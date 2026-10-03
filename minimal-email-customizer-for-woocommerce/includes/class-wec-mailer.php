<?php
defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WooCommerce emails: fixed header/footer, subject, heading, intro,
 * additional content and optional full custom body.
 */
class MECW_Mailer {

	private static $instance = null;

	/** ob level of our discard-buffer while a custom body email is being built (0 = inactive). */
	private $buffer_level = 0;

	/** @var WC_Email|null */
	private $current_email = null;

	private $hooked_ids = array();

	/** ob level of the buffer holding WooCommerce's default text (text-only mode); 0 = inactive. */
	private $text_level = 0;

	/** Template for the replacement text in text-only mode. */
	private $text_tpl = '';

	/** Whether the current email's template asked for "additional content". */
	private $additional_used = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Fires at the end of the WC_Emails constructor, before any email is triggered.
		add_action( 'woocommerce_email', array( $this, 'on_mailer' ), 5 );
		// Second pass, so emails that other plugins append to the mailer late are picked up too.
		add_action( 'woocommerce_email', array( $this, 'on_mailer' ), 999 );
		add_action( 'woocommerce_email_header', array( $this, 'header' ), 1, 2 );
		add_action( 'woocommerce_email_footer', array( $this, 'footer' ), 1, 1 );
		// Fires right where WooCommerce's default text ends and the order table begins.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'swap_text' ), 1, 4 );
		add_filter( 'woocommerce_email_styles', array( $this, 'styles' ), 99 );
	}

	private function enabled() {
		return (bool) MECW_Settings::general( 'enabled' );
	}

	/* ------------------------------------------------------------------
	 * Per-email field filters
	 * ---------------------------------------------------------------- */

	public function on_mailer( $mailer ) {
		if ( ! $this->enabled() ) {
			return;
		}
		$this->unhook_defaults( $mailer );

		foreach ( $mailer->get_emails() as $email ) {
			$id = $email->id;
			if ( ! $id || isset( $this->hooked_ids[ $id ] ) ) {
				continue;
			}
			$this->hooked_ids[ $id ] = true;

			foreach ( array( 'subject' => 'subject', 'heading' => 'heading', 'additional_content' => 'additional' ) as $filter => $field ) {
				add_filter(
					"woocommerce_email_{$filter}_{$id}",
					function ( $value, $object = null, $email_obj = null ) use ( $id, $field ) {
						return $this->filter_field( $id, $field, $value, $email_obj );
					},
					20,
					3
				);
			}
		}
	}

	private function filter_field( $id, $field, $value, $email ) {
		if ( ! $this->enabled() ) {
			return $value;
		}
		$cfg = MECW_Settings::email( $id );
		$tpl = trim( $cfg[ $field ] );
		if ( '' === $tpl ) {
			return $value;
		}
		if ( 'additional' === $field ) {
			$this->additional_used = true;
		}

		if ( $email instanceof WC_Email ) {
			$tpl = $email->format_string( $tpl );
		}
		$html = ( 'additional' === $field ) && $email instanceof WC_Email && 'plain' !== $email->get_email_type();

		return MECW_Shortcodes::render( $tpl, MECW_Shortcodes::context( $email instanceof WC_Email ? $email : null ), $html );
	}

	private function unhook_defaults( $mailer = null ) {
		$mailer = $mailer ? $mailer : WC()->mailer();
		remove_action( 'woocommerce_email_header', array( $mailer, 'email_header' ) );
		remove_action( 'woocommerce_email_footer', array( $mailer, 'email_footer' ) );
	}

	/* ------------------------------------------------------------------
	 * Header / footer output
	 * ---------------------------------------------------------------- */

	public function header( $heading, $email = null ) {
		if ( ! $this->enabled() ) {
			return;
		}
		$this->unhook_defaults();
		$this->current_email   = $email instanceof WC_Email ? $email : null;
		$this->additional_used = false;
		$this->text_level      = 0;

		$cfg = $this->current_email ? MECW_Settings::email( $this->current_email->id ) : MECW_Settings::email( '' );
		$ctx = MECW_Shortcodes::context( $this->current_email );

		if ( 'custom' === $cfg['body_mode'] && trim( $cfg['custom_body'] ) !== '' ) {
			// Swallow WooCommerce's default body; we print our own in footer().
			ob_start();
			$this->buffer_level = ob_get_level();
			return;
		}

		echo $this->open( $heading, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( 'text' === $cfg['body_mode'] && trim( $cfg['body_text'] ) !== '' ) {
			// Hold back WooCommerce's default text; swap_text() replaces it when the order table starts.
			$this->text_tpl = $this->current_email ? $this->current_email->format_string( $cfg['body_text'] ) : $cfg['body_text'];
			ob_start();
			$this->text_level = ob_get_level();
			return;
		}

		if ( trim( $cfg['intro'] ) !== '' ) {
			$intro = $this->current_email ? $this->current_email->format_string( $cfg['intro'] ) : $cfg['intro'];
			echo $this->block( $intro, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/**
	 * Text-only mode: drop the default text printed since the heading and print ours instead.
	 */
	public function swap_text() {
		if ( ! $this->text_level || ob_get_level() !== $this->text_level ) {
			return;
		}
		ob_end_clean();
		$this->text_level = 0;
		echo $this->block( $this->text_tpl, MECW_Shortcodes::context( $this->current_email ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function footer( $email = null ) {
		if ( ! $this->enabled() ) {
			return;
		}
		$email = $email instanceof WC_Email ? $email : $this->current_email;
		$ctx   = MECW_Shortcodes::context( $email );

		if ( $this->text_level ) {
			// No order table in this email (e.g. account emails): keep the default content, our text goes on top.
			while ( ob_get_level() > $this->text_level ) {
				ob_end_flush();
			}
			$default          = ob_get_level() >= $this->text_level ? ob_get_clean() : '';
			$this->text_level = 0;
			echo $this->block( $this->text_tpl, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo $default; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		if ( $this->buffer_level ) {
			while ( ob_get_level() >= $this->buffer_level && ob_get_level() > 0 ) {
				ob_end_clean();
			}
			$this->buffer_level = 0;

			$cfg     = MECW_Settings::email( $email ? $email->id : '' );
			$heading = ( $email && $email->get_heading() ) ? $email->get_heading() : '';
			$body    = $email ? $email->format_string( $cfg['custom_body'] ) : $cfg['custom_body'];

			echo $this->open( $heading, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->block( $body, $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		// Some third-party templates never print WooCommerce's "additional content"; do it for them.
		if ( $email && ! $this->additional_used ) {
			$cfg = MECW_Settings::email( $email->id );
			if ( trim( $cfg['additional'] ) !== '' && 'custom' !== $cfg['body_mode'] && 'plain' !== $email->get_email_type() ) {
				echo $this->block( $email->format_string( $cfg['additional'] ), $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}

		echo $this->close( $ctx ); // phpcs:ignore WordPress.Security.EscapeOutput
		$this->current_email = null;
	}

	/**
	 * Turn an editor-authored template into HTML with shortcodes replaced.
	 */
	private function block( $tpl, $ctx ) {
		$tpl = wpautop( $tpl );
		// Don't leave block-level shortcodes wrapped in <p>.
		$tpl = preg_replace( '#<p>\s*(\[(?:order_details|customer_details|addresses|button)[^\]]*\])\s*</p>#i', '$1', $tpl );
		return MECW_Shortcodes::render( $tpl, $ctx, true );
	}

	/**
	 * Resolve the body and heading font stacks.
	 *
	 * @return array [ body, heading ]
	 */
	public static function font_stacks( $g ) {
		$fonts = MECW_Settings::fonts();
		$body  = '' !== $g['font_custom'] ? $g['font_custom'] : $fonts[ $g['font'] ][1];
		$head  = ( 'inherit' === $g['heading_font'] || ! isset( $fonts[ $g['heading_font'] ] ) ) ? $body : $fonts[ $g['heading_font'] ][1];
		return array( $body, $head );
	}

	private function heading_style( $g, $color ) {
		$stacks = self::font_stacks( $g );
		return sprintf(
			'margin:0;font-family:%s;font-size:%dpx;font-weight:%s;line-height:1.3;color:%s;text-transform:%s;letter-spacing:%dpx;text-align:%s;',
			$stacks[1],
			(int) $g['heading_size'],
			$g['heading_weight'],
			$color,
			$g['heading_transform'],
			(int) $g['heading_spacing'],
			$g['header_align']
		);
	}

	private function open( $heading, $ctx ) {
		$g       = MECW_Settings::general();
		$stacks  = self::font_stacks( $g );
		$font    = $stacks[0];
		$width   = (int) $g['width'];
		$align   = $g['header_align'];
		$cp      = (int) $g['content_padding'];
		$pp      = (int) $g['page_padding'];
		$radius  = (int) $g['radius'];
		$inline  = 'inline' === $g['heading_style'];
		$has_h   = '' !== trim( (string) $heading );

		$tagline = trim( $g['header_text'] ) !== '' ? $this->block( $g['header_text'], $ctx ) : '';

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
</head>
<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin:0;padding:0;background-color:<?php echo esc_attr( $g['page_bg'] ); ?>;">
<div id="wec-wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="background-color:<?php echo esc_attr( $g['page_bg'] ); ?>;margin:0;padding:0;width:100%;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:<?php echo esc_attr( $g['page_bg'] ); ?>;">
<tr><td align="center" style="padding:<?php echo esc_attr( $pp ); ?>px 12px;">
<table role="presentation" id="wec-container" width="<?php echo esc_attr( $width ); ?>" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:<?php echo esc_attr( $width ); ?>px;background-color:<?php echo esc_attr( $g['body_bg'] ); ?>;border-radius:<?php echo esc_attr( $radius ); ?>px;overflow:hidden;">

	<tr><td class="wec-header" align="<?php echo esc_attr( $align ); ?>" style="background-color:<?php echo esc_attr( $g['header_bg'] ); ?>;color:<?php echo esc_attr( $g['header_color'] ); ?>;padding:28px <?php echo esc_attr( $cp ); ?>px;text-align:<?php echo esc_attr( $align ); ?>;font-family:<?php echo esc_attr( $font ); ?>;">
		<?php if ( $g['logo_url'] ) : ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $g['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" width="<?php echo esc_attr( (int) $g['logo_width'] ); ?>" style="display:inline-block;border:0;max-width:100%;height:auto;width:<?php echo esc_attr( (int) $g['logo_width'] ); ?>px;" /></a>
		<?php else : ?>
			<div style="font-size:24px;font-weight:bold;line-height:1.3;color:<?php echo esc_attr( $g['header_color'] ); ?>;"><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></div>
		<?php endif; ?>
		<?php if ( $tagline ) : ?>
			<div class="wec-tagline" style="margin-top:8px;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $g['header_color'] ); ?>;"><?php echo $tagline; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
	</td></tr>

	<?php if ( $has_h && ! $inline ) : ?>
	<tr><td class="wec-heading" align="<?php echo esc_attr( $align ); ?>" style="background-color:<?php echo esc_attr( $g['heading_bg'] ); ?>;padding:24px <?php echo esc_attr( $cp ); ?>px;text-align:<?php echo esc_attr( $align ); ?>;font-family:<?php echo esc_attr( $font ); ?>;">
		<h1 style="<?php echo esc_attr( $this->heading_style( $g, $g['heading_color'] ) ); ?>"><?php echo esc_html( $heading ); ?></h1>
	</td></tr>
	<?php endif; ?>

	<tr><td class="wec-body" align="<?php echo is_rtl() ? 'right' : 'left'; ?>" style="padding:32px <?php echo esc_attr( $cp ); ?>px;font-family:<?php echo esc_attr( $font ); ?>;font-size:<?php echo esc_attr( (int) $g['font_size'] ); ?>px;line-height:<?php echo esc_attr( round( $g['line_height'] / 100, 2 ) ); ?>;color:<?php echo esc_attr( $g['text_color'] ); ?>;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
	<?php if ( $has_h && $inline ) : ?>
		<h1 class="wec-inline-heading" style="<?php echo esc_attr( $this->heading_style( $g, $g['accent'] ) ); ?>margin-bottom:20px;"><?php echo esc_html( $heading ); ?></h1>
	<?php endif; ?>
<?php
		return ob_get_clean();
	}

	private function close( $ctx ) {
		$g      = MECW_Settings::general();
		$stacks = self::font_stacks( $g );
		$font   = $stacks[0];
		$cp     = (int) $g['content_padding'];

		$footer = trim( $g['footer_text'] ) !== '' ? $this->block( $g['footer_text'], $ctx ) : '';

		$social_labels = array(
			'social_facebook'  => 'Facebook',
			'social_instagram' => 'Instagram',
			'social_x'         => 'X',
			'social_linkedin'  => 'LinkedIn',
			'social_youtube'   => 'YouTube',
		);
		$links = array();
		foreach ( $social_labels as $key => $label ) {
			if ( ! empty( $g[ $key ] ) ) {
				$links[] = '<a href="' . esc_url( $g[ $key ] ) . '" style="color:' . esc_attr( $g['footer_color'] ) . ';text-decoration:underline;">' . esc_html( $label ) . '</a>';
			}
		}

		ob_start();
		?>
	</td></tr>

	<?php if ( $footer || $links ) : ?>
	<tr><td class="wec-footer" align="center" style="background-color:<?php echo esc_attr( $g['footer_bg'] ); ?>;color:<?php echo esc_attr( $g['footer_color'] ); ?>;padding:28px <?php echo esc_attr( $cp ); ?>px;text-align:center;font-family:<?php echo esc_attr( $font ); ?>;font-size:<?php echo esc_attr( (int) $g['footer_size'] ); ?>px;line-height:1.6;">
		<?php if ( $links ) : ?>
			<div class="wec-social" style="margin-bottom:12px;"><?php echo implode( ' &nbsp;&middot;&nbsp; ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
		<?php echo $footer; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</td></tr>
	<?php endif; ?>

</table>
</td></tr>
</table>
</div>
</body>
</html>
<?php
		return ob_get_clean();
	}

	/**
	 * Extra CSS that WooCommerce inlines into the final email markup.
	 */
	public function styles( $css ) {
		if ( ! $this->enabled() ) {
			return $css;
		}
		$g      = MECW_Settings::general();
		$stacks = self::font_stacks( $g );
		$font   = $stacks[0];
		$head   = $stacks[1];
		$deco   = $g['link_underline'] ? 'underline' : 'none';
		$lh     = round( $g['line_height'] / 100, 2 );

		$css .= "\nbody{background-color:{$g['page_bg']};}";
		$css .= "\n.wec-body{font-family:{$font};color:{$g['text_color']};}";
		$css .= "\n.wec-body p,.wec-body li{margin:0 0 16px;font-family:{$font};font-size:{$g['font_size']}px;line-height:{$lh};color:{$g['text_color']};}";
		$css .= "\n.wec-body td,.wec-body th{font-family:{$font};}";
		// !important so WooCommerce's own link rules (a, .link, h2 a) can never win over the chosen link colour.
		$css .= "\n.wec-body a,.wec-body a.link,.wec-body h2 a,.wec-body h3 a{color:{$g['accent']} !important;font-weight:normal;text-decoration:{$deco};}";
		$css .= "\n.wec-body a.wec-button{color:{$g['button_color']} !important;background-color:{$g['button_bg']} !important;text-decoration:none;}";
		$css .= "\n.wec-body h2,.wec-body h3{color:{$g['accent']};font-family:{$head};font-size:{$g['h2_size']}px;font-weight:{$g['heading_weight']};text-transform:{$g['heading_transform']};}";
		$css .= "\n.wec-body table.td,.wec-body .td{border-color:{$g['border_color']};color:{$g['text_color']};}";
		$css .= "\n.wec-body hr{border-color:{$g['border_color']};}";
		$css .= "\n.wec-footer p{margin:0 0 8px;color:{$g['footer_color']};font-family:{$font};font-size:{$g['footer_size']}px;}";
		$css .= "\n.wec-footer a{color:{$g['footer_color']} !important;}";

		if ( '' !== $g['custom_css'] ) {
			$css .= "\n" . $g['custom_css'];
		}

		return $css;
	}
}

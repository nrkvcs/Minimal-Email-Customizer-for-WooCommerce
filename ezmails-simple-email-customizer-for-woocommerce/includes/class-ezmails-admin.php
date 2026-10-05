<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin screen: WooCommerce → Email Customizer.
 */
class EZMAILS_Admin {

	const SLUG = 'ezmails';

	private static $instance = null;
	private $hook            = '';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_ezmails_save', array( $this, 'save' ) );
		add_action( 'admin_post_ezmails_export', array( $this, 'export' ) );
		add_action( 'admin_post_ezmails_import', array( $this, 'import' ) );
		add_action( 'wp_ajax_ezmails_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_ezmails_send_test', array( $this, 'ajax_send_test' ) );
	}

	public function menu() {
		$this->hook = add_submenu_page(
			'woocommerce',
			__( 'EZmails Email Customizer', 'ezmails-simple-email-customizer-for-woocommerce' ),
			__( 'EZmails', 'ezmails-simple-email-customizer-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'ezmails-admin', EZMAILS_URL . 'assets/admin.css', array(), EZMAILS_VERSION );
		wp_enqueue_script( 'ezmails-admin', EZMAILS_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), EZMAILS_VERSION, true );
		wp_localize_script(
			'ezmails-admin',
			'ezmailsAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ezmails_ajax' ),
				'i18n'  => array(
					'copied'    => __( 'Copied to clipboard — paste it into a field.', 'ezmails-simple-email-customizer-for-woocommerce' ),
					'sending'   => __( 'Sending…', 'ezmails-simple-email-customizer-for-woocommerce' ),
					'chooseImg' => __( 'Choose logo', 'ezmails-simple-email-customizer-for-woocommerce' ),
					'useImg'    => __( 'Use this image', 'ezmails-simple-email-customizer-for-woocommerce' ),
					'error'     => __( 'Request failed.', 'ezmails-simple-email-customizer-for-woocommerce' ),
				),
			)
		);
	}

	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}
		check_admin_referer( 'ezmails_save' );

		$input = isset( $_POST['ezmails'] ) ? wp_unslash( $_POST['ezmails'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		EZMAILS_Settings::update( $input );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Field helpers (return escaped HTML)
	 * ---------------------------------------------------------------- */

	private function name( $path ) {
		return 'ezmails[' . implode( '][', $path ) . ']';
	}

	private function input( $path, $value, $class = 'regular-text', $placeholder = '', $insertable = false ) {
		return sprintf(
			'<input type="text" name="%s" value="%s" class="%s%s" placeholder="%s">',
			esc_attr( $this->name( $path ) ),
			esc_attr( $value ),
			esc_attr( $class ),
			$insertable ? ' ezmails-insertable' : '',
			esc_attr( $placeholder )
		);
	}

	private function textarea( $path, $value, $rows = 4, $placeholder = '' ) {
		return sprintf(
			'<textarea name="%s" rows="%d" class="large-text ezmails-insertable" placeholder="%s">%s</textarea>',
			esc_attr( $this->name( $path ) ),
			(int) $rows,
			esc_attr( $placeholder ),
			esc_textarea( $value )
		);
	}

	private function color( $path, $value ) {
		return sprintf( '<input type="text" name="%s" value="%s" class="ezmails-color">', esc_attr( $this->name( $path ) ), esc_attr( $value ) );
	}

	private function select( $path, $value, $options, $class = '' ) {
		$html = sprintf( '<select name="%s" class="%s">', esc_attr( $this->name( $path ) ), esc_attr( $class ) );
		foreach ( $options as $k => $label ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	private function num( $path, $value, $unit = 'px' ) {
		return $this->input( $path, $value, 'small-text' ) . ' ' . esc_html( $unit );
	}

	private function row( $label, $field, $desc = '', $class = '' ) {
		echo '<tr class="' . esc_attr( $class ) . '"><th scope="row">' . esc_html( $label ) . '</th><td>' . $field; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ---------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$g = EZMAILS_Settings::general();
		?>
		<div class="wrap ezmails-wrap" data-tab="design">
			<h1><?php esc_html_e( 'Email Customizer', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h1>

			<?php $this->tools_notice(); ?>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper ezmails-tabs">
				<a href="#design" data-tab="design" class="nav-tab nav-tab-active"><?php esc_html_e( 'Header & Footer', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<a href="#style" data-tab="style" class="nav-tab"><?php esc_html_e( 'Design', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<a href="#emails" data-tab="emails" class="nav-tab"><?php esc_html_e( 'Email Content', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<a href="#codes" data-tab="codes" class="nav-tab"><?php esc_html_e( 'Shortcodes', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<a href="#preview" data-tab="preview" class="nav-tab"><?php esc_html_e( 'Preview & Test', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<a href="#tools" data-tab="tools" class="nav-tab"><?php esc_html_e( 'Import / Export', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ezmails-form">
				<input type="hidden" name="action" value="ezmails_save">
				<?php wp_nonce_field( 'ezmails_save' ); ?>

				<div class="ezmails-layout">
					<div class="ezmails-main">
						<div class="ezmails-panel" id="ezmails-panel-design"><?php $this->panel_design( $g ); ?></div>
						<div class="ezmails-panel" id="ezmails-panel-style" style="display:none"><?php $this->panel_style( $g ); ?></div>
						<div class="ezmails-panel" id="ezmails-panel-emails" style="display:none"><?php $this->panel_emails(); ?></div>
						<div class="ezmails-panel" id="ezmails-panel-codes" style="display:none"><?php $this->panel_codes(); ?></div>
						<div class="ezmails-panel" id="ezmails-panel-preview" style="display:none"><?php $this->panel_preview(); ?></div>
					</div>
					<aside class="ezmails-side"><?php $this->sidebar(); ?></aside>
				</div>

				<p class="submit ezmails-save"><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button></p>
			</form>

			<div class="ezmails-panel" id="ezmails-panel-tools" style="display:none"><?php $this->panel_tools(); ?></div>
		</div>
		<?php
	}

	private function panel_design( $g ) {
		?>
		<h2><?php esc_html_e( 'General', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row(
				__( 'Enable customizer', 'ezmails-simple-email-customizer-for-woocommerce' ),
				'<input type="hidden" name="' . esc_attr( $this->name( array( 'general', 'enabled' ) ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $this->name( array( 'general', 'enabled' ) ) ) . '" value="1"' . checked( $g['enabled'], 1, false ) . '> ' . esc_html__( 'Apply the fixed header/footer and custom email content to all WooCommerce emails', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</label>',
				__( 'Untick to instantly return to the standard WooCommerce emails. Plain-text emails only use the custom subject and additional content.', 'ezmails-simple-email-customizer-for-woocommerce' )
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Fixed header', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$logo  = '<div class="ezmails-logo-field">';
			$logo .= '<input type="text" name="' . esc_attr( $this->name( array( 'general', 'logo_url' ) ) ) . '" value="' . esc_attr( $g['logo_url'] ) . '" class="regular-text ezmails-logo-url"> ';
			$logo .= '<button type="button" class="button ezmails-media">' . esc_html__( 'Select image', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</button> ';
			$logo .= '<button type="button" class="button-link ezmails-media-clear">' . esc_html__( 'Remove', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</button>';
			$logo .= '<div class="ezmails-logo-preview">' . ( $g['logo_url'] ? '<img src="' . esc_url( $g['logo_url'] ) . '" alt="">' : '' ) . '</div></div>';
			$this->row( __( 'Logo', 'ezmails-simple-email-customizer-for-woocommerce' ), $logo, __( 'Leave empty to show the site title as text.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Logo width (px)', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->input( array( 'general', 'logo_width' ), $g['logo_width'], 'small-text' ) );
			$this->row( __( 'Text under the logo', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->textarea( array( 'general', 'header_text' ), $g['header_text'], 2 ), __( 'Optional tagline. Shortcodes allowed.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Header background', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'header_bg' ), $g['header_bg'] ) );
			$this->row( __( 'Header text colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'header_color' ), $g['header_color'] ) );
			$this->row( __( 'Heading bar background', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'heading_bg' ), $g['heading_bg'] ), __( 'The bar that shows each email\'s heading.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Heading text colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'heading_color' ), $g['heading_color'] ) );
			$this->row(
				__( 'Header alignment', 'ezmails-simple-email-customizer-for-woocommerce' ),
				$this->select(
					array( 'general', 'header_align' ),
					$g['header_align'],
					array(
						'left'   => __( 'Left', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'center' => __( 'Center', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'right'  => __( 'Right', 'ezmails-simple-email-customizer-for-woocommerce' ),
					)
				)
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Fixed footer', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Footer content', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></th>
				<td>
					<?php
					wp_editor(
						$g['footer_text'],
						'ezmails_footer_text',
						array(
							'textarea_name' => $this->name( array( 'general', 'footer_text' ) ),
							'textarea_rows' => 6,
							'media_buttons' => false,
							'teeny'         => true,
							'editor_class'  => 'ezmails-insertable',
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Shortcodes allowed, e.g. [site_title], [store_address], [current_year].', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<?php
			$this->row( __( 'Footer background', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'footer_bg' ), $g['footer_bg'] ) );
			$this->row( __( 'Footer text colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'footer_color' ), $g['footer_color'] ) );
			foreach ( array(
				'social_facebook'  => 'Facebook',
				'social_instagram' => 'Instagram',
				'social_x'         => 'X (Twitter)',
				'social_linkedin'  => 'LinkedIn',
				'social_youtube'   => 'YouTube',
			) as $key => $label ) {
				/* translators: %s: social network name */
				$this->row( sprintf( __( '%s URL', 'ezmails-simple-email-customizer-for-woocommerce' ), $label ), $this->input( array( 'general', $key ), $g[ $key ], 'regular-text', 'https://' ) );
			}
			?>
		</table>
		<?php
	}

	private function panel_style( $g ) {
		$fonts = array();
		foreach ( EZMAILS_Settings::fonts() as $k => $f ) {
			$fonts[ $k ] = $f[0];
		}
		$head_fonts = array( 'inherit' => __( 'Same as body font', 'ezmails-simple-email-customizer-for-woocommerce' ) ) + $fonts;
		?>
		<h2><?php esc_html_e( 'Presets', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<p>
			<button type="button" class="button ezmails-preset" data-preset="classic"><?php esc_html_e( 'Classic', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button>
			<button type="button" class="button ezmails-preset" data-preset="dark"><?php esc_html_e( 'Dark', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button>
			<button type="button" class="button ezmails-preset" data-preset="minimal"><?php esc_html_e( 'Minimal', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button>
			<span class="description"><?php esc_html_e( 'Fills in colours, fonts and layout (including header and footer colours). Nothing changes until you save.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span>
		</p>

		<h2><?php esc_html_e( 'Colours', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row( __( 'Page background', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'page_bg' ), $g['page_bg'] ) );
			$this->row( __( 'Email body background', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'body_bg' ), $g['body_bg'] ) );
			$this->row( __( 'Text colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'text_color' ), $g['text_color'] ) );
			$this->row( __( 'Accent / link colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'accent' ), $g['accent'] ), __( 'Used for links and the sub-headings of the order details.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Border / divider colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'border_color' ), $g['border_color'] ), __( 'Order table borders and lines.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			?>
		</table>

		<h2><?php esc_html_e( 'Typography', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row( __( 'Body font', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->select( array( 'general', 'font' ), $g['font'], $fonts ), __( 'Email-safe font stacks, so every mail client renders them.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Custom font stack', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->input( array( 'general', 'font_custom' ), $g['font_custom'], 'regular-text', "'Open Sans', Arial, sans-serif" ), __( 'Optional. Overrides the body font. Always end with a safe fallback such as Arial, because most mail clients ignore web fonts.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Heading font', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->select( array( 'general', 'heading_font' ), $g['heading_font'], $head_fonts ) );
			$this->row( __( 'Body text size', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'font_size' ), $g['font_size'] ) );
			$this->row( __( 'Line height', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'line_height' ), $g['line_height'], '%' ), __( '160% is a comfortable default.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			$this->row( __( 'Email heading size', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'heading_size' ), $g['heading_size'] ) );
			$this->row( __( 'Order details heading size', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'h2_size' ), $g['h2_size'] ) );
			$this->row(
				__( 'Heading weight', 'ezmails-simple-email-customizer-for-woocommerce' ),
				$this->select(
					array( 'general', 'heading_weight' ),
					$g['heading_weight'],
					array(
						'400' => __( 'Normal', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'600' => __( 'Semi-bold', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'700' => __( 'Bold', 'ezmails-simple-email-customizer-for-woocommerce' ),
					)
				)
			);
			$this->row(
				__( 'Heading case', 'ezmails-simple-email-customizer-for-woocommerce' ),
				$this->select(
					array( 'general', 'heading_transform' ),
					$g['heading_transform'],
					array(
						'none'       => __( 'As typed', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'uppercase'  => __( 'UPPERCASE', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'capitalize' => __( 'Capitalize Each Word', 'ezmails-simple-email-customizer-for-woocommerce' ),
					)
				)
			);
			$this->row( __( 'Heading letter spacing', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'heading_spacing' ), $g['heading_spacing'] ) );
			$this->row( __( 'Footer text size', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'footer_size' ), $g['footer_size'] ) );
			$this->row(
				__( 'Underline links', 'ezmails-simple-email-customizer-for-woocommerce' ),
				'<input type="hidden" name="' . esc_attr( $this->name( array( 'general', 'link_underline' ) ) ) . '" value="0"><label><input type="checkbox" name="' . esc_attr( $this->name( array( 'general', 'link_underline' ) ) ) . '" value="1"' . checked( $g['link_underline'], 1, false ) . '> ' . esc_html__( 'Underline links in the email body', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</label>'
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Layout', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row( __( 'Email width', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'width' ), $g['width'] ) );
			$this->row( __( 'Corner radius', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'radius' ), $g['radius'] ) );
			$this->row( __( 'Space around the email', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'page_padding' ), $g['page_padding'] ) );
			$this->row( __( 'Inner side padding', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'content_padding' ), $g['content_padding'] ) );
			$this->row(
				__( 'Heading style', 'ezmails-simple-email-customizer-for-woocommerce' ),
				$this->select(
					array( 'general', 'heading_style' ),
					$g['heading_style'],
					array(
						'bar'    => __( 'Coloured bar under the header', 'ezmails-simple-email-customizer-for-woocommerce' ),
						'inline' => __( 'Plain heading inside the email body', 'ezmails-simple-email-customizer-for-woocommerce' ),
					)
				)
			);
			?>
		</table>

		<h2><?php esc_html_e( 'Buttons', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$this->row( __( 'Button colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'button_bg' ), $g['button_bg'] ) );
			$this->row( __( 'Button text colour', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->color( array( 'general', 'button_color' ), $g['button_color'] ) );
			$this->row( __( 'Button corner radius', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->num( array( 'general', 'button_radius' ), $g['button_radius'] ), __( 'Add a button to any text with <code>[button url="[order_view_url]" text="View your order"]</code>.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
			?>
		</table>

		<?php
	}

	private function panel_emails() {
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		$groups = array(
			'customer' => array( __( 'Customer emails', 'ezmails-simple-email-customizer-for-woocommerce' ), array() ),
			'admin'    => array( __( 'Admin emails', 'ezmails-simple-email-customizer-for-woocommerce' ), array() ),
		);
		foreach ( $emails as $email ) {
			if ( ! $email->id ) {
				continue;
			}
			$groups[ $email->is_customer_email() ? 'customer' : 'admin' ][1][] = $email;
		}
		?>
		<p><?php esc_html_e( 'Emails added by other plugins (for example custom order status emails) are listed too and marked "custom / plugin email".', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<p><?php esc_html_e( 'Leave a field empty to keep the WooCommerce default. Every field accepts shortcodes and WooCommerce {placeholders}. Click a shortcode in the sidebar to insert it where your cursor is.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<?php
		foreach ( $groups as $group ) {
			if ( ! $group[1] ) {
				continue;
			}
			echo '<h2>' . esc_html( $group[0] ) . '</h2>';
			foreach ( $group[1] as $email ) {
				$this->email_box( $email );
			}
		}
	}

	private function email_box( $email ) {
		$id       = EZMAILS_Settings::key( $email->id );
		$cfg      = EZMAILS_Settings::email( $id );
		$is_core  = 0 === strpos( get_class( $email ), 'WC_Email_' );
		$p        = function ( $field ) use ( $id ) {
			return array( 'emails', $id, $field );
		};
		$custom = ( 'custom' === $cfg['body_mode'] );
		$text   = ( 'text' === $cfg['body_mode'] );
		?>
		<details class="ezmails-email" data-mode="<?php echo esc_attr( $cfg['body_mode'] ); ?>" <?php echo ( $cfg['subject'] || $cfg['heading'] || $cfg['intro'] || $cfg['body_text'] || $cfg['additional'] || $custom ) ? 'open' : ''; ?>>
			<summary>
				<strong><?php echo esc_html( $email->get_title() ); ?></strong>
				<?php if ( ! $email->is_enabled() ) : ?>
					<span class="ezmails-badge ezmails-badge-off"><?php esc_html_e( 'disabled in WooCommerce', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span>
				<?php endif; ?>
				<?php if ( ! $is_core ) : ?>
					<span class="ezmails-badge ezmails-badge-ext"><?php esc_html_e( 'custom / plugin email', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span>
				<?php endif; ?>
				<?php if ( $custom ) : ?>
					<span class="ezmails-badge"><?php esc_html_e( 'custom body', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span>
				<?php elseif ( $text ) : ?>
					<span class="ezmails-badge"><?php esc_html_e( 'custom text', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span>
				<?php endif; ?>
			</summary>
			<div class="ezmails-email-body">
				<?php if ( $email->get_description() ) : ?>
					<p class="description"><?php echo esc_html( wp_strip_all_tags( $email->get_description() ) ); ?></p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<?php
					$this->row( __( 'Subject', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->input( $p( 'subject' ), $cfg['subject'], 'large-text', $email->get_default_subject(), true ) );
					$this->row( __( 'Email heading', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->input( $p( 'heading' ), $cfg['heading'], 'large-text', $email->get_default_heading(), true ) );
					$this->row(
						__( 'Body mode', 'ezmails-simple-email-customizer-for-woocommerce' ),
						$this->select(
							$p( 'body_mode' ),
							$cfg['body_mode'],
							array(
								'default' => __( 'WooCommerce default body (+ optional intro and extra text)', 'ezmails-simple-email-customizer-for-woocommerce' ),
								'text'    => __( 'Custom text only (keeps the order details)', 'ezmails-simple-email-customizer-for-woocommerce' ),
								'custom'  => __( 'Fully custom body (replaces everything)', 'ezmails-simple-email-customizer-for-woocommerce' ),
							),
							'ezmails-mode-select'
						),
						__( '"Custom text only" swaps just the message between the heading and the order details. "Fully custom body" gives you full control; use [order_details] to include the order table.', 'ezmails-simple-email-customizer-for-woocommerce' )
					);
					$this->row( __( 'Email text', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->textarea( $p( 'body_text' ), $cfg['body_text'], 6, "Hi [customer_name],\n\nThanks for your order! We're getting it ready and will let you know as soon as it ships." ), __( 'Replaces WooCommerce\'s default message between the heading and the order details. The order table, addresses and anything after them stay as they are. For emails without an order table (such as new account), this text is shown above the default content. HTML allowed.', 'ezmails-simple-email-customizer-for-woocommerce' ), 'ezmails-for ezmails-for-text' );
					$this->row( __( 'Intro text', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->textarea( $p( 'intro' ), $cfg['intro'], 4 ), __( 'Shown at the top of the body, before WooCommerce\'s default content. HTML allowed.', 'ezmails-simple-email-customizer-for-woocommerce' ), 'ezmails-for ezmails-for-default' );
					$this->row( __( 'Additional content', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->textarea( $p( 'additional' ), $cfg['additional'], 4 ), __( 'Shown after the default content (replaces WooCommerce\'s "Additional content" field).', 'ezmails-simple-email-customizer-for-woocommerce' ), 'ezmails-for ezmails-for-default ezmails-for-text' );
					$this->row( __( 'Custom body', 'ezmails-simple-email-customizer-for-woocommerce' ), $this->textarea( $p( 'custom_body' ), $cfg['custom_body'], 10, "Hi [customer_name],\n\nThanks for your order #[order_number]!\n\n[order_details]\n\n[addresses]" ), __( 'Replaces the whole body. HTML allowed.', 'ezmails-simple-email-customizer-for-woocommerce' ), 'ezmails-for ezmails-for-custom' );
					?>
				</table>
				<p><a href="#preview" class="button ezmails-goto-preview" data-email="<?php echo esc_attr( $email->id ); ?>"><?php esc_html_e( 'Preview this email', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></a>
				<span class="description"><?php esc_html_e( '(save first to preview your latest changes)', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></span></p>
			</div>
		</details>
		<?php
	}

	private function panel_codes() {
		$groups = array();
		foreach ( EZMAILS_Shortcodes::registry() as $name => $entry ) {
			$groups[ $entry['group'] ][ $name ] = $entry;
		}
		?>
		<p><?php esc_html_e( 'Use these shortcodes in any text field of this plugin (subject, heading, intro, additional content, custom body, header text and footer). Order shortcodes are empty for emails that have no order, such as new-account emails.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<?php foreach ( $groups as $group => $entries ) : ?>
			<h2><?php echo esc_html( $group ); ?></h2>
			<table class="widefat striped ezmails-codes">
				<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td class="ezmails-code-name"><code><?php echo esc_html( $entry['example'] ); ?></code></td>
						<td><?php echo esc_html( $entry['label'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
		<p class="description"><?php esc_html_e( 'Developers: add your own with the "ezmails_shortcodes" filter.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<?php
	}

	private function panel_preview() {
		$emails = WC()->mailer()->get_emails();
		?>
		<p><?php esc_html_e( 'Previews use your saved settings and a real order from your store (the latest one unless you enter an ID).', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<div class="ezmails-preview-bar">
			<select id="ezmails-prev-email">
				<?php foreach ( $emails as $email ) : ?>
					<?php if ( $email->id ) : ?>
						<option value="<?php echo esc_attr( $email->id ); ?>"><?php echo esc_html( $email->get_title() ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<input type="number" id="ezmails-prev-order" class="small-text" placeholder="<?php esc_attr_e( 'Order ID', 'ezmails-simple-email-customizer-for-woocommerce' ); ?>">
			<button type="button" class="button button-primary" id="ezmails-prev-load"><?php esc_html_e( 'Load preview', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button>
			<span class="ezmails-sep"></span>
			<input type="email" id="ezmails-test-to" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
			<button type="button" class="button" id="ezmails-test-send"><?php esc_html_e( 'Send test email', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button>
			<span id="ezmails-test-msg" class="ezmails-msg"></span>
		</div>
		<p id="ezmails-prev-subject" class="ezmails-subject"></p>
		<iframe id="ezmails-prev-frame" title="<?php esc_attr_e( 'Email preview', 'ezmails-simple-email-customizer-for-woocommerce' ); ?>"></iframe>
		<?php
	}

	private function sidebar() {
		$groups = array();
		foreach ( EZMAILS_Shortcodes::registry() as $name => $entry ) {
			$groups[ $entry['group'] ][ $name ] = $entry;
		}
		echo '<h3>' . esc_html__( 'Shortcodes', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Click to insert at the cursor.', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</p>';
		$first = true;
		foreach ( $groups as $group => $entries ) {
			echo '<details' . ( $first ? ' open' : '' ) . '><summary>' . esc_html( $group ) . '</summary><div class="ezmails-chips">';
			foreach ( $entries as $entry ) {
				printf(
					'<button type="button" class="ezmails-chip" data-code="%s" title="%s">%s</button>',
					esc_attr( $entry['example'] ),
					esc_attr( $entry['label'] ),
					esc_html( $entry['example'] )
				);
			}
			echo '</div></details>';
			$first = false;
		}
	}

	/* ------------------------------------------------------------------
	 * Import / export
	 * ---------------------------------------------------------------- */

	private function panel_tools() {
		?>
		<h2><?php esc_html_e( 'Export', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Download your settings as a JSON file to back them up or move them to another site.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ezmails_export">
			<?php wp_nonce_field( 'ezmails_export' ); ?>
			<p>
				<label><input type="checkbox" name="parts[]" value="general" checked> <?php esc_html_e( 'Header, footer and design', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></label><br>
				<label><input type="checkbox" name="parts[]" value="emails" checked> <?php esc_html_e( 'Email content (subjects, headings, texts, custom bodies)', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></label>
			</p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Download export file', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button></p>
		</form>

		<h2><?php esc_html_e( 'Import', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Upload a file exported from this plugin. Imported header, footer and design settings replace the current ones. Imported email content is merged: emails in the file overwrite the same emails here, all other emails stay untouched.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Importing will overwrite your current settings for the selected parts. Continue?', 'ezmails-simple-email-customizer-for-woocommerce' ) ); ?>');">
			<input type="hidden" name="action" value="ezmails_import">
			<?php wp_nonce_field( 'ezmails_import' ); ?>
			<p><input type="file" name="ezmails_file" accept=".json,application/json" required></p>
			<p>
				<label><input type="checkbox" name="parts[]" value="general" checked> <?php esc_html_e( 'Import header, footer and design', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></label><br>
				<label><input type="checkbox" name="parts[]" value="emails" checked> <?php esc_html_e( 'Import email content', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></label><br>
				<label><input type="checkbox" name="sideload" value="1"> <?php esc_html_e( 'Copy the logo into this site\'s media library', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></label>
			</p>
			<p class="description"><?php esc_html_e( 'The logo is stored as a link to the image on the original site. Tick the last option to download it into this site, otherwise it keeps pointing to the original site. Tip: export your current settings first as a backup.', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import settings', 'ezmails-simple-email-customizer-for-woocommerce' ); ?></button></p>
		</form>
		<?php
	}

	private function tools_notice() {
		$key    = 'ezmails_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['msg'] ) );
	}

	private function tools_redirect( $type, $msg ) {
		set_transient( 'ezmails_notice_' . get_current_user_id(), array( 'type' => $type, 'msg' => $msg ), 60 );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) ) . '#tools' );
		exit;
	}

	private function posted_parts() {
		$parts = isset( $_POST['parts'] ) && is_array( $_POST['parts'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['parts'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		return array_values( array_intersect( $parts, array( 'general', 'emails' ) ) );
	}

	public function export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}
		check_admin_referer( 'ezmails_export' );

		$parts = $this->posted_parts();
		if ( ! $parts ) {
			$this->tools_redirect( 'error', __( 'Select at least one part to export.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$json = wp_json_encode( EZMAILS_Settings::export_payload( $parts ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ezmails-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}
		check_admin_referer( 'ezmails_import' );

		$parts = $this->posted_parts();
		if ( ! $parts ) {
			$this->tools_redirect( 'error', __( 'Select at least one part to import.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$file = isset( $_FILES['ezmails_file'] ) ? $_FILES['ezmails_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $file || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->tools_redirect( 'error', __( 'The file could not be uploaded.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}
		if ( $file['size'] > 2 * MB_IN_BYTES ) {
			$this->tools_redirect( 'error', __( 'The file is too large for a settings export.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$data = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) ) {
			$this->tools_redirect( 'error', __( 'The file is not valid JSON.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		// Optionally copy the logo into this site's media library before saving.
		$logo_note = '';
		if ( ! empty( $_POST['sideload'] ) && in_array( 'general', $parts, true ) && ! empty( $data['settings']['general']['logo_url'] ) ) {
			$logo = esc_url_raw( (string) $data['settings']['general']['logo_url'] );
			if ( $logo && wp_parse_url( $logo, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$new = media_sideload_image( $logo, 0, null, 'src' );
				if ( is_wp_error( $new ) ) {
					$logo_note = ' ' . __( 'The logo could not be copied and still points to the original site.', 'ezmails-simple-email-customizer-for-woocommerce' );
				} else {
					$data['settings']['general']['logo_url'] = $new;
				}
			}
		}

		$result = EZMAILS_Settings::import_payload( $data, $parts );
		if ( is_wp_error( $result ) ) {
			$this->tools_redirect( 'error', $result->get_error_message() );
		}

		$bits = array();
		if ( $result['general'] ) {
			$bits[] = __( 'header, footer and design', 'ezmails-simple-email-customizer-for-woocommerce' );
		}
		if ( $result['emails'] ) {
			/* translators: %d: number of emails */
			$bits[] = sprintf( _n( 'content of %d email', 'content of %d emails', $result['emails'], 'ezmails-simple-email-customizer-for-woocommerce' ), $result['emails'] );
		}
		/* translators: %s: list of imported parts */
		$this->tools_redirect( 'success', sprintf( __( 'Import finished: %s.', 'ezmails-simple-email-customizer-for-woocommerce' ), implode( ', ', $bits ) ) . $logo_note );
	}

	/* ------------------------------------------------------------------
	 * AJAX: preview & test
	 * ---------------------------------------------------------------- */

	/**
	 * Populate a WC_Email with sample data so it can be rendered without being triggered.
	 *
	 * @return WC_Email|WP_Error
	 */
	private function prepare_email( $email_id, $order_id ) {
		$email = null;
		foreach ( WC()->mailer()->get_emails() as $candidate ) {
			if ( $candidate->id === $email_id || ( $candidate->id && EZMAILS_Settings::key( $candidate->id ) === EZMAILS_Settings::key( $email_id ) ) ) {
				$email = $candidate;
				break;
			}
		}
		if ( ! $email ) {
			return new WP_Error( 'ezmails', __( 'Unknown email.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$user = wp_get_current_user();

		if ( in_array( $email->id, array( 'customer_new_account', 'customer_reset_password' ), true ) ) {
			$email->object     = $user;
			$email->user_id    = $user->ID;
			$email->user_login = $user->user_login;
			$email->user_email = $user->user_email;
			if ( 'customer_new_account' === $email->id ) {
				$email->user_pass           = 'sample-password';
				$email->password_generated  = false;
				$email->set_password_url    = wc_get_page_permalink( 'myaccount' );
			} else {
				$email->reset_key = 'sample-reset-key';
			}
			return $email;
		}

		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			$ids   = wc_get_orders( array( 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ) );
			$order = $ids ? wc_get_order( $ids[0] ) : null;
		}
		if ( ! $order instanceof WC_Order ) {
			return new WP_Error( 'ezmails', __( 'Create at least one order in WooCommerce to preview order emails.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$email->object                          = $order;
		$email->placeholders['{order_date}']    = wc_format_datetime( $order->get_date_created() );
		$email->placeholders['{order_number}']  = $order->get_order_number();
		$email->placeholders['{order_billing_full_name}'] = $order->get_formatted_billing_full_name();
		$email->placeholders['{order_billing_company}']   = $order->get_billing_company();
		$email->customer_note                   = __( 'This is a sample customer note.', 'ezmails-simple-email-customizer-for-woocommerce' );
		$email->partial_refund                  = false;

		/**
		 * Lets third-party emails (e.g. custom order status emails) set extra properties before previewing.
		 *
		 * @param WC_Email $email Email being previewed.
		 * @param WC_Order $order Sample order.
		 */
		do_action( 'ezmails_prepare_email', $email, $order );

		return $email;
	}

	public function ajax_preview() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ezmails_ajax', '_wpnonce', false ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ezmails-simple-email-customizer-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$email_id = isset( $_GET['email_id'] ) ? sanitize_text_field( wp_unslash( $_GET['email_id'] ) ) : '';
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		$email = $this->prepare_email( $email_id, $order_id );
		if ( is_wp_error( $email ) ) {
			wp_die( esc_html( $email->get_error_message() ) );
		}

		try {
			$content = $email->get_content();
			$subject = $email->get_subject();
		} catch ( \Throwable $e ) {
			wp_die( esc_html( sprintf( /* translators: %s: error */ __( 'Could not render this email: %s', 'ezmails-simple-email-customizer-for-woocommerce' ), $e->getMessage() ) ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-EZMAILS-Subject: ' . rawurlencode( wp_strip_all_tags( $subject ) ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function ajax_send_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'ezmails_ajax', '_wpnonce', false ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ezmails-simple-email-customizer-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$to       = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		$email_id = isset( $_POST['email_id'] ) ? sanitize_text_field( wp_unslash( $_POST['email_id'] ) ) : '';
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! is_email( $to ) ) {
			wp_send_json_error( __( 'Enter a valid email address.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}
		$email = $this->prepare_email( $email_id, $order_id );
		if ( is_wp_error( $email ) ) {
			wp_send_json_error( $email->get_error_message() );
		}

		try {
			$sent = $email->send( $to, '[TEST] ' . $email->get_subject(), $email->get_content(), $email->get_headers(), array() );
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}

		if ( $sent ) {
			/* translators: %s: email address */
			wp_send_json_success( sprintf( __( 'Test email sent to %s.', 'ezmails-simple-email-customizer-for-woocommerce' ), $to ) );
		}
		wp_send_json_error( __( 'WordPress could not send the email. Check your mail configuration.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
	}
}

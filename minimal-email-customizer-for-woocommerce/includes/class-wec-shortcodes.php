<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shortcode registry and replacement engine.
 *
 * Shortcodes such as [order_number] are replaced directly in our own text fields (subject, heading,
 * intro, additional content, custom body, header and footer). They are not registered as WordPress
 * shortcodes, so they never execute on the front end.
 */
class MECW_Shortcodes {

	private static $registry = null;

	/**
	 * name => [ group, label, html (bool), cb (callable), example (optional) ]
	 */
	public static function registry() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$r = array();

		$add = function ( $name, $group, $label, $html, $cb, $example = '' ) use ( &$r ) {
			$r[ $name ] = array(
				'group'   => $group,
				'label'   => $label,
				'html'    => $html,
				'cb'      => $cb,
				'example' => $example ? $example : '[' . $name . ']',
			);
		};

		// Wrap callbacks that need an order / user.
		$o = function ( $cb ) {
			return function ( $ctx, $atts ) use ( $cb ) {
				return $ctx['order'] instanceof WC_Order ? $cb( $ctx['order'], $ctx, $atts ) : '';
			};
		};
		$u = function ( $cb ) {
			return function ( $ctx, $atts ) use ( $cb ) {
				return $ctx['user'] instanceof WP_User ? $cb( $ctx['user'], $ctx, $atts ) : '';
			};
		};
		$price = function ( $order, $amount ) {
			return wc_price( $amount, array( 'currency' => $order->get_currency() ) );
		};
		$e = function ( $email, $prop ) {
			return ( $email && isset( $email->$prop ) && is_scalar( $email->$prop ) ) ? (string) $email->$prop : '';
		};

		$g_store    = __( 'Store', 'minimal-email-customizer-for-woocommerce' );
		$g_order    = __( 'Order', 'minimal-email-customizer-for-woocommerce' );
		$g_customer = __( 'Customer', 'minimal-email-customizer-for-woocommerce' );
		$g_billing  = __( 'Billing', 'minimal-email-customizer-for-woocommerce' );
		$g_shipping = __( 'Shipping', 'minimal-email-customizer-for-woocommerce' );
		$g_blocks   = __( 'Order blocks (HTML)', 'minimal-email-customizer-for-woocommerce' );
		$g_account  = __( 'Account emails', 'minimal-email-customizer-for-woocommerce' );

		// Store.
		$add( 'site_title', $g_store, __( 'Site title', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return wp_specialchars_decode( get_bloginfo( 'name', 'display' ), ENT_QUOTES );
		} );
		$add( 'site_url', $g_store, __( 'Site URL', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return home_url( '/' );
		} );
		$add( 'shop_url', $g_store, __( 'Shop page URL', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return wc_get_page_permalink( 'shop' );
		} );
		$add( 'my_account_url', $g_store, __( 'My account page URL', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return wc_get_page_permalink( 'myaccount' );
		} );
		$add( 'admin_email', $g_store, __( 'Store admin email', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return get_option( 'admin_email' );
		} );
		$add( 'store_address', $g_store, __( 'Store address (from WooCommerce settings)', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			$countries = WC()->countries;
			$country   = $countries->get_base_country();
			$parts     = array_filter(
				array(
					$countries->get_base_address(),
					$countries->get_base_address_2(),
					$countries->get_base_city(),
					$countries->get_base_postcode(),
					isset( $countries->countries[ $country ] ) ? $countries->countries[ $country ] : $country,
				)
			);
			return implode( ', ', $parts );
		} );
		$add( 'current_year', $g_store, __( 'Current year', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return wp_date( 'Y' );
		} );
		$add( 'current_date', $g_store, __( 'Current date', 'minimal-email-customizer-for-woocommerce' ), false, function () {
			return wp_date( get_option( 'date_format' ) );
		} );

		// Order.
		$add( 'order_number', $g_order, __( 'Order number', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_order_number();
		} ) );
		$add( 'order_id', $g_order, __( 'Order ID', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_id();
		} ) );
		$add( 'order_date', $g_order, __( 'Order date', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return wc_format_datetime( $order->get_date_created() );
		} ) );
		$add( 'order_time', $g_order, __( 'Order time', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return wc_format_datetime( $order->get_date_created(), get_option( 'time_format' ) );
		} ) );
		$add( 'order_status', $g_order, __( 'Order status', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return wc_get_order_status_name( $order->get_status() );
		} ) );
		$add( 'order_total', $g_order, __( 'Order total (formatted)', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) {
			return $order->get_formatted_order_total();
		} ) );
		$add( 'order_subtotal', $g_order, __( 'Order subtotal', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) use ( $price ) {
			return $price( $order, $order->get_subtotal() );
		} ) );
		$add( 'order_tax', $g_order, __( 'Total tax', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) use ( $price ) {
			return $price( $order, $order->get_total_tax() );
		} ) );
		$add( 'order_shipping_total', $g_order, __( 'Shipping cost', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) use ( $price ) {
			return $price( $order, $order->get_shipping_total() );
		} ) );
		$add( 'order_discount', $g_order, __( 'Discount total', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) use ( $price ) {
			return $price( $order, $order->get_total_discount() );
		} ) );
		$add( 'order_currency', $g_order, __( 'Currency code', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_currency();
		} ) );
		$add( 'payment_method', $g_order, __( 'Payment method', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_payment_method_title();
		} ) );
		$add( 'shipping_method', $g_order, __( 'Shipping method', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_method();
		} ) );
		$add( 'customer_note', $g_order, __( 'Note left by the customer', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_customer_note();
		} ) );
		$add( 'coupon_codes', $g_order, __( 'Coupons used', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return implode( ', ', $order->get_coupon_codes() );
		} ) );
		$add( 'order_items_count', $g_order, __( 'Number of items', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_item_count();
		} ) );
		$add( 'order_items', $g_order, __( 'Simple item list (qty × name)', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) {
			$lines = array();
			foreach ( $order->get_items() as $item ) {
				$lines[] = esc_html( $item->get_quantity() . ' × ' . $item->get_name() );
			}
			return implode( '<br>', $lines );
		} ) );
		$add( 'order_view_url', $g_order, __( 'Customer link to view the order', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_view_order_url();
		} ) );
		$add( 'order_pay_url', $g_order, __( 'Payment link for the order', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_checkout_payment_url();
		} ) );
		$add( 'order_admin_url', $g_order, __( 'Admin link to edit the order', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_edit_order_url();
		} ) );
		$add( 'order_meta', $g_order, __( 'Any order custom field, e.g. [order_meta key="_my_field"]', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order, $ctx, $atts ) {
			$key = isset( $atts['key'] ) ? (string) $atts['key'] : '';
			if ( '' === $key ) {
				return '';
			}
			$value = $order->get_meta( $key );
			return is_scalar( $value ) ? (string) $value : '';
		} ), '[order_meta key=""]' );

		// Customer.
		$add( 'customer_name', $g_customer, __( 'Customer first name', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) {
			if ( $ctx['order'] && $ctx['order']->get_billing_first_name() ) {
				return $ctx['order']->get_billing_first_name();
			}
			return $ctx['user'] ? ( $ctx['user']->first_name ? $ctx['user']->first_name : $ctx['user']->display_name ) : '';
		} );
		$add( 'customer_full_name', $g_customer, __( 'Customer full name', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) {
			if ( $ctx['order'] && trim( $ctx['order']->get_formatted_billing_full_name() ) ) {
				return $ctx['order']->get_formatted_billing_full_name();
			}
			return $ctx['user'] ? $ctx['user']->display_name : '';
		} );
		$add( 'customer_email', $g_customer, __( 'Customer email', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) {
			if ( $ctx['order'] && $ctx['order']->get_billing_email() ) {
				return $ctx['order']->get_billing_email();
			}
			return $ctx['user'] ? $ctx['user']->user_email : '';
		} );

		// Billing.
		$add( 'billing_first_name', $g_billing, __( 'Billing first name', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_first_name();
		} ) );
		$add( 'billing_last_name', $g_billing, __( 'Billing last name', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_last_name();
		} ) );
		$add( 'billing_company', $g_billing, __( 'Billing company', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_company();
		} ) );
		$add( 'billing_email', $g_billing, __( 'Billing email', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_email();
		} ) );
		$add( 'billing_phone', $g_billing, __( 'Billing phone', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_phone();
		} ) );
		$add( 'billing_city', $g_billing, __( 'Billing city', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_city();
		} ) );
		$add( 'billing_country', $g_billing, __( 'Billing country code', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_billing_country();
		} ) );
		$add( 'billing_address', $g_billing, __( 'Full billing address', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) {
			return $order->get_formatted_billing_address( '' );
		} ) );

		// Shipping.
		$add( 'shipping_first_name', $g_shipping, __( 'Shipping first name', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_first_name();
		} ) );
		$add( 'shipping_last_name', $g_shipping, __( 'Shipping last name', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_last_name();
		} ) );
		$add( 'shipping_company', $g_shipping, __( 'Shipping company', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_company();
		} ) );
		$add( 'shipping_city', $g_shipping, __( 'Shipping city', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_city();
		} ) );
		$add( 'shipping_country', $g_shipping, __( 'Shipping country code', 'minimal-email-customizer-for-woocommerce' ), false, $o( function ( $order ) {
			return $order->get_shipping_country();
		} ) );
		$add( 'shipping_address', $g_shipping, __( 'Full shipping address', 'minimal-email-customizer-for-woocommerce' ), true, $o( function ( $order ) {
			return $order->get_formatted_shipping_address( '' );
		} ) );

		// Blocks rendered with WooCommerce's own email templates.
		$block = function ( $template ) {
			return function ( $ctx ) use ( $template ) {
				if ( ! $ctx['order'] instanceof WC_Order ) {
					return '';
				}
				$email = $ctx['email'];
				return wc_get_template_html(
					$template,
					array(
						'order'         => $ctx['order'],
						'sent_to_admin' => $email ? ! $email->is_customer_email() : false,
						'plain_text'    => false,
						'email'         => $email,
					)
				);
			};
		};
		$add( 'order_details', $g_blocks, __( 'Full order table (products, totals)', 'minimal-email-customizer-for-woocommerce' ), true, $block( 'emails/email-order-details.php' ) );
		$add( 'customer_details', $g_blocks, __( 'Customer details block (email, phone)', 'minimal-email-customizer-for-woocommerce' ), true, $block( 'emails/email-customer-details.php' ) );
		$add( 'addresses', $g_blocks, __( 'Billing and shipping address blocks', 'minimal-email-customizer-for-woocommerce' ), true, $block( 'emails/email-addresses.php' ) );

		// Account emails.
		$add( 'user_login', $g_account, __( 'Username', 'minimal-email-customizer-for-woocommerce' ), false, $u( function ( $user ) {
			return $user->user_login;
		} ) );
		$add( 'user_email', $g_account, __( 'Account email', 'minimal-email-customizer-for-woocommerce' ), false, $u( function ( $user ) {
			return $user->user_email;
		} ) );
		$add( 'user_display_name', $g_account, __( 'Display name', 'minimal-email-customizer-for-woocommerce' ), false, $u( function ( $user ) {
			return $user->display_name;
		} ) );
		$add( 'user_password', $g_account, __( 'Generated password (new account email)', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) use ( $e ) {
			$email = $ctx['email'];
			return ( $email && ! empty( $email->password_generated ) ) ? $e( $email, 'user_pass' ) : '';
		} );
		$add( 'set_password_url', $g_account, __( 'Link to set a password (new account email)', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) use ( $e ) {
			return $e( $ctx['email'], 'set_password_url' );
		} );
		$add( 'reset_password_url', $g_account, __( 'Reset password link (reset email)', 'minimal-email-customizer-for-woocommerce' ), false, function ( $ctx ) use ( $e ) {
			$email = $ctx['email'];
			if ( ! $email || empty( $email->reset_key ) ) {
				return '';
			}
			return add_query_arg(
				array(
					'key'   => $email->reset_key,
					'id'    => isset( $email->user_id ) ? $email->user_id : 0,
					'login' => rawurlencode( $e( $email, 'user_login' ) ),
				),
				wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
			);
		} );

		// Design.
		$add( 'button', __( 'Design', 'minimal-email-customizer-for-woocommerce' ), __( 'Styled button using your Design settings, e.g. [button url="[order_view_url]" text="View order"]', 'minimal-email-customizer-for-woocommerce' ), true, function ( $ctx, $atts ) {
			$g    = MECW_Settings::general();
			$url  = self::render( isset( $atts['url'] ) ? $atts['url'] : '', $ctx, false );
			$text = self::render( isset( $atts['text'] ) ? $atts['text'] : __( 'Click here', 'minimal-email-customizer-for-woocommerce' ), $ctx, false );
			if ( '' === $url ) {
				return '';
			}
			$stacks = MECW_Mailer::font_stacks( $g );
			return sprintf(
				'<a href="%s" class="wec-button" style="display:inline-block;background-color:%s !important;color:%s !important;padding:12px 26px;border-radius:%dpx;font-family:%s;font-size:15px;font-weight:600;line-height:1.3;text-decoration:none;">%s</a>',
				esc_url( $url ),
				esc_attr( $g['button_bg'] ),
				esc_attr( $g['button_color'] ),
				(int) $g['button_radius'],
				esc_attr( $stacks[0] ),
				esc_html( $text )
			);
		}, '[button url="" text="Click here"]' );

		self::$registry = apply_filters( 'mecw_shortcodes', $r );
		return self::$registry;
	}

	/**
	 * Build the data context for an email.
	 *
	 * @param WC_Email|null $email  Email object.
	 * @param mixed         $object Override for $email->object.
	 */
	public static function context( $email = null, $object = null ) {
		if ( null === $object && $email && isset( $email->object ) ) {
			$object = $email->object;
		}

		$order = null;
		$user  = null;

		if ( $object instanceof WC_Order ) {
			$order = $object;
		} elseif ( $object instanceof WC_Order_Refund ) {
			$order = wc_get_order( $object->get_parent_id() );
		} elseif ( $object instanceof WP_User ) {
			$user = $object;
		}

		if ( ! $user && $email && ! empty( $email->user_login ) ) {
			$user = get_user_by( 'login', $email->user_login );
		}
		if ( ! $user && $order && $order->get_customer_id() ) {
			$user = get_userdata( $order->get_customer_id() );
		}

		return array(
			'order' => $order ? $order : null,
			'user'  => $user ? $user : null,
			'email' => $email,
		);
	}

	/**
	 * Replace all known shortcodes in $text.
	 *
	 * @param string $text Source text.
	 * @param array  $ctx  Context from ::context().
	 * @param bool   $html True for HTML output, false for plain text (subjects, headings).
	 */
	public static function render( $text, $ctx, $html = true ) {
		if ( false === strpos( $text, '[' ) ) {
			return $text;
		}

		$registry = self::registry();
		$pattern  = '/\[([a-z0-9_]+)((?:\s+[a-z0-9_\-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'))*)\s*\]/i';

		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( $registry, $ctx, $html ) {
				$name = strtolower( $m[1] );
				if ( ! isset( $registry[ $name ] ) ) {
					return $m[0];
				}
				$atts  = $m[2] ? shortcode_parse_atts( trim( $m[2] ) ) : array();
				$atts  = is_array( $atts ) ? $atts : array();
				$entry = $registry[ $name ];

				try {
					$value = (string) call_user_func( $entry['cb'], $ctx, $atts );
				} catch ( \Throwable $ex ) {
					return '';
				}

				if ( $entry['html'] ) {
					if ( $html ) {
						return $value;
					}
					$value = preg_replace( '#<br\s*/?>#i', ', ', $value );
					return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
				}

				return $html ? esc_html( $value ) : $value;
			},
			$text
		);
	}
}

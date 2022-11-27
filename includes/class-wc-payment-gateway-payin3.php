<?php

/**
 * BharatX pay-in-3 integration
 *
 * Provides a BharatX pay-in-3 Payments Payment Gateway.
 *
 * @class       WC_Gateway_Payin3
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */
class WC_Gateway_Payin3 extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->api_key            = $this->get_option( 'api_key' );
		$this->partner_id          = $this->get_option( 'partner_id' );
		$this->instructions       = $this->get_option( 'instructions' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'change_payment_complete_order_status' ), 10, 3 );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'payin3';
		$this->icon               = apply_filters( 'woocommerce_payin3_icon', plugins_url('../assets/icon.png', __FILE__ ) );
		$this->method_title       = __( 'Payin3 BharatX Payments', 'payin3-payments-woo' );
		$this->api_key            = __( 'Add API Key', 'payin3-payments-woo' );
		$this->partner_id	      = __('Add Partner ID', 'payin3-payments-woo');
		$this->method_description = __( 'Have your customers pay in installments with Payin3', 'payin3-payments-woo' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __( 'Enable/Disable', 'payin3-payments-woo' ),
				'label'       => __( 'Enable BharatX payin3', 'payin3-payments-woo' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'              => array(
				'title'       => __( 'Title', 'payin3-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Pay in 3 easy installments using Payin3', 'payin3-payments-woo' ),
				'default'     => __( 'BharatX Pay-in-3', 'payin3-payments-woo' ),
				'desc_tip'    => true,
			),
			'api_key'             => array(
				'title'       => __( 'API Key', 'payin3-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Add your API key', 'payin3-payments-woo' ),
				'desc_tip'    => true,
			),
			'partner_id'             => array(
				'title'       => __( 'Partner ID', 'payin3-payments-woo' ),
				'type'        => 'text',
				'description' => __( 'Add your Partner ID', 'payin3-payments-woo' ),
				'desc_tip'    => true,
			),
			'description'        => array(
				'title'       => __( 'Description', 'payin3-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Payin3 Mobile Payment method description that the customer will see on your website.', 'payin3-payments-woo' ),
				'default'     => __( 'Payin3 Payments before delivery.', 'payin3-payments-woo' ),
				'desc_tip'    => true,
			),
			'instructions'       => array(
				'title'       => __( 'Instructions', 'payin3-payments-woo' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page.', 'payin3-payments-woo' ),
				'default'     => __( 'Payin3 Payments before delivery.', 'payin3-payments-woo' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && get_query_var( 'order-pay' ) > 0 ) {
			
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings() {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'payin3' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'payin3-payments-woo' ), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( __( '%1$s (#%2$s)', 'payin3-payments-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( __( '%1$s &ndash; %2$s', 'payin3-payments-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'payin3-payments-woo' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->get_total() > 0 ) {
			if($this->payin3_payment_processing( $order )){
				// Return thankyou redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}
		}
	}

	private function payin3_payment_processing( $order ) {

		$total = intval( $order->get_total() );
		//Getting user Inputs
		$phone = esc_attr( $_POST['payment_number'] );
		$email = esc_attr($_POST['user_email']);
		$name = esc_attr($_POST['user_name']);
		$endpoint = 'https://web.bharatx.tech/api/transaction';
		$uri = '/api/transaction';
		$body = [
			'id' => $order->order_key, 
			'amount' => $total*100, //converting amt to paise
			'user' => array('name' => $name,'phoneNumber' => '+91'.$phone,'email' => $email)
		];

		//Generating SHA256 Hash Base 64 Encoded for API Header
		$body = wp_json_encode( $body );
		$Signature = $body.$uri.$this->api_key;
		$XSignature = base64_encode(hash("sha256", $Signature, True)); 

		$options = [
			'body'        => $body,
			'headers'     => [
				'Content-Type' => 'application/json',
				'X-Signature' => $XSignature,
				'X-Partner-Id'    => $this->partner_id
			],
			'timeout'     => 60,
			
			'data_format' => 'body'
		];
		$response = wp_remote_post( $endpoint, $options );


		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return "Something went wrong: $error_message";
		}

		//Processing order on Successful Transaction 
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body );
			if ($response_data->message === 'Successfully created the transaction' ) {
				
				$order->payment_complete();
				// Remove cart.
				WC()->cart->empty_cart();

				return True;
				
			}
		}
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page() {
		if ( $this->instructions ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
		}
	}

	/**
	 * Change payment complete order status to completed for payin3 orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
		if ( $order && 'payin3' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}
}
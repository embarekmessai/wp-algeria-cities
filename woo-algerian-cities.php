<?php
/**
 * Plugin Name: Algerian Cities and States for Woocommerce
 * Plugin URI: https://github.com/embarekmessai/wp-algeria-cities
 * Description: Woocommerce plugin for listing states and cities in Algeria.
 * Version: 1.0.0
 * Author: Embarek MESSAI
 * Author URI: http://ultimatemarkettechnology.dz/
 * Developer: Embarek MESSAI
 * Developer URI: http://ultimatemarkettechnology.dz/
 * Text Domain: woocommerce-extension
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

 /**
 * Die if accessed directly
 */
defined( 'ABSPATH' ) or die( 'You can not access this file directly!' );

/**
 * Check if WooCommerce is active
 */
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	class WC_States_Cities {

		const VERSION = '1.0.0';
		private $states;
		private $cities;

		/**
		 * Construct class
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init') );
		}

		/**
		 * WC init
		 */
		public function init() {
			$this->init_states();
			$this->init_cities();
		}

		/**
		 * WC States init
		 */
		public function init_states() {
			add_filter('woocommerce_states', array($this, 'wc_states'));
		}

		/**
		 * WC States init
		 */
		public function init_cities() {
			add_filter( 'woocommerce_billing_fields', array( $this, 'wc_billing_fields' ), 10, 2 );
			add_filter( 'woocommerce_shipping_fields', array( $this, 'wc_shipping_fields' ), 10, 2 );
			add_filter( 'woocommerce_form_field_city', array( $this, 'wc_form_field_city' ), 10, 4 );

			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		}

		/**
		 * Implement WC States
		 * @param mixed $states
		 * @return mixed
		 */
		public function  wc_states($states) {
			//get countries allowed by store owner
			$allowed = $this->get_store_allowed_countries();

			if (!empty( $allowed ) ) {
				foreach ($allowed as $code => $country) {
					if (! isset( $states[$code] ) && file_exists($this->get_plugin_path() . '/states/' . $code . '.php')) {
						include($this->get_plugin_path() . '/states/' . $code . '.php');
					}
				}
			}

			return $states;
		}

		/**
		 * Modify billing field
		 * @param mixed $fields
		 * @param mixed $country
		 * @return mixed
		 */
		public function wc_billing_fields( $fields, $country ) {
			$fields['billing_city']['type'] = 'city';

			return $fields;
		}

		/**
		 * Modify shipping field
		 * @param mixed $fields
		 * @param mixed $country
		 * @return mixed
		 */
		public function wc_shipping_fields( $fields, $country ) {
			$fields['shipping_city']['type'] = 'city';

			return $fields;
		}

		/**
		 * Implement cities/city field
		 * @param mixed $field
		 * @param string $key
		 * @param mixed $args
		 * @param string $value
		 * @return mixed
		 */
		public function wc_form_field_city($field, $key, $args, $value ) {
			// Do we need a clear div?
			if ( ( ! empty( $args['clear'] ) ) ) {
				$after = '<div class="clear"></div>';
			} else {
				$after = '';
			}

			// Required markup
			if ( $args['required'] ) {
				$args['class'][] = 'validate-required';
				$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
			} else {
				$required = '';
			}

			// Custom attribute handling
			$custom_attributes = array();

			if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
				foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			// Validate classes
			if ( ! empty( $args['validate'] ) ) {
				foreach( $args['validate'] as $validate ) {
					$args['class'][] = 'validate-' . $validate;
				}
			}

			// field p and label
			$field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';
			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';
			}

			// Get Country
			$country_key = $key == 'billing_city' ? 'billing_country' : 'shipping_country';
			$current_cc  = WC()->checkout->get_value( $country_key );

			$state_key = $key == 'billing_city' ? 'billing_state' : 'shipping_state';
			$current_sc  = WC()->checkout->get_value( $state_key );

			// Get country cities
			$cities = $this->get_cities( $current_cc );

			if ( is_array( $cities ) ) {

				$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="city_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '">';

				$field .= '<option value="">'. __( 'Select an option&hellip;', 'woocommerce' ) .'</option>';

				if ( $current_sc && array_key_exists( $current_sc, $cities ) ) {
					$dropdown_cities = $cities[ $current_sc ];
				} else if ( is_array($cities) &&  isset($cities[0])) {
					$dropdown_cities = array_reduce( $cities, 'array_merge', array() );
					sort( $dropdown_cities );
				} else {
					$dropdown_cities = $cities;
				}

				foreach ( $dropdown_cities as $city_name ) {
					if(!is_array($city_name)) {
						$field .= '<option value="' . esc_attr( $city_name ) . '" '.selected( $value, $city_name, false ) . '>' . $city_name .'</option>';
					}
				}

				$field .= '</select>';

			} else {

				$field .= '<input type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
			}

			// field description and close wrapper
			if ( $args['description'] ) {
				$field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';
			}

			$field .= '</p>' . $after;

			return $field;
		}
		/**
		 * Get cities
		 * @param string $p_code(default:)
		 * @return mixed
		 */
		public function get_cities( $p_code = null ) {
			if ( empty( $this->cities ) ) {
				$this->load_country_cities();
			}

			if ( ! is_null( $p_code ) ) {
				return isset( $this->cities[ $p_code ] ) ? $this->cities[ $p_code ] : false;
			} else {
				return $this->cities;
			}
		}
		/**
		 * Get country cities
		 * @return mixed
		 */
		public function load_country_cities() {
			global $cities;

			$allowed =  $this->get_store_allowed_countries();

			if ( $allowed ) {
				foreach ( $allowed as $code => $country ) {
					if ( ! isset( $cities[ $code ] ) && file_exists( $this->get_plugin_path() . '/cities/' . $code . '.php' ) ) {
						include( $this->get_plugin_path() . '/cities/' . $code . '.php' );
					}
				}
			}

			$this->cities = $cities;
		}

		/**
		 * Load scripts
		 */
		public function load_scripts() {
			if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

				$city_select_path = $this->get_plugin_url() . 'jquery-select/cities-select.js';
				wp_enqueue_script( 'wc-city-select', $city_select_path, array( 'jquery', 'woocommerce' ), self::VERSION, true );

				$cities = json_encode( $this->get_cities() );
				wp_localize_script( 'wc-city-select', 'wc_city_select_params', array(
					'cities' => $cities,
					'i18n_select_city_text' => esc_attr__( 'Select an option&hellip;', 'woocommerce' )
				) );
			}
		}

		/**
		 * Get plugin root path
		 * @return mixed
		 */
		private function get_plugin_path() {
			if (isset($this->plugin_path)) {
				return $this->plugin_path;
			}
			$path = $this->plugin_path = plugin_dir_path( __FILE__ );

			return untrailingslashit($path);
		}

		/**
		 * Get Store allowed countries
		 * @return mixed
		 */
		private function get_store_allowed_countries() {
			return array_merge( WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries() );
		}

		/**
		 * Get plugin url
		 * @return mixed
		 */
		public function get_plugin_url() {

			if (isset($this->plugin_url)) {
				return $this->plugin_url;
			}

			return $this->plugin_url = plugin_dir_url( __FILE__ );
		}
	}
	/**
	 * Instantiate class
	 */
	$GLOBALS['WC_States_Cities'] = new WC_States_Cities();
};

?>
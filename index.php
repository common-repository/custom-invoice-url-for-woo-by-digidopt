<?php
/*
 * Plugin Name: Custom Invoice URL for WooCommerce by Digidopt
 * Description: Add any URL for an invoice to a WooCommerce order a free plugin by Digidopt
 * Version: 1.0.1
 * Author: Digidopt
 * Author URI: https://digidopt.net
 * License: GPLv2 or later
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	// Add custom action to WooCommerce order actions
	add_filter( 'woocommerce_order_actions', 'invoice_link_woocommerce_add_order_action' );
	function invoice_link_woocommerce_add_order_action( $actions ) {
		$actions['invoice'] = __( 'Invoice', 'invoice-link-woocommerce' );
		return $actions;
	}

	// Add custom action to process the "Invoice" action
	add_action( 'woocommerce_order_action_invoice', 'invoice_link_woocommerce_process_order_action' );
	function invoice_link_woocommerce_process_order_action( $order ) {
		// Generate invoice and store it somewhere (e.g. in a custom field or as a custom post type)
		// ...
		// Save invoice URL in order meta
		update_post_meta( $order->get_id(), '_invoice_url', $invoice_url );
	}

	// Add custom column to WooCommerce order list
	add_filter( 'manage_edit-shop_order_columns', 'invoice_link_woocommerce_add_order_column' );
	function invoice_link_woocommerce_add_order_column( $columns ) {
		$columns['invoice'] = __( 'Invoice', 'invoice-link-woocommerce' );
		return $columns;
	}

	// Add custom column content to WooCommerce order list
	add_action( 'manage_shop_order_posts_custom_column', 'invoice_link_woocommerce_add_order_column_content' );
	function invoice_link_woocommerce_add_order_column_content( $column ) {
		global $post;
		if ( $column == 'invoice' ) {
			$invoice_url = get_post_meta( $post->ID, '_invoice_url', true );
			if ( $invoice_url ) {
				echo '<a href="' . esc_url( $invoice_url ) . '" target="_blank">' . get_option( 'invoice_link_woocommerce_view_invoice_text', __( 'View Invoice', 'invoice-link-woocommerce' ) ) . '</a>';
			} else {
				echo '-';
			}
		}
	}

	// Add custom metabox to WooCommerce order page
	add_action( 'add_meta_boxes', 'invoice_link_woocommerce_add_metabox' );
	function invoice_link_woocommerce_add_metabox() {
		add_meta_box( 'invoice_link_woocommerce', __( 'Invoice', 'invoice-link-woocommerce' ), 'invoice_link_woocommerce_metabox_callback', 'shop_order', 'side', 'default' );
	}

	// Add custom metabox callback
	function invoice_link_woocommerce_metabox_callback( $post ) {
		wp_nonce_field( 'invoice_link_woocommerce_metabox', 'invoice_link_woocommerce_metabox_nonce' );
		$invoice_url = get_post_meta( $post->ID, '_invoice_url', true );
		echo '<p><label for="invoice_link_woocommerce_invoice_url">';
		_e( 'Invoice URL:', 'invoice-link-woocommerce' );
		echo '</label> ';
		echo '<input type="text" id="invoice_link_woocommerce_invoice_url" name="invoice_link_woocommerce_invoice_url" value="' . esc_attr( $invoice_url ) . '" style="width:100%;" /></p>';
		echo '<p><a href="' . esc_url( $invoice_url ) . '" target="_blank">' . get_option( 'invoice_link_woocommerce_view_invoice_text', __( 'View Invoice', 'invoice-link-woocommerce' ) ) . '</a></p>';
	}
    
	// Save custom metabox data
	add_action( 'save_post', 'invoice_link_woocommerce_save_metabox' );
	function invoice_link_woocommerce_save_metabox( $post_id ) {
		if ( ! isset( $_POST['invoice_link_woocommerce_metabox_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['invoice_link_woocommerce_metabox_nonce'], 'invoice_link_woocommerce_metabox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			
		return;
		}
		if ( ! isset( $_POST['invoice_link_woocommerce_invoice_url'] ) ) {
			return;
		}
		$invoice_url = sanitize_text_field( $_POST['invoice_link_woocommerce_invoice_url'] );
		update_post_meta( $post_id, '_invoice_url', $invoice_url );
	}

	// Add custom endpoint to WooCommerce My Account page
	add_action( 'init', 'invoice_link_woocommerce_add_endpoint' );
	function invoice_link_woocommerce_add_endpoint() {
		add_rewrite_endpoint( 'view-invoice', EP_ROOT | EP_PAGES );
	}

	// Add custom query var to process the custom endpoint
	add_filter( 'query_vars', 'invoice_link_woocommerce_add_query_var' );
	function invoice_link_woocommerce_add_query_var( $vars ) {
		$vars[] = 'view-invoice';
		return $vars;
	}

	// Add custom content to the custom endpoint
	add_action( 'woocommerce_account_view-invoice_endpoint', 'invoice_link_woocommerce_endpoint_content' );
	function invoice_link_woocommerce_endpoint_content() {
		$order_id = absint( get_query_var( 'view-invoice' ) );
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$invoice_url = get_post_meta( $order_id, '_invoice_url', true );
			if ( $invoice_url ) {
				echo '<p>' . __( 'Here is your invoice for order #', 'invoice-link-woocommerce' ) . $order_id . ':</p>';
				echo '<iframe src="' . esc_url( $invoice_url ) . '" style="width:100%; height:800px;"></iframe>';
			} else {
				echo '<p>' . __( 'Invoice not available for this order.', 'invoice-link-woocommerce' ) . '</p>';
			}
		}
    }


	// Add custom content to WooCommerce order details page
	add_action( 'woocommerce_order_details_after_order_table', 'invoice_link_woocommerce_add_order_details_content' );
	function invoice_link_woocommerce_add_order_details_content( $order ) {
		$invoice_url = get_post_meta( $order->get_id(), '_invoice_url', true );
		if ( $invoice_url ) {
			echo '<p>Invoice: <a href="' . esc_url( $invoice_url ) . '" target="_blank">' . get_option( 'invoice_link_woocommerce_view_invoice_text', __( 'View Invoice', 'invoice-link-woocommerce' ) ) . '</a></p>';
		}
	}

function wc_invoice_url_customer_view( $actions, $order ) {
  $invoice_url = get_post_meta( $order->get_id(), '_invoice_url', true );
  if ( !empty( $invoice_url ) ) {
    $actions['invoice'] = array(
      'url'  => $invoice_url,
      'name' => get_option( 'invoice_link_woocommerce_view_invoice_text', __( 'View Invoice', 'invoice-link-woocommerce' ) ),
    );
  }
  return $actions;
}
	add_filter( 'woocommerce_my_account_my_orders_actions', 'wc_invoice_url_customer_view', 10, 2 );
	
// Register plugin options
function invoice_link_woocommerce_register_options() {
	register_setting( 'invoice_link_woocommerce_options', 'invoice_link_woocommerce_view_invoice_text', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'invoice_link_woocommerce_options', 'invoice_link_woocommerce_custom_css', array( 'type' => 'string', 'sanitize_callback' => 'wp_strip_all_tags' ) );
}
add_action( 'admin_init', 'invoice_link_woocommerce_register_options' );

// Add custom settings to WooCommerce settings page
add_filter( 'woocommerce_get_settings_pages', 'invoice_link_woocommerce_add_settings' );
function invoice_link_woocommerce_add_settings( $settings ) {
		$settings[] = include 'class-invoice-link-woocommerce-settings.php';
	return $settings;
}

// Custom settings class
class Invoice_Link_WooCommerce_Settings {

	public static function get_section_id() {
		return 'invoice_link_woocommerce';
	}

	public static function get_section_label() {
		return __( 'Custom Invoice URL', 'invoice-link-woocommerce' );
	}

	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
		add_action( 'woocommerce_settings_' . self::get_section_id(), array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . self::get_section_id(), array( $this, 'save' ) );
	}

	public function add_settings_page( $settings_tabs ) {
		$settings_tabs[self::get_section_id()] = self::get_section_label();
		return $settings_tabs;
	}

	public function output() {
		woocommerce_admin_fields( $this->get_settings() );
	}

	public function save() {
		woocommerce_update_options( $this->get_settings() );
	}

	public function get_settings() {
		$settings = array(
			array(
				'name' => __( 'Invoice Link Options', 'invoice-link-woocommerce' ),
				'type' => 'title',
				'id'   => 'invoice_link_woocommerce_options',
			),
			array(
				'name' => __( 'View Invoice Text', 'invoice-link-woocommerce' ),
				'desc_tip' => __( 'Enter the text to display for the "View Invoice" link.', 'invoice-link-woocommerce' ),
				'id'   => 'invoice_link_woocommerce_view_invoice_text',
				'type' => 'text',
				'default' => __( 'View Invoice', 'invoice-link-woocommerce' ),
			),
		
			array(
				'type' => 'sectionend',
				'id' => 'invoice_link_woocommerce_options',
			),
		);
		return $settings;
	}

}

new Invoice_Link_WooCommerce_Settings();

function invoice_link_woocommerce_add_plugin_action_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=invoice_link_woocommerce' ) . '">' . __( 'Settings', 'invoice-link-woocommerce' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'invoice_link_woocommerce_add_plugin_action_link' );
}


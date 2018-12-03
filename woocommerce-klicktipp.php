<?php
/*
Plugin Name:  WooCommerce zu Klick Tipp
Plugin URI:
Description:  Ermöglicht es Kunden anhand ihrer Käufe in Klick Tipp zu taggen
Version:      1.1.0
Author:       Sebastian Gärtner
Author URI:   https://profiles.wordpress.org/ib4s
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/



/**
 * Klick Tipp API
 * @since 1.0.0
 */
require_once dirname( __FILE__ ) . '/klicktipp.api.inc';



/**
 * Display the product tag field
 * @since 1.0.0
 */
add_action( 'woocommerce_product_options_general_product_data', 'wookt_add_product_tag_field' );
function wookt_add_product_tag_field() {
	$args = array(
		'id'          => 'wookt_product_tag',
		'label'       => __( 'Klick-Tipp-Tag-ID', 'wookt' ),
		'class'       => 'wookt-product-tag-field',
		'placeholder' => '123456 - ID ist eine Zahl',
		'desc_tip'    => true,
		'description' => __( 'Gib hier die ID des Klick-Tipp-Tag ein, der durch den Kauf dieses Produktes gesetzt werden soll.', 'ctwc' ),
	);
	woocommerce_wp_text_input( $args );
}


/**
 * Save the product tag field
 * @since 1.0.0
 *
 */
add_action( 'woocommerce_process_product_meta', 'wookt_save_product_tag_field' );
function wookt_save_product_tag_field( $post_id ) {
	$product     = wc_get_product( $post_id );
	$product_tag = isset( $_POST['wookt_product_tag'] ) ? $_POST['wookt_product_tag'] : ''; // WP WPCS: CSRF ok.
	$product->update_meta_data( 'wookt_product_tag', sanitize_text_field( $product_tag ) );
	$product->save();
}


add_action( 'woocommerce_checkout_order_processed', 'wookt_send_customer_data_to_klicktipp', 10, 3 );
function wookt_send_customer_data_to_klicktipp( $order_id, $posted_data, $order ) {

	$logger  = wc_get_logger();
	$context = array( 'source' => 'woocommerce-klicktipp' );

	$tags = [];

	$items = $order->get_items();

	foreach ( $items as $item ) {
		$product_id = $item->get_product_id();
		$tag        = get_post_meta( $product_id, 'wookt_product_tag', true );
		if ( $tag ) {
			$tags[] = $tag;
		}
	}

	if ( empty( $tags ) ) {
		return true;
	}

	$email = $order->get_billing_email();

	if ( empty( $email ) ) {
		return true;
	}

	$customer = [];

	$customer['fieldFirstName']   = $order->get_billing_first_name();
	$customer['fieldLastName']    = $order->get_billing_last_name();
	$customer['fieldCompanyName'] = $order->get_billing_company();
	$customer['fieldStreet1']     = $order->get_billing_address_1();
	$customer['fieldStreet2']     = $order->get_billing_address_2();
	$customer['fieldCity']        = $order->get_billing_city();
	$customer['fieldZip']         = $order->get_billing_postcode();
	$customer['fieldCountry']     = $order->get_billing_country();
	$customer['fieldPhone']       = $order->get_billing_phone();
	$customer['field77074']       = $order->get_payment_method_title();

	$connector = new KlicktippConnector();
	$logged_in = $connector->login( KLICKTIPP_USER, KLICKTIPP_PASS );

	if ( ! $logged_in ) {
		$logger->emergency( 'Verbindung zu Klick Tipp konnte nicht aufgebaut werden. Vermutlich sind Username oder Passwort nicht mehr aktuell. - Bestellungs-ID: ' . $order_id, $context );
		return true;
	}

	// get subscriber ID
	$subscriber_id = $connector->subscriber_search( $email );

	// user already exists
	if ( $subscriber_id ) {

		$fields = $customer;

		$updated = $connector->subscriber_update( $subscriber_id, $fields );

		if ( $updated ) {
			$logger->info( 'Benutzer von Bestellungs-ID ' . $order_id . ' wurde erfolgreich aktuallisiert mit Daten aus Bestellung', $context );
		} else {
			$logger->error( 'Benutzer konnte nicht geupdated werden. Bestellungs-ID: ' . $order_id . ' Klick Tipp Error: ' . $connector->get_last_error(), $context );
		}
	} else {

		$double_optin_process_id = KLICKTIPP_DOUBLE_OPTIN;
		$tag_id                  = '';
		$fields                  = $customer;

		$subscriber = $connector->subscribe( $email, $double_optin_process_id, $tag_id, $fields, $smsnumber );

		if ( $subscriber ) {
			$logger->info( 'Benutzer von Bestellungs-ID ' . $order_id . ' wurde erfolgreich in Klick Tipp angelegt.', $context );
		} else {
			$logger->error( 'Benutzer konnte nicht angelegt werden. Bestellungs-ID: ' . $order_id . ' Klick Tipp Error: ' . $connector->get_last_error(), $context );
		}
	}

	foreach ( $tags as $tag_id ) {

		$result = $connector->tag( $email, $tag_id );

		if ( $result ) {
			$logger->info( 'Benutzer von Bestellungs-ID ' . $order_id . ' wurde erfolgreich mit Tag-ID ' . $tag_id . ' getaggt.', $context );
		} else {
			$logger->error( 'Benutzer konnte nicht getaggt werden. Bestellungs-ID: ' . $order_id . ' Klick Tipp Error: ' . $connector->get_last_error(), $context );
		}
	}

	$connector->logout();
}



/**
 * Make sure Klick Tipp date are set in wp-config.php
 * if not - show admin message
 * @since 1.0.0
 */
if ( is_admin() && ( ! defined( 'KLICKTIPP_USER' ) || ! defined( 'KLICKTIPP_PASS' ) || ! defined( 'KLICKTIPP_DOUBLE_OPTIN' ) ) ) {
	add_action( 'admin_notices', 'wookt_error_notice_klicktipp' );
}
function wookt_error_notice_klicktipp() {
	?>
	<div class="error notice">
		<p>
		<?php
			_e(
				"Um das <strong>Plugin \"WooCommerce zu Klick Tipp\"</strong> verwenden zu können müssen in der wp-config.php die Werte \"KLICKTIPP_USER\",  \"KLICKTIPP_PASS\" und \"KLICKTIPP_DOUBLE_OPTIN\" gesetzt werden. <br>
				Füge dazu folgenden Code in deine wp-config.php ein - wobei Username und Passwort eingesetzt werden müssen.<br>
				<code>define( 'KLICKTIPP_USER', 'hier Username einfügen' );</code><br>
				<code>define( 'KLICKTIPP_PASS', 'hier Passwort einfügen' );</code><br>
				<code>define( 'KLICKTIPP_DOUBLE_OPTIN', 'ID für Double Opt In einfügen' );</code>",
				'wookt'
			);
		?>
		</p>
	</div>
	<?php
}



/**
 * Make sure WooCommerce is active
 * if not - show admin message
 * @since 1.0.0
 */
add_action( 'admin_notices', 'wookt_error_notice_woocommerce' );
function wookt_error_notice_woocommerce() {

	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}

	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( plugin_basename( __FILE__ ) );

	?>
	<div class="error notice">
		<p>
		<?php
			_e(
				'Um das Plugin <strong>"WooCommerce zu Klick Tipp"</strong> verwenden zu können, muss "WooCommerce" aktiviert sein.',
				'wookt'
			);
		?>
		</p>
	</div>
	<?php
}

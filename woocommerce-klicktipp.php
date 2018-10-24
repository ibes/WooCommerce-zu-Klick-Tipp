<?php
/*
Plugin Name:  WooCommerce zu Klick Tipp
Plugin URI:
Description:  Ermöglicht es Kunden anhand ihrer Käufe in Klick Tipp zu taggen
Version:      1.0.0
Author:       Sebastian Gärtner
Author URI:   https://profiles.wordpress.org/ib4s
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/

require_once( dirname( __FILE__ ) . '/klicktipp.api.inc' );



/**
 * Display the product tag field
 * @since 1.0.0
 */
function wookt_add_product_tag_field() {
  $args = array(
    'id'          => 'wookt_product_tag',
    'label'       => __( 'Klick-Tipp-Tag-ID', 'wookt' ),
    'class'       => 'wookt-product-tag-field',
    'placeholder' => '690069 - ID ist eine Zahl',
    'desc_tip'    => true,
    'description' => __( 'Gib hier die ID des Klick-Tipp-Tag ein, der durch den Kauf dieses Produktes gesetzt werden soll.', 'ctwc' ),
  );
  woocommerce_wp_text_input( $args );
}
add_action( 'woocommerce_product_options_general_product_data', 'wookt_add_product_tag_field' );


/**
 * Save the product tag field
 * @since 1.0.0
 */
function wookt_save_product_tag_field( $post_id ) {
  $product = wc_get_product( $post_id );
  $title = isset( $_POST['wookt_product_tag'] ) ? $_POST['wookt_product_tag'] : '';
  $product->update_meta_data( 'wookt_product_tag', sanitize_text_field( $title ) );
  $product->save();
}
add_action( 'woocommerce_process_product_meta', 'wookt_save_product_tag_field' );





function wookt_get_klicktipp_connector() {

}


add_action( 'woocommerce_product_meta_start', 'wookt_show_connected_klicktipp_tag' );
function wookt_show_connected_klicktipp_tag() {

  global $product;

  $wookt_product_tag_id = get_post_meta( $product->id, 'wookt_product_tag', true);
  echo 'WOOKT PRODUCT TAG ID: ' . $wookt_product_tag_id . '<br>';

  $connector = new KlicktippConnector();
  $connector->login( KLICKTIPP_USER, KLICKTIPP_PASS );

  $tag = $connector->tag_get( $wookt_product_tag_id );
  $connector->logout();

  if ( $tag ) {
    echo 'WOOKT PRODUCT TAG NAME: ' . $tag->name;
  } else {
    print $connector->get_last_error();
  }

}




add_action( 'woocommerce_checkout_order_processed', 'wookt_send_customer_data_to_klicktipp', 10, 3 );

function wookt_send_customer_data_to_klicktipp ($order_id, $posted_data, $order) {

  $customer = [];

  $costumer['email'] = $order->get_billing_email();

  $customer['fieldFirstName'] = $order->get_billing_first_name();
  $customer['fieldLastName'] = $order->get_billing_last_name();
  $customer['fieldCompanyName'] = $order->get_billing_company();
  $customer['fieldStreet1'] = $order->get_billing_address_1();
  $customer['fieldStreet2'] = $order->get_billing_address_2();
  $customer['fieldCity'] = $order->get_billing_city();
  $customer['fieldZip'] = $order->get_billing_postcode();
  $customer['fieldCountry'] = $order->get_billing_country();
  $customer['fieldPhone'] = $order->get_billing_phone();


  $tags = [];
  $items = $order->get_items();
  foreach ( $items as $item ) {
    $product_id = $item->get_product_id();
    $tag = get_post_meta( $product_id, 'wookt_product_tag', true);
    if ( $tag ) {
      $tags[] = $tag;
    }
  }

  $to = "mail@gaertner-webentwicklung.de";
  $subject = "new order";
  $message = print_r( $customer, true ) . '<br>';
  $message .= print_r( $tags, true ) . '<br>';

  wp_mail( $to, $subject, $message);

}


// get subscriber ID
$subscriber_id = $connector->subscriber_search($email_address);
if ( $subscriber_id ) {

  $newemail = 'newemailaddress@domain.com';
    // Replace with the new email address.
  $fields = array ( // Use field_index to get all custom fields.
    'fieldFirstName' => 'Martin',
    'fieldLastName' => 'Meier',
  );
  $newsmsnumber = '00491631737743';

  $updated = $connector->subscriber_update( $subscriber_id, $fields, $newemail, $newsmsnumber );

  if ($updated) {
    print 'Subscriber successfully updated.';
  } else {
    print $connector->get_last_error();
  }

} else {

  $email_address = 'emailaddress@domain.com'; // Replace with the email address.
  $double_optin_process_id = 123;
    // Replace 123 with the id of the double optin prozesses.
  $tag_id = 456; // Replace 456 with the tag id.
  $fields = array ( // Use field_index to get all custom fields.
    'fieldFirstName' => 'Thomas',
    'fieldLastName' => 'Weber',
  );
  $smsnumber = '00491631737743';

  // maybe no tag hier
  $subscriber = $connector->subscribe($email_address, $double_optin_process_id, $tag_id, $fields, $smsnumber);

  if ($subscriber) {
    print('<pre>'.print_r($subscriber, true).'</pre>');
  } else {
    print $connector->get_last_error();
  }

}


// for each tag
$result = $connector->tag($email_address, $tag_id);

if ($result) {
  print 'Subscriber successfully tagged.';
} else {
  print $connector->get_last_error();
}

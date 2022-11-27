<?php

add_filter( 'woocommerce_gateway_description', 'payin3_description_fields', 20, 2 );
add_action( 'woocommerce_checkout_process', 'payin3_description_fields_validation' );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'order_data_after_billing_address', 10, 1 );
add_action( 'woocommerce_order_item_meta_end', 'order_item_meta_end', 10, 3 );

function payin3_description_fields( $description, $payment_id ) {

    if ( 'payin3' !== $payment_id ) {
        return $description;
    }
    
    ob_start();

    echo '<div style="display: block; width:300px; height:auto;">';
    echo '<img src="' . plugins_url('../assets/icon.png', __FILE__ ) . '">';

    woocommerce_form_field(
        'user_name',
        array(
            'type' => 'text',
            'label' =>__( 'Payee Name', 'payin3-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );
    

    woocommerce_form_field(
        'payment_number',
        array(
            'type' => 'text',
            'label' =>__( 'Payment Phone Number', 'payin3-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    woocommerce_form_field(
        'user_email',
        array(
            'type' => 'text',
            'label' => __( 'User E-mail', 'payin3-payments-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    echo '</div>';

    $description .= ob_get_clean();

    return $description;
}

function payin3_description_fields_validation() {

    
    if( 'payin3' === $_POST['payment_method'] &&  empty( $_POST['user_name'] ) || !isset( $_POST['user_name'] ) || !preg_match("/^[a-zA-Z-' ]*$/",$_POST['user_name']))  {
        wc_add_notice( 'Please enter your name(only letters and white space allowed)', 'error' );
    }
    if( 'payin3' === $_POST['payment_method'] && empty( $_POST['payment_number'] ) || !isset( $_POST['payment_number'] ) || !preg_match("/^[6-9]\d{9}$/",$_POST['payment_number'])) {
        wc_add_notice( 'Please enter a valid Indian Phone Number', 'error' );
    }
    if( 'payin3' === $_POST['payment_method'] &&  empty( $_POST['user_email'] ) || !isset( $_POST['user_email'] ) || !filter_var($_POST['user_email'], FILTER_VALIDATE_EMAIL) ) {
        wc_add_notice( 'Please enter a valid email address', 'error' );
    }

}


function order_data_after_billing_address( $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'payin3-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';
    echo '<p><strong>' . __( 'BharatX Pay-in-3 Transaction ID:', 'payin3-payments-woo' ) . '</strong><br>' . $order->get_order_key() . '</p>';

}

function order_item_meta_end( $item_id, $item, $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'payin3-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';

}

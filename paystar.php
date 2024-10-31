<?php if ( ! defined( 'ABSPATH' ) ) exit;

/*
Plugin Name: paystar-payment-for-edd
Plugin URI: https://paystar.ir
Description: paystar-payment-for-edd
Version: 1.0
Author: ماژول بانک
Author URI: https://www.modulebank.ir
Text Domain: paystar-payment-for-edd
Domain Path: /languages
 */

load_plugin_textdomain('paystar-payment-for-edd', false, basename(dirname(__FILE__)) . '/languages');
__('paystar-payment-for-edd', 'paystar-payment-for-edd');

function edd_add_gateway_paystar($gateways)
{
	return array_merge($gateways, array(
				'paystar' => array(
					'admin_label'    => __('PayStar', 'paystar-payment-for-edd'),
					'checkout_label' => __('PayStar', 'paystar-payment-for-edd'),
					'supports'       => array('buy_now')
				)
			)
		);
}
add_filter('edd_payment_gateways', 'edd_add_gateway_paystar');

function edd_send_to_gateway_paystar($purchase_data)
{
	global $edd_options;
	$payment_data = array(
			'price'        => $purchase_data['price'], 
			'date'         => $purchase_data['date'], 
			'user_email'   => $purchase_data['post_data']['edd_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => $edd_options['currency'],
			'downloads'    => $purchase_data['downloads'],
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending'
		);
	if ($payment = edd_insert_payment($payment_data))
	{
		require_once(dirname(__FILE__) . '/paystar_payment_helper.class.php');
		$p = new PayStar_Payment_Helper($edd_options['paystar_terminal']);
		$r = $p->paymentRequest(array(
				'amount'   => intval(ceil($purchase_data['price'])),
				'order_id' => $payment . '#' . time(),
				'name'     => @$purchase_data['post_data']['edd_first'].' '.@$purchase_data['post_data']['edd_last'],
				'mail'     => @$purchase_data['post_data']['edd_email'],
				'phone'    => @$purchase_data['post_data']['edd_phone'],
				'callback' => add_query_arg(array('listener' => 'paystar-edd', 'paymentId' => $payment), get_permalink($edd_options['success_page']))
			));
		if ($r)
		{
			session_write_close();
			echo '<form name="frmPayStarPayment" method="post" action="https://core.paystar.ir/api/pardakht/payment"><input type="hidden" name="token" value="'.esc_html($p->data->token).'" />';
			echo '<input class="paystar_btn btn button" type="submit" value="'.__('Pay', 'paystar-payment-for-edd').'" /></form>';
			echo '<script>document.frmPayStarPayment.submit();</script>';
		}
		else
		{
			echo esc_html($p->error);
		}
		exit;
	}
	else
	{
		edd_send_back_to_checkout('?payment-mode='.$purchase_data['post_data']['edd-gateway']);
	}
}
add_action('edd_gateway_paystar', 'edd_send_to_gateway_paystar');

function edd_gateway_paystar_verify()
{
	if (isset($_GET['listener'],$_POST['status'],$_POST['order_id'],$_POST['ref_num']) && $_GET['listener'] == 'paystar-edd')
	{
		global $edd_options;
		$post_status = sanitize_text_field($_POST['status']);
		$post_order_id = sanitize_text_field($_POST['order_id']);
		$post_ref_num = sanitize_text_field($_POST['ref_num']);
		$post_tracking_code = sanitize_text_field($_POST['tracking_code']);
		list($paymentId, $nothing) = explode('#', $post_order_id);
		$amount = intval(ceil(edd_get_payment_amount($paymentId)));
		require_once(dirname(__FILE__) . '/paystar_payment_helper.class.php');
		$p = new PayStar_Payment_Helper($edd_options['paystar_terminal']);
		$r = $p->paymentVerify($x = array(
				'status' => $post_status,
				'order_id' => $post_order_id,
				'ref_num' => $post_ref_num,
				'tracking_code' => $post_tracking_code,
				'amount' => $amount
			));
		if ($r)
		{
			$message = sprintf(__("Payment Completed. PaymentId : %s , RefrenceId : %s", 'paystar-payment-for-edd'), $paymentId, $p->txn_id);
			edd_update_payment_status($paymentId, 'publish');
			edd_insert_payment_note($paymentId, 'Message : '.esc_html($message));
			edd_insert_payment_note($paymentId, 'PayStarObject : '.esc_html(print_r($p,true)));
			edd_insert_payment_note($paymentId, 'ReceivedPost : '.esc_html(print_r($_POST,true)));
			edd_send_to_success_page();
			edd_empty_cart();
		}
		else
		{
			edd_insert_payment_note($paymentId, 'Error : '.esc_html($p->error));
			edd_insert_payment_note($paymentId, 'PayStarObject : '.esc_html(print_r($p,true)));
			edd_insert_payment_note($paymentId, 'ReceivedPost : '.esc_html(print_r($_POST,true)));
			edd_update_payment_status($paymentId, 'failed');
			wp_redirect( get_permalink($edd_options['failure_page']) );
			exit;
		}
	}
}
add_action('init', 'edd_gateway_paystar_verify');

function edd_gateway_paystar_setting($settings)
{
	return array_merge( $settings, array (
				array (
					'id'   => 'paystar_setting',
					'name' => __('PayStar Setting', 'paystar-payment-for-edd'),
					'desc' => __('PayStar Setting', 'paystar-payment-for-edd'),
					'type' => 'header'
				),
				array (
					'id'   => 'paystar_terminal',
					'name' => __('PayStar Terminal', 'paystar-payment-for-edd'),
					'desc' => __('PayStar Terminal', 'paystar-payment-for-edd'),
					'type' => 'text',
					'size' => 'regular'
				),
			)
		);
}
add_filter('edd_settings_gateways', 'edd_gateway_paystar_setting');

function edd_paystar_cc_form_filter(){}
add_filter( 'edd_paystar_cc_form', 'edd_paystar_cc_form_filter' );

?>
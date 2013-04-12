<?php

if ( !defined('AREA') ) { die('Access denied'); }

// Switch between test and live API
if ($processor_data['params']['test'] == 'Y') {
	$request_url = 'https://test-api.pin.net.au/1/charges';
} else {
	$request_url = 'https://api.pin.net.au/1/charges';
}

$payment_description = 'Products:';
// Products
if (!empty($order_info['items'])) {
	foreach ($order_info['items'] as $v) {
		$payment_description .= (preg_replace('/[^\w\s]/i', '', $v['product']) ."; amount=" . $v['amount'] . ";");
	}
}
// Gift Certificates
if (!empty($order_info['gift_certificates'])) {
	foreach ($order_info['gift_certificates'] as $v) {
		$payment_description .= ($v['gift_cert_code'] ."; amount=1;");
	}
}

// Build the data array
$data = array();
$data['email'] = $order_info['email'];
$data['description'] = $order_info['payment_method']['params']['order_prefix'].' order #'.$order_info['order_id'];
$data['amount'] = strval($order_info['total']*100); //cents !
$data['currency'] = 'AUD';
$data['ip_address'] = $order_info['ip_address'];
$card = array(  'number' => $order_info['payment_info']['card_number'],
                'expiry_month' => $order_info['payment_info']['expiry_month'],
                'expiry_year' => '20'.$order_info['payment_info']['expiry_year'], //Dirty, but works for the next 87 years
                'cvc' => $order_info['payment_info']['cvv2'],
                'name' => $order_info['payment_info']['cardholder_name'],
                'address_line1' => $order_info['b_address'],
                'address_city' => $order_info['b_city'],
                'address_postcode' => $order_info['b_zipcode'],
                'address_state' => $order_info['b_state'],
                'address_country' => $order_info['b_country']);
$data['card'] = $card;

// Need to authenticate to make an API call
$auth = array($order_info['payment_method']['params']['pp_secretkey'] );
$http_response = fn_https_request("POST", $request_url , $data, "", "", "", "", "", "", "", $auth);

Registry::set('log_cut_data', array('name', 'number', 'expiry_month', 'expiry_year', 'cvc'));

$return = $http_response[1];
$return = json_decode($return);

$success = $return->response->success;
$amount = $return->response->amount;
$transaction_id = $return->response->token;

if($success == "true" && fn_format_price($amount) == fn_format_price($order_info['total'] * 100)) {
	$pp_response['order_status'] = 'P';
    $message = $return->response->status_message;
	$pp_response["reason_text"] = $message;

} else {
	$pp_response['order_status'] = 'F';
    $error = $return->error_description;
	if (!empty($error)) {
		$pp_response["reason_text"] = "Error: " .$error;
	}
}

if (!empty($transaction_id)) {
	$pp_response["transaction_id"] = $transaction_id;
}

?>
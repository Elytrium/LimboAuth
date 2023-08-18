<?php
/**
 * @brief		Authorize.Net DPM Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Mar 2014
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

\IPS\Output::i()->pageCaching = FALSE;

try
{
	$invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->x_invoice_num );
}
catch ( \Exception $e )
{
	\IPS\Output::i()->sendOutput( '', 404 );
	exit;
}

try
{
	/* Load Gateway */
	$gateway = \IPS\nexus\Gateway::load( \IPS\Request::i()->payment_method );
	if ( !( $gateway instanceof \IPS\nexus\Gateway\AuthorizeNet ) )
	{
		throw new \Exception( $invoice->member->language()->addToStack('gateway_err') );
	}
	$settings = json_decode( $gateway->settings, TRUE );
	
	/* Check hash */
	$message = '^' . implode( '^', array(
		\IPS\Request::i()->x_trans_id,
		\IPS\Request::i()->x_test_request,
		\IPS\Request::i()->x_response_code,
		\IPS\Request::i()->x_auth_code,
		\IPS\Request::i()->x_cvv2_resp_code,
		\IPS\Request::i()->x_cavv_response,
		\IPS\Request::i()->x_avs_code,
		\IPS\Request::i()->x_method,
		\IPS\Request::i()->x_account_number,
		\IPS\Request::i()->x_amount,
		\IPS\Request::i()->x_company,
		\IPS\Request::i()->x_first_name,
		\IPS\Request::i()->x_last_name,
		\IPS\Request::i()->x_address,
		\IPS\Request::i()->x_city,
		\IPS\Request::i()->x_state,
		\IPS\Request::i()->x_zip,
		\IPS\Request::i()->x_country,
		\IPS\Request::i()->x_phone,
		\IPS\Request::i()->x_fax,
		\IPS\Request::i()->x_email,
		\IPS\Request::i()->x_ship_to_company,
		\IPS\Request::i()->x_ship_to_first_name,
		\IPS\Request::i()->x_ship_to_last_name,
		\IPS\Request::i()->x_ship_to_address,
		\IPS\Request::i()->x_ship_to_city,
		\IPS\Request::i()->x_ship_to_state,
		\IPS\Request::i()->x_ship_to_zip,
		\IPS\Request::i()->x_ship_to_country,
		\IPS\Request::i()->x_invoice_num,
	) ) . '^';
			
	if ( isset( \IPS\Request::i()->x_SHA2_Hash ) and isset( $settings['signature_key'] ) and $settings['signature_key'] and \IPS\Login::compareHashes( mb_strtoupper( hash_hmac( 'sha512', $message, hex2bin( $settings['signature_key'] ) ) ), \IPS\Request::i()->x_SHA2_Hash ) )
	{
		// SHA hash is okay
	}
	elseif ( isset( \IPS\Request::i()->x_MD5_Hash ) and isset( $settings['hash'] ) and $settings['hash'] and \IPS\Login::compareHashes( mb_strtoupper( md5( $settings['hash'] . $settings['login'] . \IPS\Request::i()->x_trans_id . \IPS\Request::i()->x_amount ) ), \IPS\Request::i()->x_MD5_Hash ) )
	{
		// MD5 hash is okay
	}
	else
	{
		throw new \Exception( $invoice->member->language()->addToStack('gateway_err') );
	}
			
	/* Was it accepted? */
	if ( \IPS\Request::i()->x_response_code != 1 )
	{
		\IPS\Session\Front::i();
		throw new \IPS\nexus\Gateway\AuthorizeNet\Exception( \IPS\Request::i()->x_response_reason_code );
	}
		
	/* Create a transaction */
	$transaction = new \IPS\nexus\Transaction;
	$transaction->member = \IPS\Member::load( \IPS\Request::i()->x_cust_id );
	$transaction->invoice = $invoice;
	$transaction->method = $gateway;
	$transaction->amount = new \IPS\nexus\Money( \IPS\Request::i()->x_amount, $invoice->currency );
	$transaction->currency = $invoice->currency;
	$transaction->gw_id = \IPS\Request::i()->x_trans_id;
	$transaction->ip = isset( \IPS\Request::i()->x_customer_ip ) ? \IPS\Request::i()->x_customer_ip : \IPS\Request::i()->ipAddress();
	$extra = $transaction->extra;
	$extra['lastFour'] = str_replace( 'X', '', \IPS\Request::i()->x_account_number );
	$transaction->extra  = $extra;
	$transaction->auth = \IPS\DateTime::create()->add( new \DateInterval( 'P30D' ) );
		
	/* Create a MaxMind request */
	$maxMind = NULL;
	if ( \IPS\Settings::i()->maxmind_key and ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways == '*' or \in_array( $transaction->method->id, explode( ',', \IPS\Settings::i()->maxmind_gateways ) ) ) )
	{
		$maxMind = new \IPS\nexus\Fraud\MaxMind\Request;
		$maxMind->setTransaction( $transaction );
		$maxMind->setTransactionType( 'creditcard' );
		$maxMind->setAVS( \IPS\Request::i()->x_avs_code );
	}
		
	/* Check Fraud Rules and capture */
	$transaction->checkFraudRulesAndCapture( $maxMind );
	$transaction->sendNotification();
	
	/* Show thanks screen */
	$url = $transaction->url();
}
catch ( \Exception $e )
{
	$url = $invoice->checkoutUrl()->setQueryString( array( '_step' => 'checkout_pay', 'err' => $e->getMessage() ) );
}

?>
<html>
<head>
<script type='text/javascript' charset='utf-8'>
window.location='<?php echo $url; ?>';
</script>
<noscript>
<meta http-equiv='refresh' content='1;url=<?php echo $url; ?>'>
</noscript>
</head>
<body></body>
</html>
<?php
/**
 * @brief		2Checkout Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Mar 2014
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

try
{
	$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->nexustransactionid );
	
	if ( $transaction->status !== \IPS\nexus\Transaction::STATUS_PENDING )
	{
		throw new \OutOfRangeException();
	}
}
catch ( \OutOfRangeException $e )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=checkout&controller=checkout&do=transaction&id=&t=" . \IPS\Request::i()->nexustransactionid, 'front', 'nexus_checkout' ) );
}

$settings = json_decode( $transaction->method->settings, TRUE );
$hash = mb_strtoupper( md5( $settings['word'] . $settings['sid'] . ( \IPS\NEXUS_TEST_GATEWAYS ? 1 : \IPS\Request::i()->order_number ) . number_format( $transaction->amount->amountAsString(), 2 ) ) );
if ( !\IPS\Login::compareHashes( (string) $hash, (string) \IPS\Request::i()->key ) )
{
	\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl()->setQueryString( array( '_step' => 'checkout_pay', 'err' => $transaction->member->language()->get('gateway_err') ) ) );
}
if ( \IPS\Request::i()->credit_card_processed !== 'Y' )
{
	\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl()->setQueryString( array( '_step' => 'checkout_pay', 'err' => $transaction->member->language()->get('card_refused') ) ) );
}

$transaction->gw_id = \IPS\Request::i()->order_number;
$transaction->save();
	
$maxMind = NULL;
if ( \IPS\Settings::i()->maxmind_key and ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways == '*' or \in_array( $transaction->method->id, explode( ',', \IPS\Settings::i()->maxmind_gateways ) ) ) )
{
	$maxMind = new \IPS\nexus\Fraud\MaxMind\Request( FALSE );
	$maxMind->setIpAddress( $transaction->ip );
	$maxMind->setTransaction( $transaction );
}

$memberJustCreated = $transaction->checkFraudRulesAndCapture( $maxMind );
$transaction->sendNotification();

if ( $memberJustCreated )
{
	\IPS\Session::i()->setMember( $memberJustCreated );
	\IPS\Member\Device::loadOrCreate( $memberJustCreated, FALSE )->updateAfterAuthentication( NULL );
}
\IPS\Output::i()->redirect( $transaction->url() );
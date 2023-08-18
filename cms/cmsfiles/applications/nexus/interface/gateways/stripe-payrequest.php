<?php
/**
 * @brief		Stripe Apple Pay Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		20 Jul 2017
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';
\IPS\Session\Front::i();

\IPS\Output::i()->pageCaching = FALSE;

/* Get the invoice */
try
{
	$invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->invoice );
	
	if ( !$invoice->canView() )
	{
		throw new \OutOfRangeException;
	}
}
catch ( \OutOfRangeException $e )
{
	\IPS\Output::i()->sendOutput( json_encode( array( 'success' => 0 ) ), 500, 'application/json' );
}

/* Get the gateway */
try
{
	$gateway = \IPS\nexus\Gateway::load( \IPS\Request::i()->gateway );
	if ( !( $gateway instanceof \IPS\nexus\Gateway\Stripe ) )
	{
		throw new \OutOfRangeException;
	}
}
catch ( \OutOfRangeException $e )
{
	\IPS\Output::i()->sendOutput( json_encode( array( 'success' => 0 ) ), 500, 'application/json' );
}

/* Create a transaction */
$transaction = new \IPS\nexus\Transaction;
$transaction->member = \IPS\Member::loggedIn();
$transaction->invoice = $invoice;
$transaction->amount = new \IPS\nexus\Money( \IPS\Request::i()->amount, mb_strtoupper( \IPS\Request::i()->currency ) );
$transaction->ip = \IPS\Request::i()->ipAddress();
$transaction->method = $gateway;

/* Create a MaxMind request */
$maxMind = NULL;
if ( \IPS\Settings::i()->maxmind_key and ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways == '*' or \in_array( $transaction->method->id, explode( ',', \IPS\Settings::i()->maxmind_gateways ) ) ) )
{
	$maxMind = new \IPS\nexus\Fraud\MaxMind\Request;
	$maxMind->setTransaction( $transaction );
}

/* Authorize and Capture */			
try
{
	/* Authorize */
	$transaction->auth = $gateway->auth( $transaction, array( "{$gateway->id}_card" => \IPS\Request::i()->token ), $maxMind );
	
	/* Check Fraud Rules and capture */
	$memberJustCreated = $transaction->checkFraudRulesAndCapture( $maxMind );
	if ( $memberJustCreated )
	{
		\IPS\Session::i()->setMember( $memberJustCreated );
		\IPS\Member\Device::loadOrCreate( $memberJustCreated, FALSE )->updateAfterAuthentication( NULL );
	}
	
}
catch ( \Exception $e )
{
	\IPS\Log::log( $e, 'applepay' );
	\IPS\Output::i()->sendOutput( json_encode( array( 'success' => 0 ) ), 500, 'application/json' );
}

/* Send email receipt */
$transaction->sendNotification();

/* Return */
\IPS\Output::i()->sendOutput( json_encode( array( 'success' => 1, 'url' => (string) $transaction->url() ) ), 200, 'application/json' );
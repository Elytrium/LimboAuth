<?php
/**
 * @brief		Braintree Webhook Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Dec 2018
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once '../../../../init.php';

\IPS\Output::i()->pageCaching = FALSE;

$webhookNotification = NULL;
$transaction = NULL;
foreach ( \IPS\nexus\Gateway::roots() as $method )
{
	if ( $method instanceof \IPS\nexus\Gateway\Braintree )
	{
		try
		{
			$webhookNotification = $method->gateway()->webhookNotification()->parse( $_POST["bt_signature"], $_POST["bt_payload"] );
			
			if ( isset( $webhookNotification->subject['dispute'] ) and isset( $webhookNotification->subject['dispute']['transaction'] ) )
			{
				$transaction = \IPS\nexus\Transaction::constructFromData( \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_gw_id=?', $webhookNotification->subject['dispute']['transaction']['id'] ) )->first() );
				if ( $transaction->method->id == $method->id )
				{
					break;
				}
			}
		}
		catch ( \Exception $e ) {}
	}
}
if ( !$webhookNotification or !$transaction )
{
	if( $webhookNotification AND $webhookNotification->kind == 'check' )
	{
		\IPS\Output::i()->sendOutput( 'TEST-CORRECT', 200, 'text/plain' );
	}
	else
	{
		\IPS\Output::i()->sendOutput( 'INVALID', 500, 'text/plain' );
	}
}

switch ( $webhookNotification->kind )
{
	case \Braintree_WebhookNotification::DISPUTE_OPENED:
	
		$transaction->status = $transaction::STATUS_DISPUTED;
		$extra = $transaction->extra;
		$extra['history'][] = array( 's' => $transaction::STATUS_DISPUTED, 'on' => $webhookNotification->subject['dispute']['createdAt']->getTimestamp(), 'ref' => $webhookNotification->subject['dispute']['id'] );
		$transaction->extra = $extra;
		$transaction->save();
		
		if ( $transaction->member )
		{
			$transaction->member->log( 'transaction', array(
				'type'		=> 'status',
				'status'	=> $transaction::STATUS_DISPUTED,
				'id'		=> $transaction->id
			) );
		}
		
		$transaction->invoice->markUnpaid( \IPS\nexus\Invoice::STATUS_CANCELED );
		
		\IPS\core\AdminNotification::send( 'nexus', 'Transaction', $transaction::STATUS_DISPUTED, TRUE, $transaction );
		
		\IPS\Output::i()->sendOutput( 'OK', 200, 'text/plain' );
		exit;
		
	case \Braintree_WebhookNotification::DISPUTE_LOST:
		$transaction->status = $transaction::STATUS_REFUNDED;
		$transaction->save();
		exit;
		
	case \Braintree_WebhookNotification::DISPUTE_WON:
		$transaction->status = $transaction::STATUS_PAID;
		$transaction->save();
		if ( !$transaction->invoice->amountToPay()->amount->isGreaterThanZero() )
		{	
			$transaction->invoice->markPaid();
		}
		exit;
}

\IPS\Output::i()->sendOutput( 'UNEXPECTED_WEBHOOK', 500, 'text/plain' );
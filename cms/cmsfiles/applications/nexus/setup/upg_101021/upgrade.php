<?php
/**
 * @brief		4.1.6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Nov 2015
 */

namespace IPS\nexus\setup\upg_101021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Pending Invoices
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		$cutOff		= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_invoice_pending!=?', 0 ), 'ps_id', array( $limit, $perCycle ) ) as $row )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}
			
			$did++;
			
			/* If this invoice doesn't exist, update the row */
			try
			{
				$pendingInvoice = \IPS\nexus\Invoice::load( $row['ps_invoice_pending'] );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_invoice_pending' => 0 ), array( "ps_id=?", $row['ps_id'] ) );
				continue;
			}
			
			/* If this purchase is listed in the list of renewal_ids, do nothing - the invoice is correct. */
			if ( \in_array( $row['ps_id'], $pendingInvoice->renewal_ids ) )
			{
				continue;
			}
			
			/* Otherwise, let's try and work it out. */
			try
			{
				$pendingInvoice = \IPS\nexus\Invoice::constructFromData( \IPS\Db::i()->select( '*', 'nexus_invoices', array( 'i_status=? AND ' . \IPS\Db::i()->findInSet( 'i_renewal_ids', array( $row['ps_id'] ) ), \IPS\nexus\Invoice::STATUS_PENDING ) )->first() );

				/* It is - update the row. */
				\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_invoice_pending' => $pendingInvoice->id, 'ps_invoice_warning_sent' => 1 ), array( "ps_id=?", $row['ps_id'] ) );
			}
			catch( \UnderflowException $e )
			{
				/* No invoice exists. */
				\IPS\Db::i()->update( 'nexus_purchases', array( 'ps_invoice_pending' => 0 ), array( "ps_id=?", $row['ps_id'] ) );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
		
		return TRUE;
	}
	
	/**
	 * Store certain informaiton in settings to ease performance on menu
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Settings::i()->changeValues( array( 'billing_agreement_gateways' => \count( \IPS\nexus\Gateway::billingAgreementGateways() ) ) );
		return TRUE;
	}
}
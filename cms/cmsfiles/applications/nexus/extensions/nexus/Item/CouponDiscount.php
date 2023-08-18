<?php
/**
 * @brief		Coupon Discount
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		12 May 2014
 */

namespace IPS\nexus\extensions\nexus\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Coupon Discount
 */
class _CouponDiscount extends \IPS\nexus\Invoice\Item\Charge
{
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	Application
	 */
	public static $type = 'coupon';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'ticket';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'coupon';
	
	/**
	 * On Paid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onPaid( \IPS\nexus\Invoice $invoice )
	{
		try
		{
			$coupon = \IPS\nexus\Coupon::load( $this->id );
			if ( $coupon->uses >= 1 )
			{
				$coupon->uses--;
				$coupon->save();
			}
		}
		catch ( \OutOfRangeException $e ) { }
	}
	
	/**
	 * On Unpaid
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @param	string				$status		Status
	 * @return	void
	 */
	public function onUnpaid( \IPS\nexus\Invoice $invoice, $status )
	{
		try
		{
			$coupon = \IPS\nexus\Coupon::load( $this->id );
			if ( $coupon->uses != -1 )
			{
				$coupon->uses++;
				$coupon->save();
			}
			
			$this->onInvoiceCancel( $invoice );
		}
		catch ( \OutOfRangeException $e ) { }
	}
	
	/**
	 * On Invoice Cancel (when unpaid)
	 *
	 * @param	\IPS\nexus\Invoice	$invoice	The invoice
	 * @return	void
	 */
	public function onInvoiceCancel( \IPS\nexus\Invoice $invoice )
	{
		try
		{
			$coupon = \IPS\nexus\Coupon::load( $this->id );
			$uses = $coupon->used_by ? json_decode( $coupon->used_by, TRUE ) : array();
			$member = isset( $this->extra['usedBy'] ) ? $this->extra['usedBy'] : $invoice->member->member_id;
			if ( isset( $uses[ $member ] ) )
			{
				if ( $uses[ $member ] === 1 )
				{
					unset( $uses[ $member ] );
				}
				else
				{
					$uses[ $member ]--;
				}
				$coupon->used_by = json_encode( $uses );
				$coupon->save();
			}
		}
		catch ( \OutOfRangeException $e ) { }
	}
}
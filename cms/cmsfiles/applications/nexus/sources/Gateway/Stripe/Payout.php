<?php
/**
 * @brief		Stripe Pay Out Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		7 Apr 2014
 */

namespace IPS\nexus\Gateway\Stripe;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stripe Pay Out Gateway
 */
class _Payout extends \IPS\nexus\Payout
{
	/**
	 * ACP Settings
	 *
	 * @return	array
	 */
	public static function settings()
	{
		return array();
	}
	
	/**
	 * Payout Form
	 *
	 * @return	array
	 */
	public static function form()
	{
		return array();
	}
	
	/**
	 * Get data and validate
	 *
	 * @param	array	$values	Values from form
	 * @return	mixed
	 * @throws	\DomainException
	 */
	public function getData( array $values )
	{
		return NULL;	
	}
	
	/** 
	 * Process
	 *
	 * @return	void
	 * @throws	\Exception
	 */
	public function process()
	{
		throw new \DomainException('stripe_payout_deprecated');
	}
}
<?php
/**
 * @brief		Invoice Item Class for Charges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\Invoice\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invoice Item Class for Charges
 */
abstract class _Charge extends \IPS\nexus\Invoice\Item
{
	/**
	 * @brief	Act (new/charge)
	 */
	public static $act = 'charge';
	
	/**
	 * @brief	Requires login to purchase?
	 */
	public static $requiresAccount = FALSE;
}
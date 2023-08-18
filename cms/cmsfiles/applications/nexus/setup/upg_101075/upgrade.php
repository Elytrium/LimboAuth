<?php
/**
 * @brief		4.1.17 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		28 Nov 2016
 */

namespace IPS\nexus\setup\upg_101075;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.17 Beta 3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Remove orphaned data from deleted payment gateways
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->delete( 'nexus_customer_cards', \IPS\Db::i()->in( 'card_method', array_values( iterator_to_array( \IPS\Db::i()->select( 'm_id', 'nexus_paymethods' ) ) ), TRUE ) );
		\IPS\Db::i()->delete( 'nexus_billing_agreements', \IPS\Db::i()->in( 'ba_method', array_values( iterator_to_array( \IPS\Db::i()->select( 'm_id', 'nexus_paymethods' ) ) ), TRUE ) );

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
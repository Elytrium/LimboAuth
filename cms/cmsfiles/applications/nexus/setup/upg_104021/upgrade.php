<?php
/**
 * @brief		4.4.3 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		15 Mar 2019
 */

namespace IPS\nexus\setup\upg_104021;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.3 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Repair Custom Field URLs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		\IPS\Task::queue( 'nexus', 'FixMissingAddresses', array(), 5 );

		$purchase = new \IPS\nexus\extensions\core\FileStorage\PurchaseFields;
		\IPS\Task::queue( 'core', 'RepairFileUrls', array( 'storageExtension' => 'filestorage__nexus_PurchaseFields', 'count' => $purchase->count() ), 1 );

		return TRUE;
	}
}
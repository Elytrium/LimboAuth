<?php
/**
 * @brief		4.3.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		28 Dec 2017
 */

namespace IPS\nexus\setup\upg_103000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.3.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Index products
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\nexus\Package\Item' ), 5 );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Adding products to search index';
	}
	
	/**
	 * Store certain informaiton in settings to ease performance on menu
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$counts = array();
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			if ( !isset( $counts[ $gateway->gateway ] ) )
			{
				$counts[ $gateway->gateway ] = 0;
			}
			
			$counts[ $gateway->gateway ]++;
		}
		
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $counts ) ), array( 'conf_key=?', 'gateways_counts' ) );
		\IPS\Settings::i()->gateways_counts = json_encode( $counts );
								
		unset( \IPS\Data\Store::i()->settings );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step2CustomTitle()
	{
		return 'Improving payment performance';
	}
	
	/**
	 * Add the subscriptions menu item
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		\IPS\core\FrontNavigation::insertMenuItem( NULL, array( 'app' => 'nexus', 'key' => 'Subscriptions' ), \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return 'Adding subscriptions menu';
	}
}
<?php
/**
 * @brief		4.4.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Commerce
 * @since		27 Jul 2018
 */

namespace IPS\nexus\setup\upg_104000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update Notification Settings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( explode( ',', \IPS\Settings::i()->nexus_hosting_error_emails ) as $email )
		{
			$member = \IPS\Member::load( $email, 'email' );
			if ( $member->member_id )
			{
				\IPS\Db::i()->insert( 'core_acp_notifications_preferences', array(
					'member'	=> $member->member_id,
					'type'		=> 'nexus_HostingError',
					'view'		=> 1,
					'email'		=> 'always'
				) );
			}
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating AdminCP notifictions";
	}
	
	/**
	 * Add the gift cards menu item
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\core\FrontNavigation::insertMenuItem( NULL, array( 'app' => 'nexus', 'key' => 'Gifts' ), \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Updating menu configuration";
	}
	
	/**
	 * Fix missing currency columns
	 *
	 */
	public function step3()
	{
		if ( $currencies = json_decode( \IPS\Settings::i()->nexus_currency, TRUE ) )
		{
			$definition = \IPS\Db::i()->getTableDefinition( 'nexus_package_base_prices' );

			foreach ( $currencies as $code => $defaults )
			{
				/* Add the column if it doesn't exist */
				if ( !isset( $definition['columns'][ $code ] ) )
				{
					\IPS\Db::i()->addColumn( 'nexus_package_base_prices', array(
						'name'	=> $code,
						'type'	=> 'FLOAT'
					) );
				}
			}
		}
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Fixing missing currency columns";
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		/* Build total spend for customers */
		\IPS\Task::queue( 'nexus', 'RecountTotalSpend', array(), 3 );

		return TRUE;
	}
}
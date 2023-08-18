<?php
/**
 * @brief		4.0.0 Beta 5 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		06 Jan 2015
 */

namespace IPS\cms\setup\upg_100008;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 5 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert menu titles to translatable lang strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Db::i()->select( '*', 'cms_page_menu' ) as $menu )
		{
			\IPS\Lang::saveCustom( 'cms', "cms_menu_title_" . $menu['menu_id'], $menu['menu_title'] );
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
		return "Converting menu titles to translatable fields";
	}
}
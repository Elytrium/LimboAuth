<?php
/**
 * @brief		4.1.18.1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		20 Jan 2017
 */

namespace IPS\cms\setup\upg_101088;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.18.1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Removing widgets from uninstalled plugins - Uninstalled plugins may have left behind entries in this table.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{

		$plugins = array_keys( \IPS\Plugin::plugins() );

		foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $area )
		{
			$update = FALSE;
			$widgets = json_decode( $area['area_widgets'], TRUE );
			foreach ( $widgets as $widgetKey => $widget )
			{
				if( isset( $widget['plugin'] ) AND !\in_array( $widget['plugin'], $plugins ) )
				{
					unset( $widgets[ $widgetKey ] );
					$update = TRUE;
				}
			}

			if( $update )
			{
				\IPS\Db::i()->update( 'cms_page_widget_areas', array( 'area_widgets' => json_encode( array_values( $widgets ) ) ), array( 'area_page_id=? AND area_area=?', $area['area_page_id'], $area['area_area'] ) );
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
		return "Removing widgets from uninstalled plugins";
	}
}
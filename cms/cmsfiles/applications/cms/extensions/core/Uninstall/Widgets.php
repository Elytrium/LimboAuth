<?php
/**
 * @brief		Uninstall callback
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		10 Feb 2016
 */

namespace IPS\cms\extensions\core\Uninstall;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Uninstall callback
 */
class _Widgets
{
	/**
	 * Code to execute before the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function preUninstall( $application )
	{
	}

	/**
	 * Code to execute after the application has been uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @return	array
	 */
	public function postUninstall( $application )
	{
	}

	/**
	 * Code to execute when other applications or plugins are uninstalled
	 *
	 * @param	string	$application	Application directory
	 * @param	int		$plugin			Plugin ID
	 * @return	void
	 */
	public function onOtherUninstall( $application=NULL, $plugin=NULL )
	{
		/* clean up widget areas table */
		foreach ( \IPS\Db::i()->select( '*', 'cms_page_widget_areas' ) as $row )
		{
			$data = json_decode( $row['area_widgets'], true );
			$deleted = false;
			foreach ( $data as $key => $widget )
			{
				if( $application !== NULL )
				{
					if ( isset( $widget['app'] ) and $widget['app'] == $application )
					{
						$deleted = true;
						unset( $data[$key] );
					}
				}

				if( $plugin !== NULL )
				{
					if ( isset( $widget['plugin'] ) and $widget['plugin'] == $plugin )
					{
						$deleted = true;
						unset( $data[$key] );
					}
				}
			}
			
			if ( $deleted === true )
			{
				\IPS\Db::i()->update( 'cms_page_widget_areas', array( 'area_widgets' => json_encode( $data ) ), array( 'area_page_id=? AND area_area=?', $row['area_page_id'], $row['area_area'] ) );
			}
		}
	}
}
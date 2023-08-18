<?php
/**
 * @brief		4.5.0 Beta 10 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		27 Jul 2020
 */

namespace IPS\core\setup\upg_105029;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.5.0 Beta 10 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* If we're using s3 as a file container for theme resources, lets remove any folders that still exist when the theme does not. */
		if ( get_class( \IPS\File::getClass('core_Theme') ) == 'IPS\File\Amazon' )
		{
			/* We need to schedule a little clean up */
			$keys = \IPS\File::getClass('core_Theme')->getContainerKeys( 'css_built_', 100, '/' );
			
			foreach( $keys as $dir )
			{
				$id = str_replace( 'css_built_', '', $dir );
				
				if ( $id > 0 and ! in_array( $id, array_keys( \IPS\Theme::themes() ) ) )
				{
					\IPS\File::getClass('core_Theme')->deleteContainer( $dir );
				}
			}
			
			/* We need to schedule a little clean up */
			$keys = \IPS\File::getClass('core_Theme')->getContainerKeys( 'set_resources_', 100, '/' );
			
			foreach( $keys as $dir )
			{
				$id = str_replace( 'set_resources_', '', $dir );
				
				if ( $id > 0 and ! in_array( $id, array_keys( \IPS\Theme::themes() ) ) )
				{
					\IPS\File::getClass('core_Theme')->deleteContainer( $dir );
				}
			}
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
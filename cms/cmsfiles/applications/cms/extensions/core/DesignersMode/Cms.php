<?php
/**
 * @brief		Designers Mode Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		28 Nov 2014
 */

namespace IPS\cms\extensions\core\DesignersMode;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Designers Mode Extension
 */
class _Cms
{
	/**
	 * Anything need building?
	 *
	 * @return bool
	 */
	public function toBuild()
	{
		/* Yeah.. not gonna even bother trying to match up timestamps and such like and so on etc and etcetera is that spelled right? */
		return TRUE;
	}
	
	/**
	 * Designer's mode on
	 *
	 * @param	mixed	$data	Data
	 * @return bool
	 */
	public function on( $data=NULL )
	{
		\IPS\cms\Theme\Advanced\Theme::export();
		\IPS\cms\Media::exportDesignersModeMedia();
		\IPS\cms\Pages\Page::exportDesignersMode();
		
		return TRUE;
	}
	
	/**
	 * Designer's mode off
	 *
	 * @param	mixed	$data	Data
	 * @return bool
	 */
	public function off( $data=NULL )
	{
		\IPS\cms\Theme\Advanced\Theme::import();
		\IPS\cms\Media::importDesignersModeMedia();
		\IPS\cms\Pages\Page::importDesignersMode();
		
		return TRUE;
	}
}
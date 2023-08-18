<?php
/**
 * @brief		albums Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Mar 2017
 */

namespace IPS\gallery\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * featuredAlbums Widget
 */
class _albums extends \IPS\Content\Widget
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'albums';
	
	/**
	 * @brief	App
	 */
	public $app = 'gallery';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';
	
	/**
	 * @brief Class
	 */
	protected static $class = 'IPS\gallery\Album\Item';

	/**
	 * @brief	Moderator permission to generate caches on [optional]
	 */
	protected $moderatorPermissions	= array( 'can_view_hidden_content', 'can_view_hidden_gallery_album' );

	/**
	 * Initialize widget
	 *
	 * @return	null
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'gallery', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'gallery.css', 'gallery', 'front' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'global.css', 'gallery', 'front' ) );
		parent::init();
	}
}
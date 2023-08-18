<?php
/**
 * @brief		Gallery statistics widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		25 Mar 2014
 */

namespace IPS\gallery\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gallery statistics widget
 */
class _galleryStats extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'galleryStats';
	
	/**
	 * @brief	App
	 */
	public $app = 'gallery';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * @brief	Cache Expiration - 24h
	 */
	public $cacheExpiration = 86400;
	
	/**
	 * Initialize widget
	 *
	 * @return	null
	 */
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'gallery', 'front' ) );

		parent::init();
	}

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$stats = [];

		$approxRows = \IPS\gallery\Image::databaseTableCount( TRUE );

		if ( $approxRows > 1000000 )
		{
			$stats['totalImages'] = $approxRows;
			$stats['totalComments'] = (int) \IPS\Db::i()->query( "SHOW TABLE STATUS LIKE '" . \IPS\Db::i()->prefix . "gallery_comments';" )->fetch_assoc()['Rows'];
		}
		else
		{
			$stats = \IPS\Db::i()->select( 'COUNT(*) AS totalImages, SUM(image_comments) AS totalComments', 'gallery_images', [ "image_approved=?", 1 ] )->first();
		}

		$stats['totalAlbums'] = \IPS\gallery\Album\Item::databaseTableCount( TRUE );

		$latestImage = NULL;
		foreach ( \IPS\gallery\Image::getItemsWithPermission( [], NULL, 1, 'read', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY ) as $latestImage )
		{
			break;
		}

		return $this->output( $stats, $latestImage );
	}
}
<?php
/**
 * @brief		recentImageReviews Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		20 Aug 2015
 */

namespace IPS\gallery\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * recentImageReviews Widget
 */
class _recentImageReviews extends \IPS\Content\WidgetComment
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'recentImageReviews';
	
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
	protected static $class = 'IPS\gallery\Image\Review';

	/**
	 * @brief	Moderator permission to generate caches on [optional]
	 */
	protected $moderatorPermissions	= array( 'can_view_hidden_content', 'can_view_hidden_gallery_image_review' );
}
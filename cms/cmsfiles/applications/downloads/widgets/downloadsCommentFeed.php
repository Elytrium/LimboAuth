<?php
/**
 * @brief		downloadsCommentFeed Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		13 Jul 2015
 */

namespace IPS\downloads\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * downloadsCommentFeed Widget
 */
class _downloadsCommentFeed extends \IPS\Content\WidgetComment
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'downloadsCommentFeed';

	/**
	 * @brief	App
	 */
	public $app = 'downloads';

	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Class
	 */
	protected static $class = 'IPS\downloads\File\Comment';

	/**
	 * @brief	Moderator permission to generate caches on [optional]
	 */
	protected $moderatorPermissions	= array( 'can_view_hidden_content', 'can_view_hidden_downloads_file_comment' );
	
	/**
	* Init the widget
	*
	* @return	void
	*/
	public function init()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'widgets.css', 'downloads', 'front' ) );

		parent::init();
	}
}
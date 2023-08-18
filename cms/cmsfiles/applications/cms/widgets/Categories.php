<?php
/**
 * @brief		Categories Widget
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		24 Sept 2014
 */

namespace IPS\cms\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Categories Widget
 */
class _Categories extends \IPS\Widget\PermissionCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'Categories';
	
	/**
	 * @brief	App
	 */
	public $app = 'cms';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		/* If we're not on a Pages page, return nothing */
		if( !\IPS\cms\Pages\Page::$currentPage )
		{
			return '';
		}

		/* Scope makes it possible for this block to fire before the main block which sets up the dispatcher */
		$db = NULL;
		if ( ! \IPS\cms\Databases\Dispatcher::i()->databaseId )
		{
			try
			{
				$db = \IPS\cms\Pages\Page::$currentPage->getDatabase()->id;
			}
			catch( \Exception $ex )
			{

			}
		}
		else
		{
			$db = \IPS\cms\Databases\Dispatcher::i()->databaseId;
		}

		if ( ! \IPS\cms\Pages\Page::$currentPage->full_path or ! $db )
		{
			return '';
		}

		$url = \IPS\Http\Url::internal( "app=cms&module=pages&controller=page&path=" . \IPS\cms\Pages\Page::$currentPage->full_path, 'front', 'content_page_path', \IPS\cms\Pages\Page::$currentPage->full_path );

		return $this->output($url);
	}
}
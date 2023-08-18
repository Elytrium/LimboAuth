<?php
/**
 * @brief		Page Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		15 Jan 2014
 */

namespace IPS\cms\Pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Page Model
 */
class _Router extends \IPS\Patterns\ActiveRecord
{
	/**
	 * Load Pages Thing based on a URL.
	 * The URL is sometimes complex to figure out, so this will help
	 *
	 * @param	\IPS\Http\Url	$url	URL to load from
	 * @return	\IPS\cms\Pages\Page
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function loadFromUrl( \IPS\Http\Url $url )
	{
		if ( ! isset( $url->queryString['path'] ) )
		{
			throw new \OutOfRangeException();
		}
		
		$path = $url->queryString['path'];
		
		/* First, we need a page */
		$page = \IPS\cms\Pages\Page::loadFromPath( $path );
		
		/* What do we have left? */
		$whatsLeft = trim( preg_replace( '#' . $page->full_path . '#', '', $path, 1 ), '/' );
		
		if ( $whatsLeft )
		{
			/* Check databases */
			$databases = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_databases', array( 'database_page_id > 0' ) ) );
			foreach( $databases as $db )
			{
				$classToTry = 'IPS\cms\Records' . $db['database_id'];
				try
				{
					$record = $classToTry::loadFromSlug( $whatsLeft, FALSE, FALSE );
					
					return $record;
				}
				catch( \Exception $ex ) { }
			}
			
			/* Check categories */
			foreach( $databases as $db )
			{
				$classToTry = 'IPS\cms\Categories' . $db['database_id'];
				try
				{
					$category = $classToTry::loadFromPath( $whatsLeft );
					
					if ( $category !== NULL )
					{
						return $category;
					}
				}
				catch( \Exception $ex ) { }
			}
		}
		else
		{
			/* It's a page */
			return $page;
		}
		
		/* No idea, sorry */
		throw new \InvalidArgumentException;
	}
}
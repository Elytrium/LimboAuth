<?php
/**
 * @brief		Content Extension Generator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Dec 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Extension Generator
 */
abstract class _ExtensionGenerator
{
	/**
	 * @brief	If TRUE, will prevent comment classes being included
	 */
	protected static $contentItemsOnly = FALSE;

	/**
	 * @brief	If TRUE, will include archive classes
	 */
	protected static $includeArchive = FALSE;
	
	/**
	 * Generate Extensions
	 *
	 * @return	array
	 */
	public static function generate()
	{
		$return = array();
		
		foreach ( \IPS\Content::routedClasses( FALSE, static::$includeArchive, static::$contentItemsOnly ) as $_class )
		{
			$obj = new static;
			$obj->class = $_class;
			
			if ( \IPS\Dispatcher::hasInstance()  )
			{
				$language = \IPS\Member::loggedIn()->language();
			}
			else
			{
				$language = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
			}

			$language->words[ 'ipAddresses__core_Content_' . str_replace( '\\', '_', mb_substr( $_class, 4 ) ) ] = $language->addToStack( ( ( isset( $_class::$archiveTitle ) ) ? $_class::$archiveTitle : $_class::$title ) . '_pl', FALSE );
			$return[ 'Content_' . str_replace( '\\', '_', mb_substr( $_class, 4 ) ) ] = $obj;
		}
		
		return $return;
	}
	
	/**
	 * @brief	Content Class
	 */
	public $class;
}
<?php
/**
 * @brief		Templates Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		25 Feb 2014
 */

namespace IPS\cms\Templates;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Template Model
 */
class _Container extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'container_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Table
	 */
	public static $databaseTable = 'cms_containers';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'container_key' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	Have fetched all?
	 */
	protected static $gotAll = FALSE;
	
	/**
	 * Return all containers
	 *
	 * @return	array
	 */
	public static function containers()
	{
		if ( ! static::$gotAll )
		{
			foreach( \IPS\Db::i()->select( '*', static::$databaseTable ) as $container )
			{
				static::$multitons[ $container['container_id'] ] = static::constructFromData( $container );
			}
			
			static::$gotAll = true;
		}
		
		return static::$multitons;
	}
	
	/**
	 * Get all containers by type
	 * 
	 * @param string $type		Type of container (template_block, page, etc)
	 * @return array	of Container objects
	 */
	public static function getByType( $type )
	{
		$return = array();
		static::containers();
		
		if ( $type === 'database' )
		{
			$type = 'dbtemplate';
		}
		
		foreach( static::$multitons as $id => $obj )
		{
			if ( $obj->type === $type )
			{
				$return[] = $obj;
			}
		}
		
		return $return;
	}
	
	/**
	 * Add a new container
	 *
	 * @param	array	$container	Template Data
	 * @return	object	\IPS\cms\Templates
	 */
	public static function add( $container )
	{
		$newContainer = new static;
		$newContainer->_new = TRUE;
		$newContainer->save();
	
		/* Create a unique key */
		if ( empty( $newContainer->key ) )
		{
			$newContainer->key = 'template__' . \IPS\Http\Url\Friendly::seoTitle( $newContainer->name ) . '.' . $newContainer->id;
			$newContainer->save();
		}
		
		return $newContainer;
	}
}
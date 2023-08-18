<?php
/**
 * @brief		GraphQL: AlbumComment Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		23 Feb 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\gallery\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * AlbumCommentType for GraphQL API
 */
class _AlbumCommentType extends \IPS\Content\Api\GraphQL\CommentType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $commentClass	= '\IPS\gallery\Album\Comment';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'gallery_AlbumComment';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'An album comment';

	/**
	 * Get the item type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	public static function getItemType()
	{
		return \IPS\gallery\api\GraphQL\TypeRegistry::album();
	}

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	public function fields()
	{
		$defaultFields = parent::fields();
		return $defaultFields;
	}
}

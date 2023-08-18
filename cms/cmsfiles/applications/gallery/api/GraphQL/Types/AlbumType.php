<?php
/**
 * @brief		GraphQL: Album Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		24 Feb 2019
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
 * AlbumType for GraphQL API
 */
class _AlbumType extends \IPS\Node\Api\GraphQL\NodeType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $nodeClass	= '\IPS\gallery\Album';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'gallery_Album';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'An album';

	/*
	 * @brief 	Follow data passed in to FollowType resolver
	 */
	protected static $followData = array('app' => 'gallery', 'area' => 'album');

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	public function fields()
	{
		$defaultFields = parent::fields();
		$albumFields = array(
			'owner' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
				'resolve' => function ($album) {
					return $album->owner();
				}
			],
			'lastImage' => [
				'type' => \IPS\gallery\api\GraphQL\TypeRegistry::image(),
				'resolve' => function ($album) {
					return $album->lastImage();
				}
			],
			'latestImages' => [
				'type' => TypeRegistry::listOf( \IPS\gallery\api\GraphQL\TypeRegistry::image() ),
				'resolve' => function ($album) {
					return $album->_latestImages;
				}
			],
			'item' => [
				'type' => \IPS\gallery\api\GraphQL\TypeRegistry::albumItem(),
				'resolve' => function ($album) {
					return $album->asItem();
				}
			]
		);

		// Remove unnecessary fields
		unset( $defaultFields['children'] );

		return array_merge( $defaultFields, $albumFields );
	}

	/**
	 * Get the item type that goes with this node type
	 *
	 * @return	ObjectType
	 */
	public static function getItemType()
	{
		return \IPS\gallery\api\GraphQL\TypeRegistry::image();
	}
}

<?php
/**
 * @brief		GraphQL: Coverphoto field defintiion
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL\Fields;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ForumType for GraphQL API
 */
abstract class _CoverPhotoField
{
	/**
	 * Get root type
	 *
	 * @return	array
	 */
	public static function getDefinition($name): array
	{	 
		return [
			'type' => new ObjectType([
				'name' => $name,
				'description' => 'Returns a cover photo',
				'fields' => [
					'image' => TypeRegistry::string(),
					'offset' => TypeRegistry::int()
				],
				'resolveField' => function( $value, $args, $context, $info ) {
					switch ($info->fieldName)
					{
						case 'image':
							return ( $value->file ) ? (string) $value->file->url : null;
						break;
						case 'offset':
							return ( $value->file ) ? $value->offset : null;
						break;
					}
				}
			]),
			'description' => 'Returns a cover photo',
			'resolve' => function ($thing) {
				return $thing->coverPhoto();
			}
		];
	}
}

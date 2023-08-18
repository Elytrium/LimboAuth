<?php
/**
 * @brief		GraphQL: TagsEdit field defintiion
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
 * TagsEditField for GraphQL API
 */
abstract class _TagsEditField
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
				'fields' => [
					'enabled' => [
						'type' => TypeRegistry::boolean(),
						'resolve' => function ($container, $args, $context) {
							$contentClass = $container::$contentItemClass;

							if( \in_array( 'IPS\Content\Tags', class_implements( $contentClass ) ) ){
								return true;
							}

							return false;
						}
					],
					'definedTags' => [
						'type' => TypeRegistry::listOf( TypeRegistry::string() ),
						'resolve' => function ($container, $args, $context) {
							$contentClass = $container::$contentItemClass;
							$tags = array_unique( $contentClass::definedTags( $container ) );

							return $tags;
						}
					]
				]
			]),
			'description' => 'Returns tag editing capabilities',
			'resolve' => function ($container) {
				return $container;
			}
		];
	}
}

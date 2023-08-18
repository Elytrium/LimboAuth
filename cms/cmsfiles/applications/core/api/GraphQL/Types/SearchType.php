<?php
/**
 * @brief		GraphQL: Search Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 Sep 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * SearchType for GraphQL API
 */
class _SearchType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Search',
			'description' => 'Search results',
			'fields' => function () {
				return [
					'count' => [
						'type' => TypeRegistry::int(),
						'description' => "Total number of results",
						'resolve' => function ($search) {
							return $search['count'];
						}
					],
					'results' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::searchResult() ),
						'description' => "List of items in this stream",
						'resolve' => function ($search, $args, $context) {
							return $search['results'];
						}
					],
					'types' => [
						'type' => TypeRegistry::listOf( new ObjectType([
							'name' => 'core_search_types',
							'description' => "The available search types",
							'fields' => [
								'key' => TypeRegistry::string(),
								'lang' => TypeRegistry::string()
							],
							'resolveField' => function ($type, $args, $context, $info) {
								switch( $info->fieldName )
								{
									case 'key':
										return $type;
									break;
									case 'lang':
										return \IPS\Member::loggedIn()->language()->get( $type . '_pl' );
									break;
								}
							}
						]) ),
						'resolve' => function () {
							return array_merge( array('core_members'), array_keys( \IPS\core\modules\front\search\search::contentTypes() ) );
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

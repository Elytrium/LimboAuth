<?php
/**
 * @brief		GraphQL: ActiveUsers Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
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
 * ActiveUsers for GraphQL API
 */
class _ActiveUsersType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_ActiveUsers',
			'fields' => function () {
				return [
					'count' => [
						'type' => TypeRegistry::int(),
						'args' => [
							'includeGuests' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => FALSE
							]
						],
						'resolve' => function ($val, $args) {
							$flags = \IPS\Session\Store::ONLINE_MEMBERS;

							if( $args['includeGuests'] )
							{
								$flags = \IPS\Session\Store::ONLINE_MEMBERS | \IPS\Session\Store::ONLINE_GUESTS;
							}

							return \IPS\Session\Store::i()->getOnlineUsers( $flags | \IPS\Session\Store::ONLINE_COUNT_ONLY, 'asc', NULL, NULL, \IPS\Member::loggedIn()->isAdmin() );
						}
					],
					'users' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::activeUser() ),
						'args' => [
							'includeGuests' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => FALSE
							],
							'sortDir' => [
								'type' => TypeRegistry::eNum([
									'name' => 'activeusers_sort_dir',
									'values' => ['asc', 'desc']
								]),
								'defaultValue' => 'desc'
							],
							'limit' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 25
							],
							'offset' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 0
							]
						],
						'resolve' => function ($val, $args) {
							$flags = \IPS\Session\Store::ONLINE_MEMBERS;

							if( $args['includeGuests'] )
							{
								$flags = \IPS\Session\Store::ONLINE_MEMBERS | \IPS\Session\Store::ONLINE_GUESTS;
							}
							$offset = max( $args['offset'], 0 );
							$limit = min( $args['limit'], 50 );
							$users = \IPS\Session\Store::i()->getOnlineUsers( $flags, $args['sortDir'], array( $offset, $limit ), NULL, \IPS\Member::loggedIn()->isAdmin() );

							return $users;
						}
					]
				];
			}
		];

        parent::__construct($config);
	}
}

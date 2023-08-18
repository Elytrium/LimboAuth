<?php
/**
 * @brief		GraphQL: Notification method type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		30 Jan 2019
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
 * NotificationMethodType for GraphQL API
 */
class _NotificationMethodType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_NotificationMethod',
			'description' => 'Notification method',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Method name",
						'resolve' => function ($method) {
							return $method['name'];
						}
					],
					'disabled' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this method disabled?",
						'resolve' => function ($method) {
							return $method['disabled'];
						}
					],
					'default' => [
						'type' => TypeRegistry::boolean(),
						'description' => "THe default state for this method",
						'resolve' => function ($method) {
							return $method['default'];
						}
					],
					'value' => [
						'type' => TypeRegistry::boolean(),
						'description' => "The member's setting",
						'resolve' => function ($method) {
							return $method['member'];
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

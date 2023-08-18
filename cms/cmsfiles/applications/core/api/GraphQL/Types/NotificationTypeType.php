<?php
/**
 * @brief		GraphQL: Notification types
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
 * NotificationTypeType for GraphQL API
 */
class _NotificationTypeType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_NotificationType',
			'description' => 'Notification type',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Type ID",
						'resolve' => function ($type) {
							return $type['id'];
						}
					],
					'extension' => [
						'type' => TypeRegistry::string(),
						'description' => "Type extension",
						'resolve' => function ($type) {
							return $type['extension'];
						}
					],
					'group' => [
						'type' => TypeRegistry::string(),
						'description' => "Title of the notification group",
						'resolve' => function ($type) {
							return \IPS\Member::loggedIn()->language()->addToStack( 'notifications__' . $type['extension'] );
						}
					],
					'type' => [
						'type' => TypeRegistry::string(),
						'description' => "Type type",
						'resolve' => function ($type) {
							return $type['type'];
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "Type name",
						'resolve' => function ($type) {
							return $type['name'];
						}
					],
					'description' => [
						'type' => TypeRegistry::string(),
						'description' => "Type description",
						'resolve' => function ($type) {
							return \IPS\Member::loggedIn()->language()->addToStack( $type['description'] );
						}
					],
					'lang' => [
						'type' => TypeRegistry::string(),
						'description' => "The translated label",
						'resolve' => function ($type) {
							return \IPS\Member::loggedIn()->language()->addToStack( $type['name'] );
						}
					],
					'inline' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::notificationMethod(),
						'description' => "Inline notification method",
						'resolve' => function ($type) {
							return $type['inline'];
						}
					],
					'email' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::notificationMethod(),
						'description' => "Email notification method",
						'resolve' => function ($type) {
							return $type['email'];
						}
					],
					'push' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::notificationMethod(),
						'description' => "Push notification method",
						'resolve' => function ($type) {
							if ( !isset( $type['push'] ) || !\count( $type['push'] ) || !\IPS\Member::loggedIn()->members_bitoptions['mobile_notifications'] )
							{
								return NULL;
							}

							return $type['push'];
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

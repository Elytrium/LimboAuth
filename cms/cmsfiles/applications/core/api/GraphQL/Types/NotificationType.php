<?php
/**
 * @brief		GraphQL: NotificationType group Type
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
 * NotificationType for GraphQL API
 */
class _NotificationType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Notification',
			'description' => 'Notification',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::int(),
						'description' => "Notification ID",
						'resolve' => function ($notification) {
							return $notification['notification']->id;
						}
					],
					'type' => [
						'type' => TypeRegistry::string(),
						'description' => "Notification type",
						'resolve' => function ($notification) {
							return $notification['notification']->notification_key;
						}
					],
					'app' => [
						'type' => TypeRegistry::string(),
						'description' => "Notification app",
						'resolve' => function ($notification) {
							return $notification['notification']->app;
						}
					],
					'class' => [
						'type' => TypeRegistry::string(),
						'description' => "Notification content class",
						'resolve' => function ($notification) {
							return $notification['notification']->item_class;
						}
					],
					'itemID' => [
						'type' => TypeRegistry::int(),
						'description' => "ID of the content item",
						'resolve' => function ($notification) {
							return $notification['notification']->item_id;
						}
					],
					'sentDate' => [
						'type' => TypeRegistry::int(),
						'description' => "Sent timestamp",
						'resolve' => function ($notification) {
							return $notification['notification']->sent;
						}
					],
					'updatedDate' => [
						'type' => TypeRegistry::int(),
						'description' => "Updated timestamp",
						'resolve' => function ($notification) {
							return $notification['notification']->updated;
						}
					],
					'readDate' => [
						'type' => TypeRegistry::int(),
						'description' => "Read timestamp",
						'resolve' => function ($notification) {
							return $notification['notification']->read_time;
						}
					],
					'author' => [
						'type' => \IPS\core\Api\GraphQL\TypeRegistry::member(),
						'description' => "Member that triggered this notification",
						'resolve' => function ($notification) {
							return isset( $notification['data']['author'] ) ? $notification['data']['author'] : NULL;
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Notification title",
						'resolve' => function ($notification) {
							return isset( $notification['data']['title'] ) ? $notification['data']['title'] : NULL;
						}
					],
					'content' => [
						'type' => TypeRegistry::richText(),
						'description' => "Notification content",
						'resolve' => function ($notification) {
							return isset( $notification['data']['content'] ) ? $notification['data']['content'] : NULL;
						}
					],
					'url' => [
						'type' => TypeRegistry::url(),
						'description' => "URL to content that triggered notification",
						'resolve' => function ($notification) {
							return $notification['data']['url'];
						}
					],
					'unread' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this notification unread by the recipient?",
						'resolve' => function ($notification) {
							return (bool) $notification['data']['unread'];
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

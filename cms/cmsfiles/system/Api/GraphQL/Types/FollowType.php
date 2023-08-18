<?php
/**
 * @brief		GraphQL: Follow type defintiion
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Sept 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * FollowType for GraphQL API
 */
class _FollowType extends ObjectType
{
	/**
	 * Get root type
	 *
	 * @return	array
	 */
	public function __construct()
	{		 
		$config = [
			'name' => 'Follow',
			'description' => 'Returns the follow data for a node, item or member',
			'fields' => [
				'id' => [
					'type' => TypeRegistry::id(),
					'resolve' => function ($followData) {
						// This field allows Apollo to more effectively cache results. We don't include
						// the member ID in the MD5 here though because it will change when logging in.
						return md5( $followData['app'] . ';' . $followData['area'] . ';' . $followData['id'] );
					}
				],
				'followID' => [
					'type' => TypeRegistry::id(),
					'resolve' => function ($followData) {
						return md5( $followData['app'] . ';' . $followData['area'] . ';' . $followData['id'] . ';' .  \IPS\Member::loggedIn()->member_id );
					}
				],
				'isFollowing' => [
					'type' => TypeRegistry::boolean(),
					'resolve' => function ($followData) {
						return \IPS\Member::loggedIn()->following( $followData['app'], $followData['area'], $followData['id'] );
					}
				],
				'followType' => [
					'type' => TypeRegistry::eNum([
						'name' => 'core_Follow_followType',
						'values' => ['NOT_FOLLOWING', 'PUBLIC', 'ANONYMOUS']
					]),
					'resolve' => function ($followData) {
						if( !\IPS\Member::loggedIn()->following( $followData['app'], $followData['area'], $followData['id'] ) )
						{
							return 'NOT_FOLLOWING';
						}

						try
						{
							$current = \IPS\Db::i()->select( 'follow_is_anon', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=? AND follow_member_id=?', $followData['app'], $followData['area'], $followData['id'], \IPS\Member::loggedIn()->member_id ), NULL, array(), NULL, NULL )->first();

							return $current['follow_is_anon'] ? 'ANONYMOUS' : 'PUBLIC';
						}
						catch ( \UnderflowException $e )
						{
							return 'NOT_FOLLOWING';
						}
					}
				],
				'followCount' => [
					'type' => TypeRegistry::int(),
					'resolve' => function ($followData) {
						if( isset( $followData['node'] ) && isset( $followData['nodeClass'] ) )
						{
							$nodeClass = $followData['nodeClass'];
							return $nodeClass::$contentItemClass::containerFollowerCount( $followData['node'] );
						}
						elseif( isset( $followData['member'] ) )
						{
							return $followData['member']->followersCount();
						}
						else
						{
							return $followData['item']->followersCount();
						}
					}
				],
				'anonFollowCount' => [
					'type' => TypeRegistry::int(),
					'resolve' => function ($followData) {
						if( isset( $followData['node'] ) && isset( $followData['nodeClass'] ) )
						{
							$nodeClass = $followData['nodeClass'];
							return $nodeClass::$contentItemClass::containerFollowerCount( $followData['node'], $nodeClass::$contentItemClass::FOLLOW_ANONYMOUS );
						}
						elseif( isset( $followData['member'] ) )
						{
							return $followData['member']->followersCount( \IPS\Member::FOLLOW_ANONYMOUS );
						}
						else
						{
							return $followData['item']->followersCount( $followData['itemClass']::FOLLOW_ANONYMOUS );
						}
					}
				],
				'followers' => [
					'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::member() ),
					'args' => [
						'offset' => [
							'type' => TypeRegistry::int(),
							'defaultValue' => 0
						],
						'limit' => [
							'type' => TypeRegistry::int(),
							'defaultValue' => 25
						]
					],
					'resolve' => function ($followData, $args) {
						$offset = max( $args['offset'], 0 );
						$limit = min( $args['limit'], 50 );

						if( isset( $followData['node'] ) && isset( $followData['nodeClass'] ) )
						{
							$nodeClass = $followData['nodeClass'];
							$_followers = $nodeClass::$contentItemClass::containerFollowers( $followData['node'], $nodeClass::$contentItemClass::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( $offset, $limit ), 'name' );
						}
						elseif( isset( $followData['member'] ) )
						{
							$_followers = $followData['member']->followers( \IPS\Member::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( $offset, $limit ), 'name' );
						}
						else
						{
							$itemClass = $followData['itemClass'];
							$_followers = $followData['item']->followers( $itemClass::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( $offset, $limit ), 'name' );
						}

						if( $_followers === NULL )
						{
							return NULL;
						}

						$followers = array();

						foreach( $_followers as $followerRow )
						{
							$followers[] = \IPS\Member::load( $followerRow['follow_member_id'] );
						}

						return $followers;
					}
				],
				// @todo this will need to be extensively modified when I add support for following clubs
				'followOptions' => [
					'type' => TypeRegistry::listOf( new ObjectType([
						'name' => 'core_Follow_Options',
						'fields' => [
							'type' => TypeRegistry::string(),
							'selected' => TypeRegistry::boolean(),
							'disabled' => TypeRegistry::boolean()
						],
						'resolveField' => function ($followOption, $args, $context, $info) {
							return $followOption[ $info->fieldName ];
						}
					]) ),
					'resolve' => function ($followData, $args) {
						// No options if we're a guest
						if( !\IPS\Member::loggedIn()->member_id )
						{
							return array();
						}

						/* Do we follow it? */
						try
						{
							$current = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=? AND follow_member_id=?', $followData['app'], $followData['area'], $followData['id'], \IPS\Member::loggedIn()->member_id ), NULL, array(), NULL, NULL )->first();
						}
						catch ( \UnderflowException $e )
						{
							$current = FALSE;
						}

						// What kind of following are we doing?
						if( isset( $followData['node'] ) )
						{
							$followType = 'new_content';
						}
						elseif( isset( $followData['member'] ) )
						{
							$followType = 'follower_content';
						}
						else
						{
							$followType = 'new_comment';
						}

						$notificationConfiguration = \IPS\Member::loggedIn()->notificationsConfiguration();
						$notificationConfiguration = isset( $notificationConfiguration[ $followType ] ) ? $notificationConfiguration[ $followType ] : array();

						// Now figure out which options we can have
						$options = array( array(
							'type' => 'immediate', 
							'disabled' => empty( $notificationConfiguration ), // If notification configuration is empty, show but disable 'immediate'
							'selected' => ( $current && $current['follow_notify_freq'] === 'immediate' ) 
						) );

						if( !isset( $followData['member'] ) )
						{
							$options[] = array('type' => 'weekly', 'disabled' => false, 'selected' => ( $current && $current['follow_notify_freq'] === 'weekly' ) );
							$options[] = array('type' => 'daily', 'disabled' => false, 'selected' => ( $current && $current['follow_notify_freq'] === 'daily' ) );
							$options[] = array('type' => 'none', 'disabled' => false, 'selected' => ( $current && $current['follow_notify_freq'] === 'none' ) );
						}

						return $options;
					}
				]
			]
		];

		parent::__construct( $config );
	}
}

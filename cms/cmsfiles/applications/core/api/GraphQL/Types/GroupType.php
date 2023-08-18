<?php
/**
 * @brief		GraphQL: Group Type
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
 * GroupType for GraphQL API
 */
class _GroupType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Group',
			'description' => 'Member groups',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::int(),
						'description' => "Group ID",
						'resolve' => function ($group, $args, $context, $info) {
							return $group->g_id;
						}
					],
					'groupType' => [
						'type' => TypeRegistry::eNum([
							'name' => 'groupType',
							'values' => ['GUEST', 'MEMBER', 'ADMIN']
						]),
						'description' => "Is this a guest, member or admin group?",
						'resolve' => function ($group, $args, $context, $info) {
							if( $group->g_id == \IPS\Settings::i()->guest_group )
							{
								return 'GUEST';
							}
							elseif( isset( \IPS\Member::administrators()['g'][ $group->g_id ] ) )
							{
								return 'ADMIN';
							}
							else
							{
								return 'MEMBER';
							}
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "Group name",
						'args' => [
							'formatted' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => FALSE
							]
						],
						'resolve' => function ($group, $args, $context, $info) {
							return ( $args['formatted'] ) ? $group->get_formattedName() : $group->name;
						}
					],
					'canAccessSite' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Can users in this group access the site?",
						'resolve' => function ($group, $args, $context, $info) {
							return $group->g_view_board;
						}
					],
					'canAccessOffline' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Can users in this group access the site when it's offline?",
						'resolve' => function ($group, $args, $context, $info) {
							return $group->g_access_offline;
						}
					],
					'canTag' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Can users in this group tag content (if enabled)?",
						'resolve' => function ($group) {
							return !$group->g_id || !\IPS\Settings::i()->tags_enabled ? FALSE : !( $group->g_bitoptions['gbw_disable_tagging'] );
						}
					],
					'maxMessengerRecipients' => [
						'type' => TypeRegistry::int(),
						'description' => "Maximum number of recipients to a PM sent by a member in this group",
						'resolve' => function ($group) {
							return \IPS\Member::loggedIn()->group['g_max_mass_pm'];
						}
					],
					'members' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::member() ),
						'description' => "List of members in this group",
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
						'resolve' => function ($group, $args, $context, $info) {
							/* If we don't allow filtering by this group, don't return the members in it */
							if( $group->g_bitoptions['gbw_hide_group'] )
							{
								return NULL;
							}

							$offset = max( $args['offset'], 0 );
							$limit = min( $args['limit'], 50 );
							return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', array('member_group_id=?', $group->g_id), NULL, array( $offset, $limit ) ), 'IPS\Member' );
						}
					]
				];
			}
		];

		parent::__construct($config);  
	}
}

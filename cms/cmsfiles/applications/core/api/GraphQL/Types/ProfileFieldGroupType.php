<?php
/**
 * @brief		GraphQL: Profile field group Type
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
 * StreamType for GraphQL API
 */
class _ProfileFieldGroupType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_ProfileFieldGroup',
			'description' => 'Custom profile field groups',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Profile field group ID (relative to member)",
						'resolve' => function ($fieldGroup) {
							return $fieldGroup['id'];
						}
					],
					'groupId' => [
						'type' => TypeRegistry::int(),
						'description' => "Actual profile field group ID",
						'resolve' => function ($fieldGroup) {
							return $fieldGroup['groupId'];
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Profile field group title",
						'resolve' => function ($fieldGroup) {
							return \IPS\Member::loggedIn()->language()->get( $fieldGroup['title'] );
						}
					],
					'fields' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::profileField() ),
						'description' => "List of fields in this profile field group",
						'resolve' => function ($fieldGroup) {
							return $fieldGroup['fields'];
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

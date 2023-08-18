<?php
/**
 * @brief		GraphQL: Profile field Type
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
 * ProfileFieldType for GraphQL API
 */
class _ProfileFieldType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{

		$config = [
			'name' => 'core_ProfileField',
			'description' => 'Custom profile field',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Profile field ID, relative to member",
						'resolve' => function ($field) {
							return $field['id'];
						}
					],
					'fieldId' => [
						'type' => TypeRegistry::int(),
						'description' => "Actual profile field ID",
						'resolve' => function ($field) {
							return $field['fieldId'];
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Profile field title",
						'resolve' => function ($field) {
							return \IPS\Member::loggedIn()->language()->get( $field['title'] );
						}
					],
					'value' => [
						'type' => TypeRegistry::string(),
						'description' => "Profile field value",
						'resolve' => function ($field) {
							return $field['value'];
						}
					],
					'type' => [
						'type' => TypeRegistry::string(),
						'description' => "Profile field type",
						'resolve' => function ($field) {
							return $field['type'];
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}

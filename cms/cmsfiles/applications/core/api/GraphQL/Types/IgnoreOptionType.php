<?php
/**
 * @brief		GraphQL: Ignore option Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 Oct 2018
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
 * IgnoreOptionType for GraphQL API
 */
class _IgnoreOptionType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_IgnoreOption',
			'description' => 'IgnoreOption type',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Ignore option ID",
						'resolve' => function ($ignore) {
							return md5( $ignore['member_id'] . $ignore['type'] );
						}
					],
					'type' => [
						'type' => TypeRegistry::string(),
						'description' => "Ignore type name",
						'resolve' => function ($ignore) {
							return $ignore['type'];
						}
					],
					'isBeingIgnored' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this member being ignored for the current content type?",
						'resolve' => function ($ignore) {
							return $ignore['is_being_ignored'];
						}
					],
					'lang' => [
						'type' => TypeRegistry::string(),
						'description' => "The language string for this ignore type",
						'resolve' => function ($ignore) {
							return \IPS\Member::loggedIn()->language()->addToStack( 'ignore_' . $ignore['type'] );
						}
					]
				];
			}
		];

		parent::__construct($config);  
	}
}

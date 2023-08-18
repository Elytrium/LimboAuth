<?php
/**
 * @brief		GraphQL: Item State Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		21 Jun 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL\Types;
use GraphQL\Type\Definition\InputObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ItemStateType for GraphQL API
 */
class _ItemStateType extends InputObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'ItemState',
			'fields' => [
				'locked' => TypeRegistry::boolean(),
				'featured' => TypeRegistry::boolean(),
				'pinned' => TypeRegistry::boolean(),
				'hidden' => TypeRegistry::eNum([
					'name' => 'hiddenState',
					'values' => [
						'visible' => 0,
						'unapproved' => -1,
						'hidden' => 1
					]
				])
			]
		];

		parent::__construct( $config );
	}
}
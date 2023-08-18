<?php
/**
 * @brief		GraphQL: Image Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 May 2019
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
 * ImageType for GraphQL API
 */
class _ImageType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'Image',
			'description' => 'Represents an image with width/height data',
			'fields' => function () {
				return [
					'url' => [
						'type' => TypeRegistry::string(),
						'description' => "The URL to the image",
					],
					'width' => [
						'type' => TypeRegistry::int(),
						'description' => "The width in pixels",
					],
					'height' => [
						'type' => TypeRegistry::int(),
						'description' => "The height in pixels",
					],
				];
			},
			'resolveField' => function ($data, $args, $context, $info) {
				return $data[ $info->fieldName ];
			}
		];

		parent::__construct($config);
	}
}
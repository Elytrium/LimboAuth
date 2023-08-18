<?php
/**
 * @brief		GraphQL: Upload progress Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		27 Mar 2020
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
 * UploadProgressType for GraphQL API
 */
class _UploadProgressType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_UploadProgress',
			'description' => 'Upload progress',
			'fields' => function () {
				return [
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "The attachment's original filename (not necessarily what is actually stored on disk)",
						'resolve' => function( $attachment ) {
							return $attachment['name'];
						}
					],
					'ref' => [
						'type' => TypeRegistry::string(),
						'description' => "Upload ref",
						'resolve' => function( $attachment ) {
							return $attachment['ref'];
						}
					],
				];
			}
		];

		parent::__construct($config);
	}
}

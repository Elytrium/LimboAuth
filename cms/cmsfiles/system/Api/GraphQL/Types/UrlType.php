<?php
/**
 * @brief		GraphQL: URL Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
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
 * URLType for GraphQL API
 */
class _UrlType extends ObjectType
{
    /**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'URL',
			'description' => 'Represents an internal URL',
			'fields' => function () {
				return [
					'full' => [
						'type' => TypeRegistry::string(),
						'description' => "The full URL",
						'resolve' => function ($url, $args, $context) {
							return (string) $url;
						}
					],
					'app' => [
						'type' => TypeRegistry::string(),
						'description' => "Application",
					],
					'module' => [
						'type' => TypeRegistry::string(),
						'description' => "Module",
					],
					'controller' => [
						'type' => TypeRegistry::string(),
						'description' => "Controller",
					],
					'query' => [
						'type' => TypeRegistry::listOf( new ObjectType([
							'name' => 'URL_query',
							'fields' => [
								'key' => TypeRegistry::string(),
								'value' => TypeRegistry::string()
							],
							'resolveField' => function ($query, $args, $context, $info) {
								return $query[ $info->fieldName ];
							}
						])),
						'resolve' => function ($url) {
							if( !( $url instanceof \IPS\Http\Url ) )
							{
								$url = \IPS\Http\Url::createFromString( $url );
							}

							$pieces = array();

							foreach( $url->queryString as $key => $value )
							{
								$pieces[] = array(
									'key' => $key,
									'value' => $value
								);
							}

							return $pieces;
						}
					]
				];
			},
			'resolveField' => function ($url, $args, $context, $info) {
				if( !( $url instanceof \IPS\Http\Url ) )
				{
					$url = \IPS\Http\Url::createFromString( $url );
				}

				$urlPieces = $url->hiddenQueryString;
				if( \in_array( $info->fieldName, array('app', 'module', 'controller') ) )
				{
					return $urlPieces[ $info->fieldName ];
				}

				return (string) $url;
			}
		];

		parent::__construct($config);
	}
}
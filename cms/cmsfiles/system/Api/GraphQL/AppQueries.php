<?php
/**
 * @brief		GraphQL: API query controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		15 Oct 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core controller for GraphQL API
 * @todo maybe this shouldn't be a class since it only has a static method?
 */
abstract class _AppQueries
{

	/**
	 * Get the supported query types in this app
	 *
	 * @return	array
	 */
	public static function queries($app): array
	{
		return [
			'moduleAccess' => new \IPS\Api\GraphQL\Queries\ModuleAccess($app)
		];
	}
}

<?php
/**
 * @brief		GraphQL: ModuleAccess query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ModuleAccess query for GraphQL API
 */
class _ModuleAccess
{
	public function __construct($app)
	{
        $this->app = $app;
    }

	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns module view permissions";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return [];
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::moduleAccess();
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\Application
	 */
	public function resolve($val, $args, $context)
	{
		return $this->app;
	}
}

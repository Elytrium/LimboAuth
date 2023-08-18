<?php
/**
 * @brief		GraphQL: ActiveUsers query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * OurPicks query for GraphQL API
 */
class _ActiveUsers
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns active user information";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array();
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return \IPS\core\api\GraphQL\TypeRegistry::activeUsers();
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @return	\IPS\core\Stream
	 */
	public function resolve($val)
	{
		if ( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'online', 'front' ) ) )
		{
			return NULL;
		}

		// There's no real value here, we're just passing on to the next resolver in the chain
		return $val;
	}
}

<?php
/**
 * @brief		GraphQL: Login handler query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		30 Oct 2018
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
 * Login handler query for GraphQL API
 */
class _LoginHandlers
{

	/*
	 * @brief 	Query description
	 */
	public static $description = "Return login handler data";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			
		);
	}

	/**
	 * Return the query return type
	 */
	public function type()
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::login() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\Member|null
	 */
	public function resolve($val, $args)
	{
		$login = new \IPS\Login;
		return $login->buttonMethods();
	}
}
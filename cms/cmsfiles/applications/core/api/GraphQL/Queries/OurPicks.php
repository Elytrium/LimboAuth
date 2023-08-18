<?php
/**
 * @brief		GraphQL: OurPicks query
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
class _OurPicks
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns promoted items";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			'count' => TypeRegistry::int()
		);
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::promotedItem() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\core\Stream
	 */
	public function resolve($val, $args, $context)
	{
		$limit = 7;
		if( isset( $args['count'] ) && \is_int( $args['count'] ) )
		{
			$limit = $args['count'];
		}

		$items = \IPS\core\Promote::internalStream( $limit );
		if( !\count( $items ) )
		{
			return NULL;
		}

		return $items;
	}
}

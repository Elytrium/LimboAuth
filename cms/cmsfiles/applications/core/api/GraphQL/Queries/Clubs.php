<?php
/**
 * @brief		GraphQL: Clubs query
 * @author		<a href='https://invisioncommunity.com/'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2023 Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Cloud
 * @since       09 February 2023
 */

namespace IPS\core\api\GraphQL\Queries;

use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) ? $_SERVER[ 'SERVER_PROTOCOL' ] : 'HTTP/1.0' ).' 403 Forbidden' );
	exit;
}

/**
 * Club query for GraphQL API
 */
class _Clubs
{
	/*
	 * @brief 	Query description
	 */
	public static string $description = 'Returns a list of clubs';

 	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
		'clubs' => TypeRegistry::listOf( TypeRegistry::int() ),
		'offset' => [
			'type' => TypeRegistry::int(),
			'defaultValue' => 0
		],
		'limit' => [
			'type' => TypeRegistry::int(),
			'defaultValue' => 25
		],
		'orderBy' => [
			'type' => TypeRegistry::eNum( [
									  'name' => 'clubs_order_by',
									  'description' => 'Fields on which topics can be sorted',
									  'values' => \IPS\core\api\GraphQL\Types\ClubType::getOrderByOptions()
									  ] ),
			'defaultValue' => NULL // will use default sort option
		],
		'orderDir' => [
		'type' => TypeRegistry::eNum( [
									  'name' => 'clubs_order_dir',
									  'description' => 'Directions in which items can be sorted',
									  'values' => [ 'ASC', 'DESC' ]
									  ] ),
		'defaultValue' => 'DESC'
		],

		);
	}



	/**
	 * Return the query return type
	 */
	public function type(): \GraphQL\Type\Definition\ListOfType
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::club() );
	}

	/**
	 * Resolves this query
	 *
	 * @param mixed    Value passed into this resolver
	 * @param array    Arguments
	 * @param array    Context values
	 * @return    \IPS\Patterns\ActiveRecordIterator[\IPS\Member\Club]
	 */
	public function resolve( mixed $val, array $args, array $context ): \IPS\Patterns\ActiveRecordIterator
	{
		$where = [];
		$sortBy = ( isset( $args['orderBy'] ) and \in_array( $args['orderBy'], \IPS\core\api\GraphQL\Types\ClubType::getOrderByOptions() ) ) ? $args['orderBy'] : 'name';
		$sortDir = ( isset( $args['orderDir'] ) and \in_array( mb_strtolower( $args['orderDir'] ), array( 'asc', 'desc' ) ) ) ? $args['orderDir'] : 'desc';
		$limit =( isset( $args['orderDir'] ) and \is_int( $args['limit'] ) ) ? $args['limit'] : 25;

		$query = \IPS\Db::i()->select( '*', 'core_clubs', $where, "{$sortBy} {$sortDir}", $limit );
		return new \IPS\Patterns\ActiveRecordIterator( $query, 	'\IPS\Member\Club' );
	}
}

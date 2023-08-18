<?php
/**
 * @brief		GraphQL: PopularContributors query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		11 Feb 2019
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
 * PopularContributors query for GraphQL API
 */
class _PopularContributors
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns popular contributors";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			'limit' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 5
			],
			'period' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_PopularContributors_period',
					'values' => ['WEEK', 'MONTH', 'YEAR', 'ALL']
				]),
				'defaultValue' => 'WEEK'
			]
		);
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::popularContributor() );
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
		/* How many? */
		$limit = min( $args['limit'], 25 );
		
		/* What timeframe? */
		$where = array( array( 'member_received > 0' ) );
		$timeframe = 'all';
		if ( $args['period'] !== 'ALL' )
		{
			switch ( $args['period'] )
			{
				case 'WEEK':
					$where[] = array( 'rep_date>' . \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) )->getTimestamp() );
					break;
				case 'MONTH':
					$where[] = array( 'rep_date>' . \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) )->getTimestamp() );
					break;
				case 'YEAR':
					$where[] = array( 'rep_date>' . \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) )->getTimestamp() );
					break;
			}

			$innerQuery = \IPS\Db::i()->select( 'core_reputation_index.member_received as member, SUM(rep_rating) as rep', 'core_reputation_index', $where, NULL, NULL, 'member' );
			$topContributors = iterator_to_array( \IPS\Db::i()->select( 'member, rep', array( $innerQuery, 'in' ), NULL, 'rep DESC', $limit )->setKeyField('member')->setValueField('rep') );
		}
		else
		{
			$topContributors = iterator_to_array( \IPS\Db::i()->select( 'member_id as member, pp_reputation_points as rep', 'core_members', array( 'pp_reputation_points > 0' ), 'rep DESC', $limit )->setKeyField('member')->setValueField('rep') );
		}

		/* Contruct their data */	
		/* The PopularContributorsType will call ::load on the member ID to get the object */
		foreach ( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->in( 'member_id', array_keys( $topContributors ) ) ) as $member )
		{
			\IPS\Member::constructFromData( $member );
		}

		$output = array();

		foreach ( $topContributors as $memberID => $rep )
		{
			$output[] = array(
				'rep' => $rep,
				'member_id' => $memberID
			);
		}

		return $output;
	}
}

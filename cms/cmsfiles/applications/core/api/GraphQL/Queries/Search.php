<?php
/**
 * @brief		GraphQL: Search query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		22 Sep 2018
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
 * Search query for GraphQL API
 */
class _Search
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns search results";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			'term' => TypeRegistry::string(),
			'type' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_search_types_input',
					'description' => "The available search types",
					'values' => array_merge( array('core_members'), array_keys( \IPS\core\modules\front\search\search::contentTypes() ) )
				]),
			],
			'offset' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 0
			],
			'limit' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 25
			],
			'orderBy' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_search_order_by',
					'description' => 'Fields on which reuslts can be sorted',
					'values' => [ 'newest', 'relevancy', 'joined', 'name', 'member_posts', 'pp_reputation_points' ]
				])
			],
			'orderDir' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_search_order_dir',
					'description' => 'Directions in which reuslts can be sorted',
					'values' => [ 'ASC', 'DESC' ]
				]),
				'defaultValue' => 'DESC'
			],
		);
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return \IPS\core\api\GraphQL\TypeRegistry::search();
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
		$where = array();
		$returnObject = array();
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );

		// Member search
		if ( isset( $args['type'] ) and $args['type'] === 'core_members' and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) )
		{
			if ( $args['term'] )
			{
				$where = array( array( 'LOWER(core_members.name) LIKE ?', '%' . mb_strtolower( trim( $args['term'] ) ) . '%' ) );
			}
			else
			{
				$where = array( array( 'core_members.name<>?', '' ) );
			}

			// Ordering
			$orderDir = isset( $args['orderDir'] ) ? $args['orderDir'] : 'DESC';
			$orderBy = 'name';

			if( isset( $args['orderBy'] ) && \in_array( $args['orderBy'], array( 'joined', 'name', 'member_posts', 'pp_reputation_points' ) ) )
			{
				$orderBy = $args['orderBy'];
			}

			$order = $orderBy . ' ' . $orderDir;

			// Get results
			$select	= \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where );
			$select->join( 'core_pfields_content', 'core_pfields_content.member_id=core_members.member_id' );
			$returnObject['count'] = $select->first();

			$select	= \IPS\Db::i()->select( 'core_members.*', 'core_members', $where, $order, array( $offset, $limit ) );
			$select->join( 'core_pfields_content', 'core_pfields_content.member_id=core_members.member_id' );
			
			$returnObject['results'] = new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member' );

			return $returnObject;
		}

		// Content search
		$query = \IPS\Content\Search\Query::init();
		$types = \IPS\core\modules\front\search\search::contentTypes();

		if( isset( $args['type'] ) && !empty( $args['type'] ) )
		{
			$class = $types[ $args['type'] ];
			$filter = \IPS\Content\Search\ContentFilter::init( $class );
			$query->filterByContent( array( $filter ) );
		}

		$orderBy = $query->getDefaultSortMethod();

		// Ordering
		if( isset( $args['orderBy'] ) )
		{
			switch( $args['orderBy'] )
			{
				case 'newest':
					$query->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED );
				break;
				case 'relevancy':
					$query->setOrder( \IPS\Content\Search\Query::ORDER_RELEVANCY );
				break;
				default:
					$query->setOrder( $orderBy );
				break;
			}
		}
		else
		{
			$query->setOrder( $orderBy );
		}

		// Get page
		// We don't know the count at this stage, so figure out the page number from
		// our offset/limit
		$page = 1;

		if( $offset > 0 )
		{
			$page = floor( $offset / $limit ) + 1;
		}

		$query->setLimit( $limit )->setPage( $page );

		/* Run query */
		$returnObject['results'] = $query->search(
			isset( $args['term'] ) ? ( $args['term'] ) : NULL,
			NULL,
			\IPS\Content\Search\Query::TERM_AND_TAGS + \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY,
			NULL
		);
		$returnObject['count'] = $returnObject['results']->count( TRUE );

		return $returnObject;
	}
}

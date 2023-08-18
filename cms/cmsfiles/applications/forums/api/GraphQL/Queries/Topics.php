<?php
/**
 * @brief		GraphQL: Topics query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Queries;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Topics query for GraphQL API
 */
class _Topics
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns a list of topics";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			'forums' => TypeRegistry::listOf( TypeRegistry::int() ),
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
					'name' => 'forums_fluid_order_by',
					'description' => 'Fields on which topics can be sorted',
					'values' => \IPS\forums\api\GraphQL\Types\TopicType::getOrderByOptions()
				]),
				'defaultValue' => NULL // will use default sort option
			],
			'orderDir' => [
				'type' => TypeRegistry::eNum([
					'name' => 'forums_fluid_order_dir',
					'description' => 'Directions in which items can be sorted',
					'values' => [ 'ASC', 'DESC' ]
				]),
				'defaultValue' => 'DESC'
			],
			'honorPinned' => [
				'type' => TypeRegistry::boolean(),
				'defaultValue' => true
			]
		);
	}

	/**
	 * Return the query return type
	 */
	public function type() 
	{
		return TypeRegistry::listOf( \IPS\forums\api\GraphQL\TypeRegistry::topic() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\forums\Topic
	 */
	public function resolve($val, $args, $context, $info)
	{
		\IPS\forums\Forum::loadIntoMemory('view', \IPS\Member::loggedIn() );

		$where = array( 'container' => array( array( 'forums_forums.password IS NULL' ) ) );
		$forumIDs = [];

		/* Are we filtering by forums? */
		if( isset( $args['forums'] ) && \count( $args['forums'] ) )
		{
			foreach( $args['forums'] as $id )
			{
				$forum = \IPS\forums\Forum::loadAndCheckPerms( $id );
				$forumIDs[] = $forum->id;
			}

			if( \count( $forumIDs ) )
			{
				$where['container'][] = array( \IPS\Db::i()->in( 'forums_forums.id', array_filter( $forumIDs ) ) );
			}
		}

		/* Get sorting */
		try 
		{
			if( $args['orderBy'] === NULL )
			{
				$orderBy = 'last_post';
			}
			else
			{
				$orderBy = \IPS\forums\Topic::$databaseColumnMap[ $args['orderBy'] ];
			}

			if( $args['orderBy'] === 'last_comment' )
			{
				$orderBy = \is_array( $orderBy ) ? array_pop( $orderBy ) : $orderBy;
			}
		}
		catch (\Exception $e)
		{
			$orderBy = 'last_post';
		}

		$sortBy = \IPS\forums\Topic::$databaseTable . '.' . \IPS\forums\Topic::$databasePrefix . "{$orderBy} {$args['orderDir']}";
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );

		/* Figure out pinned status */
		if ( $args['honorPinned'] )
		{
			$column = \IPS\forums\Topic::$databaseTable . '.' . \IPS\forums\Topic::$databasePrefix . \IPS\forums\Topic::$databaseColumnMap['pinned'];
			$sortBy = "{$column} DESC, {$sortBy}";
		}

		return \IPS\forums\Topic::getItemsWithPermission( $where, $sortBy, array( $offset, $limit ), 'read' );
	}
}

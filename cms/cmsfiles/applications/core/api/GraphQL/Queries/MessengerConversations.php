<?php
/**
 * @brief		GraphQL: Messenger conversation squery
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		25 Sep 2019
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
 * Messenger conversations query for GraphQL API
 */
class _MessengerConversations
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns a list of messenger conversations";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return array(
			'folder' => TypeRegistry::id(),
			'filter' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_messengerConversations_filter',
					'values' => ['MINE', 'NOT_MINE', 'READ', 'NOT_READ']
				]),
				'defaultValue' => NULL
			],
			'search' => TypeRegistry::string(),
			'searchIn' => [
				'type' => TypeRegistry::listOf( 
					TypeRegistry::eNum([
						'name' => 'core_messengerConversations_searchIn',
						'description' => 'Fields on which a conversation search can be applied',
						'values' => ['TOPIC', 'POST', 'SENDER', 'RECIPIENT']
					])
				)
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
					'name' => 'core_messengerConversations_orderBy',
					'description' => 'Fields on which conversations can be sorted',
					'values' => \IPS\core\api\GraphQL\Types\MessengerConversationType::getOrderByOptions()
				]),
				'defaultValue' => 'last_post_time'
			],
			'orderDir' => [
				'type' => TypeRegistry::eNum([
					'name' => 'core_messengerConversations_orderDir',
					'description' => 'Directions in which items can be sorted',
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
		return TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::messengerConversation() );
	}

	/**
	 * Resolves this query
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\core\Messenger\Conversation
	 */
	public function resolve($val, $args, $context, $info)
	{
		// Start to build the query to fetch messages
		$where = array( array( 'core_message_topic_user_map.map_user_id=? AND core_message_topic_user_map.map_user_active=1', \IPS\Member::loggedIn()->member_id ) );
		$orderBy	= 'mt_' . $args['orderBy'];
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );
		$iterator = NULL;

		// Filters
		if( !\is_null( $args['filter'] ) )
		{
			switch ($args['filter'] ) {
				case "MINE":
					$where[] = array( 'core_message_topic_user_map.map_is_starter=1' );    
				break;
				case "NOT_MINE":
					$where[] = array( 'core_message_topic_user_map.map_is_starter=0' );    
				break;
				case "READ":
					$where[] = array( 'core_message_topic_user_map.map_has_unread=0' );
				break;
				case "NOT_READ":
					$where[] = array( 'core_message_topic_user_map.map_has_unread=1' );
				break;
			}
		}

		// Folder
		if ( !\is_null( $args['folder'] ) )
		{
			$folderObj = \IPS\core\api\GraphQL\TypeRegistry::messengerFolder();
			$folders = $folderObj->getMemberFolders();

			if( isset( $folders[ $args['folder'] ] ) )
			{
				$where[] = array( 'core_message_topic_user_map.map_folder_id=?', $args['folder'] );
			}
		}
			
		// Search
		if( !\is_null( $args['search'] ) )
		{
			$subQuery = \IPS\Db::i()->select( 'map_topic_id', array( 'core_message_topic_user_map', 'core_message_topic_user_map' ), $where );
			$where = array( array( 'core_message_posts.msg_topic_id IN (?)', $subQuery ) );
			$query = array();
			$prefix = \IPS\Db::i()->prefix;
			
			if ( !\is_null( $args['searchIn'] ) )
			{
				if ( \in_array( 'TOPIC', $args['searchIn'] ) )
				{
					$query[] = \IPS\Content\Search\Mysql\Query::matchClause( "core_message_topics.mt_title", $args['search'], '+', FALSE );
				}
				if ( \in_array( 'POST', $args['searchIn'] ) )
				{
					$query[] = \IPS\Content\Search\Mysql\Query::matchClause( "core_message_posts.msg_post", $args['search'], '+', FALSE );
				}
				if ( \in_array( 'RECIPIENT', $args['searchIn'] ) )
				{
					$query[] = "core_message_posts.msg_topic_id IN ( SELECT sender_map.map_topic_id FROM {$prefix}core_message_topic_user_map AS sender_map WHERE sender_map.map_is_starter=1 AND sender_map.map_user_id IN ( SELECT member_id FROM {$prefix}core_members AS sm WHERE name LIKE '" . \IPS\Db::i()->escape_string( $args['search'] ) . "%' ) )";
				}
				if ( \in_array( 'SENDER', $args['searchIn'] ) )
				{
					$query[] = "core_message_posts.msg_topic_id IN ( SELECT receiver_map.map_topic_id FROM {$prefix}core_message_topic_user_map AS receiver_map WHERE receiver_map.map_is_starter=0 AND receiver_map.map_user_id IN ( SELECT member_id FROM {$prefix}core_members AS rm WHERE name LIKE '" . \IPS\Db::i()->escape_string( $args['search'] ) . "%') )";
				}
				
				if ( \count( $query ) )
				{
					$where[] = array( '(' . implode( ' OR ', $query ) . ')' );
				}
			}

			/* Get a count */
			try
			{
				$count	= \IPS\Db::i()->select( 'COUNT(*)', 'core_message_posts', $where, NULL, NULL, 'msg_topic_id' )
					->join(
						'core_message_topics',
						'core_message_posts.msg_topic_id=core_message_topics.mt_id'
					)
					->join(
						'core_message_topic_user_map',
						'core_message_topic_user_map.map_topic_id=core_message_topics.mt_id'
					)
					->first();
			}
			catch( \UnderflowException $e )
			{
				$count	= 0;
			}

			/* Performance: if count is 0, don't bother selecting ... it's a wasted query */
			if( $count )
			{
				/* Because of strict group by, we first need to select the ids, then grab those topics */
				$iterator	= \IPS\Db::i()->select(
						'core_message_posts.msg_topic_id',
						'core_message_posts',
						$where,
						$orderBy . ' ' . $args['orderDir'],
						array( $offset, $limit ),
						array( 'msg_topic_id', $orderBy )
					)->join(
						'core_message_topics',
						'core_message_posts.msg_topic_id=core_message_topics.mt_id'
					)->join(
						'core_message_topic_user_map',
						'core_message_topic_user_map.map_topic_id=core_message_topics.mt_id'
					);

				/* Get iterator */
				$iterator	= \IPS\Db::i()->select(
						'core_message_topic_user_map.*, core_message_topics.*',
						'core_message_topic_user_map',
						array( 'map_topic_id IN(' . implode( ',', iterator_to_array( $iterator ) ) . ')' ),
						$orderBy . ' ' . $args['orderDir']
					)->join(
						'core_message_topics',
						'core_message_topic_user_map.map_topic_id=core_message_topics.mt_id'
					);
			}
		}
		else
		{
			/* Get iterator */
			$iterator	= \IPS\Db::i()->select(
				'core_message_topic_user_map.*, core_message_topics.*',
				'core_message_topic_user_map',
				$where,
				$orderBy . ' ' . $args['orderDir'],
				array( $offset, $limit )
			)->join(
				'core_message_topics',
				'core_message_topic_user_map.map_topic_id=core_message_topics.mt_id'
			);
		}

		if( $iterator )
		{
			return new \IPS\Patterns\ActiveRecordIterator( $iterator, 'IPS\core\Messenger\Conversation' );
		}

		return array();
	}
}

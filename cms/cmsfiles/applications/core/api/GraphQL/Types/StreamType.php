<?php
/**
 * @brief		GraphQL: Stream Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * StreamType for GraphQL API
 */
class _StreamType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Stream',
			'description' => 'Activity streams',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Stream ID",
						'resolve' => function ($stream) {
							return $stream->id;
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Stream title",
						'resolve' => function ($stream) {
							if( !$stream->title ) {
								return \IPS\Member::loggedIn()->language()->addToStack( "stream_title_{$stream->id}" );
							}
							return $stream->title;
						}
					],
					'member' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'description' => "Stream owner, if applicable",
						'resolve' => function ($stream) {
							return ( $stream->member ) ? \IPS\Member::load( $stream->member ) : null;
						}
					],
					'isDefault' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this a default stream?",
						'resolve' => function ($stream) {
							return !( $stream->member );
						}
					],
					'items' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::contentSearchResult() ),
						'description' => "List of items in this stream",
						'args' => [
							'offset' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 0
							],
							'limit' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 25
							],
							'club' => TypeRegistry::int(),
							'unread' => TypeRegistry::boolean(),

						],
						'resolve' => function ($stream, $args, $context) {
							return self::items( $stream, $args, $context );
						}
					],
				];
			}
		];

		parent::__construct($config);
	}

	/**
	 * Resolve items
	 *
	 * @param 	\IPS\core\Stream
	 * @param 	array 	Arguments passed from resolver
	 * @return	array
	 */
	protected static function items( $stream, $args, $context )
	{
		/* Clubs */
		if( isset( $args['club'] ) )
		{
			$stream->clubs = $args['club'];
		}

		/* Unread */
		if( isset( $args['unread'] ) )
		{
			if( ( $args['unread'] === TRUE && $stream->read !== 'unread' ) || ( $args['unread'] === FALSE && $stream->read === 'unread' ) )
			{
				$stream->read = ( $args['unread'] ) ? 'unread' : 'all';
			}
		}

		/* Build the query */
		$query = $stream->query( \IPS\Member::loggedIn() );

		// Get page
		// We don't know the count at this stage, so figure out the page number from
		// our offset/limit
		$page = 1;
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );

		if( $offset > 0 )
		{
			$page = floor( $offset / $limit ) + 1;
		}

		$query->setLimit( $limit )->setPage( $page );

		/* Get the results */
		$results = $query->search( NULL, NULL, ( $stream->include_comments ? \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY + \IPS\Content\Search\Query::TERM_OR_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS ) );
		
		/* Load data we need like the authors, etc */
		$results->init();

		return $results;
	}
}

<?php
/**
 * @brief		GraphQL: Calender Events query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		26 January 2023
 * @version        SVN_VERSION_NUMBER
 */

namespace IPS\calendar\api\GraphQL\Queries;

use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER[ 'SERVER_PROTOCOL' ] ) ? $_SERVER[ 'SERVER_PROTOCOL' ] : 'HTTP/1.0' ).' 403 Forbidden' );
	exit;
}

/**
 * Calendar Events query for GraphQL API
 */
class _Events
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Returns a list of events";

	/*
	 * Query arguments
	 */
	public function args(): array
	{
		return [
		'calendar' => TypeRegistry::listOf( TypeRegistry::int() ),
		'offset' => [
			'type' => TypeRegistry::int(),
			'defaultValue' => 0,
		],
		'limit' => [
			'type' => TypeRegistry::int(),
			'defaultValue' => 25,
		],
		'orderBy' => [
			'type' => TypeRegistry::eNum( [
									  'name' => 'events_order_by',
									  'description' => 'Fields on which events can be sorted',
									  'values' => \IPS\calendar\api\GraphQL\Types\EventType::getOrderByOptions(),
									  ] ),
			'defaultValue' => NULL, // will use default sort option
		],
		'orderDir' => [
			'type' => TypeRegistry::eNum( [
									  'name' => 'events_order_dir',
									  'description' => 'Directions in which items can be sorted',
									  'values' => [ 'ASC', 'DESC' ],
									  ] ),
			'defaultValue' => 'DESC',
		],
		'honorPinned' => [
			'type' => TypeRegistry::boolean(),
			'defaultValue' => TRUE,
		],
		];
	}

	/**
	 * Return the query return type
	 */
	public function type()
	{
		return TypeRegistry::listOf( \IPS\calendar\api\GraphQL\TypeRegistry::event() );
	}

	/**
	 * Resolves this query
	 *
	 * @param mixed    Value passed into this resolver
	 * @param array    Arguments
	 * @param array    Context values
	 * @return    \IPS\calendar\Event
	 */
	public function resolve( $val, $args, $context, $info )
	{
		$where = [];
		\IPS\calendar\Calendar::loadIntoMemory( 'view', \IPS\Member::loggedIn() );

		$calendarIDs = [];

		/* Are we filtering by calendards? */
		if( isset( $args[ 'calendars' ] ) && \count( $args[ 'calendars' ] ) )
		{
			foreach( $args[ 'calendars' ] as $id )
			{
				$calendar = \IPS\calendar\Calendar::loadAndCheckPerms( $id );
				$calendarIDs[] = $calendar->id;
			}

			if( \count( $calendarIDs ) )
			{
				$where[ 'container' ][] = [ \IPS\Db::i()->in( 'calendar_events.event_calendar_id', array_filter( $calendarIDs ) ) ];
			}
		}

		/* Get sorting */

		if( $args[ 'orderBy' ] === NULL )
		{
			$orderBy = 'saved';
		}
		else if( \in_array( $args[ 'orderBy' ], \IPS\calendar\api\GraphQL\Types\EventType::getOrderByOptions() ) )
		{
			$orderBy = $args[ 'orderBy' ];
		}

		$sortBy = \IPS\calendar\Event::$databaseTable.'.'.\IPS\calendar\Event::$databasePrefix."{$orderBy} {$args['orderDir']}";
		$offset = max( $args[ 'offset' ], 0 );
		$limit = min( $args[ 'limit' ], 50 );

		return \IPS\calendar\Event::getItemsWithPermission( $where, $sortBy, [ $offset, $limit ], 'read' );
	}
}

<?php
/**
 * @brief		GraphQL: Mark a notification read mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		6 Nov 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Mark notification read mutation for GraphQL API
 */
class _MarkNotificationRead
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Mark a notification as read";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::int()
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\core\Api\GraphQL\TypeRegistry::notification();
	}

	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	array|NULL
	 */
	public function resolve($val, $args)
	{
		if( !\IPS\Member::loggedIn()->member_id )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NOT_LOGGED_IN', 'GQL/0003/1', 403 );
		}

		$where = array();
		$where[] = array( "notification_app IN('" . implode( "','", array_keys( \IPS\Application::enabledApplications() ) ) . "')" );
		$where[] = array( "member = ?", \IPS\Member::loggedIn()->member_id );

		if( isset( $args['id'] ) && $args['id'] !== NULL )
		{
			$where[] = array( "id = ?", \intval( $args['id'] ) );
		}

		try 
		{
			$row = \IPS\Db::i()->select( '*', 'core_notifications', $where )->first();
			$notification = \IPS\Notification\Api::constructFromData( $row );
		}
		catch ( \UnderflowException $e )
		{
			if( isset( $args['id'] ) && $args['id'] !== NULL )
			{
				// Only throw an error if we were trying to work on a specific notification
				throw new \IPS\Api\GraphQL\SafeException( 'INVALID_NOTIFICATION', 'GQL/0003/2', 403 );
			}
		}

		\IPS\Db::i()->update( 'core_notifications', array( 'read_time' => time() ), $where );
		\IPS\Member::loggedIn()->recountNotifications();		

		if( $notification )
		{
			return array( 'notification' => $notification, 'data' => $notification->getData() );
		}

		return NULL;
	}
}

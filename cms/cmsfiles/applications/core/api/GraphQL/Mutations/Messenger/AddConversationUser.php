<?php
/**
 * @brief		GraphQL: Add user to PM conversation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		23 Oct 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Mutations\Messenger;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Add user to conversation mutation for GraphQL API
 */
class _AddConversationUser
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Add a user to a PM conversation";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'memberId' => TypeRegistry::nonNull( TypeRegistry::id() ),
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\core\Api\GraphQL\TypeRegistry::messengerConversation();
	}

	/**
	 * Resolves this mutation
	 *
	 * @param 	mixed 	Value passed into this resolver
	 * @param 	array 	Arguments
	 * @param 	array 	Context values
	 * @return	\IPS\forums\Forum
	 */
	public function resolve($val, $args)
	{
		if( !\IPS\Member::loggedIn()->member_id )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NOT_LOGGED_IN', 'GQL/0003/1', 403 );
		}

		try
		{
			$conversation = \IPS\core\Messenger\Conversation::loadAndCheckPerms( $args['id'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_MESSAGE', '1F294/2_graphql', 400 );
		}

		// Fetch our new member
		$member = \IPS\Member::load( $args['memberId'] );
		
		if( !$member->member_id ){
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_MEMBER', '1F294/2_graphql', 403 );
		}

		// Check member limit
		if ( \IPS\Member::loggedIn()->group['g_max_mass_pm'] !== -1 AND $conversation->to_count + 1 > \IPS\Member::loggedIn()->group['g_max_mass_pm'] )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'TOO_MANY_RECIPIENTS', '1F294/2', 400 );
		}

		$maps = $conversation->maps( TRUE );

		// Authorize the member
		if ( array_key_exists( $member->member_id, $maps ) and !$maps[ $member->member_id ]['map_user_active'] AND !$maps[ $member->member_id ]['map_user_banned'] )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'MEMBER_LEFT', '1F294/2', 403 );
		}
			
		if ( !$this->memberCanReceiveNewMessage( $member ) )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'BAD_RECIPIENT', '1F294/2', 403 );
		}

		//echo 'here';
			
		$conversation->authorize( $member );
		$conversation->maps(TRUE); // Need to rebuild participant maps here to reflect this change

		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'private_message_added', $conversation, array( $conversation, \IPS\Member::loggedIn() ) );
		$notification->recipients->attach( $member );
		$notification->send();

		return $conversation;
	}

	/**
	 * Can the member recieve new messages?
	 *
	 * @param 	\IPS\Member 	The member to check
	 * @return	boolean
	 */
	public function memberCanReceiveNewMessage( $member )
	{
		if ( $member->members_disable_pm )
		{
			return FALSE;
		}
		
		/* Group can not use messenger */
		if ( !$member->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) )
		{
			return FALSE;
		}
		
		/* Inbox is full */
		if ( ( $member->group['g_max_messages'] > 0 AND $member->msg_count_total >= $member->group['g_max_messages'] ) and !\IPS\Member::loggedIn()->group['gbw_pm_override_inbox_full'] )
		{
			return FALSE;
		}
		
		/* Is being ignored */
		if ( $member->isIgnoring( \IPS\Member::loggedIn(), 'messages' ) )
		{
			return FALSE;
		}

		return TRUE;
	}
}

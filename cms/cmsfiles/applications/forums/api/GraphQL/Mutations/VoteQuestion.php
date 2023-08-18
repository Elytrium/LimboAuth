<?php
/**
 * @brief		GraphQL: Vote on a question
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		02 Jan 2019
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Vote on a question mutation for GraphQL API
 */
class _VoteQuestion
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Vote on a question";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'vote' => TypeRegistry::nonNull( \IPS\forums\api\GraphQL\TypeRegistry::vote() ),
		];
	}

	/**
	 * Return the mutation return type
	 */
	public function type() 
	{
		return \IPS\forums\api\GraphQL\TypeRegistry::topic();
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
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( $args['id'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', 'GQL/0008/1', 400 );
		}

		if( !$topic->can('read') )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_ID', 'GQL/0008/2', 403 );
		}

		if( !$topic->isQuestion() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NON_QUESTION', 'GQL/0008/3', 403 );
		}

		if( !$topic->canVote() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'CANNOT_VOTE', 'GQL/0008/4', 403 );
		}

		$rating = $args['vote'] == 'UP' ? 1 : -1;

		// If we have an existing vote, undo that
		$ratings = $topic->votes();
		if ( isset( $ratings[ \IPS\Member::loggedIn()->member_id ] ) )
		{
			\IPS\Db::i()->delete( 'forums_question_ratings', array( 'topic=? AND member=?', $topic->tid, \IPS\Member::loggedIn()->member_id ) );
		}
		
		\IPS\Db::i()->insert( 'forums_question_ratings', array(
			'topic'		=> $topic->tid,
			'forum'		=> $topic->forum_id,
			'member'	=> \IPS\Member::loggedIn()->member_id,
			'rating'	=> $rating,
			'date'		=> time()
		), TRUE );
		
		/* Rebuild count */
		$topic->question_rating = \IPS\Db::i()->select( 'SUM(rating)', 'forums_question_ratings', array( 'topic=?', $topic->tid ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$topic->save();

		return $topic;
	}
}

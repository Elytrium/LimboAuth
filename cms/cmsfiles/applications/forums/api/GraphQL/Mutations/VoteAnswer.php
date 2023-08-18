<?php
/**
 * @brief		GraphQL: Vote on an answer
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
 * Vote on an answer mutation for GraphQL API
 */
class _VoteAnswer
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Vote on an answer";

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
		return \IPS\forums\api\GraphQL\TypeRegistry::post();
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
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( $args['id'] );
			$topic = $post->item();
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', 'GQL/0009/1', 403 );
		}

		if( !$topic->can('read') )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_ID', 'GQL/0009/2', 403 );
		}

		if( !$topic->isQuestion() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NON_QUESTION', 'GQL/0009/3', 403 );
		}

		if( !$post->canVote() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'CANNOT_VOTE', 'GQL/0009/4', 403 );
		}

		$rating = $args['vote'] == 'UP' ? 1 : -1;

		/* Have we already rated ? */
		try
		{
			\IPS\Db::i()->delete( 'forums_answer_ratings', array( 'topic=? AND post=? AND `member`=?', $topic->tid, $post->pid, \IPS\Member::loggedIn()->member_id ) );
		}
		catch ( \UnderflowException $e ){}
		
		\IPS\Db::i()->insert( 'forums_answer_ratings', array(
			'post'		=> $post->pid,
			'topic'		=> $topic->tid,
			'member'	=> \IPS\Member::loggedIn()->member_id,
			'rating'	=> $rating,
			'date'		=> time()
		), TRUE );

		$post->post_field_int = (int) \IPS\Db::i()->select( 'SUM(rating)', 'forums_answer_ratings', array( 'post=?', $post->pid ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$post->save();

		return $post;
	}
}

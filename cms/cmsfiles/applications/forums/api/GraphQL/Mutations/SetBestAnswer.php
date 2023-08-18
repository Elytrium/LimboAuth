<?php
/**
 * @brief		GraphQL: Set a post as best answer
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		08 Jan 2019
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
 * Set a post as best answer mutation for GraphQL API
 */
class _SetBestAnswer
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Set a post as best answer";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::nonNull( TypeRegistry::id() )
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
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', 'GQL/0010/1', 403 );
		}

		if( !$topic->can('read') )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_ID', 'GQL/0010/2', 403 );
		}

		if( !$topic->isQuestion() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NON_QUESTION', 'GQL/0010/3', 403 );
		}

		if( !$topic->canSetBestAnswer() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', 'GQL/0010/4', 403 );
		}

		// Do we have an existing best answer
		if ( $topic->topic_answered_pid )
		{
			try
			{
				$oldBestAnswer = \IPS\forums\Topic\Post::load( $topic->topic_answered_pid );
				$oldBestAnswer->post_bwoptions['best_answer'] = FALSE;
				$oldBestAnswer->save();
			}
			catch ( \Exception $e ) {}
		}

		$post->post_bwoptions['best_answer'] = TRUE;
		$post->save();
		
		$topic->topic_answered_pid = $post->pid;
		$topic->save();

		return $post;
	}
}

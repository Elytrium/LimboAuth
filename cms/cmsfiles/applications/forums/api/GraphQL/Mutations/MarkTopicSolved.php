<?php
/**
 * @brief		GraphQL: Mark a topic solved mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		1 Jul 2020
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
 * Mark topic solved mutation for GraphQL API
 */
class _MarkTopicSolved extends \IPS\Node\Api\GraphQL\NodeMutator
{
	/*
	 * @brief 	Query description
	 */
	public static $description = "Mark a topic solved";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'id' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'answer' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'solved' => [
				'type' => TypeRegistry::boolean(),
				'defaultValue' => TRUE
			]
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
	 * @return	\IPS\forums\Topic
	 */
	public function resolve($val, $args)
	{
		try 
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \intval( $args['id'] ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', 'GQL', 403 );
		}

		try 
		{
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( \intval( $args['answer'] ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', 'GQL', 403 );
		}

		/* This is "solved" mode */
		if ( !$topic->canSolve() )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', 'GQL', 403 );
		}

		
		if( $args['solved'] )
		{
			$topic->toggleSolveComment( $post->pid, TRUE );
		}
		else if ( $topic->mapped('solved_comment_id') )
		{
			$topic->toggleSolveComment( $post->pid, FALSE );
		}

		\IPS\Db::i()->insert( 'core_moderator_logs', array( 
			'member_id'			=> \IPS\Member::loggedIn()->member_id,
			'member_name'		=> \IPS\Member::loggedIn()->name,
			'ctime'				=> time(),
			'note'				=> json_encode( array( $post->pid => $args['solved'] ) ),
			'ip_address'		=> \IPS\Request::i()->ipAddress(),
			'appcomponent'		=> 'forums',
			'module'			=> 'forums',
			'controller'		=> 'topic',
			'do'				=> $args['solved'] ? 'solve' : 'unsolve',
			'lang_key'			=> $args['solved'] ? 'modlog__best_answer_set' : 'modlog__best_answer_unset',
			'class'				=> "IPS\forums\Topic",
			'item_id'			=> $args['id'],
		)	);

		return $topic;
	}
}

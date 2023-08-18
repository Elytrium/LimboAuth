<?php
/**
 * @brief		GraphQL: Reply to topic mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
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
 * Reply to topic mutation for GraphQL API
 */
class _ReplyTopic extends \IPS\Content\Api\GraphQL\CommentMutator
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic\Post';

	/*
	 * @brief 	Query description
	 */
	public static $description = "Create a new post";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'topicID'		=> TypeRegistry::nonNull( TypeRegistry::id() ),
			'content'		=> TypeRegistry::nonNull( TypeRegistry::string() ),
			'replyingTo'	=> TypeRegistry::id(),
			'postKey'		=> TypeRegistry::string()
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
	public function resolve($val, $args, $context, $info)
	{
		/* Get topic */
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( $args['topicID'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_TOPIC', '1F295/1_graphl', 403 );
		}
		
		/* Get author */
		if ( !$topic->canComment( \IPS\Member::loggedIn() ) )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '2F294/A_graphl', 403 );
		}
		
		/* Check we have a post */
		if ( !$args['content'] )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1F295/3_graphl', 403 );
		}

		$originalPost = NULL;

		if( isset( $args['replyingTo'] ) )
		{
			try
			{
				$originalPost = \IPS\forums\Topic\Post::loadAndCheckPerms( $args['replyingTo'] );
			}
			catch ( \OutOfRangeException $e )
			{
				// Just ignore it
			}			
		}
		
		/* Do it */
		return $this->_createComment( $args, $topic, $args['postKey'] ?? NULL, $originalPost );
	}
}

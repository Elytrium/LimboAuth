<?php
/**
 * @brief		GraphQL: Create topic mutation
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
 * Create topic mutation for GraphQL API
 */
class _CreateTopic extends \IPS\Content\Api\GraphQL\ItemMutator
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic';

	/*
	 * @brief 	Query description
	 */
	public static $description = "Create a new topic";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'forumID' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'title' => TypeRegistry::nonNull( TypeRegistry::string() ),
			'content' => TypeRegistry::nonNull( TypeRegistry::string() ),
			'tags' => TypeRegistry::listOf( TypeRegistry::string() ),
			'state' => TypeRegistry::itemState(),
			'postKey' => TypeRegistry::string()
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
	 * @return	\IPS\forums\Topic
	 */
	public function resolve($val, $args, $context, $info)
	{
		/* Get forum */
		try
		{
			$forum = \IPS\forums\Forum::loadAndCheckPerms( $args['forumID'] );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_FORUM', '1F294/2_graphl', 400 );
		}
		
		/* Check permission */
		if ( !$forum->can( 'add', \IPS\Member::loggedIn() ) )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_PERMISSION', '2F294/9_graphl', 403 );
		}
		
		/* Check we have a title and a post */
		if ( !$args['title'] )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_TITLE', '1F294/5_graphl', 400 );
		}
		if ( !$args['content'] )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POST', '1F294/4_graphl', 400 );
		}
		
		
		$item = $this->_create( $args, $forum, $args['postKey'] ?? NULL );

		return $item;
	}
}

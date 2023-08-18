<?php
/**
 * @brief		GraphQL: Vote in poll mutation
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		5 Dec 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Mutations;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Vote in poll mutation for GraphQL API
 */
class _VoteInPoll extends \IPS\Poll\Api\GraphQL\PollMutator
{
	/**
	 * Class
	 */
	protected $class = 'IPS\forums\Topic';

	/*
	 * @brief 	Query description
	 */
	public static $description = "Vote in a poll in a topic";

	/*
	 * Mutation arguments
	 */
	public function args(): array
	{
		return [
			'itemID' => TypeRegistry::nonNull( TypeRegistry::id() ),
			'poll' => TypeRegistry::listOf( 
				new InputObjectType([
					'name' => 'core_PollQuestionInput',
					'fields' => [
						'id' => TypeRegistry::id(),
						'choices' => TypeRegistry::listOf( TypeRegistry::int() )
					]
				])
			)
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
		/* Get topic */
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( $args['itemID'] );
			$poll = $topic->getPoll();
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_TOPIC', 'GQL/0006/1', 403 );
		}
		
		if( !$topic->can('read') || $poll === NULL )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_POLL', 'GQL/0006/2', 403 );
		}

		$this->_vote( $poll, $args['poll'] );

		return $topic;
	}
}

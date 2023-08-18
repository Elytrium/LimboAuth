<?php
/**
 * @brief		GraphQL: Forums mutations
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\Types;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Forums mutationss GraphQL API
 */
abstract class _Mutation
{
	/**
	 * Get the supported query types in this app
	 *
	 * @return	array
	 */
	public static function mutations()
	{
		return [
			'createTopic' => new \IPS\forums\api\GraphQL\Mutations\CreateTopic(),
			'replyTopic' => new \IPS\forums\api\GraphQL\Mutations\ReplyTopic(),
			'postReaction' => new \IPS\forums\api\GraphQL\Mutations\PostReaction(),
			'markForumRead' => new \IPS\forums\api\GraphQL\Mutations\MarkForumRead(),
			'markTopicRead' => new \IPS\forums\api\GraphQL\Mutations\MarkTopicRead(),
			'markTopicSolved' => new \IPS\forums\api\GraphQL\Mutations\MarkTopicSolved(),
			'voteInPoll' => new \IPS\forums\api\GraphQL\Mutations\VoteInPoll(),
			'voteQuestion' => new \IPS\forums\api\GraphQL\Mutations\VoteQuestion(),
			'voteAnswer' => new \IPS\forums\api\GraphQL\Mutations\VoteAnswer(),
			'setBestAnswer' => new \IPS\forums\api\GraphQL\Mutations\SetBestAnswer(),
			'reportPost' => new \IPS\forums\api\GraphQL\Mutations\ReportPost(),
			'revokePostReport' => new \IPS\forums\api\GraphQL\Mutations\RevokePostReport(),
		];
	}
}

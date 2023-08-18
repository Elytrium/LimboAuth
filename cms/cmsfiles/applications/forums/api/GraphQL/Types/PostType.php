<?php
/**
 * @brief		GraphQL: Post Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PostType for GraphQL API
 */
class _PostType extends \IPS\Content\Api\GraphQL\CommentType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $commentClass	= '\IPS\forums\Topic\Post';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'forums_Post';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A post';

	/**
	 * Get the item type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	public static function getItemType()
	{
		return \IPS\forums\api\GraphQL\TypeRegistry::topic();
	}

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	public function fields()
	{
		$defaultFields = parent::fields();
		$postFields = array(
			'topic' => [
				'type' => \IPS\forums\api\GraphQL\TypeRegistry::topic(),
				'resolve' => function ($post) {
					return $post->item();
				}
			],
			/* Q&A STUFF */
			'isQuestion' => [
				'type' => TypeRegistry::boolean(),
				'description' => "Boolean indicating whether this post is a question, i.e. the first post in a question topic",
				'resolve' => function ($post) {
					return $post->item()->isQuestion() && $post->new_topic;
				}
			],
			'answerVotes' => [
				'type' => TypeRegistry::int(),
				'resolve' => function ($post) {
					return $post->post_field_int;
				}
			],
			'isBestAnswer' => [
				'type' => TypeRegistry::boolean(),
				'description' => "Whether this post is the best answer in a question",
				'resolve' => function ($post) {
					return $post->post_bwoptions['best_answer'];
				}
			],
			'canVoteUp' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($post) {
					if( !$post->item()->isQuestion() )
					{
						return NULL;
					}

					return $post->canVote(1);
				}
			],
			'canVoteDown' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($post) {
					if( !$post->item()->isQuestion() )
					{
						return NULL;
					}

					return $post->canVote(-1) && \IPS\Settings::i()->forums_questions_downvote;
				}
			],
			'vote' => [
				'type' => \IPS\forums\api\GraphQL\TypeRegistry::vote(),
				'resolve' => function ($post) {
					$ratings = $post->item()->answerVotes( \IPS\Member::loggedIn() );
					
					if( !$post->item()->isQuestion() || !isset( $ratings[ $post->pid ] ) )
					{
						return NULL;
					}

					return $ratings[ $post->pid ] === -1 ? 'DOWN' : 'UP';
				}
			]
		);

		// Remove duplicated fields
		unset( $defaultFields['item'] );

		return array_merge( $defaultFields, $postFields );
	}

	/**
	 * Return the definite article, but without the item type
	 *
	 * @return	string
	 */
	public static function definiteArticleNoItem($post, $options = array())
	{
		$type = $post->item()->isQuestion() ? 'answer_lc' : 'post_lc';
		return \IPS\Member::loggedIn()->language()->addToStack($type, FALSE, $options);
	}
}

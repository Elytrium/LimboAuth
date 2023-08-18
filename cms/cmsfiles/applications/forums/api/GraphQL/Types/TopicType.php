<?php
/**
 * @brief		GraphQL: Topic Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		10 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\_TypeRegistry;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * TopicType for GraphQL API
 */
class _TopicType extends \IPS\Content\Api\GraphQL\ItemType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $itemClass	= '\IPS\forums\Topic';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'forums_Topic';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A topic';

	/*
	 * @brief 	Follow data passed in to FollowType resolver
	 */
	protected static $followData = array('app' => 'forums', 'area' => 'topic');

	/**
	 * Return the fields available in this type
	 *
	 * @return	array
	 */
	public function fields()
	{
		// Extend our fields with topic-specific stuff
		$defaultFields = parent::fields();
		$topicFields = array(
			'forum' => [
				'type' => \IPS\forums\api\GraphQL\TypeRegistry::forum(),
				'resolve' => function ($item) {
					return $item->container();
				}
			],
			'isArchived' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return (bool) $topic->isArchived();
				}
			],
			'isHot' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					foreach( $topic->stats(FALSE) as $k => $v )
					{
						if( \in_array( $k, $topic->hotStats ) )
						{
							return TRUE;
						}
					}
					
					return FALSE;
				}
			],

			/* SOLVED STUFF */
			'isSolved' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return (bool) $topic->isSolved();
				}
			],
			'solvedId' => [
				'type' => TypeRegistry::int(),
				'resolve' => function ($topic) {
					if( $topic->isSolved() ){
						return $topic->mapped('solved_comment_id');
					}
				}
			],
			'canMarkSolved' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return $topic->canSolve();
				}
			],
			'solvedComment' => [
				'type' => \IPS\forums\api\GraphQL\TypeRegistry::post(),
				'resolve' => function ($topic) {
					if( !$topic->isSolved() )
					{
						return NULL;
					}
					
					try 
					{
						$solved = \IPS\forums\Topic\Post::load( $topic->mapped('solved_comment_id') );
						return $solved;
					}
					catch (\Exception $err) 
					{
						return NULL;
					}
				}
			],

			/* Q&A STUFF */
			'isQuestion' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return $topic->isQuestion();
				}
			],
			'questionVotes' => [
				'type' => TypeRegistry::int(),
				'resolve' => function ($topic) {
					if( !$topic->isQuestion() )
					{
						return NULL;
					}

					return \intval( $topic->question_rating );
				}
			],
			'canVoteUp' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					if( !$topic->isQuestion() )
					{
						return NULL;
					}

					return $topic->canVote(1);
				}
			],
			'canVoteDown' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					if( !$topic->isQuestion() )
					{
						return NULL;
					}

					return $topic->canVote(-1) && \IPS\Settings::i()->forums_questions_downvote;
				}
			],
			'vote' => [
				'type' => \IPS\forums\api\GraphQL\TypeRegistry::vote(),
				'resolve' => function ($topic) {
					$topicVotes	= $topic->votes();

					if( !$topic->isQuestion() || !isset( $topicVotes[ \IPS\Member::loggedIn()->member_id ] ) )
					{
						return NULL;
					}

					return $topicVotes[ \IPS\Member::loggedIn()->member_id ] === -1 ? 'DOWN' : 'UP';
				}
			],
			'hasBestAnswer' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return !!$topic->topic_answered_pid;
				}
			],
			'bestAnswerID' => [
				'type' => TypeRegistry::id(),
				'resolve' => function ($topic) {
					if( !$topic->isQuestion() )
					{
						return NULL;
					}

					return $topic->topic_answered_pid;
				}
			],
			'canSetBestAnswer' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($topic) {
					return $topic->canSetBestAnswer();
				}
			]
		);

		// Questions get their comment count reduced by one in the model, but for the API
		// we'll reverse that change to keep topics & questions consistent.
		$topicFields['postCount'] = $defaultFields['commentCount'];
		$topicFields['postCount']['resolve'] = function ($topic, $args) {
			if( $args['includeHidden'] )
			{
				return $topic->commentCount() + ( $topic->isQuestion() ? 1 : 0 );
			}

			return $topic->mapped('num_comments');
		};		

		// Duplicate fields that have different names in topics
		//$topicFields['forum'] = $defaultFields['container'];
		$topicFields['posts'] = $defaultFields['comments'];
		$topicFields['lastPostAuthor'] = $defaultFields['lastCommentAuthor'];
		$topicFields['lastPostDate'] = $defaultFields['lastCommentDate'];

		// Remove duplicated fields
		unset( $defaultFields['container'] );
		unset( $defaultFields['comments'] );
		unset( $defaultFields['commentCount'] );
		unset( $defaultFields['lastCommentAuthor'] );
		unset( $defaultFields['lastCommentDate'] );

		return array_merge( $defaultFields, $topicFields );
	}

	public static function args()
	{
		return array_merge( parent::args(), array(
			'password' => [
				'type' => TypeRegistry::string()
			]
		));
	}

	/**
	 * Return item permission fields.
	 * Here we adjust the resolver for the commentInformation field to check whether this is
	 * a poll-only topic.
	 *
	 * @return	string|null
	 */
	public static function getItemPermissionFields()
	{
		$defaultFields = parent::getItemPermissionFields();
		$existingResolver = $defaultFields['commentInformation']['resolve'];

		$defaultFields['commentInformation']['resolve'] = function ($topic, $args, $context) use ( $existingResolver ) {
			if( $topic->canComment( \IPS\Member::loggedIn(), FALSE ) && ( $topic->getPoll() and $topic->getPoll()->poll_only ) )
			{
				return 'topic_poll_can_comment';
			}
			else
			{
				return $existingResolver($topic, $args, $context);
			}
		};

		return $defaultFields;
	}

	/**
	 * Return the available sorting options
	 *
	 * @return	array
	 */
	public static function getOrderByOptions()
	{
		$defaultArgs = parent::getOrderByOptions();
		return array_merge( $defaultArgs, array('last_comment', 'num_comments', 'views', 'author_name', 'last_comment_name', 'date', 'votes') );
	}

	/**
	 * Get the comment type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	protected static function getCommentType()
	{
		return \IPS\forums\api\GraphQL\TypeRegistry::post();
	}

	/**
	 * Resolve the comments field - overridden from ItemType
	 * If this is a question and order isn't date, then force order by votes
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function comments($topic, $args)
	{
		if( $topic->isQuestion() && $args['orderBy'] !== 'date' )
		{
			if( $topic->isArchived() )
			{
				$args['orderBy'] = "archive_is_first desc, archive_bwoptions";
				$args['orderDir'] = "DESC";
			}
			else
			{
				$args['orderBy'] = "new_topic DESC, post_bwoptions DESC, post_field_int DESC, post_date";
				$args['orderDir'] = "ASC";
			}
		}
		elseif( !$topic->isQuestion() && $args['orderBy'] === 'votes' )
		{
			$args['orderBy'] = 'date'; // Only Q&A can order by votes
		}

		return parent::comments($topic, $args);
	}
}

<?php
/**
 * @brief		GraphQL: Poll Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		30 Nov 2018
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PollType for GraphQL API
 */
class _PollType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Poll',
			'description' => 'Polls',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Returns the poll ID",
						'resolve' => function ($poll) {
							return $poll->pid;
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "The poll question",
						'resolve' => function ($poll) {
							return $poll->poll_question;
						}
					],
					'votes' => [
						'type' => TypeRegistry::int(),
						'description' => "Number of votes on this poll",
						'resolve' => function ($poll) {
							return $poll->votes;
						}
					],
					'questions' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::pollQuestion() ),
						'description' => "The poll questions",
						'resolve' => function ($poll) {
							return static::getQuestions($poll);
						}
					],
					'closeTimestamp' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of when this poll will close (NULL if no close date set)",
						'resolve' => function ($poll) {
							if( $poll->poll_close_date instanceof \IPS\DateTime )
							{
								return $poll->poll_close_date->getTimestamp();
							}

							return NULL;
						}
					],
					'isClosed' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether this poll is closed to further votes",
						'resolve' => function ($poll) {
							return $poll->poll_closed;
						}
					],
					'isPublic' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether this is a public poll (i.e. voter names are public)",
						'resolve' => function ($poll) {
							return $poll->poll_view_voters;
						}
					],
					'hasVoted' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Has the user voted in this poll?",
						'resolve' => function ($poll) {
							return $poll->getVote() !== NULL;
						}
					],
					'canVote' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether the user can vote in this poll",
						'resolve' => function ($poll) {
							return $poll->canVote();
						}
					],
					'canViewResults' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether the user can view the results of this poll",
						'resolve' => function ($poll) {
							return $poll->canViewResults();
						}
					],
					'canViewVoters' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether the user can view who voted, based on permissions",
						'resolve' => function ($poll) {
							return $poll->canSeeVoters();
						}
					],
					'canClose' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether the user can close this poll",
						'resolve' => function ($poll) {
							return $poll->canClose();
						}
					]
				];
			}
		];

		parent::__construct($config);
	}

	/**
	 * Resolve poll questions field
	 *
	 * @param 	\IPS\Poll
	 * @return	array
	 */
	protected static function getQuestions ($poll) {
		$questions = array();

		foreach ( $poll->choices as $idx => $choice )
		{
			// We provide the index as a ID since questions don't have a real ID. Providing an ID
			// allows GraphQL (well, Apollo) to optimize queries.
			// We need to pass `poll` through here since there's no way to obtain a reference to the poll
			// from the individual question/choices
			$questions[ $idx ] = array(
				'id' => 'p' . $poll->pid . '-q' . $idx, 
				'data' => $choice, 
				'poll' => $poll,
				'voters' => array()
			);
		}

		if( $poll->canSeeVoters() )
		{
			// Get the voters for each choice in advance here, but we won't load the member 
			// until questions -> choices -> voters is resolved in PollQuestionType
			$voters = array();
			$query = \IPS\Db::i()->select( '*', 'core_voters', array( 'poll=?', $poll->pid ) );

			foreach( $query as $voter )
			{
				if( $voter['member_choices'] !== NULL )
				{
					$voteData = json_decode( $voter['member_choices'] );
					foreach( $voteData as $questionIdx => $answer )
					{
						if( \is_array( $answer ) )
						{
							foreach( $answer as $_answer )
							{
								$questions[ $questionIdx ]['voters'][ \intval( $_answer ) ][] = $voter['member_id'];
							}		
						}
						else
						{
							$questions[ $questionIdx ]['voters'][ \intval( $answer ) ][] = $voter['member_id'];
						}
					}
				}
			}
		}

		return $questions;
	}
}
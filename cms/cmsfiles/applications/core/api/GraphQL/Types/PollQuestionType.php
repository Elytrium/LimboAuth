<?php
/**
 * @brief		GraphQL: Poll Question Type
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
 * PollQuestionType for GraphQL API
 */
class _PollQuestionType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_PollQuestion',
			'description' => 'A poll question',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Returns the poll question ID",
						'resolve' => function ($question) {
							return $question['id'];
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the question title",
						'resolve' => function ($question) {
							return $question['data']['question'];
						}
					],
					'isMultiChoice' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this question multiple-choice?",
						'resolve' => function ($question) {
							return $question['data']['multi'];
						}
					],
					'votes' => [
						'type' => TypeRegistry::int(),
						'description' => "How many users voted on this question",
						'resolve' => function ($question) {
							return isset( $question['data']['votes'] ) ? array_sum( $question['data']['votes'] ) : 0;
						}
					],
					'choices' => [
						'type' => TypeRegistry::listOf( new ObjectType([
							'name' => 'core_PollChoice',
							'description' => "A poll choice",
							'fields' => [
								'id' => [
									'type' => TypeRegistry::id(),
									'resolve' => function ($choice) {
										return $choice['id'];
									}
								],
								'title' => [
									'type' => TypeRegistry::string(),
									'resolve' => function ($choice) {
										return $choice['title'];
									}
								],
								'votes' => [
									'type' => TypeRegistry::int(),
									'resolve' => function ($choice) {
										return $choice['votes'];
									}
								],
								'votedFor' => [
									'type' => TypeRegistry::boolean(),
									'description' => "Whether the logged-in member voted for this choice",
									'resolve' => function ($choice) {
										return \in_array( \IPS\Member::loggedIn()->member_id, $choice['voters'] );
									}
								],
								'voters' => [
									'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::member() ),
									'args' => [
										'offset' => [
											'type' => TypeRegistry::int(),
											'defaultValue' => 0
										],
										'limit' => [
											'type' => TypeRegistry::int(),
											'defaultValue' => 25
										]
									],
									'resolve' => function ($choice, $args) {
										$members = array();
										$offset = max( $args['offset'], 0 );
										$limit = min( $args['limit'], 50 );
										$membersToLoad = \array_slice( $choice['voters'], $offset, $limit );

										foreach( $membersToLoad as $memberID )
										{
											$members[] = \IPS\Member::load( $memberID );
										}

										return $members;
									}
								]
							]
						]) ),
						'description' => "The choices for this question",
						'resolve' => function ($question) {
							$choices = array();

							foreach( $question['data']['choice'] as $idx => $choice )
							{
								$choices[ $idx ] = array(
									'id' => $question['id'] . '-c' . $idx, 
									'poll' => $question['poll'],
									'title' => $choice,
									'votes' => isset( $question['data']['votes'] ) ? $question['data']['votes'][ $idx ] : 0,
									'voters' => isset( $question['voters'][ $idx ] ) ? $question['voters'][ $idx ] : array()
								);
							}

							return $choices;
						}
					]
				];
			}
		];

		parent::__construct($config);
	}
}
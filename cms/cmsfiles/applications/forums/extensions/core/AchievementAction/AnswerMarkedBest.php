<?php
/**
 * @brief		Achievement Action Extension: Answer a member in a Q&A Forum is marked as the best answer
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Forums
 * @since		18 Feb 2021
 */

namespace IPS\forums\extensions\core\AchievementAction;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement Action Extension: Answer a member in a Q&A Forum is marked as the best answer
 */
class _AnswerMarkedBest extends \IPS\core\Achievements\Actions\AbstractAchievementAction
{
	/**
	 * Get filter form elements
	 *
	 * @param	array|NULL		$filters	Current filter values (if editing)
	 * @param	\IPS\Http\Url	$url		The URL the form is being shown on
	 * @return	array
	 */
	public function filters( ?array $filters, \IPS\Http\Url $url ): array
	{
		return [
			'nodes' => new \IPS\Helpers\Form\Node( 'achievement_filter_AnswerMarkedBest_forum', ( $filters and isset( $filters['nodes'] ) and $filters['nodes'] ) ? $filters['nodes'] : NULL, FALSE, [
				'url'				=> $url,
				'class'				=> 'IPS\forums\Forum',
				'showAllNodes'		=> TRUE,
				'multiple' 			=> TRUE,
				'permissionCheck'	=> function( $forum ) {
					return $forum->forums_bitoptions['bw_enable_answers'];
				}
			], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_AnswerMarkedBest_forum_prefix') ),
			'milestone' => new \IPS\Helpers\Form\Number( 'achievement_filter_AnswerMarkedBest_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_AnswerMarkedBest_nth_suffix') )
		];
	}
	
	/**
	 * Format filter form values
	 *
	 * @param	array	$values	The values from the form
	 * @return	array
	 */
	public function formatFilterValues( array $values ): array
	{
		$return = [];
		if ( isset( $values['achievement_filter_AnswerMarkedBest_forum'] ) )
		{
			$return['nodes'] = array_keys( $values['achievement_filter_AnswerMarkedBest_forum'] );
		}
		if ( isset( $values['achievement_filter_AnswerMarkedBest_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_AnswerMarkedBest_nth'];
		}
		return $return;
	}
	
	/**
	 * Work out if the filters applies for a given action
	 *
	 * Important note for milestones: consider the context. This method is called by \IPS\Member::achievementAction(). If your code 
	 * calls that BEFORE making its change in the database (or there is read/write separation), you will need to add
	 * 1 to the value being considered for milestones
	 *
	 * @param	\IPS\Member	$subject	The subject member
	 * @param	array		$filters	The value returned by formatFilterValues()
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @return	bool
	 */
	public function filtersMatch( \IPS\Member $subject, array $filters, $extra = NULL ): bool
	{
		if ( isset( $filters['nodes'] ) and !\in_array( $extra->container()->_id, $filters['nodes'] ) )
		{
			return FALSE;
		}
		
		if ( isset( $filters['milestone'] ) )
		{
			$where = [
				[ 'member_id=? AND app=?', $extra->author()->member_id, 'forums' ],
			];
			if ( isset( $filters['nodes'] ) )
			{
				$where[] = [ \IPS\Db::i()->in( 'forum_id', $filters['nodes'] ) ];
			}
			$query = \IPS\Db::i()->select( 'COUNT(*)', 'core_solved_index', $where );
			if ( isset( $filters['nodes'] ) )
			{
				$query->join( 'forums_topics', 'core_solved_index.item_id=forums_topics.tid' );
			}			
			if ( ( $query->first() + 1 ) < $filters['milestone'] )
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}

	/**
	 * Get the labels for the people this action might give awards to
	 *
	 * @param	array|NULL		$filters	Current filter values
	 *
	 * @return	array
	 */
	public function awardOptions( ?array $filters ): array
	{
		return [
			'subject'	=> 'achievement_filter_AnswerMarkedBest_poster',
			'other'		=> 'achievement_filter_AnswerMarkedBest_asker'
		];
	}

	/**
	 * Get the "other" people we need to award =stuff to
	 *
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @param	array|NULL	$filters	Current filter values
	 * @return	array
	 */
	public function awardOther( $extra = NULL, ?array $filters = NULL ): array
	{
		return [ $extra->item()->author() ];
	}
	
	/**
	 * Get identifier to prevent the member being awarded points for the same action twice
	 * Must be unique within within of this domain, must not exceed 32 chars.
	 *
	 * @param	\IPS\Member	$subject	The subject member
	 * @param	mixed		$extra		Any additional information about what is happening (e.g. if a post is being made: the post object)
	 * @return	string
	 */
	public function identifier( \IPS\Member $subject, $extra = NULL ): string
	{
		return (string) $extra->pid;
	}
	
	/**
	 * Return a description for this action to show in the log
	 *
	 * @param	string	$identifier	The identifier as returned by identifier()
	 * @param	array	$actor		If the member was the "subject", "other", or both
	 * @return	string
	 */
	public function logRow( string $identifier, array $actor ): string
	{
		$sprintf = [];
		try
		{
			$post = \IPS\forums\Topic\Post::load( $identifier );
			$sprintf = [ 'htmlsprintf' => [
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $post->url(), TRUE, $post->item()->title, FALSE )
			] ];
		}
		catch ( \OutOfRangeException $e )
		{
			$sprintf = [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack('AchievementAction__AnswerMarkedBest_log_deleted') ] ];
		}
		
		if ( \in_array( 'subject', $actor ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__AnswerMarkedBest_log_subject', FALSE, $sprintf );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__AnswerMarkedBest_log_other', FALSE, $sprintf );
		}
	}
	
	/**
	 * Get "description" for rule
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|null
	 */
	public function ruleDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{		
		$conditions = [];
		if ( isset( $rule->filters['milestone'] ) )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone', FALSE, [
				'htmlsprintf' => [
					\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'milestone', \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone_nth', FALSE, [ 'pluralize' => [ $rule->filters['milestone'] ] ] ) )
				],
				'sprintf' => \IPS\Member::loggedIn()->language()->addToStack( 'best_answer_post', FALSE, [ 'strtolower' => TRUE ] )
			] );
		}
		if ( isset( $rule->filters['nodes'] ) )
		{
			$forumNames = [];
			foreach ( $rule->filters['nodes'] as $id )
			{
				try
				{
					$forumNames[] = \IPS\forums\Forum::load( $id )->_title;
				}
				catch ( \OutOfRangeException $e ) {}
			}
			if ( $forumNames )
			{
				$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location', FALSE, [
					'htmlsprintf' => [
						\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'location',
							\count( $forumNames ) === 1 ? $forumNames[0] : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location_val', FALSE, [ 'sprintf' => [
								\count( $forumNames ),
								\IPS\Member::loggedIn()->language()->addToStack( \IPS\forum\Forum::$nodeTitle, FALSE, [ 'strtolower' => TRUE ] )
							] ] ),
							\count( $forumNames ) === 1 ? NULL : $forumNames
						)
					],
				] );
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			\IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__AnswerMarkedBest_title' ),
			$conditions
		);
	}

	/**
	 * Get rebuild data
	 *
	 * @return	array
	 */
	static public function rebuildData()
	{
		return [ [
			'table' => 'core_solved_index',
			'pkey'  => 'id',
			'date'  => 'solved_date',
			'where' => [ [ 'app=?', 'forums' ] ],
		] ];
	}

	/**
	 * Process the rebuild row
	 *
	 * @param array		$row	Row from database
	 * @param array		$data	Data collected when starting rebuild [table, pkey...]
	 * @return void
	 */
	public static function rebuildRow( $row, $data )
	{
		$post = \IPS\forums\Topic\Post::load( $row['comment_id'] );
		$post->author()->achievementAction( 'forums', 'AnswerMarkedBest', $post, \IPS\DateTime::ts( $row[ $data['date'] ] ) );
	}
}
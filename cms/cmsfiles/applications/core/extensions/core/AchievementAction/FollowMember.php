<?php
/**
 * @brief		Achievement Action Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @since		03 Mar 2021
 */

namespace IPS\core\extensions\core\AchievementAction;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement Action Extension
 */
class _FollowMember extends \IPS\core\Achievements\Actions\AbstractAchievementAction
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
		$nthFilter = new \IPS\Helpers\Form\Number( 'achievement_filter_FollowMember_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_FollowMember_nth_suffix') );
		$nthFilter->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewClub_nth');

		$return = array();

		$return['milestone'] = $nthFilter;

		return $return;
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

		if ( isset( $values['achievement_filter_FollowMember_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_FollowMember_nth'];
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
		if ( isset( $filters['milestone'] ) )
		{
			$where = [];
			$where[] = [ 'follow_app=? and follow_area=? and follow_rel_id=?', 'core', 'member', $subject->member_id ];

			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', $where )->first();

			if ( $count < $filters['milestone'] )
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
			'subject'	=> 'achievement_filter_FollowMember_receiver',
			'other'		=> 'achievement_filter_FollowMember_giver'
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
		return [ $extra['giver'] ];
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
		return $subject->member_id . ':' . $extra['giver']->member_id;
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
		$exploded = explode( ':', $identifier );

		$reactionName = \IPS\Member::loggedIn()->language()->addToStack('unknown');
		$receivedLink = \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted');
		$giverLink = \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted');
		try
		{
			$giver = \IPS\Member::load( $exploded[0] );
			$received = \IPS\Member::load( $exploded[1] );
			$receivedLink = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $received->url(), TRUE, $received->name, FALSE );
			$giverLink = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $giver->url(), TRUE, $giver->name, FALSE );
		}
		catch ( \OutOfRangeException $e ) {  }

		if ( \in_array( 'subject', $actor ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowMember_log_subject', FALSE, [ 'htmlsprintf' => [ $receivedLink ] ] );
		}
		else
		{

			return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowMember_log_other', FALSE, [ 'htmlsprintf' => [ $giverLink ] ] );
		}
	}

	/**
	 * Get "description" for rule
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
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
				'sprintf' => \IPS\Member::loggedIn()->language()->addToStack('AchievementAction__FollowMember_title_generic')
			] );
		}

		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			\IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowMember_title' ),
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
			'table' => 'core_follow',
			'pkey'  => 'follow_id',
			'date'  => 'follow_added',
			'where' => [ ['follow_app=? and follow_area=?', 'core', 'member' ] ],
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
		$receiver = \IPS\Member::load( $row['follow_rel_id'] );
		$receiver->achievementAction( 'core', 'FollowMember', [
			'giver' => \IPS\Member::load( $row['follow_member_id'] )
		] );
	}
}

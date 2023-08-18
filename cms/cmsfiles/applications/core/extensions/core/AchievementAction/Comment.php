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
class _Comment extends \IPS\core\Achievements\Actions\AbstractContentAchievementAction
{	
	protected static $includeItems = FALSE;
	protected static $includeReviews = FALSE;
	
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
		if ( !parent::filtersMatch( $subject, $filters, $extra ) )
		{
			return FALSE;
		}

		if ( isset( $filters['milestone'] ) )
		{
			$count = 0;
			$classes = [];
			$itemClassesToDeduct = [];
			if ( isset( $filters['type'] ) )
			{
				$item = $extra->item();

				if ( isset( $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $item ) ) ] ) )
				{
					$class = $filters['type'];
					$where = [ [ $class::$databasePrefix . $class::$databaseColumnMap['author'] . '=?', $subject->member_id ] ];

					if ( isset( $extra::$databaseColumnMap['first'] ) )
					{
						$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['first'] . '=0' ];
					}

					if ( isset( $class::$databaseColumnMap['approved'] ) )
					{
						$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['approved'] . '=?', 1 ];
					}
					elseif ( isset( $class::$databaseColumnMap['hidden'] ) )
					{
						$where[] = [ $class::$databasePrefix . $class::$databaseColumnMap['hidden'] . '=?', 0 ];
					}

					$where[] = [ \IPS\Db::i()->in( $item::$databaseTable . '.' . $item::$databasePrefix . $item::$databaseColumnMap['container'] , $filters[ 'nodes_' . str_replace( '\\', '-', \get_class( $item ) ) ] ) ];
					
					$count += \IPS\Db::i()->select( 'COUNT(*)', $class::$databaseTable, $where )
						      ->join( $item::$databaseTable, $item::$databasePrefix . $item::$databaseColumnId . '=' . $class::$databasePrefix . $class::$databaseColumnMap['item'] )
						      ->first();
				}
				else
				{
					$classes[] = $filters['type'];
				}

				foreach ( $classes as $class )
				{
					$count += $class::memberPostCount( $subject, TRUE, FALSE );
				}
				foreach ( $itemClassesToDeduct as $class )
				{
					$count -= $class::memberPostCount( $subject, TRUE, FALSE );
				}
			}
			else
			{
				/* It's too expensive to get a live count from every single item/comment class available to the suite
				   so lets just use the post count here. It won't be quite as accurate but we're not a bank so whatever */
				$count = $subject->member_posts;
			}

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
			'subject'	=> 'achievement_filter_Comment_author',
			'other'		=> 'achievement_filter_Comment_item_author'
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
	 * Return a description for this action to show in the log
	 *
	 * @param	string	$identifier	The identifier as returned by identifier()
	 * @param	array	$actor		If the member was the "subject", "other", or both
	 * @return	string
	 */
	public function logRow( string $identifier, array $actor ): string
	{
		$exploded = explode( ':', $identifier );
		
		$sprintf = [];
		try
		{
			if( !\class_exists( $exploded[0] ) )
			{
				throw new \OutOfRangeException;
			}

			$comment = $exploded[0]::load( $exploded[1] );
			$item = $comment->item();
			$sprintf = [ 'htmlsprintf' => [
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $comment->url(), TRUE, $item->mapped('title') ?: $item->indefiniteArticle(), FALSE )
			] ];
		}
		catch ( \OutOfRangeException $e )
		{
			$sprintf = [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted') ] ];
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__Comment_log', FALSE, $sprintf );
	}
	
	/**
	 * Get "description" for rule
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
	 */
	public function ruleDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{
		$type = $rule->filters['type'] ?? NULL;
		
		$conditions = [];
		if ( isset( $rule->filters['milestone'] ) )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone', FALSE, [
				'htmlsprintf' => [
					\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'milestone', \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_milestone_nth', FALSE, [ 'pluralize' => [ $rule->filters['milestone'] ] ] ) )
				],
				'sprintf'		=> [ $type ? \IPS\Member::loggedIn()->language()->addToStack( $type::$title ) : \IPS\Member::loggedIn()->language()->addToStack('AchievementAction__Comment_title_generic') ]
			] );
		}
		if ( $nodeCondition = $this->_nodeFilterDescription( $rule ) )
		{
			$conditions[] = $nodeCondition;
		}
		
		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			$type ? \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__NewContentItem_title_t', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( $type::$title ) ] ] ) : \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__Comment_title' ),
			$conditions
		);
	}
}
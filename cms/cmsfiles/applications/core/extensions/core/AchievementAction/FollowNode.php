<?php
/**
 * @brief		Achievement Action Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @since		04 Mar 2021
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
class _FollowNode extends \IPS\core\Achievements\Actions\AbstractNodeAchievementAction
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
		$filters = parent::filters( $filters, $url );

		foreach( $filters['type']->options['options'] as $class => $value )
		{
			if ( isset( $class::$contentItemClass ) and  ! \in_array( 'IPS\Content\Followable', class_implements( $class::$contentItemClass ) ) )
			{
				unset( $filters['type']->options['options'][ $class ] );
				unset( $filters['nodes_' . str_replace( '\\', '-', $class ) ] );
			}
		}

		return $filters;
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
			$node = $exploded[0]::load( $exploded[1] );
			$sprintf = [ 'htmlsprintf' => [
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $node->url(), TRUE, $node->_title, FALSE )
			] ];
		}
		catch ( \OutOfRangeException $e )
		{
			$sprintf = [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted') ] ];
		}

		return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowNode_log', FALSE, $sprintf );
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
				'sprintf'		=> [ $type ? \IPS\Member::loggedIn()->language()->addToStack( $type::fullyQualifiedType(), FALSE, [ 'strtolower' => TRUE ] ) : \IPS\Member::loggedIn()->language()->addToStack('AchievementAction__NewContentItem_title_generic') ]
			] );
		}
		if ( $nodeCondition = $this->_nodeFilterDescription( $rule ) )
		{
			$conditions[] = $nodeCondition;
		}

		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			$type ? \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowNode_title_t', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( $type::fullyQualifiedType() ) ] ] ) : \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__FollowNode_title' ),
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
			'where' => [ [ '(follow_app !=? and follow_area !=?)', 'core', 'member' ] ],
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
		$class = 'IPS\\' . $row['follow_app'] . '\\' . mb_ucfirst( $row['follow_area'] );
		if ( class_exists( $class ) AND \in_array( 'IPS\Node\Model', class_parents( $class ) ) )
		{
			\IPS\Member::load( $row['follow_member_id'] )->achievementAction( 'core', 'FollowNode', $class::load( $row['follow_rel_id'] ) );
		}
	}
}
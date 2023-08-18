<?php
/**
 * @brief		Achievement Action Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Commerce
 * @since		25 Mar 2021
 */

namespace IPS\nexus\extensions\core\AchievementAction;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement Action Extension
 */
class _Subscription extends \IPS\core\Achievements\Actions\AbstractAchievementAction // NOTE: Other classes exist to provided bases for common situations, like where node-based filters will be required
{
	/**
	 * Can use this rule?
	 *
	 * @return boolean
	 */
	public function canUse(): bool
	{
		return (bool) parent::canUse() and \IPS\Settings::i()->nexus_subs_enabled;
	}

	/**
	 * Get filter form elements
	 *
	 * @param	array|NULL		$filters	Current filter values (if editing)
	 * @param	\IPS\Http\Url	$url		The URL the form is being shown on
	 * @return	array
	 */
	public function filters( ?array $filters, \IPS\Http\Url $url ): array
	{
		$return	= array();

		$return['subscriptions'] = new \IPS\Helpers\Form\Node( 'achievement_filter_Subscriptions', ( $filters and isset( $filters['subscriptions'] ) and $filters['subscriptions'] ) ? $filters['subscriptions'] : 0, FALSE, [
			'url'				=> $url,
			'class'				=> 'IPS\nexus\Subscription\Package',
			'showAllNodes'		=> TRUE,
			'multiple' 			=> TRUE,
		], NULL, \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_Subscriptions_node_prefix', FALSE, [ 'sprintf' => [
			\IPS\Member::loggedIn()->language()->addToStack( 'nexus_sub_package_id', FALSE ),
			\IPS\Member::loggedIn()->language()->addToStack( 'calendars_sg', FALSE, [ 'strtolower' => TRUE ] )
		] ] ) );

		$return['subscriptions_active'] = new \IPS\Helpers\Form\YesNo( 'achievement_filter_Subscriptions_active', $filters and isset( $filters['subscriptions_active'] ) and $filters['subscriptions_active'], FALSE, [],NULL,
			\IPS\Member::loggedIn()->language()->addToStack( 'nexus_sub_active', FALSE )
		 );

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
		if ( isset( $values['achievement_filter_Subscriptions'] ) )
		{
			$return['subscriptions'] = array_keys( $values['achievement_filter_Subscriptions'] );
		}
		if ( isset( $values['achievement_filter_Subscriptions_active'] ) )
		{
			$return['subscriptions_active'] =  $values['achievement_filter_Subscriptions_active'];
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
		if ( isset( $filters['subscriptions'] ) )
		{
			if ( !\in_array( $extra->package_id, $filters['subscriptions'] ) )
			{
				return FALSE;
			}
		}

		if ( isset( $filters['subscriptions_active'] ) )
		{
			foreach ( $filters['subscriptions'] as $id )
			{
				try
				{
					\IPS\nexus\Subscription::loadByMemberAndPackage( $subject, \IPS\nexus\Subscription\Package::load( $id ), TRUE );

					/* Still here? */
					return TRUE;
				}
				catch ( \OutOfRangeException $e ) { }
			}

			/* No active matches */
			return FALSE;
		}

		return TRUE;
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
		return (string) $extra->id;
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
		return "Subscription";
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

		if ( isset( $rule->filters['subscriptions_active'] ) AND $rule->filters['subscriptions_active'] )
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_Subscriptions_active');
		}
		else
		{
			$conditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_Subscriptions_node_prefix');
		}

		if ( $nodeCondition = $this->_nodeFilterDescription( $rule ) )
		{
			$conditions[] = $nodeCondition;
		}

		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			\IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__Subscription_title' ),
			$conditions
		);
	}

	/**
	 * Get "description" for rule (usually a description of the rule's filters)
	 *
	 * @param	\IPS\core\Achievements\Rule	$rule	The rule
	 * @return	string|NULL
	 */
	protected function _nodeFilterDescription( \IPS\core\Achievements\Rule $rule ): ?string
	{
		if ( isset( $rule->filters['subscriptions'] ) )
		{
			$nodeNames = [];
			foreach ( $rule->filters['subscriptions'] as $id )
			{
				try
				{
					$nodeNames[] = \IPS\nexus\Subscription\Package::load( $id )->_title;
				}
				catch ( \OutOfRangeException $e ) {}
			}

			if ( $nodeNames )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'achievements_filter_Subscription_type', FALSE, [
					'htmlsprintf' => [
						\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'achievements_filter_Subscription_type',
							\count( $nodeNames ) === 1 ? $nodeNames[0] : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_filter_Subscription_pl', FALSE, [ 'sprintf' => [
								\count( $nodeNames ),
								\IPS\Member::loggedIn()->language()->addToStack( 'nexus_sub_package_id', FALSE, [ 'strtolower' => TRUE ] )
							] ] ),
							\count( $nodeNames ) === 1 ? NULL : $nodeNames
						)
					],
				] );
			}
		}

		return NULL;
	}

	/**
	 * Get rebuild data
	 *
	 * @return	array
	 */
	static public function rebuildData()
	{
		return [ [
			'table' => 'nexus_member_subscriptions',
			'pkey'  => 'sub_id',
			'date'  => 'sub_start',
			'where' => [],
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
		\IPS\Member::load( $row['sub_member_id'] )->achievementAction( 'nexus', 'Subscription', \IPS\nexus\Subscription::load( $row['sub_id'] ) );
	}

}
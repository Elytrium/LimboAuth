<?php
/**
 * @brief		Achievement Action Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @subpackage	Calendar
 * @since		17 Mar 2021
 */

namespace IPS\calendar\extensions\core\AchievementAction;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement Action Extension
 */
class _Rsvp extends \IPS\core\Achievements\Actions\AbstractAchievementAction // NOTE: Other classes exist to provided bases for common situations, like where node-based filters will be required
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
		$return	= array();

		$return['nodes'] = new \IPS\Helpers\Form\Node( 'achievement_filter_Rsvp_nodes', ( $filters and isset( $filters['nodes'] ) and $filters['nodes'] ) ? $filters['nodes'] : 0, FALSE, [
			'url'				=> $url,
			'class'				=> 'IPS\calendar\Calendar',
			'showAllNodes'		=> TRUE,
			'multiple' 			=> TRUE,
		], NULL, \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node_prefix', FALSE, [ 'sprintf' => [
			\IPS\Member::loggedIn()->language()->addToStack( 'rsvp', FALSE ),
			\IPS\Member::loggedIn()->language()->addToStack( 'calendars_sg', FALSE, [ 'strtolower' => TRUE ] )
		] ] ) );
		$return['nodes']->label = \IPS\Member::loggedIn()->language()->addToStack( 'achievement_filter_NewContentItem_node', FALSE, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack( 'calendars_sg', FALSE, [ 'strtolower' => TRUE ] ) ] ] );

		$return['milestone'] = new \IPS\Helpers\Form\Number( 'achievement_filter_Rsvp_nth', ( $filters and isset( $filters['milestone'] ) and $filters['milestone'] ) ? $filters['milestone'] : 0, FALSE, [], NULL, \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_nth_their'), \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_Rsvp_nth_suffix') );
		$return['milestone']->label = \IPS\Member::loggedIn()->language()->addToStack('achievement_filter_NewContentItem_nth');
		
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
		if ( isset( $values['achievement_filter_Rsvp_nodes'] ) )
		{			
			$return['nodes'] = array_keys( $values['achievement_filter_Rsvp_nodes'] );
		}
		if ( isset( $values['achievement_filter_Rsvp_nth'] ) )
		{
			$return['milestone'] = $values['achievement_filter_Rsvp_nth'];
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
		if ( isset( $filters['nodes'] ) )
		{
			if ( !\in_array( $extra->container()->_id, $filters['nodes'] ) )
			{
				return FALSE;
			}
		}

		if ( isset( $filters['milestone'] ) )
		{
			$query = $this->getQuery( 'COUNT(*)', [ [ 'rsvp_member_id=?', $subject->member_id ] ], NULL, NULL, $filters );
						
			if ( $query->first() < $filters['milestone'] )
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
			'subject'	=> 'achievement_filter_Rsvp_receiver',
			'other'		=> 'achievement_filter_Rsvp_giver'
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
		return [ $extra->author() ];
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
		return 'Rsvp:' . $extra->id . ':' . $subject->member_id;
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
			$item = \IPS\calendar\Event::load( $exploded[1] );
			$sprintf = [ 'htmlsprintf' => [
				\IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( $item->url(), TRUE, $item->mapped('title') ?: $item->indefiniteArticle(), FALSE )
			] ];
		}
		catch ( \OutOfRangeException $e )
		{
			$sprintf = [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->addToStack('modcp_deleted') ] ];
		}

		return \IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__Rsvp_log', FALSE, $sprintf );
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
				'sprintf'		=> [ \IPS\Member::loggedIn()->language()->addToStack('rsvp') ]
			] );
		}
		if ( $nodeCondition = $this->_nodeFilterDescription( $rule ) )
		{
			$conditions[] = $nodeCondition;
		}

		return \IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescription(
			\IPS\Member::loggedIn()->language()->addToStack( 'AchievementAction__Rsvp_title' ),
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
		if ( isset( $rule->filters['nodes'] ) )
		{
			$nodeNames = [];
			foreach ( $rule->filters['nodes'] as $id )
			{
				try
				{
					$nodeNames[] = \IPS\calendar\Calendar::load( $id )->_title;
				}
				catch ( \OutOfRangeException $e ) {}
			}
			if ( $nodeNames )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location', FALSE, [
					'htmlsprintf' => [
						\IPS\Theme::i()->getTemplate( 'achievements' )->ruleDescriptionBadge( 'location',
							\count( $nodeNames ) === 1 ? $nodeNames[0] : \IPS\Member::loggedIn()->language()->addToStack( 'achievements_title_filter_location_val', FALSE, [ 'sprintf' => [
								\count( $nodeNames ),
								\IPS\Member::loggedIn()->language()->addToStack( 'calendars', FALSE, [ 'strtolower' => TRUE ] )
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
	 * Rebuild points and badges based on the content this rule manages
	 *
	 * @param	array				$data		The rebuild data
	 * @param	array				$filters	The value returned by formatFilterValues()
	 * @param	int					$lastId		The last ID returned by a previous iteration
	 * @param	int					$limit		The limit of how many to rebuild
	 * @param 	\IPS\DataTime|null 	$time		Any time limit to add
	 * @return array
	 */
	public function processRebuild( $data, $filters, $lastId, $limit, \IPS\DateTime $time = NULL ): array
	{
		$where = ( $time ? [ ['rsvp_id > ? and rsvp_date >= ?', $lastId, $time->getTimestamp() ] ] : [ [ 'rsvp_id > ?', $lastId ] ] );

		/* Try and limit the number of rows for the good of humanity */
		$query = $this->getQuery( '*', $where, $limit, 'rsvp_id ASC', $filters );
		$done = 0;
		foreach( $query as $row )
		{
			try
			{
				\IPS\Member::load( $row['rsvp_member_id'] )->achievementAction( 'calendar', 'Rsvp', [ \IPS\calendar\Event::load( $row['rsvp_event_id'] ) ] );
			}
			catch( \Exception $e ){}

			$done++;
			$lastId = $row['rsvp_id'];
		}

		return [ 'processed' => $done, 'lastId' => $lastId ];
	}

	/**
	 * Get rebuild data
	 *
	 * @param	array				$data		Data stored with the queue item
	 * @param	array				$filters	The value returned by formatFilterValues()
	 * @param	\IPS\DataTime|null	$time		Any time limit to add
	 * @return	void
	 */
	public function preRebuildData( &$data, $filters, \IPS\DateTime $time = NULL )
	{
		$data['count'] = $this->getQuery( 'COUNT(*)', ( $time ? [ ['rsvp_date >= ?', $time->getTimestamp() ] ] : [] ), NULL, NULL, $filters )->first();
	}

	/**
	 * Get a query to use for multiple methods within this extension
	 * @param	string		$select		Select for the query
	 * @param	array|NULL	$where		Where for the query
	 * @param	int|NULL	$limit		Limit for the query
	 * @param	string|NULL	$order		Order by for the query
	 * @param	array		$filters	Rule filters
	 * @return	\IPS\Db\Select
	 */
	public function getQuery( $select, $where, $limit, $order, $filters ): \IPS\Db\Select
	{
		$joinContainers		= FALSE;
		$extraJoinCondition	= NULL;
		$where				= \is_array( $where ) ? $where : array();

		/* Limit by node */
		if ( isset( $filters['nodes'] ) )
		{
			$joinContainers		= TRUE;
			$extraJoinCondition	= ' AND ' . \IPS\Db::i()->in( 'calendar_events.event_calendar_id', $filters['nodes'] );
		}

		$query = \IPS\Db::i()->select( $select, 'calendar_event_rsvp', $where, $order, $limit );

		if ( $joinContainers )
		{
			$query->join( 'calendar_events', 'calendar_event_rsvp.rsvp_event_id=calendar_events.event_id' . $extraJoinCondition, 'INNER' );
		}

		return $query;
	}
}
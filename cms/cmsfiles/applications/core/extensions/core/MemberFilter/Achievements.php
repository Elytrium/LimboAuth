<?php
/**
 * @brief		Member filter extension: Achievements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Sept 2021
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Achievements
 */
class _Achievements
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'bulkmail', 'group_promotions', 'automatic_moderation' ) );
	}

	/**
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		/* Get our badges */
		$badges['options'] = [];

		foreach( \IPS\core\Achievements\Badge::roots() as $badge )
		{
			$badges['options'][ $badge->id ] = $badge->_title;
		}

		/* If all *available* options are selected, we want to choose 'all' for consistency on create vs edit */
		$_options = array_keys( $badges['options'] );

		if( isset( $criteria['achievement_badges'] ) )
		{
			if( ! \count( array_diff( $_options, explode( ',', $criteria['achievement_badges'] ) ) ) )
			{
				$criteria['achievement_badges'] = 'all';
			}
		}

		/* Get our ranks */
		$ranks['options'] = [];

		foreach( \IPS\core\Achievements\Rank::roots() as $rank )
		{
			$ranks['options'][ $rank->id ] = $rank->_title;
		}

		/* If all *available* options are selected, we want to choose 'all' for consistency on create vs edit */
		$_options = array_keys( $ranks['options'] );

		$return = array(
			new \IPS\Helpers\Form\Custom( 'mf_points', array( 0 => isset( $criteria['achievement_points_operator'] ) ? $criteria['achievement_points_operator'] : NULL, 1 => isset( $criteria['achievement_points_value'] ) ? $criteria['achievement_points_value'] : NULL ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( "{$element->name}[0]", $element->value[0], $element->required, array(
							'any'	=> \IPS\Member::loggedIn()->language()->addToStack('any'),
							'gt'	=> \IPS\Member::loggedIn()->language()->addToStack('gt'),
							'lt'	=> \IPS\Member::loggedIn()->language()->addToStack('lt'),
							'eq'	=> \IPS\Member::loggedIn()->language()->addToStack('exactly'),
						),
							FALSE,
							NULL,
							FALSE,
							array(
								'any'	=> array(),
								'gt'	=> array( 'elNumber_' . $element->name . '-qty' ),
								'lt'	=> array( 'elNumber_' . $element->name . '-qty' ),
								'eq'	=> array( 'elNumber_' . $element->name . '-qty' ),
							) )
						. ' '
						. \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->number( "{$element->name}[1]", $element->value[1], $element->required, NULL, FALSE, NULL, NULL, NULL, 0, NULL, FALSE, NULL, array(), array(), array(), $element->name . '-qty' );
				}
			), NULL, NULL, NULL, 'mp_points' )
		);

		if( \count( $badges['options'] ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo( 'mf_badges_choose', ( !empty( $criteria['achievement_badges'] ) AND $criteria['achievement_badges'] !== 'ignore' ), FALSE, array(
				'togglesOn' => [ 'mf_badges' ]
			), NULL, NULL, NULL, 'mf_badges_choose' );
			$return[] = new \IPS\Helpers\Form\CheckboxSet( 'mf_badges', ( isset( $criteria['achievement_badges'] ) AND $criteria['achievement_badges'] != 'ignore' ) ? ( $criteria['achievement_badges'] === 'all' ? 'all' : explode( ',', $criteria['achievement_badges'] ) ) : [], FALSE, array(
				'options'		=> $badges['options'],
				'multiple'		=> TRUE,
				'unlimited'		=> 'all',
				'unlimitedLang'	=> 'mf_achievements_all_badges',
				'impliedUnlimited' => TRUE
			), NULL, NULL, NULL, 'mf_badges' );
		}

		if( \count( $ranks['options'] ) )
		{
			$return[] = new \IPS\Helpers\Form\YesNo( 'mf_ranks_choose', ( !empty( $criteria['achievement_ranks'] ) AND $criteria['achievement_ranks'] !== 'ignore' ), FALSE, array(
				'togglesOn' => [ 'mf_ranks' ]
			), NULL, NULL, NULL, 'mf_ranks_choose' );
			$return[] = new \IPS\Helpers\Form\CheckboxSet( 'mf_ranks', ( isset( $criteria['achievement_ranks'] ) AND $criteria['achievement_ranks'] !== 'ignore' ) ? ( $criteria['achievement_ranks'] === 'all' ? 'all' : explode( ',', $criteria['achievement_ranks'] ) ) : [], FALSE, array(
				'options'		=> $ranks['options'],
				'multiple'		=> TRUE,
				'unlimited'		=> 'all',
				'unlimitedLang'	=> 'mf_achievements_all_ranks',
				'impliedUnlimited' => TRUE
			), NULL, NULL, NULL, 'mf_ranks' );
		}

		return $return;
	}
	
	/**
	 * Save the filter data
	 *
	 * @param	array	$post	Form values
	 * @return	mixed			False, or an array of data to use later when filtering the members
	 * @throws \LogicException
	 */
	public function save( $post )
	{
		return array(
			'achievement_points_operator' => $post['mf_points'][0],
			'achievement_points_value' => $post['mf_points'][1],
			'achievement_badges' => ( !empty( $post['mf_badges_choose'] ) ) ? ( $post['mf_badges'] == 'all' ? 'all' : implode( ',', $post['mf_badges'] ) ) : 'ignore',
			'achievement_ranks' =>  ( !empty( $post['mf_ranks_choose'] ) ) ? ( $post['mf_ranks'] == 'all' ? 'all' : implode( ',', $post['mf_ranks'] ) ) : 'ignore',
		);
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		$where = [];
		if ( $data['achievement_points_operator'] and $data['achievement_points_value'] )
		{
			switch ( $data['achievement_points_operator'] )
			{
				case 'gt':
					$where[] = "achievements_points > " . (int) $data['achievement_points_value'];
					break;
				case 'lt':
					$where[] = "achievements_points < " . (int) $data['achievement_points_value'];
					break;
				case 'eq':
					$where[] = "achievements_points = " . (int) $data['achievement_points_value'];
					break;
			}
		}

		if ( $data['achievement_badges'] != 'ignore' )
		{
			if( $data['achievement_ranks'] == 'all' )
			{
				$badgeWhere = NULL;
			}
			else
			{
				$badgeWhere = \IPS\Db::i()->in( 'badge', explode( ',', $data['achievement_badges'] ) );
			}

			$badgeSelect = \IPS\Db::i()->select( 'member', 'core_member_badges', $badgeWhere, NULL, NULL, NULL, NULL, \IPS\Db::SELECT_DISTINCT );
			$where[] = 'core_members.member_id IN(' . $badgeSelect . ')';
		}

		if ( $data['achievement_ranks'] != 'ignore' )
		{
			$ranks = \IPS\core\Achievements\Rank::getStore();
			$rankWhere = [];

			if( $data['achievement_ranks'] == 'all' )
			{
				$rankIds = array_keys( $ranks );
			}
			else
			{
				$rankIds = explode( ',', $data['achievement_ranks'] );
			}

			foreach( $rankIds as $val )
			{
				$minPoints = 0;
				$maxPoints = 0;
				$minSet = FALSE;
				foreach ( $ranks as $rank )
				{
					if ( !$minSet and $rank->id == $val )
					{
						$minPoints = $rank->points;
						$minSet = TRUE;
					}

					if ( $minSet and $rank->points > $minPoints )
					{
						$maxPoints = $rank->points;
						break;
					}
				}

				if ( $minPoints and $maxPoints )
				{
					$rankWhere[] = '( achievements_points BETWEEN ' . $minPoints . ' AND ' . ( $maxPoints - 1 ) . ' )';
				}
				elseif ( $minPoints )
				{
					$rankWhere[] = '( achievements_points >= ' . $minPoints . ' )';
				}
				elseif ( $maxPoints )
				{
					$rankWhere[] = '( achievements_points < ' . $maxPoints . ' )';
				}
			}

			if ( \count( $rankWhere ) )
			{
				$where[] = '(' . implode( ' OR ', $rankWhere ) . ')';
			}
		}

		return \count( $where ) ? '(' . implode( ' AND ', $where ) . ')' : NULL;
	}

	/**
	 * Determine if a member matches specified filters
	 *
	 * @note	This is only necessary if availableIn() includes group_promotions
	 * @param	\IPS\Member	$member		Member object to check
	 * @param	array 		$filters	Previously defined filters
	 * @param	object|NULL	$object		Calling class
	 * @return	bool
	 */
	public function matches( \IPS\Member $member, $filters, $object=NULL )
	{
		if ( ( isset( $filters['achievement_points_operator'] ) and ( $filters['achievement_points_operator'] ) and $filters['achievement_points_operator'] != 'any' ) and ( isset( $filters['achievement_points_value'] ) ) )
		{
			$pass = FALSE;
			switch ( $filters['achievement_points_operator'] )
			{
				case 'gt':
					$pass = (bool) ( $member->achievements_points > (int) $filters['achievement_points_value'] );
					break;
				case 'lt':
					$pass = (bool) ( $member->achievements_points < (int) $filters['achievement_points_value'] );
					break;
				case 'eq':
					$pass = (bool) ( $member->achievements_points = (int) $filters['achievement_points_value'] );
					break;
			}

			if ( $pass === FALSE )
			{
				/* They don't make it this far */
				return FALSE;
			}
		}

		/* Lets check badges */
		if ( isset( $filters['achievement_badges'] ) and $filters['achievement_badges'] != 'ignore' )
		{
			if ( ! \IPS\Db::i()->select( 'COUNT(*)', 'core_member_badges', [ 'member=? AND ' . \IPS\Db::i()->in( 'badge', explode( ',', $filters['achievement_badges'] ) ), $member->member_id ] )->first() )
			{
				/* Did not find a badge, so they failed the match */
				return FALSE;
			}
		}

		/* Let's check ranks */
		if ( isset( $filters['achievement_ranks'] ) and $filters['achievement_ranks'] != 'ignore' )
		{
			$ranks = \IPS\core\Achievements\Rank::getStore();
			$pass = FALSE;

			if( $filters['achievement_ranks'] == 'all' )
			{
				$rankIds = array_keys( $ranks );
			}
			else
			{
				$rankIds = explode( ',', $filters['achievement_ranks'] );
			}

			foreach( $rankIds as $val )
			{
				$minPoints = 0;
				$maxPoints = 0;
				$minSet = FALSE;
				foreach ( $ranks as $rank )
				{
					if ( !$minSet and $rank->id == $val )
					{
						$minPoints = $rank->points;
						$minSet = TRUE;
					}

					if ( $minSet and $rank->points > $minPoints )
					{
						$maxPoints = $rank->points;
						break;
					}
				}

				if ( $minPoints and $maxPoints )
				{
					$pass = (bool) $member->achievements_points >= $minPoints AND $member->achievements_points <= ($maxPoints - 1);
				}
				elseif ( $minPoints )
				{
					$pass = (bool) $member->achievements_points >= $minPoints;
				}
				elseif ( $maxPoints )
				{
					$pass = (bool) $member->achievements_points < $minPoints;
				}
			}

			if ( $pass === FALSE )
			{
				/* They don't make it this far */
				return FALSE;
			}
		}

		/* If we are still here, then there wasn't an appropriate operator (maybe they selected 'any') so return true */
		return TRUE;
	}
	
	/**
	 * Return a lovely human description for this rule if used
	 *
	 * @param	mixed				$filters	The array returned from the save() method
	 * @return	string|NULL
	 */
	public function getDescription( $filters )
	{
		$message = [];
		if ( ! empty( $filters['achievement_points_value'] ) and $filters['achievement_points_value'] > 0 )
		{
			switch ( $filters['achievement_points_operator'] )
			{
				case 'gt':
					$message[] = \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_cheev_points_gt_desc', FALSE, array( 'sprintf' => array( $filters['achievement_points_value'] ) ) );
				break;
				case 'lt':
					$message[] =  \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_cheev_points_lt_desc', FALSE, array( 'sprintf' => array( $filters['achievement_points_value'] ) ) );
				break;
				case 'eq':
					$message[] =  \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_cheev_points_eq_desc', FALSE, array( 'sprintf' => array( $filters['achievement_points_value'] ) ) );
				break;
			}
		}

		if ( $filters['achievement_badges'] != 'ignore' )
		{
			$count = \count( explode( ',', $filters['achievement_badges'] ) );
			if( $count )
			{
				$message[] = \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_cheev_desc', FALSE, [
					'sprintf' => [
						\IPS\Member::loggedIn()->language()->addToStack( 'recognize_badges_pluralize', FALSE, ['sprintf' => [$count], 'pluralize' => [$count]] )
					]] );
			}
		}

		if ( $filters['achievement_ranks'] != 'ignore' )
		{
			$count = \count( explode( ',', $filters['achievement_badges'] ) );
			if ( $count )
			{
				$message[] = \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_cheev_desc', FALSE, [
					'sprintf' => [
						\IPS\Member::loggedIn()->language()->addToStack( 'achievement_ranks_pluralize', FALSE, ['sprintf' => [$count], 'pluralize' => [$count]] )
					]] );
			}
		}
		
		return \count( $message ) ? implode( ', ', $message ) : NULL;
	}
}
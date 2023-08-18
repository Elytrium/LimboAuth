<?php
/**
 * @brief		Member filter extension: Reputation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Mar 2017
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Reputation
 */
class _Reputation
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
		return array(
			new \IPS\Helpers\Form\Custom( 'mf_reputation', array( 0 => isset( $criteria['reputation_operator'] ) ? $criteria['reputation_operator'] : NULL, 1 => isset( $criteria['reputation_score'] ) ? $criteria['reputation_score'] : NULL ), FALSE, array(
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
			) )
		);
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
		return array( 'reputation_operator' => $post['mf_reputation'][0], 'reputation_score' => $post['mf_reputation'][1] );
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( $data['reputation_operator'] and $data['reputation_score'] )
		{
			switch ( $data['reputation_operator'] )
			{
				case 'gt':
					return array( "pp_reputation_points > " . (int) $data['reputation_score'] );
					break;
				case 'lt':
					return array( "pp_reputation_points < " . (int) $data['reputation_score'] );
					break;
				case 'eq':
					return array( "pp_reputation_points= " . (int) $data['reputation_score'] );
					break;
			}
		}

		return NULL;
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
		/* If we aren't filtering by this, then any member matches */
		if( !isset( $filters['reputation_operator'] ) OR !$filters['reputation_operator'] OR !isset( $filters['reputation_score'] ) OR !$filters['reputation_score'] )
		{
			return TRUE;
		}

		switch ( $filters['reputation_operator'] )
		{
			case 'gt':
				return (bool) ( $member->pp_reputation_points > (int) $filters['reputation_score'] );
				break;
			case 'lt':
				return (bool) ( $member->pp_reputation_points < (int) $filters['reputation_score'] );
				break;
			case 'eq':
				return (bool) ( $member->pp_reputation_points == (int) $filters['reputation_score'] );
				break;
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
		if ( ! empty( $filters['reputation_score'] ) and $filters['reputation_score'] > 0 )
		{
			switch ( $filters['reputation_operator'] )
			{
				case 'gt':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_reputation_gt_desc', FALSE, array( 'sprintf' => array( $filters['reputation_score'] ) ) );
				break;
				case 'lt':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_reputation_lt_desc', FALSE, array( 'sprintf' => array( $filters['reputation_score'] ) ) );
				break;
				case 'eq':
					return \IPS\Member::loggedIn()->language()->addToStack( 'member_filter_core_reputation_eq_desc', FALSE, array( 'sprintf' => array( $filters['reputation_score'] ) ) );
				break;
			}
		}
		
		return NULL;
	}
}
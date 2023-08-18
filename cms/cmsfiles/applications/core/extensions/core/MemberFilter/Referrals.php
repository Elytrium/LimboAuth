<?php
/**
 * @brief		Member Filter Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		08 Aug 2019
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Filter Extension
 */
class _Referrals
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'bulkmail', 'group_promotions' ) );
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
			new \IPS\Helpers\Form\Custom( 'mf_referrals', array( 0 => isset( $criteria['referrals_operator'] ) ? $criteria['referrals_operator'] : NULL, 1 => isset( $criteria['referrals'] ) ? $criteria['referrals'] : NULL ), FALSE, array(
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
		return array( 'referrals_operator' => $post['mf_referrals'][0], 'referrals' => $post['mf_referrals'][1] );
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['referrals_operator'] ) and isset( $data['referrals'] ) )
		{
			$referralsSelect = \IPS\Db::i()->select( 'COUNT(*)', 'core_referrals', array( 'referred_by=core_members.member_id' ) );

			switch ( $data['referrals_operator'] )
			{
				case 'gt':
					return array( "(" . $referralsSelect . ")>{$data['referrals']}" );
					break;
				case 'lt':
					return array( "(" . $referralsSelect . ")<{$data['referrals']}" );
					break;
				case 'eq':
					return array( "(" . $referralsSelect . ")={$data['referrals']}" );
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
		if( !isset( $filters['referrals_operator'] ) OR !isset( $filters['referrals'] ) )
		{
			return TRUE;
		}

		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_referrals', array( 'referred_by=?', $member->member_id ) )->first();

		switch ( $filters['referrals_operator'] )
		{
			case 'gt':
				return (bool) ( $count > (int) $filters['referrals'] );
				break;
			case 'lt':
				return (bool) ( $count < (int) $filters['referrals'] );
				break;
			case 'eq':
				return (bool) ( $count == (int) $filters['referrals'] );
				break;
		}

		/* If we are still here, then there wasn't an appropriate operator (maybe they selected 'any') so return true */
		return TRUE;
	}
}
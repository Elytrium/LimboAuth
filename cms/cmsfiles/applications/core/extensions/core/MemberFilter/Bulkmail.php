<?php
/**
 * @brief		Member filter extension: Bulk mail filter
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 July 2018
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Bulk mail filter
 */
class _Bulkmail
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'group_promotions' ) );
	}

	/**
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		$options = array( 'any' => 'any', 'on' => 'member_filter_bulk_mail_on', 'off' => 'member_filter_bulk_mail_off' );

		return array(
			new \IPS\Helpers\Form\Radio( 'member_filter_bulk_mail', isset( $criteria['bulk_mail'] ) ? $criteria['bulk_mail'] : 'any', FALSE, array( 'options' => $options ) ),
		);
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
		if( !isset( $filters['bulk_mail'] ) OR ! $filters['bulk_mail'] )
		{
			return TRUE;
		}

		switch ( $filters['bulk_mail'] )
		{
			case 'on':
				return $member->allow_admin_mails ? TRUE : FALSE;
				break;
			case 'off':
				return ! $member->allow_admin_mails ? TRUE : FALSE;
				break;
		}

		/* If we are still here, then there wasn't an appropriate operator (maybe they selected 'any') so return true */
		return TRUE;
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
		return ( isset( $post['member_filter_bulk_mail'] ) and \in_array( $post['member_filter_bulk_mail'], array( 'on', 'off' ) ) ) ? array( 'bulk_mail' => $post['member_filter_bulk_mail'] ) : FALSE;
	}

	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL	Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['bulk_mail'] ) )
		{
			switch ( $data['bulk_mail'] )
			{
				case 'on':
					return array( "allow_admin_mails=1" );
					break;
				case 'off':
					return array( "allow_admin_mails=0" );
					break;
			}
		}

		return NULL;
	}
}

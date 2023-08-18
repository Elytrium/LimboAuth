<?php
/**
 * @brief		Member Filter Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		22 Sep 2021
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
class _Banned
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
		$options = array( 'any' => 'any', 'banned' => 'mf_banned_banned', 'notbanned' => 'mf_banned_not_banned' );

		return array(
			new \IPS\Helpers\Form\Radio( 'mf_banned', isset( $criteria['banned'] ) ? $criteria['banned'] : 'any', FALSE, array( 'options' => $options ) ),
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
		if( !isset( $filters['banned'] ) )
		{
			return TRUE;
		}

		switch ( $filters['banned'] )
		{
			case 'banned':
				return ( (int) $member->temp_ban !== 0 );
			break;
			case 'notbanned':
				return empty( $member->temp_ban );
			break;
		}

		return FALSE;
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
		return ( isset( $post['mf_banned'] ) and \in_array( $post['mf_banned'], array( 'banned', 'notbanned' ) ) ) ? array( 'banned' => $post['mf_banned'] ) : FALSE;
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['banned'] ) )
		{
			switch ( $data['banned'] )
			{
				case 'banned':
					return array( "temp_ban<>0" );
					break;
				case 'notbanned':
					return array( "(temp_ban IS NULL OR temp_ban=0)" );
					break;
			}
		}

		return NULL;
	}
}
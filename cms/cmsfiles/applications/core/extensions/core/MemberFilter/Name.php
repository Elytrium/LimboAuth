<?php
/**
 * @brief		Member filter extension: Name
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 May 2018
 */

namespace IPS\core\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member filter: Name
 */
class _Name
{
	/**
	 * Determine if the filter is available in a given area
	 *
	 * @param	string	$area	Area to check
	 * @return	bool
	 */
	public function availableIn( $area )
	{
		return \in_array( $area, array( 'bulkmail' ) );
	}

	/**
	 * Get Setting Field
	 *
	 * @param	mixed	$criteria	Value returned from the save() method
	 * @return	array 	Array of form elements
	 */
	public function getSettingField( $criteria )
	{
		$options = array( 'any' => 'mf_name_whatever', 'yes' => 'mf_name_yes', 'no' => 'mf_name_no' );

		return array(
			new \IPS\Helpers\Form\Radio( 'mf_name', isset( $criteria['mf_name'] ) ? $criteria['mf_name'] : 'yes', FALSE, array( 'options' => $options ) ),
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
		return ( isset( $post['mf_name'] ) and \in_array( $post['mf_name'], array( 'any', 'yes', 'no' ) ) ) ? array( 'mf_name' => $post['mf_name'] ) : FALSE;
	}

	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['mf_name'] ) )
		{
			switch ( $data['mf_name'] )
			{
				case 'yes':
					return array( "core_members.name <> ''" );
					break;
				case 'no':
					return array( "core_members.name = ''" );
					break;
			}
		}

		return NULL;
	}
}
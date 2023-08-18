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
class _Validating
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
		$options = array( 'any' => 'any', 'validating' => 'mf_validating_validating', 'notvalidating' => 'mf_validating_not_validating' );

		return array(
			new \IPS\Helpers\Form\Radio( 'mf_validating', isset( $criteria['validating'] ) ? $criteria['validating'] : 'any', FALSE, array( 'options' => $options ) ),
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
		return ( isset( $post['mf_validating'] ) and \in_array( $post['mf_validating'], array( 'validating', 'notvalidating' ) ) ) ? array( 'validating' => $post['mf_validating'] ) : FALSE;
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['validating'] ) )
		{
			switch ( $data['validating'] )
			{
				case 'validating':
					return array( "( v.lost_pass=0 AND v.forgot_security=0 AND v.vid IS NOT NULL )" );
					break;
				case 'notvalidating':
					return array( "( v.vid IS NULL )" );
					break;
			}
		}

		return NULL;
	}

	/**
	 * Callback for member retrieval database query
	 * Can be used to set joins
	 *
	 * @param	mixed			$data	The array returned from the save() method
	 * @param	\IPS\Db\Query	$query	The query
	 * @return	void
	 */
	public function queryCallback( $data, &$query )
	{
		if( isset( $data['validating'] ) )
		{
			$query->join( [ 'core_validating', 'v' ], "core_members.member_id=v.member_id" );
		}

		return NULL;
	}
}
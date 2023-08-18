<?php
/**
 * @brief		Member filter extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		20 Apr 2015
 */

namespace IPS\nexus\extensions\core\MemberFilter;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member filter extension
 */
class _Purchases
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
		return array(
			new \IPS\Helpers\Form\Node( 'nexus_bm_filters_packages', isset( $criteria['nexus_bm_filters_packages'] ) ? array_filter( array_map( function( $val )
			{
				try
				{
					return \IPS\nexus\Package::load( $val );
				}
				catch ( \OutOfRangeException $e )
				{
					return NULL;
				}
			}, explode( ',', $criteria['nexus_bm_filters_packages'] ) ) ) : 0, FALSE, array( 'zeroVal' => 'nexus_bm_filters_packages_none', 'multiple' => TRUE, 'class' => 'IPS\nexus\Package\Group', 'zeroValTogglesOff' => array( 'nexus_bm_filters_pkg_active', 'nexus_bm_filters_pkg_expired', 'nexus_bm_filters_pkg_canceled' ), 'permissionCheck' => function( $node )
			{
				return !( $node instanceof \IPS\nexus\Package\Group );
			} ) ),
			new \IPS\Helpers\Form\CheckboxSet( 'nexus_bm_filters_type', isset( $criteria['nexus_bm_filters_type'] ) ? $criteria['nexus_bm_filters_type'] : array( 'active' ), FALSE, array( 'toggles' => array( 'expired' => array( 'nexus_bm_filters_pkg_expired' ) ), 'options' => array( 'active' => 'nexus_bm_filters_type_active', 'expired' => 'nexus_bm_filters_type_expired', 'canceled' => 'nexus_bm_filters_type_canceled' ) ), NULL, NULL, NULL, 'nexus_bm_filters_types' ),
			new \IPS\Helpers\Form\Custom( 'nexus_bm_filters_pkg_expired', array( 0 => isset( $criteria['nexus_bm_filters_pkg_expired']['range'] ) ? $criteria['nexus_bm_filters_pkg_expired']['range'] : '', 1 => isset( $criteria['nexus_bm_filters_pkg_expired']['days'] ) ? $criteria['nexus_bm_filters_pkg_expired']['days'] : NULL, 3 => isset( $criteria['nexus_bm_filters_pkg_expired']['days_lt'] ) ? $criteria['nexus_bm_filters_pkg_expired']['days_lt'] : NULL ), FALSE, array(
				'getHtml'	=> function( $element )
				{
					$dateRange = new \IPS\Helpers\Form\DateRange( "{$element->name}[0]", $element->value[0], FALSE );

					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->dateFilters( $dateRange, $element );
				}
			), NULL, NULL, NULL, 'nexus_bm_filters_pkg_expired' ),
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
		$return = array();

		if ( \is_array( $post['nexus_bm_filters_packages'] ) )
		{
			$ids = array();
			foreach ( $post['nexus_bm_filters_packages'] as $package )
			{
				$ids[] = $package->id;
			}

			$return['nexus_bm_filters_packages'] = implode( ',', $ids );
		}

		if( isset( $post['nexus_bm_filters_type'] ) )
		{
			$return['nexus_bm_filters_type']			= $post['nexus_bm_filters_type'];
			$return['nexus_bm_filters_pkg_active']		= (bool) ( \in_array( "active", $post['nexus_bm_filters_type'] ) );
			$return['nexus_bm_filters_pkg_canceled']	= (bool) ( \in_array( "canceled", $post['nexus_bm_filters_type'] ) );
			$return['nexus_bm_filters_pkg_expired']		= NULL;

			if( isset( $post['nexus_bm_filters_pkg_expired'][2] ) AND $post['nexus_bm_filters_pkg_expired'][2] == 'days' )
			{
				$return['nexus_bm_filters_pkg_expired'] = $post['nexus_bm_filters_pkg_expired'][1] ? array( 'days' => \intval( $post['nexus_bm_filters_pkg_expired'][1] ) ) : FALSE;
			}
			elseif( isset( $post['nexus_bm_filters_pkg_expired'][2] ) AND $post['nexus_bm_filters_pkg_expired'][2] == 'days_lt' )
			{
				$return['nexus_bm_filters_pkg_expired'] = $post['nexus_bm_filters_pkg_expired'][3] ? array( 'days_lt' => \intval( $post['nexus_bm_filters_pkg_expired'][3] ) ) : FALSE;
			}
			elseif( isset( $post['nexus_bm_filters_pkg_expired'][2] ) AND $post['nexus_bm_filters_pkg_expired'][2] == 'range' )
			{
				$return['nexus_bm_filters_pkg_expired'] = array( 'range' => json_decode( json_encode( $post['nexus_bm_filters_pkg_expired'][0] ), TRUE ) );
			}
			else
			{
				$return['nexus_bm_filters_pkg_expired'] = FALSE;
			}
		}
		
		return \count( $return ) ? $return : FALSE;
	}
	
	/**
	 * Get where clause to add to the member retrieval database query
	 *
	 * @param	mixed				$data	The array returned from the save() method
	 * @return	array|NULL			Where clause - must be a single array( "clause" )
	 */
	public function getQueryWhereClause( $data )
	{
		if ( isset( $data['nexus_bm_filters_packages'] ) and $data['nexus_bm_filters_packages'] )
		{
			return array( 'purchase.ps_id IS NOT NULL' );
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
		if ( isset( $data['nexus_bm_filters_packages'] ) and $data['nexus_bm_filters_packages'] )
		{
			$types = array();

			if ( isset($data[ 'nexus_bm_filters_pkg_active' ]) and $data[ 'nexus_bm_filters_pkg_active' ] )
			{
				$types[] = 'purchase.ps_active=1';
			}

			if ( isset( $data[ 'nexus_bm_filters_pkg_canceled' ] ) and $data[ 'nexus_bm_filters_pkg_canceled' ] )
			{
				$types[] = 'purchase.ps_cancelled=1';
			}

			if ( !empty( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ] ) AND !empty( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ][ 'end' ] ) )
			{
				$start = ( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ][ 'start' ] ) ? new \IPS\DateTime( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ][ 'start' ] ) : NULL;
				$end = ( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ][ 'end' ] ) ? new \IPS\DateTime( $data[ 'nexus_bm_filters_pkg_expired' ][ 'range' ][ 'end' ] ) : NULL;

				if ( $start and $end )
				{
					$types[] = "( purchase.ps_active=0 AND purchase.ps_cancelled=0 AND purchase.ps_expire BETWEEN {$start->getTimestamp()} AND {$end->getTimestamp()})";
				}
			}
			elseif ( !empty( $data[ 'nexus_bm_filters_pkg_expired' ][ 'days' ] ) )
			{
				$date = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $data[ 'nexus_bm_filters_pkg_expired' ][ 'days' ] . 'D' ) );
				$types[] = "( purchase.ps_active=0 AND purchase.ps_cancelled=0 AND purchase.ps_expire < {$date->getTimestamp()})";
			}
			elseif( !empty( $data[ 'nexus_bm_filters_pkg_expired' ][ 'days_lt' ] ) AND (int) $data[ 'nexus_bm_filters_pkg_expired' ][ 'days_lt' ] )
			{
				$date = \IPS\DateTime::create()->sub( new \DateInterval( 'P' . (int) $data[ 'nexus_bm_filters_pkg_expired' ][ 'days_lt' ] . 'D' ) );

				$types[] = "( purchase.ps_active=0 AND purchase.ps_cancelled=0 AND purchase.ps_expire > {$date->getTimestamp()})";
			}
			elseif( isset( $data['nexus_bm_filters_type'] ) AND \in_array( 'expired', $data['nexus_bm_filters_type'] ) )
			{
				$types[] = "(purchase.ps_expire < " . time() . ")";
			}
			
			if ( !empty( $types ) )
			{
				$types = implode( ' OR ', $types );
			}

			$query->join( ['nexus_purchases', 'purchase'], "purchase.ps_app='nexus' AND purchase.ps_type='package' AND purchase.ps_member=core_members.member_id AND " . \IPS\Db::i()->in( 'purchase.ps_item_id', explode( ',', $data['nexus_bm_filters_packages'] ) ) . ( $types ? " AND ( {$types} )" : '' ) );
		}
	}
}
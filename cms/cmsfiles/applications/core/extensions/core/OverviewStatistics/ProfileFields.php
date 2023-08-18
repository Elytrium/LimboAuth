<?php
/**
 * @brief		Overview statistics extension: ProfileFields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Apr 2020
 */

namespace IPS\core\extensions\core\OverviewStatistics;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Overview statistics extension: ProfileFields
 */
class _ProfileFields
{
	/**
	 * @brief	Which statistics page (activity or user)
	 */
	public $page	= 'user';

	/**
	 * Return the sub-block keys
	 *
	 * @note This is designed to allow one class to support multiple blocks, for instance using the ContentRouter to generate blocks.
	 * @return array
	 */
	public function getBlocks()
	{
		$return = array();

		foreach ( \IPS\core\ProfileFields\Field::fieldData() as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				if( \in_array( $field['pf_type'], array( 'Select', 'Radio', 'Checkbox', 'Rating', 'YesNo' ) ) AND !$field['pf_multiple'] )
				{
					$return[] = $id;
				}
			}
		}

		return $return;
	}

	/**
	 * Return block details (title and description)
	 *
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	array
	 */
	public function getBlockDetails( $subBlock = NULL )
	{
		foreach ( \IPS\core\ProfileFields\Field::fieldData() as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				if( $id == $subBlock )
				{
					return array( 'app' => 'core', 'title' => "core_pfield_{$id}", 'description' => 'stats_overview_pfields', 'refresh' => 60 );
				}
			}
		}
		
		return array();
	}

	/** 
	 * Return the block HTML to show
	 *
	 * @param	array|NULL	$dateRange	NULL for all time, or an array with 'start' and 'end' \IPS\DateTime objects to restrict to
	 * @param	string|NULL	$subBlock	The subblock we are loading as returned by getBlocks()
	 * @return	string
	 */
	public function getBlock( $dateRange = NULL, $subBlock = NULL )
	{
		foreach ( \IPS\core\ProfileFields\Field::fieldData() as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				if( $id == $subBlock )
				{
					return $this->_getChart( $field );
				}
			}
		}

		return '';
	}

	/**
	 * Return the HTML to display for this block
	 *
	 * @param	array	$field	Field data
	 * @return	string
	 */
	protected function _getChart( $field )
	{
		/* Init Chart */
		$pieBarData = array();
		$results	= array();
		$limit = 15;
		
		/* Add Rows */
		$select	= \IPS\Db::i()->select( 'COUNT(*) as total, field_' . $field['pf_id'], 'core_pfields_content', NULL, 'total DESC', NULL, 'field_' . $field['pf_id'] );
		$total	= 0;

		$i = 0;
		foreach( $select as $row )
		{
			if( $row['field_' . $field['pf_id'] ] !== NULL )
			{
				$total += $row['total'];

				if( $i <= $limit )
				{
					$results[ $row['field_' . $field['pf_id'] ] ] = $row;
				}
				else
				{
					$otherKey = \IPS\Member::loggedIn()->language()->addToStack('stats_overview_others');
					$results[$otherKey] = [ 'total' => 5 ];
				}

				$i++;
			}
		}

		if( $field['pf_type'] == 'Checkbox' )
		{
			$pieBarData[] = array(
				'name' =>  \IPS\Member::loggedIn()->language()->addToStack('stats_pfields__unchecked'),
				'value' => isset( $results[0] ) ? $results[0]['total'] : 0,
				'percentage' => ( isset( $results[0] ) AND $results[0]['total'] > 0 ) ? round( ( $results[0]['total'] / $total ) * 100, 2 ) : 0
			);

			$pieBarData[] = array(
				'name' =>  \IPS\Member::loggedIn()->language()->addToStack('stats_pfields__checked'),
				'value' => isset( $results[1] ) ? $results[1]['total'] : 0,
				'percentage' => ( isset( $results[1] ) AND $results[0]['total'] > 0 ) ? round( ( $results[1]['total'] / $total ) * 100, 2 ) : 0
			);
		}
		elseif( $field['pf_type'] == 'YesNo' )
		{
			$pieBarData[] = array(
				'name' =>  \IPS\Member::loggedIn()->language()->addToStack('stats_pfields__no'),
				'value' => isset( $results[0] ) ? $results[0]['total'] : 0,
				'percentage' => ( isset( $results[0] ) AND $results[0]['total'] > 0 ) ? round( ( $results[0]['total'] / $total ) * 100, 2 ) : 0
			);

			$pieBarData[] = array(
				'name' =>  \IPS\Member::loggedIn()->language()->addToStack('stats_pfields__yes'),
				'value' => isset( $results[1] ) ? $results[1]['total'] : 0,
				'percentage' => ( isset( $results[1] ) AND $results[1]['total'] > 0 ) ? round( ( $results[1]['total'] / $total ) * 100, 2 ) : 0
			);
		}
		elseif( $field['pf_type'] == 'Rating' )
		{
			foreach( range( 1, 5 ) as $step )
			{
				$pieBarData[] = array(
					'name' =>  $step,
					'nameRaw' => str_repeat( "<i class='fa fa-star'></i>", $step ),
					'value' => isset( $results[$step] ) ? $results[$step]['total'] : 0,
					'percentage' => ( isset( $results[$step] ) AND $results[$step]['total'] > 0 ) ? round( ( $results[$step]['total'] / $total ) * 100, 2 ) : 0
				);
			}
		}
		elseif( $field['pf_content'] )
		{
			$options	= json_decode( $field['pf_content'], TRUE );
			$options = array_keys( $results);

			foreach( $options as $k => $v )
			{
				$resultValue = ( isset( $results[ $v ] ) ) ? $results[ $v ]['total'] : ( isset( $results[ $k ] ) ? $results[ $k ]['total'] : 0 );

				if( isset( $resultValue ) )
				{
					$percentage = $resultValue > 0 ? round( ( $resultValue / $total ) * 100, 2 ) : 0;

					$pieBarData[] = array(
						'name' =>  $v,
						'tooltip'	=> $v . ": " . $resultValue . ' (' . $percentage . '%)',
						'value' => $resultValue,
						'percentage' => $percentage
					);
				}
				else
				{
					$pieBarData[] = array(
						'name' =>  $v,
						'tooltip' => $v . ": 0 (0%)",
						'value' => 0,
						'percentage' => 0
					);
				}
			}
		}

		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global'  )->applePieChart( $pieBarData );
	}
}
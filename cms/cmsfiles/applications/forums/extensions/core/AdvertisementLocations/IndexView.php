<?php
/**
 * @brief		Advertisement locations extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		16 Jul 2014
 */

namespace IPS\forums\extensions\core\AdvertisementLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Advertisement locations extension
 */
class _IndexView
{
	/** 
	 * Get the locations and the additional settings
	 *
	 * @param	array	$settings	Current setting values
	 * @return	array	Array with two elements: 'locations' which should have keys as the location keys and values as the fields to toggle, and 'settings' which are additional fields to add to the form
	 */
	public function getSettings( $settings )
	{
		return array(
			'locations' => array( 'ad_fluid_index_view' => array( 'ad_fluid_index_view_number', 'ad_fluid_index_view_repeat' ) ),
			'settings'  => array(
				/* Some communities may already have a 0 stored for `ad_fluid_index_view_number`, we now need to make sure it's at least 1 */
				new \IPS\Helpers\Form\Number( 'ad_fluid_index_view_number', ( isset( $settings['ad_fluid_index_view_number'] ) AND $settings['ad_fluid_index_view_number'] ) ? $settings['ad_fluid_index_view_number'] : 1, FALSE, array( 'min' => 1 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('ad_fluid_index_view_number_suffix'), 'ad_fluid_index_view_number' ),
				new \IPS\Helpers\Form\Number( 'ad_fluid_index_view_repeat', ( isset( $settings['ad_fluid_index_view_repeat'] ) ) ? $settings['ad_fluid_index_view_repeat'] : 0, FALSE, array( 'unlimited' => -1 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('ad_fluid_index_view_repeat_suffix'), 'ad_fluid_index_view_repeat' )
			)
		);
	}

	/** 
	 * Return an array of setting values to store
	 *
	 * @param	array	$values	Values from the form submission
	 * @return	array 	Array of setting key => value to store
	 */
	public function parseSettings( $values )
	{
		return array(
			'ad_fluid_index_view_number' => $values['ad_fluid_index_view_number'],
			'ad_fluid_index_view_repeat' => $values['ad_fluid_index_view_repeat']
		);
	}
}
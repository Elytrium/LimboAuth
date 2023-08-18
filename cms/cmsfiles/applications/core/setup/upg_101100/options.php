<?php
/**
 * @brief		Upgrader: Custom Upgrade Options
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		9 Feb 2017
 */

$options = array();

if( \IPS\Image::canWriteText() )
{
	\IPS\Member::loggedIn()->language()->words['101100_letter_photos'] = \IPS\Member::loggedIn()->language()->addToStack( 'options_letter_photos' );
	\IPS\Member::loggedIn()->language()->words['101100_letter_photos_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'options_letter_photos_desc' );
	$options[] = new \IPS\Helpers\Form\YesNo( '101100_letter_photos', TRUE );
}
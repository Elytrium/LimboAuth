<?php
/**
 * @brief        Upgrader: Giphy Default Key deprecation
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * @since        2 June 2023
 */
if( \IPS\Settings::i()->giphy_enabled AND \IPS\Settings::i()->giphy_apikey == 'N74I5NtWXp00t2lOwOQn3XunASrJ9wLV')
{
	$message = "The Giphy Default Key setting has been deprecated. Please visit the Giphy API page to obtain a new key.";
	$message = \IPS\Theme::i()->getTemplate( 'global' )->block( NULL, $message );
}

<?php
/**
 * @brief		Upgrader: Custom Post Upgrade Message
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 May 2016
 */

if ( \IPS\Application::appIsEnabled('cms' ) )
{
	$message = \IPS\Theme::i()->getTemplate( 'global' )->block( NULL, "Please check any custom moderator permissions (ACP -> Members -> Moderators) for Pages Database categories. These have been reset to 'All Categories'." );
}	

<?php
/**
 * @brief		Service worker output
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Feb 2021
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Service worker controller
 */
class _serviceworker extends \IPS\Dispatcher\Controller
{	
	/**
	 * View Notifications
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$cachedUrls = array();
		$cachedUrls[] = (string) \IPS\Http\Url::internal("app=core&module=system&controller=offline", "front", "user_offline");

		$notificationIcon = NULL;

		/* Get an icon to use in notifications */
		$homeScreenIcons = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) ?? array();
		
		if( \count( $homeScreenIcons ) )
		{
			foreach( $homeScreenIcons as $name => $image )
			{
				if( isset( $image['width'] ) and $image['width'] == 192 )
				{
					$notificationIcon = \IPS\File::get( 'core_Icons', $image['url'] )->url;
					break;
				}
			}
		}

		/* VARIABLES TO PASS THROUGH TO JS */
		$DEBUG = ( ( \IPS\IN_DEV and \IPS\DEV_DEBUG_JS ) or \IPS\DEBUG_JS ) ? 'true' : 'false'; // Weird casting is intentional.
		$BASE_URL = \IPS\Settings::i()->base_url;
		$CACHED_ASSETS = json_encode( $cachedUrls, JSON_UNESCAPED_SLASHES );
		$OFFLINE_URL = (string) \IPS\Http\Url::internal("app=core&module=system&controller=offline", "front", "user_offline");
		$CACHE_VERSION = \IPS\Theme::i()->cssCacheBustKey();
		$NOTIFICATION_ICON = $notificationIcon ? "\"{$notificationIcon}\"" : 'null'; // Weird casting is intentional. 'null' will become literal null in JS file.
		$DEFAULT_NOTIFICATION_TITLE = \IPS\Member::loggedIn()->language()->addToStack('default_notification_title');
		$DEFAULT_NOTIFICATION_BODY = \IPS\Member::loggedIn()->language()->addToStack('default_notification_body');

		$output = <<<JAVASCRIPT
"use strict";
const DEBUG = {$DEBUG};
const BASE_URL = "{$BASE_URL}";
const CACHED_ASSETS = {$CACHED_ASSETS};
const CACHE_NAME = 'invision-community-{$CACHE_VERSION}';
const OFFLINE_URL = "{$OFFLINE_URL}";
const NOTIFICATION_ICON = {$NOTIFICATION_ICON};
const DEFAULT_NOTIFICATION_TITLE = "{$DEFAULT_NOTIFICATION_TITLE}";
const DEFAULT_NOTIFICATION_BODY = "{$DEFAULT_NOTIFICATION_BODY}";

JAVASCRIPT;

		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );
		$output .= file_get_contents( \IPS\ROOT_PATH . '/applications/core/interface/js/serviceWorker.js' );
		$cacheHeaders	= \IPS\IN_DEV !== true ? \IPS\Output::getCacheHeaders(time(), 86400) : array();
		\IPS\Output::i()->sendOutput($output, 200, 'text/javascript', $cacheHeaders);
	}
}
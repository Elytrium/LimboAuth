<?php
/**
 * @brief		Front-end Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front-end Dispatcher
 */
class _Front extends \IPS\Dispatcher\Standard
{
	/**
	 * Controller Location
	 */
	public $controllerLocation = 'front';

	/**
	 * Init
	 *
	 * @return    void
	 * @throws \Exception
	 */
	public function init()
	{
		/* Set up in progress? */
		if ( isset( \IPS\Settings::i()->setup_in_progress ) AND \IPS\Settings::i()->setup_in_progress )
		{
			$protocol = '1.0';
			if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
			{
				$protocol = '1.1';
			}

			/* Don't allow the setup in progress page to be cached, it will only be displayed for a very short period of time */
			foreach( \IPS\Output::getNoCacheHeaders() as $headerKey => $headerValue )
			{
				header( "{$headerKey}: {$headerValue}" );
			}
			
			if ( \IPS\CIC and ! \IPS\Session\Front::loggedIn() and ! \IPS\Session\Front::i()->userAgent->spider )
			{
				/* The software is unavailable, but the site is up so we do not want to affect our cloud downtime statistics and trigger monitoring alarms
				   if we are not a search engine */
				header( "HTTP/{$protocol} 200 OK" );
			}
			else
			{
				header( "HTTP/{$protocol} 503 Service Unavailable" );
				header( "Retry-After: 300"); #5 minutes
			}
					
			require \IPS\ROOT_PATH . '/' . \IPS\UPGRADING_PAGE;
			exit;
		}

		/* Sync stuff when in developer mode */
		if ( \IPS\IN_DEV )
		{
			 \IPS\Developer::sync();
		}
		
		/* Base CSS */
		static::baseCss();

		/* Base JS */
		static::baseJs();

		/* Perform some legacy URL conversions - Need to do this before checking furl in case app name has changed */
		static::convertLegacyParameters();

		/* Check friendly URL and whether it is correct */
		try
		{
			$this->checkUrl();
		}
		catch( \OutOfRangeException $e )
		{
			/* Display a 404 */
			$this->application = \IPS\Application::load('core');
			$this->setDefaultModule();
			if ( \IPS\Member::loggedIn()->isBanned() )
			{
				\IPS\Output::i()->sidebar = FALSE;
				\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			}
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'app.js' ) );
			\IPS\Output::i()->error( 'requested_route_404', '1S160/2', 404, '' );
		}

		/* Run global init */
		try
		{
			parent::init();
		}
		catch ( \DomainException $e )
		{
			// If this is a "no permission", and they're validating - show the validating screen instead
			if( $e->getCode() === 6 and \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->members_bitoptions['validating'] )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating', 'front', 'register' ) );
			}
			// Otherwise show the error
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '2S100/' . $e->getCode(), $e->getCode() === 4 ? 403 : 404, '' );
			}
		}

		$this->_setReferralCookie();
		
		/* Enable sidebar by default (controllers can turn it off if needed) */
		\IPS\Output::i()->sidebar['enabled'] = ( \IPS\Request::i()->isAjax() ) ? FALSE : TRUE;
		
		/* Add in RSS Feeds */
		foreach( \IPS\core\Rss::getStore() AS $feed_id => $feed )
		{
			$feed = \IPS\core\Rss::constructFromData( $feed );

			if ( $feed->_enabled AND ( $feed->groups == '*' OR \IPS\Member::loggedIn()->inGroup( $feed->groups ) ) )
			{
				\IPS\Output::i()->rssFeeds[ $feed->_title ] = $feed->url();
			}
		}
		
		/* Are we online? */
		if ( !\IPS\Settings::i()->site_online and !\IPS\Member::loggedIn()->group['g_access_offline'] and $this->controllerLocation == 'front' and !$this->application->allowOfflineAccess( $this->module, $this->controller, \IPS\Request::i()->do ) )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( \IPS\Member::loggedIn()->language()->addToStack( 'offline_unavailable', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->board_name ) ) ), 503 );
			}
			
			\IPS\Output::i()->showOffline();
		}
		
		/* Member Ban? */

		/* IP Ban check happens only the Login and Register Controller for guests */
		$ipBanned = FALSE;
		if( \IPS\Member::loggedIn()->member_id OR \in_array( $this->controller, array( 'register', 'login' ) ) )
		{
			$ipBanned = \IPS\Request::i()->ipAddressIsBanned();
		}

		if ( $ipBanned or $banEnd = \IPS\Member::loggedIn()->isBanned() )
		{
			if ( !$ipBanned and !\IPS\Member::loggedIn()->member_id )
			{
				if ( $this->notAllowedBannedPage() )
				{
					$url = \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' );
					
					if ( \IPS\Request::i()->url() != \IPS\Settings::i()->base_url AND !isset( \IPS\Request::i()->_mfaLogin ) )
					{
						$url = $url->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
					}
					else if ( isset( \IPS\Request::i()->_mfaLogin ) )
					{
						$url = $url->setQueryString( '_mfaLogin', 1 );
					}
					
					\IPS\Output::i()->redirect( $url );
				}
			}
			else
			{
				\IPS\Output::i()->sidebar = FALSE;
				\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
				if( !\in_array( $this->controller, array( 'contact', 'warnings', 'privacy', 'guidelines', 'metatags' ) ) )
				{
					\IPS\Output::i()->showBanned();
				}
			}
		}
		
		/* Do we need more info from the member or do they need to validate? */

		/* These controllers should always be accessible, no matter if the member is awaiting validation or needs to set up the email or name */
		$legalControllers = array( 'privacy', 'contact', 'terms', 'embed', 'metatags', 'subscriptions', 'serviceworker', 'settings' );

		/* Do we need more info from the member or do they need to validate? */
		if( \IPS\Member::loggedIn()->member_id and $this->controller !== 'language' and $this->controller !== 'theme' and $this->controller !== 'ajax' and !\in_array( $this->controller, $legalControllers ) )
		{
			if ( $url = static::doMemberCheck() )
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
		
		/* Permission Check */
		try
		{
			if ( !\IPS\Member::loggedIn()->canAccessModule( $this->module ) )
			{
				if ( !\IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->_mfaLogin ) )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' )->setQueryString( '_mfaLogin', 1 ) );
				}
				\IPS\Output::i()->error( ( \IPS\Member::loggedIn()->member_id ? 'no_module_permission' : 'no_module_permission_guest' ), '2S100/2', 403, 'no_module_permission_admin' );
			}
		}
		catch( \InvalidArgumentException $e ) # invalid module
		{
			\IPS\Output::i()->error( 'requested_route_404', '2S160/5', 404, '' );
		}

		/* Set up isAnonymous variable for realtime */
		\IPS\Output::i()->jsVars['isAnonymous'] = (bool) \IPS\Member::loggedIn()->isOnlineAnonymously();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			/* Base Navigation. We only add the module not the app as most apps don't have a global base (for example, in Nexus, you want "Store" or "Client Area" to be the base). Apps can override themselves in their controllers. */
			foreach( \IPS\Application::applications() as $directory => $application )
			{
				if( $application->default )
				{
					$defaultApplication	= $directory;
					break;
				}
			}

			if( !isset( $defaultApplication ) )
			{
				$defaultApplication = 'core';
			}
			
			if ( $this->module->key != 'system' AND $this->application->directory != $defaultApplication )
			{
				\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( 'app=' . $this->application->directory . '&module=' . $this->module->key . '&controller=' . $this->module->default_controller, 'front', array_key_exists( $this->module->key, \IPS\Http\Url::furlDefinition() ) ?  $this->module->key : NULL ), $this->module->_title );
			}

			/* Figure out what the global search is */
			foreach ( $this->application->extensions( 'core', 'ContentRouter' ) as $object )
			{
				if ( \count( $object->classes ) === 1 )
				{
					$classes = $object->classes;
					foreach ( $classes as $class )
					{
						if ( is_subclass_of( $class, 'IPS\Content\Searchable' ) and $class::includeInSiteSearch() and $this->module->key == $class::$module )
						{
							$type = mb_strtolower( str_replace( '\\', '_', mb_substr( array_pop( $classes ), 4 ) ) );
							
							/* If not the default app, set default search option to current app */
							if ( ! mb_stristr( $type, $defaultApplication ) )
							{
								\IPS\Output::i()->defaultSearchOption = array( $type, "{$type}_pl" );
							}
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Set the referral cookie if appropriate
	 *
	 * @return void
	 */
	protected function _setReferralCookie()
	{
		/* Set a referral cookie */
		if( \IPS\Settings::i()->ref_on and isset( \IPS\Request::i()->_rid ) )
		{
			\IPS\Request::i()->setCookie( 'referred_by', \intval( \IPS\Request::i()->_rid ), \IPS\DateTime::create()->add( new \DateInterval( 'P1Y' ) ) );
		}
	}

	/**
	  * Check whether the URL we visited is correct and route appropriately
	  *
	  * @return void
	  */
	protected function checkUrl()
	{
		/* Handle friendly URLs */
		if ( \IPS\Settings::i()->use_friendly_urls )
		{
			$url = \IPS\Request::i()->url();

			/* Redirect to the "correct" friendly URL if there is one */
			if ( !\IPS\Request::i()->isAjax() and mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' and !\IPS\ENFORCE_ACCESS )
			{
				$correctUrl = NULL;
				
				/* If it's already a friendly URL, we need to check the SEO title is valid. If it isn't, we redirect iof "Force Friendly URLs" is enabled */
				if ( $url instanceof \IPS\Http\Url\Friendly or ( $url instanceof \IPS\Http\Url\Internal and \IPS\Settings::i()->seo_r_on ) )
				{
					$correctUrl = $url->correctFriendlyUrl();
				}
				

				if ( !( $correctUrl instanceof \IPS\Http\Url ) and $url instanceof \IPS\Http\Url\Internal )
				{
					$pathFromBaseUrl = ltrim( mb_substr( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], mb_strlen( \IPS\Http\Url::internal('')->data[ \IPS\Http\Url::COMPONENT_PATH ] ) ), '/' );

					/* If they are accessing "index.php/whatever", we want "index.php?/whatever */
					if ( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], '/index.php/' ) !== FALSE )
					{
						if ( mb_substr( $pathFromBaseUrl, 0, 10 ) === 'index.php/' )
						{
							$correctUrl = \IPS\Http\Url\Friendly::friendlyUrlFromComponent( 0, trim( mb_substr( $pathFromBaseUrl, 10 ), '/' ), $url->queryString );
						}
					}
					else
					{
						/* If necessary, return any special cases like the robots.txt file */
						$this->customResponse( $pathFromBaseUrl );
					}
				}

				/* Redirect to the correct URL if we got one */
				if ( $correctUrl instanceof \IPS\Http\Url )
				{
					if( $correctUrl->seoPagination and \in_array( 'page', array_keys( $url->hiddenQueryString ) ) )
					{
						$correctUrl = $correctUrl->setPage( 'page', $url->hiddenQueryString['page'] );
					}
					\IPS\Output::i()->redirect( $correctUrl, NULL, 301 );
				}

				/* Check pagination */
				if ( $url instanceof \IPS\Http\Url\Friendly and $url->seoPagination and \in_array( 'page', array_keys( $url->queryString ) ) )
				{
					\IPS\Output::i()->redirect( $url->setPage( 'page', $url->queryString['page'] )->stripQueryString('page'), NULL, 301 );
				}
			}
			
			/* If the accessed URL is friendly, set the "real" query string properties */
			if ( $url instanceof \IPS\Http\Url\Friendly )
			{
				foreach ( ( $url->queryString + $url->hiddenQueryString ) as $k => $v )
				{
					if( $k == 'module' )
					{
						$this->_module	= NULL;
					}
					else if( $k == 'controller' )
					{
						$this->_controller	= NULL;
					}
					
					/* If this is a POST request, and this key has already been populated, do not overwrite it as this allows form input to be ignored and the query string data used */
					if ( \IPS\Request::i()->requestMethod() == 'POST' and isset( \IPS\Request::i()->$k ) )
					{
						continue;
					}
					
					\IPS\Request::i()->$k = $v;
				}
			}
			/* Otherwise if it's not a recognised URL, show a 404 */
			elseif ( !( $url instanceof \IPS\Http\Url\Internal ) or $url->base !== 'front' )
			{
				/* Call the parent first in case we need to redirect to https, and so the correct locale, etc. is set */
				try
				{
					parent::init();
				}
				catch ( \Exception $e ) { }
				
				throw new \OutOfRangeException;
			}
		}
	}

	/**
	 * Define that the page should load even if the user is banned and not logged in
	 *
	 * @return	bool
	 */
	protected function notAllowedBannedPage()
	{
		return !\IPS\Member::loggedIn()->group['g_view_board'] and !$this->application->allowGuestAccess( $this->module, $this->controller, \IPS\Request::i()->do );
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public static function convertLegacyParameters()
	{
		foreach( \IPS\Application::applications() as $directory => $application )
		{
			if ( $application->_enabled )
			{
				if( method_exists( $application, 'convertLegacyParameters' ) )
				{
					$application->convertLegacyParameters();
				}
			}
		}
	}

	/**
	 * Finish
	 *
	 * @return	void
	 */
	public function finish()
	{
		/* Sidebar Widgets */
		if( !\IPS\Request::i()->isAjax() )
		{
			$widgets = array();
			
			if ( ! isset( \IPS\Output::i()->sidebar['widgets'] ) OR ! \is_array( \IPS\Output::i()->sidebar['widgets'] ) )
			{
				\IPS\Output::i()->sidebar['widgets'] = array();
			}

			try
			{
				$widgetConfig = \IPS\Db::i()->select( '*', 'core_widget_areas', array( 'app=? AND module=? AND controller=?', $this->application->directory, $this->module->key, $this->controller ) );
				foreach( $widgetConfig as $area )
				{
					$widgets[ $area['area'] ] = json_decode( $area['widgets'], TRUE );
				}
			}
			catch ( \UnderflowException $e ) {}
			
			if ( \IPS\Output::i()->allowDefaultWidgets )
			{
				foreach( \IPS\Widget::appDefaults( $this->application ) as $widget )
				{
					/* If another app has already defined this area, don't overwrite it */
					if ( isset( $widgets[ $widget['default_area'] ] ) )
					{
						continue;
					}
	
					$widget['unique']	= $widget['key'];
					
					$widgets[ $widget['default_area'] ][] = $widget;
				}
			}
					
			if( \count( $widgets ) )
			{
				if ( ( \IPS\Data\Cache::i() instanceof \IPS\Data\Cache\None ) and ! \IPS\Theme::isUsingTemplateDiskCache() )
				{
					$templateLoad = array();
					foreach ( $widgets as $areaKey => $area )
					{
						foreach ( $area as $widget )
						{
							if ( isset( $widget['app'] ) and $widget['app'] )
							{
								$templateLoad[] = array( $widget['app'], 'front', 'widgets' );
								$templateLoad[] = 'template_' . \IPS\Theme::i()->id . '_' . \IPS\Theme::makeBuiltTemplateLookupHash( $widget['app'], 'front', 'widgets' ) . '_widgets';
							}
						}
					}
	
					if( \count( $templateLoad ) )
					{
						\IPS\Data\Store::i()->loadIntoMemory( $templateLoad );
					}
				}
				
				$widgetObjects = array();
				$storeLoad = array();
				$googleFonts = array();
				foreach ( $widgets as $areaKey => $area )
				{
					foreach ( $area as $widget )
					{
						try
						{
							$appOrPlugin = isset( $widget['plugin'] ) ? \IPS\Plugin::load( $widget['plugin'] ) : \IPS\Application::load( $widget['app'] );

							if( !$appOrPlugin->enabled )
							{
								continue;
							}
							
							$_widget = \IPS\Widget::load( $appOrPlugin, $widget['key'], ( ! empty($widget['unique'] ) ? $widget['unique'] : mt_rand() ), ( isset( $widget['configuration'] ) ) ? $widget['configuration'] : array(), ( isset( $widget['restrict'] ) ? $widget['restrict'] : null ), ( $areaKey == 'sidebar' ) ? 'vertical' : 'horizontal' );
							if ( ( \IPS\Data\Cache::i() instanceof \IPS\Data\Cache\None ) and isset( $_widget->cacheKey ) )
							{
								$storeLoad[] = $_widget->cacheKey;
							}

							if ( \in_array( 'IPS\Widget\Builder', class_implements( $_widget ) ) )
							{
								if ( ! empty( $_widget->configuration['widget_adv__font'] ) and $_widget->configuration['widget_adv__font'] !== 'inherit' )
								{
									$font = $_widget->configuration['widget_adv__font'];

									if ( \mb_substr( $font, -6 ) === ' black' )
									{
										$fontWeight = 900;
										$font = \mb_substr( $font, 0, -6 ) . ':400,900';
									}

									$googleFonts[ $font ] = $font;
								}
							}

							$widgetObjects[ $areaKey ][] = $_widget;
						}
						catch ( \Exception $e )
						{
							\IPS\Log::log( $e, 'dispatcher' );
						}
					}
				}

				if ( \count( $googleFonts ) )
				{
					\IPS\Output::i()->linkTags['googlefonts'] = array('rel' => 'stylesheet', 'href' => "https://fonts.googleapis.com/css?family=" . implode( "|", array_values( $googleFonts ) ) . "&display=swap");
				}

				if( ( \IPS\Data\Cache::i() instanceof \IPS\Data\Cache\None ) and \count( $storeLoad ) )
				{
					\IPS\Data\Store::i()->loadIntoMemory( $storeLoad );
				}
				
				foreach ( $widgetObjects as $areaKey => $_widgets )
				{
					foreach ( $_widgets as $_widget )
					{
						\IPS\Output::i()->sidebar['widgets'][ $areaKey ][] = $_widget;
					}
				}
			}
		}

		/* Do things if we're actively using the easy theme editor */
		\IPS\Theme::easyModePreOutput();

		/* Meta tags */
		\IPS\Output::i()->buildMetaTags();

		/* Check MFA */
		$this->checkMfa();

		/* Check Alerts */
		$this->checkAlerts();
		
		/* Finish */
		parent::finish();
	}

	/**
	 * Check MFA to see if we need to supply a code. If the member elected to cancel, cancel (and redirect) here
	 *
	 * @param	boolean	$return	Return any HTML (true) or add to Output (false)
	 *
	 * @return void
	 */
	public function checkMfa( $return=FALSE )
	{
		/* MFA Login? */
		if ( isset( \IPS\Request::i()->_mfaLogin ) and isset( $_SESSION['processing2FA'] ) and $member = \IPS\Member::load( $_SESSION['processing2FA']['memberId'] ) and $member->member_id )
		{
			$device = \IPS\Member\Device::loadOrCreate( $member, FALSE );
			if ( $output = \IPS\MFA\MFAHandler::accessToArea( 'core', $device->known ? 'AuthenticateFrontKnown' : 'AuthenticateFront', \IPS\Request::i()->url(), $member ) )
			{
				/* Did we just cancel? */
				if ( \IPS\Request::i()->_mfaCancel and ( ! \IPS\Member::loggedIn()->member_id or ( \IPS\Member::loggedIn()->member_id === $member->member_id ) ) )
				{
					/* We don't need this until we re-enter the MFA flow again */
					unset( $_SESSION['processing2FA'] );

					/* Is MFA required for this member? */
					$mfaRequired = \IPS\Settings::i()->mfa_required_groups === '*' or $member->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) );

					/* Does this member require MFA upon login? */
					$logout = \IPS\Settings::i()->mfa_required_prompt === 'immediate' and $mfaRequired;

					/* Can they see this page without MFA? */
					if ( !$mfaRequired OR ( $logout and $this->application->allowGuestAccess( $this->module, $this->controller, \IPS\Request::i()->do ) ) )
					{
						$redirectUrl = \IPS\Request::i()->url()->stripQueryString([ '_mfaCancel', '_mfaLogin', '_fromLogin', 'csrfKey' ]);
					}
					else
					{
						$redirectUrl = \IPS\Http\Url::internal( '' );
					}

					if ( $logout )
					{
						\IPS\Login::logout( $redirectUrl );
						$redirectUrl = $redirectUrl->setQueryString( '_fromLogout', 1 );
					}

					\IPS\Output::i()->redirect( $redirectUrl );
				}

				if ( $return )
				{
					return $output;
				}
				
				\IPS\Output::i()->output .= $output;
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login&do=mfa', 'front', 'login' ) );
			}
		}
	}

	/**
	 *  Show a robots.txt file if configured to do so
	 *
	 * @param string $pathFromBaseUrl
	 */
	public function customResponse( string $pathFromBaseUrl )
	{
		if ( $pathFromBaseUrl === 'robots.txt' )
		{
			$this->robotsTxt();
		}
		else if ( \IPS\core\IndexNow::i()->isEnabled() AND $pathFromBaseUrl === \IPS\core\IndexNow::i()->getKeyFileName() )
		{
			$this->indexNow();
		}
	}

	/**
	 * Return the IndexNow key.
	 *
	 * @return mixed
	 */
	protected function indexNow()
	{
		\IPS\Output::i()->sendOutput( \IPS\core\IndexNow::i()->getKeyfileContent(), 200, 'text/plain' );
	}

	/**
	 * Return the robots.txt files
	 *
	 * @return mixed
	 */
	protected function robotsTxt()
	{
		if ( \IPS\Settings::i()->robots_txt == 'default' )
		{
			\IPS\Output::i()->sendOutput( static::robotsTxtRules(), 200, 'text/plain' );
		}
		else if ( \IPS\Settings::i()->robots_txt != 'off' )
		{
			\IPS\Output::i()->sendOutput( \IPS\Settings::i()->robots_txt, 200, 'text/plain' );
		}
		throw new \OutOfRangeException;
	}

	/**
	 * Return the text for the robots.txt file
	 *
	 * @return string
	 */
	public static function robotsTxtRules(): string
	{
		$path = str_replace( '//', '/', '/' . trim( str_replace( 'robots.txt', '', \IPS\Http\Url::createFromString( \IPS\Http\Url::baseUrl() )->data[ \IPS\Http\Url::COMPONENT_PATH ] ), '/' ) . '/' );
		$sitemapUrl = ( new \IPS\Sitemap )->sitemapUrl;
		$content = <<<FILE
# Rules for Invision Community (https://invisioncommunity.com)
User-Agent: *
# Block pages with no unique content
Disallow: {$path}startTopic/
Disallow: {$path}discover/unread/
Disallow: {$path}markallread/
Disallow: {$path}staff/
Disallow: {$path}cookie/
Disallow: {$path}online/
Disallow: {$path}discover/
Disallow: {$path}leaderboard/
Disallow: {$path}search/
Disallow: {$path}tags/
Disallow: {$path}*?advancedSearchForm=
Disallow: {$path}register/
Disallow: {$path}lostpassword/
Disallow: {$path}login/

# Block faceted pages and 301 redirect pages
Disallow: {$path}*?sortby=
Disallow: {$path}*?filter=
Disallow: {$path}*?tab=
Disallow: {$path}*?do=
Disallow: {$path}*ref=
Disallow: {$path}*?forumId*

# Block profile pages as these have little unique value, consume a lot of crawl time and contain hundreds of 301 links
Disallow: {$path}profile/

# Sitemap URL
Sitemap: {$sitemapUrl}
FILE;

		return $content;
	}

	/**
	 * Output the basic javascript files every page needs
	 *
	 * @return void
	 */
	protected static function baseJs()
	{
		parent::baseJs();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->globalControllers[] = 'core.front.core.app';
			if ( \IPS\Settings::i()->core_datalayer_enabled )
			{
				\IPS\Output::i()->globalControllers[] = 'core.front.core.dataLayer';
			}
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front.js' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_core.js', 'core', 'front' ) );

			if ( \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] AND ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) AND \IPS\Request::i()->cookie['vseThemeId'] ) and \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_vse.js', 'core', 'front' ) );
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'vse/vsedata.js', 'core', 'interface' ) );
				\IPS\Output::i()->globalControllers[] = 'core.front.vse.window';
			}

			/* Can we edit widget layouts? */
			if( \IPS\Member::loggedIn()->modPermission('can_manage_sidebar') )
			{
				\IPS\Output::i()->globalControllers[] = 'core.front.widgets.manager';
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_widgets.js', 'core', 'front' ) );
			}

			/* Are we editing meta tags? */
			if( isset( $_SESSION['live_meta_tags'] ) and $_SESSION['live_meta_tags'] and \IPS\Member::loggedIn()->isAdmin() )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_system.js', 'core', 'front' ) );
			}
		}
	}

	/**
	 * Base CSS
	 *
	 * @return	void
	 */
	public static function baseCss()
	{
		parent::baseCss();

		/* Stuff for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core.css', 'core', 'front' ) );
			if ( \IPS\Output::i()->responsive and \IPS\Theme::i()->settings['responsive'] )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core_responsive.css', 'core', 'front' ) );
			}
			
			if ( \IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] AND ( isset( \IPS\Request::i()->cookie['vseThemeId'] ) AND \IPS\Request::i()->cookie['vseThemeId'] ) and \IPS\Member::loggedIn()->isAdmin() and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/vse.css', 'core', 'front' ) );
			}

			/* Are we editing meta tags? */
			if( isset( $_SESSION['live_meta_tags'] ) and $_SESSION['live_meta_tags'] and \IPS\Member::loggedIn()->isAdmin() )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/meta_tags.css', 'core', 'front' ) );
			}
			
			/* Query log? */
			if ( \IPS\QUERY_LOG )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/query_log.css', 'core', 'front' ) );
			}
			if ( \IPS\CACHING_LOG or \IPS\REDIS_LOG )
			{
				\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/caching_log.css', 'core', 'front' ) );
			}
		}
	}
	
	/**
	 * Do Member Check
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	protected static function doMemberCheck(): ?\IPS\Http\Url
	{
		foreach( \IPS\Application::applications() AS $app )
		{
			if ( $url = $app->doMemberCheck() )
			{
				return $url;
			}
		}
		
		return NULL;
	}

	/**
	 *
	 *
	 * @return void
	 */
	public function checkAlerts()
	{
		/* Don't get in the way of validating members */
		if ( \IPS\Member::loggedIn()->members_bitoptions['validating'] )
		{
			return;
		}

		/* Don't get in the way of the ModCP, registering, logging in, etc */
		$ignoreControllers = [ 'modcp', 'register', 'login', 'redirect', 'cookie' ];
		if( !\IPS\Request::i()->isAjax() and !\in_array( $this->controller, $ignoreControllers ) AND $alert = \IPS\core\Alerts\Alert::getNextAlertForMember( \IPS\Member::loggedIn() ) )
		{
			$alert->viewed( \IPS\Member::loggedIn() );

			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/alerts.css', 'core', 'front' ) );
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'alerts', 'core', 'front' )->alertModal( $alert, $url = base64_encode( \IPS\Request::i()->url() ) );
		}

		return;
	}
}

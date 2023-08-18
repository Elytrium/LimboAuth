<?php
/**
 * @brief		Core Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */
 
namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Core Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * @brief	Cached advertisement count
	 */
	protected $advertisements	= NULL;
	
	/**
	 * @brief	Cached clubs pending approval count
	 */
	protected $clubs = NULL;

	/**
	 * ACP Menu Numbers
	 *
	 * @param	array	$queryString	Query String
	 * @return	int
	 */
	public function acpMenuNumber( $queryString )
	{
		parse_str( $queryString, $queryString );
		switch ( $queryString['controller'] )
		{
			case 'advertisements':
				if( $this->advertisements === NULL )
				{
					$this->advertisements	= \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( 'ad_active=-1' ) )->first();
				}
				return $this->advertisements;
			
			case 'clubs':
				if( $this->clubs === NULL )
				{
					$this->clubs	= \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', array( 'approved=0' ) )->first();
				}
				return $this->clubs;

			case 'privacy':
				$where = ['action IN (?)', \IPS\Db::i()->in('action', [\IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE, \IPS\Member\PrivacyAction::TYPE_REQUEST_PII] ) ];
				return \IPS\Db::i()->select( 'COUNT(*)', 'core_member_privacy_actions', $where)->first();

			case 'themes':
			case 'applications':
			case 'plugins':
			case 'languages':
				return $this->_getUpdateCount( $queryString['controller'] );
		}
		
		return 0;
	}
	
	/**
	 * Returns the ACP Menu JSON for this application.
	 *
	 * @return array
	 */
	public function acpMenu()
	{
		$menu = parent::acpMenu();
		
		if ( \IPS\DEMO_MODE )
		{
			unset( $menu['support'] );
		}
		

		$categories = ( new \IPS\core\modules\admin\marketplace\marketplace )->hierarchicalCategoryTree();

		foreach( $categories as $c )
		{
			$menu[ 'mp_' . $c['id'] ][ 'all' ] = array(
				'tab' => 'marketplace',
				'module_url' => 'marketplace',
				'controller' => 'marketplace',
				'do' => 'viewCategory&id=' . $c['id'],
				'restriction' => 'marketplace_manage',
				'restriction_module' => 'marketplace',
				'menu_checks' => array( 'do' => 'viewCategory', 'id' => $c['id'] ),
				'menu_controller' => 'all'
			);

			foreach( $c['children'] as $ch )
			{
				$menu[ 'mp_' . $c['id'] ][ $ch['id'] ] = array(
					'tab' => 'marketplace',
					'module_url' => 'marketplace',
					'controller' => 'marketplace',
					'do' => 'viewCategory&id=' . $ch['id'],
					'restriction' => 'marketplace_manage',
					'restriction_module' => 'marketplace',
					'menu_checks' => array( 'do' => 'viewCategory', 'id' => $ch['id'] ),
					'menu_controller' => $ch['id']
				);

				\IPS\Member::loggedIn()->language()->words[ 'menu__core_mp_' . $c['id'] . '_' . $ch['id'] ] = $ch['name'];
			}
			\IPS\Member::loggedIn()->language()->words[ 'menu__core_mp_' . $c['id'] ] = $c['name'];
			\IPS\Member::loggedIn()->language()->words[ 'menu__core_mp_' . $c['id'] . '_all' ] = 'All ' . $c['name'];
		}
		
		if ( \IPS\Application::appIsEnabled( 'cloud' ) )
		{
			$menu['smartcommunity']['features'] = array(
				'tab'			=> 'core',
				'controller'	=> 'cloud',
				'do'			=> '',
				'restriction'	=> 'licensekey_manage'
			);
		}
				
		return $menu;
	}
	
	/**
	 * Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function get__badge()
	{
		$return = parent::get__badge();
		
		if ( $return )
		{
			$availableUpgrade = $this->availableUpgrade( TRUE, FALSE );
			$return[2] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->updatebadge( $availableUpgrade['version'], \IPS\Http\Url::internal( 'app=core&module=system&controller=upgrade', 'admin' ), (string) \IPS\DateTime::ts( $availableUpgrade['released'] )->localeDate(), FALSE );
		}
		
		return $return;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'cogs';
	}

	/**
	 * Install Other
	 *
	 * @return	void
	 */
	public function installOther()
	{
		/* Save installed domain to spam defense whitelist */
		$domain = rtrim( str_replace( 'www.', '', parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) ), '/' );
		\IPS\Db::i()->insert( 'core_spam_whitelist', array( 'whitelist_type' => 'domain', 'whitelist_content' => $domain, 'whitelist_date' => time(), 'whitelist_reason' => 'Invision Community Domain' ) );

		/* Generate VAPID keys for web push notifications */
		try 
		{
			$vapid = \IPS\Notification::generateVapidKeys();
			\IPS\Settings::i()->changeValues( array( 'vapid_public_key' => $vapid['publicKey'], 'vapid_private_key' => $vapid['privateKey'] ) );
		}
		catch (\Exception $ex)
		{
			\IPS\Log::log( $ex, 'create_vapid_keys' );
		}

		/* Install default ranks, rules and badges */
		\IPS\core\Achievements\Rule::importXml( $this->getApplicationPath() . "/data/achievements/rules.xml" );
		\IPS\core\Achievements\Rank::importXml( $this->getApplicationPath() . "/data/achievements/ranks.xml" );
		\IPS\core\Achievements\Badge::importXml( $this->getApplicationPath() . "/data/achievements/badges.xml" );
	}
	
	/**
	 * Can view page even when user is a guest when guests cannot access the site
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowGuestAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return (
			$module->key == 'system'
			and
			\in_array( $controller, array( 'login', 'register', 'lostpass', 'terms', 'ajax', 'privacy', 'editor',
				'language', 'theme', 'redirect', 'guidelines', 'announcement', 'metatags', 'marketplace', 'serviceworker', 'offline', 'cookie' ) )
		)
		or
		( 
			$module->key == 'contact' and $controller == 'contact'
		)
        or
        (
            $module->key == 'discover' and \in_array( $controller, array( 'rss', 'streams' ) )
        );
	}
	
	/**
	 * Can view page even when site is offline
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowOfflineAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return (
			$module->key == 'system'
			and
			(
				\in_array( $controller, array(
					'login', // Because you can login when offline
					'embed', // Because the offline message can contain embedded media
					'lostpass',
					'register',
					'announcement', // Announcements can be useful when the site is offline
					'redirect', // When email tracking is enabled we pass through here
					'marketplace', // Remote services needs to send data back
					'metatags', // Manifest
					'serviceworker', // Service Worker
					'offline', // Service Worker offline page
					'cookie'
				) )
				or
				(
					$controller === 'ajax' and 
						( $do === 'states' OR  // Makes sure address input still works within the ACP otherwise the form to turn site back online is broken
						$do === 'passwordStrength' OR // Makes sure the password strength meter still works because it is used in the AdminCP and registration
						$do === 'getCsrfKey' ) // Makes sure we can still fetch the correct CSRF key for the ajax replacement
				)
			or
				in_array( $controller, ['terms', 'cookies'] )	// whitelist terms and cookies pages
			)
		);
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		$activityTabs = array(
			array( 'key' => 'AllActivity' ),
			array( 'key' => 'YourActivityStreams' ),
		);
		
		foreach ( array( 1, 2 ) as $k )
		{
			try
			{
				\IPS\core\Stream::load( $k );
				$activityTabs[] = array(
					'key'		=> 'YourActivityStreamsItem',
					'config'	=> array( 'menu_stream_id' => $k )
				);
			}
			catch ( \Exception $e ) { }
		}

		$activityTabs[] = array( 'key' => 'Search' );
		$activityTabs[] = array( 'key' => 'Promoted' );
		
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array(
				array( 'key' => 'Clubs' )
			),
			'browseTabsEnd'	=> array(
				array( 'key' => 'Guidelines' ),
				array( 'key' => 'StaffDirectory' ),
				array( 'key' => 'OnlineUsers' ),
				array( 'key' => 'Leaderboard' )
			),
			'activityTabs'	=> $activityTabs
		);
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		/* Convert &section= to &controller= */
		if ( isset( \IPS\Request::i()->section ) AND !isset( \IPS\Request::i()->controller ) )
		{
			\IPS\Request::i()->controller = \IPS\Request::i()->section;
		}

		/* Convert &showuser= */
		if ( isset( \IPS\Request::i()->showuser ) and \is_numeric( \IPS\Request::i()->showuser ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=profile&id=' . \IPS\Request::i()->showuser ) );
		}
		
		/* Redirect ?app=core&module=attach&section=attach&attach_rel_module=post&attach_id= */
		if ( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'attach' AND isset( \IPS\Request::i()->attach_id ) AND \is_numeric( \IPS\Request::i()->attach_id ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "applications/core/interface/file/attachment.php?id=" . \IPS\Request::i()->attach_id, 'none' ) );
		}

		/* redirect vnc to new streams */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND  isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'vnc' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discover&controller=streams' ) );
		}

		/* redirect 4.0 activity page to streams */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND isset( \IPS\Request::i()->module ) AND (\IPS\Request::i()->module == 'activity' ) AND isset( \IPS\Request::i()->controller ) AND \IPS\Request::i()->controller == 'activity' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discover&controller=streams' ) );
		}

		/* redirect old message link */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'members' AND isset( \IPS\Request::i()->module ) AND ( \IPS\Request::i()->module == 'messaging' ) AND \IPS\Request::i()->controller == 'view' AND isset( \IPS\Request::i()->topicID ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=messaging&controller=messenger&id=' . \IPS\Request::i()->topicID, 'front', 'messenger_convo' ) );
		}

		/* redirect old messenger link */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'members' AND isset( \IPS\Request::i()->module ) AND ( \IPS\Request::i()->module == 'messaging' ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=messaging&controller=messenger', 'front', 'messaging' ) );
		}

		/* redirect old messenger link */
		if( isset( \IPS\Request::i()->module ) AND \IPS\Request::i()->module == 'global' AND isset( \IPS\Request::i()->controller ) AND (\IPS\Request::i()->controller == 'register' ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=register', 'front', 'register' ) );
		}

		/* redirect old reports */
		if( isset( \IPS\Request::i()->app ) AND \IPS\Request::i()->app == 'core' AND
			isset( \IPS\Request::i()->module ) AND (\IPS\Request::i()->module == 'reports' ) AND
			isset( \IPS\Request::i()->do ) AND ( \IPS\Request::i()->do == 'show_report' )  )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports&action=view&id=' . \IPS\Request::i()->rid , 'front', 'modcp_report' ) );
		}
	}
	
	/**
	 * Get any third parties this app uses for the privacy policy
	 *
	 * @return array( title => language bit, description => language bit, privacyUrl => privacy policy URL )
	 */
	public function privacyPolicyThirdParties()
	{
		/* Apps can overload this */
		$subprocessors = array();
			
		/* Analytics */
		if ( \IPS\Settings::i()->ga_enabled )
		{
			$subprocessors[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack('enhancements__core_GoogleAnalytics'),
				'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_GoogleAnalytics'),
				'privacyUrl' => 'https://www.google.com/intl/en/policies/privacy/'
			);
		}
		if ( \IPS\Settings::i()->matomo_enabled )
		{
			$subprocessors[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack('analytics_provider_matomo'),
				'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_Matomo'),
				'privacyUrl' => 'https://matomo.org/privacy-policy/'
			);
		}
		
		/* Facebook Pixel */
		$fb = new \IPS\core\extensions\core\CommunityEnhancements\FacebookPixel();
		if ( $fb->enabled )
		{
			$subprocessors[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack('enhancements__core_FacebookPixel'),
				'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_FacebookPixel'),
				'privacyUrl' => 'https://www.facebook.com/about/privacy/'
			);
		}
		
		/* IPS Spam defense */
		if ( \IPS\Settings::i()->spam_service_enabled )
		{
			$subprocessors[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack('enhancements__core_SpamMonitoring'),
				'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_SpamMonitoring'),
				'privacyUrl' => 'https://invisioncommunity.com/legal/privacy'
			);
		}
		
		/* Send Grid */
		$sendgrid = new \IPS\core\extensions\core\CommunityEnhancements\Sendgrid();
		if ( $sendgrid->enabled )
		{
			$subprocessors[] = array(
				'title' => \IPS\Member::loggedIn()->language()->addToStack('enhancements__core_Sendgrid'),
				'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_SendGrid'),
				'privacyUrl' => 'https://sendgrid.com/policies/privacy/'
			);
		}
		
		/* Captcha */
		if ( \IPS\Settings::i()->bot_antispam_type !== 'none' )
		{
			switch ( \IPS\Settings::i()->bot_antispam_type )
			{
				case 'recaptcha2':
					$subprocessors[] = array(
						'title' => \IPS\Member::loggedIn()->language()->addToStack('captcha_type_recaptcha2'),
						'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_captcha'),
						'privacyUrl' => 'https://www.google.com/policies/privacy/'
					);
					break;
				case 'invisible':
					$subprocessors[] = array(
						'title' => \IPS\Member::loggedIn()->language()->addToStack('captcha_type_invisible'),
						'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_captcha'),
						'privacyUrl' => 'https://www.google.com/policies/privacy/'
					);
					break;
				case 'keycaptcha':
					$subprocessors[] = array(
						'title' => \IPS\Member::loggedIn()->language()->addToStack('captcha_type_keycaptcha'),
						'description' => \IPS\Member::loggedIn()->language()->addToStack('pp_desc_captcha'),
						'privacyUrl' => 'https://www.keycaptcha.com'
					);
					break;
				case 'hcaptcha':
					$subprocessors[] = array(
						'title' => \IPS\Member::loggedIn()->language()->addToStack('captcha_type_hcaptcha'),
						'description' => \IPS\Member::loggedIn()->language()->addToStack('hcaptcha_privacy'),
						'privacyUrl' => 'https://www.hcaptcha.com/privacy'
					);
			}
		}
		
		return $subprocessors;
				
	}
	
	/**
	 * Get any settings that are uploads
	 *
	 * @return	array
	 */
	public function uploadSettings()
	{
		/* Apps can overload this */
		return array( 'email_logo' );
	}

	/**
	 * Imports an IN_DEV email template into the database
	 *
	 * @param	string		$path			Path to file
	 * @param	object		$file			DirectoryIterator File Object
	 * @param	string|null	$namePrefix		Name prefix
	 * @return  array
	 */
	protected function _buildEmailTemplateFromInDev( $path, $file, $namePrefix='' )
	{
		$return = parent::_buildEmailTemplateFromInDev( $path, $file, $namePrefix );

		/* Make sure that the email wrapper is pinned to the top of the list */
		if( $file->getFilename() == 'emailWrapper.phtml' )
		{
			$return['template_pinned'] = 1;
		}

		return $return;
	}

	/**
	 * Get AdminCP Menu Count for resource updates
	 *
	 * @param	string	$type		resource type (applications/plugins/languages/themes)
	 * @return	int
	 */
	protected function _getUpdateCount( string $type ): int
	{
		$key = "updatecount_{$type}";
		if( isset( \IPS\Data\Store::i()->$key ) )
		{
			return \IPS\Data\Store::i()->$key;
		}

		$count = 0;
		switch( $type )
		{
			case 'applications':
				foreach( \IPS\Application::applications() as $app )
				{
					if ( \IPS\CIC AND \IPS\IPS::isManaged() AND \in_array( $app->directory, \IPS\IPS::$ipsApps ) )
					{
						continue;
					}
					
					if( \count( $app->availableUpgrade( TRUE ) ) )
					{
						$count++;
					}
				}
				break;
			case 'plugins':
				foreach( \IPS\Plugin::plugins() as $plugin )
				{
					if( $plugin->update_check_data )
					{
						$data = json_decode( $plugin->update_check_data, TRUE );
						if( !empty( $data['longversion'] ) AND $data['longversion'] > $plugin->version_long )
						{
							$count++;
						}
					}
				}
				break;
			case 'languages':
				foreach( \IPS\Lang::languages() as $language )
				{
					if( $language->update_data )
					{
						$data = json_decode( $language->update_data, TRUE );
						if( !empty( $data['longversion'] ) AND $data['longversion'] > $language->version_long )
						{
							$count++;
						}
					}
				}
				break;
			case 'themes':
				foreach( \IPS\Theme::themes() as $theme )
				{
					if( $theme->update_data )
					{
						$data = json_decode( $theme->update_data, TRUE );
						if( !empty( $data['longversion'] ) AND $data['longversion'] > $theme->long_version )
						{
							$count++;
						}
					}
				}
				break;
		}

		\IPS\Data\Store::i()->$key = $count;
		return (int) \IPS\Data\Store::i()->$key;
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks() : array
	{
		return array_merge(  [
				'club_created' => \IPS\Member\Club::class,
				'club_deleted' => \IPS\Member\Club::class,
				'club_member_added' => ['club' => \IPS\Member\Club::class, 'member' => \IPS\Member::class, 'status' => "string" ],
				'club_member_removed' => ['club' => \IPS\Member\Club::class, 'member' => \IPS\Member::class ],
				'member_create' => \IPS\Member::class,
				'member_registration_complete' => \IPS\Member::class,
				'member_edited' => [ 'member' => \IPS\Member::class, 'changes' => "array" ],
				'member_delete' => \IPS\Member::class,
				'member_warned' => \IPS\core\Warnings::class,
				'member_merged' => [ 'kept' => \IPS\Member::class, 'removed' => \IPS\Member::class],
				'content_promoted' => \IPS\Content::class,
				'content_marked_solved' => [ 'item' => \IPS\Content\Item::class, 'comment' => \IPS\Content\Comment::class , 'markedBy' => \IPS\Member::class ]
		], parent::getWebhooks() );
	}
	
	/**
	 * Do Member Check
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function doMemberCheck(): ?\IPS\Http\Url
	{
		/* Need their name or email... */
		if( ( \IPS\Member::loggedIn()->real_name === '' or !\IPS\Member::loggedIn()->email ) and \IPS\Dispatcher::i()->controller !== 'register' and \IPS\Dispatcher::i()->controller !== 'login' and \IPS\Dispatcher::i()->controller !== 'cookies' )
		{
			return \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=complete' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
		}
		/* Need them to validate... */
		elseif(
			\IPS\Member::loggedIn()->members_bitoptions['validating'] and
			\IPS\Dispatcher::i()->controller !== 'register' and
			\IPS\Dispatcher::i()->controller !== 'login' and
			\IPS\Dispatcher::i()->controller != 'redirect' and
			\IPS\Dispatcher::i()->controller !== 'store' and
			\IPS\Dispatcher::i()->controller !== 'checkout' and
			\IPS\Dispatcher::i()->controller !== 'cookies'
		)
		{
			return \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=validating', 'front', 'register' );
		}
		/* Need them to reconfirm terms/privacy policy... */
		elseif ( ( \IPS\Member::loggedIn()->members_bitoptions['must_reaccept_privacy'] or \IPS\Member::loggedIn()->members_bitoptions['must_reaccept_terms'] ) and \IPS\Dispatcher::i()->controller !== 'register' and \IPS\Dispatcher::i()->controller !== 'ajax' )
		{
			return \IPS\Http\Url::internal( 'app=core&module=system&controller=register&do=reconfirm', 'front', 'register' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
		}
		/* Have required profile actions that need completing */
		else if (
			\IPS\Settings::i()->allow_reg AND
			!\IPS\Member::loggedIn()->members_bitoptions['profile_completed'] AND
			!\in_array( \IPS\Dispatcher::i()->controller, array( 'register', 'login', 'redirect', 'ajax', 'settings', 'checkout', 'pixabay', 'editor', 'cookies' ) ) AND
			$completion = \IPS\Member::loggedIn()->profileCompletion() AND
			\count( $completion['required'] )
		)
		{
			foreach( $completion['required'] AS $id => $completed )
			{
				if ( $completed === FALSE )
				{
					return \IPS\Http\Url::internal( "app=core&module=system&controller=register&do=finish&_new=1", 'front', 'register' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
				}
			}
		}

		/* Need to set up MFA... */
		if ( !\in_array( \IPS\Dispatcher::i()->controller, array( 'register', 'login', 'redirect', 'ajax', 'settings', 'embed', 'cookies' ) ) )
		{
			$haveAcceptableHandlers = FALSE;
			$haveConfiguredHandler = FALSE;
			foreach ( \IPS\MFA\MFAHandler::handlers() as $key => $handler )
			{
				if ( $handler->isEnabled() and $handler->memberCanUseHandler( \IPS\Member::loggedIn() ) )
				{
					$haveAcceptableHandlers = TRUE;
					if ( $handler->memberHasConfiguredHandler( \IPS\Member::loggedIn() ) )
					{
						$haveConfiguredHandler = TRUE;
						break;
					}
				}
			}
			
			if ( !$haveConfiguredHandler and $haveAcceptableHandlers )
			{
				if ( \IPS\Settings::i()->mfa_required_groups == '*' or \IPS\Member::loggedIn()->inGroup( explode( ',', \IPS\Settings::i()->mfa_required_groups ) ) )
				{
					if ( \IPS\Settings::i()->mfa_required_prompt === 'immediate' )
					{
						return \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&do=initialMfa', 'front', 'settings' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
					}
				}
				elseif ( \IPS\Settings::i()->mfa_optional_prompt === 'immediate' and !\IPS\Member::loggedIn()->members_bitoptions['security_questions_opt_out'] )
				{
					return \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&do=initialMfa', 'front', 'settings' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) );
				}
			}
		}

		/* Need to reset password */
		if ( ( \IPS\Dispatcher::i()->controller !== 'settings' AND ( !isset( \IPS\Request::i()->area ) OR \IPS\Request::i()->area !== 'password' ) ) AND !( \IPS\Dispatcher::i()->controller == 'alerts' AND \IPS\Request::i()->do == 'dismiss'  ) )
		{
			foreach( \IPS\Login::methods() AS $method )
			{
				if ( $url = $method->forcePasswordResetUrl( \IPS\Member::loggedIn(), \IPS\Request::i()->url() ) )
				{
					return $url;
				}
			}
		}
		
		return NULL;
	}

	/**
	 * Install the application's settings
	 *
	 * @return	void
	 */
	public function installSettings()
	{
		/* It's enough if we run this only for the core app instead for each app which is upgraded */
		\IPS\core\extensions\core\CommunityEnhancements\Zapier::rebuildRESTApiPermissions();
		parent::installSettings();
	}

	/**
	 * Returns a list of essential cookies which are set by this app.
	 * Wildcards (*) can be used at the end of cookie names for PHP set cookies.
	 *
	 * @return string[]
	 */
	public function _getEssentialCookieNames(): array
	{
		$cookies = [ 'oauth_authorize', 'member_id', 'login_key', 'clearAutosave', 'lastSearch','device_key', 'IPSSessionFront', 'loggedIn', 'noCache', 'hasJS', 'cookie_consent', 'cookie_consent_optional' ];

		if( \IPS\Settings::i()->guest_terms_bar )
		{
			$cookies[] = 'guestTermsDismissed';
		}

		if( count( \IPS\Lang::getEnabledLanguages() ) > 1 )
		{
			$cookies[] = 'language';
		}

		if( \IPS\Settings::i()->ref_on )
		{
			$cookies[] = 'referred_by';
		}

		return $cookies;
	}
}

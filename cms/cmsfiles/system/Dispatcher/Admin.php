<?php
/**
 * @brief		Admin CP Dispatcher
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
 * Admin CP Dispatcher
 */
class _Admin extends \IPS\Dispatcher\Standard
{
	/**
	 * Controller Location
	 */
	public $controllerLocation = 'admin';
	
	/**
	 * @brief	Cached Menu
	 */
	protected $menu = NULL;
	
	/**
	 * @brief	Search Keywords
	 */
	public $searchKeywords = array();
	
	/**
	 * @brief	ACP Restrictions (for search keyword editing)
	 */
	public $moduleRestrictions = array();
	
	/**
	 * @brief	ACP Restriction for the current menu item (for search keyword editing)
	 */
	public $menuRestriction = NULL;
	
	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		\IPS\Output::i()->sidebar['appmenu'] = '';

		/* Sync stuff when in developer mode */
		if ( \IPS\IN_DEV )
		{
			 \IPS\Developer::sync();
		}

		if ( \IPS\Member::loggedIn()->member_id )
		{
			/* Build the menu */
			$menu = $this->buildMenu();

			/* Do we need to figure out the default? */
			if ( !isset( \IPS\Request::i()->app ) )
			{
				foreach ( $menu['tabs'] as $app => $appData )
				{
					if ( isset( $menu['defaults'][ $app ] ) )
					{
						parse_str( $menu['defaults'][ $app ], $defaultQueryString );
						foreach ( $defaultQueryString as $k => $v )
						{
							\IPS\Request::i()->$k = $v;
						}
						break;
					}
				}
			}
		}
		
		/* Call parent */
		static::baseCss();
		static::baseJs();

		/* Stuff needed for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			/* Special grouped CSS files */
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'core.css', 'core', 'admin' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'responsive.css', 'core', 'front' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'responsive.css', 'core', 'admin' ) );

			/* JS */
			\IPS\Output::i()->globalControllers[] = 'core.admin.core.app';
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin.js' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-touchpunch.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.menuaim.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.nestedSortable.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_core.js', 'core', 'front' ) );

			if ( \IPS\Member::loggedIn()->member_id )
			{
				/* These are just defaults in case we hit an immediate error, e.g. app or controller doesn't exist */
				\IPS\Output::i()->sidebar['sidebar'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->sidebar( array(), 'core_overview' );
				\IPS\Output::i()->sidebar['appmenu'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->appmenu( $menu, 'core', 'core_overview_dashboard' );
				\IPS\Output::i()->sidebar['mobilenav'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->mobileNavigation( $menu, 'core' );
			}

		}
		
		/* Check we're logged in and we have ACP access */
		if( ( !\IPS\Member::loggedIn()->member_id or !\IPS\Member::loggedIn()->isAdmin() )
				and ( \IPS\Request::i()->module !== 'system' or \IPS\Request::i()->controller !== 'login' )
				and ( !\IPS\ENFORCE_ACCESS )
		)
		{
			/* Make sure the right protocol is used. IIS, for example, does not like protocol relative URL's in redirects. (Ref: 970629) */
			$protocol = \IPS\Http\Url::PROTOCOL_HTTP;
			if ( \substr( \IPS\Settings::i()->base_url, 0, 5 ) == 'https' )
			{
				$protocol = \IPS\Http\Url::PROTOCOL_HTTPS;
			}
			
			$url = \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'admin', NULL, array(), $protocol );

			if ( \IPS\Session::i()->error )
			{
				$url = $url->setQueryString( 'error', \IPS\Session::i()->error->getMessage() );
			}
			
			if( !\IPS\Request::i()->isAjax() )
			{
				/* If someone calls this from command line, while it wouldn't work, the key won't be set */
				if( isset( $_SERVER['QUERY_STRING'] ) )
				{
					$url = $url->setQueryString( 'ref', base64_encode( $_SERVER['QUERY_STRING'] ) );
				}
			}
			else if( isset( $_SERVER['HTTP_REFERER'] ) )
			{
				$previous = preg_replace( "/^(.+?)\/\?/", "", $_SERVER['HTTP_REFERER'] );
				$url = $url->setQueryString( 'ref', base64_encode( $previous ) );
			}

			\IPS\Output::i()->redirect( $url );
		}
				
		/* Init */
		try
		{
			parent::init();
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2S100/' . $e->getCode(), $e->getCode() === 4 ? 403 : 404, '' );
		}
		
		/* Unless there is a flag telling us we have specifically added CSRF checks, assume any AdminCP action which contains more than app/module/controller/id (i.e. anything with "do") requires CSRF-protection */
		if ( !isset( $this->classname::$csrfProtected ) and array_diff( array_keys( \IPS\Request::i()->url()->queryString ), array( 'app', 'module', 'controller', 'id' ) ) )
		{
			\IPS\Session::i()->csrfCheck();
		}

		\IPS\Db::i()->readWriteSeparation = FALSE;
		if ( isset( $this->classname::$allowRWSeparation ) and $this->classname::$allowRWSeparation )
		{
			\IPS\Db::i()->readWriteSeparation = TRUE;
		}

		/* If we are in recovery mode, but not actually doing the recovery process, or logging in, then we need them to remove the constant */
		if ( \IPS\RECOVERY_MODE === TRUE AND !\in_array( $this->controller, array( 'recovery', 'login' ) ) )
		{
			\IPS\Output::i()->error( 'recovery_mode_remove_constant', '1S107/3', 403, '' );
		}
		
		/* Permission Check */
		if (
			(
				$this->module->key !== 'system' or
				!\in_array( $this->controller, array( 'login', 'language', 'theme', 'livesearch', 'editor', 'ajax' ) )
			) and
			/* Every admin can view and manage his own acp notification */
			( $this->module->key !== 'overview' or $this->controller !== 'notifications' ) and
			(
				$this->module->key !== 'members' or
				$this->controller !== 'members' or
				!\in_array( \IPS\Request::i()->do, array( 'adminDetails', 'adminEmail', 'adminPassword' ) )
			) and
			/* This is slightly hacky, but the upgrader was moved to the system module, however the ACP restriction is still set to overview.
				To avoid unintentionally removing restrictions via an upgrade by moving the restriction, we reference the overview module for the restriction check instead */
			!\IPS\Member::loggedIn()->hasAcpRestriction( $this->application, ( $this->application->directory === 'core' and $this->module->key === 'system' and $this->controller === 'upgrade' ) ? \IPS\Application\Module::get( 'core', 'overview', 'admin' ) : $this->module )
		)
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S107/1', 403, '' );
		}

		if( ! \IPS\Application::appIsEnabled( $this->application->directory ) )
		{
			\IPS\Output::i()->error( 'requested_route_404', '2S107/5', 404, '' );
		}

		/* Support is not available for demos */
		if ( \IPS\DEMO_MODE AND $this->module->application === 'core' AND $this->module->key === 'support' )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '1S107/4', 403, '' );
		}
		
		/* ACP search keywords */
		if ( \IPS\IN_DEV )
		{
			foreach ( \IPS\Db::i()->select( '*', 'core_acp_search_index' ) as $word )
			{
				$this->searchKeywords[ $word['url'] ]['lang_key'] = $word['lang_key'];
				$this->searchKeywords[ $word['url'] ]['restriction'] = $word['restriction'];
				$this->searchKeywords[ $word['url'] ]['keywords'][] = $word['keyword'];
			}

			$restrictions = array();

			$file = $this->application->getApplicationPath() . "/data/acprestrictions.json";
			if ( file_exists( $file ) )
			{
				$restrictions = json_decode( file_get_contents( $file ), TRUE );
			}

			$this->moduleRestrictions[''] = 'acpmenu_norestriction';
			if ( isset( $restrictions[ $this->module->key ] ) )
			{
				foreach ( $restrictions[ $this->module->key ] as $key => $values )
				{
					$this->moduleRestrictions[ $key ] = array_combine( $values, $values );
				}
			}
									
			$appMenu = $this->application->acpMenu();
			if ( isset( $appMenu[ $this->module->key ] ) )
			{
				foreach ( $appMenu[ $this->module->key ] as $menuItem )
				{
					if ( $menuItem['restriction'] and $menuItem['controller'] == $this->controller and ( !$menuItem['do'] or ( isset( \IPS\Request::i()->do ) and $menuItem['do'] == \IPS\Request::i()->do ) ) )
					{
						$this->menuRestriction = $menuItem['restriction'];
					}
				}
			}
		}
		
		/* More stuff needed for output */
		if ( !\IPS\Request::i()->isAjax() )
		{
			/* Menu and base navigation */
			if ( \IPS\Member::loggedIn()->member_id )
			{
				/* Work out what tab we're on */
				$currentTab = NULL;
				$currentItem = NULL;

				foreach ( $this->application->acpMenu() as $moduleKey => $items )
				{
					/* If the module key does not match, we still need to inspect each item to see if a module_url that matches was specified */
					$moduleUrlMatches = FALSE;

					foreach( $items as $item )
					{
						if( isset( $item['module_url'] ) AND $item['module_url'] == $this->module->key )
						{
							$moduleUrlMatches = TRUE;
							break;
						}
					}

					if ( $moduleUrlMatches OR $moduleKey === $this->module->key )
				  	{
				  		foreach ( $items as $itemKey => $item )
				  		{
					  		if ( !$currentTab )
					  		{
					  			$currentTab = $item['tab'];
					  		}

					  		$additionalChecksPass = TRUE;

					  		if( isset( $item['menu_checks'] ) AND \is_array( $item['menu_checks'] ) )
					  		{
					  			foreach( $item['menu_checks'] as $key => $value )
					  			{
					  				if( !isset( \IPS\Request::i()->$key ) OR \IPS\Request::i()->$key != $value )
					  				{
					  					$additionalChecksPass = FALSE;
					  					break;
					  				}
					  			}
					  		}

				  			if ( $additionalChecksPass === TRUE and ( $item['controller'] === $this->controller or ( isset( $item['subcontrollers'] ) and \in_array( $this->controller, explode( ",", $item['subcontrollers'] ) ) ) ) )
				  			{
				  				$controllerForKey = ( isset( $item['menu_controller'] ) ) ? $item['menu_controller'] : $item['controller'];

								$currentItem = $this->application->directory . "_" . $moduleKey . "_" . $controllerForKey;

								if( $currentTab != $item['tab'] )
								{
									$currentTab = $item['tab'];
								}
					  		}
				  		}
				  	}
				}

				if ( !$currentTab )
				{
					$currentTab = $this->application->directory;
				}

				/* Display */
				if ( isset( $menu['tabs'][ $currentTab ] ) )
				{
					\IPS\Output::i()->sidebar['sidebar'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->sidebar( $menu['tabs'][ $currentTab ], $this->application->directory . '_' . $this->module->key );
				}
				\IPS\Output::i()->sidebar['appmenu'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->appmenu( $menu, $currentTab, $currentItem );
				\IPS\Output::i()->sidebar['mobilenav'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->mobileNavigation( $menu, $currentTab, $currentItem );
			}
		}
	}
	
	/**
	 * Build Menu
	 *
	 * @param	bool	$rebuild	If TRUE, will rebuild
	 * @return	array
	 */
	public function buildMenu( $rebuild=FALSE )
	{
		$acpTabOrder = $this->_getAcpTabOrder();
		
		if ( $this->menu === NULL or $rebuild === TRUE )
		{
			$this->menu = array( 'tabs' => array(), 'defaults' => array() );
			
			foreach ( \IPS\Application::applications() as $app )
			{
				if ( \IPS\Application::appIsEnabled( $app->directory ) and \IPS\Application::load( $app->directory )->canAccess() )
				{
					$appMenu = $app->acpMenu();
					
					if ( $acpTabOrder !== NULL and isset( $acpTabOrder[ $app->directory ] ) and $app->directory == 'nexus' )
					{
						uksort( $appMenu, function( $a, $b ) use ( $acpTabOrder, $app )
						{
							return array_search( "{$app->directory}_{$a}", $acpTabOrder[ $app->directory ] ) - array_search( "{$app->directory}_{$b}", $acpTabOrder[ $app->directory ] );
						} );
					}

					foreach ( $appMenu as $moduleKey => $items )
					{
				  		foreach ( $items as $itemKey => $item )
					  	{
						  	if ( isset( $item['callback'] ) and !eval( $item['callback'] ) )
						  	{
							  	continue;
						  	}
						  	
						  	$moduleUrl = ( isset( $item['module_url'] ) ) ? $item['module_url'] : $moduleKey;
					  		$moduleToCheck = ( isset( $item['restriction_module'] ) ) ? $item['restriction_module'] : $moduleKey;
					  		
					  		if ( \IPS\Member::loggedIn()->hasAcpRestriction( $app, $moduleToCheck ) )
					  		{
								if( !$item['restriction'] )
								{
									$canAccess = TRUE;
								}
								else
								{
									if( mb_strpos( $item['restriction'], ',' ) )
									{
										$restrictions = explode( ',', $item['restriction'] );
									}
									else
									{
										$restrictions = array( $item['restriction'] );
									}

									$canAccess = FALSE;

									foreach( $restrictions as $restrictionKey )
									{
										if( \IPS\Member::loggedIn()->hasAcpRestriction( $app, $moduleToCheck, $restrictionKey ) )
										{
											$canAccess = TRUE;
											break;
										}
									}
								}

					  			if ( $canAccess )
					  			{  				
					  				$this->menu['tabs'][ $item['tab'] ][ "{$app->directory}_{$moduleKey}" ][ $itemKey ] = "app={$app->directory}&module={$moduleUrl}&controller={$item['controller']}" . ( $item['do'] ? "&do={$item['do']}" : '' );
					  			}
					  		}
					  	}
					}
				}
			}
		}
		
		if ( $acpTabOrder !== NULL )
		{
			$_apps	= array_keys( $acpTabOrder );
			uksort( $this->menu['tabs'], function($a, $b) use ( $_apps )
			{
				if( !\in_array( $a, $_apps ) )
				{
					return 1;
				}

				if( !\in_array( $b, $_apps ) )
				{
					return -1;
				}

				return array_search( $a, $_apps ) - array_search( $b, $_apps );
			} );
			
			foreach( $acpTabOrder as $app => $submenu )
			{
				if ( !empty( $submenu ) )
				{
					if( isset( $this->menu['tabs'][ $app ] ) )
					{
						uksort( $this->menu['tabs'][ $app ], function($a, $b) use ( $submenu )
						{
							if( !\in_array( $a, $submenu ) )
							{
								return 1;
							}

							if( !\in_array( $b, $submenu ) )
							{
								return -1;
							}

							return array_search( $a, $submenu ) - array_search( $b, $submenu );
						} );
					}
					
					if ( isset( $this->menu['defaults'] ) )
					{
						uksort( $this->menu['defaults'], function($a, $b) use ( $acpTabOrder )
						{
							return array_search( $a, $acpTabOrder );
						} );
					}
				}
			}
		}

		/* Now set the tab defaults */
		foreach( $this->menu['tabs'] as $tab => $menu )
		{
			if ( !isset( $this->menu['defaults'][ $tab ] ) )
			{
				foreach( $menu as $group => $submenu )
				{
					$this->menu['defaults'][ $tab ] = array_values($submenu)[0];
					break;
				}				
			}
		}

		return $this->menu;
	}

	/**
	 * @brief	Cached ACP tab order
	 */
	protected $acpTabOrder	= NULL;

	/**
	 * Figure out the ACP tab order
	 *
	 * @return array
	 */
	public function _getAcpTabOrder()
	{
		if( $this->acpTabOrder !== NULL )
		{
			return $this->acpTabOrder;
		}
		
		if ( isset( \IPS\Request::i()->cookie['acpTabs'] ) AND !\IPS\Settings::i()->acp_menu_cookie_rebuild )
		{ 
			$this->acpTabOrder = json_decode( \IPS\Request::i()->cookie['acpTabs'], TRUE );
		}
		else
		{
			if ( \IPS\Settings::i()->acp_menu_cookie_rebuild )
			{
				\IPS\Settings::i()->changeValues( array( 'acp_menu_cookie_rebuild' => 0 ) );
			}
			
			try
			{
				$this->acpTabOrder = json_decode( \IPS\Db::i()->select( 'data', 'core_acp_tab_order', array( 'id=?', \IPS\Member::loggedIn()->member_id ) )->first(), TRUE );
			}
			catch( \UnderflowException $ex )
			{
				$this->acpTabOrder = array( 'core' => array(), 'community' => array(), 'members' => array(), 'nexus' => array(), 'cms' => array(), 'stats' => array(), 'customization' => array(), 'marketplace' => array() );
			}
			
			\IPS\Request::i()->setCookie( 'acpTabs', json_encode( $this->acpTabOrder ) );
		}

		return $this->acpTabOrder;
	}

	/**
	 * Display a link in a custom format
	 *
	 * @param   string  $url    The URL from the menu system
	 * @return string|null
	 */
	public function acpMenuCustom( $url ): ?string
	{
		if ( !\IPS\Application::appIsEnabled( 'cloud' ) AND $url == 'app=core&module=smartcommunity&controller=cloud' )
		{
			$url = \IPS\Http\Url::internal( $url, 'admin' );
			$title = \IPS\Member::loggedIn()->language()->addToStack( "menu__core_smartcommunity_features" );
			return <<<EOF
<a href='{$url}' style="opacity:30%">{$title} <i class="fa fa-lock"></i></a>
EOF;

		}
		return NULL;
	}

	/**
	 * Do we have permission to use this module?
	 *
	 * @param	\IPS\Application	$app		Application
	 * @param	\IPS\Module|string	$module		Module
	 * @return	bool
	 */
	public function hasPermission( $app, $module )
	{
		return \IPS\Member::loggedIn()->hasAcpRestriction( $app, $module );
	}
	
	/**
	 * Check ACP Permission
	 *
	 * @param	string					$key		Permission Key
	 * @param	\IPS\Application|null	$app		Application (NULL will default to current)
	 * @param	\IPS\Module|string|null	$module		Module (NULL will default to current)
	 * @param	boolean					$return		Return boolean (true/false) instead of throwing an error
	 * @return	void
	 */
	public function checkAcpPermission( $key, $app=NULL, $module=NULL, $return=FALSE )
	{
		if ( !\IPS\Member::loggedIn()->hasAcpRestriction( ( $app ?: $this->application ), ( $module ?: $this->module ), $key ) )
		{
			if ( $return )
			{
				return FALSE;
			}
			
			\IPS\Output::i()->error( 'no_module_permission', '2S107/2', 403, '' );
		}
		
		if ( $return )
		{
			return TRUE;
		}
	}
	
	/**
	 * Show switch link
	 *
	 * @return	boolean
	 */
	final public static function showSwitchLink(): bool
	{
		if ( \IPS\CIC )
		{
			return false;
		}

		/* Don't show if installation was less than a month ago */
		if ( time() < ( (int) \IPS\Settings::i()->board_start + ( 86400 * 30 ) ) )
		{
			return false;
		}

		if ( ! isset( \IPS\Request::i()->cookie['acpLinkSnooze'] ) )
		{
			return true;
		}

		$value = json_decode( \IPS\Request::i()->cookie['acpLinkSnooze'], true );

		if ( $value['hits'] < 3 )
		{
			/* Show every 30 days */
			if ( time() < ( $value['lastClick'] + ( 86400 * 30 ) ) )
			{
				return false;
			}
		}
		else if ( $value['hits'] < 9 )
		{
			/* Show every 60 days */
			if ( time() < ( $value['lastClick'] + ( 86400 * 60 ) ) )
			{
				return false;
			}
		}
		else
		{
			/* Show every 90 days */
			if ( time() < ( $value['lastClick'] + ( 86400 * 90 ) ) )
			{
				return false;
			}
		}

		return true;
	}
}

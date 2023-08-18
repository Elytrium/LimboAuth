<?php
/**
 * @brief		Developer Center Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Developer Center Controller
 */
class _developer extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @param	string				$command	The part of the query string which will be used to get the method
	 * @return	void
	 */
	public function execute( $command='do' )
	{
		/* This controller can only be accessed in developer mode, so we can bypass the check global */
		
		\IPS\Output::i()->bypassCsrfKeyCheck = true;

		/* Are we in developer mode? */
		if( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C103/1', 403, '' );
		}
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C103/M', 403, '' );
		}
				
		/* Load application */
		try
		{
			$this->application = \IPS\Application::load( \IPS\Request::i()->appKey );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ) );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}" ), $this->application->_title );
		
		/* Get tab content */
		$this->activeTab = \IPS\Request::i()->tab ?: 'modules-front';
	
		/* Hand off to dispatcher */
		return parent::execute( $command );
	}

	/**
	 * Tree sorting calls to do=reorder, but we want to let the individual tabs handle that in this case.
	 * This method just takes the request and passes it to manage(), which in turn passes it to the correct tab, which then finally handles the reordering.
	 *
	 * @return void
	 */
	public function reorder()
	{
		\IPS\Session::i()->csrfCheck();
		
		return $this->manage();
	}
	
	/**
	 * Manage: Works out tab and fetches content
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		if ( $pos = mb_strpos( $this->activeTab, '-' ) )
		{
			$methodToCall		= '_manage' . mb_ucfirst( mb_substr( $this->activeTab, 0, $pos ) );
			$activeTabContents	= $this->$methodToCall( mb_substr( $this->activeTab, $pos + 1 ) );
		}
		else
		{
			$methodToCall		= '_manage' . mb_ucfirst( $this->activeTab );
			$activeTabContents	= $this->$methodToCall();
		}
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Build tab list */
		$tabs = array();
		$tabs['acpmenu'] = 'dev_acpmenu';
		$tabs['acprestrictions'] = 'dev_acprestrictions';
		$tabs['schema'] = 'dev_schema';
		$tabs['extensions'] = 'dev_extensions';
		$tabs['hooks'] = 'plugin_hooks';
		$tabs['modules-admin'] = 'dev_module_admin';
		$tabs['modules-front'] = 'dev_module_front';
		$tabs['settings'] = 'dev_settings';
		$tabs['tasks'] = 'dev_tasks';
		$tabs['versions'] = 'dev_versions';
		$tabs['widgets'] = 'dev_widgets';
		
		if( \IPS\Application::appIsEnabled('cms') )
		{
			$tabs['cmstemplates'] = 'dev_cms_templates';
		}
			
		/* Display */
		if ( $activeTabContents )
		{
			/* Add the build and download button */
            if( !$this->application->marketplace_id )
            {
                \IPS\Output::i()->jsFiles = array_merge(\IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_system.js', 'core', 'admin'));
                \IPS\Output::i()->sidebar['actions']['build'] = array(
                    'icon' => 'download',
                    'title' => 'download',
                    'link' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->application->directory}&do=download"),
                    'data' => array(
                        'controller' => 'system.buildApp',
                        'downloadURL' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->application->directory}&do=download&type=download"),
                        'buildURL' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->application->directory}&do=download&type=build"),
                    )
                );
            }
			
			\IPS\Output::i()->title		= $this->application->_title;
			\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $this->activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}" ) );
		}
	}
	
	/**
	 * Modules: Show Modules
	 *
	 * @param	string	$location	Location (e.g. "admin" or "front")
	 * @return	string	HTML to display
	 */
	protected function _manageModules( $location )
	{
		/* Get modules */
		$appKey = $this->application->directory;
		$modules = $this->_getModules();

		/* Are we setting a default? */
		if ( isset( \IPS\Request::i()->default ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			$modules[ $location ][ \IPS\Request::i()->root ]['default_controller'] = mb_substr( \IPS\Request::i()->default, 0, -4 );
			$this->_writeModules( $modules );
			
			$module = \IPS\Application\Module::get( $this->application->directory, \IPS\Request::i()->root, $location );
			$module->default_controller = mb_substr( \IPS\Request::i()->default, 0, -4 );
			$module->save();
		}
		
		/* Or deleting a controller? */
		if ( isset( \IPS\Request::i()->delete ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			if ( @unlink( \IPS\ROOT_PATH . "/applications/{$appKey}/modules/{$location}/" . \IPS\Request::i()->root . '/' . \IPS\Request::i()->delete ) === FALSE )
			{
				\IPS\Output::i()->error( 'dev_could_not_write_controller', '1C103/I', 403, '' );
			}

			/* delete all the other stuff */

			$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
			if (isset ( $restrictions[ \IPS\Request::i()->root ][ mb_substr(\IPS\Request::i()->delete,0 ,-4 )] ) )
			{
				unset($restrictions[ \IPS\Request::i()->root ][ mb_substr(\IPS\Request::i()->delete,0 ,-4 )] );
				$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
			}

			$menu = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json" );
			if (isset ( $menu[ \IPS\Request::i()->root ][ mb_substr(\IPS\Request::i()->delete,0 ,-4 )] ) )
			{
				unset($menu[ \IPS\Request::i()->root ][ mb_substr(\IPS\Request::i()->delete,0 ,-4 )] );
				$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json", $menu );
			}


			if ( \IPS\Request::i()->isAjax() )
			{
				return;
			}
		}
		
		/* Show tree */
		$url = \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&tab=modules-{$location}" );
		return new \IPS\Helpers\Tree\Tree(
			$url,
			\IPS\Member::loggedIn()->language()->addToStack('dev_modules', FALSE, array( 'sprintf' => array( ucwords( $location ) ) ) ),
			/* Get Roots */
			function() use ( $appKey, $location, $modules, $url )
			{
				$rows = array();

				if( !empty($modules[ $location ]) AND \is_array($modules[ $location ]) )
				{
					foreach ( $modules[ $location ] as $k => $module )
					{
						$rows[ $k ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, $k, $k, TRUE, array(
							'default'=> array(
								'icon'		=> ( array_key_exists( 'default', $module ) ) ? ( $module['default'] ? 'star' : 'star-o' ) : 'star-o',
								'title'		=> 'make_default_module',
								'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=setDefaultModule&id={$module['id']}&location={$location}" )->csrf(),
							 ),
							'add'	=> array(
								'icon'		=> 'plus-circle',
								'title'		=> 'modules_add_controller',
								'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=addController&module_key={$k}&location={$location}" ),
								'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('modules_add_controller') )
							),
							'edit'	=> array(
								'icon'		=> 'pencil',
								'title'		=> 'edit',
								'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=moduleForm&key={$k}&location={$location}" ),
								'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
								'hotkey'	=> 'e'
							),
							'delete'	=> array(
								'icon'		=> 'times-circle',
								'title'		=> 'delete',
								'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=deleteModule&key={$k}&location={$location}" ),
								'data'		=> array( 'delete' => '' )
							)
						), "", NULL, NULL );
					}
				}
				return $rows;
			},
			/* Get Row */
			function( $key, $root=FALSE ) use ( $url, $appKey, $location )
			{
				return \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, $key, $key, TRUE, array(
					'add'	=> array(
						'icon'		=> 'plus-circle',
						'title'		=> 'modules_add_controller',
						'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=addController&module_key={$key}&location={$location}" ),
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('modules_add_controller') )
					),
					'edit'	=> array(
						'icon'		=> 'pencil',
						'title'		=> 'edit',
						'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=moduleForm&key={$key}&location={$location}" ),
						'data' 		=> array( 'ipDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
						'hotkey'	=> 'e'
					),
					'delete'	=> array(
						'icon'		=> 'times-circle',
						'title'		=> 'delete',
						'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=deleteModule&key={$key}&location={$location}" ),
						'data'		=> array( 'delete' => '' )
					)
				), '', NULL, NULL, $root );
			},
			/* Get Row's Parent ID */
			function( $id )
			{
				return NULL;
			},
			/* Get Children */
			function( $key ) use ( $appKey, $location, $modules, $url )
			{
				$rows = array();
				foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$appKey}/modules/{$location}/{$key}" ) as $controller )
				{
					if ( $controller->isFile() and \substr( $controller, 0, 1 ) !== '.'  and $controller->getFilename() !== 'index.html' )
					{
						$rows[] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, $controller, $controller, FALSE, array(
							'default'	=> array(
								'icon'		=> ( str_replace( '.php', '', $controller ) == $modules[ $location ][ $key ]['default_controller'] ) ? 'star' : 'star-o',
								'title'		=> 'modules_make_default',
								'link'		=> $url->setQueryString( array( 'root' => $key, 'default' => (string) $controller ) )->csrf(),
							),
							'delete'	=> array(
								'icon'		=> 'times-circle',
								'title'		=> 'delete',
								'link'		=> $url->setQueryString( array( 'root' => $key, 'delete' => (string) $controller ) )->csrf(),
								'data'		=> array( 'delete' => '' )
							)
						), '', NULL, NULL, FALSE, NULL, NULL, ( $modules[ $location ][ $key ]['default_controller'] == $controller ? array( 'green', 'modules_default' ) : NULL ) );
					}
				}
				return $rows;
			},
			/* Get Root Buttons */
			function() use ( $appKey, $location )
			{
				return array(
					'add'	=> array(
						'icon'		=> 'plus',
						'title'		=> 'modules_add',
						'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=moduleForm&key=&location={$location}" ),
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('modules_add') )
					),
				);
			},
			FALSE,
			TRUE,
			TRUE
		);
	}
	
	/**
	 * Make this the default module
	 *
	 * @return void
	 */
	public function setDefaultModule()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$module	= \IPS\Application\Module::load( \IPS\Request::i()->id );
			$module->setAsDefault();

			$this->_writeModules( $this->_getModules() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_for_default', '2C133/A', 403, '' );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$module->application}&tab=modules-{$module->area}" ), 'saved' );
	}
	
	
	/**
	 * Add/Edit Module
	 *
	 * @return	void
	 */
	protected function moduleForm()
	{
		/* Get JSON */
		$modules = $this->_getModules();
		$application = $this->application;
		$location = \IPS\Request::i()->location;
		
		/* Load existing module if we're editing */
		if ( \IPS\Request::i()->key )
		{
			if ( !isset( $modules[ $location ][ \IPS\Request::i()->key ] ) )
			{
				\IPS\Output::i()->error( 'node_error', '2C103/J', 404, '' );
			}
			$current = array( 'module_key' => \IPS\Request::i()->key, 'protected' => $modules[ $location ][ \IPS\Request::i()->key ]['protected'], 'default_controller' => $modules[ $location ][ \IPS\Request::i()->key ]['default_controller'] );
		}
		else
		{
			$current = array( 'module_key' => NULL, 'protected' => FALSE, 'default_controller' => NULL );
		}
		
		/* Build the form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Text( 'module_key', $current['module_key'], TRUE, array( 'maxLength' => 32 ), function( $val ) use ( $application, $modules, $location, $current )
		{
			if ( !preg_match( '/^[a-z]*$/', $val ) )
			{
				throw new \DomainException( 'module_key_bad' );
			}
			
			try
			{
				$module = ( !$current['module_key'] OR $current['module_key'] != $val ) ? \IPS\Application\Module::load( $val, 'sys_module_key', array( 'sys_module_application=? and sys_module_area=?', $application->directory, $location ) ) : NULL;
				if( $module === NULL )
				{
					throw new \OutOfRangeException;
				}

				throw new \DomainException( 'module_key_exists' );
			}
			catch ( \OutOfRangeException $e ) {}
		} ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'module_protected', $current['protected'], TRUE ) );
		if ( \IPS\Request::i()->key )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'module_default_controller', $current['default_controller'] ) );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'modules_add', $form, FALSE );
		if ( \IPS\Request::i()->isAjax() )
		{
			return;
		}

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			if( $current['module_key'] )
			{
				$module = \IPS\Application\Module::get( $this->application->directory, $current['module_key'], $location );
			}
			else
			{
				$module = new \IPS\Application\Module;
				$module->application		= $this->application->directory;
				$module->area				= $location;
			}

			$module->key				= $values['module_key'];
			$module->protected			= $values['module_protected'];
			$module->default_controller	= isset( $values['module_default_controller'] ) ? $values['module_default_controller'] : '';
			$module->save();
			
			$modules[ $location ][ $module->key ] = array(
				'default_controller'	=> $module->default_controller,
				'protected'				=> $module->protected,
				'default'				=> $module->default
			);

			if( $current['module_key'] AND $current['module_key'] != $module->key )
			{
				unset( $modules[ $location ][ $current['module_key'] ] );
			}
						
			$this->_writeModules( $modules );
			
			if( $current['module_key'] )
			{
				$oldDir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/modules/{$location}/{$current['module_key']}";
				$newDir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/modules/{$location}/{$module->key}";
				@rename( $oldDir, $newDir );
				chmod( $newDir, \IPS\IPS_FOLDER_PERMISSION );
			}
			else
			{
				$dir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/modules/{$location}/{$module->key}";
				@mkdir( $dir );
				chmod( $dir, \IPS\IPS_FOLDER_PERMISSION );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=modules-{$location}" ), 'saved' );
		}
	}
	
	/**
	 * Delete Module
	 *
	 * @return	void
	 */
	protected function deleteModule()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		/* Load the module */
		try
		{
			$module = \IPS\Application\Module::get( $this->application->directory, \IPS\Request::i()->key, \IPS\Request::i()->location );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C103/K', 404, '' );
		}
	
		/* Remove it from the JSON */
		$modules = $this->_getModules();
		unset( $modules[ $module->area ][ $module->key ] );
		$this->_writeModules( $modules );
		
		/* Delete it */
		$location = $module->area;
		$module->delete();
						
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=modules-{$location}" ) );
	}
	
	/**
	 * Manage ACP Menu
	 *
	 * @return	string
	 */
	protected function _manageAcpmenu()
	{
		$modules = $this->_getModules();
		$url = \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acpmenu" );
		$menu = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json" );
		$appKey = $this->application->directory;

		/* Handle reordering */
		if ( \IPS\Request::i()->do === 'reorder' )
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Normalise AJAX vs non-AJAX */			
			if( isset( \IPS\Request::i()->ajax_order ) )
			{
				$order = array();
				$position = array();
				foreach( \IPS\Request::i()->ajax_order as $id => $parent )
				{
					/* We have to fudge children "ids" to prevent conflicts in the array */
					$id = str_replace( 's@', '', $id );

					if ( !isset( $order[ $parent ] ) )
					{
						$order[ $parent ] = array();
						$position[ $parent ] = 1;
					}
					$order[ $parent ][ $id ] = $position[ $parent ]++;
				}
			}
			/* Non-AJAX way */
			else
			{
				$order = array( \IPS\Request::i()->root ?: 'null' => \IPS\Request::i()->order );
			}

			/* Sort */
			$_menu = $menu;
			$menu = array();

			if( isset( $order['null'] ) )
			{
				foreach( $order['null'] as $key => $position )
				{
					foreach ( $_menu as $_parent => $_items )
					{
						if ( $key == $_parent )
						{
							$menu[ $_parent ] = $_menu[ $_parent ];
							break;
						}
					}
				}
			}

			foreach( $_menu as $root => $items )
			{
				/* If we were sorting one level of the menu, and this is not it, then leave it as is */
				if( isset( \IPS\Request::i()->root ) and \IPS\Request::i()->root != $root )
				{
					$menu[ $root ] = $items;
				}
				elseif( isset( $order[ $root ] ) )
				{
					$menu[ $root ] = array();
					foreach( $order[ $root ] as $key => $position )
					{
						$menu[ $root ][ $key ] = $_menu[ $root ][ $key ];
					}
				}
			}

			/* Write */
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json", $menu );

			if ( \IPS\Request::i()->isAjax() )
			{
				return;
			}
		}

		/* Display the table */
		return new \IPS\Helpers\Tree\Tree( $url, 'dev_acpmenu',
			/* Get Roots */
			function () use ( $url, $appKey, $menu )
			{
				$rows	= array();
				$order	= 1;
				foreach ( array_keys($menu) as $k )
				{
					$lang = "menu__{$appKey}_{$k}";
					$rows[ $k ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, $k, \IPS\Member::loggedIn()->language()->addToStack( $lang ), isset( $menu[ $k ] ), array(
						'add'	=> array(
							'icon'	=> 'plus-circle',
							'title'	=> 'acpmenu_add',
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=menuForm&module_key={$k}" ),
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acpmenu_add') )
						)
					), "app={$appKey}&amp;module={$k}", NULL, $order );
					$order++;
				}
				return $rows;
			},
			/* Get Row */
			function ( $k, $root ) use ( $url, $menu, $appKey )
			{
				$lang = "menu__{$appKey}_{$k}";
				return \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, $k, \IPS\Member::loggedIn()->language()->addToStack( $lang ), isset( $menu[ $k ] ), array(
						'add'	=> array(
							'icon'	=> 'plus-circle',
							'title'	=> 'acpmenu_add',
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=menuForm&module_key={$k}" ),
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acpmenu_add') )
						)
					), "app={$appKey}&amp;module={$k}", NULL, NULL, $root );
			},
			/* Get Row Parent */
			function ()
			{
				return NULL;
			},
			/* Get Children */
			function ( $k ) use ( $url, $menu, $appKey )
			{
				$rows = array();
				$pos = 0;
				foreach ( $menu[ $k ] as $id => $row )
				{
					$lang = "menu__{$appKey}_{$k}_{$id}";
					$rows[ 's@' . $id ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, 's@' . $id, \IPS\Member::loggedIn()->language()->addToStack( $lang ), FALSE, array(
						'edit'	=> array(
							'icon'	=> 'pencil',
							'title'	=> 'edit',
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=menuForm&module_key={$k}&id={$id}" ),
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
							'hotkey'=> 'e'
						),
						'delete'	=> array(
							'icon'	=> 'times-circle',
							'title'	=> 'delete',
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=menuDelete&module_key={$k}&id={$id}" ),
							'data'	=> array( 'delete' => '' )
						),
					), "app={$appKey}&amp;module={$k}&amp;controller={$row['controller']}" . ( $row['do'] ? "&amp;do={$row['do']}" : '' ), NULL, ++$pos, FALSE, NULL, NULL, NULL, FALSE, FALSE, FALSE );
				}
				return $rows;
			},
			NULL,
			FALSE,
			FALSE,
			TRUE
		);
	}
	
	/**
	 * Menu Form Item
	 *
	 * @return	void
	 */
	protected function menuForm()
	{
		/* Current Menu */
		$menu = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json" );
	
		/* Get module and controllers */
		$module = $this->_loadModule();
		
		/* And controllers */
		$controllers = array();
		foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/modules/{$module->area}/{$module->key}" ) as $file )
		{
			if ( !$file->isDot() and mb_substr( $file, -4 ) === '.php' )
			{
				$controllers[ mb_substr( $file, 0, -4 ) ] = (string) $file;
			}
		}
		
		/* And restrictions */
		$restrictions = $this->_getRestrictions( $module );
		
		/* And tabs */
		$tabs = $this->_getAcpMenuTabs();
		
		/* Load existing */
		$current = NULL;
		if ( \IPS\Request::i()->id and isset( $menu[ $module->key ][ \IPS\Request::i()->id ] ) )
		{
			$current = $menu[ $module->key ][ \IPS\Request::i()->id ];
		}
		
		/* Show Form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Select( 'acpmenu_controller', ( $current ? $current['controller'] : NULL ), TRUE, array( 'options' => $controllers ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'acpmenu_tab', ( $current ? $current['tab'] : $module->application ), TRUE, array( 'autocomplete' => array(
			'source' 	=> 	$tabs,
			'maxItems'	=> 1,
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'acpmenu_doaction', ( $current ? $current['do'] : NULL ), FALSE, array(), NULL, 'do=' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'acpmenu_restriction', ( $current ? explode( ',', $current['restriction'] ) : '' ), FALSE, array( 'options' => $restrictions, 'multiple' => true ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$menu[ $module->key ][ $values['acpmenu_controller'] . ( $values['acpmenu_doaction'] ? "_{$values['acpmenu_doaction']}" : '' ) ] = array(
				'tab'			=> $values['acpmenu_tab'],
				'controller'	=> $values['acpmenu_controller'],
				'do'			=> $values['acpmenu_doaction'],
				'restriction'	=> \count( $values['acpmenu_restriction'] ) ? implode( ',', $values['acpmenu_restriction'] ) : '',
				'subcontrollers'	=> ""
				);
			
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json", $menu );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acpmenu&root={$module->key}" ) );
		}
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'acpmenu_add', $form, FALSE );
	}
	
	/**
	 * Delete Menu Item
	 *
	 * @return	void
	 */
	protected function menuDelete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		/* Get Menu */
		$menu = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json" );
	
		/* Get module and controllers */
		$module = $this->_loadModule();
		
		/* Delete It */
		unset( $menu[ $module->key ][ \IPS\Request::i()->id ] );
		$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json", $menu );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acpmenu&root={$module->key}" ) );
	}
	
	/**
	 * Manage ACP Restrictions
	 *
	 * @return	string
	 */
	protected function _manageAcprestrictions()
	{
		$this->modules = $this->_getModules();
		$this->url = \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acprestrictions" );
		$this->restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
		$appKey = $this->application->directory;
		
		/* Handle reordering */
		if ( \IPS\Request::i()->do === 'reorder' )
		{
			\IPS\Session::i()->csrfCheck();

			/* Figure out the items we are reordering, we would only be reordering a single group */
			$newOrder = array();
			$position = 1;
			foreach( \IPS\Request::i()->ajax_order as $i => $parent )
			{
				if( \strpos( $i, '~' ) !== FALSE )
				{
					if( !isset( $newOrder[ $parent ] ) )
					{
						$newOrder[ $parent ] = array();
					}
					$newOrder[ $parent ][ $i ] = $position++;
				}
			}

			foreach( $newOrder as $groupKey => $order )
			{
				if( isset( \IPS\Request::i()->ajax_order[ $groupKey ] ) )
				{
					$moduleKey = \IPS\Request::i()->ajax_order[ $groupKey ];

					/* Sort */
					uasort( $this->restrictions[ $moduleKey ][ $groupKey ], function( $a, $b ) use ( $order ) {
						return ( isset( $order[ str_replace( '_', '~', $a ) ] ) ? $order[ str_replace( '_', '~', $a ) ] : 0 ) - ( isset( $order[ str_replace( '_', '~', $b ) ] ) ? $order[ str_replace( '_', '~', $b ) ] : 0 );
					});
				}
			}
			
			/* Write */
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $this->restrictions );

			if ( \IPS\Request::i()->isAjax() )
			{
				return;
			}
		}
		
		/* Display the table */
		return new \IPS\Helpers\Tree\Tree( $this->url, 'dev_acprestrictions', array( $this, 'restrictionsGetRoots' ), array( $this, 'restrictionsGetRow' ),
			function ( $k )
			{
				if ( mb_substr( $k, 0, 1 ) === 'M' )
				{
					return NULL;
				}
				else
				{
					return 'M' . mb_substr( $k, 1, mb_strpos( $k, '-' ) - 1 );
				}
			},
			array( $this, 'restrictionsGetchildren' ),
			NULL,
			FALSE,
			TRUE,
			TRUE
		);
	}
	
	/**
	 * Restrictions: Get Root Rows
	 *
	 * @return	array
	 */
	public function restrictionsGetRoots()
	{
		$rows = array();

		if( !empty( $this->modules['admin'] ) )
		{
			foreach ( $this->modules['admin'] as $k => $module )
			{
				if ( empty( $module['protected'] ) )
				{
					$rows[ $k ] = $this->_restrictionsModuleRow( $this->url, $k, $this->restrictions, FALSE );
				}
			}
		}
		return $rows;
	}
	
	/**
	 * Restrictions: Get Individual Row
	 *
	 * @param	string	$k		ID
	 * @param	bool	$root	Is root row?
	 * @return	string
	 */
	public function restrictionsGetRow( $k, $root )
	{
		if ( mb_substr( $k, 0, 1 ) === 'M' )
		{
			return $this->_restrictionsModuleRow( $this->url, mb_substr( $k, 1 ), $this->restrictions, $root );
		}
		else
		{
			$moduleKey = mb_substr( $k, 1, mb_strpos( $k, '-' ) - 1 );
			$groupKey = mb_substr( $k, mb_strpos( $k, '-' ) + 1 );
			return $this->_restrictionsGroupRow( $this->url, $groupKey, $moduleKey, $this->restrictions[ $moduleKey ][ $groupKey ], $root );
		}
	}
	
	/**
	 * Restrictions: Get Child Rows
	 *
	 * @param	string	$k	ID
	 * @return	array
	 */
	public function restrictionsGetChildren( $k )
	{
		$rows = array();
		
		if ( mb_substr( $k, 0, 1 ) === 'M' )
		{
			$k = mb_substr( $k, 1 );
			foreach ( $this->restrictions[ $k ] as $groupKey => $r )
			{
				$rows[ $groupKey ] = $this->_restrictionsGroupRow( $this->url, $groupKey, $k, $r, FALSE );
			}
		}
		else
		{
			$moduleKey = mb_substr( $k, 1, mb_strpos( $k, '-' ) - 1 );
			$groupKey = mb_substr( $k, mb_strpos( $k, '-' ) + 1 );
			
			$pos = 0;
			foreach ( $this->restrictions[ $moduleKey ][ $groupKey ] as $rKey )
			{
				$lang = "r__{$rKey}";
				$rows[ str_replace( '_', '~', $rKey ) ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $this->url, $rKey, \IPS\Member::loggedIn()->language()->addToStack( $lang ), FALSE, array(
					'delete' => array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=restrictionRowDelete&module_key={$moduleKey}&group={$groupKey}&id={$rKey}" ),
						'data'	=> array( 'delete' => '' )
					)
				), $rKey, NULL, ++$pos );
			}
		}
		
		return $rows;
	}
	
	/**
	 * Get Module Row
	 *
	 * @param	string	$url			URL
	 * @param	string	$k				Key
	 * @param	array	$restrictions	Restrictions JSON
	 * @param	bool	$root			As root?
	 * @return	string
	 */
	protected function _restrictionsModuleRow( $url, $k, $restrictions, $root )
	{
		return \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, 'M'.$k, $k, isset( $restrictions[ $k ] ), array(
			'add'	=> array(
				'icon'	=> 'plus-circle',
				'title'	=> 'acprestrictions_addgroup',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=restrictionGroupForm&module_key={$k}&id=0" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_addgroup') )
			)
		), '', NULL, NULL, $root );
	}
	
	/**
	 * Get Group Row
	 *
	 * @param	string	$url		URL
	 * @param	string	$groupKey	Key
	 * @param	string	$moduleKey	Module Key
	 * @param	array	$r			Rows in this group
	 * @param	bool	$root		As root?
	 * @return	string
	 */
	protected function _restrictionsGroupRow( $url, $groupKey, $moduleKey, $r, $root )
	{
		$lang = "r__{$groupKey}";
		return \IPS\Theme::i()->getTemplate( 'trees' )->row( $url, "G{$moduleKey}-{$groupKey}", \IPS\Member::loggedIn()->language()->addToStack( $lang ), !empty( $r ), array(
			'add'	=> array(
				'icon'	=> 'plus-circle',
				'title'	=> 'acprestrictions_addrow',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=restrictionRowForm&module_key={$moduleKey}&group={$groupKey}" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_addrow') )
			),
			'edit'	=> array(
				'icon'	=> 'pencil',
				'title'	=> 'edit',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=restrictionGroupForm&module_key={$moduleKey}&id={$groupKey}" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') ),
				'hotkey'=> 'e'
			),
			'delete'=> array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=restrictionGroupDelete&module_key={$moduleKey}&id={$groupKey}" ),
				'data'	=> array( 'delete' => '' )
			)
		), '', NULL, NULL, $root );
	}
	
	/**
	 * Restriction Group Form
	 *
	 * @return	void
	 */
	public function restrictionGroupForm()
	{
		/* Get restriction data */
		$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
		
		/* Load module */
		$module = $this->_loadModule();
		
		/* Do we have a default value? */
		$current = NULL;
		if ( \IPS\Request::i()->id and isset( $restrictions[ $module->key ][ \IPS\Request::i()->id ] ) )
		{
			$current = \IPS\Request::i()->id;
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Text( 'acprestrictions_groupkey', $current ?: '', TRUE ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$rows = array();
			if ( $current !== NULL )
			{
				$rows = $restrictions[ $module->key ][ $current ];
				unset( $restrictions[ $module->key ][ $current ] );
			}
		
			$restrictions[ $module->key ][ $values['acprestrictions_groupkey'] ] = $rows;
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acprestrictions&root=M{$module->key}" ) );
		}
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'acprestrictions_addgroup', $form, FALSE );
	}
	
	/**
	 * Delete Restriction Group
	 *
	 * @return	void
	 */
	public function restrictionGroupDelete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
		$module = $this->_loadModule();
		
		if( isset( $restrictions[ $module->key ][ \IPS\Request::i()->id ] ) )
		{
			unset( $restrictions[ $module->key ][ \IPS\Request::i()->id ] );
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acprestrictions&root=M{$module->key}" ) );
	}
	
	/**
	 * Restriction Row Form
	 *
	 * @return	void
	 */
	protected function restrictionRowForm()
	{
		$module = $this->_loadModule();
		$group = \IPS\Request::i()->group;
		
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Text( 'acprestrictions_rowkey', NULL, TRUE ) );
		if ( $values = $form->values() )
		{
			$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
			$restrictions[ $module->key ][ $group ][ $values['acprestrictions_rowkey'] ] = $values['acprestrictions_rowkey'];
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acprestrictions&root=G{$module->key}-{$group}" ) );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'acprestrictions_addrow', $form, FALSE );
	}
	
	/**
	 * Delete Restriction Row
	 *
	 * @return	void
	 */
	public function restrictionRowDelete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
		$module = $this->_loadModule();
		$group = \IPS\Request::i()->group;
		$id = \IPS\Request::i()->id;
		
		if( isset( $restrictions[ $module->key ][ $group ][ $id ] ) )
		{
			unset( $restrictions[ $module->key ][ $group ][ $id ] );
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=acprestrictions&root=G{$module->key}-{$group}" ) );
	}
	
	/**
	 * Create Controller
	 *
	 * @return	void
	 */
	protected function addController()
	{
		$module = $this->_loadModule();
		$targetDir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/modules/" . \IPS\Request::i()->location . '/' . $module->key . '/';
	
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Text( 'filename', NULL, TRUE, array(), function( $val ) use ( $targetDir )
		{
			if ( file_exists( $targetDir . $val . '.php' ) )
			{
				throw new \DomainException( 'modules_controller_exists' );
			}
		}, NULL, '.php' ) );
		
		if ( \IPS\Request::i()->location === 'admin' )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'type', NULL, TRUE, array(
				'options' => array(
					'blank'	=> 'controllertype_blank',
					'node'	=> 'controllertype_node',
					'list'	=> 'controllertype_list',
				),
				'toggles'	=> array(
					'node'	=> array( 'model_name' ),
					'list'	=> array( 'database_table_name' ),
				)
				) ) );

			$form->add( new \IPS\Helpers\Form\Text( 'model_name', NULL, FALSE, array(),NULL, NULL, NULL, 'model_name' ) );

			$form->add( new \IPS\Helpers\Form\Text( 'database_table_name', NULL, FALSE, array(),NULL, NULL, NULL, 'database_table_name' ) );

			$form->add( new \IPS\Helpers\Form\Text( 'acpmenu_tab', $this->application->directory, FALSE, array( 'autocomplete' => array(
				'source'	=> $this->_getAcpMenuTabs(),
				'maxItems'	=> 1
			) ) ) );
			$form->add( new \IPS\Helpers\Form\Select( 'acpmenu_restriction', '__create', FALSE, array( 'options' => array_merge( array(
				''			=> 'acpmenu_norestriction',
				'__create'	=> 'controller_rcreate',
			), $this->_getRestrictions( $module ) ) ) ) );
		}
		
		if ( $values = $form->values() )
		{
			if( !isset($values['type']) )
			{
				$values['type']	= 'blank';
			}

			/* Create a restriction? */
			$restriction = NULL;
			if ( isset( $values['acpmenu_restriction'] ) and $values['acpmenu_restriction'] )
			{
				if ( $values['acpmenu_restriction'] === '__create' )
				{
					$restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
					$restrictions[ $module->key ][ $values['filename'] ]["{$values['filename']}_manage"] = "{$values['filename']}_manage";
					$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json", $restrictions );
					$restriction = "{$values['filename']}_manage";
				}
				else
				{
					$restriction = $values['acpmenu_restriction'];
				}
			}
		
			/* Work out the contents */
			$contents = str_replace(
				array(
					'{controller}',
					"{subpackage}",
					'{date}',
					'{app}',
					'{module}',
					'{location}',
					'{restriction}',
					'{node_model}',
					'{table_name}'
				),
				array(
					$values['filename'],
					( $this->application->directory != 'core' ) ? ( " * @subpackage\t" . \IPS\Member::loggedIn()->language()->get( "__app_{$this->application->directory}" ) ) : '',
					date( 'd M Y' ),
					$this->application->directory,
					\IPS\Request::i()->module_key,
					\IPS\Request::i()->location,
					$restriction ? '\IPS\Dispatcher::i()->checkAcpPermission( \''.$restriction.'\' );' : '',
					isset( $values['model_name'] ) ? $values['model_name'] : NULL,
					isset( $values['database_table_name'] ) ? $values['database_table_name'] : NULL,
				),
				file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/defaults/Controller" . mb_ucfirst( $values['type'] ) . '.txt' )
			);
			
			/* If this isn't an IPS app, strip out our header */
			if ( !\in_array( $this->application->directory, \IPS\IPS::$ipsApps ) )
			{
				$contents = preg_replace( '/(<\?php\s)\/*.+?\*\//s', '$1', $contents );
			}
		
			/* Write */
			if( @\file_put_contents( $targetDir . $values['filename'] . '.php', $contents ) === FALSE )
			{
				\IPS\Output::i()->error( 'dev_could_not_write_controller', '1C103/H', 403, '' );
			}

			@chmod( $targetDir . $values['filename'] . '.php', \IPS\FILE_PERMISSION_NO_WRITE );
			
			/* Add to the menu? */
			if ( isset( $values['acpmenu_tab'] ) and $values['acpmenu_tab'] )
			{
				$menu = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json" );
				$menu[ $module->key ][ $values['filename'] ] = array(
					'tab'			=> $values['acpmenu_tab'],
					'controller'	=> $values['filename'],
					'do'			=> '',
					'restriction'	=> $restriction,
					'subcontrollers'	=> ''
					);
				
				$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acpmenu.json", $menu );
			}
			
			/* Boink */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=modules-" . \IPS\Request::i()->location . "&root=" . \IPS\Request::i()->module_key ) );
		}
				
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'modules_add_controller', $form, FALSE );
	}

	/**
	 * Database Schema: Show Tables
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageSchema()
	{
		/* Create the file if it doesn't exist */
		$json = $this->_getSchema();
								
		/* Build list table */
		$table = new \IPS\Helpers\Table\Custom( $json, \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=schema" ) );
		$table->langPrefix = 'database_table_';
		$table->mainColumn = 'name';
		$table->limit	   = 150;
		$table->include = array( 'name' );
			
		/* Set default sort */
		$table->sortBy = $table->sortBy ?: 'name';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		
		/* Add the "add" button */
		$table->rootButtons = array(
			'add' => array(
				'icon'	=> 'plus',
				'title'	=> 'database_table_create',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addTable" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_table_create') )
			)
		);
		
		/* Add the buttons for each row */
		$appKey = $this->application->directory;
		$table->rowButtons = function( $row ) use ( $appKey )
		{
			return array(
				'edit' => array(
					'icon'	=> 'pencil',
					'title'	=> 'database_table_edit',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=editSchema&_name={$row['name']}" ),
					'hotkey'=> 'e'
				),
				'delete' => array(
					'icon'	=> 'times-circle',
					'title'	=> 'database_table_delete',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=deleteTable&name={$row['name']}" ),
					'data'	=> array( 'delete' => '', 'delete-warning' => \IPS\Member::loggedIn()->language()->addToStack( 'database_droptable_info' ) )
				)
			);
		};
		
		/* Return */
		return (string) $table;
	}
	
	/**
	 * Database Schema: Add Table
	 *
	 * @return	void
	 */
	protected function addTable()
	{
		/* Get our current working version queries */
		$queriesJson = $this->_getQueries( 'working' );
		
		/* Get form */
		$message = NULL;
		$activeTab = \IPS\Request::i()->tab ?: 'new';
		$form = new \IPS\Helpers\Form( "database_table_{$activeTab}" );
		switch ( $activeTab )
		{
			/* Create New */
			case 'new':
				$form->add( new \IPS\Helpers\Form\Text(
					'database_table_name',
					NULL,
					TRUE,
					array(
						'maxLength' => ( 64 - \strlen( "{$this->application->directory}_" ) )
					),
					function( $value )
					{
						if( \IPS\Db::i()->checkForTable( \IPS\Request::i()->appKey . '_' . $value ) === TRUE )
						{
							throw new \DomainException( 'database_table_exists' );
						}
					},
					"{$this->application->directory}_"
				) );
				$message = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('database_newtable_info'), 'information' );
				break;
				
			/* Import */
			case 'import':
			
				/* Fetch tables */
				$tables = array();
				$stmt = \IPS\Db::i()->query( "SHOW TABLES;" );
				while ( $row = $stmt->fetch_assoc() )
				{
					$tableName = array_pop( $row );
					$tables[ $tableName ] = $tableName;
				}
				
				/* Add the form element */
				$form->add( new \IPS\Helpers\Form\Select(
					'database_table_import',
					NULL,
					TRUE,
					array( 'options' => $tables, 'parse' => 'normal' )
				) );
				
				/* Warn the user we may rename the table */
				$message = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('database_renametable_info', FALSE, array( 'sprintf' => array( "{$this->application->directory}_" ) ) ), 'information' );
			
				break;
				
			/* Upload */
			case 'upload':
				$appKey = $this->application->directory;
				$form->add( new \IPS\Helpers\Form\Upload(
					'upload',
					NULL,
					TRUE,
					array( 'allowedFileTypes' => array( 'sql' ), 'temporary' => TRUE ),
					function( $value ) use ( $appKey )
					{
						/* Get contents and remove comments */
						$contents = \IPS\Db::stripComments( file_get_contents( $value ) );
						
						/* If there's more than one ; character - reject it */
						if( mb_substr_count( $contents, ';' ) > 1 )
						{
							throw new \DomainException( 'database_upload_too_many_queries' );
						}
							
						/* Is it a CREATE TABLE statement */
						preg_match( '/^CREATE (TEMPORARY )?TABLE (IF NOT EXISTS )?`?(.+?)`?\s+?\(/i', $contents, $matches );
						if( empty( $matches ) or !$matches[3] )
						{
							throw new \DomainException( 'database_upload_no_create' );
						}
						
						/* Does the table already exist? */
						if ( mb_substr( $matches[3], 0, mb_strlen( $appKey ) + 1 ) !== "{$appKey}_" )
						{
							$matches[3] = "{$appKey}_{$matches[3]}";
						}
						if( \IPS\Db::i()->checkForTable( $matches[3] ) )
						{
							throw new \DomainException( 'database_table_exists' );
						}
					}
				) );
				$message = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('database_newtable_info'), 'information' );
				break;
		}
		
		/* Has the form been submitted? */
		if( $values = $form->values() )
		{
			/* Work out defintion */
			switch ( $activeTab )
			{
				/* New table */
				case 'new':
					/* Set definition */
					$definition = array(
						'name'		=> $this->application->directory . '_' . $values['database_table_name'],
						'columns'	=> array(
							'id' => array(
								'name'				=> 'id',
								'type'				=> 'BIGINT',
								'length'			=> '20',
								'unsigned'			=> TRUE,
								'allow_null'		=> FALSE,
								'default'			=> NULL,
								'auto_increment'	=> TRUE,
								'comment'			=> \IPS\Member::loggedIn()->language()->get('database_default_column_comment')
							),
						),
						'indexes'	=> array(
							'PRIMARY' => array(
								'type'		=> 'primary',
								'name'		=> 'PRIMARY',
								'columns'	=> array( 'id' ),
								'length'	=> array( NULL ),
							),
						),
					);
					
					/* Create table */
					\IPS\Db::i()->createTable( $definition );
					
					/* Add to the queries.json file */
					$queriesJson = $this->_addQueryToJson( $queriesJson, array( 'method' => 'createTable', 'params' => array( $definition ) ) );
					$this->_writeQueries( 'working', $queriesJson );
					
					break;
				
				/* Import existing table */
				case 'import':
					/* Get definition */
					if ( \IPS\Db::i()->prefix AND mb_strpos( $values['database_table_import'], \IPS\Db::i()->prefix ) === 0 )
					{
						$values['database_table_import'] = mb_substr( $values['database_table_import'], mb_strlen( \IPS\Db::i()->prefix ) );
					}
					$definition = \IPS\Db::i()->getTableDefinition( $values['database_table_import'] );

					/* Do we need to rename? */
					if ( mb_substr( $definition['name'], 0, mb_strlen( $this->application->directory ) + 1 ) !== "{$this->application->directory}_" )
					{
						/* Do it */
						\IPS\Db::i()->renameTable( $definition['name'], "{$this->application->directory}_{$definition['name']}" );
						
						/* Add to the queries.json file */
						$queriesJson = $this->_addQueryToJson( $queriesJson,  array( 'method' => 'renameTable', 'params' => array( $definition['name'], "{$this->application->directory}_{$definition['name']}" ) ) );
						$this->_writeQueries( 'working', $queriesJson );
						
						/* Set the name for later */
						$definition['name'] = "{$this->application->directory}_{$definition['name']}";
					}
									
					break;
				
				/* Uploaded .sql file */
				case 'upload':
					/* Get contents */
					$contents = \IPS\Db::stripComments( file_get_contents( $values['upload'] ) );
					
					/* Put the app key in if it's not already */
					$appKey = $this->application->directory;
					$contents = preg_replace_callback( '/CREATE (TEMPORARY )?TABLE (IF NOT EXISTS )?`?(.+?)`?\s+?/i', function( $matches ) use ( $appKey )
					{
						$prefix = '';
						if ( \IPS\Db::i()->prefix AND mb_substr( $matches[3], 0, mb_strlen( \IPS\Db::i()->prefix ) ) !== \IPS\Db::i()->prefix )
						{
							$prefix = \IPS\Db::i()->prefix;
						}
						
						if ( mb_substr( $matches[3], 0, mb_strlen( $appKey ) + 1 ) !== "{$appKey}_" )
						{
							return str_replace( $matches[3], "{$prefix}{$appKey}_{$matches[3]}", $matches[0] );
						}
						
						return $matches[0];
					}, $contents );

					/* Work out the name */
					$prefix = \IPS\Db::i()->prefix;
					preg_match( "/CREATE (TEMPORARY )?TABLE (IF NOT EXISTS )?`?{$prefix}(.+?)`?\s+?\(/i", $contents, $matches );
					$name = $matches[3];

					/* Run the query */
					\IPS\Db::i()->query( $contents );
					
					/* Now get the definition */
					$definition = \IPS\Db::i()->getTableDefinition( $name );

					/* Add to the queries.json file */
					$queriesJson = $this->_addQueryToJson( $queriesJson, array( 'method' => 'createTable', 'params' => array( $definition ) ) );
					$this->_writeQueries( 'working', $queriesJson );
					
					/* Delete the file */
					unlink( $values['upload'] );
					
					break;
			}
			
			/* Add to schema.json */
			$schema = $this->_getSchema();
			$schema[ $definition['name'] ] = $definition;
			$this->_writeSchema( $schema );
			
			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}" ) );
		}
			
		/* If not, show it */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs(
			array(
				'new'		=> 'database_table_new',
				'import'	=> 'database_table_import',
				'upload'	=> 'database_table_upload',
				),
			$activeTab,
			$message . $form,
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addTable&existing=1" )
			);
			
		if( \IPS\Request::i()->isAjax() )
		{
			if( \IPS\Request::i()->existing )
			{
				\IPS\Output::i()->output = $message . $form;
			}
		}
	}

	/**
	 * Database Schema: View/Edit Table
	 *
	 * @return	void
	 */
	protected function editSchema()
	{
		/* Get table definition */
		$schema = $this->_getSchema();
		if ( !isset( $schema[ \IPS\Request::i()->_name ] ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C103/A', 404, '' );
		}
		$definition = \IPS\Db::i()->normalizeDefinition( $schema[ \IPS\Request::i()->_name ] );
					
		/* Init Output */
		\IPS\Output::i()->title = $definition['name'];
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=schema" ), \IPS\Member::loggedIn()->language()->addToStack( 'database_tables' ) );
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('database_changes_info'), 'information' );
		
		/* Does it match the database? */
		$_definition = $definition;
		unset( $_definition['inserts'] );
		unset( $_definition['comment'] );
        unset( $_definition['reporting'] );
		try
		{
			$localDefinition = $this->_getTableDefinition( $definition['name'] );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Db::i()->createTable( $definition );
			$localDefinition = $definition;
		}

		$localDefinition = \IPS\Db::i()->normalizeDefinition( $localDefinition );

		unset( $localDefinition['comment'] );

		if ( $_definition != $localDefinition )
		{
			$string1 = str_replace( array( '&lt;?php', '<br>', '<br />' ), "\n", highlight_string( "<?php\n" . var_export( $_definition, TRUE ), TRUE ) );
			$string2 = str_replace( array( '&lt;?php', '<br>', '<br />' ), "\n", highlight_string( "<?php\n" . var_export( $localDefinition, TRUE ), TRUE ) );
						
			require_once \IPS\ROOT_PATH . "/system/3rd_party/Diff/class.Diff.php";
			$diff = html_entity_decode( \Diff::toTable( \Diff::compare( $string1, $string2 ) ) );
			
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'applications', 'core' )->schemaConflict( $definition['name'], $diff );
			return;
		}
		
		/* Get schema file */
		$schemaJson = $this->_getSchema();
		$_schemaJson = $schemaJson;
		
		/* We'll probably also need the queries.json file */
		$queriesJson = $this->_getQueries( 'working' );
		$_queriesJson = $queriesJson;
		$queries = array();
		
		/* Display "Show Schema" button */
		\IPS\Output::i()->sidebar['actions'] = array(
			'settings'	=> array(
				'title'		=> 'database_show_schema',
				'icon'		=> 'code',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=showSchema&_name={$definition['name']}" ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_show_schema') )
			),
		);
		
		/* Work out tab */
		$activeTab = \IPS\Request::i()->tab ?: 'info';
		
		//-----------------------------------------
		// Info
		//-----------------------------------------
		
		if ( $activeTab === 'info' )
		{
			/* Build Form */
			$output = new \IPS\Helpers\Form();
		
			$output->add( new \IPS\Helpers\Form\Text(
				'database_table_name',
				( mb_substr( $definition['name'], 0, mb_strlen( $this->application->directory ) ) === $this->application->directory ) ? mb_substr( $definition['name'], mb_strlen( $this->application->directory ) + 1 ) : $definition['name'],
				TRUE,
				array( 'maxLength' => ( 64 - mb_strlen( "{$this->application->directory}_" ) ) ),
				NULL,
				"{$this->application->directory}_"
			) );
			
			$output->add( new \IPS\Helpers\Form\Text(
				'database_comment',
				isset( $definition['comment'] ) ? $definition['comment'] : '',
				FALSE,
				array( 'maxLength' => 60, 'size' => 80 )
			) );
			$output->add( new \IPS\Helpers\Form\Select(
				'database_table_engine',
				isset( $definition['engine'] ) ? $definition['engine'] : '',
				FALSE,
				array(
					'options' => array(
						''			=> 'database_table_engine_default',
						'MyISAM'	=> 'MyISAM',
						'InnoDB'	=> 'InnoDB',
						'MEMORY'	=> 'MEMORY',
					)
				),
				function( $v ) use ( $definition )
				{
					$fulltextSupported	= FALSE;

					if( $v AND $v === 'MyISAM' )
					{
						$fulltextSupported	= TRUE;
					}
					else if( ( ( $v AND $v === 'InnoDB' ) OR !$v ) AND \IPS\Db::i()->server_version >= 50600 )
					{
						$fulltextSupported	= TRUE;
					}

					if ( !$fulltextSupported )
					{
						foreach ( $definition['indexes'] as $index )
						{
							if ( $index['type'] === 'fulltext' )
							{
								throw new \DomainException( 'database_table_engine_fulltext' );
							}
						}
					}
				}
			) );
			
			if ( \in_array( $this->application->directory, \IPS\IPS::$ipsApps ) )
			{
				$output->add( new \IPS\Helpers\Form\Select( 'database_table_reporting', isset( $definition['reporting'] ) ? $definition['reporting'] : 'none', FALSE, array( 'options' => array(
					'none'	=> 'database_table_reporting_none',
					'count'	=> 'database_table_reporting_count',
				) ) ) );
			}
			
			/* Handle submissions */
			if ( $values = $output->values() )
			{
				/* Changed the comment? */
				if ( !isset( $definition['comment'] ) or $values['database_comment'] !== $definition['comment']  )
				{
					$schemaJson[ $definition['name'] ]['comment'] = $values['database_comment'];
				}
				
				/* Changed the engine? */
				if ( ( !isset( $definition['engine'] ) and $values['database_table_engine'] ) or ( isset( $definition['engine'] ) and $values['database_table_engine'] != $definition['engine'] ) )
				{
					if ( $values['database_table_engine'] )
					{
						$queries[] = array( 'method' => 'alterTable', 'params' => array( $definition['name'], NULL, $values['database_table_engine'] ) );
						$schemaJson[ $definition['name'] ]['engine'] = $values['database_table_engine'];
					}
					else
					{
						unset( $schemaJson[ $definition['name'] ]['engine'] );
					}
				}
				
				/* Changed reporting? */
				if ( isset( $values['database_table_reporting'] ) AND ( !isset( $definition['reporting'] ) or $values['database_table_reporting'] !== $definition['reporting'] ) )
				{
					$schemaJson[ $definition['name'] ]['reporting'] = $values['database_table_reporting'];
				}
				
				/* Renamed table? */
				$values['database_table_name'] = "{$this->application->directory}_{$values['database_table_name']}";
				if ( $values['database_table_name'] !== $definition['name'] )
				{
					$queries[] = array( 'method' => 'renameTable', 'params' => array( $definition['name'], $values['database_table_name'] ) );
					
					$schemaJson[ $values['database_table_name'] ] = $schemaJson[ $definition['name'] ];
					$schemaJson[ $values['database_table_name'] ]['name'] = $values['database_table_name'];
					unset( $schemaJson[ $definition['name'] ] );
					$definition['name'] = $values['database_table_name'];
				}
								
				/* Run queries */
				foreach ( $queries as $query )
				{
					/* Execute it */
					try
					{
						$method = $query['method'];
						$params = $query['params'];
						\IPS\Db::i()->$method( ...$params );
					}
					catch ( \IPS\Db\Exception $e )
					{
						\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack('database_schema_error', FALSE, array( 'sprintf' => array( $e->query, $e->getCode(), $e->getMessage() ) ) ), '1C103/E', 403, '' );
					}
					
					/* Add it to the queries.json file */
					$queriesJson = $this->_addQueryToJson( $queriesJson, $query );
				}
				
				/* Write the json files if we've changed it */
				$changesMade = !empty( $queries );
				if ( $_schemaJson !== $schemaJson )
				{
					$this->_writeSchema( $schemaJson );
				}
				if ( $_queriesJson !== $queriesJson )
				{
					$this->_writeQueries( 'working', $queriesJson );
				}
				
				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&tab={$activeTab}" ) );
			}
		
		}
		
		//-----------------------------------------
		// Columns
		//-----------------------------------------
		
		elseif ( $activeTab === 'columns' )
		{
			$output = new \IPS\Helpers\Table\Custom( $definition['columns'], \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&existing=1&tab=columns" ) );
			$output->langPrefix = 'database_column_';
			$output->include = array( 'name', 'type', 'length', 'unsigned', 'allow_null', 'default', 'auto_increment', 'comment' );
			$output->limit = 150;
			$output->rootButtons = array(
				'add'	=> array(
					'icon'	=> 'plus',
					'title'	=> 'database_columns_add',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchemaColumn&_name={$definition['name']}" ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_columns_add') )
				),
			);
			
			$appKey = $this->application->directory;
			$output->rowButtons = function( $row ) use ( $definition, $appKey )
			{
				return array(
					'edit'	=> array(
						'icon'	=> 'pencil',
						'title'	=> 'edit',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=editSchemaColumn&_name={$definition['name']}&column={$row['name']}" ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => $row['name'] ),
						'hotkey'=> 'e'
					),
					'delete'	=> array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=editSchemaDeleteColumn&_name={$definition['name']}&column={$row['name']}" )->csrf(),
						'data'	=> array( 'delete' => '' )
					)
				);
			};
			
			$boolParser = function( $val )
			{
				return $val ? '&#10003;' : '&#10007;';
			};
			$output->parsers = array(
				'length'		=> function( $val, $row )
				{
					if ( isset( $row['decimals'] ) AND $row['decimals'] )
					{
						return "{$row['length']},{$row['decimals']}";
					}
					if ( isset( $row['values'] ) AND $row['values'] )
					{
						return implode( '<br>', $row['values'] );
					}
					return $val;
				},
				'unsigned'		=> $boolParser,
				'allow_null'	=> $boolParser,
				'auto_increment'=> $boolParser,
			);
			
			$output = (string) $output . \IPS\Theme::i()->getTemplate('global')->message( 'database_schema_member_columns', 'warning', NULL, TRUE, TRUE );
		}
		
		//-----------------------------------------
		// Indexes
		//-----------------------------------------
		
		elseif ( $activeTab === 'indexes' )
		{
			$output = new \IPS\Helpers\Table\Custom( $definition['indexes'], \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&existing=1&tab=indexes" ) );
			$output->langPrefix = 'database_index_';
			$output->exclude	= array( 'length' );
			$output->limit      = 150;
			$output->rootButtons = array(
				'add'	=> array(
					'icon'	=> 'plus',
					'title'	=> 'database_indexes_add',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchemaIndex&_name={$definition['name']}" ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_indexes_add') )
				),
			);
			
			$appKey = $this->application->directory;
			$output->rowButtons = function( $row ) use ( $definition, $appKey )
			{
				return array(
					'edit'	=> array(
						'icon'	=> 'pencil',
						'title'	=> 'edit',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=editSchemaIndex&_name={$definition['name']}&index={$row['name']}" ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => $row['name'] ),
						'hotkey'=> 'e'
					),
					'delete'	=> array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=editSchemaDeleteIndex&_name={$definition['name']}&index={$row['name']}" )->csrf(),
						'data'	=> array( 'delete' => '' )
					)
				);
			};
			
			$output->parsers = array(
				'type'		=> function( $val )
				{
					return mb_strtoupper( $val );
				},
				'columns'	=> function( $val, $data )
				{
					$output	= array();

					foreach( $data['columns'] as $_idx => $value )
					{
						$output[]	= $value . ' (' . (int) $data['length'][ $_idx ] . ')';
					}

					return implode( '<br>', $output );
				}
			);
		}
		
		//-----------------------------------------
		// Default Inserts
		//-----------------------------------------
		
		elseif ( $activeTab === 'inserts' )
		{
			$keys = [];
			if( isset( $definition['inserts'] ) )
			{
				foreach( $definition['inserts'] as $row )
				{
					$keys = array_keys( $row );
					break;
				}
			}

			$output = new \IPS\Helpers\Table\Custom( isset( $definition['inserts'] ) ? $definition['inserts'] : array(), \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&existing=1&tab=inserts" ) );
			$output->langPrefix = "&zwnj;";
			$output->limit      = 150;

			foreach( $keys as $key )
			{
				$output->parsers[ $key ] = function( $val )
				{
					if ( mb_strlen( $val ) > 100 )
					{
						return mb_substr( $val, 0, 97 ) . '...';
					}

					return $val;
				};
			}

			$output->rootButtons = array(
				'add'	=> array(
					'icon'	=> 'plus',
					'title'	=> 'database_inserts_add',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchemaInsert&_name={$definition['name']}" ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_inserts_add') )
				),
			);
			$self = $this;
			$output->rowButtons = function( $row, $k ) use ( $definition, $self )
			{
				return array(
					'edit'	=> array(
						'icon'	=> 'pencil',
						'title'	=> 'edit',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$self->application->directory}&do=editSchemaInsert&_name={$definition['name']}&row={$k}" ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('database_inserts_edit') ),
						'hotkey'=> 'e'
					),
					'delete'	=> array(
						'icon'	=> 'times-circle',
						'title'	=> 'delete',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$self->application->directory}&do=editSchemaDeleteInsert&_name={$definition['name']}&row={$k}" )->csrf(),
						'data'	=> array( 'delete' => '' )
					)
				);
			};
		}
		
		//-----------------------------------------
		// Display
		//-----------------------------------------
	
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs(
			array(
				'info'		=> 'database_table_settings',
				'columns'	=> 'database_columns',
				'indexes'	=> 'database_indexes',
				'inserts'	=> 'database_inserts'
				),
			$activeTab,
			(string) $output,
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&existing=1" )
			);
			
		if( \IPS\Request::i()->isAjax() )
		{
			if( \IPS\Request::i()->existing )
			{
				\IPS\Output::i()->output = $output;
			}
		}
	}
	
	/**
	 * Get definition from database, ignoring columns added by other apps
	 *
	 * @param	string	$name	Table name
	 * @return	array
	 */
	protected function _getTableDefinition( $name )
	{
		$definition = \IPS\Db::i()->getTableDefinition( $name );
		foreach ( \IPS\Application::applications() as $app )
		{
			$file = \IPS\ROOT_PATH . "/applications/{$app->directory}/setup/install/queries.json";
			if ( file_exists( $file ) )
			{
				foreach( json_decode( file_get_contents( $file ), TRUE ) as $query )
				{
					if ( $query['method'] === 'addColumn' and $query['params'][0] === $definition['name'] )
					{
						unset( $definition['columns'][ $query['params'][1]['name'] ] );
					}
				}
			}
		}
		return $definition;
	}
	
	/**
	 * Edit Schema: Add/Edit Column
	 *
	 * @return	void
	 */
	protected function editSchemaColumn()
	{
		/* Get current column */
		$column = NULL;
		$schema = $this->_getSchema();
		$definition = $schema[ \IPS\Request::i()->_name ];
		if ( \IPS\Request::i()->column )
		{
			$column = $definition['columns'][ \IPS\Request::i()->column ];
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Text( 'database_column_name', $column ? $column['name'] : '', TRUE, array( 'maxLength' => 64 ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'database_column_type', $column ? $column['type'] : 'VARCHAR', TRUE, array(
			'options' 	=> \IPS\Db::$dataTypes,
			'toggles'	=> array(
				'TINYINT'	=> array( 'database_column_unsigned', 'database_column_auto_increment', 'database_column_default' ),
				'SMALLINT'	=> array( 'database_column_unsigned', 'database_column_auto_increment', 'database_column_default' ),
				'MEDIUMINT'	=> array( 'database_column_unsigned', 'database_column_auto_increment', 'database_column_default' ),
				'INT'		=> array( 'database_column_unsigned', 'database_column_auto_increment', 'database_column_default' ),
				'BIGINT'	=> array( 'database_column_unsigned', 'database_column_auto_increment', 'database_column_default' ),
				'DECIMAL'	=> array( 'database_column_length', 'database_column_decimals', 'database_column_default' ),
				'FLOAT'		=> array( 'database_column_length', 'database_column_default' ),
				'BIT'		=> array( 'database_column_length', 'database_column_default' ),
				'DATE'		=> array( 'database_column_default' ),
				'DATETIME'	=> array( 'database_column_default' ),
				'TIMESTAMP'	=> array( 'database_column_default' ),
				'TIME'		=> array( 'database_column_default' ),
				'YEAR'		=> array( 'database_column_default' ),
				'CHAR'		=> array( 'database_column_length', 'database_column_default' ),
				'VARCHAR'	=> array( 'database_column_length', 'database_column_default' ),
				'BINARY'	=> array( 'database_column_length', 'database_column_default' ),
				'VARBINARY'	=> array( 'database_column_length', 'database_column_default' ),
				'TINYBLOB'	=> array(  ),
				'BLOB'		=> array(  ),
				'MEDIUMBLOB'=> array(  ),
				'BIGBLOB'	=> array(  ),
				'ENUM'		=> array( 'database_column_values', 'database_column_default' ),
				'SET'		=> array( 'database_column_values', 'database_column_default' ),
			),
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Number( 'database_column_length', ( $column and $column['length'] !== NULL ) ? $column['length'] : -1, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'no_value' ), NULL, NULL, NULL, 'database_column_length' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'database_column_decimals', ( $column and isset( $column['decimals'] ) and $column['decimals'] !== NULL ) ? $column['decimals'] : -1, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'no_value' ), NULL, NULL, NULL, 'database_column_decimals' ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'database_column_values', ( $column and isset( $column['values'] ) ) ? $column['values'] : NULL, FALSE, array(), NULL, NULL, NULL, 'database_column_values' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'database_column_allow_null', $column ? $column['allow_null'] : TRUE, TRUE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'database_column_default', $column ? $column['default'] : NULL, FALSE, array( 'nullLang' => 'NULL' ), NULL, NULL, NULL, 'database_column_default' ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'database_column_comment', $column ? $column['comment'] : NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'database_column_unsigned', $column ? $column['unsigned'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'database_column_unsigned' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'database_column_auto_increment', $column ? $column['auto_increment'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'database_column_auto_increment' ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Change -1 to NULL where appropriate */
			foreach ( array( 'database_column_length', 'database_column_decimals' ) as $k )
			{
				if ( $values[ $k ] === -1 )
				{
					$values[ $k ] = NULL;
				}
			}

			/* Check default value is a number, where it should be a number */
			if( !$values['database_column_allow_null'] AND empty( $values['database_column_default'] ) )
			{
				if( \in_array( $values['database_column_type'], array( 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC' ) ) )
				{
					$values['database_column_default'] = 0;
				}
			}
						
			/* Get a column definition */
			$save = array();
			foreach ( $values as $k => $v )
			{
				/* If this is a new column and we have set the auto_increment flag, or if this is an existing column and the auto_increment
					flag was not previously set but we have toggled it on, then we need to add the primary key flag as well because MySQL
					requires any auto_increment column to also be a primary key */
				if( $k == 'database_column_auto_increment' AND $v AND ( !$column OR ( !$column['auto_increment'] ) ) )
				{
					$save['primary'] = true;
				}

				$save[ str_replace( 'database_column_', '', $k ) ] = $v;
			}

			/* Save */
			try
			{
				if ( $this->_schemaJsonIsWritable() !== true )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_schema_data');
				}
				else
				{
					if ( !$column )
					{
						\IPS\Db::i()->addColumn( $definition['name'], $save );
						$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'addColumn', 'params' => array( $definition['name'], $save ) ) ) );
					}
					else
					{
						\IPS\Db::i()->changeColumn( $definition['name'], $column['name'], $save );
						$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'changeColumn', 'params' => array( $definition['name'],  $column['name'], $save ) ) ) );

						if ( $column['name'] != $save['name'] )
						{
							unset( $schema[ $definition['name'] ]['columns'][ $column['name'] ] );
						}
					}

					/* If we added the 'primary' flag, remove it before saving schema.json because it should not be reflected there...BUT we
						need to add primary key index definition to the schema.json instead in this case */
					if( isset( $save['primary'] ) )
					{
						unset( $save['primary'] );
						$schema[ $definition['name'] ]['columns'][ $save['name'] ] = $save;

						$schema[ $definition['name'] ]['indexes']['PRIMARY'] = array(
							'type'		=> 'primary',
							'name'		=> 'PRIMARY',
							'length'	=> array( 0 => NULL ),
							'columns'	=> array( 0 => $save['name'] )
						);
					}
					else
					{
						$schema[ $definition['name'] ]['columns'][ $save['name'] ] = $save;
					}

					/* Did we rename the column? */
					if( $column AND $save['name'] !== $column['name'] )
					{
						/* Fix references to the column name in indexes */
						if( isset( $schema[ $definition['name'] ]['indexes'] ) )
						{
							foreach( $schema[ $definition['name'] ]['indexes'] as $indexName => $indexDefinition )
							{
								foreach( $indexDefinition['columns'] as $_idx => $columnName )
								{
									if( $columnName == $column['name'] )
									{
										$schema[ $definition['name'] ]['indexes'][ $indexName ]['columns'][ $_idx ]	= $save['name'];
									}
								}
							}
						}

						/* Fix references to the column name in inserts */
						if( isset( $schema[ $definition['name'] ]['inserts'] ) )
						{
							foreach( $schema[ $definition['name'] ]['inserts'] as $_idx => $insert )
							{
								$insert[ $save['name'] ] = $insert[ $column['name'] ];
								unset( $insert[ $column['name'] ] );

								$schema[ $definition['name'] ]['inserts'][ $_idx ] = $insert;
							}
						}
					}
	
					$this->_writeSchema( $schema );
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&tab=columns" ) );
				}
			}
			catch ( \Exception $e )
			{
				$form->error = $e->getMessage();
			}
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Edit Schema: Delete Column
	 *
	 * @return	void
	 */
	protected function editSchemaDeleteColumn()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			if ( $this->_schemaJsonIsWritable() !== true )
			{
				throw new \Exception('dev_could_not_write_schema_data');
			}
			
			\IPS\Db::i()->dropColumn( \IPS\Request::i()->_name, \IPS\Request::i()->column );
			$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'dropColumn', 'params' => array( \IPS\Request::i()->_name, \IPS\Request::i()->column ) ) ) );
			
			$schema = $this->_getSchema();
			unset( $schema[ \IPS\Request::i()->_name ]['columns'][ \IPS\Request::i()->column ] );
			
			/* Do any indexes use this column? */
			if ( isset( $schema[ \IPS\Request::i()->_name ]['indexes'] ) and \is_array( $schema[ \IPS\Request::i()->_name ]['indexes'] ) )
			{
				foreach( $schema[ \IPS\Request::i()->_name ]['indexes'] as $name => $definition )
				{
					$changed = false;
					
					foreach( $definition['columns'] as $id => $colName )
					{
						if ( $colName === \IPS\Request::i()->column )
						{
							unset( $definition['columns'][ $id ] );
							unset( $definition['length'][ $id ] );
							
							$changed = true;
						}
					}
					
					/* Still have columns? */
					if ( ! \count( $definition['columns'] ) )
					{
						/* Remove the index from schema.json, MySQL will do this automatically */
						unset( $schema[ \IPS\Request::i()->_name ]['indexes'][ $name ] );
					}
					else if ( $changed )
					{
						/* Alter it */
						\IPS\Db::i()->changeIndex( \IPS\Request::i()->_name, $name, $definition );
						
						$schema[ \IPS\Request::i()->_name ]['indexes'][ $name ] = $definition;
						
						$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'changeIndex', 'params' => array( $name,  $name, $definition ) ) ) );
					}
				}
			}
			
			$this->_writeSchema( $schema );
			
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = 1;
				return;
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name=" . \IPS\Request::i()->_name . "&tab=columns" ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '1C103/N', 403, '' );
		}
	}
	
	/**
	 * Edit Schema: Add/Edit Index
	 *
	 * @return	void
	 */
	protected function editSchemaIndex()
	{
		/* Get current index */
		$maxIndexLength = 250;
		$index  = NULL;
		$schema = $this->_getSchema();
		$definition = $schema[ \IPS\Request::i()->_name ];
		if ( \IPS\Request::i()->index )
		{
			$index = $definition['indexes'][ \IPS\Request::i()->index ];
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Select( 'database_index_type', $index ? $index['type'] : 'key', TRUE, array( 'options' => array(
			'primary'	=> 'PRIMARY',
			'unique'	=> 'UNIQUE',
			'fulltext'	=> 'FULLTEXT',
			'key'		=> 'KEY'
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'database_index_name', $index ? $index['name'] : NULL, TRUE, array( 'maxLength' => 64 ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'database_index_columns', $index ? $index['columns'] : array(), TRUE, array(
			'options'			=> array_combine( array_keys( $definition['columns'] ), array_keys( $definition['columns'] ) ),
			'parse'				=> 'normal',
			'stackFieldType'	=> 'Select',
		) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Get a definition */
			$save = array();
			foreach ( $values as $k => $v )
			{
				$save[ str_replace( 'database_index_', '', $k ) ] = $v;
			}
			
			foreach( $save['columns'] as $id => $field )
			{
				if ( isset( $definition['columns'][ $field ] ) )
				{
					if ( ( \mb_substr( \mb_strtolower( $definition['columns'][ $field ]['type'] ), -4 ) === 'text' ) OR ( ! empty( $definition['columns'][ $field ]['length'] ) AND \is_integer( $definition['columns'][ $field ]['length']) AND $definition['columns'][ $field ]['length'] > $maxIndexLength ) )
					{
						$save['length'][ $id ] = $maxIndexLength;
					}
				}
				
				if ( ! isset( $save['length'][ $id ] ) )
				{
					$save['length'][ $id ] = null;
				}
			}

			/* Save */
			try
			{
				if ( $this->_schemaJsonIsWritable() !== true )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_schema_data');
				}
				else
				{
					if ( !$index )
					{
						\IPS\Db::i()->addIndex( $definition['name'], $save );
						$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'addIndex', 'params' => array( $definition['name'], $save ) ) ) );
					}
					else
					{
						\IPS\Db::i()->changeIndex( $definition['name'], $index['name'], $save );
						$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'changeIndex', 'params' => array( $definition['name'],  $index['name'], $save ) ) ) );

						if ( $index['name'] != $save['name'] )
						{
							unset( $schema[ $definition['name'] ]['indexes'][ $index['name'] ] );
						}
					}
					$schema[ $definition['name'] ]['indexes'][ $save['name'] ] = $save;
					
					$this->_writeSchema( $schema );
	
					if( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->output = 1;
						return;
					}
	
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&tab=indexes" ) );
				}
			}
			catch ( \Exception $e )
			{
				$form->error = $e->getMessage();
			}
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Edit Schema: Delete Index
	 *
	 * @return	void
	 */
	protected function editSchemaDeleteIndex()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			if ( $this->_schemaJsonIsWritable() !== true )
			{
				throw new \Exception('dev_could_not_write_schema_data');
			}
			
			\IPS\Db::i()->dropIndex( \IPS\Request::i()->_name, \IPS\Request::i()->index );
			$this->_writeQueries( 'working', $this->_addQueryToJson( $this->_getQueries( 'working' ), array( 'method' => 'dropIndex', 'params' => array( \IPS\Request::i()->_name, \IPS\Request::i()->index ) ) ) );
			
			$schema = $this->_getSchema();
			unset( $schema[ \IPS\Request::i()->_name ]['indexes'][ \IPS\Request::i()->index ] );
			$this->_writeSchema( $schema );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name=" . \IPS\Request::i()->_name . "&tab=indexes" ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '1C103/O', 403, '' );
		}
	}
	
	/**
	 * Edit Schema: Add/Edit Insert Row
	 *
	 * @return	void
	 */
	protected function editSchemaInsert()
	{
		/* Get current row */
		$index = NULL;
		$schema = $this->_getSchema();
		$definition = $schema[ \IPS\Request::i()->_name ];
		$data = array();
		if ( isset( \IPS\Request::i()->row ) )
		{
			$index = \IPS\Request::i()->row;
			$data = $definition['inserts'][ \IPS\Request::i()->row ];
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		foreach ( $definition['columns'] as $column )
		{
			if ( array_key_exists( $column['type'], \IPS\Db::$dataTypes['database_column_type_numeric'] ) and $column['type'] !== 'BIT' )
			{
				$min = NULL;
				$max = NULL;
				
				switch ( $column['type'] )
				{
					case 'TINYINT':
						$min = $column['unsigned'] ? 0 : -128;
						$max = $column['unsigned'] ? 255 : 127;
						break;
						
					case 'SMALLINT':
						$min = $column['unsigned'] ? 0 : -32768;
						$max = $column['unsigned'] ? 65535 : 32767;
						break;
						
					case 'MEDIUMINT':
						$min = $column['unsigned'] ? 0 : -8388608;
						$max = $column['unsigned'] ? 16777215 : 8388607;
						break;
						
					case 'INT':
					case 'INTEGER':
						$min = $column['unsigned'] ? 0 : -2147483648;
						$max = $column['unsigned'] ? 4294967295 : 2147483647;
						break;
						
					case 'BIGINT':
						$min = $column['unsigned'] ? 0 : -9223372036854775808;
						$max = $column['unsigned'] ? 18446744073709551615 : 9223372036854775807;
						break;
				}

				$options = array();
				if ( $column['allow_null'] or $column['auto_increment'] )
				{
					//$options = array( 'unlimited' => 'NULL', 'unlimitedLang' => 'NULL' );
					$options = array( 'nullLang' => 'NULL' );
				}
				if ( isset( $column['decimals'] ) )
				{
					$options['decimals'] = $column['decimals'];
				}
								
				/*if ( isset( $data[ $column['name'] ] ) and $data[ $column['name'] ] === NULL )
				{
					$data[ $column['name'] ] = 'NULL';
				}*/

				$options['min']	= NULL;
				
				$value = NULL;
				if ( $data and isset( $data[ $column['name'] ] ) )
				{
					$value = $data[ $column['name'] ];
				}
				else
				{
					$value = ( $column['auto_increment'] or $column['default'] === NULL ) ? NULL : $column['default'];
				}

				$element = new \IPS\Helpers\Form\Text( $column['name'], $value, FALSE, $options );
			}
			elseif ( \in_array( $column['type'], array( 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY' ) ) )
			{
				$element = new \IPS\Helpers\Form\Text( $column['name'], ( $data AND isset( $data[ $column['name'] ] ) ) ? $data[ $column['name'] ] : $column['default'], FALSE, $column['allow_null'] ? array( 'nullLang' => 'NULL' ) : array() );
			}
			elseif ( \in_array( $column['type'], array( 'TEXT', 'MEDIUMTEXT', 'BIGTEXT', 'TINYBLOB', 'BLOB', 'MEDIUMBLOB', 'BIGBLOB' ) ) )
			{
				$element = new \IPS\Helpers\Form\TextArea( $column['name'], ( $data AND isset( $data[ $column['name'] ] ) ) ? $data[ $column['name'] ] : $column['default'], FALSE, $column['allow_null'] ? array( 'nullLang' => 'NULL' ) : array() );
			}
			elseif ( $column['type'] === 'ENUM' )
			{
				$element = new \IPS\Helpers\Form\Select( $column['name'], ( $data AND isset( $data[ $column['name'] ] ) ) ? $data[ $column['name'] ] : $column['default'], FALSE, array( 'options' => array_combine( $column['values'], $column['values'] ) ) );
			}
			elseif ( $column['type'] === 'SET' )
			{
				$element = new \IPS\Helpers\Form\Select( $column['name'], ( $data AND isset( $data[ $column['name'] ] ) ) ? explode( ',', $data[ $column['name'] ] ) : explode( ',', $column['default'] ), FALSE, array( 'options' => array_combine( $column['values'], $column['values'] ), 'multiple' => TRUE ) );
			}
			else
			{
				$element = new \IPS\Helpers\Form\Text( $column['name'], ( $data AND isset( $data[ $column['name'] ] ) ) ? $data[ $column['name'] ] : $column['default'], FALSE, $column['allow_null'] ? array( 'nullLang' => 'NULL' ) : array() );
			}

			$element->label = $column['name'];
			$form->add( $element );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			foreach ( $definition['columns'] as $column )
			{
				if ( array_key_exists( $column['type'], \IPS\Db::$dataTypes['database_column_type_numeric'] ) and $column['type'] !== 'BIT' and $values[ $column['name'] ] === 'NULL' )
				{
					$values[ $column['name'] ] = NULL;
				}
			}
			
			try
			{
				if ( $this->_schemaJsonIsWritable() !== true )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_schema_data');
				}
				else
				{
					if ( $index !== NULL )
					{
						$schema[ $definition['name'] ]['inserts'][ $index ] = $values;
					}
					else
					{
						\IPS\Db::i()->insert( $definition['name'], $values );
						$schema[ $definition['name'] ]['inserts'][] = $values;
					}
					
					$this->_writeSchema( $schema );
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$definition['name']}&tab=inserts" ) );
				}
			}
			catch ( \Exception $e )
			{
				$form->error = $e->getMessage();
			}
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Edit Schema: Delete Insert
	 *
	 * @return	void
	 */
	protected function editSchemaDeleteInsert()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			if ( $this->_schemaJsonIsWritable() !== true )
			{
				throw new \Exception('dev_could_not_write_schema_data');
			}
			
			$schema = $this->_getSchema();
			unset( $schema[ \IPS\Request::i()->_name ]['inserts'][ \IPS\Request::i()->row ] );
			$this->_writeSchema( $schema );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name=" . \IPS\Request::i()->_name . "&tab=inserts" ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '1C103/P', 403, '' );
		}
	}
		
	/**
	 * Show Schema
	 *
	 * @return	void
	 */
	protected function showSchema()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block(
			\IPS\Request::i()->_name,
			str_replace( '&lt;?php', '', highlight_string( "<?php " . var_export(\IPS\Db::i()->getTableDefinition( \IPS\Request::i()->_name ), TRUE ), TRUE ) ),
			FALSE
		);
	}
	
	/**
	 * Resolve Schema Conflicts
	 *
	 * @return	void
	 */
	protected function resolveSchemaConflicts()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Get table definitions */
		$schema = $this->_getSchema();
		if ( !isset( $schema[ \IPS\Request::i()->_name ] ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C103/G', 404, '' );
		}
		$schemaDefinition = $schema[ \IPS\Request::i()->_name ];
		$localDefinition = $this->_getTableDefinition( $schemaDefinition['name'] );
	
		/* Use local database */
		if ( \IPS\Request::i()->local )
		{			
			foreach ( $localDefinition['columns'] as $i => $data )
			{
				if ( $data['type'] == 'BIT' )
				{
					$localDefinition['columns'][ $i ]['default'] = \intval( preg_replace( "/^b'(\d+?)'\$/", '$1', $data['default'] ) );
				}
			}
			$schema[ \IPS\Request::i()->_name ] = $localDefinition;
			if ( isset( $schemaDefinition['inserts'] ) )
			{
				$schema[ \IPS\Request::i()->_name ]['inserts'] = $schemaDefinition['inserts'];
			}
			if ( isset( $schemaDefinition['engine'] ) )
			{
				$schema[ \IPS\Request::i()->_name ]['engine'] = $schemaDefinition['engine'];
			}
			else
			{
				unset( $schema[ \IPS\Request::i()->_name ]['engine'] );
			}

			if( isset( $schemaDefinition['reporting'] ) )
			{
				$schema[ \IPS\Request::i()->_name ]['reporting'] = $schemaDefinition['reporting'];
			}

			$this->_writeSchema( $schema );
		}
		/* Use schema file */
		else
		{
			/* Create a new table */
			$_newTable = $schemaDefinition;
			$_newTable['name'] = $_newTable['name'] . '_temp';
			\IPS\Db::i()->createTable( $_newTable );
						
			/* Work out our columns */
			$columns = array();
			foreach ( array_keys( $schemaDefinition['columns'] ) as $column )
			{
				if ( isset( $localDefinition['columns'][ $column ] ) )
				{
					$columns[] = $column;
				}
			}
			$columns = implode( ',', array_map( function( $v ){ return "`{$v}`"; }, $columns ) );
			
			/* Insert the rows */
			if ( !empty( $columns ) )
			{
				\IPS\Db::i()->query( 'INSERT IGNORE INTO `' . \IPS\Db::i()->prefix . $_newTable['name'] . "` ( {$columns} ) SELECT {$columns} FROM `" . \IPS\Db::i()->prefix . $schemaDefinition['name'] . '`' );
			}
			
			/* Drop the old table */
			\IPS\Db::i()->dropTable( $schemaDefinition['name'] );
			
			/* Rename the new table */
			\IPS\Db::i()->renameTable( $_newTable['name'], $schemaDefinition['name'] );
		}
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=editSchema&_name={$schemaDefinition['name']}" ) );
	}
	
	/**
	 * Delete Table
	 *
	 * @return	void
	 */
	protected function deleteTable()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$table = \IPS\Request::i()->name;
		
		/* Drop the table */
		\IPS\Db::i()->dropTable( $table, TRUE );
		
		/* Add the drop to the queries.json file */
		$queries = $this->_getQueries( 'working' );
		$queries = $this->_addQueryToJson( $queries, array( 'method' => 'dropTable', 'params' => array( $table, TRUE ) ) );
		$this->_writeQueries( 'working', $queries );
		
		/* Remove from schema.json */
		$schemaJson = $this->_getSchema();
		unset( $schemaJson[ $table ] );
		$this->_writeSchema( $schemaJson );
		
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=schema" ) );
	}
	
	/**
	 * Manage Extensions
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageExtensions()
	{
		$this->url = \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=extensions" );
		$table = new \IPS\Helpers\Tree\Tree( $this->url, 'dev_extensions', array( $this, 'extGetRoots' ), array( $this, 'extGetRow' ), array( $this, 'extGetRowParentId' ), array( $this, 'extGetChildren' ), NULL, FALSE, TRUE, TRUE );
		
		return $table;
	}
	
	/**
	 * Extensions: Get Root Rows
	 *
	 * @return	array
	 */
	public function extGetRoots()
	{
		$rows = array();
		
		foreach ( \IPS\Application::applications() as $app )
		{
			$haveExtensions = FALSE;

			if( is_dir( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/defaults/extensions" ) )
			{
				foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/defaults/extensions" ) as $file )
				{
					if ( mb_substr( $file, 0, 1 ) !== '.' AND $file != 'index.html' )
					{
						$haveExtensions = TRUE;
						break;
					}
				}
			}
			
			if ( $haveExtensions === TRUE )
			{
				$rows[ $app->directory ] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, $app->directory, $app->directory, TRUE );
			}
		}
			
		return $rows;
	}
	
	/**
	 * Extensions: Get row
	 *
	 * @param	string	$row	Row ID
	 * @param	bool	$root	Is root?
	 * @return	string
	 */
	public function extGetRow( $row, $root )
	{
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, $row, $row, TRUE, array(), '', NULL, NULL, $root );
	}
	
	/**
	 * Extensions: Get Children
	 *
	 * @param	string	$folder	Folder
	 * @return	array
	 */
	public function extGetChildren( $folder )
	{
		$rows = array();
		
		if ( is_dir( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/extensions/{$folder}" ) )
		{
			foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/extensions/{$folder}" ) as $file )
			{
				if ( mb_substr( $file, 0, 1 ) !== '.' AND $file != 'index.html' )
				{
					$buttons = array();
					
					if ( $file->isDir() )
					{
						if ( file_exists( \IPS\ROOT_PATH . "/applications/{$folder}/data/defaults/extensions/{$file}.txt" ) )
						{
							$buttons['add'] = array(
								'icon'	=> 'plus-circle',
								'title'	=> \IPS\Member::loggedIn()->language()->addToStack('dev_extensions_create', FALSE, array( 'sprintf' => array( $file ) ) ),
								'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addExtension&type={$file}&extapp={$folder}" ),
								'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('dev_extensions_create', FALSE, array( 'sprintf' => array( $file ) ) ) )
							);
						}
					}
					else
					{
						$name = mb_substr( $file, 0, -4 );
						
						$buttons['delete'] = array(
							'icon'	=> 'times-circle',
							'title'	=> \IPS\Member::loggedIn()->language()->addToStack('delete'),
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=removeExtension&file={$name}&ext={$folder}" )->csrf(),
							'data'	=> array( 'delete' => '' )
						);
					}
				
					$name = str_replace( '\\', '/', mb_substr( $file->getPathName(), mb_strlen( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/extensions/" ) ) );
	
					$rows[ $name ] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, $name, mb_substr( $name, mb_strlen( $folder ) + 1 ), $file->isDir() ? TRUE : FALSE, $buttons, $file->isDir() ? ( '<br>' . \IPS\Member::loggedIn()->language()->addToStack( 'ext__' . mb_substr( $name, mb_strlen( $folder ) + 1 ) ) ) : '', NULL, NULL, FALSE, NULL, NULL, NULL, FALSE, TRUE );
				}
			}
		}

		$extensionApp	= explode( '/', $folder );
		if( \count( $extensionApp ) == 1 )
		{
			foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$extensionApp[0]}/data/defaults/extensions" ) as $file )
			{
				if ( mb_substr( $file, 0, 1 ) !== '.' AND $file != 'index.html' )
				{
					$name = $folder . '/' .mb_substr( $file->getPathName(), mb_strlen( \IPS\ROOT_PATH . "/applications/{$extensionApp[0]}/data/defaults/extensions/" ), -4 );

					if ( !isset( $rows[ $name ] ) )
					{
						$rows[ $name ] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, $name, mb_substr( $name, mb_strlen( $folder ) + 1 ), $file->isDir() ? TRUE : FALSE, array( 'add' => array(
							'icon'	=> 'plus-circle',
							'title'	=> \IPS\Member::loggedIn()->language()->addToStack( 'dev_extensions_create', FALSE, array( 'sprintf' => array( mb_substr( $file, 0, -4 ) ) ) ),
							'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addExtension&type=" . mb_substr( $file, 0, -4 ) . "&extapp={$folder}" ),
							'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('dev_extensions_create', FALSE, array( 'sprintf' => array( mb_substr( $file, 0, -4 ) ) ) ) )
						) ), \IPS\Member::loggedIn()->language()->addToStack( 'ext__' . mb_substr( $name, mb_strlen( $folder ) + 1 ) ) );
					}
				}
			}
		}

		ksort( $rows );
		
		return $rows;
	}
	
	/**
	 * Extensions: Get parent ID
	 *
	 * @param	string	$folder	Folder name
	 * @return	string
	 */
	public function extGetRowParentId( $folder )
	{
		return NULL;
	}

	/**
	 * Add Extension
	 *
	 * @return	void
	 */
	protected function addExtension()
	{
		$form = new \IPS\Helpers\Form();
		$form->hiddenFields['type'] = \IPS\Request::i()->type;
		$form->add( new \IPS\Helpers\Form\Text( 'dev_extensions_classname', NULL, TRUE, array( 'regex' => '/^[A-Z0-9]+$/i' ) ) );
		
		if ( $values = $form->values() )
		{
			$contents = str_replace(
				array(
					"{subpackage}",
					'{date}',
					'{app}',
					'{class}',
				),
				array(
					( $this->application->directory != 'core' ) ? ( " * @subpackage\t" . \IPS\Member::loggedIn()->language()->get( "__app_{$this->application->directory}" ) ) : '',
					date( 'd M Y' ),
					$this->application->directory,
					$values['dev_extensions_classname']
					
				),
				file_get_contents( \IPS\ROOT_PATH . '/applications/' . \IPS\Request::i()->extapp . '/data/defaults/extensions/' . \IPS\Request::i()->type . '.txt' )
			);
			
			$dir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/extensions/" . \IPS\Request::i()->extapp . '/' . \IPS\Request::i()->type;
			if ( !is_dir( $dir ) )
			{
				mkdir( $dir, \IPS\IPS_FOLDER_PERMISSION, TRUE );
			}
			
			\file_put_contents( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/extensions/" . \IPS\Request::i()->extapp . '/' . \IPS\Request::i()->type . "/{$values['dev_extensions_classname']}.php", $contents );
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/extensions.json", $this->application->buildExtensionsJson() );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=extensions" ), 'file_created' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( \IPS\Member::loggedIn()->language()->addToStack('dev_extensions_create', FALSE, array( 'sprintf' => array( \IPS\Request::i()->type ) ) ), $form, FALSE );
	}
	
	/**
	 * Remove Extension
	 *
	 * @return	void
	 */
	protected function removeExtension()
	{
		\IPS\Session::i()->csrfCheck();

		if( is_file( \IPS\ROOT_PATH."/applications/{$this->application->directory}/extensions/".\IPS\Request::i()->ext.'/'.\IPS\Request::i()->file.'.php'))
		{
			unlink( \IPS\ROOT_PATH."/applications/{$this->application->directory}/extensions/".\IPS\Request::i()->ext.'/'.\IPS\Request::i()->file.'.php' );
		}

		$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/extensions.json", $this->application->buildExtensionsJson() );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=extensions" ), 'deleted' );
	}
	
	/**
	 * Manage Settings
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageSettings()
	{
		$settings = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/settings.json" );
	
		$form = new \IPS\Helpers\Form\Matrix();
		$form->langPrefix = 'dev_settings_';
		$form->columns = array(
			'key'		=> array( 'Text', NULL, TRUE ),
			'default'	=> array( 'Text' ),
		);
		if ( \in_array( $this->application->directory, \IPS\IPS::$ipsApps ) )
		{
			$form->columns['report'] = array( 'Select', 'none', FALSE, array( 'options' => array(
				'none'	=> 'dev_settings_none',
				'full'	=> 'dev_settings_full',
				'bool'	=> 'dev_settings_bool',
			) ) );
		}
		$form->rows = $settings;

		if ( $form->values() !== FALSE )
		{
			$values = $form->values();
			
			if ( !empty( $form->addedRows ) )
			{
				$insert = array();
				foreach ( $form->addedRows as $key )
				{
					$insert[] = array( 'conf_key' => $values[ $key ]['key'], 'conf_value' => $values[ $key ]['default'], 'conf_default' => $values[ $key ]['default'], 'conf_app' => $this->application->directory );
				}

				\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert );
			}
			if ( !empty( $form->changedRows ) )
			{
				foreach ( $form->changedRows as $key )
				{
					\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_key' => $values[ $key ]['key'], 'conf_default' => $values[ $key ]['default'] ), array( 'conf_key=?', $form->rows[ $key ]['key'] ) );
				}
			}
			if ( !empty( $form->removedRows ) )
			{
				$delete = array();
				foreach ( $form->removedRows as $key )
				{
					$delete[] = $form->rows[ $key ]['key'];
				}
				
				\IPS\Db::i()->delete( 'core_sys_conf_settings', \IPS\Db::i()->in( 'conf_key', $delete ) );
			}
			
			$save = array();
			foreach ( $values as $data )
			{
				if ( $data['key'] )
				{
					$save[] = $data;
				}
			}
			
			usort( $save, function( $a, $b )
			{
				return strnatcmp( $a['key'], $b['key'] );
			} );
						
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/settings.json", $save );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=settings" ) );
		}
		
		return $form;
	}
	
	/**
	 * Versions: Show Versions
	 *
	 * @return	string	HTML to display
	 */
	protected function _manageVersions()
	{
		/* Create the file if it doesn't exist */
		$this->json = $this->_getVersions();
		
		/* Reorder if necessary */
		if ( \IPS\Request::i()->do === 'reorder' )
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Normalise AJAX vs non-AJAX */			
			if( isset( \IPS\Request::i()->ajax_order ) )
			{
				$order = array();
				$position = array();
				foreach( \IPS\Request::i()->ajax_order as $id => $parent )
				{
					if ( !isset( $order[ $parent ] ) )
					{
						$order[ $parent ] = array();
						$position[ $parent ] = 1;
					}
					$order[ $parent ][ $id ] = $position[ $parent ]++;
				}
			}
			/* Non-AJAX way */
			else
			{
				$order = array( \IPS\Request::i()->root ?: 'null' => \IPS\Request::i()->order );
			}
			
			/* Work out */
			$queries = array();
			$write = array();
			foreach ( $order as $versionKey => $keys )
			{
				if ( $versionKey != 'null' )
				{
					asort( $keys );
					foreach ( $keys as $key => $position )
					{
						$versionNumber = mb_substr( $key, 0, mb_strpos( $key, '.' ) );
						if ( !isset( $queries[ $versionNumber ] ) )
						{
							$queries[ $versionNumber ] = $this->_getQueries( $versionNumber );
						}
						$write[ $versionNumber ][] = $queries[ $versionNumber ][ mb_substr( $key, mb_strpos( $key, '.' ) + 1 ) ];
					}
				}
			}
			foreach ( $write as $versionNumber => $queries )
			{
				$this->_writeQueries( $versionNumber, $queries );
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				return;
			}
		}
		
		/* Build node tree */
		$appKey = $this->application->directory;
		$this->url = \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions" );
		$table = new \IPS\Helpers\Tree\Tree(
			$this->url,
			'dev_versions',
			/* Get Roots */
			array( $this, '_getVersionRows' ),
			/* Get Row */
			array( $this, '_getVersionRow' ),
			/* Get Row's Parent ID */
			function( $id )
			{
				return NULL;
			},
			/* Get Children */
			array( $this, '_getQueriesRows' ),
			/* Get Root Buttons */
			function() use ( $appKey )
			{
				return array(
					'add'	=> array(
						'icon'	=> 'plus',
						'title'	=> 'versions_add',
						'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$appKey}&do=addVersion" ),
						'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('versions_add') )
					)
				);
			},
			NULL,
			FALSE,
			TRUE
		);
		
		/* Return */
		return $table;
	}
	
	/**
	 * Get all version rows
	 *
	 * @return	array
	 */
	public function _getVersionRows()
	{
		$rows = array( 'install' => $this->_getVersionRow( 'install' ) );
		foreach ( $this->json as $long => $human )
		{
			array_unshift( $rows, $this->_getVersionRow( $long ) );
		}
		array_unshift( $rows, $this->_getVersionRow( 'working' ) );
		return $rows;
	}
	
	/**
	 * Get individual version row
	 *
	 * @param	int		$long	Version ID
	 * @param	bool	$root	Format this as the root node?
	 * @return	string
	 */
	public function _getVersionRow( $long, $root=FALSE )
	{
		$dir = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/setup/" . ( $long === 'install' ? $long : "upg_{$long}" );
		
		$hasChildren = FALSE;
		if ( is_dir( $dir ) )
		{
			foreach ( new \DirectoryIterator( $dir ) as $file )
			{
				if ( !$file->isDot() and mb_substr( $file, 0, 1 ) !== '.' and $file != 'index.html' )
				{
					$hasChildren = TRUE;
					break;
				}
			}
		}

		$buttons = array();

		if( $long != 'install' )
		{
			$buttons['phpcode'] = array(
				'icon'		=> 'code',
				'title'		=> 'versions_code',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=versionCode&id={$long}" )->csrf(),
			);
		}

		$buttons['add'] = array(
			'icon'		=> 'plus-circle',
			'title'		=> 'versions_query',
			'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addVersionQuery&id={$long}" ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('versions_query'), 'ipsDialog-remoteVerify' => "false" )
		);
		
		if( $long != 'install' and $long !== 'working' )
		{
			$buttons['delete']	= array(
				'icon'		=> 'times-circle',
				'title'		=> 'delete',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=deleteVersion&id={$long}" ),
				'data'		=> array( 'delete' => '' )
			);
		}
		
		if ( $long === 'install' )
		{
			$nameToDisplay = \IPS\Member::loggedIn()->language()->addToStack('versions_install');
		}
		elseif ( $long === 'working' )
		{
			$nameToDisplay = \IPS\Member::loggedIn()->language()->addToStack('versions_working');
		}
		else
		{
			$nameToDisplay = isset( $this->json[ $long ] ) ? $this->json[ $long ] : $long;
		}

		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, $long, $nameToDisplay, $hasChildren, $buttons, $long, NULL, NULL, $root );
	}
	
	/**
	 * Get the queries rows for a version
	 *
	 * @param	int		$long	Version ID
	 * @return	array
	 */
	public function _getQueriesRows( $long )
	{		
		$queries = $this->_getQueries( $long );
		$order = 1;
		$rows = array();
		foreach ( ( $queries ?? [] ) as $qid => $data )
		{
			$params = array();
			if ( isset( $data['params'] ) and \is_array( $data['params'] ) )
			{
				foreach ( $data['params'] as $v )
				{
					$params[] = var_export( $v, TRUE );
				}
			}
						
			$rows["{$long}.{$qid}"] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $this->url, "{$long}.{$qid}", str_replace( '&lt;?php', '', highlight_string( "<?php \\IPS\\Db::i()->{$data['method']}( ". implode( ', ', $params ) ." )", TRUE ) ), FALSE, array(
				'delete'	=> array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=deleteVersionQuery&version={$long}&query={$qid}" ),
					'data'		=> array( 'delete' => '' )
				)
			), NULL, NULL, NULL, FALSE, NULL, NULL, NULL, TRUE, FALSE, FALSE, FALSE );
			
			$order++;
		}
		
		return $rows;
	}

	/**
	 * Versions: Add Version
	 *
	 * @return	void
	 */
	protected function addVersion()
	{
		/* Load existing versions.json file */
		$json = $this->_getVersions();
				
		/* Get form */
		$activeTab = \IPS\Request::i()->tab ?: 'new';
		$form = new \IPS\Helpers\Form( 'versions_add' );
		switch ( $activeTab )
		{
			/* Create New */
			case 'new':
				$form->addMessage( 'versions_add_information' );
				$form->add( new \IPS\Helpers\Form\Text( 'versions_human', NULL, TRUE, array( 'placeholder' => '1.0.0' ), function( $val )
				{
					if ( !preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $val ) )
					{
						throw new \DomainException( 'versions_human_bad' );
					}
				} ) );
				$form->add( new \IPS\Helpers\Form\Text( 'versions_long', NULL, TRUE, array( 'placeholder' => '10000' ), function( $val ) use ( $json )
				{
					if ( !preg_match( '/^\d*$/', $val ) )
					{
						throw new \DomainException( 'form_number_bad' );
					}
					if( isset( $json[ $val ] ) )
					{
						throw new \DomainException( 'versions_long_exists' );
					}
				} ) );
				break;

			/* Upload */
			case 'upload':
				$appKey = $this->application->directory;
				$form->add( new \IPS\Helpers\Form\Upload(
					'upload',
					NULL,
					TRUE,
					array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE )
					) );

				break;
		}

		/* Has the form been submitted? */
		if( $values = $form->values() )
		{
			/* Add values */
			$toAdd = array();
			switch ( $activeTab )
			{
				/* New Version */
				case 'new':
					$json[ $values['versions_long'] ] = $values['versions_human'];

					/* If this was for the core, add it to all IPS apps */
					if ( $this->application->directory === 'core' )
					{
						foreach ( \IPS\IPS::$ipsApps as $_appKey )
						{
							$appJson = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$_appKey}/data/versions.json" );
							$appJson[ $values['versions_long'] ] = $values['versions_human'];
							$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$_appKey}/data/versions.json", $appJson );
						}
					}

					break;

				/* Uploaded versions.xml file */
				case 'upload':
					$xml = NULL;
					try
					{
						$xml = \IPS\Xml\SimpleXML::loadFile( $values['upload'] );
					}
					catch ( \InvalidArgumentException $e ) {}

					if ( !$xml or $xml->getName() !== 'versions' )
					{
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions" ), 'versions_upload_badxml' );
					}

					foreach ( $xml as $version )
					{
						$json[ (int) $version->long ] = (string) $version->human;
					}
					unlink( $values['upload'] );
					break;
			}
			
			/* Save a snapshot of the default theme for diffs */
			if ( $this->application->directory === 'core' )
			{
				\IPS\Theme::master()->saveHistorySnapshot();
			}

			/* Save it */
			$this->_writeVersions( $json );

			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions" ) );
		}

		if( \IPS\Request::i()->isAjax() and $activeTab == 'upload' )
		{
			\IPS\Output::i()->output = $form;
			return;
		}



		/* If not, show it */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs(
			array(
				'new'		=> 'versions_add_new',
				'upload'	=> 'versions_add_upload',
				),
			$activeTab,
			$form,
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&do=addVersion" )
			);

		if( \IPS\Request::i()->isAjax() )
		{
			if( \IPS\Request::i()->existing )
			{
				\IPS\Output::i()->output = $form;
			}
		}
	}
		
	/**
	 * Delete Version
	 *
	 * @return	void
	 */
	protected function deleteVersion()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$versionsFile = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/versions.json";
		$json = $this->_getVersions();
		if ( isset( $json[ \intval( \IPS\Request::i()->id ) ] ) )
		{
			unset( $json[ \intval( \IPS\Request::i()->id ) ] );
		}
		$this->_writeVersions( $json );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions" ) );
	}
	
	/**
	 * Add version query
	 *
	 * @return	void
	 */
	protected function addVersionQuery()
	{
		/* Build Form */
		$form = new \IPS\Helpers\Form( 'add_version_query' );
		$form->add( new \IPS\Helpers\Form\TextArea( 'versions_query_code', '\IPS\Db::i()->', TRUE, array( 'size' => 45 ), function( $val )
		{
			/* Check it starts with \IPS\Db::i()-> */
			$val = trim( $val );
			if ( mb_substr( $val, 0, 14 ) !== '\IPS\Db::i()->' )
			{
				throw new \DomainException( 'versions_query_start' );
			}
			
			/* Check there's only one query */
			if ( mb_substr( $val, -1 ) !== ';' )
			{
				$val .= ';';
			}
			if ( mb_substr_count( $val, ';' ) > 1 )
			{
				throw new \DomainException( 'versions_query_one' );
			}
			
			/* Check our Regex will be okay with it */
			preg_match( '/^\\\IPS\\\Db::i\(\)->(.+?)\(\s*[\'"](.+?)[\'"]\s*(,\s*(.+?))?\)\s*;$/', $val, $matches );
			if ( empty( $matches ) )
			{
				throw new \DomainException( 'versions_query_format' );
			}
			
			/* Run it if we're adding it to the current working version */
			if( \IPS\Request::i()->id == 'working' )
			{
				try
				{
					try
					{
						if ( @eval( $val ) === FALSE )
						{
							throw new \DomainException( 'versions_query_phperror' );
						}
					}
					catch ( \ParseError $e )
					{
						throw new \DomainException( 'versions_query_phperror' );
					}
				}
				catch ( \IPS\Db\Exception $e )
				{
					throw new \DomainException( $e->getMessage() );
				}
			}
		} ) );
		
		/* If submitted, add to json file */
		if ( $values = $form->values() )
		{
			/* Get our file */
			$version = \IPS\Request::i()->id;
			$json = $this->_getQueries( $version );
		
			/* Work out the different parts of the query */
			$val = trim( $values['versions_query_code'] );
			if ( mb_substr( $val, -1 ) !== ';' )
			{
				$val .= ';';
			}
			preg_match( '/^\\\IPS\\\Db::i\(\)->(.+?)\(\s*(.+?)\s*\)\s*;$/', $val, $matches );
			
			/* Add it on */
			$json[] = array( 'method' => $matches[1], 'params' => eval( 'return array( ' . $matches[2] . ' );' ) );
			
			/* Write it */
			$this->_writeQueries( $version, $json );
			
			/* Redirect us */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions&root={$version}" ) );
		}
		
		/* Or display it */
		else
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global' )->block( 'versions_query', $form, FALSE );
		}
	}
	
	/**
	 * Delete Version Query
	 *
	 * @return	void
	 */
	protected function deleteVersionQuery()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$version = ( \IPS\Request::i()->version == 'install' ? 'install' : \IPS\Request::i()->version );

		$json = $this->_getQueries( $version );
		unset( $json[ \intval( \IPS\Request::i()->query ) ] );
		$this->_writeQueries( $version, $json );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions&root={$version}" ) );
	}
	
	/**
	 * Create a PHP class for a version
	 *
	 * @return	void
	 */
	protected function versionCode()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Get version */
		if ( \IPS\Request::i()->id !== 'working' )
		{
			$long = \intval( \IPS\Request::i()->id );
			$json = $this->_getVersions();
			if ( !isset( $json[ $long ] ) )
			{
				\IPS\Output::i()->error( 'node_error', '2C103/8', 404, '' );
			}
			$human = $json[ $long ];
		}
		else
		{
			$long = 'working';
			$human = '{version_human}';
		}
		
		/* Write the file if we don't already have one */
		$phpFilePath = \IPS\ROOT_PATH  . "/applications/{$this->application->directory}/setup/upg_{$long}";
		$phpFile = $phpFilePath . '/upgrade.php';
		if ( !file_exists( $phpFile ) )
		{
			/* Work out the contents */
			$contents = str_replace(
				array(
					'{version_human}',
					"{subpackage}",
					'{date}',
					'{app}',
					'{version_long}',
				),
				array(
					$human,
					( $this->application->directory != 'core' ) ? ( " * @subpackage\t" . \IPS\Member::loggedIn()->language()->get( "__app_{$this->application->directory}" ) ) : '',
					date( 'd M Y' ),
					$this->application->directory,
					$long,
				),
				file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/defaults/Upgrade.txt" )
			);
			
			/* If this isn't an IPS app, strip out our header */
			if ( !\in_array( $this->application->directory, \IPS\IPS::$ipsApps ) )
			{
				$contents = preg_replace( '/(<\?php\s)\/*.+?\*\//s', '$1', $contents );
			}
		
			/* Write */
			if ( !is_dir( $phpFilePath ) )
			{
				mkdir( $phpFilePath );
				chmod( $phpFilePath, \IPS\IPS_FOLDER_PERMISSION );
			}
			if( @\file_put_contents( $phpFile, $contents ) === FALSE )
			{
				\IPS\Output::i()->error( 'dev_could_not_write_setup', '1C103/9', 403, '' );
			}
		}
				
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=versions" ), 'file_created' );
	}
	
	/**
	 * Manage Tasks
	 *
	 * @return	string
	 */
	protected function _manageTasks()
	{
		return \IPS\Task::devTable(
			\IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/tasks.json",
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=tasks" ),
			\IPS\ROOT_PATH . "/applications/{$this->application->directory}/tasks",
			$this->application->directory,
			$this->application->directory . '\tasks',
			$this->application->directory
		);
	}
	
	/**
	 * Manage Widgets
	 *
	 * @return	string
	 */
	protected function _manageWidgets()
	{		
		return \IPS\Widget::devTable(
			\IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/widgets.json",
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=widgets" ),
			\IPS\ROOT_PATH . "/applications/{$this->application->directory}/widgets",
			$this->application->directory,
			$this->application->directory,
			$this->application->directory
		);
	}
	
	/**
	 * Manage Hooks
	 *
	 * @return	string
	 */
	protected function _manageHooks()
	{
		return \IPS\Plugin\Hook::devTable(
			\IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=hooks" ),
			$this->application->directory,
			\IPS\ROOT_PATH . "/applications/{$this->application->directory}/hooks"
		);
	}
	
	/**
	 * Edit Hook
	 *
	 * @csrfChecked	uses $hook->editForm() 7 Oct 2019
	 * @return	string
	 */
	protected function editHook()
	{
		try
		{
			if ( \IPS\Request::i()->hookApp and \IPS\Request::i()->hookFilename )
			{
				$hook = \IPS\Plugin\Hook::constructFromData( \IPS\Db::i()->select( '*', 'core_hooks', array( 'app=? AND filename=?', \IPS\Request::i()->hookApp, \IPS\Request::i()->hookFilename ) )->first() );
			}
			else
			{
				$hook = \IPS\Plugin\Hook::load( \IPS\Request::i()->hook );
			}
			
			$hook->editForm( \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->application->directory}&tab=hooks" ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C103/Q', 404, '' );
		}
	}
	
	/**
	 * Get ACP Menu Tabs
	 *
	 * @return	array
	 */
	protected function _getAcpMenuTabs()
	{
		return array_map( function( $val )
		{
			return mb_substr( $val, 9 );
		}, array_filter( array_keys( \IPS\Member::loggedIn()->language()->words ), function( $val )
		{
			return preg_match( '/^menutab__[a-z]*$/i', $val );
		} ) );
	}
	
	/**
	 * Get available ACP restrictions
	 *
	 * @param	\IPS\Application\Module	$module	The module to get restrictions for
	 * @return	array
	 */
	protected function _getRestrictions( $module )
	{
		$restrictions = array();
		$_restrictions = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/acprestrictions.json" );
		if ( isset( $_restrictions[ $module->key ] ) )
		{
			foreach ( $_restrictions[ $module->key ] as $groupKey => $rows )
			{
				foreach ( $rows as $key )
				{
					$restrictions[ 'r__'.$groupKey ][ $key ] = 'r__'.$key;
				}
			}
		}
		return $restrictions;
	}
	
	/**
	 * Load Module
	 *
	 * @return	\IPS\Application\Module
	 */
	protected function _loadModule()
	{
		try
		{
			$module = \IPS\Application\Module::get( $this->application->directory, \IPS\Request::i()->module_key, \IPS\Request::i()->location );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C103/L', 404, '' );
		}
		
		return $module;
	}
	
	/**
	 * Get modules.json
	 *
	 * @return	array
	 */
	protected function _getModules()
	{
		$file    = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/modules.json";
		$json    = $this->_getJson( $file );
		$modules = array();
		$extra   = array();
		$db      = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_modules', array( 'sys_module_application=?', $this->application->directory ) ) as $row )
		{
			$db[] = $row;
			$extra[ $row['sys_module_area'] ][ $row['sys_module_key'] ] = array( 'default' => $row['sys_module_default'], 'id' => $row['sys_module_id'], 'default_controller' => $row['sys_module_default_controller'], 'protected' => $row['sys_module_protected'] );
		}
		
		if ( \is_array( $json ) AND \count( $json ) )
		{
			$modules = $json;

			foreach( $db as $row )
			{
				if( $row['sys_module_default'] )
				{
					$modules[ $row['sys_module_area'] ][ $row['sys_module_key'] ]['default'] = true;
				}
				elseif( isset( $modules[ $row['sys_module_area'] ][ $row['sys_module_key'] ]['default'] ) )
				{
					$modules[ $row['sys_module_area'] ][ $row['sys_module_key'] ]['default'] = false;
				}
			}
		}
		else
		{
			foreach( $db as $row )
			{
				$modules[ $row['sys_module_area'] ][ $row['sys_module_key'] ] = array(
					'default_controller'	=> $row['sys_module_default_controller'],
					'protected'				=> $row['sys_module_protected'],
					'default'				=> $row['sys_module_default']
				);
			}
		}
		
		if ( ! is_file( $file ) )
		{
			$this->_writeJson( $file, $modules );
		}
		
		/* We get the ID and default flag from the local DB to prevent devs syncing defaults */
		return array_replace_recursive( $modules, $extra );
	}
	
	/**
	 * Write modules.json file
	 *
	 * @param	array	$json	Data
	 * @return	void
	 */
	protected function _writeModules( $json )
	{
		foreach( $json as $location => $module )
		{
			foreach( $module as $name => $data )
			{
				foreach( $data as $k => $v )
				{
					if ( ! \in_array( $k, array( 'protected', 'default_controller', 'default' ) ) )
					{
						unset( $json[ $location ][ $name ][ $k ] );
					}
				}
			}
		}
		
		return $this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/modules.json", $json );
	}
	
	/**
	 * Get schema.json
	 *
	 * @return	array
	 */
	protected function _getSchema()
	{
		return $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/schema.json" );
	}
	
	/**
	 * Write schema.json file
	 *
	 * @param	array	$json	Data
	 * @return	void
	 */
	protected function _writeSchema( $json )
	{
		return $this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/schema.json", $json );
	}
	
	/**
	 * Checks to see if schema JSON is writeable
	 * 
	 * @return boolean		
	 */
	protected function _schemaJsonIsWritable()
	{ 
		if ( !is_writable( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/schema.json" ) )
		{
			return false;
		}
		
		$file = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/setup/upg_working/queries.json";
		if ( file_exists( $file ) and !is_writable( $file ) )
		{
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get versions
	 *
	 * @return	array
	 */
	protected function _getVersions()
	{
		$result = $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/versions.json" );
		ksort( $result );

		return $result;
	}
		
	/**
	 * Write versions.json file
	 *
	 * @param	array	$json	Data
	 * @return	void
	 */
	protected function _writeVersions( $json )
	{
		return $this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/data/versions.json", $json );
	}
	
	/**
	 * Get queries for a version
	 *
	 * @param	int		$long	Version ID
	 */
	protected function _getQueries( $long )
	{
		return $this->_getJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/setup/" . ( $long === 'install' ? $long : "upg_{$long}" ) . '/queries.json' );
	}
	
	/**
	 * Add a query to a queries.json file, looking for CREATE TABLE statements and
	 * adjusting those instead if necessary
	 *
	 * @param	array	$queriesJson	Decoded queries.json file
	 * @param	array	$query			The query to add
	 * @return	array	Decoded queries.json file, modified as necessary
	 */
	protected function _addQueryToJson( $queriesJson, $query )
	{
		$added = FALSE;
		
		$tableName = NULL;
		switch ( $query['method'] )
		{
			case 'renameTable':
			case 'dropTable':
			case 'addColumn':
			case 'changeColumn':
			case 'dropColumn':
			case 'addIndex':
			case 'changeIndex':
			case 'dropIndex':
				$tableName = $query['params'][0];
				break;
		}
		
		if ( $tableName !== NULL )
		{
			foreach ( $queriesJson as $i => $q )
			{
				if ( $q['method'] === 'createTable' and $q['params'][0]['name'] === $tableName )
				{
					switch ( $query['method'] )
					{
						case 'renameTable':
							$queriesJson[ $i ]['params'][0]['name'] = $query['params'][1];
							$added = TRUE;
							break;
							
						case 'dropTable':
							unset( $queriesJson[ $i ] );
							$added = TRUE;
							break;
							
						case 'addColumn':
							$queriesJson[ $i ]['params'][0]['columns'][ $query['params'][1]['name'] ] = $query['params'][1];
							$added = TRUE;
							break;
							
						case 'changeColumn':
							unset( $queriesJson[ $i ]['params'][0]['columns'][ $query['params'][1] ] );
							$queriesJson[ $i ]['params'][0]['columns'][ $query['params'][2]['name'] ] = $query['params'][2];

							/* Fix references to the column name in indexes */
							if ( isset( $queriesJson[ $i ]['params'][0]['indexes'] ) )
							{
								foreach( $queriesJson[ $i ]['params'][0]['indexes'] as $indexName => $indexDefinition )
								{
									foreach( $indexDefinition['columns'] as $_idx => $columnName )
									{
										if( $columnName == $query['params'][1] )
										{
											$queriesJson[ $i ]['params'][0]['indexes'][ $indexName ]['columns'][ $_idx ] = $query['params'][2]['name'];
										}
									}
								}
							}
							$added = TRUE;
							break;
							
						case 'dropColumn':
							unset( $queriesJson[ $i ]['params'][0]['columns'][ $query['params'][1] ] );
							$added = TRUE;
							break;
							
						case 'addIndex':
							$queriesJson[ $i ]['params'][0]['indexes'][ $query['params'][1]['name'] ] = $query['params'][1];
							$added = TRUE;
							break;
							
						case 'changeIndex':
							unset( $queriesJson[ $i ]['params'][0]['indexes'][ $query['params'][1] ] );
							$queriesJson[ $i ]['params'][0]['indexes'][ $query['params'][2]['name'] ] = $query['params'][2];
							$added = TRUE;
							break;
							
						case 'dropIndex':
							unset( $queriesJson[ $i ]['params'][0]['indexes'][ $query['params'][1] ] );
							$added = TRUE;
							break;
					}
				}
			}
		}
		
		if ( $added === FALSE )
		{
			$queriesJson[] = $query;
		}
		
		return $queriesJson;
	}
	
	/**
	 * Write queries.json file
	 *
	 * @param	int		$long	Version ID
	 * @param	array	$json	Data
	 * @return	void
	 */
	protected function _writeQueries( $long, $json )
	{
		/* Create a directory if we don't already have one */
		$path = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/setup/" . ( $long === 'install' ? $long : "upg_{$long}" );
		if ( ! is_dir( $path ) )
		{
			mkdir( $path );
			chmod( $path, \IPS\IPS_FOLDER_PERMISSION );
		}
		
		/* We need to make sure the array is 1-indexed otherwise the upgrader gets confused - unless this is the "working" version
			since that causes conflicts if two branches try to add queries - for the "working" version, this same thing is done
			by \IPS\Application::assignNewVersion() */
		
		if ( $long === 'working' )
		{
			$write = array_values( $json );
		}
		else
		{
			$write = array();
			$i = 0;
			foreach ( $json as $query )
			{
				$write[ ++$i ] = $query;
			}
		}
		
		/* Write */
		$this->_writeJson( $path  . '/queries.json', $write );
		
		/* Update core_dev */
		\IPS\Db::i()->update( 'core_dev', array(
			'last_sync'	=> time(),
			'ran'		=> json_encode( $write ),
		), array( 'app_key=? AND working_version=?', $this->application->directory, $long ) );
	}
	
	/**
	 * Get JSON file
	 *
	 * @param	string	$file	Filepath
	 * @return	array	Decoded JSON data
	 */
	protected function _getJson( $file )
	{
		if( !file_exists( $file ) )
		{
			$json = array();
		}
		else
		{
			$json = json_decode( file_get_contents( $file ), TRUE );
		}
		
		return $json;
	}
	
	/**
	 * Write JSON file
	 *
	 * @param	string	$file	Filepath
	 * @param	array	$data	Data to write
	 * @return	void
	 */
	protected function _writeJson( $file, $data )
	{
		try
		{
			\IPS\Application::writeJson( $file, $data );
		}
		catch ( \RuntimeException $e )
		{
			\IPS\Output::i()->error( 'dev_could_not_write_data', '1C103/4', 403, '' );
		}
	}
	
	/**
	 * Build for release
	 *
	 * @return	void
	 */
	protected function build()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$this->application->build();
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '' );
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications" ), 'dev_built' );
	}

	/**
	 * Manage CMS Templates
	 *
	 * @return	string
	 */
	protected function _manageCmstemplates(): string
	{
		$templateConfigFile = \IPS\ROOT_PATH . "/applications/{$this->application->directory}/dev/cmsTemplates.json";
		$preSelectedTemplates = array( 'templates_database' => [], 'templates_block' => [], 'templates_page' => [] );

		if( file_exists( $templateConfigFile ) )
		{
			$preSelectedTemplates = json_decode( file_get_contents( $templateConfigFile ), TRUE );
		}

		$form = \IPS\cms\Templates::exportForm( TRUE, $preSelectedTemplates );

		if( $values = $form->values() )
		{
			$this->_writeJson( \IPS\ROOT_PATH . "/applications/{$this->application->directory}/dev/cmsTemplates.json", $values );
		}

		return $form;
	}
}
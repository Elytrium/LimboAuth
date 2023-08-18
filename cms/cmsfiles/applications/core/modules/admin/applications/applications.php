<?php
/**
 * @brief		Application & Module Management Controller
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
 * Application & Module Management Controller
 */
class _applications extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Application';

	/**
	 * Description can contain HTML?
	 */
	public $_descriptionHtml = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'app_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Create the basic tree */
		if ( !\IPS\Request::i()->isAjax() )
		{
			if( \IPS\IPS::canManageResources() )
			{
				if ( \IPS\IPS::checkThirdParty() )
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb( 'applications_blurb' );
				}
				else
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb('applications_blurb_no_custom');
				}
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb( 'applications_blurb_no_upload' );
			}
		}
		parent::manage();

		/* Find uninstalled applications */
		$uninstalled	= array();
		$installed		= array_keys( \IPS\Application::applications() );

		foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/" ) as $file )
		{
			if ( $file->isDir() AND !\in_array( $file->getFilename(), $installed ) AND !$file->isDot() )
			{
				if( file_exists( $file->getPathname() . '/data/application.json' ) )
				{
					$application	= json_decode( file_get_contents( $file->getPathname() . '/data/application.json' ), TRUE );

					$uninstalled[ $file->getFilename() ]	= array(
						'title'		=> $application['application_title'],
						'author'	=> $application['app_author'],
						'website'	=> $application['app_website'],
					);
				}
			}
		}
		
		if ( \IPS\CIC2 )
		{
			if ( \is_dir( \IPS\SITE_FILES_PATH . "/applications/" ) )
			{
				foreach( new \DirectoryIterator( \IPS\SITE_FILES_PATH . "/applications/" ) AS $file )
				{
					if ( $file->isDir() AND !\in_array( $file->getFilename(), $installed ) AND !$file->isDot() )
					{
						if ( file_exists( $file->getPathname() . '/data/application.json' ) )
						{
							$application	= json_decode( file_get_contents( $file->getPathname() . '/data/application.json' ), TRUE );
	
							$uninstalled[ $file->getFilename() ]	= array(
								'title'		=> $application['application_title'],
								'author'	=> $application['app_author'],
								'website'	=> $application['app_website'],
							);
						}
					}
				}
			}
		}

		if( \count( $uninstalled ) AND empty( \IPS\Request::i()->root ) )
		{
			$baseUrl	= $this->url;
			$tree = new \IPS\Helpers\Tree\Tree(
				$this->url,
				\IPS\Member::loggedIn()->language()->addToStack('uninstalled_applications'),
				function() use ( $uninstalled, $baseUrl )
				{
					$rows = array();

					if( !empty($uninstalled) AND \is_array($uninstalled) )
					{
						foreach ( $uninstalled as $k => $app )
						{
							$buttons = array();
							if( \IPS\IPS::canManageResources() )
							{
								$buttons = array(
									'add'	=> array(
										'icon'		=> 'plus-circle',
										'title'		=> 'install',
										'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$k}&do=install" )->csrf(),
									)
								);
							}

							$rows[ $k ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $baseUrl, $k, $app['title'], FALSE, $buttons );
						}
					}
					return $rows;
				},
				function( $key, $root=FALSE ) use ( $uninstalled, $baseUrl )
				{
					$buttons = array();
					if( \IPS\IPS::canManageResources() AND \IPS\IPS::checkThirdParty() )
					{
						$buttons = array(
							'add'	=> array(
								'icon'		=> 'plus-circle',
								'title'		=> 'install',
								'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$key}&do=install" )->csrf(),
							)
						);
					}

					return \IPS\Theme::i()->getTemplate( 'trees' )->row( $baseUrl, $key, $uninstalled[ $key ]['title'], FALSE, $buttons, '', NULL, NULL, $root );
				},
				function() { return 0; },
				function() { return array(); },
				function() { return array(); },
				FALSE,
				TRUE,
				TRUE
			);

			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'applications' )->applicationWrapper( $tree, 'uninstalled_applications' );
		}
		
		/* And 3.x applications not yet upgraded */
		$legacyApps = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_applications', NULL, 'app_position' ) as $application )
		{
			try
			{
				\IPS\Application::constructFromData( $application );
			}
			catch( \UnexpectedValueException $e )
			{
				if ( mb_stristr( $e->getMessage(), 'Missing:' ) )
				{
					$legacyApps[ $application['app_directory'] ]	= array(
						'title'		=> isset( $application['app_title'] ) ? $application['app_title'] : $application['app_directory'],
						'author'	=> $application['app_author'],
						'website'	=> $application['app_website'],
					);
				}
			}
		}
		if( \count( $legacyApps ) AND empty( \IPS\Request::i()->root ) )
		{
			$baseUrl	= $this->url;
			$legacyTree = new \IPS\Helpers\Tree\Tree(
				$this->url,
				\IPS\Member::loggedIn()->language()->addToStack('legacy_applications'),
				function() use ( $legacyApps, $baseUrl )
				{
					$rows = array();
 					if( !empty( $legacyApps ) AND \is_array( $legacyApps ) )
					{
						foreach ( $legacyApps as $k => $app )
						{
							$buttons = array();
							if( \IPS\IPS::canManageResources() AND \IPS\IPS::checkThirdParty() )
							{
								$buttons['upgrade'] = array(
									'icon' => 'upload',
									'title' => 'upload_new_version',
									'link' => \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$k}&do=upload" ),
									'data' => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'upload_new_version' ) )
								);
							}
								
							if( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'app_delete' ) )
							{
								$buttons['delete'] = array(
									'icon'	=> 'times-circle',
									'title'	=> 'uninstall',
									'link'	=> $baseUrl->setQueryString( array( 'do' => 'delete', 'id' => $k ) )->csrf(),
									'data' 	=> array( 'delete' => '' ),
									'hotkey'=> 'd'
								);
							}
							
							$rows[ $k ] = \IPS\Theme::i()->getTemplate( 'trees' )->row( $baseUrl, $k, $app['title'], FALSE, $buttons );
						}
					}
					return $rows;
				},
				function( $key, $root=FALSE ) use ( $legacyApps, $baseUrl )
				{
					$buttons = array();
					if( \IPS\IPS::canManageResources() AND \IPS\IPS::checkThirdParty() )
					{
						$buttons['upgrade']	= array(
							'icon' => 'upload',
							'title' => 'upload_new_version',
							'link' => \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$key}&do=upload" ),
							'data' => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'upload_new_version' ) )
						);
					}
					
					if( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'app_delete' ) )
					{
						$buttons['delete'] = array(
							'icon'	=> 'times-circle',
							'title'	=> 'uninstall',
							'link'	=> $baseUrl->setQueryString( array( 'do' => 'delete', 'id' => $key ) )->csrf(),
							'data' 	=> array( 'delete' => '' ),
							'hotkey'=> 'd'
						);
					}
					
					return \IPS\Theme::i()->getTemplate( 'trees' )->row( $baseUrl, $key, $legacyApps[ $key ]['title'], FALSE, $buttons, '', NULL, NULL, $root );
				},
				function() { return 0; },
				function() { return array(); },
				function() { return array(); },
				FALSE,
				TRUE,
				TRUE
			);

			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'applications' )->applicationWrapper( $legacyTree, 'legacy_applications' );
		}

		/* Javascript */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_system.js', 'core', 'admin' ) );
		
		/* Check for updates button */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'	=> 'refresh',
			'link'	=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications&do=updateCheck' )->csrf(),
			'title'	=> 'check_for_updates',
		);
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->sidebar['actions']['build_all'] = array(
				'icon'	=> 'cogs',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications&do=buildAll' ),
				'title'	=> 'build_all_apps',
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('build_all_apps') )
			);
		}
	}
	
	/**
	 * Check for updates
	 *
	 * @return	void
	 */
	public function updateCheck()
	{
		\IPS\Session::i()->csrfCheck();
		
		$task = \IPS\Task::constructFromData( \IPS\Db::i()->select( '*', 'core_tasks', array( 'app=? AND `key`=?', 'core', 'updatecheck' ) )->first() );
		$task->type = 'manual';
		$task->run();
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications" ), 'update_check_complete' );
	}
	
	/**
	 * Set as default app
	 *
	 * @return void
	 */
	public function setAsDefault()
	{
		\IPS\Session::i()->csrfCheck();
		
		$application = \IPS\Application::load( \IPS\Request::i()->appKey );
		
		if ( !\count( $application->modules( 'front' ) ) )
		{
			\IPS\Output::i()->error( 'app_cannot_be_default', '2C133/W', 403, '' );
		}
		
		$application->setAsDefault();
		\IPS\Session::i()->log( 'acplog__application_set_default', array( $application->titleForLog() => FALSE ) );
	
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications" ), 'saved' );
	}

	/**
	 * Specify the default module
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
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_for_default', '2C133/A', 403, '' );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&root={$module->application}" ), 'saved' );
	}

	/**
	 * Get Child Rows
	 *
	 * @param	int|string	$id		Row ID
	 * @return	array
	 */
	public function _getChildren( $id )
	{
		$rows = array();

		$nodeClass = $this->nodeClass;

		try
		{
			$node	= $nodeClass::load( $id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S101/R', 404, '' );
		}

		foreach ( $node->children( NULL ) as $child )
		{
			if( $child->area == 'admin' )
			{
				continue;
			}

			$id = ( $child instanceof $this->nodeClass ? '' : 's.' ) . $child->_id;
			$rows[ $id ] = $this->_getRow( $child );
		}
		return $rows;
	}
		
	/**
	 * Get Single Row
	 *
	 * @param	mixed	$id		May be ID number (or key) or an \IPS\Node\Model object
	 * @param	bool	$root	Format this as the root node?
	 * @param	bool	$noSort	If TRUE, sort options will be disabled (used for search results)
	 * @return	string
	 * @note	Overridden so we can set the status toggle information to provide the offline message/permissions functionality
	 */
	public function _getRow( $id, $root=FALSE, $noSort=FALSE )
	{
		/* Load the node first */
		if ( $id instanceof \IPS\Node\Model )
		{
			$node = $id;
		}
		else
		{
			try
			{
				$nodeClass = $this->nodeClass;
				$node = $nodeClass::load( $id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2S101/P', 404, '' );
			}
		}

		/* Don't do this for modules, just applications */
		if( $node instanceof \IPS\Application\Module )
		{
			return parent::_getRow( $node, $root, $noSort );
		}
		
		/* Work out buttons */				
		$buttons = $node->getButtons( $this->url, !( $node instanceof $this->nodeClass ) );
		if ( isset( \IPS\Request::i()->searchResult ) and isset( $buttons['edit'] ) )
		{
			$buttons['edit']['link'] = $buttons['edit']['link']->setQueryString( 'searchResult', \IPS\Request::i()->searchResult );
		}

		/* Return */			
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
			$this->url,
			$node->_id,
			\IPS\Theme::i()->getTemplate('applications')->appRowTitle( $node ),
			$node->childrenCount( NULL ),
			$buttons,
			$node->_description,
			$node->_icon ? $node->_icon : NULL,
			( $node->canEdit() ) ? $node->_position : NULL,
			$root,
			$node->_enabled,
			( $node->_locked or !$node->canEdit() or \IPS\NO_WRITES ),
			( ( $node instanceof \IPS\Node\Model ) ? $node->_badge : $this->_getRowBadge( $node ) ),
			TRUE,
			$this->_descriptionHtml,
			$node->canAdd(),
			TRUE,
			\IPS\Theme::i()->getTemplate('applications')->appRowAdditional( $node ),
			$node->_lockedLang
		);
	}

	/**
	 * Permissions Form
	 *
	 * @return	void
	 */
	protected function permissions()
	{
		/* Work out which class we're using */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			return parent::permissions();
		}
			
		/* Load Node */
		try
		{
			$node = $nodeClass::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '3S101/A', 404, '' );
		}
		
		/* Check we're not locked */
		if( $node->_locked or !$node->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_enable', '2S101/3', 403, '' );
		}

		/* Create the form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'app_enabled', $node->disabled_groups === NULL, TRUE, array( 'togglesOff' => array( 'app_disabled_groups', 'app_disabled_message_editor' ), 'disabled' => \IPS\NO_WRITES ) ) );
		if ( \IPS\NO_WRITES )
		{
			\IPS\Member::loggedIn()->language()->words['app_enabled_desc'] = \IPS\Member::loggedIn()->language()->addToStack( 'app_enabled_desc_no_writes' );
		}
		
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'app_disabled_groups', ( $node->disabled_groups == '*' or $node->disabled_groups === NULL ) ? '*' : explode( ',', $node->disabled_groups ), FALSE, array(
			'options' 	=> array_combine( array_keys( \IPS\Member\Group::groups() ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups() ) ),
			'multiple' 	=> true,
			'unlimited'		=> '*',
			'unlimitedLang'	=> 'all',
			'impliedUnlimited' => TRUE
		), NULL, NULL, NULL, 'app_disabled_groups' ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'app_disabled_message', $node->disabled_message, FALSE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => $node->_key . 'app_disabled_message', 'attachIds' => array( $node->id, NULL, 'appdisabled' ) ), NULL, NULL, NULL, 'app_disabled_message_editor' ) );
				
		/* And then save the values, if appropriate */
		if ( $values = $form->values() )
		{
			$node->disabled_message	= $values['app_disabled_message'];
			$node->disabled_groups	= $values['app_enabled'] ? NULL : ( $values['app_disabled_groups'] == '*' ? '*' : implode( ',', $values['app_disabled_groups'] ) );
			$node->save();
			
			if ( !\IPS\NO_WRITES )
			{
				\IPS\Plugin\Hook::writeDataFile();
			}
			
			/* Clear templates to rebuild automatically */
			\IPS\Theme::deleteCompiledTemplate();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			$this->_logToggleAndRedirect( $node );
		}

		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Build form
	 *
	 * @param	string	$appKey					The application key
	 * @param	bool	$includeDownloadOption	If a "just download" option should be included
	 * @return	\IPS\Helpers\Form
	 */
	protected function _buildForm( $appKey, $includeDownloadOption=FALSE )
	{
		$json = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$appKey}/data/versions.json" ), TRUE );
		ksort( $json );
		
		$defaults = array( 'human' => '1.0.0', 'long' => '10000' );
		$long = NULL;
		$human = NULL;
		foreach ( array_reverse( $json, TRUE ) as $long => $human )
		{
			$exploded = explode( '.', $human );
			$defaults['human'] = "{$exploded[0]}.{$exploded[1]}." . ( \intval( $exploded[2] ) + 1 );
			$defaults['long'] = $long + 1;
			break;
		}
		
		$options = array(
			'options'	=> array(),
			'toggles'	=> array( 'new' => array( 'versions_human', 'versions_long' ) )
		);
		if ( $human !== NULL )
		{
			$options['options']['rebuild'] = 'developer_build_type_rebuild';
			\IPS\Member::loggedIn()->language()->words['developer_build_type_rebuild'] = sprintf( \IPS\Member::loggedIn()->language()->get('developer_build_type_rebuild'), $human );
		}
		$options['options']['new'] = 'developer_build_new';
		if ( $includeDownloadOption )
		{
			$options['options']['download'] = 'developer_build_download';
		}
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'developer_build_type', 'rebuild', TRUE, $options ) );
		$form->add( new \IPS\Helpers\Form\Text( 'versions_human', $defaults['human'], NULL, array(), function( $val )
		{
			if ( !preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $val ) )
			{
				throw new \DomainException( 'versions_human_bad' );
			}
		}, NULL, NULL, 'versions_human' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'versions_long', $defaults['long'], NULL, array(), function( $val ) use ( $json )
		{
			if ( !preg_match( '/^\d*$/', $val ) )
			{
				throw new \DomainException( 'form_number_bad' );
			}
			if( $val < 10000 )
			{
				throw new \DomainException( 'versions_long_too_low' );
			}
			if( isset( $json[ $val ] ) )
			{
				throw new \DomainException( 'versions_long_exists' );
			}
		}, NULL, NULL, 'versions_long' ) );
		
		return $form;
	}
	
	/**
	 * Build all applications
	 *
	 * @return void
	 */
	public function buildAll()
	{
		if ( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C133/M', 403, '' );
		}
		
		$form = $this->_buildForm( 'core' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'developer_build_submit', (bool) \IPS\IPS_PASSWORD ) );
		
		if ( $values = $form->values() )
		{
			foreach ( \IPS\Application::applications() as $application )
			{
			    /* Skip building Marketplace applications */
			    if( $application->marketplace_id )
                {
                    continue;
                }

				if ( $values['developer_build_type'] === 'new' )
				{
					$application->assignNewVersion( $values['versions_long'], $values['versions_human'] );
				}
				
				try
				{
					$application->build();
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->error( $e->getMessage(), '' );
				}
			}
			
			if ( $values['developer_build_submit'] )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications&do=submit' )->csrf() );
			}
			else
			{				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'application_now_built' );
			}
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Build an application
	 *
	 * @return void
	 */
	public function build()
	{
		if ( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C133/N', 403, '' );
		}
		
		$application = \IPS\Application::load( \IPS\Request::i()->appKey );
		
		$form = $this->_buildForm( $application->directory );
		
		if ( $values = $form->values() )
		{
			if ( $values['developer_build_type'] === 'new' )
			{
				$application->assignNewVersion( $values['versions_long'], $values['versions_human'] );
			}
			
			try
			{
				$application->build();
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '' );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'application_now_built' );
		}
			
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Export an application from ipbmafia.ru
	 *
	 * @return void
	 */
	public function downloadMafia()
	{
		$application = \IPS\Application::load( \IPS\Request::i()->appKey );

		try
		{
			$pharPath	= str_replace( '\\', '/', rtrim( \IPS\TEMP_DIRECTORY, '/' ) ) . '/' . $application->directory . ".tar";
			$download	= new \PharData( $pharPath, 0, $application->directory . ".tar", \Phar::TAR );
				
			$download->buildFromIterator( new \IPS\Application\BuilderIterator( $application ) );
		}
		catch( \PharException $e )
		{
			\IPS\Log::log( $e, 'phar' );
			\IPS\Output::i()->error( 'app_no_phar', '4C133/7', 403, '' );
		}
	
		$output	= \file_get_contents( rtrim( \IPS\TEMP_DIRECTORY, '/' ) . '/' . $application->directory . ".tar" );
	
		/* Cleanup */
		unset($download);
		\Phar::unlinkArchive($pharPath);
	
		\IPS\Output::i()->sendOutput( $output, 200, 'application/tar', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', \IPS\Member::loggedIn()->language()->get('__app_' . $application->directory ) . " {$application->version}.tar" ) ), FALSE, FALSE, FALSE );
	}
	
	/**
	 * Export an application
	 *
	 * @return void
	 * @note	We have to use a custom RecursiveDirectoryIterator in order to skip the /dev folder
	 */
	public function download()
	{
		$application = \IPS\Application::load( \IPS\Request::i()->appKey );

		/* Downloads need developer mode */
		if( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C133/10', 403, '' );
		}

		/* Don't allow downloads of Marketplace resources */
		if( $application->marketplace_id )
        {
            // \IPS\Output::i()->error( 'application_cannot_build_marketplace', '2C133/Y', 403, '' );
        }
		
		$form = $this->_buildForm( $application->directory, TRUE );
		if ( $values = $form->values() )
		{
			if ( $values['developer_build_type'] !== 'download' )
			{
				if ( $values['developer_build_type'] === 'new' )
				{
					$application->assignNewVersion( $values['versions_long'], $values['versions_human'] );
				}
				try
				{
					$application->build();
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->error( $e->getMessage(), '' );
				}
			}
				
			try
			{
				$pharPath	= str_replace( '\\', '/', rtrim( \IPS\TEMP_DIRECTORY, '/' ) ) . '/' . $application->directory . ".tar";
				$download	= new \PharData( $pharPath, 0, $application->directory . ".tar", \Phar::TAR );
				
				$download->buildFromIterator( new \IPS\Application\BuilderIterator( $application ) );
			}
			catch( \PharException $e )
			{
				\IPS\Log::log( $e, 'phar' );
				\IPS\Output::i()->error( 'app_no_phar', '4C133/7', 403, '' );
			}
	
			$output	= \file_get_contents( rtrim( \IPS\TEMP_DIRECTORY, '/' ) . '/' . $application->directory . ".tar" );
	
			/* Cleanup */
			unset($download);
			\Phar::unlinkArchive($pharPath);
	
			\IPS\Output::i()->sendOutput( $output, 200, 'application/tar', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', \IPS\Member::loggedIn()->language()->get('__app_' . $application->directory ) . " {$application->version}.tar" ) ), FALSE, FALSE, FALSE );
		}
		
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Toggle Enabled/Disable
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		parent::enableToggle();

		/* update the essential cookie name list */
		unset( \IPS\Data\Store::i()->essentialCookieNames );
	}

	/**
	 * Upgrade an application that is currently installed. After importing a PHAR the user is redirected to this method.
	 *
	 * @see		\IPS\core\modules\admin\applications\applications::import()
	 * @return	void
	 */
	public function upgrade()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('upgrading_application');
		
		unset( \IPS\Data\Store::i()->syncCompleted );

		$url = \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=upgrade&appKey=" . \IPS\Request::i()->appKey )->csrf();
		if ( isset( \IPS\Request::i()->marketplace ) )
		{
			$url = $url->setQueryString( 'marketplace', \IPS\Request::i()->marketplace );
		}

		\IPS\Output::i()->output	= new \IPS\Helpers\MultipleRedirect(
			$url,
			function( $data )
			{
				/* On first cycle return data */
				if ( !\is_array( $data ) )
				{
					/* Does this application exist in the database? */
					try
					{
						$app = \IPS\Application::load( \IPS\Request::i()->appKey );
					}
					catch( \OutOfRangeException $e )
					{
						\IPS\Output::i()->error( 'no_app_to_update', '3C133/G', 403, '' );
					}

					/* Get the application data to update the application record */
					if( file_exists( \IPS\Application::getRootPath( \IPS\Request::i()->appKey ) . '/applications/' . \IPS\Request::i()->appKey . '/data/application.json' ) )
					{
						$application	= json_decode( file_get_contents( \IPS\Application::getRootPath( $app->directory ) . '/applications/' . $app->directory . '/data/application.json' ), TRUE );

						//\IPS\Lang::saveCustom( $app->directory, "__app_{$app->directory}", $application['application_title'] );

						unset( $application['app_directory'], $application['app_protected'], $application['application_title'] );

						foreach( $application as $column => $value )
						{
							$column			= preg_replace( "/^app_/", "", $column );
							$app->$column	= $value;
						}

						$app->save();
					}
					else
					{
						\IPS\Output::i()->error( 'app_invalid_data', '3C133/H', 403, '' );
					}

					return array(
						array( 'laststep' => 'start', 'key' => \IPS\Request::i()->appKey ),
						\IPS\Member::loggedIn()->language()->addToStack('installing_application'),
						1
					);
				}

				/* Install the application in stages */
				$laststep	= NULL;
				$language	= NULL;
				$progress	= 1;
				$extra		= NULL;

				switch( $data['laststep'] )
				{
					case 'start':
						/* Determine our current version and the last version we ran */
						$currentVersion	= \IPS\Application::load( $data['key'] )->long_version;
						$allVersions	= \IPS\Application::load( $data['key'] )->getAllVersions();
						$longVersions	= array_keys( $allVersions );
						$humanVersions	= array_values( $allVersions );
						$lastRan		= ( isset( $data['extra']['_last'] ) ) ? \intval( $data['extra']['_last'] ) : $currentVersion;
						
						if( \count($allVersions) )
						{
							$latestLVersion	= array_pop( $longVersions );
							$latestHVersion	= array_pop( $humanVersions );

							\IPS\Db::i()->insert( 'core_upgrade_history', array( 'upgrade_version_human' => $latestHVersion, 'upgrade_version_id' => $latestLVersion, 'upgrade_date' => time(), 'upgrade_mid' => (int) \IPS\Member::loggedIn()->member_id, 'upgrade_app' => $data['key'] ) );
						}
				
						/* Now find any upgrade paths since the last one we ran that need to be executed */
						$upgradeSteps	= \IPS\Application::load( $data['key'] )->getUpgradeSteps( $lastRan );
						
						/* Did we find any? */
						if( \count( $upgradeSteps ) )
						{
							/* Re-initialize $extra variable */
							$extra	= array();

							/* Store a count of all the upgrade steps for later use */
							if( !$lastRan )
							{
								$extra['_totalSteps']			= \count($upgradeSteps);
								$data['extra']['_totalSteps']	= $extra['_totalSteps'];
							}
							else
							{
								$extra['_totalSteps']			= $data['extra']['_totalSteps'];
							}

							/* We need to populate \IPS\Request with the extra data returned from the last upgrader step call */
							if( isset( $data['extra']['_upgradeData'] ) )
							{
								\IPS\Request::i()->extra	= $data['extra']['_upgradeData'];
							}

							/* Grab next upgrade step to run */
							$_next	= array_shift( $upgradeSteps );

							/* Set this now - we can reset later if we need to re-run this step */
							$extra['_last']	= $_next;

							/* What step in the upgrader file are we on? */
							$upgradeStep	= ( isset($data['extra']['_upgradeStep']) ) ? \intval($data['extra']['_upgradeStep']) : 1;
							
							/* Delete removed language strings */ 
							if( file_exists( \IPS\ROOT_PATH . "/applications/{$data['key']}/setup/upg_{$_next}/lang.json" ) )
 							{
 								$langChanges = json_decode( \file_get_contents( \IPS\ROOT_PATH . "/applications/{$data['key']}/setup/upg_{$_next}/lang.json" ), TRUE );
 								if ( isset( $langChanges['normal']['removed'] ) and $langChanges['normal']['removed'] )
 								{
 									\IPS\Db::i()->delete( 'core_sys_lang_words', array( array( 'word_app=?', $data['key'] ), array( 'word_js=0' ), array( \IPS\Db::i()->in( 'word_key', $langChanges['normal']['removed'] ) ) ) );
 								}
 								if ( isset( $langChanges['js']['removed'] ) and $langChanges['js']['removed'] )
 								{
 									\IPS\Db::i()->delete( 'core_sys_lang_words', array( array( 'word_app=?', $data['key'] ), array( 'word_js=1' ), array( \IPS\Db::i()->in( 'word_key', $langChanges['js']['removed'] ) ) ) );
 								}
 							}

							/* If we haven't run the raw queries yet, do so */
							if( $upgradeStep == 1 AND !isset( $data['extra']['_upgradeData'] ) )
							{
								\IPS\Application::load( $data['key'] )->installDatabaseUpdates( $_next );
							}

							/* Get the object */
							$_className		= "\\IPS\\{$data['key']}\\setup\\upg_{$_next}\\Upgrade";
							$_methodName	= "step{$upgradeStep}";

							if( class_exists( $_className ) )
							{
								$upgrader		= new $_className;

								/* If the next step exists, run it */
								if( method_exists( $upgrader, $_methodName ) )
								{
									/* Get custom title first as the step may unset session variables that are being referenced */
									$customTitleMethod = 'step' . $upgradeStep . 'CustomTitle';

									if ( method_exists( $upgrader, $customTitleMethod ) )
									{
										$language = $upgrader->$customTitleMethod();
									}

									$result		= $upgrader->$_methodName();

									/* If the result is 'true' we move on to the next step, otherwise we need to run the same step again and store the data returned */
									if( $result === TRUE )
									{
										$_nextMethodStep	= "step" . ( $upgradeStep + 1 );

										if( method_exists( $upgrader, $_nextMethodStep ) )
										{
											/* We have another step to run - set the data and move along */
											$extra['_last']			= $lastRan;
											$extra['_upgradeStep']	= $upgradeStep + 1;
										}
									}
									else
									{
										/* Store the data returned, set the step to the same/current one, and re-run */
										$extra['_upgradeData']	= $result;
										$extra['_upgradeStep']	= $upgradeStep;
										$extra['_last']			= $lastRan;
									}
								}
							}

							$laststep		= 'start';
							$language		= $language ?: \IPS\Member::loggedIn()->language()->addToStack('appupdate_databasechanges', FALSE, array( 'sprintf' => $allVersions[ $_next ] ) );
							$progress		= round( ( 30 * ( $data['extra']['_totalSteps'] - \count($upgradeSteps) ) ) / ( $data['extra']['_totalSteps'] ?: 1 ) );
						}
						else
						{
							$laststep		= 'db';
							$language		= \IPS\Member::loggedIn()->language()->addToStack('appinstall_databasechanges');
							$progress		= 30;
						}
					break;

					case 'db':
						/* Rebuild data */
						\IPS\Application::load( $data['key'] )->installJsonData();

						$laststep	= 'basics';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_basics');
						$progress	= 40;
					break;

					case 'basics':
						/* Insert lang data */
						\IPS\Application::load( $data['key'] )->installLanguages();

						$laststep	= 'lang';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_languages');
						$progress	= 60;
					break;

					case 'lang':
						/* Insert email templates */
						\IPS\Application::load( $data['key'] )->installEmailTemplates();

						$hasCmsTemplates = file_exists( \IPS\ROOT_PATH . '/applications/' . \IPS\Application::load( $data['key'] )->directory . '/data/cmsTemplates.xml' );
						$laststep	= $hasCmsTemplates ? 'emails' : 'cmstemplates';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_emails');
						$progress	= 75;
					break;

					case 'emails':
						/* Get Templates and Insert */
						$_SESSION['cmsConflictKey'] = '';
						try
						{
							$result = \IPS\cms\Templates::importUserTemplateXml( \IPS\Application::getRootPath( $data['key'] ) . '/applications/' . \IPS\Application::load( $data['key'] )->directory . '/data/cmsTemplates.xml' );
							if( $result instanceof \IPS\Http\Url\Internal )
							{
								$_SESSION['cmsConflictKey'] = $result->setQueryString( [ 'application' => \IPS\Application::load( $data['key'] )->directory, 'lang' => 'updated' ] );
							}
						}
						catch( \Throwable $e )
						{
							\IPS\Log::log( $e, 'cms_template_app_install' );
						}

						$laststep	= 'cmstemplates';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_cmstemplates');
						$progress	= 95;
					break;

					case 'cmstemplates':
						/* Insert skin templates */
						\IPS\Application::load( $data['key'] )->installSkins( TRUE );
						\IPS\Application::load( $data['key'] )->installJavascript();

						$laststep	= NULL;
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_skins');
						$progress	= 100;
					break;
				}

				/* Return null to indicate we are done */
				if( $laststep === NULL )
				{
					if( \IPS\Request::i()->marketplace AND !\IPS\Application::load( $data['key'] )->marketplace_id )
					{
						\IPS\Application::load( $data['key'] )->marketplace_id = (int) \IPS\Request::i()->marketplace;
						\IPS\Application::load( $data['key'] )->save();
					}
					\IPS\Session::i()->log( 'acplog__application_updated', array( \IPS\Application::load( $data['key'] )->titleForLog() => FALSE, \IPS\Application::load( $data['key'] )->version => TRUE ) );

					return NULL;
				}
				else
				{
					return array( array( 'laststep' => $laststep, 'key' => $data['key'], 'extra' => $extra ), $language, $progress );
				}
			},
			function()
			{
				/* IPS Cloud Sync */
				\IPS\IPS::resyncIPSCloud('Upgraded application in ACP');
				$application = \IPS\Application::load( \IPS\Request::i()->appKey );

				/* Unlock the app for PHP8 Usage */
				if ( $application->requires_manual_intervention )
				{
					$application->requires_manual_intervention = 0;
					$application->save();
				}
				
				/* Invalidate disk templates */
				\IPS\Theme::resetAllCacheKeys();

				/* If CMS Template install had conflicts, solve those now */
				if( !empty( $_SESSION['cmsConflictKey'] ) )
				{
					if( $application->marketplace_id )
					{
						$_SESSION['cmsConflictKey'] = $_SESSION['cmsConflictKey']->setQueryString( 'marketplace', $application->marketplace_id );
					}
					\IPS\Output::i()->redirect( $_SESSION['cmsConflictKey'] );
				}
			
				/* And redirect back to the overview screen (or marketplace if it was installed from there) */
				if( $application->marketplace_id )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . $application->marketplace_id ), 'application_now_updated' );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'application_now_updated' );
				}
			}
		);
	}

	/**
	 * Install an application that is currently stored on disk. After importing a PHAR the user is redirected to this method.
	 *
	 * @see		\IPS\core\modules\admin\applications\applications::import()
	 * @return	void
	 */
	public function install()
	{
		\IPS\Session::i()->csrfCheck();
		
		unset( \IPS\Data\Store::i()->syncCompleted );
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('installing_application');

		$url = \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=install&appKey=" . \IPS\Request::i()->appKey )->csrf();
		if ( isset( \IPS\Request::i()->marketplace ) )
		{
			$url = $url->setQueryString( 'marketplace', \IPS\Request::i()->marketplace );
		}

		\IPS\Output::i()->output	= new \IPS\Helpers\MultipleRedirect(
			$url,
			function( $data )
			{
				/* On first cycle return data */
				if ( !\is_array( $data ) )
				{
					/* Does this application exist in the database? */
					try
					{
						$application = \IPS\Application::load( \IPS\Request::i()->appKey );

						if( $application->id )
						{
							\IPS\Output::i()->error( 'app_already_installed', '2C133/4', 403, '' );
						}
					}
					catch( \OutOfRangeException $e ){} // We don't need to do anything if it hasn't loaded - that's good
					
					/* Get the application data to insert the application record */
					if( file_exists( \IPS\Application::getRootPath( \IPS\Request::i()->appKey ) . '/applications/' . \IPS\Request::i()->appKey . '/data/application.json' ) )
					{
						$application	= json_decode( file_get_contents( \IPS\Application::getRootPath( \IPS\Request::i()->appKey ) . '/applications/' . \IPS\Request::i()->appKey . '/data/application.json' ), TRUE );

						if( !$application['app_directory'] )
						{
							\IPS\Output::i()->error( 'app_invalid_data', '4C133/5', 403, '' );
						}

						$application['app_position']	= \IPS\Db::i()->select( 'MAX(app_position)', 'core_applications' )->first() + 1;
						$application['app_added']		= time();
						$application['app_protected']	= 0;
						$application['app_enabled']		= 0;	/* We will reset this post-installation */

						//\IPS\Lang::saveCustom( $application['app_directory'], "__app_{$application['app_directory']}", $application['application_title'] );
						unset($application['application_title']);

						\IPS\Db::i()->insert( 'core_applications', $application );
					}
					else
					{
						\IPS\Output::i()->error( 'app_invalid_data', '4C133/6', 403, '' );
					}

					return array(
						array( 'laststep' => 'start', 'key' => \IPS\Request::i()->appKey ),
						\IPS\Member::loggedIn()->language()->addToStack('installing_application'),
						1
					);
				}

				/* Install the application in stages */
				$laststep	= NULL;
				$language	= NULL;
				$progress	= 1;

				switch( $data['laststep'] )
				{
					case 'start':
						/* Perform database changes */
						\IPS\Application::load( $data['key'] )->installDatabaseSchema();

						$laststep	= 'db';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_databasechanges');
						$progress	= 12.5;
					break;

					case 'db':
						/* Rebuild data */
						\IPS\Application::load( $data['key'] )->installJsonData();

						$laststep	= 'basics';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_basics');
						$progress	= 25;
					break;

					case 'basics':
						/* Insert lang data */
						$offset = ( isset( $data['offset'] ) ) ? \intval( $data['offset'] ) : 0;

						$inserted	= \IPS\Application::load( $data['key'] )->installLanguages( $offset, 250 );

						if( $inserted )
						{
							$laststep		= 'basics';
							$data['offset']	= $offset + $inserted;
						}
						else
						{
							$laststep	= 'lang';
							unset( $data['offset'] );
						}

						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_languages');
						$progress	= 37.5;
					break;

					case 'lang':
						/* Insert email templates */
						\IPS\Application::load( $data['key'] )->installEmailTemplates();

						$laststep	= 'emails';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_emails');
						$progress	= 50;
					break;
					
					case 'emails':
						/* Install Extensions */
						\IPS\Application::load( $data['key'] )->installExtensions();
						
						$laststep	= 'extensions';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_extensions');
						$progress	= 62.5;
					break;

					case 'extensions':
						/* Insert skin templates */
						$offset = ( isset( $data['offset'] ) ) ? \intval( $data['offset'] ) : 0;

						if( !$offset )
						{
							\IPS\Application::load( $data['key'] )->installThemeSettings();
							\IPS\Application::load( $data['key'] )->clearTemplates();
						}
						
						$inserted = \IPS\Application::load( $data['key'] )->installTemplates( FALSE, $offset, 150 );

						if( $inserted )
						{
							$laststep		= 'extensions';
							$data['offset']	= $offset + $inserted;
						}
						else
						{
							$laststep	= 'skins';
							unset( $data['offset'] );
						}

						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_skins');
						$progress	= 75;
					break;

					case 'skins':
						/* Insert skin templates */
						\IPS\Application::load( $data['key'] )->installJavascript();
						
						$hasCmsTemplates = file_exists( \IPS\Application::getRootPath( $data['key'] ) . '/applications/' . $data['key'] . '/data/cmsTemplates.xml' );
						$laststep	= $hasCmsTemplates ? 'cmstemplates' : 'javascript';
						$language	= $hasCmsTemplates ? \IPS\Member::loggedIn()->language()->addToStack('appinstall_cmstemplates') : \IPS\Member::loggedIn()->language()->addToStack('appinstall_javascript');
						$progress	= 87.5;
					break;

					case 'cmstemplates':
						/* Get Templates and Insert */
						$_SESSION['cmsConflictKey'] = '';
						try
						{
							$result = \IPS\cms\Templates::importUserTemplateXml( \IPS\Application::getRootPath( $data['key'] ) . '/applications/' . $data['key'] . '/data/cmsTemplates.xml' );
							if( $result instanceof \IPS\Http\Url\Internal )
							{
								$_SESSION['cmsConflictKey'] = $result->setQueryString( [ 'application' => $data['key'], 'lang' => 'installed' ] );
							}
						}
						catch( \Throwable $e )
						{
							\IPS\Log::log( $e, 'cms_template_app_install' );
						}

						$laststep	= 'javascript';
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_javascript');
						$progress	= 95;
					break;

					case 'javascript':
						/* Insert other data */
						\IPS\Application::load( $data['key'] )->installOther();

						$laststep	= NULL;
						$language	= \IPS\Member::loggedIn()->language()->addToStack('appinstall_finish');
						$progress	= 100;
					break;
				}

				/* Return null to indicate we are done */
				if( $laststep === NULL )
				{
					if( \IPS\Request::i()->marketplace )
					{
						\IPS\Application::load( $data['key'] )->marketplace_id = (int) \IPS\Request::i()->marketplace;
						\IPS\Application::load( $data['key'] )->save();
					}

					\IPS\Session::i()->log( 'acplog__application_installed', array( "__app_" . $data['key'] => TRUE ) );

					return NULL;
				}
				else
				{
					$data['laststep']	= $laststep;
					return array( $data, $language, $progress );
				}
			},
			function()
			{
				/* Enable the application now */
				$application = \IPS\Application::load( \IPS\Request::i()->appKey );
				$application->enabled	= 1;
				$application->save();

				/* Install hooks - do this after enabling the application */
				$application->installHooks();

				/* Clear caches so templates can rebuild and so on */
				\IPS\Data\Store::i()->clearAll();
				\IPS\Data\Cache::i()->clearAll();
				\IPS\IPS::resyncIPSCloud('Installed application in ACP');
				
				/* Invalidate disk templates */
				\IPS\Theme::resetAllCacheKeys();

				/* If CMS Template install had conflicts, solve those now */
				if( !empty( $_SESSION['cmsConflictKey'] ) )
				{
					if( $application->marketplace_id )
					{
						$_SESSION['cmsConflictKey'] = $_SESSION['cmsConflictKey']->setQueryString( 'marketplace', $application->marketplace_id );
					}
					\IPS\Output::i()->redirect( $_SESSION['cmsConflictKey'] );
				}

				/* And redirect back to the overview screen (or marketplace if it was installed from there) */
				if( $application->marketplace_id )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . $application->marketplace_id ), 'application_now_installed' );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'application_now_installed' );
				}
			}
		);
	}

	/**
	 * Delete
	 *
	 * @return	void
	 * @note	For application uninstall we don't need the whole move children thing
	 */
	protected function delete()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		
		try
		{
			/* Load the application - we don't use \IPS\Application::load() because this could be a legacy out of date application
				and if no Application.php an exception will be thrown. We don't need to stop just based on that, we want to proceed with delete. */
			$node = NULL;

			foreach( \IPS\Data\Store::i()->applications as $application )
			{
				if( $application['app_directory'] == \IPS\Request::i()->id )
				{
					$node	= \IPS\Application::constructFromData( $application );
					break;
				}
			}

			if( $node === NULL )
			{
				throw new \UnexpectedValueException;
			}
			
			/* Permission check */
			if( !$node->canDelete() )
			{
				\IPS\Output::i()->error( 'node_noperm_delete', '2C133/J', 403, '' );
			}
	
			if ( $node->default )
			{
				return $this->setNewDefaultApplication($node);
			}
			else
			{
				/* Make sure the user confirmed the deletion */
				\IPS\Request::i()->confirmedDelete();
			}
	
			/* Delete it */
			\IPS\Session::i()->log( 'acplog__application_uninstalled', array( $node->directory => TRUE ) );
			$node->delete();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			/* Clear \Data\Store */
			\IPS\Data\Store::i()->clearAll();
		}
		/* Legacy */
		catch ( \UnexpectedValueException $e )
		{
			if( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'app_delete' ) )
			{
				\IPS\Output::i()->error( 'node_noperm_delete', '2C133/J', 403, '' );
			}
			
			\IPS\Db::i()->delete( 'core_applications', array( 'app_directory=?', \IPS\Request::i()->id ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C133/I', 404, '' );
		}

		/* Boink */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'deleted' );
	}


	/**
	 * Set a new default application if the current default app is being uninstalled
	 *
	 * @param	$node	\IPS\Application	Application to delete
	 * @return	void
	 */
	protected function setNewDefaultApplication($node)
	{
		$form = new \IPS\Helpers\Form();
		$form->hiddenValues['wasConfirmed']	= 1;
		$form->add( new \IPS\Helpers\Form\Node( 'new_default_app', NULL, TRUE, array(
				'class'					=> 'IPS\Application',
				'subnodes' => false,
				'permissionCheck' => function( $app ) use ( $node )
					{
 						if ( $app->directory == 'core')
						{
							return false;
						}
						else
						{
							return !($node->directory == $app->directory);
						}

					}
		) ) );

		if  ( $values = $form->values() )
		{
			$values['new_default_app']->setAsDefault();
			\IPS\Session::i()->log( 'acplog__application_set_default', array( $values['new_default_app']->titleForLog() => FALSE ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=delete&id={$node->_id}&wasConfirmed=1" )->csrf() );
		}
		else
		{
			\IPS\Output::i()->output = (string) $form;
		}
	}

	/**
	 * View application details
	 *
	 * @return	void
	 */
	public function details()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}

		/* Get the application */
		try
		{
			$application	= $nodeClass::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'error_no_app', '2C133/1', 404, '' );
		}

		/* Work out tab */
		$tab				= \IPS\Request::i()->tab ?: 'details';
		$tabFunction		= array( $this, '_show' . mb_ucfirst( $tab ) );
		$activeTabContents	= $tabFunction( $application );

		/* If this is an AJAX request, just return tab contents */
		if( \IPS\Request::i()->isAjax() && \IPS\Request::i()->tab and !isset( \IPS\Request::i()->ajaxValidate ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}

		/* Build tab list */
		$tabs				= array();
		$tabs['details']	= 'app_details_details';
		$tabs['upgrades']	= 'app_details_upgrades';
		$tabs['hooks']		= 'app_details_hooks';

		/* Output */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( $tabs[ $tab ] );
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $tab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=details&id={$application->directory}" ) );
	}

	/**
	 * Upload a new application for installation
	 *
	 * @return void
	 */
	public function upload()
	{
		if ( \IPS\DEMO_MODE )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '1C133/V', 403, '' );
		}
		
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C133/B', 403, '' );
		}

		if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/applications/" ) )
		{
			\IPS\Output::i()->error( 'app_dir_not_write', '4C133/8', 500, '' );
		}
		
		if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/plugins/" ) ) // necessary as we write the hooks.php file here
		{
			\IPS\Output::i()->error( 'plugin_dir_not_write', '4C133/L', 403, '' );
		}

		if ( !\IPS\CIC2 AND file_exists( \IPS\ROOT_PATH . "/plugins/hooks.php") AND !is_writable( \IPS\ROOT_PATH . "/plugins/hooks.php" ) )
		{
			\IPS\Output::i()->error( 'plugin_file_not_write', '4C133/U', 403, '' );
		}
		
		if ( !\extension_loaded('phar') )
		{
			\IPS\Output::i()->error( 'no_phar_extension', '1C133/P', 403, '' );
		}
		
		if ( !\IPS\IPS::checkThirdParty() )
		{
			\IPS\Output::i()->error( 'cic_3rdparty_unavailable', '2C133/Z', 403, '' );
		}

		$_type	= 'install';

		/* Are we upgrading an application? */
		if( \IPS\Request::i()->appKey )
		{
			try
			{
				$app = NULL;

				foreach( \IPS\Data\Store::i()->applications as $application )
				{
					if( $application['app_directory'] == \IPS\Request::i()->appKey )
					{
						$app	= \IPS\Application::constructFromData( $application );
						break;
					}
				}

				if( $app === NULL )
				{
					throw new \OutOfRangeException;
				}
			}
			catch ( \UnexpectedValueException $e )
			{
				// Legacy 3.x app
				if ( !\IPS\CIC2 )
				{
					if ( !is_dir( \IPS\ROOT_PATH . "/applications/" . \IPS\Request::i()->appKey ) )
					{
						mkdir( \IPS\ROOT_PATH . "/applications/" . \IPS\Request::i()->appKey );
						chmod( \IPS\ROOT_PATH . "/applications/" . \IPS\Request::i()->appKey, \IPS\IPS_FOLDER_PERMISSION );
					}
				}
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'no_app_to_update', '2C133/C', 403, '' );
			}

			if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/applications/" . $app->directory ) )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( "app_specific_dir_nowrite", FALSE, array( 'sprintf' => $app->directory ) ), '4C133/D', 500, '' );
			}
			
			if ( \IPS\Theme::designersModeEnabled() )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( "app_upload_designersmode", FALSE, array( 'sprintf' => \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmode' ) ) ), '2C133/O', 403, '' );
			}

			/* Check Marketplace Status? */
			if( $app->marketplace_id )
			{
				// \IPS\Output::i()->error( 'app_upload_marketplace_only', '2C133/X', 403, '' );
			}

			$_type	= 'upgrade';
		}

		$form = new \IPS\Helpers\Form( 'form', 'install' );
		$form->addMessage('applications_manual_install_warning');
		$form->add( new \IPS\Helpers\Form\Upload( 'application_file', NULL, TRUE, array( 'allowedFileTypes' => array( 'tar' ), 'temporary' => TRUE ) ) );

		if ( $values = $form->values() )
		{
			try
			{
				if ( mb_substr( $values['application_file'], -4 ) !== '.tar' )
				{
					/* If rename fails on a significant number of customer's servers, we might have to consider using
						move_uploaded_file into uploads and rename in there */
					rename( $values['application_file'], $values['application_file'] . ".tar" );
					
					$values['application_file'] .= ".tar";
				}
				
				/* Test the phar */
				$application = new \PharData( $values['application_file'], 0, NULL, \Phar::TAR );
 
 				/* Get app directory */
				$appdata = json_decode( file_get_contents( "phar://" . $values['application_file'] . '/data/application.json' ), TRUE );
				
				/* Make sure that the app data is valid */
				if( !isset( $appdata['app_directory'] ) )
				{
					throw new \UnexpectedValueException;
				}
				
				$appDirectory	= $appdata['app_directory'];
				
				if ( \IPS\CIC2 )
				{
					unset( \IPS\Data\Store::i()->syncCompleted );
					
					\IPS\Cicloud\file( "IPScustomapp_{$appdata['app_directory']}.tar", \file_get_contents( $values['application_file'] ) );

					/* Check files are ready */
					$i = 0;
					do
					{
						if ( ( isset( \IPS\Data\Store::i()->syncCompleted ) AND \IPS\Data\Store::i()->syncCompleted ) OR $i >= 30 ) # 30 x 0.25 seconds
						{
							/* We need to wait for the backend to process the tar */
							sleep(3);
							break;
						}

						/* Pause slightly before checking the datastore again */
						usleep( 250000 );
						$i++;
					}
					while( TRUE );
				}
				else
				{
					/* Extract */
					$application->extractTo( \IPS\ROOT_PATH . "/applications/" . $appDirectory, NULL, TRUE );
					$this->_checkChmod( \IPS\ROOT_PATH . '/applications/' . $appDirectory );
					\IPS\IPS::resyncIPSCloud('Uploaded new application in ACP');
				}
			}
			catch( \PharException $e )
			{
				\IPS\Log::log( $e, 'phar' );
				\IPS\Output::i()->error( 'application_notvalid', '1C133/9', 403, '' );
			}
			catch( \UnexpectedValueException $e )
			{
				\IPS\Output::i()->error( 'application_notvalid', '1C133/K', 403, '' );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do={$_type}&appKey={$appDirectory}" )->csrf(), \IPS\Member::loggedIn()->language()->addToStack('installing_application') );
		}

		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Recursively check and adjust CHMOD permissions after uploading an application
	 *
	 * @param	string	$directory	Directory to check
	 * @return	void
	 * @see		Ticket 956849
	 */
	public static function _checkChmod( $directory )
	{
		if ( !is_dir( $directory ) )
		{
			throw new \UnexpectedValueException;
		}
		
		$it = new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS );
		foreach( new \RecursiveIteratorIterator( $it ) AS $f )
		{
			if ( $f->isDir() )
			{
				@chmod( $f->getPathname(), \IPS\IPS_FOLDER_PERMISSION );
			}
			else
			{
				/* If this is a .php file in the /interface/ folder it will be called via web directly. We cannot set permissions too high though or it won't execute in many environments */
				@chmod( $f->getPathname(), ( mb_strpos( $f->getPathname(), '/interface/' ) !== FALSE AND mb_strtolower( $f->getExtension() ) == 'php' ) ? \IPS\FILE_PERMISSION_NO_WRITE : \IPS\IPS_FILE_PERMISSION );
			}
		}
	}
		
	/**
	 * Import JS from /dev folders and compile into file objects
	 *
	 * @return	void
	 */
	public function compilejs()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=applications&controller=applications&do=compilejs&appKey=' . \IPS\Request::i()->appKey )->csrf(),
			function( $data )
			{
				/* Is this the first cycle? */
				if ( !\is_array( $data ) )
				{
					/* Start importing */
					$data = array( 'toDo' => array( 'import', 'compile' ) );
						
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
	
				/* Grab something to build */
				if ( \count( $data['toDo'] ) )
				{
					reset( $data['toDo'] );
					$command = array_shift( $data['toDo'] );
					
					switch( $command )
					{
						case 'import':
							$xml = \IPS\Output\Javascript::createXml( \IPS\Request::i()->appKey );
							
							/* Write it */
							if ( is_writable( \IPS\ROOT_PATH . '/applications/' . \IPS\Request::i()->appKey . '/data' ) )
							{
								\file_put_contents( \IPS\ROOT_PATH . '/applications/' . \IPS\Request::i()->appKey . '/data/javascript.xml', $xml->outputMemory() );
							}
						break;
						case 'compile':
							\IPS\Output\Javascript::compile( \IPS\Request::i()->appKey );
							
							/* Compile global JS after so map is written and correct */
							if ( \IPS\Request::i()->appKey == 'core' )
							{
								\IPS\Output\Javascript::compile('global');
							}
							
						break;
					}
	
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
				else
				{
					/* All Done */
					return null;
				}
			},
			function()
			{
				/* Finished */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'completed' );
			}
		);
	}
	
	/**
	 * Submit application details to IPS backend
	 *
	 * @return void
	 */
	public function submit()
	{
		\IPS\Session::i()->csrfCheck();
		
		$url = \IPS\Http\Url::internal('app=core&module=applications&controller=applications&do=submit')->csrf();
		\IPS\Output::i()->output = (string) new \IPS\Helpers\MultipleRedirect( $url, function( $data )
		{
			if ( !isset( $data['done'] ) )
			{
				$data['done'] = array();
			}
			
			foreach ( \IPS\IPS::$ipsApps as $app )
			{
				if ( \in_array( $app, $data['done'] ) )
				{
					continue;
				}
				
				try
				{
					$version = \IPS\Application::getAvailableVersion( $app );
					$info = json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/' . $app . '/setup/upg_' . $version . '/data.json' ), TRUE );
					$info['built'] = time();
					
					$response = \IPS\Http\Url::ips('upgrade')->request()->login( 'dev', \IPS\IPS_PASSWORD )->post( array(
						'version'	=> $version,
						'app'		=> $app,
						'type'		=> 'info',
						'data'		=> json_encode( $info ),
						'alpha'		=> (int) \IPS\IPS_ALPHA_BUILD,
					) );
					if ( $response->httpResponseCode != 200 )
					{
						\IPS\Output::i()->error( (string) $app . $response, '2C133/X', 403, '' );
					}
					
					foreach ( $info['steps'] as $k => $v )
					{
						if ( $v and !\in_array( $k, array( 'customOptions', 'customRoutines' ) ) )
						{
							$response = \IPS\Http\Url::ips('upgrade')->request()->login( 'dev', \IPS\IPS_PASSWORD )->post( array(
								'version'	=> $version,
								'app'		=> $app,
								'type'		=> $k,
								'alpha'		=> (int) \IPS\IPS_ALPHA_BUILD,
								'data'		=> json_encode( json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/' . $app . '/setup/upg_' . $version . '/' . $k . '.json' ) ) )
							) );
							if ( $response->httpResponseCode != 200 )
							{
								\IPS\Output::i()->error( (string) $app .  $response, '2C133/Z', 403, '' );
							}
						}
					}
					
					$data['done'][] = $app;
					
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->error( $e->getMessage(), '2C133/Y', 500, '' );
				}
			}
			
			return NULL;
		},
		function()
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications' ), 'application_now_built' );
		} );
	}

	/**
	 * Get application details
	 *
	 * @param	\IPS\Node\Model	$application	Application node
	 * @return	string
	 */
	protected function _showDetails( $application )
	{
		try
		{
			$history = \IPS\Db::i()->select( 'upgrade_date', 'core_upgrade_history', array( 'upgrade_app=?', $application->directory ), 'upgrade_version_id DESC', array( 0, 1 ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			$history = null;
		}
		
		return \IPS\Theme::i()->getTemplate( 'applications' )->details( $application, $history );
	}

	/**
	 * Show the application upgrade history
	 *
	 * @param	\IPS\Node\Model	$application	Application node
	 * @return	string
	 */
	protected function _showUpgrades( $application )
	{
		$list		= array();
		$upgrades = \IPS\Db::i()->select( '*', 'core_upgrade_history', array( 'upgrade_app=?', $application->directory ), 'upgrade_version_id DESC' );
		foreach(  $upgrades as $version )
		{
			$list[ (string) \IPS\DateTime::ts( $version['upgrade_date'] ) ]	= \IPS\Member::loggedIn()->language()->addToStack('app_version_string', FALSE, array( 'sprintf' => array( $version['upgrade_version_human'], $version['upgrade_version_id'] ) ) );
		}

		return ( \count( $upgrades ) ) ? \IPS\Theme::i()->getTemplate( 'global' )->definitionTable( $list ) : \IPS\Theme::i()->getTemplate( 'global' )->paddedBlock( \IPS\Member::loggedIn()->language()->addToStack('app_no_upgrade_history') );
	}
	
	/**
	 * Show the application hooks
	 *
	 * @param	\IPS\Application	$application	Application
	 * @return	string
	 */
	protected function _showHooks( $application )
	{
		$table = new \IPS\Helpers\Table\Db( 'core_hooks', \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=details&id={$application->directory}&tab=hooks" ), array( 'app=?', $application->directory ) );
		$table->include = array( 'filename', 'class' );
		$table->langPrefix = 'plugin_hook_';
		if ( !$table->sortBy )
		{
			$table->sortBy = 'class';
			$table->sortDirection = 'asc';
		}
		$table->parsers = array(
			'filename'	=> function( $val, $row )
			{
				return $val . '.php';
			}
		);
		
		return $table;
	}
}
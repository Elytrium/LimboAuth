<?php
/**
 * @brief		Plugins
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Jul 2013
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Plugins
 */
class _plugins extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Plugin';

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
		\IPS\Dispatcher::i()->checkAcpPermission( 'plugins_view' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Settings::i()->disable_all_plugins )
		{
			\IPS\Output::i()->sidebar['actions'][] = array(
				'icon'	=> 'undo',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins&do=reenableAll' )->csrf(),
				'title'	=> 'plugins_reenable_all',
			);
		}
		else
		{
			\IPS\Output::i()->sidebar['actions'][] = array(
				'icon'	=> 'times',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins&do=disableAll' )->csrf(),
				'title'	=> 'plugins_disable_all',
			);
		}
		
		if ( !\IPS\Request::i()->isAjax() AND \IPS\IPS::canManageResources() )
		{
			if ( \IPS\IPS::checkThirdParty() )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb( 'plugins_blurb' );
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb('plugins_blurb_no_custom');
			}
		}
		
		parent::manage();
	}
	
	/**
	 * Disable All
	 *
	 * @return	void
	 */
	protected function disableAll()
	{
		\IPS\Session::i()->csrfCheck();
		
		$disabledPlugins = array();
		
		foreach ( \IPS\Plugin::plugins() as $plugin )
		{
			if ( $plugin->enabled )
			{
				$plugin->enabled = FALSE;
				$plugin->save();
				
				$disabledPlugins[] = $plugin->id;
			}
		}

		\IPS\Settings::i()->changeValues( array( 'disable_all_plugins' => implode( ',', $disabledPlugins ) ) );
		
		\IPS\Session::i()->log( 'acplogs__all_plugins_disabled' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins' ) );
	}

	
	/**
	 * Re-enable All
	 *
	 * @return	void
	 */
	protected function reenableAll()
	{
		\IPS\Session::i()->csrfCheck();
		
		foreach ( explode( ',', \IPS\Settings::i()->disable_all_plugins ) as $plugin )
		{			
			try
			{
				$plugin = \IPS\Plugin::load( $plugin );
				$plugin->enabled = TRUE;
				$plugin->save();
			}
			catch ( \Exception $e ) {}
		}

		\IPS\Settings::i()->changeValues( array( 'disable_all_plugins' => '' ) );
		
		\IPS\Session::i()->log( 'acplogs__all_plugins_reenabled' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins' ) );
	}

	/**
	 * Toggle Enabled/Disable
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C145/G', 403, '' );
		}
		
		/* Remove plugin.js so it can be rebuilt with only active plugins */
		\IPS\Output\Javascript::deleteCompiled( 'core', 'plugins', 'plugins.js' );
		
		return parent::enableToggle();
	}

	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		return \IPS\Theme::i()->getTemplate( 'applications' )->pluginVersionData( $node );
	}

	/**
	 * Install Form
	 *
	 * @return	void
	 */
	public function install()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'plugins_install' );
		
		if ( \IPS\DEMO_MODE )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '1C145/O', 403, '' );
		}
		
		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '1C145/1', 403, '' );
		}
		if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/plugins/" ) ) // necessary as we write the hooks.php file here
		{
			\IPS\Output::i()->error( 'plugin_dir_not_write', '4C145/2', 403, '' );
		}

		if ( !\IPS\CIC2 AND file_exists( \IPS\ROOT_PATH . "/plugins/hooks.php") AND !is_writable( \IPS\ROOT_PATH . "/plugins/hooks.php" ) )
		{
			\IPS\Output::i()->error( 'plugin_file_not_write', '4C145/N', 403, '' );
		}
		
		if ( \IPS\CIC AND ! \IPS\IPS::checkThirdParty() )
		{
			\IPS\Output::i()->error( 'cic_3rdparty_unavailable', '2C145/S', 403, '' );
		}

		/* Build form */
		$form = new \IPS\Helpers\Form( NULL, 'install' );
		if ( isset( \IPS\Request::i()->id ) )
		{
			$form->hiddenValues['id'] = \IPS\Request::i()->id;

			if( \IPS\Plugin::load( \IPS\Request::i()->id )->marketplace_id )
			{
				\IPS\Output::i()->error( 'app_upload_marketplace_only', '2C145/P', 403, '' );
			}
		}
		$form->addMessage('plugins_manual_install_warning');
		$form->add( new \IPS\Helpers\Form\Upload( 'plugin_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ) ) );
		$activeTabContents = $form;
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Already installed? */
			$xml = \IPS\Xml\XMLReader::safeOpen( $values['plugin_upload'] );
			if ( !@$xml->read() )
			{
				\IPS\Output::i()->error( 'xml_upload_invalid', '2C145/D', 403, '' );
			}
			
			if ( !isset( \IPS\Request::i()->id ) )
			{
				try
				{
					$id = \IPS\Db::i()->select( 'plugin_id', 'core_plugins', array( 'plugin_name=? AND plugin_author=?', $xml->getAttribute('name'), $xml->getAttribute('author') ) )->first();
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'plugin_already_installed', FALSE, array( 'sprintf' => array( (string) \IPS\Http\Url::internal("app=core&module=applications&controller=plugins&do=install&id={$id}") ) ) ), '1C145/F', 403, '' );
				}
				catch ( \UnderflowException $e ) { }
			}

			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['plugin_upload'], $tempFile );
											
			/* Initate a redirector */
			$url = \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins&do=doInstall' )->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ) ) )->csrf();
			if ( isset( \IPS\Request::i()->id ) )
			{
				$url = $url->setQueryString( 'id', \IPS\Request::i()->id );
			}
			\IPS\Output::i()->redirect( $url );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Install
	 *
	 * @return	void
	 */
	public function doInstall()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'plugins_install' );
		\IPS\Session::i()->csrfCheck();
		
		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3C145/3', 500, '' );
		}

		$url = \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins&do=doInstall' )->setQueryString( array( 'file' => \IPS\Request::i()->file, 'key' => \IPS\Request::i()->key, 'id' => \IPS\Request::i()->id ) )->csrf();
		if ( isset( \IPS\Request::i()->marketplace ) )
		{
			$url = $url->setQueryString( 'marketplace', \IPS\Request::i()->marketplace );
		}
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			$url,
			function( $data )
			{
				/* Open XML file */
				$xml = \IPS\Xml\XMLReader::safeOpen( \IPS\Request::i()->file );
				$new = FALSE;
				
				$xml->read();
				$version = $xml->getAttribute('version');
				
				/* Initial insert */
				if ( !\is_array( $data ) )
				{
					if( !$xml->getAttribute('name') )
					{
						@unlink( \IPS\Request::i()->file );

						\IPS\Output::i()->error( 'xml_upload_invalid', '2C145/E', 403, '' );
					}

					if ( isset( \IPS\Request::i()->id ) )
					{
						try
						{
							$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );

							/* Disable the plugin to prevent errors if core classes are extended */
							$plugin->enabled = FALSE;
							$plugin->save();

							/* If we're upgrading, remove current HTML, CSS, etc. We'll insert again in a moment */
							\IPS\Theme::removeTemplates( 'core', 'global', 'plugins', $plugin->id );
							\IPS\Theme::removeCss( 'core', 'front', 'custom', $plugin->id );
							\IPS\Theme::removeResources( 'core', 'global', 'plugins', $plugin->id );

							foreach( \IPS\Db::i()->select( '*', 'core_javascript', array( 'javascript_plugin=?', $plugin->id ) ) as $javascript )
							{
								$jsObject = \IPS\Output\Javascript::constructFromData( $javascript );
								$jsObject->delete();
							}
						}
						catch ( \OutOfRangeException $e )
						{
							$plugin = new \IPS\Plugin;
							$new = TRUE;
						}
					}
					else
					{
						$plugin = new \IPS\Plugin;
						$new = TRUE;
					}

					$currentVersionId = $plugin->version_long;

					$plugin->name = $xml->getAttribute('name');
					$plugin->update_check = $xml->getAttribute('update_check');
					$plugin->author = $xml->getAttribute('author');
					$plugin->website = $xml->getAttribute('website');

					if ( !$plugin->location )
					{
						$directory = \mb_substr(  \mb_strtolower( preg_replace( '#[^a-zA-Z0-9_]#', '', $plugin->name ) ), 0, 80 );
						$plugin->location = file_exists( \IPS\SITE_FILES_PATH . "/plugins/" . $directory ) ? 'p' . mb_substr( md5( mt_rand() ), 0, 10 ) : $directory;
					}

					$plugin->version_long = $xml->getAttribute('version_long');
					$plugin->version_human = $xml->getAttribute('version_human');
					$plugin->save();
					
					if ( !\IPS\CIC2 )
					{
						if ( !file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}" ) )
						{
							\mkdir( \IPS\ROOT_PATH . "/plugins/{$plugin->location}" );
							chmod( \IPS\ROOT_PATH . "/plugins/{$plugin->location}", \IPS\IPS_FOLDER_PERMISSION );
	
							/* IN_DEV directories */
							if( \IPS\IN_DEV )
							{
								foreach ( array( 'hooks', 'dev', 'dev/html', 'dev/css', 'dev/js', 'dev/resources', 'dev/setup' ) as $k )
								{
									@\mkdir( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$k}" );
									\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$k}/index.html", '' );
									@\chmod( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$k}", \IPS\IPS_FOLDER_PERMISSION );
								}
							}
						}

						// nulled by ipbmafia.ru
						\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$plugin->name}_{$plugin->version_human}.xml", \file_get_contents( \IPS\Request::i()->file ) );
						@chmod( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$plugin->name}_{$plugin->version_human}.xml", \IPS\IPS_FILE_PERMISSION );
	
						/* Check to make sure that worked */
						if( !\is_dir( \IPS\ROOT_PATH . "/plugins/{$plugin->location}" ) )
						{
							\IPS\Output::i()->error( 'plugin_mkdir_perm', '4C145/H', 403, '' );
						}
					}
					
					if ( \IPS\CIC2 )
					{
						unset( \IPS\Data\Store::i()->syncCompleted );
						\IPS\Cicloud\file( 'index.html', '', "plugins/{$plugin->location}" );

						/* Check file is ready */
						$i = 0;
						do
						{
							if ( ( ( isset( \IPS\Data\Store::i()->syncCompleted ) AND \IPS\Data\Store::i()->syncCompleted ) OR $i >= 30 )
								AND \file_exists( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/index.html" ) ) # 30 x 0.25 seconds
							{
								/* We need to wait for the backend to process the file */
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
						\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/index.html", '' );
					}

					/* Check to make sure that worked */
					if( !\file_exists( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/index.html" ) )
					{
						\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'plugin_fpc_perm', FALSE, array( 'sprintf' => array( $plugin->location ) ) ), '4C145/J', 403, '' );
					}
					
					return array( array(
						'id'               => $plugin->id,
						'currentVersionId' => $currentVersionId,
						'storeKeys'        => array(),
						'setUpClasses'     => array(),
						'step'             => 0,
						'upgradeData'      => NULL,
						'done'             => array(),
						'isNew'            => $new
					), \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
				
				/* Load plugin */
				$plugin = \IPS\Plugin::load( $data['id'] );
				
				unset( \IPS\Data\Store::i()->syncCompleted );
				
				/* Skip to whatever we're doing */
				$xml->read();
				while ( TRUE )
				{
					if ( !\in_array( $xml->name, $data['done'] ) )
					{
						/* What are we doing? */
						$step = $xml->name;
						switch ( $step )
						{
							case 'plugin':
								break 2;
							
							/* Hooks */
							case 'hooks':
								
								/* Make the directory, or if we're upgrading, empty it */
								if ( !file_exists( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/hooks" ) )
								{
									if ( \IPS\CIC2 )
									{
										\IPS\Cicloud\file( 'index.html', '', "/plugins/{$plugin->location}/hooks" );
									}
									else
									{
										\mkdir( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/hooks" );
										chmod( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/hooks", \IPS\IPS_FOLDER_PERMISSION );
										\file_put_contents( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/hooks/index.html", '' );
	
										if( \IPS\IN_DEV )
										{
											\mkdir( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/dev" );
											chmod( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/dev", \IPS\IPS_FOLDER_PERMISSION );
										}
									}
								}
								else
								{
									foreach ( \IPS\Db::i()->select( 'class', 'core_hooks', array( 'plugin=? AND type=?', $plugin->id, 'S' ) ) as $class )
									{
										$data['recompileTemplates'][ $class ] = $class;
									}
									\IPS\Db::i()->delete( 'core_hooks', array( 'plugin=?', $plugin->id ) );
									foreach ( new \DirectoryIterator( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/hooks" ) as $file )
									{
										if ( !$file->isDot() )
										{
											if ( \IPS\CIC2 )
											{
												\IPS\Cicloud\fileDelete( str_replace( \IPS\SITE_FILES_PATH, '', $file->getPathname() ) );
											}
											else
											{
												unlink( $file->getPathname() );
											}
										}
									}
									\IPS\Plugin\Hook::writeDataFile();
								}
								
								/* Loop hooks */
								while ( $xml->read() and $xml->name == 'hook' )
								{
									/* Make up a filename */
								  	$filename = $xml->getAttribute( 'filename' ) ?: md5( mt_rand() );
								  	
								  	/* Insert into DB */
								  	$insertId = \IPS\Db::i()->insert( 'core_hooks', array( 'plugin' => $plugin->id, 'type' => $xml->getAttribute('type'), 'class' => $xml->getAttribute('class'), 'filename' => $filename ) );
								  	
								   	/* Write contents */
								   	$contents = preg_replace( '/class hook(\d+?) extends _HOOK_CLASS_/', "class hook{$insertId} extends _HOOK_CLASS_", $xml->readString() );
								   	
								   	if ( \IPS\CIC2 )
								   	{
									   	\IPS\Cicloud\file( "{$filename}.php", $contents, "plugins/{$plugin->location}/hooks" );
								   	}
								   	else
								   	{
									  	\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/hooks/{$filename}.php", $contents );
	
										/* Clear zend opcache if enabled */
										if ( \function_exists( 'opcache_invalidate' ) )
										{
											@opcache_invalidate( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/hooks/{$filename}.php" );
										}
									}
								  	
								  	/* If that was a skin hook, trash our compiled version of that template */
								  	if ( $xml->getAttribute('type') == 'S' )
								  	{
								  		$class = $xml->getAttribute('class');
								  		$data['recompileTemplates'][ $class ] = $class;
								  	}
								  								
									/* Move onto next */
									$xml->read();
									$xml->next();
									
								}
								$plugin->buildHooks();
								break;
								
							/* Settings */
							case 'settings':
								$settings = array();
								
								$inserts = array();
								while ( $xml->read() and $xml->name == 'setting' )
								{
									$xml->read();
									$key = $xml->readString();
									$xml->next();
									$value = $xml->readString();
									
									if( isset( \IPS\Settings::i()->$key ) )
									{
										\IPS\Db::i()->update( 'core_sys_conf_settings', array(
											'conf_default'	=> $value,
											'conf_plugin'	=> $plugin->id
										), array( 'conf_key=?', $key ) );
									}
									else
									{
										$inserts[] = array(
											'conf_key'		=> $key,
											'conf_value'	=> $value,
											'conf_default'	=> $value,
											'conf_plugin'	=> $plugin->id
										);
									}
									$settings[] = array( 'key' => $key, 'default' => $value);
									$xml->next();
								}
								
								if( \count( $inserts ) )
								{
									\IPS\Db::i()->insert( 'core_sys_conf_settings', $inserts , TRUE );
								}

								if( \IPS\IN_DEV )
								{
									try
									{
										\IPS\Application::writeJson( \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/dev/settings.json', $settings );
									}
									catch ( \RuntimeException $e )
									{
										throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_plugin_not_writable') );
									}
								}

								\IPS\Settings::i()->clearCache();
								break;
								
							/* Settings code */
							case 'settingsCode':
								if ( \IPS\CIC2 )
								{
									\IPS\Cicloud\file( 'settings.php', $xml->readString(), "plugins/{$plugin->location}" );
								}
								else
								{
									\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/settings.php", $xml->readString() );
	
									/* Clear zend opcache if enabled */
									if ( \function_exists( 'opcache_invalidate' ) )
									{
										@opcache_invalidate( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/settings.php" );
									}
								}
								break;
								
							/* Tasks */
							case 'tasks':
								
								/* Make the directory */
								if ( !file_exists( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/tasks" ) )
								{
									if ( \IPS\CIC2 )
									{
										\IPS\Cicloud\file( 'index.html', '', "plugins/{$plugin->location}/tasks" );
									}
									else
									{
										\mkdir( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/tasks" );
										chmod( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/tasks", \IPS\IPS_FOLDER_PERMISSION );
									}
								}
								
								/* Loop tasks */
								$tasks = array();
								while ( $xml->read() and $xml->name == 'task' )
								{
									$key = $xml->getAttribute('key');
									
								  	/* Insert into DB */
								  	try
								  	{
									  	$task = \IPS\Task::load( $key, 'key', array( 'plugin=?', $plugin->id ) );
								  	}
								  	catch ( \OutOfRangeException $e )
								  	{
									  	$task = new \IPS\Task;
									}
								  	$task->plugin = $plugin->id;
								  	$task->key = $key;
								  	$task->frequency = $xml->getAttribute('frequency');
								  	$task->save();
								  									  	
								  	/* Write contents */
								  	if ( \IPS\CIC2 )
								  	{
									  	\IPS\Cicloud\file( "{$key}.php", $xml->readString(), "plugins/{$plugin->location}/tasks" );
									}
									else
									{
									  	\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/tasks/{$key}.php", $xml->readString() );
	
										/* Clear zend opcache if enabled */
										if ( \function_exists( 'opcache_invalidate' ) )
										{
											@opcache_invalidate( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/tasks/{$key}.php" );
										}
									}

									$tasks[$key] = $xml->getAttribute('frequency');
							
									/* Move onto next */
									$xml->read();
									$xml->next();
								}
								if( \IPS\IN_DEV )
								{
									try
									{
										\IPS\Application::writeJson( \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/dev/tasks.json', $tasks );
									}
									catch ( \RuntimeException $e )
									{
										throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_plugin_not_writable') );
									}
								}

								
								break;
								
							/* Files */
							case 'htmlFiles':
							case 'cssFiles':
							case 'jsFiles':
							case 'resourcesFiles':
								$class = ( \IPS\Theme::designersModeEnabled() ) ? '\IPS\Theme\Advanced\Theme' : '\IPS\Theme';
								
								while ( $xml->read() and \in_array( $xml->name, array( 'html', 'css', 'js', 'resources' ) ) )
								{
									switch ( $xml->name )
									{
										case 'html':
											$name = $xml->getAttribute('filename');
											$content = base64_decode( $xml->readString() );

											preg_match('/^<ips:template parameters="(.+?)?"([^>]+?)>(\r\n?|\n)/', $content, $matches );
											$output = preg_replace( '/^<ips:template parameters="(.+?)?"([^>]+?)>(\r\n?|\n)/', '', $content );

											$class::addTemplate( array(
												'app'		=> 'core',
												'location'	=> 'global',
												'group'		=> 'plugins',
												'name'		=> mb_substr( $name, 0, -6 ),
												'variables'	=> $matches[1],
												'content'	=> $output,
												'plugin'	=> $plugin->id,
												'_default_template' => TRUE,
												'rawContent' => $content,
											), TRUE );
											
											break;
											
										case 'css':
											$class::addCss( array(
												'app'		=> 'core',
												'location'	=> 'front',
												'path'		=> 'custom',
												'name'		=> $xml->getAttribute('filename'),
												'content'	=> base64_decode( $xml->readString() ),
												'plugin'	=> $plugin->id
											), TRUE );
																						
											break;
											
										case 'js':
											$name = $xml->getAttribute('filename');

											$js = new \IPS\Output\Javascript;
											$js->plugin  = $plugin->id;
											$js->name    = $name;
											$js->content = base64_decode( $xml->readString() );
											$js->version = $plugin->version_long;
											$js->save();

											if( \IPS\IN_DEV )
											{
												\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/js/{$name}", $js->content );
												@chmod( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/js/{$name}", \IPS\IPS_FILE_PERMISSION );
											}
											
											break;
											
										case 'resources':
											$class::addResource( array(
												'app'		=> 'core',
												'location'	=> 'global',
												'path'		=> '/plugins/',
												'name'		=> $xml->getAttribute('filename'),
												'content'	=> base64_decode( $xml->readString() ),
												'plugin'	=> $plugin->id
											) );
											break;
									}

									$xml->read();
									$xml->next();
								}
							
								break;
								
							/* Lang */
							case 'lang':
								/* Fetch existing language keys */
								$existingLanguageKeys = iterator_to_array( \IPS\Db::i()->select( 'word_key', 'core_sys_lang_words', array( 'word_plugin=? and lang_id=?', $plugin->id, \IPS\Lang::defaultLanguage() ) ) );
								$keysToDelete         = $existingLanguageKeys;
								$inserts = array();
								$batchSize = 25;
								$i = 0;

								while ( $xml->read() and $xml->name == 'word' )
								{
									$key = $xml->getAttribute('key');
									$js = $xml->getAttribute('js');
									$value = $xml->readString();
									
									$i++;


									
									foreach ( \IPS\Lang::languages() as $lang )
									{
										if ( \count( $existingLanguageKeys ) and \in_array( $key, $existingLanguageKeys ) )
										{
											/* Exists so do not delete */
											$keysToDelete = array_diff( $keysToDelete, array( $key ) );
											
											\IPS\Db::i()->update( 'core_sys_lang_words', array(
													'word_default'			=> $value,
													'word_default_version'	=> $plugin->version_long,
													'word_js'				=> $js
												),
												array( 'lang_id=? and word_plugin=? and word_key=?', $lang->id, $plugin->id, $key )
											);
										}
										else
										{
											$inserts[] = array(
												'lang_id'				=> $lang->id,
												'word_app'				=> NULL,
												'word_plugin'			=> $plugin->id,
												'word_key'				=> $key,
												'word_default'			=> $value,
												'word_custom'			=> NULL,
												'word_default_version'	=> $plugin->version_long,
												'word_custom_version'	=> NULL,
												'word_js'				=> $js,
												'word_export'			=> 1,
											);
										}
									}
									
									if ( $i % $batchSize === 0 )
									{
										if ( \count( $inserts ) )
										{
											\IPS\Db::i()->replace( 'core_sys_lang_words', $inserts );
											$inserts = array();
										}
									}
									
									if ( !$xml->isEmptyElement )
									{
										$xml->read();
										$xml->next();
									}
								}
								
								/* Anything left that doesn't quite match up with a batch size? */
								if( \count( $inserts ) )
								{
									\IPS\Db::i()->replace( 'core_sys_lang_words', $inserts );
								}
								
								if ( \count( $keysToDelete ) )
								{
									\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_plugin=? AND ' . \IPS\Db::i()->in( 'word_key', $keysToDelete ), $plugin->id ) );
								}

								$pluginStrings =  iterator_to_array( \IPS\Db::i()->select( 'word_key, word_default', 'core_sys_lang_words', array( 'word_plugin=? and lang_id=? and word_js=?', $plugin->id, \IPS\Lang::defaultLanguage(), 0 ) )->setKeyField('word_key')->setValueField('word_default' ) );

								$pluginJsStrings =  iterator_to_array( \IPS\Db::i()->select( 'word_key, word_default', 'core_sys_lang_words', array( 'word_plugin=? and lang_id=? and word_js=?', $plugin->id, \IPS\Lang::defaultLanguage(), 1 ) )->setKeyField('word_key')->setValueField('word_default' ) );
								
								if ( \IPS\IN_DEV )
								{
									\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/lang.php", "<?php\n\n\$lang = " . var_export( $pluginStrings, TRUE ) . ";" );
									\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php", "<?php\n\n\$lang = " . var_export( $pluginJsStrings, TRUE ) . ";" );
								}

								break;
							
							/* Versions */
							case 'versions':
								$versions = array();
								while ( $xml->read() and $xml->name == 'version' )
								{
									$class = $xml->readString();

									if ( $class AND $xml->getAttribute('long') )
									{
										if ( $xml->getAttribute('long') == 10000 AND $data['isNew'] )
										{
											/* Installing, so use install file which is bundled with <version long="10000"> */
											$key   = 'plugin_' . $plugin->id . '_setup_install_class';
											\IPS\Data\Store::i()->$key = $class;

											$data['storeKeys'][]    = $key;
											$data['setUpClasses']['install'] = 'install';
										}
										else if ( $data['currentVersionId'] < $xml->getAttribute('long') )
										{
											$key = 'plugin_' . $plugin->id . '_setup_' . $xml->getAttribute('long') . '_class';
											\IPS\Data\Store::i()->$key = $class;

											$data['storeKeys'][]    = $key;
											$data['setUpClasses'][ $xml->getAttribute('long') ] = $xml->getAttribute('long');
										}
									}

									$versions[$xml->getAttribute('long')] = $xml->getAttribute('human');


									/* Create the upgrade file */
									if ( \IPS\CIC2 )
									{
										\IPS\Cicloud\file( "{$xml->getAttribute('long')}.php", $class, "plugins/{$plugin->location}/dev/setup" );
									}
									else
									{
										\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/{$xml->getAttribute('long')}.php", $class );
									}

									$xml->read();
									$xml->next();
								}

								if( \IPS\IN_DEV )
								{
									try
									{
										\IPS\Application::writeJson( \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/dev/versions.json', $versions );
									}
									catch ( \RuntimeException $e )
									{
										throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack( 'dev_plugin_not_writable' ) );
									}
								}
								break;

							/* Uninstall Code */
							case 'uninstall':
								/* Write contents */
								if ( \IPS\CIC2 )
								{
									\IPS\Cicloud\file( 'uninstall.php', $xml->readString(), "plugins/{$plugin->location}" );
								}
								else
								{
									\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/uninstall.php", $xml->readString() );
	
									/* Clear zend opcache if enabled */
									if ( \function_exists( 'opcache_invalidate' ) )
									{
										@opcache_invalidate( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/uninstall.php" );
									}
								}
								break;

							/* Sidebar Widgets */
							case 'widgets':
							
								/* Make the directory */
								if ( !file_exists( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/widgets" ) )
								{
									if ( \IPS\CIC2 )
									{
										\IPS\Cicloud\file( 'index.html', '', "plugins/{$plugin->location}/widgets" );
									}
									else
									{
										\mkdir( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/widgets" );
										chmod( \IPS\SITE_FILES_PATH . "/plugins/{$plugin->location}/widgets", \IPS\IPS_FOLDER_PERMISSION );
									}
								}
								/* Loop widgets */
								$widgets = array();
								while ( $xml->read() and $xml->name == 'widget' )
								{
									/** delete the widgets cache */
									$data['storeKeys'][] = 'widgets';

									$key = $xml->getAttribute('key');
										
									/* Insert into DB */
									try
									{
										$widget = \IPS\Db::i()->select( '*', 'core_widgets', array( '`key`=? and plugin=?', $key, $plugin->id ) )->first();
										
										\IPS\Db::i()->update( 'core_widgets', array(
											'plugin'	   => $plugin->id,
											'key'		   => $key,
											'class'		   => $xml->getAttribute('class'),
											'restrict'     => json_encode( explode( ",", $xml->getAttribute('restrict') ) ),
											'default_area' => $xml->getAttribute('default_area'),
											'allow_reuse'  => \intval( $xml->getAttribute('allow_reuse') ),
											'menu_style'   => $xml->getAttribute('menu_style'),
											'embeddable'   => \intval( $xml->getAttribute('embeddable') )
										), array( '`id`=?', $widget['id'] ) );
									}
									catch ( \UnderflowException $e )
									{
										$inserts[] = array(
											'plugin'	   => $plugin->id,
											'key'		   => $key,
											'class'		   => $xml->getAttribute('class'),
											'restrict'     => json_encode( explode( ",", $xml->getAttribute('restrict') ) ),
											'default_area' => $xml->getAttribute('default_area'),
											'allow_reuse'  => \intval( $xml->getAttribute('allow_reuse') ),
											'menu_style'   => $xml->getAttribute('menu_style'),
											'embeddable'   => \intval( $xml->getAttribute('embeddable') )
										);
										\IPS\Db::i()->insert( 'core_widgets', $inserts, TRUE );
									}


									/* Write contents */
									$contents = $xml->readString();
									$contents = str_replace( '<{ID}>', $plugin->id, $contents );
									$contents = str_replace( '<{LOCATION}>', $plugin->location, $contents );
									if ( \IPS\CIC2 )
									{
										\IPS\Cicloud\file( "{$key}.php", $contents, "plugins/{$plugin->location}/widgets" );
									}
									else
									{
										\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/widgets/{$key}.php", $contents );
	
										/* Clear zend opcache if enabled */
										if ( \function_exists( 'opcache_invalidate' ) )
										{
											@opcache_invalidate( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/widgets/{$key}.php" );
										}
									}

									$widgets[ $key ] = array(
										'class'    	   => $xml->getAttribute('class'),
										'restrict' 	   => explode( ",", $xml->getAttribute('restrict') ),
										'default_area' => $xml->getAttribute('default_area'),
										'allow_reuse'  => \intval( $xml->getAttribute('allow_reuse') ),
										'menu_style'   => $xml->getAttribute('menu_style'),
										'embeddable'   => \intval( $xml->getAttribute('embeddable') )
									);
										
									/* Move onto next */
									$xml->read();
									$xml->next();
								}

								if( \IPS\IN_DEV )
								{
									try
									{
										\IPS\Application::writeJson( \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/dev/widgets.json', $widgets );
									}
									catch ( \RuntimeException $e )
									{
										throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack( 'dev_plugin_not_writable' ) );
									}
								}
							
								break;
							case 'cmsTemplates':
								/* Get Templates and Insert */
								$_SESSION['cmsConflictKey'] = '';
								try
								{
									$result = \IPS\cms\Templates::importUserTemplateXml( NULL, base64_decode( $xml->readString() ) );
									if( $result instanceof \IPS\Http\Url\Internal )
									{
										$_SESSION['cmsConflictKey'] = $result->setQueryString( 'plugin', $plugin->location );
									}
								}
								catch( \Throwable $e )
								{
									\IPS\Log::log( $e, 'cms_template_plugin_install' );
								}

								/* Move onto next */
								$xml->read();
								$xml->next();

								break;
						}
						
						/* Move on */
						$data['done'][] = $step;
						return array( $data, \IPS\Member::loggedIn()->language()->addToStack('plugins_install_setup_done_step', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'plugin_step_' . $step ) ) ) ) );
					}
					else
					{
						$xml->next();
					}
				}

				/* Do upgrade classes */
				if ( \count( $data['setUpClasses'] ) )
				{
					\IPS\Log::debug( "Plugin setup: found " . \count( $data['setUpClasses'] ). " set up classes to run ", 'plugin_setup' );

					/* Grab class and run it, step by step */
					$versionToRun = current( $data['setUpClasses'] );
					$key          = 'plugin_' . $plugin->id . '_setup_' . $versionToRun . '_class';
					$class       = ( $versionToRun === 'install' ) ? 'ips_plugins_setup_install' : 'ips_plugins_setup_upg_' . $versionToRun;

					\IPS\Log::debug( "Plugin setup: looking for class key " . $key, 'plugin_setup' );

					if ( isset( \IPS\Data\Store::i()->$key ) )
					{
						\IPS\Log::debug( "Plugin setup: found class key " . $key . " class " . $class, 'plugin_setup' );
						\IPS\Log::debug( \IPS\Data\Store::i()->$key, 'plugin_setup' );

						/* As this is to be evaled, make sure PHP tags aren't there */
						\IPS\Data\Store::i()->$key = preg_replace( '/^<' . '?php(\n)/', '', \IPS\Data\Store::i()->$key );

						eval( \IPS\Data\Store::i()->$key );

						\IPS\Log::debug( "Plugin setup: looking for class " . $class, 'plugin_setup' );

						if ( class_exists( $class ) )
						{
							$upgrader = new $class();

							$stepToRun = $data['step'] + 1;
							$method    = 'step' . $stepToRun;
							$more      = FALSE;

							\IPS\Log::debug( "Plugin setup: looking for class key " . $key . ", " . $method, 'plugin_setup' );

							if ( method_exists( $upgrader, $method ) )
							{
								\IPS\Log::debug( "Plugin setup: Running " . $key . ", " . $method, 'plugin_setup' );

								\IPS\Request::i()->extra = $data['upgradeData'];
								$result = $upgrader->$method();

								if ( $result === TRUE )
								{
									$method = 'step' . ( $stepToRun + 1 );

									if ( method_exists( $upgrader, $method ) )
									{
										\IPS\Log::debug( "Plugin setup: Running " . $key . ", next method " . $method . " found", 'plugin_setup' );

										/* Hit it on the next redirect */
										$data['step']++;
										$data['upgradeData'] = NULL;
										$more = TRUE;
									}
								}
								/* If the result is an array with 'html' key, we show that */
								else if( \is_array( $result ) AND isset( $result['html'] ) )
								{
									return $result['html'];
								}
								else if ( ! empty( $result ) )
								{
									$data['upgradeData'] = $result;
									$more = TRUE;
								}
							}

							/* Go for another hit on multiredirector */
							if ( $more )
							{
								return array( $data, \IPS\Member::loggedIn()->language()->addToStack('plugins_install_setup_method', FALSE, array( 'sprintf' => array( $versionToRun ) ) ) );
							}
						}
					}

					\IPS\Log::debug( "Plugin setup: Class " . $versionToRun . " completed", 'plugin_setup' );

					/* Done this class completely */
					$data['step'] = 0;
					unset( $data['setUpClasses'][ $versionToRun ] );

					\IPS\Log::debug( json_encode( $data ), 'plugin_setup' );

					/* Go for another hit on multiredirector */
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('plugins_install_setup_method', FALSE, array( 'sprintf' => array( $versionToRun ) ) ) );
				}

				\IPS\Log::debug( "Plugin setup: All set up classes run", 'plugin_setup' );

				/* All set up classes are done, so delete stored data so far */
				if ( \count( $data['storeKeys'] ) )
				{
					foreach( $data['storeKeys'] as $sk )
					{
						if ( isset( \IPS\Data\Store::i()->$sk ) )
						{
							unset( \IPS\Data\Store::i()->$sk );
						}
					}

					$data['storeKeys'] = array();
				}

				/* Update data file */
				\IPS\Plugin\Hook::writeDataFile();

				/* Recompile CSS */
				\IPS\Theme::deleteCompiledCss( 'core', 'front', 'custom' );
															
				/* Recompile templates */
				\IPS\Theme::deleteCompiledTemplate( 'core', 'global', 'plugins' );
				if ( isset( $data['recompileTemplates'] ) )
				{
					foreach ( $data['recompileTemplates'] as $k )
					{
						$exploded = explode( '_', $k );
						\IPS\Theme::deleteCompiledTemplate( $exploded[1], $exploded[2], $exploded[3] );
					}
				}

				/* Clear javascript map to rebuild automatically */
				unset( \IPS\Data\Store::i()->javascript_file_map, \IPS\Data\Store::i()->javascript_map );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();

				/* Log */
				\IPS\Session::i()->log( 'acplog__plugin_installed', array( $plugin->name => FALSE ) );

				/* Set Marketplace ID. */
				if( \IPS\Request::i()->marketplace )
				{
					$plugin->marketplace_id = (int) \IPS\Request::i()->marketplace;
				}

				/* Re-enable the plugin */
				$plugin->enabled = TRUE;
				$plugin->save();
				
				/* IPS Cloud Sync */
				\IPS\IPS::resyncIPSCloud('Installed plugin');
				
				/* Invalidate disk templates */
				\IPS\Theme::resetAllCacheKeys();
				
				/* All done */
				return NULL;
			},
			function()
			{
				@unlink( \IPS\Request::i()->file );

				/* Make sure the plugin is not php8 locked now */
				try
				{
					$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
					if ( $plugin->requires_manual_intervention )
					{
						$plugin->requires_manual_intervention = 0;
						$plugin->save();
					}
				}
				catch ( \OutOfRangeException $e ) {}

				/* If CMS Template install had conflicts, solve those now */
				if( !empty( $_SESSION['cmsConflictKey'] ) )
				{
					if( \IPS\Request::i()->marketplace )
					{
						$_SESSION['cmsConflictKey'] = $_SESSION['cmsConflictKey']->setQueryString( 'marketplace', \IPS\Request::i()->marketplace );
					}
					\IPS\Output::i()->redirect( $_SESSION['cmsConflictKey'] );
				}

				/* Redirect back to marketplace if it was installed from there. */
				if( \IPS\Request::i()->marketplace )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . \IPS\Request::i()->marketplace ), 'plugin_now_installed' );
				}

				/* And redirect back to the overview screen */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=applications&controller=plugins' ) );
			}
		);
	}
	
	/**
	 * Edit Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'plugins_edit' );
		
		/* Load */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/4', 404, '' );
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form( 'form' . \IPS\Request::i()->id );
		try
		{
			eval( file_get_contents( \IPS\SITE_FILES_PATH . '/plugins/' . $plugin->location . '/settings.php' ) );
		}
		catch ( \ParseError $e )
		{
			\IPS\Output::i()->error( 'plugin_parse_error', '3C145/L', 500, '', array(), $e->getMessage() );
		}
		
		/* Display */
		if ( $form->values() )
		{
			\IPS\Session::i()->log( 'acplog__plugin_settings', array( $plugin->name => FALSE ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins" ), 'saved' );
		}
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Developer Mode
	 *
	 * @return	void
	 */
	protected function developer()
	{
		if( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C145/C', 403, '' );
		}
	
		/* Load */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/4', 404, '' );
		}
		
		/* Get tab contents */
		$activeTab = \IPS\Request::i()->tab ?: 'hooks';
		$methodToCall		= '_manage' . mb_ucfirst( $activeTab );
		$activeTabContents	= $this->$methodToCall( $plugin );
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Work out tabs */
		$tabs = array();
		$tabs['info'] = 'plugin_information';
		$tabs['hooks'] = 'plugin_hooks';
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
			/* Add Download Button */
            if( !$plugin->marketplace_id )
            {
                \IPS\Output::i()->sidebar['actions'] = array(
                    'download' => array(
                        'icon' => 'download',
                        'title' => 'download',
                        'link' => \IPS\Http\Url::internal("app=core&module=applications&controller=plugins&do=download&id={$plugin->id}")->csrf(),
                    )
                );
            }
			
			\IPS\Output::i()->title		= $plugin->name;
			\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}" ) );
		}
	}
	
	/**
	 * Developer Mode: Plugin Information
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageInfo( $plugin )
	{
		$form = new \IPS\Helpers\Form;
		$plugin->form( $form );
		if ( $values = $form->values() )
		{
			$plugin->saveForm( $plugin->formatFormValues( $values ) );
			$plugin->save();
		}
		return $form;
	}
	
	/**
	 * Developer Mode: Hooks
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageHooks( $plugin )
	{
		return \IPS\Plugin\Hook::devTable(
			\IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=hooks" ),
			$plugin->id,
			\IPS\ROOT_PATH . "/plugins/{$plugin->location}/hooks"
		);
	}
	
	/**
	 * Edit Hook
	 *
	 * @csrfChecked	Uses \IPS\Plugin\Hook::load( \IPS\Request::i()->hook )->editForm() 7 Oct 2019
	 * @return	string
	 */
	protected function editHook()
	{
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=hooks" ), $plugin->_title );
			\IPS\Plugin\Hook::load( \IPS\Request::i()->hook )->editForm( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id=" . $plugin->id . "&tab=hooks" ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/5', 404, '' );
		}
	}
	
	/**
	 * Show Template Tree
	 *
	 * @return	void
	 */
	protected function templateTree()
	{
		$exploded = explode( '_', \IPS\Request::i()->class );
		$bits = \IPS\Theme::load( \IPS\Theme::defaultTheme() )->getRawTemplates( $exploded[1], $exploded[2], $exploded[3], \IPS\Theme::RETURN_ALL );
		
		$document = new \IPS\Xml\DOMDocument();
		$document->strictErrorChecking = FALSE;

		/* Get the template content */
		$code	= $bits[ $exploded[1] ][ $exploded[2] ][ $exploded[3] ][ \IPS\Request::i()->template ]['template_content'];

		/* We need to fix special wrapping html tags or dom document will mess with them */
		$code	= preg_replace( '/<(\/?)(html|head|body)(>|\s)/', '<$1x_$2_x$3', $code );

		/* Fix else statement - basic replacement */
		$code	= str_replace( "{{else}}", "<else>", $code );

		/* Fix if/foreach/for tags...htmlspecialchars the content in case there is a -> which will break things */
		$code	= preg_replace_callback( '/\{\{(if|foreach|for)\s+?(.+?)\}\}/i', function( $matches )
		{
			return '<' . $matches[1] . ' code="' . htmlspecialchars( $matches[2], ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) . '">';
		}, $code );

		/* Fix ending if/foreach/for tags */
		$code	= preg_replace( '/\{\{end(if|foreach|for)\}\}/i', '</$1>', $code );

		/* Fix regular template tags such as url to htmlspecialchars the content */
		$code	= preg_replace_callback( '/\{([a-z]+?=([\'"]).+?\\2 ?+)}/', function( $matches )
		{
			return htmlspecialchars( $matches[0], ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE );
		}, $code );

		/* Strip any raw replacement tags remaining */
		$code	= preg_replace( '/\{{(.+?)}}/', '', $code );
		
		/* Ensure attributes with variable data are encoded before the cheeky DOM parser gets to it */
		$code	= preg_replace_callback( '/=([\'"])(\{.+?\})([\'"])/', function( $matches )
		{
			return "=" . $matches[1] . htmlspecialchars( $matches[2], ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) . $matches[3];
		}, $code );
		
		/* Now fix <if/foreach/for tags that are embedded into attributes */
		while( preg_match( '/\<([^>]+?)(\<(\/*?)(if|foreach|for|else).*?\>([^>]*?)(\<\/\4\>))/ms', $code ) )
		{
			$code	= preg_replace_callback( '/\<([^>]+?)(\<(\/*?)(if|foreach|for|else).*?\>([^>]*?)(\<\/\4\>))/ms', function( $matches )
			{
				return str_replace( $matches[2], '', $matches[0] );
			}, $code );
		}

		/* Fix embedded single quotes */
		$code	= preg_replace_callback( '/=\'([^\']*?){.+?}([^\']*?)\'/', function( $matches )
		{
			return "=''";
		}, $code );
		
		/* Now load the HTML - doctype necessary sometimes */
		$document->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( '<ipscontent id="ipscontent">' . $code . '</ipscontent>' ) );
	
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'applications' )->themeHookEditorTreeRoot( $document->getElementById('ipscontent') );
	}
	
	/**
	 * Get the CSS selector for a node
	 *
	 * @param	\DOMNode	$node	The node
	 * @return	string
	 */
	public static function getSelector( \DOMNode $node )
	{
		$bits = array();
		while ( TRUE )
		{
			if ( $node->tagName == 'ipscontent' )
			{
				break;
			}
			elseif ( \in_array( $node->tagName, array( 'if', 'foreach', 'for', 'else' ) ) )
			{
				$node = $node->parentNode;
			}
			else
			{
				if ( $node->hasAttributes() )
				{
					if ( $node->attributes->getNamedItem('id') AND $node->attributes->getNamedItem('id')->nodeValue AND mb_strpos( $node->attributes->getNamedItem('id')->nodeValue, '$' ) === FALSE )
					{
						$bits[] = '#' . $node->attributes->getNamedItem('id')->nodeValue;
						break;
					}
					else
					{
						$bit = preg_replace( '/^(x_)?([a-z]+)(_x)?$/i', '$2', $node->nodeName );
						for ( $i = 0; $i < $node->attributes->length; ++$i )
						{
							if ( $node->attributes->item( $i )->nodeName === 'class' AND $node->attributes->item( $i )->nodeValue AND mb_strpos( $node->attributes->item( $i )->nodeValue, '$' ) === FALSE  AND mb_strpos( $node->attributes->item( $i )->nodeValue, '{' ) === FALSE )
							{
								foreach ( array_filter( explode( ' ', $node->attributes->item( $i )->nodeValue ) ) as $class )
								{
									$bit .= '.' . $class;
								}
							}
							elseif ( !\in_array( $node->attributes->item( $i )->nodeName, array( 'href', 'src', 'value', 'class', 'id' ) ) AND $node->attributes->item( $i )->nodeValue AND mb_strpos( $node->attributes->item( $i )->nodeValue, '$' ) === FALSE  AND mb_strpos( $node->attributes->item( $i )->nodeValue, '{' ) === FALSE )
							{
								if ( $node->attributes->item( $i )->nodeValue )
								{
									$bit .= "[{$node->attributes->item( $i )->nodeName}='{$node->attributes->item( $i )->nodeValue}']";
								}
								else
								{
									$bit .= "[{$node->attributes->item( $i )->nodeName}]";
								}
							}
						}
						$bits[] = $bit;
					}
				}
				else
				{
					$bits[] = preg_replace( '/^(x_)?([a-z]+)(_x)?$/i', '$2', $node->tagName );
				}
				
				$node = $node->parentNode;
			}
		}
		return implode( ' > ', array_reverse( $bits ) );
	}

	/**
	 * Manage Settings
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageSettings( $plugin )
	{
		$file = \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/settings.json";
		
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->langPrefix = 'dev_settings_';
		$matrix->columns = array(
			'key'		=> array( 'Text', NULL, TRUE ),
			'default'	=> array( 'Text' )
		);
		$matrix->rows = file_exists( $file ) ? json_decode( file_get_contents( $file ), TRUE ) : array();

		if ( $matrix->values() !== FALSE )
		{
			$values = $matrix->values();

			if ( !empty( $matrix->addedRows ) )
			{
				$insert = array();
				foreach ( $matrix->addedRows as $key )
				{
					$insert[] = array( 'conf_key' => $values[ $key ]['key'], 'conf_value' => $values[ $key ]['default'], 'conf_default' => $values[ $key ]['default'], 'conf_plugin' => $plugin->id );
				}

				\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert );
			}
			if ( !empty( $matrix->changedRows ) )
			{
				foreach ( $matrix->changedRows as $key )
				{
					\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_default' => $values[ $key ]['default'] ), array( 'conf_key=?', $values[ $key ]['key'] ) );
				}
			}
			if ( !empty( $matrix->removedRows ) )
			{
				$delete = array();
				foreach ( $matrix->removedRows as $key )
				{
					$delete[] = $matrix->rows[ $key ]['key'];
				}
				
				\IPS\Db::i()->delete( 'core_sys_conf_settings', \IPS\Db::i()->in( 'conf_key', $delete ) );
			}

			\IPS\Settings::i()->clearCache();

			\file_put_contents( $file, json_encode( array_filter( array_values( $values ), function ( $v )
			{
				return (bool) $v['key'];
			} ) ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=settings" ) );
		}
		
		return $matrix;
	}
	
	/**
	 * Manage Tasks
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageTasks( $plugin )
	{
		return \IPS\Task::devTable(
			\IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/tasks.json",
			\IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=tasks" ),
			\IPS\ROOT_PATH . "/plugins/{$plugin->location}/tasks",
			$plugin->location,
			'pluginTasks',
			$plugin->id
		);
	}
	
	/**
	 * Manage Widgets
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageWidgets( $plugin )
	{
		return \IPS\Widget::devTable(
			\IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/widgets.json",
			\IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=widgets" ),
			\IPS\ROOT_PATH . "/plugins/{$plugin->location}/widgets",
			$plugin->location,
			"plugins\\" . $plugin->location,
			$plugin->id
		);
	}
	
	/**
	 * Manage Versions
	 *
	 * @param	\IPS\Plugin	$plugin	The plugin
	 * @return	string
	 */
	protected function _manageVersions( $plugin )
	{
		if ( !file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json" ) )
		{
			\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json", json_encode( array() ) );
		}
		$versions = array();
		foreach ( json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json" ) ) as $long => $human )
		{
			$versions[] = array(
				'versions_long'		=> $long,
				'versions_human'	=> $human
			);
		}
		
		$table = new \IPS\Helpers\Table\Custom( $versions, \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=versions" ) );
		
		$table->rootButtons = array(
			'add' => array(
				'title'	=> 'versions_add',
				'icon'	=> 'plus',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=addVersion&plugin={$plugin->id}" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('versions_add') )
			)
		);
		
		$table->sortBy = $table->sortBy ?: 'versions_long';
		$table->sortDirection = $table->sortDirection ?: 'desc';
				
		$table->rowButtons = function( $row ) use ( $plugin )
		{
			return array(
				'delete' => array(
					'title'	=> 'delete',
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=deleteVersion&plugin={$plugin->id}&id={$row['versions_long']}" ),
					'data'	=> array( 'delete' => '' )
				)
			);
		};
		
		return (string) $table;
	}
	
	/**
	 * Versions: Add Version
	 *
	 * @return	void
	 */
	protected function addVersion()
	{
		/* Load Plugin */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->plugin );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/8', 404, '' );
		}
		
		/* Load existing versions.json file */
		$json = json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json" ), TRUE );
				
		/* Build form */
		$form = new \IPS\Helpers\Form( 'versions_add' );
		$defaults = array( 'human' => '1.0.0', 'long' => '10000' );
		foreach ( array_reverse( $json, TRUE ) as $long => $human )
		{
			$exploded = explode( '.', $human );
			$defaults['human'] = "{$exploded[0]}.{$exploded[1]}." . ( \intval( $exploded[2] ) + 1 );
			$defaults['long'] = $long + 1;
			break;
		}
		$form->add( new \IPS\Helpers\Form\Text( 'versions_human', $defaults['human'], TRUE, [], function( $val )
		{
			if ( !preg_match( '/^([0-9]+\.[0-9]+\.[0-9]+)/', $val ) )
			{
				throw new \DomainException( 'versions_human_bad' );
			}
		} ) );
		$form->add( new \IPS\Helpers\Form\Text( 'versions_long', $defaults['long'], TRUE, array(), function( $val ) use ( $json )
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
		} ) );
		
		/* Has the form been submitted? */
		if( $values = $form->values() )
		{
			$json[ $values['versions_long'] ] = $values['versions_human'];
			\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/{$values['versions_long']}.php", preg_replace( '/(<\?php\s)\/*.+?\*\//s', '$1', str_replace(
				array(
					'{version_human}',
					'{app}',
					'{version_long}',
				),
				array(
					$values['versions_human'],
					'plugins',
					$values['versions_long'],
				),
				file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/defaults/UpgradePlugin.txt" )
			) ) );
			\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json", json_encode( $json ) );
			
			krsort( $json );
			foreach ( $json as $long => $human )
			{
				$plugin->version_long = $long;
				$plugin->version_human = $human;
				$plugin->save();
				break;
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=versions" ) );
		}
					
		/* If not, show it */
		\IPS\Output::i()->output = $form;
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
		
		/* Load Plugin */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->plugin );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/A', 404, '' );
		}
		
		/* Load existing versions.json file */
		$json = json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json" ), TRUE );
		
		/* Unset */
		if ( isset( $json[ \intval( \IPS\Request::i()->id ) ] ) )
		{
			unset( $json[ \intval( \IPS\Request::i()->id ) ] );
		}
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/" . \intval( \IPS\Request::i()->id ) . ".php" ) )
		{
			unlink( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/" . \intval( \IPS\Request::i()->id ) . ".php" );
		}
		
		/* Write */
		\file_put_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json", json_encode( $json ) );
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=developer&id={$plugin->id}&tab=versions" ) );
	}

	/**
	 * Nulled: download plugin
	 *
	 * @return	void
	 */
	public function downloadNull()
	{
		/* Load */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/NULL', 404, '' );
		}

		if ( !file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$plugin->name}_{$plugin->version_human}.xml" ) ) 
		{
			\IPS\Output::i()->error( 'not_found', '2C146/NULL', 404, '' );
		}

		$xml = \IPS\ROOT_PATH . "/plugins/{$plugin->location}/{$plugin->name}_{$plugin->version_human}.xml";

		/* Build */
		\IPS\Output::i()->sendOutput( \file_get_contents( $xml ), 200, 'application/xml', array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', "{$plugin->name}_{$plugin->version_human}.xml" ) ), FALSE, FALSE, FALSE );
	}
		
	/**
	 * Developer Mode: Download
	 *
	 * @return	void
	 */
	public function download()
	{
		if( !\IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'not_in_dev', '2C145/R', 403, '' );
		}

		\IPS\Session::i()->csrfCheck();

		/* Load */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/B', 404, '' );
		}

        /* Don't allow downloads of Marketplace resources */
        if( $plugin->marketplace_id )
        {
            \IPS\Output::i()->error( 'plugin_cannot_build_marketplace', '2C145/Q', 403, '' );
        }

		/* Init */
		$xml = \IPS\Xml\SimpleXML::create('plugin');
		$xml->addAttribute( 'name', $plugin->name );
		$xml->addAttribute( 'version_long', $plugin->version_long );
		$xml->addAttribute( 'version_human', $plugin->version_human );
		$xml->addAttribute( 'author', $plugin->author );
		$xml->addAttribute( 'website', $plugin->website );
		$xml->addAttribute( 'update_check', $plugin->update_check );
		/* It's intentional that $plugin->location is skipped for the XML file. The location is being built automatically while the installation to avoid conflicts when another plugin uses the same location */

		/* Get Hooks */
		$hooks = $xml->addChild( 'hooks' );
		foreach ( \IPS\Db::i()->select( '*', 'core_hooks',  array( 'plugin=?', $plugin->id ) ) as $hook )
		{
			$hookNode = $hooks->addChild( 'hook', \IPS\Plugin::addExceptionHandlingToHookFile( \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/hooks/' . $hook['filename'] . '.php' ) );
			$hookNode->addAttribute( 'type', $hook['type'] );
			$hookNode->addAttribute( 'class', $hook['class'] );
			$hookNode->addAttribute( 'filename', $hook['filename'] );
		}
		
		/* Get Settings */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/settings.json" ) )
		{
			$settings	= json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/settings.json" ), TRUE );
			
			if ( !empty( $settings ) )
			{
				$inserts	= array();

				$xml->addChild( 'settings', $settings );

				foreach( $settings as $setting )
				{
					$key = $setting['key'];

					if( isset( \IPS\Settings::i()->$key ) )
					{
						\IPS\Db::i()->update( 'core_sys_conf_settings', array(
							'conf_default'	=> $setting['default'],
							'conf_plugin'	=> $plugin->id
						), array( 'conf_key=?', $key ) );
					}
					else
					{
						$inserts[] = array(
							'conf_key'		=> $key,
							'conf_value'	=> $setting['default'],
							'conf_default'	=> $setting['default'],
							'conf_plugin'	=> $plugin->id
						);
					}
				}

				if( \count( $inserts ) )
				{
					\IPS\Db::i()->insert( 'core_sys_conf_settings', $inserts , TRUE );
				}
				
				\IPS\Settings::i()->clearCache();
			}
		}

		/* Uninstall Code */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/uninstall.php" ) )
		{
			$xml->addChild( 'uninstall', file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/uninstall.php" ) );
		}

		/* Add the settings code */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/settings.php" ) )
		{
			$xml->addChild( 'settingsCode', file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/settings.php" ) );
		}
		
		/* Get tasks */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/tasks.json" ) )
		{
			$tasksNode = $xml->addChild( 'tasks' );
			foreach ( json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/tasks.json" ), TRUE ) as $key => $frequency )
			{
				$taskNode = $tasksNode->addChild( 'task', file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/tasks/{$key}.php" ) );
				$taskNode->addAttribute( 'key', $key );
				$taskNode->addAttribute( 'frequency', $frequency );

				/* Insert into DB */
				try
				{
					$task = \IPS\Task::load( $key, 'key', array( 'plugin=?', $plugin->id ) );
				}
				catch ( \OutOfRangeException $e )
				{
					$task = new \IPS\Task;
				}
				$task->plugin = $plugin->id;
				$task->key = $key;
				$task->frequency = $frequency;
				$task->save();
			}
		}
		
		/* Get sidebar widgets */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/widgets.json" ) )
		{
			$widgetsNode = $xml->addChild( 'widgets' );
			foreach ( json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/widgets.json" ), TRUE ) as $key => $json )
			{
				$content = file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/widgets/{$key}.php" );
				$content = str_replace( "namespace IPS\\plugins\\{$plugin->location}\\widgets", "namespace IPS\\plugins\\<{LOCATION}>\\widgets", $content );
				$content = str_replace( "public \$plugin = '{$plugin->id}';", "public \$plugin = '<{ID}>';", $content );
				$content = str_replace( "public \$app = '';", "", $content );
				$widgetNode = $widgetsNode->addChild( 'widget', $content );
				$widgetNode->addAttribute('key', $key );
				
				foreach ($json as $dataKey => $value)
				{
					if( \is_array( $value ) )
					{
						$value = implode( ",", $value );
					}

					$widgetNode->addAttribute( $dataKey, $value );
				}
				
				/* Automatically import everything into the local database to avoid having to toggle IN_DEV to import data first */
				try
				{
					$widget = \IPS\Db::i()->select( '*', 'core_widgets', array( '`key`=? and plugin=?', $key, $plugin->id ) )->first();
					
					\IPS\Db::i()->update( 'core_widgets', array(
						'plugin'	   => $plugin->id,
						'key'		   => $key,
						'class'		   => $json['class'],
						'restrict'     => json_encode( $json['restrict'] ),
						'default_area' => $json['default_area'],
						'allow_reuse'  => \intval( $json['allow_reuse'] ),
						'menu_style'   => $json['menu_style'],
						'embeddable'   => \intval( $json['embeddable'] )
					), array( '`id`=?', $widget['id'] ) );
				}
				catch ( \UnderflowException $e )
				{
					$inserts[] = array(
						'plugin'	   => $plugin->id,
						'key'		   => $key,
						'class'		   => $json['class'],
						'restrict'     => json_encode( $json['restrict'] ),
						'default_area' => $json['default_area'],
						'allow_reuse'  => \intval( $json['allow_reuse'] ),
						'menu_style'   => $json['menu_style'],
						'embeddable'   => \intval( $json['embeddable'] )
					);
					\IPS\Db::i()->insert( 'core_widgets', $inserts, TRUE );
				}
			}
		}
		
		/* If we're upgrading, remove current HTML, CSS, etc. We'll insert again in a moment */
		\IPS\Theme::removeTemplates( 'core', 'global', 'plugins', $plugin->id );
		\IPS\Theme::removeCss( 'core', 'front', 'custom', $plugin->id );
		\IPS\Theme::removeResources( 'core', 'global', 'plugins', $plugin->id );
		\IPS\Db::i()->delete( 'core_javascript', array( 'javascript_plugin=?', $plugin->id ) );

		/* Get HTML, CSS, JS, Resources */
		foreach ( array( 'html' => 'phtml', 'css' => 'css', 'js' => 'js', 'resources' => '*' ) as $k => $ext )
		{
			$resourcesNode = $xml->addChild( "{$k}Files" );
			foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/{$k}" ) as $file )
			{
				if ( !$file->isDot() and mb_substr( $file, 0, 1 ) != '.' and ( $ext === '*' or mb_substr( $file, - ( mb_strlen( $ext ) + 1 ) ) === ".{$ext}" ) AND $file != 'index.html'  )
				{
					$content = file_get_contents( $file->getPathname() );
					$resourcesNode->addChild( $k, base64_encode( $content ) )->addAttribute( 'filename', $file );
					
					/* Automatically import everything into the local database to avoid having to toggle IN_DEV to import data first */
					switch( $k )
					{
						case 'html':
							preg_match('/^<ips:template parameters="(.+?)?"([^>]+?)>(\r\n?|\n)/', $content, $matches );
							$output = preg_replace( '/^<ips:template parameters="(.+?)?"([^>]+?)>(\r\n?|\n)/', '', $content );

							\IPS\Theme::addTemplate( array(
								'app'		=> 'core',
								'location'	=> 'global',
								'group'		=> 'plugins',
								'name'		=> mb_substr( $file, 0, -6 ),
								'variables'	=> $matches[1],
								'content'	=> $output,
								'plugin'	=> $plugin->id,
								'_default_template' => TRUE,
								'rawContent' => $content,
							), TRUE );
							
							break;
							
						case 'css':
							\IPS\Theme::addCss( array(
								'app'		=> 'core',
								'location'	=> 'front',
								'path'		=> 'custom',
								'name'		=> $file,
								'content'	=> $content,
								'plugin'	=> $plugin->id
							), TRUE );
																		
							break;
							
						case 'js':
							try
							{
								$js = \IPS\Output\Javascript::find( 'core', 'plugins', '/', (string) $file );
								$js->delete();
							}
							catch ( \OutOfRangeException $e ) {}

							$js = new \IPS\Output\Javascript;
							$js->plugin  = $plugin->id;
							$js->name    = (string) $file;
							$js->content = $content;
							$js->version = $plugin->version_long;
							$js->save();
							
							break;
							
						case 'resources':
								\IPS\Theme::addResource( array(
									'app'		=> 'core',
									'location'	=> 'global',
									'path'		=> '/plugins/',
									'name'		=> $file,
									'content'	=> $content,
									'plugin'	=> $plugin->id
								) );
							break;
					}
				}
			}
		}
		
		/* Get language strings */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/lang.php" ) or file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php" ) )
		{
			$existingLanguageKeys	= iterator_to_array( \IPS\Db::i()->select( 'word_key', 'core_sys_lang_words', array( 'word_plugin=? and lang_id=?', $plugin->id, \IPS\Lang::defaultLanguage() ) ) );
			$keysToDelete			= $existingLanguageKeys;
			$inserts				= array();

			$langNode = $xml->addChild( 'lang' );
			foreach ( array( 'lang' => 0, 'jslang' => 1 ) as $file => $js )
			{
				if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/{$file}.php" ) )
				{
					require \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/{$file}.php";
					foreach ( $lang as $k => $v )
					{
						$word = $langNode->addChild( 'word', $v );
						$word->addAttribute( 'key', $k );
						$word->addAttribute( 'js', $js );
						
						/* Automatically import everything into the local database to avoid having to toggle IN_DEV to import data first */
						foreach ( \IPS\Lang::languages() as $lang )
						{
							if ( \count( $existingLanguageKeys ) and \in_array( $k, $existingLanguageKeys ) )
							{
								/* Exists so do not delete */
								$keysToDelete = array_diff( $keysToDelete, array( $k ) );
								
								\IPS\Db::i()->update( 'core_sys_lang_words', array(
										'word_default'			=> $v,
										'word_default_version'	=> $plugin->version_long,
										'word_js'				=> $js
									),
									array( 'lang_id=? and word_plugin=? and word_key=?', $lang->id, $plugin->id, $k )
								);
							}
							else
							{
								$inserts[] = array(
									'lang_id'				=> $lang->id,
									'word_app'				=> NULL,
									'word_plugin'			=> $plugin->id,
									'word_key'				=> $k,
									'word_default'			=> $v,
									'word_custom'			=> NULL,
									'word_default_version'	=> $plugin->version_long,
									'word_custom_version'	=> NULL,
									'word_js'				=> $js,
									'word_export'			=> 1,
								);
							}
						}
					}
				}
			}

			if( \count( $inserts ) )
			{
				\IPS\Db::i()->replace( 'core_sys_lang_words', $inserts );
			}
			
			if ( \count( $keysToDelete ) )
			{
				\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_plugin=? AND ' . \IPS\Db::i()->in( 'word_key', $keysToDelete ), $plugin->id ) );
			}
		}
		
		/* Get versions */
		$versionsNode = $xml->addChild( 'versions' );
		$versions = json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/versions.json" ), TRUE );
		ksort( $versions );

		foreach ( $versions as $k => $v )
		{
			$setupFile = ( $k == 10000 ) ? 'install.php' : $k . '.php';

			$node = $versionsNode->addChild( 'version', file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/" . $setupFile ) ? file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/setup/" . $setupFile ) : '' );
			$node->addAttribute( 'long', $k );
			$node->addAttribute( 'human', $v );
		}

		/* Add CMS Templates */
		if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/cmsTemplates.json" ) )
		{
			$pagesTemplates = json_decode( file_get_contents( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/cmsTemplates.json" ), TRUE );
			$templateXml = \IPS\cms\Templates::exportAsXml( $pagesTemplates );
			$xml->addChild( 'cmsTemplates', base64_encode( $templateXml->outputMemory() ) );
		}

		/* Build */
		\IPS\Output::i()->sendOutput( $xml->asXML(), 200, 'application/xml', array( "Content-Disposition" => \IPS\Output::getContentDisposition( 'attachment', "{$plugin->name} {$plugin->version_human}.xml" ) ), FALSE, FALSE, FALSE );
	}

	/**
	 * View plugin details
	 *
	 * @return	void
	 */
	public function details()
	{
		/* Load */
		try
		{
			$plugin = \IPS\Plugin::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C145/M', 404, '' );
		}
		
		/* Work out tab */
		$tabs = array( 'details' => 'plugin_details', 'hooks' => 'plugin_hooks' );
		$activeTab = ( \IPS\Request::i()->tab and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'details';
		$activeTabContents = '';
		
		/* Tab contents */
		if ( $activeTab === 'details' )
		{
			$activeTabContents = \IPS\Theme::i()->getTemplate( 'plugins' )->details( $plugin );
		}
		elseif ( $activeTab === 'hooks' )
		{
			$table = new \IPS\Helpers\Table\Db( 'core_hooks', \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=details&id={$plugin->id}&tab=hooks" ), array( 'plugin=?', $plugin->id ) );
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
			$activeTabContents = (string) $table;
		}
				
		/* Output */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'plugin_details' );
		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->tab ) )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=applications&controller=plugins&do=details&id={$plugin->id}" ) );
		}
	}

	/**
	 * Manage CMS Templates
	 *
	 * @param	\IPS\Plugin		$plugin		The plugin
	 * @return	string
	 */
	protected function _manageCmstemplates( \IPS\Plugin $plugin ): string
	{
		$templateConfigFile = \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/cmsTemplates.json";
		$preSelectedTemplates = array( 'database' => [], 'block' => [], 'page' => [] );

		if( file_exists( $templateConfigFile ) )
		{
			$preSelectedTemplates = json_decode( file_get_contents( $templateConfigFile ), TRUE );
		}

		$form = \IPS\cms\Templates::exportForm( TRUE, $preSelectedTemplates );

		if( $values = $form->values() )
		{
			\IPS\Application::writeJson( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/cmsTemplates.json", $values );
		}

		return $form;
	}
}
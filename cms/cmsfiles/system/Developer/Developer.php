<?php
/**
 * @brief		Application Developer Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 October 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Developer class used for IN_DEV management
 */
class _Developer
{
	
	/**
	 * @brief Array of directories that should always be present inside /dev
	 */
	protected static $devDirs = array( 'css', 'email', 'html', 'img', 'js' );
	
	/**
	 * @brief Array of multitons
	 */
	protected static $multitons = array();
	
	/**
	 * Synchronises development data between installations
	 *
	 * @return void
	 */
	public static function sync()
	{
		$updated	= FALSE;

		foreach ( \IPS\Application::applications() as $app )
		{
			$thisAppUpdated = static::load( $app->directory )->synchronize();
			$updated		= $updated ?: $thisAppUpdated;
		}

		if( $updated )
		{
			\IPS\Plugin\Hook::writeDataFile();

			/* Update JS cache bust */
			\IPS\Settings::i()->changeValues( array( 'javascript_updated' => time() ) );
		}
	}

	/**
	 * Stores objects
	 *
	 * @param	string	$app	Application key
	 * @return object \IPS\Developer
	 */
	public static function load( $app )
	{
		if ( ! isset( static::$multitons[ $app ] ) )
		{
			static::$multitons[ $app ] = new \IPS\Developer( \IPS\Application::load( $app ) );
		}
		
		return static::$multitons[ $app ];
	}
	
	/**
	 * @brief	Application
	 */
	protected $app;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Application		$app	The application the notification belongs to
	 * @return	void
	 */
	public function __construct( \IPS\Application $app )
	{
		$this->app = $app;
	}

	/**
	 * @brief	Last updates
	 */
	protected static $lastUpdates = NULL;
	
	/**
	 * Sync development data for an application
	 *
	 * @return void
	 */
	public function synchronize()
	{
		if ( static::$lastUpdates === NULL )
		{
			static::$lastUpdates = iterator_to_array( \IPS\Db::i()->select( '*', 'core_dev' )->setKeyField('app_key') );
		}
		
		/* Get versions */
		$versions      = array_keys( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/versions.json" ), TRUE ) );
		$latestVersion = array_pop( $versions );
		
		$updated = FALSE;

		/* A brand new app won't have a latest version */
		if( $latestVersion )
		{
			/* If we don't have a record for this app, assume we're up to date */
			if ( !isset( static::$lastUpdates[ $this->app->directory ] ) )
			{
				$content = NULL;

				if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" ) )
				{
					$content = file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" );
				}

				\IPS\Db::i()->insert( 'core_dev', array(
						'app_key'			=> $this->app->directory,
						'working_version'	=> $latestVersion,
						'last_sync'			=> time(),
						'ran'				=> $content,
				) );
			}
			/* Otherwise, do stuff */
			else
			{
				/* Database schema */
				if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" ) )
				{
					if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" ) )
					{
						/* Get schema file */
						$schema = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/schema.json" ), TRUE );
							
						/* Run queries for previous versions */
						if ( static::$lastUpdates[ $this->app->directory ]['working_version'] != $latestVersion )
						{
							/* Get all versions past the working version */
							$dirMatches = [];
							foreach ( new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/" ) as $dir )
							{
								if ( $dir->isDir() and !$dir->isDot() and preg_match( '/^upg_(\d+)$/', $dir, $matches ) )
								{
									if ( (int) $matches[1] >= static::$lastUpdates[ $this->app->directory ]['working_version'] )
									{
										$dirMatches[] = (int) $matches[1];
									}
								}
							}

							/* Run through the *sorted* versions. Note DirectoryIterator sorts by last modified, but you want this to run in order of versions */
							asort( $dirMatches );
							foreach ( $dirMatches as $match )
							{
								if ( $match == static::$lastUpdates[ $this->app->directory ]['working_version'] )
								{
									if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$match}/queries.json" ) )
									{
										$queries = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$match}/queries.json" ), TRUE );
										$localQueries = json_decode( static::$lastUpdates[ $this->app->directory ]['ran'], TRUE );
										foreach ( $queries as $q )
										{
											if ( \is_array( $localQueries ) AND !\in_array( $q, $localQueries ) )
											{
												$method = $q['method'];
												$params = $q['params'];
												\IPS\Db::i()->$method( ...$params );
											}
										}
									}
								}
								else
								{
									if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$match}/queries.json" ) )
									{
										$queries = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$match}/queries.json" ), TRUE );
										foreach ( $queries as $q )
										{
											try
											{
												$method = $q['method'];
												$params = $q['params'];
												\IPS\Db::i()->$method( ...$params );
											}
											catch( \IPS\Db\Exception $e )
											{
												/* If the issue is with a create table other than exists, we should just throw it */
												if ( $q['method'] == 'createTable' and ! \in_array( $e->getCode(), array( 1007, 1050 ) ) )
												{
													throw $e;
												}

												/* Can't change a column as it doesn't exist */
												if ( $e->getCode() == 1054 )
												{
													if ( $q['method'] == 'changeColumn' )
													{
														if ( \IPS\Db::i()->checkForTable( $q['params'][0] ) )
														{
															/* Does the column exist already? */
															if ( \IPS\Db::i()->checkForColumn( $q['params'][0], $q['params'][2]['name'] ) )
															{
																/* Just make sure it's up to date */
																\IPS\Db::i()->changeColumn( $q['params'][0], $q['params'][2]['name'], $q['params'][2] );
																continue;
															}
															else
															{
																/* The table exists, so lets just add the column */
																\IPS\Db::i()->addColumn( $q['params'][0], $q['params'][2] );

																continue;
															}
														}
													}

													throw $e;
												}
												/* Can't rename a table as it doesn't exist */
												else if ( $e->getCode() == 1017 )
												{
													if ( $q['method'] == 'renameTable' )
													{
														if ( \IPS\Db::i()->checkForTable( $q['params'][1] ) )
														{
															/* The table we are renaming to *does* exist */
															continue;
														}
													}

													throw $e;
												}
												/* If the error isn't important we should ignore it */
												else if( !\in_array( $e->getCode(), array( 1007, 1008, 1050, 1051, 1060, 1061, 1062, 1091 ) ) )
												{
													throw $e;
												}
											}
										}
									}
								}
							}
			
							static::$lastUpdates[ $this->app->directory ]['ran'] = json_encode( array() );
						}
							
						/* Run queries for this version */
						$queries = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" ), TRUE );
						$localQueries = json_decode( static::$lastUpdates[ $this->app->directory ]['ran'], TRUE );
			
						if( \is_array($queries) )
						{
							foreach ( $queries as $q )
							{
								if ( !\is_array($localQueries) OR !\in_array( $q, $localQueries ) )
								{
									/* Check if the table exists, as it may be an import */
									if ( $q['method'] === 'renameTable' and \IPS\Db::i()->checkForTable( $q['params'][0] ) === FALSE )
									{
										if ( isset( $schema[ $q['params'][1] ] ) )
										{
											try
											{
												\IPS\Db::i()->createTable( $schema[ $q['params'][1] ] );
											}
											catch ( \IPS\Db\Exception $e ) { }
										}
									}
									/* Run */
									else
									{
										try
										{
											$method = $q['method'];
											$params = $q['params'];
											\IPS\Db::i()->$method( ...$params );
										}
										catch ( \IPS\Db\Exception $e ) { }
									}
								}
							}
						}
			
						$updated = TRUE;
					}
					else
					{
						$queries = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/setup/upg_{$latestVersion}/queries.json" ), TRUE );
					}
				}
		
				/* Check for missing tables or columns */
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/schema.json" ) )
				{
					$this->app->installDatabaseSchema( TRUE );
						
					$updated = TRUE;
				}
		
				/* Settings */
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/settings.json" ) )
				{
					$this->app->installSettings();
						
					$updated = TRUE;
				}
		
				/* Modules */
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/modules.json" ) )
				{
					$this->app->installModules();
						
					$updated = TRUE;
				}
		
				/* Tasks */				
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/tasks.json" ) )
				{
					$this->app->installTasks();
		
					$updated = TRUE;
				}
				
				/* Widgets */
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/widgets.json" ) )
				{
					$this->app->installWidgets();
				
					$updated = TRUE;
				}
		
				/* Skin Settings */
				if ( static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/themesettings.json" ) )
				{					
					if ( $this->app->directory == 'core' )
					{
						/* Make sure we've got a skin set ID 1 (constant DEFAULT_THEME_ID) */
						try
						{
							$skinSetOne = \IPS\Db::i()->select( '*', 'core_themes', array( 'set_id=?', \IPS\DEFAULT_THEME_ID ) )->first();
						}
						catch( \Exception $e )
						{
							$skinSetOne = array();
						}
		
						if ( ! isset( $skinSetOne['set_id'] ) )
						{
							\IPS\Db::i()->insert( 'core_themes', array(
									'set_id'	    			=> \IPS\DEFAULT_THEME_ID,
									'set_name'      			=> 'Default',
									'set_key'      				=> 'master',
									'set_parent_id' 			=> 0,
									'set_parent_array' 			=> '[]',
									'set_child_array'  			=> '[]',
									'set_permissions'  			=> '*',
									'set_author_name'  			=> "Invision Power Services, Inc",
									'set_author_url'   			=> 'https://www.invisioncommunity.com',
									'set_added'		   			=> time(),
									'set_updated'	  			=> time(),
									'set_template_settings'     => '[]',
									'set_version'				=> \IPS\Application::load( $this->app->directory )->version,
									'set_long_version'			=> \IPS\Application::load( $this->app->directory )->long_version,
							) );
								
							\IPS\Lang::saveCustom( 'core', "core_theme_set_title_" . \IPS\DEFAULT_THEME_ID, "Default" );
						}
						else if ( $skinSetOne['set_name'] != 'Default' )
						{
							\IPS\Db::i()->update( 'core_themes', array( 'set_name' => 'Default' ), array( 'set_id=?', \IPS\DEFAULT_THEME_ID ) );
							\IPS\Lang::saveCustom( 'core', "core_theme_set_title_" . \IPS\DEFAULT_THEME_ID, "Default" );
						}

						unset( \IPS\Data\Store::i()->themes );
					}
						
					$currentSettings =  iterator_to_array( \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_app=?', \IPS\DEFAULT_THEME_ID, $this->app->directory ) )->setKeyField('sc_key') );
						
					$json		= ( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/themesettings.json" ) ) ?
						json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/themesettings.json" ), TRUE ) :
						array();
					$jsonKeys	= array();
						
					/* Add */
					foreach( $json as $key => $data)
					{
						$jsonKeys[] = $data['sc_key'];
		
						if ( ! isset( $currentSettings[ $data['sc_key'] ] ) )
						{
							$currentId = \IPS\Db::i()->insert( 'core_theme_settings_fields', array(
								'sc_set_id'		 => \IPS\DEFAULT_THEME_ID,
								'sc_key'		 => $data['sc_key'],
								'sc_tab_key'	 => $data['sc_tab_key'],
								'sc_type'		 => $data['sc_type'],
								'sc_multiple'	 => $data['sc_multiple'],
								'sc_default'	 => $data['sc_default'],
								'sc_content'	 => $data['sc_content'],
								'sc_show_in_vse' => ( isset( $data['sc_show_in_vse'] ) ) ? $data['sc_show_in_vse'] : 0,
								'sc_updated'	 => time(),
								'sc_app'		 => $this->app->directory,
								'sc_title'		 => $data['sc_title'],
								'sc_order'		 => $data['sc_order'],
								'sc_condition'	 => $data['sc_condition'],
							) );
						}
						else
						{
							/* Update */
							\IPS\Db::i()->update( 'core_theme_settings_fields', array(
								'sc_tab_key'	 => $data['sc_tab_key'],
								'sc_type'		 => $data['sc_type'],
								'sc_multiple'	 => $data['sc_multiple'],
								'sc_default'	 => $data['sc_default'],
								'sc_show_in_vse' => ( isset( $data['sc_show_in_vse'] ) ) ? $data['sc_show_in_vse'] : 0,
								'sc_content'	 => $data['sc_content'],
								'sc_title'		 => $data['sc_title'],
								'sc_order'		 => $data['sc_order'],
								'sc_condition'	 => $data['sc_condition'],
							), array( 'sc_set_id=? AND sc_key=? AND sc_app=?', \IPS\DEFAULT_THEME_ID, $data['sc_key'], $this->app->directory ) );
							
							$currentId = $currentSettings[ $data['sc_key'] ]['sc_id'];
						}
						
						\IPS\Db::i()->delete('core_theme_settings_values', array('sv_id=?', $currentId ) );
						\IPS\Db::i()->insert('core_theme_settings_values', array( 'sv_id' => $currentId, 'sv_value' => (string) $data['sc_default'] ) );
					}
		
					/* Remove items not in the JSON file */
					foreach( $currentSettings as $key => $data )
					{
						if ( ! \in_array( $data['sc_key'], $jsonKeys ) )
						{
							\IPS\Db::i()->delete( 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_key=? AND sc_app=?', \IPS\DEFAULT_THEME_ID, $data['sc_key'], $this->app->directory ) );
						}
					}
		
					$updated = TRUE;
				}
				
				/* ACP Search Keywords */
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/acpsearch.json" ) AND static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/acpsearch.json" ) )
				{
					$this->app->installSearchKeywords();
						
					$updated = TRUE;
				}
				
				/* Hooks */
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/hooks.json" ) AND static::$lastUpdates[ $this->app->directory ]['last_sync'] < filemtime( \IPS\ROOT_PATH . "/applications/{$this->app->directory}/data/hooks.json" ) )
				{
					$this->app->installHooks();
						
					$updated = TRUE;
				}
				
				if ( method_exists( $this->app, 'developerSync' ) )
				{
					$devUpdated = $this->app->developerSync( static::$lastUpdates[ $this->app->directory ]['last_sync'] );
					$updated	= $updated ?: $devUpdated;
				}	
					
				/* Update record */
				if ( $updated === TRUE )
				{					
					\IPS\Theme::load( \IPS\DEFAULT_THEME_ID )->saveSet();
						
					\IPS\Db::i()->update( 'core_dev', array(
						'working_version'	=> $latestVersion,
						'last_sync'			=> time(),
						'ran'				=> isset( $queries ) ? json_encode( $queries ) : array(),
					), array( 'app_key=?', $this->app->directory ) );

					\IPS\Data\Store::i()->clearAll();
					\IPS\Data\Cache::i()->clearAll();
				}
			}
		}

		return $updated;
	}
}
<?php
/**
 * @brief		Installer
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Apr 2013
 */

namespace IPS\core\Setup;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Installer
 */
class _Install
{	
	/**
	 * System Requirements
	 *
	 * @return	array
	 */
	public static function systemRequirements()
	{
		$return = array();

		/* We don't need to check the CIC platform */
		if( \IPS\CIC )
		{
			return array( 'recommendations' => array(), 'requirements' => array() );
		}
				
		/* PHP Version */
		$phpVersion = PHP_VERSION;
		$requirements = json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/core/data/requirements.json' ), TRUE );
		if ( version_compare( $phpVersion, $requirements['php']['required'] ) >= 0 )
		{
			$return['requirements']['PHP']['version'] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_success', FALSE, array( 'sprintf' => array( $phpVersion ) ) )
			);
		}
		else
		{
			$return['requirements']['PHP']['version'] = array(
				'success'	=> FALSE,
				'message'	=> ( $requirements['php']['required'] == $requirements['php']['recommended'] ) ? \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_fail_no_recommended', FALSE, array( 'sprintf' => array( $phpVersion, $requirements['php']['required'] ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_fail', FALSE, array( 'sprintf' => array( $phpVersion, $requirements['php']['required'], $requirements['php']['recommended'] ) ) ),
			);
		}
		if ( $return['requirements']['PHP']['version']['success'] and version_compare( $phpVersion, $requirements['php']['recommended'] ) == -1 )
		{
			$return['requirements']['PHP']['version']['message'] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_success', FALSE, array( 'sprintf' => array( $phpVersion ) ) );
			$return['advice']['PHP']['php'] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_php_version_advice', FALSE, array( 'sprintf' => array( $phpVersion, $requirements['php']['recommended'] ) ) );
		}

		/* We require file_uploads otherwise lots of stuff won't work */
		if( !ini_get('file_uploads') OR mb_strtolower( ini_get('file_uploads') ) == 'off' )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_uploads' ),
				'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_fileuploads' ),
			);
		}
		
		/* cURL or allow_url_fopen */
		if ( \extension_loaded('curl') and $version = curl_version() and version_compare( $version['version'], '7.36', '>=' ) )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_success' ),
			);
		}
		elseif ( \function_exists('fsockopen') )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_fopen' ),
			);
			
			$return['advice']['PHP']['curl'] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_advice' );
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_curl_fail' ),
				'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_curl' ),
			);
		}
		
		/* mbstring can be configured with --disable-mbregex */
		if ( \extension_loaded( 'mbstring' ) )
		{
			if ( \function_exists( 'mb_eregi' ) )
			{
				$return['requirements']['PHP'][] = array(
					'success'	=> TRUE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_success' ),
				);
			}
			else
			{
				$return['requirements']['PHP'][] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_regex' ),
					'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_mbregex' ),
				);
			}

			if( ini_get('mbstring.func_overload') AND ini_get('mbstring.func_overload') > 0 )
			{
				$return['requirements']['PHP'][] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_overload' ),
					'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_mboverload' ),
				);
			}
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_mb_fail' ),
				'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_mb' ),
			);
		}
		
		/* Extensions */
		foreach ( array(
			'required'	=> array( 'dom', 'gd', 'mysqli', 'openssl', 'session', 'simplexml', 'xml', 'xmlreader', 'xmlwriter' ),
			'advised'	=> array( 'phar', 'zip', 'exif' ),
		) as $type => $extensions )
		{
			foreach ( $extensions as $extension )
			{
				if ( \extension_loaded( $extension ) )
				{
					$return['requirements']['PHP'][] = array(
						'success'	=> TRUE,
						'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_success', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) ),
					);
				}
				elseif ( $type === 'required' )
				{
					$return['requirements']['PHP'][] = array(
						'success'	=> FALSE,
						'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_fail', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) ),
						'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_extension', FALSE, array( 'sprintf' => array( $extension ) ) ),
					);
				}
				elseif ( $type === 'advised' )
				{
					if( \IPS\Member::loggedIn()->language()->checkKeyExists( 'requirements_extension_advice_' . $extension ) )
					{
						$return['advice']['PHP'][ $extension ] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_advice_' . $extension );
					}
					else
					{
						$return['advice']['PHP'][ $extension ] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_extension_advice', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "requirements_extension_{$extension}" ) ) ) );
					}
				}
			}
		}

		/* Memory Limit */
		$_memoryLimit	= @ini_get('memory_limit');
		$memoryLimit	= $_memoryLimit;
		if ( $memoryLimit != -1 )
		{
			preg_match( "#^(\d+)(\w+)$#", mb_strtolower($memoryLimit), $match );
			if( $match[2] == 'g' )
			{
				$memoryLimit = \intval( $memoryLimit ) * 1024 * 1024 * 1024;
			}
			else if ( $match[2] == 'm' )
			{
				$memoryLimit = \intval( $memoryLimit ) * 1024 * 1024;
			}
			else if ( $match[2] == 'k' )
			{
				$memoryLimit = \intval( $memoryLimit ) * 1024;
			}
			else
			{
				$memoryLimit = \intval( $memoryLimit );
			}
		}
		if ( $memoryLimit >= ( 128 * 1024 * 1024 ) OR $memoryLimit == -1 )
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> TRUE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_memory_limit_success', FALSE, array( 'sprintf' => array( ( $memoryLimit != -1 ) ? \IPS\Output\Plugin\Filesize::humanReadableFilesize( $memoryLimit ) : \IPS\Member::loggedIn()->language()->get( 'unlimited' ) ) ) )
			);
		}
		else
		{
			$return['requirements']['PHP'][] = array(
				'success'	=> FALSE,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'requirements_memory_limit_fail', FALSE, array( 'sprintf' => array( \IPS\Output\Plugin\Filesize::humanReadableFilesize( $memoryLimit ) ) ) ),
				'short'		=> \IPS\Member::loggedIn()->language()->addToStack( 'health__php_memory' ),
			);
		}
		
		$writeablesKey = \IPS\Member::loggedIn()->language()->addToStack('requirements_file_system');

		/* Suhosin */
		if ( \extension_loaded( 'suhosin' ) )
		{
			foreach ( array(
				'suhosin.post.max_vars'					=> 4096,
				'suhosin.request.max_vars'				=> 4096,
				'suhosin.get.max_value_length'			=> 2000,
				'suhosin.post.max_value_length'			=> 10000,
				'suhosin.request.max_value_length'		=> 10000,
				'suhosin.request.max_varname_length'	=> 350,
			) as $setting => $minimum )
			{
				$value = ini_get( $setting );
				if ( $value and $value < $minimum )
				{
					$return['advice'][ $writeablesKey ]['suhosin'] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_suhosin_limit', FALSE, array( 'sprintf' => array( $setting, $value, $minimum ) ) );
				}
			}
			
			$value = ini_get('suhosin.cookie.encrypt');
			if ( $value and $value == 1 )
			{
				$return['advice'][ $writeablesKey ]['suhosin'] = \IPS\Member::loggedIn()->language()->addToStack( 'requirements_suhosin_cookie_encrypt' );
			}
		}
		
		/* Writeables */
		foreach ( array( 'applications', 'datastore', 'plugins', 'uploads' ) as $dir )
		{
			$success = is_writable( \IPS\ROOT_PATH . '/' . $dir );
			
			$return['requirements'][ $writeablesKey ][ $dir ] = array(
				'success'	=> $success,
				'message'	=> $success ?  \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH . '/' . $dir ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'err_not_writable', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH . '/' . $dir ) ) )
			);
		}
		if( !\IPS\NO_WRITES )
		{
			$dir = \IPS\Log::fallbackDir();
			if ( $dir !== NULL )
			{
				$success = is_writable( $dir );
				$return['requirements'][ $writeablesKey ][ $dir ] = array(
					'success'	=> $success,
					'message'	=> $success ? \IPS\Member::loggedIn()->language()->addToStack( 'requirements_file_writable', FALSE, array( 'sprintf' => array( $dir ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'err_not_writable', FALSE, array( 'sprintf' => array( $dir ) ) )
				);
			}
		}
		try
		{
			if ( !is_writable( \IPS\TEMP_DIRECTORY ) )
			{
				throw new \Exception;
			}
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			if ( $tempFile === FALSE or !file_exists( $tempFile ) )
			{
				throw new \Exception;
			}

			@unlink( $tempFile );
		}
		catch( \Exception $e )
		{
			if( file_exists( \IPS\ROOT_PATH . '/constants.php' ) )
			{
				$return['requirements'][ $writeablesKey ]['tmp'] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'err_tmp_dir_adjust', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH ) ) )
				);
			}
			else
			{
				$return['requirements'][ $writeablesKey ]['tmp'] = array(
					'success'	=> FALSE,
					'message'	=> \IPS\Member::loggedIn()->language()->addToStack( 'err_tmp_dir_create', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH ) ) )
				);
			}
		}
		
		return $return;
	}
	
	/**
	 * @brief	Percentage of *this step* completed (used for the progress bar)
	 */
	protected $stepProgress = 0;

	/**
	 * Constructor
	 *
	 * @param	array	$apps			Application keys of apps to install
	 * @param	string	$defaultApp		The default applicaion
	 * @param	string	$baseUrl		Base URL
	 * @param	array	$path			Base Path
	 * @param	array	$db				Database connection detials [see \IPS\Db::i()]
	 * @param	string	$adminName		Admin Username
	 * @param	string	$adminPass		Admin Password
	 * @param	string	$adminEmail		Admin Email
	 * @param	bool	$diagnostics	Enable diagnostics reporting?
	 * @return	void
	 * @throws	\InvalidArgumentException
	 * @see		\IPS\Db::i()
	 */
	public function __construct( $apps, $defaultApp, $baseUrl, $path, $db, $adminName, $adminPass, $adminEmail, $diagnostics=TRUE )
	{
		/* Have core app? */
		if ( !\in_array( 'core', $apps ) )
		{
			throw new \InvalidArgumentException( 'NO_CORE_APP' );
		}
		
		/* Put the default app first */
		usort( $apps, function( $a, $b ) use ( $defaultApp ){
			if ( $a == 'core' or ( $a == $defaultApp and $b != 'core' ) )
			{
				return -1;
			}
			if ( $b == 'core' or ( $b == $defaultApp and $a != 'core' ) )
			{
				return 1;
			}
			return 0;
		} );
		
		/* Connect to DB */
		$db = \IPS\Db::i( NULL, $db );
		
		/* Check we have everything else */
		if ( !$baseUrl or !$path or !$adminName or !$adminEmail or !$adminPass )
		{
			throw new \InvalidArgumentException( 'INSUFFICIENT_DATA' );
		}
		
		/* Store data */
		$this->apps			= $apps;
		$this->defaultApp	= $defaultApp;
		$this->baseUrl		= $baseUrl;
		$this->path			= $path;
		$this->adminName	= $adminName;
		$this->adminPass	= $adminPass;
		$this->adminEmail	= $adminEmail;
		$this->diagnostics	= $diagnostics;
	}
	
	/**
	 * @brief	Custom Title
	 */
	protected $customTitle = NULL;
	
	/**
	 * Process
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array|null	Multiple-Redirector Data or NULL indicates done
	 */
	public function process( $data )
	{
		/* Start */
		if ( ! $data )
		{
			return array( array( 1 ), \IPS\Member::loggedIn()->language()->addToStack('installing') );
		}
		
		/* Run the step */
		$step = \intval( $data[0] );
		
		if ( $step == 13 )
		{
			return NULL;
		}
		elseif ( !method_exists( $this, "step{$step}" ) )
		{
			throw new \BadMethodCallException( 'NO_STEP' );
		}
		$stepFunction = "step{$step}";
		$response = $this->$stepFunction( $data );
		
		return array( $response, ( $this->customTitle ) ? $this->customTitle : \IPS\Member::loggedIn()->language()->addToStack( 'install_step_' . $step ), ( ( ( 100/12 ) * $data[0] + ( ( 100/12 ) / 100 * $this->stepProgress ) ) ) ?: 1 );
	}
	
	/**
	 * App Looper
	 *
	 * @param	array		$data	Multiple-Redirector Data
	 * @param	callback	$code	Code to execute for each app
	 * @return	array		Data to Multiple-Redirector Data
	 */
	protected function appLoop( $data, $code )
	{
		$this->stepProgress = 0;
		
		$returnNext = FALSE;
		foreach ( $this->apps as $app )
		{
			$this->stepProgress += ( 100 / \count( $this->apps ) );
						
			if ( !isset( $data[1] ) )
			{
				return array( $data[0], $app );
			}
			elseif ( $data[1] == $app )
			{
				$val = $code( $app );
				
				if ( \is_array( $val ) )
				{
					return $val;
				}
				else
				{
					$returnNext = true;
				}
			}
			elseif ( $returnNext )
			{
				return array( $data[0], $app );
			}
		}
		
		return array( ( $data[0] + 1 ) );
	}
	
	/**
	 * Step 1
	 * Create database
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step1( $data )
	{
		$this->stepProgress = 0;
		$perAppProgress = floor( 100 / \count( $this->apps ) );
				
		$returnNext = FALSE;
		foreach ( $this->apps as $app )
		{
			$this->stepProgress += $perAppProgress;
						
			if ( !isset( $data[1] ) )
			{
				return array( $data[0], $app );
			}
			elseif ( $data[1] == $app )
			{
				if ( !isset( $data[2] ) )
				{
					$data[2] = 0;
				}
				
				$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_1_app'), $app, $data[2] );
				
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/data/schema.json" ) )
				{
					$schema = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/schema.json" ), TRUE );
					if ( \count( $schema ) )
					{
						$perTableProgress = ( $perAppProgress / \count( $schema ) );
						$i = 0;
						foreach( $schema as $dbTable )
						{
							$i++;
							$this->stepProgress += $perTableProgress;
							while ( $data[2] > $i )
							{
								continue 2;
							}
														
							\IPS\Db::i()->dropTable( $dbTable['name'], TRUE );
							\IPS\Db::i()->createTable( $dbTable );
													
							if ( isset( $dbTable['inserts'] ) )
							{
								foreach ( $dbTable['inserts'] as $insertData )
								{
									$adminName = $this->adminName;
									\IPS\Db::i()->insert( $dbTable['name'], array_map( function( $column ) use( $adminName ) {
										if( !\is_string( $column ) )
										{
											return $column;
										}

										$column = str_replace( '<%TIME%>', time(), $column );
										$column = str_replace( '<%ADMIN_NAME%>', $adminName, $column );
										$column = str_replace( '<%IP_ADDRESS%>', $_SERVER['REMOTE_ADDR'], $column );
										return $column;
									}, $insertData ) );
								}
							}
							
							if ( !file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ) )
							{
								$data[2]++;
								return $data;
							}
						}
					}
				}
				
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ) )
				{
					$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/setup/install/queries.json" ), TRUE );
		
					ksort($schema);
		
					foreach( $schema as $instruction )
					{
						if ( $instruction['method'] === 'addColumn' )
						{
							/* Check to see if it exists first */
							$tableDefinition = \IPS\Db::i()->getTableDefinition( $instruction['params'][0] );
							
							if ( ! empty( $tableDefinition['columns'][ $instruction['params'][1]['name'] ] ) )
							{
								/* Run an alter instead */
								\IPS\Db::i()->changeColumn( $instruction['params'][0], $instruction['params'][1]['name'], $instruction['params'][1] );
								continue;
							}
						}

						if( isset( $instruction['params'][1] ) and \is_array( $instruction['params'][1] ) )
						{
							$groups	= array_filter( iterator_to_array( \IPS\Db::i()->select( 'g_id', 'core_groups' ) ), function( $groupId ) {
								if( $groupId == 2 )
								{
									return FALSE;
								}

								return TRUE;
							});

							foreach( $instruction['params'][1] as $column => $value )
							{
								if( $value === "<%NO_GUESTS%>" )
								{
									$instruction['params'][1][ $column ]	= implode( ",", $groups );
								}
							}
						}

						$method = $instruction['method'];
						$params = $instruction['params'];
						\IPS\Db::i()->$method( ...$params );
					}
				}
							
				$returnNext = TRUE;
			}
			elseif ( $returnNext )
			{
				return array( $data[0], $app );
			}
		}
		
		return array( ( $data[0] + 1 ) );
	}
	
	/**
	 * Step 2
	 * Insert application and module data
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step2( $data )
	{
		$pos = 0;
		$defaultApp = $this->defaultApp;
		return $this->appLoop( $data, function( $app ) use ( &$pos, $defaultApp )
		{
			/* Get version data */
			if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/data/versions.json" ) )
			{
				$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_2_app'), $app );
				$versions = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/versions.json" ), TRUE );
				$keys = array_keys( $versions );

				$info = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/application.json" ), TRUE );
						
				/* App Data */
				\IPS\Db::i()->insert( 'core_applications', array(
					'app_author'		=> $info['app_author'],
					'app_version'		=> array_pop( $versions ),
					'app_long_version'	=> array_pop( $keys ),
					'app_directory'		=> $app,
					'app_added'			=> time(),
					'app_position'		=> ++$pos,
					'app_protected'		=> ( $app === 'core' ),
					'app_enabled'		=> TRUE,
					'app_default'		=> ( $app === $defaultApp ),
				) );
				
				/* Modules */
				$modules = json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/data/modules.json" ), TRUE );
				$modulePos = 0;
				foreach ( $modules as $area => $areaModules )
				{
					foreach ( $areaModules as $key => $data )
					{
						$insertId = \IPS\Db::i()->insert( 'core_modules', array(
							'sys_module_application'		=> $app,
							'sys_module_key'				=> $key,
							'sys_module_protected'			=> $data['protected'],
							'sys_module_visible'			=> TRUE,
							'sys_module_position'			=> ++$modulePos,
							'sys_module_area'				=> $area,
							'sys_module_default_controller'	=> $data['default_controller'],
							'sys_module_default'			=> (int) ( isset( $data['default'] ) and $data['default'] )
						) );
						
						\IPS\Db::i()->insert( 'core_permission_index', array(
							'app'			=> 'core',
							'perm_type'		=> 'module',
							'perm_type_id'	=> $insertId,
							'perm_view'		=> '*',
						) );
					}
				}
			}
		} );
	}
	
	/**
	 * Step 3
	 * Insert Settings
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step3( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_3_app'), $app );
			\IPS\Application::load( $app )->installSettings();
			
			if ( $app === 'core' )
			{
				require \IPS\ROOT_PATH . '/conf_global.php';

				\IPS\Db::i()->insert( 'core_file_storage', array(
					'method' => 'FileSystem',
					'configuration' => json_encode( array(
						'dir' => '{root}/uploads',
						'url' => 'uploads'
					) )
				) );
			}
			
			/* Set up File Storage methods */
			\IPS\Application::load( $app )->installExtensions( TRUE );
		} );
	}
	
	/**
	 * Step 4
	 * Create admin account
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step4( $data )
	{
		\IPS\Settings::i()->member_group = 4;
		$member = new \IPS\Member;
		$member->pp_photo_type        = '';
		$member->name = $this->adminName;
		$member->email = $this->adminEmail;
		$member->ip_address	= \IPS\Request::i()->ipAddress();
		$member->timezone = 'UTC';
		$member->member_group_id = 4;
		$member->allow_admin_mails = 0;
		$member->joined = time();
		$member->setLocalPassword( $this->adminPass );
		$member->members_bitoptions['view_sigs'] = TRUE;
		$member->save();
	
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->adminEmail ), "conf_key = 'email_out'" );
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->adminEmail ), "conf_key = 'email_in'" );
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->adminEmail ), "conf_key = 'upgrade_email'" );
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $this->diagnostics ), "conf_key = 'diagnostics_reporting' OR conf_key = 'usage_reporting'" );

		\IPS\Settings::i()->clearCache();
		
		return array( 5 );
	}
	
	/**
	 * Step 5
	 * Create Tasks
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step5( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_5_app'), $app );
			\IPS\Application::load( $app )->installTasks();

		} );
	}

	/**
	 * Step 6
	 * Install default Language
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step6( $data )
	{
		/* Install the default language */
		$locales = array( 'en_US', 'en_US.UTF-8', 'en_US.UTF8', 'en_US.utf8', 'english' );
		foreach ( $locales as $k => $localeCode )
		{
			try
			{
				\IPS\Lang::validateLocale( $localeCode );
			}
			catch ( \InvalidArgumentException $e )
			{
				unset( $locales[ $k ] );
			}
		}

		$locale = ( !empty( $locales ) ) ? array_shift( $locales ) : 'en_US';

		\IPS\Db::i()->insert( 'core_sys_lang', array(
				'lang_id' => 1,
				'lang_short' => $locale,
				'lang_title' => "English (USA)",
				'lang_default' => 1,
				'lang_isrtl' => 0,
				'lang_protected' => 1,
				'lang_order' => 1
			)
		);

		if ( isset( \IPS\Data\Store::i()->languages ) )
		{
			unset( \IPS\Data\Store::i()->languages );
		}

		return array( 7 );
	}
	
	/**
	 * Step 7
	 * Create Languages
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step7( $data )
	{
		return $this->appLoop( $data, function( $app ) use ($data)
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_7_app'), $app, $data[2] );
			$inserted = \IPS\Application::load( $app )->installLanguages( $data[2], 250 );
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				return null;
			}
		} );
	}
	
	/**
	 * Step 8
	 * Create Email Templates
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step8( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_8_app'), $app );
			\IPS\Application::load( $app )->installEmailTemplates();
		} );
	}
	
	/**
	 * Step 9
	 * Create Themes
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step9( $data )
	{
		return $this->appLoop( $data, function( $app ) use ($data)
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}
			
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_9_app'), $app, $data[2] );
			if( $data[2] == 0 )
			{
				\IPS\Application::load( $app )->installThemeSettings();
			}
			
			$inserted = \IPS\Application::load( $app )->installTemplates( FALSE, $data[2], 150 );
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				\IPS\Theme::load( \IPS\Theme::defaultTheme() )->saveSet();
				return null;
			}
		} );
	}
	
	/**
	 * Step 10
	 * Create Javascript
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step10( $data )
	{
		return $this->appLoop( $data, function( $app ) use( $data )
		{
			if ( !isset( $data[2] ) )
			{
				$data[2] = 0;
			}
			
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_10_app'), $app, $data[2] );
			$inserted = \IPS\Application::load( $app )->installJavascript( $data[2], 250 );
			
			if ( $inserted )
			{
				$data[2] += $inserted;
				return $data;
			}
			else
			{
				return null;
			}
		} );
	}
	
	/**
	 * Step 11
	 * Create Search Keywords
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step11( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_11_app'), $app );
			\IPS\Application::load( $app )->installSearchKeywords();
		} );
	}
	
	/**
	 * Step 12
	 * Install any widgets/extensions that need adding on install
	 *
	 * @param	array	$data	Multi-redirector data
	 * @return	array	Multiple-Redirector Data
	 */
	protected function step12( $data )
	{
		return $this->appLoop( $data, function( $app )
		{
			if( !file_exists( \IPS\ROOT_PATH . '/plugins/hooks.php' ) )
			{
				@touch( \IPS\ROOT_PATH . '/plugins/hooks.php' );
				@chmod( \IPS\ROOT_PATH . '/plugins/hooks.php', 0777 );
			}

			if( !is_writable( \IPS\ROOT_PATH . '/plugins/hooks.php' ) )
			{
				throw new \RuntimeException( sprintf( \IPS\Member::loggedIn()->language()->get("hook_file_not_writable"), \IPS\ROOT_PATH . '/plugins/hooks.php' ) );
			}
			
			$this->customTitle = sprintf( \IPS\Member::loggedIn()->language()->get('install_step_12_app'), $app );
			try
			{
				\IPS\Application::load( $app )->installHooks();
				\IPS\Application::load( $app )->installWidgets();
				\IPS\Application::load( $app )->installOther();
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e, 'install_error' );
			}
			
			/* Insert default emoticons and reaction */
			if( $app == 'core' )
			{
				$setId = mt_rand();
				$position = 0;
				\IPS\Lang::saveCustom( 'core', "core_emoticon_group_{$setId}", "Classic" );

				$inserts = array();

				foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY ."/install/emoticons/data.json" ), TRUE ) as $type => $file )
				{
					$fileObj = \IPS\File::create( 'core_Emoticons', $file['image'], file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $file['image'] ), 'emoticons', FALSE, NULL, FALSE );
					$fileObj2x = isset( $file['image_2x'] ) ? \IPS\File::create( 'core_Emoticons', $file['image_2x'], file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $file['image_2x'] ), 'emoticons', FALSE, NULL, FALSE ) : NULL;
					$imageProperties = @getimagesize( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $file['image'] );

					$inserts[] = array(
						'typed'			=> $type,
						'image'			=> (string) $fileObj,
						'image_2x'		=> (string) $fileObj2x,
						'clickable'		=> TRUE,
						'emo_set'		=> $setId,
						'emo_position'	=> ++$position,
						'width'			=> isset( $imageProperties[0] ) ? $imageProperties[0] : 0,
						'height'		=> isset( $imageProperties[1] ) ? $imageProperties[1] : 0
					);
				}

				if( \count( $inserts ) )
				{
					\IPS\Db::i()->insert( 'core_emoticons', $inserts );
				}
				
				$position = 0;
				foreach( array( 'like', 'thanks', 'haha', 'confused', 'sad' ) AS $reaction )
				{
					$fileObj = \IPS\File::create( 'core_Reaction', "react_{$reaction}.png", file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/reaction/react_{$reaction}.png" ), 'reactions', FALSE, NULL, FALSE );
					$id = \IPS\Db::i()->insert( 'core_reactions', array(
						'reaction_value'	=> ( \in_array( $reaction, array( 'confused', 'sad' ) ) ) ? 0 : 1,
						'reaction_icon'		=> (string) $fileObj,
						'reaction_position'	=> ++$position,
						'reaction_enabled'	=> 1,
					) );
					\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $id, ucwords( $reaction ) );
				}
			}
		} );
	}
}
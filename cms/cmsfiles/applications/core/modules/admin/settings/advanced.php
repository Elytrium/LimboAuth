<?php
/**
 * @brief		Advanced Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 June 2013
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Advanced Settings
 */
class _advanced extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage' );
		parent::execute();
	}
	
	/**
	 * Manage: Works out tab and fetches content
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Work out output */
		\IPS\Request::i()->tab = isset( \IPS\Request::i()->tab ) ? \IPS\Request::i()->tab : 'settings';
		if ( $pos = mb_strpos( \IPS\Request::i()->tab, '-' ) )
		{
			$tabMethod			= '_manage' . mb_ucfirst( mb_substr( \IPS\Request::i()->tab, 0, $pos ) );
			$activeTabContents	= $this->$tabMethod( mb_substr( \IPS\Request::i()->tab, $pos + 1 ) );
		}
		else
		{
			$tabMethod			= '_manage' . mb_ucfirst( \IPS\Request::i()->tab );
			$activeTabContents	= $this->$tabMethod();
		}
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
			return;
		}
		
		/* Build tab list */
		$tabs = array();
		$tabs['settings'] = 'server_environment';
		if ( \IPS\Settings::i()->use_friendly_urls and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'advanced_manage_furls' ) )
		{
			$tabs['furl']  = 'furls';
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'settings', 'datastore' ) and !\IPS\CIC )
		{
			$tabs['datastore'] = 'data_store';
		}
		$tabs['pageoutput'] = 'page_output';
			
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_advanced');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, \IPS\Request::i()->tab, $activeTabContents, \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced" ) );
	}

	/**
	 * Data store management
	 *
	 * @return	string
	 */
	protected function _manageDatastore()
	{
		/* Are we just checking the constants? */
		if ( isset( \IPS\Request::i()->checkConstants ) )
		{
			$cacheConfig = \IPS\CACHE_CONFIG;
			
			if ( \IPS\Request::i()->store_method === 'Redis' or \IPS\Request::i()->cache_method === 'Redis' )
			{
				$cacheConfig = \IPS\REDIS_CONFIG;
			}
			
			/* If we've changed anything, explain to the admin they have to update */
			if ( \IPS\Request::i()->store_method !== \IPS\STORE_METHOD or \IPS\Request::i()->store_config !== \IPS\STORE_CONFIG or \IPS\Request::i()->cache_method !== \IPS\CACHE_METHOD or \IPS\Request::i()->cache_config !== $cacheConfig )
			{
				$downloadUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=downloadDatastoreConstants' )->setQueryString( array( 'store_method' => \IPS\Request::i()->store_method, 'store_config' => \IPS\Request::i()->store_config, 'cache_method' => \IPS\Request::i()->cache_method, 'cache_config' => \IPS\Request::i()->cache_config ) );
				$checkUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=datastore&checkConstants=1' )->setQueryString( array( 'store_method' => \IPS\Request::i()->store_method, 'store_config' => \IPS\Request::i()->store_config, 'cache_method' => \IPS\Request::i()->cache_method, 'cache_config' => \IPS\Request::i()->cache_config ) )->csrf();
				return \IPS\Theme::i()->getTemplate( 'settings' )->dataStoreChange( $downloadUrl, $checkUrl, TRUE );
			}
			/* Otherwise just log and redirect */
			else
			{
				/* Clear it */
				\IPS\Data\Cache::i()->clearAll();
				\IPS\Data\Store::i()->clearAll();

				/* Log and redirect */
				\IPS\Session::i()->log( 'acplogs__datastore_settings_updated' );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=datastore' ), 'saved' );
			}
		}
		
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'datastore' );
		
		/* Init */
		$form = new \IPS\Helpers\Form;
		$form->attributes['data-controller'] = 'core.admin.system.settings';

		/* If the datastore isn't working properly, show a message */
		if( !\IPS\Data\Store::testStore() OR \IPS\Db::i()->select( 'COUNT(*)', 'core_log', array( '`category`=? AND `time`>?', 'datastore', \IPS\DateTime::create()->sub( new \DateInterval( 'PT1H' ) )->getTimestamp() ) )->first() >= 10 )
		{
			/* Have we just recently updated the configuration? If so, ignore this warning for 24 hours */
			if( \IPS\Settings::i()->last_data_store_update < \IPS\DateTime::create()->sub( new \DateInterval( 'PT24H' ) )->getTimestamp() )
			{
				$form->addMessage( 'dashboard_datastore_broken_settings', 'ipsMessage ipsMessage_warning' );
			}
		}

		/* Cold storage */
		$extra = array();
		$toggles = array();
		$disabled = array();
		$storeConfigurationFields = array();
		$options = [];
		foreach( \IPS\Data\Store::availableMethods() AS $key => $class )
		{
			$options[ $key ] = 'datastore_method_' . $key;
		}

		$existingConfiguration = json_decode( \IPS\STORE_CONFIG, TRUE );
		foreach ( $options as $k => $v )
		{
			$class = 'IPS\Data\Store\\' . $k;
			if ( !$class::supported() )
			{
				$disabled[] = $k;
				\IPS\Member::loggedIn()->language()->words["datastore_method_{$k}_desc"] = \IPS\Member::loggedIn()->language()->addToStack('datastore_method_disableddesc', FALSE, array( 'sprintf' => array( $k ) ) );
			}
			else
			{
				foreach ( $class::configuration( $k === \IPS\STORE_METHOD ? $existingConfiguration : array() ) as $inputKey => $input )
				{
					if ( !$input->htmlId )
					{
						$input->htmlId = 'id_' . $input->name;
					}
					
					$extra[] = $input;
					$toggles[ $k ][] = $input->htmlId;
					$storeConfigurationFields[ $k ][ $inputKey ] = $input->name;
				}
			}
		}
		$form->add( new \IPS\Helpers\Form\Radio( 'datastore_method', \IPS\STORE_METHOD, TRUE, array(
			'options'	=> $options,
			'toggles'	=> $toggles,
			'disabled'	=> $disabled,
		), function( $val ){
			if( $val === 'Redis' AND \IPS\Request::i()->cache_method !== 'Redis' )
			{
				throw new \DomainException( 'datastore_redis_cache' );
			}
		} ) );
		foreach ( $extra as $input )
		{
			$form->add( $input );
		}

		/* Cache */
		$extra = array();
		$toggles = array( 'Redis' => array( 'redis_enabled' ) );
		$disabled = array();
		$cacheConfigurationFields = array();
		$options = [];
		foreach( \IPS\Data\Cache::availableMethods() AS $key => $class )
		{
			$options[ $key ] = 'datastore_method_' . $key;
		}
		
		$cacheConfig = \IPS\CACHE_CONFIG;
		
		if ( \defined( '\IPS\REDIS_CONFIG' ) and ( \IPS\STORE_METHOD == 'Redis' OR \IPS\CACHE_METHOD == 'Redis' ) )
		{
			$cacheConfig = \IPS\REDIS_CONFIG;
		}
		
		$existingConfiguration = json_decode( $cacheConfig, TRUE );
		
		foreach ( $options as $k => $v )
		{
			$class = \IPS\Data\Cache::availableMethods()[ $k ];
			if ( !$class::supported() )
			{
				$disabled[] = $k;
				\IPS\Member::loggedIn()->language()->words["datastore_method_{$k}_desc"] = \IPS\Member::loggedIn()->language()->addToStack('datastore_method_disableddesc', FALSE, array( 'sprintf' => array( $k ) ) );
			}
			else
			{				
				foreach ( $class::configuration( $k === \IPS\CACHE_METHOD ? $existingConfiguration : array() ) as $inputKey => $input )
				{
					if ( !$input->htmlId )
					{
						$input->htmlId = 'id_' . $input->name;
					}
					
					$extra[] = $input;
					$toggles[ $k ][] = $input->htmlId;
					$cacheConfigurationFields[ $k ][ $inputKey ] = $input->name;
				}
			}
		}
		$form->add( new \IPS\Helpers\Form\Radio( 'cache_method', \IPS\CACHE_METHOD, TRUE, array(
			'options'	=> $options,
			'toggles'	=> $toggles,
			'disabled'	=> $disabled,
		) ) );
		foreach ( $extra as $input )
		{
			$form->add( $input );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'redis_enabled', \IPS\REDIS_ENABLED, FALSE, array(), NULL, NULL, NULL, 'redis_enabled' ) );

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Work out configuration */
			$storeConfiguration = array();
			if ( isset( $storeConfigurationFields[ $values['datastore_method'] ] ) )
			{
				foreach ( $storeConfigurationFields[ $values['datastore_method'] ] as $k => $fieldName )
				{
					$storeConfiguration[ $k ] = $values[ $fieldName ];
				}
			}
			$cacheConfiguration = array();
			if ( isset( $cacheConfigurationFields[ $values['cache_method'] ] ) )
			{
				foreach ( $cacheConfigurationFields[ $values['cache_method'] ] as $k => $fieldName )
				{
					$cacheConfiguration[ $k ] = $values[ $fieldName ];
				}
			}
			
			/* If we've changed anything, explain to the admin they have to update */
			if ( $values['datastore_method'] !== \IPS\STORE_METHOD or str_replace( '\\/', '/', json_encode( $storeConfiguration ) ) !== \IPS\STORE_CONFIG or $values['cache_method'] !== \IPS\CACHE_METHOD or json_encode( $cacheConfiguration ) !== \IPS\CACHE_CONFIG or \IPS\REDIS_ENABLED != (boolean) $values['redis_enabled'] )
			{
				/* Connect to cache engine if we can and invalidate any existing caches */
				try
				{
					$classname = 'IPS\Data\Cache\\' . $values['cache_method'];
					
					if ( $classname::supported() )
					{
						$instance = new $classname( $cacheConfiguration );
						$instance->clearAll();
					}
				}
				catch( \Exception $e ){}

				/* Invalidate any existing datastore records */
				try
				{
					$classname =  'IPS\Data\Store\\' . $values['datastore_method'];
					$instance = new $classname( $storeConfiguration );
					$instance->clearAll();
				}
				catch( \Exception $e ){}

				/* Reset the last update flag for data store */
				\IPS\Settings::i()->changeValues( array( 'last_data_store_update' => time() ) );
				\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'dataStorageBroken' );
				
				/* Display */
				$downloadUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=downloadDatastoreConstants' )->setQueryString( array( 'store_method' => $values['datastore_method'], 'store_config' => str_replace( '\\/', '/', json_encode( $storeConfiguration ) ), 'cache_method' => $values['cache_method'], 'cache_config' => json_encode( $cacheConfiguration ), 'redis_enabled' => $values['redis_enabled'] ) );
				$checkUrl = \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=datastore&checkConstants=1' )->setQueryString( array( 'store_method' => $values['datastore_method'], 'store_config' => str_replace( '\\/', '/', json_encode( $storeConfiguration ) ), 'cache_method' => $values['cache_method'], 'cache_config' => json_encode( $cacheConfiguration ), 'redis_enabled' => $values['redis_enabled'] ) )->csrf();
				return \IPS\Theme::i()->getTemplate( 'settings' )->dataStoreChange( $downloadUrl, $checkUrl );
			}
			/* Otherwise just log and redirect */
			else
			{
				\IPS\Session::i()->log( 'acplogs__datastore_settings_updated' );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=datastore' ), 'saved' );
			}
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('admin_system.js', 'core', 'admin') );
		
		return $form;
	}
	
	/**
	 * Download constants.php
	 *
	 * @return	void
	 */
	protected function downloadDatastoreConstants()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'datastore' );

		$output = "<?php\n\n";
		foreach ( \IPS\IPS::defaultConstants() as $k => $v )
		{
			$val = \constant( 'IPS\\' . $k );

			if ( $val !== $v and !\in_array( $k, array( 'STORE_METHOD', 'STORE_CONFIG', 'CACHE_METHOD', 'CACHE_CONFIG', 'CACHE_PAGE_TIMEOUT', 'SUITE_UNIQUE_KEY', 'READ_WRITE_SEPARATION', 'REDIS_ENABLED', 'REDIS_CONFIG', 'REPORT_EXCEPTIONS' ) ) )
			{
				$output .= "\\define( '{$k}', " . var_export( $val, TRUE ) . " );\n";
			}
		}

		/* We have to treat READ_WRITE_SEPARATION special because admin/index.php always disables it */
		if( \file_exists( \IPS\ROOT_PATH . '/constants.php' ) )
		{
			$constants = \file_get_contents( \IPS\ROOT_PATH . '/constants.php' );

			/* Did we sniff the constant out with a quick check? */
			if( mb_strpos( $constants, 'READ_WRITE_SEPARATION' ) )
			{
				preg_match( "/define\(\s*?['\"]READ_WRITE_SEPARATION[\"']\s*?,\s*?(.+?)\);/i", $constants, $matches );

				if( isset( $matches[1] ) )
				{
					$output .= "\\define( 'READ_WRITE_SEPARATION', " . $matches[1] . " );\n";
				}
			}
		}
		
		$output .= "\n";
		$output .= "\\define( 'REDIS_ENABLED', " . var_export( (boolean) \IPS\Request::i()->redis_enabled, TRUE ) . " );\n";
		$output .= "\\define( 'STORE_METHOD', " . var_export( \IPS\Request::i()->store_method, TRUE ) . " );\n";
		$output .= "\\define( 'STORE_CONFIG', " . var_export( \IPS\Request::i()->store_config, TRUE ) . " );\n";
		$output .= "\\define( 'CACHE_METHOD', " . var_export( \IPS\Request::i()->cache_method, TRUE ) . " );\n";
		
		if ( \IPS\Request::i()->store_method === 'Redis' or \IPS\Request::i()->cache_method === 'Redis' )
		{
			$output .= "\\define( 'REDIS_CONFIG', " . var_export( \IPS\Request::i()->cache_config, TRUE ) . " );\n";
		}
		else
		{
			$output .= "\\define( 'CACHE_CONFIG', " . var_export( \IPS\Request::i()->cache_config, TRUE ) . " );\n";
		}
		
		$output .= "\\define( 'SUITE_UNIQUE_KEY', " . var_export( mb_substr( md5( mt_rand() ), 10, 10 ), TRUE ) . " );\n"; // Regenerate the unique key so there's no conflicts
		$output .= "\n\n\n";
				
		\IPS\Output::i()->sendOutput( $output, 200, 'text/x-php', array( 'Content-Disposition' => 'attachment; filename=constants.php' ) );
	}

	/**
	 * Get setting to configure tasks
	 *
	 * @param	\IPS\Form	$form	Form to add the setting to
	 * @return	void
	 */
	public static function taskSetting( $form )
	{
		/* Generate a cron key if we don't have one */
		if ( !\IPS\Settings::i()->task_cron_key )
		{
			\IPS\Settings::i()->changeValues( array( 'task_cron_key' => md5( mt_rand() ) ) );
		}
		
		/* Sort stuff out for the cron setting */
		if ( \IPS\CIC )
		{
			$options = array( 'options' => array(
				'ips' => 'task_method_ips'
			) );
		}
		else
		{
			$cronCommand = PHP_BINDIR . '/php -d memory_limit=-1 -d max_execution_time=0 ' . \IPS\ROOT_PATH . '/applications/core/interface/task/task.php ' . \IPS\Settings::i()->task_cron_key;
			try
			{
				\IPS\Member::loggedIn()->language()->words['task_method_cron_warning'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'task_method_cron_warning', FALSE ), $cronCommand );
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Member::loggedIn()->language()->words['task_method_cron_warning'] = $cronCommand;
			}
			
			$webCronUrl = (string) \IPS\Http\Url::internal( 'applications/core/interface/task/web.php?key=' . \IPS\Settings::i()->task_cron_key, 'none' );
			try
			{
				\IPS\Member::loggedIn()->language()->words['task_method_web_warning'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'task_method_web_warning', FALSE ), $webCronUrl );
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Member::loggedIn()->language()->words['task_method_web_warning'] = $webCronUrl;
			}
			
			$options = array( 
				'options'	=> array(
					'normal'	=> 'task_method_normal',
					'cron'		=> 'task_method_cron',
					'web'		=> 'task_method_web',
				),
				'toggles' => array( 
					'cron' => array( 'task_use_cron_cron_warning' ), 
					'web' => array( 'task_use_cron_web_warning' )
				) 
			);
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'task_use_cron', \IPS\CIC ? 'ips' : \IPS\Settings::i()->task_use_cron, FALSE, $options, function ( $val )
		{
			$cronFile = \IPS\ROOT_PATH . '/applications/core/interface/task/task.php';
			if ( $val == 'cron' and ( mb_strtoupper( mb_substr( PHP_OS, 0, 3 ) ) !== 'WIN' AND !is_executable( $cronFile ) ) )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('task_use_cron_executable', FALSE, array( 'sprintf' => array( $cronFile ) ) ) );
			}
		}, NULL, NULL, 'task_use_cron' ) );
	}
	
	/**
	 * Settings
	 *
	 * @return	string
	 */
	protected function _manageSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_server' );
		
		/* Build and show form */
		$form = new \IPS\Helpers\Form;
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_settings.js', 'core', 'admin' ) );
		$form->attributes['data-controller'] = 'core.admin.settings.advanced';
		$form->hiddenValues['rebuildPosts'] = \IPS\Request::i()->rebuildPosts ?: 0;
		$form->addHeader('task_manager');
		
		static::taskSetting( $form );

		$form->addHeader( 'security_header_ips' );
		if ( !\IPS\CIC )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'xforward_matching', \IPS\Settings::i()->xforward_matching, FALSE ) );
		}
		$form->add( new \IPS\Helpers\Form\YesNo( 'match_ipaddress', \IPS\Settings::i()->match_ipaddress, FALSE ) );
		if( \IPS\BYPASS_ACP_IP_CHECK === TRUE )
		{
			\IPS\Member::loggedIn()->language()->words['match_ipaddress_warning'] = \IPS\Member::loggedIn()->language()->addToStack('ip_override_warn');
		}
		$form->add( new \IPS\Helpers\Form\Radio( 'clickjackprevention', \IPS\Settings::i()->clickjackprevention, FALSE, array(
			'options'	=> array(
				'xframe'	=> 'clickjackprevention_xframe',
				'csp'		=> 'clickjackprevention_csp',
				'none'		=> 'clickjackprevention_none',
			),
			'toggles'	=> array(
				'csp'		=> array( 'csp_header' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'csp_header', \IPS\Settings::i()->csp_header, FALSE, array( 'placeholder' => "default-src *; frame-ancestors 'self' *.example.com" ), NULL, NULL, NULL, 'csp_header' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'referrer_policy_header', \IPS\Settings::i()->referrer_policy_header, FALSE, array(
			'options'	=> array(
				'0'		=> 'referrerpolicy_disabled',
				'1'		=> 'referrerpolicy_acp_only',
				'2'		=> 'referrerpolicy_enabled',
			)
		) ) );

		$form->addHeader('performance_settings');
		$form->add( new \IPS\Helpers\Form\Interval( 'widget_cache_ttl', ( isset( \IPS\Settings::i()->widget_cache_ttl ) ) ? \IPS\Settings::i()->widget_cache_ttl : 60, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::SECONDS, 'min' => 60 ), NULL, \IPS\Member::loggedIn()->language()->addToStack('for'), NULL ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'lazy_load_enabled', \IPS\Settings::i()->lazy_load_enabled, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'auto_polling_enabled', \IPS\Settings::i()->auto_polling_enabled, FALSE ) );
		
		if ( ! \IPS\CIC )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'theme_disk_cache_templates', \IPS\Settings::i()->theme_disk_cache_templates, FALSE, array( 'togglesOn' => array( 'theme_disk_cache_path' ) ) ) );	
			$form->add( new \IPS\Helpers\Form\Text( 'theme_disk_cache_path', \IPS\Settings::i()->theme_disk_cache_path ? \IPS\Settings::i()->theme_disk_cache_path : \IPS\ROOT_PATH . '/uploads', FALSE, array(), function( $val )
			{
				if ( \IPS\Request::i()->theme_disk_cache_templates_checkbox )
				{
					if ( ! is_dir( \IPS\Request::i()->theme_disk_cache_path ) or ! is_readable( \IPS\Request::i()->theme_disk_cache_path ) or ! is_writable( \IPS\Request::i()->theme_disk_cache_path ) )
					{
						throw new \InvalidArgumentException( 'theme_disk_cache_path_wrong' );
					}
				}
			}, NULL, NULL, 'theme_disk_cache_path' ) );
		}
		
		if ( $values = $form->values() )
		{
			/* Run the rebuild posts routine */
			if( $values['rebuildPosts'] )
			{
				/* Remove any existing rebuilds */
				\IPS\Db::i()->delete( 'core_queue', \IPS\Db::i()->in( '`key`', array( 'RebuildLazyLoad', 'RebuildLazyLoadNonContent' ) ) );

				/* Unset task datastore */
				unset( \IPS\Data\Store::i()->currentLazyLoadRebuild );

				$enableDisable = $values['lazy_load_enabled'] ? TRUE : FALSE;
				foreach ( \IPS\Content::routedClasses( FALSE, TRUE ) as $class )
				{
					if( isset( $class::$databaseColumnMap['content'] ) )
					{
						try
						{
							\IPS\Task::queue( 'core', 'RebuildLazyLoad', array( 'class' => $class, 'status' => $enableDisable ), 4 );
						}
						catch( \OutOfRangeException $ex ) { }
					}
				}

				foreach( \IPS\Application::allExtensions( 'core', 'EditorLocations', FALSE, NULL, NULL, TRUE, TRUE ) as $_key => $extension )
				{
					if( method_exists( $extension, 'rebuildLazyLoad' ) )
					{
						\IPS\Task::queue( 'core', 'RebuildLazyLoadNonContent', array( 'extension' => $_key, 'status' => $enableDisable ), 4 );
					}
				}
			}

			unset( $values['rebuildPosts'] );

			$form->saveAsSettings( $values );			
			\IPS\Session::i()->log( 'acplogs__advanced_server_edited' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=settings' ), 'saved' );
		}
		return $form;
	}
	
	/**
	 * Settings
	 *
	 * @return	string
	 */
	protected function _managePageoutput()
	{
		/* Build and show form */
		$form = new \IPS\Helpers\Form;
			
		$form->add( new \IPS\Helpers\Form\Codemirror( 'custom_body_code', \IPS\Settings::i()->custom_body_code, FALSE, array('height' => 150, 'mode' => 'javascript'), NULL, NULL, NULL, 'custom_body_code' ) );	
		$form->add( new \IPS\Helpers\Form\Codemirror( 'custom_page_view_js', \IPS\Settings::i()->custom_page_view_js, FALSE, array('height' => 150, 'mode' => 'javascript'), NULL, NULL, NULL, 'custom_page_view_js' ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );			
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=pageoutput' ), 'saved' );
		}
		return $form;
	}
	
	/**
	 * Tasks
	 *
	 * @return	void
	 */
	protected function tasks()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_tasks' );
		
		$table = new \IPS\Helpers\Table\Db( 'core_tasks', \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=tasks' ), array( array( '(p.plugin_enabled=1 OR a.app_enabled=1)' ) ) );
		$table->joins = array(
			array( 'select' => 'a.app_enabled', 'from' => array( 'core_applications', 'a' ), 'where' => "a.app_directory=app" ),
			array( 'select' => 'p.plugin_enabled', 'from' => array( 'core_plugins', 'p' ), 'where' => "p.plugin_id=plugin" )
		);
		$table->langPrefix = 'task_manager_';
		$table->include = array( 'app', 'key', 'frequency', 'next_run', 'last_run' );
		$table->mainColumn = 'key';
		$table->quickSearch = 'key';

		$table->primarySortBy = 'enabled';
		$table->primarySortDirection = 'DESC';
		
		$table->sortBy = $table->sortBy ?: 'next_run';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		$table->noSort	= array( 'frequency' );
		
		$table->quickSearch = function( $val )
		{
			$matches = \IPS\Member::loggedIn()->language()->searchCustom( 'task__', $val, TRUE );
			if ( \count( $matches ) )
			{
				return array( '(' . \IPS\Db::i()->in( '`key`', array_keys( $matches ) ) . " OR `key` LIKE '%{$val}%')" );
			}
			else
			{
				return array( "`key` LIKE '%" . \IPS\Db::i()->escape_string( $val ) . "%'" );
			}
		};
		
		$table->parsers = array(
			'app'	=> function( $val, $row )
			{
				try
				{
					return $val ? \IPS\Application::load( $val )->_title : \IPS\Plugin::load( $row['plugin'] )->name;
				}
				catch ( \UnexpectedValueException $e )
				{
					return NULL;
				}
				catch ( \OutOfRangeException $e )
				{
					return NULL;
				}
			},
			'key'	=> function( $val )
			{
				$langKey = 'task__' . $val;
				if ( \IPS\Member::loggedIn()->language()->checkKeyExists( $langKey ) )
				{
					return $val . '<br><span class="ipsType_light">' . \IPS\Member::loggedIn()->language()->addToStack( $langKey ) . '</span>';
				}
				return $val;
			},
			'frequency' => function ( $v )
			{
				$interval = new \DateInterval( $v );
				$return = array();
				foreach ( array( 'y' => 'years', 'm' => 'months', 'd' => 'days', 'h' => 'hours', 'i' => 'minutes', 's' => 'seconds' ) as $k => $v )
				{
					if ( $interval->$k )
					{
						$return[] = \IPS\Member::loggedIn()->language()->addToStack( 'every_x_' . $v, FALSE, array( 'pluralize' => array( $interval->format( '%' . $k ) ) ) );
					}
				}
				
				return \IPS\Member::loggedIn()->language()->formatList( $return );
			},
			'next_run' => function ( $v, $row )
			{
				if ( !$row['enabled'] )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('task_manager_disabled');
				}
				elseif ( $row['running'] )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('task_manager_running');
				}
				else
				{
					return (string) \IPS\DateTime::ts( $row['next_run'] ?: time() );
				}
			},
			'last_run' => function ( $v, $row )
			{
				return (string) $row['last_run'] ?  \IPS\DateTime::ts( $row['last_run'] ) : \IPS\Member::loggedIn()->language()->addToStack( 'never' );
			}
		);
		
		$table->rowButtons = function( $row )
		{
			if ( $row['running'] )
			{
				$return = array( 'unlock' => array(
					'icon'	=> 'unlock',
					'title'	=> 'task_manager_unlock',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=unlockTask&id={$row['id']}" )->csrf()
				) );
			}
			else
			{
				$return = array( 'run' => array(
					'icon'	=> 'play-circle',
					'title'	=> 'task_manager_run',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=runTask&id={$row['id']}" )->csrf()
				) );
			}
			$return['logs'] = array(
				'icon'	=> 'search',
				'title'	=> 'task_manager_logs',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=taskLogs&id={$row['id']}" )
			);
			return $return;
		};
		
		/* Add a button for settings */
		\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
						'title'		=> 'settings',
						'icon'		=> 'cog',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=taskSettings' ),
						'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
				),
		);
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('task_manager');
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function taskSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_tasks' );

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_tasks', \IPS\Settings::i()->prune_log_tasks, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_tasks' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__tasklog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=tasks' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('task_settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'task_settings', $form, FALSE );
	}
	
	/**
	 * Run Task
	 *
	 * @return	void
	 */
	protected function runTask()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_tasks' );
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('task_manager');
		
		try
		{
			$task = \IPS\Task::load( \IPS\Request::i()->id );
			if ( $task->running and !\IPS\IN_DEV )
			{
				\IPS\Output::i()->error( 'task_manager_locked', '2C124/2', 403, '' );
			}
			
			$output = $task->run();
			
			if ( $output === NULL )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=tasks' ), 'task_manager_ran' );
			}
			else
			{
				if ( \is_array( $output ) )
				{
					$output = implode( "\n", array_map( array( \IPS\Member::loggedIn()->language(), 'addToStack' ), $output ) );
				}
				elseif ( !\is_string( $output ) and !\is_numeric( $output ) )
				{
					$output = var_export( $output, TRUE );
				}
				else
				{
					$output = \IPS\Member::loggedIn()->language()->addToStack( $output, FALSE );
				}
				
				\IPS\Output::i()->bypassCsrfKeyCheck = true;
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'advancedsettings' )->taskResult( TRUE, $output, $task->id );
			}
		}
		catch ( \IPS\Task\Exception $e )
		{
			\IPS\Output::i()->bypassCsrfKeyCheck = true;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'advancedsettings' )->taskResult( FALSE, \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage(), FALSE ), $task->id );
		}
		catch ( \RuntimeException $e )
		{
			\IPS\Output::i()->error( 'task_running_error', '2C124/7', 404, '', array(), \IPS\IPS::getExceptionDetails( $e ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'task_class_not_found', '2C124/1', 404, '' );
		}
		catch( \Exception $e )
		{
			\IPS\Log::log( $e, 'uncaught_exception' );
			\IPS\Output::i()->error( $e->getMessage() ?: 'task_running_error', '4C124/6', 404, '' );
		}
	}
	
	/**
	 * Unlock Task
	 *
	 * @return	void
	 */
	protected function unlockTask()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_tasks' );
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			\IPS\Task::load( \IPS\Request::i()->id )->unlock();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=tasks' ), 'task_manager_unlocked' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C124/3', 404, '' );
		}
	}
	
	/**
	 * View task logs
	 *
	 * @return	void
	 */
	protected function taskLogs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_tasks' );
		
		try
		{
			$task = \IPS\Task::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C124/4', 404, '' );
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_tasks_log', \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=taskLogs&id={$task->id}" ), array( 'task=?', $task->id ) );
		$table->langPrefix = 'task_manager_';
		
		$table->include = array( 'time', 'log' );
		$table->parsers = array(
			'time'	=> function( $val )
			{
				return (string) \IPS\DateTime::ts( $val );
			},
			'log'	=> function ( $val, $row )
			{
				$val = json_decode( $val );
				if ( \is_array( $val ) )
				{
					$val = implode( "\n", array_map( array( \IPS\Member::loggedIn()->language(), 'addToStack' ), $val ) );
				}
				elseif ( !\is_string( $val ) and !\is_numeric( $val ) )
				{
					$val = var_export( $val, TRUE );
				}
				else
				{
					if( $decoded = json_decode( $val ) )
					{
						$val = \IPS\Member::loggedIn()->language()->addToStack( array_shift( $decoded ), FALSE, array( 'sprintf' => $decoded ) );
					}
					else
					{
						$val = \IPS\Member::loggedIn()->language()->addToStack( $val, FALSE );
					}
				}
				return $row['error'] ? \IPS\Theme::i()->getTemplate( 'global' )->message( $val, 'error' ) : $val;
			}
		);
		
		$table->sortBy = $table->sortBy ?: 'time';
		
		$table->quickSearch = 'log';
		$table->advancedSearch = array(
			'time'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'log'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT
		);

		\IPS\Output::i()->title = $task->key;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->message( 'tasklogs_blurb', 'info' ) . $table;
	}
	
	/**
	 * FURLs
	 *
	 * @return string
	 */
	protected function _manageFurl()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_furls' );
		
		if ( \IPS\IN_DEV and !\IPS\DEV_USE_FURL_CACHE )
		{
			\IPS\Output::i()->error( 'furl_in_dev', '1C124/5', 403, '' );
		}

		$definition = \IPS\Http\Url\Friendly::furlDefinition();
		$customConfiguration = \IPS\Settings::i()->furl_configuration ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();

		$table = new \IPS\Helpers\Table\Custom( $definition, \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=furl' ) );
		$table->include = array( 'friendly', 'real' );
		$table->limit   = 100;
		$table->langPrefix = 'furl_';
		$table->mainColumn = 'real';
		$table->parsers = array(
			'friendly'	=> function( $val )
			{
				$val = preg_replace( '/{[@#](.+?)}/', '<strong><em>$1</em></strong>', $val );
				$val = preg_replace( '/{\?(\d+?)?}/', '<em>??</em>', $val );
				return "<span class='ipsType_light ipsResponsive_hideTablet'>" . \IPS\Settings::i()->base_url . ( \IPS\Settings::i()->htaccess_mod_rewrite ? '' : 'index.php?/' ) . "</span>{$val}";
			},
			'real' => function( $val, $row )
			{
				preg_match_all( '/{([@#])(.+?)}/', $row['friendly'], $matches );
				if ( !empty( $matches[0] ) )
				{
					foreach ( $matches[0] as $i => $m )
					{
						$val .= '&' . $matches[ 2 ][ $i ] . '=<strong><em>' . ( $matches[ 1 ][ $i ] == '#' ? '123' : 'abc' ) . '</em></strong>';
					}
					$val .= '</strong>';
				}
				
				return "<span class='ipsType_light ipsResponsive_hideTablet'>" . \IPS\Settings::i()->base_url . "index.php?</span>{$val}";
			}
		);
		$table->quickSearch = 'friendly';
		$table->advancedSearch = array(
			'friendly'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'real'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT
		);
		
		$table->rootButtons = array(
			'add'		=> array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=furlForm' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add') )
			)
		);

		if( $customConfiguration AND \count( $customConfiguration ) )
		{
			$table->rootButtons['revert'] = array(
				'icon'	=> 'undo',
				'title'	=> 'furl_revert',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&do=furlRevert' )->csrf(),
				'data'	=> array( 'confirm' => '' )
			);
		}

		$table->rowButtons = function( $row, $k ) use ( $definition, $customConfiguration )
		{
			$return = array(
				'edit'	=> array(
					'icon'	=> 'pencil',
					'title'	=> 'edit',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=furlForm&key={$k}" ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit') )
				)
			);

			if( isset( $definition[ $k ]['custom'] ) OR isset( $customConfiguration[ $k ] ) )
			{
				$return['revert'] = array(
					'icon'	=> 'undo',
					'title'	=> 'revert',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=settings&controller=advanced&do=furlDelete&key={$k}" )->csrf(),
					'data'	=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('revert_confirm') )
				);
			}

			return $return;
		};

		return ( \IPS\Request::i()->advancedSearchForm ? '' : \IPS\Theme::i()->getTemplate('global')->message( 'furl_warning', 'warning' ) ) . $table;
	}
	
	/**
	 * Add/Edit FURL
	 *
	 * @return	void
	 */
	protected function furlForm()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_furls' );
		
		$current	= NULL;
		$config		= \IPS\Http\Url\Friendly::furlDefinition();
		if ( \IPS\Request::i()->key )
		{
			$current = ( isset( $config[ \IPS\Request::i()->key ] ) ) ? $config[ \IPS\Request::i()->key ] : NULL;
		}

		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'furl_friendly', $current ? $current['friendly'] : '', FALSE, array( 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('furl_friendly_placeholder') ), function( $val )
		{
			if( mb_substr( $val, 0, 3 ) == '{?}' )
			{
				throw new \DomainException( 'furl_too_greedy' );
			}
		},
		\IPS\Settings::i()->base_url . ( \IPS\Settings::i()->htaccess_mod_rewrite ? '' : 'index.php?/' ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'furl_real', $current ? $current['real'] : '', FALSE, array(), NULL, \IPS\Settings::i()->base_url . 'index.php?' ) );
		
		if ( $values = $form->values() )
		{
			$furl = \IPS\Settings::i()->furl_configuration ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();
			
			$currentDefinition = \IPS\Http\Url\Friendly::furlDefinition();
			$appTopLevel = NULL;
			$appIsDefault = FALSE;
			$alias = NULL;
			$verify = NULL;
			$seoPagination = NULL;
			$friendly = $values['furl_friendly'];
			if ( \IPS\Request::i()->key )
			{
				if ( isset( $currentDefinition[ \IPS\Request::i()->key ]['alias'] ) )
				{
					$alias = $currentDefinition[ \IPS\Request::i()->key ]['alias'];
				}
				
				if ( isset( $currentDefinition[ \IPS\Request::i()->key ]['verify'] ) )
				{
					$verify = $currentDefinition[ \IPS\Request::i()->key ]['verify'];
				}

				if ( isset( $currentDefinition[ \IPS\Request::i()->key ]['seoPagination'] ) )
				{
					$seoPagination = $currentDefinition[ \IPS\Request::i()->key ]['seoPagination'];
				}

				if ( isset( $currentDefinition[ \IPS\Request::i()->key ]['with_top_level'] ) )
				{
					$appIsDefault = TRUE;
					$appTopLevel = mb_substr( $currentDefinition[ \IPS\Request::i()->key ]['with_top_level'], 0, -mb_strlen( $currentDefinition[ \IPS\Request::i()->key ]['friendly'] . '/' ) );
				}
				
				if ( isset( $currentDefinition[ \IPS\Request::i()->key ]['without_top_level'] ) )
				{
					$appIsDefault = FALSE;
					if ( $currentDefinition[ \IPS\Request::i()->key ]['without_top_level'] )
					{
						$appTopLevel = mb_substr( $currentDefinition[ \IPS\Request::i()->key ]['friendly'], 0, -mb_strlen( $currentDefinition[ \IPS\Request::i()->key ]['without_top_level'] . '/' ) );
						$friendly = rtrim( preg_replace( '/^' . preg_quote( $appTopLevel, '/' ) . '(\/|$)/', '', $friendly ), '/' );
					}
				}
			}

			$save = \IPS\Http\Url\Friendly::buildFurlDefinition( $friendly, $values['furl_real'], $appTopLevel, $appIsDefault, $alias, TRUE, $verify, NULL, $seoPagination );
															
			if ( \IPS\Request::i()->key )
			{
				$furl[ \IPS\Request::i()->key ] = $save;
			}
			else
			{
				ksort( $furl, SORT_NATURAL );
				$keys = array_keys( $furl );
				$lastKey = str_replace( 'key', '', end( $keys ) );
				$key = 'key' . ( (int)$lastKey + 1 );
				$furl[ $key ] = $save;
			}
			
			\IPS\Session::i()->log( 'acplogs__advanced_furl_edited' );
			
			$newValue = json_encode( $furl );
			\IPS\Settings::i()->changeValues( array( 'furl_configuration' => $newValue ) );
			
			/* Clear Sidebar Caches */
			\IPS\Widget::deleteCaches();

			/* Clear create menu caches */
			\IPS\Member::clearCreateMenu();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=furl' ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Delete FURL
	 *
	 * @return	void
	 */
	protected function furlDelete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_furls' );
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$furlDefinition = \IPS\Settings::i()->furl_configuration ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();
		if( isset( $furlDefinition[ \IPS\Request::i()->key ] ) )
		{
			unset( $furlDefinition[ \IPS\Request::i()->key ] );
			$newValue = json_encode( $furlDefinition );
			\IPS\Settings::i()->changeValues( array( 'furl_configuration' => $newValue ) );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
		}
		
		\IPS\Session::i()->log( 'acplogs__advanced_furl_deleted' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=furl' ), 'saved' );
	}
	
	/**
	 * Revert FURL customisation
	 *
	 * @return	void
	 */
	protected function furlRevert()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'advanced_manage_furls' );
		\IPS\Session::i()->csrfCheck();

		\IPS\Settings::i()->changeValues( array( 'furl_configuration' => NULL ) );
		unset( \IPS\Data\Store::i()->furl_configuration );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		\IPS\Session::i()->log( 'acplogs__advanced_furl_reverted' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=settings&controller=advanced&tab=furl' ), 'saved' );
	}
}
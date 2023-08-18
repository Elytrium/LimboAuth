<?php
/**
 * @brief		Installer/Upgrader Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Apr 2013
 */

namespace IPS\Dispatcher;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Installer/Upgrader Dispatcher
 */
class _Setup extends \IPS\Dispatcher
{
	/**
	 * @brief Controller Location
	 */
	public $controllerLocation = 'setup';

	/**
	 * @brief Install or upgrade
	 */
	public $setupLocation = 'install';

	/**
	 * @brief Step
	 */
	public $step = 1;
	
	/**
	 * Initiator
	 *
	 * @return	void
	 */
	public function init()
	{
	}

	/**
	 * Return valid steps
	 *
	 * @return	array
	 */
	protected function returnSteps()
	{
		if( $this->setupLocation == 'upgrade' )
		{
			return array(
				1	=> 'login',
				2	=> 'systemcheck',
				3	=> 'license',
				4	=> 'applications',
				5	=> 'customoptions',
				6	=> 'confirm',
				7	=> 'upgrade',
				8	=> 'done',
			);
		}
		else
		{
			return array(
				1	=> 'systemcheck',
				2	=> 'license',
				3	=> 'applications',
				4	=> 'serverdetails',
				5	=> 'admin',
				6	=> 'install',
				7	=> 'done',
			);
		}
	}
		
	/**
	 * Set location (install or upgrade)
	 *
	 * @param	string	$location	'install' or 'upgrade'
	 * @return	\IPS\Dispatcher\Setup
	 */
	public function setLocation( $location )
	{
		$this->setupLocation	= $location;
		$steps					= $this->returnSteps();
		$this->classname		= 'IPS\core\modules\setup\\' . $location . '\\' . ( isset( \IPS\Request::i()->controller ) ? \IPS\Request::i()->controller : $steps[1] );
		return $this;
	}
	
	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		/* Installer checks */
		if( $this->setupLocation == 'install' )
		{
			if ( !file_exists( \IPS\ROOT_PATH . '/conf_global.php' ) )
			{
				try
				{
					rename( \IPS\ROOT_PATH . '/conf_global.dist.php', \IPS\ROOT_PATH . '/conf_global.php' );
				}
				catch ( \Exception $e ) { }
				
				if ( !file_exists( \IPS\ROOT_PATH . '/conf_global.php' ) )
				{
					try
					{
						file_put_contents( \IPS\ROOT_PATH. '/conf_global.php', '' );
					}
					catch ( \Exception $e ) { }
				}
								
				if ( !file_exists( \IPS\ROOT_PATH . '/conf_global.php' ) )
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Member::loggedIn()->language()->addToStack( 'installation_error' ), '', true, \IPS\ROOT_PATH ), 500, 'text/html', array(), FALSE, FALSE, FALSE );
				}
			}
			
			require \IPS\ROOT_PATH . '/conf_global.php';
			if ( isset( $INFO ) and isset( $INFO['installed'] ) )
			{
				$upgradeUrl = new \IPS\Http\Url( \IPS\Settings::i()->base_url . \IPS\CP_DIRECTORY . '/upgrade/' );
				\IPS\Output::i()->redirect( $upgradeUrl );
			}
		}
		/* Upgrader Checks */
		else
		{
			if ( !file_exists( \IPS\SITE_FILES_PATH . '/conf_global.php' ) )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Member::loggedIn()->language()->addToStack('upgrade_error'), '', \IPS\Member::loggedIn()->language()->addToStack('no_conf_global'), \IPS\ROOT_PATH ), 200, 'text/html', array(), FALSE );
			}
			
			if ( \IPS\STORE_METHOD === 'FileSystem' )
			{
				$config = json_decode( \IPS\STORE_CONFIG, TRUE );
				$path = str_replace( '{root}', \IPS\ROOT_PATH, $config['path'] );
				if ( !is_dir( $path ) or !is_writable( $path ) )
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Member::loggedIn()->language()->addToStack('upgrade_error'), '', \IPS\Member::loggedIn()->language()->addToStack( 'create_conf_global', FALSE, array( 'sprintf' => array( $path ) ) ), \IPS\ROOT_PATH ), 200, 'text/html', array(), FALSE );
				}
			}

			require \IPS\SITE_FILES_PATH . '/conf_global.php';
			if ( !isset( $INFO ) )
			{
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Member::loggedIn()->language()->addToStack('upgrade_error'), '', \IPS\Member::loggedIn()->language()->addToStack('bad_conf_global'), \IPS\ROOT_PATH ), 200, 'text/html', array(), FALSE );
			}
			
			/* Fix languages if necessary */
			if( \IPS\Db::i()->checkForTable( 'core_sys_lang' ) AND !\IPS\Db::i()->checkForColumn( 'core_sys_lang', 'lang_order' ) )
			{
				\IPS\Lang::languages( \IPS\Db::i()->select( '*', 'core_sys_lang' ) );
			}
	
			/* Fix members if necessary */
			if( !\IPS\Db::i()->checkForTable( 'core_members' ) )
			{
				\IPS\Member::$databaseTable	= 'members';
				
				if ( !\IPS\Db::i()->checkForTable( 'core_groups' ) )
				{
					\IPS\Member\Group::$databaseTable = 'groups';
				}
				
				/* re-arrange 3.x mapping to 4.x code */
				$bits    = \IPS\Member::$bitOptions;
				unset( $bits['members_bitoptions']['members_bitoptions2'] );
				\IPS\Member::$bitOptions = $bits;				
			}
		}
		
		\IPS\Settings::i()->base_url = ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . mb_substr( $_SERVER['SCRIPT_NAME'], 0, -mb_strlen( \IPS\CP_DIRECTORY . '/' . $this->setupLocation . '/index.php' ) );

		$this->step	= array_search( \IPS\Request::i()->controller, $this->returnSteps() );

		session_name( ( \IPS\COOKIE_PREFIX !== NULL ) ? \IPS\COOKIE_PREFIX . 'IPSSessionSetup' : 'IPSSessionSetup' );
		$currentCookieParams = session_get_cookie_params();
		session_set_cookie_params( 
			86400 * 14, 
			( \IPS\COOKIE_PATH !== NULL ) ? \IPS\COOKIE_PATH : $currentCookieParams['path'],
			( \IPS\COOKIE_DOMAIN !== NULL ) ? \IPS\COOKIE_DOMAIN : $currentCookieParams['domain'],
			( \IPS\COOKIE_BYPASS_SSLONLY !== TRUE ) ? ( mb_substr( \IPS\Settings::i()->base_url, 0, 5 ) == 'https' ) : $currentCookieParams['secure'],
			TRUE
		);

		if( !@session_start() )
		{
			\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'session_no_good', FALSE, array( 'sprintf' => array( \IPS\IPS::$lastError ? \IPS\IPS::$lastError->getMessage() : NULL ) ) ), '4S109/5', 500, '' );
		}
		
		if( $this->classname != 'IPS\core\modules\setup\upgrade\done' and $this->setupLocation != 'install' and $this->step > 1 AND ( !isset( $_SESSION['uniqueKey'] ) OR $_SESSION['uniqueKey'] != \IPS\Request::i()->key ) )
		{
			\IPS\Output::i()->error( 'upgrade_session_error', '3S109/4', 403, '' );
		}

		/* Init class */
		if( !class_exists( $this->classname ) )
		{
			\IPS\Output::i()->error( 'page_doesnt_exist', '2S100/1', 404 );
		}
		$controller = new $this->classname; 
		if( !( $controller instanceof \IPS\Dispatcher\Controller ) )
		{
			\IPS\Output::i()->error( 'page_not_found', '5S100/3', 500, '' );
		}
		
		/* Execute */
		$controller->execute();
		
		/* If we're still here - output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html', array(), FALSE, FALSE, TRUE, FALSE );
		}
		else
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output ), 200, 'text/html', array(), FALSE, FALSE, TRUE, FALSE );
		}
	}

    /**
     * Destructor
     *
     * @return	void
     */
    public function __destruct()
    {
    }
}
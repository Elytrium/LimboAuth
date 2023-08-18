<?php
/**
 * @brief		Dispatcher
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dispatcher
 */
abstract class _Dispatcher
{
	/**
	 * @brief	Singleton Instance
	 */
	protected static $instance = NULL;
	
	/**
	 * Check if a dispatcher instance is available
	 *
	 * @return	static
	 * @note	This should be used sparingly, primarily for gateway scripts that do not need a dispatcher but still use the framework
	 */
	public static function hasInstance()
	{
		return ( static::$instance !== NULL );
	}

	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$class = \get_called_class();

			if( $class == 'IPS\\Dispatcher' )
			{
				throw new \RuntimeException( "Only subclasses of Dispatcher can be instantiated" );
			}
			
			static::$instance = new $class;
			
			if( static::$instance->controllerLocation != 'setup' )
			{
				$_redirect	= FALSE;
				
				if ( !file_exists( \IPS\SITE_FILES_PATH . '/conf_global.php' ) )
				{
					$_redirect	= TRUE;
				}
				else
				{
					require \IPS\SITE_FILES_PATH . '/conf_global.php';

					if( !isset( $INFO['sql_database'] ) )
					{
						$_redirect	= TRUE;
					}
					else if ( !isset( $INFO['installed'] ) OR !$INFO['installed'] )
					{
						/* This looks weird, but there was a period of time where "installed" was misspelled as "instaled" on Community in the Cloud after install finished. So, if that is present, assume we're okay. */
						if ( !isset( $INFO['instaled'] ) )
						{
							if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
							{
								header( "HTTP/1.0 503 Service Unavailable" );
							}
							else
							{
								header( "HTTP/1.1 503 Service Unavailable" );
							}
									
							require \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . '/install/installing.html';
							exit;
						}
					}
				}

				if( $_redirect === TRUE )
				{
					/* conf_global.php does not exist, forward to installer - we'll do this manually to avoid any code in Output.php that anticipates the installation already being complete (such as setting CSP header in __construct()) */
					$url	= ( \IPS\Request::i()->isSecure()  ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . rtrim( \dirname( $_SERVER['SCRIPT_NAME'] ), '/' );

					header( "HTTP/1.1 307 Temporary Redirect" );
					foreach( \IPS\Output::getNoCacheHeaders() as $headerKey => $headerValue )
					{
						header( "{$headerKey}: {$headerValue}" );
					}
					header( "Location: {$url}/" . \IPS\CP_DIRECTORY . "/install/" );
					exit;
				}
			}
			
			static::$instance->init();
		}
		
		return static::$instance;
	}
	
	/**
	 * @brief	Controller Classname
	 */
	protected $classname;

	/**
	 * @brief	Controller instance
	 */
	public $dispatcherController;

	/**
	 * Init
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	abstract public function init();

	/**
	 * Run
	 *
	 * @return	void
	 */
	public function run()
	{
		/* Init class */
		if( !class_exists( $this->classname ) )
		{
			\IPS\Output::i()->error( 'page_doesnt_exist', '2S100/1', 404 );
		}
		$this->dispatcherController = new $this->classname;
		if( !( $this->dispatcherController instanceof \IPS\Dispatcher\Controller ) )
		{
			\IPS\Output::i()->error( 'page_not_found', '5S100/3', 500, '' );
		}
		
		/* Execute */
		$this->dispatcherController->execute();
		
		$this->finish();
	}
	
	/**
	 * Finish
	 *
	 * @return	void
	 */
	public function finish()
	{
		/* If we're still here - output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Output::i()->output ), 200, 'text/html' );
		}
		else
		{
			/* Just prefetch this to save a query later */
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, $this->getLocationData() ), 200, 'text/html' );
		}
	}

	/**
	 * Get an array of data representing the user's current location
	 * This gets passed to the templates in order to apply some attributes to the body tag
	 * 
	 * @return 	array
	 */
	public function getLocationData()
	{
		return array( 
			'app' => \IPS\Dispatcher::i()->application->directory, 
			'module' => \IPS\Dispatcher::i()->module->key, 
			'controller' => \IPS\Dispatcher::i()->controller,
			'id' => \IPS\Request::i()->id ? (int) \IPS\Request::i()->id : NULL
		);
	}
}
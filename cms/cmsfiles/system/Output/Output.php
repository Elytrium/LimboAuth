<?php
/**
 * @brief		Output Class
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
 * Output Class
 */
class _Output
{
	/**
	 * @brief	HTTP Statuses
	 * @see		<a href="http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html">RFC 2616</a>
	 */
	public static $httpStatuses = array( 100 => 'Continue', 101 => 'Switching Protocols', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 307 => 'Temporary Redirect', 400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Requested Range Not Satisfiable', 417 => 'Expectation Failed', 429 => 'Too Many Requests', 500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported' );
	
	/**
	 * @brief	Singleton Instance
	 */
	protected static $instance = NULL;
	
	/**
	 * @brief	Global javascript bundles
	 */
	public static $globalJavascript = array( 'admin.js', 'front.js', 'framework.js', 'library.js', 'map.js' );
	
	/**
	 * @brief	Javascript map of file object URLs
	 */
	protected static $javascriptObjects = null;
	
	/**
	 * @brief	File object classes
	 */
	protected static $fileObjectClasses = array();
	
	/**
	 * @brief	Meta tags for the current page
	 */
	public $metaTags	= array();

	/**
	 * @brief	Custom meta tags for the current page
	 */
	public $customMetaTags	= array();

	/**
	 * @brief	Automatic meta tags for the current page
	 */
	public $autoMetaTags	= array();
	
	/**
	 * @brief	Other `<link rel="">` tags
	 */
	public $linkTags = array();
	
	/**
	 * @brief	RSS feeds for the current page
	 */
	public $rssFeeds = array();

	/**
	 * @brief	Custom meta tag page title
	 */
	public $metaTagsTitle	= '';

	/**
	 * @brief	Requested URL fragment for meta tag editing
	 */
	public $metaTagsUrl	= '';
	
	/**
	 * Get instance
	 *
	 * @return	\IPS\Output
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = \get_called_class();
			static::$instance = new $classname;
		}
		
		/* Inline Message */
		if( $message = static::getInlineMessage() )
		{
			if( !\IPS\Request::i()->isAjax() )
			{
				static::$instance->inlineMessage = $message;
				static::setInlineMessage();
			}
		}

		return static::$instance;
	}
	
	/**
	 * @brief	Additional HTTP Headers
	 */
	public $httpHeaders = array(
		'X-XSS-Protection' => '0',	// This is so when we post contents with scripts (which is possible in the editor, like when embedding a Twitter tweet) the broswer doesn't block it
	);
	
	/**
	 * @brief	Stored Page Title
	 */
	public $title = '';

	/**
	 * @brief	Default page title (may differ from $title if the meta tag editor was used)
	 */
	public $defaultPageTitle = '';

	/**
	 * @brief	Should the title show in the header (ACP only)?
	 */
	public $showTitle = TRUE;
	
	/**
	 * @brief	Stored Content to output
	 */
	public $output = '';
	
	/**
	 * @brief	URLs for CSS files to include
	 */
	public $cssFiles = array();
	
	/**
	 * @brief	URLs for JS files to include
	 */
	public $jsFiles = array();
	
	/**
	 * @brief	URLs for JS files to include with async="true"
	 */
	public $jsFilesAsync = array();
	
	/**
	 * @brief	Other variables to hand to the JavaScript
	 */
	public $jsVars = array();
	
	/**
	 * @brief	Other raw JS - this is included inside an existing `<script>` tag already, so you should omit wrapping tags
	 */
	public $headJs = '';

	/**
	 * @brief	Raw CSS to output, used to send custom CSS that may need to be dynamically generated at runtime
	 */
	public $headCss = '';

	/**
	 * @brief	Anything set in this property will be output right before `</body>` - useful for certain third party scripts that need to be output at end of page
	 */
	public $endBodyCode = '';
	
	/**
	 * @brief	Breadcrumb
	 */
	public $breadcrumb = array();
	
	/**
	 * @brief	Page is responsive?
	 */
	public $responsive = TRUE;
	
	/**
	 * @brief	Sidebar
	 */
	public $sidebar = array();
	
	/**
	 * @brief	Global controllers
	 */
	public $globalControllers = array();
	
	/**
	 * @brief	Additional CSS classes to add to body tag
	 */
	public $bodyClasses = array();
	
	/**
	 * @brief	Elements that can be hidden from view
	 */
	public $hiddenElements = array();
	
	/**
	 * @brief	Inline message
	 */
	public $inlineMessage = '';
	
	/**
	 * @brief	Page Edit URL
	 */
	public $editUrl	= NULL;
	
	/**
	 * @brief	`<base target="">`
	 */
	public $base	= NULL;
	
	/**
	 * @brief	Allow default widgets with this output
	 */
	public $allowDefaultWidgets = TRUE;
	
	/**
	 * @brief	Allow page caching. This can be set at any point during controller execution to override defaults
	 */
	public $pageCaching = TRUE;
	
	/**
	 * @brief	pageName for data-pageName in the <body> tag
	 */
	public $pageName = NULL;

	/**
	 * @brief	Data which were loaded via the GraphQL framework, but which have to be immediately available, rather then via later AJAX requests
	 */
	public $graphData = [];
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation !== 'setup' )
		{
			if ( \IPS\Settings::i()->clickjackprevention == 'csp' )
			{
				$this->httpHeaders['Content-Security-Policy'] = \IPS\Settings::i()->csp_header;
				$this->httpHeaders['X-Content-Security-Policy'] = \IPS\Settings::i()->csp_header; // This is just for IE11
			}
			elseif ( \IPS\Settings::i()->clickjackprevention != 'none' )
			{
				$this->httpHeaders['X-Frame-Options'] = "sameorigin";
				$this->httpHeaders['Content-Security-Policy'] = "frame-ancestors 'self'";
				$this->httpHeaders['X-Content-Security-Policy'] = "frame-ancestors 'self'";
			}

			/* 2 = entire suite, 1 = ACP only */
			if( \IPS\Settings::i()->referrer_policy_header == 2 OR ( \IPS\Settings::i()->referrer_policy_header == 1 AND \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation === 'admin' ) )
			{
				$this->httpHeaders['Referrer-Policy'] = 'strict-origin-when-cross-origin';
			}
		}
	}

	/**
	 * Get a JS bundle and add the files to the output. This greatly cleans up the usage from outside of the Output class
	 *
	 * @par JS Bundle Filename Cheatsheet
	 * @li library.js (this is jQuery, mustache, underscore, jstz, etc)
	 * @li framework.js (this is ui/, utils/*, ips.model.js, ips.controller.js and the editor controllers)
	 * @li admin.js or front.js (these are controllers, templates and models which are used everywhere for that location)
	 * @li app.js (this is all models for a single application)
	 * @li {location}_{section}.js (this is all controllers and templates for this section called ad-hoc when needed)
	 *
	 * @param	string		$file		Filename
	 * @param	string|null	$app		Application
	 * @param	string|null	$location	Location (e.g. 'admin', 'front')
	 * @return	array		URL to JS files
	 */
	public function addJsFiles( $file, $app=NULL, $location=NULL )
	{
		$this->jsFiles = array_merge( $this->jsFiles, $this->js( $file, $app, $location ) );
	}
	
	/**
	 * Get a JS bundle
	 *
	 * @par JS Bundle Cheatsheet
	 * @li library.js (this is jQuery, mustache, underscore etc)
	 * @li framework.js (this is ui/, utils/*, ips.model.js, ips.controller.js and the editor controllers)
     * @li admin.js or front.js (these are controllers, templates and models which are used everywhere for that location)
	 * @li app.js (this is all models for a single application)
	 * @li {location}_{section}.js (this is all controllers and templates for this section called ad-hoc when needed)
	 *
	 * @param	string		$file		Filename
	 * @param	string|null	$app		Application
	 * @param	string|null	$location	Location (e.g. 'admin', 'front')
	 * @return	array		URL to JS files
	 */
	public function js( $file, $app=NULL, $location=NULL )
	{
		$file = trim( $file, '/' );
			 
		if ( $location === 'interface' AND mb_substr( $file, -3 ) === '.js' )
		{
			return array( rtrim( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ), '/' ) . "/applications/{$app}/interface/{$file}?v=" . ( \defined( '\IPS\CACHEBUST_KEY' ) ? \IPS\CACHEBUST_KEY : time() ) );
		}
		elseif ( \IPS\IN_DEV )
		{
			return \IPS\Output\Javascript::inDevJs( $file, $app, $location );
		}
		else
		{
			if ( class_exists( 'IPS\Dispatcher', FALSE ) and ( !\IPS\Dispatcher::hasInstance() OR \IPS\Dispatcher::i()->controllerLocation === 'setup' ) )
			{
				return array();
			}
			
			if ( $app === null OR $app === 'global' )
			{
				if ( \in_array( $file, static::$globalJavascript ) )
				{
					/* Global bundle (admin.js, front.js, library.js, framework.js, map.js) */
					$fileObj = static::_getJavascriptFileObject( 'global', 'root', $file );

					if ( $fileObj !== NULL )
					{
						return array( $fileObj->url->setQueryString( 'v', \IPS\Output\Javascript::javascriptCacheBustKey() ) );
					}
				}
				
				/* Languages JS file */
				if ( mb_substr( $file, 0, 8 ) === 'js_lang_' )
				{
					$fileObj = static::_getJavascriptFileObject( 'global', 'root', $file );

					if ( $fileObj !== NULL )
					{
						return array( $fileObj->url->setQueryString( 'v', \IPS\Output\Javascript::javascriptCacheBustKey() ) );
					}
				}
			}
			else
			{
				$app      = $app      ?: \IPS\Request::i()->app;
				$location = $location ?: \IPS\Dispatcher::i()->controllerLocation;

				/* plugin.js */
				if ( $app === 'core' and $location === 'plugins' and $file === 'plugins.js' )
				{
					$pluginsJs = static::_getJavascriptFileObject( 'core', 'plugins', 'plugins.js' );
					
					if ( $pluginsJs !== NULL )
					{
						return array( $pluginsJs->url->setQueryString( 'v', \IPS\Output\Javascript::javascriptCacheBustKey() ) );
					}
				}
				/* app.js - all models and ui */
				else if ( $file === 'app.js' )
				{
					$fileObj = static::_getJavascriptFileObject( $app, $location, 'app.js' );
					
					if ( $fileObj !== NULL )
					{
						return array( $fileObj->url->setQueryString( 'v', \IPS\Output\Javascript::javascriptCacheBustKey() ) );
					}
				}
				/* {location}_{section}.js */
				else if ( mb_strstr( $file, '_') AND mb_substr( $file, -3 ) === '.js' )
				{
					list( $location, $key ) = explode( '_',  mb_substr( $file, 0, -3 ) );
						
					if ( ( $location == 'front' OR $location == 'admin' OR $location == 'global' ) AND ! empty( $key ) )
					{
						$fileObj = static::_getJavascriptFileObject( $app, $location, $location . '_' . $key . '.js' );
						
						if ( $fileObj !== NULL )
						{
							return array( $fileObj->url->setQueryString( 'v', \IPS\Output\Javascript::javascriptCacheBustKey() ) );
						}
					}
				}
			}
		}
		
		return array();
	}
	
	/**
	 * Removes JS files from \IPS\File
	 *
	 * @param	string|null	$app		Application
	 * @param	string|null	$location	Location (e.g. 'admin', 'front')
	 * @param	string|null	$file		Filename
	 * @return	void
	 */
	public static function clearJsFiles( $app=null, $location=null, $file=null )
	{
		$javascriptObjects = ( isset( \IPS\Data\Store::i()->javascript_map ) ) ? \IPS\Data\Store::i()->javascript_map : array();
			
		if ( $location === null and $file === null )
		{
			if ( $app === null or $app === 'global' )
			{
				try
				{
					\IPS\File::getClass('core_Theme')->deleteContainer( 'javascript_global' );
				} catch( \Exception $e ) { }
				
				unset( $javascriptObjects['global'] );
			}
			
			foreach( \IPS\Application::applications() as $key => $data )
			{
				if ( $app === null or $app === $key )
				{
					try
					{
						\IPS\File::getClass('core_Theme')->deleteContainer( 'javascript_' . $key );
					} catch( \Exception $e ) { }
					
					unset( $javascriptObjects[ $key ] );
				}
			}
		}
		
		if ( $file )
		{
			$key = md5( $app .'-' . $location . '-' . $file );
			
			if ( isset( $javascriptObjects[ $app ] ) and \is_array( $javascriptObjects[ $app ] ) and \in_array( $key, array_keys( $javascriptObjects[ $app ] ) ) )
			{
				if ( $javascriptObjects[ $app ][ $key ] !== NULL )
				{
					\IPS\File::get( 'core_Theme', $javascriptObjects[ $app ][ $key ] )->delete();
					
					unset( $javascriptObjects[ $app ][ $key ] );
				}
			}
		}

		\IPS\Settings::i()->changeValues( array( 'javascript_updated' => time() ) );

		\IPS\Data\Store::i()->javascript_map = $javascriptObjects;
	}

	/**
	 * Check page title and modify as needed
	 *
	 * @param	string	$title	Page title
	 * @return	string
	 */
	public function getTitle( $title )
	{
		if( $this->metaTagsTitle )
		{
			$title	= $this->metaTagsTitle;
		}
		else
		{
			$title = htmlspecialchars( $title, ENT_DISALLOWED, 'UTF-8', FALSE );
		}
		
		if( !\IPS\Settings::i()->site_online )
		{
			$title	= sprintf( \IPS\Member::loggedIn()->language()->get( 'offline_title_wrap' ), $title );
		}

		return $title;
	}

	/**
	 * Retrieve cache headers
	 *
	 * @param	int		$lastModified	Last modified timestamp
	 * @param	int		$cacheSeconds	Number of seconds to cache for
	 * @return	array
	 */
	public static function getCacheHeaders( int $lastModified, int $cacheSeconds ): array
	{
		return array(
			'Date'			=> \IPS\DateTime::ts( time(), TRUE )->rfc1123(),
			'Last-Modified'	=> \IPS\DateTime::ts( $lastModified, TRUE )->rfc1123(),
			'Expires'		=> \IPS\DateTime::ts( ( time() + $cacheSeconds ), TRUE )->rfc1123(),
			'Cache-Control'	=> 'no-cache="Set-Cookie", max-age=' . $cacheSeconds . ", public, s-maxage=" . $cacheSeconds . ", stale-while-revalidate, stale-if-error",
		);
	}
	
	/**
	 * Get No Cache Headers
	 *
	 * @return	array
	 */
	public static function getNoCacheHeaders(): array
	{
		return array(
			'Expires'		=> 0,
			'Cache-Control'	=> "no-cache, no-store, must-revalidate, max-age=0, s-maxage=0"
		);
	}

	/**
	 * Retrieve Content-disposition header. Formats filename according to requesting client.
	 *
	 * @param	string		$disposition	Disposition: attachment or inline
	 * @param	string		$filename		Filename
	 * @return	string
	 * @see		<a href='http://code.google.com/p/browsersec/wiki/Part2#Downloads_and_Content-Disposition'>Browser content-disposition handling</a>
	 */
	public static function getContentDisposition( $disposition='attachment', $filename=NULL )
	{
		if( $filename === NULL )
		{
			return $disposition;
		}

		$return	= $disposition . '; filename';

		if ( !\IPS\Dispatcher::hasInstance() )
		{
			\IPS\Session\Front::i();
		}
		
		switch( \IPS\Session::i()->userAgent->browser )
		{
			case 'firefox':
			case 'opera':
				$return	.= "*=UTF-8''" . rawurlencode( $filename );
			break;

			case 'explorer':
			case 'Edge':
			case 'edge':
			case 'chrome':
			case 'Chrome':
				$return	.= '="' . rawurlencode( $filename ) . '"';
			break;

			default:
				$return	.= '="' . $filename . '"';
			break;
		}

		return $return;
	}
	
	/**
	 * Return a JS file object, recompiling it first if doesn't exist.
	 *
	 * @param	string|null	$app		Application
	 * @param	string|null	$location	Location (e.g. 'admin', 'front')
	 * @param	string		$file		Filename
	 * @return	string|null					URL to JS file object
	 */
	protected static function _getJavascriptFileObject( $app, $location, $file )
	{
		$key = md5( $app .'-' . $location . '-' . $file );

		$javascriptObjects = ( isset( \IPS\Data\Store::i()->javascript_map ) ) ? \IPS\Data\Store::i()->javascript_map : array();

		if ( isset( $javascriptObjects[ $app ] ) and \in_array( $key, array_keys( $javascriptObjects[ $app ] ) ) )
		{
			if ( $javascriptObjects[ $app ][ $key ] === NULL )
			{
				return NULL;
			}
			else
			{
				return \IPS\File::get( 'core_Theme', $javascriptObjects[ $app ][ $key ] );
			}
		}
		
		/* We're setting up, do nothing to avoid compilation requests when tables are incomplete */
		if ( ! isset( \IPS\Settings::i()->setup_in_progress ) OR \IPS\Settings::i()->setup_in_progress )
		{
			return NULL;
		}
			
		/* Still here? */
		try
		{
			if ( \IPS\Output\Javascript::compile( $app, $location, $file ) === NULL )
			{
				/* Rebuild already in progress */
				return NULL;
			}
		}
		catch( \RuntimeException $e )
		{
			/* Possibly cannot write file - log but don't show an error as the user can't fix anyways */
			\IPS\Log::log( $e, 'javascript' );

			return NULL;
		}

		/* The map may have changed */
		$javascriptObjects = ( isset( \IPS\Data\Store::i()->javascript_map ) ) ? \IPS\Data\Store::i()->javascript_map : array();
		
		/* Test again */
		if ( isset( $javascriptObjects[ $app ] ) and \in_array( $key, array_keys( $javascriptObjects[ $app ] ) ) and $javascriptObjects[ $app ][ $key ] )
		{
			return \IPS\File::get( 'core_Theme', $javascriptObjects[ $app ][ $key ] );
		}
		else
		{
			/* Still not there, set this map key to null to prevent repeat access attempts */
			$javascriptObjects[ $app ][ $key ] = null;
			
			\IPS\Data\Store::i()->javascript_map = $javascriptObjects;
		}
		
		return NULL;
	}
	
	/**
	 * Display Error Screen
	 *
	 * @param	string				$message 			language key for error message
	 * @param	mixed				$code 				Error code
	 * @param	int					$httpStatusCode 	HTTP Status Code
	 * @param	string				$adminMessage 		language key for error message to show to admins
	 * @param	array 				$httpHeaders 		Additional HTTP Headers
	 * @param	string 				$extra 				Additional information (such backtrace or API error) which will be shown to admins
	 * @param	int|string|NULL		$faultyAppOrHookId	The 3rd party application or the hook id, which caused this error, NULL if it was a core application
	 */
	public function error( $message, $code, $httpStatusCode=500, $adminMessage=NULL, $httpHeaders=array(), $extra=NULL, $faultyAppOrHookId=NULL )
	{
		/* When we log out, the user is taken back to the page they were just on. If this is producing a "no permission" error, redirect them to the index instead */
		if ( isset( \IPS\Request::i()->_fromLogout ) )
		{
			// _fromLogout=1 indicates that they came from log out. To make sure that we don't cause an infinite redirect (which
			// would happen if guests cannot view the index page) we need to change _fromLogout, but we can't unset it because _fromLogout={anything}
			// will clear the autosave content on next load (by Javascript), which we need to do on log out for security reasons... so, _fromLogout=2
			// is used here which will clear the autosave, but *not* redirect them again
			if ( \IPS\Request::i()->_fromLogout != 2 )
			{
				$this->redirect( \IPS\Http\Url::internal('')->stripQueryString()->setQueryString( '_fromLogout', 2 ) );
			}
		}
		
		/* If we just logged in and we need to do MFA, do that */
		if ( isset( \IPS\Request::i()->_mfaLogin ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login", 'front', 'login' )->setQueryString( '_mfaLogin', 1 ) );
		}

		/* Do not show advertisements that shouldn't display on non-content pages on error pages */
		if( \IPS\Dispatcher::i()->dispatcherController )
		{
			\IPS\Dispatcher::i()->dispatcherController->isContentPage = FALSE;
		}
		
		/* If we're in an external script, just show a simple message */
		if ( !\IPS\Dispatcher::hasInstance() )
		{
			\IPS\Session\Front::i();

			$this->sendOutput( \IPS\Member::loggedIn()->language()->get( $message ), $httpStatusCode, 'text/html', $httpHeaders, FALSE );
			return;
		}

		/* Remove the page token */
		unset( \IPS\Output::i()->jsVars['page_token'] );
		
		/* Work out the title */
		$title = "{$httpStatusCode}_error_title";
		$title = \IPS\Member::loggedIn()->language()->checkKeyExists( $title ) ? \IPS\Member::loggedIn()->language()->addToStack( $title ) : \IPS\Member::loggedIn()->language()->addToStack( 'error_title' );

		/* If we're in setup, just display it */
		if ( \IPS\Dispatcher::i()->controllerLocation === 'setup' )
		{
			$this->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( $title, \IPS\Theme::i()->getTemplate( 'global', 'core' )->error( $title, $message, $code, $extra ) ), $httpStatusCode, 'text/html', $httpHeaders, FALSE );
		}
		
		/* Are we an administrator logged in as a member? */
		$member = \IPS\Member::loggedIn();
		if ( isset( $_SESSION['logged_in_as_key'] ) )
		{
			try
			{
				$_member = \IPS\Member::load( $_SESSION['logged_in_from']['id'] );
				if ( $_member->member_id == $_SESSION['logged_in_from']['id'] )
				{
					$member = $_member;
				}
			}
			catch ( \OutOfRangeException $e ) { }
		}
		
		/* Which message are we showing? */
		if( $member->isAdmin() and $adminMessage )
		{
			$message = $adminMessage;
		}
		else if ( \IPS\Dispatcher::i()->dispatcherController instanceof \IPS\Content\Controller and mb_substr( $message, 0, 10 ) == 'node_error' )
		{
			if ( \IPS\Member::loggedIn()->language()->checkKeyExists( $httpStatusCode . '_' . \IPS\Dispatcher::i()->application->directory . '_' . \IPS\Dispatcher::i()->controller ) )
			{
				$message = $httpStatusCode . '_' . \IPS\Dispatcher::i()->application->directory . '_' . \IPS\Dispatcher::i()->controller;
			}
		}

		if ( \IPS\Member::loggedIn()->language()->checkKeyExists( $message ) )
		{
			$message = \IPS\Member::loggedIn()->language()->addToStack( $message );
		}
		
		/* Replace language stack keys with actual content */
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $message );
								
		/* Log */
		$level = \intval( \substr( $code, 0, 1 ) );
		if( !\IPS\Session::i()->userAgent->spider )
		{
			if( $code and \IPS\Settings::i()->error_log_level and $level >= \IPS\Settings::i()->error_log_level )
			{
				\IPS\Db::i()->insert( 'core_error_logs', array(
					'log_member'		=> \IPS\Member::loggedIn()->member_id ?: 0,
					'log_date'			=> time(),
					'log_error'			=> $message,
					'log_error_code'	=> $code,
					'log_ip_address'	=> \IPS\Request::i()->ipAddress(),
					'log_request_uri'	=> $_SERVER['REQUEST_URI'],
					) );

				\IPS\core\AdminNotification::send( 'core', 'Error', NULL, TRUE, array( $code, $message ) );
			}
		}
			
		/* If this is an AJAX request, send a JSON response */
		if( \IPS\Request::i()->isAjax() )
		{
			$this->json( $message, $httpStatusCode );
		}


		$faulty = '';

		/* Try to find the breaking hook */
		if ( $faultyAppOrHookId )
		{
			if ( \is_numeric( $faultyAppOrHookId ) )
			{
				$hookSource = \IPS\Db::i()->select( 'plugin', 'core_hooks', array( 'id=?', $faultyAppOrHookId ) )->first();
				$plugin = \IPS\Plugin::load( $hookSource );
				$faulty = \IPS\Member::loggedIn()->language()->addToStack( 'faulty_plugin', FALSE, array( 'sprintf' => array( $plugin->name, \IPS\Http\Url::internal('app=core&module=applications&controller=plugins', 'admin' ) ) ) );
			}
			else
			{
				$app = \IPS\Application::load( $faultyAppOrHookId );
				$faulty = \IPS\Member::loggedIn()->language()->addToStack( 'faulty_app', FALSE, array( 'sprintf' => array( $app->_title, \IPS\Http\Url::internal( 'app=core&module=applications&controller=applications', 'admin' ) ) ) );
			}
		}

		/* Send default Cache Headers if this is a front-end page. */
		$allowGuestCache = FALSE;
		if ( !isset( $httpHeaders['Cache-Control'] ) AND ( $httpStatusCode == 404 OR $httpStatusCode == 403 ) )
		{
			if ( \IPS\CACHE_PAGE_TIMEOUT AND $this->pageCaching AND \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation === 'front' AND !\IPS\Member::loggedIn()->member_id )
			{
				$httpHeaders += static::getCacheHeaders( time(), \IPS\CACHE_PAGE_TIMEOUT );
			}
		}

		/* Send output */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		$this->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( $title, \IPS\Theme::i()->getTemplate( 'global', 'core' )->error( $title, $message, $code, $extra, $member, $faulty, $httpStatusCode ), array( 'app' => \IPS\Dispatcher::i()->application ? \IPS\Dispatcher::i()->application->directory : NULL, 'module' => \IPS\Dispatcher::i()->module ? \IPS\Dispatcher::i()->module->key : NULL, 'controller' => \IPS\Dispatcher::i()->controller ) ), $httpStatusCode, 'text/html', $httpHeaders, FALSE, FALSE );
	}

	/**
	 * Send a header.  This is abstracted in an effort to better isolate code for testing purposes.
	 *
	 * @param	string	$header	Text to send as a fully formatted header string
	 * @return	void
	 */
	public function sendHeader( $header )
	{
		/* If we are running our test suite, we don't want to send browser headers */
		if( \IPS\ENFORCE_ACCESS === true AND mb_strtolower( php_sapi_name() ) == 'cli' )
		{
			return;
		}

		header( $header );
	}

	/**
	 * Send a header.  This is abstracted in an effort to better isolate code for testing purposes.
	 *
	 * @param	int	$httpStatusCode	HTTP Status Code
	 * @return	void
	 */
	public function sendStatusCodeHeader( $httpStatusCode )
	{
		/* Set HTTP status */
		if( isset( $_SERVER['SERVER_PROTOCOL'] ) and \strstr( $_SERVER['SERVER_PROTOCOL'], '/1.0' ) !== false )
		{
			$this->sendHeader( "HTTP/1.0 {$httpStatusCode} " . static::$httpStatuses[ $httpStatusCode ] );
		}
		else
		{
			$this->sendHeader( "HTTP/1.1 {$httpStatusCode} " . static::$httpStatuses[ $httpStatusCode ] );
		}
	}

	/**
	 * @brief Flag to bypass CSRF IN_DEV check
	 */
	public $bypassCsrfKeyCheck	= FALSE;

	/**
	 * Send output
	 *
	 * @param string $output Content to output
	 * @param int $httpStatusCode HTTP Status Code
	 * @param string $contentType HTTP Content-type
	 * @param array $httpHeaders Additional HTTP Headers
	 * @param bool $cacheThisPage Can/should this page be cached?
	 * @param bool $pageIsCached Is the page from a cache? If TRUE, no language parsing will be done
	 * @param bool $parseFileObjects Should `<fileStore.xxx>` and `<___base_url___>` be replaced in the output?
	 * @param bool $parseEmoji Should Emoji be parsed?
	 * @return    void
	 * @throws \Exception
	 */
	public function sendOutput( $output='', $httpStatusCode=200, $contentType='text/html', $httpHeaders=array(), $cacheThisPage=TRUE, $pageIsCached=FALSE, $parseFileObjects=TRUE, $parseEmoji=TRUE )
	{
		if( \IPS\IN_DEV AND !$this->bypassCsrfKeyCheck AND mb_substr( $httpStatusCode, 0, 1 ) === '2' AND isset( $_GET['csrfKey'] ) AND $_GET['csrfKey'] AND !\IPS\Request::i()->isAjax() AND ( !isset( $httpHeaders['Content-Disposition'] ) OR mb_strpos( $httpHeaders['Content-Disposition'], 'attachment' ) === FALSE ) )
		{
			trigger_error( "An {$httpStatusCode} response is being sent however the CSRF key is present in the requested URL. CSRF keys should be sent via POST or the request should be redirected to a URL not containing a CSRF key once finished.", E_USER_ERROR );
			exit;
		}

		if ( \defined('LIVE_TOPICS_DEV') AND \LIVE_TOPICS_DEV )
		{
			$httpHeaders['Access-Control-Allow-Origin'] = '*';
		}

		/* Cache session Data Layer events */
		if ( !( $httpStatusCode === 200 AND !\IPS\Request::i()->isAjax() AND $contentType == 'text/html' ) )
		{
			\IPS\core\DataLayer::i()->cache();
		}

		/* Replace language stack keys with actual content */
		if ( \IPS\Dispatcher::hasInstance() and !\in_array( $contentType, array( 'text/javascript', 'text/css', 'application/json' ) ) and $output and !$pageIsCached )
		{
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $output );
		}
		
		/* Parse file storage URLs */
		if ( $output and $parseFileObjects )
		{
			$this->parseFileObjectUrls( $output );
		}

		/* Replace emoji */
		if ( $output and $parseEmoji and \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation !== 'setup' )
		{
			$output = $this->replaceEmojiWithImages( $output );
		}

		/* Combine some common checks for re-use */
		$canCache = function( $contentType, $obj ) {
				if( $obj->pageCaching and
					( mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'get' OR mb_strtolower( $_SERVER['REQUEST_METHOD'] ) == 'head' ) and   // Is a HTTP GET/HEAD request (don't cache output for POSTs)
					( $contentType == 'text/html' or $contentType == 'text/xml' or $contentType == 'application/manifest+json' ) and    // Output is HTML, XML or [PWA] application manifest (don't cache JSON output, etc)
					\IPS\Dispatcher::hasInstance() and class_exists( 'IPS\Dispatcher', FALSE ) and \IPS\Dispatcher::i()->controllerLocation === 'front' // Is a normal, front-end page (necessary to know if user is logged in)
				)
				{
					return TRUE;
				}
				return FALSE;
		};

		if (
			\IPS\CACHE_PAGE_TIMEOUT and						        // Page caching is enabled
			$cacheThisPage and										// Some pages can specify not to be cached (for example, when displaying a cached page, you don't want it recached)
			!isset( \IPS\Request::i()->cookie['noCache'] ) and		// A noCache cookie might get set to not cache a particular guest (for example, if they have added items to their cart in the store)
			$canCache( $contentType, $this ) and                    // Common checks, see above
			!isset( \IPS\Request::i()->csrfKey ) and 				// CSRF key isn't present (which would be like a POST request)
			!\IPS\Member::loggedIn()->member_id						// User is not logged in
		) {
			/* Add caching headers */
			if( !isset( $httpHeaders['Cache-Control'] ) )
			{
				$httpHeaders += \IPS\Output::getCacheHeaders( time(), \IPS\CACHE_PAGE_TIMEOUT );
			}
		}
		/* Send no-cache headers if we got to this point without any cache-control headers being set, or page caching is forced off */
		elseif( !isset( $httpHeaders['Cache-Control'] ) OR !$this->pageCaching )
		{
			$httpHeaders += \IPS\Output::getNoCacheHeaders();
		}

		/* Include headers set in constructor, intentionally after guest caching. */
		$httpHeaders = $this->httpHeaders + $httpHeaders;

		/* We will only push resources (http/2) on the first visit, i.e. if the session cookie is not present yet */
		$location = ( \IPS\Dispatcher::hasInstance() ) ? mb_ucfirst( \IPS\Dispatcher::i()->controllerLocation ) : 'Front';

		if( isset( \IPS\Request::i()->cookie['IPSSession' . $location ] ) AND \IPS\Request::i()->cookie['IPSSession' . $location ] AND isset( $httpHeaders['Link'] ) )
		{
			unset( $httpHeaders['Link'] );
		}
		
		/* Query Log (has to be done after parseOutputForDisplay because runs queries and after page caching so the log isn't misleading) */
		if ( $output and ( \IPS\QUERY_LOG or \IPS\CACHING_LOG or \IPS\REDIS_LOG ) and \in_array( $contentType, array( 'text/html', 'application/json' ) ) and ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation !== 'setup' ) )
		{
			/* Close the session and run tasks now so we can see those queries */
			session_write_close();
			if ( \IPS\Dispatcher::hasInstance() )
			{
				\IPS\Dispatcher::i()->__destruct();
			}
			
			/* And run */
			$cachingLog = \IPS\Data\Cache::i()->log;

			try
			{
				if ( \IPS\REDIS_LOG )
				{
					$cachingLog =  $cachingLog + \IPS\Redis::$log;
					ksort( $cachingLog );
				}
			}
			catch( \Exception $e ) { }

			$queryLog = \IPS\Db::i()->log;
			if ( \IPS\QUERY_LOG )
			{
				$output = str_replace( '<!--ipsQueryLog-->', \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->queryLog( $queryLog ), $output );
			}
			if ( \IPS\CACHING_LOG or \IPS\REDIS_LOG )
			{
				$output = str_replace( '<!--ipsCachingLog-->', \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->cachingLog( $cachingLog ), $output );
			}
		}

		/* VLE language bits now parseOutputForDisplay has run */
		if ( \IPS\Lang::vleActive() )
		{
			$output = str_replace( '<!--ipsVleWords-->', 'var ipsVle = ' . json_encode( \IPS\Member::loggedIn()->language()->vleForJson(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) . ';', $output );
		}

		/* Check for any autosave cookies */
		if ( \count( \IPS\Request::i()->clearAutoSaveCookie ) )
		{
			\IPS\Request::i()->setCookie( 'clearAutosave', implode( ',', \IPS\Request::i()->clearAutoSaveCookie ), NULL, FALSE );
		}

		/* Remove anything from the output buffer that should not be there as it can confuse content-length */
		if( ob_get_length() )
		{
			@ob_end_clean();
		}

		/* Trim any blank spaces before the beginning of output */
		$output = ltrim( $output );
				
		/* Set HTTP status */
		$this->sendStatusCodeHeader( $httpStatusCode );

		/* Start buffering */
		ob_start();
		
		/* Generated by a logged in user? */
		if( \IPS\Dispatcher::hasInstance() )
		{
			$this->sendHeader( "X-IPS-LoggedIn: " . ( ( \IPS\Member::loggedIn()->member_id ) ? 1 : 0 ) );
		}

		/* We want to vary on the cookie so that browser caches are not used when changing themes or languages */
		$vary = array( 'Cookie' );
		
		/* Can we compress the content? */
		if ( $output and isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) )
		{
			/* If php zlib.output_compression is on, don't do anything since PHP will */
			if( (bool) ini_get('zlib.output_compression') === false )
			{
				/* Try brotli first - support will be rare, but preferred if it is available */
				if ( \strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'br' ) !== false and \function_exists( 'brotli_compress' ) )
				{
					$output = brotli_compress( $output );
					$this->sendHeader( "Content-Encoding: br" ); // Tells the server we've alredy encoded so it doesn't need to
					$vary[] = "Accept-Encoding"; // Tells proxy caches that the response varies depending upon encoding
				}
				/* If the browser supports gzip, gzip the content - we do this ourselves so that we can send Content-Length even with mod_gzip */
				elseif ( \strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) !== false and \function_exists( 'gzencode' ) )
				{
					$output = gzencode( $output ); // mod_gzip will encode pages, but we want to encode ourselves so that Content-Length is correct
					$this->sendHeader( "Content-Encoding: gzip" ); // Tells the server we've alredy encoded so it doesn't need to
					$vary[] = "Accept-Encoding"; // Tells proxy caches that the response varies depending upon encoding
				}
			}
		}
		
		if ( \count( $vary ) )
		{
			$this->sendHeader( "Vary: " . implode( ", ", $vary ) );
		}

		/* Output */
		print $output;
		
		/* Update advertisement impression counts, if appropriate */
		\IPS\core\Advertisement::updateImpressions();

		/* Send headers */
		$this->sendHeader( "Content-type: {$contentType};charset=UTF-8" );

		/* Send content-length header, but only if not using zlib.output_compression, because in that case the length we send in the header
			will not match the length of the actual content sent to the browser, breaking things (particularly json) */
		if( (bool) ini_get('zlib.output_compression') === false )
		{
			$size = ob_get_length();
			$this->sendHeader( "Content-Length: {$size}" ); // Makes sure the connection closes after sending output so that tasks etc aren't holding it open
		}
		
		/* Rest of our HTTP headers */
		foreach ( $httpHeaders as $key => $header )
		{
			$this->sendHeader( $key . ': ' . $header );
		}
		$this->sendHeader( "Connection: close" );

		/* If we are running our test suite, we don't want to output or exit, which will allow the test suite to capture the response */
		if( \IPS\ENFORCE_ACCESS === true AND mb_strtolower( php_sapi_name() ) == 'cli' )
		{
			return;
		}

		/* Flush and exit */
		@ob_end_flush();
		@flush();

		/* Log headers if we are set to do so */
		if( \IPS\DEV_LOG_HEADERS === TRUE )
		{
			$this->_logHeadersSent();
		}

		/* If using PHP-FPM, close the request so that __destruct tasks are run after data is flushed to the browser
			@see http://www.php.net/manual/en/function.fastcgi-finish-request.php */
		if( \function_exists( 'fastcgi_finish_request' ) )
		{
			fastcgi_finish_request();
		}

		exit;
	}

	/**
	 * Logs the headers that have been sent, if we are able to do so
	 *
	 * @return void
	 */
	protected function _logHeadersSent()
	{
		$headers = NULL;

		if( \function_exists('headers_list') )
		{
			$headers = headers_list();
		}
		elseif( \function_exists('apache_response_headers') )
		{
			$headers = apache_response_headers();
		}
		elseif( \function_exists('xdebug_get_headers') )
		{
			$headers = xdebug_get_headers();
		}

		if( $headers !== NULL )
		{
			\IPS\Log::log( $headers, 'httpHeaders' );
		}
	}

	/**
	 * Fetch the URLs to preload (via Link: HTTP header)
	 *
	 * @return array
	 */
	public function getPreloadUrls()
	{
		/* http/2 push resources */
		$preload = array();

		foreach( $this->linkTags as $tag )
		{
			/* We are only doing this for rel=preload, and the 'as' parameter is not optional. Preloading fonts currently does not work as expected in Chrome, resulting in the font file downloading twice, so we will skip fonts. */
			if( \is_array( $tag ) AND isset( $tag['rel'] ) AND $tag['rel'] == 'preload' AND isset( $tag['as'] ) AND $tag['as'] AND $tag['as'] != 'font' )
			{
				$preload[] = $tag['href'] . '; rel=preload; as=' .  $tag['as'];
			}
		}

		$cssFilesToCheck = ( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation == 'setup' ) ? \IPS\Output::i()->cssFiles : array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'custom.css', 'core', 'front' ) );

		foreach( array_unique( $cssFilesToCheck, SORT_STRING ) as $css )
		{
			$url = \IPS\Http\Url::external( $css )->setQueryString( 'v', \IPS\CACHEBUST_KEY );
			$preload[] = (string) $url . '; rel=preload; as=style';
		}
		
		foreach( array_unique( array_filter( array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->jsFilesAsync ) ), SORT_STRING ) as $js )
		{
			$url = \IPS\Http\Url::external( $js );

			if( $url->data['host'] == parse_url( \IPS\Settings::i()->base_url, PHP_URL_HOST ) )
			{
				$url = $url->setQueryString( 'v', \IPS\CACHEBUST_KEY );
			}

			$preload[] = (string) $url . '; rel=preload; as=script';
		}

		/* Only include the first 30 entries if there are a lot */
		return \array_slice( $preload, 0, 30 );
	}
	
	/**
	 * Send JSON output
	 *
	 * @param	string|array	$data	Data to be JSON-encoded
	 * @param	int				$httpStatusCode		HTTP Status Code
	 * @return	void
	 */
	public function json( $data, $httpStatusCode=200 )
	{
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $data );
		return $this->sendOutput( json_encode( \IPS\Member::loggedIn()->language()->stripVLETags( $data ) ), $httpStatusCode, 'application/json', $this->httpHeaders );
	}
	
	/**
	 * Redirect
	 *
	 * @param	\IPS\Http\Url	$url			URL to redirect to
	 * @param	string			$message		Optional message to display
	 * @param	int				$httpStatusCode	HTTP Status Code
	 * @param	bool			$forceScreen	If TRUE, an intermediate screen will be shown
	 * @return	void
	 */
	public function redirect( $url, $message='', $httpStatusCode=301, $forceScreen=FALSE )
	{
		if( \IPS\Request::i()->isAjax() )
		{
			if ( $message !== '' )
			{
				$message =  \IPS\Member::loggedIn()->language()->checkKeyExists( $message ) ? \IPS\Member::loggedIn()->language()->addToStack( $message ) : $message;
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $message );
			}

			$this->json( array(
					'redirect' => (string) $url,
					'message' => $message
			)	);
		}
		elseif ( $forceScreen === TRUE or ( $message and !( $url instanceof \IPS\Http\Url\Internal ) ) )
		{
			/* We cannot send a 3xx status code without a Location header, or some browsers (cough IE) will not actually redirect. We are showing
				an intermediary page performing the redirect through a meta refresh tag, so a 200 status is appropriate in this case. */
			$httpStatusCode = ( mb_substr( $httpStatusCode, 0, 1 ) == 3 ) ? 200 : $httpStatusCode;

			$this->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->redirect( $url, $message ), $httpStatusCode );
		}
		else
		{
			if ( $message )
			{
				$message = \IPS\Member::loggedIn()->language()->addToStack( $message );
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $message );
				static::setInlineMessage( $message );
				session_write_close();
			}
			elseif( $this->inlineMessage )
			{
				static::setInlineMessage( $this->inlineMessage );
				session_write_close();
			}

			/* Send location and no-cache headers to prevent redirects from being cached */
			$headers = \array_merge( array( "Location" => (string) $url ), \IPS\Output::getNoCacheHeaders() );

			$this->sendOutput( '', $httpStatusCode, '', $headers );
		}
	}
	
	/**
	 * Replace the {{fileStore.xxxxxx}} urls to the actual URLs
	 *
	 * @param	string	$output		The compiled output
	 * @return void
	 */
	public function parseFileObjectUrls( &$output )
	{
		if ( \stristr( $output, '<fileStore.' ) )
		{
			preg_match_all( '#<fileStore.([\d\w\_]+?)>#', $output, $matches, PREG_SET_ORDER );
			
			foreach( $matches as $index => $data )
			{
				if ( isset( $data[1] ) )
				{
					if ( ! isset( static::$fileObjectClasses[ $data[1] ] ) )
					{
						try
						{
							static::$fileObjectClasses[ $data[1] ] = \IPS\File::getClass( $data[1], TRUE );
						}
						catch ( \RuntimeException $e )
						{
							static::$fileObjectClasses[ $data[1] ] = NULL;
						}
					}
					
					if ( static::$fileObjectClasses[ $data[1] ] )
					{
						$output = str_replace( $data[0], static::$fileObjectClasses[ $data[1] ]->baseUrl(), $output );
					}
				}
			}
		}
		
		/* ___base_url___ is a bit dramatic but it prevents accidental replacements with tags called base_url if a third party app or hook uses it */
		$output = str_replace( '<___base_url___>', rtrim( \IPS\Settings::i()->base_url, '/' ), $output );
	}
	
	/**
	 * Replace emoji unicode with images
	 *
	 * @param	string	$output		The output containing emojis as unicode
	 * @return	string
	 */
	public function replaceEmojiWithImages( $output )
	{
		if ( \IPS\Settings::i()->emoji_style == 'twemoji' )
		{
			return preg_replace_callback( '/<span class="ipsEmoji">(.+?)<\/span>/', function( $matches ) {
				$hex = bin2hex( mb_convert_encoding( $matches[1], 'UTF-32', 'UTF-8' ) );
				$hexLength = \strlen( $hex ) / 8;
			    $chunks = array();
			    for ( $i = 0; $i < $hexLength; ++$i )
			    {
			        $tmp = \substr( $hex, $i * 8, 8 );
			        		
			        $copy = false;
				    $len = \strlen( $tmp );
				    $res = '';
				    for ( $j = 0; $j < $len; ++$j )
				    {
				        $ch = $tmp[ $j ];
				        if ( !$copy )
				        {
				            if ( $ch != '0' )
				            {
				                $copy = true;
				            }
				            else if ( ( $i + 1 ) == $len )
				            {
				                $res = '0';
				            }
				        }
				        if ( $copy )
				        {
				            $res .= $ch;
				        }
				    }
				    
			        $chunks[ $i ] = $res;
			    }

				$image = implode( '-', $chunks );
				
				if ( \strstr( $image, '200d' ) === FALSE or $image === '1f441-fe0f-200d-1f5e8-fe0f' )
				{
					$image = str_replace( '-fe0f', '', $image );
				}
				if ( \in_array( $image, array( '0031-20e3', '0030-20e3', '0032-20e3', '0034-20e3', '0035-20e3', '0036-20e3', '0037-20e3', '0038-20e3', '0033-20e3', '0039-20e3', '0023-20e3', '002a-20e3', '00a9', '00ae' ) ) )
				{
					$image = str_replace( '00', '', $image );
				}
			    
				return '<img src="https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/'  . $image . '.png" class="ipsEmoji" alt="' . $matches[1] . '">';
			}, $output );
		}
		return $output;
	}
	
	/**
	 * Show Offline
	 *
	 * @return	void
	 */
	public function showOffline()
	{
		$this->bodyClasses[] = 'ipsLayout_minimal';
		$this->bodyClasses[] = 'ipsLayout_minimalNoHome';
		
		$this->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->offline( \IPS\Settings::i()->site_offline_message );
		$this->title  = \IPS\Settings::i()->board_name;
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;

		\IPS\Dispatcher\Front::i()->checkMfa();

		/* Unset page token */
		unset( \IPS\Output::i()->jsVars['page_token'] );

		$this->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( $this->title, $this->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 503 );
	}

	/**
	 * Show Banned
	 *
	 * @return	void
	 */
	public function showBanned()
	{
		$ipBanned = \IPS\Request::i()->ipAddressIsBanned();
		$banEnd = \IPS\Member::loggedIn()->isBanned();

		$message = 'member_banned';
		if ( !$ipBanned and $banEnd instanceof \IPS\DateTime )
		{
			$message = \IPS\Member::loggedIn()->language()->addToStack( 'member_banned_temp', FALSE, array( 'htmlsprintf' => array( $banEnd->html() ) ) );
		}

		$member = \IPS\Member::loggedIn();
		$warnings = NULL;

		if( $member->member_id )
		{
			try
			{
				$warningCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_members_warn_logs', array( 'wl_member = ?', $member->member_id ) )->first();

				if( $warningCount )
				{
					$warnings = new \IPS\Helpers\Table\Content( 'IPS\core\Warnings\Warning', \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&id={$member->member_id}", 'front', 'warn_list', $member->members_seo_name ), array( array( 'wl_member=?', $member->member_id ) ) );
					$warnings->rowsTemplate	  = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'warningRow' );
				}
			}
			catch ( \UnderflowException $e ){}
		}

		$this->bodyClasses[] = 'ipsLayout_minimal';
		$this->bodyClasses[] = 'ipsLayout_minimalNoHome';

		$this->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->banned( $message, $warnings, $banEnd );
		$this->title  = \IPS\Settings::i()->board_name;

		\IPS\Output::i()->sidebar['enabled'] = FALSE;

		/* Unset page token */
		unset( \IPS\Output::i()->jsVars['page_token'] );

		$this->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( $this->title, $this->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 403, 'text/html', array(), FALSE );
	}

	/**
	 * Checks and rebuilds JS map if it is broken
	 *
	 * @param	string	$app	Application
	 * @return	void
	 */
	protected function _checkJavascriptMap( $app )
	{
		$javascriptObjects = ( isset( \IPS\Data\Store::i()->javascript_map ) ) ? \IPS\Data\Store::i()->javascript_map : array();

		if ( ! \is_array( $javascriptObjects ) OR ! \count( $javascriptObjects ) OR ! isset( $javascriptObjects[ $app ] ) )
		{
			/* Map is broken or missing, recompile all JS */
			\IPS\Output\Javascript::compile( $app );
		}
	}

	/**
	 * @brief	JSON-LD structured data
	 */
	public $jsonLd	= array();

	/**
	 * Fetch meta tags for the current page.  Must be called before sendOutput() in order to reset title.
	 *
	 * @return	void
	 */
	public function buildMetaTags()
	{
		/* Set basic ones */
		$this->metaTags['og:site_name'] = \IPS\Settings::i()->board_name;
		$this->metaTags['og:locale'] = preg_replace( "/^([a-zA-Z0-9\-_]+?)(?:\..*?)$/", "$1", \IPS\Member::loggedIn()->language()->short );
		
		/* Add the site name to the title */
		if( \IPS\Settings::i()->board_name )
		{
			$this->title .= ' - ' . \IPS\Settings::i()->board_name;
		}

		$this->defaultPageTitle	= $this->title;
		
		/* Add Admin-specified ones */
		if( !$this->metaTagsUrl )
		{
			$this->metaTagsUrl	= ( \IPS\Request::i()->url() instanceof \IPS\Http\Url\Friendly ) ? \IPS\Request::i()->url()->friendlyUrlComponent : '';

			if ( isset( \IPS\Data\Store::i()->metaTags ) )
			{
				$rows = \IPS\Data\Store::i()->metaTags;
			}
			else
			{
				$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'core_seo_meta' ) );
				\IPS\Data\Store::i()->metaTags = $rows;
			}
						
			if( \is_array( $rows ) )
			{
				/* We duplicate these so we can know what is generated automatically for the live meta tag editor */
				$this->autoMetaTags = $this->metaTags;

				$rootPath = \IPS\Http\Url::external( \IPS\Settings::i()->base_url )->data['path'];

				foreach ( $rows as $row )
				{
					if( \strpos( $row['meta_url'], '*' ) !== FALSE )
					{
						if( preg_match( "#^" . str_replace( '\*', '(.*)', trim( preg_quote( $row['meta_url'], '#' ), '/' ) ) . "$#i", trim( $this->metaTagsUrl, '/' ) ) )
						{
							$_tags	= json_decode( $row['meta_tags'], TRUE );
		
							if( \is_array( $_tags ) )
							{
								foreach( $_tags as $_tagName => $_tagContent )
								{
									if( $_tagContent === NULL )
									{
										unset( $this->metaTags[ $_tagName ] );
									}
									else
									{
										$this->metaTags[ $_tagName ]		= $_tagContent;
									}

									$this->customMetaTags[ $_tagName ]	= $_tagContent;
								}
							}
		
							/* Are we setting page title? */
							if( $row['meta_title'] )
							{
								$this->title			= $row['meta_title'];
								$this->metaTagsTitle	= $row['meta_title'];
							}
						}
					}
					else
					{
						if( trim( $row['meta_url'], '/' ) == trim( $this->metaTagsUrl, '/' ) and ( ( $row['meta_url'] == '/' and \IPS\Request::i()->url()->data['path'] == $rootPath ) or $row['meta_url'] !== '/' ) )
						{
							$_tags	= json_decode( $row['meta_tags'], TRUE );
							
							if ( \is_array( $_tags ) )
							{
								foreach( $_tags as $_tagName => $_tagContent )
								{
									if( $_tagContent === NULL )
									{
										unset( $this->metaTags[ $_tagName ] );
									}
									else
									{
										$this->metaTags[ $_tagName ]		= $_tagContent;
									}

									$this->customMetaTags[ $_tagName ]	= $_tagContent;
								}
							}
							
							/* Are we setting page title? */
							if( $row['meta_title'] )
							{
								$this->title			= $row['meta_title'];
								$this->metaTagsTitle	= $row['meta_title'];
							}
						}
					}
				}
			}
		}
		
		$baseUrl = parse_url( \IPS\Settings::i()->base_url );	

		foreach( $this->metaTags as $name => $value )
		{
			if ( ! \is_array( $value ) )
			{
				$value = array( $value );
			}
			
			foreach( $value as $tag )
			{
				if ( mb_substr( $tag, 0, 2 ) === '//' )
				{
					/* Try to preserve http vs https */
					if( isset( $baseUrl['scheme'] ) )
					{
						$tag = str_replace( '//', $baseUrl['scheme'] . '://', $tag );
					}
					else
					{
						$tag = str_replace( '//', 'http://', $tag );
					}
					
					$this->metaTags[ $name ] = $tag;
				}
			}
		}

		/* Automatically generate JSON-LD markup */
		$mainSiteUrl = ( \IPS\Settings::i()->site_site_elsewhere and \IPS\Settings::i()->site_main_url ) ? \IPS\Settings::i()->site_main_url : \IPS\Settings::i()->base_url;
		$mainSiteTitle = ( \IPS\Settings::i()->site_site_elsewhere and \IPS\Settings::i()->site_main_title ) ? \IPS\Settings::i()->site_main_title : \IPS\Settings::i()->board_name;
		$jsonLd = array(
			'website'		=> array(
				'@context'	=> "http://www.schema.org",
				'publisher' => \IPS\Settings::i()->base_url . '#organization',
				'@type'		=> "WebSite",
				'@id' 		=> \IPS\Settings::i()->base_url . '#website',
	            'mainEntityOfPage' => \IPS\Settings::i()->base_url,
				'name'		=> \IPS\Settings::i()->board_name,
				'url'		=> \IPS\Settings::i()->base_url,
				'potentialAction'	=> array(
					'type'			=> "SearchAction",
					'query-input'	=> "required name=query",
					'target'		=> urldecode( (string) \IPS\Http\Url::internal( "app=core&module=search&controller=search", "front", "search" )->setQueryString( "q", "{query}" ) ),
				),
				'inLanguage'		=> array()
			),
			'organization'	=> array(
				'@context'	=> "http://www.schema.org",
				'@type'		=> "Organization",
				'@id' 		=> $mainSiteUrl . '#organization',
	            'mainEntityOfPage' => $mainSiteUrl,
				'name'		=> $mainSiteTitle,
				'url'		=> $mainSiteUrl,
			)
		);

		if( $logo = \IPS\Theme::i()->logo_front )
		{
			$jsonLd['organization']['logo'] = array(
				'@type' => 'ImageObject',
	            '@id'   => \IPS\Settings::i()->base_url . '#logo',
	            'url'   => (string) $logo
			);
		}

		if( \IPS\Settings::i()->site_social_profiles AND $links = json_decode( \IPS\Settings::i()->site_social_profiles, TRUE ) AND \count( $links ) )
		{
			if( !isset( $jsonLd['organization']['sameAs'] ) )
			{
				$jsonLd['organization']['sameAs'] = array();
			}

			foreach( $links as $link )
			{
				$jsonLd['organization']['sameAs'][]	= $link['key'];
			}
		}

		if( \IPS\Settings::i()->site_address AND $address = \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->site_address ) )
		{
			if ( ! empty( $address->country ) )
			{
				$jsonLd['organization']['address'] = array(
					'@type'				=> 'PostalAddress',
					'streetAddress'		=> implode( ', ', $address->addressLines ),
					'addressLocality'	=> $address->city,
					'addressRegion'		=> $address->region,
					'postalCode'		=> $address->postalCode,
					'addressCountry'	=> $address->country,
				);
			}
		}

		foreach( \IPS\Lang::getEnabledLanguages() as $language )
		{
			$jsonLd['website']['inLanguage'][] = array(
				'@type'		=> "Language",
				'name'		=> $language->title,
				'alternateName'	=> $language->bcp47()
			);
		}

		/* Add breadcrumbs */
		if( \count( $this->breadcrumb ) )
		{

			$position	= 1;
			$elements	= [];

			foreach( $this->breadcrumb as $breadcrumb )
			{
				if( $breadcrumb[0] )
				{
					$elements[] = array(
						'@type'		=> "ListItem",
						'position'	=> $position,
						'item'		=> array(
							'@id'	=> (string) $breadcrumb[0],
							'name'	=> $breadcrumb[1],
						)
					);

					$position++;
				}
			}

			if( \count( $elements ) )
			{
				$jsonLd['breadcrumbs'] = array(
					'@context'	=> "http://schema.org",
					'@type'		=> "BreadcrumbList",
					'itemListElement'	=> $elements,
				);
			}
		}

		if( \IPS\Member::loggedIn()->canUseContactUs() )
		{
			$jsonLd['contact'] = array(
				'@context'	=> "http://schema.org",
				'@type'		=> "ContactPage",
				'url'		=> urldecode( (string) \IPS\Http\Url::internal( "app=core&module=contact&controller=contact", "front", "contact" ) ),
			);
		}
		
		$this->jsonLd	= array_merge( $this->jsonLd, $jsonLd );
	}

	/**
	 * @brief	Global search menu options
	 */
	protected $globalSearchMenuOptions	= NULL;
	
	/**
	 * @brief	Contextual search menu options
	 */
	public $contextualSearchOptions = array();
	
	/**
	 * @brief	Default search option
	 */
	public $defaultSearchOption	= array( 'all', 'search_everything' );

	/**
	 * Retrieve options for search menu
	 *
	 * @return	array
	 */
	public function globalSearchMenuOptions()
	{
		if( $this->globalSearchMenuOptions === NULL )
		{
			foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
			{
				if( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
				{
					if ( $class::includeInSiteSearch() )
					{
						$type	= mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) );
						$this->globalSearchMenuOptions[ $type ] = $type . '_pl';
					}
				}
			}
		}
		
		/* This is also supported, but is not a content item class implementing \Searchable */
		if ( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) )
		{
			$this->globalSearchMenuOptions['core_members'] = 'core_members_pl';
		}

		return $this->globalSearchMenuOptions;
	}

	/**
	 * Include a file and return the output
	 *
	 * @param	string	$path	Path or URL
	 * @return	string
	 */
	public static function safeInclude( $path )
	{
		ob_start();
		include( \IPS\ROOT_PATH . DIRECTORY_SEPARATOR . $path );
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}
	
	/**
	 * Get any inline message
	 *
	 * @return	string
	 */
	protected static function getInlineMessage()
	{
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation == 'front' and \IPS\Session\Front::loggedIn() ) # Don't attempt to initiate a full member object here
		{
			return ( ( isset( $_SESSION['inlineMessage'] ) and ! empty( $_SESSION['inlineMessage'] ) ) ? $_SESSION['inlineMessage'] : NULL );
		}
		else if ( isset( \IPS\Request::i()->cookie['inlineMessage'] ) )
		{
			return \IPS\Request::i()->cookie['inlineMessage'];
		}
		
		return NULL; 
	}
	
	/**
	 * Set an inline message
	 *
	 * @param	string	$message	The message
	 * @return	void
	 */
	protected static function setInlineMessage( $message=NULL )
	{
		if ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation == 'front' and \IPS\Session\Front::loggedIn() ) # Don't attempt to initiate a full member object here
		{
			$_SESSION['inlineMessage'] = $message;
		}
		else
		{
			\IPS\Request::i()->setCookie( 'inlineMessage', $message );
		}
	}
}
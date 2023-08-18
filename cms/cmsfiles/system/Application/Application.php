<?php
/**
 * @brief		Application Class
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
 * @brief	Abstract class that applications extend and use to handle application data
 */
class _Application extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Have fetched all?
	 */
	protected static $gotAll	= FALSE;

	/**
	 * @brief	Defined versions
	 */
	protected $definedVersions	= NULL;

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = '__app_';

	/**
	 * @brief	Defined theme locations for the theme system
	 */
	public $themeLocations = array('admin', 'front', 'global');

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'updatecount_applications' );
	
	/**
	 * Set default
	 *
	 * @return void
	 */
	public function setAsDefault()
	{
		/* Update any FURL customizations */
		if ( \IPS\Settings::i()->furl_configuration )
		{
			$furlCustomizations = json_decode( \IPS\Settings::i()->furl_configuration, TRUE );
	
			try
			{
				/* Add the top-level directory to all the FURLs for the old default app */
				$previousDefaultApp = static::constructFromData( \IPS\Db::i()->select( '*', 'core_applications', 'app_default=1' )->first() );
				if( file_exists( $previousDefaultApp->getApplicationPath()  . "/data/furl.json" ) )
				{
					$oldDefaultAppDefinition = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( $previousDefaultApp->getApplicationPath() . "/data/furl.json" ) ), TRUE );
					if ( $oldDefaultAppDefinition['topLevel'] )
					{
						foreach ( $oldDefaultAppDefinition['pages'] as $k => $data )
						{
							if ( isset( $furlCustomizations[ $k ] ) )
							{
								$furlCustomizations[ $k ] = \IPS\Http\Url\Friendly::buildFurlDefinition( $furlCustomizations[ $k ]['friendly'], $furlCustomizations[ $k ]['real'], $oldDefaultAppDefinition['topLevel'], FALSE, isset( $furlCustomizations[ $k ]['alias'] ) ? $furlCustomizations[ $k ]['alias'] : NULL, isset( $furlCustomizations[ $k ]['custom'] ) ? $furlCustomizations[ $k ]['custom'] : FALSE, isset( $furlCustomizations[ $k ]['verify'] ) ? $furlCustomizations[ $k ]['verify'] : NULL );
							}
						}
					}
				}
			}
			catch ( \UnderflowException $e ){}
	
			
			/* And remove it from the new */
			if( file_exists( $this->getApplicationPath() . "/data/furl.json" ) )
			{
				$newDefaultAppDefinition = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( $this->getApplicationPath() . "/data/furl.json" ) ), TRUE );
				if ( $newDefaultAppDefinition['topLevel'] )
				{
					foreach ( $newDefaultAppDefinition['pages'] as $k => $data )
					{
						if ( isset( $furlCustomizations[ $k ] ) )
						{
							$furlCustomizations[ $k ] = \IPS\Http\Url\Friendly::buildFurlDefinition( rtrim( preg_replace( '/^' . preg_quote( $newDefaultAppDefinition['topLevel'], '/' ) . '\/?/', '', $furlCustomizations[ $k ]['friendly'] ), '/' ), $furlCustomizations[ $k ]['real'], $newDefaultAppDefinition['topLevel'], TRUE, isset( $furlCustomizations[ $k ]['alias'] ) ? $furlCustomizations[ $k ]['alias'] : NULL, isset( $furlCustomizations[ $k ]['custom'] ) ? $furlCustomizations[ $k ]['custom'] : FALSE, isset( $furlCustomizations[ $k ]['verify'] ) ? $furlCustomizations[ $k ]['verify'] : NULL );
						}
					}
				}
			}
					
			/* Save the new FURL customisation */		
			\IPS\Settings::i()->changeValues( array( 'furl_configuration' => json_encode( $furlCustomizations ) ) );
		}

		foreach( \IPS\Application::applications() as $directory => $application )
		{
			if( $application->default )
			{
				static::removeMetaPrefix( $application );
				break;
			}
		}

		static::addMetaPrefix( $this );
		
		/* Actually update the database */
		\IPS\Db::i()->update( 'core_applications', array( 'app_default' => 0 ) );
		\IPS\Db::i()->update( 'core_applications', array( 'app_default' => 1 ), array( 'app_id=?', $this->id ) );
		
		/* Clear cached data */
		unset( \IPS\Data\Store::i()->applications );
		unset( \IPS\Data\Store::i()->furl_configuration );
		\IPS\Member::clearCreateMenu();

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
	}
	
	/**
	 * Get Applications
	 *
	 * @return	array<\IPS\Application>
	 */
	public static function applications(): array
	{
		if( static::$gotAll === FALSE )
		{
			static::$multitons = array();
			
			foreach ( static::getStore() as $row )
			{
				try
				{
					static::$multitons[ $row['app_directory'] ] = static::constructFromData( $row );
				}
				catch( \UnexpectedValueException $e )
				{
					if ( mb_stristr( $e->getMessage(), 'Missing:' ) )
					{
						/* Ignore this, the app is in the table, but not 4.0 compatible */
						continue;
					}
				}
			}
			
			static::$gotAll = TRUE;
		}
		
		return static::$multitons;
	}

	/**
	 * Get data store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->applications ) )
		{
			\IPS\Data\Store::i()->applications = iterator_to_array( \IPS\Db::i()->select( '*', 'core_applications', NULL, 'app_position' ) );
		}
		
		return \IPS\Data\Store::i()->applications;
	}

	/**
	 * Get enabled applications
	 *
	 * @return	array<\IPS\Application>
	 */
	public static function enabledApplications(): array
	{
		$applications	= static::applications();
		$enabled		= array();

		foreach( $applications as $key => $application )
		{
			if( $application->enabled )
			{
				$enabled[ $key ] = $application;
			}
		}
		
		return $enabled;
	}
	
	/**
	 * Does an application exist and is it enabled? Note: does not check if offline for a particular member
	 *
	 * @see		\IPS\Application::canAccess()
	 * @param	string	$key	Application key
	 * @return	bool
	 */
	public static function appIsEnabled( $key )
	{
		$applications = static::applications();
		
		if ( !array_key_exists( $key, $applications ) )
		{
			return FALSE;
		}

		return $applications[ $key ]->enabled;
	}
	 
	/**
	 * Load Record
	 *
	 * @see		\IPS\Db::build
	 * @param	int|string	$id					ID
	 * @param	string		$idField			The database column that the $id parameter pertains to (NULL will use static::$databaseColumnId)
	 * @param	mixed		$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	static
	 * @throws	\InvalidArgumentException
	 * @throws	\OutOfRangeException
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		static::applications(); // Load all applications so we can grab the data from the cache
		return parent::load( $id, $idField, $extraWhereClause );
	}

	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @param	array|NULL			$limit				Limit/offset to use, or NULL for no limit (default)
	 * @note	This is overridden to prevent UnexpectedValue exceptions when there is an old application record in core_applications without an Application.php file
	 * @return	array
	 */
	public static function roots( $permissionCheck='view', $member=NULL, $where=array(), $limit=NULL )
	{
		return static::applications();
	}

	/**
	 * Get all extensions
	 *
	 * @param	\IPS\Application|string					$app				The app key of the application which owns the extension
	 * @param	string									$extension			Extension Type
	 * @param	\IPS\Member|\IPS\Member\Group|bool		$checkAccess		Check access permission for application against supplied member/group (or logged in member, if TRUE) before including extension
	 * @param	string|NULL								$firstApp			If specified, the application with this key will be returned first
	 * @param	string|NULL								$firstExtensionKey	If specified, the extension with this key will be returned first
	 * @param	bool									$construct			Should an object be returned? (If false, just the classname will be returned)
	 * @return	array
	 */
	public static function allExtensions( $app, $extension, $checkAccess=TRUE, $firstApp=NULL, $firstExtensionKey=NULL, $construct=TRUE )
	{
		$extensions = array();
	
		/* Get applications */
		$apps = static::applications();

		if ( $firstApp !== NULL )
		{
			$apps = static::$multitons;

			usort( $apps, function( $a, $b ) use ( $firstApp )
			{
				if ( $a->directory === $firstApp )
				{
					return -1;
				}
				if ( $b->directory === $firstApp )
				{
					return 1;
				}
				return 0;
			} );
		}
		
		/* Get extensions */
		foreach ( $apps as $application )
		{
			if ( !static::appIsEnabled( $application->directory ) )
			{
				continue;
			}
						
			if( $checkAccess !== FALSE )
			{
				if( !$application->canAccess( $checkAccess === TRUE ? NULL : $checkAccess ) )
				{
					continue;
				}
			}

			$_extensions = array();
			
			foreach ( $application->extensions( $app, $extension, $construct, $checkAccess ) as $key => $class )
			{
				$_extensions[ $application->directory . '_' . $key ] = $class;
			}

			if ( $firstExtensionKey !== NULL AND array_key_exists( $application->directory . '_' . $firstExtensionKey, $_extensions ) )
			{
				uksort( $_extensions, function( $a, $b ) use ( $application, $firstExtensionKey )
				{
					if ( $a === $application->directory . '_' . $firstExtensionKey )
					{
						return -1;
					}
					if ( $b === $application->directory . '_' . $firstExtensionKey )
					{
						return 1;
					}
					return 0;
				} );
			}

			$extensions = array_merge( $extensions, $_extensions );
		}
		
		/* Return */
		return $extensions;
	}

	/**
	 * Retrieve a list of applications that contain a specific type of extension
	 *
	 * @param	\IPS\Application|string		$app				The app key of the application which owns the extension
	 * @param	string						$extension			Extension Type
	 * @param	\IPS\Member|bool			$checkAccess		Check access permission for application against supplied member (or logged in member, if TRUE) before including extension
	 * @return	array
	 */
	public static function appsWithExtension( $app, $extension, $checkAccess=TRUE )
	{
		$_apps	= array();

		foreach( static::applications() as $application )
		{
			if ( static::appIsEnabled( $application->directory ) )
			{
				/* If $checkAccess is false we don't verify access to the app */
				if( $checkAccess !== FALSE )
				{
					/* If we passed true, we want to check current member, otherwise pass the member in directly */
					if( $application->canAccess( ( $checkAccess === TRUE ) ? NULL : $checkAccess ) !== TRUE )
					{
						continue;
					}
				}

				if( \count( $application->extensions( $app, $extension ) ) )
				{
					$_apps[ $application->directory ] = $application;
				}
			}
		}

		return $_apps;
	}
	
	/**
	 * Get available version for an application
	 * Used by the installer/upgrader
	 *
	 * @param	string		$appKey	The application key
	 * @param	bool		$human	Return the human-readable version instead
	 * @return	int|null
	 */
	public static function getAvailableVersion( $appKey, $human=FALSE )
	{
		$versionsJson = static::getRootPath( $appKey ) . "/applications/{$appKey}/data/versions.json";

		$_versions	= $human ? array_values( json_decode( file_get_contents( $versionsJson ), TRUE ) ) : array_keys( json_decode( file_get_contents( $versionsJson ), TRUE ) );
		if ( file_exists( $versionsJson ) and $versionsJson = $_versions )
		{
			return array_pop( $versionsJson );
		}
		
		return NULL;
	}

	/**
	 * Get all defined versions for an application
	 *
	 * @return	array
	 */
	public function getAllVersions()
	{
		if( $this->definedVersions !== NULL )
		{
			return $this->definedVersions;
		}

		$this->definedVersions	= array();

		$versionsJson = $this->getApplicationPath() . "/data/versions.json";

		if ( file_exists( $versionsJson ) )
		{
			$this->definedVersions	= json_decode( file_get_contents( $versionsJson ), TRUE );
		}
		
		return $this->definedVersions;
	}
	
	/**
	 * Return the human version of an INT long version
	 *
	 * @param 	int 	$longVersion	Long version (10001)
	 * @return	string|false			Long Version (1.1.1 Beta 1)
	 */
	public function getHumanVersion( $longVersion )
	{
		$this->getAllVersions();
		
		if ( isset( $this->definedVersions[ $longVersion ] ) )
		{
			return $this->definedVersions[ (int) $longVersion ];
		}
		
		return false;
	}
	
	/**
	 * The available version we can upgrade to
	 *
	 * @param	bool	$latestOnly				If TRUE, will return the latest version only
	 * @param	bool	$skipSameHumanVersion	If TRUE, will not include any versions with the same "human" version number as the current version
	 * @return	array
	 */
	public function availableUpgrade( $latestOnly=FALSE, $skipSameHumanVersion=TRUE )
	{
		$update = array();
		
		if( ( $versions = json_decode( $this->update_version, TRUE ) ) AND is_iterable( $versions ) )
		{
			if ( \is_array( $versions ) and !isset( $versions[0] ) and isset( $versions['longversion'] ) )
			{
				$versions = array( $versions );
			}

			$update = array();
			foreach ( $versions as $data )
			{
				if( !empty( $data['longversion'] ) and $data['longversion'] > $this->long_version and ( !$skipSameHumanVersion or $data['version'] != $this->version ) )
				{
					if( $data['released'] AND ( (int) $data['released'] != $data['released'] OR \strlen($data['released']) != 10 ) )
					{
						$data['released']	= strtotime( $data['released'] );
					}
						
					$update[]	= $data;
				}
			}
		}

		if ( !empty( $update ) and $latestOnly )
		{
			$update = array_pop( $update );
		} 

		return $update;
	}

	/**
	 * The latest new feature ID
	 *
	 * @return	int|null
	 */
	public function newFeature()
	{
		if( $this->update_version )
		{
			$versions = json_decode( $this->update_version, TRUE );
			if ( \is_array( $versions ) and !isset( $versions[0] ) and isset( $versions['longversion'] ) )
			{
				$versions = array( $versions );
			}

			$latestVersion	= NULL;

			foreach ( $versions as $data )
			{
				if( isset( $data['latestNewFeature'] ) AND $data['latestNewFeature'] AND $data['latestNewFeature'] > $latestVersion )
				{
					$latestVersion	= $data['latestNewFeature'];
				}
			}

			return $latestVersion;
		}

		return NULL;
	}

	/**
	 * Is the application up to date with security patches?
	 *
	 * @return	bool
	 */
	public function missingSecurityPatches()
	{
		$updates = $this->availableUpgrade();
		if( !empty( $updates ) )
		{
			foreach( $updates as $update )
			{
				if( $update['security'] )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}
	
	/**
	 * MD5 check (returns path to files which do not match)
	 *
	 * @param	int|NULL	$version	Version to check against
	 * @return	array
	 * @throws	\IPS\Http\Request\Exception
	 */
	public static function md5Check( $version = NULL )
	{		
		/* For Community in the Cloud customers we cannot do this because they have encoded files
			and the encoder produces different output each time it runs, so every Cloud customer
			has different files, even for the same version */
		$key = \IPS\IPS::licenseKey();
		if ( \IPS\CIC OR $key['cloud'] )
		{
			return array();
		}
		
		/* For everyone else, get the correct md5 sums for each file... */
		$url = \IPS\Http\Url::ips( 'md5' );
		if ( $version !== NULL )
		{
			$url = $url->setQueryString( 'version', $version );
		} 
		$correctMd5s = $url->request()->get()->decodeJson();
				
		/* And return whichever ones don't match */
		$return = array();
		foreach ( $correctMd5s as $file => $md5Hash )
		{
			/* Fix the admin directory */
			$file = preg_replace( '/^\/admin\//', '/' . \IPS\CP_DIRECTORY . '/', $file );
						
			/* If this is an application directory but the application doesn't exist or has been disabled then we shouldn't check it */
			preg_match( '/^\/applications\/(.+?)\//', $file, $matches );
			if ( $matches )
			{
				if( \in_array( $matches[1], \IPS\IPS::$ipsApps ) AND !static::appIsEnabled( $matches[1] ) )
				{
					continue;
				}
			}
			
			/* Ignore init.php (which always changes on build) and conf_global.dist.php (which doesn't exist after install) */
			if ( \in_array( $file, array( '/init.php', '/conf_global.dist.php' ) ) )
			{
				continue;
			}
			
			/* If the file doesn't exist at all, flag it */
			if ( !file_exists( \IPS\ROOT_PATH . $file ) )
			{
				$return[] = \IPS\ROOT_PATH . $file;
			}
			/* Otherwise, compare the md5 hashes... */
			else
			{
				/* Get the contents. If you can't get the contents, it may be that the file permissions are set wrong. Try to fix. */
				$fileContents = @file_get_contents( \IPS\ROOT_PATH . $file );
				if ( !$fileContents )
				{
					@chmod( \IPS\ROOT_PATH . $file, \IPS\FILE_PERMISSION_NO_WRITE );
				}
				
				/* If we got the file contents... */
				if ( $fileContents )
				{
					/* Strip whitespace since FTP in ASCII mode will change the whitespace characters */
					$fileContents = preg_replace( '#\s#', '', utf8_decode( file_get_contents( \IPS\ROOT_PATH . $file ) ) );
					
					/* Compare */
					if ( md5( $fileContents ) != $md5Hash )
					{
						$return[] = \IPS\ROOT_PATH . $file;
					}
				}
				
				/* Otherwise, flag it */
				else
				{
					$return[] = \IPS\ROOT_PATH . $file;
				}
			}			
		}
		
		return $return;
	}
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_applications';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'app_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'directory';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'app_id', 'app_marketplace_id' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
		
	/**
	 * @brief	[Node] Subnode class
	 */
	public static $subnodeClass = 'IPS\Application\Module';
	
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'applications_and_modules';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 */
	protected static $restrictions = array( 'app' => 'core', 'module' => 'applications', 'prefix' => 'app_' );
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		/* Load class */
		if( !file_exists( static::getRootPath( $data['app_directory'] ) . '/applications/' . $data['app_directory'] . '/Application.php' ) )
		{
			/* If you are upgrading and you have an application "123flashchat" this causes a PHP error, so just die out now */
			if( !\in_array( mb_strtolower( mb_substr( $data['app_directory'], 0, 1 ) ), range( 'a', 'z' ) ) )
			{
				throw new \UnexpectedValueException( "Missing: " . '/applications/' . $data['app_directory'] . '/Application.php' );
			}

			if( !\IPS\Dispatcher::hasInstance() OR \IPS\Dispatcher::i()->controllerLocation !== 'setup' )
			{
				throw new \UnexpectedValueException( "Missing: " . '/applications/' . $data['app_directory'] . '/Application.php' );
			}
			else
			{
				$className = "\\IPS\\{$data['app_directory']}\\Application";

				if( !class_exists( $className ) )
				{
					$code = <<<EOF
namespace IPS\\{$data['app_directory']};
class Application extends \\IPS\\Application{}
EOF;
					eval( $code );
				}
			}
		}
		else
		{
			require_once static::getRootPath( $data['app_directory'] ) . '/applications/' . $data['app_directory'] . '/Application.php';
		}

		/* Initiate an object */
		$classname = 'IPS\\' . $data['app_directory'] . '\\Application';
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		if ( static::$databasePrefix )
		{
			$databasePrefixLength = \strlen( static::$databasePrefix );
		}
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix )
			{
				$k = \substr( $k, $databasePrefixLength );
			}
			
			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
				
		/* Return */
		return $obj;
	}
	
	/**
	 * @brief	Modules Store
	 */
	protected $modules = NULL;
	
	/**
	 * Get Modules
	 *
	 * @see		static::$modules
	 * @param	string	$location	Location (e.g. "admin" or "front")
	 * @return	array
	 */
	public function modules( $location=NULL )
	{
		/* Don't have an instance? */
		if( $this->modules === NULL )
		{
			$modules = \IPS\Application\Module::modules();
			$this->modules = array_key_exists( $this->directory, $modules ) ? $modules[ $this->directory ] : array();
		}
		
		/* Return */
		return isset( $this->modules[ $location ] ) ? $this->modules[ $location ] : array();
	}
	
	/**
	 * Returns the ACP Menu JSON for this application.
	 *
	 * @return array
	 */
	public function acpMenu()
	{
		return json_decode( file_get_contents( $this->getApplicationPath() . "/data/acpmenu.json" ), TRUE );
	}
	
	/**
	 * ACP Menu Numbers
	 *
	 * @param	array	$queryString	Query String
	 * @return	int
	 */
	public function acpMenuNumber( $queryString )
	{
		return 0;
	}
	
	/**
	 * Get Extensions
	 *
	 * @param	\IPS\Application|string					$app		    The app key of the application which owns the extension
	 * @param	string									$extension	    Extension Type
	 * @param	bool									$construct	    Should an object be returned? (If false, just the classname will be returned)
	 * @param	\IPS\Member|\IPS\Member\Group|bool		$checkAccess	Check access permission for extension against supplied member/group (or logged in member, if TRUE)
	 * @return	array
	 */
	public function extensions( $app, $extension, $construct=TRUE, $checkAccess=FALSE )
	{		
		$app = ( \is_string( $app ) ? $app : $app->directory );
		
		$classes = array();
		$jsonFile = $this->getApplicationPath() . "/data/extensions.json";
				
		/* New extensions.json based approach */
		if ( file_exists( $jsonFile ) and $json = @json_decode( \file_get_contents( $jsonFile ), TRUE ) )
		{
			if ( isset( $json[ $app ] ) and isset( $json[ $app ][ $extension ] ) )
			{
				foreach ( $json[ $app ][ $extension ] as $name => $classname )
				{
					if ( method_exists( $classname, 'generate' ) )
					{
						$classes = array_merge( $classes, $classname::generate() );
					}
					elseif ( !$construct )
					{
						$classes[ $name ] = $classname;
					}
					else
					{
						try
						{							
							$classes[ $name ] = new $classname( $checkAccess === TRUE ? \IPS\Member::loggedIn() : ( $checkAccess === FALSE ? NULL : $checkAccess ) );
						}
						catch( \RuntimeException | \OutOfRangeException $e ){}
					}
				}
			}
		}
				
		return $classes;
	}

	/**
	 * [Node] Get Node Title
	 *
	 * @return	string
	 */
	protected function get__title()
	{
		$key = "__app_{$this->directory}";
		return \IPS\Member::loggedIn()->language()->addToStack( $key );
	}
	
	/**
	 * [Node] Get Node Icon
	 *
	 * @return	string
	 */
	protected function get__icon()
	{
		return 'cubes';
	}
			
	/**
	 * [Node] Does this node have children?
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	bool				$subnodes			Include subnodes?
	 * @param	array				$_where				Additional WHERE clause
	 * @return	bool
	 */
	public function hasChildren( $permissionCheck='view', $member=NULL, $subnodes=TRUE, $_where=array() )
	{
		return $subnodes;
	}

	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if( \IPS\NO_WRITES or !static::restrictionCheck( 'delete' ) )
		{
			return FALSE;
		}

		if( $this->_data['protected'] )
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;
	
	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app={$this->directory}" );
		}

		return $this->_url;
	}

	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	array(
	 				'icon.png'			// Path to icon
	 				'core'				// Application icon belongs to
	 			),
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @endcode
	 * @param	string	$url	Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		/* Get normal buttons */
		$buttons	= parent::getButtons( $url );
		$edit = NULL;
		$uninstall = NULL;
		if( \IPS\IN_DEV and isset( $buttons['edit'] ) )
		{
			$edit = $buttons['edit'];
		}
		unset( $buttons['edit'] );
		unset( $buttons['copy'] );
		if( isset( $buttons['delete'] ) )
		{
			$buttons['delete']['title']	= 'uninstall';
			$buttons['delete']['data']	= array( 'delete' => '', 'delete-warning' => \IPS\Member::loggedIn()->language()->addToStack( \IPS\IN_DEV ? 'app_files_indev_uninstall' : 'app_files_delete_uninstall') );
			
			$uninstall = $buttons['delete'];
			unset( $buttons['delete'] );
		}
		
		/* Default */
		if( $this->enabled AND \count( $this->modules( 'front' ) ) )
		{
			$buttons['default']	= array(
				'icon'		=> $this->default ? 'star' : 'star-o',
				'title'		=> 'make_default_app',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$this->_id}&do=setAsDefault" )->csrf(),
			);
		}
		
		/* Online/offline */
		if( !$this->protected )
		{
			$buttons['offline']	= array(
				'icon'	=> 'lock', 
				'title'	=> 'permissions',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&id={$this->_id}&do=permissions" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-forceReload' => 'true', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('permissions') )
			);
		}
		
		/* View Details */
		$buttons['details']	= array(
			'icon'	=> 'search',
			'title'	=> 'app_view_details',
			'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&do=details&id={$this->_id}" ),
			'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('app_view_details') )
		);
		
		/* Upgrade */
		if( !$this->protected AND !\IPS\DEMO_MODE AND $this->marketplace_id === NULL AND \IPS\IPS::canManageResources() AND \IPS\IPS::checkThirdParty() )
		{
			$buttons['upgrade']	= array(
				'icon'	=> 'upload',
				'title'	=> 'upload_new_version',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$this->_id}&do=upload" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('upload_new_version') )
			);
		}
		
		/* Uninstall */
		if ( $uninstall )
		{
			$buttons['delete'] = $uninstall;
			$buttons['delete']['link'] = $buttons['delete']['link']->csrf();
			
			if ( $this->default )
			{
				$buttons['delete']['data'] = array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('uninstall') );
			}

			if ( !isset( $buttons['delete']['data'] ) )
			{
				$buttons['delete']['data'] = array();
			}
			$buttons['delete']['data'] = $buttons['delete']['data'] + array( 'noajax' => '' );
		}
				
		/* Developer */
		if( \IPS\IN_DEV )
		{			
			if ( $edit )
			{
				$buttons['edit'] = $edit;
			}

			if( !$this->marketplace_id )
			{
                $buttons['compilejs'] = array(
                    'icon'	=> 'cog',
                    'title'	=> 'app_compile_js',
                    'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=applications&appKey={$this->_id}&do=compilejs" )->csrf()
                );

                $buttons['build'] = array(
                    'icon' => 'cog',
                    'title' => 'app_build',
                    'link' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->_id}&do=build"),
                    'data' => array('ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('app_build'))
                );

                $buttons['export'] = array(
                    'icon' => 'download',
                    'title' => 'download',
                    'link' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->_id}&do=download"),
                    'data' => array('ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('download'), 'ipsDialog-remoteVerify' => 'false')
                );
            }

			$buttons['developer']	= array(
				'icon'	=> 'cogs',
				'title'	=> 'developer_mode',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=applications&controller=developer&appKey={$this->_id}" ),
			);
		}

		if( !\IPS\IN_DEV ) {

			$buttons['export'] = array(
		        'icon' => 'download',
		        'title' => 'download',
		        'link' => \IPS\Http\Url::internal("app=core&module=applications&controller=applications&appKey={$this->_id}&do=downloadMafia")
		    );
		}
		
		return $buttons;
	}
	
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		if ( $this->directory == 'core' )
		{
			return TRUE;
		}
		
		return $this->enabled and ( !\in_array( $this->directory, \IPS\IPS::$ipsApps ) or $this->version == \IPS\Application::load('core')->version );
	}

	/**
	 * [Node] Set whether or not this node is enabled
	 *
	 * @param	bool|int	$enabled	Whether to set it enabled or disabled
	 * @return	void
	 */
	protected function set__enabled( $enabled )
	{
		if ( \IPS\NO_WRITES )
	    {
			throw new \RuntimeException;
	    }
		
		$this->enabled = $enabled;
		$this->save();
		\IPS\Plugin\Hook::writeDataFile();

        /* Clear templates to rebuild automatically */
        \IPS\Theme::deleteCompiledTemplate();
		
		/* Invalidate disk templates */
		\IPS\Theme::resetAllCacheKeys();

		/* Enable queue task in case there are pending items */
		if( $this->enabled )
		{
			$queueTask = \IPS\Task::load( 'queue', 'key' );
			$queueTask->enabled = TRUE;
			$queueTask->save();
		}

		/* Update other app specific task statuses */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => (int) $this->enabled ), array( 'app=?', $this->directory ) );
	}
	
	/**
	 * [Node] Get whether or not this node is locked to current enabled/disabled status
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__locked()
	{
		if ( $this->directory == 'core' )
		{
			return TRUE;
		}
		
		if ( !$this->_enabled and \in_array( $this->directory, \IPS\IPS::$ipsApps ) and $this->version != \IPS\Application::load('core')->version )
		{
			return TRUE;
		}

		if ( $this->requires_manual_intervention )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * [Node] Lang string for the tooltip when this is locked
	 *
	 * @return string
	 */
	protected function get__lockedLang()
	{
		return $this->requires_manual_intervention ? 'invalid_php8_customization' : null;
	}
	
	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		return \IPS\Theme::i()->getTemplate( 'applications', 'core' )->appRowDescription( $this );
	}

	/**
	 * Get the Application State Description ( Offline , Offline for specific groups or all )
	 * @return mixed
	 */
	public function get__disabledMessage()
	{
		if ( $this->_locked and $this->directory != 'core' AND \in_array( $this->directory, \IPS\IPS::$ipsApps ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('app_force_disabled');
		}
		elseif ( $this->disabled_groups )
		{
			$groups = array();
			if ( $this->disabled_groups != '*' )
			{
				foreach ( explode( ',', $this->disabled_groups ) as $groupId )
				{
					try
					{
						$groups[] = \IPS\Member\Group::load( $groupId )->name;
					}
					catch ( \OutOfRangeException $e ) { }
				}
			}

			if ( empty( $groups ) )
			{
				return \IPS\Member::loggedIn()->language()->addToStack('app_offline_to_all');
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'app_offline_to_groups', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $groups ) ) ) );
			}
		}
	}

	/**
	 * Get the authors website
	 *
	 * @return \IPS\Http\Url|null
	 */
	public function website()
	{
		if ( $this->_data['website'] )
		{
			return \IPS\Http\Url::createFromString( $this->_data['website'] );
		}
		return NULL;
	}

	/**
	 * Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function get__badge()
	{
		if ( \IPS\CIC AND \IPS\IPS::isManaged() AND \in_array( $this->directory, \IPS\IPS::$ipsApps ) )
		{
			return NULL;
		}
		
		if ( $availableUpgrade = $this->availableUpgrade( TRUE ) )
		{
			return array(
				0	=> 'new',
				1	=> '',
				2	=> \IPS\Theme::i()->getTemplate( 'global', 'core' )->updatebadge( $availableUpgrade['version'], isset( $availableUpgrade['updateurl'] ) ? $availableUpgrade['updateurl'] : '', (string) \IPS\DateTime::ts( $availableUpgrade['released'] )->localeDate() )
			);
		}

		return NULL;
	}

	/**
	 * [Node] Does the currently logged in user have permission to add a child node?
	 *
	 * @return	bool
	 * @note	Modules are added via the developer center and should not be added by a regular admin via the standard node controller
	 */
	public function canAdd()
	{
		return false;
	}

	/**
	 * [Node] Does the currently logged in user have permission to add aa root node?
	 *
	 * @return	bool
	 * @note	If IN_DEV is on, the admin can create a new application
	 */
	public static function canAddRoot()
	{
		return ( \IPS\IN_DEV ) ? true : false;
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to edit permissions for this node?
	 *
	 * @return	bool
	 * @note	We don't allow permissions to be set for applications - they are handled by modules and by the enabled/disabled mode
	 */
	public function canManagePermissions()
	{
		return false;
	}
	
	/**
	 * Add or edit an application
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object we can add our fields to
	 * @return	void
	 */
	public function form( &$form )
	{
		if ( !$this->directory )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'app_title', NULL, FALSE, array( 'app' => 'core', 'key' => ( !$this->directory ) ? NULL : "__app_{$this->directory}" ) ) );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'app_directory', $this->directory, TRUE, array( 'disabled' => $this->id ? TRUE : FALSE, 'regex' => '/^[a-zA-Z][a-zA-Z0-9]+$/', 'maxLength' => 80 ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'app_author', $this->author ) );
		$form->add( new \IPS\Helpers\Form\Url( 'app_website', $this->website ) );
		$form->add( new \IPS\Helpers\Form\Url( 'app_update_check', $this->update_check ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'app_protected', $this->protected, FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'app_hide_tab', !$this->hide_tab, FALSE ) );
	}

	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		/* New application stuff */
		if ( !$this->id )
		{
			/* Check dir is writable */
			if( !is_writable( \IPS\ROOT_PATH . '/applications/' ) )
			{
				\IPS\Output::i()->error( 'app_dir_not_write', '4S134/2', 403, '' );
			}
			
			/* Check key isn't in use */
			$values['app_directory'] = mb_strtolower( $values['app_directory'] );
			try
			{
				$test = \IPS\Application::load( $values['app_directory'] );
				\IPS\Output::i()->error( 'app_error_key_used', '1S134/1', 403, '' );
			}
			catch ( \OutOfRangeException $e ) { }

			/* Attempt to create the basic directory structure for the developer */
			if( is_writable( \IPS\ROOT_PATH . '/applications/' ) )
			{
				/* If we can make the root dir, we can create the subfolders */
				if( @mkdir( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] ) )
				{
					@chmod( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'], \IPS\FOLDER_PERMISSION_NO_WRITE );

					/* Create directories */
					foreach ( array( 'data', 'dev', 'dev/css', 'dev/email', 'dev/html', 'dev/resources', 'dev/js', 'extensions', 'extensions/core', 'hooks', 'interface', 'modules', 'modules/admin', 'modules/front', 'setup', '/setup/upg_working', 'sources', 'tasks' ) as $f )
					{
						@mkdir( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/' . $f );
						@chmod( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/' . $f, \IPS\FOLDER_PERMISSION_NO_WRITE );
						\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/' . $f . '/index.html', '' );
					}

					/* Create files */
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/schema.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/settings.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/tasks.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/themesettings.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/acpmenu.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/modules.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/widgets.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/acpsearch.json', '{}' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/hooks.json', '[]' );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/versions.json', json_encode( array() ) );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/dev/lang.php', '<?' . "php\n\n\$lang = array(\n\t'__app_{$values['app_directory']}'\t=> \"{$values['app_title']}\"\n);\n" );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/dev/jslang.php', '<?' . "php\n\n\$lang = array(\n\n);\n" );
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/Application.php', str_replace(
						array(
							'{app}',
							'{website}',
							'{author}',
							'{year}',
							'{subpackage}',
							'{date}'
						),
						array(
							$values['app_directory'],
							$values['app_website'],
							$values['app_author'],
							date('Y'),
							$values['app_title'],
							date( 'd M Y' ),
						),
						file_get_contents( \IPS\ROOT_PATH . "/applications/core/data/defaults/Application.txt" )
					) );
	
					@\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $values['app_directory'] . '/data/application.json', json_encode( array(
						'application_title'	=> $values['app_title'],
						'app_author'		=> $values['app_author'],
						'app_directory'		=> $values['app_directory'],
						'app_protected'		=> $values['app_protected'],
						'app_website'		=> $values['app_website'],
						'app_update_check'	=> $values['app_update_check'],
						'app_hide_tab'		=> $values['app_hide_tab']
					) ) );
				}
			}
			
			/* Enable it */
			$values['enabled']		= TRUE;
			$values['app_added']	= time();
		}

		$values['app_hide_tab'] = !$values['app_hide_tab'];

		if( isset( $values['app_title'] ) )
		{
			unset( $values['app_title'] );
		}

		return $values;
	}

	/**
	 * [Node] Perform actions after saving the form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function postSaveForm( $values )
	{
		/* Clear out member's cached "Create Menu" contents */
		\IPS\Member::clearCreateMenu();
		unset( \IPS\Data\Store::i()->applications );
		\IPS\Settings::i()->clearCache();
	}

	/**
	 * Install database changes from the schema.json file
	 *
	 * @param	bool	$skipInserts	Skip inserts
	 * @throws \Exception
	 */
	public function installDatabaseSchema( $skipInserts=FALSE )
	{
		if( file_exists( $this->getApplicationPath() . "/data/schema.json" ) )
		{
			$schema	= json_decode( file_get_contents( $this->getApplicationPath() . "/data/schema.json" ), TRUE );

			foreach( $schema as $table => $definition )
			{
				/* Look for missing tables first */
				if( !\IPS\Db::i()->checkForTable( $table ) )
				{
					\IPS\Db::i()->createTable( $definition );
				}
				else
				{
					/* If the table exists, look for missing columns */
					if( \is_array( $definition['columns'] ) AND \count( $definition['columns'] ) )
					{
						/* Get the table definition first */
						$tableDefinition = \IPS\Db::i()->getTableDefinition( $table );

						foreach( $definition['columns'] as $column )
						{
							/* Column does not exist in the table definition?  Add it then. */
							if( empty($tableDefinition['columns'][ $column['name'] ]) )
							{
								\IPS\Db::i()->addColumn( $table, $column );
							}
						}
					}
				}

				if ( isset( $definition['inserts'] ) AND !$skipInserts )
				{
					foreach ( $definition['inserts'] as $insertData )
					{
						$adminName = \IPS\Member::loggedIn()->name;
						try
						{
							\IPS\Db::i()->insert( $definition['name'], array_map( function( $column ) use( $adminName ) {
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
						catch( \IPS\Db\Exception $e )
						{}
					}
				}
			}
		}
		
		if( file_exists( $this->getApplicationPath() . "/setup/install/queries.json" ) )
		{
			$schema	= json_decode( file_get_contents( $this->getApplicationPath() . "/setup/install/queries.json" ), TRUE );

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
				
				try
				{
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
				catch( \Exception $e )
				{
					if( $instruction['method'] == 'insert' )
					{
						return;
					}

					throw $e;
				}
			}
		}
	}

	/**
	 * Install database changes from an upgrade schema file
	 *
	 * @param	int		$version		Version to execute database updates from
	 * @param	int		$lastJsonIndex	JSON index to begin from
	 * @param	int		$limit			Limit updates
	 * @param	bool	$return			Check table size first and return queries for larger tables instead of running automatically
	 * @return	array					Returns an array: ( count: count of queries run, queriesToRun: array of queries to run)
	 * @note	We ignore some database errors that shouldn't prevent us from continuing.
	 * @li	1007: Can't create database because it already exists
	 * @li	1008: Can't drop database because it does not exist
	 * @li	1050: Can't rename a table as it already exists
	 * @li	1051: Can't drop a table because it doesn't exist
	 * @li	1060: Can't add a column as it already exists
	 * @li	1062: Can't add an index as index already exists
	 * @li	1062: Can't add a row as PKEY already exists
	 * @li	1091: Can't drop key or column because it does not exist
	 */
	public function installDatabaseUpdates( $version=0, $lastJsonIndex=0, $limit=50, $return=FALSE )
	{
		$toReturn    = array();
		$count  = 0;

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= null;

		if( $maxExecution = @ini_get( 'max_execution_time' ) )
		{
			/* If max_execution_time is set to "no limit" we should add a hard limit to prevent browser timeouts */
			if ( $maxExecution == -1 )
			{
				$maxExecution = 30;
			}
			$cutOff	= time() + ( $maxExecution * .5 );
		}

		if( file_exists( $this->getApplicationPath() . "/setup/upg_{$version}/queries.json" ) )
		{
			$schema	= json_decode( file_get_contents( $this->getApplicationPath() . "/setup/upg_{$version}/queries.json" ), TRUE );
			
			ksort($schema, SORT_NUMERIC);

			foreach( $schema as $jsonIndex => $instruction['params'] )
			{
				if ( $lastJsonIndex AND ( $jsonIndex <= $lastJsonIndex ) )
				{
					continue;
				}
				
				if ( $count >= $limit )
				{
					return array( 'count' => $count, 'queriesToRun' => $toReturn );
				}
				else if( $cutOff !== null AND time() >= $cutOff )
				{
					return array( 'count' => $count, 'queriesToRun' => $toReturn );
				}
				
				$_SESSION['lastJsonIndex'] = $jsonIndex;
				
				$count++;

				/* Get the table name, we need it */
				$_table	= $instruction['params']['params'][0];

				if ( !\is_string( $_table ) )
				{
					$_table	= $instruction['params']['params'][0]['name'];
				}

				/* Check table size first and store query if requested */
				if( $return === TRUE )
				{
					if( 
						/* Only run manually if we need to */
						\IPS\Db::i()->recommendManualQuery( $_table ) AND 
						/* And if it's not a drop table, insert or rename table query */
						!\in_array( $instruction['params']['method'], array( 'dropTable', 'insert', 'renameTable' ) ) AND
						/* ANNNNNDDD only if the method is not delete or there's a where clause, i.e. a truncate table statement does not run manually */
						( $instruction['params']['method'] != 'delete' OR isset( $instructions['params']['params'][1] ) )
						)
					{
						\IPS\Log::debug( "Big table " . $_table . ", storing query to run manually", 'upgrade' );

						\IPS\Db::i()->returnQuery = TRUE;

						$method = $instruction['params']['method'];
						$params = $instruction['params']['params'];
						$query = \IPS\Db::i()->$method( ...$params );

						if( $query )
						{
							$toReturn[] = $query;

							if ( $instruction['params']['method'] == 'renameTable' )
							{
								\IPS\Db::i()->cachedTableData[ $instruction['params']['params'][1] ] = \IPS\Db::i()->cachedTableData[ $_table ];

								foreach( $toReturn as $k => $v )
								{
									$toReturn[ $k ]	= preg_replace( "/\`" . \IPS\Db::i()->prefix . $_table . "\`/", "`" . \IPS\Db::i()->prefix . $instruction['params']['params'][1] . "`", $v );
								}
							}

							return array( 'count' => $count, 'queriesToRun' => $toReturn );
						}
					}
				}

				try
				{
					$method = $instruction['params']['method'];
					$params = $instruction['params']['params'];
					\IPS\Db::i()->$method( ...$params );
				}
				catch( \IPS\Db\Exception $e )
				{
					\IPS\Log::log( "Error (" . $e->getCode() . ") " . $e->getMessage() . ": " . $instruction['params']['method'] . ' ' . json_encode( $instruction['params']['params'] ), 'upgrade_error' );
					
					/* If the issue is with a create table other than exists, we should just throw it */
					if ( $instruction['params']['method'] == 'createTable' and ! \in_array( $e->getCode(), array( 1007, 1050 ) ) )
					{
						throw $e;
					}
					
					/* Can't change a column as it doesn't exist */
					if ( $e->getCode() == 1054 )
					{
						if ( $instruction['params']['method'] == 'changeColumn' )
						{
							if ( \IPS\Db::i()->checkForTable( $instruction['params']['params'][0] ) )
							{
								/* Does the column exist already? */
								if ( \IPS\Db::i()->checkForColumn( $instruction['params']['params'][0], $instruction['params']['params'][2]['name'] ) )
								{
									/* Just make sure it's up to date */
									\IPS\Db::i()->changeColumn( $instruction['params']['params'][0], $instruction['params']['params'][2]['name'], $instruction['params']['params'][2] );
									continue;
								}
								else
								{
									/* The table exists, so lets just add the column */
									\IPS\Db::i()->addColumn( $instruction['params']['params'][0], $instruction['params']['params'][2] );
								
									continue;
								}
							}
						}
						
						throw $e;
					}
					/* Can't rename a table as it doesn't exist */
					else if ( $e->getCode() == 1017 )
					{
						if ( $instruction['params']['method'] == 'renameTable' )
						{
							if ( \IPS\Db::i()->checkForTable( $instruction['params']['params'][1] ) )
							{
								/* The table we are renaming to *does* exist */
								continue;
							}
						}
						
						throw $e;
					}
					/* Possibly trying to change a column to not null that has NULL values */
					else if ( $e->getCode() == 1138 )
					{
						if ( $instruction['params']['method'] == 'changeColumn' and ! $instruction['params']['params'][2]['allow_null'] )
						{
							$currentDefintion = \IPS\Db::i()->getTableDefinition( $instruction['params']['params'][0] );
							$column = $instruction['params']['params'][2]['name'];
							
							if ( isset( $currentDefintion['columns'][ $column ] ) AND $currentDefintion['columns'][ $column ]['allow_null'] )
							{
								\IPS\Db::i()->update( $instruction['params']['params'][0], array( $column => '' ), array( $column . ' IS NULL' ) );
								
								/* Just make sure it's up to date */
								\IPS\Db::i()->changeColumn( $instruction['params']['params'][0], $instruction['params']['params'][1], $instruction['params']['params'][2] );
								
								continue;
							}
						}
						
						throw $e;
					}
					/* If the error isn't important we should ignore it */
					else if( !\in_array( $e->getCode(), array( 1007, 1008, 1050, 1060, 1061, 1062, 1091, 1051 ) ) )
					{
						throw $e;
					}
				}
			}
		}

		return array( 'count' => $count, 'queriesToRun' => $toReturn );
	}

	/**
	 * Rebuild common data during an install or upgrade. This is a shortcut method which
	 * * Installs module data from JSON file
	 * * Installs task data from JSON file
	 * * Installs setting data from JSON file
	 * * Installs ACP live search keywords from JSON file
	 * * Installs hooks from JSON file
	 * * Updates latest version in the database
	 *
	 * @param	bool	$skipMember		Skip clearing member cache clearing
	 * @return void
	 */
	public function installJsonData( $skipMember=FALSE )
	{
		/* Rebuild modules */
		$this->installModules();

		/* Rebuild tasks */
		$this->installTasks();

		/* Rebuild settings */
		$this->installSettings();
		
		/* Rebuild sidebar widgets */
		$this->installWidgets();

		/* Rebuild search keywords */
		$this->installSearchKeywords();
		
		/* Rebuild hooks */
		$this->installHooks();

		/* Update app version data */
		$versions		= $this->getAllVersions();
		$longVersions	= array_keys( $versions );
		$humanVersions	= array_values( $versions );

		if( \count($versions) )
		{
			$latestLVersion	= array_pop( $longVersions );
			$latestHVersion	= array_pop( $humanVersions );

			\IPS\Db::i()->update( 'core_applications', array( 'app_version' => $latestHVersion, 'app_long_version' => $latestLVersion ), array( 'app_directory=?', $this->directory ) );
		}

		unset( \IPS\Data\Store::i()->applications );

		if( !$skipMember )
		{
			\IPS\Member::clearCreateMenu();
		}
	}

	/**
	 * Install the application's modules
	 *
	 * @note	A module's "default" status will not be adjusted during upgrades - if there is already a module flagged as default, it will remain the default.
	 * @return	void
	 */
	public function installModules()
	{
		if( file_exists( $this->getApplicationPath() . "/data/modules.json" ) )
		{
			$currentModules	= array();
			$moduleStore	= array();
			$hasDefault		= FALSE;

			foreach ( \IPS\Db::i()->select( '*', 'core_modules', array( 'sys_module_application=?', $this->directory ) ) as $row )
			{
				if( $row['sys_module_default'] )
				{
					$hasDefault = TRUE;
				}

				$currentModules[ $row['sys_module_area'] ][ $row['sys_module_key'] ] = array(
					'default_controller'	=> $row['sys_module_default_controller'],
					'protected'				=> $row['sys_module_protected'],
					'default'				=> $row['sys_module_default']
				);
				$moduleStore[ $row['sys_module_area'] ][ $row['sys_module_key'] ] = $row;
			}
			
			$insert	= array();
			$update	= array();

			$position = 0;
			foreach( json_decode( file_get_contents( $this->getApplicationPath() . "/data/modules.json" ), TRUE ) as $area => $modules )
			{
				foreach ( $modules as $key => $data )
				{
					$position++;

					if ( !isset( $currentModules[ $area ][ $key ] ) )
					{
						$module = new \IPS\Application\Module;
					}
					elseif ( $currentModules[ $area ][ $key ] != $data )
					{
						$module = \IPS\Application\Module::constructFromData( $moduleStore[ $area ][ $key ] );
					}
					else
					{
						continue;
					}

					$module->application = $this->directory;
					$module->key = $key;
					$module->protected = \intval( $data['protected'] );
					$module->visible = TRUE;
					$module->position = $position;
					$module->area = $area;
					$module->default_controller = $data['default_controller'];

					/* We don't set/change default status if a module is already flagged as the default. An administrator may legitimately wish to change which module is the default, and we wouldn't want to reset that. */
					if( !$hasDefault )
					{
						$module->default = ( isset( $data['default'] ) and $data['default'] );
					}

					$module->save( TRUE );
				}
			}
		}
	}

	/**
	 * Install the application's tasks
	 *
	 * @return	void
	 */
	public function installTasks()
	{
		if( file_exists( $this->getApplicationPath() . "/data/tasks.json" ) )
		{
			foreach ( json_decode( file_get_contents( $this->getApplicationPath() . "/data/tasks.json" ), TRUE ) as $key => $frequency )
			{
				\IPS\Db::i()->replace( 'core_tasks', array(
					'app'		=> $this->directory,
					'key'		=> $key,
					'frequency'	=> $frequency,
					'next_run'	=> \IPS\DateTime::create()->add( new \DateInterval( $frequency ) )->getTimestamp()
				) );
			}
		}
	}
	
	/**
	 * Install the application's extension data where required
	 *
	 * @param	bool	$newInstall	TRUE if the community is being installed for the first time (opposed to an app being added)
	 * @return	void
	 */
	public function installExtensions( $newInstall=FALSE )
	{
		/* File storage */
		$settings = json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		
		try
		{
			/* Only check for Amazon when installing an app via the Admin CP on Community in the Cloud. The CiC Installer will handle brand new installs. */
			if ( \IPS\CIC AND !$newInstall )
			{
				$fileSystem = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'method=?', 'Amazon' ), 'id ASC' )->first();
			}
			else
			{
				$fileSystem = \IPS\Db::i()->select( '*', 'core_file_storage', array( 'method=?', 'FileSystem' ), 'id ASC' )->first();
			}
		}
		catch( \UnderflowException $ex )
		{
			$fileSystem = \IPS\Db::i()->select( '*', 'core_file_storage', NULL, 'id ASC' )->first();
		}
		
		foreach( $this->extensions( 'core', 'FileStorage' ) as $key => $path )
		{
			$settings[ 'filestorage__' . $this->directory . '_' . $key ] = $fileSystem['id'];
		}
		
		\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );
		
		$inserts = array();
		foreach( $this->extensions( 'core', 'Notifications' ) as $key => $class )
		{
			if ( method_exists( $class, 'getConfiguration' ) )
			{
				$defaults = $class->getConfiguration( NULL );
				
				foreach( $defaults AS $k => $config )
				{
					$inserts[] = array(
						'notification_key'	=> $k,
						'default'			=> implode( ',', $config['default'] ),
						'disabled'			=> implode( ',', $config['disabled'] ),
					);
				}
			}
		}
		
		if( \count( $inserts ) )
		{
			\IPS\Db::i()->insert( 'core_notification_defaults', $inserts );
		}
		
		/* Install Menu items */
		if ( !$newInstall )
		{
			$defaultNavigation = $this->defaultFrontNavigation();
			foreach ( $defaultNavigation as $type => $tabs )
			{
				foreach ( $tabs as $config )
				{
					$config['real_app'] = $this->directory;
					if ( !isset( $config['app'] ) )
					{
						$config['app'] = $this->directory;
					}
					
					\IPS\core\FrontNavigation::insertMenuItem( NULL, $config, \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );
				}
			}
			unset( \IPS\Data\Store::i()->frontNavigation );
		}
	}

	/**
	 * Install the application's settings
	 *
	 * @return	void
	 */
	public function installSettings()
	{
		if( file_exists( $this->getApplicationPath() . "/data/settings.json" ) )
		{
			$currentData = iterator_to_array( \IPS\Db::i()->select( array( 'conf_key', 'conf_default', 'conf_report' ), 'core_sys_conf_settings' )->setKeyField('conf_key') );

			$insert	= array();
			$update	= array();

			foreach ( json_decode( file_get_contents( $this->getApplicationPath() . "/data/settings.json" ), TRUE ) as $setting )
			{
				$report = ( isset( $setting['report'] ) and $setting['report'] != 'none' ) ? $setting['report'] : NULL;
				if ( ! array_key_exists( $setting['key'], $currentData ) )
				{
					$insert[]	= array( 'conf_key' => $setting['key'], 'conf_value' => $setting['default'], 'conf_default' => $setting['default'], 'conf_app' => $this->directory, 'conf_report' => $report );
				}
				elseif ( $currentData[ $setting['key'] ]['conf_default'] != $setting['default'] or $currentData[ $setting['key'] ]['conf_report'] != $report )
				{
					$update[]	= array( array( 'conf_default' => $setting['default'], 'conf_report' => $report ), array( 'conf_key=?', $setting['key'] ) );
				}
			}
			
			if ( !empty( $insert ) )
			{
				\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert, TRUE );
			}
			
			foreach ( $update as $data )
			{
				\IPS\Db::i()->update( 'core_sys_conf_settings', $data[0], $data[1] );
			}
			
			\IPS\Settings::i()->clearCache();
		}
	}

	/**
	 * Install the application's language strings
	 *
	 * @param	int|null		$offset Offset to begin import from
	 * @param	int|null		$limit	Number of rows to import
	 * @return	int				Rows inserted
	 */
	public function installLanguages( $offset=null, $limit=null )
	{
		$languages	= array_keys( \IPS\Lang::languages() );
		$inserted	= 0;
		
		$current = array();
		foreach( $languages as $languageId )
		{
			foreach( iterator_to_array( \IPS\Db::i()->select( 'word_key, word_default, word_js', 'core_sys_lang_words', array( 'word_app=? AND lang_id=?', $this->directory, $languageId ) ) ) as $word )
			{
				$current[ $languageId ][ $word['word_key'] . '-.-' . $word['word_js'] ] = $word['word_default'];
			}
		}

		if ( !$offset and file_exists( $this->getApplicationPath() . "/data/installLang.json" ) )
		{
			$inserts = array();
			foreach ( json_decode( file_get_contents( $this->getApplicationPath() . "/data/installLang.json" ), TRUE ) as $key => $default )
			{
				foreach( $languages as $languageId )
				{
					if ( !isset( $current[ $languageId ][ $key . '-.-0' ] ) )
					{
						$inserts[]	= array(
							'word_app'				=> $this->directory,
							'word_key'				=> $key,
							'lang_id'				=> $languageId,
							'word_default'			=> $default,
							'word_custom'			=> $default,
							'word_default_version'	=> $this->long_version,
							'word_custom_version'	=> $this->long_version,
							'word_js'				=> 0,
							'word_export'			=> 0,
						);
					}
				}
			}
			
			if ( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_sys_lang_words', $inserts, TRUE );
			}
		}
		
		if( file_exists( $this->getApplicationPath() . "/data/lang.xml" ) )
		{			
			/* Open XML file */
			$xml = \IPS\Xml\XMLReader::safeOpen( $this->getApplicationPath() . "/data/lang.xml" );
			$xml->read();

			/* Get the version */
			$xml->read();
			$xml->read();
			$version	= $xml->getAttribute('version');

			/* Get all installed languages */
			$inserts	 = array();
			$batchSize   = 25;
			$batchesDone = 0;
			$i           = 0;
			
			/* Try to prevent timeouts to the extent possible */
			$cutOff			= null;

			if( $maxExecution = @ini_get( 'max_execution_time' ) )
			{
				/* If max_execution_time is set to "no limit" we should add a hard limit to prevent browser timeouts */
				if ( $maxExecution == -1 )
				{
					$maxExecution = 30;
				}

				$cutOff	= time() + ( $maxExecution * .5 );
			}

			/* Start looping through each word */
			while ( $xml->read() )
			{
				if( $xml->name != 'word' OR $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				if( $cutOff !== null AND time() >= $cutOff )
				{
					return $inserted;
				}
				
				$i++;
				
				if ( $offset !== null )
				{
					if ( $i - 1 < $offset )
					{
						$xml->next();
						continue;
					}
				}

				$inserted++;
				
				$key = $xml->getAttribute('key');
				$value = $xml->readString();
				foreach( $languages as $languageId )
				{
					if ( !isset( $current[ $languageId ][ $key . '-.-' . (int) $xml->getAttribute('js') ] ) or $current[ $languageId ][ $key . '-.-' . (int) $xml->getAttribute('js') ] != $value )
					{
						$inserts[]	= array(
							'word_app'				=> $this->directory,
							'word_key'				=> $key,
							'lang_id'				=> $languageId,
							'word_default'			=> $value,
							'word_default_version'	=> $version,
							'word_js'				=> (int) $xml->getAttribute('js'),
							'word_export'			=> 1,
						);
					}
				}
				
				$done = ( $limit !== null AND $i === ( $limit + $offset ) );
				
				if ( $done OR $i % $batchSize === 0 )
				{
					if ( \count( $inserts ) )
					{
						\IPS\Db::i()->insert( 'core_sys_lang_words', $inserts, TRUE );
						$inserts = array();
					}
					$batchesDone++;
				}
				
				if ( $done )
				{
					break;
				}
				
				$xml->next();
			}
			
			if ( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_sys_lang_words', $inserts, TRUE );
			}
		}

		return $inserted;
	}

	/**
	 * Install the application's email templates
	 *
	 * @return	void
	 */
	public function installEmailTemplates()
	{
		if( file_exists( $this->getApplicationPath() . "/data/emails.xml" ) )
		{
			/* First, delete any existing non-customized email templates for this app */
			\IPS\Db::i()->delete( 'core_email_templates', array( 'template_app=? AND template_parent=0', $this->directory ) );

			/* Open XML file */
			$xml = \IPS\Xml\XMLReader::safeOpen( $this->getApplicationPath() . "/data/emails.xml" );
			$xml->read();

			/* Start looping through each word */
			while ( $xml->read() and $xml->name == 'template' )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				$insert	= array(
					'template_parent'	=> 0,
					'template_app'		=> $this->directory,
					'template_edited'	=> 0,
					'template_pinned'	=> 0
				);

				while ( $xml->read() and $xml->name != 'template' )
				{
					if( $xml->nodeType != \XMLReader::ELEMENT )
					{
						continue;
					}

					switch( $xml->name )
					{
						case 'template_name':
							$insert['template_name']				= $xml->readString();
							$insert['template_key']					= md5( $this->directory . ';' . $insert['template_name'] );
						break;

						case 'template_data':
							$insert['template_data']				= $xml->readString();
						break;

						case 'template_content_html':
							$insert['template_content_html']		= $xml->readString();
						break;

						case 'template_content_plaintext':
							$insert['template_content_plaintext']	= $xml->readString();
						break;

						case 'template_pinned':
							$insert['template_pinned']				= $xml->readString();
						break;
					}
				}

				\IPS\Db::i()->replace( 'core_email_templates', $insert );
			}

			/* Now re-associate customized email templates */
			foreach( \IPS\Db::i()->select( '*', 'core_email_templates', array( 'template_app=? AND template_parent>0', $this->directory ) ) as $template )
			{
				/* Find the real parent now */
				try
				{
					$parent = \IPS\Db::i()->select( '*', 'core_email_templates', array( 'template_app=? and template_name=? and template_parent=0', $template['template_app'], $template['template_name'] ) )->first();

					/* And now update this template */
					\IPS\Db::i()->update( 'core_email_templates', array( 'template_parent' => $parent['template_id'], 'template_data' => $parent['template_data'] ), array( 'template_id=?', $template['template_id'] ) );
					\IPS\Db::i()->update( 'core_email_templates', array( 'template_edited' => 1 ), array( 'template_id=?', $parent['template_id'] ) );
				}
				catch( \UnderflowException $ex ) { }
			}

			\IPS\Data\Cache::i()->clearAll();
			\IPS\Data\Store::i()->clearAll();
		}
	}

	/**
	 * Install the application's skin templates, CSS files and resources
	 *
	 * @param	bool	$update		If set to true, do not overwrite current theme setting values
	 * @return	void
	 */
	public function installSkins( $update=FALSE )
	{
		/* Clear old caches */
		\IPS\Data\Cache::i()->clearAll();
		\IPS\Data\Store::i()->clearAll();

		/* Install the stuff */
		$this->installThemeSettings( $update );
		$this->clearTemplates();
		$this->installTemplates( $update );
	}

	/**
	 * Install the application's theme settings
	 *
	 * @param	bool	$update		If set to true, do not overwrite current theme setting values
	 * @return	void
	 */
	public function installThemeSettings( $update=FALSE )
	{
		if ( file_exists( $this->getApplicationPath() . "/data/themesettings.json" ) )
		{
			unset( \IPS\Data\Store::i()->themes );
			try
			{
				$defaultThemeId = \IPS\Theme::load('default', 'set_key')->id;
			}
			catch( \Exception $ex )
			{
				$defaultThemeId = \IPS\Theme::defaultTheme();
			}
			
			$currentSettings	= iterator_to_array( \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_app=?', $defaultThemeId, $this->directory ) )->setKeyField('sc_key') );
			$json				= json_decode( file_get_contents( $this->getApplicationPath() . "/data/themesettings.json" ), TRUE );
			
			/* Add */
			foreach( $json as $key => $data)
			{
				$insertedSetting = FALSE;
				
				if ( ! isset( $currentSettings[ $data['sc_key'] ] ) )
				{
					$insertedSetting = TRUE;
					
					$currentId = \IPS\Db::i()->insert( 'core_theme_settings_fields', array(
						'sc_set_id'		 => $defaultThemeId,
						'sc_key'		 => $data['sc_key'],
						'sc_tab_key'	 => $data['sc_tab_key'],
						'sc_type'		 => $data['sc_type'],
						'sc_multiple'	 => $data['sc_multiple'],
						'sc_default'	 => $data['sc_default'],
						'sc_content'	 => $data['sc_content'],
						'sc_show_in_vse' => ( isset( $data['sc_show_in_vse'] ) ) ? $data['sc_show_in_vse'] : 0,
						'sc_updated'	 => time(),
						'sc_app'		 => $this->directory,
						'sc_title'		 => $data['sc_title'],
						'sc_order'		 => $data['sc_order'],
						'sc_condition'	 => $data['sc_condition'],
					) );
					
					$currentSettings[ $data['sc_key'] ] = $data;
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
					), array( 'sc_set_id=? AND sc_key=? AND sc_app=?', $defaultThemeId, $data['sc_key'], $this->directory ) );
			
					$currentId = $currentSettings[ $data['sc_key'] ]['sc_id'];
				}

				/* Are we updating the value? */
				if( $update === FALSE OR $insertedSetting === TRUE )
				{
					\IPS\Db::i()->delete('core_theme_settings_values', array('sv_id=?', $currentId ) );
					\IPS\Db::i()->insert('core_theme_settings_values', array( 'sv_id' => $currentId, 'sv_value' => (string)$data['sc_default'] ) );
				}
			}

			if ( $update )
			{
				$defaultCurrentSettings = $currentSettings;
				foreach( \IPS\Theme::themes() as $theme )
				{
					/* If we are using the stock default theme, then use the setting values from the JSON as the base */
					if ( $theme->id == $defaultThemeId )
					{
						$currentSettings = $defaultCurrentSettings;
					}
					else
					{
						$currentSettings = iterator_to_array( \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_set_id=?', $theme->id ) )->setKeyField('sc_key') );
					}
					
					$added           = FALSE;
					$save            = json_decode( $theme->template_settings, TRUE );

					/* Add */
					foreach( $json as $key => $data )
					{
						if ( ! isset( $currentSettings[ $data['sc_key'] ] ) )
						{
							$added = TRUE;
							$save[ $data['sc_key'] ] = $data['sc_default'];

							\IPS\Db::i()->insert( 'core_theme_settings_fields', array(
								'sc_set_id'		 => $theme->id,
								'sc_key'		 => $data['sc_key'],
								'sc_tab_key'	 => $data['sc_tab_key'],
								'sc_type'		 => $data['sc_type'],
								'sc_multiple'	 => $data['sc_multiple'],
								'sc_default'	 => $data['sc_default'],
								'sc_content'	 => $data['sc_content'],
								'sc_show_in_vse' => ( isset( $data['sc_show_in_vse'] ) ) ? $data['sc_show_in_vse'] : 0,
								'sc_updated'	 => time(),
								'sc_app'		 => $this->directory,
								'sc_title'		 => $data['sc_title'],
								'sc_order'		 => $data['sc_order'],
								'sc_condition'	 => $data['sc_condition'],
							) );
						}
						else
						{
							/* Update */
							\IPS\Db::i()->update( 'core_theme_settings_fields', array(
								'sc_type'		 => $data['sc_type'],
								'sc_multiple'	 => $data['sc_multiple'],
								'sc_default'	 => $data['sc_default'],
								'sc_show_in_vse' => ( isset( $data['sc_show_in_vse'] ) ) ? $data['sc_show_in_vse'] : 0,
								'sc_content'	 => $data['sc_content'],
								'sc_title'		 => $data['sc_title'],
								'sc_condition'	 => $data['sc_condition'],
							), array( 'sc_set_id=? AND sc_key=?', $theme->id, $data['sc_key'] ) );
							
							$currentId = $currentSettings[ $data['sc_key'] ]['sc_id'];
							
							try
							{
								$currentValue = \IPS\Db::i()->select( 'sv_value', 'core_theme_settings_values', array( array( 'sv_id=?', $currentId ) ) )->first();
							}
							catch( \UnderFlowException $ex )
							{
								$currentValue = $currentSettings[ $data['sc_key'] ]['sc_default'];
							}
							
							/* Are we using the existing default? If so, update it */
							if ( ( $data['sc_default'] != $currentSettings[ $data['sc_key'] ]['sc_default'] ) and ( $currentValue == $defaultCurrentSettings[ $data['sc_key'] ]['sc_default'] ) )
							{
								$added = TRUE;
								$save[ $data['sc_key'] ] = $data['sc_default'];
								
								\IPS\Db::i()->delete('core_theme_settings_values', array('sv_id=?', $currentId ) );
								\IPS\Db::i()->insert('core_theme_settings_values', array( 'sv_id' => $currentId, 'sv_value' => (string)$data['sc_default'] ) );
							}
						}
					}
					
					if ( $added )
					{
						$theme->template_settings = json_encode( $save );
						$theme->save();
					}
				}
			}
		}
	}

	/**
	 * Clear out existing templates before installing new ones
	 *	
	 * @return	void
	 */
	public function clearTemplates()
	{
		if( file_exists( $this->getApplicationPath() . "/data/theme.xml" ) )
		{
			unset( \IPS\Data\Store::i()->themes );
			\IPS\Theme::removeTemplates( $this->directory );
			\IPS\Theme::removeCss( $this->directory );
			\IPS\Theme::clearFiles( \IPS\Theme::CSS );
			\IPS\Theme::removeResources( $this->directory );
			\IPS\Theme::resetAllCacheKeys();
		}
	}

	/**
	 * Install the application's templates
	 * Theme resources should be raw binary data everywhere (filesystem and DB) except in the theme XML download where they are base64 encoded.
	 *
	 * @param	bool		$update	If set to true, do not overwrite current theme setting values
	 * @param	int|null	$offset Offset to begin import from
	 * @param	int|null	$limit	Number of rows to import	
	 * @return	int			Rows inserted
	 */
	public function installTemplates( $update=FALSE, $offset=null, $limit=null )
	{
		$i			= 0;
		$inserted	= 0;
		
		if ( \IPS\Dispatcher::hasInstance() AND class_exists( '\IPS\Dispatcher', FALSE ) and \IPS\Dispatcher::i()->controllerLocation === 'setup' )
		{
			$class = '\IPS\Theme';
		}
		else
		{
			$class = ( \IPS\Theme::designersModeEnabled() ) ? '\IPS\Theme\Advanced\Theme'  : '\IPS\Theme';
		}
		
		if( file_exists( $this->getApplicationPath() . "/data/theme.xml" ) )
		{
			unset( \IPS\Data\Store::i()->themes );
			
			/* Try to prevent timeouts to the extent possible */
			$cutOff			= null;

			if( $maxExecution = @ini_get( 'max_execution_time' ) )
			{
				/* If max_execution_time is set to "no limit" we should add a hard limit to prevent browser timeouts */
				if ( $maxExecution == -1 )
				{
					$maxExecution = 30;
				}
				
				$cutOff	= time() + ( $maxExecution * .5 );
			}

			/* Open XML file */
			$xml = \IPS\Xml\XMLReader::safeOpen( $this->getApplicationPath() . "/data/theme.xml" );
			$xml->read();

			while( $xml->read() )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}

				if( $cutOff !== null AND time() >= $cutOff )
				{
					break;
				}

				$i++;

				if ( $offset !== null )
				{
					if ( $i - 1 < $offset )
					{
						$xml->next();
						continue;
					}
				}

				$inserted++;

				if( $xml->name == 'template' )
				{
					$template	= array(
						'app'		=> $this->directory,
						'group'		=> $xml->getAttribute('template_group'),
						'name'		=> $xml->getAttribute('template_name'),
						'variables'	=> $xml->getAttribute('template_data'),
						'content'	=> $xml->readString(),
						'location'	=> $xml->getAttribute('template_location'),
						'_default_template' => true
					);

					try
					{
						$class::addTemplate( $template );
					}
					catch( \OverflowException $e )
					{
						if ( ! $update )
						{
							throw $e;
						}
					}
				}
				else if( $xml->name == 'css' )
				{
					$css	= array(
						'app'		=> $this->directory,
						'location'	=> $xml->getAttribute('css_location'),
						'path'		=> $xml->getAttribute('css_path'),
						'name'		=> $xml->getAttribute('css_name'),
						'content'	=> $xml->readString(),
						'_default_template' => true
					);

					try
					{
						$class::addCss( $css );
					}
					catch( \OverflowException $e )
					{
						if( ! $update )
						{
							throw $e;
						}
					}
				}
				else if( $xml->name == 'resource' )
				{
					$resource	= array(
						'app'		=> $this->directory,
						'location'	=> $xml->getAttribute('location'),
						'path'		=> $xml->getAttribute('path'),
						'name'		=> $xml->getAttribute('name'),
						'content'	=> base64_decode( $xml->readString() ),
					);

					$class::addResource( $resource, TRUE );
				}

				if( $limit !== null AND $i === ( $limit + $offset ) )
				{
					break;
				}
			}
		}

		return $inserted;
	}
	
	/**
	 * Install the application's javascript
	 *
	 * @param	int|null	$offset Offset to begin import from
	 * @param	int|null	$limit	Number of rows to import	
	 * @return	int			Rows inserted
	 */
	public function installJavascript( $offset=null, $limit=null )
	{
		if( file_exists( $this->getApplicationPath() . "/data/javascript.xml" ) )
		{
			return \IPS\Output\Javascript::importXml( $this->getApplicationPath() . "/data/javascript.xml", $offset, $limit );
		}
	}
	
	/**
	 * Install the application's ACP search keywords
	 *
	 * @return	void
	 */
	public function installSearchKeywords()
	{
		if( file_exists( $this->getApplicationPath() . "/data/acpsearch.json" ) )
		{
			\IPS\Db::i()->delete( 'core_acp_search_index', array( 'app=?', $this->directory ) );
			
			$inserts	= array();
			$maxInserts	= 50;

			foreach( json_decode( file_get_contents( $this->getApplicationPath() . "/data/acpsearch.json" ), TRUE ) as $url => $data )
			{
				foreach ( $data['keywords'] as $word )
				{
					$inserts[] = array(
						'url'			=> $url,
						'keyword'		=> $word,
						'app'			=> $this->directory,
						'lang_key'		=> $data['lang_key'],
						'restriction'	=> $data['restriction'] ?: NULL
					);

					if( \count( $inserts ) >= $maxInserts )
					{
						\IPS\Db::i()->insert( 'core_acp_search_index', $inserts );
						$inserts = array();
					}
				}
			}
			
			if( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_acp_search_index', $inserts );
			}
		}
	}
	
	/**
	 * Install hooks
	 *
	 * @return	void
	 */
	public function installHooks()
	{
		if( file_exists( $this->getApplicationPath() . "/data/hooks.json" ) )
		{
			\IPS\Db::i()->delete( 'core_hooks', array( 'app=?', $this->directory ) );
			
			$inserts = array();
			$templatesToRecompile = array();
			foreach( json_decode( file_get_contents( $this->getApplicationPath() . "/data/hooks.json" ), TRUE ) as $filename => $data )
			{
				\IPS\Db::i()->insert( 'core_hooks', array(
					'app'			=> $this->directory,
					'type'			=> $data['type'],
					'class'			=> $data['class'],
					'filename'		=> $filename
				) );

				if ( $data['type'] === 'S' )
				{
					$templatesToRecompile[ $data['class'] ] = $data['class'];
				}
			}
			
			\IPS\Plugin\Hook::writeDataFile();
			
			foreach ( $templatesToRecompile as $k )
			{
				$exploded = explode( '_', $k );
				\IPS\Theme::deleteCompiledTemplate( $exploded[1], $exploded[2], $exploded[3] );
			}
		}
	}
	
	/**
	 * Install the application's widgets
	 *
	 * @return	void
	 */
	public function installWidgets()
	{
		if( file_exists( $this->getApplicationPath() . "/data/widgets.json" ) )
		{
			\IPS\Db::i()->delete( 'core_widgets', array( 'app=?', $this->directory ) );
	
			$inserts = array();
			foreach ( json_decode( file_get_contents( $this->getApplicationPath() . "/data/widgets.json" ), TRUE ) as $key => $json )
			{
					$inserts[] = array(
							'app'		   => $this->directory,
							'key'		   => $key,
							'class'		   => $json['class'],
							'restrict'     => json_encode( $json['restrict'] ),
							'default_area' => ( isset( $json['default_area'] ) ? $json['default_area'] : NULL ),
							'allow_reuse'  => ( isset( $json['allow_reuse'] ) ? $json['allow_reuse'] : 0 ),
							'menu_style'   => ( isset( $json['menu_style'] ) ? $json['menu_style'] : 'menu' ),
							'embeddable'   => ( isset( $json['embeddable'] ) ? $json['embeddable'] : 0 ),
						);
			}
			
			if( \count( $inserts ) )
			{
				\IPS\Db::i()->insert( 'core_widgets', $inserts, TRUE );
				unset( \IPS\Data\Store::i()->widgets );
			}
		}
	}

	/**
	 * Install 'other' items. Left blank here so that application classes can override for app
	 *  specific installation needs. Always run as the last step.
	 *
	 * @return void
	 */
	public function installOther()
	{

	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array(),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}
	
	/**
	 * Database check
	 *
	 * @return	array	Queries needed to correct database in the following format ( table => x, query = x );
	 */
	public function databaseCheck()
	{
		$db = \IPS\Db::i();
		$changesToMake = array();

		/* If member IDs are getting near the legacy mediumint limit, we need to increase it. */
		$maxMemberId = \IPS\Db::i()->select( 'max(member_id)', 'core_members' )->first();
		$enableBigInt = ( $maxMemberId > 8288607 );

		/* Loop the tables in the schema */
		foreach( json_decode( file_get_contents( $this->getApplicationPath() . "/data/schema.json" ), TRUE ) as $tableName => $tableDefinition )
		{
			$tableChanges	= array();
			$needIgnore		= false;
			$innoDbFullTextIndexes = array();
			
			/* Get our local definition of this table */
			try
			{
				$localDefinition	= \IPS\Db::i()->getTableDefinition( $tableName, FALSE, TRUE );
				$originalDefinition = $localDefinition; #Store this before it is normalised and engine stripped
				$localDefinition	= \IPS\Db::i()->normalizeDefinition( $localDefinition );

				if( isset( $tableDefinition['reporting'] ) )
				{
					unset( $tableDefinition['reporting'] );
				}

				if( isset( $tableDefinition['inserts'] ) )
				{
					unset( $tableDefinition['inserts'] );
				}

				/* Now we have to add the correct colation for text columns to our compare definition to flag any columns that don't have the correct charset/collation */
				$tableDefinition['columns'] = array_map( function( $column ){
					if( \in_array( mb_strtoupper( $column['type'] ), array( 'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET' ) ) )
					{
						$column['collation'] = \IPS\Db::i()->collation; 
					}

					return $column;
				}, $tableDefinition['columns'] );

				/* And store our definition */
				$compareDefinition	= \IPS\Db::i()->normalizeDefinition( $tableDefinition );
				$tableDefinition	= \IPS\Db::i()->updateDefinitionIndexLengths( $tableDefinition );

				if( isset( $compareDefinition['comment'] ) AND !$compareDefinition['comment'] )
				{
					unset( $compareDefinition['comment'] );
				}

				/* Ensure that we use the proper engine, not whatever is in the schema.json as this will confuse index sub_part lengths */
				if ( isset( $originalDefinition['engine'] ) )
				{
					$tableDefinition['engine'] = $originalDefinition['engine'];
				}

				if ( $compareDefinition != $localDefinition )
				{
					$dropped = array();

					/* Loop the columns */
					foreach ( $tableDefinition['columns'] as $columnName => $columnData )
					{
						/* If it doesn't exist in the local database, create it */
						if ( !isset( $localDefinition['columns'][ $columnName ] ) )
						{
							$tableChanges[] = "ADD COLUMN {$db->compileColumnDefinition( $columnData )}";
						}
						/* Or if it's wrong, change it */
						elseif ( $compareDefinition['columns'][ $columnName ] != $localDefinition['columns'][ $columnName ] )
						{
							/*  If the only difference is MEDIUMIT or INT should be BIGINT UNSIGNED - that's where we changed the member ID column. We don't need to flag it */
							$differences = array();
							foreach ( $columnData as $k => $v )
							{
								if ( isset( $localDefinition['columns'][ $columnName ][ $k ] ) AND $v != $localDefinition['columns'][ $columnName ][ $k ] )
								{
									$differences[ $k ] = array( 'is' => $localDefinition['columns'][ $columnName ][ $k ], 'shouldBe' => $v );
								}
							}
							if ( isset( $differences['type'] ) and ( $differences['type']['is'] == 'MEDIUMINT' or $differences['type']['is'] == 'INT' ) and $differences['type']['shouldBe'] == 'BIGINT' AND !$enableBigInt )
							{
								unset( $differences['type'] );
								if ( isset( $differences['length'] ) )
								{
									unset( $differences['length'] );
								}
								if ( isset( $differences['unsigned'] ) and !$differences['unsigned']['is'] and $differences['unsigned']['shouldBe'] )
								{
									unset( $differences['unsigned'] );
								}
							}

							/* Remove attempted changes back to empty string when INT */
							if( !empty( $differences['default'] ) AND $differences['default']['is'] == 0 AND $differences['default']['shouldBe'] == '' )
							{
								unset( $differences['default'] );
							}

							/* If this is a decimal column, ignore unsigned attribute */
							if( $compareDefinition['columns'][ $columnName ]['type'] == 'DECIMAL' AND isset( $differences['unsigned'] ) )
							{
								unset( $differences['unsigned'] );
							}
							
							/* If there were other differences, carry on... */
							if ( $differences )
							{
								/* We re-add indexes after changing columns */
								$indexesToAdd = array();

								/* First check indexes to see if any need to be adjusted */
								foreach( $localDefinition['indexes'] as $indexName => $indexData )
								{
									/* We skip the primary key as it can cause errors related to auto-increment */
									if( $indexName == 'PRIMARY' )
									{
										if ( isset( $tableDefinition['columns'][ $indexData['columns'][0] ] ) and isset( $tableDefinition['columns'][ $indexData['columns'][0] ]['auto_increment'] ) and $tableDefinition['columns'][ $indexData['columns'][0] ]['auto_increment'] === TRUE )
										{
											continue;
										}
									}
	
									foreach( $indexData['columns'] as $indexColumn )
									{
										/* If the column we are about to adjust is included in this index, see if it needs adjusting */
										if( $indexColumn == $columnName AND !\in_array( $indexName, $dropped ) )
										{
											$thisIndex = $db->updateDefinitionIndexLengths( $tableDefinition );
	
											if( !isset( $thisIndex['indexes'][ $indexName ] ) )
											{
												$tableChanges[] = "DROP INDEX `{$db->escape_string( $indexName )}`";
												$dropped[]		= $indexName;
											}
											elseif( $thisIndex['indexes'][ $indexName ] !== $localDefinition['indexes'][ $indexName ] )
											{
												$tableChanges[] = "DROP INDEX `{$db->escape_string( $indexName )}`";
												$indexesToAdd[] = $db->buildIndex( $tableName, $thisIndex['indexes'][ $indexName ], $tableDefinition );
												$dropped[]		= $indexName;
	
												if( $tableDefinition['indexes'][ $indexName ]['type'] == 'unique' OR $tableDefinition['indexes'][ $indexName ]['type'] == 'primary' )
												{
													$needIgnore = TRUE;
												}
											}
										}
									}
								}
	
								/* If we are about to adjust the column to not allow NULL values then adjust those values first... */
								if( isset( $columnData['allow_null'] ) and $columnData['allow_null'] === FALSE )
								{
									$defaultValue = "''";
									
									/* Default value */
									if( isset( $columnData['default'] ) and !\in_array( \strtoupper( $columnData['type'] ), array( 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'MEDIUMBLOB', 'BIGBLOB', 'LONGBLOB' ) ) )
									{
										if( $columnData['type'] == 'BIT' )
										{
											$defaultValue = "{$columnData['default']}";
										}
										else
										{
											$defaultValue = \in_array( $columnData['type'], array( 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC' ) ) ? \floatval( $columnData['default'] ) : ( ! \in_array( $columnData['default'], array( 'CURRENT_TIMESTAMP', 'BIT' ) ) ? '\'' . $db->escape_string( $columnData['default'] ) . '\'' : $columnData['default'] );
										}
									}
	
									$changesToMake[] = array( 'table' => $tableName, 'query' => "UPDATE `{$db->prefix}{$db->escape_string( $tableName )}` SET `{$db->escape_string( $columnName )}`={$defaultValue} WHERE `{$db->escape_string( $columnName )}` IS NULL;" );
								}
									
								$tableChanges[] = "CHANGE COLUMN `{$db->escape_string( $columnName )}` {$db->compileColumnDefinition( $columnData )}";

								if( \count( $indexesToAdd ) )
								{
									$tableChanges = array_merge( $tableChanges, $indexesToAdd );
								}
							}
						}
					}
					
					/* Loop the index */
					foreach ( $compareDefinition['indexes'] as $indexName => $indexData )
					{
						if( \in_array( $indexName, $dropped ) )
						{
							continue;
						}

						if ( !isset( $localDefinition['indexes'][ $indexName ] ) )
						{
							/* InnoDB FullText indexes must be added one at a time */
							if( $tableDefinition['engine'] === 'InnoDB' AND $tableDefinition['indexes'][ $indexName ]['type'] === 'fulltext' )
							{
								$innoDbFullTextIndexes[] = $db->buildIndex( $tableName, $tableDefinition['indexes'][ $indexName ], $tableDefinition );
							}
							else
							{
								$tableChanges[] = $db->buildIndex( $tableName, $tableDefinition['indexes'][ $indexName ], $tableDefinition );
							}

							if( $tableDefinition['indexes'][ $indexName ]['type'] == 'unique' OR $tableDefinition['indexes'][ $indexName ]['type'] == 'primary' )
							{
								$needIgnore = TRUE;
							}
						}
						elseif ( $indexData != $localDefinition['indexes'][ $indexName ] )
						{
							$tableChanges[] = ( ( $indexName == 'PRIMARY KEY' ) ? "DROP " . $indexName . ", " : "DROP INDEX `" . $db->escape_string( $indexName ) . "`, " ) . $db->buildIndex( $tableName, $tableDefinition['indexes'][ $indexName ], $tableDefinition );

							if( $tableDefinition['indexes'][ $indexName ]['type'] == 'unique' OR $tableDefinition['indexes'][ $indexName ]['type'] == 'primary' )
							{
								$needIgnore = TRUE;
							}
						}
					}
					
					/* Remove unnecessary indexes, which can be an issue if, for example, there is a UNIQUE index that the schema doesn't think should be there */
					foreach ( $localDefinition['indexes'] as $indexName => $indexData )
					{
						if ( $indexName != 'PRIMARY' and !isset( $compareDefinition['indexes'][ $indexName ] ) )
						{
							/* If the index is on a column which we don't recognise (which may happen on tables which we add columns to like the ones that
								store custom fields, or very naughty third parties adding columns on tables they don't own), don't drop it */
							foreach ( $indexData['columns'] as $indexedColumn )
							{
								if ( !isset( $compareDefinition['columns'][ $indexedColumn ] ) )
								{
									continue 2;
								}
							}
							
							/* Still here? Go ahead */
							$dropIndexQuery = "DROP INDEX `{$db->escape_string( $indexName )}`";
							if ( !\in_array( $dropIndexQuery, $tableChanges ) ) // We skip the primary key as it can cause errors related to auto-increment
							{
								$tableChanges[] = $dropIndexQuery;
							}
						}
					}
				}

				if( \count( $tableChanges ) )
				{
					if( $needIgnore )
					{
						$changesToMake[] = array( 'table' => $tableName, 'query' => "CREATE TABLE `{$db->prefix}{$db->escape_string( $tableName )}_new` LIKE `{$db->prefix}{$db->escape_string( $tableName )}`;" );
						$changesToMake[] = array( 'table' => $tableName, 'query' => "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}_new` " . implode( ", ", $tableChanges ) . ";" );
						$changesToMake[] = array( 'table' => $tableName, 'query' => "INSERT IGNORE INTO `{$db->prefix}{$db->escape_string( $tableName )}_new` SELECT * FROM `{$db->prefix}{$db->escape_string( $tableName )}`;" );
						$changesToMake[] = array( 'table' => $tableName, 'query' => "DROP TABLE `{$db->prefix}{$db->escape_string( $tableName )}`;" );
						$changesToMake[] = array( 'table' => $tableName, 'query' => "RENAME TABLE `{$db->prefix}{$db->escape_string( $tableName )}_new` TO `{$db->prefix}{$db->escape_string( $tableName )}`;" );
					}
					else
					{
						$changesToMake[] = array( 'table' => $tableName, 'query' => "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}` " . implode( ", ", $tableChanges ) . ";" );
					}
				}

				/* InnoDB FullText indexes must be added one at a time */
				if( \count( $innoDbFullTextIndexes ) )
				{
					foreach( $innoDbFullTextIndexes as $newIndex )
					{
						$changesToMake[] = array( 'table' => $tableName, 'query' => "ALTER TABLE `{$db->prefix}{$db->escape_string( $tableName )}` " . $newIndex . ";" );
					}
				}
			}
			/* If the table doesn't exist, create it */
			catch ( \OutOfRangeException $e )
			{
				$changesToMake[] = array( 'table' => $tableName, 'query' => $db->_createTableQuery( $tableDefinition ) );
			}
		}
		
		/* And loop any install routine for columns added to other tables */
		if ( file_exists( $this->getApplicationPath() . "/setup/install/queries.json" ) )
		{
			foreach( json_decode( file_get_contents( $this->getApplicationPath() . "/setup/install/queries.json" ), TRUE ) as $query )
			{
				switch ( $query['method'] )
				{
					/* Add column */
					case 'addColumn':
						$localDefinition = \IPS\Db::i()->getTableDefinition( $query['params'][0] );
						if ( !isset( $localDefinition['columns'][ $query['params'][1]['name'] ] ) )
						{
							$changesToMake[] = array( 'table' => $query['params'][0], 'query' => "ALTER TABLE `{$db->prefix}{$query['params'][0]}` ADD COLUMN {$db->compileColumnDefinition( $query['params'][1] )}" );
						}
						else
						{
							$correctDefinition = $db->compileColumnDefinition( $query['params'][1] );
							if ( $correctDefinition != $db->compileColumnDefinition( $localDefinition['columns'][ $query['params'][1]['name'] ] ) )
							{
								$changesToMake[] = array( 'table' => $query['params'][0], 'query' => "ALTER TABLE `{$db->prefix}{$query['params'][0]}` CHANGE COLUMN `{$query['params'][1]['name']}` {$correctDefinition}" );
							}
						}
						break;
				}
			}
		}
		
		/* Return */
		return $changesToMake;
	}
	
	/**
	 * Create a new version number and move current working version
	 * code into it
	 *
	 * @param	int		$long	The "long" version number (e.g. 100000)
	 * @param	string	$human	The "human" version number (e.g. "1.0.0")
	 * @return	void
	 */
	public function assignNewVersion( $long, $human )
	{
		/* Add to versions.json */
		$json = json_decode( \file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/versions.json" ), TRUE );
		$json[ $long ] = $human;
		static::writeJson( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/versions.json", $json );
		
		/* Do stuff */
		$setupDir = \IPS\ROOT_PATH . "/applications/{$this->directory}/setup";
		$workingDir = $setupDir . "/upg_working";
		if ( file_exists( $workingDir ) )
		{
			/* We need to make sure the array is 1-indexed otherwise the upgrader gets confused */
			$queriesJsonFile = $workingDir . "/queries.json";
			if ( file_exists( $queriesJsonFile ) )
			{
				$write = array();
				$i = 0;
				foreach ( json_decode( \file_get_contents( $queriesJsonFile ), TRUE ) as $query )
				{
					$write[ ++$i ] = $query;
				}
				static::writeJson( $queriesJsonFile, $write );
			}
			
			/* Add the actual version number in upgrade.php & options.php */
			$versionReplacement = function( $file ) use ( $human, $long )
			{
				if ( file_exists( $file ) )
				{
					$contents = file_get_contents( $file );
					$contents = str_replace(
						array(
							'{version_human}',
							'upg_working',
							'{version_long}'
						),
						array(
							$human,
							"upg_{$long}",
							$long
						),
						$contents
					);
					\file_put_contents( $file, $contents );
				}
			};

			/* Make the replacement */
			$versionReplacement( $workingDir . "/upgrade.php" );
			$versionReplacement( $workingDir . "/options.php" );
			
			/* Rename the directory */
			rename( $workingDir, $setupDir . "/upg_{$long}" );
		}

		/* Update core_dev */
		\IPS\Db::i()->update( 'core_dev', array(
			'working_version'	=> $long,
		), array( 'app_key=? AND working_version=?', $this->directory, 'working' ) );
	}
	
	/**
	 * Build application for release
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function build()
	{
		/* Use full upgrader? */
		$forceFullUpgrade = FALSE;

		/* Write the application data to the application.json file */
		$applicationData	= array(
			'application_title'	=> \IPS\Member::loggedIn()->language()->get('__app_' . $this->directory ),
			'app_author'		=> $this->author,
			'app_directory'		=> $this->directory,
			'app_protected'		=> $this->protected,
			'app_website'		=> $this->website,
			'app_update_check'	=> $this->update_check,
			'app_hide_tab'		=> $this->hide_tab
		);
		
		\IPS\Application::writeJson( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/application.json', $applicationData );

		/* Update app version data */
		$versions		= $this->getAllVersions();
		$longVersions	= array_keys( $versions );
		$humanVersions	= array_values( $versions );
		if( \count($versions) )
		{
			$latestLVersion	= array_pop( $longVersions );
			$latestHVersion	= array_pop( $humanVersions );

			\IPS\Db::i()->update( 'core_applications', array( 'app_version' => $latestHVersion, 'app_long_version' => $latestLVersion ), array( 'app_directory=?', $this->directory ) );

			$this->long_version = $latestLVersion;
			$this->version		= $latestHVersion;
		}
		$setupDir = \IPS\ROOT_PATH . '/applications/' . $this->directory . '/setup/upg_' . $this->long_version;
		if ( !is_dir( $setupDir ) )
		{
			mkdir( $setupDir );
		}

		/* Take care of languages for this app */
		$languageChanges = $this->buildLanguages();
		$langChangesFile = $setupDir . '/lang.json';
		if ( \count( array_filter( $languageChanges['normal'] ) ) or \count( array_filter( $languageChanges['js'] ) ) )
		{
			if ( file_exists( $langChangesFile ) )
			{
				$previousLangChanges = json_decode( file_get_contents( $langChangesFile ), TRUE );				
				$languageChanges['normal'] = $this->_combineChanges( $languageChanges['normal'], $previousLangChanges['normal'] );
				$languageChanges['js'] = $this->_combineChanges( $languageChanges['js'], $previousLangChanges['js'] );
			}
			
			if ( \count( array_filter( $languageChanges['normal'] ) ) or \count( array_filter( $languageChanges['js'] ) ) )
			{
				\file_put_contents( $langChangesFile, json_encode( $languageChanges, JSON_PRETTY_PRINT ) );
			}
			elseif ( \file_exists( $langChangesFile ) )
			{
				\unlink( $langChangesFile );
			}
		}	
		$this->installLanguages();

		/* Take care of skins for this app */
		$themeChanges = $this->buildThemeTemplates();
		$themeChangesFile = $setupDir . '/theme.json';
		if ( \count( array_filter( $themeChanges['html'] ) ) or \count( array_filter( $themeChanges['css'] ) ) or \count( array_filter( $themeChanges['resources'] ) ) )
		{
			if ( file_exists( $themeChangesFile ) )
			{
				$previousThemeChanges = json_decode( file_get_contents( $themeChangesFile ), TRUE );				
				$themeChanges['html'] = $this->_combineChanges( $themeChanges['html'], $previousThemeChanges['html'] );
				$themeChanges['css'] = $this->_combineChanges( $themeChanges['css'], $previousThemeChanges['css'] );
				$themeChanges['resources'] = $this->_combineChanges( $themeChanges['resources'], $previousThemeChanges['resources'] );
			}
			
			if ( \count( array_filter( $themeChanges['html'] ) ) or \count( array_filter( $themeChanges['css'] ) ) or \count( array_filter( $themeChanges['resources'] ) ) )
			{
				\file_put_contents( $themeChangesFile, json_encode( $themeChanges, JSON_PRETTY_PRINT ) );
			}
			elseif ( \file_exists( $themeChangesFile ) )
			{
				\unlink( $themeChangesFile );
			}
		}
		
		/* Take care of emails for this app */
		$emailTemplateChanges = $this->buildEmailTemplates();
		$emailTemplateChangesFile = $setupDir . '/emailTemplates.json';
		if ( \count( array_filter( $emailTemplateChanges ) ) )
		{
			if ( file_exists( $emailTemplateChangesFile ) )
			{
				$emailTemplateChanges = $this->_combineChanges( $emailTemplateChanges, json_decode( file_get_contents( $emailTemplateChangesFile ), TRUE ) );
			}
			
			if ( \count( array_filter( $emailTemplateChanges ) ) )
			{
				\file_put_contents( $emailTemplateChangesFile, json_encode( $emailTemplateChanges, JSON_PRETTY_PRINT ) );
			}
			elseif ( \file_exists( $emailTemplateChangesFile ) )
			{
				\unlink( $emailTemplateChangesFile );
			}
		}
		$this->installEmailTemplates();
				
		/* Take care of javascript for this app */
		$jsChanges = $this->buildJavascript();
		$jsChangesFile = $setupDir . '/javascript.json';
		if ( \count( array_filter( $jsChanges['files'] ) ) or \count( array_filter( $jsChanges['orders'] ) ) )
		{
			if ( file_exists( $jsChangesFile ) )
			{
				$previousJsChanges = json_decode( file_get_contents( $jsChangesFile ), TRUE );				
				$jsChanges['files'] = $this->_combineChanges( $jsChanges['files'], $previousJsChanges['files'] );
				$jsChanges['orders'] = $this->_combineChanges( $jsChanges['orders'], $previousJsChanges['orders'] );
			}
			
			if ( \count( array_filter( $jsChanges['files'] ) ) or \count( array_filter( $jsChanges['orders'] ) ) )
			{
				\file_put_contents( $jsChangesFile, json_encode( $jsChanges, JSON_PRETTY_PRINT ) );
			}
			elseif ( \file_exists( $jsChangesFile ) )
			{
				\unlink( $jsChangesFile );
			}

			/* Force full upgrade if global JS has changed */
			foreach( new \RecursiveIteratorIterator( new \RecursiveArrayIterator( $jsChanges ) ) as $k => $v )
			{
				if( mb_substr( $v, 0, 6 ) === 'global' )
				{
					$forceFullUpgrade = true;
					break;
				}
			}
		}
		$this->installJavascript();
				
		/* Take care of hooks for this app */
		$this->buildHooks();
		
		/* And custom build routines */
		foreach( $this->extensions( 'core', 'Build' ) as $builder )
		{
			$builder->build();
		}
		
		/* Write a build.xml file with the current json data so we know what has changed next time we build */
		$jsonChanges = $this->buildJsonData();
		foreach ( array( 'modules', 'tasks', 'settings', 'widgets', 'acpSearchKeywords', 'hooks', 'themeSettings' ) as $k )
		{
			if ( isset( $jsonChanges[ $k ] ) and ( $jsonChanges[ $k ]['added'] or $jsonChanges[ $k ]['edited'] or $jsonChanges[ $k ]['removed'] ) )
			{
				$changesFile = "{$setupDir}/{$k}.json";
				
				if ( file_exists( $changesFile ) )
				{
					$jsonChanges[ $k ] = $this->_combineChanges( $jsonChanges[ $k ], json_decode( file_get_contents( $changesFile ), TRUE ) );
				}
				
				if ( $jsonChanges[ $k ]['added'] or $jsonChanges[ $k ]['edited'] or $jsonChanges[ $k ]['removed'] )
				{
					\file_put_contents( $changesFile, json_encode( $jsonChanges[ $k ], JSON_PRETTY_PRINT ) );
				}
				elseif ( \file_exists( $changesFile ) )
				{
					\unlink( $changesFile );
				}
			}
		}

		/* Included CMS Templates */
		if( file_exists( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/dev/cmsTemplates.json' ) )
		{
			$pagesTemplates = json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/dev/cmsTemplates.json' ), TRUE );
			$xml = \IPS\cms\Templates::exportAsXml( $pagesTemplates );

			if( $xml )
			{
				if ( is_writable( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data' ) )
				{
					\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/cmsTemplates.xml', $xml->outputMemory() );
				}
				else
				{
					throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
				}
			}
		}
		
		/* Write the version data file */
		\file_put_contents( $setupDir . '/data.json', json_encode( array(
			'id'					=> $this->long_version,
			'name'					=> $this->version,
			'steps'					=> array(
				'queries'				=> file_exists( $setupDir . "/queries.json" ),
				'lang'					=> file_exists( $langChangesFile ),
				'theme'					=> file_exists( $themeChangesFile ),
				'themeSettings'			=> file_exists( $setupDir . "/themeSettings.json" ),
				'javascript'			=> file_exists( $jsChangesFile ),
				'emailTemplates'		=> file_exists( $emailTemplateChangesFile ),
				'hooks'					=> file_exists( $setupDir . "/hooks.json" ),
				'acpSearchKeywords'		=> file_exists( $setupDir . "/acpSearchKeywords.json" ),
				'settings'				=> file_exists( $setupDir . "/settings.json" ),
				'tasks'					=> file_exists( $setupDir . "/tasks.json" ),
				'modules'				=> file_exists( $setupDir . "/modules.json" ),
				'widgets'				=> file_exists( $setupDir . "/widgets.json" ),
				'customOptions'			=> file_exists( $setupDir . "/options.php" ),
				'customRoutines'		=> file_exists( $setupDir . "/upgrade.php" ),
			),
			'forceMainUpgrader'			=> $forceFullUpgrade,
			'forceManualDownloadNoCiC'	=> FALSE,
			'forceManualDownloadCiC'		=> FALSE,
		), JSON_PRETTY_PRINT ) );
	}
	
	/**
	 * Combine information about changes when rebuilding a version after it was already built once before
	 *
	 * @param	array	$newChanges			The changes detected in this build
	 * @param	array	$previousChanges		The changes detected in the previous build
	 * @param	bool	$keysOnly			Set to TRUE if the changes is just a list of keys, or FALSE if they're key/values
	 * @return	array
	 */
	protected function _combineChanges( $newChanges, $previousChanges, $keysOnly=TRUE )
	{
		if ( $keysOnly )
		{
			foreach ( $newChanges['added'] as $v )
			{
				if ( \in_array( $v, $previousChanges['removed'] ) )
				{
					unset( $previousChanges['removed'][ array_search( $v, $previousChanges['removed'] ) ] );
				}
				else
				{
					$previousChanges['added'][] = $v;
				}
			}
			foreach ( $newChanges['edited'] as $v )
			{
				if ( !\in_array( $v, $previousChanges['added'] ) )
				{
					$previousChanges['edited'][] = $v;
				}
			}
			foreach ( $newChanges['removed'] as $v )
			{
				if ( \in_array( $v, $previousChanges['added'] ) )
				{
					unset( $previousChanges['added'][ array_search( $v, $previousChanges['added'] ) ] );
				}
				elseif ( \in_array( $v, $previousChanges['edited'] ) )
				{
					unset( $previousChanges['edited'][ array_search( $v, $previousChanges['edited'] ) ] );
					$previousChanges['removed'][] = $v;
				}
				elseif ( !\in_array( $v, $previousChanges['removed'] ) )
				{
					$previousChanges['removed'][] = $v;
				}
			}
		}
		else
		{
			foreach ( $newChanges['added'] as $k => $v )
			{
				if ( isset( $previousChanges['removed'][ $k ] ) )
				{
					unset( $previousChanges['removed'][ $k ] );
				}
				else
				{
					$previousChanges['added'][ $k ] = $v;
				}
			}
			foreach ( $newChanges['edited'] as $k => $v )
			{
				if ( !isset( $previousChanges['added'][ $k ] ) )
				{
					$previousChanges['edited'][ $k ] = $v;
				}
			}
			foreach ( $newChanges['removed'] as $k => $v )
			{
				if ( isset( $previousChanges['added'][ $k ] ) )
				{
					unset( $previousChanges['added'][ $k ] );
				}
				elseif ( isset( $previousChanges['edited'][ $k ] ) )
				{
					unset( $previousChanges['edited'][ $k ] );
					$previousChanges['removed'][ $k ] = $v;
				}
				elseif ( !isset( $previousChanges['removed'][ $k ] ) )
				{
					$previousChanges['removed'][ $k ] = $v;
				}
			}
		}
		
		return $previousChanges;
	}

	/**
	 * Build skin templates for an app
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function buildThemeTemplates()
	{
		/* Delete compiled items */
		\IPS\Theme::deleteCompiledTemplate( $this->directory );
		\IPS\Theme::deleteCompiledCss( $this->directory );
		\IPS\Theme::removeResources( $this->directory );
		
		\IPS\Theme::i()->importDevHtml( $this->directory, 0 );
		\IPS\Theme::i()->importDevCss( $this->directory, 0 );
		
		/* Get current XML file for calculating differences */
		$return = array( 'html' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ), 'css' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ), 'resources' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ) );
		$current = array( 'html' => array(), 'css' => array(), 'resources' => array() );
		$currentFile = \IPS\ROOT_PATH . "/applications/{$this->directory}/data/theme.xml";
		if ( file_exists( $currentFile ) )
		{
			$xml = \IPS\Xml\SimpleXML::loadFile( $currentFile );
			foreach ( $xml->template as $html )
			{
				$attributes = iterator_to_array( $html->attributes() );				
				
				$current['html'][ "{$attributes['template_location']}/{$attributes['template_group']}/{$attributes['template_name']}" ] = array(
					'params'	=> $attributes['template_data'],
					'content'	=> (string) $html
				);
			}
			foreach ( $xml->css as $css )
			{
				$attributes = iterator_to_array( $css->attributes() );		
				
				$current['css'][ "{$attributes['css_location']}/" . ( $attributes['css_path'] == '.' ? '' : "{$attributes['css_path']}/" ) . $attributes['css_name'] ] = array(
					'params'	=> $attributes['css_attributes'],
					'content'	=> (string) $css
				);
			}
			foreach ( $xml->resource as $resource )
			{
				$attributes = iterator_to_array( $resource->attributes() );
				$current['resources'][ "{$attributes['location']}{$attributes['path']}{$attributes['name']}" ] = (string) $resource;
			}
		}

		/* Build XML and write to app directory */
		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent( TRUE );
		$xml->startDocument( '1.0', 'UTF-8' );
		
		/* Root tag */
		$xml->startElement('theme');
		$xml->startAttribute('name');
		$xml->text( "Default" );
		$xml->endAttribute();
		$xml->startAttribute('author_name');
		$xml->text( "Invision Power Services, Inc" );
		$xml->endAttribute();
		$xml->startAttribute('author_url');
		$xml->text( "https://www.invisioncommunity.com" );
		$xml->endAttribute();
		
		/* Skin settings */
		foreach (
			\IPS\Db::i()->select(
				'core_theme_settings_fields.*',
				'core_theme_settings_fields',
				array( 'sc_set_id=? AND sc_app=?', 1, $this->directory ), 
				'sc_key ASC'
			)
			as $row
		)
		{
			/* Initiate the <fields> tag */
			$xml->startElement('field');
			
			unset( $row['sc_id'], $row['sc_set_id'] );
			
			foreach( $row as $k => $v )
			{
				if ( $k != 'sc_content' )
				{
					$xml->startAttribute( $k );
					$xml->text( $v );
					$xml->endAttribute();
				}
			}
			
			/* Write value */
			if ( preg_match( '/<|>|&/', $row['sc_content'] ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $row['sc_content'] ) );
			}
			else
			{
				$xml->text( $row['sc_content'] );
			}
			
			/* Close the <fields> tag */
			$xml->endElement();
		}
		
		/* Templates */
		foreach ( \IPS\Db::i()->select( '*', 'core_theme_templates', array( 'template_set_id=? AND template_user_added=? AND template_app=?', 0, 0 , $this->directory ), 'template_group, template_name, template_location' ) as $template )
		{
			/* Initiate the <template> tag */
			$xml->startElement('template');
			$attributes = array();
			foreach( $template as $k => $v )
			{
				if ( \in_array( \substr( $k, 9 ), array('app', 'location', 'group', 'name', 'data' ) ) )
				{
					$attributes[ $k ] = $v;
					$xml->startAttribute( $k );
					$xml->text( $v );
					$xml->endAttribute();
				}
			}
			
			/* Write value */
			if ( preg_match( '/<|>|&/', $template['template_content'] ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $template['template_content'] ) );
			}
			else
			{
				$xml->text( $template['template_content'] );
			}
			
			/* Close the <template> tag */
			$xml->endElement();
			
			/* Note it */
			$k = "{$attributes['template_location']}/{$attributes['template_group']}/{$attributes['template_name']}";
			if ( !isset( $current['html'][ $k ] ) )
			{
				$return['html']['added'][] = $k;
			}
			elseif ( $current['html'][ $k ]['params'] != $attributes['template_data'] or $current['html'][ $k ]['content'] != $template['template_content'] )
			{
				$return['html']['edited'][] = $k;
			}
			unset( $current['html'][ $k ] );
		}
		
		/* Css */
		foreach ( \IPS\Db::i()->select( '*', 'core_theme_css', array( 'css_set_id=? AND css_added_to=? AND css_app=?', 0, 0 , $this->directory ), 'css_path, css_name, css_location' ) as $css )
		{
			$xml->startElement('css');
			$attributes = array();
			foreach( $css as $k => $v )
			{
				if ( \in_array( \substr( $k, 4 ), array('app', 'location', 'path', 'name', 'attributes' ) ) )
				{
					$attributes[ $k ] = $v;
					$xml->startAttribute( $k );
					$xml->text( $v );
					$xml->endAttribute();
				}
			}

			/* Write value */
			if ( preg_match( '/<|>|&/', $css['css_content'] ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $css['css_content'] ) );
			}
			else
			{
				$xml->text( $css['css_content'] );
			}
			$xml->endElement();
			
			/* Note it */
			$k = "{$attributes['css_location']}/" . ( $attributes['css_path'] === '.' ? '' : "{$attributes['css_path']}/" ) . $attributes['css_name'];
			if ( !isset( $current['css'][ $k ] ) )
			{
				$return['css']['added'][] = $k;
			}
			elseif ( $current['css'][ $k ]['params'] != $attributes['css_attributes'] or $current['css'][ $k ]['content'] != $css['css_content'] )
			{
				$return['css']['edited'][] = $k;
			}
			unset( $current['css'][ $k ] );
		}
		
		/* Resources */
		$_resources	= $this->_buildThemeResources();
		
		foreach ( $_resources as $data )
		{
			$xml->startElement('resource');
					
			$xml->startAttribute('name');
			$xml->text( $data['resource_name'] );
			$xml->endAttribute();
			
			$xml->startAttribute('app');
			$xml->text( $data['resource_app'] );
			$xml->endAttribute();
			
			$xml->startAttribute('location');
			$xml->text( $data['resource_location'] );
			$xml->endAttribute();
			
			$xml->startAttribute('path');
			$xml->text( $data['resource_path'] );
			$xml->endAttribute();
			
			/* Write value */
			$encoded = base64_encode( $data['resource_data'] );
			$xml->text( $encoded );
			
			$xml->endElement();
			
			/* Note it */
			$k = "{$data['resource_location']}{$data['resource_path']}{$data['resource_name']}";
			if ( !isset( $current['resources'][ $k ] ) )
			{
				$return['resources']['added'][] = $k;
			}
			elseif ( $current['resources'][ $k ] != $encoded )
			{				
				$return['resources']['edited'][] = $k;
			}
			unset( $current['resources'][ $k ] );
		}
		
		/* Finish */
		$xml->endDocument();
		
		/* Write it */
		if ( is_writable( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data' ) )
		{
			\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/theme.xml', $xml->outputMemory() );
		}
		else
		{
			throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
		}

		/* Return */
		$return['html']['removed'] = array_keys( $current['html'] );
		$return['css']['removed'] = array_keys( $current['css'] );
		$return['resources']['removed'] = array_keys( $current['resources'] );
		return $return;
	}

	/**
	 * Build Resources ready for non IN_DEV use
	 *
	 * @return	array
	 */
	protected function _buildThemeResources()
	{
		$resources = array();
		$path	= \IPS\ROOT_PATH . "/applications/" . $this->directory . "/dev/resources/";

		\IPS\Theme::i()->importDevResources( $this->directory, 0 );

		if ( is_dir( $path ) )
		{
			foreach( new \DirectoryIterator( $path ) as $location )
			{
				if ( $location->isDot() || \substr( $location->getFilename(), 0, 1 ) === '.' )
				{
					continue;
				}

				if ( $location->isDir() )
				{
					$resources	= $this->_buildResourcesRecursive( $location->getFilename(), '/', $resources );
				}
			}
		}

		return $resources;
	}
	
	/**
	 * Build Resources ready for non IN_DEV use (Iterable)
	 * Theme resources should be raw binary data everywhere (filesystem and DB) except in the theme XML download where they are base64 encoded.
	 *
	 * @param	string	$location	Location Folder Name
	 * @param	string	$path		Path
	 * @param	array	$resources	Array of resources to append to
	 * @return	array
	 */
	protected function _buildResourcesRecursive( $location, $path='/', $resources=array() )
	{
		$root = \IPS\ROOT_PATH . "/applications/{$this->directory}/dev/resources/{$location}";
	
		foreach( new \DirectoryIterator( $root . $path ) as $file )
		{
			if ( $file->isDot() || \substr( $file->getFilename(), 0, 1 ) === '.' || $file == 'index.html' )
			{
				continue;
			}
	
			if ( $file->isDir() )
			{
				$resources	= $this->_buildResourcesRecursive( $location, $path . $file->getFilename() . '/', $resources );
			}
			else
			{
				$resources[] = array(
					'resource_app'		=> $this->directory,
					'resource_location'	=> $location,
					'resource_path'		=> $path,
					'resource_name'		=> $file->getFilename(),
					'resource_data'		=> \file_get_contents( $root . $path . $file->getFilename() ),
					'resource_added'	=> time()
				);
			}
		}

		return $resources;
	}

	/**
	 * Build languages for an app
	 *
	 * @return	array
	 * @throws	\RuntimeException
	 */
	public function buildLanguages()
	{
		$return = array( 'normal' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ), 'js' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ) );
		
		/* Start with current XML file */
		$currentFile = \IPS\ROOT_PATH . "/applications/{$this->directory}/data/lang.xml";

		$current = array( '0' => array(), '1' => array() );

		if ( file_exists( $currentFile ) )
		{
			foreach ( \IPS\Xml\SimpleXML::loadFile( $currentFile )->app->word as $word )
			{
				$attributes = iterator_to_array( $word->attributes() );				
				$current[ (string) $attributes['js'] ][ (string) $attributes['key'] ] = (string) $word;
			}
		}

		/* Create the lang.xml file */
		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent( TRUE );
		$xml->startDocument( '1.0', 'UTF-8' );
				
		/* Root tag */
		$xml->startElement('language');

		/* Initiate the <app> tag */
		$xml->startElement('app');
		
		/* Set key */
		$xml->startAttribute('key');
		$xml->text( $this->directory );
		$xml->endAttribute();
		
		/* Set version */
		$xml->startAttribute('version');
		$xml->text( $this->long_version );
		$xml->endAttribute();
		
		/* Import the language files */
		$lang	= array();

		require \IPS\ROOT_PATH . "/applications/{$this->directory}/dev/lang.php";
		foreach ( $lang as $k => $v )
		{
			/* Start */
			$xml->startElement( 'word' );
			
			/* Add key */
			$xml->startAttribute('key');
			$xml->text( $k );
			$xml->endAttribute();

			/* Add javascript flag */
			$xml->startAttribute('js');
			$xml->text( 0 );
			$xml->endAttribute();
							
			/* Write value */
			if ( preg_match( '/<|>|&/', $v ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $v ) );
			}
			else
			{
				$xml->text( $v );
			}
			
			/* End */
			$xml->endElement();

			/* Enforce \n line endings */
			if( mb_strtolower( mb_substr( PHP_OS, 0, 3 ) ) === 'win' )
			{
				$v = str_replace( "\r\n", "\n", $v );
			}

			/* Note it */
			if ( !isset( $current['0'][ $k ] ) )
			{
				$return['normal']['added'][] = $k;
			}
			elseif ( isset( $current['0'][ $k ] ) AND $current['0'][ $k ] != $v )
			{
				$return['normal']['edited'][] = $k;
			}

			if ( isset( $current['0'][ $k ] ) )
			{
				unset( $current['0'][ $k ] );
			}
		}

		$lang	= array();

		require \IPS\ROOT_PATH . "/applications/{$this->directory}/dev/jslang.php";
		foreach ( $lang as $k => $v )
		{
			/* Start */
			$xml->startElement( 'word' );
			
			/* Add key */
			$xml->startAttribute('key');
			$xml->text( $k );
			$xml->endAttribute();

			/* Add javascript flag */
			$xml->startAttribute('js');
			$xml->text( 1 );
			$xml->endAttribute();
							
			/* Write value */
			if ( preg_match( '/<|>|&/', $v ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $v ) );
			}
			else
			{
				$xml->text( $v );
			}
			
			/* End */
			$xml->endElement();

			/* Enforce \n line endings */
			if( mb_strtolower( mb_substr( PHP_OS, 0, 3 ) ) === 'win' )
			{
				$v = str_replace( "\r\n", "\n", $v );
			}
			
			/* Note it */
			if ( !isset( $current['1'][ $k ] ) )
			{
				$return['js']['added'][] = $k;
			}
			elseif ( isset( $current['1'][ $k ] ) AND $current['1'][ $k ] != $v )
			{
				$return['js']['edited'][] = $k;
			}
			if ( isset( $current['1'][ $k ] ) )
			{
				unset( $current['1'][ $k ] );
			}
		}

		/* Finish */
		$xml->endDocument();
					
		/* Write it */
		if ( is_writable( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data' ) )
		{
			\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/lang.xml', $xml->outputMemory() );
		}
		else
		{
			throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
		}
		
		/* Return */
		$return['normal']['removed'] = array_keys( $current['0'] );
		$return['js']['removed'] = array_keys( $current['1'] );
		return $return;
	}

	/**
	 * Build email templates for an app
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function buildEmailTemplates()
	{
		/* Get current XML file for calculating differences */
		$return = array( 'added' => array(), 'edited' => array(), 'removed' => array() );
		$current = array();
		$currentFile = \IPS\ROOT_PATH . "/applications/{$this->directory}/data/emails.xml";
		if ( file_exists( $currentFile ) )
		{
			$xml = \IPS\Xml\SimpleXML::loadFile( $currentFile );
			foreach ( $xml->template as $template )
			{
				$attributes = iterator_to_array( $template->attributes() );
								
				$current[ (string) (string) $template->template_name ] = array(
					'params'		=> (string) $template->template_data,
					'html'		=> (string) $template->template_content_html,
					'plaintext'	=> (string) $template->template_content_plaintext,
					'pinned'		=> (string) $template->template_pinned,
				);
			}
		}
		
		/* Where are we looking? */
		$path = \IPS\ROOT_PATH . "/applications/{$this->directory}/dev/email";
		
		/* We create an array and store the templates temporarily so we can merge plaintext and HTML together */
		$templates		= array();
		$templateKeys	= array();

		/* Loop over files in the directory */
		if ( is_dir( $path ) )
		{
			foreach( new \DirectoryIterator( $path ) as $location )
			{
				if ( $location->isDir() and mb_substr( $location, 0, 1 ) !== '.' and ( $location->getFilename() === 'plain' or $location->getFilename() === 'html' ) )
				{
					foreach( new \DirectoryIterator( $path . '/' . $location->getFilename() ) as $sublocation )
					{
						if ( $sublocation->isDir() and mb_substr( $sublocation, 0, 1 ) !== '.' )
						{
							foreach( new \DirectoryIterator( $path . '/' . $location->getFilename() . '/' . $sublocation->getFilename() ) as $file )
							{
								if ( $file->isDot() or !$file->isFile() or mb_substr( $file, 0, 1 ) === '.' or $file->getFilename() === 'index.html' )
								{
									continue;
								}
								
								$data = $this->_buildEmailTemplateFromInDev( $path . '/' . $location->getFilename() . '/' . $sublocation->getFilename(), $file, $sublocation->getFilename() . '__' );
								$extension = mb_substr( $file->getFilename(), mb_strrpos( $file->getFilename(), '.' ) + 1 );
								$type = ( $extension === 'txt' ) ? "plaintext" : "html";
								
								if ( ! isset( $templates[ $data['template_name'] ] ) )
								{
									$templates[ $data['template_name'] ] = array();
								}
				
								$templates[ $data['template_name'] ] = array_merge( $templates[ $data['template_name'] ], $data );
				
								/* Delete the template in the store */
								$key = $templates[ $data['template_name'] ]['template_key'] . '_email_' . $type;
								unset( \IPS\Data\Store::i()->$key );
				
								/* Remember our templates */
								$templateKeys[]	= $data['template_key'];
							}
						}
					}

				}
				else
				{
					if ( $location->isDot() or !$location->isFile() or mb_substr( $location, 0, 1 ) === '.' or $location->getFilename() === 'index.html' )
					{
						continue;
					}
					
					$data = $this->_buildEmailTemplateFromInDev( $path, $location );
					$extension = mb_substr( $location->getFilename(), mb_strrpos( $location->getFilename(), '.' ) + 1 );
					$type = ( $extension === 'txt' ) ? "plaintext" : "html";
					
					if ( ! isset( $templates[ $data['template_name'] ] ) )
					{
						$templates[ $data['template_name'] ]	= array();
					}
	
					$templates[ $data['template_name'] ] = array_merge( $templates[ $data['template_name'] ], $data );
	
					/* Delete the template in the store */
					$key = $templates[ $data['template_name'] ]['template_key'] . '_email_' . $type;
					unset( \IPS\Data\Store::i()->$key );
	
					/* Remember our templates */
					$templateKeys[]	= $data['template_key'];
				}
			}
		}

		/* Clear out invalid templates */
		\IPS\Db::i()->delete( 'core_email_templates', array( "template_app=? AND template_key NOT IN('" . implode( "','", $templateKeys ) . "')", $this->directory ) );

		/* If we have any templates, put them in the database */
		if( \count($templates) )
		{
			foreach( $templates as $template )
			{
				\IPS\Db::i()->insert( 'core_email_templates', $template, TRUE );
			}

			/* Build the executable copies */
			$this->parseEmailTemplates();
		}

		$xml = \IPS\Xml\SimpleXML::create('emails');

		/* Templates */
		foreach ( \IPS\Db::i()->select( '*', 'core_email_templates', array( 'template_parent=? AND template_app=?', 0, $this->directory ), 'template_key ASC' ) as $template )
		{
			$forXml = array();
			foreach( $template as $k => $v )
			{
				if ( \in_array( \substr( $k, 9 ), array('app', 'name', 'content_html', 'data', 'content_plaintext', 'pinned' ) ) )
				{
					$forXml[ $k ] = $v;
				}
			}
			
			$xml->addChild( 'template', $forXml );
			
			/* Note it */
			$compare = array(
				'params'		=> $template['template_data'],
				'html'			=> $template['template_content_html'],
				'plaintext'		=> $template['template_content_plaintext'],
				'pinned'		=> $template['template_pinned']
			);
			if ( !isset( $current[ $template['template_name'] ] ) )
			{
				$return['added'][] = $template['template_name'];
			}
			elseif ( $current[ $template['template_name'] ] != $compare )
			{
				$return['edited'][] = $template['template_name'];
			}
			unset( $current[ $template['template_name'] ] );
		}

		/* Write it */
		if ( is_writable( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data' ) )
		{
			\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/emails.xml', $xml->asXML() );
		}
		else
		{
			throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
		}
		
		/* Return */
		$return['removed'] = array_keys( $current );
		return $return;
	}
	
	/**
	 * Imports an IN_DEV email template into the database
	 *
	 * @param	string		$path			Path to file
	 * @param	object		$file			DirectoryIterator File Object
	 * @param	string|null	$namePrefix		Name prefix
	 * @return  array
	 */
	protected function _buildEmailTemplateFromInDev( $path, $file, $namePrefix='' )
	{
		/* Get the content */
		$html	= file_get_contents( $path . '/' . $file->getFilename() );
		$params	= array();
		
		/* Parse the header tag */
		preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $html, $params );
		
		/* Strip the params tag */
		$html	= str_replace( $params[0], '', $html );

		/* Enforce \n line endings */
		if( mb_strtolower( mb_substr( PHP_OS, 0, 3 ) ) === 'win' )
		{
			$html = str_replace( "\r\n", "\n", $html );
		}
		
		/* Figure out some details */
		$extension = mb_substr( $file->getFilename(), mb_strrpos( $file->getFilename(), '.' ) + 1 );
		$name	= $namePrefix . str_replace( '.' . $extension, '', $file->getFilename() );
		$type	= ( $extension === 'txt' ) ? "plaintext" : "html";

		$return = array(
			'template_app'				=> $this->directory,
			'template_name'				=> $name,
			'template_data'				=> ( isset( $params[1] ) ) ? $params[1] : '',
			'template_content_' . $type	=> $html,
			'template_key'				=> md5( $this->directory . ';' . $name ),
		);

		return $return;
	}
	
	/**
	 * Build javascript for this app
	 *
	 * @return	array
	 * @throws	\RuntimeException
	 */
	public function buildJavascript()
	{
		/* Get current XML file for calculating differences */
		$return = array( 'files' => array( 'added' => array(), 'edited' => array(), 'removed' => array() ), 'orders' =>  array( 'added' => array(), 'edited' => array(), 'removed' => array() ) );
		$currentFile = \IPS\ROOT_PATH . "/applications/{$this->directory}/data/javascript.xml";
		$current = array( 'files' => array(), 'orders' => array() );
		if ( file_exists( $currentFile ) )
		{
			$xml = \IPS\Xml\SimpleXML::loadFile( $currentFile );
			foreach ( $xml->file as $javascript )
			{
				$attributes = iterator_to_array( $javascript->attributes() );
				
				$current['files'][ "{$attributes['javascript_app']}/{$attributes['javascript_location']}/" . ( trim( $attributes['javascript_path'] ) ? "{$attributes['javascript_path']}/" : '' ) . "{$attributes['javascript_name']}" ] = (string) $javascript;
			}
			foreach ( $xml->order as $order )
			{
				$attributes = iterator_to_array( $order->attributes() );
				
				$current['orders'][ "{$attributes['app']}/{$attributes['path']}" ] = (string) $order;
			}
		}
				
		/* Remove existing file object maps */
		$map = isset( \IPS\Data\Store::i()->javascript_map ) ? \IPS\Data\Store::i()->javascript_map : array();
		$map[ $this->directory ] = array();
		
		\IPS\Data\Store::i()->javascript_map = $map;
		
		$xml = \IPS\Output\Javascript::createXml( $this->directory, $current, $return );
		
		/* Write it */
		if ( is_writable( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data' ) )
		{
			\file_put_contents( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/javascript.xml', $xml->outputMemory() );
		}
		else
		{
			throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
		}
		
		/* Return */
		return $return;
	}
	
	/**
	 * Build hooks for an app
	 *
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public function buildHooks()
	{
		/* Build data */
		$data = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_hooks', array( 'app=?', $this->directory ) ) as $hook )
		{
			$data[ $hook['filename'] ] = array(
				'type'		=> $hook['type'],
				'class'		=> $hook['class'],
			);
		}
				
		/* Write it */
		try
		{
			\IPS\Application::writeJson( \IPS\ROOT_PATH . '/applications/' . $this->directory . '/data/hooks.json', $data );
		}
		catch ( \RuntimeException $e )
		{
			throw new \RuntimeException( \IPS\Member::loggedIn()->language()->addToStack('dev_could_not_write_data') );
		}
	}
	
	/**
	 * Build extensions.json file for an app
	 *
	 * @return	array
	 * @throws	\DomainException
	 */
	public function buildExtensionsJson()
	{
		$json = array();
		$appsMainExtensionDir = new \DirectoryIterator( \IPS\ROOT_PATH . "/applications/{$this->directory}/extensions/" );
		foreach ( $appsMainExtensionDir as $appDir )
		{
			if ( $appDir->isDir() and !$appDir->isDot() )
			{
				foreach ( new \DirectoryIterator( $appDir->getPathname() ) as $extensionDir )
				{
					if ( $extensionDir->isDir() and !$extensionDir->isDot() )
					{
						foreach ( new \DirectoryIterator( $extensionDir->getPathname() ) as $extensionFile )
						{
							if ( !$extensionFile->isDir() and !$extensionFile->isDot() and mb_substr( $extensionFile, -4 ) === '.php' AND mb_substr( $extensionFile, 0, 2 ) != '._' )
							{
								$classname = 'IPS\\' . $this->directory . '\extensions\\' . $appDir . '\\' . $extensionDir . '\\' . mb_substr( $extensionFile, 0, -4 );
								
								/* Check if class exists - sometimes we have to use blank files to wipe out old extensions */
								try
								{
									if( !class_exists( $classname ) )
									{
										continue;
									}
									
									if ( method_exists( $classname, 'deprecated' ) )
									{
										continue;
									}
								}
								catch( \ErrorException $e )
								{
									continue;
								}
								
								$json[ (string) $appDir ][ (string) $extensionDir ][ mb_substr( $extensionFile, 0, -4 ) ] = $classname;
							}
						}
					}
				}
			}
		}
		return $json;
	}
	
	/**
	 * Write a build.xml file with the current json data so we know what has changed between builds
	 *
	 * @return	void
	 */
	public function buildJsonData()
	{
		$file = \IPS\ROOT_PATH . "/applications/{$this->directory}/data/build.xml";
		
		/* Get current XML file for calculating differences */
		$return = array(
			'modules'			=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'tasks'				=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'settings'			=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'widgets'			=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'acpSearchKeywords'	=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'hooks'				=> array( 'added' => array(), 'edited' => array(), 'removed' => array() ),
			'themeSettings' 		=> array( 'added' => array(), 'edited' => array(), 'removed' => array() )
		);
		$current = array(
			'modules'			=> array(),
			'tasks'				=> array(),
			'settings'			=> array(),
			'widgets'			=> array(),
			'acpSearchKeywords'	=> array(),
			'hooks'				=> array(),
			'themeSettings'		=> array()
		);
		if ( file_exists( $file ) )
		{
			$xml = \IPS\Xml\SimpleXML::loadFile( $file );
			foreach ( $xml->module as $module )
			{
				$attributes = iterator_to_array( $module->attributes() );				
				$current['modules'][ (string) $module['key'] ] = (string) $module;
			}
			foreach ( $xml->task as $task )
			{
				$attributes = iterator_to_array( $task->attributes() );				
				$current['tasks'][ (string) $task ] = (string) $task['frequency'];
			}
			foreach ( $xml->setting as $setting )
			{
				$attributes = iterator_to_array( $setting->attributes() );				
				$current['settings'][ (string) $setting['key'] ] = (string) $setting;
			}
			foreach ( $xml->widget as $widget )
			{
				$attributes = iterator_to_array( $widget->attributes() );				
				$current['widgets'][ (string) $attributes['key'] ] = (string) $widget;
			}
			foreach ( $xml->acpsearch as $searchKeyword )
			{
				$attributes = iterator_to_array( $searchKeyword->attributes() );				
				$current['acpSearchKeywords'][ (string) $attributes['key'] ] = (string) $searchKeyword;
			}
			foreach ( $xml->hook as $hook )
			{
				$attributes = iterator_to_array( $hook->attributes() );				
				$current['hooks'][ (string) $attributes['key'] ] = (string) $hook;
			}
			foreach ( $xml->themesetting as $themeSetting )
			{
				$attributes = iterator_to_array( $themeSetting->attributes() );				
				$current['themeSettings'][ (string) $attributes['key'] ] = (string) $themeSetting;
			}
		}
				
		/* Build XML and write to app directory */
		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent( TRUE );
		$xml->startDocument( '1.0', 'UTF-8' );
		
		/* Root tag */
		$xml->startElement('build');
		
		/* Modules */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/modules.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/modules.json" ), TRUE ) as $area => $modules )
			{
				foreach ( $modules as $moduleKey => $moduleData )
				{
					$val = json_encode( $moduleData );
					
					$xml->startElement('module');
					$xml->startAttribute('key');
					$xml->text( "{$area}/{$moduleKey}" );
					$xml->endAttribute();
					$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
					$xml->endElement();
					
					if ( !isset( $current['modules'][ "{$area}/{$moduleKey}" ] ) )
					{
						$return['modules']['added'][] = "{$area}/{$moduleKey}";
					}
					elseif ( $current['modules'][ "{$area}/{$moduleKey}" ] != $val )
					{
						$return['modules']['edited'][] = "{$area}/{$moduleKey}";
					}
					unset( $current['modules'][ "{$area}/{$moduleKey}" ] );
				}				
			}
		}
		
		/* Tasks */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/tasks.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/tasks.json" ), TRUE ) as $taskKey => $taskFrequency )
			{
				$xml->startElement('task');
				$xml->startAttribute('frequency');
				$xml->text( $taskFrequency );
				$xml->endAttribute();
				$xml->text( $taskKey );
				$xml->endElement();
				
				if ( !isset( $current['tasks'][ $taskKey ] ) )
				{
					$return['tasks']['added'][] = $taskKey;
				}
				elseif ( $current['tasks'][ $taskKey ] != $taskFrequency )
				{
					$return['tasks']['edited'][] = $taskKey;
				}
				unset( $current['tasks'][ $taskKey ] );
			}
		}
		
		/* Settings */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/settings.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/settings.json" ), TRUE ) as $setting )
			{
				$val = json_encode( $setting );
				
				$xml->startElement('setting');
				$xml->startAttribute('key');
				$xml->text( $setting['key'] );
				$xml->endAttribute();
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
				$xml->endElement();
				
				if ( !isset( $current['settings'][ $setting['key'] ] ) )
				{
					$return['settings']['added'][] = $setting['key'];
				}
				elseif ( $current['settings'][ $setting['key'] ] != $val )
				{
					$return['settings']['edited'][] = $setting['key'];
				}
				unset( $current['settings'][ $setting['key'] ] );
			}
		}
		
		/* Widgets */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/widgets.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/widgets.json" ), TRUE ) as $widgetKey => $widgetData )
			{
				$val = json_encode( $widgetData );
				
				$xml->startElement('widget');
				$xml->startAttribute('key');
				$xml->text( $widgetKey );
				$xml->endAttribute();
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
				$xml->endElement();
				
				if ( !isset( $current['widgets'][ $widgetKey ] ) )
				{
					$return['widgets']['added'][] = $widgetKey;
				}
				elseif ( $current['widgets'][ $widgetKey ] != $val )
				{
					$return['widgets']['edited'][] = $widgetKey;
				}
				unset( $current['widgets'][ $widgetKey ] );
			}
		}
		
		/* ACP Search Keywords */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/acpsearch.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/acpsearch.json" ), TRUE ) as $searchUrl => $searchData )
			{
				$val = json_encode( $searchData );
				
				$xml->startElement('acpsearch');
				$xml->startAttribute('key');
				$xml->text( $searchUrl );
				$xml->endAttribute();
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
				$xml->endElement();
				
				if ( !isset( $current['acpSearchKeywords'][ $searchUrl ] ) )
				{
					$return['acpSearchKeywords']['added'][] = $searchUrl;
				}
				elseif ( $current['acpSearchKeywords'][ $searchUrl ] != $val )
				{
					$return['acpSearchKeywords']['edited'][] = $searchUrl;
				}
				unset( $current['acpSearchKeywords'][ $searchUrl ] );
			}
		}
		
		/* Hooks */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/hooks.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/hooks.json" ), TRUE ) as $hookKey => $hookData )
			{
				$val = json_encode( $hookData );
				
				$xml->startElement('hook');
				$xml->startAttribute('key');
				$xml->text( $hookKey );
				$xml->endAttribute();
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
				$xml->endElement();
				
				if ( !isset( $current['hooks'][ $hookKey ] ) )
				{
					$return['hooks']['added'][] = $hookKey;
				}
				elseif ( $current['hooks'][ $hookKey ] != $val )
				{
					$return['hooks']['edited'][] = $hookKey;
				}
				unset( $current['hooks'][ $hookKey ] );
			}
		}
		
		/* Master Theme Settings */
		if( file_exists( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/themesettings.json" ) )
		{
			foreach( json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$this->directory}/data/themesettings.json" ), TRUE ) as $themeSetting )
			{
				$val = json_encode( $themeSetting );
				
				$xml->startElement('themesetting');
				$xml->startAttribute('key');
				$xml->text( $themeSetting['sc_key'] );
				$xml->endAttribute();
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $val ) );
				$xml->endElement();
				
				if ( !isset( $current['themeSettings'][ $themeSetting['sc_key'] ] ) )
				{
					$return['themeSettings']['added'][] = $themeSetting['sc_key'];
				}
				elseif ( $current['themeSettings'][ $themeSetting['sc_key'] ] != $val )
				{
					$return['themeSettings']['edited'][] = $themeSetting['sc_key'];
				}
				unset( $current['themeSettings'][ $themeSetting['sc_key'] ] );
			}
		}
		
		/* Finish */
		$xml->endDocument();
		
		/* Write it */
		\file_put_contents( $file, $xml->outputMemory() );
		
		/* Return */
		$return['modules']['removed'] = array_keys( $current['modules'] );
		$return['tasks']['removed'] = array_keys( $current['tasks'] );
		$return['settings']['removed'] = array_keys( $current['settings'] );
		$return['widgets']['removed'] = array_keys( $current['widgets'] );
		$return['acpSearchKeywords']['removed'] = array_keys( $current['acpSearchKeywords'] );
		$return['hooks']['removed'] = array_keys( $current['hooks'] );
		$return['themeSettings']['removed'] = array_keys( $current['themeSettings'] );
		return $return;
	}

	/**
	 * Compile email template into executable template
	 *
	 * @return	void
	 */
	public function parseEmailTemplates()
	{
		foreach( \IPS\Db::i()->select( '*','core_email_templates', NULL, 'template_parent DESC' ) as $template )
		{
			/* Rebuild built copies */
			$htmlFunction	= 'namespace IPS\Theme;' . "\n" . \IPS\Theme::compileTemplate( $template['template_content_html'], "email_html_{$template['template_app']}_{$template['template_name']}", $template['template_data'] );
			$ptFunction		= 'namespace IPS\Theme;' . "\n" . \IPS\Theme::compileTemplate( $template['template_content_plaintext'], "email_plaintext_{$template['template_app']}_{$template['template_name']}", $template['template_data'] );

			$key	= $template['template_key'] . '_email_html';
			\IPS\Data\Store::i()->$key = $htmlFunction;

			$key	= $template['template_key'] . '_email_plaintext';
			\IPS\Data\Store::i()->$key = $ptFunction;
		}
	}
	
	/**
	 * Write JSON file
	 *
	 * @param	string	$file	Filepath
	 * @param	array	$data	Data to write
	 * @return	void
	 * @throws	\RuntimeException	Could not write
	 */
	public static function writeJson( $file, $data )
	{
		$json = json_encode( $data, JSON_PRETTY_PRINT );
		
		/* No idea why, but for some people blank structures have line breaks in them and for some people they don't
			which unecessarily makes version control think things have changed - so let's make it the same for everyone */
		$json = preg_replace( '/\[\s*\]/', '[]', $json );
		$json = preg_replace( '/\{\s*\}/', '{}', $json );
		
		/* Write it */
		if( \file_put_contents( $file, $json ) === FALSE )
		{
			throw new \RuntimeException;
		}
		@chmod( $file, 0777 );
	}

	/**
	 * Can the user access this application?
	 *
	 * @param	\IPS\Member|\IPS\Member\Group|NULL	$memberOrGroup		Member/group we are checking against or NULL for currently logged on user
	 * @return	bool
	 */
	public function canAccess( $memberOrGroup=NULL )
	{
		/* If it's not enabled, we can't */
		if( !$this->enabled )
		{
			return FALSE;
		}

		/* If we are in the AdminCP, and we have permission to manage applications, then we have access */
		if( \IPS\Dispatcher::hasInstance() AND \IPS\Dispatcher::i()->controllerLocation === 'admin' AND ( !$memberOrGroup AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'app_manage' ) ) )
		{
			return TRUE;
		}

		/* If all groups have access, we can */
		if( $this->disabled_groups === NULL )
		{
			return TRUE;
		}
		
		/* If all groups do not have access, we cannot */
		if( $this->disabled_groups == '*' )
		{
			return FALSE;
		}

		/* Check member */
		if ( $memberOrGroup instanceof \IPS\Member\Group )
		{
			$memberGroups = array( $memberOrGroup->g_id );
		}
		else
		{
			$member	= ( $memberOrGroup === NULL ) ? \IPS\Member::loggedIn() : $memberOrGroup;
			$memberGroups = array_merge( array( $member->member_group_id ), array_filter( explode( ',', $member->mgroup_others ) ) );
		}
		$accessGroups	= explode( ',', $this->disabled_groups );

		/* Are we in an allowed group? */
		if( \count( array_intersect( $accessGroups, $memberGroups ) ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Can manage the widgets
	 *
	 * @param	\IPS\Member|NULL	$member		Member we are checking against or NULL for currently logged on user
	 * @return 	boolean
	 */
	public function canManageWidgets( $member=NULL )
	{
		/* Check member */
		$member	= ( $member === NULL ) ? \IPS\Member::loggedIn() : $member;
		
		return $member->modPermission('can_manage_sidebar');
	}
	
	/**
	 * Save Changes
	 *
	 * @param	bool	$skipMember		Skip clearing member cache clearing
	 * @return	void
	 */
	public function save( $skipMember=FALSE )
	{
		parent::save();
		static::postToggleEnable( $skipMember );
	}

	/**
	 * Cleanup after saving
	 *
	 * @param	bool	$skipMember		Skip clearing member cache clearing
	 * @return	void
	 * @note	This is abstracted so it can be called externally, i.e. by the support tool
	 */
	public static function postToggleEnable( $skipMember=FALSE )
	{
		unset( \IPS\Data\Store::i()->applications );
		unset( \IPS\Data\Store::i()->frontNavigation );
		unset( \IPS\Data\Store::i()->acpNotifications );
		unset( \IPS\Data\Store::i()->acpNotificationIds );
		unset( \IPS\Data\Store::i()->furl_configuration );

		/* Clear out member's cached "Create Menu" contents */
		if( !$skipMember )
		{
			\IPS\Member::clearCreateMenu();
		}
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{		
		/* Get our uninstall callback script(s) if present. They are stored in an array so that we only create one object per extension, instead of one each time we loop. */
		$uninstallExtensions	= array();
		foreach( $this->extensions( 'core', 'Uninstall', TRUE ) as $extension )
		{
			$uninstallExtensions[]	= $extension;
		}

		/* Call preUninstall() so that application may perform any necessary cleanup before other data is removed (i.e. database tables) */
		foreach( $uninstallExtensions as $extension )
		{
			if( method_exists( $extension, 'preUninstall' ) )
			{
				$extension->preUninstall( $this->directory );
			}
		}

		/* Call onOtherUninstall so that other applications may perform any necessary cleanup */
		foreach( static::allExtensions( 'core', 'Uninstall', FALSE ) as $extension )
		{
			if( method_exists( $extension, 'onOtherUninstall' ) )
			{
				$extension->onOtherUninstall( $this->directory );
			}
		}

		$templatesToRecompile = array();

		/* Note any templates that will need recompiling */
		foreach ( \IPS\Db::i()->select( 'class', 'core_hooks', array( 'app=? AND type=?', $this->directory, 'S' ) ) as $class )
		{
			$templatesToRecompile[ $class ] = $class;
		}
		
		/* Delete profile steps */
		\IPS\Member\ProfileStep::deleteByApplication( $this );

		/* Delete menu items */
		\IPS\core\FrontNavigation::deleteByApplication( $this );
		
		/* Delete club node maps */
		\IPS\Member\Club::deleteByApplication( $this );
		
		/* Delete data from shared tables */
		\IPS\Content\Search\Index::i()->removeApplicationContent( $this );
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id IN(?)', 'core', 'module', \IPS\Db::i()->select( 'sys_module_id', 'core_modules', array( 'sys_module_application=?', $this->directory ) ) ) );
		\IPS\Db::i()->delete( 'core_modules', array( 'sys_module_application=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_dev', array( 'app_key=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_hooks', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_item_markers', array( 'item_app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_reputation_index', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_upgrade_history', array( 'upgrade_app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_admin_logs', array( 'appcomponent=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_sys_conf_settings', array( 'conf_app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_queue', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_follow_count_cache', array( "class LIKE CONCAT( ?, '%' )", "IPS\\\\{$this->directory}" ) );
		\IPS\Db::i()->delete( 'core_item_statistics_cache', array( "cache_class LIKE CONCAT( ?, '%' )", "IPS\\\\{$this->directory}" ) );
		\IPS\Db::i()->delete( 'core_view_updates', array( "classname LIKE CONCAT( ?, '%' )", "IPS\\\\{$this->directory}" ) );
		\IPS\Db::i()->delete( 'core_moderator_logs', array( 'appcomponent=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_member_history', array( 'log_app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_acp_notifications', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_solved_index', array( 'app=?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_notifications', array( 'notification_app=?', $this->directory ) );

		$rulesToDelete = iterator_to_array( \IPS\Db::i()->select( 'id', 'core_achievements_rules', [ "action LIKE CONCAT( ?, '_%' )", $this->directory ] ) );
		\IPS\Db::i()->delete( 'core_achievements_rules', \IPS\Db::i()->in( 'id', $rulesToDelete ) );
		\IPS\Db::i()->delete( 'core_achievements_log_milestones', \IPS\Db::i()->in( 'milestone_rule', $rulesToDelete ) );

		foreach( $this->extensions( 'core', 'AdminNotifications', FALSE ) AS $adminNotificationExtension )
		{	
			$exploded = explode( '\\', $adminNotificationExtension );
			\IPS\Db::i()->delete( 'core_acp_notifications_preferences', array( 'type=?', "{$this->directory}_{$exploded[5]}" ) );
		}

		$classes = array();
		foreach( $this->extensions( 'core', 'ContentRouter' ) AS $contentRouter )
		{
			foreach ( $contentRouter->classes as $class )
			{
				$classes[]	= $class;

				if ( isset( $class::$commentClass ) )
				{
					$classes[]	= $class::$commentClass;
				}

				if ( isset( $class::$reviewClass ) )
				{
					$classes[]	= $class::$reviewClass;
				}
			}
		}

		if( \count( $classes ) )
		{
			$queueWhere = array();
			$queueWhere[] = array( 'app=?', 'core' );
			$queueWhere[] = array( \IPS\Db::i()->in( '`key`', array( 'rebuildPosts', 'RebuildReputationIndex' ) ) );

			foreach ( \IPS\Db::i()->select( '*', 'core_queue', $queueWhere ) as $queue )
			{
				$queue['data'] = json_decode( $queue['data'], TRUE );
				if( \in_array( $queue['data']['class'], $classes ) )
				{
					\IPS\Db::i()->delete( 'core_queue', array( 'id=?', $queue['id'] ) );
				}
			}

			\IPS\Db::i()->delete( 'core_notifications', \IPS\Db::i()->in( 'item_class', $classes ) );

			/* Delete Deletion Log Records */
			\IPS\Db::i()->delete( 'core_deletion_log', \IPS\Db::i()->in( 'dellog_content_class', $classes ) );

			/* Delete Promoted Content from this app */
			\IPS\Db::i()->delete( 'core_social_promote', \IPS\Db::i()->in( 'promote_class', $classes ) );

			/* Delete ratings from this app */
			\IPS\Db::i()->delete( 'core_ratings', \IPS\Db::i()->in( 'class', $classes ) );

			/* Delete merge redirects */
			\IPS\Db::i()->delete( 'core_item_redirect', \IPS\Db::i()->in( 'redirect_class', $classes ) );

			/* Delete member map */
			\IPS\Db::i()->delete( 'core_item_member_map', \IPS\Db::i()->in( 'map_class', $classes ) );

			/* Delete RSS Imports */
			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_rss_import', \IPS\Db::i()->in( 'rss_import_class', $classes ) ), 'IPS\core\Rss\Import' ) as $import )
			{
				$import->delete();
			}

			/* Delete Soft Deletion Log data */
			$softDeleteKeys = array();
			foreach ( $classes as $class )
			{
				if ( isset( $class::$hideLogKey ) AND $class::$hideLogKey )
				{
					$softDeleteKeys[]  = $class::$hideLogKey;
				}
			}

			if ( \count( $softDeleteKeys ) )
			{
				\IPS\Db::i()->delete( 'core_soft_delete_log', \IPS\Db::i()->in( 'sdl_obj_key', $softDeleteKeys ) );
			}

			/* Delete PBR Data */
			\IPS\Db::i()->delete( 'core_post_before_registering', \IPS\Db::i()->in( 'class', $classes ) );
			
			/* Delete Anonymous Data */
			\IPS\Db::i()->delete( 'core_anonymous_posts', \IPS\Db::i()->in( 'anonymous_object_class', $classes ) );

			/* Delete Polls */
			\IPS\Db::i()->delete( 'core_voters', array( 'poll in (?)', \IPS\Db::i()->select( 'pid', 'core_polls', \IPS\Db::i()->in( 'poll_item_class', $classes ) ) ) );
			\IPS\Db::i()->delete( 'core_polls', \IPS\Db::i()->in( 'poll_item_class', $classes ) );
		}

		/* Delete attachment maps - if the attachment is unused, the regular cleanup task will remove the file later */
		$extensions = array();

		foreach( $this->extensions( 'core', 'EditorLocations', FALSE ) AS $key => $extension )
		{
			$extensions[] = $this->directory . '_' . $key;
		}

		\IPS\Db::i()->delete( 'core_attachments_map', array( \IPS\Db::i()->in( 'location_key', $extensions ) ) );

		/* Cleanup some caches */
		\IPS\Settings::i()->clearCache();
		unset( \IPS\Data\Store::i()->acpNotifications );
		unset( \IPS\Data\Store::i()->acpNotificationIds );

		/* Delete tasks and task logs */
		\IPS\Db::i()->delete( 'core_tasks_log', array( 'task IN(?)', \IPS\Db::i()->select( 'id', 'core_tasks', array( 'app=?', $this->directory ) ) ) );
		\IPS\Db::i()->delete( 'core_tasks', array( 'app=?', $this->directory ) );

		/* Delete reports */
		\IPS\Db::i()->delete( 'core_rc_reports', array( 'rid IN(?)', \IPS\Db::i()->select('id', 'core_rc_index', \IPS\Db::i()->in( 'class', $classes ) ) ) );
		\IPS\Db::i()->delete( 'core_rc_comments', array( 'rid IN(?)', \IPS\Db::i()->select('id', 'core_rc_index', \IPS\Db::i()->in( 'class', $classes ) ) ) );
		\IPS\Db::i()->delete( 'core_rc_index', \IPS\Db::i()->in('class', $classes) );

		/* Delete language strings */
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=?', $this->directory ) );

		/* Delete email templates */
		$emailTemplates	= \IPS\Db::i()->select( '*', 'core_email_templates', array( 'template_app=?', $this->directory ) );

		if( $emailTemplates->count() )
		{
			foreach( $emailTemplates as $template )
			{
				if( $template['template_content_html'] )
				{
					$k = $template['template_key'] . '_email_html';
					unset( \IPS\Data\Store::i()->$k );
				}

				if( $template['template_content_plaintext'] )
				{
					$k = $template['template_key'] . '_email_plaintext';
					unset( \IPS\Data\Store::i()->$k );
				}
			}

			\IPS\Db::i()->delete( 'core_email_templates', array( 'template_app=?', $this->directory ) );
		}

		/* Delete skin template/CSS/etc. */
		\IPS\Theme::removeTemplates( $this->directory, NULL, NULL, NULL, TRUE );
		\IPS\Theme::removeCss( $this->directory, NULL, NULL, NULL, TRUE );
		\IPS\Theme::removeResources( $this->directory, NULL, NULL, NULL, TRUE );

		/* Invalidate disk templates */
		\IPS\Theme::resetAllCacheKeys();
		
		/* Delete theme settings */
		$valueIds = iterator_to_array( \IPS\Db::i()->select( 'sc_id', 'core_theme_settings_fields', array( array( 'sc_app=?', $this->directory ) ) ) );
		
		\IPS\Db::i()->delete( 'core_theme_settings_fields', array( 'sc_app=?', $this->directory ) );
		
		if ( \count( $valueIds ) )
		{
			\IPS\Db::i()->delete( 'core_theme_settings_values', \IPS\Db::i()->in('sv_id', $valueIds ) );
		}
		
		unset( \IPS\Data\Store::i()->themes );
		
		/* Delete any stored files */
		foreach( $this->extensions( 'core', 'FileStorage', TRUE ) as $extension )
		{
			try
			{
				$extension->delete();
			}
			catch( \Exception $e ){}
		}

		/* Delete any upload settings */
		foreach( $this->uploadSettings() as $setting )
		{
			if( \IPS\Settings::i()->$setting )
			{
				try
				{
					\IPS\File::get( 'core_Theme', \IPS\Settings::i()->$setting )->delete();
				}
				catch( \Exception $e ){}
			}
		}

		$notificationTypes = array();
		foreach( $this->extensions( 'core', 'Notifications' ) as $key => $class )
		{
			if ( method_exists( $class, 'getConfiguration' ) )
			{
				$defaults = $class->getConfiguration( NULL );

				foreach( $defaults AS $k => $config )
				{
					$notificationTypes[] =  $k;
				}
			}
		}

		if( \count( $notificationTypes ) )
		{
			\IPS\Db::i()->delete( 'core_notification_defaults', "notification_key IN('" . implode( "','", $notificationTypes ) . "')");
			\IPS\Db::i()->delete( 'core_notification_preferences', "notification_key IN('" . implode( "','", $notificationTypes ) . "')");
		}

		/* Delete database tables */
		if( file_exists( $this->getApplicationPath() . "/data/schema.json" ) )
		{
			$schema	= @json_decode( file_get_contents( $this->getApplicationPath() . "/data/schema.json" ), TRUE );

			if( \is_array( $schema ) AND \count( $schema ) )
			{
				foreach( $schema as $tableName => $definition )
				{
					try
					{
						\IPS\Db::i()->dropTable( $tableName, TRUE );
					}
					catch( \IPS\Db\Exception $e )
					{
						/* Ignore "Cannot drop table because it does not exist" */
						if( $e->getCode() <> 1051 )
						{
							throw $e;
						}
					}
				}
			}
		}

		/* Revert other database changes performed by installation */
		if( file_exists( $this->getApplicationPath() . "/setup/install/queries.json" ) )
		{
			$schema	= json_decode( file_get_contents( $this->getApplicationPath() . "/setup/install/queries.json" ), TRUE );

			ksort($schema);

			foreach( $schema as $instruction )
			{
				switch ( $instruction['method'] )
				{
					case 'addColumn':
						try
						{
							\IPS\Db::i()->dropColumn( $instruction['params'][0], $instruction['params'][1]['name'] );
						}
						catch( \IPS\Db\Exception $e )
						{
							/* Ignore "Cannot drop key because it does not exist" */
							if( $e->getCode() <> 1091 )
							{
								throw $e;
							}
						}
					break;

					case 'addIndex':
						try
						{
							\IPS\Db::i()->dropIndex( $instruction['params'][0], $instruction['params'][1]['name'] );
						}
						catch( \IPS\Db\Exception $e )
						{
							/* Ignore "Cannot drop key because it does not exist" */
							if( $e->getCode() <> 1091 )
							{
								throw $e;
							}
						}
					break;
				}
			}
		}

		/* delete widgets */
		\IPS\Db::i()->delete( 'core_widgets', array( 'app = ?', $this->directory ) );
		\IPS\Db::i()->delete( 'core_widget_areas', array( 'app = ?', $this->directory ) );

		/* clean up widget areas table */
		foreach ( \IPS\Db::i()->select( '*', 'core_widget_areas' ) as $row )
		{
			$data = json_decode( $row['widgets'], true );

			foreach ( $data as $key => $widget)
			{
				if ( isset( $widget['app'] ) and $widget['app'] == $this->directory )
				{
					unset( $data[$key]) ;
				}
			}

			\IPS\Db::i()->update( 'core_widget_areas', array( 'widgets' => json_encode( $data ) ), array( 'id=?', $row['id'] ) );
		}
		
		/* Clean up widget trash table */
		$trash = array();
		foreach( \IPS\Db::i()->select( '*', 'core_widget_trash' ) AS $garbage )
		{
			$data = json_decode( $garbage['data'], TRUE );
			
			if ( isset( $data['app'] ) AND $data['app'] == $this->directory )
			{
				$trash[] = $garbage['id'];
			}
		}
		
		\IPS\Db::i()->delete( 'core_widget_trash', \IPS\Db::i()->in( 'id', $trash ) );

		/* Call postUninstall() so that application may perform any necessary cleanup after other data is removed */
		foreach( $uninstallExtensions as $extension )
		{
			if( method_exists( $extension, 'postUninstall' ) )
			{
				$extension->postUninstall( $this->directory );
			}
		}
		
		/* Clean up FURL Definitions */
		if ( file_exists( $this->getApplicationPath() . "/data/furl.json" ) )
		{
			$current = json_decode( \IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( "conf_key=?", 'furl_configuration' ) )->first(), true );
			$default = json_decode( preg_replace( '/\/\*.+?\*\//s', '', @file_get_contents( $this->getApplicationPath() . "/data/furl.json" ) ), true );
						
			if ( isset( $default['pages'] ) and $current !== NULL )
			{
				foreach( $default['pages'] AS $key => $def )
				{
					if ( isset( $current[$key] ) )
					{
						unset( $current[$key] );
					}
				}
								
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => json_encode( $current ) ), array( "conf_key=?", 'furl_configuration' ) );
			}
		}
		
		/* Delete from DB */
		\IPS\File::unclaimAttachments( 'core_Admin', $this->id, NULL, 'appdisabled' );
		parent::delete();

		/* Rebuild hooks file */
		\IPS\Plugin\Hook::writeDataFile();
		foreach ( $templatesToRecompile as $k )
		{
			$exploded = explode( '_', $k );
			\IPS\Theme::deleteCompiledTemplate( $exploded[1], $exploded[2], $exploded[3] );
		}

		/* Clear out member's cached "Create Menu" contents */
		\IPS\Member::clearCreateMenu();
		
		/* Clear out data store for updated values */
		unset( \IPS\Data\Store::i()->modules );
		unset( \IPS\Data\Store::i()->applications );
		unset( \IPS\Data\Store::i()->widgets );
		unset( \IPS\Data\Store::i()->furl_configuration );
		
		\IPS\Settings::i()->clearCache();

		/* Remove the files and folders, if possible (if not IN_DEV and not in DEMO_MODE and not on platform) */
		if ( !\IPS\CIC2 AND !\IPS\IN_DEV AND !\IPS\DEMO_MODE AND file_exists( \IPS\ROOT_PATH . '/applications/' . $this->directory ) )
		{
			try
			{
				$iterator = new \RecursiveDirectoryIterator( \IPS\ROOT_PATH . '/applications/' . $this->directory, \FilesystemIterator::SKIP_DOTS );
				foreach ( new \RecursiveIteratorIterator( $iterator, \RecursiveIteratorIterator::CHILD_FIRST ) as $file )
				{  
					if ( $file->isDir() )
					{  
						@rmdir( $file->getPathname() );  
					}
					else
					{  
						@unlink( $file->getPathname() );  
					}  
				}
				$dir = \IPS\ROOT_PATH . '/applications/' . $this->directory;
				$handle = opendir( $dir );
				closedir ( $handle );
				@rmdir( $dir );
			}
			catch( \UnexpectedValueException $e ){}
		}
	}

	/**
	 * Return an array of version upgrade folders this application contains
	 *
	 * @param	int		$start	If provided, only upgrade steps above this version will be returned
	 * @return	array
	 */
	public function getUpgradeSteps( $start=0 )
	{
		$path	= $this->getApplicationPath() . "/setup";

		if( !is_dir( $path ) )
		{
			return array();
		}

		$versions	= array();

		foreach( new \DirectoryIterator( $path ) as $file )
		{
			if( $file->isDir() AND !$file->isDot() )
			{
				if( mb_substr( $file->getFilename(), 0, 4 ) == 'upg_' )
				{
					$_version	= \intval( mb_substr( $file->getFilename(), 4 ) );

					if( $_version > $start )
					{
						$versions[]	= $_version;
					}
				}
			}
		}

		/* Sort the versions lowest to highest */
		sort( $versions, SORT_NUMERIC );

		return $versions;
	}
	
	/**
	 * Can view page even when user is a guest when guests cannot access the site
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowGuestAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return FALSE;
	}
	
	/**
	 * Can view page even when site is offline
	 *
	 * @param	\IPS\Application\Module	$module			The module
	 * @param	string					$controller		The controller
	 * @param	string|NULL				$do				To "do" parameter
	 * @return	bool
	 */
	public function allowOfflineAccess( \IPS\Application\Module $module, $controller, $do )
	{
		return FALSE;
	}

	/**
	 * [Node] Does the currently logged in user have permission to edit this node?
	 *
	 * @return	bool
	 */
	public function canEdit()
	{
		return ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'app_manage' ) );
	}
	
	/**
	 * Get any third parties this app uses for the privacy policy
	 *
	 * @return array( title => language bit, description => language bit, privacyUrl => privacy policy URL )
	 */
	public function privacyPolicyThirdParties()
	{
		/* Apps can overload this */
		return array();
	}
	
	/**
	 * Get any settings that are uploads
	 *
	 * @return	array
	 */
	public function uploadSettings()
	{
		/* Apps can overload this */
		return array();
	}

	/**
	 * Search
	 *
	 * @param	string		$column	Column to search
	 * @param	string		$query	Search query
	 * @param	string|null	$order	Column to order by
	 * @param	mixed		$where	Where clause
	 * @return	array
	 */
	public static function search( $column, $query, $order=NULL, $where=array() )
	{
		if ( $column === '_title' )
		{
			$return = array();
			foreach( \IPS\Member::loggedIn()->language()->words as $k => $v )
			{
				if ( preg_match( '/^__app_([a-z]*)$/', $k, $matches ) and mb_strpos( mb_strtolower( $v ), mb_strtolower( $query ) ) !== FALSE )
				{
					try
					{
						$application = static::load( $matches[1] );
						$return[ $application->_id ] = $application;
					}
					catch ( \OutOfRangeException $e ) { }
				}
			}
			return $return;
		}
		return parent::search( $column, $query, $order, $where );
	}

	/**
	 * remove the furl prefix from all metadata rows
	 *
	 * @param Application $application
	 */
	public static function removeMetaPrefix( \IPS\Application $application )
	{
		$metaWhere = array();
		$prefix = '';
		$oldDefaultAppDefinition = ( file_exists( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) ) ? json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) ), TRUE ) : array();
		if ( isset( $oldDefaultAppDefinition['topLevel'] ) and $oldDefaultAppDefinition['topLevel'] )
		{
			$prefix = $oldDefaultAppDefinition['topLevel']  .'/';
			$metaWhere[] = \IPS\Db::i()->like( 'meta_url', $oldDefaultAppDefinition['topLevel'] . '/' );

			/* Replace the root */
			\IPS\Db::i()->update( 'core_seo_meta', array( 'meta_url' =>  '' ), array( 'meta_url=?', $oldDefaultAppDefinition['topLevel'] ) );
		}

		$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'core_seo_meta', $metaWhere )->setKeyField('meta_id') );
		
		foreach( $rows as $id => $row )
		{
			/* The old urls need now the new prefix */
			$newUrl = str_replace( $prefix, '', $row['meta_url'] );
			\IPS\Db::i()->update( 'core_seo_meta', array( 'meta_url' =>  $newUrl ), array( 'meta_id=?', $id ) );
		}
	}

	/**
	 * Add the new prefix to the metadata rows
	 *
	 * @param Application $application
	 */
	public static function addMetaPrefix( \IPS\Application $application )
	{
		$metaWhere = array();
		$oldDefaultAppDefinition = ( file_exists( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) ) ? json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) ), TRUE ) : array();
		if ( isset( $oldDefaultAppDefinition['topLevel'] ) and $oldDefaultAppDefinition['topLevel'] )
		{
			$prefix = $oldDefaultAppDefinition['topLevel']  .'/';
		}
		else
		{
			$prefix = "";
		}

		$existingTopLevels = [];
		foreach ( \IPS\Application::applications() as $app )
		{
			/* If it has a furl.json file... */
			if (  $application->directory != $app->directory AND file_exists( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) )
			{
				/* Open it up */
				$data = json_decode( preg_replace( '/\/\*.+?\*\//s', '', \file_get_contents( static::getRootPath( $application->directory ) . "/applications/{$application->directory}/data/furl.json" ) ), TRUE );
				$existingTopLevels[] = $data['topLevel'];
			}
		}

		$rows = iterator_to_array( \IPS\Db::i()->select( '*', 'core_seo_meta', $metaWhere )->setKeyField('meta_id') );

		foreach( $rows as $id => $row )
		{
			/* The old urls need now the new prefix */
			$newUrl = $prefix . $row['meta_url'];
			\IPS\Db::i()->update( 'core_seo_meta', array( 'meta_url' =>  $newUrl ), array( 'meta_id=?', $id ) );
		}
	}
	
	/**
	 * Get Application Path
	 *
	 * @return	string
	 */
	public function getApplicationPath(): string
	{
		return static::getRootPath( $this->directory ) . '/applications/' . $this->directory;
	}
	
	/**
	 * Get Root Path
	 *
	 * @param	string	$appKey		Application to check if it's an IPS app or third party, or NULL to not check.
	 * @return	string
	 */
	public static function getRootPath( ?string $appKey = NULL ): string
	{
		if ( $appKey AND \in_array( $appKey, \IPS\IPS::$ipsApps ) )
		{
			return \IPS\ROOT_PATH;
		}
		else
		{
			return \IPS\SITE_FILES_PATH;
		}
	}

	/**
	 * Returns a list of all existing webhooks and their payload in this app.
	 *
	 * @return array
	 */
	public function getWebhooks() : array
	{
		// Fetch all the content classes
		$classes = [];
		$hooks = [];

		foreach ( $this->extensions( 'core', 'ContentRouter' ) as $router )
		{
			foreach ( $router->classes as $class )
			{
				$classes[] = $class;

				if ( isset( $class::$commentClass ) )
				{
					$commentClass = $class::$commentClass;
					$classes[] = $commentClass;

				}

				if ( isset( $class::$reviewClass ) )
				{
					$reviewClass = $class::$reviewClass;
					$classes[] = $reviewClass;
				}
			}

		}

		foreach( $classes as $class )
		{
			$key = str_replace( '\\', '', \substr( $class, 3 ) );
			$hooks[$key .'_create'] = $class;
			$hooks[$key .'_delete'] = $class;
			\IPS\Member::loggedIn()->language()->words[ 'webhook_' . $key .'_create' ]     = \IPS\Member::loggedIn()->language()->addToStack('webhook_contentitem_created', FALSE, ['sprintf' => [ $class::_indefiniteArticle() ]]);
			\IPS\Member::loggedIn()->language()->words[ 'webhook_' . $key .'_delete' ]     = \IPS\Member::loggedIn()->language()->addToStack('webhook_contentitem_deleted', FALSE, ['sprintf' => [ $class::_indefiniteArticle() ]]);
		}
		return $hooks;
	}
	
	/**
	 * Do Member Check
	 *
	 * @return	\IPS\Http\Url|NULL
	 */
	public function doMemberCheck(): ?\IPS\Http\Url
	{
		return NULL;
	}

	/**
	 * Returns a list of all essential cookies which are set by all the installed apps
	 * To return a list of own cookies, use the @see \IPS\Application::_getEssentialCookieNames() method.
	 * 
	 * @return string[]
	 */
	public final static function getEssentialCookieNames(): array
	{
		if ( !isset( \IPS\Data\Store::i()->essentialCookieNames ) )
		{
			$names = [];
			foreach( static::applications() as $app )
			{
				$names = array_merge( $names, $app->_getEssentialCookieNames() );
			}
			\IPS\Data\Store::i()->essentialCookieNames = $names;
		}
		return \IPS\Data\Store::i()->essentialCookieNames;
	}

	/**
	 * Returns a list of essential cookies which are set by this app.
	 * Wildcards (*) can be used at the end of cookie names for PHP set cookies.
	 *
	 * @return string[]
	 */
	public function _getEssentialCookieNames(): array
	{
		return [];
	}
}

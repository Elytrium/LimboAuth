<?php
/**
 * @brief		Scanner Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a> (Matt F)
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		Aug 2022
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */

use Jose\Component\Console\P12CertificateLoaderCommand;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Class for scanning apps and plugins to make sure they won't break everything (specifically on PHP8).
 */
class _Scanner extends \stdClass
{
	/**
	 * @brief static cache for getMethods
	 */
	private static $methodInfoCache = array();

	/**
	 * @brief static cache for getClassDetails
	 */
	private static $classDetailsCache = array();

	/**
	 * @brief   The default $options parameter for the scan methods
	 */
	private static $defaultScannerOptions = array(
		"enabledOnly" => true,
		"dirsToCheck" => null
	);

	/**
	 * Scan all the methods in hooks installed on a site to make sure they're declared in a php8 compatible way
	 *
	 * @param   bool        $getDetails     Return the details of every hook and base class loaded? This will make the result SIGNIFICANTLY larger so leave false unless you need it.
	 * @param   bool        $shallowCheck   Return a bool instead that indicates whether discrepancies were found. Potentially faster since it returns at the first discrepancy
	 * @param   int         $limit          The upper limit of issues to return. Set to -1 for no limit; Note this makes no difference for shallowChecks
	 * @param   int         $hardLimit      A hard limit for the parent class search and filesystem search... those are self-propagating and can go for quite a while. Pass -1 at your own risk to make them boundless
	 * @param   array       $options        Options;
	 * @example
	 * array(
	 *      "enabledOnly" => true, // Whether to only check enabled apps & plugins only. default is true
	 *      "dirsToCheck" => [ "apps" => array(), "plugins" => array() ], // Filter for what apps & plugins to check. Empty/null checks all apps
	 * )
	 *
	 *
	 * @return  bool|array[]     By default, array of [$issues, $details], where both $issues and $details are arrays of issues and method details found respectively
	 */
	final public static function scanExtendedClasses( bool $getDetails=false, bool$shallowCheck=false, int $limit=500, int $hardLimit=5000, ?array $options=NULL )
	{
		$details = array();
		$issues = array();
		if ( empty( $options ) OR !\is_array( $options ) )
		{
			$options = static::$defaultScannerOptions;
		}

		$appDirsToCheck = isset( $options['dirsToCheck']['apps'] ) ? $options['dirsToCheck']['apps'] : ( isset( $options['dirsToCheck']['plugins'] ) ? array() : null );
		$pluginDirsToCheck = isset( $options['dirsToCheck']['plugins'] ) ? $options['dirsToCheck']['plugins'] : ( isset( $options['dirsToCheck']['apps'] ) ? array() : null );
		$enabledOnly = $options['enabledOnly'] ?? static::$defaultScannerOptions['enabledOnly'];


		/* Iterate through all app & plugin directories */
		$excludeDirs = [ 'hooks', 'setup', 'dev', 'sources\/vendor' ];
		$classesToLoad = [];
		$nodes = array();
		$appAndPluginClasses = array();
		foreach ( ( $enabledOnly ? \IPS\Application::enabledApplications() : \IPS\Application::applications() ) as $key => $app )
		{
			/* Skip it if we don't care */
			if (
				( !\is_array( $appDirsToCheck ) AND \in_array( $app->directory, \IPS\IPS::$ipsApps ) ) OR
				( \is_array( $appDirsToCheck ) AND !\in_array( $app->directory, $appDirsToCheck ) )
			)
			{
				continue;
			}

			/* Create a tree like array of class inheritance; each node is indexed by its name and contains. Note this is not a node object tree but a relational tree
			array(
				"root" => "\IPS\<ipsApp|SystemClass>\...",
				"parent" => "\IPS\<customApp|plugins>\...",
				"method_details" => array(...)
			)
			 */

			/* For each customization, get all the dirs recursively; find any php file that could break things */
			$dir = ( ( \defined( 'IPS\CIC2' ) and \IPS\CIC2 ) ? \IPS\SITE_FILES_PATH : \IPS\ROOT_PATH ) . '/applications/' . $app->directory;
			if ( !\is_dir( $dir ) )
			{
				static::log( "During the customization compatibility check, the app {$app->directory} did not have a valid directory and was skipped" );
				continue;
			}

			if ( file_exists( $dir . '/Application.php' ) )
			{
				$contents = file_get_contents( $dir . '/Application.php' );
				$methods = static::getMethods( $contents );
				$classDetails = static::getClassDetails( $contents );
				if ( $classDetails['globalClassName'] )
				{
					$classDetails['isApp'] = true;
					$classDetails['appOrPlugin'] = $key;
					$classDetails['filepath'] = $dir . '/Application.php';
					$classDetails['isIps'] = false;
					$classDetails['priority'] = $enabledOnly ? true : $app->enabled;
					$nodes[$classDetails['globalClassName']] = $classDetails;
					if ( $getDetails )
					{
						$details[$dir . '/Application.php'] = $classDetails['methods'];
					}

					if ( $classDetails['parentClass'] )
					{
						$appAndPluginClasses[$classDetails['globalClassName']] = 1;
						$classesToLoad[$classDetails['parentClass']] = 1;
					}
				}
			}

			$directory = new \RecursiveDirectoryIterator($dir);
			$iterator = new \RecursiveIteratorIterator($directory);
			$skips =  implode( '|', $excludeDirs );
			$skipPattern = '/' . preg_quote( $dir, '/' ) . '\\/(?:' . $skips . ')/';

			$i = 0;
			foreach ( $iterator as $info )
			{
				if ( !( $info instanceof \SplFileInfo and $info->isFile() ) )
				{
					continue;
				}
				$pathname = $info->getPathname();

				/* We did this already */
				if ( $pathname === ($dir . '/Application.php') )
				{
					continue;
				}

				/* Don't care about non-php files */
				if ( \mb_strtolower( $info->getExtension() ) !== 'php' )
				{
					continue;
				}


				if ( $hardLimit !== -1 and $i++ >= $hardLimit )
				{
					static::log( "Some PHP classes may not have been scanned for the app {$app->directory} since the filesystem search's hard limit of {$hardLimit} was reached." );
					break;
				}

				/* Don't care about these directories */
				if ( preg_match( $skipPattern, $pathname ) )
				{
					continue;
				}

				$contents = \file_get_contents( $pathname );
				$classDetails = static::getClassDetails( $contents );
				if ( $classDetails['globalClassName'] )
				{
					$classDetails['isApp'] = true;
					$classDetails['appOrPlugin'] = $key;
					$classDetails['filepath'] = $pathname;
					$classDetails['isIps'] = false;
					$classDetails['priority'] = $enabledOnly ? true : $app->enabled;
					$nodes[$classDetails['globalClassName']] = $classDetails;

					if ( $getDetails )
					{
						$details[$pathname] = $classDetails['methods'];
					}

					if ( $classDetails['parentClass'] )
					{
						$appAndPluginClasses[$classDetails['globalClassName']] = 1;
						$classesToLoad[$classDetails['parentClass']] = 1;
					}
				}
			}
		}

		/* Now for the plugins */
		foreach ( ( $enabledOnly ? \IPS\Plugin::enabledPlugins() : \IPS\Plugin::plugins() ) as $key => $plugin )
		{
			if ( \is_array( $pluginDirsToCheck ) AND !\in_array( $plugin->location, $pluginDirsToCheck ) )
			{
				continue;
			}

			$dir = ( ( \defined('\IPS\IN_DEV' ) AND \IPS\IN_DEV ) ? \IPS\ROOT_PATH : \IPS\SITE_FILES_PATH ) . '/plugins/' . $plugin->location;

			if ( !( @is_dir( $dir ) ) )
			{
				$message = "Couldn't load the files for the plugin '{$plugin->location}'. '{$dir}' is not an accessible directory";
				static::log( $message );
				continue;
			}

			$dirs = [ $dir . '/tasks', $dir . '/widgets' ];

			$pluginLimit = 200; /* We'll stop at 200 php files... more than that is suspicious */
			$i = 0;
			foreach ( $dirs as $dirToCheck )
			{
				if ( !( \file_exists( $dirToCheck ) AND \is_dir( $dirToCheck ) ) )
				{
					continue;
				}

				$directory = new \RecursiveDirectoryIterator( $dirToCheck );
				foreach ( new \RecursiveIteratorIterator( $directory ) as $info )
				{
					if ( !( $info instanceof \SplFileInfo AND $info->isFile() ) )
					{
						continue;
					}

					$pathname = $info->getPathname();
					if ( \strtolower( $info->getExtension() ) !== 'php' )
					{
						continue;
					}

					if ( $i >= $pluginLimit or ( $hardLimit !== -1 and $i >= $hardLimit ) )
					{
						$actualLimit = $hardLimit === -1 ? $pluginLimit : min( $pluginLimit, $hardLimit );
						static::log( "Some PHP Files may not have been checked for the plugin {$plugin->location}. It contains at least {$actualLimit} PHP files in the widgets and tasks subdirectories." );
						break;
					}
					$i++;

					$contents = \file_get_contents( $pathname );
					$classDetails = static::getClassDetails( $contents );
					if ( $classDetails['globalClassName'] )
					{
						$classDetails['isApp'] = false;
						$classDetails['appOrPlugin'] = $key;
						$classDetails['filepath'] = $pathname;
						$classDetails['isIps'] = false;
						$classDetails['priority'] = $enabledOnly ? true : ( $plugin->enabled );
						$nodes[$classDetails['globalClassName']] = $classDetails;

						if ( $getDetails )
						{
							$details[$pathname] = $classDetails['methods'];
						}

						if ( $classDetails['parentClass'] )
						{
							$appAndPluginClasses[$classDetails['globalClassName']] = 1;
							$classesToLoad[$classDetails['parentClass']] = 1;
						}
					}
				}

				if ( $limit <= 0 )
				{
					break;
				}
			}
		}

		if ( !empty( $appAndPluginClasses ) )
		{
			/* Now load until we've traced back to oob IPS classes (ips apps and system) */
			$i = 0;
			$classesToLoad = array_keys( $classesToLoad );
			while ( $classToLoad = array_shift( $classesToLoad ) )
			{
				if ( isset( $nodes[$classToLoad] ) )
				{
					continue;
				}

				/* Determine the path */
				$filename = '';
				$appOrPlugin = null;
				$isApp = false;
				$isIps = false;
				$bits = explode( '\\', ltrim( $classToLoad, '\\' ) );

				/* If this doesn't belong to us, try a PSR-0 loader or ignore it */
				$vendorName = array_shift( $bits );
				if( $vendorName !== 'IPS' )
				{
					continue;
				}

				$baseClassName = array_pop( $bits );

				/* Locate file */
				$sourcesDirSet = FALSE;
				foreach ( array_merge( $bits, array( $baseClassName ) ) as $i => $bit )
				{
					if( preg_match( "/^[a-z0-9]/", $bit ) )
					{
						if( $i === 0 )
						{
							if ( \in_array( $bit, \IPS\IPS::$ipsApps ) )
							{
								$isIps = true;
							}

							if ( \IPS\CIC2 AND !\in_array( $bit, \IPS\IPS::$ipsApps ) )
							{
								$filename .= \IPS\SITE_FILES_PATH . '/applications/'; // Applications are in the root on Cloud2
							}
							else
							{
								$filename .= \IPS\ROOT_PATH . '/applications/';
							}
						}
						else
						{
							$sourcesDirSet = TRUE;
						}
					}
					elseif ( $i === 3 and $bit === 'Upgrade' )
					{
						$bit = mb_strtolower( $bit );
					}
					elseif( $sourcesDirSet === FALSE )
					{
						if( $i === 0 )
						{
							$isIps = true;
							$filename .= \IPS\ROOT_PATH . '/system/';
						}
						elseif ( $i === 1 and $bit === 'Application' )
						{
							// do nothing
						}
						else
						{
							$filename .= 'sources/';
						}
						$sourcesDirSet = TRUE;
					}

					$filename .= "{$bit}/";
				}

				/* Load it */
				$filename = \substr( $filename, 0, -1 ) . '.php';

				if ( !\file_exists( $filename ) )
				{
					$filename = \substr( $filename, 0, -4 ) . \substr( $filename, \strrpos( $filename, '/' ) );
					if ( !file_exists( $filename ) )
					{
						static::log( "A class in an app extends the class {$classToLoad} for which no file {$filename} exists! The method scanner skipped comparing that file to any of its subclasses" );
						continue;
					}
				}

				$contents = \file_get_contents( $filename );
				$classDetails = static::getClassDetails( $contents );
				if ( !$classDetails['globalClassName'] )
				{
					static::log( "A class in an app extends the class {$classToLoad} for which no valid class file can be found (checked {$filename}). The method scanner skipped comparing that file to any of its subclasses" );
					continue;
				}

				$classDetails['isApp'] = $isApp;
				$classDetails['appOrPlugin'] = $appOrPlugin;
				$classDetails['filepath'] = $filename;
				$classDetails['isIps'] = $isIps;
				$classDetails['priority'] = false;
				$nodes[$classDetails['globalClassName']] = $classDetails;


				/* Add the parent to the classes to load if we still haven't reached our own IPS code; we're not going to trudge through IPS code */
				if ( !$isIps AND $classDetails['parentClass'] )
				{
					$classesToLoad[] = $classDetails['parentClass'];
				}

				if ( $hardLimit !== -1 AND $i++ >= $hardLimit )
				{
					static::log( "The Extended Class Compatibility scanner ran but may have been inaccurate. Not all parent classes could be loaded since the hard limit of {$hardLimit} was reached." );
					break;
				}
			}

			/* Now we have the inheritance tree, compare extended classes from the app classes */
			foreach ( $appAndPluginClasses as $appOrPluginClass => $var )
			{
				$classDetails = $nodes[$appOrPluginClass];
				$appOrPlugin = '';
				if ( @$classDetails['appOrPlugin'] )
				{
					$appOrPlugin = ( @$classDetails['isApp'] ) ? 'App - ' : 'Plugin - ';
					$appOrPlugin .= $classDetails['appOrPlugin'];
				}
				else
				{
					static::log( "Something went wrong when evaluating the class {$classDetails['globalClassName']}." );
					continue;
				}
				if ( $parentClass = @$classDetails['parentClass'] )
				{
					if ( $parentClassDetails = @$nodes[$parentClass] )
					{
						$priority = $classDetails['priority'] ?? 1;
						$methodIssues = static::compareMethodsForIssues(
							$parentClassDetails['methods'],
							$classDetails['methods'],
							$parentClassDetails['filepath'],
							$classDetails['filepath'],
							$parentClassDetails['globalClassName'],
							$classDetails['globalClassName'],
							$shallowCheck,
							$limit,
							$priority
						);

						if ( !empty( $methodIssues ) )
						{

							if ( $shallowCheck )
							{
								if ( $priority or !( $options['shallowCheckPriorityOnly'] ?? 1 ) )
								{
									return true;
								}
							}

							if ( $limit !== -1 and \is_countable( $methodIssues ) )
							{
								$limit -= \count( $methodIssues );
							}

							$issues[ $appOrPlugin ] = $issues[$appOrPlugin] ?? [];
							$issues[ $appOrPlugin ][ $classDetails['filepath'] ] = is_array( $methodIssues ) ? array_merge( $issues[ $appOrPlugin ][ $classDetails['filepath'] ] ?? [], $methodIssues ): $issues[ $appOrPlugin ][ $classDetails['filepath'] ];
						}
					}
					else
					{
						static::log( "The class {$appOrPluginClass} extends {$parentClass}, but the parent class couldn't be loaded." );
					}
				}
				else
				{
					static::log( "The class {$appOrPluginClass} was queued to be compared to its parent class, but no parent class was found" );
				}
			}
		}

		/* return issues */
		if ( $shallowCheck )
		{
			return empty( $issues ) ? null : false; // if we got an issue but haven't returned with shallow check, none of the issues are priority
		}

		return $getDetails ? [ $issues, $details ] : [ $issues ];
	}


	/**
	 * Compare 2 sets of methods from a subclass and its parent, and returns all the issues it could find with how the methods are declared
	 *
	 * @see PHP8 uses the Liskov Substitution Principle (LSP) to determine method compatibility https://www.php.net/manual/en/language.oop5.basic.php#language.oop.lsp
	 *
	 * @param   array[]         $baseMethods        The Methods from the parent/base class
	 * @param   array[]         $subclassMethods    The Methods from the child class
	 * @param   string          $baseFile           The path to the base/parent file; will trim out \IPS\ROOT_PATH and SITE_FILES_PATH if it starts with it
	 * @param   string          $subclassFile       The path to the subclass file; will trim out \IPS\ROOT_PATH and SITE_FILES_PATH if it starts with it
	 * @param   string          $baseClassName      The name of the base class being evaluated; necessary to render all the context details about the issue
	 * @param   string          $subclassName       The name of the extending subclass being evaluated; necessary to render all the context details about the issue
	 * @param   bool            $shallowCheck       Perform a lightweight shallow check? If this is true, the method returns a bool, and will return TRUE as soon as it comes across the first issue
	 * @param   int             $limit              The limit of the issues; set -1 to run without a limit. This is important when scanning all files across entire scaled communities
	 * @param   bool            $isHook             Is the subclass a hook? If so, will set the 'class' property of the output to the base class instead of the subclass
	 * @param   bool            $isPriority         Are these issues going to be considered priority? If so that's added here to prevent memory issues adding manually later
	 *
	 * @return bool|array
	 */
	final public static function compareMethodsForIssues( $baseMethods, $subclassMethods, $baseFile, $subclassFile, $baseClassName, $subclassName, $shallowCheck=false, $limit=500, $isHook=false, $isPriority=false )
	{
		/* For the file paths, remove the root or site files path for security and readability; anyone who can do something about these files knows what those are */
		$searches = [\IPS\ROOT_PATH];
		if ( \defined( '\IPS\SITE_FILES_PATH' ) )
		{
			$searches[] = \IPS\SITE_FILES_PATH;
		}
		foreach ( ['baseFile', 'subclassFile'] as $arg )
		{
			foreach ( $searches as $const )
			{
				if ( \mb_substr( $$arg, 0, \mb_strlen( $const ) ) === $const )
				{
					$$arg = \mb_substr( $$arg, \mb_strlen( $const ) );
				}
			}
		}

		/* This is what we're looking for */
		$issues = array();
		$methodFieldMap = array(
			'security'      => 'method_issue_security',
			'static'        => 'method_issue_static',
			'returnType'    => 'method_issue_return_type'
		);

		$parameterFieldMap = array(
			"type"                  => "method_issue_parameter_type",
			"nullable"              => "method_issue_nullable",
			"passedByReference"     => 'method_issue_reference',
			"packed"                => "method_issue_packed"
		);

		/* Only compare overlapping methods */
		$overloaded = array_intersect( array_keys( $baseMethods ), array_keys( $subclassMethods ) );
		$count = 0;
		foreach ( $overloaded as $method )
		{
			$baseMethod = $baseMethods[$method];
			$subclassMethod = $subclassMethods[$method];

			/* According to the Liskov Substitution Principle, constructors and private methods are exempt */
			if ( $method === '__construct' OR ( $baseMethod['security'] === 'private' and $subclassMethod['security'] === 'private' ) )
			{
				continue;
			}

			foreach ( $methodFieldMap as $field => $reason )
			{
				/* A protected base class method can be made public in a subclass */
				if ( $field === 'security' and ( $baseMethod['security'] === 'protected' and $subclassMethod['security'] === 'public' ) )
				{
					continue;
				}

				/* A subclass can add a return type hint if the base class does not have one */
				if ( $field === 'returnType' and ( ! $baseMethod[$field] and $subclassMethod[$field] ) )
				{
					continue;
				}

				if ( $baseMethod[$field] !== $subclassMethod[$field] )
				{
					if ( $shallowCheck )
					{
						return true;
					}

					$issues[] = array(
						"method"            => $method,
						"reason"            => $reason,
						"parameter"         => null,
						"subclassFile"      => $subclassFile,
						"baseFile"          => $baseFile,
						"baseClass"         => $baseClassName,
						"subclassMethod"    => $subclassMethod,
						"subclassName"      => $subclassName,
						"baseMethod"        => $baseMethod,
						"class"             => $isHook ? $baseClassName : $subclassName,
						'priority'          => $isPriority
					);

					if ( $limit !== -1 AND ++$count >= $limit )
					{
						break;
					}
				}
			}

			if ( $baseMethod['final'] )
			{
				if ( $shallowCheck )
				{
					return true;
				}

				$issues[] = array(
					"method"            => $method,
					"reason"            => "method_issue_final",
					"parameter"         => null,
					"subclassFile"      => $subclassFile,
					"baseFile"          => $baseFile,
					"baseClass"         => $baseClassName,
					"subclassMethod"    => $subclassMethod,
					"subclassName"      => $subclassName,
					"baseMethod"        => $baseMethod,
					"class"             => $isHook ? $baseClassName : $subclassName,
					'priority'          => $isPriority
				);

				if ( $limit !== -1 AND ++$count >= $limit )
				{
					break;
				}
			}

			/* What are our parameters */
			if ( \count( $baseMethod['parameters'] ) or \count( $subclassMethod['parameters'] ) )
			{
				/* The subclass param names can be different from the base class, but must be the same type, nullable, etc otherwise */
				$pass = true;
				$baseParamsByIndex = array_values( $baseMethod['parameters'] );
				$subclassParamsByIndex = array_values( $subclassMethod['parameters'] );

				/* The extended class has fewer params than the base class */
				if ( \count( $baseParamsByIndex ) > 0 and ( \count( $baseParamsByIndex ) > \count( $subclassParamsByIndex ) ) )
				{
					$pass = false;
				}
				else if ( \count( $baseParamsByIndex ) > 0 )
				{
					foreach ( range( 0, \count( $baseParamsByIndex ) - 1 ) as $i )
					{
						foreach( $baseParamsByIndex[$i] as $key => $value )
						{
							if ( $key !== 'name' )
							{
								if ( $baseParamsByIndex[$i][ $key ] != $subclassParamsByIndex[$i][ $key ] )
								{
									$pass = false;
									break;
								}
							}
						}
					}
				}

				if ( $pass === false )
				{
					if ( $shallowCheck )
					{
						return true;
					}

					$issues[] = array(
						"method"            => $method,
						"reason"            => "method_issue_parameters",
						"parameter"         => null,
						"subclassFile"      => $subclassFile,
						"baseFile"          => $baseFile,
						"baseClass"         => $baseClassName,
						"subclassMethod"    => $subclassMethod,
						"subclassName"      => $subclassName,
						"baseMethod"        => $baseMethod,
						"class"             => $isHook ? $baseClassName : $subclassName,
						'priority'          => $isPriority
					);

					if ( $limit !== -1 AND ++$count >= $limit )
					{
						break;
					}
				}

				if ( $limit !== -1 AND $count >= $limit )
				{
					break;
				}

				foreach ( array_intersect( array_keys( $baseMethod['parameters'] ), array_keys( $subclassMethod['parameters'] ) ) as $param )
				{
					foreach ( $parameterFieldMap as $field => $reason )
					{
						if ( $baseMethod['parameters'][$param][$field] !== $subclassMethod['parameters'][$param][$field] )
						{
							if ( $shallowCheck )
							{
								return true;
							}

							$issues[] = array(
								"method"            => $method,
								"reason"            => $reason,
								"parameter"         => $param,
								"subclassFile"      => $subclassFile,
								"baseFile"          => $baseFile,
								"baseClass"         => $baseClassName,
								"subclassMethod"    => $subclassMethod,
								"subclassName"      => $subclassName,
								"baseMethod"        => $baseMethod,
								"class"             => $isHook ? $baseClassName : $subclassName,
								'priority'          => $isPriority
							);

							if ( $limit !== -1 AND ++$count >= $limit )
							{
								break;
							}
						}
					}

					if ( $limit !== -1 AND $count >= $limit )
					{
						break;
					}
				}
			}
		}

		if ( $shallowCheck )
		{
			return !empty( $issues );
		}

		return $issues;
	}

	/**
	 * @brief   Enable logging for this scan
	 */
	protected static bool $logging = TRUE;

	/**
	 * Scan a class and subclass installed on a site to make sure any methods are overloaded in a php8 compatible way
	 *
	 * @param   bool        $getDetails     Return the details of every class loaded? This will make the result SIGNIFICANTLY larger memory-wise so leave false unless you need it.
	 * @param   bool        $shallowCheck   Return a bool instead that indicates whether discrepancies were found. Fastest since it returns at the first discrepancy and lightweight since it doesn't store all that data in memory
	 * @param   int         $limit          The upper limit of issues to return. Set to -1 for no limit; Note this makes no difference for shallowChecks
	 * @param   array       $options        Options;
	 * @example
	 * array(
	 *      "enabledOnly" => true, // Whether to only check enabled apps & plugins only. default is true
	 *      "dirsToCheck" => [ "apps" => array(), "plugins" => array() ], // Filter for what apps & plugins to check. Empty/null checks all apps
	 *      "logging"     => true // Whether to disable logging for this scan, default is true
	 * )
	 *
	 * @return  bool|null|array[]     By default, array of [$issues, $details], where both $issues and $details are arrays of issues and method details found respectively
	 */
	final public static function scanCustomizationIssues( $getDetails=false, $shallowCheck=false, $limit=500, $options=NULL )
	{
		if( isset( $options['logging'] ) )
		{
			static::$logging = $options['logging'];
		}

		$issues = array();
		$details = array();

		/* Get issues with hooks */
		$hookIssues = static::scanHooks( $getDetails, $shallowCheck, $limit, $options );
		if ( !empty( $hookIssues ) OR ( $shallowCheck AND $hookIssues !== null ) )
		{
			if ( $shallowCheck )
			{
				return $hookIssues;
			}

			foreach ( $hookIssues[0] as $appOrPlugin => $classes )
			{
				$issues[$appOrPlugin] = $issues[$appOrPlugin] ?? [];
				foreach ( $classes as $className => $classIssues )
				{
					$issues[$appOrPlugin][$className] = array_merge( $issues[$appOrPlugin][$className] ?? [], $classIssues );
				}
			}

			if ( $getDetails )
			{
				foreach ( $hookIssues[1] as $name => $methodData )
				{
					$details[$name] = $details[$name] ?? $methodData;
				}
			}

			if ( $limit !== -1 )
			{
				$limit -= \count( $hookIssues );
			}
		}

		/* Get issues with real classes inheriting IPS code */
		$extendingIssues = static::scanExtendedClasses( $getDetails, $shallowCheck, $limit, 5000, $options );
		if ( $extendingIssues OR ( $shallowCheck and $extendingIssues !== null ) )
		{
			if ( $shallowCheck )
			{
				return $extendingIssues;
			}

			foreach ( $extendingIssues[0] as $appOrPlugin => $classes )
			{
				$issues[$appOrPlugin] = $issues[$appOrPlugin] ?? [];
				foreach ( $classes as $className => $classIssues )
				{
					$issues[$appOrPlugin][$className] = array_merge( $issues[$appOrPlugin][$className] ?? [], $classIssues );
				}
			}

			if ( $getDetails )
			{
				foreach ( $extendingIssues[1] as $name => $methodData )
				{
					$details[$name] = $details[$name] ?? $methodData;
				}
			}

			if ( $limit !== -1 )
			{
				$limit -= \count( $hookIssues );
			}
		}

		if ( $shallowCheck )
		{
			if ( !empty( $issues ) )
			{
				foreach( $issues as $appOrPlugin => $classIssues )
				{
					foreach( $classIssues as $className => $issues )
					{
						foreach ( $issues as $issue )
						{
							if ( $issue['priority'] ?? 0 )
							{
								return false;
							}
						}
					}
				}
			}
			return null;
		}

		return $getDetails ? [ $issues, $details ] : [ $issues ];
	}

	/**
	 * Scan all the methods in hooks installed on a site to make sure they're declared in a php8 compatible way
	 *
	 * @param   bool        $getDetails     Return the details of every hook and base class loaded? This will make the result SIGNIFICANTLY larger so leave false unless you need it.
	 * @param   bool        $shallowCheck   Return a bool instead that indicates whether discrepancies were found. Potentially faster since it returns at the first discrepancy
	 * @param   int         $limit          The upper limit of issues to return. Set to -1 for no limit; Note this makes no difference for shallowChecks
	 * @param   array       $options        Options;
	 * @example
	 * array(
	 *      "enabledOnly" => true, // Whether to only check enabled apps & plugins only. default is true
	 *      "dirsToCheck" => [ "apps" => array(), "plugins" => array() ], // Filter for what apps & plugins to check. Empty/null checks all apps
	 * )
	 *
	 * @return  bool|null|array[]     By default, array of [$issues, $details], where both $issues and $details are arrays of issues and method details found respectively
	 */
	final public static function scanHooks( $getDetails=false, $shallowCheck=false, $limit=500, $options=NULL )
	{
		$details = array();
		$issues = array();
		if ( empty( $options ) OR !\is_array( $options ) )
		{
			$options = static::$defaultScannerOptions;
		}

		$appDirsToCheck = isset( $options['dirsToCheck']['apps'] ) ? $options['dirsToCheck']['apps'] : ( isset( $options['dirsToCheck']['plugins'] ) ? array() : null );
		$pluginDirsToCheck = isset( $options['dirsToCheck']['plugins'] ) ? $options['dirsToCheck']['plugins'] : ( isset( $options['dirsToCheck']['apps'] ) ? array() : null );
		$enabledOnly = $options['enabledOnly'] ?? static::$defaultScannerOptions['enabledOnly'];

		/* ITERATE through our hooks */
		if ( $enabledOnly )
		{
			if ( !empty( \IPS\IPS::$hooks ) OR file_exists( \IPS\SITE_FILES_PATH . "/plugins/hooks.php" ) )
			{
				if ( empty( \IPS\IPS::$hooks ) )
				{
					\IPS\IPS::$hooks = require( \IPS\SITE_FILES_PATH . '/plugins/hooks.php' );
				}
			}

			if ( empty( \IPS\IPS::$hooks ) )
			{
				return $shallowCheck ? false : ( $getDetails ? array( [], [] ) : array( [] ) );
			}
			$hooksToCheck = \IPS\IPS::$hooks;
		}
		else
		{
			$hooksToCheck = array();
			$where = array(
				[ 'type=?', 'C' ]
			);

			if ( $appDirsToCheck !== null OR $pluginDirsToCheck !== null )
			{
				$appAndPluginClauses = [];
				if ( !empty( $appDirsToCheck ) )
				{
					$appAndPluginClauses[] = \IPS\Db::i()->in( 'app', $appDirsToCheck );
				}

				if ( !empty( $pluginDirsToCheck ) )
				{
					$appAndPluginClauses[] = \IPS\Db::i()->in( 'plugin', $pluginDirsToCheck );
				}

				if ( !empty( $appAndPluginClauses ) )
				{
					$where[] = [ '( ' . implode( ' OR ', $appAndPluginClauses ) . ' )' ];
				}
			}
			$select = \IPS\Db::i()->select( 'core_hooks.*, core_plugins.plugin_location, core_plugins.plugin_enabled, core_applications.app_enabled', 'core_hooks', $where )
			                      ->join( 'core_plugins', 'core_plugins.plugin_id=core_hooks.plugin' )
			                      ->join( 'core_applications', 'core_applications.app_directory=core_hooks.app' );

			foreach ( $select as $row )
			{
				if ( empty( $row['app'] ?? $row['plugin_location'] ) )
				{
					continue;
				}

				$fileSource = ( $row['app'] === null ? 'plugins/' : 'applications/' ) . ( $row['app'] ?? $row['plugin_location'] ) . '/hooks/' . $row['filename'] . '.php';
				$enabled = \boolval( !empty( $row['app'] ) ? ( $row['app_enabled'] ?? true ) : ( $row['plugin_enabled'] ?? true ) );
				if ( !file_exists( \IPS\SITE_FILES_PATH . '/' . $fileSource ) )
				{
					continue;
				}

				$hooksToCheck[$row['class']][$row['id']] = array(
					'file' => $fileSource,
					'class' => ( $row['app'] === null ) ? "hook{$row['id']}" : "{$row['app']}_hook_{$row['filename']}",
					'isPriority' => $enabled
				);
			}
		}

		$count = 0;

		/* Teeny tiny optimization, but we'll save these for each app rather than compute on each and every hook */
		$hookBases = array( 'app' => array(), 'plugin' => array() );
		$shouldSkip = array( 'app' => array(), 'plugin' => array() );

		foreach ( $hooksToCheck as $baseClass => $hooks )
		{
			if ( \mb_substr( trim( $baseClass, '\\' ), 0, 9 ) === 'IPS\\Theme' )
			{
				continue;
			}
			$baseFile = null;

			/* Determine the path */
			$bits = explode( '\\', ltrim( $baseClass, '\\' ) );

			/* If this doesn't belong to us, try a PSR-0 loader or ignore it */
			$vendorName = array_shift( $bits );
			if( $vendorName !== 'IPS' )
			{
				continue;
			}

			/* Work out what namespace we're in */
			$baseClassName = array_pop( $bits );
			$baseClassNamespace = '\\IPS\\' . implode( '\\', $bits );

			/* Locate file */
			$path = '';
			$sourcesDirSet = FALSE;
			$baseFileLocation = \IPS\SITE_FILES_PATH;
			foreach ( array_merge( $bits, array( $baseClassName ) ) as $i => $bit )
			{
				if( preg_match( "/^[a-z0-9]/", $bit ) )
				{
					if( $i === 0 )
					{
						if ( \IPS\CIC2 AND !\in_array( $bit, \IPS\IPS::$ipsApps ) )
						{
							$path .= \IPS\SITE_FILES_PATH . '/applications/'; // Applications are in the root on Cloud2
						}
						else
						{
							$path .= \IPS\ROOT_PATH . '/applications/';
							$baseFileLocation = \IPS\ROOT_PATH;
						}
					}
					else
					{
						$sourcesDirSet = TRUE;
					}
				}
				elseif ( $i === 3 and $bit === 'Upgrade' )
				{
					$bit = \mb_strtolower( $bit );
				}
				elseif( $sourcesDirSet === FALSE )
				{
					if( $i === 0 )
					{
						$path .= \IPS\ROOT_PATH . '/system/';
						$baseFileLocation = \IPS\ROOT_PATH;
					}
					elseif ( $i === 1 and $bit === 'Application' )
					{
						// do nothing
					}
					else
					{
						$path .= 'sources/';
					}
					$sourcesDirSet = TRUE;
				}

				$path .= "{$bit}/";
			}



			/* Load it */
			$path = \substr( $path, 0, -1 ) . '.php';

			/* An app contains a hook that extends a class, but the class it extends cannot be found */
			if ( !file_exists( $path ) )
			{
				$path = \substr( $path, 0, -4 ) . \substr( $path, \strrpos( $path, '/' ) );
				if ( !file_exists( $path ) )
				{
					continue;
				}
			}

			/* Get the methods */
			$baseFile = $path;
			$baseMethods = static::getMethods( file_get_contents( $baseFile ) );

			if ( $getDetails )
			{
				$details[ $baseFile ] = $baseMethods;
			}

			foreach ( $hooks as $hookData )
			{
				$hookFileComponents = explode( '/', $hookData['file'] );
				$hookType = $hookFileComponents[0] === 'applications' ? 'app' : ( $hookFileComponents[0] === 'plugins' ? 'plugin' : null );
				$appOrPluginDir = $hookFileComponents[1];

				if ( $hookType === null )
				{
					continue;
				}

				/* Skip if we don't care about this app */
				if ( $shouldSkip[$hookType][$appOrPluginDir] ?? null )
				{
					continue;
				}
				elseif (
					!\array_key_exists( $appOrPluginDir, $shouldSkip[$hookType] ) AND (
					( ( $hookType === 'app' ) and \is_array( $appDirsToCheck ) and !\in_array( $appOrPluginDir, $appDirsToCheck ) ) OR
					( ( $hookType === 'app' ) and !\is_array( $appDirsToCheck ) and \in_array( $appOrPluginDir, \IPS\IPS::$ipsApps ) ) OR
					( \is_array( $pluginDirsToCheck ) and !\in_array( $appOrPluginDir, $pluginDirsToCheck ) ) )
				)
				{
					$shouldSkip[$hookType][$appOrPluginDir] = 1;
					continue;
				}
				$shouldSkip[$hookType][$appOrPluginDir] = 0;

				$appOrPlugin = ( $hookType === 'app' ) ? 'App - ' : 'Plugin - ';
				$issueFile = '/' . implode( '/', \array_slice( $hookFileComponents, 2 ) );
				array_pop( $hookFileComponents );
				$app = $appOrPlugin . $hookFileComponents[1];

				if ( $hookBases[$hookType][$appOrPluginDir] ?? null )
				{
					$hookBase = $hookBases[$hookType][$appOrPluginDir];
				}
				else
				{
					$hookBase = \IPS\SITE_FILES_PATH;
					if ( \IPS\CIC2 and $hookType === 'app' and \in_array( $appOrPluginDir, \IPS\IPS::$ipsApps ) )
					{
						$hookBase = \IPS\ROOT_PATH;
					}
					$hookBases[$hookType][$appOrPluginDir] = $hookBase;
				}
				$hookFile = $hookBase . '/' . $hookData['file'];

				if ( !file_exists( $hookFile ) )
				{
					static::log( "The file {$hookFile} does not exist, so the hook belonging to {$app} was not checked." );
					continue;
				}

				$hookMethods = static::getMethods( file_get_contents( $hookFile ) );
				if ( $getDetails )
				{
					$details[$hookFile] = $hookMethods;
				}
				$priority = $hookData['isPriority'] ?? ( $hookType === 'app' ? !\in_array( $appOrPluginDir, \IPS\IPS::$ipsApps ) : true ); // Built in apps are not priority

				/* Find any issues */
				$hookIssues = static::compareMethodsForIssues(
					$baseMethods,
					$hookMethods,
					$baseFile,
					$hookFile,
					$baseClass,
					$baseClassNamespace . '\\' . $hookData['class'], /* The hook class shares the same namespace as the base; see \IPS\IPS::monkeyPatch() to understand */
					$shallowCheck,
					$limit,
					true,
					$priority
				);

				if ( !empty( $hookIssues ) )
				{
					if ( $shallowCheck )
					{
						if ( $priority OR !( $options['shallowCheckPriorityOnly'] ?? 1 ) )
						{
							return true;
						}
						$issues[] = 1;
						continue;
					}

					$issues[$app] = $issues[$app] ?? [];
					$issues[$app][$issueFile] = array_merge( $issues[$app][$issueFile] ?? [], $hookIssues );

					if ( $limit !== -1 )
					{
						$limit -= \count( $hookIssues );
					}
					$count += \count( $hookIssues );
				}
			}

			if ( $limit !== -1 AND $count >= $limit )
			{
				break;
			}
		}

		if ( $shallowCheck )
		{
			return empty( $issues ) ? null : false; //only issues that could be here are not priority
		}

		return ( $getDetails ? [ $issues, $details ] : [ $issues ] );
	}

	/**
	 * Get Methods from a file's contents and details on each method
	 *
	 * @param   string      $scriptContents     The contents of the PHP file; should be the entire file's contents
	 *
	 * @return  array
	 *  @example with a file containing public function foo( ?array $item )
	 *      array(
	"foo" => array(
	"final" => false,
	"security" => "public",
	"static" => false,
	"name" => "foo",
	"parameters" => array(
	"item" => array(
	"name" => "item",
	"type" => "IPS\Content\Item",
	"nullable" => true,
	"passedByReference" => false,
	"packed" => false
	)
	),
	"returnType" => "bool",
	"lineNumber" => 384
	)
	)
	 */
	final protected static function getMethods( string $scriptContents ): array
	{
		$cacheKey = md5( $scriptContents );
		if ( isset( static::$methodInfoCache[ $cacheKey ] ) )
		{
			return static::$methodInfoCache[ $cacheKey ];
		}
		$methods = array();
		$pattern = '/(public\\s+|static\\s+|protected\\s+|private\\s+|final\\s+)*function\\s+[a-zA-Z0-9_]+\\s*\\(.*\\)[\\:\\sa-zA-Z0-9_\\\\]*\\{/';

		try
		{
			$matches = array();
			if ( preg_match_all( $pattern, $scriptContents, $matches, \PREG_OFFSET_CAPTURE ) and !empty( $matches[0] ) )
			{
				foreach ( $matches[0] as $methodDeclarationDetails )
				{
					$methodDeclaration = rtrim( $methodDeclarationDetails[0], '{' );
					$components = explode( 'function', $methodDeclaration, 2 );
					$prefix = preg_split( '/\s+/', $components[0] ) ?: array();
					$suffix = $components[1]; // we are certain this exists given our regex pattern

					$method = array(
						'final'      => \in_array( 'final', $prefix ),
						'security'   => \in_array( 'protected', $prefix ) ? 'protected' : ( \in_array( 'private', $prefix ) ? 'private' : 'public' ),
						'static'     => \in_array( 'static', $prefix ),
						'name'       => '',
						'parameters' => array(),
						'returnType' => null,
						'lineNumber' => \count( explode("\n", \mb_substr( $scriptContents, 0, $methodDeclarationDetails[1] ) ) )
					);
					preg_match( '/^\\s*([a-zA-Z0-9_]+)/', $suffix, $names );
					$method['name'] = $names[1];

					//did we get a return type?
					if ( $suffixComponents = explode( ':', $suffix ) and isset( $suffixComponents[1] ) )
					{
						$method['returnType'] = trim( $suffixComponents[1] ) ?: null;
					}

					/* Remove arrays from parameters since they will break things later Multidimensional arrays will still break things, but idc because this works on core/our MS code */
					$noArrays = preg_replace( ['/array\\(.*?\\)/', '/\\[[^\s]+?\\]/'], ['',''], trim( \mb_substr( $suffix, \strlen( $method['name'] ) - 1 ) ) );
					preg_match( '/\\(\\s*([^\\)]*)\\s*\\)/', $noArrays, $paramComponents );
					$method['parameters'] = static::getParameterDetails( $paramComponents[1] );
					$methods[$method['name']] = $method;
				}
			}
		}
		catch ( \Exception $e ) { }

		static::$methodInfoCache[$cacheKey] = $methods;
		return $methods;
	}
	
	/**
	 * Get Methods from a file's contents and details on each method
	 *
	 * @param   string      $scriptContents     The contents of the PHP file; should be the entire file's contents
	 *
	 * @return  array
	 */
	final public static function getClassDetails( string $scriptContents )
	{
		$cacheKey = md5( $scriptContents );
		if ( array_key_exists( $cacheKey, static::$classDetailsCache ) )
		{
			return static::$classDetailsCache[$cacheKey];
		}

		$details = array(
			'className'         => null,
			'fileNamespace'     => '\\',
			'classNamespace'    => '\\',
			'globalClassName'   => null,
			'parentClass'       => null,
			'abstract'          => false,
			'interfaces'        => array(),
			'methods'           => array(),
		);

		$pattern = '/(namespace\\s+[a-zA-Z\\\\_0-9]+?\\s*;(?:.|\\n)*?)?.*?(?:^|\\n)\\s*(abstract\\s+?)?\\s*\\bclass\\s+([a-zA-Z0-9_\\\\]+)(\\s+?extends\\s+[\\\\A-Za-z0-9_]+\\s+)?([^{]+?)?\\s+\\{/';
		$matches = array();
		if ( preg_match( $pattern, $scriptContents, $matches ) and !empty( $matches ) ) // we don't need preg_match_all since we're assuming 1 class per file
		{
			array_shift($matches);
			$component = trim( array_shift( $matches ) );

			/* Namespace found? */
			if ( \mb_substr( $component, 0, 9 ) === 'namespace' )
			{
				$namespaceDeclaration       = explode( ';', $component, 2 )[0];
				$details['fileNamespace']   = '\\' . trim( str_replace( 'namespace', '', $namespaceDeclaration ), "\\ \n\r\t\v\x00" );
				$component                  = trim( array_shift( $matches ) );
			}

			/* Is it abstract */
			if ( $component === 'abstract' )
			{
				$details['abstract'] = true;
				$component = trim( array_shift( $matches ) );
			}
			elseif ( empty( $component ) )
			{
				$component = trim( array_shift( $matches ) );
			}

			/* What's the name */
			$classComponents        = explode( '\\', trim( $component ) );
			$details['className']   = array_pop( $classComponents );

			/* If it's globally namespaced, don't use the file namespace */
			if ( @$classComponents[0] === 'IPS' OR \mb_substr( $component, 0, 1 ) === '\\' )
			{
				$imploded                   = implode( '\\', $classComponents );
				$details['classNamespace']  = '\\' . $imploded;
			}
			else
			{
				$details['classNamespace'] = '\\' . trim( $details['fileNamespace'], '\\' );
				if ( \count( $classComponents ) )
				{
					$details['classNamespace'] .= '\\' . implode( '\\', $classComponents );
				}
			}

			/* We can remove the leading underscore for IPS stuff */
			if ( \mb_substr( $details['classNamespace'], 0, 4 ) === '\\IPS' and \mb_substr( $details['className'], 0, 1 ) === '_' )
			{
				$details['className'] = \mb_substr( $details['className'], 1 );
			}

			$details['globalClassName'] = $details['classNamespace'] . '\\' . $details['className'];
			$component = trim( array_shift( $matches ) );
			if ( empty( $component ) )
			{
				$component = trim( array_shift( $matches ) );
			}

			/* Does it extend? */
			if ( \mb_substr( $component, 0, 7 ) === 'extends' )
			{
				$component = trim( str_replace( 'extends', '', $component ) );

				if ( \mb_substr( $component, 0, 1 ) !== '\\' AND \mb_substr( $component, 0, 3 ) !== 'IPS' )
				{
					$component = rtrim( $details['fileNamespace'], '\\' ) . '\\' . trim( $component, '\\' );
				}

				$details['parentClass'] = $component;
				$component              = trim( array_shift( $matches ) );
			}
			elseif ( empty( $component ) )
			{
				$component = trim( array_shift( $matches ) );
			}

			/* Interfaces? */
			$parts = explode( 'implements', $component );
			$interfacesComponent = array_pop( $parts );
			/* We got interfaces if removing the implements keyword changed the length */
			if ( \mb_strlen( $interfacesComponent ) !== \mb_strlen( $component ) )
			{
				$interfaceComponents = explode( ',', $interfacesComponent );
				$interfaceComponents = array_map( 'trim', $interfaceComponents );
				while ( \count( $interfaceComponents ) AND preg_match( "/^[\\\\_a-zA-Z0-9]+\$/", $interfaceComponents[0] ) )
				{
					$details['interfaces'][] = array_shift( $interfaceComponents );
				}
			}
		}

		if ( $details['globalClassName'] )
		{
			$details['methods'] = static::getMethods( $scriptContents );
		}

		static::$classDetailsCache[$cacheKey] = $details;
		return $details;
	}

	/**
	 * Get details for the parameter string (what's in the parenthesis following the method name)
	 * NOTE - This will break if you have a comma-delimited array as a default param; we don't care about that enough to fix it though
	 *
	 * @param   string  $parameterString        The raw string of parameters from a method declaration
	 *
	 * @return  array
	 */
	final protected static function getParameterDetails( string $parameterString ) : array
	{
		$parameters = array();
		if ( empty( trim( $parameterString ) ) )
		{
			return array();
		}

		$entities = explode( ',', $parameterString );

		foreach( $entities as $entity )
		{
			$parameter = array(
				"name" => "",
				"type" => null,
				"nullable" => false,
				"passedByReference" => false,
				"packed" => false
			);

			$entity = trim($entity);

			// is it packed?
			$unpacked = str_replace( '...', '', $entity );
			$parameter['packed'] = $entity !== $unpacked;
			$entity = $unpacked;

			// is it by reference?
			$unreferenced = str_replace( '&', '', $entity );
			$parameter['passedByReference'] = $unreferenced !== $entity;
			$entity = $unreferenced;

			// is it nullable?
			$nonNullable = str_replace( '?', '', $entity );
			$parameter['nullable'] = $entity !== $nonNullable;
			$entity = $nonNullable;

			// var name
			preg_match( '/\\$([a-zA-Z0-9_]+)\\s*=?\\s*(null|NULL)?/', $entity, $matches );
			$parameter['name'] = $matches[1];
			$defaultValue = isset( $matches[2] ) ? trim( $matches[2] ) : null;

			// to get the type, capture everything before the name
			$pattern = '/^(.*)\s+\\$' . $parameter['name'] . '/';
			preg_match( $pattern, $entity, $matches );
			if ( isset( $matches[1] ) )
			{
				$parameter['type'] = trim( $matches[1] );
			}

			/* If the default is null, it is still nullable. */
			if ( $parameter['type'] and $defaultValue and \mb_strtolower( $defaultValue ) === 'null' )
			{
				$parameter['nullable'] = true;
			}

			$parameters[$parameter['name']] = $parameter;
		}

		return $parameters;
	}

	/**
	 * Log any messages
	 *
	 * @param   string      $message
	 * @return  void
	 */
	protected static function log( string $message )
	{
		if( static::$logging === TRUE )
		{
			\IPS\Log::debug( $message, 'app_scanner' );
		}
	}
}
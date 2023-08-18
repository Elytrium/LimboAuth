<?php
/**
 * @brief		Javascript Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Aug 2013
 */

namespace IPS\Output;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Javascript: Javascript handler
 */
class _Javascript extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[Javascript]	Array of found javascript keys and objects
	 */
	protected static $foundJsObjects = array();
	
	/**
	 * @brief	[Javascript]	Position index for writing javascript to core_javascript
	 */
	protected static $positions = array();
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_javascript';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'javascript_';

	/**
	 * @brief	Javascript map of file object URLs
	 */
	protected static $javascriptObjects = null;
		
	/**
	 * Find JavaScript file
	 *
	 * @param	string $app			Application key or Plugin key
	 * @param	string $location		Location (front, admin, etc)
	 * @param	string $path			Path
	 * @param	string $name			Filename
	 * @return	\IPS\Output\Javascript
	 * @throws	\OutOfRangeException
	 */
	public static function find( $app, $location, $path, $name )
	{
		$key = md5( $app . '-' . $location . '-' . $path . '-' . $name );
		
		if ( !\in_array( $key, static::$foundJsObjects ) )
		{
			$where  = array( 'javascript_app=?', 'javascript_location=?', 'javascript_path=?', 'javascript_name=?' );
			
			if ( !\is_string( $app ) )
			{
				$where[]  = 'javascript_plugin=?';
				$bindings = array( 'core', 'plugins', '/', $name, $app );
			}
			else
			{
				$bindings = array( $app, $location, $path, $name );
			}
			
			try
			{
				$js = \IPS\Db::i()->select( '*', 'core_javascript', array_merge( array( implode( ' AND ', $where ) ), $bindings ) )->first();
				static::$foundJsObjects[ $key ] = parent::constructFromData( $js );
			}
			catch ( \UnderflowException $e )
			{
				throw new \OutOfRangeException;
			}
		}
		
		return static::$foundJsObjects[ $key ];
	}

	/**
	 * Set class properties if this object belongs to an application or a plugin
	 *
	 * @return void
	 */
	protected function setAppOrPluginProperties()
	{
		if ( ( $this->app AND !\is_string( $this->app ) ) OR ( $this->plugin ) )
		{
			$this->app		= 'core';
			$this->location	= 'plugins';
			$this->path		= '/';
			$this->type		= 'plugin';
			$this->plugin	= ( !\is_string( $this->app ) ) ? $this->app : $this->plugin;
		}
	}
	
	/**
	 * Create a javascript file. This overwrites any existing JS that matches the same parameters.
	 * If a $this->app is not a string, then it will assume plugin and automatically determine the correct 'app', 'location' and 'path' so these do not need to
	 * be defined.
	 * 
	 * @throws	\InvalidArgumentException
	 * @throws	\RuntimeException
	 * @return	void
	 */
	public function save()
	{
		$this->setAppOrPluginProperties();

		if ( ! isset( $this->path ) OR empty( $this->path ) )
		{
			$this->path = '/';
		}
		
		if ( ! $this->app OR ! $this->location OR ! $this->name )
		{
			throw new \InvalidArgumentException;
		}
		
		if ( ! $this->type )
		{
			$this->type = static::_getType( $this->path, $this->name );
		}

		$key = '';

		if ( \IPS\IN_DEV AND $this->type == 'plugin' )
		{
			$key = md5( $this->app . ';' . $this->location . ';' . $this->path . ';' . $this->name );
		}

		\IPS\Db::i()->insert( 'core_javascript', array(
			'javascript_app'		=> $this->app,
			'javascript_location'	=> $this->location,
			'javascript_plugin'		=> $this->plugin,
			'javascript_path'		=> $this->path,
			'javascript_name'		=> $this->name,
			'javascript_type'		=> $this->type,
			'javascript_content'	=> $this->content,
			'javascript_version'	=> $this->version,
			'javascript_position'	=> ( $this->position ) ? $this->position : 2000000,
			'javascript_key'		=> $key
		) );
	}
	
	/**
	 * Delete a javascript file
	 * be defined.
	 * 
	 * @throws	\InvalidArgumentException
	 * @return	void
	 */
	public function delete()
	{
		$this->setAppOrPluginProperties();
	
		if ( ! isset( $this->path ) OR empty( $this->path ) )
		{
			$this->path = '/';
		}
		
		if ( ! $this->app OR ! $this->location OR ! $this->name )
		{
			throw new \InvalidArgumentException;
		}
		
		if ( ! $this->type )
		{
			$this->type = static::_getType( $this->path, $this->name );
		}
		
		if ( \IPS\IN_DEV AND $this->location == 'plugins' )
		{
			try
			{
				$plugin = \IPS\Plugin::load( $this->plugin );

				/* Write the file to disk in the correct location */
				$file = \IPS\ROOT_PATH . '/plugins/' . $plugin->location . '/dev/js/' . $this->name;
					
				if ( \is_file( $file ) )
				{
					\unlink( $file );
				}
			}
			catch( \OutOfRangeException $e ) {}
		}
		
		$_where    = "javascript_app=? AND javascript_location=? AND javascript_path=? AND javascript_name=?";
		$where = array( $this->app, $this->location, $this->path, $this->name );

		if ( $this->location == 'plugins' )
		{
			$_where    .= " AND javascript_plugin=?";
			$where = array_merge( $where, array( $this->plugin ) );
		}

		array_unshift( $where, $_where );

		\IPS\Db::i()->delete( 'core_javascript', $where );
	}
	
	/**
	 * Create an XML document
	 *
	 * @param	string	$app		Application
	 * @param	array	$current	Details about current javascript.xml file. Used if $changes is desired to be tracked
	 * @param	array	$changes	If set, will set details of any changes by reference
	 * @return	object
	 */
	public static function createXml( $app, $current = array(), &$changes = NULL )
	{
		static::importDev($app);
		
		if ( $app === 'core' )
		{
			static::importDev('global');
		}
		
		/* Build XML and write to app directory */
		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent( TRUE );
		$xml->startDocument( '1.0', 'UTF-8' );
		
		/* Root tag */
		$xml->startElement('javascript');
		$xml->startAttribute('app');
		$xml->text( $app );
		$xml->endAttribute();
		
		/* Loop */
		foreach ( \IPS\Db::i()->select( '*', 'core_javascript', ( $app === 'core' ) ? \IPS\Db::i()->in( 'javascript_app', array('core', 'global') ) : array( 'javascript_app=?', $app ), 'javascript_path, javascript_location, javascript_name' ) as $js )
		{
			/* Initiate the <template> tag */
			$xml->startElement('file');
			$attributes = array();
			foreach( $js as $k => $v )
			{
				if ( \in_array( \substr( $k, 11 ), array('app', 'location', 'path', 'name', 'type', 'version', 'position' ) ) )
				{
					$attributes[ $k ] = $v;
					$xml->startAttribute( $k );
					$xml->text( $v );
					$xml->endAttribute();
				}
			}
				
			/* Write value */
			if ( preg_match( '/<|>|&/', $js['javascript_content'] ) )
			{
				$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $js['javascript_content'] ) );
			}
			else
			{
				$xml->text( $js['javascript_content'] );
			}
				
			/* Close the <template> tag */
			$xml->endElement();
			
			/* Note it */
			$k = "{$attributes['javascript_app']}/{$attributes['javascript_location']}/" . ( trim( $attributes['javascript_path'] ) ? "{$attributes['javascript_path']}/" : '' ) . "{$attributes['javascript_name']}";

			if( $changes !== NULL )
			{
				if ( !isset( $current['files'][ $k ] ) )
				{
					$changes['files']['added'][] = $k;
				}
				elseif ( $current['files'][ $k ] != $js['javascript_content'] )
				{
					$changes['files']['edited'][] = $k;
				}
			}

			unset( $current['files'][ $k ] );
		}

		if( \count( static::$_orders ) )
		{
			foreach( static::$_orders as $_app => $orderArray )
			{
				foreach( $orderArray as $order )
				{
					$xml->startElement('order');
					
					$xml->startAttribute( 'app' );
					$xml->text( $_app );
					$xml->endAttribute();

					$xml->startAttribute( 'path' );
					$xml->text( $order['path'] );
					$xml->endAttribute();

					$xml->text( $order['contents'] );

					$xml->endElement();
					
					/* Note it */
					$k = "{$_app}/{$order['path']}";

					if( $changes !== NULL )
					{
						if ( !isset( $current['orders'][ $k ] ) )
						{
							$changes['orders']['added'][] = $k;
						}
						elseif ( $current['orders'][ $k ] != $order['contents'] )
						{
							$changes['orders']['edited'][] = $k;
						}
					}

					unset( $current['orders'][ $k ] );
				}
			}
		}
		
		/* Finish */
		$xml->endDocument();
		
		if( $changes !== NULL )
		{
			$changes['files']['removed'] = array_keys( $current['files'] );
			$changes['orders']['removed'] = array_keys( $current['orders'] );
		}
		
		return $xml;
	}
	
	/**
	 * Import from an XML file on disk
	 * 
	 * @param	string		$file	File to import from (can be from applications dir, or tmp uploaded file)
	 * @param	int|null	$offset Offset to begin import from
	 * @param	int|null	$limit	Number of rows to import
	 * @return	bool|int	False if the file is invalid, otherwise the number of rows inserted
	 */
	public static function importXml( $file, $offset=null, $limit=null )
	{
		if ( ! \is_file( $file ) )
		{
			return false;
		}

		$i			= 0;
		$inserted	= 0;

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
		$xml = \IPS\Xml\XMLReader::safeOpen( $file );
		$xml->read();
		
		$app = $xml->getAttribute('app');
		
		/* Remove existing elements */
		if( $offset === null or $offset === 0 )
		{
			\IPS\Db::i()->delete( 'core_javascript', array( 'javascript_app=? and (javascript_plugin is null or javascript_plugin=?)', $app, '' ) );

			if ( $app === 'core' )
			{
				\IPS\Db::i()->delete( 'core_javascript', array( 'javascript_app=?', 'global' ) );
			}
		}
		
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

			if( $xml->name == 'file' )
			{
				/* We have a unique key on app, location, path, name so we use replace into to prevent duplicates */
				\IPS\Db::i()->replace( 'core_javascript', array(
					'javascript_app'		=> $xml->getAttribute('javascript_app'),
					'javascript_key'        => '',
					'javascript_plugin'		=> '',
					'javascript_location'	=> $xml->getAttribute('javascript_location'),
					'javascript_path'		=> $xml->getAttribute('javascript_path'),
					'javascript_name'		=> $xml->getAttribute('javascript_name'),
					'javascript_type'		=> $xml->getAttribute('javascript_type'),
					'javascript_content'	=> $xml->readString(),
					'javascript_version'	=> $xml->getAttribute('javascript_version'),
					'javascript_position'	=> $xml->getAttribute('javascript_position')
				) );
			}

			if( $limit !== null AND $i === ( $limit + $offset ) )
			{
				break;
			}
		}

		return $inserted;
	}
	
	/**
	 * Export Javascript to /dev/js
	 *
	 * @param	string	$app		 Application Directory
	 * @return	void
	 * @throws	\RuntimeException
	 */
	public static function exportDev( $app )
	{
		try
		{
			\IPS\Developer::writeDirectory( $app, 'js' );
		}
		catch( \RuntimeException $e )
		{
			throw new \RuntimeException( $e->getMessage() );
		}
	
		foreach( \IPS\Db::i()->select( '*', 'core_javascript', array( 'javascript_app=?', $app ) )->setKeyField('javascript_id') as $jsId => $js )
		{
			try
			{
				$pathToWrite = \IPS\Developer::writeDirectory( $app, 'js/', $js['javascript_location'] );
			}
			catch( \RuntimeException $e )
			{
				throw new \RuntimeException( $e->getMessage() );
			}
	
			if ( $js['javascript_path'] != '/' )
			{
				$_path = '';
					
				foreach( explode( '/', trim( $js['javascript_path'], '/' ) ) as $dir )
				{
					$_path .= '/' . trim( $dir, '/' );
						
					try
					{
						$pathToWrite = \IPS\Developer::writeDirectory( $app, 'js/' . $js['javascript_location'] . $_path );
					}
					catch( \RuntimeException $e )
					{
						throw new \RuntimeException( $e->getMessage() );
					}
				}
			}
	
			if ( ! @\file_put_contents( $pathToWrite . '/' . $js['javascript_name'], $js['javascript_content'] ) )
			{
				throw new \RuntimeException('core_theme_dev_cannot_write_js,' . $pathToWrite . '/' . $js['javascript_name']);
			}
			else
			{
				@chmod( $pathToWrite . '/' . $js['javascript_name'], 0777 );
			}
		}

		/* Open XML file */
		if( file_exists( \IPS\ROOT_PATH . '/applications/' . $app . '/data/javascript.xml' ) )
		{
			$xml = \IPS\Xml\XMLReader::safeOpen( \IPS\ROOT_PATH . '/applications/' . $app . '/data/javascript.xml' );				
			$xml->read();

			while( $xml->read() )
			{
				if( $xml->nodeType != \XMLReader::ELEMENT )
				{
					continue;
				}
			
				if( $xml->name == 'order' )
				{
					$path = rtrim( $xml->getAttribute('path'), '/' ) . '/';
					$app = $xml->getAttribute('app');
					$content = $xml->readString();

					file_put_contents( \IPS\ROOT_PATH . $path . 'order.txt', $content );
				}
			}
		}
	}

	/**
	 * @brief	Track order.txt files for writing
	 */
	protected static $_orders = array();

	/**
	 * Import JS from dev folders and store into core_javascript
	 * 
	 * @param	string	$app	Application
	 * @return	void
	 */
	public static function importDev( $app )
	{
		$root = \IPS\ROOT_PATH . '/applications/' . $app . '/dev/js';
		
		if ( $app == 'global' )
		{
			$root = \IPS\ROOT_PATH . '/dev/js/';
		}

		static::$_orders[ $app ]	= array();
		
		if ( is_dir( $root ) )
		{
			\IPS\Db::i()->delete( 'core_javascript', array( 'javascript_app=? and javascript_plugin = \'\'', $app ) );
			static::$positions = array();
			
			foreach( new \DirectoryIterator( $root ) as $location )
			{
				if ( $location->isDot() OR mb_substr( $location->getFilename(), 0, 1 ) === '.' )
				{
					continue;
				}
		
				if ( $location->isDir() )
				{
					static::_importDevDirectory( $root, $app, $location->getFilename() );
				}
			}
		}
	}
	
	/**
	 * Import a /dev directory recursively.
	 * 
	 * @param	string	$root		Root directory to recurse
	 * @param	string	$app		Application key
	 * @param	string	$location	Location (front, global, etc)
	 * @param   string  $path		Additional path information
	 * @return	void
	 */
	protected static function _importDevDirectory( $root, $app, $location, $path='' )
	{
		$dir       = $root . '/' . $location . '/' . $path;
		$parentDir = preg_replace( '#^(.*)/([^\/]+?)$#', '\2', $dir );
		
		if ( \file_exists( $dir . '/order.txt' ) )
		{
			$contents = file_get_contents( $dir . '/order.txt' );

			/* Enforce \n line endings */
			if( mb_strtolower( mb_substr( PHP_OS, 0, 3 ) ) === 'win' )
			{
				$contents = str_replace( "\r\n", "\n", $contents );
			}

			static::$_orders[ $app ][] = array( 'path' => str_replace( \IPS\ROOT_PATH, '', $dir ), 'contents' => $contents );

			$order = \file( $dir . '/order.txt' );
			
			foreach( $order as $item )
			{
				$item = trim( $item );
				
				if ( isset( static::$positions[ $app . '-' . $location . '-' . $parentDir ] ) AND static::$positions[ $app . '-' . $location . '-' . $parentDir ] < 1000000 )
				{
					static::$positions[ $app . '-' . $location . '-' . $item ] = ++static::$positions[ $app . '-' . $location . '-' . $parentDir ];
				}
				else
				{
					static::$positions[ $app . '-' . $location . '-' . $item ] = static::_getNextPosition( $app, $location );
				}
			}
		}
		
		foreach ( new \DirectoryIterator( $dir ) as $file )
		{
			if ( $file->isDot() || mb_substr( $file->getFilename(), 0, 1 ) === '.' || $file == 'index.html' )
			{
				continue;
			}
				
			if ( $file->isDir() )
			{
				static::_importDevDirectory( $root, $app, $location, $path . '/' . $file->getFileName() );
			}
			else if ( mb_substr( $file->getFileName(), -3 ) === '.js' )
			{
				$js = \file_get_contents( $dir . '/' . $file->getFilename() );
				
				if ( isset( static::$positions[ $app . '-' . $location . '-' . $file->getFilename() ] ) )
				{
					$position = static::$positions[ $app . '-' . $location . '-' . $file->getFilename() ];
				}
				else
				{
					/* Attempt to order by files */
					if ( ! isset( static::$positions[  $app . '-' . $location . '-' . $parentDir ] ) )
					{
						static::$positions[  $app . '-' . $location . '-' . $parentDir ] = static::_getNextPosition( $app, $location ) + 1000000;
					}
					
					$position = static::$positions[  $app . '-' . $location . '-' . $parentDir ];
				}
				
				/* Check to see if 'ips.{dir}.js' exists and if so, put that first */
				if ( $file->getFilename() == 'ips.' . $parentDir . '.js' )
				{
					$position = $position - 1;
				}
				
				$path = trim( $path, '/' );

				\IPS\Db::i()->delete( 'core_javascript', array( 'javascript_app=? AND javascript_location=? AND javascript_path=? AND javascript_name=?', $app, $location, $path, $file->getFileName() ) );
				
				\IPS\Db::i()->insert( 'core_javascript', array(
						'javascript_app'		=> $app,
						'javascript_location'	=> $location,
						'javascript_plugin'		=> '',
						'javascript_path'		=> $path,
						'javascript_name'		=> $file->getFileName(),
						'javascript_type'		=> static::_getType( $dir . '/', $file->getFileName() ),
						'javascript_content'	=> ( mb_strtolower( mb_substr( PHP_OS, 0, 3 ) ) === 'win' ) ? str_replace( "\r\n", "\n", $js ) : $js,
						'javascript_position'   => $position,
						'javascript_version'	=> \IPS\Application::load( ( $app == 'global' ? 'core' : $app ) )->long_version,
						'javascript_key'		=> md5( $app . ';' . $location . ';' . $path . ';' . $file->getFileName() )
				) );
			}
		}
	}
	
	/**
	 * Delete a compiled JS file
	 * 
	 * @param	string		$app		Application
	 * @param	string|null	$location	Location (front, global, etc)
	 * @param	string|null	$file		File to remove
	 * @return	boolean|null
	 */
	public static function deleteCompiled( $app, $location=null, $file=null )
	{
		$map   = ( isset( \IPS\Data\Store::i()->javascript_map ) )      ? \IPS\Data\Store::i()->javascript_map      : array();
		$files = ( isset( \IPS\Data\Store::i()->javascript_file_map ) ) ? \IPS\Data\Store::i()->javascript_file_map : array();

		if ( $location === NULL and $file === NULL )
		{
			if ( isset( $map[ $app ] ) )
			{
				foreach( $map[ $app ] as $hash => $path )
				{
					try
					{
						\IPS\File::get( 'core_Theme', $path )->delete();
					}
					catch( \Exception $e ) { }
				}
				
				$map[ $app ] = array();
			}
		}
		else
		{
			if ( isset( $map[ $app ] ) )
			{
				foreach( $map[ $app ] as $hash => $path )
				{
					if ( $file === NULL )
					{
						$lookFor = 'javascript_' . $app . '/' . $location . '_';
					}
					else
					{
						$lookFor = 'javascript_' . $app . '/' . $location . '_' . $file;
					}
					
					if ( mb_substr( $path, 0, mb_strlen( $lookFor ) ) === $lookFor )
					{
						try
						{
							\IPS\File::get( 'core_Theme', $path )->delete();
						}
						catch( \Exception $e ) { }
						
						unset( $map[ $app ][ $hash ] );
					}
				}
			}
		}
		
		\IPS\Data\Store::i()->javascript_map = $map;
	}
	
	/**
	 * Compiles JS into fewer minified files suitable for non IN_DEV use.
	 * Imports the fewer files into a database for writing out.
	 * 
	 * @param	string		$app		Application
	 * @param	string|null	$location	Location (front, global, etc)
	 * @param	string|null	$file		File to build
	 * @return	boolean|null
	 */
	public static function compile( $app, $location=null, $file=null )
	{
		$flagKey = 'js_compiling_' . md5( $app . ',' . $location . ',' . $file );
		if ( \IPS\Theme::checkLock( $flagKey ) )
		{
			return NULL;
		}

		\IPS\Theme::lock( $flagKey );
		
		$map   = ( isset( \IPS\Data\Store::i()->javascript_map ) and \is_array( \IPS\Data\Store::i()->javascript_map ) )           ? \IPS\Data\Store::i()->javascript_map      : array();
		$files = ( isset( \IPS\Data\Store::i()->javascript_file_map ) and \is_array( \IPS\Data\Store::i()->javascript_file_map ) ) ? \IPS\Data\Store::i()->javascript_file_map : array();
		
		if ( $location === null and $file === null )
		{
			$map[ $app ]   = array();
			$files[ $app ] = array();
			
			\IPS\Data\Store::i()->javascript_file_map = $files;
		}
		
		if ( $app == 'global' )
		{
			if ( $location === null and $file === null )
			{
				\IPS\File::getClass('core_Theme')->deleteContainer( 'javascript_' . $app );
			}
			
			if ( mb_substr( $file, 0, 8 ) === 'js_lang_' )
			{
				$langId = \intval( mb_substr( $file, 8, -3 ) );
				
				if ( $langId > 0 )
				{
					/* Write it */
					$obj = static::_writeJavascriptFromResultset( array( array(
						'javascript_app'      => 'global',
						'javascript_location' => 'root',
						'javascript_path'     => '',
						'javascript_name'     => $file,
						'javascript_type'     => 'lang',
						'javascript_content'  => static::getJavascriptLanguage( $langId ),
						'javascript_position' => 0
					) ), $file, 'global', 'root' );

					$map[ $app ][ md5( 'global-root-' . $file ) ] = (string) $obj;
				}
			}
			
			foreach( \IPS\Output::$globalJavascript as $fileName )
			{			
				if ( $file === null OR $file == $fileName )
				{
					if ( $fileName === 'map.js' )
					{
						/* Write it */
						$obj = static::_writeJavascriptFromResultset( array( array(
							'javascript_app'      => 'global',
							'javascript_location' => 'root',
							'javascript_path'     => '',
							'javascript_name'     => 'map.js',
							'javascript_type'     => 'framework',
							'javascript_content'  => static::_getMapAsScript(),
							'javascript_position' => 0
						) ), 'map.js', 'global', 'root' );

						$map[ $app ][ md5( 'global-root-map.js' ) ] = (string) $obj;
					}
					else
					{
						$rows = iterator_to_array( \IPS\Db::i()->select(
							'*',
							'core_javascript', array( 'javascript_app=? AND javascript_location=?', $app, mb_substr( $fileName, 0, -3 ) ),
							'javascript_app, javascript_location, javascript_position'
						)->setKeyField('javascript_id') );
						
						/* Write it */
						$obj = static::_writeJavascriptFromResultset( $rows, $fileName, $app, 'root' );

						$map[ $app ][ md5( $app .'-' . 'root' . '-' . $fileName ) ] = $obj ? (string) $obj : null;
					}
				}
			}
		}
		else
		{
			if ( $location === null and $file === null )
			{
				\IPS\File::getClass('core_Theme')->deleteContainer( 'javascript_' . $app );
			}
			
			/* Plugins */
			if ( $app === 'core' )
			{
				if ( $file === null OR $file === 'plugins.js' )
				{
					$ids = array();
					
					foreach ( \IPS\Db::i()->select( '*', 'core_plugins', 'plugin_enabled=1' ) as $row )
					{
						$ids[] = \intval( $row['plugin_id'] );
					}
		
					if ( \count( $ids ) )
					{
						$obj = static::_writeJavascriptFromResultset( \IPS\Db::i()->select( '*', 'core_javascript', array( array( 'javascript_app=? AND javascript_location=? AND javascript_type=? AND javascript_plugin IN(' . implode( ',', array_values( $ids ) ) . ')', 'core', 'plugins', 'plugin' ) ), 'javascript_app, javascript_location, javascript_position' )->setKeyField('javascript_id'), 'plugins.js', 'core', 'plugins' );
						$map[ $app ][ md5( 'core-plugins-plugins.js' ) ] = $obj ? (string) $obj : null;
					}
				}
			}
			
			foreach( array( 'front', 'admin', 'global' ) as $loc )
			{
				if ( ( $file === null OR $file === 'app.js' ) AND ( $location === null OR $location === $loc ) )
				{
					/* app.js: All models and ui for the app */
					$obj = static::_writeJavascriptFromResultset( \IPS\Db::i()->select( '*', 'core_javascript', array( 'javascript_app=? AND javascript_location=? AND javascript_type IN (\'mixins\', \'model\',\'ui\')', $app, $loc ), 'javascript_app, javascript_location, javascript_position' )->setKeyField('javascript_id'), 'app.js', $app, $loc );
				
					$map[ $app ][ md5( $app .'-' . $loc . '-' . 'app.js' ) ] = $obj ? (string) $obj : null;
				}
				
				/* {location}_{controller}.js: Controllers and templates bundles */
				$controllers = array();
				$templates   = array();
				
				foreach( \IPS\Db::i()->select( '*', 'core_javascript', array( 'javascript_app=? AND javascript_location=? AND javascript_type IN (\'controller\', \'template\')', $app, $loc ), 'javascript_app, javascript_location, javascript_position' )->setKeyField('javascript_id') as $id => $row )
				{
					if ( $row['javascript_type'] == 'controller' )
					{
						list( $dir, $controller ) = explode( '/', $row['javascript_path'] );
						
						$controllers[ $controller ][] = $row;
					}
					else
					{
						/* ips . templates . {controller} . js */
						$bits = explode( '.', $row['javascript_name'] );
						$templates[ $bits[2] ][] = $row; 
					}
				}
				
				/* Check to see if we have a template that does not have a controller */
				$templateOnlyKeys = array_diff( array_keys( $templates ), array_keys( $controllers ) );
				
				foreach( array_merge( array_keys( $controllers ), array_keys( $templates ) ) as $key )
				{
					if ( ( $file === null ) OR ( $file === $loc . '_' . $key . '.js' ) )
					{
						$files = array();
						
						if ( isset( $templates[ $key ] ) )
						{
							foreach( $templates[ $key ] as $id => $row )
							{
								$files[ $row['javascript_id'] ] = $row;
							}
						}
						
						if ( isset( $controllers[ $key ] ) )
						{
							foreach( $controllers[ $key ] as $controller )
							{
								$files[ $controller['javascript_id'] ] = $controller;
							}
						}
						
						/* Template only? */
						if ( \count( $templateOnlyKeys ) AND \in_array( $key, $templateOnlyKeys ) )
						{
							foreach( $templates[ $key ] as $tmpl )
							{
								$files[ $tmpl['javascript_id'] ] = $tmpl;
							}
						}
						
						$obj = static::_writeJavascriptFromResultset( $files, $loc . '_' . $key . '.js', $app, $loc );
						
						$map[ $app ][ md5( $app .'-' . $loc . '-' . $loc . '_' . $key . '.js' ) ] = $obj ? (string) $obj : null;
					}
				}
			}
		}
		
		\IPS\Data\Store::i()->javascript_map = $map;
		\IPS\Settings::i()->changeValues( array( 'javascript_updated' => time() ) );

		/* As the filename has changed, rebuild the JS map */
		if ( ( $app !== 'global' and ( $location === NULL or \in_array( $location, array( 'admin', 'front', 'global' ) ) ) ) )
		{
			\IPS\Output\Javascript::compile( 'global', 'root', 'map.js' );
		}
		
		\IPS\Theme::unlock( $flagKey );
		
		return TRUE;
	}
	
	/**
	 * Combines the DB rows into a single string for writing.
	 * 
	 * @param 	\IPS\Db\Select	$files		Result set
	 * @param 	string 			$fileName	Filename to use
	 * @param	string			$app		Application
	 * @param	string			$location	Location (front, global, etc)
	 * @return	object|null		\IPS\File object
	 */
	protected static function _writeJavascriptFromResultset( $files, $fileName, $app, $location )
	{
		$content = array();
		$jsMap   = ( isset( \IPS\Data\Store::i()->javascript_map ) and \is_array( \IPS\Data\Store::i()->javascript_map ) ) ? \IPS\Data\Store::i()->javascript_map : array();
		
		/* Try and remove any existing files */
		try
		{
			$md5 = md5( $app . '-' . $location . '-' . $fileName );
			
			if ( isset( $jsMap[ $app ] ) and \in_array( $md5, array_keys( $jsMap[ $app ] ) ) )
			{
				\IPS\File::get( 'core_Theme', $jsMap[ $app ][ $md5 ] )->delete();
			}
		}
		catch ( \InvalidArgumentException $e ) { }
		
		
		if ( ! \count( $files ) )
		{
			return null;
		}
		
		foreach( $files as $row )
		{
			$content[] = static::_minify( $row['javascript_content'] ) . ";"; 
		}
		
		$fileObject = static::_writeJavascript( implode( "\n", $content ), $fileName, $app, $location );

		try
		{
			$map = \IPS\Data\Store::i()->javascript_file_map;
		}
		catch ( \OutOfRangeException $e )
		{
			$map = array();
		}
		
		foreach( $files as $row )
		{
			$path = ( ( ! empty( $row['javascript_path'] ) AND $row['javascript_path'] !== '/' ) ? '/' . $row['javascript_path'] . '/' : '/' );
			$map[ $row['javascript_app'] ][ $row['javascript_location'] ][ $path ][ $row['javascript_name'] ] = (string) $fileObject;
		}
		
		\IPS\Data\Store::i()->javascript_file_map = $map;
		
		return $fileObject;
	}
	
	/**
	 * Combines the DB rows into a single string for writing.
	 *
	 * @param 	string		$content	Javascript string to write
	 * @param 	string 		$fileName	Filename to use
	 * @param	string		$app		Application
	 * @param	string		$location	Location (front, global, etc)
	 * @return	object		\IPS\File object
	 */
	protected static function _writeJavascript( $content, $fileName, $app, $location )
	{
		return \IPS\File::create( 'core_Theme', $location . '_' . $fileName, $content, 'javascript_' . $app, TRUE, NULL, FALSE );
	}
	
	/**
	 * Get javascript language as a script
	 * 
	 * @param	int		$langId	ID of language to fetch
	 * @return string
	 */
	public static function getJavascriptLanguage( $langId )
	{
		$_lang	= array();
		
		foreach ( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'lang_id=? AND word_js=?', $langId, TRUE ) ) as $row )
		{
			$_lang[ $row['word_key'] ] = $row['word_custom'] ?: $row['word_default'];
		}
		
		if ( \IPS\IN_DEV )
		{
			foreach ( \IPS\Application::enabledApplications() as $app )
			{
				if ( file_exists( \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/jslang.php" ) )
				{
					require \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/jslang.php";
					$_lang = array_merge( $_lang, $lang );
				}
			}
			foreach ( \IPS\Plugin::enabledPlugins() as $plugin )
			{
				if ( file_exists( \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php" ) )
				{
					require \IPS\ROOT_PATH . "/plugins/{$plugin->location}/dev/jslang.php";
					$_lang = array_merge( $_lang, $lang );
				}
			}
		}
		
		return 'ips.setString( ' . json_encode( $_lang ) . ')';
	}
	
	/**
	 * Get javascript map as a script
	 * 
	 * @return string
	 */
	protected static function _getMapAsScript()
	{
		$fileMap = isset(\IPS\Data\Store::i()->javascript_file_map) ? \IPS\Data\Store::i()->javascript_file_map : array();
		$map     = array();
		
		/* Fix up the map a little */
		foreach( $fileMap as $app => $location )
		{
			if ( $app === 'global' )
			{
				continue;
			}
			
			foreach( $location as $locName => $locData )
			{
				foreach( $locData as $name => $items )
				{
					if ( mb_stristr( $name, '/controllers/' ) )
					{
						$url = array_pop( $items );
					
						$map[ $app ][ $locName . '_' . trim( str_replace( '/controllers/', '', $name ) , '/' ) ] = (string) \IPS\File::get( 'core_Theme', $url )->url;
					}
				}
			}
		}
		
		$json = json_encode( $map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		
		return 'var ipsJavascriptMap = ' . $json . ';';
	}
	
	/**
	 * Minifies javascript
	 * 
	 * @param string $js	Javascript code
	 * @return string
	 */
	protected static function _minify( $js )
	{
		require_once( \IPS\ROOT_PATH . '/system/3rd_party/JShrink/Minifier.php' );

		$js = \JShrink\Minifier::minify( $js, array( 'flaggedComments' => false ) );
		
		return $js;
	}
	
	/**
	 * Get JS
	 *
	 * @param	string		$file		Filename
	 * @param	string|null	$app		Application
	 * @param	string|null	$location	Location (e.g. 'admin', 'front')
	 * @return	array		URL to JS files
	 */
	public static function inDevJs( $file, $app=NULL, $location=NULL )
	{
		/* 1: Is this the magic plugin JS */
		if ( $app === 'core' and $location === 'plugins' and $file === 'plugins.js' )
		{
			$return = array();
			
			foreach ( new \GlobIterator( \IPS\ROOT_PATH . '/plugins/*/dev/js/*' ) as $file )
			{
				try
				{
					$plugin = \IPS\Plugin::getPluginFromPath( $file );

					if( $plugin->enabled )
					{
						$url = str_replace( \IPS\ROOT_PATH, rtrim( \IPS\Settings::i()->base_url, '/' ), $file );
						$return[] = str_replace( '\\', '/', $url );
					}
				}
				catch( \OutOfRangeException $e ){}
			}
			
			return $return;
		}
	
		/* 2: Is it a named grouped collection? */
		if ( $app === NULL AND $location === NULL )
		{
			if ( $file === 'map.js' )
			{
				return array();
			}
			
			if ( \in_array( $file, \IPS\Output::$globalJavascript ) )
			{
				$app      = 'global';
				$location = '/';
			}
			
			if ( mb_substr( $file, 0, 8 ) === 'js_lang_' )
			{
				return array( \IPS\Http\Url::baseUrl() . "/applications/core/interface/js/jslang.php?langId=" . \intval( mb_substr( $file, 8, -3 ) ) );
			}
		}
	
		$app      = $app      ?: ( \IPS\Dispatcher::i()->application ? \IPS\Dispatcher::i()->application->directory : NULL );
		$location = $location ?: \IPS\Dispatcher::i()->controllerLocation;
		
		/* 3: App JS? */
		if ( $file == 'app.js' )
		{
			return static::_appJs( $app, $location );
		}
		
		/* 3: Is this a controller/template combo? */
		if ( mb_strstr( $file, '_') AND mb_substr( $file, -3 ) === '.js' )
		{
			list( $location, $key ) = explode( '_',  mb_substr( $file, 0, -3 ) );
			
			if ( ( $location == 'front' OR $location == 'admin' OR $location == 'global' ) AND ! empty( $key ) )
			{ 
				return static::_sectionJs( $key, $location, $app );
			}
		}
		
		/* 4: Is it in the interface directory? */
		if ( $location === 'interface' )
		{
			$path = \IPS\ROOT_PATH . "/applications/{$app}/interface/{$file}";
		}
		else if ( $app === 'global' )
		{
			$return = array();
			
			if ( \in_array( $file, \IPS\Output::$globalJavascript ) )
			{
				return static::_directoryJs( \IPS\ROOT_PATH . "/dev/js/" . mb_substr( $file, 0, -3 ) );
			}
			
			$path = \IPS\ROOT_PATH . "/dev/js";
		}
		else
		{
			$path = \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/{$file}";
		}
		
		if ( is_dir( $path ) )
		{
			return static::_directoryJs( $path );
		}
		else
		{
			return array( str_replace( \IPS\ROOT_PATH, \IPS\Http\Url::baseUrl(), $path ) );
		}
	}
	
	/**
	 * Get the map for IN_DEV use
	 * 
	 * @return array
	 */
	public static function inDevMapJs()
	{
		$files = array();

		foreach( \IPS\Application::enabledApplications() as $app => $data )
		{
			$root       	= \IPS\ROOT_PATH . "/applications/{$app}/dev/js/";

			foreach( array( 'front', 'admin', 'global' ) as $location )
			{
				if ( is_dir( $root . "{$location}/controllers" ) )
				{
					foreach ( new \DirectoryIterator( $root . "{$location}/controllers" ) as $controllerDir )
					{
						if ( $controllerDir->isDot() || mb_substr( $controllerDir->getFilename(), 0, 1 ) === '.' )
						{
							continue;
						}
							
						if ( $controllerDir->isDir() )
						{
							$controllerPath	= \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/controllers/{$controllerDir}";

							foreach ( new \DirectoryIterator( $root . "{$location}/controllers/{$controllerDir}" ) as $file )
							{
								if ( $file->isDot() || mb_substr( $file->getFilename(), 0, 1 ) === '.' || $file == 'index.html' )
								{
									continue;
								}

								$files[ $app ][ $location . '_' . $controllerDir ] = str_replace( \IPS\ROOT_PATH, rtrim( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ), '/' ), $controllerPath ) . '/' . $file->getFileName();
							}
						}
					}
				}
			}
		}

		$json = json_encode( $files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		return 'var ipsJavascriptMap = ' . $json . ';';
	}

	/**
	 * Returns the cache bust key for javascript
	 *
	 * @return string
	 */
	public static function javascriptCacheBustKey()
	{
		return \IPS\CACHEBUST_KEY . \IPS\Settings::i()->javascript_updated;
	}

	/**
	 * Returns the component parts from a URL
	 * 
	 * @param string $url	Full URL of javascript file
	 * @return array	Array( 'app' => .., 'location' => .., 'path' => .., 'name' => .. );
	 */
	protected static function _urlToComponents( $url )
	{
		$url  = ltrim( str_replace( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ) . 'applications', '', $url ), '/' );
		$bits = explode( "/", $url );
		
		$app = array_shift( $bits );
		
		/* Remove dev/js */
		array_shift( $bits );
		array_shift( $bits );
		
		$location = array_shift( $bits );
		$name     = array_pop( $bits );
		$path	  = preg_replace( '#/{2,}#', '/', '/' . trim( implode( '/', $bits ), '/' ) . '/' );
		
		return array( 'app' => $app, 'location' => $location, 'name' => $name, 'path' => $path );
	}
	
	/**
	 * Gets app specific Javascript
	 * 
	 * @param   string  $app	    Application
	 * @param	string	$location	Location (front, global, etc)
	 * @return  array
	 */
	protected static function _appJs( $app, $location )
	{
		$models = array();

		/* Only include if the app is enabled */
		if( !\in_array( $app, array_keys( \IPS\Application::enabledApplications() ) ) )
		{
			return $models;
		}
		
		if ( \is_dir( \IPS\ROOT_PATH . "/applications/" . $app ) )
		{
			foreach( array( \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/mixins", \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/models", \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/ui" ) as $durr )
			{
				/* Models */
				if ( \is_dir( $durr ) )
				{
					$models = static::_directoryJs( $durr );
				}
			}
		}
		
		return $models;
	}
	
	/**
	 * Returns section specific JS (controller, models and any template files required)
	 * 
	 * @param string $key			Controller Key (messages, reports, etc)
	 * @param string $location		Location (front, admin)
	 * @param string $app			Application
	 * @return	array
	 */
	protected static function _sectionJs( $key, $location, $app )
	{
		$return        = array();
		$controllerDir = \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/controllers/{$key}";
		$templatesDir  = \IPS\ROOT_PATH . "/applications/{$app}/dev/js/{$location}/templates";
		$controllers   = array();
		$templates     = array();
		
		/* Get controllers */
		if ( \is_dir( $controllerDir ) )
		{
			$controllers = static::_directoryJs( $controllerDir );
		}
		
		/* Templates */
		if ( \is_dir( $templatesDir . '/' . $key ) )
		{
			$templates = static::_directoryJs( $templatesDir . '/' . $key );
		}
		else if ( \file_exists( $templatesDir . '/ips.templates.' . $key . '.js' ) )
		{
			$templates = array( str_replace( \IPS\ROOT_PATH, rtrim( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ), '/' ), $templatesDir . '/ips.templates.' . $key . '.js' ) );
		}
		
		return array_merge( $templates, $controllers );
	}
	
	/**
	 * Get Javascript files recursively.
	 * 
	 * @param string $path		Path to open
	 * @param array	 $return	Items retreived so far
	 * @return array
	 */
	protected static function _directoryJs( $path, $return=array() )
	{
		$path     = rtrim( $path, '/' );
		$contents = array();
		
		foreach ( new \DirectoryIterator( $path ) as $file )
		{
			if ( $file->isDot() || mb_substr( $file->getFilename(), 0, 1 ) === '.' )
			{
				continue;
			}
			
			if ( $file->isDir() )
			{
				$return = static::_directoryJs( $path . '/' . $file->getFileName(), $return );
			}
			else if ( mb_substr( $file->getFileName(), -3 ) === '.js' )
			{
				$contents[] = str_replace( \IPS\ROOT_PATH, rtrim( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ), '/' ), $path ) . '/' . $file->getFileName();
			}
		}
		
		if ( \count( $contents ) )
		{
			/* Check to see if 'ips.{dir}.js' exists and if so, put that first */
			$parentDir = preg_replace( '#^(.*)/([^\/]+?)$#', '\2', $path );
				
			$reordered = array();
				
			foreach( $contents as $url )
			{
				if ( mb_strstr( $url, '/' . $parentDir . '/' ) AND mb_strstr( $url, '/' . $parentDir . '/ips.' . $parentDir . '.js' ) )
				{
					$reordered[] = $url;
					break;
				}
			}
			
			$return = array_merge( $reordered, array_diff( $contents, $reordered ), $return );
		}
		
		$reordered = array();
		
		if ( is_dir( $path ) AND \file_exists( $path . '/order.txt' ) )
		{
			$order = \file( $path . '/order.txt' );
			
			foreach( $order as $item )
			{
				foreach( $return as $url )
				{
					$item = trim( $item );
					
					if ( mb_substr( $item, -3 ) === '.js' )
					{
						if ( mb_substr( $url, -(mb_strlen( $item ) ) ) == $item )
						{
							$reordered[] = $url;
						}
					}
					else
					{
						if ( mb_substr( str_replace( \IPS\Http\Url::baseUrl( \IPS\Http\Url::PROTOCOL_RELATIVE ) . ltrim( str_replace( \IPS\ROOT_PATH, '', $path ), '/' ), '', $url ), 1, (mb_strlen( $item ) ) ) == $item )
						{
							$reordered[] = $url;
						}
					}
				}
			}
		}
		
		if ( \count( $reordered ) )
		{
			/* Add in items not specified in the order */
			$diff = array_diff( $return, $reordered );
				
			if ( \count( $diff ) )
			{
				foreach( $diff as $url )
				{
					$reordered[] = $url;
				}
			}
			
			return $reordered;
		}
		
		return $return;
	}
	
	/**
	 * Returns the type of javascript file
	 * @param	string	$path	Path
	 * @param	string	$name	File Name
	 * @return string
	 */
	protected static function _getType( $path, $name )
	{
		$type = 'framework';
		
		if ( mb_strstr( $path, '/controllers/' ) )
		{
			$type = 'controller';
		}
		else if ( mb_strstr( $path, '/models/' ) )
		{
			$type = 'model';
		}
		else if ( mb_strstr( $path, '/mixins/' ) )
		{
			$type = 'mixins';
		}
		else if ( mb_strstr( $path, '/ui/' ) )
		{
			$type = 'ui';
		}
		else if ( mb_strstr( $name, 'ips.templates.' ) )
		{
			$type = 'template';
		}
	
		return $type;
	}
	
	/**
	 * Returns an incremented position integer for this app and location
	 *
	 * @param	string	$app		Application key
	 * @param	string	$location	Location (front, global, etc)
	 * @return	int
	 */
	protected static function _getNextPosition( $app, $location )
	{
		if ( ! isset( static::$positions[ $app . '-' . $location ] ) )
		{
			static::$positions[ $app . '-' . $location ] = 0;
		}
		
		static::$positions[ $app . '-' . $location ] += 50;
		
		return static::$positions[ $app . '-' . $location ];
	}
} 
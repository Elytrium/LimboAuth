<?php
/**
 * @brief		Abstract Storage Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		07 May 2013
 */

namespace IPS\Data;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Storage Class
 */
abstract class _Store extends AbstractData
{
	/**
	 * @brief	Instance
	 */
	protected static $instance;

	/**
	 * Available Data Store Methods
	 * - MUST always return 'Database' and 'FileSystem' options
	 *
	 * @return  array
	 */
	public static function availableMethods(): array
	{
		return [
			'Database'      => 'IPS\Data\Store\Database',
			'FileSystem'    => 'IPS\Data\Store\FileSystem',
			'Redis'         => 'IPS\Data\Store\Redis',
		];
	}
	
	/**
	 * Get instance
	 *
	 * @return	\IPS\Data\Store
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			try
			{
				if( !isset( static::availableMethods()[ \IPS\STORE_METHOD ] ) )
				{
					throw new \IPS\Data\Store\Exception;
				}

				$classname = static::availableMethods()[ \IPS\STORE_METHOD ];

				if( !$classname::supported() )
				{
					throw new \IPS\Data\Store\Exception;
				}

				static::$instance = new $classname( json_decode( \IPS\STORE_CONFIG, TRUE ) );
			}
			catch( \IPS\Data\Store\Exception $e )
			{
				/* Fall back to MySQL (if not using it currently, and then FileSystem if we are) */
				if ( \IPS\STORE_METHOD !== 'Database' )
				{
					$classname = static::availableMethods()['Database'];
				}
				else
				{
					$classname = static::availableMethods()['FileSystem'];
				}
				
				static::$instance = new $classname( json_decode( \IPS\STORE_CONFIG, TRUE ) );
			}
		}
		
		return static::$instance;
	}
	
	/**
	 * @brief	Always needed Store keys
	 */
	public $initLoad = array();
	
	/**
	 * @brief	Template store keys
	 */
	public $templateLoad = array();
	
	/**
	 * @brief	Log
	 */
	public $log	= array();
	
	/**
	 * Test to see if this store works
	 *
	 * @return	boolean
	 */
	public static function testStore()
	{
		try
		{
			$classname = 'IPS\Data\Store\\' . \IPS\STORE_METHOD;
			$store = new $classname( json_decode( \IPS\STORE_CONFIG, TRUE ) );
			
			return $store->test();
		}
		catch( \Exception $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * Load mutiple
	 * Used so if it is known that several are going to be needed, they can all be loaded into memory at the same time
	 *
	 * @param	array	$keys	Keys
	 * @return	void
	 */
	public function loadIntoMemory( array $keys )
	{
		
	}

	/**
	 * Test the datastore engine to make sure it's working
	 *
	 * @return	bool
	 */
	public function test()
	{
		/* Set a test key */
		$key	= 'test_' . md5( mt_rand() );
		$this->$key	= '1';

		/* Now clear our internal cache to ensure the value isn't returned from there */
		unset( $this->_data[ $key ], $this->_exists[ $key ], static::$cache[ $key ] );

		/* And then try to retrieve it */
		if( isset( $this->$key ) )
		{
			$value = $this->$key;

			unset( $this->$key );

			return ( $value == 1 ) ? TRUE : FALSE;
		}

		return FALSE;
	}
	
	/**
	 * Magic Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 */
	public function __get( $key )
	{
		/* Try to get it from the cache store... */
		try
		{
			/* If caching is enabled, and this isn't the special map of hashes... */ 
			if ( $this->useCache( $key ) )
			{
				if ( \IPS\Data\Cache::i()->checkKeys() === false )
				{
					if ( isset( \IPS\Data\Cache::i()->$key ) and \IPS\Data\Cache::i()->$key !== NULL )
					{
						/* Cache engine always returns current content, so return it */
						return \IPS\Data\Cache::i()->$key;
					}
					 
					/* Still here? throw an exception so we get it from the data storage engine */
					throw new \OutOfRangeException;
				}
				else
				{
					/* It exists in the caching engine, and we know the hash of the correct value... */
					$cacheKeys = ( isset( $this->cacheKeys ) and \is_array( $this->cacheKeys ) ) ? $this->cacheKeys : array();
					if ( array_key_exists( $key, $cacheKeys ) and isset( \IPS\Data\Cache::i()->$key ) )
					{				
						/* Get it... */
						$value = \IPS\Data\Cache::i()->$key;
						
						/* But only use it if the hash matches */
						if( $cacheKeys[ $key ] == md5( json_encode( $value ) ) )
						{
							return $value;
						}
					}
				}
			}
			
			/* Still here? throw an exception so we get it from the data storage engine */
			throw new \OutOfRangeException;
		}
		
		/* If we couldn't get it from the cache engine, get it from the data storage engine */
		catch ( \OutOfRangeException $e )
		{
			/* Actually get it... */
			$value = parent::__get( $key );
		
			/* If caching is enabled, and this isn't the special map of hashes... */
			if ( $this->useCache( $key ) )
			{
				/* Set it in the caching engine... */
				\IPS\Data\Cache::i()->$key = $value;
				
				if ( \IPS\Data\Cache::i()->checkKeys() === true )
				{
					/* And set the hash in the cacheKeys hash map */
					$cacheKeys = ( isset( $this->cacheKeys ) and \is_array( $this->cacheKeys ) ) ? $this->cacheKeys : array();
					$cacheKeys[ $key ] = md5( json_encode( $value ) );
					$this->cacheKeys = $cacheKeys;
				}
			}
			
			return $value;
		}
	}

	/**
	 * Magic Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		/* Actually set it in the data storage engine */
		parent::__set( $key, $value );
		
		/* If caching is enabled, and this isn't the special map of hashes... */
		if ( $this->useCache( $key ) )
		{
			/* Then also set it in the cache... */
			\IPS\Data\Cache::i()->$key = $value;
		
			/* And set the hash in the cacheKeys hash map */
			if ( \IPS\Data\Cache::i()->checkKeys() === true )
			{
				if ( ( isset( $this->cacheKeys ) and \is_array( $this->cacheKeys ) ) )
				{
					$cacheKeys = \is_array( $this->cacheKeys ) ? $this->cacheKeys : array();
					$cacheKeys[ $key ] = md5( json_encode( $value ) );
					$this->cacheKeys = $cacheKeys;
				}
				else
				{
					$this->cacheKeys = array( $key => md5( json_encode( $value ) ) );
				}
			}
		}
	}
	
	/**
	 * Magic Method: Isset
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function __isset( $key )
	{
		/* If caching is enabled, and this isn't the special map of hashes, try to get it from the cache store... */
		if ( $this->useCache( $key ) )
		{
			/* Does it exist in the caching engine? */
			if ( isset( \IPS\Data\Cache::i()->$key ) )
			{
				/* Get it... */
				$value = \IPS\Data\Cache::i()->$key;
				
				if ( \IPS\Data\Cache::i()->checkKeys() === false )
				{
					/* Cache engine always returns current content, so return it */
					return TRUE;
				}				
				
				/* Only use it if the hash matches */
				$cacheKeys = ( isset( $this->cacheKeys ) and \is_array( $this->cacheKeys ) ) ? $this->cacheKeys : array();
				if ( isset( $cacheKeys[ $key ] ) and $cacheKeys[ $key ] == md5( json_encode( $value ) ) )
				{
					return TRUE;
				}
			}			
		}
		
		/* If we're still here, check the data storage engine... */
		return parent::__isset( $key );
	}
		
	/**
	 * Magic Method: Unset
	 *
	 * @param	string	$key	Key
	 * @return	void
	 */
	public function __unset( $key )
	{
		/* Unset it in the data storage engine */
		parent::__unset( $key );
		
		/* If caching is enabled, and this isn't the special map of hashes, remove it from the cache store... */
		if ( $this->useCache( $key ) )
		{
			/* Remove it from the cache store... */
			unset( \IPS\Data\Cache::i()->$key );
			
			if ( \IPS\Data\Cache::i()->checkKeys() === true )
			{
				/* And from the special map of hashes */
				try
				{
					$cacheKeys = \is_array( $this->cacheKeys ) ? $this->cacheKeys : array();
				}
				/* If the cache doesn't exist we shouldn't error out with an uncaught OutOfRangeException */
				catch( \OutOfRangeException $e )
				{
					$cacheKeys = array();
				}
	
				if( isset( $cacheKeys[ $key ] ) )
				{
					unset( $cacheKeys[ $key ] );
				}
	
				$this->cacheKeys = $cacheKeys;
			}
		}
	}
	
	/**
	 * Check the cache for this key?
	 *
	 * @param	string	$key	The store key
	 * @return boolean
	 */
	protected function useCache( $key )
	{
		return ( \IPS\CACHE_METHOD != \IPS\STORE_METHOD and ( ! \IPS\Data\Cache::i() instanceof \IPS\Data\Cache\None ) and $key !== 'cacheKeys' );
	}
}
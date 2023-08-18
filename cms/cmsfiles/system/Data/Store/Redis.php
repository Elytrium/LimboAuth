<?php
/**
 * @brief		Redis Storage Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 October 2017
 */

namespace IPS\Data\Store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis Storage Class
 */
class _Redis extends \IPS\Data\Store
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return class_exists('Redis');
	}
	
	/**
	 * Redis key
	 */
	protected $_redisKey;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Configuration to use
	 * @return	void
	 */
	public function __construct( $configuration )
	{
		try
		{
			$connection = \IPS\Redis::i()->connection('write');

			if( !$connection )
			{
				throw new \RedisException;
			}
		}
		catch( \RedisException $e )
		{
			throw new \IPS\Data\Store\Exception;
		}
	}
		
	/**
	 * Get random string used in the keys to identify this site compared to other sites
	 *
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function _getRedisKey()
	{
		if ( !$this->_redisKey )
		{
			/* Last access ensures that the data is not stale if we fail back to MySQL and then go back to Redis later */
			if ( !( $this->_redisKey = \IPS\Redis::i()->get( 'redisKey_store' ) ) OR ! \IPS\Redis::i()->get( 'redisStore_lastAccess' ) )
			{
				$this->_redisKey = md5( mt_rand() );
				\IPS\Redis::i()->setex( 'redisKey_store', 604800, $this->_redisKey );
				\IPS\Redis::i()->setex( 'redisStore_lastAccess', ( 3 * 3600 ), time() );
			}
		}

		return $this->_redisKey . '_str_';
	}
	
	/**
	 * @brief	Cache
	 */
	protected static $cache = array();
	
	/**
	 * @brief	Already updated lastAccess?
	 */
	protected static $updatedLastAccess = FALSE;
	
	/**
	 * Abstract Method: Get
	 *
	 * @param   string          $key	Key
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	public function get( $key )
	{
		if( array_key_exists( $key, static::$cache ) )
		{
			return static::$cache[ $key ];
		}

		try
		{
			/* Set the last access time */
			if ( static::$updatedLastAccess === FALSE )
			{
				\IPS\Redis::i()->setex( 'redisStore_lastAccess', ( 3 * 3600 ), time() );
				static::$updatedLastAccess = TRUE;
			}
			
			$return = \IPS\Redis::i()->get( $this->_getRedisKey() . '_' . $key );
			
			if ( $return !== FALSE AND $decoded = \IPS\Redis::i()->decode( $return ) )
			{
				static::$cache[ $key ] = $decoded;
				return static::$cache[ $key ];
			}
			else
			{
				throw new \UnderflowException;
			}
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );

			throw new \UnderflowException;
		}
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string			$key	Key
	 * @param	string			$value	Value
	 * @return	bool
	 */
	public function set( $key, $value )
	{
		try
		{
			return (bool) \IPS\Redis::i()->setex( $this->_getRedisKey() . '_' . $key, 604800, \IPS\Redis::i()->encode( $value ) );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );

			return FALSE;
		}
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function exists( $key )
	{
		if( array_key_exists( $key, static::$cache ) )
		{
			return ( static::$cache[ $key ] === FALSE ) ? FALSE : TRUE;
		}

		/* We do a get instead of an exists() check because it will cause the cache value to be fetched and cached inline, saving another call to the server */
		try
		{
			return ( $this->get( $key ) === FALSE ) ? FALSE : TRUE;
		}
		catch ( \UnderflowException $e )
		{
			return FALSE;
		}
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function delete( $key )
	{		
		try
		{
			unset( static::$cache[ $key ] );
			return (bool) \IPS\Redis::i()->del( $this->_getRedisKey() . '_' . $key );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );
			return FALSE;
		}
	}
	
	/**
	 * Abstract Method: Clear All
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		try
		{
			$this->_redisKey = md5( mt_rand() );
			\IPS\Redis::i()->setex( 'redisKey_store', 604800, $this->_redisKey );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );
			return FALSE;
		}
	}
	
	/**
	 * Test the datastore engine to make sure it's working
	 * Overloaded here to ensure that if we're using a cluster, there isn't a false error because of the delay with RW
	 *
	 * @return	bool
	 */
	public function test()
	{
		return \IPS\Redis::i()->test();
	}
}
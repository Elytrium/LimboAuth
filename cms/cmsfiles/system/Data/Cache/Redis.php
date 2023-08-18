<?php
/**
 * @brief		Redis Cache Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Oct 2013
 */

namespace IPS\Data\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis Cache Class
 */
class _Redis extends \IPS\Data\Cache
{
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
			throw new \IPS\Data\Cache\Exception;
		}
	}
	
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
	 * Needs cache key check with this storage engine to maintain integrity
	 *
	 * @return boolean
	 */
	public function checkKeys()
	{
		return false;
	}
	
	/**
	 * Configuration
	 *
	 * @param	array	$configuration	Existing settings
	 * @return	array	\IPS\Helpers\Form\FormAbstract elements
	 */
	public static function configuration( $configuration )
	{
		return array(
			'server'	=> new \IPS\Helpers\Form\Text( 'server_host', isset( $configuration['server'] ) ? $configuration['server'] : '', FALSE, array( 'placeholder' => '127.0.0.1' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' and empty( $val ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			}, NULL, NULL, 'redis_host' ),
			'port'		=> new \IPS\Helpers\Form\Number( 'server_port', isset( $configuration['port'] ) ? $configuration['port'] : NULL, FALSE, array( 'placeholder' => '6379' ), function( $val )
			{
				if ( \IPS\Request::i()->cache_method === 'Redis' AND $val AND ( $val < 0 OR $val > 65535 ) )
				{
					throw new \DomainException( 'datastore_redis_servers_err' );
				}
			}, NULL, NULL, 'redis_port' ),
			'password'	=> new \IPS\Helpers\Form\Password( 'server_password', isset( $configuration['password'] ) ? $configuration['password'] : '', FALSE, array(), NULL,  NULL, NULL, 'redis_password' ),
		);
	}

	/**
	 * Redis key
	 */
	protected $_redisKey;
		
	/**
	 * Get random string used in the keys to identify this site compared to other sites
	 *
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function _getRedisKey()
	{
		if ( !$this->_redisKey )
		{
			if ( !( $this->_redisKey = \IPS\Redis::i()->get( 'redisKey' ) ) )
			{
				$this->_redisKey = md5( mt_rand() );
				\IPS\Redis::i()->setex( 'redisKey', 604800, $this->_redisKey );
			}
		}
		
		return $this->_redisKey;
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param   string          $key
	 * @return  string|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function get( $key )
	{
		if( array_key_exists( $key, $this->cache ) )
		{
			return $this->cache[ $key ];
		}

		try
		{
			$this->cache[ $key ] = \IPS\Redis::i()->get( $this->_getRedisKey() . '_' . $key );

			return $this->cache[ $key ];
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );

			return FALSE;
		}
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string			$key	Key
	 * @param	string			$value	Value
	 * @param	\IPS\DateTime|NULL	$expire	Expreation time, or NULL for no expiration
	 * @return	bool
	 */
	protected function set( $key, $value, \IPS\DateTime $expire = NULL )
	{
		try
		{
			if ( $expire )
			{
				return (bool) \IPS\Redis::i()->setex( $this->_getRedisKey() . '_' . $key, $expire->getTimestamp() - time(), $value );
			}
			else
			{
				/* Set for 24 hours */
				return (bool) \IPS\Redis::i()->setex( $this->_getRedisKey() . '_' . $key, 86400, $value );
			}
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
	protected function exists( $key )
	{
		if( array_key_exists( $key, $this->cache ) )
		{
			return ( $this->cache[ $key ] === FALSE ) ? FALSE : TRUE;
		}

		/* We do a get instead of an exists() check because it will cause the cache value to be fetched and cached inline, saving another call to the server */
		return ( $this->get( $key ) === FALSE ) ? FALSE : TRUE;
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{		
		try
		{
			return (bool) \IPS\Redis::i()->del( $this->_getRedisKey() . '_' . $key );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );
			return FALSE;
		}
	}

	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		parent::clearAll();
		
		$this->_redisKey = md5( mt_rand() );
		\IPS\Redis::i()->setex( 'redisKey', 604800, $this->_redisKey );
	}
	
}
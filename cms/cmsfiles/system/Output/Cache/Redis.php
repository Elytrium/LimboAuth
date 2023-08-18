<?php
/**
 * @brief		Redis Cache Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 November 2018
 */

namespace IPS\Output\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis Cache Class
 */
class _Redis extends \IPS\Output\Cache
{
	/**
	 * Turn on debugging which logs stats about cache hits and misses in Redis
	 *
	 * @var bool
	 */
	protected $debug = FALSE;
	
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		if ( class_exists('Redis') )
		{
			try
			{
				$connection = \IPS\Redis::i()->connection('read');
	
				if( !$connection )
				{
					return FALSE;
				}
				
				return TRUE;
			}
			catch( \RedisException $e )
			{ 
				return FALSE;
			}
		}
		
		return FALSE;
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
			if ( !( $this->_redisKey = \IPS\Redis::i()->get( 'redisKeyOutputCache' ) ) )
			{
				$this->_redisKey = md5( mt_rand() ) . '_pg_';
				\IPS\Redis::i()->setex( 'redisKeyOutputCache', 604800, $this->_redisKey );
			}
		}
		
		return $this->_redisKey;
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param   string          $key
	 * @return  array|FALSE    Value from the _datastore; FALSE if key doesn't exist
	 */
	protected function _get( $key )
	{
		try
		{
			$value = \IPS\Redis::i()->get( $this->_getRedisKey() . '_' . $key );
			
			if ( ! $value )
			{
				if ( $this->debug )
				{
					\IPS\Redis::i()->zRemRangeByScore('guest_page_failures_key_gone', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
					\IPS\Redis::i()->zAdd( 'guest_page_failures_key_gone', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
				}
				
				return FALSE;
			}

			if ( ! $decrypted = \IPS\Text\Encrypt::fromTag( $value )->decrypt() )
			{
				if ( $this->debug )
				{
					\IPS\Redis::i()->zRemRangeByScore('guest_page_failures_decrypt', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
					\IPS\Redis::i()->zAdd( 'guest_page_failures_decrypt', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
				}

				return FALSE;
			}

			if ( ! $unzipped = @gzdecode( $decrypted ) )
			{
				if ( $this->debug )
				{					
					\IPS\Redis::i()->zRemRangeByScore('guest_page_failures_gz', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
					\IPS\Redis::i()->zAdd( 'guest_page_failures_gz', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
				}
				
				return FALSE;
			}
			
			if ( ! $data = json_decode( $unzipped, TRUE ) )
			{
				if ( $this->debug )
				{
					\IPS\Redis::i()->zRemRangeByScore('guest_page_failures_json_decode', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
					\IPS\Redis::i()->zAdd( 'guest_page_failures_json_decode', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
				}
				
				return FALSE;
			}
			
			if ( $this->debug )
			{
				\IPS\Redis::i()->zRemRangeByScore('guest_page_hits', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
				\IPS\Redis::i()->zAdd( 'guest_page_hits', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
			}
			
			return array( 'output' => $data['value'], 'meta' => $data['meta'], 'expires' => $data['expires'] );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );

			return FALSE;
		}
	}
	
	/**
	 * Store value using cache method if available or falling back to the database
	 *
	 * @param	string			$key		Key
	 * @param	mixed			$value		Value
	 * @param	mixed			$meta		Meta data (contentType, headers, etc)
	 * @param	\IPS\DateTime	$expire		Expiration if using database
	 * @return	bool
	 */
	protected function _set( $key, $value, $meta, \IPS\DateTime $expire )
	{
		try
		{
			if ( $this->debug )
			{
				\IPS\Redis::i()->zRemRangeByScore('guest_page_created', 0, time() - \IPS\CACHE_PAGE_TIMEOUT );
				\IPS\Redis::i()->zAdd( 'guest_page_created', time(), md5(microtime()), \IPS\CACHE_PAGE_TIMEOUT );
			}

			/* json_encode > gzencode > crypt */
			return (bool) \IPS\Redis::i()->setex( $this->_getRedisKey() . '_' . $key, $expire->getTimestamp() - time(), \IPS\Text\Encrypt::fromPlaintext( gzencode( json_encode( array( 'value' => $value, 'meta' => $meta, 'expires' => $expire->getTimestamp() ) ) ) )->tag() );
		}
		catch( \RedisException $e )
		{
			\IPS\Redis::i()->resetConnection( $e );

			return FALSE;
		}
	}

	/**
	 * Remove all expired caches
	 *
	 * @param	boolean	$truncate	Truncate the table
	 * @return	void
	 */
	public function deleteExpired( $truncate=FALSE )
	{
		/* Redis handles this for us automatically */
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		$this->_redisKey = md5( mt_rand() ) . '_pg_';
		\IPS\Redis::i()->setex( 'redisKeyOutputCache', 604800, $this->_redisKey );
	}
}
<?php
/**
 * @brief		Dummy Cache Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Sept 2013
 */

namespace IPS\Data\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Dummy Storage Class
 */
class _None extends \IPS\Data\Cache
{
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	Configuration
	 * @return	void
	 * @note	Overridden for performance reasons
	 */
	public function __construct( $configuration )
	{
	}

	/**
	 * Magic Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 * @throws	\OutOfRangeException
	 * @note	Overridden for performance reasons
	 */
	public function __get( $key )
	{
		throw new \OutOfRangeException;
	}

	/**
	 * Magic Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	void
	 * @note	Overridden for performance reasons
	 */
	public function __set( $key, $value )
	{
	}

	/**
	 * Magic Method: Isset
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 * @note	Overridden for performance reasons
	 */
	public function __isset( $key )
	{
		return FALSE;
	}

	/**
	 * Magic Method: Unset
	 *
	 * @param	string	$key	Key
	 * @return	void
	 * @note	Overridden for performance reasons
	 */
	public function __unset( $key )
	{
	}

	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return TRUE;
	}
	
	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 */
	protected function get( $key )
	{
		throw new \RuntimeException;
	}
	
	/**
	 * Get value using cache method if available or falling back to the database
	 *
	 * @param	string	$key	Key
	 * @param	bool	$fallback	Use database if no caching method is available?
	 * @return	mixed
	 * @throws	\OutOfRangeException
	 */
	public function getWithExpire( $key, $fallback=FALSE )
	{
		if ( $fallback )
		{
			try
			{
				return json_decode( \IPS\Db::i()->select( 'cache_value', 'core_cache', array( 'cache_key=? AND cache_expire>?', $key, time() ) )->first(), TRUE );
			}
			catch ( \UnderflowException $e )
			{
				throw new \OutOfRangeException;
			}
		}
		else
		{
			throw new \OutOfRangeException;
		}
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	bool
	 */
	protected function set( $key, $value )
	{
		return FALSE;
	}
	
	/**
	 * Store value using cache method if available or falling back to the database
	 *
	 * @param	string			$key		Key
	 * @param	mixed			$value		Value
	 * @param	\IPS\DateTime	$expire		Expiration if using database
	 * @param	bool			$fallback	Use database if no caching method is available?
	 * @return	bool
	 */
	public function storeWithExpire( $key, $value, \IPS\DateTime $expire, $fallback=FALSE )
	{
		if ( $fallback )
		{
			\IPS\Db::i()->replace( 'core_cache', array(
				'cache_key'		=> $key,
				'cache_value'	=> json_encode( $value ),
				'cache_expire'	=> $expire->getTimestamp()
			) );

			$this->_data[ $key ] = $value;
			$this->_exists[ $key ] = $key;

			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function exists( $key )
	{
		return FALSE;
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{
		return TRUE;
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		parent::clearAll();
		try
		{
			\IPS\Db::i()->delete( 'core_cache' );
		}
		catch ( \IPS\Db\Exception $e ){}
	}
}
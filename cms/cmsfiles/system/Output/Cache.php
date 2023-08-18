<?php
/**
 * @brief		Abstract output caching class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 November 2018
 */

namespace IPS\Output;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Output Caching Class
 */
abstract class _Cache
{
	/**
	 * @brief	Instance
	 */
	protected static $instance;

	/**
	 * @brief	Caches already retrieved this instance
	 */
	protected $cache	= array();
	
	/**
	 * @brief	Log
	 */
	public $log	= array();

	/**
	 * Get instance
	 *
	 * @return	\IPS\Data\Cache
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = 'IPS\Output\Cache\\' . \IPS\OUTPUT_CACHE_METHOD;
			
			if ( \class_exists( $classname ) and $classname::supported() )
			{
				try
				{
					static::$instance = new $classname();
				}
				catch( \IPS\Data\Cache\Exception $e )
				{
					static::$instance = new \IPS\Output\Cache\Database();
				}
			}
			else
			{
				static::$instance = new \IPS\Output\Cache\Database();
			}
		}

		return static::$instance;
	}
	
	/**
	 * Store value
	 *
	 * @param	string			$key		Key
	 * @param	mixed			$value		Value
	 * @param	mixed			$meta		Meta data (contentType, headers, etc)
	 * @param	\IPS\DateTime	$expire		Expiration if using database
	 * @return	bool
	 */
	public function set( $key, $value, $meta, \IPS\DateTime $expire )
	{
		if ( \IPS\CACHING_LOG )
		{
			$this->log[ microtime(true) ] = array( 'set', $key, json_encode( $value, JSON_PRETTY_PRINT ), var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ) );
		}
		
		if ( $this->_set( $key, $value, $meta, $expire ) )
		{
			return TRUE;
		}

		return FALSE;
	}
	
	/**
	 * Get value
	 *
	 * @param	string	$key	Key
	 * @return	mixed
	 * @throws	\OutOfRangeException
	 */
	public function get( $key )
	{
		$data = $this->_get( $key );

		if( \is_array( $data ) and \count( $data ) and isset( $data['output'] ) and isset( $data['expires'] ) )
		{
			/* Is it expired? */
			if( $data['expires'] AND time() < $data['expires'] )
			{
				return $data;
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
		else
		{
			throw new \OutOfRangeException;
		}
	}
}
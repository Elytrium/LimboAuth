<?php
/**
 * @brief		Abstract Data Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Sep 2013
 */

namespace IPS\Data;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Data Class
 */
abstract class _AbstractData
{
	/**
	 * Configuration
	 *
	 * @param	array	$configuration	Existing settings
	 * @return	array	\IPS\Helpers\Form\FormAbstract elements
	 */
	public static function configuration( $configuration )
	{
		return array();
	}
	
	/**
	 * @brief	Data Store
	 */
	protected $_data = array();
	
	/**
	 * @brief	Keys that exist
	 */
	protected $_exists = array();

	/**
	 * Magic Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 * @throws	\OutOfRangeException
	 */
	public function __get( $key )
	{	
		if( !isset( $this->_data[ $key ] ) )
		{						
			if( $this->exists( $key ) )
			{
				$value = $this->decode( $this->get( $key ) );
				if ( \IPS\CACHING_LOG )
				{
					$this->log[ sprintf( '%.4f', microtime(true) ) ] = array( 'get', $key, json_encode( $value, JSON_PRETTY_PRINT ), var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ) );
				}
				$this->_data[ $key ] = $value;
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
		
		return $this->_data[ $key ];
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
		if ( \IPS\CACHING_LOG )
		{
			$this->log[ sprintf( '%.4f', microtime(true) ) ] = array( 'set', $key, json_encode( $value, JSON_PRETTY_PRINT ), var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ) );
		}
		
		if ( $this->set( $key, $this->encode( $value ) ) )
		{
			$this->_data[ $key ] = $value;
			$this->_exists[ $key ] = $key;
		}
		else
		{
			/* We can only really log if datastore fails, because if cache fails we create a catch-22 where settings can't/hasn't loaded but
				we rely on that data for logging, so don't log if this is for cache */
			$classname = explode( '\\', \get_class( $this ) );

			if( $classname[2] == 'Cache' )
			{
				return;
			}

			$classarea = array_pop( $classname );
			$namespace = array_pop( $classname );

			\IPS\Log::log( "Could not write to {$namespace}-{$classarea} ({$key})", 'datastore' );
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
		if( array_key_exists( $key, $this->_data ) or array_key_exists( $key, $this->_exists ) )
		{
			return TRUE;
		}
		else
		{
			$return = $this->exists( $key );
			if ( $return )
			{
				$this->_exists[ $key ] = $key;
			}
			if ( \IPS\CACHING_LOG )
			{
				$this->log[ sprintf( '%.4f', microtime(true) ) ] = array( 'check', $key, var_export( $return, TRUE ), var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ) );
			}
			return $return;
		}
	}
		
	/**
	 * Magic Method: Unset
	 *
	 * @param	string	$key	Key
	 * @return	void
	 */
	public function __unset( $key )
	{
		if ( \IPS\CACHING_LOG )
		{
			$this->log[ sprintf( '%.4f', microtime(true) ) ] = array( 'delete', $key, NULL, var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ) );
		}
		$this->delete( $key );
		unset( $this->_data[ $key ] );
		unset( $this->_exists[ $key ] );
	}
	
	/**
	 * Encode
	 *
	 * @param	mixed	$value	Value
	 * @return	string
	 */
	protected function encode( $value )
	{
		return json_encode( $value );
	}
	
	/**
	 * Decode
	 *
	 * @param	mixed	$value	Value
	 * @return	mixed
	 */
	protected function decode( $value )
	{
		return json_decode( $value, TRUE );
	}
}
<?php
/**
 * @brief		Database Output Cache Storage Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 November 2018
 */

namespace IPS\Output\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Database Output Cache Storage Class
 */
class _Database extends \IPS\Output\Cache
{
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
	 * @return	array
	 */
	protected function _get( $key )
	{
		try
		{
			$data = \IPS\Db::i()->select( '*', 'core_output_cache', array( 'cache_key=? AND cache_expire>?', $key, time() ) )->first();
			$meta = array();
			
			if ( ! empty( $data['cache_meta'] ) )
			{
				$meta = json_decode( $data['cache_meta'], TRUE );
			}
			return array( 'output' => $data['cache_value'], 'meta' => $meta, 'expires' => $data['cache_expire'] );
		}
		catch ( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
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
			\IPS\Db::i()->replace( 'core_output_cache', array(
				'cache_key' => $key,
				'cache_value' => $value,
				'cache_meta' => json_encode( $meta ),
				'cache_expire' => $expire->getTimestamp()
			) );
		}
		catch( \IPS\Db\Exception $e )
		{
			\IPS\Log::debug( $e, 'guest_cache_fail' );
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Remove all expired caches
	 *
	 * @param	boolean	$truncate	Truncate the table
	 * @return	void
	 */
	public function deleteExpired( $truncate=FALSE )
	{
		try
		{
			\IPS\Db::i()->delete( 'core_output_cache', ( ! $truncate ? array( 'cache_expire<?', time() ) : NULL ) );
		}
		catch ( \IPS\Db\Exception $e ) { }
	}
		
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		try
		{
			\IPS\Db::i()->delete( 'core_output_cache' );
		}
		catch ( \IPS\Db\Exception $e ){}
	}
}
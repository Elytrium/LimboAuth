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
 * Remote Database Output Cache Storage Class
 */
class _RemoteDatabase extends \IPS\Output\Cache
{
	/**
	 * @brief Unpacked config
	 */
	protected static $config = NULL;

	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		static::getConfig();
		return ( static::$config === NULL ) ? FALSE : TRUE;
	}

	/**
	 * Unpack the config
	 *
	 * @return void
	 */
	protected static function getConfig()
	{
		if ( \defined('\IPS\OUTPUT_CACHE_METHOD_CONFIG') and \IPS\OUTPUT_CACHE_METHOD_CONFIG !== NULL )
		{
			static::$config = json_decode( \IPS\OUTPUT_CACHE_METHOD_CONFIG, TRUE );
		}
	}

	/**
	 * @brief	[ActiveRecord] Database Connection
	 * @return	\IPS\Db
	 */
	public static function db()
	{
		return \IPS\Db::i( 'remoteOutputCache', array(
			'sql_host'		=> static::$config['sql_host'],
			'sql_user'		=> static::$config['sql_user'],
			'sql_pass'		=> static::$config['sql_pass'],
			'sql_database'	=> static::$config['sql_database'],
			'sql_port'		=> static::$config['sql_port'],
			'sql_socket'	=> static::$config['sql_socket'],
			'sql_tbl_prefix'=> ( ! empty( static::$config['sql_tbl_prefix'] ) ) ? static::$config['sql_tbl_prefix'] : '',
			'sql_utf8mb4'	=> TRUE
		) );
	}

	/**
	 * Ensure the remote DB connection is closed
	 *
	 * @return void
	 */
	public function __destruct()
	{
		static::db()->close();
	}

	/**
	 * Fix any SQL issues
	 *
	 * @param \Exception $exception 	The exception object
	 * @return boolean
	 */
	protected static function handleException( $exception )
	{
		if ( $exception->getCode() == 1017 or $exception->getCode() == 1146 )
		{
			/* Table does not exist */
			static::db()->createTable( static::getSchema() );
			return TRUE;
		}
		else if ( $exception->getCode() == 1054 )
		{
			/* Column doesn't exist, so drop table and recreate */
			static::db()->dropTable( 'core_output_cache' );
			static::db()->createTable( static::getSchema() );
			return TRUE;
		}

		/* Other errors we can't auto fix */
		return FALSE;
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	array
	 * @throws \OutOfRangeException
	 */
	protected function _get( $key )
	{
		try
		{
			$data = static::db()->select( '*', 'core_output_cache', array( 'cache_key=?', $key,) )->first();
			$meta = array();
			
			if ( ! empty( $data['cache_meta'] ) )
			{
				$meta = json_decode( $data['cache_meta'], TRUE );
			}

			if ( $data['cache_expire'] <= time() )
			{
				throw new \UnderflowException;
			}

			return array( 'output' => $data['cache_value'], 'meta' => $meta, 'expires' => $data['cache_expire'] );
		}
		catch ( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( static::handleException( $e ) === TRUE )
			{
				return $this->_get( $key );
			}
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
			static::db()->replace( 'core_output_cache', array(
				'cache_key'		=> $key,
				'cache_value'	=> $value,
				'cache_meta'	=> json_encode( $meta ),
				'cache_expire'	=> $expire->getTimestamp()
			) );

			return TRUE;
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( static::handleException( $e ) === TRUE )
			{
				$this->_set( $key, $value, $meta, $expire );
			}
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
		try
		{
			static::db()->delete( 'core_output_cache', ( ! $truncate ? array( 'cache_expire<?', time() ) : NULL ) );
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( static::handleException( $e ) === TRUE )
			{
				$this->deleteExpired( $truncate );
			}
		}
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
			static::db()->delete( 'core_output_cache' );
		}
		catch ( \IPS\Db\Exception $e )
		{
			if ( static::handleException( $e ) === TRUE )
			{
				$this->clearAll();
			}
		}
	}

	/**
	 * Drop the tables
	 *
	 * @return void
	 */
	public function recreateTable()
	{
		try
		{
			static::db()->dropTable( 'core_output_cache' );
			static::db()->createTable( static::getSchema() );
		}
		catch ( \Exception $e ) { }
	}

	/**
	 * Get schema
	 *
	 * @return mixed
	 */
	protected static function getSchema()
	{
		$schema = <<<EOF
{
 "name": "core_output_cache",
        "columns": {
            "cache_key": {
                "allow_null": false,
                "auto_increment": false,
                "comment": "The key",
                "decimals": null,
                "default": "",
                "length": 100,
                "name": "cache_key",
                "type": "VARCHAR",
                "unsigned": false,
                "values": []
            },
            "cache_value": {
                "allow_null": false,
                "auto_increment": false,
                "comment": "The output HTML",
                "decimals": null,
                "default": "",
                "length": 0,
                "name": "cache_value",
                "type": "LONGTEXT",
                "unsigned": false,
                "values": []
            },
            "cache_meta": {
                "allow_null": false,
                "auto_increment": false,
                "comment": "JSON headers and meta data",
                "decimals": null,
                "default": "",
                "length": 0,
                "name": "cache_meta",
                "type": "MEDIUMTEXT",
                "unsigned": false,
                "values": []
            },
            "cache_expire": {
                "allow_null": false,
                "auto_increment": false,
                "comment": "Unix timestamp of when the cache expires",
                "decimals": null,
                "default": "",
                "length": null,
                "name": "cache_expire",
                "type": "INT",
                "unsigned": false,
                "values": []
            }
        },
        "indexes": {
            "PRIMARY": {
                "type": "primary",
                "name": "PRIMARY",
                "length": [
                    null
                ],
                "columns": [
                    "cache_key"
                ]
            },
            "cache_expire": {
                "type": "key",
                "name": "cache_expire",
                "length": [
                    null
                ],
                "columns": [
                    "cache_expire"
                ]
            }
        },
	"collation": "utf8mb4_unicode_ci",
	"engine": "InnoDB"
}
EOF;
		return json_decode( $schema, TRUE );
	}
}
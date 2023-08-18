<?php
/**
 * @brief		Settings Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Settings class
 */
class _Settings extends \IPS\Patterns\Singleton
{
	/**
	 * @brief	Singleton Instances
	 */
	protected static $instance = NULL;
	
	/**
	 * @brief	Data Store
	 */
	protected $data = NULL;
	
	/**
	 * @brief	Settings loaded?
	 */
	protected $loaded = FALSE;
	
	/**
	 * @brief	Store $INFO so we know what came from conf_global later
	 */
	protected static $confGlobal = array();
		
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	protected function __construct()
	{
		if ( file_exists( \IPS\SITE_FILES_PATH . '/conf_global.php' ) )
		{
			$allowedFields = $this->getAllowedFields();
			
			require( \IPS\SITE_FILES_PATH . '/conf_global.php' );
			if ( isset( $INFO ) )
			{
				if( isset( $INFO['board_url'] ) AND ( !isset( $INFO['base_url'] ) OR !$INFO['base_url'] ) )
				{
					$INFO['base_url']	= $INFO['board_url'];
				}
				if( isset( $INFO['base_url'] ) )
				{
					/* Upgraded boards may not have trailing slash */
					if( mb_substr( $INFO['base_url'], -1, 1 ) !== '/' )
					{
						$INFO['base_url']	= $INFO['base_url'] . '/';
					}
				}
				
				foreach( $INFO as $k => $v )
				{
					if ( ! \in_array( $k, $allowedFields ) )
					{
						unset( $INFO[ $k ] );
					}
				}

				static::$confGlobal = $INFO;
				$this->data = $INFO;
			}
		}
	}
	
	/**
	 * Fetch the allowed fields to be stored from conf_global
	 * Abstracted to one can use it as a hook point
	 *
	 * @return array
	 */
	public function getAllowedFields()
	{
		return array( 'sql_host', 'sql_database', 'sql_user', 'sql_pass', 'sql_port', 'sql_socket', 'sql_tbl_prefix', 'sql_utf8mb4', 'board_start', 'installed', 'base_url', 'guest_group', 'member_group', 'admin_group' );
	}
	
	/**
	 * Magic Method: Get
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value from the datastore
	 */
	public function __get( $key )
	{
		/* CiC hardcoded */
		if ( \IPS\CIC )
		{
			if ( \in_array( $key, array( 'xforward_matching', 'use_friendly_urls', 'htaccess_mod_rewrite', 'seo_r_on' ) ) )
			{
				return TRUE;
			}
			if ( $key == 'task_use_cron' )
			{
				return 'normal';
			}
		}
		
		/* Get normally */
		$return = parent::__get( $key );
		if ( $return === NULL and !$this->loaded )
		{
			$this->loadFromDb();
			return parent::__get( $key );
		}
		return $return;
	}
	
	/**
	 * Get from conf_global.php
	 * Useful when you need to get a value from conf_global.php without loading the DB, such as in the installer
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value
	 */
	public function getFromConfGlobal( $key )
	{	
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : NULL;
	}
	
	/**
	 * Magic Method: Isset
	 *
	 * @param	mixed	$key	Key
	 * @return	bool
	 */
	public function __isset( $key )
	{
		$return = parent::__isset( $key );
		
		if ( $return === FALSE and !$this->loaded )
		{
			$this->loadFromDb();
			return parent::__isset( $key );
		}
		
		return $return;
	}
	
	/**
	 * Load Settings
	 *
	 * @return	void
	 */
	protected function loadFromDb()
	{
		$settings = [];
		if ( isset( \IPS\Data\Store::i()->settings ) )
		{
			$settings = \IPS\Data\Store::i()->settings;
		}
		else
		{
			foreach ( \IPS\Db::i()->select( 'conf_key, conf_default, conf_value', 'core_sys_conf_settings' )->setKeyField( 'conf_key' ) as $k => $data )
			{
				$settings[ $k ] = ( $data['conf_value'] === '' ) ? $data['conf_default'] : $data['conf_value'];
			}
			\IPS\Data\Store::i()->settings = $settings;
		}

		/* We don't want to 'cache' what is in conf_global */
		$this->data = array_merge( $this->data, $settings );

		$this->loaded = TRUE;
	}

	/**
	 * Purge the current settings cache
	 *
	 * @note	While calling unset() on the datastore value purges it, if we still have settings loaded in memory a call to changeValues() causes
	 	the same values to be written, which is not desired
	 * @return	void
	 */
	public function clearCache()
	{
		unset( \IPS\Data\Store::i()->settings );
		$this->data		= static::$confGlobal;
		$this->loaded	= FALSE;
	}
	
	/**
	 * Change values
	 *
	 * @param	array	$newValues	New values
	 * @return	void
	 */
	public function changeValues( $newValues )
	{
		/* Get the current values if we don't have them already */
		if ( !$this->loaded )
		{
			$this->loadFromDb(); // Misleading method name - will load from datastore if it's available
		}
		
		/* If we want to set any of them to an empty string, we need the default value */
		$defaultValues = array();
		foreach ( $newValues as $k => $v )
		{
			if ( $v === '' )
			{
				$defaultValues[] = $k;
			}
		}
		$defaultValues	= iterator_to_array( \IPS\Db::i()->select( array( 'conf_default', 'conf_key' ), 'core_sys_conf_settings', \IPS\Db::i()->in( 'conf_key', $defaultValues ) )->setKeyField( 'conf_key' )->setValueField( 'conf_default' ) );
		$validKeys		= iterator_to_array( \IPS\Db::i()->select( 'conf_key', 'core_sys_conf_settings', \IPS\Db::i()->in( 'conf_key', array_keys( $newValues ) ) ) );
		
		/* Update the database */
		$changed = FALSE;
		foreach ( $newValues as $k => $v )
		{
			$valueToCache = isset( $defaultValues[ $k ] ) ? $defaultValues[ $k ] : $v;

			/* Make sure the key is valid */
			if( !\in_array( $k, $validKeys ) )
			{
				if ( \IPS\IN_DEV )
				{
					throw new \InvalidArgumentException( 'unknown_setting: ' . $k );
				}
				continue;
			}

			if ( $this->$k != $valueToCache )
			{
				$this->$k = $valueToCache;
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $v ), array( 'conf_key=?', $k ) );
				
				$changed = TRUE;
			}
		}

		/* Update the datastore */
		if ( $changed )
		{
			$toStore = array();
			foreach( $this->data AS $dk => $dv )
			{
				if ( ! isset( static::$confGlobal[ $dk ] ) )
				{
					/* Make sure objects are cast to string as the DB bind layer will do this automatically when saving */
					if ( \is_object( $dv ) and method_exists( $dv, '__toString' ) )
					{
						$dv = (string) $dv;
					}
					
					$toStore[ $dk ] = $dv;
				}
			}
			\IPS\Data\Store::i()->settings = $toStore;
		}
	}
}
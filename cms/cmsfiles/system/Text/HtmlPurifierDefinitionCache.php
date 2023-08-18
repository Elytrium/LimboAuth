<?php
/**
 * @brief		Definition caching for HTML Purifier not on disk
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Oct 2013
 */


/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Definition caching for HTML Purifier not on disk
 */
class HtmlPurifierDefinitionCache extends \HTMLPurifier_DefinitionCache
{
	/**
	 * Adds a definition object to the cache
	 *
	 * @param	mixed	$def	Definition to cache
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	bool
	 */
	public function add($def, $config)
	{
		return $this->set( $def, $config );
	}

	/**
	 * Unconditionally saves a definition object to the cache
	 *
	 * @param	mixed	$def	Definition to cache
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	bool
	 */
	public function set($def, $config)
	{
		/* If invalid type, just return */
		if( !$this->checkDefType( $def ) )
		{
			return;
		}

		/* Generate key and store it */
		$key	= $this->generateKey( $config );

		\IPS\Data\Store::i()->$key	= base64_encode( \serialize( $def ) );

		/* Store an array of all keys so we can implement flush() and cleanup() */
		$currentKeys			= \IPS\Data\Store::i()->htmlpurifier_definitions;
		$currentKeys[ $key ]	= $key;

		\IPS\Data\Store::i()->htmlpurifier_definitions	= $currentKeys;

		/* Return TRUE */
		return TRUE;
	}

	/**
	 * Replace an object in the cache
	 *
	 * @param	mixed	$def	Definition to cache
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	bool
	 */
	public function replace($def, $config)
	{
		return $this->set( $def, $config );
	}

	/**
	 * Retrieves a definition object from the cache
	 *
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	mixed	FALSE if not found, or the definition object
	 */
	public function get($config)
	{
		$key	= $this->generateKey( $config );

		if( isset( \IPS\Data\Store::i()->$key ) and \IPS\Data\Store::i()->$key !== 0 )
		{
			return \unserialize( base64_decode( \IPS\Data\Store::i()->$key ) );
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Removes a definition object to the cache
	 *
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	bool
	 */
	public function remove($config)
	{
		$key	= $this->generateKey( $config );

		if( isset( \IPS\Data\Store::i()->$key ) )
		{
			unset( \IPS\Data\Store::i()->$key );
		}

		return TRUE;
	}

	/**
	 * Clears all objects from cache
	 *
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	void
	 */
	public function flush($config)
	{
		if( !isset( \IPS\Data\Store::i()->htmlpurifier_definitions ) )
		{
			\IPS\Data\Store::i()->htmlpurifier_definitions	= array();
		}

		foreach( \IPS\Data\Store::i()->htmlpurifier_definitions as $key )
		{
			if( isset( \IPS\Data\Store::i()->$key ) )
			{
				unset( \IPS\Data\Store::i()->$key );
			}
		}

		return;
	}

	/**
	 * Clears all expired (older version or revision) objects from cache
	 *
	 * @param	mixed	$config	HTML Purifier configuration
	 * @return	void
	 */
	public function cleanup($config)
	{
		return $this->flush( $config );
	}
}
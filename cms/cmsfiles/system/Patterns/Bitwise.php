<?php
/**
 * @brief		Bitwise Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Apr 2013
 */

namespace IPS\Patterns;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Bitwise Class
 */
class _Bitwise implements \ArrayAccess
{
	/**
	 * @brief	Values
	 */
	public $values = array();
	
	/**
	 * @brief	Original Values
	 */
	public $originalValues = 0;
	
	/**
	 * @brief	Keys
	 */
	protected $keys = array();
	
	/**
	 * @brief	Keys Lookup
	 */
	protected $lookup = array();
	
	/**
	 * @brief	Callback when value is changed
	 */
	protected $callback = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	array			$values		The numbers
	 * @param	array			$keys		Multi-dimensional array. Keys match keys in $value, value is associative array with keys and representive value, or just a list of keys in order
	 * @param	callback|null	$callback	Callback when value is changed
	 * @return	void
	 */
	public function __construct( array $values, array $keys, $callback=NULL )
	{
		$this->values = $values;
		$this->originalValues = $values;
		
		foreach ( $keys as $groupKey => $fields )
		{
			$i = 1;
			foreach ( $fields as $k => $v )
			{
				if ( \is_string( $v ) )
				{
					$this->keys[ $groupKey ][ $v ] = $i;
					$this->lookup[ $k ] = $groupKey;
				}
				else
				{
					while( $v != $i )
					{
						$this->keys[ $groupKey ][] = $i;
						$this->lookup[] = $groupKey;
						$i *= 2;
					}
					
					$this->keys[ $groupKey ][ $k ] = $v;
					$this->lookup[ $k ] = $groupKey;
				}
				
				$i *= 2;
			}
		}
				
		$this->callback = $callback;
	}
		
	/**
	 * Offset exists?
	 *
	 * @param	string	$offset	Offset
	 * @return	bool
	 */
	public function offsetExists( $offset )
	{
		return isset( $this->lookup[ $offset ] );
	}
	
	/**
	 * Get offset
	 *
	 * @param	string	$offset	Offset
	 * @return	bool
	 */
	public function offsetGet( $offset )
	{
		$group = $this->lookup[ $offset ];
		return (bool) ( $this->values[ $group ] & $this->keys[ $group ][ $offset ] );
	}
	
	/**
	 * Set offset
	 *
	 * @param	string	$offset	Offset
	 * @param	bool	$value	Value
	 * @return	void
	 */
	public function offsetSet( $offset, $value )
	{
		if ( $this->callback !== NULL )
		{
			$callback = $this->callback;
			$callback( $offset, $value );
		}
		
		$group = $this->lookup[ $offset ];
		if ( $value )
		{
			$this->values[ $group ] |= $this->keys[ $group ][ $offset ];
		}
		else
		{
			$this->values[ $group ] &= ~$this->keys[ $group ][ $offset ];
		}
	}
	
	/**
	 * Unset offset
	 *
	 * @param	string	$offset	Offset
	 * @return	void
	 */
	public function offsetUnset( $offset )
	{
		return $this->offsetSet( $offset, FALSE );
	}
	
	/** 
	 * Get array
	 *
	 * @return	array
	 */
	public function asArray()
	{	
		$return = array();
						
		foreach ( $this->keys as $group )
		{
			foreach ( $group as $k => $v )
			{
				$return[ $k ] = $this->offsetGet( $k );
			}
		}
		
		return $return;
	}
}
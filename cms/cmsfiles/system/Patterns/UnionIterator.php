<?php
/**
 * @brief		Union Iterator Pattern
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Nov 2014
 */

namespace IPS\Patterns;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Union Iterator Pattern
 */
class _UnionIterator implements \Iterator, \Countable
{
	/**
	 * @brief	Iterators
	 */
	protected $iterators;
	
	/**
	 * @brief	Current lowest value
	 */
	protected $currentIteratorKey;
	
	/**
	 * @brief	Order Direction
	 */
	protected $orderDirection;
	
	/**
	 * Constructor
	 *
	 * @param	string	$orderDirection	"asc" or "desc"
	 * @return	void
	 */
	public function __construct( $orderDirection )
	{
		$this->iterators = new \SplObjectStorage;
		$this->orderDirection = $orderDirection;
	}
	
	/**
	 * Attach Iterator
	 *
	 * @param	\Traverable	$iterator	Iterator
	 * @param	mixed		$key		The name of the property which contains the value used to compare positions
	 * @return	void
	 */
	public function attachIterator( \Traversable $iterator, $key )
	{		
    	$this->iterators->attach( $iterator, $key );
	}

	/**
	 * Rewind
	 *
	 * @return	void
	 */
	public function rewind()
	{		
    	foreach ( $this->iterators as $iterator )
    	{
	    	$iterator->rewind();
    	}
    	$this->currentLowestValue = NULL;
    	$this->next();
	}
	
	/**
	 * Get current row
	 *
	 * @return	array
	 */
	public function current()
	{
		foreach ( $this->iterators as $k => $iterator )
		{
			if ( $k === $this->currentIteratorKey )
			{
				$return = $iterator->current();
				$iterator->next();
				return $return;
			}
		}
	}
	
	/**
	 * Get current key
	 *
	 * @return	mixed
	 */
	public function key()
	{
		return NULL;
	}
	
	/**
	 * Fetch next result
	 *
	 * @return	void
	 */
	public function next()
	{
		$this->currentIteratorKey = NULL;
		$currentLowestValue = NULL;
		
		$values = array();
		foreach ( $this->iterators as $k => $iterator )
		{
			if ( $iterator->valid() )
			{
				$col = $this->iterators->getInfo();
				$current = $iterator->current();
				if ( \is_array( $current ) )
				{
					$value = $current[ $col ];
				}
				else
				{
					$value = $current->$col;
				}
				
				if ( $value instanceof \IPS\DateTime )
				{
					$value = $value->getTimestamp();
				}
				
				if ( $currentLowestValue === NULL or ( $this->orderDirection === 'asc' and $value < $currentLowestValue ) or ( $this->orderDirection === 'desc' and $value > $currentLowestValue ) )
				{
					$currentLowestValue = $value;
					$this->currentIteratorKey = $k;
				}
			}
		}
	}
	
	/**
	 * Is the current row valid?
	 *
	 * @return	bool
	 */
	public function valid()
	{
		return $this->currentIteratorKey !== NULL;
	}
	
	/**
	 * Get count
	 *
	 * @return	void
	 */
	public function count()
	{		
		$count = 0;
    	foreach ( $this->iterators as $iterator )
    	{
	    	$count += \count( $iterator );
    	}
    	return $count;
	}
}
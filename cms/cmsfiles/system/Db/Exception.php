<?php
/**
 * @brief		Exception class for database errors
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Db;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Exception class for database errors
 */
class _Exception extends \RuntimeException
{
	/**
	 * @brief	Query
	 */
	public $query;
	
	/**
	 * @brief	Binds
	 */
	public $binds = array();
	
	/**
	 * Constructor
	 *
	 * @param	string			$message	MySQL Error message
	 * @param	int				$code		MySQL Error Code
	 * @param	\Exception|NULL	$previous	Previous Exception
	 * @param	string|NULL		$query		MySQL Query that caused exception
	 * @param	array			$binds		Binds for query
	 * @return	void
	 * @see		<a href='https://bugs.php.net/bug.php?id=30471'>Recursion "bug" with var_export()</a>
	 */
	public function __construct( $message = null, $code = 0, $previous = null, $query=NULL, $binds=array() )
	{
		/* Store these for the extraLogData() method */
		$this->query = $query;
		$this->binds = $binds;
				
		return parent::__construct( $message, $code, $previous );
	}
	
	/**
	 * Is this a server issue?
	 *
	 * @return	bool
	 */
	public function isServerError()
	{
		/* Low-end server errors */
		if ( $this->getCode() < 1046 or \in_array( $this->getCode(), array( 1129, 1130, 1194, 1195, 1203 ) ) )
		{
			return TRUE;
		}
		
		/* Low-end client errors */
		if ( $this->getCode() >= 2000 and $this->getCode() < 2029 )
		{
			return TRUE;
		}
		
		/* Our custom error code */
		if ( $this->getCode() === -1 )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Additional log data?
	 *
	 * @return	string
	 */
	public function extraLogData()
	{
		return \IPS\Db::_replaceBinds( $this->query, $this->binds );
	}
}
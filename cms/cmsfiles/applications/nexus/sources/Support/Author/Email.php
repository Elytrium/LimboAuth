<?php
/**
 * @brief		Support Author Model - Email
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		15 Apr 2014
 */

namespace IPS\nexus\Support\Author;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Author Model - Email
 */
class _Email
{
	/**
	 * @brief	Email Address
	 */
	protected $email;
	
	/**
	 * Constructor
	 *
	 * @param	string	$email	Email address
	 * @return	void
	 */
	public function __construct( $email )
	{
		$this->email = $email;
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function name()
	{
		return $this->email;
	}
		
	/**
	 * Get photo
	 *
	 * @return	string
	 */
	public function photo()
	{
		return NULL;
	}
	
	/**
	 * Get email
	 *
	 * @return	string
	 */
	public function email()
	{
		return $this->email;
	}
	
	/**
	 * Get url
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return NULL;
	}
	
	/**
	 * Get meta data
	 *
	 * @return	array
	 */
	public function meta()
	{		
		return array( );
	}
	
	/**
	 * Get latest invoices
	 *
	 * @param	int		$limit	Number of invoices
	 * @param	bool	$count	Return the total count
	 * @return	\IPS\Patterns\ActiveRecordIterator|NULL|int
	 */
	public function invoices( $limit = 10, $count = FALSE )
	{		
		return NULL;
	}
	
	/**
	 * Support Requests
	 *
	 * @param	int							$limit		Number to get
	 * @param	\IPS\nexus\Support\Request	$exclude	A request to exclude
	 * @param	string						$order		Order clause
	 * @param	bool						$count		Return the total count
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public function supportRequests( $limit, \IPS\nexus\Support\Request $exclude = NULL, $order='r_started DESC', $count = FALSE )
	{
		$where = array( array( 'r_email=?', $this->email ) );
		if ( $exclude )
		{
			$where[] = array( 'r_id<>?', $exclude->id );
		}

		if( $count === TRUE )
		{
			return \IPS\nexus\Support\Request::getItemsWithPermission( $where, $order, NULL, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC, 0, NULL, FALSE, FALSE, FALSE, TRUE );
		}
		
		return \IPS\nexus\Support\Request::getItemsWithPermission( $where, $order, $limit, 'read', \IPS\Content\Hideable::FILTER_AUTOMATIC );
	}
}
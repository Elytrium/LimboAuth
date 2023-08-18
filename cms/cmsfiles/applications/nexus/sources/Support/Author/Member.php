<?php
/**
 * @brief		Support Author Model - Member
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Apr 2014
 */

namespace IPS\nexus\Support\Author;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Author Model - Member
 */
class _Member
{
	/**
	 * @brief	Customer object
	 */
	protected $customer;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\nexus\Customer	$customer	Customer object
	 * @return	void
	 */
	public function __construct( \IPS\nexus\Customer $customer )
	{
		$this->customer = $customer;
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function name()
	{
		return $this->customer->cm_name;
	}
	
	/**
	 * Get email
	 *
	 * @return	string
	 */
	public function email()
	{
		return $this->customer->email;
	}
		
	/**
	 * Get photo
	 *
	 * @return	string
	 */
	public function photo()
	{
		return \IPS\Member::photoUrl( $this->customer->_data, TRUE, FALSE, FALSE );
	}
	
	/**
	 * Get url
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return $this->customer->acpUrl();
	}
	
	/**
	 * Get meta data
	 *
	 * @return	array
	 */
	public function meta()
	{
		if( !\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view_statistics' ) )
		{
			return array( $this->customer->email );
		}

		return array(
			$this->customer->email,
			\IPS\Member::loggedIn()->language()->addToStack( 'transaction_customer_since', FALSE, array( 'sprintf' => array( $this->customer->joined->localeDate() ) ) ),
			\IPS\Member::loggedIn()->language()->addToStack( 'transaction_spent', FALSE, array( 'sprintf' => array( $this->customer->totalSpent() ) ) ),
		);
	}
	
	/**
	 * Get nuber of notes
	 *
	 * @return	array
	 */
	public function noteCount()
	{		
		return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_notes', array( 'note_member=?', $this->customer->member_id ) )->first();
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
		if( $count === TRUE )
		{
			return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', array( 'i_member=?', $this->customer->member_id ) )->first();
		}

		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_invoices', array( 'i_member=?', $this->customer->member_id ), 'i_date DESC', $limit ), 'IPS\nexus\Invoice' );
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
		$where = array( array( 'r_member=?', $this->customer->member_id ) );
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
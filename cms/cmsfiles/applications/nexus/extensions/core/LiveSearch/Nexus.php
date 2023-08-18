<?php
/**
 * @brief		ACP Live Search Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Sep 2014
 */

namespace IPS\nexus\extensions\core\LiveSearch;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Live Search Extension
 */
class _Nexus
{	
	/**
	 * Check we have access
	 *
	 * @return	bool
	 */
	public function hasAccess()
	{
		return	\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'invoices_manage' )
		or 		\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_manage' )
		or		\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_view' )
		or		\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_manage' )
		or		\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view' )
		or		\IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'lkeys_view' );
	}
	
	/**
	 * Get the search results
	 *
	 * @param	string	$searchTerm	Search Term
	 * @return	array 	Array of results
	 */
	public function getResults( $searchTerm )
	{
		$results = array();
		
		/* Numeric */
		if ( \is_numeric( $searchTerm ) )
		{
			/* Invoice */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'invoices_manage' ) )
			{
				try
				{
					$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->invoice( \IPS\nexus\Invoice::load( $searchTerm ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			/* Transaction */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_manage' ) )
			{
				try
				{
					$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->transaction( \IPS\nexus\Transaction::load( $searchTerm ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			/* Purchase */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_view' ) )
			{
				try
				{
					$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->purchase( \IPS\nexus\Purchase::load( $searchTerm ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
						
			/* Support */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_manage' ) )
			{
				try
				{
					$supportRequest = \IPS\nexus\Support\Request::load( $searchTerm );
					if ( $supportRequest->canView() )
					{
						$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->support( $supportRequest );
					}
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			/* Customer */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view' ) )
			{
				try
				{
					$customer = \IPS\nexus\Customer::load( $searchTerm );
					if ( $customer->member_id )
					{
						$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->customer( $customer );
					}
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		/* Textual */
		else
		{
			/* License Key */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'lkeys_view' ) )
			{
				try
				{
					$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->licensekey( \IPS\nexus\Purchase\LicenseKey::load( $searchTerm ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			
			/* Customers */
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_view' ) )
			{
				if( \IPS\core\extensions\core\LiveSearch\Members::canPerformInlineSearch() )
				{
					$query = \IPS\Db::i()->select( '*', 'nexus_customers', array( \IPS\Db::i()->like( array( 'core_members.name', 'core_members.email', "CONCAT( LOWER( nexus_customers.cm_first_name ), ' ', LOWER( nexus_customers.cm_last_name ) )" ), $searchTerm, TRUE, TRUE, TRUE ) ), NULL, 50 )->join( 'core_members', 'core_members.member_id=nexus_customers.member_id' );
				}
				else
				{
					$query = \IPS\Db::i()->select( '*', 'nexus_customers', array( \IPS\Db::i()->like( array( 'core_members.name', 'core_members.email', 'nexus_customers.cm_first_name', 'nexus_customers.cm_last_name', "CONCAT( LOWER( nexus_customers.cm_first_name ), ' ', LOWER( nexus_customers.cm_last_name ) )" ), $searchTerm, TRUE, TRUE, FALSE ) ), NULL, 50 )->join( 'core_members', 'core_members.member_id=nexus_customers.member_id' );
				}

				foreach ( new \IPS\Patterns\ActiveRecordIterator( $query, 'IPS\nexus\Customer' ) as $customer )
				{
					$results[] = \IPS\Theme::i()->getTemplate('livesearch', 'nexus')->customer( $customer );
				}
			}
		}
		
		/* For either, search for transaction gateway IDs */
		foreach ( \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_gw_id=?', $searchTerm ) ) as $transactionData )
		{
			$results[] = \IPS\Theme::i()->getTemplate( 'livesearch', 'nexus' )->transaction( \IPS\nexus\Transaction::constructFromData( $transactionData ) );
		}
		
		/* Return */		
		return $results;
	}
	
	/**
	 * Is default for current page?
	 *
	 * @return	bool
	 */
	public function isDefault()
	{
		return \IPS\Dispatcher::i()->application->directory == 'nexus';
	}
}
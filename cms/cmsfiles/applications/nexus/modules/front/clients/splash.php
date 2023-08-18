<?php
/**
 * @brief		Controller to redirect /clients to something sensible
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 May 2017
 */

namespace IPS\nexus\modules\front\clients;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Controller to redirect /clients to something sensible
 */
class _splash extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$where = array( array( 'ps_member=?', \IPS\Member::loggedIn()->member_id ) );
			$parentContacts = \IPS\nexus\Customer::loggedIn()->parentContacts();
			if ( \count( $parentContacts ) )
			{
				$or = array();
				foreach ( $parentContacts as $contact )
				{
					$where[0][0] .= ' OR ' . \IPS\Db::i()->in( 'ps_id', $contact->purchaseIds() );
				}
			}
			$where[] = array( 'ps_show=1' );
			
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_purchases', $where )->first() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=purchases', 'front', 'clientspurchases' ) );
			}
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=invoices', 'front', 'clientsinvoices' ) );
	}
}
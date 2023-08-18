<?php
/**
 * @brief		Billing Agreements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Dec 2015
 */

namespace IPS\nexus\modules\front\clients;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Billing Agreements
 */
class _billingagreements extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X321/1', 403, '' );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'clients.css', 'nexus' ) );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=billingagreements', 'front', 'clientsbillingagreements' ), \IPS\Member::loggedIn()->language()->addToStack('client_billing_agreements') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('client_billing_agreements');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		
		if ( $output = \IPS\MFA\MFAHandler::accessToArea( 'nexus', 'BillingAgreements', \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=billingagreements', 'front', 'clientsbillingagreements' ) ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->billingAgreements( array() ) . $output;
			return;
		}
		
		parent::execute();
	}
	
	/**
	 * View List
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$billingAgreements = array();
		
		$where = array( 'ba_member=?', \IPS\Member::loggedIn()->member_id );
		$parentContacts = \IPS\nexus\Customer::loggedIn()->parentContacts( array( 'billing=1' ) );
		if ( \count( $parentContacts ) )
		{
			$or = array();
			foreach ( array_keys( iterator_to_array( $parentContacts ) ) as $id )
			{
				$where[0] .= ' OR ba_member=?';
				$where[] = $id;
			}
		}
		
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_billing_agreements', $where ), 'IPS\nexus\Customer\BillingAgreement' ) as $billingAgreement )
		{			
			try
			{
				$status = $billingAgreement->status();
			}
			catch ( \Exception $e )
			{
				$status = NULL;
			}
			
			try
			{
				$term = $billingAgreement->term();
			}
			catch ( \Exception $e )
			{
				$term = NULL;
			}
			
			$billingAgreements[] = array(
				'status'	=> $status,
				'id'		=> $billingAgreement->gw_id,
				'term'		=> $term,
				'url'		=> $billingAgreement->url()
			);
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->billingAgreements( $billingAgreements );
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	protected function view()
	{
		/* Load Billing Agreement */
		try
		{
			$billingAgreement = \IPS\nexus\Customer\BillingAgreement::loadAndCheckPerms( \IPS\Request::i()->id );
			$billingAgreement->status(); // Just to make the API call so we can catch the error if there is one
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X320/4', 404, '' );
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '4X321/5', 500, '', array(), $e->getName() );
		}
		
		/* Get associated purchases */
		$purchases = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_billing_agreement=?', $billingAgreement->id ) ), 'IPS\nexus\Purchase' ) as $purchase )
		{
			$purchases[0][ $purchase->id ] = $purchase;
		}
		
		/* Transactions */
		$currentPage = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		$perPage = 25;
		$invoices = new \IPS\Patterns\ActiveRecordIterator(
			\IPS\Db::i()->select(
				'*',
				'nexus_invoices',
				array( 'i_id IN(?)', \IPS\Db::i()->select( 't_invoice', 'nexus_transactions', array( 't_billing_agreement=?', $billingAgreement->id ) ) ),
				'i_date DESC',
				array( ( $currentPage - 1 ) * $perPage, $perPage )
			),
			'IPS\nexus\Invoice'
		);
		$invoicesCount = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', array( 'i_id IN(?)', \IPS\Db::i()->select( 't_invoice', 'nexus_transactions', array( 't_billing_agreement=?', $billingAgreement->id ) ) ) )->first();
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $billingAgreement->url(), ceil( $invoicesCount / $perPage ), $currentPage, $perPage );
		
		\IPS\Output::i()->breadcrumb[] = array( $billingAgreement->url(), $billingAgreement->gw_id );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->billingAgreement( $billingAgreement, $purchases, $invoices, $pagination );
	}
	
	/**
	 * Act
	 *
	 * @return	void
	 */
	protected function act()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Check act */
		$act = \IPS\Request::i()->act;
		if ( !\in_array( $act, array( 'suspend', 'reactivate', 'cancel' ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '3X321/3', 403, '' );
		}
		
		/* Load Billing Agreement */
		try
		{
			$billingAgreement = \IPS\nexus\Customer\BillingAgreement::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X321/2', 404, '' );
		}
		
		/* Perform Action */
		try
		{
			$billingAgreement->$act();
			
			\IPS\Output::i()->redirect( $billingAgreement->url() );
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error_public', '3X321/4', 500, '' );
		}
	}
}
<?php
/**
 * @brief		Billing Agreememts
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		16 Dec 2015
 */

namespace IPS\nexus\modules\admin\payments;

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
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'billingagreements_view' );
		parent::execute();
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Load */
		try
		{
			$billingAgreement = \IPS\nexus\Customer\BillingAgreement::load( \IPS\Request::i()->id );
			
			if ( !$billingAgreement->canceled and $billingAgreement->status() == $billingAgreement::STATUS_CANCELED )
			{
				$billingAgreement->canceled = TRUE;
				$billingAgreement->next_cycle = NULL;
				$billingAgreement->save();
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X320/1', 404, '' );
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error_public', '4X320/9', 500, 'billing_agreement_error', array(), $e->getName() );
		}
		
		/* Show */
		try
		{
			/* Purchases */
			$purchases = NULL;
			if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'purchases_view' ) and ( !isset( \IPS\Request::i()->table ) ) )
			{
				$purchases = \IPS\nexus\Purchase::tree( $billingAgreement->acpUrl(), array( array( 'ps_billing_agreement=?', $billingAgreement->id ) ), 'ba' );
			}
			
			/* Transactions */
			$transactions = NULL;
			if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_manage' ) and ( !isset( \IPS\Request::i()->table ) ) )
			{
				$transactions = \IPS\nexus\Transaction::table( array( array( 't_billing_agreement=? AND t_status<>?', $billingAgreement->id, \IPS\nexus\Transaction::STATUS_PENDING ) ), $billingAgreement->acpUrl(), 'ba' );
				$transactions->limit = 50;
				foreach ( $transactions->include as $k => $v )
				{
					if ( \in_array( $v, array( 't_method', 't_member' ) ) )
					{
						unset( $transactions->include[ $k ] );
					}
				}
			}
			
			/* Action Buttons */
			if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'billingagreements_manage' ) )
			{
				if ( $billingAgreement->status() == $billingAgreement::STATUS_ACTIVE )
				{
					\IPS\Output::i()->sidebar['actions']['refresh'] = array(
						'icon'	=> 'refresh',
						'title'	=> 'billing_agreement_check',
						'link'	=> $billingAgreement->acpUrl()->setQueryString( array( 'do' => 'act', 'act' => 'refresh' ) )->csrf(),
						'data'	=> array( 'confirm' => '' )
					);

					\IPS\Output::i()->sidebar['actions']['suspend'] = array(
						'icon'	=> 'times',
						'title'	=> 'billing_agreement_suspend',
						'link'	=> $billingAgreement->acpUrl()->setQueryString( array( 'do' => 'act', 'act' => 'suspend' ) )->csrf(),
						'data'	=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('billing_agreement_suspend_confirm') )
					);
				}
				elseif ( $billingAgreement->status() == $billingAgreement::STATUS_SUSPENDED )
				{
					\IPS\Output::i()->sidebar['actions']['reactivate'] = array(
						'icon'	=> 'check',
						'title'	=> 'billing_agreement_reactivate',
						'link'	=> $billingAgreement->acpUrl()->setQueryString( array( 'do' => 'act', 'act' => 'reactivate' ) )->csrf(),
						'data'	=> array( 'confirm' => '' )
					);
				}
				if ( $billingAgreement->status() != $billingAgreement::STATUS_CANCELED )
				{
					\IPS\Output::i()->sidebar['actions']['cancel'] = array(
						'icon'	=> 'times-circle',
						'title'	=> 'billing_agreement_cancel',
						'link'	=> $billingAgreement->acpUrl()->setQueryString( array( 'do' => 'act', 'act' => 'cancel' ) )->csrf(),
					);
				}
			}
							
			/* Display */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'billing_agreement_id', FALSE, array( 'sprintf' => array( $billingAgreement->gw_id ) ) );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'billingagreements' )->view( $billingAgreement, $purchases, $transactions );
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/2', 500, '', array(), $e->getName() );
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/3', 500 );
		}
	}
	
	/**
	 * Reconcile - resets next_cycle date
	 *
	 * @return	void
	 */
	public function reconcile()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Load */
		try
		{
			$billingAgreement = \IPS\nexus\Customer\BillingAgreement::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X320/5', 404, '' );
		}
		
		/* Reconcile */
		try
		{
			$billingAgreement->next_cycle = $billingAgreement->nextPaymentDate();
			$billingAgreement->save();
			
			\IPS\Output::i()->redirect( $billingAgreement->acpUrl() );
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/6', 500, '', array(), $e->getName() );
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/7', 500 );
		}
	}
	
	/**
	 * Suspend/Reactivate/Cancel
	 *
	 * @return	void
	 */
	public function act()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Check act */
		$act = \IPS\Request::i()->act;
		if ( !\in_array( $act, array( 'suspend', 'reactivate', 'cancel', 'refresh' ) ) )
		{
			\IPS\Output::i()->error( 'node_error', '3X320/8', 403, '' );
		}
		
		/* Load */
		try
		{
			$billingAgreement = \IPS\nexus\Customer\BillingAgreement::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X320/8', 404, '' );
		}
		
		/* Perform Action */
		try
		{
			$billingAgreement->$act();
			
			\IPS\Output::i()->redirect( $billingAgreement->acpUrl() );
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/9', 500, '', array(), $e->getName() );
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( 'billing_agreement_error', '1X320/A', 500 );
		}
	}
}
<?php
/**
 * @brief		Purchases
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		06 May 2014
 */

namespace IPS\nexus\modules\front\clients;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Purchases
 */
class _purchases extends \IPS\Dispatcher\Controller
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
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X212/6', 403, '' );
		}
		
		/* Purchases breadcrumb */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=purchases', 'front', 'clientspurchases' ), \IPS\Member::loggedIn()->language()->addToStack('client_purchases') );
		
		/* Load Purchase */
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$this->purchase = \IPS\nexus\Purchase::load( \IPS\Request::i()->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2X212/1', 404, '' );
			}
			if ( !$this->purchase->canView() )
			{
				\IPS\Output::i()->error( 'no_module_permission', '2X212/2', 403, '' );
			}
			
			\IPS\Output::i()->breadcrumb[] = array( $this->purchase->url(), $this->purchase->name );
			\IPS\Output::i()->title = $this->purchase->name;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('client_purchases');
			if ( isset( \IPS\Request::i()->do ) )
			{
				\IPS\Output::i()->error( 'node_error', '2X212/3', 403, '' );
			}
		}
		
		/* Execute */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'clients.css', 'nexus' ) );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		parent::execute();
	}

	/**
	 * View List
	 *
	 * @return	void
	 */
	protected function manage()
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
		
		/* Get only the purchases from active applications */
		$where[] = array( "ps_app IN('" . implode( "','", array_keys( \IPS\Application::enabledApplications() ) ) . "')" );

		$purchases = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', $where, 'ps_active DESC, ps_expire DESC, ps_start DESC' ), 'IPS\nexus\Purchase' ) as $purchase )
		{
			$purchases[ $purchase->parent ][ $purchase->id ] = $purchase;
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->purchases( $purchases );
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	public function view()
	{		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->purchase( $this->purchase );
	}
	
	/**
	 * Extra
	 *
	 * @return	void
	 */
	protected function extra()
	{
		if ( $output = $this->purchase->clientAreaAction() )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = $output;
			}
			else
			{
				\IPS\Output::i()->output = $output;
			}
		}
		else
		{
			\IPS\Output::i()->redirect( $this->purchase->url() );
		}
	}
	
	/**
	 * Renew
	 *
	 * @return	void
	 */
	protected function renew()
	{
		$cycles = $this->purchase->canRenewUntil( NULL, TRUE );
		if ( $cycles === FALSE )
		{
			\IPS\Output::i()->error( 'you_cannot_renew', '2X212/4', 403, '' );
		}
		elseif ( $cycles === 1 )
		{
			\IPS\Session::i()->csrfCheck();
		}
		elseif ( isset( \IPS\Request::i()->cycles ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
		{
			$_cycles = \intval( \IPS\Request::i()->cycles );
			if ( $_cycles >= 1 and ( $cycles === TRUE or $_cycles <= $cycles ) )
			{
				$cycles = $_cycles;
			}
		}
		
		$term = $this->purchase->renewals->getTerm();
		if ( $term['term'] > 1 )
		{
			$suffix = '&times; ' . $this->purchase->renewals->getTermUnit();
		}
		else
		{
			switch( $term['unit'] )
			{
				case 'd':
					$suffix = \IPS\Member::loggedIn()->language()->addToStack('days');
					break;
				case 'm':
					$suffix = \IPS\Member::loggedIn()->language()->addToStack('months');
					break;
				case 'y':
					$suffix = \IPS\Member::loggedIn()->language()->addToStack('years');
					break;
			}
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'continue' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Number( 'renew_for', 1, TRUE, array( 'min' => 1, 'max' => $cycles === TRUE ? NULL : $cycles ), NULL, NULL, $suffix ) );
		if( $values = $form->values() or $cycles === 1 )
		{
			if ( $pendingInvoice = $this->purchase->invoice_pending and $pendingInvoice->status === $pendingInvoice::STATUS_PENDING )
			{
				if ( \count( $pendingInvoice->items ) === 1 )
				{
					foreach ( $pendingInvoice->items as $item )
					{
						if ( $item instanceof \IPS\nexus\Invoice\Item\Renewal and $item->id === $this->purchase->id and $item->quantity === ( $cycles === 1 ? 1 : $values['renew_for'] ) )
						{
							\IPS\Output::i()->redirect( $pendingInvoice->checkoutUrl() );
						}
					}
				}
				
				$pendingInvoice->status = $pendingInvoice::STATUS_CANCELED;
				$pendingInvoice->save();
				$pendingInvoice->member->log( 'invoice', array( 'type' => 'status', 'new' => 'canc', 'id' => $pendingInvoice->id, 'title' => $pendingInvoice->title ) );
			}
						
			$invoice = new \IPS\nexus\Invoice;
			$invoice->member = \IPS\nexus\Customer::loggedIn();
			$invoice->currency = $this->purchase->renewals->cost->currency;
			$invoice->addItem( \IPS\nexus\Invoice\Item\Renewal::create( $this->purchase, $cycles === 1 ? 1 : $values['renew_for'] ) );
			$invoice->save();
			
			$this->purchase->invoice_pending = $invoice;
			$this->purchase->save();
			
			\IPS\Output::i()->redirect( $invoice->checkoutUrl() );
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 */
	protected function cancel()
	{
		\IPS\Session::i()->csrfCheck();
						
		if ( !$this->purchase->canCancel() )
		{
			\IPS\Output::i()->error( 'you_cannot_cancel', '2X212/5', 403, '' );
		}
		
		$this->purchase->member->log( 'purchase', array( 'type' => 'info', 'id' => $this->purchase->id, 'name' => $this->purchase->name, 'info' => 'remove_renewals' ) );
		
		/* If we have a pending renewal invoice, cancel it (as at this point, we need to reactivate instead) */
		if ( $this->purchase->invoice_pending )
		{
			$this->purchase->invoice_pending->status = \IPS\nexus\Invoice::STATUS_CANCELED; # The constant has a typo and it make me sad
			$this->purchase->invoice_pending->save();
			
			$this->purchase->invoice_pending = NULL;
		}
		
		$this->purchase->renewals = NULL;
		$this->purchase->can_reactivate = TRUE;
		$this->purchase->save();
		
		if ( $ref = \IPS\Request::i()->referrer( FALSE, TRUE ) )
		{
			\IPS\Output::i()->redirect( $ref );
		}
		\IPS\Output::i()->redirect( $this->purchase->url() );
	}
}
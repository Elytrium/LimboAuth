<?php
/**
 * @brief		Invoices
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
 * Invoices
 */
class _invoices extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{	
		/* Load Invoice */
		if ( isset( \IPS\Request::i()->id ) )
		{
			if ( isset( \IPS\Request::i()->printout ) )
			{
				\IPS\Request::i()->do = 'printout';
			}
			
			if ( \IPS\Member::loggedIn()->member_id )
			{
				try
				{
					$this->invoice = \IPS\nexus\Invoice::loadAndCheckPerms( \IPS\Request::i()->id );
				}
				catch ( \OutOfRangeException $e )
				{
					\IPS\Output::i()->error( 'node_error', '2X215/1', 404, '' );
				}
			}
			else
			{
				/* Prevent the vid key from being exposed in referrers */
				\IPS\Output::i()->sendHeader( "Referrer-Policy: origin" );

				$key = isset( \IPS\Request::i()->key ) ? \IPS\Request::i()->key : ( isset( \IPS\Request::i()->cookie['guestTransactionKey'] ) ? \IPS\Request::i()->cookie['guestTransactionKey'] : NULL );
				$this->invoice = \IPS\nexus\Invoice::load( \IPS\Request::i()->id );

				if( $this->invoice->member->member_id or !$key or !isset( $this->invoice->guest_data['guestTransactionKey'] ) or !\IPS\Login::compareHashes( $key, $this->invoice->guest_data['guestTransactionKey'] ) )
				{
					\IPS\Output::i()->error( 'no_module_permission_guest', '2X215/6', 404, '' );
				}

				/* Do not cache this guest view invoice */
				\IPS\Output::i()->pageCaching = FALSE;
			}
				
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=invoices', 'front', 'clientsinvoices' ), \IPS\Member::loggedIn()->language()->addToStack('client_invoices') );
			\IPS\Output::i()->breadcrumb[] = array( $this->invoice->url(), $this->invoice->title );
			\IPS\Output::i()->title = $this->invoice->title;
		}
		else
		{
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->error( 'no_module_permission_guest', '2X215/3', 403, '' );
			}
		
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('client_invoices');
			if ( isset( \IPS\Request::i()->do ) )
			{
				\IPS\Output::i()->error( 'node_error', '2X215/2', 403, '' );
			}
		}
		
		/* Execute */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'clients.css', 'nexus' ) );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		parent::execute();
	}
	
	/**
	 * @brief Invoices Per Page
	 */
	protected static $invoicesPerPage = 25;

	/**
	 * View List
	 *
	 * @return	void
	 */
	protected function manage()
	{	
		$where = array( 'i_member=?', \IPS\Member::loggedIn()->member_id );
		$parentContacts = \IPS\nexus\Customer::loggedIn()->parentContacts( array( 'billing=1' ) );
		if ( \count( $parentContacts ) )
		{
			$or = array();
			foreach ( array_keys( iterator_to_array( $parentContacts ) ) as $id )
			{
				$where[0] .= ' OR i_member=?';
				$where[] = $id;
			}
		}
		
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_invoices', $where )->first();
		$page = isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1;
		
		if ( $page < 1 )
		{
			$page = 1;
		}
		
		$pages = ( $count > 0 ) ? ceil( $count / static::$invoicesPerPage ) : 1;
		
		if ( $page > $pages )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=clients&controller=invoices", 'front', 'clientsinvoices' ) );
		}
		
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( \IPS\Http\Url::internal( "app=nexus&module=clients&controller=invoices", 'front', 'clientsinvoices' ), $pages, $page, static::$invoicesPerPage );
				
		$invoices = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_invoices', $where, 'i_date DESC', array( ( $page - 1 ) * static::$invoicesPerPage, static::$invoicesPerPage ) ), 'IPS\nexus\Invoice' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->invoices( $invoices, $pagination );
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	public function view()
	{
		$shipments = $this->invoice->shipments();		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->invoice( $this->invoice );
	}
	
	/**
	 * PO Number
	 *
	 * @return	void
	 */
	public function poNumber()
	{		
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Text( 'invoice_po_number', $this->invoice->po, FALSE, array( 'maxLength' => 255 ) ) );
		if ( $values = $form->values() )
		{
			$this->invoice->po = $values['invoice_po_number'];
			$this->invoice->save();
			\IPS\Output::i()->redirect( $this->invoice->url() );
		}
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );;
	}
	
	/**
	 * Notes
	 *
	 * @return	void
	 */
	public function notes()
	{		
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\TextArea( 'invoice_notes', $this->invoice->notes ) );
		\IPS\Member::loggedIn()->language()->words['invoice_notes_desc'] = '';
		if ( $values = $form->values() )
		{
			$this->invoice->notes = $values['invoice_notes'];
			$this->invoice->save();
			\IPS\Output::i()->redirect( $this->invoice->url() );
		}
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );;
	}
	
	/**
	 * Print
	 *
	 * @return	void
	 */
	public function printout()
	{
		$output = \IPS\Theme::i()->getTemplate( 'invoices', 'nexus', 'global' )->printInvoice( $this->invoice, $this->invoice->summary(), $this->invoice->billaddress ?: $this->invoice->member->primaryBillingAddress() );
		\IPS\Output::i()->title = 'I' . $this->invoice->id;
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $output ) );
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 */
	public function cancel()
	{
		/* CSRF check */
		\IPS\Session::i()->csrfCheck();
		
		/* Can only cancel the invoice if it's pending and there are no processing transactions */
		if ( $this->invoice->status !== \IPS\nexus\Invoice::STATUS_PENDING or \count( $this->invoice->transactions( [ \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING, \IPS\nexus\Transaction::STATUS_DISPUTED ] ) ) )
		{
			\IPS\Output::i()->error( 'order_already_paid', '2X215/4', 403, '' );
		}
				        
        /* If they have already made a partial payment, refund it to their account credit */
        foreach ( $this->invoice->transactions( array( \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_PART_REFUNDED ) ) as $transaction )
		{
			try
			{
				$transaction->refund( 'credit' );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'order_cancel_error', '4C171/5', 500, $e->getMessage() );
			}
		}
		
		/* Cancel the invoice */
		$this->invoice->status = \IPS\nexus\invoice::STATUS_CANCELED;
		$this->invoice->save();
		$this->invoice->member->log( 'invoice', array( 'type' => 'status', 'new' => 'canc', 'id' => $this->invoice->id, 'title' => $this->invoice->title ) );

		/* Run any callbacks (for example, coupons get unmarked as being used) */
        foreach ( $this->invoice->items as $k => $item )
        {
            $item->onInvoiceCancel( $this->invoice );
        }
        
        /* Redirect */
		\IPS\Output::i()->redirect( $this->invoice->url() );
	}
}
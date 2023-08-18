<?php
/**
 * @brief		View Customer
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Feb 2014
 */

namespace IPS\nexus\modules\admin\customers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * View
 */
class _view extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	Member
	 */
	protected $member;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_view' );
		
		try
		{
			$this->member = \IPS\nexus\Customer::load( \IPS\Request::i()->id );
			if ( !$this->member->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X233/1', 404, '' );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customer.css', 'nexus', 'admin' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customer_responsive.css', 'nexus', 'admin' ) );
		}

		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_customer.js', 'nexus', 'admin' ) );
		\IPS\Output::i()->title = "{$this->member->cm_name}";		
		
		parent::execute();
	}

	/**
	 * View Customer
	 *
	 * @return	void
	 * @deprecated
	 */
	protected function manage()
	{
		\IPS\Output::i()->redirect( $this->member->acpUrl() );
	}
	
	/**
	 * View Addresses
	 *
	 * @return	void
	 */
	protected function addresses()
	{
		$addresses = new \IPS\Helpers\Table\Db( 'nexus_customer_addresses', \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( 'view', 'addresses' ), array( '`member`=?', $this->member->member_id ) );
		$addresses->sortBy = 'primary_billing, primary_shipping, added';
		$addresses->include = array( 'address', 'primary_billing', 'primary_shipping' );
		$addresses->parsers = array( 'address' => function( $val )
		{
			$address = \IPS\GeoLocation::buildFromJson( $val );
			return $address->toString( '<br>' ) . ( ( isset( $address->business ) and $address->business and isset( $address->vat ) and $address->vat ) ? ( '<br>' . \IPS\Member::loggedIn()->language()->addToStack('cm_checkout_vat_number') . ': ' .\IPS\Theme::i()->getTemplate( 'global', 'nexus' )->vatNumber( $address->vat ) ) : '' );
		} );
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'customers', 'customers_edit_details' ) )
		{
			$addresses->rootButtons = array(
				'add'	=> array(
					'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( 'do', 'addressForm' ),
					'title'	=> 'add',
					'icon'	=> 'plus',
					'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add_address') )
				)
			);
			$addresses->rowButtons = function( $row )
			{
				return array(
					'edit'	=> array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'addressForm', 'address_id' => $row['id'] ) ),
						'title'	=> 'edit',
						'icon'	=> 'pencil',
						'data'	=> array( 'ipsDialog' => true, 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('edit_address') )
					),
					'delete'	=> array(
						'link'	=> \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array( 'do' => 'deleteAddress', 'address_id' => $row['id'] ) ),
						'title'	=> 'delete',
						'icon'	=> 'times-circle',
						'data'	=> array( 'delete' => '' )
					)
				);
			};
		}
	
		$addresses->tableTemplate = array( \IPS\Theme::i()->getTemplate('customers'), 'addressTable' );
		$addresses->rowsTemplate = array( \IPS\Theme::i()->getTemplate('customers'), 'addressTableRows' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('customers')->customerPopup( $addresses );
	}
		
	/**
	 * Edit Customer Fields
	 *
	 * @return	void
	 */
	public function edit()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_edit_details' );
		
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Text( 'cm_first_name', $this->member->cm_first_name, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'cm_last_name', $this->member->cm_last_name, FALSE ) );
		
		foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
		{
			$column = $field->column;
			if ( $field->type === 'Editor' )
			{
				$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => array( $this->member->member_id ) ) );
			}
			$form->add( $field->buildHelper( $this->member->$column ) );
		}
		
		if ( $values = $form->values( TRUE ) )
		{
			$changes = array();
			foreach ( array( 'cm_first_name', 'cm_last_name' ) as $k )
			{
				if ( $values[ $k ] != $this->member->$k )
				{
					/* We only need to log this once, so do it if it isn't set */
					if ( !isset( $changes['name'] ) )
					{
						$changes['name'] = $this->member->cm_name;
					}
					
					$this->member->$k = $values[ $k ];
				}
			}
			foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
			{
				$column = $field->column;
				if ( $this->member->$column != $values["nexus_ccfield_{$field->id}"] )
				{
					$changes['other'][] = array( 'name' => 'nexus_ccfield_' . $field->id, 'value' => $field->displayValue( $values["nexus_ccfield_{$field->id}"] ), 'old' => $this->member->$column );
					$this->member->$column = $values["nexus_ccfield_{$field->id}"];
				}
				
				if ( $field->type === 'Editor' )
				{
					$field->claimAttachments( $this->member->member_id );
				}
			}
			if ( !empty( $changes ) )
			{
				$this->member->log( 'info', $changes );
			}
			$this->member->save();
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Edit Credits
	 *
	 * @return	void
	 */
	public function credits()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_edit_credit' );
		
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		foreach ( \IPS\nexus\Money::currencies() as $currency )
		{
			$form->add( new \IPS\Helpers\Form\Number( $currency, isset( $this->member->cm_credits[ $currency ] ) ? $this->member->cm_credits[ $currency ]->amount : 0, FALSE, array( 'min' => 0, 'decimals' => \IPS\nexus\Money::numberOfDecimalsForCurrency( $currency ) ), NULL, NULL, $currency ) );
		}
		
		if ( $values = $form->values() )
		{
			$credits = $this->member->cm_credits;
			foreach ( $values as $currency => $amount )
			{
				$amount = new \IPS\Math\Number( number_format( $amount, \IPS\nexus\Money::numberOfDecimalsForCurrency( $currency ), '.', '' ) );
				if ( ( isset( $this->member->cm_credits[ $currency ] ) and $this->member->cm_credits[ $currency ]->amount->compare( $amount ) !== 0 ) or $amount )
				{
					$this->member->log( 'comission', array( 'type' => 'manual', 'old' => isset( $this->member->cm_credits[ $currency ] ) ? $this->member->cm_credits[ $currency ]->amountAsString() : 0, 'new' => (string) $amount, 'currency' => $currency ) );
				}
				$credits[ $currency ] = new \IPS\nexus\Money( $amount, $currency );
			}
			$this->member->cm_credits = $credits;
			$this->member->save();
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		
		\IPS\Output::i()->output = (string) $form;
	}
	
	/**
	 * Add/Edit Note
	 *
	 * @return	void
	 */
	public function noteForm()
	{
		$noteId = NULL;
		$note = NULL;
		if ( \IPS\Request::i()->note_id )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'customer_notes_edit' );
			$noteId = \intval( \IPS\Request::i()->note_id );
			try
			{
				$note = \IPS\Db::i()->select( 'note_text', 'nexus_notes', array( 'note_id=?', \IPS\Request::i()->note_id ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2X233/3', 404, '' );
			}
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'customer_notes_add' );
		}
		
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Editor( 'customer_note', $note, TRUE, array(
			'app'			=> 'nexus',
			'key'			=> 'Customer',
			'autoSaveKey'	=> $noteId ? "nexus-note-{$this->member->member_id}-{$noteId}" : "nexus-note-{$this->member->member_id}-new",
			'attachIds'		=> $noteId ? array( $this->member->member_id, $noteId, 'note' ) : NULL
		) ) );
		if ( $values = $form->values() )
		{
			if ( \IPS\Request::i()->note_id )
			{
				\IPS\Db::i()->update( 'nexus_notes', array(
					'note_text'	=> $values['customer_note']
				), array( 'note_id=?', \IPS\Request::i()->note_id ) );
				
				$this->member->log( 'note', 'edited' );
			}
			else
			{
				$noteId = \IPS\Db::i()->insert( 'nexus_notes', array(
					'note_member'	=> $this->member->member_id,
					'note_text'		=> $values['customer_note'],
					'note_author'	=> \IPS\Member::loggedIn()->member_id,
					'note_date'		=> time(),
				) );
				
				\IPS\File::claimAttachments( "nexus-note-{$this->member->member_id}-new", $this->member->member_id, $noteId, 'note' );
				
				$this->member->log( 'note', 'added' );
			}
			
			if ( isset( \IPS\Request::i()->support ) and \IPS\Request::i()->support )
			{
				try
				{
					\IPS\Output::i()->redirect( \IPS\nexus\Support\Request::load( \IPS\Request::i()->support )->acpUrl() );
				}
				catch ( \OutOfRangeException $e ) {}
			}
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		\IPS\Output::i()->output = $form;
	}
	
	/** 
	 * Delete Note
	 *
	 * @return	void
	 */
	public function deleteNote()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customer_notes_delete' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		\IPS\Db::i()->delete( 'nexus_notes', array( 'note_id=?', \IPS\Request::i()->note_id ) );
		$this->member->log( 'note', 'deleted' );
		
		if ( isset( \IPS\Request::i()->support ) and \IPS\Request::i()->support )
		{
			try
			{
				\IPS\Output::i()->redirect( \IPS\nexus\Support\Request::load( \IPS\Request::i()->support )->acpUrl() );
			}
			catch ( \OutOfRangeException $e ) {}
		}
		\IPS\Output::i()->redirect( $this->member->acpUrl() );
	}
	
	/**
	 * Add Address
	 *
	 * @return	void
	 */
	public function addressForm()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_edit_details' );
		
		if ( \IPS\Request::i()->address_id )
		{
			try
			{
				$address = \IPS\nexus\Customer\Address::load( \IPS\Request::i()->address_id );
				if ( $address->member !== $this->member )
				{
					throw new \OutOfRangeException;
				}
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2X233/2', 404, '' );
			}
		}
		else
		{
			$address = new \IPS\nexus\Customer\Address;
			$address->member = $this->member;
			$address->primary_billing = ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_addresses', array( '`member`=? AND primary_billing=1', $this->member->member_id ) )->first() == 0 );
			$address->primary_shipping = ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_addresses', array( '`member`=? AND primary_shipping=1', $this->member->member_id ) )->first() == 0 );
		}
		
		$needTaxStatus = NULL;
		foreach ( \IPS\nexus\Tax::roots() as $tax )
		{
			if ( $tax->type === 'eu' )
			{
				$needTaxStatus = 'eu';
				break;
			}
			if ( $tax->type === 'business' )
			{
				$needTaxStatus = 'business';
			}
		}
		$addressHelperClass = $needTaxStatus ? 'IPS\nexus\Form\BusinessAddress' : 'IPS\Helpers\Form\Address';
		$addressHelperOptions = ( $needTaxStatus === 'eu' ) ? array( 'vat' => TRUE ) : array();
		
		$form = new \IPS\Helpers\Form;
		$form->add( new $addressHelperClass( 'address', $address->address, TRUE, $addressHelperOptions ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'primary_billing', $address->primary_billing ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'primary_shipping', $address->primary_shipping ) );
		if ( $values = $form->values() )
		{
			if ( $address->id )
			{
				if ( $values['address'] != $address->address )
				{
					$this->member->log( 'address', array( 'type' => 'edit', 'new' => json_encode( $values['address'] ), 'old' => json_encode( $address->address ) ) );
				}
				if ( $values['primary_billing'] and !$address->primary_billing )
				{
					\IPS\Db::i()->update( 'nexus_customer_addresses', array( 'primary_billing' => 0 ), array( '`member`=?', $this->member->member_id ) );
					$this->member->log( 'address', array( 'type' => 'primary_billing', 'details' => json_encode( $values['address'] ) ) );
				}
				if ( $values['primary_shipping'] and !$address->primary_shipping )
				{
					\IPS\Db::i()->update( 'nexus_customer_addresses', array( 'primary_shipping' => 0 ), array( '`member`=?', $this->member->member_id ) );
					$this->member->log( 'address', array( 'type' => 'primary_shipping', 'details' => json_encode( $values['address'] ) ) );
				}
			}
			else
			{
				$this->member->log( 'address', array( 'type' => 'add', 'details' => json_encode( $values['address'] ) ) );
			}
			
			$address->address = $values['address'];
			$address->primary_billing = $values['primary_billing'];
			$address->primary_shipping = $values['primary_shipping'];
			$address->save();
			
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		\IPS\Output::i()->output = $form;
	}
	
	/** 
	 * Delete Address
	 *
	 * @return	void
	 */
	public function deleteAddress()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_edit_details' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$address = \IPS\nexus\Customer\Address::load( \IPS\Request::i()->address_id );
			$this->member->log( 'address', array( 'type' => 'delete', 'details' => json_encode( $address->address ) ) );
			$address->delete();
		}
		catch ( \OutOfRangeException $e ) { }
		\IPS\Output::i()->redirect( $this->member->acpUrl() );
	}
	
	/** 
	 * Add Card
	 *
	 * @csrfChecked	Uses Form helper in Gateway classes 7 Oct 2019
	 * @return	void
	 */
	public function addCard()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_gateways.js', 'nexus', 'global' ) );
		$form = \IPS\nexus\Customer\CreditCard::create( $this->member, TRUE );
		if ( $form instanceof \IPS\nexus\Customer\CreditCard )
		{
			$this->member->log( 'card', array( 'type' => 'add', 'number' => $form->card->lastFour ) );
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		else
		{
			\IPS\Output::i()->output = $form;
		}		
	}
	
	/** 
	 * Delete Card
	 *
	 * @return	void
	 */
	public function deleteCard()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$card = \IPS\nexus\Customer\CreditCard::load( \IPS\Request::i()->card_id );
			$this->member->log( 'card', array( 'type' => 'delete', 'number' => $card->card->lastFour ) );
			$card->delete();
		}
		catch ( \OutOfRangeException $e ) { }
		\IPS\Output::i()->redirect( $this->member->acpUrl() );
	}
	
	/**
	 * Add/Edit Alternative Contact
	 *
	 * @return	void
	 */
	public function alternativeContactForm()
	{
		$existing = NULL;
		if ( isset( \IPS\Request::i()->alt_id ) )
		{
			try
			{
				$existing = \IPS\nexus\Customer\AlternativeContact::constructFromData( \IPS\Db::i()->select( '*', 'nexus_alternate_contacts', array( 'main_id=? AND alt_id=?', $this->member->member_id, \IPS\Request::i()->alt_id ) )->first() );
			}
			catch ( \UnderflowException $e ) {}
		}
				
		$form = new \IPS\Helpers\Form;
		if ( !$existing )
		{
			$form->add( new \IPS\Helpers\Form\Member( 'altcontact_member_admin', NULL, TRUE, array(), function( $val )
			{
				if( $this->member->member_id === $val->member_id )
				{
					throw new \DomainException('altcontact_member_admin_self');
				}
			} ) );
		}
		$form->add( new \IPS\Helpers\Form\Node( 'altcontact_purchases_admin', $existing ? iterator_to_array( $existing->purchases ) : NULL, FALSE, array( 'class' => 'IPS\nexus\Purchase', 'forceOwner' => $this->member, 'multiple' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'altcontact_support_admin', $existing ? $existing->support : FALSE ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'altcontact_billing_admin', $existing ? $existing->billing : FALSE ) );
		if ( $values = $form->values() )
		{
			if ( $existing )
			{
				$altContact = $existing;
				$this->member->log( 'alternative', array( 'type' => 'edit', 'alt_id' => $altContact->alt_id->member_id, 'alt_name' => $altContact->alt_id->name, 'purchases' => json_encode( $values['altcontact_purchases_admin'] ? $values['altcontact_purchases_admin'] : array() ), 'billing' => $values['altcontact_billing_admin'], 'support' => $values['altcontact_support_admin'] ) );
			}
			else
			{
				$altContact = new \IPS\nexus\Customer\AlternativeContact;
				$altContact->main_id = $this->member;
				$altContact->alt_id = $values['altcontact_member_admin'];
				$this->member->log( 'alternative', array( 'type' => 'add', 'alt_id' => $values['altcontact_member_admin']->member_id, 'alt_name' => $values['altcontact_member_admin']->name, 'purchases' => json_encode( $values['altcontact_purchases_admin'] ? $values['altcontact_purchases_admin'] : array() ), 'billing' => $values['altcontact_billing_admin'], 'support' => $values['altcontact_support_admin'] ) );		
			}
			$altContact->purchases = $values['altcontact_purchases_admin'] ? $values['altcontact_purchases_admin'] : array();
			$altContact->billing = $values['altcontact_billing_admin'];
			$altContact->support = $values['altcontact_support_admin'];
			$altContact->save();
			
			\IPS\Output::i()->redirect( $this->member->acpUrl() );
		}
		\IPS\Output::i()->output = $form;
	}
	
	/** 
	 * Delete Alternative Contact
	 *
	 * @return	void
	 */
	public function deleteAlternativeContact()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$contact = \IPS\nexus\Customer\AlternativeContact::constructFromData( \IPS\Db::i()->select( '*', 'nexus_alternate_contacts', array( 'main_id=? AND alt_id=?', $this->member->member_id, \IPS\Request::i()->alt_id ) )->first() );
			$this->member->log( 'alternative', array( 'type' => 'delete', 'alt_id' => $contact->alt_id->member_id, 'alt_name' => $contact->alt_id->name ) );
			$contact->delete();
		}
		catch ( \OutOfRangeException $e ) { }
		\IPS\Output::i()->redirect( $this->member->acpUrl() );
	}
	
	/** 
	 * Void Account
	 *
	 * @return	void
	 */
	public function void()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'customers_void' );
		
		if ( isset( \IPS\Request::i()->process ) )
		{
			\IPS\Session::i()->csrfCheck();
			$values = array(
				'void_refund_transactions'			=> \IPS\Request::i()->trans,
				'void_cancel_billing_agreements'	=> \IPS\Request::i()->ba,
				'void_cancel_purchases' 			=> \IPS\Request::i()->purch,
			);
		}
		else
		{		
			$form = new \IPS\Helpers\Form( 'void_account', 'void_account' );
			$form->ajaxOutput = TRUE;
			$form->addMessage( 'void_account_warning' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'void_refund_transactions', TRUE ) );
			if ( \IPS\nexus\Gateway::billingAgreementGateways() )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'void_cancel_billing_agreements', TRUE ) );
			}
			$form->add( new \IPS\Helpers\Form\YesNo( 'void_cancel_purchases', TRUE ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'void_cancel_invoices', TRUE ) );
			$form->add( new \IPS\Helpers\Form\Node( 'void_resolve_support', \IPS\Settings::i()->nexus_autoresolve_status, FALSE, array( 'class' => 'IPS\nexus\Support\Status', 'zeroVal' => 'do_not_change' ) ) );
			if ( $this->member->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$form->add( new \IPS\Helpers\Form\YesNo( 'void_ban_account', TRUE ) );
			}
			$form->add( new \IPS\Helpers\Form\Editor( 'void_add_note', NULL, FALSE, array(
				'app'			=> 'nexus',
				'key'			=> 'Customer',
				'autoSaveKey'	=> "nexus-note-{$this->member->member_id}-new",
				'minimize'		=> 'void_add_note_placeholder'
			) ) );
			
			if ( $values = $form->values() )
			{
				if ( $values['void_cancel_invoices'] )
				{
					\IPS\Db::i()->update( 'nexus_invoices', array( 'i_status' => \IPS\nexus\Invoice::STATUS_CANCELED ), array( 'i_member=? AND i_status<>?', $this->member->member_id, \IPS\nexus\Invoice::STATUS_PAID ) );
				}
				if ( $values['void_resolve_support'] )
				{
					\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_status' => $values['void_resolve_support']->_id ), array( 'r_member=?', $this->member->member_id ) );
				}
				if ( $this->member->member_id != \IPS\Member::loggedIn()->member_id and $values['void_ban_account'] )
				{
					$this->member->temp_ban = -1;
					$this->member->save();
				}
				if ( $values['void_add_note'] )
				{
					$noteId = \IPS\Db::i()->insert( 'nexus_notes', array(
						'note_member'	=> $this->member->member_id,
						'note_text'		=> $values['void_add_note'],
						'note_author'	=> \IPS\Member::loggedIn()->member_id,
						'note_date'		=> time(),
					) );
					
					\IPS\File::claimAttachments( "nexus-note-{$this->member->member_id}-new", $this->member->id, $noteId, 'note' );
				}
				
				if ( !$values['void_refund_transactions'] and !$values['void_cancel_purchases'] and !$values['void_cancel_billing_agreements'] )
				{
					\IPS\Output::i()->redirect( $this->member->acpUrl() );
				}
			}
		}
		
		if ( $values )
		{
			$member = $this->member;
						
			\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect( \IPS\Http\Url::internal("app=nexus&module=customers&controller=view&id={$this->member->member_id}")->setQueryString( array(
				'do'		=> 'void',
				'process'	=> 1,
				'trans'		=> $values['void_refund_transactions'],
				'ba'		=> isset( $values['void_cancel_billing_agreements'] ) ? $values['void_cancel_billing_agreements'] : FALSE ,
				'purch'		=> $values['void_cancel_purchases'],
			) )->csrf(), function( $data ) use ( $member )
			{		
				if ( !\is_array( $data ) )
				{
					$data = array( 'trans' => 0, 'ba' => 0, 'purch' => 0, 'fail' => array() );
				}
				
				$done = 0;
				
				if ( \IPS\Request::i()->trans )
				{
					foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_transactions', array( 't_member=?', $member->member_id ), 't_id', array( $data['trans'], 10 ) ), 'IPS\nexus\Transaction' ) as $transaction )
					{
						if ( \in_array( $transaction->status, array( $transaction::STATUS_PENDING, $transaction::STATUS_WAITING, $transaction::STATUS_GATEWAY_PENDING ) ) )
						{
							$transaction->status = $transaction::STATUS_REVIEW;
							$transaction->save();
						}
						elseif ( \in_array( $transaction->status, array( $transaction::STATUS_PAID, $transaction::STATUS_HELD, $transaction::STATUS_REVIEW, $transaction::STATUS_PART_REFUNDED ) ) )
						{
							try
							{
								if ( $transaction->auth and \in_array( $transaction->status, array( $transaction::STATUS_HELD, $transaction::STATUS_REVIEW ) ) )
								{
									$transaction->void();
								}
								else
								{
									$transaction->refund();
								}
								
								$transaction->invoice->markUnpaid( \IPS\nexus\Invoice::STATUS_CANCELED, \IPS\Member::loggedIn() );
							}
							catch ( \Exception $e )
							{
								$data['fail'][] = $transaction->id;
							}
						}
						
						$data['trans']++;
						$done++;
						if ( $done >= 10 )
						{
							return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
						}
					}
				}
				
				if ( \IPS\Request::i()->ba )
				{
					foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_billing_agreements', array( 'ba_member=?', $member->member_id ), 'ba_id', array( $data['ba'], 10 ) ), 'IPS\nexus\Customer\BillingAgreement' ) as $billingAgreement )
					{
						try
						{
							$billingAgreement->cancel();
						}
						catch ( \Exception $e ) { }
						
						$data['ba']++;
						$done++;
						if ( $done >= 10 )
						{
							return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
						}
					}
				}
				
				if ( \IPS\Request::i()->purch )
				{
					foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_member=?', $member->member_id ), 'ps_id', array( $data['purch'], 10 ) ), 'IPS\nexus\Purchase' ) as $purchase )
					{
						$purchase->cancelled = TRUE;
						$purchase->can_reactivate = FALSE;
						$purchase->save();
						
						$data['purch']++;
						$done++;
						if ( $done >= 10 )
						{
							return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
						}
					}
				}
				
				$_SESSION['voidAccountFails'] = $data['fail'];
				return NULL;
			}, function() use ( $member )
			{
				if ( \count( $_SESSION['voidAccountFails'] ) )
				{
					\IPS\Output::i()->redirect( $member->acpUrl()->setQueryString( 'do', 'voidFails' ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $member->acpUrl() );
				}
			} );
			return;
		}
		else
		{
			\IPS\Output::i()->output = $form;
		}
	}
	
	/** 
	 * Void Account Results
	 *
	 * @return	void
	 */
	public function voidFails()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'customers' )->voidFails( $_SESSION['voidAccountFails'] );
	}
}
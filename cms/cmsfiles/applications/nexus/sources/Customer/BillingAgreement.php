<?php
/**
 * @brief		Billing Agreement Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		16 Dec 2015
 */

namespace IPS\nexus\Customer;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Billing Agreement Model
 */
class _BillingAgreement extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Billing Agreement is active and will charge automatically
	 */
	const STATUS_ACTIVE		= 'active';

	/**
	 * @brief	Billing Agreement has been suspended but can be reactivated
	 */
	const STATUS_SUSPENDED	= 'suspended';

	/**
	 * @brief	Billing Agreement has been canceled
	 */
	const STATUS_CANCELED	= 'canceled';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_billing_agreements';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'ba_';
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$gateway = \IPS\nexus\Gateway::load( $data['ba_method'] );
		$classname = 'IPS\nexus\Gateway\\' . $gateway->gateway . '\\BillingAgreement';
		
		/* Initiate an object */
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix AND mb_strpos( $k, static::$databasePrefix ) === 0 )
			{
				$k = \substr( $k, \strlen( static::$databasePrefix ) );
			}

			$obj->_data[ $k ] = $v;
		}
		$obj->changed = array();
		
		/* Init */
		if ( method_exists( $obj, 'init' ) )
		{
			$obj->init();
		}
				
		/* Return */
		return $obj;
	}
	
	/**
	 * Load and check permissions
	 *
	 * @param	mixed	$id	ID to load
	 * @return	\IPS\Content\Item
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id )
	{
		$obj = static::load( $id );
		
		if ( !$obj->canView() )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}
	
	/**
	 * Member can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for, or NULL for currently logged in member
	 * @return	bool
	 */
	public function canView( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		return $this->member->member_id === $member->member_id or array_key_exists( $member->member_id, iterator_to_array( $this->member->alternativeContacts( array( 'billing=1' ) ) ) );
	}
	
	/**
	 * Get member
	 *
	 * @return	\IPS\Member
	 */
	public function get_member()
	{
		return \IPS\nexus\Customer::load( $this->_data['member'] );
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function set_member( \IPS\Member $member )
	{
		$this->_data['member'] = $member->member_id;
	}
	
	/**
	 * Get payment gateway
	 *
	 * @return	\IPS\nexus\Gateway
	 */
	public function get_method()
	{
		return \IPS\nexus\Gateway::load( $this->_data['method'] );
	}
	
	/**
	 * Set payment gateway
	 *
	 * @param	\IPS\nexus\Gateway	$gateway	Payment gateway
	 * @return	void
	 */
	public function set_method( \IPS\nexus\Gateway $gateway )
	{
		$this->_data['method'] = $gateway->id;
	}
	
	/**
	 * Get start date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_started()
	{
		return \IPS\DateTime::ts( $this->_data['started'] );
	}
	
	/**
	 * Set next payment date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_next_cycle( \IPS\DateTime $date = NULL )
	{
		$this->_data['next_cycle'] = $date ? $date->getTimestamp() : NULL;
	}
	
	/**
	 * Get next payment date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_next_cycle()
	{
		return $this->_data['next_cycle'] ? \IPS\DateTime::ts( $this->_data['next_cycle'] ) : NULL;
	}
	
	/**
	 * Set start date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_started( \IPS\DateTime $date )
	{
		$this->_data['started'] = $date->getTimestamp();
	}

	/**
	 * Suspend
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function refresh()
	{
		try
		{
			while( true )
			{
				$this->checkForLatestTransaction();
			}
		}
		catch( \OutOfRangeException | \UnderflowException $e )
		{
			return;
		}
	}	

	/**
	 * Suspend
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function suspend()
	{
		$this->doSuspend();
		
		$this->member->log( 'billingagreement', array( 'type' => 'suspend', 'id' => $this->id, 'gw_id' => $this->gw_id ) );
		$this->next_cycle = NULL;
		$this->save();
	}
	
	/**
	 * Reactivate
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function reactivate()
	{
		$this->doReactivate();
		
		$this->member->log( 'billingagreement', array( 'type' => 'reactivate', 'id' => $this->id, 'gw_id' => $this->gw_id ) );
		$this->next_cycle = $this->nextPaymentDate();
		$this->save();
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 * @throws	\DomainException
	 */
	public function cancel()
	{
		$this->doCancel();
		
		$this->member->log( 'billingagreement', array( 'type' => 'cancel', 'id' => $this->id, 'gw_id' => $this->gw_id ) );
		$this->next_cycle = NULL;
		$this->canceled = TRUE;	
		$this->save();
	}
	
	/**
	 * Front-End URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return \IPS\Http\Url::internal( "app=nexus&module=clients&controller=billingagreements&do=view&id={$this->id}", 'front', 'clientsbillingagreement' );
	}
	
	/**
	 * ACP URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return \IPS\Http\Url::internal( "app=nexus&module=payments&controller=billingagreements&id={$this->id}", 'admin' );
	}

	/**
	 * Check for latest unclaimed transactions
	 *
	 * @return	void
	 */
	public function checkForLatestTransaction()
	{
		/* Fetch the latest unclaimed transaction */
		try
		{
			$transaction = $this->latestUnclaimedTransaction();
		}
		/* If there isn't one yet, it might just be that PayPal hasn't taken it yet. Let it try for up to 11 days before giving up, which is how long PayPal will try for if there's an issue. */
		catch ( \OutOfRangeException $e )
		{
			if ( $this->next_cycle->getTimestamp() > ( time() - ( 86400 * 11 ) ) )
			{
				throw new \UnderflowException;
			}
			else
			{
				/* We are here because we could not find any unclaimed transactions and it has been at least 5 days since we expected to. Verify the next cycle before proceeding because it may not have been set properly in the past so before we say there's a problem let's check. */
				$nextPaymentDate = $this->nextPaymentDate();

				if( $nextPaymentDate->getTimestamp() > time() )
				{
					$this->next_cycle = $nextPaymentDate;
					$this->save();

					throw new \UnderflowException;
				}

				throw $e;
			}
		}

		/* Get purchases */
		$purchases = new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_purchases', array( 'ps_billing_agreement=?', $this->id ) ), 'IPS\nexus\Purchase' );

		/* Generate an invoice */
		$invoice = new \IPS\nexus\Invoice;
		$invoice->system = TRUE;
		$invoice->currency = $transaction->amount->currency;
		$invoice->member = $this->member;
		$invoice->billaddress = $this->member->primaryBillingAddress();
		foreach ( $purchases as $purchase )
		{
			$invoice->addItem( \IPS\nexus\Invoice\Item\Renewal::create( $purchase ) );
		}
		$invoice->save();

		/* Assign the transaction to it */
		$transaction->invoice = $invoice;
		$transaction->save();
		$transaction->approve();
		$invoice->status = $transaction->invoice->status;

		/* Log */
		$invoice->member->log( 'transaction', array(
			'type' => 'paid',
			'status' => \IPS\nexus\Transaction::STATUS_PAID,
			'id' => $transaction->id,
			'invoice_id' => $invoice->id,
			'invoice_title' => $invoice->title,
			'automatic' => TRUE,
		), FALSE );

		/* Update the purchase */
		if ( $invoice->status !== $invoice::STATUS_PAID )
		{
			foreach ( $purchases as $purchase )
			{
				$purchase->invoice_pending = $invoice;
				$purchase->save();
			}
		}

		/* Send notification */
		$invoice->sendNotification();

		/* Update billing agreement */
		if ( $invoice->status === $invoice::STATUS_PAID )
		{
			$this->next_cycle = $this->nextPaymentDate();
		}
		else
		{
			$this->next_cycle = NULL;
		}
		$this->save();
	}
}
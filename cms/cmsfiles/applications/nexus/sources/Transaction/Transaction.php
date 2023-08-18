<?php
/**
 * @brief		Transaction Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Transaction Model
 */
class _Transaction extends \IPS\Patterns\ActiveRecord
{
	const STATUS_PAID			= 'okay'; // Transaction has been paid successfully
	const STATUS_PENDING		= 'pend'; // Payment not yet submitted (for example, has been redirected to external site)
	const STATUS_WAITING		= 'wait'; // Waiting for user (for example, a check is in the mail). Manual approval will be required
	const STATUS_HELD			= 'hold'; // Transaction is being held for approval
	const STATUS_REVIEW			= 'revw'; // Transaction, after being held for approval, has been flagged for review by staff
	const STATUS_REFUSED		= 'fail'; // Transaction was refused
	const STATUS_REFUNDED		= 'rfnd'; // Transaction has been refunded in full
	const STATUS_PART_REFUNDED	= 'prfd'; // Transaction has been partially refunded
	const STATUS_GATEWAY_PENDING= 'gwpd'; // The gateway is processing the transaction
	const STATUS_DISPUTED		= 'dspd'; // The customer disputed the transaction with their bank (filed a chargeback)
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'nexus_transactions';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 't_';
	
	/**
	 * Load and check permissions
	 *
	 * @return	\IPS\Content\Item
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id )
	{
		$obj = static::load( $id );

		if ( $obj->member->member_id !== \IPS\Member::loggedIn()->member_id )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}
	
	/**
	 * Get statuses
	 *
	 * @return	array
	 */
	public static function statuses()
	{
		$options = array();
		$reflection = new \ReflectionClass( \get_called_class() );
		foreach ( $reflection->getConstants() as $k => $v )
		{
			if ( mb_substr( $k, 0, 7 ) === 'STATUS_' )
			{
				$options[ $v ] = "tstatus_{$v}";
			}
		}
		return $options;	
	}
	
	/**
	 * Get transaction table
	 *
	 * @param	array	$where	Where clause
	 * @param	string	$ref	Referrer
	 * @return	\IPS\Helpers\Table\Db
	 */
	public static function table( $where, \IPS\Http\Url $url, $ref = 't' )
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'nexus_transactions', $url, $where );
		$table->sortBy = $table->sortBy ?: 't_date';

		/* Format Columns */
		$table->rowClasses = array( 't_invoice' => array( 'ipsTable_wrap' ) );
		$table->include = array( 't_status', 't_id', 't_method', 't_member', 't_amount', 't_invoice', 't_date' );
		$table->parsers = array(
			't_status'	=> function( $val )
			{
				return \IPS\Theme::i()->getTemplate('transactions', 'nexus')->status( $val );
			},
			't_method'	=> function( $val )
			{
				if ( $val )
				{
					try
					{
						return \IPS\nexus\Gateway::load( $val )->_title;
					}
					catch ( \OutOfRangeException $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack('gateway_deleted');
					}
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('account_credit');
				}
			},
			't_member'	=> function ( $val )
			{
				return \IPS\Theme::i()->getTemplate('global', 'nexus')->userLink( \IPS\Member::load( $val ) );
			},
			't_amount'	=> function( $val, $row )
			{
				return (string) new \IPS\nexus\Money( $val, $row['t_currency'] );
			},
			't_invoice'	=> function( $val )
			{
				try
				{
					return \IPS\Theme::i()->getTemplate('invoices', 'nexus')->link( \IPS\nexus\Invoice::load( $val ) );
				}
				catch ( \OutOfRangeException $e )
				{
					return '';
				}
			},
			't_date'	=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			}
		);
				
		/* Buttons */
		$table->rowButtons = function( $row ) use ( $ref )
		{
			return array_merge( array(
				'view'	=> array(
					'icon'	=> 'search',
					'title'	=> 'transaction_view',
					'link'	=> \IPS\Http\Url::internal( "app=nexus&module=payments&controller=transactions&do=view&id={$row['t_id']}" )->getSafeUrlFromFilters()
				),
			), \IPS\nexus\Transaction::constructFromData( $row )->buttons( $ref ) );
		};
		
		return $table;	
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->status = static::STATUS_PENDING;
		$this->date = new \IPS\DateTime;
		$this->fraud_blocked = NULL;
		$this->extra = array();
	}
	
	/**
	 * Get member
	 *
	 * @return	\IPS\Member
	 */
	public function get_member()
	{
		try
		{
			return \IPS\nexus\Customer::load( $this->_data['member'] );
		}
		catch( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member
	 * @return	void
	 */
	public function set_member( \IPS\Member $member )
	{
		$this->_data['member'] = (int) $member->member_id;

		/* If this is an incomplete member, flag them so they will not get deleted */
		if( $member->member_id AND ( empty( $member->name ) OR empty( $member->email ) OR $member->members_bitoptions['validating'] ) )
		{
			$member->members_bitoptions['created_externally'] = TRUE;
			$member->save();
		}
	}
	
	/**
	 * Get invoice
	 *
	 * @return	\IPS\nexus\Invoice|NULL
	 */
	public function get_invoice()
	{
		/* If an invoice is deleted, then the transaction will remain present, which then can result in uncaught exception errors. */
		try
		{
			return \IPS\nexus\Invoice::load( $this->_data['invoice'] );
		}
		catch( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set invoice
	 *
	 * @param	\IPS\nexus\Invoice
	 * @return	void
	 */
	public function set_invoice( \IPS\nexus\Invoice $invoice )
	{
		$this->_data['invoice'] = $invoice->id;
	}
	
	/**
	 * Get payment gateway
	 *
	 * @return	\IPS\nexus\Gateway
	 */
	public function get_method()
	{
		if ( !isset( $this->_data['method'] ) or $this->_data['method'] === 0 )
		{
			return 0;
		}
		
		try
		{
			return \IPS\nexus\Gateway::load( $this->_data['method'] );
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set payment gateway
	 *
	 * @param	\IPS\nexus\Gateway
	 * @return	void
	 */
	public function set_method( \IPS\nexus\Gateway $gateway )
	{
		$this->_data['method'] = $gateway->id;
	}
	
	/**
	 * Get amount
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function get_amount()
	{		
		$amount = new \IPS\nexus\Money( $this->_data['amount'], $this->_data['currency'] );
		return $amount;
	}
	
	/**
	 * Set total
	 *
	 * @param	\IPS\nexus\Money	$amount	The total
	 * @return	void
	 */
	public function set_amount( \IPS\nexus\Money $amount )
	{
		$this->_data['amount'] = $amount->amount;
		$this->_data['currency'] = $amount->currency;
	}
	
	/**
	 * Get date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_date()
	{
		return \IPS\DateTime::ts( $this->_data['date'] );
	}
	
	/**
	 * Set date
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_date( \IPS\DateTime $date )
	{
		$this->_data['date'] = $date->getTimestamp();
	}
	
	/**
	 * Get extra information
	 *
	 * @return	mixed
	 */
	public function get_extra()
	{
		return json_decode( $this->_data['extra'], TRUE );
	}
	
	/**
	 * Set extra information
	 *
	 * @param	mixed	$extra	The data
	 * @return	void
	 */
	public function set_extra( $extra )
	{
		$this->_data['extra'] = json_encode( $extra );
	}

	/**
	 * Get MaxMind data
	 *
	 * @return	\IPS\nexus\Fraud\MaxMind\Response 
	 */
	public function get_fraud()
	{
		return ( isset( $this->_data['fraud'] ) and $this->_data['fraud'] ) ? \IPS\nexus\Fraud\MaxMind\Response::buildFromJson( $this->_data['fraud'] ) : NULL;
	}
	
	/**
	 * Set MaxMind data
	 *
	 * @param	\IPS\nexus\Fraud\MaxMind\Response 	$maxMind	The data
	 * @return	void
	 */
	public function set_fraud( \IPS\nexus\Fraud\MaxMind\Response $maxMind )
	{
		$this->_data['fraud'] = (string) $maxMind;
	}
	
	/**
	 * Get triggered fraud rule
	 *
	 * @return	\IPS\nexus\Fraud\Rule|NULL
	 */
	public function get_fraud_blocked()
	{
		try
		{
			return $this->_data['fraud_blocked'] ? \IPS\nexus\Fraud\Rule::load( $this->_data['fraud_blocked'] ) : NULL;
		}
		catch ( \OutOfRangeException $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Set triggered fraud rule
	 *
	 * @param	\IPS\nexus\Fraud\Rule 	$rule The rule
	 * @return	void
	 */
	public function set_fraud_blocked( \IPS\nexus\Fraud\Rule $rule = NULL )
	{
		$this->_data['fraud_blocked'] = $rule ? $rule->id : 0;
	}
	
	/**
	 * Get partial refund amount
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function get_partial_refund()
	{		
		return new \IPS\nexus\Money( $this->_data['partial_refund'], $this->_data['currency'] );
	}
	
	/**
	 * Set partial refund amount
	 *
	 * @param	\IPS\nexus\Money	$amount	The total
	 * @return	void
	 */
	public function set_partial_refund( \IPS\nexus\Money $amount )
	{
		$this->_data['partial_refund'] = (string) $amount->amount;
	}
	
	/**
	 * Get credit amount
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function get_credit()
	{		
		return new \IPS\nexus\Money( $this->_data['credit'], $this->_data['currency'] );
	}
	
	/**
	 * Set credit amount
	 *
	 * @param	\IPS\nexus\Money	$amount	The total
	 * @return	void
	 */
	public function set_credit( \IPS\nexus\Money $amount )
	{
		$this->_data['credit'] = (string) $amount->amount;
	}
	
	/**
	 * Get date transaction must be captured by (is set after authorisation. once captured, should be NULL)
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_auth()
	{
		return $this->_data['auth'] ? \IPS\DateTime::ts( $this->_data['auth'] ) : NULL;
	}
	
	/**
	 * Set date transaction must be captured by (is set after authorisation. once captured, should be NULL)
	 *
	 * @param	\IPS\DateTime	$date	The invoice date
	 * @return	void
	 */
	public function set_auth( \IPS\DateTime $date = NULL )
	{
		$this->_data['auth'] = $date === NULL ? NULL : $date->getTimestamp();
	}
	
	/**
	 * Get billing agreement
	 *
	 * @return	\IPS\nexus\Customer\BillingAgreement|NULL
	 */
	public function get_billing_agreement()
	{
		return ( isset( $this->_data['billing_agreement'] ) AND $this->_data['billing_agreement'] ) ? \IPS\nexus\Customer\BillingAgreement::load( $this->_data['billing_agreement'] ) : NULL;
	}
	
	/**
	 * Set billing agreement
	 *
	 * @param	\IPS\nexus\Customer\BillingAgreement|NULL	$billingAgreement	The billing agreement
	 * @return	void
	 */
	public function set_billing_agreement( \IPS\nexus\Customer\BillingAgreement $billingAgreement = NULL )
	{
		$this->_data['billing_agreement'] = $billingAgreement === NULL ? NULL : $billingAgreement->id;
	}
	
	/**
	 * Run Anti-Fraud Checks and return status for transaction
	 *
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @return	string
	 */
	public function runFraudCheck( $maxMind=NULL )
	{
		/* Run MaxMind */
		if ( \IPS\Settings::i()->maxmind_key and ( !\IPS\Settings::i()->maxmind_gateways or \IPS\Settings::i()->maxmind_gateways == '*' or \in_array( $this->method->id, explode( ',', \IPS\Settings::i()->maxmind_gateways ) ) ) )
		{
			if ( $maxMind === NULL )
			{
				$maxMind = new \IPS\nexus\Fraud\MaxMind\Request;
				$maxMind->setTransaction( $this );
			}
			
			try
			{
				$maxMindResponse = $maxMind->request();
				
				$this->fraud = $maxMindResponse;
				$this->save();
			
				/* If MaxMind fails, stop here */
				if ( $this->fraud->error() and \IPS\Settings::i()->maxmind_error == 'hold' )
				{
					return static::STATUS_HELD;
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'maxmind_error' );
				
				if ( \IPS\Settings::i()->maxmind_error == 'hold' )
				{
					return static::STATUS_HELD;
				}
			}
		}
		
		/* Check Fraud Rules */
		foreach ( \IPS\nexus\Fraud\Rule::roots() as $rule )
		{
			if ( $rule->matches( $this ) )
			{
				$this->fraud_blocked = $rule;
				$this->save();
				
				return $rule->action;
			}
		}
		
		/* Check gateway */
		return $this->method->fraudCheck( $this );
	}
	
	/**
	 * Check fraud rules and capture
	 *
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @return	\IPS\Member|NULL	If the invoice belonged to a guest, a member will be created by approving and returned here
	 * @throws	\LogicException
	 */
	public function checkFraudRulesAndCapture( $maxMind=NULL )
	{
		/* Check fraud rules */
		$fraudResult = $this->runFraudCheck( $maxMind );
		if ( $fraudResult )
		{
			$this->executeFraudAction( $fraudResult, TRUE );
		}
		
		/* If we're not being fraud blocked, we can capture and approve */
		if ( $fraudResult === static::STATUS_PAID )
		{
			return $this->captureAndApprove();
		}
		return NULL;
	}
	
	/**
	 * Perform fraud rule action
	 *
	 * @param	string					$fraudResult	Status as returned by runFraudCheck()
	 * @param	bool					$isApproved		Has the payment already been approved? If so and the fraus rule wants to refuse, we will void
	 * @return	void
	 * @throws	\LogicException
	 */
	public function executeFraudAction( $fraudResult, $isApproved=TRUE )
	{		
		/* If the fraud rule wants to hold or refuse... */
		if ( $fraudResult !== static::STATUS_PAID )
		{
			/* If it wants to refuse, void the payment */
			if ( $isApproved and $fraudResult === static::STATUS_REFUSED )
			{
				$this->method->void( $this );
			}
			
			/* Set the status */
			$this->status = $fraudResult;
			$extra = $this->extra;
			$extra['history'][] = array( 's' => $fraudResult );
			$this->extra = $extra;
			
			/* Log */
			$this->member->log( 'transaction', array(
				'type'			=> 'paid',
				'status'		=> $fraudResult,
				'id'			=> $this->id,
				'invoice_id'	=> $this->invoice->id,
				'invoice_title'	=> $this->invoice->title,
			) );
			
			/* Notification */
			if ( \in_array( $fraudResult, array( static::STATUS_HELD, static::STATUS_REVIEW, static::STATUS_DISPUTED ) ) )
			{
				\IPS\core\AdminNotification::send( 'nexus', 'Transaction', $fraudResult, TRUE, $this );
			}
		}
		
		/* Save */
		$this->save();
	}
	
	/**
	 * Capture and approve
	 *
	 * @return	\IPS\Member|NULL	If the invoice belonged to a guest, a member will be created by approving and returned here
	 * @throws	\LogicException
	 */
	public function captureAndApprove()
	{		
		$this->capture();
		
		$this->member->log( 'transaction', array(
			'type'			=> 'paid',
			'status'		=> static::STATUS_PAID,
			'id'			=> $this->id,
			'invoice_id'	=> $this->invoice->id,
			'invoice_title'	=> $this->invoice->title,
		) );
		
		return $this->approve();
	}
	
	/**
	 * Capture
	 *
	 * @return	void
	 * @throws	\LogicException
	 */
	public function capture()
	{
		$this->method->capture( $this );
		$this->auth = NULL;
		$this->save();
	}
	
	/**
	 * Approve
	 *
	 * @param	\IPS\Member|NULL	$by	The staff member approving, or NULL if it's automatic
	 * @return	\IPS\Member|NULL	If the invoice belonged to a guest, a member will be created by approving and returned here
	 */
	public function approve( $by = NULL )
	{
		/* Get the amount to pay before storing this as a paid transaction */
		$amountToPayOnInvoice = $this->invoice->amountToPay();

		/* Set the transaction as paid */
		$this->status = static::STATUS_PAID;
		$extra = $this->extra;
		if ( $by )
		{
			$extra['history'][] = array( 's' => static::STATUS_PAID, 'on' => time(), 'by' => $by->member_id );
		}
		else
		{
			$extra['history'][] = array( 's' => static::STATUS_PAID );
		}
		$this->extra = $extra;
		$this->save();

		/* Update member total spend */
		if( $this->member->member_id )
		{
			$this->member->updateSpend( $this->amount->amount, $this->amount->currency );
		}

		/* Mark the invoice paid if necessary */
		if ( !$amountToPayOnInvoice->amount->subtract( $this->amount->amount )->isGreaterThanZero() )
		{	
			return $this->invoice->markPaid();
		}
		return NULL;
	}
	
	/**
	 * Void
	 *
	 * @return	void
	 * @throws	\Exception
	 */
	public function void()
	{
		/* Void it */
		$this->method->void( $this );
		
		/* Update transaction */
		$extra = $this->extra;
		$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'on' => time(), 'by' => \IPS\Member::loggedIn()->member_id );
		$this->extra = $extra;
		$this->status = \IPS\nexus\Transaction::STATUS_REFUSED;
		$this->auth = NULL;
		$this->save();
		
		/* Log it */
		if ( $this->member )
		{
			$this->member->log( 'transaction', array(
				'type'		=> 'status',
				'status'	=> \IPS\nexus\Transaction::STATUS_REFUSED,
				'id'		=> $this->id
			) );
		}
	}
	
	/**
	 * Refund
	 *
	 * @param	string		$refundMethod	"gateway", "credit", or "none"
	 * @param	float|NULL	$amount			Amount (NULL for full amount)
	 * @param	string		$reason			Reason for refund, if applicable (provided by gateway's refundReasons())
	 * @return	void
	 * @throws	\Exception
	 */
	public function refund( $refundMethod='gateway', $amount=NULL, $reason=NULL )
	{
		$extra = $this->extra;
		
		/* What's the amount? */
		if ( $amount )
		{
			if ( !( $amount instanceof \IPS\Math\Number ) )
			{
				$amount = new \IPS\Math\Number( number_format( $amount, \IPS\nexus\Money::numberOfDecimalsForCurrency( $this->amount->currency ), '.', '' ) );
			}
		}
		if ( !$amount or $this->amount->amount->compare( $amount ) === 0 )
		{
			$amount = NULL;
		}
		
		/* Actual Refund */
		if ( $refundMethod === 'gateway' and method_exists( $this->method, 'refund' ) )
		{
			/* Refund with gateway */
			$refundReference = $this->method->refund( $this, $amount, $reason );
			
			/* Update transaction and log */
			if ( $amount === NULL )
			{
				$this->status = static::STATUS_REFUNDED;
				
				$extra['history'][] = array( 's' => static::STATUS_REFUNDED, 'by' => \IPS\Member::loggedIn()->member_id, 'on' => time(), 'to' => $refundMethod, 'ref' => $refundReference );
				
				if ( $this->member )
				{
					$this->member->log( 'transaction', array(
						'type'		=> 'status',
						'status'	=> static::STATUS_REFUNDED,
						'id'		=> $this->id,
						'refund'	=> $refundMethod
					) );
				}

				/* Update member total spend */
				$this->member->updateSpend( $this->amount->amount, $this->amount->currency, true );
			}
			else
			{
				$this->partial_refund = new \IPS\nexus\Money( $this->partial_refund->amount->add( $amount ), $this->currency );
				
				if ( $amount >= $this->amount->amount )
				{
					$this->status = static::STATUS_REFUNDED;
				}
				else
				{
					$this->status = static::STATUS_PART_REFUNDED;
				}
				$extra['history'][] = array( 's' => $this->status, 'by' => \IPS\Member::loggedIn()->member_id, 'on' => time(), 'to' => $refundMethod, 'amount' => $amount, 'ref' => $refundReference );
				
				if ( $this->member )
				{
					$this->member->log( 'transaction', array(
						'type'		=> 'status',
						'status'	=> static::STATUS_PART_REFUNDED,
						'id'		=> $this->id,
						'refund'	=> $refundMethod,
						'amount'	=> $amount,
						'currency'	=> $this->currency
					) );
				}

				/* Update member total spend accounting for partial refund */
				$this->member->updateSpend( $amount, $this->amount->currency, true );
			}
		}
		/* Credit */
		elseif ( $refundMethod === 'credit' )
		{
			$amount = $amount ?: $this->amount->amount->subtract( $this->credit->amount );
			
			/* Add the credit */
			$credits = $this->member->cm_credits;
			$credits[ $this->amount->currency ]->amount = $credits[ $this->amount->currency ]->amount->add( $amount );
			$this->member->cm_credits = $credits;
			$this->member->save();
			
			/* Update transaction */
			$this->status = static::STATUS_PART_REFUNDED;
			$this->credit = new \IPS\nexus\Money( $this->credit->amount->add( $amount ), $this->currency );
			
			/* Log */
			$extra['history'][] = array( 's' => static::STATUS_PART_REFUNDED, 'by' => \IPS\Member::loggedIn()->member_id, 'on' => time(), 'to' => $refundMethod, 'amount' => $amount, 'ref' => NULL );
			if ( $this->member )
			{
				$this->member->log( 'transaction', array(
					'type'		=> 'status',
					'status'	=> static::STATUS_PART_REFUNDED,
					'id'		=> $this->id,
					'refund'	=> $refundMethod,
					'amount'	=> $amount,
					'currency'	=> $this->currency
				) );
			}
		}
		/* Mark refused, but don't actually do anything */
		elseif ( $refundMethod === 'none' )
		{
			/* Update transaction */
			$this->status = static::STATUS_REFUSED;
			$extra['history'][] = array( 's' => static::STATUS_REFUSED, 'by' => \IPS\Member::loggedIn()->member_id, 'on' => time() );
			
			/* Log */
			if ( $this->member )
			{
				$this->member->log( 'transaction', array(
					'type'		=> 'status',
					'status'	=> static::STATUS_REFUSED,
					'id'		=> $this->id
				) );
			}
		}		
		
		/* Save */
		$this->extra = $extra;
		$this->auth = NULL;
		$this->save();
	}
	
	/**
	 * Reverse previously given credit (will log, but does not change status - status must be set separately)
	 *
	 * @return	void
	 * @throws	\Exception
	 */
	public function reverseCredit()
	{
		$credits = $this->member->cm_credits;
		$credits[ $this->amount->currency ]->amount = $credits[ $this->amount->currency ]->amount->subtract( $this->credit->amount );
		$this->member->cm_credits = $credits;
		$this->member->save();
				
		$extra = $this->extra;
		$extra['history'][] = array( 's' => 'undo_credit', 'by' => \IPS\Member::loggedIn()->member_id, 'on' => time(), 'amount' => $this->credit->amount );
		if ( $this->member )
		{
			$this->member->log( 'transaction', array(
				'type'		=> 'undo_credit',
				'id'		=> $this->id,
				'amount'	=> $this->credit->amount,
				'currency'	=> $this->currency
			) );
		}
		$this->extra = $extra;
		
		$this->credit = new \IPS\nexus\Money( 0, $this->currency );

		$this->save();
	}
	
	/**
	 * Send Notification
	 *
	 * @return	void
	 */
	public function sendNotification()
	{		
		switch ( $this->status )
		{	
			case static::STATUS_PAID:
				$key = 'transactionApproved';
				$emailKey = 'payment_received';
				break;
							
			case static::STATUS_WAITING:
				$key = 'transactionWaiting';
				$emailKey = 'payment_waiting';
				break;
				
			case static::STATUS_HELD:
				$key = 'transactionHeld';
				$emailKey = 'payment_held';
				break;
				
			case static::STATUS_REFUSED:
				$key = 'transactionFailed';
				$emailKey = 'payment_failed';
				break;
				
			case static::STATUS_REFUNDED:
			case static::STATUS_PART_REFUNDED:
				$key = 'transactionRefunded';
				$emailKey = 'payment_refunded';
				break;
				
			case static::STATUS_GATEWAY_PENDING:
				$key = 'transactionGatewayPending';
				$emailKey = 'payment_gateway_pending';
				break;

			default:
				throw new \RuntimeException;
				break;
		}

		\IPS\Email::buildFromTemplate( 'nexus', $key, array( $this, $this->invoice, $this->invoice->summary( $this->invoice->member->language() ) ), \IPS\Email::TYPE_TRANSACTIONAL )
			->send(
				$this->invoice->member,
				array_map(
					function( $contact )
					{
						return $contact->alt_id->email;
					},
					iterator_to_array( $this->invoice->member->alternativeContacts( array( 'billing=1' ) ) )
				),
				( ( \in_array( $emailKey, explode( ',', \IPS\Settings::i()->nexus_notify_copy_types ) ) AND \IPS\Settings::i()->nexus_notify_copy_email ) ? explode( ',', \IPS\Settings::i()->nexus_notify_copy_email ) : array() )
			);
	}

	/**
	 * @brief	Cached URL
	 */
	protected $_url	= NULL;

	/**
	 * Get URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		if( $this->_url === NULL )
		{
			$this->_url = \IPS\Http\Url::internal( "app=nexus&module=checkout&controller=checkout&do=transaction&id={$this->invoice->id}&t={$this->id}", 'front', 'nexus_checkout' );
		}

		return $this->_url;
	}

	/**
	 * ACP URL
	 *
	 * @return	\IPS\Http\Url
	 */
	public function acpUrl()
	{
		return \IPS\Http\Url::internal( "app=nexus&module=payments&controller=transactions&do=view&id={$this->id}", 'admin' );
	}
	
	/**
	 * ACP Buttons
	 *
	 * @param	string	$ref	Referer
	 * @return	array
	 */
	public function buttons( $ref='v' )
	{
		$url = $this->acpUrl()->setQueryString( 'r', $ref );
		$return = array();
		
		/* Approve button */
		if ( $this->method and \in_array( $this->status, array( static::STATUS_WAITING, static::STATUS_HELD, static::STATUS_REVIEW ) ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_edit' ) )
		{
			$return['approve'] = array(
				'title'		=> $this->auth ? 'transaction_capture' : 'transaction_approve',
				'icon'		=> 'check',
				'link'		=> $url->setQueryString( array( 'do' => 'approve' ) )->csrf()->getSafeUrlFromFilters(),
				'data'		=> array( 'confirm' => '' )
			);
		}
		
		/* Review button */
		if ( \in_array( $this->status, array( static::STATUS_WAITING, static::STATUS_HELD ) ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_edit' ) )
		{
			$return['review'] = array(
				'title'		=> 'transaction_flag_review',
				'icon'		=> 'flag',
				'link'		=> $url->setQueryString( array( 'do' => 'review' ) )->csrf()->getSafeUrlFromFilters(),
			);
		}
				
		/* Void button */
		if ( $this->auth and \in_array( $this->status, array( static::STATUS_HELD, static::STATUS_REVIEW  ) ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_edit' ) )
		{
			$return['void'] = array(
				'title'		=> 'transaction_void',
				'icon'		=> 'times',
				'link'		=> $url->setQueryString( array( 'do' => 'void' ) )->csrf()->getSafeUrlFromFilters(),
				'data'		=> array( 'confirm' => '' )
			);
		}
		
		/* Cancel button for manual */
		elseif ( $this->status === static::STATUS_WAITING and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_edit' ) )
		{
			$return['void'] = array(
				'title'		=> 'cancel',
				'icon'		=> 'times',
				'link'		=> $url->setQueryString( array( 'do' => 'void', 'override' => 1 ) )->csrf(),
				'data'		=> array( 'confirm' => '' )
			);
		}
		
		/* Refund button */
		elseif ( \in_array( $this->status, array( static::STATUS_PAID, static::STATUS_HELD, static::STATUS_REVIEW, static::STATUS_PART_REFUNDED ) ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_refund' ) )
		{
			$return['refund'] = array(
				'title'		=> 'transaction_refund_credit',
				'icon'		=> 'reply',
				'link'		=> $url->setQueryString( array( 'do' => 'refund' ) ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'transaction_refund_credit_title', FALSE, array( 'sprintf' => array( $this->amount ) ) ) )
			);
		}
				
		/* Delete button */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'transactions_delete' ) )
		{
			$return['delete'] = array(
				'title'		=> 'delete',
				'icon'		=> 'times-circle',
				'link'		=> $url->setQueryString( 'do', 'delete' )->csrf()->getSafeUrlFromFilters(),
				'data'		=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('trans_delete_warning') )
			);
		}
		return $return;
	}
	
	/**
	 * History
	 *
	 * @return	array
	 */
	public function history()
	{
		$return = array();
		$extra = $this->extra;
		
		if ( isset( $extra['history'] ) )
		{
			return $extra['history'];
		}
		else
		{
			if ( !\in_array( $this->status, array( static::STATUS_PENDING, static::STATUS_WAITING, static::STATUS_GATEWAY_PENDING ) ) )
			{
				$return[] = array(
					's'		=> $this->status,
					'by'	=> isset( $extra['status_by'] ) ? $extra['status_by'] : NULL,
					'on'	=> isset( $extra['status_on'] ) ? $extra['status_on'] : NULL,
				);
			}
		}
		
		return $return;
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse			int						id				ID number
	 * @apiresponse			string					status			Status: 'okay' = Paid; 'pend' = Pending, waiting for gateway; 'wait' = Pending, manual approval required; 'hold' = Held for manual approval; 'revw' = Flagged for review; 'fail' = Failed; 'rfnd' = Refunded; 'prfd' = Partially refunded
	 * @apiresponse			int						invoiceId		Invoice ID Number
	 * @apiresponse			\IPS\nexus\Money		amount			Amount
	 * @apiresponse			\IPS\nexus\Money		refundAmount	If partially refunded, the amount that has been refunded
	 * @apiresponse			\IPS\nexus\Money		credit			If credited, the amount that has been credited
	 * @apiresponse			\IPS\nexus\Gateway		gateway			The gateway
	 * @clientapiresponse	string					gatewayId		Any ID number provided by the gateway to identify the transaction on their end
	 * @apiresponse			datetime				date			Date
	 * @apiresponse			\IPS\nexus\Customer		customer		Customer
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'			=> $this->id,
			'status'		=> $this->status,
			'invoiceId'		=> $this->invoice->id,
			'amount'		=> $this->amount->apiOutput( $authorizedMember ),
			'refundAmount'	=> $this->partial_refund ? $this->partial_refund->apiOutput( $authorizedMember ) : null,
			'creditAmount'	=> $this->credit ? $this->credit->apiOutput( $authorizedMember ) : null,
			'gateway'		=> $this->method ? $this->method->apiOutput( $authorizedMember ) : null,
			'gatewayId'		=> $this->gw_id,
			'date'			=> $this->date->rfc3339(),
			'customer'		=> $this->member ? $this->member->apiOutput( $authorizedMember ) : null
			
		);
	}
}

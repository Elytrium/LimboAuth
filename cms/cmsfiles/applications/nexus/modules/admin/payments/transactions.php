<?php
/**
 * @brief		Transactions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Feb 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Transactions
 */
class _transactions extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'transaction.css', 'nexus', 'admin' ) );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Create Table */
		$table = \IPS\nexus\Transaction::table( array( array( 't_status<>?', \IPS\nexus\Transaction::STATUS_PENDING ) ), \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=transactions' ), 't' );
		$table->filters = array(
			'trans_attention_required'	=> array( \IPS\Db::i()->in( 't_status', array( \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_WAITING, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_DISPUTED ) ) ),
		);
		$table->advancedSearch = array(
			't_id'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			't_status'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => \IPS\nexus\Transaction::statuses(), 'multiple' => TRUE ) ),
			't_member'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
			't_amount'	=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			't_method'	=> array( \IPS\Helpers\Table\SEARCH_NODE, array( 'class' => '\IPS\nexus\Gateway' ) ),
			't_date'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
		);
		$table->quickSearch = 't_id';
		
		/* Display */
		if ( isset( \IPS\Request::i()->attn ) and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( '( t_status=? OR t_status=? OR t_status=? )', \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_DISPUTED ) ) )
		{
			$table->filter = 'trans_attention_required';
		}
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_payments_transactions');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	public function view()
	{
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/8', 404, '' );
		}
				
		/* Output */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'transaction_number', FALSE, array( 'sprintf' => array( $transaction->id ) ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'transactions' )->view( $transaction );
	}
	
	/**
	 * Approve
	 *
	 * @return	void
	 */
	public function approve()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_edit' );
		\IPS\Session::i()->csrfCheck();
		
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/9', 404, '' );
		}
		$method = $transaction->method;

		/* Can we approve it? */
		if ( !$method or !\in_array( $transaction->status, array( \IPS\nexus\Transaction::STATUS_WAITING, \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING, \IPS\nexus\Transaction::STATUS_DISPUTED ) ) )
		{
			\IPS\Output::i()->error( 'transaction_status_err', '2X186/A', 403, '' );
		}
		
		/* Log it */
		if( $transaction->member )
		{
			$transaction->member->log( 'transaction', array(
				'type'		=> 'status',
				'status'	=> \IPS\nexus\Transaction::STATUS_PAID,
				'id'		=> $transaction->id
			) );
		}

		/* Do it */
		try
		{
			if ( $transaction->status !== \IPS\nexus\Transaction::STATUS_DISPUTED )
			{
				$transaction->capture();
			}
			$transaction->approve( \IPS\Member::loggedIn() );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '3X186/2', 500, '' );
		}
		catch ( \RuntimeException $e )
		{
			\IPS\Output::i()->error( 'transaction_capture_err', '3X186/3', 500, '' );
		}
		
		/* Send Email */
		$transaction->sendNotification();
				
		/* Redirect */
		if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->queueStatus )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack('tstatus_okay_set'), 'queue' => \IPS\nexus\extensions\core\AdminNotifications\Transaction::queueHtml( \IPS\Request::i()->queueStatus ) ) );
		}
		else
		{
			$this->_redirect( $transaction );
		}
	}
	
	/**
	 * Flag for review
	 *
	 * @return	void
	 */
	public function review()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_edit' );
		\IPS\Session::i()->csrfCheck();
		
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/C', 404, '' );
		}
		$method = $transaction->method;
		
		/* Can we flag it? */
		if ( !\in_array( $transaction->status, array( \IPS\nexus\Transaction::STATUS_WAITING, \IPS\nexus\Transaction::STATUS_HELD ) ) )
		{
			\IPS\Output::i()->error( 'transaction_status_err', '2X186/B', 403, '' );
		}
		
		/* Set it */
		$extra = $transaction->extra;
		$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REVIEW, 'on' => time(), 'by' => \IPS\Member::loggedIn()->member_id );
		$transaction->extra = $extra;
		$transaction->status = \IPS\nexus\Transaction::STATUS_REVIEW;
		$transaction->save();
		
		/* Log it */
		if( $transaction->member )
		{
			$transaction->member->log( 'transaction', array(
				'type'		=> 'status',
				'status'	=> \IPS\nexus\Transaction::STATUS_REVIEW,
				'id'		=> $transaction->id
			) );
		}
		
		/* Notification */
		\IPS\core\AdminNotification::send( 'nexus', 'Transaction', \IPS\nexus\Transaction::STATUS_REVIEW, TRUE, $transaction );
		
		/* Create a support request? */
		if ( \IPS\Settings::i()->nexus_revw_sa != -1 )
		{
			$extraData = array();
			if ( $transaction->member->member_id )
			{
				$extraData['member'] = $transaction->member->member_id;
			}
			else
			{
				$extraData['email'] = $transaction->invoice->guest_data['member']['email'];
			}
			
			$createUrl = \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=create');
			$key = md5( $createUrl );
			
			if ( \IPS\Settings::i()->nexus_revw_sa )
			{
				$extraData['stock_action'] = \IPS\Settings::i()->nexus_revw_sa;
				$_SESSION["wizard-{$key}-data"] = $extraData;
				$_SESSION["wizard-{$key}-step"] = 'request_details';
			}
			else
			{
				$_SESSION["wizard-{$key}-data"] = $extraData;
				if ( \count( \IPS\nexus\Support\StockAction::roots() ) )
				{
					$_SESSION["wizard-{$key}-step"] = 'stock_action';
				}
				else
				{
					$_SESSION["wizard-{$key}-step"] = 'request_details';
				}
			}
			
			$_SESSION["wizard-{$key}-data"]['transaction'] = $transaction->id;
			$_SESSION["wizard-{$key}-data"]['ref'] = isset( \IPS\Request::i()->r ) ? \IPS\Request::i()->r : 'v';
			
			\IPS\Output::i()->redirect( $createUrl );
		}
		
		/* Redirect */
		if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->queueStatus )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack('tstatus_revw_set'), 'queue' => \IPS\nexus\extensions\core\AdminNotifications\Transaction::queueHtml( \IPS\Request::i()->queueStatus ) ) );
		}
		else
		{
			$this->_redirect( $transaction );
		}
	}
	
	/**
	 * Void
	 *
	 * @return	void
	 */
	public function void()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_edit' );
		\IPS\Session::i()->csrfCheck();
		
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/4', 404, '' );
		}
		$method = $transaction->method;
		
		/* Can we void it? */
		if ( !\in_array( $transaction->status, array( \IPS\nexus\Transaction::STATUS_WAITING, \IPS\nexus\Transaction::STATUS_GATEWAY_PENDING ) ) and ( !$transaction->auth or !\in_array( $transaction->status, array( \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW ) ) ) )
		{
			\IPS\Output::i()->error( 'transaction_status_err', '2X186/5', 403, '' );
		}
		
		/* Void it */
		try
		{
			$transaction->void( $transaction );
		}
		catch ( \Exception $e )
		{
			if ( !isset( \IPS\Request::i()->override ) )
			{
				\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'transaction_void_err', FALSE, array( 'sprintf' => array( $transaction->acpUrl()->setQueryString( array( 'do' => 'void', 'override' => 1 ) ) ) ) ), '3X186/6', 500, '', array(), $e->getMessage() );
			}
		}
		
		/* Send Email */
		$transaction->sendNotification();
		
		/* Redirect */
		if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->queueStatus )
		{
			\IPS\Output::i()->json( array( 'message' => \IPS\Member::loggedIn()->language()->addToStack('tstatus_fail_set'), 'queue' => \IPS\nexus\extensions\core\AdminNotifications\Transaction::queueHtml( \IPS\Request::i()->queueStatus ) ) );
		}
		else
		{
			$this->_redirect( $transaction );
		}
	}	
	
	/**
	 * Refund
	 *
	 * @return	void
	 */
	public function refund()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_refund' );
		
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/D', 404, '' );
		}
		$method = $transaction->method;
		
		/* Can we refund it? */
		if ( !\in_array( $transaction->status, array( \IPS\nexus\Transaction::STATUS_PAID, \IPS\nexus\Transaction::STATUS_HELD, \IPS\nexus\Transaction::STATUS_REVIEW, \IPS\nexus\Transaction::STATUS_PART_REFUNDED, \IPS\nexus\Transaction::STATUS_DISPUTED ) ) )
		{
			\IPS\Output::i()->error( 'transaction_status_err', '2X186/E', 403, '' );
		}
		
		/* What are the refund methods? */
		$refundMethods = array();
		$refundMethodToggles = array( 'none' => array( 'refund_reverse_credit' ) );
		$refundReasons = array();
		if ( $method and $method::SUPPORTS_REFUNDS )
		{
			$refundMethods['gateway'] = 'transaction_refund';
			$refundMethodToggles['gateway'] = array( 'refund_reverse_credit' );
			if ( $method::SUPPORTS_PARTIAL_REFUNDS )
			{
				$refundMethodToggles['gateway'][] = 'refund_amount';
			}
			if ( $refundReasons = $method::refundReasons() )
			{
				$refundMethodToggles['gateway'][] = 'refund_reason';
			}
		}
		if ( $transaction->credit->amount->compare( $transaction->amount->amount ) === -1 )
		{
			$refundMethods['credit'] = 'refund_method_credit';
			$refundMethodToggles['credit'][] = 'refund_credit_amount';
		}
		$refundMethods['none'] = 'refund_method_none';
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'refund_method', ( $method and $method::SUPPORTS_REFUNDS ) ? 'gateway' : 'credit', TRUE, array( 'options' => $refundMethods, 'toggles' => $refundMethodToggles ) ) );
		if ( $refundReasons )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'refund_reason', NULL, FALSE, array( 'options' => $refundReasons ), NULL, NULL, NULL, 'refund_reason' ) );
		}
		if ( $method and $method::SUPPORTS_REFUNDS and $method::SUPPORTS_PARTIAL_REFUNDS )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'refund_amount', 0, TRUE, array(
				'unlimited'		=> 0,
				'unlimitedLang'	=> (
					$transaction->partial_refund->amount->isGreaterThanZero()
						? \IPS\Member::loggedIn()->language()->addToStack( 'refund_full_remaining', FALSE, array( 'sprintf' => array(
							new \IPS\nexus\Money( $transaction->amount->amount->subtract( $transaction->partial_refund->amount ), $transaction->currency ) )
						) )
						: \IPS\Member::loggedIn()->language()->addToStack( 'refund_full', FALSE, array( 'sprintf' => array( $transaction->amount ) ) )
				),
				'max'			=> (string) $transaction->amount->amount->subtract( $transaction->partial_refund->amount ),
				'decimals' 		=> TRUE
			), NULL, NULL, $transaction->amount->currency, 'refund_amount' ) );
			
			if ( $transaction->credit->amount->isGreaterThanZero() )
			{
				\IPS\Member::loggedIn()->language()->words['refund_amount_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('refund_amount_descwarn'), $transaction->credit );
			}
		}
		if ( $transaction->credit->amount->compare( $transaction->amount->amount ) === -1 )
		{
			$form->add( new \IPS\Helpers\Form\Number( 'refund_credit_amount', 0, TRUE, array(
				'unlimited'		=> 0,
				'unlimitedLang'	=> (
					$transaction->credit->amount->isGreaterThanZero()
						? \IPS\Member::loggedIn()->language()->addToStack( 'refund_full_remaining', FALSE, array( 'sprintf' => array(
							new \IPS\nexus\Money( $transaction->amount->amount->subtract( $transaction->credit->amount ), $transaction->currency ) )
						) )
						: \IPS\Member::loggedIn()->language()->addToStack( 'refund_full', FALSE, array( 'sprintf' => array( $transaction->amount ) ) )
				),
				'max'			=> (string) $transaction->amount->amount->subtract( $transaction->credit->amount ),
				'decimals' 		=> TRUE
			), NULL, NULL, $transaction->amount->currency, 'refund_credit_amount' ) );
			
			if ( $transaction->partial_refund->amount->isGreaterThanZero() )
			{
				\IPS\Member::loggedIn()->language()->words['refund_credit_amount_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('refund_credit_amount_descwarn'), $transaction->partial_refund );
			}
		}
		if ( $transaction->credit->amount->isGreaterThanZero() )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'refund_reverse_credit', TRUE, TRUE, array( 'togglesOn' => array( 'form_refund_reverse_credit_warning' ) ), NULL, NULL, NULL, 'refund_reverse_credit' ) );
			\IPS\Member::loggedIn()->language()->words['refund_reverse_credit'] = sprintf( \IPS\Member::loggedIn()->language()->get( 'refund_reverse_credit' ), $transaction->credit );
			
			$credits = $transaction->member->cm_credits;
			if ( $credits[ $transaction->amount->currency ]->amount->compare( $transaction->credit->amount ) === -1 )
			{
				\IPS\Member::loggedIn()->language()->words['refund_reverse_credit_warning'] = \IPS\Member::loggedIn()->language()->addToStack( 'account_credit_remove_neg' );
			}
		}
		if ( $transaction->invoice !== NULL and $transaction->invoice->status === \IPS\nexus\Invoice::STATUS_PAID )
		{
			$field = new \IPS\Helpers\Form\Radio( 'refund_invoice_status', \IPS\nexus\Invoice::STATUS_PENDING, TRUE, array(
				'options' => array(
					\IPS\nexus\Invoice::STATUS_PAID	=> 'refund_invoice_paid',
					\IPS\nexus\Invoice::STATUS_PENDING	=> 'refund_invoice_pending',
					\IPS\nexus\Invoice::STATUS_CANCELED	=> 'refund_invoice_canceled',
				),
				'toggles'	=> array(
					\IPS\nexus\Invoice::STATUS_PENDING	=> array( 'form_refund_invoice_status_warning' ),
					\IPS\nexus\Invoice::STATUS_CANCELED	=> array( 'form_refund_invoice_status_warning' )
				)
			) );
			$field->warningBox = \IPS\Theme::i()->getTemplate('invoices')->unpaidConsequences( $transaction->invoice );
			$form->add( $field );
		}
		if ( $billingAgreement = $transaction->billing_agreement )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'refund_cancel_billing_agreement', TRUE, NULL, array( 'togglesOff' => array( 'form_refund_cancel_billing_agreement_warning' ) ) ) );
			
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( 't_billing_agreement=? AND t_id<?', $billingAgreement->id, $transaction->id ) )->first() )
			{
				unset( \IPS\Member::loggedIn()->language()->words['refund_cancel_billing_agreement_warning'] );
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Handle billing agreement */
			if ( $transaction->billing_agreement )
			{
				if ( isset( $values['refund_cancel_billing_agreement'] ) and $values['refund_cancel_billing_agreement'] )
				{
					try
					{
						$transaction->billing_agreement->cancel();
					}
					catch ( \Exception $e )
					{
						\IPS\Output::i()->error( 'billing_agreement_cancel_error', '3X186/G', 500, '', array(), $e->getMessage() );
					}
				}
			}
			
			/* Reverse credit */
			if ( $values['refund_method'] !== 'credit' and isset( $values['refund_reverse_credit'] ) and $values['refund_reverse_credit'] )
			{
				$transaction->reverseCredit();
			}
			
			/* Refund */
			try
			{
				$amount = NULL;
				if ( $values['refund_method'] === 'gateway' and isset( $values['refund_amount'] ) )
				{
					$amount = $values['refund_amount'];
				}
				elseif ( $values['refund_method'] === 'credit' and isset( $values['refund_credit_amount'] ) )
				{
					$amount = $values['refund_credit_amount'];
				}
								
				$transaction->refund( $values['refund_method'], $amount, isset( $values['refund_reason'] ) ? $values['refund_reason'] : NULL );
			}
			catch ( \LogicException $e )
			{
				\IPS\Output::i()->error( $e->getMessage(), '1X186/1', 500, '' );
			}
			catch ( \RuntimeException $e )
			{
				\IPS\Output::i()->error( 'refund_failed', '3X186/7', 500, '' );
			}
			
			/* Handle invoice */
			if( $transaction->invoice !== NULL )
			{
				if ( isset( $values['refund_invoice_status'] ) and $values['refund_invoice_status'] !== \IPS\nexus\Invoice::STATUS_PAID )
				{
					$transaction->invoice->markUnpaid( $values['refund_invoice_status'] );

					if( $transaction->invoice->member )
					{
						$transaction->invoice->member->log( 'invoice', array(
							'type'	=> 'status',
							'new'	=> $values['refund_invoice_status'],
							'id'	=> $transaction->invoice->id,
							'title' => $transaction->invoice->title
						) );
					}
				}

				/* Send Email */
				$transaction->sendNotification();
			}
						
			/* Redirect */
			$this->_redirect( $transaction );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'transaction_refund_title', FALSE, array( 'sprintf' => array( $transaction->amount ) ) );
		\IPS\Output::i()->output = $form;		
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'transactions_delete' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Load Transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X186/F', 404, '' );
		}
		
		/* Delete */
		$transaction->delete();
		
		/* Log it */
		try
		{
			if( $transaction->member )
			{
				$transaction->member->log( 'transaction', array(
					'type'		=> 'delete',
					'id'		=> $transaction->id,
					'method'	=> $transaction->method ? $transaction->method->id : NULL,
				) );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			// If the member no longer exists, we just won't log it
		}
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=transactions')->getSafeUrlFromFilters());
	}
	
	
	/**
	 * Redirect
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	The transaction
	 * @return	void
	 */
	protected function _redirect( \IPS\nexus\Transaction $transaction )
	{
		if ( isset( \IPS\Request::i()->r ) )
		{
			switch ( \IPS\Request::i()->r )
			{
				case 'v':
					\IPS\Output::i()->redirect( $transaction->acpUrl()->getSafeUrlFromFilters() );
					break;
					
				case 'i':
					\IPS\Output::i()->redirect( $transaction->invoice->acpUrl()->getSafeUrlFromFilters() );
					break;
				
				case 'c':
					\IPS\Output::i()->redirect( $transaction->member->acpUrl()->getSafeUrlFromFilters());
					break;
				
				case 't':
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=transactions')->getSafeUrlFromFilters() );
					break;
					
				case 'n':
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=overview&controller=notifications')->getSafeUrlFromFilters());
					break;
			}
		}
		
		\IPS\Output::i()->redirect( $transaction->acpUrl()->getSafeUrlFromFilters());
	}
}
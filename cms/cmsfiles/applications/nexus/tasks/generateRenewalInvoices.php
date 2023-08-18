<?php
/**
 * @brief		Generate Renewal Invoices Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		01 Apr 2014
 */

namespace IPS\nexus\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Generate Renewal Invoices Task
 */
class _generateRenewalInvoices extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
        /* Get purchases grouped by member and currency */
        $select = $this->_getSelectQuery();
        $log = \IPS\Db::_replaceBinds( $select->query, $select->binds ) . "\n" . \count( $select ) . " matches\n\n";
		$availableTaxes = \IPS\nexus\Tax::roots();

		$groupedPurchases = array();
		foreach ( new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\nexus\Purchase' ) as $purchase )
		{
			/* If the member does not exist, we should not lock the task */
			try
			{
				$groupedPurchases[ $purchase->member->member_id ][ $purchase->renewal_currency ][ $purchase->id ] = $purchase;
			}
			catch( \OutOfRangeException $e )
			{
				/* Set the purchase inactive so we don't try again. */
				$purchase->active = 0;
				$purchase->save();
			}
		}
		
		/* Loop */
		foreach ( $groupedPurchases as $memberId => $currencies )
		{
			$member = \IPS\nexus\Customer::load( $memberId );
			foreach ( $currencies as $currency => $purchases )
			{		
				$log .= "Member {$memberId}, {$currency}: " . \count( $purchases ) . " purchase(s) to be renewed: " . implode( ', ', array_keys( $purchases ) ) . ". ";
						
				/* Create Invoice */
				$invoice = new \IPS\nexus\Invoice;
				$invoice->system = TRUE;
				$invoice->currency = $currency;
				$invoice->member = $member;
				$invoice->billaddress = $member->primaryBillingAddress();
				$items = array();
				
				foreach ( $purchases as $purchase )
				{
					/* Check the renewal is valid */
					if( $purchase->canBeRenewed() )
					{
						$items[] = $purchase;
						continue;
					}

					/* Remove renewals for this purchase */
					$log .= "Purchase {$purchase->id} cannot be renewed. ";
					$purchase->renewals = NULL;
					$purchase->member->log( 'purchase', array( 'type' => 'info', 'id' => $purchase->id, 'name' => $purchase->name, 'info' => 'remove_renewals' ) );
					$purchase->can_reactivate = TRUE;
					$purchase->save();
				}

				/* Continue to next invoice if no items left */
				if( !\count( $items ) )
				{
					continue;
				}

				/* Add items to invoice */
				foreach( $items as $item )
				{
					$invoice->addItem( \IPS\nexus\Invoice\Item\Renewal::create( $item ) );
				}
				$invoice->save();
				$log .= "Invoice {$invoice->id} generated... ";
				
				/* Try to take payment automatically, but *only* if we have a billing address (i.e. the customer has a primary billing address set)
					otherwise we don't know how we're taxing this and the customer will need to manually come and pay it - we can skip this if tax has not been configured */
				if ( $invoice->billaddress OR \count( $availableTaxes ) === 0 )
				{
					/* Nothing to pay? */
					if ( $invoice->amountToPay()->amount->isZero() )
					{
						$log .= "Nothing to pay!";
						
						$extra = $invoice->status_extra;
						$extra['type']		= 'zero';
						$invoice->status_extra = $extra;
						$invoice->markPaid();
					}
	
					/* Charge what we can to account credit */
					if ( $invoice->status !== $invoice::STATUS_PAID )
					{
						$credits = $member->cm_credits;
						if ( isset( $credits[ $currency ] ) )
						{
							$credit = $credits[$currency]->amount;
							if( $credit->isGreaterThanZero() )
							{
								$take = NULL;
								/* If credit is equal or larger than invoice value */
								if ( \in_array( $credit->compare( $invoice->total->amount ), [ 0, 1 ] ) )
								{
									$take = $invoice->total->amount;
								}
								else
								{
									/* Only use credit if amount remaining is greater than card gateway min amount */
									if( $invoice->total->amount->subtract( $credit ) > new \IPS\Math\Number( '0.50' ) )
									{
										$take = $credit;
									}
								}

								if( $take )
								{
									$log .= "{$credit} account credit available... ";

									$transaction = new \IPS\nexus\Transaction;
									$transaction->member = $member;
									$transaction->invoice = $invoice;
									$transaction->amount = new \IPS\nexus\Money( $take, $currency );
									$transaction->extra = array('automatic' => TRUE);
									$transaction->save();
									$transaction->approve();

									$log .= "Transaction {$transaction->id} generated... ";

									$member->log( 'transaction', array(
										'type' => 'paid',
										'status' => \IPS\nexus\Transaction::STATUS_PAID,
										'id' => $transaction->id,
										'invoice_id' => $invoice->id,
										'invoice_title' => $invoice->title,
										'automatic' => TRUE,
									), FALSE );

									$credits[$currency]->amount = $credits[$currency]->amount->subtract( $take );
									$member->cm_credits = $credits;
									$member->save();

									$invoice->status = $transaction->invoice->status;
								}

							}
						}
					}
					/* Charge to card */
					if ( $invoice->status !== $invoice::STATUS_PAID )
					{
                        /* Figure out which payment methods are allowed in this invoice */
                        $allowedPaymentMethods = array();
                        foreach( $invoice->items as $item )
                        {
                            if( \is_array( $item->paymentMethodIds ) and !\in_array( '*', $item->paymentMethodIds ) )
                            {
                                $allowedPaymentMethods = array_merge( $allowedPaymentMethods, $item->paymentMethodIds );
                            }
                        }

                        $cardWhere = array(
                            array( 'card_member=?', $member->member_id )
                        );
                        if( \count( $allowedPaymentMethods ) )
                        {
                            $cardWhere[] = array( \IPS\Db::i()->in( 'card_method', $allowedPaymentMethods ) );
                        }

						foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_customer_cards', $cardWhere ), 'IPS\nexus\Customer\CreditCard' ) as $card )
						{
							$log .= "Attempting card {$card->id}... ";
							
							try
							{
								$cardDetails = $card->card; // We're just checking this doesn't throw an exception
								
								$amountToPay = $invoice->amountToPay();
								$gateway = $card->method;
		
								$transaction = new \IPS\nexus\Transaction;
								$transaction->member = $member;
								$transaction->invoice = $invoice;
								$transaction->method = $gateway;
								$transaction->amount = $amountToPay;
								$transaction->currency = $currency;
								$transaction->extra = array( 'automatic' => TRUE );
		
								try
								{
									$transaction->auth = $gateway->auth( $transaction, array(
										( $gateway->id . '_card' ) => $card
									), NULL, array(), 'renewal' );
									$transaction->capture();
		
									$transaction->member->log( 'transaction', array(
										'type'			=> 'paid',
										'status'		=> \IPS\nexus\Transaction::STATUS_PAID,
										'id'			=> $transaction->id,
										'invoice_id'	=> $invoice->id,
										'invoice_title'	=> $invoice->title,
										'automatic'		=> TRUE,
									), FALSE );
		
									$transaction->approve();
									
									$log .= "Transaction {$transaction->id} approved! ";
									
									break;
								}
								catch ( \Exception $e )
								{
									$transaction->status = \IPS\nexus\Transaction::STATUS_REFUSED;
									$extra = $transaction->extra;
									$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'noteRaw' => $e->getMessage() );
									$transaction->extra = $extra;
									$transaction->save();
									
									$log .= "Transaction {$transaction->id} failed. ";
									
									$transaction->member->log( 'transaction', array(
										'type'			=> 'paid',
										'status'		=> \IPS\nexus\Transaction::STATUS_REFUSED,
										'id'			=> $transaction->id,
										'invoice_id'	=> $invoice->id,
										'invoice_title'	=> $invoice->title,
										'automatic'		=> TRUE,
									), FALSE );
								}
		
								$invoice->status = $transaction->invoice->status;
							}
							// error with card, move on
							catch ( \Exception $e ){}
						}
					}
				}
				
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
				$log .= "Final status: {$invoice->status}\n";
			}
		}
						
		return $log;
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}

	/**
	 * Get Purchases Query
	 *
	 * @return \IPS\Db\Select
	 * @throws \Exception
	 */
	protected function _getSelectQuery(): \IPS\Db\Select
	{
		/* What's out cutoff? */
		$renewalDate = \IPS\DateTime::create();
		if( \IPS\Settings::i()->cm_invoice_generate )
		{
			$renewalDate->add( new \DateInterval( 'PT' . \IPS\Settings::i()->cm_invoice_generate . 'H' )  );
		}

		return \IPS\Db::i()->select( 'ps.*', [ 'nexus_purchases', 'ps' ],
			[
				"ps_cancelled=0 AND ps_renewals>0 AND ps_invoice_pending=0 AND ps_active=1 AND ps_expire>0 AND ps_expire<? AND (ps_billing_agreement IS NULL OR ba.ba_canceled=1) AND ( ps_grouped_renewals='' OR ps_grouped_renewals IS NULL )",
				$renewalDate->getTimestamp()
			], 'ps_member', 50 )
			->join( [ 'nexus_billing_agreements', 'ba' ], 'ps.ps_billing_agreement=ba.ba_id' );
	}
}
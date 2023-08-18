<?php
/**
 * @brief		PayPal Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayPal Gateway
 */
class _PayPal extends \IPS\nexus\Gateway
{
	/* !Features */
	
	const SUPPORTS_REFUNDS = TRUE;
	const SUPPORTS_PARTIAL_REFUNDS = TRUE;
	
	/**
	 * Check the gateway can process this...
	 *
	 * @param	$amount			\IPS\nexus\Money		The amount
	 * @param	$billingAddress	\IPS\GeoLocation|NULL	The billing address, which may be NULL if one if not provided
	 * @param	$customer		\IPS\nexus\Customer		The customer (Default NULL value is for backwards compatibility - it should always be provided.)
	 * @param	array			$recurrings				Details about recurring costs
	 * @return	bool
	 */
	public function checkValidity( \IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress = NULL, \IPS\nexus\Customer $customer = NULL, $recurrings = array() )
	{		
		$settings = json_decode( $this->settings, TRUE );

		/* Card payments require name and billing address */
		if ( isset( $settings['type'] ) and $settings['type'] === 'card' and ( !$customer->cm_first_name or !$customer->cm_last_name or !$billingAddress ) )
		{
			return FALSE;
		}

		/* Check transaction limit */
		switch ( $amount->currency )
		{
			case 'AUD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '12500' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'BRL':
				if ( $amount->amount->compare( new \IPS\Math\Number( '20000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'CAD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '12500' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'CZK':
				if ( $amount->amount->compare( new \IPS\Math\Number( '240000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'DKK':
				if ( $amount->amount->compare( new \IPS\Math\Number( '60000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'EUR':
				if ( $amount->amount->compare( new \IPS\Math\Number( '8000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'GBP':
				if ( $amount->amount->compare( new \IPS\Math\Number( '5500' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'HKD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '80000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'HUF':
				if ( $amount->amount->compare( new \IPS\Math\Number( '2000000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'ILS':
				if ( $amount->amount->compare( new \IPS\Math\Number( '40000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'JPY':
				if ( $amount->amount->compare( new \IPS\Math\Number( '1000000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'MYR':
				if ( $amount->amount->compare( new \IPS\Math\Number( '40000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'MXN':
				if ( $amount->amount->compare( new \IPS\Math\Number( '110000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'TWD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '330000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'NZD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '15000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'NOK':
				if ( $amount->amount->compare( new \IPS\Math\Number( '70000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'PHP':
				if ( $amount->amount->compare( new \IPS\Math\Number( '500000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'PLN':
				if ( $amount->amount->compare( new \IPS\Math\Number( '32000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'RUB':
				if ( $amount->amount->compare( new \IPS\Math\Number( '550000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'SGD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '16000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'SEK':
				if ( $amount->amount->compare( new \IPS\Math\Number( '80000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'CHF':
				if ( $amount->amount->compare( new \IPS\Math\Number( '13000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'THB':
				if ( $amount->amount->compare( new \IPS\Math\Number( '360000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'TRY':
				if ( $amount->amount->compare( new \IPS\Math\Number( '25000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			case 'USD':
				if ( $amount->amount->compare( new \IPS\Math\Number( '10000' ) ) !== -1 )
				{
					return FALSE;
				}
				break;
			default:
				return FALSE;	
		}
		
		/* Pass to parent */
		return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
	}

	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		$settings = json_decode( $this->settings, TRUE );
		if( isset( $settings['type'] ) and $settings['type'] === 'card' )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'gateway_deprecated', FALSE, array( 'sprintf' => 'PayPal Credit Card' ) );
		}

		return null;
	}
	
	/**
	 * Can store cards?
	 *
	 * @param	bool	$adminCreatableOnly	If TRUE, will only return gateways where the admin (opposed to the user) can create a new option
	 * @return	bool
	 */
	public function canStoreCards( $adminCreatableOnly = FALSE )
	{
		$settings = json_decode( $this->settings, TRUE );
		return ( isset( $settings['type'] ) and $settings['type'] === 'card' and isset( $settings['vault'] ) and $settings['vault'] );
	}
	
	/**
	 * Admin can manually charge using this gateway?
	 *
	 * @param	\IPS\nexus\Customer	$customer	The customer we're wanting to charge
	 * @return	bool
	 */
	public function canAdminCharge( \IPS\nexus\Customer $customer )
	{
		$settings = json_decode( $this->settings, TRUE );
		return ( isset( $settings['type'] ) and $settings['type'] === 'card' );
	}
	
	/**
	 * Supports billing agreements?
	 *
	 * @return	bool
	 */
	public function billingAgreements()
	{
		$settings = json_decode( $this->settings, TRUE );
		return ( ( isset( $settings['type'] ) and $settings['type'] === 'paypal' ) or !isset( $settings['type'] ) ) and ( isset( $settings['billing_agreements'] ) and \in_array( $settings['billing_agreements'], array( 'required', 'optional' ) ) );
	}
		
	/* !Payment Gateway */
	
	/**
	 * Payment Screen Fields
	 *
	 * @param	\IPS\nexus\Invoice		$invoice	Invoice
	 * @param	\IPS\nexus\Money		$amount		The amount to pay now
	 * @param	\IPS\nexus\Customer		$member		The member the payment screen is for (if in the ACP charging to a member's card) or NULL for currently logged in member
	 * @param	array					$recurrings	Details about recurring costs
	 * @param	bool					$type		'checkout' means the cusotmer is doing this on the normal checkout screen, 'admin' means the admin is doing this in the ACP, 'card' means the user is just adding a card
	 * @return	array
	 */
	public function paymentScreen( \IPS\nexus\Invoice $invoice, \IPS\nexus\Money $amount, \IPS\nexus\Customer $member = NULL, $recurrings = array(), $type = 'checkout' )
	{
		$settings = json_decode( $this->settings, TRUE );

		if ( isset( $settings['type'] ) and $settings['type'] === 'card' )
		{
			return array( 'card' => new \IPS\nexus\Form\CreditCard( $this->id . '_card', NULL, TRUE, array(
				'types' 	=> array( \IPS\nexus\CreditCard::TYPE_VISA, \IPS\nexus\CreditCard::TYPE_MASTERCARD, \IPS\nexus\CreditCard::TYPE_DISCOVER, \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS ),
				'save'		=> ( isset( $settings['vault'] ) and $settings['vault'] ) ? $this : NULL,
				'member'	=> $member
			) ) );
		}
		elseif ( isset( $settings['billing_agreements'] ) and $settings['billing_agreements'] == 'optional' and static::_canProcessRecurringsAsBillingAgreement( $recurrings ) and $invoice->billaddress )
		{
			return array( 'billing_agreement' => new \IPS\Helpers\Form\Checkbox( 'paypal_billing_agreement', TRUE, FALSE ) );
		}
		return array();
	}
	
	/**
	 * Authorize
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	array|\IPS\nexus\Customer\CreditCard	$values			Values from form OR a stored card object if this gateway supports them
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @param	array									$recurrings		Details about recurring costs
	 * @param	string|NULL								$source			'checkout' if the customer is doing this at a normal checkout, 'renewal' is an automatically generated renewal invoice, 'manual' is admin manually charging. NULL is unknown
	 * @return	\IPS\DateTime|NULL						Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException							Message will be displayed to user
	 */
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array(), $source = NULL )
	{
		/* We need a transaction ID */
		$transaction->save();

		/* Do it */
		$settings = json_decode( $this->settings, TRUE );

		if ( isset( $settings['type'] ) and $settings['type'] === 'card' )
		{
			return $this->_cardAuth( \is_array( $values ) ? $values[ $this->id . '_card' ] : $values, $transaction, $maxMind );
		}
		else
		{
			if ( static::_canProcessRecurringsAsBillingAgreement( $recurrings ) and ( $settings['billing_agreements'] == 'required' or ( $settings['billing_agreements'] == 'optional' and $values['paypal_billing_agreement'] ) ) )
			{
				foreach ( $recurrings as $recurrance )
				{
					break;
				}
				return $this->_billingAgreementAuth( $transaction, $maxMind, $recurrance['term'], $recurrance['items'] );
			}
			else
			{
				return $this->_paypalAuth( $transaction, $maxMind );
			}
		}
	}

	/**
	 * Authorize Card Payment
	 *
	 * @param	\IPS\nexus\CreditCard|\IPS\nexus\Customer\CreditCard	$card	The card to charge
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made
	 * @param	string|NULL								$source			'checkout' if the customer is doing this at a normal checkout, 'renewal' is an automatically generated renewal invoice, 'manual' is admin manually charging. NULL is unknown
	 * @return	\IPS\DateTime|NULL		Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException			Message will be displayed to user
	 */
	protected function _cardAuth( $card, \IPS\nexus\Transaction $transaction, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, ?string $source = NULL )
	{
		/* Stored Card */
		if ( $card instanceof \IPS\nexus\Customer\CreditCard )
		{
			$payer = array(
				'payment_method'		=> 'credit_card',
				'funding_instruments'	=> array(
					array(
						'credit_card_token'	=> array(
							'credit_card_id'	=> $card->data
						)
					)
				)
			);
		}
		/* New Card */
		else
		{
			if ( $card->save and !$transaction->member->member_id )
			{
				$transaction->member = $transaction->invoice->createAccountForGuest();
				\IPS\Session::i()->setMember( $transaction->member );
				\IPS\Member\Device::loadOrCreate( $transaction->member, FALSE )->updateAfterAuthentication( NULL );
			}

			if ( $maxMind )
			{
				$maxMind->setCard( $card );
			}
			
			switch ( $card->type )
			{
				case \IPS\nexus\CreditCard::TYPE_VISA:
					$cardType = 'visa';
					break;
				case \IPS\nexus\CreditCard::TYPE_MASTERCARD:
					$cardType = 'mastercard';
					break;
				case \IPS\nexus\CreditCard::TYPE_DISCOVER:
					$cardType = 'discover';
					break;
				case \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS:
					$cardType = 'amex';
					break;
			}

			$payer = array(
				'payment_method'		=> 'credit_card',
				'funding_instruments'	=> array(
					array(
						'credit_card'		=> array(
							'number'			=> $card->number,
							'type'				=> $cardType,
							'expire_month'		=> \intval( $card->expMonth ),
							'expire_year'		=> \intval( $card->expYear ),
							'cvv2'				=> $card->ccv,
							'first_name'		=> $this->_getFirstName( $transaction ),
							'last_name'			=> $this->_getLastName( $transaction ),
							'billing_address'	=> $this->_getAddress( $transaction->invoice->billaddress, $transaction->member, 'card' )
						)
					),
				)
			);
		}

		try
		{
			/* Send the request */
			$response = $this->api( 'payments/payment', array(
				'intent'		=> 'authorize',
				'payer'			=> $payer,
				'transactions'	=> array( $this->_getTransactions( $transaction ) ),
				'redirect_urls'	=> array(
					'return_url'	=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/paypal.php?nexusTransactionId=' . $transaction->id,
					'cancel_url'	=> (string) $transaction->invoice->checkoutUrl(),
				)
			) );
		}
		catch( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			$this->processException( $transaction, $e );
			throw $e;
		}
		
		/* Set transaction data */	
		$transaction->gw_id = $response['transactions'][0]['related_resources'][0]['authorization']['id']; // The transaction ID for the authorization. At capture, it will be updated again to the capture transaction ID
		
		/* Save the card first if the user wants */
		if ( $card->save )
		{			
			try
			{
				$storedCard = new \IPS\nexus\Gateway\PayPal\CreditCard;
				$storedCard->member = $transaction->member;
				$storedCard->method = $this;
				$storedCard->card = $card;
				$storedCard->save();
			}
			catch ( \Exception $e ) {  /* If there's any issue with saving (which may happen for a duplicate card) we can just carry on since we already auth'd */ }
		}
		
		/* And return */
		return \IPS\DateTime::ts( strtotime( $response['transactions'][0]['related_resources'][0]['authorization']['valid_until'] ) );
	}
	
	/**
	 * Authorize PayPal Payment
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @return	\IPS\DateTime|NULL		Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException			Message will be displayed to user
	 */
	protected function _paypalAuth( \IPS\nexus\Transaction $transaction, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL )
	{
		/* Send the request */
		try
		{
			$response = $this->api( 'checkout/orders', array(
				'intent'		=> 'AUTHORIZE',
				'purchase_units' => $this->_getPurchaseUnits( $transaction ),
				'payment_source' => array(
					'paypal' => array(
						'experience_context' => array(
							'return_url'	=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/paypal.php?nexusTransactionId=' . $transaction->id,
							'cancel_url'	=> (string) $transaction->invoice->checkoutUrl(),
						),
						'email_address' => $transaction->member->email,
						'name' => array(
							'given_name' => $this->_getFirstName( $transaction ),
							'surname' => $this->_getLastName( $transaction )
						),
						'address' => ( $transaction->invoice->billaddress instanceof \IPS\GeoLocation ) ? $this->_getAddress( $transaction->invoice->billaddress, $transaction->member ) : NULL,
					)
				)
			), 'POST', true, null, md5( (string) $transaction->invoice->checkoutUrl() . ';' . $transaction->id ), 2 );
		}
		catch( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			$this->processException( $transaction, $e );
			throw $e;
		}

		/* Set transaction data */		
		$transaction->gw_id = $response['id']; // This is a payment ID ("PAY-XXX"). At this time we do not have a real transaction ID
		$transaction->save();
		
		/* Redirect */
		foreach ( $response['links'] as $link )
		{
			if ( $link['rel'] === 'payer-action' )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $link['href'] ) );
			}
		}

		throw new \RuntimeException;
	}
	
	/**
	 * Authorize Billing Agreement
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @param	\IPS\nexus\Purchase\RenewalTerm			$term			Renewal Term
	 * @param	array									$items			Items
	 * @return	\IPS\DateTime|NULL		Auth is valid until or NULL to indicate auth is good forever
	 * @throws	\LogicException			Message will be displayed to user
	 */
	protected function _billingAgreementAuth( \IPS\nexus\Transaction $transaction, ?\IPS\nexus\Fraud\MaxMind\Request $maxMind, \IPS\nexus\Purchase\RenewalTerm $term, $items )
	{
		$settings = json_decode( $this->settings, TRUE );
		
		/* Work out the name */
		$titles = array();
		$initialTerm = null;
		foreach ( $items as $item )
		{
			$titles[] = ( $item->name . ( $item->quantity > 1 ? " x{$item->quantity}" : '' ) );

			if( isset( $item->initialInterval ) AND $item->initialInterval instanceof \DateInterval )
			{
				$initialTerm = $item->initialInterval;
			}
		}

		$title = implode( ', ', $titles );
		if ( mb_strlen( $title ) > 127 )
		{
			$title = mb_substr( $title, 0, 124 ) . '...';
		}
		$description = sprintf( $transaction->member->language()->get('transaction_number'), $transaction->id );
		
		/* Create a product */
		$product = $this->api( 'catalogs/products', array(
			'name'			=> $title,
			'description'	=> $description,
			'type'			=> 'DIGITAL',
		) );

		/* Set up the billing cycles */
		$billingCycles = array();
		$sequence = 1;
		$renewalAmountIncludingTax = $term->cost->amount->multiply( new \IPS\Math\Number( number_format( $term->tax ? ( ( 1 + $term->tax->rate( $transaction->invoice->billaddress ) ) ) : 1, 4, '.', '' ) ) );

		/* If we have an initial term that is different from the renewal, start with that */
		if( $initialTerm instanceof \DateInterval OR $transaction->amount->amount->compare( $renewalAmountIncludingTax ) !== 0 )
		{
			$intervalToUse = $initialTerm instanceof \DateInterval ? $initialTerm : $term->interval;
			$billingCycles[] = array(
				'pricing_scheme'	=> array(
					'fixed_price'		=> array(
						'currency_code'		=> $transaction->amount->currency,
						'value'				=> $transaction->amount->amountAsString()
					),
				),
				'frequency'			=> static::_getFrequencyFromInterval( $intervalToUse ),
				'tenure_type'		=> 'TRIAL',
				'sequence'			=> $sequence,
				'total_cycles'		=> 1
			);
			$sequence++;
		}

		/* And now the regular renewals */
		$billingCycles[] = array(
			'pricing_scheme'	=> array(
				'fixed_price'		=> array(
					'currency_code'		=> $term->cost->currency,
					'value'				=> ( new \IPS\nexus\Money( $renewalAmountIncludingTax, $term->cost->currency ) )->amountAsString()
				),
			),
			'frequency'			=> static::_getFrequencyFromInterval( $term->interval ),
			'tenure_type'		=> 'REGULAR',
			'sequence'			=> $sequence,
			'total_cycles'		=> 0
		);
		
		/* Create a plan */
		$planDetails = array(
			'product_id'				=> $product['id'],
			'name'						=> $title,
			'description'				=> $description,
			'billing_cycles'			=> $billingCycles,
			'payment_preferences'		=> array(
				'auto_bill_outstanding'		=> FALSE,
				'payment_failure_threshold'	=> \intval( $settings['billing_agreement_allowed_fails'] )
			),
			'taxes'						=> array(
				'percentage'				=> $term->tax ? ( $term->tax->rate( $transaction->invoice->billaddress ) * 100 ) : '0',
				'inclusive'					=> TRUE
			),
			'quantity_supported'		=> FALSE
		);

		$plan = $this->api( 'billing/plans', $planDetails );
		
		/* Create a subscription */
		$subscription = $this->api( 'billing/subscriptions', array(
			'plan_id'				=> $plan['id'],
			'quantity'				=> '1',
			'subscriber'			=> array(
				'name'					=> array(
					'given_name'				=> $this->_getFirstName( $transaction ),
					'surname'				=> $this->_getLastName( $transaction )
				),
				'email_address'			=> $transaction->member->email,
			),
			'application_context'	=> array(
				'brand_name'			=> \IPS\Settings::i()->board_name,
				'locale'				=> $transaction->member->language()->bcp47(),
				'shipping_preference'	=> 'NO_SHIPPING',
				'user_action'			=> 'SUBSCRIBE_NOW',
				'payment_method'		=> array(
					'payer_selected'			=> 'PAYPAL',
					'payee_preferred'		=> 'UNRESTRICTED',
					'category'				=> 'CUSTOMER_PRESENT_RECURRING_FIRST',
				),
				'cancel_url'			=> (string) $transaction->invoice->checkoutUrl(),
				'return_url'			=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/paypal.php?subscription=1&nexusTransactionId=' . $transaction->id,
			)
		) );
		
		/* Redirect */
		foreach ( $subscription['links'] as $link )
		{
			if ( $link['rel'] === 'approve' )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $link['href'] ) );
			}
		}
		throw new \RuntimeException;
	}
	
	/**
	 * Void
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	void
	 * @throws	\Exception
	 */
	public function void( \IPS\nexus\Transaction $transaction )
	{
		/* If this is the initial transaction for a billing agreement which hasn't
			been processed yet, cancel the billing agreement. If it's a subscription it may still be the first payment */
		if ( $transaction->status === $transaction::STATUS_GATEWAY_PENDING and $transaction->billing_agreement )
		{
			$transaction->billing_agreement->cancel();
		}

		/* If the transaction is not in a 'hold' state, it cannot be voided and must be refunded instead */
		if( !\in_array( $transaction->status, array( $transaction::STATUS_HELD, $transaction::STATUS_REVIEW ) ) || !$transaction->auth )
		{
			return $this->refund( $transaction );
		}
		
		/* Try to find the authorization ID */
		if ( mb_substr( $transaction->gw_id, 0, 4 ) === 'PAY-' )
		{
			$authId = NULL;
			try
			{
				$payment = $this->api( "payments/payment/{$transaction->gw_id}", NULL, 'get' );
				foreach ( $payment['transactions'][0]['related_resources'] as $rr )
				{
					if ( isset( $rr['authorization'] ) )
					{
						$authId = $rr['authorization']['id'];
					}
				}

				if ( !$authId )
				{
					throw new \RuntimeException;
				}
			}
			/* Let's try the gateway id as the auth id */
			catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
			{
				$authId = $transaction->gw_id;
			}
		}
		else
		{
			$authId = $transaction->gw_id;
			if( $transaction->billing_agreement )
			{
				try
				{
					return $this->refund( $transaction );
				}
				catch ( \IPS\nexus\Gateway\PayPal\Exception $e ){}
			}
		}
		
		/* Void it */
		return $this->api( "payments/authorizations/{$authId}/void", null, 'POST', TRUE, NULL, NULL, 2 );
	}
		
	/**
	 * Capture
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	bool
	 * @throws	\LogicException
	 */
	public function capture( \IPS\nexus\Transaction $transaction )
	{
		/* If this is for a billing agreement, it should have automatically been captured - all we need to do is check its status, and if
			it has been captured, record the ID - otherwise return an error explaining what's going on.
			The only time we normally get here is if its the initial transaction and PayPal didn't take payment before the user was redirected back
			(in which case the status will be STATUS_GATEWAY_PENDING) but if has been an issue (with Maxmind, for example) it could be STATUS_HELD */
		if ( $billingAgreement = $transaction->billing_agreement )
		{
			$response = 'WAIT';
			/* Subscriptions API */
			if( $data = $billingAgreement->_getData() AND isset( $data['plan_id'] ) )
			{
				$transactions = $this->api( "billing/subscriptions/{$transaction->billing_agreement->gw_id}/transactions?start_time=" . \IPS\DateTime::ts( $transaction->date->getTimestamp() - 86400 )->rfc3339() . '&end_time=' . \IPS\DateTime::ts( time() )->rfc3339(), NULL, 'get' );
				foreach ( $transactions['transactions'] as $t )
				{
					if ( ( !$transaction->gw_id or $transaction->gw_id == $t['id'] ) and $transaction->amount->currency == $t['amount_with_breakdown']['gross_amount']['currency_code'] and $transaction->amount->amountAsString() == $t['amount_with_breakdown']['gross_amount']['value'] )
					{
						if ( $t['status'] == 'COMPLETED' )
						{
							$transaction->gw_id = $t['id'];
							$transaction->save();
							return TRUE;
						}
						elseif ( $t['status'] == 'REFUNDED' )
						{
							$response = 'RFND';
						}
						elseif ( $t['status'] == 'DECLINED' )
						{
							$response = 'FAIL';
						}
					}
				}
			}
			/* Legacy Billing Agreements */
			else
			{
				$transactions = $this->api( "payments/billing-agreements/{$transaction->billing_agreement->gw_id}/transactions?start_date=" . date( 'Y-m-d', $transaction->date->getTimestamp() - 86400 ) . '&end_date=' . date( 'Y-m-d' ), NULL, 'get' );
				foreach ( $transactions['agreement_transaction_list'] as $t )
				{
					if ( ( !$transaction->gw_id or $transaction->gw_id == $t['transaction_id'] ) and $transaction->amount->currency == $t['amount']['currency'] and $transaction->amount->amountAsString() == $t['amount']['value'] )
					{
						if ( $t['status'] == 'Completed' )
						{
							$transaction->gw_id = $t['transaction_id'];
							$transaction->save();
							return TRUE;
						}
						elseif ( $t['status'] == 'Refunded' )
						{
							$response = 'RFND';
						}
						elseif ( $t['status'] == 'Failed' )
						{
							$response = 'FAIL';
						}
					}
				}
			}
			throw new \RuntimeException( $response );
		}

		/* Try to find the authorization ID */
		if ( mb_substr( $transaction->gw_id, 0, 4 ) === 'PAY-' )
		{
			$authId = NULL;
			$payment = $this->api( "payments/payment/{$transaction->gw_id}", NULL, 'get' );
			foreach ( $payment['transactions'][0]['related_resources'] as $rr )
			{
				if ( isset( $rr['authorization'] ) )
				{
					$authId = $rr['authorization']['id'];
				}
			}
			
			if ( !$authId )
			{
				throw new \RuntimeException;
			}
		}
		else
		{
			$authId = $transaction->gw_id;
			if( $transaction->billing_agreement )
			{
				try
				{
					$sale = $this->api( "payments/authorizations/{$authId}", NULL, 'get', TRUE, NULL, NULL, 2 );
					return TRUE; // "Sales" came from Billing Agreements and have already been captured
				}
				catch ( \IPS\nexus\Gateway\PayPal\Exception $e ){}
			}
		}
		
		/* Capture it */
		try
		{
			$response = $this->api( "payments/authorizations/{$authId}/capture", NULL, 'POST', TRUE, NULL, NULL, 2 );
			$transaction->gw_id = $response['id']; // We now set the gateway ID to the capture ID
			$transaction->save();
		}
		catch ( \IPS\nexus\Gateway\PayPal\Exception $e )
		{
			if ( $e->getName() == 'ORDER_ALREADY_AUTHORIZED' )
			{
				return TRUE;
			}
			throw $e;
		}

		return TRUE;
	}
		
	/**
	 * Refund
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction to be refunded
	 * @param	float|NULL				$amount			Amount to refund (NULL for full amount - always in same currency as transaction)
	 * @return	mixed									Gateway reference ID for refund, if applicable
	 * @throws	\Exception
 	 */
	public function refund( \IPS\nexus\Transaction $transaction, $amount = NULL )
	{
		/* The capture ID is *normally* the gateway transaction ID */
		$captureId = $transaction->gw_id;

		/* Refund Amount */
		$amount = $amount ? new \IPS\nexus\Money( $amount, $transaction->currency ) : $transaction->amount;

		/* If it's a billing agreement and the gateway ID isn't prefixed with an I, it's likely to be a subscription */
		if( $transaction->billing_agreement AND $transaction->gw_id AND mb_substr( $transaction->billing_agreement->gw_id, 0, 2 ) !== 'I-' )
		{
			$response = $this->api( "payments/sale/{$transaction->gw_id}/refund", array( 'amount' => array(
				'currency'	=> $amount->currency,
				'total'		=> $amount->amountAsString()
			) ) );
			return $response['id'];
		}
		/* But if it starts with I- (or is a blank but known to be a billing agreement payment) - that's a billing agreement */
		elseif ( ( $transaction->billing_agreement and !$transaction->gw_id ) or ( mb_substr( $transaction->gw_id, 0, 2 ) === 'I-' ) )
		{
			$transactions = $this->api( "payments/billing-agreements/{$transaction->billing_agreement->gw_id}/transactions?start_date=" . $transaction->date->sub( new \DateInterval('P1D') )->format('Y-m-d') . '&end_date=' . $transaction->date->format('Y-m-d'), NULL, 'get' );
			foreach ( $transactions['agreement_transaction_list'] as $t )
			{
				if ( $t['status'] == 'Completed' )
				{
					$transaction->gw_id = $t['transaction_id'];
					$transaction->save();
					$captureId = $transaction->gw_id;
					break;
				}
			}
		}
		/* And if it starts with PAY-, that's a payment */
		elseif ( mb_substr( $transaction->gw_id, 0, 4 ) === 'PAY-' )
		{
			$payment = $this->api( "payments/payment/{$transaction->gw_id}", NULL, 'get' );		
			$captureId = NULL;
			foreach ( $payment['transactions'][0]['related_resources'] as $rr )
			{
				if ( isset( $rr['capture'] ) )
				{
					$captureId = $rr['capture']['id'];
					break;
				}
			}
		}
		
		/* Process Refund */
		$response = $this->api( "payments/captures/{$captureId}/refund", array(
			'amount' => array(
				'currency_code'	=> $amount->currency,
				'value'		=> $amount->amountAsString()
			),
			'invoice_id' => $transaction->invoice->id
		), 'POST', TRUE, NULL, NULL, 2 );
		return $response['id'];
	}
	
	/**
	 * Extra data to show on the ACP transaction page
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	string
 	 */
	public function extraData( \IPS\nexus\Transaction $transaction )
	{
		return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->paypalStatus( $transaction );
	}
	
	/* !ACP Configuration */
	
	/**
	 * Settings
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$settings = json_decode( $this->settings, TRUE );

		if ( isset( $settings['type'] ) and $settings['type'] === 'card' )
		{
			$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'gateway_deprecated', FALSE, array( 'sprintf' => 'PayPal Credit Card' ) ), 'ipsMessage ipsMessage_warning' );
			$form->add( new \IPS\Helpers\Form\Radio( 'paypal_type', $settings['type'], TRUE, array( 'options' => array( 'paypal' => 'paypal_type_paypal', 'card' => 'paypal_type_card' ), 'toggles' => array( 'paypal' => array( 'paypal_billing_agreements' ), 'card' => array( 'paypal_vault' ) ) ) ) );
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'paypal_billing_agreements', ( $this->id AND isset( $settings['billing_agreements'] ) ) ? (string) $settings['billing_agreements'] : '', FALSE, array(
			'options' => array(
				'required'	=> 'paypal_billing_agreements_req',
				'optional'	=> 'paypal_billing_agreements_opt',
				''			=> 'paypal_billing_agreements_dis',
			),
			'toggles' => array(
				'required'	=> array( 'paypal_billing_agreement_allowed_fails' ),
				'optional'	=> array( 'paypal_billing_agreement_allowed_fails' ),
			)
		), function( $val ) {
			if ( $val )
			{
				if ( \IPS\Http\Url::internal('')->data['scheme'] !== 'https' )
				{
					throw new \DomainException('paypal_billing_agreements_https');
				}
			}
		}, NULL, NULL, 'paypal_billing_agreements' ) );
		
		$form->add( new \IPS\Helpers\Form\Number( 'paypal_billing_agreement_allowed_fails', ( $this->id AND isset( $settings['billing_agreement_allowed_fails'] ) ) ? $settings['billing_agreement_allowed_fails'] : 0, FALSE, array( 'unlimited' => 0, 'min' => 1 ), NULL, \IPS\Member::loggedIn()->language()->addToStack('paypal_billing_agreement_allowed_fails_prefix'), \IPS\Member::loggedIn()->language()->addToStack('paypal_billing_agreement_allowed_fails_suffix'), 'paypal_billing_agreement_allowed_fails' ) );

		if ( isset( $settings['type'] ) and $settings['type'] === 'card' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'paypal_vault', ( $this->id and isset( $settings['vault'] ) ) ? $settings['vault'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'paypal_vault' ) );
		}

		$form->add( new \IPS\Helpers\Form\Text( 'paypal_client_id', $settings['client_id'], TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'paypal_secret', $settings['secret'], TRUE ) );
	}
	
	/**
	 * Test Settings
	 *
	 * @param	array	$settings	Settings
	 * @return	array
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings( $settings )
	{
		try
		{
			$token = $this->getNewToken( $settings );
			$settings['token'] = $token['access_token'];
			$settings['token_expire'] = ( time() + $token['expires_in'] );
			
			if ( isset( $settings['billing_agreements'] ) and $settings['billing_agreements'] )
			{			
				$correctWebhookUrl = \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/paypal-webhook.php';
				$webhookId = NULL;
				$webhooks = $this->api( 'notifications/webhooks', NULL, 'get', TRUE, $settings );
				foreach ( $webhooks['webhooks'] as $webhook )
				{
					if ( $webhook['url'] === $correctWebhookUrl )
					{
						foreach ( $webhook['event_types'] as $eventType )
						{
							if ( $eventType['name'] === '*' )
							{
								$webhookId = $webhook['id'];
								break 2;
							}
						}
					}
				}
				if ( !$webhookId )
				{
					$response = $this->api( 'notifications/webhooks', array(
						'url'			=> $correctWebhookUrl,
						'event_types'	=> array(
							array(
								'name'	=> '*'
							)
						)
					), 'post', TRUE, $settings );
					$webhookId = $response['id'];
				}
				$settings['webhook_id'] = $webhookId;
			}
			
			\IPS\core\AdminNotification::remove( 'nexus', 'ConfigurationError', "pm{$this->id}" );
		}
		catch ( \Exception $e )
		{
			throw new \InvalidArgumentException( $e->getMessage() ?: \IPS\Member::loggedIn()->language()->addToStack('paypal_connection_error'), $e->getCode() );
		}
				
		return $settings;
	}
	
	/* !Utility Methods */
	
	/**
	 * Send API Request
	 *
	 * @param	string		$uri			The API to request (e.g. "payments/payment")
	 * @param	array		$data		The data to send
	 * @param	string		$method		Method (get/post)
	 * @param	array|NULL	$settings	Settings (NULL for saved setting)
	 * @param   string|Null $requestId
	 * @param	int|Null 	$version	API Version
	 * @return	array|null
	 * @throws	\IPS\Http|Exception
	 * @throws	\IPS\nexus\Gateway\PayPal\Exception
	 */
	public function api( $uri, $data=NULL, $method='post', $expectResponse=TRUE, $settings=NULL, $requestId=NULL, $version=NULL )
	{
		if ( !$settings )
		{
			$settings = json_decode( $this->settings, TRUE );
			if ( !isset( $settings['token'] ) or $settings['token_expire'] < time() )
			{
				$token = $this->getNewToken();
				$settings['token'] = $token['access_token'];
				$settings['token_expire'] = ( time() + $token['expires_in'] );
				$this->settings = json_encode( $settings );
				$this->save();
			}
		}

		/* Some API calls (e.g. for billing agreements) still use v1 for the REST APIs */
		$version = $version ?: 1;
		
		$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api-m.paypal.com' ) . '/v' . $version . '/' . $uri )
			->request( \IPS\LONG_REQUEST_TIMEOUT )
			->forceTls()
			->setHeaders( array(
				'Content-Type'					=> 'application/json',
				'Authorization'					=> "Bearer {$settings['token']}",
				'PayPal-Partner-Attribution-Id'	=> 'InvisionPower_SP',
				'PayPal-Request-Id'				=> $requestId
			) )
			->$method( $data === NULL ? NULL : json_encode( $data ) );
					
		if ( mb_substr( $response->httpResponseCode, 0, 1 ) !== '2' )
		{
			throw new \IPS\nexus\Gateway\PayPal\Exception( $response, mb_substr( $uri, -7 ) === '/refund' );
		}
		
		if ( \in_array( $method, array( 'delete', 'patch' ) ) or $response->httpResponseCode == 204 )
		{
			return NULL;
		}
		else
		{
			return $response->decodeJson();
		}
	}
	
	/**
	 * Get Token
	 *
	 * @param	array|NULL	$settings	Settings (NULL for saved setting)
	 * @return	array
	 * @throws	\IPS\Http\Request\Exception
	 * @throws	\UnexpectedValueException
	 */
	protected function getNewToken( $settings = NULL )
	{
		$settings = $settings ?: json_decode( $this->settings, TRUE );
				
		$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api-m.paypal.com' ) . '/v1/oauth2/token' )
			->request()
			->forceTls()
			->setHeaders( array(
				'Accept'			=> 'application/json',
				'Accept-Language'	=> 'en_US',
			) )
			->login( $settings['client_id'], $settings['secret'] )
			->post( array( 'grant_type' => 'client_credentials' ) )
			->decodeJson();
			
		if ( !isset( $response['access_token'] ) )
		{
			throw new \UnexpectedValueException( isset( $response['error_description'] ) ? $response['error_description'] : $response );
		}

		return $response;
	}

	/**
	 * Handle the PayPal exception and mark the transaction as failed
	 *
	 * @param \IPS\nexus\Transaction $transaction
	 * @param \Exception $e
	 * @return void
	 */
	public function processException( \IPS\nexus\Transaction $transaction, \Exception $e )
	{
		/* Make sure it's a PayPal exception only. The interface might send us a different exception type */
		if( ! ( $e instanceof \IPS\nexus\Gateway\PayPal\Exception ) )
		{
			return;
		}

		/* Mark the transaction as failed */
		$transaction->status = \IPS\nexus\Transaction::STATUS_REFUSED;

		$details = json_decode( $e->extraLogData(), true );

		/* Log the error message to the transaction history */
		$extra = $transaction->extra;
		$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'on' => time(), 'noteRaw' => ( $details['details'][0]['description'] ?? $e->getMessage() ) );

		if( isset( $details['processor_response'] ) )
		{
			$extra['processor_response'] = $details['processor_response'];

			// log this failure to the customer history
			$responseCode = $details['processor_response']['response_code'];
			if( \is_numeric( $responseCode ) )
			{
				$responseCode = (int) $responseCode;
			}
			if( $transaction->member->language()->checkKeyExists( 'processor_response_code__' . $responseCode ) )
			{
				$failureReason = $transaction->member->language()->get( 'processor_response_code__' . $responseCode );
				$transaction->member->logHistory( 'nexus', 'custom', array(
					'message' => \sprintf( $transaction->member->language()->get( 'history_payment_rejected' ), $failureReason )
				) );
			}
		}

		$transaction->extra = $extra;
		$transaction->save();
	}
	
	/**
	 * Get address for PayPal
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @param 	\IPS\nexus\Customer		$customer
	 * @param 	string					$paymentType
	 * @return	array
	 */
	protected function _getAddress( \IPS\GeoLocation $address, \IPS\nexus\Customer $customer, $paymentType='paypal' )
	{
		/* PayPal requires short codes for states */
		$state = $address->region;
		if ( isset( \IPS\nexus\Customer\Address::$stateCodes[ $address->country ] ) )
		{
			if ( !array_key_exists( $state, \IPS\nexus\Customer\Address::$stateCodes[ $address->country ] ) )
			{
				$_state = array_search( $address->region, \IPS\nexus\Customer\Address::$stateCodes[ $address->country ] );
				if ( $_state !== FALSE )
				{
					$state = $_state;
				}
			}
		}

		if( $paymentType == 'card' )
		{
			/* Construct */
			$address = array(
				'line1'				=> $address->addressLines[0],
				'line2'				=> isset( $address->addressLines[1] ) ? $address->addressLines[1] : '',
				'city'				=> $address->city,
				'country_code'		=> $address->country,
				'postal_code'		=> $address->postalCode,
				'state'				=> $state,
			);

			/* Add phone number */
			if ( $customer->cm_phone )
			{
				$address['phone'] = preg_replace( '/[^\+0-9\s]/', '', $customer->cm_phone );
			}
		}
		else
		{
			/* Construct */
			$address = array(
				'address_line_1'	=> $address->addressLines[0],
				'address_line_2'	=> isset( $address->addressLines[1] ) ? $address->addressLines[1] : '',
				'admin_area_2'		=> $address->city,
				'country_code'		=> $address->country,
				'postal_code'		=> $address->postalCode,
				'admin_area_1'		=> $state,
			);

			if( empty( $address['address_line_2'] ) )
			{
				unset( $address['address_line_2'] );
			}
		}
		
		/* Return */
		return $address;
	}
	
	/**
	 * Get first name for PayPal
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	string
	 */
	protected function _getFirstName( \IPS\nexus\Transaction $transaction )
	{
		return $transaction->invoice->member->member_id ? $transaction->invoice->member->cm_first_name : $transaction->invoice->guest_data['member']['cm_first_name'];
	}
	
	/**
	 * Get last name for PayPal
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	string
	 */
	protected function _getLastName( \IPS\nexus\Transaction $transaction )
	{
		return $transaction->invoice->member->member_id ? $transaction->invoice->member->cm_last_name : $transaction->invoice->guest_data['member']['cm_last_name'];
	}

	/**
	 * Get transaction data for PayPal
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	array
	 */
	protected function _getPurchaseUnits( \IPS\nexus\Transaction $transaction )
	{
		/* Init */
		$payPalTransactionData = array(
			'amount'	=> array(
				'currency_code'	=> $transaction->amount->currency,
				'value'		=> $transaction->amount->amountAsString(),
			),
			'custom_id'=> \IPS\Settings::i()->site_secret_key . '-' . $transaction->id,
			'invoice_id' => $transaction->invoice->id,
			'items' => array()
		);

		/* If we're paying the whole invoice, we can add item data... */
		if ( $transaction->amount->amount->compare( $transaction->invoice->total->amount ) === 0 )
		{
			$summary = $transaction->invoice->summary();

			/* Shipping / Tax */
			$payPalTransactionData['amount']['breakdown'] = array(
				'shipping'		=> array( 'currency_code' => $summary['shippingTotal']->currency, 'value' => $summary['shippingTotal']->amountAsString() ),
				'item_total'	=> array( 'currency_code' => $summary['subtotal']->currency, 'value' => $summary['subtotal']->amountAsString() ),
				'tax_total'		=> array( 'currency_code' => $summary['taxTotal']->currency, 'value' => $summary['taxTotal']->amountAsString() ),
				'discount'		=> array( 'currency_code' => $summary['discount']->currency, 'value' => $summary['discount']->amountAsString() )
			);

			/* Items */
			$itemTotal = new \IPS\Math\Number( '0' );
			foreach ( $summary['items'] as $item )
			{
				if( $item instanceof \IPS\nexus\extensions\nexus\Item\CouponDiscount )
				{
					continue;
				}

				$itemCategory = 'DIGITAL_GOODS';
				if( $item->physical )
				{
					$itemCategory = 'PHYSICAL_GOODS';
				}
				elseif( $item instanceof \IPS\nexus\extensions\nexus\Item\Donation )
				{
					$itemCategory = 'DONATION';
				}

				$itemData = array(
					'name'		=> mb_strlen( $item->name ) > 127 ? mb_substr( $item->name, 0, 124 ) . '...' : $item->name,
					'quantity'	=> $item->quantity,
					'unit_amount' => array(
						'currency_code' => $item->price->currency,
						'value' => $item->price->amountAsString()
					),
					'category' => $itemCategory
				);
				$itemTotal = $itemTotal->add( $item->price->amount->multiply( new \IPS\Math\Number("{$item->quantity}") ) );

				$payPalTransactionData['items'][] = $itemData;
			}

			/* PayPal requires that the item total be equal to the pre-discounted amounts. The summary uses the invoice total, which is not accepted. */
			$itemTotal = new \IPS\nexus\Money( $itemTotal, $transaction->amount->currency );
			$payPalTransactionData['amount']['breakdown']['item_total']['value'] = $itemTotal->amountAsString();

			/* Shipping Address */
			if ( $transaction->invoice->shipaddress )
			{
				$payPalTransactionData['shipping'] = array(
					'address' => $this->_getAddress( $transaction->invoice->shipaddress, $transaction->member ),
					'name' => array(
						'full_name' => $this->_getFirstName( $transaction ) . ' ' . $this->_getLastName( $transaction )
					),
					'type' => 'SHIPPING'
				);
			}
		}
		/* Otherwise just use a generic description */
		else
		{
			$payPalTransactionData['description'] = sprintf( $transaction->member->language()->get('partial_payment_desc'), $transaction->invoice->id );
		}

		return array( $payPalTransactionData );
	}
	
	/**
	 * Get transaction data for PayPal
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	array
	 */
	protected function _getTransactions( \IPS\nexus\Transaction $transaction )
	{
		/* Init */
		$payPalTransactionData = array(
			'amount'	=> array(
				'currency'	=> $transaction->amount->currency,
				'total'		=> $transaction->amount->amountAsString(),
			),
			'invoice_number'=> \IPS\Settings::i()->site_secret_key . '-' . $transaction->id,
		);
		
		/* If we're paying the whole invoice, we can add item data... */
		if ( $transaction->amount->amount->compare( $transaction->invoice->total->amount ) === 0 )
		{
			$summary = $transaction->invoice->summary();
			
			/* Shipping / Tax */
			$payPalTransactionData['amount']['details'] = array(
				'shipping'	=> $summary['shippingTotal']->amountAsString(),
				'subtotal'	=> $summary['subtotal']->amountAsString(),
				'tax'		=> $summary['taxTotal']->amountAsString(),
			);

			/* Items */
			$payPalTransactionData['item_list'] = array( 'items' => array() );
			foreach ( $summary['items'] as $item )
			{
				$payPalTransactionData['item_list']['items'][] = array(
					'quantity'	=> $item->quantity,
					'name'		=> mb_strlen( $item->name ) > 127 ? mb_substr( $item->name, 0, 124 ) . '...' : $item->name,
					'price'		=> $item->price->amountAsString(),
					'currency'	=> $transaction->amount->currency,
				);
			}

			/* Shipping Address */
			if ( $transaction->invoice->shipaddress )
			{
				$payPalTransactionData['item_list']['shipping_address'] = array_merge(
					array( 'recipient_name'	=> $this->_getFirstName( $transaction ) . ' ' . $this->_getLastName( $transaction ) ),
					$this->_getAddress( $transaction->invoice->shipaddress, $transaction->invoice->member, 'card' )
				);
			}
		}
		/* Otherwise just use a generic description */
		else
		{
			$payPalTransactionData['description'] = sprintf( $transaction->member->language()->get('partial_payment_desc'), $transaction->invoice->id );
		}
		
		return $payPalTransactionData;
	}
	
	/**
	 * Can we handle the renewal terms in a billing agreement?
	 *
	 * @param	array				$recurrings				Details about recurring costs
	 * @return	bool
	 */
	protected static function _canProcessRecurringsAsBillingAgreement( array $recurrings )
	{
		if( \count( $recurrings ) == 1 )
		{
			$recurrance = array_pop( $recurrings );

			/* If we only have one item, we're fine */
			if( \count( $recurrance['items'] ) == 1 )
			{
				return true;
			}

			/* Make sure all the terms match */
			$initialTermFrequency = null;
			foreach( $recurrance['items'] as $item )
			{
				if( isset( $item->initialInterval ) AND $item->initialInterval instanceof \DateInterval )
				{
					$thisTermFrequency = static::_getFrequencyFromInterval( $item->initialInterval );
					if( $initialTermFrequency !== null AND ( $thisTermFrequency['interval_unit'] != $initialTermFrequency['interval_unit'] OR $thisTermFrequency['interval_count'] != $initialTermFrequency['interval_count'] ) )
					{
						return false;
					}
					$initialTermFrequency = $thisTermFrequency;
				}
				elseif( $initialTermFrequency !== null )
				{
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Get the billing cycle frequency based on the DateInterval
	 *
	 * @param \DateInterval $term
	 * @return array|null
	 */
	protected static function _getFrequencyFromInterval( \DateInterval $term )
	{
		if( $term->y )
		{
			return array(
				'interval_unit' => 'YEAR',
				'interval_count' => $term->y
			);
		}

		if( $term->m )
		{
			return array(
				'interval_unit' => 'MONTH',
				'interval_count' => $term->m
			);
		}

		if( $term->d )
		{
			if( $term->d % 7 == 0 )
			{
				return array(
					'interval_unit' => 'WEEK',
					'interval_count' => $term->d / 7
				);
			}

			return array(
				'interval_unit' => 'DAY',
				'interval_count' => $term->d
			);
		}

		return null;
	}
}

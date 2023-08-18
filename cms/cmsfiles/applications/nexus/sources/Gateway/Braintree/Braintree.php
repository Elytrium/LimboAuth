<?php
/**
 * @brief		Braintree Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		5 Dec 2018
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Braintree Gateway
 * @todo	Prevent people from vaulting duplicate cards/PayPal/Venmo accounts (both at checkout and in account)
 */
class _Braintree extends \IPS\nexus\Gateway
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
		
		/* Check we have a merchant account id for the desired currency */
		if ( !array_key_exists( $amount->currency, $settings['merchant_accounts'] ) )
		{
			return FALSE;
		}
		
		/* Method specific checks */
		switch ( $settings['type'] )
		{			
			/* Credit cards */
			case 'card':
				/* Check some methods are accepted */
				if ( !$settings['merchant_accounts'][ $amount->currency ]['cardTypes'] )
				{
					return FALSE;
				}
				break;
				
			/* Venmo */
			case 'venmo':
				/* Requires USD */
				if ( $amount->currency !== 'USD' )
				{
					return FALSE;
				}
				
				/* Requires browser support (unless they already have vaulted accounts) */
				if ( isset( \IPS\Request::i()->cookie['VenmoSupported'] ) and !\IPS\Request::i()->cookie['VenmoSupported'] and !\IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_cards', array( 'card_member=? AND card_method=?', $customer ? $customer->member_id : \IPS\nexus\Customer::loggedIn()->member_id, $this->id ) )->first() )
				{
					return FALSE;
				}
				break;
				
			/* Apple Pay */
			case 'applepay':
				/* Requires browser support  */
				if ( isset( \IPS\Request::i()->cookie['ApplePaySupported'] ) and !\IPS\Request::i()->cookie['ApplePaySupported'] )
				{
					return FALSE;
				}
				break;
		}
		
		/* If we're still here, we're good */
		return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
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
		
		switch ( $settings['type'] )
		{
			case 'card':
				return (bool) $settings['cards'];
			case 'paypal':
				return $adminCreatableOnly ? FALSE : ( (bool) $settings['paypal_vault'] );
			case 'venmo':
				return $adminCreatableOnly ? FALSE : ( (bool) $settings['venmo_vault'] );
		}
		
		return FALSE;
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
		
		switch ( $settings['type'] )
		{
			case 'paypal':
			case 'venmo':
				return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_cards', array( 'card_member=? AND card_method=?', $customer->member_id, $this->id ) )->first();
			
			case 'card':
				return TRUE;
		}
		
		return FALSE;
	}
	
	/* !Payment Gateway */
	
	/**
	 * Should the submit button show when this payment method is shown?
	 *
	 * @return	bool
	 */
	public function showSubmitButton()
	{
		$settings = json_decode( $this->settings, TRUE );
		return !\in_array( $settings['type'], array( 'paypal', 'venmo', 'applepay', 'googlepay' ) );
	}
	
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
		$clientToken = $this->gateway()->clientToken()->generate( array( 'merchantAccountId' => $settings['merchant_accounts'][ $amount->currency ]['id'], 'version' => '3' ) );
		
		$member = $member ?: \IPS\nexus\Customer::loggedIn();
				
		if ( $settings['type'] === 'paypal' or $settings['type'] === 'venmo' or $settings['type'] === 'applepay' or $settings['type'] === 'googlepay' )
		{
			$vaultAccounts = NULL;
			if ( ( ( $settings['type'] === 'paypal' and $settings['paypal_vault'] ) or ( $settings['type'] === 'venmo' and $settings['venmo_vault'] ) ) and $type != 'card' )
			{
				$vaultAccounts = array();
				if ( $member->member_id )
				{
					foreach( \IPS\Db::i()->select( '*', 'nexus_customer_cards', array( 'card_member=? AND card_method=?', $member->member_id, $this->id ) ) as $vaultAccount )
					{				
						try
						{
							$vaultAccount = \IPS\nexus\Customer\CreditCard::constructFromData( $vaultAccount );
							$vaultAccount->card; // This is just to make the API call now and cache the response so we can catch the exception if one is thrown
							$vaultAccounts[ $vaultAccount->id ] = $vaultAccount;
						}
						catch ( \Exception $e ) {}
					}
				}
			}
			
			if ( $type === 'admin' )
			{
				$options = array();
				foreach ( $vaultAccounts as $id => $account )
				{
					$options[ $account->data ] = $account->card->number;
				}
				$element = new \IPS\Helpers\Form\Radio( $this->id . '_card', NULL, FALSE, array( 'options' => $options ), NULL, NULL, NULL, $this->id . '_card' );
				$element->label = \IPS\Member::loggedIn()->language()->addToStack('braintree_account');
				return array( 'braintree' => $element );
			}
			else
			{
				return array( 'braintree' => new \IPS\Helpers\Form\Custom( $this->id . '_card', NULL, FALSE, array(
					'rowHtml'	=> function( $field ) use ( $clientToken, $vaultAccounts, $invoice, $amount, $settings, $type )
					{
						$shippingAddress = NULL;
						if ( $invoice->shipaddress )
						{
							$shippingAddress = json_encode( array(
								'line1'			=> isset( $invoice->shipaddress->addressLines[0] ) ? $invoice->shipaddress->addressLines[0] : NULL,
								'line2'			=> isset( $invoice->shipaddress->addressLines[1] ) ? $invoice->shipaddress->addressLines[1] : NULL,
								'city'			=> $invoice->shipaddress->city,
								'state'			=> $settings['type'] === 'paypal' ? $this->_payPalState( $invoice->shipaddress ) : $invoice->shipaddress->region,
								'postalCode'	=> $invoice->shipaddress->postalCode,
								'countryCode'	=> $invoice->shipaddress->country,
								'phone'			=> $invoice->member->cm_phone,
								'recipientName'	=> $invoice->member->cm_name,
							) );
						}
						
						return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->braintree( $clientToken, $settings['type'], $field, $vaultAccounts, $invoice, $amount, $this, $shippingAddress, isset( $settings['venmo_profile'] ) ? $settings['venmo_profile'] : NULL, $type, ( $settings['advanced_fraud'] and $type != 'admin' ), ( $settings['type'] === 'paypal' and $settings['paypal_credit'] and \in_array( $amount->currency, array( 'USD', 'GBP' ) ) and $type === 'checkout' ), $settings['googlepay_merchant'] );
					}
				), NULL, NULL, NULL, $this->id . '_card' ) );
			}
		}
		elseif ( $settings['type'] === 'card' )
		{
			if ( isset( \IPS\Request::i()->convertTokenToNonce ) )
			{
				try
				{
					$card = \IPS\nexus\Customer\CreditCard::load( \IPS\Request::i()->convertTokenToNonce );					
					if ( $card->member->member_id and $card->member->member_id == \IPS\Member::loggedIn()->member_id )
					{
						\IPS\Output::i()->json( array( 'success' => true, 'nonce' => $this->gateway()->paymentMethodNonce()->create( $card->data )->paymentMethodNonce->nonce ) );
					}
					else
					{
						\IPS\Output::i()->json( array( 'success' => false ) );
					}
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->json( array( 'success' => false, 'message' => $e->getMessage() ) );
				}
			}
						
			return array( 'card' => new \IPS\nexus\Form\CreditCard( $this->id . '_card', NULL, FALSE, array(
				'types' 		=> $settings['merchant_accounts'][ $amount->currency ]['cardTypes'],
				'attr'			=> array(
					'data-controller'		=> 'nexus.global.gateways.braintree',
					'data-clientToken'		=> $clientToken,
					'data-method'			=> 'card',
					'data-id'				=> $this->id,
					'data-amount'			=> $amount->amount,
					'data-3dsecure'			=> ( $settings['3d_secure'] and $type === 'checkout' ) ? 'true' : 'false',
					'data-email'			=> $member->email,
					'data-billingAddress'	=> json_encode( array(
						'firstName'				=> $invoice->member->cm_first_name,
						'lastName'				=> $invoice->member->cm_last_name,
						'streetAddress'			=> $invoice->billaddress ? implode( ', ', $invoice->billaddress->addressLines ) : NULL,
						'locality'				=> $invoice->billaddress ? $invoice->billaddress->city : NULL,
						'region'				=> $invoice->billaddress ? ( $settings['type'] === 'paypal' ? $this->_payPalState( $invoice->billaddress ) : $invoice->billaddress->region ) : NULL,
						'postalCode'			=> $invoice->billaddress ? $invoice->billaddress->postalCode : NULL,
						'countryCodeAlpha2'		=> $invoice->billaddress ? $invoice->billaddress->country : NULL,
						'phoneNumber'			=> preg_replace( '/[^0-9]/', '', $invoice->member->cm_phone ),
					) ),
					'data-advanced-fraud'	=> ( $settings['advanced_fraud'] and $type != 'admin' ) ? 'true' : 'false',
				),
				'jsRequired'	=> TRUE,
				'names'			=> FALSE,
				'dummy'			=> TRUE,
				'save'			=> ( $settings['cards'] ) ? $this : NULL,
				'member'		=> $member,
				'loading'		=> TRUE
			) ) );
		}
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
		$settings = json_decode( $this->settings, TRUE );
						
		/* We need a transaction ID */
		$transaction->save();
		
		/* Do we have a customer profile in Braintree already? */
		$customerId = \IPS\Settings::i()->site_secret_key . '-' . $transaction->member->member_id;

		if( \strlen( $customerId ) > 36 )
		{
			$customerId = substr( $customerId, -36 );
		}

		try
		{
			$braintreeCustomer = $this->gateway()->customer()->find( $customerId );
			
			$customerUpdate = array();
			foreach ( array(
				'email'		=> $transaction->member->email,
				'firstName'	=> $transaction->member->cm_first_name,
				'id'		=> $customerId,
				'lastName'	=> $transaction->member->cm_last_name,
				'phone'		=> $transaction->member->cm_phone
			) as $k => $v ) {
				if ( $braintreeCustomer->$k != $v )
				{
					$customerUpdate[ $k ] = $v;
				}
			}
			
			if ( $customerUpdate )
			{
				$this->gateway()->customer()->update( $customerId, $customerUpdate );
			}
			
		}
		catch ( \Braintree\Exception\NotFound $e )
		{
			$braintreeCustomer = NULL;
		}
				
		/* Init basic data */
		$profiles = $transaction->member->cm_profiles;
		$data = array(
			'merchantAccountId'	=> $settings['merchant_accounts'][ $transaction->amount->currency ]['id'],
			'amount'			=> $transaction->amount->amount,
			'options'			=> array(
				'paypal'							=> array(),
				'submitForSettlement'				=> FALSE,
			),
			'orderId'			=> \substr( \IPS\Settings::i()->site_secret_key, 0, 20 ) . '-' . $transaction->id,
		);
		
		/* Venmo profile */
		if ( $settings['type'] === 'venmo' and isset( $settings['venmo_profile'] ) and $settings['venmo_profile'] )
		{
			$data['options']['venmo']['profileId'] = $settings['venmo_profile'];
		}
		
		/* Add addresses */
		if ( $transaction->invoice->billaddress )
		{
			$billingAddress = array(
				'countryCodeAlpha2'		=> $transaction->invoice->billaddress->country,
				'firstName'				=> $transaction->invoice->member->cm_first_name,
				'lastName'				=> $transaction->invoice->member->cm_last_name,
				'locality'				=> $transaction->invoice->billaddress->city,
				'postalCode'			=> $transaction->invoice->billaddress->postalCode,
				'region'				=> $settings['type'] === 'paypal' ? $this->_payPalState( $transaction->invoice->billaddress ) : $transaction->invoice->billaddress->region,
				'streetAddress'			=> implode( ', ', $transaction->invoice->billaddress->addressLines )
			);
			
			if ( $braintreeCustomer )
			{
				foreach ( $braintreeCustomer->addresses as $address )
				{
					foreach ( $billingAddress as $k => $v )
					{
						if ( $billingAddress[ $k ] != $address->$k )
						{
							continue 2;
						}
					}
					
					$data['billingAddressId'] = $address->id;
					break;
				}
			}
			
			if ( !isset( $data['billingAddressId'] ) )
			{
				$data['billing'] = $billingAddress;
			}
		}		
		if ( $transaction->invoice->shipaddress )
		{
			$shippingAddress = array(
				'countryCodeAlpha2'		=> $transaction->invoice->shipaddress->country,
				'firstName'				=> $transaction->invoice->member->cm_first_name,
				'lastName'				=> $transaction->invoice->member->cm_last_name,
				'locality'				=> $transaction->invoice->shipaddress->city,
				'postalCode'			=> $transaction->invoice->shipaddress->postalCode,
				'region'				=> $settings['type'] === 'paypal' ? $this->_payPalState( $transaction->invoice->billaddress ) : $transaction->invoice->billaddress->region,
				'streetAddress'			=> implode( ', ', $transaction->invoice->shipaddress->addressLines )
			);
			
			if ( $braintreeCustomer )
			{
				foreach ( $braintreeCustomer->addresses as $address )
				{
					foreach ( $shippingAddress as $k => $v )
					{
						if ( $shippingAddress[ $k ] != $address->$k )
						{
							continue 2;
						}
					}
					
					$data['shippingAddressId'] = $address->id;
					break;
				}
			}
			
			if ( !isset( $data['shippingAddressId'] ) )
			{
				$data['shipping'] = $shippingAddress;
			}
		}
		
		/* Add device data and transaction source */
		if ( $source === 'checkout' )
		{
			$data['transactionSource'] = $recurrings ? 'recurring_first' : 'moto';
			if ( \is_array( $_POST[ $this->id . '_card' ] ) and isset( $_POST[ $this->id . '_card' ]['device'] ) )
			{
				$data['deviceData'] = $_POST[ $this->id . '_card' ]['device'];
			}
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) and mb_strlen( $_SERVER['HTTP_USER_AGENT'] ) <= 255 )
			{
				$data['riskData']['customerBrowser'] = $_SERVER['HTTP_USER_AGENT'];
			}
			if ( $transaction->ip )
			{
				$data['riskData']['customerIp'] = $transaction->ip;
			}
		}
		elseif ( $source === 'renewal' )
		{
			$data['transactionSource'] = 'recurring';
		}
		elseif ( $source === 'manual' )
		{
			$data['transactionSource'] = 'unscheduled';
		}
		
		/* If we're paying the whole invoice, we can add item data... */
		if ( $transaction->amount->amount->compare( $transaction->invoice->total->amount ) === 0 )
		{
			$summary = $transaction->invoice->summary();
			foreach ( $summary['items'] as $item )
			{
				$unitTaxAmount = $item->tax ? $item->price->amount->multiply( new \IPS\Math\Number( number_format( "{$item->tax->rate( $transaction->invoice->billaddress )}", \IPS\nexus\Money::numberOfDecimalsForCurrency( $transaction->amount->currency ), '.', '' ) ) ) : new \IPS\Math\Number('0');
				$data['lineItems'][] = array(
					'description'	=> mb_strlen( $item->name ) > 127 ? mb_substr( $item->name, 0, 124 ) . '...' : $item->name,
					'kind'			=> $item->price->amount->isGreaterThanZero() ? 'debit' : 'credit',
					'name'			=> mb_strlen( $item->name ) > 35 ? mb_substr( $item->name, 0, 32 ) . '...' : $item->name,
					'productCode'	=> $item->id,
					'quantity'		=> $item->quantity,
					'taxAmount'		=> ( new \IPS\nexus\Money( $unitTaxAmount->multiply( new \IPS\Math\Number("{$item->quantity}") ), $transaction->amount->currency ) )->amountAsString(),
					'totalAmount'	=> $item->price->amount->multiply( new \IPS\Math\Number("{$item->quantity}") )->absolute(),
					'unitAmount'	=> $item->price->amount->absolute(),
					'unitTaxAmount'	=> ( new \IPS\nexus\Money( $unitTaxAmount, $transaction->amount->currency ) )->amountAsString()
				);
			}
			
			$data['shippingAmount'] = $summary['shippingTotal']->amountAsString();
			$data['taxAmount'] = $summary['taxTotal']->amountAsString();
			
			$data['options']['paypal']['description'] = mb_strlen( $transaction->invoice->title ) > 127 ? mb_substr( $transaction->invoice->title, 0, 124 ) . '...' : $transaction->invoice->title;
		}
		else
		{
			$data['options']['paypal']['description'] = sprintf( $transaction->member->language()->get('partial_payment_desc'), $transaction->invoice->id );
		}
				
		/* Figure out what we're dealing with */
		$token = NULL;
		$nonce = NULL;
		$save = FALSE;
		if ( \is_string( $values[ $this->id . '_card' ] ) ) // Admin manually charging PayPal/Venmo account
		{
			$token = $values[ $this->id . '_card' ];
		}
		elseif ( $values[ $this->id . '_card' ] instanceof \IPS\nexus\Gateway\Braintree\CreditCard ) // Stored card or PayPal/Venmo account initiated by automatic renewal
		{
			$token = $values[ $this->id . '_card' ]->data;
		}
		elseif ( $values[ $this->id . '_card' ] instanceof \IPS\nexus\CreditCard ) // New card
		{
			$nonce = $values[ $this->id . '_card' ]->token;
			$save = $values[ $this->id . '_card' ]->save;
		}
		elseif ( isset( $values[ $this->id . '_card' ]['token'] ) ) // Apple Pay and Google Pay
		{
			$nonce = $values[ $this->id . '_card' ]['token'];
		}
		elseif ( isset( $values[ $this->id . '_card' ]['stored'] ) ) // PayPal/Venmo (maybe stored or maybe new) initiated by normal checkout 
		{
			if ( mb_substr( $values[ $this->id . '_card' ]['stored'], 0, 6 ) === 'NONCE:' ) // New
			{
				$exploded = explode( ':', $values[ $this->id . '_card' ]['stored'] );
				$nonce = $exploded[2];
				$save = (bool) \intval( $exploded[1] );
			}
			else // Stored
			{
				$token = $values[ $this->id . '_card' ]['stored'];
			}
		}
		else
		{
			throw new \RuntimeException;
		}
				
		/* If we're supposed to be using 3DSecure, check it was verified */
		if ( $source == 'checkout' and $settings['type'] === 'card' and $settings['3d_secure'] )
		{
			try
			{
				$details = $this->gateway()->paymentMethodNonce()->find( $nonce );
				if ( !$details->threeDSecureInfo or ( !$details->threeDSecureInfo->liabilityShifted and $details->threeDSecureInfo->liabilityShiftPossible ) )
				{
					throw new \LogicException();
				}
			}
			catch ( \Braintree\Exception\NotFound $e )
			{
				throw new \LogicException();
			}
		}

		/* Put that into the right places */
		if ( $token )
		{
			$data['paymentMethodToken'] = $token;
		}
		else
		{
			$data['paymentMethodNonce'] = $nonce;

			if ( $save )
			{
				if ( $braintreeCustomer )
				{
					$data['customerId'] = $customerId;
				}
				else
				{
					$data['customer'] = array(
						'email'		=> $transaction->member->email,
						'firstName'	=> $transaction->member->cm_first_name,
						'id'		=> $customerId,
						'lastName'	=> $transaction->member->cm_last_name,
						'phone'		=> $transaction->member->cm_phone
					);
				}
				$data['options']['storeInVaultOnSuccess'] = TRUE;
			}
		}
		
		if ( isset( $data['customer'] ) or isset( $data['customerId'] ) )
		{
			$options['addBillingAddressToPaymentMethod'] = TRUE;
			$options['storeShippingAddressInVault'] = TRUE;
		}
		
		/* Do it! */
		$result = $this->gateway()->transaction()->sale( $data );
		if ( $result->success )
		{
			/* Save the customer ID */
			if ( isset( $result->transaction->customer ) and isset( $result->transaction->customer['id'] ) and !isset( $profiles[ $this->id ] ) )
			{
				$profiles[ $this->id ] = $result->transaction->customer['id'];
				$transaction->member->cm_profiles = $profiles;
				$transaction->member->save();
			}
			
			/* Save the payment method if we've chosen to do so */
			if ( $nonce and $save )
			{
				$storedCard = new \IPS\nexus\Gateway\Braintree\CreditCard;
				$storedCard->member = $transaction->member;
				$storedCard->method = $this;
				$storedCard->data = ( $settings['type'] === 'paypal' ) ? $result->transaction->paypal['token'] : $result->transaction->creditCard['token'];
				$storedCard->save();
			}
			
			/* Set MaxMind data */
			if ( $maxMind )
			{
				if ( $result->transaction->paymentInstrumentType === 'credit_card' )
				{
					if ( $result->transaction->creditCardDetails )
					{
						$maxMind->setCard( $result->transaction->creditCardDetails->maskedNumber );
					}
					else
					{
						$maxMind->setTransactionType('creditcard');
					}
				}
				elseif ( $result->transaction->paymentInstrumentType === 'paypal_account' )
				{
					$maxMind->setTransactionType('paypal');
				}
				else
				{
					$maxMind->setTransactionType('other');
				}
				
				if ( $result->transaction->avsStreetAddressResponseCode )
				{
					$maxMind->setAVS( $result->transaction->avsStreetAddressResponseCode );
				}
				elseif ( $result->transaction->avsPostalCodeResponseCode )
				{
					$maxMind->setAVS( $result->transaction->avsPostalCodeResponseCode );
				}
				
				if ( $result->transaction->cvvResponseCode and \in_array( $result->transaction->cvvResponseCode, array( 'M', 'N' ) ) )
				{
					$maxMind->setCVV( $result->transaction->cvvResponseCode === 'M' );
				}
			}
			
			/* Return */
			$transaction->gw_id = $result->transaction->id;
			return \IPS\DateTime::ts( $result->transaction->authorizationExpiresAt->getTimestamp() );
		}
		else
		{
			if ( isset( $result->transaction ) and isset( $result->transaction->id ) )
			{
				$transaction->gw_id = $result->transaction->id;
			}
			$transaction->status = $transaction::STATUS_REFUSED;
			$extra = $transaction->extra;
			$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'noteRaw' => $result->message );
			$transaction->extra = $extra;
			$transaction->save();
			
			throw new \DomainException( \IPS\Member::loggedIn()->language()->get( static::errorMessageFromCode( ( isset( $result->transaction ) and isset( $result->transaction->processorResponseCode ) ) ? $result->transaction->processorResponseCode : '' ) ) );
		}
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
		$result = $this->gateway()->transaction()->void( $transaction->gw_id );
		
		if ( !$result->success )
		{
			throw new \LogicException( $result->message );
		}
	}
	
	/**
	 * Capture
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	void
	 * @throws	\LogicException
	 */
	public function capture( \IPS\nexus\Transaction $transaction )
	{
		$result = $this->gateway()->transaction()->submitForSettlement( $transaction->gw_id );
		
		if ( !$result->success )
		{
			throw new \LogicException( $result->message );
		}
	}
	
	/**
	 * Refund
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction to be refunded
	 * @param	float|NULL				$amount			Amount to refund (NULL for full amount - always in same currency as transaction)
	 * @param	string|NULL				$reason			Reason for refund, if applicable
	 * @return	mixed									Gateway reference ID for refund, if applicable
	 * @throws	\Exception
 	 */
	public function refund( \IPS\nexus\Transaction $transaction, $amount = NULL, $reason = NULL )
	{
		$result = $this->gateway()->transaction()->refund( $transaction->gw_id, $amount );
		
		if ( !$result->success )
		{
			throw new \LogicException( $result->message );
		}
	}
	
	/**
	 * Extra data to show on the ACP transaction page
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @param	string					$type			"short" or "full"
	 * @return	string
 	 */
	public function extraData( \IPS\nexus\Transaction $transaction, $type = 'short' )
	{
		if ( !$transaction->gw_id )
		{
			return NULL;
		}
		
		try
		{
			$braintreeData = $this->gateway()->transaction()->find( $transaction->gw_id );
		}
		catch ( \Exception $e )
		{
			return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->braintreeData( NULL, 'error' );
		}
		
		return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->braintreeData( $braintreeData );
	}
	
	/**
	 * Extra data to show on the ACP transaction page for a dispute
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @param	array					$ref			Dispute log data
	 * @return	string
 	 */
	public function disputeData( \IPS\nexus\Transaction $transaction, $log )
	{
		if ( isset( $log['ref'] ) )
		{
			try
			{
				$response = $this->gateway()->dispute()->find( $log['ref'] );
			}
			catch ( \Exception $e )
			{
				return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->braintreeDispute( $transaction, $log, NULL, TRUE );
			}
			return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->braintreeDispute( $transaction, $log, $response );
		}
	}

	/**
	 * Run any gateway-specific anti-fraud checks and return status for transaction
	 * This is only called if our local anti-fraud rules have not matched
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	string
	 */
	public function fraudCheck( \IPS\nexus\Transaction $transaction )
	{
		try
		{
			$response = $this->gateway()->transaction()->find( $transaction->gw_id );
			if ( isset( $response->riskData ) and isset( $response->riskData->decision ) and $response->riskData->decision === 'Review' )
			{
				return $transaction::STATUS_HELD;
			}
			return $transaction::STATUS_PAID;
		}
		catch ( \Exception $e )
		{
			return $transaction::STATUS_PAID;
		}
	}
	
	/**
	 * URL to view transaction in gateway
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	\IPS\Http\Url|NULL
 	 */
	public function gatewayUrl( \IPS\nexus\Transaction $transaction )
	{
		$settings = json_decode( $this->settings, TRUE );
		return \IPS\Http\Url::external( "https://" . ( \IPS\NEXUS_TEST_GATEWAYS ? 'sandbox.' : '' ) . "braintreegateway.com/merchants/{$settings['merchant_id']}/transactions/{$transaction->gw_id}" );
	}
	
	/* !ACP Configuration */
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader('braintree_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'paymethod_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => "nexus_paymethod_{$this->id}" ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'paymethod_countries', ( $this->id and $this->countries !== '*' ) ? explode( ',', $this->countries ) : '*', FALSE, array( 'options' => array_map( function( $val )
		{
			return "country-{$val}";
		}, array_combine( \IPS\GeoLocation::$countries, \IPS\GeoLocation::$countries ) ), 'multiple' => TRUE, 'unlimited' => '*', 'unlimitedLang' => 'no_restriction' ) ) );
		$this->settings( $form );
	}
	
	/**
	 * Settings
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$settings = json_decode( $this->settings, TRUE );

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_payments.js', 'nexus', 'admin' ) );
		$form->attributes['data-controller'] = 'nexus.admin.payments.braintreeSetup';
		if ( isset( \IPS\Request::i()->_getClientToken ) )
		{
			try
			{
				$_settings = array( 'merchant_id' => \IPS\Request::i()->merchant_id, 'public_key' => \IPS\Request::i()->public_key, 'private_key' => \IPS\Request::i()->private_key );

				$tokens = array();
				foreach ( $this->gateway( $_settings )->merchantAccount()->all() as $merchantAccount )
				{
					$tokens[ (string) $merchantAccount->currencyIsoCode ] = $this->gateway()->clientToken()->generate( array( 'merchantAccountId' => $merchantAccount->id ) );
				}
				
				\IPS\Output::i()->json( array( 'success' => true, 'tokens' => $tokens ) );
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->json( array( 'success' => false, 'message' => \IPS\Member::loggedIn()->language()->addToStack('braintree_auth_error') ) );
			}
		}
				
		$form->addHeader('braintree_keys');
		$form->addMessage('braintree_keys_blurb');
		$form->add( new \IPS\Helpers\Form\Text( 'braintree_merchant_id', $settings ? $settings['merchant_id'] : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'braintree_public_key', $settings ? $settings['public_key'] : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'braintree_private_key', $settings ? $settings['private_key'] : NULL, TRUE ) );
		
		$form->addHeader('braintree_type_header');
		$formId = $this->id ? "form_{$this->id}" : 'gw';
		$form->add( new \IPS\Helpers\Form\Radio( 'braintree_type', isset( $settings['type'] ) ? $settings['type'] : 'paypal', TRUE, array(
			'options'	=> array(
				'paypal'		=> 'braintree_type_paypal',
				'card'			=> 'braintree_type_card',
				'venmo'			=> 'braintree_type_venmo',
				'applepay'		=> 'braintree_type_applepay',
				'googlepay'		=> 'braintree_type_googlepay',
			),
			'toggles'	=> array(
				'paypal'		=> array( 'braintree_paypal_vault', 'braintree_paypal_credit' ),
				'card'			=> array( 'braintree_cards', 'braintree_3d_secure', 'braintree_advanced_fraud' ),
				'venmo'			=> array( 'braintree_venmo_vault', 'braintree_venmo_profile' ),
				'applepay'		=> array( 'braintree_advanced_fraud' ),
				'googlepay'		=> array( 'braintree_googlepay_merchant', 'braintree_advanced_fraud' ),
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_paypal_vault', ( $settings and isset( $settings['paypal_vault'] ) ) ? $settings['paypal_vault'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'braintree_paypal_vault' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_paypal_credit', ( $settings and isset( $settings['paypal_credit'] ) ) ? $settings['paypal_credit'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'braintree_paypal_credit' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_venmo_vault', ( $settings and isset( $settings['venmo_vault'] ) ) ? $settings['venmo_vault'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'braintree_venmo_vault' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'braintree_venmo_profile', ( $settings and isset( $settings['venmo_profile'] ) ) ? $settings['venmo_profile'] : NULL, FALSE, array( 'nullLang' => 'braintree_venmo_profile_null' ), NULL, NULL, NULL, 'braintree_venmo_profile' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'braintree_googlepay_merchant', ( $settings and isset( $settings['googlepay_merchant'] ) ) ? $settings['googlepay_merchant'] : NULL, NULL, array(), NULL, NULL, NULL, 'braintree_googlepay_merchant' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_cards', ( $settings and isset( $settings['cards'] ) ) ? $settings['cards'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'braintree_cards' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_3d_secure', isset( $settings['3d_secure'] ) ? $settings['3d_secure'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'braintree_3d_secure' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'braintree_advanced_fraud', isset( $settings['advanced_fraud'] ) ? $settings['advanced_fraud'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'braintree_advanced_fraud' ) );
		
		$form->addHeader('braintree_webhook_header');
		$form->addMessage( 'braintree_webhook', '', TRUE, 'braintree_webhook' );
		\IPS\Member::loggedIn()->language()->words["braintree_webhook"] = sprintf( \IPS\Member::loggedIn()->language()->get('braintree_webhook'), (string) \IPS\Http\Url::internal( 'applications/nexus/interface/gateways/braintree.php', 'interface' ) );
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
		parse_str( urldecode( \IPS\Request::i()->braintree_features ), $features );
		
		try
		{
			$merchantAccounts = $this->gateway( $settings )->merchantAccount()->all();
			
			foreach ( $merchantAccounts as $merchantAccount )
			{
				if ( $settings['type'] != 'paypal' or isset( $features[ (string) $merchantAccount->currencyIsoCode ] ) and $features[ (string) $merchantAccount->currencyIsoCode ]['paypal'] === 'true' )
				{					
					$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['id'] = $merchantAccount->id;
					
					if ( $settings['type'] == 'card' )
					{
						$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'] = array();
						
						foreach ( $features[ (string) $merchantAccount->currencyIsoCode ]['cardTypes'] as $type )
						{				
							switch ( $type )
							{
								case 'Visa':
									$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'][] = \IPS\nexus\CreditCard::TYPE_VISA;
									break;
								case 'American Express':
									$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'][] =  \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS;
									break;
								case 'MasterCard':
									$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'][] = \IPS\nexus\CreditCard::TYPE_MASTERCARD;
									break;
								case 'Discover':
									$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'][] =  \IPS\nexus\CreditCard::TYPE_DISCOVER;
									break;
								case 'JCB':
									$settings['merchant_accounts'][ (string) $merchantAccount->currencyIsoCode ]['cardTypes'][] =  \IPS\nexus\CreditCard::TYPE_JCB;
									break;
							}
						}
					}
				}
			}
		}
		catch ( \Exception $e )
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('braintree_auth_error') );
		}		
		
		return $settings;
	}
	
	/* !Utility Methods */
	
	/**
	 * @brief	$gateway	Braintree gateway
	 */
	protected $_gateway;
	
	/**
	 * Get Braintree gateway
	 *
	 * @return	\Braintree_Gateway
	 */
	public function gateway( $settings = NULL )
	{
		if ( !$this->_gateway )
		{
			require_once \IPS\ROOT_PATH . '/applications/nexus/sources/Gateway/Braintree/lib/autoload.php';
			
			$settings = $settings ?: json_decode( $this->settings, TRUE );
			
			$this->_gateway = new \Braintree_Gateway( array(
				'environment'	=> \IPS\NEXUS_TEST_GATEWAYS ? 'sandbox' : 'production',
				'merchantId'	=> $settings['merchant_id'],
				'publicKey'		=> $settings['public_key'],
				'privateKey'	=> $settings['private_key']
			) );
		}
		
		return $this->_gateway;
	}
	
	/**
	 * Customer-readable error message from processor code
	 *
	 * @param	string	$code	Processor response code
	 * @return	string
	 */
	public static function errorMessageFromCode( $code )
	{
		$errorMessage = 'gateway_err';
		if ( \in_array( $code, array( '2000', '2001', '2002', '2003', '2012', '2013', '2014', '2015', '2019', '2020', '2021', '2027', '2038', '2039', '2041', '2043', '2044', '2046', '2050', '2053', '2054', '2057', '2059', '2060', '2071', '2074', '2075', '2082', '2083', '2086', '2098', '2099' ) ) )
		{
			$errorMessage = 'card_refused';
		}
		elseif ( \in_array( $code, array( '2004', '2005', '2006', '2008', '2010', '2072', '2093' ) ) )
		{
			$errorMessage = 'card_information_invalid';
		}
		elseif ( \in_array( $code, array( '2007', '2009', '2022', '2029', '2032', '2047', '2051', '2061', '2062', '2076', '2084' ) ) )
		{
			$errorMessage = 'payment_refused';
		}
		return $errorMessage;
	}

	/**
	 * Get short code for PayPal State
	 *
	 * @param   \IPS\GeoLocation $address
	 * @return  NULL|string
	 */
	protected function _payPalState( \IPS\GeoLocation $address ): ?string
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

		return $state;
	}
}
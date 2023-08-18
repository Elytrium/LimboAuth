<?php
/**
 * @brief		Stripe Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		13 Mar 2014
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stripe Gateway
 */
class _Stripe extends \IPS\nexus\Gateway
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
	 * @see		<a href="https://stripe.com/docs/currencies">Supported Currencies</a>
	 * @return	bool
	 */
	public function checkValidity( \IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress = NULL, \IPS\nexus\Customer $customer = NULL, $recurrings = array() )
	{
		$settings = json_decode( $this->settings, TRUE );
		
		/* Stripe has a minimum transaction fee. This is based on the businesses currency, but as we don't know what the transaction rate is
			we'll do this check only in the transactions we know - anything else will be rejected when the user tries to pay */
		switch ( $amount->currency )
		{
			case 'AUD':
			case 'BRL':
			case 'CAD':
			case 'CHF':
			case 'EUR':
			case 'INR':
			case 'JPY':
			case 'NZD':
			case 'SGD':
			case 'USD':
				if ( static::_amountAsCents( $amount ) < 50 )
				{
					return FALSE;
				}
				break;
			case 'AED':
			case 'PLN':
			case 'RON':
				if ( static::_amountAsCents( $amount ) < 200 )
				{
					return FALSE;
				}
				break;
			case 'BGN':
				if ( static::_amountAsCents( $amount ) < 100 )
				{
					return FALSE;
				}
				break;
			case 'CZK':
				if ( static::_amountAsCents( $amount ) < 1500 )
				{
					return FALSE;
				}
				break;
			case 'DKK':
				if ( static::_amountAsCents( $amount ) < 250 )
				{
					return FALSE;
				}
				break;
			case 'GBP':
				if ( static::_amountAsCents( $amount ) < 30 )
				{
					return FALSE;
				}
				break;
			case 'HKD':
				if ( static::_amountAsCents( $amount ) < 400 )
				{
					return FALSE;
				}
				break;
			case 'MXN':
				if ( static::_amountAsCents( $amount ) < 1000 )
				{
					return FALSE;
				}
				break;
			case 'MYR':
				if ( static::_amountAsCents( $amount ) < 2 )
				{
					return FALSE;
				}
				break;
			case 'NOK':
			case 'SEK':
				if ( static::_amountAsCents( $amount ) < 300 )
				{
					return FALSE;
				}
				break;
		}
		
		/* And the maximum is based on the size of the amount */
		if ( \strlen( static::_amountAsCents( $amount ) ) > 8 )
		{
			return FALSE;
		}
				
		/* European methods are EUR only */
		if ( isset( $settings['type'] ) and \in_array( $settings['type'], array( 'bancontact', 'giropay', 'ideal', 'sofort' ) ) )
		{
			if ( $amount->currency !== 'EUR' )
			{
				return FALSE;
			}
			
			/* Sofort is only for Austria, Belgium, Germany, Netherlands, Spain */
			if ( $settings['type'] == 'sofort' and ( !$billingAddress or !\in_array( $billingAddress->country, array( 'AT', 'BE', 'DE', 'NL', 'ES' ) ) ) )
			{
				return FALSE;
			}
		}

		/* Otherwise, see https://stripe.com/docs/currencies (it makes it look like what currencies are supported depends on the business country, but at time of writing, all countries have the same list */
		else
		{
			if( !\in_array( $amount->currency, array(
				'USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
				'BSD', 'BWP', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD',
				'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES',
				'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR',
				'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON',
				'RSD', 'RUB', 'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD', 'TWD',
				'TZS', 'UAH', 'UGX', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR', 'ZMW'
			) ) ) {
				return false;
			}
		}
		
		/* Check if Payment Request API is supported  */
		if ( isset( $settings['type'] ) and $settings['type'] === 'native' )
		{
			if ( isset( \IPS\Request::i()->cookie['PaymentRequestAPI'] ) and !\IPS\Request::i()->cookie['PaymentRequestAPI'] )
			{
				return FALSE;
			}
			if ( $billingAddress and !\in_array( $billingAddress->country, array( 'AT', 'AU', 'BE', 'BR', 'CA', 'CH', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'HK', 'IE', 'IN', 'IT', 'JP', 'LT', 'LU', 'LV', 'MX', 'NL', 'NZ', 'NO', 'PH', 'PL', 'PT', 'RO', 'SE', 'SG', 'SK', 'US' ) ) )
			{
				return FALSE;
			}
		}
		
		/* Still here? Do normal checks */
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
		return ( ( !isset( $settings['type'] ) or $settings['type'] == 'card' ) and $settings['cards'] );
	}
	
	/**
	 * Admin can manually charge using this gateway?
	 *
	 * @param	\IPS\nexus\Customer	$customer	The customer we're wanting to charge
	 * @return	bool
	 */
	public function canAdminCharge( \IPS\nexus\Customer $customer )
	{
		return TRUE;
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
		return !\in_array( $settings['type'], array( 'amex', 'native' ) );
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
		if ( !isset( $settings['type'] ) or $settings['type'] === 'card' )
		{			
			if ( isset( \IPS\Request::i()->createPaymentIntent ) )
			{
				$member = $member ?: \IPS\nexus\Customer::loggedIn();
				
				try
				{			
					$paymentMethod = \IPS\Request::i()->createPaymentIntent;
					$savePaymentMethod = ( isset( \IPS\Request::i()->savePaymentMethod ) and \IPS\Request::i()->savePaymentMethod and \IPS\Request::i()->savePaymentMethod != 'false' );
					
					if ( \is_numeric( $paymentMethod ) ) // That means it's a saved card
					{
						try
						{
							$card = \IPS\nexus\Customer\CreditCard::load( $paymentMethod );
							if ( $card->member->member_id !== $member->member_id )
							{
								throw new \DomainException;
							}
							
							$paymentMethod = $card->data;
							$savePaymentMethod = FALSE;
						}
						catch ( \Exception $e ) { }
					}
					
					$response = $this->_createPaymentIntent( $invoice, $amount, $member, $paymentMethod, $savePaymentMethod, $type );
					\IPS\Output::i()->json( array( 'success' => true, 'type' => 'payment', 'response' => $response ) );
				}
				catch( \IPS\nexus\Gateway\Stripe\Exception $e )
				{
					\IPS\Output::i()->json( array( 'success' => false, 'message' => $e->getMessage() ), 500 );
				}
			}
			
			
			$supportedCards = array( \IPS\nexus\CreditCard::TYPE_VISA, \IPS\nexus\CreditCard::TYPE_MASTERCARD );
			if ( ( $settings['country'] == 'IN' and $invoice->currency == 'USD' ) or ( $settings['country'] == 'MX' and $invoice->currency == 'MXN' ) or ( !\in_array( $settings['country'], [ 'IN', 'MX' ] ) and !\in_array( $invoice->currency, array( 'AFN', 'AOA', 'ARS', 'BOB', 'BRL', 'CLP', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'FKP', 'GNF', 'GTQ', 'HNL', 'HUF', 'INR', 'LAK', 'MUR', 'NIO', 'PAB', 'PEN', 'PYG', 'SHP', 'SRD', 'STD', 'UYU', 'XOF', 'XPF' ) ) ) )
			{
				$supportedCards[] = \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS;
			}
			if ( \in_array( $settings['country'], [ 'AU', 'CA', 'JA', 'NZ', 'US' ] ) and $invoice->currency == 'USD' )
			{
				$supportedCards[] = \IPS\nexus\CreditCard::TYPE_DISCOVER;
				$supportedCards[] = \IPS\nexus\CreditCard::TYPE_DINERS_CLUB;
				$supportedCards[] = \IPS\nexus\CreditCard::TYPE_JCB;
			}
			
			$setupIntent = NULL;
			if ( $type === 'card' )
			{
				try
				{
					$setupIntent = $this->api( 'setup_intents', array( 'usage' => 'off_session' ) );
				}
				catch ( \IPS\nexus\Gateway\Stripe\Exception $e )
				{
					\IPS\Log::log( $e, 'checkout' );
					return array();
				}
			}

			return array( 'card' => new \IPS\nexus\Form\CreditCard( $this->id . '_card', NULL, FALSE, array(
				'types' 		=> $supportedCards,
				'attr'			=> array(
					'data-controller'	=> 'nexus.global.gateways.stripe',
					'data-id'			=> $this->id,
					'class'				=> 'ipsHide',
					'data-key'			=> $settings['publishable_key'],
					'data-name'			=> $member ? $member->cm_name : $invoice->member->cm_name,
					'data-address1'		=> ( $invoice->billaddress and isset( $invoice->billaddress->addressLines[0] ) ) ? $invoice->billaddress->addressLines[0] : NULL,
					'data-address2'		=> ( $invoice->billaddress and isset( $invoice->billaddress->addressLines[1] ) ) ? $invoice->billaddress->addressLines[1] : NULL,
					'data-city'			=> $invoice->billaddress ? $invoice->billaddress->city : NULL,
					'data-state'		=> $invoice->billaddress ? $invoice->billaddress->region : NULL,
					'data-zip'			=> $invoice->billaddress ? $invoice->billaddress->postalCode : NULL,
					'data-country'		=> $invoice->billaddress ? $invoice->billaddress->country : NULL,
					'data-email'		=> $member ? $member->email : $invoice->member->email,
					'data-phone'		=> isset( $member->cm_phone ) ? $member->cm_phone : NULL,
					'data-amount'		=> static::_amountAsCents( $amount ),
					'data-currency'		=> $amount->currency,
					'data-setupIntent'	=> $setupIntent ? $setupIntent['id'] : NULL,
					'data-setupSecret'	=> $setupIntent ? $setupIntent['client_secret'] : NULL,
				),
				'jsRequired'	=> TRUE,
				'names'			=> FALSE,
				'dummy'			=> TRUE,
				'save'			=> ( $settings['cards'] ) ? $this : NULL,
				'member'		=> $member,
			) ) );
		}
		elseif ( $settings['type'] === 'sofort' and \IPS\NEXUS_TEST_GATEWAYS )
		{
			return array(
				new \IPS\Helpers\Form\Radio( 'stripe_debug_action', 'succeeding_charge', FALSE, array( 'options' => array(
					'succeeding_charge'	=> 'stripe_debug_succeeding_charge',
					'pending_charge'	=> 'stripe_debug_pending_charge',
					'failing_charge'	=> 'stripe_debug_failing_charge',
				) ) )
			);
		}
		elseif ( \in_array( $settings['type'], array( 'alipay', 'bancontact', 'giropay', 'ideal', 'sofort' ) ) )
		{
			return array();
		}
		else
		{
			return array( 'card' => new \IPS\Helpers\Form\Custom( $this->id . '_card', NULL, FALSE, array(
				'rowHtml'	=> function( $field ) use ( $settings, $invoice, $amount ) {
					return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->paymentRequestApi( $field, $this, $settings['publishable_key'], $invoice->billaddress ? $invoice->billaddress->country : 'US', $invoice, mb_strtolower( $amount->currency ), static::_amountAsCents( $amount ), $amount->amount );
				}
			), NULL, NULL, NULL, $this->id . '_card' ) );
		}
	}
	
	/**
	 * Create a payment intent
	 *
	 * @param	\IPS\nexus\Invoice						$invoice				Invoice
	 * @param	\IPS\nexus\Money							$amount					The amount to pay now
	 * @param	\IPS\nexus\Customer						$member					The customer making the payment, or, if in the ACP charging to a member's card, the customer that the payment is for
	 * @param	string									$paymentMethod			Stripe payment method ID ("pm_....") - can be for a new card or a card already on file
	 * @param	bool									$savePaymentMethod		Save this payment method for future (off-session) payments?
	 * @param	string									$type					'checkout' means the cusotmer is doing this on the normal checkout screen, 'admin' means the admin is doing this in the ACP, 'card' means the user is just adding a card
	 * @return	array
	 * @throws	
	 */
	protected function _createPaymentIntent( \IPS\nexus\Invoice $invoice, \IPS\nexus\Money $amount, \IPS\nexus\Customer $member, $paymentMethod, $savePaymentMethod = FALSE, $type = 'checkout' )
	{	
		$data = array(
			'amount'				=> static::_amountAsCents( $amount ),
			'currency'				=> $amount->currency,
			'capture_method'		=> 'manual',
			'confirm'				=> 'true',
			'confirmation_method'	=> 'manual',
			'description'			=> $invoice->title,
			'metadata'				=> array(
				"Invoice ID"			=> $invoice->id,
				"Customer ID"			=> $member->member_id,
				"Customer Email"		=> $member->email,
				"IP Address"			=> \IPS\Request::i()->ipAddress(),
				"Payment Method ID"		=> $this->id
			),
			'payment_method'		=> $paymentMethod,
		);
		if ( $type === 'admin' )
		{
			$data['metadata']["Admin"] = \IPS\Member::loggedIn()->member_id;
		}
		if ( $invoice->shipaddress )
		{
			$data['shipping'] = array(
				'address'	=> array(
					'city'			=> $invoice->shipaddress->city,
					'country'		=> $invoice->shipaddress->country,
					'line1'			=> isset( $invoice->shipaddress->addressLines[0] ) ? $invoice->shipaddress->addressLines[0] : NULL,
					'line2'			=> isset( $invoice->shipaddress->addressLines[1] ) ? $invoice->shipaddress->addressLines[1] : NULL,
					'postal_code'	=> $invoice->shipaddress->postalCode,
					'state'			=> $invoice->shipaddress->region,
				),
				'name'		=> $invoice->member->cm_name,
				'phone'		=> isset( $invoice->member->cm_phone ) ? $invoice->member->cm_phone : NULL
			);
		}
		$profiles = $member->cm_profiles;
		if ( isset( $profiles[ $this->id ] ) )
		{
			$data['customer'] = $profiles[ $this->id ];
		}
		
		/* We need the customer profile, so if we don't have it - try to fetch it now */
		if ( !isset( $data['customer'] ) )
		{
			$response = $this->api( 'payment_methods/' . $paymentMethod, array(), 'GET' );

			if( isset( $response['customer'] ) )
			{
				$data['customer'] = $response['customer'];

				if( $member->member_id )
				{
					$profiles[ $this->id ] = $response['customer'];
					
					$member->cm_profiles = $profiles;
					$member->save();
				}
				else
				{
					$profiles[ $this->id ] = $response['customer'];
					
					$guestData = $invoice->guest_data;
					$guestData['member']['cm_profiles'] = $profiles;
					$guestData['cards'][] = array(
						'card_method'	=> $this->id,
						'card_data'		=> $paymentMethod
					);
					$invoice->guest_data = $guestData;
					$invoice->save();
				}
			}
		}

		if ( $savePaymentMethod )
		{
			$data['save_payment_method'] = 'true';
			$data['setup_future_usage'] = 'off_session';
		
			if ( !isset( $data['customer'] ) )
			{
				if ( $member->member_id )
				{
					$response = $this->api( 'customers', array(
						'name'			=> $member->cm_name,
						'email'			=> $member->email,
						'metadata' 		=> array(
							"Customer ID" => $member->member_id
						)
					) );
					$profiles[ $this->id ] = $response['id'];
					
					$member->cm_profiles = $profiles;
					$member->save();
				}
				else
				{
					$response = $this->api( 'customers', array(
						'name'			=> $invoice->member->cm_name,
						'email'			=> $invoice->member->email,
					) );
					$profiles[ $this->id ] = $response['id'];
					
					$guestData = $invoice->guest_data;
					$guestData['member']['cm_profiles'] = $profiles;
					$guestData['cards'][] = array(
						'card_method'	=> $this->id,
						'card_data'		=> $paymentMethod
					);
					$invoice->guest_data = $guestData;
					$invoice->save();
				}
				
				$data['customer'] = $profiles[ $this->id ];
			}
		}
		
		$response = $this->api( 'payment_intents', $data );
		
		if ( $savePaymentMethod and $member->member_id )
		{
			\IPS\Db::i()->insert( 'nexus_customer_cards', array(
				'card_member'	=> $member->member_id,
				'card_method'	=> $this->id,
				'card_data'		=> $paymentMethod
			) );
		}
		
		return $response;
	}
		
	/**
	 * Authorize
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	array|\IPS\nexus\Customer\CreditCard	$values			Values from form OR a stored card object if this gateway supports them
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		*If* MaxMind is enabled, the request object will be passed here so gateway can additional data before request is made	
	 * @param	array									$recurrings		Details about recurring costs
	 * @param	string|NULL								$source			'checkout' if the customer is doing this at a normal checkout, 'renewal' is an automatically generated renewal invoice, 'manual' is admin manually charging. NULL is unknown
	 * @return	\IPS\DateTime|NULL|array					Auth is valid until, NULL to indicate auth is good forever, or if we need to wait for a webhook: an array containing existing transaction IDs to skip
	 * @throws	\LogicException							Message will be displayed to user
	 */
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array(), $source = NULL )
	{
		$settings = json_decode( $this->settings, TRUE );
		
		/* Do we need to redirect? */
		if ( \in_array( $settings['type'], array( 'alipay', 'bancontact', 'giropay', 'ideal', 'sofort' ) ) and !isset( $values[ $this->id . '_card' ] ) )
		{	
			/* We need a transaction ID */
			$transaction->save();
			
			/* Create the source */
			$data = array(
				'type'			=> $settings['type'],
				'amount'		=> static::_amountAsCents( $transaction->amount ),
				'currency'		=> $transaction->amount->currency,
				'redirect'		=> array(
					'return_url'	=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/stripe-redirector.php?nexusTransactionId=' . $transaction->id
				),
				'owner'			=> array(
					'email'			=> $transaction->member->email,
					'name'			=> ( \IPS\NEXUS_TEST_GATEWAYS and isset( $values['stripe_debug_action'] ) ) ? $values['stripe_debug_action'] : $transaction->member->cm_name,
				)
			);
			if ( isset( $transaction->member->cm_phone ) and $transaction->member->cm_phone )
			{
				$data['owner']['phone'] = $transaction->member->cm_phone;
			}
			if ( $settings['type'] == 'sofort' )
			{
				$data['sofort'] = array( 'country' => $transaction->invoice->billaddress->country );
			}
			if ( $transaction->invoice->billaddress )
			{
				$data['owner']['address'] = array(
					'city'			=> $transaction->invoice->billaddress->city,
					'country'		=> $transaction->invoice->billaddress->country,
					'line1'			=> isset( $transaction->invoice->billaddress->addressLines[0] ) ? $transaction->invoice->billaddress->addressLines[0] : NULL,
					'line2'			=> isset( $transaction->invoice->billaddress->addressLines[1] ) ? $transaction->invoice->billaddress->addressLines[1] : NULL,
					'postal_code'	=> $transaction->invoice->billaddress->postalCode,
					'state'			=> $transaction->invoice->billaddress->region,
				);
			}
			$response = $this->api( 'sources', $data );
			
			/* Redirect the user */
			if ( isset( $response['redirect']['url'] ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $response['redirect']['url'] ) );
			}
			throw new \RuntimeException;
		}
				
		/* Set MaxMind type */
		if ( $maxMind )
		{
			if ( !isset( $settings['type'] ) or $settings['type'] === 'card' )
			{
				$maxMind->setTransactionType('creditcard');
			}
		}		
		/* Set card */
		$card = $values[ $this->id . '_card' ];
		
		/* If we got a payment intent ID, we just need to confirm it and then wait for the webhooks */
		if ( $card and !\is_string( $card ) and $card->token and mb_substr( $card->token, 0, 3 ) === 'pi_' )
		{
			/* Confirm it */
			$response = $this->api( "payment_intents/{$card->token}" );
			if ( $response['status'] === 'requires_confirmation' )
			{
				$response = $this->api( "payment_intents/{$card->token}/confirm" );
			}
						
			/* Stripe webhooks will take it from there, so show the "Processing your payment" multiredirector */
			return isset( $values['previousTransactions'] ) ? explode( ',', $values['previousTransactions'] ) : array();
		}
		
		$transaction->save();
		
		/* Build data */
		$data = array(
			'amount'		=> static::_amountAsCents( $transaction->amount ),
			'currency'		=> $transaction->amount->currency,
			'capture'		=> 'false',
			'description'	=> $transaction->invoice->title,
			'metadata'		=> array(
				"Transaction ID"	=> $transaction->id,
				"Invoice ID"		=> $transaction->invoice->id,
				"Customer ID"		=> $transaction->member->member_id,
				"Customer Email"	=> $transaction->member->email,
			),			
		);
		if ( $transaction->invoice->shipaddress )
		{
			$data['shipping'] = array(
				'address'	=> array(
					'city'			=> $transaction->invoice->shipaddress->city,
					'country'		=> $transaction->invoice->shipaddress->country,
					'line1'			=> isset( $transaction->invoice->shipaddress->addressLines[0] ) ? $transaction->invoice->shipaddress->addressLines[0] : NULL,
					'line2'			=> isset( $transaction->invoice->shipaddress->addressLines[1] ) ? $transaction->invoice->shipaddress->addressLines[1] : NULL,
					'postal_code'	=> $transaction->invoice->shipaddress->postalCode,
					'state'			=> $transaction->invoice->shipaddress->region,
				),
				'name'		=> $transaction->invoice->member->cm_name,
				'phone'		=> isset( $transaction->invoice->member->cm_phone ) ? $transaction->invoice->member->cm_phone : NULL
			);
		}
		
		/* Source-based */
		if ( \is_string( $card ) )
		{			
			$data['source'] = $card;
			unset( $data['capture'] );
		}
		
		/* Stored Card (for recurring payments) */
		elseif ( $card instanceof \IPS\nexus\Gateway\Stripe\CreditCard )
		{		
			$profiles = $card->member->cm_profiles;
			$data['customer'] = $profiles[ $this->id ];
			
			if ( mb_substr( $values[ $this->id . '_card' ]->data, 0, 3 ) === 'pm_' )
			{
				// If we are dealing with a payment method, we have to create a payment intent rather than directly creating a charge
				$paymentIntentData = $data;
				$paymentIntentData['capture_method'] = 'manual';
				$paymentIntentData['confirm'] = 'true';
				$paymentIntentData['confirmation_method'] = 'manual';
				$paymentIntentData['payment_method'] = $values[ $this->id . '_card' ]->data;
				$paymentIntentData['off_session'] = ( $source === 'renewal' ? 'recurring' : 'one_off' );
				unset( $paymentIntentData['capture'] );
				
				$paymentIntent = $this->api( 'payment_intents', $paymentIntentData );
				if ( $paymentIntent['status'] === 'requires_capture' )
				{
					$transaction->gw_id = $paymentIntent['id'];
					return \IPS\DateTime::ts( $paymentIntent['created'] )->add( new \DateInterval( 'P7D' ) );
				}
				else
				{
					// It could just be that the card requires authentication, but throwing this exception will automatically try any other payment methods
					// on file and then ultimately send the customer an invoice if none succeeded, so we can just allow that to happen.
					throw new \DomainException("Unexpected Payment Intent status: {$paymentIntent['status']}");
				}
			}
			elseif ( mb_substr( $values[ $this->id . '_card' ]->data, 0, 4 ) === 'src_' )
			{
				$data['source'] = $values[ $this->id . '_card' ]->data;
			}
			else
			{
				$data['card'] = $values[ $this->id . '_card' ]->data;
			}
		}
												
		/* Authorize */
		try
		{
			$response = $this->api( 'charges', $data );
		}
		catch ( \IPS\nexus\Gateway\Stripe\Exception $e )
		{
			if ( isset( $e->details['charge'] ) and $e->details['charge'] )
			{
				$note = $e->getMessage();
				try
				{
					$response = $this->api( "charges/{$e->details['charge']}", NULL, 'get' );
					if ( isset( $response['outcome']['seller_message'] ) )
					{
						$note = $response['outcome']['seller_message'];
					}
				}
				catch ( \Exception $e ) { }
				
				$transaction->gw_id = $e->details['charge'];
				$transaction->status = $transaction::STATUS_REFUSED;
				$extra = $transaction->extra;
				$extra['history'][] = array( 's' => \IPS\nexus\Transaction::STATUS_REFUSED, 'noteRaw' => $note );
				$transaction->extra = $extra;
				$transaction->save();
			}
			throw $e;
		}
		$transaction->gw_id = $response['id'];
		
		/* Return */
		if ( isset( $response['captured'] ) and $response['captured'] )
		{
			return NULL;
		}
		else
		{
			return \IPS\DateTime::ts( $response['created'] )->add( new \DateInterval( 'P7D' ) );
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
		try
		{
			if ( mb_substr( $transaction->gw_id, 0, 3 ) === 'pi_' )
			{
				$this->api( "payment_intents/{$transaction->gw_id}/cancel" );
			}
			else
			{
				$response = $this->refund( $transaction );
			}
		}
		catch ( \Exception $e ) { }
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
		$settings = json_decode( $this->settings, TRUE );
		if ( isset( $settings['type'] ) and \in_array( $settings['type'], array( 'alipay', 'bancontact', 'giropay', 'ideal', 'sofort' ) ) )
		{
			return;
		}
				
		try
		{
			if ( mb_substr( $transaction->gw_id, 0, 3 ) === 'pi_' )
			{
				$this->api( "payment_intents/{$transaction->gw_id}/capture" );
			}
			else
			{
				$this->api( "charges/{$transaction->gw_id}/capture" );
			}
		}
		catch( \IPS\nexus\Gateway\Stripe\Exception $e )
		{
			/* If we have already captured/refunded the charge we don't need to let an exception bubble up */
			if( $e->details['code'] == 'charge_already_captured' or $e->details['code'] == 'charge_already_refunded' )
			{
				return;
			}
			/* PaymentIntent returns a different error for an already captured payment */
			elseif( $e->details['code'] == 'payment_intent_unexpected_state'
				AND mb_strpos( $e->details['message'], 'already been captured' ) !== FALSE
				AND $e->details['payment_intent']['amount_capturable'] === 0 )
			{
				return;
			}

			throw $e;
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
		$data = NULL;
		if ( $amount )
		{
			$data['amount'] = static::_amountAscents( new \IPS\nexus\Money( $amount, $transaction->currency ) );
		}
		if ( $reason )
		{
			$data['reason'] = $reason;
		}
		
		if ( mb_substr( $transaction->gw_id, 0, 3 ) === 'pi_' )
		{
			$response = $this->api( "charges?payment_intent={$transaction->gw_id}", NULL, 'get' );

			foreach ( $response['data'] as $charge )
			{
				if ( $charge['paid'] )
				{
					$this->api( "charges/{$charge['id']}/refund", $data );
					return;
				}
			}
		}
		else
		{
			$this->api( "charges/{$transaction->gw_id}/refund", $data );
		}
	}
	
	/**
	 * Refund Reasons that the gateway understands, if the gateway supports this
	 *
	 * @return	array
 	 */
	public static function refundReasons()
	{
		return array(
			'requested_by_customer'	=> 'refund_reason_requested_by_customer',
			'duplicate'				=> 'refund_reason_duplicate',
			'fraudulent'			=> 'refund_reason_stripe_fraudulent',
		);
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
			if ( mb_substr( $transaction->gw_id, 0, 3 ) === 'pi_' )
			{
				$response = $this->api( "charges?payment_intent={$transaction->gw_id}", NULL, 'get' );

				foreach ( $response['data'] as $charge )
				{
					if ( $charge['paid'] )
					{
						$response = $charge;
						$response['source'] = $response['payment_method_details'];
						foreach ( array( 'cvc_check', 'address_line1_check', 'address_postal_code_check' ) as $k )
						{
							$response['source']['card'][ $k ] = isset( $response['payment_method_details']['card']['checks'][ $k ] ) ? $response['payment_method_details']['card']['checks'][ $k ] : 'unavailable';
						}
						break;
					}
				}
			}
			else
			{
				$response = $this->api( "charges/{$transaction->gw_id}", NULL, 'get' );
				
				if ( isset( $response['source']['three_d_secure'] ) )
				{
					try
					{
						$response2 = $this->api( "sources/{$response['source']['three_d_secure']['card']}", NULL, 'get' );
						$response['source']['card'] = $response2['card'];
					}
					catch ( \Exception $e )
					{
						return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->stripeData( $response, 'error' );
					}
				}
				elseif ( $response['source']['object'] === 'card' and isset( $response['card'] ) ) // For cards stored in older versions
				{
					$response['source']['card'] = $response['card'];
				}
			}
		}
		catch ( \Exception $e )
		{
			return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->stripeData( NULL, 'error' );
		}		
										
		return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->stripeData( $response );
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
				$response = $this->api( "disputes/{$log['ref']}", NULL, 'get' );
			}
			catch ( \Exception $e )
			{
				return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->stripeDispute( $transaction, $log, NULL, TRUE );
			}
			return \IPS\Theme::i()->getTemplate( 'transactions', 'nexus', 'admin' )->stripeDispute( $transaction, $log, $response );
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
			if ( mb_substr( $transaction->gw_id, 0, 3 ) === 'pi_' )
			{
				$response = $this->api( "charges?payment_intent={$transaction->gw_id}", NULL, 'get' );

				foreach ( $response['data'] as $charge )
				{
					if ( $charge['status'] === 'succeeded' )
					{
						if ( isset( $charge['outcome']['risk_level'] ) and $charge['outcome']['risk_level'] === 'elevated' )
						{
							return $transaction::STATUS_HELD;
						}
					}
				}
			}
			else
			{
				$response = $this->api( "charges/{$transaction->gw_id}", NULL, 'get' );
				if ( isset( $response['outcome']['risk_level'] ) and $response['outcome']['risk_level'] === 'elevated' )
				{
					return $transaction::STATUS_HELD;
				}
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
		return \IPS\Http\Url::external( "https://dashboard.stripe.com/payments/{$transaction->gw_id}" );
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
		$form->addHeader('stripe_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'paymethod_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_paymethod_{$this->id}" : NULL ) ) );
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
		$form->addHeader('stripe_keys');
		$form->addMessage('stripe_keys_blurb');
		$form->add( new \IPS\Helpers\Form\Text( 'stripe_secret_key', $settings ? $settings['secret_key'] : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'stripe_publishable_key', $settings ? $settings['publishable_key'] : NULL, TRUE ) );
		$form->addHeader('stripe_type_header');
		$form->add( new \IPS\Helpers\Form\Radio( 'stripe_type', isset( $settings['type'] ) ? $settings['type'] : 'card', TRUE, array(
			'options'	=> array(
				'card'		=> 'stripe_type_card',
				'native' 	=> 'stripe_type_native',
				'alipay' 	=> 'stripe_type_alipay',
				'bancontact'=> 'stripe_type_bancontact',
				'giropay'	=> 'stripe_type_giropay',
				'ideal'		=> 'stripe_type_ideal',
				'sofort'	=> 'stripe_type_sofort',
			),
			'toggles'	=> array(
				'card'		=> array( 'stripe_cards' ),
				'native'	=> array( 'stripe_apple_pay_file')
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'stripe_cards', $settings ? $settings['cards'] : TRUE, FALSE, array(), NULL, NULL, NULL, 'stripe_cards' ) );

		if ( \IPS\CIC )
		{
			$form->add( new \IPS\Helpers\Form\Upload( 'm_validationfile', $this->validationfile ? \IPS\File::get( 'nexus_Gateways', $this->validationfile ) : '', FALSE, array(  'storageExtension' => 'nexus_Gateways' ), NULL, NULL, NULL, 'stripe_apple_pay_file' ) );
			\IPS\Member::loggedIn()->language()->words[ 'm_validationfile']     = \IPS\Member::loggedIn()->language()->addToStack('stripe_apple_verificationfile');
		}

		$form->addHeader('stripe_webhook');
		$form->addMessage('stripe_webhook_blurb');
		\IPS\Member::loggedIn()->language()->words["stripe_webhook_blurb"] = sprintf( \IPS\Member::loggedIn()->language()->get('stripe_webhook_blurb'), (string) \IPS\Http\Url::internal( 'applications/nexus/interface/gateways/stripe.php', 'interface' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'stripe_webhook_secret', isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : NULL, TRUE ) );
	}

	/**
	 * @brief Webhook events we need
	 */
	protected $webhookEvents = array( 'source.chargeable', 'charge.succeeded', 'charge.failed', 'charge.dispute.created', 'charge.dispute.closed' );
	
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
			/* Get the country */
			$response = $this->api( 'account', NULL, 'get', $settings );
			$settings['country'] = $response['country'];
			
			/* Check we have a webhook. We can't actually verify if the secret we have is correct, but if we see a webhook with our URL we'll assume it is */
			$correctWebhookUrl = \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/stripe.php';
			$webhookId = NULL;
			$webhooks = $this->api( 'webhook_endpoints', NULL, 'get', $settings );
			foreach ( $webhooks['data'] as $webhook )
			{
				if ( $webhook['url'] === $correctWebhookUrl and $webhook['status'] === 'enabled' )
				{
					if( \in_array( '*', $webhook['enabled_events'] ) OR \count( array_intersect( $webhook['enabled_events'], $this->webhookEvents ) ) === \count( $this->webhookEvents ) )
					{
						$webhookId = $webhook['id'];
						break;
					}
				}
			}
			if ( !$webhookId )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'stripe_webhook_invalid', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $this->webhookEvents ) ) ) ) );
			}
			$settings['webhook_id'] = $webhookId;
															
			/* Return */
			\IPS\core\AdminNotification::remove( 'nexus', 'ConfigurationError', "pm{$this->id}" );
			return $settings;
		}
		catch ( \IPS\nexus\Gateway\Stripe\Exception $e )
		{
			throw new \InvalidArgumentException( $e->details['message'] );
		}
	}
	
	/* !Utility Methods */
	
	/**
	 * Send API Request
	 *
	 * @param	string		$uri		The API to request (e.g. "charges")
	 * @param	array		$data		The data to send
	 * @param	string		$method		Method (get/post)
	 * @param	array|NULL	$settings	Settings (NULL for saved setting)
	 * @return	array
	 * @throws	\IPS\Http|Exception
	 * @throws	\IPS\nexus\Gateway\PayPal\Exception
	 */
	public function api( $uri, $data=NULL, $method='post', $settings = NULL )
	{		
		$settings = $settings ?: json_decode( $this->settings, TRUE );
		
		$response = \IPS\Http\Url::external( 'https://api.stripe.com/v1/' . $uri )
			->request( \IPS\LONG_REQUEST_TIMEOUT )
			->setHeaders( array( 'Stripe-Version' => '2022-11-15' ) )
			->forceTls()
			->login( $settings['secret_key'], '' )
			->$method( $data )
			->decodeJson();
			
		if ( isset( $response['error'] ) )
		{
			throw new \IPS\nexus\Gateway\Stripe\Exception( $response['error'] );
		}
		
		return $response;
	}
	
	/**
	 * Convert amount into cents
	 *
	 * @param	\IPS\nexus\Money	$amount		The amount
	 * @return	int
	 */
	protected static function _amountAsCents( \IPS\nexus\Money $amount )
	{
		if ( \in_array( $amount->currency, array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VUV', 'XAF', 'XOF', 'XPF' ) ) )
		{
			return \intval( (string) $amount->amount );
		}
		else
		{
			return \intval( (string) $amount->amount->multiply( new \IPS\Math\Number( '100' ) ) );
		}
	}
}
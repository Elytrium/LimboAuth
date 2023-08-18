<?php
/**
 * @brief		Authorize.Net Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Mar 2014
 */

namespace IPS\nexus\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Authorize.Net Gateway
 */
class _AuthorizeNet extends \IPS\nexus\Gateway
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
		
		/* If we don't know the processor, we can only go off the default checks */
		if ( !$settings['processor'] )
		{
			return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
		}
		
		/* European processors require a full name and billing address */
		if ( \in_array( $settings['processor'], array( 8, 9, 10, 11, 12, 13 ) ) )
		{
			if ( !$customer->cm_first_name or !$customer->cm_last_name or !$billingAddress )
			{
				return FALSE;
			}
		}
		/* GPN Canada and WorldPay Streamline Processing Platform also require a billing address */
		if ( \in_array( $settings['processor'], array( 4, 13 ) ) )
		{
			if ( !$billingAddress )
			{
				return FALSE;
			}
		}
		
		/* For everything else, check the currencies they can handle... */
		$currencies = array();
		if ( \in_array( $settings['processor'], array( 1, 2, 4, 5, 6, 7, 8, 13, 14 ) ) )
		{
			$currencies[] = 'USD';
		}
		if ( \in_array( $settings['processor'], array( 1, 2, 4 ) ) )
		{
			$currencies[] = 'CAD';
		}
		if ( \in_array( $settings['processor'], array( 8, 9, 10, 11, 12, 13 ) ) )
		{
			$currencies[] = 'GBP';
		}
		if ( \in_array( $settings['processor'], array( 8, 9, 11, 13 ) ) )
		{
			$currencies[] = 'EUR';
		}
		if ( \in_array( $settings['processor'], array( 14, 15 ) ) )
		{
			$currencies[] = 'AUD';
		}
		if ( \in_array( $settings['processor'], array( 14 ) ) )
		{
			$currencies[] = 'NZD';
		}
		if ( !\in_array( $amount->currency, $currencies ) )
		{
			return FALSE;
		}
		
		/* Still here? We're good - pass to parent for general checks */
		return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
	}

	/**
	 * [Node] Get Node Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'gateway_deprecated', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('gateway__' . $this->gateway ) ) ) );
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
		return ( $settings['method'] === 'AIM' and $settings['cim'] );
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
		return ( $settings['method'] === 'AIM' );
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
		$return = array();
		$settings = json_decode( $this->settings, TRUE );
		
		/* Accepted types */
		if( $settings['processor'] )
		{
			$types = array();
			
			if ( \in_array( $settings['processor'], array( 1, 2, 3, 4, 5, 6, 7 ) ) )
			{
				$types[] = \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS;
				$types[] = \IPS\nexus\CreditCard::TYPE_DINERS_CLUB;
				$types[] = \IPS\nexus\CreditCard::TYPE_DISCOVER;
				$types[] = \IPS\nexus\CreditCard::TYPE_JCB;
			}
			elseif ( \in_array( $settings['processor'], array( 9, 13 ) ) )
			{
				$types[] = \IPS\nexus\CreditCard::TYPE_JCB;
			}
			
			$types[] =  \IPS\nexus\CreditCard::TYPE_MASTERCARD;
			$types[] =  \IPS\nexus\CreditCard::TYPE_VISA;
		}
		else
		{
			$types = array( \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS, \IPS\nexus\CreditCard::TYPE_DINERS_CLUB, \IPS\nexus\CreditCard::TYPE_DISCOVER, \IPS\nexus\CreditCard::TYPE_JCB, \IPS\nexus\CreditCard::TYPE_MASTERCARD, \IPS\nexus\CreditCard::TYPE_VISA );
		}
		$options = array( 'types' => $types, 'member' => $member );
		
		/* If we're using DPM, we include all that data in here */
		if ( $settings['method'] == 'DPM' )
		{
			$timestamp = time();
															
			$options['jsRequired']	= TRUE;
			$options['names']		= FALSE;
			$options['attr']		= array(
				'data-controller'	=> 'nexus.global.gateways.authorizenet',
				'data-id'			=> $this->id,
				'class'				=> 'ipsHide',
				'data-url'			=> \IPS\NEXUS_TEST_GATEWAYS ? 'https://test.authorize.net/gateway/transact.dll' : 'https://secure.authorize.net/gateway/transact.dll',
				'data-fields'		=> json_encode( array(
					'x_login'			=> $settings['login'],
					'x_version'			=> '3.1',
					'x_type'			=> 'AUTH_ONLY',
					'x_method'			=> 'CC',
					'x_amount'			=> (string) $amount->amount,
					'x_currency_code'	=> $amount->currency,
					'x_fp_hash'			=> isset( $settings['signature_key'] ) ? hash_hmac( 'sha512', "{$settings['login']}^{$invoice->id}^{$timestamp}^{$amount->amount}^{$amount->currency}", hex2bin( $settings['signature_key'] ) ) : hash_hmac( 'md5', "{$settings['login']}^{$invoice->id}^{$timestamp}^{$amount->amount}^{$amount->currency}", $settings['tran_key'] ),
					'x_fp_sequence'		=> $invoice->id,
					'x_fp_timestamp'	=> $timestamp,
					'x_invoice_num'		=> $invoice->id,
					'x_description'		=> $invoice->title,
					'x_first_name'		=> $invoice->member->cm_first_name,
					'x_last_name'		=> $invoice->member->cm_last_name,
					'x_address'			=> $invoice->billaddress ? implode( ', ', $invoice->billaddress->addressLines ) : '',
					'x_city'			=> $invoice->billaddress ? $invoice->billaddress->city : '',
					'x_state'			=> $invoice->billaddress ? $invoice->billaddress->region : '',
					'x_zip'				=> $invoice->billaddress ? $invoice->billaddress->postalCode : '',
					'x_country'			=> $invoice->billaddress ? $invoice->billaddress->country : '',
					'x_phone'			=> $invoice->member->cm_phone,
					'x_email'			=> $invoice->member->email,
					'x_email_customer'	=> 'FALSE',
					'x_cust_id'			=> $invoice->member->member_id,
					'x_customer_ip'		=> \IPS\Request::i()->ipAddress(),
					'x_relay_response'	=> 'TRUE',
					'x_relay_url'		=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/authorize.php?payment_method=' . $this->id,
				) )
			);
			
		}
		
		/* If we're using AIM, we might be able to save */
		elseif ( $this->canStoreCards() )
		{
			$options['save'] = $this;
			$options['member'] = $member;
		}
		
		/* And then return the card field */
		$return['card'] = new \IPS\nexus\Form\CreditCard( $this->id . '_card', NULL, FALSE, $options );
		return $return;
	}
	
	/**
	 * Authorize (AIM only)
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
		$card = \is_array( $values ) ? $values[ $this->id . '_card' ] : $values;
		
		/* Stored Card */
		if ( $card instanceof \IPS\nexus\Gateway\AuthorizeNet\CreditCard )
		{
			/* MaxMind */
			if ( $maxMind )
			{
				$maxMind->setTransactionType( 'creditcard' );
			}
			
			/* Authorize */
			$profiles = $card->member->cm_profiles;
			$xml = $card->createAuthenticationXml( 'createCustomerProfileTransactionRequest' );
			$xml->addChild( 'transaction', array(
				'profileTransAuthOnly' => array(
					'amount'					=> (string) $transaction->amount->amount,
					'customerProfileId'			=> $profiles[ $card->method->id ],
					'customerPaymentProfileId'	=> $card->data,
				),
			) );
			$response = $this->_handleResponse( explode( ',', $card->api( $xml )->directResponse ) );
			
			/* Change $card to the actual \IPS\nexus\CreditCard object so we get the last 4 digits correctly */
			$card = $card->card;
		}
		
		/* New Card */
		else
		{
			/* MaxMind */
			if ( $maxMind )
			{
				$maxMind->setCard( $card );
			}
			
			/* Authorize */
			$response = $this->api( array(
				'x_type'			=> 'AUTH_ONLY',
				'x_method'			=> 'CC',
				'x_invoice_num'		=> $transaction->invoice->id,
				'x_description'		=> $transaction->invoice->title,
				'x_amount'			=> (string) $transaction->amount->amount,
				'x_currency_code'	=> $transaction->amount->currency,
				'x_card_num'		=> $card->number,
				'x_exp_date'		=> str_pad( $card->expMonth, 2, '0', STR_PAD_LEFT ) . '-' . $card->expYear,
				'x_card_code'		=> $card->ccv,
				'x_first_name'		=> $transaction->member->cm_first_name,
				'x_last_name'		=> $transaction->member->cm_last_name,
				'x_address'			=> $transaction->invoice->billaddress ? implode( ', ', $transaction->invoice->billaddress->addressLines ) : '',
				'x_city'			=> $transaction->invoice->billaddress ? $transaction->invoice->billaddress->city : '',
				'x_state'			=> $transaction->invoice->billaddress ? $transaction->invoice->billaddress->region : '',
				'x_zip'				=> $transaction->invoice->billaddress ? $transaction->invoice->billaddress->postalCode : '',
				'x_country'			=> $transaction->invoice->billaddress ? $transaction->invoice->billaddress->country : '',
				'x_phone'			=> $transaction->member->cm_phone,
				'x_email'			=> $transaction->member->email,
				'x_email_customer'	=> 'FALSE',
				'x_cust_id'			=> $transaction->member->member_id,
				'x_customer_ip'		=> \IPS\Request::i()->ipAddress(),
				'x_relay_response'	=> 'false'
			) );
			
			/* Save? */
			if ( $card->save )
			{
				if ( !$transaction->member->member_id )
				{
					$transaction->member = $transaction->invoice->createAccountForGuest();
					\IPS\Session::i()->setMember( $transaction->member );
					\IPS\Member\Device::loadOrCreate( $transaction->member, FALSE )->updateAfterAuthentication( NULL );
				}
				
				try
				{
					$storedCard = new \IPS\nexus\Gateway\AuthorizeNet\CreditCard;
					$storedCard->member = $transaction->member;
					$storedCard->method = $this;
					$storedCard->card = $card;
					$storedCard->save();
				}
				catch ( \Exception $e ) { /* If there's any issue with saving (which may happen for a duplicate card) we can just carry on since we already auth'd */ }
			}
		}
		
		/* Set AVS code for MaxMind */
		if ( $maxMind and $response['avs'] )
		{
			$maxMind->setAVS( $response['avs'] );
		}
		
		/* Remember the last 4 digits of the card number as we'll need them to refund */
		$extra = $transaction->extra;
		$extra['lastFour'] = $card->lastFour;
		$transaction->extra = $extra;
		
		/* Return */
		$transaction->gw_id = $response['id'];
		return \IPS\DateTime::create()->add( new \DateInterval( 'P30D' ) );
	}
	
	/**
	 * Void (AIM and DPM)
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	void
	 * @throws	\Exception
	 */
	public function void( \IPS\nexus\Transaction $transaction )
	{
		$this->api( array(
			'x_type'			=> 'VOID',
			'x_trans_id'		=> $transaction->gw_id,
		) );
	}
	
	/**
	 * Capture (AIM and DPM)
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	void
	 * @throws	\LogicException
	 */
	public function capture( \IPS\nexus\Transaction $transaction )
	{
		$this->api( array(
			'x_type'			=> 'PRIOR_AUTH_CAPTURE',
			'x_trans_id'		=> $transaction->gw_id,
			'x_relay_response'	=> 'false'
		) );
	}
	
	/**
	 * Refund (AIM and DPM)
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction to be refunded
	 * @param	float|NULL				$amount			Amount to refund (NULL for full amount - always in same currency as transaction)
	 * @return	mixed									Gateway reference ID for refund, if applicable
	 * @throws	\Exception
 	 */
	public function refund( \IPS\nexus\Transaction $transaction, $amount = NULL )
	{		
		try
		{
			$this->api( array(
				'x_type'		=> 'CREDIT',
				'x_trans_id'	=> $transaction->gw_id,
				'x_amount'		=> (string) ( $amount ?: $transaction->amount->amount ),
				'x_card_num'	=> $transaction->extra['lastFour']
			) );
		}
		catch( \IPS\nexus\Gateway\AuthorizeNet\Exception $e )
		{
			/* If the transaction is unsettled (error code 54 from the API), then we need to void it instead */
			if ( $e->getCode() == 54 )
			{
				$this->void( $transaction );
			}
		}
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

		$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'gateway_deprecated', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('gateway__' . $this->gateway ) ) ) ), 'ipsMessage ipsMessage_warning' );
		
		$form->add( new \IPS\Helpers\Form\Text( 'authorizenet_login', $settings['login'], TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'authorizenet_tran_key', $settings['tran_key'], TRUE ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'authorizenet_method', $settings['method'], TRUE, array(
			'options' 	=> array(
				'AIM'		=> 'authorizenet_AIM',
				'DPM'		=> 'authorizenet_DPM'
			),
			'toggles'	=> array(
				'AIM'		=> array( 'authorizenet_cim' ),
				'DPM'		=> array( 'authorizenet_signature_key', 'authorizenet_hash' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'authorizenet_cim', $settings['cim'], FALSE, array(), NULL, NULL, NULL, 'authorizenet_cim' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'authorizenet_signature_key', isset( $settings['signature_key'] ) ? $settings['signature_key'] : '', FALSE, array(), NULL, NULL, NULL, 'authorizenet_signature_key' ) );
		if ( isset( $settings['hash'] ) and !isset( $settings['signature_key'] ) )
		{
			$form->add( new \IPS\Helpers\Form\Text( 'authorizenet_hash', $settings['hash'], FALSE, array(), NULL, NULL, NULL, 'authorizenet_hash' ) );
		}
		$form->add( new \IPS\Helpers\Form\Select( 'authorizenet_processor', isset( $settings['processor'] ) ? $settings['processor'] : '0', FALSE, array( 'options' => array(
			0		=> 'dont_know',
			'North American Payment Processors'	=> array(
				1 	=> 'Chase Paymentech Tampa Processing Platform',
				2	=> 'Elavon',
				3	=> 'First Data Merchant Services (FDMS) Omaha, Nashville, and EFSNet Processing Platforms',
				4	=> 'Global Payments',
				5	=> 'Heartland Payment Systems',
				6	=> 'TSYS Acquiring Solutions',
				7	=> 'WorldPay Atlanta Processing Platform',
			),
			'European Payment Processors'		=> array(
				8	=> 'AIB Merchant Services',
				9	=> 'Barclaycard',
				10	=> 'First Data Merchant Solutions (MSIP platform)',
				11	=> 'HSBC Merchant Services',
				12	=> 'Lloyds Bank Cardnet',
				13	=> 'Streamline',
			),
			'Asia-Pacific Processors'			=> array(
				14	=> 'FDI Australia',
				15	=> 'Westpac',
			)
		) ) ) );
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
		return $settings;
	}
	
	/* !Utility Methods */
	
	/**
	 * Send API Request
	 *
	 * @param	array	$data	The data to send
	 * @return	array
	 * @throws	\IPS\Http|Exception
	 * @throws	\IPS\nexus\Gateway\AuthorizeNet\Exception
	 */
	public function api( $data=array() )
	{
		$settings = json_decode( $this->settings, TRUE );
				
		$response = \IPS\Http\Url::external( \IPS\NEXUS_TEST_GATEWAYS ? 'https://test.authorize.net/gateway/transact.dll' : 'https://secure.authorize.net/gateway/transact.dll' )->request()->post( array_merge( array(
			'x_login'			=> $settings['login'],
			'x_tran_key'		=> $settings['tran_key'],
			'x_version'			=> '3.1',
			'x_delim_data'		=> 'TRUE',
			'x_delim_char'		=> '~',
			'x_encap_char'		=> '',
		), $data ) );
		
		return $this->_handleResponse( explode( '~', $response ) );
	}
	
	/**
	 * Handle response
	 *
	 * @param	array	$data
	 * @return	array
	 * @throws	\IPS\nexus\Gateway\AuthorizeNet\Exception
	 */
	protected function _handleResponse( $response )
	{
		$response = array_map( function( $v ) {
			return trim( $v, ';' );
		}, $response );
		
		if ( $response[0] != 1 )
		{
			throw new \IPS\nexus\Gateway\AuthorizeNet\Exception( $response[2] );
		}
		
		return array(
			'id'	=> $response[6],
			'avs'	=> $response[5],
		);
	}
}
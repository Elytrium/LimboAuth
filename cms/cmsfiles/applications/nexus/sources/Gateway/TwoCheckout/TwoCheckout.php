<?php
/**
 * @brief		2Checkout Gateway
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
 * 2Checkout Gateway
 */
class _TwoCheckout extends \IPS\nexus\Gateway
{
	/* !Features (Each gateway will override) */

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
		/* We require both a full name and a billing address */
		if ( !$customer->cm_first_name or !$customer->cm_last_name or !$billingAddress )
		{
			return FALSE;
		}
		
		/* Still here? We're good - pass to parent for general checks */
		return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
	}
		
	/* !Payment Gateway */
	
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
		$transaction->save();
		
		/* Basic Data */
		$data = array(
			'sid'					=> $settings['sid'],
			'nexustransactionid'	=> $transaction->id,
			'mode'					=> '2CO',
			'currency_code'			=> $transaction->amount->currency,
			'purchase_step'			=> 'payment-method',
			'x_receipt_link_url'	=> \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/2checkout.php',
			'card_holder_name'		=> $transaction->member->cm_name,
			'street_address'		=> $transaction->invoice->billaddress->addressLines[0],
			'street_address2'		=> isset( $transaction->invoice->billaddress->addressLines[1] ) ? $transaction->invoice->billaddress->addressLines[1] : '',
			'city'					=> $transaction->invoice->billaddress->city,
			'state'					=> $transaction->invoice->billaddress->region,
			'zip'					=> $transaction->invoice->billaddress->postalCode,
			'country'				=> $transaction->invoice->billaddress->country,
			'email'					=> $transaction->member->email,
			'phone'					=> $transaction->member->cm_phone
		);
		
		/* Shipping address */
		if ( $transaction->invoice->shipaddress )
		{
			$data['ship_name'] = $transaction->member->cm_name;
			$data['ship_street_address'] = $transaction->invoice->shipaddress->addressLines[0];
			if ( isset( $transaction->invoice->shipaddress->addressLines[1] ) )
			{
				$data['ship_street_address2'] = $transaction->invoice->shipaddress->addressLines[1];
			}
			$data['ship_city'] = $transaction->invoice->shipaddress->city;
			$data['ship_state'] = $transaction->invoice->shipaddress->region;
			$data['ship_zip'] = $transaction->invoice->shipaddress->postalCode;
			$data['ship_country'] = $transaction->invoice->shipaddress->country;
		}
		
		/* Items */
		if ( $transaction->amount->amount->compare( $transaction->invoice->total->amount ) === 0 )
		{
			$summary = $transaction->invoice->summary();
			
			$k = 0;
			foreach ( $summary['items'] as $item )
			{
				$data["li_{$k}_type"] = $item->price->amount->isGreaterThanZero() ? 'product' : 'coupon';
				$data["li_{$k}_name"] = $item->name;
				$data["li_{$k}_quantity"] = $item->quantity;
				$data["li_{$k}_price"] = (string) ( $item->price->amount->isPositive() ? $item->price->amount : $item->price->amount->multiply( new \IPS\Math\Number( '-1' ) ) );
				$data["li_{$k}_tangible"] = $item->physical ? 'Y' : 'N';
				
				$d = 0;
				foreach ( $item->details as $label => $value )
				{
					$data["li_{$k}_option_{$d}_name"] = $label;
					$data["li_{$k}_option_{$d}_value"] = $value;
					$d++;
				}
				
				$k++;
			}
			
			foreach ( $summary['shipping'] as $shipping )
			{
				$data["li_{$k}_type"] = 'shipping';
				$data["li_{$k}_name"] = $shipping->name;
				$data["li_{$k}_quantity"] = 1;
				$data["li_{$k}_price"] = (string) $shipping->price->amount;
				$data["li_{$k}_tangible"] = 'Y';
				
				$k++;
			}
			
			foreach ( $summary['tax'] as $tax )
			{
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $tax['name'] );
				$data["li_{$k}_type"] = 'tax';
				$data["li_{$k}_name"] = $tax['name'];
				$data["li_{$k}_quantity"] = 1;
				$data["li_{$k}_price"] = (string) $tax['amount']->amount;
				$data["li_{$k}_tangible"] = 'N';

				$k++;
			}
		}
		else
		{
			$data["li_0_type"] = 'product';
			$data["li_0_name"] = $transaction->member->language()->addToStack( 'partial_payment_desc', FALSE, array( 'sprintf' => array( $transaction->invoice->id ) ) );
			$data["li_0_quantity"] = 1;
			$data["li_0_price"] = (string) $transaction->amount->amount;
			$data["li_0_tangible"] = 'N';
		}
		
		/* Test mode */
		if ( \IPS\NEXUS_TEST_GATEWAYS )
		{
			$data['demo'] = 'Y';
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::external( 'https://www.2checkout.com/checkout/purchase' )->setQueryString( $data ) );
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
		$data = array(
			'sale_id'	=> $transaction->gw_id,
			'category'	=> 5,
			'comment'	=> 'Nexus'
		);
		
		if ( $amount )
		{
			$data['amount'] = $amount;
			$data['currency'] = 'vendor';
		}

		$settings = json_decode( $this->settings, TRUE );
		$response = \IPS\Http\Url::external( 'https://www.2checkout.com/api/sales/refund_invoice' )->request()->forceTls()->login( $settings['api_username'], $settings['api_password'] )->post( $data )->decodeJson();
		
		if ( $response['response_code'] != 'OK' )
		{
			throw new \LogicException( $response['response_message'] );
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
		
		$form->add( new \IPS\Helpers\Form\Text( 'twocheckout_api_username', $settings['api_username'], TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'twocheckout_api_password', $settings['api_password'], TRUE ) );
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
			$response = \IPS\Http\Url::external( 'https://www.2checkout.com/api/acct/detail_company_info' )->request()->forceTls()->setHeaders( array( 'Accept' => 'application/json' ) )->login( $settings['api_username'], $settings['api_password'] )->get()->decodeJson();
			if ( isset( $response['errors'] ) )
			{
				throw new \InvalidArgumentException( $response['errors'][0]['message'] );
			}
			if ( $response['response_code'] != 'OK' )
			{
				throw new \InvalidArgumentException( $response['response_message'] );
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			if ( \OPENSSL_VERSION_NUMBER < 268439647 )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'twocheckout_openssl_req' ) );
			}
			else
			{
				throw $e;
			}
		}
			
		$settings['sid'] = $response['vendor_company_info']['vendor_id'];
		$settings['word'] = $response['vendor_company_info']['secret_word'];
		return $settings;
	}
}
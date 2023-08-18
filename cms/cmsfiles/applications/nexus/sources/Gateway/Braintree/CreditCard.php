<?php
/**
 * @brief		Braintree Stored Card / PayPal Account
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		5 Dec 2018
 */

namespace IPS\nexus\Gateway\Braintree;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Braintree Stored Card / PayPal Account
 */
class _CreditCard extends \IPS\nexus\Customer\CreditCard
{
	/**
	 * @brief	Braintree Payment Method
	 */
	protected $_paymentMethod;
	
	/**
	 * @brief	Card
	 */
	protected $_card;
	
	/**
	 * Get card
	 *
	 * @return	\IPS\nexus\CreditCard
	 */
	public function get_card()
	{
		if ( !$this->_card )
		{
			if ( !$this->_paymentMethod )
			{
				$this->_paymentMethod = $this->method->gateway()->paymentMethod()->find( $this->data );
			}
			
			$this->_card = new \IPS\nexus\CreditCard;
			
			if ( $this->_paymentMethod instanceof \Braintree\PayPalAccount )
			{
				$this->_card->type = \IPS\nexus\CreditCard::TYPE_PAYPAL;
				$this->_card->number = $this->_paymentMethod->email;
			}
			elseif ( $this->_paymentMethod instanceof \Braintree\VenmoAccount )
			{
				$this->_card->type = \IPS\nexus\CreditCard::TYPE_VENMO;
				$this->_card->number = $this->_paymentMethod->username;
			}
			else
			{
				$this->_card->lastFour = $this->_paymentMethod->last4;
				switch ( $this->_paymentMethod->cardType )
				{
					case 'Visa':
						$this->_card->type = \IPS\nexus\CreditCard::TYPE_VISA;
						break;
					case 'American Express':
						$this->_card->type =  \IPS\nexus\CreditCard::TYPE_AMERICAN_EXPRESS;
						break;
					case 'MasterCard':
						$this->_card->type = \IPS\nexus\CreditCard::TYPE_MASTERCARD;
						break;
					case 'Discover':
						$this->_card->type =  \IPS\nexus\CreditCard::TYPE_DISCOVER;
						break;
					case 'JCB':
						$this->_card->type =  \IPS\nexus\CreditCard::TYPE_JCB;
						break;
				}
				$this->_card->expMonth = $this->_paymentMethod->expirationMonth;
				$this->_card->expYear = $this->_paymentMethod->expirationYear;
			}
		}
				
		return $this->_card;
	}
	
	/**
	 * Set card
	 *
	 * @param	\IPS\nexus\CreditCard	$card	The card
	 * @return	void
	 */
	public function set_card( \IPS\nexus\CreditCard $card )
	{		
		$settings = json_decode( $this->method->settings, TRUE );

		try
		{
			$braintreeCustomer = $this->method->gateway()->customer()->find( \IPS\Settings::i()->site_secret_key . '-' . $this->member->member_id );
		}
		catch ( \Braintree\Exception\NotFound $e )
		{
			$result = $this->method->gateway()->customer()->create( array(
				'email'					=> $this->member->email,
				'firstName'				=> $this->member->cm_first_name,
				'id'					=> \IPS\Settings::i()->site_secret_key . '-' . $this->member->member_id,
				'lastName'				=> $this->member->cm_last_name,
				'phone'					=> $this->member->cm_phone,
				'deviceData'			=> isset( $_POST[ $this->method->id . '_card' ]['device'] ) ? $_POST[ $this->method->id . '_card' ]['device'] : NULL
			) );
		}
		
		$result = $this->method->gateway()->paymentMethod()->create( array(
			'customerId'			=> \IPS\Settings::i()->site_secret_key . '-' . $this->member->member_id,
			'options'				=> array(
				'verificationMerchantAccountId'	=> $settings['merchant_accounts'][ $this->member->defaultCurrency() ]['id'],
				'verifyCard'					=> TRUE,
			),
			'paymentMethodNonce'	=> $card->token,
			'deviceData'			=> isset( $_POST[ $this->method->id . '_card' ]['device'] ) ? $_POST[ $this->method->id . '_card' ]['device'] : NULL
		) );
		
		if ( !$result->success )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->get( $this->method::errorMessageFromCode( ( isset( $result->verification ) and isset( $result->verification->processorResponseCode ) ) ? $result->verification->processorResponseCode : '' ) ) );
		}
		
		$this->data = $result->paymentMethod->token;
		$this->save();
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		try
		{
			$this->method->gateway()->paymentMethod()->delete( $this->data );
		}
		catch ( \Exception $e ) { }
		return parent::delete();
	}
}
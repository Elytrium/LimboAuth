<?php
/**
 * @brief		Authorize.Net CIM Stored Card
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Mar 2014
 */

namespace IPS\nexus\Gateway\AuthorizeNet;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Authorize.Net CIM Stored Card
 */
class _CreditCard extends \IPS\nexus\Customer\CreditCard
{
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
			$profiles = $this->member->cm_profiles;
			if ( !isset( $profiles[ $this->method->id ] ) )
			{
				throw new \UnexpectedValueException;
			}
			
			$xml = $this->createAuthenticationXml('getCustomerPaymentProfileRequest');
			$xml->addChild( 'customerProfileId', $profiles[ $this->method->id ] );
			$xml->addChild( 'customerPaymentProfileId', $this->data );
			$response = $this->api( $xml );
			
			$this->_card = new \IPS\nexus\CreditCard;
			$this->_card->lastFour	= mb_substr( $response->paymentProfile->payment->creditCard->cardNumber, -4 );
			if ( isset( $response->paymentProfile->payment->creditCard->cardType ) )
			{
				switch ( $response->paymentProfile->payment->creditCard->cardType )
				{
					case 'Visa':
						$this->_card->type = \IPS\nexus\CreditCard::TYPE_VISA;
						break;
					case 'AmericanExpress':
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
					case 'DinersClub':
						$this->_card->type =  \IPS\nexus\CreditCard::TYPE_DINERS_CLUB;
						break;
					default:
						$this->_card->type = NULL;
						break;
				}
			}
			else
			{
				$this->_card->type		= NULL;
			}
			$this->_card->expYear	= NULL;
			$this->_card->expMonth	= NULL; 
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
		/* Create Profile */
		$profiles = $this->member->cm_profiles;		
		if ( !isset( $profiles[ $this->method->id ] ) )
		{
			$xml = $this->createAuthenticationXml( 'createCustomerProfileRequest' );
			$xml->addChild( 'profile', array(
				'merchantCustomerId' => $this->member->member_id
			) );
			$response = $this->api( $xml );
			if ( !$response->customerProfileId )
			{
				throw new \UnexpectedValueException;
			}
			
			$profiles[ $this->method->id ] = (string) $response->customerProfileId;
			$this->member->cm_profiles = $profiles;
			$this->member->save();
		}
								
		/* Add the card */
		$xml = $this->createAuthenticationXml( 'createCustomerPaymentProfileRequest' );
		$xml->addChild( 'customerProfileId', $profiles[ $this->method->id ] );
		$xml->addChild( 'paymentProfile', array(
			'payment' => array(
				'creditCard'	=> array(
					'cardNumber'	=> $card->number,
					'expirationDate'=> $card->expYear . '-' . str_pad( $card->expMonth, 2, '0', STR_PAD_LEFT ),
					'cardCode'		=> $card->ccv
				)
			)
		) );
		$xml->addChild( 'validationMode', 'none' );
		$response = $this->api( $xml );
		if ( !$response->customerPaymentProfileId )
		{
			throw new \UnexpectedValueException;
		}
		
		/* Check it doesn't already exist */
		if ( \count( \IPS\Db::i()->select( 'card_data', 'nexus_customer_cards', array( 'card_member=? AND card_method=? AND card_data=?', $this->member->member_id, $this->method->id, (string) $response->customerPaymentProfileId ) ) ) )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('card_is_duplicate') );
		}
		
		/* Save */
		$this->data = (string) $response->customerPaymentProfileId;
		$this->save();
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		$profiles = $this->member->cm_profiles;
		if ( isset( $profiles[ $this->method->id ] ) )
		{
			$xml = $this->createAuthenticationXml( 'deleteCustomerPaymentProfileRequest' );
			$xml->addChild( 'customerProfileId', $profiles[ $this->method->id ] );
			$xml->addChild( 'customerPaymentProfileId', $this->data );
			$response = $this->api( $xml );
		}
		
		parent::delete();
	}
	
	/**
	 * Send API Request
	 *
	 * @param	\IPS\Xml\SimpleXML	$xml	The XML to send
	 * @return	\IPS\Xml\SimpleXML
	 */
	public function api( \IPS\Xml\SimpleXML $xml )
	{		
		return \IPS\Http\Url::external( \IPS\NEXUS_TEST_GATEWAYS ? 'https://apitest.authorize.net/xml/v1/request.api' : 'https://api.authorize.net/xml/v1/request.api' )
			->request()
			->setHeaders( array( 'Content-Type' => 'application/xml' ) )
			->post( $xml->asXML() )
			->decodeXml();
	}
	
	/**
	 * Create XML document with authentication credentials
	 *
	 * @param	string	$method	The API method we will be calling
	 * @return	\IPS\Xml\SimpleXML
	 */
	public function createAuthenticationXml( $method )
	{
		$settings = json_decode( $this->method->settings, TRUE );
		$xml = \IPS\Xml\SimpleXML::create( $method, 'AnetApi/xml/v1/schema/AnetApiSchema.xsd' );
		$authentication = $xml->addChild( 'merchantAuthentication' );
		$authentication->addChild( 'name', $settings['login'] );
		$authentication->addChild( 'transactionKey', $settings['tran_key'] );
		
		return $xml;
	}
}
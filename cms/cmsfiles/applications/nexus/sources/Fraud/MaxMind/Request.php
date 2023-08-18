<?php
/**
 * @brief		MaxMind Request
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		07 Mar 2014
 */

namespace IPS\nexus\Fraud\MaxMind;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * MaxMind Request
 */
class _Request
{
	/**
	 * @brief	Maxmind User ID
	 */
	protected ?string $id = NULL;

	/**
	 * @brief	Maxmind License Key
	 */
	protected ?string $licenseKey = NULL;

	/**
	 * @brief	Data that will be posted
	 */
	protected array $data = array();
	
	/**
	 * Constructor
	 *
	 * @param	bool		$session	Set session data (set to FALSE if this is being initiated outside the checkout sequence)
	 * @param	NULL|string	$maxmindKey	MaxMind License Key (NULL to get from settings)
	 * @param	NULL|string	$maxmindId	MaxMind User ID (NULL to get from settings)
	 * @return	void
	 */
	public function __construct( $session=TRUE, $maxmindKey=NULL, $maxmindId=NULL )
	{
		$this->licenseKey	= $maxmindKey ?: \IPS\Settings::i()->maxmind_key;
		$this->id	= $maxmindId ?: \IPS\Settings::i()->maxmind_id;

		if ( $session )
		{
			$this->setIpAddress( \IPS\Request::i()->ipAddress() );
			
			$this->data['sessionID'] = session_id();

			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) and $_SERVER['HTTP_USER_AGENT'] )
			{
				$this->data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			}
			
			if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) and $_SERVER['HTTP_ACCEPT_LANGUAGE'] )
			{
				$this->data['accept_language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
		}
	}
	
	/**
	 * Set IP Address
	 *
	 * @param	string	$ipAddress	IP Address
	 * @return	void
	 */
	public function setIpAddress( $ipAddress )
	{
		$this->data['device']['ip_address'] = $ipAddress;
	}
	
	/**
	 * Set Transaction
	 *
	 * @param	\IPS\nexus\Transaction	$transaction
	 * @return	void
	 */
	public function setTransaction( \IPS\nexus\Transaction $transaction )
	{
		$this->setMember( $transaction->member->member_id ? $transaction->member : $transaction->invoice->member );
		
		if ( $billingAddress = $transaction->invoice->billaddress )
		{
			$this->setBillingAddress( $transaction->invoice->billaddress );
		}
		if ( $shippingAddress = $transaction->invoice->shipaddress )
		{
			$this->setShippingAddress( $transaction->invoice->shipaddress );
		}
		
		$this->data['order']['amount']		= (string) $transaction->amount->amount;
		$this->data['order']['currency']	= $transaction->amount->currency;
	}
	
	/**
	 * Set Billing Address
	 *
	 * @param	\IPS\GeoLocation	$billingAddress
	 * @return	void
	 */
	public function setBillingAddress( \IPS\GeoLocation $billingAddress )
	{
		$this->data['billing']['city']			= $billingAddress->city;
		$this->data['billing']['postal']		= $billingAddress->postalCode;
		$this->data['billing']['country']		= $billingAddress->country;
	}
	
	/**
	 * Set Shipping Address
	 *
	 * @param	\IPS\GeoLocation	$shippingAddress
	 * @return	void
	 */
	public function setShippingAddress( \IPS\GeoLocation $shippingAddress )
	{
		$this->data['shipping']['address']		= implode( ', ', $shippingAddress->addressLines );
		$this->data['shipping']['city']		= $shippingAddress->city;
		$this->data['shipping']['postal']	= $shippingAddress->postalCode;
		$this->data['shipping']['country']	= $shippingAddress->country;
	}
	
	/**
	 * Set Member
	 *
	 * @param	\IPS\Member		$member
	 * @return	void
	 */
	public function setMember( \IPS\Member $member )
	{
		$this->_setEmailProperties( $member->email );
		$this->data['account']['username_md5']	= md5( $member->name );
	}

	/**
	 * @brief	MaxMind common typo domains list
	 * @see		https://github.com/maxmind/minfraud-api-php/blob/b08158b6c096bde8560b1b7fb8bb548c79f8d57b/src/MinFraud/Util.php#L18
	 */
	protected static $typoDomains = [
        // gmail.com
        '35gmai.com' => 'gmail.com',
        '636gmail.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gmail.comu' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmil.com' => 'gmail.com',
        'yahoogmail.com' => 'gmail.com',
        // outlook.com
        'putlook.com' => 'outlook.com',
    ];

	/**
	 * Set the email properties after applying MaxMind's recommended normalization
	 *
	 * @see	https://dev.maxmind.com/normalizing-email-addresses-for-minfraud/
	 * @param	string	$email	The member's email address
	 * @return	void
	 */
	protected function _setEmailProperties( $email )
	{
		$email	= trim( \strtolower( $email ) );
		$local	= \substr( $email, 0, \strrpos( $email, '@' ) );
		$domain	= \substr( $email, \strrpos( $email, '@' ) + 1 );

		/* Trim the domain and remove trailing dots */
		$domain	= rtrim( trim( $domain ), '.' );

		/* Convert IDNs to ASCII */
		if ( !\function_exists('idn_to_ascii') )
		{
			\IPS\IPS::$PSR0Namespaces['TrueBV'] = \IPS\ROOT_PATH . "/system/3rd_party/php-punycode";
			require_once \IPS\ROOT_PATH . "/system/3rd_party/php-punycode/polyfill.php";
		}

		$asciiDomain	= idn_to_ascii( $domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46 );

		if( $asciiDomain !== FALSE )
		{
			$domain	= $asciiDomain;
		}

		/* Then address common typos */
		$domain	= isset( static::$typoDomains[ $domain ] ) ? static::$typoDomains[ $domain ] : $domain;

		/* Now remove email aliases from the local part */
		$divider	= ( $domain === 'yahoo.com' ) ? '-' : '+';

		if( $alias = \strpos( $local, $divider ) )
		{
			$local	= \substr( $local, 0, $alias );
		}

		$this->data['email']['domain']		= $domain;
		$this->data['email']['address']		= md5( $local . '@' . $domain );
	}
	
	/**
	 * Set Phone Number
	 *
	 * @param	string	$phoneNumber
	 * @return	void
	 */
	public function setPhone( $phoneNumber )
	{
		$this->data['billing']['phone_number']	= $phoneNumber;
	}
	
	/**
	 * Set Credit Card
	 *
	 * @param	\IPS\nexus\CreditCard|string	$card	The card number
	 * @return	void
	 */
	public function setCard( $card )
	{
		$cardNumber = ( $card instanceof \IPS\nexus\CreditCard ) ? $card->number : $card;
		$this->data['credit_card']['issuer_id_number'] = mb_substr( $cardNumber, 0, 6 );
		$this->data['credit_card']['last_digits'] = mb_substr( $cardNumber, -4 );
	}
	
	/**
	 * Set Transaction Type
	 *
	 * @param	string	$type		Transaction Type
	 * @return	void
	 */
	public function setTransactionType( $type )
	{
		$this->data['event']['txn_type']		= $type;
	}
	
	/**
	 * Set AVS Result
	 *
	 * @param	string	$code	AVS Code
	 * @return	void
	 */
	public function setAVS( $code )
	{
		$this->data['credit_card']['avs_result'] = $code;
	}
	
	/**
	 * Set CVV Result
	 *
	 * @param	bool	$result	CVV check result (boolean only - do not provide actual code)
	 * @return	void
	 */
	public function setCVV( $result )
	{
		$this->data['credit_card']['cvv_result'] = $result ? 'Y' : 'N';
	}
		
	/**
	 * Make Request
	 *
	 * @return	\IPS\nexus\Fraud\MaxMind\Response
	 * @throws	\IPS\Http\Request\Exception
	 */
	public function request()
	{		
		$response = \IPS\Http\Url::external( 'https://minfraud.maxmind.com/minfraud/v2.0/insights' )->request()->login( $this->id, $this->licenseKey )->post( json_encode( $this->data ) );

		if ( isset( $response->httpHeaders['Content-Type'] ) and preg_match( '/; charset=(.+?);$/', $response->httpHeaders['Content-Type'], $matches ) )
		{
			$response = mb_convert_encoding( $response, 'UTF-8', $matches[1] );
		}

		return new \IPS\nexus\Fraud\MaxMind\Response( (string) $response );
	}
}
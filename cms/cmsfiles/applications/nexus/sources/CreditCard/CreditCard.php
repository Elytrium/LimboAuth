<?php
/**
 * @brief		Credit card object
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Mar 2014
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Credit card object
 */
class _CreditCard
{
	/** 
	 * @brief	Card Number
	 */
	public $number;
	
	/** 
	 * @brief	Last 4 numbers
	 */
	public $lastFour;
	
	/**
	 * @brief	Type
	 */
	const TYPE_VISA = 'visa';
	const TYPE_MASTERCARD = 'mastercard';
	const TYPE_DISCOVER = 'discover';
	const TYPE_AMERICAN_EXPRESS	= 'american_express';
	const TYPE_DINERS_CLUB = 'diners_club';
	const TYPE_JCB = 'jcb';
	const TYPE_PAYPAL = 'paypal';
	const TYPE_VENMO = 'venmo';
	public $type;
	
	/** 
	 * @brief	Expire Month
	 */
	public $expMonth;
	
	/** 
	 * @brief	Expire Year
	 */
	public $expYear;
	
	/** 
	 * @brief	Security Code
	 */
	public $ccv;
	
	/**
	 * @brief	Save
	 */
	public $save;
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct() {}
	
	/**
	 * Create object
	 *
	 * @param	string		$cardNumber	Card Number
	 * @param	int|string	$expMonth	Expire month
	 * @param	int|string	$expYear	Expire year
	 * @param	int|string	$ccv		Security Code
	 * @param	bool		$save		If the card should be saved
	 * @return	void
	 * @throws	\InvalidArgumentException	Card number or expiry date is invalid
	 * @throws	\DomainException			Expire date is in the past
	 */
	public static function build( $cardNumber, $expMonth, $expYear, $ccv, $save=FALSE )
	{
		$obj = new static;
		
		/* Work out card type */ 
		$cardNumber = str_replace( array( ' ', '-' ), '', $cardNumber );
		$obj->number = $cardNumber;
		if ( preg_match( '/^4[0-9]{12}(?:[0-9]{3})?$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_VISA;
		}
		elseif ( preg_match( '/^5[1-5][0-9]{14}$/', $cardNumber ) or /* Maestro */ preg_match( '/^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_MASTERCARD;
		}
		elseif ( preg_match( '/^3[47][0-9]{13}$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_AMERICAN_EXPRESS;
		}
		elseif ( preg_match( '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_DINERS_CLUB;
		}
		elseif ( preg_match( '/^6(?:011|5[0-9]{2})[0-9]{12}$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_DISCOVER;
		}
		elseif ( preg_match( '/^(?:2131|1800|35\d{3})\d{11}$/', $cardNumber ) )
		{
			$obj->type = static::TYPE_JCB;
		}
		else
		{
			throw new \InvalidArgumentException('card_number_invalid');
		}
		$obj->lastFour = mb_substr( $cardNumber, -4 );
		
		/* Check it with the Luhn algorithm */
		$sumTable = array(
			array(0,1,2,3,4,5,6,7,8,9),
			array(0,2,4,6,8,1,3,5,7,9)
		);
		$sum = 0;
		$flip = 0;
		$cardNumberLength = \strlen( $cardNumber );
		for ($i = $cardNumberLength - 1; $i >= 0; $i--)
		{
			$sum += $sumTable[ $flip++ & 0x1 ][ $cardNumber[ $i ] ];
		}
		if( $sum % 10 !== 0 )
		{
			throw new \InvalidArgumentException('card_number_invalid');
		}
		
		/* Check the expiry date */
		$expMonth = \intval( $expMonth );
		if ( $expMonth < 0 or $expMonth > 12 )
		{
			throw new \InvalidArgumentException('card_month_invalid');
		}
		$obj->expMonth = str_pad( $expMonth, 2, '0', STR_PAD_LEFT );
		$obj->expYear = \intval( $expYear );
		if ( mktime( 23, 59, 59, $expMonth + 1, 0, $expYear ) < time() )
		{
			throw new \DomainException;
		}
		
		/* Check the security code */
		$obj->ccv = $ccv;
		if ( $obj->type === static::TYPE_AMERICAN_EXPRESS )
		{
			if ( mb_strlen( $ccv ) !== 4 )
			{
				throw new \InvalidArgumentException('ccv_invalid_4');
			}
		}
		elseif ( mb_strlen( $ccv ) !== 3 )
		{
			throw new \InvalidArgumentException('ccv_invalid_3');
		}
		
		/* Should it be saved? */
		if ( $save )
		{
			$obj->save = $save;
		}
		
		
		return $obj;
	}
}
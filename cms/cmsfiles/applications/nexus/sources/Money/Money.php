<?php
/**
 * @brief		Money Object
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Feb 2014
 */

namespace IPS\nexus;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Money Object
 */
class _Money
{	
	/**
	 * Get available currencies
	 *
	 * @return	array
	 */
	public static function currencies()
	{
		if ( $currencies = json_decode( \IPS\Settings::i()->nexus_currency, TRUE ) )
		{
			return array_keys( $currencies );
		}
		else
		{
			return array( \IPS\Settings::i()->nexus_currency );
		}
	}
	
	/**
	 * Get the number of decimal points used for a currency
	 *
	 * @param	string	$currency	Currency code
	 * @return	int
	 */
	public static function numberOfDecimalsForCurrency( $currency )
	{
		switch ( $currency )
		{
			case 'CLF':
				return 4;
				
			case 'BHD':
			case 'IQD':
			case 'JOD':
			case 'KWD':
			case 'LYD':
			case 'OMR':
			case 'TND':
				return 3;
				
			case 'MGA':
			case 'MRO':
				return 1;
				
			case 'BIF':
			case 'BYR':
			case 'CLP':
			case 'CVE':
			case 'DJF':
			case 'GNF':
			case 'ISK':
			case 'JPY':
			case 'KMF':
			case 'KRW':
			case 'PYG':
			case 'RWF':
			case 'UGX':
			case 'UYI':
			case 'VND':
			case 'VUV':
			case 'XAF':
			case 'XOF':
			case 'XPF':
				return 0;
			
			default:
				return 2;
		}
	}
		
	/**
	 * @brief	Amount
	 */
	public $amount;
	
	/**
	 * @brief	Currency
	 */
	public $currency;
	
	/**
	 * @brief	Number of decimal points
	 */
	protected $numberOfDecimalPlaces = 2;
	
	/**
	 * Contructor
	 *
	 * @param	mixed	$amount		Amount
	 * @param	string	$currency	Currency code
	 * @return	void
	 */
	public function __construct( $amount, $currency )
	{
		$this->currency = $currency;
		$this->numberOfDecimalPlaces = static::numberOfDecimalsForCurrency( $currency );
		
		if ( !( $amount instanceof \IPS\Math\Number ) )
		{
			if ( \is_string( $amount ) )
			{
				$amount = \floatval( $amount );
			}
			$amount = new \IPS\Math\Number( number_format( $amount, $this->numberOfDecimalPlaces, '.', '' ) );
		}
		
		$this->amount = $amount->round( $this->numberOfDecimalPlaces );
	}
		
	/**
	 * Amount as string (not formatted, not locale aware)
	 * Used for gateways where, for example, (float) 10.5 is not acceptable, and (string) "10.50" is required
	 *
	 * @return	\IPS\nexus\Money
	 */
	public function amountAsString()
	{
		return sprintf( "%01." . $this->numberOfDecimalPlaces . "F", (string) $this->amount );
	}
	
	/**
	 * To string
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->toString( \IPS\Member::loggedIn()->language() );
	}
	
	/**
	 * To string for language
	 *
	 * @param	\IPS\Lang	$language	The language to use
	 * @return	string
	 */
	public function toString( \IPS\Lang $language )
	{
		/* If intl is installed, use NumberFormatter */
		if( class_exists( "NumberFormatter" ) and trim( $language->locale['int_curr_symbol'] ) === $this->currency )
		{
			$formatter = new \NumberFormatter( $language->short, \NumberFormatter::CURRENCY );
			
			if ( \floatval( (string) $this->amount ) == \intval( (string) $this->amount ) )
			{
				$formatter->setAttribute( \NumberFormatter::FRACTION_DIGITS, 0 );
			}

			$result = $formatter->formatCurrency( (string) $this->amount, $this->currency );

			/* @see https://bugs.php.net/bug.php?id=54538 */
			if( $result !== 'NaN' )
			{
				return $result;
			}
		}

		/* If this currency matches the locale the user is using, and we are not using PHP 7.4, AND money_format is supported (Windows doesn't support it), use that */
		if ( \function_exists( 'money_format' ) and version_compare( PHP_VERSION, '7.4.0', '<' ) and trim( $language->locale['int_curr_symbol'] ) === $this->currency )
		{
			/* We need to make sure the locale is correct */
			$currentLocale = setlocale( LC_ALL, '0' );
			$language->setLocale();
			
			/* Get the value to use */
			$return = money_format( '%n', (string) $this->amount );
			
			/* Then put the locale back */
			\IPS\Lang::restoreLocale( $currentLocale );
			
			/* And return */
			return $return;
		}
				
		/* If it matches any of the installed languages, we can do something with the locale data */
		foreach ( \IPS\Lang::languages() as $lang )
		{
			if ( isset( $lang->locale['int_curr_symbol'] ) AND trim( $lang->locale['int_curr_symbol'] ) === $this->currency )
			{
				$currencySymbol = $lang->convertString( $lang->locale['currency_symbol'] );
				$currencySymbolPreceeds = $this->amount->isPositive() ? $lang->locale['p_cs_precedes'] : $lang->locale['n_cs_precedes'];
				$spaceBetweenCurrencySymbolAndAmount = ( $this->amount->isPositive() ? $lang->locale['p_sep_by_space'] : $lang->locale['n_sep_by_space'] ) ? ' ' : '';
				$amount = number_format( (string) $this->amount, $lang->locale['frac_digits'], $lang->locale['mon_decimal_point'], $lang->locale['mon_thousands_sep'] );
				
				$positiveNegativeSymbol = $this->amount->isPositive() ? $lang->locale['positive_sign'] : $lang->locale['negative_sign'];
				if ( $positiveNegativeSymbol )
				{
					$positiveNegativeSymbolFormat = $this->amount->isPositive() ? $lang->locale['p_sign_posn'] : $lang->locale['n_sign_posn'];
					$positiveNegativeSymbolFormatLength = \strlen( $positiveNegativeSymbolFormat );
					for ( $i=0; $i < $positiveNegativeSymbolFormatLength; $i++ )
					{
						switch ( $positiveNegativeSymbolFormat[ $i ] )
						{
							case 0:
								if ( $currencySymbolPreceeds )
								{
									$currencySymbol = "({$currencySymbol}";
									$amount = "{$amount})";
								}
								else
								{
									$currencySymbol = "{$currencySymbol})";
									$amount = "({$amount}";
								}
								break;
							case 1:
								if ( $currencySymbolPreceeds )
								{
									$currencySymbol = "{$positiveNegativeSymbol}{$currencySymbol}";
								}
								else
								{
									$amount = "{$positiveNegativeSymbol}{$amount}";
								}
								break;
							case 2:
								if ( $currencySymbolPreceeds )
								{
									$amount = "{$amount}{$positiveNegativeSymbol}";
								}
								else
								{
									$currencySymbol = "{$currencySymbol}{$positiveNegativeSymbol}";
								}
								break;
							case 3:
								$currencySymbol = ( $positiveNegativeSymbol . $currencySymbol );
								break;
							case 4:
								$currencySymbol = ( $currencySymbol . $positiveNegativeSymbol );
								break;
						}
					}
				}
				
				return ( $currencySymbolPreceeds ? ( $currencySymbol . $spaceBetweenCurrencySymbolAndAmount . $amount ) : ( $amount . $spaceBetweenCurrencySymbolAndAmount . $currencySymbol ) );
			}
		}

		/* And if all else fails, just use the currency code */
		return number_format( (string) $this->amount, static::numberOfDecimalsForCurrency( $this->currency ) ) . " {$this->currency}";
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	string	currency	The currency code (e.g. 'USD')
	 * @apiresponse	string	amount		The amount
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'currency'	=> $this->currency,
			'amount'	=> $this->amountAsString(),
		);
	}
}
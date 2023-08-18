<?php
/**
 * @brief		Address input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Jul 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Address input class for Form Builder
 */
class _Address extends FormAbstract
{	
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
			'minimize'			=> FALSE,			// Minimize the address field until the user focuses?
			'requireFullAddress'=> TRUE,			// Does this have to be a full address? Default is TRUE, may set to FALSE if a more generic location is acceptable
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'minimize' 				=> FALSE,
		'requireFullAddress'	=> TRUE,
		'preselectCountry'	=> TRUE,
	);

	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		/* If we don't have a value, set their country based on the HTTP headers */
		if ( !$this->value OR ( $this->value instanceof \IPS\GeoLocation AND !$this->value->country ) )
		{
			$this->value = ( $this->value instanceof \IPS\GeoLocation ) ? $this->value : new \IPS\GeoLocation;
			if ( $this->options['preselectCountry'] and $defaultCountry = static::calculateDefaultCountry() )
			{
				$this->value->country = $defaultCountry;
			}
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->address( $this->name, $this->value, \IPS\Settings::i()->googleplacesautocomplete ? \IPS\Settings::i()->google_maps_api_key : NULL, $this->options['minimize'], $this->options['requireFullAddress'] );
	}
	
	/**
	 * Calculate default country
	 *
	 * @return	string|NULL
	 */
	public static function calculateDefaultCountry()
	{		
		if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
		{
			if( mb_strpos( $_SERVER['HTTP_ACCEPT_LANGUAGE'], ',' ) )
			{
				$languages	= explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
			}
			else
			{
				$languages	= array( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );
			}

			foreach( $languages as $language )
			{
				/* Remove quotient if it exists */
				if( mb_strpos( $language, ';' ) !== FALSE )
				{
					$language = mb_substr( $language, 0, mb_strpos( $language, ';' ) );
				}

				$dashPos = mb_strpos( $language, '-' );
				$country = mb_strtoupper( $dashPos ? mb_substr( $language, $dashPos + 1 ) : $language );
				if ( \in_array( $country, \IPS\GeoLocation::$countries ) )
				{					
					return $country;
				}
			}
		}
		
		return NULL;
	}

	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		/* Create the object */
		$input = parent::getValue();
		$value = new \IPS\GeoLocation;
		$value->addressLines = ( isset( $input['address'] ) and \is_array( $input['address'] ) ) ? array_filter( $input['address'] ) : array();
		if ( empty( $value->addressLines ) )
		{
			$value->addressLines = array( NULL );
		}

		if( isset( $input['city'] ) )
		{
			$value->city = $input['city'];
		}

		if( isset( $input['region'] ) )
		{
			$value->region = $input['region'];
		}

		if( isset( $input['postalCode'] ) )
		{
			$value->postalCode = $input['postalCode'];
		}

		if( isset( $input['country'] ) )
		{
			$value->country = $input['country'];
		}
		
		/* Work out what parts are filled in */
		$partiallyCompleted = FALSE;
		$fullyCompleted = TRUE;
		$addresslines = array_filter( $value->addressLines );
		if ( empty( $addresslines ) )
		{
			$fullyCompleted = FALSE;
		}
		else
		{
			$partiallyCompleted = TRUE;
		}
		if ( $value->city )
		{
			$partiallyCompleted = TRUE;
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( $value->postalCode )
		{
			$partiallyCompleted = TRUE;
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( $value->country )
		{
			if ( $value->country != static::calculateDefaultCountry() )
			{
				$partiallyCompleted = TRUE;
			}
			
			if ( array_key_exists( $value->country, \IPS\GeoLocation::$states ) )
			{
				if ( !$value->region )
				{
					$fullyCompleted = FALSE;
				}
			}
		}
		else
		{
			$fullyCompleted = FALSE;
		}
		if ( trim( $value->region ) )
		{
			$states = ( isset( \IPS\GeoLocation::$states[ $value->country ] ) ) ? \IPS\GeoLocation::$states[ $value->country ] : array();
			if ( !array_key_exists( $value->country, \IPS\GeoLocation::$states ) or $value->region != array_shift( $states ) )
			{
				$partiallyCompleted = TRUE;
			}
		}
		
		/* Validate, return NULL if we have nothing */
		if ( !$fullyCompleted and $this->options['requireFullAddress'] )
		{
			if ( $partiallyCompleted )
			{
				if ( $this->required )
				{
					throw new \InvalidArgumentException('form_partial_address_req');
				}
				else
				{
					throw new \InvalidArgumentException('form_partial_address_opt');
				}
			}
			else
			{
				return NULL;
			}
		}
		elseif( !$fullyCompleted )
		{
			/* If we don't have any country, region city AND postal code, return NULL, otherwise everywhere ends up storing empty
				location arrays which makes it difficult to confirm if a location was actually set or not */
			if( !$value->country AND !$value->region AND !$value->city AND !$value->postalCode )
			{
				return NULL;
			}
		}

		/* Add in latitude and longitude if we can */
		try
		{
			$value->getLatLong();
		}
		catch( \BadFunctionCallException $e ){}
		
		/* Return */
		return $value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if( $this->value === NULL and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}
		
		return parent::validate();
	}
		
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		return json_encode( $value );
	}
}
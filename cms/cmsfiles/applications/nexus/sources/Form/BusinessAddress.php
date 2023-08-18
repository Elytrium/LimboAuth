<?php
/**
 * @brief		Consumer/business address input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		8 Oct 2019
 */

namespace IPS\nexus\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Consumer/business address input class for Form Builder
 */
class _BusinessAddress extends \IPS\Helpers\Form\Address
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
			'minimize'			=> FALSE,			// Minimize the address field until the user focuses?
			'requireFullAddress'=> TRUE,				// Does this have to be a full address? Default is TRUE, may set to FALSE if a more generic location is acceptable
			'vat'				=> FALSE,			// In addition if asking if this is a business address, should we prompt for the VAT number?
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'minimize' 				=> FALSE,
		'requireFullAddress'	=> TRUE,
		'vat'					=> FALSE
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_forms.js', 'nexus', 'global' ) );

		/* If we don't have a value, set their country based on the HTTP headers */
		if ( !$this->value OR ( $this->value instanceof \IPS\GeoLocation AND !$this->value->country ) )
		{
			$this->value = ( $this->value instanceof \IPS\GeoLocation ) ? $this->value : new \IPS\GeoLocation;
			if ( $defaultCountry = static::calculateDefaultCountry() )
			{
				$this->value->country = $defaultCountry;
			}
		}
		
		/* We need a HTML id */
		if ( !$this->htmlId )
		{
			$this->htmlId = md5( 'ips_checkbox_' . $this->name );
		}
		
		/* Display */
		return \IPS\Theme::i()->getTemplate( 'forms', 'nexus', 'global' )->businessAddress( $this->name, $this->value, \IPS\Settings::i()->googleplacesautocomplete ? \IPS\Settings::i()->google_maps_api_key : NULL, $this->options['minimize'], $this->options['requireFullAddress'], $this->htmlId, $this->options['vat'] );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		/* Get the normal address stuff */
		$value = parent::getValue();
		
		/* Add in business stuff */
		if ( $value )
		{
			$name = $this->name;
			if( \IPS\Request::i()->$name['type'] === 'business' )
			{
				$value->business = \IPS\Request::i()->$name['business'];
				
				if ( $this->options['vat'] and \in_array( $value->country, array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','GB') ) and \IPS\Request::i()->$name['vat'] )
				{
					$value->vat = \IPS\Request::i()->$name['vat'];
				}
				else
				{
					$value->vat = NULL;
				}
			}
			else
			{
				$value->business = NULL;
				$value->vat = NULL;
			}
		}
		
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
		parent::validate();
		
		if( $this->value )
		{
			if ( $this->value->business === '' )
			{
				throw new \DomainException('cm_business_name_required');
			}
			
			if ( $this->value->vat )
			{
				try
				{
					$response = \IPS\nexus\Tax::validateVAT( $this->value->vat );
					if ( !$response )
					{
						throw new \DomainException('cm_checkout_vat_invalid');
					}
					elseif ( $response['countryCode'] !== $this->value->country )
					{
						throw new \DomainException('cm_checkout_vat_wrong_country');
					}
				}
				catch ( \IPS\Http\Request\Exception $e )
				{
					\IPS\Log::log( $e, 'vat-validation' );
					throw new \DomainException('cm_checkout_vat_error');
				}
			}
		}
		
		return TRUE;
	}
}
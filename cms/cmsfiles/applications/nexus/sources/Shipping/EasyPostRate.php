<?php
/**
 * @brief		EasyPost Shipping Rate
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Jun 2014
 */

namespace IPS\nexus\Shipping;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * EasyPost Shipping Rate
 */
class _EasyPostRate implements \IPS\nexus\Shipping\Rate
{
	/**
	 * @brief	Data
	 */
	protected $data = array();
	
	/**
	 * Constuctor
	 *
	 * @param	array	$data	Date from EasyPost
	 */
	public function __construct( $data )
	{
		$this->data = $data;
	}
	
	/**
	 * Is available?
	 *
	 * @param	\IPS\GeoLocation		$destination	Desired destination
	 * @param	array					$items			Items
	 * @param	string					$currency		Desired currency
	 * @param	\IPS\nexus\Invoice|NULL	$invoice		The invoice
	 * @return	bool
	 */
	public function isAvailable( \IPS\GeoLocation $destination, array $items, $currency, \IPS\nexus\Invoice $invoice = NULL )
	{
		return TRUE;
	}
	
	/**
	 * Name
	 *
	 * @return	string
	 */
	public function getName()
	{
		$service = $this->data['carrier'];
		for ( $i=0; $i<\strlen( $this->data['service'] ); $i++ )
		{
			if ( \strtoupper( $this->data['service'][ $i ] ) === $this->data['service'][ $i ] )
			{
				$service .= " {$this->data['service'][ $i ]}";
			}
			else
			{
				$service .= $this->data['service'][ $i ];
			}
		}
		
		return $service;
	}
	
	/**
	 * Price
	 *
	 * @param	array					$items		Items
	 * @param	string					$currency	Desired currency
	 * @param	\IPS\nexus\Invoice|NULL	$invoice	The invoice
	 * @return	\IPS\nexus\Money
	 */
	public function getPrice( array $items, $currency, \IPS\nexus\Invoice $invoice = NULL )
	{
		$adjustment = 0;
		if ( \IPS\Settings::i()->easypost_price_adjustment and $adjustments = json_decode( \IPS\Settings::i()->easypost_price_adjustment, TRUE ) and isset( $adjustments[ $currency ] ) )
		{
			$adjustment = $adjustments[ $currency ]['amount'];
		}
		
		return new \IPS\nexus\Money( $this->data['rate'] + $adjustment, $this->data['currency'] );
	}
	
	/**
	 * Tax
	 *
	 * @return	\IPS\nexus\Tax|NULL
	 */
	public function getTax()
	{
		try
		{
			return \IPS\Settings::i()->easypost_tax ? \IPS\nexus\Tax::load( \IPS\Settings::i()->easypost_tax ) : NULL;
		}
		catch ( \OutOfRangeException $e ) {}
		{
			return NULL;
		}
		
	}
	
	/**
	 * Estimated delivery date
	 *
	 * @param	array					$items		Items
	 * @param	\IPS\nexus\Invoice|NULL	$invoice	The invoice
	 * @return	string
	 */
	public function getEstimatedDelivery( array $items, \IPS\nexus\Invoice $invoice = NULL )
	{
		if ( $this->data['est_delivery_days'] )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'easypost_delivery_estimate', FALSE, array( 'pluralize' => array( $this->data['est_delivery_days'] + \IPS\Settings::i()->easypost_delivery_adjustment ) ) );
		}
		return NULL;
	}
	
	/**
	 * API
	 *
	 * @param	float				$lengthInInches	Parcel length
	 * @param	float				$widthInInches	Parcel width
	 * @param	float				$heightInInches	Parcel height
	 * @param	float				$weightInOz		Parcel weight
	 * @param	\IPS\nexus\Customer	$toCustomer		Customer to ship to
	 * @param	\IPS\Geolocation	$toAddress		Address to ship to
	 * @param	string				$currency		Desired currency for rates
	 * @return	array
	 * @throws	\IPS\Http\Request\Exception
	 */
	public static function getRates( $lengthInInches, $widthInInches, $heightInInches, $weightInOz, \IPS\nexus\Customer $toCustomer, \IPS\Geolocation $toAddress, $currency )
	{
		$fromAddress = \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->easypost_address ?: \IPS\Settings::i()->site_address );
		
		return \IPS\Http\Url::external( 'https://api.easypost.com/v2/shipments' )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->post( array( 'shipment' => array(
			'to_address'	=> array(
				'street1'	=> array_shift( $toAddress->addressLines ),
				'street2'	=> \count( $toAddress->addressLines ) ? implode( ', ', $toAddress->addressLines ) : NULL,
				'city'		=> $toAddress->city,
				'state'		=> $toAddress->region,
				'zip'		=> $toAddress->postalCode,
				'country'	=> $toAddress->country,
				'name'		=> $toCustomer->cm_first_name . ' ' . $toCustomer->cm_last_name,
				'phone'		=> $toCustomer->cm_phone,
				'email'		=> $toCustomer->email
			),
			'from_address'	=> array(
				'street1'	=> array_shift( $fromAddress->addressLines ),
				'street2'	=> \count( $fromAddress->addressLines ) ? implode( ', ', $fromAddress->addressLines ) : NULL,
				'city'		=> $fromAddress->city,
				'state'		=> $fromAddress->region,
				'zip'		=> $fromAddress->postalCode,
				'country'	=> $fromAddress->country,
				'company'	=> \IPS\Settings::i()->board_name,
				'phone'		=> \IPS\Settings::i()->easypost_phone,
				'email'		=> \IPS\Settings::i()->email_in
			),
			'parcel'		=> array(
				'length'	=> round( $lengthInInches, 1 ),
				'width'		=> round( $widthInInches, 1 ),
				'height'	=> round( $heightInInches, 1 ),
				'weight'	=> round( $weightInOz, 1 )
			),
			'options'		=> array(
				'currency'		=> $currency
			)
		) ) )->decodeJson();
	}
}
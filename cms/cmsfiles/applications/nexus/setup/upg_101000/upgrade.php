<?php
/**
 * @brief		4.0.99 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		23 Jul 2015
 */

namespace IPS\nexus\setup\upg_101000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.99 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Store certain informaiton in settings to ease performance on menu
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Settings::i()->changeValues( array(
			'card_storage_gateways'	=> \count( \IPS\nexus\Gateway::cardStorageGateways() ),
			'donation_goals'		=> \count( \IPS\nexus\Donation\Goal::roots() ),
			'customer_fields'		=> \count( \IPS\nexus\Customer\CustomField::roots() )
		) );
		return TRUE;
	}
	
	/**
	 * Fix shipping methods
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( !isset( \IPS\Settings::i()->nexus_currency ) )
		{
			return TRUE;
		}
		
		$statesFrom3 = array(
			//'GB' => array( "LONDON" => 'London', "ESSEX" => 'Essex' ),
			'CA' => array(
				"ALBERTA" => "Alberta",
				"BRITISH COLUMBIA" => "British Columbia",
				"MANITOBA" => "Manitoba",
				"NEW BRUNSWICK" => "New Brunswick",
				"NEWFOUNDLAND AND LABRADOR" => "Newfoundland and Labrador",
				"NORTHWEST TERRITORIES" => "Northwest Territories",
				"NOVA SCOTIA" => "Nova Soctia",
				"NUNAVUT" => "Nunavut",
				"ONTARIO" => "Ontario",
				"PRINCE EDWARD ISLAND" => "Prince Edward Island",
				"QUEBEC" => "Quebec",
				"SASKATCHEWAN" => "Saskatchewan",
				"YUKON" => "Yukon",
				),
			'US' => array(
				'ALABAMA' => "AL",
				'ALASKA' => "AK",
				'AMERICAN SAMOA' => "AS",
				'ARIZONA' => "AZ",
				'ARKANSAS' => "AR",
				'CALIFORNIA' => "CA",
				'COLORADO' => "CO",
				'CONNECTICUT' => "CT",
				'DELAWARE' => "DE",
				'DISTRICT OF COLUMBIA' => "DC",
				'FEDERATED STATES OF MICRONESIA' => "FM",
				'FLORIDA' => "FL",
				'GEORGIA' => "GA",
				'GUAM' => "GU",
				'HAWAII' => "HI",
				'IDAHO' => "ID",
				'ILLINOIS' => "IL",
				'INDIANA' => "IN",
				'IOWA' => "IA",
				'KANSAS' => "KS",
				'KENTUCKY' => "KY",
				'LOUISIANA' => "LA",
				'MAINE' => "ME",
				'MARSHALL ISLANDS' => "MH",
				'MARYLAND' => "MD",
				'MASSACHUSETTS' => "MA",
				'MICHIGAN' => "MI",
				'MINNESOTA' => "MN",
				'MISSISSIPPI' => "MS",
				'MISSOURI' => "MO",
				'MONTANA' => "MT",
				'NEBRASKA' => "NE",
				'NEVADA' => "NV",
				'NEW HAMPSHIRE' => "NH",
				'NEW JERSEY' => "NJ",
				'NEW MEXICO' => "NM",
				'NEW YORK' => "NY",
				'NORTH CAROLINA' => "NC",
				'NORTH DAKOTA' => "ND",
				'NORTHERN MARIANA ISLANDS' => "MP",
				'OHIO' => "OH",
				'OKLAHOMA' => "OK",
				'OREGON' => "OR",
				'PALAU' => "PW",
				'PENNSYLVANIA' => "PA",
				'PUERTO RICO' => "PR",
				'RHODE ISLAND' => "RI",
				'SOUTH CAROLINA' => "SC",
				'SOUTH DAKOTA' => "SD",
				'TENNESSEE' => "TN",
				'TEXAS' => "TX",
				'UTAH' => "UT",
				'VERMONT' => "VT",
				'VIRGIN ISLANDS' => "VI",
				'VIRGINIA' => "VA",
				'WASHINGTON' => "WA",
				'WEST VIRGINIA' => "WV",
				'WISCONSIN' => "WI",
				'WYOMING' => "WY",
				'ARMED FORCES - AMERICAS' => "AA",
				'ARMED FORCES - EUROPE' => "AE",
				'ARMED FORCES - PACIFIC' => "AP",
				),
			'PT' => array(
				'AVEIRO' => "Aveiro",
				'AZORES' => "Azores",
				'BEJA' => "Beja",
				'BRAGA' => "Braga",
				'BRAGANCA' => "Braganca",
				'CASTELO BRANCO' => "Castelo Branco",
				'COIMBRA' => "Coimbra",
				'EVORA' => "Evora",
				'FARO' => "Faro",
				'GUARDA' => "Guarda",
				'LEIRIA' => "Leiria",
				'LISBOA' => "Lisboa",
				'MADEIRA ISLANDS' => "Madeira Islands",
				'PORTALEGRE' => "Portalegre",
				'PORTO' => "Porto",
				'SANTAREM' => "Santarem",
				'SETUBAL' => "Setubal",
				'VIANA DO CASTELO' => "Viana do Castelo",
				'VILA REAL' => "Vila Real",
				'VISEU' => "Viseu",
				),
			'AU' => array(
				'AUSTRALIAN CAPITAL TERRITORY' => 'ACT',
				'NEW SOUTH WALES' => 'NSW',
				'NORTHERN TERRITORY' => 'NT',
				'QUEENSLAND' => 'QLD',
				'SOUTH AUSTRALIA' => 'SA',
				'TASMANIA' => 'TAS',
				'VICTORIA' => 'VIC',
				'WESTERN AUSTRALIA' => 'WA',
				),
			);
			
		$offset = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;
		$select = \IPS\Db::i()->select( '*', 'nexus_shipping', NULL, 's_id', array( $offset, 100 ) );
		if ( \count( $select ) )
		{		
			foreach ( $select as $row )
			{
				$update = array();
				
				$decoded = json_decode( $row['s_rates'], TRUE );
				if( !empty( $decoded ) )
				{
					foreach ( $decoded as $k => $value )
					{
						if ( !isset( $value['min'] ) )
						{
							switch( $row['s_type'] )
							{
								// Number of items
								default:
								case 'q':
									$min = (int) $value['value'];
									break;
								// Invoice subtotal
								case 't':
									$min = array( \IPS\Settings::i()->nexus_currency => \floatval( $value['value'] ) );
									break;
								// Total weight of items
								case 'w':
									$min = \floatval( $value['value'] );
									break;
							}

							$decoded[ $k ] = array(
								'min'	=> $min,
								'max'	=> isset( $decoded[ $k + 1 ] ) ? array( \IPS\Settings::i()->nexus_currency => \floatval( $decoded[ $k + 1 ]['value'] ) ) : '*',
								'price'	=> array( \IPS\Settings::i()->nexus_currency => \floatval( $value['cost'] ) )
							);
						}
					}
					if ( json_encode( $decoded ) != $row['s_rates'] )
					{
						$update['s_rates'] = json_encode( $decoded );
					}
				}
				
				if ( $decoded = @\unserialize( $row['s_locations'] ) )
				{
					foreach ( $decoded as $countryCode => $locations )
					{
						/* 3.x treated a null value for locations as '*'. 4.x is much more strict over allowed values */
						if ( !$locations )
						{
							$locations = $decoded[ $countryCode ] = '*';
						}

						if ( $locations != '*' and isset( $statesFrom3[ $countryCode ] ) and isset( \IPS\GeoLocation::$states[ $countryCode ] ) )
						{
							foreach ( $locations as $i => $location )
							{
								if ( !\in_array( $location, \IPS\GeoLocation::$states[ $countryCode ] ) )
								{
									if ( $key = array_search( $location, $statesFrom3[ $countryCode ] ) )
									{
										if ( $key = array_search( mb_strtolower( $key ), array_map( 'mb_strtolower', \IPS\GeoLocation::$states[ $countryCode ] ) ) )
										{
											$decoded[ $countryCode ][ $i ] = \IPS\GeoLocation::$states[ $countryCode ][ $key ];
										}
									}
								}
							}
						}
					}
					$update['s_locations'] = json_encode( $decoded );
				}
				
				if ( !empty( $update ) )
				{
					\IPS\Db::i()->update( 'nexus_shipping', $update, array( 's_id=?', $row['s_id'] ) );
				}
			}
			
			return $offset + 100;
		}
		else
		{
			unset( $_SESSION['_step16Count'] );
			return TRUE;
		}
	}
}
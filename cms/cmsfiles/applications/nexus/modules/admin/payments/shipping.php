<?php
/**
 * @brief		Shipments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		20 Mar 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Shipments
 */
class _shipping extends \IPS\Dispatcher\Controller
{	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'shiporders_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'payments', 'shipmethods_manage' ) )
		{
			\IPS\Output::i()->sidebar['actions']['settings'] = array(
				'title'		=> 'shipping_rates',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal('app=nexus&module=payments&controller=shippingrates'),
			);
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('shipping');
		\IPS\Output::i()->output	= (string) \IPS\nexus\Shipping\Order::table( \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=shipping' ) );
	}
	
	/**
	 * View
	 *
	 * @return	void
	 */
	public function view()
	{
		/* Load */
		try
		{
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X193/1', 404, '' );
		}
		
		$easyPost = NULL;
		if ( \IPS\Settings::i()->easypost_api_key )
		{
			$extra = json_decode( $shipment->extra, TRUE );
			if ( isset( $extra['shipment'] ) )
			{
				try
				{
					$easyPost = \IPS\Http\Url::external( 'https://api.easypost.com/v2/shipments/' . $extra['shipment'] )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->get()->decodeJson();
				}
				catch ( \IPS\Http\Request\Exception $e ) { }
			}
		}
				
		/* Add Buttons  */
		\IPS\Output::i()->sidebar['actions'] = $shipment->buttons( 'v' );
		
		/* Output */
		\IPS\Output::i()->title = $shipment->method ? \IPS\Member::loggedIn()->language()->addToStack( 'shipment_number_with_method', FALSE, array( 'sprintf' => array( $shipment->id, $shipment->method->_title ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'shipment_number', FALSE, array( 'sprintf' => array( $shipment->id ) ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'shiporders' )->view( $shipment, $easyPost );
	}
	
	/**
	 * Ship
	 *
	 * @return	void
	 */
	public function ship()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'shiporders_edit' );
		
		/* Load */
		try
		{
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X193/2', 404, '' );
		}
						
		/* Build Form */
		$form = new \IPS\Helpers\Form( 'form', 'shipment_ship' );
		if ( \IPS\Settings::i()->easypost_api_key )
		{
			/* Calculate stuff */
			$weightInOz = 0;
			$lengthInInches = 0;
			$widthInInches = 0;
			$heightInInches = 0;
			foreach ( $shipment->items as $item )
			{
				$weight = new \IPS\nexus\Shipping\Weight( $item['weight']['kilograms'] );
				$weightInOz += ( $weight->float('oz') * $item['quantity'] );
				
				foreach ( array( 'length', 'width', 'height' ) as $k )
				{
					$dim = new \IPS\nexus\Shipping\Length( $item[ $k ]['metres'] );
					$v = "{$k}InInches";
					$$v += ( $dim->float('in') * $item['quantity'] );
				}
			}
			
			/* Method */
			$form->add( new \IPS\Helpers\Form\Radio( 'shipment_ship_method', 'easypost', TRUE, array(
				'options'	=> array( 'easypost' => 'shipment_ship_easypost', 'manual' => 'shipment_ship_manual' ),
				'toggles'	=> array(
					'easypost'	=> array( 'form_header_easypost_package_header', 'easypost_package', 'easypost_package_weight', 'form_header_easypost_customs_header', 'easypost_customs_details' ),
					'manual'	=> array( 'o_tracknumber', 'tracking_url' )
				)
			) ) );
			
			/* Parcel */
			$form->addHeader( 'easypost_package_header' );
			$form->add( new \IPS\Helpers\Form\Select( 'easypost_package', 'custom', NULL, array(
				'options' => array(
					'custom'	=> 'easypost_package_custom',
					'easypost_package_usps'	=> array(
						'Card'						=> 'easypost_package_usps_Card',
						'Letter'					=> 'easypost_package_usps_Letter',
						'Flat'						=> 'easypost_package_usps_Flat',
						'Parcel'					=> 'easypost_package_usps_Parcel',
						'LargeParcel'				=> 'easypost_package_usps_LargeParcel',
						'IrregularParcel'			=> 'easypost_package_usps_IrregularParcel',
						'FlatRateEnvelope'			=> 'easypost_package_usps_FlatRateEnvelope',
						'FlatRateLegalEnvelope'		=> 'easypost_package_usps_FlatRateLegalEnvelope',
						'FlatRatePaddedEnvelope'	=> 'easypost_package_usps_FlatRatePaddedEnvelope',
						'FlatRateGiftCardEnvelope'	=> 'easypost_package_usps_FlatRateGiftCardEnvelope',
						'FlatRateWindowEnvelope'	=> 'easypost_package_usps_FlatRateWindowEnvelope',
						'FlatRateCardboardEnvelope'	=> 'easypost_package_usps_FlatRateCardboardEnvelope',
						'SmallFlatRateEnvelope'		=> 'easypost_package_usps_SmallFlatRateEnvelope',
						'SmallFlatRateBox'			=> 'easypost_package_usps_SmallFlatRateBox',
						'MediumFlatRateBox'			=> 'easypost_package_usps_MediumFlatRateBox',
						'LargeFlatRateBox'			=> 'easypost_package_usps_LargeFlatRateBox',
						'RegionalRateBoxA'			=> 'easypost_package_usps_RegionalRateBoxA',
						'RegionalRateBoxB'			=> 'easypost_package_usps_RegionalRateBoxB',
						'RegionalRateBoxC'			=> 'easypost_package_usps_RegionalRateBoxC',
						'LargeFlatRateBoardGameBox'	=> 'easypost_package_usps_LargeFlatRateBoardGameBox',
					),
					'easypost_package_ups'	=> array(
						'UPSLetter'					=> 'easypost_package_ups_UPSLetter',
						'UPSExpressBox'				=> 'easypost_package_ups_UPSExpressBox',
						'UPS25kgBox'				=> 'easypost_package_ups_UPS25kgBox',
						'UPS10kgBox'				=> 'easypost_package_ups_UPS10kgBox',
						'Tube'						=> 'easypost_package_ups_Tube',
						'Pak'						=> 'easypost_package_ups_Pak',
						'Pallet'					=> 'easypost_package_ups_Pallet',
						'SmallExpressBox'			=> 'easypost_package_ups_SmallExpressBox',
						'MediumExpressBox'			=> 'easypost_package_ups_MediumExpressBox',
						'LargeExpressBox'			=> 'easypost_package_ups_LargeExpressBox',
					),
					'easypost_package_fedex' => array(
						'FedExEnvelope'				=> 'easypost_package_fedex_FedExEnvelope',
						'FedExBox'					=> 'easypost_package_fedex_FedExBox',
						'FedExPak'					=> 'easypost_package_fedex_FedExPak',
						'FedExTube'					=> 'easypost_package_fedex_FedExTube',
						'FedEx10kgBox'				=> 'easypost_package_fedex_FedEx10kgBox',
						'FedEx25kgBox'				=> 'easypost_package_fedex_FedEx25kgBox',
					),
				),
				'toggles'	=> array(
					'custom'	=> array( 'easypost_package_length', 'easypost_package_width', 'easypost_package_height' )
				)
			), NULL, NULL, NULL, 'easypost_package' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'easypost_package_length', $lengthInInches, NULL, array(), function( $val )
			{
				if ( !$val and \IPS\Request::i()->shipment_ship_method == 'easypost' and \IPS\Request::i()->easypost_package == 'custom' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, \IPS\Member::loggedIn()->language()->addToStack('inches'), 'easypost_package_length' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'easypost_package_width', $widthInInches, NULL, array(), function( $val )
			{
				if ( !$val and \IPS\Request::i()->shipment_ship_method == 'easypost' and \IPS\Request::i()->easypost_package == 'custom' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, \IPS\Member::loggedIn()->language()->addToStack('inches'), 'easypost_package_width' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'easypost_package_height', $heightInInches, NULL, array(), function( $val )
			{
				if ( !$val and \IPS\Request::i()->shipment_ship_method == 'easypost' and \IPS\Request::i()->easypost_package == 'custom' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, \IPS\Member::loggedIn()->language()->addToStack('inches'), 'easypost_package_height' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'easypost_package_weight', $weightInOz, NULL, array( 'decimals' => 1 ), function( $val )
			{
				if ( !$val and \IPS\Request::i()->shipment_ship_method == 'easypost' )
				{
					throw new \DomainException('form_required');
				}
			}, NULL, 'oz', 'easypost_package_weight' ) );
			
			$matrix = NULL;
			$fromCountry = \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->easypost_address ?: \IPS\Settings::i()->site_address )->country;
			if ( $fromCountry != $shipment->data['address']['country'] )
			{
				$originCountries = array();
				foreach ( \IPS\GeoLocation::$countries as $code )
				{
					$originCountries[ $code ] = 'country-' . $code;
				}
				
				$matrix = new \IPS\Helpers\Form\Matrix;
				$matrix->columns = array(
					'epc_description'		=> function( $key, $value, $data )
					{
						return new \IPS\Helpers\Form\Text( $key, $value, TRUE );
					},
					'epc_quantity'		=> function( $key, $value, $data )
					{
						return new \IPS\Helpers\Form\Number( $key, $value ?: 1, TRUE );
					},
					'epc_value'		=> function( $key, $value, $data )
					{
						return new \IPS\Helpers\Form\Number( $key, $value ?: 0, TRUE, array( 'decimals' => TRUE ) );
					},
					'epc_weight'		=> function( $key, $value, $data )
					{
						return new \IPS\Helpers\Form\Text( $key, $value ?: 0, TRUE, array( 'decimals' => 1 ) );
					},
					'epc_origin'		=> function( $key, $value, $data ) use ( $originCountries, $fromCountry )
					{
						return new \IPS\Helpers\Form\Select( $key, $value ?: $fromCountry, FALSE, array( 'options' => $originCountries ) );
					},
					'epc_hs_tarrif'		=> function( $key, $value, $data )
					{
						return new \IPS\Helpers\Form\Text( $key, $value, FALSE, array( 'maxLength' => 6, 'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('optional') ) );
					},
				);
				foreach ( $shipment->items as $item )
				{
					$weight = new \IPS\nexus\Shipping\Weight( $item['weight']['kilograms'] );
					$matrix->rows[] = array(
						'epc_description'	=> $item['name'],
						'epc_quantity'		=> $item['quantity'],
						'epc_value'			=> $item['price']['currency'] === 'USD' ? $item['price']['amount'] : 0,
						'epc_weight'		=> round( $weight->float('oz'), 1 )
					);
				}
				
				$form->addHeader( 'easypost_customs_header' );
				$form->addMatrix( 'easypost_customs_details', $matrix );
			}
		}
		
		
		$form->add( new \IPS\Helpers\Form\Text( 'o_tracknumber', NULL, FALSE, array( 'maxLength' => 255 ), NULL, NULL, NULL, 'o_tracknumber' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'tracking_url', NULL, FALSE, array( 'maxLength' => 255 ), NULL, NULL, NULL, 'tracking_url' ) );
		
		/* Handle Submissions */
		if ( $values = $form->values() )
		{
			/* EasyPost */
			if ( isset( $values['shipment_ship_method'] ) AND $values['shipment_ship_method'] === 'easypost' )
			{
				try
				{		
					/* Parcel Data */
					if ( $values['easypost_package'] === 'custom' )
					{
						$parcelData = array(
							'length'	=> $values['easypost_package_length'],
							'width'		=> $values['easypost_package_width'],
							'height'	=> $values['easypost_package_height'],
						);
					}
					else
					{
						$parcelData = array( 'predefined_package' => $values['easypost_package'] );
					}
					$parcelData['weight'] = $values['easypost_package_weight'];
					
					/* Customs Info */
					$customsInfo = NULL;
					if ( isset( $values['easypost_customs_details'] ) )
					{
						$customItems = array();
						$totalValue = 0;
						foreach ( $values['easypost_customs_details'] as $customItem )
						{
							if ( $customItem['epc_description'] )
							{
								$totalValue += $customItem['epc_value'];
								$customItems[] = array(
									'description'		=> $customItem['epc_description'],
									'quantity'			=> $customItem['epc_quantity'],
									'value'				=> $customItem['epc_value'],
									'weight'			=> $customItem['epc_weight'],
									'hs_tariff_number'	=> $customItem['epc_hs_tarrif'],
									'origin_country'	=> $customItem['epc_origin'],
								);
							}
						}
						
						$customsInfo = array(
							'eel_pfc' 		=> ( $totalValue < 2500 ) ? 'NOEEI 30.37(a)' : '',
							'contents_type'	=> 'merchandise',
							'customs_items'	=> $customItems
						);
					}
					
					/* Send API call */
					$toAddress = $shipment->data['address'];
					$fromAddress = \IPS\GeoLocation::buildFromJson( \IPS\Settings::i()->easypost_address ?: \IPS\Settings::i()->site_address );
					$easyPost = \IPS\Http\Url::external( 'https://api.easypost.com/v2/shipments' )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->post( array( 'shipment' => array(
						'to_address'	=> array(
							'street1'	=> array_shift( $toAddress['addressLines'] ),
							'street2'	=> \count( $toAddress['addressLines'] ) ? implode( ', ', $toAddress['addressLines'] ) : NULL,
							'city'		=> $toAddress['city'],
							'state'		=> $toAddress['region'],
							'zip'		=> $toAddress['postalCode'],
							'country'	=> $toAddress['country'],
							'name'		=> $shipment->data['cm_first_name'] . ' ' . $shipment->data['cm_last_name'],
							'phone'		=> $shipment->data['cm_phone'],
							'email'		=> $shipment->invoice->member->email
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
						'parcel'		=> $parcelData,
						'customs_info'	=> $customsInfo
					) ) )->decodeJson();
					
					if ( !\count( $easyPost['rates'] ) )
					{
						\IPS\Output::i()->error( 'err_no_easypost', '1X193/6', 403, '' );
					}
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=payments&controller=shipping&do=easypost&id={$easyPost['id']}&shipment={$shipment->id}" ) );
				}
				catch ( \IPS\Http\Request\Exception $e )
				{
					\IPS\Output::i()->error( 'err_err_easypost', '3X193/7', 500, '', array(), $e->getMessage() );
				}
			}
			/* Manual */
			else
			{
				$shipment->status = \IPS\nexus\Shipping\Order::STATUS_SHIPPED;
				$shipment->shipped_date = new \IPS\DateTime;
				if ( $values['o_tracknumber'] )
				{
					$shipment->tracknumber = $values['o_tracknumber'];
				}
				if ( $values['tracking_url'] )
				{
					$shipment->service = $values['tracking_url'];
				}
				$shipment->save();
				$shipment->sendNotification();
			}
			
			$shipment->invoice->member->log( 'shipping', array( 'id' => $shipment->id ) );
			$this->_redirect( $shipment );
		}
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'shipment_number', FALSE, array( 'sprintf' => array( $shipment->id ) ) );
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * EasyPost
	 *
	 * @return	void
	 */
	public function easypost()
	{
		/* Get Data */
		try
		{
			$easyPost = \IPS\Http\Url::external( 'https://api.easypost.com/v2/shipments/' . \IPS\Request::i()->id )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->get()->decodeJson();
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->shipment );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'err_err_easypost', '3X193/8', 500, '', array(), $e->getMessage() );
		}
		
		/* Gigure out our options */
		$selected = NULL;
		$options = array();
		$descriptions = array();
		foreach ( $easyPost['rates'] as $rate )
		{
			if ( $rate['service'] === $shipment->api_service )
			{
				$selected = $rate['id'];
			}
			
			$service = '';
			for ( $i=0; $i<\strlen( $rate['service'] ); $i++ )
			{
				if ( \strtoupper( $rate['service'][ $i ] ) === $rate['service'][ $i ] )
				{
					$service .= " {$rate['service'][ $i ]}";
				}
				else
				{
					$service .= $rate['service'][ $i ];
				}
			}
			
			$options[ $rate['id'] ] = $rate['carrier'] . $service . ' - ' . new \IPS\nexus\Money( $rate['rate'], $rate['currency'] );
			if ( $rate['est_delivery_days'] )
			{
				$descriptions[ $rate['id'] ] = \IPS\Member::loggedIn()->language()->addToStack( 'easypost_rates_desc', FALSE, array( 'sprintf' => array( $rate['est_delivery_days'] ) ) );		
			}
		}
		
		/* Build form */			
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'easypost_rate', $selected, TRUE, array( 'options' => $options, 'descriptions' => $descriptions ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			try
			{
				$response = \IPS\Http\Url::external( 'https://api.easypost.com/v2/shipments/' . \IPS\Request::i()->id . '/buy' )->request()->login( \IPS\Settings::i()->easypost_api_key, '' )->post( array(
					'rate'	=> array(
						'id'	=> $values['easypost_rate']
					)
				) )->decodeJson();
				
				if ( isset( $response['error'] ) )
				{
					\IPS\Output::i()->error( 'err_err_easypost', '3X193/9', 500, '', array(), $response['error']['message'] );
				}

				$shipment->label = $response['postage_label']['label_url'];
				$shipment->tracknumber = $response['tracking_code'];
				$shipment->extra = json_encode( array( 'shipment' => $response['selected_rate']['shipment_id'], 'rate' => $response['selected_rate']['id'] ) );
				$shipment->status = \IPS\nexus\Shipping\Order::STATUS_SHIPPED;
				$shipment->shipped_date = new \IPS\DateTime;
				$shipment->save();
				$shipment->sendNotification();
				
				\IPS\Output::i()->redirect( $shipment->acpUrl() );
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				\IPS\Output::i()->error( 'err_err_easypost', '3X193/A', 500, '', array(), $e->getMessage() );
			}
		}
		
		/* How much did the customer pay for shipping? */
		$invoiceSummary = $shipment->invoice->summary();
		$otherShipments = \count( $shipment->invoice->shipments() ) - 1;
		$message = $otherShipments ? \IPS\Member::loggedIn()->language()->addToStack( 'shipment_cost_others', FALSE, array( 'sprintf' => array( $invoiceSummary['shippingTotal'], $shipment->invoice->id, $otherShipments ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'shipment_cost_noothers', FALSE, array( 'sprintf' => array( $invoiceSummary['shippingTotal'] ) ) );
		\IPS\Member::loggedIn()->language()->words['easypost_rate_desc'] = $message;
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'shipment_number', FALSE, array( 'sprintf' => array( $shipment->id ) ) );
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 */
	public function cancel()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'shiporders_edit' );
		\IPS\Session::i()->csrfCheck();
		
		/* Load */
		try
		{
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X193/4', 404, '' );
		}
		
		/* Cancel */
		$shipment->status = $shipment::STATUS_CANCELED;
		$shipment->save();
		
		/* Log */
		$shipment->invoice->member->log( 'shipping', array( 'id' => $shipment->id, 'type' => 'canc' ) );
		
		/* Redirect */
		$this->_redirect( $shipment );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'shiporders_delete' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Load */
		try
		{
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X193/5', 404, '' );
		}
		
		/* Delete */
		$shipment->delete();
		
		/* Log */
		try
		{
			$shipment->invoice->member->log( 'shipping', array( 'id' => $shipment->id, 'deleted' => TRUE ) );
		}
		catch ( \OutOfRangeException $e ) {}
		
		/* Redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=shipping') );
	}
	
	/**
	 * Print
	 *
	 * @return	void
	 */
	public function printout()
	{
		/* Load */
		try
		{
			$shipment = \IPS\nexus\Shipping\Order::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X193/3', 404, '' );
		}
		$data = $shipment->data;
		
		if ( \IPS\Request::i()->print === 'apilabel' )
		{
			\IPS\Output::i()->sendOutput( $shipment->label, 200, 'application/pdf' );
		}

		if ( \IPS\Request::i()->print === 'label' )
		{
			$output = \IPS\Theme::i()->getTemplate('shiporders', 'nexus', 'global')->packingLabel( $shipment );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'shipping.css', 'nexus' ) );			
		}
		else
		{
			$output = \IPS\Theme::i()->getTemplate('shiporders', 'nexus', 'global')->packingSheet( $shipment );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'shipping.css', 'nexus' ) );
		}

		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->blankTemplate( $output ), 200, 'text/html' );
	}
	
	/**
	 * Redirect
	 *
	 * @param	\IPS\nexus\Shipping\Order	$shipment	The shipment
	 * @return	void
	 */
	protected function _redirect( \IPS\nexus\Shipping\Order $shipment )
	{
		if ( isset( \IPS\Request::i()->r ) )
		{
			switch ( \IPS\Request::i()->r )
			{
				case 'v':
					\IPS\Output::i()->redirect( $shipment->acpUrl() );
					break;
					
				case 'i':
					\IPS\Output::i()->redirect( $shipment->invoice->acpUrl() );
					break;
				
				case 'c':
					\IPS\Output::i()->redirect( $shipment->member->acpUrl() );
					break;
				
				case 't':
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=shipping') );
					break;
			}
		}
		
		\IPS\Output::i()->redirect( $transaction->acpUrl() );
	}
}
<?php
/**
 * @brief		PayPal Pay Out Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		7 Apr 2014
 */

namespace IPS\nexus\Gateway\PayPal;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Settings;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayPal Pay Out Gateway
 */
class _Payout extends \IPS\nexus\Payout
{
	/**
	 * ACP Settings
	 *
	 * @return	array
	 */
	public static function settings()
	{
		$settings = json_decode( \IPS\Settings::i()->nexus_payout, TRUE );
		
		$return = array();
		$return[] = new \IPS\Helpers\Form\Text( 'paypal_client_id', ( isset( $settings['PayPal']['client_id'] ) AND isset( $settings['PayPal']['client_id'] ) AND $settings['PayPal']['client_id'] ) ? $settings['PayPal']['client_id'] : '', NULL );
		$return[] = new \IPS\Helpers\Form\Text( 'paypal_secret', ( isset( $settings['PayPal']['secret'] ) AND $settings['PayPal']['secret'] ) ? $settings['PayPal']['secret'] : '', NULL );
		return $return;
	}
	
	/**
	 * Payout Form
	 *
	 * @return	array
	 */
	public static function form()
	{		
		$return = array();
		$return[] = new \IPS\Helpers\Form\Email( 'paypal_email', \IPS\Member::loggedIn()->email, NULL, array(), function( $val )
		{
			if ( !$val and \IPS\Request::i()->withdraw_method === 'PayPal' )
			{
				throw new \DomainException('form_required');
			}
		} );
		return $return;
	}
	
	/**
	 * Get data and validate
	 *
	 * @param	array	$values	Values from form
	 * @return	mixed
	 * @throws	\DomainException
	 */
	public function getData( array $values )
	{
		return $values['paypal_email'];
	}
	
	/** 
	 * Process
	 *
	 * @return	void
	 * @throws	\Exception
	 */
	public function process()
	{
		static::checkToken();

		$settings = json_decode( \IPS\Settings::i()->nexus_payout, true );

		$data = array(
			'items' => array(
				array(
					'amount' => array(
						'currency' => $this->amount->currency,
						'value' => $this->amount->amountAsString()
					),
					'receiver' => $this->data,
					'recipient_type' => 'EMAIL'
				)
			),
			'sender_batch_header' => array(
				'recipient_type' => 'EMAIL',
				'sender_batch_id' => "Payout " . \IPS\DateTime::create()->rfc3339()
			)
		);

		$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api.paypal.com' ) . '/v1/payments/payouts' )
			->request()
			->forceTls()
			->setHeaders( array(
				'Content-Type'		=> 'application/json',
				'Authorization'		=> 'Bearer ' . $settings['PayPal']['token']
			) )
			->post( json_encode( $data ) );

		$json = $response->decodeJson();

		if( $response->httpResponseCode != 201 )
		{
			throw new \IPS\Http\Request\Exception( $json['message'] );
		}

		if( isset( $json['batch_header'] ) AND $json['batch_header']['payout_batch_id'] )
		{
			$this->gw_id = $json['batch_header']['payout_batch_id'];
			switch( $json['batch_header']['batch_status'] )
			{
				case 'DENIED':
				case 'CANCELED':
					$this->status = static::STATUS_CANCELED;
					break;
				case 'SUCCESS':
					$this->status = static::STATUS_COMPLETE;
					break;
				default:
					$this->status = static::STATUS_PROCESSING;
					break;
			}

			$this->save();
		}
	}
	
	/** 
	 * Mass Process
	 *
	 * @param	\IPS\Patterns\ActiveRecordIterator	$payouts	Iterator of payouts to process
	 * @return	void
	 * @throws	\Exception
	 */
	public static function massProcess( \IPS\Patterns\ActiveRecordIterator $payouts )
	{
		/* Make sure we check the token first so that we have proper authorization to API calls */
		static::checkToken();

		$settings = json_decode( \IPS\Settings::i()->nexus_payout, TRUE );

		/* Build a batch of payout items */
		$payoutIds = $payoutData = [];
		foreach( $payouts as $payout )
		{
			$payoutIds[ $payout->amount->currency ] = $payout->id;
			$payoutData[ $payout->amount->currency ][] = [
				'amount' => [
					'currency' => $payout->amount->currency,
					'value' => $payout->amount->amountAsString()
				],
				'receiver' => $payout->data,
				'recipient_type' => 'EMAIL'
			];
		}

		foreach( $payoutData as $currency => $batchData )
		{
			$requestData = [
				'items' => $batchData,
				'sender_batch_header' => [
					'recipient_type' => 'EMAIL',
					'sender_batch_id' => "Payout-{$currency}-" . \IPS\DateTime::create()->rfc3339()
				]
			];

			$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api.paypal.com' ) . '/v1/payments/payouts' )
				->request()
				->forceTls()
				->setHeaders( [
					'Content-Type'		=> 'application/json',
					'Authorization'		=> 'Bearer ' . $settings['PayPal']['token']
				] )
				->post( json_encode( $requestData ) );

			if( $response->httpResponseCode != 201 )
			{
				throw new \RuntimeException( (string) $response, $response->httpResponseCode );
			}

			$response = $response->decodeJson();

			if( isset( $response['batch_header'] ) AND $response['batch_header']['payout_batch_id'] )
			{
				$update = [
					'po_gw_id' => $response['batch_header']['payout_batch_id']
				];

				switch( $response['batch_header']['batch_status'] )
				{
					case 'DENIED':
					case 'CANCELED':
						$update['po_status'] = static::STATUS_CANCELED;
						break;
					case 'SUCCESS':
						$update['po_status'] = static::STATUS_COMPLETE;
						break;
					default:
						$update['po_status'] = static::STATUS_PROCESSING;
						break;
				}

				\IPS\Db::i()->update( 'nexus_payouts', $update, \IPS\Db::i()->in( 'po_id', $payoutIds[ $currency ] ) );
			}
		}
	}

	/**
	 * @brief   cache batch results for further lookup
	 */
	protected static array $_batchCache = [];

	/**
	 * Check the status of a payout batch and update the withdrawal requests accordingly
	 *
	 * @param string	$batchId
	 * @return string|NULL
	 */
	public static function checkStatus( string $batchId ):? string
	{
		if( isset( static::$_batchCache[ $batchId ] ) )
		{
			return static::$_batchCache[ $batchId ];
		}

		/* Make sure we check the token first so that we have proper authorization to API calls */
		static::checkToken();

		$settings = json_decode( \IPS\Settings::i()->nexus_payout, TRUE );

		$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api.paypal.com' ) . '/v1/payments/payouts/' . $batchId )
			->request()
			->forceTls()
			->setHeaders( array(
				'Content-Type'		=> 'application/json',
				'Authorization'		=> 'Bearer ' . $settings['PayPal']['token']
			) )
			->get()
			->decodeJson();

		if( isset( $response['batch_header'] ) AND $response['batch_header']['payout_batch_id'] )
		{
			switch( $response['batch_header']['batch_status'] )
			{
				case 'DENIED':
				case 'CANCELED':
					return static::$_batchCache[ $batchId ] = static::STATUS_CANCELED;
					break;
				case 'SUCCESS':
					return static::$_batchCache[ $batchId ] = static::STATUS_COMPLETE;
					break;
				default:
					return static::$_batchCache[ $batchId ] = static::STATUS_PROCESSING;
					break;
			}
		}

		return NULL;
	}

	/**
	 * Get Token
	 *
	 * @return	void
	 * @throws	\IPS\Http\Request\Exception
	 * @throws	\UnexpectedValueException
	 */
	protected static function checkToken()
	{
		$payoutSettings = json_decode( \IPS\Settings::i()->nexus_payout, true );
		$settings = $payoutSettings['PayPal'];

		if ( !isset( $settings['token'] ) or $settings['token_expire'] < time() )
		{
			$response = \IPS\Http\Url::external( 'https://' . ( \IPS\NEXUS_TEST_GATEWAYS ? 'api-m.sandbox.paypal.com' : 'api.paypal.com' ) . '/v1/oauth2/token' )
				->request()
				->forceTls()
				->setHeaders( array(
					'Accept'			=> 'application/json',
					'Accept-Language'	=> 'en_US',
				) )
				->login( $settings['client_id'], $settings['secret'] )
				->post( array( 'grant_type' => 'client_credentials' ) )
				->decodeJson();

			if ( !isset( $response['access_token'] ) )
			{
				throw new \UnexpectedValueException( isset( $response['error_description'] ) ? $response['error_description'] : $response );
			}

			$settings['token'] = $response['access_token'];
			$settings['token_expire'] = ( time() + $response['expires_in'] );
			$payoutSettings['PayPal'] = $settings;

			\IPS\Settings::i()->changeValues( array(
				'nexus_payout' => json_encode( $payoutSettings )
			) );
		}
	}
}
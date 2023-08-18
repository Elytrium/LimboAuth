<?php
/**
 * @brief		Community Enhancement: Google Maps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Apr 2013
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement: Google Maps
 */
class _GoogleMaps
{
	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "google_maps.jpeg";

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->google_maps_api_key and ( \IPS\Settings::i()->googlemaps or \IPS\Settings::i()->googleplacesautocomplete ) );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$wizard = new \IPS\Helpers\Wizard( array(
			'google_maps_enable_apis'		=> function( $data )
			{
				$form = new \IPS\Helpers\Form( 'google_maps_enable_apis', 'continue' );
				$form->addHeader('google_maps_choose_features');
				$form->add( new \IPS\Helpers\Form\YesNo( 'googlemaps', isset( $data['googlemaps'] ) ? $data['googlemaps'] : \IPS\Settings::i()->googlemaps, FALSE, array( 'togglesOn' => array( 'googleApi_jsapi', 'googleApi_staticapi', 'googleApi_geocodeapi', 'google_maps_enable_apis_google_maps_static_use_embed' ) ) ) );
				$form->add( new \IPS\Helpers\Form\YesNo( 'google_maps_static_use_embed', isset( $data['google_maps_static_use_embed'] ) ? $data['google_maps_static_use_embed'] : \IPS\Settings::i()->google_maps_static_use_embed, FALSE ) );
				$form->add( new \IPS\Helpers\Form\YesNo( 'googleplacesautocomplete', isset( $data['googleplacesautocomplete'] ) ? $data['googleplacesautocomplete'] : \IPS\Settings::i()->googleplacesautocomplete, FALSE, array( 'togglesOn' => array( 'googleApi_places' ) ) ) );
				$form->addHeader('google_maps_enable_apis');
				$form->addMessage('google_maps_create_project_message');
				foreach ( array( 'jsapi', 'staticapi', 'geocodeapi', 'places' ) as $k )
				{
					$form->addHtml( \IPS\Theme::i()->getTemplate('applications')->enhancementsGoogleMapsApi( $k ) );
				}
				
				if ( $values = $form->values() )
				{
					if ( $values['googlemaps'] or $values['googleplacesautocomplete'] or $values['google_maps_static_use_embed'] )
					{
						return $values;
					}
					else
					{
						\IPS\Settings::i()->changeValues( array( 'googlemaps' => 0, 'googleplacesautocomplete' => 0, 'google_maps_static_use_embed' => 0 ) );
						\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=enhancements'), 'saved' );
					}
				}
				
				return (string) $form;
			},
			'google_maps_create_credentials'=> function( $data )
			{
				$websiteUrl = rtrim( \IPS\Settings::i()->base_url, '/' ) . '/*';
				
				$form = new \IPS\Helpers\Form;
				if ( $data['googlemaps'] )
				{
					$form->addHeader('google_maps_create_public_key_header');
				}
				$form->addMessage('google_maps_create_public_key_message');
				$form->add( new \IPS\Helpers\Form\Text( 'google_maps_api_key', \IPS\Settings::i()->google_maps_api_key, TRUE, array(), function( $val )
				{
					try
					{
						$response = \IPS\Http\Url::external( 'https://maps.googleapis.com/maps/api/staticmap' )->setQueryString( array(
							'center'		=> '40.714224,-73.961452',
							'zoom'		=> NULL,
							'size'		=> "100x100",
							'sensor'		=> 'false',
							'markers'	=> '40.714224,-73.961452',
							'key'		=> $val,
						) )->request()->get();
					}
					catch ( \Exception $e )
					{
						throw new \DomainException('google_maps_api_error');
					}
					if ( $response->httpResponseCode != 200 )
					{
						throw new \DomainException( $response ?: 'google_maps_api_key_invalid' );
					}
				} ) );
				$form->addHtml( \IPS\Theme::i()->getTemplate('applications')->enhancementsGoogleMapsKeyRestrictions( TRUE, $websiteUrl, $data ) );
				if ( $data['googlemaps'] )
				{
					$form->addHeader('google_maps_create_secret_key_header');
					$form->addMessage('google_maps_create_secret_key_message');
					$form->add( new \IPS\Helpers\Form\Text( 'google_maps_api_key_secret', \IPS\Settings::i()->google_maps_api_key_secret, TRUE, array(), function( $val )
					{
						try
						{
							$response = \IPS\Http\Url::external( "https://maps.googleapis.com/maps/api/geocode/json" )->setQueryString( array(
								'latlng'	=> '40.714224,-73.961452',
								'sensor'	=> 'false',
								'key'		=> $val
							) )->request()->get()->decodeJson();
						}
						catch ( \Exception $e )
						{
							throw new \DomainException('google_maps_api_error');
						}
						if ( !isset( $response['status'] ) or $response['status'] !== 'OK' )
						{
							throw new \DomainException( ( isset( $response['error_message'] ) ) ? $response['error_message'] : 'google_maps_api_key_invalid' );
						}
					} ) );
					$form->addHtml( \IPS\Theme::i()->getTemplate('applications')->enhancementsGoogleMapsKeyRestrictions( FALSE, $websiteUrl, $data ) );
				}
				
				if ( $values = $form->values() )
				{
					$form->saveAsSettings( array(
						'googlemaps'						=> $data['googlemaps'],
						'googleplacesautocomplete'		=> $data['googleplacesautocomplete'],
						'google_maps_static_use_embed'  => $data['google_maps_static_use_embed'],
						'google_maps_api_key'			=> $values['google_maps_api_key'],
						'google_maps_api_key_secret'		=> $data['googlemaps'] ? $values['google_maps_api_key_secret'] : ''
					) );
					\IPS\Session::i()->log( 'acplog__enhancements_edited', array( 'enhancements__core_GoogleMaps' => TRUE ) );
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=applications&controller=enhancements'), 'saved' );
				}
				
				return (string) $form;
			},
		), \IPS\Http\Url::internal('app=core&module=applications&controller=enhancements&do=edit&id=core_GoogleMaps') );
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/googlemaps' ),
				'target'	=> '_blank'
			),
		);
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_GoogleMaps', $wizard );
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 */
	public function toggle( $enabled )
	{
		/* If we're disabling, just disable */
		if( !$enabled )
		{
			\IPS\Settings::i()->changeValues( array( 'googlemaps' => 0, 'googleplacesautocomplete' => 0 ) );
		}

		/* Otherwise if we already have an API key, just toggle on */
		if( $enabled && \IPS\Settings::i()->google_maps_api_key )
		{
			\IPS\Settings::i()->changeValues( array( 'googlemaps' => 1, 'googleplacesautocomplete' => 1, 'mapbox' => 0 ) );
		}
		else
		{
			/* Otherwise we need to let them enter an API key before we can enable.  Throwing an exception causes you to be redirected to the settings page. */
			throw new \DomainException;
		}
	}
}
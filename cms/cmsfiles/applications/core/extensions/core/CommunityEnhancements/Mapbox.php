<?php
/**
 * @brief		Community Enhancement: Mapbox
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Nov 2017
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement: Mapbox
 */
class _Mapbox
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
	public $icon	= "mapbox.png";

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->mapbox_api_key and ( \IPS\Settings::i()->mapbox ) );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$validation = function( $val ) {
			if ( $val and !\IPS\Request::i()->mapbox_api_key )
			{
				throw new \DomainException('mapbox_api_key_req');
			}
		};
		
		$form = new \IPS\Helpers\Form;		
		$form->add( new \IPS\Helpers\Form\Text( 'mapbox_api_key', \IPS\Settings::i()->mapbox_api_key, FALSE, array(), function( $val ) {
			if ( $val )
			{			
				/* Check API */
				try
				{
					$location = '-73.961452,40.714224';

					$response = \IPS\Http\Url::external( "https://api.mapbox.com/geocoding/v5/mapbox.places/{$location}.json" )->setQueryString( array(
						'access_token'		=> $val,
					) )->request()->get()->decodeJson();
				}
				catch ( \Exception $e )
				{
					throw new \DomainException('mapbox_api_error');
				}

				if ( isset( $response['message'] ) )
				{
					throw new \DomainException('mapbox_api_key_invalid');
				}

			}
		} ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'mapbox', \IPS\Settings::i()->mapbox, FALSE, array(), $validation ) );

		if ( $values = $form->values() )
		{
			if( $values['mapbox'] > 0 )
			{
				$values['googlemaps'] = 0;
				$values['googleplacesautocomplete'] = 0;
			}

			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__enhancements_edited', array( 'enhancements__core_MapboxMaps' => TRUE ) );
			\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/mapboxmaps' ),
				'target'	=> '_blank'
			),
		);
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_MapboxMaps', $form );
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
			\IPS\Settings::i()->changeValues( array( 'mapbox' => 0 ) );
		}

		/* Otherwise if we already have an API key, just toggle on */
		if( $enabled && \IPS\Settings::i()->mapbox_api_key )
		{
			\IPS\Settings::i()->changeValues( array( 'mapbox' => 1, 'googlemaps' => 0, 'googleplacesautocomplete' => 0 ) );
		}
		else
		{
			/* Otherwise we need to let them enter an API key before we can enable.  Throwing an exception causes you to be redirected to the settings page. */
			throw new \DomainException;
		}
	}
}
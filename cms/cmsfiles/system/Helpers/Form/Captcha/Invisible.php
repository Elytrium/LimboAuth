<?php
/**
 * @brief		Invisible reCAPTCHA
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 June 2017
 */

namespace IPS\Helpers\Form\Captcha;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invisible reCAPTCHA
 */
class _Invisible implements CaptchaInterface
{
	/**
	 *  Does this CAPTCHA service support being added in a modal?
	 */
	public static $supportsModal = TRUE;
	
	/**
	 * @brief	Error
	 */
	protected $error;
	
	/**
	 * Display
	 *
	 * @return	string
	 */
	public function getHtml()
	{		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->captchaInvisible( \IPS\Settings::i()->recaptcha2_public_key, preg_replace( '/^(.+?)\..*$/', '$1', \IPS\Member::loggedIn()->language()->short ) );
	}
	
	/**
	 * Display
	 *
	 * @return	string
	 */
	public function rowHtml()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->captchaInvisible( \IPS\Settings::i()->recaptcha2_public_key, preg_replace( '/^(.+?)\..*$/', '$1', \IPS\Member::loggedIn()->language()->short ), TRUE );
	}
		
	/**
	 * Verify
	 *
	 * @return	bool|null	TRUE/FALSE indicate if the test passed or not. NULL indicates the test failed, but the captcha system will display an error so we don't have to.
	 */
	public function verify()
	{		
		try
		{
			$response = \IPS\Http\Url::external( 'https://www.google.com/recaptcha/api/siteverify' )->request()->post( array(
				'secret'		=> \IPS\Settings::i()->recaptcha2_private_key,
				'response'		=> trim( \IPS\Request::i()->__get('g-recaptcha-response') ),
				'remoteip'		=> \IPS\Request::i()->ipAddress(),
			) )->decodeJson( TRUE );
						
			return ( ( (bool) $response['success'] ) and ( $response['hostname'] === \IPS\Http\Url::internal('')->data[ \IPS\Http\Url::COMPONENT_HOST ] ) );
		}
		catch( \RuntimeException $e )
		{
			if( $e->getMessage() == 'BAD_JSON' )
			{
				return FALSE;
			}
			else
			{
				throw $e;
			}
		}
	}

}
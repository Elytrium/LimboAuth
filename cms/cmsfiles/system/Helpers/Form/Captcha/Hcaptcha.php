<?php
/**
 * @brief		hCaptcha
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Mai 2022
 */

namespace IPS\Helpers\Form\Captcha;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * hCaptcha
 */
class _Hcaptcha implements CaptchaInterface
{
	/**
	 *  Does this CAPTCHA service support being added in a modal?
	 */
	public static $supportsModal = FALSE;

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
		\IPS\Output::i()->jsFilesAsync[] = "https://js.hcaptcha.com/1/api.js";
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->hCaptcha( \IPS\Settings::i()->hcaptcha_sitekey );
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
			$response = \IPS\Http\Url::external( 'https://hcaptcha.com/siteverify' )->request()->post( array(
				'secret'		=> \IPS\Settings::i()->hcaptcha_secret,
				'response'		=> trim( \IPS\Request::i()->__get('h-captcha-response') ),
				'remoteip'		=> \IPS\Request::i()->ipAddress(),
			) )->decodeJson( TRUE );

			$hostname = \IPS\Http\Url::internal('')->data[ \IPS\Http\Url::COMPONENT_HOST ];
			return ( ( (bool) $response['success'] ) and ( $response['hostname'] === mb_substr( $hostname, \mb_strlen( $hostname ) - mb_strlen( $response['hostname'] ) ) ) );
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
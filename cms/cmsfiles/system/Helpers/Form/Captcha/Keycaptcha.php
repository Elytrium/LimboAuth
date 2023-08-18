<?php
/**
 * @brief		keyCAPTCHA
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Apr 2013
 */

namespace IPS\Helpers\Form\Captcha;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * keyCAPTCHA
 */
class _Keycaptcha implements CaptchaInterface
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
		$explodedKey	= explode( '0', \IPS\Settings::i()->keycaptcha_privatekey, 2 );
		$uniq			= md5( mt_rand() );
		$sign			= md5( $uniq . \IPS\Request::i()->ipAddress . $explodedKey[0] );
		$sign2			= md5( $uniq . $explodedKey[0] );
		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->captchaKeycaptcha( $explodedKey[1], $uniq, $sign, $sign2 );
	}
	
	/**
	 * Verify
	 *
	 * @return	bool|null	TRUE/FALSE indicate if the test passed or not. NULL indicates the test failed, but the captcha system will display an error so we don't have to.
	 */
	public function verify()
	{
		$explodedResponse	= explode( '|', \IPS\Request::i()->keycaptcha );
		$explodedKey		= explode( '0', \IPS\Settings::i()->keycaptcha_privatekey );
	
		if( \IPS\Login::compareHashes( $explodedResponse[0], md5( 'accept' . $explodedResponse[1] . $explodedKey[0] . $explodedResponse[2] ) ) )
		{
			if( (string) \IPS\Http\Url::external( $explodedResponse[2] )->request()->get() === '1' )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}

}
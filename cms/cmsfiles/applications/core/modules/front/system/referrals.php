<?php
/**
 * @brief		referrals
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Aug 2019
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Incoming Referrals
 *
 * Deprecated controller maintained for backwards compatibility with old referral links
 */
class _referrals extends \IPS\Dispatcher\Controller
{
	/**
	 * Handle Referral
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Request::i()->setCookie( 'referred_by', \intval( \IPS\Request::i()->id ), \IPS\DateTime::create()->add( new \DateInterval( 'P1Y' ) ) );

		try
		{
			$target = \IPS\Request::i()->direct ? \IPS\Http\Url::createFromString( base64_decode( \IPS\Request::i()->direct ) ) : \IPS\Http\Url::baseUrl();
		}
		catch( \IPS\Http\Url\Exception $e )
		{
			$target = NULL;
		}

		if ( $target instanceof \IPS\Http\Url\Internal )
		{
			\IPS\Output::i()->redirect( $target );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Settings::i()->base_url );
		}
	}
}
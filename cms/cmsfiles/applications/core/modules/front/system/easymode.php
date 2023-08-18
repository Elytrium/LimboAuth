<?php
/**
 * @brief		Easy mode controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Oct 2021
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redirect
 */
class _easymode extends \IPS\Dispatcher\Controller
{
	/**
	 * Proxy remote CSS URLs to allow for JS to select classes
	 *
	 * @return	void
	 */
	protected function proxy()
	{
		if ( \IPS\Theme::isUsingEasyModeEditor() )
		{
			if ( isset( \IPS\Request::i()->f ) )
			{
				$url = base64_decode( \IPS\Request::i()->f );
				$storage = \IPS\File::getClass( 'core_Theme' );
				$storageUrl = parse_url( $storage->baseUrl() );
				$test = parse_url( $url );

				/* Does this URL match the host of the storage URL? */
				if ( preg_match( "/\.css(\.gz)?(\?|$)/", $url ) and isset( $test['host'] ) and $test['host'] == $storageUrl['host'] )
				{
					$encoded = ( preg_match( "/\.css\.gz?(\?|$)/", $url ) );

					try
					{
						$css = \IPS\Http\Url::external( $url )->setScheme('https')->request()->get();

						if ( $encoded )
						{
							$css = gzdecode( $css );
						}

						\IPS\Output::i()->sendOutput( $css, 200, 'text/css' );
					}
					catch( \Exception $e ) { }
				}
			}
		}

		/* still here? */
		\IPS\Output::i()->sendOutput( '/* Could not read ' . $url . '*/', 200, 'text/css' );
	}
}
<?php
/**
 * @brief		Embed iframe display
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Sep 2014
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Embed iframe display
 */
class _embed extends \IPS\Content\Controller
{
	/**
	 * Embed iframe display
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Check cache */
		$cacheKey = 'embed_' . md5( \IPS\Request::i()->url );
		try
		{
			$return = \IPS\Data\Cache::i()->getWithExpire( $cacheKey, TRUE );
		}

		/* Not in cache - fetch */
		catch ( \OutOfRangeException $e )
		{
			try
			{
				$return = \IPS\Text\Parser::embeddableMedia( \IPS\Http\Url::createFromString( \IPS\Request::i()->url, FALSE, TRUE ), TRUE );
			}
			catch( \UnexpectedValueException $e )
			{
				$return	= '';
			}

			/* And cache */
			\IPS\Data\Cache::i()->storeWithExpire( $cacheKey, $return, \IPS\DateTime::create()->add( new \DateInterval('P1D') ), TRUE );
		}

		/* Output */
		$js = array(
			\IPS\Output::i()->js( 'js/commonEmbedHandler.js', 'core', 'interface' ),
			\IPS\Output::i()->js( 'js/externalEmbedHandler.js', 'core', 'interface' )
		);
		/* Intentionally replace the cssFiles array with a single file here, since we don't need the complete CSS framework in external embeds */
		\IPS\Output::i()->cssFiles = \IPS\Theme::i()->css( 'styles/embeds.css', 'core', 'front' );
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->embedExternal( $return, $js ), 200 );
	}
}

<?php
/**
 * @brief		Print out sitemap
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Aug 2013
 */

namespace IPS\core\modules\front\sitemap;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Print out sitemap
 */
class _sitemap extends \IPS\Content\Controller
{
	/**
	 * Print out the requested sitemap
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( isset( \IPS\Request::i()->file ) )
		{
			try
			{
				$content = \IPS\Db::i()->select( 'data', 'core_sitemap', array( 'data IS NOT NULL AND sitemap=?', \IPS\Request::i()->file ) )->first();
				$content = str_replace( '<loc>{base_url}', '<loc>' . \IPS\Settings::i()->base_url, $content );

				/* http://www.w3.org/TR/REC-xml/#charsets */
				$content = preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $content);
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'sitemap_not_found', '2C152/1', 404, '' );
			}
		}
		else
		{
			$sitemapUrl = \IPS\Http\Url::external( \IPS\Settings::i()->sitemap_url ? rtrim( \IPS\Settings::i()->sitemap_url, '/' ) : rtrim( \IPS\Settings::i()->base_url, '/' ) . '/sitemap.php' );
			
			$content = \IPS\Xml\SimpleXML::create( 'sitemapindex', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
			foreach ( \IPS\Db::i()->select( array( 'sitemap', 'updated' ), 'core_sitemap', array( 'data IS NOT NULL' ) ) as $sitemap )
			{
				$content->addChild( 'sitemap', array( 'loc' => $sitemapUrl->setQueryString( 'file', $sitemap['sitemap'] ), 'lastmod' => \IPS\DateTime::ts( $sitemap['updated'] )->format('c') ) );
			}
			$content = $content->asXML();
		}

		\IPS\Output::i()->sendOutput( $content, 200, 'text/xml' );
	}
}
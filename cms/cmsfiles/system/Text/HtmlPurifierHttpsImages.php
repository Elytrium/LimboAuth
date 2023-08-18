<?php
/**
 * @brief		An HTMLPurifier image transformation that only allows HTTPS images
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Jan 2020
 */

namespace IPS\Text;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * An HTMLPurifier image transformation that only allows HTTPS images
 * @see https://stackoverflow.com/questions/34876151/htmlpurifier-allow-scheme-for-specific-tags
 */
class _HtmlPurifierHttpsImages extends \HTMLPurifier_URIFilter
{
	public $name	= 'HttpsEmbedScheme';
	public $ourHost	= '';

	public function prepare( $config )
	{
		$this->ourHost = parse_url( \IPS\Settings::i()->base_url, \PHP_URL_HOST );
	}

	public function filter( &$uri, $config, $context )
	{
		/* We only care about embedded resources for this, skip src attr so our own parser can handle that. */
		if ( !$context->get( 'EmbeddedURI', true ) OR $context->get( 'CurrentAttr', true ) == 'src' )
		{
			return true;
		}

		/* If the image is on the same domain, we will allow it. This covers lazy loading as well as emoticons. */
		if( !$uri->host OR $uri->host == $this->ourHost )
		{
			return true;
		}

		/* Don't allow anything but https */
		if( $uri->scheme !== 'https' AND $uri->scheme !== NULL )
		{
			return false;
		}

		return true;
	}
}
<?php
/**
 * @brief		Linked In share link
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Sept 2013
 * @see			<a href='http://developer.linkedin.com/plugins/share-plugin-generator'>Linked In button documentation</a>
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Linked In share link
 */
class _Linkedin extends \IPS\Content\ShareServices
{
	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->linkedin( urlencode( $this->url ), urlencode( $this->title ) );
	}
}
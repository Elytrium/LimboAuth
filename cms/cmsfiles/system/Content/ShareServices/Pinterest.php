<?php
/**
 * @brief		Pinterest share link
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Dec 2016
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pinterest share link
 */
class _Pinterest extends \IPS\Content\ShareServices
{
	/**
	 * @brief	Ccontent item
	 */
	protected $item	= NULL;
		
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	URL to the content [optional - if omitted, some services will figure out on their own]
	 * @param	string			$title	Default text for the content, usually the title [optional - if omitted, some services will figure out on their own]
	 * @param	\IPS\Content|NULL	$item	Content item (or comment) to share
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL, $title=NULL, $item=NULL )
	{
		$this->item = $item;
		
		parent::__construct( $url, $title );
	}
		
	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		if ( $this->item )
		{
			return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->pinterest( \IPS\Http\Url::external( 'https://pinterest.com/pin/create/button/' )->setQueryString( 'url', (string) $this->url )->setQueryString( 'media', (string) $this->item->shareImage() ) );
		}
		else
		{
			return '';
		}
	}
}
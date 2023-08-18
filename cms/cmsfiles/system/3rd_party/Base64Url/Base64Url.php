<?php
/**
 * @brief		URL Safe Base64 Encoding Polyfill
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Feb 2021
 */

namespace Base64Url;

class Base64Url
{
	public static function encode( string $string ): string
	{
		return \str_replace( '=', '', \strtr( base64_encode( $string ), '+/', '-_' ) );
	}
	
	public static function decode( string $string ): string
	{
		return base64_decode( \strtr( $string, '-_', '+/' ) );
	}
}
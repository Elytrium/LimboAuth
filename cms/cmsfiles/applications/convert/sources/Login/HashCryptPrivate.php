<?php
/**
 * @brief		Converter private crypt hashing
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		6 May 2016
 */

namespace IPS\convert\Login;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Helper class to facilitate private crypt hashing
 */
class _HashCryptPrivate
{
	/**
	 * Private crypt hashing
	 *
	 * @param	string		$password	Password
	 * @param	string 		$setting	Settings
	 * @param	string		$itoa64		Hash-lookup
	 * @return	string		password hash
	 */
	public function hashCryptPrivate( $password, $setting, &$itoa64 )
	{
		$output	= '*';

		// Check for correct hash
		if ( \substr( $setting, 0, 3 ) != '$H$' )
		{
			return $output;
		}

		$count_log2 = \strpos( $itoa64, $setting[3] );

		if ( $count_log2 < 7 || $count_log2 > 30 )
		{
			return $output;
		}

		$count	= 1 << $count_log2;
		$salt	= \substr( $setting, 4, 8 );

		if ( \strlen($salt) != 8 )
		{
			return $output;
		}

		/**
		 * We're kind of forced to use MD5 here since it's the only
		 * cryptographic primitive available in all versions of PHP
		 * currently in use.  To implement our own low-level crypto
		 * in PHP would result in much worse performance and
		 * consequently in lower iteration counts and hashes that are
		 * quicker to crack (by non-PHP code).
		 */
		if ( PHP_VERSION >= 5 )
		{
			$hash = md5( $salt . $password, true );

			do
			{
				$hash = md5( $hash . $password, true );
			}
			while ( --$count );
		}
		else
		{
			$hash = pack( 'H*', md5( $salt . $password ) );

			do
			{
				$hash = pack( 'H*', md5( $hash . $password ) );
			}
			while ( --$count );
		}

		$output	= \substr( $setting, 0, 12 );
		$output	.= $this->_hashEncode64( $hash, 16, $itoa64 );

		return $output;
	}

	/**
	 * Private function to encode phpBB3 hash
	 *
	 * @param	string		$input	Input
	 * @param	count 		$count	Iteration
	 * @param	string		$itoa64	Hash-lookup
	 * @return	string		phpbb3 password hash encoded bit
	 */
	protected function _hashEncode64($input, $count, &$itoa64)
	{
		$output	= '';
		$i		= 0;

		do
		{
			$value	= \ord( $input[$i++] );
			$output	.= $itoa64[$value & 0x3f];

			if ( $i < $count )
			{
				$value |= \ord($input[$i]) << 8;
			}

			$output .= $itoa64[($value >> 6) & 0x3f];

			if ( $i++ >= $count )
			{
				break;
			}

			if ( $i < $count )
			{
				$value |= \ord($input[$i]) << 16;
			}

			$output .= $itoa64[($value >> 12) & 0x3f];

			if ($i++ >= $count)
			{
				break;
			}

			$output .= $itoa64[($value >> 18) & 0x3f];
		}
		while ( $i < $count );

		return $output;
	}
}
<?php
/**
 * @brief		Encrypt Text
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2014
 */

namespace IPS\Text;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Encrypted
 */
class _Encrypt
{
	/**
	 * Get Key
	 *
	 * @return	void
	 */
	public static function key()
	{
		return \IPS\TEXT_ENCRYPTION_KEY ?: md5( \IPS\Settings::i()->sql_pass . \IPS\Settings::i()->sql_database );
	}
	
	/**
	 * @brief	Cipher
	 */
	public $cipher;
	
	/**
	 * @brief	IV
	 */
	protected $iv = NULL;
	
	/**
	 * @brief	Tag
	 */
	protected $tag = NULL;
	
	/**
	 * @brief	Hash of cipher
	 */
	protected $hmac = NULL;
			
	/**
	 * From plaintext
	 *
	 * @param	string	$plaintext	Plaintext
	 * @return	static
	 */
	public static function fromPlaintext( $plaintext )
	{
		$obj = new static;
		
		/* Try to use OpenSSL if it's available... */
		if ( \function_exists( 'openssl_get_cipher_methods' ) )
		{
			/* If GCM is available (PHP 7.1+), use that as if provides authenticated encryption natively */
			if ( \in_array( 'aes-128-gcm', openssl_get_cipher_methods() ) )
			{
				$obj->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-128-gcm' ) );
				$obj->cipher = openssl_encrypt( $plaintext, 'aes-128-gcm', static::key(), 0, $obj->iv, $obj->tag );
				return $obj;
			}
			
			/* Otherwise, use CBC and store the hash so we can do our own authentication when decrypting */
			elseif ( \in_array( 'aes-128-cbc', openssl_get_cipher_methods() ) )
			{
				$obj->iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-128-cbc' ) );
				$obj->cipher = openssl_encrypt( $plaintext, 'aes-128-cbc', static::key(), OPENSSL_RAW_DATA, $obj->iv );
				$obj->hmac = hash_hmac( 'sha256', $obj->cipher, static::key(), TRUE );
				return $obj;
			}
		}
		
		/* If we're still here, fallback to the PHP library */
		require_once \IPS\ROOT_PATH . '/system/3rd_party/AES/AES.php';
		$obj->cipher = \AesCtr::encrypt( $plaintext, static::key(), 256 );
		return $obj;
	}
	
	/**
	 * From plaintext
	 *
	 * @param	string		$cipher	Cipher
	 * @param	string|null	$iv		The IV, or if null, will use the PHP library rather than built-in openssl_*() methods
	 * @param	string|null	$tag		The tag if using AES-128-GCM
	 * @param	string|null	$hash	The hash if using AES-128-CBC
	 * @return	static
	 */
	public static function fromCipher( $cipher, $iv = NULL, $tag = NULL, $hash = NULL )
	{
		$obj = new static;
		$obj->cipher = $cipher;
		$obj->iv = $iv;
		$obj->tag = $tag;
		$obj->hmac = $hash;
		return $obj;
	}
	
	/**
	 * From tag
	 *
	 * @param	string	$tag	Tag
	 * @return	static
	 */
	public static function fromTag( $tag )
	{
		if ( preg_match( '/^\[\!AES128GCM\[(.+?)\]\[(.+?)\]\[(.+?)\]\]/', $tag, $matches ) )
		{
			return static::fromCipher( $matches[1], hex2bin( $matches[2] ), hex2bin( $matches[3] ) );
		}
		elseif ( preg_match( '/^\[\!AES128CBC\[(.+?)\]\]/', $tag, $matches ) )
		{
			$cipher = base64_decode( $matches[1] );
			$ivLength = openssl_cipher_iv_length('aes-128-cbc');
			
			return static::fromCipher( \substr( $cipher, $ivLength + 32 ), \substr( $cipher, 0, $ivLength ), NULL, \substr( $cipher, $ivLength, 32 ) );
		}
		elseif ( preg_match( '/^\[\!AES\[(.+?)\]\]/', $tag, $matches ) )
		{
			return static::fromCipher( $matches[1] );
		}
		else
		{
			return static::fromPlaintext( $tag );
		}
	}
	
	/**
	 * Wrap in a tag to use later with fromTag
	 *
	 * @return	string
	 */
	public function tag()
	{
		if ( $this->tag )
		{
			return '[!AES128GCM[' . $this->cipher . '][' . bin2hex( $this->iv ) . '][' . bin2hex( $this->tag ) . ']]';
		}
		elseif ( $this->hmac )
		{
			return '[!AES128CBC[' . base64_encode( $this->iv . $this->hmac . $this->cipher ) . ']]';
		}
		else
		{
			return '[!AES[' . $this->cipher . ']]';
		}
	}
	
	/**
	 * Decript
	 *
	 * @return	string
	 */
	public function decrypt()
	{
		if ( $this->tag )
		{
			return openssl_decrypt( $this->cipher, 'aes-128-gcm', static::key(), 0, $this->iv, $this->tag );
		}
		elseif ( $this->hmac )
		{
			$decrypted = openssl_decrypt( $this->cipher, 'aes-128-cbc', static::key(), OPENSSL_RAW_DATA, $this->iv );
			if ( hash_equals( $this->hmac, hash_hmac( 'sha256', $this->cipher, static::key(), TRUE ) ) )
			{
				return $decrypted;
			}
			return '';
		}
		else
		{
			require_once \IPS\ROOT_PATH . '/system/3rd_party/AES/AES.php';
			return \AesCtr::decrypt( $this->cipher, static::key(), 256 );
		}
	}
}
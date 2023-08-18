<?php
/**
 * @brief		URL input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * URL input class for Form Builder
 */
class _Url extends Text
{
	/**
	 * @brief	Default Options
	 * @code
	 	$childDefaultOptions = array(
	 		'allowedProtocols'	=> array( 'http', 'https' ),	// Allowed protocols. Default is http and https. Be careful changing this not to introduce security issues.
	 		'image' 			=> TRUE,						// If TRUE, will check the URL is for an image.
	 		'allowedMimes'		=> \IPS\Image::$imageMimes,		// Sets the allowed mimetype(s). Can be string or array. * is a wildcard. Default is NULL, which allows any mimetypes. If the 'image' option is set, it will already be restricted to images
	 		'file'				=> 'Profile',					// If provided, the contents of the URL will be fetched and written as a file, an \IPS\File object will then be returned rather than a string. Provide the extension name which specifies the storage location to use.
	 		'maxFileSize'		=> NULL,						// If provided along with 'file', the resulting file that is written to disk cannot be greater than this size in megabytes. NULL for no limit (default is NULL).
	 		'maxDimensions'		=> NULL,						// If supplied as an array with keys 'width' and 'height' and the resulting file is an image, the image will be scaled down to these maximum dimensions. NULL for no limits (default is NULL).
	 		'rateLimit'			=> 20,							// Rate-limit requests to prevent the ability to DOS a remote server - time between allowed attempts in seconds (defaults to 20). NULL to disable rate limiting.
	 	);
	 * @endcode
	 * @note You should NOT use file/image/allowedMimes options for public-facing forms. These options will result in the file being imported, and exposing such a form to end users opens up the community to potential SSRF concerns.
	 */
	public $childDefaultOptions = array(
		'allowedProtocols'	=> array( 'http', 'https' ),
		'allowedMimes'		=> NULL,
		'image'				=> FALSE,
		'file'				=> FALSE,
		'maxFileSize'		=> NULL,
		'maxDimensions'		=> NULL,
		'rateLimit'			=> 20
	);
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\DomainException
	 * @return	TRUE
	 * @note	Custom validation is performed AFTER all standard validations to ensure consistent error messages in places like importing profile photos (if an attacker was attempting to supply a local address). This means that if the form helper's 'file' childoption is set, the value supplied to the custom helper will be an instance of \IPS\File instead of \IPS\Http\Url. Also, a second param $response is made available if you need to check the value of the URL's response and file, allowedMimes, or image was set (preventing a second unnecessary HTTP request).
	 */
	public function validate()
	{
		/* If we have a custom validation function, store it now and we'll run it manually afterwards */
		$validationFunction = NULL;
		if( $this->customValidationCode !== NULL )
		{
			$validationFunction = $this->customValidationCode;
			$this->customValidationCode = NULL;
		}

		parent::validate();
		
		$response = NULL;

		if ( $this->value )
		{
			$value = $this->formatValue();
			
			/* Check the URL is valid */
			if ( !( $value instanceof \IPS\Http\Url ) )
			{
				throw new \InvalidArgumentException('form_url_bad');
			}
			
			/* And that it's an allowed protocol */
			if ( $this->options['allowedProtocols'] and !\in_array( mb_strtolower( $value->data['scheme'] ), $this->options['allowedProtocols'] ) )
			{
				throw new \DomainException('form_url_bad_protocol');
			}
			
			/* Try to fetch it, if necessary */
			if ( $this->options['file'] or $this->options['allowedMimes'] or $this->options['image'] )
			{
				/* Is rate limiting enabled (the default)? */
				if( $this->options['rateLimit'] !== NULL AND \intval( $this->options['rateLimit'] ) > 0 )
				{
					if( $rateLimit = $this->getRateLimitValue() )
					{
						/* Was it less than 20 seconds ago? If so, make the user wait */
						$timeLeft =  $rateLimit - ( time() - \intval( $this->options['rateLimit'] ) );
						if( $timeLeft > 0 )
						{
							throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_too_soon', FALSE, array( 'sprintf' => array( $this->options['rateLimit'], $timeLeft ) ) ) );
						}
					}

					$this->setRateLimitValue( time() );
				}
				
				/* Make the request */
				try
				{
					$response = $value->request( null, null, 5, $this->options['allowedProtocols'] )->get();
				}
				catch ( \IPS\Http\Request\Exception $e )
				{
					if( $e->getMessage() === 'localhost_url_not_followed' )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_localhost', FALSE, array( 'sprintf' => array( \IPS\Http\Url::internal('')->data['host'] ) ) ) );
					}

					if( $e->getMessage() === 'protocol_not_followed' )
					{
						throw new \DomainException('form_url_bad_protocol');
					}
					
					throw new \DomainException( 'form_url_error' );
				}

				/* Check MIME */
				if ( $this->options['allowedMimes'] or $this->options['image'] )
				{
					$allowedMimes = $this->options['allowedMimes'] ? ( \is_array( $this->options['allowedMimes'] ) ? $this->options['allowedMimes'] : array( $this->options['allowedMimes'] ) ): \IPS\Image::$imageMimes;
					
					$match = FALSE;
                    $contentType = ( isset( $response->httpHeaders['Content-Type'] ) ) ? $response->httpHeaders['Content-Type'] : ( ( isset( $response->httpHeaders['content-type'] ) ) ? $response->httpHeaders['content-type'] : NULL );
					if( $contentType )
					{
						foreach ( $allowedMimes as $mime )
						{
							if ( preg_match( '/^' . str_replace( '~~', '.+', preg_quote( str_replace( '*', '~~', $mime ), '/' ) ) . '$/i', $contentType ) )
							{
								$match = TRUE;
								break;
							}
						}
					}
					
					if ( !$match )
					{
						throw new \DomainException( 'form_url_bad_mime' );
					}
				}
				
				/* Max file size */
				if( $this->options['maxFileSize'] !== NULL )
				{
					$maxFileSize	= $this->options['maxFileSize'] * 1048576;

					if( \strlen( $response ) > $maxFileSize )
					{
						unset( $response );
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'upload_too_big', TRUE, array( 'sprintf' => $this->options['maxFileSize'] ) ), 2 );
					}
				}
								
				/* Image check and resize if necessary */
				if ( $this->options['image'] or $this->options['maxDimensions'] !== NULL )
				{
					try
					{
						$image = \IPS\Image::create( $response );

						if ( $this->options['maxDimensions'] !== NULL )
						{
							$image->resizeToMax( $this->options['maxDimensions']['width'] ?: NULL, $this->options['maxDimensions']['height'] ?: NULL );
							$response = (string) $image;
						}
					}
					catch ( \Exception $e )
					{
						if ( $this->options['image'] )
						{
							throw new \DomainException( 'form_url_bad_mime' );
						}
					}
				}
				
				/* Write file if necessary */
				if ( $this->options['file'] )
				{
					$filename = preg_replace( "/(.+?)(\?|$)/", "$1", mb_substr( $value, mb_strrpos( $value, '/' ) + 1 ) );

					try
					{
						$this->value = \IPS\File::create( $this->options['file'], $filename, $response );
					}
					catch( \InvalidArgumentException $e )
					{
						throw new \DomainException( 'form_url_error' );
					}
				}
			}
		}

		/* Now run our validation */
		if( $validationFunction !== NULL )
		{
			$validationFunction( $this->value, $response );
			$this->customValidationCode = $validationFunction;
		}

		return TRUE;
	}
	
	/**
	 * Get the stored rate limit for this field and member
	 * We use sessions if we're a member to avoid adding data to cache_store
	 *
	 * @return int
	 */
	protected function getRateLimitValue()
	{
		if ( \IPS\Member::loggedIn()->member_id )
		{
			return ( isset( $_SESSION[ 'url_fetch_' . $this->htmlId ] ) ? $_SESSION[ 'url_fetch_' . $this->htmlId ] : NULL );
		}
		else
		{
			$cached = NULL;
			try
			{
				$cached = \IPS\Data\Cache::i()->getWithExpire( 'url_fetch_' . $this->htmlId . '-' . \IPS\Member::loggedIn()->ip_address, TRUE );
			}
			catch( \Exception $ex ) { }
			
			return $cached;
		}
	}
	
	/**
	 * Set the stored rate limit for this field and member
	 * We use sessions if we're a member to avoid adding data to cache_store
	 *
	 * @param	mixed	$value	The value to store
	 *
	 * @return void
	 */
	protected function setRateLimitValue( $value )
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$_SESSION[ 'url_fetch_' . $this->htmlId ] = $value;
		}
		else
		{
			\IPS\Data\Cache::i()->storeWithExpire( 'url_fetch_' . $this->htmlId . '-' . \IPS\Member::loggedIn()->ip_address, $value, \IPS\DateTime::create()->add( new \DateInterval( 'PT60M' ) ), TRUE );
		}
	}
	
	/**
	 * Get Value
	 *
	 * @return	string
	 */
	public function getValue()
	{
		$val = str_replace( 'feed://', 'http://', parent::getValue() );
		if ( $val and !mb_strpos( $val, '://' ) )
		{
			$val = "http://{$val}";
		}
		
		return $val;
	}
	
	/**
	 * Format Value
	 *
	 * @return	\IPS\Http\Url|string
	 */
	public function formatValue()
	{
		if ( $this->value and !( $this->value instanceof \IPS\Http\Url ) )
		{
			try
			{
				return \IPS\Http\Url::createFromString( $this->value, TRUE, TRUE );
			}
			catch ( \InvalidArgumentException $e )
			{
				return $this->value;
			}
		}
		
		return $this->value;
	}
}
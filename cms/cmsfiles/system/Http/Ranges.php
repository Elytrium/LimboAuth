<?php
/**
 * @brief		HTTP Ranges Request/Response Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 May 2014
 * @note		This class facilitates parsing of HTTP Range requests and sending the appropriate response
 */

namespace IPS\Http;
 
/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * HTTP Ranges Request/Response Class
 */
class _Ranges extends Response
{
	/**
	 * @brief	File object
	 */
	public $file	= NULL;

	/**
	 * @brief	Is this a range request?
	 */
	public $ranged	= FALSE;

	/**
	 * Constructor
	 *
	 * @param	\IPS\File	$file		File object we are sending
	 * @param	int			$throttle	Throttle speed (kb/sec)
	 * @return	void
	 */
	public function __construct( $file, $throttle=0 )
	{
		/* Send some headers */
		\IPS\Output::i()->sendHeader( 'Accept-Ranges: bytes' );
		\IPS\Output::i()->sendHeader( "Content-Transfer-Encoding: binary" );

		/* We will need the file size */
		$filesize		= $file->filesize();
		$length			= $filesize;
		$range			= 0;
		$start_range	= 0;

		\IPS\Log::debug( "Range response initiated", 'ranges' );

		/* Was this a ranged request? */
		if( isset( $_SERVER['HTTP_RANGE'] ) ) 
		{
			\IPS\Log::debug( "This is a range request: " . $_SERVER['HTTP_RANGE'], 'ranges' );

			/* Get the range */
			list( $a, $range )	= explode( "=", $_SERVER['HTTP_RANGE'] );

			/* Check for multiple ranges */
			$ranges				= explode( ",", $range );
			
			/* Did we only find one range? */
			if( \count($ranges) == 1 )
			{
				\IPS\Log::debug( "Only one range detected", 'ranges' );

				/* Get the actual range start and end */
				list( $start_range, $end_range )	= explode( "-", $range );
	
				/* If there is no start, we want the last x bytes */
				if( \strlen( $start_range ) == 0 )
				{
					$size	= $filesize - 1;
					$length = $end_range + 1;
				}
				/* Or did we want the first x bytes? */
				else if( \strlen( $end_range ) == 0 )
				{
					$size	= $filesize - 1;
					$length	= $size - $start_range + 1;
				}
				/* Or a specific range with start and end specified */
				else
				{
					$size	= $end_range;
					$length	= $end_range - $start_range + 1;
				}

				/* If the range is invalid we need to send a 416 header and let the client know the valid range */
				if( $start_range > $size OR $end_range > $size )
				{
					\IPS\Log::debug( "Invalid range, responding with 416 header.  Requested {$start_range} - {$end_range} out of {$size}", 'ranges' );

					\IPS\Output::i()->sendStatusCodeHeader( 416 );
					\IPS\Output::i()->sendHeader( "Content-Range: */{$filesize}" );
					return;
				}
				/* Otherwise send the 206 header */
				else
				{
					/* If the end range is larger than the filesize, correct the end range to the filesize */
					if ( $end_range > $filesize )
					{
						$size = $filesize - 1;
					}
					
					\IPS\Log::debug( "Sending requested range with 206 header: {$start_range} - {$size} out of {$filesize}", 'ranges' );

					\IPS\Output::i()->sendStatusCodeHeader( 206 );
					\IPS\Output::i()->sendHeader( "Content-Range: bytes {$start_range}-{$size}/{$filesize}" );
				}
				
				/* A couple more headers for good measure */
				if( !ini_get('zlib.output_compression') OR ini_get('zlib.output_compression') == 'off' )
				{
					\IPS\Output::i()->sendHeader( "Content-Length: " . $filesize );
				}
			}
			/* Or did they get froggy and request multiple ranges? */
			else
			{
				/* Start parsing the ranges */
				$the_responses		= array();
				
				foreach( $ranges as $arange )
				{
					\IPS\Log::debug( "Multiple ranges: {$start_range} - {$end_range}", 'ranges' );

				    /* Get the actual range start and end */
					list( $start_range, $end_range ) 	= explode( "-", $arange );
					
					/* If there is no start, we want the last x bytes */
					if( \strlen( $start_range ) == 0 )
					{
						$size	= $filesize - 1;
						$length = $end_range + 1;
					}
					/* Or did we want the first x bytes? */
					else if( \strlen( $end_range ) == 0 )
					{
						$size	= $filesize - 1;
						$length	= $size - $start_range + 1;
					}
					/* Or a specific range with start and end specified */
					else
					{
						$size	= $end_range;
						$length	= $end_range - $start_range + 1;
					}

					/* If the range is invalid we need to send a 416 header and let the client know the valid range */
					if( $start_range > $size OR $end_range > $size )
					{
						\IPS\Log::debug( "This range out of multiple ranges was invalid", 'ranges' );

						\IPS\Output::i()->sendStatusCodeHeader( 416 );
						\IPS\Output::i()->sendHeader( "Content-Range: */{$filesize}" );
						return;
					}
					/* Otherwise store this range */
					else
					{
						$the_responses[] 	= array( $start_range, $size, $length );
					}
				}

				/* Did we still only have one range? */
				if( \count($the_responses) == 1 )
				{
					$length			= $the_responses[0][2];
					$start_range	= $the_responses[0][0];
					$size			= $the_responses[0][1];

					$the_responses	= array();

					\IPS\Log::debug( "One range out of multiple ranges to respond: {$start_range} - {$size} out of {$filesize}", 'ranges' );

					\IPS\Output::i()->sendStatusCodeHeader( 206 );
					\IPS\Output::i()->sendHeader( "Content-Range: bytes {$start_range}-{$size}/{$filesize}" );

					/* A couple more headers for good measure */
					if( !ini_get('zlib.output_compression') OR ini_get('zlib.output_compression') == 'off' )
					{
						\IPS\Output::i()->sendHeader( "Content-Length: " . $filesize );
					}
				}
				/* Now we're working with something exciting */
				else if( \count($the_responses) > 1 )
				{
					$content_length	= 0;
					
					\IPS\Log::debug( "Multiple range responses, using boundary", 'ranges' );

					foreach( $the_responses as $part )
					{
						$content_length	+= \strlen( "\r\n--IPDOWNLOADSBOUNDARYMARKER\r\n" );
						$content_length	+= \strlen( "Content-Type: " . \IPS\File::getMimeType( $file->originalFilename ) . "\r\n" );
						$content_length	+= \strlen( "Content-Range: bytes {$part[0]}-{$part[1]}/{$filesize}\r\n\r\n" );
						$content_length	+= $part[2];
					}
					
					$content_length	+= \strlen( "\r\n--IPDOWNLOADSBOUNDARYMARKER--\r\n" );
					
					\IPS\Output::i()->sendStatusCodeHeader( 206 );
					\IPS\Output::i()->sendHeader( "Content-Type: multipart/x-byteranges; boundary=IPDOWNLOADSBOUNDARYMARKER" );

					if( !ini_get('zlib.output_compression') OR ini_get('zlib.output_compression') == 'off' )
					{
						\IPS\Output::i()->sendHeader( "Content-Length: " . $content_length );
					}
				}
			}
		}
		/* No range */
		else
		{
			\IPS\Log::debug( "No range requested, sending entire file", 'ranges' );

			$size = $filesize - 1;

			\IPS\Output::i()->sendStatusCodeHeader( 200 );

			if( !ini_get('zlib.output_compression') OR ini_get('zlib.output_compression') == 'off' )
			{
				\IPS\Output::i()->sendHeader( "Content-Length: " . $filesize );
			}

			\IPS\Output::i()->sendHeader( "Content-Range: bytes 0-{$size}/{$filesize}" );
		}
		
		/* Not sending multiple ranges */
		if( !isset( $the_responses ) OR !\count( $the_responses ) )
		{
			\IPS\Output::i()->sendHeader( 'Content-Type: ' . \IPS\File::getMimeType( $file->originalFilename ) );
			\IPS\Output::i()->sendHeader( 'Content-Disposition: ' . \IPS\Output::getContentDisposition( 'attachment', $file->originalFilename ) );
		}

		/* Turn off output buffering if it is on */
		while( ob_get_level() > 0 )
		{
			ob_end_clean();
		}
		
		/* Throttling? */
		$throttle	= ( $throttle > 0 ) ? ( $throttle * 1024 ) : NULL;

		/* Do we have multiple ranges? */
		if( isset( $the_responses ) AND \count($the_responses) )
		{
			/* Loop over each range */
			foreach( $the_responses as $part )
			{
				$length = $part[2];

				echo "\r\n--IPDOWNLOADSBOUNDARYMARKER\r\n";
				echo "Content-Type: " . \IPS\File::getMimeType( $file->originalFilename ) . "\r\n";
				echo "Content-Range: bytes {$part[0]}-{$part[1]}/{$filesize}\r\n\r\n";

				$file->printFile( $part[0], $part[2], $throttle );
			}

			echo "\r\n--IPDOWNLOADSBOUNDARYMARKER--\r\n";
		}
		/* Just a single range? */
		else
		{
			$file->printFile( $start_range, $length, $throttle );
		}
	}

	/**
	 * Determine if this is a range request for the start of the file
	 *
	 * @return	bool
	 */
	public static function isStartOfFile()
	{
		/* Was this a ranged request? */
		if( isset( $_SERVER['HTTP_RANGE'] ) ) 
		{
			/* Get the range */
			list( $a, $range )	= explode( "=", $_SERVER['HTTP_RANGE'] );

			/* Check for multiple ranges */
			$ranges				= explode( ",", $range );
			
			/* Did we only find one range? */
			if( \count($ranges) == 1 )
			{
				/* Get the actual range start and end */
				list( $start_range, $end_range )	= explode( "-", $range );
	
				if( \strlen( $start_range ) == 0 )
				{
					return TRUE;
				}
				else if( \strlen( $end_range ) == 0 )
				{
					return FALSE;
				}
				else
				{
					return ( $start_range == 0 ) ? TRUE : FALSE;
				}
			}
			/* Or did they get froggy and request multiple ranges? */
			else
			{
				foreach( $ranges as $arange )
				{
				    /* Get the actual range start and end */
					list( $start_range, $end_range ) 	= explode( "-", $arange );
					
					if( \strlen( $start_range ) == 0 )
					{
						return TRUE;
					}
					else if( \strlen( $end_range ) == 0 )
					{
						continue;
					}
					else
					{
						if( $start_range == 0 )
						{
							return TRUE;
						}
					}
				}

				return FALSE;
			}
		}
		/* No range */
		else
		{
			return TRUE;
		}
	}
}
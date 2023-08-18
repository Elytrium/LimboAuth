<?php
/**
 * @brief		Incoming email parsing and routing
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 June 2013
 */

namespace IPS\Email\Incoming;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PHP Email Class
 * @see	<a href="https://tools.ietf.org/html/rfc5322">RFC 5322</a>
 */
class _Email
{
	/**
	 * @brief	"To" email addresses
	 */
	public $to = array();
	
	/**
	 * @brief	"CC" email addresses
	 */
	public $cc = array();

	/**
	 * @brief	"From" email address
	 */
	public $from = '';
	
	/**
	 * @brief	Subject
	 */
	public $subject = '';
	
	/**
	 * @brief	Message (sanatised HTML)
	 */
	public $message = '';
	
	/**
	 * @brief	Quoted part of the message (sanatised HTML)
	 */
	public $quote = '';
	
	/**
	 * @brief	Attachments
	 */
	public $attachments = array();

	/**
	 * @brief	Existing attachments to store a map for
	 */
	public $mappedAttachments = array();
	
	/**
	 * @brief	Raw email headers
	 */
	public $headers = array();
	
	/**
	 * @brief	Raw email contents
	 */
	public $raw = '';
	
	/**
	 * Constructor
	 *
	 * @param	string	$contents	Contents
	 * @return	void
	 */
	public function __construct( $contents )
	{
		/* Extract the raw data */
	    $this->raw = $contents;
	    $data = $this->_decodePart( $contents );
	    
	    /* Parse headers into something we can use */
	    $this->headers = $data['headers'];
	    $this->_parseBasicHeaders( $data['headers'] );
	    
	    /* Get the message contents */
	    $this->_parseMessagePart( $data );
	}
	
	/* !Raw Email Parsing */
	
	/**
	 * Decode message part
	 *
	 * @param	string	$contents	Contents
	 * @return	array	headers, parts, body
	 */
	protected function _decodePart( $contents )
	{		
		/* Separate header and body */
		preg_match( "/^(.*?)\r?\n\r?\n(.*)/s", $contents, $matches );
		$header = $matches[1];
		$body = $matches[2];
		
		/* Unfold headers (see section 2.2.3 of the RFC) */
		$header = preg_replace( "/\r?\n/", "\r\n", $header );
		$header = preg_replace( "/=\r\n(\t| )+/", '= ', $header );
		$header = preg_replace( "/\r\n(\t| )+/", ' ', $header );
		
		/* Parse headers */
		$headers = array();
		foreach( explode( "\r\n", trim( $header ) ) as $line )
		{
			/* Extract the value */
			$colonPosition = mb_strpos( $line, ':' );
			$headerName = mb_strtolower( mb_substr( $line, 0, $colonPosition ) );
			$headerValue = $this->_decodeHeaderValue( mb_substr( $line, $colonPosition + ( ( mb_substr( $line, $colonPosition + 1, 1 ) === ' ' ) ? 2 : 1 ) ) );
			
			/* Decode the value */			
			/* Save */
			if ( isset( $headers[ $headerName ] ) )
			{
				if ( \is_array( $headers[ $headerName ] ) )
				{
					$headers[ $headerName ][] = $headerValue;
				}
				else
				{
					$headers[ $headerName ] = array( $headers[ $headerName ], $headerValue );
				}
			}
			else
			{
				$headers[ $headerName ] = $headerValue;
			}
		}
				
		/* Work out the content type */
		if ( isset( $headers['content-type'] ) and \is_string( $headers['content-type'] ) )
		{
			$contentType = $this->_parseStructuredHeader( $headers['content-type'] );
		}
		else
		{
			$contentType = array( 'value' => 'text/plain', 'extra' => array() );
		}

		/* Parse body: Multipart */
		if ( \in_array( $contentType['value'], array( 'multipart/parallel', 'multipart/appledouble', 'multipart/report', 'multipart/signed', 'multipart/digest', 'multipart/alternative', 'multipart/related', 'multipart/mixed', 'application/vnd.wap.multipart.related' ) ) and isset( $contentType['extra']['boundary'] ) )
		{
			$parts = array();

			/* Sometimes the email will start with "This is a multi-part message in MIME format" before the first part, so we need to strip that */
			$body = preg_replace( "/(.*?)(--" . preg_quote( $contentType['extra']['boundary'], '/') . ".*)$/s", "$2", $body );

			foreach ( array_filter( preg_split( "/--" . preg_quote( $contentType['extra']['boundary'], '/') . "((?=\s)|--)/", $body ) ) as $part )
			{
				if ( trim( $part ) )
				{
					$parts[] = $this->_decodePart( $part );
				}
			}

			return array( 'headers' => $headers, 'parts' => $parts );
		}
		
		/* Parse body: Included message */
		elseif ( $contentType['value'] == 'message/rfc822' )
		{
			return array( 'headers' => $headers, 'parts' => array( $this->_decodePart( $body ) ) );
		}
		
		/* Parse body: Normal */
		else
		{
			/* Work out the encoding */
			$encoding = null;
			if ( isset( $headers['content-transfer-encoding'] ) )
			{
				$encoding = trim( mb_strtolower( $headers['content-transfer-encoding'] ) );
				if ( mb_strpos( $encoding, ';' ) !== FALSE )
				{
					$encoding = trim( mb_substr( $encoding, mb_strpos( $encoding, ';' ) ) );
				}
			}
			
			/* Decode it */
			if ( $encoding == 'quoted-printable' )
			{
				$body = preg_replace( "/=\r?\n/", '', $body );
				
				$body = preg_replace_callback( '/=([a-f0-9]{2})/i', function( $matches )
				{
					return \chr( hexdec( $matches[1] ) );
				}, $body );
			}
			elseif( $encoding == 'base64' )
			{
				$body = base64_decode( $body );
			}
			
			/* Add it to the array */
			return array( 'headers' => $headers, 'body' => $body );
		}
	}
	
	/**
	 * Decode header value
	 *
	 * @param	string	$headerValue	Value
	 * @return	array
	 */
	protected function _decodeHeaderValue( $headerValue )
	{
		$headerValue = preg_replace( '/(=\?[^?]+\?(q|b)\?[^?]*\?=)(\s)+=\?/i', '\1=?', $headerValue );
        while ( preg_match( '/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i', $headerValue, $matches ) )
        {
            $encoded  = $matches[1];
            $charset  = $matches[2];
            $encoding = $matches[3];
            $text     = $matches[4];

            switch ( mb_strtolower( $encoding ) )
            {
                case 'b':
                    $text = base64_decode( $text );
                    break;

                case 'q':
                    $text = str_replace( '_', ' ', $text );
                    preg_match_all( '/=([a-f0-9]{2})/i', $text, $matches );
                    foreach( $matches[1] as $value )
                    {
						$text = str_replace( '=' . $value, \chr( hexdec( $value ) ), $text );
					}
                    break;
            }

			/* We need to convert the text to UTF-8 if it is not already */
			if ( mb_strtolower( $charset ) != 'utf-8' and ( in_array( mb_strtolower( $charset ), array_map( 'mb_strtolower', mb_list_encodings() ) ) ) )
			{
				$text = mb_convert_encoding( $text, 'UTF-8', mb_strtoupper( $charset ) );
			}

            $headerValue = str_replace( $encoded, $text, $headerValue );
        }
        return $headerValue;
	}
	
	/**
	 * Parse a structured header
	 * i.e. foo="bar";baz="moo"
	 * e.g. Content-Type: multipart/alternative; boundary=xxxxxx
	 *
	 * @param	string	$value	Value
	 * @return	array
	 */
	protected function _parseStructuredHeader( $value )
	{
		$extra = array();
		$semiColonPosition = mb_strpos( $value, ';' );
		if ( $semiColonPosition !== false )
		{
			foreach ( explode( ';', trim( mb_substr( $value, $semiColonPosition + 1 ) ) ) as $extraPart )
			{
				$equalsPosition = mb_strpos( $extraPart, '=' );
				$extra[ trim( mb_substr( $extraPart, 0, $equalsPosition ) ) ] = trim( mb_substr( $extraPart, $equalsPosition + 1 ), " \t\n\r\0\x0B'\"" );
			}
			$value = trim( mb_substr( $value, 0, $semiColonPosition ) );
		}
		
		return array( 'value' => mb_strtolower( $value ), 'extra' => $extra );
	}
		
	/* !Secondary Email parsing (extract common headers, get the message body) */
	
	/**
	 * Parse basic headers (to, from, subject, cc)
	 *
	 * @param	array	$headers	Raw headers
	 * @return	void
	 */
	protected function _parseBasicHeaders( $headers )
	{
		/* To */
		if ( $headers['to'] )
		{
			if ( mb_strpos( $headers['to'], ',' ) === FALSE )
			{
				$this->to = array( $headers['to'] );
			}
			/* We need to account for formats like "Doe, John (Contractor)" <a@b.com>, "Doe, Jane" <z@b.com> */
			elseif( mb_strpos( $headers['to'], '"' ) !== FALSE )
			{
				/* The regex here is looking for opening quote, anything except <, comma, anything, closing quote and then stripping the comma so that
					commas are only used to separate the actual addresses */
				$this->to = explode( ',', preg_replace( "/\"([^<]*?),(.*?)\"/", "\"$1 $2\"", $headers['to'] ) );
			}
			else
			{
				$this->to = explode( ',', $headers['to'] );
			}
			$this->to = array_map( array( $this, '_extractEmailAddress' ), $this->to );
		}

		/* From */
		$this->from = $this->_extractEmailAddress( $headers['from'] );

		/* Subject */
		$this->subject = ( (bool) trim( $headers['subject'] ) ) ? $headers['subject'] : \IPS\Member::load( $this->from, 'email' )->language()->get('incoming_email_no_subject');

		/* CC */
		if( isset( $headers['cc'] ) )
		{			
			if( \is_array( $headers['cc'] ) )
			{
				$this->cc = $headers['cc'];
			}
			/* We need to account for formats like "Doe, John (Contractor)" <a@b.com>, "Doe, Jane" <z@b.com> */
			elseif( mb_strpos( $headers['cc'], '"' ) !== FALSE )
			{
				/* The regex here is looking for opening quote, anything except <, comma, anything, closing quote and then stripping the comma so that
					commas are only used to separate the actual addresses */
				$this->cc = explode( ',', preg_replace( "/\"([^<]*?),(.*?)\"/", "\"$1 $2\"", $headers['cc'] ) );
			}
			else
			{
				$this->cc = explode( ",", $headers['cc'] );
			}
			$this->cc = array_map( array( $this, '_extractEmailAddress' ), $this->cc );
		}
	}
	
	/**
	 * Extract the email address from a "Name <email@example.com>" format
	 *
	 * @param	string	$contents	The content to extract from
	 * @return	string
	 */
	protected function _extractEmailAddress( $contents )
	{
		if ( preg_match( "/.+? <(.+?)>/", $contents, $matches ) )
		{
			return $matches[1];
		}
		else
		{
			return trim( trim( $contents ), '<>' );
		}
	}
	
	/**
	 * Parse message part
	 *
	 * @param	array	$data	As returned by _decodePart
	 * @return	void
	 */
	protected function _parseMessagePart( $data )
	{
		/* If this part contains sub-parts, parse recursively... */
		if ( isset( $data['parts'] ) )
		{
			$this->_parseMultipartPart( $data['headers'], $data['parts'] );
		}
				
		/* Otherwise just add to the body */
		elseif ( isset( $data['body'] ) )
		{
			/* If it's text which isn't an attachment, add it */
			$contentType = $this->_parseStructuredHeader( isset( $data['headers']['content-type'] ) ? $data['headers']['content-type'] : 'text/plain' );
			$contentDisposition = isset( $data['headers']['content-disposition'] ) ? $this->_parseStructuredHeader( $data['headers']['content-disposition'] ) : NULL;
			if ( mb_substr( $contentType['value'], 0, 5 ) === 'text/' and ( !$contentDisposition or $contentDisposition['value'] != 'attachment' ) )
			{				
				$body = $this->_parseBodyAsHtml( $data['body'], $contentType );
				$quote = $this->_extractQuoteFromBody( $body );
				$this->message .= $body;
				$this->quote .= $quote;
			}
			
			/* Otherwise make it an attachment */
			else
			{					
				/* If there is no filename, which will often happen with inline disposition, create a random one */
				if( isset( $contentDisposition['extra']['filename'] ) )
				{
					$filename = $contentDisposition['extra']['filename'];
				}
				elseif ( isset( $contentType['extra']['name'] ) )
				{
					$filename = $contentType['extra']['name'];
				}
				else
				{
					$filename = md5( mt_rand() );
				}

				try
				{
					/* Save the file */
					$file = \IPS\File::create( 'core_Attachment', $filename, $data['body'] );
					
					/* Add to the message */
					if ( $file->isImage() )
					{
						if ( isset( $data['headers']['content-id'] ) and $contentId = trim( $data['headers']['content-id'], '<>' ) and isset( $this->imagePlaceholders[ $contentId ] ) )
						{
							$imageTag = "<img src='<fileStore.core_Attachment>/{$file}' class='ipsImage ipsImage_thumbnailed'> ";				
							$this->message = preg_replace( "/<a href=\"#image-placeholder={$this->imagePlaceholders[ $contentId ]}\" rel=\"[^\"]*\"><\/a>/", $imageTag, $this->message );
						}
						else
						{
							$this->message .= "<img src='<fileStore.core_Attachment>/{$file}' class='ipsImage ipsImage_thumbnailed'> ";
						}
					}
					else
					{
						$this->message .= "<a class='ipsAttachLink' href='<fileStore.core_Attachment>/{$file}'>{$file->originalFilename}</a><br><br>";
					}
					
					/* Save to the $attachments array and exit */
					$this->attachments[] = $file;
				}
				catch( \Exception $e )
				{
					\IPS\Log::log( $e, 'incoming_email' );

					/* This could be a corrupt file, which we can't do anything about */
				}
			}
		}
	}
	
	/**
	 * Parse multipart parts
	 *
	 * @param	array	$headers	Raw headers for this part
	 * @param	array	$parts		Raw message sub-parts for this part
	 * @return	void
	 */
	protected function _parseMultipartPart( $headers, $parts )
	{
		$contentType = $this->_parseStructuredHeader( $headers['content-type'] );
		
		/* multipart/alternative is the same content in different formats (usually html and plaintext) */
		if ( $contentType['value'] == 'multipart/alternative' )
		{
			$preferredPart = null;
			
			foreach ( $parts as $part )
			{
				if ( $preferredPart === null )
				{
					$preferredPart = $part;
				}
				else
				{
					$preferredPartContentType = $this->_parseStructuredHeader( $preferredPart['headers']['content-type'] );
					$thisPartContentType = $this->_parseStructuredHeader( $part['headers']['content-type'] );
					
					if ( $preferredPartContentType['value'] == 'text/plain' and $thisPartContentType['value'] != 'text/plain' )
					{
						$preferredPart = $part;
					}
				}
			}
						
			$this->_parseMessagePart( $preferredPart );
		}
		
		/* Everything else (normally multipart/mixed) we'll assume to be broken into multiple parts (for example, attachments) */
		else
		{
			foreach ( $parts as $part )
			{
				$this->_parseMessagePart( $part );
			}
		}
		
	}
	
	/**
	 * @brief Image placeholders
	 */
	protected $imagePlaceholders = array();
	
	/**
	 * Turn body into sanatised HTML for use within Invision Community
	 *
	 * @param	string	$body			Email body
	 * @param	array	$contentType	Content-Type data (as returned by _parseStructuredHeader)
	 * @return	void
	 */
	protected function _parseBodyAsHtml( $body, $contentType )
	{
		/* If it's plaintext, nl2br */
		if ( $contentType['value'] == 'text/plain' )
		{
			$body = nl2br( $body );
		}
				
		/* Convert to UTF-8 if possible */
		if ( isset( $contentType['extra']['charset'] ) and mb_strtolower( $contentType['extra']['charset'] ) != 'utf-8' )
		{
			if( \in_array( mb_strtolower( $contentType['extra']['charset'] ), array_map( 'mb_strtolower', mb_list_encodings() ) ) )
			{
				$body = mb_convert_encoding( $body, 'UTF-8', $contentType['extra']['charset'] );
			}
		}
		
		/* Make any images which we haven't parsed yet placeholders */
		$body = preg_replace_callback( '/<img[^>]*src=[\'"]cid:(.+?)[\'"][^>]*>/', array( $this, '_replaceImageWithPlaceholder' ), $body );
		
		/* Clean */
		$parser = new \IPS\Text\Parser( FALSE, NULL, \IPS\Member::load( $this->from, 'email' ), TRUE, TRUE, TRUE, function( $config ) {
			$config->set( 'HTML.TargetBlank', TRUE );
		} );
		$body = $parser->parse( $body );

		/* Store any mapped attachments that will need a mapping stored in the individual incoming email handlers */
		$this->mappedAttachments = array_merge( $this->mappedAttachments, $parser->mappedAttachments );

		/* Return */
		return $body;
	}
	
	/**
	 * Replace an image with a placeholder to be replaced later when it is parsed as an attachment
	 *
	 * @param	array	$matches	Matches from preg_replace
	 * @return	string
	 */
	protected function _replaceImageWithPlaceholder( $matches )
	{
		$id = mt_rand();
		$this->imagePlaceholders[ $matches[1] ] = $id;
		return '<a href="#image-placeholder=' . $id . '" rel=""></a>';
	}
	
	/**
	 * Extract quote from message body
	 *
	 * @param	string	$body			Email body (the quote will be removed from it and returned)
	 * @return	string
	 */
	protected function _extractQuoteFromBody( &$body )
	{
		/* Init */
		$_quote	= array();
		$_body	= array();
		$_seen	= FALSE;
		$inQuote = FALSE;
		
		/* Split into lines - It is possible the email is formatted with <p></p> and not <br> which can confuse the quote selection */
		if ( mb_stristr( $body, '</p>' ) or mb_stristr( $body, '</div>' ) )
		{			
			$body = preg_replace( '#<(p|div)[^>]*?>#', '', $body );
			$body = preg_replace( '#</(p|div)>#', '<br>', $body );
		}		
		$exploded = explode( "<br />", $body );
		if ( \count( $exploded ) === 1 )
		{
			$exploded = explode( '<br>', $body );
		}
		
		/* Loop over each line */		
		foreach ( $exploded as $k => $line )
		{
			$line = trim( $line );

			if ( mb_substr( $line, 0, 1 ) == '>' )
			{
				$line = ltrim( $line, '> ' );
				
				/* If we are just now hitting a quote line, go back one line to see if it's a "on .. wrote:" line */
				if( !$_seen )
				{
					/* Get the last 2 lines we pushed to the body block.  Sometimes you have "on ... wrote:" followed by an empty line. */
					$_last	= array_pop($_body);
					$_last2	= array_pop($_body);

					$_check	= ( !trim($_last) ) ? $_last2 : $_last;

					/* If it ends with a colon push it to the quote block instead, otherwise put it back */
					if( mb_substr( trim($_check), -1 ) == ':' )
					{
						$_quote[]	= $_last;
						$_quote[]	= $_last2;
					}
					else
					{
						$_body[]	= $_last;
						$_body[]	= $_last2;
					}

					/* Don't do this again */
					$_seen	= true;
				}

				$_quote[]	= $line;
			}
			else
			{
				if ( !$inQuote and preg_match( '/(\s|^)-* ?((Original)|(Forwarded)) Message:? ?-*/i', $line ) )
				{
					$line = preg_replace( '/-* ?(Begin )?((Original)|(Forwarded)) Message:? ?-*/i', '', $line );
					$inQuote = TRUE;
				}
				
				if ( $inQuote )
				{
					$_quote[] = $line;
				}
				else
				{
					$_body[] = $line;
				}
			}
		}

		$quote = implode( "<br>", $_quote );
		$message = implode( "<br>", $_body );
		
		/* Parse out <blockquote> tags which are typically used in HTML emails.  Remember that blockquotes
			can be nested and that our own quote routine uses blockquotes, so we have to be careful.  Look for data-ips* attributes to try to weed out our own. */
		if( mb_strpos( $message, '</blockquote>' ) !== FALSE )
		{
			/* First get the position of the last closing blockquote tag. The "13" here is "</blockquote>". */
			$_lastClosingBlockquote	= mb_strrpos( $message, "</blockquote>" ) + 13;
			
			/* Now get the position of the first opening blockquote tag */
			preg_match( '/<blockquote(?! data-ips).+?>/s', $message, $matches, PREG_OFFSET_CAPTURE );
						
			if( $matches[0][1] )
			{						
				/* Check for common "on x so and so wrote:" type lines preceeding this position */
				preg_match_all( '/<div([^>]+?)?>((?!<div).)*:(<br(>| \/>))*\s*<\/div>/sU', $message, $possibleHeaders, PREG_OFFSET_CAPTURE );
				if ( \count( $possibleHeaders ) )
				{
					foreach ( array_reverse( $possibleHeaders[0] ) as $header )
					{
						if ( $header[1] < $matches[0][1] and mb_strpos( mb_substr( $message, $header[1], $matches[0][1] - $header[1] ), "\n" ) === FALSE )
						{
							$matches[0][1]	= $header[1];
							break;
						}
					}
				}
								
				preg_match( '/<div class=[\'"][^>]*?quote[^>]*?[\'"]>(.*?):(<br(>| \/>))?\s*<blockquote(?! data-ips).+?>/s', $message, $header, PREG_OFFSET_CAPTURE );
				if( !empty($header) AND $header[1][1] < $matches[0][1] )
				{
					$matches[0][1]	= $header[1][1];
				}

				/* Now take everything between these positions and move into the quoted content. substr() rather than mb_substr() because the regex we used to get the offset isn't multibyte aware */
				$quote = \substr( $message, $matches[0][1], ( $_lastClosingBlockquote - $matches[0][1] ) );

				/* And finally, remove the quoted stuff from our email message. substr() rather than mb_substr() because the regex we used to get the offset isn't multibyte aware */
				$message = \substr( $message, 0, $matches[0][1] ) . mb_substr( $message, $_lastClosingBlockquote );
			}
		}
		
		/* Make sure each section is valid HTML */
		$body = $message;
		if ( $quote )
		{
			/* Because we have already parsed the body we have to swap these back otherwise HTMLPurifier will think they're invalid
				HTML. The reparsing we do below will put them back automatically. */
			$body = str_replace( '<___base_url___>', rtrim( \IPS\Settings::i()->base_url, '/' ), $body );
			$quote = str_replace( '<___base_url___>', rtrim( \IPS\Settings::i()->base_url, '/' ), $quote );
			
			/* Parse */
			$body = \IPS\Text\Parser::parseStatic( $body, FALSE, NULL, \IPS\Member::load( $this->from, 'email' ) );
			$quote = \IPS\Text\Parser::parseStatic( $quote, FALSE, NULL, \IPS\Member::load( $this->from, 'email' ) );
		}
		
		/* Return */
		return $quote;
	}
	
	/* !Routing */
	
	/**
	 * Route the email and pass off to the appropriate handler
	 *
	 * @return	void
	 */
	public function route()
	{
		/* Ignore auto-responder messages */
		if( $this->isAutoreply() )
		{
			return;
		}
		
		/* Initialize some vars */
		$routed		= FALSE;

		/* Get our filter rules from the database */
		foreach ( \IPS\Db::i()->select( '*', 'core_incoming_emails' ) as $row )
		{
			/* Reset some vars */
			$analyze	= NULL;
			$match		= FALSE;

			/* Field to check */
			switch ( $row['rule_criteria_field'] )
			{
				case 'to':
					$analyze = implode( ',', $this->to );
					break;
					
				case 'from':
					$analyze = $this->from;
					break;
					
				case 'sbjt':
					$analyze = $this->subject;
					break;
					
				case 'body':
					$analyze = $this->message;
					break;
			}

			/* Now check if we match the supplied rule */
			switch ( $row['rule_criteria_type'] )
			{
				case 'ctns':
					$match = (bool) ( mb_strpos( $analyze, $row['rule_criteria_value'] ) !== FALSE );
					break;
					
				case 'eqls':
					if ( mb_strpos( $analyze, ',' ) !== FALSE )
					{
						$match = (bool) \in_array( $analyze, explode( ',', $analyze ) );
					}
					else
					{
						$match = (bool) ( $analyze == $row['rule_criteria_value'] );
					}
					break;
					
				case 'regx':
					$match = (bool) ( preg_match( "/{$row['rule_criteria_value']}/", $analyze ) == 1 );
					break;
			}

			/* Do we have a match? */
			if ( $match === TRUE )
			{
				$routed	= TRUE;
				break;
			}
		}
		
		/* If we are still here, check each app to see if it wants to handle this unrouted incoming email */
		if ( !$routed )
		{
			/* Loop over all apps that have an incoming email extension */
			foreach ( \IPS\Application::appsWithExtension( 'core', 'IncomingEmail', FALSE ) as $dir => $app )
			{
				/* Get all IncomingEmail extension classes for the app */
				$extensions	= $app->extensions( 'core', 'IncomingEmail' );

				if( \count( $extensions ) )
				{
					/* Loop over the extensions */
					foreach( $extensions as $_instance )
					{
						/* And if it returns true, the unrouted email has now been handled.  We can break. */
						if( $routed = $_instance->process( $this ) )
						{
							break;
						}
					}
				}
			}
		}
		
		/* If we are still here, send an "unrouted email" email to the sender */
		if ( !$routed )
		{
			if ( \IPS\Email::hasTemplate( 'core', 'unrouted' ) )
			{
				\IPS\Email::buildFromTemplate( 'core', 'unrouted', $this, \IPS\Email::TYPE_TRANSACTIONAL )->send( $this->from );
			}
		}
	}

	/**
	 * Is this an auto-reply?  Try to detect to prevent auto-reply loops.
	 *
	 * @return	bool
	 * @see		https://github.com/opennorth/multi_mail/wiki/Detecting-autoresponders
	 */
	protected function isAutoreply()
	{
		/* RFC http://tools.ietf.org/html/rfc3834 */
		if( !empty( $this->headers['auto-submitted'] ) AND mb_strtolower( $this->headers['auto-submitted'] ) != 'no' )
		{
			return TRUE;
		}

		/* If any of these headers are present with any values, ignore the email */
		if( !empty( $this->headers['x-autorespond'] ) OR 
			!empty( $this->headers['x-autoreply'] ) OR
			!empty( $this->headers['x-autoreply-From'] ) OR
			!empty( $this->headers['x-mail-autoreply'] )
			)
		{
			return TRUE;
		}

		/* Now we check for a null return-path (which is considered the "proper" way to prevent auto-responses) */
		if( !empty( $this->headers['return-path'] ) AND $this->headers['return-path'] == '<>' )
		{
			return TRUE;
		}

		/* Now check for specific headers with specific values */
		if( !empty( $this->headers['x-autogenerated'] ) AND \in_array( mb_strtolower( $this->headers['x-autogenerated'] ), array( 'forward', 'group', 'letter', 'mirror', 'redirect', 'reply' ) ) )
		{
			return TRUE;
		}
		
		if( !empty( $this->headers['precedence'] ) AND \in_array( mb_strtolower( $this->headers['precedence'] ), array( 'auto_reply', 'list', 'bulk' ) ) )
		{
			return TRUE;
		}

		if( !empty( $this->headers['x-precedence'] ) AND \in_array( mb_strtolower( $this->headers['x-precedence'] ), array( 'auto_reply', 'list', 'bulk' ) ) )
		{
			return TRUE;
		}

		if( !empty( $this->headers['x-fc-machinegenerated'] ) AND mb_strtolower( $this->headers['x-fc-machinegenerated'] ) == 'true' )
		{
			return TRUE;
		}

		if( !empty( $this->headers['x-post-messageclass'] ) AND mb_strtolower( $this->headers['x-post-messageclass'] ) == '9; autoresponder' )
		{
			return TRUE;
		}

		if ( !empty( $this->headers['delivered-to'] ) AND \is_array( $this->headers['delivered-to'] ) )
		{
			foreach( $this->headers['delivered-to'] AS $deliveredTo )
			{
				if ( mb_strtolower( $deliveredTo ) == 'autoresponder' )
				{
					return TRUE;
				}
			}
		}
		
		if( !empty( $this->headers['delivered-to'] ) AND \is_string( $this->headers['delivered-to'] ) AND mb_strtolower( $this->headers['delivered-to'] ) == 'autoresponder' )
		{
			return TRUE;
		}

		/* And finally, some basic checks on the subject line */
		if( mb_stripos( $this->headers['subject'], "out of office: " ) === 0 OR 
			mb_stripos( $this->headers['subject'], "out of office autoreply:" ) === 0 OR
			mb_stripos( $this->headers['subject'], "automatic reply: " ) === 0 OR
			mb_strtolower( $this->headers['subject'] ) === "out of office"
			)
		{
			return TRUE;
		}

		return FALSE;
	}
}
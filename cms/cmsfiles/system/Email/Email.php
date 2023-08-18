<?php
/**
 * @brief		Outgoing Email Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Outgoing Email
 *
 * An object of this class represents an outgoing email preparing to be sent. An object is constructed either by:
 *	`$email = \IPS\Email::buildFromTemplate( ... )`
 * Or, less frequently:
 *	`$email = \IPS\Email::buildFromContent( ... )`
 * 
 * At the time of construction, no parsing (such as language parsing) is done. That is only done when the email is sent:
 *	`$email->send( $member );`
 * It is acceptable to construct a single object and send to multiple members:
 *	`$email->send( $member1 );`
 *	`$email->send( $member2 );`
 * Because no parsing is done until `send()` is called, these two members do not even need to be using the same language.
 *
 * Although this is possible, if sending an email to lots of members, the `mergeAndSend()` method can be used, which assumes
 * tags in the `{{tag}}` format will be used, and provides a way to do a merge send. Though for most outgoing email methods
 * there is essentially no benefit to do this versus lots of `->send()` calls, if using a service like SparkPost, a single
 * API call will be used, which is of course much more efficient. Unlike `send()`, `mergeAndSend()` requires each recipient
 * to be expecting the same language
 *	`$email->mergeAndSend( [ 'user1@example.com' => [ 'member_name' => "User 1" ], 'user2@example.com' => [ 'member_name' => "User 2" ] ], $language );`
 *
 * A handy method for debugging is to output the compiled content:
 *	`echo $email->compileContent( 'html', $member );`
 */
abstract class _Email
{
	/**
	 * @brief	Transaction email (constant)
	 * @note	A single recipient messages that is used operationally, usually in response to a specific action. For example, to reset a password.
	 */
	const TYPE_TRANSACTIONAL = 'transactional';

	/**
	 * @brief	Transaction email (constant)
	 * @note	A notification about something in particular that the user has opted into, but may be sent to multiple users who have also opted to receive notifications for the same thing.
	 */
	const TYPE_LIST = 'list';

	/**
	 * @brief	Transaction email (constant)
	 * @note	A bulk mail sent to multiple recipients that is not in response to something the user has opted in to.
	 */
	const TYPE_BULK = 'bulk';

	/**
	 * @brief	The number of emails that can be sent in one "go"
	 */
	const MAX_EMAILS_PER_GO = \IPS\BULK_MAILS_PER_CYCLE;

	/**
	 * @brief   Use the NoWrapper wrapper (basic HTML container)
	 */
	const WRAPPER_NONE = 0;

	/**
	 * @brief   Use the full email wrapper
	 */
	const WRAPPER_USE = 1;

	/**
	 * @brief   A wrapper has already been applied to the email content ( used in MergeAndSend() )
	 */
	const WRAPPER_APPLIED = 2;
	
	/* !Factory Constructors */
	
	/**
	 * Get the class to use
	 *
	 * @param	string	$type	See TYPE_* constants
	 * @return	string
	 */
	public static function classToUse( $type )
	{
		if( \defined( '\IPS\EMAIL_DEBUG_PATH' ) AND \IPS\EMAIL_DEBUG_PATH )
		{
			return 'IPS\Email\Outgoing\Debug';
		}
		elseif ( \IPS\Settings::i()->sendgrid_api_key and ( \IPS\Settings::i()->mail_method === 'sendgrid' or ( \IPS\Settings::i()->sendgrid_use_for == 2 ) or ( \IPS\Settings::i()->sendgrid_use_for and $type === static::TYPE_BULK ) ) )
		{
			return 'IPS\Email\Outgoing\SendGrid';
		}
		elseif ( \IPS\Settings::i()->mail_method === 'smtp' )
		{
			return 'IPS\Email\Outgoing\Smtp';
		}
		elseif ( \IPS\CIC )
		{
			return 'IPS\Email\Outgoing\IpsCic';
		}
		else
		{
			return 'IPS\Email\Outgoing\Php';
		}
	}

	/**
	 * Whether the IPS CIC email system is used. Only affects IPS Cloud Installs
	 *
	 * @param   string|null     $type       The type of email
	 *
	 * @return bool
	 */
	public static function usingCicEmail( ?string $type ) : bool
	{
		return false;
	}

	/**
	 * Whether an email is registered as blocked by the current email platform (probably only implemented on cloud)
	 *
	 * @param 	string 		$email		The email
	 * @param 	string|null $type		The type of email
	 *
	 * @return 	bool
	 */
	public static function emailIsBlocked( string $email, ?string $type = null ) : bool
	{
		$type = $type ?: static::TYPE_TRANSACTIONAL;
		if ( !is_subclass_of( \get_called_class(), '\IPS\Email' ) )
		{
			return static::classToUse( $type )::emailIsBlocked( $email, $type );
		}
		return false;
	}
	
	/**
	 * Factory
	 *
	 * @param	string	$type	See TYPE_* constants
	 * @return	\IPS\Email
	 */
	protected static function factory( $type )
	{
		$className = static::classToUse( $type );
		switch ( $className )
		{
			case 'IPS\Email\Outgoing\Debug':
				return new \IPS\Email\Outgoing\Debug( \IPS\EMAIL_DEBUG_PATH );
			case 'IPS\Email\Outgoing\SendGrid':
				return new \IPS\Email\Outgoing\SendGrid( \IPS\Settings::i()->sendgrid_api_key );
			case 'IPS\Email\Outgoing\Smtp':
				return new \IPS\Email\Outgoing\Smtp( \IPS\Settings::i()->smtp_protocol, \IPS\Settings::i()->smtp_host, \IPS\Settings::i()->smtp_port, \IPS\Settings::i()->smtp_user, \IPS\Settings::i()->smtp_pass );
			default:
				return new $className;
		}
	}
	
	/**
	 * @brief	Type
	 */
	protected $type;
	
	/**
	 * @brief	HTML Content
	 */
	protected $htmlContent = NULL;
		
	/**
	 * @brief	Plaintext Content
	 */
	protected $plaintextContent = NULL;
	
	/**
	 * Initiate a new custom email based on raw email content.
	 *
	 * @param	string		$subject			    Subject
	 * @param	string		$htmlContent		    HTML Version
	 * @param	string|NULL	$plaintextContent	    Plaintext version. If not provided, one will be built automatically based off $htmlContent.
	 * @param	string		$type				    See TYPE_* constants.
	 * @param	int		    $useWrapper			    See WRAPPER_* constants.
	 * @param	string		$emailKey			    Key used to identify the email, used for tracking purposes
	 * @param	bool		$trackingEnabled	    TRUE by default, pass FALSE to explicitly disable log/view tracking. Useful with mergeAndSend()'s default behavior of rebuilding an already built email
	 * @return	\IPS\Email
	 */
	public static function buildFromContent( $subject, $htmlContent='', $plaintextContent=NULL, $type = NULL, $useWrapper=\IPS\Email::WRAPPER_USE, $emailKey=NULL, $trackingEnabled=TRUE )
	{
		if( !$type )
		{
			if( \IPS\IN_DEV )
			{
				trigger_error( "The email type must be specified when calling buildFromContent()", E_USER_ERROR );
				exit;
			}
			else
			{
				$type = static::TYPE_TRANSACTIONAL;
			}
		}

		if( \IPS\IN_DEV AND \is_bool( $useWrapper ) )
		{
			trigger_error( "The email wrapper must be passed a constant when calling buildFromContent()", E_USER_ERROR );
			exit;
		}

		$email = static::factory( $type );
		$email->type = $type;
		$email->subject = $subject;
		$email->htmlContent = $htmlContent;
		$email->plaintextContent = ( $plaintextContent === NULL ) ? static::buildPlaintextBody( $htmlContent ) : $plaintextContent;
		$email->useWrapper = $useWrapper;
		$email->templateKey = $emailKey;

		if( $trackingEnabled === FALSE )
		{
			$email->trackingCompleted = array( 'html' => TRUE, 'plaintext' => TRUE );
		}

		return $email;
	}
	
	/**
	 * @brief	Template App
	 */
	protected $templateApp;
	
	/**
	 * @brief	Template Key
	 */
	protected $templateKey;
	
	/**
	 * @brief	Template Params
	 */
	protected $templateParams;

	/**
	 * Initiate new email using a template
	 *
	 * @param	string		$app					Application key
	 * @param	string		$key					Email template key
	 * @param	array 		$parameters				Parameters for the template
	 * @param	string		$type					See TYPE_* constants.
	 * @param	bool		$useWrapper			If TRUE, the email will be wrapped in the default wrapper template
	 * @return	\IPS\Email
	 */
	public static function buildFromTemplate( $app, $key, $parameters=array(), $type = NULL, $useWrapper=TRUE )
	{
		if( !$type )
		{
			if( \IPS\IN_DEV )
			{
				trigger_error( "The email type must be specified when calling buildFromTemplate()", E_USER_ERROR );
				exit;
			}
			else
			{
				$type = static::TYPE_TRANSACTIONAL;
			}
		}

		$email = static::factory( $type );
		$email->type = $type;
		$email->templateApp = $app;
		$email->templateKey = $key;
		$email->templateParams = $parameters;
		$email->useWrapper = $useWrapper;
		return $email;
	}
	
	/* !Content Management */
	
	/**
	 * @brief	Subject
	 */
	protected $subject;
	
	/**
	 * @brief	Should the default wrapper template be used?
	 */
	protected $useWrapper = self::WRAPPER_USE;
	
	/**
	 * @brief	Unsubscribe Template App
	 */
	protected $unsubscribeApp;
	
	/**
	 * @brief	Unsubscribe Template Key
	 */
	protected $unsubscribeKey;
	
	/**
	 * @brief	Unsubscribe Template Parameyers
	 */
	protected $unsubscribeParams = array();
		
	/**
	 * Set the unsubscribe data
	 *
	 * @param	string	$app			App name
	 * @param	string	$template		Template name
	 * @param	array	$parameters		Parameters
	 * @return	\IPS\Email
	 */
	public function setUnsubscribe( $app, $template, $parameters = array() )
	{
		$this->unsubscribeApp = $app;
		$this->unsubscribeKey = $template;
		$this->unsubscribeParams = $parameters;
		return $this;
	}

	/**
	 * @brief	Container information used for restricting ads
	 */
	protected $advertisementParams	= NULL;

	/**
	 * Specify a specific container to attempt to load an advertisement from
	 *
	 * @param	string	$className		Node class to restrict to
	 * @param	int		$id				Node id to restrict to
	 * @return	\IPS\Email
	 */
	public function setAdvertisementParameters( $className, $id )
	{
		$this->advertisementParams = array( 'className' => $className, 'id' => $id );

		return $this;
	}

	/**
	 * Return the advertisement HTML to embed into an email
	 *
	 * @param	string	$type	html (default) or plaintext
	 * @return	string
	 */
	public function getAdvertisement( $type='html' )
	{
		if( $advertisement = \IPS\core\Advertisement::loadForEmail( $this->advertisementParams ) )
		{
			return $advertisement->toString( $type, $this );
		}
		else
		{
			return '';
		}
	}

	/**
	 * @brief Flag if we have already added tracking tokens so we do not do it again
	 */
	public $trackingCompleted = array( 'html' => FALSE, 'plaintext' => FALSE );
		
	/**
	 * Compile the content which will actually be sent
	 *
	 * @param	string					$type		'html' or 'plaintext'
	 * @param	\IPS\Member|NULL|FALSE	$member		If the email is going to a member, the member object. Ensures correct language is used and the email starts with "Hi {member}". NULL for no member, FALSE to use "Hi *|member_name|*" for mergeAndSend()
	 * @param	\IPS\Lang|NULL			$language	If provided, will override the $member language
	 * @param	bool					$logViews	If set to FALSE, will skip logging the email and including the view pixel
	 * @return	string
	 */
	public function compileContent( $type, $member = NULL, \IPS\Lang $language = NULL, $logViews = TRUE )
	{
		/* Setting $language as a property is a bit confusing because the email doesn't *have* a language - it could
			change for different recipients, but since the templates expect it as a property we set it here for
			backwards compatibility. Beware that this property should only be used for the sake of giving ::template()
			something to read */
		if ( $language === NULL )
		{
			$language = $member ? $member->language() : \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
		}
		$this->language = $language;
		
		/* $htmlContent or $plaintextContent was set by buildFromContent() */
		if ( $this->htmlContent !== NULL or $this->plaintextContent !== NULL )
		{
			$return = ( $type === 'html' ) ? $this->htmlContent : $this->plaintextContent;
		}
		
		/* Using a template */
		elseif ( $this->templateApp )
		{
			$return = static::template( $this->templateApp, $this->templateKey, $type, array_merge( $this->templateParams, array( $this ) ) );
		}

		/* Check whether we have a subject yet, if not generate it */
		$subject = $this->subject ?? $this->compileSubject( $member ?: NULL, $language );

		/* Wrap in the wrapper if necessary */
		if ( $this->useWrapper == static::WRAPPER_USE )
		{
			/* Compile the unsubscribe link */
			$unsubscribe = '';
			if ( $this->unsubscribeApp )
			{
				$unsubscribe = static::template( $this->unsubscribeApp, $this->unsubscribeKey, $type, array_merge( $this->unsubscribeParams, array( $member, $this ) ) );
			}

			/* Get our picks */
			$ourPicks = NULL;
			if( \IPS\Settings::i()->promote_community_enabled and \IPS\Settings::i()->our_picks_in_email and $this->type !== 'transactional' )
			{
				$ourPicks = \IPS\core\Promote::internalStream( 4 );
			}

			/* Wrap */
			$return = static::template( 'core', 'emailWrapper', $type, array( $subject, $member ?: new \IPS\Member, $return, $unsubscribe, $member === FALSE, '', $this, $ourPicks ) );
		}
		elseif( $this->useWrapper == static::WRAPPER_NONE )
		{
			$return = static::template( 'core', 'emailNoWrapper', $type, array( $subject, $member ?: new \IPS\Member, $return, '', $member === FALSE, '', $this, NULL ) );
		}

		/* Parse language */
		$language->parseEmail( $return );
		
		/* Parse URLs */
		static::parseFileObjectUrls( $return );
		
		/* Add the view tracking pixel and click tracking if appropriate */
		if( $logViews === TRUE AND $this->trackingCompleted[ $type ] !== TRUE )
		{
			if( \IPS\Settings::i()->prune_log_emailstats != 0 )
			{
				/* Click tracking */
				if( $type == 'plaintext' )
				{
					static::addPlaintextClickTracking( $return );
				}
				else
				{
					static::addHtmlClickTracking( $return );
				}
			}
			
			if( \count( \IPS\core\Advertisement::$advertisementIdsEmail ) )
			{
				$imageUrl = (string) \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->base_url, '/' ) . '/applications/core/interface/email/views.php' )->setQueryString( 'ads', implode( ',', \IPS\core\Advertisement::$advertisementIdsEmail ) );
				$return = str_replace( '</body>', "<img width='1' height='1' src='{$imageUrl}' border='0'></body>", $return );
			}

			$this->trackingCompleted[ $type ] = TRUE;
		}

		/* Return */
		return $return;
	}

	/**
	 * Add click tracking to plain text emails
	 *
	 * @param	string	$content	The email content
	 * @return	void
	 */
	protected function addPlaintextClickTracking( &$content )
	{
		$content = preg_replace_callback( '#(?:^|\s|\)|\(|\{|\}|/>|>|\]|\[|;|href=\S)((http|https|news|ftp)://(?:[^<>\)\[\"\s]+|[a-zA-Z0-9/\._\-!&\#;,%\+\?:=]+))#is', function ($matches) {
            
			$url = \IPS\Http\Url::internal( "app=core&module=system&controller=redirect", 'front' )->setQueryString( array(
				'url'		=> trim( $matches[0] ),
				'key'		=> hash_hmac( "sha256", trim( $matches[0] ), \IPS\Settings::i()->site_secret_key . 'r' ),
				'email'		=> 1,
				'type'		=> $this->templateKey
			) );
			
			return (string) $url;
        }, $content );
	}

	/**
	 * Add click tracking to HTML emails
	 *
	 * @param	string	$content	The email content
	 * @return	void
	 */
	protected function addHtmlClickTracking( &$content )
	{
		$document = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
		$document->loadHTML( $content );

		/* Get document links */
		$links = $document->getElementsByTagName( 'a' );

		foreach( $links as $element )
		{
			static::_parseElementForClickTracking( $element, $this->templateKey );
		}

		/* DOMDocument will change href="*|unfollow_link" to href="*%7Cunfollow_link%7C*", so we need to change it back
			so mergeAndSend() can do its thing properly */
 		$content = preg_replace( "/\*%7C([a-z0-9_]+)%7C\*/ims", "*|$1|*", $document->saveHTML() );
	}

	/**
	 * Parse a DOM Element to add click tracking to a link
	 *
	 * @param	\DOMElement	$element		Anchor tag element
	 * @param	string|null	$templateKey	The key for the template, used for logging/tracking
	 * @return	void
	 */
	protected static function _parseElementForClickTracking( $element, $templateKey=NULL )
	{
		/* Ignore any links that are mailto links */
		$href = (string)$element->getAttribute( 'href' );
		if( \str_starts_with( $href, 'mailto' ) )
		{
			return;
		}

		/* If we are working with mergeAndSend() this may not be a full URL (which may be passed as a user param)
			so we need to ignore any exceptions and simply not adjust the link, which should be done manually instead */
		try
		{
			$url = \IPS\Http\Url::internal( "app=core&module=system&controller=redirect", 'front' )->setQueryString( array(
				'url'		=> $href,
				'resource'	=> ( \IPS\Request::i()->resource ) ? 1 : NULL,
				'key'		=> hash_hmac( "sha256", (string) $element->getAttribute('href'), \IPS\Settings::i()->site_secret_key . 'r' ),
				'email'		=> 1,
				'type'		=> $templateKey
			) );

			$element->setAttribute( 'href', (string) $url );
		}
		catch( \IPS\Http\Url\Exception $e ){}
	}
	
	/**
	 * Get subject
	 *
	 * @param	\IPS\Member|NULL	    $member		If the email is going to a member, the member object. Ensures correct language is used and the email starts with "Hi {member}". NULL for no member, FALSE to use "Hi *|member_name|*" for mergeAndSend()
	 * @param	\IPS\Lang|NULL			$language	If provided, will override the $member language
	 * @return	string
	 */
	public function compileSubject( \IPS\Member $member = NULL, \IPS\Lang $language = NULL )
	{
		/* Setting $language as a property is a bit confusing because the email doesn't *have* a language - it could
			change for different recipients, but since the templates expect it as a property we set it here for
			backwards compatibility. Beware that this property should only be used for the sake of giving ::template()
			something to read */
		if ( $language === NULL )
		{
			$language = $member ? $member->language( TRUE ) : \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
		}
		$this->language = $language;
		
		/* Subject was set by buildFromContent() */
		if ( $this->subject !== NULL )
		{
			$return = $this->subject;
		}
		
		/* Using a template */
		elseif ( $this->templateApp )
		{
			$return = trim( static::devProcessTemplate(
				"email__{$this->templateApp}_{$this->templateKey}_subject_" . str_replace( array( ' ', '.', '-', '@' ), '_', $language->short ),
				$language->get( "mailsub__{$this->templateApp}_{$this->templateKey}" ),
				array_merge( $this->templateParams, array( $this ) ),
				'plaintext'
			) );
		}
		
		/* Parse language */
		$language->parseEmail( $return );
		
		/* Return */
		return $return;
	}
	
	/* !Sending */
	
	/**
	 * Compile the raw email content
	 *
	 * @param	mixed		$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed		$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed		$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	NULL|string	$fromEmail			The email address to send from. If NULL, default setting is used
	 * @param	NULL|string	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array		$additionalHeaders	Additional headers to send
	 * @param	string		$eol				EOL character to use
	 * @param	int|NULL	$lineLimit			Maximum line length
	 * @return	string
	 */
	public function compileFullEmail( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array(), $eol = "\r\n", $lineLimit = 998 )
	{		
		$boundary = "--==_mimepart_" . md5( mt_rand() );
		
		$return = '';
		
		foreach ( $this->_compileHeaders( $this->compileSubject( static::_getMemberFromRecipients( $to ) ), $to, $cc, $bcc, $fromEmail, $fromName, $additionalHeaders, $boundary ) as $k => $v )
		{
			$line = "{$k}: {$v}";
			if ( $lineLimit )
			{
				$line = wordwrap( $line, $lineLimit, $eol );
			}
			
			$return .= $line . $eol;
		}
		
		$return .= $eol;
		$return .= $eol;
		
		$return .= $this->_compileMessage( static::_getMemberFromRecipients( $to ), $boundary, $eol, $lineLimit );

		return str_replace( "\n", $eol, str_replace( [ "\r\n", "\r" ], "\n", $return ) );
	}
	
	/**
	 * Compile the headers
	 *
	 * @param	string		$subject			The subject
	 * @param	mixed		$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed		$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed		$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	NULL|string	$fromEmail			The email address to send from. If NULL, default setting is used
	 * @param	NULL|string	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array		$additionalHeaders	Additional headers to send
	 * @param	string		$boundary			The boundary that will be used between parts
	 * @return	string
	 */
	public function _compileHeaders( $subject, $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array(), $boundary = '' )
	{
		/* Work out From details */
		$fromEmail = $fromEmail ?: \IPS\Settings::i()->email_out;
		$fromName = $fromName ?: \IPS\Settings::i()->board_name;
		
		/* Basic headers */
		$headers = array(
			'MIME-Version'		=> '1.0',
			'To'				=> static::_parseRecipients( $to, TRUE ),
			'From'				=> static::encodeHeader( $fromName, $fromEmail ),
			'Subject'			=> static::encodeHeader( $subject ),
			'Date'				=> date('r'),
		);
		
		if ( $this->autoSubmitted )
		{
			$headers['Auto-Submitted'] = 'auto-generated'; // This is to try to prevent auto-responders and delivery failure notifications from responding]
		}
		
		/* CC/BCC */
		if ( $cc )
		{
			$headers['Cc'] = static::_parseRecipients( $cc );
		}
		if ( $bcc )
		{
			$headers['Bcc'] = static::_parseRecipients( $bcc );
		}
		
		/* Precedence */
		if ( $this->type === static::TYPE_LIST )
		{
			$headers['Precedence'] = 'list';
		}
		elseif ( $this->type === static::TYPE_BULK )
		{
			$headers['Precedence'] = 'bulk';
		}
		
		/* Content */
		$headers['Content-Type'] = "multipart/alternative; boundary=\"{$boundary}\"; charset=UTF-8";
		$headers['Content-Transfer-Encoding'] = "8bit";
		
		/* Additional */
		foreach ( $additionalHeaders as $k => $v )
		{
			if ( !isset( $headers[ $k ] ) ) // We deliberately don't allow overriding because when resending a failed email, it sets *all* the headers rather than just "additional" ones
			{
				$headers[ $k ] = $v;
			}
		}
		
		/* Return */
		return $headers;
	}
	
	/**
	 * Build the email message
	 *
	 * @param	\IPS\Member|NULL	$member		If the email is going to a member, the member object. Ensures correct language is used.
	 * @param	string				$boundary	The boundary used in the Content-Type header
	 * @param	string				$eol		EOL character to use
	 * @param	int|NULL			$lineLimit	Maximum line length
	 * @return	bool
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	protected function _compileMessage( ?\IPS\Member $member, $boundary, $eol, $lineLimit = 998 )
	{
		$return = '';
		
		foreach ( array( 'text/plain' => $this->compileContent( 'plaintext', $member ), 'text/html' => $this->compileContent( 'html', $member ) ) as $contentType => $content )
		{
			$return	.= "--{$boundary}{$eol}";
			$return	.= "Content-Type: {$contentType}; charset=UTF-8{$eol}";
			$return .= "{$eol}";
			
			$content = preg_replace( "/(?<!\r)\n/", "{$eol}", $content );
			if ( $lineLimit )
			{
				foreach ( explode( $eol, $content ) as $line )
				{
					$return .= wordwrap( $line, $lineLimit, $eol ) . $eol;
				}
			}
			else
			{
				$return	.= $content . $eol;
			}
		}

		$return .= "--{$boundary}--{$eol}";
		
		return $return;
	}
	
	/**
	 * @brief	Auto generated flag
	 */
	protected $autoSubmitted = TRUE;

	/**
	 * Send the email
	 * 
	 * @param	mixed	$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	mixed	$fromEmail			The email address to send from. If NULL, default setting is used. NOTE: This should always be a site-controlled domin. Some services like Sparkpost require the domain to be validated.
	 * @param	mixed	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array	$additionalHeaders	Additional headers to send
	 * @param	boolean	$autoSubmitted		The email was auto-generated (yes for notification, bulk mail, no for contact us form)
	 * @param	boolean	$updateAds			TRUE to update ad impression count (by one), FALSE to skip (used for mergeAndSend where one ad is used many times)
	 * @return	bool
	 */
	public function send( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array(), $autoSubmitted = TRUE, $updateAds = TRUE )
	{
		/* Send the email */
		try
		{
			$this->autoSubmitted = $autoSubmitted;
			
			/* Send */			
			$this->_send( $to, $cc, $bcc, $fromEmail, $fromName, $additionalHeaders );

			/* Log the send if enabled */
			$this->_trackStatistics();

			/* Sent successfully, remove notification */
			\IPS\core\AdminNotification::remove( 'core', 'ConfigurationError', 'failedMail' );
			\IPS\Db::i()->update( 'core_mail_error_logs', [ 'mlog_notification_sent' => TRUE ], [ 'mlog_notification_sent=?', 0 ] );

			/* Update the ad impression count if appropriate */
			if( $updateAds === TRUE )
			{
				\IPS\core\Advertisement::updateEmailImpressions();
			}
			
			/* Return */
			return TRUE;
		}
		/* Handle errors */
		catch( \IPS\Email\Outgoing\Exception $e )
		{
			$subject = $this->compileSubject( static::_getMemberFromRecipients( $to ) );
			$html = $this->compileContent( 'html', static::_getMemberFromRecipients( $to ) );
			$plaintext = $this->compileContent( 'plaintext', static::_getMemberFromRecipients( $to ) );
			$fromEmail = $fromEmail ?: \IPS\Settings::i()->email_out;
			$fromName = $fromName ?: \IPS\Settings::i()->board_name;
			$boundary = "--==_mimepart_" . md5( mt_rand() );

			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'					=> time(),
				'mlog_to'					=> static::_parseRecipients( $to, TRUE ),
				'mlog_from'					=> $fromEmail,
				'mlog_subject'				=> $subject,
				'mlog_content'				=> $html ?: $plaintext,
				'mlog_resend_data'			=> json_encode( array( 'type' => $this->type, 'headers' => $this->_compileHeaders( $subject, $to, $cc, $bcc, $fromEmail, $fromName, $additionalHeaders, $boundary ), 'body' => array( 'html' => $html, 'plain' => $plaintext ), 'boundary' => $boundary ) ),
				'mlog_msg'					=> json_encode( array( 'message' => $e->messageKey, 'details' => $e->extraDetails ) ),
				'mlog_smtp_log'				=> $this->getLog(),
				'mlog_notification_sent' 	=> FALSE
			) );
			
			/* Return */
			return FALSE;
		}
		/* Catch any parse errors occurred when compiling an email */
		catch( \ParseError $e )
		{
			\IPS\Log::log( $e, 'email_compile_failed' );

			return FALSE;
		}
	}

	/**
	 * Track the number of emails sent
	 *
	 * @param	int		$number		Number of emails being sent
	 * @return	void
	 */
	protected function _trackStatistics( $number=1 )
	{
		if( \IPS\Settings::i()->prune_log_emailstats == 0 )
		{
			return;
		}

		/* If we have a row for "today" then update it, otherwise insert one */
		$today = \IPS\DateTime::create()->format( 'Y-m-d', $this->language );

		try
		{
			/* We only include the time column in the query so that the db index can be effectively used */
			if( $this->templateKey === NULL )
			{
				$currentRow = \IPS\Db::i()->select( '*', 'core_statistics', array( 'type=? AND time>? AND value_4=? AND extra_data IS NULL', 'emails_sent', 1, $today ) )->first();
			}
			else
			{
				$currentRow = \IPS\Db::i()->select( '*', 'core_statistics', array( 'type=? AND time>? AND value_4=? AND extra_data=?', 'emails_sent', 1, $today, $this->templateKey ) )->first();
			}

			\IPS\Db::i()->update( 'core_statistics', "value_1=value_1+{$number}", array( 'id=?', $currentRow['id'] ) );
		}
		catch( \UnderflowException $e )
		{
			\IPS\Db::i()->insert( 'core_statistics', array( 'type' => 'emails_sent', 'value_1' => $number, 'value_4' => $today, 'time' => time(), 'extra_data' => $this->templateKey ) );
		}
	}
	
	/**
	 * Get full log if sending failed
	 * 
	 * @return	string
	 */
	public function getLog()
	{
		return NULL;
	}
	
	/**
	 * Send the email
	 * 
	 * @param	mixed	$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	mixed	$fromEmail			The email address to send from. If NULL, default setting is used. NOTE: This should always be a site-controlled domin. Some services like Sparkpost require the domain to be validated.
	 * @param	mixed	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array	$additionalHeaders	Additional headers to send
	 * @return	void
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	abstract public function _send( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array() );
	
	/**
	 * Merge and Send
	 *
	 * @param	array			$recipients			Array where the keys are the email addresses to send to and the values are an array of variables to replace
	 * @param	mixed			$fromEmail			The email address to send from. If NULL, default setting is used. NOTE: This should always be a site-controlled domin. Some services like Sparkpost require the domain to be validated.
	 * @param	mixed			$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array			$additionalHeaders	Additional headers to send. Merge tags can be used like in content.
	 * @param	\IPS\Lang|NULL	$language			The language the email content should be in
	 * @return	int				Number of successful sends
	 */
	public function mergeAndSend( $recipients, $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array(), \IPS\Lang $language = NULL )
	{
		$return = 0;

		/* Get the current locale, and then set the language's locale so datetime formatting in templates is correct for this language */
		$currentLocale = setlocale( LC_ALL, '0' );
		$language->setLocale();
		
		/* We need to know before we start if tracking has been completed or not for the content already */
		$trackingCompleted = $this->trackingCompleted;

		foreach ( $recipients as $address => $vars )
		{
			$member = \IPS\Member::load( $address, 'email' );

			/* Before compiling our content, reset the "tracking completed flag", otherwise if it hasn't been done yet, the flag is set during the first loop and never reset (so tracking isn't performed) for subsequent loops */
			$this->trackingCompleted = $trackingCompleted;
			$subject = $this->compileSubject( $member, $language );
			$htmlContent = $this->compileContent( 'html', $member, $language );
			$plaintextContent = $this->compileContent( 'plaintext', $member, $language );
			$_additionalHeaders = $additionalHeaders;
			
			foreach ( $vars as $k => $v )
			{
				$language->parseEmail( $v );

				$htmlContent = str_replace( "*|{$k}|*", htmlspecialchars( $v, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', TRUE ), $htmlContent );
				$plaintextContent = str_replace( "*|{$k}|*", $v, $plaintextContent );
				$subject = str_replace( "*|{$k}|*", $v, $subject );
				
				foreach ( $_additionalHeaders as $headerKey => $headerValue )
				{
					$_additionalHeaders[ $headerKey ] = str_replace( "*|{$k}|*", $v, $headerValue );
				}
			}

			if ( static::buildFromContent( $subject, $htmlContent, $plaintextContent, $this->type, static::WRAPPER_APPLIED, $this->templateKey, FALSE )->send( $address, array(), array(), $fromEmail, $fromName, $_additionalHeaders, TRUE, FALSE ) )
			{
				$return++;
			}
		}

		/* Update ad impression count */
		\IPS\core\Advertisement::updateEmailImpressions( $return );

		/* Now restore the locale we started with */
		\IPS\Lang::restoreLocale( $currentLocale );
		
		return $return;
	}
	
	/* !Template Parsing */
	
	/**
	 * Get template value
	 *
	 * @param	string	$app		App name
	 * @param	string	$template	Template name
	 * @param	string	$type		'html' or 'plaintext'
	 * @param	array	$params		Parameters
	 * @return	string
	 */
	public static function template( $app, $template, $type, $params )
	{
		if ( \IPS\IN_DEV )
		{
			$extension = $type === 'html' ? 'phtml' : 'txt';
			
			if ( mb_substr( $template, 0, 9 ) === 'digests__' )
			{
				$file = \IPS\ROOT_PATH . "/applications/{$app}/dev/email/" . ( $type === 'html' ? 'html' : 'plain' ) . "/digests/" . mb_substr( $template, 9 ) . ".{$extension}";
			}
			else
			{
				$file = \IPS\ROOT_PATH . "/applications/{$app}/dev/email/{$template}.{$extension}";
			}
			
			return static::devProcessTemplate( "email_{$type}_{$app}_{$template}", file_get_contents( $file ), $params, $type );
		}
		else
		{
			$key = md5( "{$app};{$template}" ) . "_email_{$type}";
							
			if ( !isset( \IPS\Data\Store::i()->$key ) )
			{
				$templateData = \IPS\Db::i()->select( '*', 'core_email_templates', array( "template_app=? AND template_name=?", $app, $template ), 'template_parent DESC' )->first();				
				\IPS\Data\Store::i()->$key = "namespace IPS\Theme;\n" . \IPS\Theme::compileTemplate( $templateData['template_content_html'], "email_html_{$app}_{$template}", $templateData['template_data'], $type === 'html' ) . "\n" . \IPS\Theme::compileTemplate( $templateData['template_content_plaintext'], "email_plaintext_{$app}_{$template}", $templateData['template_data'], $type === 'html' );
			}
						
			$functionName = "IPS\\Theme\\email_{$type}_{$app}_{$template}";
			if( !\function_exists( $functionName ) )
			{
				eval( \IPS\Data\Store::i()->$key );
			}
							
			return $functionName( ...$params );
		}
	}
	
	/**
	 * @brief	Temporary store needed in IN_DEV to remember what parameters a template has
	 */
	protected static $matchesStore = '';
	
	/**
	 * IN_DEV - load and run template
	 *
	 * @param	string	$functionName		Function name to use
	 * @param	string	$templateContents	Content to parse
	 * @param	array	$params				Params
	 * @param	string	$type				'html' or 'plaintext'
	 * @return	string
	 */
	protected static function devProcessTemplate( $functionName, $templateContents, $params, $type )
	{
		if( !\function_exists( 'IPS\\Theme\\' . $functionName ) )
		{
			preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $templateContents, $matches );
			if ( isset( $matches[0] ) )
			{
				static::$matchesStore = isset( $matches[1] ) ? $matches[1] : '';
				$templateContents = preg_replace( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', '', $templateContents );
			}
			else
			{
				/* Subjects do not contain the ips:template header, so we need a little magic */
				if ( $params !== NULL and \is_array( $params ) and \count( $params ) )
				{
					/* Extract app and key from "email__{app}_{key}_subject" */
					list( $app, $key ) = explode( '_', mb_substr( $functionName, 7, -( mb_strlen( $functionName ) - mb_strpos($functionName, '_subject' ) ) ), 2 );
					
					if ( $app and $key )
					{
						 /* Doesn't matter if it's HTML or TXT here, we just want the param list */
						$md5Key	  = md5( $app . ';' . $key ) . '_email_html';
						$template = isset( \IPS\Data\Store::i()->$md5Key ) ? \IPS\Data\Store::i()->$md5Key : NULL;
						
						if ( $template )
						{
							preg_match( "#function\s+?([^\(]+?)\((.+?)\)\s*?\{#", $template, $matches );
							
							if ( isset( $matches[2] ) )
							{
								static::$matchesStore = trim( $matches[2] );
							}
						}
						else
						{
							if ( \IPS\IN_DEV )
							{
								/* Try and get template file */
								foreach( array( 'phtml', 'txt' ) AS $type )
								{
									/* We only need one */
									if ( $file = @file_get_contents( \IPS\ROOT_PATH . "/applications/{$app}/dev/email/{$key}.{$type}" ) )
									{
										break;
									}
								}
								
								if ( $file !== FALSE )
								{
									preg_match( '/^<ips:template parameters="(.+?)?" \/>(\r\n?|\n)/', $file, $matches );
									static::$matchesStore = isset( $matches[1] ) ? $matches[1] : '';
								}
								else
								{
									throw new \BadMethodCallException( 'NO_EMAIL_TEMPLATE_FILE - ' . $app . '/' . $key . '.' . $type );
								}
							}
							else
							{
								/* Grab the param list from the database */
								try
								{
									$template = \IPS\Db::i()->select( 'template_name, template_data', 'core_email_templates', array( 'template_app=? AND template_name=?', $app, $key ), 'template_parent DESC' )->first();
									
									if ( isset( $template['template_name'] ) )
									{
										static::$matchesStore = $template['template_data'];
									}
								}
								catch( \UnderflowException $e )
								{
									/* I can't really help you, sorry */
									throw new \LogicException;
								}
							}
						}
					}
				}
			}
			
			\IPS\Theme::makeProcessFunction( $templateContents, $functionName, static::$matchesStore, $type === 'html' );
		}

		$function = 'IPS\\Theme\\'.$functionName;
		return $function( ...$params );
	}
	
	/**
	 * Determine if we have a specific email template
	 *
	 * @param	string		$app	Application key
	 * @param	string		$key	Email template key
	 * @return	bool
	 */
	public static function hasTemplate( $app, $key )
	{
		if( \IPS\IN_DEV )
		{
			foreach ( array( 'phtml', 'txt' ) as $type )
			{
				if( file_exists( \IPS\ROOT_PATH . "/applications/{$app}/dev/email/{$key}.{$type}" ) )
				{
					return TRUE;
				}
			}

			return FALSE;
		}
		else
		{
			/* See if we found anything from the store */
			$storeKey = md5( $app . ';' . $key ) . '_email_html';
			if ( isset( \IPS\Data\Store::i()->$storeKey ) )
			{
				return TRUE;
			}
			else
			{
				/* Check Database */
				try
				{
					\IPS\Db::i()->select( 'template_id', 'core_email_templates', array( 'template_app=? and template_name=?', $app, $key ) )->first();
					return TRUE;
				}
				catch( \Exception $e )
				{
					/* Nothing, it's OK to return false because there is not a separate row for plaintext */
					return FALSE;
				}
			}

			$storeKey = md5( $app . ';' . $key ) . '_email_plaintext';
			return isset( \IPS\Data\Store::i()->$storeKey );
		}
	}
	
	/* !Utilities */
	
	/**
	 * Encode Header
	 * Does not use mb_encode_mimeheader ad that does not encode special characters such as :
	 * so if the site name has a colon in it but no UTF-8 characters, emails will fail
	 *
	 * @param	string	$value
	 * @param	string	$email	If this is an email address (for a From, To, etc. header) the email address to be appended un-encoded
	 * @return	string
	 */
	public static function encodeHeader( $value = NULL, $email = NULL )
	{
		$return = '';
		
		if ( $value )
		{
			$return .= '=?UTF-8?B?' . base64_encode( $value ) . '?=';
			
			if ( $email )
			{
				$return .= ' ';
			}
		}
		
		if ( $email )
		{
			$return .= '<' . $email . '>';
		}
		
		return $return;
	}
	
	/**
	 * Turn an HTML email into a plaintext email
	 *
	 * @param	string	$html 	HTML email
	 * @return	string
	 * @note	We might find that using HTML Purifier to retain links in parenthesis is useful.
	 */
	public static function buildPlaintextBody( $html )
	{		
		/* Add newlines as needed */
		$html	= str_replace( "</p>", "</p>\n", $html );
		$html	= str_replace( array( "<br>", "<br />" ), "\n", $html );

		/* Strip HTML and return */
		return strip_tags( $html );
	}
	
	/**
	 * Convert a member object, email address, or array of either into a string to use in a header
	 *
	 * @param	string|array|\IPS\Member	$data		The member or email address, or array of members or email addresses, to send to
	 * @param	bool						$emailOnly	If TRUE, will use email only rather than names too. Set to TRUE for the "To" header
	 *
	 * @return	string
	 * @see		<a href='http://www.faqs.org/rfcs/rfc2822.html'>RFC 2822</a>
	 */
	protected static function _parseRecipients( $data, $emailOnly=FALSE )
	{
		$return = array();
		
		if ( !\is_array( $data ) )
		{
			$data = array( $data );
		}
		
		foreach ( $data as $recipient )
		{
			if ( $recipient instanceof \IPS\Member )
			{
				$return[] = $emailOnly ? $recipient->email : static::encodeHeader( $recipient->name, $recipient->email );
			}
			else
			{
				$return[] = $emailOnly ? $recipient : static::encodeHeader( NULL, $recipient );;
			}
		}
		
		return implode( ', ', $return );
	}
	
	/**
	 * Convert a member object, email address, or array of either into a member object
	 *
	 * @param	string|array|\IPS\Member	$data		The member or email address, or array of members or email addresses, to send to
	 * @return	\IPS\Member
	 */
	protected function _getMemberFromRecipients( $data )
	{
		if ( \is_array( $data ) )
		{
			$data = array_shift( $data );
		}
		
		if ( $data instanceof \IPS\Member )
		{
			return $data;
		}
		else
		{
			return \IPS\Member::load( $data, 'email' );
		}
	}
	
	/**
	 * Fix URLs before sending
	 *
	 * @param	string	$return	The content
	 * @return	string
	 */
	protected static function parseFileObjectUrls( &$return )
	{
		/* Parse file URLs */
		\IPS\Output::i()->parseFileObjectUrls( $return );
		
		/* Fix any protocol-relative URLs */
		$return = preg_replace_callback( "/\s+?(srcset|src)=(['\"])\/\/([^'\"]+?)(['\"])/ims", function( $matches ){
			$baseUrl	= parse_url( \IPS\Settings::i()->base_url );

			/* Try to preserve http vs https */
			if( isset( $baseUrl['scheme'] ) )
			{
				$url = $baseUrl['scheme'] . '://' . $matches[3];
			}
			else
			{
				$url = 'http://' . $matches[3];
			}
	
			return " {$matches[1]}={$matches[2]}{$url}{$matches[2]}";
		}, $return );
	}
	
	/* !Parsing for user-submitted content */
	
	/**
	 * Makes HTML acceptable for use in emails
	 *
	 * @param	string	$text	The text
	 * @param	\IPS\Lang		$language	Language
	 * @param	null|int		$truncate	NULL to not truncate, (int) to truncate to (int) chars
	 * @return	string
	 */
	public static function staticParseTextForEmail( $text, \IPS\Lang $language, $truncate=NULL )
	{
		static::parseFileObjectUrls( $text );
	
		if ( $truncate !== NULL and \is_int( $truncate ) and $truncate > 0 )
		{
			return \IPS\Text\Parser::truncate( $text, TRUE, $truncate );
		}		
		else
		{
			try
			{
				/* Swap out lazy load stuff */
				$text = \IPS\Text\Parser::removeLazyLoad( $text );
	
				$document = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
				$document->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $text ) );
				static::_parseNodeForEmail( $document, $language );
	
				return preg_replace( '/^<!DOCTYPE.+?>/', '', str_replace( array( '<html>', '</html>', '<head>', '</head>', '<body>', '</body>', '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' ), '', $document->saveHTML() ) );
			}
			catch( \Exception $e )
			{
				return $text;
			}
		}
	}
		
	/**
	 * Makes HTML acceptable for use in emails
	 *
	 * @param	string	$text	The text
	 * @param	\IPS\Lang		$language	Language. If not provided, will use whatever is set in $this->language - provided for backwards compatibility with templates not sending one
	 * @param	null|int		$truncate	NULL to not truncate, (int) to truncate to (int) chars
	 * @return	string
	 */
	public function parseTextForEmail( $text, \IPS\Lang $language = NULL, $truncate=NULL )
	{
		if ( $language === NULL )
		{
			$language = $this->language;
		}
		
		return static::staticParseTextForEmail( $text, $language, $truncate );
	}
		
	/**
	 * Makes HTML acceptable for use in emails
	 *
	 * @param	\DOMElement		$node		The DOM element
	 * @param	\IPS\Lang		$language	Language
	 * @return	string
	 */
	protected static function _parseNodeForEmail( \DOMNode &$node, \IPS\Lang $language )
	{
		if ( $node->hasChildNodes() )
		{
			/* Dom node lists are "live" and if you modify the tree, you may affect the index which also affects php foreach loops.  Subsequently we
				need to capture all the nodes in a loop and store them, and then loop over that store */
			$_nodes = array();

			foreach ( $node->childNodes as $child )
			{
				$_nodes[]	= $child;
			}

			foreach( $_nodes as $_node )
			{
				static::_parseNodeForEmail( $_node, $language );
			}
		}

		if ( $node instanceof \DOMElement )
		{					
			static::_parseElementForEmail( $node, $language );
		}
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Parse Element
	 *
	 * @param	\DOMElement		$node		The DOM element
	 * @param	\IPS\Lang		$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmail( \DOMElement &$node, \IPS\Lang $language )
	{
		$parent = $node->parentNode;
		
		if ( $node->getAttribute('class') )
		{
			$classMap = static::_parseElementClassMap();
			
			foreach ( explode( ' ', $node->getAttribute('class') ) as $class )
			{	
				if ( array_key_exists( $class, $classMap ) )
				{
					$method = $classMap[ $class ];
					static::$method( $node, $parent, $language );
				}
			}				
		}
		
		if ( $node->tagName == 'iframe' )
		{
			static::_parseElementForEmailIframe( $node, $parent, $language );
		}
	}
	
	/**
	 * Get the map for which CSS classes need to be parsed
	 * by which methods
	 *
	 * @return	array
	 */
	protected static function _parseElementClassMap()
	{
		return array(
			'ipsQuote'			=> '_parseElementForEmailQuote',
			'ipsCode'			=> '_parseElementForEmailCode',
			'ipsStyle_spoiler'	=> '_parseElementForEmailSpoiler',
			'ipsSpoiler'		=> '_parseElementForEmailSpoiler',
			'ipsEmbeddedVideo'	=> '_parseElementForEmailEmbed',
			'ipsImage'			=> '_parseElementForEmailImage',
			'ipsAttachLink'		=> '_parseElementForEmailAttachment',
		);
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Attachments
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailAttachment( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		if ( $node->getAttribute('href') )
		{
			$url = $node->getAttribute('href');
			$parsed = parse_url( $node->getAttribute('href') );
			
			if ( !isset( $parsed['scheme'] ) )
			{
				$baseUrl = parse_url( \IPS\Settings::i()->base_url );
				$url = $baseUrl['scheme'] . '://' . str_replace( '//', '', $url );
				$node->setAttribute( 'href', $url );
			}
		}
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Quotes
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailQuote( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		$cell = static::_createContainerTable( $parent, $node );
		$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; margin: 0;border: 1px solid #e0e0e0;border-left: 3px solid #adadad;position: relative;font-size: 13px;background: #fdfdfd" );
		
		if ( $node->getAttribute('data-cite') )
		{
			$citation = static::_createContainerTable( $cell );
			$citation->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; background: #f5f5f5;padding: 8px 15px;color: #000;font-weight: bold;font-size: 13px;display: block;" );
			$citation->appendChild( new \DOMText( $node->getAttribute('data-cite') ) );
		}
									
		$containerCell = static::_createContainerTable( $cell );
		$containerCell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; padding-left:15px" );
		
		while( $node->childNodes->length )
		{
			foreach ( $node->childNodes as $child )
			{									
				if ( $child instanceof \DOMElement and $child->getAttribute('class') == 'ipsQuote_citation' )
				{
					$child->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; background: #f3f3f3; margin: 0px 0px 0px -15px; padding: 5px 15px; color: #222; font-weight: bold; font-size: 13px; display: block;" );
				}
				
				$containerCell->appendChild( $child );
			}
		}

		$parent->removeChild( $node );
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Code boxes
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailCode( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		$cell = static::_createContainerTable( $parent, $node );
		$cell->setAttribute( 'style', "font-family: monospace; line-height: 1.5; font-size: 14px; background: #fafafa; padding: 0; border-left: 4px solid #e0e0e0;" );
		$p = new \DOMElement( 'pre' );
		$cell->appendChild( $p );
		$p->setAttribute( 'style', "font-family: monospace; line-height: 1.5; font-size: 14px; padding-left:15px" );

		while( $node->childNodes->length )
		{
			foreach ( $node->childNodes as $child )
			{
				$p->appendChild( $child );
			}
		}

		$parent->removeChild( $node );
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Spoilers
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailSpoiler( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		$cell = static::_createContainerTable( $parent, $node );
		$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; margin: 0;padding: 10px;background: #363636;color: #d8d8d8;" );
		$cell->appendChild( new \DOMText( $language->addToStack('email_spoiler_line') ) );
		$parent->removeChild( $node );
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Embedded Video
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailEmbed( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		$cell = static::_createContainerTable( $parent, $node );
		$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px; padding: 10px; margin: 0;border: 1px solid #e0e0e0;border-left: 3px solid #adadad;position: relative;font-size: 13px;background: #fdfdfd" );
		$cell->appendChild( new \DOMText( $language->addToStack('email_video_line') ) );
		$parent->removeChild( $node );
	}
	
	/**
	 * Makes HTML acceptable for use in emails: Image
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMNode	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailImage( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		/* In this case, set max size to default of 1000x750 if display setting is 'unlimited' */
		$maxImageDims	= \IPS\Settings::i()->attachment_image_size !== '0x0' ? explode( 'x', \IPS\Settings::i()->attachment_image_size ) : array( 1000, 750 );

		/* Set the max image height and width */
		$node->setAttribute( 'style', "max-width:{$maxImageDims[0]}px;max-height:{$maxImageDims[1]}px;" . $node->getAttribute('style') ) ;
	}
	
	/**
	 * Makes HTML acceptable for use in emails: iFrame
	 *
	 * @param	\DOMElement	$node		The element
	 * @param	\DOMElement	$parent		The element's parent node
	 * @param	\IPS\Lang	$language	Language
	 * @return	string
	 */
	protected static function _parseElementForEmailIframe( \DOMElement &$node, \DOMNode $parent, \IPS\Lang $language )
	{
		if ( $node->getAttribute('src') )
		{
			$url	= \IPS\Http\Url::createFromString( $node->getAttribute('src') );
			
			/* If this is an external embed link, swap it for whatever is actually embedded */
			if ( $url instanceof \IPS\Http\Url\Internal and isset( $url->queryString['app'] ) and $url->queryString['app'] == 'core' and isset( $url->queryString['module'] ) and $url->queryString['module'] == 'system' and isset( $url->queryString['controller'] ) and $url->queryString['controller'] == 'embed' and isset( $url->queryString['url'] ) )
			{
				try
				{
					$url = new \IPS\Http\Url( $url->queryString['url'] );
				}
				catch ( \Exception $e ) { }
			}
			
			/* Same for internal embeds */
			if ( $url instanceof \IPS\Http\Url\Internal )
			{			
				/* Strip "do" param, but only if it is set to "embed" */
				if ( isset( $url->queryString['do'] ) AND $url->queryString['do'] == 'embed' )
				{
					$url = $url->stripQueryString( 'do' );
				}
	
				/* Convert embedDo and embedComment if present */
				if ( isset( $url->queryString['embedDo'] ) )
				{
					$url = $url->setQueryString( 'do', $url->queryString['embedDo'] )->stripQueryString( 'embedDo' );
				}
	
				if ( isset( $url->queryString['embedComment'] ) )
				{
					$url = $url->setQueryString( 'comment', $url->queryString['embedComment'] )->stripQueryString( 'embedComment' );
				}
			}

			/* Create a link, and a paragraph, insert the paragraph into the document, then the link into the paragraph */
			$a		= new \DOMElement( 'a' );
			$p		= new \DOMElement( 'p' );

			$parent->insertBefore( $p, $node );
			$p->appendChild( $a );

			$a->setAttribute( 'href', (string) $url );
			$a->appendChild( new \DOMText( (string) $url ) );

			$parent->removeChild( $node );
		}
	}
	
	/**
	 * Create container table as some email clients can't handle things if they're not in tables
	 *
	 * @param	\DOMNode		$node		The node to put the table into
	 * @param	\DOMNode|null	$replace	If the table should replace an existing node, the node to be replaced
	 * @return	\DOMNode
	 */
	protected static function _createContainerTable( $node, $replace=NULL )
	{
		$table = new \DOMElement( 'table' );
		$row = new \DOMElement( 'tr' );
		$cell = new \DOMElement( 'td' );
		
		if ( $replace )
		{
			$node->insertBefore( $table, $replace );
		}
		else
		{
			$node->appendChild( $table );
		}
		
		$table->appendChild( $row );
		$row->appendChild( $cell );
		
		$table->setAttribute( 'width', '100%' );
		$table->setAttribute( 'cellpadding', '0' );
		$table->setAttribute( 'cellspacing', '0' );
		$table->setAttribute( 'border', '0' );
		$cell->setAttribute( 'style', "font-family: 'Helvetica Neue', helvetica, sans-serif; line-height: 1.5; font-size: 14px;" );
		
		return $cell;
	}

	/**
	 * @brief	Store database counts
	 */
	protected static $_failedMail = [];

	/**
	 * Get number of failed emails
	 *
	 * @param	\IPS\DateTime|NULL		$cutoff
	 * @param 	bool					$cache				Whether to use a cached value
	 * @param	bool					$includeNotified	Include logs that have already triggered a notification
	 * @return	int
	 */
	public static function countFailedMail( \IPS\DateTime $cutoff=NULL, bool $cache=TRUE, bool $includeNotified=FALSE ): int
	{
		$key = $cutoff ? $cutoff->getTimestamp() : 'all';
		if( isset( static::$_failedMail[ $key ] ) AND $cache )
		{
			return static::$_failedMail[ $key ];
		}

		$where = [ [ 'mlog_notification_sent=?', $includeNotified ] ];
		if( $cutoff )
		{
			$where[] = [ 'mlog_date>?', $cutoff->getTimestamp() ];
		}

		static::$_failedMail[ $key ] = \IPS\Db::i()->select( 'count(mlog_id)', 'core_mail_error_logs', $where )->first();
		return static::$_failedMail[ $key ];
	}
}
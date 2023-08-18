<?php
/**
 * @brief		SendGrid Email Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Oct 2013
 */

namespace IPS\Email\Outgoing;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * SendGrid Email Class
 */
class _SendGrid extends \IPS\Email
{
	/* !Configuration */
	
	/**
	 * @brief	The number of emails that can be sent in one "go"
	 */
	const MAX_EMAILS_PER_GO = 1000; // SendGrid has a hard 1000 recipients per request limit
	
	/**
	 * @brief	API Key
	 */
	protected $apiKey;
	
	/**
	 * Constructor
	 *
	 * @param	string	$apiKey	API Key
	 * @return	void
	 */
	public function __construct( $apiKey )
	{
		$this->apiKey = $apiKey;
	}
	
	/**
	 * Send the email
	 * 
	 * @param	mixed	$to					The member or email address, or array of members or email addresses, to send to
	 * @param	mixed	$cc					Addresses to CC (can also be email, member or array of either)
	 * @param	mixed	$bcc				Addresses to BCC (can also be email, member or array of either)
	 * @param	mixed	$fromEmail			The email address to send from. If NULL, default setting is used
	 * @param	mixed	$fromName			The name the email should appear from. If NULL, default setting is used
	 * @param	array	$additionalHeaders	The name the email should appear from. If NULL, default setting is used
	 * @return	void
	 * @throws	\IPS\Email\Outgoing\Exception
	 */
	public function _send( $to, $cc=array(), $bcc=array(), $fromEmail = NULL, $fromName = NULL, $additionalHeaders = array() )
	{
		/* Initiate the request */
		$request = $this->_initRequest( $fromEmail, $fromName );
		
		/* Add the subject and content */
		$request['subject'] = $this->compileSubject( static::_getMemberFromRecipients( $to ) );
		$request['content'] = array(
			array(
				'type'				=> 'text/plain',
				'value'				=> $this->compileContent( 'plaintext', static::_getMemberFromRecipients( $to ) )
			),
			array(
				'type'				=> 'text/html',
				'value'				=> $this->compileContent( 'html', static::_getMemberFromRecipients( $to ) )
			),
		);
		
		/* Add the recipients */
		foreach ( array( 'to', 'cc', 'bcc' ) as $type )
		{
			if ( \is_array( $$type ) )
			{
				foreach ( $$type as $recipient )
				{
					if ( $recipient instanceof \IPS\Member )
					{
						$request['personalizations'][0][ $type ][] = array( 'email' => $recipient->email, 'name' => $recipient->name );
					}
					else
					{
						$request['personalizations'][0][ $type ][] = array( 'email' => $recipient );
					}
				}
			}
			elseif ( $$type )
			{
				$recipient = $$type;
				if ( $recipient instanceof \IPS\Member )
				{
					$request['personalizations'][0][ $type ][] = array( 'email' => $recipient->email, 'name' => $recipient->name );
				}
				else
				{
					$request['personalizations'][0][ $type ][] = array( 'email' => $recipient );
				}
			}
		}
		
		/* Add additional headers */
		$request = $this->_modifyRequestDataWithHeaders( $request, $additionalHeaders );
				
		/* Send */
		$response = $this->_api( 'mail/send', $request );
		if ( isset( $response['errors'] ) )
		{
			throw new \IPS\Email\Outgoing\Exception( $response['errors'][0]['message'] );
		}
	}
	
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
		/* Initiate the request */
		$request = $this->_initRequest( $fromEmail, $fromName );
		
		/* Add the subject and content */
		$subject = $this->compileSubject( NULL, $language );
		$htmlContent = $this->compileContent( 'html', FALSE, $language );
		$plaintextContent = preg_replace( '/\*\|(.+?)\|\*/', '*|$1_plain|*', $this->compileContent( 'plaintext', FALSE, $language ) );
		$request['subject'] = preg_replace( '/\*\|(.+?)\|\*/', '*|$1_plain|*', $subject );
		$request['content'] = array(
			array(
				'type'				=> 'text/plain',
				'value'				=> $plaintextContent
			),
			array(
				'type'				=> 'text/html',
				'value'				=> $htmlContent
			),
		);
		
		/* Add the recipients */
		$addresses = array();
		foreach ( $recipients as $email => $substitutions )
		{
			$addresses[] = $email;
			
			$finalSubstitutions = array();
			foreach ( $substitutions as $k => $v )
			{
				$language->parseEmail( $v );
				$finalSubstitutions["*|{$k}_plain|*"] = $v;
				$finalSubstitutions["*|{$k}|*"] = htmlspecialchars( $v, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', TRUE );
			} 
			
			$request['personalizations'][] = array(
				'to'			=> array( array( 'email' => $email ) ),
				'substitutions'	=> $finalSubstitutions
			);
		}
				
		/* Add additional headers */
		$request = $this->_modifyRequestDataWithHeaders( $request, $additionalHeaders );

		/* Log emails sent */
		$this->_trackStatistics( \count( $addresses ) );
		
		/* Send */
		try
		{
			$response = $this->_api( 'mail/send', $request );
		}
		catch( \IPS\Email\Outgoing\Exception $e )
		{
			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'			=> time(),
				'mlog_to'			=> json_encode( $addresses ),
				'mlog_from'			=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'mlog_subject'		=> $subject,
				'mlog_content'		=> $htmlContent,
				'mlog_resend_data'	=> NULL,
				'mlog_msg'			=> json_encode( array( 'message' => $e->getMessage() ) ),
				'mlog_smtp_log'		=> NULL
			) );

			return 0;
		}

		$errorcount = 0;
		if ( isset( $response['errors'] ) )
		{
			$errorcount = \count( $response['errors'] );
			\IPS\Db::i()->insert( 'core_mail_error_logs', array(
				'mlog_date'			=> time(),
				'mlog_to'			=> json_encode( $addresses ),
				'mlog_from'			=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'mlog_subject'		=> $subject,
				'mlog_content'		=> $htmlContent,
				'mlog_resend_data'	=> NULL,
				'mlog_msg'			=> json_encode( array( 'message' => $response['errors'] ) ),
				'mlog_smtp_log'		=> NULL
			) );
		}

		$successCount = ( \count( $recipients ) - $errorcount );

		/* Update ad impression count */
		\IPS\core\Advertisement::updateEmailImpressions( $successCount );

		return $successCount;
	}
	
	/**
	 * Create a request
	 *
	 * @param	mixed			$fromEmail			The email address to send from. If NULL, default setting is used. NOTE: This should always be a site-controlled domin. Some services like Sparkpost require the domain to be validated.
	 * @param	mixed			$fromName			The name the email should appear from. If NULL, default setting is used
	 * @return	array
	 */
	protected function _initRequest( $fromEmail = NULL, $fromName = NULL )
	{
		$request = array(
			'personalizations'	=> array(),
			'from'				=> array(
				'email'				=> $fromEmail ?: \IPS\Settings::i()->email_out,
				'name'				=> $fromName ?: \IPS\Settings::i()->board_name
			),
			'tracking_settings'	=> array(
				'click_tracking'	=> array(
					'enable'			=> (bool) \IPS\Settings::i()->sendgrid_click_tracking,
					'enable_text'		=> (bool) \IPS\Settings::i()->sendgrid_click_tracking,
				),
				'open_tracking'	=> array(
					'enable'			=> (bool) \IPS\Settings::i()->sendgrid_click_tracking,
				)
			)
		);
				
		if ( \IPS\Settings::i()->sendgrid_ip_pool )
		{
			$request['ip_pool_name'] = \IPS\Settings::i()->sendgrid_ip_pool;
		}
		
		return $request;
	}
	
	/**
	 * Modify the request data that will be sent to the SparkPost API with header data
	 * 
	 * @param	array	$request			SparkPost API request data
	 * @param	array	$additionalHeaders	Additional headers to send
	 * @param	array	$allowedTags		The tags that we want to parse
	 * @return	array
	 */
	protected function _modifyRequestDataWithHeaders( $request, $additionalHeaders = array(), $allowedTags = array() )
	{
		/* Do we have a Reply-To? */
		if ( isset( $additionalHeaders['Reply-To'] ) )
		{
			if ( preg_match( '/(.*)\s?<(.*)>$/', $additionalHeaders['Reply-To'], $matches ) )
			{
				$email = $matches[2];

				$request['reply_to'] = array( 'email' => $matches[2] );
			
				if ( $matches[1] )
				{		
					if ( preg_match( '/^\=\?UTF\-8\?B\?(.+?)\?\=$/i', trim( $matches[1] ), $_matches ) )
					{
						$request['reply_to']['name'] = base64_decode( $_matches[1] );
					}
				}
			}

			unset( $additionalHeaders['Reply-To'] );
		}
		
		/* Any other headers? */
		unset( $additionalHeaders['x-sg-id'] );
		unset( $additionalHeaders['x-sg-eid'] );
		unset( $additionalHeaders['received'] );
		unset( $additionalHeaders['dkim-signature'] );
		unset( $additionalHeaders['Content-Type'] );
		unset( $additionalHeaders['Content-Transfer-Encoding'] );
		unset( $additionalHeaders['Subject'] );
		unset( $additionalHeaders['From'] );
		unset( $additionalHeaders['To'] );
		unset( $additionalHeaders['CC'] );
		unset( $additionalHeaders['BCC'] );
		if ( \count( $additionalHeaders ) )
		{
			$request['headers'] = $additionalHeaders;
		}
				
		/* Return */
		return $request;
	}
	
	/**
	 * Make API call
	 *
	 * @param	string	$method	Method
	 * @param	array	$args	Arguments
	 * @throws  \IPS\Email\Outgoing\Exception   Indicates an invalid JSON response or HTTP error
	 * @return	array|null
	 */
	protected function _api( $method, $args=NULL )
	{
		$request = \IPS\Http\Url::external( 'https://api.sendgrid.com/v3/' . $method )
			->request( \IPS\LONG_REQUEST_TIMEOUT )
			->setHeaders( array( 'Content-Type' => 'application/json', 'Authorization' => "Bearer {$this->apiKey}" ) );

		try
		{
			if ( $args )
			{
				$response = $request->post( json_encode( $args ) );
			}
			else
			{
				$response = $request->get();
			}

			
			if ( $response->content )
			{
				$response = $response->decodeJson();
			}
			else
			{
				$response = null;
			}
			
			return $response;
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage(), $e->getCode() );
		}
		/* Capture json decoding errors */
		catch ( \RuntimeException $e )
		{
			throw new \IPS\Email\Outgoing\Exception( $e->getMessage(), $e->getCode() );
		}
	}

	/**
	 * Get API key scopes
	 *
	 * @return	array
	 */
	public function scopes()
	{
		return $this->_api( 'scopes' );
	}
	
}
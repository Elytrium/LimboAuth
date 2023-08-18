<?php
/**
 * @brief		Debug Email Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\Email\Outgoing;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Debug Email Class
 */
class _Debug extends \IPS\Email
{
	/**
	 * @brief	Debug path to write the email files
	 */
	protected $debugPath	= '';
	
	/**
	 * Constructor
	 *
	 * @param	string	$debugPath	Debug path
	 * @return	void
	 */
	public function __construct( $debugPath )
	{
		$this->debugPath = $debugPath;
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
		if( !is_dir( $this->debugPath ) )
		{
			throw new \IPS\Email\Outgoing\Exception( 'no_path_email_debug', 1, NULL, array( $this->debugPath ) );
		}
		
		$fullEmailContents = $this->compileFullEmail( $to, $cc, $bcc, $fromEmail, $fromName, $additionalHeaders );

		$filename = date("M-j-Y") . '-' . microtime() . '-' . urlencode( mb_substr( $this->_parseRecipients( $to, TRUE ), 0, 200 ) ) . ".eml";
		if( !@\file_put_contents( rtrim( $this->debugPath, '/' ) . '/' . $filename, $fullEmailContents ) )
		{
			throw new \IPS\Email\Outgoing\Exception( 'no_write_email_debug', 2, NULL, array( rtrim( $this->debugPath, '/' ) . '/' . $filename ) );
		}
	}
}
<?php
/**
 * @brief		POP/IMAP Incoming Email Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 December 2015
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
 */
class _PopImap
{
	/**
	 * @brief	File pointer
	 */
	protected $fh;
	
	/**
	 * Constructor
	 *
	 * @param	string	$server			Server name (e.g. pop.example.com)
	 * @param	bool	$sslRequired	If SSL is required
	 * @param	int		$port			Port
	 * @param	string	$username		Username
	 * @param	string	$password		Password
	 * @return	void
	 * @throws	\IPS\Email\Incoming\PopImapException
	 */
	public function __construct( $server, $sslRequired, $port, $username, $password )
	{
		/* Connect */
		$errorNumber = 0;
		$errorMessage = '';
		$this->fh = @fsockopen( ( $sslRequired ? 'ssl://' : '' ) . $server, $port, $errorNumber, $errorMessage );
		if ( $this->fh === FALSE )
		{
			throw new PopImapException( 'pop3_cant_connect' );
		}
		
		/* Set stream preferences */
		stream_set_timeout( $this->fh, \IPS\DEFAULT_REQUEST_TIMEOUT );
		stream_set_blocking( $this->fh, TRUE );
		
		/* Get initial response */
		try
		{
			$response = $this->_getLine();
		}
		catch ( PopImapException $e )
		{
			throw new PopImapException( 'pop3_connect_err' );
		}
		
		/* Log In */
		try
		{
			$this->_putLine( "USER {$username}" );
			$this->_putLine( "PASS {$password}" );
		}
		catch ( PopImapException $e )
		{
			throw new PopImapException( 'pop3_login_err' );
		}
	}
	
	/**
	 * Get number of emails in inbox
	 *
	 * @return	int
	 * @throws	\IPS\Email\Incoming\PopImapException
	 */
	public function emailsInInbox()
	{
		$stats = explode( ' ', $this->_putLine( 'STAT' ) );
		return (int) $stats[1];
	}
	
	/**
	 * Get email
	 *
	 * @param	int	$i	The email to fetch
	 * @return	string
	 * @throws	\IPS\Email\Incoming\PopImapException
	 */
	public function getEmail( $i )
	{
		$this->_putLine( "RETR {$i}" );

		$message = '';
		while ( $line = $this->_getLine( FALSE ) and !preg_match( "/^\.\r\n/", $line ) )
		{
			$message .= $line;
		}
										
		return $message;
	}
	
	/**
	 * Delete email
	 *
	 * @param	int	$i	The email to delete
	 * @return	string
	 * @throws	\IPS\Email\Incoming\PopImapException
	 */
	public function deleteEmail( $i )
	{
		$this->_putLine( "DELE {$i}" );
	}
	
	/**
	 * Get Line
	 *
	 * @param	bool	$expectOkay	The response should start with "+OK"
	 * @return	string
	 */
	public function _getLine( $expectOkay = TRUE )
	{
		$response = fgets( $this->fh, 512 );
		
		$status = socket_get_status( $this->fh );
		if ( $status['timed_out'] )
		{
			throw new \IPS\Email\Incoming\PopImapException( 'TIMEOUT' );
		}
		
		if ( $expectOkay and mb_substr( $response, 0, 3 ) !== '+OK' )
		{
			throw new \IPS\Email\Incoming\PopImapException( 'UNEXPECTED_RESPONSE: ' . $response );
		}
		
		return $response;
	}
	
	/**
	 * Put Line
	 *
	 * @param	string	$command	Command to send
	 * @param	bool	$expectOkay	The response should start with "+OK"
	 * @return	string	The response
	 */
	public function _putLine( $command, $expectOkay = TRUE )
	{
		if ( @\fwrite( $this->fh, $command . "\r\n" ) === FALSE )
		{
			throw new \IPS\Email\Incoming\PopImapException( 'COULD_NOT_WRITE' );
		}
		
		return $this->_getLine( $expectOkay );
	}
	
	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		$this->_putLine( "QUIT", FALSE );
		fclose( $this->fh );
	}
}
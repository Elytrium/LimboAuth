<?php
/**
 * @brief		Exception class for email errors
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Aug 2013
 */

namespace IPS\Email\Outgoing;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Exception class for email errors
 */
class _Exception extends \RuntimeException
{
	/**
	 * @brief Extra details for log
	 */
	public $extraDetails	= array();
	public $messageKey = NULL;

	/**
	 * Constructor
	 *
	 * @param	string			$message	Error message
	 * @param	int				$code		Error Code
	 * @param	\Exception|NULL	$previous	Previous Exception
	 * @param	array|NULL		$extra		Extra details for log
	 * @return	void
	 */
	public function __construct( $message = null, $code = 0, $previous = null, $extra=NULL )
	{
		/* Store these for the extraLogData() method */
		$this->extraDetails = $extra;
		$this->messageKey = $message;
		$message = \IPS\Member::loggedIn()->language()->addToStack( $message, FALSE, array( 'sprintf' => $extra ) );

		return parent::__construct( $message, $code, $previous );
	}
}
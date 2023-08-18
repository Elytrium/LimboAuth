<?php
/**
 * @brief		Background (queue) Task Exception
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 May 2016
 */

namespace IPS\Task\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Task Exception
 */
class _OutOfRangeException extends \OutOfRangeException
{
	/**
	 * Constructor
	 *
	 * @note	This exception is thrown to indicate the task has completed successfully
	 * @param	string		$message	Error Message
	 * @return	void
	 */
	public function __construct( $message='' )
	{
		return parent::__construct( $message );
	}
}
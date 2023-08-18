<?php
/**
 * @brief		Handle actions from emails
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Nov 2020
 */

namespace IPS\nexus\modules\front\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Handle actions from emails
 */
class _email extends \IPS\Dispatcher\Controller
{
	/**
	 * Send or discard reply
	 *
	 * @return	void
	 */
	public function sendDiscard()
	{
		try
		{
			$reply = \IPS\nexus\Support\Reply::load( \IPS\Request::i()->id );
			if ( !\IPS\Login::compareHashes( md5( $reply->item()->email_key . $reply->date ), (string) \IPS\Request::i()->key ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X206/1', 404, '' );
		}

		if ( $reply->type !== \IPS\nexus\Support\Reply::REPLY_PENDING )
		{
			\IPS\Output::i()->error( 'support_reply_not_pending', '1X206/2', 403, '' );
		}

		if ( \IPS\Request::i()->send )
		{
			$reply->sendPending();
		}
		else
		{
			$reply->delete();
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( \IPS\Request::i()->send ? 'support_pending_sent' : 'support_pending_discarded' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support', 'nexus', 'front' )->pendingDone( \IPS\Request::i()->send ? 'support_pending_sent' : 'support_pending_discarded' );
	}
}
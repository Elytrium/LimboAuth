<?php
/**
 * @brief		rss
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Oct 2016
 */

namespace IPS\core\modules\front\discover;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * rss
 */
class _rss extends \IPS\Dispatcher\Controller
{
	/**
	 * Display Feed
	 *
	 * @return	void
	 */
	protected function manage()
	{
		try
		{
			$feed = \IPS\core\Rss::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C340/1', 404, '' );
		}
		
		if ( !$feed->_enabled )
		{
			\IPS\Output::i()->error( 'node_error_no_perm', '2C340/2', 403, '' );
		}
		
		/* Specific Member? */
		if ( isset( \IPS\Request::i()->member_id ) AND isset( \IPS\Request::i()->key ) )
		{
			/* Load Member */
			$member = \IPS\Member::load( \IPS\Request::i()->member_id );
			
			/* Make sure we have an actual member, and that the key matches. If it doesn't, we can bubble up and see if the feed works for guests, and just use that */
			if ( $member->member_id AND \IPS\Login::compareHashes( $member->getUniqueMemberHash(), (string) \IPS\Request::i()->key ) )
			{
				/* Make sure we have access to this feed. */
				if ( $feed->groups == '*' OR $member->inGroup( $feed->groups ) )
				{
					/* Send It */
					\IPS\Output::i()->sendOutput( $feed->generate( $member ), 200, 'text/xml' );
				}
				else
				{
					\IPS\Output::i()->error( 'node_error_no_perm', '2C340/3', 403, '' );
				}
			}
		}
		
		/* We're working with a guest. */
		if ( $feed->groups == '*' OR \in_array( \IPS\Settings::i()->guest_group, $feed->groups ) )
		{
			\IPS\Output::i()->sendOutput( $feed->generate(), 200, 'text/xml' );
		}
		else
		{
			\IPS\Output::i()->error( 'node_error_no_perm', '2C340/4', 403, '' );
		}
	}
}
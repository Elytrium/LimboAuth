<?php
/**
 * @brief		Poll View Voters Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jan 2014
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Poll View Voters Controller
 */
class _poll extends \IPS\Dispatcher\Controller
{
	/**
	 * View log
	 *
	 * @return	void
	 */
	protected function voters()
	{
		\IPS\Output::i()->metaTags['robots'] = 'noindex';
		try
		{
			$poll = \IPS\Poll::load( \IPS\Request::i()->id );
			if ( !$poll->canSeeVoters() )
			{
				\IPS\Output::i()->error( 'node_error', '2C174/2', 403, '' );
			}
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pollVoters( $poll->getVotes( \IPS\Request::i()->question, \IPS\Request::i()->option ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C174/1', 404, '' );
		}		
	}
}
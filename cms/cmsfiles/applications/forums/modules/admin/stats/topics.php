<?php
/**
 * @brief		topics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		18 Aug 2014
 */

namespace IPS\forums\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * topics
 */
class _topics extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'topics_manage' );

		$tabs = array( 'total' => 'stats_topics_tab_total' );

        if( \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', array( 'topics>?', 0 ) )->first() )
        {
            $tabs[ 'byforum'] = 'stats_topics_tab_byforum';
        }

		\IPS\Request::i()->tab ??= 'total';
		$activeTab	= ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'total';

		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'forums', ( $activeTab === 'total' ) ? 'Topics' : 'TopicsByForum' )->getChart( \IPS\Http\Url::internal( "app=forums&module=stats&controller=topics&tab={$activeTab}" ) );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__forums_stats_topics');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=forums&module=stats&controller=topics" ) );
		}
	}
}
<?php
/**
 * @brief		solved
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		25 Jul 2022
 */

namespace IPS\forums\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * solved
 */
class _solved extends \IPS\Dispatcher\Controller
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
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'topics_manage' );
		parent::execute();
	}

	/**
	 * Show the stats then
	 *
	 * core_statistics mapping:
	 * type: solved
	 * value_1: forum_id
	 * value_2: total topics added
	 * value_3: total solved
	 * value_4: AVG time to solved (in seconds)
	 * time: timestamp of the start of the day (so 0:00:00)
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs = array(
			'time' 		 => 'forums_solved_stats_time',
			'percentage' => 'forums_solved_stats_percentage',
			'solved'	 => 'stats_topics_tab_solved'
		);

		/* Show button to adjust settings */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'title'		=> 'solved_stats_rebuild_button',
			'link'		=> \IPS\Http\Url::internal( 'app=forums&module=stats&controller=solved&do=rebuildStats' )->csrf(),
			'data'		=> array( 'confirm' => '' )
		);
		
		\IPS\Request::i()->tab ??= 'time';
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'time';
		
		$url = \IPS\Http\Url::internal( "app=forums&module=stats&controller=solved&tab={$activeTab}" );
		$chart = match( $activeTab ) {
			'percentage'	=> \IPS\core\Statistics\Chart::loadFromExtension( 'forums', 'PercentageSolved' )->getChart( $url ),
			'solved'		=> \IPS\core\Statistics\Chart::loadFromExtension( 'forums', 'SolvedByForum' )->getChart( $url ),
			default			=> \IPS\core\Statistics\Chart::loadFromExtension( 'forums', 'TimeSolved' )->getChart( $url )
		};
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string)$chart;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__forums_stats_solved' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string)$chart, \IPS\Http\Url::internal( "app=forums&module=stats&controller=solved" ), 'tab', '', 'ipsPad' );
		}
	}
	
	/**
	 * Kick off a rebuild of the stats
	 *
	 */
	public function rebuildStats()
	{
		\IPS\Session::i()->csrfCheck();

		foreach( \IPS\Db::i()->select( '*', 'forums_forums', array( 'topics>? and ( forums_bitoptions & ? or forums_bitoptions & ? or forums_bitoptions & ? )', 0, 4, 8, 16 ) ) as $forum )
		{
			\IPS\Task::queue( 'forums', 'RebuildSolvedStats', array( 'forum_id' => $forum['id'] ) );
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=forums&module=stats&controller=solved'), 'solved_stats_rebuild_started' );
	}
	
	/**
	 * Get valid forum IDs to protect against bad data when a forum is removed
	 *
	 * @return array
	 */
	protected function getValidForumIds()
	{
		$validForumIds = [];
		
		foreach( \IPS\Db::i()->select( 'value_1', 'core_statistics', [ 'type=?', 'solved' ], NULL, NULL, 'value_1' ) as $forumId )
		{
			try
			{
				$validForumIds[ $forumId ] = \IPS\forums\Forum::load( $forumId );
			}
			catch( \Exception $e ) { }
		}
		
		return $validForumIds;
	}
}
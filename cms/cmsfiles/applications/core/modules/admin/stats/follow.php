<?php
/**
 * @brief		follow
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		10 Sep 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * follow
 */
class _follow extends \IPS\Dispatcher\Controller
{
	
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	Number of results per page
	 */
	const PER_PAGE = 25;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'follow_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs		= array(
			'followers'		=> 'stats_followers_title',
			'following'		=> 'stats_following_title',
		);
		$activeTab	= ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'followers';

		$where = array( 'core_follow.follow_app=? and core_follow.follow_area=?', 'core', 'member' );
		
		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

		if( $page < 1 )
		{
			$page = 1;
		}

		$column = ( $activeTab == "followers" ) ? 'follow_rel_id' : 'follow_member_id';
		
		try
		{
			$total = \IPS\Db::i()->select( 'COUNT(DISTINCT(core_follow.' . $column . '))', 'core_follow', $where )->join( 'core_members', 'core_members.member_id = core_follow.' . $column )->first();
		}
		catch ( \UnderflowException $e )
		{
			$total = 0;
		}

		if( $total )
		{
			$select	= \IPS\Db::i()->select( 'core_follow.' . $column . ', count(*) as count', 'core_follow', $where, 'count DESC', array( ( $page - 1 ) * static::PER_PAGE, static::PER_PAGE ), $column )->join( 'core_members', 'core_members.member_id = core_follow.' . $column );
			$mids = array();
			
			foreach( $select as $row )
			{
				$mids[] = $row[ $column ];
			}

			$members = array();

			if ( \count( $mids ) )
			{
				$members = iterator_to_array( \IPS\Db::i()->select( '*', 'core_members', array( \IPS\Db::i()->in( 'member_id', $mids ) ) )->setKeyField('member_id') );
			}

			$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination(
				\IPS\Http\Url::internal( 'app=core&module=stats&controller=follow' ),
				ceil( $total / static::PER_PAGE ),
				$page,
				static::PER_PAGE,
				FALSE
			);

			$output = \IPS\Theme::i()->getTemplate('stats' )->topFollow( $select, $pagination, $members, $total, $column, $activeTab );
		}
		else
		{
			$output= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( NULL, \IPS\Member::loggedIn()->language()->addToStack('no_results'), FALSE , 'ipsPad', NULL, TRUE );
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->paddedBlock( $output, NULL, "ipsPad" );
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_stats_follow' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $output, \IPS\Http\Url::internal( "app=core&module=stats&controller=follow" ), 'tab', '', 'ipsPad' );
		}
			
	}
	
	// Create new methods with the same name as the 'do' parameter which should execute it
}
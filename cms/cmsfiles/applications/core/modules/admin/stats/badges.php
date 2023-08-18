<?php
/**
 * @brief		badges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		10 Mar 2021
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * badges
 */
class _badges extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'overview_manage' );
		parent::execute();
	}

	/**
	 * Show badges in a modal
	 *
	 * @return void
	 */
	protected function showBadges()
	{
		$where = [
			[ 'datetime BETWEEN ? AND ?', \IPS\Request::i()->badgeDateStart, \IPS\Request::i()->badgeDateEnd ],
			[ 'member_group_id=?', \IPS\Request::i()->member_group_id ]
		];

		$query = \IPS\Db::i()->select( 'badge, COUNT(*) as count', 'core_member_badges', $where, NULL, NULL, 'badge' )
							 ->join( 'core_members', [ 'core_members.member_id=core_member_badges.member' ] );

		$results = [];
		foreach( $query as $row )
		{
			try
			{
				$row['badge'] = \IPS\core\Achievements\Badge::load( $row['badge'] );
				$results[] = $row;
			}
			catch( \Exception $e ) { }
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'achievements' )->statsBadgeModal( $results );
	}
	
	/**
	 * Show member badges in a modal
	 *
	 * @return void
	 */
	protected function showMemberBadges()
	{
		$where = [
			[ 'datetime BETWEEN ? AND ?', \IPS\Request::i()->badgeDateStart, \IPS\Request::i()->badgeDateEnd ],
			[ 'member_id=?', \IPS\Request::i()->member_id ]
		];

		$query = \IPS\Db::i()->select( 'badge, datetime', 'core_member_badges', $where, NULL, NULL )
							 ->join( 'core_members', [ 'core_members.member_id=core_member_badges.member' ] );

		$results = [];
		foreach( $query as $row )
		{
			try
			{
				$row['badge'] = \IPS\core\Achievements\Badge::load( $row['badge'] );
				$results[] = $row;
			}
			catch( \Exception $e ) { }
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'achievements' )->statsMemberBadgeModal( $results );
	}

	/**
	 * Badges earned activity chart
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$tabs = array(
			'type' => 'stats_badges_by_badge',
			'list' => 'stats_badges_by_group',
			'member'	=> 'stats_badges_by_member',
		);
		\IPS\Request::i()->tab ??= 'type';
		$activeTab = ( array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'type';

		if ( $activeTab === 'type' )
		{
			$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Badges' )->getChart( \IPS\Http\Url::internal( 'app=core&module=stats&controller=badges&tab=' . $activeTab ) );
		}
		elseif ( $activeTab === 'member' )
		{
			$start		= NULL;
			$end		= NULL;

			$defaults = array( 'start' => \IPS\DateTime::create()->setDate( date('Y'), date('m'), 1 ), 'end' => new \IPS\DateTime );

			if( isset( \IPS\Request::i()->badgeDateStart ) AND isset( \IPS\Request::i()->badgeDateEnd ) )
			{
				$defaults = array( 'start' => \IPS\DateTime::ts( \IPS\Request::i()->badgeDateStart ), 'end' => \IPS\DateTime::ts( \IPS\Request::i()->badgeDateEnd ) );
			}

			$form = new \IPS\Helpers\Form( $activeTab, 'continue' );
			$form->add( new \IPS\Helpers\Form\DateRange( 'date', $defaults, TRUE ) );

			if( $values = $form->values() )
			{
				/* Determine start and end time */
				$startTime	= $values['date']['start']->getTimestamp();
				$endTime	= $values['date']['end']->getTimestamp();

				$start		= $values['date']['start']->html();
				$end		= $values['date']['end']->html();
			}
			else
			{
				/* Determine start and end time */
				$startTime	= $defaults['start']->getTimestamp();
				$endTime	= $defaults['end']->getTimestamp();

				$start		= $defaults['start']->html();
				$end		= $defaults['end']->html();
			}

			/* Create the table */
			$chart = new \IPS\Helpers\Table\Db( 'core_member_badges', \IPS\Http\Url::internal( 'app=core&module=stats&controller=badges&type=member' ), [ 'datetime BETWEEN ? AND ?', $startTime, $endTime ] );
			$chart->quickSearch = NULL;
			$chart->selects = [ 'count(*) as count' ];
			$chart->joins = [
				[ 'select' => 'member_id', 'from' => 'core_members', 'where' => 'core_members.member_id=core_member_badges.member' ]
			];

			$chart->groupBy = 'member_id';
			$chart->langPrefix = 'stats_badges_';
			$chart->include = [ 'member_id', 'count' ];
			$chart->mainColumn = 'member_id';
			$chart->baseUrl = $chart->baseUrl->setQueryString( array( 'badgeDateStart' => $startTime, 'badgeDateEnd' => $endTime, 'tab' => $activeTab ) );
			$chart->sortBy = 'count';
			
			/* Custom parsers */
			$chart->parsers = array(
				'member_id' => function( $val, $row )
				{
					$member = \IPS\Member::load( $val );
					return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . ' ' . $member->link();
				},
				'count' => function( $val, $row ) use( $startTime, $endTime )
				{
					return \IPS\Theme::i()->getTemplate( 'achievements' )->statsMemberBadgeCount( $val, $row['member_id'], $startTime, $endTime );
				}
			);

			$formHtml = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'stats' ), 'filtersFormTemplate' ) );
			$chart = \IPS\Theme::i()->getTemplate( 'achievements', 'core' )->statsBadgeWrapper( $formHtml, (string) $chart );
		}
		else
		{
			$start		= NULL;
			$end		= NULL;

			$defaults = array( 'start' => \IPS\DateTime::create()->setDate( date('Y'), date('m'), 1 ), 'end' => new \IPS\DateTime );

			if( isset( \IPS\Request::i()->badgeDateStart ) AND isset( \IPS\Request::i()->badgeDateEnd ) )
			{
				$defaults = array( 'start' => \IPS\DateTime::ts( \IPS\Request::i()->badgeDateStart ), 'end' => \IPS\DateTime::ts( \IPS\Request::i()->badgeDateEnd ) );
			}

			$form = new \IPS\Helpers\Form( $activeTab, 'continue' );
			$form->add( new \IPS\Helpers\Form\DateRange( 'date', $defaults, TRUE ) );

			if( $values = $form->values() )
			{
				/* Determine start and end time */
				$startTime	= $values['date']['start']->getTimestamp();
				$endTime	= $values['date']['end']->getTimestamp();

				$start		= $values['date']['start']->html();
				$end		= $values['date']['end']->html();
			}
			else
			{
				/* Determine start and end time */
				$startTime	= $defaults['start']->getTimestamp();
				$endTime	= $defaults['end']->getTimestamp();

				$start		= $defaults['start']->html();
				$end		= $defaults['end']->html();
			}

			/* Create the table */
			$chart = new \IPS\Helpers\Table\Db( 'core_member_badges', \IPS\Http\Url::internal( 'app=core&module=stats&controller=badges&type=list' ), [ 'datetime BETWEEN ? AND ?', $startTime, $endTime ] );
			$chart->quickSearch = NULL;
			$chart->selects = [ 'count(*) as count' ];
			$chart->joins = [
				[ 'select' => 'member_group_id', 'from' => 'core_members', 'where' => 'core_members.member_id=core_member_badges.member' ]
			];

			$chart->groupBy = 'member_group_id';
			$chart->langPrefix = 'stats_badges_';
			$chart->include = [ 'member_group_id', 'count' ];
			$chart->mainColumn = 'member_group_id';
			$chart->baseUrl = $chart->baseUrl->setQueryString( array( 'badgeDateStart' => $startTime, 'badgeDateEnd' => $endTime, 'tab' => $activeTab ) );

			/* Custom parsers */
			$chart->parsers = array(
				'member_group_id' => function( $val, $row )
				{
					try
					{
						return \IPS\Member\Group::load( $val )->formattedName;
					}
					catch ( \Throwable $e )
					{
						return \IPS\Member::loggedIn()->language()->addToStack( 'unavailable' );
					}
				},
				'count' => function( $val, $row ) use( $startTime, $endTime )
				{
					return \IPS\Theme::i()->getTemplate( 'achievements' )->statsBadgeCount( $val, $row['member_group_id'], $startTime, $endTime );
				}
			);

			$formHtml = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'stats' ), 'filtersFormTemplate' ) );
			$chart = \IPS\Theme::i()->getTemplate( 'achievements', 'core' )->statsBadgeWrapper( $formHtml, (string) $chart );
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string)$chart;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_stats_badges' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, (string)$chart, \IPS\Http\Url::internal( "app=core&module=stats&controller=badges" ), 'tab', '', 'ipsPad' );
		}
	}
}
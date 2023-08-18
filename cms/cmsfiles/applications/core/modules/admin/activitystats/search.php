<?php
/**
 * @brief		Search Statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Dec 2019
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search Statistics
 */
class _search extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'search_stats_manage' );
		parent::execute();
	}

	/**
	 * View search statistics
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Show button to adjust settings */
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'primary'	=> TRUE,
			'title'		=> 'manage_searchstats',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search&do=settings' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
		);

		\IPS\Output::i()->sidebar['actions']['log'] = array(
			'icon'		=> 'search',
			'title'		=> 'searchstats_log',
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search&do=log' ),
		);
		
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Search' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=search" ) );

		\IPS\Output::i()->output	= (string) $chart;

		if( \IPS\Request::i()->noheader AND \IPS\Request::i()->isAjax() )
		{
			return;
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_search');
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'stats_search_prune', \IPS\Settings::i()->stats_search_prune, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__statssearch_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search' ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'settings', $form, FALSE );
	}

	/**
	 * Search log
	 *
	 * @return	void
	 */
	protected function log()
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_statistics', \IPS\Http\Url::internal( 'app=core&module=activitystats&controller=search&do=log' ), array( array( 'type=?', 'search' ) ) );
		$table->langPrefix = 'searchstats_';
		$table->quickSearch = 'value_4';

		/* Columns we need */
		$table->include = array( 'value_4', 'value_2', 'time' );
		$table->mainColumn = 'value_4';

		$table->sortBy = $table->sortBy ?: 'time';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		/* Custom parsers */
		$table->parsers = array(
			'time'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			}
		);

		/* The table filters won't without this */
		\IPS\Output::i()->bypassCsrfKeyCheck = true;

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('searchstats_log');
		\IPS\Output::i()->output 	= (string) $table;
	}
}
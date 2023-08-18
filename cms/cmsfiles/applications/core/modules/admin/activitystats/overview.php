<?php
/**
 * @brief		Content overview statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Jan 2020
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content overview statistics
 */
class _overview extends \IPS\core\modules\admin\stats\overview
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * @brief Template group to use to output
	 */
	protected $templateGroup = 'activitystats';

	/**
	 * @brief	Allow MySQL RW separation for efficiency
	 */
	public static $allowRWSeparation = TRUE;

	/**
	 * Create the general page layout, but we will load the individual cells via AJAX to ensure there are no performance concerns loading the page
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$formHtml = $this->form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'stats' ), 'filtersOverviewForm' ) );
		$blocks = \IPS\Application::allExtensions( 'core', 'OverviewStatistics', TRUE, 'core', 'Registrations' );

		$excludedApps = array();

		if( isset( \IPS\Request::i()->cookie['overviewExcludedApps'] ) )
		{
			try
			{
				$excludedApps = json_decode( \IPS\Request::i()->cookie['overviewExcludedApps'] );

				if( !\is_array( $excludedApps ) )
				{
					$excludedApps = array();
				}
			}
			catch( \Exception $e ){}
		}

		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_stats.js', 'core' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/statistics.css', 'core', 'admin' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_overview');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( $this->templateGroup )->overview( $formHtml, $blocks, $excludedApps );
	}

	/**
	 * Load the filter configuration form
	 *
	 * @return	void
	 */
	protected function loadBlockForm()
	{
		$blocks = \IPS\Application::allExtensions( 'core', 'OverviewStatistics', TRUE, 'core', 'Registrations' );

		if( !isset( $blocks[ \IPS\Request::i()->blockKey ] ) )
		{
			\IPS\Output::i()->error( 'stats_overview_block_not_found', '2C416/1', 404, '' );
		}

		\IPS\Output::i()->output = $blocks[ \IPS\Request::i()->blockKey ]->getBlockForm( \IPS\Request::i()->subBlockKey );
	}
}
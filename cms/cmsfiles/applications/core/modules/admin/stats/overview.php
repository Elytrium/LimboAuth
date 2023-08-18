<?php
/**
 * @brief		User activity statistics overview
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		09 Jan 2020
 */

namespace IPS\core\modules\admin\stats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * User activity statistics overview
 */
class _overview extends \IPS\Dispatcher\Controller
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
	 * @brief Date range to restrict to, or NULL for no restriction
	 */
	protected $dateRange = '7';

	/**
	 * @brief Form object
	 */
	protected $form = NULL;

	/**
	 * @brief Template group to use to output
	 */
	protected $templateGroup = 'stats';

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'overview_manage' );

		$options = array(
			'7'		=> 'last_week',
			'30'	=> 'last_month',
			'90'	=> 'last_three_months',
			'180'	=> 'last_six_months',
			'365'	=> 'last_year',
			'0'		=> 'alltime',
			'-1'	=> 'custom'
		);

		$this->form = new \IPS\Helpers\Form( 'posts', 'update' );
		$this->form->add( new \IPS\Helpers\Form\Select( 'predate', '7', FALSE, array( 'options' => $options, 'toggles' => array( '-1' => array( 'dateFilterInputs' ) ) ) ) );
		$this->form->add( new \IPS\Helpers\Form\DateRange( 'date', NULL, FALSE, array(), NULL, NULL, NULL, 'dateFilterInputs' ) );

		parent::execute();
	}

	/**
	 * Create the general page layout, but we will load the individual cells via AJAX to ensure there are no performance concerns loading the page
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$formHtml = $this->form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'stats' ), 'filtersOverviewForm' ) );
		$blocks = \IPS\Application::allExtensions( 'core', 'OverviewStatistics', TRUE, 'core', 'Registrations' );

		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_stats.js', 'core' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/statistics.css', 'core', 'admin' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_stats_overview');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( $this->templateGroup )->overview( $formHtml, $blocks );
	}

	/**
	 * Load an individual block and output its HTML
	 *
	 * @return	void
	 */
	protected function loadBlock()
	{
		$blocks = \IPS\Application::allExtensions( 'core', 'OverviewStatistics', TRUE, 'core', 'Registrations' );

		if( !isset( $blocks[ \IPS\Request::i()->blockKey ] ) )
		{
			\IPS\Output::i()->error( 'stats_overview_block_not_found', '2C412/1', 404, '' );
		}

		$dateFilters = NULL;

		if( \IPS\Request::i()->range )
		{
			$dateFilters = \IPS\Request::i()->range;
		}
		elseif( \IPS\Request::i()->start )
		{
			try
			{
				$timezone = \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL;
			}
			catch ( \Exception $e )
			{
				$timezone = NULL;
			}

			$dateFilters = array(
				'start'	=> new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->start ), $timezone ),
				'end'	=> ( new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->end ), $timezone ) )->setTime( 23, 59, 59 )
			);
		}

		\IPS\Output::i()->sendOutput( $blocks[ \IPS\Request::i()->blockKey ]->getBlock( $dateFilters, \IPS\Request::i()->subblock ) );
	}
}
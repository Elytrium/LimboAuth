<?php
/**
 * @brief		Email statistics
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 Oct 2018
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Email statistics
 */
class _emailstats extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'emailstats_manage' );

		/* We can only view the stats if we have logging enabled */
		if( \IPS\Settings::i()->prune_log_emailstats == 0 )
		{
			\IPS\Output::i()->error( 'emaillogs_not_enabled', '1C395/1', 403, '' );
		}

		parent::execute();
	}

	/**
	 * Show the charts
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$activeTab = $this->_getActiveTab();

		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', ( $activeTab === 'emails' ) ? 'Emails' : 'EmailClicks' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=emailstats&tab={$activeTab}" ) );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
		}
		else
		{	
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_emailstats');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $this->_getAvailableTabs(), $activeTab, (string) $chart, \IPS\Http\Url::internal( "app=core&module=activitystats&controller=emailstats" ), 'tab', '', 'ipsPad' );
		}
	}

	/**
	 * Get the active tab
	 *
	 * @return string
	 */
	protected function _getActiveTab()
	{
		\IPS\Request::i()->tab ??= 'emails';
		return ( array_key_exists( \IPS\Request::i()->tab, $this->_getAvailableTabs() ) ) ? \IPS\Request::i()->tab : 'emails';
	}

	/**
	 * Get the possible tabs
	 *
	 * @return array
	 */
	protected function _getAvailableTabs()
	{
		return array(
			'emails'	=> 'stats_emailstats_emails',
			'clicks'	=> 'stats_emailstats_clicks',
		);
	}
}
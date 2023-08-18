<?php
/**
 * @brief		Community Activity
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Mar 2017
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Activity
 */
class _communityactivity extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'communityactivity_manage' );
		parent::execute();
	}

	/**
	 * Show a graph of user activity
	 *
	 * @return	void
	 * @note	Activity includes posting, following, reacting
	 */
	protected function manage()
	{
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'CommunityActivity' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=communityactivity" ) );

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $chart;
			return;
		}
	
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_communityactivity');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'stats' )->activitymessage();
		\IPS\Output::i()->output .= (string) $chart;
	}

	
}
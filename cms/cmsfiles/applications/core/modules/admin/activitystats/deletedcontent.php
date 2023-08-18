<?php
/**
 * @brief		deletedcontent
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		23 Sep 2021
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * deletedcontent
 */
class _deletedcontent extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'deletedcontent_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'DeletedContent' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=deletedcontent" ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_deletedcontent');
		\IPS\Output::i()->output = (string) $chart;
	}
	
}
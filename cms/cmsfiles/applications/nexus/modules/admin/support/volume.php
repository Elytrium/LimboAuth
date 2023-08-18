<?php
/**
 * @brief		New Support Request Volumme
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		25 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * volume
 */
class _volume extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'volume_manage' );
		parent::execute();
	}

	/**
	 * View
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'nexus', 'Support' )->getChart( \IPS\Http\Url::internal( "app=nexus&module=support&controller=volume" ) );
		\IPS\Output::i()->output = (string) $chart;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_support_volume');
	}
}
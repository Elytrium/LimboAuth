<?php
/**
 * @brief		Tag Usage
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Jan 2018
 */

namespace IPS\core\modules\admin\activitystats;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Tag Usage
 */
class _tags extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'tagsusage_manage' );
		parent::execute();
	}

	/**
	 * Show a graph of tag usage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$chart = \IPS\core\Statistics\Chart::loadFromExtension( 'core', 'Tags' )->getChart( \IPS\Http\Url::internal( "app=core&module=activitystats&controller=tags" ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_activitystats_tags');
		\IPS\Output::i()->output = (string) $chart;
	}
}
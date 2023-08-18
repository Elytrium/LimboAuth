<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		15 Jan 2015
 */

namespace IPS\cms\setup\upg_100015;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 RC 2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		foreach ( \IPS\Db::i()->select( 'database_id', 'cms_databases', 'database_page_id>0' ) as $id )
		{
			\IPS\Task::queue( 'core', 'RebuildItems', array( 'class' => 'IPS\cms\Records' . $id ), 3, array( 'class' ) );
			\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\cms\Records' . $id, 'count' => 0 ), 4, array( 'class' ) );
			\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\cms\Categories' . $id, 'count' => 0 ), 5, array( 'class' ) );
		}
	}
}
<?php
/**
 * @brief		4.1.16 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
{subpackage}
 * @since		28 Sep 2016
 */

namespace IPS\cms\setup\upg_101064;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.16 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Rebuild the Reviews count
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Db::i()->select( '*', 'cms_databases') as $db )
		{
			\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\cms\Records' . $db['database_id'], 'count' => 0 ), 4, array( 'class' ) );
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
<?php
/**
 * @brief		4.6.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		04 Mar 2021
 */

namespace IPS\cms\setup\upg_106100;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.6.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Initialize a search index rebuild for Pages
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Task::queue( 'core', 'RebuildSearchIndex', array( 'class' => 'IPS\cms\Pages\PageItem' ), 5 );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Rebuilding pages search index';
	}


	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
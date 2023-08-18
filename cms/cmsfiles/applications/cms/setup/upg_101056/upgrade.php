<?php
/**
 * @brief		4.1.15 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		25 Aug 2016
 */

namespace IPS\cms\setup\upg_101056;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.15 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix star ratings which haven't been averaged correctly.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		\IPS\Db::i()->update( 'cms_databases', array( 'database_field_sort' => 'record_rating' ), array( 'database_field_sort=?', 'rating_real' ) );
		
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $database )
		{
			try
			{
				\IPS\Task::queue( 'core', 'RecountStarRatings', array( 'class' => 'IPS\cms\Records' . $database['database_id'] ), 3 );
			}
			catch ( \OutOfRangeException $ex ) { }
		}

		return TRUE;
	}
}
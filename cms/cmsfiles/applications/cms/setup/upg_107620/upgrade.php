<?php
/**
 * @brief		4.7.7 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		01 Nov 2022
 */

namespace IPS\cms\setup\upg_107620;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.7.7 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	bool|array 	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
        foreach( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) as $database )
        {
            \IPS\Db::i()->update( 'cms_custom_database_' . $database, 'record_saved=record_publish_date', array( 'record_publish_date is not null and record_publish_date > ?', 0 ) );
        }

		return TRUE;
	}

	/**
	 * Check and fix database schema
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		foreach( \IPS\cms\Databases::databases() as $id => $db )
		{
			try
			{
				\IPS\cms\Databases::checkandFixDatabaseSchema( $id );
			}
			catch( \Exception $e ) { }
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return 'Fixing CMS Tables';
	}


		// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}
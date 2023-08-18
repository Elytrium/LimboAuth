<?php
/**
 * @brief		4.0.0 Beta 3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		24 Nov 2014
 */

namespace IPS\cms\setup\upg_100003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 3 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Remove any fulltext indices
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( 'database_id, database_database', 'cms_databases' ) as $database )
		{
			if( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $database['database_id'] ) )
			{
				$tableDefinition	= \IPS\Db::i()->getTableDefinition( 'cms_custom_database_' . $database['database_id'] );

				foreach( $tableDefinition['indexes'] as $name => $indexDefinition )
				{
					if( $indexDefinition['type'] == 'fulltext' )
					{
						\IPS\Db::i()->dropIndex( 'cms_custom_database_' . $database['database_id'], $indexDefinition['name'] );
					}
				}
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Removing unused fulltext indices";
	}
}
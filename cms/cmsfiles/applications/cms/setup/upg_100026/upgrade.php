<?php
/**
 * @brief		4.0.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 Apr 2015
 */

namespace IPS\cms\setup\upg_100026;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Add record_comments_hidden field
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $database )
		{
			if ( ! \IPS\Db::i()->checkForColumn( 'cms_custom_database_' . $database['database_id'], 'record_comments_hidden' ) )
			{
				\IPS\Db::i()->addColumn( 'cms_custom_database_' . $database['database_id'], array(
					"name"		=> "record_comments_hidden",
					"type"		=> "INT",
					"length"	=> 10,
					"null"		=> true,
					"default"	=> null,
					"comment"	=> "",
					"unsigned"	=> true
				)	);
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
		return "Correcting custom database definitions";
	}
}
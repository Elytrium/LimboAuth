<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		08 Jan 2015
 */

namespace IPS\cms\setup\upg_11003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Upgrade
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach( \IPS\Db::i()->select( '*', 'ccs_databases' ) as $r )
		{
			\IPS\Db::i()->addColumn( $r['database_database'], array(
				"name"		=> "record_approved",
				"type"		=> "TINYINT",
				"length"	=> 1,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
			)	);

			\IPS\Db::i()->addColumn( $r['database_database'], array(
				"name"		=> "record_pinned",
				"type"		=> "TINYINT",
				"length"	=> 1,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
			)	);

			\IPS\Db::i()->addColumn( $r['database_database'], array(
				"name"		=> "record_views",
				"type"		=> "MEDIUMINT",
				"length"	=> 8,
				"null"		=> false,
				"default"	=> 0,
				"comment"	=> "",
				"unsigned"	=> false
			)	);

			\IPS\Db::i()->addIndex( $r['database_database'], array(
				'type'			=> 'key',
				'name'			=> 'record_approved',
				'columns'		=> array( 'record_approved' )
			) );

			\IPS\Db::i()->addIndex( $r['database_database'], array(
				'type'			=> 'key',
				'name'			=> 'category_id',
				'columns'		=> array( 'category_id' )
			) );
		}
		
		return TRUE;
	}
}
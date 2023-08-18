<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Jun 2014
 */

namespace IPS\core\setup\upg_34005;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrade steps
 */
class _Upgrade
{
	/**
	 * Step 1
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if( !\IPS\Db::i()->checkForIndex( 'members_warn_logs', 'wl_expire' ) )
		{
			\IPS\Db::i()->addIndex( 'members_warn_logs', array(
				'type'			=> 'key',
				'name'			=> 'wl_expire',
				'columns'		=> array( 'wl_expire', 'wl_expire_date', 'wl_date' )
			) );
		}

		if( !\IPS\Db::i()->checkForTable( 'search_keywords' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'search_keywords',
				'columns'	=> array(
					array(
						'name'			=> 'keyword',
						'type'			=> 'varchar',
						'length'		=> 250,
						'allow_null'	=> false
					),
					array(
						'name'			=> 'count',
						'type'			=> 'int',
						'length'		=> 11,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'unique',
						'name'		=> 'idx_keyword_unq',
						'columns'	=> array( 'keyword' ),
						'length'	=> array( 190 )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'idx_kw_cnt',
						'columns'	=> array( 'keyword', 'count' ),
						'length'	=> array( 180, null )
					),
				)
			)	);
		}

		if( !\IPS\Db::i()->checkForTable( 'search_visitors' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'search_visitors',
				'columns'	=> array(
					array(
						'name'			=> 'id',
						'type'			=> 'int',
						'length'		=> 11,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'member',
						'type'			=> 'int',
						'length'		=> 11,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'date',
						'type'			=> 'int',
						'length'		=> 11,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'engine',
						'type'			=> 'varchar',
						'length'		=> 50,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'keywords',
						'type'			=> 'varchar',
						'length'		=> 250,
						'allow_null'	=> false,
					),
					array(
						'name'			=> 'url',
						'type'			=> 'varchar',
						'length'		=> 2048,
						'allow_null'	=> false,
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'id' )
					),
					array(
						'type'		=> 'key',
						'name'		=> 'idx_date_engine',
						'columns'	=> array( 'date', 'engine' )
					),
				)
			)	);
		}

		/* Finish */
		return TRUE;
	}
}
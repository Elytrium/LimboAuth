<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Jun 2014
 */

namespace IPS\core\setup\upg_32001;

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
		\IPS\Db::i()->changeColumn( 'cache_simple', 'cache_data', array(
			'name'			=> 'cache_data',
			'type'			=> 'mediumtext',
			'length'		=> null,
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->update( 'groups', "g_photo_max_vars=REPLACE(g_photo_max_vars, ':150:150', ':200:300')", "g_photo_max_vars LIKE '%:150:150'" );

		if( !\IPS\Db::i()->checkForTable('skin_generator_sessions') )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'skin_generator_sessions',
				'columns'	=> array(
					array(
						'name'			=> 'sg_session_id',
						'type'			=> 'varchar',
						'length'		=> 32,
						'allow_null'	=> false,
						'default'		=> ''
					),
					array(
						'name'			=> 'sg_member_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_skin_set_id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_date_start',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'sg_data',
						'type'			=> 'mediumtext',
						'allow_null'	=> true,
						'default'		=> null
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'sg_session_id' )
					),
				)
			)	);
		}

		\IPS\Db::i()->addColumn( 'skin_collections', array(
			'name'			=> 'set_by_skin_gen',
			'type'			=> 'int',
			'length'		=> 1,
			'allow_null'	=> false,
			'default'		=> 0
		) );

		\IPS\Db::i()->addColumn( 'skin_collections', array(
			'name'			=> 'set_skin_gen_data',
			'type'			=> 'mediumtext',
			'allow_null'	=> true,
			'default'		=> null
		) );

		\IPS\Db::i()->insert( 'core_share_links', array( 'share_title' => 'Google+', 'share_key' => 'googleplusone', 'share_enabled' => 1, 'share_position' => 2, 'share_canonical' => 1 ) );
		\IPS\Db::i()->delete( 'core_share_links', "share_key='buzz'" );

		/* Finish */
		return TRUE;
	}
}
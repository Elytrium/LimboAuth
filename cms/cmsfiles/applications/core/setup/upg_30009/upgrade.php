<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_30009;

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
		if( \IPS\Db::i()->checkForColumn( 'profile_portal', 'fb_status' ) )
		{
			\IPS\Db::i()->dropColumn( 'profile_portal', 'fb_status' );
		}

		if( !\IPS\Db::i()->checkForColumn( 'core_applications', 'app_hide_tab' ) )
		{
			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_hide_tab',
				'type'			=> 'tinyint',
				'length'		=> 1,
				'allow_null'	=> false,
				'default'		=> 0
			) );
		}

		if( !\IPS\Db::i()->checkForColumn( 'core_applications', 'app_tab_groups' ) )
		{
			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_tab_groups',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_website',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_update_check',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );

			\IPS\Db::i()->addColumn( 'core_applications', array(
				'name'			=> 'app_global_caches',
				'type'			=> 'varchar',
				'length'		=> 255,
				'allow_null'	=> true,
				'default'		=> null
			) );
		}

		\IPS\Db::i()->delete( 'task_manager', "task_key IN ('doexpiresubs', 'expiresubs') AND task_application != 'subscriptions'" );

		\IPS\Db::i()->createTable( array(
			'name'		=> 'spam_service_log',
			'columns'	=> array(
				array(
					'name'			=> 'id',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'auto_increment'	=> true
				),
				array(
					'name'			=> 'log_date',
					'type'			=> 'int',
					'length'		=> 10,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'log_code',
					'type'			=> 'smallint',
					'length'		=> 1,
					'unsigned'		=> true,
					'allow_null'	=> false,
					'default'		=> 0
				),
				array(
					'name'			=> 'log_msg',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'email_address',
					'type'			=> 'varchar',
					'length'		=> 255,
					'allow_null'	=> false
				),
				array(
					'name'			=> 'ip_address',
					'type'			=> 'varchar',
					'length'		=> 32,
					'allow_null'	=> false
				),
			),
			'indexes'	=> array(
				array(
					'type'		=> 'primary',
					'columns'	=> array( 'id' )
				)
			)
		)	);

		/* Finish */
		return TRUE;
	}
}
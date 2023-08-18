<?php
/**
 * @brief		Upgrade steps
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 May 2014
 */

namespace IPS\core\setup\upg_31004;

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
		if ( ! isset( \IPS\Request::i()->run_anyway ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'mobile_notifications',
				'columns'	=> array(
					array(
						'name'			=> 'id',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> null,
						'auto_increment'	=> true
					),
					array(
						'name'			=> 'notify_title',
						'type'			=> 'text',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					array(
						'name'			=> 'notify_date',
						'type'			=> 'int',
						'length'		=> 10,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'member_id',
						'type'			=> 'mediumint',
						'length'		=> 8,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
					array(
						'name'			=> 'notify_sent',
						'type'			=> 'tinyint',
						'length'		=> 3,
						'unsigned'		=> true,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					array(
						'type'		=> 'primary',
						'columns'	=> array( 'id' )
					)
				)
			)	);
	
			if( \IPS\Db::i()->checkForIndex( 'rc_classes', 'group_perms' ) )
			{
				\IPS\Db::i()->dropIndex( 'rc_classes', 'group_perms' );
			}
	
			if( \IPS\Db::i()->checkForIndex( 'rc_classes', 'onoff' ) )
			{
				\IPS\Db::i()->dropIndex( 'rc_classes', 'onoff' );
			}
	
			\IPS\Db::i()->changeColumn( 'rc_classes', 'group_can_report', array(
				'name'			=> 'group_can_report',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->changeColumn( 'rc_classes', 'mod_group_perm', array(
				'name'			=> 'mod_group_perm',
				'type'			=> 'text',
				'length'		=> null,
				'allow_null'	=> true,
				'default'		=> null
			) );
	
			\IPS\Db::i()->addIndex( 'rc_classes', array(
				'type'			=> 'key',
				'name'			=> 'onoff',
				'columns'		=> array( 1 => 'onoff', 2 => 'mod_group_perm' ),
				'length'		=> array( 1 => null, 2 => 255 ),
			) );
	
			\IPS\Db::i()->dropColumn( 'pfields_content', 'updated' );
		}
		
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'members',
			'query' => "ALTER TABLE " . \IPS\Db::i()->prefix . "members ADD COLUMN ips_mobile_token VARCHAR(64) NULL DEFAULT NULL;"
		) ) );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		/* Finish */
		return TRUE;
	}
}
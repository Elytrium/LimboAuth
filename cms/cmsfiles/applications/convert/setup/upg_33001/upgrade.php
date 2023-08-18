<?php
/**
 * @brief		1.2.3 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Converter
 * @since		01 Feb 2017
 */

namespace IPS\convert\setup\upg_33001;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 1.2.3 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Create extra conv_link tables
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( !\IPS\Db::i()->checkForTable( 'conv_link_pms' ) )
		{
			\IPS\Db::i()->createTable( array (
				'name'		=> 'conv_link_pms',
				'columns'	=> array(
					'link_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> true,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 10,
						'name'				=> 'link_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'ipb_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'ipb_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'foreign_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'foreign_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'type' => array(
						'allow_null'		=> true,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 32,
						'name'				=> 'type',
						'type'				=> 'VARCHAR',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'duplicate' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 1,
						'name'				=> 'duplicate',
						'type'				=> 'TINYINT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'app' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'app',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
				),
				'indexes' => array(
				    'PRIMARY' => array(
						'type' => 'primary',
						'name' => 'PRIMARY',
						'length' => array( 0 => NULL ),
						'columns' => array( 0 => 'link_id' ),
					),
				)
			) );
		}
		
		if ( !\IPS\Db::i()->checkForTable( 'conv_link_topics' ) )
		{
			\IPS\Db::i()->createTable( array (
				'name'		=> 'conv_link_topics',
				'columns'	=> array(
					'link_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> true,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 10,
						'name'				=> 'link_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'ipb_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'ipb_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'foreign_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'foreign_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'type' => array(
						'allow_null'		=> true,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 32,
						'name'				=> 'type',
						'type'				=> 'VARCHAR',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'duplicate' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 1,
						'name'				=> 'duplicate',
						'type'				=> 'TINYINT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'app' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'app',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
				),
				'indexes' => array(
				    'PRIMARY' => array(
						'type' => 'primary',
						'name' => 'PRIMARY',
						'length' => array( 0 => NULL ),
						'columns' => array( 0 => 'link_id' ),
					),
				)
			) );
		}
		
		if ( !\IPS\Db::i()->checkForTable( 'conv_link_posts' ) )
		{
			\IPS\Db::i()->createTable( array (
				'name'		=> 'conv_link_posts',
				'columns'	=> array(
					'link_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> true,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 10,
						'name'				=> 'link_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'ipb_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'ipb_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'foreign_id' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'foreign_id',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'type' => array(
						'allow_null'		=> true,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> NULL,
						'length'			=> 32,
						'name'				=> 'type',
						'type'				=> 'VARCHAR',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'duplicate' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 1,
						'name'				=> 'duplicate',
						'type'				=> 'TINYINT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
					'app' => array(
						'allow_null'		=> false,
						'auto_increment'	=> false,
						'binary'			=> false,
						'comment'			=> '',
						'decimals'			=> NULL,
						'default'			=> 0,
						'length'			=> 10,
						'name'				=> 'app',
						'type'				=> 'INT',
						'unsigned'			=> false,
						'values'			=> array(),
						'zerofill'			=> false,
					),
				),
				'indexes' => array(
				    'PRIMARY' => array(
						'type' => 'primary',
						'name' => 'PRIMARY',
						'length' => array( 0 => NULL ),
						'columns' => array( 0 => 'link_id' ),
					),
				)
			) );
		}
		return TRUE;
	}
}
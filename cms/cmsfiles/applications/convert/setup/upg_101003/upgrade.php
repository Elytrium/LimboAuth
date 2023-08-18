<?php


namespace IPS\convert\setup\upg_101003;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix 3.x Converter Columns
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries = array();
		
		/* This does not include all of the columns the 4.x Converters use - only the ones set by the 3.x converters */
		$old = array(
			'downloads'	=> array(
				array(
					'table'		=> 'downloads_categories',
					'old'		=> 'conv_parent',
					'new'		=> 'cconv_parent'
				)
			),
			'gallery'	=> array(
				array(
					'table'		=> 'gallery_categories',
					'old'		=> 'conv_parent',
					'new'		=> 'category_conv_parent'
				)
			),
			'cms'		=> array(
				array(
					'table'		=> 'cms_database_categories',
					'old'		=> 'conv_parent',
					'new'		=> 'category_conv_parent',
				)
			),
			'nexus'		=> array(
				array(
					'table'		=> 'nexus_package_groups',
					'old'		=> 'conv_pg_parent',
					'new'		=> 'pg_conv_parent'
				),
				array(
					'table'		=> 'nexus_packages',
					'old'		=> 'conv_p_associable',
					'new'		=> 'p_conv_associable'
				),
				array(
					'table'		=> 'nexus_purchases',
					'old'		=> 'conv_ps_parent',
					'new'		=> 'ps_conv_parent'
				)
			)
		);
		
		foreach( $old AS $app => $tables )
		{
			foreach( $tables AS $data )
			{
				/* Does the table and old column exist? */
				if ( \IPS\Db::i()->checkForTable( $data['table'] ) AND \IPS\Db::i()->checkForColumn( $data['table'], $data['old'] ) )
				{
					/* Does the new column exist? */
					if ( \IPS\Db::i()->checkForColumn( $data['table'], $data['new'] ) )
					{
						/* Drop it */
						\IPS\Db::i()->dropColumn( $data['table'], $data['new'] );
					}
					
					/* Alter the old one */
					\IPS\Db::i()->changeColumn( $data['table'], $data['old'], array(
						'name'		=> $data['new'],
						'type'		=> 'BIGINT',
						'length'	=> 20,
						'default'	=> 0
					) );
				}
			}
		}
		
		return TRUE;
	}
}
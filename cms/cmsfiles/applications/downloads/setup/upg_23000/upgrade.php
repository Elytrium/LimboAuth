<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		24 Oct 2014
 */

namespace IPS\downloads\setup\upg_23000;

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
	 * Convert follows
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$members	= array();

		foreach( \IPS\Db::i()->select( '*', 'downloads_favorites' ) as $favorite )
		{
			$members[ $favorite['ffid'] ][ $favorite['fmid'] ]	= array(
				'follow_rel_id'		=> $favorite['ffid'],
				'follow_member_id'	=> $favorite['fmid'],
				'follow_notify_do'	=> 0,
			);
		}

		foreach( \IPS\Db::i()->select( 'file_id, file_sub_mems', 'downloads_files', "file_sub_mems != '' AND file_sub_mems IS NOT NULL" ) as $file )
		{
			$_subscriptions	= explode( ',', trim( $r['file_sub_mems'], ',' ) );

			if( \count( $_subscriptions ) )
			{
				foreach( $_subscriptions as $mid )
				{
					if( isset( $members[ $file['file_id'] ][ $mid ]) )
					{
						$members[ $file['file_id'] ][ $mid ]['follow_notify_do']	= 1;
					}
					else
					{
						$members[ $file['file_id'] ][ $mid ]	= array(
																	'follow_rel_id'		=> $file['file_id'],
																	'follow_member_id'	=> $mid,
																	'follow_notify_do'	=> 1,
																	);
					}
				}
			}
		}

		if( \count( $members ) )
		{
			foreach( $members as $file => $_members )
			{
				foreach( $_members as $member )
				{
					if( !$member['follow_member_id'] )
					{
						continue;
					}

					\IPS\Db::i()->insert( 'core_follow', array(
						'follow_id'				=> md5( 'downloads;files;' . $member['follow_rel_id'] . ';' .  $member['follow_member_id'] ),
						'follow_app'			=> 'downloads',
						'follow_area'			=> 'files',
						'follow_rel_id'			=> $member['follow_rel_id'],
						'follow_member_id'		=> $member['follow_member_id'],
						'follow_is_anon'		=> 0,
						'follow_added'			=> time(),
						'follow_notify_do'		=> $member['follow_notify_do'],
						'follow_notify_freq'	=> 'immediate',
						'follow_visible'		=> 1,
					)	);
				}
			}
		}
		
		return TRUE;
	}

	/**
	 * Clean up
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( \IPS\Db::i()->checkForColumn( 'downloads_files', 'file_meta' ) )
		{
			\IPS\Db::i()->dropColumn( 'downloads_files', 'file_meta' );
		}

		\IPS\Db::i()->dropTable( 'downloads_favorites' );
		\IPS\Db::i()->dropColumn( 'downloads_files', 'file_sub_mems' );

		return true;
	}
}
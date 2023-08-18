<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		26 Dec 2013
 */

namespace IPS\downloads\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Downloads
{
	/**
	 * Member is merged with another member
	 *
	 * @param	\IPS\Member	$member		Member being kept
	 * @param	\IPS\Member	$member2	Member being removed
	 * @return	void
	 */
	public function onMerge( $member, $member2 )
	{
		\IPS\Db::i()->update( 'downloads_downloads', array( 'dmid' => $member->member_id ), array( 'dmid=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'downloads_files', array( 'file_approver' => $member->member_id ), array( 'file_approver=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'downloads_files_notify', array( 'notify_member_id' => $member->member_id ), array( 'notify_member_id=?', $member2->member_id ) );
		\IPS\Db::i()->delete( 'downloads_sessions', array( 'dsess_mid=?', $member2->member_id ) );

		/* Clean up duplicate notify rows */
		$unique		= array();
		$duplicate	= array();

		foreach( \IPS\Db::i()->select( 'downloads_files_notify.*, downloads_files.file_submitter', 'downloads_files_notify', array( 'notify_member_id=?', $member->member_id ) )->join( 'downloads_files', "downloads_files_notify.notify_file_id=downloads_files.file_id" ) as $notify )
		{
			if( !\in_array( $notify['notify_file_id'], $unique ) and $notify['notify_member_id'] !== $notify['file_submitter'] )
			{
				$unique[]	= $notify['notify_file_id'];
			}
			else
			{
				$duplicate[]	= $notify['notify_id'];
			}
		}

		if( \count( $duplicate ) )
		{
			\IPS\Db::i()->delete( 'downloads_files_notify', array( "notify_id IN('" . implode( "','", $duplicate ) . "')" ) );
		}
	}
	
	/**
	 * Member is deleted
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onDelete( $member )
	{
		\IPS\Db::i()->delete( 'downloads_downloads', array( 'dmid=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'downloads_sessions', array( 'dsess_mid=?', $member->member_id ) );
		\IPS\Db::i()->update( 'downloads_files', array( 'file_approver' => 0 ), array( 'file_approver=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'downloads_files_notify', array( 'notify_member_id=?', $member->member_id ) );
	}
}
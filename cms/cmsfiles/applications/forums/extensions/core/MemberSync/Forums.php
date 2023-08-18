<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		14 Jul 2014
 */

namespace IPS\forums\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Forums
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
		\IPS\Db::i()->update( 'forums_answer_ratings', array( 'member' => $member->member_id ), array( '`member`=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'forums_question_ratings', array( 'member' => $member->member_id ), array( '`member`=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		\IPS\Db::i()->update( 'forums_view_method', array( 'member_id' => $member->member_id ), array( 'member_id=?', $member2->member_id ), array(), NULL, \IPS\Db::IGNORE );
		
		if ( \IPS\Settings::i()->archive_on )
		{
			/* Connect to the remote DB if needed */
			if ( \IPS\CIC2 )
			{
				$archiveStorage = \IPS\Cicloud\getForumArchiveDb();
			}
			else
			{
				$archiveStorage = ( !\IPS\Settings::i()->archive_remote_sql_host ) ? \IPS\Db::i() : \IPS\Db::i( 'archive', array(
					'sql_host'		=> \IPS\Settings::i()->archive_remote_sql_host,
					'sql_user'		=> \IPS\Settings::i()->archive_remote_sql_user,
					'sql_pass'		=> \IPS\Settings::i()->archive_remote_sql_pass,
					'sql_database'	=> \IPS\Settings::i()->archive_remote_sql_database,
					'sql_port'		=> \IPS\Settings::i()->archive_sql_port,
					'sql_socket'	=> \IPS\Settings::i()->archive_sql_socket,
					'sql_tbl_prefix'=> \IPS\Settings::i()->archive_sql_tbl_prefix,
					'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
				) );
			}
			$archiveStorage->update( 'forums_archive_posts', array( 'archive_author_id' => $member->member_id ), array( 'archive_author_id=?', $member2->member_id ) );
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
		\IPS\Db::i()->delete( 'forums_answer_ratings', array( '`member`=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'forums_question_ratings', array( '`member`=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'forums_view_method', array( 'member_id=?', $member->member_id ) );
	}
}
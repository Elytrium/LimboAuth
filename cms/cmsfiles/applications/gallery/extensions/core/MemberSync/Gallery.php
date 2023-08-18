<?php
/**
 * @brief		Member Sync
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Member Sync
 */
class _Gallery
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
		\IPS\Db::i()->update( 'gallery_albums', array( 'album_owner_id' => $member->member_id ), array( 'album_owner_id=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'gallery_bandwidth', array( 'member_id' => $member->member_id ), array( 'member_id=?', $member2->member_id ) );
		\IPS\Db::i()->update( 'gallery_images_uploads', array( 'upload_member_id' => $member->member_id ), array( 'upload_member_id=?', $member2->member_id ) );

		foreach( \IPS\gallery\Album::loadByOwner( $member2 ) as $album )
		{
			$album->owner_id	= $member->member_id;
			$album->save();
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
		\IPS\Db::i()->delete( 'gallery_bandwidth', array( 'member_id=?', $member->member_id ) );
		\IPS\Db::i()->delete( 'gallery_images_uploads', array( 'upload_member_id=?', $member->member_id ) );

		foreach( \IPS\gallery\Album::loadByOwner( $member ) as $album )
		{
			//$album->delete();
			$album->owner_id = 0;
			$album->save();
		}
	}

	/**
	 * Member is flagged as spammer
	 *
	 * @param	$member	\IPS\Member	The member
	 * @return	void
	 */
	public function onSetAsSpammer( $member )
	{
		$actions = explode( ',', \IPS\Settings::i()->spm_option );

		/* Hide or delete */
		if ( \in_array( 'unapprove', $actions ) or \in_array( 'delete', $actions ) )
		{
			foreach( \IPS\gallery\Album::loadByOwner( $member ) as $album )
			{
				$album->hidden = -1;
				$album->save();

				if( \in_array( 'delete', $actions ) )
				{
					\IPS\Task::queue( 'core', 'DeleteOrMoveContent', array( 'class' => 'IPS\gallery\Album', 'id' => $album->id, 'deleteWhenDone' => TRUE ), 3 );
				}
			}
		}
	}
}
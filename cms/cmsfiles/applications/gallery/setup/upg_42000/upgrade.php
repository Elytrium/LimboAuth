<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		24 Oct 2014
 */

namespace IPS\gallery\setup\upg_42000;

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
	 * Reset member's album category
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$membersAlbum		= $_SESSION['upgrade_options']['gallery']['42000']['members_album'];
		$newMembersAlbum	= $_SESSION['upgrade_options']['gallery']['42000']['new_members_album'];

		/* making a new one? */
		if ( !$newMembersAlbum )
		{
			/* Update array */
			$save = array( 'album_name'				=> 'Temp global album for root member albums',
						   'album_name_seo'			=> 'temp-global-album-for-root-member-albums',
						   'album_description'		=> "This is a temporary global album that holds the member albums that didn't have the proper parent album set. This album has NO permissions and is not visible from the public side, please move the albums in the proper location.",
						   'album_parent_id'		=> 0,
						   'album_is_public'		=> 0,
						   'album_is_global'		=> 1,
						   'album_g_container_only'	=> 0,
						   'album_allow_comments'	=> 0,
						   'album_g_approve_com'	=> 0,
						   'album_g_approve_img'	=> 0,
						   'album_sort_options'		=> serialize( array( 'key' => 'ASC', 'dir' => 'idate' ) ),
						   'album_detail_default'	=> 0,
						   'album_after_forum_id'	=> 0,
						   'album_watermark'		=> 0 );
			
			$membersAlbum = \IPS\Db::i()->insert( 'gallery_albums_main', $save );
		}
		
		/* Now move the stuffs */
		if ( $membersAlbum )
		{
			/* Tag this as member album for later update */
			\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_g_perms_thumbs' => 'member' ), 'album_id=' . \intval( $membersAlbum ) );
			
			/* Update albums and reset permissions/node tree.. */
			\IPS\Db::i()->update( 'gallery_albums_main', array( 'album_parent_id' => $membersAlbum ), 'album_parent_id=0 AND album_is_global=0' );
		}

		return TRUE;
	}
}
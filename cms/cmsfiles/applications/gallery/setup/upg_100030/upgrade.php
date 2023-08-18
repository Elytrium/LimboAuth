<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		18 Feb 2015
 */

namespace IPS\gallery\setup\upg_100030;

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
	 * Fix gallery images category_id
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 250;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff = \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'gallery_albums', NULL, 'album_id ASC', array( $limit, $perCycle ) ) as $album )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			if ( $album['album_category_id'] )
			{
				\IPS\Db::i()->update( 'gallery_images', array( 'image_category_id' => $album['album_category_id'] ), array( 'image_album_id=?', $album['album_id'] ) );
			}
		}

		/* And then continue */
		if( $did AND $did == $perCycle )
		{
			return $limit + $did;
		}
		else
		{
			unset( $_SESSION['_step1Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step1Count'] ) )
		{
			$_SESSION['_step1Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'gallery_albums' )->first();
		}

		return "Updating gallery album images (Updated so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
	}
}
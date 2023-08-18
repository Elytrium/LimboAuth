<?php
/**
 * @brief		4.0.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		30 Oct 2014
 */

namespace IPS\gallery\setup\upg_100002;

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
	 * Fix SEO titles
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Older installs may not allow NULL */
		$albums = \IPS\Db::i()->getTableDefinition( 'gallery_albums', TRUE );

		if( !$albums['columns']['album_name_seo']['allow_null'] )
		{
			\IPS\Db::i()->dropIndex( 'gallery_albums', 'album_parent_id' );
			\IPS\Db::i()->changeColumn( 'gallery_albums', 'album_name_seo', array( 'name' => 'album_name_seo', 'type' => 'VARCHAR', 'length' => 255, 'allow_null' => TRUE, 'default' => null ) );
			\IPS\Db::i()->addIndex( 'gallery_albums', array(
				'type'			=> 'key',
				'name'			=> 'album_parent_id',
				'columns'		=> array( 'album_category_id', 'album_name_seo' ),
				'length'		=> array( null, 181 )
			) );
		}

		$images = \IPS\Db::i()->getTableDefinition( 'gallery_images', TRUE );

		if( !$images['columns']['image_caption_seo']['allow_null'] )
		{
			\IPS\Db::i()->changeColumn( 'gallery_images', 'image_caption_seo', array( 'name' => 'image_caption_seo', 'type' => 'VARCHAR', 'length' => 255, 'allow_null' => TRUE, 'default' => null ) );
		}

		\IPS\Db::i()->update( 'gallery_albums', array( 'album_name_seo' => null ) );
		\IPS\Db::i()->update( 'gallery_categories', array( 'category_name_seo' => null ) );
		\IPS\Db::i()->update( 'gallery_images', array( 'image_caption_seo' => null ) );

		return true;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Resetting gallery friendly URL titles";
	}
}
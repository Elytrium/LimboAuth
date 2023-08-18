<?php
/**
 * @brief		4.1.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		25 Sep 2015
 */

namespace IPS\gallery\setup\upg_101000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix images in the root uploads directory as they'll get stripped by the orphaned attachments tool
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$toRunQueries	= array(
			array(
				'table'	=> 'gallery_images',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "gallery_images SET image_masked_file_name=SUBSTR( image_masked_file_name, 2 ) WHERE image_masked_file_name LIKE '/%'",
			),
			array(
				'table'	=> 'gallery_images',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "gallery_images SET image_medium_file_name=SUBSTR( image_medium_file_name, 2 ) WHERE image_medium_file_name LIKE '/%'",
			),
			array(
				'table'	=> 'gallery_images',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "gallery_images SET image_original_file_name=SUBSTR( image_original_file_name, 2 ) WHERE image_original_file_name LIKE '/%'",
			),
			array(
				'table'	=> 'gallery_images',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "gallery_images SET image_thumb_file_name=SUBSTR( image_thumb_file_name, 2 ) WHERE image_thumb_file_name LIKE '/%'",
			),
			array(
				'table'	=> 'gallery_images',
				'query'	=> "UPDATE " . \IPS\Db::i()->prefix . "gallery_images SET image_small_file_name=SUBSTR( image_small_file_name, 2 ) WHERE image_small_file_name LIKE '/%'",
			),
		);

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 2 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing broken gallery image paths";
	}
}
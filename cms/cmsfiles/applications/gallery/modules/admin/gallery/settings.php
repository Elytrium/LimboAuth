<?php
/**
 * @brief		Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		04 Mar 2014
 */

namespace IPS\gallery\modules\admin\gallery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );
		parent::execute();
	}

	/**
	 * Manage settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = $this->_getForm();

		if ( $values = $form->values() )
		{
			$this->_saveSettingsForm( $form, $values );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=gallery&module=gallery&controller=settings' ), 'saved' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output = $form;
	}

	/**
	 * Build and return the settings form
	 *
	 * @note	Abstracted to allow third party devs to extend easier
	 * @return	\IPS\Helpers\Form
	 */
	protected function _getForm()
	{
		$form = new \IPS\Helpers\Form;

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_settings.js', 'gallery', 'admin' ) );
		$form->attributes['data-controller'] = 'gallery.admin.settings.settings';
		$form->hiddenValues['rebuildWatermarkScreenshots'] = \IPS\Request::i()->rebuildWatermarkScreenshots ?: 0;

		$form->addTab( 'basic_settings' );
		$form->addHeader( 'gallery_images' );
		$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack('gallery_dims_explanation'), '', FALSE );
		$large	= ( isset( \IPS\Settings::i()->gallery_large_dims ) ) ? explode( 'x', \IPS\Settings::i()->gallery_large_dims ) : array( 1600, 1200 );
		$small	= ( isset( \IPS\Settings::i()->gallery_small_dims ) ) ? explode( 'x', \IPS\Settings::i()->gallery_small_dims ) : array( 240, 240 );
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'gallery_large_dims', $large, TRUE, array( 'resizableDiv' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'gallery_small_dims', $small, TRUE, array( 'resizableDiv' => FALSE ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_use_square_thumbnails', \IPS\Settings::i()->gallery_use_square_thumbnails ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_use_watermarks', \IPS\Settings::i()->gallery_use_watermarks, FALSE, array( 'togglesOn' => array( 'gallery_watermark_path', 'gallery_watermark_images' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'gallery_watermark_path', \IPS\Settings::i()->gallery_watermark_path ? \IPS\File::get( 'core_Theme', \IPS\Settings::i()->gallery_watermark_path ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Theme' ), NULL, NULL, NULL, 'gallery_watermark_path' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'gallery_watermark_images',
			\IPS\Settings::i()->gallery_watermark_images ? explode( ',', \IPS\Settings::i()->gallery_watermark_images ) : array(),
			FALSE,
			array(
				'multiple'			=> TRUE,
				'options'			=> array( 'large' => 'gallery_watermark_large', 'small' => 'gallery_watermark_small' ),
			),
			NULL,
			NULL,
			NULL,
			'gallery_watermark_images'
		) );

		$form->addHeader( 'gallery_bandwidth' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_detailed_bandwidth', \IPS\Settings::i()->gallery_detailed_bandwidth ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'gallery_bandwidth_period', \IPS\Settings::i()->gallery_bandwidth_period, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::HOURS, 'min' => NULL, 'unlimited' => -1 ), NULL, NULL, NULL, 'easypost_delivery_adjustment' ) );

		$form->addHeader( 'gallery_options' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_rss_enabled', \IPS\Settings::i()->gallery_rss_enabled ) );

		if( \IPS\GeoLocation::enabled() )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_maps_default', \IPS\Settings::i()->gallery_maps_default ) );
		}

		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_nsfw', \IPS\Settings::i()->gallery_nsfw ) );

		$form->addTab( 'gallery_overview_settings' );
		$form->addHeader( 'gallery_featured_images' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_overview_show_carousel', \IPS\Settings::i()->gallery_overview_show_carousel, FALSE, array( 'togglesOn' => array( 'gallery_overview_carousel_count', 'gallery_overview_carousel_type' ) ) ) );
		$options = array(
			'featured'		=> 'gallery_overview_carousel_featured',
			'new'		=> 'gallery_overview_carousel_new'
		);
		$form->add( new \IPS\Helpers\Form\Radio( 'gallery_overview_carousel_type', \IPS\Settings::i()->gallery_overview_carousel_type, TRUE, array( 'options'	=> $options ), NULL, NULL, NULL, 'gallery_overview_carousel_type' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'gallery_overview_carousel_count', \IPS\Settings::i()->gallery_overview_carousel_count, FALSE, array(), NULL, NULL, NULL, 'gallery_overview_carousel_count' ) );

		$form->addHeader( 'gallery_overview_recent_comments' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_show_recent_comments', \IPS\Settings::i()->gallery_show_recent_comments, FALSE, array() ) );

		$form->addHeader('gallery_overview_categories');
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_overview_show_categories', \IPS\Settings::i()->gallery_overview_show_categories, FALSE, array() ) );

		$form->addHeader( 'gallery_overview_recent_updated_albums' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_show_recent_updated_albums', \IPS\Settings::i()->gallery_show_recent_updated_albums, FALSE, array( 'togglesOn' => array( 'gallery_recent_updated_albums_count' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'gallery_recent_updated_albums_count', \IPS\Settings::i()->gallery_recent_updated_albums_count, FALSE, array(), NULL, NULL, NULL, 'gallery_recent_updated_albums_count' ) );

		$form->addHeader( 'gallery_overview_new_images' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'gallery_show_new_images', \IPS\Settings::i()->gallery_show_new_images, FALSE, array( 'togglesOn' => array( 'gallery_new_images_count' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'gallery_new_images_count', \IPS\Settings::i()->gallery_new_images_count, FALSE, array(), NULL, NULL, NULL, 'gallery_new_images_count' ) );

		return $form;
	}

	/**
	 * Save the settings form
	 *
	 * @param \IPS\Helpers\Form 	$form		The Form Object
	 * @param array 				$values		Values
	 */
	protected function _saveSettingsForm( \IPS\Helpers\Form $form, array $values )
	{
		$form->saveAsSettings( array(
			'gallery_large_dims'			=> implode( 'x', $values['gallery_large_dims'] ),
			'gallery_small_dims'			=> implode( 'x', $values['gallery_small_dims'] ),
			'gallery_use_square_thumbnails'	=> $values['gallery_use_square_thumbnails'],
			'gallery_watermark_path'		=> (string)  $values['gallery_watermark_path'],
			'gallery_detailed_bandwidth'	=> $values['gallery_detailed_bandwidth'],
			'gallery_bandwidth_period'		=> $values['gallery_bandwidth_period'],
			'gallery_rss_enabled'			=> $values['gallery_rss_enabled'],
			'gallery_watermark_images'		=> implode( ',', $values['gallery_watermark_images'] ),
			'gallery_use_watermarks'		=> $values['gallery_use_watermarks'],
			'gallery_maps_default'			=> $values['gallery_maps_default'] ?? 0,
			'gallery_nsfw'					=> $values['gallery_nsfw'],
			'gallery_overview_show_carousel'			=> $values['gallery_overview_show_carousel'],
			'gallery_overview_carousel_type'			=> $values['gallery_overview_carousel_type'],
			'gallery_overview_carousel_count'			=> $values['gallery_overview_carousel_count'],
			'gallery_show_recent_comments'				=> $values['gallery_show_recent_comments'],
			'gallery_overview_show_categories'			=> $values['gallery_overview_show_categories'],
			'gallery_show_recent_updated_albums'		=> $values['gallery_show_recent_updated_albums'],
			'gallery_recent_updated_albums_count'		=> $values['gallery_recent_updated_albums_count'],
			'gallery_show_new_images'					=> $values['gallery_show_new_images'],
			'gallery_new_images_count'					=> $values['gallery_new_images_count'],
		) );

		\IPS\Session::i()->log( 'acplogs__gallery_settings' );

		if( $values['rebuildWatermarkScreenshots'] )
		{
			\IPS\Db::i()->delete( 'core_queue', array( '`app`=? OR `key`=?', 'gallery', 'RebuildGalleryImages' ) );
			\IPS\Task::queue( 'gallery', 'RebuildGalleryImages', array( ), 2 );
		}
	}
}
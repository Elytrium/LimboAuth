<?php
/**
 * @brief		Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		09 Oct 2013
 */

namespace IPS\downloads\modules\admin\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Settings
 */
class _settings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage' );

		$form = $this->_getForm();

		if ( $values = $form->values( TRUE ) )
		{
			$this->_saveSettingsForm( $form, $values, $redirectMessage );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__downloads_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=downloads&module=downloads&controller=settings' ), $redirectMessage );

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

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_settings.js', 'downloads', 'admin' ) );
		$form->attributes['data-controller'] = 'downloads.admin.settings.settings';
		$form->hiddenValues['rebuildWatermarkScreenshots'] = \IPS\Request::i()->rebuildWatermarkScreenshots ?: 0;

		$form->addTab( 'idm_landing_page' );
		$form->addHeader( 'featured_downloads' );
        $form->add( new \IPS\Helpers\Form\YesNo( 'idm_show_featured', \IPS\Settings::i()->idm_show_featured, FALSE, array( 'togglesOn' => array( 'idm_featured_count' ) ) ) );
        $form->add( new \IPS\Helpers\Form\Number( 'idm_featured_count', \IPS\Settings::i()->idm_featured_count, FALSE, array(), NULL, NULL, NULL, 'idm_featured_count' ) );

		$form->addHeader('browse_whats_new');
        $form->add( new \IPS\Helpers\Form\YesNo( 'idm_show_newest', \IPS\Settings::i()->idm_show_newest, FALSE, array('togglesOn' => array( 'idm_newest_categories') ) ) );
        $form->add( new \IPS\Helpers\Form\Node( 'idm_newest_categories', ( \IPS\Settings::i()->idm_newest_categories AND \IPS\Settings::i()->idm_newest_categories != 0 ) ? explode( ',', \IPS\Settings::i()->idm_newest_categories ) : 0, FALSE, array(
            'class' => 'IPS\downloads\Category',
            'zeroVal' => 'any',
            'multiple' => TRUE ), NULL, NULL, NULL, 'idm_newest_categories') );

		$form->addHeader('browse_highest_rated');
        $form->add( new \IPS\Helpers\Form\YesNo( 'idm_show_highest_rated', \IPS\Settings::i()->idm_show_highest_rated, FALSE, array( 'togglesOn' => array( 'idm_highest_rated_categories' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'idm_highest_rated_categories', ( \IPS\Settings::i()->idm_highest_rated_categories AND \IPS\Settings::i()->idm_highest_rated_categories != 0 ) ? explode( ',', \IPS\Settings::i()->idm_highest_rated_categories ) : 0, FALSE, array(
			'class' => 'IPS\downloads\Category',
			'zeroVal' => 'any',
			'multiple' => TRUE ), NULL, NULL, NULL, 'idm_highest_rated_categories') );

		$form->addHeader('browse_most_downloaded');
        $form->add( new \IPS\Helpers\Form\YesNo( 'idm_show_most_downloaded', \IPS\Settings::i()->idm_show_most_downloaded, FALSE, array( 'togglesOn' => array( 'idm_show_most_downloaded_categories' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Node( 'idm_show_most_downloaded_categories', ( \IPS\Settings::i()->idm_show_most_downloaded_categories AND \IPS\Settings::i()->idm_show_most_downloaded_categories != 0 ) ? explode( ',', \IPS\Settings::i()->idm_show_most_downloaded_categories ) : 0, FALSE, array(
			'class' => 'IPS\downloads\Category',
			'zeroVal' => 'any',
			'multiple' => TRUE ), NULL, NULL, NULL, 'idm_show_most_downloaded_categories') );


        $form->addTab( 'basic_settings' );
		$form->add( new \IPS\Helpers\Form\Upload( 'idm_watermarkpath', \IPS\Settings::i()->idm_watermarkpath ? \IPS\File::get( 'core_Theme', \IPS\Settings::i()->idm_watermarkpath ) : NULL, FALSE, array( 'image' => TRUE, 'storageExtension' => 'core_Theme' ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'idm_link_blacklist', explode( ',', \IPS\Settings::i()->idm_link_blacklist ), FALSE, array( 'placeholder' => 'example.com' ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'idm_antileech', \IPS\Settings::i()->idm_antileech ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'idm_rss', \IPS\Settings::i()->idm_rss ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'idm_default_view', \IPS\Settings::i()->idm_default_view, FALSE, array(
			'options' => array(
				'table' => 'downloads_default_view_table',
				'grid' => 'downloads_default_view_grid',
		),
		) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'idm_default_view_choose', \IPS\Settings::i()->idm_default_view_choose ) );

		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$form->addTab( 'paid_file_settings' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'idm_nexus_on', \IPS\Settings::i()->idm_nexus_on, FALSE, array( 'togglesOn' => array( 'idm_nexus_tax', 'idm_nexus_percent', 'idm_nexus_transfee', 'idm_nexus_mincost', 'idm_nexus_gateways', 'idm_nexus_display' ) ) ) );
			$form->add( new \IPS\Helpers\Form\Node( 'idm_nexus_tax', \IPS\Settings::i()->idm_nexus_tax ?:0, FALSE, array( 'class' => '\IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ), NULL, NULL, NULL, 'idm_nexus_tax' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'idm_nexus_percent', \IPS\Settings::i()->idm_nexus_percent, FALSE, array( 'min' => 0, 'max' => 100 ), NULL, NULL, '%', 'idm_nexus_percent' ) );
			$form->add( new \IPS\nexus\Form\Money( 'idm_nexus_transfee', json_decode( \IPS\Settings::i()->idm_nexus_transfee, TRUE ), FALSE, array(), NULL, NULL, NULL, 'idm_nexus_transfee' ) );
			$form->add( new \IPS\nexus\Form\Money( 'idm_nexus_mincost', json_decode( \IPS\Settings::i()->idm_nexus_mincost, TRUE ), FALSE, array(), NULL, NULL, NULL, 'idm_nexus_mincost' ) );
			$form->add( new \IPS\Helpers\Form\Node( 'idm_nexus_gateways', ( \IPS\Settings::i()->idm_nexus_gateways ) ? explode( ',', \IPS\Settings::i()->idm_nexus_gateways ) : 0, FALSE, array( 'class' => '\IPS\nexus\Gateway', 'zeroVal' => 'no_restriction', 'multiple' => TRUE ), NULL, NULL, NULL, 'idm_nexus_gateways' ) );
			$form->add( new \IPS\Helpers\Form\CheckboxSet( 'idm_nexus_display', explode( ',', \IPS\Settings::i()->idm_nexus_display ), FALSE, array( 'options' => array( 'purchases' => 'idm_purchases', 'downloads' => 'downloads' ) ), NULL, NULL, NULL, 'idm_nexus_display' ) );
		}

		return $form;
	}

	/**
	 * Save the settings form
	 *
	 * @param \IPS\Helpers\Form 	$form		The Form Object
	 * @param array 				$values		Values
	 * @param string $redirectMessage	Message to show on redirect
	 */
	protected function _saveSettingsForm( \IPS\Helpers\Form $form, array $values, ?string &$redirectMessage )
	{
		/* We can't store '' for idm_nexus_display as it will fall back to the default */
		if ( \IPS\Application::appIsEnabled( 'nexus' ) and !$values['idm_nexus_display'] )
		{
			$values['idm_nexus_display'] = 'none';
		}

		$rebuildScreenshots = $values['rebuildWatermarkScreenshots'];

		unset( $values['rebuildWatermarkScreenshots'] );

		$form->saveAsSettings( $values );

		/* Save the form first, then queue the rebuild */
		if( $rebuildScreenshots )
		{
			\IPS\Db::i()->delete( 'core_queue', array( '`app`=? OR `key`=?', 'downloads', 'RebuildScreenshotWatermarks' ) );

			\IPS\Task::queue( 'downloads', 'RebuildScreenshotWatermarks', array( ), 5 );
			$redirectMessage = 'download_settings_saved_rebuilding';
		}
		else
		{
			$redirectMessage ='saved';
		}
	}
}
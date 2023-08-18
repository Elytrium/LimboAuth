<?php
/**
 * @brief		Blog Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\modules\admin\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Blog settings
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'settings_manage', 'blog' );
		parent::execute();
	}

	/**
	 * Manage Blog Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_rating', \IPS\Settings::i()->blog_enable_rating ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_enable_sidebar', \IPS\Settings::i()->blog_enable_sidebar ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_allow_grid', \IPS\Settings::i()->blog_allow_grid, FALSE, array( 'togglesOn' => array( 'blog_view_mode' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'blog_view_mode', \IPS\Settings::i()->blog_view_mode, FALSE, array( 'options' => array(
			'grid'	=> 'blog_view_mode_grid',
			'list'  => 'blog_view_mode_list'	
		) ), NULL, NULL, NULL, 'blog_view_mode' ) );
		
		$form->addHeader('blog_settings_rss');
		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_allow_rssimport', \IPS\Settings::i()->blog_allow_rssimport ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'blog_allow_rss', \IPS\Settings::i()->blog_allow_rss ) );
		
		if ( $form->values() )
		{
			$form->saveAsSettings();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			\IPS\Session::i()->log( 'acplogs__blog_settings' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output = $form;
	}
}
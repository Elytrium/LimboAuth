<?php
/**
 * @brief		webhooks
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		04 Nov 2021
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * webhooks
 */
class _webhooks extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Api\Webhook';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;

	/**
	 * Description can contain HTML?
	 */
	public $_descriptionHtml = TRUE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'webhooks_manage' );

		parent::execute();
	}

	/**
	 * Form to add/edit a forum
	 *
	 * @return void
	 */
	protected function form()
	{
		parent::form();

		if ( \IPS\Request::i()->id )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('edit_webhook', FALSE, array( 'sprintf' => array( \IPS\Output::i()->title ) ) );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_webhook');
		}
	}

	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'webhook_logs_success', \IPS\Settings::i()->webhook_logs_success, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'always' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'webhook_logs_success' ) );

		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__webhook_settings' );
			\IPS\Output::i()->redirect( $this->url, 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'settings', $form, FALSE );
	}

	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$response = parent::_getRootButtons();

			$settingsButton = [
				'title'		=> 'settings',
				'icon'		=> 'cog',
				'link'		=> $this->url->setQueryString('do', 'settings'),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
			];
			return array_merge( $response, [ 'settings' =>$settingsButton] );
	}

}
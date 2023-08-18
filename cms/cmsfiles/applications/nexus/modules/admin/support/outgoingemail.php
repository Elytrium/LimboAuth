<?php
/**
 * @brief		Outgoing Email Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		18 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Outgoing Email Settings
 */
class _outgoingemail extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'autoresolve_manage' );
		
		$form = new \IPS\Helpers\Form;
		
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_sout_chrome', \IPS\Settings::i()->nexus_sout_chrome, FALSE, array(
			'options' 		=> array(
				0	=> \IPS\Theme::i()->resource( 'settings/email_no_chrome.png' ),
				1	=> \IPS\Theme::i()->resource( 'settings/email_chrome.png' )
			),
			'parse'			=> 'image',
			'descriptions'	=> array(
				0	=> \IPS\Member::loggedIn()->language()->addToStack('nexus_sout_chrome_no'),
				1	=> \IPS\Member::loggedIn()->language()->addToStack('nexus_sout_chrome_yes'),
			)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Radio( 'nexus_sout_from', \IPS\Settings::i()->nexus_sout_from, FALSE, array(
			'options'	=> array(
				'staff'	=> 'nexus_sout_from_staff',
				'dpt'	=> 'nexus_sout_from_department',
				'other'	=> 'nexus_sout_from_other',
			),
			'userSuppliedInput'	=> 'other',
			'toggles'	=> array(
				'other'	=> array( 'other_nexus_sout_from' )
			)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_sout_autoreply', \IPS\Settings::i()->nexus_sout_autoreply ) );
		
		if ( $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplogs__outgoingemail_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=settings&tab=outgoingemail' ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
}
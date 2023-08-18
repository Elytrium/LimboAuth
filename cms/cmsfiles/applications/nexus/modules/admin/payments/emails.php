<?php
/**
 * @brief		Notification Copy Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		19 Aug 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Copy Settings
 */
class _emails extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'email_copies_settings' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'nexus_notify_copy_types', explode( ',', \IPS\Settings::i()->nexus_notify_copy_types ), FALSE, array( 'options' => array(
			'invoice_warn'		=> 'nexus_notify_copy_invoice_warn',
			'new_invoice'		=> 'nexus_notify_copy_new_invoice',
			'payment_received'	=> 'nexus_notify_copy_payment_received',
			'payment_waiting'	=> 'nexus_notify_copy_payment_waiting',
			'payment_held'		=> 'nexus_notify_copy_payment_held',
			'payment_failed'	=> 'nexus_notify_copy_payment_failed',
			'payment_refunded'	=> 'nexus_notify_copy_payment_refunded',
			'commission_earned'	=> 'nexus_notify_copy_commission_earned',
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'nexus_notify_copy_email', explode( ',', \IPS\Settings::i()->nexus_notify_copy_email ) ) );

		if ( $form->values() )
		{
			$form->saveAsSettings();
			
			\IPS\Session::i()->log( 'acplogs__email_copies_setting' );	
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=paymentsettings&tab=emails' ), 'saved' );
		}
		
		\IPS\Output::i()->output = $form;
	}
}
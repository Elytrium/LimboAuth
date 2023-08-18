<?php
/**
 * @brief		Autoresolve Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		25 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Autoresolve Settings
 */
class _autoresolve extends \IPS\Dispatcher\Controller
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
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_autoresolve_on', \IPS\Settings::i()->nexus_autoresolve_days, FALSE, array( 'togglesOn' => array( 'reply_request_resolved', 'nexus_autoresolve_departments', 'nexus_autoresolve_applicable', 'nexus_autoresolve_days', 'nexus_autoresolve_notify', 'nexus_autoresolve_status' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'reply_request_resolved', \IPS\Settings::i()->reply_request_resolved, FALSE, array(
			'options' => array(
				'reopen' => 'reopen_request_autoresolve',
				'create' => 'create_new_request_autoresolve',
				'reject' => 'reject_notify_user_autoresolve'
				), 
			'descriptions' => array(
				'create'	=> \IPS\Member::loggedIn()->language()->addToStack( 'create_new_request_autoresolve_desc' ),
				'reject'	=> \IPS\Member::loggedIn()->language()->addToStack( 'reject_notify_user_autoresolve_desc' )
			)
		) ) );
		$form->addSeparator();
		$form->add( new \IPS\Helpers\Form\Node( 'nexus_autoresolve_departments', \IPS\Settings::i()->nexus_autoresolve_departments === '*' ? 0 : explode( ',', \IPS\Settings::i()->nexus_autoresolve_departments ), FALSE, array( 'class' => 'IPS\nexus\Support\Department', 'multiple' => TRUE, 'zeroVal' => 'any' ), NULL, NULL, NULL, 'nexus_autoresolve_departments' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'nexus_autoresolve_applicable', \IPS\Settings::i()->nexus_autoresolve_applicable, FALSE, array( 'class' => 'IPS\nexus\Support\Status', 'multiple' => TRUE ), NULL, NULL, NULL, 'nexus_autoresolve_applicable' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'nexus_autoresolve_days', \IPS\Settings::i()->nexus_autoresolve_days, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS ), NULL, NULL, NULL, 'nexus_autoresolve_days' ) );
		$form->add( new \IPS\Helpers\Form\Node( 'nexus_autoresolve_status', \IPS\Settings::i()->nexus_autoresolve_status, FALSE, array( 'class' => 'IPS\nexus\Support\Status' ), NULL, NULL, NULL, 'nexus_autoresolve_status' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'nexus_autoresolve_notify', \IPS\Settings::i()->nexus_autoresolve_notify, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('hours_before_resolving'), 'nexus_autoresolve_notify' ) );
		
		if ( $values = $form->values() )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => (int) $values['nexus_autoresolve_on'] ), "`key`='supportAutoresolve'" );
			
			if ( !$values['nexus_autoresolve_on'] )
			{
				$values['nexus_autoresolve_days'] = 0;
			}
			unset( $values['nexus_autoresolve_on'] );

			$values['nexus_autoresolve_departments'] = $values['nexus_autoresolve_departments'] ? implode( ',' , array_keys( $values['nexus_autoresolve_departments'] ) ) : '*';
			$values['nexus_autoresolve_applicable'] = \is_array( $values['nexus_autoresolve_applicable'] ) ? implode( ',', array_keys( $values['nexus_autoresolve_applicable'] ) ) : $values['nexus_autoresolve_applicable'];
			$values['nexus_autoresolve_status'] = $values['nexus_autoresolve_status']->id;
			
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__autoresolve_settings' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=settings&tab=autoresolve') );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('autoresolve');
		\IPS\Output::i()->output = $form;
	}
}
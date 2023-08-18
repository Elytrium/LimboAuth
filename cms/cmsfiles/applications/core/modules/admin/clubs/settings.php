<?php
/**
 * @brief		Clubs Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Feb 2017
 */

namespace IPS\core\modules\admin\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs Settings
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_settings_manage' );
		parent::execute();
	}

	/**
	 * Manage Club Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$fields =  array( 'clubs_default_sort', 'clubs_header', 'clubs_locations', 'clubs_modperms', 'clubs_require_approval', 'form_header_club_display_settings', 'form_header_club_moderation', 'clubs_default_view', 'clubs_allow_view_change', 'club_nodes_in_apps', 'form_header_clubs_paid_settings', 'clubs_paid_on', '_allow_club_moderators', 'club_max_cover' );

		$form = new \IPS\Helpers\Form;
		$form->addHeader( 'club_settings' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_enabled_setting', \IPS\Settings::i()->clubs, FALSE, array( 'togglesOn' => $fields ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_require_approval', \IPS\Settings::i()->clubs_require_approval, FALSE, array(), NULL, NULL, NULL, 'clubs_require_approval' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_locations', \IPS\Settings::i()->clubs_locations, FALSE, array(), NULL, NULL, NULL, 'clubs_locations' ) );
		if ( \IPS\Application::appIsEnabled( 'nexus' ) )
		{
			$form->addHeader( 'clubs_paid_settings' );
			$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_paid_on', \IPS\Settings::i()->clubs_paid_on, FALSE, array( 'togglesOn' => array( 'clubs_paid_tax', 'clubs_paid_commission', 'clubs_paid_transfee', 'clubs_paid_gateways' ) ), NULL, NULL, NULL, 'clubs_paid_on' ) );
			$form->add( new \IPS\Helpers\Form\Node( 'clubs_paid_tax', \IPS\Settings::i()->clubs_paid_tax ?:0, FALSE, array( 'class' => '\IPS\nexus\Tax', 'zeroVal' => 'do_not_tax' ), NULL, NULL, NULL, 'clubs_paid_tax' ) );
			$form->add( new \IPS\Helpers\Form\Number( 'clubs_paid_commission', \IPS\Settings::i()->clubs_paid_commission, FALSE, array( 'min' => 0, 'max' => 100 ), NULL, NULL, '%', 'clubs_paid_commission' ) );
			$form->add( new \IPS\nexus\Form\Money( 'clubs_paid_transfee', \IPS\Settings::i()->clubs_paid_transfee, FALSE, array(), NULL, NULL, NULL, 'clubs_paid_transfee' ) );
			$form->add( new \IPS\Helpers\Form\Node( 'clubs_paid_gateways', \IPS\Settings::i()->clubs_paid_gateways, FALSE, array( 'class' => '\IPS\nexus\Gateway', 'zeroVal' => 'no_restriction', 'multiple' => TRUE ), NULL, NULL, NULL, 'clubs_paid_gateways' ) );
		}
		$form->addHeader( 'club_display_settings' );
		$form->add( new \IPS\Helpers\Form\Radio( 'clubs_default_view', \IPS\Settings::i()->clubs_default_view, FALSE, array( 'options' => array(
			'grid'		=> 'club_view_grid',
			'list'		=> 'club_view_list',
		) ), NULL, NULL, NULL, 'clubs_default_view' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'clubs_allow_view_change', \IPS\Settings::i()->clubs_allow_view_change, FALSE, array(), NULL, NULL, NULL, 'clubs_allow_view_change' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'clubs_default_sort', \IPS\Settings::i()->clubs_default_sort, FALSE, array( 'options' => array(
			'last_activity'		=> 'clubs_sort_last_activity',
			'members'			=> 'clubs_sort_members',
			'content'			=> 'clubs_sort_content',
			'created'			=> 'clubs_sort_created',
			'name'				=> 'clubs_sort_name'
		) ), NULL, NULL, NULL, 'clubs_default_sort' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'clubs_header', \IPS\Settings::i()->clubs_header, FALSE, array( 'options' => array(
			'full'		=> 'clubs_header_full',
			'sidebar'	=> 'clubs_header_sidebar',
		) ), NULL, NULL, NULL, 'clubs_header' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'club_nodes_in_apps', \IPS\Settings::i()->club_nodes_in_apps, FALSE, array( 'options' => array(
			'0'	=> 'club_nodes_in_apps_off',
			'1'	=> 'club_nodes_in_apps_on',
		) ), NULL, NULL, NULL, 'club_nodes_in_apps' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'club_max_cover', \IPS\Settings::i()->club_max_cover ?: -1, FALSE, array( 'unlimited' => -1 ), function( $value ) {
			if( !$value )
			{
				throw new \InvalidArgumentException('form_required');
			}
		}, NULL, \IPS\Member::loggedIn()->language()->addToStack('filesize_raw_k'), 'club_max_cover' ) );
		$form->addHeader( 'club_moderation' );
		$form->add( new \IPS\Helpers\Form\YesNo( '_allow_club_moderators', ( \IPS\Settings::i()->clubs_modperms != -1 ), FALSE, array( 'togglesOn' => array( 'clubs_modperms' ) ), NULL, NULL, NULL, '_allow_club_moderators' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'clubs_modperms', ( \IPS\Settings::i()->clubs_modperms != -1 ) ? explode( ',', \IPS\Settings::i()->clubs_modperms ) : array(), FALSE, array( 'options' => array(
			'pin'				=> 'club_modperm_pin',
			'unpin'				=> 'club_modperm_unpin',
			'edit'				=> 'club_modperm_edit',
			'hide'				=> 'club_modperm_hide',
			'unhide'			=> 'club_modperm_unhide',
			'view_hidden'		=> 'club_modperm_view_hidden',
			'future_publish'	=> 'club_modperm_future_publish',
			'view_future'		=> 'club_modperm_view_future',
			'move'				=> 'club_modperm_move',
			'lock'				=> 'club_modperm_lock',
			'unlock'			=> 'club_modperm_unlock',
			'reply_to_locked'	=> 'club_modperm_reply_to_locked',
			'delete'			=> 'club_modperm_delete',
			'split_merge'		=> 'club_modperm_split_merge',
		) ), NULL, NULL, NULL, 'clubs_modperms' ) );

		if ( $values = $form->values() )
		{
			$values['clubs'] = $values['clubs_enabled_setting'];

			/* If this setting is set to "No" then we're going to wipe out moderator permissions */
			if( !$values['_allow_club_moderators'] )
			{
				$values['clubs_modperms'] = array();
			}

			$values['clubs_modperms'] = ( \count( $values['clubs_modperms'] ) ) ? implode( ',', $values['clubs_modperms'] ) : -1;

			/* Get rid of fake settings */
			unset( $values['clubs_enabled_setting'], $values['_allow_club_moderators'] );

			if ( \IPS\Application::appIsEnabled( 'nexus' ) )
			{
				$values['clubs_paid_tax'] = $values['clubs_paid_tax'] ? $values['clubs_paid_tax']->id : 0;	
				$values['clubs_paid_gateways'] = \is_array( $values['clubs_paid_gateways'] ) ? implode( ',', array_keys( $values['clubs_paid_gateways'] ) ) : $values['clubs_paid_gateways'];		
			}
			$form->saveAsSettings( $values );
			
			\IPS\Session::i()->log( 'acplog__club_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=clubs&controller=settings'), 'saved' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_clubs_settings');
		\IPS\Output::i()->output = $form;
	}
}
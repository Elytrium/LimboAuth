<?php
/**
 * @brief		ACP Member Profile: Basic Information Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Basic Information Block
 */
class _BasicInformation extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$hasPassword = FALSE;
		$canChangePassword = \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\Standard' );
		$activeIntegrations = array();
		if ( \IPS\Member::loggedIn()->hasAcpRestriction('core', 'members', 'member_edit') )
		{
			/* Is this an admin? */
			if ( $this->member->isAdmin() AND !\IPS\Member::loggedIn()->hasAcpRestriction('core', 'members', 'member_edit_admin' ) )
			{
				$canChangePassword = FALSE;
			}
			
			if ( $canChangePassword !== FALSE )
			{
				foreach ( \IPS\Login::methods() as $method )
				{
					if ( $method->canProcess( $this->member ) )
					{
						if ( !( $method instanceof \IPS\Login\Handler\Standard ) )
						{
							$activeIntegrations[] = $method->_title;
						}
						if ( $method->canChangePassword( $this->member ) )
						{
							$hasPassword = TRUE;
							$canChangePassword = TRUE;
						}
					}
				}
			}
		}
		else
		{
			$canChangePassword = FALSE;
		}
		
		$accountActions = array();
		if ( \IPS\Member::loggedIn()->member_id != $this->member->member_id AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_login' ) AND !$this->member->isBanned() )
		{
			$accountActions[] = array(
				'title'		=> \IPS\Member::loggedIn()->language()->addToStack( 'login_as_x', FALSE, array( 'sprintf' => array( $this->member->name ) ) ),
				'icon'		=> 'key',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=login&id={$this->member->member_id}" )->csrf(),
				'class'		=> '',
				'target'    => '_blank'
			);
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'members_merge' ) )
		{
			$accountActions[] = array(
				'title'		=> 'merge_with_another_account',
				'icon'		=> 'level-up',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=merge&id={$this->member->member_id}" ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('merge') )
			);
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete_admin' ) or !$this->member->isAdmin() ) and $this->member->member_id != \IPS\Member::loggedIn()->member_id )
		{
			$accountActions[] = array(
				'title'		=> 'delete',
				'icon'		=> 'times-circle',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=delete&id={$this->member->member_id}" ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ),
			);
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_export_pi' ) )
		{
			$accountActions[] = array(
				'title'		=> 'member_export_pi_title',
				'icon'		=> 'download',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=exportPersonalInfo&id={$this->member->member_id}" ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('member_export_pi_title') )
			);
		}

		/* Data Layer PII Option */
		if (
			\IPS\Settings::i()->core_datalayer_enabled AND
			\IPS\Settings::i()->core_datalayer_include_pii AND
			\IPS\Settings::i()->core_datalayer_member_pii_choice AND
			( ( $this->member->isAdmin() AND \IPS\Member::loggedIn()->hasAcpRestriction('core', 'members', 'member_edit_admin' ) ) OR \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) )
		)
		{
			$enabled = !$this->member->members_bitoptions['datalayer_pii_optout'];
			$accountActions[] = array(
				'title'		=> $enabled ? 'datalayer_omit_member_pii' : 'datalayer_collect_member_pii',
				'icon'		=> 'id-card',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=toggleDataLayerPii&id={$this->member->member_id}" ),
			);
		}
		
		$activeSubscription = FALSE;
		if ( \IPS\Application::appIsEnabled('nexus') and \IPS\Settings::i()->nexus_subs_enabled ) // I know... this should really be a hook... I won't tell if you won't
		{
			$activeSubscription = \IPS\nexus\Subscription::loadActiveByMember( $this->member );
		}
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->basicInformation( $this->member, $canChangePassword, $hasPassword, $activeIntegrations, $accountActions, $activeSubscription );
	}
}
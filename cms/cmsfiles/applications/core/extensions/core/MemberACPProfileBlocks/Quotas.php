<?php
/**
 * @brief		ACP Member Profile: Quotas Block
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
 * @brief	ACP Member Profile: Quotas Block
 */
class _Quotas extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$messengerCount = NULL;
		$messengerPercent = NULL;
		if ( $this->member->canAccessModule( \IPS\Application\Module::get( 'core', 'messaging', 'front' ) ) and !$this->member->members_disable_pm )
		{
			$messengerCount = \IPS\Db::i()->select( 'count(*)', 'core_message_topic_user_map', array( 'map_user_id=? AND map_user_active=1', $this->member->member_id ) )->first();
			
			if ( $this->member->group['g_max_messages'] > 0 )
			{
				$messengerPercent = floor( 100 / $this->member->group['g_max_messages'] * $messengerCount );;
			}
		}
		
		$attachmentStorage = NULL;
		$attachmentPercent = NULL;
		if ( $this->member->group['g_attach_max'] != 0 )
		{
			$attachmentStorage = \IPS\Db::i()->select( 'SUM(attach_filesize)', 'core_attachments', array( 'attach_member_id=?', $this->member->member_id ) )->first();
			if ( !$attachmentStorage )
			{
				$attachmentStorage = 0;
			}
			
			if ( $this->member->group['g_attach_max'] > 0 )
			{
				$attachmentPercent = floor( 100 / ( $this->member->group['g_attach_max'] * 1024 ) * $attachmentStorage );
			}
		}
		
		$viewAttachmentsLink = NULL;
		if ( $attachmentStorage and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'overview', 'files_view' ) )
		{
			$viewAttachmentsLink = \IPS\Http\Url::internal('app=core&module=overview&controller=files&advanced_search_submitted=1')->setQueryString( 'attach_member_id', $this->member->name )->csrf();
		}
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->quotas( $this->member, $messengerCount, $messengerPercent, $attachmentStorage, $attachmentPercent, $viewAttachmentsLink );
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		\IPS\Session::i()->csrfCheck();
		
		$old = $this->member->members_disable_pm;
		if ( \IPS\Request::i()->enable )
		{
			$this->member->members_disable_pm = 0;
		}
		else
		{
			if ( \IPS\Request::i()->prompt ) // Member cannot re-enable
			{
				$this->member->members_disable_pm = 2;
			}
			else // Member can re-enable
			{
				$this->member->members_disable_pm = 1;
			}
		}
		$this->member->save();
		if ( $old != $this->member->members_disable_pm )
		{
			$this->member->logHistory( 'core', 'warning', array( 'restrictions' => array( 'members_disable_pm' => array( 'old' => $old, 'new' => $this->member->members_disable_pm ) ) ) );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
	}
}
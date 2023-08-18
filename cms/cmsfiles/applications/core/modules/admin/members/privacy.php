<?php
/**
 * @brief		privacy
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Mar 2023
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\Member;
use IPS\Output;

if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * privacy
 */
class _privacy extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'privacy_manage' );
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'		=> 'cog',
			'title'		=> 'settings',
			'link'		=> $this->url->setQueryString( 'do', 'settings' ),
			'data'	=>  array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
		);
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Some advanced search links may bring us here */
		\IPS\Output::i()->bypassCsrfKeyCheck = true;

		/* Create the table */
		$where = [ [ \IPS\Db::i()->in('action', [\IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE, \IPS\Member\PrivacyAction::TYPE_REQUEST_PII] ) ], ['approved=?', 0] ];
		$table = new \IPS\Helpers\Table\Db( 'core_member_privacy_actions', \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' ), $where );
		$table->include = [ 'photo', 'name', 'email', 'joined','action','request_date' ];

		$table->filters = [
			'deletion_request'			=> ['action=?', \IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE],
			'pii_data'					=> ['action=?', \IPS\Member\PrivacyAction::TYPE_REQUEST_PII],
		];

		$table->joins = [
			[ 'select' => 'm.*', 'from' => [ 'core_members', 'm' ], 'where' => 'core_member_privacy_actions.member_id=m.member_id' ]
		];

		$table->parsers = [
			'photo'				=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::constructFromData( $row ), 'tiny' );
			},
			'name' => function( $val, $row ){
				if( $val )
				{
					$member = \IPS\Member::constructFromData( $row );

					if( $banned = $member->isBanned() )
					{
						if( $banned instanceof \IPS\DateTime )
						{
							$title = \IPS\Member::loggedIn()->language()->addToStack( 'suspended_until', FALSE, array( 'sprintf' => array( $banned->localeDate() ) ) );
						}else
						{
							$title = \IPS\Member::loggedIn()->language()->addToStack( 'banned' );
						}
						return "<a href='".\IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=view&id=' ).$row[ 'member_id' ]."'>".htmlentities( $val, ENT_DISALLOWED, 'UTF-8', FALSE )."</a> &nbsp; <span class='ipsBadge ipsBadge_negative'>".$title.'</span> ';
					}else
					{
						return "<a href='".\IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=view&id=' ).$row[ 'member_id' ]."'>".htmlentities( $val, ENT_DISALLOWED, 'UTF-8', FALSE ).'</a>';
					}
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberReserved( \IPS\Member::constructFromData( $row ) );
				}
			},
			'joined'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'request_date'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'action' 			=> function( $val, $row )
			{
				switch ( $val )
				{
					case \IPS\Member\PrivacyAction::TYPE_REQUEST_PII:
						return \IPS\Member::loggedIn()->language()->addToStack('pii_download_requested');
					case \IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE:
						return \IPS\Member::loggedIn()->language()->addToStack('account_deletion_requested');
				}
			}
		];

		$table->rowButtons = function( $row )
		{
			$return = [];

			if( $row['action'] == \IPS\Member\PrivacyAction::TYPE_REQUEST_PII )
			{
				$return[ 'approve' ] = [
					'title' => 'approve',
					'icon' => 'check-circle',
					'link' => \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy&do=approvePii&id='.$row[ 'id' ] )->csrf()->getSafeUrlFromFilters(),
				];
				$return['reject'] = [
					'icon'		=> 'times',
					'title'		=> 'reject',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy&do=rejectPii&id=' . $row['id'] )->csrf()->getSafeUrlFromFilters(),
				];
			}
			else if( $row['action'] == \IPS\Member\PrivacyAction::TYPE_REQUEST_DELETE )
			{
				$member = \IPS\Member::constructFromData( $row );
				
				$return[ 'approveDeletion' ] = [
					'title' => 'approve',
					'icon' => 'check-circle',
					'link' => \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy&do=approveDeletion&id='.$row[ 'id' ] )->csrf()->getSafeUrlFromFilters(),
					'data' => [ 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack( 'delete_member_confirm', FALSE, array( 'sprintf' => $member->name ) ) ]
				];
				$return['rejectDeletion'] = [
					'icon'		=> 'times',
					'title'		=> 'reject',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy&do=rejectDeletion&id=' . $row['id'] )->csrf()->getSafeUrlFromFilters(),
					'data'	=> array(

					)
				];
			}

			
			return $return;
		};
		
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menu__core_members_privacy');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', (string) $table );
	}

	/**
	 * Approve PII Request
	 *
	 * @return void
	 */
	protected function approvePii()
	{
		\IPS\Session::i()->csrfCheck();
		try
		{
			$request = \IPS\Member\PrivacyAction::load( \IPS\Request::i()->id );
			$request->approvePiiRequest();
			\IPS\Session::i()->log( 'acplog__piirequest_approved', array( $request->member->name => FALSE ) );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C432/1', 404, '' );
		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' )->getSafeUrlFromFilters(), 'approved' );
	}

	/**
	 * Reject PII Request
	 *
	 * @return void
	 */
	protected function rejectPii()
	{
		\IPS\Session::i()->csrfCheck();
		try
		{
			$request = \IPS\Member\PrivacyAction::load( \IPS\Request::i()->id );
			$request->rejectPiiRequest();
			\IPS\Session::i()->log( 'acplog__piirequest_rejected', array( $request->member->name => FALSE ) );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C432/2', 404, '' );
		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' )->getSafeUrlFromFilters(), 'pii_request_rejected' );
	}

	/**
	 * Approve deletion request
	 *
	 * @return void
	 */
	protected function approveDeletion()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			$request = \IPS\Member\PrivacyAction::load( \IPS\Request::i()->id );

			\IPS\Request::i()->confirmedDelete( message: \IPS\Member::loggedIn()->language()->addToStack( 'delete_member_confirm', FALSE, array( 'sprintf' => $request->member->name ) ) );

			$request->deleteAccount();
			\IPS\Session::i()->log( 'acplog__deletionrequest__approved', array( $request->member->name => FALSE ) );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C432/3', 404, '' );
		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' )->getSafeUrlFromFilters(), 'approved' );
	}

	/**
	 * Reject deletion request
	 *
	 * @return void
	 */
	protected function rejectDeletion()
	{
		\IPS\Session::i()->csrfCheck();
		try
		{
			$request = \IPS\Member\PrivacyAction::load( \IPS\Request::i()->id );
			$request->rejectDeletionRequest();
			\IPS\Session::i()->log( 'acplog__deletionrequest__rejected', array( $request->member->name => FALSE ) );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C432/4', 404, '' );
		}
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' )->getSafeUrlFromFilters(), 'rejected' );
	}

	protected function settings()
	{
		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\Radio( 'pii_type', \IPS\Settings::i()->pii_type, FALSE, array(
			'options' => array(
				'off' => 'disabled',
				'on' => 'enabled',
				'redirect' => "pii_external" ),
			'toggles' => array(
				'off'	=> array( '' ),
				'redirect'	=> array( 'pii_link' ),
				'on'		=> array(),
			)
		) ) );

		$form->add( new \IPS\Helpers\Form\Url( 'pii_link', \IPS\Settings::i()->pii_link, FALSE, array(), NULL, NULL, NULL, 'pii_link'  ) );


		$form->add( new \IPS\Helpers\Form\Radio( 'right_to_be_forgotten_type', \IPS\Settings::i()->right_to_be_forgotten_type, FALSE, array(
			'options' => array(
				'off' => 'disabled',
				'on' => 'enabled',
				'redirect' => "right_to_be_forgotten_external" ),
			'toggles' => array(
				'off'	=> array( '' ),
				'redirect'	=> array( 'right_to_be_forgotten_link' ),
				'on'		=> array(),
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'right_to_be_forgotten_link', \IPS\Settings::i()->right_to_be_forgotten_link, FALSE, array(), NULL, NULL, NULL, 'right_to_be_forgotten_link'  ) );


		if( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=privacy' ), 'saved' );
		}
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'settings');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global', 'core' )->block( 'title', (string) $form );
	}
}
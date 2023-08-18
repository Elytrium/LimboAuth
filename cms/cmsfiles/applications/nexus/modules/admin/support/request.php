<?php
/**
 * @brief		Support Request View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		09 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;
use \IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Request View
 */
class _request extends \IPS\nexus\modules\front\support\view
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_manage' );
		parent::execute();
	}
	
	/**
	 * View Item
	 *
	 * @return	void
	 */
	protected function manage()
	{	
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests'), \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_support_requests') );
		parent::manage();
				
		/* AJAX responders */
		if ( \IPS\Request::i()->isAjax() )
		{
			/* Popup which has the merge button */
			if ( isset( \IPS\Request::i()->popup ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->requestPopup( $this->request );
			}
			/* Stock Action data */
			elseif( isset( \IPS\Request::i()->stockActionData ) )
			{
				if ( \IPS\Request::i()->stockActionData )
				{
					try
					{
						$action = \IPS\nexus\Support\StockAction::load( \IPS\Request::i()->stockActionData );
						$data = array();
						
						if ( $action->department )
						{
							$data['department'] = $action->department->id;
						}
						else
						{
							$data['department'] = $this->request->department->id;
						}
						
						if ( $action->status )
						{
							$data['status'] = $action->status->id;
						}
						else
						{
							$data['status'] = \IPS\nexus\Support\Status::load( TRUE, 'status_default_staff' )->id;
						}
						
						if ( $action->staff )
						{
							$data['assign_to'] = $action->staff->member_id;
						}
						else
						{
							$data['assign_to'] = $this->request->staff_lock ? ( $this->request->staff ? $this->request->staff->member_id : 0 ) : 0;
						}
						
						if ( $action->message )
						{
							/* Do we have default content? */
							try
							{
								$defaultContent = \IPS\Db::i()->select( 'content', 'nexus_support_staff_preferences', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) )->first();

								/* If we aren't using the {default_content} tag then we just want the stock reply returned */
								if( mb_strpos( $defaultContent, '{default_content}' ) === FALSE )
								{
									throw new \UnderflowException;
								}

								$data['message'] = str_replace(
									array( '{customer_first_name}', '{customer_last_name}', '{customer_full_name}', '{department_name}', '{department_email}', '{default_content}' ),
									array(
										( $this->request->supportAuthor() instanceof \IPS\nexus\Support\Author\Member ) ? \IPS\nexus\Customer::load( $this->request->author()->member_id )->cm_first_name : '',
										( $this->request->supportAuthor() instanceof \IPS\nexus\Support\Author\Member ) ? \IPS\nexus\Customer::load( $this->request->author()->member_id )->cm_last_name : '',
										$this->request->supportAuthor()->name(),
										$action->department ? $action->department->_title : $this->request->department->_title,
										$action->department ? $action->department->email : $this->request->department->email,
										/* We want to strip the wrapping <p></p> because where we insert it into our default content will already be wrapped, so we will have double line breaks otherwise */
										preg_replace( "/^<p>(.+?)<\/p>$/is", "$1", trim( $action->message ) )
									),
									$defaultContent
								);
							}
							catch ( \UnderflowException $e )
							{
								$data['message'] = $action->message;
							}
						}
						
						\IPS\Output::i()->json( $data );
					}
					catch ( \Exception $e )
					{
						\IPS\Output::i()->json( $e->getMessage(), 500 );
					}
				}
				else
				{
					\IPS\Output::i()->json( array(
						'department'	=> $this->request->department->id,
						'status'		=> \IPS\nexus\Support\Status::load( TRUE, 'status_default_staff' )->id,
						'assign_to'		=> $this->request->staff_lock ? ( $this->request->staff ? $this->request->staff->member_id : 0 ) : 0
					)	);
				}
			}
			/* Purchase tree */
			else
			{
				\IPS\Output::i()->output = \IPS\nexus\Purchase::tree( $this->request->acpUrl(), array(), 's.' . $this->request->id, $this->request->purchase );
			}
			return;
		}
		
		/* Setting Order? */
		if ( isset( \IPS\Request::i()->order ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'support_replies_order', \IPS\Request::i()->order, \IPS\DateTime::create()->add( new \DateInterval('P1Y') ) );
			\IPS\Output::i()->redirect( $this->request->acpUrl() );
		}
		
		/* Views */
		$this->request->setStaffView( \IPS\Member::loggedIn() );

		/* Are we tracking? */
		try
		{
			$trackLang = \IPS\Db::i()->select( 'notify', 'nexus_support_tracker', array( 'member_id=? AND request_id=?', \IPS\Member::loggedIn()->member_id, $this->request->id ) )->first() ? 'tracking_notify' : 'tracking_no_notify';
		}
		catch ( \UnderflowException $e )
		{
			$trackLang = 'not_tracking';
		}

		/* Init buttons */
		$requestActions = array(
			'status'	=> array(
				'icon'			=> 'tag',
				'title'			=> $this->request->status->_title,
				'menu'			=> array(),
				'menuClass' 	=> 'ipsMenu_selectable ipsMenu_narrow',
				'data'			=> array( 'role' => 'statusMenu', 'controller' => 'nexus.admin.support.metamenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_status')
			),
			'severity'	=> array(
				'icon'			=> 'exclamation',
				'title'			=> $this->request->severity->_title,
				'menu'			=> array(),
				'menuClass' 	=> 'ipsMenu_selectable',
				'data'			=> array( 'role' => 'severityMenu', 'controller' => 'nexus.admin.support.metamenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_severity')
			),
			'department'	=> array(
				'icon'			=> 'folder',
				'title'			=> $this->request->department->_title,
				'menu'			=> array(),
				'menuClass' 	=> 'ipsMenu_selectable ipsMenu_narrow',
				'data'			=> array( 'role' => 'departmentMenu', 'controller' => 'nexus.admin.support.metamenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_department')
			),
			'track'	=> array(
				'icon'			=> 'bookmark',
				'title'			=> $trackLang,
				'menu'			=> array(
					array(
						'class'	=> $trackLang === 'not_tracking' ? 'ipsMenu_itemChecked' : '',
						'title'	=> 'not_tracking',
						'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'track', 'track' => 0 ) )->csrf()
					),
					array(
						'class'	=> $trackLang === 'tracking_no_notify' ? 'ipsMenu_itemChecked' : '',
						'title'	=> 'tracking_no_notify',
						'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'track', 'track' => 1, 'notify' => 0 ) )->csrf()
					),
					array(
						'class'	=> $trackLang === 'tracking_notify' ? 'ipsMenu_itemChecked' : '',
						'title'	=> 'tracking_notify',
						'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'track', 'track' => 1, 'notify' => 1 ) )->csrf()
					)
				),
				'menuClass' => 'ipsMenu_selectable',
				'data'			=> array( 'role' => 'trackMenu', 'controller' => 'nexus.admin.support.metamenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_tracking')
			),
			'staff'	=> array(
				'icon'			=> 'user',
				'title'			=> $this->request->staff ? $this->request->staff->name : 'unassigned',
				'menu'			=> array(),
				'menuClass' 	=> 'ipsMenu_selectable',
				'data'			=> array( 'controller' => 'nexus.admin.support.metamenu', 'role' => 'staffMenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_staff')
			),
		);
		
		/* Populate statuses */
		foreach ( Support\Status::roots() as $status )
		{
			$requestActions['status']['menu'][] = array(
				'class'	=> $status->id === $this->request->status->id ? 'ipsMenu_itemChecked' : '',
				'title'	=> $status->_title,
				'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'status', 'status' => $status->id ) )->csrf()
			);
		}
		
		/* Populate severities */
		if ( \count( Support\Severity::roots() ) < 2 )
		{
			unset( \IPS\Output::i()->sidebar['actions']['severity'] );
		}
		else
		{
			foreach ( Support\Severity::roots() as $severity )
			{
				$requestActions['severity']['menu'][] = array(
					'class'	=> $severity->id === $this->request->severity->id ? 'ipsMenu_itemChecked' : '',
					'title'	=> $severity->_title,
					'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'severity', 'severity' => $severity->id ) )->csrf(),
					'data'	=> array( 'group' => 'severities' ),
				);
			}
			
			if ( $this->request->member and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_block_sev' ) )
			{
				$requestActions['severity']['menu'][] = array( 'hr' => TRUE );
				$requestActions['severity']['menu'][] = array(
					'class'	=> !$this->request->author()->cm_no_sev ? 'ipsMenu_itemChecked' : '',
					'title'	=> \IPS\Member::loggedIn()->language()->addToStack( 'cm_no_sev_off', FALSE, array( 'sprintf' => array( $this->request->supportAuthor()->name() ) ) ),
					'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'noSev', 'no_sev' => 0 ) )->csrf(),
					'data'	=> array( 'group' => 'no_sev', 'noSet' => 'true' ),
				);
				$requestActions['severity']['menu'][] = array(
					'class'	=> $this->request->author()->cm_no_sev ? 'ipsMenu_itemChecked' : '',
					'title'	=> \IPS\Member::loggedIn()->language()->addToStack( 'cm_no_sev_on', FALSE, array( 'sprintf' => array( $this->request->supportAuthor()->name() ) ) ),
					'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'noSev', 'no_sev' => 1 ) )->csrf(),
					'data'	=> array( 'group' => 'no_sev', 'noSet' => 'true' ),
				);
			}
		}
		
		/* Populate departments */
		foreach ( Support\Department::roots() as $department )
		{
			$requestActions['department']['menu'][] = array(
				'class'	=> $department->id === $this->request->department->id ? 'ipsMenu_itemChecked' : '',
				'title'	=> $department->_title,
				'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'department', 'department' => $department->id ) )->csrf()
			);
		}
		
		/* Populate staff */
		foreach ( Support\Request::staff() as $id => $name )
		{
			$requestActions['staff']['menu'][] = array(
				'class'	=> ( $this->request->staff and $id === $this->request->staff->member_id ) ? 'ipsMenu_itemChecked' : '',
				'title'	=> $name,
				'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'staff', 'staff' => $id ) )->csrf(),
				'data'	=> array( 'group' => 'staff', 'id' => $id ),
			);
		}
		$requestActions['staff']['menu'][] = array(
			'class'	=> !$this->request->staff ? 'ipsMenu_itemChecked' : '',
			'title'	=> 'unassigned',
			'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'staff', 'staff' => 0 ) )->csrf(),
			'data'	=> array( 'group' => 'staff', 'id' => 0 ),
		);
		$requestActions['staff']['menu'][] = array( 'hr' => TRUE );
		$requestActions['staff']['menu'][] = array(
			'class'	=> $this->request->staff_lock ? 'ipsMenu_itemChecked' : '',
			'title'	=> \IPS\Member::loggedIn()->language()->addToStack( 'request_staff_lock_on' ),
			'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'staffLock', 'lock' => 1 ) )->csrf(),
			'data'	=> array( 'group' => 'staff_lock', 'noSet' => 'true' ),
		);
		$requestActions['staff']['menu'][] = array(
			'class'	=> !$this->request->staff_lock ? 'ipsMenu_itemChecked' : '',
			'title'	=>\IPS\Member::loggedIn()->language()->addToStack( 'request_staff_lock_off' ),
			'link'	=> $this->request->acpUrl()->setQueryString( array( 'do' => 'staffLock', 'lock' => 0 ) )->csrf(),
			'data'	=> array( 'group' => 'staff_lock', 'noSet' => 'true' ),
		);

		/* Regular actions */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_delete' ) )
		{
			\IPS\Output::i()->sidebar['actions']['delete'] = array(
				'icon'			=> 'times-circle',
				'title'			=> 'delete',
				'link'			=> $this->request->acpUrl()->setQueryString( 'do', 'delete' )->csrf(),
				'data'			=> array( 'confirm' => '' )
			);
		}

		if( \IPS\Member::loggedIn()->hasAcpRestriction( \IPS\Application::load('nexus'), \IPS\Application\Module::get( 'nexus', 'customers' ), 'purchases_view' ) )
		{
			\IPS\Output::i()->sidebar['actions']['purchase']	= array(
				'icon'			=> 'cube',
				'title'			=> 'associate_purchase',
				'link'			=> $this->request->acpUrl()->setQueryString( 'do', 'associate' ),
				'data'			=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('associate'), 'ipsDialog-size' => 'narrow', 'role' => 'associatePurchaseMenu' ),
				'tooltip'		=> \IPS\Member::loggedIn()->language()->addToStack('keyboard_shortcut_purchase')
			);
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->request( $this->request, $requestActions );

		/* Display */
		\IPS\Output::i()->customHeader = \IPS\Theme::i()->getTemplate( 'support' )->requestHeader( $this->request );
		\IPS\Output::i()->title = "#{$this->request->id} " . $this->request->mapped('title');
		\IPS\Output::i()->showTitle = FALSE;
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_support.js', 'nexus', 'admin' ) );
		\IPS\Output::i()->globalControllers[] = 'nexus.admin.support.request';
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support_responsive.css', 'nexus', 'admin' ) );
		}
	}
	
	/**
	 * Hovercard
	 *
	 * @return	void
	 */
	protected function hovercard()
	{
		/* Load request */
		try
		{
			$request = \IPS\nexus\Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/K', 404, '' );
		}
		
		/* Start with the first message */
		$overview = array( 'firstMessage' => array( 'support_first_message', $request->comments( 1, 0, 'date', 'asc', NULL, FALSE ) ) );
		
		/* Add the last customer reply */
		if ( $request->last_reply != $request->started )
		{
			if ( $latestPost = $request->comments( 1, 0, 'date', 'desc', NULL, FALSE, NULL, array( '( reply_type=? OR reply_type=? OR reply_type=? )', \IPS\nexus\Support\Reply::REPLY_MEMBER, \IPS\nexus\Support\Reply::REPLY_ALTCONTACT, \IPS\nexus\Support\Reply::REPLY_EMAIL ) ) )
			{
				$overview['lastCustomerReply'] =  array( 'support_last_customer_reply', $latestPost );
			}
		}
		
		/* Add the last staff reply */
		if ( $request->last_staff_reply )
		{
			if ( $latestStaffReply = $request->comments( 1, 0, 'date', 'desc', NULL, FALSE, NULL, array( 'reply_type=?', \IPS\nexus\Support\Reply::REPLY_STAFF ) ) )
			{
				$overview['lastStaffReply'] =  array( 'support_last_staff_reply', $latestStaffReply );
			}
		}
		
		/* And the latest note */
		if ( $latestNote = $request->comments( 1, 0, 'date', 'desc', NULL, NULL, NULL, array( 'reply_type=?', \IPS\nexus\Support\Reply::REPLY_HIDDEN ) ) )
		{
			$overview['lastNote'] =  array( 'support_last_note', $latestNote );
		}
		
		/* Display */		
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'support' )->requestHover( $request, $overview ) );
	}
	
	/**
	 * Go to first unread
	 *
	 * @return	void
	 */
	public function getNewComment()
	{
		/* Load request */
		try
		{
			$request = \IPS\nexus\Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/L', 404, '' );
		}
		
		/* What order are we viewing? */
		$order = isset( \IPS\Request::i()->cookie['support_replies_order'] ) ? mb_strtolower( \IPS\Request::i()->cookie['support_replies_order'] ) : 'desc';
		
		/* Have we read it before? */
		$timeLastRead = $request->timeLastRead();
		if ( $timeLastRead instanceof \IPS\DateTime )
		{
			if( $unreadComment = $request->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead ) )
			{
				$where = array( array( 'reply_request=?', $request->id ) );
				
				if ( $order === 'desc' )
				{
					$where[] = array( 'reply_id>?', $unreadComment->id );
				}
				else
				{
					$where[] = array( 'reply_id<?', $unreadComment->id );
				}
				
				$commentPosition = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', $where )->first();
				
				$url = $request->acpUrl();
				$page = ceil( $commentPosition / \IPS\nexus\Support\Request::getCommentsPerPage() );
				if ( $page != 1 )
				{
					$url = $url->setPage( 'page', $page );
				}
				$url = $url->setFragment( "reply-{$unreadComment->id}" );
				
				\IPS\Output::i()->redirect( $url );
			}
			else
			{
				\IPS\Output::i()->redirect( $request->acpUrl() );
			}
		}
		/* Nope? Just go to the first message */
		else
		{
			$url = $request->acpUrl();
			
			if ( $order === 'desc' )
			{
				$lastPage = $request->commentPageCount();
				if ( $lastPage != 1 )
				{
					$url = $url->setPage( 'page', $lastPage );
				}
			}
			
			$firstMessage = $request->comments( 1, 0, 'date', 'asc', NULL, FALSE );			
			$url = $url->setFragment( "reply-{$firstMessage->id}" );
			
			\IPS\Output::i()->redirect( $url );
		}
	}
	
	/**
	 * Find a Comment
	 *
	 * @return	void
	 */
	public function findComment()
	{
		/* Load request and comment */
		try
		{
			$request = \IPS\nexus\Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$comment = \IPS\nexus\Support\Reply::load( \IPS\Request::i()->comment );
			if ( $comment->request != $request->id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/M', 404, '' );
		}
		
		/* Find its position */
		$order = isset( \IPS\Request::i()->cookie['support_replies_order'] ) ? mb_strtolower( \IPS\Request::i()->cookie['support_replies_order'] ) : 'desc';
		$where = array( array( 'reply_request=?', $request->id ) );
		if ( $order === 'desc' )
		{
			$where[] = array( 'reply_id>?', $comment->id );
		}
		else
		{
			$where[] = array( 'reply_id<?', $comment->id );
		}
		$commentPosition = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', $where )->first();
				
		/* Redirect */
		$url = $request->acpUrl();
		$page = ceil( $commentPosition / \IPS\nexus\Support\Request::getCommentsPerPage() );
		if ( $page != 1 )
		{
			$url = $url->setPage( 'page', $page );
		}
		$url = $url->setFragment( "reply-{$comment->id}" );
		\IPS\Output::i()->redirect( $url );
	}
	
	/**
	 * Pending Response Send/Discard
	 *
	 * @return	void
	 */
	protected function pending()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$message = Support\Reply::loadAndCheckPerms( \IPS\Request::i()->response );
			
			if ( \IPS\Request::i()->send )
			{
				$message->sendPending();
			}
			else
			{
				/* Discard the message and any related changes */
				$url = $message->item()->acpUrl();
				$message->delete();

				\IPS\Output::i()->redirect( $url );
			}
			
			if ( isset( \IPS\Request::i()->department ) and \IPS\Request::i()->department != -1 )
			{
				$newDepartment = Support\Department::load( \IPS\Request::i()->department );
				if ( $message->item()->department != $newDepartment )
				{
					$message->item()->log( 'department', $message->item()->department, $newDepartment );
					$message->item()->department = $newDepartment;
				}
			}
			if ( isset( \IPS\Request::i()->status ) and \IPS\Request::i()->status != -1 )
			{
				$message->item()->status = Support\Status::load( \IPS\Request::i()->status );
			}
			if ( isset( \IPS\Request::i()->staff ) and \IPS\Request::i()->staff != -1 )
			{
				$newStaff = \IPS\Request::i()->staff ? \IPS\Member::load( \IPS\Request::i()->staff ) : NULL;
				if ( $message->item()->staff != $newStaff )
				{
					if ( $newStaff )
					{
						$message->item()->log( 'staff', $message->item()->staff, $newStaff );
					}
					$message->item()->staff = $newStaff;
				}
			}
			$message->item()->save();
			
			\IPS\Output::i()->redirect( $message->item()->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/1', 404, '' );
		}
	}
	
	/**
	 * Edit Title
	 *
	 * @return	void
	 */
	protected function editTitle()
	{
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Text( 'support_title', $request->title, TRUE, array( 'maxLength' => 255 ) ) );
			
			if ( $values = $form->values() )
			{
				$request->title = $values['support_title'];
				$request->save();
				\IPS\Output::i()->redirect( $request->acpUrl() );
			}
			
			\IPS\Output::i()->output = $form;
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/2', 404, '' );
		}
	}
	
	/**
	 * Edit Custom Fields
	 *
	 * @return	void
	 */
	protected function cfields()
	{
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$customFieldValues = $request->cfields;
			
			$form = new \IPS\Helpers\Form;
			foreach ( $request->department->customFields() as $field )
			{
				if ( $field->type === 'Editor' )
				{
					$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => array( $request->id, $field->id, 'fields' ) ) );
				}
				$helper = $field->buildHelper( isset( $customFieldValues[ $field->id ] ) ? $customFieldValues[ $field->id ] : NULL );
				$form->add( $helper );
			}
						
			if ( $values = $form->values( TRUE ) )
			{
				$save = array();
				foreach ( $values as $k => $v )
				{
					$save[ mb_substr( $k, 13 ) ] = $v;
				}
				$request->cfields = $save;				
				$request->save();
				\IPS\Output::i()->redirect( $request->acpUrl() );
			}
			
			\IPS\Output::i()->output = $form;
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/3', 404, '' );
		}
	}
	
	/**
	 * Set Status
	 *
	 * @return	void
	 */
	protected function status()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{	
			/* Init */		
			$return = array( 'note_status' => \IPS\Request::i()->status );
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$status = Support\Status::load( \IPS\Request::i()->status );
			$oldStatus = $request->status;
			$request->status = $status;
			
			/* Assign it if we have to */
			if ( $status->assign )
			{
				if ( $request->staff and $request->staff->member_id != \IPS\Member::loggedIn()->member_id )
				{
					$return['alert'] = \IPS\Member::loggedIn()->language()->addToStack( 'you_have_stolen_request', FALSE, array( 'sprintf' => array( $request->staff->name ) ) );
				}
				$request->staff = \IPS\Member::loggedIn();
				$return['staff'] = array( 'id' => \IPS\Member::loggedIn()->member_id, 'name' => \IPS\Member::loggedIn()->name );
				$return['note_assign_to'] = \IPS\Member::loggedIn()->member_id;
				$return['staffBadge'] = \IPS\Member::loggedIn()->language()->addToStack( 'assigned_to_x', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack('you') ) ) );
			}
			/* Or set the previous status was "Working", release our assigning */
			elseif ( $oldStatus->assign and $request->staff )
			{
				$request->staff = NULL;
				$return['staff'] = array( 'id' => 0, 'name' => \IPS\Member::loggedIn()->language()->addToStack('unassigned') );
				$return['note_assign_to'] = 0;
				$return['staffBadge'] = '';
			}
			$request->save();
			
			/* Log */
			if ( $status->log )
			{
				$request->log( 'status', $oldStatus, $status );
			}
			
			/* Return */
			$return['statusBadge'] = \IPS\Theme::i()->getTemplate('support')->status( $request->status );
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( $return );
			}
			else
			{
				\IPS\Output::i()->redirect( $request->acpUrl() );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/4', 404, '' );
		}
	}
	
	/**
	 * Set Severity
	 *
	 * @return	void
	 */
	protected function severity()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$new = Support\Severity::load( \IPS\Request::i()->severity );
			$request->log( 'severity', $request->severity, $new );
			$request->severity = $new;
			$request->save();
			if ( \IPS\Request::i()->isAjax() )
			{
				$return = array();
				$return['severityBadge'] = $request->severity->color != '000' ? \IPS\Theme::i()->getTemplate('support')->severity( $request->severity ) : '';
				\IPS\Output::i()->json( $return );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/5', 404, '' );
		}
	}
	
	/**
	 * Control member's permission to set severities
	 *
	 * @return	void
	 */
	protected function noSev()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_block_sev' );
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$request->author()->cm_no_sev = \IPS\Request::i()->no_sev;
			$request->author()->save();
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array() );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/6', 404, '' );
		}
	}
	
	/**
	 * Staff Lock
	 *
	 * @return	void
	 */
	protected function staffLock()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$request->staff_lock = \IPS\Request::i()->lock;
			$request->save();
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'assign_to' => ( $request->staff_lock ? ( $request->staff ? $request->staff->member_id : 0 ) : 0 ) ) );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/7', 404, '' );
		}
	}
	
	/**
	 * Set Department
	 *
	 * @return	void
	 */
	protected function department()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$new = Support\Department::load( \IPS\Request::i()->department );
			$request->log( 'department', $request->department, $new );
			$request->department = $new;
			$request->save();
			if ( \IPS\Request::i()->isAjax() )
			{
				$stockActionOptions = array();
				$stockActions = array( 0 => '' );
				foreach ( \IPS\nexus\Support\StockAction::roots( NULL, NULL, "action_show_in='*' OR " . \IPS\Db::i()->findInSet( 'action_show_in', array( $request->department->id ) ) ) as $action )
				{
					$stockActions[ $action->id ] = $action->_title;
				}
				
				$purchaseWarning = NULL;
				if ( $request->department->packages )
				{
					if ( !$request->purchase )
					{
						$purchaseWarning = 'purchaseWarningNone';
					}
					elseif ( !$request->purchase->active )
					{
						$purchaseWarning = 'purchaseWarningInactive';
					}
					
					if ( $purchaseWarning )
					{
						$purchaseWarning .= ( $request->department->require_package ? 'Required' : 'Optional' );
					}
				}
				
				\IPS\Output::i()->json( array( 'department' => \IPS\Request::i()->department, 'note_department' => \IPS\Request::i()->department, 'stockActions' => $stockActions, 'purchaseWarning' => $purchaseWarning ) );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/8', 404, '' );
		}
	}
	
	/**
	 * Set Staff
	 *
	 * @return	void
	 */
	protected function staff()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$new = \IPS\Request::i()->staff ? \IPS\Member::load( \IPS\Request::i()->staff ) : NULL;
			$request->log( 'staff', $request->staff, $new );
			$request->staff = $new;
			$request->save();
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'assign_to' => ( $request->staff_lock ? ( $request->staff ? $request->staff->member_id : 0 ) : 0 ),
					'note_assign_to' => $request->staff ? $request->staff->member_id : 0,
					'staffBadge' => $request->staff ? \IPS\Member::loggedIn()->language()->addToStack( 'assigned_to_x', FALSE, array( 'sprintf' => array( $request->staff->name ) ) ) : ''
				) );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/9', 404, '' );
		}
	}
	
	/**
	 * Track
	 *
	 * @return	void
	 */
	protected function track()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( \IPS\Request::i()->track )
			{
				\IPS\Db::i()->insert( 'nexus_support_tracker', array(
					'member_id'		=> \IPS\Member::loggedIn()->member_id,
					'request_id'	=> $request->id,
					'notify'		=> \IPS\Request::i()->notify
				), TRUE );
			}
			else
			{
				\IPS\Db::i()->delete( 'nexus_support_tracker', array( 'member_id=? AND request_id=?', \IPS\Member::loggedIn()->member_id, $request->id ) );
			}
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array() );
			}
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/A', 404, '' );
		}
	}
	
	/**
	 * Associate
	 *
	 * @return	void
	 */
	protected function associate()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'purchases_view', 'nexus', 'customers' );
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$form = new \IPS\Helpers\Form;
			
			$parentContacts = \IPS\nexus\Customer::load( $request->author()->member_id )->parentContacts();
			if ( \count( $parentContacts ) )
			{
				$form->class = 'ipsForm_vertical';
				$options = array();
				$toggles = array();
				foreach ( $parentContacts as $contact )
				{
					$options[ $contact->main_id->member_id ] = $contact->main_id->cm_name;
					$toggles[ $contact->main_id->member_id ] = array( 'associated_purchase_' . $contact->main_id->member_id );
				}
				$options[ $request->author()->member_id ] = $request->author()->name;
				$toggles[ $request->author()->member_id ] = array( 'associated_purchase_' . $request->author()->member_id );
				$form->add( new \IPS\Helpers\Form\Radio( 'support_account', $request->author()->member_id, TRUE, array( 'options' => $options, 'toggles' => $toggles, 'parse' => 'normal' ) ) );
				foreach ( $options as $memberId => $memberName )
				{
					$field = new \IPS\Helpers\Form\Node( 'associated_purchase_' . $memberId, $memberId == $request->author()->member_id ? $request->purchase : NULL, TRUE, array( 'class' => 'IPS\nexus\Purchase', 'forceOwner' => \IPS\Member::load( $memberId ), 'zeroVal' => 'none' ), NULL, NULL, NULL, 'associated_purchase_' . $memberId );
					$field->label = \IPS\Member::loggedIn()->language()->addToStack('associated_purchase');
					$form->add( $field );
				}
			}
			else
			{
				$form->class = 'ipsForm_vertical ipsForm_noLabels';
				$form->add( new \IPS\Helpers\Form\Node( 'associated_purchase', $request->purchase, TRUE, array( 'class' => 'IPS\nexus\Purchase', 'forceOwner' => $request->author(), 'zeroVal' => 'none' ) ) );
			}

			if ( $values = $form->values() )
			{
				if ( isset( $values['support_account'] ) )
				{
					if ( $values['support_account'] != $request->member )
					{
						\IPS\Db::i()->update( 'nexus_support_replies', array( 'reply_type' => \IPS\nexus\Support\Reply::REPLY_MEMBER ), array( 'reply_request=? AND reply_member=?', $request->id, $values['support_account'] ) );
						\IPS\Db::i()->update( 'nexus_support_replies', array( 'reply_type' => \IPS\nexus\Support\Reply::REPLY_ALTCONTACT ), array( 'reply_request=? AND reply_member=?', $request->id, $request->member ) );
						$request->member = $values['support_account'];
					}
					$request->log( 'purchase', $request->purchase, $values[ 'associated_purchase_' . $values['support_account'] ] ?: NULL );
					$request->purchase = $values[ 'associated_purchase_' . $values['support_account'] ] ?: NULL;
				}
				else
				{				
					$request->log( 'purchase', $request->purchase, $values['associated_purchase'] ?: NULL );
					$request->purchase = $values['associated_purchase'] ?: NULL;
				}
				$request->save();
				\IPS\Output::i()->redirect( $request->acpUrl() );
			}
			
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' ), 'popupTemplate' ) );			
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/B', 404, '' );
		}
	}
	
	/**
	 * Merge
	 *
	 * @return	void
	 */
	protected function merge()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_merge' );
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$request->mergeIn( array( Support\Request::loadAndCheckPerms( \IPS\Request::i()->merge ) ) ) ;
			$request->log( 'merge', 0, 0 );
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/C', 404, '' );
		}
	}
	
	/**
	 * Multimod
	 *
	 * @return	void
	 */
	protected function multimod()
	{
		/* CSRF check */
		\IPS\Session::i()->csrfCheck();
		
		/* Load request */
		$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
		
		/* Are we going to leave any messages in the request? */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', array( array( 'reply_request=? AND reply_hidden=0', $request->id ), \IPS\Db::i()->in( 'reply_id', array_keys( \IPS\Request::i()->multimod ), TRUE ) ) )->first();
		if ( !$count )
		{
			\IPS\Output::i()->error( 'can_not_multimod_all', '1X208/H', 403, '' );
		}
		
		/* Splitting? */
		if ( \IPS\Request::i()->modaction === 'split' )
		{
			/* Permission check */			
			\IPS\Dispatcher::i()->checkAcpPermission( 'requests_split' );
			
			/* Check we're not trying to split notes only */
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', array( array( 'reply_hidden=0', $request->id ), \IPS\Db::i()->in( 'reply_id', array_keys( \IPS\Request::i()->multimod ) ) ) )->first();
			if ( !$count )
			{
				\IPS\Output::i()->error( 'can_not_split_notes_only', '1S208/J', 403, '' );
			}
			
			/* Create form */
			$url = $request->acpUrl()->setQueryString( array( 'do' => 'multimod', 'modaction' => 'split', 'multimod' => \IPS\Request::i()->multimod ) )->csrf();
			$form = new \IPS\Helpers\Form( 'split_form', 'split', $url, array( 'data-controller' => 'nexus.admin.support.splitForm' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'support_title', sprintf( \IPS\Member::loggedIn()->language()->get( 'split_request_title' ), $request->title, $request->id ), TRUE ) );
			$form->add( new \IPS\Helpers\Form\Node( 'support_department', $request->department, TRUE, array( 'class' => 'IPS\nexus\Support\Department', 'url' => $url ) ) );
			$form->add( new \IPS\Helpers\Form\Node( 'r_status', $request->status, TRUE, array( 'class' => 'IPS\nexus\Support\Status', 'url' => $url ) ) );
			$form->add( new \IPS\Helpers\Form\Node( 'support_severity', $request->severity, TRUE, array( 'class' => 'IPS\nexus\Support\Severity', 'url' => $url ) ) );
			$form->add( new \IPS\Helpers\Form\Select( 'r_staff', $request->staff ? $request->staff->member_id : 0, FALSE, array( 'parse' => 'normal', 'options' =>  array( 0 => \IPS\Member::loggedIn()->language()->addToStack('unassigned') ) + \IPS\nexus\Support\Request::staff() ) ) );
			
			if ( $values = $form->values() )
			{
				$newRequest = new \IPS\nexus\Support\Request;
				$newRequest->title = $values['support_title'];
				$newRequest->member = $request->member;
				$newRequest->email = $request->email;
				$newRequest->department = $values['support_department'];
				if ( $request->purchase )
				{
					$newRequest->purchase = $request->purchase;
				}
				$newRequest->status = $values['r_status'];
				$newRequest->severity = $values['support_severity'];
				if ( $values['r_staff'] )
				{
					$newRequest->staff = \IPS\Member::load( $values['r_staff']);
				}
				$newRequest->cfields = $request->cfields;
				$newRequest->notify = $request->notify;
				$newRequest->save();
				
				foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_replies', array( array( 'reply_request=?', $request->id ), \IPS\Db::i()->in( 'reply_id', array_keys( \IPS\Request::i()->multimod ) ) ) ), 'IPS\nexus\Support\Reply' ) as $comment )
				{
					$comment->move( $newRequest );
				}
				
				$request->rebuildFirstAndLastCommentData();
				$newRequest->rebuildFirstAndLastCommentData();
				
				
				
				$request->log( 'split_away', $request, $newRequest );
				$newRequest->log( 'split_new', $request, $newRequest );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array( 'oldUrl' => (string) $request->acpUrl(), 'newUrl' => (string) $newRequest->acpUrl() ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $newRequest->acpUrl() );
				}
			}
			
			\IPS\Output::i()->output = $form;
		}
		/* Or deleting? */
		elseif ( \IPS\Request::i()->modaction === 'delete' )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'requests_reply_delete' );
			
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_replies', array( array( 'reply_request=?', $request->id ), \IPS\Db::i()->in( 'reply_id', array_keys( \IPS\Request::i()->multimod ) ) ) ), 'IPS\nexus\Support\Reply' ) as $comment )
			{
				$comment->delete();
			}
			
			$request->rebuildFirstAndLastCommentData();
			
			\IPS\Output::i()->redirect( $request->acpUrl() );
		}
		else
		{
			\IPS\Output::i()->error( 'generic_error', '3X208/I', 403, '' );
		}
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_delete' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			\IPS\Session::i()->log( 'acplogs__deleted_request', array( $request->id => FALSE ) );
			$request->delete();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=support&controller=requests" ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/D', 404, '' );
		}
	}
	
	/**
	 * Delete Reply
	 *
	 * @return	void
	 */
	protected function deleteReply()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_reply_delete' );
		
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$reply = Support\Reply::loadAndCheckPerms( \IPS\Request::i()->id );
			\IPS\Session::i()->log( 'acplogs__deleted_reply', array( $reply->id => FALSE ) );
			$reply->delete();
			\IPS\Output::i()->redirect( $reply->item()->acpUrl() );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/G', 404, '' );
		}
	}
	
	/**
	 * View Feedback
	 *
	 * @return	void
	 */
	protected function feedback()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_ratings_feedback' );
		
		try
		{
			$reply = \IPS\nexus\Support\Reply::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/E', 404, '' );
		}
		
		$rating = NULL;
		try
		{
			$rating = \IPS\Db::i()->select( '*', 'nexus_support_ratings', array( 'rating_reply=?', $reply->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/F', 404, '' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->feedback( $rating );
	}
	
	/**
	 * Get default action
	 *
	 * @return	string
	 */
	public static function defaultReplyAction()
	{
		$defaultAction = NULL;
		try
		{
			$defaultAction = \IPS\Db::i()->select( 'action', 'nexus_support_staff_preferences', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e ) {}

		if ( !$defaultAction )
		{
			$defaultAction = isset( \IPS\Request::i()->cookie['support_primary_action'] ) ? \IPS\Request::i()->cookie['support_primary_action'] : 'first';
		}
		return $defaultAction;
	}

	/**
	 * Redirect to next/prev support request
	 *
	 * @return void
	 */
	public function nextRequest()
	{
		$type = \IPS\Request::i()->ty == 'next' ? 0 : 1;

		try
		{
			$request = Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
			$nextRequest = $request->nextPrevious( $type );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X208/N', 404, '' );
		}

		/* Nothing left? */
		if( $nextRequest === NULL )
		{
			\IPS\Output::i()->error( 'next_request_not_found', '2X208/O', 404, '' );
		}

		\IPS\Output::i()->redirect( $nextRequest->acpUrl() );
	}
}

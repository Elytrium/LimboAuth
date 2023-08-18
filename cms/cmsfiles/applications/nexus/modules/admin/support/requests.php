<?php
/**
 * @brief		Support Requests
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		09 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Requests
 */
class _requests extends \IPS\Dispatcher\Controller
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
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support.css', 'nexus', 'admin' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'support_responsive.css', 'nexus', 'admin' ) );
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_support.js', 'nexus', 'admin' ) );

		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Can we access anything? */
		if ( !\count( \IPS\nexus\Support\Department::departmentsWithPermission() ) )
		{
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'departments_manage' ) )
			{
				\IPS\Output::i()->error( 'err_no_departments_with_perm1', '1X261/1', 403, '' );
			}
			else
			{
				\IPS\Output::i()->error( 'err_no_departments_with_perm2', '1X261/2', 403, '' );
			}
		}
										
		/* Get stream - can set new... */
		if ( ( isset( \IPS\Request::i()->stream ) and \IPS\Request::i()->stream !== 'custom' ) or isset( \IPS\Request::i()->member ) or isset( \IPS\Request::i()->email ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Specific stream */
			if ( isset( \IPS\Request::i()->stream ) )
			{
				try
				{
					$stream = \IPS\nexus\Support\Stream::load( \IPS\Request::i()->stream );
				}
				catch ( \OutOfRangeException $e )
				{
					\IPS\Output::i()->error( 'node_error', '2X261/4', 404, '' );
				}
			}
			/* Temporary stream */
			else
			{
				if( isset( \IPS\Request::i()->member ) )
				{
					$customer = \IPS\nexus\Customer::load( \IPS\Request::i()->member );
				}
				else
				{
					$customer = \IPS\nexus\Customer::constructFromData( array( 'email' => \IPS\Request::i()->email ) );
				}
				$stream = \IPS\nexus\Support\Stream::customer( $customer );
				$stream->temporary = TRUE;
				$stream->owner = \IPS\Member::loggedIn()->member_id;
				$stream->save();
			}
			
			/* Set the cookie */
			\IPS\Request::i()->setCookie( 'support_stream', $stream->id, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			
			/* Delete previously stored temporary streams */
			$where = array(
				array( 'stream_owner=?', \IPS\Member::loggedIn()->member_id ),
				array( 'stream_temporary=?', 1 )
			);
			if ( !\in_array( $stream->id, array( 'open', 'assigned', 'tracked' ) ) )
			{
				$where[] = array( 'stream_id<>?', $stream->id );
			}
			\IPS\Db::i()->delete( 'nexus_support_streams', $where );	

			/* If this isn't an AJAX request, redirect to it to stop the CSRF key hanging around in the address bar */
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
			}
		}
		/* ... or use preferred stream */
		elseif ( isset( \IPS\Request::i()->cookie['support_stream'] ) )
		{
			try
			{
				$stream = \IPS\nexus\Support\Stream::load( \IPS\Request::i()->cookie['support_stream'] );
			}
			catch ( \OutOfRangeException $e )
			{
				$stream = \IPS\nexus\Support\Stream::load('open');
			}
		}
		/* ... or just use the default */
		else
		{
			$stream = \IPS\nexus\Support\Stream::load('open');
		}
		
		/* Are we grouping by department? */
		if ( isset( \IPS\Request::i()->groupByDepartment ) )
		{
			\IPS\Session::i()->csrfCheck();
			if ( \IPS\Request::i()->groupByDepartment )
			{
				\IPS\Request::i()->setCookie( 'support_dpt_group', 1, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			}
			else
			{
				\IPS\Request::i()->setCookie( 'support_dpt_group', 0, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			}
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
			}
		}
		$groupByDepartment = ( isset( \IPS\Request::i()->cookie['support_dpt_group'] ) and \IPS\Request::i()->cookie['support_dpt_group'] );
		
		/* What's our order? */
		if ( \IPS\Request::i()->sortBy )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'support_sort', \IPS\Request::i()->sortBy, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
			}
		}
		if ( \IPS\Request::i()->sortDir )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'support_order', \IPS\Request::i()->sortDir, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
			}
		}
		if ( isset( \IPS\Request::i()->honorSeverities ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Request::i()->setCookie( 'support_honor_severities', \IPS\Request::i()->honorSeverities, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
			}
		}
		$order = array();
		if ( $groupByDepartment )
		{
			$order[] = 'nexus_support_staff_dpt_order.dpt_position';
			$order[] = 'nexus_support_departments.dpt_position'; // This is to prevent an issue if two departments have the same position (which may be the case if new departments have been created since the user set their preferred order)
		}
		$honorSeverities = ( !isset( \IPS\Request::i()->cookie['support_honor_severities'] ) or \IPS\Request::i()->cookie['support_honor_severities'] );
		if ( $honorSeverities )
		{
			$order[] = 'sev_position ASC';
		}
		$sortBy = ( isset( \IPS\Request::i()->cookie['support_sort'] ) and \in_array( \IPS\Request::i()->cookie['support_sort'], array( 'r_started', 'r_last_new_reply', 'r_last_reply', 'r_last_staff_reply' ) ) ) ? \IPS\Request::i()->cookie['support_sort'] : 'r_last_new_reply';
		$sortDir = ( isset( \IPS\Request::i()->cookie['support_order'] ) and \in_array( \IPS\Request::i()->cookie['support_order'], array( 'ASC', 'DESC' ) ) ) ? \IPS\Request::i()->cookie['support_order'] : 'ASC';
		$order[] = $sortBy . ' ' . $sortDir;
		$order = implode( ', ', $order );
		
		/* Get the results and pagination */
		$perPage = 25;
		$count = $stream->count( \IPS\Member::loggedIn() );
		$numberOfPages = ceil( $count / $perPage );
		$page = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page <= $numberOfPages ) ? \intval( \IPS\Request::i()->page ) : 1;
		$results = $stream->results( \IPS\Member::loggedIn(), $order, $page, $perPage );
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests'), $numberOfPages, $page, $perPage );
		
		/* Are any of those tracked/participated in? */
		$supportRequestsInView = array_keys( iterator_to_array( $results ) );
		$tracked = iterator_to_array( \IPS\Db::i()->select( array( 'request_id', 'notify' ), 'nexus_support_tracker', array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'request_id', $supportRequestsInView ) ) ) )->setKeyField( 'request_id' )->setValueField( 'notify' ) );
		$participatedIn = iterator_to_array( \IPS\Db::i()->select( 'DISTINCT reply_request', 'nexus_support_replies', array( array( 'reply_member=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'reply_request', $supportRequestsInView ) ) ) ) );
		
		/* Filter form */
		$form = \IPS\nexus\Support\Stream::load('open')->form( $stream, \IPS\Http\Url::internal('app=nexus&module=support&controller=requests') );
		if ( $values = $form->values() )
		{
			if ( $values['stream'] == 'custom' )
			{
				\IPS\Db::i()->delete( 'nexus_support_streams', array( 'stream_owner=? AND stream_temporary=1', \IPS\Member::loggedIn()->member_id ) );
				$stream = \IPS\nexus\Support\Stream::createFromForm( $values );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests&stream=' . $stream->id )->csrf() );
			}
			else
			{
				\IPS\Request::i()->setCookie( 'support_stream', $values['stream'], \IPS\DateTime::create()->add( new \DateInterval( 'P1Y') ), FALSE );
			}
		}
		$form = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'support' ), 'filterForm' ) );
					
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array(
				'form'		=> $form,
				'results'	=> \IPS\Theme::i()->getTemplate( 'support' )->requestsTable( $results, $pagination, $groupByDepartment, $sortBy, $sortDir, $tracked, $participatedIn, $honorSeverities )
			) );
			return;
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_support_requests');
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'support' )->requests( $form, $stream, $results, $pagination, $groupByDepartment, $sortBy, $sortDir, $tracked, $participatedIn, $honorSeverities );
		}
		
		/* Create Button */
		if( \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_create' ) )
		{
			$createUrl = \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=create&_new=1');
			\IPS\Output::i()->sidebar['actions']['create'] = array(
				'icon'	=> 'plus',
				'title'	=> 'add',
				'link'	=> $createUrl,
			);
		}
		
		/* Settings */
		\IPS\Output::i()->sidebar['actions']['my_preferences'] = array(
			'icon'	=> 'cog',
			'title'	=> 'my_preferences',
			'link'	=> \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=preferences'),
		);
		
		/* History Button */
		\IPS\Output::i()->sidebar['actions']['history'] = array(
			'icon'	=> 'clock-o',
			'title'	=> 'my_history',
			'link'	=> \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=history'),
		);
	}
	
	/**
	 * Filter form
	 *
	 * @return 	\IPS\Helpers\Form
	 */
	protected function filters()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__nexus_support_requests');
		\IPS\Output::i()->output = \IPS\nexus\Support\Stream::load('open')->form( NULL, \IPS\Http\Url::internal('app=nexus&module=support&controller=requests') )->customTemplate( array( \IPS\Theme::i()->getTemplate( 'support' ), 'filterForm' ) );
	}
	
	/**
	 * Save a temporary stream
	 *
	 * @return 	void
	 */
	protected function saveStream()
	{
		if ( isset( \IPS\Request::i()->stream_title ) )
		{
			\IPS\Session::i()->csrfCheck();
			\IPS\Db::i()->update( 'nexus_support_streams', array(
				'stream_title'		=> \IPS\Request::i()->stream_title,
				'stream_temporary'	=> 0
			), array( 'stream_owner=? AND stream_temporary=1', \IPS\Member::loggedIn()->member_id ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ) );
		}
		else
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Text( 'stream_title', '', TRUE ) );
			\IPS\Output::i()->output = $form;
		}
	}
	
	/**
	 * Edit a saved stream
	 *
	 * @return 	void
	 */
	protected function editStream()
	{
		try
		{
			$stream = \IPS\nexus\Support\Stream::load( \IPS\Request::i()->id );
			if ( $stream->temporary or $stream->owner != \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X261/3', 404, '' );
		}
		
		$form = $stream->form( $stream, \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests&do=editStream&id=' . $stream->id ) );
		$form->addTab('title');
		$form->add( new \IPS\Helpers\Form\Text( 'stream_title', $stream->title, TRUE ) );
		$form->addButton( 'delete', 'submit', NULL, 'ipsButton ipsButton_light', array( 'name' => 'deleteButton', 'value' => 1 ) );
				
		if ( $values = $form->values() )
		{
			if ( isset( \IPS\Request::i()->deleteButton ) and \IPS\Request::i()->deleteButton )
			{
				$stream->delete();
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests' ) );
			}
			else
			{			
				$stream->title = $values['stream_title'];
				$stream->updateFromForm( $values );
				$stream->save();
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests&stream=' . $stream->id )->csrf() );
			}
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'support' ), 'filterForm' ) );
	}
	
	/**
	 * Multimod
	 *
	 * @return	void
	 */
	protected function multimod()
	{
		\IPS\Session::i()->csrfCheck();

		$notices = array();
		
		/* Tracking? */
		if ( \IPS\Request::i()->modaction === 'track_off' )
		{
			\IPS\Db::i()->delete( 'nexus_support_tracker', array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'request_id', array_keys( \IPS\Request::i()->multimod ) ) ) ) );
		}
		elseif ( \IPS\Request::i()->modaction === 'track_on' or \IPS\Request::i()->modaction === 'track_notify' )
		{
			foreach ( array_keys( \IPS\Request::i()->multimod ) as $requestId )
			{
				\IPS\Db::i()->insert( 'nexus_support_tracker', array(
					'member_id'		=> \IPS\Member::loggedIn()->member_id,
					'request_id'	=> $requestId,
					'notify'		=> \IPS\Request::i()->modaction === 'track_notify'
				), TRUE );
			}
		}
				
		/* Everything else requires a loop... */
		else
		{
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_requests', \IPS\Db::i()->in( 'r_id', array_keys( \IPS\Request::i()->multimod ) ) ), 'IPS\nexus\Support\Request' ) as $request )
			{
				if ( $request->canView() )
				{
					/* Set status */
					if ( mb_substr( \IPS\Request::i()->modaction, 0, 7 ) === 'status_' )
					{
						/* Set it */
						$status = \IPS\nexus\Support\Status::load( mb_substr( \IPS\Request::i()->modaction, 7 ) );
						$oldStatus = $request->status;
						$request->status = $status;
						
						/* Assign it if we have to */
						if ( $status->assign )
						{
							if ( $request->staff and $request->staff->member_id != \IPS\Member::loggedIn()->member_id )
							{
								$notices[] = \IPS\Member::loggedIn()->language()->addToStack( 'you_have_stolen_request_multimod', FALSE, array( 'sprintf' => array( $request->id, $request->staff->name ) ) );
							}

							$request->staff = \IPS\Member::loggedIn();
						}
						/* Or set the previous status was "Working", release our assigning */
						elseif ( $oldStatus->assign and $request->staff )
						{
							$request->staff = NULL;
						}
						
						/* Save and Log */
						$request->save();
						if ( $status->log )
						{
							$request->log( 'status', $oldStatus, $status );
						}
					}
					
					/* Set severity */
					elseif ( mb_substr( \IPS\Request::i()->modaction, 0, 9 ) === 'severity_' )
					{
						$new = \IPS\nexus\Support\Severity::load( mb_substr( \IPS\Request::i()->modaction, 9 ) );
						$request->log( 'severity', $request->severity, $new );
						$request->severity = $new;
						$request->save();
					}
					
					/* Set department */
					elseif ( mb_substr( \IPS\Request::i()->modaction, 0, 11 ) === 'department_' )
					{
						$new = \IPS\nexus\Support\Department::load( mb_substr( \IPS\Request::i()->modaction, 11 ) );
						$request->log( 'department', $request->department, $new );
						$request->department = $new;
						$request->save();
					}
					
					/* Assign */
					elseif ( mb_substr( \IPS\Request::i()->modaction, 0, 6 ) === 'staff_' )
					{
						$staff = mb_substr( \IPS\Request::i()->modaction, 6 );
						$new = $staff ? \IPS\Member::load( $staff ) : NULL;
						$request->log( 'staff', $request->staff, $new );
						$request->staff = $new;
						$request->save();
					}
					
					/* Delete? */
					elseif ( \IPS\Request::i()->modaction === 'delete' and \IPS\Member::loggedIn()->hasAcpRestriction( 'nexus', 'support', 'requests_delete' ) )
					{
						$request->delete();
					}
				}
			}
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=support&controller=requests' ), implode( ', ', $notices ) );
	}
	
	/**
	 * My Preferences
	 *
	 * @return	void
	 */
	protected function preferences()
	{
		$types = array( 'support_notify_new' => 'n', 'support_notify_replies' => 'r', 'support_notify_assign' => 'a' );
		
		$existing = iterator_to_array( \IPS\Db::i()->select( '*', 'nexus_support_notify', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) )->setKeyField('type')->setValueField('departments') );
		
		$myDepartments = array();
		foreach ( \IPS\nexus\Support\Department::roots( NULL, NULL, array( "dpt_staff='*' OR " . \IPS\Db::i()->findInSet( 'dpt_staff', \IPS\nexus\Support\Department::staffDepartmentPerms() ) ) ) as $dpt )
		{
			$myDepartments[ $dpt->id ] = $dpt->_title;
		}
		
		$form = new \IPS\Helpers\Form;
		
		$form->addHeader('my_preferences');
		try
		{
			$preferences = \IPS\Db::i()->select( '*', 'nexus_support_staff_preferences', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			$preferences = NULL;
		}
		$form->add( new \IPS\Helpers\Form\Editor( 'nexus_default_support_content', $preferences ? $preferences['content'] : NULL, FALSE, array( 'app' => 'nexus', 'key' => 'Support', 'autoSaveKey' => 'default_content', 'tags' => array(
			'{customer_first_name}'	=> \IPS\Member::loggedIn()->language()->addToStack('customer_first_name'),
			'{customer_last_name}'	=> \IPS\Member::loggedIn()->language()->addToStack('customer_last_name'),
			'{customer_full_name}'	=> \IPS\Member::loggedIn()->language()->addToStack('customer_full_name'),
			'{department_name}'		=> \IPS\Member::loggedIn()->language()->addToStack('department_name'),
			'{department_email}'	=> \IPS\Member::loggedIn()->language()->addToStack('department_email'),
			'{default_content}'		=> \IPS\Member::loggedIn()->language()->addToStack('default_content'),
		) ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'nexus_default_support_action', $preferences ? $preferences['action'] : '', FALSE, array( 'options' => array(
			''		=> 'reply_remember_last',
			'first'	=> 'reply_and_go_to_first',
			'stay'	=> 'reply_and_stay',
			'next'	=> 'reply_and_go_to_next',
			'list'	=> 'reply_and_go_to_list',
		) ) ) );
		
		$_myDepartments = iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission() );
		$myOrder = array();
		foreach ( \IPS\Db::i()->select( array( 'department_id' ), 'nexus_support_staff_dpt_order', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ), 'dpt_position ASC' ) as $departmentId )
		{
			if ( array_key_exists( $departmentId, $_myDepartments ) )
			{
				$myOrder[ $departmentId ] = $_myDepartments[ $departmentId ]->_title;
			}
		}
		foreach( $_myDepartments as $department )
		{
			if ( !array_key_exists( $department->id, $myOrder ) )
			{
				$myOrder[ $department->id ] = $department->_title;
			}
		}
		$form->add( new \IPS\Helpers\Form\Sort( 'nexus_group_departments_order', $myOrder, FALSE ) );
		
		$form->addHeader('notify_me_when');
		$form->addMessage( 'support_notify_blurb' );
		foreach ( $types as $k => $t )
		{
			$form->add( new \IPS\Helpers\Form\CheckboxSet( $k, isset( $existing[ $t ] ) ? ( $existing[ $t ] === '*' ? 0 : explode( ',', $existing[ $t ] ) ) : NULL, FALSE, array( 'options' => $myDepartments, 'multiple' => TRUE, 'unlimited' => 0, 'unlimitedLang' => 'all', 'noDefault' => TRUE, 'impliedUnlimited' => TRUE ) ) );
		}
		
		if ( $values = $form->values() )
		{
			\IPS\Db::i()->insert( 'nexus_support_staff_preferences', array(
				'staff_id'	=> \IPS\Member::loggedIn()->member_id,
				'content'	=> $values['nexus_default_support_content'],
				'action'	=> $values['nexus_default_support_action'],
			), TRUE );
			
			\IPS\Db::i()->delete( 'nexus_support_staff_dpt_order', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) );
			
			$i = 1;
			foreach ( $values['nexus_group_departments_order'] as $k => $v )
			{
				\IPS\Db::i()->insert( 'nexus_support_staff_dpt_order', array(
					'staff_id'		=> \IPS\Member::loggedIn()->member_id,
					'department_id'	=> $k,
					'dpt_position'	=> $i++,
				) );
			}
			
			\IPS\Db::i()->delete( 'nexus_support_notify', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) );
			
			foreach ( $types as $k => $t )
			{
				if ( $values[ $k ] === 0 or \count( $values[ $k ] ) )
				{
					\IPS\Db::i()->insert( 'nexus_support_notify', array(
						'staff_id'		=> \IPS\Member::loggedIn()->member_id,
						'type'			=> $t,
						'departments'	=> ( $values[ $k ] === 0 ) ? '*' : implode( ',', $values[ $k ] )
					) );
				}
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal("app=nexus&module=support&controller=requests") );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('my_preferences');
		\IPS\Output::i()->output = $form;
	}
		
	/**
	 * Create
	 *
	 * @return	void
	 */
	protected function create()
	{
		/* Init */
		\IPS\Dispatcher::i()->checkAcpPermission( 'requests_create' );
		$steps = array();
		$url = \IPS\Http\Url::internal('app=nexus&module=support&controller=requests&do=create');
		$initialData = NULL;
		if ( isset( \IPS\Request::i()->member ) )
		{
			$initialData = array( 'member' => \IPS\Request::i()->member );
			$url = $url->setQueryString( 'member', \IPS\Request::i()->member );
		}
		
		/* Owner */
		if ( !isset( \IPS\Request::i()->member ) )
		{
			$steps['request_owner'] = function( $data ) use ( $url )
			{
				$form = new \IPS\Helpers\Form( 'owner', 'continue', $url->setQueryString( '_step', 'request_owner' ) );
				$form->add( new \IPS\Helpers\Form\Radio( 'request_owner_type', isset( $data['email'] ) ? 'email' : 'member', TRUE, array(
					'options' 	=> array( 'member' => 'request_owner_member', 'email' => 'request_owner_email' ),
					'toggles'	=> array( 'member' => array( 'request_owner_member' ), 'email' => array( 'request_owner_email' ) )
				) ) );
				$form->add( new \IPS\Helpers\Form\Member( 'request_owner_member', isset( $data['member'] ) ? \IPS\Member::load( $data['member'] ) : ( isset( \IPS\Request::i()->member ) ? \IPS\Member::load( \IPS\Request::i()->member ) : NULL ), FALSE, array(), NULL, NULL, NULL, 'request_owner_member' ) );
				$form->add( new \IPS\Helpers\Form\Email( 'request_owner_email', isset( $data['email'] ) ? $data['email'] : NULL, FALSE, array(), NULL, NULL, NULL, 'request_owner_email' ) );
				if ( $values = $form->values() )
				{
					if ( $values['request_owner_type'] === 'member' )
					{
						if ( !$values['request_owner_member'] )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack('request_owner_req');
							return (string) $form;
						}
						$data['member'] = $values['request_owner_member']->member_id;
					}
					else
					{
						if ( !$values['request_owner_email'] )
						{
							$form->error = \IPS\Member::loggedIn()->language()->addToStack('request_owner_req');
							return (string) $form;
						}
						
						$member = \IPS\Member::load( $values['request_owner_email'], 'email' );
						if ( $member->member_id )
						{
							$data['member'] = $member->member_id;
						}
						else
						{
							$data['email'] = $values['request_owner_email'];
						}
					}
					
					return $data;
				}
				
				return (string) $form;
			};
		}
		
		/* Stock Actions */
		$stockActions = \IPS\nexus\Support\StockAction::roots();
		if ( \count( $stockActions ) )
		{
			$steps['stock_action'] = function( $data ) use ( $stockActions, $url )
			{
				$options = array( 0 => 'none' );
				foreach ( $stockActions as $action )
				{
					$options[ $action->id ] = $action->_title;
				}
				
				$form = new \IPS\Helpers\Form( 'stock_action', 'continue', $url->setQueryString( '_step', 'stock_action' ) );
				$form->add( new \IPS\Helpers\Form\Radio( 'stock_action', isset( $data['stock_action'] ) ? $data['stock_action'] : 0, FALSE, array(
					'options'	=> $options
				) ) );
				if ( $values = $form->values() )
				{
					$data['stock_action'] = $values['stock_action'];
					return $data;
				}
				
				return (string) $form;
			};
		}
		
		/* Request Details */
		$steps['request_details'] = function( $data ) use ( $stockActions, $url )
		{
			if ( !isset( $data['member'] ) AND isset( \IPS\Request::i()->member ) )
			{
				$data['member'] = \IPS\Request::i()->member;
			}
			
			$stockAction = NULL;
			if ( isset( $data['stock_action'] ) and isset( $stockActions[ $data['stock_action'] ] ) )
			{
				$stockAction = $stockActions[ $data['stock_action'] ];
			}
						
			$form = new \IPS\Helpers\Form( 'request_details', 'continue', $url->setQueryString( '_step', 'request_details' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'support_title', isset( $data['title'] ) ? $data['title'] : NULL, TRUE ) );
			$form->add( new \IPS\Helpers\Form\Node( 'support_department', isset( $data['department'] ) ? $data['department'] : ( $stockAction ? $stockAction->department : NULL ), TRUE, array( 'class' => 'IPS\nexus\Support\Department' ) ) );
			if ( isset( $data['member'] ) )
			{
				$form->add( new \IPS\Helpers\Form\Node( 'support_purchase', isset( $data['purchase'] ) ? $data['purchase'] : 0, FALSE, array( 'class' => 'IPS\nexus\Purchase', 'forceOwner' => \IPS\Member::load( $data['member'] ), 'zeroVal' => 'none' ) ) );
			}
			$form->add( new \IPS\Helpers\Form\Node( 'r_status', isset( $data['status'] ) ? $data['status'] : ( $stockAction ? $stockAction->status : \IPS\nexus\Support\Status::load( TRUE, 'status_default_staff' ) ), TRUE, array( 'class' => 'IPS\nexus\Support\Status' ) ) );
			$form->add( new \IPS\Helpers\Form\Node( 'support_severity', isset( $data['severity'] ) ? $data['severity'] : \IPS\nexus\Support\Severity::load( TRUE, 'sev_default' ), TRUE, array( 'class' => 'IPS\nexus\Support\Severity' ) ) );
			$form->add( new \IPS\Helpers\Form\Select( 'r_staff', isset( $data['staff'] ) ? $data['staff'] : 0, FALSE, array( 'parse' => 'normal', 'options' =>  array( 0 => \IPS\Member::loggedIn()->language()->addToStack('unassigned') ) + \IPS\nexus\Support\Request::staff() ) ) );
			if ( $values = $form->values() )
			{
				$data['title'] = $values['support_title'];
				$data['department'] = $values['support_department']->id;
				if ( isset( $values['support_purchase'] ) and $values['support_purchase'] )
				{
					$data['purchase'] = $values['support_purchase']->id;
				}
				$data['status'] = $values['r_status']->id;
				$data['severity'] = $values['support_severity']->id;
				$data['staff'] = $values['r_staff'];
				return $data;
			}
			return (string) $form;
		};
		
		/* Custom Fields */
		$customFields = \IPS\nexus\Support\CustomField::roots();
		if ( \count( $customFields ) )
		{
			$steps['custom_support_fields'] = function( $data ) use ( $url )
			{
				$customFields = \IPS\nexus\Support\CustomField::roots( NULL, NULL, array( "sf_departments='*' OR " . \IPS\Db::i()->findInSet( 'sf_departments', array( $data['department'] ) ) ) );
				if ( \count( $customFields ) )
				{
					$form = new \IPS\Helpers\Form( 'custom_fields', 'continue', $url->setQueryString( '_step', 'custom_support_fields' ) );
					foreach ( $customFields as $field )
					{
						$form->add( $field->buildHelper( isset( $data['custom_fields']["nexus_cfield_{$field->id}"] ) ? $data['custom_fields']["nexus_cfield_{$field->id}"] : NULL ) );
					}
					if ( $values = $form->values() )
					{
						$data['custom_fields'] = $values;
						return $data;
					}
					return (string) $form;
				}
				else
				{
					return $data;
				}
			};
		}
		
		/* Your Message */
		$steps['your_message'] = function( $data ) use ( $stockActions, $url )
		{
			$stockAction = NULL;
			if ( isset( $data['stock_action'] ) and isset( $stockActions[ $data['stock_action'] ] ) )
			{
				$stockAction = $stockActions[ $data['stock_action'] ];
			}
			
			$department = \IPS\nexus\Support\Department::load( $data['department'] );
			try
			{
				$owner = isset( $data['member'] ) ? \IPS\nexus\Customer::load( $data['member'] ) : NULL;
				
				$defaultContent = \IPS\Db::i()->select( 'content', 'nexus_support_staff_preferences', array( 'staff_id=?', \IPS\Member::loggedIn()->member_id ) )->first();
				$defaultContent = $defaultContent ? str_replace(
					array( '{customer_first_name}', '{customer_last_name}', '{customer_full_name}', '{department_name}', '{department_email}', '{default_content}' ),
					array(
						$owner ? $owner->cm_first_name : '',
						$owner ? $owner->cm_last_name : '',
						$owner ? $owner->cm_name : $data['email'],
						$department->_title,
						$department->email,
						$stockAction ? $stockAction->message : ''
					),
					$defaultContent
				) : ( $stockAction ? $stockAction->message : '' );
			}
			catch ( \UnderflowException $e )
			{
				$defaultContent = $stockAction ? $stockAction->message : NULL;
			}
			
			$form = new \IPS\Helpers\Form( 'your_message', 'save', $url->setQueryString( '_step', 'your_message' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'support_email_customer', TRUE, TRUE, array( 'togglesOn' => array( 'support_create_to', 'support_create_cc', 'support_create_bcc' ) ) ) );
			$form->add( new \IPS\Helpers\Form\Email( 'to', isset( $data['member'] ) ? \IPS\Member::load( $data['member'] )->email : $data['email'], NULL, array(), NULL, NULL, NULL, 'support_create_to' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'cc', array(), FALSE, array( 'autocomplete' => array( 'unique' => TRUE, 'forceLower' => TRUE ) ), array( 'IPS\nexus\Support\Request', '_validateEmail' ), NULL, NULL, 'support_create_cc' ) );
			$form->add( new \IPS\Helpers\Form\Text( 'bcc', array(), FALSE, array( 'autocomplete' => array( 'unique' => TRUE, 'forceLower' => TRUE ) ), array( 'IPS\nexus\Support\Request', '_validateEmail' ), NULL, NULL, 'support_create_bcc' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'message', $defaultContent, TRUE, array( 'app' => 'nexus', 'key' => 'Support', 'autoSaveKey' => "new-req-message", 'defaultIfNoAutoSave' => TRUE ) ) );
			if ( $values = $form->values() )
			{
				$request = new \IPS\nexus\Support\Request;
				$request->started = time();
				$request->title = $data['title'];
				if ( isset( $data['member'] ) )
				{
					$request->member = $data['member'];
				}
				else
				{
					$request->email = $data['email'];
				}
				$request->department = $department;
				if ( isset( $data['purchase'] ) and $data['purchase'] )
				{
					$request->purchase = \IPS\nexus\Purchase::load( $data['purchase'] );
				}
				$request->status = \IPS\nexus\Support\Status::load( $data['status'] );
				$request->severity = \IPS\nexus\Support\Severity::load( $data['severity'] );
				if ( isset( $data['staff'] ) and $data['staff'] )
				{
					$request->staff = \IPS\Member::load( $data['staff'] );
				}
				if ( isset( $data['custom_fields'] ) )
				{
					$cfields = array();
					$customFieldObjects = \IPS\nexus\Support\CustomField::roots();
					foreach ( $data['custom_fields'] as $k => $v )
					{
						if ( mb_substr( $k, 0, 13 ) === 'nexus_cfield_' )
						{
							$k = mb_substr( $k, 13 );
							$class = $customFieldObjects[ $k ]->buildHelper();
							$cfields[ $k ] = $class::stringValue( $v );
						}
					}

					$request->cfields = $cfields;
				}
				
				$notify = $request->notify;
				foreach ( $values['cc'] as $cc )
				{
					foreach ( $notify as $n )
					{
						if ( $n['value'] === $cc )
						{
							continue 2;
						}
					}
					$notify[] = array( 'type' => 'e', 'value' => $cc );
				}
				foreach ( $values['bcc'] as $cc )
				{
					foreach ( $notify as $k => $n )
					{
						if ( $n['value'] === $cc )
						{
							if ( $n['bcc'] )
							{
								unset( $notify[ $k ]['bcc'] );
							}
							
							continue 2;
						}
					}
					$notify[] = array( 'type' => 'e', 'value' => $cc, 'bcc' => 1 );
				}
				$request->notify = $notify;
				$request->last_reply = time();
				$request->last_new_reply = time();
				$request->last_staff_reply = time();
				$request->last_reply_by = \IPS\Member::loggedIn()->member_id;
				$request->replies = 1;
				$request->save();
				
				$reply = new \IPS\nexus\Support\Reply;
				$reply->request = $request->id;
				$reply->member = \IPS\Member::loggedIn()->member_id;
				$reply->type = $reply::REPLY_STAFF;
				$reply->post = $values['message'];
				$reply->date = time();
				$reply->cc = implode( ',', $values['cc'] );
				$reply->ip_address = \IPS\Request::i()->ipAddress();
				$reply->bcc = implode( ',', $values['bcc'] );
				$reply->save();

				\IPS\File::claimAttachments( 'new-req-message', $request->id, $reply->id );

				$request->processAfterCreate( $reply, $data );
				if ( $values['support_email_customer'] )
				{
					$reply->sendCustomerNotifications( $values['to'], $values['cc'], $values['bcc'] );
				}
				$reply->sendNotifications();
				
				$url = NULL;
				if ( isset( $data['ref'] ) and isset( $data['transaction'] ) )
				{
					try
					{
						$transaction = \IPS\nexus\Transaction::load( $data['transaction'] );
						$extra = $transaction->extra;
						$extra['sr'] = $transaction->id;
						$transaction->extra = $extra;
						$transaction->save();
						
						switch ( $data['ref'] )
						{
							case 'v':
								\IPS\Output::i()->redirect( $transaction->acpUrl() );
								break;
								
							case 'i':
								\IPS\Output::i()->redirect( $transaction->invoice->acpUrl() );
								break;
							
							case 'c':
								\IPS\Output::i()->redirect( $transaction->member->acpUrl() );
								break;
							
							case 't':
								\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=transactions') );
								break;
						}
					}
					catch ( \OutOfRangeException $e ) { }
				}
				if ( !$url )
				{
					$url = $request->canView() ? $request->acpUrl() : \IPS\Http\Url::internal('app=nexus&module=support&controller=requests');
				}
				\IPS\Output::i()->redirect( $url, 'request_created' );
			}
			
			return $form;
		};
				
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('create_support_request');
		\IPS\Output::i()->output = (string) new \IPS\Helpers\Wizard( $steps, $url, TRUE, $initialData );
	}
	
	/**
	 * View History
	 *
	 * @return	void
	 */
	protected function history()
	{
		/* Jump to the right date */
		if ( isset( \IPS\Request::i()->date_jump ) )
		{
			try
			{
				$timezone = \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL;
			}
			catch ( \Exception $e )
			{
				$timezone = NULL;
			}
			
			$date = new \IPS\DateTime( \IPS\Helpers\Form\Date::_convertDateFormat( \IPS\Request::i()->date_jump ), $timezone );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests&do=history&date=' . $date->getTimestamp() ) );
		}
		
		/* Get our dates */
		$date = isset( \IPS\Request::i()->date ) ? \IPS\DateTime::ts( \IPS\Request::i()->date ) : \IPS\DateTime::ts( time() )->setTime( 0, 0, 0 );
		$tomorrow = clone $date;
		$tomorrow = $tomorrow->add( new \DateInterval('P1D') );
		$yesterday = clone $date;
		$yesterday = $yesterday->sub( new \DateInterval('P1D') );
		
		/* What page are we on? */	
		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		$perPage = 25;
		
		/* Get our replies */
		$replyWhere = array(
			array( 'reply_member=? AND reply_date>? AND reply_date<?', \IPS\Member::loggedIn()->member_id, $date->getTimestamp(), $tomorrow->getTimestamp() ),
			array( \IPS\Db::i()->in( 'r_department', array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( \IPS\Member::loggedIn() ) ) ) ) )
		);
		$replies = new \IPS\Patterns\ActiveRecordIterator(
			\IPS\Db::i()->select( 'nexus_support_replies.*, nexus_support_ratings.*', 'nexus_support_replies',
				$replyWhere,
				'reply_id DESC',
				array( ( $page - 1 ) * $perPage, $perPage ),
				NULL,
				NULL,
				\IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS
			)
			->join( 'nexus_support_requests', 'r_id=reply_request' )
			->join( 'nexus_support_departments', 'dpt_id=r_department' )
			->join( 'nexus_support_ratings', 'reply_id=rating_reply' )
			, 'IPS\nexus\Support\Reply'
		);
		
		/* Work out pagination */
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', $replyWhere )
			->join( 'nexus_support_requests', 'r_id=reply_request' )
			->join( 'nexus_support_departments', 'dpt_id=r_department' )
			->join( 'nexus_support_ratings', 'reply_id=rating_reply' )
			->first();
		$numberOfPages = ceil( $count / $perPage );
		$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( \IPS\Http\Url::internal( 'app=nexus&module=support&controller=requests&do=history&date=' . $date->getTimestamp() ), $numberOfPages, $page, $perPage );
		
		/* Any tracked? */
		$supportRequestsInView = array();
		$first = NULL;
		$last = NULL;
		foreach ( $replies as $reply )
		{
			if ( $first === NULL )
			{
				$first = $reply;
			}
			$supportRequestsInView[] = $reply->request;
			$last = $reply;
		}
		$tracked = iterator_to_array( \IPS\Db::i()->select( array( 'request_id', 'notify' ), 'nexus_support_tracker', array( array( 'member_id=?', \IPS\Member::loggedIn()->member_id ), array( \IPS\Db::i()->in( 'request_id', $supportRequestsInView ) ) ) )->setKeyField( 'request_id' )->setValueField( 'notify' ) );
		
		/* Add in actions */
		if ( $last )
		{
			$logWhere = array( array( 'rlog_member=? AND rlog_date>=?', \IPS\Member::loggedIn()->member_id, $last->date ) );
			try
			{
				$firstOnNextPage = \IPS\Db::i()->select( 'reply_date', 'nexus_support_replies', array_merge( $replyWhere, array( array( 'reply_date>?', $first->date ) ) ), 'reply_date ASC', 1 )->join( 'nexus_support_requests', 'r_id=reply_request' )->join( 'nexus_support_departments', 'dpt_id=r_department' )->first();
				$logWhere[] = array( 'rlog_date<?', $firstOnNextPage );
			}
			catch ( \UnderflowException $e )
			{
				$logWhere[] = array( 'rlog_date<?', $tomorrow->getTimestamp() );
			}
		}
		else
		{
			$logWhere = array( array( 'rlog_member=? AND rlog_date>? AND rlog_date<?', \IPS\Member::loggedIn()->member_id, $date->getTimestamp(), $tomorrow->getTimestamp() ) );
		}
		$iterator = new \IPS\Patterns\UnionIterator( 'desc' );
		$iterator->attachIterator( $replies, 'date' );
		$iterator->attachIterator( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_request_log', $logWhere, 'rlog_date DESC' ), 'IPS\nexus\Support\Log' ), 'date' );	
		
		/* Make a 30 day chart */
		$data = array();
		$workingDate = \IPS\DateTime::ts( time() - ( 86400 * 29 ) )->setTime( 0, 0, 0 );
		$thirtyDaysAgo = $workingDate->getTimestamp();
		for ( $i = 0; $i < 30; $i++ )
		{
			$data[ $workingDate->format( 'Y-n-j' ) ] = 0;
			$workingDate->add( new \DateInterval('P1D') );
		}
		$chart = new \IPS\Helpers\Chart;
		$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('date'), 'date' );
		$chart->addHeader( \IPS\Member::loggedIn()->language()->addToStack('staff_reply_count'), 'number' );
		foreach ( \IPS\Db::i()->select( "COUNT(*) AS count, DATE_FORMAT( FROM_UNIXTIME( reply_date ), '%Y-%c-%e' ) AS time", 'nexus_support_replies', array( 'reply_member=? AND reply_type=? AND reply_date>?', \IPS\Member::loggedIn()->member_id, \IPS\nexus\Support\Reply::REPLY_STAFF, $thirtyDaysAgo ), NULL, NULL, 'time' ) as $row )
		{
			$data[ $row['time'] ] = $row['count'];
		}
		foreach ( $data as $dateString => $count )
		{
			$datetime = new \IPS\DateTime;
			$exploded = explode( '-', $dateString );
			$datetime->setDate( (float) $exploded[0], $exploded[1], $exploded[2] );
			$datetime->setTime( 0, 0, 0 );
			$chart->addRow( array( $datetime, $count ) );
		}		
		$chart = $chart->render( 'SteppedAreaChart', array(
			'backgroundColor'	=> '#fff',
			'areaOpacity'		=> 0.4,
			'chartArea'			=> array( 'width' => '97%', 'height' => '81%' ),
			'colors'			=> array( '#10967e' ),
			'fontSize'			=> 10,
			'height'			=> 150,
			'lineWidth'			=> 1,
			'vAxis'				=> array( 'gridlines' => array( 'color' => '#f0f0f0', 'count' => 4 ), 'maxValue' => max( $data ), 'textPosition' => 'none' ),
			'hAxis'				=> array( 'gridlines' => array( 'color' => '#f5f5f5' ) ),
			'legend'			=> array( 'position' => 'none' )
		) );
		
		/* Stats */
		$sixtyDaysAgo = ( $thirtyDaysAgo - ( 86400 * 30 ) );
		$resolvedStatuses = iterator_to_array( \IPS\Db::i()->select( 'status_id', 'nexus_support_statuses', 'status_is_locked=1' ) );
		$stats = array();
				
		$stats['totalRepliesThis'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', array( 'reply_member=? AND reply_type=? AND reply_date>?', \IPS\Member::loggedIn()->member_id, \IPS\nexus\Support\Reply::REPLY_STAFF, $thirtyDaysAgo ) )->first();
		$stats['totalRepliesPrev'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_replies', array( 'reply_member=? AND reply_type=? AND reply_date>? AND reply_date<?', \IPS\Member::loggedIn()->member_id, \IPS\nexus\Support\Reply::REPLY_STAFF, $sixtyDaysAgo, $thirtyDaysAgo ) )->first();
		$stats['customersHelpedThis'] = \IPS\Db::i()->select( 'COUNT( DISTINCT IF( nexus_support_requests.r_member, nexus_support_requests.r_member, nexus_support_requests.r_email ) )', 'nexus_support_replies', array( 'reply_member=? AND reply_type=? AND reply_date>?', \IPS\Member::loggedIn()->member_id, \IPS\nexus\Support\Reply::REPLY_STAFF, $thirtyDaysAgo ) )->join( 'nexus_support_requests', 'reply_request=r_id' )->first();
		$stats['customersHelpedPrev'] = \IPS\Db::i()->select( 'COUNT( DISTINCT IF( nexus_support_requests.r_member, nexus_support_requests.r_member, nexus_support_requests.r_email ) )', 'nexus_support_replies', array( 'reply_member=? AND reply_type=? AND reply_date>? AND reply_date<?', \IPS\Member::loggedIn()->member_id, \IPS\nexus\Support\Reply::REPLY_STAFF, $sixtyDaysAgo, $thirtyDaysAgo ) )->join( 'nexus_support_requests', 'reply_request=r_id' )->first();
		$stats['issuesResolvedThis'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'reply_member=? AND r_last_staff_reply>?', \IPS\Member::loggedIn()->member_id, $thirtyDaysAgo ) )->join( 'nexus_support_replies', 'reply_date=r_last_staff_reply' )->first();
		$stats['issuesResolvedPrev'] = \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'reply_member=? AND r_last_staff_reply>? AND r_last_staff_reply<?', \IPS\Member::loggedIn()->member_id, $sixtyDaysAgo, $thirtyDaysAgo ) )->join( 'nexus_support_replies', 'reply_date=r_last_staff_reply' )->first();
		$stats['averageRatingThis'] = round( \IPS\Db::i()->select( 'AVG(rating_rating)', 'nexus_support_ratings', array( 'rating_staff=? AND rating_date>?', \IPS\Member::loggedIn()->member_id, $thirtyDaysAgo ) )->first(), 1 );
		$stats['averageRatingPrev'] = round( \IPS\Db::i()->select( 'AVG(rating_rating)', 'nexus_support_ratings', array( 'rating_staff=? AND rating_date>? AND rating_date<?', \IPS\Member::loggedIn()->member_id, $sixtyDaysAgo, $thirtyDaysAgo ) )->first(), 1 );

		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('my_history');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('support')->myHistory( $date, $tomorrow, $yesterday, $iterator, $pagination, $tracked, $chart, $stats );

	}
}
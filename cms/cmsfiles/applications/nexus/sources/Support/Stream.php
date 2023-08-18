<?php
/**
 * @brief		Support Requests Stream
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		11 Sep 2016
 */

namespace IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Requests Stream
 */
class _Stream extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_streams';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'stream_';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * Get streams for a member
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	array
	 */
	public static function myStreams( \IPS\Member $member )
	{
		$return = array();
		
		foreach ( array( 'open', 'assigned', 'tracked' ) as $k )
		{
			$return[] = static::load( $k );
		}
		
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'nexus_support_streams', array( 'stream_owner=?', $member->member_id ) ), 'IPS\nexus\Support\Stream' ) as $stream )
		{
			$return[] = $stream;
		}
		
		return $return;
	}
		
	/**
	 * Get my stream
	 *
	 * @return	static
	 */
	public static function myStream()
	{
		$streamId = isset( \IPS\Request::i()->cookie['support_stream'] ) ? \IPS\Request::i()->cookie['support_stream'] : 'open';
		try
		{
			$stream = \IPS\nexus\Support\Stream::load( $streamId );
		}
		catch ( \OutOfRangeException $e )
		{
			$stream = \IPS\nexus\Support\Stream::load( 'open' );
		}
		return $stream;
	}
	
	/**
	 * Load
	 *
	 * @param	mixed		$id			The key or ID number
	 * @param	string|NULL	$idField	The field to search `$id` in
	 * @param	mixed		$extraWhereClause	Extra where clause or NULL
	 * @return	\IPS\nexus\Support\Stream
	 */
	public static function load( $id, $idField=NULL, $extraWhereClause=NULL )
	{
		switch ( $id )
		{
			case 'open':
				$return = new static;
				$return->id = 'open';
				$return->title = \IPS\Member::loggedIn()->language()->addToStack('support_stream_open');
				$return->departments = NULL;
				$return->statuses = iterator_to_array( \IPS\Db::i()->select( 'status_id', 'nexus_support_statuses', array( 'status_open=1' ) ) );
				$return->severities = NULL;
				$return->staff = NULL;
				$return->from_email = NULL;
				$return->search_term = NULL;
				$return->started = NULL;
				$return->last_new_reply = NULL;
				$return->last_reply = NULL;
				$return->last_staff_reply = NULL;
				$return->tracked = FALSE;
				return $return;
				
			case 'assigned':
				$return = new static;
				$return->id = 'assigned';
				$return->title = \IPS\Member::loggedIn()->language()->addToStack('support_stream_assigned');
				$return->departments = NULL;
				$return->statuses = iterator_to_array( \IPS\Db::i()->select( 'status_id', 'nexus_support_statuses', array( 'status_open=1' ) ) );
				$return->severities = NULL;
				$return->staff = 'me';
				$return->from_email = NULL;
				$return->search_term = NULL;
				$return->started = NULL;
				$return->last_new_reply = NULL;
				$return->last_reply = NULL;
				$return->last_staff_reply = NULL;
				$return->tracked = FALSE;
				return $return;
				
			case 'tracked':
				$return = new static;
				$return->id = 'tracked';
				$return->title = \IPS\Member::loggedIn()->language()->addToStack('support_stream_tracked');
				$return->departments = NULL;
				$return->statuses = NULL;
				$return->severities = NULL;
				$return->staff = NULL;
				$return->from_email = NULL;
				$return->search_term = NULL;
				$return->started = NULL;
				$return->last_new_reply = NULL;
				$return->last_reply = NULL;
				$return->last_staff_reply = NULL;
				$return->tracked = TRUE;
				return $return;			

			default:
				return parent::load( $id, $idField, $extraWhereClause );
		}
	}
	
	/**
	 * Customer Stream
	 *
	 * @param	\IPS\nexus\Customer	$customer	The customer
	 * @return	static
	 */
	public static function customer( \IPS\nexus\Customer $customer )
	{
		$return = new static;
		$return->title = sprintf( \IPS\Member::loggedIn()->language()->get( 'members_support_requests' ), $customer->member_id ? $customer->cm_name : $customer->email );
		$return->departments = NULL;
		$return->statuses = NULL;
		$return->severities = NULL;
		$return->staff = NULL;
		$return->from_email = $customer->email;
		$return->search_term = NULL;
		$return->started = NULL;
		$return->last_new_reply = NULL;
		$return->last_reply = NULL;
		$return->last_staff_reply = NULL;
		$return->tracked = FALSE;
		return $return;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array	$values	Values from the form
	 * @return	static
	 */
	public static function createFromForm( $values )
	{
		$return = new static;
		$return->updateFromForm( $values );
		$return->temporary = TRUE;
		$return->owner = \IPS\Member::loggedIn()->member_id;
		$return->title = \IPS\Member::loggedIn()->language()->get('stream_default_title');
		$return->save();
		return $return;			
	}
	
	/**
	 * Update from form
	 *
	 * @param	array	$values	Values from the form
	 * @return	void
	 */
	public function updateFromForm( $values )
	{
		foreach ( array( 'r_started', 'r_last_new_reply', 'r_last_reply', 'r_last_staff_reply' ) as $k )
		{
			if ( \is_array( $values[ $k ] ) and isset( $values[ $k ][2] ) and $values[ $k ][2] != 'h' )
			{
				$values[ $k ][1] = \IPS\Helpers\Form\Interval::convertValue( $values[ $k ][1], $values[ $k ][2], \IPS\Helpers\Form\Interval::HOURS );
				unset( $values[ $k ][2] );
			}
		}
		
		$this->departments = $values['r_department'] ?: NULL;
		$this->statuses = $values['r_status'] ?: NULL;
		$this->severities = $values['r_severity'] ?: NULL;
		$this->staff = $values['r_staff'] ?: NULL;
		$this->from_email = $values['r_email'] ?: NULL;
		$this->search_term = $values['r_search'] ?: NULL;
		$this->started = ( \is_array( $values['r_started'] ) AND $values['r_started'][1] ) ? json_encode($values['r_started']): NULL;
		$this->last_new_reply = ( \is_array( $values['r_last_new_reply'] ) AND $values['r_last_new_reply'][1] ) ? json_encode($values['r_last_new_reply']): NULL;
		$this->last_reply = ( \is_array( $values['r_last_new_reply'] ) AND $values['r_last_reply'][1] ) ? json_encode($values['r_last_reply']): NULL;
		$this->last_staff_reply = ( \is_array( $values['r_last_staff_reply'] ) AND $values['r_last_staff_reply'][1] ) ? json_encode($values['r_last_staff_reply']): NULL;
		$this->tracked = (bool) $values['r_tracked'];
	}
	
	/**
	 * Convert arrays
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value from the datastore
	 */
	public function __get( $key )
	{
		$return = parent::__get( $key );
		
		if ( $return !== NULL and \in_array( $key, array( 'departments', 'statuses', 'severities', 'staff' ) ) and $return !== 'me' )
		{
			$return = explode( ',', $return );
		}
		
		return $return;
	}
	
	/**
	 * Set value in data store
	 *
	 * @see		\IPS\Patterns\ActiveRecord::save
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		if ( \is_array( $value ) and \in_array( $key, array( 'departments', 'statuses', 'severities', 'staff' ) ) )
		{
			$value = implode( ',', $value );
		}
		
		return parent::__set( $key, $value );
	}
	
	/**
	 * Get form
	 *
	 * @param	\IPS\nexus\Support\Stream	$selectedStream	The stream to show as selected
	 * @param	\IPS\Http\Url				$url			The URL to submit to
	 * @return	\IPS\Helpers\Form
	 */
	public function form( ?Stream $selectedStream, \IPS\Http\Url $url )
	{
		/* Init */
		$form = new \IPS\Helpers\Form( 'form', 'show_results', $url );
		$form->class = 'ipsForm_vertical';
		
		/* Streams */
		$form->addTab('streams');
		$form->add( new \IPS\Helpers\Form\Custom( 'stream', $selectedStream ? $selectedStream->id : 'custom', FALSE, array(
			'getHtml'	=> function( $input ) {				
				return \IPS\Theme::i()->getTemplate('support')->filterFormStream( $input, \IPS\nexus\Support\Stream::myStreams( \IPS\Member::loggedIn() ) );
			}
		) ) );
		
		/* Departments */
		$form->addTab('first');
		$departments = array();
		foreach ( \IPS\nexus\Support\Department::departmentsWithPermission() as $department )
		{
			$departments[ $department->_id ] = $department->_title;
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'r_department', $this->departments ?: array_keys( $departments ), FALSE, array( 'options' => $departments ) ) );
		
		/* Statuses */
		$statuses = array();
		$openStatuses = array();
		foreach ( \IPS\nexus\Support\Status::roots() as $status )
		{
			$statuses[ $status->_id ] = $status->_title;
			if ( $status->open )
			{
				$openStatuses[] = $status->_id;
			}
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'r_status', $this->statuses ?: $openStatuses, FALSE, array( 'options' => $statuses ) ) );
		
		/* Severities */
		$form->addTab('second');
		$severities = array();
		foreach ( \IPS\nexus\Support\Severity::roots() as $severity )
		{
			$severities[ $severity->_id ] = $severity->_title;
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'r_severity', $this->severities ?: array_keys( $severities ), FALSE, array( 'options' => $severities ) ) );
		
		/* Assigned To */
		$staff = \IPS\nexus\Support\Request::staff();
		$staff[0] = 'unassigned';
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'r_staff', $this->staff ?: array_keys( $staff ), FALSE, array( 'options' => $staff ) ) );
		
		/* Tracked */
		$form->add( new \IPS\Helpers\Form\YesNo( 'r_tracked', $this->tracked, FALSE ) );
		
		/* Customer email */
		$form->addTab('third');
		$form->add( new \IPS\Helpers\Form\Email( 'r_email', $this->from_email ) );
		
		/* Search text */
		$form->add( new \IPS\Helpers\Form\Text( 'r_search', $this->search_term ) );

		/* Time frame */
		$timeFrameOptions = array(
			'getHtml'	=> function( $input ) {
				return \IPS\Theme::i()->getTemplate('support')->filterFormTime( $input );
			}
		);

		$form->add( new \IPS\Helpers\Form\Custom( 'r_started', \is_int( $this->started ) ? array( 0 => '<', 1 => $this->started ) : json_decode( $this->started ), FALSE, $timeFrameOptions ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'r_last_new_reply', \is_int( $this->last_new_reply ) ? array( 0 => '<', 1 => $this->last_new_reply ) : json_decode( $this->last_new_reply ), FALSE, $timeFrameOptions ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'r_last_reply', \is_int( $this->last_reply ) ? array( 0 => '<', 1 => $this->last_reply ) : json_decode( $this->last_reply ), FALSE, $timeFrameOptions ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'r_last_staff_reply', \is_int( $this->last_staff_reply ) ? array( 0 => '<', 1 => $this->last_staff_reply ) : json_decode( $this->last_staff_reply ), FALSE, $timeFrameOptions ) );
		
		/* Return */
		return $form;
	}
	
	/**
	 * Get where clause for results
	 *
	 * @param	$member		Staff member, or NULL for no permission restriction
	 * @return	array
	 */
	public function _whereClause( $member )
	{
		/* Init where clause */
		$where = array();

		/* Departments */
		$departments = NULL;
		if ( $this->departments )
		{
			if ( $member )
			{
				$departments = array_intersect( $this->departments, array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( $member ) ) ) );
			}
			else
			{
				$departments = $this->departments;
			}
		}
		elseif ( $member )
		{
			$departments = array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( $member ) ) );
		}
		if ( $departments )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_department', $departments ) );
		}
		
		/* Statuses */
		if ( $this->statuses )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_status', $this->statuses ) );
		}
		
		/* Severities */
		if ( $this->severities )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_severity', $this->severities ) );
		}
		
		/* Staff */
		if ( $this->staff )
		{
			if ( $this->staff === 'me' )
			{
				$where[] = array( 'r_staff=?', $member->member_id );
			}
			else
			{
				$where[] = array( \IPS\Db::i()->in( 'r_staff', $this->staff ) );
			}
		}
				
		/* From */
		if ( $this->from_email )
		{
			$fromMember = \IPS\Member::load( $this->from_email, 'email' );
			if ( $fromMember->member_id )
			{
				$where[] = array( '( r_member=? OR r_email=? )', $fromMember->member_id, $fromMember->email );
			}
			else
			{
				$where[] = array( '( r_email=? )', $this->from_email );
			}
		}
		
		/* Search term */
		if ( $this->search_term )
		{
			$subQuery= \IPS\Content\Search\Mysql\Query::matchClause('reply_post', $this->search_term);
			$where[] = array( 'r_id IN(?)', \IPS\Db::i()->select( 'reply_request', 'nexus_support_replies', $subQuery ) );
		}
		
		/* Tracked? */
		if ( $this->tracked and $member )
		{
			$where[] = array( 'r_id IN(?)', \IPS\Db::i()->select( 'request_id', 'nexus_support_tracker', array( 'member_id=?', $member->member_id ) ) );
		}
		
		/* Time limits */
		foreach ( array( 'started', 'last_new_reply', 'last_reply', 'last_staff_reply' ) as $k )
		{
			if ( $this->$k )
			{
				if ( \is_int( $this->$k ) )
				{
					$operand = $this->$k;
					$operator = '<';
				}
				else
				{
					$limit = json_decode( $this->$k );
					// This looks backwards, but if we say "greater than 5 days" we want to know if the date is less than the resulting timestamp
					$operator = ( $limit[0] == 'gt' ) ? '<' : '>';
					$operand = $limit[1];
				}
				$where[] = array( "r_{$k}{$operator}?", time() - ( 3600 * $operand ) );
			}
		}
		
		/* Return */
		return $where;
	}
	
	/**
	 * Get count
	 *
	 * @param	$member		Staff member, or NULL for no permission restriction
	 * @return	int
	 */
	public function count( $member = NULL )
	{		
		return \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', $this->_whereClause( $member ) )->first();
	}
	
	/**
	 * Get results
	 *
	 * @param	\IPS\Member|NULL	$member		Staff member, or NULL for no permission restriction
	 * @param	string				$order		Order by clause
	 * @param	int					$page		Page number
	 * @param	int					$perPage	Number of results per page
	 * @return	\IPS\Patterns\ActiveRecordIterator
	 */
	public function results( $member = NULL, $order='r_id DESC', $page = 1, $perPage = 25 )
	{
		$query = \IPS\Db::i()->select( '*', 'nexus_support_requests', $this->_whereClause( $member ), $order, array( ( $page - 1 ) * $perPage, $perPage ) );
		
		if ( mb_strpos( $order, 'dpt_position' ) !== FALSE )
		{
			$query->join( 'nexus_support_staff_dpt_order', 'nexus_support_staff_dpt_order.department_id=r_department AND nexus_support_staff_dpt_order.staff_id=' . $member->member_id );
			$query->join( 'nexus_support_departments', 'dpt_id=r_department' );
		}
		if ( mb_strpos( $order, 'sev_position' ) !== FALSE )
		{
			$query->join( 'nexus_support_severities', 'sev_id=r_severity' );
		}
		
		$query->setKeyField('r_id');
		
		$results = new \IPS\Patterns\ActiveRecordIterator( $query, 'IPS\nexus\Support\Request' );		
		return $results;
	}
}

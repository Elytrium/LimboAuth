<?php
/**
 * @brief		Support Requests API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		4 Dec 2015
 */

namespace IPS\nexus\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Support Requests API
 */
class _supportrequests extends \IPS\Content\Api\ItemController
{
	/**
	 * Class
	 */
	protected $class = 'IPS\nexus\Support\Request';
	
	/**
	 * GET /nexus/supportrequests
	 * Get list of support requests
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, only topics the authorized user can view will be included - if the user is a staff member, will use their staff permissions
	 * @apiparam	string	authors			Comma-delimited list of member IDs - if provided, only support requests belonging to those members (or emails specified by email param) are returned
	 * @apiparam	string	email			Comma-delimited list of email addresses - if provided, only support requests created by those emails (or members specified by authors param) are returned
	 * @apiparam	string	departments		Comma-delimited list of department IDs
	 * @apiparam	string	statuses		Comma-delimited list of status IDs
	 * @apiparam	string	severities		Comma-delimited list of severity IDs
	 * @apiparam	string	purchases		Comma-delimited list of purchase IDs - if provided, only support requests associated with one of the provided purchase IDs are returned
	 * @apiparam	string	staff			Comma-delimited list of member IDs - if provided, only support requests assigned to the staff members with one of the provided IDs are returned
	 * @apiparam	int		hidden			If 1, only replies which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortBy			What to sort by. Can be 'date', 'title' or leave unspecified for ID
	 * @apiparam	string	sortDir			Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page			Page number
	 * @apiparam	int		perPage			Number of results per page - defaults to 25
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Support\Request>
	 */
	public function GETindex()
	{
		/* Init */
		$where = array();
		
		/* Authors */
		if ( isset( \IPS\Request::i()->authors ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_member', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->authors ) ) ) ) );
		}
		if ( isset( \IPS\Request::i()->authors ) and isset( \IPS\Request::i()->emails ) )
		{
			$where[] = array( '( ' . \IPS\Db::i()->in( 'r_member', array_filter( explode( ',', \IPS\Request::i()->authors ) ) ) . ' OR ' . \IPS\Db::i()->in( 'r_email', array_filter( explode( ',', \IPS\Request::i()->emails ) ) ) . ' )' );
		}
		elseif ( isset( \IPS\Request::i()->authors ) )
		{
			$where[] = array( '( ' . \IPS\Db::i()->in( 'r_member', array_filter( explode( ',', \IPS\Request::i()->authors ) ) ) . ' OR r_email<>? )', '' );
		}
		elseif ( isset( \IPS\Request::i()->emails ) )
		{
			$where[] = array( '( r_member>0 OR ' . \IPS\Db::i()->findInSet( 'r_email', array_filter( explode( ',', \IPS\Request::i()->emails ) ) ) . ' )' );
		}

		/* Departments */
		if ( isset( \IPS\Request::i()->departments ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_department', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->departments ) ) ) ) );
		}
		
		/* Statuses */
		if ( isset( \IPS\Request::i()->statuses ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_status', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) ) );
		}

		/* Severities */
		if ( isset( \IPS\Request::i()->severities ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_severity', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->statuses ) ) ) ) );
		}

		/* Purchases */
		if ( isset( \IPS\Request::i()->purchases ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_purchase', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->purchases ) ) ) ) );
		}
		
		/* Staff */
		if ( isset( \IPS\Request::i()->staff ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_staff', array_map( 'intval', array_filter( explode( ',', \IPS\Request::i()->staff ) ) ) ) );
		}
		
		/* Are we a staff member? */
		$bypassPerms = FALSE;
		if ( $this->member and $this->member->isAdmin() and \count( \IPS\nexus\Support\Department::departmentsWithPermission( $this->member ) ) )
		{
			$where[] = array( \IPS\Db::i()->in( 'r_department', array_keys( iterator_to_array( \IPS\nexus\Support\Department::departmentsWithPermission( $this->member ) ) ) ) );
			$bypassPerms = TRUE;
		}
		
		/* Return */
		return $this->_list( $where, NULL, $bypassPerms );
	}
		
	/**
	 * GET /nexus/supportrequests/{id}
	 * Get information about and replies to a specific support request
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		hidden		If 1, only posts which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @throws		2X313/1	INVALID_ID	The request does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Support\Request
	 */
	public function GETitem( $id )
	{
		try
		{
			$request = \IPS\nexus\Support\Request::load( $id );
			if ( $this->member and !$request->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			return new \IPS\Api\Response( 200, $request->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X313/1', 404 );
		}
	}
	
	/**
	 * GET /nexus/supportrequests/{id}/replies
	 * Get replies to a specific support request
	 *
	 * @param		int		$id			ID Number
	 * @apiparam	int		hidden		If 1, only replies which are hidden are returned, if 0 only not hidden
	 * @apiparam	string	sortDir		Sort direction. Can be 'asc' or 'desc' - defaults to 'asc'
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @throws		2X313/2	INVALID_ID	The request does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\Api\PaginatedResponse<IPS\nexus\Support\Reply>
	 */
	public function GETitem_replies( $id )
	{
		try
		{
			$request = \IPS\nexus\Support\Request::load( $id );
			if ( $this->member and !$request->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
			
			return $this->_comments( $id, 'IPS\nexus\Support\Reply', array( array( 'reply_type<>?', \IPS\nexus\Support\Reply::REPLY_PENDING ) ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X313/2', 404 );
		}
	}
	
	
	/**
	 * Create or update item
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @param	string				$type	add or edit
	 * @return	\IPS\Content\Item
	 */
	protected function _createOrUpdate( \IPS\Content\Item $item, $type='add' )
	{
		/* Department, status, etc */
		foreach ( array( 'department' => 'IPS\nexus\Support\Department', 'status' => 'IPS\nexus\Support\Status', 'severity' => 'IPS\nexus\Support\Severity', 'purchase' => 'IPS\nexus\Purchase', 'staff' => 'IPS\Member'  ) as $key => $class )
		{
			if ( isset( \IPS\Request::i()->$key ) )
			{
				try
				{
					$object = $class::load( \IPS\Request::i()->$key );
					
					if ( $this->member )
					{
						if ( $key === 'department' and $type == 'edit' )
						{
							continue;
						}
						if ( $key === 'severity' and ( !$this->member->cm_no_sev or !\in_array( $object, \IPS\nexus\Support\Department::load( \IPS\Request::i()->department )->availableSeverities() ) ) )
						{
							continue;
						}
						if ( $key === 'purchase' and ( $type == 'edit' or !$object->canView( $this->member ) ) )
						{
							continue;
						}
						if ( $key === 'staff' )
						{
							continue;
						}
						if ( $key === 'status' and ( $type == 'add' or !\IPS\Member::loggedIn()->language()->checkKeyExists("nexus_status_{$object->id}_set") ) )
						{
							continue;
						}
					}
					
					$item->$key = $object;
				}
				catch ( \OutOfRangeException $e ) {}
			}
		}
		if ( ( $type == 'add' or !$this->member ) and !isset( \IPS\Request::i()->purchase ) and isset( \IPS\Request::i()->lkey ) )
		{
			try
			{
				$licenseKey = \IPS\nexus\Purchase\LicenseKey::load( \IPS\Request::i()->lkey );
				if ( !$this->member or $licenseKey->purchase->canView( $this->member ) )
				{				
					$item->purchase = $licenseKey->purchase;
				}
			}
			catch ( \OutOfRangeException $e ) {}
		}
		
		/* Defaults */
		if ( !$item->id )
		{
			if ( !$item->_data['status'] )
			{
				$item->status = \IPS\nexus\Support\Status::load( TRUE, 'status_default_member' );
			}
			if ( !$item->_data['severity'] )
			{
				$item->severity = \IPS\nexus\Support\Severity::load( TRUE, 'sev_default' );
			}
		}
		
		/* Cannot edit title */
		if ( $this->member and $type == 'edit' )
		{
			unset( \IPS\Request::i()->title );
		}
				
		/* Pass up */
		return parent::_createOrUpdate( $item );
	}
		
	/**
	 * POST /nexus/supportrequests
	 * Create a support request
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, pay-per-incident departments cannot be used. Any parameters the user doesn't have permission to use are ignored (for example, severities they cannot set).
	 * @reqapiparam	string		title				The support request title
	 * @reqapiparam	int			department			Department ID number
	 * @reqapiparam	int			account				The ID number of the member creating the support request - not required if email is provided. For requests using an OAuth Access Token for a particular member, this is ignored and that member will always be the author.
	 * @reqapiparam	string		email				The email address creating the support request - not required if account is provided. For requests using an OAuth Access Token for a particular member, this is ignored and that member will always be the author.
	 * @reqapiparam	string		message				The content as HTML (e.g. "<p>This is a support request.</p>"). Will be sanatized for requests using an OAuth Access Token for a particular member; will be saved unaltered for requests made using an API Key or the Client Credentials Grant Type. 
	 * @apiparam	int			status				Status ID number. If not provided, will use the default. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	int			severity			Severity ID number. If not provided, will use the default.
	 * @apiparam	int			purchase			Associated purchase ID number. 
	 * @apiparam	string		lkey				License key of associated purchase ID number. (Will be ignored if "purchase" is provided)
	 * @apiparam	int			staff				Assigned staff ID number. Ignored for requests using an OAuth Access Token for a particular member
	 * @apiparam	datetime	date				The date/time that should be used for the support request date. If not provided, will use the current date/time. Ignored for requests using an OAuth Access Token for a particular member
	 * @throws		1X313/3		NO_DEPARTMENT		The department does not exist
	 * @throws		1X313/6		NO_PERMISSION		The request is for an authorizes member and the department does not accept submissions or is pay-per-incident
	 * @throws		1X313/4		NO_AUTHOR			The author ID does not exist
	 * @throws		1X313/5		NO_TITLE			No title was supplied
	 * @throws		1X313/4		NO_POST				No post was supplied
	 * @return		\IPS\nexus\Support\Request
	 */
	public function POSTindex()
	{
		/* Get department */
		try
		{
			$department = \IPS\nexus\Support\Department::load( \IPS\Request::i()->department );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'NO_DEPARTMENT', '1X313/3', 400 );
		}
		if ( $this->member and ( !$department->open or $department->ppiCost() ) )
		{
			throw new \IPS\Api\Exception( 'NO_PERMISSION', '1X313/6', 403 );
		}
		
		/* Get author */
		if ( $this->member )
		{
			$author = $this->member;
		}
		else
		{
			if ( \IPS\Request::i()->account )
			{
				$author = \IPS\Member::load( \IPS\Request::i()->account );
				if ( !$author->member_id )
				{
					throw new \IPS\Api\Exception( 'NO_AUTHOR', '1X313/4', 400 );
				}
			}
			else
			{
				$author = new \IPS\Member;
			}
		}
		
		/* Check we have a title and a post */
		if ( !\IPS\Request::i()->title )
		{
			throw new \IPS\Api\Exception( 'NO_TITLE', '1X313/5', 400 );
		}
		if ( !\IPS\Request::i()->message )
		{
			throw new \IPS\Api\Exception( 'NO_POST', '1X313/4', 400 );
		}
		
		/* Create */
		$item = $this->_create( NULL, $author, 'message' );
				
		/* Email */
		if ( !$this->member and isset( \IPS\Request::i()->email ) )
		{
			$item->email = \IPS\Request::i()->email;
			$item->save();
		}  
		
		/* Do it */
		return new \IPS\Api\Response( 201, $item->apiOutput( $this->member ) );
	}
	
	/**
	 * POST /nexus/supportrequests/{id}
	 * Edit a support request
	 *
	 * @note		For requests using an OAuth Access Token for a particular member, any parameters the user doesn't have permission to use are ignored (for example, severities they cannot set).
	 * @apiparam	string		title				The support request title. Ignored for requests using an OAuth Access Token for a particular member (users cannot edit request titles after they're created)
	 * @apiparam	int			department			Department ID number. Ignored for requests using an OAuth Access Token for a particular member (users cannot move requests after they're created)
	 * @apiparam	int			status				Status ID number. If not provided, will use the default.
	 * @apiparam	int			severity			Severity ID number. If not provided, will use the default.
	 * @apiparam	int			purchase			Associated purchase ID number. Ignored for requests using an OAuth Access Token for a particular member (users cannot change associated purchase of request after its created)
	 * @apiparam	string		lkey				License key of associated purchase ID number. (Will be ignored if "purchase" is provided). Ignored for requests using an OAuth Access Token for a particular member (users cannot change associated purchase of request after its created)
	 * @apiparam	int			staff				Assigned staff ID number. Ignored for requests using an OAuth Access Token for a particular member
	 * @param		int		$id			ID Number
	 * @throws		2X313/7		INVALID_ID	The topic ID does not exist or the authorized user does not have permission to view it
	 * @return		\IPS\nexus\Support\Request
	 */
	public function POSTitem( $id )
	{
		try
		{
			$request = \IPS\nexus\Support\Request::load( $id );
			if ( $this->member and !$request->canView( $this->member ) )
			{
				throw new \OutOfRangeException;
			}
						
			/* Process */
			$this->_createOrUpdate( $request, 'edit' );
			
			/* Save and return */
			$request->save();
			return new \IPS\Api\Response( 200, $request->apiOutput( $this->member ) );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X313/7', 404 );
		}
	}
	
	/**
	 * DELETE /nexus/supportrequests/{id}
	 * Delete a support request
	 *
	 * @apiclientonly
	 * @param		int		$id			ID Number
	 * @throws		1F294/5	INVALID_ID	The topic ID does not exist
	 * @return		void
	 */
	public function DELETEitem( $id )
	{
		try
		{
			\IPS\nexus\Support\Request::load( $id )->delete();
			
			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '2X313/8', 404 );
		}
	}
}
<?php
/**
 * @brief		Support Reply Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		9 Apr 2014
 */

namespace IPS\nexus\Support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Reply Model
 */
class _Reply extends \IPS\Content\Comment implements \IPS\Content\Hideable
{
	const REPLY_MEMBER		= 'm';
	const REPLY_ALTCONTACT	= 'a';
	const REPLY_STAFF		= 's';
	const REPLY_HIDDEN		= 'h';
	const REPLY_EMAIL		= 'e';
	const REPLY_PENDING		= 'p';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	Application
	 */
	public static $application = 'nexus';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\nexus\Support\Request';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_support_replies';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'reply_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'			=> 'request',
		'author'		=> 'member',
		'content'		=> 'post',
		'date'			=> 'date',
		'ip_address'	=> 'ip_address',
		'hidden'		=> 'hidden',
        'first'         => 'is_first'
	);
	
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'support', 'nexus' ), 'replyContainer' );
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'life-ring';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'support_request';
	
	/**
	 * @brief	Rating data
	 */
	public $ratingData;
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		if ( isset( $data['nexus_support_ratings'] ) and $data['nexus_support_ratings']['rating_rating'] )
		{
			$obj->ratingData = $data['nexus_support_ratings'];
		}
		return $obj;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->type = static::REPLY_MEMBER;
	}
	
	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item				The content item just created
	 * @param	string					$comment			The comment
	 * @param	bool					$first				Is the first comment?
	 * @param	string					$guestName			If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @param	string|NULL				$ipAddress			The IP address or NULL to detect automatically
	 * @param	int|NULL				$hiddenStatus		NULL to set automatically or override: 0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
	 * @param	int|NULL				$anonymous			NULL for no value, 0 or 1 for a value (0=no, 1=yes)
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL )
	{
		$obj = parent::create( $item, $comment, $first, $guestName, $incrementPostCount, $member, $time, $ipAddress, $hiddenStatus );
		
		if ( $obj->type === static::REPLY_MEMBER and $obj->author()->member_id !== $obj->item()->author()->member_id )
		{
			$obj->type = static::REPLY_ALTCONTACT;
			$obj->save();
			
			$notify = $obj->item()->notify;
			$in = FALSE;
			foreach ( $notify as $n ) 
			{
				if ( $n['type'] === 'm' and $n['value'] == $obj->author()->member_id )
				{
					$in = TRUE;
				}
			}
			if ( !$in )
			{
				$notify[] = array( 'type' => 'm', 'value' => $obj->author()->member_id );
				$obj->item()->notify = $notify;
				$obj->item()->save();
			}
		}
						
		return $obj;
	}
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
	}
	
	/**
	 * Joins (when loading comments)
	 *
	 * @param	\IPS\Content\Item	$item			The item
	 * @return	array
	 */
	public static function joins( \IPS\Content\Item $item )
	{
		$return = parent::joins( $item );
		if ( \IPS\Settings::i()->nexus_support_satisfaction )
		{
			$return['nexus_support_ratings'] = array(
				'select'	=> 'nexus_support_ratings.*',
				'from'		=> 'nexus_support_ratings',
				'where'		=> 'rating_reply=reply_id'
			);
		}
		return $return;
	}
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Can split this comment off?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canSplit( $member=NULL )
	{
		return FALSE;
	}
		
	/**
	 * Get author
	 *
	 * @return	\IPS\nexus\Customer
	 */
	public function author()
	{
		if ( $this->member )
		{
			try
			{
				return \IPS\nexus\Customer::load( $this->member );
			}
			catch ( \OutOfRangeException $e ) { }
		}
		return new \IPS\nexus\Customer;
	}
	
	/**
	 * Can see moderation tools
	 *
	 * @note	This is used generally to control if the user has permission to see multi-mod tools. Individual content items may have specific permissions
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function canSeeMultiModTools( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		// If we decide to support multimod in future, we need to make sure it either doesn't show, or shows correctly when viewing a staff member's latest replies in the "Performance" section
		return FALSE;
	}
	
	/**
	 * Get comments based on some arbitrary parameters
	 *
	 * @param	array		$where					Where clause
	 * @param	string		$order					MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit					Limit clause
	 * @param	string|NULL	$permissionKey			A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index, or NULL to ignore permissions
	 * @param	mixed		$includeHiddenComments	Include hidden comments? NULL to detect if currently logged in member has permission, -1 to return public content only, TRUE to return unapproved content and FALSE to only return unapproved content the viewing member submitted
	 * @param	int			$queryFlags				Select bitwise flags
	 * @param	\IPS\Member	$member					The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer			If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments			If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews			If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly				If true will return the count
	 * @param	array|null	$joins					Additional arbitrary joins for the query
	 * @return	array|NULL|\IPS\Content\Comment		If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenComments=\IPS\Content\Hideable::FILTER_AUTOMATIC, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		/* Get customer object */
		if ( !$member )
		{
			$member = \IPS\nexus\Customer::loggedIn();
		}
		elseif ( !( $member instanceof \IPS\nexus\Customer ) )
		{
			$member = \IPS\nexus\Customer::load( $member->member_id );
		}
		$extraClause = array( 'r_member=?', $member->member_id );
		
		/* Work out the clause for parent alternative contacts */
		$alternativeContactWhere = array();
		foreach ( $member->parentContacts() as $contact )
		{
			if ( $contact->support )
			{
				$alternativeContactWhere[] = '( r_member=' . $contact->main_id->member_id . ' )';
			}
			else
			{
				$alternativeContactWhere[] = '( r_member=' . $contact->main_id->member_id . ' AND ' . \IPS\Db::i()->in( 'r_purchase', $contact->purchaseIds() ) . ' )';
			}
		}
		if ( \count( $alternativeContactWhere ) )
		{
			$extraClause[0] = '( ' . $extraClause[0] . ' OR ( ' . implode( ' OR ', $alternativeContactWhere ) . ' ) )';
		}		
		
		/* Work out the clause for admins */
		if ( ( !\IPS\Dispatcher::hasInstance() or \IPS\Dispatcher::i()->controllerLocation === 'admin' ) and $member->isAdmin() )
		{
			$extraClause[0] = '( ' . $extraClause[0] . " OR dpt_staff='*' OR " . \IPS\Db::i()->findInSet( 'dpt_staff', Department::staffDepartmentPerms( $member ) ) . ' )';
			$joins[] = array(
				'from'		=> 'nexus_support_departments',
				'where'		=> 'dpt_id=r_department'
			);
		}
		
		/* Do it */
		$where[] = $extraClause;
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenComments, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins );
	}
	
	/**
	 * Send if it's pending
	 *
	 * @return	void
	 */
	public function sendPending()
	{
		if ( $this->type === static::REPLY_PENDING )
		{
			$this->type = static::REPLY_STAFF;
			$this->hidden = 0;
			$this->save();
			
			$defaultRecipients = $this->item()->getDefaultRecipients();
			$this->sendCustomerNotifications( $defaultRecipients['to'], $defaultRecipients['cc'], $defaultRecipients['bcc'] );

			$this->sendNotifications();
		}
	}
	
	/**
	 * Send staff notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		$staffIds = array_keys( Request::staff() );
		$type = array( 'type=?', 'r' );
		if ( $assignedTo = $this->item()->staff )
		{
			$type[0] .= ' OR ( type=? AND staff_id=? )';
			$type[] = 'a';
			$type[] = $assignedTo->member_id;
		}
		$sentTo = array( $this->member );
		
		$staffToSendTo = array();
		foreach ( \IPS\Db::i()->select( 'staff_id', 'nexus_support_notify', array( $type, array( "( departments='*' OR " . \IPS\Db::i()->findInSet( 'departments', array( $this->item()->department->id ) ) . ' )' ) ) ) as $staffId )
		{
			$staffToSendTo[ $staffId ] = $staffId;
		}
		foreach ( \IPS\Db::i()->select( 'member_id', 'nexus_support_tracker', array( 'request_id=? AND notify=1', $this->item()->id ) ) as $staffId )
		{
			$staffToSendTo[ $staffId ] = $staffId;
		}
				
		foreach ( $staffToSendTo as $staffId )
		{
			if ( \in_array( $staffId, $staffIds ) )
			{
				/* The department may only be available to specific members OR groups - we need to load an \IPS\Member object here so that we can check both and send the notification as appropriate. */
				$staff				= \IPS\Member::load( $staffId );
				$departmentStaff	= $this->item()->department->staff;
				
				if ( !\in_array( $staffId, $sentTo ) and ( $this->item()->department->staff === '*' or \count( array_intersect( explode( ',', $departmentStaff ), Department::staffDepartmentPerms( $staff ) ) ) ) )
				{
					$fromEmail = ( $this->item()->department->email ) ? $this->item()->department->email : \IPS\Settings::i()->email_out;
					switch ( \IPS\Settings::i()->nexus_sout_from )
					{
						case 'staff':
							$fromName = $this->member ? $this->author()->name : $fromEmail;
							break;
						case 'dpt':
							$member = \is_int( $this->member ) ? \IPS\Member::load( $this->member ) : $this->member;
							$fromName = $this->member
								? $member->language()->get( 'nexus_department_' . $this->item()->department->_id )
								: \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'nexus_department_' . $this->item()->department->_id );
							break;
						default:
							$fromName = \IPS\Settings::i()->nexus_sout_from;
							break;
					}
										
					\IPS\Email::buildFromTemplate( 'nexus', $this->type === static::REPLY_HIDDEN ? 'staffNotifyNote' : 'staffNotifyReply', array( $this ), \IPS\Email::TYPE_LIST )
						->setUnsubscribe( 'nexus', 'unsubscribeStaffNotify' )
						->send( $staff, array(), array(), $fromEmail, $fromName );
					
					$sentTo[] = $staffId;
				}
			}
			else
			{
				\IPS\Db::i()->delete( 'nexus_support_notify', array( 'staff_id=?', $staffId ) );
			}
		}
		
		\IPS\core\AdminNotification::send( 'nexus', 'Support', NULL, TRUE, NULL, TRUE );
	}
	
	/**
	 * Send Unapproved Notification
	 *
	 * @return	void
	 */
	public function sendUnapprovedNotification()
	{
		// If a hidden note is added, we don't want to send the normal "User has posted something which
		// requires approval" email - so this method is overloaded and intentionally left blank.
	}
	
	/**
	 * Send Customer Notifications
	 *
	 * @param	string	$to		Primary "To" email address
	 * @param	array	$cc		Emails to Cc
	 * @param	array	$bcc	Emails to Bcc
	 * @return	void
	 */
	public function sendCustomerNotifications( $to, $cc, $bcc )
	{
		if ( \IPS\Settings::i()->nexus_sout_chrome )
		{
			$email = \IPS\Email::buildFromTemplate( 'nexus', 'staffReply', array( $this ), \IPS\Email::TYPE_TRANSACTIONAL );
		}
		else
		{
			$email = \IPS\Email::buildFromTemplate( 'nexus', 'staffReplyNoChrome', array( $this ), \IPS\Email::TYPE_TRANSACTIONAL, FALSE );
		}

		/* We need to figure out which language we will use */
		try
		{
			$member	= \IPS\Member::load( $to, 'email' );
		}
		catch( \OutOfRangeException $e )
		{
			$member	= NULL;
		}

		$language = $member ? $member->language() : \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
		
		$fromEmail = $this->item()->department->email ?: \IPS\Settings::i()->email_out;
		switch ( \IPS\Settings::i()->nexus_sout_from )
		{
			case 'staff':
				$fromName = $this->author()->name;
				break;
			case 'dpt':
				$fromName = $language->get( 'nexus_department_' . $this->item()->department->_id );
				break;
			default:
				$fromName = \IPS\Settings::i()->nexus_sout_from;
				break;
		}
		
		$email->send( $to, $cc, $bcc, $fromEmail, $fromName, array( 'Message-Id' => "<IPS-{$this->id}-SR{$this->item()->id}.{$this->item()->email_key}-{$fromEmail}>" ) );
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		$item = $this->item();
		
		if ( $this->type === Reply::REPLY_MEMBER or $this->type === Reply::REPLY_ALTCONTACT )
		{
			$item->status = Status::load( TRUE, 'status_default_member' );
		}
		
		return parent::postCreate();
	}
}
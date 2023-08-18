<?php
/**
 * @brief		GraphQL: Member Type
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		7 May 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * MemberrType for GraphQL API
 */
class _MemberType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_Member',
			'description' => 'Community members',
			'fields' => function () {
				return [
					'id' => [
						'type' => TypeRegistry::id(),
						'description' => "Returns the member's ID",
						'resolve' => function( $member, $args, $context, $info ) {
							return $member->member_id;
						}
					],
					'email' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's email address (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return $member->email;
						}
					],
					'url' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the URL to member's profile",
						'resolve' => function( $member, $args, $context, $info ) {
							return (string) $member->url();
						}
					],
					'name' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's username",
						'args' => [
							'formatted' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => FALSE
							]
						],
						'resolve' => function( $member, $args, $context, $info ) {
							return ( $args['formatted'] ) ? \IPS\Member\Group::load( $member->member_group_id )->formatName( $member->name ) : $member->name;
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's title",
						'resolve' => function( $member, $args, $context, $info ) {
							return null;
						}
					],
					'timezone' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's timezone (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return $member->timezone;
						}
					],
					'joined' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the date the member joined",
						'resolve' => function( $member, $args, $context, $info ) {
							return $member->joined->getTimestamp();
						}
					],
					'notifications' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::notification() ),
						'description' => "Returns the member's notifications (depending on permissions)",
						'args' => [
							'offset' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 0,
							],
							'limit' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 25
							],
							'sortBy' => [
								'type' => TypeRegistry::eNum([
									'name' => 'notification_sort',
									'values' => ['updated_time', 'sent_time', 'read_time', 'unread']
								]),
								'defaultValue' => 'updated_time'
							],
							'sortDir' => [
								'type' => TypeRegistry::eNum([
									'name' => 'notification_sort_dir',
									'values' => ['asc', 'desc']
								]),
								'defaultValue' => 'desc'
							],
							'unread' => [
								'type' => TypeRegistry::boolean(),
								'defaultValue' => NULL
							]
						],
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isOwnerMember($member) )
							{
								return NULL;
							}
							return self::notifications($member, $args, $context);
						}
					],
					'notificationCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the member's notification count (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isOwnerMember($member) )
							{
								return NULL;
							}
							return $member->notification_cnt;
						}
					],
					'posts' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the member's post count",
						'resolve' => function( $member, $args, $context, $info ) {
							return $member->member_posts;
						}
					],
					'contentCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the member's post count",
						'resolve' => function( $member, $args, $context, $info ) {
							return $member->member_posts;
						}
					],
					'reputationCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the member's reputation count",
						'resolve' => function( $member, $args, $context, $info ) {
							return ( \IPS\Settings::i()->reputation_enabled ) ? $member->pp_reputation_points : null;
						}
					],
					'solvedCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the number of items this member has solved",
						'resolve' => function ($member) {
							if( !\IPS\Application::appIsEnabled('forums') || !\IPS\forums\Topic::anyContainerAllowsSolvable() )
							{
								return 0;
							}

							$count = \IPS\Db::i()->select( 'COUNT(*) as count', 'core_solved_index', array( 'member_id=?', $member->member_id ) )->first();
							return $count;
						}
					],
					'ip_address' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's IP address (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return $member->ip_address;
						}
					],
					'warn_level' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the member's warning level (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) ){
								return NULL;
							}
							return $member->warn_level;
						}
					],
					'profileViews' => [
						'type' => TypeRegistry::int(),
						'description' => "Returns the number of profile views for this member (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return $member->members_profile_views;
						}
					],
					'validating' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Returns the member's validating status (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return (bool) $member->members_bitoptions['validating'];
						}
					],
					'group' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::group(),
						'description' => "Returns the member's primary group",
						'resolve' => function( $member, $args, $context, $info ) {
							return \IPS\Member\Group::load( $member->member_group_id );
						}
					],
					'isOnline' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Indicates whether the user is online, taking permissions into account",
						'resolve' => function ($member) {
							return ( $member->isOnline() AND !$member->isOnlineAnonymously() ) OR ( $member->isOnlineAnonymously() AND \IPS\Member::loggedIn()->isAdmin() );
						}
					],
					'lastActivity' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the timestamp of the member's last activity",
						'resolve' => function( $member, $args, $context, $info ) {
							return \IPS\DateTime::ts( $member->last_activity )->rfc3339();
						}
					],
					'lastVisit'	=> [
						'type' => TypeRegistry::string(),
						'description' => "Returns the timestamp of the member's last visit",
						'resolve' => function( $member, $args, $context, $info ) {
							return \IPS\DateTime::ts( $member->last_visit )->rfc3339();
						}
					],
					'lastPost' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the timestamp of member's last post",
						'resolve' => function( $member, $args, $context, $info ) {
							return \IPS\DateTime::ts( $this->member_last_post )->rfc3339();
						}
					],
					'secondaryGroups' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::group() ),
						'description' => "Returns the member's secondary groups (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							if( !self::isAuthorized($member) )
							{
								return NULL;
							}
							return self::secondaryGroups( $member, $args, $context, $info );
						}
					],
					'defaultStream' => [
						'type' => TypeRegistry::int(),
						'description' => "The ID of the user's default stream",
						'resolve' => function ($member) {
							if( !self::isAuthorized($member) || $member->defaultStream == FALSE )
							{
								return NULL;
							}

							return $member->defaultStream;
						}
					],
					'allowFollow' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Whether this member allows others to follow them",
						'resolve' => function ($member) {
							return !$member->members_bitoptions['pp_setting_moderate_followers'];
						}
					],
					'follow' => [
						'type' => TypeRegistry::follow(),
						'description' => "Returns fields to handle followers/following",
						'resolve' => function ($member) {
							return array(
								'app' => 'core',
								'area' => 'member',
								'id' => $member->member_id,
								'member' => $member
							);
						}
					],
					'content' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::searchResult() ),
						'args' => [
							'offset' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 0
							],
							'limit' => [
								'type' => TypeRegistry::int(),
								'defaultValue' => 25
							],
						],
						'description' => "Returns the member's content",
						'resolve' => function ($member, $args) {
							return self::content($member, $args);
						}
					],
					'clubs' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::club() ),
						'description' => "Returns the member's clubs (depending on permissions)",
						'resolve' => function( $member, $args, $context, $info ) {
							return \IPS\Member\Club::clubs( $member, 25, 'last_activity' );
						}
					],
					'photo' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns the member's photo",
						'resolve' => function( $member, $args, $context, $info ) {
							return self::photo($member);
						}
					],
					'coverPhoto' => \IPS\Api\GraphQL\Fields\CoverPhotoField::getDefinition('core_MemberCoverPhoto'),
					'customFieldGroups' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::profileFieldGroup() ),
						'resolve' => function( $member, $args, $context, $info ) {
							return self::customFieldGroups( $member, $args, $context );
						}
					],
					'maxUploadSize' => [
						'type' => TypeRegistry::int(),
						'description' => "The maximum upload size allowed by this user, either globally or per-item (whichever is smaller). NULL indicates no limit.",
						'resolve' => function( $member ) {
							if( $member->member_id && !self::isOwnerMember( $member ) )
							{
								throw new \IPS\Api\GraphQL\SafeException( 'INVALID_USER', '2S401/1', 403 ); 
							}

							return \IPS\Helpers\Form\Editor::maxTotalAttachmentSize( $member, 0 );
						}
					],
					'canBeIgnored' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Can this member be ignored by the current user?",
						'resolve' => function ($member) {
							return $member->canBeIgnored();
						}
					],
					'ignoreStatus' => [
						'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::ignoreOption() ),
						'description' => "Returns the ignore status of this member, based on the currently-authenticated member's preferences.",
						'resolve' => function ($member ) {
							return self::ignoreStatus($member);
						} 
					],

					// Messenger fields
					'messengerDisabled' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this member's messenger disabled?",
						'resolve' => function( $member ) {
							return (bool) $member->members_disable_pm;
						}
					],
					'messengerNewCount' => [
						'type' => TypeRegistry::int(),
						'description' => "Number of new messages",
						'resolve' => function ($member) {
							if( $member->member_id && !self::isOwnerMember( $member ) )
							{
								return 0;
							}
							
							return $member->msg_count_new;
						}
					]
				];
			}
		];

		parent::__construct($config);
	}

	/**
	 * Return a member's photo
	 *
	 * @param 	\IPS\Member
	 * @return	string
	 */
	protected static function photo($member) 
	{
		$member_properties = array();

		foreach( array( 'name', 'pp_main_photo', 'pp_photo_type', 'pp_thumb_photo', 'member_id' ) as $column )
		{
			$member_properties[ $column ] = $member->$column;
		}

		$photoUrl = \IPS\Member::photoUrl( $member_properties );

		if( mb_strpos( $photoUrl, "data:image/svg+xml" ) === 0 )
		{
			return static::getLetterPhotoData( $member );
		}
		else
		{
			return $photoUrl;
		}
	}

	/**
	 * Return a json array containing letter/color combo for a user's letter photo
	 *
	 * @param 	\IPS\Member
	 * @return	string
	 */
	protected static function getLetterPhotoData($member)
	{
		return json_encode( \IPS\Member::generateLetterPhoto( array(
				'name'			=> $member->name,
				'pp_main_photo'	=> $member->pp_main_photo,
				'member_id'		=> $member->member_id
			), TRUE ) );
	}

	/**
	 * Determines if this is a user authorized to access sensitive data
	 *
	 * @param 	\IPS\Member
	 * @return	boolean
	 */
	protected static function isAuthorized($member)
	{
		return self::isOwnerMember($member) || \IPS\Member::loggedIn()->isAdmin();
	}

	/**
	 * Determines if this user is the same as the user requesting the info
	 *
	 * @param 	\IPS\Member
	 * @return	boolean
	 */
	protected static function isOwnerMember($member)
	{
		return $member->member_id && $member->member_id == \IPS\Member::loggedIn()->member_id;
	}

	/**
	 * Returns a member's content
	 *
	 * @param 	\IPS\Member
	 * @return	boolean
	 */
	protected static function content($member, $args)
	{
		// Get page
		// We don't know the count at this stage, so figure out the page number from
		// our offset/limit
		$page = 1;
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );

		if( $offset > 0 )
		{
			$page = floor( $offset / $limit ) + 1;
		}

		$latestActivity = \IPS\Content\Search\Query::init()->filterForProfile( $member )->setLimit( $limit )->setPage( $page )->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED )->search();
		$latestActivity->init( TRUE );

		return $latestActivity;
	}

	/**
	 * Return user's notifications
	 *
	 * @param 	\IPS\Member
	 * @param 	array 	$args
	 * @param 	array 	$context
	 * @return	array
	 */
	protected static function notifications($member, $args, $context)
	{
		/* Specify filter in where clause */
		$where = array();
		$where[] = array( "notification_app IN('" . implode( "','", array_keys( \IPS\Application::enabledApplications() ) ) . "')" );
		$where[] = array( "`member`=?", \IPS\Member::loggedIn()->member_id );

		/* Are we filtering by unread status? */
		if( isset( $args['unread'] ) && $args['unread'] !== NULL )
		{
			if( $args['unread'] === TRUE )
			{
				$where[] = array( "read_time IS NULL" );
			}
			else
			{
				$where[] = array( "read_time IS NOT NULL" );
			}
		}

		/* Sorting */
		$sort = $args['sortBy'] . ' ' . $args['sortDir'];

		if( $args['sortBy'] == 'unread' )
		{
			$sort = $args['sortDir'] == 'desc' ? 'read_time IS NOT NULL, sent_time DESC' : 'read_time IS NULL, sent_time ASC';
		}

		/* Get Count */
		$count = \IPS\Db::i()->select( 'COUNT(*) as cnt', 'core_notifications', $where )->first();

		/* Get results */
		$returnRows = array();
		$offset = max( $args['offset'], 0 );
		$limit = min( $args['limit'], 50 );

		foreach( \IPS\Db::i()->select( '*', 'core_notifications', $where, $sort, array( $offset, $limit ) ) as $row )
		{
			try
			{
				$notification   = \IPS\Notification\Api::constructFromData( $row );
				$returnRows[]	= array( 'notification' => $notification, 'data' => $notification->getData( FALSE ) );
			}
			catch ( \LogicException $e ) { }
		}

		return $returnRows;
	}

	/**
	 * Return custom profile field groups
	 *
	 * @param 	\IPS\Member
	 * @return	boolean
	 */
	protected static function customFieldGroups($member, $args, $context)
	{
		/* Get profile field values */
		try
		{
			$profileFieldValues	= \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id=?', $member->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return null;
		}

		if( !empty( $profileFieldValues ) )
		{
			$fields = array();

			if( \IPS\Member::loggedIn()->isAdmin() OR \IPS\Member::loggedIn()->modPermissions() )
			{
				$where = array( "pfd.pf_member_hide='owner' OR pfd.pf_member_hide='staff' OR pfd.pf_member_hide='all'" );
			}
			elseif( \IPS\Member::loggedIn()->member_id == $member->member_id )
			{
				$where = array( "pfd.pf_member_hide='owner' OR pfd.pf_member_hide='all'" );
			}
			else
			{
				$where = array( "pfd.pf_member_hide='all'" );
			}

			foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( 'pfd.*', array('core_pfields_data', 'pfd'), $where, 'pfg.pf_group_order, pfd.pf_position' )->join(
				array('core_pfields_groups', 'pfg'),
				"pfd.pf_group_id=pfg.pf_group_id"
			), 'IPS\core\ProfileFields\Field' ) as $field )
			{
				if( $profileFieldValues[ 'field_' . $field->id ] !== '' AND $profileFieldValues[ 'field_' . $field->id ] !== NULL )
				{
					if( !isset( $fields[ $field->group_id ] ) ){
						$fields[ $field->group_id ] = array(
							'id' => md5( $member->member_id . $field->group_id ),
							'groupId' => $field->group_id,
							'title' => 'core_pfieldgroups_' . $field->group_id,
							'fields' => array()
						);
					}

					$fields[ $field->group_id ]['fields'][] = array(
						'id' => md5( $member->member_id . $field->id ),
						'fieldId' => $field->id,
						'title' => 'core_pfield_' . $field->id,
						'value' => json_encode( $field->apiValue( $profileFieldValues['field_' . $field->id] ) ),
						'type' => $field->type
					);
				}
			}

			return $fields;
		}

		return null;
	}

	/**
	 * Resolve followers field
	 *
	 * @param 	\IPS\Member
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function followers($member, $args, $context, $info)
	{
		$limit = min( $args['limit'], 50 );

		return array_map(
			function ($followRow)
			{
				return \IPS\Member::load( $followRow['follow_member_id'] );
			},
			iterator_to_array( $member->followers( 3, array( 'immediate', 'daily', 'weekly' ), NULL, array(0, $limit ) ) )
		);
	}

	/**
	 * Resolve secondary groups field
	 *
	 * @param 	\IPS\Member
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function secondaryGroups($member, $args, $context, $info)
	{
		$secondaryGroups = array();
		foreach ( array_filter( array_map( "intval", explode( ',', $member->mgroup_others ) ) ) as $secondaryGroupId )
		{
			try
			{
				$secondaryGroups[] = \IPS\Member\Group::load( $secondaryGroupId );
			}
			catch ( \OutOfRangeException $e ) { }
		}

		return $secondaryGroups;
	}

	/**
	 * Resolve ignore status field
	 *
	 * @param 	\IPS\Member $member	Member to check
	 * @return	array
	 */
	protected static function ignoreStatus( \IPS\Member $member)
	{
		if( !$member->canBeIgnored() || $member->member_id === \IPS\Member::loggedIn()->member_id ){
			return NULL;
		}

		$ignore = FALSE;

		try
		{
			$ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
		}
		catch ( \OutOfRangeException $e ) {
			// Just keep ignore as false
		}

		$ignoreStatus = array();

		foreach ( \IPS\core\Ignore::types() as $type )
		{
			$ignoreStatus[] = array(
				'type' => $type,
				'is_being_ignored' => !$ignore ? $ignore : $ignore->$type
			);
		}

		return $ignoreStatus;
	}

	public static function getOrderByOptions()
	{
		return ['member_id', 'joined','last_activity','name','last_visit'];
	}
}

<?php
/**
 * @brief		Base class for Content Items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Aug 2018
 */

namespace IPS\Content\Api\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use IPS\Api\GraphQL\TypeRegistry;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base mutator class for Content Items
 */
class _ItemType extends ObjectType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $itemClass	= '\IPS\Content\Item';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'core_Item';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A generic content item';


	public function __construct()
	{
		$config = [
			'name' => static::$typeName,
			'description' => static::$typeDescription,
			'fields' => function () {
				return $this->fields();
			}
		];

		parent::__construct($config);
	}

	/**
	 * Get the fields that this type supports
	 *
	 * @return	array
	 */
	public function fields()
	{
		return array(
			'id' => [
				'type' => TypeRegistry::id(),
				'resolve' => function ($item) {
					$idColumn = static::getIdColumn($item);
					return $item->$idColumn;
				}
			],
			'url' => [
				'type' => TypeRegistry::url(),
				'resolve' => function ($item) {
					return $item->url();
				}
			],
			'title' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($item) {
					return $item->mapped('title');
				}
			],
			'seoTitle' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($item) {
					return \IPS\Http\Url\Friendly::seoTitle( $item->mapped('title') );
				}
			],
			'views' => [
				'type' => TypeRegistry::int(),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\Views', class_implements( $item ) ) )
					{
						return $item->mapped('views');
					}

					return NULL;
				}
			],
			'commentCount' => [
				'type' => TypeRegistry::int(),
				'args' => [
					'includeHidden' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Should the count include hidden/unapproved comments that the logged in member can see?",
						'defaultValue' => FALSE
					]
				],
				'resolve' => function ($item, $args) {
					if( $args['includeHidden'] && method_exists( $item, 'commentCount' )  ){
						return $item->commentCount();
					}

					return $item->mapped('num_comments');
				}
			],
			'isLocked' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\Lockable', class_implements( $item ) ) )
					{
						return (bool) $item->locked();
					}

					return NULL;
				}
			],
			'isPinned' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\Pinnable', class_implements( $item ) ) )
					{
						return (bool) $item->mapped('pinned');
					}

					return NULL;
				}
			],
			'isFeatured' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\Featurable', class_implements( $item ) ) )
					{
						return (bool) $item->mapped('featured');
					}

					return NULL;
				}
			],
			'hiddenStatus' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_hiddenStatus',
					'values' => ['HIDDEN', 'PENDING', 'DELETED']
				]),
				'resolve' => function ($item) {
					if ( !\in_array( 'IPS\Content\Hideable', class_implements( $item ) ) )
					{
						switch( $item->hidden() ){
							case -2:
								return 'DELETED';
							case -1:
								return 'HIDDEN';
							case 1:
								return 'PENDING';
							default:
								return NULL;
						}
					}
				}
			],
			'updated' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($item) {
					return $item->mapped('updated');
				}
			],
			'started' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($item) {
					return $item->mapped('date');
				}
			],
			'isUnread' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\ReadMarkers', class_implements( $item ) ) )
					{
						return $item->unread();
					}

					return NULL;
				}
			],
			'timeLastRead' => [
				'type' => TypeRegistry::int(),
				'resolve' => function ($item) {
					return static::timeLastRead($item);
				}
			],
			'unreadCommentPosition' => [
				'type' => TypeRegistry::int(),
				'description' => 'Returns the position of the comment that is the first unread in this topic',
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\ReadMarkers', class_implements( $item ) ) )
					{
						return static::getUnreadPosition($item);
					}

					return NULL;
				}
			],
			'findCommentPosition' => [
				'type' => TypeRegistry::int(),
				'args' => [
					'findComment' => TypeRegistry::int()
				],
				'description' => 'Returns the position of the comment provided in the required findComment arg',
				'resolve' => function ($item, $args) {
					return static::findCommentPosition($item, $args);
				}
			],
			'follow' => [
				'type' => TypeRegistry::follow(),
				'resolve' => function ($item) {
					if( \in_array( 'IPS\Content\Followable', class_implements( $item ) ) && isset( static::$followData ) && \is_array( static::$followData ) ){
						$idColumn = static::getIdColumn($item);
						return array_merge( static::$followData, array(
							'id' => $item->$idColumn,
							'item' => $item,
							'itemClass' => static::$itemClass
						));
					}

					return NULL;
				}
			],
			'tags' => [
				'type' => TypeRegistry::listOf( \IPS\core\api\GraphQL\TypeRegistry::tag() ),
				'resolve' => function ($item) {
					if ( \in_array( 'IPS\Content\Tags', class_implements( $item ) ) )
					{
						return static::tags($item);
					}

					return NULL;
				}
			],
			'author' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
				'resolve' => function ($item, $args) {
					return static::author($item, $args);
				}
			],
			'container' => [
				// @todo return generic node
				'type' => \IPS\Node\Api\GraphQL\TypeRegistry::node(),
				'resolve' => function ($item) {
					return $item->container();
				}
			],
			'content' => [
				'type' => TypeRegistry::richText(),
				'resolve' => function ($item) {
					return $item->content();
				}
			],
			'contentImages' => [
				'type' => TypeRegistry::listOf( TypeRegistry::string() ),
				'resolve' => function ($item) {
					return static::contentImages($item);
				}
			],
			'hasPoll' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					return $item instanceof \IPS\Content\Polls && $item->poll_state;
				}
			],
			'poll' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::poll(),
				'resolve' => function ($item) {
					if( $item instanceof \IPS\Content\Polls && $item->poll_state )
					{
						return $item->getPoll();
					}

					return NULL;
				}
			],
			'firstCommentRequired' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($item) {
					return static::$itemClass::$firstCommentRequired;
				}
			],
			'articleLang' => [
				'type' => new ObjectType([
					'name' => static::$typeName . '_articleLang',
					'fields' => [
						'indefinite' => TypeRegistry::string(),
						'definite' => [
							'type' => TypeRegistry::string(),
							'args' => [
								'uppercase' => [
									'type' => TypeRegistry::boolean(),
									'defaultValue' => FALSE
								]
							]
						]
					],
					'resolveField' => function ($item, $args, $context, $info) {
						$className = \get_class( $item );

						switch( $info->fieldName )
						{
							case 'indefinite':
								return $className::_indefiniteArticle( NULL );
							break;
							case 'definite':
								return $className::_definiteArticle( NULL, NULL, $args['uppercase'] ? array( 'ucfirst' => TRUE ) : array() );	
							break;
						}
					}
				]),
				'resolve' => function ($item) {
					return $item;
				}
			],
			'lastCommentAuthor' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
				'resolve' => function ($item, $args) {
					return static::lastCommentAuthor($item, $args);
				}
			],
			'lastCommentDate' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($item, $args) {
					return static::lastCommentDate($item, $args);
				}
			],
			'comments' => [
				'type' => TypeRegistry::listOf( static::getCommentType() ),
				'args' => static::getCommentType()::args(),
				'resolve' => function ($item, $args, $context) {
					return static::comments($item, $args);
				}
			],
			'itemPermissions' => [
				'type' => new ObjectType([
					'name' => static::$typeName . '_itemPermissions',
					'fields' => static::getItemPermissionFields()
				]),
				'resolve' => function ($item) {
					return $item;
				}
			],
			'uploadPermissions' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::attachmentPermissions(),
				'description' => 'Details about what the user can attach when commenting on this item.',
				'args' => [
					'postKey' => TypeRegistry::nonNull( TypeRegistry::string() ),
				],
				'resolve' => function( $node, $args, $context ) {
					return array( 'postKey' => $args['postKey'] );
				}
			],
			'reportStatus' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::report(),
				'resolve' => function ($item) {
					return $item;
				}
			]
		);
	}

	/**
	 * Return the ID column for the provided item
	 *
	 * @return	array
	 */
	protected function getIdColumn($item)
	{
		$className = \get_class( $item );
		return $className::$databaseColumnId;
	}

	/**
	 * Return the arguments that can be used to filter topics. Passed into NodeType.
	 *
	 * @return	array
	 */
	public static function args() 
	{
		return array(
			'offset' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 0
			],
			'limit' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 25
			],
			'orderBy' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_order_by',
					'description' => 'Fields on which items can be sorted',
					'values' => static::getOrderByOptions()
				]),
				'defaultValue' => NULL // will use default sort option
			],
			'orderDir' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_order_dir',
					'description' => 'Directions in which items can be sorted',
					'values' => [ 'ASC', 'DESC' ]
				]),
				'defaultValue' => 'DESC'
			],
			'honorPinned' => [
				'type' => TypeRegistry::boolean(),
				'defaultValue' => true
			]
		);
	}

	/**
	 * Return the available sorting options
	 *
	 * @return	array
	 */
	public static function getOrderByOptions()
	{
		return array('title', 'author_name', 'last_comment_name');
	}

	/**
	 * Get the field config for the item permissions query
	 *
	 * @return	array
	 */
	public static function getItemPermissionFields()
	{
		return array(
			'canComment' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the logged in user comment on this item?',
				'resolve' => function ($item, $args, $context) {
					return $item->canComment( \IPS\Member::loggedIn(), FALSE );
				}
			],
			'commentInformation' => [
				'type' => TypeRegistry::string(),
				'description' => 'A message providing some information about the comment form availability',
				'resolve' => function ($item, $args, $context) {
					if( $item->canComment( \IPS\Member::loggedIn(), FALSE ) )
					{
						if( $item instanceof \IPS\Content\Lockable && $item->locked() )
						{
							return 'locked_can_comment';
						}
					}
					else
					{
						if( $item instanceof \IPS\Content\Lockable && $item->locked() )
						{
							return 'locked_cannot_comment';
						}
						elseif( \IPS\Member::loggedIn()->restrict_post )
						{
							return 'restricted_cannot_comment';
						} 
						elseif( \IPS\Member::loggedIn()->members_bitoptions['unacknowledged_warnings'] )
						{
							return 'unacknowledged_warning_cannot_post';
						}
						elseif( !\IPS\Member::loggedIn()->checkPostsPerDay() )
						{
							return 'member_exceeded_posts_per_day';
						}
					}

					return NULL;
				}
			],
			'canCommentIfSignedIn' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Returns boolean indicating whether a guest who signs in would be able to comment on this item. Returns NULL if already signed in.',
				'resolve' => function ($item, $args, $context) {
					if ( !\IPS\Member::loggedIn()->member_id )
					{
						$testUser = new \IPS\Member;
						$testUser->member_group_id = \IPS\Settings::i()->member_group;
						
						return $item->canComment( $testUser, FALSE );
					}

					return NULL;
				}
			],
			'canMarkAsRead' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Boolean indicating whether this item supports read markers, and if so, if the user can mark as read',
				'resolve' => function ($item) {
					return $item instanceof \IPS\Content\ReadMarkers && \IPS\Member::loggedIn()->member_id;
				}
			],
			'canReport' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the user report this item?',
				'resolve' => function ($item) {
					return $item->canReport( \IPS\Member::loggedIn() ) === TRUE;
				}
			],
			'canReportOrRevoke' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the user report (or revoke a report) on this item?',
				'resolve' => function ($item) {
					return $item->canReportOrRevoke( \IPS\Member::loggedIn() ) === TRUE;
				}
			],
			'canShare' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can this item be shared?',
				'resolve' => function ($item) {
					return $item->canShare();
				}
			]
		);
	}

	/**
	 * Get the comment type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	protected static function getCommentType()
	{
		return \IPS\Content\Api\GraphQL\TypeRegistry::comment();
	}

	/**
	 * Return content images for the provided item
	 *
	 * @return	array|null
	 */
	protected static function contentImages($item)
	{
		try
		{
			if ( $images = $item->contentImages( 20 ) )
			{
				foreach( $images as $image )
				{
					foreach( $image as $extension => $file )
					{
						$toReturn[] = (string) \IPS\File::get( $extension, $file )->url;
					}
				}
				return $toReturn;
			}
		}
		catch( \BadMethodCallException $e ) { }

		return NULL;
	}

	/**
	 * Resolve the findCommentPosition field
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function findCommentPosition($item, $args)
	{
		if( $args['findComment'] === NULL )
		{
			return NULL;
		}

		try 
		{
			$comment = static::$itemClass::$commentClass::load( $args['findComment'] );

			// Check this comment belongs to this topic
			if( $comment->item() !== $item )
			{
				return NULL;
			}
		}
		catch (\Exception $e)
		{
			return NULL;
		}

		return static::findComment($comment, $item);
	}

	/**
	 * Resolve the comments field
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	array
	 */
	protected static function comments($item, $args)
	{
		$offset = 0;
		$limit = 25;

		/* Figure out where we're starting our offset */
		switch( $args['offsetPosition'] )
		{
			case 'UNREAD':
				$offset = static::getUnreadPosition($item) + $args['offsetAdjust'];
			break;
			case 'LAST':
				// Since we're zero-indexed, when we're working from the end we need to go one more to get the last item
				$offset = static::getEndPosition($item) + $args['offsetAdjust'] + 1;
			break;
			case 'ID':
				if( !isset( $args['findComment'] ) )
				{
					throw new \OutOfRangeException;
				}

				$offset = static::getCommentPosition($item, $args['findComment']) + $args['offsetAdjust'];
			break;
			case 'FIRST':
			default:
				$offset = 0 + $args['offsetAdjust'];
		}

		/* Ensure offset is never lower than 0 */
		$offset = max( $offset, 0 );
		$limit = min( $args['limit'], 50 );

		if( $args['orderBy'] == 'DATE' )
		{
			$args['orderBy'] = static::$itemClass::$commentClass::$databaseColumnMap['date'];
		}

		
		// We can't allow straight boolean TRUE here otherwise members without permission
		// will see them. Instead, if TRUE is passed as a value in the query, set the value
		// to NULL which honors permissions.
		$includeDeleted = $args['includeDeleted'] ? NULL : FALSE;
		$includeHidden = $args['includeHidden'] ? NULL : FALSE;

		return $item->comments( $limit, $offset, $args['orderBy'], $args['orderDir'], NULL, $includeHidden, NULL, NULL, NULL, $includeDeleted );
	}

	/**
	 * Get the position of a specific comment
	 *
	 * @param 	\IPS\Content\Item
	 * @return	int
	 */
	protected static function getCommentPosition($item, $commentID)
	{
		try 
		{
			$comment = static::$itemClass::$commentClass::load($commentID);
			return static::findComment($comment, $item);
		}
		catch(\Exception $error)
		{}
	}

	/**
	 * Get the position of the last comment
	 *
	 * @param 	\IPS\Content\Item
	 * @return	int
	 */
	protected static function getEndPosition($item)
	{
		$comment = $item->comments( 1, NULL, 'date', 'desc' );
		return static::findComment($comment, $item);
	}

	/**
	 * Get the position of the first unread comment
	 *
	 * @param 	\IPS\Content\Item
	 * @return	int
	 */
	protected static function getUnreadPosition($item)
	{
		try
		{	
			$class = static::$itemClass;
			$timeLastRead = $item->timeLastRead();

			if ( $timeLastRead instanceof \IPS\DateTime )
			{
				$comment = NULL;
				if( \IPS\DateTime::ts( $item->mapped('date') ) < $timeLastRead )
				{
					$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead );
				}

				/* If we don't have any unread comments... */
				if ( !$comment and $class::$firstCommentRequired )
				{
					/* If we haven't read the item at all, go there */
					if ( $item->unread() )
					{
						return 0;
					}
					/* Otherwise, go to the last comment */
					else
					{
						$comment = $item->comments( 1, NULL, 'date', 'desc' );
					}
				}

				if( !$comment ){
					return 0;
				}
			}
			else
			{
				if ( $item->unread() )
				{
					/* If we do not have a time last read set for this content, fallback to the reset time */
					$resetTimes = \IPS\Member::loggedIn()->markersResetTimes( $class::$application );

					if ( array_key_exists( $item->container()->_id, $resetTimes ) and $item->mapped('date') < $resetTimes[ $item->container()->_id ] )
					{
						$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $resetTimes[ $item->container()->_id ] ) );
						
						if ( !$comment || $class::$firstCommentRequired and $comment->isFirst() )
						{
							return 0;
						}
					}
					else
					{
						return 0;
					}
				}
				else
				{
					return 0;
				}
			}

			return static::findComment($comment, $item);
		}
		catch( \Exception $e )
		{
			return 0;
		}
	}

	/**
	 * Find the position of a comment
	 *
	 * @param 	\IPS\Content\Comment
	 * @param 	\IPS\Content\Item
	 * @return	int
	 */
	protected static function findComment($comment, $item)
	{
		try 
		{
			$commentClass = \get_class( $comment );
			$idColumn = $commentClass::$databaseColumnId;
			$itemColumn = $commentClass::$databaseColumnMap['item'];

			/* Work out where the comment is in the item */	
			$directional = ( \in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) ) ? '>=?' : '<=?';
			$where = array(
				array( $commentClass::$databasePrefix . $itemColumn . '=?', $comment->$itemColumn ),
				array( $commentClass::$databasePrefix . $idColumn . $directional, $comment->$idColumn )
			);

			/* Exclude content pending deletion, as it will not be shown inline  */
			if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '<>?', -2 );
			}
			elseif( isset( $commentClass::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '<>?', -2 );
			}

			if ( $commentClass::commentWhere() !== NULL )
			{
				$where[] = $commentClass::commentWhere();
			}
			if ( $container = $item->containerWrapper() )
			{
				if ( $commentClass::modPermission( 'view_hidden', NULL, $container ) === FALSE )
				{
					if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
					{
						$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
					}
					elseif( isset( $commentClass::$databaseColumnMap['hidden'] ) )
					{
						$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 0 );
					}
				}
			}
			$commentPosition = \IPS\Db::i()->select( 'COUNT(*) AS position', $commentClass::$databaseTable, $where )->first();

			if( static::$itemClass::$firstCommentRequired ){
				$commentPosition = $commentPosition - 1;
			}

			return $commentPosition;
		} 
		catch( \Exception $e )
		{
			return 0;
		}
	}

	/**
	 * Resolve the tags field
	 *
	 * @param 	\IPS\Content\Item
	 * @return	int
	 */
	protected static function timeLastRead($item)
	{
		$time = $item->timeLastRead();
		if( $time instanceof \IPS\DateTime )
		{
			return (int) $time->setTimezone( new \DateTimeZone( "UTC" ) )->format('U');
		}
		return NULL;
	}

	/**
	 * Resolve the tags field
	 *
	 * @param 	\IPS\Content\Item
	 * @return	array
	 */
	protected static function tags($item)
	{
		$tags = $item->tags();
		return \is_array( $tags ) ? $tags : array();
	}

	/**
	 * Resolve the author field
	 *
	 * @param 	null
	 * @param 	array 	Arguments passed to this resolver
	 * @return	\IPS\forums\Forum
	 */
	protected static function author($item, $args)
	{
		return $item->author();
	}

	/**
	 * Resolve the last comment author field
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	\IPS\Member
	 */
	protected static function lastCommentAuthor($item, $args)
	{
		if( $item->mapped('num_comments') )
		{
			return $item->lastCommenter();
		}
		
		return $item->author();
	}

	 /**
	 * Resolve the last comment date field
	 *
	 * @param 	\IPS\forums\Topic
	 * @param 	array 	Arguments passed to this resolver
	 * @return	string
	 */
	protected static function lastCommentDate($item, $args)
	{
		if( $item->mapped('last_comment') )
		{
			return $item->mapped('last_comment');
		}
		
		return $item->mapped('date');
	}
}
<?php
/**
 * @brief		Base class for Content Comments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Aug 2018
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
 * @brief	Base mutator class for Content Comments
 */
class _CommentType extends ObjectType
{
	/*
	 * @brief 	The item classname we use for this type
	 */
	protected static $commentClass	= '\IPS\Content\Comment';

	/*
	 * @brief 	GraphQL type name
	 */
	protected static $typeName = 'core_Comment';

	/*
	 * @brief 	GraphQL type description
	 */
	protected static $typeDescription = 'A generic content comment item';

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
				'resolve' => function ($comment) {
					$idColumn = $comment::$databaseColumnId;
					return $comment->$idColumn;
				}
			],
			'url' => [
				'type' => TypeRegistry::url(),
				'resolve' => function ($comment) {
					$idColumn = $comment::$databaseColumnId;
					return $comment->item()->url()->setQueryString( array( 'do' => 'findComment', 'comment' => $comment->$idColumn ) );
				}
			],
			'timestamp' => [
				'type' => TypeRegistry::string(),
				'resolve' => function ($comment) {
					return $comment->mapped('date');
				}
			],
			'author' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
				'resolve' => function ($comment) {
					return $comment->author();
				}
			],
			'item' => [
				'type' => \IPS\Content\Api\GraphQL\TypeRegistry::item(),
				'resolve' => function ($comment) {
					return $comment->item();
				}
			],
			'reputation' => [
				'type' => TypeRegistry::reputation(),
				'resolve' => function ($comment) {
					return $comment;
				}
			],
			'content' => [
				'type' => TypeRegistry::richText(),
				'resolve' => function ($comment) {
					return $comment->content();
				}
			],
			'isFirstPost' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($comment) {
					$idColumn = $comment::$databaseColumnId;
					return $comment->item()->topic_firstpost == $comment->$idColumn;
				}
			],
			'isIgnored' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($comment) {
					return $comment->isIgnored();
				}
			],
			'isFeatured' => [
				'type' => TypeRegistry::boolean(),
				'resolve' => function ($comment) {
					return $comment->isFeatured();
				}
			],
			'hiddenStatus' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_hiddenStatus',
					'values' => ['HIDDEN', 'PENDING', 'DELETED']
				]),
				'resolve' => function ($comment) {
					switch( $comment->hidden() ){
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
								],
								'withItem' => [
									'type' => TypeRegistry::boolean(),
									'defaultValue' => TRUE
								]
							]
						],
					],
					'resolveField' => function ($item, $args, $context, $info) {
						$className = \get_class( $item );

						switch( $info->fieldName )
						{
							case 'indefinite':
								return $className::_indefiniteArticle( NULL );
							break;
							case 'definite':
								if( $args['withItem'] === FALSE )
								{
									// Normal defart strings are "post in a topic". This option allows us to return "post" instead.
									// However for now it's something specific to the graphql api
									return static::definiteArticleNoItem($item, $args['uppercase'] ? array( 'ucfirst' => TRUE ) : array() );
								} 
								
								// Return normal definite article string handled by \IPS\Content
								return $className::_definiteArticle( NULL, NULL, $args['uppercase'] ? array( 'ucfirst' => TRUE ) : array() );									
							break;
						}
					}
				]),
				'resolve' => function ($item) {
					return $item;
				}
			],
			'commentPermissions' => [
				'type' => new ObjectType([
					'name' => static::$typeName . '_commentPermissions',
					'fields' => static::getCommentPermissionFields()
				]),
				'resolve' => function ($comment) {
					return $comment;
				}
			],
			'reportStatus' => [
				'type' => \IPS\core\api\GraphQL\TypeRegistry::report(),
				'resolve' => function ($comment) {
					return $comment;
				}
			]
		);
	}

	/**
	 * Get the field config for the comment permissions query
	 *
	 * @return	array
	 */
	public static function getCommentPermissionFields()
	{
		return array(
			'canShare' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the user share this item?',
				'resolve' => function ($comment) {
					return $comment->canShare( \IPS\Member::loggedIn() );
				}
			],
			'canReport' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the user report this item?',
				'resolve' => function ($comment) {
					return $comment->canReport( \IPS\Member::loggedIn() ) === TRUE;
				}
			],
			'canReportOrRevoke' => [
				'type' => TypeRegistry::boolean(),
				'description' => 'Can the user report (or revoke a report) on this comment?',
				'resolve' => function ($comment) {
					return $comment->canReportOrRevoke( \IPS\Member::loggedIn() ) === TRUE;
				}
			]
		);
	}

	/**
	 * Get the arguments that will be passed into the item type's schema
	 *
	 * @return	array
	 */
	public static function args()
	{
		return array(
			'offsetPosition' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_offset_position',
					'description' => 'Provides an easy way to set the offset to a specific relevant position. If ID, the findComment arg is required.',
					'values' => [ 'FIRST', 'UNREAD', 'LAST', 'ID' ]
				]),
				'defaultValue' => 'FIRST'
			],
			'offsetAdjust' => [
				'type' => TypeRegistry::int(),
				'description' => 'Provides the offset to fetch. If offsetPosition is any value other than `CUSTOM`, then this arg adjusts the offset returned by offsetPosition.',
				'defaultValue' => 0
			],
			'findComment' => [
				'type' => TypeRegistry::int(),
				'description' => 'Sets offset to the position of the comment ID provided.',
			],
			'limit' => [
				'type' => TypeRegistry::int(),
				'defaultValue' => 25
			],
			'orderBy' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_order_by',
					'description' => 'Fields on which comments can be sorted',
					'values' => [
						'DATE' => 'date'
					]
				]),
				'defaultValue' => 'DATE'
			],
			'orderDir' => [
				'type' => TypeRegistry::eNum([
					'name' => static::$typeName . '_order_dir',
					'description' => 'Directions in which topics can be sorted',
					'values' => [ 'ASC', 'DESC' ]
				]),
				'defaultValue' => 'ASC'
			],
			'includeHidden' => [
				'type' => TypeRegistry::boolean(),
				'defaultValue' => NULL,
				'description' => 'Whether to include hidden comments, if the viewer has permission to see them'
			],
			'includeDeleted' => [
				'type' => TypeRegistry::boolean(),
				'defaultValue' => NULL,
				'description' => 'Whether to include deleted comments, if the viewer has permission to see them'
			]
		);
	}

	/**
	 * Get the item type that goes with this item type
	 *
	 * @return	ObjectType
	 */
	public static function getItemType()
	{
		return \IPS\Content\Api\GraphQL\TypeRegistry::item();
	}

	/**
	 * Return the definite article, but without the item type
	 *
	 * @return	string
	 */
	public static function definiteArticleNoItem($post, $options = array())
	{
		return \IPS\Member::loggedIn()->language()->addToStack( '__defart_comment', FALSE );
	}
}
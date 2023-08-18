<?php
/**
 * @brief		GraphQL: Search result Type
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
 * ContentSearchResultType for GraphQL API
 */
class _ContentSearchResultType extends ObjectType
{
	/**
	 * Get object type
	 *
	 * @return	ObjectType
	 */
	public function __construct()
	{
		$config = [
			'name' => 'core_ContentSearchResult',
			'description' => 'A search result item',
			'fields' => function () {
				return [
					'indexID' => [
						'type' => TypeRegistry::id(),
						'description' => "Index ID of this result",
						'resolve' => function ($result) {
							return self::getFieldValue('index_id', $result);
						}
					],
					'itemID' => [
						'type' => TypeRegistry::int(),
						'description' => "ID of this result item",
						'resolve' => function ($result) {
							return self::getFieldValue('index_item_id', $result);
						}
					],
					'objectID' => [
						'type' => TypeRegistry::int(),
						'description' => "ID of the indexed result",
						'resolve' => function ($result) {
							return self::getFieldValue('index_object_id', $result);
						}
					],
					'containerID' => [
						'type' => TypeRegistry::int(),
						'description' => "ID of this result container",
						'resolve' => function ($result) {
							return self::getFieldValue('index_container_id', $result);
						}
					],
					'url' => [
						'type' => TypeRegistry::string(),
						'description' => "URL to this result item",
						'resolve' => function ($result) {
							return self::url($result);
						}
					],					
					'containerTitle' => [
						'type' => TypeRegistry::string(),
						'description' => "Title of this result container",
						'resolve' => function ($result) {
							return self::containerTitle( $result );
						}
					],
					'class' => [
						'type' => TypeRegistry::string(),
						'description' => "Class of this result item",
						'resolve' => function ($result) {
							return self::getFieldValue('index_class', $result);
						}
					],
					'itemClass' => [
						'type' => TypeRegistry::string(),
						'description' => "Class of the item type, if this result is a comment/review",
						'resolve' => function ($result) {
							$asArray = $result->asArray();
							$itemClass = static::getItemClass( $asArray );

							return $itemClass;
						}
					],
					// @todo this should really be in a more generic ClassType that returns 
					// data about each class, with appropriate permission checking in place
					'firstCommentRequired' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Does the result class require a first comment (e.g. topics)?",
						'resolve' => function ($result) {
							$asArray = $result->asArray();
							$itemClass = static::getItemClass( $asArray );

							return $itemClass::$firstCommentRequired;
						}
					],
					'content' => [
						'type' => TypeRegistry::string(),
						'description' => "Search result content",
						'args' => [
							'truncateLength' => [
								'type' => TypeRegistry::int(),
								'description' => 'Characters to truncate on.',
								'defaultValue' => 0
							]
						],
						'resolve' => function ($result, $args) {
							$string = html_entity_decode( self::getFieldValue('index_content', $result), ENT_QUOTES, 'UTF-8' );
							$string = preg_replace( "/\r|\n|\t/", '', $string );
							
							/* Truncate */
							if( $args['truncateLength'] )
							{
								$string = mb_substr( $string, 0, $args['truncateLength'] );
							}

							return $string;
						}
					],
					'contentImages' => [
						'type' => TypeRegistry::listOf( TypeRegistry::string() ),
						'description' => "Item images for this search result",
						'resolve' => function ($result) {
							return self::contentImages( $result );
						}
					],
					'articleLang' => [
						'type' => new ObjectType([
							'name' => 'core_articleLang',
							'fields' => [
								'indefinite' => TypeRegistry::string(),
								'definite' => TypeRegistry::string(),
								'definiteUC' => TypeRegistry::string()
							],
							'resolveField' => function ($result, $args, $context, $info) {
								$asArray = $result->asArray();
								$classToUse = static::getItemClass( $asArray );

								switch( $info->fieldName )
								{
									case 'indefinite':
										return $classToUse::_indefiniteArticle( $asArray['containerData'] );
									break;
									case 'definite':
										return $classToUse::_definiteArticle( $asArray['containerData'] );
									break;
									case 'definiteUC':
										return $classToUse::_definiteArticle( $asArray['containerData'], NULL, array( 'ucfirst' => TRUE ) );
									break;
								}
							}
						]),
						'resolve' => function ($result) {
							return $result;
						}
					],
					'title' => [
						'type' => TypeRegistry::string(),
						'description' => "Search result item title",
						'resolve' => function ($result) {
							return self::title($result);
						}
					],
					'unread' => [
						'type' => TypeRegistry::boolean(),
						'resolve' => function ($result) {
							return self::isUnread( $result );
						}
					],
					'hiddenStatus' => [
						'type' => TypeRegistry::eNum([
							'name' => 'search_hiddenStatus',
							'values' => ['HIDDEN', 'PENDING', 'DELETED']
						]),
						'resolve' => function ($result) {
							switch( self::getFieldValue('index_hidden', $result) ){
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
					'updated' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of when this result was updated",
						'resolve' => function ($result) {
							return self::getFieldValue('index_date_updated', $result);
						}
					],
					'created' => [
						'type' => TypeRegistry::int(),
						'description' => "Timestamp of when this result was created",
						'resolve' => function ($result) {
							return self::getFieldValue('index_date_created', $result);
						}
					],
					'isComment' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this result a comment?",
						'resolve' => function ($result) {
							return \in_array( 'IPS\Content\Comment', class_parents( self::getFieldValue('index_class', $result) ) ) && !self::getFieldValue('index_title', $result);
						}
					],
					'isReview' => [
						'type' => TypeRegistry::boolean(),
						'description' => "Is this result a review?",
						'resolve' => function ($result) {
							return \in_array( 'IPS\Content\Review', class_parents( self::getFieldValue('index_class', $result) ) );
						}
					],
					'replies' => [
						'type' => TypeRegistry::int(),
						'description' => "Number of replies to the content item",
						'resolve' => function ($result) {
							return self::replies($result);
						}
					],
					'relativeTimeKey' => [
						'type' => TypeRegistry::string(),
						'description' => "Returns a string indicating the relative posting time of this item",
						'resolve' => function ($result) {
							return $result->streamSeparator();
						}
					],
					'author' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'description' => "Author of this result",
						'resolve' => function ($result) {
							return \IPS\Member::load( self::getFieldValue('index_author', $result) );
						}
					],
					'itemAuthor' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::member(),
						'description' => "Author of the original content item",
						'resolve' => function ($result) {
							return \IPS\Member::load( self::getFieldValue('index_item_author', $result) );	
						}
					],
					'club' => [
						'type' => \IPS\core\api\GraphQL\TypeRegistry::club(),
						'description' => "Club this result belongs to, if applicable",
						'resolve' => function ($result) {
							return self::club( $result );
						}
					],
					'reactions' => [
						'type' => TypeRegistry::listOf( \IPS\core\Api\GraphQL\TypeRegistry::contentReaction() ), 
						'description' => "Reactions for this content",
						'resolve' => function ($result) {
							try {
								$reactions = array();

								if( \count( $result->reactions ) )
								{
									foreach( $result->reactions as $reactID => $count )
									{
										$reactions[] = array(
											'id' => $reactID,
											'reaction' => \IPS\Content\Reaction::load( $reactID ),
											'count' => $count
										);
									}
								}

								return $reactions;
							}
							catch (\Exception $e)
							{}
								
							return array();
						}
					]
				];
			}
		];

		parent::__construct($config);
	}

	protected static function getFieldValue( $field, $result )
	{
		$asArray = $result->asArray();
		return $asArray['indexData'][ $field ];
	}

	/**
	 * Return the number of replies
	 *
	 * @param 	\IPS\Content\Search\Result 	Search result
	 * @return	string
	 */
	protected static function replies($result) {
		$asArray = $result->asArray();
		$itemData = $asArray['itemData'];

		if ( \in_array( 'IPS\Content\Comment', class_parents( $asArray['indexData']['index_class'] ) ) )
		{
			$itemClass = static::getItemClass( $asArray );

			if( isset( $itemClass::$databaseColumnMap['num_comments'] ) and isset( $itemData[ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] ] ) )
			{
				if( $itemClass::$firstCommentRequired )
				{
					return $itemData[ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] ] - 1;
				}
				else
				{
					return $itemData[ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['num_comments'] ];
				}
			}		
			
			return 0;
		}
		else
		{
			$indexClass = self::getFieldValue('index_class', $result);

			if ( isset( $indexClass::$databaseColumnMap['num_comments'] ) and isset( $itemData[ $indexClass::$databasePrefix . $indexClass::$databaseColumnMap['num_comments'] ] ) )
			{
				if ( $indexClass::$firstCommentRequired )
				{
					return $itemData[ $indexClass::$databasePrefix . $indexClass::$databaseColumnMap['num_comments'] ] - 1;
				}
				else
				{
					return $itemData[ $indexClass::$databasePrefix . $indexClass::$databaseColumnMap['num_comments'] ];
				}

				return 0;
			}
		}

		return NULL;
	}

	/**
	 * Return the content item title
	 *
	 * @param 	\IPS\Content\Search\Result 	Search result
	 * @return	string
	 */
	protected static function title( $result )
	{
		$asArray = $result->asArray();
		$itemClass = static::getItemClass( $asArray );

		// If the content is used as the title (e.g. status updates), don't return anything
		if( $itemClass::$databaseColumnMap['title'] == 'content' )
		{
			return NULL;
		}

		return $asArray['itemData'][ $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['title'] ];
	}

	/**
	 * Return the item URL
	 *
	 * @param 	\IPS\Content\Search\Result 	Search result
	 * @return	string
	 */
	protected static function url( $result )
	{
		$asArray = $result->asArray();
		$itemClass = static::getItemClass( $asArray );
		$itemUrl = $itemClass::urlFromIndexData( $asArray['indexData'], $asArray['itemData'] );

		/* Object URL */
		if ( \in_array( 'IPS\Content\Comment', class_parents( $asArray['indexData']['index_class'] ) ) )
		{
			if ( \in_array( 'IPS\Content\Review', class_parents( $asArray['indexData']['index_class'] ) ) )
			{
				$itemUrl = $itemUrl->setQueryString( array( 'do' => 'findReview', 'review' => $asArray['indexData']['index_object_id'] ) );
			}
			else
			{
				$itemUrl = $itemUrl->setQueryString( array( 'do' => 'findComment', 'comment' => $asArray['indexData']['index_object_id'] ) );
			}
		}

		return $itemUrl;
	}

	/**
	 * Return the unread status of this content
	 *
	 * @param 	\IPS\Content\Search\Result 	Search result
	 * @return	boolean
	 */
	protected static function isUnread( $result )
	{
		$asArray = $result->asArray();
		$itemClass = static::getItemClass( $asArray );

		if ( \in_array( 'IPS\Content\Comment', class_parents( $itemClass ) ) )
		{
			$contentClass = $itemClass::$contentItemClass;
			$unread = $contentClass::unreadFromData( NULL, $asArray['indexData']['index_date_updated'], $asArray['indexData']['index_date_created'], $asArray['indexData']['index_item_id'], $asArray['indexData']['index_container_id'], FALSE );
		}
		else
		{
			$unread = $itemClass::unreadFromData( NULL, $asArray['indexData']['index_date_updated'], $asArray['indexData']['index_date_created'], $asArray['indexData']['index_item_id'], $asArray['indexData']['index_container_id'], FALSE );
		}

		return $unread;
	}

	/**
	 * Resolve contentImage
	 *
	 * @param 	array 	Array representation of \IPS\Content\Search\Result
	 * @param 	array 	Arguments passed from resolver
	 * @return	string
	 */
	protected static function contentImages( $result )
	{
		try {
			$itemClass = static::getItemClass( $result->asArray() );
			$item = $itemClass::load( self::getFieldValue('index_item_id', $result) );
			$toReturn = array();

			if ( $images = $item->contentImages( 20 ) )
			{
				foreach( $images as $image )
				{
					foreach( $image as $extension => $file )
					{
						$toReturn[] = (string) \IPS\File::get( $extension, $file )->url;
					}
				}
			}

			return $toReturn;
		} catch (\Exception $e) {
			return NULL;
		}

		return NULL;
	}

	/**
	 * Resolve containerTitle
	 *
	 * @param 	array 	Array representation of \IPS\Content\Search\Result
	 * @param 	array 	Arguments passed from resolver
	 * @return	string
	 */
	protected static function containerTitle( $result )
	{
		$result = $result->asArray();
		$itemClass = static::getItemClass( $result );
		$containerTitle = NULL;

		if ( isset( $itemClass::$containerNodeClass ) )
		{
			$containerClass	= $itemClass::$containerNodeClass;
			$containerTitle	= $containerClass::titleFromIndexData( $result['indexData'], $result['itemData'], $result['containerData'], FALSE );
		}

		return $containerTitle;
	}

	/**
	 * Resolve club
	 *
	 * @param 	array 	Array representation of \IPS\Content\Search\Result
	 * @param 	array 	Arguments passed from resolver
	 * @return	\IPS\Member\Club
	 */
	protected static function club( $result )
	{
		if( self::getFieldValue('index_club_id', $result) )
		{
			return \IPS\Member\Club::load( self::getFieldValue('index_club_id', $result) );
		}

		return NULL;
	}

	/**
	 * Get the item class for the result
	 *
	 * @param 	array 	Array representation of \IPS\Content\Search\Result
	 * @return	string
	 */
	protected static function getItemClass( $result )
	{
		$indexClass = $result['indexData']['index_class'];
		$itemClass = ( \in_array( 'IPS\Content\Comment', class_parents( $indexClass ) ) ) ? $indexClass::$itemClass : $indexClass;

		return $itemClass;
	}
}

<?php
/**
 * @brief		Album Review Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		17 Mar 2017
 */

namespace IPS\gallery\Album;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Album Review Model
 */
class _Review extends \IPS\Content\Review implements \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Searchable, \IPS\Content\Embeddable
{
	use \IPS\Content\Reactable, \IPS\Content\Reportable;

	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\gallery\Album\Item';

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'gallery_album_reviews';

	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'review_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'album_id',
		'author'			=> 'author',
		'author_name'		=> 'author_name',
		'content'			=> 'content',
		'date'				=> 'date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_member_name'	=> 'edit_member_name',
		'edit_show'			=> 'edit_show',
		'rating'			=> 'rating',
		'votes_total'		=> 'votes_total',
		'votes_helpful'		=> 'votes_helpful',
		'votes_data'		=> 'votes_data',
		'approved'			=> 'approved',
		'author_response'	=> 'author_response',
	);

	/**
	 * @brief	Application
	 */
	public static $application = 'gallery';

	/**
	 * @brief	Title
	 */
	public static $title = 'gallery_album_review';

	/**
	 * @brief	Icon
	 */
	public static $icon = 'camera';

	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'gallery-album-review';

	/**
	 * Get URL for doing stuff
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 * @throws	\BadMethodCallException
	 * @throws	\IPS\Http\Url\Exception
	 */
	public function url( $action='find' )
	{
		return parent::url( $action )->setQueryString( 'tab', 'reviews' );
	}

	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		parent::postCreate();

		$this->item()->asNode()->setLastReview( $this );
		$this->item()->asNode()->save();
	}

	/**
	 * Get snippet HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	string		$view			'expanded' or 'condensed'
	 * @return	callable
	 */
	public static function searchResultSnippet( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $view )
	{
		return \IPS\gallery\Album\Comment::searchResultSnippet( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $view );
	}

	/**
	 * @brief	A classname applied to the search result block
	 */
	public static $searchResultClassName = 'cGalleryAlbumSearchResult';

	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'album_review';
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'gallery', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'gallery' )->embedAlbumReview( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}

	/**
	 * Get the container item class to use for mod permission checks
	 *
	 * @return	string|NULL
	 * @note	By default we will return NULL and the container check will execute against Node::$contentItemClass, however
	 *	in some situations we may need to override this (i.e. for Gallery Albums)
	 */
	protected static function getContainerModPermissionClass()
	{
		return 'IPS\gallery\Album\Item';
	}
}
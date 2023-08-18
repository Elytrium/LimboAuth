<?php
/**
 * @brief		Event Review Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		7 Jan 2014
 */

namespace IPS\calendar\Event;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Event Review Model
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
	public static $itemClass = 'IPS\calendar\Event';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'calendar_event_reviews';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'review_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'eid',
		'author'			=> 'mid',
		'author_name'		=> 'author_name',
		'content'			=> 'text',
		'date'				=> 'date',
		'ip_address'		=> 'ip',
		'edit_time'			=> 'edit_time',
		'edit_member_name'	=> 'edit_name',
		'edit_show'			=> 'append_edit',
		'rating'			=> 'rating',
		'votes_total'		=> 'votes',
		'votes_helpful'		=> 'votes_helpful',
		'votes_data'		=> 'votes_data',
		'approved'			=> 'approved',
		'author_response'	=> 'author_response',
	);
	
	/**
	 * @brief	Application
	 */
	public static $application = 'calendar';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'calendar_event_review';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'calendar';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'calendar-events-rev';
	
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
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'commentRows' );
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
		return \IPS\calendar\Event\Comment::searchResultSnippet( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $view );
	}
	
	/**
	 * Reaction Type
	 *
	 * @return	string
	 */
	public static function reactionType()
	{
		return 'review_id';
	}

	/**
	 * Get content for embed
	 *
	 * @param	array	$params	Additional parameters to add to URL
	 * @return	string
	 */
	public function embedContent( $params )
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'calendar', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'calendar' )->embedEventReview( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}
}
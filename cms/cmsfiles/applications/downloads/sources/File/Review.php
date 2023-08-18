<?php
/**
 * @brief		File Review Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		4 Nov 2013
 */

namespace IPS\downloads\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Review Model
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
	public static $itemClass = 'IPS\downloads\File';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'downloads_reviews';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'review_';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'fid',
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
	public static $application = 'downloads';
	
	/**
	 * @brief	Title
	 */
	public static $title = 'downloads_file_review';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'download';
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'downloads-files-rev';
	
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
		return \IPS\downloads\File\Comment::searchResultSnippet( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $view );
	}
	
	/**
	 * Reaction type
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
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'embed.css', 'downloads', 'front' ) );
		return \IPS\Theme::i()->getTemplate( 'global', 'downloads' )->embedFileReview( $this, $this->item(), $this->url()->setQueryString( $params ) );
	}
}
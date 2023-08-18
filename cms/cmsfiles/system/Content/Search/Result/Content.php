<?php
/**
 * @brief		Search Result from Index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Sep 2015
*/

namespace IPS\Content\Search\Result;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search Result
 */
class _Content extends \IPS\Content\Search\Result
{
	/**
	 * Index Data
	 */
	protected $indexData;
	
	/**
	 * Author Data
	 */
	protected $authorData;
	
	/**
	 * Item Data
	 */
	protected $itemData;
	
	/**
	 * Author Data
	 */
	protected $containerData;
	
	/**
	 * Review Rating
	 */
	protected $reviewRating;
	
	/**
	 * If the user has posted in the item
	 */
	protected $iPostedIn;
	
	/**
	 * Constructor
	 *
	 * @param	array		$indexData		Data from index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	array		$reactions		Reaction Data
	 * @return	void
	 */
	public function __construct( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating = NULL, $iPostedIn = FALSE, $reactions=array() )
	{
		$this->createdDate = \IPS\DateTime::ts( $indexData['index_date_created'] );
		$this->lastUpdatedDate = \IPS\DateTime::ts( $indexData['index_date_updated'] );
		$this->indexData = $indexData;
		$this->authorData = $authorData;
		$this->itemData = $itemData;
		$this->containerData = $containerData;
		$this->reputationData = $reputationData;
		$this->reviewRating = $reviewRating;
		$this->iPostedIn = $iPostedIn;
		$this->reactions = $reactions;
	}
	
	/**
	 * HTML
	 *
	 * @param	string	$view	'expanded' or 'condensed'
	 * @param	bool	$asItem	Displaying results as items?
	 * @param	bool	$canIgnoreComments	Can ignore comments in the result stream? Activity stream can, but search results cannot.
	 * @param	array|NULL	$template	Optional custom template
	 * @return	string
	 */
	public function html( $view = 'expanded', $asItem = FALSE, $canIgnoreComments=FALSE, $template=NULL )
	{
		$searchResultTemplate = array( $this->indexData['index_class'], 'searchResult' );
		return $searchResultTemplate( $this->indexData, $this->authorData, $this->itemData, $this->containerData, $this->reputationData, $this->reviewRating, $this->iPostedIn, $view, $asItem, $canIgnoreComments, $template, $this->reactions );
	}
	
	/**
	 * Return search index data as an array
	 *
	 * @return array( 'indexData' => array() ... )
	 */
	public function asArray()
	{
		return array(
			'indexData' => $this->indexData,
			'authorData' => $this->authorData,
			'itemData' => $this->itemData,
			'containerData' => $this->containerData,
			'reputationData' => $this->reputationData,
			'reviewRating' => $this->reviewRating,
			'iPostedIn' => $this->iPostedIn
		);
	}
	
	/**
	 * Add to RSS feed
	 *
	 * @param	\IPS\Xml\Rss	$document	Document to add to
	 * @return	string
	 */
	public function addToRssFeed( \IPS\Xml\Rss $document )
	{
		try
		{
			$class = $this->indexData['index_class'];
			$object = $class::load( $this->indexData['index_object_id'] );
			$enclosure = NULL;

			if ( $images = $object->contentImages(1) )
			{
				$data = array_pop( $images );
				$key = key( $data );

				try
				{
					$enclosure = \IPS\File::get( $key, $data[ $key ] );
				}
				catch( \Exception $ex ) { }
			}

			$document->addItem( $object instanceof \IPS\Content\Comment ? $object->item()->searchIndexTitle() : $object->searchIndexTitle(), $object->url(), \IPS\Content\Search\Result::preDisplay( $this->indexData['index_content'] ), \IPS\DateTime::ts( $this->indexData['index_date_created'] ), NULL, $enclosure );
		}
		/* If the search result was orphaned, let us continue */
		catch( \OutOfRangeException $e ){}
	}
}
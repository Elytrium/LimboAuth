<?php
/**
 * @brief		Pages Page Item Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		15 Dec 2017
 */

namespace IPS\cms\Pages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Package Item Model
 */
class _PageItem extends \IPS\Content\Item implements \IPS\Content\Searchable
{
	/**
	 * @brief	Application
	 */
	public static $application = 'cms';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'pages';
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'cms_pages';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'page_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
			
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(

	);
	
	/**
	 * @brief	Title
	 */
	public static $title = 'cms_page';
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'files-o';
	
	/**
	 * @brief	Include In Sitemap
	 */
	public static $includeInSitemap = FALSE;
	
	/**
	 * @brief	Can this content be moderated normally from the front-end (will be FALSE for things like Pages and Commerce Products)
	 */
	public static $canBeModeratedFromFrontend = FALSE;
	
	/**
	 * Columns needed to query for search result / stream view
	 *
	 * @return	array
	 */
	public static function basicDataColumns()
	{
		return array( 'page_id', 'page_folder_id', 'page_full_path', 'page_default' );
	}
	
	/**
	 * Get URL from index data
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	string|NULL	$action			Action
	 * @return	\IPS\Http\Url
	 */
	public static function urlFromIndexData( $indexData, $itemData, $action = NULL )
	{
		if ( \IPS\Application::load('cms')->default AND $itemData['page_default'] AND !$itemData['page_folder_id'] )
		{
			/* No - that's easy */
			return \IPS\Http\Url::internal( '', 'front' );
		}
		else
		{
			return \IPS\Http\Url::internal( 'app=cms&module=pages&controller=page&path=' . $itemData['page_full_path'], 'front', 'content_page_path', array( $itemData['page_full_path'] ) );
		}
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @param	array		$indexData		Data from the search index
	 * @param	array		$authorData		Basic data about the author. Only includes columns returned by \IPS\Member::columnsForPhoto()
	 * @param	array		$itemData		Basic data about the item. Only includes columns returned by item::basicDataColumns()
	 * @param	array|NULL	$containerData	Basic data about the container. Only includes columns returned by container::basicDataColumns()
	 * @param	array		$reputationData	Array of people who have given reputation and the reputation they gave
	 * @param	int|NULL	$reviewRating	If this is a review, the rating
	 * @param	bool		$iPostedIn		If the user has posted in the item
	 * @param	string		$view			'expanded' or 'condensed'
	 * @param	bool		$asItem	Displaying results as items?
	 * @param	bool		$canIgnoreComments	Can ignore comments in the result stream? Activity stream can, but search results cannot.
	 * @param	array		$template	Optional custom template
	 * @param	array		$reactions	Reaction Data
	 * @return	string
	 */
	public static function searchResult( array $indexData, array $authorData, array $itemData, ?array $containerData, array $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments=FALSE, $template=NULL, $reactions=array() )
	{
		$indexData['index_title'] = \IPS\Member::loggedIn()->language()->addToStack( 'cms_page_' . $indexData['index_item_id'] );
		return parent::searchResult( $indexData, $authorData, $itemData, $containerData, $reputationData, $reviewRating, $iPostedIn, $view, $asItem, $canIgnoreComments, $template, $reactions );
	}
		
	/**
	 * Title for search index
	 *
	 * @return	string
	 */
	public function searchIndexTitle()
	{
		$titles = array();
		foreach ( \IPS\Lang::languages() as $lang )
		{
			$titles[] = $lang->get("cms_page_{$this->id}");
		}
		return implode( ' ', $titles );
	}
	
	/**
	 * Content for search index
	 *
	 * @return	string
	 */
	public function searchIndexContent()
	{
		if ( $this->type == 'builder' )
		{
			$content = array();
			foreach( \IPS\Db::i()->select( '*', 'cms_page_widget_areas', array( 'area_page_id=?', $this->id ) ) as $widgetArea )
			{
				foreach ( json_decode( $widgetArea['area_widgets'], TRUE ) as $widget )
				{
					if ( $widget['app'] == 'cms' and $widget['key'] == 'Wysiwyg' )
					{
						$content[] = trim( $widget['configuration']['content'] );
					}
				}
			}
			return implode( ' ', $content );
		}
		else
		{
			/* Remove {tags="foo"} */
			$this->content = preg_replace( '#\{([a-z]+?=([\'"]).+?\\2 ?+)}#', '', $this->content );

			/* Convert {{}} logic into html tags */
			$this->content = preg_replace( '#{{(if|foreach)([^{]+?)}}#', '<ips$1 data="$2">', $this->content );
			$this->content = preg_replace( '#{{end(if|foreach)}}#', '</ips$1>', $this->content );

			/* Remove custom PHP {{$foo = $this->test();}} */
			$this->content = preg_replace( '#{{(.+?)}}#', '', $this->content );

			$source = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$source->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $this->content ) );

			/* And then remove the HTML logic */
			$domElemsToRemove = array();
			foreach ( $source->getElementsByTagname('ipsif') as $domElement )
			{
				$domElemsToRemove[] = $domElement;
			}

			foreach ( $source->getElementsByTagname('ipsforeach') as $domElement )
			{
				$domElemsToRemove[] = $domElement;
			}

			foreach( $domElemsToRemove as $domElement )
			{
				$domElement->parentNode->removeChild($domElement);
			}

			$content = \IPS\Text\DOMParser::getDocumentBodyContents( $source );

			return $content;
		}
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		try
		{
			return \IPS\Db::i()->select( 'perm_view', 'core_permission_index', array( "app='cms' AND perm_type='pages' AND perm_type_id=?", $this->id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return '';
		}
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
}
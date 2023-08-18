<?php
/**
 * @brief		Tree Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Feb 2013
 */

namespace IPS\Helpers\Tree;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Tree Table
 */
class _Tree
{
	/**
	 * @brief	Title for tree table
	 */
	public $title = '';
	
	/**
	 * @brief	URL where the tree table is displayed
	 */
	public $url = '';
	
	/**
	 * @brief	Callback function to get the root rows
	 */
	public $getRoots;
	
	/**
	 * @brief	Callback function to get a single row by ID
	 */
	public $getRow;
	
	/**
	 * @brief	Callback function to get the parent ID for a row
	 */
	public $getRowParentId;
	
	/**
	 * @brief	Callback function to get the child rows for a row
	 */
	public $getChildren;
	
	/**
	 * @brief	Searchable?
	 */
	public $searchable = FALSE;
	
	/**
	 * @brief	If true, will prevent any item from being moved out of its current parent, only allowing them to be reordered within their current parent
	 */
	protected $lockParents = FALSE;
	
	/**
	 * @brief	If true, root cannot be turned into sub-items, and other items cannot be turned into roots
	 */
	protected $protectRoots = FALSE;
	
	/**
	 * @brief	Number of roots to show per page (NULL for unlimited). In most cases this doesn't make sense, since it makes re-ordering impossible. But for trees which are not orderable and which may contain a lot of roots, you can set this value
	 */
	public $rootsPerPage = NULL;

	/**
	 * @brief	If using $rootsPerPage, a callback function that returns the total number of roots
	 */
	public $getTotalRoots = NULL;
		
	/**
	 * Constructor
	 *
	 * @param	string			$url					URL where the tree table is displayed
	 * @param	string			$title					Tree Table title
	 * @param	callback		$getRoots				Callback function to get the root rows
	 * @param	callback		$getRow					Callback function to get a single row by ID
	 * @param	callback		$getRowParentId			Callback function to get the parent ID for a row
	 * @param	callback		$getChildren			Callback function to get the child rows for a row
	 * @param	callback|null	$getRootButtons			Callback function to get the root buttons
	 * @param	callback		$searchable				Show the search bar?
	 * @param	bool			$lockParents			If true, will prevent any item from being moved out of its current parent, only allowing them to be reordered within their current parent
	 * @param	bool			$protectRoots			If true, root cannot be turned into sub-items, and other items cannot be turned into roots
	 * @param	int|null		$rootsPerPage			Number of roots to show per page (NULL for unlimited). In most cases this doesn't make sense, since it makes re-ordering impossible. But for trees which are not orderable and which may contain a lot of roots, you can set this value
	 * @param	callback		$getTotalRoots			If using $rootsPerPage, a callback function that returns the total number of roots
	 * @return	void
	 */
	public function __construct( $url, $title, $getRoots, $getRow, $getRowParentId, $getChildren, $getRootButtons=NULL, $searchable=FALSE, $lockParents=FALSE, $protectRoots=FALSE, $rootsPerPage = NULL, $getTotalRoots = NULL )
	{
		$this->url = $url;
		$this->title = $title;
		$this->getRoots = $getRoots;
		$this->getRow = $getRow;
		$this->getRowParentId = $getRowParentId;
		$this->getChildren = $getChildren;
		$this->getRootButtons = $getRootButtons ?: function(){ return array(); };
		$this->searchable = $searchable;
		$this->lockParents = $lockParents;
		$this->protectRoots = $protectRoots;
		$this->rootsPerPage = $rootsPerPage;
		$this->getTotalRoots = $getTotalRoots;
	}
	
	/**
	 * Display Table
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			/* Get rows */
			$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
			$root = NULL;
			$rootParent = NULL;

			if( !\IPS\Request::i()->root )
			{
				$getRootsFunction = $this->getRoots;
				$rows = $getRootsFunction( $this->rootsPerPage ? array( ( $page - 1 ) * $this->rootsPerPage, $this->rootsPerPage ) : NULL );
			}
			else
			{
				$getChildrenFunction = $this->getChildren;
				$rows = $getChildrenFunction( \IPS\Request::i()->root );

				if ( \IPS\Request::i()->isAjax() )
				{
					return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->rows( $rows, mt_rand() );
				}
				
				$getRowFunction = $this->getRow;
				$root = $getRowFunction( \IPS\Request::i()->root, TRUE );
				$getRowParentIdFunction = $this->getRowParentId;
				$rootParent = $getRowParentIdFunction( \IPS\Request::i()->root );
			}
			
			/* Pagination? */
			$pagination = '';
			if ( $this->rootsPerPage )
			{
				$getTotalRootsFunction = $this->getTotalRoots;
				$totalNumber = $getTotalRootsFunction();
				if ( $totalNumber )
				{
					$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $this->url, ceil( $totalNumber / $this->rootsPerPage ), $page, $this->rootsPerPage );
				}
			}
										
			/* Display */
			$getRootButtonsFunction = $this->getRootButtons;
			return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->template( $this->url, $this->title, $root, $rootParent, $rows, $getRootButtonsFunction(), $this->lockParents, $this->protectRoots, $this->searchable, $pagination );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
}
<?php
/**
 * @brief		Table Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Helpers\Table;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

const SEARCH_CUSTOM = 0;
const SEARCH_CONTAINS_TEXT = 1;
const SEARCH_DATE_RANGE = 2;
const SEARCH_SELECT = 3;
const SEARCH_MEMBER = 4;
const SEARCH_NODE = 5;
const SEARCH_NUMERIC = 6;
const SEARCH_BOOL = 7;
const HEADER = 8;
const SEARCH_RADIO = 9;
const SEARCH_NUMERIC_TEXT = 10;
const SEARCH_QUERY_TEXT = 11;
const SEARCH_CHECKBOX = 12;

/**
 * List Table Builder
 */
abstract class _Table
{
	/**
	 * @brief	Base URL of the page the list table is on
	 */
	public $baseUrl;
	
	/**
	 * @brief	Elements to include (defaults to all - can use either this or $exclude)
	 */
	public $include = NULL;
	
	/**
	 * @brief	Elements to exclude (defaults to none - can use either this or $include)
	 */
	public $exclude = NULL;

	/**
	 * @brief	Column to sort results by
	 */
	public $sortBy;
	
	/**
	 * @brief	Sort Direction - "asc" or "desc"
	 */
	public $sortDirection = NULL;
	
	/**
	 * @brief	Columns that are not sortable
	 */
	public $noSort = array();

	/**
	 * @brief	Default column to sort results by
	 */
	public $defaultSortBy;

	/**
	 * @brief	Default sort direction - "asc" or "desc"
	 */
	public $defaultSortDirection = NULL;
	
	/**
	 * @brief	Filters
	 */
	public $filters = array();
	
	/**
	 * @brief	Current filter
	 */
	public $filter = NULL;
	
	/**
	 * @brief	Field to enable quick search on
	 */
	public $quickSearch = NULL;

	/**
	 * @brief	Whether to show advanced search/sort button or not
	 * @note	It is possible to recreate the form external to the helper (e.g. search) in which case you may not want to show the button
	 */
	public $showAdvancedSearch	= TRUE;

	/**
	 * @brief	Whether to use the placeholder loading style with this table
	 */
	public $dummyLoading = FALSE;

	/**
	 * @brief	Fields to enable advanced sarch on
	 * @note	Keys are the field names. Values are a IPS\Helpers\Table\SEARCH_* constant
	 */
	public $advancedSearch = array();
	
	/**
	 * @brief	Number of records to show
	 */
	public $limit = 25;
	
	/**
	 * @brief	Number of pages
	 * @see		\IPS\Helpers\Table\getRows
	 */
	public $pages = 1;
	
	/**
	 * @brief	Current Page
	 */
	public $page = 1;

	/**
	 * @brief	Pagination parameter
	 */
	protected $paginationKey	= 'page';

	/**
	 * @brief	Use simple pagination
	 */
	public $simplePagination	= FALSE;

	/**
	 * @brief	Table resort parameter
	 */
	public $resortKey			= 'listResort';
	
	/**
	 * @brief	Language prefix for column names
	 */
	public $langPrefix = '';
	
	/**
	 * @brief 	Language key for table title
	 */
	public $title = '';

	/**
	 * @brief 	Use realtime features within the table?
	 */
	public $enableRealtime = false;
	
	/**
	 * @brief	Parsers
	 * @code
	 	// Example of a parser that would convert value to uppercase:
	 	$parsers = array(
	 		'column_key'	=> function( $value )
	 		{
	 			return strtoupper( $value );
	 		}
	 	);
	 * @endcode
	 * @note	When implementing, note that this will override the default parser which runs htmlentities, necessary for preventing XSS
	 */
	public $parsers = array();

	/**
	 * @brief	Column to highlight as the "main" column (e.g. the title)
	 */
	public $mainColumn = NULL;
	
	/**
	 * @brief	Additional CSS classes to apply to the table
	 */
	public $classes = array();

	/**
	 * @brief	Additional CSS classes to apply to individual columns
	 */
	public $rowClasses = array();
	
	/**
	 * @brief	Rows to highlight
	 */
	public $highlightRows = array();
	
	/**
	 * @brief	Buttons to show on the "root row"
	 * @code
	 	array(
	 		array(
	 			'icon'	=>	array(
	 				'icon.png'			// Path to icon
	 				'core'				// Application icon belongs to
	 			),
	 			'title'	=> 'foo',		// Language key to use for button's title parameter
	 			'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	 			'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	 		),
	 		...							// Additional buttons
	 	);
	 * @endcode
	 */
	public $rootButtons = NULL;
	
	/**
	 * @brief	Callback function to get buttons for a record
	 * @code
	 	$rowButtons = function( $row )
	 	{
	 		return array( ... ); // Same format as IPS\Helpers\Table::$rootButtons
	 	}
	 * @endcode
	 */
	public $rowButtons = NULL;
	
	/**
	 * @brief	Column widths (in percentages)
	 */
	public $widths = array();
	
	/**
	 * @brief	Template for table
	 */
	public $tableTemplate;
	
	/**
	 * @brief	Template for rows
	 */
	public $rowsTemplate;
	
	/**
	 * @brief	Sort options (used only on front-end)
	 */
	public $sortOptions;
	
	/**
	 * @brief	Unique ID for this table
	 */
	public $uniqueId;
	
	/**
	 * @brief 	Extra HTML to show below filter/search bar
	 */
	public $extraHtml = '';
	
	/**
	 * @brief 	Extra Data
	 */
	public $extra = NULL;
	
	/**
	 * @brief  Store advanced search values
	 */
	protected $advancedSearchValues = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$baseUrl	Base URL of the page the list table is on
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $baseUrl )
	{
		/* Set page */
		$parameter	= $this->paginationKey;

		if ( \IPS\Request::i()->$parameter )
		{
			$this->page = \intval( \IPS\Request::i()->$parameter );

			if ( !$this->page OR $this->page < 1 )
			{
				$this->page = 1;
			}
		}
		
		/* Set sort options */
		if( \IPS\Request::i()->sortby )
		{
			$this->sortBy = \IPS\Request::i()->sortby;
		}
		if( \IPS\Request::i()->sortdirection )
		{
			$this->sortDirection = ( mb_strtolower( \IPS\Request::i()->sortdirection ) === 'desc' or mb_strtolower( \IPS\Request::i()->sortdirection ) === 'asc' ) ? mb_strtolower( \IPS\Request::i()->sortdirection ) : NULL;
		}

		/* Filter? */
		if ( \IPS\Request::i()->filter )
		{
			$this->filter = \IPS\Request::i()->filter;
		}
		
		/* Set base URL */
		$this->baseUrl = $baseUrl->setQueryString( array( 'filter' => $this->filter, 'sortby' => $this->sortBy, 'sortdirection' => $this->sortDirection ) )->setPage( $this->paginationKey, $this->page );
		
		/* Templates */
		$this->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'tables', 'core' ), 'table' );
		$this->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'tables', 'core' ), 'rows' );

		/* Create a unique id used by the template - can be overriden manually if desired */
		$this->uniqueId	= md5( mt_rand() );
	}

	/**
	 * Retrieve the pagination key
	 *
	 * @return	string
	 */
	public function getPaginationKey()
	{
		return $this->paginationKey;
	}

	/**
	 * Setting the page parameter means we need to recalculate the pages
	 *
	 * @param	string	$property	Property we are updating
	 * @param	mixed	$value		Value being set
	 * @return	void
	 */
	public function __set( $property, $value )
	{
		if( $property == 'paginationKey' )
		{
			$this->baseUrl			= $this->baseUrl->stripQueryString( $this->paginationKey );
			$this->paginationKey	= $value;

			if ( \IPS\Request::i()->$value )
			{
				$this->page = \intval( \IPS\Request::i()->$value );
				if ( !$this->page OR $this->page < 1 )
				{
					$this->page = 1;
				}
			}

			$this->baseUrl	= $this->baseUrl->setQueryString( array( $value => $this->page ) );
		}
	}
	
	/**
	 * Return the column without a table prefix
	 * For example, we might allow array( 'last_post', 'author' ) but the $this->sortBy property is often set as "app_table.last_post" meaning
	 * that the filter drop down doesn't show the selected value correctly.
	 *
	 * @return	string
	 */
	public function getSortByColumn()
	{
		if ( mb_strpos( $this->sortBy, '.' ) )
		{
			list( $table, $column ) = explode( '.', $this->sortBy );
			return $column;
		}
		else
		{
			return $this->sortBy;
		}
	}
	
	/**
	 * Build Advanced Search Form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	protected function advancedSearch()
	{
		$form = new \IPS\Helpers\Form( 'advanced_search', 'search', $this->baseUrl, array( 'data-role' => 'advancedSearch' ) );
		$form->hiddenValues['filter']		 = \IPS\Request::i()->filter;
		$form->hiddenValues['sortby']		 = \IPS\Request::i()->sortby;
		$form->hiddenValues['sortdirection'] = \IPS\Request::i()->sortdirection;

		foreach ( $this->advancedSearch as $k => $type )
		{
			$options = array();
			if ( \is_array( $type ) )
			{
				$options = $type[1];
				$type = $type[0];
			}
		
			switch ( $type )
			{
				case SEARCH_CUSTOM:
					$form->add( new \IPS\Helpers\Form\Custom( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;
				
				case SEARCH_CONTAINS_TEXT:
					$form->add( new \IPS\Helpers\Form\Text( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;
					
				case SEARCH_QUERY_TEXT:
					$form->add( new \IPS\Helpers\Form\Custom( $this->langPrefix . $k, NULL, FALSE, array(
						'getHtml'	=> function( $element )
						{
							return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( "{$element->name}[0]", ( \is_array( $element->value ) AND isset( $element->value[0] ) ) ? $element->value[0] : NULL, $element->required, array(
								'c'	 => \IPS\Member::loggedIn()->language()->addToStack('contains'),
								'bw' => \IPS\Member::loggedIn()->language()->addToStack('begins_with'),
								'eq' => \IPS\Member::loggedIn()->language()->addToStack('exactly'),
							) )
							. ' '
							. \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( "{$element->name}[1]", 'text', ( \is_array( $element->value ) AND isset( $element->value[1] ) ) ? $element->value[1] : NULL, $element->required, NULL, FALSE, NULL, NULL, NULL, '', NULL, FALSE, NULL, array(), array(), array( $element->name . '-qty' ) );
						}
					) ) );
					break;
					
				case SEARCH_DATE_RANGE:
					$form->add( new \IPS\Helpers\Form\DateRange( $this->langPrefix . $k, array( 'start' => '', 'end' => '' ), FALSE, $options ) );
					break;
				
				case SEARCH_SELECT:
					$form->add( new \IPS\Helpers\Form\Select( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;

				case SEARCH_RADIO:
					$form->add( new \IPS\Helpers\Form\Radio( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;
					
				case SEARCH_MEMBER:
					$form->add( new \IPS\Helpers\Form\Member( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;
					
				case SEARCH_NODE:
					$form->add( new \IPS\Helpers\Form\Node( $this->langPrefix . $k, 0, FALSE, $options ) );
					break;
					
				case SEARCH_NUMERIC:
				case SEARCH_NUMERIC_TEXT:
					$form->add( new \IPS\Helpers\Form\Custom( $this->langPrefix . $k, NULL, FALSE, array(
						'getHtml'	=> function( $element )
						{
							return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( "{$element->name}[0]", ( \is_array( $element->value ) AND isset( $element->value[0] ) ) ? $element->value[0] : NULL, $element->required, array(
								'any'	=> \IPS\Member::loggedIn()->language()->addToStack('any'),
								'gt'	=> \IPS\Member::loggedIn()->language()->addToStack('gt'),
								'lt'	=> \IPS\Member::loggedIn()->language()->addToStack('lt'),
								'eq'	=> \IPS\Member::loggedIn()->language()->addToStack('exactly'),
							),
							FALSE,
							NULL,
							FALSE,
							array(
								'any'	=> array(),
								'gt'	=> array( $element->name . '-qty' ),
								'lt'	=> array( $element->name . '-qty' ),
								'eq'	=> array( $element->name . '-qty' ),
							) )
							. ' '
							. \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->number( "{$element->name}[1]", ( \is_array( $element->value ) AND isset( $element->value[1] ) ) ? $element->value[1] : NULL, $element->required, NULL, FALSE, NULL, NULL, NULL, 0, NULL, FALSE, NULL, array(), array(), array( $element->name . '-qty' ) );
						}
					) ) );
					break;
				
				case SEARCH_CHECKBOX:
					$form->add( new \IPS\Helpers\Form\Checkbox( $this->langPrefix . $k, NULL, FALSE, $options ) );
					break;	
				case SEARCH_BOOL:
					$form->add( new \IPS\Helpers\Form\YesNo( $this->langPrefix . $k, TRUE, FALSE, $options ) );
					break;
				case HEADER:
					$form->addHeader( $this->langPrefix . $k );
					break;
			}
		}

		return $form;
	}

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			/* Advanced Search */
			$advancedSearchValues 		= array();

			if ( !empty( $this->advancedSearch ) )
			{
				/* Are we displaying the advanced search form? */
				if ( \IPS\Request::i()->advancedSearchForm )
				{
					/* If we are showing just the advanced search form, send a noindex meta tag */
					\IPS\Output::i()->metaTags['robots']	= 'noindex';

					return (string) $this->advancedSearch();
				}
				/* No? Try getting some values then */
				else
				{
					$advancedSearchValues	= $this->getAdvancedSearchValues();
				}
			}

			/* Get rows */
			$rows = $this->getRows( $advancedSearchValues ) ?? array();

			/* Check we're on a valid page (must come after getRows() as this is where $this->pages is set) */
			if ( $this->page )
			{ 
				if ( $this->pages and $this->page > $this->pages )
				{
					\IPS\Output::i()->redirect( $this->baseUrl->setPage( $this->paginationKey, 1 ), NULL, 303 );
				} 
			}
			
			/* Add link tags */
			if ( $this->page != 1 )
			{
				if( !isset( \IPS\Output::i()->linkTags['canonical'] ) )
				{
					\IPS\Output::i()->linkTags['canonical'] = (string) $this->baseUrl->setPage( $this->paginationKey, $this->page );
				}
				
				\IPS\Output::i()->linkTags['first'] = (string) $this->baseUrl->setPage( $this->paginationKey, 1 );
				\IPS\Output::i()->linkTags['prev'] = (string) $this->baseUrl->setPage( $this->paginationKey, $this->page - 1 );
			}
			/* If we literally requested ?page=1 add canonical tag to get rid of the page query string param */
			elseif( isset( $this->baseUrl->data[ \IPS\Http\Url::COMPONENT_QUERY ][ $this->paginationKey ] ) )
			{
				\IPS\Output::i()->linkTags['canonical'] = (string) $this->baseUrl->setPage();
			}
			if ( $this->pages > $this->page )
			{
				\IPS\Output::i()->linkTags['next'] = (string) $this->baseUrl->setPage( $this->paginationKey, $this->page + 1 );
			}
			if ( $this->pages != $this->page )
			{
				\IPS\Output::i()->linkTags['last'] = (string) $this->baseUrl->setPage( $this->paginationKey, $this->pages );
			}
			
			/* No rows to show? Add a noindex but follow for crawling later to check if content exists */
			if ( ! \count( $rows ) )
			{
				\IPS\Output::i()->metaTags['robots'] = 'noindex, follow';
			}
			
			/* Get table headers */
			$headers = $this->getHeaders( $advancedSearchValues );
				
			/* If this is an AJAX request, just return them, with pagination */
			$resortKey	= $this->resortKey;
			if( \IPS\Request::i()->isAjax() and \IPS\Request::i()->$resortKey )
			{
				if ( \count( $rows ) )
				{
					$rowsTemplate = $this->rowsTemplate;
					$rowHtml = $rowsTemplate( $this, $headers, $rows, $this->mainColumn, $this->rootButtons, array() );
				}
				else
				{
					$rowHtml = \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' )->noRows();
				}
				
				\IPS\Output::i()->json( array( 'rows' => $rowHtml, 'pagination' => \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $this->baseUrl, $this->pages, $this->page, $this->limit, TRUE, $this->paginationKey, $this->simplePagination ), 'extraHtml' => $this->extraHtml ) );
			}
			/* Otherwise, show the full table */
			else
			{
				/* If there are no root buttons, but we have a callback for adding row buttons, make sure the column gets made */
				if( $this->rootButtons === NULL and $this->rowButtons !== NULL )
				{
					$this->rootButtons = array();
				}
				
				/* Add JS */
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );
				
				/* Build table */
				$tableTemplate = $this->tableTemplate;
				return $tableTemplate( $this, $headers, $rows, ( \is_array( $this->quickSearch ) ? $this->quickSearch[1] : $this->quickSearch ), !empty( $this->advancedSearch ) );
			}
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
	
	/**
	 * Convert the value of a search field into something we can put in the query string
	 *
	 * @param	array	$values	The values
	 * @return	array
	 */
	protected function _convertSearchValuesForQueryString( $values )
	{
		$return = array();
		foreach( $values as $k => $v )
		{
			if ( \is_array( $v ) )
			{
				$return[ $k ] = $this->_convertSearchValuesForQueryString( $v );
			}
			elseif ( $v instanceof \IPS\DateTime )
			{
				$return[ $k ] = $v->getTimestamp();
			}
			elseif ( $v instanceof \IPS\Member )
			{
				$return[ $k ] = $v->name;
			}
			elseif ( $v instanceof \IPS\Node\Model )
			{
				if ( isset( $this->advancedSearch[ mb_substr( $k, mb_strlen( $this->langPrefix ) ) ] ) AND 
					!( $v instanceof $this->advancedSearch[ mb_substr( $k, mb_strlen( $this->langPrefix ) ) ][1]['class'] ) )
				{
					$return[ $k ] = 's.' . $v->_id;
				}
				else
				{
					$return[ $k ] = $v->_id;
				}
			}
			else if( isset( $this->advancedSearch[ mb_substr( $k, mb_strlen( $this->langPrefix ) ) ][0] ) and $this->advancedSearch[ mb_substr( $k, mb_strlen( $this->langPrefix ) ) ][0] === \IPS\Helpers\Table\SEARCH_BOOL )
			{
				$return[ $k . '_checkbox' ] = $v;
			}
			else
			{
				$return[ $k ] = $v;
			}
		}

		return $return;
	}

	/**
	 * Get rows
	 *
	 * @param	array	$advancedSearchValues	Values from the advanced search form
	 * @return	array
	 */
	abstract public function getRows( $advancedSearchValues );

	/**
	 * Return the table headers
	 *
	 * @param	array|NULL	$advancedSearchValues	Advanced search values
	 * @return	array
	 */
	public function getHeaders( $advancedSearchValues )
	{
		/* Get headers */
		if ( empty( $this->include ) )
		{
			$headers = array();
			foreach ( $this->getRows( $advancedSearchValues ) as $row )
			{
				foreach ( array_keys( $row ) as $header )
				{
					$headers[ $header ] = $header;
				}
				break;
			}
		}
		else
		{
			if( !empty( $advancedSearchValues ) AND !isset( \IPS\Request::i()->noColumn ) )
			{
				$headers = array_combine( $this->include, $this->include );
				
				foreach ( $this->getRows( $advancedSearchValues ) as $row )
				{
					foreach ( array_keys( $row ) as $header )
					{
						$headers[ $header ] = $header;
					}
					break;
				}
			}
			else
			{
				$headers = $this->include;
			}
		}
		
		if ( $this->exclude !== NULL )
		{
			$headers = array_diff( $headers, $this->exclude );
		}
		
		if ( $this->rootButtons !== NULL or $this->rowButtons !== NULL )
		{
			$headers['_buttons'] = '_buttons';
		}

		return $headers;
	}
	
	/**
	 * Does the user have permission to use the multi-mod checkboxes?
	 *
	 * @param	string|null		$action		Specific action to check (hide/unhide, etc.) or NULL for a generic check
	 * @return	bool
	 */
	public function canModerate( $action=NULL )
	{
		return FALSE;
	}
	
	/**
	 * Get the advanced search values
	 *
	 * @return	array
	 */
	public function getAdvancedSearchValues()
	{
		/* Advanced Search */
		$advancedSearchValues 		= array();
		$advancedSearchValuesQuery	= array();

		/* Store these to prevent URL from being rebuilt on subsequent calls */
		$storeSearchValues = FALSE;

		if ( $this->advancedSearchValues === NULL )
		{
			$storeSearchValues = TRUE;
			$this->advancedSearchValues = array();
		}

		if ( !empty( $this->advancedSearch ) )
		{
			/* Are we displaying the advanced search form? */
			if ( \IPS\Request::i()->advancedSearchForm )
			{
				return (string) $this->advancedSearch();
			}
			/* No? Try getting some values then */
			elseif ( $values = $this->advancedSearch()->values() )
			{
				$form = $this->advancedSearch();

				/* Store these to prevent URL from being rebuilt on subsequent calls */
				if ( $storeSearchValues === TRUE )
				{
					foreach ( $values as $k => $v )
					{
						if( array_key_exists( $k, $form->hiddenValues ) )
						{
							continue;
						}

						if ( $v !== NULL )
						{
							$advancedSearchValuesQuery[ $k ] = $v;
							$this->advancedSearchValues[ mb_substr( $k, mb_strlen( $this->langPrefix ) ) ] = $v;
						}
					}
	
					if ( !empty( $this->advancedSearchValues ) )
					{
						$this->baseUrl = $this->baseUrl->setQueryString( array_merge( array( 'advanced_search_submitted' => 1, 'csrfKey' => \IPS\Session::i()->csrfKey ), $this->_convertSearchValuesForQueryString( $advancedSearchValuesQuery ) ) );
					}
				}
			}
		}

		return $this->advancedSearchValues;
	}

	/**
	 * Return the sort direction to use for links
	 *
	 * @note	Abstracted so other table helper instances can adjust as needed
	 * @param	string	$column		Sort by string
	 * @return	string [asc|desc]
	 */
	public function getSortDirection( $column )
	{
		/* If the column we are sorting by is the default sort, use the default sort direction */
		if ( $this->defaultSortBy AND $column == $this->defaultSortBy AND $this->defaultSortDirection )
		{
			return $this->defaultSortDirection;
		}

		if( $column == 'title' )
		{
			return 'asc';
		}

		return 'desc';
	}
}
<?php
/**
 * @brief		Search API
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Oct 2017
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Search API
 */
class _search extends \IPS\Api\Controller
{
	/**
	 * GET /core/search
	 * Perform a search and get a list of results
	 *
	 * @apiparam	int		page		Page number
	 * @apiparam	int		perPage		Number of results per page - defaults to 25
	 * @apiparam	string	q			String to search for
	 * @apiparam	string	tags		Comma-separated list of tags to search for
	 * @apiparam	string	type		Content type class to restrict searches to
	 * @apiparam	int		item		Restrict searches to comments or reviews made to the specified item
	 * @apiparam	string	nodes		Comma-separated list of node IDs to restrict searches to
	 * @apiparam	int		search_min_comments	Minimum number of comments search results must have for content types that support comments
	 * @apiparam	int		search_min_replies	Minimum number of comments search results must have for content types that require the first comment (e.g. topics)
	 * @apiparam	int		search_min_reviews	Minimum number of reviews search results must have
	 * @apiparam	int		search_min_views	Minimum number of views search results must have (note, not supported by elasticsearch)
	 * @apiparam	string	author		Restrict searches to results posted by this member (name)
	 * @apiparam	string	club		Comma-separated list of club IDs to restrict searches to
	 * @apiparam	string	start_before	Date period (from current time) that search results should start before
	 * @apiparam	string	start_after		Date period (from current time) that search results should start after
	 * @apiparam	string	updated_before	Date period (from current time) that search results should last be updated before
	 * @apiparam	string	updated_after	Date period (from current time) that search results should last be updated after
	 * @apiparam	string	sortby			Sort by method (newest or relevancy)
	 * @apiparam	string	eitherTermsOrTags	Whether to search both tags and search term ("and") or either ("or")
	 * @apiparam	string	search_and_or	Whether to perform an "and" search or an "or" search for all search terms
	 * @apiparam	string	search_in		Specify "titles" to search in titles only, otherwise titles and content are both searched
	 * @apiparam	int		search_as		Member ID to perform the search as (guest permissions will be used when this parameter is omitted)
	 * @apiparam	bool	doNotTrack		If doNotTrack is passed with a value of 1, the search will not be tracked for statistical purposes
	 * @return		array
	 * @apiresponse	int		page		api_int_page
	 * @apiresponse	int		perPage		api_int_perpage
	 * @apiresponse	int		totalResults	api_int_totalresults
	 * @apiresponse	int		totalPages	api_int_totalpages
	 * @apiresponse	[\IPS\Content\Search\Result]	results		api_results_thispage
	 * @note	For requests using an OAuth Access Token for a particular member, only content the authorized user can view will be included and the "search_as" parameter will be ignored.
	 */
	public function GETindex()
	{
		$memberPermissions = $this->member;

		if( !$this->member AND isset( \IPS\Request::i()->search_as ) )
		{
			$memberPermissions = \IPS\Member::load( \IPS\Request::i()->search_as );

			if( !$memberPermissions->member_id )
			{
				$memberPermissions = NULL;
			}
		}

		/* Get valid content types */
		$contentTypes = \IPS\core\modules\front\search\search::contentTypes( $memberPermissions ?: TRUE );

		/* Initialize search */
		$query = \IPS\Content\Search\Query::init( $memberPermissions ?: NULL );

		/* Set content type */
		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $contentTypes ) )
		{	
			if ( isset( \IPS\Request::i()->item ) )
			{
				$class = $contentTypes[ \IPS\Request::i()->type ];
				try
				{
					$item = $class::loadAndCheckPerms( \IPS\Request::i()->item );
					$query->filterByContent( array( \IPS\Content\Search\ContentFilter::init( $class )->onlyInItems( array( \IPS\Request::i()->item ) ) ) );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			else
			{
				$filter = \IPS\Content\Search\ContentFilter::init( $contentTypes[ \IPS\Request::i()->type ] );
				
				if ( isset( \IPS\Request::i()->nodes ) )
				{
					$filter->onlyInContainers( explode( ',', \IPS\Request::i()->nodes ) );
				}
				
				if ( isset( \IPS\Request::i()->search_min_comments ) )
				{
					$filter->minimumComments(  \IPS\Request::i()->search_min_comments );
				}
				if ( isset( \IPS\Request::i()->search_min_replies ) )
				{
					$filter->minimumComments(  \IPS\Request::i()->search_min_replies + 1 );
				}
				if ( isset( \IPS\Request::i()->search_min_reviews ) )
				{
					$filter->minimumReviews(  \IPS\Request::i()->search_min_reviews );
				}
				if ( isset( \IPS\Request::i()->search_min_views ) )
				{
					$filter->minimumViews(  \IPS\Request::i()->search_min_views );
				}
				
				$query->filterByContent( array( $filter ) );
			}
		}
		
		/* Filter by author */
		if ( isset( \IPS\Request::i()->author ) )
		{
			$author = \IPS\Member::load( \IPS\Request::i()->author, 'name' );
			if ( $author->member_id )
			{
				$query->filterByAuthor( $author );
			}
		}
		
		/* Filter by club */
		if ( isset( \IPS\Request::i()->club ) AND \IPS\Settings::i()->clubs )
		{
			$query->filterByClub( explode( ',', \IPS\Request::i()->club ) );
		}
		
		/* Set time cutoffs */
		foreach ( array( 'start' => 'filterByCreateDate', 'updated' => 'filterByLastUpdatedDate' ) as $k => $method )
		{
			$beforeKey = "{$k}_before";
			$afterKey = "{$k}_after";
			if ( isset( \IPS\Request::i()->$beforeKey ) or isset( \IPS\Request::i()->$afterKey ) )
			{
				foreach ( array( 'before', 'after' ) as $l )
				{
					$$l = NULL;
					$key = "{$l}Key";
					if ( isset( \IPS\Request::i()->$$key ) AND \IPS\Request::i()->$$key != 'any' )
					{
						switch ( \IPS\Request::i()->$$key )
						{
							case 'day':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) );
								break;
								
							case 'week':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) );
								break;
								
							case 'month':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) );
								break;
								
							case 'six_months':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P6M' ) );
								break;
								
							case 'year':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) );
								break;
								
							default:
								$$l = \IPS\DateTime::ts( \IPS\Request::i()->$$key );
								break;
						}
					}
				}
				
				$query->$method( $after, $before );
			}
		}

		/* Set Order */
		if ( ! isset( \IPS\Request::i()->sortby ) )
		{
			\IPS\Request::i()->sortby = $query->getDefaultSortMethod();
		}
		
		switch( \IPS\Request::i()->sortby )
		{
			case 'newest':
				$query->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED );
				break;

			case 'relevancy':
				$query->setOrder( \IPS\Content\Search\Query::ORDER_RELEVANCY );
				break;
		}

		$flags = ( isset( \IPS\Request::i()->eitherTermsOrTags ) and \IPS\Request::i()->eitherTermsOrTags === 'and' ) ? \IPS\Content\Search\Query::TERM_AND_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS;
		$operator = NULL;
		
		if ( isset( \IPS\Request::i()->search_and_or ) and \in_array( \IPS\Request::i()->search_and_or, array( \IPS\Content\Search\Query::OPERATOR_OR, \IPS\Content\Search\Query::OPERATOR_AND ) ) )
		{
			$operator = \IPS\Request::i()->search_and_or;
		}
		
		if ( isset( \IPS\Request::i()->search_in ) and \IPS\Request::i()->search_in === 'titles' )
		{
			$flags = $flags | \IPS\Content\Search\Query::TERM_TITLES_ONLY;
		}

		/* Return */
		return new \IPS\Content\Search\ApiResponse(
			200,
			array( $query, $flags, isset( \IPS\Request::i()->q ) ? ( \IPS\Request::i()->q ) : NULL, isset( \IPS\Request::i()->tags ) ? explode( ',', \IPS\Request::i()->tags ) : NULL, $operator ),
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			NULL,
			0,
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * GET /core/search/contenttypes
	 * Get list of content types that can be searched
	 *
	 * @clientapiparam	int	search_as	Member ID to perform the search as (by default, search results are based on guest permissions)
	 * @return		array
	 * @apiresponse	array	contenttypes	Content types that can be used in /search requests in the 'type' parameter
	 */
	public function GETitem()
	{
		$memberPermissions = $this->member;

		if( !$this->member AND isset( \IPS\Request::i()->search_as ) )
		{
			$memberPermissions = \IPS\Member::load( \IPS\Request::i()->search_as );

			if( !$memberPermissions->member_id )
			{
				$memberPermissions = NULL;
			}
		}

		return new \IPS\Api\Response( 200, array( 'contenttypes' => array_keys( \IPS\core\modules\front\search\search::contentTypes( $memberPermissions ?: TRUE ) ) ) );
	}
}
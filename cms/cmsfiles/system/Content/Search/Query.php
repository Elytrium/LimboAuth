<?php
/**
 * @brief		Abstract Search Query
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2014
*/

namespace IPS\Content\Search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Search Query
 */
abstract class _Query
{
	const TERM_OR_TAGS = 1; // If both tags and a term are provided, match results where either the tags or the term match
	const TERM_AND_TAGS = 2; // If both tags and a term are provided, match only results where BOTH the tags or the term match
	const TERM_TITLES_ONLY  = 8; // If this flag is set, only titles will be searched
	const TAGS_MATCH_ITEMS_ONLY = 16;
	
	const OPERATOR_OR = 'or';
	const OPERATOR_AND = 'and';
	
	const HIDDEN_VISIBLE = 0;
	const HIDDEN_UNAPPROVED = 1;
	const HIDDEN_HIDDEN = -1;
	const HIDDEN_PARENT_HIDDEN = 2;
	
	const ORDER_NEWEST_UPDATED = 1;
	const ORDER_NEWEST_CREATED = 2;
	const ORDER_RELEVANCY = 3;
	const ORDER_OLDEST_UPDATED = 4;
	const ORDER_OLDEST_CREATED = 5;
	const ORDER_NEWEST_COMMENTED = 6;
	
	const SUPPORTS_JOIN_FILTERS = TRUE;
		
	/**
	 * Create new query
	 *
	 * @param	\IPS\Member	$member	The member performing the search (NULL for currently logged in member)
	 * @return	\IPS\Content\Search\Query
	 */
	public static function init( \IPS\Member $member = NULL )
	{
		if ( \IPS\Settings::i()->search_method == 'elastic' )
		{
			return new \IPS\Content\Search\Elastic\Query( $member ?: \IPS\Member::loggedIn(), \IPS\Http\Url::external( rtrim( \IPS\Settings::i()->search_elastic_server, '/' ) . '/' . \IPS\Settings::i()->search_elastic_index ) );
		}
		else
		{
			return new \IPS\Content\Search\Mysql\Query( $member ?: \IPS\Member::loggedIn() );
		}
	}
		
	/**
	 * @brief	Number of results to get
	 */
	public $resultsToGet = 25;
	
	/**
	 * @brief	The member performing the search
	 */
	protected $member;
				
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	The member performing the search
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
		
		/* Exclude hidden items */
		if ( !$member->modPermission('can_view_hidden_content') )
		{
			$this->setHiddenFilter( static::HIDDEN_VISIBLE );
		}
		
		/* Exclude disabled applications */
		$filters = array();
		foreach ( \IPS\Content::routedClasses( $member, FALSE, TRUE ) as $class )
		{
			$filters[] = \IPS\Content\Search\ContentFilter::init( $class );
		}
		if ( !empty( $filters ) )
		{
			$this->filterByContent( $filters );
		}
	}
					
	/**
	 * Filter by multiple content types
	 *
	 * @param	array	$contentFilters	Array of \IPS\Content\Search\ContentFilter objects
	 * @param	bool	$type			TRUE means only include results matching the filters, FALSE means exclude all results matching the filters
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByContent( array $contentFilters, $type = TRUE );
		
	/**
	 * Filter by author
	 *
	 * @param	\IPS\Member|int|array	$author						The author, or an array of author IDs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByAuthor( $author );
	
	/**
	 * Filter by club
	 *
	 * @param	\IPS\Member\Club|int|array	$club	The club, or array of club IDs
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByClub( $club );
	
	/**
	 * Filter for profile
	 *
	 * @param	\IPS\Member	$member	The member whose profile is being viewed
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterForProfile( \IPS\Member $member );
	
	/**
	 * Filter by item author
	 *
	 * @param	\IPS\Member	$author		The author
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByItemAuthor( \IPS\Member $author );
	
	/**
	 * Filter by container class
	 *
	 * @param	array	$classes	Container classes to exclude from results.
	 * @param	array	$exclude	Content classes to exclude from the filter. For cases where multiple content classes may have the same container class such as Gallery images, comments and reviews.
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByContainerClasses( $classes=array(), $exclude=array() );
	
	/**
	 * Filter by content the user follows
	 *
	 * @param	bool	$includeContainers	Include content in containers the user follows?
	 * @param	bool	$includeItems		Include items and comments/reviews on items the user follows?
	 * @param	bool	$includeMembers		Include content posted by members the user follows?
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByFollowed( $includeContainers, $includeItems, $includeMembers );
	
	/**
	 * Filter by content the user has posted in
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByItemsIPostedIn();
	
	/**
	 * Filter by content the user has not read
	 *
	 * @note	If applicable, it is more efficient to call filterByContent() before calling this method
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByUnread();

	/**
	 * Filter by content the user has not read
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterBySolved();

	/**
	 * Filter by content the user has not read
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByUnsolved();
	
	/**
	 * Filter by start date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByCreateDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL );
	
	/**
	 * Filter by last updated date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByLastUpdatedDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL );
	
	/**
	 * Set hidden status
	 *
	 * @param	int|array|NULL	$statuses	The statuses (see HIDDEN_ constants) or NULL for any
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setHiddenFilter( $statuses );
	
	/**
	 * Set limit
	 *
	 * @param	int		$limit	Number per page
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setLimit( $limit )
	{
		$this->resultsToGet = $limit;
		return $this;
	}
	
	/**
	 * Set page
	 *
	 * @param	int		$page	The page number
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setPage( $page );
	
	/**
	 * Set order
	 *
	 * @param	int		$order	Order (see ORDER_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setOrder( $order );
	
	/**
	 * Permission Array
	 *
	 * @return	array
	 */
	public function permissionArray()
	{
		return $this->member->permissionArray();
	}
	
	/**
	 * Search
	 *
	 * @param	string|null	$term		The term to search for
	 * @param	array|null	$tags		The tags to search for
	 * @param	int			$method 	See \IPS\Content\Search\Query::TERM_* contants
	 * @param	string|null	$operator	If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms. NULL will go to admin-defined setting
	 * @return	\IPS\Content\Search\Results
	 */
	abstract public function search( $term = NULL, $tags = NULL, $method = 1, $operator = NULL );
	
	/**
	 * Get the default date cut off
	 *
	 * @return string
	 */
	public function getDefaultDateCutOff()
	{
		return 'any';
	}
	
	/**
	 * Get the default sort method
	 *
	 * @return string
	 */
	public function getDefaultSortMethod()
	{
		return 'relevancy';
	}
	
	/**
	 * Get count
	 *
	 * @param	string|null	$term		The term to search for
	 * @param	array|null	$tags		The tags to search for
	 * @param	int			$method		\IPS\Content\Search\Index::i()->TERM_OR_TAGS or \IPS\Content\Search\Index::i()->TERM_AND_TAGS
	 * @param	string|null	$operator	If $term contains more than one word, determines if searching for both ("and") or any ("or") of those terms. NULL will go to admin-defined setting
	 * @return	int
	 */
	public function count( $term = NULL, $tags = NULL, $method = 1, $operator = NULL )
	{
		return $this->search( $term, $tags, $method, $operator )->count( TRUE );
	}
	
	/**
	 * Is this term a phrase?
	 *
	 * @param	string	$term	The term to search for
	 * @return	boolean
	 */
	public static function termIsPhrase( $term )
	{
		return (boolean) preg_match( '#^".*"$#', $term );
	}
	
	/**
	 * Convert the term into an array of words
	 *
	 * @param	string			$term			The term to search for
	 * @param	boolean			$ignorePhrase	When true, phrases are stripped of quotes and treated as normal words
	 * @param	int|NULL		$minLength		The minimum length a sequence of characters has to be before it is considered a word. If null, ft_min_word_len/innodb_ft_min_token_size is used.
	 * @param	int|NULL		$maxLength		The maximum length a sequence of characters can be for it to be considered a word. If null, ft_max_word_len/innodb_ft_max_token_size is used.
	 * @return	array
	 */
	public static function termAsWordsArray( $term, $ignorePhrase=FALSE, $minLength=NULL, $maxLength=NULL )
	{		
		$minLength = \is_null( $minLength ) ? 0 : $minLength;
		$maxLength = \is_null( $maxLength ) ? 255 : $maxLength;
		
		/* Sub quotes with smaller than min word strings can cause issues for larger tables, so we convert the sub quote into normal words for a boolean search */
		if ( ! static::termIsPhrase( $term ) )
		{
			preg_match_all( '#"([^"]+?)"#', $term, $matches, PREG_SET_ORDER );
			
			foreach( $matches as $match )
			{
				$allWordsInPhraseAreGood = true;
				$subWords = array();
				foreach( explode( ' ', $match[1] ) as $subWord )
				{
					if ( mb_strlen( $subWord ) <= $minLength or mb_strlen( $subWord ) >= $maxLength )
					{
						$allWordsInPhraseAreGood = false;
					}
					else
					{
						$subWords[] = $subWord;
					}
				}
				
				if ( ! $allWordsInPhraseAreGood )
				{
					$term = str_replace( $match[0], implode( ' ', $subWords ), $term );
				}
			}
		}

		/* Parse */
		$words = array();
		$currentWord = '';
		$inQuote = false;
		$termLength = mb_strlen( $term );
		for ( $i = 0; $i < $termLength; $i++ )
		{
			$c = mb_substr( $term, $i, 1 );
			if ( $c == '"' )
			{
				if ( $ignorePhrase )
				{
					continue;
				}
				$inQuote = !$inQuote;
			}
			elseif ( $c == ' ' and !$inQuote )
			{
				$words[] = trim( $currentWord );
				$currentWord = '';
			}
			$currentWord .= $c;
		}
		$words[] = trim( $currentWord );
				
		/* Now check each of the words is acceptable */
		$finalWords = array();
		foreach( $words as $word )
		{
			if ( mb_strlen( $word ) >= $minLength and mb_strlen( $word ) <= $maxLength )
			{
				$finalWords[] = $word;
			}
		}
						
		/* And return */
		return $finalWords;
	}
	
	/**
	 * Is a rebuild task running?
	 *
	 * @return boolean
	 */
	 public static function isRebuildRunning()
	 {
		 return \IPS\Db::i()->select( 'COUNT(*)', 'core_queue', array( '`key`=?', 'RebuildSearchIndex' ) )->first() ? TRUE : FALSE;
	 }
}
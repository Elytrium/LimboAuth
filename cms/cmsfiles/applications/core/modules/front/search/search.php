<?php
/**
 * @brief		Search
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Apr 2014
 */

namespace IPS\core\modules\front\search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search
 */
class _search extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Amount of time to cache search result in browser/CDN
	 */
	protected $_cacheTimeout = 30 * 60;

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 * @example array(
		'community_area' => array( 'value' => 'search', 'odkUpdate' => 'true' )
	   )
	 */
	public static $dataLayerContext = array(
		'community_area' => array( 'value' => 'search', 'odkUpdate' => 'true' )
	);

	/**
	 * Check if this was a cached result we should just return a 304 against
	 *
	 * @return	void
	 */
	protected function _checkCached()
	{
		/* Check whether a 304 Not modified response is appropriate */
		if( !empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) AND ( new \IPS\DateTime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )->getTimestamp() > ( time() - $this->_cacheTimeout ) )
		{
			header('HTTP/1.1 304 Not Modified' );
			exit;
		}
	}

	/**
	 * Search Form
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Make sure this isn't a cached result we should just honor */
		$this->_checkCached();

		/* Init stuff for the output */
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams.css' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search.css' ) );
		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/search_responsive.css' ) );
		}

		/* Add any global CSS from other apps */
		foreach( \IPS\Application::applications() as $app )
		{
			\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'global.css', $app->directory, 'front' ) );
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_search.js', 'core' ) );	
		\IPS\Output::i()->metaTags['robots'] = 'noindex'; // Tell search engines not to index search pages

		if( !\IPS\Settings::i()->tags_enabled and isset( \IPS\Request::i()->tags ) )
		{
			\IPS\Output::i()->error( 'page_doesnt_exist', '2C205/4', 404, '' );
		}

		/* Get the form */
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' );
		$form = $this->_form();

		/* If we have the term, show the results */
		if ( ( \IPS\Request::i()->isAjax() and ! isset( \IPS\Request::i()->_nodeSelectName ) ) or isset( \IPS\Request::i()->q ) or isset( \IPS\Request::i()->tags ) or ( \IPS\Request::i()->type == 'core_members' and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) ) )
		{
			$showForm = ( \IPS\Request::i()->type == 'core_members' ) ? FALSE : ( !\IPS\Request::i()->isAjax() and !\IPS\Request::i()->q and !\IPS\Request::i()->tags );

			if( \IPS\Request::i()->type == 'core_members' AND !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) )
			{
				$showForm = TRUE;
			}

			if ( $showForm )
			{
				if ( isset( \IPS\Request::i()->csrfKey ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('no_search_term');
					$form->hiddenValues['__advanced'] = true;

					\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'advanced_search' );
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( $this->_splitTermsForDisplay(), FALSE, FALSE, FALSE, $baseUrl, FALSE, $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, NULL ), 0, TRUE );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' ) );
				}
				return;
			}

			/* Data Layer Event */
			if ( \IPS\Settings::i()->core_datalayer_enabled AND !\IPS\Request::i()->isAjax() AND \IPS\Request::i()->q )
			{
				\IPS\core\DataLayer::i()->addEvent( 'search', array( 'query' => \IPS\Request::i()->q, ) );
			}
			
			return $this->_results();
		}
		/* Otherwise, show the advanced search form */
		else
		{
			$form->hiddenValues['__advanced'] = true;			

			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'advanced_search' );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( $this->_splitTermsForDisplay(), FALSE, FALSE, FALSE, $baseUrl, FALSE, $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, NULL ), 0, TRUE );
		}
	}

	/**
	 * @brief	Number of member search results to retrieve
	 */
	protected $memberSearchResults	= 24;
	
	/**
	 * Get Results
	 *
	 * @return	void
	 */
	protected function _results()
	{
		/* Make sure we are not doing anything nefarious like passing an array as the "q" parameter, which generates errors */
		foreach( array( 'q', 'type' ) AS $parameter )
		{
			if ( isset( \IPS\Request::i()->$parameter ) AND \is_array( \IPS\Request::i()->$parameter ) )
			{
				\IPS\Request::i()->$parameter = NULL;
			}
		}
		
		/* Init */
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' ); 
		if( \IPS\Request::i()->q ) 
		{ 
			$baseUrl = $baseUrl->setQueryString( 'q', \IPS\Request::i()->q ); 
		} 
		if ( \IPS\Request::i()->tags ) 
		{ 
			$baseUrl = $baseUrl->setQueryString( 'tags', \IPS\Request::i()->tags ); 
		}
		if (isset( \IPS\Request::i()->eitherTermsOrTags)) 
		{ 
			$baseUrl = $baseUrl->setQueryString( 'eitherTermsOrTags', \IPS\Request::i()->eitherTermsOrTags ); 
		}
		
		if( isset( \IPS\Request::i()->quick ) ) 
		{ 
			$baseUrl = $baseUrl->setQueryString( 'quick', \IPS\Request::i()->quick );
		}
		
		$types = static::contentTypes();

		/* Flood control */
		\IPS\Request::floodCheck();

		/* Are we searching members? */
		if ( isset( \IPS\Request::i()->type ) and \IPS\Request::i()->type === 'core_members' and \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members', 'front' ) ) )
		{
			$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
			if ( \IPS\Request::i()->q )
			{
				$where = array( array( 'LOWER(core_members.name) LIKE ?', '%' . mb_strtolower( trim( \IPS\Request::i()->q ) ) . '%' ) );
			}
			else
			{
				$where = array( array( 'core_members.completed=?', true ) );
			}
			
			if ( isset( \IPS\Request::i()->joinedDate ) and !isset( \IPS\Request::i()->start_after ) )
			{
				$baseUrl = $baseUrl->setQueryString( 'joinedDate', \IPS\Request::i()->joinedDate );
				
				\IPS\Request::i()->start_after = \IPS\Request::i()->joinedDate;
			}

			if ( ( isset( \IPS\Request::i()->start_before ) ) or ( isset( \IPS\Request::i()->start_after ) ) )
			{
				foreach ( array( 'before', 'after' ) as $l )
				{
					$$l = NULL;
					$key = "start_{$l}";
					if ( isset( \IPS\Request::i()->$key ) AND \IPS\Request::i()->$key != 'any' )
					{
						switch ( \IPS\Request::i()->$key )
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
								$$l = \IPS\DateTime::ts( \IPS\Request::i()->$key );
								break;
						}
					}
				}
				
				if ( $before )
				{
					$where[] = array( 'core_members.joined<?', $before->getTimestamp() );
				}
				if ( $after )
				{
					$where[] = array( 'core_members.joined>?', $after->getTimestamp() );
				}
			}

			if ( isset( \IPS\Request::i()->group ) )
			{
				/* Only exclude by group if the only value isn't __EMPTY **/
				if( !\is_array( \IPS\Request::i()->group ) OR ( \count( \IPS\Request::i()->group ) > 1 OR !isset( \IPS\Request::i()->group['__EMPTY'] ) ) )
				{
					$groups = ( \is_array( \IPS\Request::i()->group ) ) ? array_filter( array_keys( \IPS\Request::i()->group ), function( $val ){ 
						if( $val == '__EMPTY' )
						{
							return false;
						}

						return true;
					} ) : explode( ',', \IPS\Request::i()->group );

					foreach( $groups as $_idx => $groupId )
					{
						try
						{
							if( \IPS\Member\Group::load( $groupId )->g_bitoptions['gbw_hide_group'] )
							{
								unset( $groups[ $_idx ] );
							}
						}
						catch( \OutOfRangeException $e )
						{
							/* Group didn't exist, so let's ignore it */
							unset( $groups[ $_idx ] );
						}
					}
					$baseUrl = $baseUrl->setQueryString( 'group', \IPS\Request::i()->group );
					$where[] = \IPS\Db::i()->in( 'core_members.member_group_id', $groups );
				}
			}
			else
			{
				$exclude = array();
				/* Groups not specified but we still want to omit non searchable groups */
				foreach( \IPS\Member\Group::groups() as $group )
				{
					if ( $group->g_bitoptions['gbw_hide_group'] )
					{
						$exclude[] = $group->g_id;
					}
				}
				
				if ( \count( $exclude ) )
				{
					$where[] = \IPS\Db::i()->in( 'core_members.member_group_id', $exclude, TRUE );
				}
			}

			/* Figure out member custom field filters */
			foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\Field::SEARCH ) as $group => $fields )
			{
				/* Fields */
				foreach ( $fields as $id => $field )
				{
					/* Work out the object type so we can show the appropriate field */
					$type = \get_class( $field );

					switch ( $type )
					{
						case 'IPS\Helpers\Form\Text':
						case 'IPS\Helpers\Form\Tel':
						case 'IPS\Helpers\Form\Editor':
						case 'IPS\Helpers\Form\Email':
						case 'IPS\Helpers\Form\TextArea':
						case 'IPS\Helpers\Form\Url':
						case 'IPS\Helpers\Form\Date':
						case 'IPS\Helpers\Form\Number':
							$fieldName	= 'core_pfield_' . $id;

							if( isset( \IPS\Request::i()->$fieldName ) )
							{
								if( \IPS\Data\Store::i()->profileFields['fields'][$group][$id]['pf_search_type'] == 'loose' )
								{
									$where[] = array( 'LOWER(core_pfields_content.field_' . $id . ') LIKE ?', '%' . mb_strtolower( \IPS\Request::i()->$fieldName ) . '%' );
								}
								else
								{
									$where[] = array( 'LOWER(core_pfields_content.field_' . $id . ') = ?', mb_strtolower( \IPS\Request::i()->$fieldName ) );
								}
								$baseUrl = $baseUrl->setQueryString( $fieldName, \IPS\Request::i()->$fieldName );
							}
							break;
						case 'IPS\Helpers\Form\Select':
						case 'IPS\Helpers\Form\CheckboxSet':
						case 'IPS\Helpers\Form\Radio':
							$fieldName	= 'core_pfield_' . $id;

							if( isset( \IPS\Request::i()->$fieldName ) )
							{
								if ( ( $type === 'IPS\Helpers\Form\CheckboxSet' ) or ( isset( $field->options['multiple'] ) AND $field->options['multiple'] == 1 ) )
								{
									$where[] = \IPS\Db::i()->findInSet( 'core_pfields_content.field_' . $id ,array( mb_strtolower( \IPS\Request::i()->$fieldName ) ) );
								}
								else
								{
									$where[] = array( 'core_pfields_content.field_' . $id . ' = ?', mb_strtolower( \IPS\Request::i()->$fieldName ) );
								}

								$baseUrl = $baseUrl->setQueryString( $fieldName, \IPS\Request::i()->$fieldName );
							}
							break;
					}
				}
			}

			if( isset( \IPS\Request::i()->sortby ) AND \in_array( mb_strtolower( \IPS\Request::i()->sortby ), array( 'joined', 'name', 'member_posts', 'pp_reputation_points' ) ) )
			{
				$direction	= ( isset( \IPS\Request::i()->sortdirection ) AND \in_array( mb_strtolower( \IPS\Request::i()->sortdirection ), array( 'asc', 'desc' ) ) ) ? \IPS\Request::i()->sortdirection : 'asc';
				$order		= mb_strtolower( \IPS\Request::i()->sortby ) . ' ' . $direction;

				$baseUrl = $baseUrl->setQueryString( array( 'sortby' => \IPS\Request::i()->sortby, 'sortdirection' => \IPS\Request::i()->sortdirection ) );
			}
			else
			{
				/* If we have a search query, we order by INSTR(name, q) ASC, LENGTH(name) ASC, name ASC so as to show "xyz" before "abcxyz" when searching for "xyz" - INSTR() will pull results
					starting with the search string first, then we order by length to match xyz before xyza, then finally we sort by the name itself */
				if( \IPS\Request::i()->q )
				{
					$order = "INSTR( name, '" . \IPS\Db::i()->escape_string( \IPS\Request::i()->q ) . "' ) ASC, LENGTH( name ) ASC, name ASC";
				}
				else
				{
					$order = "name ASC";
				}
			}
			
			$page	= isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page = 1;
			}
			
			$where[] = array( "( core_validating.vid IS NULL ) " );
			
			$select	= \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where );
			$select->join( 'core_pfields_content', 'core_pfields_content.member_id=core_members.member_id' );
			$select->join( 'core_validating', 'core_validating.member_id=core_members.member_id AND core_validating.new_reg = 1' );
			$count = $select->first();

			$select	= \IPS\Db::i()->select( 'core_members.*', 'core_members', $where, $order, array( ( $page - 1 ) * $this->memberSearchResults, $this->memberSearchResults ) );
			$select->join( 'core_pfields_content', 'core_pfields_content.member_id=core_members.member_id' );
			$select->join( 'core_validating', 'core_validating.member_id=core_members.member_id AND core_validating.new_reg = 1' );
			
			$results	= new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member' );

			$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, ceil( $count / $this->memberSearchResults ), $page, $this->memberSearchResults, TRUE, 'page', ( $count > \IPS\UPGRADE_MANUAL_THRESHOLD ? TRUE : FALSE ) ) );
			if ( !\IPS\Request::i()->q )
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'members' );
			}
			else
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_results_title_term_area', FALSE, array( 'sprintf' => array( \IPS\Request::i()->q, \IPS\Member::loggedIn()->language()->addToStack( 'core_members_pl' ) ) ) );
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'filters'	=> $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, $count ),
					'content'	=> \IPS\Theme::i()->getTemplate( 'search' )->results( $this->_splitTermsForDisplay(), $title, $results, $pagination, $baseUrl, $count ),
					'title'		=> $title,
					'css'		=> array()
				) );
			}
			else
			{
				\IPS\Output::i()->title = $title;
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( $this->_splitTermsForDisplay(), $title, $results, $pagination, $baseUrl, $types, $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, $count ), $count );
			}
			return;
		}
		
		/* Init */
		$query = \IPS\Content\Search\Query::init();
		$titleConditions = array();
		$titleType = 'search_blurb_all_content';
				
		/* Set content type */
		if ( isset( \IPS\Request::i()->type ) and array_key_exists( \IPS\Request::i()->type, $types ) )
		{	
			$class = $types[ \IPS\Request::i()->type ];

			if ( isset( \IPS\Request::i()->item ) )
			{
				try
				{
					$item = $class::loadAndCheckPerms( (int) \IPS\Request::i()->item );
					$query->filterByContent( array( \IPS\Content\Search\ContentFilter::init( $class )->onlyInItems( array( \IPS\Request::i()->item ) ) ) );
					$baseUrl = $baseUrl->setQueryString( 'item', \intval( \IPS\Request::i()->item ) );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_in', FALSE, array( 'sprintf' => array( $item->mapped('title') ) ) );
					$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
				}
				catch ( \OutOfRangeException $e ) { }
			}
			else
			{
				$filter = \IPS\Content\Search\ContentFilter::init( $types[ \IPS\Request::i()->type ] );
				$baseUrl = $baseUrl->setQueryString( 'type', \IPS\Request::i()->type );
				
				if ( isset( \IPS\Request::i()->nodes ) and isset( $types[ \IPS\Request::i()->type ]::$containerNodeClass ) )
				{
					/* It's ok to just pass the (potential) parent IDs into the URL */
					$baseUrl = $baseUrl->setQueryString( 'nodes', \IPS\Request::i()->nodes );
					
					$nodeIds = explode( ',', \IPS\Request::i()->nodes );
					$nodes = array();
					$nodeClass = $types[ \IPS\Request::i()->type ]::$containerNodeClass;
					foreach ( $nodeIds as $id )
					{
						try
						{
							$thisNode = $nodeClass::loadAndCheckPerms( $id );
							$nodes[] = $thisNode->_title;
							
							if ( isset( \IPS\Request::i()->quick ) AND $thisNode->childrenCount() )
							{
								foreach( $thisNode->children() as $child )
								{
									/* Avoid going too deep. Stop laughing at the back */
									if ( \count( $nodeIds ) < 200 )
									{
										$nodeIds[] = $child->_id;
									}
								}
							}
						}
						catch ( \OutOfRangeException $e ) { }
					}
					
					if ( !$nodes )
					{
						\IPS\Output::i()->error( 'search_invalid_nodes', '2C205/5', 404, '' );
					}
					
					$filter->onlyInContainers( $nodeIds );
					
					$nodes = \IPS\Member::loggedIn()->language()->formatList( $nodes );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_in', FALSE, array( 'sprintf' => array( $nodes ) ) );
				}
				else
				{
					$titleType = $types[ \IPS\Request::i()->type ]::$title . '_pl_lc';
				}
				
				if ( isset( \IPS\Request::i()->search_min_comments ) )
				{
					$filter->minimumComments( (int) \IPS\Request::i()->search_min_comments );
					$baseUrl = $baseUrl->setQueryString( 'search_min_comments', (int) \IPS\Request::i()->search_min_comments );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_min_comments', FALSE, [ 'sprintf' => [ (int) \IPS\Request::i()->search_min_comments ] ] );
				}
				if ( isset( \IPS\Request::i()->search_min_replies ) AND isset( $class::$commentClass ) )
				{
					$filter->minimumComments( (int) \IPS\Request::i()->search_min_replies + 1 );
					$baseUrl = $baseUrl->setQueryString( 'search_min_replies', (int) \IPS\Request::i()->search_min_replies );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_min_replies', FALSE, [ 'sprintf' => [ (int) \IPS\Request::i()->search_min_replies ] ] );
				}
				if ( isset( \IPS\Request::i()->search_min_reviews ) AND isset( $class::$reviewClass ) )
				{
					$filter->minimumReviews( (int) \IPS\Request::i()->search_min_reviews );
					$baseUrl = $baseUrl->setQueryString( 'search_min_reviews', (int) \IPS\Request::i()->search_min_reviews );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_min_reviews', FALSE, [ 'sprintf' => [ (int) \IPS\Request::i()->search_min_reviews ] ] );
				}
				if ( isset( \IPS\Request::i()->search_min_views ) AND isset( $class::$databaseColumnMap['views'] ) AND \IPS\Content\Search\Index::i()->supportViewFiltering() )
				{
					$filter->minimumViews( (int) \IPS\Request::i()->search_min_views );
					$baseUrl = $baseUrl->setQueryString( 'search_min_views', (int) \IPS\Request::i()->search_min_views );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_min_views', FALSE, [ 'sprintf' => [ (int) \IPS\Request::i()->search_min_views ] ] );
				}
				
				$query->filterByContent( array( $filter ) );
				
			}
		}
		
		/* Filter by author */
		if ( isset( \IPS\Request::i()->author ) )
		{
			$author = \IPS\Member::load( html_entity_decode( \IPS\Request::i()->author, ENT_QUOTES, 'UTF-8' ), 'name' );
			if ( $author->member_id )
			{
				$query->filterByAuthor( $author );
				$baseUrl = $baseUrl->setQueryString( 'author', $author->name );
				$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_author', FALSE, array( 'sprintf' => array( $author->name ) ) );
			}
		}
		
		/* Filter by club */
		if ( isset( \IPS\Request::i()->club ) AND \IPS\Settings::i()->clubs )
		{
			$clubIds = array();
			$clubNames = array();
			
			foreach ( explode( ',', \IPS\Request::i()->club ) as $clubId )
			{
				try
				{
					$club = \IPS\Member\Club::load( ltrim( $clubId, 'c' ) );
					if ( $club->canRead() )
					{
						$clubIds[] = $club->id;
						$clubNames[] = $club->name;
					}
				}
				catch ( \OutOfRangeException $e ) { }				
			}
			
			if ( \count( $clubIds ) )
			{
				$query->filterByClub( $clubIds );
				$baseUrl = $baseUrl->setQueryString( 'club', \IPS\Request::i()->club );
				$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_club', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $clubNames, \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) ) );
			}
		}
		
		/* Set time cutoffs */
		foreach ( array( 'start' => 'filterByCreateDate', 'updated' => 'filterByLastUpdatedDate' ) as $k => $method )
		{
			$beforeKey = "{$k}_before";
			$afterKey = "{$k}_after";
			
			if ( isset( \IPS\Request::i()->$beforeKey ) )
			{
				$baseUrl = $baseUrl->setQueryString( $beforeKey, \IPS\Request::i()->$beforeKey );
			}

			if ( isset( \IPS\Request::i()->$afterKey ) )
			{
				$baseUrl = $baseUrl->setQueryString( $afterKey, \IPS\Request::i()->$afterKey );
			}
			
			if ( isset( \IPS\Request::i()->$beforeKey ) and \IPS\Request::i()->$beforeKey != 'any' and isset( \IPS\Request::i()->$afterKey ) and \IPS\Request::i()->$afterKey != 'any' )
			{
				/* Javascript takes a date and turns it into a timestamp but does not apply any timezone offsetting, so we don't want to do so here or else the date we create may not be the same one the user chose. */
				$after	= \IPS\DateTime::ts( \IPS\Request::i()->$afterKey, TRUE );
				$before	= \IPS\DateTime::ts( \IPS\Request::i()->$beforeKey, TRUE );
				$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_$k", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_between", FALSE, array( 'sprintf' => array( $after->localeDate(), $before->localeDate() ) ) ) ) ) );

				$query->$method( $after, $before );
			}
			elseif ( isset( \IPS\Request::i()->$beforeKey ) or isset( \IPS\Request::i()->$afterKey ) )
			{
				foreach ( array( 'after', 'before' ) as $l )
				{
					$$l = NULL;
					$key = "{$l}Key";
					if ( isset( \IPS\Request::i()->$$key ) AND \IPS\Request::i()->$$key != 'any' )
					{
						if ( \IPS\Content\Search\Index::i()->supportViewFiltering() )
						{
							$baseUrl = $baseUrl->setQueryString( 'search_min_views', \IPS\Request::i()->search_min_views );
						}
						
						switch ( \IPS\Request::i()->$$key )
						{
							case 'day':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_rel_{$l}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_date_rel_day' ) ) ) );
								break;
								
							case 'week':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_rel_{$l}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_date_rel_week' ) ) ) );
								break;
								
							case 'month':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1M' ) );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_rel_{$l}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_date_rel_month' ) ) ) );
								break;
								
							case 'six_months':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P6M' ) );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_rel_{$l}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_date_rel_6months' ) ) ) );
								break;
								
							case 'year':
								$$l = \IPS\DateTime::create()->sub( new \DateInterval( 'P1Y' ) );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_rel_{$l}", FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_date_rel_year' ) ) ) );
								break;
								
							default:
								$$l = \IPS\DateTime::ts( \IPS\Request::i()->$$key );
								$dateCondition = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_{$l}", FALSE, array( 'sprintf' => array( $$l->localeDate() ) ) );
								break;
						}
						
						$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( "search_blurb_date_{$k}", FALSE, array( 'sprintf' => array( $dateCondition ) ) );
					}
				}
				
				$query->$method( $after, $before );
			}
		}
		
		/* Work out the title */
		if ( \IPS\Request::i()->tags )
		{
			/* @todo Remove when we fix \Http\Url as there are issues with urlencode/decoding */
			if ( ! \IPS\Settings::i()->htaccess_mod_rewrite )
			{
				\IPS\Request::i()->tags = urldecode( \IPS\Request::i()->tags );
			}
			
			$tagList = array_map( function( $val )
			{
				return '\'' . htmlentities( $val, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) . '\'';
			}, explode( ',', \IPS\Request::i()->tags ) );
		}
		$title = '';
		if ( \IPS\Request::i()->q )
		{
			\IPS\core\Facebook\Pixel::i()->Search = array(
				'search_string' => \IPS\Request::i()->q
			);
			
			if ( \IPS\Request::i()->tags )
			{
				if ( isset( \IPS\Request::i()->eitherTermsOrTags ) and \IPS\Request::i()->eitherTermsOrTags === 'and' )
				{
					$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_term', FALSE, array( 'sprintf' => array( urldecode( \IPS\Request::i()->q ) ) ) );
					$titleConditions[] = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_tag_condition', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $tagList, \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) ) );
				}
				else
				{
					$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_term_or_tag', FALSE, array( 'sprintf' => array( urldecode( \IPS\Request::i()->q ), \IPS\Member::loggedIn()->language()->formatList( $tagList, \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) ) );
				}
			}
			else
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_term', FALSE, array( 'sprintf' => array( urldecode( \IPS\Request::i()->q ) ) ) );
			}
		}
		elseif ( \IPS\Request::i()->tags )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_tag', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $tagList, \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) ) );
		}
		if ( \count( $titleConditions ) )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_conditions', FALSE, array( 'sprintf' => array( $title, \IPS\Member::loggedIn()->language()->addToStack( $titleType ), \IPS\Member::loggedIn()->language()->formatList( $titleConditions ) ) ) );
		}
		elseif ( $titleType != 'search_blurb_all_content' )
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_with_type', FALSE, array( 'sprintf' => array( $title, \IPS\Member::loggedIn()->language()->addToStack( $titleType ) ) ) );
		}
		else
		{
			$title = \IPS\Member::loggedIn()->language()->addToStack( 'search_blurb_no_conditions', FALSE, array( 'sprintf' => array( $title ) ) );
		}
		
		/* Set page */
		if ( isset( \IPS\Request::i()->page ) AND \intval( \IPS\Request::i()->page ) > 0 )
		{
			$query->setPage( \intval( \IPS\Request::i()->page ) );
			$baseUrl = $baseUrl->setQueryString( 'page', \intval( \IPS\Request::i()->page ) );
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
		
		$baseUrl = $baseUrl->setQueryString( 'sortby', \IPS\Request::i()->sortby );
		
		$flags = ( isset( \IPS\Request::i()->eitherTermsOrTags ) and \IPS\Request::i()->eitherTermsOrTags === 'and' ) ? \IPS\Content\Search\Query::TERM_AND_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS;
		$operator = NULL;
				
		if ( isset( \IPS\Request::i()->search_and_or ) and \in_array( \IPS\Request::i()->search_and_or, array( \IPS\Content\Search\Query::OPERATOR_OR, \IPS\Content\Search\Query::OPERATOR_AND ) ) )
		{
			$operator = \IPS\Request::i()->search_and_or;
			$baseUrl = $baseUrl->setQueryString( 'search_and_or', \IPS\Request::i()->search_and_or );
		}
		
		if ( isset( \IPS\Request::i()->search_in ) and \IPS\Request::i()->search_in === 'titles' )
		{
			$flags = $flags | \IPS\Content\Search\Query::TERM_TITLES_ONLY;
			$baseUrl = $baseUrl->setQueryString( 'search_in', \IPS\Request::i()->search_in );
		}

		/* Run query */
		$results = $query->search(
			isset( \IPS\Request::i()->q ) ? ( \IPS\Request::i()->q ) : NULL,
			isset( \IPS\Request::i()->tags ) ? explode( ',', \IPS\Request::i()->tags ) : NULL,
			$flags + \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY,
			$operator
		);
				
		/* Get pagination */
		$page = isset( \IPS\Request::i()->page ) ? \intval( \IPS\Request::i()->page ) : 1;
		$count = $results->count( TRUE );
		$pages = ceil( $results->count( TRUE ) / $query->resultsToGet );

		if ( ! $count )
		{
			/* Enforce that we're on page 1 if there are no results to prevent page tampering */
			$page = 1;
		}
		else if( $count and $pages and ( $page < 1 or $page > $pages ) )
		{
			/* There's no point resetting the page at this point as the search query has been run and no results have been found */
			\IPS\Request::i()->setCookie( 'lastSearch', 0 );
			\IPS\Output::i()->redirect( $baseUrl->setPage( 'page', 1 ), NULL, 303 );
		}

		$pagination = trim( \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, $pages, $page, $query->resultsToGet ) );
		
		/* Search tracking, only if there's a term for a non-member search and we're on the first page */
		if( \IPS\Request::i()->q AND $page == 1 )
		{
			if( !\IPS\Member::loggedIn()->inGroup( json_decode( \IPS\Settings::i()->searchlog_exclude_groups ) ) )
			{
				try
				{
					\IPS\Db::i()->insert( 'core_statistics', array(
						'type'			=> 'search',
						'time'			=> time(),
						'value_4'		=> mb_substr( \IPS\Request::i()->q, 0, 255 ), // Some search queries can be unnecessarily long
						'value_2'		=> $count,
						'extra_data'	=> md5( \IPS\Member::loggedIn()->email . \IPS\Member::loggedIn()->joined ) // Intentionally anonymized
					) );
				}
				catch( \IPS\Db\Exception $e ) {}
			}
		}

		/* Enable caching for the search results request */
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			$httpHeaders = array(   'Expires' => \IPS\DateTime::create()->add( new \DateInterval( 'PT3M' ) )->rfc1123(),
									'Cache-Control' => 'no-cache="Set-Cookie", max-age=' . $this->_cacheTimeout . ", s-maxage=" . $this->_cacheTimeout . ", public, stale-if-error, stale-while-revalidate" );

			\IPS\Output::i()->httpHeaders += $httpHeaders;
		}

		/* Display results */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array(
				'filters'	=> $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, $count ),
				'hints' 	=> \IPS\Theme::i()->getTemplate( 'search' )->hints( $baseUrl, $count ),
				'content'	=> \IPS\Theme::i()->getTemplate( 'search' )->results( $this->_splitTermsForDisplay(), $title, $results, $pagination, $baseUrl, $count ),
				'title'		=> $title,
				'css'		=> array()
			) );
		}
		else
		{
			\IPS\Output::i()->title = $title;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'search' )->search( $this->_splitTermsForDisplay(), $title, $results, $pagination, $baseUrl, $types, $this->_form()->customTemplate( array( \IPS\Theme::i()->getTemplate( 'search' ), 'filters' ), $baseUrl, $count ), $count );
		}
	}
	
	/**
	 * Get the search form
	 *
	 * @return	\IPS\Helpers\Form
	 */
	public function _form()
	{
		/* Init */
		$form = new \IPS\Helpers\Form;
		
		/* Update filters sidebar will lose item as it is not part of the form #5772 */
		if ( isset( \IPS\Request::i()->item ) )
		{
			$form->hiddenValues['item'] = \IPS\Request::i()->item;
		}
		
		if ( isset( \IPS\Request::i()->sortby ) )
		{
			$form->hiddenValues['sortby'] = \IPS\Request::i()->sortby;
		}
		
		if ( isset( \IPS\Request::i()->sortdirection ) )
		{
			$form->hiddenValues['sortdirection'] = \IPS\Request::i()->sortdirection;
		}
		
		if ( isset( \IPS\Request::i()->nodes ) )
		{
			$form->hiddenValues['nodes'] = \IPS\Request::i()->nodes;
		}
		
		if ( isset( \IPS\Request::i()->quick ) )
		{
			$form->hiddenValues['quick'] = \IPS\Request::i()->quick;
		}

		/* If we do a quick search the type is set to 'all' but we want it as '' for the form to work properly */
		if( isset( \IPS\Request::i()->type ) AND \IPS\Request::i()->type === 'all' )
		{
			\IPS\Request::i()->type = '';
		}
		
		/* Set up some sensible defaults */
		if ( ! isset( \IPS\Request::i()->item ) and ! isset( \IPS\Request::i()->updated_after ) )
		{
			\IPS\Request::i()->updated_after = \IPS\Content\Search\Query::init()->getDefaultDateCutOff();
		}

		/* Types */
		$types				= array( '' => 'search_everything' );
		$contentTypes		= static::contentTypes();
		$contentToggles		= array();
		$typeFields			= array();
		$typeFieldToggles	= array('' => array( 'club' ) );
		$haveCommentClass	= FALSE;
		$haveReplyClass		= FALSE;
		$haveReviewClass	= FALSE;
		$dateOptions = array(
			'any'			=> 'any',
			'day'			=> 'last_24hr',
			'week'			=> 'last_week',
			'month'			=> 'last_month',
			'six_months'	=> 'last_six_months',
			'year'			=> 'last_year',
			'custom'		=> 'custom'
		);

		/* Form tabs */
		$form->addTab( 'search_tab_all' );
		$form->addTab( 'search_tab_content' );
		$form->addTab( 'search_tab_member' );

		/* Figure out member fields to set toggles */
		$memberToggles	= array( 'joinedDate', 'core_members_group' );
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\Field::SEARCH ) as $group => $fields )
		{
			foreach ( $fields as $id => $field )
			{
				switch ( \get_class( $field ) )
				{
					case 'IPS\Helpers\Form\Text':
					case 'IPS\Helpers\Form\Tel':
					case 'IPS\Helpers\Form\Editor':
					case 'IPS\Helpers\Form\Email':
					case 'IPS\Helpers\Form\TextArea':
					case 'IPS\Helpers\Form\Url':
					case 'IPS\Helpers\Form\Date':
					case 'IPS\Helpers\Form\Number':
					case 'IPS\Helpers\Form\Select':
					case 'IPS\Helpers\Form\CheckboxSet':
					case 'IPS\Helpers\Form\Radio':
						$memberToggles[]	= 'core_pfield_' . $id;
						break;
				}
			}
		}

		/* Type select */
		foreach ( $contentTypes as $k => $class )
		{
			$types[ $k ] = $k . '_pl';
			if ( $k !== 'core_members' )
			{
				$typeFieldToggles[ $k ][] = $k . '_node';

				if( isset( $class::$databaseColumnMap['views'] ) and \IPS\Content\Search\Index::i()->supportViewFiltering() )
				{
					$typeFieldToggles[ $k ][] = 'search_min_views';
				}

				if ( isset( $class::$commentClass ) )
				{
					if ( $class::$firstCommentRequired )
					{
						$haveReplyClass = TRUE;
						$typeFieldToggles[ $k ][] = 'search_min_replies';
					}
					else
					{
						$haveCommentClass = TRUE;
						$typeFieldToggles[ $k ][] = 'search_min_comments';
					}
				}
				if ( isset( $class::$reviewClass ) )
				{
					$haveReviewClass = TRUE;
					$typeFieldToggles[ $k ][] = 'search_min_reviews';
				}
			}
		}
		$form->add( new \IPS\Helpers\Form\Radio( 'type', '', FALSE, array( 'options' => $types, 'toggles' => $typeFieldToggles ) ), NULL, 'search_tab_content' );

		/* Term */
		$form->add( new \IPS\Helpers\Form\Text( 'q' ), NULL, 'search_tab_all' );

		$form->add( new \IPS\Helpers\Form\Text( 'tags', \IPS\Request::i()->tags, FALSE, array( 'autocomplete' => array( 'minimized' => FALSE, 'forceLower' => ( \IPS\Settings::i()->tags_force_lower ) ? TRUE : FALSE ) ), NULL, NULL, NULL, 'tags' ), NULL, 'search_tab_content' );
		$form->add( new \IPS\Helpers\Form\Radio( 'eitherTermsOrTags', \IPS\Request::i()->eitherTermsOrTags, FALSE, array( 'options' => array( 'or' => 'termsortags_or_desc', 'and' => 'termsortags_and_desc' ) ), NULL, NULL, NULL, 'eitherTermsOrTags' ), NULL, 'search_tab_content' );	

		/* Author */
		$form->add( new \IPS\Helpers\Form\Member( 'author', NULL, FALSE, array(), NULL, NULL, NULL, 'author' ), NULL, 'search_tab_content' );
		
		/* Club */
		if( \IPS\Settings::i()->clubs )
		{
			if ( \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'name', TRUE, array(), NULL, TRUE ) )
			{
				$clubs = \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'name', TRUE );
				
				$clubOptions = array();
				foreach ( $clubs as $club )
				{
					$clubOptions[ "c{$club->id}" ] = $club->name;
				}
							
				$form->add( new \IPS\Helpers\Form\Select( 'club', NULL, FALSE, array( 'options' => $clubOptions, 'parse' => 'normal', 'multiple' => TRUE, 'noDefault' => TRUE, 'class' => 'ipsField_fullWidth' ), NULL, NULL, NULL, 'club' ), NULL, 'search_tab_content' );
			}
		}
		
		/* Dates */
		$form->add( new \IPS\Helpers\Form\Select( 'startDate', ( isset( \IPS\Request::i()->start_before ) or ( isset( \IPS\Request::i()->start_after ) and \is_numeric( \IPS\Request::i()->start_after ) ) ) ? 'custom' : \IPS\Request::i()->start_after, FALSE, array( 'options' => $dateOptions, 'toggles' => array( 'custom' => array( 'elCustomDate_startDate' ) ) ), NULL, NULL, NULL, 'startDate' ), NULL, 'search_tab_content' );
		$form->add( new \IPS\Helpers\Form\DateRange( 'startDateCustom', array( 'start' => ( isset( \IPS\Request::i()->start_after ) and \is_numeric(  \IPS\Request::i()->start_after ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->start_after, TRUE ) : NULL, 'end' => isset( \IPS\Request::i()->start_before ) ? \IPS\DateTime::ts( \IPS\Request::i()->start_before, TRUE ) : NULL ), false, array(
			'start' => array( 'timezone' => new \DateTimeZone( 'UTC' ), 'time' => FALSE ),
			'end' => array( 'timezone' => new \DateTimeZone( 'UTC' ), 'time' => FALSE )
		) ), NULL, 'search_tab_content' );
		$form->add( new \IPS\Helpers\Form\Select( 'updatedDate', ( isset( \IPS\Request::i()->updated_before ) or ( isset( \IPS\Request::i()->updated_after ) and \is_numeric( \IPS\Request::i()->updated_after ) ) ) ? 'custom' : \IPS\Request::i()->updated_after, FALSE, array( 'options' => $dateOptions, 'toggles' => array( 'custom' => array( 'elCustomDate_updatedDate' ) ) ), NULL, NULL, NULL, 'updatedDate' ), NULL, 'search_tab_content' );
		$form->add( new \IPS\Helpers\Form\DateRange( 'updatedDateCustom', array( 'start' => ( isset( \IPS\Request::i()->updated_after ) and \is_numeric( \IPS\Request::i()->updated_after ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->updated_after, TRUE ) : NULL, 'end' => isset( \IPS\Request::i()->updated_before ) ? \IPS\DateTime::ts( \IPS\Request::i()->updated_before, TRUE ) : NULL ), false, array(
			'start' => array( 'timezone' => new \DateTimeZone( 'UTC' ), 'time' => FALSE ),
			'end' => array( 'timezone' => new \DateTimeZone( 'UTC' ), 'time' => FALSE )
		) ), NULL, 'search_tab_content' );

		/* Other filters */
		$form->add( new \IPS\Helpers\Form\Radio( 'search_in', \IPS\Request::i()->search_in, FALSE, array( 'options' => array( 'all' => 'titles_and_body', 'titles' => 'titles_only' ) ), NULL, NULL, NULL, 'searchIn' ), NULL, 'search_tab_content' );
		$form->add( new \IPS\Helpers\Form\Radio( 'search_and_or', isset( \IPS\Request::i()->search_and_or ) ? \IPS\Request::i()->search_and_or : \IPS\Settings::i()->search_default_operator, FALSE, array( 'options' => array( 'and' => 'search_and', 'or' => 'search_or' ) ), NULL, NULL, NULL, 'andOr' ), NULL, 'search_tab_content' );

		/* Nodes */
		foreach ( $contentTypes as $k => $class )
		{
			if ( isset( $class::$containerNodeClass ) )
			{
				$nodes = NULL;

				/* If we have a node type, we should only select nodes for that type */
				if( isset( \IPS\Request::i()->type ) AND !empty( \IPS\Request::i()->type ) )
				{
					$typeInfo	= explode( '_', \IPS\Request::i()->type );
					$typeClass	= 'IPS\\' . $typeInfo[0] . '\\' . mb_ucfirst( $typeInfo[1] );

					if( $typeClass == $class )
					{
						$nodes = ( isset( \IPS\Request::i()->nodes ) ) ? \IPS\Request::i()->nodes : NULL;
					}
				}

				$nodeClass = $class::$containerNodeClass;
				$field = new \IPS\Helpers\Form\Node( $k . '_node', $nodes, FALSE, array( 'class' => $nodeClass, 'subnodes' => FALSE, 'multiple' => TRUE, 'permissionCheck' => $nodeClass::searchableNodesPermission(), 'forceOwner' => FALSE, 'clubs' => ( \IPS\Settings::i()->clubs AND \IPS\IPS::classUsesTrait( $nodeClass, 'IPS\Content\ClubContainer' ) ) ), NULL, NULL, NULL, $k . '_node' );
				$field->label = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle );
				$form->add( $field, NULL, 'search_tab_nodes' );
			}
		}

		/* Comments/Views */
		$queryClass = \IPS\Content\Search\Query::init();
		if ( $queryClass::SUPPORTS_JOIN_FILTERS )
		{
			if ( $haveCommentClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_comments', isset( \IPS\Request::i()->search_min_comments ) ? \IPS\Request::i()->search_min_comments : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_comments' ), NULL, 'search_tab_content' );
			}
			if ( $haveReplyClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_replies', isset( \IPS\Request::i()->search_min_replies ) ? \IPS\Request::i()->search_min_replies : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_replies' ), NULL, 'search_tab_content' );
			}
			if ( $haveReviewClass )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_reviews', isset( \IPS\Request::i()->search_min_reviews ) ? \IPS\Request::i()->search_min_reviews : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_reviews' ), NULL, 'search_tab_content' );
			}
			if ( \IPS\Content\Search\Index::i()->supportViewFiltering() )
			{
				$form->add( new \IPS\Helpers\Form\Number( 'search_min_views', isset( \IPS\Request::i()->search_min_views ) ? \IPS\Request::i()->search_min_views : 0, FALSE, array(), NULL, NULL, NULL, 'search_min_views' ), NULL, 'search_tab_content' );
			}
		}
		
		/* Member group and joined */
		$groups = \IPS\Member\Group::groups( TRUE, FALSE, TRUE );

		$form->add(new \IPS\Helpers\Form\CheckboxSet('group', ( isset( \IPS\Request::i()->group ) ) ? \is_array( \IPS\Request::i()->group) ? array_keys( \IPS\Request::i()->group) : array( \IPS\Request::i()->group ) : array_keys( $groups ), FALSE, array('options' => $groups, 'parse' => 'normal'), NULL, NULL, NULL, 'core_members_group'), NULL, 'search_tab_member' );
		$form->add(new \IPS\Helpers\Form\Select('joinedDate', (isset(\IPS\Request::i()->start_before) or (isset(\IPS\Request::i()->start_after) and \is_numeric(\IPS\Request::i()->start_after))) ? 'custom' : \IPS\Request::i()->start_after, FALSE, array('options' => $dateOptions, 'toggles' => array('custom' => array('elCustomDate_joinedDate'))), NULL, NULL, NULL, 'joinedDate'), NULL, 'search_tab_member' );
		$form->add(new \IPS\Helpers\Form\DateRange('joinedDateCustom', array('start' => (isset(\IPS\Request::i()->start_after) and \is_numeric(\IPS\Request::i()->start_after)) ? \IPS\DateTime::ts(\IPS\Request::i()->start_after, TRUE) : NULL, 'end' => isset(\IPS\Request::i()->start_before) ? \IPS\DateTime::ts(\IPS\Request::i()->start_before, TRUE) : NULL)), NULL, 'search_tab_member' );

		/* Profile fields for member searches */
		$memberFields	= array();
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\Field::SEARCH ) as $group => $fields )
		{
			$fieldsToAdd	= array();
			/* Fields */
			foreach ( $fields as $id => $field )
			{

				/* Alias the lang keys */
				$realLangKey = "core_pfield_{$id}";

				/* Work out the object type so we can show the appropriate field */
				$type = \get_class( $field );
				$helper = NULL;
				
				switch ( $type )
				{
					case 'IPS\Helpers\Form\Text':
					case 'IPS\Helpers\Form\Tel':
					case 'IPS\Helpers\Form\Editor':
					case 'IPS\Helpers\Form\Email':
					case 'IPS\Helpers\Form\TextArea':
					case 'IPS\Helpers\Form\Url':
						$helper = new \IPS\Helpers\Form\Text( 'core_pfield_' . $id, NULL, FALSE, array(), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Date':
						$helper = new \IPS\Helpers\Form\DateRange( 'core_pfield_' . $id, NULL, FALSE, array(), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Number':
						$helper = new \IPS\Helpers\Form\Number( 'core_pfield_' . $id, -1, FALSE, array( 'unlimited' => -1, 'unlimitedLang' => 'member_number_anyvalue' ), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
					case 'IPS\Helpers\Form\Select':
					case 'IPS\Helpers\Form\CheckboxSet':
					case 'IPS\Helpers\Form\Radio':
						$options = array( '' => "" );
						if( \count( $field->options['options'] ) )
						{
							foreach ($field->options['options'] as $optionKey => $option )
							{
								$options[ $type === 'IPS\Helpers\Form\CheckboxSet' ? $optionKey : $option ] = $option;
							}
						}
						
						$helper = new \IPS\Helpers\Form\Select( 'core_pfield_' . $id, NULL, FALSE, array( 'options' => $options ), NULL, NULL, NULL, 'core_pfield_' . $id );
						$memberFields[]	= 'core_pfield_' . $id;
						break;
				}
				
				if ( $helper )
				{
					$fieldsToAdd[] = $helper;
				}
			}
			
			if( \count( $fieldsToAdd ) )
			{
				foreach( $fieldsToAdd as $field )
				{
					$form->add( $field, NULL, 'search_tab_member' );
				}
			}
		}		

		/* If they submitted the advanced search form, redirect back (searching is a GET not a POST) */
		if ( $values = $form->values() )
		{
			if( !\IPS\Request::i()->isAjax() AND ( ( $values['q'] or $values['tags'] ) or $values['type'] == 'core_members' ) )
			{
				$url = \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' );
							
				if ( $values['q'] )
				{
					$url = $url->setQueryString( 'q', $values['q'] );
				}
				if ( $values['tags'] )
				{
					$url = $url->setQueryString( 'tags', implode( ',', $values['tags'] ) );
				}
				if ( $values['q'] and $values['tags'] )
				{
					$url = $url->setQueryString( 'eitherTermsOrTags', $values['eitherTermsOrTags'] );
				}
				if ( $values['type'] )
				{
					$url = $url->setQueryString( 'type', $values['type'] );
					
					if ( isset( $values[ $values['type'] . '_node' ] ) and !empty( $values[ $values['type'] . '_node' ] ) )
					{
						$url = $url->setQueryString( 'nodes', implode( ',', array_keys( $values[ $values['type'] . '_node' ] ) ) );
					}
					
					if ( isset( $values['search_min_comments'] ) and $values['search_min_comments'] )
					{
						$url = $url->setQueryString( 'comments', $values['search_min_comments'] );
					}
					if ( isset( $values['search_min_replies'] ) and $values['search_min_replies'] )
					{
						$url = $url->setQueryString( 'replies', $values['search_min_replies'] );
					}
					if ( isset( $values['search_min_reviews'] ) and $values['search_min_reviews'] )
					{
						$url = $url->setQueryString( 'reviews', $values['search_min_reviews'] );
					}
					if ( isset( $values['search_min_views'] ) and $values['search_min_views'] and \IPS\Content\Search\Index::i()->supportViewFiltering() )
					{
						$url = $url->setQueryString( 'views', $values['search_min_views'] );
					}
				}
				if ( isset( $values['author'] ) and $values['author'] )
				{
					$url = $url->setQueryString( 'author', $values['author']->name );
				}
				if ( isset( $values['club'] ) and $values['club'] )
				{
					$url = $url->setQueryString( 'club', $values['club'] );
				}

				if ( isset( $values['group'] ) and $values['group'] )
				{

					$values['group']	= array_flip( $values['group'] );

					array_walk( $values['group'], function( &$value, $key ){
						$value = 1;
					} );

					$url = $url->setQueryString( 'group', $values['group'] );
				}

				foreach( $memberFields as $fieldName )
				{
					if( isset( $values[ $fieldName ] ) AND $values[ $fieldName ] )
					{
						$url = $url->setQueryString( $fieldName, $values[ $fieldName ] );
					}
				}
				
				if( isset( $values['joinedDate'] ) AND $values['joinedDate'] != 'custom' )
				{
					$url = $url->setQueryString( 'start_after', $values['joinedDate'] );
				}

				if( isset( $values['joinedDate'] ) AND $values['joinedDate'] == 'custom' AND isset( $values['joinedDateCustom']['start'] ) )
				{
					$url = $url->setQueryString( 'start_after', $values['joinedDateCustom']['start']->getTimestamp() );
				}

				if( isset( $values['joinedDate'] ) AND $values['joinedDate'] == 'custom' AND isset( $values['joinedDateCustom']['end'] ) )
				{
					$url = $url->setQueryString( 'start_before', $values['joinedDateCustom']['end']->getTimestamp() );
				}

				foreach ( array( 'start', 'updated' ) as $k )
				{
					if ( $values[ $k . 'Date' ] != 'any' )
					{
						if ( $values[ $k . 'Date' ] === 'custom' )
						{
							if ( $values[ $k . 'DateCustom' ]['start'] )
							{
								$url = $url->setQueryString( $k . '_after', $values[ $k . 'DateCustom' ]['start']->getTimestamp() );
							}
							if ( $values[ $k . 'DateCustom' ]['end'] )
							{
								$url = $url->setQueryString( $k . '_before', $values[ $k . 'DateCustom' ]['end']->getTimestamp() );
							}
						}
						else
						{
							$url = $url->setQueryString( $k . '_after', $values[ $k . 'Date' ] );
						}
					}
				}
				\IPS\Output::i()->redirect( $url );
			}
		}

		return $form;
	}
	
	/**
	 * Handle quicksearch and redirect to correct results page
	 *
	 * @return	array
	 */
	public static function quicksearch()
	{
		$query = array();
		
		if ( \IPS\Request::i()->q )
		{
			$query['q'] = \IPS\Request::i()->q;
			$query['quick'] = 1;
			
			if ( \IPS\Request::i()->type != 'all' )
			{
				if ( mb_substr( \IPS\Request::i()->type, 0, 11 ) === 'contextual_' )
				{
					if ( $json = json_decode( mb_substr( \IPS\Request::i()->type, 11 ), TRUE ) )
					{
						foreach ( $json as $k => $v )
						{
							$query[ $k ] = $v;
						}
					}
				}
				else
				{
					$query['type'] = \IPS\Request::i()->type;
				}
			}
			
			if ( \IPS\Request::i()->search_and_or != \IPS\Settings::i()->search_default_operator )
			{
				$query['search_and_or'] = \IPS\Request::i()->search_and_or;
			}
			
			if ( \IPS\Request::i()->search_in == 'titles' )
			{
				$query['search_in'] = 'titles';
			}
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=search&controller=search', 'front', 'search' )->setQueryString( $query ) );
	}
	
	/**
	 * Get the different content type extensions
	 *
	 * @param	bool|\IPS\Member	$member		Check member access
	 * @return	array
	 */
	public static function contentTypes( $member = TRUE )
	{
		$types = array();
		foreach ( \IPS\Content::routedClasses( $member, FALSE, TRUE ) as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\Searchable' ) and $class::includeInSiteSearch() )
			{	
				$key = mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) );
				$types[ $key ] = $class;
			}
		}
		return $types;
	}
	
	/**
	 * Splits the search term into distinct matches
	 * e.g. one "two three" becomes ['one', 'two three']
	 *
	 * @return	string
	 */
	protected function _splitTermsForDisplay()
	{
		if( !isset( \IPS\Request::i()->q ) ){
			return json_encode( array() );
		}

		$words = preg_split("/[\s]*\\\"([^\\\"]+)\\\"[\s]*|[\s]*'([^']+)'[\s]*|[\s]+/", \IPS\Request::i()->q, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
		
		$words = \IPS\Content\Search\Index::i()->stemmedTerms( $words );

		foreach( $words as $idx => $word )
		{
			$words[ $idx ] = htmlentities( $word, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ); // ENT_QUOTES is because this will go in a HTML attribute (data-term="$value") so if you include a single quote in your search query, it can break
		}

		return json_encode( $words );
	}
	
	/**
	 * Global filter options (AJAX Request)
	 *
	 * @return	void
	 */
	protected function globalFilterOptions()
	{
		\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'search' )->globalSearchMenuOptions( explode( ',', \IPS\Request::i()->exclude ) ) );
	}
}
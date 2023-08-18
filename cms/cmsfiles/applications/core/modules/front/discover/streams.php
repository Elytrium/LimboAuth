<?php
/**
 * @brief		streams
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Jul 2015
 */

namespace IPS\core\modules\front\discover;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * streams
 */
class _streams extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'activity_stream', 'odkUpdate' => 'true']
	);

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( isset( \IPS\Request::i()->_nodeSelectName ) )
		{
			return $this->getContainerNodeElement();
		}
		
		/* Initiate the breadcrumb */
		\IPS\Output::i()->breadcrumb = array( array( \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' ), \IPS\Member::loggedIn()->language()->addToStack('activity') ) );

		/* Necessary CSS/JS */
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_streams.js', 'core' ) );
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_statuses.js', 'core' ) );
		\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams.css' ) );
		
		if ( \IPS\Theme::i()->settings['responsive'] )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/streams_responsive.css', 'core', 'front' ) );
		}

		/* Add any global CSS from other apps */
		foreach( \IPS\Application::applications() as $app )
		{
			\IPS\Output::i()->cssFiles	= array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'global.css', $app->directory, 'front' ) );
		}
		
		/* Execute */
		return parent::execute();
	}
	
	/**
	 * View Stream
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* If this request is from an auto-poll, kill it and exit */
		if ( !\IPS\Settings::i()->auto_polling_enabled && \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->after ) )
		{
			\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
			return;
		}
		
		/* RSS validate? */
		$member = NULL;

		if ( isset( \IPS\Request::i()->rss ) AND isset( \IPS\Request::i()->member ) AND isset( \IPS\Request::i()->key ) )
		{
			$member = \IPS\Member::load( \IPS\Request::i()->member );

			if ( !$member->member_id OR !\IPS\Login::compareHashes( $member->getUniqueMemberHash(), (string) \IPS\Request::i()->key ) )
			{
				$member = NULL;
			}

			/* If we do not have a specific member, and this member is not allowed to view the site, throw an error as they do not have permission */
			if ( ! $member and ! \IPS\Member::loggedIn()->group['g_view_board'] )
			{
				\IPS\Output::i()->error( 'stream_no_permission', '2C280/G', 403, '' );
			}
		}
		else if ( ! \IPS\Member::loggedIn()->group['g_view_board'] )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=login', 'front', 'login' )->setQueryString( 'ref', base64_encode( \IPS\Request::i()->url() ) ) );
		}


		$form = NULL;
		/* Viewing a particular stream? */
		if ( isset( \IPS\Request::i()->id ) )
		{
			/* Get it */
			try
			{
				$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C280/1', 404, '' );
			}

			/* In order to make most streams more efficient, if we are looking at newest items first, and we haven't set a custom date, then just look for the last 12 months max */
			if ( \IPS\Settings::i()->search_method != 'elastic' and $stream->date_type == 'all' and $stream->sort == 'newest' )
			{
				$stream->date_relative_days = 365;
				$stream->date_type = 'relative';
			}
			
			/* Suitable for guests? */
			if ( !\IPS\Member::loggedIn()->member_id and !$member and !( ( $stream->ownership == 'all' or $stream->ownership == 'custom' ) and $stream->read == 'all' and $stream->follow == 'all' and $stream->date_type != 'last_visit' ) )
			{
				\IPS\Output::i()->error( 'stream_no_permission', '2C280/3', 403, '' );
			}
			
			if ( \IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->default ) )
			{
				\IPS\Session::i()->csrfCheck();
				
				if ( \IPS\Request::i()->default )
				{
					\IPS\Member::loggedIn()->defaultStream = $stream->_id;
				}
				else
				{
					\IPS\Member::loggedIn()->defaultStream = NULL;
				}
				
				if ( \IPS\Request::i()->isAjax() )
				{
					$defaultStream = \IPS\core\Stream::defaultStream();
					
					if ( ! $defaultStream )
					{
						\IPS\Output::i()->json( array( 'title' => NULL ) );
					}
					else
					{
						\IPS\Output::i()->json( array(
							'url'   	=> $defaultStream->url(),
							'title' 	=> htmlspecialchars( $defaultStream->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ),
							'tooltip'	=> $defaultStream->_title, // We need to pass this individually for the tooltip that shows, but the JS itself will escape any entities
							'id'    	=> $defaultStream->_id
						 ) );
					}
				}
				
				\IPS\Output::i()->redirect( $stream->url() );
			}
			
			$form = $this->_buildForm( $stream );
			
			/* Set title and breadcrumb */
			
			\IPS\Output::i()->breadcrumb[] = array( $stream->url(), $stream->_title );
			\IPS\Output::i()->title = $stream->_title;
		}
		
		/* Or just everything? */
		else
		{
			if ( \IPS\Member::loggedIn()->member_id and isset( \IPS\Request::i()->default ) )
			{
				\IPS\Session::i()->csrfCheck();

				if ( \IPS\Request::i()->default )
				{
					\IPS\Member::loggedIn()->defaultStream = 0;
				}
				else
				{
					\IPS\Member::loggedIn()->defaultStream = NULL;
				}
				
				if ( \IPS\Request::i()->isAjax() )
				{
					$defaultStream = \IPS\core\Stream::defaultStream();
					
					if ( ! $defaultStream )
					{
						\IPS\Output::i()->json( array( 'title' => NULL ) );
					}
					else
					{
						\IPS\Output::i()->json( array(
							'url'   => \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' ),
							'title' => htmlspecialchars( $defaultStream->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ),
							'id'    => $defaultStream->_id
						 ) );
					}
				}
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' ) );
			}

			/* Start with a blank stream */
			$stream = \IPS\core\Stream::allActivityStream();
			$stream->default_view = "expanded";

			/* Set the title to "All Activity" */
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('all_activity');
		}
		
		/* Store the URL before we add query strings to it, etc */
		$streamUrl = $stream->url();
		
		/* Look for url params that can come from view switch or load more button */	
		/* but only if we haven't submitted the form on this request */
		if( !\IPS\Request::i()->stream_submitted )
		{
			$streamFields = array( 'include_comments', 'classes', 'ownership', 'custom_members', 'read', 'follow', 'followed_types', 'date_type', 'date_start', 'date_end', 'date_relative_days', 'sort', 'tags', 'solved' );

			/* Clubs enabled? */
			if( \IPS\Settings::i()->clubs )
			{
				$streamFields[]	= 'clubs';
			}
			
			/* Build and format field values */
			$_values = array();
			foreach ( \IPS\Request::i() as $requestKey => $requestField )
			{
				$field = str_replace( 'stream_', '', $requestKey );
				
				if ( $field == 'custom_members' and isset( \IPS\Request::i()->stream_custom_members ) )
				{
					$members = NULL;
					foreach( str_replace( "\r", '', explode( "\n", \IPS\Request::i()->stream_custom_members ) ) as $name )
					{
						try
						{
							$members[] = \IPS\Member::load( $name, 'name' );
						}
						catch( \OutOfRangeException $e ) { }
					}
					
					$_values['stream_custom_members'] = $members;
				}
				else if ( \in_array( $field, $streamFields ) && ( $field == 'classes' || $field == 'followed_types' ) && \is_array( \IPS\Request::i()->{ 'stream_' . $field } ) )
				{
					/* Some array values will come in as key=1 params, so we only need the keys here */
					$_values[ $requestKey ] = array_keys( $requestField );
				}
				else
				{
					$_values[ $requestKey ] = $requestField;
				}				
			}
			
			if ( isset( $_values['stream_club_filter'] ) and !isset( $_values['stream_club_select'] ) )
			{
				$_values['stream_club_select'] = 'select';
			}
			
			$formattedValues = $stream->formatFormValues( $_values );
			
			$rebuildForm = FALSE;

			/* Overwrite stream config if present in the request */
			foreach ( $streamFields as $k )
			{	
				$requestKey = 'stream_' . $k;
				
				if ( isset( \IPS\Request::i()->$requestKey ) or ( $k === 'clubs' and ( isset( \IPS\Request::i()->stream_club_select ) or isset( \IPS\Request::i()->stream_club_filter ) ) ) )
				{
					if ( $stream->$k != $formattedValues[ $k ] )
					{
						$stream->$k = $formattedValues[ $k ];
						$stream->baseUrl = $stream->baseUrl->setQueryString( 'stream_' . $k, \IPS\Request::i()->$requestKey );
						$rebuildForm = TRUE;
					}
				}			
			}
			
			/* Containers are special */
			if ( isset( \IPS\Request::i()->stream_containers ) and \is_array( \IPS\Request::i()->stream_containers ) )
			{ 
				/* Remove null/'' values as we no longer want to restrict by container for this class if that occurs */
				$cleanedContainers = array();
				foreach( \IPS\Request::i()->stream_containers as $class => $containers )
				{
					if ( $containers )
					{
						$cleanedContainers[ $class ] = $containers;
					}
				}
				
				if ( \count( array_diff_assoc( $cleanedContainers, $stream::containersToUrl( $stream->containers ) ) ) )
				{
					$stream->containers = $stream::containersFromUrl( $cleanedContainers );
					$stream->baseUrl = $stream->baseUrl->setQueryString( 'stream_containers', $cleanedContainers );
					$rebuildForm = TRUE;
				}
			}
			
			if ( isset( \IPS\Request::i()->id ) AND $rebuildForm )
			{
				/* reset the form to account for all the modifications to $stream class variables */
				$form = $this->_buildForm( $stream );
			}
		}

		/* Condensed or expanded? */
		$view = 'expanded';
		$streamID = ( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : 'all';

		if( isset( \IPS\Request::i()->view ) AND \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
		{
			$view = \IPS\Request::i()->view;

			\IPS\Request::i()->setCookie( 'stream_view_' . $streamID, \IPS\Request::i()->view, \IPS\DateTime::create()->add( new \DateInterval( 'P1Y' ) ) );

			if( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( $stream->url() );
			}
		}
		elseif ( isset( \IPS\Request::i()->cookie['stream_view_' . $streamID] ) )
		{
			$view =  \IPS\Request::i()->cookie['stream_view_' . $streamID];
		}
		else
		{
			$view = $stream->default_view;
		}

		/* Ensure correct params are set for view mode */
		if ( $view === 'condensed' )
		{
			$stream->include_comments = FALSE;
		}

		/* Build the query */
		$query = $stream->query( $member );

		/* Set page or the before/after date */
		$currentPage = 1;
		if ( isset( \IPS\Request::i()->page ) AND \intval( \IPS\Request::i()->page ) > 0 )
		{
			$currentPage = \IPS\Request::i()->page;
			$query->setPage( $currentPage );
		}
		
		$before = ( isset( \IPS\Request::i()->before ) and \is_numeric( \IPS\Request::i()->before ) ) ? (int) \IPS\Request::i()->before : null;
		$after  = ( isset( \IPS\Request::i()->after ) and \is_numeric( \IPS\Request::i()->after ) ) ? (int) \IPS\Request::i()->after : null;
		
		/* If we sort by oldest, then we need to switch these values */
		if ( $stream->sort == 'oldest' )
		{
			$tmp = $after;
			$after  = $before;
			$before = $tmp;
			unset( $tmp );
		}
		
		if ( isset( \IPS\Request::i()->latest ) )
		{
			if ( $stream->id and !$stream->include_comments )
			{
				$query->filterByLastUpdatedDate( \IPS\DateTime::ts( \IPS\Request::i()->latest )  );
			}
			else
			{
				$query->filterByCreateDate( \IPS\DateTime::ts( \IPS\Request::i()->latest ) );
			}
			
			$query->setLimit(350);
		}
		else if ( $before )
		{
			if ( $stream->id and !$stream->include_comments )
			{
				$query->filterByLastUpdatedDate( NULL, \IPS\DateTime::ts( $before ) );
			}
			else
			{
				$query->filterByCreateDate( NULL, \IPS\DateTime::ts( $before ) );
			}
		}
		if ( $after )
		{
			if ( $stream->id and !$stream->include_comments )
			{
				$query->filterByLastUpdatedDate( \IPS\DateTime::ts( $after ) );
			}
			else
			{
				$query->filterByCreateDate( \IPS\DateTime::ts( $after ) );
			}
		}

		/* Get the results */
		$results = $query->search( NULL, $stream->tags ? explode( ',', $stream->tags ) : NULL, ( $stream->include_comments ? \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY + \IPS\Content\Search\Query::TERM_OR_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS ) );
		
		/* Load data we need like the authors, etc */
		$results->init();
		
		/* Add in extra stuff? */
		if ( !isset( \IPS\Request::i()->id ) )
		{
			/* Is anything turned on? */
			$extra = array();
			foreach ( array( 'register', 'follow_member', 'follow_content', 'photo', 'like', 'votes', 'react', 'clubs' ) as $k )
			{
				$key = "all_activity_{$k}";
				if ( \IPS\Settings::i()->$key )
				{
					$extra[] = $k;
				}
			}
			if ( !empty( $extra ) )
			{
				$results = $results->addExtraItems( $extra, NULL, ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->after ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->after ) : NULL, ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->before ) ) ? \IPS\DateTime::ts( \IPS\Request::i()->before ) : NULL );
			}
		}

		/* If this is an AJAX request, just show the results */
		if ( \IPS\Request::i()->isAjax() )
		{
			$output = \IPS\Theme::i()->getTemplate('streams')->streamItems( $results, TRUE, ( $stream->id and !$stream->include_comments ) ? 'last_comment' : 'date', $view );

			$return = array(
				'title' => htmlspecialchars( $stream->_title, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ),
				'blurb' => $stream->blurb(),
				'config' => json_encode( $stream->config() ),
				'count' => \count( $results ),
				'results' => $output,
				'id' => ( $stream->id ) ? $stream->id : '',
				'url' => $stream->url()
			);

			\IPS\Output::i()->json( $return );
			return;
		}
		
		/* Display - RSS */
		if ( \IPS\Settings::i()->activity_stream_rss and isset( \IPS\Request::i()->rss ) )
		{
			$document = \IPS\Xml\Rss::newDocument( $stream->baseUrl, $stream->_title, sprintf( \IPS\Member::loggedIn()->language()->get( 'stream_rss_title' ), \IPS\Settings::i()->board_name, $stream->_title ) );
			
			foreach ( $results as $result )
			{
				if ( $result instanceof \IPS\Content\Search\Result\Content )
				{
					$result->addToRssFeed( $document );
				}
			}
			
			\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
			return;
		}
		
		/* Display - HTML */
		else
		{			
			/* What's the RSS Link? */
			$rssLink = NULL;
			if ( \IPS\Settings::i()->activity_stream_rss )
			{
				if ( isset( \IPS\Request::i()->id ) )
				{
					$rssLink = \IPS\Http\Url::internal( "app=core&module=discover&controller=streams&id={$stream->id}", 'front', 'discover_rss' );
					if ( \IPS\Member::loggedIn()->member_id )
					{
						$rssLink = $rssLink->setQueryString( 'member', \IPS\Member::loggedIn()->member_id )->setQueryString( 'key', \IPS\Member::loggedIn()->getUniqueMemberHash() );
					}
				}
				else
				{
					/* It's all activity! */
					$rssLink = \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_rss_all_activity' );
				}
			}
			
			/* Display */
			$output = \IPS\Theme::i()->getTemplate('streams')->stream( $stream, $results, $stream->id ? FALSE : TRUE, TRUE, ( $stream->id and !$stream->include_comments ) ? 'last_comment' : 'date', $view );
			
			\IPS\Output::i()->linkTags['canonical'] = (string) $streamUrl;
			\IPS\Output::i()->jsVars['stream_config'] = $stream->config();
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('streams')->streamWrapper( $stream, $output, $form, $rssLink, isset( \IPS\Request::i()->id ) and $stream->member and \IPS\Member::loggedIn()->member_id and $stream->member != \IPS\Member::loggedIn()->member_id );
		}
	}
	
	/**
	 * Create a new stream
	 *
	 * @return	void
	 */
	public function create()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C280/A', 403, '' );
		}
		
		$stream = new \IPS\core\Stream;
		$stream->member = \IPS\Member::loggedIn()->member_id;
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'create_new_stream' );
		\IPS\Output::i()->output = $this->_buildForm( $stream );
	}
	
	/**
	 * Edit a stream's title
	 *
	 * @return void
	 */
	public function edit()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C280/6', 403, '' );
		}
		
		try
		{
			$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C280/7', 404, '' );
		}
		
		if ( $stream->member != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C280/8', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Text( 'stream_title', $stream->title, NULL, array( 'maxLength' => 255 ) ) );
		
		if ( $values = $form->values() )
		{
			if ( isset( $values['stream_title'] ) and $values['stream_title'] )
			{
				$stream->title = $values['stream_title'];
				$stream->save();
				$this->_rebuildStreams();
				\IPS\Output::i()->redirect( $stream->url() );
			}	
		}
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->genericBlock( $form, NULL, 'ipsPad' ), 200, 'text/html' );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'stream_edit_title' );
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Copy a stream
	 *
	 * @return	void
	 */
	public function copy()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C280/4', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$stream = clone \IPS\core\Stream::load( \IPS\Request::i()->id );
			$stream->member = \IPS\Member::loggedIn()->member_id;
			$stream->save();
			$this->_rebuildStreams();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C280/5', 404, '' );
		}

		\IPS\Output::i()->redirect( $stream->url() );
	}

	/**
	 * Deletes a new stream
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
			if ( !$stream->member or $stream->member != \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C280/2', 404, '' );
		}
		
		$stream->delete();
		$this->_rebuildStreams();

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' ) );
	}
	
	/**
	 * Get the container Node form element HTML
	 *
	 * @return string
	 */
	protected function getContainerNodeElement()
	{
		$currentContainers = array();
		$stream = NULL;
		if ( isset( \IPS\Request::i()->id ) )
		{
			$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
			$currentContainers = $stream->containers ? json_decode( $stream->containers, TRUE ) : array();
			
			if ( isset( \IPS\Request::i()->stream_containers ) )
			{
				$currentContainers = json_decode( $stream::containersFromUrl( \IPS\Request::i()->stream_containers ), TRUE );
			}
		}
 
		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			if( is_subclass_of( $class, 'IPS\Content\Searchable' ) )
			{
				$classes[ $class ] = $class::$title . '_pl';
				if ( isset( $class::$containerNodeClass ) and $class == \IPS\Request::i()->className )
				{
					$url = $stream ? $stream->baseUrl->setQueryString( 'className', $class ) : \IPS\Request::i()->url()->setQueryString( 'className', $class );
					$containerClass = $class::$containerNodeClass;
					$field = new \IPS\Helpers\Form\Node( 'stream_containers_' . $class::$title, isset( $currentContainers[ $class ] ) ? $currentContainers[ $class ] : array(), NULL, array( 'url' => $url, 'class' => $class::$containerNodeClass, 'multiple' => TRUE, 'permissionCheck' => $containerClass::searchableNodesPermission(), 'clubs' => ( \IPS\Settings::i()->clubs AND \IPS\IPS::classUsesTrait( $containerClass, 'IPS\Content\ClubContainer' ) ) ), NULL, NULL, NULL, 'stream_containers_' . $class::$title );
					$field->label = \IPS\Member::loggedIn()->language()->addToStack( 'stream_narrow_by_container_label', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $containerClass::$nodeTitle ) ) ) );
					
					return \IPS\Output::i()->json( array( 'node' => \IPS\Theme::i()->getTemplate('streams')->filterFormContentTypeContent( $field, $class, \IPS\Request::i()->key ) ) );
				}
			}
		}

		return NULL;
	}
	
	/**
	 * Get the club selector form element HTML
	 *
	 * @return string
	 */
	protected function getClubElement()
	{
		if( !\IPS\Settings::i()->clubs or !\IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'name', TRUE, array(), \IPS\Settings::i()->clubs_require_approval ? array( 'approved=1' ) : NULL, TRUE ) )
		{
			return \IPS\Output::i()->json( array( 'field' => '' ) );
		}

		$clubOptions = array();
		foreach ( \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'name', TRUE, array(), \IPS\Settings::i()->clubs_require_approval ? array( 'approved=1' ) : NULL ) as $club )
		{
			$clubOptions[ "c{$club->id}" ] = $club->name;
		}
		
		if ( isset( \IPS\Request::i()->stream_club_select ) )
		{
			switch ( \IPS\Request::i()->stream_club_select )
			{
				case 'all':
					$value = 0;
					break;
				
				case 'none':
					$value = array();
					break;
					
				default:
					$value = explode( ',', \IPS\Request::i()->stream_club_filter );
					break;
			}
		}
		else
		{
			$value = 0;
		}
										
		$field = new \IPS\Helpers\Form\Select( 'stream_club_filter_dummy', $value, FALSE, array( 'options' => $clubOptions, 'parse' => 'normal', 'multiple' => TRUE, 'noDefault' => TRUE, 'unlimited' => 0, 'unlimitedLang' => 'stream_club_select_all' ), NULL, NULL, NULL, 'stream_club_filter' );
		$field->label = \IPS\Member::loggedIn()->language()->addToStack('stream_club_filter');
		
		return \IPS\Output::i()->json( array( 'field' => \IPS\Theme::i()->getTemplate('streams')->filterFormClubs( $field ) ) );
	}
	
	/**
	 * Build form
	 *
	 * @param	\IPS\core\Stream	$stream	The stream
	 * @return	string
	 */
	protected function _buildForm( \IPS\core\Stream &$stream )
	{
		/* Build form */
		$form = new \IPS\Helpers\Form( 'stream', 'continue', ( $stream->id ? $stream->url() : NULL ) );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
				
		$stream->form( $form, 'Text', !$stream->id );
		$redirectAfterSave = FALSE;		
		
		/* Note if it's custom */
		if ( $stream->member && \IPS\Member::loggedIn()->member_id )
		{
			$form->hiddenValues['__custom_stream'] = TRUE;
		}
		
		if ( $stream->member )
		{
			$form->hiddenValues['__stream_owner'] = $stream->member;
		}
		
		if ( $stream->default_view )
		{
			$form->hiddenValues['stream_default_view'] = $stream->default_view;
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* As the node container form elements are not in the actual form, we need to work some magic. 
				And by magic, I do mean a sort of hacky thing. First, if we are editing a stream, we need to get our existing stream filters
				and put these into an array. Then we loop over the input and overwrite the existing filters with what was submitted. If the user
				never loaded the container filters we'll retain what we had (expected) but if they did and adjusted or removed container filters
				then we'll update with what they submitted (expected).
			*/
			if( $stream->id )
			{
				$currentContainers = $stream->containers ? json_decode( $stream->containers, TRUE ) : array();

				foreach( $currentContainers as $class => $containers )
				{
					$values['stream_containers_' . $class::$title ] = array_combine( $containers, $containers );
				}
			}

			foreach ( \IPS\Request::i() as $k => $v )
			{
				if ( mb_substr( $k, 0, 18 ) == 'stream_containers_' )
				{
					if( $v )
					{
						$vals = explode( ',', $v );
						$values[ $k ] = array_combine( $vals, $vals );
					}
					elseif( isset( $values[ $k ] ) )
					{
						$values[ $k ] = NULL;
					}
				}
			}

			/* Update only */
			if ( isset( \IPS\Request::i()->updateOnly ) )
			{
				$formattedValues = $stream->formatFormValues( $values );

				foreach ( array( 'include_comments', 'classes', 'containers', 'clubs', 'ownership', 'custom_members', 'read', 'follow', 'followed_types', 'date_type', 'date_start', 'date_end', 'date_relative_days', 'sort', 'tags', 'solved' ) as $k )
				{
					$requestKey = 'stream_' . $k;

					if ( array_key_exists( $k, $formattedValues ) AND $stream->$k != $formattedValues[ $k ] )
					{
						$stream->$k = $formattedValues[ $k ];
						$stream->baseUrl = $stream->baseUrl->setQueryString( 'stream_' . $k, \IPS\Request::i()->$requestKey );
					}
				}
			}			
			/* Update & Save */
			else
			{
				if ( !$stream->id )
				{
					$stream->position = \IPS\Db::i()->select( 'MAX(position)', 'core_streams', array( '`member`=?', \IPS\Member::loggedIn()->member_id )  )->first() + 1;
					$redirectAfterSave = TRUE;
				}
				else
				{
					if ( !$stream->member or $stream->member != \IPS\Member::loggedIn()->member_id )
					{
						\IPS\Output::i()->error( 'no_module_permission', '2C280/9', 403, '' );
					}
				}
			
				$stream->saveForm( $stream->formatFormValues( $values ) );
				
				$this->_rebuildStreams();
				
				if( $redirectAfterSave )
				{
					\IPS\Output::i()->redirect( $stream->url() );
				}
			}
		}
		
		/* Display */
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'streams', 'core' ), $stream->id ? 'filterInlineForm' : 'filterCreateForm' ) );
	}

	/**
	 *
	 */
	protected function subscribe()
	{
		/* Get it */
		try
		{
			$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C280/C', 404, '' );
		}

		if( !$stream->canSubscribe() )
		{
			\IPS\Output::i()->error( 'stream_no_permission', '2C280/D', 403, '' );
		}

		$form = new \IPS\Helpers\Form('subscribe', 'stream_subscribe_button');
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Radio('stream_subscription_frequency', NULL , TRUE, ['options' => ['daily' => 'stream_daily', 'weekly' => 'stream_weekly']] ));

		if( $values = $form->values() )
		{
			$streamSub = new \IPS\core\Stream\Subscription();
			$streamSub->member_id = \IPS\Member::loggedIn()->member_id;
			$streamSub->stream_id = $stream->id;
			$streamSub->frequency = $values['stream_subscription_frequency'];

			/* Set the fake last sent time to get the next run populated correct */
			$streamSub->sent =  $values['stream_subscription_frequency'] == 'daily' ? \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() : \IPS\DateTime::create()->sub( new \DateInterval( 'P1W' ) )->getTimestamp();
			$streamSub->added = time();
			$streamSub->save();

			/* Enable the task */
			$taskKey =  $values['stream_subscription_frequency'] == 'daily' ? 'dailyStreamSubscriptions' : 'weeklyStreamSubscriptions';

			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', $taskKey ) );

			\IPS\Output::i()->redirect( $stream->url(), 'subscribed' );
		}
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( \IPS\Output::i()->output  );
		}
		else
		{
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html' );
		}
	}

	/**
	 * Unsubscribe from a stream
	 */
	protected function unsubscribe()
	{
		/* Get it */
		try
		{
			$stream = \IPS\core\Stream::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException|\UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C280/F', 404, '' );
		}
		\IPS\Request::i()->confirmedDelete( 'stream_unsubscribe', 'confirm_stream_unsubscribe', 'stream_unsubscribe_button');
		if ( $streamSubscription = \IPS\core\Stream\Subscription::loadByStreamAndMember( $stream ) )
		{
			$streamSubscription->delete();
		}
		else
		{
			\IPS\Output::i()->error( 'stream_no_permission', '2C280/E', 403, '' );
		}
		
		\IPS\Output::i()->redirect( $stream->url(), 'unsubscribed' );
 	}

	/**
	 * Unsubscribe from a stream or from all streams from email
	 * If we're logged in, we can send them right to the normal form.
	 * Otherwise, they get a special guest page using the gkey as an authentication key.
	 *
	 * @return void
	 */
	protected function unSubscribeFromEmail()
	{
		/* Logged in? */
		if ( \IPS\Member::loggedIn()->member_id )
		{
			/* Go to the normal page */
			\IPS\Output::i()->redirect( $this->url->setQueryString('do','unsubscribe')->setQueryString('id', \IPS\Request::i()->id));
		}

		if ( !empty( \IPS\Request::i()->gkey ) )
		{
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_stream_subscriptions', [ 'id=?', \IPS\Request::i()->id ])->first();
			}
			catch ( \UnderFlowException $e )
			{
				\IPS\Output::i()->error('node_error', '2C280/E');
			}

			$member = \IPS\Member::load( $current['member_id'] );

			if ( md5( $member->email . ';' . $member->ip_address . ';' . $member->joined->getTimestamp() ) != \IPS\Request::i()->gkey )
			{
				\IPS\Output::i()->error( 'stream_no_permission', '2C280/E', 403, '' );
			}

			$form = new \IPS\Helpers\Form( '', 'update_stream_subscriptions' );
			$form->class = 'ipsForm_vertical';

			if ( $streams = \IPS\core\Stream\Subscription::getSubscribedStreams( $member ) AND $count = \count( $streams ) AND $count == 1 )
			{
				$title = \IPS\core\Stream\Subscription::constructFromData($current)->stream->_title;
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'streamsubscription_guest_thing', FALSE, array('sprintf' => array($title)) );
				$form->add( new \IPS\Helpers\Form\Checkbox( 'guest_unsubscribe_single', 'single', FALSE, array('disabled' => true) ) );
				\IPS\Member::loggedIn()->language()->words['guest_unsubscribe_single'] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_subscription_unsubscribe_thing', FALSE, array('sprintf' => array($title)) );
			}
			else
			{
				$title = \IPS\Member::loggedIn()->language()->addToStack( 'stream_subscriptions');
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'streamsubscription_guest_all' );
				$form->add( new \IPS\Helpers\Form\Radio( 'guest_unsubscribe_choice', 'single', FALSE, array(
					'options' => array(
						'single' => \IPS\Member::loggedIn()->language()->addToStack( 'streamsubscription_guest_thing', FALSE, array('sprintf' => array( \IPS\core\Stream\Subscription::constructFromData($current)->stream->_title  ) ) ),
						'all' => \IPS\Member::loggedIn()->language()->addToStack( 'streamsubscription_guest_all', FALSE, array('pluralize' => array( $count )) ),
					),
					'descriptions' => array(
						'single' => \IPS\Member::loggedIn()->language()->addToStack( 'follow_guest_unfollow_thing_desc' ),
						'all' => \IPS\Member::loggedIn()->language()->addToStack( 'follow_guest_unfollow_all_desc', FALSE, array('sprintf' => array(base64_encode( \IPS\Http\Url::internal( 'app=core&module=system&controller=followed' ) ))) )
					)
				) ) );
			}

			if ( $values = $form->values() )
			{
				if( isset( $values['guest_unsubscribe_choice'] ) and $values['guest_unsubscribe_choice'] == 'all' )
				{
					foreach ( \IPS\core\Stream\Subscription::getSubscribedStreams( $member ) as $subscription )
					{
						$subscription->delete();
					}
				}
				else
				{
					$subscription = \IPS\core\Stream\Subscription::loadByStreamAndMember( \IPS\core\Stream::load( $current['stream_id'] ), $member );
					$subscription->delete();
				}
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( '' ), 'unsubscribed' );
			}

			\IPS\Output::i()->sidebar['enabled'] = FALSE;
			\IPS\Output::i()->bodyClasses[] = 'ipsLayout_minimal';
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'streams' )->unsubscribeStream( $title, $member, $form, !isset( \IPS\Request::i()->activity_stream_subscription ) ? FALSE : \IPS\Request::i()->activity_stream_subscription );
		}
	}

	
	/**
	 * Rebuild logged in member's streams
	 *
	 * @return	void
	 */
	protected function _rebuildStreams()
	{
		$default = \IPS\Member::loggedIn()->defaultStream;
		\IPS\Member::loggedIn()->member_streams = json_encode( array( 'default' => $default, 'streams' => iterator_to_array( \IPS\Db::i()->select( 'id, title', 'core_streams', array( '`member`=?', \IPS\Member::loggedIn()->member_id ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->setKeyField('id')->setValueField('title') ) ) );
		\IPS\Member::loggedIn()->save();
	}
}

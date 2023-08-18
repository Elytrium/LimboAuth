<?php
/**
 * @brief		Content Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		5 Jul 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Controller
 */
class _Controller extends \IPS\Helpers\CoverPhoto\Controller
{
	/**
	 * @brief	Should views and item markers be updated by AJAX requests?
	 */
	protected $updateViewsAndMarkersOnAjax = FALSE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* We do this to prevent SQL errors with page offsets */
		if ( isset( \IPS\Request::i()->page ) )
		{
			\IPS\Request::i()->page	= \intval( \IPS\Request::i()->page );
			if ( !\IPS\Request::i()->page OR \IPS\Request::i()->page < 1 )
			{
				\IPS\Request::i()->page	= 1;
			}
		}

		/* Ensure JS loaded for forms/content functions such as moderation */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_core.js', 'core' ) );

		parent::execute();

		/* Do this to prevent non-existent and non-accessible items from returning a status of 200 */
		if ( empty( \IPS\Output::i()->output ) AND !\IPS\Request::i()->_bypassItemIdCheck AND isset( \IPS\Request::i()->id ) )
		{
			$contentModel = static::$contentModel;
			try
			{
				$item = $contentModel::load( \IPS\Request::i()->id );
				if ( !$item->can( 'read' ) )
				{
					\IPS\Output::i()->error( 'node_error', '2S136/1X', 403, '' );
				}
			}
			catch ( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2S136/1Y', 404, '' );
			}
		}
	}

	/**
	 * View Item
	 *
	 * @return	\IPS\Content\Item|NULL
	 */
	protected function manage()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			/* Have we moved? If a user has loaded a forum, and a moderator merges two topics leaving a link, then we need to account for this if the
				user happens to hover over the topic that was removed before refreshing the page. */
			if ( isset( $class::$databaseColumnMap['state'] ) AND isset( $class::$databaseColumnMap['moved_to'] ) )
			{
				$stateColumn	= $class::$databaseColumnMap['state'];
				$movedToColumn	= $class::$databaseColumnMap['moved_to'];
				$movedTo		= explode( '&', $item->$movedToColumn );
				
				if ( $item->$stateColumn == 'link' OR $item->$stateColumn == 'merged' )
				{
					try
					{
						$moved = $class::loadAndCheckPerms( $movedTo[0] );

						if ( \IPS\Request::i()->isAjax() AND \IPS\Request::i()->preview )
						{
							return NULL;
						}

						\IPS\Output::i()->redirect( $moved->url(), '', 301 );
					}
					catch( \OutOfRangeException $e ) { }
				}
			}
			
			/* If this is an AJAX request (like the topic hovercard), we don't want to do any of the below like update views and mark read */
			if ( \IPS\Request::i()->isAjax() and !$this->updateViewsAndMarkersOnAjax )
			{
				/* But we do want to mark read if we are paging through the content */
				if( $item instanceof \IPS\Content\ReadMarkers AND isset( \IPS\Request::i()->page ) AND \IPS\Request::i()->page AND $item->isLastPage() )
				{
					$item->markRead();
				}

				/* We also want to update the views if we have a page parameter */
				if( $item instanceof \IPS\Content\ReadMarkers AND isset( \IPS\Request::i()->page ) AND \IPS\Request::i()->page )
				{
					if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\ViewUpdates' ) )
					{
						$item->updateViews();
					}
					else
					{
						$idColumn = $class::$databaseColumnId;
						if ( \in_array( 'IPS\Content\Views', class_implements( $class ) ) AND isset( $class::$databaseColumnMap['views'] ) )
						{
							$countUpdated = false;
							if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
							{
								try
								{
									\IPS\Redis::i()->zIncrBy( 'topic_views', 1, $class .'__' . $item->$idColumn );
									$countUpdated = true;
								}
								catch( \Exception $e ) {}
							}
							
							if ( ! $countUpdated )
							{
								\IPS\Db::i()->insert( 'core_view_updates', array(
										'classname'	=> $class,
										'id'		=> $item->$idColumn
								) );
							}
						}
					}
				}

				return $item;
			}

			/* Do we need to convert any legacy URL parameters? */
			if( $redirectToUrl = $item->checkForLegacyParameters() )
			{
				\IPS\Output::i()->redirect( $redirectToUrl );
			}

			/* Get ready to store data layer info */
			$dataLayer = \IPS\Settings::i()->core_datalayer_enabled;
			$dataLayerProperties = array();
			if ( $dataLayer )
			{
				$dataLayerProperties = $item->getDataLayerProperties();
			}

			/* Check we're on a valid page */
			$paginationType = 'comment';
			if ( isset( \IPS\Request::i()->tab ) and  \IPS\Request::i()->tab === 'reviews' )
			{
				$paginationType = 'review';
			}
			
			$methodName = "{$paginationType}PageCount";
			$pageCount = $item->$methodName();

			if ( isset( \IPS\Request::i()->page ) )
			{
				$paginationType	= NULL;
				$container		= ( isset( $item::$databaseColumnMap['container'] ) ) ? $item->container() : NULL;

				if( $item::supportsComments( NULL, $container ) )
				{
					$paginationType	= 'comment';
				}

				if( ( isset( \IPS\Request::i()->tab ) and \IPS\Request::i()->tab === 'reviews' AND $item::supportsReviews( NULL, $container ) ) OR 
					( $item::supportsReviews( NULL, $container ) AND $paginationType === NULL ) )
				{
					$paginationType = 'review';
				}

				if( $paginationType !== NULL )
				{
					$methodName = "{$paginationType}PageCount";
					$pageCount = $item->$methodName();
					if ( $pageCount and \IPS\Request::i()->page > $pageCount )
					{
						$lastPageMethod = 'last' . mb_ucfirst( $paginationType ) . 'PageUrl';
						\IPS\Output::i()->redirect( $item->$lastPageMethod(), NULL, 303 );
					}

					$dataLayerProperties['page_number'] = \intval( \IPS\Request::i()->page );
				}

				/* Add rel tags */
				if( \IPS\Request::i()->page != 1 ) 
				{
					\IPS\Output::i()->linkTags['first'] = (string) $item->url();

					if( \IPS\Request::i()->page - 1 > 1 )
					{
						\IPS\Output::i()->linkTags['prev'] = (string) $item->url()->setPage( 'page', \IPS\Request::i()->page - 1 );
					}
					else
					{
						\IPS\Output::i()->linkTags['prev'] = (string) $item->url();
					}
				}
				/* If we literally requested ?page=1 add canonical tag to get rid of the page query string param */
				elseif( isset( $item->url()->data[ \IPS\Http\Url::COMPONENT_QUERY ]['page' ] ) )
				{
					\IPS\Output::i()->linkTags['canonical'] = (string) $item->url();
				}
			}

			/* Add rel tags */
			if ( $pageCount > 1 AND ( !\IPS\Request::i()->page OR $pageCount > \IPS\Request::i()->page ) )
			{
				\IPS\Output::i()->linkTags['next'] = (string) $item->url()->setPage( 'page', ( \IPS\Request::i()->page ?: 1 ) + 1 );
			}
			if ( $pageCount > 1 AND ( !\IPS\Request::i()->page OR $pageCount != \IPS\Request::i()->page ) )
			{
				\IPS\Output::i()->linkTags['last'] = (string) $item->url()->setPage( 'page', $pageCount );
			}

			/* Update Views */
			$idColumn = $class::$databaseColumnId;
			if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\ViewUpdates' ) )
			{
				$item->updateViews();
			}
			else
			{
				if ( \in_array( 'IPS\Content\Views', class_implements( $class ) ) AND isset( $class::$databaseColumnMap['views'] ) )
				{
					$countUpdated = false;
					if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
					{
						try
						{
							\IPS\Redis::i()->zIncrBy( 'topic_views', 1, $class .'__' . $item->$idColumn );
	
							$countUpdated = true;
						}
						catch( \Exception $e ) {}
					}
	
					if( \IPS\Application::appIsEnabled( 'cloud' ) and \IPS\cloud\Realtime::i()->isEnabled('trending') and $class::$includeInTrending )
					{
						try
						{
							/* Score by timestamp for trending */
							\IPS\Redis::i()->zIncrBy( 'trending', time() * 0.1, $class .'__' . $item->$idColumn );
						}
						catch( \BadMethodCallException | \RedisException $e ) {}
					}
					
					if ( ! $countUpdated )
					{
						\IPS\Db::i()->insert( 'core_view_updates', array(
							'classname'	=> $class,
							'id'		=> $item->$idColumn
						) );
					}
				}
			}
						
			/* Mark read */
			if( $item instanceof \IPS\Content\ReadMarkers )
			{	
				/* Note time last read before we mark it read so that the line is in the right place */
				$item->timeLastRead();
				
				if ( $item->isLastPage() )
				{
					$item->markRead();
				}
			}
			
			/* Set navigation and title */
			$this->_setBreadcrumbAndTitle( $item, FALSE );
			
			/* Set meta tags */
			\IPS\Output::i()->linkTags['canonical'] = (string) ( \IPS\Request::i()->page > 1 ) ? $item->url()->setPage( 'page', \IPS\Request::i()->page ) : $item->url() ;
			\IPS\Output::i()->metaTags['og:title'] = $item->mapped( 'title' );
			\IPS\Output::i()->metaTags['og:type'] = 'website';
			\IPS\Output::i()->metaTags['og:url'] = (string) $item->url();
			
			/* Do not set description tags for page 2+ as you end up with duplicate tags */
			if ( \IPS\Request::i()->page < 2 )
			{
				\IPS\Output::i()->metaTags['description'] = $item->metaDescription();
				/* If we had $_SESSION['_findComment'] and a specific comment's text was pulled, that var would have been wiped out on the first call */
				\IPS\Output::i()->metaTags['og:description'] = \IPS\Output::i()->metaTags['description'];
			}

			/* Facebook Pixel */
			$itemId = $class::$databaseColumnId;
			\IPS\core\Facebook\Pixel::i()->PageView = array(
				'item_id' => $item->$itemId,
				'item_name' => $item->mapped( 'title' ),
				'item_type' => isset( $class::$contentType ) ? $class::$contentType : $class::$title,
				'category_name' => isset( $item::$databaseColumnMap['container'] ) ? $item->container()->_title : NULL
			);

			/* Data Layer */
			if ( $dataLayer )
			{
				if ( !\IPS\Request::i()->isAjax() )
				{
					\IPS\core\DataLayer::i()->addEvent( 'content_view', $dataLayerProperties );
				}
				unset( $dataLayerProperties['ips_key'] );
				foreach ( $dataLayerProperties as $key => $value )
				{
					\IPS\core\DataLayer::i()->addContextProperty( $key, $value );
				}
			}

			if( $item->mapped( 'updated' ) OR $item->mapped( 'last_comment' ) OR $item->mapped( 'last_review' ) )
			{
				\IPS\Output::i()->metaTags['og:updated_time'] = \IPS\DateTime::ts( $item->mapped( 'updated' ) ? $item->mapped( 'updated' ) : ( $item->mapped( 'last_comment' ) ? $item->mapped( 'last_comment' ) : $item->mapped( 'last_review' ) ) )->rfc3339();
			}

			$tags = array();

			if( $item->prefix() !== NULL )
			{
				$tags[]	= $item->prefix();
			}

			if( $item->tags() !== NULL )
			{
				$tags = array_merge( $tags, $item->tags() );
			}

			if( \count( $tags ) )
			{
				\IPS\Output::i()->metaTags['keywords'] = implode( ', ', $tags );
			}
			
			/* Add contextual search options */
			if( $item instanceof \IPS\Content\Searchable )
			{
				\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_' . $item::$title ) ] = array( 'type' => mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ), 'item' => $item->$idColumn );

				try
				{
					$container = $item->container();
					\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_' . $container::$nodeTitle ) ] = array( 'type' => mb_strtolower( str_replace( '\\', '_', mb_substr( $class, 4 ) ) ), 'nodes' => $container->_id );
				}
				catch ( \BadMethodCallException $e ) { }
			}
			
			/* Return */
			return $item;
		}
		catch ( \LogicException $e )
		{
			try
			{
				\IPS\Output::i()->redirect( $class::getRedirectFrom( \IPS\Request::i()->id )->url(), '', 301 );
			}
			catch( \OutOfRangeException $e ) { }

			return NULL;
		}
	}
	
	/**
	 * AJAX - check for new replies
	 *
	 * @return	\IPS\Content\Item|NULL
	 */
	protected function checkForNewReplies()
	{
		\IPS\Session::i()->csrfCheck();

		/* If auto-polling isn't enabled, kill the polling now */
		if ( !\IPS\Settings::i()->auto_polling_enabled )
		{
			\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
			return;
		}

		/* If we're filtering the topic (live topic questions) then the max commentID in the source will not be the max commentID possible
		   as not all comments will be shown */
		if ( isset( \IPS\Request::i()->ltqid ) )
		{
			\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
			return;
		}

		/* If guest page caching is on, and we're a guest, disable polling */
		if ( !\IPS\Member::loggedIn()->member_id and \IPS\CACHE_PAGE_TIMEOUT )
		{
			\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
			return;
		}

		try
		{
			/* no need for polling for embeds */
			if ( !isset( static::$contentModel ) )
			{
				\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
				return;
			}

			$class = static::$contentModel;

			/* no need for polling if the content item doesn't have comments */
			if ( !isset( $class::$commentClass ) )
			{
				\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
				return;
			}

			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$commentClass = $class::$commentClass;

			/* Don't auto-poll archived content, it never gets updated */
			if( isset( $item::$archiveClass ) AND method_exists( $item, 'isArchived' ) AND $item->isArchived() )
			{
				\IPS\Output::i()->json( array( 'error' => 'auto_polling_disabled' ) );
				return;
			}

			$commentIdColumn = $commentClass::$databaseColumnId;
			$commentDateColumn = $commentClass::$databaseColumnMap['date'];
			
			/* The form field has an underscore, but this value is sent in a query string value without an underscore via AJAX */
			if( ! \IPS\Request::i()->lastSeenID or ! \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->json( array( 'count' => 0 ) );
			}

			$lastComment = $commentClass::load( \IPS\Request::i()->lastSeenID );
			$authorColumn = $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['author'];

            $where = array();

            /* Ignored Users */
            if( ! \IPS\Member::loggedIn()->members_bitoptions['has_no_ignored_users'] )
            {
                $ignored = iterator_to_array( \IPS\Db::i()->select( 'ignore_ignore_id', 'core_ignored_users', array( 'ignore_owner_id=? and ignore_messages=?', \IPS\Member::loggedIn()->member_id, 1 ) ) );
                if( \count( $ignored ) )
                {
                    $where[] = array( \IPS\Db::i()->in( $authorColumn, $ignored, TRUE ) );
                }
            }

            /* We will fetch up to 200 comments - anything over this is excessive */
			$newComments = $item->comments( 200, 0, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $lastComment->$commentDateColumn ), array_merge( $where, array( "{$authorColumn} != " . \IPS\Member::loggedIn()->member_id ) ) );
			
			/* Get the next to last comment for the spillover link as PMs do not support read markers */
			$nextLastComment = NULL;
			if( \count( $newComments ) )
			{
				$nextLastComment = reset( $newComments );
			}
			
			if ( \IPS\Request::i()->type === 'count' )
			{
				$data = array(
					'totalNewCount'	=> (int) \count( $newComments ), 
					'count'			=> (int) \count( $newComments ), 	/* This is here for legacy purposes only */
					'perPage'		=> $class::getCommentsPerPage(),
					'totalCount'	=> $item->mapped( 'num_comments' ),
					'title'			=> $item->mapped( 'title' ) ,
					'spillOverUrl'	=> ( $nextLastComment ) ? $nextLastComment->url() : $item->url(),
				);

				if( $data['count'] === 1 ){
					$itemData = reset( $newComments );
					$author = $itemData->author();

					$data['name'] = htmlspecialchars( $author->name, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE );
					$data['photo'] = (string) $author->photo;
				}

				\IPS\Output::i()->json( $data );
			}
			else
			{
				$output = array();
				$lastId = 0;
				foreach ( $newComments as $newComment )
				{
					$output[] = $newComment->html();
					$lastId = ( $newComment->$commentIdColumn > $lastId ) ? $newComment->$commentIdColumn : $lastId;
				}

				/* Only mark as read if we'll be staying on this page, otherise we'll be marking it read despite
					the user not having seen everything */
				if( (int) \IPS\Request::i()->showing + (int) \count( $newComments ) <= $class::getCommentsPerPage() )
				{
					$item->markRead();
				}
			}
			
			\IPS\Output::i()->json( array( 
				'content' => \IPS\Output::i()->replaceEmojiWithImages( $output ), 
				'id' => $lastId, 
				'totalCount' => $item->mapped( 'num_comments' ), 
				'totalNewCount' => (int) \count( $newComments ), 
				'perPage' => $class::getCommentsPerPage(),
				'spillOverUrl' => ( $nextLastComment ) ? $nextLastComment->url() : $item->url()
			) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->json( $e->getMessage(), 500 );
		}
	}
	
	/**
	 * Edit Item
	 *
	 * @return	void
	 */
	protected function edit()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			// We check if the form has been submitted to prevent the user loosing their content
			if ( isset( \IPS\Request::i()->form_submitted ) )
			{
				if ( ! $item->couldEdit() )
				{
					throw new \OutOfRangeException;
				}
			}
			else
			{
				if ( ! $item->canEdit() )
				{
					throw new \OutOfRangeException;
				}
			}
			
			$container = NULL;
			try
			{
				$container = $item->container();
			}
			catch ( \BadMethodCallException $e ) {}

			/* Build the form */
			$form = $item->buildEditForm();

			if ( $values = $form->values() )
			{
				$titleField = $item::$databaseColumnMap['title'];
				$oldTitle = $item->$titleField;

				if ( $item->canEdit() )
				{				
					$item->processForm( $values );
					if ( isset( $item::$databaseColumnMap['updated'] ) )
					{
						$column = $item::$databaseColumnMap['updated'];
						$item->$column = time();
					}
	
					if ( isset( $item::$databaseColumnMap['date'] ) and isset( $values[ $item::$formLangPrefix . 'date' ] ) )
					{
						$column = $item::$databaseColumnMap['date'];
	
						if ( $values[ $item::$formLangPrefix . 'date' ] instanceof \IPS\DateTime )
						{
							$item->$column = $values[ $item::$formLangPrefix . 'date' ]->getTimestamp();
						}
						else
						{
							$item->$column = time();
						}
					}

					$item->save();
					$item->processAfterEdit( $values );

					/* Moderator log */
					$toLog = array( $item::$title => TRUE, $item->url()->__toString() => FALSE, $item::$title => TRUE, $item->mapped( 'title' ) => FALSE );
					
					if ( $oldTitle != $item->$titleField )
					{
						array_push( $toLog, $oldTitle ); 
					}
					
					\IPS\Session::i()->modLog( 'modlog__item_edit', $toLog, $item );

					\IPS\Output::i()->redirect( $item->url() );
				}
				else
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'edit_no_perm_err' );
				}
			}
			
			$this->_setBreadcrumbAndTitle( $item );

			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->editContentForm( \IPS\Member::loggedIn()->language()->addToStack( 'edit_title', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) ), $this->getEditForm( $form ), $container );
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Log::debug( $e, 'content_debug' );
			\IPS\Output::i()->error( 'edit_no_perm_err', '2S136/E', 404, '' );
		}
	}

	/**
	 * Return the form for editing. Abstracted so controllers can define a custom template if desired.
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	string
	 */
	protected function getEditForm( $form )
	{
		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}

	/**
	 * Edit item's tags
	 *
	 * @return	void
	 */
	protected function editTags()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			/* Make sure tagging is supported in this class */
			if ( !\in_array( 'IPS\Content\Tags', class_implements( $class ) ) )
			{
				throw new \DomainException;
			}

			/* Get the container as we'll need it for permission checking */
			$container = NULL;
			try
			{
				$container = $item->container();
			}
			catch ( \BadMethodCallException $e ) {}

			/* Make sure we can edit and tag */
			if ( !$item->canEdit() OR ( !$item::canTag( NULL, $container )  AND !\count( $item->tags() ) ) )
			{
				throw new \OutOfRangeException;
			}

			/* If the tag form field is generated, create the form, otherwise throw an exception */
			if( $tagsField = $item::tagsFormField( $item, $container, TRUE ) )
			{
				$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->checkKeyExists( $item::$formLangPrefix . '_save' ) ? $item::$formLangPrefix . '_save' : 'save' );
				$form->class = 'ipsForm_vertical ipsForm_fullWidth';

				$form->add( $tagsField );
			}
			else
			{
				throw new \DomainException;
			}

			/* If we are simply removing a tag, do it */
			if( isset( \IPS\Request::i()->removeTag ) )
			{
				/* Get our current tags */
				$existingTags = ( $item->prefix() ? array_merge( array( 'prefix' => $item->prefix() ), $item->tags() ) : $item->tags() );

				/* Remove the tag from the array. Preserve index since it could be 'prefix' which we need to remember. */
				foreach( $existingTags as $index => $tag )
				{
					if( $tag == \IPS\Request::i()->removeTag )
					{
						unset( $existingTags[ $index ] );
						break;
					}
				}

				/* Now set the tags */
				$name = $tagsField->name;
				\IPS\Request::i()->$name = implode( "\n", $existingTags );

				if( isset( $existingTags['prefix'] ) )
				{
					$prefix = $tagsField->name . '_prefix';
					\IPS\Request::i()->$prefix = $existingTags['prefix'];

					$prefix = $tagsField->name . '_freechoice_prefix';
					\IPS\Request::i()->$prefix = 'on';
				}

				$tagsField->setValue( FALSE, TRUE );

				$submittedKey = $form->id . "_submitted";
				\IPS\Request::i()->$submittedKey = 1;
			}

			/* Process the values */
			if ( $values = $form->values() )
			{
				$item->setTags( $values[ $item::$formLangPrefix . 'tags' ] ?: array() );

				if ( \IPS\Request::i()->isAjax() )
				{
					/* Build html we'll need to display */
					$toReturn = array( 'tags' => '', 'prefix' => '' );

					foreach ( $item->tags() as $tag )
					{
						$toReturn['tags'] .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->tag( $tag, $item->url() );
					}

					if( $item->prefix() )
					{
						$toReturn['prefix'] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->prefix( $item->prefix( TRUE ), $item->prefix() );
					}

					\IPS\Output::i()->json( $toReturn );
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url() );	
				}				
			}

			/* If we tried to delete a tag and this is an AJAX request, just return the error. If it's not AJAX, we
				can just let the regular form output which will show the error. */
			if( $tagsField->error AND \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->error( $tagsField->error, '1S136/13', 403, '' );
			}
			
			/* Show the output */
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->editTagsForm( $form );
		}
		catch ( \DomainException $e )
		{
			\IPS\Output::i()->error( 'edit_no_tags_defined', '2S131/3', 403, 'edit_no_tags_defined_admin' );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'edit_no_perm_err', '2S136/12', 403, '' );
		}
	}
	
	/**
	 * Quick Edit Title
	 *
	 * @return	void
	 */
	public function ajaxEditTitle()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
			
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canEditTitle() )
			{
				throw new \RuntimeException;
			}
			
			$oldTitle = $item->mapped( 'title' );

			$maxLength	= \IPS\Settings::i()->max_title_length ?: 255;
			$titleField	= $item::$databaseColumnMap['title'];

			if( \strlen( \IPS\Request::i()->newTitle ) > $maxLength )
			{
				throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack( 'form_maxlength', FALSE, array( 'pluralize' => array( $maxLength ) ) ) );
			}
			elseif( !trim( \IPS\Request::i()->newTitle ) )
			{
				throw new \InvalidArgumentException('form_required');
			}

			$newTitle = new \IPS\Helpers\Form\Text( 'newTitle', \IPS\Request::i()->newTitle );

			if( $newTitle->validate() !== TRUE )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_not_allowed', FALSE, array( 'sprintf' => array( \IPS\Request::i()->newTitle ) ) ) );
			}

			$item->$titleField = $newTitle->value;
			$idField = $item::$databaseColumnId;
			$item->save();

			/* rebuild the container last item data */
			if ( isset( $item::$containerNodeClass ) and ! $item->hidden() and ( $item->$idField === $item->container()->last_id ) )
			{
				$item->container()->seo_last_title = $item->title_seo;
				$item->container()->last_title     = $item->title;
				$item->container()->save();

				foreach( $item->container()->parents() AS $parent )
				{
					if ( ( $item::$databaseColumnId === $parent->last_id ) )
					{
						$parent->seo_last_title		= $item->title_seo;
						$parent->last_title			= $item->title;
						$parent->save();
					}
				}
			}

			if ( $item instanceof \IPS\Content\Searchable )
			{
				\IPS\Content\Search\Index::i()->index( $item );
			}
			
			/* Only add the mod log entry if we actually changed the title */
			if( $item->$titleField !== $oldTitle )
			{
				\IPS\Session::i()->modLog( 'modlog__comment_edit_title', array( (string) $item->url() => FALSE, $item->$titleField => FALSE, $oldTitle => FALSE ), $item );
			}
			
			\IPS\Output::i()->json( $item->$titleField );
		}
		catch( \LogicException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2S136/1M', 403, '' );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/11', 404, '' );
		}
	}
	
	/**
	 * Set the breadcrumb and title
	 *
	 * @param	\IPS\Content\Item	$item	Content item
	 * @param	bool				$link	Link the content item element in the breadcrumb
	 * @return	void
	 */
	protected function _setBreadcrumbAndTitle( $item, $link=TRUE )
	{
		$container	= NULL;
		try
		{
			$container = $item->container();
			if ( \IPS\IPS::classUsesTrait( $container, 'IPS\Content\ClubContainer' ) and $club = $container->club() )
			{
				\IPS\core\FrontNavigation::$clubTabActive = TRUE;
				\IPS\Output::i()->breadcrumb = array();
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
				\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
				\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
				
				if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
				{
					\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $container, 'sidebar' );
				}
			}
			else
			{
				foreach ( $container->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
			}
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $link ? $item->url() : NULL, $item->mapped( 'title' ) );

		$title = ( isset( \IPS\Request::i()->page ) and \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( $item->mapped( 'title' ), \IPS\Request::i()->page ) ) ) : $item->mapped( 'title' );
		\IPS\Output::i()->title = $container ? ( $title . ' - ' . $container->_title ) : $title;
	}
	
	/**
	 * Toggle a poll status
	 *
	 * @return void
	 */
	protected function pollStatus()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
						
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( $poll = $item->getPoll() )
			{
				if ( !$poll->canClose() )
				{
					\IPS\Output::i()->error( 'no_module_permission', '2S136/Z', 403, '' );
				}

				$newStatus = ( \IPS\Request::i()->value == 1 ? 0 : 1 );
				$poll->poll_closed = $newStatus;
				$redirectMessage = $newStatus == 0 ? 'poll_status_opened' : 'poll_status_closed';

				/* If opening the poll (after it has closed) remove the auto-close date */
				if( !$newStatus and ( $poll->poll_close_date instanceof \IPS\DateTime ) )
				{
					$poll->poll_close_date = -1;
					$redirectMessage .= '_no_date';
				}

				$poll->save();

				$type = $poll->poll_closed ? 'closed' : 'opened';
				\IPS\Session::i()->modLog( 'modlog__poll_' . $type, array( $item->url()->__toString() => FALSE, $item->mapped( 'title' ) => FALSE ), $item );

				\IPS\Output::i()->redirect( $item->url(), $redirectMessage );
			}
			else
			{
				throw new \UnderflowException;
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/Y', 404, '' );
		}
	}
	
	/**
	 * Moderate
	 *
	 * @return	void
	 */
	protected function moderate()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
						
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			if ( $item::$hideLogKey and \IPS\Request::i()->action === 'hide' )
			{
				/* If this is an AJAX request, and we're coming from the approval queue, just do it. */
				if ( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->_fromApproval ) )
				{
					$item->modAction( \IPS\Request::i()->action );
					\IPS\Output::i()->json( 'OK' );
				}
				
				$this->_setBreadcrumbAndTitle( $item );
				
				$form = new \IPS\Helpers\Form;
				$form->add( new \IPS\Helpers\Form\Text( 'hide_reason' ) );
				$this->moderationAlertField($form, $item);
				if ( $values = $form->values() )
				{
					$item->modAction( \IPS\Request::i()->action, NULL, $values['hide_reason'] );

					if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'])
					{
						$this->sendModerationAlert($values, $item);
					}
				}
				else
				{
					\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
					return;
				}
			}
			else if ( \IPS\Request::i()->action === 'delete' AND isset( \IPS\Request::i()->immediate ) )
			{
				$item->modAction( \IPS\Request::i()->action, NULL, NULL, TRUE );
			}
			else
			{
				if( \IPS\Request::i()->action === 'lock' )
				{
					if ( \IPS\Member::loggedIn()->modPermission('can_manage_alerts') AND $item->author()->member_id )
					{
						$form = new \IPS\Helpers\Form;
						$this->moderationAlertField($form, $item);
						$this->_setBreadcrumbAndTitle( $item );

						if ( $values = $form->values() )
						{
							if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'])
							{
								$this->sendModerationAlert($values, $item);
							}
						}
						else
						{
							\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
							\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
							return;
						}
					}

				}

				$item->modAction( \IPS\Request::i()->action );
			}
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				if( \IPS\Request::i()->action == 'delete' )
				{
					try
					{
						if( \IPS\Member::loggedIn()->modPermission( 'can_view_reports' ) AND isset( \IPS\Request::i()->_report ) )
						{
							try
							{
								$report = \IPS\core\Reports\Report::load( \IPS\Request::i()->_report );
								\IPS\Output::i()->redirect( $report->url() );
							}
							catch( \OutOfRangeException $e )
							{
								\IPS\Output::i()->redirect( $item->container()->url() );
							}
						}
						else
						{
							\IPS\Output::i()->redirect( $item->container()->url() );
						}
					}
					catch( \BadMethodCallException $e )
					{
						/* We could be deleting something from a report, in which case we can go back to the report */
						if( \IPS\Member::loggedIn()->modPermission( 'can_view_reports' ) AND isset( \IPS\Request::i()->_report ) )
						{
							\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=modcp&controller=modcp&tab=reports&action=view&id=' . \IPS\Request::i()->_report , 'front', 'modcp_report' ) );
						}
						else
						{
							/* Generic fallback in case we delete something that doesn't have a container */
							\IPS\Output::i()->redirect( \IPS\Http\Url::internal('') );
						}
					}
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url(), 'mod_confirm_' . \IPS\Request::i()->action );
				}
			}
		}
		catch( \InvalidArgumentException $e )
		{
			\IPS\Output::i()->error( 'mod_error_invalid_action', '3S136/1A', 403, 'mod_error_invalid_action_admin' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1', 404, '' );
		}
	}
	
	/**
	 * Move
	 *
	 * @return	void
	 */
	protected function move()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMove() )
			{
				throw new \DomainException;
			}

			$container = $item->container();
			
			$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->addToStack( 'move_send_to_container', FALSE, array( 'sprintf' => $container->_title ) ), NULL, array( 'data-bypassValidation' => true ) );
			$form->actionButtons[] = \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->button( \IPS\Member::loggedIn()->language()->addToStack( 'move_send_to_item', FALSE, array( 'sprintf' => $item->definiteArticle() ) ), 'submit', null, 'ipsButton ipsButton_link', array( 'tabindex' => '3', 'accesskey' => 'i', 'value' => 'item', 'name' => 'returnto' ) );
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Node( 'move_to', NULL, TRUE, array(
				'class'				=> \get_class( $item->container() ),
				'permissionCheck'	=> function( $node ) use ( $item )
				{
					if ( $node->id != $item->container()->id )
					{
						try
						{
							/* If the item is in a club, only allow moving to other clubs that you moderate */
							if ( \IPS\IPS::classUsesTrait( $item->container(), 'IPS\Content\ClubContainer' ) and $item->container()->club()  )
							{
								return $item::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
							}
							
							if ( $node->can( 'add' ) )
							{
								return true;
							}
						}
						catch( \OutOfBoundsException $e ) { }
					}
					
					return false;
				},
				'clubs'	=> TRUE
			) ) );

			$this->moderationAlertField( $form, $item);
			
			if ( isset( $class::$databaseColumnMap['moved_to'] ) )
			{
				$form->add( new \IPS\Helpers\Form\Checkbox( 'move_keep_link' ) );
				
				if ( \IPS\Settings::i()->topic_redirect_prune )
				{
					\IPS\Member::loggedIn()->language()->words['move_keep_link_desc'] = \IPS\Member::loggedIn()->language()->addToStack( '_move_keep_link_desc', FALSE, array( 'pluralize' => array( \IPS\Settings::i()->topic_redirect_prune ) ) );
				}
			}

			if ( $values = $form->values() )
			{
				if ( $values['move_to'] === NULL OR !$values['move_to']->can( 'add' ) OR $values['move_to']->id == $item->container()->id )
				{
					\IPS\Output::i()->error( 'node_move_invalid', '1S136/L', 403, '' );
				}

				/* If this item is read, we need to re-mark it as such after moving */
				if( $item instanceof \IPS\Content\ReadMarkers )
				{
					$unread = $item->unread();
				}

				$item->move( $values['move_to'], isset( $values['move_keep_link'] ) ? $values['move_keep_link'] : FALSE );

				/* Mark it as read */
				if( $item instanceof \IPS\Content\ReadMarkers and $unread == 0 )
				{
					$item->markRead( NULL, NULL, NULL, TRUE );
				}
				if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'] )
				{
					$this->sendModerationAlert($values, $item);
				}

				\IPS\Session::i()->modLog( 'modlog__action_move', array( $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) ?: ( method_exists( $item, 'item' ) ? $item->item()->mapped( 'title' ) : NULL ) => FALSE ),  $item );

				\IPS\Output::i()->redirect( ( isset( \IPS\Request::i()->returnto ) AND \IPS\Request::i()->returnto == 'item' ) ? $item->url() : $container->url() );
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'move_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title ) ) ) );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
			
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/D', 403, '' );
		}
	}
	
	/**
	 * Merge
	 *
	 * @return	void
	 */
	protected function merge()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canMerge() )
			{
				throw new \DomainException;
			}

			$form = $item->mergeForm();

			if ( $values = $form->values() )
			{
				$target = $class::loadFromUrl( $values['merge_with'] );
				if ( !$target->canView() )
				{
					throw new \DomainException;
				}

				$item->mergeIn( array( $target ), isset( $values['move_keep_link'] ) ? $values['move_keep_link'] : FALSE );
				\IPS\Output::i()->redirect( $item->url() );
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'merge_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title ) ) ) );
				
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/G', 403, '' );
		}
	}
	
	/**
	 * Delete a report
	 *
	 * @return	void
	 */
	protected function deleteReport()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'id=? AND report_by=? AND date_reported > ?', \IPS\Request::i()->cid, \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'automoderation_cannot_find_report', '2S136/1G', 404, '' );
		}
		
		try
		{
			$index = \IPS\core\Reports\Report::load( $report['rid'] );
		}
		catch( \OutofRangeException $e )
		{
			\IPS\Output::i()->error( 'automoderation_cannot_find_report', '2S136/1H', 404, '' );
		}
		
		$class = $index->class;
		
		\IPS\Db::i()->delete( 'core_rc_reports', array( 'id=?', \IPS\Request::i()->cid ) );
		
		/* Recalculate, we may have dropped below the threshold needed to hide a thing */
		$index->runAutomaticModeration();
		
		\IPS\Output::i()->redirect( $class::load( $index->content_id )->url(), 'automoderation_deleted' );
	}

	/**
	 * View Edit Log of the Item
	 *
	 * @return	void
	 * @throws	\LogicException
	 */
	public function editlog()
	{
		/* Permission check */
		if ( \IPS\Settings::i()->edit_log != 2 or ( !\IPS\Settings::i()->edit_log_public and !\IPS\Member::loggedIn()->modPermission( 'can_view_editlog' ) ) )
		{
			throw new \DomainException;
		}

		try
		{
			/* Init */
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			$this->_setBreadcrumbAndTitle( $item );

			/* Even if guests can see the changelog, we don't want this being indexed in Google */
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1Q', 404, '' );
		}

		$idColumn = $class::$databaseColumnId;
		$where = array( array( 'class=? AND comment_id=?', $class, $item->$idColumn ) );
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_view_editlog' ) )
		{
			$where[] = array( '`member`=? AND public=1', $item->author()->member_id );
		}

		$table = new \IPS\Helpers\Table\Db( 'core_edit_history', $item->url()->setQueryString( array( 'do' => 'editlog' ) ), $where );
		$table->sortBy = 'time';
		$table->sortDirection = 'desc';
		$table->limit = 10;
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'commentEditHistoryTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'commentEditHistoryRows' );
		$table->parsers = array(
			'new' => function( $val )
			{
				return $val;
			},
			'old' => function( $val )
			{
				return $val;
			}
		);
		$table->extra = $item;

		$pageParam = $table->getPaginationKey();
		if( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->$pageParam ) )
		{
			\IPS\Output::i()->sendOutput( (string) $table );
		}

		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->commentEditHistory( (string) $table, $item );
	}
	
	/**
	 * Report Item
	 *
	 * @return	void
	 */
	protected function report()
	{
		try
		{
			/* Init */
			$class = static::$contentModel;
			$commentClass = $class::$commentClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			/* Permission check */
			$canReport = $item->canReport();
			if ( $canReport !== TRUE AND !( $canReport == 'report_err_already_reported' AND \IPS\Settings::i()->automoderation_enabled ) )
			{
				\IPS\Output::i()->error( $canReport, '2S136/6', 403, '' );
			}
			
			/* Show form */
			$form = new \IPS\Helpers\Form( NULL, 'report_submit' );
			$form->class = 'ipsForm_vertical';
			$idColumn = $class::$databaseColumnId;
			
			/* As we group by user id to determine if max points have been reached, guests cannot contribute to counts */
			if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->automoderation_enabled )
			{
				/* Has this member already reported this in the past 24 hours */
				try
				{
					$index = \IPS\core\Reports\Report::loadByClassAndId( \get_class( $item ), $item->$idColumn );
					$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_by=? and date_reported > ?', $index->id, \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();
					
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->reportedAlready( $index, $report, $item );
					return;
				}
				catch( \Exception $e ) { }
				
				$options = array( \IPS\core\Reports\Report::TYPE_MESSAGE => \IPS\Member::loggedIn()->language()->addToStack('report_message_item') );
				foreach( \IPS\core\Reports\Types::roots() as $type )
				{
					$options[ $type->id ] = $type->_title;
				}
				
				$form->add( new \IPS\Helpers\Form\Radio( 'report_type', NULL, FALSE, array( 'options' => $options ) ) );
			}
			
			$form->add( new \IPS\Helpers\Form\Editor( 'report_message', NULL, FALSE, array( 'app' => 'core', 'key' => 'Reports', 'autoSaveKey' => "report-{$class::$application}-{$class::$module}-{$item->$idColumn}", 'minimize' => \IPS\Request::i()->isAjax() ? 'report_message_placeholder' : NULL ) ) );
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Member::loggedIn()->language()->words['report_message'] = \IPS\Member::loggedIn()->language()->addToStack('report_message_fallback');
			}
			
			if( !\IPS\Member::loggedIn()->member_id )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}
			if ( $values = $form->values() )
			{
				$report = $item->report( $values['report_message'], ( isset( $values['report_type'] ) ) ? $values['report_type'] : 0 );
				\IPS\File::claimAttachments( "report-{$class::$application}-{$class::$module}-{$item->$idColumn}", $report->id );
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack( 'report_submit_success' ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url(), 'report_submit_success' );
				}
			}

			$this->_setBreadcrumbAndTitle( $item );

			/* Even if guests can report something, we don't want the report form indexed in Google */
			\IPS\Output::i()->metaTags['robots'] = 'noindex';

			\IPS\Output::i()->output = \IPS\Request::i()->isAjax() ? $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ) : \IPS\Theme::i()->getTemplate( 'system', 'core' )->reportForm( $form );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/7', 404, '' );
		}
	}
	
	/**
	 * Get Next Unread Item
	 *
	 * @return	void
	 */
	protected function nextUnread()
	{
		try
		{
			$class		= static::$contentModel;
			$item		= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$next		= $item->nextUnread();

			if ( $next instanceof \IPS\Content\Item )
			{
				\IPS\Output::i()->redirect( $next->url()->setQueryString( array( 'do' => 'getNewComment' ) ) );
			}
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'next_unread_not_found', '2S136/J', 404, '' );
		}
	}
	
	/**
	 * React
	 *
	 * @return	void
	 */
	protected function react()
	{
		if( !\IPS\IPS::classUsesTrait( static::$contentModel, 'IPS\Content\Reactable' ) )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1N', 404, '' );
		}

		try
		{
			\IPS\Session::i()->csrfCheck();
			
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->reaction );
			$item->react( $reaction );

			if ( \IPS\Request::i()->isAjax() )
			{
				$output = array(
					'status' => 'ok',
					'count' => \count( $item->reactions() ),
					'score' => $item->reactionCount(),
					'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $item )
				);

				if ( \IPS\Settings::i()->core_datalayer_enabled )
				{
					$output['datalayer'] = array_replace( $item->getDataLayerProperties(), ['reaction_type' => $reaction->_title] );
				}

				\IPS\Output::i()->json( $output );
			}
			else
			{
				if ( \IPS\Settings::i()->core_datalayer_enabled )
				{
					\IPS\core\DataLayer::i()->addEvent( 'content_react', array_replace( $item->getDataLayerProperties(), [ 'reaction_type' => $reaction->_title ] ) );
				}

				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \OutOfRangeException | \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/14', 403, '' );
			}
		}
	}
	
	/**
	 * Unreact
	 *
	 * @return	void
	 */
	protected function unreact()
	{
		if( !\IPS\IPS::classUsesTrait( static::$contentModel, 'IPS\Content\Reactable' ) )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1O', 404, '' );
		}

		try
		{
			\IPS\Session::i()->csrfCheck();

			$member = ( isset( \IPS\Request::i()->member ) and \IPS\Member::loggedIn()->modPermission('can_remove_reactions') ) ? \IPS\Member::load( \IPS\Request::i()->member ) : \IPS\Member::loggedIn();
			
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$item->removeReaction( $member );

			/* Log */
			if( $member->member_id !== \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Session::i()->modLog( 'modlog__reaction_delete', array( $member->url()->__toString() => FALSE, $member->name => FALSE, $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) => FALSE ), $item );
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'status' => 'ok',
					'count' => \count( $item->reactions() ),
					'score' => $item->reactionCount(),
					'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $item )
				));
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/15', 403, '' );
			}
		}
	}
	
	/**
	 * Show Reactions
	 *
	 * @return	void
	 */
	protected function showReactions()
	{
		if( !\IPS\IPS::classUsesTrait( static::$contentModel, 'IPS\Content\Reactable' ) )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1P', 404, '' );
		}
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->tooltip ) and isset( \IPS\Request::i()->reaction ) )
			{
				$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->reaction );
				
				$numberToShowInPopup = 10;
				$where = $item->getReactionWhereClause( $reaction );
				$total = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', $where )->join( 'core_reactions', 'reaction=reaction_id' )->first();
				$names = \IPS\Db::i()->select( 'name', 'core_reputation_index', $where, 'rep_date DESC', $numberToShowInPopup )->join( 'core_reactions', 'reaction=reaction_id' )->join( 'core_members', 'core_reputation_index.member_id=core_members.member_id' );
				
				\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reactionTooltip( $reaction, $total ? $names : [], ( $total > $numberToShowInPopup ) ? ( $total - $numberToShowInPopup ) : 0 ) );
			}
			else
			{		
				$blurb = $item->reactBlurb();
				
				$this->_setBreadcrumbAndTitle( $item );
				
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'see_who_reacted' ) . ' - ' . \IPS\Output::i()->title;
				
				$tabs = array();
				$tabs['all'] = array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('all'), 'count' => \count( $item->reactions() ) );
				foreach( \IPS\Content\Reaction::roots() AS $reaction )
				{
					if ( $reaction->_enabled !== FALSE )
					{
						$tabs[ $reaction->id ] = array( 'title' => $reaction->_title, 'icon' => $reaction->_icon, 'count' => isset( $blurb[ $reaction->id ] ) ? $blurb[ $reaction->id ] : 0 );
					}
				}
				
				$activeTab = 'all';
				if ( isset( \IPS\Request::i()->reaction ) )
				{
					$activeTab = \IPS\Request::i()->reaction;
				}
				
				$url = $item->url('showReactions');
				$url = $url->setQueryString( 'changed', 1 );

				if ( isset( \IPS\Request::i()->item ) )
				{
					$url = $url->setQueryString( 'item', \IPS\Request::i()->item );
				}
				
				if ( $activeTab !== 'all' )
				{
					$url = $url->setQueryString( 'reaction', $activeTab );
				}
	
				\IPS\Output::i()->metaTags['robots'] = 'noindex';
				
				if ( \IPS\Content\Reaction::isLikeMode() or ( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->changed ) ) )
				{
					\IPS\Output::i()->output = $item->reactionTable( $activeTab !== 'all' ? $activeTab : NULL, $url, 'reaction', FALSE );
				}
				else
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reactionTabs( $tabs, $activeTab, $item->reactionTable( $activeTab !== 'all' ? $activeTab : NULL ), $url, 'reaction', FALSE );
				}
			}
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/18', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/19', 403, '' );
		}
	}
	
	/**
	 * Moderation Log
	 *
	 * @return	void
	 */
	protected function modLog()
	{
		if( !\IPS\Member::loggedIn()->modPermission( 'can_view_moderation_log' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/1F', 403, '' );
		}
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			/* Set up some stuff so we're not doing too much logic / assignment in the template */
			$modlog = $item->moderationTable();

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->modLog( $item, $modlog );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/T', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/U', 403, '' );
		}
	}

	/**
	 * Moderation Log
	 *
	 * @return	void
	 */
	protected function analytics()
	{
		if( !\IPS\Member::loggedIn()->modPermission( 'can_view_moderation_log' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/1F', 403, '' );
		}

		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			/* Set up some stuff so we're not doing too much logic / assignment in the template */
			$lastCommenter = $members = $busy = $reacted = $images = NULL;
			try
			{
				$lastCommenter = $item->lastCommenter();
				if ( !$lastCommenter->member_id )
				{
					$lastCommenter = NULL;
				}
			}
			catch( \BadMethodCallException $e ) { }

			if ( \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Statistics' ) )
			{
				$members	= $item->topPosters();
				$busy		= $item->popularDays();
				$reacted	= $item->topReactedPosts();
				$images		= $item->imageAttachments();
			}

			/* Set navigation */
			$this->_setBreadcrumbAndTitle( $item );

			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'analytics_and_stats' ) );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->analytics( $item, $lastCommenter, $members, $busy, $reacted, $images );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/T', 404, '' );
		}
		catch( \DomainException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/U', 403, '' );
		}
	}
	
	/**
	 * Go to new comment.
	 *
	 * @return	void
	 */
	public function getNewComment()
	{
		try
		{
			$class	= static::$contentModel;
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->id );

			$timeLastRead = $item->timeLastRead();

			if ( $timeLastRead instanceof \IPS\DateTime )
			{
				$comment = NULL;
				if( \IPS\DateTime::ts( $item->mapped('date') ) < $timeLastRead )
				{
					$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead );
				}

				/* If we don't have any unread comments... */
				if ( !$comment and $class::$firstCommentRequired )
				{
					/* If we haven't read the item at all, go there */
					if ( $item->unread() )
					{
						\IPS\Output::i()->redirect( $item->url() );
					}
					/* Otherwise, go to the last comment */
					else
					{
						$comment = $item->comments( 1, NULL, 'date', 'desc' );
					}
				}

				\IPS\Output::i()->redirect( $comment ? $comment->url() : $item->url() );
			}
			else
			{
				if ( $item->unread() )
				{
					/* If we do not have a time last read set for this content, fallback to the reset time */
					$resetTimes = \IPS\Member::loggedIn()->markersResetTimes( $class::$application );

					if ( ( \is_array( $resetTimes ) AND array_key_exists( $item->container()->_id, $resetTimes ) ) and $item->mapped('date') < $resetTimes[ $item->container()->_id ] )
					{
						$comment = $item->comments( 1, NULL, 'date', 'asc', NULL, NULL, \IPS\DateTime::ts( $resetTimes[ $item->container()->_id ] ) );
						
						if ( $class::$firstCommentRequired and $comment->isFirst() )
						{
							\IPS\Output::i()->redirect( $item->url() );
						}
						
						\IPS\Output::i()->redirect( $comment ? $comment->url() : $item->url() );
					}
					else
					{
						\IPS\Output::i()->redirect( $item->url() );
					}
				}
				else
				{
					\IPS\Output::i()->redirect( $item->url() );
				}
			}
		}
		catch( \BadMethodCallException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/I', 404, '' );
		}
		catch( \OutOfRangeException $e )
		{
			$class = static::$contentModel;

			try
			{
				$item = $class::load( \IPS\Request::i()->id );
				$error = ( !$item->canView() and ( $item->containerWrapper( TRUE ) and method_exists( $item->container(), 'errorMessage' ) ) ) ? $item->container()->errorMessage() : 'node_error';
			}
			catch( \OutOfRangeException $e )
			{
				$error = 'node_error';
			}
			
			\IPS\Output::i()->error( $error, '2S136/V', 403, '' );
		}
		catch( \LogicException $e )
		{
			$class = static::$contentModel;

			try
			{
				$item = $class::load( \IPS\Request::i()->id );
				$error = ( !$item->canView() and ( $item->containerWrapper( TRUE ) and method_exists( $item->container(), 'errorMessage' ) ) ) ? $item->container()->errorMessage() : 'node_error';
			}
			catch( \OutOfRangeException $e )
			{
				$error = 'node_error';
			}

			\IPS\Output::i()->error( $error, '2S136/R', 404, '' );
		}
	}
	
	/**
	 * Go to last comment
	 *
	 * @return	void
	 */
	public function getLastComment()
	{
		try
		{
			$class	= static::$contentModel;
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$comment = $item->comments( 1, NULL, 'date', 'desc' );
			
			if ( $comment !== NULL )
			{
				$this->_find( \get_class( $comment ), $comment, $item );
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \BadMethodCallException $e )
		{
			try
			{
				$item = $class::load( \IPS\Request::i()->id );
				$error = ( !$item->canView() and ( $item->containerWrapper( TRUE ) and method_exists( $item->container(), 'errorMessage' ) ) ) ? $item->container()->errorMessage() : 'node_error';
			}
			catch( \OutOfRangeException $e )
			{
				$error = 'node_error';
			}
			
			\IPS\Output::i()->error( $error, '2S136/K', 404, '' );
		}
		catch( \LogicException $e )
		{
			try
			{
				$item = $class::load( \IPS\Request::i()->id );
				$error = ( !$item->canView() and ( $item->containerWrapper( TRUE ) and method_exists( $item->container(), 'errorMessage' ) ) ) ? $item->container()->errorMessage() : 'node_error';
			}
			catch( \OutOfRangeException $e )
			{
				$error = 'node_error';
			}
			
			\IPS\Output::i()->error( $error, '2S136/Q', 403, '' );
		}
	}
	
	/**
	 * Go to first comment
	 *
	 * @return	void
	 */
	public function getFirstComment()
	{
		try
		{
			$class	= static::$contentModel;
			$item	= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( $class::$firstCommentRequired )
			{
				$comments = $item->comments( 2, NULL, 'date', 'asc' );
				$comment  = array_pop( $comments );
				unset( $comments );
			}
			else
			{
				$comment = $item->comments( 1, NULL, 'date', 'asc' );
			}
			
			if ( $comment !== NULL )
			{
				$this->_find( \get_class( $comment ), $comment, $item );
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
		catch( \BadMethodCallException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/W', 404, '' );
		}
		catch( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/X', 403, '' );
		}
	}
	
	/**
	 * Rate Review as helpful/unhelpful
	 *
	 * @return	void
	 */
	public function rateReview()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
			
			/* Only logged in members */
			if ( !\IPS\Member::loggedIn()->member_id )
			{
				throw new \DomainException;
			}
			
			/* Init */
			$class = static::$contentModel;
			$reviewClass = $class::$reviewClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$review = $reviewClass::load( \IPS\Request::i()->review );
			
			/* Review authors can't rate their own reviews */
			if ( $review->author()->member_id === \IPS\Member::loggedIn()->member_id )
			{
				throw new \DomainException;
			}
			
			/* Have we already rated? */
			$dataColumn = $reviewClass::$databaseColumnMap['votes_data'];
			$votesData = $review->mapped( 'votes_data' ) ? json_decode( $review->mapped( 'votes_data' ), TRUE ) : array();
			if ( array_key_exists( \IPS\Member::loggedIn()->member_id, $votesData ) )
			{
				\IPS\Output::i()->error( 'you_have_already_rated', '2S136/A', 403, '' );
			}
			
			/* Add it */
			$votesData[ \IPS\Member::loggedIn()->member_id ] = \intval( \IPS\Request::i()->helpful );
			if ( \IPS\Request::i()->helpful )
			{
				$helpful = $reviewClass::$databaseColumnMap['votes_helpful'];
				$review->$helpful++;
			}
			$total = $reviewClass::$databaseColumnMap['votes_total'];
			$review->$total++;
			$review->$dataColumn = json_encode( $votesData );
			$review->save();
			
			/* Boink */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( $review->html() );
			}
			else
			{
				\IPS\Output::i()->redirect( $review->url() );
			}
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/9', 404, '' );
		}
	}

	/**
	 * Allow the author of a content item to reply to a review
	 *
	 * @param	bool	$editing	TRUE if we are editing our response
	 * @return	void
	 */
	protected function _respond( $editing=FALSE )
	{
		try
		{
			/* Init */
			$class = static::$contentModel;
			$reviewClass = $class::$reviewClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$review = $reviewClass::loadAndCheckPerms( \IPS\Request::i()->review );
			
			/* Are we allowed to respond? */
			if( $editing === TRUE )
			{
				if ( !$review->canEditResponse() )
				{
					throw new \DomainException;
				}
			}
			else
			{
				if ( !$review->canRespond() )
				{
					throw new \DomainException;
				}
			}
			
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Editor( 'reviewResponse', $editing ? $review->mapped('author_response') : NULL, TRUE, array(
				'app'			=> 'core',
				'key'			=> 'ReviewResponses',
				'autoSaveKey' 	=> 'reviewResponse-' . $class::$application . '/' . $class::$module . '-' . \IPS\Request::i()->review,
				'attachIds'		=> array( \IPS\Request::i()->id, \IPS\Request::i()->review, \get_class( $item ) )
			) ) );
			
			if ( $values = $form->values() )
			{
                $review->setResponse( $values['reviewResponse'] );

                /* Claim attachments and clear the editor */
                \IPS\File::claimAttachments( 'reviewResponse-' . $class::$application . '/' . $class::$module . '-' . \IPS\Request::i()->review, \IPS\Request::i()->id, \IPS\Request::i()->review, \get_class( $item ) );

				\IPS\Output::i()->redirect( $review->url() );
			}

			\IPS\Output::i()->metaTags['robots'] = 'noindex';
			
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1I', 403, '' );
		}
	}

	/**
	 * Allow the author of a content item to edit their review response
	 *
	 * @return	void
	 */
	protected function _editResponse()
	{
		return $this->_respond( TRUE );
	}

	/**
	 * Delete a review response
	 *
	 * @return	void
	 */
	protected function _deleteResponse()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			/* Init */
			$class = static::$contentModel;
			$reviewClass = $class::$reviewClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$review = $reviewClass::loadAndCheckPerms( \IPS\Request::i()->review );
			
			/* Are we allowed to delete responses? */
			if ( !$review->canDeleteResponse() )
			{
				throw new \DomainException;
			}
			
			$review->author_response = NULL;
			$review->save();

			\IPS\Output::i()->redirect( $review->url() );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1J', 403, '' );
		}
	}
	
	/**
	 * Message Form
	 *
	 * @return	void
	 */
	protected function messageForm()
	{
		$class = static::$contentModel;
		try
		{
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			$current = NULL;
			$metaData = $item->getMeta();
			if ( isset( \IPS\Request::i()->meta_id ) AND isset( $metaData['core_ContentMessages'][ \IPS\Request::i()->meta_id ] ) )
			{
				$current = $metaData['core_ContentMessages'][ \IPS\Request::i()->meta_id ];
			}
			
			if ( !$item->canOnMessage( $current ? 'edit' : 'add' ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1D', 404, '' );
		} 

		$form = new \IPS\Helpers\Form;
		$form->attributes['data-controller'] = 'core.front.core.contentMessage';
		$form->add( new \IPS\Helpers\Form\YesNo( 'message_is_public', isset( $current['is_public'] ) ? $current['is_public'] : FALSE ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'message', $current ? $current['message'] : NULL , TRUE, array( 'app' => 'core', 'key' => 'Meta', 'autoSaveKey' => $current ? "meta-message-" . \IPS\Request::i()->meta_id : "meta-message-new", 'attachIds' => $current ? array( \IPS\Request::i()->meta_id, NULL, 'core_ContentMessages' ) : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'message_color', isset( $current['color'] ) ? $current['color'] : 'none', FALSE, array( 'getHtml' => function( $element )
        {
            return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' )->colorSelection( $element->name, $element->value );
        } ), NULL, NULL, NULL, 'message_color' ) );

		if ( $values = $form->values() )
		{
			if ( $current )
			{
				$item->editMessage( \IPS\Request::i()->meta_id, $values['message'], $values['message_color'], NULL, (bool) $values['message_is_public'] );
				\IPS\File::claimAttachments( "meta-message-" . \IPS\Request::i()->meta_id, \IPS\Request::i()->meta_id, NULL, 'core_ContentMessages' );
				
				\IPS\Session::i()->modLog( 'modlog__message_edit', array(
					(string) $item->url()	=> FALSE,
					$item->mapped('title')	=> FALSE
				), $item );
			}
			else
			{
				$id = $item->addMessage( $values['message'], $values['message_color'], NULL, (bool) $values['message_is_public'] );
				\IPS\File::claimAttachments( "meta-message-new", $id, NULL, 'core_ContentMessages' );
				
				\IPS\Session::i()->modLog( 'modlog__message_add', array(
					(string) $item->url()	=> FALSE,
					$item->mapped('title')	=> FALSE
				), $item );
			}
			
			\IPS\Output::i()->redirect( $item->url() );
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Message Delete
	 *
	 * @return	void
	 */
	protected function messageDelete()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$item->canOnMessage('delete') )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1E', 404, '' );
		}
		
		$item->deleteMessage( \IPS\Request::i()->meta_id );
		
		\IPS\File::unclaimAttachments( 'core_Meta', \IPS\Request::i()->meta_id, NULL, 'core_ContentMessages' );
		
		\IPS\Session::i()->modLog( 'modlog__message_delete', array(
			(string) $item->url()	=> FALSE,
			$item->mapped('title')	=> FALSE
		), $item );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( $item->url() );
		}
	}

	/**
	 * Solve the item
	 *
	 * @return	void
	 */
	public function solve()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			$class = static::$contentModel;
			$commentClass = $class::$commentClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );

			if ( ! \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Solvable' ) )
			{
				throw new \OutOfRangeException;
			}

			if ( ! $item->canSolve() )
			{
				throw new \OutOfRangeException;
			}

			$comment = $commentClass::loadAndCheckPerms( \IPS\Request::i()->answer );
			$idField = $comment::$databaseColumnId;

			if ( $comment->item() != $item )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/7', 404, '' );
		}

		$item->toggleSolveComment( \IPS\Request::i()->answer, TRUE );

		/* Log */
		if ( \IPS\Member::loggedIn()->modPermission('can_set_best_answer') )
		{
			\IPS\Session::i()->modLog( 'modlog__best_answer_set', array( $comment->$idField => FALSE ), $item );
		}

		\IPS\Output::i()->redirect( $item->url() );
	}

	/**
	 * Unsolve the item
	 *
	 * @return	void
	 */
	public function unsolve()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			$class = static::$contentModel;
			$commentClass = $class::$commentClass;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$comment = $commentClass::loadAndCheckPerms( \IPS\Request::i()->answer );

			$idField = $comment::$databaseColumnId;
			$solvedField = $item::$databaseColumnMap['solved_comment_id'];

			if ( ! \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Solvable' ) )
			{
				throw new \OutOfRangeException;
			}

			if ( ! $item->canSolve() )
			{
				throw new \OutOfRangeException;
			}

			if ( $comment->item() != $item )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/G', 404, '' );
		}

		if ( $item->mapped('solved_comment_id') )
		{
			try
			{
				$item->toggleSolveComment( $item->mapped('solved_comment_id'), FALSE );

				if ( \IPS\Member::loggedIn()->modPermission('can_set_best_answer') )
				{
					\IPS\Session::i()->modLog( 'modlog__best_answer_unset', array( $comment->$idField => FALSE ), $item );
				}
			}
			catch ( \Exception $e ) {}
		}

		\IPS\Output::i()->redirect( $item->url() );
	}
	
	/**
	 * Toggle Item Moderation
	 *
	 * @return	void
	 */
	public function toggleItemModeration()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( !$item->canToggleItemModeration() )
			{
				throw new \BadMethodCallException;
			}
			
			if ( $item->itemModerationEnabled() )
			{
				$action = 'disable';
				$actionLang = 'disabled';
			}
			else
			{
				$action = 'enable';
				$actionLang = 'enabled';
			}
			
			$item->toggleItemModeration( $action );
			
			\IPS\Session::i()->modLog( 'modlog__item_moderation_toggled', [$actionLang => TRUE], $item );
			
			\IPS\Output::i()->redirect( $item->url(), $actionLang );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1T', 404, '' );
		}
		catch( \BadMethodCallException | \InvalidArgumentException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2S136/1U', 403, '' );
		}
	}

	/**
	 * Stuff that applies to both comments and reviews
	 *
	 * @param	string	$method	Desired method
	 * @param	array	$args	Arguments
	 * @return	void
	 */
	public function __call( $method, $args )
	{
		$class = static::$contentModel;
		
		try
		{
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$comment = NULL;
			if ( mb_substr( $method, -7 ) === 'Comment' )
			{
				if ( isset( $class::$commentClass ) and ( isset( \IPS\Request::i()->comment ) OR mb_substr( $method, 0, 8 ) === 'multimod' ) )
				{
					$class = $class::$commentClass;
					$method = '_' . mb_substr( $method, 0, mb_strlen( $method ) - 7 );
					
					$comment = $method === '_multimod' ? NULL : $class::load( \IPS\Request::i()->comment );
				}
			}
			elseif ( mb_substr( $method, -6 ) === 'Review' )
			{
				if ( isset( $class::$reviewClass ) and ( isset( \IPS\Request::i()->review ) OR mb_substr( $method, 0, 8 ) === 'multimod' ) )
				{
					$class = $class::$reviewClass;
					$method = '_' . mb_substr( $method, 0, mb_strlen( $method ) - 6 );
					$comment = $method === '_multimod' ? NULL : $class::load( \IPS\Request::i()->review );
				}
			}
			
			if ( $method === '_multimod' )
			{
				$this->_multimod( $class, $item );
			}
									
			if ( !$comment or !method_exists( $this, $method ) )
			{
				if ( mb_substr( $method, 0, 4 ) === 'find' )
				{
					/* Nothing found, redirect to main URL */
					\IPS\Output::i()->redirect( $item->url() );
				}
				else
				{
					\IPS\Output::i()->error( 'page_not_found', '2S136/B', 404, '' );
				}
			}
			else
			{
				$this->$method( $class, $comment, $item );
			}
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/C', 404, '' );
		}
	}
	
	/**
	 * Find a Comment / Review (do=findComment/findReview)
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 */
	public function _find( $commentClass, $comment, $item )
	{
		$idColumn = $commentClass::$databaseColumnId;
		$itemColumn = $commentClass::$databaseColumnMap['item'];
		
		/* Note in the session that we were looking for this comment. This can be used
			to set appropriate meta tag descriptions */
		$_SESSION['_findComment']	= $comment->$idColumn;
		
		/* Work out where the comment is in the item */	
		$directional = ( \in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) ) ? '>=?' : '<=?';
		$where = array(
			array( $commentClass::$databasePrefix . $itemColumn . '=?', $comment->$itemColumn ),
			array( $commentClass::$databasePrefix . $idColumn . $directional, $comment->$idColumn )
		);

		/* Exclude content pending deletion, as it will not be shown inline  */
		if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '<>?', -2 );
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '<>?', -3 );
		}
		elseif( isset( $commentClass::$databaseColumnMap['hidden'] ) )
		{
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '<>?', -2 );
			$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '<>?', -3 );
		}

		if ( $commentClass::commentWhere() !== NULL )
		{
			$where[] = $commentClass::commentWhere();
		}
		if ( $container = $item->containerWrapper() )
		{
			if ( $commentClass::modPermission( 'view_hidden', NULL, $container ) === FALSE )
			{
				if ( isset( $commentClass::$databaseColumnMap['approved'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['approved'] . '=?', 1 );
				}
				elseif( isset( $commentClass::$databaseColumnMap['hidden'] ) )
				{
					$where[] = array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['hidden'] . '=?', 0 );
				}
			}
		}
		$commentPosition = $commentClass::db()->select( 'COUNT(*) AS position', $commentClass::$databaseTable, $where )->first();
		
		/* Now work out what page that makes it */
		$url = $item->url();

		if ( \in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) )
		{
			$perPage = $item::$reviewsPerPage;
		}
		else
		{
			$perPage = $item::getCommentsPerPage();
		}

		$page = ceil( $commentPosition / $perPage );
		if ( $page != 1 )
		{
			$url = $url->setPage( 'page', $page );
		}

		if( $commentClass::$tabParameter !== NULL )
		{
			$url = $url->setQueryString( $commentClass::$tabParameter );
		}

		$fragment = 'comment';

		if ( \in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) )
		{
			$url = $url->setQueryString( array( 'sort' => 'newest' ) );
			$fragment = 'review';
		}

		if ( isset( \IPS\Request::i()->showDeleted ) )
		{
			$url = $url->setQueryString( 'showDeleted', 1 );
		}
		
		if ( isset( \IPS\Request::i()->_report ) )
		{
			$url = $url->setQueryString( '_report', \IPS\Request::i()->_report );
		}
		
		/* And redirect */
		\IPS\Output::i()->redirect( $url->setFragment( $fragment . '-' . $comment->$idColumn ) );
	}
	
	/**
	 * Hide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _hide( $commentClass, $comment, $item  )
	{
		/* If this is an AJAX request, and we're coming from the approval queue, just do it. */
		if ( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->_fromApproval ) )
		{
			\IPS\Session::i()->csrfCheck();

			$comment->modAction( 'hide' );
			\IPS\Output::i()->json( 'OK' );
		}
		
		if ( $comment::$hideLogKey )
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Text( 'hide_reason' ) );
			$this->moderationAlertField( $form, $comment);

			if ( $values = $form->values() )
			{
				$comment->modAction( 'hide', NULL, $values['hide_reason'] );

				if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'])
				{
					$this->sendModerationAlert($values, $comment);
				}
			}
			else
			{
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
				return;
			}
		}
		else
		{
			\IPS\Session::i()->csrfCheck();

			$comment->modAction( 'hide' );
		}
		
		\IPS\Output::i()->redirect( $comment->url() );
	}
	
	/**
	 * Unhide Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _unhide( $commentClass, $comment, $item  )
	{
		\IPS\Session::i()->csrfCheck();
		$comment->modAction( 'unhide' );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->sendOutput( $comment->html(), 200, 'text/html' );
			return;
		}
		else
		{
			\IPS\Output::i()->redirect( $comment->url() );
		}
	}
	
	/**
	 * Restore a Comment / Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _restore( $commentClass, $comment, $item )
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( isset( \IPS\Request::i()->restoreAsHidden ) )
		{
			\IPS\Session::i()->modLog( 'modlog__action_restore_hidden', array(
				$comment::$title				=> TRUE,
				$comment->url()->__toString()	=> FALSE
			) );

			$comment->modAction( 'restoreAsHidden' );
		}
		else
		{
			\IPS\Session::i()->modLog( 'modlog__action_restore', array(
				$comment::$title				=> TRUE,
				$comment->url()->__toString()	=> FALSE
			) );

			$comment->modAction( 'restore' );
		}
		
		if ( \IPS\Member::loggedIn()->modPermission( 'can_view_reports' ) AND isset( \IPS\Request::i()->_report ) )
		{
			try
			{
				$report = \IPS\core\Reports\Report::load( \IPS\Request::i()->_report );
				\IPS\Output::i()->redirect( $report->url() );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->redirect( $comment->url() );
			}
		}
		else
		{
			\IPS\Output::i()->redirect( $comment->url() );
		}
	}


	/**
	 * Edit Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _edit( $commentClass, $comment, $item )
	{
		$class = static::$contentModel;
		$valueField = $commentClass::$databaseColumnMap['content'];
		$idField = $commentClass::$databaseColumnId;
		$itemIdField = $item::$databaseColumnId;

		if ( $comment->canEdit() )
		{
			$form = new \IPS\Helpers\Form( 'form', false );
			$form->class = 'ipsForm_vertical';
			
			if ( \in_array( 'IPS\Content\Review', class_parents( $comment ) ) )
			{
				$ratingField = $commentClass::$databaseColumnMap['rating'];
				$form->add( new \IPS\Helpers\Form\Rating( 'rating_value', $comment->$ratingField, TRUE, array( 'max' => \IPS\Settings::i()->reviews_rating_out_of ) ) );
			}
			
			$form->add( new \IPS\Helpers\Form\Editor( 'comment_value', $comment->$valueField, TRUE, array(
				'app'			=> $class::$application,
				'key'			=> mb_ucfirst( $class::$module ),
				'autoSaveKey' 	=> 'editComment-' . $class::$application . '/' . $class::$module . '-' . $comment->$idField,
				'attachIds'		=> $comment->attachmentIds()
			) ) );
			
			/* Post Anonymously */
			$container = $item->containerWrapper();
			if ( $container and $container->canPostAnonymously( $container::ANON_COMMENTS ) and $comment instanceof \IPS\Content\Anonymous and ( $comment->author() and $comment->author()->group['gbw_can_post_anonymously'] or $comment->isAnonymous() ) )
			{
				$form->add ( new \IPS\Helpers\Form\YesNo( 'post_anonymously', $comment->isAnonymous(), FALSE, array( 'label' => \IPS\Member::loggedIn()->language()->addToStack( 'post_anonymously_suffix' ) ), NULL, NULL, NULL, 'post_anonymously' ) );
			}

			$form->addButton( 'save', 'submit', null, 'ipsButton ipsButton_medium ipsButton_primary ipsButton_fullWidth', array( 'tabindex' => '2', 'accesskey' => 's' ) );

			$form->addButton( 'cancel', 'link', $item->url()->setQueryString( \in_array( 'IPS\Content\Review', class_parents( $comment ) ) ? array( 'do' => 'findReview', 'review' => $comment->$idField ) : array( 'do' => 'findComment', 'comment' => $comment->$idField ) ), 'ipsButton ipsButton_medium ipsButton_link ipsButton_fullWidth', array( 'data-action' => 'cancelEditComment', 'data-comment-id' => $comment->$idField ) );

			if ( $comment instanceof \IPS\Content\EditHistory and \IPS\Settings::i()->edit_log )
			{
				if ( \IPS\Settings::i()->edit_log == 2 or isset( $commentClass::$databaseColumnMap['edit_reason'] ) )
				{
					$form->add( new \IPS\Helpers\Form\Text( 'comment_edit_reason', ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) ) ? $comment->mapped( 'edit_reason' ) : NULL, FALSE, array( 'maxLength' => 255 ) ) );
				}
				if ( \IPS\Member::loggedIn()->group['g_append_edit'] )
				{
					$form->add( new \IPS\Helpers\Form\Checkbox( 'comment_log_edit', FALSE ) );
				}
			}
			
			if ( $values = $form->values() )
			{
				/* Log History */
				if ( $comment instanceof \IPS\Content\EditHistory and \IPS\Settings::i()->edit_log )
				{
					$editIsPublic = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
					
					if ( \IPS\Settings::i()->edit_log == 2 )
					{
						\IPS\Db::i()->insert( 'core_edit_history', array(
							'class'			=> \get_class( $comment ),
							'comment_id'	=> $comment->$idField,
							'member'		=> \IPS\Member::loggedIn()->member_id,
							'time'			=> time(),
							'old'			=> $comment->$valueField,
							'new'			=> $values['comment_value'],
							'public'		=> $editIsPublic,
							'reason'		=> isset( $values['comment_edit_reason'] ) ? $values['comment_edit_reason'] : NULL,
						) );
					}
					
					if ( isset( $commentClass::$databaseColumnMap['edit_reason'] ) and isset( $values['comment_edit_reason'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_reason'];
						$comment->$field = $values['comment_edit_reason'];
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_time'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_time'];
						$comment->$field = time();
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_member_id'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_member_id'];
						$comment->$field = \IPS\Member::loggedIn()->member_id;
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_member_name'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_member_name'];
						$comment->$field = \IPS\Member::loggedIn()->name;
					}
					if ( isset( $commentClass::$databaseColumnMap['edit_show'] ) and $editIsPublic )
					{
						$field = $commentClass::$databaseColumnMap['edit_show'];
						$comment->$field = \IPS\Member::loggedIn()->group['g_append_edit'] ? $values['comment_log_edit'] : TRUE;
					}
					else if( isset( $commentClass::$databaseColumnMap['edit_show'] ) )
					{
						$field = $commentClass::$databaseColumnMap['edit_show'];
						$comment->$field = 0;
					}
				}

				/* Determine if the comment is hidden to start with */
				$isHidden = $comment->hidden();
				
				/* Do it */
				$comment->editContents( $values['comment_value'] );
				
				/* Edit rating */
				$reloadPage = false;
				if ( isset( $values['rating_value'] ) and \in_array( 'IPS\Content\Review', class_parents( $comment ) ) )
				{
					/* The star rating changes but is outside of JS scope to change when editing a comment */
					$ratingField = $comment::$databaseColumnMap['rating'];
					if ( $comment->$ratingField != $values['rating_value'] )
					{
						$reloadPage = true;
					}
					
					$comment->editRating( $values['rating_value'] );
				}

				/* Anonymous posting */
				if( isset( $values[ 'post_anonymously' ] ) )
				{
					/* The anon changes to the user details (photo, name, etc) is outside the JS scope to change when editing a comment */
					if ( (bool) $comment->isAnonymous() !== (bool) $values['post_anonymously'] )
					{
						$reloadPage = true;
					}
					
					$comment->setAnonymous( $values[ 'post_anonymously' ], $comment->author() );
				}
			
				/* Moderator log */
				\IPS\Session::i()->modLog( 'modlog__comment_edit', array( $comment->url()->__toString() => FALSE, $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) => FALSE ), $item );
				
				/* If this is an AJAX request and the comment hidden status has not changed just output the comment HTML */
				if ( \IPS\Request::i()->isAjax() AND $isHidden == $comment->hidden() AND $reloadPage === false )
				{
					\IPS\Output::i()->output = $comment->html();
					return;
				}
				else
				{
					\IPS\Output::i()->redirect( $comment->url() );
				}
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \in_array( 'IPS\Content\Review', class_parents( $commentClass ) )? \IPS\Member::loggedIn()->language()->addToStack( 'edit_review' ) : \IPS\Member::loggedIn()->language()->addToStack( 'edit_comment' ) );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_comment' ) . ' - ' . $item->mapped( 'title' );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			throw new \InvalidArgumentException;
		}
	}
	
	/**
	 * Delete Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _delete( $commentClass, $comment, $item )
	{
		\IPS\Session::i()->csrfCheck();

		$currentPageCount = $item->commentPageCount();

		$valueField = $commentClass::$databaseColumnMap['content'];
		$idField = $commentClass::$databaseColumnId;
		
		if ( $item::$firstCommentRequired and $comment->mapped( 'first' ) )
		{
			if ( $item->canDelete() )
			{
				/* If we are retaining content for a period of time, we need to just hide it instead for deleting later - this only works, though, with items that implement \IPS\Content\Hideable */
				if ( \IPS\Settings::i()->dellog_retention_period AND ( $item instanceof \IPS\Content\Hideable ) AND !isset( \IPS\Request::i()->immediately ) )
				{
					$item->logDelete();
				}
			}
			else
			{
				\IPS\Output::i()->error( 'node_noperm_delete', '3S136/1L', 403, '' );
			}
		}
		else
		{
			if ( $comment->canDelete() )
			{
				/* If we are retaining content for a period of time, we need to just hide it instead for deleting later - this only works, though, with items that implement \IPS\Content\Hideable */
				if ( \IPS\Settings::i()->dellog_retention_period AND ( $item instanceof \IPS\Content\Hideable ) AND !isset( \IPS\Request::i()->immediately ) )
				{
					$comment->logDelete();
				}
				else
				{
					$comment->delete();
				}
				
				/* Log */
				\IPS\Session::i()->modLog( 'modlog__comment_delete', array( $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) => FALSE ), $item );
			}
			else
			{
				\IPS\Output::i()->error( 'node_noperm_delete', '3S136/1K', 403, '' );
			}
		}

		/* Reset best answer */
		if( $item->topic_answered_pid and $item->topic_answered_pid == $comment->$idField )
		{
			$item->topic_answered_pid = 0;
			$item->save();
		}
		
		if ( \IPS\Request::i()->isAjax() )
		{
			$currentPageCount = \IPS\Request::i()->page;
			$newPageCount = $item->commentPageCount( TRUE );
			if ( isset( \IPS\Request::i()->page ) AND $currentPageCount != $newPageCount )
			{
				/* If we are on page 2 and delete a comment, and there are 3 pages, we don't want to be sent to page 3 (that makes no sense).
					Instead, we'll send you to the page requested. If it exists you'll be on the same page. If it doesn't, the controller will
					handle sending you to the correct location */
				\IPS\Output::i()->json( array( 'type' => 'redirect', 'total' => $item->mapped( 'num_comments' ), 'url' => (string) $item->url()->setPage( 'page', (int) \IPS\Request::i()->page ) ) );
			}
			else
			{
				\IPS\Output::i()->json( array( 'page' => $newPageCount, 'total' => $item->mapped( 'num_comments' ) ) );
			}
		}
		else
		{
			if ( \IPS\Member::loggedIn()->modPermission( 'can_view_reports' ) AND isset( \IPS\Request::i()->_report ) )
			{
				try
				{
					$report = \IPS\core\Reports\Report::load( \IPS\Request::i()->_report );
					\IPS\Output::i()->redirect( $report->url() );
				}
				catch( \OutOfRangeException $e )
				{
					\IPS\Output::i()->redirect( $item->url() );
				}
			}
			else
			{
				\IPS\Output::i()->redirect( $item->url() );
			}
		}
	}
	
	/**
	 * Split Comment
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _split( $commentClass, $comment, $item )
	{
		if ( $comment->canSplit() )
		{
			$itemClass = $comment::$itemClass;
			$idColumn = $itemClass::$databaseColumnId;
			$commentIdColumn = $comment::$databaseColumnId;
			
			/* Create a copy of the old item for logging */
			$oldItem = $item;
			
			/* Construct a form */
			$form = $this->_splitForm( $item, $comment );

			/* Handle submissions */
			if ( $values = $form->values() )
			{
				/* Are we creating or using an existing? */
				if ( isset( $values['_split_type'] ) and $values['_split_type'] === 'new' )
				{
					$item = $itemClass::createItem( $comment->author(), $comment->mapped( 'ip_address' ), \IPS\DateTime::ts( $comment->mapped( 'date' ) ), isset( $values[ $itemClass::$formLangPrefix . 'container' ] ) ? $values[ $itemClass::$formLangPrefix . 'container' ] : NULL );
					$item->processForm( $values );
					if ( isset( $itemClass::$databaseColumnMap['first_comment_id'] ) )
					{
						$firstCommentIdColumn = $itemClass::$databaseColumnMap['first_comment_id'];
						$item->$firstCommentIdColumn = $comment->$commentIdColumn;
					}

					/* Does the first post require moderator approval? */
					if ( $comment->hidden() === 1 )
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$column = $item::$databaseColumnMap['hidden'];
							$item->$column = 1;
						}
						elseif ( isset( $item::$databaseColumnMap['approved'] ) )
						{
							$column = $item::$databaseColumnMap['approved'];
							$item->$column = 0;
						}
					}
					/* Or is it hidden? */
					elseif ( $comment->hidden() === -1 )
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$column = $item::$databaseColumnMap['hidden'];
						}
						elseif ( isset( $item::$databaseColumnMap['approved'] ) )
						{
							$column = $item::$databaseColumnMap['approved'];
						}

						$item->$column = -1;
					}

					$item->save();

					if( $comment->hidden() !== 0 )
					{
						if ( isset( $comment::$databaseColumnMap['hidden'] ) )
						{
							$column = $comment::$databaseColumnMap['hidden'];
							$comment->$column = 0;
						}
						elseif ( isset( $comment::$databaseColumnMap['approved'] ) )
						{
							$column = $comment::$databaseColumnMap['approved'];
							$comment->$column = 1;
						}

						$comment->save();
					}
				}
				else
				{
					$item = $itemClass::loadFromUrl( $values['_split_into_url'] );

					if ( !$item->canView() )
					{
						throw new \DomainException;
					}
				}

				/* Remove featured comment associations */
				if( $comment->isFeatured() AND $oldItem )
				{
					\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->unfeatureComment( $oldItem, $comment );
				}

				/* Remove solved associations */
				if ( $oldItem and \IPS\IPS::classUsesTrait( $oldItem, 'IPS\Content\Solvable' ) and $oldItem->isSolved() and ( $oldItem->mapped('solved_comment_id') === $comment->$commentIdColumn ) )
				{
					$oldItem->toggleSolveComment( $comment->$commentIdColumn, FALSE );
				}

				$comment->move( $item );
				$oldItem->rebuildFirstAndLastCommentData();

				/* Log it */
				\IPS\Session::i()->modLog( 'modlog__action_split', array(
					$item::$title					=> TRUE,
					$item->url()->__toString()		=> FALSE,
					$item->mapped( 'title' )			=> FALSE,
					$oldItem->url()->__toString()	=> FALSE,
					$oldItem->mapped( 'title' )		=> FALSE
				), $item );

				if( isset( $values['moderation_alert_content'] ) AND $values['moderation_alert_content'] )
				{
					$this->sendModerationAlert( $values, $comment );
				}
			
				/* Redirect to it */
				\IPS\Output::i()->redirect( $item->url() );
			}
			
			/* Display */
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			throw new \DomainException;
		}
	}
	
	/**
	 * Edit Log
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	public function _editlog( $commentClass, $comment, $item )
	{
		/* Permission check */
		if ( \IPS\Settings::i()->edit_log != 2 or ( !\IPS\Settings::i()->edit_log_public and !\IPS\Member::loggedIn()->modPermission( 'can_view_editlog' ) ) )
		{
			throw new \DomainException;
		}

		$idColumn = $commentClass::$databaseColumnId;
		$where = array( array( 'class=? AND comment_id=?', $commentClass, $comment->$idColumn ) );
		if ( !\IPS\Member::loggedIn()->modPermission( 'can_view_editlog' ) )
		{
			$where[] = array( '`member`=? AND public=1', $comment->author()->member_id );
		}

		$table = new \IPS\Helpers\Table\Db( 'core_edit_history', $item->url()->setQueryString( array( 'do' => 'editlogComment', 'comment' => $comment->$idColumn ) ), $where );
		$table->sortBy = 'time';
		$table->sortDirection = 'desc';
		$table->limit = 10;
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'commentEditHistoryTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'commentEditHistoryRows' );
		$table->parsers = array(
			'new' => function( $val )
			{
				return $val;
			},
			'old' => function( $val )
			{
				return $val;
			}
		);
		$table->extra = $comment;

		$pageParam = $table->getPaginationKey();
		if( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->$pageParam ) )
		{
			\IPS\Output::i()->sendOutput( (string) $table );
		}
		
		/* Display */
		$container = NULL;
		try
		{
			$container = $item->container();
			foreach ( $container->parents() as $parent )
			{
				\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
			}
			\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
		}
		catch ( \Exception $e ) { }
		\IPS\Output::i()->breadcrumb[] = array( $comment->url(), $item->mapped( 'title' ) );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'edit_history_title' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->commentEditHistory( (string) $table, $comment );
	}
	
	/**
	 * Report Comment/Review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _report( $commentClass, $comment, $item )
	{
		try
		{
			$class = static::$contentModel;

			/* Permission check */
			$canReport = $comment->canReport();
			if ( $canReport !== TRUE AND !( $canReport == 'report_err_already_reported' AND \IPS\Settings::i()->automoderation_enabled ) )
			{
				\IPS\Output::i()->error( $canReport, '2S136/4', 403, '' );
			}

			/* Show form */
			$form = new \IPS\Helpers\Form( NULL, 'report_submit' );
			$form->class = 'ipsForm_vertical';
			$itemIdColumn = $class::$databaseColumnId;
			$idColumn = $comment::$databaseColumnId;
			
			/* As we group by user id to determine if max points have been reached, guests cannot contribute to counts */
			if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->automoderation_enabled )
			{
				/* Has this member already reported this in the past 24 hours */
				try
				{
					$index = \IPS\core\Reports\Report::loadByClassAndId( \get_class( $comment ), $comment->$idColumn );
					$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_by=? and date_reported > ?', $index->id, \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();
					
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system', 'core' )->reportedAlready( $index, $report, $comment );
					return;
				}
				catch( \Exception $e ) { }
				
				$options = array( \IPS\core\Reports\Report::TYPE_MESSAGE => \IPS\Member::loggedIn()->language()->addToStack('report_message_comment') );
				foreach( \IPS\core\Reports\Types::roots() as $type )
				{
					$options[ $type->id ] = $type->_title;
				}
				
				$form->add( new \IPS\Helpers\Form\Radio( 'report_type', NULL, FALSE, array( 'options' => $options ) ) );
			}
			
			$form->add( new \IPS\Helpers\Form\Editor( 'report_message', NULL, FALSE, array( 'app' => 'core', 'key' => 'Reports', 'autoSaveKey' => "report-{$class::$application}-{$class::$module}-{$item->$itemIdColumn}-{$comment->$idColumn}", 'minimize' => 'report_message_placeholder' ) ) );
			if ( !\IPS\Request::i()->isAjax() )
			{
				\IPS\Member::loggedIn()->language()->words['report_message'] = \IPS\Member::loggedIn()->language()->addToStack('report_message_fallback');
			}
			
			if( !\IPS\Member::loggedIn()->member_id )
			{
				$form->add( new \IPS\Helpers\Form\Captcha );
			}

			if ( $values = $form->values() )
			{
				$report = $comment->report( $values['report_message'], ( isset( $values['report_type'] ) ) ? $values['report_type'] : 0 );
				\IPS\File::claimAttachments( "report-{$class::$application}-{$class::$module}-{$item->$itemIdColumn}-{$comment->$idColumn}", $report->id );
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Member::loggedIn()->language()->addToStack( 'report_submit_success' ) );
				}
				else
				{
					\IPS\Output::i()->redirect( $comment->url(), 'report_submit_success' );
				}
			}
			$this->_setBreadcrumbAndTitle( $item );

			/* Even if guests can report something, we don't want the report form indexed in Google */
			\IPS\Output::i()->metaTags['robots'] = 'noindex';

			\IPS\Output::i()->output = \IPS\Request::i()->isAjax() ? $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) ) : \IPS\Theme::i()->getTemplate( 'system', 'core' )->reportForm( $form );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/10', 404, '' );
		}
	}
	
	/**
	 * React to a comment/review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _react( $commentClass, $comment, $item )
	{
		try
		{
			\IPS\Session::i()->csrfCheck();

			$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->reaction );
			$comment->react( $reaction );

			if ( \IPS\Request::i()->isAjax() )
			{
				$output = array(
					'status' => 'ok',
					'count' => \count( $comment->reactions() ),
					'score' => $comment->reactionCount(),
					'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $comment )
				);

				if ( \IPS\Settings::i()->core_datalayer_enabled )
				{
					$item = $item ?? $comment->item();
					$object = ( !isset( $item::$databaseColumnMap['content'] ) AND ( $item::$commentClass ) AND $comment->isFirst() ) ? $item : $comment;
					$output['datalayer'] = array_replace( $object->getDataLayerProperties(), ['reaction_type' => $reaction->_title] );
				}

				\IPS\Output::i()->json( $output );
			}
			else
			{
				/* Data Layer Event */
				if ( \IPS\Settings::i()->core_datalayer_enabled )
				{
					$properties = $comment->getDataLayerProperties();
					$properties['reaction_type'] = $reaction->_title;

					\IPS\core\DataLayer::i()->addEvent( 'content_react', $properties );
				}
				\IPS\Output::i()->redirect( $comment->url() );
			}
		}
		catch( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/16', 403, '' );
			}
		}
	}
	
	/**
	 * Feature a Comment
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws \LogicException
	 */
	protected function _feature( $commentClass, $comment, $item )
	{
		if ( !$item->canFeatureComment() )
		{
			throw new \DomainException;
		}
		
		try
		{
			$form = new \IPS\Helpers\Form( 'form', 'add_recommend_content' );
			$form->class = 'ipsForm_vertical ipsForm_fullWidth';
			$form->add( new \IPS\Helpers\Form\Text( 'feature_mod_note', NULL, FALSE ) );
			$this->moderationAlertField($form, $comment);
			if ( $values = $form->values() )
			{
				$item->featureComment( $comment, $values['feature_mod_note'] );
				if( isset( $values['moderation_alert_content']) AND $values['moderation_alert_content'])
				{
					$this->sendModerationAlert($values, $item);
				}
				
				\IPS\Session::i()->modLog( 'modlog__featured_comment', array(
					(string) $comment->url()	=> FALSE,
					$item->mapped('title')		=> FALSE
				) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					$idField = $comment::$databaseColumnId;
					$isReview = FALSE;

					if ( $comment instanceof \IPS\Content\Review )
					{
						$isReview = TRUE;
					}

					/* Send the new recommend comment ID so that the JS can identify it */
					\IPS\Output::i()->json( array( 
						'recommended' => $comment->$idField,
						'comment' => $comment->html()
					) );
				}
				else
				{
					\IPS\Output::i()->redirect( $comment->url() );
				}
			}
			
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' ), 'recommendCommentTemplate' ) );
		}
		catch( \BadMethodCallException $e )
		{
			throw new \LogicException;
		}
	}
	
	/**
	 * Unreact to a comment/review
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _unreact( $commentClass, $comment, $item )
	{
		try
		{
			\IPS\Session::i()->csrfCheck();

			$member = ( isset( \IPS\Request::i()->member ) and \IPS\Member::loggedIn()->modPermission('can_remove_reactions') ) ? \IPS\Member::load( \IPS\Request::i()->member ) : \IPS\Member::loggedIn();

			$comment->removeReaction( $member );

			/* Log */
			if( $member->member_id !== \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Session::i()->modLog( 'modlog__comment_reaction_delete', array( $member->url()->__toString() => FALSE, $member->name => FALSE, $comment->url()->__toString() => FALSE, $item::$title => TRUE, $item->url()->__toString() => FALSE, $item->mapped( 'title' ) => FALSE ), $item );
			}
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'status' => 'ok',
					'count' => \count( $comment->reactions() ),
					'score' => $comment->reactionCount(),
					'blurb' => ( \IPS\Settings::i()->reaction_count_display == 'count' ) ? '' : \IPS\Theme::i()->getTemplate( 'global', 'core' )->reactionBlurb( $comment )
				));
			}
			else
			{
				\IPS\Output::i()->redirect( $comment->url() );
			}
		}
		catch( \DomainException $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1S136/17', 403, '' );
			}
		}
	}
	
	/**
	 * Unfeature a comment
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _unfeature( $commentClass, $comment, $item )
	{
		\IPS\Session::i()->csrfCheck();
		if ( !$item->canUnfeatureComment() )
		{
			throw new \DomainException;
		}
		
		try
		{
			$item->unfeatureComment( $comment );
			
			\IPS\Session::i()->modLog( 'modlog__unfeatured_comment', array(
				(string) $comment->url()	=> FALSE,
				$item->mapped('title')		=> FALSE
			) );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				$idField = $comment::$databaseColumnId;
				$isReview = FALSE;

				if ( $comment instanceof \IPS\Content\Review )
				{
					$isReview = TRUE;
				}

				/* Send the new recommend comment ID so that the JS can identify it */
				\IPS\Output::i()->json( array( 
					'unrecommended' => $comment->$idField,
					'comment' => $comment->html()
				) );
			}
			else
			{
				\IPS\Output::i()->redirect( $comment->url() );
			}
		}
		catch( \DomainException $e )
		{
			throw new \LogicException;
		}
	}
	
	/**
	 * Show Comment/Review Reactions
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Comment	$comment		The comment/review
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _showReactions( $commentClass, $comment, $item )
	{		
		$idColumn = $commentClass::$databaseColumnId;
		
		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->tooltip ) and isset( \IPS\Request::i()->reaction ) )
		{
			$reaction = \IPS\Content\Reaction::load( \IPS\Request::i()->reaction );
			
			$numberToShowInPopup = 10;
			$where = $comment->getReactionWhereClause( $reaction );
			$total = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', $where )->join( 'core_reactions', 'reaction=reaction_id' )->first();
			$names = \IPS\Db::i()->select( 'name', 'core_reputation_index', $where, 'rep_date DESC', $numberToShowInPopup )->join( 'core_reactions', 'reaction=reaction_id' )->join( 'core_members', 'core_reputation_index.member_id=core_members.member_id' );
			
			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reactionTooltip( $reaction, $total ? $names : [], ( $total > $numberToShowInPopup ) ? ( $total - $numberToShowInPopup ) : 0 ) );
		}
		else
		{		
			$blurb = $comment->reactBlurb();
	
			$this->_setBreadcrumbAndTitle( $item );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'see_who_reacted' ) . ' (' . $comment->$idColumn . ') - ' . \IPS\Output::i()->title;
			
			$tabs = array();
			$tabs['all'] = array( 'title' => \IPS\Member::loggedIn()->language()->addToStack('all'), 'count' => \count( $comment->reactions() ) );
			foreach( \IPS\Content\Reaction::roots() AS $reaction )
			{
				if ( $reaction->_enabled !== FALSE )
				{
					$tabs[ $reaction->id ] = array( 'title' => $reaction->_title, 'icon' => $reaction->_icon, 'count' => isset( $blurb[ $reaction->id ] ) ? $blurb[ $reaction->id ] : 0 );
				}
			}
	
			$activeTab = 'all';
			if ( isset( \IPS\Request::i()->reaction ) )
			{
				$activeTab = \IPS\Request::i()->reaction;
			}
			
			$url = $comment->url('showReactions');
			$url = $url->setQueryString( 'changed', 1 );
			
			if ( $activeTab !== 'all' )
			{
				$url = $url->setQueryString( 'reaction', $activeTab );
			}
	
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
			
			if ( \IPS\Content\Reaction::isLikeMode() or ( \IPS\Request::i()->isAjax() AND isset( \IPS\Request::i()->changed ) ) )
			{
				\IPS\Output::i()->output = $comment->reactionTable( $activeTab !== 'all' ? $activeTab : NULL, $url, 'reaction', FALSE );
			}
			else
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reactionTabs( $tabs, $activeTab, $comment->reactionTable( $activeTab !== 'all' ? $activeTab : NULL ), $url, 'reaction', FALSE );
			}
		}
	}
	
	/**
	 * Multimod
	 *
	 * @param	string					$commentClass	The comment/review class
	 * @param	\IPS\Content\Item		$item			The item
	 * @return	void
	 * @throws	\LogicException
	 */
	protected function _multimod( $commentClass, $item )
	{
		\IPS\Session::i()->csrfCheck();
		
		$checkAgainst = \IPS\Request::i()->modaction;
		
		$classToCheck = $commentClass;
		if( $checkAgainst == 'split' OR $checkAgainst == 'merge' )
		{
			$classToCheck = $item;
			$checkAgainst = 'split_merge';
		}
		
		if ( !$classToCheck::modPermission( $checkAgainst, NULL, $item->containerWrapper() ) )
		{
			throw new \DomainException;
		}
		
		if ( \IPS\Request::i()->modaction == 'split' )
		{
			$form = $this->_splitForm( $item );
			$form->hiddenValues['modaction'] = 'split';
			foreach ( \IPS\Request::i()->multimod as $k => $v )
			{
				$form->hiddenValues['multimod['.$k.']'] = $v;
			}
			if ( $values = $form->values() )
			{
				$itemIdColumn = $item::$databaseColumnId;
				$commentIdColumn = $commentClass::$databaseColumnId;

				/* Create a copy of the old item for logging */
				$oldItem = $item;

				$comments = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select(
					'*',
					$commentClass::$databaseTable,
					array(
						array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $item->$itemIdColumn ),
						\IPS\Db::i()->in( $commentClass::$databasePrefix . $commentClass::$databaseColumnId, array_keys( \IPS\Request::i()->multimod ) )
					),
					$commentClass::$databasePrefix . $commentClass::$databaseColumnMap['date']
				), $commentClass ) );
				
				foreach ( $comments as $comment )
				{
					$firstComment = $comment;
					break;
				}

				/* If we don't have a $firstComment, something went wrong - perhaps the input multimod comment ids are all from a different topic for instance */
				if( !isset( $firstComment ) )
				{
					$form->error	= \IPS\Member::loggedIn()->language()->addToStack( 'mod_error_invalid_action' );
					goto splitFormError;
				}
					
				if ( isset( $values['_split_type'] ) and $values['_split_type'] === 'new' )
				{
					$item = $item::createItem( $firstComment->author(), $firstComment->mapped( 'ip_address' ), \IPS\DateTime::ts( $firstComment->mapped( 'date' ) ), $values[ $item::$formLangPrefix . 'container' ] );
					$item->processForm( $values );
					if ( isset( $item::$databaseColumnMap['first_comment_id'] ) )
					{
						$firstCommentIdColumn = $item::$databaseColumnMap['first_comment_id'];
						$item->$firstCommentIdColumn = $firstComment->$commentIdColumn;
					}

					/* Does the first post require moderator approval? */
					if ( $firstComment->hidden() === 1 )
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$column = $item::$databaseColumnMap['hidden'];
							$item->$column = 1;
						}
						elseif ( isset( $item::$databaseColumnMap['approved'] ) )
						{
							$column = $item::$databaseColumnMap['approved'];
							$item->$column = 0;
						}
					}
					/* Or is it hidden? */
					elseif ( $firstComment->hidden() === -1 )
					{
						if ( isset( $item::$databaseColumnMap['hidden'] ) )
						{
							$column = $item::$databaseColumnMap['hidden'];
						}
						elseif ( isset( $item::$databaseColumnMap['approved'] ) )
						{
							$column = $item::$databaseColumnMap['approved'];
						}

						$item->$column = -1;
					}

					$item->save();

				}
				else
				{
					$item = $item::loadFromUrl( $values['_split_into_url'] );

					if ( !$item->canView() )
					{
						throw new \DomainException;
					}
				}
				
				foreach ( $comments as $comment )
				{
					/* Remove featured comment associations */
					if( $comment->isFeatured() AND $oldItem )
					{
						\IPS\Application::load('core')->extensions( 'core', 'MetaData' )['FeaturedComments']->unfeatureComment( $oldItem, $comment );
					}

					/* Remove solved associations */
					if ( $oldItem and \IPS\IPS::classUsesTrait( $oldItem, 'IPS\Content\Solvable' ) and $oldItem->isSolved() and ( $oldItem->mapped('solved_comment_id') === $comment->$commentIdColumn ) )
					{
						$oldItem->toggleSolveComment( $comment->$commentIdColumn, FALSE );
					}

					if( $comment == $firstComment AND $comment->hidden() !== 0 )
					{
						if ( isset( $comment::$databaseColumnMap['hidden'] ) )
						{
							$column = $comment::$databaseColumnMap['hidden'];
							$comment->$column = 0;
						}
						elseif ( isset( $comment::$databaseColumnMap['approved'] ) )
						{
							$column = $comment::$databaseColumnMap['approved'];
							$comment->$column = 1;
						}
					}

					$comment->move( $item, TRUE );
				}

				$item->rebuildFirstAndLastCommentData();
				$oldItem->rebuildFirstAndLastCommentData();

				/* Update popular data */
				if( method_exists( $item, 'rebuildPopularTime' ) )
				{
					$item->rebuildPopularTime();
					$oldItem->rebuildPopularTime();
				}

				/* Option to do some post split stuff */
				if ( method_exists( $item, 'splitComplete' ) )
				{
					$item->splitComplete( $oldItem, $item, $comments );
				}

				/* Now reindex all of the comments - we do this last to prevent the "is last comment" flag continously bouncing around while the comments are still being moved */
				foreach( $comments as $comment )
				{
					/* Add to search index */
					if ( $comment instanceof \IPS\Content\Searchable )
					{
						\IPS\Content\Search\Index::i()->index( $comment );
					}
				}
				
				\IPS\Content\Search\Index::i()->rebuildAfterMerge( $item );
				\IPS\Content\Search\Index::i()->rebuildAfterMerge( $oldItem );
				
				/* Log it */
				\IPS\Session::i()->modLog( 'modlog__action_split', array(
					$item::$title					=> TRUE,
					$item->url()->__toString()		=> FALSE,
					$item->mapped( 'title' )			=> FALSE,
					$oldItem->url()->__toString()	=> FALSE,
					$oldItem->mapped( 'title' )		=> FALSE
				), $item );

				\IPS\Output::i()->redirect( $firstComment->url() );
			}
			else
			{
				/* Label for goto command */
				splitFormError:
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Output::i()->output  );
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html' );
				}
				return;
			}
		}
		elseif ( \IPS\Request::i()->modaction == 'merge' )
		{
			if ( !( \count( \IPS\Request::i()->multimod ) > 1 ) )
			{
				\IPS\Output::i()->error( 'cannot_merge_one_post', '1S136/S', 403, '' );
			}
			
			$comments	= array();
			$authors	= array();
			$content	= array();
			foreach( array_keys( \IPS\Request::i()->multimod ) AS $id )
			{
				try
				{
					$comments[$id]	= $commentClass::loadAndCheckPerms( $id );
					$content[]		= $comments[$id]->mapped( 'content' );
				}
				catch( \Exception $e ) {}
			}
			
			$form = new \IPS\Helpers\Form;
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Editor( 'final_comment_content', implode( '<p>&nbsp;</p>', $content ), TRUE, array(
				'app'			=> $item::$application,
				'key'			=> ucwords( $item::$module ),
				'autoSaveKey'	=> 'mod-merge-' . implode( '-', array_keys( $comments ) ),
			) ) );

			if ( $values = $form->values() )
			{
				$idColumn			= $item::$databaseColumnId;
				$commentIdColumn	= $commentClass::$databaseColumnId;
				$commentIds			= array_keys( \IPS\Request::i()->multimod );
				$firstComment		= $commentClass::loadAndCheckPerms( array_shift( $commentIds ) );
				$contentColumn		= $commentClass::$databaseColumnMap['content'];
				$firstComment->$contentColumn = $values['final_comment_content'];
				$firstComment->save();
				
				foreach( $commentIds AS $id )
				{
					try
					{
						$comment = $commentClass::loadAndCheckPerms( $id );
						\IPS\Db::i()->update( 'core_attachments_map', array(
							'id1'	=> $item->$idColumn,
							'id2'	=> $firstComment->$commentIdColumn,
						), array( 'location_key=? AND id1=? AND id2=?', (string) $item::$application . '_' . mb_ucfirst( $item::$module ), $item->$idColumn, $comment->$commentIdColumn ) );

						/* Merge likes */
						if ( \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
						{
							\IPS\Db::i()->update( 'core_reputation_index', array( 'type_id' => $firstComment->$commentIdColumn ), array( 'app=? and type=? and type_id=?', $item::$application, $comment::reactionType(), $id ) );
						}

						$comment->delete();
					}
					catch( \Exception $e ) {}
				}

				/* Fix duplicated reactions */
				if ( \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Db::i()->delete( array( 'row1' => 'core_reputation_index', 'row2' => 'core_reputation_index' ), 'row1.id > row2.id AND row1.member_id = row2.member_id AND row1.app = \'' . $item::$application . '\' AND row1.type = \'' . $comment::reactionType() . '\' AND row2.app = \'' . $item::$application . '\' AND row2.type = \'' . $comment::reactionType() . '\' AND row1.type_id = ' . $firstComment->$commentIdColumn . ' AND row2.type_id = ' . $firstComment->$commentIdColumn, NULL, NULL, NULL, 'row1' );
				}

				$item->rebuildFirstAndLastCommentData();

				/* Log it */
				\IPS\Session::i()->modLog( 'modlog__action_merge_comments', array(
					$firstComment::$title					=> TRUE,
					$firstComment->url()->__toString()		=> FALSE,
					$firstComment->$commentIdColumn			=> FALSE,
				), $item );
				
				\IPS\Output::i()->redirect( $firstComment->url() );
			}
			else
			{
				$this->_setBreadcrumbAndTitle( $item );
				\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
				
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->sendOutput( \IPS\Output::i()->output );
				}
				else
				{
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html' );
				}
				
				return;
			}
		}
		elseif ( \IPS\Request::i()->modaction == 'hide' )
		{
			if ( $commentClass::$hideLogKey )
			{
				$form = new \IPS\Helpers\Form;
				$form->class = 'ipsForm_vertical';
				$form->add( new \IPS\Helpers\Form\Text( 'hide_reason' ) );

				if ( $values = $form->values() )
				{
					foreach( array_keys( \IPS\Request::i()->multimod ) AS $id )
					{
						try
						{
							$comment = $commentClass::loadAndCheckPerms( $id );
							$comment->modAction( 'hide', NULL, $values['hide_reason'] );
						}
						catch( \Exception $e ) { }
					}

					if( ! \in_array( 'IPS\Content\Review', class_parents( $commentClass ) ) )
					{
						$item->rebuildFirstAndLastCommentData();
					}
					
					$url = $item->url();
					
					if ( isset( \IPS\Request::i()->page ) )
					{
						$url = $url->setPage( 'page', (int) \IPS\Request::i()->page );
					}
					
					\IPS\Output::i()->redirect( $url );
				}
				else
				{
					$this->_setBreadcrumbAndTitle( $item );
					\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );

					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->sendOutput( \IPS\Output::i()->output );
					}
					else
					{
						\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output, array( 'app' => \IPS\Dispatcher::i()->application->directory, 'module' => \IPS\Dispatcher::i()->module->key, 'controller' => \IPS\Dispatcher::i()->controller ) ), 200, 'text/html' );
					}

					return;
				}
			}
			else
			{
				foreach( array_keys( \IPS\Request::i()->multimod ) AS $id )
				{
					try
					{
						$comment = $commentClass::loadAndCheckPerms( $id );
						$comment->modAction( 'hide' );
					}
					catch( \Exception $e ) { }
				}
				
				$url = $item->url();
				
				if ( isset( \IPS\Request::i()->page ) )
				{
					$url = $url->setPage( 'page', (int) \IPS\Request::i()->page );
				}
				
				\IPS\Output::i()->redirect( $url );
			}

		}
		else
		{
			$object = NULL;

			if( isset( \IPS\Request::i()->multimod ) AND \is_array( \IPS\Request::i()->multimod ) )
			{
				foreach ( array_keys( \IPS\Request::i()->multimod ) as $id )
				{
					try
					{
						$object = $commentClass::loadAndCheckPerms( $id );
						if ( \IPS\Request::i()->modaction === 'delete' AND $object->isFeatured() )
						{
							$item->unfeatureComment( $object );
						}
						$object->modAction( \IPS\Request::i()->modaction, \IPS\Member::loggedIn() );
					}
					catch ( \Exception $e ) {}
				}
			}

			$item->resyncCommentCounts();
			$item->save();
						
			if ( $object and \IPS\Request::i()->modaction != 'delete' )
			{
				$url = $object->url();
				
				if ( isset( \IPS\Request::i()->page ) )
				{
					$url = $url->setQueryString( 'page', (int) \IPS\Request::i()->page );
				}
				
				\IPS\Output::i()->redirect( $url );
			}
			else
			{
				$url = $item->url();
				
				if ( isset( \IPS\Request::i()->page ) )
				{
					$url = $url->setPage( 'page', (int) \IPS\Request::i()->page );
				}
				
				\IPS\Output::i()->redirect( $url );
			}
		}
	}
	
	/**
	 * Form for splitting
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	\IPS\Helpers\Form
	 */
	protected function _splitForm( \IPS\Content\Item $item, ?\IPS\Content\Comment $comment = NULL )
	{
		try
		{
			$container = $item->container();
		}
		catch ( \Exception $e )
		{
			$container = NULL;	
		}
		
		$form = new \IPS\Helpers\Form;
		if ( $item::canCreate( \IPS\Member::loggedIn() ) )
		{
			$toAdd = array();
			$toggles = array();
							
			foreach ( $item::formElements( $item ) as $k => $field )
			{				
				if ( !\in_array( $k, array( 'poll', 'content', 'comment_edit_reason', 'comment_log_edit' ) ) )
				{
					if ( $k === 'container' AND ( $container AND $container->can( 'add' ) ) )
					{
						$field->defaultValue = $container;
						if ( !$field->value )
						{
							$field->value = $field->defaultValue;
						}
					}
					
					if ( !$field->htmlId )
					{
						$field->htmlId = $field->name;
					}
					$toggles[] = $field->htmlId;
					
					$toAdd[] = $field;
				}
			}
			
			$form->add( new \IPS\Helpers\Form\Radio( '_split_type', 'new', FALSE, array(
				'options' => array(
					'new'		=> \IPS\Member::loggedIn()->language()->addToStack( 'split_type_new', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) ),
					'existing'	=> \IPS\Member::loggedIn()->language()->addToStack( 'split_type_existing', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $item::$title ) ) ) )
				),
				'toggles' => array( 'new' => $toggles, 'existing' => array( 'split_into_url' ) ),
			) ) );

			foreach ( $toAdd as $field )
			{
				if ( $field->name == $item::$formLangPrefix . 'container' )
				{
					/* Add a custom permission check for splitting comments */
					$field->options['permissionCheck'] = function( $node ) use ( $item )
					{
						try
						{
							/* If the item is in a club, only allow moving to other clubs that you moderate */
							if ( \IPS\Settings::i()->clubs and \IPS\IPS::classUsesTrait( $item->container(), 'IPS\Content\ClubContainer' ) and $item->container()->club()  )
							{
								return $item::modPermission( 'move', \IPS\Member::loggedIn(), $node ) and $node->can( 'add' ) ;
							}

							if ( $node->can( 'add' ) )
							{
								return true;
							}
						}
						catch( \OutOfBoundsException $e ) { }

						return false;
					};
					if ( \IPS\Settings::i()->clubs and \IPS\IPS::classUsesTrait( $item->container(), 'IPS\Content\ClubContainer' ) )
					{
						$field->options['clubs'] = TRUE;
					}
				}

				$form->add( $field );
			}
		}
		$form->add( new \IPS\Helpers\Form\Url( '_split_into_url', NULL, FALSE, array(), function ( $val ) use ( $item )
		{
			if ( \IPS\Request::i()->_split_type == 'existing' OR !isset( \IPS\Request::i()->_split_type ) )
			{
				try
				{
					/* Validate the URL */
					$test = $item::loadFromUrl( $val );

					if ( !$test->canView() )
					{
						throw new \InvalidArgumentException;
					}

					/* Make sure the URL matches the content type we're splitting */
					foreach( array( 'app', 'module', 'controller') as $index )
					{
						if( $test->url()->hiddenQueryString[ $index ] != $val->hiddenQueryString[ $index ] )
						{
							throw new \InvalidArgumentException;
						}
					}
				}
				catch ( \Exception $e )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_url_bad_item', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( '__defart_' . $item::$title ) ) ) ) );
				}

				if( !$test->canMerge() )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'no_merge_permission', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( '__defart_' . $item::$title ) ) ) ) );
				}
			}
		}, NULL, NULL, 'split_into_url' ) );

		/* multi mod doesn't support moderation alerts , so if $comment isn't set, we can't use it */
		if( $comment )
		{
			$this->moderationAlertField($form, $comment );
		}

		return $form;
	}

	/**
	 * Retrieve content tagged the same
	 *
	 * @param	\int	$limit	How many items should be returned
	 *
	 * @note	Used with a widget, but can be used elsewhere too
	 * @return	array|NULL
	 */
	public function getSimilarContent( $limit = 5 )
	{
		if( !isset( static::$contentModel ) )
		{
			return NULL;
		}

		$class = static::$contentModel;
		if( !\is_subclass_of( $class, 'IPS\Content\Item' ) )
		{
			return NULL;
		}

		try
		{
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$items = [];

			/* Are we using elasticsearch? */
			if ( \IPS\Settings::i()->search_method == 'elastic' )
			{
				$query = \IPS\Content\Search\Query::init( \IPS\Member::loggedIn() );
				$query->resultsToGet = $limit;
				$query->filterByMoreLikeThis( $item );
				$query->filterByLastUpdatedDate( \IPS\DateTime::create()->sub( new \DateInterval( 'P2Y' ) ) );
				$results = $query->search();
				$results->init();

				foreach( $results as $result )
				{
					$object = $result->asArray()['indexData'];
					$class = $object['index_class'];
					$loaded = $class::loadAndCheckPerms( $object['index_object_id'] );

					if ( $loaded instanceof \IPS\Content\Item )
					{
						$obj = $loaded;
					}
					else
					{
						$obj = $loaded->item();
					}

					$items[] = $obj;
				}
			}
			else
			{
				if ( !$item instanceof \IPS\Content\Tags or ( $item->tags() === NULL and $item->prefix( FALSE ) === NULL ) )
				{
					return NULL;
				}

				/* Store tags in array, so that we can add a prefix if set */
				$tags = $item->tags() ?: array();
				$tags[] = $item->prefix( FALSE );

				/* Build the where clause */
				$where = array(
					array('(' . \IPS\Db::i()->in( 'tag_text', $tags ) . ')'),
					array('!(tag_meta_app=? and tag_meta_area=? and tag_meta_id=?)', $class::$application, $class::$module, \IPS\Request::i()->id),
					array('(' . \IPS\Db::i()->findInSet( 'tag_perm_text', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'tag_perm_text=? )', '*'),
					array('tag_perm_visible=1')
				);

				/* Allow the item to manipulate the query if needed */
				if ( $item->similarContentFilter() )
				{
					$where = array_merge( $where, $item->similarContentFilter() );
				}

				$select = \IPS\Db::i()->select(
					'tag_meta_app,tag_meta_area,tag_meta_id',
					'core_tags',
					$where,
					'tag_added DESC',
					array(0, $limit),
					array('tag_meta_app', 'tag_meta_area', 'tag_meta_id', 'tag_added')
				)->join(
					'core_tags_perms',
					array('tag_perm_aai_lookup=tag_aai_lookup')
				);

				foreach ( $select as $result )
				{
					foreach ( \IPS\Application::load( $result['tag_meta_app'] )->extensions( 'core', 'ContentRouter' ) as $key => $router )
					{
						foreach ( $router->classes as $itemClass )
						{
							if ( $itemClass::$module == $result['tag_meta_area'] )
							{
								try
								{
									$items[$result['tag_meta_id']] = $itemClass::loadAndCheckPerms( $result['tag_meta_id'] );
									break;
								}
								catch ( \Exception $e )
								{
								}
							}
						}
					}

				}
			}

			return $items;
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Get Cover Photo Storage Extension
	 *
	 * @return	string
	 */
	protected function _coverPhotoStorageExtension()
	{
		$class = static::$contentModel;
		return $class::$coverPhotoStorageExtension;
	}
	
	/**
	 * Set Cover Photo
	 *
	 * @param	\IPS\Helpers\CoverPhoto	$photo	New Photo
	 * @return	void
	 */
	protected function _coverPhotoSet( \IPS\Helpers\CoverPhoto $photo )
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			$photoColumn = $class::$databaseColumnMap['cover_photo'];
			$item->$photoColumn = (string) $photo->file;
			
			$offsetColumn = $class::$databaseColumnMap['cover_photo_offset'];
			$item->$offsetColumn = (int) $photo->offset;
			
			$item->save();
		}
		catch ( \OutOfRangeException $e ){}
	}

	/**
	 * Get Cover Photo
	 *
	 * @return	\IPS\Helpers\CoverPhoto
	 */
	protected function _coverPhotoGet()
	{
		try
		{
			$class = static::$contentModel;
			$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			return $item->coverPhoto();
		}
		catch ( \OutOfRangeException $e )
		{
			return new \IPS\Helpers\CoverPhoto;
		}
	}
	
	/**
	 * Reveal
	 *
	 * @return	void
	 */
	protected function reveal()
	{
		\IPS\Session::i()->csrfCheck();
		
		if( !\IPS\Member::loggedIn()->modPermission( 'can_view_anonymous_posters' ) )
		{
			\IPS\Output::i()->error( 'node_error', '2S136/1V', 403, '' );
		}
		
		$class = static::$contentModel;
		$item = $class::loadAndCheckPerms( \IPS\Request::i()->id );
		
		if( isset( \IPS\Request::i()->comment ) )
		{
			$class = $class::$commentClass;
			$id = \IPS\Request::i()->comment;
		}
		else
		{
			$id = \IPS\Request::i()->id;
		}
		
		try
		{
			$anonymousAuthor = \IPS\Db::i()->select( 'anonymous_member_id', 'core_anonymous_posts', array( 'anonymous_object_class=? and anonymous_object_id=?', $class, $id )  )->first();
		}
		catch ( \UnderflowException $e )
		{
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicHover( \IPS\Member::loggedIn()->language()->addToStack( 'anon_user_deleted' ) );
				return;
			}
			\IPS\Output::i()->error( 'anon_user_deleted', '2S136/1W', 403, '' );
		}
		
		$author = \IPS\Member::load( $anonymousAuthor );

		$addWarningUrl = \IPS\Http\Url::internal( "app=core&module=system&controller=warnings&do=warn&id={$author->member_id}", 'front', 'warn_add', array( $author->members_seo_name ) );
		if ( isset( \IPS\Request::i()->wr ) )
		{
			$addWarningUrl = $addWarningUrl->setQueryString( 'ref', \IPS\Request::i()->wr );
		}
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile', 'core' )->hovercard( $author, $addWarningUrl );
		}
		else
		{
			\IPS\Output::i()->redirect( $author->url() );
		}
	}
	
	/**
	 * Reveal Comment
	 *
	 * @return	void
	 */
	protected function revealComment()
	{
		$this->reveal();
	}

	/**
	 * A convenient hook point to finish any set up in manage()
	 *
	 * @param \IPS\Content\Item 	$item	The item that is being set up in manage()
	 * @return	void
	 */
	protected function finishManage( $item )
	{
	}

	/**
	 * Get the moderation alert fields
	 *
	 * @param \IPS\Helpers\Form $form
	 * @return void
	 */
	protected function moderationAlertField( \IPS\Helpers\Form $form, \IPS\Content $item )
	{
		if( \IPS\Member::loggedIn()->modPermission('can_manage_alerts') AND $item->author()->member_id )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'moderation_alert', FALSE, FALSE, array( 'togglesOn' => array( 'moderation_alert_title', 'moderation_alert_content','moderation_alert_anonymous','moderation_alert_reply' ) ) ) );
			\IPS\Member::loggedIn()->language()->words[ 'moderation_alert'] = \IPS\Member::loggedIn()->language()->addToStack('moderation_alert_name', FALSE, ['sprintf' => [ $item->author()->name ] ] );
			$form->add( new \IPS\Helpers\Form\Text( 'moderation_alert_title', NULL, TRUE, array( 'maxLength' => 255 ), NULL, NULL, NULL, 'moderation_alert_title' ) );
			$form->add( new \IPS\Helpers\Form\Editor( 'moderation_alert_content', NULL, TRUE, array( 'app' => 'core', 'key' => 'Alert', 'autoSaveKey' => 'createAlert', 'attachIds' => NULL ), NULL, NULL, NULL, 'moderation_alert_content' ) );
			$form->add( new \IPS\Helpers\Form\YesNo( 'moderation_alert_anonymous', FALSE, TRUE, array( 'togglesOff' => array( 'moderation_alert_reply') ), NULL, NULL, NULL, 'moderation_alert_anonymous' ) );
			$form->add( new \IPS\Helpers\Form\Radio( 'moderation_alert_reply', 0, TRUE, array( 'disabled' => (bool) \IPS\Member::loggedIn()->members_disable_pm, 'options' => array( '0' => 'alert_no_reply', '1' => 'alert_can_reply', '2' => 'alert_must_reply' ) ), NULL, NULL, NULL, 'moderation_alert_reply' ) );

			if ( \IPS\Member::loggedIn()->members_disable_pm )
			{
				\IPS\Member::loggedIn()->language()->words['alert_reply_desc'] = \IPS\Member::loggedIn()->language()->get('alert_reply__nopm_desc');
			}
		}
	}

	/**
	 * Send the alert
	 *
	 * @param array $values
	 * @param Item|Comment $content
	 * @return \IPS\core\Alerts\Alert
	 */
	protected function sendModerationAlert( array $values, \IPS\Content\Item|\IPS\Content\Comment $content ) : \IPS\core\Alerts\Alert
	{
		$values['alert_title'] = $values['moderation_alert_title'];
		$values['alert_content'] = $values['moderation_alert_content'];
		$values['alert_recipient_type'] = 'user';
		$values['alert_recipient_user'] = $content->author()->member_id;
		$values['alert_anonymous'] = $values['moderation_alert_anonymous'];
		$values['alert_reply'] = $values['moderation_alert_reply'];
		return \IPS\core\Alerts\Alert::_createFromForm( $values, NULL );
	}
}
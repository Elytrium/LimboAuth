<?php
/**
 * @brief		Topic View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		08 Jan 2014
 */

namespace IPS\forums\modules\front\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Topic View
 */
class _topic extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\forums\Topic';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_topic.js', 'forums' ) );
		parent::execute();
	}

	/**
	 * View Topic
	 *
	 * @return    \IPS\Content\Item
	 * @throws \Exception
	 */
	protected function manage()
	{
		/* Load topic */
		$topic = parent::manage();
		\IPS\Member::loggedIn()->language()->words['submit_comment'] = \IPS\Member::loggedIn()->language()->addToStack( 'submit_reply', FALSE );

		/* If it failed, it might be because we want a password */
		if ( $topic === NULL )
		{
			$forum = NULL;
			try
			{
				$topic = \IPS\forums\Topic::load( \IPS\Request::i()->id );
				$forum = $topic->container();
				if ( $forum->can('view') and !$forum->loggedInMemberHasPasswordAccess() )
				{
					\IPS\Output::i()->redirect( $forum->url()->setQueryString( 'topic', \IPS\Request::i()->id ) );
				}
				
				if ( !$topic->canView() )
				{
					if ( $topic instanceof \IPS\Content\Hideable and $topic->hidden() )
					{
						/* If the item is hidden we don't want to show the custom no permission error as the conditions may not apply */
						\IPS\Output::i()->error( 'node_error', '2F173/O', 404, '' );
					}
					else
					{
						\IPS\Output::i()->error(  $forum ? $forum->errorMessage() : 'node_error_no_perm', '2F173/H', 403, '' );
					}
				}
			}
			catch ( \OutOfRangeException $e )
			{
				/* Nope, just a generic no access error */
				\IPS\Output::i()->error( 'node_error', '2F173/1', 404, '' );
			}
		}
		
		$topic->container()->clubCheckRules();
		
		/* If there's only one forum and we're not in a club, and we're not in a sub-forum, we actually don't want the nav */
		$theOnlyForum = NULL;
		if ( !$topic->container()->club() AND ( ( $theOnlyForum = \IPS\forums\Forum::theOnlyForum() AND $theOnlyForum->_id == $topic->container()->_id ) or \IPS\forums\Forum::isSimpleView() ) )
		{
			$topicBreadcrumb = array_pop( \IPS\Output::i()->breadcrumb );
			\IPS\Output::i()->breadcrumb = isset( \IPS\Output::i()->breadcrumb['module'] ) ? array( 'module' => \IPS\Output::i()->breadcrumb['module'] ) : array();
			\IPS\Output::i()->breadcrumb[] = $topicBreadcrumb;
		}
		
		/* We need to shift the breadcrumb if we are in a sub-forum and we have $theOnlyForum */
		if ( $theOnlyForum AND $theOnlyForum->_id != $topic->container()->_id )
		{
			array_shift( \IPS\Output::i()->breadcrumb );
			array_shift( \IPS\Output::i()->breadcrumb );
		}
		
		/* Legacy findpost redirect */
		if ( \IPS\Request::i()->findpost )
		{
			\IPS\Output::i()->redirect( $topic->url()->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->findpost ) ), NULL, 301 );
		}
		elseif ( \IPS\Request::i()->p )
		{
			\IPS\Output::i()->redirect( $topic->url()->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->p ) ), NULL, 301 );
		}
		elseif ( \IPS\Request::i()->pid )
		{
			\IPS\Output::i()->redirect( $topic->url()->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->pid ) ), NULL, 301 );
		}
		
		if ( \IPS\Request::i()->view )
		{
			$this->_doViewCheck();
		}

		/* If the topic is locked and scheduled to unlock already, or vice versa, do that */
		if( $topic->locked() && $topic->topic_open_time && $topic->topic_open_time < time() )
		{
			$topic->state = 'open';
			$topic->save();
		}
		elseif( !$topic->locked() && $topic->topic_close_time && $topic->topic_close_time < time() )
		{
			$topic->state = 'closed';
			$topic->save();
		}

		/* If this is an AJAX request fetch the comment form now. The HTML will be cached so calling here and then again in the template has no overhead
			and this is necessary if you entered into a topic with &queued_posts=1, approve the posts, then try to reply. Otherwise, clicking into the
			editor produces an error when the getUploader=1 call occurs, and submitting a reply results in an error. */
		if ( \IPS\Request::i()->isAjax() and ( !isset( \IPS\Request::i()->preview ) OR !\IPS\Request::i()->preview ) )
		{
			$topic->commentForm();
		}
	
		/* AJAX hover preview? */
		if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->preview )
		{
			$postClass = '\IPS\forums\Topic\Post';

			if( $topic->isArchived() )
			{
				$postClass = '\IPS\forums\Topic\ArchivedPost';
			}
			
			/* If this topic was moved or merged, load that up in case someone loads the preview after that happens but before they reload the page */
			$previewTopic = $topic;
			if ( \in_array( $topic->state, array( 'merged', 'link' ) ) )
			{
				$movedTo = explode( '&', $topic->moved_to );
				
				try
				{
					$previewTopic = \IPS\forums\Topic::loadAndCheckPerms( $movedTo[0] );
				}
				catch( \OutOfRangeException $e )
				{
					/* I can't help you */
					\IPS\Output::i()->error( 'node_error', '2F173/Q', 404, '' );
					return;
				}
			}

			$firstPost = $postClass::load( $previewTopic->topic_firstpost );
			
			$topicOverview = array( 'firstPost' => array( $previewTopic->isQuestion() ? 'question_mainTab' : 'first_post', $firstPost ) );

			if ( $previewTopic->posts > 1 )
			{
				$latestPost = $previewTopic->comments( 1, 0, 'date', 'DESC' );
				$topicOverview['latestPost'] = array( $previewTopic->isQuestion() ? 'latest_answer' : 'latest_post', $latestPost );
			
				$timeLastRead = $previewTopic->timeLastRead();
				if ( $timeLastRead instanceof \IPS\DateTime AND $previewTopic->unread() !== 0 )
				{
					$firstUnread = $previewTopic->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead );
					if( $firstUnread instanceof \IPS\forums\Topic\Post AND $firstUnread->date !== $latestPost->date AND $firstUnread->date !== $firstPost->date )
					{
						$topicOverview['firstUnread'] = array( 'first_unread_post_hover', $previewTopic->comments( 1, NULL, 'date', 'asc', NULL, NULL, $timeLastRead ) );
					}
				}			
			}

			if ( $previewTopic->isQuestion() and $previewTopic->topic_answered_pid )
			{
				$topicOverview['bestAnswer'] = array( 'best_answer_post', \IPS\forums\Topic\Post::load( $previewTopic->topic_answered_pid ) );
			}

			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'forums' )->topicHover( $previewTopic, $topicOverview ) );
			return;
		}
		
		$topic->container()->setTheme();
		
		/* Watch for votes */
		if ( $poll = $topic->getPoll() )
		{
			$poll->attach( $topic );
		}
				
		/* How are we sorting posts? */
		$question = NULL;
		$offset = NULL;
		$order = 'date';
		$orderDirection = 'asc';
		$where = NULL;
		if( \IPS\forums\Topic::modPermission( 'unhide', NULL, $topic->container() ) AND \IPS\Request::i()->queued_posts )
		{
			if ( $topic->isArchived() )
			{
				$where = 'archive_queued=1';
			}
			else
			{
				$where = 'queued=1';
			}
			
			$queuedPagesCount = ceil( \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', array( 'topic_id=? AND queued=1', $topic->id ) )->first() / $topic->getCommentsPerPage() );
			$pagination = ( $queuedPagesCount > 1 ) ? $topic->commentPagination( array( 'queued_posts', 'sortby' ), 'pagination', $queuedPagesCount ) : NULL;
			
			if ( $topic->isQuestion() )
			{
				$question = $topic->comments( 1, 0 );
			}
		}
		else
		{
			if ( $topic->isQuestion() )
			{
				$question	= $topic->comments( 1, 0 );
				try
				{
					$question->warning = \IPS\core\Warnings\Warning::constructFromData( \IPS\Db::i()->select( '*', 'core_members_warn_logs', array( array( 'wl_content_app=? AND wl_content_module=? AND wl_content_id1=? AND wl_content_id2=?', 'forums', 'forums-comment', $question->topic_id, $question->pid ) ) )->first() );
				}
				catch ( \UnderflowException $e ) { }
						
				$page		= ( isset( \IPS\Request::i()->page ) ) ? \intval( \IPS\Request::i()->page ) : 1;

				if( $page < 1 )
				{
					$page	= 1;
				}

				$offset		= ( ( $page - 1 ) * $topic::getCommentsPerPage() ) + 1;
				
				if ( ( !isset( \IPS\Request::i()->sortby ) or \IPS\Request::i()->sortby != 'date' ) )
				{
					if ( $topic->isArchived() )
					{
						$order = "archive_is_first desc, archive_bwoptions";
						$orderDirection = 'desc';
					}
					else
					{
						$order = "new_topic DESC, post_bwoptions DESC, post_field_int DESC, post_date";
						$orderDirection = 'ASC';
					}
				}
			}
			$pagination = ( $topic->commentPageCount() > 1 ) ? $topic->commentPagination( array( 'sortby' ) ) : NULL;
		}

		if ( isset( \IPS\Request::i()->ltqid ) and $liveTopic = $topic->getLiveTopic() )
		{
			try
			{
				$question = \IPS\cloud\LiveTopic\Question::load( \IPS\Request::i()->ltqid );

				if ( is_string( $where ) )
				{
					$_where[] = $where;
					$where = $_where;
				}

				$where[] = array( 'pid=? OR pid IN(?)', $question->post_id, \IPS\Db::i()->select( 'post_id', 'cloud_livetopics_answer', [ 'question_id=?', $question->id ] ) );
			}
			catch( \OutOfRangeException ) { }
		}

		$comments = $topic->comments( NULL, $offset, $order, $orderDirection, NULL, NULL, NULL, $where, FALSE, ( isset( \IPS\Request::i()->showDeleted ) ) );
		$current  = current( $comments );
		reset( $comments );

		if( ( !\count( $comments ) AND !$topic->isQuestion() ) OR ( $topic->isQuestion() AND empty( $question ) ) )
		{
			\IPS\Output::i()->error( 'no_posts_returned', '2F173/L', 404, '' );
		}

		/* Mark read */
		if( !$topic->isLastPage() AND $topic->unread() !== 0 )
		{
			$maxTime	= 0;

			foreach( $comments as $comment )
			{
				$maxTime	= ( $comment->mapped('date') > $maxTime ) ? $comment->mapped('date') : $maxTime;
			}

			if( $topic->timeLastRead() === NULL OR $maxTime > $topic->timeLastRead()->getTimestamp() )
			{
				$topic->markRead( NULL, $maxTime );
			}
		}
		elseif( $topic->isLastPage() )
		{
			/* See if the last comment is hidden or pending approval. If so, force the topic read because it won't be done so automatically. */
			$lastComment = end( $comments );

			if( $lastComment and $lastComment->hidden() !== 0 )
			{
				$topic->markRead( NULL, NULL, NULL, TRUE );
			}

			reset( $comments );
		}

		/* A convenient hook point to do any further set up */
		$this->finishManage( $topic );

		$votes		= array();
		$topicVotes = array();

		/* Get post ratings for this user */
		if ( $topic->isQuestion() && \IPS\Member::loggedIn()->member_id )
		{
			$votes		= $topic->answerVotes( \IPS\Member::loggedIn() );
			$topicVotes	= $topic->votes();
		}
		
		if ( $topic->isQuestion() )
		{
			\IPS\Member::loggedIn()->language()->words[ 'topic__comment_placeholder' ] = \IPS\Member::loggedIn()->language()->addToStack( 'question__comment_placeholder', FALSE );
		}

		/* Online User Location */
		\IPS\Session::i()->setLocation( $topic->url(), ( $topic->container()->password or !$topic->container()->can_view_others or $topic->container()->min_posts_view ) ? 0 : $topic->onlineListPermissions(), 'loc_forums_viewing_topic', array( $topic->title => FALSE ) );

		/* Next unread */
		try
		{
			$nextUnread	= $topic->containerHasUnread();
		}
		catch( \Exception $e )
		{
			$nextUnread	= NULL;
		}

		/* Sidebar? */
		if ( $topic->showSummaryOnDesktop() == 'sidebar' )
		{
			\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'topics' )->activity( $topic, 'sidebar' );
		}

		if ( $liveTopic = $topic->getLiveTopic() )
		{
			if ( ! isset( \IPS\Output::i()->sidebar['contextual'] ) )
			{
				\IPS\Output::i()->sidebar['contextual'] = '';
			}

			\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'livetopics', 'cloud' )->liveTopicSidebar( $liveTopic );
		}

		/* Add Json-LD */
		$isQuestion = ( $topic->isQuestion() or $topic->isSolved() );

		if ( $isQuestion )
		{
			\IPS\Output::i()->jsonLd['topic'] = array(
				'@context'		=> "http://schema.org",
				'@type'			=> 'QAPage',
				'@id'			=> (string) $topic->url(),
				'url'			=> (string) $topic->url(),
				'mainEntity' => [
					'@type'	=> "Question",
					'name'	=> $topic->title,
					'text'  => $topic->comments( 1, 0 )->truncated( TRUE, NULL ),
					'answerCount' => $topic->posts ? $topic->posts - 1 : 0,
					'dateCreated' => \IPS\DateTime::ts( $topic->start_date )->format( \IPS\DateTime::ISO8601 ),
					'author' => [
						'@type' => 'Person',
						'name' => $topic->author()->name
					]
				]
			);

			if( $topic->topic_answered_pid )
			{
				try
				{
					$postClass = '\IPS\forums\Topic\Post';
		
					if( $topic->isArchived() )
					{
						$postClass = '\IPS\forums\Topic\ArchivedPost';
					}
					
					$answer = $postClass::load( $topic->topic_answered_pid );
					
					/* Set up our column names */
					$authorIdColumn = $answer::$databaseColumnMap['author'];
					$dateColumn = $answer::$databaseColumnMap['date'];
					
					if ( $truncatedAnswer = $answer->truncated( TRUE, NULL ) )
					{
						\IPS\Output::i()->jsonLd['topic']['mainEntity']['acceptedAnswer'] = array(
							'@type'		=> 'Answer',
							'text'		=> $truncatedAnswer,
							'url'		=> (string) $answer->url(),
							'dateCreated' => \IPS\DateTime::ts( $answer->$dateColumn )->format( \IPS\DateTime::ISO8601 ),
							'upvoteCount' => ( $topic->isArchived() ) ? $answer->field_int : $answer->post_field_int,
							'author'	=> array(
								'@type'		=> 'Person',
								'name'		=> \IPS\Member::load( $answer->$authorIdColumn )->name,
								'image'		=> \IPS\Member::load( $answer->$authorIdColumn )->get_photo( TRUE, TRUE )
							),
						);
						
						if( $topic->isQuestion() )
						{
							\IPS\Output::i()->jsonLd['topic']['mainEntity']['acceptedAnswer']['upvoteCount'] = ( $topic->isArchived() ) ? $answer->field_int : $answer->post_field_int;
						}
	
						if( $answer->author_id )
						{
							\IPS\Output::i()->jsonLd['topic']['mainEntity']['acceptedAnswer']['author']['url']	= (string) \IPS\Member::load( $answer->$authorIdColumn )->url();
						}
					}
				}
				catch( \OutOfRangeException $e ){}
			}
		}
		else
		{
			\IPS\Output::i()->jsonLd['topic'] = array(
				'@context'		=> "http://schema.org",
				'@type'			=> 'DiscussionForumPosting',
				'@id'			=> (string) $topic->url(),
				'isPartOf'		=> array(
					'@id' => \IPS\Settings::i()->base_url . '#website'
				),
				'publisher'		=> array(
					'@id' => \IPS\Settings::i()->base_url . '#organization'
				),
				'url'			=> (string) $topic->url(),
				'discussionUrl'	=> (string) $topic->url(),
				'mainEntityOfPage' => array(
					'@type'	=> 'WebPage',
					'@id'	=> (string) $topic->url()
				),
				'pageStart'		=> 1,
				'pageEnd'		=> $topic->commentPageCount(),
			);
		}

		/* Add in comments */
		if( $topic->posts > 1 )
		{
			if ( $isQuestion )
			{
				\IPS\Output::i()->jsonLd['topic']['mainEntity']['suggestedAnswer'] = [];
			}
			else
			{
				\IPS\Output::i()->jsonLd['topic']['comment'] = [];
			}

			$i = 0;
			$commentJson = [];
			foreach( $comments as $comment )
			{
				/* Set up our column names */
				$idColumn = $comment::$databaseColumnId;
				$authorIdColumn = $comment::$databaseColumnMap['author'];
				$dateColumn = $comment::$databaseColumnMap['date'];
				
				// Don't include the first post as a "comment"
				if( $comment->$idColumn == $topic->topic_firstpost )
				{
					continue;
				}

				// Don't include the answer as the suggested answer
				if( $isQuestion and ( $comment->$idColumn === $topic->mapped('solved_comment_id') ) )
				{
					continue;
				}

				if ( $truncatedComment = $comment->truncated( TRUE, NULL ) )
				{
					$url = $topic->url()->setPage( 'page', \IPS\Request::i()->page );

					$commentJson[$i] = array(
						'@type' => $isQuestion ? 'Answer' : 'Comment',
						'@id' => (string)$url->setFragment( 'comment-' . $comment->$idColumn ),
						'url' => (string)$url->setFragment( 'comment-' . $comment->$idColumn ),
						'author' => array(
							'@type' => 'Person',
							'name' => \IPS\Member::load( $comment->$authorIdColumn )->name,
							'image' => \IPS\Member::load( $comment->$authorIdColumn )->get_photo( TRUE, TRUE )
						),
						'dateCreated' => \IPS\DateTime::ts( $comment->$dateColumn )->format( \IPS\DateTime::ISO8601 ),

						'text' => $truncatedComment,
					);

					if ( $comment->$authorIdColumn )
					{
						$commentJson[$i]['author']['url'] = (string)\IPS\Member::load( $comment->$authorIdColumn )->url();
					}

					if( $isQuestion )
					{
						$commentJson[$i]['upvoteCount'] = ( $topic->isArchived() ) ? $comment->field_int : $comment->post_field_int;
					}
					else
					{
						$commentJson[$i]['upvoteCount'] = $comment->reactionCount();
					}

					$i++;
				}
			}

			if ( $isQuestion )
			{
				\IPS\Output::i()->jsonLd['topic']['mainEntity']['suggestedAnswer'] = $commentJson;
			}
			else
			{
				\IPS\Output::i()->jsonLd['topic']['comment'] = $commentJson;
			}
		}

		/* Do we have a real author */
		if( $topic->starter_id )
		{
			\IPS\Output::i()->jsonLd['topic']['author']['url'] = (string) \IPS\Member::load( $topic->starter_id )->url();

			\IPS\Output::i()->jsonLd['topic']['publisher']['member'] = array(
				'@type'		=> "Person",
				'name'		=> \IPS\Member::load( $topic->starter_id )->name,
				'image'		=> (string) \IPS\Member::load( $topic->starter_id )->get_photo( TRUE, TRUE ),
				'url'		=> (string) \IPS\Member::load( $topic->starter_id )->url(),
			);
		}

		/* Enable caching for archived topics */
		if( $topic->isArchived() AND !\IPS\Member::loggedIn()->member_id )
		{
			/* We do not want to use the \IPS\CACHE_PRIVATE_TIMEOUT constant here, as we explicitly want to cache archived topics for longer times */
			$httpHeaders = array( 'Expires'		=> \IPS\DateTime::create()->add( new \DateInterval( 'PT12H' ) )->rfc1123() ,
								  'Cache-Control'	=> 'no-cache="Set-Cookie", max-age=' . ( 60 * 60 * 12 ) . ", s-maxage=" . ( 60 * 60 * 12 ) . ", public, stale-if-error, stale-while-revalidate" );

			\IPS\Output::i()->httpHeaders += $httpHeaders;
		}

		\IPS\Output::i()->jsonLd['topic'] = array_merge_recursive( array(
			'name'			=> $topic->mapped('title'),
			'headline'		=> $topic->mapped('title'),
			'text'			=> $topic->isQuestion() ? $question->truncated( TRUE, NULL ) : $current->truncated( TRUE, NULL ),
			'dateCreated'	=> \IPS\DateTime::ts( $topic->start_date )->format( \IPS\DateTime::ISO8601 ),
			'datePublished'	=> \IPS\DateTime::ts( $topic->start_date )->format( \IPS\DateTime::ISO8601 ),
			'dateModified'	=> \IPS\DateTime::ts( $topic->last_post )->format( \IPS\DateTime::ISO8601 ),
			/* Image is required, but we don't have "topic images", so we'll use topic starter's profile photo for now */
			'image'			=> (string) \IPS\Member::load( $topic->starter_id )->get_photo( TRUE, TRUE ),
			'author'		=> array(
				'@type'		=> 'Person',
				'name'		=> \IPS\Member::load( $topic->starter_id )->name,
				'image'		=> \IPS\Member::load( $topic->starter_id )->get_photo( TRUE, TRUE )
			),
			'interactionStatistic'	=> array(
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/ViewAction",
					'userInteractionCount'	=> $topic->views
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/CommentAction",
					'userInteractionCount'	=> $topic->posts - 1 // We subtract one to account for the "first post"
				),
				array(
					'@type'					=> 'InteractionCounter',
					'interactionType'		=> "http://schema.org/FollowAction",
					'userInteractionCount'	=> $topic->followersCount()
				),
			)
		), \IPS\Output::i()->jsonLd['topic'] );

		/* Add og:image meta tags */
		if( count( $file = $topic->imageAttachments(1 ) ) )
		{
			$object = \IPS\File::get( 'core_Attachment', $file[0]['attach_location'] );
			\IPS\Output::i()->metaTags['og:image'] = (string) $object->url->setScheme( ( mb_substr( \IPS\Settings::i()->base_url, 0, 5 ) === 'https' ) ? 'https' : 'http' );
		}

		/* Set default search to this topic */
		\IPS\Output::i()->defaultSearchOption = array( 'forums_topic', 'forums_topic_el' );

		/* Show topic */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'topics.css', 'forums' ) );
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'topics' )->topic( $topic, $comments, $question, $votes, $nextUnread, $pagination, $topicVotes );

		return $topic;
	}

	/**
	 * Check our view method and act accordingly (redirect if appropriate)
	 *
	 * @return	void
	 */
	protected function _doViewCheck()
	{
		try
		{
			$class	= static::$contentModel;
			$topic	= $class::loadAndCheckPerms( \IPS\Request::i()->id );
			
			switch( \IPS\Request::i()->view )
			{
				case 'getnewpost':
					\IPS\Output::i()->redirect( $topic->url( 'getNewComment' ) );
				break;
				
				case 'getlastpost':
					\IPS\Output::i()->redirect( $topic->url( 'getLastComment' ) );
				break;
			}
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/F', 403, '' );
		}
	}
	
	/**
	 * Edit topic
	 *
	 * @return	void
	 */
	public function edit()
	{
		try
		{
			$class = static::$contentModel;
			$topic = $class::loadAndCheckPerms( \IPS\Request::i()->id );
			$forum = $topic->container();
			$forum->setTheme();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/D', 403, 'no_module_permission_guest' );
		}
		
		if ( $forum->forums_bitoptions['bw_enable_answers'] )
		{
			\IPS\Member::loggedIn()->language()->words['topic_mainTab'] = \IPS\Member::loggedIn()->language()->addToStack( 'question_mainTab', FALSE );
		}
		
		// We check if the form has been submitted to prevent the user loosing their content
		if ( isset( \IPS\Request::i()->form_submitted ) )
		{
			if ( ! $topic->couldEdit() )
			{
				\IPS\Output::i()->error( 'edit_no_perm_err', '2F173/E', 403, '' );
			}
		}
		else
		{
			if ( ! $topic->canEdit() )
			{
				\IPS\Output::i()->error( 'edit_no_perm_err', '2F173/E', 403, '' );
			}
		}
		
		$formElements = $class::formElements( $topic, $forum );

		$hasModOptions = FALSE;
		/* We used to just check against the ability to lock, however this may not be enough - a moderator could pin, for example, but not lock */
		foreach( array( 'lock', 'pin', 'feature' ) AS $perm )
		{
			if ( $class::modPermission( $perm, NULL, $forum ) )
			{
				$hasModOptions = TRUE;
				break;
			}
		}
		if( $topic->canHide() )
		{
		    $hasModOptions = TRUE;
		}
		
		$form = $topic->buildEditForm();
		
		if ( $values = $form->values() )
		{
			if ( $topic->canEdit() )
			{
				$titleField = $topic::$databaseColumnMap['title'];
				$oldTitle = $topic->$titleField;
				
				$topic->processForm( $values );
				$topic->save();
				$topic->processAfterEdit( $values );

				/* Moderator log */
				$toLog = array( $topic::$title => FALSE, $topic->url()->__toString() => FALSE, $topic::$title => TRUE, $topic->mapped( 'title' ) => FALSE );
					
				if ( $oldTitle != $topic->$titleField )
				{
					$toLog[ $oldTitle ] = false; 
				}
				
				\IPS\Session::i()->modLog( 'modlog__item_edit', $toLog, $topic );

				\IPS\Output::i()->redirect( $topic->url() );
			}
			else
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack('edit_no_perm_err');
			}
		}

		$formTemplate = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'forums' ), 'createTopicForm' ), $forum, $hasModOptions, $topic );

		$title = $forum->forums_bitoptions['bw_enable_answers'] ? 'edit_question' : 'edit_topic';

		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->createTopic( $formTemplate, $forum, $title );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $title );
		
		if ( !\IPS\forums\Forum::theOnlyForum() and ! \IPS\forums\Forum::isSimpleView() )
		{
			try
			{
				foreach( $forum->parents() AS $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
				\IPS\Output::i()->breadcrumb[] = array( $forum->url(), $forum->_title );
			}
			catch( \Exception $e ) {}
		}
		
		\IPS\Output::i()->breadcrumb[] = array( $topic->url(), $topic->mapped('title') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( $title ) );
	}

	/**
	 * Unarchive
	 *
	 * @return	void
	 */
	public function unarchive()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$topic->canUnarchive() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/B', 404, '' );
		}
		
		$topic->topic_archive_status = \IPS\forums\Topic::ARCHIVE_RESTORE;
		$topic->save();
		
		/* Make sure the task is enabled */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'unarchive' ) );

		/* Log */
		\IPS\Session::i()->modLog( 'modlog__unarchived_topic', array( $topic->url()->__toString() => FALSE, $topic->mapped( 'title' ) => FALSE ), $topic );
		
		\IPS\Output::i()->redirect( $topic->url() );
	}

	/**
	 * Remove the archive exclude flag
	 *
	 * @return void
	 */
	public function removeArchiveExclude()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			if ( !$topic->canRemoveArchiveExcludeFlag() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/P', 404, '' );
		}

		$topic->topic_archive_status = \IPS\forums\Topic::ARCHIVE_NOT;
		$topic->save();

		/* Log */
		\IPS\Session::i()->modLog( 'modlog__removed_archive_exclude_topic', array( $topic->url()->__toString() => FALSE, $topic->mapped( 'title' ) => FALSE ), $topic );

		\IPS\Output::i()->redirect( $topic->url() );
	}
	
	/**
	 * Rate Question
	 *
	 * @return	void
	 */
	public function rateQuestion()
	{
		/* CSRF Check */
		\IPS\Session::i()->csrfCheck();
		
		/* Get the question */
		try
		{
			$question = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/8', 404, '' );
		}
		
		/* Voting up or down? */
		$rating = \intval( \IPS\Request::i()->rating );
		if ( $rating !== 1 and $rating !== -1 )
		{
			\IPS\Output::i()->error( 'form_bad_value', '2F173/A', 403, '' );
		}
		
		/* Check we can cast this vote */
		if ( !$question->canVote( $rating ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/9', 403, '' );
		}
		
		/* If we have an existing vote, remove it first */
		$ratings = $question->votes();
		if ( isset( $ratings[ \IPS\Member::loggedIn()->member_id ] ) )
		{
			\IPS\Db::i()->delete( 'forums_question_ratings', array( 'topic=? AND `member`=?', $question->tid, \IPS\Member::loggedIn()->member_id ) );
		}

		/* Revoting for the same thing you already voted for should remove your vote - so don't insert if we voted for the same thing we did before */
		if ( !isset( $ratings[ \IPS\Member::loggedIn()->member_id ] ) OR $ratings[ \IPS\Member::loggedIn()->member_id ] != $rating )
		{
			\IPS\Db::i()->insert( 'forums_question_ratings', array(
				'topic'		=> $question->tid,
				'forum'		=> $question->forum_id,
				'member'	=> \IPS\Member::loggedIn()->member_id,
				'rating'	=> $rating,
				'date'		=> time()
			), TRUE );
		}
		
		/* Rebuild count */
		$question->question_rating = \IPS\Db::i()->select( 'SUM(rating)', 'forums_question_ratings', array( 'topic=?', $question->tid ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$question->save();
		
		/* Redirect back */
		\IPS\Output::i()->redirect( $question->url() );
	}
	
	/**
	 * Rate Answer
	 *
	 * @return	void
	 */
	public function rateAnswer()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$question = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			$answer = \IPS\forums\Topic\Post::loadAndCheckPerms( \IPS\Request::i()->answer );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/4', 404, '' );
		}
		
		if ( !$answer->item()->can('read') or !$answer->canVote() )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/5', 403, '' );
		}
		
		$rating = \intval( \IPS\Request::i()->rating );
		if ( $rating !== 1 and $rating !== -1 )
		{
			\IPS\Output::i()->error( 'form_bad_value', '2F173/6', 403, '' );
		}

		$ratings = $question->answerVotes( \IPS\Member::loggedIn() );

		/* If we've already rated the answer, remove that first */
		\IPS\Db::i()->delete( 'forums_answer_ratings', array( 'topic=? AND post=? AND `member`=?', $question->tid, $answer->pid, \IPS\Member::loggedIn()->member_id ) );

		/* Revoting for the same thing you already voted for should remove your vote - so don't insert if we voted for the same thing we did before */
		if ( !isset( $ratings[ $answer->pid ] ) OR $ratings[ $answer->pid ] != $rating )
		{
			\IPS\Db::i()->insert( 'forums_answer_ratings', array(
				'post'		=> $answer->pid,
				'topic'		=> $question->tid,
				'member'	=> \IPS\Member::loggedIn()->member_id,
				'rating'	=> $rating,
				'date'		=> time()
			), TRUE );
		}

		$answer->post_field_int = (int) \IPS\Db::i()->select( 'SUM(rating)', 'forums_answer_ratings', array( 'post=?', $answer->pid ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		$answer->save();

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'votes' => $answer->post_field_int, 'canVoteUp' => $answer->canVote(1), 'canVoteDown' => $answer->canVote(-1) ) );
		}
		else
		{
			\IPS\Output::i()->redirect( $answer->url() );
		}
	}
	
	/**
	 * Set Best Answer
	 *
	 * @return	void
	 */
	public function bestAnswer()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( \IPS\Request::i()->answer );
			
			if ( !$topic->canSetBestAnswer() )
			{
				throw new \OutOfRangeException;
			}
			
			if ( $post->item() != $topic )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/7', 404, '' );
		}

		$topic->toggleSolveComment( $post->pid, TRUE );
		
		/* Log */
		if ( \IPS\Member::loggedIn()->modPermission('can_set_best_answer') )
		{
			\IPS\Session::i()->modLog( 'modlog__best_answer_set', array( $post->pid => FALSE ), $topic );
		}
		
		\IPS\Output::i()->redirect( $post->url() );
	}
	
	/**
	 * Unset Best Answer
	 *
	 * @return	void
	 */
	public function unsetBestAnswer()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			$post = \IPS\forums\Topic\Post::loadAndCheckPerms( \IPS\Request::i()->answer );
			
			if ( !$topic->canSetBestAnswer() )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/G', 404, '' );
		}

		if ( $post->item() != $topic )
		{
			throw new \OutOfRangeException;
		}

		try
		{
			$topic->toggleSolveComment( $post->pid, FALSE );
			
			if ( \IPS\Member::loggedIn()->modPermission('can_set_best_answer') )
			{
				\IPS\Session::i()->modLog( 'modlog__best_answer_unset', array( $post->pid => FALSE ), $topic );
			}
		}
		catch ( \Exception $e ) {}
	
		\IPS\Output::i()->redirect( $post->url() );
	}
	
	/**
	 * Saved Action
	 *
	 * @return	void
	 */
	public function savedAction()
	{
		try
		{
			\IPS\Session::i()->csrfCheck();
			
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			$action = \IPS\forums\SavedAction::load( \IPS\Request::i()->action );
			$action->runOn( $topic );
			
			/* Log */
			\IPS\Session::i()->modLog( 'modlog__saved_action', array( 'forums_mmod_' . $action->mm_id => TRUE, $topic->url()->__toString() => FALSE, $topic->mapped( 'title' ) => FALSE ), $topic );
			\IPS\Output::i()->redirect( $topic->url() );
		}
		catch ( \LogicException $e )
		{
			
		}
	}

	/**
	 * Mark Topic Read
	 *
	 * @return	void
	 */
	public function markRead()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$topic = \IPS\forums\Topic::load( \IPS\Request::i()->id );
			$topic->markRead();

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( "OK" );
			}
			else
			{
				\IPS\Output::i()->redirect( $topic->url() );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/C', 403, 'no_module_permission_guest' );
		}
	}
	
	/**
	 * We need to use the custom widget poll template for ajax methods
	 *
	 * @return void
	 */
	public function widgetPoll()
	{
		try
		{
			$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2F173/N', 403, '' );
		}
		
		$poll  = $topic->getPoll();
		$poll->displayTemplate = array( \IPS\Theme::i()->getTemplate( 'widgets', 'forums', 'front' ), 'pollWidget' );
		$poll->url = $topic->url();
		
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'widgets', 'forums', 'front' )->poll( $topic, $poll );
	}

	/**
	 * Show a single comment requested by ajax
	 *
	 * @return	void
	 */
	public function ajaxShowComment()
	{
		try
		{
			if ( ! \IPS\Request::i()->isAjax() )
			{
				throw new \BadMethodCallException();
			}

			\IPS\Session::i()->csrfCheck();

			try
			{
				$topic = \IPS\forums\Topic::loadAndCheckPerms( \IPS\Request::i()->id );
			}
			catch( \OutOfRangeException $ex )
			{
				\IPS\Output::i()->error( 'node_error', '2F173/N', 403, '' );
			}

			$comment = \IPS\forums\Topic\Post::load( \IPS\Request::i()->showComment );

			if ( ! $comment->canView() )
			{
				throw new \BadMethodCallException();
			}

			\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( \IPS\Theme::i()->getTemplate( 'global', 'core' )->commentContainer( $topic, $comment ), 200, 'text/html' ) );
		}
		catch( \Exception $e )
		{
			return '';
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
		/* For normal topics (i.e. not questions), we can handle this normally */
		if ( !$item->isQuestion() )
		{
			return parent::_find( $commentClass, $comment, $item );
		}

		/* Otherwise we need to get the position ordered by votes... */
		if ( $item->isArchived() )
		{
			$where = array( array( 'archive_topic_id=?', $item->tid ) );
			if ( !$item->canViewHiddenComments() )
			{
				$hiddenWhereClause = "(archive_queued != -2 AND archive_queued != -1 AND archive_queued != 1)";

				if ( \IPS\Member::loggedIn()->member_id )
				{
					$where[] = array( "( {$hiddenWhereClause} OR ( archive_queued=1 AND archive_author_id=" . \IPS\Member::loggedIn()->member_id . '))' );
				}
				else
				{
					$where[] = array( $hiddenWhereClause );
				}
			}
			
			/* Connect to the remote DB if needed */
			if ( \IPS\CIC2 )
			{
				$storage = \IPS\Cicloud\getForumArchiveDb();
			}
			else
			{
				$storage = !\IPS\Settings::i()->archive_remote_sql_host ? \IPS\Db::i() : \IPS\Db::i( 'archive', array(
					'sql_host'		=> \IPS\Settings::i()->archive_remote_sql_host,
					'sql_user'		=> \IPS\Settings::i()->archive_remote_sql_user,
					'sql_pass'		=> \IPS\Settings::i()->archive_remote_sql_pass,
					'sql_database'	=> \IPS\Settings::i()->archive_remote_sql_database,
					'sql_port'		=> \IPS\Settings::i()->archive_sql_port,
					'sql_socket'	=> \IPS\Settings::i()->archive_sql_socket,
					'sql_tbl_prefix'=> \IPS\Settings::i()->archive_sql_tbl_prefix,
					'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
				) );
			}
			
			$answers = $storage->select( 'archive_id, @rownum := @rownum + 1 AS position', 'forums_archive_posts', $where, 'archive_is_first DESC, archive_bwoptions DESC, archive_field_int DESC, archive_content_date' )->join( array( '(SELECT @rownum := 0)', 'r' ), NULL, 'JOIN' );
			$commentPosition = $storage->select( 'position', $answers, array( 'archive_id=?', $comment->id ) )->first() - 1;
		}
		else
		{
			$where = array( array( 'topic_id=?', $item->tid ) );
			if ( !$item->canViewHiddenComments() )
			{
				$hiddenWhereClause = "(queued != -2 AND queued != -1 AND queued != 1)";

				if ( \IPS\Member::loggedIn()->member_id )
				{
					$where[] = array( "( {$hiddenWhereClause} OR ( queued=1 AND author_id=" . \IPS\Member::loggedIn()->member_id . '))' );
				}
				else
				{
					$where[] = array( $hiddenWhereClause );
				}
			}

			$answers = \IPS\Db::i()->select( 'pid, @rownum := @rownum + 1 AS position', 'forums_posts', $where, 'new_topic DESC, post_bwoptions DESC, post_field_int DESC, post_date' )->join( array( '(SELECT @rownum := 0)', 'r' ), NULL, 'JOIN' );
			$commentPosition = \IPS\Db::i()->select( 'position', $answers, array( 'pid=?', $comment->pid ) )->first() - 1;
		}

		/* Now work out what page that makes it */
		$url = $item->url();
		$perPage = $item::getCommentsPerPage();
		$page = ceil( $commentPosition / $perPage );
		if ( $page != 1 )
		{
			$url = $url->setPage( 'page', $page );
		}

		/* And redirect */
		$idField = $commentClass::$databaseColumnId;
		\IPS\Output::i()->redirect( $url->setFragment( 'comment-' . $comment->$idField ) );
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
		\IPS\Member::loggedIn()->language()->words['edit_comment']		= \IPS\Member::loggedIn()->language()->addToStack( 'edit_reply', FALSE );

		return parent::_edit( $commentClass, $comment, $item );
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
			$item = $class::load( \IPS\Request::i()->id );
			if ( !$item->canView() )
			{
				$forum = $item->container();
				\IPS\Output::i()->error( $forum ? $forum->errorMessage() : 'node_error_no_perm', '2F173/K', 403, '' );
			}
			
			if ( $item->isArchived() )
			{
				$class::$commentClass = $class::$archiveClass;
			}
			
			return parent::__call( $method, $args );
		}
		catch( \OutOfRangeException $e )
		{
			if ( isset( \IPS\Request::i()->do ) AND \IPS\Request::i()->do === 'findComment' AND isset( \IPS\Request::i()->comment ) )
			{
				try
				{
					$commentClass = $class::$commentClass;
					$comment = $commentClass::load( \IPS\Request::i()->comment );
					$topic   = \IPS\forums\Topic::load( $comment->topic_id );
					
					\IPS\Output::i()->redirect( $topic->url()->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->comment ) ), NULL, 301 );
				}
				catch( \Exception $e )
				{
					\IPS\Output::i()->error( 'node_error', '2F173/M', 404, '' );
				}
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'topic_call' );
			\IPS\Output::i()->error( 'node_error', '2F173/I', 404, '' );
		}
	}
	
	/**
	 * Form for splitting
	 *
	 * @param	\IPS\Content\Item	$item	The item
	 * @return	\IPS\Helpers\Form
	 */
	protected function _splitForm( \IPS\Content\Item $item, $comment = NULL  )
	{
		$form = parent::_splitForm( $item, $comment );

		if ( isset( $form->elements['']['topic_create_state'] ) )
		{
			unset( $form->elements['']['topic_create_state'] );
		}
		
		return $form;
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
		parent::_split( $commentClass, $comment, $item );

		$item->rebuildPopularTime();
	}
}
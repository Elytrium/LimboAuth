<?php
/**
 * @brief		Forum Index
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
 * Forum Index
 */
class _forums extends \IPS\Dispatcher\Controller
{
	protected $themeGroup = NULL;
	
	/**
	 * Route
	 *
	 * @return	void
	 */
	protected function manage()
	{	
		$forum = NULL;
		try
		{
			$this->_forum( \IPS\forums\Forum::loadAndCheckPerms( \IPS\Request::i()->id ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2F176/1', 404, '' );
		}
	}

	protected $childrenIds = [];

	protected function getChildrenIds( $forum )
	{
		$this->childrenIds[] = $forum->_id;

		foreach( $forum->children() as $node )
		{
			$this->childrenIds[] = $node->_id;
			foreach( $node->children() as $child )
			{
				$this->getChildrenIds( $child );
			}
		}

		return $this->childrenIds;
	}

	/**
	 * Show Forum
	 *
	 * @param	\IPS\forums\Forum	$forum	The forum to show
	 * @return	void
	 */
	public function _forum( $forum )
	{
		$forum->clubCheckRules();
				
		/* Is simple mode on? If so, redirect to the index page */
		if ( \IPS\forums\Forum::isSimpleView( $forum ) )
		{
			if ( ! isset( \IPS\Request::i()->url()->hiddenQueryString['rss'] ) )
			{
				\IPS\Output::i()->redirect( $forum->url(), '', 302 );
			}
		}

		/* Theme */
		$forum->setTheme();
		$this->themeGroup = \IPS\Theme::i()->getTemplate( 'forums', 'forums', 'front' );
		
		/* Password protected */
		if ( $form = $forum->passwordForm() )
		{
			\IPS\Output::i()->title = $forum->_title;
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forums', 'forums', 'front' ), 'forumPasswordPopup' ), $forum );
			return;
		}

		/* We can read? */
		if ( $forum->sub_can_post and !$forum->permission_showtopic and !$forum->can('read') )
		{
			\IPS\Output::i()->error( $forum->errorMessage(), '1F176/3', 403, '' );
		}

        /* Users can see topics posted by other users? */
        $where = array();
        if ( !$forum->memberCanAccessOthersTopics( \IPS\Member::loggedIn() ) )
        {
            $where[] = array( 'starter_id = ?', \IPS\Member::loggedIn()->member_id );
        }
		
		$getReactions = $getFirstComment = $getFollowerCount = (boolean) ( \IPS\forums\Forum::getMemberListView() === 'snippet' );
		
		/* Do view update now - we want to include redirect forums */
		if ( !\IPS\Request::i()->isAjax() )
		{
			$forum->updateViews();
		}

		/* Redirect? */
		if ( $forum->redirect_url )
		{
			$forum->redirect_hits++;
			$forum->save();
			\IPS\Output::i()->redirect( $forum->redirect_url );
		}

		/* Display */
		if ( $forum->isCombinedView() )
		{
			$childrenIds = $this->getChildrenIds( $forum );
			$where = array();

			if ( \IPS\forums\Forum::customPermissionNodes() )
			{
				$where['container'][] = array( 'forums_forums.password IS NULL' );
			}

			if ( !\IPS\Settings::i()->club_nodes_in_apps OR !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
			{
				$where['container'][] = array( 'forums_forums.club_id IS NULL' );
			}

			$forumIds = array();
			$ids = $childrenIds;
			$urlParam = 'forumId' . $forum->_id;

			if ( isset( \IPS\Request::i()->$urlParam ) )
			{
				$ids = explode( ',', \IPS\Request::i()->$urlParam );
			}
			else if ( isset( \IPS\Request::i()->cookie['forums_flowIdsRoots'] ) and $cookie = json_decode( \IPS\Request::i()->cookie['forums_flowIdsRoots'], TRUE ) )
			{
				if ( isset( $cookie[ $forum->_id ] ) )
				{
					if ( \is_array( $cookie[ $forum->_id ] ) )
					{
						$ids = $cookie[$forum->_id];
					}
				}
			}

			/* Inline forum IDs? */
			if ( ( \is_array( $ids ) and ! \count( $ids ) ) )
			{
				/* If we unselect all the filters, then there is nothing to show... */
				$where[] = [ "1=2" ];
			}
			else if ( \count( $ids ) )
			{
				foreach( $ids as $id )
				{
					if ( ! \in_array( $id, $childrenIds ) )
					{
						continue;
					}

					try
					{
						/* Panic not, they are all loaded into memory at this point */
						$_forum = \IPS\forums\Forum::load( $id );
						$forumIds[] = $_forum->id;

					}
					catch( \Exception $ex ) { }
				}

				if ( \count( $forumIds ) )
				{
					$where['container'][] = array( \IPS\Db::i()->in( 'forums_forums.id', array_filter( $forumIds ) ) );
				}
			}

			/* Simplified view */
			$table = new \IPS\Helpers\Table\Content( 'IPS\forums\Topic', $forum->url(), $where, $forum, NULL, 'read', TRUE, FALSE, NULL, FALSE, FALSE, FALSE, FALSE );
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'index' ), 'simplifiedForumTable' );
			$table->classes = array( 'cTopicList' );
			$table->limit = \IPS\Settings::i()->forums_topics_per_page;
			$table->enableRealtime = true;

			if ( \IPS\forums\Forum::getMemberListView() == 'snippet' )
			{
				$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'index' ), 'simplifiedTopicRowSnippet' );
			}
			else
			{
				$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'index' ), 'simplifiedTopicRow' );
			}

			$table->honorPinned = \IPS\Settings::i()->forums_fluid_pinned;
			$table->hover = TRUE;
			$table->sortOptions['num_replies']	= $table->sortOptions['num_comments'];
			unset( $table->sortOptions['num_comments'] );

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'index' )->simplifiedView( $table );

			if( \IPS\forums\Forum::theOnlyForum() === NULL )
			{
				\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'index' )->simplifiedViewForumSidebar( $forum );
			}

			if ( isset( $where['container'] ) )
			{
				$allForumIds = iterator_to_array( \IPS\Db::i()->select( 'id', 'forums_forums', $where['container'] ) );
				\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack('forums_chosen_forums') ] = array( 'type' => 'forums_topic', 'nodes' => implode( ',', $allForumIds ) );
			}
		}
		else
		{
			/* Init table (it won't show anything until after the password check, but it sets navigation and titles) */
			$table = new \IPS\Helpers\Table\Content( 'IPS\forums\Topic', $forum->url(), $where, $forum, NULL, 'view', isset( \IPS\Request::i()->rss ) ? FALSE : TRUE, isset( \IPS\Request::i()->rss ) ? FALSE : TRUE, NULL, $getFirstComment, $getFollowerCount, $getReactions );
			$table->tableTemplate = array(\IPS\Theme::i()->getTemplate( 'forums', 'forums', 'front' ), 'forumTable');
			$table->classes = array('cTopicList');
			$table->limit = \IPS\Settings::i()->forums_topics_per_page;
			$table->title = \IPS\Member::loggedIn()->language()->addToStack( ( $forum->forums_bitoptions['bw_enable_answers'] ) ? 'count_questions_in_forum' : 'count_topics_in_forum', FALSE, array('pluralize' => array($forum->topics)) );
			if ( $forum->forums_bitoptions['bw_enable_answers'] )
			{
				$table->rowsTemplate = array($this->themeGroup, 'questionRow');
			}
			else
			{
				if ( \IPS\forums\Forum::getMemberListView() == 'snippet' )
				{
					$table->rowsTemplate = array($this->themeGroup, 'topicRowSnippet');
				}
				else
				{
					$table->rowsTemplate = array($this->themeGroup, 'topicRow');
				}
			}

			\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack( 'search_contextual_item_forums' ) ] = array( 'type' => 'forums_topic', 'nodes' => $forum->_id );
		}

		/* If there's only one forum and we're not in a club, and we're not in a sub-forum, we actually don't want the nav */
		if ( $theOnlyForum = \IPS\forums\Forum::theOnlyForum() AND $theOnlyForum->_id == $forum->_id and !$forum->club() )
		{
			\IPS\Output::i()->breadcrumb = isset( \IPS\Output::i()->breadcrumb['module'] ) ? array('module' => \IPS\Output::i()->breadcrumb['module']) : array();
		}
		
		/* We need to shift the breadcrumb if we are in a sub-forum and we have $theOnlyForum */
		if ( $theOnlyForum AND $theOnlyForum->_id != $forum->_id )
		{
			array_shift( \IPS\Output::i()->breadcrumb );
			array_shift( \IPS\Output::i()->breadcrumb );
		}

		$table->hover = TRUE;
		if ( isset( $table->sortOptions['num_comments'] ) )
		{
			$table->sortOptions['num_replies'] = $table->sortOptions['num_comments'];
			unset( $table->sortOptions['num_comments'] );
		}

		/* Custom Search */
		$filterOptions = array(
			'all' => 'all_topics',
			'open' => 'open_topics',
			'popular' => 'popular_now',
			'poll' => 'poll',
			'locked' => 'locked_topics',
			'moved' => 'moved_topics',
		);
		$timeFrameOptions = array(
			'show_all' => 'show_all',
			'today' => 'today',
			'last_5_days' => 'last_5_days',
			'last_7_days' => 'last_7_days',
			'last_10_days' => 'last_10_days',
			'last_15_days' => 'last_15_days',
			'last_20_days' => 'last_20_days',
			'last_25_days' => 'last_25_days',
			'last_30_days' => 'last_30_days',
			'last_60_days' => 'last_60_days',
			'last_90_days' => 'last_90_days',
		);

		if ( \IPS\Member::loggedIn()->member_id )
		{
			$filterOptions['starter'] = $forum->forums_bitoptions['bw_enable_answers'] ? 'questions_i_asked' : 'topics_i_started';
			$filterOptions['replied'] = $forum->forums_bitoptions['bw_enable_answers'] ? 'questions_i_posted_in' : 'topics_i_posted_in';

			if ( \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->last_visit )
			{
				$timeFrameOptions['since_last_visit'] = \IPS\Member::loggedIn()->language()->addToStack( 'since_last_visit', FALSE, array('sprintf' => array(\IPS\DateTime::ts( \IPS\Member::loggedIn()->last_visit ))) );
			}
		}

		if ( $forum->forums_bitoptions['bw_enable_answers'] )
		{
			$table->filters = array(
				'questions_with_best_answers' => 'topic_answered_pid>0',
				'questions_without_best_answers' => 'topic_answered_pid=0',
			);

			$table->sortOptions['question_rating'] = 'forums_topics.question_rating';
		}
		else
		{
			if ( $forum->forums_bitoptions['bw_enable_answers_moderator'] )
			{
				$table->filters = array(
					'solved_topics' => 'topic_answered_pid>0',
					'unsolved_topics' => 'topic_answered_pid=0',
				);
			}
		}

		/* Are we a moderator? */
		if ( \IPS\forums\Topic::modPermission( 'unhide', NULL, $forum ) )
		{
			$filterOptions['queued_topics'] = 'queued_topics';
			$filterOptions['queued_posts'] = 'queued_posts';

			$table->filters['filter_hidden_topics'] = 'approved=-1';
			$table->filters['filter_hidden_posts_in_topics'] = 'topic_hiddenposts=1';
		}

		/* Are we filtering by queued topics or posts? */
		if ( \IPS\Request::i()->filter == 'queued_topics' or \IPS\Request::i()->filter == 'queued_posts' )
		{
			\IPS\Request::i()->advanced_search_submitted = 1;
			\IPS\Request::i()->csrfKey = \IPS\Session::i()->csrfKey;
			\IPS\Request::i()->topic_type = \IPS\Request::i()->filter;
		}

		$table->advancedSearch = array(
			'topic_type' => array(\IPS\Helpers\Table\SEARCH_SELECT, array('options' => $filterOptions)),
			'sort_by' => array(\IPS\Helpers\Table\SEARCH_SELECT, array('options' => array(
				'last_post' => 'last_post',
				'replies' => 'replies',
				'views' => 'views',
				'topic_title' => 'topic_title',
				'last_poster' => 'last_poster',
				'topic_started' => 'topic_started',
				'topic_starter' => $forum->forums_bitoptions['bw_enable_answers'] ? 'question_asker' : 'topic_starter',
			))
			),
			'sort_direction' => array(\IPS\Helpers\Table\SEARCH_SELECT, array('options' => array(
				'asc' => 'asc',
				'desc' => 'desc',
			))
			),
			'time_frame' => array(\IPS\Helpers\Table\SEARCH_SELECT, array('options' => $timeFrameOptions)),
		);
		$table->advancedSearchCallback = function ( $table, $values ) {
			/* Type */
			switch ( $values['topic_type'] )
			{
				case 'open':
					$table->where[] = array('state=?', 'open');
					break;
				case 'popular':
					$table->where[] = array('popular_time IS NOT NULL AND popular_time>?', time());
					break;
				case 'poll':
					$table->where[] = array('poll_state<>0');
					break;
				case 'locked':
					$table->where[] = array('state=?', 'closed');
					break;
				case 'moved':
					$table->where[] = array('state=?', 'link');
					break;
				case 'starter':
					$table->where[] = array('starter_id=?', \IPS\Member::loggedIn()->member_id);
					break;
				case 'replied':
					$table->joinComments = TRUE;
					$table->where[] = array('forums_posts.author_id=?', \IPS\Member::loggedIn()->member_id);
					break;
				case 'answered':
					$table->where[] = array('topic_answered_pid<>0');
					break;
				case 'unanswered':
					$table->where[] = array('topic_answered_pid=0');
					break;
				case 'queued_topics':
					$table->where[] = array('approved=0');
					break;
				case 'queued_posts':
					$table->where[] = array('topic_queuedposts>0');
					break;
			}

			if ( !isset( $values['sort_by'] ) )
			{
				$values['sort_by'] = 'forums_topics.last_post';
			}

			/* Sort */
			switch ( $values['sort_by'] )
			{
				case 'last_post':
				case 'views':
					$table->sortBy = 'forums_topics.' . $values['sort_by'];
					break;
				case 'replies':
					$table->sortBy = 'posts';
					break;
				case 'topic_title':
				case 'title':
					$table->sortBy = 'title';
					break;
				case 'last_poster':
					$table->sortBy = 'last_poster_name';
					break;
				case 'topic_started':
					$table->sortBy = 'start_date';
					break;
				case 'topic_starter':
					$table->sortBy = 'starter_name';
					break;
			}
			$table->sortDirection = $values['sort_direction'];

			/* Cutoff */
			$days = NULL;

			if ( isset( $values['time_frame'] ) )
			{
				switch ( $values['time_frame'] )
				{
					case 'today':
						$days = 1;
						break;
					case 'last_5_days':
						$days = 5;
						break;
					case 'last_7_days':
						$days = 7;
						break;
					case 'last_10_days':
						$days = 10;
						break;
					case 'last_15_days':
						$days = 15;
						break;
					case 'last_20_days':
						$days = 20;
						break;
					case 'last_25_days':
						$days = 25;
						break;
					case 'last_30_days':
						$days = 30;
						break;
					case 'last_60_days':
						$days = 60;
						break;
					case 'last_90_days':
						$days = 90;
						break;
					case 'since_last_visit':
						$table->where[] = array('forums_topics.last_post>?', \IPS\Member::loggedIn()->last_visit);
						break;
				}
			}

			if ( $days !== NULL )
			{
				$table->where[] = array('forums_topics.last_post>?', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . $days . 'D' ) )->getTimestamp());
			}
		};
		\IPS\Request::i()->sort_direction = \IPS\Request::i()->sort_direction ?: mb_strtolower( $table->sortDirection );

		/* Saved actions */
		foreach ( \IPS\forums\SavedAction::actions( $forum ) as $action )
		{
			$table->savedActions[ $action->_id ] = $action->_title;
		}
		
		/* RSS */
		if ( \IPS\Settings::i()->forums_rss and $forum->topics )
		{
			/* Show the link */
			$rssUrl = \IPS\Http\Url::internal( "app=forums&module=forums&controller=forums&id={$forum->_id}&rss=1", 'front', 'forums_rss', array( $forum->name_seo ) );

			if ( \IPS\Member::loggedIn()->member_id )
			{
				$key = \IPS\Member::loggedIn()->getUniqueMemberHash();

				$rssUrl = $rssUrl->setQueryString( array( 'member' => \IPS\Member::loggedIn()->member_id , 'key' => $key ) );
			}

			if ( $forum->forums_bitoptions['bw_enable_answers'] )
			{
				$rssTitle = \IPS\Member::loggedIn()->language()->addToStack( 'forum_rss_title_questions', FALSE, array( 'escape' => true, 'sprintf' => array( $forum->_title ) ) );
			}
			else
			{
				$rssTitle = \IPS\Member::loggedIn()->language()->addToStack( 'forum_rss_title_topics', FALSE, array( 'escape' => true, 'sprintf' => array( $forum->_title ) ) );
			}
			\IPS\Output::i()->rssFeeds[ $rssTitle ] = $rssUrl;
		}

		/* Online User Location */
		$permissions = $forum->permissions();
		\IPS\Session::i()->setLocation( $forum->url(), explode( ",", $permissions['perm_view'] ), 'loc_forums_viewing_forum', array( "forums_forum_{$forum->id}" => TRUE ) );
		
		if ( \IPS\forums\Forum::getMemberView() === 'grid' )
		{
			\IPS\forums\Forum::populateFollowerCounts( $forum );
		}

		/* Data Layer Context */
		if ( !\IPS\Request::i()->isAjax AND \IPS\Settings::i()->core_datalayer_enabled )
		{
			foreach ( $forum->getDataLayerProperties() as $key => $value )
			{
				\IPS\core\DataLayer::i()->addContextProperty( $key, $value );
				\IPS\core\DataLayer::i()->addContextProperty( 'sort_direction', \IPS\Request::i()->sort_direction ?: null );
				$sortby = \IPS\Request::i()->sort_by ?: null;
				if ( $sortby AND !\in_array( $sortby, ['asc', 'desc'] ) )
				{
					$sortby = null;
				}
				\IPS\core\DataLayer::i()->addContextProperty( 'sort_by', $sortby );
				\IPS\core\DataLayer::i()->addContextProperty( 'page_number', 'page' );
			}
		}
			
		/* Show Forum */
		if ( isset( \IPS\Request::i()->advancedSearchForm ) )
		{
			\IPS\Output::i()->output = (string) $table;
			return;
		}

		$forumOutput = '';

		if ( ! $forum->isCombinedView() and $forum->forums_bitoptions['bw_enable_answers'] )
		{	
			$featuredTopic = NULL;

			foreach ( \IPS\forums\Topic::featured( 1, 'RAND()', $forum ) as $featuredTopic )
			{
				break;
			}
			
			$popularQuestions = \IPS\forums\Topic::getItemsWithPermission( array( array( 'forum_id=?', $forum->id ), array( 'start_date>?', \IPS\DateTime::ts( time() - ( 86400 * 30 ) )->getTimestamp() ), array( 'question_rating>0' ) ), 'question_rating DESC, views DESC', 5 );
			$newQuestionsWhere = array( array( 'forum_id=?', $forum->id ) );

			if ( !\IPS\Settings::i()->forums_new_questions )
			{
				$newQuestionsWhere[] = array( 'topic_answered_pid=0' );
			}
			else
			{
				$newQuestionsWhere[] = array( '( forums_topics.posts IS NULL OR forums_topics.posts=1 )' );
			}
			
			$newQuestions = \IPS\forums\Topic::getItemsWithPermission( $newQuestionsWhere, 'start_date DESC', 5 );			
			$forumOutput = \IPS\Theme::i()->getTemplate( 'forums' )->qaForum( (string) $table, $popularQuestions, $newQuestions, $featuredTopic, $forum );
		}
		else if( $forum->sub_can_post or $forum->isCombinedView() )
		{
			$forumOutput = (string) $table;
		}

		$table = $this->postProcessTable( $table );
		
		/* Set default search to this forum */
		\IPS\Output::i()->defaultSearchOption = array( 'forums_topic', 'forums_topic_el' );

		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_forum.js', 'forums' ) );
		\IPS\Output::i()->output	= $this->themeGroup->forumDisplay( $forum, $forumOutput );
	}

	/**
	 * Return the forum table
	 * 
	 * @return \IPS\Helpers\Table\Content
	 */
	protected function postProcessTable( $table ) {
		return $table;
	}
	
	/**
	 * Show Club Forums
	 *
	 * @return	void
	 */
	public function clubs()
	{
		if ( !\IPS\Settings::i()->club_nodes_in_apps )
		{
			\IPS\Output::i()->error( 'node_error', '2F176/4', 404, '' );
		}

		if ( \IPS\forums\Forum::isSimpleView() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index&forumId=clubs', 'front', 'forums' ), '', 302 );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('club_node_forums') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('club_node_forums');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forums' )->clubForums();
	}

	/**
	 * Add Topic
	 *
	 * @return	void
	 */
	protected function add()
	{
		if ( !isset( \IPS\Request::i()->id ) )
		{
			$this->_selectForum();
			return;
		}

		try
		{
			$forum = \IPS\forums\Forum::loadAndCheckPerms( \IPS\Request::i()->id );
			$forum->setTheme();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/2', 403, 'no_module_permission_guest' );
		}
		
		if ( $forum->forums_bitoptions['bw_enable_answers'] )
		{
			\IPS\Member::loggedIn()->language()->words['topic_mainTab'] = \IPS\Member::loggedIn()->language()->addToStack( 'question_mainTab', FALSE );
		}
		
		$form = \IPS\forums\Topic::create( $forum );

		$hasModOptions = false;
		
		$canHide = ( \IPS\Member::loggedIn()->group['g_hide_own_posts'] == '1' or \in_array( 'IPS\forums\Topics', explode( ',', \IPS\Member::loggedIn()->group['g_hide_own_posts'] ) ) );
		if ( \IPS\forums\Topic::modPermission( 'lock', NULL, $forum ) or
			 \IPS\forums\Topic::modPermission( 'pin', NULL, $forum ) or
			 \IPS\forums\Topic::modPermission( 'hide', NULL, $forum ) or
			 $canHide or 
			 \IPS\forums\Topic::modPermission( 'feature', NULL, $forum ) )
		{
			$hasModOptions = TRUE;
		}
		
		$formTemplate = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'forums' ), 'createTopicForm' ), $forum, $hasModOptions, NULL );
		
		$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? !$forum->can( 'add', \IPS\Member::loggedIn(), FALSE ) : NULL;
		$modQueued = \IPS\forums\Topic::moderateNewItems( \IPS\Member::loggedIn(), $forum, $guestPostBeforeRegister );
		if ( $guestPostBeforeRegister or $modQueued )
		{
			$formTemplate = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( $guestPostBeforeRegister, $modQueued, TRUE ) . $formTemplate;
		}

		$title = $forum->forums_bitoptions['bw_enable_answers'] ? 'ask_new_question' : 'create_new_topic';

		/* Online User Location */
		$permissions = $forum->permissions();
		\IPS\Session::i()->setLocation( $forum->url(), explode( ",", $permissions['perm_view'] ), 'loc_forums_creating_topic', array( "forums_forum_{$forum->id}" => TRUE ) );
		
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->linkTags['canonical'] = (string) $forum->url()->setQueryString( 'do', 'add' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->createTopic( $formTemplate, $forum, $title );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( $title );
		
		if ( $club = $forum->club() )
		{
			\IPS\core\FrontNavigation::$clubTabActive = TRUE;
			\IPS\Output::i()->breadcrumb = array();
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
			\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
			\IPS\Output::i()->breadcrumb[] = array( $forum->url(), $forum->_title );
			
			if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
			{
				\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $forum, 'sidebar' );
			}
		}
		elseif ( !\IPS\forums\Forum::theOnlyForum() )
		{
			try
			{
				foreach( $forum->parents() as $parent )
				{
					\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
				}
			}
			catch( \UnderflowException $e ) {}
			\IPS\Output::i()->breadcrumb[] = array( $forum->url(), $forum->_title );
		}
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( ( $forum->forums_bitoptions['bw_enable_answers'] ) ? 'ask_new_question' : 'create_new_topic' ) );
	}
	
	/**
	 * Create Category Selector
	 *
	 * @return	void
	 */
	protected function createMenu()
	{
		$this->_selectForum();
	}
	
	/**
	 * Mark Read
	 *
	 * @return	void
	 */
	protected function markRead()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$forum		= \IPS\forums\Forum::load( \IPS\Request::i()->id );
			$returnTo	= $forum;

			if( \IPS\Request::i()->return )
			{
				$returnTo	= \IPS\forums\Forum::load( \IPS\Request::i()->return );
			}
			
			if ( \IPS\Request::i()->fromForum )
			{
				\IPS\forums\Topic::markContainerRead( $forum, NULL, FALSE );
			}
			else
			{
				\IPS\forums\Topic::markContainerRead( $forum );
			}

			\IPS\Output::i()->redirect( ( \IPS\Request::i()->return OR \IPS\Request::i()->fromForum ) ? $returnTo->url() : \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', NULL, 'forums' ) );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F173/3', 403, 'no_module_permission_guest' );
		}
	}
	
	/**
	 * Set the viewing method
	 *
	 * @return	void
	 */
	protected function setMethod()
	{
		\IPS\Session::i()->csrfCheck();
		
		$method = ( isset( \IPS\Request::i()->method ) ) ? \IPS\Request::i()->method : \IPS\Settings::i()->forums_default_view;
		
		\IPS\Request::i()->setCookie( 'forum_list_view', $method, ( new \IPS\DateTime )->add( new \DateInterval( 'P1Y' ) ) );
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Db::i()->replace( 'forums_view_method', array( 'member_id' => \IPS\Member::loggedIn()->member_id, 'method' => $method, 'type' => 'list' ) );
		}

		if ( ! \IPS\Request::i()->id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' ) );
		}
		\IPS\Output::i()->redirect( \IPS\forums\Forum::load( \IPS\Request::i()->id )->url() );
	}

	/**
	 * Populate the combined fluid view start modal form elements
	 * @param $nodes
	 * @param $disabled
	 * @param $node
	 * @param int $depth
	 */
	protected function _selectForumPopulate( &$nodes, &$disabled, $node, $depth = 0 )
	{
		if ( $node->can('view') )
		{
			$nodes[ $node->_id ] = str_repeat( '- ', $depth ) . $node->_title;

			if ( ! $node->can('add') )
			{
				$disabled[] = $node->_id;
			}

			foreach( $node->children() AS $child )
			{
				$this->_selectForumPopulate( $nodes, $disabled, $child, $depth + 1 );
			}
		}
	}

	/**
	 * Shows the forum selector for creating a topic outside of specific forum
	 *
	 * @return	void
	 */
	protected function _selectForum()
	{
		if( !\IPS\forums\Forum::canOnAny( 'add' ) )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2F176/5', 403, 'no_module_permission_guest' );
		}

		$form = new \IPS\Helpers\Form( 'select_forum', 'continue' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';

		if ( isset( \IPS\Request::i()->root ) )
		{
			$root = \IPS\forums\Forum::load( \IPS\Request::i()->root );
			$options = [];
			$disabled = [];
			$this->_selectForumPopulate( $options, $disabled, $root );

			$form->add( new \IPS\Helpers\Form\Select( 'forum', $root->id, TRUE, [
				'options' => $options,
				'disabled' => $disabled
			] ) );
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\Node( 'forum', NULL, TRUE, array(
				'url' => \IPS\Http\Url::internal( 'app=forums&module=forums&controller=forums&do=createMenu' ),
				'class' => 'IPS\forums\Forum',
				'permissionCheck' => function ( $node ) {
					if ( $node->can( 'view' ) )
					{
						if ( $node->can( 'add' ) )
						{
							return TRUE;
						}

						return FALSE;
					}

					return NULL;
				},
				'clubs' => \IPS\Settings::i()->club_nodes_in_apps
			) ) );
		}
		if ( $values = $form->values() )
		{
			if ( \is_numeric( $values['forum'] ) )
			{
				$forum = \IPS\forums\Forum::load( $values['forum'] );
			}
			else
			{
				$forum = $values['forum'];
			}

			\IPS\Output::i()->redirect( $forum->url()->setQueryString( 'do', 'add' ) );
		}
		
		\IPS\Output::i()->title			= \IPS\Member::loggedIn()->language()->addToStack( 'select_forum' );
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'select_forum' ) );
		\IPS\Output::i()->output		= \IPS\Theme::i()->getTemplate( 'forums' )->forumSelector( $form );
	}
}

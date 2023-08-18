<?php
/**
 * @brief		index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		28 Jul 2014
 */

namespace IPS\forums\modules\front\forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * index
 */
class _index extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( isset( \IPS\Request::i()->forumId ) and ! \IPS\forums\Forum::isSimpleView() )
		{
			/* This is a simple view URL, but we're not using simple view, so redirect */
			$ids = explode( ',', \IPS\Request::i()->forumId );
			$firstId = array_shift( $ids );
			if ( $firstId )
			{
				try
				{
					$forum = \IPS\forums\Forum::loadAndCheckPerms( $firstId );
					\IPS\Output::i()->redirect( $forum->url(), '', 302 );
				}
				catch( \Exception $ex ) { }
			}
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_browse.js', 'gallery' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_forum.js', 'forums' ) );
		
		parent::execute();
	}
	
	/**
	 * Handle forum things that are directed here when simple view is on
	 *
	 * @param	string	$method	Desired method
	 * @param	array	$args	Arguments
	 * @return void
	 */
	public function __call( $method, $args )
	{
		if ( \IPS\forums\Forum::isSimpleView() and isset( \IPS\Request::i()->do ) and isset( \IPS\Request::i()->forumId ) and ! mb_stristr( \IPS\Request::i()->forumId, ',' ) )
		{
			/* If we have a specific do action that this controller does not handle, then we really want the full forum view to handle it */
			try
			{
				/* Panic not, they are all loaded into memory at this point */
				$controller = new \IPS\forums\modules\front\forums\forums( $this->url );
				\IPS\Request::i()->id = \IPS\Request::i()->forumId;
				$controller->execute();
				
			}
			catch( \Exception $ex ) { }
		}
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Load into memory */
		\IPS\forums\Forum::loadIntoMemory();
			
		/* Is there only one forum? But don't redirect if it's simple view mode... */
		if ( $theOnlyForum = \IPS\forums\Forum::theOnlyForum() AND !\IPS\forums\Forum::isSimpleView() )
		{
			$controller = new \IPS\forums\modules\front\forums\forums( $this->url );
			return $controller->_forum( $theOnlyForum );
		}
		
		/* Prepare output */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );
		if ( \IPS\forums\Forum::isSimpleView() )
		{
			\IPS\Output::i()->title = ( isset( \IPS\Request::i()->page ) AND \IPS\Request::i()->page > 1 ) ? \IPS\Member::loggedIn()->language()->addToStack( 'title_with_page_number', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( 'forums' ), \IPS\Request::i()->page ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'forums' );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'forums' );
		}
		\IPS\Output::i()->linkTags['canonical'] = (string) \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' );
		\IPS\Output::i()->metaTags['og:title'] = \IPS\Settings::i()->board_name;
		\IPS\Output::i()->metaTags['og:type'] = 'website';
		\IPS\Output::i()->metaTags['og:url'] = (string) \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' );
		
		/* Set Online Location */
		$permissions = \IPS\Dispatcher::i()->module->permissions();
		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' ), explode( ',', $permissions['perm_view'] ), 'loc_forums_index' );
		
		/* Display */
		if ( \IPS\forums\Forum::isSimpleView() )
		{
			$where = array();
			
			if ( \IPS\forums\Forum::customPermissionNodes() )
			{
				$where = array( 'container' => array( array( 'forums_forums.password IS NULL' ) ) );
			}
			
			if ( !\IPS\Settings::i()->club_nodes_in_apps OR !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
			{
				$where['container'][] = array( 'forums_forums.club_id IS NULL' );
			}
			$forumIds = array();
			$map = array();
			$ids = array();
			
			if ( isset( \IPS\Request::i()->forumId ) )
			{
				$ids = explode( ',', \IPS\Request::i()->forumId );
			}
			else if ( isset( \IPS\Request::i()->cookie['forums_flowIds'] ) )
			{
				$ids = explode( ',', \IPS\Request::i()->cookie['forums_flowIds'] );
			}

			/* Inline forum IDs? */
			if ( \count( $ids ) )
			{
				foreach( $ids as $id )
				{
					try
					{
						if ( $id == 'clubs' )
						{
							$map['clubs'] = 'clubs';
							foreach ( \IPS\forums\Forum::clubNodes() as $child )
							{
								$forumIds[ $child->id ] = $child->id;
							}
						}
						else
						{						
							/* Panic not, they are all loaded into memory at this point */
							$forum = \IPS\forums\Forum::load( $id );
	
							$map[ $forum->parent_id ][] = $forum->_id;
							$forumIds[] = $forum->id;
						}
					}
					catch( \Exception $ex ) { }
				}

				if ( \count( $forumIds ) )
				{
					$where['container'][] = array( \IPS\Db::i()->in( 'forums_forums.id', array_filter( $forumIds ) ) );
				}
			}
						
			/* Simplified view */
			$table = new \IPS\Helpers\Table\Content( 'IPS\forums\Topic', \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' ), $where, NULL, NULL, 'read' );
			$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'index' ), 'simplifiedForumTable' );
			$table->classes = array( 'cTopicList' );
			$table->limit = \IPS\Settings::i()->forums_topics_per_page;

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
				\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'index' )->simplifiedViewSidebar( $forumIds, $map );
			}

			if ( isset( $where['container'] ) )
			{
				$allForumIds = iterator_to_array( \IPS\Db::i()->select( 'id', 'forums_forums', $where['container'] ) );
				\IPS\Output::i()->contextualSearchOptions[ \IPS\Member::loggedIn()->language()->addToStack('forums_chosen_forums') ] = array( 'type' => 'forums_topic', 'nodes' => implode( ',', $allForumIds ) );
			}					
		}
		else
		{
			/* Merge in follower counts to the immediately visible forums */
			if ( \IPS\forums\Forum::getMemberView() === 'grid' )
			{
				\IPS\forums\Forum::populateFollowerCounts();
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'index' )->index();
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
		
		\IPS\Request::i()->setCookie( 'forum_view', $method, ( new \IPS\DateTime )->add( new \DateInterval( 'P1Y' ) ) );
		
		if ( \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Db::i()->replace( 'forums_view_method', array( 'member_id' => \IPS\Member::loggedIn()->member_id, 'method' => $method, 'type' => 'index' ) );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=forums&module=forums&controller=index', 'front', 'forums' ) );
	}
}
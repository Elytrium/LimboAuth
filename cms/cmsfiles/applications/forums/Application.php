<?php
/**
 * @brief		Forums Application Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	Forums
 * @since		07 Jan 2014
 * @version		
 */
 
namespace IPS\forums;

/**
 * Forums Application Class
 */
class _Application extends \IPS\Application
{

	/**
	 * Init
	 *
	 * @return	void
	 */
	public function init()
	{
		/* Handle RSS requests */
		if ( \IPS\Request::i()->module == 'forums' and \IPS\Request::i()->controller == 'forums' and isset( \IPS\Request::i()->rss ) )
		{
			$member = NULL;
			if( \IPS\Request::i()->member AND \IPS\Request::i()->key )
			{
				$member = \IPS\Member::load( \IPS\Request::i()->member );
				if( !\IPS\Login::compareHashes( $member->getUniqueMemberHash(), (string) \IPS\Request::i()->key ) )
				{
					$member = NULL;
				}
			}

			$this->sendForumRss( $member ?? new \IPS\Member );

			if( !\IPS\Member::loggedIn()->group['g_view_board'] )
			{
				\IPS\Output::i()->error( 'node_error', '2F219/1', 404, '' );
			}
		}
	}

	/**
	 * Send the forum's RSS feed for the indicated member
	 *
	 * @param	\IPS\Member		$member		Member
	 * @return	void
	 */
	protected function sendForumRss( $member )
	{
		try
		{
			$forum = \IPS\forums\Forum::load( \IPS\Request::i()->id );

			if( !$forum->can( 'view', $member ) )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			/* We'll let the regular controller handle the error */
			return;
		}

		/* RSS feeds are not available if the setting is off, or there is nothing to include */
		if ( !\IPS\Settings::i()->forums_rss OR !$forum->topics )
		{
			return;
		}

        /* Users can see topics posted by other users? Also, exclude moved links. */
        $where = array( array( 'forums_topics.moved_to IS NULL' ), array( 'forums_topics.forum_id=?', $forum->id ) );
        if ( !$forum->memberCanAccessOthersTopics( $member ) )
        {
            $where[] = array( 'starter_id = ?', $member->member_id );
        }

		/* Init table (it won't show anything until after the password check, but it sets navigation and titles) */
		$iterator = \IPS\forums\Topic::getItemsWithPermission( $where, 'forums_topics.last_post DESC', \IPS\Settings::i()->forums_topics_per_page, 'view', \IPS\Content\Hideable::FILTER_PUBLIC_ONLY, 0, $member, FALSE, FALSE, FALSE, FALSE, NULL, $forum, TRUE, TRUE, TRUE, FALSE );

		/* Set the title, either topics or questions */
		if ( $forum->forums_bitoptions['bw_enable_answers'] )
		{
			$rssTitle = sprintf( $member->language()->get( 'forum_rss_title_questions'), $member->language()->get( "forums_forum_{$forum->id}" ) );
		}
		else
		{
			$rssTitle = sprintf( $member->language()->get( 'forum_rss_title_topics'), $member->language()->get( "forums_forum_{$forum->id}" ) );
		}

		/* Can we view the content (permission_showtopic may allow us to view the list, but not the content)? */
		$canViewContent = $forum->can('read', $member );

		/* Build the document */
		$document = \IPS\Xml\Rss::newDocument( $forum->url(), $rssTitle, $rssTitle );
		foreach ( $iterator as $topic )
		{
			try
			{
				$document->addItem( $topic->title, $topic->url(), $canViewContent ? $topic->content() : NULL, \IPS\DateTime::ts( $topic->start_date ), $topic->tid );
			}
			catch ( \Exception $e ) { }
		}
		
		/* Display - note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
		\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml', array(), TRUE );
	}
	
	/**
	 * Archive Query
	 *
	 * @param	array	$rules	Rules
	 * @return	array
	 */
	public static function archiveWhere( $rules )
	{
		$where = array();
		
		if ( \IPS\CIC )
		{
			return \IPS\Cicloud\getForumArchiveWhere();
		}

		foreach ( $rules as $rule )
		{
			$clause = NULL;
			
			switch ( $rule['archive_field'] )
			{
				case 'lastpost':
					/* If the data is bad, log and don't throw an error, but don't allow anything to be archived. */
					if( !$rule['archive_text'] OR !$rule['archive_unit'] )
					{
						\IPS\Log::log( 'Forum archiving missing time period or archive unit', 'forum_archive' );
						$clause = array( '0=?', '1' );
					}
					else
					{
						$clause = array( '(last_post > 0 AND last_post ' . $rule['archive_value'] . ' ?)', \IPS\DateTime::create()->sub( new \DateInterval( 'P' . trim( $rule['archive_text'] ) . mb_strtoupper( $rule['archive_unit'] ) ) )->getTimestamp() );
					}
					break;
				
				case 'forum':
					if ( $rule['archive_text'] )
					{
						$clause = array( 'forum_id ' . ( $rule['archive_value'] == '+' ? 'IN' : 'NOT IN' ) . '(' . $rule['archive_text'] . ')' );
					}
					break;
					
				case 'pinned':
				case 'featured':
				case 'state':
				case 'approved':
					$clause = array( $rule['archive_field'] . '=?', $rule['archive_value'] );
					break;
				
				case 'poll':
					if ( $rule['archive_value'] )
					{
						$clause = array( 'poll_state>0' );
					}
					else
					{
						$clause = array( '(poll_state=0 or poll_state IS NULL)' );
					}
					break;
					
				case 'post':
				case 'view':
					$clause = array( $rule['archive_field'] . 's' . $rule['archive_value'] . '?', $rule['archive_text'] );
					break;
				
				case 'rating':
					$clause = array( 'ROUND(topic_rating_total/topic_rating_hits)' . $rule['archive_value'] . '?', $rule['archive_text'] );
					break;
				
				case 'member':
					$clause = array( 'starter_id ' . ( $rule['archive_value'] == '+' ? 'IN' : 'NOT IN' ) . '(' . $rule['archive_text'] . ')' );
					break;
				
			}
			
			if ( $clause )
			{
				if ( $rule['archive_skip'] )
				{
					$clause[0] = ( '!(' . $clause[0] . ')' );
					$where[] = $clause;
				}
				else
				{
					$where[] = $clause;
				}
			}
		}
		
		return $where;
	}

	/**
	 * [Node] Get Icon for tree
	 *
	 * @note	Return the class for the icon (e.g. 'globe')
	 * @return	string|null
	 */
	protected function get__icon()
	{
		return 'comments';
	}
	
	/**
	 * Install 'other' items.
	 *
	 * @return void
	 */
	public function installOther()
	{
		\IPS\Content\Search\Index::i()->index( \IPS\forums\Topic::load( 1 ) );
		\IPS\Content\Search\Index::i()->index( \IPS\forums\Topic\Post::load( 1 ) );
	}
	
	/**
	 * Default front navigation
	 *
	 * @code
	 	
	 	// Each item...
	 	array(
			'key'		=> 'Example',		// The extension key
			'app'		=> 'core',			// [Optional] The extension application. If ommitted, uses this application	
			'config'	=> array(...),		// [Optional] The configuration for the menu item
			'title'		=> 'SomeLangKey',	// [Optional] If provided, the value of this language key will be copied to menu_item_X
			'children'	=> array(...),		// [Optional] Array of child menu items for this item. Each has the same format.
		)
	 	
	 	return array(
		 	'rootTabs' 		=> array(), // These go in the top row
		 	'browseTabs'	=> array(),	// These go under the Browse tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'browseTabsEnd'	=> array(),	// These go under the Browse tab after all other items on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Browse tab may not exist)
		 	'activityTabs'	=> array(),	// These go under the Activity tab on a new install or when restoring the default configuraiton; or in the top row if installing the app later (when the Activity tab may not exist)
		)
	 * @endcode
	 * @return array
	 */
	public function defaultFrontNavigation()
	{
		return array(
			'rootTabs'		=> array(),
			'browseTabs'	=> array( array( 'key' => 'Forums' ) ),
			'browseTabsEnd'	=> array(),
			'activityTabs'	=> array()
		);
	}

	/**
	 * Perform some legacy URL parameter conversions
	 *
	 * @return	void
	 */
	public function convertLegacyParameters()
	{
		/* Convert &showtopic= (link) */
		if ( isset( \IPS\Request::i()->showtopic ) and \is_numeric( \IPS\Request::i()->showtopic ) )
		{
			$base        = NULL;
			$seoTemplate = NULL;
			$seoTitles   = array();

			try
			{
				$topic = \IPS\forums\Topic::load( \IPS\Request::i()->showtopic );

				if ( $topic->canView() )
				{
					$base        = 'front';
					$seoTemplate = 'forums_topic';
					$seoTitles   = array( $topic->title_seo );
				}
			} catch( \Exception $e ) {}

			$url = \IPS\Http\Url::internal( 'app=forums&module=forums&controller=topic&id=' . \IPS\Request::i()->showtopic, $base, $seoTemplate, $seoTitles );

			if ( isset( \IPS\Request::i()->p ) or isset( \IPS\Request::i()->findpost ) or isset( \IPS\Request::i()->comment ) )
			{
				$url = $url->setQueryString( array( 'do' => 'findComment', 'comment' => \IPS\Request::i()->p ?: ( \IPS\Request::i()->findpost ?: \IPS\Request::i()->comment ) ) );
			}
			elseif ( isset( \IPS\Request::i()->page ) )
			{
				$url = $url->setPage( 'page', \IPS\Request::i()->page );
			}
			\IPS\Output::i()->redirect( $url );
		}

		/* Convert &showforum= */
		if ( isset( \IPS\Request::i()->showforum ) and \is_numeric( \IPS\Request::i()->showforum ) )
		{
			$base        = NULL;
			$seoTemplate = NULL;
			$seoTitles   = array();

			try
			{
				$forum = \IPS\forums\Forum::load( \IPS\Request::i()->showforum );

				if ( $forum->can( 'view' ) )
				{
					$base        = 'front';
					$seoTemplate = 'forums_forum';
					$seoTitles   = array( $forum->name_seo );
				}
			} catch ( \Exception $e ) {}

			$url = \IPS\Http\Url::internal( 'app=forums&module=forums&controller=forums&id=' . \IPS\Request::i()->showforum, $base, $seoTemplate, $seoTitles );
			\IPS\Output::i()->redirect( $url );
		}
		
		/* Convert /topic/123-example/&p= */
		if ( isset( \IPS\Request::i()->p ) AND \is_numeric( \IPS\Request::i()->p ) AND ( \IPS\Request::i()->url() instanceof \IPS\Http\Url\Friendly ) AND \IPS\Request::i()->url()->seoTemplate == 'forums_topic' )
		{
			/* We do this a little differently as the topic seo title is already known at this point */
			try
			{
				$post = \IPS\forums\Topic\Post::loadAndCheckPerms( \IPS\Request::i()->p );
				\IPS\Output::i()->redirect( $post->url() );
			}
			catch( \Exception $e ) {}
		}
	}

	/**
	 * Returns a list of essential cookies which are set by this app.
	 * Wildcards (*) can be used at the end of cookie names for PHP set cookies.
	 *
	 * @return string[]
	 */
	public function _getEssentialCookieNames(): array
	{
		return [ 'forumpass_*' ];
	}
}
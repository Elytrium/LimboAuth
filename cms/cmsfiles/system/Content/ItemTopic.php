<?php
/**
 * @brief		ItemTopic Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Jun 2018
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * ItemTopic Trait
 */
trait ItemTopic
{

	/**
	 * Get the topic title
	 *
	 * @return string
	 */
	abstract function getTopicTitle();

	/**
	 * Get the topic content
	 *
	 * @return mixed
	 */
	abstract function getTopicContent();

	/**
	 * Get the database column which stores the topic ID
	 *
	 * @return	string
	 */
	public static function topicIdColumn()
	{
		return 'topicid';
	}

	/**
	 * Get the database column which stores the forum ID
	 *
	 * @return	string
	 */
	public static function containerForumIdColumn()
	{
		return 'forum_id';
	}

	/**
	 * Create/Update Topic
	 *
	 * @return	void
	 */
	public function syncTopic()
	{
		$column = static::topicIdColumn();
		$containercolumn = static::containerForumIdColumn();

		/* Existing topic */
		if ( $this->$column )
		{
			/* Get */
			try
			{
				$topic = \IPS\forums\Topic::load( $this->$column );
				if ( !$topic )
				{
					return;
				}
				$title = $this->getTopicTitle();
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $title );
				$topic->title = $title;
				if ( \IPS\Settings::i()->tags_enabled )
				{
					$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
				}
				$topic->save();
				$firstPost = $topic->comments( 1 );

				/* If the first post of the topic is missing, NULL will be returned */
				if( $firstPost === NULL )
				{
					throw new \OutOfRangeException;
				}

				$content = $this->getTopicContent();
				\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );
				$firstPost->post = $content;
				$firstPost->save();
				\IPS\Content\Search\Index::i()->index( $firstPost );
			}
			catch ( \OutOfRangeException $e )
			{
				return;
			}
		}
		/* New topic */
		else
		{
			/* Create topic */
			try
			{
				$forum = \IPS\forums\Forum::load( $this->container()->$containercolumn );
			}
			catch( \OutOfRangeException $e )
			{
				return;
			}

			$topic = \IPS\forums\Topic::createItem( $this->author(), $this->mapped('ip_address'), \IPS\DateTime::ts( $this->mapped('date') ), $forum, $this->hidden() );
			$title = $this->getTopicTitle();
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $title );
			$topic->title = $title;
			$topic->topic_archive_status = \IPS\forums\Topic::ARCHIVE_EXCLUDE;		
			$topic->save();
			
			if( $this->isAnonymous() )
			{
				$topic->setAnonymous( TRUE, $this->author );
			}
			
			$topic->markRead( $this->author() );
			if ( \IPS\Settings::i()->tags_enabled )
			{
				$topic->setTags( $this->prefix() ? array_merge( $this->tags(), array( 'prefix' => $this->prefix() ) ) : $this->tags() );
			}

			/* Create post */
			$content = $this->getTopicContent();
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $content );
			$post = \IPS\forums\Topic\Post::create( $topic, $content, TRUE, NULL, NULL, $this->author() );
			
			if( $this->isAnonymous() )
			{
				$post->setAnonymous( TRUE, $this->author );
			}
			
			$topic->topic_firstpost = $post->pid;
			$topic->save();
			\IPS\Content\Search\Index::i()->index( $post );

			/* Send notifications */
			if ( !$topic->isFutureDate() AND !$topic->hidden() )
			{
				$topic->sendNotifications();
			}

			/* Update file */
			$this->$column = $topic->tid;
			$this->save();
		}
	}

	/**
	 * Get Topic (checks member's permissions)
	 *
	 * @param	bool	$checkPerms		Should check if the member can read the topic?
	 * @return	\IPS\forums\Topic|NULL
	 */
	public function topic( $checkPerms=TRUE )
	{
		$column = static::topicIdColumn();

		if ( \IPS\Application::appIsEnabled('forums') and $this->$column )
		{
			try
			{
				return $checkPerms ? \IPS\forums\Topic::loadAndCheckPerms( $this->$column ) : \IPS\forums\Topic::load( $this->$column );
			}
			catch ( \OutOfRangeException $e )
			{
				return NULL;
			}
		}

		return NULL;
	}

	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @param	bool		$log		If TRUE, action will be logged to moderator log
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor, $log=TRUE )
	{
		if ( \IPS\Application::appIsEnabled( 'forums' ) )
		{
			if ( $topic = $this->topic() )
			{
				$topic->changeAuthor( $newAuthor, $log );
			}
		}

		return parent::changeAuthor( $newAuthor, $log );
	}

	/**
	 * Process after the object has been edited on the front-end
	 *
	 * @param	array	$values		Values from form
	 * @return	void
	 */
	public function processAfterEdit( $values )
	{
		if ( \IPS\Application::appIsEnabled('forums') and $this->topic() )
		{
			$this->syncTopic();
		}

		parent::processAfterEdit( $values );
	}

	/**
	 * Callback to execute when tags are edited
	 *
	 * @return	void
	 */
	protected function processAfterTagUpdate()
	{
		parent::processAfterTagUpdate();

		if ( \IPS\Application::appIsEnabled('forums') and $this->topic() )
		{
			$this->syncTopic();
		}
	}

	/**
	 * Syncing to run when hiding
	 *
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onHide( $member )
	{
		parent::onHide( $member );
		if ( \IPS\Application::appIsEnabled('forums') and $topic = $this->topic() )
		{
			$topic->hide( $member );
		}
	}

	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool					$approving	If true, is being approved for the first time
	 * @param	\IPS\Member|NULL|FALSE	$member	The member doing the action (NULL for currently logged in member, FALSE for no member)
	 * @return	void
	 */
	public function onUnhide( $approving, $member )
	{
		$containercolumn = static::containerForumIdColumn();

		parent::onUnhide( $approving, $member );
		if ( \IPS\Application::appIsEnabled('forums') )
		{
			if ( $topic = $this->topic() )
			{
				$topic->unhide( $member );
			}
			elseif ( $approving and $this->container()->$containercolumn )
			{
				$this->syncTopic();
			}
		}
	}

	/**
	 * Move
	 *
	 * @param	\IPS\Node\Model	$container	Container to move to
	 * @param	bool			$keepLink	If TRUE, will keep a link in the source
	 * @return	void
	 */
	public function move( \IPS\Node\Model $container, $keepLink=FALSE )
	{
		$containercolumn = static::containerForumIdColumn();
		$oldCategory = $this->container();

		parent::move( $container, $keepLink );
		if ( \IPS\Application::appIsEnabled('forums') and $topic = $this->topic() )
		{
			/* If the old category didn't sync, but the new one does, create the topic */
			if ( !$oldCategory->$containercolumn and $this->container()->$containercolumn )
			{
				$this->syncTopic();
			}

			/* If both the old and the new categories sync, but to different forums, move the topic, unless it's been moved manually */
			elseif ( $oldCategory->$containercolumn and $this->container()->$containercolumn and $oldCategory->$containercolumn != $this->container()->$containercolumn and $topic->forum_id == $oldCategory->$containercolumn )
			{
				try
				{
					$topic->move( \IPS\forums\Forum::load( $this->container()->$containercolumn ), $keepLink );
				}
				catch ( \Exception $e ) { }
			}
		}
	}

	/**
	 * Create from form
	 *
	 * @param	array					$values		Values from form
	 * @param	\IPS\Node\Model|NULL	$container	Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	TRUE to automatically send new content notifications (useful for items that may be uploaded in bulk)
	 * @return	\IPS\Content\Item
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		$containercolumn = static::containerForumIdColumn();

		$item = parent::createFromForm( $values, $container, $sendNotification );
		if ( \IPS\Application::appIsEnabled('forums') and $item->container()->$containercolumn and !$item->hidden() )
		{
			$item->syncTopic();
		}
		return $item;
	}

	/**
	 * If, when making a post, we should merge with an existing comment, this method returns the comment to merge with
	 *
	 * @return	\IPS\Content\Comment|NULL
	 */
	public function mergeConcurrentComment()
	{
		$column = static::topicIdColumn();
		$lastComment = parent::mergeConcurrentComment();

		/* If we sync to the forums, make sure that the "last comment" is not actually the first post */
		if( $this->$column AND $lastComment !== NULL )
		{
			$firstComment = \IPS\forums\Topic::load( $this->$column )->comments( 1, 0, 'date', 'asc' );

			if( $firstComment->pid == $lastComment->pid )
			{
				return NULL;
			}
		}

		return $lastComment;
	}
}
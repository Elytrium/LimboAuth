//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Post extends _HOOK_CLASS_
{
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		if ( $this->isFirst() AND \IPS\cms\Records::getLinkedRecord( $this->item() ) )
		{
			return FALSE;
		}
		
		return parent::canEdit( $member );
	}

	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item				The content item just created
	 * @param	string					$comment			The comment
	 * @param	bool					$first				Is the first comment?
	 * @param	string					$guestName			If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @param	string|NULL				$ipAddress			The IP address or NULL to detect automatically
	 * @param	int|NULL				$hiddenStatus		NULL to set automatically or override: 0 = unhidden; 1 = hidden, pending moderator approval; -1 = hidden (as if hidden by a moderator)
     * @param	int|NULL				$anonymous			NULL for no value, 0 or 1 for a value (0=no, 1=yes)
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL )
	{
		$comment = parent::create( $item, $comment, $first, $guestName, $incrementPostCount, $member, $time, $ipAddress, $hiddenStatus, $anonymous );
		
		static::recordSync( $item );
		
		return $comment;
	}
	
	/**
     * Delete Post
     *
     * @return	void
     */
    public function delete()
    {
		parent::delete();
		
		/* It is possible to delete a post that is orphaned, so let's try to protect against that */
		try
		{
			static::recordSync( $this->item() );
		}
		catch( \OutOfRangeException $e ){}
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
		
		static::recordSync( $this->item() );
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
		parent::onUnhide( $approving, $member );
		
		static::recordSync( $this->item() );
	}
	
	/**
	 * Sync up the topic
	 * 
	 * @param	\IPS\forums\Topic	$item		Topic object
	 *
	 * @return void
	 */
	protected static function recordSync( $item )
	{
		$synced = array();
		
		/* We used to restrict by forum ID in these two queries, but if you move a topic to a new forum then the counts no longer sync properly */
		foreach( \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_forum_record=? AND category_forum_comments=?', 1, 1 ) ) as $category )
		{
			try
			{
				if ( ! \in_array( $category['category_database_id'], $synced ) )
				{
					$class    = '\IPS\cms\Records' . $category['category_database_id'];
                    if( class_exists( $class ) )
					{
						$object	  = $class::load( $item->tid, 'record_topicid' );
						$object->syncRecordFromTopic( $item );

						/* Successful sync (no exception thrown, so lets skip this database from now on */
						$synced[] = $category['category_database_id'];
                    }

				}
			}
			catch( \Exception $ex )
			{
			}
		}
		
		foreach( \IPS\Db::i()->select( '*', 'cms_databases', array( 'database_forum_record=? AND database_forum_comments=?', 1, 1 ) ) as $database )
		{
			try
			{
				if ( ! \in_array( $database['database_id'], $synced ) )
				{
					$class = '\IPS\cms\Records' . $database['database_id'];
                    if( class_exists( $class ) )
					{
						$object = $class::load( $item->tid, 'record_topicid' );
						$object->syncRecordFromTopic( $item );
                    }
				}
			}
			catch( \Exception $ex )
			{
			}
		}
	}
}
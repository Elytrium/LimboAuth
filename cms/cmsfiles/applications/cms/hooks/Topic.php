//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class cms_hook_Topic extends _HOOK_CLASS_
{
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		$canView = parent::canView( $member );
		$member  = $member ? $member : \IPS\Member::loggedIn();
		
		/* Whitelist which types of do we allow */
		$do      = array( 'editComment' );
		
		if ( ! $canView )
		{
			/* Check to see if it's attached to a database record and we are not a guest */
			if ( isset( \IPS\Request::i()->do ) and \in_array( \IPS\Request::i()->do, $do ) and $record = \IPS\cms\Records::getLinkedRecord( $this ) and $member->member_id )
			{
				return $record->canView();
			}
		}

		return $canView;
	}

	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		if ( \IPS\cms\Records::getLinkedRecord( $this ) )
		{
			return FALSE;
		}
		
		return parent::canEdit( $member );
	}

	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{		
		if ( $action === 'delete' )
		{
			/* We used to restrict by forum ID but if you move a topic to a new forum then the forum ID will no longer match */
			foreach( \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_forum_record=? AND category_forum_comments=?', 1, 1 ) ) as $category )
			{
				try
				{
					$class    = '\IPS\cms\Records' . $category['category_database_id'];

					if( class_exists( $class ) )
					{
						$class::load( $this->tid, 'record_topicid' );

						$database = \IPS\cms\Databases::load( $category['category_database_id'] );
						\IPS\Member::loggedIn()->language()->words['cms_delete_linked_topic'] = sprintf( \IPS\Member::loggedIn()->language()->get('cms_delete_linked_topic'), $database->recordWord( 1 ) );

						\IPS\Output::i()->error( 'cms_delete_linked_topic', '1T281/1', 403, '' );
					}

				}
				catch( \Exception $ex ) { }
			}
			
			foreach( \IPS\Db::i()->select( '*', 'cms_databases', array( 'database_forum_record=? AND database_forum_comments=?', 1, 1 ) ) as $database )
			{
				try
				{
					$class = '\IPS\cms\Records' . $database['database_id'];

					$class::load( $this->tid, 'record_topicid' );
					
					$database = \IPS\cms\Databases::constructFromData( $database );
					\IPS\Member::loggedIn()->language()->words['cms_delete_linked_topic'] = sprintf( \IPS\Member::loggedIn()->language()->get('cms_delete_linked_topic'), $database->recordWord( 1 ) );
					
					\IPS\Output::i()->error( 'cms_delete_linked_topic', '1T281/1', 403, '' );
				}
				catch( \Exception $ex ) { }
			}
		}

		parent::modAction( $action, $member, $reason, $immediately );

		if ( $action === 'lock' or $action === 'unlock' )
		{
			foreach( \IPS\Db::i()->select( '*', 'cms_databases', array( 'database_forum_record=? AND database_forum_comments=?', 1, 1 ) ) as $database )
			{
				try
				{
					$class = '\IPS\cms\Records' . $database['database_id'];
					$record = $class::load( $this->tid, 'record_topicid' );
				
					$record->record_locked = ( $action === 'lock' ) ? 1 : 0;
					$record->save();
				}
				catch( \Exception $ex ) { }
			}
 		}
	}
	
	/**
	 * Can merge?
	 *
	 * @param	\IPS\Member|NULL	$member The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canMerge( $member=NULL )
	{
		if ( \IPS\cms\Records::topicIsLinked( $this ) )
		{
			return FALSE;
		}
		
		return parent::canMerge( $member );
	}
}
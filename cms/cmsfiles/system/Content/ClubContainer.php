<?php
/**
 * @brief		Trait for Content Containers which can be used in Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Feb 2017
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Trait for Content Containers which can be used in Clubs
 */
trait ClubContainer
{
	/**
	 * Get the database column which stores the club ID
	 *
	 * @return	string
	 */
	public static function clubIdColumn()
	{
		return 'club_id';
	}
	
	/**
	 * Get front-end language string
	 *
	 * @return	string
	 */
	public static function clubFrontTitle()
	{
		$itemClass = static::$contentItemClass;
		return $itemClass::$title . '_pl';
	}
	
	/**
	 * Get acp language string
	 *
	 * @return	string
	 */
	public static function clubAcpTitle()
	{
		return static::$nodeTitle;
	}
	
	/**
	 * Check if we need to re-acknowledge rules
	 *
	 * @return void
	 */
	public function clubCheckRules()
	{
		if ( $club = $this->club() AND !$club->rulesAcknowledged() AND !\IPS\Member::loggedIn()->modPermission( 'can_access_all_clubs' ) )
		{
			\IPS\Output::i()->redirect( $club->url()->setQueryString( 'do', 'rules' )->addRef( \IPS\Request::i()->url() ) );
		}
	}
	
	/**
	 * Get the associated club
	 *
	 * @return	\IPS\Member\Club|NULL
	 */
	public function club()
	{
		if ( \IPS\Settings::i()->clubs )
		{
			$clubIdColumn = $this->clubIdColumn();
			if ( $this->$clubIdColumn )
			{
				try
				{
					return \IPS\Member\Club::load( $this->$clubIdColumn );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		return NULL;
	}
		
	/**
	 * Set form for creating a node of this type in a club
	 *
	 * @param	\IPS\Helpers\Form	$form	Form object
	 * @return	void
	 */
	public function clubForm( \IPS\Helpers\Form $form, \IPS\Member\Club $club )
	{
		$itemClass = static::$contentItemClass;
		$form->add( new \IPS\Helpers\Form\Text( 'club_node_name', $this->_id ? $this->_title : \IPS\Member::loggedIn()->language()->addToStack( static::clubFrontTitle() ), TRUE, array( 'maxLength' => 255 ) ) );
	}
	
	/**
	 * Save club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function saveClubForm( \IPS\Member\Club $club, $values )
	{
		$nodeClass = \get_called_class();
		$itemClass = $nodeClass::$contentItemClass;
		$haveId = (bool) $this->_id;
		
		$this->_saveClubForm( $club, $values );
		
		$needToUpdatePermissions = FALSE;
		
		if ( !$haveId )
		{
			$clubIdColumn = $this->clubIdColumn();
			$this->$clubIdColumn = $club->id;
			$this->save();
			\IPS\Db::i()->insert( 'core_clubs_node_map', array(
				'club_id'		=> $club->id,
				'node_class'	=> $nodeClass,
				'node_id'		=> $this->_id,
				'name'			=> $values['club_node_name'],
				'public'		=> ( isset( $values['club_node_public'] ) ) ? $values['club_node_public'] : 0
			) );
			
			$needToUpdatePermissions = TRUE;
		}
		else
		{
			if( isset( $values['club_node_public'] ) AND $values['club_node_public'] != $this->isPublic() )
			{
				$needToUpdatePermissions = TRUE;
			}
			
			$this->save();
			\IPS\Db::i()->update( 'core_clubs_node_map', array( 'name' => $values['club_node_name'], 'public' => $values['club_node_public'] ? $values['club_node_public'] : 0), array( 'club_id=? AND node_class=? AND node_id=?', $club->id, $nodeClass, $this->_id ) );
		}
		
		\IPS\Lang::saveCustom( $itemClass::$application, static::$titleLangPrefix . $this->_id, $values['club_node_name'] );
		\IPS\Lang::saveCustom( $itemClass::$application, static::$titleLangPrefix . $this->_id . '_desc', isset( $values['club_node_description'] ) ? $values['club_node_description'] : '' );
		
		if ( $needToUpdatePermissions )
		{
			$this->setPermissionsToClub( $club );
		}

		if ( !$haveId )
		{
			$followApp = $itemClass::$application;
			$followArea = mb_strtolower( mb_substr( $nodeClass, mb_strrpos( $nodeClass, '\\' ) + 1 ) );
			$time = time();
			$follows = array();
			foreach( \IPS\Db::i()->select( "MD5( CONCAT( '{$followApp};{$followArea};{$this->_id};', follow_member_id ) ) AS follow_id, '{$followApp}' AS follow_app, '{$followArea}' AS follow_area, '{$this->_id}' AS follow_rel_id, follow_member_id, follow_is_anon, '{$time}' AS follow_added, follow_notify_do, follow_notify_meta, follow_notify_freq, 0 AS follow_notify_sent, follow_visible", 'core_follow', array(	'follow_app=? AND follow_area=? AND follow_rel_id=?', 'core', 'club', $club->id ) ) AS $follow )
			{
				$follows[] = $follow;
			}

			if ( \count( $follows ) )
			{
				\IPS\Db::i()->insert( 'core_follow', $follows );
			}
		}
	}
	
	/**
	 * Class-specific routine when saving club form
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @param	array				$values	Values
	 * @return	void
	 */
	public function _saveClubForm( \IPS\Member\Club $club, $values )
	{
		// Deliberately does nothing so classes can override
	}
	
	/**
	 * Set the permission index permissions to a specific club
	 *
	 * @param	\IPS\Member\Club	$club	The club
	 * @return  void
	 */
	public function setPermissionsToClub( \IPS\Member\Club $club )
	{
		/* Delete current rows */
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', static::$permApp, static::$permType, $this->_id ) );

		/* Build new rows */
		$insert = array( 'app' => static::$permApp, 'perm_type' => static::$permType, 'perm_type_id' => $this->_id );
		foreach ( static::$permissionMap as $k => $v )
		{
			if ( \in_array( $k, array( 'view', 'read' ) ) )
			{
				switch ( $club->type )
				{
					case $club::TYPE_PUBLIC:
					case $club::TYPE_OPEN:
					case $club::TYPE_READONLY:
						$insert[ 'perm_' . $v ] = '*';
						break;					
					case $club::TYPE_CLOSED:
						$insert[ 'perm_' . $v ] = ( $this->isPublic() ) ? "*" : "cm,c{$club->id}";						
						break;
					case $club::TYPE_PRIVATE:
						$insert[ 'perm_' . $v ] = "cm,c{$club->id}";
						break;
				}
			}
			elseif ( \in_array( $k, array( 'add', 'edit', 'reply', 'review' ) ) )
			{
				switch ( $club->type )
				{
					case $club::TYPE_PUBLIC:
						$insert[ 'perm_' . $v ] = 'ca';
						break;
					case $club::TYPE_CLOSED:
						$insert[ 'perm_' . $v ] = ( $this->isPublic() == 2 ) ? "*" : "cm,c{$club->id}";
						break;
					case $club::TYPE_OPEN:
					case $club::TYPE_PRIVATE:
					case $club::TYPE_READONLY:
						$insert[ 'perm_' . $v ] = "cm,c{$club->id}";
						break;
				}
			}
			else
			{
				switch ( $club->type )
				{
					case $club::TYPE_PUBLIC:
					case $club::TYPE_READONLY:
						$insert[ 'perm_' . $v ] = 'ca';
						break;
					
					case $club::TYPE_OPEN:
					case $club::TYPE_CLOSED:
					case $club::TYPE_PRIVATE:
						$insert[ 'perm_' . $v ] = "cm,c{$club->id}";
						break;
				}
			}
		}

		/* Insert */
		$permId = \IPS\Db::i()->insert( 'core_permission_index', $insert );
		
		/* Update tags permission cache */
		if ( isset( static::$permissionMap['read'] ) )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_text' => $insert[ 'perm_' . static::$permissionMap['read'] ] ), array( 'tag_perm_aap_lookup=?', md5( static::$permApp . ';' . static::$permType . ';' . $this->_id ) ) );
		}

		/* Make sure this object resets the permissions internally */
		$this->_permissions = array_merge( array( 'perm_id' => $permId ), $insert );
		
		/* Update search index */
		$this->updateSearchIndexPermissions();
	}

	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULL to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @param	array|NULL			$limit				Limit/offset to use, or NULL for no limit (default)
	 * @return	array
	 */
	public static function rootsWithClubs( $permissionCheck='view', $member=NULL, $where=array(), $limit=NULL )
	{
		/* Will we need to check permissions? */
		$usingPermssions = ( \in_array( 'IPS\Node\Permissions', class_implements( \get_called_class() ) ) and $permissionCheck !== NULL );
		if ( $usingPermssions )
		{
			$member = $member ?: \IPS\Member::loggedIn();
		}

		if( static::$databaseColumnParent !== NULL )
		{
			$where[] = array( static::$databasePrefix . static::$databaseColumnParent . '=?', static::$databaseColumnParentRootValue );
		}
		
		$order = static::$databasePrefix . static::clubIdColumn();

		if( static::$databaseColumnOrder !== NULL )
		{
			$order .= ', ' . static::$databasePrefix . static::$databaseColumnOrder;
		}

		return static::nodesWithPermission( $usingPermssions ? $permissionCheck : NULL, $member, $where, $order, $limit );
	}

	/**
	 * @brief	Cached club nodes
	 */
	protected static $cachedClubNodes = NULL;
	
	/**
	 * Fetch All Nodes in Clubs
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @return	array
	 */
	public static function clubNodes( $permissionCheck='view', $member=NULL, $where=array() )
	{
		if( static::$cachedClubNodes === NULL )
		{
			$clubIdColumn = static::clubIdColumn();

			$where[] = array( static::$databasePrefix . $clubIdColumn . ' IS NOT NULL' );
			
			$member = $member ?: \IPS\Member::loggedIn();

			if( $member->canAccessModule( \IPS\Application\Module::get( 'core', 'clubs', 'front' ) ) )
			{
				static::$cachedClubNodes = static::nodesWithPermission( $permissionCheck, $member, $where );
			}
			else
			{
				static::$cachedClubNodes = array();
			}

			/* Preload the clubs so we don't query each one individually later */
			$clubIds = array();

			foreach( static::$cachedClubNodes as $node )
			{
				$clubIds[] = $node->$clubIdColumn;
			}

			if( \count( $clubIds ) )
			{
				foreach( \IPS\Db::i()->select( '*', 'core_clubs', array( 'id IN(' . implode( ',', $clubIds ) . ')' ) ) as $clubData )
				{
					\IPS\Member\Club::constructFromData( $clubData );
				}
			}
		}

		return static::$cachedClubNodes;
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	string						$class		The class to check against
	 * @return	bool
	 */
	public function modPermission( $type, \IPS\Member $member, $class )
	{
		if ( \IPS\Settings::i()->clubs )
		{
			$clubIdColumn	= $this->clubIdColumn();
			
			$class = $class ?: static::$contentItemClass;
			$title = $class::$title;

			if ( $this->$clubIdColumn and $club = $this->club() )
			{
				if ( \in_array( $club->memberStatus( $member ), array( \IPS\Member\Club::STATUS_LEADER, \IPS\Member\Club::STATUS_MODERATOR ) ) )
				{
					if ( \in_array( $type, explode( ',', \IPS\Settings::i()->clubs_modperms ) ) )
					{
						return TRUE;
					}
				}
				elseif ( $member->modPermission( "can_{$type}_{$title}" ) and \is_array( $member->modPermission( static::$modPerm ) ) and $member->modPermission('can_access_all_clubs') )
				{
					if ( \in_array( $type, explode( ',', \IPS\Settings::i()->clubs_modperms ) ) )
					{
						return TRUE;
					}
				}
			}		
		}

		return parent::modPermission( $type, $member, $class );
	}
		
	/**
	 * [Node] Get parent list
	 *
	 * @return	\SplStack
	 */
	public function parents()
	{
		$clubIdColumn = static::clubIdColumn();
		
		/* Only do this if this node is actually associated with a club */
		if ( $this->$clubIdColumn )
		{
			return new \SplStack;
		}
		else
		{
			return parent::parents();
		}
	}
	
	/**
	 * Is public
	 *
	 * @return	int 0 = not public, 1 = anyone can view, 2 = anyone can participate
	 */
	public function isPublic()
	{
		try
		{
			return (int) \IPS\Db::i()->select( 'public', 'core_clubs_node_map', array( 'club_id=? AND node_class=? AND node_id=?', $this->club()->id, \get_called_class(), $this->_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			return 0;
		}
	}
}
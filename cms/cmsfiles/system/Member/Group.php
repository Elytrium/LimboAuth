<?php
/**
 * @brief		Group Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Mar 2013
 */

namespace IPS\Member;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Group Model
 */
class _Group extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_groups';
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'g_id';
	
	/**
	 * @brief	Bitwise keys
	 */
	protected static $bitOptions = array(
		'g_bitoptions'	=> array(
			'g_bitoptions'	=> array(
				'gbw_mod_post_unit_type'	=> 1, 			// Lift moderation after x. 1 is days, 0 is posts. Corresponds to g_mod_post_unit
				'gbw_ppd_unit_type'			=> 2, 			// Lift post-per-day limit after x. 1 is days, 0 is posts. Corresponds to g_ppd_unit
				'gbw_displayname_unit_type'	=> 4, 			// Username change restrictions. 1 is days, 0 is posts. Corresponds to g_displayname_unit
				'gbw_sig_unit_type'			=> 8, 			// Signature edit restrictions. 1 is days, 0 is posts. Corresponds to g_sig_unit
				'gbw_promote_unit_type'		=> 16, 			// 16 is deprecated (previously gbw_promote_unit_type). Remove in 4.1.19.
				'gbw_no_status_update'		=> 32, 			// Can NOT post status updates
				// 64 is deprecated (previously gbw_soft_delete)
				// 128 is deprecated (previously gbw_soft_delete_own)
				// 256 is deprecated (previously gbw_soft_delete_own_topic)
				// 512 is deprecated (previously gbw_un_soft_delete)
				// 1024 is deprecated (previously gbw_soft_delete_see)
				// 2048 is deprecated (previously gbw_soft_delete_topic)
				// 4096 is deprecated (previously gbw_un_soft_delete_topic)
				// 8192 is deprecated (previously gbw_soft_delete_topic_see)
				// 16384 is deprecated (previously gbw_soft_delete_reason)
				// 32768 is deprecated (previously gbw_soft_delete_see_post)
				// 65536 is deprecated (previously gbw_allow_customization)
				// 131072 is deprecated (previously gbw_allow_url_bgimage)
				'gbw_allow_upload_bgimage'	=> 262144, 		// Can upload a cover photo?
				'gbw_view_reps'				=> 524288, 		// Can view who gave reputation?
				'gbw_no_status_import'		=> 1048576, 	// Can NOT import status updates from Facebook/Twitter
				'gbw_disable_tagging'		=> 2097152, 	// Can NOT use tags
				'gbw_disable_prefixes'		=> 4194304, 	// Can NOT use prefixes
				// 8388608 is deprecated (previously gbw_view_last_info)
				// 16777216 is deprecated (previously gbw_view_online_lists)
				// 33554432 is deprecated (previously gbw_hide_leaders_page)
				'gbw_pm_unblockable'		=> 67108864,	// Deprecated in favour of global unblockable setting
				'gbw_pm_override_inbox_full'=> 134217728,	// 1 means this group can send other members PMs even when that member's inbox is full
				// 268435456 is deprecated (previously gbw_no_report)
				'gbw_cannot_be_ignored'		=> 536870912,	// 1 means they cannot be ignored. 0 means they can
				'gbw_delete_attachments'	=> 1073741824,	// 1 means they can delete attachments from the "My Attachments" screen
			),
			'g_bitoptions2'	=> array(
				'gbw_post_highlight'	=> 1,	// 1 means on, 0 means off
				'gbw_hide_group'		=> 2,	// Hide this group on the front end in search and similar areas: 1 is on, 0 is off
				// 4 is deprecated (previously gbw_lock_unlock_own)
				'gbw_promote'			=> 8,	// Allow users in this group to promote to twitter
				'gbw_immune_auto_mod'   => 16,	// Users in this group can not be auto moderated
				'gbw_paid_clubs'		=> 32,	// Users in this group can create paid clubs
				'gbw_can_post_anonymously'	=> 64, // Users in this group can post anonymously (where allowed)
				'gbw_hide_inline_modevents'	=> 128, // Users in this group can see inline moderation events (where supported)
				'gbw_posted_in'				=> 256, // Items (topics, articles) will be marked as 'posted in by this group'
				'gbw_posted_in_secondary'	=> 512, // Items (topics, articles) will be marked as 'posted in by this group' when the group is set as a secondary group
				'gbw_club_manage_indexing'	=> 1024, // Users in this group can manage indexing of clubs
			)
		)
	);
		
	/**
	 * @brief	[ActiveRecord] Attempt to load from cache
	 * @note	If this is set to TRUE you MUST define a getStore() method to return the objects from cache
	 */
	protected static $loadFromCache = TRUE;

	/**
	 * Group datastore
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->groups ) )
		{
			\IPS\Data\Store::i()->groups = iterator_to_array( \IPS\Db::i()->select( '*', 'core_groups' )->setKeyField( 'g_id' ) );
		}

		return \IPS\Data\Store::i()->groups;
	}
	
	/**
	 * @brief	Stored Groups
	 */
	protected static $allGroups;
		
	/**
	 * Get groups
	 *
	 * @param	bool	$showAdminGroups	Show admin groups. Used to restrict admin groups from being available when you cannot add/edit members in them.
	 * @param	bool	$showGuestGroups	Show guest groups. Used to remove the guest group from the available groups returned.
	 * @param	bool	$hideForFiltering	If the group is set not to not be available to filter by, should we hide them?
	 * @return	array
	 */
	public static function groups( $showAdminGroups=TRUE, $showGuestGroups=TRUE, $hideForFiltering=FALSE )
	{
		if ( !static::$allGroups )
		{
			static::$allGroups = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( static::getStore() ), 'IPS\Member\Group' ) );

			/* If we have a dispatcher instance, sort the groups alphabetically */
			if( \IPS\Dispatcher::hasInstance() )
			{
				/* Get all of the group names in one query */
				$sortedGroupIds = array();

				foreach( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'word_key LIKE ? AND lang_id=?', 'core_group_%', \IPS\Member::loggedIn()->language()->id ), 'word_custom ASC' ) as $lang )
				{
					$sortedGroupIds[] = \str_replace( 'core_group_', '', $lang['word_key'] );
				}

				$finalGroups = array();

				foreach( $sortedGroupIds as $groupId )
				{
					if( isset( static::$allGroups[ $groupId ] ) )
					{
						$finalGroups[ $groupId ] = static::$allGroups[ $groupId ];
					}
				}

				static::$allGroups = $finalGroups;
			}
		}

		$groups = static::$allGroups;

		if ( !$showGuestGroups )
		{
			unset( $groups[ \IPS\Settings::i()->guest_group ] );
		}

		if( !$showAdminGroups )
		{
			$administrators = \IPS\Member::administrators();
			foreach( $groups as $k => $_group )
			{
				if ( isset( $administrators['g'][ $_group->g_id ] ) )
				{
					unset( $groups[ $k ] );
				}
			}
		}

		if( $hideForFiltering )
		{
			foreach( $groups as $groupId => $group )
			{
				if( $group->g_bitoptions['gbw_hide_group'] )
				{
					unset( $groups[ $groupId ] );
				}
			}
		}

		return $groups;
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		$oldId = $this->g_id;
		$oldGroup = $this;
		parent::__clone();

		/* Rebuild permission indexes */
		$perms = array( 'perm_view', 'perm_2', 'perm_3', 'perm_4', 'perm_5', 'perm_6', 'perm_7' );
		$where = array();
		foreach( $perms as $key )
		{
			$where[] = \IPS\Db::i()->findInSet( $key, array($oldId) );
		}

		foreach( \IPS\Db::i()->select( '*', 'core_permission_index', implode( ' OR ', $where ) ) as $index )
		{
			foreach( $perms as $key )
			{
				$groups = explode( ",", $index[$key] );
				if( \in_array( $oldId, $groups ) and !\in_array( $this->g_id, $groups ) )
				{
					$groups[] = $this->g_id;
				}

				$index[$key] = implode( ",", $groups );
			}
			\IPS\Db::i()->update( 'core_permission_index', $index, array( 'perm_id = ?', $index['perm_id'] ) );
		}

		$extensions = \IPS\Application::allExtensions( 'core', 'GroupForm', FALSE, 'core', 'GroupSettings', TRUE );
		/* Process each extension */
		foreach ( $extensions as $class )
		{
			if( method_exists( $class, 'cloneGroup' ) )
			{
				$class->cloneGroup( $oldGroup, $this );
			}
		}

		\IPS\Lang::saveCustom( 'core', "core_group_{$this->g_id}", iterator_to_array( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'word_key=?', "core_group_{$oldId}" ) )->setKeyField('lang_id')->setValueField('word_custom') ) );

		/* Clear application and module store so that permissions are correctly rebuilt */
		unset( \IPS\Data\Store::i()->applications );
		unset( \IPS\Data\Store::i()->modules );
		unset( \IPS\Data\Store::i()->groups );

		/* Rebuild search index */
		\IPS\Content\Search\Index::i()->rebuild();
	}
	
	/**
	 * Get data
	 *
	 * @return	array
	 */
	public function data()
	{
		return $this->_data;
	}
	
	/**
	 * Magic Method: To String
	 * Returns group name
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->name;
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function get_name()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( "core_group_{$this->g_id}" );
	}
	
	/**
	 * Get formatted name
	 *
	 * @return	string
	 */
	public function get_formattedName()
	{
		return $this->formatName( $this->name );
	}

	/**
	 * Get number of members in this group
	 *
	 * @note	Includes count of members with this group as a secondary group
	 * @note	The count is cached for 10 minutes as the query can be resource intensive
	 * @return	int
	 */
	public function getCount()
	{
		$key = 'groupMembersCount_' . $this->g_id;

		try
		{
			$count = \IPS\Data\Store::i()->$key;

			if( $values = json_decode( $count, true ) )
			{
				if( $values['expire'] < time() )
				{
					throw new \OutOfRangeException;
				}

				return $values['count'];
			}
			else
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)',  'core_members', array( 'member_group_id=? OR FIND_IN_SET( ?, mgroup_others )', $this->g_id, $this->g_id ) )->first();

			\IPS\Data\Store::i()->$key = json_encode( array( 'count' => $count, 'expire' => \IPS\DateTime::create()->add( new \DateInterval( 'PT10M' ) )->getTimestamp() ) );
		}

		return (int) $count;
	}
	
	/**
	 * Format Name
	 *
	 * @param	string	$name	The name to format
	 * @return	string
	 */
	public function formatName( $name )
	{
		return ( $this->prefix . htmlspecialchars( $name, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE ) . $this->suffix );
	}

	/**
	 * Can use module
	 *
	 * @param	\IPS\Application\Module	$module	The module to test
	 * @return	bool
	 */
	public function canAccessModule( $module )
	{
		return $module->can( 'view', $this );
	}

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'groups', 'moderators', 'administrators' );

	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( \in_array( $this->g_id, array( \IPS\Settings::i()->guest_group, \IPS\Settings::i()->member_group, \IPS\Settings::i()->admin_group ) ) )
		{
			throw new \InvalidArgumentException;
		}

		/* remove group from mod & staff section */

		\IPS\Db::i()->delete( 'core_leaders', array( 'leader_type=? AND leader_type_id = ?', 'g', $this->g_id ) );
		\IPS\Db::i()->delete( 'core_moderators', array( 'type=? AND id = ?', 'g', $this->g_id ) );
		\IPS\Db::i()->delete( 'core_admin_permission_rows', array( 'row_id=? and row_id_type=?', $this->g_id, 'group' ) );

		/* Make sure no other groups have this group ID set to promote to */
		foreach( static::groups() as $group )
		{
			$promote = explode( '&', $group->g_promotion );

			if( $promote[0] == $this->g_id )
			{
				$group->g_promotion = '-1&' . $promote[1];
				$group->save();
			}
		}

		$extensions = \IPS\Application::allExtensions( 'core', 'GroupForm', FALSE, 'core', 'GroupSettings', TRUE );
		/* Process each extension */
		foreach ( $extensions as $class )
		{
			if( method_exists( $class, 'delete' ) )
			{
				$class->delete( $this );
			}
		}
		
		parent::delete();
		\IPS\Lang::deleteCustom( 'core', 'core_group_' . $this->g_id );

		/* Update Core Leaders */
		\IPS\core\StaffDirectory\User::updateEmptySetting();

		/* Deleted group, expire widget caches (online stats may try to load a group) */
		\IPS\Widget::deleteCaches();
	}

	/**
	 * Can this group be deleted?
	 *
	 * @return bool
	 */
	public function canDelete() : bool
	{
		$extensions = \IPS\Application::allExtensions( 'core', 'GroupForm', FALSE, 'core', 'GroupSettings', TRUE );

		foreach ( $extensions as $class )
		{
			if( method_exists( $class, 'canDelete' ) AND !$class->canDelete( $this ) )
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Return group that will show the posted in indicator
	 *
	 * @param array	$primary	Primary Group IDs
	 * @param array	$secondary	Array of Secondary Group IDs
	 * @return array|FALSE
	 */
	public static function postedIn( array $primary, array $secondary )
	{
		if ( !static::$allGroups )
		{
			static::groups();
		}
		$return = array();

		foreach( array( 'primary', 'secondary' ) as $type )
		{
			if ( \is_array( $$type ) and \count( $$type ) )
			{
				foreach ( $$type as $id )
				{
					if ( isset( static::$allGroups[$id] ) and static::$allGroups[$id]->g_bitoptions[ ( $type == 'primary' ? 'gbw_posted_in' : 'gbw_posted_in_secondary') ] )
					{
						$return[ $id ] = static::$allGroups[$id];
					}
				}
			}
		}

		return ( \count( $return ) ? $return : FALSE );
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return		array
	 * @apiresponse	int			id				ID number
	 * @apiresponse	string		name			Name
	 * @apiresponse	string		formattedName	Name with formatting
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'id'				=> $this->g_id,
			'name'				=> $this->name,
			'formattedName'		=> $this->formattedName,
		);
	}
}
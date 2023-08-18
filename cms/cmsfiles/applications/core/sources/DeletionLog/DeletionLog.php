<?php
/**
 * @brief		Deletion Log Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Deletion Log Model
 */
class _DeletionLog extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_deletion_log';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'dellog_';
	
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
		
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->deleted_date = \IPS\DateTime::create();
	}
	
	/**
	 * Set deleted date
	 *
	 * @param	\IPS\DateTime	$time	A DateTime object
	 * @return	void
	 */
	public function set_deleted_date( \IPS\DateTime $time )
	{
		$this->_data['deleted_date'] = $time->getTimestamp();
	}
	
	/**
	 * Get deleted date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_deleted_date()
	{
		return \IPS\DateTime::ts( $this->_data['deleted_date'] );
	}
	
	/**
	 * Set Deleted By
	 *
	 * @param	\IPS\Member		$member	The member
	 * @return	void
	 */
	public function set_deleted_by( \IPS\Member $member )
	{
		$this->_data['deleted_by']			= $member->member_id;
		$this->_data['deleted_by_name']		= $member->real_name;
		$this->_data['deleted_by_seo_name']	= $member->members_seo_name;
	}
	
	/**
	 * @brief	Deleted By Cache
	 */
	protected $_deletedBy = NULL;
	
	/**
	 * Get Deleted By
	 *
	 * @return	\IPS\Member
	 */
	public function get__deleted_by()
	{
		if ( $this->_deletedBy === NULL AND $this->_data['deleted_by'] !== NULL )
		{
			$this->_deletedBy = \IPS\Member::load( $this->_data['deleted_by'] );
		}
		return $this->_deletedBy;
	}
	
	/**
	 * Get Permissions
	 *
	 * @return	array|string
	 */
	public function get_content_permissions()
	{
		if ( $this->_data['content_permissions'] == '*' )
		{
			return $this->_data['content_permissions'];
		}
		
		$perms = explode( ',', $this->_data['content_permissions'] );
		
		$return = array( 'members' => array(), 'groups' => array() );
		foreach( $perms AS $perm )
		{
			if ( \substr( $perm, 0, 1 ) == 'm' )
			{
				$return['members'][] = str_replace( 'm', '', $perm );
			}
			else
			{
				$return['groups'][] = $perm;
			}
		}
		
		return $return;
	}
	
	/**
	 * Get the date the content will be permanently removed on
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_deletion_date()
	{
		return $this->deleted_date->add( new \DateInterval( 'P' . \IPS\Settings::i()->dellog_retention_period . 'D' ) );
	}
	
	/**
	 * Set Content and Member
	 *
	 * @param	\IPS\Content		$content	The content being deleted.
	 * @param	\IPS\Member|NULL	$member		The member performing the deletion, NULL for the currently logged in member, or FALSE for no member (i.e. system task)
	 * @return	void
	 * @note Convenience ftw
	 */
	public function setContentAndMember( \IPS\Content $content, $member = NULL )
	{
		if( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
		
		$idField = $content::$databaseColumnId;
		
		$item = $content;
		if ( $content instanceof \IPS\Content\Comment )
		{
			$item = $content->item();
		}
		
		/* Content Data */
		$this->content_class		= \get_class( $content );
		$this->content_id			= $content->$idField;
		$this->content_title		= $item->mapped('title');
		$this->content_seo_title	= \IPS\Http\Url\Friendly::seoTitle( $item->mapped('title') );
		$this->content_permissions	= $item->deleteLogPermissions();

		try
		{
			$this->content_container_id		= $item->container()->_id;
			$this->content_container_class	= $item::$containerNodeClass;
		}
		catch( \BadMethodCallException | \OutOfRangeException $e )
		{
			$this->content_container_id		= 0;
			$this->content_container_class	= NULL;
		}
		
		/* Member Data */
		if( $member )
		{
			$this->deleted_by	= $member;
		}
	}
	
	/**
	 * Save
	 *
	 * @return	void
	 */
	public function save()
	{
		if ( !$this->id )
		{
			$contentClass	= $this->content_class;
			$content		= $contentClass::load( $this->content_id );

			if( $content instanceof \IPS\Content\Searchable )
			{
				\IPS\Content\Search\Index::i()->removeFromSearchIndex( $content );
			}
		}
		
		parent::save();
	}
	
	/**
	 * Load and check perms
	 *
	 * @param	int		$id	ID
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id )
	{
		$obj = parent::load( $id );
		
		if ( !$obj->canView() )
		{
			throw new \OutOfRangeException;
		}
		
		return $obj;
	}
	
	/**
	 * Load from content
	 *
	 * @param	\IPS\Content		$content	The content object
	 * @param	\IPS\Member		$member		Member for permission checking
	 * @return	static
	 * @throws \OutOfRangeException
	 */
	public static function loadFromContent( \IPS\Content $content, ?\IPS\Member $member = NULL ): \IPS\core\DeletionLog
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		try
		{
			$idColumn = $content::$databaseColumnId;
			$log = static::constructFromData( \IPS\Db::i()->select( '*', 'core_deletion_log', array( "dellog_content_class=? AND dellog_content_id=?", \get_class( $content ), $content->$idColumn ) )->first() );
		}
		catch( \UnderflowException $e )
		{
			throw new \OutOfRangeException;
		}
		
		if ( !$log->canView( $member ) )
		{
			throw new \OutOfRangeException;
		}
		
		return $log;
	}
	
	/**
	 * Can View
	 *
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	bool
	 */
	public function canView( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !\is_array( $this->content_permissions ) AND $this->content_permissions == '*' )
		{
			return TRUE;
		}
		
		if ( \in_array( $member->member_id, $this->content_permissions['members'] ) )
		{
			return TRUE;
		}
		
		if ( $member->inGroup( $this->content_permissions['groups'] ) )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * URL
	 *
	 * @param	string|NULL		$action	"action" parameter or NULL to go to the content
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		if ( $action === NULL )
		{
			try
			{
				$class	= $this->content_class;
				if ( class_exists( $this->content_class ) )
				{
					$item	= $class::load( $this->content_id );
					return $item->url()->setQueryString( 'showDeleted', 1 );
				}
				else
				{
					/* Content class doesn't exist anymore */
					$this->delete();
					return \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=deleted", 'front', 'modcp_deleted' )->setQueryString( array( 'id' => $this->id, 'action' => $action ) );
				}
			}
			catch( \OutOfRangeException $e )
			{
				/* Orphaned item */
				$this->delete();
				
				return \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=deleted", 'front', 'modcp_deleted' )->setQueryString( array( 'id' => $this->id, 'action' => $action ) );
			}
		}
		else
		{
			return \IPS\Http\Url::internal( "app=core&module=modcp&controller=modcp&tab=deleted", 'front', 'modcp_deleted' )->setQueryString( array( 'id' => $this->id, 'action' => $action ) );
		}
	}

	/**
	 * Mass update permissions for a specific node
	 * 
	 * @param	string				$class	The Node Class to update
	 * @param	int					$id		The ID of the container
	 * @param	string|array		$perms	The permissions to update with
	 * @return	void
	 */
	public static function updateNodePermissions( $class, $id, $perms )
	{
		if ( \is_array( $perms ) )
		{
			$perms = implode( ',', $perms );
		}

		\IPS\Db::i()->update( 'core_deletion_log', array(
			'dellog_content_permissions' => $perms
		), array( "dellog_content_container_id=? AND dellog_content_container_class=?", $id, $class ) );
	}
}
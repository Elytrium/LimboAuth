<?php
/**
 * @brief		Groups
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Mar 2013
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Groups
 */
class _groups extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'groups_manage' );
		
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		if ( isset( \IPS\Request::i()->searchResult ) )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( sprintf( \IPS\Member::loggedIn()->language()->get('search_results_in_nodes'), mb_strtolower( \IPS\Member::loggedIn()->language()->get('group') ) ), 'information' );
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_groups', \IPS\Http\Url::internal( 'app=core&module=members&controller=groups' ) );
		
		$table->include = array( 'word_custom', 'members' );
		$table->noSort = array( 'members' );
		$table->langPrefix = 'acpgroups_';
		
		$table->joins = array(
			array( 'select' => 'w.word_custom', 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=CONCAT( 'core_group_', core_groups.g_id ) AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
		);
		
		$table->parsers = array(
			'word_custom'		=> function( $val, $row )
			{
				return \IPS\Member\Group::constructFromData( $row )->formattedName;
			},
			'members'		=> function( $val, $row )
			{
				if ( $row['g_id'] == \IPS\Settings::i()->guest_group )
				{
					$onlineGuests = \IPS\Session\Store::i()->getOnlineUsers( \IPS\Session\Store::ONLINE_GUESTS | \IPS\Session\Store::ONLINE_COUNT_ONLY );
					
					return \IPS\Member::loggedIn()->language()->addToStack( 'online_guests', FALSE, array( 'pluralize' => array( $onlineGuests ) ) );
				}

				return \IPS\Theme::i()->getTemplate( 'members' )->memberCounts( $row );
			}
		);
		
		$table->mainColumn = 'word_custom';
		$table->quickSearch = array( 'word_custom', 'word_custom' );
		
		$table->sortBy = $table->sortBy ?: 'word_custom';
		$table->sortDirection = $table->sortDirection ?: 'asc';
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'groups_add' ) )
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'primary'	=> true,
				'icon'		=> 'plus',
				'title'		=> 'groups_add',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=groups&do=form' ),
			);
		}

		/* Show an extra warning if we are going to delete a group that Commerce intends to move subscribers back to */
		$warnGroups = array();
		if( \IPS\Db::i()->checkForColumn( 'core_members', 'cm_return_group' ) )
		{
			try
			{
				$warnGroups = iterator_to_array( \IPS\Db::i()->select( 'DISTINCT(cm_return_group)', 'core_members' ) );
			}
			catch( \Exception $e ){}
		}
		
		$table->rowButtons = function( $row ) use ( $warnGroups )
		{
			$return = array();
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'groups_edit' ) )
			{
				$editLink = \IPS\Http\Url::internal( 'app=core&module=members&controller=groups&do=form&id=' . $row['g_id'] );
				if ( isset( \IPS\Request::i()->searchResult ) )
				{
					$editLink = $editLink->setQueryString( 'searchResult', \IPS\Request::i()->searchResult );
				}
				$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> $editLink,
				);
				
				$return['permissions'] = array(
					'icon'		=> 'lock',
					'title'		=> 'permissions',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=groups&do=permissions&id=' ) . $row['g_id'],
				);
			}
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'groups_add' ) )
			{
				$return['copy'] = array(
					'icon'		=> 'files-o',
					'title'		=> 'copy',
					'data'		=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack( 'group_clone_confirm_message' ), 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack( 'group_clone_confirm_sub_message' ) ),
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=groups&do=copy&id=' . $row['g_id'] )->csrf(),
				);
			}
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_export' ) )
			{
				$return['download'] = array(
					'icon'		=> 'cloud-download',
					'title'		=> 'members_export',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=export&_new=1&group=' ) . $row['g_id'],
				);
			}
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'groups_delete' ) AND \IPS\Member\Group::load($row['g_id'] )->canDelete() )
			{
				$return['delete'] = array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=groups&do=delete&id=' ) . $row['g_id'],
					'data'		=> \IPS\Db::i()->select( 'COUNT(*)',  'core_members', array( 'member_group_id=?', $row['g_id'] ) )->first() ? 
						array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( "core_group_{$row['g_id']}" ) ) : 
						array( 'delete' => '', 'delete-warning' => ( \in_array( $row['g_id'], $warnGroups ) ) ? \IPS\Member::loggedIn()->language()->addToStack( "nexus_dont_delete_group", FALSE, array( 'sprintf' => array( \IPS\Member\Group::load( \IPS\Settings::i()->member_group )->name ) ) ) : '' ),
				);
			}
			
			return $return;
		};
		
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('groups');
		\IPS\Output::i()->output .= (string) $table;
	}

	/**
	 * Fetch the number of members in this group
	 *
	 * @note	Fetched via AJAX after the page is loaded as this can be intensive with lots of groups and lots of members
	 * @return	void
	 */
	public function getCount()
	{
		\IPS\Output::i()->sendOutput( \IPS\Member\Group::load( \IPS\Request::i()->group )->getCount(), 200, 'text/html' );
	}
	
	/**
	 * Add/Edit Group
	 *
	 * @return	void
	 */
	public function form()
	{
		/* Load group */
		try
		{
			$group = \IPS\Member\Group::load( \IPS\Request::i()->id );
			\IPS\Dispatcher::i()->checkAcpPermission( 'groups_edit' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'groups_add' );
			$group = new \IPS\Member\Group;
		}
		/* Get extensions */
		$extensions = \IPS\Application::allExtensions( 'core', 'GroupForm', FALSE, 'core', 'GroupSettings', TRUE );
		/* Build form */
		$form = new \IPS\Helpers\Form( ( !$group->g_id ? 'groups_add' : $group->g_id ) );
		foreach ( $extensions as $k => $class )
		{
			$form->addTab( 'group__' . $k );
			$class->process( $form, $group );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Create a group if we don't have one already - we have to save it so we have an ID for our translatables */
			$new = FALSE;
			if ( !$group->g_id )
			{
				$group->save();
				$new = TRUE;
			}
			
			/* Process each extension */
			foreach ( $extensions as $class )
			{
				$class->save( $values, $group );
			}
			
			/* And save */
			$group->save();
			\IPS\Session::i()->log( ( $new ) ? 'acplog__groups_created' : 'acplog__groups_edited', array( "core_group_". $group->g_id => TRUE ) );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=groups' ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->title	 		= ( $group->g_id ? $group->name : \IPS\Member::loggedIn()->language()->addToStack('groups_add') );
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output 		= \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Output::i()->title, $form );
	}
	
	/**
	 * Permissions
	 *
	 * @return	void
	 */
	public function permissions()
	{
		/* Load group */
		try
		{
			$group = \IPS\Member\Group::load( \IPS\Request::i()->id );
			\IPS\Dispatcher::i()->checkAcpPermission( 'groups_edit' );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C108/3', 404, '' );
		}
		
		/* Load Permissions */
		$current = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_permission_index' ) as $row )
		{
			$current[ $row['app'] ][ $row['perm_type'] ][ $row['perm_type_id'] ] = $row;
		}

		/* Init Form */
		$form = new \IPS\Helpers\Form;
		
		/* Add Tabs */
		foreach ( \IPS\Application::allExtensions( 'core', 'Permissions', FALSE ) as $ext )
		{
			foreach ( $ext->getNodeClasses() as $class => $callback )
			{
				if( !\is_callable( $callback ) )
				{
					continue;
				}

				/* Start a new section */
				$form->addTab( '__app_' . $class::$permApp );
				$matrix	= new \IPS\Helpers\Form\Matrix( $class );
				$matrix->classes = array( 'cGroupPermissions' );
				$matrix->styledRowTitle = TRUE;
				$matrix->showTooltips = TRUE;

				/* Remove delete buttons and add row button */
				$matrix->manageable	= FALSE;

				/* Call the callable callbacks */
				$matrix->columns	= array(
					'label'		=> function( $key, $value, $data ) use ( $class )
					{
						$nodeId = explode( '[', $key )[0];
						$acpUrl = NULL;

						/* Let's try to get a link to the node's permissions */
						try
						{
							$node = $class::load( $nodeId );

							if ( !$node->canManagePermissions() )
								throw new \DomainException;

							$acpUrl = $node->acpUrl( 'permissions' );
						}
						catch ( \Exception $e ) { }

						return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->titleWithLink( $value, $acpUrl, 'all_permissions', \IPS\Member::loggedIn()->language()->addToStack( "all_permissions_node_hovertitle", FALSE, array( 'sprintf' => [ $value ] ) ) );
					}
				);
				$matrix->widths = array( 'label' => 30 );
				
				/* Automatically generate the columns based on the node class permission map */
				foreach ( $class::$permissionMap as $k => $v )
				{
					$matrix->columns[ $class::$permissionLangPrefix . 'perm__' . $k ] = function( $key, $value, $data ) use ( $class ) 
					{
						return new \IPS\Helpers\Form\Checkbox( $class::$permApp . $key, $value, FALSE, array( 'disabled' => \is_null( $value ) ) );
					};
					
					if( isset( $current[ $class::$permApp ][ $class::$permType ] ) AND \is_array( $current[ $class::$permApp ][ $class::$permType ] ) )
					{
						foreach( $current[ $class::$permApp ][ $class::$permType ] AS $x => $y )
						{
							$matrix->checkAlls[ $class::$permissionLangPrefix . 'perm__' . $k ] = TRUE;
							if ( $y["perm_{$v}"] !== '*' AND !\in_array( $group->g_id, explode( ',', $y["perm_{$v}"] ) ) )
							{
								$matrix->checkAlls[ $class::$permissionLangPrefix . 'perm__' . $k ] = FALSE;
								break;
							}
						}
					}
				}
			
				$matrix->checkAllRows = TRUE;
				
				if ( isset( $current[ $class::$permApp ][ $class::$permType ] ) )
				{
					try
					{
						$matrix->rows		= $callback( $current[ $class::$permApp ][ $class::$permType ], $group );
					}
					catch( \UnderflowException $e )
					{
						if( $e->getCode() != 199 )
						{
							throw $e;
						}

						\IPS\Output::i()->error( 'generic_error', '4T382/1', 500, $e->getMessage() );
					}
				}

				/* Add the matrix */		
				$form->addMatrix( $class, $matrix );
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			foreach( $values as $class => $newValues )
			{
				foreach( $newValues as $permTypeId => $newPermissions )
				{
					$perms = array();
					foreach( $newPermissions AS $k => $v )
					{
						$perms[ str_replace( $class::$permissionLangPrefix . 'perm__', '', $k ) ] = $v;
					}
					$class::load( $permTypeId )->changePermissions( $group, $perms );
				}
			}

			/* Clear out member's cached "Create Menu" contents */
			\IPS\Member::clearCreateMenu();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* Log */
			\IPS\Session::i()->log( 'permissions_adjusted_node', array( "core_group_". $group->g_id => TRUE ) );

			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=groups' ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'members/group.css', 'core', 'admin' ) );
		\IPS\Output::i()->title	 		= $group->name;
		\IPS\Output::i()->breadcrumb[]	= array( NULL, \IPS\Output::i()->title );
		\IPS\Output::i()->output 		= \IPS\Theme::i()->getTemplate( 'global' )->block( 'permissions', $form );
	}
	
	/**
	 * Copy Group
	 *
	 * @return	void
	 */
	public function copy()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'groups_add' );
		\IPS\Session::i()->csrfCheck();
	
		/* Load group */
		try
		{
			$group = \IPS\Member\Group::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C108/1', 404, '' );
		}
		
		/* Copy */
		$newGroup = clone $group;
		\IPS\Session::i()->log( 'acplog__groups_copied', array( "core_group_". $newGroup->g_id => TRUE ) );
		
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=groups&do=form&id={$newGroup->g_id}" ) );
	}
	
	/**
	 * Delete Group
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'groups_delete' );
		
		/* Load group */
		try
		{
			$group = \IPS\Member\Group::load( \IPS\Request::i()->id );
			if( !$group->canDelete() )
			{
				\IPS\Output::i()->error( 'cannot_delete_group', '2C108/5', 403, '' );
			}
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', array( 'member_group_id=?', $group->g_id ) )->first();
			
			/* Any members in it? */
			if ( $count )
			{
				/* Create the options, but don't let the admin move members in the group back into this same group */
				$groupOptions = \IPS\Member\Group::groups( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin2' ), FALSE );
				unset( $groupOptions[ $group->g_id ] );

				$form = new \IPS\Helpers\Form( 'form', 'delete' );
				$form->add( new \IPS\Helpers\Form\Select( 'delete_group_move_to', NULL, TRUE, array( 'options' => $groupOptions, 'parse' => 'normal' ) ) );
				$form->hiddenValues['wasConfirmed']	= 1;
				if ( $values = $form->values() )
				{
					\IPS\Db::i()->update( 'core_members', array( 'member_group_id' => $values['delete_group_move_to'] ), array( 'member_group_id=?', $group->g_id ) );
					\IPS\Member::clearCreateMenu();
				}
				else
				{
					\IPS\Output::i()->output = $form;
					return;
				}
			}
			else
			{
				/* Make sure the user confirmed the deletion */
				\IPS\Request::i()->confirmedDelete();
			}

			/* remove this from members secondary groups column */
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_members', \IPS\Db::i()->findInSet( 'mgroup_others', array( $group->g_id ) ) ), 'IPS\Member') AS $member )
			{
				$secondaryGroups= $member->mgroup_others ? explode( ',', $member->mgroup_others ) : array();

				foreach ( $secondaryGroups as $id => $secGroup )
				{
					if (  $secGroup == $group->g_id )
					{
						unset( $secondaryGroups[$id] );
					}
				}

				\IPS\Db::i()->update( 'core_members', array( 'mgroup_others' => implode( ',', array_filter( $secondaryGroups ) ) ), array( 'member_id=?', $member->member_id ) );
			}

			$name = $group->name;
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $name );

			\IPS\Session::i()->log( 'acplog__groups_deleted', array( $name => FALSE ) );
			$group->delete();
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C108/2', 404, '' );
		}
		catch( \InvalidArgumentException $e )
		{
			\IPS\Output::i()->error( 'cannot_delete_protected_group', '1C108/4', 403, '' );
		}
		
		/* And redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=groups" ) );
	}
}
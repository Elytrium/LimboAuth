<?php
/**
 * @brief		moderators
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Apr 2013
 */

namespace IPS\core\modules\admin\staff;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * moderators
 */
class _moderators extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'members/restrictions.css', 'core', 'admin' ) );

		return parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_moderators', \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators' ) );

		/* Columns */
		$table->langPrefix	= 'moderators_';
		$table->selects		= array( 'perms', 'id', 'type', 'updated' );
		$table->joins = array(
			array( 'select' => "IF(core_moderators.type= 'g', w.word_custom, m.name) as name", 'from' => array( 'core_members', 'm' ), 'where' => "m.member_id=core_moderators.id AND core_moderators.type='m'" ),
			array( 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=CONCAT( 'core_group_', core_moderators.id ) AND core_moderators.type='g' AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
		);
		$table->include = array( 'name', 'updated', 'perms' );
		$table->parsers = array(
			'name'		=> function( $val, $row )
			{
				$return	= \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( $row['type'] === 'g' ? 'group' : 'member', array( 'ipsBadge', 'ipsBadge_neutral', 'ipsBadge_label' ) );
				try
				{
					$name = empty( $row['name'] ) ? \IPS\Member::loggedIn()->language()->addToStack('deleted_member') : htmlentities( $row['name'], ENT_DISALLOWED, 'UTF-8', FALSE );
					$return	.= ( $row['type'] === 'g' ) ? \IPS\Member\Group::load( $row['id'] )->formattedName : $name;
				}
				catch( \OutOfRangeException $e )
				{
					$return .= \IPS\Member::loggedIn()->language()->addToStack('deleted_group');
				}
				return $return;
			},
			'updated'	=> function( $val )
			{
				return ( $val ) ? \IPS\DateTime::ts( $val )->localeDate() : \IPS\Member::loggedIn()->language()->addToStack('never');
			},
			'perms' => function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'members' )->restrictionsLabel( $val );
			}
		);
		$table->mainColumn = 'name';
		$table->quickSearch = array( array( 'name', 'word_custom' ), 'name' );
		$table->noSort = array( 'perms' );
		
		/* Sorting */
		$table->sortBy = $table->sortBy ?: 'updated';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		/* Buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_member' ) or \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_group' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'add'	=> array(
					'primary' => TRUE,
					'icon'	=> 'plus',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=add' ),
					'title'	=> 'add_moderator',
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('add_moderator') )
				),
			);
		}
		$table->rowButtons = function( $row )
		{
			$buttons = array(
				'edit'	=> array(
					'icon'	=> 'pencil',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=staff&controller=moderators&do=edit&id={$row['id']}&type={$row['type']}" ),
					'title'	=> 'edit',
					'class'	=> '',
				),
				'delete'	=> array(
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=staff&controller=moderators&do=delete&id={$row['id']}&type={$row['type']}" ),
					'title'	=> 'delete',
					'data'	=> array( 'delete' => '' ),
				)
			);
			
			if ( $row['type'] === 'm' )
			{
				if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_edit_member' ) )
				{
					unset( $buttons['edit'] );
				}
				if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_delete_member' ) )
				{
					unset( $buttons['delete'] );
				}
			}
			else
			{
				if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_edit_group' ) )
				{
					unset( $buttons['edit'] );
				}
				if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_delete_group' ) )
				{
					unset( $buttons['delete'] );
				}
			}

			return $buttons;
		};
		
		/* Buttons for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_moderatorlogs' ) )
		{
			\IPS\Output::i()->sidebar['actions']['actionLogs'] = array(
					'title'		=> 'modlogs',
					'icon'		=> 'search',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=actionLogs' ),
			);
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('moderators');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Add
	 *
	 * @return	void
	 */
	protected function add()
	{
		$form = new \IPS\Helpers\Form();
				
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_member' ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_group' ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'moderators_type', NULL, TRUE, array( 'options' => array( 'g' => 'group', 'm' => 'member' ), 'toggles' => array( 'g' => array( 'moderators_group' ), 'm' => array( 'moderators_member' ) ) ) ) );
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_group' ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'moderators_group', NULL, FALSE, array( 'options' => \IPS\Member\Group::groups( TRUE, FALSE ), 'parse' => 'normal' ), NULL, NULL, NULL, 'moderators_group' ) );
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_member' ) )
		{
			$form->add( new \IPS\Helpers\Form\Member( 'moderators_member', NULL, ( \IPS\Request::i()->moderators_type === 'member' ), array(), NULL, NULL, NULL, 'moderators_member' ) );
		}
		
		if ( $values = $form->values() )
		{		
			$rowId = NULL;

			if( !isset( $values['moderators_type'] ) )
			{
				$values['moderators_type'] = isset( $values['moderators_group'] ) ? 'g' : 'm';
			}
			
			if ( $values['moderators_type'] === 'g' or !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'moderators_add_member' ) )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_add_group' );
				$rowId = $values['moderators_group'];
			}
			elseif ( $values['moderators_member'] )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_add_member' );
				$rowId = $values['moderators_member']->member_id;
			}

			if ( $rowId !== NULL )
			{
				try
				{
					$current = \IPS\Db::i()->select( '*', 'core_moderators', array( "id=? AND type=?", $rowId, $values['moderators_type'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					$current	= array();
				}

				if ( !\count( $current ) )
				{
					$current = array(
						'id'		=> $rowId,
						'type'		=> $values['moderators_type'],
						'perms'		=> '*',
						'updated'	=> time()
					);
					
					\IPS\Db::i()->insert( 'core_moderators', $current );
					
					foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE ) as $k => $ext )
					{
						$ext->onChange( $current, $values );
					}

					if( $values['moderators_type'] == 'g' )
					{
						$logValue = array( 'core_group_' . $values['moderators_group'] => TRUE );
					}
					else
					{
						$logValue = array( $values['moderators_member']->name => FALSE );
					}

					\IPS\Session::i()->log( 'acplog__moderator_created', $logValue );

					unset (\IPS\Data\Store::i()->moderators);
				}

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=staff&controller=moderators" ) );
			}
		}

		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('add_moderator');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'add_moderator', $form, FALSE );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_moderators', array( "id=? AND type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C118/2', 404, '' );
		}

		/* Check acp restrictions */
		if ( $current['type'] === 'm' )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_edit_member' );
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_edit_group' );
		}

		/* Load */
		try
		{
			$_name = ( $current['type'] === 'm' ) ? \IPS\Member::load( $current['id'] )->name : \IPS\Member\Group::load( $current['id'] )->name;
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C118/2', 404, '' );
		}

		$currentPermissions = ( $current['perms'] === '*' ) ? '*' : ( $current['perms'] ? json_decode( $current['perms'], TRUE ) : array() );
				
		/* Define content field toggles */
		$toggles = array( 'view_future' => array(), 'future_publish' => array(), 'pin' => array(), 'unpin' => array(), 'feature' => array(), 'unfeature' => array(), 'edit' => array(), 'hide' => array(), 'unhide' => array(), 'view_hidden' => array(), 'move' => array(), 'lock' => array(), 'unlock' => array(), 'reply_to_locked' => array(), 'delete' => array(), 'split_merge' => array(), 'feature_comments' => array(), 'unfeature_comments' => array(), 'add_item_message' => array(), 'edit_item_message' => array(), 'delete_item_message' => array(), 'toggle_item_moderation' => array() );
		foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE ) as $k => $ext )
		{
			if ( $ext instanceof \IPS\Content\ModeratorPermissions )
			{
				foreach ( $ext->actions as $s )
				{
					$class = $ext::$class;
					$toggles[ $s ][] = "can_{$s}_{$class::$title}";
				}
				
				if ( isset( $class::$commentClass ) )
				{
					foreach ( $ext->commentActions as $s )
					{
						$commentClass = $class::$commentClass;
						$toggles[ $s ][] = "can_{$s}_{$commentClass::$title}";
					}
				}
				
				if ( isset( $class::$reviewClass ) )
				{
					foreach ( $ext->reviewActions as $s )
					{
						$reviewClass = $class::$reviewClass;
						$toggles[ $s ][] = "can_{$s}_{$reviewClass::$title}";
					}
				}
			}
		}

		/* We need to remember which keys are 'nodes' so we can adjust values upon submit */
		$nodeFields = array();
		
		/* Build */
		$form = new \IPS\Helpers\Form;

		/* Add the restricted/unrestricted option first */
		$form->add(
			new \IPS\Helpers\Form\Radio( 'mod_use_restrictions', ( $currentPermissions === '*' ) ? 'no' : 'yes', TRUE, array( 'options' => array( 'no' => 'mod_all_permissions', 'yes' => 'mod_restricted' ), 'toggles' => array( 'yes' => array( 'permission_form_wrapper' ) ) ), NULL, NULL, NULL, 'use_restrictions_id' )
		);
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'mod_show_badge', ( $current ) ? $current['show_badge'] : TRUE, TRUE ) );
		
		$extensions = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE, 'core' ) as $k => $ext )
		{
			$extensions[ $k ] = $ext;
		}
		
		if ( isset( $extensions['core_General'] ) )
		{
			$meFirst = array( 'core_General' => $extensions['core_General'] );
			unset( $extensions['core_General'] );
			$extensions = $meFirst + $extensions;
		}
		
		foreach( $extensions as $k => $ext )
		{
			$form->addTab( 'modperms__' . $k );
						
			foreach ( $ext->getPermissions( $toggles ) as $name => $data )
			{
				/* Class */
				$type = \is_array( $data ) ? $data[0] : $data;
				$class = '\IPS\Helpers\Form\\' . ( $type );

				/* Remember 'nodes' */
				if( $type == 'Node' )
				{
					$nodeFields[ $name ]	= $name;
				}
				
				/* Current Value */
				if ( $currentPermissions === '*' )
				{
					switch ( $type )
					{
						case 'YesNo':
							$currentValue = TRUE;
							break;
							
						case 'Number':
							$currentValue = -1;
							break;
						
						case 'Node':
							$currentValue = 0;
							break;
					}
				}
				else
				{
					$currentValue = ( isset( $currentPermissions[ $name ] ) ? $currentPermissions[ $name ] : NULL );

					/* We translate nodes to -1 so the moderator permissions merging works as expected allowing "all" to override individual node selections */
					if( $type == 'Node' AND $currentValue == -1 )
					{
						$currentValue = 0;
					}
				}
				
				/* Options */
				$options = \is_array( $data ) ? $data[1] : array();
				if ( $type === 'Number' )
				{
					$options['unlimited'] = -1;
				}
				
				/* Prefix/Suffix */
				$prefix = NULL;
				$suffix = NULL;
				if ( \is_array( $data ) )
				{
					if ( isset( $data[2] ) )
					{
						$prefix = $data[2];
					}
					if ( isset( $data[3] ) )
					{
						$suffix = $data[3];
					}
				}
				
				/* Add */
				$form->add( new $class( $name, $currentValue, FALSE, $options, NULL, $prefix, $suffix, $name ) );
			}
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Allow extensions an opportunity to inspect the values and make adjustments */
			foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE ) as $k => $ext )
			{
				if( method_exists( $ext, 'preSave' ) )
				{
					$ext->preSave( $values );
				}
			}

			if( $values['mod_use_restrictions'] == 'no' )
			{
				$permissions = '*';

				$changed = '*';
			}
			else
			{
				unset( $values['mod_use_restrictions'] );

				foreach ( $values as $k => $v )
				{
					/* For node fields, if the value is 0 translate it to -1 so mod permissions can merge properly */
					if( \in_array( $k, $nodeFields ) )
					{
						/* If nothing is checked we have '', but if 'all' is checked then the value is 0 */
						if( $v === 0 )
						{
							$v = -1;
							$values[ $k ] = $v;
						}
					}

					if ( \is_array( $v ) )
					{
						foreach ( $v as $l => $w )
						{
							if ( $w instanceof \IPS\Node\Model )
							{
								$values[ $k ][ $l ] = $w->_id;
							}
						}
					}
				}
				
				if ( $currentPermissions == '*' )
				{
					$changed = $values;
				}
				else
				{
					$changed = array();
					foreach ( $values as $k => $v )
					{
						if ( !isset( $currentPermissions[ $k ] ) or $currentPermissions[ $k ] != $v )
						{
							$changed[ $k ] = $v;
						}
					}
				}

				$permissions = json_encode( $values );
			}
			
			\IPS\Db::i()->update( 'core_moderators', array( 'perms' => $permissions, 'updated' => time(), 'show_badge' => $values['mod_show_badge'] ), array( array( "id=? AND type=?", $current['id'], $current['type'] ) ) );

			if( !( $currentPermissions == '*' AND $changed == '*' ) )
			{
				foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE ) as $k => $ext )
				{
					$ext->onChange( $current, $changed );
				}
			}

			if( $current['type'] == 'g' )
			{
				$logValue = array( 'core_group_' . $current['id'] => TRUE );
			}
			else
			{
				$logValue = array( \IPS\Member::load( $current['id'] )->name => FALSE );
			}

			\IPS\Session::i()->log( 'acplog__moderator_edited', $logValue );

			$currentPermissions = $values;

			unset( \IPS\Data\Store::i()->moderators );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators' ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->title		= $_name;
		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_members.js', 'core', 'admin' ) );
		\IPS\Output::i()->output 	.= $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'members' ), 'moderatorPermissions' ) );
	}

	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Load */
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_moderators', array( "id=? AND type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C118/4', 404, '' );
		}

		/* Check acp restrictions */
		if ( $current['type'] === 'm' )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_delete_member' );
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'moderators_delete_group' );
		}

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Delete */
		\IPS\Db::i()->delete( 'core_moderators', array( array( "id=? AND type=?", $current['id'], $current['type'] ) ) );
		foreach ( \IPS\Application::allExtensions( 'core', 'ModeratorPermissions', FALSE ) as $k => $ext )
		{
			$ext->onDelete( $current );
		}

		unset (\IPS\Data\Store::i()->moderators);
		
		/* Log and redirect */
		if( $current['type'] == 'g' )
		{
			try
			{
				$name = 'core_group_' . $current['id'];
			}
			catch( \OutOfRangeException $e )
			{
				$name = 'deleted_group';
			}

			$logValue = array( $name => TRUE );
		}
		else
		{
			$member = \IPS\Member::load( $current['id'] );

			if( $member->member_id )
			{
				$logValue = array( $member->name => FALSE );
			}
			else
			{
				$logValue = array( 'deleted_member' => TRUE );
			}
		}

		\IPS\Session::i()->log( 'acplog__moderator_deleted', $logValue );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators' ) );
	}
	
	/**
	 * Action Logs
	 *
	 * @return	void
	 */
	protected function actionLogs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_moderatorlogs' );
	
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_moderator_logs', \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=actionLogs' ) );
		$table->langPrefix = 'modlogs_';
		$table->include = array( 'member_id', 'action', 'ip_address', 'ctime' );
		$table->mainColumn = 'action';
		$table->parsers = array(
				'member_id'	=> function( $val, $row )
				{
					$member = \IPS\Member::load( $val );
					if ( $member->member_id )
					{
						return htmlentities( \IPS\Member::load( $val )->name, ENT_DISALLOWED, 'UTF-8', FALSE );
					}
					else if ( $row['member_name'] != '' )
					{
						return htmlentities( $row['member_name'], ENT_DISALLOWED, 'UTF-8', FALSE );
					}
					else
					{
						// Member doesn't exist anymore, but we also haven't stored the name
						return '';
					}
				},
				'action'	=> function( $val, $row )
				{
					if ( $row['lang_key'] )
					{
						$langKey = $row['lang_key'];
						$params = array();
                        $note = json_decode( $row['note'], TRUE );
                        if ( !empty( $note ) )
                        {
                            foreach ($note as $k => $v)
                            {
                                $params[] = $v ? \IPS\Member::loggedIn()->language()->addToStack($k) : $k;
                            }
                        }
						return \IPS\Member::loggedIn()->language()->addToStack( $langKey, FALSE, array( 'sprintf' => $params ) );
					}
					else
					{
						return $row['note'];
					}
				},
				'ip_address'=> function( $val )
				{
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' ) )
					{
						return "<a href='" . \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}" ) . "'>{$val}</a>";
					}
					return $val;
				},
				'ctime'		=> function( $val )
				{
					return (string) \IPS\DateTime::ts( $val );
				}
		);
		$table->sortBy = $table->sortBy ?: 'ctime';
		$table->sortDirection = $table->sortDirection ?: 'desc';
	
		/* Search */
		$table->advancedSearch	= array(
				'member_id'			=> \IPS\Helpers\Table\SEARCH_MEMBER,
				'ip_address'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				'ctime'				=> \IPS\Helpers\Table\SEARCH_DATE_RANGE
		);

		/* Custom quick search function to search unicode entities in JSON encoded data */
		$table->quickSearch = function( $val )
		{
			$searchTerm = mb_strtolower( trim( \IPS\Request::i()->quicksearch ) );
			$jsonSearchTerm = str_replace( '\\', '\\\\\\', trim( json_encode( trim( \IPS\Request::i()->quicksearch ) ), '"' ) );

			return array(
				"(`note` LIKE CONCAT( '%', ?, '%' ) OR LOWER(`word_custom`) LIKE CONCAT( '%', ?, '%' ) OR LOWER(`word_default`) LIKE CONCAT( '%', ?, '%' ))",
				$jsonSearchTerm,
				$searchTerm,
				$searchTerm
			);
		};

		$table->joins = array(
			array( 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=lang_key AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
		);

		/* Add a button for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_moderatorlogs_prune' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
					'settings'	=> array(
							'title'		=> 'prunesettings',
							'icon'		=> 'cog',
							'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=actionLogSettings' ),
							'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
					),
			);
		}
	
		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=actionLogs' ), \IPS\Member::loggedIn()->language()->addToStack( 'modlogs' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'modlogs' );
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function actionLogSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_moderatorlogs_prune' );
	
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_moderator', \IPS\Settings::i()->prune_log_moderator, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_moderator' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__moderatorlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=moderators&do=actionLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('moderatorlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'moderatorlogssettings', $form, FALSE );
	}
}

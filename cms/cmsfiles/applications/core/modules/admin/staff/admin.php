<?php
/**
 * @brief		Admin CP Restrictions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Apr 2013
 */

namespace IPS\core\modules\admin\staff;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin CP Restrictions
 */
class _admin extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_manage' );
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
		$table = new \IPS\Helpers\Table\Db( 'core_admin_permission_rows', \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin' ) );
		
		/* Columns */
		$table->selects		= array( 'row_perm_cache', 'row_updated', 'row_id', 'row_id_type' );
		$table->langPrefix = 'acprestrictions_';
		$table->joins = array(
			array( 'select' => "IF(core_admin_permission_rows.row_id_type= 'group', w.word_custom, m.name) as name", 'from' => array( 'core_members', 'm' ), 'where' => "m.member_id=core_admin_permission_rows.row_id AND core_admin_permission_rows.row_id_type='member'" ),
			array( 'from' => array( 'core_sys_lang_words', 'w' ), 'where' => "w.word_key=CONCAT( 'core_group_', core_admin_permission_rows.row_id ) AND core_admin_permission_rows.row_id_type='group' AND w.lang_id=" . \IPS\Member::loggedIn()->language()->id )
		);
		$table->include = array( 'name', 'row_updated', 'row_perm_cache' );
		$table->parsers = array(
			'name'		=> function( $val, $row )
			{
				$return	= \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( $row['row_id_type'], array( 'ipsBadge', 'ipsBadge_neutral', 'ipsBadge_label' ) );
				try
				{
					$name = empty( $row['name'] ) ? \IPS\Member::loggedIn()->language()->addToStack('deleted_member') : htmlentities( $row['name'], ENT_DISALLOWED, 'UTF-8', FALSE );
					$return	.= ( $row['row_id_type'] === 'group' ) ? \IPS\Member\Group::load( $row['row_id'] )->formattedName : $name;
				}
				catch( \OutOfRangeException $e )
				{
					$return .= \IPS\Member::loggedIn()->language()->addToStack('deleted_group');
				}
				return $return;
			},
			'row_updated'	=> function( $val )
			{
				return ( $val ) ? \IPS\DateTime::ts( $val )->localeDate() : \IPS\Member::loggedIn()->language()->addToStack('never');
			},
			'row_perm_cache' => function( $val )
			{
				return \IPS\Theme::i()->getTemplate( 'members' )->restrictionsLabel( $val );
			}
		);
		$table->mainColumn = 'name';
		$table->quickSearch = array( array( 'name', 'word_custom' ), 'name' );
		$table->noSort = array( 'row_perm_cache' );
		
		/* Sorting */
		$table->sortBy = $table->sortBy ?: 'row_updated';
		$table->sortDirection = $table->sortDirection ?: 'desc';

		/* Buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_member' ) or \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_group' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'add'	=> array(
					'primary' => TRUE,
					'icon'	=> 'plus',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=add' ),
					'title'	=> 'acprestrictions_add',
					'data' => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_add') )
				),
			);
		}
		$table->rowButtons = function( $row )
		{
			$delKey = 'acprestrictions_delwarning_' . $row['row_id_type'];
			
			$buttons = array(
				'edit'	=> array(
					'icon'	=> 'pencil',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=staff&controller=admin&do=edit&id={$row['row_id']}&type={$row['row_id_type']}" ),
					'title'	=> 'edit',
					'class'	=> '',
				),
				'delete'	=> array(
					'icon'	=> 'times-circle',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=staff&controller=admin&do=delete&id={$row['row_id']}&type={$row['row_id_type']}" ),
					'title'	=> 'delete',
					'data'	=> array( 'delete' => '' ),
				)
			);
			
			if ( $row['row_id_type'] === 'member' )
			{
				if ( $row['row_id'] == \IPS\Member::loggedIn()->member_id )
				{
					return array();
				}
				else
				{
					if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_edit_member' ) )
					{
						unset( $buttons['edit'] );
					}
					if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_delete_member' ) )
					{
						unset( $buttons['delete'] );
					}
				}
			}
			else
			{
				if ( \IPS\Member::loggedIn()->inGroup( $row['row_id'] ) )
				{
					return array();
				}
				else
				{
					if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_edit_group' ) )
					{
						unset( $buttons['edit'] );
					}
					if ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_delete_group' ) )
					{
						unset( $buttons['delete'] );
					}
				}
			}
	
			return $buttons;
		};
		
		/* Buttons for logs */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_adminlogs' ) )
		{
			\IPS\Output::i()->sidebar['actions']['actionLogs'] = array(
				'title'		=> 'acplogs',
				'icon'		=> 'search',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=actionLogs' ),
			);
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_acploginlogs' ) )
		{
			\IPS\Output::i()->sidebar['actions']['loginLogs'] = array(
				'title'		=> 'adminloginlogs',
				'icon'		=> 'search',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=loginLogs' ),
			);
		}
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members' ) )
		{
			\IPS\Output::i()->sidebar['actions']['list_admins']	= array(
				'icon'	=> 'search',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&filter=members_filter_administrators' ),
				'title'	=> 'security_list_admins',
			);
		}
		
		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('acprestrictions');
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
				
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_member' ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_group' ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'acprestrictions_type', NULL, TRUE, array( 'options' => array( 'group' => 'group', 'member' => 'member' ), 'toggles' => array( 'group' => array( 'acprestrictions_group' ), 'member' => array( 'acprestrictions_member' ) ) ) ) );
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_member' ) )
		{
			$form->add( new \IPS\Helpers\Form\Select( 'acprestrictions_group', NULL, FALSE, array( 'options' => \IPS\Member\Group::groups( TRUE, FALSE ), 'parse' => 'normal' ), NULL, NULL, NULL, 'acprestrictions_group' ) );
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_group' ) )
		{
			$form->add( new \IPS\Helpers\Form\Member( 'acprestrictions_member', NULL, ( \IPS\Request::i()->acprestrictions_type === 'member' ), array( 'multiple' => 1 ), NULL, NULL, NULL, 'acprestrictions_member' ) );
		}
		
		if ( $values = $form->values() )
		{
			$rowId = NULL;

			if ( $values['acprestrictions_type'] === 'group' or !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_add_member' ) )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_add_group' );
				if ( \IPS\Member::loggedIn()->inGroup( $values['acprestrictions_group'] ) )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_noself');
				}
				else
				{
					$rowId = $values['acprestrictions_group'];
				}
			}
			elseif ( $values['acprestrictions_member'] )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_add_member' );
				if ( $values['acprestrictions_member']->member_id === \IPS\Member::loggedIn()->member_id )
				{
					$form->error = \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_noself');
				}
				else
				{
					$rowId = $values['acprestrictions_member']->member_id;
				}
			}

			if ( $rowId !== NULL )
			{
				$current = \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( "row_id=? AND row_id_type=?", $rowId, $values['acprestrictions_type'] ) );

				if ( !\count( $current ) )
				{
					$current = array(
						'row_id'			=> $rowId,
						'row_id_type'		=> $values['acprestrictions_type'],
						'row_perm_cache'	=> '*',
						'row_updated'		=> time()
					);
					\IPS\Db::i()->insert( 'core_admin_permission_rows', $current );

					if( $values['acprestrictions_type'] == 'group' )
					{
						$logValue = array( 'core_group_' . $values['acprestrictions_group'] => TRUE );
					}
					else
					{
						$logValue = array( $values['acprestrictions_member']->name => FALSE );
					}

					\IPS\Session::i()->log( 'acplog__acpr_created', $logValue );
				}
				else
				{
					$current	= $current->first();
				}

				unset( \IPS\Data\Store::i()->administrators );

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=staff&controller=admin" ) );
			}
		}
		
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('acprestrictions_add');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'acprestrictions_add', $form, FALSE );
	}
	
	/**
	 * Edit
	 *
	 * @csrfChecked	Uses form helper 7 Oct 2019
	 * @return	void
	 */
	protected function edit()
	{
		try
		{
			/* Get record */
			$current = \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( "row_id=? AND row_id_type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C113/1', 404, '' );
		}
		
		/* Check permissions */
		if ( $current['row_id_type'] === 'member' )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_edit_member' );
			if ( $current['row_id'] == \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->error( 'acprestrictions_noself', '1C113/3', 403, '' );
			}
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_edit_group' );
			if ( $current['row_id_type'] === 'group' and \IPS\Member::loggedIn()->inGroup( $current['row_id'] ) )
			{
				\IPS\Output::i()->error( 'acprestrictions_noself', '1C113/4', 403, '' );
			}
		}
		
		/* Get available restrictions */
		$restrictions = array();
		foreach ( \IPS\Application::applications() as $app )
		{
			if ( !\IPS\CIC or !\in_array( "{$app->directory}", \IPS\Member::$cicBlockedAcpRestrictions ) )
			{			
				$restrictions['applications'][ $app->directory ] = $app->id;
				
				$_restrictions = array();
				$file = $app->getApplicationPath() . '/data/acprestrictions.json';

				if ( file_exists( $file ) )
				{
					$_restrictions = json_decode( file_get_contents( $file ), TRUE );
				}
				
				foreach ( $app->modules( 'admin' ) as $module )
				{
					if ( !$module->protected and ( !\IPS\CIC or !\in_array( "{$app->directory}.{$module->key}", \IPS\Member::$cicBlockedAcpRestrictions ) ) )
					{
						$restrictions['modules'][ $app->id ][ $module->key ] = $module->id;
						
						if ( isset( $_restrictions[ $module->key ] ) )
						{
							foreach ( $_restrictions[ $module->key ] as $group => $items )
							{
								foreach ( $items as $restrictionKey => $langKey )
								{
									if ( !\IPS\CIC or !\in_array( "{$app->directory}.{$module->key}.{$restrictionKey}", \IPS\Member::$cicBlockedAcpRestrictions ) )
									{
										$restrictions['items'][ $module->id ][ $group ][ $restrictionKey ] = $langKey;
									}
								}
							}
						}
					}
				}
			}
		}

		/* Display */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_members.js', 'core', 'admin' ) );
		\IPS\Output::i()->title	 = $current['row_id_type'] === 'group' ? \IPS\Member\Group::load( $current['row_id'] )->name : \IPS\Member::load( $current['row_id'] )->name;
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global' )->block( 'acprestrictions', \IPS\Theme::i()->getTemplate( 'members' )->acpRestrictions( $current['row_perm_cache'] === '*' ? '*' : json_decode( $current['row_perm_cache'], TRUE ), $restrictions, $current ) );
	}
	
	/**
	 * Save
	 *
	 * @return	void
	 */
	protected function save()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Get record */
		$current = \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( "row_id=? AND row_id_type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) )->first();
		if ( !$current )
		{
			\IPS\Output::i()->error( 'node_error', '2C113/2', 404, '' );
		}
		
		$permissions = ( \IPS\Request::i()->admin_use_restrictions == 'no' ) ? '*' : json_encode( ( \is_array( \IPS\Request::i()->r ) ) ? \IPS\Request::i()->r : array() );

		/* Save */
		\IPS\Db::i()->update( 'core_admin_permission_rows', array( 'row_perm_cache' => $permissions, 'row_updated' => time() ), array( "row_id=? AND row_id_type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) );

		unset( \IPS\Data\Store::i()->administrators );
		
		/* Log */
		if( $current['row_id_type'] == 'group' )
		{
			$logValue = array( 'core_group_' . $current['row_id'] => TRUE );
		}
		else
		{
			$logValue = array( \IPS\Member::load( $current['row_id'] )->name => FALSE );
		}

		\IPS\Session::i()->log( 'acplog__acpr_edited', $logValue );
		
		/* Boink */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin' ), 'saved' );
	}

	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		$current = \IPS\Db::i()->select( '*', 'core_admin_permission_rows', array( "row_id=? AND row_id_type=?", \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) )->first();

		if ( $current['row_id_type'] === 'member' )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_delete_member' );
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_delete_group' );
		}

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		if( $current['row_id_type'] == 'group' )
		{
			try
			{
				$name = 'core_group_' . $current['row_id'];
			}
			catch( \OutOfRangeException $e )
			{
				$name = 'deleted_group';
			}

			$logValue = array( $name => TRUE );
		}
		else
		{
			$member = \IPS\Member::load( $current['row_id'] );

			if( $member->member_id )
			{
				$logValue = array( $member->name => FALSE );
			}
			else
			{
				$logValue = array( 'deleted_member' => TRUE );
			}
		}

		\IPS\Session::i()->log( 'acplog__acpr_deleted', $logValue );

		\IPS\Db::i()->delete( 'core_admin_permission_rows', array( 'row_id=? AND row_id_type=?', \intval( \IPS\Request::i()->id ), \IPS\Request::i()->type ) );
		unset ( \IPS\Data\Store::i()->administrators );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin' ) );
	}
	
	/**
	 * Action Logs
	 *
	 * @return	void
	 */
	protected function actionLogs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_adminlogs' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_admin_logs', \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=actionLogs' ) );
		$table->langPrefix = 'acplogs_';
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
					foreach ( json_decode( $row['note'], TRUE ) as $k => $v )
					{
						$params[] = ( $v ? \IPS\Member::loggedIn()->language()->addToStack( $k ) : $k );
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
			'ctime'				=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
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
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_adminlogs_prune' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=actionLogSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}
		
		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=actionLogs' ), \IPS\Member::loggedIn()->language()->addToStack( 'acplogs' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('acplogs');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Prune Settings
	 *
	 * @return	void
	 */
	protected function actionLogSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_adminlogs_prune' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_admin', \IPS\Settings::i()->prune_log_admin, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_admin' ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__adminlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=actionLogs' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('adminlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'adminlogssettings', $form, FALSE );
	}
	
	/**
	 * Login Logs
	 *
	 * @return	void
	 */
	protected function loginLogs()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_acploginlogs' );
		
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_admin_login_logs', \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=loginLogs' ) );
		$table->joins[] = array( 'from' => 'core_members', 'where' => 'core_admin_login_logs.admin_username=core_members.email' );
		$table->langPrefix = 'adminloginlogs_';
		$table->sortBy	= $table->sortBy ?: 'admin_time';
		$table->sortDirection	= $table->sortDirection ?: 'DESC';
		$table->include = array( 'admin_username', 'admin_ip_address', 'admin_time', 'admin_success' );
				
		/* Search */
		$table->quickSearch		= 'admin_username';
		$table->advancedSearch	= array(
			'admin_username'	=> array(
				\IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
				array(),
				function( $val ){
					if( !empty( $val ) )
					{
						return array( "(core_members.name LIKE CONCAT(?, '%') OR admin_username LIKE CONCAT(?,'%'))", $val, $val );
					}
				}
			),
			'admin_ip_address'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'admin_time' => \IPS\Helpers\Table\SEARCH_DATE_RANGE
		);
		
		/* Filters */
		$table->filters = array(
			'adminloginlogs_successful'		=> 'admin_success = 1',
			'adminloginlogs_unsuccessful'	=> 'admin_success = 0',
		);
		
		/* Custom parsers */
		$table->parsers = array(
			'admin_username' => function( $val, $row )
			{
				$member = \IPS\Member::constructFromData( $row );
				if( $member->member_id )
				{
					return "<a href='{$member->acpUrl()}'>" . htmlentities( $member->name, ENT_DISALLOWED, 'UTF-8', FALSE ) . "</a>";
				}
				return $val;
			},
			'admin_time'	=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val );
			},
			'admin_success'	=> function( $val, $row )
			{
				return ( $val ) ? "<i class='fa fa-check'></i>" : "<i class='fa fa-times'></i>";
			},
		);
				
		/* Add a button for settings */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'staff', 'restrictions_acploginlogs_prune' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'settings'	=> array(
					'title'		=> 'prunesettings',
					'icon'		=> 'cog',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=loginLogsSettings' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('prunesettings') )
				),
			);
		}
		
		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin&do=loginLogs' ), \IPS\Member::loggedIn()->language()->addToStack( 'adminloginlogs' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('adminloginlogs');
		\IPS\Output::i()->output	= (string) $table;
	}
	
	/**
	 * Login Log Settings
	 *
	 * @return	void
	 */
	protected function loginLogsSettings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'restrictions_acploginlogs_prune' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Interval( 'prune_log_adminlogin', \IPS\Settings::i()->prune_log_adminlogin, FALSE, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'never' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('after'), NULL, 'prune_log_adminlogin' ) );
	
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			\IPS\Session::i()->log( 'acplog__adminloginlog_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=staff&controller=admin' ), 'saved' );
		}
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('adminloginlogssettings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'adminloginlogssettings', $form, FALSE );
	}
}
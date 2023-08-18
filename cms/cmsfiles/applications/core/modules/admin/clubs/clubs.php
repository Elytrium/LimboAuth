<?php
/**
 * @brief		Clubs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Feb 2017
 */

namespace IPS\core\modules\admin\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Clubs
 */
class _clubs extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{			
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_clubs_clubs');
		
		if ( \IPS\Settings::i()->clubs )
		{			
			/* Create the table */
			$table = new \IPS\Helpers\Table\Db( 'core_clubs', \IPS\Http\Url::internal( 'app=core&module=clubs&controller=clubs' ) );
			$table->include = array( 'name', 'type', 'members', 'owner', 'created' );
			$table->langPrefix = 'club_';
			$table->parsers = array(
				'name'	=> function( $value, $row )
				{
					return \IPS\Theme::i()->getTemplate('clubs')->name( $value, $row );
				},
				'type'	=> function( $value ) {
					return \IPS\Theme::i()->getTemplate('clubs')->privacy( $value );
				},
				'created'	=> function( $value ) {
					return \IPS\DateTime::ts( $value );
				},
				'members'	=> function( $value, $row ) {
					if ( $row['type'] !== \IPS\Member\Club::TYPE_PUBLIC )
					{
						$link = \IPS\Http\Url::internal( "app=core&module=clubs&controller=view&id={$row['id']}&do=members", 'front', 'clubs_view', array( \IPS\Http\Url\Friendly::seoTitle( $row['name'] ) ) );
						return \IPS\Theme::i()->getTemplate('clubs')->members( $value, $link );
					}
					return '';
				},
				'owner'	=> function( $value ) {
					return \IPS\Theme::i()->getTemplate('clubs')->owner( \IPS\Member::load( $value ) );
				},
				'_highlight' => function( $row ) {
					if ( !$row['approved'] )
					{
						return 'ipsModerated';
					}
					return NULL;
				}
			);
			$table->noSort = array( 'privacy', 'owner' );
			$table->sortBy = $table->sortBy ?: 'members';
			$table->quickSearch = 'name';
			$table->advancedSearch = array(
				'name'	=> \IPS\Helpers\Table\SEARCH_QUERY_TEXT,
				'members' => \IPS\Helpers\Table\SEARCH_NUMERIC,
				'owner'	=> \IPS\Helpers\Table\SEARCH_MEMBER,
				'created'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			);
			if ( \IPS\Settings::i()->clubs_require_approval )
			{
				$table->filters = array(
					'pending_approval' => 'approved=0'
				);
				$table->advancedSearch['type'] = array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => array(
					\IPS\Member\Club::TYPE_PUBLIC	=> 'club_type_' . \IPS\Member\Club::TYPE_PUBLIC,
					\IPS\Member\Club::TYPE_OPEN	=> 'club_type_' . \IPS\Member\Club::TYPE_OPEN,
					\IPS\Member\Club::TYPE_CLOSED	=> 'club_type_' . \IPS\Member\Club::TYPE_CLOSED,
					\IPS\Member\Club::TYPE_PRIVATE	=> 'club_type_' . \IPS\Member\Club::TYPE_PRIVATE,
					\IPS\Member\Club::TYPE_READONLY	=> 'club_type_' . \IPS\Member\Club::TYPE_READONLY,
				), 'multiple' => TRUE ) );
			}
			else
			{
				$table->filters = array(
					'club_type_' . \IPS\Member\Club::TYPE_PUBLIC	=> array( 'type=?', \IPS\Member\Club::TYPE_PUBLIC ),
					'club_type_' . \IPS\Member\Club::TYPE_OPEN		=> array( 'type=?', \IPS\Member\Club::TYPE_OPEN ),
					'club_type_' . \IPS\Member\Club::TYPE_CLOSED	=> array( 'type=?', \IPS\Member\Club::TYPE_CLOSED ),
					'club_type_' . \IPS\Member\Club::TYPE_PRIVATE	=> array( 'type=?', \IPS\Member\Club::TYPE_PRIVATE ),
					'club_type_' . \IPS\Member\Club::TYPE_READONLY	=> array( 'type=?', \IPS\Member\Club::TYPE_READONLY )
				);
			}
			$table->rowButtons = function( $row ) {
				$return = array();
				if ( !$row['approved'] )
				{
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_edit' ) )
					{
						$return['approve']	= array(
							'title'	=> 'approve',
							'icon'	=> 'check',
							'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=approve&id={$row['id']}")->csrf()
						);
					}
					if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_delete' ) )
					{
						$return['delete'] = array(
							'title'	=> 'delete',
							'icon'	=> 'times',
							'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=delete&id={$row['id']}"),
							'data'	=> \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_node_map', array( 'club_id=?', $row['id'] ) )->first() ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ) : array( 'delete' => '' )
						);
					}
				}
				$return['open']	= array(
					'title'	=> 'view',
					'icon'	=> 'search',
					'link'	=> \IPS\Http\Url::internal( "app=core&module=clubs&controller=view&id={$row['id']}", 'front', 'clubs_view', array( \IPS\Http\Url\Friendly::seoTitle( $row['name'] ) ) ),
					'target'=> '_blank'
				);
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_edit' ) )
				{
					$return['edit']	= array(
						'title'	=> 'edit',
						'icon'	=> 'pencil',
						'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=edit&id={$row['id']}")
					);
				}
				if ( !isset( $return['delete'] ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_delete' ) )
				{
					$return['delete'] = array(
						'title'	=> 'delete',
						'icon'	=> 'times-circle',
						'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=delete&id={$row['id']}"),
						'data'	=> \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs_node_map', array( 'club_id=?', $row['id'] ) )->first() ? array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ) : array( 'delete' => '' )
					);
				}
				return $return;
			};
				
			/* Display */
			\IPS\Output::i()->output = (string) $table;
		}
		else
		{
			$availableTypes = array();
			foreach ( \IPS\Member\Club::availableNodeTypes( NULL ) as $class )
			{
				$availableTypes[] = \IPS\Member::loggedIn()->language()->addToStack( $class::clubAcpTitle() );
			}
			
			$availableTypes = \IPS\Member::loggedIn()->language()->formatList( $availableTypes );
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->disabled( $availableTypes );
		}
	}
	
	/**
	 * Enable
	 *
	 * @return	void
	 */
	protected function enable()
	{	
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_settings_manage' );
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Settings::i()->changeValues( array( 'clubs' => true ) );

		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'clubrebuild' ) );
		
		\IPS\Session::i()->log( 'acplog__club_settings' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=clubs&controller=clubs') );
	}
	
	/**
	 * Edit
	 *
	 * @csrfChecked	Uses node form() 7 Oct 2019
	 * @return	void
	 */
	protected function edit()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_edit' );
		
		/* Load Club */
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/1', 404, '' );
		}
		$editUrl = \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=edit&id={$club->id}");
		
		/* Tabs */
		$tabs = array( 'settings' => 'settings' );
		foreach ( \IPS\Member\Club::availableNodeTypes( \IPS\Member::loggedIn() ) as $class )
		{
			$tabs[ str_replace( '\\', '-', preg_replace( '/^IPS\\\/', '', $class ) ) ] = $class::clubAcpTitle();
		}
		$activeTab = ( isset( \IPS\Request::i()->tab ) and array_key_exists( \IPS\Request::i()->tab, $tabs ) ) ? \IPS\Request::i()->tab : 'settings';
		
		/* Settings */
		if ( $activeTab === 'settings' )
		{
			$form = $club->form( TRUE );

			if( $values = $form->values() )
			{
				$club->skipCloneDuplication = TRUE;
				$old = clone $club;

				$club->processForm( $values, TRUE, FALSE, NULL );

				$changes = $club::renewalChanges( $old, $club );

				if ( !empty( $changes ) )
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'product_change_blurb', array(
						'product_change_blurb_existing'	=> \IPS\Http\Url::internal( "app=core&module=clubs&controller=clubs&do=updateExisting&id={$club->id}" )->setQueryString( 'changes', json_encode( $changes ) )->csrf(),
						'product_change_blurb_new'		=> \IPS\Http\Url::internal( "app=core&module=clubs&controller=clubs" ),
					) );

					return;
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs"), "saved" );
				}
			}
			else
			{
				$activeTabContents = (string) $form;
			}
		}
		
		/* Node List */
		else
		{
			$nodeClass = 'IPS\\' . str_replace( '-', '\\', \IPS\Request::i()->tab );
			$this->nodeClass = $nodeClass;
			$this->club = $club;
			
			$tree = new \IPS\Helpers\Tree\Tree( $editUrl->setQueryString( 'tab', \IPS\Request::i()->tab ), 'x', array( $this, '_getNodeRows' ), array( $this, '_getNodeRow' ), function() { return NULL; }, function() { return array(); }, function() use( $nodeClass ) {
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_add_nodes' ) )
				{
					return array(
						'create'	=> array(
							'title'		=> 'add',
							'icon'		=> 'plus',
							'link'		=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=nodeForm&club={$this->club->id}&nodeClass={$nodeClass}" ),
							'data'	=> array(
								'ipsDialog'			=> '',
								'ipsDialog-title'	=> \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::clubAcpTitle() )
							)
						)
					);
				}
				return array();
			} );
			
			$activeTabContents = $tree;
		}
		
		/* Output */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = $activeTabContents;
		}
		else
		{
			\IPS\Output::i()->title = $club->name;
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->tabs( $tabs, $activeTab, $activeTabContents, $editUrl );
		}
	}
	
	/**
	 * Approve
	 *
	 * @return	void
	 */
	protected function approve()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_edit' );
		\IPS\Session::i()->csrfCheck();
		
		/* Load Club */
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/8', 404, '' );
		}
		
		/* Approve */
		$club->approved = TRUE;
		$club->save();
		
		$club->onApprove();
			
		/* Redirect */
		$target = \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs");
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_clubs', array( 'approved=0 AND id!=?', $club->id ) )->first() )
		{
			$target = $target->setQueryString( 'filter', 'pending_approval' );
		}
		\IPS\Session::i()->modLog( 'acplog__club_approved', array( $club->name => FALSE ) );
		\IPS\Output::i()->redirect( $target, "approved" );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_delete' );
		
		/* Load Club */
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/7', 404, '' );
		}
		
		/* Ask what to do with each node */
		$nodes = $club->nodes();
		if ( \count( $nodes ) )
		{
			$form = new \IPS\Helpers\Form( 'form', 'delete' );
			$form->addMessage( 'club_delete_blurb' );
			foreach ( $nodes as $data )
			{
				try
				{
					$field = new \IPS\Helpers\Form\Node( 'node_' . str_replace( '\\', '-', preg_replace( '/^IPS\\\/', '', $data['node_class'] ) ) . '_' . $data['node_id'], 0, TRUE, array( 'class' => $data['node_class'], 'disabled' => array( $data['node_id'] ), 'disabledLang' => 'node_move_delete', 'zeroVal' => 'node_delete_content', 'subnodes' => FALSE, 'permissionCheck' => function( $node )
					{
						return array_key_exists( 'add', $node->permissionTypes() );
					}, 'clubs' => TRUE ) );
					$field->label = htmlspecialchars( $data['name'] );
					$form->add( $field );
				}
				catch ( \Exception $e ) {}
			}
			if ( $values = $form->values() )
			{
				foreach ( $values as $k => $v )
				{
					$exploded = explode( '_', $k );
					$nodeClass = 'IPS\\' . str_replace( '-', '\\', $exploded[1] );
					
					try
					{
						$node = $nodeClass::load( $exploded[2] );
						
						$nodesToQueue = array( $node );
						$nodeToCheck = $node;
						while( $nodeToCheck->hasChildren( NULL ) )
						{
							foreach ( $nodeToCheck->children( NULL ) as $nodeToCheck )
							{
								$nodesToQueue[] = $nodeToCheck;
							}
						}
						
						foreach ( $nodesToQueue as $_node )
						{
							$_values = array();

							if ( $v )
							{
								$_values['node_move_children'] = $v;
								$_values['node_move_content'] = $v;
							}

							$_node->deleteOrMoveFormSubmit( $_values );
						}
					}
					catch ( \Exception $e ) {}
				}
			}
			else
			{
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		else
		{
			\IPS\Request::i()->confirmedDelete();
		}
		
		/* Delete it */
		\IPS\Session::i()->log( 'acplog__club_deleted', array( $club->name => FALSE ) );
		$club->delete();
		\IPS\Db::i()->delete( 'core_clubs_memberships', array( 'club_id=?', $club->id ) );
		\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'club_id=?', $club->id ) );
		\IPS\Db::i()->delete( 'core_clubs_fieldvalues', array( 'club_id=?', $club->id ) );

		/* Boink */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( "OK" );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=clubs&controller=clubs'), 'deleted' );
		}
	}
	
	/**
	 * Edit Node
	 *
	 * @return	void
	 */
	protected function nodeForm()
	{
		/* Load Club */
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->club );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/2', 404, '' );
		}
		
		/* Load Node */
		$nodeClass = \IPS\Request::i()->nodeClass;
		if ( isset( \IPS\Request::i()->nodeId ) )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_edit_nodes' );
			
			try
			{
				$node = $nodeClass::load( \IPS\Request::i()->nodeId );
				$nodeClub = $node->club();
				if ( !$nodeClub or $nodeClub->id !== $club->id )
				{
					throw new \Exception;
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C352/3', 404, '' );
			}
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_add_nodes' );
			$node = new $nodeClass;
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$node->clubForm( $form, $club );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$node->saveClubForm( $club, $values );
			\IPS\Session::i()->log( 'acplog__node_edited_club', array( $nodeClass::$nodeTitle => TRUE, $node->titleForLog() => FALSE, $club->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=clubs&controller=clubs&do=edit&id={$club->id}&tab=" . str_replace( '\\', '-', preg_replace( '/^IPS\\\/', '', $nodeClass ) ) ) );
		}
		
		/* Display */
		\IPS\Output::i()->title = $node->_title;
		\IPS\Output::i()->output = $form;		
	}
	
	/**
	 * Delete Node
	 *
	 * @return	void
	 */
	protected function deleteNode()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'clubs_delete_nodes' );
		
		/* Load Club */
		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->club );
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/4', 404, '' );
		}
		
		/* Load Node */
		$nodeClass = \IPS\Request::i()->nodeClass;
		try
		{
			$node = $nodeClass::load( \IPS\Request::i()->nodeId );
			$nodeClub = $node->club();
			if ( !$nodeClub or $nodeClub->id !== $club->id )
			{
				throw new \Exception;
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C352/5', 404, '' );
		}
		$targetUrl = \IPS\Http\Url::internal( "app=core&module=clubs&controller=clubs&do=edit&id={$club->id}&tab=" . str_replace( '\\', '-', preg_replace( '/^IPS\\\/', '', $nodeClass ) ) );
		
		/* Do we have any children or content? */
		if ( $node->hasChildren( NULL, NULL, TRUE ) or $node->showDeleteOrMoveForm() )
		{			
			$form = $node->deleteOrMoveForm( FALSE );
			if ( $values = $form->values() )
			{
				\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'club_id=? AND node_class=? AND node_id=?', $club->id, $nodeClass, $node->_id ) );
				$node->deleteOrMoveFormSubmit( $values );				
				\IPS\Output::i()->redirect( $targetUrl );
			}
			else
			{
				/* Show form */
				\IPS\Output::i()->output = $form;
				return;
			}
		}
		else
		{
			/* Make sure the user confirmed the deletion */
			\IPS\Request::i()->confirmedDelete();
		}
		
		/* Delete it */
		\IPS\Db::i()->delete( 'core_clubs_node_map', array( 'club_id=? AND node_class=? AND node_id=?', $club->id, $nodeClass, $node->_id ) );
		\IPS\Session::i()->log( 'acplog__node_deleted_club', array( $nodeClass::$nodeTitle => TRUE, $node->titleForLog() => FALSE, $club->name => FALSE ) );
		$node->delete();

		/* Boink */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( "OK" );
		}
		else
		{
			\IPS\Output::i()->redirect( $targetUrl );
		}
	}
	
	/**
	 * Get Node Rows
	 *
	 * @return	array
	 */
	public function _getNodeRows()
	{
		$nodeClass = $this->nodeClass;
		$rows = array();
		foreach( $nodeClass::roots( NULL, NULL, array( $nodeClass::$databasePrefix . $nodeClass::clubIdColumn() . '=?', $this->club->id ) ) as $node )
		{
			$rows[ $node->_id ] = $this->_getNodeRow( $node );
		}
		
		return $rows;
	}
	
	/**
	 * Get Node Row
	 *
	 * @param	mixed	$id		May be ID number (or key) or an \IPS\Node\Model object
	 * @param	bool	$root	Format this as the root node?
	 * @param	bool	$noSort	If TRUE, sort options will be disabled (used for search results)
	 * @return	string
	 */
	public function _getNodeRow( $id, $root=FALSE, $noSort=FALSE )
	{
		$nodeClass = $this->nodeClass;
		
		if ( $id instanceof \IPS\Node\Model )
		{
			$node = $id;
		}
		else
		{
			try
			{
				$node = $nodeClass::load( $id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C352/6', 404, '' );
			}
		}
		
		
		$buttons = array(
			'open'	=> array(
				'title'	=> 'view',
				'icon'	=> 'search',
				'link'	=> $node->url(),
				'target'=> '_blank',
			)
		);
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_edit_nodes' ) )
		{
			$buttons['edit'] = array(
				'title'	=> 'edit',
				'icon'	=> 'pencil',
				'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=nodeForm&club={$this->club->id}&nodeClass={$nodeClass}&nodeId={$node->_id}"),
				'data'	=> array(
					'ipsDialog'			=> '',
					'ipsDialog-title'	=> $node->_title
				)
			);
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'clubs', 'clubs_delete_nodes' ) )
		{
			$buttons['delete'] = array(
				'title'	=> 'delete',
				'icon'	=> 'times-circle',
				'link'	=> \IPS\Http\Url::internal("app=core&module=clubs&controller=clubs&do=deleteNode&club={$this->club->id}&nodeClass={$nodeClass}&nodeId={$node->_id}"),
				'data' 	=> ( $node->hasChildren( NULL, NULL, TRUE ) or $node->showDeleteOrMoveForm() ) ? array( 'ipsDialog' => '', 'ipsDialog-title' => $node->_title ) : array( 'delete' => '' ),
			);
		}
		
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
			NULL,
			$node->_id,
			$node->_title,
			FALSE,
			$buttons,
			$node->description
		);
	}

	/**
	 * Update Existing Purchases
	 *
	 * @return	void
	 */
	public function updateExisting()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			$club = \IPS\Member\Club::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'no_module_permission', '3C352/8', 403, '' );
		}

		$changes = json_decode( \IPS\Request::i()->changes, TRUE );

		\IPS\Task::queue( 'core', 'UpdateClubRenewals', array( 'changes' => $changes, 'club' => $club->id ), 5 );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=clubs&controller=clubs" ), 'saved' );
	}
}
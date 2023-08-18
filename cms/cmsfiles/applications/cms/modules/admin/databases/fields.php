<?php
/**
 * @brief		Fields Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		31 March 2014
 */

namespace IPS\cms\modules\admin\databases;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * fields
 */
class _fields extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\cms\Fields';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		$this->url = $this->url->setQueryString( array( 'database_id' => \IPS\Request::i()->database_id ) );
		
		$this->nodeClass = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;

		\IPS\Dispatcher::i()->checkAcpPermission( 'databases_use' );
		\IPS\Dispatcher::i()->checkAcpPermission( 'cms_fields_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* If we lose the database id because of a log in, do something more useful than an uncaught exception */
		if ( ! isset( \IPS\Request::i()->database_id ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases" ) );
		}
		
		parent::manage();
		
		$url = \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id  );
		
		$class = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
		
		/* Build fixed fields */
		$fixed	= array_merge( array( 'record_publish_date' => array(), 'record_expiry_date' => array(), 'record_allow_comments' => array(), 'record_comment_cutoff' => array(), 'record_image' => array() ), $class::fixedFieldPermissions() );

		/* Fixed fields */
		$fixedFields = new \IPS\Helpers\Tree\Tree(
			$url,
			\IPS\Member::loggedIn()->language()->addToStack('content_fields_fixed_title'),
			function() use ( $fixed, $url )
			{
				$rows = array();
				
				foreach( $fixed as $field => $data )
				{
					$description = ( $field === 'record_publish_date' ) ? \IPS\Member::loggedIn()->language()->addToStack( 'content_fields_fixed_record_publish_date_desc' ) : NULL;
					$rows[ $field ] = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row( $url, $field, \IPS\Member::loggedIn()->language()->addToStack( 'content_fields_fixed_'. $field ), FALSE, array(
						'permission'	=> array(
							'icon'		=> 'lock',
							'title'		=> 'permissions',
							'link'		=> $url->setQueryString( array( 'field' => $field, 'do' => 'fixedPermissions' ) ),
							'data'      => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'content_fields_fixed_'. $field ) )
						)
					), $description, NULL, NULL, NULL, ( empty( $data['visible'] ) ? FALSE : TRUE )  );
				}
				
				return $rows;
			},
			function( $key, $root=FALSE ) use ( $fixed, $url ) {},
			function() { return 0; },
			function() { return array(); },
			function() { return array(); },
			FALSE,
			TRUE,
			TRUE
		);

		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'databases' )->fieldsWrapper( $fixedFields );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('content_database_field_area', FALSE, array( 'sprintf' => array( \IPS\cms\Databases::load( \IPS\Request::i()->database_id)->_title ) ) );
	}
	
	/**
	 * Get Root Rows
	 *
	 * @return	array
	 */
	public function _getRoots()
	{
		$nodeClass = $this->nodeClass;
		$rows = array();
		
		foreach( $nodeClass::roots( NULL ) as $node )
		{
			if ( $node->database_id == \IPS\Request::i()->database_id )
			{
				$rows[ $node->_id ] = $this->_getRow( $node );
			}
		}

		return $rows;
	}

	/**
	 * Fixed field permissions
	 *
	 * @return void
	 */
	public function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		$class = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
		
		$class::setFixedFieldVisibility( \IPS\Request::i()->id, (boolean) \IPS\Request::i()->status );
		
		/* Redirect */
		if ( \IPS\Request::i()->status )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id . '&do=fixedPermissions&field=' . \IPS\Request::i()->id ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id ), 'saved' );
		}
	}

	/**
	 * Set this field as the record title
	 *
	 * @return void
	 */
	public function setAsTitle()
	{
		\IPS\Session::i()->csrfCheck();
		
		$class    = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->database_id );

		try
		{
			$field = $class::load( \IPS\Request::i()->id );

			if ( $field->canBeTitleField() )
			{
				$database->field_title = $field->id;
				$database->save();
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id ), 'saved' );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'cms_cannot_find_field', '2T255/1', 403, '' );
		}
	}

	/**
	 * Set this field as the record content
	 *
	 * @return void
	 */
	public function setAsContent()
	{
		\IPS\Session::i()->csrfCheck();
		
		$class    = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
		$database = \IPS\cms\Databases::load( \IPS\Request::i()->database_id );

		try
		{
			$field = $class::load( \IPS\Request::i()->id );

			if ( $field->canBeContentField() )
			{
				$database->field_content = $field->id;
				$database->save();
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id ), 'saved' );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'cms_cannot_find_field', '2T255/2', 403, '' );
		}
	}

	/**
	 * Fixed field permissions
	 * 
	 * @return void
	 */
	public function fixedPermissions()
	{
		$class = '\IPS\cms\Fields' . \IPS\Request::i()->database_id;
		$perms = $class::fixedFieldPermissions( \IPS\Request::i()->field );

		$permMap = array( 'view' => 'view', 'edit' => 2, 'add' => 3 );

		foreach( $permMap as $k => $v )
		{
			if ( ! isset( $perms[ 'perm_' . $v ] ) )
			{
				$perms[ 'perm_' . $v ] = NULL;
			}
		}

		/* Build Matrix */
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->manageable = FALSE;
		$matrix->langPrefix = 'content_perm_fixed_fields__';
		$matrix->columns = array(
				'label'		=> function( $key, $value, $data )
				{
					return $value;
				},
		);
		foreach ( $permMap as $k => $v )
		{
			$matrix->columns[ $k ] = function( $key, $value, $data ) use ( $perms, $k, $v )
			{
				$groupId = mb_substr( $key, 0, -( 2 + mb_strlen( $k ) ) );
				return new \IPS\Helpers\Form\Checkbox( $key, isset( $perms[ "perm_{$v}" ] ) and ( $perms[ "perm_{$v}" ] === '*' or \in_array( $groupId, explode( ',', $perms[ "perm_{$v}" ] ) ) ) );
			};
			$matrix->checkAlls[ $k ] = ( $perms[ "perm_{$v}" ] === '*' );
		}
		$matrix->checkAllRows = TRUE;
		
		$rows = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$rows[ $group->g_id ] = array(
					'label'	=> $group->name,
					'view'	=> TRUE,
			);
		}
		$matrix->rows = $rows;
		
		/* Handle submissions */
		if ( $values = $matrix->values() )
		{
			$_perms = array();
			$save   = array();
				
			/* Check for "all" checkboxes */
			foreach ( $permMap as $k => $v )
			{
				if ( isset( \IPS\Request::i()->__all[ $k ] ) )
				{
					$_perms[ $v ] = '*';
				}
				else
				{
					$_perms[ $v ] = array();
				}
			}
				
			/* Loop groups */
			foreach ( $values as $group => $perms )
			{
				foreach ( $permMap as $k => $v )
				{
					if ( isset( $perms[ $k ] ) and $perms[ $k ] and \is_array( $_perms[ $v ] ) )
					{
						$_perms[ $v ][] = $group;
					}
				}
			}
				
			/* Finalise */
			foreach ( $_perms as $k => $v )
			{
				$save[ "perm_{$k}" ] = \is_array( $v ) ? implode( ',', $v ) : $v;
			}
			
			$class::setFixedFieldPermissions( \IPS\Request::i()->field, $save );
			
			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=cms&module=databases&controller=fields&database_id=" . \IPS\Request::i()->database_id ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->output .= $matrix;
		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack('content_database_manage_fields');
	
	}
}
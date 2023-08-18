<?php
/**
 * @brief		Base API endpoint for Nodes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Apr 2017
 */

namespace IPS\Node\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base API endpoint for Nodes
 */
class _NodeController extends \IPS\Api\Controller
{
	/**
	 * List
	 *
	 * @param	array	$where	Extra WHERE clause
	 * @return	\IPS\Api\PaginatedResponse
	 */
	protected function _list( $where = array() )
	{
		$class = $this->class;
		
		if ( $this->member and \in_array( 'IPS\Node\Permissions', class_implements( $class ) ) )
		{
			$where[] = array( '(' . \IPS\Db::i()->findInSet( 'core_permission_index.perm_' . $class::$permissionMap['view'], $this->member->permissionArray() ) . ' OR ' . 'core_permission_index.perm_' . $class::$permissionMap['view'] . '=? )', '*' );
			if ( $class::$databaseColumnEnabledDisabled )
			{
				$where[] = array( $class::$databasePrefix . $class::$databaseColumnEnabledDisabled . '=1' );
			}
		}

		/* Exclude clubs? */
		if ( \IPS\IPS::classUsesTrait( $class, 'IPS\Content\ClubContainer' ) AND isset( \IPS\Request::i()->clubs ) AND !\IPS\Request::i()->clubs )
		{
			$where[] = array( $class::$databasePrefix . $class::clubIdColumn() . ' IS NULL' );
		}
		
		$select = \IPS\Db::i()->select( '*', $class::$databaseTable, $where, $class::$databaseColumnOrder ? $class::$databasePrefix . $class::$databaseColumnOrder . " asc" : NULL );

		/* Return permissions */
		if ( \in_array( 'IPS\Node\Permissions', class_implements( $class ) ) )
		{
			$select->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . $class::$databaseTable . "." . $class::$databasePrefix . $class::$databaseColumnId, $class::$permApp, $class::$permType ) );
		}

		/* Return */
		return new \IPS\Api\PaginatedResponse(
			200,
			$select,
			isset( \IPS\Request::i()->page ) ? \IPS\Request::i()->page : 1,
			$class,
			NULL,
			$this->member,
			isset( \IPS\Request::i()->perPage ) ? \IPS\Request::i()->perPage : NULL
		);
	}

	/**
	 * View
	 *
	 * @param	int	$id	ID Number
	 * @return	\IPS\Api\Response
	 */
	protected function _view( $id )
	{
		$class = $this->class;
		
		$node = $class::load( $id );
		if ( $this->member and !$node->can( 'view', $this->member ) )
		{
			throw new \OutOfRangeException;
		}
		
		return new \IPS\Api\Response( 200, $node->apiOutput( $this->member ) );
	}

	/**
	 * Delete
	 *
	 * @param	int			$id						ID Number
	 * @param	int|NULL	$deleteChildrenOrMove	-1 to delete all child nodes, or the new parent node ID to move the children to
	 * @throws	1S359/1	INVALID_ID		The node ID does not exist
	 * @throws	1S359/2	INVALID_TARGET	The target node cannot be deleted because the new parent node does not exist
	 * @throws	1S359/3	HAS_CHILDREN	The target node cannot be deleted because it has children (pass deleteChildrenOrMove in the request to specify how to handle the children)
	 * @return	\IPS\Api\Response
	 */
	protected function _delete( $id, $deleteChildrenOrMove = NULL )
	{
		$class = $this->class;

		try
		{
			$node = $class::load( $id );

			if ( $node->hasChildren( NULL, NULL, TRUE ) )
			{
				/* -1 means delete everything */
				if ( $deleteChildrenOrMove AND $deleteChildrenOrMove == -1 )
				{
					$node->deleteOrMoveFormSubmit( array( 'node_move_children' => FALSE ) );
				}
				else if ( $deleteChildrenOrMove )
				{
					try
					{
						$target = $class::load( $deleteChildrenOrMove );
						$node->deleteOrMoveFormSubmit( array( 'node_move_children' => TRUE, 'node_destination' => $target->_id ) );
					}
					catch ( \OutOfRangeException $e )
					{
						throw new \IPS\Api\Exception( 'INVALID_TARGET', '1S359/2', 404 );
					}
				}
				/* Or return an exception if no action was set */
				if ( !$deleteChildrenOrMove )
				{
					throw new \IPS\Api\Exception( 'HAS_CHILDREN', '1S359/3', 404 );
				}
			}
			else
			{
				$node->delete();
			}

			return new \IPS\Api\Response( 200, NULL );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\Exception( 'INVALID_ID', '1S359/1', 404 );
		}
	}

	/**
	 * Create or update node
	 *
	 * @param	\IPS\node\Model	$node				The node
	 * @return	\IPS\node\Model
	 */
	protected function _createOrUpdate( \IPS\Node\Model $node )
	{
		$node->save();

		if( $node instanceof \IPS\Node\Permissions AND isset( \IPS\Request::i()->permissions ) AND \IPS\Request::i()->permissions )
		{
			$insert = array(
				'app'			=> $node::$permApp,
				'perm_type'		=> $node::$permType,
				'perm_type_id'	=> $node->_id,
			);

			foreach( $node::$permissionMap as $key => $field )
			{
				if( isset( \IPS\Request::i()->permissions[ $key ] ) )
				{
					$insert[ 'perm_' . $field ] = \is_array( \IPS\Request::i()->permissions[ $key ] ) ? implode( ',', \IPS\Request::i()->permissions[ $key ] ) : \IPS\Request::i()->permissions[ $key ];
				}
			}

			$node->setPermissions( $insert );
		}

		/* Return */
		return $node;
	}

	/**
	 * Create
	 *
	 * @return	\IPS\Content\Node
	 */
	protected function _create()
	{
		$class = $this->class;

		/* Create item */
		$node = new $class;

		if( isset( $node::$databaseColumnOrder ) AND $node::$automaticPositionDetermination === TRUE )
		{
			$orderColumn = $node::$databaseColumnOrder;
			$node->$orderColumn = \IPS\Db::i()->select( 'MAX(' . $node::$databasePrefix . $orderColumn . ')', $node::$databaseTable  )->first() + 1;
		}

		$node->save();
		$node = $this->_createOrUpdate( $node );

		/* Output */
		return $node;
	}

	/**
	 * Returns the global available where condition for all nodes
	 *
	 * @param array|null $where Extra WHERE clause
	 * @return array
	 */
	protected function _globalWhere(array $where = NULL): array
	{
		$class = $this->class;

		$where = $where ?: [];
		$idField = $class::$databaseTable . '.' . $class::$databasePrefix . '.' . $class::$databaseColumnId;

		if ( isset( \IPS\Request::i()->ids ) )
		{
			$where[] = array( \IPS\Db::i()->in( $idField, array_map( 'intval', explode(',', \IPS\Request::i()->ids ) ) ) );
		}

		return $where;
	}
}
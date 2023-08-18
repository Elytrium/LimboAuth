<?php
/**
 * @brief		Number input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 Apr 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Text input class for Form Builder
 */
class _Node extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'url'				=> \IPS\Http\Url::internal(...)	// The URL that this form element will be displayed on
	 		'class'				=> '\IPS\core\Foo',				// The node class
	 		'permissionCheck'	=> 'add',						// If a permission key is provided, only nodes that the member has that permission for will be available. Alternatively, can be a callback to return if node can be selected.
	 		'showAllNodes'		=> FALSE,						// Normally all nodes are shown in the AdminCP, but only nodes the current user has permission to view (regardless of permissionCheck) are shown on the front end. This option forces all nodes to be returned even on the front end. Useful in areas like the widget manager where you may wish to select a block that guests can view but moderators cannot.
	 		'zeroVal'			=> 'none',						// If provided, a checkbox allowing you to receive a value of 0 will be shown with the label given. Default is NULL.
	 		'zeroValTogglesOn'	=> array( ... ),				// Element IDs to toggle on when the zero val checkbox is checked
	 		'zeroValTogglesOff'	=> array( ... ),				// Element IDs to toggle on when the zero val checkbox is unchecked
	 		'multiple'			=> TRUE,						// If multiple values are supported. Defaults to FALSE
	 		'subnodes'			=> TRUE,						// Controls if subnodes should be included. Defaults to TRUE
	 		'togglePerm'		=> 'edit',						// If a permission key is provided, nodes that have this permission will toggle the element IDs in toggleIds on/off
	 		'togglePermPBR'		=> FALSE,						// Determines value of $considerPostBeforeRegistering when performing toggle permission check
	 		'toggleIds'			=> array(),						// Element IDs to toggle on when a node with 'togglePerm' permission IS selected - or, if togglePerm is NULL, an associtive array of elements to toggle when particular node IDs are selected
	 		'toggleIdsOff'		=> array(),						// Element IDs to toggle on when a node with 'togglePerm' permission IS NOT selected
	 		'forceOwner'		=> \IPS\Member::load(),			// If nodes are 'owned', the owner. NULL for currently logged in member, FALSE to not limit by owner
	 		'where'				=> array(),						// where clause to control which results to display
	 		'disabledIds'		=> array(),					    // Array of disabled IDs
	 		'noParentNodes'		=> 'Custom'						// If a value is provided, subnodes of this class which have no parent node will be added into a pseudo-group with the title provided. e.g. custom packages in Nexus do not belong to any package group
	 		'autoPopulate'		=> FALSE						// Whether or not to autopopulate children of root nodes (defaults to TRUE which means the children are loaded, use FALSE to only show the parent nodes by default until they are clicked on)
	 		'clubs'				=> TRUE,						// If TRUE, will also show nodes inside clubs that the user can access. Defaults to FALSE.
	 		'maxResults'		=> 200,							// Maximum number of nodes to load. NULL to load all nodes or FALSE to respect node class definition. Defaults to FALSE.
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'url'				=> NULL,
		'class'				=> NULL,
		'permissionCheck'	=> NULL,
		'showAllNodes'		=> FALSE,
		'zeroVal'			=> NULL,
		'multiple'			=> FALSE,
		'subnodes'			=> TRUE,
		'togglePerm'		=> NULL,
		'togglePermPBR'		=> TRUE,
		'toggleIds'			=> array(),
		'toggleIdsOff'		=> array(),
		'zeroValTogglesOn'	=> array(),
		'zeroValTogglesOff'	=> array(),
		'forceOwner'		=> NULL,
		'where'				=> array(),
		'disabledIds'		=> array(),
		'noParentNodes'		=> NULL,
		'autoPopulate'		=> TRUE,
		'clubs'				=> FALSE,
		'maxResults'		=> FALSE,
	);
	
	/**
	 * Constructor
	 *
	 * @param	string			$name					Name
	 * @param	mixed			$defaultValue			Default value
	 * @param	bool|NULL		$required				Required? (NULL for not required, but appears to be so)
	 * @param	array			$options				Type-specific options
	 * @param	callback		$customValidationCode	Custom validation code
	 * @param	string			$prefix					HTML to show before input field
	 * @param	string			$suffix					HTML to show after input field
	 * @param	string			$id						The ID to add to the row
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array(), $customValidationCode=NULL, $prefix=NULL, $suffix=NULL, $id=NULL )
	{
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		
		if ( !$this->options['url'] )
		{
			$this->options['url'] = \IPS\Request::i()->url();
		}
		$this->options['url'] = $this->options['url']->setQueryString( '_nodeSelectName', $this->name );
		
		if ( $this->options['clubs'] and ( !\IPS\Settings::i()->clubs or !\IPS\IPS::classUsesTrait( $this->options['class'], 'IPS\Content\ClubContainer' ) ) )
		{
			$this->options['clubs'] = FALSE;
		}

		/* Do we need to respect the node class's defined max results (which is the default)? */
		if( $this->options['maxResults'] === FALSE )
		{
			$nodeClass = $this->options['class'];
			$this->options['maxResults'] = $nodeClass::$maxFormHelperResults;
		}
	}
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 * @note	$selectable controls whether the node is selectable, not whether it is provided as an option or not. 
	 * @note	$showable controls whether or not the node should be displayed at all. On the front end this is based on view permissions, and on the backend we display all nodes regardless of view permissions.
	 */
	public function html()
	{
		$nodeClass = $this->options['class'];
		$selectable = \is_string( $this->options['permissionCheck'] ) ? $this->options['permissionCheck'] : NULL;
		$showable = $this->_getShowable();
		$disabledCallback = \is_callable( $this->options['permissionCheck'] ) ? $this->options['permissionCheck'] : NULL;
		
		if ( !$selectable and $showable and \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation === 'front' )
		{
			$selectable = 'view';
		}

		/* Determine if we need a limit clause */
		$limit = $this->options['maxResults'] ?
			( ( isset( \IPS\Request::i()->_nodeSelectOffset ) ) ? array( (int) \IPS\Request::i()->_nodeSelectOffset, $this->options['maxResults'] ) : array( 0, $this->options['maxResults'] ) ) :
			NULL;

		/* Are we getting some AJAX stuff? */
		if ( isset( \IPS\Request::i()->_nodeSelectName ) and  \IPS\Request::i()->_nodeSelectName === $this->name )
		{
			$disabled = null;
			
			if ( isset( \IPS\Request::i()->_disabled ) )
			{
				$disabled = json_decode( \IPS\Request::i()->_disabled, true );
				$disabled = ( $disabled === FALSE ) ? array() : $disabled;
			}
			
			switch ( \IPS\Request::i()->_nodeSelect )
			{
				case 'children':
					try
					{
						$node = $showable ? $nodeClass::loadAndCheckPerms( \IPS\Request::i()->_nodeId, $showable ) : $nodeClass::load( \IPS\Request::i()->_nodeId );
						
						/* Note - we must check 'view' permissions here, so that the list can properly populate even if we do not have the original permission
						 * - subsequent children may eventually have those permissions, so if the actual permCheck fails, add the node to the disabled list
						 * - where $showable is null, we need to keep it null for areas such as the AdminCP where view permissions should not be set.
						 */
						$children = $node->children( $showable, NULL, $this->options['subnodes'], $disabled, $this->options['where'] );

						if( $selectable !== NULL )
						{
							foreach( $children AS $child )
							{
								if ( !$child->can( $selectable ) )
								{
									$this->options['disabledIds'][] = $child->_id;
								}
							}
						}
					}
					catch ( \Exception $e )
					{
						\IPS\Output::i()->json( NULL, 404 );
					}

					\IPS\Output::i()->json( array( 'viewing' => $node->_id, 'title' => $node->_title, 'output' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $children, FALSE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabledIds'], NULL, array(), NULL, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ) ) );
					break;
					
				case 'parent':					
					try
					{
						$node = $showable ? $nodeClass::loadAndCheckPerms( \IPS\Request::i()->_nodeId, $showable ) : $nodeClass::load( \IPS\Request::i()->_nodeId );
						$parent = $node->parent();
						
						$children = $parent ? $parent->children( $showable, NULL, $this->options['subnodes'], $disabled, $this->options['where'] ) : $nodeClass::roots( $showable, NULL, $this->options['where'] );

						if( $selectable !== NULL )
						{
							foreach( $children AS $child )
							{
								if ( !$child->can( $selectable ) )
								{
									$this->options['disabledIds'][] = $child->_id;
								}
							}
						}
					}
					catch ( \Exception $e )
					{
						\IPS\Output::i()->json( $e->getMessage(), 404 );
					}
					
					\IPS\Output::i()->json( array( 'viewing' => $parent ? $parent->_id : 0, 'title' => $parent ? $parent->_title : \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle ), 'output' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $children, FALSE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabledIds'], NULL, array(), NULL, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ) ) );
					break;
					
				case 'search':
					$results = array();
					
					$_results = $nodeClass::search( '_title', \IPS\Request::i()->q, '_title' );
					foreach ( $_results as $node )
					{
						if ( ( !$showable or $node->can( $showable ) ) AND ! \in_array( $node->_id, $disabled ) )
						{						
							$id = ( $node instanceof $nodeClass ? $node->_id : "s.{$node->_id}" );
							$results[ $id ] = $node;
						}
					}
					
					\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $results, TRUE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabled'], NULL, array(), NULL, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ) );
					break;
			}
		}
		
		/* Get initial nodes */
		$nodes		= array();
		$children	= array();
		$noParentNodes = array();
		if( isset( $nodeClass::$ownerTypes ) and $nodeClass::$ownerTypes !== NULL and $this->options['forceOwner'] !== FALSE )
		{
			$nodes = $nodeClass::loadByOwner( $this->options['forceOwner'] ?: \IPS\Member::loggedIn(), $this->options['where'] );
			if ( $this->options['clubs'] )
			{
				$nodes = array_merge( $nodes, $nodeClass::clubNodes( $showable, NULL, $this->options['where'] ) );
			}
		}
		else
		{
			/* Get roots */
			if ( $this->options['clubs'] )
			{
				$nodes = $nodeClass::rootsWithClubs( $showable, NULL, $this->options['where'], $limit );
			}
			else
			{
				$nodes = $nodeClass::roots( $showable, NULL, $this->options['where'], $limit );
			}

			/* We want to recurse to a certain amount of depth for discoverability, but if we go too crazy not only will it actually
				make the control harder to use, it will have a bad performance impact. So we'll go 3 levels deep or until 300 nodes are
				showing, whichever happens first  */ 
			$totalLimit = 300;
			$currentCount = \count( $nodes );
			if ( $currentCount < $totalLimit )
			{
				foreach( $nodes AS $node )
				{
					$this->_populate( $nodes, $disabled, 0, $node, $children, 3, NULL, FALSE, $totalLimit, $currentCount );
				}
			}
			if ( $this->options['noParentNodes'] )
			{
				$subnodeClass = $nodeClass::$subnodeClass;
				$noParentNodes = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', $subnodeClass::$databaseTable, $subnodeClass::$databasePrefix . $subnodeClass::$parentNodeColumnId . '=0' )->setKeyField( $subnodeClass::$databasePrefix . $subnodeClass::$databaseColumnId ), $subnodeClass ) );
			}
		}

		/* Do we need to show a "load more" link? */
		$loadMoreLink = FALSE;

		if( $this->options['maxResults'] AND \count( $nodes ) == $this->options['maxResults'] )
		{
			$loadMoreLink = (int) \IPS\Request::i()->_nodeSelectOffset + $this->options['maxResults'];
		}

		if ( isset( $this->options['disabled'] ) and \is_array( $this->options['disabled'] ) )
		{
			$this->options['url'] = $this->options['url']->setQueryString( '_disabled', json_encode( $this->options['disabled'] ) );
			
			foreach( $this->options['disabled'] as $id )
			{
				if ( isset( $nodes[ $id ] ) )
				{
					unset( $nodes[ $id ] );
				}
			}
		}
		
		/* What is selected? */
		if ( $this->options['zeroVal'] !== NULL and $this->value === 0 )
		{
			$selected = 0;
		}
		else
		{
			$selected = array();

			if ( \is_array( $this->value ) )
			{
				foreach ( $this->value as $node )
				{
					$title = isset( $node::$titleLangPrefix ) ? \IPS\Member::loggedIn()->language()->addToStack( $node::$titleLangPrefix . $node->_id, FALSE, array( 'json' => TRUE, 'escape' => TRUE, 'striptags'=> TRUE ) ) : str_replace( "&#039;", "&apos;", htmlspecialchars( $node->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', false ) );

					$selected[ !( $node instanceof $nodeClass ) ? "{$node->_id}.s" : $node->_id ] = array( 'title' => $title, 'parents' => array_values( array_map( function( $val ){
							if( isset( $val::$titleLangPrefix ) )
							{
								return \IPS\Member::loggedIn()->language()->addToStack( $val::$titleLangPrefix . $val->_id, FALSE, array( 'json' => TRUE, 'escape' => TRUE, 'striptags'=> TRUE ) );
							}
							else
							{
								return str_replace( "&#039;", "&apos;", htmlspecialchars( $val->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', false ) ); 
							}
					}, iterator_to_array( $node->parents() ) ) ) );
				}
			}
			elseif ( $this->value instanceof \IPS\Node\Model )
			{
				$class = \get_class( $this->value );
				$title = isset( $nodeClass::$titleLangPrefix ) ? \IPS\Member::loggedIn()->language()->addToStack( $class::$titleLangPrefix . $this->value->_id, FALSE, array( 'json' => TRUE, 'escape' => TRUE, 'striptags'=> TRUE ) ) : str_replace( "&#039;", "&apos;", htmlspecialchars( $this->value->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', false ) );
				$selected[ !( $this->value instanceof $nodeClass ) ? "{$this->value->_id}.s" : $this->value->_id ] = array( 'title' => $title, 'parents' => array_values( array_map( function( $val ){ 
						if( isset( $val::$titleLangPrefix ) )
						{
							return \IPS\Member::loggedIn()->language()->addToStack( $val::$titleLangPrefix . $val->_id, FALSE, array( 'json' => TRUE, 'escape' => TRUE, 'striptags'=> TRUE ) );
						}
						else
						{
							return str_replace( "&#039;", "&apos;", htmlspecialchars( $val->_title, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', false ) ); 
						}
				}, iterator_to_array( $this->value->parents() ) ) ) );
			}
			
			$selected = json_encode( $selected );
		}

		/* Do we need the no-JS fallback? We only do this if we know JS is disabled as it's intensive and may cause memory exhaustion if there are lots of nodes - it's a last resort */
		$noJS = NULL;
		if ( isset( \IPS\Request::i()->_noJs ) )
		{
			$options = array();
			$_children = array();
			$disabled = ( isset( $this->options['disabled'] ) and \is_array( $this->options['disabled'] ) ) ? $this->options['disabled'] : array();
			$currentCount = NULL;
			foreach ( $nodeClass::roots( $showable, NULL, $this->options['where'] ) as $root )
			{
				$this->_populate( $options, $disabled, 0, $root, $_children, NULL, NULL, TRUE, NULL, $currentCount );
			}
			
			$value = NULL;
			if ( $this->value !== 0 )
			{
				if ( $this->options['multiple'] )
				{
					$value = array();
					if ( \is_array( $this->value ) )
					{
						$value = array_keys( $this->value );
					}
					elseif ( \is_object( $this->value ) )
					{
						$value = array( $this->value->_id );
					}
				}
				else
				{
					if ( \is_numeric( $this->value ) )
					{
						$value = $this->value;
					}
					elseif ( \is_object( $this->value ) )
					{
						$value = array( $this->value->_id );
					}
				}
			}
																
			$noJS = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( $this->name . ( $this->options['multiple'] ? '[]' : '' ), $value, $this->required, $options, $this->options['multiple'], '', $disabled );
		}

		/* Were we just loading more? */
		if( isset( \IPS\Request::i()->_nodeSelectName ) and \IPS\Request::i()->_nodeSelectName === $this->name AND isset( \IPS\Request::i()->_nodeSelect ) AND \IPS\Request::i()->_nodeSelect == 'loadMore' )
		{
			if ( $this->options['clubs'] )
			{
				\IPS\Output::i()->json( array( 'loadMore' => $loadMoreLink, 'globalOutput' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $nodes, FALSE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabledIds'], NULL, array(), FALSE, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ), 'clubsOutput' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $nodes, FALSE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabledIds'], NULL, array(), TRUE, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ) ) );
			}
			else
			{
				\IPS\Output::i()->json( array( 'loadMore' => $loadMoreLink, 'output' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->nodeCascade( $nodes, FALSE, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, FALSE, NULL, $this->options['class'], $this->options['where'], $this->options['disabledIds'], NULL, array(), NULL, $this->options['togglePermPBR'], $this->options['toggleIdsOff'] ) ) );
			}
		}

		/* Display */
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->node( $this->name, $selected, $this->options['multiple'], $this->options['url'], $nodeClass::$nodeTitle, $nodes, $this->options['zeroVal'], $noJS, $selectable, $this->options['subnodes'], $this->options['togglePerm'], $this->options['toggleIds'], $disabledCallback, $this->options['zeroValTogglesOn'], $this->options['zeroValTogglesOff'], $this->options['autoPopulate'], $children, $this->options['class'], $this->options['where'], \is_array( $this->options['disabledIds'] ) ? $this->options['disabledIds'] : array(), $this->options['noParentNodes'], $noParentNodes, $this->options['clubs'], $this->options['togglePermPBR'], $this->options['toggleIdsOff'], $loadMoreLink );
	}
	
	/**
	 * Populate array options
	 *
	 * @param	array					$nodes		The list of nodes
	 * @param	array|boolean			$disabled	Disabled options
	 * @param	int						$depth		The current depth
	 * @param	\IPS\Node\Model			$node		The node
	 * @param	array					$children	Children of the node
	 * @param	int|NULL				$depthLimit	How deep the recursion should go
	 * @param	\IPS\Node\Model|NULL	$parent		If we are recursing on a child, this should be the parent node. Otherwise NULL if in the root.
	 * @param	bool					$noJS		No-javascript fallback
	 * @return	void
	 */
	protected function _populate( &$nodes, &$disabled, $depth, $node, &$children, $depthLimit = NULL, $parent = NULL, $noJS = FALSE, $totalLimit = NULL, &$currentCount = 0 )
	{
		$showable = $this->_getShowable();

		if ( ( !$showable OR $node->can( $showable ) ) and ( empty( $this->options['disabled'] ) or !\in_array( $node->_id, $this->options['disabled'] ) ) and !$node->deleteOrMoveQueued() )
		{
			if ( $noJS )
			{
				$nodes[ $node->_id . ( !( $node instanceof $this->options['class'] ) ? '.s' : '' ) ] = str_repeat( '- ', $depth ) . $node->_title;
			}
			else
			{
				if ( $parent === NULL )
				{
					$nodes[ $node->_id ] = $node;
				}
				else
				{
					$children[ $parent->_id ][ $node->_id ] = $node;
				}
			}
			
			if ( $this->options['permissionCheck'] )
			{
				if ( \is_string( $this->options['permissionCheck'] ) )
				{
					if ( !$node->can( $this->options['permissionCheck'] ) )
					{
						$disabled[] = $node->_id;
					}
				}
				elseif ( \is_callable( $this->options['permissionCheck'] ) )
				{
					$permissionCheck = $this->options['permissionCheck'];
					if ( !$permissionCheck( $node ) )
					{
						$disabled[] = $node->_id;
					}
				}
			}
			
			if ( $depthLimit === NULL OR $depth < (int) $depthLimit )
			{
				if ( !$totalLimit or ( ( $currentCount += $node->childrenCount( $showable, NULL, $this->options['subnodes'], $this->options['where'] ) ) < $totalLimit ) )
				{
					foreach( $node->children( $showable, NULL, $this->options['subnodes'], NULL, $this->options['where'] ) AS $child )
					{
						$this->_populate( $nodes, $disabled, $depth + 1, $child, $children, $depthLimit, $node, $noJS, $totalLimit, $currentCount );
					}
				}
			}
		}
	}

	/**
	 * Get the "should I show this option" value
	 *
	 * @return	string|NULL
	 */
	protected function _getShowable()
	{
		if( $this->options['showAllNodes'] === TRUE )
		{
			return NULL;
		}
		else
		{
			return ( \IPS\Dispatcher::hasInstance() and \IPS\Dispatcher::i()->controllerLocation === 'front' ) ? 'view' : NULL;
		}
	}
		
	/**
	 * Get Value
	 *
	 * @return	string|int
	 */
	public function getValue()
	{
		$zeroValName = "{$this->name}-zeroVal";
		if ( $this->options['zeroVal'] !== NULL and isset( \IPS\Request::i()->$zeroValName ) )
		{
			return 0;
		}
		else
		{
			return parent::getValue();
		}
	}
	
	/**
	 * Format Value
	 *
	 * @return	\IPS\Node\Model|array|NULL
	 */
	public function formatValue()
	{		
		$nodeClass	= $this->options['class'];
		$selectable	= $this->options['permissionCheck'];
		$showable	= $this->_getShowable();
				
		if ( $this->value and !( $this->value instanceof \IPS\Node\Model ) )
		{			
			$return = array();
			foreach ( \is_array( $this->value ) ? $this->value : explode( ',', $this->value ) as $v )
			{
				if ( $v instanceof \IPS\Node\Model )
				{
					$return[ !( $v instanceof $nodeClass ) ? "s{$v->_id}" : $v->_id ] = $v;
				}	
				elseif( $v )
				{
					try
					{
						$exploded = explode( '.', $v );
						$classToUse = ( isset( $exploded[1] ) and $exploded[1] === 's' ) ? $nodeClass::$subnodeClass : $nodeClass;
						$node = $classToUse::load( $exploded[0] );
												
						if ( ( !$showable or $node->can( $showable ) ) and !$selectable or ( \is_string( $selectable ) and $node->can( $selectable ) ) or ( \is_callable( $selectable ) and $selectable( $node ) ) )
						{
							$return[ isset( $exploded[1] ) ? "s{$node->_id}" : $node->_id ] = $node;
						}
					}
					catch ( \Exception $e ) {}
				}
			}
			
			if ( !empty( $return ) )
			{
				return $this->options['multiple'] ? $return : array_pop( $return );
			}
			else
			{
				return NULL;
			}
		}
		
		return $this->value;
	}

	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		/* We return a NULL value instead of an empty string, so we need to check that if field is required */
		if( ( $this->value === NULL OR ( \is_array( $this->value ) AND empty( $this->value ) ) ) and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}

		return parent::validate();
	}
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		if ( \is_array( $value ) )
		{
			return implode( ',', array_keys( $value ) );
		}
		elseif ( \is_object( $value ) )
		{
			return $value->_id;
		}
		return (string) $value;
	}
}
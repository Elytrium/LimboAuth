<?php
/**
 * @brief		Content Discovery Stream
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Jul 2015
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Discovery Stream
 */
class _Stream extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_streams';
			
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'streams';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'stream_title_';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'core',
		'module'	=> 'discovery',
		'all'	 	=> 'streams_manage',
	);
	
	/**
	 * @brief The base URL of this stream
	 */
	public $baseUrl = NULL;
	
	/**
	 * @brief	The default stream either set by member or admin
	 */
	protected static $defaultStream = NULL;
	
	/**
	 * @brief	The config of the stream when it is first loaded
	 */
	protected $defaultConfig = NULL;
	
	/**
	 * Fetch All Root Nodes
	 *
	 * @param	string|NULL			$permissionCheck	The permission key to check for or NULl to not check permissions
	 * @param	\IPS\Member|NULL	$member				The member to check permissions for or NULL for the currently logged in member
	 * @param	mixed				$where				Additional WHERE clause
	 * @param	array|NULL			$limit				Limit/offset to use, or NULL for no limit (default)
	 * @return	array
	 */
	public static function roots( $permissionCheck='view', $member=NULL, $where=array(), $limit=NULL )
	{
		$where[] = array( '`member` IS NULL' );
		return parent::roots( $permissionCheck, $member, $where, $limit );
	}
	
	/**
	 * Fetch the default stream, or NULL
	 *
	 * @return \IPS\core\Stream|null
	 */
	public static function defaultStream()
	{
		/* If we've already loaded it, return it */
		if( static::$defaultStream !== NULL )
		{
			return static::$defaultStream;
		}

		/* Check the member first */
		if ( \IPS\Member::loggedIn()->member_id )
		{
			$default = \IPS\Member::loggedIn()->defaultStream;

			if ( $default !== NULL )
			{
				try
				{
					if ( $default )
					{
						static::$defaultStream = static::load( $default );
					}
					else
					{
						static::$defaultStream = static::allActivityStream();
					}
				}
				catch( \Exception $e )
				{
					return NULL;
				}

				return static::$defaultStream;
			}
		}

		/* Still here? Check menu */
		try
		{
			if ( !isset( \IPS\Data\Store::i()->defaultStreamData ) )
			{
				try
				{
					\IPS\Data\Store::i()->defaultStreamData = \IPS\Db::i()->select( '*', 'core_streams', array( array( '`default`=?', 1 ) ) )->first();
				}
				catch ( \UnderflowException $e )
				{
					\IPS\Data\Store::i()->defaultStreamData = 0;
				}
			}
			
			if ( \IPS\Data\Store::i()->defaultStreamData == 0 )
			{
				static::$defaultStream = static::allActivityStream();
				return static::$defaultStream;
			}
			
			$stream = static::constructFromData( \IPS\Data\Store::i()->defaultStreamData );
			
			/* Suitable for guests? */
			if ( ! \IPS\Member::loggedIn()->member_id )
			{
				if ( ! ( ( $stream->ownership == 'all' and $stream->read == 'all' and $stream->follow == 'all' and $stream->date_type != 'last_visit' ) ) )
				{
					static::$defaultStream = static::allActivityStream();
					return static::$defaultStream;
				}
			}

			static::$defaultStream = $stream;
			
			return $stream;
		}
		catch( \Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * "All Activity" Stream
	 *
	 * @return	\IPS\core\Stream
	 */
	public static function allActivityStream()
	{
		$stream = new static;
		$stream->id = 0;
		$stream->include_comments = TRUE;
		$stream->date_relative_days = 365;
		$stream->date_type = 'relative';
		$stream->default_view = 'expanded';
		$stream->baseUrl = \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' );
		return $stream;
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$obj = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		$obj->initBaseUrl();
		$obj->defaultConfig = $obj->config();
		
		return $obj;
	}
	
	/**
	 * The Javascript code handles containers slightly differently
	 * So we need to convert them ready for use in the URL so javascript can read them as native
	 * Javascript returns containers[class] = 1,2,3
	 * The PHP code expects json_encode( containers[class] = array(1,2,3) )
	 *
	 * @param	string|array	$containers		Containers to convert
	 * @return	array
	 */
	static public function containersToUrl( $containers )
	{
		if ( $containers === NULL )
		{
			return array();
		}
		
		if ( ! \is_array( $containers ) )
		{ 
			$containers = json_decode( $containers, true );
		}
		
		if ( \count( $containers ) )
		{
			foreach( $containers as $class => $ids )
			{
				$containers[ $class ] = implode( ',', $ids );
			}
		}
		
		return $containers;
	}
	
	/**
	 * The Javascript code handles containers slightly differently
	 * This converts javascript formatted containers into PHP native
	 * Javascript returns containers[class] = 1,2,3
	 * The PHP code expects json_encode( containers[class] = array(1,2,3) )
	 *
	 * @param	array	$containers		Containers to convert
	 * @return	string
	 */
	static public function containersFromUrl( $containers )
	{
		if ( \is_array( $containers) and \count( $containers ) )
		{
			foreach( $containers as $class => $ids )
			{
				$containers[ $class ] = explode( ',', $ids );
			}
		}
		
		return json_encode( $containers );
	}
	
	/**
	 * Initilize the base url
	 *
	 * @return void
	 */
	protected function initBaseUrl()
	{
		$this->baseUrl = $this->getBaseUrl();
	}
	
	/**
	 * Fetch the base url
	 *
	 * @return void
	 */
	public function getBaseUrl()
	{
		if ( $this->id )
		{
			switch ( $this->id )
			{
				case 1:
					$furlKey = 'discover_unread';
					break;
				case 2:
					$furlKey = 'discover_istarted';
					break;
				case 3:
					$furlKey = 'discover_followed';
					break;
				case 4:
					$furlKey = 'discover_following';
					break;
				case 5:
					$furlKey = 'discover_posted';
					break;
				default:
					$furlKey = 'discover_stream';
					break;
			}
			return \IPS\Http\Url::internal( "app=core&module=discover&controller=streams&id={$this->id}", 'front', $furlKey );
		}
		else
		{
			return \IPS\Http\Url::internal( "app=core&module=discover&controller=streams", 'front', 'discover_all' );
		}
	}
	
	/**
	 * [Node] Get Title
	 *
	 * @return	string|null
	 */
	protected function get__title()
	{
		if ( $this->id )
		{
			return $this->title ?: parent::get__title();
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('all_activity');
		}
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form			The form
	 * @param	string				$titleType		'Text' or 'Translatable
	 * @param	bool				$titleRequired	Is the title field required?
	 * @return	void
	 */
	public function form( &$form, $titleType='Translatable', $titleRequired=TRUE )
	{
		/* Title */
		if ( $titleType )
		{
			$titleClass = '\IPS\Helpers\Form\\' . $titleType;
			$form->add( new $titleClass( 'stream_title', ( $this->id and $titleType === 'Text' ) ? $this->_title : NULL, $titleRequired, array( 'app' => 'core', 'key' => ( $this->id ? "stream_title_{$this->id}" : NULL ),'maxLength' => 255 ) ) );
		}
		
		/* All content or specific content? */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_include_comments', $this->include_comments, TRUE, array( 'options' => array(
			1	=> 'stream_include_comments_1',
			0	=> 'stream_include_comments_0'
		) ) ) );
		
		/* All content or specific content? */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_classes_type', $this->classes ? 1 : 0, TRUE, array(
			'options'	=> array( 0 => 'stream_classes_type_all', 1 => 'stream_classes_type_custom' ),
			'toggles'	=> array( 0 => array( 'stream_club_select' ), 1 => array( 'stream_classes' ) )
		) ) );
		
		/* Work out all the different classes */
		$classes = array();
		$classToggles = array();

		foreach ( \IPS\Content::routedClasses( TRUE, FALSE, TRUE ) as $class )
		{
			if ( is_subclass_of( $class, 'IPS\Content\Searchable' ) AND isset( $class::$databaseColumnMap['date'] ) )
			{
				$classes[ $class ] = $class::$title . '_pl';
				if ( isset( $class::$containerNodeClass ) )
				{
					$classToggles[ $class ][] = 'stream_containers_' . $class::$title;
				}
			}
		}

		$containers = json_decode( $this->containers );

		/* Add the fields for them */
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'stream_classes', $this->classes ? explode( ',', $this->classes ) : array(), NULL, array( 'options' => $classes, 'toggles' => $classToggles ), NULL, NULL, NULL, 'stream_classes' ) );

		if ( \IPS\Dispatcher::i()->controllerLocation === 'admin' )
		{
			/* Nodes */
			foreach ( $classToggles as $class => $id )
			{
				if ( isset( $class::$containerNodeClass ) )
				{
					$nodeClass = $class::$containerNodeClass;
					$value = isset( $containers->$class ) ? $containers->$class : 0;

					$field = new \IPS\Helpers\Form\Node( 'stream_containers_' . $class::$title, $value, FALSE, array( 'class' => $nodeClass, 'clubs' => TRUE, 'multiple' => TRUE, 'zeroVal' => 'all', 'permissionCheck' => $nodeClass::searchableNodesPermission(), 'forceOwner' => FALSE, 'subnodes' => FALSE ), NULL, NULL, NULL, 'stream_containers_' . $class::$title );
					$field->label = \IPS\Member::loggedIn()->language()->addToStack( $nodeClass::$nodeTitle );
					$form->add( $field );
				}
			}
		}
		
		/* Clubs */
		if( \IPS\Settings::i()->clubs )
		{
			$clubs = $this->_getOurClubs();

			if ( \is_array( $clubs ) AND \count( $clubs ) )
			{
				$clubOptions = array();
				foreach ( $clubs as $club )
				{
					$clubOptions[ "c{$club->id}" ] = $club->name;
				}
							
				switch ( $this->clubs )
				{
					case NULL:
						$clubSelect = 'all';
						break;
					case '0':
						$clubSelect = 'none';
						break;
					default:
						$clubSelect = 'select';
						break;
				}
							
				$form->add( new \IPS\Helpers\Form\Radio( 'stream_club_select', $clubSelect, TRUE, array(
					'options'		=> array(
						'all'		=> 'stream_club_select_all',
						'none'		=> 'stream_club_select_none',
						'select'	=> 'stream_club_select_select',
					),
					'toggles'	=> array(
						'select'	=> array( 'stream_club_filter' )
					)
				), NULL, NULL, NULL, 'stream_club_select' ) );
				$form->add( new \IPS\Helpers\Form\Select( 'stream_club_filter', explode( ',', $this->clubs ), FALSE, array( 'options' => $clubOptions, 'parse' => 'normal', 'multiple' => TRUE, 'noDefault' => TRUE ), NULL, NULL, NULL, 'stream_club_filter' ) );
			}
		}

		/* Tags */
		if ( \IPS\Settings::i()->tags_enabled )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'stream_tags_type', $this->tags ? 'custom' : 'all', TRUE, array(
				'options' 	=> array(
					'all'		=> 'stream_tags_all',
					'custom'	=> 'stream_tags_custom'
				),
				'toggles'	=> array(
					'custom'	=> array( 'stream_tags' )
				)
			) ) );
			$form->add( new \IPS\Helpers\Form\Text( 'stream_tags', $this->tags ? explode( ',', $this->tags ) : NULL, NULL, array( 'autocomplete' => array( 'minimized' => FALSE ) ), NULL, NULL, NULL, 'stream_tags' ) );
		}
		
		/* Ownership */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_ownership', $this->ownership, TRUE, array(
			'options' => array(
				'all'				=> 'stream_ownership_all',
				'started'			=> 'stream_ownership_started',
				'postedin'			=> 'stream_ownership_postedin',
				'custom'			=> 'stream_ownership_custom',
			),
			'toggles'	=> array(
				'custom'			=> array( 'stream_custom_members' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Member( 'stream_custom_members', $this->custom_members ? array_map( array( 'IPS\Member', 'load' ), explode( ',', $this->custom_members ) ) : NULL, NULL, array( 'multiple' => NULL ), NULL, NULL, NULL, 'stream_custom_members' ) );
		
		/* Read */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_read', $this->read, TRUE, array( 'options' => array(
			'all'				=> 'stream_read_all',
			'unread'			=> 'stream_read_unread',
		) ) ) );

		/* Solved */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_solved', $this->solved, TRUE, array( 'options' => array(
			'all'				=> 'stream_solved_all',
			'solved'			=> 'stream_solved_solved',
			'unsolved'			=> 'stream_solved_unsolved',
		) ) ) );
		
		/* Follow */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_follow', $this->follow, TRUE, array(
			'options' 	=> array(
				'all'		=> 'stream_follow_all',
				'followed'	=> 'stream_follow_followed',
			),
			'toggles'	=> array(
				'followed'	=> array( 'stream_followed_types' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'stream_followed_types', $this->followed_types ? explode( ',', $this->followed_types ) : array( 'items' ), NULL, array( 'options' => array(
			'containers'	=> 'stream_followed_types_areas',
			'items'			=> 'stream_followed_types_items',
			'members'		=> 'stream_followed_types_members',
		) ), NULL, NULL, NULL, 'stream_followed_types' ) );
		
		/* Date */
		$streamDateOptions = [];
		if ( \IPS\Settings::i()->search_method === 'elastic' )
		{
			$streamDateOptions['all'] = 'stream_date_type_all';
		}
		$streamDateOptions['last_visit'] = 'stream_date_type_last_visit';
		$streamDateOptions['relative'] = 'stream_date_type_relative';
		$streamDateOptions['custom'] = 'stream_date_type_custom';
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_date_type', $this->id ? $this->date_type : ( \IPS\Settings::i()->search_method === 'elastic' ? 'all' : 'relative' ), TRUE, array(
			'options' => $streamDateOptions,
			'toggles' => array(
				'relative'			=> array( 'stream_date_relative_days' ),
				'custom'			=> array( 'stream_date_range' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'stream_date_relative_days', $this->date_relative_days ?: 365, NULL, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS ), function( $val )
		{
			if ( \IPS\Request::i()->stream_date_type == 'relative' and !$val )
			{
				throw new \DomainException('form_required');
			}
		}, \IPS\Member::loggedIn()->language()->addToStack('stream_date_relative_days_prefix'), NULL, 'stream_date_relative_days' ) );
		$form->add( new \IPS\Helpers\Form\DateRange( 'stream_date_range', array( 'start' => $this->date_start, 'end' => $this->date_end ), NULL, array(), function( $val )
		{
			if ( \IPS\Request::i()->stream_date_type == 'custom' and !$val['start'] and !$val['end'] )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'stream_date_range' ) );

		/* Default View */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_default_view', $this->default_view ?: 'expanded', TRUE, array( 'options' => array(
			'condensed'			=> 'stream_default_view_condensed',
			'expanded'			=> 'stream_default_view_expanded',
		) ) ) );
		
		/* Sort */
		$form->add( new \IPS\Helpers\Form\Radio( 'stream_sort', $this->sort, TRUE, array( 'options' => array(
			'newest'	=> 'stream_sort_newest',
			'oldest'	=> 'stream_sort_oldest',
		) ) ) );
		
		if ( \IPS\Dispatcher::i()->controllerLocation === 'admin' )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'stream_default', $this->default, FALSE ) );
		}
	}

	/**
	 * @brief Cached clubs
	 */
	protected static $ourClubs = NULL;

	/**
	 * Fetch our clubs and cache
	 *
	 * @return array
	 */
	protected function _getOurClubs()
	{
		if( static::$ourClubs === NULL )
		{
			static::$ourClubs = \IPS\Member\Club::clubs( \IPS\Member::loggedIn(), NULL, 'name', TRUE, array(), \IPS\Settings::i()->clubs_require_approval ? array( 'approved=1' ) : NULL );

			static::$ourClubs = static::$ourClubs ? iterator_to_array( static::$ourClubs ) : array();
		}

		return static::$ourClubs;
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		/* Title */
		if ( ! $this->id and $this->id !== 0 )
		{
			$this->save();
		}
		if ( isset( $values['stream_title'] ) and \is_array( $values['stream_title'] ) )
		{
			\IPS\Lang::saveCustom( 'core', "stream_title_{$this->id}", $values['stream_title'] );
			$values['stream_title'] = NULL;
		}
		unset( $values['__custom_stream'] );
		unset( $values['__stream_owner'] );
						
		/* Sort out stream_classes_type */
		if ( ( isset( $values['stream_classes_type'] ) && $values['stream_classes_type'] == 0 ) )
		{
			$values['stream_classes'] = NULL;
			$values['stream_containers'] = NULL;
		} 
		elseif ( ( isset( $values['stream_classes'] ) && \is_array( $values['stream_classes'] ) ) )
		{
			$classes = array();
			$containers = NULL;
			foreach ( $values['stream_classes'] as $class )
			{
				$classes[] = $class;
				if ( isset( $values[ 'stream_containers_' . $class::$title ] ) and $values[ 'stream_containers_' . $class::$title ] )
				{
					$containers[ $class ] = array_keys( $values[ 'stream_containers_' . $class::$title ] );
				}
			}
						
			$values['stream_classes'] = implode( ',', $classes );
			$values['stream_containers'] = $containers ? json_encode( $containers ) : NULL;		
		}
		else
		{
			$values['stream_classes'] = NULL;
			$values['stream_containers'] = NULL;
		}
		unset( $values['stream_classes_type'] );
		
		/* Clubs */
		if ( isset( $values['stream_club_select'] ) and ( !isset( $values['stream_classes_type'] ) or $values['stream_classes_type'] == 0 ) )
		{
			switch ( $values['stream_club_select'] )
			{
				case 'all':
					$values['stream_clubs'] = NULL;
					break;
					
				case 'none':
					$values['stream_clubs'] = 0;
					break;
					
				case 'select':
					if ( $values['stream_club_filter'] )
					{
						$values['stream_clubs'] = \is_array( $values['stream_club_filter'] ) ? implode( ',', $values['stream_club_filter'] ) : $values['stream_club_filter'];
					}
					else
					{
						$values['stream_clubs'] = 0;
					}
					break;
			}
		}
		else
		{
			$values['stream_clubs'] = NULL;
		}
		unset( $values['stream_club_select'] );
		unset( $values['stream_club_filter'] );
		
		
		/* And tags */
		if ( isset( $values['stream_tags'] ) )
		{
			if ( !$values['stream_tags'] )
			{
				$values['stream_tags'] = NULL;
			}
			else
			{
				$values['stream_tags'] = ( \is_array( $values['stream_tags'] ) ) ? implode( ',', $values['stream_tags'] ) : $values['stream_tags'];
			}
		}
		
		if ( isset( $values['stream_tags_type'] ) )
		{
			unset( $values['stream_tags_type'] );
		}
		
		/* And follows */
		if ( isset( $values['stream_follow'] ) )
		{
			$values['stream_followed_types'] = ( isset( $values['stream_followed_types'] ) && $values['stream_follow'] == 'followed' ? implode( ',', $values['stream_followed_types'] ) : NULL );
		}		
		
		/* And members */
		if ( isset( $values['stream_ownership'] ) )
		{
			if ( $values['stream_ownership'] == 'custom' )
			{
				if( !empty( $values['stream_custom_members'] ) )
				{
					$members = array();
					foreach ( $values['stream_custom_members'] as $member )
					{
						$members[] = $member->member_id;
					}
					$values['stream_custom_members'] = implode( ',', $members );
				}
				else
				{
					$values['stream_custom_members'] = NULL;
					$values['stream_ownership'] = 'all';
				}
			}
			else
			{
				$values['stream_custom_members'] = NULL;
			}
		}

		/* And dates */
		if ( isset( $values['stream_date_type'] ) )
		{
			/* If we're using last visit or relative, we need to reset some values. */
			try
			{
				match( $values['stream_date_type'] ) {
					'last_visit'	=> [
						$values['stream_date_start']			= NULL,
						$values['stream_date_end']				= NULL,
						$values['stream_date_relative_days']	= NULL,
					],
					'relative'		=> [
						$values['stream_date_start']			= NULL,
						$values['stream_date_end']				= NULL,
					]
				};
			}
			catch( \UnhandledMatchError ) { }
		}
		
		/* If we're using custom dates, these need to be handled specially. */
		if ( isset( $values['stream_date_type'] ) AND $values['stream_date_type'] AND $values['stream_date_type'] === 'custom' )
		{
			if ( ! isset( $values['stream_date_start'] ) )
			{
				$values['stream_date_start'] = isset( $values['stream_date_range'] ) && $values['stream_date_range']['start'] ? ( $values['stream_date_range']['start'] instanceof \IPS\DateTime ? $values['stream_date_range']['start']->getTimestamp() : $values['stream_date_range']['start'] ) : NULL;
			}
			
			if ( ! isset( $values['stream_date_end'] ) )
			{
				$values['stream_date_end'] = isset( $values['stream_date_range'] ) && $values['stream_date_range']['end'] ? ( $values['stream_date_range']['end'] instanceof \IPS\DateTime ? $values['stream_date_range']['end']->getTimestamp() : $values['stream_date_range']['end'] ) : NULL;
			}
			
			$values['stream_date_relative_days'] = NULL;
		}
		
		unset( $values['stream_date_range'] );
		
		if ( \IPS\Dispatcher::i()->controllerLocation === 'admin' and ! empty( $values['stream_default'] ) )
		{
			$where = ( $this->id ) ? array( 'id !=?', $this->id ) : NULL;
			\IPS\Db::i()->update( 'core_streams', array( 'default' => 0 ), $where );
		}
		
		/* Remove stream_ prefix */
		$_values = $values;
		$values = array();
		foreach ( $_values as $k => $v )
		{
			if( mb_substr( $k, 0, 15 ) === 'stream_classes_' or mb_substr( $k, 0, 18 ) === 'stream_containers_' )
			{
				continue;
			}
			if( mb_substr( $k, 0, 7 ) === 'stream_' )
			{
				$values[ mb_substr( $k, 7 ) ] = $v;
			}
			else
			{
				$values[ $k ]	= $v;
			}
		}

		/* Return */
		return $values;
	}
	
	/**
	 * Get blurb
	 *
	 * @return	string
	 */
	public function blurb()
	{
		if ( $this->classes )
		{
			$classes = array();
			foreach ( explode( ',', $this->classes ) as $class )
			{
				if ( class_exists( $class ) )
				{
					if ( \in_array( 'IPS\Content\Review', class_parents( $class ) ) )
					{
						$classes[ $class::$itemClass ]['reviews'] = $class;
					}
					elseif ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
					{
						$classes[ $class::$itemClass ]['comments'] = $class;
					}
					elseif ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
					{
						$classes[ $class ]['items'] = $class;
					}
				}
			}
			
			$types = array();
			$allowedContainers = $this->containers ? json_decode( $this->containers, TRUE ) : array();
			foreach ( $classes as $itemClass => $subClasses )
			{
				$_types = array();
				foreach ( $subClasses as $class )
				{
					$_types[] = \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl_lc' );
				}
				$_types = \IPS\Member::loggedIn()->language()->formatList( $_types );
												
				if ( isset( $allowedContainers[ $itemClass ] ) and isset( $itemClass::$containerNodeClass ) )
				{
					$containers = array();
					$containerClass = $itemClass::$containerNodeClass;
					foreach ( $allowedContainers[ $itemClass ] as $id )
					{
						try
						{
							$containers[] = $containerClass::loadAndCheckPerms( $id )->_title;
						}
						catch ( \OutOfRangeException $e ) { }
					}
					$containers = \IPS\Member::loggedIn()->language()->formatList( $containers );
					
					$types[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_in_containers', FALSE, array( 'sprintf' => array( $_types, $containers ) ) );
				}
				else
				{
					$types[] = $_types;
				}
			}
						
			$type = \IPS\Member::loggedIn()->language()->formatList( $types );
		}
		else
		{
			$type = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_all');
		}
				
		$terms = array();
		
		if ( $this->clubs === '0' )
		{
			$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_no_clubs');
		}
		elseif ( $this->clubs )
		{
			$clubs = array();
			foreach ( explode( ',', $this->clubs ) as $clubId )
			{
				try
				{
					$club = \IPS\Member\Club::load( ltrim( $clubId, 'c' ) );
					if ( $club->canRead( \IPS\Member::loggedIn() ) )
					{
						$clubs[] = $club->name;
					}
				}
				catch ( \Exception $e ) { }
			}
			if ( \count( $clubs ) )
			{
				$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_in_clubs', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $clubs ) ) ) );
			}
			else
			{
				$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_no_clubs');
			}
		}
		
		if ( $this->tags )
		{
			$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_includes_tags', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( array_map( function( $val ){
				return '\'' . $val . '\'';
			}, explode( ',', $this->tags ) ) ), \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) );
		}
		
		switch ( $this->ownership )
		{
			case 'started':
				$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_i_started');
				break;
			
			case 'postedin':
				$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_i_posted_in');
				break;
							
			case 'custom':
				$memberNames = array();
				$members = ( ! \is_array( $this->custom_members ) ? explode( ',', $this->custom_members ) : $this->custom_members );
				foreach ( $members as $member )
				{
					if ( ! ( $member instanceof \IPS\Member ) )
					{
						$_member = \IPS\Member::load( $member );
						if ( $_member->member_id )
						{
							$memberNames[] = $_member->name;
						}
					}
					else
					{
						$memberNames[] = $member->name;
					}
				}
				if ( \count( $memberNames ) )
				{
					$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_by_members', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $memberNames, \IPS\Member::loggedIn()->language()->get('or_list_format') ) ) ) );
				}
				break;
		}
		if ( $this->read == 'unread' )
		{
			$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_unread');
		}
		if ( $this->follow == 'followed' )
		{
			$followTerms = array();
			foreach ( explode( ',', $this->followed_types ) as $followType )
			{
				switch ( $followType )
				{
					case 'containers':
						$followTerms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_following_containers');
						break;
					case 'items':
						$followTerms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_following_items');
						break;
					case 'members':
						$followTerms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_following_members');
						break;
				}
			}
			
			$terms[] = \IPS\Member::loggedIn()->language()->formatList( $followTerms, \IPS\Member::loggedIn()->language()->get('or_list_format') );
		}
		switch ( $this->date_type )
		{
			case 'last_visit':
				$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_since_last_visit');
				break;
			case 'relative':
				if( \IPS\Request::i()->stream_date_relative_days )
				{
					$period = ( \IPS\Request::i()->stream_date_relative_days['unit'] == 'd' ) ? \IPS\Member::loggedIn()->language()->addToStack( 'f_days', FALSE, array( 'pluralize' => array( \IPS\Request::i()->stream_date_relative_days['val'] ) ) ) : \IPS\Member::loggedIn()->language()->addToStack( 'f_weeks', FALSE, array( 'pluralize' => array( \IPS\Request::i()->stream_date_relative_days['val'] ) ) );
				}
				else
				{
					$period = \IPS\Member::loggedIn()->language()->addToStack( 'f_days', FALSE, array( 'pluralize' => array( $this->date_relative_days ) ) );
				}

				$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_relative', FALSE, array( 'sprintf' => array( $period ) ) );
				break;
			case 'custom':
				if ( $this->date_start and $this->date_end )
				{
					$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_date_between', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $this->date_start ), \IPS\DateTime::ts( $this->date_end ) ) ) );
				}
				elseif ( $this->date_start )
				{
					$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_date_after', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $this->date_start ) ) ) );
				}
				elseif ( $this->date_end )
				{
					$terms[] = \IPS\Member::loggedIn()->language()->addToStack( 'stream_blurb_date_before', FALSE, array( 'sprintf' => array( \IPS\DateTime::ts( $this->date_end ) ) ) );
				}
				break;
		}

		if ( $this->solved == 'unsolved' )
		{
			$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_unsolved');
		}
		else if ( $this->solved =='solved')
		{
			$terms[] = \IPS\Member::loggedIn()->language()->addToStack('stream_blurb_solved');
		}
		
		if ( \count( $terms ) )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'steam_blurb_with_terms', FALSE, array( 'sprintf' => array( $type, \IPS\Member::loggedIn()->language()->formatList( $terms ) ) ) );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( 'steam_blurb_no_terms', FALSE, array( 'sprintf' => array( $type ) ) );
		}
	}
		
	/**
	 * Gets an array representing the config of this stream
	 *
	 * @return	array
	 */
	public function config()
	{
		/* The simple values */
		$config = array(
			'id' => $this->id,
			'url' => (string) $this->getBaseUrl(),
			'owner' => $this->member,
			'stream_include_comments' => $this->include_comments,
			'stream_read' => $this->read,
			'stream_follow' => $this->follow,
			'stream_default_view' => $this->default_view,
			'stream_sort' => $this->sort,
			'stream_ownership' => $this->ownership,
			'stream_date_type' => $this->date_type,
			'stream_classes' => NULL,
			'stream_solved' => $this->solved,
			'containers' => static::containersToUrl( $this->containers ),
		);
		
		/* Clubs */
		switch ( $this->clubs )
		{
			case NULL:
				$config['stream_club_select'] = 'all';
				$config['stream_club_filter'] = '';
				break;
			case '0':
				$config['stream_club_select'] = 'none';
				$config['stream_club_filter'] = '0';
				break;
			default:
				$config['stream_club_select'] = 'select';
				$config['stream_club_filter'] = $this->clubs;
				break;
		}
		
		/* Follows */
		$followedTypes = explode( ',', $this->followed_types );
		$config['stream_followed_types'] = array();
		
		foreach( $followedTypes as $type )
		{
			if ( $type )
			{
				$config['stream_followed_types'][ $type ] = 1;
			}
		}
		
		/* Classes */
		if ( $this->classes )
		{
			$classes = array();
			foreach ( explode( ',', $this->classes ) as $class )
			{
				if ( class_exists( $class ) )
				{
					if ( \in_array( 'IPS\Content\Review', class_parents( $class ) ) )
					{
						$classes[ $class::$itemClass ]['reviews'] = $class;
					}
					elseif ( \in_array( 'IPS\Content\Comment', class_parents( $class ) ) )
					{
						$classes[ $class::$itemClass ]['comments'] = $class;
					}
					elseif ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
					{
						$classes[ $class ]['items'] = $class;
					}
				}
			}
			
			$types = array();
			foreach ( $classes as $itemClass => $subClasses )
			{
				foreach ( $subClasses as $class )
				{
					$types[ $class ] = \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl', FALSE );
				}
			}
			
			$config['stream_classes'] = $types;
		}

		$config['stream_classes_type'] = (int) ( $config['stream_classes'] !== NULL );
		
		/* Ownership */		
		if( $this->ownership == 'custom' and $this->custom_members )
		{
			/* Values are store as int in the db, but the json needs names so it matches the form data */
			if( mb_strpos( $this->custom_members, ',' ) === FALSE AND $this->custom_members == \IPS\Member::loggedIn()->member_id )
			{
				$config['stream_custom_members'] = \IPS\Member::loggedIn()->name;
			}
			else
			{
				try
				{
					$config['stream_custom_members'] = iterator_to_array( \IPS\Db::i()->select( 'name', 'core_members', array( \IPS\Db::i()->in( 'member_id', explode( ',', $this->custom_members ) ) ) )->setValueField('name') );
				}
				catch( \Exception $e ) { }
			}
		}
		
		/* Tags */
		if( $this->tags )
		{
			$config['stream_tags'] = $this->tags;
		}
		
		/* Dates */
		if( $this->date_type == 'relative' )
		{
			$config['stream_date_relative_days'] = $this->date_relative_days;
		}
		elseif( $this->date_type == 'custom' )
		{
			$config['stream_date_range'] = array(
				'start' => \intval( $this->date_start ),
				'end' => \intval( $this->date_end )
			);
		}
		
		if ( $this->defaultConfig !== NULL )
		{
			/* Record what has been altered outside the class */
			$changed = array();
			foreach( $config as $k => $v )
			{
				if ( array_key_exists( $k, $this->defaultConfig ) and $v != $this->defaultConfig[ $k ] )
				{
					$changed[ $k ] = $this->defaultConfig[ $k ];
				}
			}
			
			$config['changed'] = $changed;
		}
		
		return $config;
	}
	
	/**
	 * Get results
	 *
	 * @param	\IPS\Member|null	$member	The member to get the results as
	 * @return	\IPS\Content\Search\Query
	 */
	public function query( $member = NULL )
	{
		/* Init */
		$query = \IPS\Content\Search\Query::init( $member );

		/* Get Member */
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Content Filters */
		$filters			= array();
		$allowedContainers	= $this->containers ? json_decode( $this->containers, TRUE ) : array();
		$classesChecked		= array();

		if ( $this->classes )
		{			
			/* Translate how we store this into the format needed for filterByContent */
			$classes = array();
			foreach ( explode( ',', $this->classes ) as $class )
			{
				if ( class_exists( $class ) )
				{
					$classes[ $class ]['items'] = TRUE;
					if ( $this->include_comments and isset( $class::$commentClass ) )
					{
						$classes[ $class ]['comments'] = TRUE;
					}
					if ( $this->include_comments and isset( $class::$reviewClass ) )
					{
						$classes[ $class ]['reviews'] = TRUE;
					}
				}
			}

			/* Build the filters */
			foreach ( $classes as $class => $options )
			{
				/* Init */
				if ( ! $this->include_comments or ( isset( $options['items'] ) and !isset( $options['comments'] ) and \in_array( 'IPS\Content\Item', class_parents( $class ) ) and $class::$firstCommentRequired ) )
				{
					/* As we do not want comments to appear separately, we want to only show the content item if no comments
							   or the last comment of the content item */
					$filter= \IPS\Content\Search\ContentFilter::init( $class, TRUE, TRUE, TRUE )->onlyLastComment();
				}
				else
				{
					$filter = \IPS\Content\Search\ContentFilter::init( $class, isset( $options['items'] ), isset( $options['comments'] ), isset( $options['reviews'] ) );
				}
				
				/* Are we restricted to certain containers? */
				if ( isset( $allowedContainers[ $class ] ) )
				{
					$filter->onlyInContainers( $allowedContainers[ $class ] );
				}
				
				/* Add to the array */
				$filters[] = $filter;
				$classesChecked[]	= $class;
			}
		}
		else
		{
			foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
			{
				foreach ( $object->classes as $class )
				{
					if ( \in_array( 'IPS\Content\Item', class_parents( $class ) ) )
					{
						if( !$this->include_comments )
						{
							/* As we do not want comments to appear separately, we want to only show the content item if no comments
							   or the last comment of the content item */
							$filters[] = \IPS\Content\Search\ContentFilter::init( $class, TRUE, TRUE, TRUE )->onlyLastComment();
						}

						$classesChecked[]	= $class;
					}
				}
			}
		}

		/* Give content item classes a chance to inspect and manipulate filters */
		foreach( $classesChecked as $itemClass )
		{
			$itemClass::searchEngineFiltering( $filters, $query );
		}

		if ( \count( $filters ) )
		{
			$query->filterByContent( $filters );
		}
		
		/* Clubs */
		if ( $this->clubs === '0' )
		{
			$query->filterByClub( NULL );
		}
		elseif ( $this->clubs )
		{
			$query->filterByClub( array_map( function( $val ) { return ltrim( $val, 'c' ); }, explode( ',', $this->clubs ) ) );
		}
		
		/* Ownership */
		switch ( $this->ownership )
		{
			case 'started':
				$query->filterByItemAuthor( $member );
				break;
			
			case 'postedin':
				$query->filterByItemsIPostedIn();
				break;
							
			case 'custom':
				$query->filterByAuthor( explode( ',', $this->custom_members ) );
				break;
		}
		
		/* Read */
		if ( $this->read == 'unread' )
		{
			$query->filterByUnread();
		}
				
		/* Follow */
		if ( $this->follow == 'followed' )
		{
			$followTypes = explode( ',', $this->followed_types );
			$query->filterByFollowed( \in_array( 'containers', $followTypes ), \in_array( 'items', $followTypes ), \in_array( 'members', $followTypes ) );
		}
		
		/* If we are showing all items (not grouping on last comment, then we need to filter by creation date */
		$filterDateMethod = ( $this->include_comments ) ? 'filterByCreateDate' : 'filterByLastUpdatedDate';
		
		/* Date */
		switch ( $this->date_type )
		{
			case 'last_visit':
				$query->$filterDateMethod( \IPS\DateTime::ts( $member->last_visit ) );
				break;
			case 'relative':
				if ( \is_array( $this->date_relative_days ) )
				{
					$query->$filterDateMethod( \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \intval( $this->date_relative_days['val'] ) . mb_strtoupper( $this->date_relative_days['unit'] ) ) ) );
				}
				else
				{
					$query->$filterDateMethod( \IPS\DateTime::create()->sub( new \DateInterval( 'P' . \intval( $this->date_relative_days ) . 'D' ) ) );
				}
			case 'custom':
				$query->$filterDateMethod( $this->date_start ? \IPS\DateTime::ts( $this->date_start ) : NULL, $this->date_end ? \IPS\DateTime::ts( $this->date_end ) : NULL );
				break;
		}

		/* Solved */
		if ( $this->solved == 'unsolved' )
		{
			$query->filterByUnsolved();
		}
		else if( $this->solved == 'solved' )
		{
			$query->filterBySolved();
		}
		
		/* Sort */
		if ( $this->include_comments )
		{
			if ( $this->sort === 'oldest' )
			{
				$query->setOrder( \IPS\Content\Search\Query::ORDER_OLDEST_CREATED );
			}
			else
			{
				$query->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_CREATED );
			}
		}
		else
		{
			if ( $this->sort === 'oldest' )
			{
				$query->setOrder( \IPS\Content\Search\Query::ORDER_OLDEST_CREATED );
			}
			else
			{
				$query->setOrder( \IPS\Content\Search\Query::ORDER_NEWEST_COMMENTED );
			}
		}
				
		/* Return */
		return $query;
	}
	
	/**
	 * URL to this stream
	 *
	 * @return	\IPS\Http\Url
	 */
	public function url()
	{
		return $this->baseUrl;
	}
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		
		$this->initBaseUrl();
		
		if ( !$this->member )
		{
			unset( \IPS\Data\Store::i()->globalStreamIds );
		}
		
		if ( $this->default )
		{
			unset( \IPS\Data\Store::i()->defaultStreamData );
		}
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Delete Subscriptions */
		\IPS\Db::i()->delete( 'core_stream_subscriptions', ['stream_id=?', $this->id ] );

		if ( $this->member )
		{
			parent::delete();
		}
		else
		{
			/* If it's in the menu, remove it */
			foreach( \IPS\Db::i()->select( '*', 'core_menu', array( "extension=?", 'YourActivityStreamsItem' ) ) AS $row )
			{
				$config = json_decode( $row['config'], true );
				if ( isset( $config['menu_stream_id'] ) )
				{
					if ( $config['menu_stream_id'] == $this->_id )
					{
						\IPS\Db::i()->delete( 'core_menu', array( 'id=?', $row['id'] ) );
						\IPS\Lang::deleteCustom( 'core', "menu_item_{$row['id']}" );
					}
				}
			}
			unset( \IPS\Data\Store::i()->frontNavigation );
			
			parent::delete();
			unset( \IPS\Data\Store::i()->globalStreamIds );
			
			if ( $this->default )
			{
				unset( \IPS\Data\Store::i()->defaultStreamData );
			}
		}
	}

	/**
	 * Can the member subscribe to this stream?
	 *
	 * @param \IPS\Member|null $member
	 * @return bool
	 */
	public function canSubscribe( \IPS\Member $member = NULL ) : bool
	{
		if( !\IPS\Settings::i()->activity_stream_subscriptions )
		{
			return FALSE;
		}

		if( !$this->id )
		{
			return FALSE;
		}

		$member = $member ?: \IPS\Member::loggedIn();

		if( !$member->member_id )
		{
			return FALSE;
		}

		if ( \IPS\Db::i()->select( 'COUNT(*)', 'core_stream_subscriptions', array( 'member_id=?', $member->member_id ) )->first() >= \IPS\Settings::i()->activity_stream_subscriptions_max )
		{
			return FALSE;
		}

		return !$this->isSubscribed($member);
	}

	/**
	 * Can the member unsubscribe from this stream?
	 *
	 * @param \IPS\Member|null $member
	 * @return bool
	 */
	public function canUnsubscribe( \IPS\Member $member = NULL ) : bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return $this->isSubscribed($member);
	}

	/**
	 * Are we already subscribed to this stream?
	 *
	 * @param \IPS\Member|null $member
	 * @return bool
	 */
	public function isSubscribed( \IPS\Member $member = NULL ) : bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_stream_subscriptions', array( 'stream_id=? AND member_id=?', $this->id, $member->member_id ) )->first();
	}
	
	/**
	 * [Node] Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	protected function get__badge()
	{
		$badge = NULL;
		if ( $this->default )
		{
			$badge	= array(
				0	=> 'positive ipsPos_right',
				1	=> 'default_no_parenthesis'
			);
		}

		return $badge;
	}
}
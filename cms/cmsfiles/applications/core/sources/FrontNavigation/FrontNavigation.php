<?php
/**
 * @brief		Front Navigation Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		30 Jun 2015
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Custom Item
 */
class _FrontNavigation
{
	/**
	 * @brief	Singleton Instances
	 * @note	This needs to be declared in any child classes as well, only declaring here for editor code-complete/error-check functionality
	 */
	protected static $instance = NULL;
	
	/**
	 * @brief	This is a hacky flag to indicate if active page is a club area so we know which tab to highlight in the menu
	 */
	public static $clubTabActive = FALSE;
	
	/**
	 * @brief	Store root objects for re-use later
	 */
	protected $roots = NULL;
	
	/**
	 * @brief	Store subBars objects for later use
	 */
	protected $subBars = NULL;
	
	/**
	 * Get instance
	 *
	 * @return	static
	 */
	public static function i()
	{
		if( static::$instance === NULL )
		{
			$classname = \get_called_class();
			static::$instance = new $classname;
		}
		
		return static::$instance;
	}
	
	/**
	 * Get data store
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public static function frontNavigation( $noStore=FALSE )
	{
		if ( $noStore or !isset( \IPS\Data\Store::i()->frontNavigation ) )
		{
			$frontNavigation = array( 0 => array(), 1 => array() );
			$select = \IPS\Db::i()->select( '*', 'core_menu', NULL, 'position' );
			if ( \count( $select ) )
			{
				foreach ( $select as $item )
				{
					if ( \IPS\Application::appIsEnabled( $item['app'] ) )
					{
						$frontNavigation[ \intval( $item['parent'] ) ][ $item['id'] ] = $item;
					}
				}
			}
			if ( $noStore )
			{
				return $frontNavigation;
			}
			\IPS\Data\Store::i()->frontNavigation = $frontNavigation;
		}
		return \IPS\Data\Store::i()->frontNavigation;
	}
	
	/**
	 * Delete front navigation items by application
	 *
	 * @param	\IPS\Application	$app	Application deleted
	 * @return	void
	 */
	public static function deleteByApplication( \IPS\Application $app )
	{
		foreach( \IPS\Db::i()->select( '*', 'core_menu', array( array( 'extension=?', 'CustomItem' ) ) ) as $row )
		{
			$config = json_decode( $row['config'], TRUE );
		
			if ( isset( $config['menu_custom_item_url'] ) and $config['menu_custom_item_url'] and isset( $config['internal'] ) and $config['internal'] )
			{
				try
				{
					parse_str( $config['menu_custom_item_url'], $data );
					
					if ( ! empty( $data['app'] ) and $data['app'] === $app->directory )
					{
						\IPS\Db::i()->delete( 'core_menu', array( 'id=?', $row['id'] ) );
					}
				}
				catch( \Exception $e ) { }
			}
		}
		
		\IPS\Db::i()->delete( 'core_menu', array( 'app=?', $app->directory ) );
		
		unset( \IPS\Data\Store::i()->frontNavigation );
	}
		
	/**
	 * Build default front navigation
	 *
	 * @return	void
	 */
	public static function buildDefaultFrontNavigation()
	{		
		\IPS\Db::i()->delete( 'core_menu' );
		
		$position = 1;
				
		/* Browse */
		\IPS\Db::i()->insert( 'core_menu', array(
			'id'			=> 1,
			'app'			=> 'core',
			'extension'		=> 'CustomItem',
			'config'		=> json_encode( array( 'menu_custom_item_url' => '', 'internal' => '' ) ),
			'position'		=> $position++,
			'parent'		=> NULL,
			'permissions'	=> NULL,
		) );
		\IPS\Lang::saveCustom( 'core', "menu_item_1", \IPS\Member::loggedIn()->language()->get('default_menu_item_1') );

		/* Activity */
		\IPS\Db::i()->insert( 'core_menu', array(
			'id'			=> 2,
			'app'			=> 'core',
			'extension'		=> 'CustomItem',
			'config'		=> json_encode( array( 'menu_custom_item_url' => 'app=core&module=discover&controller=streams', 'internal' => 'discover_all' ) ),
			'position'		=> $position++,
			'parent'		=> NULL,
			'permissions'	=> NULL,
		) );
		\IPS\Lang::saveCustom( 'core', "menu_item_2", \IPS\Member::loggedIn()->language()->get('default_menu_item_2') );
		
		/* Loop */
		$waiting = array();
		foreach ( \IPS\Application::applications() as $app )
		{
			if ( \IPS\Application::appIsEnabled( $app->directory ) )
			{
				$defaultNavigation = $app->defaultFrontNavigation();
				foreach ( $defaultNavigation as $type => $tabs )
				{
					foreach ( $tabs as $config )
					{
						switch ( $type )
						{
							case 'rootTabs':
								$parent = NULL;
								break;
							case 'browseTabs':
								$parent = 1;
								break;
							case 'activityTabs':
								$parent = 2;
								break;
						}
						
						$config['real_app'] = $app->directory;
						if ( !isset( $config['app'] ) )
						{
							$config['app'] = $app->directory;
						}
						
						if ( $type == 'browseTabsEnd' )
						{
							$waiting[] = $config;
						}
						else
						{
							static::insertMenuItem( $parent, $config, $position );
						}
					}
				}
			}
		}
		foreach ( $waiting as $config )
		{
			static::insertMenuItem( 1, $config, $position );
		}
	}
	
	/**
	 * Insert a menu item
	 *
	 * @param	int		$parent			Parent ID
	 * @param	array	$config			Configuration
	 * @param	int		$position		Position
	 * @param	bool	$isMenuChild	Is item in a menu?
	 * @return	void
	 */
	public static function insertMenuItem( $parent, $config, $position, $isMenuChild=FALSE )
	{
		$insertedId = \IPS\Db::i()->insert( 'core_menu', array(
			'app'			=> $config['app'],
			'extension'		=> $config['key'],
			'config'		=> json_encode( isset( $config['config'] ) ? $config['config'] : array() ),
			'position'		=> ( \intval( $position ) + 1 ),
			'parent'		=> $parent,
			'permissions'	=> NULL,
			'is_menu_child'	=> $isMenuChild,
		) );
		
		if ( isset( $config['title'] ) )
		{
			\IPS\Lang::copyCustom( $config['real_app'], $config['title'], "menu_item_{$insertedId}" );
		}
		
		if ( isset( $config['children'] ) )
		{
			foreach ( $config['children'] as $childConfig )
			{
				$childConfig['real_app'] = $config['real_app'];
				if ( !isset( $childConfig['app'] ) )
				{
					$childConfig['app'] = $config['real_app'];
				}
						
				static::insertMenuItem( $insertedId, $childConfig, $position, $config['app'] == 'core' and $config['key'] == 'Menu' );
			}
		}
	}
	
	/**
	 * @brief	The active primary navigation bar
	 */
	public $activePrimaryNavBar = NULL;
	
	/**
	 * Get roots
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function roots( $noStore=FALSE )
	{
		if ( $this->roots === NULL )
		{
			$this->roots = array();
			$frontNavigation = static::frontNavigation( $noStore );
			$return = array();
			foreach ( $frontNavigation[0] as $item )
			{
				$class = 'IPS\\' . $item['app'] . '\extensions\core\FrontNavigation\\' . $item['extension'];
				if ( class_exists( $class ) )
				{
					$object = new $class( json_decode( $item['config'], TRUE ), $item['id'], $item['permissions'] );
					if ( !$this->activePrimaryNavBar )
					{
						$this->activePrimaryNavBar = $item['id'];
					}
					$this->roots[ $item['id'] ] = $object;
				}
			}
		}
	
		return $this->roots;
	}

	/**
	 * Get sub-bars
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function subBars( $noStore=FALSE )
	{
		if ( $this->subBars === NULL )
		{
			$this->subBars = array();
			$frontNavigation = static::frontNavigation( $noStore );
			$parentIDs = array();
			// Changed so that empty sub bars don't add an array to their parent, allowing us to do \count( $subBars ) and figure
			// out if there's any to show.
			foreach ( $frontNavigation[0] as $item )
			{
				$parentIDs[] = $item['id'];
			}

			foreach ( $parentIDs as $i )
			{
				if ( isset( $frontNavigation[$i] ) )
				{
					foreach ( $frontNavigation[$i] as $item )
					{
						if ( empty( $item['is_menu_child'] ) )
						{
							$class = 'IPS\\' . $item['app'] . '\extensions\core\FrontNavigation\\' . $item['extension'];
							if ( class_exists( $class ) )
							{
								$this->subBars[ $item['parent'] ][ $item['id'] ] = new $class( json_decode( $item['config'], TRUE ), $item['id'], $item['permissions'] );
							}
						}
					}
				}
			}
		}

		return $this->subBars;
	}
}
<?php
/**
 * @brief		Reaction Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Nov 2016
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Reaction Model
 */
class _Reaction extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_reactions';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'reaction_';
	
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
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'reactions';
	
	/**
	 * @brief	[Node] Sortable
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Positon Column
	 */
	public static $databaseColumnOrder = 'position';
	
	/**
	 * @brief	[Node] Modal Forms because Charles loves them so
	 */
	public static $modalForms = TRUE;
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'reaction_title_';
	
	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'enabled';

	/**
	 * @brief Icon Cache
	 */
	public static $icons = array();

	/**
	 * Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'reaction_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? 'reaction_title_' . $this->id : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'reaction_value', $this->id ? $this->value : 1, TRUE, array( 'options' => array( 1 => 'positive', 0 => 'neutral', -1 => 'negative' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'reaction_icon', $this->id ? \IPS\File::get( 'core_Reaction', $this->icon ) : NULL, TRUE, array( 'image' => TRUE, 'storageExtension' => 'core_Reaction', 'storageContainer' => 'reactions', 'obscure' => FALSE ) ) );
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->save();
		}
		
		\IPS\Lang::saveCustom( 'core', 'reaction_title_' . $this->id, $values['reaction_title'] );
		unset( $values['reaction_title'] );
		
		return parent::formatFormValues( $values );
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to delete this node?
	 *
	 * @return	bool
	 */
	public function canDelete()
	{
		if ( $this->id === 1 )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Get Icon
	 *
	 * @return	\IPS\File
	 */
	public function get__icon()
	{
		if ( !isset( static::$icons[ $this->id ] ) )
		{
			static::$icons[ $this->id ] = \IPS\File::get( 'core_Reaction', $this->_data['icon'] );
		}

		return static::$icons[ $this->id ];
	}

	/**
	 * Get Description
	 *
	 * @return	string
	 */
	public function get__description()
	{
		if ( $this->value == 1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('positive');
		}
		elseif ( $this->value == -1 )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('negative');
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack('neutral');
		}
	}
	
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
		if ( !\count( $where ) )
		{
			$cacheKey	= md5( \get_called_class() . $permissionCheck );

			if( isset( static::$rootsResult[ $cacheKey ] ) )
			{
				return static::$rootsResult[ $cacheKey ];
			}

			static::$rootsResult[ $cacheKey ]	= array();
			foreach( static::getStore() AS $reaction )
			{
				static::$rootsResult[ $cacheKey ][ $reaction['reaction_id'] ] = static::constructFromData( $reaction );
			}
			
			return static::$rootsResult[ $cacheKey ];
		}
		else
		{
			return parent::roots( $permissionCheck, $member, $where, $limit );
		}
	}
	
	/**
	 * [Node] Get whether or not this node is enabled
	 *
	 * @note	Return value NULL indicates the node cannot be enabled/disabled
	 * @return	bool|null
	 */
	protected function get__enabled()
	{
		if ( $this->id == 1 )
		{
			return NULL;
		}
		
		return parent::get__enabled();
	}

	/**
	 * Reaction Store
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->reactions ) )
		{
			\IPS\Data\Store::i()->reactions = iterator_to_array( \IPS\Db::i()->select( '*', 'core_reactions', NULL, "reaction_position ASC" )->setKeyField( 'reaction_id' ) );
		}
		
		return \IPS\Data\Store::i()->reactions;
	}
	
	/**
	 * Is Like Mode
	 *
	 * @return	bool
	 */
	public static function isLikeMode()
	{
		return (bool) \IPS\Settings::i()->reaction_is_likemode;
	}
	
	/**
	 * @brief	[ActiveRecord] Attempt to load from cache
	 * @note	If this is set to TRUE you MUST define a getStore() method to return the objects from cache
	 */
	protected static $loadFromCache = TRUE;

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'reactions' );

	/**
	 * Clear any defined caches
	 *
	 * @param	bool	$removeMultiton		Should the multiton record also be removed?
	 * @return void
	 */
	public function clearCaches( $removeMultiton=FALSE )
	{
		parent::clearCaches( $removeMultiton );

		static::updateLikeModeSetting();
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	array(
	array(
	'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	'title'	=> 'foo',		// Language key to use for button's title parameter
	'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	),
	...							// Additional buttons
	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = parent::getButtons( $url, $subnode );
		
		if ( $this->canDelete() )
		{
			$buttons['delete'] = array(
				'icon'	=> 'times-circle',
				'title'	=> 'delete',
				'link'	=> $url->setQueryString( array( 'do' => 'delete', 'id' => $this->_id ) ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('delete') ),
				'hotkey'=> 'd'
			);
		}
		return $buttons;
	}

	/**
	 * Update the setting which stores if likemode is enabled ( = only one reaction is being used )
	 *
	 * @return void
	 */
	public static function updateLikeModeSetting()
	{
		\IPS\Settings::i()->changeValues( array( 'reaction_is_likemode' => \intval( \count( static::enabledReactions() ) == 1 ) ) );
	}

	/**
	 * Return enabled reactions
	 *
	 * @return array
	 */
	public static function enabledReactions()
	{
		return array_filter( 
			static::roots(), 
			function( $reaction ){
				return ( $reaction->_enabled !== FALSE );
			}
		);
	}

	/**
	 * [ActiveRecord] Duplicate
	 *
	 * @return	void
	 */
	public function __clone()
	{
		if ( $this->skipCloneDuplication === TRUE )
		{
			return;
		}

		$oldIcon = $this->icon;

		parent::__clone();

			try
			{
				$icon = \IPS\File::get( 'core_Reaction', $oldIcon );
				$newIcon = \IPS\File::create( 'core_Reaction', $icon->originalFilename, $icon->contents() );
				$this->icon = (string) $newIcon;
			}

			catch ( \Exception $e )
			{
				$this->icon = NULL;
			}

			$this->save();
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		try
		{
			\IPS\File::get( 'core_Reaction', $this->icon )->delete();
		}
		catch( \Exception $ex ) { }

		parent::delete();
	}
}
<?php
/**
 * @brief		Automatic Content Moderation Rules
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		7 Dec 2017
 */

namespace IPS\core\Reports;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Group promotion node model
 */
class _Rules extends \IPS\Node\Model
{
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_automatic_moderation_rules';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'rule_';
	
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
	public static $nodeTitle = 'automaticmoderation';
	
	/**
	 * @brief	[Node] Sortable
	 */
	public static $nodeSortable = TRUE;
	
	/**
	 * @brief	[Node] Positon Column
	 */
	public static $databaseColumnOrder = 'position';

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'automaticmoderation_';
	
	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'enabled';
	
	/**
	 * Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader( 'automaticmoderation_generic_details' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'automaticmoderation_title', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->id ? 'automaticmoderation_' . $this->id : NULL ) ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'automaticmoderation_enabled', $this->id ? $this->enabled : 1, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Number( 'automaticmoderation_points', $this->id ? $this->points_needed : 10, TRUE, array( 'min' => 1, 'max' => 9999 ), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('automaticmoderation_points_suffix') ) );
		
		$options = array();
		foreach( \IPS\core\Reports\Types::roots() as $type )
		{
			$options[ $type->id ] = $type->_title;
		}
		
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'automaticmoderation_type', $this->types ? explode( ',', $this->types ) : array(), TRUE, array( 'options' => $options ) ) );

		/* Loop over our member filters */
		$form->addHeader( 'automaticmoderation_generic_filters' );
		$options	= $this->id ? $this->_filters : array();

		$lastApp	= 'core';

		/* We take an extra step with groups to disable invalid options */
		//$options['core_Group']['disabled_groups']	= $this->getDisabledGroups();

		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getSettingField' ) AND $extension->availableIn( 'automatic_moderation' ) )
			{
				/* See if we need a new form header - one per app */
				$_key		= explode( '_', $key );

				if( $_key[0] != $lastApp )
				{
					$lastApp	= $_key[0];
					$form->addHeader( $lastApp . '_bm_filters' );
				}

				/* Grab our fields and add to the form */
				$fields		= $extension->getSettingField( !empty( $options[ $key ] ) ? $options[ $key ] : array() );

				foreach( $fields as $field )
				{
					$form->add( $field );
				}
			}
		}
	}

	/**
	 * Return an array of groups that cannot be promoted
	 *
	 * @return array
	 */
	protected function getDisabledGroups()
	{
		$return = array( \IPS\Settings::i()->guest_group );

		foreach( \IPS\Member\Group::groups() as $group )
		{
			if( $group->g_promote_exclude )
			{
				$return[] = $group->g_id;
			}
		}

		return $return;
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
		
		/* Save the title */
		\IPS\Lang::saveCustom( 'core', 'automaticmoderation_' . $this->id, $values['automaticmoderation_title'] );

		/* Json-encode the rules */
		$_options	= array();

		/* Loop over bulk mail extensions to format the options */
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'save' ) AND $extension->availableIn( 'automatic_moderation' ) )
			{
				/* Grab our fields and add to the form */
				$_value = $extension->save( $values );

				if( $_value )
				{
					$_options[ $key ]	= $_value;
				}
			}
		}

		$values['rule_filters'] = json_encode( $_options );
		$values['rule_points_needed'] = $values['automaticmoderation_points'];
		$values['rule_enabled']	= $values['automaticmoderation_enabled'];
		$values['rule_types'] = $values['automaticmoderation_type'];
		
		/* Now we have to remove any fields that aren't valid... */
		foreach( $values as $k => $v )
		{
			if( !\in_array( $k, array( 'rule_filters', 'rule_points_needed', 'rule_enabled', 'rule_types', 'rule_position' ) ) )
			{
				unset( $values[ $k ] );
			}
		}

		return parent::formatFormValues( $values );
	}

	/**
	 * Return our filters as an array
	 *
	 * @return array
	 */
	public function get__filters()
	{
		return json_decode( $this->filters, TRUE );
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
			$return = array();
			foreach( static::getStore() AS $node )
			{
				$return[ $node['rule_id'] ] = static::constructFromData( $node );
			}
			
			return $return;
		}
		else
		{
			return parent::roots( $permissionCheck, $member, $where, $limit );
		}
	}

	/**
	 * Get data store
	 *
	 * @return	array
	 * @note	Note that all records are returned, even disabled report rules. Enable status needs to be checked in userland code when appropriate.
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->automatic_moderation_rules ) )
		{
			\IPS\Data\Store::i()->automatic_moderation_rules = iterator_to_array( \IPS\Db::i()->select( '*', static::$databaseTable, NULL, "rule_position ASC" )->setKeyField( 'rule_id' ) );
		}
		
		return \IPS\Data\Store::i()->automatic_moderation_rules;
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
	protected $caches = array( 'automatic_moderation_rules' );

	/**
	 * @brief	Cache extensions so we only need to load them once
	 */
	protected static $extensions = NULL;

	/**
	 * Check if a member matches the rule
	 *
	 * @param	\IPS\Member		$member			Member to check
	 * @param	array			$typeCounts		Array of type counts indexed by report_type (constants in \IPS\core\Reports\Report)
	 * @return	bool
	 */
	public function matches( \IPS\Member $member, $typeCounts=array() )
	{
		if ( ! \IPS\Settings::i()->automoderation_enabled )
		{
			return FALSE;
		}
		
		if( static::$extensions === NULL )
		{
			static::$extensions = \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' );
		}
		
		/* Nothing flagged as bad? Back you go */
		if ( ! \count( $typeCounts ) )
		{
			return FALSE;
		}
		
		/* Author can bypass? */
		if ( $member->group['gbw_immune_auto_mod'] )
		{
			return FALSE;
		}
		
		/* Match the count threshold */
		$count = 0;
		foreach( explode( ',', $this->types ) as $type )
		{
			if ( isset( $typeCounts[ $type ] ) )
			{
				$count += $typeCounts[ $type ];
			}
		}
		
		if ( $count < $this->points_needed )
		{
			/* You lose */
			return FALSE;
		}
		
		/* Loop over the filters */
		foreach( $this->_filters as $key => $filter )
		{
			if( isset( static::$extensions[ $key ] ) AND method_exists( static::$extensions[ $key ], 'matches' ) )
			{
				/* Ask the extension if this member matches the defined rule...if not, just return FALSE now */
				if( !static::$extensions[ $key ]->matches( $member, $filter, $this ) )
				{
					return FALSE;
				}
			}
		}

		/* If we are still here, then the rule matched */
		return TRUE;
	}

	/* ! ACP STUFF */
	
	/**
	 * [Node] Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	protected function get__badge()
	{
		return array(
			0 => 'ipsBadge ipsBadge_positive',
			1 => \IPS\Member::loggedIn()->language()->addToStack( 'automoderation_points_needed_badge', FALSE, array( 'pluralize' => array( $this->points_needed	) ) )
		);
	}
	
	/**
	 * Return a warning if this promotion uses not existing groups
	 *
	 * @return	string
	 */
	public function get__description()
	{
		if( static::$extensions === NULL )
		{
			static::$extensions = \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' );
		}

		/* Loop over the filters */
		$descriptions = array();
		foreach( $this->_filters as $key => $filter )
		{
			if( isset( static::$extensions[ $key ] ) )
			{
				/* Ask the extension if this member matches the defined rule...if not, just return FALSE now */
				if( method_exists( static::$extensions[ $key ], 'getDescription' ) and $description = static::$extensions[ $key ]->getDescription( $filter ) )
				{
					$descriptions[] = $description; /* Is this descriptive enough? */
				}
			}
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( 'automaticmoderation_row_desc', TRUE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $descriptions ) ) ) );
	}

	/**
	 * Returns the hide reason language string
	 *
	 * @param	null|int	$languageId	Language ID (or NULL to use default language)
	 * @return	string
	 */
	public static function getDefaultHideReason( $languageId = NULL )
	{
		$languageId = $languageId ? : \IPS\Lang::defaultLanguage() ;
		return \IPS\Lang::load( $languageId )->get( 'automaticmoderation_hide_reason' );
	}
}
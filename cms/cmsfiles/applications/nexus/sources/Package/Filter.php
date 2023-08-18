<?php
/**
 * @brief		Package Filter
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		22 Aug 2018
 */

namespace IPS\nexus\Package;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Package Filter
 */
class _Filter extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_package_filters';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'pfilter_';
		/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'order';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'menu__nexus_store_filters';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_product_filter_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_public';

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
		'app'		=> 'nexus',
		'module'	=> 'store',
		'all'		=> 'packages_manage',
	);
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->addHeader( 'pfilter_basic_settings' );
		$form->add( new \IPS\Helpers\Form\Translatable( 'pfilter_admin_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_product_filter_{$this->id}" : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'pfilter_public_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_product_filter_{$this->id}_public" : NULL ) ) );
		
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->sortable = TRUE;
		foreach ( \IPS\Lang::languages() as $lang )
		{
			if ( $lang->enabled )
			{
				\IPS\Member::loggedIn()->language()->words["lang_{$lang->id}"] = $lang->title;
				$matrix->columns["lang_{$lang->id}"] = function( $key, $value, $data )
				{
					return new \IPS\Helpers\Form\Text( $key, $value );
				};
			}
		}
		if ( $this->id )
		{
			foreach ( \IPS\Db::i()->select( '*', 'nexus_package_filters_values', array( 'pfv_filter=?', $this->id ), 'pfv_order' ) as $filterValue )
			{
				$matrix->rows[ $filterValue['pfv_value'] ][ 'lang_' . $filterValue['pfv_lang'] ] = $filterValue['pfv_text'];
			}
		}
		
		$form->addHeader( 'pfilter_options' );
		$form->addMatrix( 'pfilter_options', $matrix );
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
			
		if( isset( $values['pfilter_admin_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_product_filter_{$this->id}", $values['pfilter_admin_name'] );
			unset( $values['pfilter_admin_name'] );
		}

		if( isset( $values['pfilter_public_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_product_filter_{$this->id}_public", $values['pfilter_public_name'] );
			unset( $values['pfilter_public_name'] );
		}
				
		$order = 1;
		$deletedIds = $this->id ? iterator_to_array( \IPS\Db::i()->select( 'pfv_value', 'nexus_package_filters_values', array( 'pfv_filter=?', $this->id ) )->setKeyField('pfv_value') ) : array();
		foreach ( $values['pfilter_options'] as $k => $_values )
		{
			unset( $deletedIds[ $k ] );
			
			if ( mb_substr( $k, 0, 5 ) === '_new_' )
			{
				$id = \IPS\Db::i()->select( 'MAX(pfv_value)', 'nexus_package_filters_values', array( 'pfv_filter=?', $this->id ) )->first() + 1;
				
				foreach ( $_values as $langId => $text )
				{
					\IPS\Db::i()->insert( 'nexus_package_filters_values', array(
						'pfv_filter'	=> $this->id,
						'pfv_value'		=> $id,
						'pfv_lang'		=> \intval( mb_substr( $langId, 5 ) ),
						'pfv_text'		=> $text,
						'pfv_order'		=> $order
					) );
				}
			}
			else
			{
				foreach ( $_values as $langId => $text )
				{
					\IPS\Db::i()->update( 'nexus_package_filters_values', array(
						'pfv_text'		=> $text,
						'pfv_order'		=> $order
					), array( 'pfv_filter=? AND pfv_value=? AND pfv_lang=?', $this->id, $k, \intval( mb_substr( $langId, 5 ) ) ) );
				}
			}
			
			$order++;
		}
		if ( $deletedIds )
		{
			\IPS\Db::i()->delete( 'nexus_package_filters_values', \IPS\Db::i()->in( 'pfv_value', $deletedIds ) );
		}
		unset( $values['pfilter_options'] ); 
		
		return $values;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'nexus_package_filters_values', array( 'pfv_filter=?', $this->id ) );
		\IPS\Lang::deleteCustom( 'nexus', static::$titleLangPrefix . $this->_id );
		\IPS\Lang::deleteCustom( 'nexus', static::$titleLangPrefix . $this->_id . static::$descriptionLangSuffix );
		parent::delete();
	}
	
	/**
	 * Work out filter query string value
	 *
	 * @param	array		$queryString	Query string
	 * @param	int			$filterId		ID of the filter
	 * @param	int|null	$valueToAdd		Value to add to the filter array
	 * @param	int|null	$valueToRemove	Value to remove from the filter array
	 * @return	void
	 */
	public static function queryString( $queryString, $filterId, $valueToAdd=NULL, $valueToRemove = NULL )
	{
		$queryString = $queryString ?: array();
		
		if ( $valueToAdd )
		{
			if ( isset( $queryString[ $filterId ] ) )
			{
				$new = explode( ',', $queryString[ $filterId ] );
				$new[] = $valueToAdd;
				$queryString[ $filterId ] = implode( ',', $new );
			}
			else
			{
				$queryString[ $filterId ] = $valueToAdd;
			}
		}
		elseif ( $valueToRemove and isset( $queryString[ $filterId ] ) )
		{
			$new = explode( ',', $queryString[ $filterId ] );
			unset( $new[ array_search( $valueToRemove, $new ) ] );
			if ( $new )
			{
				$queryString[ $filterId ] = implode( ',', $new );
			}
			else
			{
				unset( $queryString[ $filterId ] );
			}
		}
		
		return $queryString;
	}
}
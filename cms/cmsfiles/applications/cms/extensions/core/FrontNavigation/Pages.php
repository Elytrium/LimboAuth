<?php
/**
 * @brief		Front Navigation Extension: Pages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		01 Jul 2015
 */

namespace IPS\cms\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Pages
 */
class _Pages extends \IPS\core\FrontNavigation\FrontNavigationAbstract
{
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('menu_content_page');
	}
	
	/**
	 * Allow multiple instances?
	 *
	 * @return	bool
	 */
	public static function allowMultiple()
	{
		return TRUE;
	}
	
	/**
	 * Get configuration fields
	 *
	 * @param	array	$existingConfiguration	The existing configuration, if editing an existing item
	 * @param	int		$id						The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function configuration( $existingConfiguration, $id = NULL )
	{
		$pages = array();
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_pages' ), 'IPS\cms\Pages\Page' ) as $page )
		{
			$pages[ $page->id ] = $page->full_path;
		}
		
		return array(
			new \IPS\Helpers\Form\Select( 'menu_content_page', isset( $existingConfiguration['menu_content_page'] ) ? $existingConfiguration['menu_content_page'] : NULL, NULL, array( 'options' => $pages ), NULL, NULL, NULL, 'menu_content_page' ),
			new \IPS\Helpers\Form\Radio( 'menu_title_page_type', isset( $existingConfiguration['menu_title_page_type'] ) ? $existingConfiguration['menu_title_page_type'] : 0, NULL, array( 'options' => array( 0 => 'menu_title_page_inherit', 1 => 'menu_title_page_custom' ), 'toggles' => array( 1 => array( 'menu_title_page' ) ) ), NULL, NULL, NULL, 'menu_title_page_type' ),
			new \IPS\Helpers\Form\Translatable( 'menu_title_page', NULL, NULL, array( 'app' => 'cms', 'key' => $id ? "cms_menu_title_{$id}" : NULL ), NULL, NULL, NULL, 'menu_title_page' ),
		);
	}
	
	/**
	 * Parse configuration fields
	 *
	 * @param	array	$configuration	The values received from the form
	 * @param	int		$id				The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function parseConfiguration( $configuration, $id )
	{
		if ( $configuration['menu_title_page_type'] )
		{
			\IPS\Lang::saveCustom( 'cms', "cms_menu_title_{$id}", $configuration['menu_title_page'] );
		}
		else
		{
			\IPS\Lang::deleteCustom( 'cms', "cms_menu_title_{$id}" );
		}
		
		unset( $configuration['menu_title_page'] );
		
		return $configuration;
	}
		
	/**
	 * Can access?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		if ( $this->permissions )
		{
			if ( $this->permissions != '*' )
			{
				return \IPS\Member::loggedIn()->inGroup( explode( ',', $this->permissions ) );
			}
			
			return TRUE;
		}
		
		/* Inherit from page */
		$store = \IPS\cms\Pages\Page::getStore();

		if ( isset( $store[ $this->configuration['menu_content_page'] ] ) )
		{
			if ( $store[ $this->configuration['menu_content_page'] ]['perm'] != '*' )
			{
				return \IPS\Member::loggedIn()->inGroup( explode( ',', $store[ $this->configuration['menu_content_page'] ]['perm'] ) );
			}
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		if ( $this->configuration['menu_title_page_type'] )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( "cms_menu_title_{$this->id}" );
		}
		else
		{
			$page = \IPS\cms\Pages\Page::load( $this->configuration['menu_content_page'] );
			
			if( $database = $page->getDatabase() and $database->pageTitle() )
			{
				return $database->pageTitle();
			}
			else
			{
				return \IPS\Member::loggedIn()->language()->addToStack( "cms_page_{$this->configuration['menu_content_page']}" );
			}	
		}
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		$store = \IPS\cms\Pages\Page::getStore();
		
		if ( isset( $store[ $this->configuration['menu_content_page'] ] ) )
		{
			return $store[ $this->configuration['menu_content_page'] ]['url'];
		}
		
		/* Fall back here */
		return \IPS\cms\Pages\Page::load( $this->configuration['menu_content_page'] )->url();
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return ( \IPS\cms\Pages\Page::$currentPage and \IPS\cms\Pages\Page::$currentPage->id == $this->configuration['menu_content_page'] );
	}
	
	/**
	 * Children
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function children( $noStore=FALSE )
	{
		return array();
	}
}
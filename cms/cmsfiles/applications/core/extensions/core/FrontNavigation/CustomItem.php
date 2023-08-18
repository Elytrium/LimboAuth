<?php
/**
 * @brief		Front Navigation Extension: Custom Item
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		21 Jan 2015
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Custom Item
 */
class _CustomItem extends \IPS\core\FrontNavigation\FrontNavigationAbstract
{
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('menu_custom_item');
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
		$currentUrl = NULL;
		if ( isset( $existingConfiguration['menu_custom_item_url'] ) )
		{
			if ( isset( $existingConfiguration['internal'] ) )
			{
				$currentUrl = (string) \IPS\Http\Url::internal( $existingConfiguration['menu_custom_item_url'], 'front', $existingConfiguration['internal'], isset( $existingConfiguration['seoTitles'] ) ? $existingConfiguration['seoTitles'] : array() );
			}
			else
			{
				$currentUrl = $existingConfiguration['menu_custom_item_url'];
			}
		}
		
		return array(
			new \IPS\Helpers\Form\Translatable( 'menu_custom_item_link', NULL, NULL, array( 'app' => 'core', 'key' => $id ? "menu_item_{$id}" : NULL ), function( $val )
			{
				if ( !trim( $val[ \IPS\Lang::defaultLanguage() ] ) )
				{
					throw new \InvalidArgumentException('form_required');
				}
			} ),
			new \IPS\Helpers\Form\Url( 'menu_custom_item_url', $currentUrl, NULL, array(), function( $val )
			{
				if ( isset( \IPS\Request::i()->menu_manager_extension ) and \IPS\Request::i()->menu_manager_extension === 'core_CustomItem' and empty( $val ) )
				{
					throw new \InvalidArgumentException('form_required');
				}
			} ),
			new \IPS\Helpers\Form\YesNo( 'menu_custom_item_target_blank', isset( $existingConfiguration['menu_custom_item_target_blank'] ) ? $existingConfiguration['menu_custom_item_target_blank'] : FALSE )
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
		$baseUrl = \IPS\Http\Url::internal('', 'front');
		
		if ( $configuration['menu_custom_item_url'] instanceof \IPS\Http\Url\Friendly )
		{
			$configuration['internal'] = $configuration['menu_custom_item_url']->seoTemplate;
			$configuration['seoTitles'] = $configuration['menu_custom_item_url']->seoTitles;
			$configuration['menu_custom_item_url'] = http_build_query( $configuration['menu_custom_item_url']->hiddenQueryString + $configuration['menu_custom_item_url']->queryString, '', '&' );
		}
		else
		{
			$configuration['menu_custom_item_url'] = (string) $configuration['menu_custom_item_url'];
		}
				
		\IPS\Lang::saveCustom( 'core', "menu_item_{$id}", $configuration['menu_custom_item_link'] );
		unset( $configuration['menu_custom_item_link'] );
		
		return $configuration;
	}
	
	/**
	 * Permissions can be inherited?
	 *
	 * @return	bool
	 */
	public static function permissionsCanInherit()
	{
		return FALSE;
	}
		
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( "menu_item_{$this->id}" );
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		if ( isset( $this->configuration['menu_custom_item_url'] ) and ( $this->configuration['menu_custom_item_url'] or isset( $this->configuration['internal'] ) ) )
		{
			if ( isset( $this->configuration['internal'] ) )
			{
				return \IPS\Http\Url::internal( $this->configuration['menu_custom_item_url'], 'front', $this->configuration['internal'], isset( $this->configuration['seoTitles'] ) ? $this->configuration['seoTitles'] : array() );
			}
			else
			{
				return \IPS\Http\Url::external( $this->configuration['menu_custom_item_url'] );
			}
		}
		else
		{
			return '#';
		}		
	}
	
	/**
	 * Get target (e.g. '_blank')
	 *
	 * @return	string
	 */
	public function target()
	{
		if ( isset( $this->configuration['menu_custom_item_target_blank'] ) and $this->configuration['menu_custom_item_target_blank'] )
		{
			return '_blank';
		}
		else
		{
			return '';
		}		
	}
}
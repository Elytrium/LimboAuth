<?php
/**
 * @brief		4.4.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		25 Jun 2018
 */

namespace IPS\cms\setup\upg_104000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.4.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Category Permissions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* @note We wouldn't normally use an upgrade.php for an update query, however this query is semi-complex due to the sub-select, so it confuses the dev center. */
		\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND '. \IPS\Db::i()->in( 'perm_type_id', array_values( iterator_to_array( \IPS\Db::i()->select( 'category_id', 'cms_database_categories' ) ) ), TRUE ), 'cms', 'categories' ) ); # Fix orphans
		\IPS\Db::i()->update( 'core_permission_index', 'perm_type=CONCAT( perm_type, \'_\', (' . (string) \IPS\Db::i()->select( 'category_database_id', 'cms_database_categories', array( "category_id=perm_type_id" ) ) . ') )', array( "app=? AND perm_type=?", 'cms', 'categories' ) );
		
		foreach( \IPS\Db::i()->select( '*', 'cms_database_categories' ) AS $cat )
		{
			\IPS\Db::i()->update( 'core_tags', array( 'tag_aap_lookup' => md5( "cms;categories_{$cat['category_database_id']};{$cat['category_id']}" ) ), array( "tag_aap_lookup=?", md5( "cms;categories;{$cat['category_id']}" ) ) );
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_aap_lookup' => md5( "cms;categories_{$cat['category_database_id']};{$cat['category_id']}" ) ), array( "tag_perm_aap_lookup=?", md5( "cms;categories;{$cat['category_id']}" ) ) );
		}

		return TRUE;
	}
	
	/**
     * Custom title for this step
     *
     * @return string
     */
    public function step1CustomTitle()
    {
        return "Fixing database permissions";
    }
}
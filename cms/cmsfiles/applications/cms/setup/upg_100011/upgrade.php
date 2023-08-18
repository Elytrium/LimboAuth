<?php
/**
 * @brief		4.0.0 Beta 7 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		30 Jan 2015
 */

namespace IPS\cms\setup\upg_100011;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 7 Upgrade Code
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert menu titles to translatable lang strings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		\IPS\Db::i()->update( 'cms_templates', "template_content=REPLACE(template_content, '{image=', '{resource=' )" );
		\IPS\Data\Cache::i()->clearAll();
		\IPS\Data\Store::i()->clearAll();

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Converting image plugin to resource plugin in database templates";
	}

	/**
	 * Step 2
	 * Remove old database tables if they exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if( \IPS\Db::i()->checkForTable('ccs_attachments_map') )
		{
			\IPS\Db::i()->dropTable( 'ccs_attachments_map' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_block_wizard') )
		{
			\IPS\Db::i()->dropTable( 'ccs_block_wizard' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_database_moderators') )
		{
			\IPS\Db::i()->dropTable( 'ccs_database_moderators' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_database_modqueue') )
		{
			\IPS\Db::i()->dropTable( 'ccs_database_modqueue' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_database_ratings') )
		{
			\IPS\Db::i()->dropTable( 'ccs_database_ratings' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_menus') )
		{
			\IPS\Db::i()->dropTable( 'ccs_menus' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_page_templates') )
		{
			\IPS\Db::i()->dropTable( 'ccs_page_templates' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_page_wizard') )
		{
			\IPS\Db::i()->dropTable( 'ccs_page_wizard' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_revisions') )
		{
			\IPS\Db::i()->dropTable( 'ccs_revisions' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_slug_memory') )
		{
			\IPS\Db::i()->dropTable( 'ccs_slug_memory' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_template_blocks') )
		{
			\IPS\Db::i()->dropTable( 'ccs_template_blocks' );
		}

		if( \IPS\Db::i()->checkForTable('ccs_template_cache') )
		{
			\IPS\Db::i()->dropTable( 'ccs_template_cache' );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Removing old database tables";
	}

	/**
	 * Step 3
	 * Remove old stuff from tables we retained
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* A previous upgrader bug may have prevented this column from being added - #2296 */
		if( !\IPS\Db::i()->checkForColumn( 'cms_databases', 'database_fixed_field_settings' ) )
		{
			\IPS\Db::i()->addColumn( 'cms_databases', array( 'name' => 'database_fixed_field_settings', 'type' => 'MEDIUMTEXT', 'allow_null' => true, 'default' => null ) );
		}

		\IPS\Db::i()->update( 'cms_databases', array( 'database_fixed_field_settings' => '{"record_image":{"image_dims":[0,0],"thumb_dims":["200","200"]}}' ), array( 'database_fixed_field_settings IS NULL OR database_fixed_field_settings=?', '' ) );
		\IPS\Db::i()->update( 'cms_databases', array( 'database_featured_settings' => '{"featured":false,"perpage":10,"pagination":false,"sort":"record_publish_date","direction":"desc","categories":0}' ), array( 'database_featured_settings IS NULL OR database_featured_settings=?', '' ) );

		/* Database categories */
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_description' ) )
		{
			$columns[] = "category_description";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_rss' ) )
		{
			$columns[] = "category_rss";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_rss_cache' ) )
		{
			$columns[] = "category_rss_cache";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_rss_cached' ) )
		{
			$columns[] = 'category_rss_cached';
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_rss_exclude' ) )
		{
			$columns[] = "category_rss_exclude";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_template' ) )
		{
			$columns[] = "category_template";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_tags_override' ) )
		{
			$columns[] = "category_tags_override";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_tags_enabled' ) )
		{
			$columns[] = "category_tags_enabled";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_tags_noprefixes' ) )
		{
			$columns[] = "category_tags_noprefixes";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_categories', 'category_tags_predefined' ) )
		{
			$columns[] = "category_tags_predefined";
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_database_categories', $columns );
		}

		/* Fields */
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'cms_database_fields', 'field_name' ) )
		{
			$columns[] = "field_name";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_fields', 'field_description' ) )
		{
			$columns[] = "field_description";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_database_fields', 'field_is_numeric' ) )
		{
			$columns[] = "field_is_numeric";
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_database_fields', $columns );
		}

		/* Pages */
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_template_used' ) )
		{
			$columns[] = "page_template_used";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_last_edited' ) )
		{
			$columns[] = "page_last_edited";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_content_only' ) )
		{
			$columns[] = "page_content_only";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_content_type' ) )
		{
			$columns[] = "page_content_type";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_omit_filename' ) )
		{
			$columns[] = "page_omit_filename";
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', $columns );
		}

		/* Blocks */
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'cms_blocks', 'block_name' ) )
		{
			$columns[] = "block_name";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_blocks', 'block_description' ) )
		{
			$columns[] = "block_description";
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_blocks', $columns );
		}

		/* Databases */
		$columns = array();

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_name' ) )
		{
			$columns[] = "database_name";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_database' ) )
		{
			$columns[] = "database_database";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_description' ) )
		{
			$columns[] = "database_description";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_field_count' ) )
		{
			$columns[] = "database_field_count";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_rss_cache' ) )
		{
			$columns[] = "database_rss_cache";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_rss_cached' ) )
		{
			$columns[] = "database_rss_cached";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_lang_sl' ) )
		{
			$columns[] = "database_lang_sl";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_lang_pl' ) )
		{
			$columns[] = "database_lang_pl";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_lang_su' ) )
		{
			$columns[] = "database_lang_su";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_lang_pu' ) )
		{
			$columns[] = "database_lang_pu";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_featured_article' ) )
		{
			$columns[] = "database_featured_article";
		}

		if( \IPS\Db::i()->checkForColumn( 'cms_databases', 'database_is_articles' ) )
		{
			$columns[] = "database_is_articles";
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_databases', $columns );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Cleaning up database tables";
	}
}
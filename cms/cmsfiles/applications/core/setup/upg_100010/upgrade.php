<?php
/**
 * @brief		4.0.0 Beta 6 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Jan 2015
 */

namespace IPS\core\setup\upg_100010;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Beta 6 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert core_theme_images into core_theme_resources
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$columns = array ( 'resource_id' => array ( 'allow_null' => false, 'auto_increment' => true, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => NULL, 'length' => 10, 'name' => 'resource_id', 'type' => 'INT', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_set_id' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '0', 'length' => 10, 'name' => 'resource_set_id', 'type' => 'INT', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_app' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '', 'length' => 32, 'name' => 'resource_app', 'type' => 'VARCHAR', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_location' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '', 'length' => 32, 'name' => 'resource_location', 'type' => 'VARCHAR', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_path' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '', 'length' => 255, 'name' => 'resource_path', 'type' => 'VARCHAR', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_name' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '', 'length' => 255, 'name' => 'resource_name', 'type' => 'VARCHAR', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_added' => array ( 'allow_null' => false, 'auto_increment' => false, 'binary' => false, 'comment' => '', 'decimals' => NULL, 'default' => '0', 'length' => 10, 'name' => 'resource_added', 'type' => 'INT', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_filename' => array ( 'allow_null' => true, 'auto_increment' => false, 'binary' => false, 'comment' => 'File object URI', 'decimals' => NULL, 'default' => NULL, 'length' => 255, 'name' => 'resource_filename', 'type' => 'VARCHAR', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), 'resource_plugin' => array ( 'allow_null' => true, 'auto_increment' => false, 'binary' => false, 'comment' => 'The plugin ID, if created by a plugin', 'decimals' => NULL, 'default' => NULL, 'length' => 20, 'name' => 'resource_plugin', 'type' => 'BIGINT', 'unsigned' => true, 'values' => array ( ), 'zerofill' => false, ), 'resource_data' => array ( 'allow_null' => true, 'auto_increment' => false, 'binary' => false, 'comment' => 'Stores the resource data so the resources can be re-written if the file object is lost', 'decimals' => NULL, 'default' => NULL, 'length' => 0, 'name' => 'resource_data', 'type' => 'MEDIUMBLOB', 'unsigned' => false, 'values' => array ( ), 'zerofill' => false, ), );

		$mappedColumns = array();
		foreach ( $columns as $columnName => $data )
		{
			$mappedColumns[] = '`image_' . mb_substr( $columnName, 9 ) . '` AS `' . $columnName . '`';
		}
		
		if( !\IPS\Db::i()->checkForTable( 'core_theme_resources' ) )
		{
			\IPS\Db::i()->createTable( array ( 'name' => 'core_theme_resources', 'columns' => $columns, 'indexes' => array ( 'PRIMARY' => array ( 'type' => 'primary', 'name' => 'PRIMARY', 'length' => array ( 0 => NULL, ), 'columns' => array ( 0 => 'resource_id', ), ), 'resource_set_id' => array ( 'type' => 'key', 'name' => 'resource_set_id', 'length' => array ( 0 => NULL, ), 'columns' => array ( 0 => 'resource_set_id', ), ), 'resource_app' => array ( 'type' => 'key', 'name' => 'resource_app', 'length' => array ( 0 => NULL, ), 'columns' => array ( 0 => 'resource_app', ), ), ), 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB', ) );
		}
		else
		{
			\IPS\Db::i()->delete( 'core_theme_resources' );
		}
		
		\IPS\Db::i()->insert( 'core_theme_resources', \IPS\Db::i()->select( $mappedColumns, 'core_theme_images' ) );
		
		\IPS\Db::i()->dropTable( 'core_theme_images' );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing images table";
	}

	/**
	 * Step 2
	 * Fix references to {image=...} in templates (including emails)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_theme_templates', "template_content=REPLACE(template_content, '{image=', '{resource=' )" );
		\IPS\Db::i()->update( 'core_theme_css', "css_content=REPLACE(css_content, '{image=', '{resource=' )" );
		\IPS\Db::i()->update( 'core_email_templates', "template_content_html=REPLACE(template_content_html, '{image=', '{resource=' ), template_content_plaintext=REPLACE(template_content_plaintext, '{image=', '{resource=' )" );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Converting image plugin to resource plugin";
	}
	
	/**
	 * Step 3
	 * Upgrade tags... if we haven't already done so in upg_40000.
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if ( isset( $_SESSION['_tagsUpgraded'] ) )
		{
			return TRUE;
		}
		
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;
		
		if ( isset( \IPS\Request::i()->extra ) )
		{
			$limit = (int) \IPS\Request::i()->extra;
		}
		
		/* We need to loop through all tags and fix up tag_aai_lookup as the area has changed. Normally we could just use MySQL MD5, but we need to update core_tags_perms too... */
		foreach( \IPS\Db::i()->select( '*', 'core_tags', NULL, 'tag_id ASC', array( $limit, $perCycle ) ) AS $row )
		{
			$did++;
			switch( $row['tag_meta_area'] )
			{
				case 'topics':
					$row['tag_meta_area']	= 'forums';
				break;
				
				case 'files':
					$row['tag_meta_area']	= 'downloads';
				break;
				
				case 'images':
					$row['tag_meta_area']	= 'gallery';
				break;
				
				case 'entries':
					$row['tag_meta_area']	= 'blogs';
				break;
			}
			
			$oldAaiLookup = $row['tag_aai_lookup'];
			$row['tag_aai_lookup'] = md5( $row['tag_meta_app'] . ';' . $row['tag_meta_area'] . ';' . $row['tag_meta_id'] );
			
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_aai_lookup' => $row['tag_aai_lookup'] ), array( 'tag_perm_aai_lookup=?', $oldAaiLookup ) );
			\IPS\Db::i()->update( 'core_tags', $row, array( 'tag_id=?', $row['tag_id'] ) );
			\IPS\Db::i()->update( 'core_tags_cache', array( 'tag_cache_key' => $row['tag_aai_lookup'] ), array( 'tag_cache_key=?', $oldAaiLookup ) );
		}
		
		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			/* Unset the count, and set a flag in the session so we do not do this again in Beta 6. */
			unset( $_SESSION['_step4Count'] );
			$_SESSION['_tagsUpgraded'] = TRUE;
			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		if ( isset( $_SESSION['_tagsUpgraded'] ) )
		{
			return "Tags already upgraded - skipping this step.";
		}
		
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step4Count'] ) )
		{
			$_SESSION['_step4Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_tags' )->first();
		}

		return "Upgrading tags (Upgraded so far: " . ( ( $limit > $_SESSION['_step4Count'] ) ? $_SESSION['_step4Count'] : $limit ) . ' out of ' . $_SESSION['_step4Count'] . ')';
	}
}
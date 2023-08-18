<?php
/**
 * @brief		4.1.9 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		28 Jan 2016
 */

namespace IPS\cms\setup\upg_101026;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.9 Upgrade Code
 */
class _Upgrade
{
	/**
	 * ...
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$categories = iterator_to_array( \IPS\Db::i()->select( 'category_id', 'cms_database_categories' ) );
		\IPS\Db::i()->delete( 'core_follow', array( "follow_app=? AND follow_area LIKE 'categories%' AND " . \IPS\Db::i()->in( 'follow_rel_id', $categories, TRUE ), 'cms' ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Removing invalid follows";
	}
	
	/**
	 * Fix recordFeed sidebar visibility for ONCE AND FOR ALL*
	 *
	 *
	 * * until next time.
	 *
	 * @return string
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_widgets', array( 'restrict' => '["cms"]' ), array( 'app=? and `key`=?', 'cms', 'recordFeed' ) );
		
		return TRUE;
	}
	
	/**
	 * Trigger background task
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		\IPS\Task::queue( 'cms', 'RebuildCommentLikes', array(), 4 );

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Updating database comment likes";
	}
	
	/**
	 * Fix Number Custom Fields
	 */
	public function step4()
	{
		$toRunQueries = array();
		
		foreach ( \IPS\Db::i()->select( '*', 'cms_database_fields', array( 'field_type=?', 'Number' ) ) AS $field )
		{
			if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $field['field_database_id'] ) )
			{
				try
				{
					$toRunQueries[]	= array(
						'table' => 'cms_custom_database_' . $field['field_database_id'],
						'query' => "ALTER TABLE `" . \IPS\Db::i()->prefix . 'cms_custom_database_' . $field['field_database_id'] . "` DROP INDEX `field_" . $field['field_id'] . "`"
					);
					
					$toRunQueries[]	= array(
						'table' => 'cms_custom_database_' . $field['field_database_id'],
						'query' => "ALTER TABLE `" . \IPS\Db::i()->prefix . 'cms_custom_database_' . $field['field_database_id'] . "` CHANGE COLUMN `field_" . $field['field_id'] . "` field_" . $field['field_id'] . " VARCHAR(255) DEFAULT NULL"
					);
					
					$toRunQueries[]	= array(
						'table' => 'cms_custom_database_' . $field['field_database_id'],
						'query' => "ALTER TABLE `" . \IPS\Db::i()->prefix . 'cms_custom_database_' . $field['field_database_id'] . "` ADD INDEX `field_" . $field['field_id'] . "`(`field_" . $field['field_id'] . "`(191))"
					);
				}
				catch( \Exception $e ) { }
			}
		}
				
		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'cms', 'extra' => array( '_upgradeStep' => 5 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		return "Changing custom fields database structure";
	}
}
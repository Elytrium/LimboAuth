<?php
/**
 * @brief		4.5.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Pages
 * @since		10 Sep 2019
 */

namespace IPS\cms\setup\upg_105013;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.5.0 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Adjust database indexes
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$queries = array();

		foreach( \IPS\Db::i()->select( 'database_id', 'cms_databases' ) as $database )
		{
			$queries[] = array(
				'table'		=> 'cms_custom_database_' . $database,
				'query'		=> "ALTER TABLE `" . \IPS\Db::i()->prefix . "cms_custom_database_{$database}` DROP KEY `category_id`, ADD KEY `category_id` (`category_id`,`record_last_comment`), ADD KEY `future_entries` (`record_future_date`,`record_publish_date`)"
			);
		}

		if( \count( $queries ) )
		{
			$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $queries );
			
			if ( \count( $toRun ) )
			{
				\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'cms', 'extra' => array( '_upgradeStep' => 2 ) ) );

				/* Queries to run manually */
				return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
			}
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
		return "Improving performance for custom Pages databases";
	}
	
	/**
	 * Fix templates 
	 *
	 * @return boolean
	 */
	public function step2()
	{
		/* Update existing template bits */
		foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( 'template_master=0 and template_user_created=1 and template_user_edited=1' ) ) as $key => $template )
		{
			$obj = \IPS\cms\Templates::constructFromData( $template );
	
			if ( $obj->getMasterOfThis() and ! $obj->isDifferentFromMaster() )
			{
				/* It's not different from the master template, so remove the user_edited flag */
				$obj->user_edited = 0;
				$obj->save();
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Upgrade WYSIWYG Blocks
	 *
	 * @return	bool
	 */
	public function step3()
	{
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'cms_blocks', array( "block_type=?", 'custom' ) ), 'IPS\cms\Blocks\Block' ) AS $block )
		{
			if ( $block->getConfig('editor') !== 'editor' )
			{
				continue;
			}
			
			\IPS\Lang::saveCustom( 'cms', "cms_block_content_{$block->id}", $block->content );
			$block->content = NULL;
			$block->save();
		}
		
		return TRUE;
	}
	
	/**
	 * Custom Title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return "Upgrading WYSIWYG blocks";
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Fixing database templates";
	}

	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		\IPS\Task::queue( 'cms', 'FixCommentAttachments', array(), 4 );

		return TRUE;
	}
}
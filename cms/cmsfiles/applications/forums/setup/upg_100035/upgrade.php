<?php
/**
 * @brief		4.0.9 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Forums
 * @since		19 Jun 2015
 */

namespace IPS\forums\setup\upg_100035;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.9 Upgrade Code
 */
class _Upgrade
{
	/**
	 * In older versions, a forum may have been set as its own parent
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Do we have any forums that have parent_id = id? */
		if( \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums', "parent_id=id" )->first() )
		{
			$forum				= new \IPS\forums\Forum;
			
			$values = array(
				'forum_parent_id'	=> 0,
				'name'				=> 'Orphaned Forums',
			);

			$forum->saveForm( $forum->formatFormValues( $values ) );

			foreach( \IPS\Db::i()->select( '*', 'forums_forums', "parent_id=id" ) as $childForum )
			{
				$childForum = \IPS\forums\Forum::constructFromData( $childForum );
				$childForum->parent_id = $forum->_id;
				$childForum->save();
			}

			try
			{
				$forum->setLastComment();
			}
			catch( \Throwable )
			{
				/* This may error if columns are not present, so if it doesn't work, skip it. Later steps take care of it anyway. */
			}

			$forum->setLastReview();
			$forum->save();
		}

		return TRUE;
	}
		
	/**
	 * Fix archive table prefix issue
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		if ( \IPS\Settings::i()->archive_remote_sql_host )
		{
			$db = \IPS\Db::i( 'archive', array(
					'sql_host'		=> \IPS\Settings::i()->archive_remote_sql_host,
					'sql_user'		=> \IPS\Settings::i()->archive_remote_sql_user,
					'sql_pass'		=> \IPS\Settings::i()->archive_remote_sql_pass,
					'sql_database'	=> \IPS\Settings::i()->archive_remote_sql_database,
					'sql_port'		=> \IPS\Settings::i()->archive_sql_port,
					'sql_utf8mb4'	=> isset( \IPS\Settings::i()->sql_utf8mb4 ) ? \IPS\Settings::i()->sql_utf8mb4 : FALSE
			) );
			
			foreach( $db->forceQuery( "SHOW TABLES LIKE '%". $db->escape_string( "forums_archive_posts" ) . "'" ) as $row )
			{
				if ( $value = current( $row ) )
				{
					$prefix = str_replace( 'forums_archive_posts', '', $value );
					break;
				}
			}
			
			if ( $prefix )
			{
				try
				{
					\IPS\Db::i()->select( 'conf_value', 'core_sys_conf_settings', array( 'conf_key=?', 'archive_sql_tbl_prefix' ) )->first();
				}
				catch( \UnderflowException $ex )
				{
					$insert = array(
						'conf_key'      => 'archive_sql_tbl_prefix',
						'conf_value'    => '',
						'conf_default'  => '', 
						'conf_keywords' => '',
						'conf_app'      => 'forums'
					);
								
					/* This key was added in 4.0.9 so it will not exist */
					\IPS\Db::i()->insert( 'core_sys_conf_settings', $insert );
				}
			
				\IPS\Settings::i()->changeValues( array( 'archive_sql_tbl_prefix' => $prefix ) );
			}
		}

		return TRUE;
	}
	
	/**
	 * Update widgets
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		$areas = array( 'core_widget_areas' );
		if ( \IPS\Application::appIsEnabled('cms') )
		{
			$areas[] = 'cms_page_widget_areas';
		}
		
		foreach ( $areas as $table )
		{
			foreach ( \IPS\Db::i()->select( '*', $table ) as $area )
			{
				$widgetsColumn = $table == 'core_widget_areas' ? 'widgets' : 'area_widgets';
				$whereClause = $table == 'core_widget_areas' ? array( 'id=? AND area=?', $area['id'], $area['area'] ) : array( 'area_page_id=? AND area_area=?', $area['area_page_id'], $area['area_area'] );
				
				$widgets = json_decode( $area[ $widgetsColumn ], TRUE );
				$update = FALSE;
				
				foreach ( $widgets as $k => $widget )
				{
					if ( $widget['key'] == 'latestTopics' )
					{
						$widgets[ $k ]['key'] = 'topicFeed';
						$update = TRUE;
					}
					if ( $widget['key'] == 'featuredTopics' )
					{
						$widgets[ $k ]['key'] = 'topicFeed';
						$widgets[ $k ]['configuration']['tfb_topic_status'] = array( 'open', 'closed', 'pinned', 'notpinned', 'featured' );
						$update = TRUE;
					}
					
					if ( $widget['key'] == 'topicFeed' )
					{
						if ( isset( $widgets[ $k ]['configuration'] ) )
						{
							$config = array();
							foreach ( $widgets[ $k ]['configuration'] as $_k => $_v )
							{
								$_k = str_replace( 'tfb_', 'widget_feed', $_k );
								if ( $_k == 'widget_feed_topic_status' )
								{
									$_k = 'widget_feed_status';
								}
								$widgets[ $k ]['configuration'][ $_k ] = $_v;
							}
						}
					}
					
					if ( $update )
					{
						\IPS\Db::i()->update( $table, array( $widgetsColumn => json_encode( $widgets ) ), $whereClause );
					}
				}
			}
		}

		return TRUE;
	}
}
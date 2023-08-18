<?php
/**
 * @brief		4.0.11 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		14 Jul 2015
 */

namespace IPS\cms\setup\upg_100041;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.11 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix Pages blocks that use topicFeed block
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		foreach ( \IPS\Db::i()->select( '*', 'cms_blocks', array( 'block_type=?', 'plugin' ) ) as $block )
		{
			$config = json_decode( $block['block_plugin_config'], TRUE );

			/* Some badly stored blocks may have corrupt config...we'll just switch to an array to prevent errors in the upgrade */
			if( !\is_array( $config ) )
			{
				$config = array();
			}

			$update = FALSE;
			
			if ( $block['block_plugin'] == 'latestTopics' )
			{
				$block['block_plugin'] = 'topicFeed';
				$update = TRUE;
			}
			if ( $block['block_plugin'] == 'featuredTopics' )
			{
				$block['block_plugin'] = 'topicFeed';
				$config['tfb_topic_status'] = array( 'open', 'closed', 'pinned', 'notpinned', 'featured' );
				$update = TRUE;
			}
			
			if ( $block['block_plugin'] == 'recentImages' )
			{
				$block['block_plugin'] = 'imageFeed';
				if ( isset( $config['number_to_show'] ) )
				{
					$config['widget_feed_show'] = $config['number_to_show'];
					unset($config['number_to_show'] );
				}
				
				$update = TRUE;
			}
			
			if ( $block['block_plugin'] == 'topImages' )
			{
				$block['block_plugin'] = 'imageFeed';
				
				$config['widget_feed_min_rating'] = 1;
				$config['widget_feed_sort_on'] = 'rating';
				$config['widget_feed_sort_dir'] = 'desc';
				
				if ( isset( $config['number_to_show'] ) )
				{
					$config['widget_feed_show'] = $config['number_to_show'];
					unset( $config['number_to_show'] );
				}
				
				$update = TRUE;
			}
			
			if ( $block['block_plugin'] == 'recentFiles' )
			{
				$block['block_plugin'] = 'fileFeed';
				if ( isset( $config['number_to_show'] ) )
				{
					$config['widget_feed_show'] = $config['number_to_show'];
					unset( $config['number_to_show'] );
				}
				
				$update = TRUE;
			}
			
			if ( $block['block_plugin'] == 'LatestArticles' )
			{
				$block['block_plugin'] = 'RecordFeed';
				if ( isset( $config['database'] ) )
				{
					$config['cms_rf_database'] = $config['database'];
					unset( $config['database'] );
				}
				$update = TRUE;
			}
							
			if ( $block['block_plugin'] == 'topicFeed' )
			{
				$update = TRUE;
				
				foreach ( $config as $_k => $_v )
				{
					$_k = str_replace( 'tfb_', 'widget_feed', $_k );
					if ( $_k == 'widget_feed_topic_status' )
					{
						$_k = 'widget_feed_status';
					}
					$config[ $_k ] = $_v;
				}
			}
			
			if ( $update )
			{
				\IPS\Db::i()->update( 'cms_blocks', array( 'block_plugin' => $block['block_plugin'], 'block_plugin_config' => json_encode( $config ) ), array( 'block_id=?', $block['block_id'] ) );
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
		return "Updating custom feed blocks";
	}
}
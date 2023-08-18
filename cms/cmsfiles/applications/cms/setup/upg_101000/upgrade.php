<?php
/**
 * @brief		4.0.13 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		30 Jul 2015
 */

namespace IPS\cms\setup\upg_101000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Fix cms_blocks
	 *
	 * @return	 void
	 */
	public function finish()
	{
		foreach ( \IPS\Db::i()->select( '*', 'cms_blocks', array( 'block_type=?', 'plugin' ) ) as $block )
		{
			$config = json_decode( $block['block_plugin_config'], TRUE );
			$config = ( \is_array( $config ) ) ? $config : array();
			$update = FALSE;
			
			if ( $block['block_plugin'] == 'topicFeed' )
			{
				$update = TRUE;
				$newConfig = array();
				foreach ( $config as $_k => $_v )
				{
					if ( $_k === 'widget_feed_status' )
					{
						if ( \in_array( 'open', $_v ) and \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'any';
						}
						else if ( \in_array( 'open', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'open';
						}
						else if ( \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'closed';
						}
						
						if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'any';
						}
						else if ( \in_array( 'pinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'open';
						}
						else if ( \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'closed';
						}
						
						if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'any';
						}
						else if ( \in_array( 'featured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'featured';
						}
						else if ( \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'notfeatured';
						}
					}
					else
					{
						$newConfig[ $_k ] = $_v;
					}
				}
			}
			
			if ( $block['block_plugin'] == 'postFeed' )
			{
				$update = TRUE;
				$newConfig = array();
				foreach ( $config as $_k => $_v )
				{
					if ( \in_array( $_k, array( 'tfb_show', 'tfb_sort_dir', 'tfb_use_perms', 'tfb_topic_status' ) ) )
					{
						$_k = str_replace( 'tfb_', 'widget_feed', $_k );
					}
					else
					{
						$_k = str_replace( 'tfb_', 'widget_feed_item', $_k );
					}
					
					if ( $_k == 'widget_feed_topic_status' )
					{
						if ( \in_array( 'open', $_v ) and \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_item_status_locked'] = 'any';
						}
						else if ( \in_array( 'open', $_v ) )
						{
							$newConfig['widget_feed_item_status_locked'] = 'open';
						}
						else if ( \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_item_status_locked'] = 'closed';
						}
						
						if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'any';
						}
						else if ( \in_array( 'pinned', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'open';
						}
						else if ( \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'closed';
						}
						
						if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'any';
						}
						else if ( \in_array( 'featured', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'featured';
						}
						else if ( \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_item_status_pinned'] = 'notfeatured';
						}
					}
					else
					{
						$newConfig[ $_k ] = $_v;
					}
				}
			}
			
			if ( $block['block_plugin'] == 'RecordFeed' )
			{
				$update = TRUE;
				$newConfig = array();
				foreach ( $config as $_k => $_v )
				{
					if ( $_k !== 'cms_rf_database' )
					{
						$_k = str_replace( 'cms_rf', 'widget_feed', $_k );
					}
					
					if ( $_k == 'widget_feed_record_status' )
					{
						$_k = 'widget_feed_status';
					}
					
					if ( $_k == 'cms_rf_category' )
					{
						$_k = 'widget_feed_container';
					}
					
					if ( $_k === 'widget_feed_status' AND $_v !== NULL )
					{
						if ( \in_array( 'open', $_v ) and \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'any';
						}
						else if ( \in_array( 'open', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'open';
						}
						else if ( \in_array( 'closed', $_v ) )
						{
							$newConfig['widget_feed_status_locked'] = 'closed';
						}
						
						if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'any';
						}
						else if ( \in_array( 'pinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'open';
						}
						else if ( \in_array( 'notpinned', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'closed';
						}
						
						if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'any';
						}
						else if ( \in_array( 'featured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'featured';
						}
						else if ( \in_array( 'notfeatured', $_v ) )
						{
							$newConfig['widget_feed_status_pinned'] = 'notfeatured';
						}
					}
					else
					{
						$newConfig[ $_k ] = $_v;
					}
				}
			}
			
			if ( $update )
			{
				\IPS\Db::i()->update( 'cms_blocks', array( 'block_plugin_config' => json_encode( $newConfig ) ), array( 'block_id=?', $block['block_id'] ) );
			}
		}
		
		/* Update template params */
		\IPS\Db::i()->update( 'cms_blocks', array( 'block_template_params' => '$records, $title, $orientation=\'vertical\'' ), array( 'block_plugin=?', 'RecordFeed' ) );

		return TRUE;
	}
}
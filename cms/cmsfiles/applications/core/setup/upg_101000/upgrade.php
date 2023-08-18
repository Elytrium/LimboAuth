<?php
/**
 * @brief		4.1.0 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Jul 2015
 */

namespace IPS\core\setup\upg_101000;

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
	 * Step 1: Sort out the global template
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Drop globalTemplates */
		if ( isset( $_SESSION['upgrade_options']['core']['101000']['globalTemplate_revert'] ) and $_SESSION['upgrade_options']['core']['101000']['globalTemplate_revert'] == 'yes' )
		{
			$templateIds = array();
	
			foreach( \IPS\Db::i()->select( '*', 'core_theme_templates', array( array( 'template_set_id > 0 and template_name=? and template_group=? and template_app=? and template_location=?', 'globalTemplate', 'global', 'core', 'front' ) ) ) as $template )
			{
				if ( mb_stristr( $template['template_content'], '{template="utilitiesMenu"' ) )
				{
					$templateIds[] = $template['template_id'];
				}
			}
			
			if ( \count( $templateIds ) )
			{
				\IPS\Db::i()->delete( 'core_theme_templates', array( \IPS\Db::i()->in( 'template_id', $templateIds ) ) );
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
		return "Upgrading menu";
	}
	
	/**
	 * Step 2: Add email to upgrade notification emails
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => \IPS\Settings::i()->email_in ), "conf_key = 'upgrade_email'" );
		\IPS\Settings::i()->clearCache();

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Setting upgrade notifications email address";
	}

	/**
	 * Step 3: Upgrade emoticons
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$json = json_decode( file_get_contents( \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY ."/install/emoticons/data.json" ), TRUE );

		foreach( \IPS\Db::i()->select( '*', 'core_emoticons', \IPS\Db::i()->in( 'typed', array_keys( $json ) ) ) as $emoticon )
		{
			if( !isset( $json[ $emoticon['typed'] ]['image_2x'] ) or !file_exists(\IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $json[ $emoticon['typed'] ]['image_2x'] ) )
			{
				continue;
			}
			
			try
			{
				$imageDimensions = \IPS\File::get( 'core_Emoticons', $emoticon['image'] )->getImageDimensions();
				$fileObj2x = \IPS\File::create( 'core_Emoticons', $json[ $emoticon['typed'] ]['image_2x'], NULL, 'emoticons', TRUE, \IPS\ROOT_PATH . '/' . \IPS\CP_DIRECTORY . "/install/emoticons/" . $json[ $emoticon['typed'] ]['image_2x'], FALSE );
	
				$update = array(
							'image_2x' => (string) $fileObj2x,
							'width' => $imageDimensions[0] ,
							'height' => $imageDimensions[1]
						);
				\IPS\Db::i()->update( 'core_emoticons', $update, array( 'typed=?', $emoticon['typed'] ) );
			}
			catch( \Exception $ex ) { }
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
		return "Upgrading emoticons";
	}

	/**
	 * Step 4: Populate default streams
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		/* Grab the inserts from the schematic as they will always be the most up to date */
		$json = json_decode( file_get_contents( \IPS\ROOT_PATH . '/applications/core/data/schema.json' ), TRUE );
		
		foreach( $json['core_streams']['inserts'] as $data )
		{
			try
			{
				\IPS\Db::i()->insert( 'core_streams', $data );
			}
			catch( \IPS\Db\Exception $ex ) { }
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
		return "Populating default streams";
	}

	/**
	 * Step 5
	 * Fix followers from a 3.x upgrade - We have to do this here because the original code was wrong, and we need to fix people affected between then and now
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		/* Init */
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );
		
		/* Because follow_id gets changed in the process, we have to copy data to a temporary table first */
		if ( !$limit )
		{
			$tableDefinition = \IPS\Db::i()->getTableDefinition( 'core_follow' );
			\IPS\Db::i()->renameTable( 'core_follow', 'core_follow_backup' );
			\IPS\Db::i()->createTable( $tableDefinition );
		}

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		/* Now do it - sort by follow_added ASC so that we do them in reverse order so if a new record was added which is now correct, that is what gets maintained */
		foreach( \IPS\Db::i()->select( '*', 'core_follow_backup', NULL, 'follow_added ASC', array( $limit, $perCycle ) ) as $follow )
		{
			/* Try to prevent timeouts to the extent possible */
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			/* Increase the counter */
			$did++;
			
			/* Figure out what to update */
			$insert	= $follow;
			switch( $follow['follow_area'] )
			{
				case 'topics':
					$insert['follow_area']	= 'topic';
					$insert['follow_id']	= md5( 'forums;topic;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'forums':
					$insert['follow_area']	= 'forum';
					$insert['follow_id']	= md5( 'forums;forum;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'files':
					$insert['follow_area']	= 'file';
					$insert['follow_id']	= md5( 'downloads;file;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'images':
					$insert['follow_area']	= 'image';
					$insert['follow_id']	= md5( 'gallery;image;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'entries':
					$insert['follow_area']	= 'entry';
					$insert['follow_id']	= md5( 'blog;entry;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'calendars':
					$insert['follow_area']	= 'calendar';
					$insert['follow_id']	= md5( 'calendar;calendar;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'categories':
					$insert['follow_area']	= 'category';
					$insert['follow_id']	= md5( $follow['follow_app'] . ';category;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'albums':
					$insert['follow_area']	= 'album';
					$insert['follow_id']	= md5( 'gallery;album;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				case 'events':
					$insert['follow_area']	= 'event';
					$insert['follow_id']	= md5( 'calendar;event;' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
				break;

				default:
					if ( mb_strpos( $follow['follow_area'], 'ccs_custom_database' ) === 0 )
					{
						$data	= explode( '_', str_replace( 'ccs_custom_database_', '', $follow['follow_area'] ) );
						$insert['follow_app']	= 'cms';
						$insert['follow_area']	= $data[1] . $data[0];
						$insert['follow_id']	= md5( 'cms;' . $insert['follow_area'] . ';' . $follow['follow_rel_id'] . ';' . $follow['follow_member_id'] );
					}
				break;
			}
			
			/* And insert */
			\IPS\Db::i()->replace( 'core_follow', $insert );
		}
		
		/* More to do? */
		if( $did )
		{
			return ( $limit + $did );
		}
		/* Nope - carry on */
		else
		{
			\IPS\Db::i()->dropTable( 'core_follow_backup' );
			unset( $_SESSION['_step5Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step5Count'] ) )
		{
			$_SESSION['_step5Count']	= \IPS\Db::i()->select( 'COUNT(*)', 'core_follow' )->first();
		}

		return "Fixing existing follows (Fixed so far: " . ( ( $limit > $_SESSION['_step5Count'] ) ? $_SESSION['_step5Count'] : $limit ) . ' out of ' . $_SESSION['_step5Count'] . ')';
	}

	/**
	 * Step 6: Adjust email SMTP host/protocol
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		/* We only need to do this if there is an SMTP host and it is something like tls://domain */
		if( \IPS\Settings::i()->smtp_host AND mb_strpos( \IPS\Settings::i()->smtp_host, '://' ) !== FALSE )
		{
			/* First we need to insert the setting since it won't be present just yet */
			\IPS\Db::i()->replace( 'core_sys_conf_settings', array( 'conf_key' => 'smtp_protocol', 'conf_default' => 'plain', 'conf_app' => 'core' ) );

			/* Now figure out what we need to set it to */
			$protocol	= mb_substr( \IPS\Settings::i()->smtp_host, 0, mb_strpos( \IPS\Settings::i()->smtp_host, '://' ) );
			$host		= mb_substr( \IPS\Settings::i()->smtp_host, mb_strpos( \IPS\Settings::i()->smtp_host, '://' ) + 3 );

			/* And update */
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $host ), "conf_key = 'smtp_host'" );
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $protocol ), "conf_key = 'smtp_protocol'" );
			\IPS\Settings::i()->clearCache();
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		return "Adjusting email configuration";
	}

	/**
	 * Step 7: Remove some legacy columns if they still exist
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		if ( \IPS\Db::i()->checkForColumn( 'core_bulk_mail', 'mail_pergo' ) )
		{
			$columns[]	= 'mail_pergo';
		}

		if ( \IPS\Db::i()->checkForColumn( 'core_bulk_mail', 'mail_groups' ) )
		{
			$columns[]	= 'mail_groups';
		}

		if( \count( $columns ) )
		{
			\IPS\Db::i()->dropColumn( 'core_bulk_mail', $columns );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		return "Cleaning up bulk mail database table";
	}

	/**
	 * Update widgets and menu
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* Create the new default configuration */
		\IPS\core\FrontNavigation::buildDefaultFrontNavigation();
		$maxPosition = \IPS\Db::i()->select( 'MAX(position)', 'core_menu', 'parent IS NULL' )->first();
		
		/* Add the home link if necessary */
		if ( \IPS\Settings::i()->show_home_link )
		{
			$id = \IPS\Db::i()->insert( 'core_menu', array(
				'app'			=> 'core',
				'extension'		=> 'CustomItem',
				'config'		=> json_encode( array( 'menu_custom_item_url' => \IPS\Settings::i()->home_url ) ),
				'position'		=> ++$maxPosition,
				'parent'		=> NULL,
				'permissions'	=> '*',
			) );
			\IPS\Lang::copyCustom( 'core', 'home_name_value', "menu_item_{$id}" );
			\IPS\Lang::deleteCustom( 'core', 'home_name_value' );
		}

		/* We have to fix nexus language bits */
		if ( \IPS\Application::appIsEnabled('nexus') )
		{
			/* There is a support tab we will need to fix */
			$menu = \IPS\Db::i()->select( 'id', 'core_menu', array( "extension=? AND config LIKE '%app=nexus%' AND parent IS NULL", 'CustomItem' ) )->first();

			\IPS\Lang::saveCustom( 'nexus', 'menu_item_' . $menu, 'Support' );

			/* There is a my details menu we will need to fix */
			$menu = \IPS\Db::i()->select( 'parent', 'core_menu', array( "app=? AND is_menu_child=?", 'nexus', 1 ) )->first();

			\IPS\Lang::saveCustom( 'nexus', 'menu_item_' . $menu, 'My Details' );
		}
		
		/* And pages? */
		if ( \IPS\Application::appIsEnabled('cms') )
		{
			$rootId = \IPS\Db::i()->insert( 'core_menu', array(
				'app'			=> 'core',
				'extension'		=> 'CustomItem',
				'config'		=> json_encode( array( 'menu_custom_item_url' => 'app=cms&module=pages&controller=page', 'internal' => '' ) ),
				'position'		=> ++$maxPosition,
				'parent'		=> NULL,
				'permissions'	=> '*',
			) );
			
			/* 3.x to 4.x won't have language strings installed at this point */
			try
			{
				$pagesLang = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get("__app_cms");
			}
			catch( \UnderflowException $ex )
			{
				$pagesLang = "Pages";
			}
			
			\IPS\Lang::saveCustom( 'core', "menu_item_{$rootId}", $pagesLang );
			
			$position = 1;
			foreach ( \IPS\Db::i()->select( '*', 'cms_page_menu' ) as $menuItem )
			{				
				if ( $menuItem['menu_type'] == 'page' )
				{
					/* We need to make sure the page actually exists, otherwise the site will be down with a 500 ISE after upgrade */
					try
					{
						$_page = \IPS\Db::i()->select( 'page_id', 'cms_pages', array( 'page_id=?', $menuItem['menu_content'] ) )->first();
					}
					catch( \UnderflowException $e )
					{
						/* If the page doesn't exist, just don't add it to the core_menu table and continue */
						continue;
					}

					$app = 'cms';
					$extension = 'Pages';
					$config = array( 'menu_content_page' => $menuItem['menu_content'], 'menu_title_page_type' => ( $menuItem['menu_title'] ? 1 : 0 ) );
				}
				elseif ( $menuItem['menu_type'] == 'folder' )
				{
					$app = 'core';
					$extension = 'Menu';
					$config = array();
				}
				elseif ( $menuItem['menu_type'] == 'url' )
				{
					$app = 'core';
					$extension = 'CustomItem';
					$config = array( 'menu_custom_item_url' => $menuItem['menu_content'] );
				}
								
				$_id = \IPS\Db::i()->insert( 'core_menu', array(
					'id'			=> $menuItem['menu_id'] + $rootId,
					'app'			=> $app,
					'extension'		=> $extension,
					'config'		=> json_encode( $config ),
					'position'		=> $position++,
					'parent'		=> $menuItem['menu_parent_id'] ? ( $menuItem['menu_parent_id'] + $rootId ) : $rootId,
					'permissions'	=> $menuItem['menu_permission'] == 'page' ? '*' : $menuItem['menu_permission'],
				) );
				
				if ( $menuItem['menu_type'] == 'folder' or $menuItem['menu_type'] == 'url' )
				{
					\IPS\Lang::copyCustom( 'cms', "cms_menu_title_{$menuItem['menu_id']}", "menu_item_{$_id}", 'core' );
					\IPS\Lang::deleteCustom( 'cms', "cms_menu_title_{$menuItem['menu_id']}" );
				}
				else
				{
					\IPS\Lang::copyCustom( 'cms', "cms_menu_title_{$menuItem['menu_id']}", "cms_menu_title_{$_id}" );
					\IPS\Lang::deleteCustom( 'cms', "cms_menu_title_{$menuItem['menu_id']}" );
				}

			}

			\IPS\Db::i()->dropTable('cms_page_menu');
		}

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
					if ( $widget['key'] == 'topicFeed' )
					{
						if ( isset( $widgets[ $k ]['configuration'] ) )
						{
							$update = TRUE;
							$config = array();
							foreach ( $widgets[ $k ]['configuration'] as $_k => $_v )
							{
								if ( $_k === 'widget_feed_status' )
								{
									if ( \in_array( 'open', $_v ) and \in_array( 'closed', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'any';
									}
									else if ( \in_array( 'open', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'open';
									}
									else if ( \in_array( 'closed', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'closed';
									}
									
									if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'any';
									}
									else if ( \in_array( 'pinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'open';
									}
									else if ( \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'closed';
									}
									
									if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'any';
									}
									else if ( \in_array( 'featured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'featured';
									}
									else if ( \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'notfeatured';
									}
								}
								else
								{
									$widgets[ $k ]['configuration'][ $_k ] = $_v;
								}
							}
						}
					}
					
					if ( $widget['key'] == 'postFeed' )
					{
						if ( isset( $widgets[ $k ]['configuration'] ) )
						{
							$update = TRUE;
							$config = array();
							foreach ( $widgets[ $k ]['configuration'] as $_k => $_v )
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
										$widgets[ $k ]['configuration']['widget_feed_item_status_locked'] = 'any';
									}
									else if ( \in_array( 'open', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_locked'] = 'open';
									}
									else if ( \in_array( 'closed', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_locked'] = 'closed';
									}
									
									if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'any';
									}
									else if ( \in_array( 'pinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'open';
									}
									else if ( \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'closed';
									}
									
									if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'any';
									}
									else if ( \in_array( 'featured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'featured';
									}
									else if ( \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_item_status_pinned'] = 'notfeatured';
									}
								}
								else
								{
									$widgets[ $k ]['configuration'][ $_k ] = $_v;
								}
								
								$widgets[ $k ]['configuration'][ $_k ] = $_v;
							}
						}
					}
					
					if ( $widget['key'] == 'RecordFeed' )
					{
						if ( isset( $widgets[ $k ]['configuration'] ) )
						{
							$config = array();
							foreach ( $widgets[ $k ]['configuration'] as $_k => $_v )
							{
								$_k = str_replace( 'cms_rf', 'widget_feed', $_k );
								if ( $_k == 'widget_feed_record_status' )
								{
									$_k = 'widget_feed_status';
								}
								
								if ( $_k === 'widget_feed_status' )
								{
									if ( \in_array( 'open', $_v ) and \in_array( 'closed', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'any';
									}
									else if ( \in_array( 'open', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'open';
									}
									else if ( \in_array( 'closed', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_locked'] = 'closed';
									}
									
									if ( \in_array( 'pinned', $_v ) and \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'any';
									}
									else if ( \in_array( 'pinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'open';
									}
									else if ( \in_array( 'notpinned', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'closed';
									}
									
									if ( \in_array( 'featured', $_v ) and \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'any';
									}
									else if ( \in_array( 'featured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'featured';
									}
									else if ( \in_array( 'notfeatured', $_v ) )
									{
										$widgets[ $k ]['configuration']['widget_feed_status_pinned'] = 'notfeatured';
									}
								}
								else
								{
									$widgets[ $k ]['configuration'][ $_k ] = $_v;
								}
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
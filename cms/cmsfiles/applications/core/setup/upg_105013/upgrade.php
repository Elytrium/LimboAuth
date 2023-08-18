<?php
/**
 * @brief		4.5.0 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Jul 2019
 */

namespace IPS\core\setup\upg_105013;

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
	 * Update group anonymous options
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		if ( \IPS\Settings::i()->disable_anonymous )
		{
			\IPS\Db::i()->update( 'core_groups', array( "g_hide_online_list" => 2 ), array( "g_hide_online_list!=?", 1 ) );
		}
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step1CustomTitle()
	{
		return "Fixing anonymous groups online list configuration";
	}

	/**
	 * Convert notification defaults
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$existingDefaults = iterator_to_array( \IPS\Db::i()->select( '*', 'core_notification_defaults' )->setKeyField('notification_key') );
		$newDefaults = array();
		
		$extensions = \IPS\Application::allExtensions( 'core', 'Notifications' );
		foreach ( $extensions as $group => $extension )
		{
			try
			{
				if ( method_exists( $extension, 'configurationOptions' ) )
				{
					foreach ( $extension->configurationOptions( NULL ) as $optionKey => $option )
					{
						if ( $option['type'] === 'standard' )
						{
							foreach ( $option['notificationTypes'] as $type ) { break; } // This is to just get the first type key

							$value = array( 'notification_key' => $optionKey );

							if ( isset( $_SESSION['upgrade_options']['core']['105000']["notifications_default_{$optionKey}"] ) )
							{
								$value['default'] = implode( ',', $_SESSION['upgrade_options']['core']['105000']["notifications_default_{$optionKey}"] );
							}
							elseif ( isset( $existingDefaults[ $type ] ) )
							{
								$value['default'] = $existingDefaults[ $type ]['default'];
							}
							else
							{
								$value['default'] = implode( ',', $option['default'] );
							}

							if ( isset( $_SESSION['upgrade_options']['core']['105000']["notifications_disabled_{$optionKey}"] ) )
							{
								$value['disabled'] = implode( ',', $_SESSION['upgrade_options']['core']['105000']["notifications_disabled_{$optionKey}"] );
							}
							elseif ( isset( $existingDefaults[ $type ] ) )
							{
								$value['disabled'] = $existingDefaults[ $type ]['disabled'];
							}
							else
							{
								$value['disabled'] = '';
							}

							if ( isset( $_SESSION['upgrade_options']['core']['105000']["notifications_editable_{$optionKey}"] ) )
							{
								$value['editable'] = intval( $_SESSION['upgrade_options']['core']['105000']["notifications_editable_{$optionKey}"] );
							}
							elseif ( isset( $existingDefaults[ $type ] ) )
							{
								$value['editable'] = intval( $existingDefaults[ $type ]['editable'] );
							}
							else
							{
								$value['editable'] = 1;
							}

							$newDefaults[ $optionKey ] = $value;

							foreach ( $option['notificationTypes'] as $type )
							{
								$value['notification_key'] = $type;
								$newDefaults[ $type ] = $value;
							}
						}
					}
				}

			}
			catch( \Exception $e ){}
		}
				
		\IPS\Db::i()->delete( 'core_notification_defaults' );
		\IPS\Db::i()->insert( 'core_notification_defaults', $newDefaults );
		
		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step2CustomTitle()
	{
		return "Fixing default notifications";
	}

	/**
	 * Convert referrals
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		try
		{
			if( \IPS\Db::i()->checkForTable('nexus_referrals' ) )
			{
				\IPS\Db::i()->insert( 'core_referrals', \IPS\Db::i()->select( 'member_id, referred_by, amount', 'nexus_referrals' ), FALSE, TRUE );
			}

			if( \IPS\Db::i()->checkForTable('nexus_referral_banners' ) )
			{
				\IPS\Db::i()->insert( 'core_referral_banners', \IPS\Db::i()->select( 'rb_id, rb_url, rb_upload, rb_order', 'nexus_referral_banners' ), FALSE, TRUE );
			}
		}
		catch ( \Exception $e ) {}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return "Converting referrals";
	}

	/**
	 * Cleaning up pruning options
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		if( \IPS\CIC )
		{
			/* Reset notification pruning back to default */
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => NULL ), array( \IPS\Db::i()->in( 'conf_key', array( 'prune_member_history', 'prune_notifications' ) ) ) );

			/* If we are not using the default member history prune preference, initiate the BG task to reset it */
			if( !\IPS\Settings::i()->prune_member_history OR \IPS\Settings::i()->prune_member_history != 365 )
			{
				\IPS\Task::queue( 'core', 'PruneLargeTable', array(
					'table'			=> 'core_member_history',
					'where'			=> array( 'log_date < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P365D' ) )->getTimestamp() ),
					'setting'		=> 'prune_member_history',
				), 4 );
			}
		}
		else
		{
			/* If this is less than the new minimum, update the setting */
			if( \IPS\Settings::i()->prune_notifications > 0 AND \IPS\Settings::i()->prune_notifications < 7 )
			{
				\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => NULL ), array( 'conf_key=?', 'prune_notifications' ) );
			}
		}

		/* Do the initial prune on the potentially large tables */
		if ( \IPS\CIC OR ( isset( $_SESSION['upgrade_options']['core']['105000']['prune'] ) AND $_SESSION['upgrade_options']['core']['105000']['prune'] == 'enable' ) )
		{
			\IPS\Task::queue( 'core', 'PruneLargeTable', array(
				'table'			=> 'core_members_known_ip_addresses',
				'where'			=> array( 'last_seen < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P365D' ) )->getTimestamp() ),
				'setting'		=> 'prune_known_ips',
			), 4 );

			\IPS\Task::queue( 'core', 'PruneLargeTable', array(
				'table'			=> 'core_members_known_devices',
				'where'			=> array( 'last_seen < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P365D' ) )->getTimestamp() ),
				'setting'		=> 'prune_known_devices',
			), 4 );

			\IPS\Task::queue( 'core', 'PruneLargeTable', array(
				'table'			=> 'core_item_markers',
				'where'			=> array( 'item_member_id IN(?)', \IPS\Db::i()->select( 'member_id', 'core_members', array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P60D' ) )->getTimestamp() ) ) ),
				'setting'		=> 'prune_item_markers',
				'deleteJoin'	=> array(
					'column'		=> 'member_id',
					'table'			=> 'core_members',
					'where'			=> array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P60D' ) )->getTimestamp() ),
					'outerColumn'	=> 'item_member_id'
				)
			), 4 );

			\IPS\Task::queue( 'core', 'PruneLargeTable', array(
				'table'			=> 'core_follow',
				'where'			=> array( 'follow_app!=? AND follow_area!=? AND follow_member_id IN(?)', 'core', 'member', \IPS\Db::i()->select( 'member_id', 'core_members', array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P365D' ) )->getTimestamp() ) ) ),
				'setting'		=> 'prune_follows',
				'deleteJoin'	=> array(
					'column'		=> 'member_id',
					'table'			=> 'core_members',
					'where'			=> array( 'last_activity < ?', \IPS\DateTime::create()->sub( new \DateInterval( 'P365D' ) )->getTimestamp() ),
					'outerColumn'	=> 'follow_member_id'
				)
			), 4 );
		}
		elseif( !\IPS\CIC AND isset( $_SESSION['upgrade_options']['core']['105000']['prune'] ) AND $_SESSION['upgrade_options']['core']['105000']['prune'] == 'disable' )
		{
			/* We need to insert the settings with values of 0 so they will remain disabled when we import the settings later */
			\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
				'conf_key'		=> 'prune_item_markers',
				'conf_value'	=> '0',
				'conf_default'	=> '60',
				'conf_app'		=> 'core'
			)	);

			\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
				'conf_key'		=> 'prune_known_ips',
				'conf_value'	=> '0',
				'conf_default'	=> '365',
				'conf_app'		=> 'core'
			)	);

			\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
				'conf_key'		=> 'prune_known_devices',
				'conf_value'	=> '0',
				'conf_default'	=> '365',
				'conf_app'		=> 'core'
			)	);

			\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
				'conf_key'		=> 'prune_follows',
				'conf_value'	=> '0',
				'conf_default'	=> '365',
				'conf_app'		=> 'core'
			)	);
		}

		/* If this is less than the new minimum, update the setting */
		if( \IPS\Settings::i()->prune_log_system > 0 AND \IPS\Settings::i()->prune_log_system < 7 )
		{
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => NULL ), array( 'conf_key=?', 'prune_log_system' ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step4CustomTitle()
	{
		return "Adjusting pruning options";
	}

	/**
	 * Remove orphaned ratings
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		$validClasses = array();

		foreach( \IPS\Content::routedClasses( FALSE, TRUE, TRUE ) as $class )
		{
			if( \in_array( 'IPS\Content\Ratings', class_implements( $class ) ) )
			{
				$validClasses[] = $class;
			}
		}

		if( !\count( $validClasses ) )
		{
			return TRUE;
		}

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( array( array(
			'table' => 'core_ratings',
			'query' => "DELETE FROM `" . \IPS\Db::i()->prefix . "core_ratings` WHERE " . \IPS\Db::i()->in( 'class', $validClasses, TRUE )
		) ) );

		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 1 => 'core', 'extra' => array( '_upgradeStep' => 6 ) ) );

			/* Queries to run manually */
			return array( 'html' => \IPS\Theme::i()->getTemplate( 'forms' )->queries( $toRun, \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr_continue' => 1, 'mr' => \IPS\Request::i()->mr ) ) ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step5CustomTitle()
	{
		return "Removing orphaned ratings";
	}

	/**
	 * Rebuild the existing content messages and make them public
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		$perCycle	= 250;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_content_meta', array( 'meta_type=?', 'core_ContentMessages' ), 'meta_id', array( $limit, $perCycle ) ) as $i )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $limit + $did );
			}

			$did++;

			$meta = json_decode( $i['meta_data'], TRUE);
			$meta['is_public'] = TRUE;
			\IPS\Db::i()->update('core_content_meta', array('meta_data' => json_encode($meta)), array('meta_id=?', $i['meta_id'] ));
		}

		if ( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			unset( $_SESSION['_step6Count'] );
			return TRUE;
		}
	}


	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step6CustomTitle()
	{
		return "Rebuilding content messages";
	}

	/**
	 * Finish
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		/* Delete old language strings (yes, these are in core for some reason ) */
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_app=? and ' . \IPS\Db::i()->in( 'word_key', array(
			'rss_import_forum_id',
			'rss_import_mid',
			'rss_import_mid_desc',
			'acronym_expansion',			
			'acronym_add',				
			'acronym_a_short',	
			'acronym_a_long'	,					
			'acronym_a_casesensitive'	
		 ) ), 'core' ) );
		 
		/* Revert back to MySQL search if their Elasticsearch version is outdated */
		if ( isset( $_SESSION['upgrade_options']['core']['105000']['es_version'] ) AND $_SESSION['upgrade_options']['core']['105000']['es_version'] )
		{
			\IPS\Settings::i()->changeValues( array( 'search_method' => 'mysql' ) );

			\IPS\Content\Search\Index::i()->rebuild();
		}

		/* Update user link preference setting for existing installs */
		\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
			'conf_key'		=> 'link_default',
			'conf_value'	=> 'first',
			'conf_default'	=> 'unread',
			'conf_app'		=> 'core'
		)	);

		/* Enable/disable bbcode appropriately */
		if ( isset( $_SESSION['upgrade_options']['core']['105000']['enable_bbcode'] ) AND $_SESSION['upgrade_options']['core']['105000']['enable_bbcode'] == 'enable' )
		{
			/* The setting won't have been inserted yet */
			\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
				'conf_key'		=> 'enable_bbcode',
				'conf_value'	=> 1,
				'conf_default'	=> 0,
				'conf_app'		=> 'core'
			)	);
		}

		/* Copy Commerce setting to core. It is removed in Commerce upgrader */
		\IPS\Db::i()->replace( 'core_sys_conf_settings', array(
			'conf_key'		=> 'ref_on',
			'conf_value'	=> \IPS\Settings::i()->cm_ref_on ?: 0,
			'conf_default'	=> 0,
			'conf_app'		=> 'core'
		)	);
		
		/* Revert admin_reg email templates */
		\IPS\Db::i()->delete( 'core_email_templates', array( "template_app=? AND template_name=? AND template_edited=?", 'core', 'admin_reg', 1 ) );

		/* Initiate image proxy rebuilds to remove image proxy */
		unset( \IPS\Data\Store::i()->currentImageProxyRebuild );

		if( \IPS\Db::i()->checkForTable('core_image_proxy') )
		{
			foreach ( \IPS\Content::routedClasses( FALSE, TRUE ) as $class )
			{
				if( isset( $class::$databaseColumnMap['content'] ) )
				{
					try
					{
						\IPS\Task::queue( 'core', 'RebuildImageProxy', array( 'class' => $class ), 4 );
					}
					catch( \OutOfRangeException $ex ) { }
				}
			}

			foreach( \IPS\Application::allExtensions( 'core', 'EditorLocations', FALSE, NULL, NULL, TRUE, TRUE ) as $_key => $extension )
			{
				if( method_exists( $extension, 'rebuildImageProxy' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildImageProxyNonContent', array( 'extension' => $_key ), 4 );
				}
			}

			/* Also initiate the task to delete the files. If we are caching indefinitely, files will be retained but the table still should be dropped. */
			\IPS\Task::queue( 'core', 'DeleteImageProxyFiles', array(), 5 );
		}

		/* Disable third party addons to prevent errors post-upgrade */
		foreach( \IPS\Application::enabledApplications() as $app )
		{
			if( !\in_array( $app->directory, \IPS\IPS::$ipsApps ) )
			{
				$app->enabled = false;
				$app->save();
			}
		}

		foreach( \IPS\Plugin::enabledPlugins() as $plugin )
		{
			$plugin->enabled = FALSE;
			$plugin->save();
		}

		return TRUE;
	}
	
	// You can create as many additional methods (step2, step3, etc.) as is necessary.
	// Each step will be executed in a new HTTP request
}

<?php
/**
 * @brief		4.1.17 Beta 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		03 Nov 2016
 */

namespace IPS\core\setup\upg_101071;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.17 Beta 1 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Clean up reputation table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* clean up the database to avoid any orphaned reputation data */
		\IPS\Db::i()->delete( 'core_reputation_index',  \IPS\Db::i()->in( 'app', array_keys( \IPS\Application::applications() ), TRUE ) );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Cleaning up orphaned reputation";
	}

	/**
	 * Add new core_Imageproxycache file storage extension, mirrored after existing attachments extension
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		/* Copy core_Attachment to core_Imageproxycache */
		$settings		= json_decode( \IPS\Settings::i()->upload_settings, TRUE );

		$settings['filestorage__core_Imageproxycache'] = $settings['filestorage__core_Attachment'];

		\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );

		/* That's all ... easy peasy */
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Adding file storage extension for image proxy caches";
	}
	
	/**
	 * Set analytics provider
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		/* It is likely this will be attempted before settings are created */
		$currentDefaults = iterator_to_array( \IPS\Db::i()->select( '*', 'core_sys_conf_settings' )->setKeyField('conf_key')->setValueField('conf_default') );

		foreach ( array(
			array( 'key' => 'ipbseo_ga_provider', 'default' => 'none' ),
			array( 'key' => 'ipbseo_ga_enabled', 'default' => 1 ) ) as $setting )
		{
			if ( ! array_key_exists( $setting['key'], $currentDefaults ) )
			{
				\IPS\Db::i()->insert( 'core_sys_conf_settings', array( 'conf_key' => $setting['key'], 'conf_value' => $setting['default'], 'conf_default' => $setting['default'], 'conf_app' => 'core' ), TRUE );
			}
		}
		
		if ( mb_strpos( \IPS\Settings::i()->ipseo_ga, 'i,s,o,g,r,a,m' ) !== FALSE )
		{
			\IPS\Settings::i()->changeValues( array( 'ipbseo_ga_provider' => 'ga' ) );
		}
		else if ( mb_strpos( \IPS\Settings::i()->ipseo_ga, 'piwik.js' ) !== FALSE )
		{
			\IPS\Settings::i()->changeValues( array( 'ipbseo_ga_provider' => 'piwik' ) );
		}
		else if ( \IPS\Settings::i()->ipseo_ga )
		{
			\IPS\Settings::i()->changeValues( array( 'ipbseo_ga_provider' => 'custom' ) );
		}
		else
		{
			\IPS\Settings::i()->changeValues( array( 'ipbseo_ga_provider' => 'none', 'ipbseo_ga_enabled' => '0' ) );
		}

		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step3CustomTitle()
	{
		return "Updating analytics settings";
	}
	
	/**
	 * Add Guest Terms Bar Text
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other values, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		\IPS\Lang::saveCustom( 'core', 'guest_terms_bar_text_value', 'By using this site, you agree to our %1$s.' );
		
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return	string
	 */
	public function step4CustomTitle()
	{
		return "Inserting default guest terms bar text";
	}
	
	/**
	 * Update mail setting
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		if ( \IPS\Settings::i()->mail_method == 'mail' ) 
		{
			\IPS\Settings::i()->changeValues( array( 'mail_method' => 'php' ) );
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
		return "Fixing email method setting";
	}
	
	/**
	 * Initiate rebuild tasks for repuation
	 *
	 * @return boolean
	 */
	public function finish()
	{
		$classes = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', FALSE ) as $object )
		{
			$classes = array_merge( $object->classes, $classes );
		}
		
		foreach( $classes as $item )
		{
			try
			{
				$commentClass = NULL;
				$reviewClass  = NULL;
				
				if ( isset( $item::$commentClass ) )
				{
					$commentClass = $item::$commentClass;
				}
				
				if ( isset( $item::$reviewClass ) )
				{
					$reviewClass = $item::$reviewClass;
				}
				
				if ( \IPS\IPS::classUsesTrait( $item, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $item ), 3 );
				}
				
				if ( $commentClass and \IPS\IPS::classUsesTrait( $commentClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $commentClass ), 3 );
				}
				
				if ( $reviewClass and \IPS\IPS::classUsesTrait( $reviewClass, 'IPS\Content\Reactable' ) )
				{
					\IPS\Task::queue( 'core', 'RebuildReputationIndex', array( 'class' => $reviewClass ), 3 );
				}
			}
			catch( \Exception $e ) { }
		}
		
		/* Rebuild search index */
		\IPS\Content\Search\Index::i()->rebuild();
		
		/* Rebuild leaderboard */
		\IPS\Task::queue( 'core', 'RebuildReputationLeaderboard', array(), 4 );
		
		/* Add menu item */
		\IPS\core\FrontNavigation::insertMenuItem( NULL, array( 'app' => 'core', 'key' => 'Leaderboard' ), \IPS\Db::i()->select( 'MAX(position)', 'core_menu' )->first() );
		
		return TRUE;
	}
}
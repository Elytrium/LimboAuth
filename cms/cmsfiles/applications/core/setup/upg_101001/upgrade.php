<?php
/**
 * @brief		4.1.0 Beta 2 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Oct 2015
 */

namespace IPS\core\setup\upg_101001;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.0 Beta 2 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Some file storage extensions (i.e. CMS) may never have been saved in the upload_settings setting, which resulted in the RepairFileUrls not being run against them, 
	 *	which then results in the files being deleted by the orphaned files tool because the full URL is still stored
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Anything that is not set, we'll assume the same setting as core_Attachment */
		$settings		= json_decode( \IPS\Settings::i()->upload_settings, TRUE );
		$defaultSetting	= $settings['filestorage__core_Attachment'];

		/* Set some variables to help us determine if we need to do anything else after */
		$hasChanges		= FALSE;
		$applications	= array();

		$_SESSION['repairFileUrls']	= false;

		/* Loop over all file storage extensions */
		foreach ( \IPS\Application::allExtensions( 'core', 'FileStorage', FALSE, NULL, NULL, TRUE ) as $name => $obj )
		{
			if( !isset( $settings[ "filestorage__{$name}" ] ) )
			{
				$settings[ "filestorage__{$name}" ]	= $defaultSetting;
				$hasChanges	= TRUE;
			}
		}

		/* Now, if we have changes, update the setting and reinitiate the RepairFileUrls background queue task for the affected application(s) */
		if( $hasChanges )
		{
			\IPS\Settings::i()->changeValues( array( 'upload_settings' => json_encode( $settings ) ) );

			$_SESSION['repairFileUrls']	= true;
		}

		/* That's all ... easy peasy */
		return TRUE;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Fixing missing upload storage configurations";
	}

	/**
	 * Orphaned private message maps from older versions can cause errors for specific users
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$toRunQueries	= array(
			array(
				'table'	=> 'core_message_topic_user_map',
				'query'	=> "DELETE FROM " . \IPS\Db::i()->prefix . "core_message_topic_user_map WHERE map_topic_id NOT IN(SELECT mt_id FROM " . \IPS\Db::i()->prefix . "core_message_topics)",
			)
		);

		$toRun = \IPS\core\Setup\Upgrade::runManualQueries( $toRunQueries );
		
		if ( \count( $toRun ) )
		{
			\IPS\core\Setup\Upgrade::adjustMultipleRedirect( array( 'extra' => array( '_upgradeStep' => 3 ) ) );

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
	public function step2CustomTitle()
	{
		return "Removing orphaned personal message maps";
	}

	/**
	 * Repair URLs
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function finish()
	{
		if( isset( $_SESSION['repairFileUrls'] ) AND $_SESSION['repairFileUrls'] )
		{
			foreach( \IPS\Application::applications() as $app )
			{
				\IPS\core\Setup\Upgrade::repairFileUrls( $app->directory );
			}
		}
	}
}
<?php
/**
 * @brief		4.1.14 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Jul 2016
 */

namespace IPS\core\setup\upg_101042;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.1.14 Upgrade Code
 */
class _Upgrade
{
	/**
	 * Update FURL customisations
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		$customisedFurls = \IPS\Settings::i()->furl_configuration ? json_decode( \IPS\Settings::i()->furl_configuration, TRUE ) : array();
		$uncustomisedFurls = \IPS\Http\Url\Friendly::furlDefinition( TRUE );
		$newValueToSave = array();
		
		foreach ( $customisedFurls as $k => $data )
		{
			if ( !isset( $uncustomisedFurls[ $k ] ) )
			{
				$newValueToSave[ $k ] = \IPS\Http\Url\Friendly::buildFurlDefinition( $data['friendly'], $data['real'], NULL, FALSE, NULL, TRUE );
			}
			else
			{
				$newValueToSave[ $k ] = $uncustomisedFurls[ $k ];
				if ( $data['friendly'] != $uncustomisedFurls[ $k ]['friendly'] )
				{
					$newValueToSave[ $k ] = \IPS\Http\Url\Friendly::buildFurlDefinition( $data['friendly'], $data['real'], NULL, FALSE, NULL, TRUE );
				}
			}
		}
		
		\IPS\Settings::i()->changeValues( array( 'furl_configuration' => json_encode( $newValueToSave ) ) );
		\IPS\Data\Cache::i()->clearAll();

		return true;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Updating friendly URL customizations";
	}

	/**
	 * Fix incorrectly converted warn logs (if any)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$perCycle	= 1000;
		$did		= 0;
		$doneSoFar	= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff			= \IPS\core\Setup\Upgrade::determineCutoff();

		foreach( \IPS\Db::i()->select( '*', 'core_members_warn_logs', array( "wl_mq LIKE '%TH' OR wl_rpa LIKE '%TH' OR wl_suspend LIKE '%TH'" ), 'wl_id ASC', array( 0, $perCycle ) ) as $log )
		{
			if( $cutOff !== null AND time() >= $cutOff )
			{
				return ( $doneSoFar + $did );
			}

			$did++;

			/* Fix incorrectly stored dateinterval values */
			$update	= array();

			if( \strpos( $log['wl_mq'], 'TH' ) )
			{
				$update['wl_mq'] = preg_replace( "/^P([0-9]+?)TH$/", "PT$1H", $log['wl_mq'] );
			}

			if( \strpos( $log['wl_rpa'], 'TH' ) )
			{
				$update['wl_rpa'] = preg_replace( "/^P([0-9]+?)TH$/", "PT$1H", $log['wl_rpa'] );
			}

			if( \strpos( $log['wl_suspend'], 'TH' ) )
			{
				$update['wl_suspend'] = preg_replace( "/^P([0-9]+?)TH$/", "PT$1H", $log['wl_suspend'] );
			}

			if( \count( $update ) )
			{
				\IPS\Db::i()->update( 'core_members_warn_logs', $update, "wl_id=" . $log['wl_id'] );
			}
		}

		if( $did )
		{
			return ( $doneSoFar + $did );
		}
		else
		{
			unset( $_SESSION['_step2Count'] );

			return TRUE;
		}
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		$doneSoFar = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step2Count'] ) )
		{
			$_SESSION['_step2Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members_warn_logs', array( "wl_mq LIKE '%TH' OR wl_rpa LIKE '%TH' OR wl_suspend LIKE '%TH'" ) )->first();
		}

		return "Fixing member warnings (Fixed so far: " . ( ( $doneSoFar > $_SESSION['_step2Count'] ) ? $_SESSION['_step2Count'] : $doneSoFar ) . ' out of ' . $_SESSION['_step2Count'] . ')';
	}
	
	/**
	 * Update Pages permissions
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		if ( ! \IPS\Application::appIsEnabled('cms' ) )
		{
			return true;
		}
		
		foreach( \IPS\Db::i()->select( '*', 'core_moderators', array( 'perms != ?', '*' ) ) as $mod )
		{
			$perms = json_decode( trim( $mod['perms'] ), TRUE );
			
			if ( \is_array( $perms ) and array_key_exists( 'cms', $perms ) )
			{
				foreach ( \IPS\cms\Databases::databases() as $id => $database )
				{
					if( $database->page_id )
					{
						/* Set all categories to -1 which means all */
						$perms['cms' . $database->id] = -1;
					}
				}
				
				unset( $perms['cms'] );
				
				\IPS\Db::i()->update( 'core_moderators', array( 'perms' => json_encode( $perms ) ), array( 'type=? and id=?', $mod['type'], $mod['id'] ) );
			}
		}

		return true;
	}
	
	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Updating pages database moderator permissions";
	}
	
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		/* New URL is more strict, and legacy upgrades can have members_seo_names missing which throws an exception */
		\IPS\Task::queue( 'core', 'UpdateMemberSeoNames', array(), 4 );
	}
}
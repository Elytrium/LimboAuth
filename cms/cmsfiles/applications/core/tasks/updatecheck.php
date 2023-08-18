<?php
/**
 * @brief		updatecheck Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Aug 2013
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * updatecheck Task
 */
class _updatecheck extends \IPS\Task
{
	/**
	 * @brief	Type to send to update server
	 */
	public $type = 'task';

	/**
	 * @brief	Cache marketplace controller
	 */
	protected $_marketplaceController = null;
	
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 */
	public function execute()
	{
		/* Refresh stored license data */
		\IPS\IPS::licenseKey( TRUE );
		
		$fails = array();
		
		/* Do IPS apps */
		$versions = array();
		foreach ( \IPS\Db::i()->select( '*', 'core_applications', \IPS\Db::i()->in( 'app_directory', \IPS\IPS::$ipsApps ) ) as $app )
		{
			if ( $app['app_enabled'] )
			{
				$versions[] = $app['app_long_version'];
			}
		}
		$version = min( $versions );
		$url = \IPS\Http\Url::ips('updateCheck')->setQueryString( array( 'type' => $this->type, 'key' => \IPS\Settings::i()->ipb_reg_number ) );
		if ( \IPS\USE_DEVELOPMENT_BUILDS )
		{
			$url = $url->setQueryString( 'development', 1 );
		}
		if ( \IPS\IPS_ALPHA_BUILD )
		{
			$url = $url->setQueryString( 'alpha', 1 );
		}
		try
		{
			$response = $url->setQueryString( 'version', $version )->request()->get()->decodeJson();
						
			$coreApp = \IPS\Application::load('core');
			$coreApp->update_version = json_encode( $response );
			$coreApp->update_last_check = time();
			$coreApp->save();

			/* Send a notification if new version is available */
			if ( $updates = $coreApp->availableUpgrade() and \count( $updates ) )
			{
				\IPS\core\AdminNotification::send( 'core', 'NewVersion', NULL, FALSE );
			}
			else
			{
				\IPS\core\AdminNotification::remove( 'core', 'NewVersion' );
			}
		}
		catch ( \Exception $e ) { }

		/* Check for bulletins while we're here */
		try
		{
			$bulletins = \IPS\Http\Url::ips('bulletin')->request()->get()->decodeJson();

			foreach ( $bulletins as $id => $bulletin )
			{
				\IPS\Db::i()->insert( 'core_ips_bulletins', array(

					'id' 			=> $id,
					'title'			=> $bulletin['title'],
					'body'			=> $bulletin['body'],
					'severity'		=> $bulletin['severity'],
					'style'			=> $bulletin['style'],
					'dismissible'	=> $bulletin['dismissible'],
					'link'			=> $bulletin['link'],
					'conditions'	=> $bulletin['conditions'],
					'cached'		=> time(),
					'min_version'	=> $bulletin['minVersion'],
					'max_version'	=> $bulletin['maxVersion']
				), TRUE );

				/* Don't send the notification until after we insert the bulletin data */
				try
				{
					if (
						/*  If the value is 0 for minVersion, there is no minimum version (a.k.a., display it). Same deal with maxVersion. */
						( $bulletin['minVersion'] == 0 AND $bulletin['maxVersion'] == 0 )
						/* If there's no minimum version, and the maximum Version is within range */
						OR ( $bulletin['minVersion'] == 0 AND \IPS\Application::load('core')->long_version < $bulletin['maxVersion'] )
						/* If there's no maximum version, and the minimum version is within range */
						OR ( $bulletin['maxVersion'] == 0 AND \IPS\Application::load('core')->long_version > $bulletin['minVersion'] )				
						/* If both min and max versions are within range */
						OR ( \IPS\Application::load('core')->long_version >= $bulletin['minVersion'] AND \IPS\Application::load('core')->long_version <= $bulletin['maxVersion'] )
					)
					{
						if ( @eval( $bulletin['conditions'] ) )
						{
							\IPS\core\AdminNotification::send( 'core', 'Bulletin', (string) $id, FALSE );
						}
						
						else
						{
							\IPS\core\AdminNotification::remove( 'core', 'Bulletin', (string) $id );
						}
					}
					
					else
					{
						\IPS\core\AdminNotification::remove( 'core', 'Bulletin', (string) $id );
					}
				}
				catch ( \Throwable | \Exception $e )
				{
					\IPS\Log::log( $e, 'bulletin' );
				}
			}
		}
		catch( \RuntimeException $e ){ }

		$updateChecked = [];
		$marketplaceUpdates = [];
		$fails = [];
		$fiveMinutesAgo = time() - 300;

		$this->runUntilTimeout( function() use ( &$updateChecked, &$marketplaceUpdates, &$fails, $version, $fiveMinutesAgo ) {
			try
			{
				$row = \IPS\Db::i()->union(
					array(
						\IPS\Db::i()->select( "'core_applications' AS `table`, app_directory AS `id`, app_update_check AS `url`, app_update_last_check AS `last`, app_long_version AS `current`, app_marketplace_id as `marketplace_id`", 'core_applications', "app_update_last_check<{$fiveMinutesAgo} AND ( ( app_update_check<>'' AND app_update_check IS NOT NULL ) OR app_marketplace_id IS NOT NULL )" ),
						\IPS\Db::i()->select( "'core_plugins' AS `table`, plugin_id AS id, plugin_update_check as url, plugin_update_check_last AS last, plugin_version_long AS `current`, plugin_marketplace_id as `marketplace_id`", 'core_plugins', "plugin_update_check_last<{$fiveMinutesAgo} AND ( (plugin_update_check<>'' AND plugin_update_check IS NOT NULL ) OR plugin_marketplace_id IS NOT NULL )" ),
						\IPS\Db::i()->select( "'core_themes' AS `table`, set_id AS `id`, set_update_check AS `url`, set_update_last_check AS `last`, set_long_version AS `current`, set_marketplace_id as `marketplace_id`", 'core_themes', "set_update_last_check<{$fiveMinutesAgo} AND ( (set_update_check<>'' AND set_update_check IS NOT NULL ) OR set_marketplace_id IS NOT NULL )" ),
						\IPS\Db::i()->select( "'core_sys_lang' AS `table`, lang_id AS `id`, `lang_update_url` AS `url`, `lang_update_check` AS `last`, `lang_version_long` AS `current`, `lang_marketplace_id` as `marketplace_id`", "core_sys_lang", "lang_update_check<{$fiveMinutesAgo} AND ( (lang_update_url<>'' AND lang_update_url IS NOT NULL ) OR lang_marketplace_id IS NOT NULL )" )
					),
					'last ASC',
					1
				)->first();
			}
			catch( \UnderflowException $e )
			{
				return FALSE;
			}

			switch ( $row['table'] )
			{
				case 'core_applications':
					$dataColumn = 'app_update_version';
					$timeColumn = 'app_update_last_check';
					$idColumn	= 'app_directory';
					$updateChecked[] = 'applications';

					/* Account for legacy applications */
					try
					{
						$key = "__app_{$row['id']}";
						$source = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( $key );
					}
					catch ( \UnderflowException | \UnexpectedValueException $e )
					{
						return;
					}
					break;

				case 'core_plugins':
					$dataColumn = 'plugin_update_check_data';
					$timeColumn = 'plugin_update_check_last';
					$idColumn	= 'plugin_id';
					$source     = \IPS\Plugin::load( $row['id'] )->name;
					$updateChecked[] = 'plugins';
					break;

				case 'core_themes':
					$dataColumn = 'set_update_data';
					$timeColumn = 'set_update_last_check';
					$idColumn	= 'set_id';
					$key = "core_theme_set_title_{$row['id']}";
					$source = \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( $key );
					$updateChecked[] = 'themes';
					break;
				
				case 'core_sys_lang':
					$dataColumn	= 'lang_update_data';
					$timeColumn	= 'lang_update_check';
					$idColumn	= 'lang_id';
					$source		= \IPS\Lang::load( $row['id'] )->_title;
					$updateChecked[] = 'languages';
					break;
			}

			try
			{
				/* Query the marketplace */
				if( $row['marketplace_id'] )
				{
					$marketplaceUpdates[ $row['marketplace_id'] ] = [
						'table'         => $row['table'],
						'current'       => $row['current'],
						'dataColumn'    => $dataColumn,
						'timeColumn'    => $timeColumn,
						'idColumn'      => $idColumn,
						'id'            => $row['id'],
						'marketplace_id'=> $row['marketplace_id']
					];

					/* Update the timestamp so we can deal with other resources */
					\IPS\Db::i()->update( $row['table'], array(
						$timeColumn	=> time()
					), array( "{$idColumn}=?", $row['id'] ) );

					return TRUE;
				}
				/* Query the applications update URL */
				else
				{
					$url = \IPS\Http\Url::external( $row['url'] )->setQueryString( array( 'version' => $row['current'], 'ips_version' => $version ) );
					$response = $url->request()->get()->decodeJson();
				}

				/* Unset the object so it isn't present for next update check. */
				unset( $object );

				/* Did we get all the information we need? */
				if ( !isset( $response['version'], $response['longversion'], $response['released'], $response['updateurl'] ) )
				{
					throw new \RuntimeException( \IPS\Lang::load( \IPS\Lang::defaultLanguage() )->get( 'update_check_missing' ) );
				}

				/* Save the latest version data and move on to the next app */
				\IPS\Db::i()->update( $row['table'], array(
					$dataColumn => json_encode( array(
						'version'		=> $response['version'],
						'longversion'	=> $response['longversion'],
						'released'		=> $response['released'],
						'updateurl'		=> $response['updateurl'],
						'releasenotes'	=> isset( $response['releasenotes'] ) ? $response['releasenotes'] : NULL
					) ),
					$timeColumn	=> time()
				), array( "{$idColumn}=?", $row['id'] ) );
			}
			/* \RuntimeException catches BAD_JSON and \IPS\Http\Request\Exception both */
			catch ( \RuntimeException $e )
			{
				$fails[] = $source . ": " . $e->getMessage();

				/* Save the time so that the next time the task runs it can move on to other apps/plugins/themes */
				\IPS\Db::i()->update( $row['table'], array(
					$timeColumn	=> time()
				), array( "{$idColumn}=?", $row['id'] ) );
			}

			return TRUE;
		});

		/* Check Marketplace updates in bulk */
		if( \count( $marketplaceUpdates ) )
		{
			try
			{
				$marketplaceController = new \IPS\core\modules\admin\marketplace\marketplace;
				$response = $marketplaceController->_updateCheck( array_column( $marketplaceUpdates, 'current', 'marketplace_id' ) );

				foreach( $marketplaceUpdates as $resourceId => $resourceData )
				{
					$releaseData = $response[ $resourceId ] ?? NULL;

					if( $releaseData !== NULL )
					{
						if( $resourceData['table'] == 'core_applications' )
						{
							$object = \IPS\Application::load( $resourceData['id'] );
						}
						elseif( $resourceData['table'] == 'core_plugins' )
						{
							$object = \IPS\Plugin::load( $resourceData['id'] );
						}

						/* Use a local update URL */
						$releaseData['updateurl'] = (string) \IPS\Http\Url::internal("app=core&module=marketplace&controller=marketplace&do=viewFile&id={$resourceId}", 'admin' );
						if( isset( $object ) AND isset( $releaseData['disableresource'] ) AND $releaseData['disableresource'] === TRUE )
						{
							$object->_enabled = FALSE;
							$object->save();
						}
					}

					\IPS\Db::i()->update( $resourceData['table'], array(
						$resourceData['dataColumn'] => json_encode( $releaseData ),
						$resourceData['timeColumn']	=> time()
					), array( "{$resourceData['idColumn']}=?", $resourceData['id'] ) );
				}
			}
			catch( \RuntimeException | \UnexpectedValueException $e )
			{
				$fails[] = '';
			}
		}

		/* Reset Menu Cache */
		foreach( $updateChecked as $type )
		{
			$key = 'updatecount_' . $type;
			unset( \IPS\Data\Store::i()->$key, \IPS\Data\Store::i()->$type );
		}
		
		if ( !empty( $fails ) )
		{
			return $fails;
		}
		
		return NULL;
	}
}
<?php
/**
 * @brief		Upgrader: Applications
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */

namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Applications
 */
class _applications extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		$apps			= array();

		/* Update old app keys or everything gets confused */
		\IPS\Db::i()->update( 'core_applications', array( 'app_directory' => 'cms' ), array( 'app_directory=?', 'ccs' ) );
		\IPS\Db::i()->update( 'core_applications', array( 'app_directory' => 'chat' ), array( 'app_directory=?', 'ipchat' ) );

		/* We had a bug in an earlier beta where the version may not have updated properly, so we need to account for that but it has to happen before we load version files */
		/* @todo We may want to remove those down the road as it should only affect users who have upgraded to early betas */
		if( \IPS\Db::i()->checkForTable( 'core_widgets' ) AND !\IPS\Db::i()->checkForColumn( 'core_widgets', 'embeddable' ) )
		{
			\IPS\Db::i()->update( 'core_applications', array( 'app_version' => '4.0.0 Beta 1', 'app_long_version' => '100001' ) );
		}

		/* Clear any caches or else we might not see new versions */
		if ( isset( \IPS\Data\Store::i()->applications ) )
		{
			unset( \IPS\Data\Store::i()->applications );
		}
		if ( isset( \IPS\Data\Store::i()->modules ) )
		{
			unset( \IPS\Data\Store::i()->modules );
		}

		foreach( \IPS\Application::applications() as $app => $data )
		{
			$path = \IPS\Application::getRootPath( $app ) . '/applications/' . $app;

			if ( $app == 'chat' )
			{
				continue;
			}

			/* Skip incomplete apps */
			if ( ! is_dir( $path . '/data' ) )
			{
				continue;
			}

			/* See if there are any errors */
			$errors = array();

			if ( file_exists( $path . '/setup/requirements.php' ) )
			{
				require $path . '/setup/requirements.php';
			}

			/* Figure out of an upgrade is even available */
			$currentVersion		= \IPS\Application::load( $app )->long_version;
			$availableVersion	= \IPS\Application::getAvailableVersion( $app );

			$name = $data->_title;

			/* Get app name */
			if ( file_exists( $path . '/data/lang.xml' ) )
			{
				$xml = \IPS\Xml\XMLReader::safeOpen( $path . '/data/lang.xml' );
				$xml->read();

				$xml->read();
				while ( $xml->read() )
				{
					if ( $xml->getAttribute('key') === '__app_' . $app )
					{
						$name = $xml->readString();
						break;
					}
				}
			}

			if( $availableVersion > $currentVersion )
			{
				$apps[ $app ] = array(
					'name'		=> $name,
					'disabled'	=> ( !empty( $errors ) OR $availableVersion <= $currentVersion ),
					'errors'	=> $errors,
					'current'	=> \IPS\Application::load( $app )->version,
					'available'	=> \IPS\Application::getAvailableVersion( $app, TRUE )
				);
			}

		}

		if( \count( $apps ) )
		{
			/* Make sure the core app is the first index */
			if( isset( $apps['core'] ) )
			{
				$apps = [ 'core' => $apps['core'] ] + $apps;
			}

			$_SESSION['apps'] = $apps;

			$warnings = array();
			$coreVersion = array_key_exists( 'core', $_SESSION['apps'] ) ? \IPS\Application::getAvailableVersion( 'core' ) : \IPS\Application::load( 'core' )->long_version;
			foreach ( \IPS\IPS::$ipsApps as $key )
			{
				if ( $key == 'chat' )
				{
					continue;
				}

				try
				{
					$appVersion = array_key_exists( $key, $_SESSION['apps'] ) ? \IPS\Application::getAvailableVersion( $key ) : \IPS\Application::load( $key )->long_version;
					if ( $appVersion != $coreVersion )
					{
						$warnings[] = $key;
					}
				}
				catch( \OutOfRangeException $e )
				{
					/* The application is not installed */
					continue;
				}
			}

			if ( \count( $warnings ) )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=applications&do=warning" )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'warnings' => implode( ',', $warnings ) ) ) );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=customoptions" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
		}
		else
		{
			$form	= \IPS\Theme::i()->getTemplate( 'forms' )->noapps();

			\IPS\core\Setup\Upgrade::setUpgradingFlag( FALSE );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('applications');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'applications', $form );
	}

	/**
	 * Show Warning
	 *
	 * @return	void
	 */
	public function warning()
	{
		$apps = array();
		foreach ( explode( ',', \IPS\Request::i()->warnings ) as $key )
		{
			try
			{
				$name = \IPS\Application::load( $key )->_title;
				$path = \IPS\ROOT_PATH . '/applications/' . $key;
				if ( file_exists( $path . '/data/lang.xml' ) )
				{
					$xml = \IPS\Xml\XMLReader::safeOpen( $path . '/data/lang.xml' );
					$xml->read();

					$xml->read();
					while ( $xml->read() )
					{
						if ( $xml->getAttribute('key') === '__app_' . $key )
						{
							$name = $xml->readString();
							break;
						}
					}
				}
				$apps[] = $name;
			}
			catch ( \Exception $e ) { }
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('applications');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'applications', \IPS\Theme::i()->getTemplate( 'global' )->appWarnings( $apps ) );
	}
}
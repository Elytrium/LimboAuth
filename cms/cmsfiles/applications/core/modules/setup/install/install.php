<?php
/**
 * @brief		Installer: Install
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		2 Apr 2013
 */
 
namespace IPS\core\modules\setup\install;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Installer: Install
 */
class _install extends \IPS\Dispatcher\Controller
{
	/**
	 * Install
	 */
	public function manage()
	{
		require \IPS\ROOT_PATH . '/conf_global.php';
		
		/* Zend Server has an issue where it caches 'require'd files which means the admin details written in the
		   previous step aren't in the $INFO array which causes the MultiRedirect to fail. Making the page reload after
		   a pause fixes the issue so we manually request a page refresh rather than doing it automatically */
		if ( ! isset( $INFO['admin_user'] ) )
		{
			\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('install');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->manualStart();
			
			return;
		}
		
		$multipleRedirect = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'controller=install' ),
			function( $data )
			{
				try
				{
					require \IPS\ROOT_PATH . '/conf_global.php';
					$install = new \IPS\core\Setup\Install(
						$INFO['apps'],
						$INFO['default_app'],
						$INFO['base_url'],
						mb_substr( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), 0, -mb_strlen( 'install/index.php' ) ),
						array( 'sql_host' => $INFO['sql_host'], 'sql_user' => $INFO['sql_user'], 'sql_pass' => $INFO['sql_pass'], 'sql_database' => $INFO['sql_database'], 'sql_port' => $INFO['sql_port'], 'sql_socket' => $INFO['sql_socket'], 'sql_tbl_prefix' => $INFO['sql_tbl_prefix'] ),
						$INFO['admin_user'],
						$INFO['admin_pass1'],
						$INFO['admin_email'],
						$INFO['diagnostics_reporting']
						);
				}
				catch ( \InvalidArgumentException $e )
				{
					\IPS\Output::i()->error( 'error', '4S112/1', 403, '' );
				}
		
				try
				{
					return $install->process( $data );
				}
				catch( \Exception $e )
				{
					$backtrace = $e->getTraceAsString();

					$error = \IPS\Theme::i()->getTemplate( 'global' )->error( "Error", $e->getMessage() ?: "Error", $e->getCode(), $backtrace );
					
					\IPS\Request::i()->start = true;
					\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('error');
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'install', $error, FALSE );
					 
					/* If we're still here - output */
					if ( \IPS\Request::i()->isAjax() )
					{
						\IPS\Output::i()->sendOutput( \IPS\Output::i()->output, 200, 'text/html' );
					}
					else
					{
						\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->globalTemplate( \IPS\Output::i()->title, \IPS\Output::i()->output ), 403, 'text/html' );
					}
				}
			},
			function()
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=done' ) );
			}
		);
	
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('install');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'install', $multipleRedirect, FALSE );
	}
}
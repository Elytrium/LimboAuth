<?php
/**
 * @brief		Upgrader: Perform Upgrade
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
 * Upgrader: Perform Upgrade
 */
class _upgrade extends \IPS\Dispatcher\Controller
{
	/**
	 * Upgrade
	 *
	 * @return	void
	 */
	public function manage()
	{
		$multipleRedirect = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( 'key', $_SESSION['uniqueKey'] ),
			function( $data )
			{
				try
				{
					$upgrader = new \IPS\core\Setup\Upgrade( array_keys( $_SESSION['apps'] ) );
				}
				catch ( \Exception $e )
				{
					\IPS\Output::i()->error( 'error', '2C222/1', 403, '' );
				}
				
				try
				{
					return $upgrader->process( $data );
				}
				catch( \Exception $e )
				{
					\IPS\Log::log( $e, 'upgrade_error' );
					
					/* Error thrown that we want to handle differently */
					$key		= 'mr-' . md5( \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );

					if ( isset( $_SESSION['updatedData'] ) and isset( $_SESSION['updatedData'][1] ) )
					{
						$_SESSION[ $key ] = json_encode( $_SESSION['updatedData'] );
					}

					$continueUrl = \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr' => 1, 'mr_continue' => 1 ) );
					$retryUrl    = \IPS\Http\Url::internal( 'controller=upgrade' )->setQueryString( array( 'key' => $_SESSION['uniqueKey'], 'mr' => 1 ) );
					
					$error = \IPS\Theme::i()->getTemplate( 'global' )->upgradeError( $e, $continueUrl, $retryUrl );
					
					return array( \IPS\Theme::i()->getTemplate( 'global' )->block( 'install', $error, FALSE ) );
				}
				catch( \BadMethodCallException $e )
				{
					/* Allow multi-redirect handle this */
					throw $e;
				}
			},
			function()
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=done' ) );
			}
		);
	
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('upgrade');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'upgrade', $multipleRedirect, FALSE );
	}
}
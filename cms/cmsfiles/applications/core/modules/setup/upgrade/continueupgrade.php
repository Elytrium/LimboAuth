<?php
/**
 * @brief		Upgrader: Continue Upgrade
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Nov 2014
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Continue Upgrade
 */
class _continueupgrade extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Best check for the upgrade file first */
		try
		{
			$json = json_decode( \IPS\Db::i()->select( 'upgrade_data', 'upgrade_temp' )->first(), TRUE );
		}
		catch( \UnderflowException $e )
		{
			$json = NULL;
		}
			
		if ( \is_array( $json ) and isset( $json['session'] ) and isset( $json['data'] ) )
		{
			$url = \IPS\Http\Url::internal( "controller=upgrade" )->setQueryString( 'key', $_SESSION['uniqueKey'] );
			
			/* Update session */
			foreach( $json['session'] as $k => $v )
			{
				if ( $k !== 'uniqueKey' )
				{
					$_SESSION[ $k ] = $v;
				}
			}
			
			/* Populate the MR data */
			$_SESSION[ 'mr-' . md5( $url ) ] = json_encode( $json['data'] );
			\IPS\Output::i()->redirect( $url );
		}
		else
		{
			\IPS\Output::i()->error( 'cannot_continue_upgrade', '', 403, '' );
		}
	}
}
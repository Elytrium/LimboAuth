<?php
/**
 * @brief		Upgrader: System Check
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
 * Upgrader: System Check
 */
class _systemcheck extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Do we have an older upgrade session hanging around? */
		if ( ! isset( \IPS\Request::i()->skippreviousupgrade ) AND \IPS\Db::i()->checkForTable( 'upgrade_temp' ) )
		{
			try
			{
				$row  = \IPS\Db::i()->select( '*', 'upgrade_temp' )->first();
				$json = json_decode( $row['upgrade_data'], TRUE );
			}
			catch( \UnderflowException $e )
			{
				$json = NULL;
			}

			if ( \is_array( $json ) and isset( $json['session'] ) and isset( $json['data'] ) )
			{
				\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('unfinished_upgrade');
				\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->unfinishedUpgrade( $json, $row['lastaccess'] );
				return;
			}
		}
		elseif( isset( \IPS\Request::i()->skippreviousupgrade ) AND \IPS\Request::i()->skippreviousupgrade )
		{
			/* If we skip the previous upgrade remove the temp upgrade data now. That way if we hit utf8 upgrader and come back to upgrader we are
				not prompted again to continue our upgrade we already elected to start over */
			\IPS\Db::i()->dropTable( 'upgrade_temp', TRUE );
		}
		
		/* Do we need to disable designer mode? */
		if ( isset( \IPS\Request::i()->disableDesignersMode ) )
		{
			\IPS\Settings::i()->changeValues( array( 'theme_designers_mode' => 0 ) );
		}
		
		/* Get requirements */
		$requirements = \IPS\core\Setup\Upgrade::systemRequirements();
		$designersModeEnabled = ( \IPS\Application::load('core')->long_version >= 40000 and \IPS\Theme::designersModeEnabled() ) ? TRUE : FALSE;
		
		/* Can we just skip this screen? */
		$canProceed = FALSE;
		if ( !$designersModeEnabled )
		{
			$canProceed = !isset( $requirements['advice'] ) or !\count( $requirements['advice'] );
			if ( $canProceed )
			{
				foreach ( $requirements['requirements'] as $k => $_requirements )
				{
					foreach ( $_requirements as $item )
					{
						if ( !$item['success'] )
						{
							$canProceed = FALSE;
						}
					}
				}
			}
		}
		
		/* Check we have the latest version and run an md5 check */
		$incorrectFiles = array();
		/* Or do not check :) */
		
		/* Display */
		if ( $canProceed )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal("controller=license&key={$_SESSION['uniqueKey']}") );
		}		
		\IPS\Output::i()->title	 = \IPS\Member::loggedIn()->language()->addToStack('healthcheck');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->healthcheck( $requirements, $designersModeEnabled, $incorrectFiles );
	}
}
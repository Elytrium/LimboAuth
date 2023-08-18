<?php
/**
 * @brief		Installer: Admin Account
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
 * Installer: Admin Account
 */
class _admin extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 */
	public function manage()
	{
		$form = new \IPS\Helpers\Form( 'admin', 'continue' );
		
		$form->add( new \IPS\Helpers\Form\Text( 'admin_user', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'admin_pass1', NULL, TRUE, array() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'admin_pass2', NULL, TRUE, array( 'confirm' => 'admin_pass1' ) ) );
		$form->add( new \IPS\Helpers\Form\Email( 'admin_email', NULL, TRUE ) );
		
		if ( $values = $form->values() )
		{
			$INFO = NULL;
			require \IPS\ROOT_PATH . '/conf_global.php';
			$INFO = array_merge( $INFO, $values );
			
			$toWrite = "<?php\n\n" . '$INFO = ' . var_export( $INFO, TRUE ) . ";";
			
			try
			{
				if ( \file_put_contents( \IPS\ROOT_PATH . '/conf_global.php', $toWrite ) )
				{
					/* PHP 5.5 - clear opcode cache or details won't be seen on next page load */
					if ( \function_exists( 'opcache_invalidate' ) )
					{
						@opcache_invalidate( \IPS\ROOT_PATH . '/conf_global.php' );
					}
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=install' ) );
				}
			}
			catch( \Exception $ex )
			{
				$errorform = new \IPS\Helpers\Form( 'admin', 'continue' );
				$errorform->add( new \IPS\Helpers\Form\TextArea( 'conf_global_error', $toWrite, FALSE ) );
				
				foreach( $values as $k => $v )
				{
					$errorform->hiddenValues[ $k ] = $v;
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->confWriteError( $errorform, \IPS\ROOT_PATH );
				return;
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('admin');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'admin', $form );
	}
}
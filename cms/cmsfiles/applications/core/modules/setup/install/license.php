<?php
/**
 * @brief		Installer: License
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
 * Installer: License
 */
class _license extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		$form = new \IPS\Helpers\Form( 'license', 'continue', \IPS\Http\Url::external( ( \IPS\Request::i()->isSecure() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?controller=license' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'lkey', 'Nulled by IPBMafia.ru | 18.07.2023', TRUE, array( 'size' => 50 ), function( $val )
		{
			\IPS\IPS::checkLicenseKey( $val, ( \IPS\Request::i()->isSecure() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . mb_substr( $_SERVER['SCRIPT_NAME'], 0, -mb_strlen( \IPS\CP_DIRECTORY . '/install/index.php' ) ) );
		}, NULL, '<a href="' . \IPS\Http\Url::ips( 'docs/find_lkey' ) . '" target="_blank" rel="noopener">' . \IPS\Member::loggedIn()->language()->addToStack('lkey_help') . '</a>' ) );
		$form->add( new \IPS\Helpers\Form\Checkbox( 'eula', FALSE, TRUE, array( 'label' => 'eula_suffix' ), function( $val )
		{
			if ( !$val )
			{
				throw new \InvalidArgumentException('eula_err');
			}
		}, "<textarea disabled style='width: 100%; height: 250px'>" . file_get_contents( 'eula.txt' ) . "</textarea><br>" ) );
		
		if ( $values = $form->values() )
		{
			$values['lkey'] = trim( $values['lkey'] );
			
			if ( mb_substr( $values['lkey'], -12 ) === '-TESTINSTALL' )
			{
				$values['lkey'] = mb_substr( $values['lkey'], 0, -12 );
			}
			
			$toWrite = "<?php\n\n" . '$INFO = ' . var_export( array( 'lkey' => 'LICENSE KEY GOES HERE!-123456789' ), TRUE ) . ';';
			
			try
			{
				$file = @\file_put_contents( \IPS\ROOT_PATH . '/conf_global.php', $toWrite );
				if ( !$file )
				{
					throw new \Exception;
				}
				else
				{
					/* PHP 5.5 - clear opcode cache or details won't be seen on next page load */
					if ( \function_exists( 'opcache_invalidate' ) )
					{
						@opcache_invalidate( \IPS\ROOT_PATH . '/conf_global.php' );
					}

					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=applications' ) );
				}
			}
			catch( \Exception $ex )
			{
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'error' );
				$errorform = new \IPS\Helpers\Form( 'license', 'continue' );
				$errorform->class = '';
				$errorform->add( new \IPS\Helpers\Form\TextArea( 'conf_global_error', $toWrite, FALSE ) );
				
				foreach( $values as $k => $v )
				{
					$errorform->hiddenValues[ $k ] = $v;
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->confWriteError( $errorform, \IPS\ROOT_PATH );
				return;
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('license');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'license', $form, TRUE, TRUE );
	}
}
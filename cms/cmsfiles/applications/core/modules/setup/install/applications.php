<?php
/**
 * @brief		Installer: Applications
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
 * Installer: Applications
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
		$INFO = NULL;
		require \IPS\ROOT_PATH . '/conf_global.php';

		if( !isset( $INFO['lkey'] ) OR !$INFO['lkey'] )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=license' ) );
		}

		$form = new \IPS\Helpers\Form( 'applications', 'continue' );
		
		$apps = array();
		$defaultTicks = array( 'core' );
		foreach( new \DirectoryIterator( \IPS\ROOT_PATH . '/applications' ) as $app )
		{
			if ( mb_substr( $app, 0, 1 ) !== '.' and $app != 'index.html' )
			{
				/* Skip incomplete apps */
				if ( ! is_dir( $app->getPathName() . '/data' ) )
				{
					continue;
				}

				$errors = array();

				if ( file_exists( $app->getPathName() . '/setup/requirements.php' ) )
				{
					require $app->getPathName() . '/setup/requirements.php';
				}

				if ( empty( $errors ) and (string) $app !== 'core' )
				{
					$defaultTicks[] = (string) $app;
				}

				$name = $app;

				/* Get app name */
				if ( file_exists( $app->getPathName() . '/data/lang.xml' ) )
				{
					$xml = \IPS\Xml\XMLReader::safeOpen( $app->getPathName() . '/data/lang.xml' );
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

				$apps[ (string) $app ] = array(
					'name'		=> (string) $name,
					'disabled'	=> ( !empty( $errors ) or ( (string) $app === 'core' or (string) $app === 'cloud' ) ),
					'errors'	=> $errors,
				);
			}
		}
		
		/* Bring core app to top */
		$system['core'] = $apps['core'];
		unset( $apps['core'] );
		if ( isset( $apps['cloud'] ) )
		{
			$system['cloud'] = $apps['cloud'];
			unset( $apps['cloud'] );
		}
		$apps = array_merge( $system, $apps );
		
		$form->add( new \IPS\Helpers\Form\Custom( 'apps', $defaultTicks, TRUE, array(
			'getHtml'	=> function( $element ) use ( $apps )
			{
				return \IPS\Theme::i()->getTemplate( 'forms' )->apps( $apps, $element->value );
			},
			'validate'	=> function( $element ) use ( $defaultTicks )
			{
				if ( !\in_array( 'core', $element->value ) )
				{
					$element->value['core'] = TRUE;
				}
				$uninstallable = array_diff( array_keys( $element->value ), $defaultTicks );
				if ( !empty( $uninstallable ) )
				{
					throw new \DomainException;
				}
			}
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Custom( 'default_app', 'forums', TRUE, array(
			'getHtml'	=> function( $element ) use ( $apps )
			{
				$options = array_combine( array_keys( $apps ), array_map( function( $value ){ return $value['name']; }, $apps ) );
				/* But put system app at end of list to ensure it's not selected by default over something else */
				unset( $options['core'] );
				unset( $options['cloud'] );
				$options['core'] = $apps['core']['name'];
				return \IPS\Theme::i()->getTemplate( 'forms' )->select( 'default_app', $element->value, TRUE, $options );
			},
			'validate'	=> function( $element ) use ( $apps )
			{
				if ( !\in_array( $element->value, array_keys( $apps ) ) or !array_key_exists( $element->value, \IPS\Request::i()->apps ) )
				{
					throw new \DomainException('default_app_invalid');
				}
			}
		) ) );
		
		if ( $values = $form->values() )
		{
			if ( !\in_array( $values['default_app'], array_keys( $values['apps'] ) ) )
			{
				throw new \DomainException('default_app_invalid');
			}
			
			$INFO['apps'] = array_keys( $values['apps'] );
			$INFO['default_app'] = $values['default_app'];
			
			$toWrite = "<?php\n\n" . '$INFO = ' . var_export( $INFO, TRUE ) . ';';
			
			try
			{
				if ( \file_put_contents( \IPS\ROOT_PATH . '/conf_global.php', $toWrite ) )
				{
					/* PHP 5.5 - clear opcode cache or details won't be seen on next page load */
					if ( \function_exists( 'opcache_invalidate' ) )
					{
						@opcache_invalidate( \IPS\ROOT_PATH . '/conf_global.php' );
					}
					
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'controller=serverdetails' ) );
				}
			}
			catch( \Exception $ex )
			{
				$errorform = new \IPS\Helpers\Form( 'applications', 'continue' );
				$errorform->add( new \IPS\Helpers\Form\TextArea( 'conf_global_error', $toWrite, FALSE ) );
				$errorform->class = 'ipsForm_vertical';
				
				foreach( $values as $k => $v )
				{
					$errorform->hiddenValues[ $k ] = $v;
				}
				
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->confWriteError( $errorform, \IPS\ROOT_PATH );
				return;
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('applications');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->block( 'applications', $form );
	}
}
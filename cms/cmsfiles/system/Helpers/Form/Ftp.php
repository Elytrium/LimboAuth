<?php
/**
 * @brief		FTP Details input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Apr 2014
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * FTP input class for Form Builder
 */
class _Ftp extends \IPS\Helpers\Form\FormAbstract
{	
	/**
	 * @brief	Default Options
	 * @code
	 		'validate'				=> TRUE,		// Should details be validated?
	 		'allowBypassValidation'	=> TRUE,		// If TRUE, the user will be allowed to use value even if the validation fails
	 		'rejectUnsupportedSftp'	=> FALSE,		// If SFTP deatils are provided, but the server doesn't support it, should validation fail?
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'validate'				=> TRUE,
		'allowBypassValidation'	=> FALSE,
		'rejectUnsupportedSftp'	=> FALSE,
	);
		
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_forms.js', 'nexus', 'global' ) );
		
		$value = \is_array( $this->value ) ? $this->value : json_decode( \IPS\Text\Encrypt::fromTag( $this->value )->decrypt(), TRUE );
		$defaultValue = \is_array( $this->defaultValue ) ? $this->defaultValue : json_decode( \IPS\Text\Encrypt::fromTag( $this->defaultValue )->decrypt(), TRUE );
		if ( isset( $value['pw'] ) and isset( $defaultValue['pw'] ) and $value['pw'] and $value['pw'] === $defaultValue['pw'] and !$this->error )
		{
			$value['pw'] = '********';
		}
		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->ftp( $this->name, $value, $this->options['allowBypassValidation'] and $this->error );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$value = parent::getValue();
		
		if ( isset( $value['pw'] ) and $value['pw'] === '********' )
		{
			$defaultValue = \is_array( $this->defaultValue ) ? $this->defaultValue : json_decode( \IPS\Text\Encrypt::fromTag( $this->defaultValue )->decrypt(), TRUE );
			$value['pw'] = $defaultValue['pw'];
		}
		
		return $value;
	}
	
	/** 
	 * Validate
	 *
	 * @param	array	$value	The value
	 * @return	\IPS\Ftp
	 */
	public static function connectFromValue( $value )
	{
		if ( $value['protocol'] == 'sftp' )
		{
			$ftp = new \IPS\Ftp\Sftp( $value['server'], $value['un'], $value['pw'], $value['port'] );
		}
		else
		{
			$ftp = new \IPS\Ftp( $value['server'], $value['un'], $value['pw'], $value['port'], ( $value['protocol'] == 'ssl_ftp' ), 3 );
		}
		
		$ftp->chdir( $value['path'] );
		
		return $ftp;
	}
	
	/** 
	 * Validate
	 *
	 * @return	bool
	 */
	public function validate()
	{
		/* Do we have a value? */
		if ( $this->value['server'] or $this->value['un'] or $this->value['pw'] )
		{
			/* And is it different to what it was originally, or do we need to establish the connection for custom validation? */
			$defaultValue = \is_array( $this->defaultValue ) ? $this->defaultValue : json_decode( \IPS\Text\Encrypt::fromTag( $this->defaultValue )->decrypt(), TRUE );
			if ( !isset( $defaultValue['protocol'] ) or $defaultValue['protocol'] != $this->value['protocol'] or $defaultValue['server'] != $this->value['server'] or $defaultValue['port'] != $this->value['port'] or $defaultValue['un'] != $this->value['un'] or $defaultValue['pw'] != $this->value['pw'] or $defaultValue['path'] != $this->value['path'] or $this->customValidationCode !== NULL )
			{
				/* And are we supposed to be validating? */
				if ( $this->options['validate'] and ( !$this->options['allowBypassValidation'] or !isset( $this->value['bypassValidation'] ) ) )
				{
					/* Do normal validation */
					try
					{
						$ftp = static::connectFromValue( $this->value );
					}
					catch ( \IPS\Ftp\Exception $e )
					{
						throw new \DomainException( 'ftp_err-' . $e->getMessage() );
					}
					catch ( \BadMethodCallException $e )
					{
						// This means we tried an SFTP connection, but the server doesn't support it. We'll have to assume it's correct unless we've specifically set not to
						if ( $this->options['rejectUnsupportedSftp'] )
						{
							throw new \DomainException( 'ftp_err_no_sftp' );
						}
					}
				}
			}
			
			/* Do any custom validation */
			if( $this->customValidationCode !== NULL )
			{
				$validationFunction = $this->customValidationCode;
				$validationFunction( $ftp );
			}
		}
		/* If not, should we? */
		elseif ( $this->required )
		{
			throw new \DomainException( 'form_required' );
		}

		return true;
	}
	
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		return \IPS\Text\Encrypt::fromPlaintext( json_encode( $value ) )->tag();
	}
}
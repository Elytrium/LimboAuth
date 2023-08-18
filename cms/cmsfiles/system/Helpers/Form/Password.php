<?php
/**
 * @brief		Password input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Password input class for Form Builder
 */
class _Password extends Text
{
	/**
	 * @brief	Default Options
	 * @code
	 	$childDefaultOptions = array(
		 	'protect'			=> FALSE,					// If true, will return value as an object that can be cast to a string rather than the string itself. This should be used for user passwords to avoid them showing in logs.
	 		'validateFor'		=> \IPS\Member::loggedIn(),	// If an \IPS\Member object is provided, the password will be checked if it is valid for that account. Default is NULL. Note that it is possible a user may not have any available password-based login handler, so this should only be used when *changing* a password.
	 		'confirm'			=> 'password1',				// If the name of another element in the form is provided, will check is the values match. Default is NULL.
	 		'showMeter'			=> FALSE,					// Show a "strength" meter
	 		'minimumStrength 	=> 3,						// If a strength is provided validation will fail for any lesser value
	 		'checkStrength'		=> FALSE,					// Whether or not to verify the password strength
			'strengthMember'	=> NULL,					// If an \IPS\Member object is provided and minimumStrength is set, the password will be checked against the member's email address and username to ensure it does not match
			'strengthRequest'	=> array()					// If minimumStrength is set, request keys can be supplied in this array and the password will be checked against the request to ensure it does not match any of the values
	 	);
	 * @endcode
	 */
	public $childDefaultOptions = array(
		'protect'			=> FALSE,
		'validateFor'		=> NULL,
		'confirm'			=> NULL,
		'showMeter'			=> FALSE,
		'minimumStrength'	=> NULL,
		'checkStrength'		=> FALSE,
		'strengthMember'	=> NULL,
		'strengthRequest'	=> array(),
		'htmlAutocomplete'	=> "current-password",
		'enforceMaxLimit'	=> TRUE,
	);
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		if ( $this->options['protect'] )
		{
			return \IPS\Request::i()->protect( $this->name );
		}
		
		$value = parent::getValue();	
		if ( $value === '********' and $this->defaultValue )
		{
			$value = $this->defaultValue;
		}
		
		return $value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();
		
		/* Password length */
		if ( mb_strlen( $this->value ) < 3 AND ( $this->required OR (string) $this->value ) )
		{
			throw new \InvalidArgumentException( 'err_password_length' );
		}

		if ( $this->options['enforceMaxLimit'] and mb_strlen( $this->value ) > 72 )
		{
			throw new \InvalidArgumentException( 'err_password_toolong' );
		}

		/* Does the password meet the minimum required strength? */
		if ( $this->options['checkStrength'] === TRUE )
		{
			$this->options['minimumStrength'] = ( $this->options['minimumStrength'] ) ?: \IPS\Settings::i()->password_strength_option;

			require_once \IPS\ROOT_PATH . "/system/3rd_party/phpass/phpass.php";
			$phpass = new \PasswordStrength();

			$score		= NULL;

			if( $this->options['strengthMember'] AND $this->options['strengthMember'] instanceof \IPS\Member )
			{
				if( (string) $this->value == $this->options['strengthMember']->name OR (string) $this->value == $this->options['strengthMember']->email )
				{
					$score		= $phpass::STRENGTH_VERY_WEAK;
				}
			}
			
			if( \count( $this->options['strengthRequest'] ) )
			{
				foreach( $this->options['strengthRequest'] as $requestKey )
				{
					if( isset( \IPS\Request::i()->$requestKey ) AND (string) $this->value == \IPS\Request::i()->$requestKey )
					{
						$score		= $phpass::STRENGTH_VERY_WEAK;
					}
				}
			}

			if( $score === NULL AND $this->options['showMeter'] and \IPS\Settings::i()->password_strength_meter_enforce )
			{
				$score = $phpass->classify( (string) $this->value );
			}

			if ( $this->options['showMeter'] and \IPS\Settings::i()->password_strength_meter_enforce and $score < $this->options['minimumStrength'] )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('err_password_strength', FALSE, array( 'sprintf' => \IPS\Member::loggedIn()->language()->addToStack( 'strength_' . \IPS\Settings::i()->password_strength_option ) ) ) );
			}
		}

		/* Is valid for member? */
		if ( $this->options['validateFor'] !== NULL )
		{
			$valid = FALSE;
			
			$login = new \IPS\Login();
			foreach ( $login->usernamePasswordMethods() as $method )
			{
				if ( $method->authenticatePasswordForMember( $this->options['validateFor'], $this->value ) )
				{
					$valid = TRUE;
					break;
				}
			}
			
			if ( !$valid )
			{
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack( 'login_err_bad_password', FALSE, array( 'pluralize' => array( $method->authType() ) ) ) );
			}
		}
		
		/* Matches the other one? */
		if ( $this->options['confirm'] !== NULL )
		{
			$confirmKey = $this->options['confirm'];
			if ( (string) $this->value !== (string) \IPS\Request::i()->$confirmKey )
			{
				throw new \InvalidArgumentException( 'form_password_confirm' );
			}
		}
	}

	/**
	 * Get HTML
	 *
	 * @return	string
	 * @note	We cannot pass the regex to the HTML5 'pattern' attribute for two reasons:
	 *	@li	PCRE and ECMAScript regex are not 100% compatible (though the instances this present a problem are admittedly rare)
	 *	@li	You cannot specify modifiers with the pattern attribute, which we need to support on the PHP side
	 */
	public function html()
	{
		/* 10/19/15 - adding htmlspecialchars around value if autocomplete is enabled so that html tag characters can be used (e.g. for members) */
		/* This value is decoded by the JS widget before use. */
		if( $this->options['autocomplete'] and !empty( $this->value ) and \is_array( $this->value ) )
		{
			foreach( $this->value as $key => $value )
			{
				$this->value[ $key ] = htmlspecialchars( $value, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			}
		}
		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( $this->name, $this->formType, ( $this->value and $this->value === $this->defaultValue and !$this->error ) ? '********' : $this->value, $this->required, $this->options['maxLength'], $this->options['size'], $this->options['disabled'], $this->options['autocomplete'], $this->options['placeholder'], NULL, $this->options['nullLang'], $this->htmlId, $this->options['showMeter'], $this->options['htmlAutocomplete'], $this->options['strengthMember'], $this->options['strengthRequest'] );
	}
}
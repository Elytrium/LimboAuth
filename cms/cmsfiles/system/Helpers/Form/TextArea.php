<?php
/**
 * @brief		Text input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Text input class for Form Builder
 */
class _TextArea extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'minLength'		=> 1,						// Minimum number of characters. NULL is no minimum. Default is NULL.
	 		'maxLength'		=> 255,						// Maximum number of characters. NULL is no maximum. Default is NULL.
	 		'disabled'		=> FALSE,					// Disables input. Default is FALSE.
	 		'placeholder'	=> 'e.g. ...',				// A placeholder (NB: Will only work on compatible browsers)
	 		'nullLang'		=> 'no_value',				// If provided, an "or X" checkbox will appear with X being the value of this language key. When checked, NULL will be returned as the value.
	 		'tags'			=> array(),					// An array of extra insertable tags in key => value pair with key being what is inserted and value serving as a description
	 		'class'			=> 'ipsField_codeInput',	// Additional CSS class
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'minLength'		=> NULL,
		'maxLength'		=> NULL,
		'disabled'		=> FALSE,
		'placeholder'	=> NULL,
		'nullLang'		=> NULL,
		'tags'			=> array(),
		'rows'			=> NULL,
		'class'			=> '',
	);

	/**
	 * Constructor
	 *
	 * @param	string			$name					Name
	 * @param	mixed			$defaultValue			Default value
	 * @param	bool|NULL		$required				Required? (NULL for not required, but appears to be so)
	 * @param	array			$options				Type-specific options
	 * @param	callback		$customValidationCode	Custom validation code
	 * @param	string			$prefix					HTML to show before input field
	 * @param	string			$suffix					HTML to show after input field
	 * @param	string			$id						The ID to add to the row
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array(), $customValidationCode=NULL, $prefix=NULL, $suffix=NULL, $id=NULL )
	{
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
	
		/* If we have a minimum length, the field is required */
		if( $this->options['minLength'] >= 1 )
		{
			$this->required = TRUE;
		}
		elseif( !$this->options['minLength'] and $this->required )
		{
			$this->options['minLength'] = 1;
		}

		/* Append needed javascript if appropriate */
		if( !empty( $this->options['tags'] ) )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.rangyinputs.js', 'core', 'interface' ) );
		}
	}
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->textarea( $this->name, $this->value, $this->required, $this->options['maxLength'], $this->options['disabled'], $this->options['class'], $this->options['placeholder'], $this->options['nullLang'], $this->options['tags'], $this->options['rows'] );
	}
	
	/**
	 * Get value
	 *
	 * @return	string|null
	 */
	public function getValue()
	{
		$nullName = "{$this->name}_null";
		if ( $this->options['nullLang'] !== NULL and isset( \IPS\Request::i()->$nullName ) )
		{
			return NULL;
		}
		
		return parent::getValue();
	}
	
	/**
	 * Validate
	 *
	 * @throws	\LengthException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();
		
		/* Tags are stored as an array so we can't do things like mb_strlen() against them */
		if( \is_array( $this->value ) )
		{
			return TRUE;
		}

		if( $this->options['minLength'] !== NULL and mb_strlen( $this->value ) < $this->options['minLength'] )
		{
			throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack( 'form_minlength', FALSE, array( 'pluralize' => array( $this->options['minLength'] ) ) ) );
		}
		if( $this->options['maxLength'] !== NULL and mb_strlen( $this->value ) > $this->options['maxLength'] )
		{
			throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack( 'form_maxlength', FALSE, array( 'pluralize' => array( $this->options['maxLength'] ) ) ) );
		}
		
		return TRUE;
	}
}
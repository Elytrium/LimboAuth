<?php
/**
 * @brief		Key/Value input class for Form Builder
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
 * Key/Value input class for Form Builder
 */
class _KeyValue extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @see		\IPS\Helpers\Form\Date::$defaultOptions
	 * @code
	 	$defaultOptions = array(
	 		'start'			=> array( ... ),
	 		'end'			=> array( ... ),
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'key'		=> array(
			'minLength'			=> NULL,
			'maxLength'			=> NULL,
			'size'				=> 20,
			'disabled'			=> FALSE,
			'autocomplete'		=> NULL,
			'placeholder'		=> NULL,
			'regex'				=> NULL,
			'nullLang'			=> NULL,
			'accountUsername'	=> FALSE,
			'trim'				=> TRUE,
		),
		'value'		=> array(
			'minLength'			=> NULL,
			'maxLength'			=> NULL,
			'size'				=> NULL,
			'disabled'			=> FALSE,
			'autocomplete'		=> NULL,
			'placeholder'		=> NULL,
			'regex'				=> NULL,
			'nullLang'			=> NULL,
			'accountUsername'	=> FALSE,
			'trim'				=> TRUE,
		),
	);

	/**
	 * @brief	Key Object
	 */
	public $keyField = NULL;
	
	/**
	 * @brief	Value Object
	 */
	public $valueField = NULL;
	
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
		$options = array_merge( $this->defaultOptions, $options );
		
		$this->keyField = new \IPS\Helpers\Form\Text( "{$name}[key]", isset( $defaultValue['key'] ) ? $defaultValue['key'] : NULL, FALSE, isset( $options['key'] ) ? $options['key'] : array() );
		$this->valueField = new \IPS\Helpers\Form\Text( "{$name}[value]", isset( $defaultValue['value'] ) ? $defaultValue['value'] : NULL, FALSE, isset( $options['value'] ) ? $options['value'] : array() );
		
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
	}
	
	/**
	 * Format Value
	 *
	 * @return	array
	 */
	public function formatValue()
	{
		return array(
			'key'	=> $this->keyField->formatValue(),
			'value'	=> $this->valueField->formatValue()
		);
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->keyValue( $this->keyField->html(), $this->valueField->html() );
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\LengthException
	 * @return	TRUE
	 */
	public function validate()
	{
		$this->keyField->validate();
		$this->valueField->validate();
		
		if( $this->customValidationCode !== NULL )
		{
			$validationFunction = $this->customValidationCode;
			$validationFunction( $this->value );
		}
	}
}
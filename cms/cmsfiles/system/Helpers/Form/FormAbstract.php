<?php
/**
 * @brief		Abstract Class for input types for Form Builder
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
 * Abstract Class for input types for Form Builder
 */
abstract class _FormAbstract
{
	/**
	 * @brief	Name
	 */
	protected $_name = '';
	
	/**
	 * @brief	Label
	 */
	public $label = NULL;
	
	/**
	 * @brief	Description
	 */
	public $description = NULL;
	
	/**
	 * @brief	Default Value
	 */
	public $defaultValue = NULL;
	
	/**
	 * @brief	Value
	 */
	public $value = NULL;
	
	/**
	 * @brief	Unformatted Value
	 */
	public $unformatted = NULL;
	
	/**
	 * @brief	Required?
	 */
	public $required = FALSE;
	
	/**
	 * @brief	Appears Required?
	 */
	public $appearRequired = FALSE;

	/**
	 * @brief 	Additional CSS classnames to use on the row
	 */
	public $rowClasses = array();
	
	/**
	 * @brief	Type-Specific Options
	 */
	public $options = array();
	
	/**
	 * @brief	Default Options
	 */
	protected $defaultOptions = array(
		'disabled'	=> FALSE,
	);
	
	/**
	 * @brief	Custom Validation Code
	 */
	protected $customValidationCode;
	
	/**
	 * @brief	Prefix (HTML that displays before the input box)
	 */
	public $prefix;
	
	/**
	 * @brief	Suffix (HTML that displays after the input box)
	 */
	public $suffix;
	
	/**
	 * @brief	HTML ID
	 */
	public $htmlId = NULL;
	
	/**
	 * @brief	Validation Error
	 */
	public $error = NULL;
	
	/**
	 * @brief	Reload form flag (Can be used by JS disabled fall backs to alter form content on submit)
	 */
	public $reloadForm = FALSE;
	
	/**
	 * @brief	Warning
	 */
	public $warningBox = NULL;
	
	/**
	 * @brief	Value has been set?
	 */
	public $valueSet = FALSE;

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
		$this->_name				= $name;
		$this->required				= \is_null( $required ) ? FALSE : $required;
		$this->appearRequired		= \is_null( $required ) ? TRUE : $required;
		$this->options				= array_merge( $this->defaultOptions, $options );
		$this->customValidationCode	= $customValidationCode;
		$this->prefix				= $prefix;
		$this->suffix				= $suffix;
		$this->defaultValue			= $defaultValue;
		$this->htmlId				= $id ? preg_replace( "/[^a-zA-Z0-9\-_]/", "_", $id ) : NULL;
		
		$this->setValue( TRUE );
	}

	/**
	 * Set the value of the element
	 *
	 * @param	bool	$initial	Whether this is the initial call or not. Do not reset default values on subsequent calls.
	 * @param	bool	$force		Set the value even if one was not submitted (done on the final validation when getting values)?
	 * @return	void
	 */
	public function setValue( $initial=FALSE, $force=FALSE )
	{
		$name			= $this->name;
		$unlimitedKey	= "{$name}_unlimited";
		$nullKey        = "{$name}_null";
		
		if( $force or ( mb_substr( $name, 0, 8 ) !== '_new_[x]' and ( mb_strpos( $name, '[' ) ? \IPS\Request::i()->valueFromArray( $name ) !== NULL : ( isset( \IPS\Request::i()->$name ) OR isset( \IPS\Request::i()->$unlimitedKey ) OR isset( \IPS\Request::i()->$nullKey ) ) ) ) )
		{
			try
			{
				$this->value = $this->getValue();
				$this->unformatted = $this->value;
				$this->value = $this->formatValue();
				$this->validate();
				$this->valueSet = TRUE;
			}
			catch ( \LogicException $e )
			{
				$this->valueSet = TRUE;
				$this->error = $e->getMessage();
			}
		}
		else
		{
			if( $initial )
			{
				$this->value = $this->defaultValue;
				try
				{
					$this->value = $this->formatValue();
				}
				catch ( \LogicException $e )
				{
					$this->error = $e->getMessage();
				}
			}
		}
	}

	/**
	 * Magic get method
	 *
	 * @param	string	$property	Property requested
	 * @return	mixed
	 */
	public function __get( $property )
	{
		if( $property === 'name' )
		{
			return $this->_name;
		}
		
		return NULL;
	}

	/**
	 * Magic set method
	 *
	 * @param	string	$property	Property requested
	 * @param	mixed	$value		Value to set
	 * @return	void
	 * @note	We are operating on the 'name' property so that if an element's name is reset after the element is initialized we can reinitialize the value
	 */
	public function __set( $property, $value )
	{
		if( $property === 'name' )
		{
			$this->_name	= $value;
			$this->setValue();
		}
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->rowHtml();
	}
	
	/**
	 * Get HTML
	 *
	 * @param	\IPS\Helpers\Form|null	$form	Form helper object
	 * @return	string
	 */
	public function rowHtml( $form=NULL )
	{
		try
		{
			if ( $this->label )
			{
				$label = $this->label;
			}
			else
			{
				$label = $this->name;
				if ( isset( $this->options['labelSprintf'] ) )
				{
					$label = \IPS\Member::loggedIn()->language()->addToStack( $label, FALSE, array( 'sprintf' => $this->options['labelSprintf'] ) );
				}
				else if ( isset( $this->options['labelHtmlSprintf'] ) )
				{
					$label = \IPS\Member::loggedIn()->language()->addToStack( $label, FALSE, array( 'htmlsprintf' => $this->options['labelHtmlSprintf'] ) );
				}
				else
				{
					$label = \IPS\Member::loggedIn()->language()->addToStack( $label );
				}
			}
			
			$html = $this->html();
			
			if ( $this->description )
			{
				$desc = $this->description;
			}
			else
			{
				$desc = $this->name . '_desc';
				$desc = \IPS\Member::loggedIn()->language()->addToStack( $desc, FALSE, array( 'returnBlank' => TRUE, 'returnInto' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->rowDesc( $label, $html, $this->appearRequired, $this->error, $this->prefix, $this->suffix, $this->htmlId ?: ( $form ? "{$form->id}_{$this->name}" : NULL ), $this, $form ) ) );
			}

			if ( $this->warningBox )
			{
				$warning = $this->warningBox;
			}
			else
			{
				$warning = $this->name . '_warning';
				$warning = \IPS\Member::loggedIn()->language()->addToStack( $warning, FALSE, array( 'returnBlank' => TRUE, 'returnInto' => \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->rowWarning( $label, $html, $this->appearRequired, $this->error, $this->prefix, $this->suffix, $this->htmlId ?: ( $form ? "{$form->id}_{$this->name}" : NULL ), $this, $form ) ) );
			}
			
			if( array_key_exists( 'endSuffix', $this->options ) )
			{ 
				$this->suffix	= $this->options['endSuffix'];
			}

			/* Some elements support an array for suffix, such as Number which supports preUnlimited and postUnlimited. We need to wipe out
				the suffix here before calling the row() template, however, which only supports a string and throws an Array to string conversion error.
				By this point, the element template has already ran and used the suffix if designed to */
			if( \is_array( $this->suffix ) )
			{
				$this->suffix = '';
			}

			return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->row( $label, $html, $desc, $warning, $this->appearRequired, $this->error, $this->prefix, $this->suffix, $this->htmlId ?: ( $form ? "{$form->id}_{$this->name}" : NULL ), $this, $form, $this->rowClasses );
		}
		catch ( \Exception $e )
		{
			if ( \IPS\IN_DEV )
			{
				echo '<pre>';
				var_dump( $e );
				exit;
			}
			
			throw $e;
		}
	}

	/**
	 * Get the value to use in the label 'for' attribute
	 *
	 * @return	mixed
	 */
	public function getLabelForAttribute()
	{
		return $this->htmlId ?? $this->name;
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$name	= $this->name;
		$value	= ( mb_strpos( $name, '[' ) OR ( isset( $this->options['multiple'] ) AND $this->options['multiple'] === TRUE ) ) ? \IPS\Request::i()->valueFromArray( $name ) : \IPS\Request::i()->$name;

		if( isset( $this->options['disabled'] ) AND $this->options['disabled'] === TRUE AND $value === NULL )
		{
			$value = $this->defaultValue;
		}

		return $value;
	}
	
	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		return $this->value;
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if( ( $this->value === '' OR ( \is_array( $this->value ) AND empty( $this->value ) ) ) and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}
		
		if ( \IPS\Settings::i()->getFromConfGlobal('sql_utf8mb4') !== TRUE )
		{
			if ( !static::utf8mb4Check( $this->value ) )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->isAdmin() ? ( \IPS\CIC ? 'form_multibyte_unicode_admin_cic' : 'form_multibyte_unicode_admin' ) : 'form_multibyte_unicode' );
			}
		}
		
		if( $this->customValidationCode !== NULL )
		{
			$validationFunction = $this->customValidationCode;
			$validationFunction( $this->value );
		}
		
		return TRUE;
	}
		
	/**
	 * Check if a value is okay to be stored in a non-utf8mb4 database
	 *
	 * @param	mixed	$value	The value
	 * @return	bool
	 */
	public static function utf8mb4Check( $value )
	{
		if ( \is_array( $value ) )
		{
			foreach ( $value as $_value )
			{
				if ( !static::utf8mb4Check( $_value ) )
				{
					return FALSE;
				}
			}
		}
		elseif ( \is_string( $value ) )
		{
			return (bool) !preg_match( '/[\x{10000}-\x{10FFFF}]/u', $value );
		}
		return TRUE;
	}
		
	/**
	 * String Value
	 *
	 * @param	mixed	$value	The value
	 * @return	string
	 */
	public static function stringValue( $value )
	{
		if ( \is_array( $value ) )
		{
			return implode( ',', array_map( function( $v )
			{
				if ( \is_object( $v ) )
				{
					return (string) $v;
				}
				return $v;
			}, $value ) );
		}
		
		return (string) $value;
	}
}
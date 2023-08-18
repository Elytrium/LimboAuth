<?php
/**
 * @brief		Select-box class for Form Builder
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
 * Select-box class for Form Builder
 */
class _Select extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'options'			=> array( 'key' => 'val', 'key' => 'val', ... )	// Options for select box
	 		'toggles'			=> array( 'key' => array( ... ) )	// IDs that each option should show/hide when toggled
	 		'multiple'			=> TRUE		// Sets the select box to allow multiple values to be selected. Default is FALSE.
	 		'class'				=> '',		// CSS class
	 		'disabled'			=> FALSE,	// Disables input. Default is FALSE.
	 		'parse'				=> 'lang',	// Sets how the values for options should be parsed. Acceptable values are "lang" (language keys), "normal" (htmlentities), "raw" (no parsing) or "image" (image URLs, only for Radio fields).  Default is "lang".
	 		'gridspan'			=> 3,		// If 'parse' is set to 'image', this controls the gridspan to use when creating the option layout
	 		'unlimited'			=> -1,			// If any value other than NULL is provided, an "Unlimited" checkbox will be displayed. If checked, the value specified will be sent.
	 		'unlimitedLang'		=> 'unlimited',	// Language string to use for unlimited checkbox label
	 		'unlimitedToggles'	=> array(...),	// Names of other input fields that should show/hide when the "Unlimited" checkbox is toggled.
	 		'unlimitedToggleOn'	=> TRUE,		// Whether the toggles should show on unlimited checked (TRUE) or unchecked(FALSE). Default is TRUE
	 		'userSuppliedInput'	=> ''		// If this option is selected (it must be a valid option passed to 'options'), a text input field will display, allowing the user to enter their own value
	 		'noDefault'			=> TRUE		// For radios, you can set this to TRUE if you do not want any radio option selected by default (useful for things like polls)
	 		'returnLabels'		=> FALSE	// If TRUE, will return the labels rather than the keys as the value
	 		'sort'				=> FALSE,	// If TRUE, options will be sorted by JavaScript. Useful where the options are language-dependant but you still want them to be in alphabetical order
	 		'impliedUnlimited'	=> FALSE,	// For checkbox sets, if 'unlimited' is set and this is FALSE a fancy toggler interface is presented. If TRUE, a checkbox set will be presented and the unlimited value will be implied if all options are checked.
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'options'			=> array(),
		'toggles'			=> array(),
		'multiple'			=> FALSE,
		'class'				=> '',
		'disabled'			=> FALSE,
		'parse'				=> 'lang',
		'gridspan'			=> 3,
		'unlimited'			=> NULL,
		'unlimitedLang'		=> 'all',
		'unlimitedToggles'	=> array(),
		'unlimitedToggleOn'	=> TRUE,
		'userSuppliedInput'	=> '',
		'noDefault'			=> FALSE,
		'returnLabels'		=> FALSE,
		'sort'				=> FALSE,
		'impliedUnlimited'	=> FALSE,
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
		/* Set the default value to the first option if one isn't provided */
		if ( $defaultValue === NULL and ( !isset( $options['noDefault'] ) OR !$options['noDefault'] ) and \is_array( $options['options'] ) )
		{
			foreach ( $options['options'] as $k => $v )
			{
				if ( \is_array( $v ) )
				{
					foreach ( $v as $_k => $_v )
					{
						if ( !isset( $options['disabled'] ) OR ( \is_array( $options['disabled'] ) AND !\in_array( $_k, $options['disabled'] ) ) )
						{
							$defaultValue = $_k;
							break;
						}
					}
				}
				else
				{
					if ( !isset( $options['disabled'] ) OR ( \is_array( $options['disabled'] ) AND !\in_array( $k, $options['disabled'] ) ) )
					{
						$defaultValue = $k;
						break;
					}
				}
			}
			
			if ( isset( $options['multiple'] ) and $options['multiple'] === TRUE )
			{
				$defaultValue = array( $defaultValue );
			}
		}
	
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
	}

	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		/* Add [] to the name if this is a multi-select */
		$name = $this->name;
		if ( $this->options['multiple'] )
		{
			$name .= '[]';
		}
				
		/* Translate labels back to keys? */
		if ( $this->options['returnLabels'] and ( $this->options['unlimited'] === NULL or $this->value !== $this->options['unlimited'] ) )
		{
			$value = array();
			if ( \is_array( $this->value ) )
			{
				foreach ( $this->value as $v )
				{
					$value[] = array_search( $v, $this->options['options'] );
				}
			}
			else
			{
				$value = array_search( $this->value, $this->options['options'] );
			}
		}
		else
		{
			$value = $this->value;
		}
		
		return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->select( $name, $value, $this->required, $this->parseOptions(), $this->options['multiple'], $this->options['class'], $this->options['disabled'], $this->options['toggles'], $this->htmlId, $this->options['unlimited'], $this->options['unlimitedLang'], $this->options['unlimitedToggles'], $this->options['unlimitedToggleOn'], $this->options['userSuppliedInput'], $this->options['sort'], $this->options['parse'] );
	}
	
	/**
	 * Parse Values
	 *
	 * @return	array
	 */
	protected function parseOptions()
	{
		$options = $this->options['options'];
		switch ( $this->options['parse'] )
		{
			case 'lang':
				foreach ( $this->options['options'] as $k => $v )
				{
					if ( \is_array( $v ) )
					{
						foreach ( $v as $x => $y )
						{
							$options[ $k ][ $x ] = \IPS\Member::loggedIn()->language()->addToStack( $y );
						}
					}
					else
					{
						$options[ $k ] = \IPS\Member::loggedIn()->language()->addToStack( $v );
					}
				}
				break;
			
			case 'normal':
				foreach ( $this->options['options'] as $k => $v )
				{
					if ( \is_array( $v ) )
					{
						foreach ( $v as $x => $y )
						{
							$options[ $k ][ $x ] = htmlspecialchars( $y, ENT_DISALLOWED, 'UTF-8', FALSE );
						}
					}
					else
					{
						$options[ $k ] = htmlspecialchars( $v, ENT_DISALLOWED, 'UTF-8', FALSE );
					}
				}
				break;
		}
		
		return $options;
	}
	
	/**
	 * Get value
	 *
	 * @return	array
	 */
	public function getValue()
	{
		/* Unlimited? */
		$unlimitedName = "{$this->name}_unlimited";
		if ( $this->options['unlimited'] !== NULL and isset( \IPS\Request::i()->$unlimitedName ) )
		{
			return $this->options['unlimited'];
		}
		
		/* Get value */
		$value = parent::getValue();

		if( isset( $this->options['userSuppliedInput'] ) )
		{
			if( $value == $this->options['userSuppliedInput'] )
			{
				$name	= $this->options['userSuppliedInput'] . '_' . $this->name;
				$value	= mb_strpos( $name, '[' ) ? \IPS\Request::i()->valueFromArray( $name ) : \IPS\Request::i()->$name;
			}
		}

		if ( isset( $value ) AND \is_array( $value ) AND $this->options['multiple'] AND array_search( '__EMPTY', $value ) !== FALSE )
		{
			unset( $value[ array_search( '__EMPTY', $value ) ] );
		}

		if ( $this->options['returnLabels'] )
		{
			if ( \is_array( $value ) )
			{
				$return = array();
				foreach ( $value as $k => $v )
				{
					$return[ $k ] = $this->options['options'][ $v ];
				}
				return $return;
			}
			elseif ( $this->options['unlimited'] === NULL )
			{
				return $this->options['options'][ $value ];
			}
		}
				
		return $value;
	}

	/**
	 * Validate
	 *
	 * @throws	\OutOfRangeException
	 * @return	TRUE
	 */
	public function validate()
	{
		if( $this->options['userSuppliedInput'] )
		{
			return TRUE;
		}
		
		/* If it is not required and there is no value (pagination from table advanced search form then pass validation otherwise it throws an error) */
		if ( $this->value === NULL and ! $this->required )
		{
			return TRUE;
		}

		parent::validate();
		
		$acceptableValues = array();
		if ( $this->options['unlimited'] !== NULL )
		{
			$acceptableValues[] = $this->options['unlimited'];
		}
		foreach ( $this->options['options'] as $k => $v )
		{		
			if ( \is_array( $v ) )
			{
				$acceptableValues = array_merge( $acceptableValues, $this->options['returnLabels']  ? $v : array_keys( $v ) );
			}
			else
			{
				$acceptableValues[] = $this->options['returnLabels'] ? $v : $k;
			}
		}

		$disabledValues = ( \is_array( $this->options['disabled'] ) ) ? $this->options['disabled'] : ( $this->options['disabled'] ? array( $this->options['disabled'] ) : array() );
		$acceptableValues = array_diff( $acceptableValues, $disabledValues );

		$diff = array_diff( ( \is_array( $this->value ) ? $this->value : array( $this->value ) ), $acceptableValues );
		if ( !empty( $diff ) )
		{
			/* Is the entire thing disabled? If so, just return any default value set (if one has been set)*/
			if ( $this->options['disabled'] === TRUE and $this->defaultValue )
			{
				return $this->defaultValue;
			}
			
			throw new \OutOfRangeException( 'form_bad_value' );
		}
		
		if ( $this->options['multiple'] and $this->required and empty( $this->value ) )
		{
			throw new \DomainException( 'form_required' );
		}
		
		return TRUE;
	}
}
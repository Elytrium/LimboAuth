<?php
/**
 * @brief		Enumeration class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Feb 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Enumeration class for Form Builder
 */
class _Enum extends FormAbstract
{
	/**
	 * @brief	Default Options
	 @code
	 array(
		 'threshold'		=> 25, // Number of options before switching to a Multi-Select
	 )
	 @encode
	 */
	protected $defaultOptions = array(
		'threshold'		=> 25
	);
	
	/**
	 * @brief	Form Class
	 */
	protected $class;
	
	/**
	 * @brief	Threshold
	 */
	protected $threshold;
	
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
		$options['multiple'] = TRUE;
		
		$this->threshold = $options['threshold'] ?? $this->defaultOptions['threshold'];
		
		if ( \count( $options['options'] ) >= $this->threshold )
		{
			$this->class = new \IPS\Helpers\Form\Select( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		}
		else
		{
			$this->class = new \IPS\Helpers\Form\CheckboxSet( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		}
		
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
	}
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return $this->class->html();
	}
	
	/**
	 * Get value
	 *
	 * @return	array
	 */
	public function getValue()
	{
		return $this->class->getValue();
	}
	
	/**
	 * Validate
	 *
	 * @throws	\OutOfRangeException
	 * @return	bool
	 */
	public function validate()
	{
		return $this->class->validate();
	}
}
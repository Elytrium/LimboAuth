<?php
/**
 * @brief		Interval input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		3 Aug 2018
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Interval input class for Form Builder
 */
class _Interval extends FormAbstract
{
	/**
	 * @brief	Seconds
	 */
	const SECONDS = 's';

	/**
	 * @brief	Minutes
	 */
	const MINUTES = 'i';

	/**
	 * @brief	Hours
	 */
	const HOURS = 'h';

	/**
	 * @brief	Days
	 */
	const DAYS = 'd';

	/**
	 * @brief	Weeks
	 */
	const WEEKS = 'w';
	
	/**
	 * @brief	A DateInterval object
	 */
	const INTERVAL = 'o';
	
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'valueAs'			=> 's',			// The format that the value will be provided and returned as (see constants) - will only return whole numbers so smaller units won't be available
	 		'min'				=> 0,			// Minimum value in the valueAs format. NULL is no minimum. Default is 0.
	 		'max'				=> 100,			// Maximum number in the valueAs format. NULL is no maximum. Default is NULL.
	 		'unlimited'			=> -1,			// If any value other than NULL is provided, an "Unlimited" checkbox will be displayed. If checked, the value specified will be sent.
	 		'unlimitedLang'		=> 'unlimited',	// Language string to use for unlimited checkbox label
	 		'unlimitedToggles'	=> array(...),	// Names of other input fields that should show/hide when the "Unlimited" checkbox is toggled.
	 		'unlimitedToggleOn'	=> TRUE,		// Whether the toggles should show on unlimited checked (TRUE) or unchecked(FALSE). Default is TRUE
	 		'valueToggles'		=> array(...),	// Names of other input fields that should show/hide if the value is/isn't 0.
	 		'disabled'			=> FALSE,		// Disables input. Default is FALSE.
	 		'endSuffix'			=> '...',		// A suffix to go after the unlimited checkbox
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'valueAs'			=> self::SECONDS,
		'min'				=> 0,
		'max'				=> NULL,
		'unlimited'			=> NULL,
		'unlimitedLang'		=> 'unlimited',
		'unlimitedToggles'	=> array(),
		'unlimitedToggleOn'	=> TRUE,
		'valueToggles'		=> array(),
		'disabled'			=> FALSE,
		'endSuffix'			=> NULL,
	);
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$valueNumber = '';
		$selectedUnit = $this->options['valueAs'];
				
		if ( $this->value !== NULL )
		{
			if ( $this->options['unlimited'] !== NULL and $this->value == $this->options['unlimited'] )
			{
				$valueNumber = $this->value;
			}
			else
			{
				if ( $this->value )
				{
					$valueNumber = static::convertValue( $this->value, $this->options['valueAs'], static::SECONDS );
					$selectedUnit = static::bestUnit( $valueNumber );
				}
				else
				{
					$valueNumber = 0;
				}
			}
		}
		
		$minimum = NULL;
		if ( $this->options['min'] === 0 )
		{
			switch ( $this->options['valueAs'] )
			{
				case static::WEEKS:
					$minimum = 86400 * 7;
					break;
				case static::DAYS:
					$minimum = 86400;
					break;
				case static::HOURS:
					$minimum = 3600;
					break;
				case static::MINUTES:
					$minimum = 60;
					break;
			}
		}
		elseif( $this->options['min'] !== NULL )
		{
			$minimum = static::convertValue( $this->options['min'], $this->options['valueAs'], static::SECONDS );
		}
						
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->interval( $this->name, $valueNumber, $selectedUnit, $this->required, $this->options['unlimited'], $this->options['unlimitedLang'], $this->options['unlimitedToggles'], $this->options['unlimitedToggleOn'], $this->options['valueToggles'], $minimum, $this->options['max'] === NULL ? NULL : static::convertValue( $this->options['max'], $this->options['valueAs'], static::SECONDS ), $this->options['disabled'], $this->suffix );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$rawValue = parent::getValue();
		
		if ( $this->options['unlimited'] !== NULL and isset( $rawValue['unlimited'] ) )
		{
			return $this->options['unlimited'];
		}
		else
		{
			$rawValue['val'] = str_replace( trim( ',' ), '.', $rawValue['val'] );
			if ( !\is_numeric( $rawValue['val'] ) )
			{
				throw new \InvalidArgumentException( 'form_number_bad' );
			}
						
			return static::convertValue( $rawValue['val'], $rawValue['unit'], $this->options['valueAs'] );
		}
	}
	
	/** 
	 * Convert values
	 *
	 * @param	int		$value		The value
	 * @param	string	$fromUnit	The unit that value is in (see constants)
	 * @param	string	$toUnit		The unit to convert to (see constants)
	 * @return	int
	 */
	public static function convertValue( $value, $fromUnit, $toUnit )
	{
		/* Convert from $fromUnit to seconds */
		$value = \floatval( $value );
		switch ( $fromUnit )
		{
			case static::WEEKS:
				$value *= 7;
			case static::DAYS:
				$value *= 24;
			case static::HOURS:
				$value *= 60;
			case static::MINUTES:
				$value *= 60;
		}
						
		/* Covert to $toUnit */
		switch ( $toUnit )
		{
			case static::WEEKS:
				$value /= 7;
			case static::DAYS:
				$value /= 24;
			case static::HOURS:
				$value /= 60;
			case static::MINUTES:
				$value /= 60;
		}
		return $value;
	}
	
	/** 
	 * Get best unit
	 *
	 * @param	int		$value		Number of seconds (modified by reference to the returned unit)
	 * @return	string
	 */
	public static function bestUnit( &$value )
	{
		if ( $value >= 604800 and ( $value % 604800 ) === 0 )
		{
			$value /= 604800;
			return static::WEEKS;
		}
		elseif ( $value >= 86400 and ( $value % 86400 ) === 0 )
		{
			$value /= 86400;
			return static::DAYS;
		}
		elseif ( $value >= 3600 and ( $value % 3600 ) === 0 )
		{
			$value /= 3600;
			return static::HOURS;
		}
		elseif ( $value >= 60 and ( $value % 60 ) === 0 )
		{
			$value /= 60;
			return static::MINUTES;
		}
		else
		{
			return static::SECONDS;
		}
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
								
		if ( $this->options['unlimited'] === NULL or $this->value != $this->options['unlimited'] )
		{	
			if ( $this->value === 0 and $this->required )
			{
				throw new \InvalidArgumentException('form_required');
			}
				
			if ( $this->options['min'] !== NULL and $this->value < $this->options['min'] )
			{
				$minValue = static::convertValue( $this->options['min'], $this->options['valueAs'], static::SECONDS );
				$minUnit = static::bestUnit( $minValue );
				throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack('form_interval_min_' . $minUnit, FALSE, array( 'pluralize' => array( $minValue ) ) ) );
			}
			if ( $this->options['max'] !== NULL and $this->value > $this->options['max'] )
			{
				$maxValue = static::convertValue( $this->options['max'], $this->options['valueAs'], static::SECONDS );
				$maxUnit = static::bestUnit( $maxValue );
				throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack('form_interval_max_' . $maxUnit, FALSE, array( 'pluralize' => array( $maxValue ) ) ) );
			}
		}
	}
}
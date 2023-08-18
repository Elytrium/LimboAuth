<?php
/**
 * @brief		Date input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Date input class for Form Builder
 */
class _Date extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'min'				=> new \IPS\DateTime('1970-01-01'),		// Minimum date. NULL will mean 1901-12-13 20:45:52 (the minimum 32-bit timestamp). Default is null.
	 		'max'				=> new \IPS\DateTime('2038-01-19'),		// Maximum date. NULL will mean 2038-01-19 03:14:07 (the maximum 32-bit timestamp). Default is null.
	 		'disabled'			=> FALSE,								// Disables input. Default is FALSE.
	 		'time'				=> FALSE,								// Also allow time input?
	 		'unlimited'			=> -1,			// If any value other than NULL is provided, an "Unlimited" checkbox will be displayed. If checked, the value specified will be sent.
	 		'unlimitedLang'		=> 'unlimited',	// Language string to use for unlimited checkbox label
	 		'unlimitedToggles'	=> array(...),	// Names of other input fields that should show/hide when the "Unlimited" checkbox is toggled.
	 		'unlimitedToggleOn'	=> TRUE,		// Whether the toggles should show on unlimited TRUE or FALSE. Default is TRUE
	 		'timezone'			=> NULL,		// The timezone (DateTimeZone object) the submitted date/time is in. If NULL is provided, the user's timezone will be used
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'min'				=> NULL,
		'max'				=> NULL,
		'disabled'			=> FALSE,
		'time'				=> FALSE,
		'unlimited'			=> NULL,
		'unlimitedLang'		=> 'indefinite',
		'unlimitedToggles'	=> array(),
		'unlimitedToggleOn'	=> TRUE,
		'timezone'			=> NULL,
	);
	
	/**
	 * Input name for time field
	 */
	protected $timeName = NULL;
	
	/**
	 * Input name for unlimited checkbox
	 */
	protected $unlimitedName = NULL;
	
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
		/* Work out the key for the time input and unlimited checkboxes */
		if ( mb_strpos( $name, '[' ) !== FALSE )
		{
			$this->timeName = preg_replace( '/\[(.+?)\]/', '[$1_time]', $name, 1 );
			$this->unlimitedName = preg_replace( '/\[(.+?)\]/', '[$1_unlimited]', $name, 1 );
		}
		else
		{
			$this->timeName = "{$name}_time";
			$this->unlimitedName = "{$name}_unlimited";
		}
		
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		
		/* Set min/max - hardcoded to 32bit values rather than using PHP_INT_MAX because the MySQL server may be 32-bit even if the web server is 64 */
		if ( !isset( $this->options['min'] ) or $this->options['min']->getTimestamp() < -2147483648 )
		{
			$this->options['min'] = \IPS\DateTime::ts( -2147483648 );
		}
		if ( !isset( $this->options['max'] ) or $this->options['max']->getTimestamp() > 2147483647 )
		{
			$this->options['max'] = \IPS\DateTime::ts( 2147483647 );
		}
	}

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-touchpunch.js', 'core', 'interface' ) );
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->date(
			$this->name,
			$this->value,
			$this->required,
			( $this->options['min'] ? $this->options['min']->format( 'Y-m-d' ) : NULL ),
			( $this->options['max'] ? $this->options['max']->format( 'Y-m-d' ) : NULL ),
			$this->options['disabled'],
			$this->options['time'] ? $this->timeName : NULL,
			$this->options['unlimited'],
			$this->options['unlimitedLang'],
			$this->unlimitedName,
			$this->options['unlimitedToggles'],
			$this->options['unlimitedToggleOn']
		);
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		/* Unlimited? */
		if ( $this->options['unlimited'] !== NULL )
		{
			$unlimitedName = $this->unlimitedName;
			if ( mb_strpos( $unlimitedName, '[' ) === FALSE )
			{
				if ( isset( \IPS\Request::i()->$unlimitedName ) )
				{
					return $this->options['unlimited'];
				}
			}
			elseif ( \IPS\Request::i()->valueFromArray( $unlimitedName ) !== NULL )
			{
				return $this->options['unlimited'];
			}			
		}
		
		/* Get value */
		return parent::getValue();
	}
	
	/**
	 * Format Value
	 *
	 * @return	\IPS\DateTime|null
	 */
	public function formatValue()
	{
		$v = $this->value;
		try
		{
			$timezone = $this->options['timezone'] ?: ( \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL );
		}
		catch ( \Exception $e )
		{
			$timezone = NULL;
		}

		if ( $this->options['unlimited'] !== NULL and $v === $this->options['unlimited'] )
		{
			return $v;
		}
		elseif ( $v )
		{
			if ( \is_int( $v ) or ( $v and \is_string( $v ) and preg_match( '/^[0-9]+$/', $v ) ) )
			{
				return \IPS\DateTime::ts( $v );
			}
			else if( \is_array( $v ) )
			{
				/* When using pagination and date range, data may come as an array of the datetime object, e.g. field['start']['date']=(date)&field['start']['timezone']=(timezone) */
				try
				{
					$timeKey = $this->timeName;
					$time = $this->options['time'] ? ( mb_strpos( $timeKey, '[' ) === FALSE ? \IPS\Request::i()->$timeKey : \IPS\Request::i()->valueFromArray( $timeKey ) ) : '';
					
					return new \IPS\DateTime( static::_convertDateFormat( $v['date'] ) . ' ' . $time, new \DateTimeZone( $v['timezone'] ) );
				}
				catch ( \Exception $e )
				{
					return $v;
				}
			}
			else
			{
				try
				{
					$timeKey = $this->timeName;
					$time = $this->options['time'] ? ( mb_strpos( $timeKey, '[' ) === FALSE ? \IPS\Request::i()->$timeKey : \IPS\Request::i()->valueFromArray( $timeKey ) ) : '';
					
					if( $time )
					{
						return new \IPS\DateTime( static::_convertDateFormat( $v ) . ' ' . $time, $timezone );
					}
					else if( $v instanceof \IPS\DateTime )
					{
						if ( $timezone )
						{
							$v->setTimeZone( $timezone );
						}
						return $v;
					}
					else
					{
						return new \IPS\DateTime( static::_convertDateFormat( $v ), $timezone );
					}
				}
				catch ( \Exception $e )
				{
					return $v;
				}
			}
		}
		return NULL;
	}
	
	/**
	 * Convert date to expected format
	 *
	 * @param	string				$date	User supplied date
	 * @param	\IPS\Member|NULL	$member	The user that supplied it (NULL For currently logged in member)
	 * @return	string
	 */
	public static function _convertDateFormat( $date, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$format = $member->language()->preferredDateFormat();
		
		$count = 0;
		$bits = array();		
		foreach ( array( 'DD', 'MM', 'YY' ) as $k )
		{
			$regex = str_replace( $k, '(.+?)', preg_quote( $format, '/' ) );
			foreach ( array( 'DD', 'MM', 'YY' ) as $j )
			{
				if ( $k != $j )
				{
					$regex = str_replace( $j, '.+?', $regex );
				}
			}
			$_count = 0;
			$bits[ $k ] = preg_replace( "/^{$regex}$/", '$1', trim( $date ), 1, $_count );
			$count += $_count;
		}

		return ( $count == 3 ) ? "{$bits['YY']}-{$bits['MM']}-{$bits['DD']}" : $date;
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
		if( $this->value === NULL and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}

		/* Check it's valid */
		if ( !( $this->value instanceof \IPS\DateTime ) and $this->value !== NULL AND ( $this->options['unlimited'] === NULL OR $this->value !== $this->options['unlimited'] ) )
		{
			throw new \InvalidArgumentException( 'form_date_bad' );
		}
		
		parent::validate();
		
		/* Unlimited is fine */
		if ( $this->options['unlimited'] !== NULL and $this->value === $this->options['unlimited'] )
		{
			return TRUE;
		}
		
		/* Check minimum */

		try
		{
			$timezone = $this->options['timezone'] ?: ( \IPS\Member::loggedIn()->timezone ? new \DateTimeZone( \IPS\Member::loggedIn()->timezone ) : NULL );
		}
		catch ( \Exception $e )
		{
			$timezone = NULL;
		}
		
		if ( $this->value and $this->options['min'] !== NULL and $this->options['min'] > $this->value )
		{
			$string = $this->options['min']->setTimeZone( $timezone )->localeDate( \IPS\Member::loggedIn() );
			if( $this->options['time'] )
			{
				$string .=' ' . $this->options['min']->setTimeZone( $timezone )->localeTime( \IPS\Member::loggedIn() );
			}
			throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack('form_date_min', FALSE, array( 'sprintf' => array( $string ) ) ) );
		}
		
		/* Check maximum */
		if ( $this->value and $this->options['max'] !== NULL and $this->options['max'] < $this->value )
		{
			$string = $this->options['max']->setTimeZone( $timezone )->localeDate( \IPS\Member::loggedIn() );
			if( $this->options['time'] )
			{
				$string .=' ' . $this->options['max']->setTimeZone( $timezone )->localeTime( \IPS\Member::loggedIn() );
			}
			throw new \LengthException( \IPS\Member::loggedIn()->language()->addToStack('form_date_max', FALSE, array( 'sprintf' => array( $string ) ) ) );
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
		return $value instanceof \IPS\DateTime ? $value->getTimestamp() : $value;
	}
}
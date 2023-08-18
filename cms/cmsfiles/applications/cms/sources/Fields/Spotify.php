<?php
/**
 * @brief		Spotify input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		11 Mar 2013
 */

namespace IPS\cms\Fields;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Spotify input class for Form Builder
 */
class _Spotify extends \IPS\Helpers\Form\Text
{
	/**
	 * @brief	Default Options
	 */
	public $childDefaultOptions = array(
		'parameters' => array()
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
		$this->childDefaultOptions['placeholder'] = \IPS\Member::loggedIn()->language()->addToStack('field_placeholder_spotify');
		
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		
		$this->formType = 'text';
	}
	
	/**
	 * Get the display value
	 * 
	 * @param	mixed			$value			Stored value from form
	 * @param	\IPS\cms\Field	$customField	Custom Field Object
	 * @return	string
	 */
	public static function displayValue( $value, $customField )
	{
		if( !$value )
		{
			return '';
		}

		$params = $customField->extra;
		
		if ( ! isset( $params['width'] ) )
		{
			$params['width'] = 300;
		}
		
		if ( ! isset( $params['height'] ) )
		{
			$params['height'] = 80;
		}
		
		return \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->spotify( $value, $params );
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\DomainException
	 * @return	TRUE
	 */
	public function validate()
	{
		parent::validate();
						
		if ( $this->value )
		{
			/* Check the URL is valid */
			if ( !( $this->value instanceof \IPS\Http\Url ) )
			{
				throw new \InvalidArgumentException('form_url_bad');
			}
			
			/* Check its a valid Spotify URL */
			if ( ! mb_stristr( $this->value->data['host'], 'spotify.com' ) )
			{
				throw new \InvalidArgumentException('form_url_bad');
			}
		}
	}
	
	/**
	 * Get Value
	 *
	 * @return	string
	 */
	public function getValue()
	{
		$val = parent::getValue();
		if ( $val and !mb_strpos( $val, '://' ) )
		{
			$val = "http://{$val}";
		}
		
		return $val;
	}
	
	/**
	 * Format Value
	 *
	 * @return	\IPS\Http\Url|string
	 */
	public function formatValue()
	{
		if ( $this->value and !( $this->value instanceof \IPS\Http\Url ) )
		{
			try
			{
				return new \IPS\Http\Url( $this->value );
			}
			catch ( \InvalidArgumentException $e )
			{
				return $this->value;
			}
		}
		
		return $this->value;
	}
}
<?php
/**
 * @brief		Youtube input class for Form Builder
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
 * Youtube input class for Form Builder
 */
class _Youtube extends \IPS\Helpers\Form\Text
{
	/**
	 * @brief	Default Options
	 */
	public $childDefaultOptions = array(
		'parameters'  => array()
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
		$this->childDefaultOptions['placeholder'] = \IPS\Member::loggedIn()->language()->addToStack('field_placeholder_youtube');
		
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
		
		$url = new \IPS\Http\Url( $value );
	
		if ( isset( $url->queryString['v'] ) )
		{
			$url = 'https://www.youtube.com/embed/' . $url->queryString['v'];
		}
		else if ( $url->data['host'] === 'youtu.be' and ! mb_strpos( $url->data['path'], 'embed' ) )
		{
			$url = 'https://www.youtube.com/embed/' . trim( $url->data['path'], '/' );
		}
		else
		{
			$url = $value;
		}
		
		$params = $customField->extra;
		
		if ( ! isset( $params['width'] ) )
		{
			$params['width'] = 640;
		}
		
		if ( ! isset( $params['height'] ) )
		{
			/* Videos on Youtube are in a 16:9 resolution ratio, but we need to give some extra space hence the 30px addition */
			$params['height'] = ( $params['width'] * ( 9 / 16 ) ) + 30;
		}
		
		$url = \IPS\Http\Url::external( $url )->setQueryString( $params );
		
		return \IPS\Theme::i()->getTemplate( 'records', 'cms', 'global' )->youtube( $url, array( 'width' => $params['width'], 'height' => $params['height'] ) );
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
			
			/* Check its a valid Youtube URL */
			if ( ! mb_stristr( $this->value->data['host'], 'youtube.' ) and ! mb_stristr( $this->value->data['host'], 'youtu.be' ) )
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
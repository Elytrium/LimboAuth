<?php
/**
 * @brief		Width/Height input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 Mar 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Width/Height input class for Form Builder
 */
class _WidthHeight extends FormAbstract
{	
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'unlimited'			=> array( 0, 0 ),	// If any value other than NULL is provided, an "Unlimited" checkbox will be displayed. If checked, the values specified will be sent.
	 		'unlimitedLang'		=> 'unlimited',	// Language string to use for unlimited checkbox label
	 		'image'				=> NULL,			// If an \IPS\File object is provided, the image will be shown for resizing rather than a div
	 		'resizableDiv'		=> FALSE,			// If set to false, the resizable div will not be displayed (useful if you expect dimensions to be large)
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'unlimited'			=> NULL,
		'unlimitedLang'		=> 'unlimited',
		'image'				=> NULL,
		'resizableDiv'		=> TRUE,
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
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );
		
		if ( $this->value === NULL )
		{
			if ( $this->options['image'] !== NULL )
			{
				$image = \IPS\Image::create( $this->options['image']->contents() );
				$this->value = array( $image->width, $image->height );
			}
			else
			{
				$this->value = array( 100, 100 );
			}
		}
	}
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->widthheight( $this->name, $this->value[0], $this->value[1], $this->options['unlimited'], $this->options['unlimitedLang'], $this->options['image'] ? $this->options['image'] : NULL, $this->options['resizableDiv'] );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$name = $this->name;
		$value = \IPS\Request::i()->$name;
		if ( $this->options['unlimited'] !== NULL and isset( $value['unlimited'] ) )
		{
			return $this->options['unlimited'];
		}
		return \IPS\Request::i()->$name;
	}
}
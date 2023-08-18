<?php
/**
 * @brief		Custom input class for Form Builder
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
 * Custom input class for Form Builder
 */
class _Custom extends FormAbstract
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'getHtml'		=> function(){...}	// Function to get output
	 		'formatValue'	=> function(){...}	// Function to format value
	 		'validate'		=> function(){...}	// Function to validate
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'getHtml'		=> NULL,
		'formatValue'	=> NULL,
		'validate'		=> NULL,
	);

	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$htmlFunction = $this->options['getHtml'];
		return $htmlFunction( $this );
	}
	
	/**
	 * Get HTML
	 *
	 * @param	\IPS\Helpers\Form|null	$form	Form helper object
	 * @return	string
	 */
	public function rowHtml( $form=NULL )
	{
		if ( isset( $this->options['rowHtml'] ) )
		{
			$htmlFunction = $this->options['rowHtml'];
			return $htmlFunction( $this, $form );
		}
		return parent::rowHtml( $form );
	}
	
	/** 
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		if ( $this->options['formatValue'] !== NULL )
		{
			$formatValueFunction = $this->options['formatValue'];
			return $formatValueFunction( $this );
		}
		else
		{
			return parent::formatValue();
		}
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{		
		parent::validate();
		
		if ( $this->options['validate'] )
		{
			$validationFunction = $this->options['validate'];
			$validationFunction( $this );
		}	
	}
}
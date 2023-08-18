<?php
/**
 * @brief		Codemirror class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Jul 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Codemirror class for Form Builder
 */
class _Codemirror extends TextArea
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'minLength'		=> 1,			// Minimum number of characters. NULL is no minimum. Default is NULL.
	 		'maxLength'		=> 255,			// Maximum number of characters. NULL is no maximum. Default is NULL.
	 		'disabled'		=> FALSE,		// Disables input. Default is FALSE.
	 		'placeholder'	=> 'e.g. ...',	// A placeholder (NB: Will only work on compatible browsers)
	 		'tags'			=> array(),		// An array of extra insertable tags in key => value pair with key being what is inserted and value serving as a description
	 		'mode'			=> 'php'		// Formatting mode. Default is htmlmixed.
	        'height'        => 300      	// Height of code mirror editor
	        'preview'		=> 'http://...'	// A URL where the value can be POSTed (as "value") and will return a preview. Defaults to NULL, which will hide the preview button.
	        'tagLinks'		=> array(),		// An array of links to display next to the headers for tags.
	        'tagSource'		=> \IPS\Http\Url( ... ), // A URL that will fetch tags using AJAX.
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'minLength'		=> NULL,
		'maxLength'		=> NULL,
		'disabled'		=> FALSE,
		'placeholder'	=> NULL,
		'tags'			=> array(),
		'mode'			=> 'htmlmixed',
		'nullLang'		=> NULL,
		'height'        => 300,
		'preview'		=> NULL,
		'tagLinks'		=> array(),
		'tagSource'		=> NULL
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
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );

		/* We don't support this feature */
		$this->options['nullLang']	= NULL;

		/* Append our necessary JS/CSS */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );	
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
	}
	
	/** 
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		if ( $this->options['height'] )
		{
			$this->options['height'] = \is_numeric( $this->options['height'] ) ? $this->options['height'] . 'px' : $this->options['height'];
		}

		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->codemirror( $this->name, $this->value, $this->required, $this->options['maxLength'], $this->options['disabled'], '', $this->options['placeholder'], $this->options['tags'], $this->options['mode'], $this->htmlId ? "{$this->htmlId}-input" : NULL, $this->options['height'], $this->options['preview'], $this->options['tagLinks'], $this->options['tagSource'] );
	}
}
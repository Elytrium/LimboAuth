<?php
/**
 * @brief		Text input class for Form Builder
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
 * Text input class for Form Builder
 */
class _Text extends TextArea
{
	/**
	 * @brief	Default Options
	 * @code
	 	$defaultOptions = array(
	 		'minLength'			=> 1,			// Minimum number of characters. NULL is no minimum. Default is NULL.
	 		'maxLength'			=> 255,			// Maximum number of characters. NULL is no maximum. Default is NULL.
	 		'size'				=> 20,			// Text input size. NULL will use default size. Default is NULL.
	 		'disabled'			=> FALSE,		// Disables input. Default is FALSE.
	 		'autocomplete'		=> array(		// An array of options for autocomplete.
	 			'source'			=> array(),			// An array of values, or a URI which will be passed an 'input' parameter and return a JSON array of autocomplete values,
	 			'freeChoice'		=> TRUE,			// If FALSE, users will only be able to choose from autocomplete values
	 			'maxItems'			=> 5,				// Maximum number of items (if it should be unlimited, do not specify this element)
	 			'minItems'			=> 2,				// Minimum number of items (if it should be unlimited, do not specify this element) - if field is not required, 0 items will be allowed
	 			'unique'			=> TRUE,			// Specifies if the values must be unique
	 			'forceLower'		=> TRUE,			// If TRUE, all values will be converted to lowercase
	 			'minLength'			=> 5,				// The minimum length of each tag (characters) - if not specified, will be unlimited
	 			'maxLength'			=> 10,				// The maximum length of each tag (characters) - if not specified, will be unlimited
	 			'prefix'			=> TRUE,			// If TRUE, user will have option to specify one tag as a prefix
	 			'resultItemTemplate'=> 'core.foo.bar',	// Can be used to specify a custom JavaScript template to use for the result
	 			'minAjaxLength'		=> 3,				// Minimum length of value before sending AJAX lookup call
	 			'disallowedCharacters' => array(), 		// An array of disallowed characters (default < > ' ")
	 			'minimized'			=> TRUE 			// Whether the autocomplete shows a 'choose' link to activate. Existing values or required = true will always override this.
	 			'alphabetical'		=> FALSE,			// Force values to be sorted alphabetically.
	 		),
	 		'placeholder'		=> 'e.g. ...',	// A placeholder (NB: Will only work on compatible browsers)
	 		'regex'				=> '/[A-Z]+/i',	// RegEx of acceptable value
	 		'nullLang'			=> 'no_value',	// If provided, an "or X" checkbox will appear with X being the value of this language key. When checked, NULL will be returned as the value.
	 		'accountUsername'	=> TRUE,		// If TRUE or an \IPS\Member, additional checks will be performed to ensure provided value is acceptable for use as a username. Pass an \IPS\Member object to exclude that member from the duplicate checks
	 		'trim'				=> TRUE,		// If TRUE (which is the default), whitespace will be stripped from the start and end of the value
	 		'bypassProfanity'	=> 0,			// Profanity filter bypass, see BYPASS_PROFANITY_* constants below.
			'htmlAutocomplete'	=> NULL, 		// an HTML autocomplete attribute If Null no autocomplete is set otherwise use a value from https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fe-autocomplete-url
	  );
	 * @endcode
	 */
	protected $defaultOptions = array(
		'minLength'			=> NULL,
		'maxLength'			=> NULL,
		'size'				=> NULL,
		'disabled'			=> FALSE,
		'autocomplete'		=> NULL,
		'placeholder'		=> NULL,
		'regex'				=> NULL,
		'nullLang'			=> NULL,
		'accountUsername'	=> FALSE,
		'trim'				=> TRUE,
		'bypassProfanity'	=> 0,
		'htmlAutocomplete'	=> NULL,
	);
	
	/**
	 * @brief	Child default Options
	 */
	protected $childDefaultOptions = array();
	
	/**
	 * @brief	Form type
	 */
	public $formType = 'text';
	
	/**
	 * @brief	Do not bypass profanity filters
	 */
	const BYPASS_PROFANITY_NONE = 0;
	
	/**
	 * @brief	Perform profanity swaps only
	 */
	const BYPASS_PROFANITY_SWAP = 1;
	
	/**
	 * @brief	Bypass profanity entirely
	 */
	const BYPASS_PROFANITY_ALL = 2;

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
		/* Pull in default options from child class */
		$this->defaultOptions = array_merge( $this->defaultOptions, $this->childDefaultOptions );

		/* Set username regex */
		if ( isset( $options['accountUsername'] ) and $options['accountUsername'] !== FALSE )
		{
			$options['minLength'] = \IPS\Settings::i()->min_user_name_length;
			$options['maxLength'] = \IPS\Settings::i()->max_user_name_length;
		}
		
		/* Call parent constructor */
		parent::__construct( $name, $defaultValue, $required, $options, $customValidationCode, $prefix, $suffix, $id );

		/* Add JS */
		if ( isset( $this->options['autocomplete']['prefix'] ) and $this->options['autocomplete']['prefix'] )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'global_core.js', 'core', 'global' ) );
		}
		
		/* Set the form type */
		$this->formType = mb_strtolower( mb_substr( \get_called_class(), mb_strrpos( \get_called_class(), '\\' ) + 1 ) );
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 * @note	We cannot pass the regex to the HTML5 'pattern' attribute for two reasons:
	 *	@li	PCRE and ECMAScript regex are not 100% compatible (though the instances this present a problem are admittedly rare)
	 *	@li	You cannot specify modifiers with the pattern attribute, which we need to support on the PHP side
	 */
	public function html()
	{
		/* 10/19/15 - adding htmlspecialchars around value if autocomplete is enabled so that html tag characters can be used (e.g. for members) */
		/* This value is decoded by the JS widget before use */
		if( $this->options['autocomplete'] and !empty( $this->value ) and \is_array( $this->value ) )
		{
			foreach( $this->value as $key => $value )
			{
				$this->value[ $key ] = htmlspecialchars( $value, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			}
		}

		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->text( $this->name, $this->formType, $this->value, $this->required, $this->options['maxLength'], $this->options['size'], $this->options['disabled'], $this->options['autocomplete'], $this->options['placeholder'], NULL, $this->options['nullLang'], $this->htmlId, FALSE, $this->options['htmlAutocomplete'] );
	}
	
	/**
	 * Get Value
	 *
	 * @return	mixed
	 */
	public function getValue()
	{
		$name = $this->name . '_noscript';
		if ( isset( \IPS\Request::i()->$name ) )
		{
			$return = \IPS\Request::i()->$name;
		}
		else
		{
			$return = parent::getValue();
		}

		if ( $this->options['trim'] )
		{
			if ( \is_array( $return ) )
			{
				$return = array_map( 'trim', $return );
			}
			else
			{
				$return = trim( $return );
			}
		}
		
		if ( isset( $this->options['autocomplete'] ) )
		{
			if ( \is_array( $return ) )
			{
				$return = array_map( function( $val ) {
					return htmlspecialchars_decode( $val, ENT_QUOTES );
				}, $return );
			}
			else
			{
				$return = htmlspecialchars_decode( $return, ENT_QUOTES );
			}
			
			if ( !\is_array( $return ) and ( !isset( $this->options['autocomplete']['maxItems'] ) or $this->options['autocomplete']['maxItems'] != 1 ) )
			{
				$return = array_filter( array_map( 'trim', explode( "\n", $return ) ) );
			}
			
			if ( isset( $this->options['autocomplete']['alphabetical'] ) AND $this->options['autocomplete']['alphabetical'] )
			{
				natcasesort( $return );
			}
		}
				
		/* Remove all invisible characters if this is a username */
		if( $this->options['accountUsername'] )
		{
			if ( !\is_array( $return ) )
			{
				$return = preg_replace( '/\p{C}+/u', '', $return );
			}
			else
			{
				foreach( $return as $k => $v )
				{
					$return[ $k ] = preg_replace( '/\p{C}+/u', '', $v );
				}
			}
		}

		if ( isset( $this->options['autocomplete']['prefix'] ) and $this->options['autocomplete']['prefix'] )
		{
			$return = \is_array( $return ) ? $return : ( $return ? array( $return ) : array() );

			$firstAsPrefix = $this->name . '_first_as_prefix';
			$freechoicePrefixCheckbox = $this->name . '_freechoice_prefix';
			$freechoicePrefix = $this->name . '_prefix';
			$noscriptPrefix = $this->name . '_noscript_prefix';
			if ( isset( \IPS\Request::i()->$noscriptPrefix ) and \IPS\Request::i()->$noscriptPrefix )
			{
				$currentIndex = array_search( \IPS\Request::i()->$noscriptPrefix, $return );
				if ( $currentIndex !== FALSE )
				{
					unset( $return[ $currentIndex ] );
				}
				$return['prefix'] = \IPS\Request::i()->$noscriptPrefix;
			}
			elseif ( isset( \IPS\Request::i()->$freechoicePrefixCheckbox ) and \IPS\Request::i()->$freechoicePrefixCheckbox and isset( \IPS\Request::i()->$freechoicePrefix ) and \IPS\Request::i()->$freechoicePrefix )
			{
				$currentIndex = array_search( \IPS\Request::i()->$freechoicePrefix, $return );
				if ( $currentIndex !== FALSE )
				{
					unset( $return[ $currentIndex ] );
				}
				$return['prefix'] = \IPS\Request::i()->$freechoicePrefix;
			}
			elseif ( isset( \IPS\Request::i()->$firstAsPrefix ) and \IPS\Request::i()->$firstAsPrefix and !empty( $return ) )
			{
				$return = array_merge( array( 'prefix' => array_shift( $return ) ), $return );
			}
		}

		return $return;
	}
	
	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{		
		if ( $this->options['autocomplete'] !== NULL and ( !isset( $this->options['autocomplete']['maxItems'] ) or $this->options['autocomplete']['maxItems'] != 1 ) and !\is_array( $this->value ) and $this->value !== NULL )
		{
			return array_filter( array_map( 'trim', explode( "\n", $this->value ) ) );
		}
		
		return $this->value;
	}

	/**
	 * Get the value to use in the label 'for' attribute
	 *
	 * @return	mixed
	 */
	public function getLabelForAttribute()
	{
		return 'elInput_' . parent::getLabelForAttribute();
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

		/* Check it isn't just invisible characters (we don't strip them, because things like zero-width-joiners when in between other characters have a special meaning */
		if ( $this->required and \is_string( $this->value ) and mb_strlen( preg_replace( '/\p{C}+/u', '', $this->value ) ) === 0 )
		{
			throw new \DomainException('form_required');
		}

		/* skip validation if it's an username field and if the name wasn't changed */
		if ( $this->options['accountUsername'] AND $this->options['accountUsername'] instanceOf \IPS\Member AND $this->options['accountUsername']->name == $this->value )
		{
			return TRUE;
		}

		if( \IPS\Dispatcher::i() instanceof \IPS\Dispatcher\Front and !\IPS\Member::loggedIn()->group['g_bypass_badwords'] )
		{
			$looseProfanity = array();
			$exactProfanity = array();
			
			/* Set up profanity filters */
			foreach( \IPS\core\Profanity::getProfanity() AS $profanity )
			{
				if ( $profanity->action == 'block' )
				{
					continue;
				}

				if ( $profanity->m_exact )
				{
					$exactProfanity[ $profanity->type ] = $profanity;
				}
				else
				{
					$looseProfanity[ $profanity->type ] = $profanity;
				}
			}

			foreach( $exactProfanity as $key => $value )
			{
				$exactProfanity[ mb_strtolower( $key ) ] = $value;
			}

			/* Construct break points */
			$exactProfanityBreakpoints = array();
			if( \count( $exactProfanity ) )
			{
				$array = array();
				foreach( array_keys( $exactProfanity ) as $entry )
				{
					$array[] = preg_quote( $entry, '/' );
				}

				$exactProfanityBreakpoints[] = '((?=<^|\b)(?:' . implode( '|', $array ) . ')(?=\b|$))';
			}
		}

		/* Regex */
		if ( $this->options['regex'] !== NULL and $this->value and !preg_match( $this->options['regex'], $this->value ) )
		{
			throw new \InvalidArgumentException( 'form_bad_value' );
		}

		/* Username */
		if ( $this->options['accountUsername'] )
		{
			/* Check it is valid */
			if ( !\IPS\Login::usernameIsAllowed( $this->value ) )
			{
				throw new \DomainException( 'form_name_banned' );
			}

			/* Check if it exists */
			if ( !( $this->options['accountUsername'] instanceof \IPS\Member ) or mb_strtolower( $this->options['accountUsername']->name ) !== mb_strtolower( $this->value ) )
			{
				if ( $error = \IPS\Login::usernameIsInUse( $this->value, ( $this->options['accountUsername'] instanceof \IPS\Member ) ? $this->options['accountUsername'] : NULL, \IPS\Member::loggedIn()->isAdmin() ) )
				{
					throw new \DomainException( $error );
				}
				
				/* Check it's not banned */
				foreach( \IPS\Db::i()->select( 'ban_content', 'core_banfilters', array("ban_type=?", 'name') ) as $bannedName )
				{
					if( preg_match( '/^' . str_replace( '\*', '.*', preg_quote( $bannedName, '/' ) ) . '$/i', $this->value ) )
					{
						throw new \DomainException( 'form_name_banned' );
					}
				}
			}
		}

		/* Tags */
		if ( $this->options['autocomplete'] !== NULL )
		{
			if( $this->value )
			{
				$values = ( \is_array( $this->value ) ) ? $this->value : array( $this->value );
			}
			else
			{
				$values = array();
			}

			if ( isset( $this->options['autocomplete']['maxItems'] ) and \count( $values ) > $this->options['autocomplete']['maxItems'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_max', FALSE, array( 'pluralize' => array( $this->options['autocomplete']['maxItems'] ) ) ) );
			}
			if ( isset( $this->options['autocomplete']['minItems'] ) and ( $this->required or \count( $values ) > 0 ) and \count( $values ) < $this->options['autocomplete']['minItems'] )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_min', FALSE, array( 'pluralize' => array( $this->options['autocomplete']['minItems'] ) ) ) );
			}

			if ( isset( $this->options['autocomplete']['minLength'] ) )
			{
				foreach ( $values as $v )
				{
					if ( mb_strlen( $v ) < $this->options['autocomplete']['minLength'] )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_length_min', FALSE, array( 'pluralize' => array( $this->options['autocomplete']['minLength'] ) ) ) );
					}
				}
			}
			if ( isset( $this->options['autocomplete']['maxLength'] ) )
			{
				foreach ( $values as $v )
				{
					if ( mb_strlen( $v ) > $this->options['autocomplete']['maxLength'] )
					{
						throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_length_max', FALSE, array( 'pluralize' => array( $this->options['autocomplete']['maxLength'] ) ) ) );
					}
				}
			}
			if ( isset( $this->options['autocomplete']['filterProfanity'] ) AND \is_array( $this->value ) AND \count( $this->value ) and \IPS\Dispatcher::i() instanceof \IPS\Dispatcher\Front and !\IPS\Member::loggedIn()->group['g_bypass_badwords'] )
			{
				foreach ( $values as $k => $v )
				{
					if ( array_key_exists( $v, $exactProfanity ) )
					{
						if ( $exactProfanity[ $v ]->action == 'swap' )
						{
							$this->value[ $k ] = $exactProfanity[ $v ]->swop;
						}
						else
						{
							throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_not_allowed', FALSE, array( 'sprintf' => array( $v ) ) ) );
						}
					}
					else
					{
						$swaps	= array();
						foreach( $looseProfanity AS $type => $row )
						{
							if ( $row->action == 'swap' )
							{
								$swaps[ $row->type ] = $row->swop;
							}
							else
							{
								if ( mb_stristr( $this->value[ $k ], $type ) )
								{
									throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_not_allowed', FALSE, array( 'sprintf' => array( $v ) ) ) );
								}
							}
						}
						
						/* Still here? Normal swaps. */
						$this->value[ $k ] = str_ireplace( array_keys( $swaps ), array_values( $swaps ), $this->value[ $k ] );
					}
				}
			}

			if ( isset( $this->options['autocomplete']['unique'] ) AND $this->options['autocomplete']['unique'] AND \is_array( $this->value ) AND \count( $this->value ) )
			{
				foreach ( $this->value as $v )
				{
					if ( is_scalar( $v ) )
					{
						$this->value = array_unique( $this->value );
					}
					break;
				}
			}

			if ( \is_array( $this->value ) AND isset( $this->options['autocomplete']['source'] ) AND \is_array( $this->options['autocomplete']['source'] ) AND ( !isset( $this->options['autocomplete']['freeChoice'] ) OR !$this->options['autocomplete']['freeChoice'] ) )
			{
				if( isset( $this->options['autocomplete']['forceLower'] ) AND $this->options['autocomplete']['forceLower'] )
				{
					$this->value = array_uintersect( array_map( 'mb_strtolower', $this->value ), array_map( 'mb_strtolower', array_map( 'trim', $this->options['autocomplete']['source'] ) ), 'strcasecmp' );
				}
				else
				{
					$this->value = array_uintersect( $this->value, array_map( 'trim', $this->options['autocomplete']['source'] ), 'strcasecmp' );
				}
			}
		}

		/* Split on profanity */
		if( \in_array( $this->options['bypassProfanity'], array( static::BYPASS_PROFANITY_NONE, static::BYPASS_PROFANITY_SWAP ) ) and \is_string( $this->value ) and \IPS\Dispatcher::i() instanceof \IPS\Dispatcher\Front and !\IPS\Member::loggedIn()->group['g_bypass_badwords'] AND !$this->options['accountUsername'] )
		{
			$newVal = NULL;
			if ( \count( $exactProfanityBreakpoints ) )
			{
				/* preg_split can return boolean false*/
				$split = preg_split( '/' . implode( '|', $exactProfanityBreakpoints ) . '/iu', $this->value, null, PREG_SPLIT_DELIM_CAPTURE );

				if( \is_array( $split ) )
				{
					foreach ( $split as $section )
					{
						if ( isset( $exactProfanity[ mb_strtolower( $section ) ] ) )
						{
							if ( $exactProfanity[ mb_strtolower( $section ) ]->action == 'swap' )
							{
								$newVal .= $exactProfanity[ mb_strtolower( $section ) ]->swop;
							}
							else
							{
								if ( $this->options['bypassProfanity'] === static::BYPASS_PROFANITY_NONE )
								{
									throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_not_allowed', FALSE, array( 'sprintf' => array( $section ) ) ) );
								}
								else
								{
									$newVal .= $section;
								}
							}
						}
						else
						{
							$newVal .= $section;
						}
					}
				}
			}
			
			$value = $newVal ?: $this->value;
			
			if ( \count( $looseProfanity ) )
			{
				$swaps = array();
				foreach( $looseProfanity AS $type => $row )
				{
					if ( $row->action == 'swap' )
					{
						$swaps[ $row->type ] = $row->swop;
					}
					else
					{
						if ( mb_stristr( $value, $type ) )
						{
							if ( $this->options['bypassProfanity'] === static::BYPASS_PROFANITY_NONE )
							{
								throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_tags_not_allowed', FALSE, array( 'sprintf' => array( $row->type ) ) ) );
							}
						}
					}
				}
				
				$value = str_ireplace( array_keys( $swaps ), array_values( $swaps ), $value );
			}

			$this->value = $value;
		}

		return TRUE;
	}
}
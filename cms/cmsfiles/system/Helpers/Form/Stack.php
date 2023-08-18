<?php
/**
 * @brief		Stack input class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Apr 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stack input class for Form Builder
 */
class _Stack extends FormAbstract
{
	/**
	 * @brief	Default Options
	 */
	protected $defaultOptions = array(
		'stackFieldType'	=> 'Text',
        'removeEmptyValues' => TRUE,
        'maxItems'			=> NULL,
        'minItems'			=> NULL
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
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-ui.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery-touchpunch.js', 'core', 'interface' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'jquery/jquery.menuaim.js', 'core', 'interface' ) );
		
		/* Test for javascript disabled add stack */
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
		{
			if ( ( \IPS\Request::i()->valueFromArray('form_remove_stack') !== NULL OR isset( \IPS\Request::i()->form_add_stack ) ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
			{
				$this->reloadForm = true;
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
		$fields		= array();
		$classType	= mb_strpos( $this->options['stackFieldType'], '\\' ) === FALSE ? ( "\\IPS\\Helpers\\Form\\" . $this->options['stackFieldType'] ) : $this->options['stackFieldType'];
		$remove     = \IPS\Request::i()->valueFromArray('form_remove_stack');
		
		/* The JS fallback needs a unique key for the field to remove */
		$remove = ( \is_array( $remove ) ) ? key( $remove ) : null;
		
		if( \count($this->value) )
		{
			foreach( $this->value as $k => $v )
			{
				$class = ( new $classType( $this->name . '[' . \count($fields) . ']', $v, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix, $this->htmlId  . '_' . \count($fields) ) );
				$class->setValue( TRUE );
				$html = $class->html();
				
				if( !\IPS\Login::compareHashes( $remove, md5( $html ) ) )
				{
					$fields[] = $html;
				}
			}
		}
		else
		{
			$class = ( new $classType( $this->name . '[0]', NULL, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix, $this->htmlId  . '_' . \count($fields) ) );
			$class->setValue( TRUE );
			$html = $class->html();
			
			if( !\IPS\Login::compareHashes( $remove, md5( $html ) ) )
			{
				$fields[] = $html;
			}
		}
		
		/* We hit the add stack button with JS disabled */
		if ( $this->reloadForm === TRUE AND $remove === NULL )
		{
			$class = ( new $classType( $this->name . '[]', NULL, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix, $this->htmlId  . '_' . \count($fields) ) );
			$class->setValue( TRUE );
			$html     = $class->html();
			$fields[] = $html;
		}

		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->stack( $this->name, $fields, $this->options );
	}

	/**
	 * @brief	Temporarily store if this is the intitial setValue call
	 */
	protected $_setValueInitial	= NULL;

	/**
	 * Set the value of the element
	 *
	 * @param	bool	$initial	Whether this is the initial call or not. Do not reset default values on subsequent calls.
	 * @param	bool	$force		Set the value even if one was not submitted (done on the final validation when getting values)?
	 * @return	void
	 */
	public function setValue( $initial=FALSE, $force=FALSE )
	{
		$this->_setValueInitial = $initial;

		parent::setValue( $initial, $force );

		$this->_setValueInitial	= NULL;
	}

	/**
	 * Format Value
	 *
	 * @return	mixed
	 */
	public function formatValue()
	{
		$values		= array();
		$classType	= mb_strpos( $this->options['stackFieldType'], '\\' ) === FALSE ? ( "\\IPS\\Helpers\\Form\\" . $this->options['stackFieldType'] ) : $this->options['stackFieldType'];
		$name		= $this->name;

		if( mb_substr( $name, 0, 8 ) !== '_new_[x]' and isset( \IPS\Request::i()->$name ) )
		{
			if( \is_array( \IPS\Request::i()->$name ) AND \count( \IPS\Request::i()->$name ) )
			{ 
				foreach( \IPS\Request::i()->$name as $k => $v )
				{
					$class = ( new $classType( $this->name . '[' . $k . ']', $v, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix, $this->htmlId ) );
					$class->setValue( $this->_setValueInitial, $this->_setValueInitial ? FALSE : TRUE );
					$values[]	= $class->value;
				}
			}

            return ( $this->options['removeEmptyValues'] ) ? array_filter( $values ) : $values;
		}
		else if ( \is_array( $this->value ) AND \count( $this->value ) )
		{
			foreach( $this->value as $k => $v )
			{
				$class = ( new $classType( $this->name . '[' . $k . ']', $v, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix, $this->htmlId ) );
				$class->setValue( $this->_setValueInitial, $this->_setValueInitial ? FALSE : TRUE );
				$values[]	= $class->value;
			}

			return ( $this->options['removeEmptyValues'] ) ? array_filter( $values ) : $values;
		}
		else
		{
			return array();
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
		if ( empty( $this->value ) and $this->required )
		{
			throw new \InvalidArgumentException('form_required');
		}
		
		if ( $this->options['maxItems'] !== NULL and \count( $this->value ) > $this->options['maxItems'] )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_items_max', FALSE, array( 'pluralize' => array( $this->options['maxItems'] ) ) ) );
		}

		if ( $this->options['minItems'] !== NULL and \count( $this->value ) < $this->options['minItems'] )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack( 'form_items_min', FALSE, array( 'pluralize' => array( $this->options['minItems'] ) ) ) );
		}

		$classType	= mb_strpos( $this->options['stackFieldType'], '\\' ) === FALSE ? ( "\\IPS\\Helpers\\Form\\" . $this->options['stackFieldType'] ) : $this->options['stackFieldType'];

		foreach( $this->value as $k => $v )
		{
			$class = new $classType( $this->name .'_validate', $v, FALSE, $this->options, $this->customValidationCode, $this->prefix, $this->suffix);
			$class->validate();
		}
		
		parent::validate();
	}
}
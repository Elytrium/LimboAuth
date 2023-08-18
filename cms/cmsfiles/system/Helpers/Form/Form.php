<?php
/**
 * @brief		Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Helpers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Form Builder
 */
class _Form
{
	/**
	 * @brief	Form ID
	 */
	public $id = '';
	
	/**
	 * @brief	Action URL
	 */
	public $action = '';
	
	/**
	 * @brief	Input Elements HTML
	 */
	public $elements = array();
	
	/**
	 * @brief	Tabs
	 */
	protected $tabs = array();
	
	/**
	 * @brief	Current Tab we're adding elements to
	 */
	protected $currentTab = '';
	
	/**
	 * @brief	Active tab
	 */
	public $activeTab = NULL;
	
	/**
	 * @brief	Additional class for tables
	 */
	protected $tabClasses = array();
		
	/**
	 * @brief	Sidebar
	 */
	public $sidebar = array();
	
	/**
	 * @brief	CSS Class(es)
	 */
	public $class = 'ipsForm_horizontal';
	
	/**
	 * @brief	Generic Form Error
	 */
	public $error = '';
	
	/**
	 * @brief	Hidden Values
	 */
	public $hiddenValues = array();
	
	/**
	 * @brief	Extra attributes for `<form>` tag
	 */
	public $attributes = array();
	
	/**
	 * @brief	Action Buttons
	 */
	public $actionButtons = array();
		
	/**
	 * @brief	If form has upload field, the maximum size (Needed to add enctype="multipart/form-data")
	 * @note	Only actually affects no-JS uploads, Plupload does it's own thing
	 */
	protected $uploadField = FALSE;
	
	/**
	 * @brief	If enabled, and this form is submitted in a modal popup window, the next screen will be shown within the modal popup
	 */
	public $ajaxOutput = FALSE;
	
	/**
	 * @brief	Is the form using tabs with icons
	 */
	protected $iconTabs = FALSE;
	
	/**
	 * @brief	Copy Button URL
	 */
	public $copyButton = NULL;
	
	/**
	 * @brief	Language keys to preload for efficiency
	 */
	protected $languageKeys = array();
	
	/**
	 * @brief	This form can be reloaded after saving
	 */
	public $canSaveAndReload = false;
	
	/**
	 * Constructor
	 *
	 * @param	string				$id			Form ID
	 * @param	string				$submitLang	Language string for submit button
	 * @param	\IPS\Http\Url|NULL	$action		Action URL
	 * @param	array				$attributes	Extra attributes for `<form>` tag
	 * @return	void
	 */
	public function __construct( $id='form', $submitLang='save', $action=NULL, $attributes=array() )
	{
		$this->id = $id;
		$this->action = $action ?: \IPS\Request::i()->url()->stripQueryString( array( 'csrfKey', 'ajaxValidate' ) );	
		
		$this->attributes = $attributes;
		
		if( $submitLang )
		{
			$this->actionButtons[] = \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->button( $submitLang, 'submit', null, 'ipsButton ipsButton_primary', array( 'tabindex' => '2', 'accesskey' => 's' ) );
		}
		
		$this->hiddenValues['csrfKey'] = \IPS\Session::i()->csrfKey;
		
		$potentialMaxUploadValues	= array();
		if( (float) ini_get('upload_max_filesize') > 0 )
		{
			$potentialMaxUploadValues[]	= \IPS\File::returnBytes( ini_get('upload_max_filesize') );
		}
		if( (float) ini_get('post_max_size') > 0 )
		{
			/* We need to reduce post_max_size lower because it includes the ENTIRE post and other data, such as the number of chunks, will also be sent with the request */
			$potentialMaxUploadValues[]	= \IPS\File::returnBytes( ini_get('post_max_size') ) - 1048576;
		}
		if( (float) ini_get('memory_limit') > 0 )
		{
			$potentialMaxUploadValues[]	= \IPS\File::returnBytes( ini_get('memory_limit') );
		}
		$this->uploadField = min( $potentialMaxUploadValues );
		
		/* This can be overridden in userland code, but by default takes the value sent by \IPS\Node\Controller::_afterSave() */
		if ( isset( \IPS\Request::i()->activeTab ) )
		{
			$this->activeTab = \IPS\Request::i()->activeTab;
		}
	}
	
	/**
	 * Add Tab
	 *
	 * @param	string			$lang		Language key
	 * @param	string|null		$icon		Icon to use
	 * @param	string|null		$blurbLang	Language to use for the blurb
	 * @param	string|null		$css		CSS class to use for the tab
	 * @return	void
	 */
	public function addTab( $lang, $icon=NULL, $blurbLang=NULL, $css=NULL )
	{
		$this->tabs[$lang]['title'] = $lang;
		$this->currentTab = $lang;
		if ( $this->activeTab === NULL )
		{
			$this->activeTab = $lang;
		}
		
		if ( $icon )
		{
			$this->tabs[$lang]['icon'] = $icon;
			$this->iconTabs = TRUE;
		}
		
		if ( $blurbLang )
		{
			$this->elements[ $this->currentTab ][] = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( $blurbLang );
		}
		
		if ( $css )
		{
			$this->tabClasses[ $this->currentTab ] = $css;
		}
	}
	
	/**
	 * Add Header
	 *
	 * @param	string			$lang		Language key
	 * @param	string|NULL		$after		The key of element to insert after
	 * @param	string|NULL		$tab		The tab to insert onto
	 * @return	void
	 */
	public function addHeader( $lang, $after=NULL, $tab=NULL )
	{
		/* Place the input into the correct position */
		$this->_insert( \IPS\Theme::i()->getTemplate( 'forms', 'core' )->header( $lang, "{$this->id}_header_{$lang}" ), NULL, $tab, $after );
	}

	/**
	 * Add Seperator
	 *
	 * @param	string|NULL		$after		The key of element to insert after
	 * @param	string|NULL		$tab		The tab to insert onto
	 * @return	void
	 */
	public function addSeparator( $after=NULL, $tab=NULL )
	{
		/* Place the input into the correct position */
		$this->_insert( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'front' )->seperator(), NULL, $tab, $after );
	}

	/**
	 * Add Message Row
	 *
	 * @param	string			$lang		Language key or formatted string to display
	 * @param	string			$css		Custom CSS class(es) to apply
	 * @param	bool			$parse		Set this to false if the language string passed is already formatted for display
	 * @param	string			$_id		HTML ID
	 * @param	string|NULL		$after		The key of element to insert after
	 * @param	string|NULL		$tab		The tab to insert onto
	 * @return	void
	 */
	public function addMessage( $lang, $css='', $parse=TRUE, $_id=NULL, $after=NULL, $tab=NULL )
	{
		if ( !$_id )
		{
			$_id	= "{$this->id}_header_" . md5( $lang );
			if( $parse === FALSE )
			{
				$_id	= preg_replace( "/[^a-zA-Z0-9_]/", '_', $_id );
			}
		}

		/* Place the input into the correct position */
		$this->_insert( \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->message( $lang, $_id, $css, $parse ), NULL, $tab, $after );
	}
	
	/**
	 * Add Html
	 *
	 * @param	string			$html	HTML to add
	 * @param	string|NULL		$after	The key of element to insert after
	 * @param	string|NULL		$tab	The tab to insert onto
	 * @return	void
	 */
	public function addHtml( $html, $after=NULL, $tab=NULL )
	{
		/* Place the input into the correct position */
		$this->_insert( $html, NULL, $tab, $after );
	}
	
	/**
	 * Add Sidebar
	 *
	 * @param	string	$contents	Contents
	 * @return	void
	 */
	public function addSidebar( $contents )
	{
		$this->sidebar[ $this->currentTab ] = $contents;
	}
	
	/**
	 * Add Matrix
	 *
	 * @param	mixed						$name	Name to identify matrix
	 * @param	\IPS\Helpers\Form\Matrix	$matrix	The Matrix
	 * @param	string|NULL					$after	The key of element to insert after
	 * @param	string|NULL					$tab	The tab to insert onto
	 * @return	void
	 */
	public function addMatrix( $name, $matrix, $after=NULL, $tab=NULL )
	{
		$matrix->formId = $this->id;
		$this->tabClasses[ $this->currentTab ] = 'ipsMatrix';

		/* Place the input into the correct position */
		$this->_insert( $matrix, $name, $tab, $after );
	}
	
	/**
	 * Add Button
	 *
	 * @param	string	$lang	Language key
	 * @param	string	$type	'link', 'button' or 'submit'
	 * @param	string	$href	If type is 'link', the target
	 * @param	string	$class 	CSS class(es) to applys
	 * @param 	array 	$attributes Attributes to apply
	 * @return	void
	 */
	public function addButton( $lang, $type, $href=NULL, $class='', $attributes=array() )
	{
		$this->actionButtons[] = ' ' . \IPS\Theme::i()->getTemplate( 'forms', 'core', 'global' )->button( $lang, $type, $href, $class, $attributes );
	}
	
	/**
	 * Add Dummy Row
	 *
	 * @param	string			$langKey	Language key
	 * @param	string			$value		Value
	 * @param	string			$desc		Field description
	 * @param	string			$warning	Field warning
	 * @param	string			$id			Element ID
	 * @param	string|NULL		$after		The key of element to insert after
	 * @param	string|NULL		$tab		The tab to insert onto
	 * @return	void
	 */
	public function addDummy( $langKey, $value, $desc='', $warning='', $id='', $after=NULL, $tab=NULL )
	{
		/* Place the input into the correct position */
		$this->_insert( \IPS\Theme::i()->getTemplate( 'forms', 'core' )->row( \IPS\Member::loggedIn()->language()->addToStack( $langKey ), $value, $desc, $warning, FALSE, NULL, NULL, NULL, $id ), NULL, $tab, $after );
	}

	/**
	 * Add Input
	 *
	 * @param	\IPS\Helpers\Form\FormAbstract	$input	Form element to add
	 * @param	string|NULL						$after	The key of element to insert after
	 * @param	string|NULL						$tab	The tab to insert onto
	 * @return	void
	 */
	public function add( $input, $after=NULL, $tab=NULL )
	{
		/* Place the input into the correct position */
		$this->_insert( $input, $input->name, $tab, $after );
		
		/* If it's a captcha field, we need to add a hidden value */
		if ( $input instanceof Form\Captcha )
		{
			$this->hiddenValues[ $input->name ] = TRUE;
		}
		
		$preloadTypes = array( 'CheckboxSet', 'Radio' );
		
		/* Some form fields check for _desc and _warning so preload these */
		foreach( $preloadTypes as $type )
		{
			$class = 'IPS\Helpers\Form\\' . $type;
			
			if ( is_a( $input, $class ) )
			{
				$this->languageKeys[] = $input->name . '_desc';
				$this->languageKeys[] = $input->name . '_warning';
				
				if ( isset( $input->options['options'] ) and \count( $input->options['options'] ) )
				{
					$this->languageKeys = array_merge( $this->languageKeys, array_map(
							function ($v )
							{	
								return $v . '_desc';
							},
							array_values( $input->options['options'] )
						)
					);
					$this->languageKeys = array_merge( $this->languageKeys, array_map(
							function ($v )
							{	
								return $v . '_warning';
							},
							array_values( $input->options['options'] )
						)
					);
				}
			}
		}
	}

	/**
	 * Actually place the element in the correct position
	 *
	 * @param	mixed			$element	Thing we are adding (could be a form input, message, etc.)
	 * @param	string|NULL		$elementKey	The key of the element
	 * @param	string|NULL		$tab		The tab to insert this thing into
	 * @param	string|NULL		$after		The key of the element we want to insert this thing after
	 * @return	void
	 */
	protected function _insert( $element, $elementKey=NULL, $tab=NULL, $after=NULL )
	{
		$tab = $tab ?: $this->currentTab;
		
		if ( $after )
		{
			$elements = array();
			foreach ( $this->elements[ $tab ] as $key => $_element )
			{
				$elements[ $key ] = $_element;
				if ( $key === $after )
				{
					$elements[ $elementKey ] = $element;
				}
			}
			$this->elements[ $tab ] = $elements;
		}
		elseif( $elementKey )
		{
			$this->elements[ $tab ][ $elementKey ] = $element;
		}
		else
		{
			$this->elements[ $tab ][] = $element;
		}
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		/* Preload languages */
		if ( \count( $this->languageKeys ) and !( \IPS\Member::loggedIn()->language() instanceof \IPS\Lang\Setup\Lang ) )
		{
			try
			{
				\IPS\Member::loggedIn()->language()->get( $this->languageKeys );
			}
			catch( \UnderflowException $e ) { }
		}
		
		try
		{
			$html = array();
			$errorTabs = array();
			foreach ( $this->elements as $tab => $elements )
			{
				$html[ $tab ] = '';
				foreach ( $elements as $k => $element )
				{
					if ( $element instanceof Form\Matrix )
					{
						$html[ $tab ] .= \IPS\Theme::i()->getTemplate( 'forms', 'core' )->emptyRow( $element->nested(), $k );
						continue;
					}
					if ( !\is_string( $element ) and $element->error )
					{
						$errorTabs[] = $tab;
					}
					$html[ $tab ] .= ( $element instanceof \IPS\Helpers\Form\FormAbstract ) ? $element->rowHtml( $this ) : (string) $element;
				}
			}
			
			if ( $this->canSaveAndReload )
			{
				$this->addButton( 'save_and_reload', 'submit', null, 'ipsButton ipsButton_primary', array( 'name' => 'save_and_reload', 'value' => 1 ) );
			}
			
			return \IPS\Theme::i()->getTemplate( 'forms', 'core' )->template( $this->id, $this->action, $html, $this->activeTab, $this->error, $errorTabs, $this->hiddenValues, $this->actionButtons, $this->uploadField, $this->sidebar, $this->tabClasses, $this->class, $this->attributes, $this->tabs, $this->iconTabs );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
	
	/**
	 * Get HTML using custom template
	 *
	 * @param	callback	$template	The template to use
	 * @return	string
	 */
	public function customTemplate( $template )
	{
		$args = \func_get_args();
		
		if ( \count( $args ) > 1 )
		{
			array_shift( $args );
		}
		else
		{
			$args = array();
		}
		
		/* Preload languages */
		if ( \count( $this->languageKeys ) )
		{
			try
			{
				\IPS\Member::loggedIn()->language()->get( $this->languageKeys );
			}
			catch( \UnderflowException $e ) { }
		}

		$errorTabs = array();
		foreach ( $this->elements as $tab => $elements )
		{
			foreach ( $elements as $k => $element )
			{
				if ( !( $element instanceof Form\Matrix ) and !\is_string( $element ) and $element->error )
				{
					$errorTabs[] = $tab;
				}
			}
		}

		$templateArguments = array_merge( $args, array( $this->id, $this->action, $this->elements, $this->hiddenValues, $this->actionButtons, $this->uploadField, $this->class, $this->attributes, $this->sidebar, $this, $errorTabs ) );
		return $template( ...$templateArguments );
	}
	
	/**
	 * Return the last used tab in the current form
	 *
	 * @return null|string
	 */
	public function getLastUsedTab()
	{
		$name = "{$this->id}_activeTab";
		if ( isset( \IPS\Request::i()->$name ) )
		{
			return \IPS\Request::i()->$name;
		}
		
		return null;
	}
	
	/**
	 * Get submitted values
	 *
	 * @param	bool	$stringValues	If true, all values will be returned as strings
	 * @return	array|FALSE		Array of field values or FALSE if the form has not been submitted or if there were validation errors
	 */
	public function values( $stringValues=FALSE )
	{
		$values = array();
		$name = "{$this->id}_submitted";
		$uploadFieldNames = array();
		$uploadRetainDeleted = array();
		$uploadCurrentFiles = array();
		
		/* Did we submit the form? */
		if( isset( \IPS\Request::i()->$name ) and \IPS\Login::compareHashes( (string) \IPS\Session::i()->csrfKey, (string) \IPS\Request::i()->csrfKey ) )
		{
			/* Work out which fields are being toggled by other fields */
			$htmlIdsToIgnoreBecauseTheyAreHiddenByToggles = array();
			$htmlIdsWeWantBecauseTheyAreActivatedByToggles = array();
			foreach ( $this->elements as $elements )
			{
				foreach ( $elements as $_name => $element )
				{
					if ( isset( $element->options['togglesOn'] ) )
					{
						if ( !$element->value )
						{
							$htmlIdsToIgnoreBecauseTheyAreHiddenByToggles = array_merge( $htmlIdsToIgnoreBecauseTheyAreHiddenByToggles, $element->options['togglesOn'] );
						}
						else
						{
							$htmlIdsWeWantBecauseTheyAreActivatedByToggles = array_merge( $htmlIdsWeWantBecauseTheyAreActivatedByToggles, $element->options['togglesOn'] );
						}
					}
					if ( isset( $element->options['togglesOff'] ) )
					{
						if ( $element->value )
						{
							$htmlIdsToIgnoreBecauseTheyAreHiddenByToggles = array_merge( $htmlIdsToIgnoreBecauseTheyAreHiddenByToggles, $element->options['togglesOff'] );
						}
						else
						{
							$htmlIdsWeWantBecauseTheyAreActivatedByToggles = array_merge( $htmlIdsWeWantBecauseTheyAreActivatedByToggles, $element->options['togglesOff'] );
						}
					}
					if ( isset( $element->options['zeroValTogglesOff'] ) )
					{
						if ( $element->value === 0 )
						{
							$htmlIdsToIgnoreBecauseTheyAreHiddenByToggles = array_merge( $htmlIdsToIgnoreBecauseTheyAreHiddenByToggles, $element->options['zeroValTogglesOff'] );
						}
						else
						{
							$htmlIdsWeWantBecauseTheyAreActivatedByToggles = array_merge( $htmlIdsWeWantBecauseTheyAreActivatedByToggles, $element->options['zeroValTogglesOff'] );
						}
					}
					if ( isset( $element->options['toggles'] ) )
					{
						foreach ( $element->options['toggles'] as $toggleValue => $toggleHtmlIds )
						{
							$match = \is_array( $element->value ) ? \in_array( $toggleValue, $element->value ) : $toggleValue == $element->value;
							if ( !$match )
							{
								$htmlIdsToIgnoreBecauseTheyAreHiddenByToggles = array_merge( $htmlIdsToIgnoreBecauseTheyAreHiddenByToggles, $toggleHtmlIds );
							}
							else
							{
								$htmlIdsWeWantBecauseTheyAreActivatedByToggles = array_merge( $htmlIdsWeWantBecauseTheyAreActivatedByToggles, $toggleHtmlIds );
							}
						}
					}
				}
			}

			$htmlIdsToIgnore = array_diff( array_unique( $htmlIdsToIgnoreBecauseTheyAreHiddenByToggles ), array_unique( $htmlIdsWeWantBecauseTheyAreActivatedByToggles ) );
			
			/* Loop elements */
			foreach ( $this->elements as $elements )
			{
				foreach ( $elements as $_name => $element )
				{
					/* If it's a matrix, populate the values from it */
					if ( ( $element instanceof Form\Matrix ) )
					{
						$values[ $_name ] = $element->values( TRUE );
						continue;
					}
					
					/* If it's not a form element, skip */
					if ( !( $element instanceof Form\FormAbstract ) )
					{
						continue;
					}
					
					/* If this is dependant on a toggle which isn't set, don't return a value so that it doesn't
						trigger an error we cannot see */
					if ( $element->htmlId and \in_array( $element->htmlId, $htmlIdsToIgnore ) )
					{ 
						$values[ $_name ] = $stringValues ? $element::stringValue( $element->defaultValue ) : $element->defaultValue;
						continue;
					}
										
					/* Make sure we have a value (someone might try to be sneaky and remove the HTML from the form before submitting) */
					if ( !$element->valueSet )
					{
						$element->setValue( FALSE, TRUE );
					}
					
					/* If it's an upload field, we'll need to remember the name */
					if ( ( $element instanceof Form\Upload ) )
					{
						$uploadFieldNames[] = $element->name;
						if ( $element->options['retainDeleted'] )
						{
							$uploadRetainDeleted[] = $element->name;
							if ( \is_array( $element->value ) )
							{
								foreach( $element->value AS $value )
								{
									$uploadCurrentFiles[] = $value->originalFilename;
								}
							}
							elseif( $element->value !== NULL )
							{
								$uploadCurrentFiles[] = $element->value->originalFilename;
							}
						}
					}

					/* If it has an error, set it and return */
					if( !empty( $element->error ) )
					{
						\IPS\Output::i()->httpHeaders['X-IPS-FormError'] = "true";
						return FALSE;
					}

					/* If it's a poll, save it */
					if ( $element instanceof Form\Poll and $element->value !== NULL )
					{
						$element->value->save();
					}

					/* If it's a social group, save it */
					if ( $element instanceof \IPS\Helpers\Form\SocialGroup )
					{
						$element->saveValue();
					}

					/* If the element has requested the form doesn't submit, return */
					if ( $element->reloadForm === TRUE )
					{
						\IPS\Output::i()->httpHeaders['X-IPS-FormNoSubmit'] = "true";
						return FALSE;
					}
					
					/* Still here? Then add the value */
					$values[ $element->name ] = $stringValues ? $element::stringValue( $element->value ) : $element->value;
				}
			}

			foreach ( $this->hiddenValues as $key => $value )
			{
				if( $key != 'csrfKey' )
				{
					$values[$key] = $value;
				}
			}

			/* If we've reached this point, all fields have acceptable values. If we're just checking that, return that it's okay */
			if ( \IPS\Request::i()->isAjax() and \IPS\Request::i()->ajaxValidate )
			{
				if ( $this->ajaxOutput === TRUE )
				{
					\IPS\Output::i()->httpHeaders['X-IPS-FormNoSubmit'] = "true";
				}
				else
				{
					\IPS\Output::i()->json( array( 'validate' => true ) );
				}
			}
			
			/* At this point we are about to return the values. Any uploaded files are now the responsibility of the controller, so release the hold on them */
			foreach ( $uploadFieldNames as $name )
			{
				if ( !\in_array( $name, $uploadRetainDeleted ) )
				{
					\IPS\Db::i()->delete( 'core_files_temp', array( 'upload_key=?', md5( $name . session_id() ) ) );
				}
				else
				{
					/* If we're retaining deleted files, then remove any files that are actually are a part of the value */
					\IPS\Db::i()->delete( 'core_files_temp', array( "upload_key=? AND " . \IPS\Db::i()->in( 'filename', $uploadCurrentFiles ), md5( $name . session_id() ) ) );
				}
			}
			
			/* And return */
			return $values;
		}
		/* Nope, return FALSE */
		else
		{
			return FALSE;
		}
	}

	/**
	 * Save values to settings table
	 *
	 * @param array|NULL 	$values		Form Values
	 * @return bool
	 */
	public function saveAsSettings( $values=NULL )
	{
		if ( !$values )
		{
			$values = $this->values( TRUE );
		}
		
		if ( $values )
		{
			\IPS\Settings::i()->changeValues( $values );
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Flood Check
	 *
	 * @return	void
	 */
	public static function floodCheck()
	{
		if ( \IPS\Settings::i()->flood_control and !\IPS\Member::loggedIn()->group['g_avoid_flood'] )
		{
			if ( time() - \IPS\Member::loggedIn()->member_last_post < \IPS\Settings::i()->flood_control )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('error_flood_control', FALSE, array( 'sprintf' => array( \IPS\Settings::i()->flood_control - ( time() - \IPS\Member::loggedIn()->member_last_post ) ) ) ) );
			}
		}
	}
}
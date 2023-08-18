<?php
/**
 * @brief		languages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Jun 2013
 */

namespace IPS\core\modules\admin\languages;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * languages
 */
class _languages extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Lang';
	
	/**
	 * Title can contain HTML?
	 */
	public $_titleHtml = TRUE;
	
	/**
	 * Description can contain HTML?
	 */
	public $_descriptionHtml = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'lang_words' );
		parent::execute();
	}
	
	/**
	 * Allow overloading to change how the title is displayed in the tree
	 *
	 * @param	$node	\IPS\Node	Node
	 * @return string
	 */
	protected static function nodeTitle( $node )
	{
		return \IPS\Theme::i()->getTemplate('customization')->langRowTitle( $node );
	}

	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		$hasBeenCustomized = \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( 'lang_id=? AND word_export=1 AND word_custom IS NOT NULL', $node->id ) )->first();
		return \IPS\Theme::i()->getTemplate('customization' )->langRowAdditional( $node, $hasBeenCustomized );
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'flags.css', 'core', 'global' ) );
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'languages', 'lang_words' ) )
		{
			\IPS\Output::i()->sidebar['actions'][] = array(
				'icon'	=> 'globe',
				'title'	=> 'lang_vle',
				'link'	=> \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=vle' ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('lang_vle') )
			);
			
			\IPS\Output::i()->sidebar['actions'][] = array(
				'icon'	=> 'plus',
				'title'	=> 'add_word',
				'link'	=> \IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=addWord" ),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( 'add_word' ) )
			);
			
			/* @note If any more settings are added, then this condition needs to be moved. */
			if ( \count( \IPS\Lang::languages() ) > 1 )
			{
				\IPS\Output::i()->sidebar['actions'][] = array(
					'icon'	=> 'cogs',
					'title'	=> 'settings',
					'link'	=> \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=settings' ),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('settings') )
				);
			}
		}
		
		parent::manage();
	}
	
	/**
	 * Add Word
	 *
	 * @return	void
	 */
	protected function addWord()
	{
		$form = new \IPS\Helpers\Form( 'wordForm', 'save', NULL, array( 'data-role' => 'wordForm' ) );
		\IPS\Lang::wordForm( $form );
		
		if ( $values = $form->values() )
		{
			/* Save */
			\IPS\Lang::saveCustom( 'core', $values['word_key'], $values['word_custom'] ?? NULL, FALSE, $values['word_default'] );
			
			\IPS\Session::i()->log( 'acplog__custom_word_added', array( $values['word_key'] => FALSE ) );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=languages&controller=languages" ), 'saved' );
			}
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'add_word' );
		\IPS\Output::i()->output = (string) $form;
	}
	
	/**
	 * Delete Word
	 *
	 * @return	void
	 */
	protected function deleteWord()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Make sure this is actually a custom phrase */
		try
		{
			$word = \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( "word_key=? AND lang_id=?", \IPS\Request::i()->key, \IPS\Request::i()->langId ) )->first();
			
			if ( !$word['word_is_custom'] ) 
			{
				\IPS\Output::i()->error( 'node_error', '1C126/9', 403, '' );
			}
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '1C126/A', 403, '' );
		}
		
		\IPS\Db::i()->delete( 'core_sys_lang_words', array( "word_key=?", \IPS\Request::i()->key ) );
		
		\IPS\Session::i()->log( 'acplog__custom_word_deleted', array( \IPS\Request::i()->key => FALSE ) );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=translate&id=" . \IPS\Request::i()->langId ), 'deleted' );
	}
	
	/**
	 * Manual Upload Form
	 *
	 * @return void
	 */
	protected function upload()
	{
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->addMessage('languages_manual_install_warning');
		$form->add( new \IPS\Helpers\Form\Upload( 'lang_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ) ) );
		\IPS\Lang::localeField( $form );
		
		$activeTabContents = $form;
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['lang_upload'], $tempFile );
			
			/* Work out locale */
			if( isset( $values['lang_short_custom'] ) )
			{
				if ( !isset($values['lang_short']) OR $values['lang_short'] === 'x' )
				{
					$locale = $values['lang_short_custom'];
				}
				else
				{
					$locale = $values['lang_short'];
				}
			}
								
			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=import' )->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ), 'locale' => $locale ) )->csrf() );
		}
		
		/* Display */
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function form()
	{
		/* If we have no ID number, this is the add form, which is handled differently to the edit */
		if ( !\IPS\Request::i()->id )
		{			
			/* CREATE NEW */
			$max = \IPS\Db::i()->select( 'MAX(lang_order)', 'core_sys_lang' )->first();

			/* Build form */
			$form = new \IPS\Helpers\Form;
			$form->addMessage('languages_create_blurb');
			$lang = new \IPS\Lang( array( 'lang_short' => 'en_US' ) );
			$lang->form( $form );
			
			/* Handle submissions */
			if ( $values = $form->values() )
			{
				/* Find the correct locale */
				if ( !isset($values['lang_short']) OR $values['lang_short'] === 'x' )
				{
					$values['lang_short'] = $values['lang_short_custom'];
				}
				unset( $values['lang_short_custom'] );

				/* reset default language if we want this to be default */
				if( isset( $values['lang_default'] ) and $values['lang_default'] )
				{
					\IPS\Db::i()->update( 'core_sys_lang', array( 'lang_default' => 0 ) );
				}

				/* Add "UTF8" if we can */
				$currentLocale = setlocale( LC_ALL, '0' );

				foreach ( array( "{$values['lang_short']}.UTF-8", "{$values['lang_short']}.UTF8" ) as $l )
				{
					$test = setlocale( LC_ALL, $l );
					if ( $test !== FALSE )
					{
						$values['lang_short'] = $l;
						break;
					}
				}

				foreach( explode( ";", $currentLocale ) as $locale )
				{
					$parts = explode( "=", $locale );
					if( \in_array( $parts[0], array( 'LC_ALL', 'LC_COLLATE', 'LC_CTYPE', 'LC_MONETARY', 'LC_NUMERIC', 'LC_TIME' ) ) )
					{
						setlocale( \constant( $parts[0] ), $parts[1] );
					}
				}
				
				/* Insert the actual language */
				$values['lang_order'] = ++$max;
				$insertId = \IPS\Db::i()->insert( 'core_sys_lang', $values );
				
				/* Copy over language strings */
				$default = \IPS\Lang::defaultLanguage();
				$prefix = \IPS\Db::i()->prefix;
				$defaultStmt = \IPS\Db::i()->prepare( "INSERT INTO `{$prefix}core_sys_lang_words` ( `lang_id`, `word_app`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export` ) SELECT {$insertId} AS `lang_id`, `word_app`, `word_key`, `word_default`, NULL AS `word_custom`, `word_default_version`, NULL AS `word_custom_version`, `word_js`, `word_export` FROM `{$prefix}core_sys_lang_words` WHERE `lang_id`={$default} AND `word_export`=1" );
				$defaultStmt->execute();
				$customStmt = \IPS\Db::i()->prepare( "INSERT INTO `{$prefix}core_sys_lang_words` ( `lang_id`, `word_app`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export`, `word_is_custom` ) SELECT {$insertId} AS `lang_id`, `word_app`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export`, `word_is_custom` FROM `{$prefix}core_sys_lang_words` WHERE `lang_id`={$default} AND `word_export`=0" );
				$customStmt->execute();

				unset( \IPS\Data\Store::i()->languages );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();

				/* Log */
				\IPS\Session::i()->log( 'acplogs__lang_created', array( $values['lang_title'] => FALSE ) );
				
				/* Redirect */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ), 'saved' );
			}
			
			/* Display */
			\IPS\Output::i()->output = $form;
		}
		/* If it's an edit, we can just let the node controller handle it */
		else
		{
			return parent::form();
		}
	}
	
	/**
	 * Toggle Enabled/Disable
	 *
	 * @return	void
	 */
	protected function enableToggle()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Load Language */
		try
		{
			$language = \IPS\Lang::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '3C126/1', 404, '' );
		}
		/* Check we're not locked */
		if( $language->_locked or !$language->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_enable', '2C126/2', 403, '' );
		}
		
		/* Check if any members are using this */
		if ( !\IPS\Request::i()->status )
		{
			$count = \IPS\Db::i()->select( 'count(*)', 'core_members', array( 'language=?', $language->_id ) )->first();
			if ( $count )
			{
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( false, 500 );
				}
				else
				{
					$options = array();
					foreach ( \IPS\Lang::languages() as $lang )
					{
						if ( $lang->id != $language->_id )
						{
							$options[ $lang->id ] = $lang->title;
						}
					}
					
					$form = new \IPS\Helpers\Form;
					$form->add( new \IPS\Helpers\Form\Select( 'lang_change_to', \IPS\Lang::defaultLanguage(), TRUE, array( 'options' => $options ) ) );
					
					if ( $values = $form->values() )
					{
						\IPS\Db::i()->update( 'core_members', array( 'language' => $values['lang_change_to'] ), array( 'language=?', $language->_id ) );
					}
					else
					{
						\IPS\Output::i()->output = $form;
						return;
					}
				}
			}
		}
		
		/* Do it */
		\IPS\Db::i()->update( 'core_sys_lang', array( 'lang_enabled' => (bool) \IPS\Request::i()->status ), array( 'lang_id=?', $language->_id ) );
		unset( \IPS\Data\Store::i()->languages );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		/* Update the essential cookie name list */
		unset( \IPS\Data\Store::i()->essentialCookieNames );

		/* Log */
		if ( \IPS\Request::i()->status )
		{
			\IPS\Session::i()->log( 'acplog__node_enabled', array( 'menu__core_languages_languages' => TRUE, $language->title => FALSE ) );
		}
		else
		{
			\IPS\Session::i()->log( 'acplog__node_disabled', array( 'menu__core_languages_languages' => TRUE, $language->title => FALSE ) );
		}
		
		/* Redirect */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( (bool) \IPS\Request::i()->status );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ), 'saved' );
		}
	}
	
	/**
	 * Visual Language Editor
	 *
	 * @return	void
	 */
	protected function vle()
	{
		if( \IPS\IN_DEV )
		{
			\IPS\Member::loggedIn()->language()->words['lang_vle_editor_warning']	= \IPS\Member::loggedIn()->language()->addToStack( 'dev_lang_vle_editor_warn', FALSE );
		}

		$form = new \IPS\Helpers\Form();
		$form->add( new \IPS\Helpers\Form\YesNo( 'lang_vle_editor', ( isset( \IPS\Request::i()->cookie['vle_editor'] ) and \IPS\Request::i()->cookie['vle_editor'] ) and !\IPS\IN_DEV, FALSE, array( 'disabled' => \IPS\IN_DEV ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'lang_vle_keys', isset( \IPS\Request::i()->cookie['vle_keys'] ) and \IPS\Request::i()->cookie['vle_keys'] ) );
		
		if ( $values = $form->values() )
		{
			foreach ( array( 'vle_editor', 'vle_keys' ) as $k )
			{
				if ( $values[ 'lang_' . $k ] )
				{
					\IPS\Request::i()->setCookie( $k, 1 );
				}
				elseif ( isset( \IPS\Request::i()->cookie[ $k ] ) )
				{
					\IPS\Request::i()->setCookie( $k, 0 );
				}
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ) );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Language Setting
	 *
	 * @return	void
	 */
	protected function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'lang_auto_detect', \IPS\Settings::i()->lang_auto_detect, TRUE ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplog__language_settings_edited' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ) );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Translate
	 *
	 * @return	void
	 */
	protected function translate()
	{
		if ( \IPS\Lang::vleActive() )
		{
			\IPS\Output::i()->error( 'no_translate_with_vle', '1C126/8', 403, '' );
		}

		try
		{
			$lang = \IPS\Lang::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C126/3', 404, '' );
		}
		
		$where = array(
			array( 'lang_id=? AND (word_export=1 OR word_is_custom=1)', \IPS\Request::i()->id ),
		);
		
		$table = new \IPS\Helpers\Table\Db( 'core_sys_lang_words', \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=translate&id=' . \IPS\Request::i()->id ), $where );
		$table->langPrefix = 'lang_';
		$table->classes = array( 'cTranslateTable' );
		$table->rowClasses = array( 'word_default' => array( 'ipsTable_wrap' ) );

		$table->include = array( 'word_app', 'word_plugin', 'word_theme', 'word_key', 'word_default', 'word_custom' );

		$table->parsers = array(
			'word_app' => function( $val, $row )
			{
				try
				{
					return \IPS\Application::load( $row['word_app'] )->_title;
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( \IPS\Member::loggedIn()->language()->addToStack('translate_na'), array( 'ipsBadge', 'ipsBadge_neutral' ) );
				}
				catch ( \InvalidArgumentException $e )
				{
					return \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( \IPS\Member::loggedIn()->language()->addToStack('translate_na'), array( 'ipsBadge', 'ipsBadge_neutral' ) );
				}
				catch ( \UnexpectedValueException $e )
				{
					return \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( \IPS\Member::loggedIn()->language()->addToStack('translate_na'), array( 'ipsBadge', 'ipsBadge_neutral' ) );
				}
			},
			'word_plugin' => function( $val, $row )
			{
				try
				{
					return \IPS\Plugin::load( $row['word_plugin'] )->name;
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( \IPS\Member::loggedIn()->language()->addToStack('translate_na'), array( 'ipsBadge', 'ipsBadge_neutral' ) );
				}
			},
			'word_theme' => function( $val, $row )
			{
				try
				{
					return \IPS\Theme::load( $row['word_theme'] )->_title;
				}
				catch ( \OutOfRangeException $e )
				{
					return \IPS\Theme::i()->getTemplate( 'global' )->shortMessage( \IPS\Member::loggedIn()->language()->addToStack('translate_na'), array( 'ipsBadge', 'ipsBadge_neutral' ) );
				}
			},
			'word_custom'	=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'customization' )->langString( $val, $row['word_key'], $row['lang_id'], $row['word_js'] );
			},
		);
		
		$table->sortBy = $table->sortBy ?: 'word_key';
		$table->sortDirection = $table->sortDirection ?: 'asc';

		$table->quickSearch = array( array( 'word_default', 'word_key' ), 'word_default' );
		$table->advancedSearch = array(
			'word_key'		=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'word_default'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'word_custom'	=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'word_app'		=> array( \IPS\Helpers\Table\SEARCH_NODE, array( 'class' => 'IPS\Application', 'subnodes' => FALSE ) ),
			'word_plugin'	=> array( \IPS\Helpers\Table\SEARCH_NODE, array( 'class' => 'IPS\Plugin', 'subnodes' => FALSE ) )
		);
		
		$table->filters = array(
			'lang_filter_translated'	=> 'word_custom IS NOT NULL',
			'lang_filter_untranslated'	=> 'word_custom IS NULL',
			'lang_filter_out_of_date'	=> 'word_custom IS NOT NULL AND word_custom_version<word_default_version',
			'lang_filter_admin_custom'	=> 'word_is_custom!=0'
		);
		
		$table->widths = array( 'word_key' => 15, 'word_default' => 35, 'word_custom' => 50 );
		$table->rowButtons = function( $row ) {
			if ( $row['word_is_custom'] )
			{
				return array(
					'delete' => array(
						'icon'		=> 'times',
						'title'		=> 'delete',
						'link'		=> \IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=deleteWord&key={$row['word_key']}&langId=" . \IPS\Request::i()->id )->csrf(),
						'data'		=> array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack('delete_word_all_languages') )
					)
				);
			}
			else
			{
				return array();
			}
		};
		
		\IPS\Member::loggedIn()->language()->words['lang_word_custom'] = $lang->title;
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/languages.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_core.js' ) );
		\IPS\Output::i()->title = $lang->title;
		\IPS\Output::i()->output = (string) $table;
	}
	
	/**
	 * Translate Word
	 *
	 * @return	void
	 */
	protected function translateWord()
	{
		try
		{
			$lang = \IPS\Lang::load( \IPS\Request::i()->lang );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C126/4', 404, '' );
		}
		
		$word = \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'lang_id=? AND word_key=? AND word_js=?', \IPS\Request::i()->lang, \IPS\Request::i()->key, (int) \IPS\Request::i()->js ) )->first();
		
		$form = new \IPS\Helpers\Form;
		$form->addDummy( 'lang_word_default', htmlspecialchars( $word['word_default'],ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'lang_word_custom', $word['word_custom'] ) );
		
		if ( $values = $form->values() )
		{
			$version = 0;
			try
			{
				if ( $word['word_app'] )
				{
					$version = \IPS\Application::load( $word['word_app'] )->long_version;
				}
				elseif ( $word['word_plugin'] )
				{
					$version = \IPS\Plugin::load( $word['word_plugin'] )->version_long;
				}
				elseif ( $word['word_theme'] )
				{
					$version = \IPS\Theme::load( $word['word_theme'] )->long_version;
				}
			}
			catch ( \OutOfRangeException $e ) { }
			
			\IPS\Db::i()->update( 'core_sys_lang_words', array( 'word_custom' => ( $values['lang_word_custom'] ? urldecode( $values['lang_word_custom'] ) : NULL ), 'word_custom_version' => ( $values['lang_word_custom'] ? $version : NULL ) ), array( 'word_id=?', $word['word_id'] ) );
			\IPS\Session::i()->log( 'acplogs__lang_translate', array( $word['word_key'] => FALSE, $lang->title => FALSE ) );
			
			if ( $word['word_js'] )
			{
				\IPS\Output::clearJsFiles( 'global', 'root', 'js_lang_' . $word['lang_id'] . '.js' );
			}

			if ( $word['word_key'] === '_list_format_' )
			{
				unset( \IPS\Data\Store::i()->listFormats );
			}
			
			if ( \substr( $word['word_key'], 0, 10 ) === 'num_short_' )
			{
				unset( \IPS\Data\Store::i()->shortFormats );
			}

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array() );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=translate&id=' . $word['lang_id'] ) );
			}
		}
		
		\IPS\Member::loggedIn()->language()->words['lang_word_custom'] = $lang->title;
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Copy
	 *
	 * @return	void
	 */
	protected function copy()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=copy&id=" . \intval( \IPS\Request::i()->id ) )->csrf(),
			function( $data )
			{
				if ( !\is_array( $data ) )
				{
					$lang = \IPS\Db::i()->select( '*', 'core_sys_lang',  array( 'lang_id=?', \IPS\Request::i()->id ) )->first();
					unset( $lang['lang_id'] );

					$lang['lang_title'] = $lang['lang_title'] . ' ' . \IPS\Member::loggedIn()->language()->get('copy_noun');
					$lang['lang_default'] = FALSE;
					$lang['lang_marketplace_id'] = NULL;

					$insertId = \IPS\Db::i()->insert( 'core_sys_lang', $lang );
					
					\IPS\Session::i()->log( 'acplog__node_copied', array( 'menu__core_languages_languages' => TRUE, $lang['lang_title'] => FALSE ) );
					
					$words = \IPS\Db::i()->select( 'count(*)', 'core_sys_lang_words', array( 'lang_id=?', \IPS\Request::i()->id ) )->first();
					
					return array( array( 'id' => $insertId, 'done' => 0, 'total' => $words ), \IPS\Member::loggedIn()->language()->addToStack('copying'), 1 );
				}
				else
				{
					$words = \IPS\Db::i()->select(  '*', 'core_sys_lang_words', array( 'lang_id=?', \IPS\Request::i()->id ), 'word_id', array( $data['done'], 100 ) );
					if ( !\count( $words  ) )
					{
						return NULL;
					}
					else
					{
						foreach ( $words as $row )
						{
							unset( $row['word_id'] );
							$row['lang_id'] = $data['id'];
							\IPS\Db::i()->replace( 'core_sys_lang_words', $row );
						}
					}
					
					
					$data['done'] += 100;
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('copying'), ( 100 / $data['total'] * $data['done'] ) );
				}
			},
			function()
			{
				unset( \IPS\Data\Store::i()->languages );
				unset( \IPS\Data\Store::i()->listFormats );

				/* Update the essential cookie name list */
				unset( \IPS\Data\Store::i()->essentialCookieNames );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ) );
			}
		);
	}
	
	/**
	 * Download
	 *
	 * @return	void
	 */
	protected function download()
	{
		/* Load language */
		try
		{
			$lang = \IPS\Lang::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C126/5', 404, '' );
		}

		$form = new \IPS\Helpers\Form( 'form', 'langauge_export_button' );

		$form->add( new \IPS\Helpers\Form\Text( 'language_export_author_name', $lang->author_name, false ) );
		$form->add( new \IPS\Helpers\Form\Url( 'language_export_author_url', $lang->author_url, false ) );
		$form->add( new \IPS\Helpers\Form\Url( 'language_export_update_check', $lang->update_url, false ) );
		$form->add( new \IPS\Helpers\Form\Text( 'language_export_version', $lang->version, true, array( 'placeholder' => '1.0.0' ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'language_export_long_version', $lang->version_long ?: 10000, true ) );

		if ( $values = $form->values() )
		{
			$lang->author_name		= $values['language_export_author_name'];
			$lang->author_url		= (string) $values['language_export_author_url'];
			$lang->update_url		= (string) $values['language_export_update_check'];
			$lang->version			= $values['language_export_version'];
			$lang->version_long		= (int) $values['language_export_long_version'];
			$lang->save();

			$count = 0;
			try
			{
				$count = \IPS\Db::i()->select( 'COUNT(word_id)', 'core_sys_lang_words', array( 'lang_id=? AND word_export=1 AND word_custom IS NOT NULL', $lang->id ), 'word_id', NULL, 'word_id' )->first();
			}
			catch ( \UnderflowException $e ) {}

			if ( $count < 1 )
			{
				\IPS\Output::i()->error( 'core_lang_download_empty', '1C126/7', 404, '' );
			}

			/* Init */
			$xml = new \XMLWriter;
			$xml->openMemory();
			$xml->setIndent( TRUE );
			$xml->startDocument( '1.0', 'UTF-8' );

			/* Root tag */
			$xml->startElement( 'language' );
			$xml->startAttribute( 'name' );
			$xml->text( $lang->title );
			$xml->endAttribute();
			$xml->startAttribute( 'rtl' );
			$xml->text( $lang->isrtl );
			$xml->endAttribute();

			$xml->startAttribute( 'author_name' );
			$xml->text( $lang->author_name );
			$xml->endAttribute();
			$xml->startAttribute( 'author_url' );
			$xml->text( $lang->author_url );
			$xml->endAttribute();

			$xml->startAttribute( 'version' );
			$xml->text( $lang->version );
			$xml->endAttribute();
			$xml->startAttribute( 'long_version' );
			$xml->text( $lang->version_long );
			$xml->endAttribute();

			$xml->startAttribute( 'update_check' );
			$xml->text( $lang->update_url );
			$xml->endAttribute();

			/* Loop applications */
			foreach ( \IPS\Application::applications() as $app )
			{
				/* Initiate the <app> tag */
				$xml->startElement( 'app' );

				/* Set key */
				$xml->startAttribute( 'key' );
				$xml->text( $app->directory );
				$xml->endAttribute();

				/* Set version */
				$xml->startAttribute( 'version' );
				$xml->text( $app->long_version );
				$xml->endAttribute();

				/* Add words */
				foreach ( \IPS\Db::i()->select( '*', 'core_sys_lang_words', array( 'lang_id=? AND word_app=? AND word_export=1 AND word_custom IS NOT NULL', $lang->id, $app->directory ), 'word_id' ) as $row )
				{
					/* Start */
					$xml->startElement( 'word' );

					/* Add key */
					$xml->startAttribute( 'key' );
					$xml->text( $row['word_key'] );
					$xml->endAttribute();

					/* Is this a javascript string? */
					$xml->startAttribute( 'js' );
					$xml->text( $row['word_js'] );
					$xml->endAttribute();

					/* Write value */
					if ( preg_match( '/<|>|&/', $row['word_custom'] ) )
					{
						$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $row['word_custom'] ) );
					}
					else
					{
						$xml->text( $row['word_custom'] );
					}

					/* End */
					$xml->endElement();
				}

				/* </app> */
				$xml->endElement();
			}

			/* Finish */
			$xml->endDocument();
			\IPS\Output::i()->sendOutput( $xml->outputMemory(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', $lang->title . " {$lang->version}.xml" ) ), FALSE, FALSE, FALSE );
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Member::loggedIn()->language()->addToStack('language_export_title', FALSE, array( 'sprintf' => array( $lang->title ) ) ), $form, FALSE );
	}
	
	/**
	 * Upload new version
	 *
	 * @return	void
	 */
	public function uploadNewVersion()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'lang_words' );

		$language = \IPS\Lang::load( \IPS\Request::i()->id );

		if( $language->marketplace_id )
		{
			\IPS\Output::i()->error( 'app_upload_marketplace_only', '2C126/B', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Upload( 'lang_upload', NULL, TRUE, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['lang_upload'], $tempFile );
								
			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=import' )->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ), 'into' => \IPS\Request::i()->id ) )->csrf() );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Import from upload
	 *
	 * @return	void
	 */
	public function import()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3C126/6', 500, '' );
		}
		
		$url = \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages&do=import' )->setQueryString( array( 'file' => \IPS\Request::i()->file, 'key' => \IPS\Request::i()->key, 'locale' => \IPS\Request::i()->locale ) )->csrf();
		if ( isset( \IPS\Request::i()->into ) )
		{
			$url = $url->setQueryString( 'into', \IPS\Request::i()->into );
		}
		if ( isset( \IPS\Request::i()->marketplace ) )
		{
			$url = $url->setQueryString( 'marketplace', \IPS\Request::i()->marketplace );
		}
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			$url,
			function( $data )
			{
				/* Open XML file */
				$xml = \IPS\Xml\XMLReader::safeOpen( \IPS\Request::i()->file );
				$xml->read();
				
				/* If this is the first batch, create the language record */
				if ( !\is_array( $data ) )
				{
					/* Create the record */
					if ( isset( \IPS\Request::i()->into ) )
					{
						$insertId = \IPS\Request::i()->into;

						\IPS\Db::i()->update( 'core_sys_lang', array(
							'lang_title'		=> $xml->getAttribute('name'),
							'lang_isrtl'		=> $xml->getAttribute('rtl'),
							'lang_version'		=> $xml->getAttribute('version'),
							'lang_version_long'	=> $xml->getAttribute('long_version'),
							'lang_author_name'	=> $xml->getAttribute('author_name'),
							'lang_author_url'	=> $xml->getAttribute('author_url'),
							'lang_update_url'	=> $xml->getAttribute('update_check')
						),
						array( 'lang_id=?', $insertId) );
					}
					else
					{
						/* Add "UTF8" if we can */
						$currentLocale = setlocale( LC_ALL, '0' );

						foreach ( array( \IPS\Request::i()->locale . ".UTF-8", \IPS\Request::i()->locale . ".UTF8" ) as $l )
						{
							$test = setlocale( LC_ALL, $l );
							if ( $test !== FALSE )
							{
								\IPS\Request::i()->locale = $l;
								break;
							}
						}

						foreach( explode( ";", $currentLocale ) as $locale )
						{
							$parts = explode( "=", $locale );
							if( \in_array( $parts[0], array( 'LC_ALL', 'LC_COLLATE', 'LC_CTYPE', 'LC_MONETARY', 'LC_NUMERIC', 'LC_TIME' ) ) )
							{
								setlocale( \constant( $parts[0] ), $parts[1] );
							}
						}

						/* Insert the language pack record */
						$max = \IPS\Db::i()->select( 'MAX(lang_order)', 'core_sys_lang' )->first();
						$insertId = \IPS\Db::i()->insert( 'core_sys_lang', array(
							'lang_short'		=> \IPS\Request::i()->locale,
							'lang_title'		=> $xml->getAttribute('name'),
							'lang_isrtl'		=> $xml->getAttribute('rtl'),
							'lang_order'		=> $max + 1,
							'lang_version'		=> $xml->getAttribute('version'),
							'lang_version_long'	=> $xml->getAttribute('long_version'),
							'lang_author_name'	=> $xml->getAttribute('author_name'),
							'lang_author_url'	=> $xml->getAttribute('author_url'),
							'lang_update_url'	=> $xml->getAttribute('update_check'),
							'lang_marketplace_id' => \IPS\Request::i()->marketplace ? (int) \IPS\Request::i()->marketplace : NULL
						) );
					
						/* Copy over default language strings */
						$default = \IPS\Lang::defaultLanguage();
						$prefix = \IPS\Db::i()->prefix;
						$defaultStmt = \IPS\Db::i()->prepare( "INSERT INTO `{$prefix}core_sys_lang_words` ( `lang_id`, `word_app`, `word_plugin`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export` ) SELECT {$insertId} AS `lang_id`, `word_app`, `word_plugin`, `word_key`, `word_default`, NULL AS `word_custom`, `word_default_version`, NULL AS `word_custom_version`, `word_js`, `word_export` FROM `{$prefix}core_sys_lang_words` WHERE `lang_id`={$default} AND `word_export`=1" );
						$defaultStmt->execute();
						$customStmt = \IPS\Db::i()->prepare( "INSERT INTO `{$prefix}core_sys_lang_words` ( `lang_id`, `word_app`, `word_plugin`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export` ) SELECT {$insertId} AS `lang_id`, `word_app`, `word_plugin`, `word_key`, `word_default`, `word_custom`, `word_default_version`, `word_custom_version`, `word_js`, `word_export` FROM `{$prefix}core_sys_lang_words` WHERE `lang_id`={$default} AND `word_export`=0" );
						$customStmt->execute();
					}
					
					/* Log */
					\IPS\Session::i()->log( 'acplogs__lang_created', array( $xml->getAttribute('name') => FALSE ) );
					
					/* Start importing */
					$data = array( 'apps' => array(), 'id' => $insertId );
					return array( $data, \IPS\Member::loggedIn()->language()->get('processing') );
				}

				/* Only import language strings from applications we have installed */
				$applications = array();
				foreach( \IPS\Application::applications() as $app )
				{
					$applications[$app->directory] = $app->long_version;
				}
				
				/* Move to correct app */
				$appKey = NULL;
				$version = NULL;
				$xml->read();
				while ( $xml->read() )
				{
					$appKey = $xml->getAttribute('key');
					if ( !array_key_exists( $appKey, $data['apps'] ) AND array_key_exists( $appKey, $applications ) )
					{
						/* Get version */
						$version = $xml->getAttribute('version') ?: $applications[$appKey];
						
						/* Import */
						$xml->read();
						while ( $xml->read() and $xml->name == 'word' )
						{
							\IPS\Db::i()->insert( 'core_sys_lang_words', array(
								'word_app'				=> $appKey,
								'word_key'				=> $xml->getAttribute('key'),
								'lang_id'				=> $data['id'],
								'word_custom'			=> $xml->readString(),
								'word_custom_version'	=> $version,
								'word_js'				=> (int) $xml->getAttribute('js'),
								'word_export'			=> 1,
							), TRUE );
							$xml->next();
						}
						
						/* Done */
						$data['apps'][ $appKey ] = TRUE;
						return array( $data, \IPS\Member::loggedIn()->language()->get('processing') );
					}
					else
					{
						$xml->next();
					}
				}
							
				/* All done */
				return NULL;
			},
			function()
			{
				/* Clear language caches, including update counter */
				unset( \IPS\Data\Store::i()->languages, \IPS\Data\Store::i()->listFormats, \IPS\Data\Store::i()->updatecount_languages );

				/* Clear guest page caches */
				\IPS\Data\Cache::i()->clearAll();

				/* Update the essential cookie name list */
				unset( \IPS\Data\Store::i()->essentialCookieNames );

				@unlink( \IPS\Request::i()->file );

				/* Redirect back to marketplace if it was installed from there. */
				if( \IPS\Request::i()->marketplace )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . \IPS\Request::i()->marketplace ), 'lang_installed' );
				}

				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ) );
			}
		);
	}
	
	/**
	 * Developer Import
	 *
	 * @return	void
	 */
	protected function devimport()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( "app=core&module=languages&controller=languages&do=devimport&id=" . \intval( \IPS\Request::i()->id ) )->csrf(),
			function ( $data )
			{
				if ( !\is_array( $data ) )
				{
					\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'lang_id=? AND word_export=1', \IPS\Request::i()->id ) );
					return array( array(), \IPS\Member::loggedIn()->language()->addToStack('lang_dev_importing'), 1 );
				}
								
				$done = FALSE;
				foreach ( \IPS\Application::applications() as $appKey => $app )
				{
					if ( !array_key_exists( $appKey, $data ) )
					{
						$words = array();
						$lang = array();
						require \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/lang.php";
						foreach ( $lang as $k => $v )
						{
							\IPS\Db::i()->replace( 'core_sys_lang_words', array(
								'lang_id'				=> \IPS\Request::i()->id,
								'word_app'				=> $app->directory,
								'word_key'				=> $k,
								'word_default'			=> $v,
								'word_custom'			=> NULL,
								'word_default_version'	=> $app->long_version,
								'word_custom_version'	=> NULL,
								'word_js'				=> 0,
								'word_export'			=> 1,
							) );
						}
												
						$data[ $appKey ] = 0;
						$done = TRUE;
						break;
					}
					elseif ( $data[ $appKey ] === 0 )
					{
						$words = array();
						$lang = array();
						require \IPS\ROOT_PATH . "/applications/{$app->directory}/dev/jslang.php";
						foreach ( $lang as $k => $v )
						{
							\IPS\Db::i()->replace( 'core_sys_lang_words', array(
								'lang_id'				=> \IPS\Request::i()->id,
								'word_app'				=> $app->directory,
								'word_key'				=> $k,
								'word_default'			=> $v,
								'word_custom'			=> NULL,
								'word_default_version'	=> $app->long_version,
								'word_custom_version'	=> NULL,
								'word_js'				=> 1,
								'word_export'			=> 1,
							) );
						}

						$data[ $appKey ] = 1;
						$done = TRUE;
						break;
					}
				}
				
				if ( $done === FALSE )
				{
					return NULL;
				}
				
				return array( $data, \IPS\Member::loggedIn()->language()->addToStack('lang_dev_importing'), ( 100 / ( \count( \IPS\Application::applications() ) * 2 ) * \count( $data ) ) );
			},
			function ()
			{
				unset( \IPS\Data\Store::i()->languages );
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ), 'saved' );
			}
		);
	}
	
	/**
	 * Set Members
	 *
	 * @return	void
	 */
	public function setMembers()
	{
		$form = new \IPS\Helpers\Form;
		$form->hiddenvalues['id'] = \IPS\Request::i()->id;
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'member_reset_where', '*', TRUE, array( 'options' => \IPS\Member\Group::groups( TRUE, FALSE ), 'multiple' => TRUE, 'parse' => 'normal', 'unlimited' => '*', 'unlimitedLang' => 'all', 'impliedUnlimited' => TRUE ) ) );
		
		if ( $values = $form->values() )
		{
			if ( $values['member_reset_where'] === '*' )
			{
				$where = NULL;
			}
			else
			{
				$where = \IPS\Db::i()->in( 'member_group_id', $values['member_reset_where'] );
			}
			
			if ( $where )
			{
				\IPS\Db::i()->update( 'core_members', array( 'language' => \IPS\Request::i()->id ), $where );
			}
			else
			{
				\IPS\Member::updateAllMembers( array( 'language' => \IPS\Request::i()->id ) );
			}
			
			\IPS\Session::i()->log( 'acplogs__members_language_reset', array( \IPS\Lang::load( \IPS\Request::i()->id ?: \IPS\Lang::defaultLanguage()  )->title  => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=languages&controller=languages' ), 'reset' );
		}

		\IPS\Output::i()->output = $form;
	}
}
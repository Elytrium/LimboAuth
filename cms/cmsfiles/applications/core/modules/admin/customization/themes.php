<?php
/**
 * @brief		themes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Apr 2013
 */

namespace IPS\core\modules\admin\customization;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * themes
 */
class _themes extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Theme';
	
	/**
	 * @brief	If true, will prevent any item from being moved out of its current parent, only allowing them to be reordered within their current parent
	 */
	protected $lockParents = TRUE;
	
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_sets_manage' );
		
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Theme::designersModeEnabled() and ! isset( \IPS\Request::i()->root ) )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack('theme_designer_mode_warning'), 'information', NULL, FALSE );
		}

		/* Add a button for designer's mode */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_designers_mode' ) and !\IPS\NO_WRITES and !\IPS\CIC )
		{
			\IPS\Output::i()->sidebar['actions']['designersmode'] = array(
				'title'		=> ( \IPS\Theme::designersModeEnabled() ) ? 'theme_designers_mode_on_title' : 'theme_designers_mode_title',
				'icon'		=> 'paint-brush',
				'class'     => ( \IPS\Theme::designersModeEnabled() ) ? 'ipsButton_negative' : NULL,
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmode' ),
				'data'	    => array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('theme_designers_mode_title') )
			);
		}
				
		\IPS\Output::i()->sidebar['actions']['add'] = array(
			'primary'	=> true,
			'icon'		=> 'plus',
			'title'		=> 'add',
			'link'		=> \IPS\Http\Url::internal( ( \IPS\DEMO_MODE ) ? 'app=core&module=customization&controller=themes&do=form&type=vse' : 'app=core&module=customization&controller=themes&do=add' ),
			'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('theme_set_add_button') )
		);
		

		parent::manage();
	}
	
	/**
	 * Allow overloading to change how the title is displayed in the tree
	 *
	 * @param	$node	\IPS\Node	Node
	 * @return string
	 */
	protected static function nodeTitle( $node )
	{
		return \IPS\Theme::i()->getTemplate('customization')->themeRowTitle( $node );
	}
	
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		return array();
	}

	/**
	 * Revert theme setting
	 *
	 * @return void
	 */
	public function revertThemeSetting()
	{
		\IPS\Session::i()->csrfCheck();
		
		$theme   = \IPS\Theme::load( \IPS\Request::i()->id );
		$setting = NULL;
		$value   = NULL;

		try
		{
			$themeSetting = \IPS\Db::i()->select( 'sc.*, sv.sv_value', array('core_theme_settings_fields', 'sc'), array( "sc_set_id=? AND sc_key=?", $theme->id, \IPS\Request::i()->key ) )
								->join( array('core_theme_settings_values', 'sv'), 'sv.sv_id=sc.sc_id' )
								->first();
		}
		catch( \UnderflowException $e ) { }

		foreach( $theme->parents() as $parent )
		{
			try
			{
				$setting = \IPS\Db::i()->select( 'sc.*, sv.sv_value', array('core_theme_settings_fields', 'sc'), array( "sc_set_id=? AND sc_key=?", $parent->id, \IPS\Request::i()->key ) )
							->join( array('core_theme_settings_values', 'sv'), 'sv.sv_id=sc.sc_id' )
							->first();

				if ( $setting['sv_value'] !== $themeSetting['sv_value'] )
				{
					/* Value different from theme set we're reverting from? use this, then */
					$value = $setting['sv_value'];
					break;
				}
			}
			catch( \UnderflowException $e ) { }
		}

		if ( $value === NULL )
		{
			/* Just use the default */
			$value = $themeSetting['sc_default'];
		}
		
		\IPS\Session::i()->log( 'acplogs__theme_setting_deleted', array( $themeSetting['sc_key'] => FALSE ) );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		if ( \IPS\Request::i()->isAjax() )
		{
			/* Just return the value */
			\IPS\Output::i()->json( array( 'value' => $value ) );
		}
		else
		{
			/* Update */
			\IPS\Db::i()->update( 'core_theme_settings_values', array( 'sv_value' => $value ), array( 'sv_id=?', $themeSetting['sc_id'] ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=form&id=' . \IPS\Request::i()->id ), 'completed' );
		}
	}

	/**
	 * Toggle designer's mode
	 *
	 * @return void
	 */
	public function designersmode()
	{
		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_designers_mode_title', \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->message( \IPS\Member::loggedIn()->language()->addToStack('theme_designer_mode_in_dev_warning'), 'information', NULL, FALSE ) );
			return;
		}
		
		if ( \IPS\NO_WRITES or \IPS\CIC )
		{
			\IPS\Output::i()->error( 'no_writes', '1C163/9', 403, '' );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'next' );
		$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack('theme_designers_mode_explain', FALSE, array( 'sprintf' => array( \IPS\ROOT_PATH, \IPS\Http\Url::ips('docs/designers_mode') ) ) ), 'ipsMessage ipsMessage_general', TRUE );

		if ( \IPS\Settings::i()->theme_designers_mode )
		{
			$form->add( new \IPS\Helpers\Form\YesNo('theme_designers_mode', TRUE, FALSE, array( 'togglesOff' => array( 'theme_designers_mode_sync', 'theme_designers_themes', 'theme_designers_mode_wipe' ) ) ) );
			$form->add( new \IPS\Helpers\Form\YesNo('theme_designers_mode_sync', TRUE, FALSE, array( 'togglesOn' => array( 'theme_designers_mode_wipe', 'theme_designers_themes' ) ), NULL, NULL, NULL, 'theme_designers_mode_sync' ) );
			$form->add( new \IPS\Helpers\Form\Node( 'theme_designers_themes', 0, FALSE, array( 'class' => '\IPS\Theme', 'multiple' => true, 'zeroVal' => 'theme_designers_themes_all' ), NULL, NULL, NULL, 'theme_designers_themes' ) );
			$form->add( new \IPS\Helpers\Form\YesNo('theme_designers_mode_wipe', FALSE, FALSE, array(), NULL, NULL, NULL, 'theme_designers_mode_wipe' ) );
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\YesNo('theme_designers_mode', \IPS\Settings::i()->theme_designers_mode, FALSE, array( 'togglesOn' => array( 'theme_designers_themes' ) ) ) );
		}

		if ( $values = $form->values() )
		{
			/* Switching on */
			if ( isset( $values['theme_designers_mode'] ) and $values['theme_designers_mode'] )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmodeon' )->csrf() );
			}
			else
			{
				if ( isset( $values['theme_designers_mode_sync'] ) AND $values['theme_designers_mode_sync'] )
				{
					\IPS\Data\Store::i()->designers_mode_theme_sync = ( $values['theme_designers_themes'] == 0 ) ? NULL : array_keys( $values['theme_designers_themes'] );
					\IPS\Data\Store::i()->designers_mode_theme_wipe = $values['theme_designers_mode_wipe'];

					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmodeoff' )->csrf() );
				}
				else
				{
					/* Toggle the setting */
					\IPS\Settings::i()->changeValues( array( 'theme_designers_mode' => 0 ) );

					/* Finished */
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=manage' ), 'saved' );
				}
			}
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_designers_mode_title', $form, FALSE );
	}

	/**
	 * Sync changes
	 *
	 * @return void
	 */
	public function designersmodeoff()
	{
		\IPS\Session::i()->csrfCheck();

		if ( \IPS\NO_WRITES or \IPS\CIC )
		{
			\IPS\Output::i()->error( 'no_writes', '1C163/B', 403, '' );
		}
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmodeoff' )->csrf(),
			function( $data )
			{
				/* Is this the first cycle? */
				if ( ! \is_array( $data ) )
				{
					$toImport = array();
					
					foreach( \IPS\Theme::themes() as $id => $theme )
					{
						if( !$theme->id )
						{
							continue;
						}

						if ( isset( \IPS\Data\Store::i()->designers_mode_theme_sync ) )
						{
							if ( \IPS\Data\Store::i()->designers_mode_theme_sync !== NULL and ! \in_array( $id, \IPS\Data\Store::i()->designers_mode_theme_sync ) )
							{
								continue;
							}
						}

						foreach( \IPS\Application::applications() as $app => $data )
						{
							$toImport[ $theme->id ][ $app ] = array( 'lang' => 'lang', 'html' => 'html', 'css' => 'css', 'resources' => 'resources' );
						}
					}
	
					/* Test extensions */
					$extensions = array();
					foreach ( \IPS\Application::allExtensions( 'core', 'DesignersMode', NULL, NULL, NULL, TRUE ) as $class )
					{
						$extensions[] = \get_class( $class );
					}

					/* Start importing */
					$data = array( 'toImport' => \count( $toImport ) ? true : false, 'extensions' => $extensions, 'extra' => array() );
					
					/* Could be too large for a simple URL */
					$_SESSION['theme_dev_import'] = $toImport;

					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}

				if ( \count( $data['extensions'] ) )
				{
					/* Do this first */
					$array = $data['extensions'];
					$class = array_shift( $array ); # Don't remove the value from $data array

					$ext = new $class;

					$result = $ext->off( $data['extra'] );

					if( $result === TRUE )
					{
						/* We're done */
						$data['extensions'] = array_diff( $data['extensions'], array( $class ) );
					}
					else
					{
						/* Some data was returned, so lets store that in extra and allow a redirect which will hit this extension again */
						$data['extra'] = $result;
					}

					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('theme_dev_import_apps') );
				}
				else if ( $data['toImport'] )
				{
					$toImport = $_SESSION['theme_dev_import'];
					
					reset( $toImport );
					$themeId = key( $toImport );
					$theme   = \IPS\Theme\Advanced\Theme::load( $themeId );
					$app     = key( $toImport[ $themeId ] );
					$type    = array_shift( $toImport[ $themeId ][ $app ] );

					if( !\count( $toImport[ $themeId ][ $app ] ) )
					{
						unset( $toImport[ $themeId ][ $app ] );
					}

					switch( $type )
					{
						case 'lang':
							if ( file_exists( \IPS\ROOT_PATH . "/themes/{$themeId}/lang.php" ) )
							{
								\IPS\Db::i()->delete( 'core_sys_lang_words', array( 'word_theme=?', $themeId ) );
								
								$languageIds = array_keys( \IPS\Lang::languages() );
								$languageInserts = array();
								$seen = array();
								require \IPS\ROOT_PATH . "/themes/{$themeId}/lang.php";
								
								foreach ( $lang as $k => $v )
								{
									if ( isset( $seen[ $k ] ) )
									{
										continue;
									}
									
									$seen[ $k ] = true;
									
									if ( mb_substr( $k, 0, 17 ) === 'theme_custom_tab_' )
									{
										\IPS\Lang::deleteCustom( 'core', $k );
									}
									
									foreach ( $languageIds as $langId )
									{
										\IPS\Db::i()->insert( 'core_sys_lang_words', array(
											'lang_id'				=> $langId,
											'word_app'				=> NULL,
											'word_plugin'			=> NULL,
											'word_theme'			=> $themeId,
											'word_key'				=> $k,
											'word_default'			=> $v,
											'word_custom'			=> NULL,
											'word_default_version'	=> $theme->long_version,
											'word_custom_version'	=> NULL,
											'word_js'				=> FALSE,
											'word_export'			=> TRUE
										) );
									}
								}
							}
						break;
						case 'html':
							$theme->importDevHtml( $app, $themeId );
						break;
						case 'css':
							$theme->importDevCss( $app, $themeId );
						break;
						case 'resources':
							$theme->importDevResources( $app, $themeId );
						break;
					}

					if ( ! \count( $toImport[ $themeId ] ) )
					{
						unset( $toImport[ $themeId ] );
					}

					if ( ! \count( $toImport ) )
					{
						$data['toImport'] = false;
					}

					$_SESSION['theme_dev_import'] = $toImport;

					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('theme_dev_import', FALSE, array( 'sprintf' => array( $theme->_title, mb_ucfirst( $app ) ) ) ) );
				}
				else
				{
					/* All Done.. */
					return null;
				}
			},
			function()
			{
				/* Remove theme directory completely */
				if ( isset( \IPS\Data\Store::i()->designers_mode_theme_wipe ) and \IPS\Data\Store::i()->designers_mode_theme_wipe )
				{
					if ( isset( \IPS\Data\Store::i()->designers_mode_theme_sync ) )
					{
						if ( \IPS\Data\Store::i()->designers_mode_theme_sync === NULL )
						{
							\IPS\Theme\Advanced\Theme::removeThemeDirectory( \IPS\ROOT_PATH . "/themes" );
						}
						else if ( \is_array( \IPS\Data\Store::i()->designers_mode_theme_sync ) )
						{
							foreach( \IPS\Data\Store::i()->designers_mode_theme_sync as $id )
							{
								\IPS\Theme\Advanced\Theme::removeThemeDirectory( \IPS\ROOT_PATH . "/themes/" . $id );
							}
						}
					}
				}
				
				\IPS\Theme::deleteCompiledResources();
				\IPS\Theme::deleteCompiledCss();
				\IPS\Theme::deleteCompiledTemplate();

				unset( $_SESSION['theme_dev_import'] );

				/* Toggle the setting */
				\IPS\Settings::i()->changeValues( array( 'theme_designers_mode' => 0 ) );

				/* Finished */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=manage' ), 'completed' );
			}
		);
	}

	/**
	 * Build the designer mode files
	 *
	 * @return void
	 */
	public function designersmodeon()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\NO_WRITES or \IPS\CIC )
		{
			\IPS\Output::i()->error( 'no_writes', '1C163/A', 403, '' );
		}
		
		/* Nothing to build, its all up to date */
		$needsBuilding = \IPS\Theme\Advanced\Theme::getToBuild();

		if ( ! $needsBuilding )
		{
			foreach ( \IPS\Application::allExtensions( 'core', 'DesignersMode', NULL, NULL, NULL, TRUE ) as $class )
			{
				if ( $class->toBuild() === TRUE )
				{
					$needsBuilding = TRUE;
					break;
				}
			}
		}

		if ( isset( \IPS\Settings::i()->theme_designers_mode ) and \IPS\Settings::i()->theme_designers_mode )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=manage' ), 'completed' );
			exit();
		}

		if ( ! $needsBuilding )
		{
			/* Toggle the setting */
			\IPS\Settings::i()->changeValues( array( 'theme_designers_mode' => 1 ) );

			/* Finished */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=manage' ), 'completed' );
			exit();
		}

		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=designersmodeon' )->csrf(),
			function( $data )
			{
				/* Is this the first cycle? */
				if ( !\is_array( $data ) )
				{
					if ( ! is_dir( \IPS\ROOT_PATH . "/themes" ) )
					{
						if ( ! @mkdir( \IPS\ROOT_PATH . "/themes" ) )
						{
							\IPS\Output::i()->error( 'theme_dm_err_cant_write_themes', '4S142/1', 403, '' );
						}
						else
						{
							@chmod( \IPS\ROOT_PATH . "/themes", \IPS\IPS_FOLDER_PERMISSION );
						}
					}

					/* Check its writeable */
					if ( ! is_writeable( \IPS\ROOT_PATH . "/themes" ) )
					{
						\IPS\Output::i()->error( 'theme_dm_err_cant_write_into_themes', '4S142/2', 403, '' );
					}

					$toBuild = array();

					if( \IPS\Theme\Advanced\Theme::getToBuild() )
					{
						foreach ( \IPS\Theme\Advanced\Theme::getToBuild() as $id )
						{
							if( !$id )
							{
								continue;
							}
							
							$toBuild[$id] = array( 'html' => 'html', 'css' => 'css', 'resources' => 'resources' );
						}
					}

					/* Test extensions */
					$extensions = array();
					foreach ( \IPS\Application::allExtensions( 'core', 'DesignersMode', NULL, NULL, NULL, TRUE ) as $class )
					{
						if ( $class->toBuild() === TRUE )
						{
							$extensions[] = \get_class( $class );
						}
					}

					/* Start importing */
					$data = array( 'buildingDesignersFiles' => true, 'toBuild' => $toBuild, 'extensions' => $extensions, 'totalItems' => \count( array_keys( $toBuild ) ) * 3 + \count( array_keys( $extensions ) ), 'totalDone' => 0, 'extra' => array()  );

					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('theme_dm_building') );
				}

				/* Grab something to build */
				$msg = NULL;
				if ( \count( $data['extensions'] ) or \count( $data['toBuild'] ) )
				{
					if ( \count( $data['extensions'] ) )
					{
						/* Do this first */
						$array = $data['extensions'];
						$class = array_shift( $array ); # Don't remove the value from $data array

						$ext = new $class;

						$result = $ext->on( $data['extra'] );

						if( $result === TRUE )
						{
							/* We're done */
							$data['totalDone']++;
							$data['extensions'] = array_diff( $data['extensions'], array( $class ) );
						}
						else
						{
							/* Some data was returned, so lets store that in extra and allow a redirect which will hit this extension again */
							$data['extra'] = $result;
						}

						$msg = \IPS\Member::loggedIn()->language()->addToStack( 'theme_dm_building_extensions' );
					}
					else if ( \count( $data['toBuild'] ) )
					{
						reset( $data['toBuild'] );

						$themeId = key( $data['toBuild'] );
						$type	 = array_shift( $data['toBuild'][$themeId] );

						if( !\count( $data['toBuild'][ $themeId ] ) )
						{
							unset( $data['toBuild'][ $themeId ] );
						}

						\IPS\Theme\Advanced\Theme::$currentThemeId = $themeId;
						\IPS\Theme\Advanced\Theme::$buildingFiles = true;
						
						foreach ( \IPS\Application::applications() as $app )
						{
							switch ( $type )
							{
								case 'html':
									\IPS\Theme\Advanced\Theme::exportTemplates( $app->directory );
									break;
								case 'css':
									\IPS\Theme\Advanced\Theme::exportCss( $app->directory );
									break;
								case 'resources':
									\IPS\Theme\Advanced\Theme::exportResources( $app->directory );
									break;
							}
						}

						$msg = \IPS\Member::loggedIn()->language()->addToStack( 'theme_dm_building_theme', FALSE, array( 'sprintf' => array( \IPS\Theme::load( $themeId )->_title ) ) );

						$data['totalDone']++;
					}

					$percent = ( $data['totalDone'] / $data['totalItems'] * 100 );
					return array( $data, $msg, ( $percent <= 100 ? $percent : 100 ) );
				}
				else
				{
					/* All Done.. */
					return null;
				}
			},
			function()
			{
				/* Toggle the setting */
				\IPS\Settings::i()->changeValues( array( 'theme_designers_mode' => 1 ) );

				/* Finished */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=manage' ), 'completed' );
			}
		);
	}

	/**
	 * Manage themes
	 *
	 * @return	void
	 */
	public function resources()
	{
		$setId = \intval( \IPS\Request::i()->set_id );

		try
		{
			$set = \IPS\Theme::load( $setId );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2C163/4', 403, '' );
		}

		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_theme_resources', \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resources&set_id=' . $set->_id ), array( array('resource_set_id=?', $set->_id ) ) );

		$table->include    = array( 'resource_data', 'resource_name', 'resource_filename' );
		$table->langPrefix = 'core_theme_resources_';
		$table->mainColumn = 'resource_name';
		$table->noSort	   = array( 'resource_data', 'resource_filename' );

		$table->parsers = array(
			'resource_data' => function( $val, $row ) use ($set)
			{
				if ( \in_array( mb_substr( $row['resource_name'], mb_strrpos( $row['resource_name'], '.' ) + 1 ), array_merge( \IPS\Image::supportedExtensions(), array( 'svg' ) ) ) )
				{
					$row['resource_filename'] = $set->resource( ltrim( $row['resource_path'], '/' ) . $row['resource_name'], $row['resource_app'], $row['resource_location'] );
				}
				
				return \IPS\Theme::i()->getTemplate( 'customization' )->resourceDisplay( $row );
			},
			'resource_name' => function( $val, $row ) use ($set)
			{

				if ( \in_array( mb_substr( $row['resource_name'], mb_strrpos( $row['resource_name'], '.' ) + 1 ), array_merge( \IPS\Image::supportedExtensions(), array( 'svg' ) ) ) )
				{
					$row['resource_filename'] = $set->resource( ltrim( $row['resource_path'], '/' ) . $row['resource_name'], $row['resource_app'], $row['resource_location'] );
				}

				return \IPS\Theme::i()->getTemplate( 'customization' )->resourceName( $row );
			},
			'resource_filename' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'customization' )->resourceTag( '{resource="' . ltrim( $row['resource_path'], '/' ) . $row['resource_name'] . '" app="' . $row['resource_app'] . '" location="' . $row['resource_location'] . '"}' );
			}
		);

		$table->sortBy        = $table->sortBy ?: 'resource_name';
		$table->sortDirection = $table->sortDirection ?: 'asc';

		/* Root Buttons */
		\IPS\Output::i()->sidebar['actions']['add'] = array(
			'primary'	=> true,
			'icon'		=> 'plus',
			'title'		=> 'core_theme_resources_add',
			'data'		=> array(),
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resourceForm&set_id=' ) . $setId,
		);

		/* Row buttons */
		$table->rowButtons = function( $row )
		{
			$return = array();

			$return['edit'] = array(
				'icon'		=> 'pencil',
				'title'		=> 'edit',
				'data'		=> array(),
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resourceForm&id=' ) . $row['resource_id'] . '&set_id=' . $row['resource_set_id'],
			);

			$return['delete'] = array(
				'icon'		=> 'times-circle',
				'title'		=> 'delete',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resourceDelete&id=' ) . $row['resource_id'] . '&set_id=' . $row['resource_set_id'],
				'data'		=> array( 'delete' => '' ),
			);

			return $return;
		};

		/* The incredibly complex search code */
		$table->quickSearch = 'resource_name';

		/* Display */
		\IPS\Output::i()->cssFiles  = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/themes.css', 'core', 'admin' ) );
		$title                      = \IPS\Member::loggedIn()->language()->addToStack('theme_custom_resources_page_title', FALSE, array( 'sprintf' => array( $set->_title ) ) );
		\IPS\Output::i()->title		= $title;
		\IPS\Output::i()->output   .= (string) $table;
	}

	/**
	 * Resource delete
	 *
	 * @return void
	 */
	public function resourceDelete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$id  = \intval( \IPS\Request::i()->id );

		try
		{
			$set = \IPS\Theme::load( \IPS\Request::i()->set_id );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2C163/5', 403, '' );
		}

		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_theme_resources', array( 'resource_set_id=? and resource_id=?', $set->_id, $id ) )->first();
		}
		catch( \UnderflowException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2C163/8', 403, '' );
		}

		/* Don't delete master resources */
		if ( $current['resource_set_id'] > 0 )
		{
			try
			{
				\IPS\File::get( 'core_Theme', $current['resource_filename'] )->delete();
			}
			catch( \Exception $ex ) { }
			
			\IPS\Db::i()->delete( 'core_theme_resources', array( 'resource_id=?', $id ) );
		}
		
		/* Delete widget caches */
		\IPS\Widget::deleteCaches();
			
		$set->buildResourceMap();

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();
		
		\IPS\Session::i()->log( 'acplog__theme_resource_deleted', array( $current['resource_filename'] => FALSE ) );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resources&set_id=' . $set->_id ), 'deleted' );
	}

	/**
	 * Resource Form
	 *
	 * @return	void
	 */
	public function resourceForm()
	{
		$id  = \intval( \IPS\Request::i()->id );

		try
		{
			$set = \IPS\Theme::load( \IPS\Request::i()->set_id );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '2C163/6', 403, '' );
		}

		if ( $id )
		{
			try
			{
				$current = \IPS\Db::i()->select( '*', 'core_theme_resources', array( 'resource_set_id=? and resource_id=?', $set->_id, $id ) )->first();
			}
			catch( \UnderflowException $ex )
			{
				\IPS\Output::i()->error( 'node_error', '2C163/7', 403, '' );
			}
		}

		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_sets_manage' );

		$form = new \IPS\Helpers\Form( 'form', 'save' );

		/* Locations */
		$locations = iterator_to_array( \IPS\Db::i()->select( 'resource_location',  'core_theme_resources' )->setKeyField( 'resource_location' ) );

		/* Groups */
		$groups = iterator_to_array( \IPS\Db::i()->select( 'resource_path', 'core_theme_resources', NULL, 'resource_path asc' )->setKeyField( 'resource_path' ) );

		/* Apps */
		$apps = array();

		foreach( \IPS\Application::applications() as $key => $data )
		{
			$apps[ $key ] = $data->_title;
		}

		$form->add( new \IPS\Helpers\Form\Radio( 'core_theme_resource_location_type', 'existing', FALSE, array(
            'options' => array( 'existing' => 'core_theme_resource_location_o_existing',
                                'new'	   => 'core_theme_resource_location_o_new' ),
            'toggles' => array( 'existing' => array( 'location_existing' ),
                                'new'      => array( 'location_new' ) )
        ) ) );

		$form->add( new \IPS\Helpers\Form\Text( 'core_theme_resource_location_new', NULL, FALSE, array( 'regex' => '/^([a-z0-9_]+?)?$/' ), NULL, NULL, NULL, 'location_new' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'core_theme_resource_location_existing', ( isset( $current['resource_location'] ) ? $current['resource_location'] : NULL ), FALSE, array( 'options' => $locations ), NULL, NULL, NULL, 'location_existing' ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'core_theme_resource_group_type', 'existing', FALSE, array(
            'options'  => array( 'existing' => 'core_theme_resource_group_o_existing',
                                 'new'	    => 'core_theme_resource_group_o_new' ),
            'toggles'  => array( 'existing' => array( 'group_existing' ),
                                 'new'      => array( 'group_new' ) )
        ) ) );

		$form->add( new \IPS\Helpers\Form\Text( 'core_theme_resource_group_new', NULL, FALSE, array( 'regex' => '/^([A-Z0-9_\\-]+?)?$/i' ), NULL, NULL, NULL, 'group_new' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'core_theme_resource_group_existing', ( isset( $current['resource_path'] ) ? $current['resource_path'] : NULL ), FALSE, array( 'options' => $groups, 'parse' => 'normal' ), NULL, NULL, NULL, 'group_existing' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'core_theme_resource_app', ( isset( $current['resource_app'] ) ? $current['resource_app'] : NULL ), TRUE, array( 'options' => $apps ) ) );

		if ( $id )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'core_theme_resource_name_choice', 'existing', FALSE, array(
				'options'  => array( 'existing' => 'core_theme_resource_name_choice_o_existing',
				                     'filename' => 'core_theme_resource_name_choice_o_filename' )
			) ) );

			\IPS\Member::loggedIn()->language()->words['core_theme_resource_name_choice_o_filename_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('core_theme_resource_name_choice_o_filename_desc'), $current['resource_name'] );
		}

		$form->add( new \IPS\Helpers\Form\Upload( 'core_theme_resource_filename', ( isset( $current['resource_filename'] ) ? \IPS\File::get( 'core_Theme', $current['resource_filename'] ) : NULL ), TRUE, array( 'obscure' => ( $id ) ? TRUE : FALSE, 'maxFileSize' => 1.2, 'storageExtension' => 'core_Theme', 'storageContainer' => 'set_resources_' . $set->_id ), NULL, NULL, NULL, 'core_theme_resource_filename' ) );

		if ( $values = $form->values() )
		{
			$save = array(
				'resource_app'	       => $values['core_theme_resource_app'],
                'resource_location'    => ( $values['core_theme_resource_location_type'] == 'existing' ) ? $values['core_theme_resource_location_existing'] : $values['core_theme_resource_location_new'],
                'resource_path' 	   => ( $values['core_theme_resource_group_type']    == 'existing' ) ? $values['core_theme_resource_group_existing']    : '/' . trim( $values['core_theme_resource_group_new'], '/' ) . '/',
                'resource_set_id'      => $set->_id,
                'resource_user_edited' => 1 
			);

			if ( $values['core_theme_resource_filename'] )
			{
				$save['resource_filename'] = (string) $values['core_theme_resource_filename'];
			    $save['resource_data']     = $values['core_theme_resource_filename']->contents();
				
				if ( ! ( $id AND isset( $values['core_theme_resource_name_choice'] ) and $values['core_theme_resource_name_choice'] === 'existing' ) )
				{
					$save['resource_name'] = $values['core_theme_resource_filename']->originalFilename;
				}
			}

			if ( $id )
			{
				\IPS\Session::i()->log( 'acplog__theme_resource_edited', array( $save['resource_filename'] => FALSE ) );
				\IPS\Db::i()->update( 'core_theme_resources', $save, array( 'resource_id=?', $id ) );
			}
			else
			{
				$save['resource_added'] = time();

				/* Check to make sure file name is unique */
				try
				{
					\IPS\Db::i()->select( '*', 'core_theme_resources', array( 'resource_set_id=? and resource_path=? and resource_location=? and resource_app=? and resource_name=?', $set->_id, $save['resource_path'], $save['resource_location'], $save['resource_app'], $save['resource_name'] ) )->first();

					$ext = mb_substr( $save['resource_name'], ( mb_strrpos( $save['resource_name'], '.' ) + 1 ) );
					$save['resource_name'] = mb_substr( $save['resource_name'], 0, ( mb_strrpos( $save['resource_name'], '.' ) ) ) . '_' . mt_rand() . '.' . $ext;
				}
				catch( \UnderflowException $ex ) { }
				
				\IPS\Session::i()->log( 'acplog__theme_resource_added', array( $save['resource_filename'] => FALSE ) );
				\IPS\Db::i()->insert( 'core_theme_resources', $save );
			}

			$set->buildResourceMap();
			
			/* Delete widget caches */
			\IPS\Widget::deleteCaches();

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* Resource may be used in CSS */
			\IPS\File::getClass('core_Theme')->deleteContainer( 'css_built_' . $set->id );
			$set->css_map = array();
			$set->css_updated = time();
			$set->save();
			
			foreach( $set->children() as $child )
			{
				\IPS\File::getClass('core_Theme')->deleteContainer( 'css_built_' . $child->id );
				$child->css_map = array();
				$child->css_updated = time();
				$child->save();
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=resources&set_id=' . $set->_id ), 'saved' );
		}
		
		\IPS\Theme::setThemeCustomized( $set->_id );

		/* Display */
		$title = \IPS\Member::loggedIn()->language()->addToStack('theme_custom_resources_page_title', FALSE, array( 'sprintf' => array( $set->_title ) ) );
		\IPS\Output::i()->title	 = $title;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( $title, $form, FALSE );
	}

	/**
	 * Launches the Easy Mode Editor in a new window.
	 *
	 * @return void
	 */
	public function launchvse()
	{
		\IPS\Session::i()->csrfCheck();
		
		$theme = \IPS\Theme::load( \IPS\Request::i()->id );
		
		if ( $theme->by_skin_gen AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_easy_editor' ) )
		{
			/* Update the current member so that the flag is set to load the VSE JS */
			\IPS\Member::loggedIn()->members_bitoptions['bw_using_skin_gen'] = 1;
			\IPS\Member::loggedIn()->save();
			\IPS\Request::i()->setCookie( 'vseThemeId', $theme->id, \IPS\DateTime::ts( time() + ( 86400 * 7 ) ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=vse&do=show&id=' . \IPS\Request::i()->id, 'front' ) );
		}
		
		/* Still here? You can't have permission or the theme isn't an easy mode theme */
		\IPS\Output::i()->error( 'core_theme_cant_easy_mode', '2C163/2', 403, '' );
	}
	
	/**
	 * Converts a VSE theme to a custom theme
	 *
	 * @return void
	 */
	public function convertToCustom()
	{
		if ( \IPS\DEMO_MODE )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '1C163/J', 403, '' );
		}
		
		\IPS\Session::i()->csrfCheck();
		
		$theme = \IPS\Theme::load( \IPS\Request::i()->id );
		$theme->by_skin_gen = 0;
		$theme->save();
		
		\IPS\Session::i()->log( 'acplog__theme_converted_custom', array( "core_theme_set_title_{$theme->id}" => TRUE ) );
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array() );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'completed' );
		}
	}
	
	/**
	 * Manual Theme Upload
	 *
	 * @return void
	 */
	public function upload()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_download_upload' );
		
		$form = new \IPS\Helpers\Form( 'form', 'next' );
		$form->addMessage('themes_manual_install_warning');
		$form->add(
			new \IPS\Helpers\Form\Upload(
				'core_theme_set_new_import', NULL, FALSE, array(
				'allowedFileTypes' => array( 'xml' ),
				'temporary'        => TRUE
			), NULL, NULL, NULL, 'core_theme_set_new_import'
			)
		);
		
		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['core_theme_set_new_import'], $tempFile );
			
			$max = \IPS\Db::i()->select( 'MAX(set_order)', 'core_themes' )->first();
			
			/* Create a default theme */
			$theme = new \IPS\Theme;
			$theme->parent_array = '[]';
			$theme->child_array  = '[]';
			$theme->parent_id    = 0;
			$theme->by_skin_gen  = 0;
			$theme->editor_skin	 = 'ips';
			$theme->order        = $max + 1;
			$theme->save();
			
			$theme->copyResourcesFromSet();
			
			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=import' )->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ), 'id' => $theme->id ) )->csrf() );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_set_add_button', $form, FALSE );
	}
	
	/**
	 * Add a theme dialog
	 *
	 * @return void
	 */
	public function add()
	{
		if ( \IPS\DEMO_MODE )
		{
			/* We only allow easy mode themes */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=form&type=vse' ) );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'next' );
		$form->addMessage('themes_create_blurb');
			
		$form->add( new \IPS\Helpers\Form\Radio(
			'core_theme_add_theme_type',
			NULL,
			FALSE,
			array(	'options'=> array( 'vse' => 'core_theme_add_theme_vse', 'custom' => 'core_theme_add_theme_custom' ),
					'descriptions' => array( 'vse' => 'core_theme_add_theme_vse_desc', 'custom' => 'core_theme_add_theme_custom_desc' ) ),
			NULL,
			NULL,
			NULL,
			'core_theme_add_theme_type'
		) );

		if ( $values = $form->values() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=form&type=' . $values['core_theme_add_theme_type'] ) );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_set_add_button', $form, FALSE );
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
			\IPS\Output::i()->error( 'generic_error', '3C130/1', 500, '' );
		}
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=import' )->setQueryString( array( 'file' => \IPS\Request::i()->file, 'key' =>  \IPS\Request::i()->key, 'id' => \IPS\Request::i()->id ) )->csrf(),
			function( $data )
			{
				$set    = \IPS\Theme::load( \IPS\Request::i()->id );
				$iMap	= $set->resource_map;
				
				$templates = \IPS\Theme::load( \IPS\Request::i()->id )->getRawTemplates( null, null, null, \IPS\Theme::RETURN_ALL_NO_CONTENT, true );
				$css	   = \IPS\Theme::load( \IPS\Request::i()->id )->getRawCss( null, null, null, \IPS\Theme::RETURN_ALL_NO_CONTENT, true );
				$masterCss = \IPS\Theme::load( \IPS\Request::i()->id )->getRawCss( null, null, null, \IPS\Theme::RETURN_BIT_NAMES, false );

				/* Open XML file */
				$xml = \IPS\Xml\XMLReader::safeOpen( \IPS\Request::i()->file );
				
				if ( ! @$xml->read() )
				{
					@unlink( \IPS\Request::i()->file );

					\IPS\Output::i()->error( 'xml_upload_invalid', '2C163/1', 403, '' );
				}
				
				/* Is this the first batch? */
				if ( !\is_array( $data ) )
				{
					$_SESSION['theme_import'] = array( 'css' => array(), 'isNewSet' => false );
					
					/* Save snapshot */
					\IPS\Theme::load( \IPS\Request::i()->id )->saveHistorySnapshot();
					
					/* Wipe clean conflicts */
					\IPS\Db::i()->delete( 'core_theme_conflict', array( 'conflict_set_id=?', \IPS\Request::i()->id ) );
					
					/* No Name? Then this is a brand new theme */
					if ( empty( $set->name ) )
					{
						$_SESSION['theme_import']['isNewSet'] = TRUE;
						while ( $xml->read() )
						{
							if ( $xml->name == 'theme' )
							{
								$groups	= array_keys( \IPS\Member::administrators()['g'] );

								$set->saveSet( array(
									'set_name'         		=> $xml->getAttribute('name'),
									'set_author_name' 		=> $xml->getAttribute('author_name'),
									'set_author_url'   		=> $xml->getAttribute('author_url'),
									'set_version'      		=> $xml->getAttribute('version'),
									'set_update_check' 		=> $xml->getAttribute('update_check'),
									'set_long_version' 		=> ( $xml->getAttribute('long_version') ) ? $xml->getAttribute('long_version') : \IPS\Application::load('core')->long_version,
									'set_is_default'   		=> $set->is_default,
									'set_permissions'  		=> implode( ',', $groups )
								) );
								
								if ( $xml->getAttribute('editor_skin') )
								{
									$editorTheme = $xml->getAttribute('editor_skin');
									
									if ( $editorTheme AND $editorTheme !== 'ips' AND is_dir( \IPS\ROOT_PATH . '/applications/core/interface/ckeditor/ckeditor/skins/' . $editorTheme ) )
									{
										$set->editor_skin = $editorTheme;
										$set->save();
									}
								}
								
								if ( $xml->getAttribute('easy_mode') )
								{
									$set->by_skin_gen = 1;
									$set->save();
								}
								
								break;
							}
						}
					}
					else
					{
						/* We are importing an update to a theme */
						while ( $xml->read() )
						{
							if ( $xml->name == 'theme' )
							{
								$set->saveSet( array(
									'set_author_name'  => $xml->getAttribute('author_name'),
									'set_author_url'   => $xml->getAttribute('author_url'),
									'set_version'      => $xml->getAttribute('version'),
									'set_update_check' => $xml->getAttribute('update_check'),
									'set_long_version' => ( $xml->getAttribute('long_version') ) ? $xml->getAttribute('long_version') : \IPS\Application::load('core')->long_version
								) );
								
								break;
							}
						}
					}
					
					/* Start importing */
					$data = array( 'apps' => array() );
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
				
				/* Move to correct app */
				$appKey = NULL;
				$version = \IPS\Theme::load( \IPS\Request::i()->id )->long_version;
				
				$xml->read();
				while ( $xml->read() )
				{
					if ( $xml->name === 'header' )
					{
						$set->custom_header = $xml->readString();
						$set->save();
						$xml->next();
					}
					elseif ( $xml->name === 'footer' )
					{
						$set->custom_footer = $xml->readString();
						$set->save();
						$xml->next();
					}
					elseif ( $xml->name === 'app' )
					{
						$appKey = $xml->getAttribute('key');
						if ( !array_key_exists( $appKey, $data['apps'] ) )
						{
							try
							{
								try
								{
									$application = \IPS\Application::load( $appKey );
								}
								/* Application is out of date */
								catch( \UnexpectedValueException $e )
								{
									$xml->next();
									continue;
								}

								if ( $application->enabled )
								{
									/* Import */
									$xml->read();
									while ( $xml->read() )
									{
										switch ( $xml->name )
										{
											case 'field':
												$current = NULL;
												try
												{
													$field = \IPS\Db::i()->select( 'sc_id', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_app=? AND sc_key=?', \IPS\Request::i()->id, $appKey, $xml->getAttribute('sc_key') ) )->first();
													$current = \IPS\Db::i()->select( 'sv_value', 'core_theme_settings_values', array( 'sv_id=?', $field ) )->first();
												}
												catch ( \UnderflowException $e ) {}

												\IPS\Db::i()->delete( 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_app=? AND sc_key=?', \IPS\Request::i()->id, $appKey, $xml->getAttribute('sc_key') ) );

												$default = $xml->getAttribute('sc_default');
												
												$fieldId = \IPS\Db::i()->insert( 'core_theme_settings_fields', array(
													'sc_set_id'    	   => \IPS\Request::i()->id,
													'sc_key'   		   => $xml->getAttribute('sc_key'),
													'sc_type'          => $xml->getAttribute('sc_type'),
													'sc_multiple'      => $xml->getAttribute('sc_multiple'),
													'sc_default'       => $default,
													'sc_content'       => $xml->getAttribute('sc_content'),
													'sc_app'		   => $appKey,
		                                            'sc_tab_key'       => $xml->getAttribute('sc_tab_key'),
		                                            'sc_show_in_vse'   => $xml->getAttribute('sc_show_in_vse'),
													'sc_title'		   => $xml->getAttribute('sc_title'),
													'sc_order'		   => (int) $xml->getAttribute('sc_order'),
													'sc_condition'	   => $xml->getAttribute('sc_condition')
												) );
												
												$custom = $xml->readString();
												$value = ( trim( $custom ) !== "" ) ? $custom : $default;
												$node  = new \SimpleXMLElement( $xml->readOuterXml() );

												/* Any kids of your own? */
												$files = array();
												foreach ( $node->children() as $k => $v )
												{
													if ( $k == 'file' )
													{
														$filename = $v['name'];
														$filedata = (string) $v;

														if ( $filename and $filedata )
														{
															$files[] = (string) \IPS\File::create( 'core_Theme', $filename, base64_decode( $filedata ) );
														}
													}
												}

												if ( \count( $files ) )
												{
													$value = implode( ',', $files );
												}


												\IPS\Db::i()->insert( 'core_theme_settings_values', array( 'sv_id' => $fieldId, 'sv_value' => ( $current !== NULL ) ? $current : $value ) );
											break;
											case 'template':
												$location = $xml->getAttribute('template_location');
												$group    = $xml->getAttribute('template_group');
												$name     = $xml->getAttribute('template_name');
												$isMaster = \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_templates', array( 'template_app=? and template_location=? and template_group=? and template_name=? and template_set_id=0 and template_added_to=0', $appKey, $location, $group, $name ) )->first();
												
												if ( isset( $templates[ $appKey ][ $location ][ $group ][ $name ] ) AND $templates[ $appKey ][ $location ][ $group ][ $name ]['template_user_edited'] )
												{
													\IPS\Db::i()->insert( 'core_theme_conflict', array(
															'conflict_set_id'	    => \IPS\Request::i()->id,
															'conflict_item_id'      => $templates[ $appKey ][ $location ][ $group ][ $name ]['template_id'],
															'conflict_type'		    => 'template',
															'conflict_app'		    => $appKey,
															'conflict_location'	    => $location,
															'conflict_path'		    => $group,
															'conflict_name'		    => $name,
															'conflict_data'		    => $xml->getAttribute('template_data'),
															'conflict_content'      => $xml->readString(),
															'conflict_long_version' => $version,
															'conflict_date'			=> time()
													) );
												}
												else
												{
													\IPS\Db::i()->replace( 'core_theme_templates', array(
															'template_set_id'     => \IPS\Request::i()->id,
															'template_app'        => $appKey,
															'template_location'   => $location,
															'template_group'      => $group,
															'template_name'       => $name,
															'template_data'       => $xml->getAttribute('template_data'),
															'template_content'    => $xml->readString(),
															'template_updated'	  => time(),
															'template_removable'  => 1,
															'template_user_added' => $isMaster ? 0 : 1,
															'template_user_edited'=> 0,
															'template_version'	  => $version,
															'template_added_to'   => \IPS\Request::i()->id
													) );
												}
											break;
											case 'css':
												$location = $xml->getAttribute('css_location');
												$path     = $xml->getAttribute('css_path');
												$name     = $xml->getAttribute('css_name');
												
												$isMaster = \IPS\Db::i()->select( 'COUNT(*)', 'core_theme_css', array( 'css_app=? and css_location=? and css_path=? and css_name=? and css_set_id=0 and css_added_to=0', $appKey, $location, $path, $name ) )->first();
												
												
												if ( isset( $css[ $appKey ][ $location ][ $path ][ $name ] ) AND $css[ $appKey ][ $location ][ $path ][ $name ]['css_user_edited'] )
												{
													/* Keep this */
													$_SESSION['theme_import']['css'][] = $css[ $appKey ][ $location ][ $path ][ $name ]['css_id'];
													
													\IPS\Db::i()->insert( 'core_theme_conflict', array(
															'conflict_set_id'	    => \IPS\Request::i()->id,
															'conflict_item_id'      => $css[ $appKey ][ $location ][ $path ][ $name ]['css_id'],
															'conflict_type'		    => 'css',
															'conflict_app'		    => $appKey,
															'conflict_location'	    => $location,
															'conflict_path'		    => $path,
															'conflict_name'		    => $name,
															'conflict_data'		    => $xml->getAttribute('css_attributes'),
															'conflict_content'      => $xml->readString(),
															'conflict_long_version' => $version,
															'conflict_date'			=> time()
													) );
												}
												else
												{
													$edited = 0;
													$addedTo = \IPS\Request::i()->id;
													
													if ( $isMaster )
													{
														$edited = time();
														$addedTo = 0;
													}
													
													/* Keep this */
													$_SESSION['theme_import']['css'][] = \IPS\Db::i()->replace( 'core_theme_css', array(
															'css_set_id'     => \IPS\Request::i()->id,
															'css_app'        => $appKey,
															'css_location'   => $xml->getAttribute('css_location'),
															'css_path'       => $xml->getAttribute('css_path'),
															'css_name'       => $xml->getAttribute('css_name'),
															'css_attributes' => $xml->getAttribute('css_attributes'),
															'css_content'    => $xml->readString(),
															'css_version'	 => $version,
															'css_user_edited'=> $edited,
															'css_added_to'   => $addedTo
													), true );
												}
											break;
											case 'resource':
												/* Theme resources should be raw binary data everywhere (filesystem and DB) except in the theme XML download where they are base64 encoded. */
												$content  = base64_decode( $xml->readString() );
												$name     = \IPS\Theme::makeBuiltTemplateLookupHash( $appKey, $xml->getAttribute('location'), $xml->getAttribute('path') ) . '_' . $xml->getAttribute('name');
												$fileName = (string) \IPS\File::create( 'core_Theme', $name, $content, 'set_resources_' . \IPS\Request::i()->id, TRUE, NULL, FALSE );
												
												try
												{
													$existingImage = \IPS\Db::i()->select( '*', 'core_theme_resources', array(
														'resource_set_id=? AND resource_app=? AND resource_location=? AND resource_path=? AND resource_name=?',
														\IPS\Request::i()->id, $appKey, $xml->getAttribute('location'), $xml->getAttribute('path'), $xml->getAttribute('name')
													) )->first();
													
													if ( $existingImage['resource_filename'] )
													{
														try
														{
															\IPS\File::get( 'core_Theme', $existingImage['resource_filename'] )->delete();
														}
														catch( \Exception $e ) { }
													}
													
													\IPS\Db::i()->delete( 'core_theme_resources', array( 'resource_id=?', $existingImage['resource_id'] ) );
												}
												catch( \UnderflowException $e ) { }
												
												\IPS\Db::i()->replace( 'core_theme_resources', array(
														'resource_set_id'      => \IPS\Request::i()->id,
														'resource_app'         => $appKey,
														'resource_location'    => $xml->getAttribute('location'),
														'resource_path'        => $xml->getAttribute('path'),
														'resource_name'        => $xml->getAttribute('name'),
													    'resource_data'        => $content,
														'resource_added'	   => time(),
														'resource_filename'    => $fileName,
														'resource_user_edited' => \intval( $xml->getAttribute('user_edited') )
												) );
											break;
										}
										
										$xml->next();
									}
								}
							}
							catch ( \OutOfRangeException $e ) { }
							
							/* Update set so far so that mappings are saved */
							$set->saveSet();
							
							/* Done */
							$data['apps'][ $appKey ] = TRUE;
							return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
						}
						else
						{
							$xml->next();
						}
					}
					elseif ( $xml->name === 'language' )
					{
						$xml->read();
						while ( $xml->read() )
						{
							if ( $xml->name == 'word' )
							{
								$languageIds = isset( $languageIds ) ? $languageIds : array_keys( \IPS\Lang::languages() );
								foreach ( $languageIds as $langId )
								{
									$default = $xml->readString();
									$exists  = \IPS\Db::i()->select( 'COUNT(*)', 'core_sys_lang_words', array( array( 'lang_id=? and word_key=? and word_theme=?', $langId, $xml->getAttribute('key'), \IPS\Request::i()->id ) ) )->first();

									if ( $exists )
									{
										\IPS\Db::i()->update( 'core_sys_lang_words', array(
											'word_default' 		   => $default,
											'word_default_version' => $version,
										), array( array( 'lang_id=? and word_key=? and word_theme=?', $langId, $xml->getAttribute('key'), \IPS\Request::i()->id ) ) );
									}
									else
									{
										\IPS\Db::i()->insert( 'core_sys_lang_words', array(
											'lang_id'				=> $langId,
											'word_app'				=> NULL,
											'word_plugin'			=> NULL,
											'word_theme'			=> \IPS\Request::i()->id,
											'word_key'				=> $xml->getAttribute('key'),
											'word_default'			=> $default,
											'word_custom'			=> NULL,
											'word_default_version'	=> $version,
											'word_custom_version'	=> NULL,
											'word_js'				=> FALSE,
											'word_export'			=> TRUE
										) );
									}
								}
								
								$xml->next();
							}
						}
						return NULL;
					}
					elseif( $xml->name === 'logo' )
					{
						$type = $xml->getAttribute('type');
						$name = $xml->getAttribute('name');

						/* Sharer logo and favicons may be present from themes generated in 4.3 and earlier, but are no longer used */
						if( $type != 'sharer' AND $type != 'favicon' )
						{
							if( $set->logo[ $type ]['url'] !== null )
							{
								\IPS\File::get( 'core_Theme', $set->logo[ $type ]['url'] )->delete();
							}

							$url = (string) \IPS\File::create( 'core_Theme', $name, base64_decode( $xml->readString() ) );

							$set->saveSet( array( 'logo' => array( $type => array( 'url' => $url, 'height' => $xml->getAttribute('height') ?? 100 ) ) ) );
						}

						$xml->next();
					}
				}
			},
			function()
			{
				/* Do we need to clean up orphaned CSS files? */
				if ( $_SESSION['theme_import']['isNewSet'] === false and isset( $_SESSION['theme_import']['css'] ) )
				{
					\IPS\Db::i()->delete( 'core_theme_css', array( 'css_added_to=? AND ' . \IPS\Db::i()->in( 'css_id', $_SESSION['theme_import']['css'], true ), \IPS\Request::i()->id ) );
				}
				
				$set = \IPS\Theme::load( \IPS\Request::i()->id );
				\IPS\Theme::deleteCompiledResources( null, null, null, null, $set->id );
				\IPS\Theme::deleteCompiledTemplate( null, null, null, $set->id );
				\IPS\Theme::deleteCompiledCss( null, null, null, null, $set->id );
				
				\IPS\Data\Store::i()->delete( 'core_theme_import_' . md5_file( \IPS\Request::i()->file ) );
				
				unset( \IPS\Data\Store::i()->themes );

				@unlink( \IPS\Request::i()->file );
				
				/* Update theme settings */
				foreach( \IPS\Application::applications() as $app )
				{
					$app->installThemeSettings( true );
				}
				
				\IPS\Theme::setThemeCustomized( $set->id );
				
				$set->resetCacheKey();

				/* Update children */
				$set->updateChildrenThemeSettings();
		
				/* Conflicts to fix? */
				if ( \IPS\Db::i()->select( 'count(*)', 'core_theme_conflict', array( 'conflict_set_id=?', \IPS\Request::i()->id ) )->first() )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=conflicts&id=' . \IPS\Request::i()->id ) );
				}
				elseif( $set->marketplace_id )
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=marketplace&controller=marketplace&do=viewFile&id=' . $set->marketplace_id ), 'theme_now_installed' );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ) );
				}
			}
		);
	}
	
	/**
	 * View and resolve conflicts
	 *
	 * @return void
	 */
	public function conflicts()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		$id          = \intval( \IPS\Request::i()->id );
		$form        = new \IPS\Helpers\Form( 'form', 'theme_conflict_save' );
		$themeSet    = \IPS\Theme::load( $id );
		$templates   = array();
		$css         = array();
		
		/* Get conflict data */
		foreach( \IPS\Db::i()->select( '*', 'core_theme_conflict', array( 'conflict_set_id=?', $id ) )->setKeyField( 'conflict_id' ) as $cid => $data )
		{
			$key = md5( $data['conflict_app'] . '.' . $data['conflict_location'] . '.' . $data['conflict_path'] . '.' . $data['conflict_name'] );
			
			$conflicts[ $data['conflict_type'] ][ $key ] = $data;
		}
		
		if ( isset( $conflicts['template'] ) AND \count( $conflicts['template'] ) )
		{
			$templates = iterator_to_array( \IPS\Db::i()->select(
				'*, MD5( CONCAT( template_app, \'.\', template_location, \'.\', template_group, \'.\', template_name ) ) as bit_key',
				'core_theme_templates',
				'template_set_id=' . $id . ' AND ' . \IPS\Db::i()->in('MD5( CONCAT( template_app, \'.\', template_location, \'.\', template_group, \'.\', template_name ) )', array_keys( $conflicts['template'] ) )
			)->setKeyField( 'bit_key' ) );
		}
		else
		{
			$conflicts['template'] = array();
		}
		
		if ( isset( $conflicts['css'] ) AND \count( $conflicts['css'] ) )
		{
			$css = iterator_to_array( \IPS\Db::i()->select(
				'*, MD5( CONCAT( css_app, \'.\', css_location, \'.\', css_path, \'.\', css_name ) ) as bit_key',
				'core_theme_css',
				'css_set_id=' . $id . ' AND ' . \IPS\Db::i()->in('MD5( CONCAT( css_app, \'.\', css_location, \'.\', css_path, \'.\', css_name ) )', array_keys( $conflicts['css'] ) )
			)->setKeyField( 'bit_key' ) );
		}
		else
		{
			$conflicts['css'] = array();
		}
		
		require_once \IPS\ROOT_PATH . "/system/3rd_party/Diff/class.Diff.php";
		
		foreach( $conflicts['template'] as $key => $data )
		{
			if( !\IPS\Login::compareHashes( md5( $data['conflict_content'] ), md5( $templates[ $key ]['template_content'] ) ) )
			{
				if ( mb_strlen( $data['conflict_content'] ) <= 10000 )
				{
					$conflicts['template'][ $key ]['diff'] = \Diff::toTable( \Diff::compare( $templates[ $key ]['template_content'], $data['conflict_content'] ) );
					$conflicts['template'][ $key ]['large'] = false;
				}
				else
				{
					$conflicts['template'][ $key ]['diff'] = \IPS\Theme::i()->getTemplate('customization')->templateConflictLarge( $css[ $key ]['css_content'], $data['conflict_content'], 'html' );
					$conflicts['template'][ $key ]['large'] = true;
				}
				
				$form->add( new \IPS\Helpers\Form\Radio( 'conflict_' . $data['conflict_id'], 'old', false, array( 'options' => array( 'old' => '', 'new' => '' ) ) ) );
			}
			else
			{
				unset( $conflicts['template'][ $key ] );
			}
		}
		
		foreach( $conflicts['css'] as $key => $data )
		{
			if( !\IPS\Login::compareHashes( md5( $data['conflict_content'] ), md5( $css[ $key ]['css_content'] ) ) )
			{
				if ( mb_strlen( $data['conflict_content'] ) <= 10000 )
				{
					$conflicts['css'][ $key ]['diff'] = \Diff::toTable( \Diff::compare( $css[ $key ]['css_content'], $data['conflict_content'] ) );
					$conflicts['css'][ $key ]['large'] = false;
				}
				else
				{
					$conflicts['css'][ $key ]['diff'] = \IPS\Theme::i()->getTemplate('customization')->templateConflictLarge( $css[ $key ]['css_content'], $data['conflict_content'], 'css' );
					$conflicts['css'][ $key ]['large'] = true;
				}
				
				$form->add( new \IPS\Helpers\Form\Radio( 'conflict_' . $data['conflict_id'], 'old', false, array( 'options' => array( 'old' => '', 'new' => '' ) ) ) );
			}
			else
			{
				unset( $conflicts['css'][ $key ] );
			}
		}
		
		if ( $values = $form->values() )
		{
			$conflicts   = array();
			$conflictIds = array();
			
			foreach( $values as $k => $v )
			{
				if ( \substr( $k, 0, 9 ) == 'conflict_' )
				{
					if ( $v == 'new' )
					{
						$conflictIds[ (int) \substr( $k, 9 ) ] = $v;
					}
				}
			}
			
			if ( \count( $conflictIds ) )
			{
				/* Get conflict data */
				foreach( \IPS\Db::i()->select( '*', 'core_theme_conflict', \IPS\Db::i()->in( 'conflict_id', array_keys( $conflictIds ) ) )->setKeyField( 'conflict_id' ) as $cid => $data )
				{
					$key = md5( $data['conflict_app'] . '.' . $data['conflict_location'] . '.' . $data['conflict_path'] . '.' . $data['conflict_name'] );
						
					$conflicts[ $data['conflict_type'] ][ $key ] = $data;
				}
			}
			
			if ( isset( $conflicts['template'] ) AND \count( $conflicts['template'] ) )
			{
				$templates = iterator_to_array( \IPS\Db::i()->select(
					'*, MD5( CONCAT( template_app, \'.\', template_location, \'.\', template_group, \'.\', template_name ) ) as bit_key',
					'core_theme_templates',
					'template_set_id=' . $id . ' AND ' . \IPS\Db::i()->in('MD5( CONCAT( template_app, \'.\', template_location, \'.\', template_group, \'.\', template_name ) )', array_keys( $conflicts['template'] ) )
				)->setKeyField( 'bit_key' ) );
			}
			
			if ( isset( $conflicts['css'] ) AND \count( $conflicts['css'] ) )
			{
				$css = iterator_to_array( \IPS\Db::i()->select(
					'*, MD5( CONCAT( css_app, \'.\', css_location, \'.\', css_path, \'.\', css_name ) ) as bit_key',
					'core_theme_css',
					'css_set_id=' . $id . ' AND ' . \IPS\Db::i()->in('MD5( CONCAT( css_app, \'.\', css_location, \'.\', css_path, \'.\', css_name ) )', array_keys( $conflicts['css'] ) )
				)->setKeyField( 'bit_key' ) );
			}
			
			foreach( $templates as $templateid => $template )
			{
				if ( isset( $conflicts['template'][ $template['bit_key'] ] ) )
				{
					\IPS\Theme::load( \IPS\Request::i()->id )->saveTemplate( array(
						'item_id'	=> $template['template_id'],
						'set_id'    => \IPS\Request::i()->id,
						'app'       => $template['template_app'],
						'location'  => $template['template_location'],
						'group'		=> $template['template_group'],
						'content'   => $conflicts['template'][ $template['bit_key'] ]['conflict_content'],
						'variables' => $conflicts['template'][ $template['bit_key'] ]['conflict_data']
					) );
				}
			}
			
			foreach( $css as $cssid => $cssitem )
			{
				if ( isset( $conflicts['css'][ $cssitem['bit_key'] ] ) )
				{
					\IPS\Theme::load( \IPS\Request::i()->id )->saveCss( array(
							'item_id'	=> $cssitem['css_id'],
							'set_id'    => \IPS\Request::i()->id,
							'app'       => $cssitem['css_app'],
							'location'  => $cssitem['css_location'],
							'path'		=> $cssitem['css_path'],
							'content'   => $conflicts['css'][ $cssitem['bit_key'] ]['conflict_content'],
							'variables' => $conflicts['css'][ $cssitem['bit_key'] ]['conflict_data']
					) );
				}
			}
			
			/* Clear out conflicts for this theme set */
			\IPS\Db::i()->delete( 'core_theme_conflict', array('conflict_set_id=?', \IPS\Request::i()->id ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'completed' );
		}
		
		if ( \count( $conflicts['css'] ) OR \count( $conflicts['template'] ) )
		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/themes.css', 'core', 'admin' ) );
			\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_templates.js', 'core', 'admin' ) );
			
			\IPS\Output::i()->output   = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'customization', 'core' ), 'templateConflict' ), $themeSet, $conflicts, $templates, $css );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'completed' );
		}
	}
	
	/**
	 * Add CSS form
	 * This is never used for editing as this is done via the template/css manager
	 *
	 * @return	void
	 */
	public function cssForm()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		$id = \intval( \IPS\Request::i()->id );
	
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->hiddenValues['id'] = $id;
	
		$form->add( new \IPS\Helpers\Form\Text( 'theme_css_name', NULL, TRUE, array( 'placeholder' => 'example.css', 'regex' => '/^([A-Z0-9_\\-\.]+?)$/i' ),
		function( $val )
		{
			if ( ! preg_match( '/^([A-Z0-9_\-\.]+?)$/i', $val ) )
			{
				throw new \InvalidArgumentException( 'form_bad_value' );
			}

			/* Make sure key is unique */
			try
			{
				$row = \IPS\Db::i()->select( '*', 'core_theme_css', array( \IPS\Db::i()->in( 'css_set_id', array( 0, \IPS\Request::i()->id ) ) . " AND css_app=? AND css_path=? AND css_location=? AND css_name=?", 'core', 'custom', 'front', $val ) )->first();
				
				if ( isset( $row['css_id'] ) )
				{
					throw new \InvalidArgumentException('core_theme_error_css_name_exists');
				}
			}
			catch( \UnderflowException $e )
			{
				/* Name is OK as select failed */
			}
			
			return true;
		} ) );
		
		if( ! \IPS\Request::i()->isAjax() )
		{
			$form->add( new \IPS\Helpers\Form\TextArea( 'theme_css_content', NULL ) );
		}
	
		if ( $values = $form->values() )
		{
			$id   = \intval( \IPS\Request::i()->id );
			$save = array( 'app'	    => 'core',
						   'location'   => 'front',
						   'path' 	    => 'custom',
						   'name' 	    => $values['theme_css_name'],
						   'added_to'	=> $id,
						   'set_id'     => $id,
						   'content'    => $values['theme_css_content'] );
				
			$newId = \IPS\Theme::addCss( $save );

			\IPS\Theme::i()->load( $id )->compileCss( $save['app'], $save['location'], $save['path'] );
				
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'id' 		=> $newId,
					'app' 		=> $save['app'],
					'location' 	=> $save['location'],
					'path'		=> $save['path'],
					'name'		=> $save['name']
				)	);
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . $id ), 'saved' );
			}
		}
	
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_set_add_css', $form, FALSE );
	}
	
	/**
	 * Upload a new version
	 *
	 * @return	void
	 */
	public function importForm()
	{
		$id = \intval( \IPS\Request::i()->id );
		
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_download_upload' );
		
		$themeSet = \IPS\Theme::load( $id );
		$apps    = array();

		if( $themeSet->marketplace_id )
		{
			\IPS\Output::i()->error( 'app_upload_marketplace_only', '2C163/K', 403, '' );
		}
	
		foreach( \IPS\Application::applications() as $key => $data )
		{
			$lang = "__app_{$key}";
			$apps[ $key ] = \IPS\Member::loggedIn()->language()->addToStack( $lang );
		}
	
		$form = new \IPS\Helpers\Form( 'form', 'theme_set_import_button' );
		
		$form->add( new \IPS\Helpers\Form\Upload( 'core_theme_set_new_import', NULL, FALSE, array( 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ), NULL, NULL, NULL, 'core_theme_set_new_import' ) );
		
		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['core_theme_set_new_import'], $tempFile );
			
			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=import' )->setQueryString( array( 'file' => $tempFile, 'key' => md5_file( $tempFile ), 'id' => \IPS\Request::i()->id) )->csrf() );
		}
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Member::loggedIn()->language()->addToStack('theme_set_import_title', FALSE, array( 'sprintf' => array( $themeSet->name ) ) ), $form, FALSE );
	}
	
	/**
	 * Export a theme set form
	 *
	 * @return	void
	 */
	public function exportForm()
	{
		$id = \intval( \IPS\Request::i()->id );
		
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_download_upload' );
		
		$themeSet = \IPS\Theme::load( $id );
		$apps    = array();
		
		foreach( \IPS\Application::applications() as $key => $data )
		{
			$lang = "__app_{$key}";
			$apps[ $key ] = \IPS\Member::loggedIn()->language()->addToStack( $lang );
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'theme_set_export_button' );
		
		$storedAuthor = ( isset( \IPS\Data\Store::i()->theme_stored_author ) AND \is_array( \IPS\Data\Store::i()->theme_stored_author ) ) ? \IPS\Data\Store::i()->theme_stored_author : null;
		
		$form->addHeader( \IPS\Member::loggedIn()->language()->addToStack('theme_set_export_title', FALSE, array( 'sprintf' => array( $themeSet->_title ) ) ) );
		
		if ( \IPS\IN_DEV OR \IPS\Theme::designersModeEnabled() )
		{
			if ( ! isset( \IPS\Request::i()->rebuildDone ) )
			{
				$url = \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=devImportBeforeExport&id=' . $id )->csrf();
				$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'theme_export_designer_mode_build_message', FALSE, array( 'sprintf' => array( $url ) ) ), 'ipsMessage ipsMessage_info', FALSE );
			}
			
			$form->add( new \IPS\Helpers\Form\Text( 'theme_template_export_author_name', ( $storedAuthor !== null ) ? $storedAuthor['name'] : false, false ) );
			$form->add( new \IPS\Helpers\Form\Text( 'theme_template_export_author_url' , ( $storedAuthor !== null ) ? $storedAuthor['url']  : false, false ) );
			$form->add( new \IPS\Helpers\Form\Text( 'theme_update_check' , $themeSet->update_check, false ) );
			
			$form->add( new \IPS\Helpers\Form\Text( 'theme_template_export_version'        , $themeSet->version     , false, array( 'placeholder' => '1.0.0' ) ) );
			$form->add( new \IPS\Helpers\Form\Number( 'theme_template_export_long_version' , $themeSet->long_version, false ) );
		}
		
		if ( $values = $form->values() or \IPS\Request::i()->form_submitted )
		{
			$authorName = $values['theme_template_export_author_name'] ? $values['theme_template_export_author_name'] : $themeSet->author_name;
			$authorUrl  = $values['theme_template_export_author_url'] ? $values['theme_template_export_author_url'] : $themeSet->author_url;
			$version = $values['theme_template_export_version'] ? $values['theme_template_export_version'] : $themeSet->version;
			$longVersion = \is_int( $values['theme_template_export_long_version'] ) ? $values['theme_template_export_long_version'] : $themeSet->long_version;
			$updateCheck = $values['theme_update_check'] ? $values['theme_update_check'] : $themeSet->update_check;
			
			\IPS\Data\Store::i()->theme_stored_author = array(
				'name' => $authorName,
				'url'  => $authorUrl,
			);
			
			/* Init */
			$xml = new \XMLWriter;
			$xml->openMemory();
			$xml->setIndent( TRUE );
			$xml->startDocument( '1.0', 'UTF-8' );
			
			/* Root tag */
			$xml->startElement('theme');
			$xml->startAttribute('name');
			$xml->text( \IPS\Member::loggedIn()->language()->get('core_theme_set_title_' . $themeSet->_id ) );
			$xml->endAttribute();

			$xml->startAttribute('easy_mode');
			$xml->text( $themeSet->by_skin_gen );
			$xml->endAttribute();
			
			$xml->startAttribute('editor_skin');
			$xml->text( $themeSet->editor_skin );
			$xml->endAttribute();

			$xml->startAttribute('author_name');
			$xml->text( $authorName );
			$xml->endAttribute();
			$xml->startAttribute('author_url');
			$xml->text( $authorUrl );
			$xml->endAttribute();
			
			$xml->startAttribute('version');
			$xml->text( $version );
			$xml->endAttribute();
			$xml->startAttribute('long_version');
			$xml->text( $longVersion );
			$xml->endAttribute();
			
			$xml->startAttribute('update_check');
			$xml->text( $updateCheck );
			$xml->endAttribute();

			$xml->startElement('header');
			$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $themeSet->custom_header ) );
			$xml->endElement();

			$xml->startElement('footer');
			$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $themeSet->custom_footer ) );
			$xml->endElement();

			/* Copy logos */
			if( $themeSet->logo_data )
			{
				$logos = json_decode( $themeSet->logo_data, TRUE );

				if( \is_array( $logos ) )
				{
					foreach ( $logos as $file => $data )
					{
						if( isset( $data['url'] ) )
						{
							/* Start the XML */
							$xml->startElement('logo');

							$xml->startAttribute('type');
							$xml->text( $file );
							$xml->endAttribute();

							$xml->startAttribute('height');
							$xml->text( $data['height'] );
							$xml->endAttribute();

							/* Get image data */
							$original = \IPS\File::get( 'core_Theme', $data['url'] );

							$xml->startAttribute('name');
							$xml->text( $original->originalFilename );
							$xml->endAttribute();

							$xml->text( base64_encode( $original->contents() ) );
											
							/* Close the <template> tag */
							$xml->endElement();
						}
					}
				}
			}
						
			/* Loop applications */
			foreach ( \IPS\Application::applications() as $appDir )
			{
				if ( ! $appDir->enabled )
				{
					continue;
				}
				
				/* Initiate the <app> tag */
				$xml->startElement('app');
					
				/* Set key */
				$xml->startAttribute('key');
				$xml->text( $appDir->directory );
				$xml->endAttribute();
					
				/* Set version */
				$xml->startAttribute('version');
				$xml->text( $appDir->long_version );
				$xml->endAttribute();
				
				/* Templates */
				$templates = $themeSet->getRawTemplates( $appDir, '', '', \IPS\Theme::RETURN_ALL );
				if ( isset( $templates[ $appDir->directory ] ) AND \count( $templates[ $appDir->directory ] ) )
				{
					foreach( $templates[ $appDir->directory ] as $loc => $lv )
					{
						foreach( $templates[ $appDir->directory ][ $loc ] as $group => $gv )
						{
							foreach( $templates[ $appDir->directory ][ $loc ][ $group ] as $name => $data )
							{
								/* Skip custom bits from other themes */
								if ( ( $data['InheritedValue'] != 'inherit' and $data['InheritedValue'] != 'changed' ) and ( $data['template_user_added'] and $data['template_added_to'] != $themeSet->_id ) )
								{
									if ( $data['template_set_id'] != $themeSet->_id )
									{
										continue;
									}
								}
								
								/* Remove original template bits */
								if ( $data['InheritedValue'] != 'original' OR $data['template_user_added'] )
								{
									/* Initiate the <template> tag */
									$xml->startElement('template');
									
									foreach( $templates[ $appDir->directory ][ $loc ][ $group ][ $name ] as $k => $v )
									{
										if ( \in_array( \substr( $k, 9 ), array( 'location', 'group', 'name', 'data' ) ) )
										{
											$xml->startAttribute($k);
											$xml->text( $v );
											$xml->endAttribute();
										}
									}
									
									/* Write value */
									if ( preg_match( '/<|>|&/', $data['template_content'] ) )
									{
										$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $data['template_content'] ) );
									}
									else
									{
										$xml->text( $data['template_content'] );
									}
									
									/* Close the <template> tag */
									$xml->endElement();
								}
							}
						}
					}
				}
				
				/* CSS */
				$css = $themeSet->getRawCss( $appDir, '', '', \IPS\Theme::RETURN_ALL );

				if ( isset( $css[ $appDir->directory] ) )
				{
					foreach( $css[ $appDir->directory ] as $loc => $lv )
					{
						foreach( $css[ $appDir->directory ][ $loc ] as $path => $gv )
						{
							foreach( $css[ $appDir->directory ][ $loc ][ $path ] as $name => $data )
							{
								/* Remove original template bits */
								if ( $data['InheritedValue'] != 'original' and trim( $data['css_content'] ) )
								{
									$xml->startElement('css');

									foreach( $css[ $appDir->directory ][ $loc ][ $path ][ $name ] as $k => $v )
									{
										if ( \in_array( \substr( $k, 4 ), array( 'location', 'path', 'name', 'attributes' ) ) )
										{
											$xml->startAttribute($k);
											$xml->text( $v );
											$xml->endAttribute();
										}
									}

									/* Write value */
									if ( preg_match( '/<|>|&/', $data['css_content'] ) )
									{
										$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $data['css_content'] ) );
									}
									else
									{
										$xml->text( $data['css_content'] );
									}

									$xml->endElement();
								}
							}
						}
					}
				}
				
				$parents = array( $themeSet->id );
				try
				{
					foreach( $themeSet->parents() as $parent )
					{
						$parents[] = $parent->_id;
					}
				}
				catch( \OutOfRangeException $e ) { }
		
				/* Append master theme set */
				array_push( $parents, 0 );
				
				$where[] = "resource_set_id IN (" . implode( ',' , $parents ) . ")";
				$resources = array();
				
				/* Resources */
				foreach ( \IPS\Db::i()->select(
					'*, CONCAT( resource_app, resource_location, resource_path, resource_name) as thekey, INSTR(\',' . implode( ',' , $parents ) . ',\', CONCAT(\',\',resource_set_id,\',\') ) as theorder',
					'core_theme_resources',
					array( 'resource_user_edited=1 and resource_set_id IN(' . implode( ',' , $parents ) . ') and resource_app=?', $appDir->directory ),
					'theorder desc'
				) as $data )
				{
					$resources[ $data['thekey'] ] = $data;
				}
				
				foreach( $resources as $key => $data )
				{					
					$xml->startElement('resource');
					
					$xml->startAttribute('name');
					$xml->text( $data['resource_name'] );
					$xml->endAttribute();
					
					$xml->startAttribute('location');
					$xml->text( $data['resource_location'] );
					$xml->endAttribute();
					
					$xml->startAttribute('path');
					$xml->text( $data['resource_path'] );
					$xml->endAttribute();
					
					$xml->startAttribute('user_edited');
					$xml->text( $data['resource_user_edited'] );
					$xml->endAttribute();
					
					/* Theme resources should be raw binary data everywhere (filesystem and DB) except in the theme XML download where they are base64 encoded. */
					$xml->text( base64_encode( $data['resource_data'] ) );
					
					$xml->endElement();
				}
				
				/* Custom fields */
				$settings = \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_app=?', $themeSet->id, $appDir->directory ) )->join( 'core_theme_settings_values', 'sc_id=sv_id' );
				if ( \count( $settings ) )
				{
					foreach ( $settings as $row )
					{
						/* Initiate the <fields> tag */
						$xml->startElement('field');
						
						unset( $row['sc_id'], $row['sc_set_id'] );
						
						foreach( $row as $k => $v )
						{
							if ( !\in_array( $k, array( 'sc_updated', 'sv_value', 'sc_app', 'sv_id' ) ) )
							{
								$xml->startAttribute( $k );
								$xml->text( $v );
								$xml->endAttribute();
							}
						}

						if ( ( $row['sc_type'] == 'Upload' and $row['sv_value'] ) or ( $row['sc_type'] == 'other' and mb_stristr( $row['sc_content'], '\IPS\Helpers\Form\Upload' ) ) )
						{
							foreach( explode( ',', $row['sv_value'] ) as $item )
							{
								try
								{
									$file = \IPS\File::get( 'core_Theme', $item );
									/* Get this first incase any errors are thrown */
									$contents = $file->contents();
									
									$xml->startElement('file');
									
									$xml->startAttribute( 'name' );
									$xml->text( $file->originalFilename );
									$xml->endAttribute();
	
									$xml->text( base64_encode( $contents ) );
	
									$xml->endElement();
								}
								catch( \Exception $ex ) { }
							}
						}
						else if ( $row['sc_type'] !== 'other' )
						{
							/* Write value */
							if ( preg_match( '/<|>|&/', $row['sv_value'] ) )
							{
								$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $row['sv_value'] ) );
							}
							else
							{
								$xml->text( $row['sv_value'] );
							}
						}

						/* Close the <fields> tag */
						$xml->endElement();
					}
				}
				
				/* Close the <app> tag */
				$xml->endElement();
			}
			
			/* Language strings */
			$xml->startElement('language');
			
			$parents = array( $themeSet->id );
			try
			{
				foreach( $themeSet->parents() as $parent )
				{
					$parents[] = $parent->_id;
				}
			}
			catch( \OutOfRangeException $e ) { }
		
			$words = array();
			
			foreach ( \IPS\Db::i()->select(
				'*, INSTR(\',' . implode( ',' , $parents ) . ',\', CONCAT(\',\',word_theme,\',\') ) as theorder',
				'core_sys_lang_words',
				array( 'word_theme IN(' . implode( ',' , $parents ) . ')' ),
				'theorder desc'
			) as $data )
			{
				$words[ $data['word_key'] ] = $data;
			}

			foreach ( $words as $row )
			{
				$xml->startElement( 'word' );
				$xml->startAttribute('key');
				$xml->text( $row['word_key'] );
				$xml->endAttribute();
				if ( preg_match( '/<|>|&/', $row['word_default'] ) )
				{
					$xml->writeCData( str_replace( ']]>', ']]]]><![CDATA[>', $row['word_default'] ) );
				}
				else
				{
					$xml->text( $row['word_default'] );
				}
				$xml->endElement();
			}
			$xml->endElement();
			
			/* Finish */
			$xml->endDocument();
			
			\IPS\Session::i()->log( 'acplog__theme_exported', array( "core_theme_set_title_{$themeSet->_id}" => TRUE ) );
						
			\IPS\Output::i()->sendOutput( $xml->outputMemory(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', \IPS\Member::loggedIn()->language()->get('core_theme_set_title_' . $themeSet->_id  ) . " {$version}.xml" ), FALSE, FALSE ) );
		}
		
		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block(  \IPS\Member::loggedIn()->language()->addToStack('theme_set_export_title', FALSE, array( 'sprintf' => array( $themeSet->_title ) ) ), $form, FALSE );
	}
	
	/**
	 * Delete Theme
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_sets_manage' );

		try
		{
			$theme = \IPS\Theme::load( \IPS\Request::i()->id );
			if ( $theme->is_default )
			{
				\IPS\Output::i()->error( 'cannot_delete_default_theme', '1C163/3', 403, '' );
			}
		}
		catch ( \OutOfRangeException $e ) {}

		$parentResult = parent::delete();

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		return $parentResult;
	}
	
	/**
	 * Add template form.
	 * This is never used for editing as this is done via the template/css manager
	 *
	 * @return	void
	 */
	public function templateForm()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		$id = \intval( \IPS\Request::i()->id );
		$locations = array();
		$groups = array();
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->hiddenValues['id'] = $id;
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_template_name', NULL, TRUE, array( 'regex' => '/^([a-z_][A-Z0-9_]+?)$/i' ),
		function( $val )
		{
			if ( ! preg_match( '/^([a-z_][A-Z0-9_]+?)$/i', $val ) )
			{
				throw new \InvalidArgumentException( 'form_bad_value' );
			}
		
			$group = ( \IPS\Request::i()->theme_template_group_type == 'existing' ) ? \IPS\Request::i()->theme_template_group_existing    : \IPS\Request::i()->theme_template_group_new;
			
			/* Make sure key is unique */
			try
			{
				$row = \IPS\Db::i()->select( '*', 'core_theme_templates', array( "template_set_id=? AND template_app=? AND template_group=? AND template_location=? AND template_name=?", 0, \IPS\Request::i()->theme_template_app, $group, \IPS\Request::i()->theme_template_location, $val ) )->first();
				
				if ( isset( $row['template_id'] ) )
				{
					throw new \InvalidArgumentException('core_theme_error_template_name_exists');
				}
			}
			catch( \UnderflowException $e )
			{
				/* Name is OK as select failed */
			}
			
			return true;
		} ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_template_data', NULL, FALSE, array( 'placeholder' => '&#36;foo=array()' ) ) );
		
		/* Locations */
		$locations = iterator_to_array( \IPS\Db::i()->select( 'template_location', 'core_theme_templates' )->setKeyField( 'template_location' )->setValueField('template_location') );

		unset( $locations['admin'] );
		
		/* Groups */
		$groups = iterator_to_array( \IPS\Db::i()->select( 'template_group', 'core_theme_templates', NULL, 'template_group ASC' )->setKeyField( 'template_group' )->setValueField('template_group') );

		/* Apps */
		$apps = array();
		
		foreach( \IPS\Application::applications() as $key => $data )
		{
			$apps[ $key ] = $data->_title;
		}
		
		$form->add( new \IPS\Helpers\Form\Select( 'theme_template_location', NULL, FALSE, array( 'options' => $locations ), NULL, NULL, NULL, 'location' ) );
			
		$form->add( new \IPS\Helpers\Form\Radio( 'theme_template_group_type', 'existing', FALSE, array(
					'options'  => array( 'existing' => 'theme_template_group_o_existing',
										 'new'	    => 'theme_template_group_o_new' ),
					'toggles'  => array( 'existing' => array( 'group_existing' ),
										 'new'      => array( 'group_new' ) )
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_template_group_new', NULL, FALSE, array( 'regex' => '/^([a-z_][a-z0-9_]+?)?$/' ), NULL, NULL, NULL, 'group_new' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'theme_template_group_existing', NULL, FALSE, array( 'options' => $groups, 'parse' => 'normal' ), NULL, NULL, NULL, 'group_existing' ) );
		
		$form->add( new \IPS\Helpers\Form\Select( 'theme_template_app', NULL, TRUE, array( 'options' => $apps ) ) );
		
		if( ! \IPS\Request::i()->isAjax() )
		{
			$form->add( new \IPS\Helpers\Form\TextArea( 'theme_template_content', NULL ) );
		}
		
		if ( $values = $form->values() )
		{
			$id   = \intval( \IPS\Request::i()->id );
			$save = array( 'set_id'	   => $id,
						   'app'	   => $values['theme_template_app'],
						   'location'  => $values['theme_template_location'],
						   'group' 	   => ( $values['theme_template_group_type'] == 'existing' ) ? $values['theme_template_group_existing'] : $values['theme_template_group_new'],
						   'name' 	   => $values['theme_template_name'],
						   'added_to'  => $id,
						   'content'   => $values['theme_template_content'],
						   'variables' => $values['theme_template_data'] );
			
			try
			{
				$newId = \IPS\Theme::addTemplate( $save );
			}
			catch( \OverflowException $ex )
			{
				throw new \InvalidArgumentException( 'form_bad_value' );
			}
			
			\IPS\Theme::i()->load( $id )->compileTemplates( $save['app'], $save['location'], $save['group'] );
			
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'id' 		=> $newId,
					'app' 		=> $save['app'],
					'location' 	=> $save['location'],
					'group'		=> $save['group'],
					'name'		=> $save['name']
				)	);
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . $id ), 'saved' );
			}
		}
		
		/* Display */
		$formHTML = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_set_add_html', $form, FALSE );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'customization' )->templateEditorAddForm( $formHTML, 'templates' );
	}
	
	/**
	 * Manually build CSS and HTML ready for use by the output engine
	 *
	 * @return	void
	 */
	public function build()
	{
		\IPS\Session::i()->csrfCheck();
		
		$set = \IPS\Theme::load( \IPS\Request::i()->id );
		
		/* Resources has to come before CSS otherwise CSS url()s are out of date as resource build changes resource URL after CSS has been built */
		$set->compileTemplates();
		$set->buildResourceMap();
		$set->compileCss();
		
 		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'completed' );
	}
	
	/**
	 * Delete a template.
	 * This can be either a CSS template or a HTML template
	 *
	 * @return	void
	 */
	public function deleteTemplate()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		$type    = \IPS\Request::i()->t_type;
		$id      = \intval( \IPS\Request::i()->id );
		$item_id = \intval( \IPS\Request::i()->t_item_id );
		
		try
		{
			if ( $type == 'templates' )
			{
				$template = \IPS\Theme::load( $id )->deleteTemplateById( $item_id );
				
				if ( isset( $template['template_content'] ) )
				{
					$template['type'] 		= 'templates';
					$template['name'] 		= $template['template_name'];
					$template['app']		= $template['template_app'];
					$template['location']	= $template['template_location'];
					$template['group']		= $template['template_group'];
				}
			}
			else
			{
				$template = \IPS\Theme::load( $id )->deleteCssById( $item_id );
				
				if ( isset( $template['css_content'] ) )
				{
					$template['type'] 		= 'css';
					$template['name'] 		= $template['css_name'];
					$template['app']		= $template['css_app'];
					$template['location']	= $template['css_location'];
					$template['group']		= $template['css_path'];
				}
			}
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'node_error', '3S121/1', 500, '' );
		}
		catch( \UnderflowException $ex )
		{
			/* This means the template to delete was not found - so we really don't need to worry about the error */
			$template = array();
		}
		
		if ( isset( $template['app'] ) )
		{
			\IPS\Session::i()->log( 'acplogs__themetemplate_deleted', array( 'app: ' . $template['app'] => FALSE, 'location: ' . $template['location'] => FALSE, 'group: ' . $template['group'] => FALSE, 'name: ' . $template['name'] => FALSE ) );
		}
		
		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( $template );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . $id . '&t_type=' . $type ), 'completed' );
		}
	}
	
	/**
	 * Save master template.
	 * This can be either a CSS template or a HTML template
	 *
	 * @return	void
	 */
	public function saveTemplate()
	{
		\IPS\Session::i()->csrfCheck();
		
		$type		= \IPS\Request::i()->t_type;
		$editorName = 'editor_' . \IPS\Request::i()->t_key;
		$url		= array();
		$log        = array();
		
		$save = array( 'app' 	  => \IPS\Request::i()->t_app,
					   'location' => \IPS\Request::i()->t_location,
					   'group'    => \IPS\Request::i()->t_group,
					   'name'     => \IPS\Request::i()->t_name,
					   'set_id'   => \intval( \IPS\Request::i()->id ),
					   'item_id'  => \intval( \IPS\Request::i()->t_item_id ),
					   'key'	  => \IPS\Request::i()->t_key,
					   'content'  => \IPS\Request::i()->$editorName );
		try
		{
			if ( $type == 'templates' )
			{
				$variablesName = 'variables_' . \IPS\Request::i()->t_key;
				
				/* Extra template only variables */
				$save['variables'] = \IPS\Request::i()->$variablesName;
				
				$newId = \IPS\Theme::load( $save['set_id'] )->saveTemplate( $save );
			}
			else
			{
				$attributesName = 'attributes_' . \IPS\Request::i()->t_key;
				
				/* Extra template only variables */
				$save['attributes'] = \IPS\Request::i()->$attributesName;

				/* This is likely a customization of a core CSS file which was allowed previously, but now is not. So let's inform the user it cannot be saved */
				if ( $save['app'] === 'core' and $save['location'] === 'global' )
				{
					\IPS\Output::i()->error( 'cant_save_global_css_changes_now_sorry', '3C163/Z', 500, '' );
				}
				
				$newId = \IPS\Theme::load( $save['set_id'] )->saveCss( $save );
			}
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Log::log( $ex, 'template_error' );
			\IPS\Output::i()->error( 'node_error', '3S121/2', 500, '' );
		}
		catch( \UnderflowException $ex )
		{
			\IPS\Log::log( $ex, 'template_error' );
			\IPS\Output::i()->error( 'node_error', '3C163/I', 500, '' );
		}
		catch( \InvalidArgumentException $ex )
		{
			$msg = $ex->getMessage();
			
			if ( mb_stristr( $msg, 'invalid_plugin:' ) )
			{
				$msg = \IPS\Member::loggedIn()->language()->addToStack('core_theme_invalid_plugin', FALSE, array( 'sprintf' => array( str_replace( 'invalid_plugin:', '', $msg ) ) ) );
			}
			
			\IPS\Output::i()->error( $msg, '3S121/3', 500, '' );
		}
	
		foreach( $save as $k => $v )
		{
			if ( \in_array( $k, array( 'app', 'location', 'group', 'name' ) ) )
			{
				$url[]      = 't_' . $k . '=' . $v;
				$log[ $k . ': ' . $v ] = FALSE;
			}
		}
		
		\IPS\Session::i()->log( 'acplogs__themetemplate_updated', $log, true );

		/* Clear guest page caches */
		\IPS\Data\Cache::i()->clearAll();

		if(  \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'item_id' => $newId ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . $save['set_id'] . '&t_type=' . $type . '&' . implode( '&', $url ) ), 'completed' );
		}
	}
	
	/**
	 * Rebuild master templates
	 *
	 * @return	void
	 */
	public function devImportBeforeExport()
	{
		$this->devImportMultipleRedirect( "app=core&module=customization&controller=themes&do=exportForm&id=" . \IPS\Request::i()->id . '&rebuildDone=1' );
	}
	
	/**
	 * Rebuild master templates
	 *
	 * @return	void
	 */
	public function devImport()
	{
		$this->devImportMultipleRedirect( 'app=core&module=customization&controller=themes' );
	}
	
	/**
	 * Rebuild master templates
	 *
	 * @param	string|NULL		$finalUrl	Final redirect URL
	 * @return	void
	 */
	public function devImportMultipleRedirect( $finalUrl=NULL )
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect(
			\IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=devImportMultipleRedirect&id=' . \IPS\Request::i()->id . '&finalUrl=' . base64_encode( $finalUrl ) )->csrf(),
			function( $data )
			{
				/* Is this the first cycle? */
				if ( ! \is_array( $data ) )
				{
					$toImport = array();
					
					foreach( \IPS\Application::applications() as $app => $data )
					{
						$toImport[ $app ] = array( 'html', 'css', 'resources' );
					}
					
					/* Start importing */
					$data = array( 'toImport' => $toImport );
						
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
	
				/* Grab something to build */
				if ( \count( $data['toImport'] ) )
				{
					reset( $data['toImport'] );
					$app   = key( $data['toImport'] );
					$types = $data['toImport'][ $app ];
					
					foreach( $types as $id => $type )
					{
						switch( $type )
						{
							case 'html':
								if( \IPS\Theme::designersModeEnabled() !== true )
								{
									\IPS\Theme::i()->importDevHtml( $app, 0 );
									\IPS\Theme::load( 1 )->compileTemplates( $app );
								}
								else
								{
									\IPS\Theme\Advanced\Theme::load( \IPS\Request::i()->id )->importDevHtml( $app, \IPS\Request::i()->id );
									\IPS\Theme::load( \IPS\Request::i()->id )->compileTemplates( $app );
								}

								unset( $data['toImport'][ $app ][0] );
							break;
							case 'css':
								if( \IPS\Theme::designersModeEnabled() !== true )
								{
									\IPS\Theme::i()->importDevCss( $app, 0 );
									\IPS\Theme::load( 1 )->compileCss( $app );
								}
								else
								{
									\IPS\Theme\Advanced\Theme::load( \IPS\Request::i()->id )->importDevCss( $app, \IPS\Request::i()->id );
									\IPS\Theme::load( \IPS\Request::i()->id )->compileCss( $app );
								}
								unset( $data['toImport'][ $app ][1] );
							break;
							case 'resources':
								if( \IPS\Theme::designersModeEnabled() !== true )
								{
									\IPS\Theme::i()->importDevResources( $app, 0 );
									\IPS\Theme::load( 1 )->buildResourceMap( $app );
								}
								else
								{
									\IPS\Theme\Advanced\Theme::load( \IPS\Request::i()->id )->importDevResources( $app, \IPS\Request::i()->id );
									\IPS\Theme::load( \IPS\Request::i()->id )->buildResourceMap( $app );
								}
								/* All done */
								unset( $data['toImport'][ $app ] );
							break;
						}
					}
					
					return array( $data, \IPS\Member::loggedIn()->language()->addToStack('processing') );
				}
				else
				{
					/* All Done.. */
					return null;
				}
			},
			function()
			{
				/* Finished */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( base64_decode( \IPS\Request::i()->finalUrl ) ), 'completed' );
			}
		);
	}
	
	/**
	 * Diff list
	 *
	 * @return void
	 */
	public function diff()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		/* Grab groups */
		$id = \intval( \IPS\Request::i()->id );
		
		if ( $id === 1 AND \IPS\IN_DEV )
		{
			$diff = \IPS\Theme::master( $id )->getDiff();
		}
		else
		{
			$diff = \IPS\Theme::load( $id )->getDiff();
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
				'download'	=> array(
						'icon'	=> 'download',
						'link'	=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=diffexport&id=' . $id ),
						'title'	=> 'core_theme_set_diff_export',
				)
		);
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/diff.css', 'core', 'admin' ) );

		/* Display */
		\IPS\Output::i()->breadcrumb = array(
				array(
						\IPS\Http\Url::internal('app=core&module=customization&controller=themes'),
						'menu__' . \IPS\Dispatcher::i()->application->directory . '_' . \IPS\Dispatcher::i()->module->key
				),
				array(
						NULL,
						\IPS\Member::loggedIn()->language()->addToStack( 'core_theme_set_title_' . $id )
				)
			);

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'customization', 'core' )->diff( \IPS\Theme::load($id), $diff );
	}
	
	/**
	 * Diff Export as HTML document
	 *
	 * @return void
	 */
	public function diffexport()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		/* Grab groups */
		$id = \intval( \IPS\Request::i()->id );
		
		if ( $id === 1 AND \IPS\IN_DEV )
		{
			$diff = \IPS\Theme::master( $id )->getDiff();
		}
		else
		{
			$diff = \IPS\Theme::load( $id )->getDiff();
		}
		
		$html = \IPS\Theme::i()->getTemplate( 'customization', 'core' )->diffExportWrapper( \IPS\Theme::i()->getTemplate( 'customization', 'core' )->diff( \IPS\Theme::load($id), $diff ) );
		
		$name = 'export_' . addslashes( str_replace( array( ' ', '.', ',' ), '_', \IPS\Theme::load( $id )->name ) . '.html' );
			
		\IPS\Output::i()->sendOutput( $html, 200, 'text/html', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', $name ) ) );
	}
	
	/**
	 * Show a difference report for an individual template or CSS file
	 *
	 * @return	void
	 */
	protected function diffTemplate()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );
		
		if ( \IPS\Request::i()->t_type == 'css' )
		{
			$customVersion = \IPS\Db::i()->select( '*', 'core_theme_css', array( 'css_id=?', (int) \IPS\Request::i()->t_item_id ) )->first();
			$rawCss = \IPS\Theme::master()->getRawCss( $customVersion['css_app'], $customVersion['css_location'], $customVersion['css_path'], \IPS\Theme::RETURN_ALL, TRUE );
			$original = $rawCss[ $customVersion['css_app'] ][ $customVersion['css_location'] ][ $customVersion['css_path'] ][ $customVersion['css_name'] ]['css_content'];
		}
		else
		{
			$theme = \IPS\Theme::load( \IPS\Request::i()->id );
			$customVersion = \IPS\Db::i()->select( '*', 'core_theme_templates', array( 'template_id=?', (int) \IPS\Request::i()->t_item_id ) )->first();
			$master = \IPS\Theme::master()->getRawTemplates( $customVersion['template_app'], $customVersion['template_location'], $customVersion['template_group'], \IPS\Theme::RETURN_ALL, TRUE );
			$rawTemplates = [];

			if ( isset( \IPS\Request::i()->type ) and \IPS\Request::i()->type == 'parent' and $theme->parent() )
			{
				$rawTemplates = $theme->parent()->getRawTemplates( $customVersion['template_app'], $customVersion['template_location'], $customVersion['template_group'], \IPS\Theme::RETURN_ALL, TRUE );
			}

			if ( ! isset( $rawTemplates[ $customVersion['template_app'] ][ $customVersion['template_location'] ][ $customVersion['template_group'] ][ $customVersion['template_name'] ] ) )
			{
				$rawTemplates = $master;
			}

			$original = isset( $rawTemplates[ $customVersion['template_app'] ][ $customVersion['template_location'] ][ $customVersion['template_group'] ][ $customVersion['template_name'] ] ) ? $rawTemplates[ $customVersion['template_app'] ][ $customVersion['template_location'] ][ $customVersion['template_group'] ][ $customVersion['template_name'] ]['template_content'] : '';
		}
		
		\IPS\Output::i()->json( $original );
	}
		
	/**
	 * Template/CSS management
	 *
	 * @return	void
	 */
	public function templates()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_templates_manage' );

		$id = \intval( \IPS\Request::i()->id );
		$themeSet = \IPS\Theme::load( $id );

		\IPS\Output::i()->sidebar['actions']['advancedmode'] = array(
			'title'		=> ( \IPS\Settings::i()->theme_editing_advanced_mode == 1 ) ? 'theme_advanced_mode_off' : 'theme_advanced_mode_on',
			'icon'		=> 'paint-brush',
			'primary'   => \IPS\Settings::i()->theme_editing_advanced_mode == 1,
			'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=advancedEditingToggle&from=' . $id ),
			'data'	    => array( 'confirm' => '', 'confirmSubMessage' => ( \IPS\Settings::i()->theme_editing_advanced_mode ) ? \IPS\Member::loggedIn()->language()->addToStack( 'theme_advanced_mode_submessage_off' ) : \IPS\Member::loggedIn()->language()->addToStack( 'theme_advanced_mode_submessage_on' ) )
		);

		/* Display */
		\IPS\Output::i()->responsive = FALSE;
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('theme_editing_title', FALSE, array( 'sprintf' => array( $themeSet->_title ) ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'codemirror/codemirror.css', 'core', 'interface' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'customization/templates.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_templates.js', 'core' ) );

		$name = 'core_theme_set_title_' . $id;

		/* Display */
		\IPS\Output::i()->breadcrumb = array(
			array(
				\IPS\Http\Url::internal('app=core&module=customization&controller=themes'),
				'menu__' . \IPS\Dispatcher::i()->application->directory . '_' . \IPS\Dispatcher::i()->module->key
			),
			array(
				NULL,
				\IPS\Member::loggedIn()->language()->addToStack( $name )
			)
		);

		/* Are we using the very lovely simple mode? */
		if ( ! \IPS\Settings::i()->theme_editing_advanced_mode )
		{
			$form = new \IPS\Helpers\Form( 'form', 'theme_simple_save_all' );
			$form->hiddenValues['id'] = $id;
			$form->class = 'ipsForm_vertical';

			$customCss = $themeSet->getRawCss( NULL, 'front', 'custom', \IPS\Theme::RETURN_ALL );
			$cssData = NULL;
			$itemId = NULL;

			$cssFiles = 0;
			foreach( $customCss as $app => $data )
			{
				if ( isset( $customCss[ $app ]['front']['custom'] ) )
				{
					foreach ( $customCss[ $app ]['front']['custom'] as $name => $data )
					{
						if ( $name == 'custom.css' )
						{
							$itemId = $data['css_id'];
							$cssData .= "\n" . $data['css_content'];
						}
						/* Any CSS files in the 'custom' folder that are prefixed with an underscore are 'hidden' to the simple theme editor */
						else if ( mb_substr( $name, 0, 1 ) != '_' )
						{
							$cssData .= "\n\n/* ===[Filename:" . $app . '/custom/' . $name . "]=== */\n\n" . $data['css_content'];
						}
						else
						{
							continue;
						}

						$cssFiles++;
					}
				}
			}

			$form->addTab('theme_simple_header_tab');
			$form->add( new \IPS\Helpers\Form\TextArea( 'theme_simple_header', $themeSet->custom_header, FALSE, [ 'mode' => 'htmlmixed', 'height' => 800 ] ) );

			$form->addTab('theme_simple_footer_tab');
			$form->add( new \IPS\Helpers\Form\TextArea( 'theme_simple_footer', $themeSet->custom_footer, FALSE, [ 'mode' => 'htmlmixed', 'height' => 800 ] ) );

			$form->addTab('theme_simple_css_tab');

			if ( $cssFiles > 1 )
			{
				$form->addMessage( 'theme_simple_lots_of_css_files', 'ipsMessage ipsMessage_warning' );
			}

			$form->add( new \IPS\Helpers\Form\TextArea( 'theme_simple_css', $cssData, FALSE, [ 'mode' => 'css', 'height' => 800 ] ) );

			if ( $values = $form->values() )
			{
				/* Test the syntax */
				$errors = [];
				foreach( [ 'header', 'footer', 'css' ] as $key )
				{
					try
					{
						\IPS\Theme::checkTemplateSyntax( $values['theme_simple_' . $key ] );
					}
					catch( \LogicException $e )
					{
						$errors[] = \IPS\Member::loggedIn()->language()->addToStack( 'theme_error_in_template', NULL, [ 'sprintf' => [ \IPS\Member::loggedIn()->language()->get('theme_simple_' . $key . '_tab') ] ] );
					}
				}

				if ( \count( $errors ) )
				{
					\IPS\Output::i()->error( implode( ". ", $errors ), '3C163/I', 500, '' );
				}

				$themeSet->custom_header = $values['theme_simple_header'];
				$themeSet->custom_footer = $values['theme_simple_footer'];
				$themeSet->save();

				$headerKey = 'custom_header_' . $themeSet->id;
				$footerKey = 'custom_footer_' . $themeSet->id;
				unset( \IPS\Data\Store::i()->$headerKey);
				unset( \IPS\Data\Store::i()->$footerKey);

				/* Now remove any additional CSS files */
				if ( $cssFiles > 1 )
				{
					foreach( $customCss as $app => $data )
					{
						if ( isset( $customCss[ $app ]['front']['custom'] ) )
						{
							foreach ( $customCss[ $app ]['front']['custom'] as $name => $data )
							{
								/* Any CSS files in the 'custom' folder that are prefixed with an underscore are 'hidden' to the simple theme editor */
								if ( mb_substr( $name, 0, 1 ) != '_' )
								{
									if ( !( $app == 'core' and $name == 'custom.css' ) )
									{
										\IPS\Theme::removeCss( $app, 'front', 'custom', NULL, TRUE, $name );
									}
								}
							}
						}
					}
				}

				/* Finally, save the theme */
				$themeSet->saveCss( [
					'app' => 'core',
					'location' => 'front',
					'group' => 'custom',
					'name' => 'custom.css',
					'content' => $values['theme_simple_css'],
					'set_id' => $id,
					'item_id' => $itemId
				] );

				/* Remove compiled templates to ensure the globalTemplate gets the latest updates */
				\IPS\Theme::deleteCompiledTemplate( 'core', 'front', 'global', $themeSet->_id );

				if( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( ['result' => 'ok' ] );
				}
				else
				{
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . $id ), 'saved' );
				}
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'customization' )->templateEditorSimple( $themeSet, $form );
		}
		else
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/diff_match_patch.js', 'core', 'interface' ) );
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'codemirror/codemirror.js', 'core', 'interface' ) );

			/* Grab groups */
			$t_type = \IPS\Request::i()->t_type ?: 'templates';
			$t_app = \IPS\Request::i()->t_app ?: 'core';
			$t_location = \IPS\Request::i()->t_location ?: ( ( \IPS\Request::i()->t_app ) ? false : 'front' );

			$hasHistory = \IPS\Db::i()->select( 'count(*) as cnt', 'core_theme_content_history', array('content_set_id=?', $id) )->setValueField( 'cnt' )->first();

			if ( $id === 1 and \IPS\IN_DEV or $hasHistory )
			{
				\IPS\Output::i()->sidebar['actions']['diff'] = [
						'icon' => 'cog',
						'link' => \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=diff&id=' . $id ),
						'title' => 'core_theme_set_diff'
				];
			}

			if ( $t_type == 'templates' )
			{
				$t_group = \IPS\Request::i()->t_group ?: ( ( \IPS\Request::i()->t_app ) ? false : 'global' );
				$t_template = \IPS\Request::i()->t_name ?: ( ( \IPS\Request::i()->t_app ) ? false : 'globalTemplate' );
			}
			else
			{
				$t_group = \IPS\Request::i()->t_group ?: ( ( \IPS\Request::i()->t_app ) ? false : 'custom' );
				$t_template = \IPS\Request::i()->t_name ?: ( ( \IPS\Request::i()->t_app ) ? false : 'custom.css' );
			}

			$groups = array();
			$bitNames = array();
			$template = array();

			if ( $t_type == 'templates' )
			{
				$templateNames = $themeSet->getRawTemplates( '', '', '', \IPS\Theme::RETURN_ALL_NO_CONTENT );
				$templateBits = $themeSet->getRawTemplates( $t_app, $t_location, $t_group, \IPS\Theme::RETURN_ALL );
				$templateBit = ( !empty( $t_template ) and !empty( $templateBits[$t_app][$t_location][$t_group][$t_template] ) ) ? $templateBits[$t_app][$t_location][$t_group][$t_template] : null;
				$itemId = $templateBit['template_id'];
			}
			else
			{
				$templateNames = $themeSet->getRawCss( '', '', '', \IPS\Theme::RETURN_ALL_NO_CONTENT );
				$templateBits = $themeSet->getRawCss( $t_app, $t_location, $t_group, \IPS\Theme::RETURN_ALL );
				$templateBit = ( !empty( $t_template ) ) ? $templateBits[$t_app][$t_location][$t_group][$t_template] : null;
				$itemId = $templateBit['css_id'];
			}

			if ( $t_type == 'templates' )
			{
				/* Remove Admin Templates */
				foreach ( $templateNames as $app => $items )
				{
					foreach ( $templateNames[$app] as $location => $items )
					{
						if ( $location == 'admin' )
						{
							unset( $templateNames[$app][$location] );
						}
					}
				}
			}
			else
			{
				$cssFiles = [];
				/* Remove all but custom/custom.css */
				foreach ( $templateNames as $app => $items )
				{
					foreach ( $templateNames[$app] as $location => $items )
					{
						if ( $location === 'front' or $location === 'global' )
						{
							foreach ( $templateNames[$app][$location] as $path => $items )
							{
								if ( $path == 'custom' )
								{
									$cssFiles[ $app ][ $location ][ $path ] = $items;
								}
								else
								{
									foreach( $items as $item )
									{
										if ( $item['css_added_to'] == $themeSet->_id or $item['css_user_edited'] )
										{
											$cssFiles[ $app ][ $location ][ $path ][ $item['css_name'] ] = $item;
										}
									}
								}
							}
						}
					}
				}

				$templateNames = $cssFiles;
			}

			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'customization' )->templateEditor( $themeSet, $templateNames, $templateBit, array( 'app' => $t_app, 'location' => $t_location, 'group' => $t_group, 'template' => $t_template, 'type' => $t_type, 'item_id' => $itemId ) );
		}
	}

	/**
	 * Toggle between the simple and advanced editing modes
	 *
	 * @return void
	 */
	public function advancedEditingToggle()
	{
		\IPS\Settings::i()->changeValues( [ 'theme_editing_advanced_mode' => ( \IPS\Settings::i()->theme_editing_advanced_mode ) ? 0 : 1 ] );

		if ( isset( \IPS\Request::i()->from ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes&do=templates&id=' . \IPS\Request::i()->from ), 'theme_mode_switched' );
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'theme_mode_switched' );
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
				\IPS\Db::i()->update( 'core_members', array( 'skin' => \IPS\Request::i()->id ), $where );
			}
			else
			{
				\IPS\Member::updateAllMembers( array( 'skin' => \IPS\Request::i()->id ) );
			}
			
			\IPS\Session::i()->log( 'acplog__theme_member_reset' );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themes' ), 'member_theme_reset' );
		}

		\IPS\Output::i()->output = $form;
	}
}
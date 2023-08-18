<?php
/**
 * @brief		theme Settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 May 2013
 */

namespace IPS\core\modules\admin\customization;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * themesettings
 */
class _themesettings extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_sets_manage' );
		
		parent::execute();

		/* Display */
		\IPS\Output::i()->breadcrumb = array(
				array(
						\IPS\Http\Url::internal('app=core&module=customization&controller=themes'),
						'menu__' . \IPS\Dispatcher::i()->application->directory . '_' . \IPS\Dispatcher::i()->module->key
				),
				array(
						NULL,
						\IPS\Member::loggedIn()->language()->addToStack( 'core_theme_set_title_' . \intval( \IPS\Request::i()->set_id ) )
				)
			);
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Load Theme */
		try
		{
			$theme = \IPS\Theme::load( \IPS\Request::i()->set_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C260/1', 404, '' );
		}
		
		if ( \IPS\Theme::designersModeEnabled() )
		{
			\IPS\Theme\Advanced\Theme::loadLanguage( $theme->id );
		}
			
		/* What tabs do we have? */
		$tabs = array();
		foreach ( \IPS\Db::i()->select( 'sc_tab_key', 'core_theme_settings_fields', array( 'sc_set_id=?', $theme->id ), NULL, NULL, 'sc_tab_key' ) as $tab )
		{
			$tabs[ $tab ] = 'theme_custom_tab_' . $tab;
		}
		
		/* Which is the active tab? */
		if ( isset( \IPS\Request::i()->tab ) and isset( $tabs[ \IPS\Request::i()->tab ] ) )
		{
			$activeTab = \IPS\Request::i()->tab;
		}
		else
		{
			$_tabs = array_keys( $tabs );
			$activeTab = array_shift( $_tabs );
		}

		/* Create tree */
		$self = $this;
		$tree = new \IPS\Helpers\Tree\Tree(
			\IPS\Http\Url::internal( "app=core&module=customization&controller=themesettings&set_id={$theme->id}" ),
			"TITLE",
			// getRoots
			function() use( $theme, $activeTab, $self )
			{
				$return = array();
				foreach( \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_tab_key=?', $theme->id, $activeTab ), 'sc_order' ) as $row )
				{
					$return[ $row['sc_id'] ] = $self->_settingRow( $row );
				}
				return $return;
			},
			// getRow
			function( $id ) use ( $self )
			{
				return $self->_settingRow( \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_id=?', $id ) )->first() );
			},
			// getRowParentId
			function()
			{
				return NULL;
			},
			// getChildren
			function()
			{
				return array();
			},
			// getRootButtons
			function()
			{
				return array();
			},
			FALSE,
			TRUE,
			TRUE
		);
		
		/* If this is an AJAX request, just return it */
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->output = (string) $tree;
			return;
		}
		
		/* Output */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->tabs( $tabs, $activeTab, $tree, \IPS\Http\Url::internal( "app=core&module=customization&controller=themesettings&set_id={$theme->id}" ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('theme_custom_setting_page_title');
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_settings_add' ) )
		{
			\IPS\Output::i()->sidebar['actions']['add'] = array(
				'icon'		=> 'plus',
				'title'		=> 'theme_custom_setting_add',
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('theme_custom_setting_add') ),
				'link'		=> \IPS\Http\Url::internal("app=core&module=customization&controller=themesettings&set_id={$theme->id}&do=form"),
			);
		}
	}
	
	/**
	 * Get row for tree
	 *
	 * @param	array	$setting	The row from the database
	 * @return	string
	 */
	public function _settingRow( $setting )
	{
		$buttons = array();
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_settings_add' ) )
		{
			$buttons['edit'] = array(
				'icon'		=> 'pencil',
				'title'		=> 'edit',
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack( $setting['sc_title'] ) ),
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themesettings&do=form&id=' ) . $setting['sc_id'] . '&set_id=' . $setting['sc_set_id'],
			);
		}
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'customization', 'theme_settings_remove' ) )
		{
			$buttons['delete'] = array(
				'icon'		=> 'times-circle',
				'title'		=> 'delete',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=customization&controller=themesettings&do=delete&id=' ) . $setting['sc_id'] . '&set_id=' . $setting['sc_set_id'],
				'data'		=> array( 'delete' => '' ),
			);
		}
						
		return \IPS\Theme::i()->getTemplate( 'trees', 'core' )->row(
			\IPS\Http\Url::internal( "app=core&module=customization&controller=themesettings&set_id={$setting['sc_set_id']}" ),
			$setting['sc_id'],
			\IPS\Member::loggedIn()->language()->addToStack( $setting['sc_title'] ),
			FALSE,
			$buttons,
			$setting['sc_key'],
			NULL,
			$setting['sc_order'],
			FALSE,
			NULL,
			NULL,
			NULL,
			FALSE,
			FALSE,
			FALSE
		);
	}
	
	/**
	 * Tree sorting calls to do=reorder, but we want to let the individual tabs handle that in this case.
	 * This method just takes the request and passes it to manage(), which in turn passes it to the correct tab, which then finally handles the reordering.
	 *
	 * @return void
	 */
	public function reorder()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Normalise AJAX vs non-AJAX */
		if( isset( \IPS\Request::i()->ajax_order ) )
		{
			$order = array();
			$position = 1;
			foreach( \IPS\Request::i()->ajax_order as $id => $parent )
			{
				$order[ $id ] = $position++;
			}
		}
		/* Non-AJAX way */
		else
		{
			$order = \IPS\Request::i()->order;
		}
		
		/* Okay, now order */
		foreach( $order as $id => $position )
		{
			\IPS\Db::i()->update( 'core_theme_settings_fields', array( 'sc_order' => $position ), array( 'sc_id=?', $id ) );
		}
		
		/* Write themesettings.json for the default theme */
		if ( \IPS\IN_DEV and \IPS\Request::i()->set_id == 1 )
		{ 
			\IPS\Theme\Dev\Theme::writeThemeSettingsToDisk();
		}
		
		/* If this is an AJAX request, just respond */
		if( \IPS\Request::i()->isAjax() )
		{
			return;
		}
		/* Otherwise, redirect */
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=customization&controller=themesettings&set_id=" . \IPS\Request::i()->set_id ) );
		}
	}
	
	/**
	 * Add/Edit Theme Setting
	 *
	 * @return	void
	 */
	public function form()
	{
		$current = NULL;
		$setId   = \intval( \IPS\Request::i()->set_id );
		$id      = \intval( \IPS\Request::i()->id );
		$set     = \IPS\Theme::load( $setId );
		
		if ( $id )
		{
			$current = \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_id=?', $id ) )->first();
		}
		else
		{
			/* Check permission */
			\IPS\Dispatcher::i()->checkAcpPermission( 'theme_settings_add' );
		}
		
		/* Apps */
		$apps = array();
		
		foreach( \IPS\Application::applications() as $key => $data )
		{
			$apps[ $key ] = $data->_title;
		}
		
		/* Tab keys */
		$tabs = iterator_to_array( \IPS\Db::i()->select( 'sc_tab_key', 'core_theme_settings_fields', array( 'sc_set_id=?', $setId ) )->setKeyField( 'sc_tab_key' )->setValueField('sc_tab_key') );
		
		/* Build form */
		$form = new \IPS\Helpers\Form();
		$form->hiddenValues['set_id']   = $setId;
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_custom_setting_title', $id ? $current['sc_title'] : null, TRUE ) );
		
		$form->add( new \IPS\Helpers\Form\Select( 'theme_custom_setting_app', $id ? $current['sc_app'] : null, TRUE, array( 'options' => $apps ) ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_custom_setting_key'  , ( $id ? $current['sc_key'] : null ), TRUE, array(
				'placeholder' => \IPS\Member::loggedIn()->language()->addToStack('theme_custom_setting_key_placeholder')
		),
		function( $val )
		{
			/* Check key format */
			if( !preg_match( '/^[a-zA-Z0-9_]+$/', $val ) )
			{
				throw new \InvalidArgumentException('core_theme_settings_error_key_disallowed_characters');
			}

			/* Make sure key is unique */
			try
			{
				$row = \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( "sc_set_id=? AND sc_key=?", \IPS\Request::i()->set_id, $val ) )->first();
					
				if ( isset( \IPS\Request::i()->id ) )
				{
					if ( $row['sc_id'] != \IPS\Request::i()->id )
					{
						throw new \InvalidArgumentException('core_theme_settings_error_key_not_unique');
					}
				}
				else
				{
					throw new \InvalidArgumentException('core_theme_settings_error_key_not_unique');
				}
			}
			catch( \UnderflowException $e )
			{
				/* Key is OK as select failed */
			}
			
			return true;
		} ) );
		
		if ( \count( $tabs ) )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'theme_custom_setting_tab_key_type', 'existing', FALSE, array(
					'options' => array( 'existing' => 'theme_custom_setting_tab_key_o_existing',
										'new'	   => 'theme_custom_setting_tab_key_o_new' ),
					'toggles' => array( 'existing' => array( 'theme_template_tab_key_existing' ),
										'new'      => array( 'theme_template_tab_key_new' ) )
			) ) );
	
			$form->add( new \IPS\Helpers\Form\Select( 'theme_template_tab_key_existing', $id ? $current['sc_tab_key'] : null, TRUE, array( 'options' => array_map( function( $item )
			{
				return ( \IPS\Member::loggedIn()->language()->addToStack('theme_custom_tab_' . $item) ) ?: $item;
			}, $tabs ) ), NULL, NULL, NULL, 'theme_template_tab_key_existing' ) );
		}
		else
		{
			$form->hiddenValues['theme_custom_setting_tab_key_type'] = 'new';
		}
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_template_tab_key_new', NULL, FALSE, array( 'regex' => '#[0-9a-z_]+#' ),
		function( $val )
		{
			$val = preg_replace( '#[^0-9a-z_]#', '', mb_strtolower( $val ) );
			
			if ( \IPS\Request::i()->theme_custom_setting_tab_key_type === 'new' )
			{
				/* Make sure its unique */
				try
				{
					$row = \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( "sc_set_id=? AND sc_tab_key=?", \IPS\Request::i()->set_id, $val ) )->first();
					
					if ( isset( \IPS\Request::i()->id ) )
					{
						if ( $row['sc_id'] != \IPS\Request::i()->id )
						{
							throw new \InvalidArgumentException('core_theme_settings_error_tab_key_not_unique');
						}
					}
				}
				catch( \UnderflowException $e )
				{
					/* Key is OK as select failed */
				}
			}
			
			return true;
		}, 'theme_custom_tab_', NULL, 'theme_template_tab_key_new' ) );
		
		$form->add( new \IPS\Helpers\Form\Select( 'theme_custom_setting_type', $id ? $current['sc_type'] : 'Text', TRUE, array(
			'options'	=> array(
				'Editor'	=> 'pf_type_Editor',
				'Number'	=> 'pf_type_Number',
				'Radio'		=> 'pf_type_Radio',
				'Select'	=> 'pf_type_Select',
				'Text'		=> 'pf_type_Text',
				'TextArea'	=> 'pf_type_TextArea',
				'Upload'	=> 'pf_type_Upload',
				'Url'		=> 'pf_type_Url',
				'Color'		=> 'pf_type_Color',
				'YesNo'	    => 'pf_type_YesNo',
				'other'		=> 'theme_custom_setting_type_other',
			),
			'toggles'	=> array(
				'Radio'		=> array( 'sc_content' ),
				'Select'	=> array( 'sc_content', 'sc_multiple' ),
				'other'		=> array( 'sc_manual_code' )
			)
		) ) );
				
		$form->add( new \IPS\Helpers\Form\Stack( 'theme_custom_setting_type_content', ( $id and $current['sc_type'] != 'other' ) ? json_decode( $current['sc_content'], TRUE ) : array(), FALSE, array( 'stackFieldType' => 'KeyValue' ), NULL, NULL, NULL, 'sc_content' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'theme_custom_setting_type_multiple', $id ? $current['sc_multiple'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'sc_multiple' ) );
		
		$form->add( new \IPS\Helpers\Form\Codemirror( 'theme_custom_setting_manual_code', '<?php' . "\n\n" . ( ( $id and $current['sc_type'] == 'other' ) ? $current['sc_content'] : 'return new \IPS\Helpers\Form\Text( "core_theme_setting_title_{$row[\'sc_id\']}", $value, FALSE, array(), NULL, NULL, NULL, \'theme_setting_\' . $row[\'sc_key\'] );' ), NULL, array( 'mode' => 'php' ), function( $val ) use ( $set )
		{
			$val = trim( preg_replace( '/^<\?php(.*)$/is', '$1', trim( $val ) ) );
			
			if ( $val )
			{
				$row = array( 'sc_id' => 0, 'sc_key' => \IPS\Request::i()->theme_custom_setting_key );
				$value = NULL;
				$theme = $set;
				if ( !( eval( $val ) instanceof \IPS\Helpers\Form\FormAbstract ) )
				{
					throw new \DomainException('theme_custom_setting_manual_code_bad');
				}
			}
			elseif ( \IPS\Request::i()->theme_custom_setting_type == 'other' )
			{
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'sc_manual_code' ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'theme_custom_setting_type_default', $id ? $current['sc_default'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'sc_default' ) );
				
		if ( \IPS\IN_DEV )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'theme_custom_setting_show_in_vse', $id ? $current['sc_show_in_vse'] : FALSE, FALSE, array(), NULL, NULL, NULL, 'sc_show_in_vse' ) );
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'theme_custom_setting_conditional', $id ? $current['sc_condition'] : FALSE, FALSE, array( 'togglesOn' => array( 'theme_custom_setting_condition' ) ), NULL, NULL, NULL, 'theme_custom_setting_conditional' ) );
		$form->add( new \IPS\Helpers\Form\Codemirror( 'theme_custom_setting_condition', '<?php' . "\n\n" . ( ( $id and $current['sc_condition'] ) ? $current['sc_condition'] : 'return TRUE;' ), FALSE, array( 'mode' => 'php' ), NULL, NULL, NULL, 'theme_custom_setting_condition' ) );
		
		if ( $values = $form->values() )
		{
			$save = array(
				'sc_set_id'      => $setId,
				'sc_key'         => $values['theme_custom_setting_key'],
				'sc_type'        => $values['theme_custom_setting_type'],
				'sc_default'     => $values['theme_custom_setting_type_default'],
				'sc_multiple'    => $values['theme_custom_setting_type_multiple'],
				'sc_updated'     => time(),
				'sc_app'	     => $values['theme_custom_setting_app'],
				'sc_show_in_vse' => ( isset( $values['theme_custom_setting_show_in_vse'] ) ) ? $values['theme_custom_setting_show_in_vse'] : 0,
				'sc_content'     => ( $values['theme_custom_setting_type'] === 'other' ) ? trim( preg_replace( '/^<\?php(.*)$/is', '$1', trim( $values['theme_custom_setting_manual_code'] ) ) ) : json_encode( $values['theme_custom_setting_type_content'] ),
				'sc_title'		 => $values['theme_custom_setting_title'],
				'sc_condition'	 => $values['theme_custom_setting_conditional'] ? trim( preg_replace( '/^<\?php(.*)$/is', '$1', trim( $values['theme_custom_setting_condition'] ) ) ) : NULL
			);
			
			$type = ( isset( \IPS\Request::i()->theme_custom_setting_tab_key_type ) ) ? \IPS\Request::i()->theme_custom_setting_tab_key_type : $values['theme_custom_setting_tab_key_type'];
			
			if ( $type === 'existing' )
			{
				$save['sc_tab_key'] = $values['theme_template_tab_key_existing'];
			}
			else
			{
				$save['sc_tab_key'] = mb_strtolower( $values['theme_template_tab_key_new'] );
			}
			
			if ( $current )
			{
				\IPS\Db::i()->update( 'core_theme_settings_fields', $save, array( 'sc_id=?', $current['sc_id'] ) );
				$id = $current['sc_id'];
				\IPS\Session::i()->log( 'acplogs__theme_setting_edited', array( $save['sc_key'] => FALSE ) );
			}
			else
			{
				$save['sc_order'] = \IPS\Db::i()->select( 'MAX(sc_order)', 'core_theme_settings_fields', array( 'sc_set_id=? AND sc_tab_key=?', $setId, $save['sc_tab_key'] ) )->first() + 1;
				$id = \IPS\Db::i()->insert( 'core_theme_settings_fields', $save );
				\IPS\Session::i()->log( 'acplogs__theme_setting_created', array( $save['sc_key'] => FALSE ) );
			}
			
			/* Default is changed, change value? */
			if ( $current and ( $save['sc_default'] !== $current['sc_default'] ) )
			{
				try
				{
					$value = \IPS\Db::i()->select( '*', 'core_theme_settings_values', array( "sv_id=?", $id ) )->first();
				
					if ( $value['sv_value'] == $current['sc_default'] )
					{
						\IPS\Db::i()->update( 'core_theme_settings_values', array( 'sv_value' => $save['sc_default'] ), array( "sv_id=?", $id ) );
					}
				}
				catch( \UnderFlowException $e ) {}
			}
			
			if ( $type === 'new' )
			{
				\IPS\Lang::saveCustom( 'core', "theme_custom_tab_" . $save['sc_tab_key'], $values['theme_template_tab_key_new'] );
			}
			
			if ( \IPS\IN_DEV AND $setId === 1 )
			{ 
				\IPS\Theme\Dev\Theme::writeThemeSettingsToDisk();
			}

			if ( ! $current )
			{
				$field = $set->getCustomSettingField( array_merge( $save, array( 'sc_id' => $id ) ) );
				$themeSettings = json_decode( $set->template_settings, TRUE );
				$themeSettings[ $values['theme_custom_setting_key'] ] = $field::stringValue( $values['theme_custom_setting_type_default'] );
				$set->template_settings =  json_encode( $themeSettings );
				$set->save();
			}

			/* Update children */
			$set->updateChildrenThemeSettings();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themesettings&set_id=' . $setId ), 'saved' );
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'theme_set_custom_setting', $form, FALSE );
		\IPS\Output::i()->title  = \IPS\Member::loggedIn()->language()->addToStack( 'core_theme_set_title_' . $setId );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		$setId   = \intval( \IPS\Request::i()->set_id );
		
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_settings_remove' );

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_theme_settings_fields', array( 'sc_id=?', \IPS\Request::i()->id ) )->first();

			\IPS\Session::i()->log( 'acplogs__theme_setting_deleted', array( $current['sc_key'] => FALSE ) );
			\IPS\Db::i()->delete( 'core_theme_settings_fields', array( 'sc_id=?', \IPS\Request::i()->id ) );
		}
		catch ( \Exception $e ) { } 
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=customization&controller=themesettings&set_id=' . $setId ) );
	}
}
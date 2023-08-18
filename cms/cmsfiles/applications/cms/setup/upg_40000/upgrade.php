<?php
/**
 * @brief		4.0.0 Alpha 1 Upgrade Code
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 Jan 2014
 */

namespace IPS\cms\setup\upg_40000;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * 4.0.0 Alpha 1 Upgrade Code
 *
 *
 */
class _Upgrade
{
	/**
	 * Step 1
	 * Convert pages and folders
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step1()
	{
		/* Permission */
		\IPS\Db::i()->update( 'core_permission_index', array('app' => 'cms'), array( 'app=?', 'ccs' ) );

		/* Folder stuff: Basic infos */
		$id         = 1;
		$folders    = array();
		$hasDefault = false;

		/* We need to add an auto-increment column, so we create a new table and copy over the data */
		if ( \IPS\Db::i()->checkForTable( 'cms_folders' ) )
		{
			\IPS\Db::i()->dropTable( 'cms_folders' );
		}

		\IPS\Db::i()->createTable( $this->get_cms_folders() );

		foreach( \IPS\Db::i()->select( '*', 'ccs_folders' ) as $folder )
		{
			$save = array(
				'folder_last_modified' => time(),
				'folder_parent_id'     => 0,
			    'folder_path'          => trim( $folder['folder_path'], '/' )
			);

			$bits = explode( '/', $save['folder_path'] );
			$save['folder_name'] = array_pop( $bits );

			$folders[ $save['folder_name'] ] = array_merge( $folder, $save );

			$folders[ $save['folder_name'] ]['folder_id'] = \IPS\Db::i()->insert( 'cms_folders', $save );
		}

		/* More folder stuff: Parents */
		foreach( $folders as $name => $data )
		{
			if ( mb_stristr( $data['folder_path'], '/' ) )
			{
				/* Need to get its parent */
				$bits = explode( '/', $save['folder_path'] );

				end( $bits );
				$parentName = prev( $bits );

				if ( isset( $folders[ $parentName ] ) )
				{
					\IPS\Db::i()->update( 'cms_folders', array( 'folder_parent_id' => $folders[ $parentName ]['folder_id'] ), array( 'folder_id=?', $data['folder_id'] ) );
				}
			}
		}

		//\IPS\Db::i()->dropTable( 'ccs_folders' );

		/* We get database info for mapping purposes when looping over pages */
		$databases = array();
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $database )
		{
			$databases[]	= $database;
		}

		/* Pages stuff: Correct folder name and do path stuffs */
		foreach( \IPS\Db::i()->select( '*', 'cms_pages' ) as $page )
		{
			$save = array( 'page_full_path' => $page['page_seo_name'] );

			if ( $page['page_folder'] )
			{
				$save['page_folder'] = trim( $page['page_folder'], '/' );

				/* Now get the folder ID */
				foreach( $folders as $name => $folder )
				{
					if ( $folder['folder_path'] == $save['page_folder'] )
					{
						$save['page_full_path'] = $folder['folder_path'] . '/' . $page['page_seo_name'];
						$save['page_folder_id'] = $folder['folder_id'];
					}
				}
			}

			if ( ! $hasDefault and ! $page['page_folder'] and mb_stristr( $page['page_seo_name'], 'index' ) )
			{
				$hasDefault = true;
				$save['page_default'] = 1;
			}

			/* Is there a database embedded in the page? Check our options, we may need to strip. */
			foreach( $databases as $database )
			{
				if( $database['database_is_articles'] AND mb_strpos( $page['page_content'], "{parse articles" ) !== FALSE )
				{
					if( isset( $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] ) AND $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] != $page['page_id'] )
					{
						$page['page_content']	= preg_replace( "/\{parse articles.*?\}/i", "", $page['page_content'] );
					}
				}

				if( mb_strpos( $page['page_content'], "{parse database=\"{$database['database_id']}\"" ) !== FALSE )
				{
					if( isset( $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] ) AND $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] != $page['page_id'] )
					{
						$page['page_content']	= preg_replace( "/\{parse database=\"{$database['database_id']}\".*?\}/i", "", $page['page_content'] );
					}
				}

				if( mb_strpos( $page['page_content'], "{parse database=\"{$database['database_key']}\"" ) !== FALSE )
				{
					if( isset( $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] ) AND $_SESSION['upgrade_options']['cms']['40000']['database_page_' . $database['database_id'] ] != $page['page_id'] )
					{
						$page['page_content']	= preg_replace( "/\{parse database=\"{$database['database_key']}\".*?\}/i", "", $page['page_content'] );
					}
				}
			}

			/* This will need manual work to correct, so lets flag it */
			if ( $page['page_type'] == 'php' )
			{
				$save['page_has_error'] = 1;
				$save['page_type'] = 'html';
			}
			else if ( $page['page_type'] == 'html' )
			{
				$save['page_content'] = str_replace( '{parse articles', '{database="1"', $page['page_content'] );
				$save['page_content'] = str_replace( '{ccs special_tag="navigation"}', '', $save['page_content'] );
				$save['page_content'] = preg_replace( '#\{parse database="([^"]+?)"\}#', '{database="\1"}', $save['page_content'] );
				$save['page_content'] = preg_replace( '#\{parse block="([^"]+?)"\}#', '{block="\1"}', $save['page_content'] );

				/* Can't run the page through the parser because this strips out custom HTML like script tags and so on */
				#$save['page_content'] = \IPS\Text\Parser::parseStatic( $save['page_content'], TRUE, NULL, NULL, TRUE, TRUE, TRUE );
			}

			if ( isset( $save['page_content'] ) and preg_match( '#\{database="([^\"]+?)"#', $save['page_content'], $match ) )
			{
				if ( $match[1] )
				{
					$where = array( 'database_id=?', $match[1] );

					if ( !\is_numeric( $match[1] ) )
					{
						$where = array( 'database_key=?', $match[1] );
					}

					try
					{
						$db = \IPS\Db::i()->select( '*', 'cms_databases', $where )->first();

						if ( isset( $db['database_id'] ) )
						{
							\IPS\Db::i()->update( 'cms_databases', array( 'database_page_id' => $page['page_id'] ), array( 'database_id=?', $db['database_id'] ) );
						}
					}
					catch( \Exception $e ) { }
				}
			}

			/* Update perms */
			\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? and perm_type=? and perm_type_id=?', 'cms', 'pages', $page['page_id'] ) );
			\IPS\Db::i()->insert( 'core_permission_index', array(
	             'app'			=> 'cms',
	             'perm_type'	=> 'pages',
	             'perm_type_id'	=> $page['page_id'],
	             'perm_view'	=> ( $page['page_view_perms'] ? $page['page_view_perms'] : '' ), # view
	         ) );

			\IPS\Db::i()->update( 'cms_pages', $save, array( 'page_id=?', $page['page_id'] ) );
		}

		if ( ! $hasDefault )
		{
			try
			{
				$page = \IPS\Db::i()->select( '*', 'cms_pages', array( 'page_folder_id=0' ) )->first();

				if ( $page )
				{
					\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 1 ), array( 'page_id=?', $page['page_id'] ) );
				}
				else
				{
					\IPS\Db::i()->update( 'cms_pages', array( 'page_default' => 1 ), NULL, array(), array( 0, 1 ) );
				}
			}
			catch( \UnderflowException $e ){}
		}

		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_folder' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_folder' );
		}

		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_view_perms' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_view_perms' );
		}

		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_cache' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_cache' );
		}
		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_cache_last' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_cache_last' );
		}

		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_cache_ttl' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_cache_ttl' );
		}

		if ( \IPS\Db::i()->checkForColumn( 'cms_pages', 'page_quicknav' ) )
		{
			\IPS\Db::i()->dropColumn( 'cms_pages', 'page_quicknav' );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step1CustomTitle()
	{
		return "Upgrading folders and pages";
	}

	/**
	 * Step 2
	 * Convert menu items
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step2()
	{
		$menuItems      = array();
		$menuMap        = array();
		$createdParents = array();

		\IPS\Db::i()->delete( 'cms_page_menu' );

		foreach ( \IPS\Db::i()->select( '*', 'ccs_menus' ) as $menu )
		{
			$menuItems[ $menu['menu_id'] ] = $menu;
			$menuMap[ $menu['menu_parent_id'] ][] = $menu['menu_id'];
			$type = 'url';
			
			if ( ! $menu['menu_url'] )
			{
				$createdParents[ $menu['menu_id'] ] = TRUE;
				$type = 'folder';
			}
			/* Otherwise, we need to make sure the URL is a fully qualified URL, and not a relative one. */
			else if ( mb_substr( $menu['menu_url'], 0, 4 ) != 'http' )
			{
				$menu['menu_url'] = \IPS\Settings::i()->base_url . ltrim( $menu['menu_url'], '/' );
			}
			
			\IPS\Db::i()->insert( 'cms_page_menu', array(
				'menu_id'         => $menu['menu_id'],
				'menu_title'      => $menu['menu_title'],
				'menu_parent_id'  => \intval( $menu['menu_parent_id'] ),
				'menu_content'    => $menu['menu_url'],
				'menu_position'   => \intval( $menu['menu_position'] ),
				'menu_type'       => $type,
				'menu_permission' => ( empty( $menu['menu_permissions'] ) ? '*' : $menu['menu_permissions'] )
			) );
		}

		/* In 4.0 we require a folder to be a parent, not any old item you fancy */
		foreach( $menuMap as $parentId => $ids )
		{
			if ( ! isset( $menuItems[ $parentId ] ) )
			{
				\IPS\Db::i()->update( 'cms_page_menu', array( 'menu_parent_id' => 0 ), array( 'menu_parent_id=?', $parentId ) );
				continue;
			}

			if ( ! isset( $createdParents[ $parentId ] ) )
			{
				$id = \IPS\Db::i()->insert( 'cms_page_menu', array(
					'menu_parent_id'  => 0,
					'menu_type'       => 'folder',
					'menu_title'      => $menuItems[ $parentId ]['menu_title'],
					'menu_position'   => 0,
					'menu_permission' => ( empty( $menuItems[ $parentId ]['menu_permissions'] ) ? '*' : $menuItems[ $parentId ]['menu_permissions'] )
				) );

				$createdParents[ $parentId ] = TRUE;

				\IPS\Db::i()->update( 'cms_page_menu', array( 'menu_parent_id' => $id ), array( 'menu_id=?', $menuItems[ $parentId ]['menu_id'] ) );
				\IPS\Db::i()->update( 'cms_page_menu', array( 'menu_parent_id' => $id ), array( 'menu_parent_id=?', $parentId ) );
			}
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step2CustomTitle()
	{
		return "Upgrading menu items";
	}

	/**
	 * Step 3
	 * Convert databases (fields, categories, databases)
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step3()
	{
		$admins = array_keys( \IPS\Member::administrators()['g'] );

		/* Fields */
		foreach( \IPS\Db::i()->select( '*', 'cms_database_fields' ) as $field )
		{
			\IPS\Lang::saveCustom( 'cms', 'content_field_' . $field['field_id'], $field['field_name'] );
			\IPS\Lang::saveCustom( 'cms', 'content_field_' . $field['field_id'] . '_desc', ( isset( $field['field_description'] ) ) ? $field['field_description'] : '' );

			$save = array( 'field_type' => mb_ucfirst( $field['field_type'] ) );

			if ( $field['field_type'] == 'input' )
			{
				$save['field_type'] = 'Text';
			}
			else if ( $field['field_type'] == 'multiselect' )
			{
				$save['field_type']        = 'Select';
				$save['field_is_multiple'] = 1;
			}
			else if ( $field['field_type'] == 'attachments' )
			{
				$save['field_type'] = 'Upload';
				$save['field_is_multiple'] = 1;
			}
			
			if ( $save['field_type'] == 'Textarea' )
			{
				$save['field_type'] = 'TextArea';
			}
			
			if ( $save['field_type'] == 'Relational' )
			{
				$save['field_type'] = 'Item';
			}
			
			if ( $field['field_extra'] )
			{
				$json = array();
				if ( \in_array( $field['field_type'], array( 'checkbox', 'radio', 'select', 'multiselect' ) ) )
				{
					foreach( explode( "\n", $field['field_extra'] ) as $line )
					{
						list( $key, $value ) = explode( '=', $line );

						$json[ $key ] = $value;
					}
					
					if ( $field['field_type'] == 'checkbox' and \count( $json ) )
					{
						$save['field_type'] = 'CheckboxSet';
					}
				}
				else if ( $field['field_type'] == 'upload' )
				{
					$save['field_extra'] = '';
					$save['field_allowed_extensions'] = json_encode( explode( ',', $field['field_extra' ] ) );
				}
				else if ( $field['field_type'] == 'relational' )
				{
					$options = explode( ',', $field['field_extra'] );

					$json	= array( 'database' => $options[0] );

					if( $options[2] == 'multiselect' )
					{
						$save['field_is_multiple']	= 1;
					}
				}

				if ( \count( $json ) )
				{
					$save['field_extra'] = json_encode( $json );
				}
			}
			
			if ( $field['field_key'] === 'article_homepage' )
			{
				/* Convert this to featured */
				if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $field['field_database_id'] ) )
				{
					try
					{
						\IPS\Db::i()->update( 'cms_custom_database_' . $field['field_database_id'], array( 'record_featured' => 1 ), array( 'field_' . $field['field_id'] . ' =1' ) );
						\IPS\Db::i()->dropColumn( 'cms_custom_database_' . $field['field_database_id'], 'field_' . $field['field_id'] );
					}
					catch( \Exception $e ) { }
				}
				
				/* Drop the field */
				\IPS\Db::i()->delete( 'cms_database_fields', array( 'field_id=?', $field['field_id'] ) );
				
				/* No need to update anything, just skip to the next field */
				continue;
			}
			
			\IPS\Db::i()->update( 'cms_database_fields', $save, array( 'field_id=?', $field['field_id'] ) );

			/* Update records to use the field default */
			if ( $field['field_default_value'] === 0 or ! empty( $field['field_default_value'] ) )
			{
				if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $field['field_database_id'] ) )
				{
					\IPS\Db::i()->update( 'cms_custom_database_' . $field['field_database_id'], array( 'field_' . $field['field_id'] => $field['field_default_value'] ), array( 'field_' . $field['field_id'] . ' =\'\' OR field_' . $field['field_id'] . ' IS NULL' ) );
				}
			}
			
			/* Insert perm row */
			\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? and perm_type=? and perm_type_id=?', 'cms', 'fields', $field['field_id'] ) );
			\IPS\Db::i()->insert( 'core_permission_index', array(
                 'app'          => 'cms',
                 'perm_type'    => 'fields',
                 'perm_type_id' => $field['field_id'],
                 'perm_view'    => '*',
                 'perm_2'       => ( isset( $field['field_user_editable'] ) and empty( $field['field_user_editable'] ) ) ? implode( ',', $admins ) : '*',
			     'perm_3'       => '*'
             ) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step3CustomTitle()
	{
		return "Upgrading database fields";
	}

	/**
	 * Step 4
	 * Convert databases (fields, categories, databases)
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step4()
	{
		/* Limit - let's do 1 per cycle */
		$perCycle	= 1;
		$limit		= \intval( \IPS\Request::i()->extra );
		$did		= false;

		/* Make sure our Application.php has been loaded, as it handles the special dynamic CMS database classes */
		\IPS\Application::load( 'cms' );

		/* Databases */
		foreach( \IPS\Db::i()->select( '*', 'cms_databases', null, 'database_id ASC', array( $limit, $perCycle ) ) as $database )
		{
			$did = true;
			
			if ( ! \IPS\Db::i()->checkForTable( 'ccs_custom_database_' . $database['database_id'] ) )
			{
				continue;
			}
			
			/* Rename table */
			try
			{
				\IPS\Db::i()->renameTable( 'ccs_custom_database_' . $database['database_id'], 'cms_custom_database_' . $database['database_id'] );
			}
			catch( \IPS\Db\Exception $e )
			{
				if ( $e->getCode() != 1050 )
				{
					throw new \Exception( $e->getMessage() );
				}
			}

			$save = array();

			/* Articles DB */
			if ( $database['database_id'] == 1 )
			{
				$keys   = array(
					'article_date'     => 'record_publish_date INT(10) NOT NULL DEFAULT 0',
					'article_expiry'   => 'record_expiry_date INT(10) NOT NULL DEFAULT 0',
					'article_cutoff'   => 'record_comment_cutoff INT(10) NOT NULL DEFAULT 0',
					'article_image'    => 'record_image TEXT DEFAULT NULL',
					'article_comments' => 'record_allow_comments INT(1) NOT NULL DEFAULT 0'
				);

				$fields = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_fields', array( "field_database_id=? AND field_key IN( '" . implode( "','", array_keys( $keys ) ) . "') ", $database['database_id'] ) )->setKeyField('field_key') );
				$json   = array();
				$drop   = array();
				$remap	= array();

				foreach( $keys as $k => $schema )
				{
					if ( isset( $fields[ $k ] ) )
					{
						/* For an int column, we need to make sure the value is an int before changing the column type or we could get 
							"data truncated" MySQL warnings */
						if( $k != 'article_image' )
						{
							\IPS\Db::i()->update( 'cms_custom_database_' . $database['database_id'], array( 'field_' . $fields[ $k ]['field_id'] => '0' ), array( "field_" . $fields[ $k ]['field_id'] . "='' OR field_" . $fields[ $k ]['field_id'] . " IS NULL" ) );
						}

						$change[] = "CHANGE field_" . $fields[ $k ]['field_id'] . ' ' . $schema;

						list( $field, ) = explode( ' ', $schema );

						$drop[] = $fields[ $k ]['field_id'];
						$remap[ 'field_' . $fields[ $k ]['field_id'] ] = $field;

						if ( $field )
						{
							try
							{
								$perm = \IPS\Db::i()->select( '*', 'core_permission_index', array( 'app=? and perm_type=? and perm_type_id=?', 'cms', 'fields', $fields[ $k ]['field_id'] ) )->first();
								
								$permissions = array( 'perm_view' => $perm['perm_view'], 'perm_2' => $perm['perm_2'], 'perm_3' => $perm['perm_3'], 'visible' => true );
							}
							catch( \UnderflowException $ex )
							{
								$permissions = array( 'perm_view' => '*', 'perm_2' => '*', 'perm_3' => '*', 'visible' => true );
							}
             
							$json[ $field ] = $permissions;
						}
					}
				}

				if( \count( $change ) )
				{
					try
					{
						\IPS\Db::i()->query( 'ALTER TABLE ' . \IPS\Db::i()->prefix . 'cms_custom_database_' . $database['database_id'] . " " . implode( ', ', $change ) );

						\IPS\Log::debug( "Run " . 'ALTER TABLE ' . \IPS\Db::i()->prefix . 'cms_custom_database_' . $database['database_id'] . " " . implode( ', ', $change ), 'upgrade_pages' );
					}
					catch ( \IPS\Db\Exception $e )
					{
						# Don't care, sync below will add columns
						\IPS\Log::log( $e, 'upgrade_pages' );
					}
				}

				if ( \count( $json ) )
				{
					$save['database_fixed_field_perms'] = json_encode( $json );
				}

				if ( \count( $drop ) )
				{
					\IPS\Db::i()->delete( 'cms_database_fields', array( "field_database_id=? AND field_id IN(" . implode( ",", $drop ) . ")", $database['database_id'] ) );
				}

				if( \count( $remap ) )
				{
					if( \in_array( $database['database_field_sort'], array_keys( $remap ) ) )
					{
						$save['database_field_sort'] = $remap[ $database['database_field_sort'] ];
					}
				}
			}

			$save['database_field_title']   = str_replace( 'field_', '', $database['database_field_title'] );
			$save['database_field_content'] = str_replace( 'field_', '', $database['database_field_content'] );

			/* Make sure we have comments enabled */
			$save['database_options'] = 1;

			/* Reset templates */
			$save['database_template_form']       = 'form';
			$save['database_template_featured']   = 'category_articles';
			$save['database_template_listing']    = 'listing';
			$save['database_template_display']    = 'display';
			$save['database_template_categories'] = 'category_index';
			$save['database_use_categories']      = 1;
			
			$catCount = \IPS\Db::i()->select( 'COUNT(*) as count', 'cms_database_categories', array( 'category_database_id=?', $database['database_id'] ) )->first();
			
			if ( ! $catCount )
			{
				$save['database_use_categories'] = 0;
			}
			
			\IPS\Lang::saveCustom( 'cms', "content_db_" . $database['database_id'], $database['database_name'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_" . $database['database_id'] . '_desc', $database['database_description'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_sl_" . $database['database_id'], $database['database_lang_sl'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_pl_" . $database['database_id'], $database['database_lang_pl'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_su_" . $database['database_id'], $database['database_lang_su'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_ia_" . $database['database_id'], "a " . $database['database_lang_sl'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_pu_" . $database['database_id'], $database['database_lang_pu'] );
			\IPS\Lang::saveCustom( 'cms', "content_db_lang_sl_" . $database['database_id'] . '_pl', $database['database_lang_pu'] );

			/* Notification, search/followed/new content langs */
			\IPS\Lang::saveCustom( 'cms', "cms_records" . $database['database_id'] . '_pl', $database['database_lang_pu'] );
			\IPS\Lang::saveCustom( 'cms', "module__cms_records" . $database['database_id'], $database['database_name'] );

			/* Moderator tools */
			\IPS\Lang::saveCustom( 'cms', "modperms__core_Content_cms_Records" . $database['database_id'], $database['database_name'] );

			/* Drop columns */
			try
			{
				\IPS\Db::i()->dropColumn( 'cms_custom_database_' . $database['database_id'], array( 'database_lang_sl', 'database_lang_pl', 'database_lang_su', 'database_lang_pu' ) );
			}
			catch( \IPS\Db\Exception $e ) { } #meh
			
			/* Wipe FURL titles so they get rebuilt because certain characters are handled differently now */
			\IPS\Db::i()->update( 'cms_custom_database_' . $database['database_id'], array( 'record_dynamic_furl' => '' ) );

			/* Update */
			\IPS\Db::i()->update( 'cms_databases', $save, array( 'database_id=?', $database['database_id'] ) );

			/* Sync */
			\IPS\cms\Databases::checkandFixDatabaseSchema( $database['database_id'] );

			/* Categories */
			try
			{
				$databasePerms = \IPS\Db::i()->select( '*', 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', 'cms', 'databases', $database['database_id'] ) )->first();
			}
			catch( \Exception $e )
			{
				$databasePerms = array( 'perm_id' => 0 );
			}

			if ( \count( $databasePerms ) )
			{
				unset( $databasePerms['perm_id'] );

				foreach( iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_database_id=? AND category_has_perms=0', $database['database_id'] ) )->setKeyField('category_id') ) as $id => $data )
				{
					\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? AND perm_type=? AND perm_type_id=?', 'cms', 'categories', $id ) );
					\IPS\Db::i()->insert( 'core_permission_index', array_merge( $databasePerms, array( 'perm_type_id' => $id, 'perm_type' => 'categories' ) ) );
				}
			}
			
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $database['database_id'], array( 'category_id=0' ) )->first();
			
			if ( $count )
			{
				/* Add new default category */
				$className = "\\IPS\\cms\\Categories{$database['database_id']}";
				$category = new $className;
				$category->database_id = $database['database_id'];

				$catTitle = array();
				$catDesc  = array();

				foreach( \IPS\Lang::languages() as $id => $lang )
				{
					$catTitle[ $id ] = $database['database_lang_pu'];
					$catDesc[ $id ]  = '';
				}

				$category->saveForm( $category->formatFormValues( array(
                  'category_name'		  => $catTitle,
                  'category_description'  => $catDesc,
                  'category_parent_id'    => 0,
                  'category_has_perms'    => 0,
                  'category_show_records' => 1
                ) ) );

				$perms = $category->permissions();

				\IPS\Db::i()->update( 'core_permission_index', array(
					'perm_view'	 => '*',
					'perm_2'	 => '*',
					'perm_3'     => '*'
				), array( 'perm_id=?', $perms['perm_id']) );
				
				 \IPS\Db::i()->update( 'cms_custom_database_' . $database['database_id'], array( 'category_id' => $category->id ), array( 'category_id=0' ) );
				 \IPS\Db::i()->update( 'cms_databases', array( 'database_default_category' => $category->id ), array( 'database_id=?', $database['database_id'] ) );
			}
		}

		if( $did )
		{
			return $limit + 1;
		}
		else
		{
			unset( $_SESSION['_step4Count'] );
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step4CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( !isset( $_SESSION['_step4Count'] ) )
		{
			$_SESSION['_step4Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'cms_databases' )->first();
		}

		return "Upgrading databases (Upgraded so far: " . ( ( $limit > $_SESSION['_step4Count'] ) ? $_SESSION['_step4Count'] : $limit ) . ' out of ' . $_SESSION['_step4Count'] . ')';
	}

	/**
	 * Step 5
	 * Convert blocks
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step5()
	{
		foreach( \IPS\Db::i()->select( '*', 'cms_blocks' ) as $block )
		{
			/* Save the name and description */
			\IPS\Lang::saveCustom( 'cms', 'content_block_name_' . $block['block_id'], $block['block_name'] );
			\IPS\Lang::saveCustom( 'cms', 'content_block_name_' . $block['block_id'] . '_desc', $block['block_description'] );

			$data = unserialize( $block['block_config'] );
			$save = array( 'block_active' => 0, 'block_config' => json_encode( $data ), 'block_template' => 0 );

			/* Can we salvage anything here? */
			if ( $block['block_type'] == 'custom' and $data['type'] == 'html' )
			{
				if ( ! preg_match( '#<if|php#', $block['block_content'] ) )
				{
					$save['block_active']  = 1;
					$save['block_content'] = \IPS\Text\Parser::parseStatic( $block['block_content'], TRUE, NULL, NULL, TRUE, TRUE, TRUE );
				}
			}

			if ( $block['block_type'] == 'feed' )
			{
				$save['block_type'] = 'plugin';

				if ( \is_array( $data ) and isset( $data['feed'] ) )
				{
					if ( $data['feed'] == 'articles' )
					{
						$data['feed'] = 'databases';
					}

					if ( $data['feed'] == 'databases' )
					{

						$config = array(
							'cms_rf_category'      => 0,
							'cms_rf_author'        => NULL,
							'cms_rf_sort_on'       => 'record_last_comment',
							'cms_rf_sort_dir'      => 'desc',
							'cms_rf_record_status' => array(),
							'cms_rf_show'          => 5,
							'cms_rf_min_posts'     => 0
						);

						if ( mb_stristr( $data['content'], ';' ) )
						{
							$bits = explode( ';', $data['content'] );

							$data['content'] = $bits[0];
						}

						$config['cms_rf_database'] = $data['content'];

						if ( ! empty( $data['filters']['category'] ) )
						{
							$config['cms_rf_category'] = implode( ',', $data['filters']['category'] );
						}

						if ( ! empty( $data['filters']['filter_starter'] ) )
						{
							$config['cms_rf_author'] = $data['filters']['filter_starter'];
						}

						if ( ! empty( $data['filters']['sortby'] ) )
						{
							$config['cms_rf_sort_on'] = $data['filters']['sortby'];
						}

						if ( ! empty( $data['filters']['sortorder'] ) )
						{
							$config['cms_rf_sort_dir'] = $data['filters']['sortorder'];
						}

						if ( ! empty( $data['filters']['offset_b'] ) )
						{
							$config['cms_rf_show'] = $data['filters']['offset_b'];
						}

						$status = array();

						if ( $data['filters']['filter_status'] == 'either' )
						{
							$status[] = 'open';
							$status[] = 'closed';
						}
						else if ( $data['filters']['filter_status'] )
						{
							$status[] = $data['filters']['filter_status'];
						}

						if ( $data['filters']['filter_pinned'] == 'either' )
						{
							$status[] = 'pinned';
							$status[] = 'notpinned';
						}
						else if ( $data['filters']['filter_pinned'] == 'pinned' )
						{
							$status[] = 'pinned';
						}
						else if ( $data['filters']['filter_pinned'] == 'unpinned' )
						{
							$status[] = 'notpinned';
						}

						if ( $data['filters']['filter_visibility'] == 'either' )
						{
							$status[] = 'visible';
							$status[] = 'hidden';
						}
						else if ( $data['filters']['filter_visibility'] == 'approved' )
						{
							$status[] = 'visible';
						}

						if ( \count( $status ) )
						{
							$config['cms_rf_record_status'] = $status;
						}

						$save['block_active']        = 1;
						$save['block_plugin_config'] = json_encode( $config );
						$save['block_plugin']        = 'RecordFeed';
						$save['block_plugin_app']    = 'cms';
					}
				}
			}

			\IPS\Db::i()->update( 'cms_blocks', $save, array( 'block_id=?', $block['block_id'] ) );

			/* Insert perm row */
			\IPS\Db::i()->delete( 'core_permission_index', array( 'app=? and perm_type=? and perm_type_id=?', 'cms', 'blocks', $block['block_id'] ) );
			\IPS\Db::i()->insert( 'core_permission_index', array(
				'app'          => 'cms',
				'perm_type'    => 'blocks',
			    'perm_type_id' => $block['block_id'],
			    'perm_view'    => '*'
			) );
		}

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step5CustomTitle()
	{
		return "Upgrading blocks";
	}

	/**
	 * Step 6
	 * Convert other
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step6()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Now loop over rows to convert */
		foreach( \IPS\Db::i()->select( '*', 'cms_database_revisions', NULL, 'revision_id asc', array( $limit, $perCycle ) ) as $row )
		{
			$did++;
			$data = unserialize( $row['revision_data'] );

			if ( \is_array( $data ) )
			{
				\IPS\Db::i()->update( 'cms_database_revisions', array( 'revision_data' => json_encode( $data ) ), array( 'revision_id=?', $row['revision_id'] ) );
			}
		}

		/* And then continue */
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step6CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('cms_database_revisions') )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_revisions' )->first();

			return "Upgrading database record revisions (Upgraded so far: " . ( ( $limit > $count ) ? $count : $limit ) . ' out of ' . $count . ')';
		}
		else
		{
			return "Upgraded database record revisions";
		}
	}

	/**
	 * Step 7
	 * Convert categories
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step7()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Now loop over rows to convert */
		foreach( \IPS\Db::i()->select( '*', 'cms_database_categories', NULL, 'category_id asc', array( $limit, $perCycle ) ) as $row )
		{
			/* Save the name and description */
			\IPS\Lang::saveCustom( 'cms', 'content_cat_name_' . $row['category_id'], $row['category_name'] );
			\IPS\Lang::saveCustom( 'cms', 'content_cat_name_' . $row['category_id'] . '_desc', $row['category_description'] );

			$save = array();

			if ( ! $row['category_furl_name'] )
			{
				$save['category_furl_name'] = \IPS\Http\Url\Friendly::seoTitle( $row['category_name'] );
				$row['category_furl_name']  = $save['category_furl_name'];
			}
			else
			{
				$row['category_furl_name']	= urldecode( $row['category_furl_name'] );
			}

			$save['category_full_path'] = $row['category_furl_name'];

			if ( $row['category_parent_id'] )
			{
				$parentId = $row['category_parent_id'];
				$failSafe = 0;
				$path     = array();

				while( $parentId != 0 )
				{
					if ( $failSafe > 50 )
					{
						break;
					}

					try
					{
						$parent = \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_id=?', $parentId ) )->first();

						if ( ! $parent['category_furl_name'] )
						{
							$parent['category_furl_name'] = \IPS\Http\Url\Friendly::seoTitle( $parent['category_name'] );
						}
						else
						{
							$parent['category_furl_name']	= urldecode( $parent['category_furl_name'] );
						}

						$parentId = $parent['category_parent_id'];
						$path[]   = $parent['category_furl_name'];
					}
					catch( \UnderflowException $e )
					{
						break;
					}

					$failSafe++;
				}

				krsort( $path );
				$path[] = $row['category_furl_name'];

				$save['category_full_path'] = trim( implode( '/', $path ), '/' );
			}

			if ( \count( $save ) )
			{
				\IPS\Db::i()->update( 'cms_database_categories', $save, array( 'category_id=?', $row['category_id'] ) );
			}
		}

		/* And then continue */
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step7CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('cms_database_categories') )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_categories' )->first();

			return "Upgrading database categories (Upgraded so far: " . $limit . ' out of ' . $count . ')';
		}
		else
		{
			return "Upgraded database categories";
		}
	}

	/**
	 * Step 8
	 * Move ratings to core_ratings and delete database_ratings table
	 *
	 * @return	array	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step8()
	{
		$perCycle	= 500;
		$did		= 0;
		$limit		= \intval( \IPS\Request::i()->extra );

		/* Try to prevent timeouts to the extent possible */
		$cutOff = \IPS\core\Setup\Upgrade::determineCutoff();

		if ( \IPS\Db::i()->checkForTable( 'ccs_database_ratings' ) )
		{
			foreach( \IPS\Db::i()->select( '*', 'ccs_database_ratings', null, 'rating_id ASC', array( $limit, $perCycle ) ) as $rating )
			{
				if( $cutOff !== null AND time() >= $cutOff )
				{
					return ( $limit + $did );
				}

				$did++;

				\IPS\Db::i()->replace( 'core_ratings', array(
						'class'		=> "IPS\\cms\\Records" . $rating['rating_database_id'],
						'item_id'	=> $rating['rating_record_id'],
						'rating'	=> $rating['rating_rating'],
						'member'	=> $rating['rating_user_id'],
						'ip'		=> $rating['rating_ip_address']
					) );
			}
		}

		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			try
			{
				\IPS\Db::i()->dropTable( 'ccs_database_ratings' );
			}
			catch( \IPS\Db\Exception $e ) { }

			unset( $_SESSION['_step8Count'] );

			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step8CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('ccs_database_ratings') )
		{
			if( !isset( $_SESSION['_step8Count'] ) )
			{
				$_SESSION['_step8Count'] = \IPS\Db::i()->select( 'COUNT(*)', 'ccs_database_ratings' )->first();
			}

			$message = "Upgrading database ratings (Upgraded so far: " . ( ( $limit > $_SESSION['_step1Count'] ) ? $_SESSION['_step1Count'] : $limit ) . ' out of ' . $_SESSION['_step1Count'] . ')';
		}
		else
		{
			$message = "Upgraded all database ratings";
		}

		return $message;
	}

	/**
	 * Step 9
	 * Convert other
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step9()
	{
		/* Add block container (custom)*/
		$container = new \IPS\cms\Blocks\Container;
		$container->parent_id = 0;
		$container->name      = "Custom";
		$container->type      = 'block';
		$container->key       = 'block_custom';
		$container->save();

		/* Add block container (plugins) */
		$container = new \IPS\cms\Blocks\Container;
		$container->parent_id = 0;
		$container->name      = "Plugins";
		$container->type      = 'block';
		$container->key       = 'block_plugins';
		$container->save();

		\IPS\cms\Templates::importXml( \IPS\ROOT_PATH . "/applications/cms/data/cms_theme.xml", NULL, NULL, TRUE );

		return TRUE;
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step9CustomTitle()
	{
		return "Upgrading other Pages items";
	}
	
	/**
	 * Step 6
	 * Attachments into uploads
	 *
	 * @return	array|boolean	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 */
	public function step10()
	{
		/* Some init */
		$did		= 0;
		$perCycle	= 500;
		$limit		= 0;

		if( isset( \IPS\Request::i()->extra ) )
		{
			$limit	= (int) \IPS\Request::i()->extra;
		}

		/* Now loop over rows to convert */
		foreach( \IPS\Db::i()->select( '*', 'ccs_attachments_map', NULL, 'map_id asc', array( 0, $perCycle ) ) as $row )
		{
			$did++;
			
			/* Fetch actual attachment */
			try
			{
				$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', $row['map_attach_id'] ) )->first();
				
			}
			catch( \Exception $ex )
			{
				/* No good will come of this, so remove */
				\IPS\Db::i()->delete( 'ccs_attachments_map', array( 'map_attach_id=?', $row['map_attach_id'] ) );
				continue;
			}
			
			if ( empty( $attachment['attach_location'] ) )
			{
				/* No good will come of this, so remove */
				\IPS\Db::i()->delete( 'ccs_attachments_map', array( 'map_attach_id=?', $row['map_attach_id'] ) );
				continue;
			}
			
			/* And also fetch the record */
			if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $row['map_database_id'] ) )
			{
				try
				{
					$record = \IPS\Db::i()->select( '*', 'cms_custom_database_' . $row['map_database_id'], array( 'primary_id_field=?', $row['map_record_id'] ) )->first();
					
					/* 3.x kept a simple count of attachments in this field */
					if ( \is_numeric( $record[ 'field_' . $row['map_field_id'] ] ) )
					{
						$record[ 'field_' . $row['map_field_id'] ] = '';
					}
					
					if ( ! empty( $record[ 'field_' . $row['map_field_id'] ] ) )
					{
						$record[ 'field_' . $row['map_field_id'] ] .= ',';
					}
					
					$record[ 'field_' . $row['map_field_id'] ] .= $attachment['attach_location'];
					
					\IPS\Db::i()->update( 'cms_custom_database_' . $row['map_database_id'], array( 'field_' . $row['map_field_id'] => $record[ 'field_' . $row['map_field_id'] ] ), array( 'primary_id_field=?', $row['map_record_id'] ) );
				}
				catch( \Exception $ex )
				{
					/* No good will come of this, so remove */
					\IPS\Db::i()->delete( 'ccs_attachments_map', array( 'map_attach_id=?', $row['map_attach_id'] ) );
					continue;
				}
			}
			
			/* Converted, so remove the row */
			\IPS\Db::i()->delete( 'ccs_attachments_map', array( 'map_attach_id=?', $row['map_attach_id'] ) );
		}

		/* And then continue */
		if( $did )
		{
			return ( $limit + $did );
		}
		else
		{
			return TRUE;
		}
	}

	/**
	 * Custom title for this step
	 *
	 * @return string
	 */
	public function step10CustomTitle()
	{
		$limit = isset( \IPS\Request::i()->extra ) ? \IPS\Request::i()->extra : 0;

		if( \IPS\Db::i()->checkForTable('ccs_attachments_map') )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*)', 'ccs_attachments_map' )->first();

			return "Upgrading attachments field (Upgraded so far: " . ( ( $limit > $count ) ? $count : $limit ) . ' out of ' . $count . ')';
		}
		else
		{
			return "Upgraded attachments field";
		}
	}
		
	/**
	 * Finish - This is run after all apps have been upgraded
	 *
	 * @return	mixed	If returns TRUE, upgrader will proceed to next step. If it returns any other value, it will set this as the value of the 'extra' GET parameter and rerun this step (useful for loops)
	 * @note	We opted not to let users run this immediately during the upgrade because of potential issues (it taking a long time and users stopping it or getting frustrated) but we can revisit later
	 */
	public function finish()
	{
		/* Rebuild records post install */
		foreach( \IPS\Db::i()->select( '*', 'cms_databases' ) as $database )
		{
			try
			{
				\IPS\Task::queue( 'cms', 'RebuildRecords'   , array( 'fixFurls' => true, 'fixHtml' => true, 'fixImage' => true, 'class' => 'IPS\cms\Records' . $database['database_id'] ), 3 );
				\IPS\Task::queue( 'cms', 'RebuildCategories', array( 'fixHtml' => true, 'fixImage' => true, 'class' => 'IPS\cms\Categories' . $database['database_id'] ), 3 );
			}
			catch ( \OutOfRangeException $ex )
			{
			}
			
			/* Some 'content' fields can be select boxes, etc and we don't want to run the text parser on those as it wraps with <p> tags */
			$fieldClass = '\IPS\cms\Fields' . $database['database_id'];

			/* We wrap in a try/catch in case upgraded content field is 'primary_id_field' which won't load, but does not need to be rebuilt anyways */
			try
			{
				$field = $fieldClass::load( $database['database_field_content'] );
				
				if ( $field->type === 'Editor' )
				{
					\IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\cms\Records' . $database['database_id'] ), 2 );
				}
			}
			catch( \Exception $e ){}
			
			\IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\cms\Records\Comment' . $database['database_id'] ), 2 );
			\IPS\Task::queue( 'core', 'RebuildPosts', array( 'class' => 'IPS\cms\Records\Review' . $database['database_id'] ), 2 );
		}
	}
	
	/**
	 * Return the cms_folders table
	 *
	 * @return array
	 */
	protected function get_cms_folders()
	{
		$json = <<<EOF
{
        "name": "cms_folders",
        "columns": {
            "folder_path": {
                "allow_null": true,
                "auto_increment": false,
                "binary": false,
                "comment": "",
                "decimals": null,
                "default": null,
                "length": 0,
                "name": "folder_path",
                "type": "TEXT",
                "unsigned": false,
                "values": [

                ],
                "zerofill": false
            },
            "folder_last_modified": {
                "allow_null": false,
                "auto_increment": false,
                "binary": false,
                "comment": "",
                "decimals": null,
                "default": "0",
                "length": 11,
                "name": "folder_last_modified",
                "type": "INT",
                "unsigned": false,
                "values": [

                ],
                "zerofill": false
            },
            "folder_id": {
                "allow_null": false,
                "auto_increment": true,
                "binary": false,
                "comment": "",
                "decimals": null,
                "default": null,
                "length": 10,
                "name": "folder_id",
                "type": "INT",
                "unsigned": true,
                "values": [

                ],
                "zerofill": false
            },
            "folder_parent_id": {
                "allow_null": false,
                "auto_increment": false,
                "binary": false,
                "comment": "",
                "decimals": null,
                "default": "0",
                "length": 10,
                "name": "folder_parent_id",
                "type": "INT",
                "unsigned": true,
                "values": [

                ],
                "zerofill": false
            },
            "folder_name": {
                "allow_null": true,
                "auto_increment": false,
                "binary": false,
                "comment": "",
                "decimals": null,
                "default": null,
                "length": 250,
                "name": "folder_name",
                "type": "VARCHAR",
                "unsigned": false,
                "values": [

                ],
                "zerofill": false
            }
        },
        "indexes": {
            "PRIMARY": {
                "type": "primary",
                "name": "PRIMARY",
                "length": [
                    null
                ],
                "columns": [
                    "folder_id"
                ]
            }
        },
        "comment": ""
}
EOF;

		return json_decode( trim( $json ), TRUE );
	}
}
<?php
/**
 * @brief		File Storage Extension: Theme
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Sep 2013
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Theme
 */
class _Theme
{
	/**
	 * Some file storage engines need to store a gzip version of some files that can be served to a browser gzipped
	 */
	public static $storeGzipExtensions = array( 'css', 'js' );
	
	/**
	 * The configuration settings have been updated
	 *
	 * @return void
	 */
	public static function settingsUpdated()
	{
		/* Clear out CSS as custom URL may have changed */
		\IPS\Theme::deleteCompiledCss();
		
		/* Trash this JS */
		\IPS\Output::clearJsFiles();
	}
	
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return 6; // While this isn't the number of files, it's the number of steps this will take to move them, which is all it's used for
	}	
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\UnderflowException					When file record doesn't exist. Indicating there are no more files to move
	 * @return	void|int							An offset integer to use on the next cycle, or nothing
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		switch ( $offset )
		{
			case 0:
				foreach ( \IPS\Member\Group::groups() as $group )
				{
					if ( $group->g_icon )
					{
						try
						{
							$group->g_icon = (string) \IPS\File::get( $oldConfiguration ?: 'core_Theme', $group->g_icon )->move( $storageConfiguration );
							$group->save();
						}
						catch( \Exception $e )
						{
							/* Any issues are logged */
						}
					}
				}
				return TRUE;

			case 1:
				foreach ( \IPS\Db::i()->select( '*', 'core_member_ranks' ) as $rank )
				{
					if ( $rank['icon'] )
					{
						try
						{
							\IPS\Db::i()->update( 'core_member_ranks', array( 'icon' => (string) \IPS\File::get( $oldConfiguration ?: 'core_Theme', $rank['icon'] )->move( $storageConfiguration ) ), array( 'id=?', $rank['id'] ) );
						}
						catch( \Exception $e )
						{
							/* Any issues are logged */
						}
					}
				}

				unset( \IPS\Data\Store::i()->ranks );
				return TRUE;

			case 2:
				foreach ( \IPS\Db::i()->select( '*', 'core_reputation_levels' ) as $rep )
				{
					try
					{
						if ( $rep['level_image'] )
						{
							\IPS\Db::i()->update( 'core_reputation_levels', array( 'level_image' => (string) \IPS\File::get( $oldConfiguration ?: 'core_Theme', $rep['level_image'] )->move( $storageConfiguration ) ), array( 'level_id=?', $rep['level_id'] ) );
						}
					}
					catch( \Exception $e )
					{
						/* Any issues are logged */
					}
				}
				unset( \IPS\Data\Store::i()->reputationLevels );
				return TRUE;

			case 3:
				/* Move logos */
				foreach( \IPS\Theme::themes() as $id => $set )
				{
					$logos   = $set->logo;
					$changed = false;
					
					foreach( array( 'front', 'sharer', 'favicon' ) as $icon )
					{
						if ( isset( $logos[ $icon ] ) AND \is_array( $logos[ $icon ] ) )
						{
							if ( ! empty( $logos[ $icon ]['url'] ) )
							{
								try
								{
									$logos[ $icon ]['url'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Theme', $logos[ $icon ]['url'] )->move( $storageConfiguration );
									$changed = true;
								}
								catch( \Exception $e )
								{
									/* Any issues are logged */
								}
							}
						}
					}
					
					if ( $changed === true )
					{
						$set->saveSet( array( 'logo' => $logos ) );
					}
				}
				
				/* All done */
				return TRUE;

			case 4:
				/* Move custom theme settings (uploads) */
				$uploads = \IPS\Db::i()->select( 'core_theme_settings_values.sv_value, core_theme_settings_values.sv_id', 'core_theme_settings_fields', array( 'sc_type=?', 'Upload' ) )
							->join( 'core_theme_settings_values', 'core_theme_settings_fields.sc_id=core_theme_settings_values.sv_id' );

				foreach( $uploads as $field )
				{
					try
					{
						\IPS\Db::i()->update( 'core_theme_settings_values', array( 'sv_value' => \IPS\File::get( $oldConfiguration ?: 'core_Theme', $field['sv_value'] )->move( $storageConfiguration ) ), array( 'sv_id=?', $field['sv_id'] ) );
					}
					catch( \Exception $e )
					{
						/* Any issues are logged */
					}
				}

				/* Trash old JS */
				try
				{
					\IPS\File::getClass( $oldConfiguration ?: 'core_Theme' )->deleteContainer( 'javascript_global' );
				} catch( \Exception $e ) { }
					
				foreach( \IPS\Application::applications() as $key => $data )
				{
					try
					{
						\IPS\File::getClass( $oldConfiguration ?: 'core_Theme' )->deleteContainer( 'javascript_' . $key );
					} catch( \Exception $e ) { }
				}
				
				/* Trash this JS */
				\IPS\Output::clearJsFiles();

				/* Trash CSS and images */
				foreach( \IPS\Theme::themes() as $id => $theme )
				{
					/* Remove files, but don't fail if we can't */
					try
					{
						\IPS\File::getClass( $oldConfiguration ?: 'core_Theme' )->deleteContainer( 'set_resources_' . $theme->id );
						\IPS\File::getClass( $oldConfiguration ?: 'core_Theme' )->deleteContainer( 'css_built_' . $theme->id );
					}
					catch( \Exception $e ){}
				}
				
				/* Trash new CSS and images */
				\IPS\Theme::clearFiles( \IPS\Theme::TEMPLATES + \IPS\Theme::CSS + \IPS\Theme::IMAGES );
				
				return TRUE;
			
			case 5:
				$settings = array();
				foreach( \IPS\Application::applications() AS $app )
				{
					$settings = array_merge( $settings, $app->uploadSettings() );
				}
				
				foreach( $settings AS $key )
				{
					if ( \IPS\Settings::i()->$key )
					{
						try
						{
							\IPS\File::get( $oldConfiguration ?: 'core_Theme', \IPS\Settings::i()->$key )->move( $storageConfiguration );
						}
						catch( \Exception $e ) {}
					}
				}
				
				throw new \UnderflowException;

			default:
				/* Go away already */
				throw new \UnderflowException;
		}
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		switch ( $offset )
		{
			case 0:
				foreach ( \IPS\Member\Group::groups() as $group )
				{
					if ( $new = \IPS\File::repairUrl( $group->g_icon ) )
					{
						try
						{
							$group->g_icon = $new;
							$group->save();
						}
						catch( \Exception $e )
						{
							/* Any issues are logged */
						}
					}
				}
				return TRUE;

			case 1:
				foreach ( \IPS\Db::i()->select( '*', 'core_member_ranks' ) as $rank )
				{
					if ( $new = \IPS\File::repairUrl( $rank['icon'] ) )
					{
						try
						{
							\IPS\Db::i()->update( 'core_member_ranks', array( 'icon' => $new ), array( 'id=?', $rank['id'] ) );
						}
						catch( \Exception $e )
						{
							/* Any issues are logged */
						}
					}
				}

				unset( \IPS\Data\Store::i()->ranks );
				return TRUE;

			case 2:
				foreach ( \IPS\Db::i()->select( '*', 'core_reputation_levels' ) as $rep )
				{
					try
					{
						if ( $new = \IPS\File::repairUrl( $rep['level_image'] ) )
						{
							\IPS\Db::i()->update( 'core_reputation_levels', array( 'level_image' => $new ), array( 'level_id=?', $rep['level_id'] ) );
						}
					}
					catch( \Exception $e )
					{
						/* Any issues are logged */
					}
				}
				unset( \IPS\Data\Store::i()->reputationLevels );
				return TRUE;

			case 3:
				/* Trash CSS and images */
				\IPS\Theme::clearFiles( \IPS\Theme::TEMPLATES + \IPS\Theme::CSS + \IPS\Theme::IMAGES );
				
				return TRUE;

			case 4:
				/* Move logos */
				foreach( \IPS\Theme::themes() as $id => $set )
				{
					$logos   = $set->logo;
					$changed = false;
					
					foreach( array( 'front', 'sharer', 'favicon' ) as $icon )
					{
						if ( isset( $logos[ $icon ] ) AND \is_array( $logos[ $icon ] ) )
						{
							if ( ! empty( $logos[ $icon ]['url'] ) and $new = \IPS\File::repairUrl( $logos[ $icon ]['url'] ) )
							{
								try
								{
									$logos[ $icon ]['url'] = $new;
									$changed = true;
								}
								catch( \Exception $e )
								{
									/* Any issues are logged */
								}
							}
						}
					}
					
					if ( $changed === true )
					{
						$set->saveSet( array( 'logo' => $logos ) );
					}
				}
				
				/* Trash JS */
				\IPS\Output::clearJsFiles();
				
				/* All done */
				return TRUE;
			
			case 5:
				$settings = array();
				foreach( \IPS\Application::applications() AS $app )
				{
					$settings = array_merge( $settings, $app->uploadSettings() );
				}
				
				$update = array();
				foreach( $settings AS $key )
				{
					if ( \IPS\Settings::i()->$key AND $new = \IPS\File::repairUrl( \IPS\Settings::i()->$key ) )
					{
						$update[ $key ] = $new;
					}
				}
				if ( \count( $update ) )
				{
					\IPS\Settings::i()->changeValues( $update );
				}
				
				throw new \UnderflowException;
		}
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		/* Is it a group icon? */
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			if ( $group->g_icon == (string) $file )
			{
				return TRUE;
			}
		}

		/* Is it a rank icon? */
		foreach ( \IPS\Db::i()->select( '*', 'core_member_ranks' ) as $rank )
		{
			if ( $rank['icon'] == (string) $file )
			{
				return TRUE;
			}
		}

		/* Is it a reputation level icon? */
		foreach ( \IPS\Db::i()->select( '*', 'core_reputation_levels' ) as $rep )
		{
			if ( $rep['level_image'] == (string) $file )
			{
				return TRUE;
			}
		}

		/* Is it a skin image? */
		foreach ( \IPS\Db::i()->select( '*', 'core_theme_resources' ) as $image )
		{
			if ( $image['resource_filename'] == (string) $file )
			{
				return TRUE;
			}
		}
		
		/* Is it JS? */
		if ( isset( \IPS\Data\Store::i()->javascript_map ) )
		{
			foreach( \IPS\Data\Store::i()->javascript_map as $app => $data )
			{
				foreach( \IPS\Data\Store::i()->javascript_map[ $app ] as $key => $js )
				{
					if ( $js == (string) $file )
					{
						return TRUE;
					}
				}
			}
		}

		/* Is it a skin logo image or CSS? */
		foreach( \IPS\Theme::themes() as $set )
		{
			foreach( array( 'front', 'sharer', 'favicon' ) as $icon )
			{
				if ( isset( $set->logo[ $icon ] ) AND \is_array( $set->logo[ $icon ] ) )
				{
					if ( ! empty( $set->logo[ $icon ]['url'] ) AND $set->logo[ $icon ]['url'] == (string) $file )
					{
						return TRUE;
					}
				}
			}

			foreach( $set->css_map as $key => $css )
			{
				if ( $css == (string) $file )
				{
					return TRUE;
				}
			}
		}
		
		/* Setting? */
		$settings = array();
		foreach( \IPS\Application::applications() AS $app )
		{
			$settings = array_merge( $settings, $app->uploadSettings() );
		}
		
		foreach( $settings AS $key )
		{
			if ( \IPS\Settings::i()->$key AND \IPS\Settings::i()->$key == (string) $file )
			{
				return TRUE;
			}
		}

		/* Not found? Then must not be valid */
		return FALSE;
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		// It's not possible to delete the core application, and this would break the entire site, so let's not bother with this
		return;
	}
}
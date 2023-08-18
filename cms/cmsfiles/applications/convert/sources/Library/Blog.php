<?php

/**
 * @brief		Converter Library Blog Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Library;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Invision Blog Converter
 * @note	We must extend the Core Library here so we can access methods like convertAttachment, convertFollow, etc
 */
class _Blog extends Core
{
	/**
	 * @brief	Application
	 */
	public $app = 'blog';

	/**
	 * Returns an array of items that we can convert, including the amount of rows stored in the Community Suite as well as the recommend value of rows to convert per cycle
	 *
	 * @param	bool	$rowCounts		enable row counts
	 * @return	array
	 */
	public function menuRows( $rowCounts=FALSE )
	{
		$return		= array();
		$extraRows 	= $this->software->extraMenuRows();

		foreach( $this->getConvertableItems() as $k => $v )
		{
			switch( $k )
			{
				case 'convertBlogCategories':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_categories',
						'step_method'		=> 'convertBlogCategories',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_categories' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array(),
						'link_type'			=> 'blog_categories',
					);
					break;

				case 'convertBlogs':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blogs',
						'step_method'		=> 'convertBlogs',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertBlogCategories' ),
						'link_type'			=> 'blog_blogs',
					);
					break;
				
				case 'convertBlogEntries':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_entries',
						'step_method'		=> 'convertBlogEntries',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_entries' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertBlogs', 'convertBlogEntryCategories' ),
						'link_type'			=> 'blog_entries',
					);
					break;

				case 'convertBlogEntryCategories':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_entry_categories',
						'step_method'		=> 'convertBlogEntryCategories',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_entry_categories' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array(),
						'link_type'			=> 'blog_entry_categories',
					);
					break;
				
				case 'convertBlogComments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_comments',
						'step_method'		=> 'convertBlogComments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_comments' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertBlogEntries' ),
						'link_type'			=> 'bog_comments',
					);
					break;
				
				case 'convertBlogRssImport':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_rss_import',
						'step_method'		=> 'convertBlogRssImport',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_rss_import' ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertBlogs' ),
						'link_type'			=> 'blog_rss_import',
					);
					break;

				case 'convertClubBlogs':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_blogs',
						'step_method'		=> 'convertClubBlogs',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'blog_blogs', array( "blog_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertClubs' ),
						'link_type'			=> 'core_club_blogs'
					);
					break;

				case 'convertClubBlogEntries':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_blog_entries',
						'step_method'		=> 'convertClubBlogEntries',
						'ips_rows'			=> \IPS\Db::i()->select( 'SUM(blog_entries)', 'blog_blogs', array( "blog_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertClubBlogs' ),
						'link_type'			=> 'core_club_blogs_entries',
						'requires_rebuild'	=> TRUE
					);
				break;

				case 'convertClubBlogComments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_club_blog_comments',
						'step_method'		=> 'convertClubBlogComments',
						'ips_rows'			=> \IPS\Db::i()->select( 'SUM(blog_comments)', 'blog_blogs', array( "blog_club_id IS NOT NULL" ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 200,
						'dependencies'		=> array( 'convertClubBlogEntries' ),
						'link_type'			=> 'core_club_blogs_comments',
						'requires_rebuild'	=> TRUE
					);
					break;
				
				case 'convertAttachments':
					$return[ $k ] = array(
						'step_title'		=> 'convert_blog_attachments',
						'step_method'		=> 'convertAttachments',
						'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments_map', array( 'location_key=? AND id1<>? AND id2 IS NULL', 'blog_Entries', 0 ) ),
						'source_rows'		=> array( 'table' => $v['table'], 'where' => $v['where'] ),
						'per_cycle'			=> 10,
						'dependencies'		=> array( 'convertBlogEntries', 'convertBlogComments' ),
						'link_type'			=> 'core_attachments',
					);
					break;
			}

			/* Append any extra steps immediately to retain ordering */
			if( isset( $v['extra_steps'] ) )
			{
				foreach( $v['extra_steps'] as $extra )
				{
					$return[ $extra ] = $extraRows[ $extra ];
				}
			}
		}

		/* Run the queries if we want row counts */
		if( $rowCounts )
		{
			$return = $this->getDatabaseRowCounts( $return );
		}

		return $return;
	}
	
	/**
	 * Returns an array of tables that need to be truncated when Empty Local Data is used
	 *
	 * @param	string	$method	Method we are truncating
	 * @return	array
	 */
	protected function truncate( $method )
	{
		$return		= array();
		$classname	= \get_class( $this->software );

		if( $classname::canConvert() === NULL )
		{
			return array();
		}
		
		foreach( $classname::canConvert() as $k => $v )
		{
			switch( $k )
			{
				/* Should return multiple array members for each table that needs to be truncated. The key should be the table, while the value should be a WHERE clause, or NULL to completely empty the table */
				case 'convertBlogs':
					$return['convertBlogs']							= array( 'blog_blogs' => NULL, 'core_clubs_node_map' => array( "node_class=?", "IPS\\blog\\Blog" ) );
					break;

				case 'convertBlogCategories':
					$return['convertBlogCategories']				= array( 'blog_categories' => NULL );
					break;
				
				case 'convertBlogEntries':
					$return['convertBlogEntries']					= array( 'blog_entries' => NULL );
					break;

				case 'convertBlogEntryCategories':
					$return['convertBlogEntryCategories']			= array( 'blog_entry_categories' => NULL );
					break;
				
				case 'convertBlogComments':
					$return['convertBlogComments']					= array( 'blog_comments' => NULL );
					break;
				
				case 'convertBlogRssImport':
					$return['convertBlogRssImport']					= array( 'blog_rss_import' => NULL, 'blog_rss_imported' => NULL );
					break;
				
				case 'convertAttachments':
					$return['convertAttachments']			= array(
						'core_attachments'		=> \IPS\Db::i()->in( 'attach_id', (string) \IPS\Db::i()->select( 'attachment_id', 'core_attachments_map', array( "location_key=?", 'blog_Entries' ) ) ),
						'core_attachments_map'	=> array( "location_key=?", 'blog_Entries' )
					);
					break;

				case 'convertClubBlogs':
					$return['convertClubBlogs'] = array( "blog_blogs" => array( "blog_club_id IS NOT NULL" ) );
					break;

				case 'convertClubBlogEntries':
					$return['convertClubBlogEntries'] = array( 'blog_entries' => array( 'entry_id IN ( ' . (string) \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( "type='core_clubs_blog_entries' AND app={$this->software->app->app_id}" ) ) . ')' ) );
					break;

				case 'convertClubBlogComments':
					$return['convertClubBlogComments'] = array( 'blog_comments' => array( 'comment_id IN ( ' . (string) \IPS\Db::i()->select( 'ipb_id', 'convert_link', array( "type='core_clubs_blog_comments' AND app={$this->software->app->app_id}" ) ) . ')' ) );
					break;
			}
		}

		return $return[ $method ];
	}
	
	/**
	 * This is how the insert methods will work - basically like 3.x, but we should be using the actual classes to insert the data unless there is a real world reason not too.
	 * Using the actual routines to insert data will help to avoid having to resynchronize and rebuild things later on, thus resulting in less conversion time being needed overall.
	 * Anything that parses content, for example, may need to simply insert directly then rebuild via a task over time, as HTML Purifier is slow when mass inserting content.
	 */
	
	/**
	 * A note on logging -
	 * If the data is missing and it is unlikely that any source software would be able to provide this, we do not need to log anything and can use default data (for example, group_layout in convertLeaderGroups).
	 * If the data is missing and it is likely that a majority of the source software can provide this, we should log a NOTICE and use default data (for example, a_casesensitive in convertAcronyms).
	 * If the data is missing and it is required to convert the item, we should log a WARNING and return FALSE.
	 * If the conversion absolutely cannot proceed at all (filestorage locations not writable, for example), then we should log an ERROR and throw an \IPS\convert\Exception to completely halt the process and redirect to an error screen showing the last logged error.
	 */
	
	/**
	 * Convert a Blog
	 *
	 * @param	array				$info			Array of blog information
	 * @param	array|NULL			$socialgroup	Array of data to be stored in core_sys_social_groups and core_sys_social_group_members
	 * @param	string|NULL|boolean	$filepath		URL/Path to cover photo, NULL to use raw file data, or FALSE to not convert
	 * @param	string|NULL			$filedata		If $filepath is NULL, this should contain the raw contents of the cover photo
	 * @return	integer|boolean		The ID of the inserted blog, or FALSE on failure.
	 */
	public function convertBlog( array $info, $socialgroup=NULL, $filepath=FALSE, $filedata=NULL )
	{
		if ( !isset( $info['blog_id'] ) )
		{
			$this->software->app->log( 'blog_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['blog_member_id'] ) AND $info['blog_member_id'] )
		{
			try
			{
				$info['blog_member_id'] = $this->software->app->getLink( $info['blog_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['blog_member_id'] = 0;
			}
		}
		else
		{
			$info['blog_member_id'] = 0;
		}
		
		if ( !isset( $info['blog_num_views'] ) )
		{
			$info['blog_num_views'] = 0;
		}
		
		if ( !isset( $info['blog_pinned'] ) )
		{
			$info['blog_pinned'] = 0;
		}
		
		if ( !isset( $info['blog_disabled'] ) )
		{
			$info['blog_disabled'] = 0;
		}
		
		if ( !isset( $info['blog_allowguests'] ) )
		{
			$info['blog_allowguests'] = 1;
		}
		
		if ( !isset( $info['blog_rating_total'] ) )
		{
			$info['blog_rating_total'] = 0;
		}
		
		if ( !isset( $info['blog_rating_count'] ) )
		{
			$info['blog_rating_count'] = 0;
		}
		
		if ( isset( $info['blog_settings'] ) )
		{
			if ( !\is_array( $info['blog_settings'] ) )
			{
				$info['blog_settings'] = json_decode( $info['blog_settings'], TRUE );
			}
			
			if ( !\is_null( $info['blog_settings'] ) )
			{
				$newSettings = array();
				
				/* This looks funny, but allow for easy further expansion later */
				foreach( array( 'allowrss' ) AS $key )
				{
					switch( $key )
					{
						case 'allowrss':
							if ( !isset( $info['blog_settings'][ $key ] ) )
							{
								$newSettings[ $key ] = true;
							}
							else
							{
								$newSettings[ $key ] = (bool) $info['blog_settings'][ $key ];
							}
						break;
					}
				}
				
				$info['blog_settings'] = json_encode( $newSettings );
			}
			else
			{
				$info['blog_settings'] = json_encode( array( 'allowrss' => TRUE ) );
			}
		}
		else
		{
			$info['blog_settings'] = json_encode( array( 'allowrss' => TRUE ) );
		}
		
		/* No Longer Used */
		$info['blog_last_visitors']		= NULL;
		$info['blog_editors']			= NULL;
		
		/* Name and Description */
		if ( !isset( $info['blog_name'] ) )
		{
			$owner = \IPS\Member::load( $info['blog_member_id'] );
			
			if ( $owner->member_id )
			{
				$name = "{$owner->name}'s Blog";
			}
			else
			{
				$name = "Untitled Blog {$info['blog_id']}";
			}
		}
		else
		{
			$name = $info['blog_name'];
			unset( $info['blog_name'] );
		}
		
		$info['blog_seo_name'] = \IPS\Http\Url::seoTitle( $name );
		
		if ( isset( $info['blog_description'] ) )
		{
			$desc = $info['blog_description'];
			unset( $info['blog_description'] );
		}
		else
		{
			$desc = '';
		}
		
		if ( isset( $info['blog_groupblog_ids'] ) )
		{
			if ( !\is_array( $info['blog_groupblog_ids'] ) )
			{
				$info['blog_groupblog_ids'] = explode( ',', $info['blog_groupblog_ids'] );
			}
			
			$newGroups = array();
			if ( \count( $info['blog_groupblog_ids'] ) )
			{
				foreach( $info['blog_groupblog_ids'] AS $group )
				{
					try
					{
						$newGroups[] = $this->software->app->getLink( $group, 'core_groups', TRUE );
					}
					catch( \OutOfRangeException $e )
					{
						continue;
					}
				}
			}
			
			if ( \count( $newGroups ) )
			{
				$info['blog_groupblog_ids'] = implode( ',', $newGroups );
			}
			else
			{
				$info['blog_groupblog_ids'] = '';
			}
		}
		else
		{
			$info['blog_groupblog_ids'] = '';
		}
		
		if ( isset( $info['blog_last_edate'] ) )
		{
			if ( $info['blog_last_edate'] instanceof \IPS\DateTime )
			{
				$info['blog_last_edate'] = $info['blog_last_edate']->getTimestamp();
			}
		}
		else
		{
			$info['blog_last_edate'] = 0;
		}
		
		/* Counts */
		foreach( array( 'blog_count_entries', 'blog_count_comments', 'blog_count_entries_hidden', 'blog_count_comments_hidden', 'blog_rating_average', 'blog_count_entries_future' ) AS $column )
		{
			if ( !isset( $info[ $column ] ) )
			{
				$info[ $column ] = 0;
			}
		}

		/* Sidebar */
		if( !isset( $info['blog_sidebar'] ) OR !$info['blog_sidebar'] )
		{
			$info['blog_sidebar'] = NULL;
		}
		
		/* Well update this later if we need too */
		$info['blog_social_group'] = NULL;
		
		if ( !\is_null( $socialgroup ) AND \is_array( $socialgroup ) )
		{
			$socialGroupId = \IPS\Db::i()->insert( 'core_sys_social_groups', array( 'owner_id' => $info['blog_member_id'] ) );
			$members	= array();
			$members[]	= array( 'group_id' => $socialGroupId, 'member_id' => $info['blog_member_id'] );
			foreach( $socialgroup['members'] AS $member )
			{
				try
				{
					$members[] = array( 'group_id' => $socialGroupId, 'member_id' => $this->software->app->getLink( $member, 'core_members', TRUE ) );
				}
				catch( \OutOfRangeException $e )
				{
					continue;
				}
			}
			\IPS\Db::i()->insert( 'core_sys_social_group_members', $members );
			
			$info['blog_social_group'] = $socialGroupId;
		}
		
		/* And now cover photo */
		if ( isset( $info['blog_cover_photo'] ) AND ( !\is_null( $filedata ) OR !\is_null( $filepath ) ) )
		{
			try
			{
				if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
				{
					$filedata = file_get_contents( $filepath );
				}
				
				$file = \IPS\File::create( 'blog_Blogs', $info['blog_cover_photo'], $filedata );
				$info['blog_cover_photo']			= (string) $file;
				$info['blog_cover_photo_offset']	= 0;
			}
			catch( \Exception $e )
			{
				$info['blog_cover_photo']			= NULL;
				$info['blog_cover_photo_offset']	= NULL;
			}
			catch( \ErrorException $e )
			{
				$info['blog_cover_photo']			= NULL;
				$info['blog_cover_photo_offset']	= NULL;
			}
		}
		else
		{
			$info['blog_cover_photo']			= NULL;
			$info['blog_cover_photo_offset']	= NULL;
		}
		
		if ( isset( $info['blog_club_id'] ) )
		{
			try
			{
				$info['blog_club_id'] = $this->software->app->getLink( $info['blog_club_id'], 'core_clubs', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['blog_club_id'] = NULL;
			}
		}
		else
		{
			$info['blog_club_id'] = NULL;
		}

		if( !empty( $info['blog_category_id'] ) )
		{
			try
			{
				$info['blog_category_id'] = $this->software->app->getLink( $info['blog_category_id'], 'blog_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['blog_category_id'] = $this->_getOrphanedBlogCategory();
			}
		}
		else
		{
			$info['blog_category_id'] = $this->_getOrphanedBlogCategory();
		}
		
		$id = $info['blog_id'];
		unset( $info['blog_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'blog_blogs', $info );
		$this->software->app->addLink( $inserted_id, $id, 'blog_blogs' );
		
		\IPS\Lang::saveCustom( 'blog', "blogs_blog_{$inserted_id}", $name );
		\IPS\Lang::saveCustom( 'blog', "blogs_blog_{$inserted_id}_desc", $desc );
		
		if ( $info['blog_club_id'] )
		{
			\IPS\Db::i()->insert( 'core_clubs_node_map', array(
				'club_id'		=> $info['blog_club_id'],
				'node_class'	=> "IPS\\blog\\Blog",
				'node_id'		=> $inserted_id,
				'name'			=> $name
			) );
			
			\IPS\blog\Blog::load( $inserted_id )->setPermissionsToClub( \IPS\Member\Club::load( $info['blog_club_id'] ) );
		}
		
		return $inserted_id;
	}

	/**
	 * Convert a Blog Category
	 *
	 * @param	array				$info			Array of blog information
	 * @return	integer|boolean						The ID of the inserted category, or FALSE on failure.
	 */
	public function convertBlogCategory( array $info )
	{
		if ( !isset( $info['category_id'] ) )
		{
			$this->software->app->log( 'blog_category_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( !isset( $info['category_name'] ) )
		{
			$name = "Converted Category {$info['category_id']}";
			$this->software->app->log( 'blog_category_missing_name', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['category_id'] );
		}
		else
		{
			$name = $info['category_name'];
			unset( $info['category_name'] );
		}

		$info['category_seo_name'] = \IPS\Http\Url::seoTitle( $name );

		if ( !array_key_exists( 'category_description', $info ) )
		{
			$desc = '';
		}
		else
		{
			/* Make sure the description isn't empty/null */
			$desc = $info['category_description'] ?: '';
			unset( $info['category_description'] );
		}

		if ( !isset( $info['category_position'] ) )
		{
			try
			{
				$position = \IPS\Db::i()->select( 'MAX(category_position)', 'blog_categories' )->first();
			}
			catch( \UnderflowException $e )
			{
				$position = 0;
			}

			$info['category_position'] = $position + 1;
		}

		if ( isset( $info['category_parent'] ) )
		{
			try
			{
				$info['category_parent'] = $this->software->app->getLink( $info['category_parent'], 'blog_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['category_conv_parent'] = $info['category_parent'];
			}
		}
		else
		{
			$info['category_parent'] = 0;
		}

		$id = $info['category_id'];
		unset( $info['category_id'] );

		$insertedId = \IPS\Db::i()->insert( 'blog_categories', $info );
		$this->software->app->addLink( $insertedId, $id, 'blog_categories' );

		\IPS\Lang::saveCustom( 'blogs', "blog_category_{$insertedId}", $name );
		\IPS\Lang::saveCustom( 'blogs', "blog_category_{$insertedId}_desc", $desc );

		\IPS\Db::i()->update( 'blog_categories', array( "category_parent" => $insertedId ), array( "category_conv_parent=?", $id ) );

		return $insertedId;
	}
	
	/**
	 * Convert a blog entry
	 *
	 * @param	array				$info		Array of entry information
	 * @param	string|NULL			$filepath	Path to the Cover Photo, or NULL
	 * @param	string|NULL			$filedata	Binary data for cover photo, or NULL
	 * @return	integer|boolean		The ID of the inserted entry, or FALSE on failure
	 */
	public function convertBlogEntry( array $info, $filepath=NULL, $filedata=NULL )
	{
		if ( !isset( $info['entry_id'] ) )
		{
			$this->software->app->log( 'blog_entry_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['entry_blog_id'] ) )
		{
			try
			{
				$info['entry_blog_id'] = $this->software->app->getLink( $info['entry_blog_id'], 'blog_blogs' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'blog_entry_missing_blog', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['entry_id'] );
				return FALSE;
			}
		}
		
		if ( !isset( $info['entry_name'] ) )
		{
			$info['entry_name'] = "Untitled Blog Entry {$info['entry_id']}";
			$this->software->app->log( 'blog_entry_missing_name', __METHOD__, \IPS\convert\App::LOG_NOTICE, $info['entry_id'] );
		}
		
		$info['entry_name_seo'] = \IPS\Http\Url::seoTitle( $info['entry_name'] );
		
		if ( empty( $info['entry_content'] ) )
		{
			$this->software->app->log( 'blog_entry_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['entry_content'] );
			return FALSE;
		}
		
		if ( isset( $info['entry_author_id'] ) )
		{
			try
			{
				$info['entry_author_id'] = $this->software->app->getLink( $info['entry_author_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['entry_author_id'] = 0;
			}
		}
		else
		{
			$info['entry_author_id'] = 0;
		}
		
		if ( !isset( $info['entry_author_name'] ) )
		{
			$author = \IPS\Member::load( $info['entry_author_id'] );
			
			if ( $author->member_id )
			{
				$info['entry_author_name'] = $author->name;
			}
			else
			{
				$info['entry_author_name'] = "Guest";
			}
		}
		
		if ( isset( $info['entry_date'] ) )
		{
			if ( $info['entry_date'] instanceof \IPS\DateTime )
			{
				$info['entry_date'] = $info['entry_date']->getTimestamp();
			}
		}
		else
		{
			$info['entry_date'] = time();
		}
		
		if ( !isset( $info['entry_status'] ) OR !\in_array( $info['entry_status'], array( 'published', 'draft' ) ) )
		{
			$info['entry_status'] = 'published';
		}
		
		/* Zero Defaults */
		foreach( array( 'entry_locked', 'entry_num_comments', 'entry_queued_comments', 'entry_featured', 'entry_views', 'entry_pinned', 'entry_hidden_comments', 'entry_is_future_entry' ) AS $zeroDefault )
		{
			if ( !isset( $info[ $zeroDefault ] ) )
			{
				$info[ $zeroDefault ] = 0;
			}
		}
		
		if ( isset( $info['entry_last_comment_mid'] ) )
		{
			try
			{
				$info['entry_last_comment_mid'] = $this->software->app->getLink( $info['entry_last_comment_mid'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['entry_last_comment_mid'] = 0;
			}
		}
		else
		{
			$info['entry_last_comment_mid'] = 0;
		}
		
		if ( !isset( $info['entry_hidden'] ) )
		{
			$info['entry_hidden'] = 1; # backwards
		}
		
		$info['entry_post_key'] = NULL;
		
		if ( isset( $info['entry_edit_time'] ) )
		{
			if ( $info['entry_edit_time'] instanceof \IPS\DateTime )
			{
				$info['entry_edit_time'] = $info['entry_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['entry_edit_time'] = NULL;
		}
		
		if ( !isset( $info['entry_edit_name'] ) )
		{
			$info['entry_edit_name'] = NULL;
		}

		if ( !isset( $info['entry_edit_reason'] ) )
		{
			$info['entry_edit_reason'] = '';
		}

		if ( !isset( $info['entry_append_edit'] ) )
		{
			$info['entry_append_edit'] = 0;
		}
		
		if ( isset( $info['entry_last_update'] ) )
		{
			if ( $info['entry_last_update'] instanceof \IPS\DateTime )
			{
				$info['entry_last_update'] = $info['entry_last_update']->getTimestamp();
			}
		}
		else
		{
			$info['entry_last_update'] = $info['entry_date'];
		}
		
		if ( isset( $info['entry_gallery_album'] ) )
		{
			try
			{
				$info['entry_gallery_album'] = $this->software->app->getSiblingLink( $info['entry_gallery_album'], 'gallery_albums', 'gallery' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['entry_gallery_album'] = NULL;
			}
		}
		else
		{
			$info['entry_gallery_album'] = NULL;
		}
		
		if ( isset( $info['entry_poll_state'] ) AND \is_array( $info['entry_poll_state'] ) )
		{
			if ( $poll = $this->convertPoll( $info['entry_poll_state']['poll_data'], $info['entry_poll_state']['vote_data'] ) )
			{
				$info['entry_poll_state'] = $poll;
			}
			else
			{
				$info['entry_poll_state']	= NULL;
			}
		}
		else
		{
			$info['entry_poll_state']	= NULL;
		}
		
		if ( isset( $info['entry_publish_date'] ) )
		{
			if ( $info['entry_publish_date'] instanceof \IPS\DateTime )
			{
				$info['entry_publish_date'] = $info['entry_publish_date']->getTimestamp();
			}
		}
		else
		{
			$info['entry_publish_date'] = 0;
		}
		
		$info['entry_image'] = '';
		
		if ( !isset( $info['entry_ip_address'] ) OR filter_var( $info['entry_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['entry_ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['entry_cover_photo'] ) AND ( !\is_null( $filepath ) OR !\is_null( $filedata ) ) )
		{
			try
			{
				if ( \is_null( $filedata ) AND !\is_null( $filepath ) )
				{
					$filedata = file_get_contents( $filepath );
				}
				
				$file = \IPS\File::create( 'blog_Entries', $info['entry_cover_photo'], $filedata );
				$info['entry_cover_photo']			= (string) $file;
				$info['entry_cover_photo_offset']	= 0;
			}
			catch( \Exception $e )
			{
				$info['entry_cover_photo']			= NULL;
				$info['entry_cover_photo_offset']	= NULL;
			}
		}
		else
		{
			$info['entry_cover_photo']			= NULL;
			$info['entry_cover_photo_offset']	= NULL;
		}

		if( !empty( $info['entry_category_id'] ) )
		{
			try
			{
				$info['entry_category_id'] = $this->software->app->getLink( $info['entry_category_id'], 'blog_entry_categories' );
			}
			catch( \OutOfRangeException $e )
			{
				$info['entry_category_id'] = NULL;
			}
		}
		else
		{
			$info['entry_category_id'] = NULL;
		}
		
		$id = $info['entry_id'];
		unset( $info['entry_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'blog_entries', $info );
		$this->software->app->addLink( $inserted_id, $id, 'blog_entries' );
		
		return $inserted_id;
	}

	/**
	 * Convert a Blog Entry Category
	 *
	 * @param	array				$info			Array of blog information
	 * @return	integer|boolean						The ID of the inserted category, or FALSE on failure.
	 */
	public function convertBlogEntryCategory( array $info )
	{
		if ( !isset( $info['entry_category_id'] ) )
		{
			$this->software->app->log( 'blog_entry_category_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( !isset( $info['entry_category_blog_id'] ) )
		{
			$this->software->app->log( 'blog_entry_category_blog_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}

		if ( isset( $info['entry_category_blog_id'] ) )
		{
			try
			{
				$info['entry_category_blog_id'] = $this->software->app->getLink( $info['entry_category_blog_id'], 'blog_blogs' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'blog_entry_category_missing_blog_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['entry_category_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'blog_entry_category_missing_blog_id', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['entry_category_id'] );
			return FALSE;
		}

		if ( !isset( $info['entry_category_name'] ) )
		{
			$info['entry_category_name'] = "Converted Category {$info['entry_category_id']}";
			$this->software->app->log( 'blog_entry_category_missing_name', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['entry_category_id'] );
		}

		$info['entry_category_seo_name'] = \IPS\Http\Url::seoTitle( $info['entry_category_name'] );

		if ( !isset( $info['entry_category_position'] ) )
		{
			try
			{
				$position = \IPS\Db::i()->select( 'MAX(entry_category_position)', 'blog_entry_categories', array( 'entry_category_blog_id=?', $info['entry_category_blog_id'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$position = 0;
			}

			$info['entry_category_position'] = $position + 1;
		}

		if ( !isset( $info['entry_category_count'] ) )
		{
			$info['entry_category_count'] = 0;
		}

		$id = $info['entry_category_id'];
		unset( $info['entry_category_id'] );

		$insertedId = \IPS\Db::i()->insert( 'blog_entry_categories', $info );
		$this->software->app->addLink( $insertedId, $id, 'blog_entry_categories' );

		return $insertedId;
	}
	
	/**
	 * Convert a blog comment
	 *
	 * @param	array				$info		Array of comment information
	 * @return	integer|boolean		The ID of the inserted comment, or FALSE on failure.
	 */
	public function convertBlogComment( array $info )
	{
		if ( !isset( $info['comment_id'] ) )
		{
			$this->software->app->log( 'blog_comment_missing_ids', __METHOD__, \IPS\convert\App::LOG_WARNING );
			return FALSE;
		}
		
		if ( isset( $info['comment_entry_id'] ) )
		{
			try
			{
				$info['comment_entry_id'] = $this->software->app->getLink( $info['comment_entry_id'], 'blog_entries' );
			}
			catch( \OutOfRangeException $e )
			{
				$this->software->app->log( 'blog_comment_missing_entry', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
				return FALSE;
			}
		}
		else
		{
			$this->software->app->log( 'blog_comment_missing_entry', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( empty( $info['comment_text'] ) )
		{
			$this->software->app->log( 'blog_comment_missing_content', __METHOD__, \IPS\convert\App::LOG_WARNING, $info['comment_id'] );
			return FALSE;
		}
		
		if ( isset( $info['comment_member_id'] ) )
		{
			try
			{
				$info['comment_member_id'] = $this->software->app->getLink( $info['comment_member_id'], 'core_members', TRUE );
			}
			catch( \OutOfRangeException $e )
			{
				$info['comment_member_id'] = 0;
			}
		}
		else
		{
			$info['comment_member_id'] = 0;
		}
		
		if ( !isset( $info['comment_member_name'] ) )
		{
			$author = \IPS\Member::load( $info['comment_member_id'] );
			
			if ( $author->member_id )
			{
				$info['comment_member_name'] = $author->name;
			}
			else
			{
				$info['comment_member_name'] = "Guest";
			}
		}
		
		if ( !isset( $info['comment_ip_address'] ) OR filter_var( $info['comment_ip_address'], FILTER_VALIDATE_IP ) === FALSE )
		{
			$info['comment_ip_address'] = '127.0.0.1';
		}
		
		if ( isset( $info['comment_date'] ) )
		{
			if ( $info['comment_date'] instanceof \IPS\DateTime )
			{
				$info['comment_date'] = $info['comment_date']->getTimestamp();
			}
		}
		else
		{
			$info['comment_date'] = time();
		}
		
		if ( isset( $info['comment_edit_time'] ) )
		{
			if ( $info['comment_edit_time'] instanceof \IPS\DateTime )
			{
				$info['comment_edit_time'] = $info['comment_edit_time']->getTimestamp();
			}
		}
		else
		{
			$info['comment_edit_time'] = NULL;
		}
		
		if ( !isset( $info['comment_approved'] ) )
		{
			$info['comment_approved'] = 1;
		}
		
		if ( !isset( $info['comment_edit_member_name'] ) )
		{
			$info['comment_edit_member_name'] = NULL;
		}
		
		if ( !isset( $info['comment_edit_show'] ) )
		{
			$info['comment_edit_show'] = 0;
		}
		
		$id = $info['comment_id'];
		unset( $info['comment_id'] );
		
		$inserted_id = \IPS\Db::i()->insert( 'blog_comments', $info );
		$this->software->app->addLink( $inserted_id, $id, 'blog_comments' );
		
		return $inserted_id;
	}
	
	/**
	 * Convert a blog RSS import
	 *
	 * @param	array				$info		Array of RSS Import information
	 * @return	integer|boolean		The ID of the inserted RSS import, or FALSE on failure
	 */
	public function convertBlogRssImport( array $info )
	{
		$info['rss_import_class'] = 'IPS\\blogs\\Entry';

		return parent::convertRssImport( $info, 'blog_blogs', 'blog_rss_import' );
	}
	
	/**
	 * Convert a blog entry attachment
	 *
	 * @param	array				$info		Array of entry attachment information
	 * @param	array				$map		Array of attachment map data
	 * @param	string|NULL			$filepath	URL/Path to attachment or NULL to use raw file data
	 * @param	string|NULL			$filedata	If $filepath is NULL, this should contain the raw contents of the attachment
	 * @param	string|NULL			$thumbnailpath	Path to thumbnail, or NULL
	 * @return	integer|boolean		The ID of the inserted attachment, or FALSE on failure.
	 */
	public function convertAttachment( $info=array(), $map=array(), $filepath = NULL, $filedata = NULL, $thumbnailpath = NULL ) 
	{
		$map['id1_type']		= 'blog_entries';
		$map['id1_from_parent']	= FALSE;
		if ( !isset( $info['id2'] ) OR \is_null( $info['id2'] ) )
		{
			$map['location_key'] = 'blog_Entries';
		}
		else
		{
			$map['location_key']	= 'blog_Blogs';
			$map['id2_type']		= 'blog_comments';
			$map['id2_from_parent']	= FALSE;
		}
		
		return parent::convertAttachment( $info, $map, $filepath, $filedata, $thumbnailpath );
	}

	/**
	 * Convert Club Blog
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly insertd blog, or FALSE on failure.
	 */
	public function convertClubBlog( $info=array() )
	{
		$insertedId = $this->convertBlog( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['blog_id'], 'core_clubs_blogs' );
		}
		return $insertedId;
	}

	/**
	 * Convert a Club Blog Entry
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted entry, or FALSE on failure.
	 */
	public function convertClubBlogEntry( $info=array() )
	{
		$insertedId = $this->convertBlogEntry( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['entry_id'], 'core_clubs_blog_entries' );
		}
		return $insertedId;
	}

	/**
	 * Convert a Club Blog Comment
	 *
	 * @param	array			$info		Data to insert
	 * @return	integer|boolean	The ID of the newly inserted comment, or FALSE on failure.
	 */
	public function convertClubBlogComment( $info=array() )
	{
		$insertedId = $this->convertBlogComment( $info );
		if ( $insertedId )
		{
			$this->software->app->addLink( $insertedId, $info['comment_id'], 'core_clubs_blog_comments' );
		}
		return $insertedId;
	}

	/**
	 * Get Parent ID for Orphaned Blogs
	 *
	 * @return	integer		The ID of the forum created for forums that do not have a parent forum.
	 */
	protected function _getOrphanedBlogCategory()
	{
		try
		{
			$id = $this->software->app->getLink( '__orphaned__', 'blog_categories' );
		}
		catch( \OutOfRangeException $e )
		{
			$id = $this->convertBlogCategory( array(
				'category_id'		=> '__orphaned__',
				'category_name'		=> "Converted Blogs",
				'category_parent'	=> 0,
			) );
		}

		return $id;
	}
}
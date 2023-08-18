<?php

/**
 * @brief		Converter vBulletin 4.x Master Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 */

namespace IPS\convert\Software\Core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * vBulletin Core Converter
 */
class _Vbulletin extends \IPS\convert\Software
{
	/**
	 * @brief	vBulletin 4 Stores all attachments under one table - this will store the content type for the forums app.
	 */
	protected static $postContentType		= NULL;
	
	/**
	 * @brief	The schematic for vB3 and vB4 is similar enough that we can make specific concessions in a single converter for either version.
	 */
	protected static $isLegacy					= NULL;

	/**
	 * @brief	Flag to indicate the post data has been fixed during conversion, and we only need to use Legacy Parser
	 */
	public static $contentFixed = TRUE;

	/**
	 * Constructor
	 *
	 * @param	\IPS\convert\App	$app	The application to reference for database and other information.
	 * @param	bool				$needDB	Establish a DB connection
	 * @return	void
	 * @throws	\InvalidArgumentException
	 */
	public function __construct( \IPS\convert\App $app, $needDB=TRUE )
	{
		$return = parent::__construct( $app, $needDB );
		
		if ( $needDB )
		{
			try
			{
				/* Is this vB3 or vB4? */
				if ( static::$isLegacy === NULL )
				{
					$version = $this->db->select( 'value', 'setting', array( "varname=?", 'templateversion' ) )->first();
					
					if ( mb_substr( $version, 0, 1 ) == '3' )
					{
						static::$isLegacy = TRUE;
					}
					else
					{
						static::$isLegacy = FALSE;
					}
				}

				/* If this is vB4, what is the content type ID for posts? */
				if ( static::$postContentType === NULL AND ( static::$isLegacy === FALSE OR \is_null( static::$isLegacy ) ) )
				{
					static::$postContentType = $this->db->select( 'contenttypeid', 'contenttype', array( "class=?", 'Post' ) )->first();
				}
			}
			catch( \Exception $e ) {} # If we can't query, we won't be able to do anything anyway
		}
		
		return $return;
	}
	
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "vBulletin (3.8.x/4.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "vbulletin";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertEmoticons'				=> array(
				'table'		=> 'smilie',
				'where'		=> NULL
			),
			'convertCustomBbcode'			=> array(
				'table'		=> 'bbcode',
				'where'		=> NULL
			),
			'convertProfileFieldGroups'		=> array(
				'table'		=> 'profilefieldcategory',
				'where'		=> NULL
			),
			'convertProfileFields'			=> array(
				'table'		=> 'profilefield',
				'where'		=> NULL
			),
			'convertGroups'					=> array(
				'table'		=> 'usergroup',
				'where'		=> NULL
			),
			'convertMembers'				=> array(
				'table'		    => 'user',
				'where'		    => NULL,
				'extra_steps'   => array( 'convertMembersFollowers' ),
			),
			'convertMembersFollowers'	=> array(
				'table'		=> 'userlist',
				'where'		=> array( "type=? AND friend=?", 'buddy', 'yes' )
			),
			'convertMemberHistory'			=> array(
				'table'		=> 'userchangelog',
				'where'		=> NULL
			),
			'convertStatuses'				=> array(
				'table'		=> 'visitormessage',
				'where'		=> NULL
			),
			'convertIgnoredUsers'			=> array(
				'table'		=> 'userlist',
				'where'		=> array( "type=?", 'ignore' )
			),
			'convertAnnouncements'			=> array(
				'table'		=> 'announcement',
				'where'		=> NULL
			),
			'convertPrivateMessages'		=> array(
				'table'		=> 'pm',
				'where'		=> array( "parentpmid=?", 0 ),
			),
			'convertPrivateMessageReplies'	=> array(
				'table'		=> 'pm',
				'where'		=> array( 'NOT (folderid= -1 AND parentpmid > 0)' )
			),
			'convertClubs'					=> array(
				'table'		=> 'socialgroup',
				'where'		=> NULL
			),
			'convertClubMembers'			=> array(
				'table'		=> 'socialgroupmember',
				'where'		=> NULL
			)
		);
	}

	/**
	 * Allows software to add additional menu row options
	 *
	 * @return	array
	 */
	public function extraMenuRows()
	{
		$rows = array();
		$count = $this->countRows( static::canConvert()['convertMembersFollowers']['table'], static::canConvert()['convertMembersFollowers']['where'] );

		if( $count )
		{
			$rows['convertMembersFollowers'] = array(
				'step_method'		=> 'convertMembersFollowers',
				'step_title'		=> 'convert_follows',
				'ips_rows'			=> \IPS\Db::i()->select( 'COUNT(*)', 'core_follow', array( 'follow_app=? and follow_area=?', 'core', 'member' ) ),
				'source_rows'		=> $count,
				'per_cycle'			=> 200,
				'dependencies'		=> array( 'convertMembers' ),
				'link_type'			=> 'core_follow',
			);
		}

		return $rows;
	}

	/**
	 * Count Source Rows for a specific step
	 *
	 * @param	string		$table		The table containing the rows to count.
	 * @param	array|NULL	$where		WHERE clause to only count specific rows, or NULL to count all.
	 * @param	bool		$recache	Skip cache and pull directly (updating cache)
	 * @return	integer
	 * @throws	\IPS\convert\Exception
	 */
	public function countRows( $table, $where=NULL, $recache=FALSE )
	{
		switch( $table )
		{
			case 'userchangelog':
				try
				{
					return $this->db->select( 'count(changeid)', 'userchangelog', array( $this->db->in( 'fieldname', array_keys( static::$changeLogTypes ) ) ) )->first();
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;

			case 'pm':
				try
				{
					/* vB doesn't put an index on a column that makes this converison much quicker, so we'll do it. */
					if( !$this->db->checkForIndex( 'pm', 'parent_id' ) )
					{
						$this->db->addIndex( 'pm', array(
							'type'			=> 'key',
							'name'			=> 'parent_id',
							'columns'		=> array( 'parentpmid', 'folderid' )
						) );
					}
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}

				return  parent::countRows( $table, $where, $recache );
				break;

			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
	}
	
	/**
	 * Can we convert passwords from this software.
	 *
	 * @return 	boolean
	 */
	public static function loginEnabled()
	{
		return TRUE;
	}
	
	/**
	 * Can we convert settings?
	 *
	 * @return	boolean
	 */
	public static function canConvertSettings()
	{
		return TRUE;
	}
	
	/**
	 * Settings Map
	 *
	 * @return	array
	 */
	public function settingsMap()
	{
		return array(
			'bbtitle'	=> 'board_name',
		);
	}
	
	/**
	 * Settings Map Listing
	 *
	 * @return	array
	 */
	public function settingsMapList()
	{
		$settings = array();
		foreach( $this->settingsMap() AS $theirs => $ours )
		{
			try
			{
				$setting = $this->db->select( 'varname, value', 'setting', array( "varname=?", $theirs ) )->first();
			}
			catch( \UnderflowException $e )
			{
				continue;
			}
			
			try
			{
				$title = $this->db->select( 'text', 'phrase', array( "varname=?", "setting_{$setting['varname']}_title" ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$title = $setting['varname'];
			}
			
			$settings[$setting['varname']] = array( 'title' => $title, 'value' => $setting['value'], 'our_key' => $ours, 'our_title' => \IPS\Member::loggedIn()->language()->addToStack( $ours ) );
		}
		
		return $settings;
	}
	
	/**
	 * Returns a block of text, or a language string, that explains what the admin must do to start this conversion
	 *
	 * @return	string
	 */
	public static function getPreConversionInformation()
	{
		return 'convert_vb4_preconvert';
	}
	
	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertEmoticons',
			'convertProfileFieldGroups',
			'convertProfileFields',
			'convertGroups',
			'convertMembers',
			'convertClubs'
		);
	}
	
	/**
	 * Get More Information
	 *
	 * @param	string	$method	Conversion method
	 * @return	array
	 */
	public function getMoreInfo( $method )
	{
		$return = array();
		
		switch( $method )
		{
			case 'convertEmoticons':
				$return['convertEmoticons'] = array(
					'emoticon_path'				=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_vb_smilie_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					),
					'keep_existing_emoticons'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
						'field_default'		=> TRUE,
						'field_required'	=> FALSE,
						'field_extra'		=> array(),
						'field_hint'		=> NULL,
					)
				);
				break;
			
			case 'convertProfileFieldGroups':
				$return['convertProfileFieldGroups'] = array();
				
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_groups' ), 'IPS\core\ProfileFields\Group' ) AS $group )
				{
					$options[$group->_id] = $group->_title;
				}
				
				foreach( $this->db->select( '*', 'profilefieldcategory' ) AS $group )
				{
					$id = $group['profilefieldcategoryid']; # vB doesn't use spaces in column names and its driving me crazy typing it out
					
					try
					{
						\IPS\Member::loggedIn()->language()->words["map_pfgroup_{$id}"]	= $this->db->select( 'text', 'phrase', array( "varname=?", "category{$id}_title" ) )->first();
					}
					catch( \UnderflowException $e )
					{
						\IPS\Member::loggedIn()->language()->words["map_pfgroup_{$id}"] = "vBulletin Profile Group {$id}";
					}

					\IPS\Member::loggedIn()->language()->words["map_pfgroup_{$id}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfgroup_desc' );
					
					$return['convertProfileFieldGroups']["map_pfgroup_{$group['profilefieldcategoryid']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			
			case 'convertProfileFields':
				$return['convertProfileFields'] = array();
				
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_data' ), 'IPS\core\ProfileFields\Field' ) AS $field )
				{
					$options[$field->_id] = $field->_title;
				}
				
				foreach( $this->db->select( '*', 'profilefield' ) AS $field )
				{
					try
					{
						\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['profilefieldid']}"]	= $this->db->select( 'text', 'phrase', array( "varname=?", "field{$field['profilefieldid']}_title" ) )->first();
					}
					catch( \UnderflowException $e )
					{
						\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['profilefieldid']}"]	= "vBulletin Profile Field {$field['profilefieldid']}";
					}
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['profilefieldid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );
					
					$return['convertProfileFields']["map_pfield_{$field['profilefieldid']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			
			case 'convertGroups':
				$return['convertGroups'] = array();
				
				$options = array();
				$options['none'] = 'None';
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}
				
				foreach( $this->db->select( '*', 'usergroup' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['usergroupid']}"]			= $group['title'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['usergroupid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );
					
					$return['convertGroups']["map_group_{$group['usergroupid']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL,
					);
				}
				break;
			
			case 'convertMembers':
				$return['convertMembers'] = array();
				
				/* We can only retain one type of photo */
				$return['convertMembers']['photo_type'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'avatars',
					'field_required'		=> TRUE,
					'field_extra'			=> array( 'options' => array( 'avatars' => \IPS\Member::loggedIn()->language()->addToStack( 'avatars' ), 'profile_photos' => \IPS\Member::loggedIn()->language()->addToStack( 'profile_photos' ) ) ),
					'field_hint'			=> NULL,
				);
				
				/* Find out where the photos live */
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'database',
					'field_required'		=> TRUE,
					'field_extra'			=> array(
						'options'				=> array(
							'database'				=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_database' ),
							'file_system'			=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_file_system' ),
						),
						'userSuppliedInput'	=> 'file_system',
					),
					'field_hint'			=> NULL,
					'field_validation'	=> function( $value ) { if ( $value != 'database' AND !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				/* Find out where the avatar gallery photos live */
				$return['convertMembers']['avatar_gallery_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_vb_avatar_gallery_path'),
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				
				/* And decide what to do about these... */
				foreach( array( 'homepage', 'icq', 'aim', 'yahoo', 'msn', 'skype', 'user_title' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => ucwords( $field ) ) );
					\IPS\Member::loggedIn()->language()->words["field_{$field}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field_desc' );
					$return['convertMembers']["field_{$field}"] = array(
						'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
						'field_default'			=> 'no_convert',
						'field_required'		=> TRUE,
						'field_extra'			=> array(
							'options'				=> array(
								'no_convert'			=> \IPS\Member::loggedIn()->language()->addToStack( 'no_convert' ),
								'create_field'			=> \IPS\Member::loggedIn()->language()->addToStack( 'create_field' ),
							),
							'userSuppliedInput'		=> 'create_field'
						),
						'field_hint'			=> NULL
					);
				}
				break;
			
			case 'convertClubs':
				\IPS\Member::loggedIn()->language()->words['club_photo_location']	= \IPS\Member::loggedIn()->language()->addToStack( 'photo_location' );

				$return['convertClubs'] = array();
				$return['convertClubs']['club_photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Radio',
					'field_default'			=> 'database',
					'field_required'		=> TRUE,
					'field_extra'			=> array(
						'options'				=> array(
							'database'				=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_database' ),
							'file_system'			=> \IPS\Member::loggedIn()->language()->addToStack( 'conv_store_file_system' ),
						),
						'userSuppliedInput'	=> 'file_system',
					),
					'field_hint'			=> NULL,
					'field_validation'	=> function( $value ) { if ( $value != 'database' AND !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
		}
		
		return ( isset( $return[ $method ] ) ) ? $return[ $method ] : array();
	}
	
	/**
	 * Finish - Adds everything it needs to the queues and clears data store
	 *
	 * @return	array		Messages to display
	 */
	public function finish()
	{
		/* Search Index Rebuild */
		\IPS\Content\Search\Index::i()->rebuild();
		
		/* Clear Cache and Store */
		\IPS\Data\Store::i()->clearAll();
		\IPS\Data\Cache::i()->clearAll();
		
		/* Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'core_member_status_updates', 'class' => 'IPS\core\Statuses\Status' ), 2, array( 'app', 'link', 'class' ) );
		
		/* Non-Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_announcements', 'extension' => 'core_Announcement' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );

		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\core\Messenger\Message' ), 3, array( 'class' ) );
		
		/* Clubs */
		\IPS\Task::queue( 'convert', 'RecountClubMembers', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );
		
		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild", "f_announce_rebuild" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		if( \preg_match( "#\[sigpic]#i", $post ) )
		{
			$post = preg_replace( "#\[sigpic\](.+?)\[\/sigpic\]#i", "", $post );
		}
		$post = str_replace( "<", "&lt;", $post );
		$post = str_replace( ">", "&gt;", $post );
		$post = str_replace( "'", "&#39;", $post );

		if( \preg_match( "#\[quote=#i", $post ) )
		{
			$post = preg_replace( "#\[quote=([^\];]+?)\]#i", "[quote name='$1']", $post );
			$post = preg_replace( "#\[quote=([^\];]+?);\d+\]#i", "[quote name='$1']", $post );
			$post = preg_replace( "#\[quote=([^\];]+?);n\d+\]#i", "[quote name='$1']", $post );
		}

		/* Remove video tags and allow our parser to handle the embeds it supports */
		if( \preg_match( "#\[video=#i", $post ) )
		{
			$post = preg_replace( "#\[video=[a-z_]+;[a-z0-9_-]+\](.*?)\[\/video\]#i", '$1', $post );
		}

		/* This is not a core feature in vBulletin, but is a popular addon and the code was supplied below.
			Addon URL: https://www.dragonbyte-tech.com/store/product/20-advanced-user-tagging/ */
		if( \preg_match( "#\[mention=#i", $post ) )
		{
			preg_match_all( '#\[MENTION=(\d+)\](.+?)\[\/MENTION\]#i', $post, $matches );

			if ( \count( $matches ) )
			{
				/* Make sure we actually have mention data */
				if ( isset( $matches[1] ) AND \count( $matches[1] ) )
				{
					$mentions = array();
					foreach ( $matches[1] AS $k => $v )
					{
						if ( isset( $matches[2][ $k ] ) )
						{
							$mentions[ $matches[2][ $k ] ] = $v;
						}
					}

					$maps = iterator_to_array( \IPS\Db::i()->select( 'name, member_id', 'core_members', array( \IPS\Db::i()->in( 'name', array_keys( $mentions ) ) ) )->setKeyField( 'name' )->setValueField( 'member_id' ) );

					foreach ( $mentions AS $memberName => $memberId )
					{
						if( !isset( $maps[ $memberName ] ) )
						{
							continue;
						}

						$memberNameQuoted = preg_quote( $memberName, '#' );
						$post = preg_replace( "#\[MENTION={$memberId}\]{$memberNameQuoted}\[\/MENTION\]#i", "[mention={$maps[ $memberName ]}]{$memberName}[/mention]", $post );
					}
				}
			}
		}

		return $post;
	}

	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'smilieid' );
		
		foreach( $this->fetch( 'smilie', 'smilieid' ) AS $emoticon )
		{
			$filename	= explode( '/', $emoticon['smiliepath'] );
			$filename	= array_pop( $filename );
			
			$info = array(
				'id'			=> $emoticon['smilieid'],
				'typed'			=> $emoticon['smilietext'],
				'filename'		=> $filename,
				'emo_position'	=> $emoticon['displayorder'],
			);
			
			try
			{
				$imageCategory = $this->db->select( '*', 'imagecategory', array( "imagecategoryid=?", $emoticon['imagecategoryid'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$imageCategory = array(
					'title'			=> "Converted",
					'displayorder'	=> 1,
				);
			}
			
			$set = array(
				'set'		=> md5( $imageCategory['title'] ),
				'title'		=> $imageCategory['title'],
				'position'	=> $imageCategory['displayorder']
			);

			/* In some rare cases, these may be URLs to a CDN */
			$filedata = $filepath = null;
			if ( mb_substr( $emoticon['smiliepath'], 0, 4 ) == 'http' )
			{
				/* Remote */
				try
				{
					$filedata = (string) \IPS\Http\Url::external( $emoticon['smiliepath'] )->request()->get();
				}
				catch( \Exception $e ) {}
			}
			else
			{
				$filepath = $this->app->_session['more_info']['convertEmoticons']['emoticon_path'];
			}
			
			$libraryClass->convertEmoticon( $info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $filepath, $filedata );
			
			$libraryClass->setLastKeyValue( $emoticon['smilieid'] );
		}
	}

	/**
	 * Convert profile field groups
	 *
	 * @return	void
	 */
	public function convertProfileFieldGroups()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'profilefieldcategoryid' );
		
		foreach( $this->fetch( 'profilefieldcategory', 'profilefieldcategoryid' ) AS $group )
		{
			try
			{
				$name = $this->db->select( 'text', 'phrase', array( "varname=?", "category{$group['profilefieldcategoryid']}_title" ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$name = "vBulletin Profile Group {$group['profilefieldcategoryid']}";
			}
			
			$merge = ( $this->app->_session['more_info']['convertProfileFieldGroups']["map_pfgroup_{$group['profilefieldcategoryid']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFieldGroups']["map_pfgroup_{$group['profilefieldcategoryid']}"] : NULL;
			
			$libraryClass->convertProfileFieldGroup( array(
				'pf_group_id'		=> $group['profilefieldcategoryid'],
				'pf_group_name'		=> $name,
				'pf_group_order'	=> $group['displayorder']
			), $merge );
			
			$libraryClass->setLastKeyValue( $group['profilefieldcategoryid'] );
		}
	}

	/**
	 * Convert profile fields
	 *
	 * @return	void
	 */
	public function convertProfileFields()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'profilefieldid' );
		
		foreach( $this->fetch( 'profilefield', 'profilefieldid' ) AS $field )
		{
			try
			{
				$name = $this->db->select( 'text', 'phrase', array( "varname=?", "field{$field['profilefieldid']}_title" ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$name = "vBulletin Profile Field {$field['profilefieldid']}";
			}
			
			try
			{
				$desc = $this->db->select( 'text', 'phrase', array( "varname=?", "field{$field['profilefieldid']}_desc" ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$desc = "";
			}
			
			$merge = ( $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$field['profilefieldid']}"] != 'none' ) ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$field['profilefieldid']}"] : NULL;

			if ( \in_array( mb_strtolower( $field['type'] ), array( 'select', 'radio', 'checkbox', 'select_multiple' ) ) )
			{
				$options = @\unserialize( $field['data'] );
				if ( $options )
				{
					$content = json_encode( $options );
				}
				else
				{
					/* We can try to restore the serialized value */
					$data = preg_replace_callback( '/s:(\d+):"(.*?)";/i', function( $match ) {
						return ( $match[1] == \strlen( $match[2] ) ) ? $match[0] : 's:' . \strlen( $match[2] ) . ':"' . $match[2] . '";';
					}, $field['data'] );

					if( $options = @\unserialize( $data ) )
					{
						$content = json_encode( $options );
					}
					else
					{
						/* Nothing else we can really do here */
						$content = json_encode( array() );
					}
				}
			}
			else
			{
				$content = json_encode( array() );
			}
			
			$type = static::_pfieldMap( $field['type'] );
			
			$multiple = 0;
			if ( $field['type'] == 'select_multiple' )
			{
				$multiple = 1;
			}
			
			$info = array(
				'pf_id'				=> $field['profilefieldid'],
				'pf_name'			=> $name,
				'pf_desc'			=> $desc,
				'pf_type'			=> $type,
				'pf_content'		=> $content,
				'pf_not_null'		=> ( \in_array( $field['required'], array( 1, 3 ) ) ) ? 1 : 0,
				'pf_member_hide'	=> $field['hidden'] ? 'hide' : 'all',
				'pf_max_input'		=> $field['maxlength'],
				'pf_member_edit'	=> ( $field['editable'] >= 1 ) ? 1 : 0,
				'pf_position'		=> $field['displayorder'],
				'pf_show_on_reg'	=> ( $field['required'] == 2 ) ? 1 : 0,
				'pf_input_format'	=> $field['regex'],
				'pf_group_id'		=> $field['profilefieldcategoryid'],
				'pf_multiple'		=> $multiple
			);
			
			$libraryClass->convertProfileField( $info, $merge );
			
			$libraryClass->setLastKeyValue( $field['profilefieldid'] );
		}
	}
	
	/**
	 * Maps vBulletin Profile Field type to the IPS Equivalent
	 *
	 * @param	string	$type	The vB Field Type
	 * @return	string	The IPS Field Type
	 */
	protected static function _pfieldMap( $type )
	{
		switch( mb_strtolower( $type ) )
		{
			case 'select':
			case 'radio':
			case 'checkbox':
				return ucwords( $type );
				break;

			case 'textarea':
				return 'TextArea';
				break;
			
			case 'select_multiple':
				return 'Select';
				break;
			
			/* Just do Text as default */
			default:
				return 'Text';
				break;
		}
	}

	/**
	 * Convert groups
	 *
	 * @return	void
	 */
	public function convertGroups()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'usergroupid' );
		
		foreach( $this->fetch( 'usergroup', 'usergroupid' ) AS $group )
		{
			/* <3 Closures */
			$self = $this;
			$checkpermission = function ( $name, $perm ) use ( $group, $self )
			{
				if ( $group[$name] & $self::$bitOptions[$name][$perm] )
				{
					return TRUE;
				}
				else
				{
					return FALSE;
				}
			};
			
			/* Work out promotion */
			$g_promotion = '-1&-1';
			$gbw_promote_unit = 0;
			try
			{
				$promotion = $this->db->select( '*', 'userpromotion', array( "usergroupid=?", $group['usergroupid'] ) )->first();
				
				/* We only support Posts or Join Date */
				if ( \in_array( $promotion['strategy'], array( 17, 18 ) ) )
				{
					switch( $promotion['strategy'] )
					{
						case 17: # posts
							$g_promotion		= array( $promotion['joinusergroupid'], $promotion['posts'] );
							break;
						
						case 18: #joindate
							$g_promotion		= array( $promotion['joinusergroupid'], $promotion['date'] );
							$gbw_promote_unit	= 1;
							break;
					}
				}
			}
			catch( \UnderflowException $e ) {}
			
			/* Work out photo vars - vBulletin has a concept of avatars and profile photos, so we are just going to use the larger of the two */
			$g_max_photo_vars	= array();
			$g_max_photo_vars[]	= ( $group['profilepicmaxsize'] > $group['avatarmaxsize'] ) ? $group['profilepicmaxsize'] : $group['avatarmaxsize'];
			
			/* We don't have individual controls for height and width, so work out which value is the largest and use that */
			$highestValue = 0;
			foreach( array( 'profilepicmaxheight', 'avatarmaxheight', 'profilepicmaxwidth', 'avatarmaxwidth' ) AS $value )
			{
				if ( $group[$value] > $highestValue )
				{
					$highestValue = $group[$value];
				}
			}
			
			$g_max_photo_vars[]	= $highestValue;
			$g_max_photo_vars[]	= $highestValue;
			
			/* Work out signature limits */
			$g_signature_limits = '1:::::';
			if ( $checkpermission( 'genericpermissions', 'canusesignature' ) )
			{
				$g_signature_limits		= array();
				$g_signature_limits[]	= 0;
				$g_signature_limits[]	= $group['sigmaximages'];
				$g_signature_limits[]	= $group['sigpicmaxwidth'];
				$g_signature_limits[]	= $group['sigpicmaxheight'];
				$g_signature_limits[]	= '';
				$g_signature_limits[]	= ( $group['sigmaxlines'] > 0 ) ? $group['sigmaxlines'] : '';
			}
			
			/* Let's dissect all of these bit options */
			$info = array(
				'g_id'					=> $group['usergroupid'],
				'g_name'				=> $group['title'],
				'g_view_board'			=> $checkpermission( 'forumpermissions', 'canview' ) ? 1 : 0,
				'g_mem_info'			=> $checkpermission( 'genericpermissions', 'canviewmembers' ) ? 1 : 0,
				'g_use_search'			=> $checkpermission( 'forumpermissions', 'cansearch' ) ? 1 : 0,
				'g_edit_profile'		=> $checkpermission( 'genericpermissions', 'canmodifyprofile' ) ? 1 : 0,
				'g_edit_posts'			=> $checkpermission( 'forumpermissions', 'caneditpost' ) ? 1 : 0,
				'g_delete_own_posts'	=> $checkpermission( 'forumpermissions', 'candeletepost' ) ? 1 : 0,
				'g_use_pm'				=> ( $group['pmquota'] > 0 ) ? 1 : 0,
				'g_is_supmod'			=> $checkpermission( 'adminpermissions', 'ismoderator' ) ? 1 : 0,
				'g_access_cp'			=> $checkpermission( 'adminpermissions', 'cancontrolpanel' ) ? 1 : 0,
				'g_append_edit'			=> $checkpermission( 'genericoptions', 'showeditedby' ) ? 1 : 0, # Fun fact, I couldn't find this as it was actually in the place it should be rather than forumpermissions
				'g_access_offline'		=> $checkpermission( 'adminpermissions', 'cancontrolpanel' ) ? 1 : 0,
				'g_attach_max'			=> ( $group['attachlimit'] > 0 ) ? $group['attachlimit'] : -1,
				'prefix'				=> $group['opentag'],
				'suffix'				=> $group['closetag'],
				'g_max_messages'		=> $group['pmquota'],
				'g_max_mass_pm'			=> $group['pmsendmax'],
				'g_promotion'			=> $g_promotion,
				'g_photo_max_vars'		=> $g_max_photo_vars,
				'g_bypass_badwords'		=> ( ( $checkpermission( 'adminpermissions', 'ismoderator' ) OR $checkpermission( 'adminpermissions', 'cancontrolpanel' ) ) AND $this->_setting( 'ctCensorMod' ) ),
				'g_mod_preview'			=> !$checkpermission( 'forumpermissions', 'followforummoderation' ) ? 1 : 0,
				'g_signature_limits'	=> $g_signature_limits,
				'g_bitoptions'			=> array(
					'gbw_promote_unit_type'		=> $gbw_promote_unit, 			// Type of group promotion to use. 1 is days since joining, 0 is content count. Corresponds to g_promotion
					'gbw_no_status_update'		=> !$checkpermission( 'visitormessagepermissions', 'canmessageownprofile' ), 			// Can NOT post status updates
					'gbw_allow_upload_bgimage'	=> 1, 		// Can upload a cover photo?
					'gbw_view_reps'				=> $checkpermission( 'genericpermissions2', 'canprofilerep' ), 		// Can view who gave reputation?
					'gbw_no_status_import'		=> !$checkpermission( 'visitormessagepermissions', 'canmessageownprofile' ), 	// Can NOT import status updates from Facebook/Twitter
					'gbw_disable_tagging'		=> !$checkpermission( 'genericpermissions', 'cancreatetag' ), 	// Can NOT use tags
					'gbw_disable_prefixes'		=> !$checkpermission( 'genericpermissions', 'cancreatetag' ), 	// Can NOT use prefixes
					'gbw_pm_override_inbox_full'=> $checkpermission( 'pmpermissions', 'canignorequota' ),	// 1 means this group can send other members PMs even when that member's inbox is full
					'gbw_cannot_be_ignored'		=> ( ( $checkpermission( 'adminpermissions', 'ismoderator' ) OR $checkpermission( 'adminpermissions', 'cancontrolpanel' ) ) AND $this->_setting( 'ignoremods' ) ),	// 1 means they cannot be ignored. 0 means they can
				),
				'g_hide_own_posts'		=> $checkpermission( 'forumpermissions', 'candeletepost' ),
				'g_pm_flood_mins'		=> $this->_setting( 'pmfloodtime' ) / 60,
				'g_post_polls'			=> $checkpermission( 'forumpermissions', 'canpostpoll' ) ? 1 : 0,
				'g_vote_polls'			=> $checkpermission( 'forumpermissions', 'canvote' ) ? 1 : 0,
				'g_topic_rate_setting'	=> $checkpermission( 'forumpermissions', 'canthreadrate' ) ? 1 : 0
			);
			
			$merge = ( $this->app->_session['more_info']['convertGroups']["map_group_{$group['usergroupid']}"] != 'none' ) ? $this->app->_session['more_info']['convertGroups']["map_group_{$group['usergroupid']}"] : NULL;
			
			$libraryClass->convertGroup( $info, $merge );
			
			$libraryClass->setLastKeyValue( $group['usergroupid'] );
		}

		/* Now check for group promotions */
		if( \count( $libraryClass->groupPromotions ) )
		{
			foreach( $libraryClass->groupPromotions as $groupPromotion )
			{
				$libraryClass->convertGroupPromotion( $groupPromotion );
			}
		}
	}

	/**
	 * @brief	Member history type map
	 */
	protected static $changeLogTypes = array( 'username' => 'display_name', 'email' => 'email_change', 'usergroupid' => 'group', 'membergroupids' => 'group' );

	/**
	 * Convert member history
	 *
	 * @return	void
	 */
	public function convertMemberHistory()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'changeid' );

		foreach( $this->fetch( 'userchangelog', 'changeid', array( $this->db->in( 'fieldname', array_keys( static::$changeLogTypes ) ) ) ) AS $change )
		{
			$info = array(
				'log_id'		=> $change['changeid'],
				'log_member'	=> $change['userid'],
				'log_by'		=> $change['adminid'],
				'log_type'		=> static::$changeLogTypes[ $change['fieldname'] ],
				'log_data'		=> array( 'old' => $change['oldvalue'], 'new' => $change['newvalue'] ),
				'log_date'		=> $change['change_time']
			);

			/* Add extra data for group change */
			if( static::$changeLogTypes[ $change['fieldname'] ] == 'group' )
			{
				$info['log_data']['type'] = $change['fieldname'] == 'usergroupid' ? 'primary' : 'secondary';
				$info['log_data']['by'] = 'manual';
			}

			$libraryClass->convertMemberHistory( $info );
			$libraryClass->setLastKeyValue( $change['changeid'] );
		}
	}

	/**
	 * @brief   default pseudo fields to convert
	 */
	protected static $pseudoFields = [ 'homepage', 'icq', 'aim', 'yahoo', 'msn', 'skype', 'user_title' ];

	/**
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		$libraryClass::setKey( 'user.userid' );

		/* User titles */
		$userTitles = iterator_to_array( $this->db->select( 'title', 'usertitle' ) );
		$userTitles = array_merge( $userTitles, iterator_to_array( $this->db->select( 'usertitle', 'usergroup', [ 'usertitle!=?', '' ] ) ) );

		$data = iterator_to_array( $this->fetch( 'user', 'user.userid', NULL, 'user.*, usertextfield.signature' )->join( 'usertextfield', 'user.userid = usertextfield.userid' ) );
		$userIds = array_column( $data, 'userid' );

		/* User Data */
		$bans = iterator_to_array( $this->db->select( 'liftdate, userid', 'userban', [ $this->db->in( 'userid', $userIds ) ] )->setKeyField('userid')->setValueField( 'liftdate') );
		$activation = iterator_to_array( $this->db->select( '*', 'useractivation', [ $this->db->in( 'userid', $userIds ) ] )->setKeyField('userid') );
		$fields = iterator_to_array( $this->db->select( '*', 'userfield', [ $this->db->in( 'userid', $userIds ) ] )->setKeyField( 'userid' ) );

		foreach( $data AS $user )
		{
			/* <3 Closures */
			$self = $this;
			$checkpermission = function ( $name, $perm ) use ( $user, $self )
			{
				if ( $name == 'useroptions' )
				{
					$key = 'options';
				}
				else
				{
					$key = $name;
				}
				
				if ( $user[$key] & $self::$bitOptions[$name][$perm] )
				{
					return TRUE;
				}
				else
				{
					return FALSE;
				}
			};
			
			/* Fetch our last warning */
			$warnings = iterator_to_array( $this->db->select( '*', 'infraction', array( "userid=?", $user['userid'] ), "dateline ASC" ) );
			$lastWarning = 0;

			/* If there are warnings, get the last warning */
			if( \count( $warnings ) )
			{
				$lastWarning = end( $warnings );
				reset( $warnings );
			}

			/* Birthday */
			if ( $user['birthday'] )
			{
				list( $bday_month, $bday_day, $bday_year ) = explode( '-', $user['birthday'] );
				
				if ( $bday_year == '0000' )
				{
					$bday_year = NULL;
				}
			}
			else
			{
				$bday_month = $bday_day = $bday_year = NULL;
			}
			
			/* Auto Track */
			$auto_track = 0;
			switch( $user['autosubscribe'] )
			{
				case 1:
					$auto_track = array( 'content' => true, 'comments' => true, 'method' => 'immediate' );
					break;
				
				case 2:
					$auto_track = array( 'content' => true, 'comments' => true, 'method' => 'daily' );
					break;
				
				case 3:
					$auto_track = array( 'content' => true, 'comments' => true, 'method' => 'weekly' );
					break;
			}
			
			/* User Banned */
			$temp_ban = 0;
			try
			{
				if( !isset( $bans[ $user['userid'] ] ) )
				{
					throw new \UnderflowException();
				}
				$temp_ban = $bans[ $user['userid'] ];
				
				if ( $temp_ban == 0 )
				{
					$temp_ban = -1;
				}
			}
			catch( \UnderflowException $e ) {}
			
			/* Main Members Table */
			$info = array(
				'member_id'					=> $user['userid'],
				'member_group_id'			=> $user['usergroupid'],
				'mgroup_others'				=> $user['membergroupids'],
				'name'						=> html_entity_decode( $user['username'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
				'email'						=> $user['email'],
				'joined'					=> $user['joindate'],
				'ip_address'				=> $user['ipaddress'],
				'warn_level'				=> ( $user['ipoints'] > 2147483647 ) ? 2147483647 : $user['ipoints'],
				'warn_lastwarn'				=>  ( \is_array( $lastWarning ) ) ? $lastWarning['dateline'] : $lastWarning,
				'bday_day'					=> $bday_day,
				'bday_month'				=> $bday_month,
				'bday_year'					=> $bday_year,
				'last_visit'				=> $user['lastvisit'],
				'last_activity'				=> $user['lastactivity'],
				'auto_track'				=> $auto_track,
				'temp_ban'					=> $temp_ban,
				'members_profile_views'		=> $user['profilevisits'],
				'conv_password'				=> $user['password'],
				'conv_password_extra'		=> $user['salt'],
				'members_bitoptions'		=> array(
					'view_sigs'						=> $checkpermission( 'useroptions', 'showsignatures' ),		// View signatures?
					'coppa_user'					=> $checkpermission( 'useroptions', 'coppauser' ),		// Was the member validated using coppa?
				),
				'members_bitoptions2'	=> array(
					'show_pm_popup'					=> $user['pmpopup'], // "Show pop-up when I have a new message"
					'is_anon'						=> $checkpermission( 'useroptions', 'invisible' ), // Anonymous status
				),
				'pp_setting_count_comments'	=> $checkpermission( 'useroptions', 'vm_enable' ),
				'pp_reputation_points'		=> $user['reputation'],
				'signature'					=> $user['signature'] ?: '',
				'allow_admin_mails'			=> $checkpermission( 'useroptions', 'adminemail' ),
				'members_disable_pm'		=> !$checkpermission( 'useroptions', 'receivepm' ),
				'member_posts'				=> $user['posts'],
			);

			/* If there is a validation record for this user, treat them as validating */
			try
			{
				if( !isset( $activation[ $user['userid'] ] ) )
				{
					throw new \UnderflowException();
				}
				$activation = $activation[ $user['userid'] ];

				$info['members_bitoptions']['validating'] = TRUE;
			}
			catch( \UnderflowException $e )
			{
				$activation = NULL;
			}
			
			/* Profile Fields */
			try
			{
				if( !isset( $fields[ $user['userid'] ] ) )
				{
					throw new \UnderflowException();
				}
				$profileFields = $fields[ $user['userid'] ];
				
				unset( $profileFields['userid'] );
				unset( $profileFields['temp'] );
				
				/* Basic fields - we only need ID => Value, the library will handle the rest */
				foreach( $profileFields AS $key => $value )
				{
					$profileFields[ str_replace( 'field', '', $key ) ] = $value;
				}
			}
			catch( \UnderflowException $e )
			{
				$profileFields = array();
			}
			
			/* Pseudo Fields */
			foreach( static::$pseudoFields AS $pseudo )
			{
				/* Are we retaining? */
				if ( $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"] == 'no_convert' )
				{
					/* No, skip */
					continue;
				}
				
				try
				{
					/* We don't actually need this, but we need to make sure the field was created */
					$this->app->getLink( $pseudo, 'core_pfields_data' );
				}
				catch( \OutOfRangeException $e )
				{
					$libraryClass->convertProfileField( array(
						'pf_id'				=> $pseudo,
						'pf_name'			=> $this->app->_session['more_info']['convertMembers']["field_{$pseudo}"],
						'pf_desc'			=> '',
						'pf_type'			=> 'Text',
						'pf_content'		=> '[]',
						'pf_member_hide'	=> 'all',
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
					) );
				}

				if( $pseudo == 'user_title' )
				{
					$profileFields[ $pseudo ] = \in_array( $user['usertitle'], $userTitles ) ? NULL : $user['usertitle'];
				}
				else
				{
					$profileFields[ $pseudo ] = $user[ $pseudo ];
				}
			}
			
			/* Profile Photos */
			$firstTable		= 'customavatar';
			$secondTable	= 'customprofilepic';
			if ( $this->app->_session['more_info']['convertMembers']['photo_type'] == 'profile_photos' )
			{
				$firstTable		= 'customprofilepic';
				$secondTable	= 'customavatar';
			}
			$filedata = NULL;
			$filename = NULL;
			if ( $this->app->_session['more_info']['convertMembers']['photo_location'] == 'database' )
			{
				try
				{
					foreach( $this->db->select( 'filedata, filename', $firstTable, array( "userid=?", $user['userid'] ) )->first() AS $key => $value )
					{
						if ( $key == 'filedata' )
						{
							$filedata = $value;
						}
						else
						{
							$filename = $value;
						}
					}
					$filepath = NULL;
				}
				catch( \UnderflowException $e )
				{
					try
					{
						foreach( $this->db->select( 'filedata, filename', $secondTable, array( "userid=?", $user['userid'] ) )->first() AS $key => $value )
						{
							if ( $key == 'filedata' )
							{
								$filedata = $value;
							}
							else
							{
								$filename = $value;
							}
						}

						$filepath = NULL;
					}
					catch( \UnderflowException $e )
					{
						list( $filedata, $filename, $filepath ) = array( NULL, NULL, NULL );
					}
				}

				if( !empty( $filedata ) AND empty( $filename ) )
				{
					$filename = 'vb-import-' . $info['member_id'] . '.jpg'; // We'll use job here, but image detection will figure it out from magic bytes
				}
			}
			else
			{
				$filepath = $this->app->_session['more_info']['convertMembers']['photo_location'];
				$first	= 'avatar';
				$second	= 'profilepic';
				
				if ( $this->app->_session['more_info']['convertMembers']['photo_type'] == 'profile_photos' )
				{
					$first	= 'profilepic';
					$second	= 'avatar';
				}
				
				try
				{
					try
					{
						$ext = $this->db->select( 'filename', $firstTable, array( "userid=?", $user['userid'] ) )->first();
						$ext = explode( '.', $ext );
						$ext = array_pop( $ext );
						
						if ( file_exists( rtrim( $filepath, '/' ) . '/' . $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.'. $ext ) )
						{
							$filename = $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.'. $ext;
						}
						else
						{
							/* The filename with the original extension doesn't exist. vBulletin may storing the image as a .gif file instead, so try that. */
							if ( file_exists( rtrim( $filepath, '/' ) . '/' . $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.gif' ) )
							{
								$filename = $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.gif';
							}
							else
							{
								/* Throw an exception so we can try the other */
								throw new \UnderflowException;
							}
						}
					}
					catch( \UnderflowException $e )
					{
						$ext = $this->db->select( 'filename', $secondTable, array( "userid=?", $user['userid'] ) )->first();
						$ext = explode( '.', $ext );
						$ext = array_pop( $ext );
						
						if ( file_exists( rtrim( $filepath, '/' ) . '/' . $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.'. $ext ) )
						{
							$filename = $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.'. $ext;
						}
						else
						{
							/* The filename with the original extension doesn't exist. vBulletin may be storing the image as a .gif file instead, so try that. */
							if ( file_exists( rtrim( $filepath, '/' ) . '/' . $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.gif' ) )
							{
								$filename = $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.gif';
							}
							else
							{
								throw new \UnderflowException;
							}
						}
					}
				}
				catch( \UnderflowException $e )
				{
					list( $filedata, $filename, $filepath ) = array( NULL, NULL, NULL );
				}
			}

			/* We'll try the avatar gallery */
			if( empty( $filename ) AND empty( $filedata ) AND (int) $user['avatarid'] )
			{
				$avatar = $this->_getFromAvatarGallery( (int) $user['avatarid'] );

				if( \is_array( $avatar ) )
				{
					$filename = $avatar['name'];
					$filedata = $avatar['data'];
				}
			}
			
			$memberId = $libraryClass->convertMember( $info, $profileFields, $filename, $filepath, $filedata );

			/* If we are validating, store our validation row entry */
			if( $activation !== NULL AND $memberId )
			{
				\IPS\Db::i()->replace( 'core_validating', array(
					'vid'			=> md5( $activation['activationid'] . $activation['useractivationid'] ),
					'member_id'		=> $memberId,
					'entry_date'	=> $activation['dateline'],
					'new_reg'		=> ( $activation['emailchange'] == 0 ? TRUE : FALSE ),
					'email_chg'		=> ( $activation['emailchange'] == 1 ? TRUE : FALSE ),
					'user_verified'	=> FALSE,
					'ip_address'	=> $user['ipaddress'],
					'email_sent'	=> $activation['dateline']
				) );
			}

			/* Skip this if we know that the user doesn't have any warnings */
			if( \count( $warnings ) )
			{
				/* And warn logs made on the profile - we'll do content specific later */
				foreach ( $warnings AS $warn )
				{
					if( $warn['postid'] )
					{
						continue;
					}

					$warnId = $libraryClass->convertWarnLog( array(
						'wl_id' 			=> $warn['infractionid'],
						'wl_member' 		=> $warn['userid'],
						'wl_moderator' 		=> $warn['whoadded'],
						'wl_date' 			=> $warn['dateline'],
						'wl_points' 		=> $warn['points'],
						'wl_note_member' 	=> $warn['note'],
						'wl_note_mods' 		=> $warn['customreason'],
					) );

					/* Add a member history record for this member */
					$libraryClass->convertMemberHistory( array(
							'log_id' 		=> 'w' . $warn['infractionid'],
							'log_member' 	=> $warn['userid'],
							'log_by' 		=> $warn['whoadded'],
							'log_type' 		=> 'warning',
							'log_data' 		=> array( 'wid' => $warnId ),
							'log_date' 		=> $warn['dateline']
						)
					);
				}
			}
			
			$libraryClass->setLastKeyValue( $user['userid'] );
		}
	}

	/**
	 * Convert member followers
	 *
	 * @return 	void
	 */
	public function convertMembersFollowers()
	{
		$libraryClass = $this->getLibrary();

		foreach ( $this->fetch( 'userlist', 'relationid', array( "type=? AND friend=?", 'buddy', 'yes' ) ) as $row )
		{
			$libraryClass->convertFollow( array(
				'follow_app'            => 'core',
				'follow_area'           => 'member',
				'follow_rel_id'         => $row['relationid'],
				'follow_rel_id_type'    => 'core_members',
				'follow_member_id'      => $row['userid'],
				'follow_notify_freq'    => 'none',
			) );
		}
	}

	/**
	 * Convert status updates
	 *
	 * @return	void
	 */
	public function convertStatuses()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'vmid' );
		
		foreach( $this->fetch( 'visitormessage', 'vmid' ) AS $status )
		{
			/* Work out approval status */
			switch( $status['state'] )
			{
				case 'moderation':
					$approved = 0;
					break;
				
				case 'deleted':
					$approved = -1;
					break;
				
				case 'visible':
				default:
					$approved = 1;
					break;
			}
			
			$info = array(
				'status_id'			=> $status['vmid'],
				'status_member_id'	=> $status['userid'],
				'status_date'		=> $status['dateline'],
				'status_content'	=> static::fixPostData( $status['pagetext'] ),
				'status_author_id'	=> $status['postuserid'],
				'status_author_ip'	=> $status['ipaddress'],
				'status_approved'	=> $approved
			);
			
			$libraryClass->convertStatus( $info );
			
			$libraryClass->setLastKeyValue( $status['vmid'] );
		}
	}
	
	/**
	 * Convert one or more settings
	 *
	 * @param	array	$settings	Settings to convert
	 * @return	void
	 */
	public function convertSettings( $settings=array() )
	{
		foreach( $this->settingsMap() AS $theirs => $ours )
		{
			if ( !isset( $values[$ours] ) OR $values[$ours] == FALSE )
			{
				continue;
			}
			
			try
			{
				$setting = $this->db->select( 'value', 'setting', array( "varname=?", $theirs ) )->first();
			}
			catch( \UnderflowException $e )
			{
				continue;
			}
			
			\IPS\Db::i()->update( 'core_sys_conf_settings', array( 'conf_value' => $setting ), array( "conf_key=?", $ours ) );
		}
	}

	/**
	 * Convert ignored users
	 *
	 * @return	void
	 */
	public function convertIgnoredUsers()
	{
		$libraryClass = $this->getLibrary();
		
		foreach( $this->fetch( 'userlist', 'userid', array( "type=?", 'ignore' ) ) AS $ignore )
		{
			$info = array(
				'ignore_id'			=> $ignore['userid'] . '-' . $ignore['relationid'],
				'ignore_owner_id'	=> $ignore['userid'],
				'ignore_ignore_id'	=> $ignore['relationid'],
			);
			
			foreach( \IPS\core\Ignore::types() AS $type )
			{
				$info['ignore_' . $type] = 1;
			}
			
			$libraryClass->convertIgnoredUser( $info );
		}
	}

	/**
	 * Convert custom bbcode
	 *
	 * @return	void
	 */
	public function convertCustomBbcode()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'bbcodeid' );

		foreach( $this->fetch( 'bbcode', 'bbcodeid' ) AS $bbcode )
		{
			$libraryClass->convertCustomBbcode( array(
				'bbcode_id'				=> $bbcode['bbcodeid'],
				'bbcode_title'			=> $bbcode['title'],
				'bbcode_description'	=> $bbcode['bbcodeexplanation'],
				'bbcode_tag'			=> $bbcode['bbcodetag'],
				'bbcode_replacement'	=> str_replace( '%1$s', '{content}', $bbcode['bbcodereplacement'] ),
				'bbcode_example'		=> $bbcode['bbcodeexample']
			) );

			$libraryClass->setLastKeyValue( $bbcode['bbcodeid'] );
		}
	}

	/**
	 * Convert announcements
	 *
	 * @return	void
	 */
	public function convertAnnouncements()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'announcementid' );
		
		foreach( $this->fetch( 'announcement', 'announcementid' ) AS $announcement )
		{
			$libraryClass->convertAnnouncement( array(
				'announce_id'			=> $announcement['announcementid'],
				'announce_title'		=> $announcement['title'],
				'announce_content'		=> $announcement['pagetext'],
				'announce_member_id'	=> $announcement['userid'],
				'announce_views'		=> $announcement['views'],
				'announce_start'		=> $announcement['startdate'],
				'announce_end'			=> $announcement['enddate'],
				'announce_active'		=> 0, # Set this to no - we can't really figure out where these go.
			) );
			
			$libraryClass->setLastKeyValue( $announcement['announcementid'] );
		}
	}

	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'pm.pmid' );
		
		foreach( $this->fetch( 'pm', 'pm.pmid', array( "pm.parentpmid=?", 0 ), "pm.*, pmtext.*" )->join( 'pmtext', 'pm.pmtextid = pmtext.pmtextid' ) AS $pm )
		{
			if ( !$pm['pmtextid'] )
			{
				$libraryClass->setLastKeyValue( $pm['pmid'] );
				continue;
			}

			/* Convert PM */
			$this->_convertPm( $libraryClass, $pm );

			$libraryClass->setLastKeyValue( $pm['pmid'] );
		}
	}

	/**
	 * Convert PM replies
	 *
	 * @return	void
	 */
	public function convertPrivateMessageReplies()
	{
		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'pm.pmid' );

		foreach( $this->fetch( 'pm', 'pm.pmid', array( 'NOT (pm.folderid= -1 AND pm.parentpmid > 0)' ), "pm.*, pmtext.*, test.pmid as parent_test" )
					 	->join( 'pmtext', 'pm.pmtextid = pmtext.pmtextid' )
						->join( array( 'pm', 'test' ), 'pm.parentpmid=test.pmid' )
				 AS $pm )
		{
			/* See if the parent exists, or whether it's already been deleted in vBulletin. */
			$parentId = FALSE;
			if( $pm['parentpmid'] AND !$pm['parent_test'] )
			{
				/* It has previously been deleted, so this message can become it's own conversation */
				$parentId = $this->_convertPm( $libraryClass, $pm );
			}

			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $pm['pmid'],
				'msg_topic_id'		=> (!$pm['parentpmid'] OR $parentId ) ? $pm['pmid'] : $pm['parentpmid'],
				'msg_date'			=> $pm['dateline'],
				'msg_post'			=> $pm['message'],
				'msg_author_id'		=> $pm['fromuserid'],
				'msg_is_first_post'	=> 1
			) );

			$libraryClass->setLastKeyValue( $pm['pmid'] );
		}
	}
	
	/**
	 * Convert clubs
	 *
	 * @return	void
	 */
	public function convertClubs()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'groupid' );
		
		foreach( $this->fetch( 'socialgroup', 'groupid' ) AS $group )
		{
			switch( $group['type'] )
			{
				case 'public':
					$type = 'public';
					break;
				
				case 'moderated':
					$type = 'closed';
					break;
				
				case 'inviteonly':
					$type = 'private';
					break;
			}
			
			$icon = NULL;
			try
			{
				$icon = $this->db->select( '*', 'socialgroupicon', array( "groupid=?", $group['groupid'] ) )->first();
			}
			catch( \UnderflowException $e ) {}
			
			$info = array(
				'club_id'		=> $group['groupid'],
				'name'			=> $group['name'],
				'type'			=> $type,
				'created'		=> $group['dateline'],
				'members'		=> $group['members'],
				'owner'			=> $group['creatoruserid'],
				'profile_photo'	=> ( $icon ) ? "clubicon{$group['groupid']}.{$icon['extension']}" : NULL,
				'about'			=> $group['description'],
				'last_activity'	=> $group['lastupdate']
			);
			
			$iconpath = NULL;
			$icondata = NULL;
			if ( $icon )
			{
				if ( $this->app->_session['more_info']['convertClubs']['club_photo_location'] == 'database' )
				{
					$icondata = $icon['filedata'];
				}
				else
				{
					$iconpath = $this->app->_session['more_info']['convertClubs']['club_photo_location'] . "/socialgroupicon_{$icon['groupid']}_{$icon['dateline']}.gif";
				}
			}
			
			$libraryClass->convertClub( $info, $iconpath, $icondata );
			
			$libraryClass->setLastKeyValue( $group['groupid'] );
		}
	}
	
	/**
	 * Convert club members
	 *
	 * @return	void
	 */
	public function convertClubMembers()
	{
		$libraryClass = $this->getLibrary();
		foreach( $this->fetch( 'socialgroupmember', 'groupid' ) AS $member )
		{
			$type = NULL;
			try
			{
				$group = $this->db->select( '*', 'socialgroup', array( "groupid=?", $member['groupid'] ) )->first();
				
				if ( $member['userid'] == $group['creatoruserid'] )
				{
					$type = 'leader';
				}
			}
			catch( \UnderflowException $e ) {}
			
			if ( $type === NULL )
			{
				$type = ( $member['type'] === 'moderated' ) ? 'requested' : $member['type'];
			}
			
			$libraryClass->convertClubMember( array(
				'club_id'		=> $member['groupid'],
				'member_id'		=> $member['userid'],
				'joined'		=> $member['dateline'],
				'status'		=> $type,
			) );
		}
	}
	
	/* !vBulletin Specific Stuff */
	/**
	 * @brief	Silly Bit Options for Groups. Typically we would leave out app specific options (such as Forums here) however we need them for some general permissions, like uploading.
	 * @note	This is public simply because I do not want to do this ever again if it ever changes.
	 */
	public static $bitOptions = array(
		'forumpermissions' => array(
			'canview'					=> 1,
			'canviewothers'				=> 2,
			'cansearch'					=> 4,
			'canemail'					=> 8,
			'canpostnew'				=> 16,
			'canreplyown'				=> 32,
			'canreplyothers'			=> 64,
			'caneditpost'				=> 128,
			'candeletepost'				=> 256,
			'candeletethread'			=> 512,
			'canopenclose'				=> 1024,
			'canmove'					=> 2048,
			'cangetattachment'			=> 4096,
			'canpostattachment' 		=> 8192,
			'canpostpoll'				=> 16384,
			'canvote'					=> 32768,
			'canthreadrate'				=> 65536,
			'followforummoderation'		=> 131072,
			'canseedelnotice'			=> 262144,
			'canviewthreads'			=> 524288,
			'cantagown'					=> 1048576,
			'cantagothers'				=> 2097152,
			'candeletetagown'			=> 4194304,
			'canseethumbnails'			=> 8388608,
			'canattachmentcss'			=> 16777216,
			'bypassdoublepost'			=> 33554432,
			'canwrtmembers'				=> 67108864,
		),
		'pmpermissions' => array(
			'cantrackpm'				=> 1,
			'candenypmreceipts'			=> 2,
			'canignorequota'			=> 4,
		),
		'wolpermissions' => array(
			'canwhosonline'				=> 1,
			'canwhosonlineip'			=> 2,
			'canwhosonlinefull'			=> 4,
			'canwhosonlinebad'			=> 8,
			'canwhosonlinelocation'		=> 16,
		),
		'adminpermissions' => array(
			'ismoderator'				=> 1,
			'cancontrolpanel'			=> 2,
			'canadminsettings'			=> 4,
			'canadminstyles'			=> 8,
			'canadminlanguages'			=> 16,
			'canadminforums'			=> 32,
			'canadminthreads'			=> 64,
			'canadmincalendars'			=> 128,
			'canadminusers'				=> 256,
			'canadminpermissions'		=> 512,
			'canadminfaq'				=> 1024,
			'canadminimages'			=> 2048,
			'canadminbbcodes'			=> 4096,
			'canadmincron'				=> 8192,
			'canadminmaintain'			=> 16384,
			'canadminplugins'			=> 65536,
			'canadminnotices'			=> 131072,
			'canadminmodlog'			=> 262144,
			'cansitemap'				=> 524288,
			'canadminads'				=> 1048576,
			'canadmintags'				=> 2097152,
			'canadminblocks'			=> 4194304,
			'cansetdefaultprofile'		=> 8388608,
		),
		'genericpermissions' => array(
			'canviewmembers'			=> 1,
			'canmodifyprofile'			=> 2,
			'caninvisible'				=> 4,
			'canviewothersusernotes'	=> 8,
			'canmanageownusernotes'		=> 16,
			'canseehidden'				=> 32,
			'canbeusernoted'			=> 64,
			'canprofilepic'				=> 128,
			'canseeownrep'				=> 256,
			'cananimateprofilepic'		=> 134217728,
			'canuseavatar'				=> 512,
			'canusesignature'			=> 1024,
			'canusecustomtitle'			=> 2048,
			'canseeprofilepic'			=> 4096,
			'canviewownusernotes'		=> 8192,
			'canmanageothersusernotes'	=> 16384,
			'canpostownusernotes'		=> 32768,
			'canpostothersusernotes'	=> 65536,
			'caneditownusernotes'		=> 131072,
			'canseehiddencustomfields'	=> 262144,
			'canuserep'					=> 524288,
			'canhiderep'				=> 1048576,
			'cannegativerep'			=> 2097152,
			'cangiveinfraction'			=> 4194304,
			'cananimateavatar'			=> 67108864,
			'canseeinfraction'			=> 8388608,
			'cangivearbinfraction'		=> 536870912,
			'canreverseinfraction'		=> 16777216,
			'cansearchft_bool'			=> 33554432,
			'canemailmember'			=> 268435456,
			'cancreatetag'				=> 1073741824,
		),
		'genericpermissions2' => array(
			'canusefriends'				=> 1,
			'canprofilerep'				=> 2,
			'canwgomembers'				=> 4,
		),
		'genericoptions' => array(
			'showgroup'					=> 1,
			'showbirthday'				=> 2,
			'showmemberlist'			=> 4,
			'showeditedby'				=> 8,
			'allowmembergroups'			=> 16,
			'isnotbannedgroup'			=> 32,
			'requirehvcheck'			=> 64,
		),
		'signaturepermissions' => array(
			'canbbcode'					=> 131072,
			'canbbcodebasic'			=> 1,
			'canbbcodecolor'			=> 2,
			'canbbcodesize'				=> 4,
			'canbbcodefont'				=> 8,
			'canbbcodealign'			=> 16,
			'canbbcodelist'				=> 32,
			'canbbcodelink'				=> 64,
			'canbbcodecode'				=> 128,
			'canbbcodephp'				=> 256,
			'canbbcodehtml'				=> 512,
			'canbbcodequote'			=> 1024,
			'allowimg'					=> 2048,
			'allowvideo'				=> 262144,
			'allowsmilies'				=> 4096,
			'allowhtml'					=> 8192,
			'cansigpic'					=> 32768,
			'cananimatesigpic'			=> 65536,
		),
		'visitormessagepermissions' => array(
			'canmessageownprofile'		=> 1,
			'canmessageothersprofile'	=> 2,
			'caneditownmessages'		=> 4,
			'candeleteownmessages'		=> 8,
			'canmanageownprofile'		=> 32,
			'followforummoderation'		=> 16,
		),
		'useroptions' => array(
			'showsignatures'			=> 1,
			'showavatars'				=> 2,
			'showimages'				=> 4,
			'coppauser'					=> 8,
			'adminemail'				=> 16,
			'showvcard'					=> 32,
			'dstauto'					=> 64,
			'dstonoff'					=> 128,
			'showemail'					=> 256,
			'invisible'					=> 512,
			'showreputation'			=> 1024,
			'receivepm'					=> 2048,
			'emailonpm'					=> 4096,
			'hasaccessmask'				=> 8192,
			'vbasset_enable'			=> 16384,
			'postorder'					=> 32768,
			'receivepmbuddies'			=> 131072,
			'noactivationmails'			=> 262144,
			'pmboxwarning'				=> 524288,
			'showusercss'				=> 1048576,
			'receivefriendemailrequest'	=> 2097152,
			'vm_enable'					=> 8388608,
			'vm_contactonly'			=> 16777216,
			'pmdefaultsavecopy'			=> 33554432
		),
		'announcementoptions'		=> array(
			'allowbbcode'				=> 1,
			'allowhtml'					=> 2,
			'allowsmilies'				=> 4,
			'parseurl'					=> 8,
			'signature'					=> 16,
		),
		'cluboptions'				=> array(
			'owner_mod_queue'			=> 1,
			'join_to_view'				=> 2,
			'enable_group_messages'		=> 4,
			'enable_group_albums'		=> 8,
			'only_owner_discussions'	=> 16
		)
	);
	
	/**
	 * @brief	Fetched Settings Cache
	 */
	protected $settingsCache = array();
	
	/**
	 * Get Setting Value - useful for global settings that need to be translated to group or member settings
	 *
	 * @param	string	$key	The setting key
	 * @return	mixed
	 */
	protected function _setting( $key )
	{
		if ( isset( $this->settingsCache[$key] ) )
		{
			return $this->settingsCache[$key];
		}
		
		try
		{
			$setting = $this->db->select( 'value, defaultvalue', 'setting', array( "varname=?", $key ) )->first();
			
			if ( $setting['value'] )
			{
				$this->settingsCache[$key] = $setting['value'];
			}
			else
			{
				$this->settingsCache[$key] = $setting['defaultvalue'];
			}
		}
		catch( \UnderflowException $e )
		{
			/* If we failed to find it, we probably will fail again on later attempts */
			$this->settingsCache[$key] = NULL;
		}
		
		return $this->settingsCache[$key];
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		$url = \IPS\Request::i()->url();

		/* Attachment URLs are the same across VB 3.8 and VB 4 */
		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'attachment.php' ) !== FALSE )
		{
			$id = NULL;
			try
			{
				$id = (string) $this->app->getLink( \IPS\Request::i()->attachmentid, array( 'attachments', 'core_attachments' ) );
			}
			/* Try any child conversions */
			catch( \Exception $e )
			{
				foreach( $this->app->children() as $child )
				{
					try
					{
						$id = (string) $child->getLink( \IPS\Request::i()->attachmentid, array( 'attachments', 'core_attachments' ) );
						break;
					}
					catch( \Exception $e ) {}
				}
			}

			if( !$id )
			{
				return NULL;
			}

			return \IPS\Http\Url::external( \IPS\Settings::i()->base_url . 'applications/core/interface/file/attachment.php' )->setQueryString( 'id', $id );
		}
		/* Tag URLs are straightforward */
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'tags.php' ) !== FALSE AND isset( \IPS\Request::i()->tag ) )
		{
			return \IPS\Http\Url::internal( "app=core&module=search&controller=search&tags=" . \IPS\Request::i()->tag, 'front', 'search' );
		}
		/* Club Index */
		elseif( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'group.php' ) !== FALSE AND isset( \IPS\Request::i()->groupid ) )
		{
			try
			{
				return \IPS\Member\Club::loadAndCheckPerms( (string) $this->app->getLink( \IPS\Request::i()->groupid, array( 'core_clubs' ) ) )->url();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		else
		{
			/* If we can't access profiles, don't bother trying to redirect */
			if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
			{
				return NULL;
			}

			/* Profile URLs can be in one of 3 formats really...
			 * /member.php/1-name
			 * /members/1-name
			 * /member.php?u=1
			 * /member.php?1-name
			 */
			$path = $url->data[ \IPS\Http\Url::COMPONENT_PATH ];
			if( mb_strpos( $path, 'member.php' ) !== FALSE )
			{
				if( isset( \IPS\Request::i()->u ) )
				{
					$oldId	= \IPS\Request::i()->u;
				}
				elseif( preg_match( '#^(\d+)-[^/]+#i', $url->data[ \IPS\Http\Url::COMPONENT_QUERY ], $matches ) )
				{
					$oldId = $matches[1];
				}
				else
				{
					$queryStringPieces	= explode( '-', mb_substr( $path, mb_strpos( $path, 'member.php/' ) + mb_strlen( 'member.php/' ) ) );
					$oldId				= $queryStringPieces[0];
				}
			}
			elseif( preg_match( '#/members/([0-9]+)#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
			{
				$oldId	= (int) $matches[1];
			}

			if( isset( $oldId ) )
			{
				try
				{
					$data = (string) $this->app->getLink( $oldId, array( 'members', 'core_members' ) );
					return \IPS\Member::load( $data )->url();
				}
				catch( \Exception $e )
				{
					return NULL;
				}
			}
		}

		return NULL;
	}

	/**
	 * Process a login
	 *
	 * @param	\IPS\Member		$member			The member
	 * @param	string			$password		Password from form
	 * @return	bool
	 */
	public static function login( $member, $password )
	{
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( str_replace( '&#39;', "'", html_entity_decode( $password ) ) ) . $member->conv_password_extra ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Centralised PM conversation conversion
	 *
	 * @param	$libraryClass		\IPS\convert\Library
	 * @param 	$pm					array
	 * @return	mixed
	 */
	protected function _convertPm( \IPS\convert\Library $libraryClass, array $pm )
	{
		try
		{
			$replies = $this->db->select( 'COUNT(*)', 'pm', array( "parentpmid=? AND folderid!=?", $pm['pmid'], -1 ) )->first();
			$replies += 1;
		}
		catch( \UnderflowException $e )
		{
			$replies = 1;
		}

		$toUserArray = @\unserialize( $pm['touserarray'] );

		$topic = array(
			'mt_id'				=> $pm['pmid'],
			'mt_date'			=> $pm['dateline'],
			'mt_title'			=> $pm['title'],
			'mt_starter_id'		=> $pm['fromuserid'],
			'mt_start_time'		=> $pm['dateline'],
			'mt_last_post_time'	=> $pm['dateline'],
			'mt_to_count'		=> ( $toUserArray AND \is_array( $toUserArray ) ) ? \count( $toUserArray ) : 1,
			'mt_to_member_id'	=> $pm['userid'],
			'mt_replies'		=> $replies,
			'mt_first_msg_id'	=> $pm['pmid'],
		);

		$authors = array();
		$authors[ $pm['fromuserid'] ] = $pm['fromuserid'];

		try
		{
			foreach( $this->db->select( 'folderid, fromuserid', 'pm', array( "parentpmid=?", $pm['pmid'] ) )->join( 'pmtext', 'pm.pmtextid = pmtext.pmtextid' ) AS $post )
			{
				if ( $post['folderid'] != -1 )
				{
					$authors[ $post['fromuserid'] ] = $post['fromuserid'];
				}
			}
		}
		catch( \UnderflowException $e ) {}

		/* Use stored author ids */
		if( isset( $toUserArray['cc'] ) AND \is_array( $toUserArray['cc'] ) )
		{
			foreach( array_keys( $toUserArray['cc'] ) as $id )
			{
				$authors[ $id ] = $id;
			}
		}
		elseif( $toUserArray AND \is_array( $toUserArray ) AND \count( $toUserArray ) )
		{
			foreach( array_keys( $toUserArray ) as $id )
			{
				$authors[ $id ] = $id;
			}
		}

		$maps = array();

		foreach( $authors AS $mapAuthor )
		{
			$maps[ $mapAuthor ] = array(
				'map_user_id'			=> $mapAuthor,
				'map_topic_id'			=> $pm['pmid'],
				'map_user_active'		=> 1,
				'map_user_banned'		=> 0,
				'map_has_unread'		=> 0,
				'map_is_starter'		=> ( $mapAuthor == $pm['fromuserid'] ) ? 1 : 0,
			);
		}

		return $libraryClass->convertPrivateMessage( $topic, $maps );
	}

	protected $_avatars = array();

	/**
	 * Fetch Avatar from Avatar Gallery
	 *
	 * @param 	int 			$id				Avatar ID
	 * @return 	array|bool						Array of data/name or FALSE
	 */
	protected function _getFromAvatarGallery( int $id )
	{
		if( isset( $this->_avatars[ $id ] ) )
		{
			return $this->_avatars[ $id ];
		}

		try
		{
			$avatar = $this->db->select( 'avatarpath', 'avatar', array( 'avatarid=?', $id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			return $this->_avatars[ $id ] = FALSE;
		}

		/* It might be a remote image */
		if( \substr( $avatar, 0, 8 ) === 'https://' OR \substr( $avatar, 0, 7 ) === 'http://' )
		{
			try
			{
				$profilePhotoName = pathinfo( parse_url( $avatar, PHP_URL_PATH ), PATHINFO_BASENAME );
				$profilePhotoData = \IPS\Http\Url::external( $avatar )->request()->get();

				/* Check it's really an image */
				try
				{
					\IPS\Image::create( $profilePhotoData );
				}
				catch( \InvalidArgumentException $e )
				{
					return $this->_avatars[ $id ] = FALSE;
				}

				return $this->_avatars[ $id ] = array( 'data' => $profilePhotoData, 'name' => $profilePhotoName );
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				return $this->_avatars[ $id ] = FALSE;
			}
		}

		/* nope, it's a file */
		$dirName = pathinfo( $avatar, PATHINFO_DIRNAME );
		if( \substr( $this->app->_session['more_info']['convertMembers']['avatar_gallery_location'], -\strlen( $dirName ) ) == $dirName )
		{
			$profilePhotoName = pathinfo( $avatar, PATHINFO_BASENAME );
			$profilePhotoData = file_get_contents( rtrim( $this->app->_session['more_info']['convertMembers']['avatar_gallery_location'], '/' ) . '/' . $profilePhotoName );

			return $this->_avatars[ $id ] = array( 'data' => $profilePhotoData, 'name' => $profilePhotoName );
		}

		return $this->_avatars[ $id ] = FALSE;
	}
}
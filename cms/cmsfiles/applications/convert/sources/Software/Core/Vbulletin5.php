<?php

/**
 * @brief		Converter vBulletin 5.x Master Class
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
 * vBulletin 5 Core Converter
 */
class _Vbulletin5 extends \IPS\convert\Software
{
	/**
	 * @brief	is vB 5.5.3 or newer
	 */
	protected static $skipCustomProfilePic = NULL;

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
				/* Is this vB 5.5.3 or newer? */
				if ( static::$skipCustomProfilePic === NULL )
				{
					$version = $this->db->select( 'value', 'setting', array( "varname=?", 'templateversion' ) )->first();
					static::$skipCustomProfilePic = version_compare( $version, '5.5.3', '>=' );
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
		return "vBulletin (5.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "vbulletin5";
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
			'convertProfileFieldGroups'	=> array(
				'table'		=> 'profilefieldcategory',
				'where'		=> NULL
			),
			'convertProfileFields'		=> array(
				'table'		=> 'profilefield',
				'where'		=> NULL
			),
			'convertGroups'				=> array(
				'table'		=> 'usergroup',
				'where'		=> NULL
			),
			'convertMembers'				=> array(
				'table'		=> 'user',
				'where'		=> NULL
			),
			'convertMemberHistory'			=> array(
				'table'		=> 'userchangelog',
				'where'		=> NULL
			),
			'convertIgnoredUsers'			=> array(
				'table'		=> 'userlist',
				'where'		=> array( "type=?", 'ignore' )
			),
			'convertPrivateMessages'		=> array(
				'table'		=> 'pm',
				'where'		=> array( "parentpmid=?", 0 ),
			),
			'convertPrivateMessageReplies'	=> array(
				'table'		=> 'privatemessage',
				'where'		=> array( "msgtype=?", 'message' ),
			),
			'convertClubs'					=> array(
				'table'		=> 'node',
				'where'		=> 'clubs'
			),
			'convertClubMembers'			=> array(
				'table'		=> 'groupintopic',
				'where'		=> NULL
			)
		);
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
			case 'pm':
				try
				{
					$node = $this->db->select( 'nodeid', 'channel', array( "guid=?", static::$guids['private_messages'] ) )->first();
					
					return $this->db->select( 'COUNT(*)', 'privatemessage', array( "privatemessage.msgtype=? AND node.parentid=?", 'message', $node ) )->join( 'node', 'privatemessage.nodeid = node.nodeid' )->first();
				}
				catch( \UnderflowException $e )
				{
					return 0;
				}
				catch( \Exception $e )
				{
					throw new \IPS\convert\Exception( sprintf( \IPS\Member::loggedIn()->language()->get( 'could_not_count_rows' ), $table ) );
				}
				break;
				
			case 'node':
				if ( $where === 'clubs' )
				{
					return \count( $this->fetchClubs() );
				}
				else
				{
					return parent::countRows( $table, $where, $recache );
				}
				break;
			
			case 'groupintopic':
				$clubs = array();
				foreach( $this->fetchClubs() AS $club )
				{
					$clubs[] = $club['nodeid'];
				}
				
				$where = array( $this->db->in( 'nodeid', $clubs ) );
				return parent::countRows( $table, $where, $recache );
				break;

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
				
			default:
				return parent::countRows( $table, $where, $recache );
				break;
		}
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
		return 'convert_vb5_preconvert';
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
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_vb5_smilie_path'),
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
						\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['profilefieldid']}"] = "vBulletin Profile Field {$field['profilefieldid']}";
					}
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['profilefieldid']}_desc"] = \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );

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
				
				/* And decide what to do about these... */
				foreach( array( 'homepage', 'icq', 'aim', 'yahoo', 'msn', 'skype', 'google', 'user_title' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["field_{$field}"]		= \IPS\Member::loggedIn()->language()->addToStack( 'pseudo_field', FALSE, array( 'sprintf' => $field ) );
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

		/* Non-Content Rebuilds */
		\IPS\Task::queue( 'convert', 'RebuildProfilePhotos', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );
		
		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

		/* Clubs */
		\IPS\Task::queue( 'convert', 'RecountClubMembers', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* First Post Data */
		\IPS\Task::queue( 'convert', 'RebuildConversationFirstIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );

		/* Attachments */
		\IPS\Task::queue( 'core', 'RebuildAttachmentThumbnails', array( 'app' => $this->app->app_id ), 1, array( 'app' ) );
		
		return array( "f_search_index_rebuild", "f_clear_caches", "f_rebuild_pms", "f_signatures_rebuild" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		/* IMG2 BBcode - Needs to be ran before htmlspecialchars due to it containing JSON */
		$matches = array();
		preg_match_all( "/\[IMG2=JSON](.+?)\[\/IMG2\]/i", $post, $matches );

		if ( \count( $matches ) )
		{
			foreach( $matches[0] as $k => $v )
			{
				try
				{
					/* Make sure the JSON value exists */
					if( !isset( $matches[1][ $k ] ) )
					{
						throw new \DomainException;
					}

					$decoded = json_decode( $matches[1][ $k ], TRUE );

					/* Make sure the JSON is valid and contains src */
					if( !isset( $decoded['src'] ) OR empty( $decoded['src'] ) )
					{
						throw new \DomainException;
					}

					$post = str_replace( $v, "[img]{$decoded['src']}[/img]", $post );
				}
				catch( \DomainException $e )
				{
					continue;
				}
			}
		}

		/* Mentions */
		$matches = array();
		preg_match_all( '#\[user(=\"?(\d+)\"?)?\](.+?)\[\/user\]#i', $post, $matches ); // this purposefully matches more than the default vB mention.

		if ( \count( $matches ) )
		{
			/* Make sure we actually have mention data */
			if ( isset( $matches[1] ) AND \count( $matches[1] ) )
			{
				$mentions = array();
				foreach ( $matches[1] AS $k => $v )
				{
					if ( isset( $matches[3][ $k ] ) )
					{
						$mentions[ $matches[3][ $k ] ] = $v;
					}
				}

				$maps = iterator_to_array( \IPS\Db::i()->select( 'name, member_id', 'core_members', array( \IPS\Db::i()->in( 'name', array_keys( $mentions ) ) ) )->setKeyField( 'name' )->setValueField( 'member_id' ) );

				foreach ( $mentions AS $memberName => $memberId )
				{
					if ( !isset( $maps[ $memberName ] ) )
					{
						continue;
					}

					$memberNameQuoted = preg_quote( $memberName, '#' );
					$post = preg_replace( "#\[user(=\"?(\d+)\"?)?\]@?{$memberNameQuoted}\[\/user\]#i", "[mention={$maps[ $memberName ]}]{$memberName}[/mention]", $post );
				}
			}
		}

		// run everything through htmlspecialchars to prevent XSS
		$post = htmlspecialchars( $post, ENT_DISALLOWED | ENT_QUOTES, 'UTF-8', FALSE );

		/* Fix Quotes */
		$post = preg_replace( "#\[quote=([^\];]+?)\]#i", "[quote name='$1']", $post );
		$post = preg_replace( "#\[quote=([^\];]+?);\d+\]#i", "[quote name='$1']", $post );
		$post = preg_replace( "#\[quote=([^\];]+?);n\d+\]#i", "[quote name='$1']", $post );

		/* Remove video tags and allow our parser to handle the embeds it supports */
		$post = preg_replace( "#\[video=[a-z_]+;[a-z0-9_-]+\](.*?)\[\/video\]#i", '$1', $post );

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
			
			$libraryClass->convertEmoticon( $info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $this->app->_session['more_info']['convertEmoticons']['emoticon_path'] );
			
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
				$content = json_encode( @\unserialize( $field['data'] ) );
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
			
			/* Let's disect all of these bit options */
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
				'log_id'			=> $change['changeid'],
				'log_member'		=> $change['userid'],
				'log_by'			=> $change['adminid'],
				'log_type'			=> static::$changeLogTypes[ $change['fieldname'] ],
				'log_data'			=> array( 'old' => $change['oldvalue'], 'new' => $change['newvalue'] ),
				'log_date'			=> $change['change_time'],
				'log_ip_address'	=> long2ip( (int) $change['ipaddress'] )
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

		foreach( $this->fetch( 'user', 'user.userid', NULL, 'user.*, usertextfield.signature' )->join( 'usertextfield', 'user.userid = usertextfield.userid' ) AS $user )
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
			$warn_lastwarn = 0;
			try
			{
				$warn_lastwarn = $this->db->select( 'actiondateline', 'infraction', array( "infracteduserid=?", $user['userid'] ), "actiondateline DESC", 1 )->first();
			}
			catch( \UnderflowException $e ) {}
			
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
				$temp_ban = $this->db->select( 'liftdate', 'userban', array( "userid=?", $user['userid'] ) )->first();
				
				if ( $temp_ban == 0 )
				{
					$temp_ban = -1;
				}
			}
			catch( \UnderflowException $e ) {}
			
			/* Figure Out Password - they differ between 5.0 and 5.1 */
			if ( !isset( $user['password'] ) AND ( isset( $user['token'] ) AND isset( $user['scheme'] ) ) )
			{
				$pass	= $user['token'];
				$extra	= $user['scheme'];
			}
			else
			{
				$pass	= $user['password'];
				$extra	= $user['salt'];
			}
			
			/* Main Members Table */
			$info = array(
				'member_id'					=> $user['userid'],
				'member_group_id'			=> $user['usergroupid'],
				'mgroup_others'				=> $user['membergroupids'],
				'name'						=> html_entity_decode( $user['username'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
				'email'						=> $user['email'],
				'joined'					=> $user['joindate'],
				'ip_address'				=> $user['ipaddress'],
				'warn_level'				=> $user['ipoints'],
				'warn_lastwarn'				=> $warn_lastwarn,
				'bday_day'					=> $bday_day,
				'bday_month'				=> $bday_month,
				'bday_year'					=> $bday_year,
				'last_visit'				=> $user['lastvisit'],
				'last_activity'				=> $user['lastactivity'],
				'auto_track'				=> $auto_track,
				'temp_ban'					=> $temp_ban,
				'members_profile_views'		=> $user['profilevisits'],
				'conv_password'				=> $pass,
				'conv_password_extra'		=> $extra,
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
				'fb_uid'					=> $user['fbuserid'],
				'fb_token'					=> $user['fbaccesstoken']
			);

			/* If there is a validation record for this user, treat them as validating */
			try
			{
				$activation = $this->db->select( '*', 'useractivation', array( "userid=?", $user['userid'] ) )->first();

				$info['members_bitoptions']['validating'] = TRUE;
			}
			catch( \UnderflowException $e )
			{
				$activation = NULL;
			}
			
			/* Profile Fields */
			try
			{
				$profileFields = $this->db->select( '*', 'userfield', array( "userid=?", $user['userid'] ) )->first();
				
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
			foreach( array( 'homepage', 'icq', 'aim', 'yahoo', 'msn', 'skype', 'google', 'user_title' ) AS $pseudo )
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
					$fieldId = $this->app->getLink( $pseudo, 'core_pfields_data' );
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
			$secondTable	= !static::$skipCustomProfilePic ? 'customprofilepic' : FALSE; # vB 5.5.3 removes the customprofilepic table
			if ( $this->app->_session['more_info']['convertMembers']['photo_type'] == 'profile_photos' )
			{
				$firstTable		= !static::$skipCustomProfilePic ? 'customprofilepic' : FALSE; # vB 5.5.3 removes the customprofilepic table
				$secondTable	= 'customavatar';
			}
			if ( $this->app->_session['more_info']['convertMembers']['photo_location'] == 'database' )
			{
				try
				{
					if( $firstTable === FALSE )
					{
						throw new \UnderflowException();
					}

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
						if( $secondTable === FALSE )
						{
							throw new \UnderflowException();
						}

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
						if( $firstTable === FALSE )
						{
							throw new \UnderflowException();
						}

						$ext = $this->db->select( 'filename', $firstTable, array( "userid=?", $user['userid'] ) )->first();
						$ext = explode( '.', $ext );
						$ext = array_pop( $ext );
						
						if ( file_exists( rtrim( $filepath, '/' ) . '/' . $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.'. $ext ) )
						{
							$filename = $first . $user['userid'] . '_' . $user[$first . 'revision'] . '.'. $ext;
						}
						else
						{
							/* Throw an exception so we can try the other */
							throw new \UnderflowException;
						}
					}
					catch( \UnderflowException $e )
					{
						if( $secondTable === FALSE )
						{
							throw new \UnderflowException();
						}

						$ext = $this->db->select( 'filename', $secondTable, array( "userid=?", $user['userid'] ) )->first();
						$ext = explode( '.', $ext );
						$ext = array_pop( $ext );
						
						if ( file_exists( rtrim( $filepath, '/' ) . '/' . $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.'. $ext ) )
						{
							$filename = $second . $user['userid'] . '_' . $user[$second . 'revision'] . '.'. $ext;
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
					list( $filedata, $filename, $filepath ) = array( NULL, NULL, NULL );
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
			
			/* Any friends need converting to followers? */
			foreach( $this->db->select( '*', 'userlist', array( "type=? AND friend=? AND userid=?", 'buddy', 'yes', $user['userid'] ) ) AS $follower )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'core',
					'follow_area'			=> 'member',
					'follow_rel_id'			=> $follower['relationid'],
					'follow_rel_id_type'	=> 'core_members',
					'follow_member_id'		=> $follower['userid'],
				) );
			}
			
			/* And warn logs made on the profile - we'll do content specific later */
			foreach( $this->db->select( '*', 'infraction', array( "infracteduserid=? AND infractednodeid=?", $user['userid'], 0 ) ) AS $warn )
			{
				try
				{
					$node = $this->db->select( 'userid, description', 'node', array( "nodeid=?", $warn['nodeid'] ) )->first();
				}
				catch( \UnderflowException $e )
				{
					continue;
				}
				
				$warnId = $libraryClass->convertWarnLog( array(
					'wl_id'					=> $warn['nodeid'],
					'wl_member'				=> $warn['infracteduserid'],
					'wl_moderator'			=> $node['userid'],
					'wl_date'				=> $warn['actiondateline'],
					'wl_points'				=> $warn['points'],
					'wl_note_member'		=> $node['description'],
					'wl_note_mods'			=> $warn['customreason'],
				) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warn['nodeid'],
						'log_member'	=> $warn['infracteduserid'],
						'log_by'		=> $warn['actionuserid'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warn['actiondateline']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $user['userid'] );
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
	 * Convert PMs
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$node = $this->db->select( 'nodeid', 'channel', array( "guid=?", 'vbulletin-4ecbdf567f3da8.31769341' ) )->first();
		
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'privatemessage.nodeid' );
		
		foreach( $this->fetch( 'privatemessage', 'privatemessage.nodeid', array( "privatemessage.msgtype=? AND node.parentid=?", 'message', $node ) )->join( 'node', 'privatemessage.nodeid = node.nodeid' )->join( 'text', 'text.nodeid = node.nodeid' ) AS $pm )
		{
			try
			{
				$replies = $this->db->select( 'COUNT(*)', 'node', array( "parentid=?", $pm['nodeid'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$replies = 1;
			}
			
			$to = array( $pm['userid'] => $pm['userid'] );
			$toId = NULL;
			foreach( $this->db->select( 'userid', 'node', array( "parentid=?", $pm['nodeid'] ) ) AS $userid )
			{
				$to[$userid] = $userid;
				if ( \is_null( $toId ) AND $userid != $pm['userid'] )
				{
					$toId = $userid;
				}
			}
			
			$topic = array(
				'mt_id'				=> $pm['nodeid'],
				'mt_date'			=> $pm['publishdate'],
				'mt_title'			=> $pm['title'],
				'mt_starter_id'		=> $pm['userid'],
				'mt_start_time'		=> $pm['publishdate'],
				'mt_last_post_time'	=> $pm['lastcontent'],
				'mt_to_count'		=> \count( $to ),
				'mt_to_member_id'	=> $toId,
				'mt_replies'		=> $replies,
				'mt_first_msg_id'	=> $pm['nodeid'],
			);

			$maps = array();

			/* First Map */
			$maps[ $pm['userid'] ] = array(
					'map_user_id'			=> $pm['userid'],
					'map_topic_id'			=> $pm['nodeid'],
					'map_user_active'		=> 1,
					'map_user_banned'		=> 0,
					'map_has_unread'		=> 0,
					'map_is_starter'		=> 1,
				);
			
			/* All other maps */
			foreach( $this->db->select( '*', 'sentto', array( "nodeid=? AND userid!=?", $pm['nodeid'], $pm['userid'] ) ) AS $map )
			{
				$maps[ $map['userid'] ] = array(
					'map_user_id'			=> $map['userid'],
					'map_topic_id'			=> $pm['nodeid'],
					'map_user_active'		=> !$map['deleted'],
					'map_user_banned'		=> 0,
					'map_has_unread'		=> !$map['msgread'],
					'map_is_starter'		=> 0
				);
			}

			$libraryClass->convertPrivateMessage( $topic, $maps );
			
			$libraryClass->setLastKeyValue( $pm['nodeid'] );
		}
	}

	/**
	 * Convert PM replies
	 *
	 * @return	void
	 */
	public function convertPrivateMessageReplies()
	{
		$node = $this->db->select( 'nodeid', 'channel', array( "guid=?", 'vbulletin-4ecbdf567f3da8.31769341' ) )->first();

		$libraryClass = $this->getLibrary();

		$libraryClass::setKey( 'privatemessage.nodeid' );

		foreach( $this->fetch( 'privatemessage', 'privatemessage.nodeid', array( "privatemessage.msgtype=?", 'message' ), 'privatemessage.*, node.parentid, node.publishdate, node.oldid, node.userid, node.ipaddress, node.description, text.rawtext' )->join( 'node', 'privatemessage.nodeid = node.nodeid' )->join( 'text', 'text.nodeid = node.nodeid' ) AS $pm )
		{
			if ( $pm['oldid'] AND $this->db->checkForTable( 'pmtext' ) )
			{
				try
				{
					$pm['rawtext'] = $this->db->select( 'message', 'pmtext', array( "pmtextid=?", $pm['oldid'] ) )->first();
				}
				catch( \UnderflowException $e ) { }
			}

			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $pm['nodeid'],
				'msg_topic_id'		=> ( $pm['parentid'] == $node ) ? $pm['nodeid'] : $pm['parentid'],
				'msg_date'			=> $pm['publishdate'],
				'msg_post'			=> ( isset( $pm['rawtext'] ) ) ? $pm['rawtext'] : $pm['description'],
				'msg_author_id'		=> $pm['userid'],
				'msg_ip_address'	=> $pm['ipaddress'],
			) );

			$libraryClass->setLastKeyValue( $pm['nodeid'] );
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
		$libraryClass::setKey( 'nodeid' );
		
		try
		{
			$mainNodeId = $this->db->select( 'nodeid', 'channel', array( "guid=?", static::$guids['clubs'] ) )->first();
		}
		catch( \UnderflowException $e )
		{
			/* shrug */
			throw new \IPS\convert\Software\Exception;
		}
		
		$categories = array();
		foreach( $this->db->select( 'nodeid', 'node', array( "parentid=?", $mainNodeId ) ) AS $cat )
		{
			$categories[] = $cat;
		}
		
		$checktype = function( $nodeoptions ) {
			if ( $nodeoptions & 8 )
			{
				return 'public';
			}
			else if ( $nodeoptions & 16 )
			{
				return 'private';
			}
			else
			{
				return 'closed';
			}
		};
		
		foreach( $this->fetch( 'node', 'nodeid', array( $this->db->in( 'parentid', $categories ) ) ) AS $row )
		{
			try
			{
				$channel = $this->db->select( '*', 'channel', array( "nodeid=?", $row['nodeid'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$channel = array( 'filedataid' => 0 );
			}
			
			$filedata = NULL;
			if ( $channel['filedataid'] )
			{
				try
				{
					$filedata = $this->db->select( '*', 'filedata', array( "filedataid=?", $channel['filedataid'] ) )->first();
				}
				catch( \UnderflowException $e ) {}
			}
			
			$info = array(
				'club_id'		=> $row['nodeid'],
				'name'			=> $row['title'],
				'type'			=> $checktype( $row['nodeoptions'] ),
				'created'		=> $row['publishdate'],
				'members'		=> $this->db->select( 'COUNT(*)', 'groupintopic', array( "nodeid=?", $row['nodeid'] ) )->first(),
				'owner'			=> $row['userid'],
				'profile_photo'	=> ( $filedata ) ? "club{$row['nodeid']}.{$filedata['extension']}" : NULL,
				'featured'		=> $row['sticky'], # I don't know if you can actually pin social groups, but the columns there so... may as well
				'about'			=> $row['description'],
				'last_activity'	=> $row['lastupdate'],
			);
			
			$icondata = NULL;
			$iconpath = NULL;
			if ( $filedata )
			{
				if ( $this->app->_session['more_info']['convertClubs']['club_photo_location'] == 'database' )
				{
					$icondata = $filedata['filedata'];
				}
				else
				{
					$folder = preg_split( '//', $filedata['userid'], NULL, PREG_SPLIT_NO_EMPTY );
					$folder = implode( '/', $folder );
					$iconpath = $this->app->_session['more_info']['convertClubs']['club_photo_location'] . '/' . $folder . '/' . $filedata['filedataid'] . '.attach';
				}
			}
			
			$libraryClass->convertClub( $info, $iconpath, $icondata );
			$libraryClass->setLastKeyValue( $row['nodeid'] );
		}
	}
	
	/**
	 * Convert Club Members
	 *
	 * @return	void
	 */
	public function convertClubMembers()
	{
		$libraryClass = $this->getLibrary();
		$clubs = array();
		foreach( $this->fetchClubs() AS $club )
		{
			$clubs[ $club['nodeid'] ] = $club['userid'];
		}
		foreach( $this->fetch( 'groupintopic', 'nodeid', array( $this->db->in( 'nodeid', array_keys( $clubs ) ) ) ) AS $row )
		{
			$libraryClass->convertClubMember( array(
				'club_id'		=> $row['nodeid'],
				'member_id'		=> $row['userid'],
				'status'		=> ( $row['userid'] == $clubs[ $row['nodeid'] ] ) ? 'leader' : 'member',
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
	 * Helper method to retrieve forums from nodes
	 *
	 * @return void
	 */
	protected function fetchForums()
	{
		$forums = array();

		foreach( $this->db->select( '*', 'node', array( "node.nodeid<>2 AND closure.parent=? AND node.contenttypeid=?", $this->fetchType( 'Thread' ), $this->fetchType( 'Channel' ) ) )->join( 'closure', "closure.child = node.nodeid" ) AS $node )
		{
			$forums[$node['nodeid']] = $node;
		}

		return $forums;
	}
	
	/**
	 * @brief 	Channel GUID Mappins.
	 * @note	As far as I can tell, these are static and not unique per installation. Only the ones that are used are present.
	 */
	public static $guids = array(
		'clubs'				=> 'vbulletin-4ecbdf567f3a38.99555306',
		'private_messages'	=> 'vbulletin-4ecbdf567f3da8.31769341'
	);
	
	/**
	 * Fetch Clubs
	 *
	 * @return	array
	 */
	protected function fetchClubs()
	{
		$clubs = array();
	
		try
		{
			/* Get the main social group node ID from the channel GUID. */
			$mainNodeId = $this->db->select( 'nodeid', 'channel', array( "guid=?", static::$guids['clubs'] ) )->first();
			
			$categories = array();
			foreach( $this->db->select( 'nodeid', 'node', array( "parentid=?", $mainNodeId ) ) AS $cat )
			{
				$categories[] = $cat;
			}
			
			/* The actual clubs are all two levels deep from the main channel node, so we need to sub-query the categories. */
			foreach( $this->db->select( '*', 'node', array( $this->db->in( 'parentid', $categories ) ) ) AS $row )
			{
				$clubs[ $row['nodeid'] ] = $row;
			}
		}
		catch( \UnderflowException $e ) { /* Just in case something goofy happened and there is no main channel */ }
		
		return $clubs;
	}
	
	/**
	 * @brief	Types Cache
	 */
	protected $typesCache = array();

	/**
	 * Helper method to retrieve content type ids
	 *
	 * @param	string	$type	Content type
	 * @return	string
	 */
	protected function fetchType( $type )
	{
		if ( !empty( $this->typesCache ) )
		{
			return $this->typesCache[ $type ];
		}
		else
		{
			foreach( $this->db->select( '*', 'contenttype' ) AS $contenttype )
			{
				$this->typesCache[ $contenttype['class'] ] = $contenttype['contenttypeid'];
			}

			return $this->typesCache[ $type ];
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		/* If we can't access profiles, don't bother trying to redirect */
		if( !\IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) ) )
		{
			return NULL;
		}

		$url = \IPS\Request::i()->url();

		if( preg_match( '#/member/([0-9]+)\-#i', $url->data[ \IPS\Http\Url::COMPONENT_PATH ], $matches ) )
		{
			try
			{
				$data = (string) $this->app->getLink( $matches[1], array( 'members', 'core_members' ) );
				return \IPS\Member::load( $data )->url();
			}
			catch( \Exception $e )
			{
				return NULL;
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
		/* If no algorithm is defined, use the legacy handler */
		if ( \strpos( $member->misc, 'blowfish' ) === FALSE and \strpos( $member->misc, 'legacy' ) === FALSE )
		{
			return \IPS\convert\Software\Core\Vbulletin::login( $member, $password );
		}

		/* Which do we need to use? */
		$algo = explode( ':', $member->misc );

		switch( $algo[ 0 ] )
		{
			case 'blowfish':
				/* vBulletin uses PasswordHash, however they md5 once the password prior to checking */
				$md5_once_password = md5( $password );
				require_once \IPS\ROOT_PATH . "/applications/convert/sources/Login/PasswordHash.php";
				$ph = new \PasswordHash( $algo[ 1 ], FALSE );
				return $ph->CheckPassword( $md5_once_password, $member->conv_password );
			break;

			case 'legacy':
				/* Legacy Passwords are stored in a format of HASH SALT so we need to explode on the space. */
				$token = explode( ' ', $member->conv_password );
				$member->conv_password = $token[0];
				$member->conv_password_extra = $token[1];
				return \IPS\convert\Software\Core\Vbulletin::login( $member, $password );
			break;
			/**  vBulletin 5.6.2 was able to also use the Argon2id password hashing scheme. */
			case 'argon2id':
				if( defined('PASSWORD_ARGON2ID') ) {
					return password_verify( md5( $password ), $member->conv_password );
				}
				else
				{
					return FALSE;
				}

			break;
		}
	}
}
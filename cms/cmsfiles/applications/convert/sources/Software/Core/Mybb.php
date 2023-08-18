<?php

/**
 * @brief		Converter MyBB Class
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
 * MyBB Core Converter
 */
class _Mybb extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "MyBB 1.8.x";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "mybb";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertBanfilters'			=> array(
				'table'							=> 'banfilters',
				'where'							=> NULL
			),
			'convertEmoticons'				=> array(
				'table'							=> 'smilies',
				'where'							=> NULL,
			),
			'convertWarnActions'			=> array(
				'table'							=> 'warninglevels',
				'where'							=> NULL
			),
			'convertWarnReasons'			=> array(
				'table'							=> 'warningtypes',
				'where'							=> NULL
			),
			'convertProfileFields'		=> array(
				'table'							=> 'profilefields',
				'where'							=> NULL
			),
			'convertGroups'				=> array(
				'table'							=> 'usergroups',
				'where'							=> NULL
			),
			'convertMembers'				=> array(
				'table'							=> 'users', # note convert ignored users during this step, rather than individually
				'where'							=> NULL
			),
			'convertAnnouncements'			=> array(
				'table'							=> 'announcements',
				'where'							=> NULL
			),
			'convertPrivateMessages'		=> array(
				'table'							=> 'privatemessages',
				'where'							=> array( 'fromid<>uid' )
			),
			'convertPrivateMessageReplies'	=> array(
				'table'							=> 'privatemessages',
				'where'							=> array( 'fromid<>uid' ),
			),
			'convertProfanityFilters'		=> array(
				'table'							=> 'badwords',
				'where'							=> NULL
			),
			'convertQuestionAndAnswers'	=> array(
				'table'							=> 'questions',
				'where'							=> NULL
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
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertEmoticons',
			'convertProfileFields',
			'convertGroups',
			'convertMembers'
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
				\IPS\Member::loggedIn()->language()->words['emoticon_path'] = \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'MyBB' ) ) );
				$return['convertEmoticons']['emoticon_path'] = array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Text',
					'field_default'		=> NULL,
					'field_required'	=> TRUE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				$return['convertEmoticons']['keep_existing_emoticons']	= array(
					'field_class'		=> 'IPS\\Helpers\\Form\\Checkbox',
					'field_default'		=> TRUE,
					'field_required'	=> FALSE,
					'field_extra'		=> array(),
					'field_hint'		=> NULL,
				);
				break;
				
			case 'convertProfileFields':
				$return['convertProfileFields'] = array();
				
				$options = array();
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_pfields_data' ), 'IPS\core\ProfileFields\Field' ) AS $field )
				{
					$options[$field->_id] = $field->_title;
				}
				
				foreach( $this->db->select( '*', 'profilefields' ) AS $field )
				{
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['fid']}"]		= $field['name'];
					\IPS\Member::loggedIn()->language()->words["map_pfield_{$field['fid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_pfield_desc' );
					
					$return['convertProfileFields']["map_pfield_{$field['fid']}"] = array(
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
				$options['none'] = \IPS\Member::loggedIn()->language()->addToStack( 'none' );
				foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_groups' ), 'IPS\Member\Group' ) AS $group )
				{
					$options[$group->g_id] = $group->name;
				}
				
				foreach( $this->db->select( '*', 'usergroups' ) AS $group )
				{
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['gid']}"]			= $group['title'];
					\IPS\Member::loggedIn()->language()->words["map_group_{$group['gid']}_desc"]	= \IPS\Member::loggedIn()->language()->addToStack( 'map_group_desc' );
					
					$return['convertGroups']["map_group_{$group['gid']}"] = array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Select',
						'field_default'		=> NULL,
						'field_required'	=> FALSE,
						'field_extra'		=> array( 'options' => $options ),
						'field_hint'		=> NULL
					);
				}
				
				\IPS\Member::loggedIn()->language()->words['icon_path'] = \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'MyBB' ) ) );
				$return['convertGroups']['icon_path'] = array(
					'field_class'			=> "IPS\\Helpers\\Form\\Text",
					'field_default'			=> NULL,
					'field_required'		=> FALSE,
					'field_extra'			=> array(),
					'field_hint'			=> \IPS\Member::loggedIn()->language()->addToStack('convert_groupicon_hint'),
					'field_validation'		=> function( $value ) { if ( !empty( $value ) AND !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);
				break;
			
			case 'convertMembers':
				$return['convertMembers'] = array();

				/* Find out where the photos live */
				\IPS\Member::loggedIn()->language()->words['photo_location']		= \IPS\Member::loggedIn()->language()->addToStack( 'source_path', FALSE, array( 'sprintf' => array( 'MyBB' ) ) );
				\IPS\Member::loggedIn()->language()->words['photo_location_desc']	= '';
				$return['convertMembers']['photo_location'] = array(
					'field_class'			=> 'IPS\\Helpers\\Form\\Text',
					'field_default'			=> NULL,
					'field_required'		=> TRUE,
					'field_extra'			=> array(),
					'field_hint'			=> NULL,
					'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
				);

				foreach( array( 'website', 'icq', 'aim', 'yahoo', 'skype', 'google', 'usertitle' ) AS $field )
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
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_announcements', 'extension' => 'core_Announcement' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_message_posts', 'extension' => 'core_Messaging' ), 2, array( 'app', 'link', 'extension' ) );
		\IPS\Task::queue( 'convert', 'RebuildNonContent', array( 'app' => $this->app->app_id, 'link' => 'core_members', 'extension' => 'core_Signatures' ), 2, array( 'app', 'link', 'extension' ) );
		
		/* Content Counts */
		\IPS\Task::queue( 'core', 'RecountMemberContent', array( 'app' => $this->app->app_id ), 4, array( 'app' ) );

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
		/* Replace align tags with left/right/center/justify */
		$post = preg_replace( '/\[align=(justify|left|center|right)\](.+?)\[\/align\]/i', '[$1]$2[/$1]', $post );

		/* remove justify, since we don't support it */
		$post = str_replace( array( '[justify]', '[/justify]' ), '', $post );

		/* Quotes */
		$post = preg_replace("#\[quote=('|\")(.+?)('|\") pid='(\d+)' dateline='(\d+)'\]#i", "[quote name=\"$2\" timestamp=\"$5\"]", $post);

		return $post;
	}

	/**
	 * Convert announcements
	 *
	 * @return	void
	 */
	public function convertAnnouncements()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'aid' );
		
		foreach ( $this->fetch( 'announcements', 'aid' ) AS $row )
		{
			$libraryClass->convertAnnouncement( array(
				'announce_id'			=> $row['aid'],
				'announce_title'		=> $row['subject'],
				'announce_content'		=> $row['message'],
				'announce_member_id'	=> $row['uid'],
				'announce_start'		=> $row['startdate'],
				'announce_end'			=> $row['enddate'],
				'announce_active'		=> ( $row['enddate'] == 0 OR $row['enddate'] > time() ) ? 1 : 0,
			) );
			
			$libraryClass->setLastKeyValue( $row['aid'] );
		}
	}
	
	/**
	 * Convert ban filters
	 *
	 * @return	void
	 */
	public function convertBanfilters()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'fid' );
		
		foreach( $this->fetch( 'banfilters', 'fid' ) AS $row )
		{
			switch( $row['type'] )
			{
				case 1:
					$type = 'ip';
					break;
				
				case 2:
					$type = 'name';
					break;
				
				case 3:
					$type = 'email';
					break;
			}
			
			$libraryClass->convertBanfilter( array(
				'ban_id'			=> $row['fid'],
				'ban_type'			=> $type,
				'ban_content'		=> $row['filter'],
				'ban_date'			=> $row['dateline']
			) );
			
			$libraryClass->setLastKeyValue( $row['fid'] );
		}
	}
	
	/**
	 * Convert emoticons
	 *
	 * @return	void
	 */
	public function convertEmoticons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'sid' );
		
		foreach( $this->fetch( 'smilies', 'sid' ) AS $row )
		{
			$set = array(
				'set'		=> md5( 'Converted' ),
				'title'		=> 'Converted',
				'position'	=> 1,
			);
			
			$filepath	= explode( '/', $row['image'] );
			$filename	= array_pop( $filepath );
			$path		= rtrim( $this->app->_session['more_info']['convertEmoticons']['emoticon_path'], '/' ) . '/' . implode( '/', $filepath );
			
			$typed		= explode( "\n", $row['find'] );
			$typed		= array_shift( $typed );
			
			$info = array(
				'id'			=> $row['sid'],
				'typed'			=> $typed,
				'filename'		=> $filename,
				'clickable'		=> $row['showclickable'],
				'emo_position'	=> $row['disporder']
			);
			
			$libraryClass->convertEmoticon( $info, $set, $this->app->_session['more_info']['convertEmoticons']['keep_existing_emoticons'], $path );
			
			$libraryClass->setLastKeyValue( $row['sid'] );
		}
	}
	
	/**
	 * Convert warn actions
	 *
	 * @return	void
	 */
	public function convertWarnActions()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'lid' );
		
		foreach( $this->fetch( 'warninglevels', 'lid' ) AS $row )
		{
			$action = \unserialize( $row['action'] );
			
			$mq				= 0;
			$mq_unit		= 'h';
			$rpa			= 0;
			$rpa_unit		= 'h';
			$suspend		= 0;
			$suspend_unit	= 'h';
			switch( $action['type'] )
			{
				// Banned
				case 1:
					if ( $action['length'] >= 86400 )
					{
						$suspend_unit	= 'd';
						$suspend		= floor( $action['length'] / 86400 );
					}
					else
					{
						$suspend_unit	= 'h';
						$suspend		= floor( $action['length'] / 3600 );
					}
					break;
				
				// Restrict Posting
				case 2:
					if ( $action['length'] >= 86400 )
					{
						$rpa_unit		= 'd';
						$rpa			= floor( $action['length'] / 86400 );
					}
					else
					{
						$rpa_unit		= 'h';
						$rpa			= floor( $action['length'] / 3600 );
					}
					break;
				
				// Moderate Posting
				case 3:
					if ( $action['length'] >= 86400 )
					{
						$mq_unit		= 'd';
						$mq				= floor( $action['length'] / 86400 );
					}
					else
					{
						$mq_unit		= 'h';
						$mq				= floor( $action['length'] / 3600 );
					}
					break;
			}
			
			$libraryClass->convertWarnAction( array(
				'wa_id'				=> $row['lid'],
				'wa_points'			=> $row['percentage'],
				'wa_mq'				=> $mq,
				'wa_mq_unit'		=> $mq_unit,
				'wa_rpa'			=> $rpa,
				'wa_rpa_unit'		=> $rpa_unit,
				'wa_suspend'		=> $suspend,
				'wa_suspend_unit'	=> $suspend_unit,
			) );
			
			$libraryClass->setLastKeyValue( $row['lid'] );
		}
	}
	
	/**
	 * Convert warn reasons
	 *
	 * @return	void
	 */
	public function convertWarnReasons()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'tid' );
		
		foreach( $this->fetch( 'warningtypes', 'tid' ) AS $row )
		{
			$libraryClass->convertWarnReason( array(
				'wr_id'			=> $row['tid'],
				'wr_name'		=> $row['title'],
				'wr_points'		=> $row['points'],
				'wr_remove'		=> floor( $row['expirationtime'] / 3600 ),
			) );
			
			$libraryClass->setLastKeyValue( $row['tid'] );
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
		
		$libraryClass::setKey( 'fid' );
		
		foreach( $this->fetch( 'profilefields', 'fid' ) AS $row )
		{
			/* MyBB stores the type and the options in the same column. */
			$typeAndOptions	= explode( "\n", $row['type'] );
			$theirType		= array_shift( $typeAndOptions );
			$ourType		= $this->_fieldMap( $theirType );
			$options		= $typeAndOptions;
			
			$info = array(
				'pf_id'				=> $row['fid'],
				'pf_type'			=> $ourType,
				'pf_name'			=> $row['name'],
				'pf_desc'			=> $row['description'],
				'pf_content'		=> $options,
				'pf_not_null'		=> $row['required'],
				'pf_member_hide'	=> $row['profile'] ? 'all' : 'hide',
				'pf_max_input'		=> $row['maxlength'],
				'pf_position'		=> $row['disporder'],
				'pf_show_on_reg'	=> $row['registration'],
				'pf_input_format'	=> '/' . $row['regex'] . '/i',
				'pf_member_edit'	=> ( $row['editableby'] == -1 ) ? 1 : 0, # MyBB allows you to define which groups can edit, so if this value is anything other than -1, assume it's admin only
				'pf_multiple'		=> ( $theirType == 'multiselect' OR $theirType == 'checkbox' ) ? 1 : 0
			);
			
			$merge = $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['fid']}"] != 'none' ? $this->app->_session['more_info']['convertProfileFields']["map_pfield_{$row['fid']}"] : NULL;
			
			$libraryClass->convertProfileField( $info, $merge );
			
			$libraryClass->setLastKeyValue( $row['fid'] );
		}
	}
	
	/**
	 * Maps a MyBB Profile Field type to IPS
	 *
	 * @param	string	$type	MyBB Type
	 * @return	string	IPS Type
	 */
	protected function _fieldMap( $type )
	{
		switch( $type )
		{
			case 'text':
				return 'Text';
				break;
			
			case 'textarea':
				return "TextArea";
				break;
			
			case 'select':
			case 'multiselect':
				return 'Select';
				break;
			
			case 'checkbox':
				return 'CheckboxSet';
				break;
			
			case 'radio':
				return 'Radio';
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
		
		$libraryClass::setKey( 'gid' );
		
		foreach( $this->fetch( 'usergroups', 'gid' ) AS $row )
		{
			/* Are we trying to fetch icons? */
			$icon_path = $this->app->_session['more_info']['convertGroups']['icon_path'];
			
			/* Work out promotions */
			$promotion_unit	= 0;
			$promotion_data	= NULL;
			try
			{
				/* We only support one type of promotion, so just fetch the first one we can find */
				$promotion = $this->db->select( '*', 'promotions', array( "originalusergroup=? AND usergrouptype=? AND ( requirements=? OR requirements=? )", $row['gid'], 'primary', 'postcount', 'timeregistered' ) )->first();
				
				switch( $promotion['requirements'] )
				{
					case 'postcount':
						if ( !\in_array( $promotion['posttype'], array( '>', '>=', '=' ) ) )
						{
							/* Bubble up */
							throw new \UnderflowException;
						}
						$promotion_unit = 0;
						$promotion_data = array( $promotion['newusergroup'], $promotion['posts'] );
						break;
					
					case 'timeregistered':
						if ( !\in_array( $promotion['registeredtype'], array( '>', '>=', '=' ) ) )
						{
							throw new \UnderflowException;
						}
						$promotion_unit = 0;
						$promotion_data = array( $promotion['newusergroup'], $promotion['registered'] );
						break;
				}
			}
			catch( \UnderflowException $e ) {}
			
			/* Username Styles */
			$style = explode( '{username}', $row['namestyle'] );
			$prefix = isset( $style[0] ) ? $style[0] : '';
			$suffix = isset( $style[1] ) ? $style[1] : '';
			
			$info = array(
				'g_id'					=> $row['gid'],
				'g_name'				=> $row['title'],
				'g_icon'				=> ( $icon_path AND $row['image'] ) ? $row['image'] : NULL,
				'g_promotion'			=> $promotion_data ?: NULL,
				'g_bitoptions'			=> array(
					'gbw_promote_unit_type'	=> $promotion_unit,
				),
				'g_view_board'			=> $row['canview'],
				'g_mem_info'			=> $row['canviewprofiles'],
				'g_use_search'			=> $row['cansearch'],
				'g_edit_profile'		=> $row['canusercp'],
				'g_edit_posts'			=> $row['caneditposts'],
				'g_use_pm'				=> $row['cansendpms'],
				'g_pm_flood_mins'		=> $row['emailfloodtime'],
				'g_post_polls'			=> $row['canpostpolls'],
				'g_vote_polls'			=> $row['canvotepolls'],
				'g_delete_own_posts'	=> $row['candeleteposts'],
				'g_is_supmod'			=> $row['issupermod'],
				'g_access_cp'			=> $row['cancp'],
				'g_access_offline'		=> $row['canviewboardclosed'],
				'g_max_messages'		=> $row['pmquota'],
				'g_attach_max'			=> ( $row['attachquota'] == 0 ) ? -1 : $row['attachquota'],
				'prefix'				=> $prefix,
				'suffix'				=> $suffix,
				'g_max_mass_pm'			=> $row['maxpmrecipients'],
				'g_rep_max_positive'	=> $row['maxreputationsday'],
				'g_rep_max_negative'	=> $row['maxreputationsday'],
			);
			
			$merge = $this->app->_session['more_info']['convertGroups']["map_group_{$row['gid']}"] != 'none' ? $this->app->_session['more_info']['convertGroups']["map_group_{$row['gid']}"] : NULL;
			
			$libraryClass->convertGroup( $info, $merge, ( $icon_path ) ? rtrim( $icon_path, '/' ) . '/' . $row['image'] : NULL );
			
			$libraryClass->setLastKeyValue( $row['gid'] );
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
	 * Convert members
	 *
	 * @return	void
	 */
	public function convertMembers()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'uid' );
		
		foreach( $this->fetch( 'users', 'uid' ) AS $row )
		{
			/* Last warning */
			$last_warn = 0;
			try
			{
				$last_warn = $this->db->select( 'dateline', 'warnings', array( "uid=?", $row['uid'] ), "dateline DESC" )->first();
			}
			catch( \UnderflowException $e ) {}
			
			/* Restrict posting */
			$restrict_post = 0;
			if ( $row['suspendposting'] )
			{
				if ( $row['suspensiontime'] > 0 )
				{
					$restrict_post = $row['suspensiontime'];
				}
				else
				{
					$restrict_post = -1;
				}
			}
			
			/* Birthday */
			$bday_day = $bday_month = $bday_year = NULL;
			$birthday = explode( '-', $row['birthday'] );
			
			if ( isset( $birthday[0] ) )
			{
				$bday_day = $birthday[0];
			}
			
			if ( isset( $birthday[1] ) )
			{
				$bday_month = $birthday[1];
			}
			
			if ( isset( $birthday[2] ) )
			{
				$bday_year = $birthday[2];
			}
			
			/* Moderate Posts */
			$mod_posts = 0;
			if ( $row['moderateposts'] )
			{
				if ( $row['moderationtime'] > 0 )
				{
					$mod_posts = $row['moderationtime'];
				}
				else
				{
					$mod_posts = -1;
				}
			}
			
			/* Subscription */
			switch( $row['subscriptionmethod'] )
			{
				case 0:
					$auto_track = array( 'content' => 0, 'comments' => 0, 'method' => 'none' );
					break;
				
				case 1:
					$auto_track = array( 'content' => 1, 'comments' => 1, 'method' => 'none' );
					break;
				
				case 2:
				case 3:
					$auto_track = array( 'content' => 1, 'comments' => 1, 'method' => 'immediate' );
					break;
			}
			
			/* Banned */
			$temp_ban = 0;
			try
			{
				$temp_ban = $this->db->select( 'lifted', 'banned', array( "uid=?", $row['uid'] ) )->first();
				
				if ( $temp_ban == 0 )
				{
					$temp_ban = -1;
				}
			}
			catch( \UnderflowException $e ) {}
			
			$info = array(
				'member_id'				=> $row['uid'],
				'name'					=> html_entity_decode( $row['username'], \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ),
				'email'					=> $row['email'],
				'password'				=> $row['password'],
				'password_extra'		=> $row['salt'],
				'member_group_id'		=> $row['usergroup'],
				'joined'				=> $row['regdate'],
				'ip_address'			=> $row['regip'],
				'warn_level'			=> $row['warningpoints'],
				'warn_lastwarn'			=> $last_warn,
				'restrict_post'			=> $restrict_post,
				'bday_day'				=> str_pad( $bday_day, 2, '0', STR_PAD_LEFT ),
				'bday_month'			=> str_pad( $bday_month, 2, '0', STR_PAD_LEFT ),
				'bday_year'				=> $bday_year ? \intval( $bday_year ) : NULL,
				'msg_count_new'			=> $row['unreadpms'],
				'msg_count_total'		=> $row['totalpms'],
				'msg_show_notification'	=> $row['pmnotify'],
				'last_visit'			=> $row['lastvisit'],
				'last_activity'			=> $row['lastactive'],
				'mod_posts'				=> $mod_posts,
				'auto_track'			=> $auto_track,
				'temp_ban'				=> $temp_ban,
				'mgroup_others'			=> $row['additionalgroups'],
				'members_bitoptions'	=> array(
					'view_sigs'				=> $row['showsigs'],
				),
				'members_bitoptions2'	=> array(
					'show_pm_popup'			=> $row['pmnotify'],
				),
				'pp_reputation_points'	=> $row['reputation'],
				'signature'				=> $row['signature'],
				'timezone'				=> $row['timezone'],
				'allow_admin_mails'		=> $row['allownotices'],
				'members_disable_pm'	=> ( $row['receivepms'] ) ? 0 : 1,
				'member_posts'			=> $row['postnum'],
				'member_last_post'		=> $row['lastpost'],
			);
			
			$pfields = array();
			try
			{
				foreach ( $this->db->select( '*', 'userfields', array( "ufid=?", $row['uid'] ) )->first() AS $key => $field )
				{
					if ( $key == 'ufid' )
					{
						continue;
					}

					$key = str_replace( 'fid', '', $key );
					$pfields[ $key ] = $field;
				}
			}
			catch( \UnderflowException $e ) {}
			
			foreach( array( 'website', 'icq', 'aim', 'yahoo', 'skype', 'google', 'usertitle' ) AS $pseudo )
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
						'pf_member_hide'	=> 0,
						'pf_max_input'		=> 255,
						'pf_member_edit'	=> 1,
						'pf_show_on_reg'	=> 0,
						'pf_admin_only'		=> 0,
					) );
				}
				
				$pfields[$pseudo] = $row[$pseudo];
			}
			
			/* Photo */
			$filename = NULL;
			$filepath = NULL;
			if( $row['avatartype'] == 'upload' )
			{
				$info['pp_photo_type'] = 'custom';

				$avatar		= trim( $row['avatar'], '.' );
				$filepath	= explode( '/', $avatar );
				$filename	= parse_url( array_pop( $filepath ), PHP_URL_PATH );
				$filepath	= rtrim( $this->app->_session['more_info']['convertMembers']['photo_location'], '/' ) . implode( '/', $filepath );
			}
			
			$libraryClass->convertMember( $info, $pfields, $filename, $filepath );
			
			/* Followers */
			foreach( explode( ',', $row['buddylist'] ) AS $buddy )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'core',
					'follow_area'			=> 'member',
					'follow_rel_id'			=> $buddy,
					'follow_rel_id_type'	=> 'core_members',
					'follow_member_id'		=> $row['uid'],
				) );
			}
			
			/* Ignore List */
			foreach( explode( ',', $row['ignorelist'] ) AS $ignore )
			{
				$libraryClass->convertIgnoredUser( array(
					'ignore_id'			=> $row['uid'] . '-' . $ignore,
					'ignore_owner_id'	=> $row['uid'],
					'ignore_ignore_id'	=> $ignore
				) );
			}
			
			/* Warnings */
			foreach( $this->db->select( '*', 'warnings', array( "uid=? AND pid=?", $row['uid'], 0 ) ) AS $warning )
			{
				$warnId = $libraryClass->convertWarnLog( array(
					'wl_id'				=> $warning['wid'],
					'wl_member'			=> $warning['uid'],
					'wl_moderator'		=> $warning['issuedby'],
					'wl_date'			=> $warning['dateline'],
					'wl_reason'			=> $warning['tid'],
					'wl_points'			=> $warning['points'],
					'wl_note_mods'		=> $warning['notes'],
					'wl_expire_date'	=> $warning['expires'],
				) );

				/* Add a member history record for this member */
				$libraryClass->convertMemberHistory( array(
						'log_id'		=> 'w' . $warning['wid'],
						'log_member'	=> $warning['uid'],
						'log_by'		=> $warning['issuedby'],
						'log_type'		=> 'warning',
						'log_data'		=> array( 'wid' => $warnId ),
						'log_date'		=> $warning['dateline']
					)
				);
			}
			
			$libraryClass->setLastKeyValue( $row['uid'] );
		}
	}
	
	/**
	 * Convert PMs
	 *
	 * @return	void
	 */
	public function convertPrivateMessages()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'pmid' );
		
		foreach( $this->fetch( 'privatemessages', 'pmid', array( 'fromid<>uid' ) ) AS $row )
		{
			/* In MyBB, replies to messages are stored as full conversations, and there is a row for both the sender and the recipient. We'll bring over every reply for archival purposes, but if the fromid == uid, skip because otherwise we end up with duplicates. */
			
			$topic = array(
				'mt_id'				=> $row['pmid'],
				'mt_date'			=> $row['dateline'],
				'mt_title'			=> $row['subject'],
				'mt_starter_id'		=> $row['fromid'],
				'mt_start_time'		=> $row['dateline'],
				'mt_last_post_time'	=> $row['dateline'],
				'mt_to_count'		=> 1,
			);
			
			$maps = array();
			$maps[$row['fromid']] = array(
				'map_user_id'		=> $row['fromid'],
				'map_read_time'		=> $row['readtime'],
				'map_is_starter'	=> true
			);

			$maps[$row['toid']] = array(
				'map_user_id'		=> $row['toid'],
				'map_read_time'		=> $row['readtime'],
			);
			
			$libraryClass->convertPrivateMessage( $topic, $maps );
			
			$libraryClass->setLastKeyValue( $row['pmid'] );
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
		
		$libraryClass::setKey( 'pmid' );
		
		foreach( $this->fetch( 'privatemessages', 'pmid', array( 'fromid<>uid' ) ) AS $row )
		{
			$libraryClass->convertPrivateMessageReply( array(
				'msg_id'			=> $row['pmid'],
				'msg_topic_id'		=> $row['pmid'],
				'msg_date'			=> $row['dateline'],
				'msg_post'			=> $row['message'],
				'msg_author_id'		=> $row['fromid'],
				'msg_ip_address'	=> $row['ipaddress'],
			) );
			
			$libraryClass->setLastKeyValue( $row['pmid'] );
		}
	}

	/**
	 * Convert profanity filters
	 *
	 * @return	void
	 */
	public function convertProfanityFilters()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'bid' );
		
		foreach( $this->fetch( 'badwords', 'bid' ) AS $row )
		{
			$libraryClass->convertProfanityFilter( array(
				'wid'		=> $row['bid'],
				'type'		=> $row['badword'],
				'swop'		=> $row['replacement'],
			) );
			
			$libraryClass->setLastKeyValue( $row['bid'] );
		}
	}
	
	/**
	 * Convert questions and answers
	 *
	 * @return	void
	 */
	public function convertQuestionAndAnswers()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'qid' );
		
		foreach( $this->fetch( 'questions', 'qid' ) AS $row )
		{
			$answers = explode( "\n", $row['answer'] );
			
			$libraryClass->convertQuestionAndAnswer( array(
				'qa_id'			=> $row['qid'],
				'qa_question'	=> $row['question'],
			), $answers );
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

		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'member.php' ) !== FALSE )
		{
			try
			{
				$data = (string) $this->app->getLink( \IPS\Request::i()-uid, array( 'members', 'core_members' ) );
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
		if ( \IPS\Login::compareHashes( $member->conv_password, md5( md5( $member->misc ) . md5( $password ) ) ) )
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
}
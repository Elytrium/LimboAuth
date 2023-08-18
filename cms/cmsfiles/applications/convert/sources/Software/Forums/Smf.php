<?php

/**
 * @brief		Converter SMF Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @package		Invision Community
 * @subpackage	convert
 * @since		21 Jan 2015
 * @todo		SMF supports karma which we should be able to convert to reactions (reputation), but we need a sample database that used karma
 */

namespace IPS\convert\Software\Forums;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * SMF Forums Converter
 */
class _Smf extends \IPS\convert\Software
{
	/**
	 * Software Name
	 *
	 * @return	string
	 */
	public static function softwareName()
	{
		/* Child classes must override this method */
		return "Simple Machines Forum (2.0.x)";
	}
	
	/**
	 * Software Key
	 *
	 * @return	string
	 */
	public static function softwareKey()
	{
		/* Child classes must override this method */
		return "smf";
	}
	
	/**
	 * Content we can convert from this software. 
	 *
	 * @return	array
	 */
	public static function canConvert()
	{
		return array(
			'convertForumsBoards'		=> array(
				'table'						=> 'boards',
				'where'						=> NULL
			),
			'convertForumsForums'		=> array(
				'table'						=> 'forums',
				'where'						=> NULL,
				'extra_steps'				=> array( 'convertForumsBoards' ),
			),
			'convertForumsTopics'		=> array(
				'table'						=> 'topics',
				'where'						=> NULL
			),
			'convertForumsPosts'		=> array(
				'table'						=> 'messages',
				'where'						=> NULL
			),
			'convertAttachments'		=> array(
				'table'						=> 'attachments',
				'where'						=> array( "id_msg<>? AND attachment_type < ?", 0, 3 )
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
		$rows['convertForumsBoards'] = array(
			'step_title'	=> 'convert_forums_forums',
			'step_method'	=> 'convertForumsBoards',
			'ips_rows'		=> \IPS\Db::i()->select( 'COUNT(*)', 'forums_forums' ),
			'source_rows'	=> array( 'table' => static::canConvert()['convertForumsBoards']['table'], 'where' => static::canConvert()['convertForumsBoards']['where'] ),
			'per_cycle'		=> 200,
			'dependencies'	=> array( 'convertForumsForums' ),
			'link_type'		=> 'forums_forums',
		);

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
			case 'forums':
				try
				{
					return $this->db->select( 'COUNT(*)', 'categories' )->first() + $this->db->select( 'COUNT(*)', 'boards' )->first();
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
	 * Requires Parent
	 *
	 * @return	boolean
	 */
	public static function requiresParent()
	{
		return TRUE;
	}
	
	/**
	 * Possible Parent Conversions
	 *
	 * @return	array
	 */
	public static function parents()
	{
		return array( 'core' => array( 'smf' ) );
	}

	/**
	 * List of conversion methods that require additional information
	 *
	 * @return	array
	 */
	public static function checkConf()
	{
		return array(
			'convertAttachments'
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
			case 'convertAttachments':
				$return['convertAttachments'] = array(
					'attach_location'	=> array(
						'field_class'		=> 'IPS\\Helpers\\Form\\Text',
						'field_default'		=> NULL,
						'field_required'	=> TRUE,
						'field_extra'		=> array(),
						'field_hint'		=> \IPS\Member::loggedIn()->language()->addToStack('convert_smf_attach_path'),
						'field_validation'	=> function( $value ) { if ( !@is_dir( $value ) ) { throw new \DomainException( 'path_invalid' ); } },
					),
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
		/* Content Rebuilds */
		\IPS\Task::queue( 'core', 'RebuildContainerCounts', array( 'class' => 'IPS\forums\Forum', 'count' => 0 ), 4, array( 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildContent', array( 'app' => $this->app->app_id, 'link' => 'forums_posts', 'class' => 'IPS\forums\Topic\Post' ), 2, array( 'app', 'link', 'class' ) );
		\IPS\Task::queue( 'core', 'RebuildItemCounts', array( 'class' => 'IPS\forums\Topic' ), 3, array( 'class' ) );
		\IPS\Task::queue( 'convert', 'RebuildFirstPostIds', array( 'app' => $this->app->app_id ), 2, array( 'app' ) );
		\IPS\Task::queue( 'convert', 'DeleteEmptyTopics', array( 'app' => $this->app->app_id ), 5, array( 'app' ) );

		return array( "f_forum_last_post_data", "f_rebuild_posts", "f_recounting_forums", "f_recounting_topics" );
	}
	
	/**
	 * Fix post data
	 *
	 * @param 	string		$post	Raw post data
	 * @return 	string		Parsed post data
	 */
	public static function fixPostData( $post )
	{
		return \IPS\convert\Software\Core\Smf::fixPostData( $post );
	}
	
	/**
	 * Convert forums
	 *
	 * @return	void
	 */
	public function convertForumsForums()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_cat' );
		
		foreach( $this->fetch( 'categories', 'id_cat', NULL, "id_cat, CONCAT( 'c', id_cat ) AS id, name, cat_order AS position, 0 AS sub_can_post, -1 AS parent_id" ) AS $row )
		{
			$current = $row['id_cat'];
			unset( $row['id_cat'] );
			$libraryClass->convertForumsForum( $row );
			
			$libraryClass->setLastKeyValue( $current );
		}
	}
	
	/**
	 * Convert categories
	 *
	 * @return	void
	 */
	public function convertForumsBoards()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_board' );
		
		foreach( $this->fetch( 'boards', 'id_board' ) AS $row )
		{
			$last_post_time		= 0;
			$last_poster		= 0;
			$last_title			= NULL;
			$last_poster_name	= NULL;
			
			try
			{
				$last_post = $this->db->select( '*', 'messages', array( "id_msg=?", $row['id_last_msg'] ) )->first();
				
				$last_post_time		= $last_post['poster_time'];
				$last_poster		= $last_post['id_member'];
				$last_poster_name	= $last_post['poster_name'];
				$last_title			= $last_post['subject']; # Yes, I know this is technically wrong, but it's better than just not showing anything
			}
			catch( \UnderflowException $e ) {}
			
			$info = array(
				'id'				=> $row['id_board'],
				'name'				=> $row['name'],
				'description'		=> $row['description'],
				'topics'			=> $row['num_topics'],
				'posts'				=> $row['num_posts'],
				'last_post'			=> $last_post_time,
				'last_poster_id'	=> $last_poster,
				'last_poster_name'	=> $last_poster_name,
				'parent_id'			=> ( $row['id_parent'] <> 0 ) ? $row['id_parent'] : 'c' . $row['id_cat'],
				'position'			=> $row['board_order'],
				'last_title'		=> $last_title,
				'redirect_url'		=> $row['redirect'],
				'queued_topics'		=> $row['unapproved_topics'],
				'queued_posts'		=> $row['unapproved_posts'],
				'sub_can_post'		=> 1
			);
			
			$libraryClass->convertForumsForum( $info );
			
			$libraryClass->setLastKeyValue( $row['id_board'] );
		}
	}
	
	/**
	 * Convert topics
	 *
	 * @return	void
	 */
	public function convertForumsTopics()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_topic' );
		
		foreach( $this->fetch( 'topics', 'id_topic' ) AS $row )
		{
			try
			{
				$firstPost = $this->db->select( '*', 'messages', array( "id_msg=?", $row['id_first_msg'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['id_topic'] );
				continue;
			}
			
			$lastPost = NULL;
			try
			{
				$lastPost = $this->db->select( '*', 'messages', array( "id_msg=?", $row['id_last_msg'] ) )->first();
			}
			catch( \UnderflowException $e ) {}
			
			$poll = NULL;
			if ( $row['id_poll'] )
			{
				try
				{
					$poll_data = $this->db->select( '*', 'polls', array( "id_poll=?", $row['id_poll'] ) )->first();
					
					$options = $this->db->select( '*', 'poll_choices', array( "id_poll=?", $poll_data['id_poll'] ) );
					
					$choices = array();
					$votes = 0;
					foreach( $options AS $option )
					{
						$choices[ $option['id_choice'] ] = $option['label'];
						$votes += $option['votes'];
					}
					
					$member_votes = array();
					foreach( $this->db->select( '*', 'log_polls', array( "id_poll=?", $poll_data['id_poll'] ) ) AS $vote )
					{
						$member_votes[$vote['id_member']] = array(
							'member_id'			=> $vote['id_member'],
							'member_choices'	=> array( $vote['id_choice'] )
						);
					}
					
					$poll = array(
						'poll_data' => array(
							'pid'			=> $poll_data['id_poll'],
							'choices'			=> array( 1 => array(
								'question'			=> $poll_data['question'],
								'multi'				=> 0,
								'choice'			=> $choices,
								'votes'				=> $votes,
							) ),
							'poll_question'	=> $poll_data['question'],
							'start_date'	=> $firstPost['poster_time'],
							'starter_id'	=> $poll_data['id_member'],
						),
						'vote_data'	=> $member_votes
					);
				}
				catch( \UnderflowException $e ) {}
			}
			
			$info = array(
				'tid'				=> $row['id_topic'],
				'title'				=> $firstPost['subject'],
				'forum_id'			=> $row['id_board'],
				'state'				=> ( $row['locked'] ) ? 'closed' : 'open',
				'posts'				=> $row['num_replies'],
				'starter_id'		=> $row['id_member_started'],
				'start_date'		=> $firstPost['poster_time'],
				'last_poster_id'	=> $row['id_member_updated'],
				'last_post'			=> ( $lastPost ) ? $lastPost['poster_time'] : 0,
				'starter_name'		=> $firstPost['poster_name'],
				'last_poster_name'	=> $lastPost['poster_name'],
				'poll_state'		=> $poll,
				'views'				=> $row['num_views'],
				'approved'			=> $row['approved'],
				'pinned'			=> $row['is_sticky'],
			);
			
			$libraryClass->convertForumsTopic( $info );
			
			/* Follows */
			foreach( $this->db->select( '*', 'log_notify', array( "id_topic=?", $row['id_topic'] ) ) AS $follow )
			{
				$libraryClass->convertFollow( array(
					'follow_app'			=> 'forums',
					'follow_area'			=> 'topic',
					'follow_rel_id'			=> $row['id_topic'],
					'follow_rel_id_type'	=> 'forums_topics',
					'follow_member_id'		=> $follow['id_member'],
					'follow_is_anon'		=> 0,
					'follow_added'			=> time(),
					'follow_notify_do'		=> 1,
					'follow_notify_meta'	=> '',
					'follow_notify_freq'	=> 'immediate',
					'follow_notify_sent'	=> 0,
					'follow_visible'		=> 1,
				) );
			}
			
			$libraryClass->setLastKeyValue( $row['id_topic'] );
		}
	}
	
	/**
	 * Convert posts
	 *
	 * @return	void
	 */
	public function convertForumsPosts()
	{
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_msg' );
		
		foreach( $this->fetch( 'messages', 'id_msg' ) AS $row )
		{
			$info = array(
				'pid'			=> $row['id_msg'],
				'topic_id'		=> $row['id_topic'],
				'post'			=> $row['body'],
				'edit_time'		=> $row['modified_time'],
				'author_id'		=> $row['id_member'],
				'author_name'	=> $row['poster_name'],
				'ip_address'	=> $row['poster_ip'],
				'post_date'		=> $row['poster_time'],
				'queued'		=> ( $row['approved'] ) ? 0 : -1,
				'edit_name'		=> $row['modified_name'],
			);

			$libraryClass->convertForumsPost( $info );
			$libraryClass->setLastKeyValue( $row['id_msg'] );
		}
	}
	
	/**
	 * @brief	Cached attachment paths
	 */
	protected static $paths = NULL;
	
	/**
	 * Convert attachments
	 *
	 * @return	void
	 */
	public function convertAttachments()
	{
		if ( \is_null( static::$paths ) )
		{
			try
			{
				static::$paths = @\unserialize( $this->db->select( 'value', 'settings', array( "variable=?", 'attachmentUploadDir' ) )->first() );
				
				/* Unserialize failed. Set to an array so we don't try again */
				if ( !\is_array( static::$paths ) )
				{
					static::$paths = array();
				}
			}
			catch( \UnderflowException $e )
			{
				static::$paths = array();
			}
		}
		
		$libraryClass = $this->getLibrary();
		
		$libraryClass::setKey( 'id_attach' );
		
		foreach( $this->fetch( 'attachments', 'id_attach', array( "id_msg<>? AND attachment_type < ?", 0, 3 ) ) AS $row )
		{
			try
			{
				$post = $this->db->select( 'id_topic, poster_time', 'messages', array( "id_msg=?", $row['id_msg'] ) )->first();
			}
			catch( \UnderflowException $e )
			{
				$libraryClass->setLastKeyValue( $row['id_attach'] );
				continue;
			}
			
			/* Map */
			$map = array(
				'id1'		=> $post['id_topic'],
				'id2'		=> $row['id_msg'],
			);
			
			/* We need to figure out where it is */
			$legacyName = null;
			if ( $row['file_hash'] )
			{
				$location = $row['id_attach'] . '_' . $row['file_hash'];
			}
			else
			{
				/* Clean filename per legacy SMF requirements */
				$cleanName = strtr( $row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
				$cleanName = strtr( $cleanName, array( 'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u' ) );
				$cleanName = preg_replace( array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $cleanName );

				$location = $row['id_attach'] . '_' . str_replace( '.', '_', $cleanName ) . md5( $cleanName );
				$legacyName = preg_replace( '~\.[\.]+~', '.', $cleanName);
			}
			$location = str_replace( ' ', '_', $location );
			
			/* Fix File Name */
			$row['filename'] = str_replace(' ', '_', $row['filename']);
			$row['filename'] = str_replace('!', '', $row['filename']);
			$row['filename'] = str_replace('(', '', $row['filename']);
			$row['filename'] = str_replace(')', '', $row['filename']);
			$row['filename'] = str_replace(',', '', $row['filename']);
			$row['filename'] = str_replace('[', '', $row['filename']);
			$row['filename'] = str_replace(']', '', $row['filename']);
			$row['filename'] = str_replace('&', '', $row['filename']);
			$row['filename'] = str_replace('\'', '', $row['filename']);
			$row['filename'] = str_replace( \chr(195) . \chr(182) , 'A', $row['filename']);
			$row['filename'] = str_replace( \chr(195) . \chr(164) , 'A', $row['filename']);
			
			/* General Information */
			$info = array(
				'attach_id'			=> $row['id_attach'],
				'attach_file'		=> $row['filename'],
				'attach_member_id'	=> $row['id_member'],
				'attach_hits'		=> $row['downloads'],
				'attach_date'		=> $post['poster_time'],
				'attach_ext'		=> $row['fileext'],
				'attach_filesize'	=> $row['size']
			);
			
			if ( $row['id_folder'] AND $row['id_folder'] != 1 )
			{
				$path = rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $location;

				if( !file_exists( $path ) AND isset( $legacyName ) )
				{
					$path = rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $legacyName;
				}
			}
			else
			{
				if ( isset( static::$paths[ $row['id_folder'] ] ) )
				{
					$path = rtrim( static::$paths[ $row['id_folder'] ], '/' ) . '/' . $location;

					if( !file_exists( $path ) AND isset( $legacyName ) )
					{
						$path = rtrim( static::$paths[ $row['id_folder'] ], '/' ) . '/' . $legacyName;
					}
				}
				else
				{
					$path = rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $location;

					if( !file_exists( $path ) AND isset( $legacyName ) )
					{
						$path = rtrim( $this->app->_session['more_info']['convertAttachments']['attach_location'], '/' ) . '/' . $legacyName;
					}
				}
			}

			// in 2.1 attachments are stored with a .dat extension, so we need to look for it if this path doesn't exist.
			if( !file_exists( $path ) )
			{
				/*  Check the 2.1 file */
				if( file_exists( $path . '.dat' ) )
				{
					$path .= '.dat';
				}
			}
			
			$libraryClass->convertAttachment( $info, $map, $path );
			
			$libraryClass->setLastKeyValue( $row['id_attach'] );
		}
	}

	/**
	 * Check if we can redirect the legacy URLs from this software to the new locations
	 *
	 * @return	NULL|\IPS\Http\Url
	 */
	public function checkRedirects()
	{
		/* Support SMF friendly URLs ( index.php/topic,1000.0.html ) */
		$url = \IPS\Request::i()->url();
		if( mb_strpos( $url->data[ \IPS\Http\Url::COMPONENT_PATH ], 'topic,' ) !== FALSE )
		{
			\IPS\Request::i()->topic = (int) explode( ',', $url->data[ \IPS\Http\Url::COMPONENT_PATH ] )[1];
		}

		if( isset( \IPS\Request::i()->topic ) )
		{
			if( mb_strpos( \IPS\Request::i()->topic, '.msg' ) !== FALSE )
			{
				$class	= '\IPS\forums\Topic\Post';
				$types	= array( 'posts', 'forums_posts' );
				$oldId	= mb_substr( \IPS\Request::i()->topic, mb_strpos( \IPS\Request::i()->topic, '.msg' ) + 4 );
			}
			else
			{
				$pieces	= explode( '.', \IPS\Request::i()->topic );
				$class	= '\IPS\forums\Topic';
				$types	= array( 'topics', 'forums_topics' );
				$oldId	= $pieces[0];
			}
		}
		elseif( isset( \IPS\Request::i()->board ) )
		{
			$pieces = explode( '.', \IPS\Request::i()->board );
			$class	= '\IPS\forums\Forum';
			$types	= array( 'forums', 'forums_forums' );
			$oldId	= $pieces[0];
		}

		if( isset( $class ) )
		{
			try
			{
				try
				{
					$data = (string) $this->app->getLink( $oldId, $types );
				}
				catch( \OutOfRangeException $e )
				{
					$data = (string) $this->app->getLink( $oldId, $types, FALSE, TRUE );
				}
				$item = $class::load( $data );

				if( $item instanceof \IPS\Content )
				{
					if( $item->canView() )
					{
						return $item->url();
					}
				}
				elseif( $item instanceof \IPS\Node\Model )
				{
					if( $item->can( 'view' ) )
					{
						return $item->url();
					}
				}
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}

		return NULL;
	}
}
<?php
/**
 * @brief		Promote model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Feb 2017
 */

namespace IPS\core;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief Promote Model
 */
class _Promote extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons = array();
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_social_promote';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'promote_';
	
	/**
	 * @brief	Class object
	 */
	protected $object = NULL;
	
	/**
	 * @brief	Author object
	 */
	protected $author = NULL;
	
	/**
	 * @brief	Sent data
	 */
	protected $history = array();
	
	/**
	 * @brief	Promoter objects
	 */
	protected static $promoters = NULL;
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		/* Ensure TEXT fields are never NULL */
		$this->_data['text'] = array();
		$this->_data['short_link'] = '';
		$this->_data['images'] = array();
		$this->_data['media'] = array();
		$this->_data['share_to'] = array();
		$this->_data['returned'] = array();
	}

	/**
	 * Set the "text" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_text( $value )
	{
		$this->_data['text'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "text" field
	 *
	 * @return array
	 */
	public function get_text()
	{
		return json_decode( $this->_data['text'], TRUE );
	}
	
	/**
	 * Set the "attach_ids" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_images( $value )
	{
		$this->_data['images'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "attach_ids" field
	 *
	 * @return array
	 */
	public function get_images()
	{
		return $this->_data['images'] ? json_decode( $this->_data['images'], TRUE ) : array();
	}
	
	/**
	 * Set the "media" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_media( $value )
	{
		$this->_data['media'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "media" field
	 *
	 * @return array
	 */
	public function get_media()
	{
		return $this->_data['media'] ? json_decode( $this->_data['media'], TRUE ) : array();
	}
	
	/**
	 * Set the "share_to" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_share_to( $value )
	{
		$this->_data['share_to'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "share_to" field
	 *
	 * @return array
	 */
	public function get_share_to()
	{
		return $this->_data['share_to'] ? json_decode( $this->_data['share_to'], TRUE ) : array();
	}
	
	/**
	 * Set the "returned" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_returned( $value )
	{
		$this->_data['returned'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "returned" field
	 *
	 * @return array
	 */
	public function get_returned()
	{
		return $this->_data['returned'] ? json_decode( $this->_data['returned'], TRUE ) : array();
	}
	
	/**
	 * Set the "form_data" field
	 *
	 * @param string|array $value
	 * @return void
	 */
	public function set_form_data( $value )
	{
		$this->_data['form_data'] = ( \is_array( $value ) ? json_encode( $value ) : $value );
	}
	
	/**
	 * Get the "form_data" field
	 *
	 * @return array
	 */
	public function get_form_data()
	{
		return $this->_data['form_data'] ? json_decode( $this->_data['form_data'], TRUE ) : array();
	}
	
	/**
	 * The author object
	 *
	 * @return \IPS\Member
	 */
	public function author()
	{
		if ( $this->author === NULL )
		{
			try
			{
				$this->author = \IPS\Member::load( $this->added_by );
			}
			catch ( \Exception $e )
			{
				$this->author = new \IPS\Member;
			}
		}
		
		return $this->author;
	}
	
	/**
	 * The content object
	 *
	 * @return \IPS\Content
	 */
	public function object()
	{
		if ( $this->object === NULL )
		{
			$class = $this->class;
			$this->object = $class::load( $this->class_id );
		}
		
		return $this->object;
	}
	
	/**
	 * Get Our Picks title
	 *
	 * @return string
	 */
	public function get_ourPicksTitle()
	{
		$settings = $this->form_data;
		
		if ( ! empty( $settings['internal']['title'] ) )
		{
			return $settings['internal']['title'];
		}
		
		return $this->objectTitle;
	}
	
	/**
	 * Get the object title
	 *
	 * @return string
	 */
	public function get_objectTitle()
	{
		return static::objectTitle( $this->object() );
	}
	
	/**
	 * Get the object date posted
	 *
	 * @return \IPS\DateTime|NULL
	 */
	public function get_objectDatePosted()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content )
		{
			if ( isset( $object::$databaseColumnMap['date'] ) )
			{
				return \IPS\DateTime::ts( $object->mapped('date') );
			}
			
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get the object author
	 *
	 * @return \IPS\Member|NULL
	 */
	public function get_objectAuthor()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content )
		{
			return $object->author();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			try
			{
				return $object->owner();
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get the object unread status
	 *
	 * @return bool|null
	 */
	public function get_objectIsUnread()
	{
		$object = $this->object();
		
		if ( $object instanceof \IPS\Content\Item )
		{
			return $object->unread();
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			return $object->item()->unread();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			if ( $object::$contentItemClass )
			{
				$contentItemClass = $object::$contentItemClass;
				
				return $contentItemClass::containerUnread( $object );
			}
			
			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}

	/**
	 * Get the number and indefinite article for replies/children where applicable
	 *
	 * @return array|null
	 */
	public function get_objectDataCount()
	{
		return $this->objectDataCount( NULL );
	}

	/**
	 * Get the number and indefinite article for replies/children where applicable
	 *
	 * @param	\IPS\Lang|null	$language	Language to use (or NULL for currently logged in member's language)
	 * @return	array|null
	 */
	public function objectDataCount( $language=NULL )
	{
		$language	= $language ?: \IPS\Member::loggedIn()->language();
		$object		= $this->object();
		
		if ( $object instanceof \IPS\Content\Item )
		{
			try
			{
				$container = $object->container();
			}
			catch( \Exception $e ){}

			if ( $object::supportsComments( NULL, $container ) )
			{
				$count = $object->mapped('num_comments');

				if ( $count AND isset( $object::$firstCommentRequired ) AND $object::$firstCommentRequired === TRUE )
				{
					$count--;
				}
				
				return array( 'count' => $count, 'words' => $language->addToStack( 'num_replies', FALSE, array( 'pluralize' => array( $count ) ) ) );
			}

			if ( $object::supportsReviews( NULL, $container ) )
			{
				$count = $object->mapped('num_reviews');

				return array( 'count' => $count, 'words' => $language->addToStack( 'num_reviews', FALSE, array( 'pluralize' => array( $count ) ) ) );
			}
			
			/* Valid object, but there isn't any date data available */
			return NULL;
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			return NULL;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			if( $object->_items !== NULL )
			{
				$count = $object->_items;

				return array( 'count' => $count, 'words' => $language->addToStack( $object->_countLanguageString, NULL, array( 'pluralize' => array( $count ) ) ) );
			}

			return NULL;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Returns "Foo posted {{indefart}} in {{container}}, {{date}}
	 *
	 * @return	string
	 */
	public function get_objectMetaDescription()
	{
		return $this->objectMetaDescription( NULL );
	}
	
	/**
	 * Returns "Foo posted {{indefart}} in {{container}}, {{date}}
	 *
	 * @param	\IPS\Lang|NULL	$plaintextLanguage	If specified, will return plaintext (not linking the user or the container in the language specified). If NULL, returns with links based on logged in user's theme and language
	 * @return	string
	 */
	public function objectMetaDescription( $plaintextLanguage=NULL )
	{
		$object = $this->object();
		$author = $this->objectAuthor;
				
		if ( $object instanceof \IPS\Content\Item )
		{
			$container = $object->containerWrapper();

			if ( $container )
			{
				if ( !$plaintextLanguage )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_container', FALSE, array(
						'htmlsprintf'	=> array( $author->link(), $this->objectDatePosted->html( FALSE ) ),
						'sprintf'		=> array( $object->indefiniteArticle(), $container->url(), $container->_title ),
					) );
				}
				else
				{
					return $plaintextLanguage->addToStack( 'promote_metadescription_container_nolink', FALSE, array(
						'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $container->getTitleForLanguage( $plaintextLanguage ), $author->name, $this->objectDatePosted->relative( \IPS\DateTime::RELATIVE_FORMAT_NORMAL, $plaintextLanguage ) ),
					) );
				}
			}
			else
			{
				if ( !$plaintextLanguage )
				{
					return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
						'htmlsprintf'	=> array( $author->link(), $this->objectDatePosted->html( FALSE ) ),
						'sprintf'		=> array( $object->indefiniteArticle() )
					) );
				}
				else
				{
					return $plaintextLanguage->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
						'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $author->name, $this->objectDatePosted->relative( \IPS\DateTime::RELATIVE_FORMAT_NORMAL, $plaintextLanguage ) )
					) );
				}
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			if ( !$plaintextLanguage )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
					'htmlsprintf'	=> array( $author->link(), $this->objectDatePosted->html( FALSE ) ),
					'sprintf'		=> array( $object->indefiniteArticle() )
				) );
			}
			else
			{
				return $plaintextLanguage->addToStack( 'promote_metadescription_nocontainer', FALSE, array(
					'sprintf'		=> array( $object->indefiniteArticle( $plaintextLanguage ), $author->name, $this->objectDatePosted->relative( \IPS\DateTime::RELATIVE_FORMAT_NORMAL, $plaintextLanguage ) )
				) );
			}
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			if ( !$plaintextLanguage )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_metadescription_node', FALSE, array(
					'htmlsprintf'	=> array( $author->link() ),
					'sprintf'		=> array( $object->url(), $object->_title )
				) );
			}
			else
			{
				return $plaintextLanguage->addToStack( 'promote_metadescription_node_nolink', FALSE, array(
					'sprintf'		=> array( $object->getTitleForLanguage( $plaintextLanguage ), $author->name )
				) );
			}
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get reactions class for this object
	 *
	 * @return \IPS\Content\Reactable|null
	 */
	public function get_objectReactionClass()
	{
		$object = $this->object();
		$class = NULL;
		
		if ( ! \IPS\Settings::i()->reputation_enabled )
		{
			return NULL;
		}
		
		if ( $object instanceof \IPS\Content\Item )
		{
			/* The first post has the reactions for this item */
			if ( $object::$firstCommentRequired )
			{
				try
				{
					$class = $object->comments( 1, NULL, 'date', 'asc' );
				}
				catch( \Exception $e )
				{
					$class =  NULL;
				}
			}
			else
			{
				$class = $object;
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			$class = $object;
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return NULL;
		}
		
		return ( $class and \IPS\IPS::classUsesTrait( $class, 'IPS\Content\Reactable' ) ) ? $class : NULL;
	}
	
	/**
	 * Send to networks now
	 *
	 * @return void
	 */
	public function send()
	{
		/* Race condition possible, so flag as sent now */
		$this->sent = time();
		$this->save();
		$returned = $this->returned;
		$time = time();
		$hasFailed = false;
		
		/* Item hidden? Unhide it now */
		if ( $this->object()->hidden() !== 0 )
		{
			$this->object()->unhide( FALSE );
		}
		
		foreach( $this->share_to as $service )
		{
			if ( $this->failed )
			{
				/* It failed, but some services may have sent */
				if ( ! $this->serviceFailed( $service ) )
				{
					/* This service did sent, so skip */
					continue;
				}
			}

			$response = array(
				'response_promote_id' => $this->id,
				'response_promote_key' => $service,
				'response_date' => time(),
				'response_sent_date' => $time
			);
		
			try
			{
				$serviceObject = $this->getPromoter( $service )->setMember( \IPS\Member::load( $this->added_by ) );
				
				/* Successful post ID returned */
				$returned[ $service ] = $serviceObject->post( $this );
			}
			catch( \Exception $ex )
			{
				$hasFailed = true;
				$this->scheduled = time() + 600;
				$this->sent = 0;
				
				$response['response_failed'] = 1;
			}
			
			/* Full response stored */
			if ( isset( $returned[ $service ] ) AND \is_array( $returned[ $service ] ) )
			{
				foreach( $returned[ $service ] as $id => $data )
				{
					$response['response_json'] = json_encode( $data, TRUE );
					$response['response_service_id'] = $id;
					
					\IPS\Db::i()->insert( 'core_social_promote_content', $response );
				}
			}
			else
			{
				$response['response_json'] = json_encode( $serviceObject->response, TRUE );
				
				\IPS\Db::i()->insert( 'core_social_promote_content', $response );
			}
		}

		$this->failed = ( $hasFailed ) ? $this->failed + 1 : 0;
		$this->returned = $returned;
		$this->save();
        \IPS\Api\Webhook::fire( 'content_promoted', $this, $this->object()->webhookFilters() );
    }
	
	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		
		/* Enable the task again */
		\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'promote' ) );
	}
	
	/**
	 * Return an array of File objects
	 *
	 * @return array|null
	 */
	public function imageObjects()
	{
		$photos = array();
		if ( \count( $this->images ) )
		{
			foreach( $this->images as $image )
			{
				foreach( $image as $ext => $url )
				{
					$photos[] = \IPS\File::get( $ext, $url );
				}
			}
		}
		
		if ( \count( $this->media ) )
		{
			foreach( $this->media as $media )
			{
				$photos[] = \IPS\File::get( 'core_Promote', $media );
			}
		}
		
		return ( \count( $photos ) ) ? $photos : NULL;
	}
	
	/**
	 * Look for a specific image
	 *
	 * @param	string	$path		Image path monthly_x_x/foo.gif
	 * @param	string	$extension	Storage extension
	 * @return boolean
	 */
	public function hasImage( $path, $extension='core_Attachment' )
	{
		foreach( $this->images as $image )
		{
			foreach( $image as $ext => $url )
			{
				if ( $ext == $extension and $path == $url )
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Returns a \IPS\DateTime object for the scheduled timestamp
	 *
	 * @return \IPS\DateTime
	 */
	public function scheduledDateTime()
	{
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		return \IPS\DateTime::ts( $this->scheduled )->setTimezone( $timezone );
	}
	
	/**
	 * Returns a \IPS\DateTime object for the sent timestamp
	 *
	 * @return \IPS\DateTime
	 */
	public function sentDateTime()
	{
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		return \IPS\DateTime::ts( $this->sent )->setTimezone( $timezone );
	}
	
	/**
	 * Shorten URL.
	 *
	 * @param	string		$service	Service key, (such as twitter)
	 * @return	boolean
	 */
	public function serviceFailed( $service )
	{
		if ( ! $this->failed )
		{
			return FALSE;
		}
		
		$returned = $this->returned;
		
		return isset( $returned[ $service ] ) ? FALSE : TRUE;
	}
	
	/**
	 * Returns text sent to a named service
	 *
	 * @param	string		$service		Service key (twitter)
	 * @param	boolean		$forDisplay		Is this for display in output?
	 * @return  string|NULL
	 */
	public function getText( $service, $forDisplay=false )
	{
		if ( \in_array( $service, $this->share_to ) )
		{
			$text = $this->text;
			
			return isset( $text[ $service ] ) ? ( $forDisplay ? nl2br( htmlspecialchars( $text[ $service ], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) ) : $text[ $service ] ) : NULL;
		}
		
		return NULL;
	}
	
	/**
	 * Sets text for a named service
	 *
	 * @param	string		$service		Service key (twitter)
	 * @param	boolean		$text			Text to save
	 * @return  string|NULL
	 */
	public function setText( $service, $text )
	{
		$allText = $this->text;
		$allText[ $service ] = $text;
		$this->text = $allText;
	}

	/**
	 * Return the published URL for this post
	 *
	 * @param		string $service Service key, (such as twitter)
	 * @return		string|NULL
	 */
	public function getPublishedUrl( $service )
	{
		$returned = $this->returned;
		
		if ( isset( $returned[ $service ] ) )
		{
			try
			{
				if ( $url = static::getPromoter( $service )->getUrl( $returned[ $service ] ) )
				{
					return $url;
				}
				
				return $this->object()->url();
			}
			catch ( \InvalidArgumentException|\RuntimeException $e )
			{
				return NULL;
			}
		}
	}

	/**
	 * Attempt to get all responses for this ID
	 *
	 * @param 	string		$service	Service to return (facebook, etc)
	 * @return	array|NULL		array( response_id => data )
	 */
	public function responses( $service )
	{
		$responses = array();
		try
		{
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote_content', array( 'response_promote_id=? and response_promote_key=?', $this->id, $service ) ) as $row )
			{
				$responses[ $row['response_id'] ] = json_decode( $row['response_json'], TRUE );
			}
		}
		catch( \Exception $e )
		{
			return NULL;
		}
		
		return \count( $responses ) ? $responses : NULL;
	}
	
	/**
	 * Fetch successful sent history for this promoted item
	 *
	 * @return	array|NULL		array( timestamp => array( service => response_time, ... )
	 */
	public function history()
	{
		if ( ! isset( $this->history[ $this->id ] ) )
		{
			$this->history[ $this->id ] = array();
			
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote_content', array( 'response_promote_id=? and response_failed=0', $this->id ) ) as $row )
			{
				$this->history[ $this->id ][ $row['response_sent_date'] ][ $row['response_promote_key'] ][] = $row;
			}
		}
		
		return \count( $this->history[ $this->id ] ) ? $this->history[ $this->id ] : NULL;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		try
		{
			\IPS\Db::i()->delete( 'core_social_promote_content', array( 'response_promote_id=?', $this->id ) );
		}
		catch( \Exception $e ) { }
		
		return parent::delete();
	}
	
	/**
	 * @brief	Cache internal stream data
	 */
	protected static $internalStream = array();

	/**
	 * Promote stream of internally promoted items
	 *
	 * @param	int|array	$limit			Number of items to fetch
	 * @param	string		$sortField		Sort by field
	 * @param	string		$sortDirection	Sort by direction (asc, desc)
	 * @return  array
	 */
	public static function internalStream( $limit=10, $sortField='promote_sent', $sortDirection='desc')
	{
		$_key = md5( $limit . $sortField . $sortDirection );

		if( array_key_exists( $_key, static::$internalStream ) )
		{
			return static::$internalStream[ $_key ];
		}

		$items = array();
		
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_sent > 0 and promote_internal=1 and promote_hide=0' ), $sortField . ' ' . $sortDirection, $limit ) as $row )
		{
			$items[ $row['promote_id'] ] = static::constructFromData( $row );
		}
		
		static::$internalStream[ $_key ] = $items;
		return $items;
	}
	
	
	/**
	 * Can a member promote anything?
	 *
	 * @param	NULL|\IPS\Member	$member		Member object or NULL for current member
	 * @return	bool
	 */
	public static function canPromote( $member=NULL )
	{
		$member = $member ? $member : \IPS\Member::loggedIn();
		
		/* Got any services enabled? */
		if ( static::promoters() === NULL )
		{
			return FALSE;
		}
		
		if ( \IPS\Settings::i()->promote_members )
		{
			if ( \IPS\Settings::i()->promote_members and \in_array( $member->member_id , explode( "\n", \IPS\Settings::i()->promote_members ) ) )
			{
				return TRUE;
			}
		}
		
		if ( $member->group['gbw_promote'] )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Can View wrapper for items and nodes
	 *
	 * @param	object				$object		Object (node or content item)
	 * @param	NULL|\IPS\Member	$member		Member object or NULL for current member
	 * @return boolean
	 * @throws \OutOfRangeException
	 */
	public static function objectCanView( $object, $member=NULL )
	{
		$member ?: \IPS\Member::loggedIn();
		
		if ( $object instanceof \IPS\Content )
		{
			return $object->canView( $member );
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return $object->can( 'view', $member );
		}
		
		throw new \OutOfRangeException('object_not_valid');
	}
	
	/**
	 * Get title wrapper for items and nodes
	 *
	 * @param	object	$object		Object (node or content item)
	 * @return string
	 */
	public static function objectTitle( $object )
	{
		if ( $object instanceof \IPS\Content\Item )
		{
			return $object->mapped('title');
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			try
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'promote_thing_in_thing', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $object::$title ), $object->item()->mapped('title') ) ) );
			}
			catch( \Exception $e )
			{
				return $object->item()->mapped('title');
			}
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			return $object->_title;
		}
		
		throw new \OutofRangeException('object_not_valid');
	}
	
	/**
	 * Get content wrapper for items and nodes
	 *
	 * @param	object	$object		Object (node or content item)
	 * @return string
	 */
	public static function objectContent( $object )
	{
		$result = NULL;

		if ( $object instanceof \IPS\Content\Item )
		{
			if ( isset( $object::$databaseColumnMap['content'] ) )
			{
				$result = $object->truncated();
			}
			else if ( $object::$firstCommentRequired )
			{
				$firstComment = $object->comments( 1, NULL, 'date', 'asc' );
				
				$result = $firstComment->truncated();
			}
		}
		else if ( $object instanceof \IPS\Content\Comment )
		{
			$result = $object->truncated();
		}
		else if ( $object instanceof \IPS\Node\Model )
		{
			$result = $object->description;
		}
		
		/* If result was not set, throw exception now */
		if( $result === NULL )
		{
			throw new \OutofRangeException('object_not_valid');
		}

		/* If we treat enter key as newline instead of paragraph, we need to clean up a bit further */
		if( !\IPS\Settings::i()->editor_paragraph_padding )
		{
			$result = str_replace( "\n", '', $result );
		}

		/* Clean up excess newlines */
		$result = trim( preg_replace( "#(<br>){1,}#", "\n",  preg_replace( '#(<br>)\s+#', "\n", $result ) ) );

		/* Clean up excess spaces at the beginning of text lines */
		$lines = array();

		foreach( explode( "\n", $result ) as $line )
		{
			$lines[] = trim( $line );
		}

		$result = implode( "\n", $lines );

		/* If this is a node, strip HTML tags for security reasons */
		if ( $object instanceof \IPS\Node\Model )
		{
			$result = strip_tags( $result );
		}

		return $result;
	}
	
	/**
	 * Load promote row for this class and id
	 */
	protected static $classAndIdLookup = array();
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		$object = parent::constructFromData( $data, $updateMultitonStoreIfExists );
		static::$classAndIdLookup[ $object->class ][ $object->class_id ] = $object->id;
		return $object;
	}

	/**
	 * Load promote row for this class and id
	 *
	 * @param	string		$class 				Class name
	 * @param	integer		$id					Item ID
	 * @param	boolean		$sent				Only look for sent items
	 * @param	boolean		$futureScheduled	Only look for future scheduled items
	 * @return  static 		Item
	 */
	public static function loadByClassAndId( $class, $id, $sent=FALSE, $futureScheduled=FALSE )
	{		
		if ( !isset( static::$classAndIdLookup[ $class ][ $id ] ) )
		{
			try
			{
				$object = static::constructFromData( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_class=? and promote_class_id=?', $class, $id ) )->first() );
			}
			catch( \UnderflowException $e )
			{
				static::$classAndIdLookup[ $class ][ $id ] = 0;
				return;
			}
		}
		else
		{
			if ( static::$classAndIdLookup[ $class ][ $id ] )
			{
				$object = static::load( static::$classAndIdLookup[ $class ][ $id ] );
			}
			else
			{
				return;
			}
		}
		
		if ( $futureScheduled and $object->scheduled < time() )
		{
			return;
		}
		
		if ( $sent and $object->sent > time() )
		{
			return;
		}
		
		return $object;		
	}
	
	/**
	 * Shorten URL.
	 * We only have bit.ly as a shortener at the moment.
	 *
	 * @param	string		$longUrl	The original URL
	 * @return	string		NULL if no shortnener available or it fails
	 * @throws	\RuntimeException if shortener fails
	 * @throws	\UnderflowException if no shortener available
	 */
	public static function shortenUrl( $longUrl )
	{
		if ( ! \IPS\Settings::i()->bitly_enabled or ! \IPS\Settings::i()->bitly_token )
		{
			throw new \UnderflowException;
		}
		
		/* Have a bash at it like */
		try
		{
			$groups = \IPS\Http\Url::external( "https://api-ssl.bitly.com/v4/groups" )->request()->setHeaders( array( 'Content-Type' => 'application/json', 'Authorization' => "Bearer " . \IPS\Settings::i()->bitly_token ) )->get()->decodeJson();
			$response = \IPS\Http\Url::external( "https://api-ssl.bitly.com/v4/shorten" )->request()->setHeaders( array( 'Content-Type' => 'application/json', 'Authorization' => "Bearer " . \IPS\Settings::i()->bitly_token ) )->post( json_encode( array( 'long_url' => (string) $longUrl, 'group_guid' => $groups['groups'][0]['guid'] ) ) );
			
			if ( !\in_array( $response->httpResponseCode, array( 200, 201 ) ) )
			{
				throw new \RuntimeException;
			}
			
			$responseData = json_decode( $response->content, TRUE );

			return $responseData['link'];
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			throw new \RuntimeException;
		}
	}

	/**
	 * Get the next auto schedule timestamp
	 *
	 * @return \IPS\DateTime|null object
	 */
	public static function getNextAutoSchedule()
	{
		if ( ! \IPS\Settings::i()->promote_scheduled )
		{
			return NULL;
		}
		
		$latest = \IPS\Db::i()->select( 'MAX(promote_scheduled)', 'core_social_promote', array( 'promote_schedule_auto=1 and promote_sent=0 and promote_failed=0' ) )->first();
		$timezone = new \DateTimeZone( \IPS\Settings::i()->promote_tz );
		$current = \IPS\DateTime::create()->setTimezone( $timezone );
		$times = explode( ',', \IPS\Settings::i()->promote_scheduled );
		$time = NULL;
		
		/* Sort schedule times just in case they have been added out of order in the ACP */
		natsort( $times );

		if ( $latest AND $latest > $current->getTimestamp() )
		{
			$current = \IPS\DateTime::ts( $latest )->setTimezone( $timezone );
		}
		
		/* Fetch the next scheduled time */
		$test = $current;
		foreach( $times as $entry )
		{
			list( $h, $m ) = explode( ':', $entry );
			$test = \IPS\DateTime::create()->setTimezone( $timezone )->setTime( $h, $m );
			
			if ( $current->getTimeStamp() < $test->getTimeStamp() )
			{
				$time = $test;
				break;
			}
		}
		
		/* Still here? Then pick the earliest time for the next day */
		if ( $time === NULL )
		{
			while( $time === NULL )
			{
				/* We need to clone otherwise $test is always the same as $current */
				$test = clone $current;
				
				foreach( $times as $entry )
				{
					list( $h, $m ) = explode( ':', $entry );
					$test->setTime( $h, $m );
					if ( ( $test->getTimeStamp() > time() ) and ( $current->getTimeStamp() < $test->getTimeStamp() ) )
					{
						$time = $test;
						break;
					}
				}
				
				/* Still here? */
				$current->add( new \DateInterval( 'P1D' ) )->setTime( 0, 0 );
			}
		}
		
		return $time;
	}

	/**
	 * Return a single promote object
	 *
	 * @param $key		string	Promote Service
	 * @return \IPS\Login
	 */

	public static function getPromoter( $key )
	{
		/* Try and get this from the datastore first */
		$promoters = static::promoters();
	
		if ( $promoters !== NULL )
		{
			foreach( $promoters as $promoterKey => $object )
			{
				if ( mb_strtolower( $promoterKey ) == mb_strtolower( $key ) )
				{
					return $object;
				}
			}
		}
		
		/* Fall back */
		return \IPS\Content\Promote\PromoteAbstract::constructFromData( \IPS\Db::i()->select( '*', 'core_social_promote_sharers', array( 'sharer_key=?', $key ) )->first() );
	}
	
	/**
	 * Get Promoter objects
	 *
	 * @return	array
	 */
	public static function promoters()
	{
		/* Fetch the appropriate promoters */
		if ( static::$promoters === NULL )
		{	
			foreach ( static::getStore() as $row )
			{
				try
				{
					static::$promoters[ $row['sharer_key'] ] = \IPS\Content\Promote\PromoteAbstract::constructFromData( $row );
				}
				catch ( \RuntimeException $e ) { }
			}
		}

		return static::$promoters;
	}

	/**
	 * Get all promoter obects
	 *
	 * @return	array
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->promoters ) )
		{
			\IPS\Data\Store::i()->promoters = iterator_to_array( \IPS\Db::i()->select( '*', 'core_social_promote_sharers', 'sharer_enabled=1' ) );
		}

		return \IPS\Data\Store::i()->promoters;
	}
	
	/**
	 * Return the promotable services this user has access to
	 *
	 * @return NULL|array of promote classes
	 */
	public static function promoteServices()
	{
		$services = array();
		$promoters = static::promoters();
		
		if ( $promoters === NULL )
		{
			return NULL;
		}
		
		foreach( $promoters as $key => $object )
		{
			if ( $object->setMember( \IPS\Member::loggedIn() )->canPromote() )
			{
				$services[] = $object;
			}
		}
		
		return \count( $services ) ? $services : NULL;
	}
	
	/**
	 * Process any queued items
	 *
	 * @return void
	 */
	public static function processQueue()
	{
		$processed = 0;
		foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_sent=0 and promote_failed < 4 and ( promote_scheduled < ' . time() . ' and promote_scheduled > 0 )' ), 'promote_scheduled asc', array( 0, 5 ) ) as $row )
		{
			$processed++;
			$promote = static::constructFromData( $row );
			$promote->send();
		}
		
		if ( ! $processed )
		{
			/* Disable the task for now, but only if there is nothing to send later */
			try
			{
				$future = \IPS\Db::i()->select( 'COUNT(*)', 'core_social_promote', array( "promote_sent=?", 0 ) )->first();
			}
			catch( \Exception $e )
			{
				$future = 0;
			}
			
			if ( !$future )
			{
				\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'promote' ) );
			}
		}
	}
	
	/**
	 * Reschedule queued items
	 *
	 * @return void
	 */
	public static function rescheduleQueue()
	{
		/* Reset times */
		if ( \IPS\Settings::i()->promote_scheduled )
		{
			\IPS\Db::i()->update( 'core_social_promote', array( 'promote_scheduled' => 0 ), array( 'promote_schedule_auto=1 and promote_sent=0' ) );
			
			foreach( \IPS\Db::i()->select( '*', 'core_social_promote', array( 'promote_scheduled=0 and promote_schedule_auto=1 and promote_sent=0' ), 'promote_added asc' ) as $row )
			{
				\IPS\Db::i()->update( 'core_social_promote', array( 'promote_scheduled' => static::getNextAutoSchedule()->getTimeStamp() ), array( 'promote_id=?', $row['promote_id'] ) );
			}
		}
	}
	
	/**
	 * Change the hidden flag for all rows that match a class
	 *
	 * @param	$class 	Object	Class (eg IPS\Blog\entry)
	 * @param	$hidden	Boolean	Set hidden
	 * @return	void
	 */
	public static function changeHiddenByClass( $class, $hidden=0 )
	{
		\IPS\Db::i()->update( 'core_social_promote', array( 'promote_hide' => \intval( $hidden ) ), array( 'promote_class=?', \get_class( $class ) ) );
		
		if ( $class instanceof \IPS\Content\Item )
		{
			if ( isset( $class::$commentClass ) )
			{
				\IPS\Db::i()->update( 'core_social_promote', array( 'promote_hide' => \intval( $hidden ) ), array( 'promote_class=?', $class::$commentClass ) );
			}
			
			if ( isset( $class::$reviewClass ) )
			{
				\IPS\Db::i()->update( 'core_social_promote', array( 'promote_hide' => \intval( $hidden ) ), array( 'promote_class=?', $class::$reviewClass ) );
			}
		}
	}

    /**
     * Get output for API
     *
     * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
     *
     * @apiresponse		\IPS\Member		promotedBy		The member who promoted the content item
     * @apiresponse		string			itemClass		The FQN classname
     * @apiresponse		string			itemTitle		Title
     * @apiresponse		string			itemDescription	The content
     * @apiresponse		object			item			The promoted content item
     *
     */
    public function apiOutput( \IPS\Member $authorizedMember = NULL )
    {
        return [
            'promotedBy' => $this->author()->apiOutput($authorizedMember),
            'itemClass' => \get_class($this->object()),
            'itemTitle' => $this->objectTitle,
            'itemDescription' => $this->objectMetaDescription,
            'item' => $this->object()->apiOutput($authorizedMember),
        ];
    }
}
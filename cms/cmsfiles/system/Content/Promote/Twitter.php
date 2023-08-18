<?php
/**
 * @brief		Twitter Promotion
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 FEB 2017
 */

namespace IPS\Content\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter Promotion
 */
class _Twitter extends PromoteAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'twitter';
	
	/**
	 * @brief Default settings
	 */
	public $defaultSettings = array(
		'id' => NULL,
		'owner' => NULL,
		'secret' => NULL,
		'token' => NULL,
		'name' => NULL,
		'permissions' => NULL,
		'members' => NULL,
		'tags' => NULL,
		'tags_method' => 'fill',
		'image' => NULL,
		'last_sync' => 0
	);

	/**
	 * @brief 	Twitter log in object
	 */
	protected static $twitter = NULL;
	
	/**
	 * Twitter object
	 *
	 * @return Object
	 */
	protected function twitter()
	{
		if ( static::$twitter === NULL )
		{
			static::$twitter = \IPS\Login\Handler::findMethod('IPS\Login\Handler\Oauth1\Twitter');
		}
		
		return static::$twitter;
	}
	
	/**
	 * Get image
	 *
	 * @return string
	 */
	public function getPhoto()
	{
		if ( ! $this->settings['image'] or $this->settings['last_sync'] < time() - 86400 )
		{
			$user = $this->twitter()->sendRequest( 'get', 'https://api.twitter.com/1.1/account/verify_credentials.json', array(), $this->settings['token'], $this->settings['secret'] )->decodeJson();

			/* Fetch again */
			$response = \IPS\Http\Url::external( $user['profile_image_url'] )->request()->get();
			 
			$extension = str_replace( 'image/', '', $response->httpHeaders['Content-Type'] );
			$newFile = \IPS\File::create( 'core_Promote', 'twitter_' . $this->settings['id'] . '.' . $extension, (string) $response, NULL, FALSE, NULL, FALSE );
			 
			$this->saveSettings( array( 'image' => (string) $newFile->url, 'last_sync' => time() ) );
		}
		 
		return $this->settings['image'];
	}
	
	/**
	 * Get name
	 *
	 * @param	string|NULL	$serviceId		Specific page/group ID
	 * @return string
	 */
	public function getName( $serviceId=NULL )
	{
		return $this->settings['name'];
	}
	
	/**
	 * Check publish permissions
	 *
	 * @return	boolean
	 */
	public function canPostToPage()
	{
		$twitter = \IPS\Login\Handler::findMethod('IPS\Login\Handler\Oauth1\Twitter');
		if ( $twitter === NULL )
		{
			return FALSE;
		}
		
		return $twitter->hasWritePermissions( $this->settings['token'], $this->settings['secret'] );
	}
	
	
	/**
	 * Get form elements for this share service
	 *
	 * @param	string		$text		Text for the text entry
	 * @param	string		$link		Short or full link (short when available)
	 * @param	string		$content	Additional text content (usually a comment, or the item content)
	 *
	 * @return array of form elements
	 */
	public function form( $text, $link=null, $content=null )
	{
		$textToActuallyUse = $text;
		
		if ( $link )
		{
			$textToActuallyUse .= ' ' . $link;
		}
		
		if ( mb_strlen( $textToActuallyUse ) < 280 )
		{
			/* Got any tags to add? */
			if ( \count( $this->settings['tags'] ) )
			{
				if ( $this->settings['tags_method'] === 'trim' )
				{
					$urlLength = mb_strlen( $link ) + 2; // spaces either side
					$left = 280 - $urlLength;
					$tagLength = 0;
					$tagString = '';
					$useText = $text;
					foreach( $this->settings['tags'] as $tag )
					{
						if ( $left > 0 )
						{
							$tagString .= ' #' . $tag;
							$left -= mb_strlen( $tagString );
						}
					}
					
					if ( $left > 20 )
					{
						$useText = mb_substr( $text, 0, $left );
					}
					
					$textToActuallyUse = $useText . ' '  . $link . ' ' . $tagString;
				}
				else
				{
					foreach( $this->settings['tags'] as $tag )
					{
						$considerAddingThis = ' #' . $tag;
						
						if ( mb_strlen( $textToActuallyUse . $considerAddingThis ) < 280 )
						{
							$textToActuallyUse .= $considerAddingThis;
						}
					}
				}
			}
		}
		
		$return = array();
		
		if ( $this->promote and $this->promote->id )
		{
			$textToActuallyUse = $text;
			$return[] = \IPS\Theme::i()->getTemplate( 'promote' )->promoteDialogTwitterDuplicate();
		}				
		
		$return[] = new \IPS\Helpers\Form\TextArea( 'promote_social_content_twitter', $textToActuallyUse, FALSE, array( 'maxLength' => 600, 'rows' => 3 ) );
		
		return $return;
	}
	 
	/**
	 * Post to Twitter
	 *
	 * @param	\IPS\Content\Promote	$promote 	Promote Object
	 * @return void
	 */
	public function post( $promote )
	{
		$photos = $promote->imageObjects();
		$mediaIds = array();

		if ( \is_array( $photos ) and \count( $photos ) )
		{
			try
			{
				$done = 0;
				foreach( $photos as $photo )
				{
					/* Twitter can only have a max of 4 images */
					if ( $done < 4 )
					{
						$this->response = $this->twitter()->sendMedia( $photo->contents(), $this->settings['token'], $this->settings['secret'] );

						if ( isset( $this->response['media_id_string'] ) )
						{
							$mediaIds[] = $this->response['media_id_string'];
						}
						else
						{
							\IPS\Log::log( $this->response, 'twitter' );
						}
						
						$done++;
					}
				}
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( $e->getMessage(), 'twitter' );
			}
		}

		try
		{
			$send = array( 'status' => $promote->text['twitter'] );
			
			if ( \count( $mediaIds ) )
			{
				$send['media_ids'] = implode( ',', $mediaIds );
			}

			$this->response = $this->twitter()->sendStatus( $send, $this->settings['token'], $this->settings['secret'] );
			
			if ( isset( $this->response['id_str'] ) )
			{
				return $this->response['id_str'];
			}
			else
			{
				/* Check for specific errors */
				if ( isset( $this->response['error'] ) )
				{
					\IPS\Log::log( $this->response['error'], 'twitter' );
					throw new \InvalidArgumentException( $this->response['error'] );
				}
				
				/* Check for non critical errors we don't need to flag as complete failures */
				if ( isset( $this->response['errors'] ) )
				{
					foreach( $this->response['errors'] as $error )
					{
						/* Status is a duplicate */
						if ( $error['code'] == 187 )
						{
							/* Fetch the original string if possible */
							foreach( $promote->responses('twitter') as $response )
							{
								if ( isset( $response['id_str'] ) )
								{
									return $response['id_str'];
								}
							}
							
							/* No? Ok */
							return 'Duplicate status';
						}
					}
				}

				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->get('twitter_publish_exception') );
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e->getMessage(), 'twitter' );
			
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->get('twitter_publish_exception') );
		}
	}
	
	/**
	 * Return the published URL
	 *
	 * @param	array	$data	Data returned from a successful POST
	 * @return	\IPS\Http\Url
	 * @throws InvalidArgumentException
	 */
	public function getUrl( $data )
	{
		if ( $data and preg_match( '#^[0-9_]*$#', $data ) )
		{
			return \IPS\Http\Url::external( 'https://twitter.com/' . $this->settings['id'] . '/status/' . $data );
		}
		
		throw new \InvalidArgumentException();
	}
}
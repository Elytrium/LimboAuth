<?php
/**
 * @brief		Twitter Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 June 2017
 */

namespace IPS\Login\Handler\OAuth1;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter Login Handler
 */
class _Twitter extends \IPS\Login\Handler\OAuth1
{	
	/**
	 * @brief	Share Service
	 */
	public static $shareService = 'Twitter';
	
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Twitter';
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Member::loggedIn()->language()->words['login_acp_desc'] = \IPS\Member::loggedIn()->language()->addToStack('login_acp_will_reauth');
		
		return array_merge(
			array(
				'name'				=> new \IPS\Helpers\Form\Radio( 'login_real_name', ( isset( $this->settings['name'] ) ) ? $this->settings['name'] : 'any', TRUE, array(
					'options' => array(
						'real'		=> 'login_twitter_name_real',
						'screen'	=> 'login_twitter_name_screen',
						'any'		=> 'login_real_name_disabled',
					),
					'toggles' => array(
						'real'		=> array( 'login_update_name_changes_inc_optional' ),
						'screen'	=> array( 'login_update_name_changes_inc_optional' ),
					)
				), NULL, NULL, NULL, 'login_real_name' ),
			),
			parent::acpForm(),
			array(
				'allow_status_import' => new \IPS\Helpers\Form\YesNo( 'login_generic_allow_status_import', ( isset( $this->settings['allow_status_import'] ) ) ? $this->settings['allow_status_import'] : FALSE, FALSE )
			)
		);
	}
	
	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return '#00abf0';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'twitter';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_twitter';
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_twitter';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Twitter.png', 'core', 'interface' );
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		$return = \IPS\Http\Url::external('https://api.twitter.com/oauth/authenticate');
		
		if ( $login->type === \IPS\Login::LOGIN_ACP or $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			$return = $return->setQueryString( 'force_login', 'true' );
		}
		
		if ( $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			try
			{
				$token = \IPS\Db::i()->select( array( 'token_access_token', 'token_secret' ), 'core_login_links', array( 'token_login_method=? AND token_member=?', $this->id, $login->reauthenticateAs->member_id ) )->first();
				$userDetails = $this->_userData( $token['token_access_token'], $token['token_secret'] );
				if ( isset( $userDetails['screen_name'] ) )
				{
					$return = $return->setQueryString( 'screen_name', $userDetails['screen_name'] );
				}
			}
			catch ( \Exception $e ) { }
		}
		
		return $return;
	}
	
	/**
	 * Token Request Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenRequestEndpoint()
	{
		return \IPS\Http\Url::external('https://api.twitter.com/oauth/request_token');
	}
	
	/**
	 * Access Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function accessTokenEndpoint()
	{
		return \IPS\Http\Url::external('https://api.twitter.com/oauth/access_token');
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken, $accessTokenSecret )
	{
		return $this->_userData( $accessToken, $accessTokenSecret )['id'];
	}
	
	/**
	 * Get authenticated user's username
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	string|NULL
	 */
	protected function authenticatedUserName( $accessToken, $accessTokenSecret )
	{
		if ( $this->settings['name'] == 'screen' )
		{
			return $this->_userData( $accessToken, $accessTokenSecret )['screen_name'];
		}
		elseif ( $this->settings['name'] == 'real' )
		{
			return $this->_userData( $accessToken, $accessTokenSecret )['name'];
		}
		return NULL;
	}
	
	/**
	 * Get authenticated user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	string|NULL
	 */
	protected function authenticatedEmail( $accessToken, $accessTokenSecret )
	{
		return $this->_userData( $accessToken, $accessTokenSecret )['email'];
	}

	/**
	 * Get user's profile name
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	string|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfileName( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->_userData( $link['token_access_token'], $link['token_secret'] )['screen_name'];
	}
	
	/**
	 * Get user's profile photo
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userProfilePhoto( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
				
		$userData = $this->_userData( $link['token_access_token'], $link['token_secret'] );
		if ( !$userData['default_profile_image'] )
		{
			return \IPS\Http\Url::external( str_replace( '_normal', '', $userData['profile_image_url_https'] ?: $userData['profile_image_url'] ) );
		}
		return NULL;
	}
		
	/**
	 * Get user's cover photo
	 * May return NULL if server doesn't support this
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userCoverPhoto( \IPS\Member $member )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
				
		$userData = $this->_userData( $link['token_access_token'], $link['token_secret'] );		
		if ( isset( $userData['profile_banner_url'] ) and $userData['profile_banner_url'] )
		{
			return \IPS\Http\Url::external( $userData['profile_banner_url'] );
		}
		
		return NULL;
	}
	
	/**
	 * Get user's statuses since a particular date
	 *
	 * @param	\IPS\Member			$member	Member
	 * @param	\IPS\DateTime|NULL	$since	Date/Time to get statuses since then, or NULL to get the latest one
	 * @return	array
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userStatuses( \IPS\Member $member, \IPS\DateTime $since = NULL )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
						
		$return = array();
		$response = $this->_sendRequest( 'get', \IPS\Http\Url::external('https://api.twitter.com/1.1/statuses/user_timeline.json'), $since ? array() : array( 'count' => 1 ), $link['token_access_token'], $link['token_secret'] )->decodeJson();
		foreach ( $response as $statusData )
		{
			if ( $since and strtotime( $statusData['created_at'] ) < $since->getTimestamp() )
			{
				break;
			}
			
			$status = \IPS\core\Statuses\Status::createItem( $member, $member->ip_address, new \IPS\DateTime( $statusData['created_at'] ) );
			$status->content = $this->_parseStatusText( $member, nl2br( $statusData['text'], FALSE ) );
					
			$return[] = $status;
		}
		
		return $return;
	}
	
	/**
	 * Get link to user's remote profile
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$identifier	The ID Nnumber/string from remote service
	 * @param	string	$username	The username from remote service
	 * @return	\IPS\Http\Url|NULL
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\DomainException		General error where it is safe to show a message to the user
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	public function userLink( $identifier, $username )
	{
		return \IPS\Http\Url::external( "https://twitter.com/" )->setPath( $username );
	}
	
	/**
	 * Syncing Options
	 *
	 * @param	\IPS\Member	$member			The member we're asking for (can be used to not show certain options iof the user didn't grant those scopes)
	 * @param	bool		$defaultOnly	If TRUE, only returns which options should be enabled by default for a new account
	 * @return	array
	 */
	public function syncOptions( \IPS\Member $member, $defaultOnly = FALSE )
	{
		$return = array();
		
		if ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' and isset( $this->settings['name'] ) and $this->settings['name'] != 'any' )
		{
			$return[] = 'name';
		}
		
		$return[] = 'photo';
		$return[] = 'cover';
		
		if ( \IPS\Settings::i()->profile_comments and isset( $this->settings['allow_status_import'] ) and $this->settings['allow_status_import'] )
		{
			$return[] = 'status';
		}
		
		return $return;
	}
	
	/**
	 * @brief	Cached user data
	 */
	protected $_cachedUserData = array();
	
	/**
	 * Get user data
	 *
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	array
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	protected function _userData( $accessToken, $accessTokenSecret )
	{
		if ( !isset( $this->_cachedUserData[ $accessToken ] ) )
		{
			$response = $this->_sendRequest( 'get', \IPS\Http\Url::external('https://api.twitter.com/1.1/account/verify_credentials.json'), array( 'include_email' => 'true' ), $accessToken, $accessTokenSecret )->decodeJson();
			if ( isset( $response['errors'] ) )
			{
				throw new \IPS\Login\Exception( $response['errors'][0]['message'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}


	/**
	 * @brief       The length of the shortened URLs returned by Twitter's text parser https://developer.twitter.com/en/docs/counting-characters#:~:text=The%20current%20length%20of%20a,count%20towards%20the%20character%20limit.
	 */
	protected static $defaultShortenedUrlLength = 23;

	/**
	 * Post something to Twitter
	 *
	 * @param	\IPS\Member			$member		Member posting
	 * @param	string				$content	Content to post
	 * @param	\IPS\Http\Url|NULL	$url		Optional link
	 * @return	void
	 */
	public function postToTwitter( \IPS\Member $member, $content, \IPS\Http\Url $url = NULL )
	{
		if ( !( $link = $this->_link( $member ) ) )
		{
			return FALSE;
		}
		
		$data = array( 'status' => $content );

		/* If we have a url, add it to the end prepended by a space */
		if ( $url !== NULL )
		{
			/* Try to refresh if the stored response is more than a day old */
			if ( !isset( \IPS\Data\Store::i()->twitter_config ) or \IPS\Data\Store::i()->twitter_config['time'] > time() - 86400 )
			{
				try
				{
					$response = $this->_sendRequest( 'get', \IPS\Http\Url::external('https://api.twitter.com/1.1/help/configuration.json'), array(), $link['token_access_token'], $link['token_secret'] );
					if ( ( $response->httpResponseCode === 200 ) AND ( $payload = $response->decodeJson() ) AND \is_array( $payload ) )
					{
						\IPS\Data\Store::i()->twitter_config = array_merge( $payload, array( 'time' => time() ) );
					}
					else
					{
						throw new \UnexpectedValueException();
					}
				}
				catch ( \Exception|\UnexpectedValueException $e )
				{
					/* We should be fine hard coding to 23 if the deprecated configuration endpoint is no longer online https://developer.twitter.com/en/docs/counting-characters#:~:text=The%20current%20length%20of%20a,count%20towards%20the%20character%20limit. */
					\IPS\Data\Store::i()->twitter_config['short_url_length'] = static::$defaultShortenedUrlLength;
				}
			}

			$maxUrlLen = \IPS\Data\Store::i()->twitter_config['short_url_length'] ?? static::$defaultShortenedUrlLength;
			$data['status'] = mb_substr( $data['status'], 0, ( 140 - ( $maxUrlLen + 1 ) ) ) . ' ' . $url;
		}
				
		$response = $this->_sendRequest( 'post', \IPS\Http\Url::external('https://api.twitter.com/1.1/statuses/update.json'), $data, $link['token_access_token'], $link['token_secret'] )->decodeJson();

		return isset( $response['id_str'] );
	}
	
	/* ! Social Promotes */
	
	/**
	 * Request a token
	 *
	 * @param	\IPS\Http\Url	$callback		Callback URK
	 * @return	string
	 */
	public function requestToken( $callback )
	{
		return $this->_sendRequest( 'get', $this->tokenRequestEndpoint(), array( 'oauth_callback' => (string) $callback ) )->decodeQueryString('oauth_token');
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$verifier			Verifier
	 * @param	string	$accessTokenSecret	Access Token
	 * @return	array
	 */
	public function exchangeToken( $verifier, $accessToken )
	{
		return $this->_sendRequest( 'post', $this->accessTokenEndpoint(), array( 'oauth_verifier' => $verifier ), $accessToken )->decodeQueryString('user_id');
	}
	
	/**
	 * Can we publish to this twitter account?
	 *
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	boolean
	 */
	public function hasWritePermissions( $accessToken, $accessTokenSecret )
	{
		try
		{
			$response = $this->_sendRequest( 'get', \IPS\Http\Url::external('https://api.twitter.com/1.1/account/verify_credentials.json'), array(), $accessToken, $accessTokenSecret );
			
			if ( $response->httpResponseCode == 401 )
			{
				return FALSE;
			}
			
			if ( $response->httpHeaders['x-access-level'] == 'read' )
			{
				return FALSE;
			}
			
			$response->decodeJson();
			
			return TRUE;
		}
		catch ( \IPS\Http\Request\Exception $e ) { }
		
		return FALSE;
	}
	
	/**
	 * Send a status
	 *
	 * @param	array	$data				Post data to send
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	boolean
	 */
	public function sendStatus( $data, $accessToken, $accessTokenSecret )
	{
		return $this->_sendRequest( 'post', \IPS\Http\Url::external("https://api.twitter.com/1.1/statuses/update.json"), $data, $accessToken, $accessTokenSecret )->decodeJson();
	}
	
	/**
	 * Send media
	 *
	 * @param	array	$contents			Photo contents
	 * @param	string	$accessToken		Access Token
	 * @param	string	$accessTokenSecret	Access Token Secret
	 * @return	boolean
	 */
	public function sendMedia( $contents, $accessToken, $accessTokenSecret )
	{
		$mimeBoundary = sha1( microtime() );
		
		$data = '--' . $mimeBoundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="media";' . "\r\n";
        $data .= 'Content-Type: application/octet-stream' . "\r\n" . "\r\n";
        $data .= $contents . "\r\n";
        $data .= '--' . $mimeBoundary . '--' . "\r\n" . "\r\n";
	        
		return $this->_sendRequest( 'post', \IPS\Http\Url::external('https://upload.twitter.com/1.1/media/upload.json'), array(), $accessToken, $accessTokenSecret, array(), array( $mimeBoundary, $data ) )->decodeJson();
	}
}
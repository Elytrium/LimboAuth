<?php
/**
 * @brief		Facebook Login Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		31 May 2017
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Facebook Login Handler
 */
class _Facebook extends \IPS\Login\Handler\OAuth2
{
	/**
	 * Get title
	 *
	 * @return	string
	 */
	public static function getTitle()
	{
		return 'login_handler_Facebook';
	}
	
	/**
	 * ACP Settings Form
	 *
	 * @return	array	List of settings to save - settings will be stored to core_login_methods.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		\IPS\Member::loggedIn()->language()->words['login_acp_desc'] = \IPS\Member::loggedIn()->language()->addToStack('login_acp_will_reauth');
		\IPS\Member::loggedIn()->language()->words['oauth_client_id'] = \IPS\Member::loggedIn()->language()->addToStack('login_facebook_app');
		\IPS\Member::loggedIn()->language()->words['oauth_client_client_secret'] = \IPS\Member::loggedIn()->language()->addToStack('login_facebook_secret');

		return array_merge(
			array(
				'real_name'	=> new \IPS\Helpers\Form\Radio( 'login_real_name', isset( $this->settings['real_name'] ) ? $this->settings['real_name'] : 1, FALSE, array(
					'options' => array(
						1			=> 'login_real_name_facebook',
						0			=> 'login_real_name_disabled',
					),
					'toggles' => array(
						1			=> array( 'login_update_name_changes_inc_optional' ),
					)
				), NULL, NULL, NULL, 'login_real_name' ),
			),
			parent::acpForm(),
			array(
				'allow_status_import' => new \IPS\Helpers\Form\YesNo( 'login_facebook_allow_status_import', ( isset( $this->settings['allow_status_import'] ) ) ? $this->settings['allow_status_import'] : FALSE, FALSE )
			)
		);
				
		return $return;
	}
	
	/**
	 * Test Compatibility
	 *
	 * @return	bool
	 * @throws	\LogicException
	 */
	public static function testCompatibility()
	{
		if ( mb_substr( \IPS\Settings::i()->base_url, 0, 8 ) !== 'https://' )
		{
			throw new \LogicException( \IPS\Member::loggedIn()->language()->addToStack( \IPS\CIC ? 'login_facebook_https_cic' : 'login_facebook_https' ) );
		}
		
		return TRUE;
	}

	/**
	 * Get the button color
	 *
	 * @return	string
	 */
	public function buttonColor()
	{
		return '#3a579a';
	}
	
	/**
	 * Get the button icon
	 *
	 * @return	string
	 */
	public function buttonIcon()
	{
		return 'facebook-official';
	}
	
	/**
	 * Get button text
	 *
	 * @return	string
	 */
	public function buttonText()
	{
		return 'login_facebook';
	}

	/**
	 * Get button class
	 *
	 * @return	string
	 */
	public function buttonClass()
	{
		return 'ipsSocial_facebook';
	}
	
	/**
	 * Get logo to display in information about logins with this method
	 * Returns NULL for methods where it is not necessary to indicate the method, e..g Standard
	 *
	 * @return	\IPS\Http\Url
	 */
	public function logoForDeviceInformation()
	{
		return \IPS\Theme::i()->resource( 'logos/login/Facebook.png', 'core', 'interface' );
	}
	
	/**
	 * Grant Type
	 *
	 * @return	string
	 */
	protected function grantType()
	{
		return 'authorization_code';
	}
	
	/**
	 * Get scopes to request
	 *
	 * @param	array|NULL	$additional	Any additional scopes to request
	 * @return	array
	 */
	protected function scopesToRequest( $additional=NULL )
	{
		$return = array('email');
		
		if ( \IPS\Settings::i()->profile_comments and isset( $this->settings['allow_status_import'] ) and $this->settings['allow_status_import'] )
		{
			$return[] = 'user_posts';
		}
		
		$additionalPermitted = array( 'manage_pages', 'publish_pages' );
		if ( $additional !== NULL )
		{
			foreach( $additional as $scope )
			{
				if ( \in_array( $scope, $additionalPermitted ) )
				{
					$return[] = $scope;
				}
			}
		}

		return $return;
	}
	
	/**
	 * Scopes Issued
	 *
	 * @param	string		$accessToken	Access Token
	 * @return	array|NULL
	 */
	public function scopesIssued( $accessToken )
	{
		try
		{
			$response = $this->_authorizedRequest( 'me/permissions', $accessToken, array(
				'appsecret_proof' => hash_hmac( 'sha256', $accessToken, $this->settings['client_secret'] )
			), 'get' );
		}
		catch ( \Exception $e )
		{
			return NULL;
		}
		
		$return = array();
		if ( isset( $response['data'] ) )
		{
			foreach ( $response['data'] as $perm )
			{
				if ( $perm['status'] === 'granted' )
				{
					$return[] = $perm['permission'];
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Authorization Endpoint
	 *
	 * @param	\IPS\Login	$login	The login object
	 * @return	\IPS\Http\Url
	 */
	protected function authorizationEndpoint( \IPS\Login $login )
	{
		$return = \IPS\Http\Url::external('https://www.facebook.com/dialog/oauth');
		
		if ( $login->type === \IPS\Login::LOGIN_ACP or $login->type === \IPS\Login::LOGIN_REAUTHENTICATE )
		{
			$return = $return->setQueryString( 'auth_type', 'reauthenticate' );
		}
		
		return $return;
	}
	
	/**
	 * Token Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function tokenEndpoint()
	{
		return \IPS\Http\Url::external('https://graph.facebook.com/v2.9/oauth/access_token');
	}
	
	/**
	 * Redirection Endpoint
	 *
	 * @return	\IPS\Http\Url
	 */
	protected function redirectionEndpoint()
	{
		if ( isset( $this->settings['legacy_redirect'] ) and $this->settings['legacy_redirect'] )
		{
			return \IPS\Http\Url::internal( 'applications/core/interface/facebook/auth.php', 'none' );
		}
		return parent::redirectionEndpoint();
	}
	
	/**
	 * Get authenticated user's identifier (may not be a number)
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string
	 */
	protected function authenticatedUserId( $accessToken )
	{
		return $this->_userData( $accessToken )['id'];
	}
	
	/**
	 * Get authenticated user's username
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedUserName( $accessToken )
	{
		if ( isset( $this->settings['real_name'] ) and $this->settings['real_name'] )
		{
			return $this->_userData( $accessToken )['name'];
		}
		return NULL;
	}
	
	/**
	 * Get authenticated user's email address
	 * May return NULL if server doesn't support this
	 *
	 * @param	string	$accessToken	Access Token
	 * @return	string|NULL
	 */
	protected function authenticatedEmail( $accessToken )
	{
		return $this->_userData( $accessToken )['email'];
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
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( $error['message'], \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		$photoVars = explode( ':', $member->group['g_photo_max_vars'] );		
		$response = $this->_authorizedRequest( "{$link['token_identifier']}/picture?width={$photoVars[1]}&redirect=false", $link['token_access_token'], NULL, 'get' );
		if ( !$response['data']['is_silhouette'] )
		{
			return \IPS\Http\Url::external( $response['data']['url'] );
		}
		return NULL;
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
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		return $this->_userData( $link['token_access_token'] )['name'];
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
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			throw new \IPS\Login\Exception( NULL, \IPS\Login\Exception::INTERNAL_ERROR );
		}
		
		$data = array();
		if ( $since )
		{
			$data['since'] = $since->getTimestamp();
		}
		
		$return = array();
		$response = $this->_authorizedRequest( 'me/posts', $link['token_access_token'], $data, 'get' );
		foreach ( $response['data'] as $statusData )
		{
			if( isset( $statusData['message'] ) and !isset( $statusData['story'] ) )
			{
				$status = \IPS\core\Statuses\Status::createItem( $member, $member->ip_address, new \IPS\DateTime( $statusData['created_time'] ) );
				$status->content = $this->_parseStatusText( $member, nl2br( $statusData['message'], FALSE ) );
					
				$return[] = $status;
				
				if ( !$since )
				{
					return $return;
				}
			}
		}
		
		return $return;
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
		$scopes = $this->authorizedScopes( $member );

		if ( ( !isset( $this->settings['update_email_changes'] ) or $this->settings['update_email_changes'] === 'optional' ) and ( $scopes and \in_array( 'email', $scopes ) ) )
		{
			$return[] = 'email';
		}
		
		if ( isset( $this->settings['update_name_changes'] ) and $this->settings['update_name_changes'] === 'optional' and isset( $this->settings['real_name'] ) and $this->settings['real_name'] )
		{
			$return[] = 'name';
		}
		
		$return[] = 'photo';
		
		if ( \IPS\Settings::i()->profile_comments and isset( $this->settings['allow_status_import'] ) and $this->settings['allow_status_import'] and ( $scopes and \in_array( 'user_posts', $this->authorizedScopes( $member ) ) ) )
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
	 * @param	string	$accessToken	Access Token
	 * @return	array
	 * @throws	\IPS\Login\Exception	The token is invalid and the user needs to reauthenticate
	 * @throws	\RuntimeException		Unexpected error from service
	 */
	protected function _userData( $accessToken )
	{
		if ( !isset( $this->_cachedUserData[ $accessToken ] ) )
		{
			$response = $this->_authorizedRequest( 'me', $accessToken, array(
				'fields'			=> 'email,id,name,picture',
				'appsecret_proof' 	=> hash_hmac( 'sha256', $accessToken, $this->settings['client_secret'] )
			), 'get' );
				
			if ( isset( $response['error'] ) )
			{
				throw new \IPS\Login\Exception( $response['error']['message'], \IPS\Login\Exception::INTERNAL_ERROR );
			}
				
			$this->_cachedUserData[ $accessToken ] = $response;
		}
		return $this->_cachedUserData[ $accessToken ];
	}
	
	
	/**
	 * Make authorized request
	 *
	 * @param	string			$endpoint		Endpoint
	 * @param	string			$accessToken	Access Token
	 * @param	array|NULL		$postData		Data to post or query string]
	 * @param	string|NULL		$method			'get' or 'post'
	 * @return	array
	 * @throws	\IPS\Http\Request\Exception
	 */
	protected function _authorizedRequest( $endpoint, $accessToken, $data = NULL, $method = NULL )
	{
		$url = \IPS\Http\Url::external( "https://graph.facebook.com/{$endpoint}" );
		if ( $method === 'get' and $data )
		{
			$url = $url->setQueryString( $data );
		}
		
		$request = $url->request()->setHeaders( array( 'Authorization' => "Bearer {$accessToken}" ) );
		if ( $method === 'get' or !$data )
		{
			$response = $request->get();
		}
		else
		{
			$response = $request->post( $data );
		}
		
		return $response->decodeJson();
	}
	
	/**
	 * Post something to Facebook
	 *
	 * @param	\IPS\Member			$member		Member posting
	 * @param	string				$content	Content to post
	 * @param	\IPS\Http\Url|NULL	$url		Optional link
	 * @return	void
	 */
	public function postToFacebook( \IPS\Member $member, $content, \IPS\Http\Url $url = NULL )
	{
		if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
		{
			return FALSE;
		}
		
		$data = array( 'message' => $content );
		if ( $url !== NULL )
		{
			$data['link'] = (string) $url;
		}
		
		$response = $this->_authorizedRequest( 'me/feed', $link['token_access_token'], $data );
		
		return isset( $response['id'] );
	}
	
	/* ! Social Promotion */
	
	/**
	 * Exchange a short lived member token for a longer lived token
	 *
	 * @param	string	$shortLivedToken	The short lived token to exchange for a long lived token
	 * @return array
	 */
	public function exchangeMemberToken( $code )
	{
		$accessToken = $this->_exchangeAuthorizationCodeForAccessToken( $code );
		
		/* Get user ID */
		$accessToken['identifier'] = $this->authenticatedUserId( $accessToken['access_token'] );
		
		return $accessToken;
	}
	
	/**
	 * Exchange a short lived token for a longer lived token
	 *
	 * @param	string	$shortLivedToken	The short lived token to exchange for a long lived token
	 * @return string
	 */
	public function exchangeToken( $shortLivedToken )
	{
		try
		{
			$response =  $this->_authenticatedRequest( $this->tokenEndpoint(), array(
				'grant_type'		=> 'fb_exchange_token',
				'fb_exchange_token'	=> $shortLivedToken
			) )->decodeJson();
			
			return isset( $response['access_token'] ) ? $response['access_token'] : NULL;			
		}
		catch( \RuntimeException $e )
		{
			\IPS\Log::log( $e, 'facebook' );
		}
		
		return NULL;
	}
	
	/**
	 * Get pages this user manages
	 *
	 * @param	\IPS\Member			$member		Member requesting pages
	 * @return	array
	 */
	public function getPages( $member )
	{
		$pages = array();
		if ( !( $link = $this->_promoteLink( $member ) ) )
		{
			return $pages;
		}
		
		$response = $this->_authorizedRequest( $link['token_identifier'] . '/accounts?limit=100', $link['token_access_token'], array(
			'appsecret_proof' 	=> hash_hmac( 'sha256', $link['token_access_token'], $this->settings['client_secret'] )
		), 'get' );

		if ( !empty( $response['data'] ) )
		{			
			foreach( $response['data'] as $page )
			{
				$pages[ $page['id'] ] = array( $page['name'], $page['access_token'] );
			}
		}
				
		return $pages;
	}
	
	/**
	 * Get groups this user manages
	 *
	 * @param	\IPS\Member			$member		Member requesting pages
	 * @return	array
	 */
	public function getGroups( $member )
	{
		$groups = array();
		if ( !( $link = $this->_promoteLink( $member ) ) )
		{
			return $groups;
		}

		$response = $this->_authorizedRequest( $link['token_identifier'] . '/groups', $link['token_access_token'], array(
			'appsecret_proof' 	=> hash_hmac( 'sha256', $link['token_access_token'], $this->settings['client_secret'] )
		), 'get' );

		if ( !empty( $response['data'] ) )
		{			
			foreach( $response['data'] as $group )
			{
				$groups[ $group['id'] ] = array( $group['name'], ( isset( $group['privacy'] ) ? $group['privacy'] : NULL ) );
			}
		}
				
		return $groups;
	}
	
	/**
	 * Get user link
	 *
	 * @param	\IPS\Member			$member		Member requesting pages
	 * @return	array
	 */
	protected function _promoteLink( $member )
	{
		if ( \is_numeric( \IPS\Settings::i()->promote_facebook_auth ) )
		{
			/* standard handler */
			if ( !( $link = $this->_link( $member ) ) or ( $link['token_expires'] and $link['token_expires'] < time() ) )
			{
				return array();
			}
		}
		else
		{
			$account = \IPS\core\Promote::getPromoter('Facebook')->setMember( $member );
			$this->settings = json_decode( \IPS\Settings::i()->promote_facebook_auth, TRUE );
			$link = array(
				'token_identifier' => $account->settings['member_token']['identifier'],
				'token_access_token' => $account->settings['member_token']['access_token']
			);
		}
		
		return $link;
	}
}
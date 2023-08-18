<?php
/**
 * @brief		OAuth Client
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 Apr 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * OAuth Client
 */
class _OAuthClient extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_oauth_clients';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'oauth_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'client_id';
	
	/**
	 * @brief	[Node] Enabled/Disabled Column
	 */
	public static $databaseColumnEnabledDisabled = 'enabled';
				
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'oauth_clients';
	
	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'core',
		'module'	=> 'applications',
		'prefix' 	=> 'oauth_',
	);

	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'core_oauth_client_';

	/**
	 * Set Default Values (overriding $defaultValues)
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->access_token_length = 168;
		$this->prompt = 'reauthorize';
		$this->ucp = TRUE;
		$this->use_refresh_tokens = TRUE;
		$this->refresh_token_length = 28;
	}
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->id = 'oauth';
		
		$type = 'invision';
		if ( $this->client_id )
		{
			if ( $this->type )
			{
				$type = $this->type;
			}
			else
			{
				$type = $this->client_secret ? 'confidential' : 'public';
			}
		}
				
		$form->addTab('oauth_basic_settings');
		$form->addHeader('oauth_basic_settings');
		$form->add( new \IPS\Helpers\Form\Translatable( 'oauth_client_name', NULL, TRUE, array( 'app' => 'core', 'key' => ( $this->client_id ? "core_oauth_client_{$this->client_id}" : NULL ) ) ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'oauth_client_type', $type, TRUE, array(
			'options'	=> array(
				'invision'		=> 'client_type_invision',
				'wordpress'		=> 'client_type_wordpress',
				'confidential'	=> 'client_type_confidential',
				'public'		=> 'client_type_public',
			),
			'toggles'	=> array(
				'invision'		=> array( 'oauth_grant_types_invision', 'oauth_invision_endpoint' ),
				'wordpress'		=> array( 'oauth_wordpress_endpoint' ),
				'confidential'	=> array( 'oauth_grant_types_confidential', 'oauth_redirect_uris', 'oauth_choose_scopes', 'oauth_header_oauth_access_tokens', 'oauth_access_token_length', 'oauth_tab_oauth_scopes','oauth_api_access', ),
				'public'		=> array( 'oauth_grant_types_public', 'oauth_redirect_uris', 'oauth_choose_scopes', 'oauth_header_oauth_access_tokens', 'oauth_access_token_length', 'oauth_tab_oauth_scopes', 'oauth_api_access' ),
			)
		) ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'oauth_api_access', $this->api_access ? $this->api_access : 'rest', TRUE, array(
			'options'	=> array(
				'rest'			=> 'oauth_api_type_rest',
				'graphql'		=> 'oauth_api_type_graphql',
				'both'		=> 'oauth_api_type_both',
			),
			'toggles'	=> array(
				'rest'		=> array( 'oauth_tab_oauth_scopes', 'oauth_choose_scopes' ),
				'both'		=> array( 'oauth_tab_oauth_scopes', 'oauth_choose_scopes' ),
				'graphql'		=> array( '' ),
			)
		), NULL, NULL, NULL, 'oauth_api_access' ) );

		$form->add( new \IPS\Helpers\Form\Radio( 'oauth_invision_grant_type', $this->client_id ? $this->grant_types : 'authorization_code', NULL, array(
			'options'	=> array(
				'authorization_code'	=> 'invision_grant_type_server_authorization_code',
				'password'				=> 'invision_grant_type_server_password',
			),
		), NULL, NULL, NULL, 'oauth_grant_types_invision' ) );
		$confidentialGrant = new \IPS\Helpers\Form\CheckboxSet( 'oauth_grant_types_confidential', $this->client_id ? explode( ',', $this->grant_types ) : array( 'authorization_code' ), NULL, array(
			'options'	=> array(
				'authorization_code'	=> 'grant_type_authorization_code',
				'implicit'				=> 'grant_type_implicit',
				'password'				=> 'grant_type_password',
				'client_credentials'	=> 'grant_type_client_credentials'
			),
			'toggles'	=> array(
				'authorization_code'	=> array( 'oauth_pkce', 'oauth_use_refresh_tokens' )
			)
		), function( $val ) {
			if ( !$val and \IPS\Request::i()->oauth_client_type === 'confidential' ) {
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'oauth_grant_types_confidential' );
		$confidentialGrant->label = \IPS\Member::loggedIn()->language()->addToStack('oauth_grant_types');
		$form->add( $confidentialGrant );
		$publicGrant = new \IPS\Helpers\Form\CheckboxSet( 'oauth_grant_types_public', $this->client_id ? explode( ',', $this->grant_types ) : array( 'implicit' ), NULL, array(
			'options'	=> array(
				'authorization_code'	=> 'grant_type_authorization_code',
				'implicit'				=> 'grant_type_implicit',
				'password'				=> 'grant_type_password',
			),
			'toggles'	=> array(
				'authorization_code'	=> array( 'oauth_pkce', 'oauth_use_refresh_tokens' )
			)
		), function( $val ) {
			if ( !$val and \IPS\Request::i()->oauth_client_type === 'public' ) {
				throw new \DomainException('form_required');
			}
		}, NULL, NULL, 'oauth_grant_types_public' );
		$publicGrant->label = \IPS\Member::loggedIn()->language()->addToStack('oauth_grant_types');
		$form->add( $publicGrant );
		$redirectUris = json_decode( $this->redirect_uris, TRUE );
		$form->add( new \IPS\Helpers\Form\Url( 'oauth_invision_endpoint', isset( $redirectUris[0] ) ? preg_replace( '#/oauth/callback/$#i', '/', $redirectUris[0] ) : NULL, NULL, array( 'placeholder' => 'https://othercommunity.example.com/', 'allowedProtocols' => NULL ), function( $val ) {
			if ( !$val and \IPS\Request::i()->oauth_client_type == 'invision' ) {
				throw new \DomainException('form_required');
			}
			if ( $val and $val instanceof \IPS\Http\Url and $val->data[ \IPS\Http\Url::COMPONENT_FRAGMENT ] ) {
				throw new \DomainException('oauth_redirect_uris_no_fragment');
			}
			if ( $val and rtrim( (string) $val, '/' ) === rtrim( \IPS\Settings::i()->base_url, '/' ) ) {
				throw new \DomainException('oauth_invision_endpoint_internal');
			}
		}, NULL, NULL, 'oauth_invision_endpoint' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'oauth_wordpress_endpoint', isset( $redirectUris[0] ) ? $redirectUris[0] : NULL, NULL, array( 'placeholder' => 'https://wordpress.example.com/', 'allowedProtocols' => NULL ), function( $val ) {
			if ( !$val and \IPS\Request::i()->oauth_client_type == 'wordpress' ) {
				throw new \DomainException('form_required');
			}
			if ( $val and $val instanceof \IPS\Http\Url and $val->data[ \IPS\Http\Url::COMPONENT_FRAGMENT ] ) {
				throw new \DomainException('oauth_redirect_uris_no_fragment');
			}
		}, NULL, NULL, 'oauth_wordpress_endpoint' ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'oauth_pkce', $this->pkce ?: 'none', FALSE, array( 'options' => array( 'S256' => 'oauth_pkce_256', 'plain' => 'oauth_pkce_plain', 'none' => 'oauth_pkce_none' ) ), NULL, NULL, NULL, 'oauth_pkce' ) );
		$form->add( new \IPS\Helpers\Form\Stack( 'oauth_redirect_uris', $redirectUris, NULL, array( 'stackFieldType' => 'Url', 'placeholder' => 'https://www.example.com/redirect_uri', 'allowedProtocols' => NULL ), function( $val ) {
			if ( !\in_array( \IPS\Request::i()->oauth_client_type, array('invision', 'wordpress') ) ) {
				$chosenGrantTypes = \IPS\Request::i()->oauth_client_type === 'public' ? \IPS\Request::i()->oauth_grant_types_public : \IPS\Request::i()->oauth_grant_types_confidential;
				if ( !$val and ( isset( $chosenGrantTypes['authorization_code'] ) or isset( $chosenGrantTypes['implicit'] ) ) ) {
					throw new \DomainException('form_required');
				}
				if ( $val and $val instanceof \IPS\Http\Url and $val->data[ \IPS\Http\Url::COMPONENT_FRAGMENT ] ) {
					throw new \DomainException('oauth_redirect_uris_no_fragment');
				}
			}
		}, NULL, NULL, 'oauth_redirect_uris' ) );
		
		$form->addHeader('oauth_authorization_screen');
		$form->add( new \IPS\Helpers\Form\Radio( 'oauth_prompt', $this->prompt, FALSE, array( 'options' => array( 'none' => 'oauth_prompt_none', 'automatic' => 'oauth_prompt_automatic', 'reauthorize' => 'oauth_prompt_reauthorize', 'login' => 'oauth_prompt_login' ) ) ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'oauth_choose_scopes', $this->choose_scopes, FALSE, array(), NULL, NULL, NULL, 'oauth_choose_scopes' ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'oauth_ucp', $this->ucp, FALSE ) );

		$form->addHeader('oauth_access_tokens');
		$form->add( new \IPS\Helpers\Form\Interval( 'oauth_access_token_length', $this->access_token_length, NULL, array( 'valueAs' => \IPS\Helpers\Form\Interval::HOURS, 'unlimited' => 0, 'unlimitedLang' => 'forever' ), NULL, NULL, NULL, 'oauth_access_token_length' ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'oauth_use_refresh_tokens', $this->use_refresh_tokens, NULL, array( 'togglesOn' => array( 'oauth_refresh_token_length' ) ), NULL, NULL, NULL, 'oauth_use_refresh_tokens' ) );
		$form->add( new \IPS\Helpers\Form\Interval( 'oauth_refresh_token_length', $this->refresh_token_length, NULL, array( 'valueAs' => \IPS\Helpers\Form\Interval::DAYS, 'unlimited' => 0, 'unlimitedLang' => 'forever' ), NULL, NULL, NULL, 'oauth_refresh_token_length' ) );

		$form->addTab('oauth_scopes');
		$form->addMessage('oauth_scopes_blurb');
		$matrix = new \IPS\Helpers\Form\Matrix;
		$matrix->classes[] = 'cApiPermissionsMatrix';
		$matrix->langPrefix = 'oauth_scope_';
		$matrix->columns = array(
			'name'	=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\Custom( $key, $value, FALSE, array(
					'getHtml'	=> function( $field )
					{
						return \IPS\Theme::i()->getTemplate( 'api' )->oauthScopeField( $field->name, $field->value );
					}
				) );
			},
			'endpoints'	=> function( $key, $value, $data )
			{
				return new \IPS\Helpers\Form\Custom( $key, $value ?: array(), FALSE, array(
					'getHtml'	=> function( $field )
					{
						$endpoints = \IPS\Api\Controller::getAllEndpoints();
						foreach ( $endpoints as $key => $endpoint )
						{
							$pieces = explode('/', $key);
							$endpointTree[ $pieces[0] ][ $pieces[1] ][ $key ] = $endpoint;
						}

						return \IPS\Theme::i()->getTemplate( 'api' )->permissionsFieldHtml( $endpointTree, $field->name, $field->value );
					}
				) );
			}
		);
		if ( !$this->client_id )
		{
			$matrix->rows[] = array(
				'name'		=> array( 'key' => 'profile', 'desc' => \IPS\Member::loggedIn()->language()->get('oauth_default_scope_profile') ),
				'endpoints'	=> array(
					'core/me/GETindex'	=> array( 'access' => TRUE, 'log' => FALSE ),
				)
			);
			$matrix->rows[] = array(
				'name'		=> array( 'key' => 'email', 'desc' => \IPS\Member::loggedIn()->language()->get('oauth_default_scope_email') ),
				'endpoints'	=> array(
					'core/me/GETitem'	=> array( 'access' => TRUE, 'log' => FALSE ),
				)
			);
		}
		elseif ( $this->scopes and $scopes = json_decode( $this->scopes, TRUE ) )
		{
			foreach ( $scopes as $key => $data )
			{
				$matrix->rows[] = array(
					'name'		=> array( 'key' => $key, 'desc' => $data['description'] ),
					'endpoints'	=> $data['endpoints']
				);
			}
		}
		$form->addMatrix( 'scopes', $matrix );
	}
	
	/**
	 * @brief	Temporary storage for the client secret
	 */
	public $_clientSecret;
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		/* Normalise the settings */
		$originalClientType = isset( $values['oauth_client_type'] ) ? $values['oauth_client_type'] : $this->type;

		if ( $values['oauth_client_type'] === 'invision' )
		{
			$values['oauth_client_type'] = 'confidential';
			$values['oauth_grant_types_confidential'] = array( $values['oauth_invision_grant_type'] );
			$values['oauth_redirect_uris'] = array( rtrim( $values['oauth_invision_endpoint'], '/' ) . '/oauth/callback/' );
			$values['oauth_choose_scopes'] = FALSE;
			$values['oauth_access_token_length'] = 168;
			$values['oauth_use_refresh_tokens'] = TRUE;
			$values['oauth_refresh_token_length'] = 28;
			$values['oauth_api_access'] = 'rest';
			$values['scopes'] = array(
				array(
					'name'		=> array( 'key' => 'profile', 'desc' => \IPS\Member::loggedIn()->language()->get('oauth_default_scope_profile') ),
					'endpoints'	=> array(
						'core/me/GETindex'	=> array( 'access' => TRUE, 'log' => FALSE ),
					)
				),
				array(
					'name'		=> array( 'key' => 'email', 'desc' => \IPS\Member::loggedIn()->language()->get('oauth_default_scope_email') ),
					'endpoints'	=> array(
						'core/me/GETitem'	=> array( 'access' => TRUE, 'log' => FALSE ),
					)
				)
			);
			$values['oauth_type'] = 'invision';
			$values['oauth_pkce'] = 'none';
		}
		elseif ( $values['oauth_client_type'] === 'wordpress' )
		{
			$values['oauth_client_type'] = 'confidential';
			$values['oauth_grant_types_confidential'] = array( 'authorization_code' );
			$values['oauth_redirect_uris'] = array( rtrim( $values['oauth_wordpress_endpoint'], '/' ) );
			$values['oauth_choose_scopes'] = FALSE;
			$values['oauth_access_token_length'] = 168;
			$values['oauth_use_refresh_tokens'] = TRUE;
			$values['oauth_refresh_token_length'] = 28;
			$values['oauth_api_access'] = 'rest';
			$values['scopes'] = array(
				array(
					'name'		=> array( 'key' => 'email', 'desc' => \IPS\Member::loggedIn()->language()->get('oauth_default_scope_email') ),
					'endpoints'	=> array(
						'core/me/GETindex'	=> array( 'access' => TRUE, 'log' => FALSE ),
						'core/me/GETitem'	=> array( 'access' => TRUE, 'log' => FALSE ),
					)
				)
			);
			$values['oauth_type'] = 'wordpress';
			$values['oauth_pkce'] = 'none';
		}
		elseif ( $values['oauth_api_access'] === 'graphql' )
		{
			$values['oauth_choose_scopes'] = FALSE;
			$values['scopes'] = array();
		}
		else
		{
			$values['oauth_type'] = NULL;
		}
		unset( $values['oauth_invision_grant_type'] );
		unset( $values['oauth_invision_endpoint'] );
		unset( $values['oauth_wordpress_endpoint'] );
								
		/* Generate Client ID */
		if ( !$this->client_id )
		{
			do
			{
				$values['oauth_client_id'] = \IPS\Login::generateRandomString( 32 );
			}
			while ( \IPS\Db::i()->select( 'COUNT(*)', 'core_oauth_clients', array( 'oauth_client_id=?', $values['oauth_client_id'] ) )->first() );
		}
		
		/* And secret */
		if ( $values['oauth_client_type'] === 'confidential' )
		{
			if ( !$this->client_secret )
			{
				$this->_clientSecret = \IPS\Login::generateRandomString( 48 );
				$values['oauth_client_secret'] = password_hash( $this->_clientSecret, PASSWORD_DEFAULT );
			}
			$values['oauth_grant_types'] = $values['oauth_grant_types_confidential'];
		}
		else
		{
			$values['oauth_client_secret'] = NULL;
			$values['oauth_grant_types'] = $values['oauth_grant_types_public'];
		}
		unset( $values['oauth_grant_types_confidential'] );
		unset( $values['oauth_grant_types_public'] );
		
		/* Save the name */
		$clientId = $this->client_id ?: $values['oauth_client_id'];
		\IPS\Lang::saveCustom( 'core', "core_oauth_client_{$clientId}", $values['oauth_client_name'] );
		unset( $values['oauth_client_name'] );
		
		/* Redirect URIs */
		if ( \in_array( $originalClientType, array( 'public', 'invision', 'wordpress' ) ) or \in_array( 'authorization_code', $values['oauth_grant_types'] ) or \in_array( 'implicit', $values['oauth_grant_types'] ) )
		{
			$values['oauth_redirect_uris'] = json_encode( array_map( function( $url ) { return (string) $url; }, $values['oauth_redirect_uris'] ) );
		}
		else
		{
			$values['oauth_redirect_uris'] = NULL;
		}
		unset( $values['oauth_client_type'] );
		
		/* Scopes */
		$scopes = array();
		foreach ( $values['scopes'] as $row )
		{
			if ( $row['name']['key'] )
			{
				$scopes[ $row['name']['key'] ] = array(
					'description'	=> isset( $row['name']['desc'] ) ? $row['name']['desc'] : NULL,
					'endpoints'		=> $row['endpoints']
				);
			}
		}
		$values['oauth_scopes'] = json_encode( $scopes );
		unset( $values['scopes'] );
		
		/* Return */
		return $values;
	}

	/**
	 * Return the custom badge for each row
	 *
	 * @return	NULL|array		Null for no badge, or an array of badge data (0 => CSS class type, 1 => language string, 2 => optional raw HTML to show instead of language string)
	 */
	public function get__badge()
	{
		return array(
			0	=> 'ipsBadge ipsBadge_neutral ipsPos_right ipsMargin_right:half',
			1	=> 'api_access_' . $this->api_access,
		);
	}
	
	/**
	 * [Node] Get Description
	 *
	 * @return	string|null
	 */
	protected function get__description()
	{
		return $this->client_id;
	}
	
	/**
	 * [Node] Does the currently logged in user have permission to copy this node?
	 *
	 * @return	bool
	 */
	public function canCopy()
	{
		return FALSE;
	}
	
	/**
	 * [Node] Get buttons to display in tree
	 * Example code explains return value
	 *
	 * @code
	array(
	array(
	'icon'	=>	'plus-circle', // Name of FontAwesome icon to use
	'title'	=> 'foo',		// Language key to use for button's title parameter
	'link'	=> \IPS\Http\Url::internal( 'app=foo...' )	// URI to link to
	'class'	=> 'modalLink'	// CSS Class to use on link (Optional)
	),
	...							// Additional buttons
	);
	 * @endcode
	 * @param	string	$url		Base URL
	 * @param	bool	$subnode	Is this a subnode?
	 * @return	array
	 */
	public function getButtons( $url, $subnode=FALSE )
	{
		$buttons = array();
		
		$buttons['view'] = array(
			'icon'	=> 'search',
			'title'	=> 'oauth_view_client',
			'link'	=> $url->setQueryString( array( 'do' => 'view', 'client_id' => $this->client_id ) )
		);
		
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'oauth_tokens' ) )
		{
			$buttons['tokens'] = array(
				'icon'	=> 'key',
				'title'	=> 'oauth_view_authorizations',
				'link'	=> $url->setQueryString( array( 'do' => 'tokens', 'client_id' => $this->client_id ) )
			);
		}
		
		$_parentButtons = parent::getButtons( $url, $subnode );		
		if ( isset( $_parentButtons['delete'] ) )
		{
			$_parentButtons['delete']['data'] = array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->addToStack('oauth_client_delete_warning') );
		}
				
		return $buttons + $_parentButtons;
	}
	
	/**
	 * Generate or renew an access token
	 *
	 * @param	\IPS\Member|NULL			$member				The member or NULL for client_credentials
	 * @param	array|null				$scopes				Array of scopes or NULL if none were requested
	 * @param	string					$grantType			Type of grant
	 * @param	bool						$skipRefreshToken	If TRUE, will not generate a refresh token (for example, when using implicit grant type)
	 * @param	string|NULL				$authorizationCode	The authorization code which generated the token, if applicable
	 * @param	string|NULL				$userAgent			The user agent that the user performed authentication on, if known
	 * @param	string|NULL				$issueUserAgent		The user agent that the access token was issued to, if known
	 * @param	\IPS\Member\Device|NULL	$device				The device used to obtain this access token, if known
	 * @param	array					$tokenToRefresh		If we are refreshing, the existing access token to refresh
	 * @return	array
	 */
	public function generateAccessToken( ?\IPS\Member $member, $scopes, $grantType, $skipRefreshToken = FALSE, $authorizationCode = NULL, $authUserAgent = NULL, $grantUserAgent = NULL, \IPS\Member\Device $device = NULL, $tokenToRefresh = NULL )
	{
		do
		{
			$accessToken = \IPS\Login::generateRandomString( 64 );
		}
		while ( $this->validateAccessToken( $accessToken ) );
		
		$data = array(
			'client_id'				=> $this->client_id,
			'member_id'				=> $member ? $member->member_id : NULL,
			'access_token'			=> $accessToken,
			'access_token_expires'	=> $this->access_token_length ? ( time() + ( $this->access_token_length * 3600 ) ) : NULL,
			'refresh_token'			=> NULL,
			'refresh_token_expires'	=> $this->refresh_token_length ? ( time() + ( $this->refresh_token_length * 86400 ) ) : NULL,
			'scope'					=> $scopes ? json_encode( $scopes ) : NULL,
			'authorization_code'		=> $authorizationCode,
			'issued'					=> time(),
			'auth_user_agent'		=> $authUserAgent,
			'issue_user_agent'		=> $grantUserAgent,
			'device_key'				=> $device ? $device->device_key : NULL,
			'status'				=> 'active',
		);
		
		if ( $this->use_refresh_tokens )
		{
			if ( !$skipRefreshToken )
			{
				do
				{
					$data['refresh_token'] = \IPS\Login::generateRandomString( 64 );
				}
				while ( $this->validateRefreshToken( $data['refresh_token'] ) );
				
				if ( $this->refresh_token_length )
				{
					$data['refresh_token_expires'] = time() + ( $this->refresh_token_length * 86400 );
				}
				else
				{
					$data['refresh_token_expires'] = NULL;
				}
			}
		}
		
		if ( $grantType === 'refresh_token' and $tokenToRefresh )
		{
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'client_id=? AND access_token=?', $tokenToRefresh['client_id'], $tokenToRefresh['access_token'] ) );
			\IPS\Db::i()->insert( 'core_oauth_server_access_tokens', $data );
		}
		else
		{		
			\IPS\Db::i()->insert( 'core_oauth_server_access_tokens', $data );
			if ( $member )
			{
				$member->logHistory( 'core', 'oauth', array( 'type' => 'issued_access_token', 'client' => $this->client_id, 'grant' => $grantType, 'scopes' => $scopes ), FALSE );
			}
		}
		
		if ( $device )
		{
			$device->last_seen = time();
			$device->save();
			$device->logIpAddress( \IPS\Request::i()->ipAddress() );
		}
		
		$data['access_token'] = $this->client_id . '_' . $data['access_token'];
		
		return $data;
	}
	
	/**
	 * Get access token details, checking it isn't expired
	 *
	 * @param	string	$accessToken	The access token
	 * @return	\IPS\Member|NULL
	 * @throws	\UnderflowException
	 * @throws	\IPS\Api\Exception
	 */
	public static function accessTokenDetails( $accessToken )
	{
		$exploded = explode( '_', $accessToken );
		
		if ( !isset( $exploded[0] ) or !isset( $exploded[1] ) )
		{
			throw new \UnderflowException;
		}
		
		$return = \IPS\Db::i()->select( '*', 'core_oauth_server_access_tokens', array( 'client_id=? AND access_token=?', $exploded[0], $exploded[1] ) )->first();
		if ( $return['status'] == 'revoked' )
		{
			throw new \IPS\Api\Exception( 'REVOKED_ACCESS_TOKEN', '1S290/F', 401, 'invalid_token' );
		}
		if ( $return['access_token_expires'] and $return['access_token_expires'] < time() )
		{
			throw new \IPS\Api\Exception( 'EXPIRED_ACCESS_TOKEN', '1S290/E', 401, 'invalid_token' );
		}
		
		return $return;
	}
	
	/**
	 * Validate an access token
	 *
	 * @param	string	$accessToken	The access token
	 * @return	\IPS\Member|NULL
	 */
	public function validateAccessToken( $accessToken )
	{
		try
		{
			$row = \IPS\Db::i()->select( array( 'member_id', 'access_token_expires' ), 'core_oauth_server_access_tokens', array( 'client_id=? AND access_token=?', $this->client_id, $accessToken ) )->first();
			if ( $row['status'] == 'revoked' )
			{
				return;
			}
			if ( $row['access_token_expires'] and $row['access_token_expires'] < time() )
			{
				return;
			}
			
			$member = \IPS\Member::load( $row['member_id'] );
			if ( $member->member_id )
			{
				return $member;
			}
		}
		catch ( \UnderflowException $e ) { }
	}
	
	/**
	 * Get an existing access token with particular scopes, if they exist
	 *
	 * @param	\IPS\Member|NULL	$member		The member or NULL for client_credentials
	 * @param	array				$scopes		The scopes
	 * @return	array|NULL
	 */
	public function getAccessToken( \IPS\Member $member = NULL, $scopes = array() )
	{
		foreach ( \IPS\Db::i()->select( '*', 'core_oauth_server_access_tokens', array( 'client_id=? AND member_id=?', $this->client_id, $member->member_id ) ) as $row )
		{
			if ( $row['status'] == 'revoked' )
			{
				continue;
			}
			if ( $this->use_refresh_tokens )
			{
				if ( $row['refresh_token_expires'] and $row['refresh_token_expires'] < time() )
				{
					continue;
				}
			}
			else
			{
				if ( $row['access_token_expires'] and $row['access_token_expires'] < time() )
				{
					continue;
				}
			}
			
			if ( \count( array_diff( $scopes, $row['scope'] ? json_decode( $row['scope'] ) : array() ) ) )
			{
				continue;
			}
		
			return $row;
		}
	}
	
	/**
	 * Validate a refresh token
	 *
	 * @param	string	$refreshToken	The refresh token
	 * @return	array|NULL
	 */
	public function validateRefreshToken( $refreshToken )
	{
		try
		{
			$row = \IPS\Db::i()->select( '*', 'core_oauth_server_access_tokens', array( 'client_id=? AND refresh_token=?', $this->client_id, $refreshToken ) )->first();
			if ( $row['status'] == 'revoked' )
			{
				return;
			}
			if ( $row['refresh_token_expires'] and $row['refresh_token_expires'] < time() )
			{
				return;
			}
			
			return $row;
		}
		catch ( \UnderflowException $e ) { }
	}
	
	/**
	 * Check if authorized scopes can access a particular endpoint (returns the accessing scope or NULL)
	 *
	 * @param	array	$authorizedScopes	The scopes the user has access to
	 * @param	string	$app				Application key
	 * @param	string	$controller			Controller
	 * @param	string	$method				Method
	 * @return	string|NULL
	 */
	public function scopesCanAccess( $authorizedScopes, $app, $controller, $method )
	{
		$scopes = $this->scopes ? json_decode( $this->scopes, TRUE ) : array();
		
		foreach ( $authorizedScopes as $scope )
		{
			if ( isset( $scopes[ $scope ] ) )
			{				
				if ( isset( $scopes[ $scope ]['endpoints']["{$app}/{$controller}/{$method}"] ) and $scopes[ $scope ]['endpoints']["{$app}/{$controller}/{$method}"]['access'] == TRUE )
				{
					return $scope;
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * Check if scope should log access to a particular endpoint
	 *
	 * @param	string	$scope				The scope
	 * @param	string	$app				Application key
	 * @param	string	$controller			Controller
	 * @param	string	$method				Method
	 * @return	bool
	 */
	public function scopeShouldLog( $scope, $app, $controller, $method )
	{
		$scopes = $this->scopes ? json_decode( $this->scopes, TRUE ) : array();
		return isset( $scopes[ $scope ]['endpoints']["{$app}/{$controller}/{$method}"] ) and isset( $scopes[ $scope ]['endpoints']["{$app}/{$controller}/{$method}"]['log'] ) and $scopes[ $scope ]['endpoints']["{$app}/{$controller}/{$method}"]['log'] == TRUE;
	}
	
	/**
	 * [ActiveRecord] Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Db::i()->delete( 'core_oauth_server_access_tokens', array( 'client_id=?', $this->client_id ) );
		\IPS\Db::i()->delete( 'core_oauth_server_authorization_codes', array( 'client_id=?', $this->client_id ) );
		
		return parent::delete();
	}
}
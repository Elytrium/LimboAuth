<?php
/**
 * @brief		OAuth Clients
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		29 Apr 2017
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\applications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * OAuth Clients
 */
class _oauth extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\Api\OAuthClient';
	
	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'oauth_manage' );
		return parent::execute();
	}
	
	/**
	 * View List (checks endpoints are available on https)
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\OAUTH_REQUIRES_HTTPS and mb_substr( \IPS\Settings::i()->base_url, 0, 8 ) !== 'https://' )
		{
			try
			{
				$response = \IPS\Http\Url::external( 'https://' . mb_substr( \IPS\Settings::i()->base_url, 7 ) )->request()->get();
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms' )->blurb( \IPS\CIC ? 'oauth_https_warning_cic' : 'oauth_https_warning', TRUE, TRUE );
				return;
			}
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( 'oauth_clients_blurb', TRUE, TRUE );
		return parent::manage();
	}
	
	/**
	 * View Client Details
	 *
	 * @return	void
	 */
	protected function view()
	{
		try
		{
			$client = \IPS\Api\OAuthClient::load( \IPS\Request::i()->client_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C362/1', 404, '' );
		}
		
		if ( $client->type === 'mobile' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=mobile&controller=mobile" ) );
		}
		
		$secret = NULL;
		if ( isset( \IPS\Request::i()->newSecret ) and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'applications', 'oauth_secrets' ) )
		{
			\IPS\Session::i()->csrfCheck();
			
			$secret = \IPS\Login::generateRandomString( 48 );
			$client->client_secret = password_hash( $secret, PASSWORD_DEFAULT );
			$client->brute_force = NULL;
			$client->save();
			
			\IPS\Session::i()->log( 'acplogs__oauth_new_secret', array( 'core_oauth_client_' . $client->client_id => TRUE ) );
			\IPS\Output::i()->bypassCsrfKeyCheck = TRUE;
		}
		
		\IPS\Output::i()->sidebar['actions'] = $client->getButtons( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$client->client_id}" ) );
		unset( \IPS\Output::i()->sidebar['actions']['view'] );
		
		$bruteForce = NULL;
		if ( $client->brute_force and $bruteForce = json_decode( $client->brute_force, TRUE ) )
		{
			$data = array();
			foreach ( $bruteForce as $ipAddress => $fails )
			{
				$data[] = array(
					'ip_address'	=> $ipAddress,
					'fails'			=> $fails
				);
			}
			
			$bruteForce = new \IPS\Helpers\Table\Custom( $data, \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$client->client_id}" ) );
			$bruteForce->langPrefix = 'oauth_brute_force_';
			$bruteForce->rowButtons = function( $row ) use ( $client ) {
				$return = array();
				$return['ban'] = array(
					'icon'	=> 'ban',
					'title'	=> 'oauth_brute_force_ban',
					'link'	=>  \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=bfRemove&ban=1&client_id={$client->client_id}" )->setQueryString( 'ip', $row['ip_address'] )->csrf()
				);
				if ( $row['fails'] >= 3 )
				{
					$return['unlock'] = array(
						'icon'	=> 'unlock',
						'title'	=> 'oauth_brute_force_unlock',
						'link'	=>  \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=bfRemove&client_id={$client->client_id}" )->setQueryString( 'ip', $row['ip_address'] )->csrf()
					);
				}
				return $return;
			};
		}
		
		\IPS\Output::i()->title = $client->_title;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('api')->oauthSecret( $client, $secret, $bruteForce );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=api&tab=oauth" ), 'oauth_clients' );
		\IPS\Output::i()->breadcrumb[] = array( NULL, $client->_title );
	}
	
	/**
	 * Remove IP Address from bruteforce
	 *
	 * @return	void
	 */
	protected function bfRemove()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$client = \IPS\Api\OAuthClient::load( \IPS\Request::i()->client_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C362/4', 404, '' );
		}
		
		if ( \IPS\Request::i()->ip and \IPS\Request::i()->ban )
		{
			\IPS\Db::i()->insert( 'core_banfilters', array(
				'ban_type'		=> 'ip',
				'ban_content'	=> \IPS\Request::i()->ip,
				'ban_date'		=> time(),
				'ban_reason'	=> 'OAuth',
			) );
			unset( \IPS\Data\Store::i()->bannedIpAddresses );
			\IPS\Session::i()->log( 'acplog__ban_created', array( 'ban_filter_ip_select' => TRUE, \IPS\Request::i()->ip => FALSE ) );
		}
		else
		{
			\IPS\Session::i()->log( 'acplogs__oauth_unlock_ip', array( \IPS\Request::i()->ip => FALSE, 'core_oauth_client_' . $client->client_id => TRUE ) );
		}
		
		$bruteForce = json_decode( $client->brute_force, TRUE );
		unset( $bruteForce[ \IPS\Request::i()->ip ] );
		$client->brute_force = json_encode( $bruteForce );
		$client->save();
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$client->client_id}" ) );
	}
	
	/**
	 * View Authorizations
	 *
	 * @return	void
	 */
	protected function tokens()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'oauth_tokens' );
		
		$client = NULL;
		$member = NULL;
		try
		{
			if ( isset( \IPS\Request::i()->client_id ) )
			{
				$client = \IPS\Api\OAuthClient::load( \IPS\Request::i()->client_id );
				$baseUrl = \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens&client_id={$client->client_id}" );
			}
			else
			{
				$member = \IPS\Member::load( \IPS\Request::i()->member_id );
				if ( !$member->member_id )
				{
					throw new \OutOfRangeException;
				}
				$baseUrl = \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens&member_id={$member->member_id}" );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C362/3', 404, '' );
		}
		
		if ( $client )
		{
			$columns = array(
				'access_token_expires'	=> (bool) $client->access_token_length,
				'refresh_token_expires'	=> (bool) ( $client->use_refresh_tokens and $client->refresh_token_length ),
				'scope'					=> (bool) ( $client->scopes and json_decode( $client->scopes ) ),
				'auth_user_agent'		=> ( \in_array( 'authorization_code', explode( ',', $client->grant_types ) ) or \in_array( 'implicit', explode( ',', $client->grant_types ) ) ),
				'issue_user_agent'		=> ( \in_array( 'authorization_code', explode( ',', $client->grant_types ) ) or \in_array( 'password', explode( ',', $client->grant_types ) ) or \in_array( 'client_credentials', explode( ',', $client->grant_types ) ) ),
			);
		}
		else
		{
			$columns = array(
				'access_token_expires'	=> FALSE,
				'refresh_token_expires'	=> FALSE,
				'scope'					=> FALSE,
				'auth_user_agent'		=> FALSE,
				'issue_user_agent'		=> FALSE,
			);
			
			$count = 0;
			foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_oauth_clients', array( \IPS\Db::i()->findInSet( 'oauth_grant_types', array( 'authorization_code', 'implicit', 'password' ) ) ) ), 'IPS\Api\OAuthClient' ) as $_client )
			{
				$count++;
				if ( $_client->access_token_length )
				{
					$columns['access_token_expires'] = TRUE;
				}
				if ( $_client->use_refresh_tokens and $_client->refresh_token_length )
				{
					$columns['refresh_token_expires'] = TRUE;
				}
				if ( $_client->scopes and json_decode( $_client->scopes ) )
				{
					$columns['scope'] = TRUE;
				}
				if ( \in_array( 'authorization_code', explode( ',', $_client->grant_types ) ) or \in_array( 'implicit', explode( ',', $_client->grant_types ) ) )
				{
					$columns['auth_user_agent'] = TRUE;
				}
				if ( \in_array( 'authorization_code', explode( ',', $_client->grant_types ) ) or \in_array( 'password', explode( ',', $_client->grant_types ) ) or \in_array( 'client_credentials', explode( ',', $_client->grant_types ) ) )
				{
					$columns['issue_user_agent'] = TRUE;
				}
			}
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_oauth_server_access_tokens', $baseUrl, array( $client ? array( 'client_id=?', $client->client_id ) : array( 'member_id=?', $member->member_id ) ) );
		$table->langPrefix = 'oauth_authorization_';
		$table->include = array();
		$table->advancedSearch = array();
		if ( $client )
		{
			$table->include[] = 'member_id';
			$table->advancedSearch['member_id'] = \IPS\Helpers\Table\SEARCH_MEMBER;
		}
		elseif ( $count > 1 )
		{
			$table->include[] = 'client_id';
		}
		$table->include[] = 'issued';
		$table->include[] = 'status';
		$table->advancedSearch['issued'] = \IPS\Helpers\Table\SEARCH_DATE_RANGE;
		if ( $columns['access_token_expires'] )
		{
			$table->include[] = 'access_token_expires';
			$table->advancedSearch['access_token_expires'] = \IPS\Helpers\Table\SEARCH_DATE_RANGE;
		}
		if ( $columns['refresh_token_expires'] )
		{
			$table->include[] = 'refresh_token_expires';
			$table->advancedSearch['refresh_token_expires'] = \IPS\Helpers\Table\SEARCH_DATE_RANGE;
		}
		if ( $columns['scope'] )
		{
			$table->include[] = 'scope';
		}
		if ( $columns['auth_user_agent'] )
		{
			$table->include[] = 'auth_user_agent';
		}
		if ( $columns['issue_user_agent'] )
		{
			$table->include[] = 'issue_user_agent';
		}
		$table->noSort = array( 'status', 'scope' );
		$table->sortBy = $table->sortBy ?: 'issued';
		$table->parsers = array(
			'client_id'		=> function( $val ) {
				try
				{
					$client = \IPS\Api\OAuthClient::load( $val );
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$client->client_id}" ), FALSE, $client->_title, FALSE );
				}
				catch ( \Exception $e )
				{
					return '';
				}
			},
			'member_id'		=> function( $val ) {
				if ( $val )
				{
					$member = \IPS\Member::load( $val );
					if ( $member->member_id )
					{
						return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( $member, 'tiny' ) . \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( $member, 'tiny' );
					}
					else
					{
						return \IPS\Member::loggedIn()->language()->addToStack('deleted_member');
					}
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('oauth_client_credentials');
				}
			},
			'issued'		=> function( $val ) {
				return \IPS\DateTime::ts( $val );
			},
			'access_token_expires' => function( $val ) {
				if ( $val )
				{
					return \IPS\DateTime::ts( $val );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('never');
				}
			},
			'refresh_token_expires' => function( $val, $row ) {
				if ( \IPS\Api\OAuthClient::load( $row['client_id'] )->use_refresh_tokens )
				{
					if ( $val )
					{
						return \IPS\DateTime::ts( $val );
					}
					else
					{
						return \IPS\Member::loggedIn()->language()->addToStack('never');
					}
				}
				else
				{
					return '';
				}
			},
			'status'		=> function( $val, $row ) {
				return \IPS\Theme::i()->getTemplate('api')->oauthStatus( $row, \IPS\Api\OAuthClient::load( $row['client_id'] )->use_refresh_tokens );
			},
			'scope'		=> function( $val ) {
				if ( $val )
				{
					return implode( '<br>', json_decode( $val ) );
				}
				else
				{
					return '';
				}
			},
			'auth_user_agent' => function( $val, $row )
			{
				if ( $row['device_key'] )
				{
					try
					{
						$device = \IPS\Member\Device::load( $row['device_key'] );
						return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal( "app=core&module=members&controller=devices&do=device&key={$row['device_key']}&member={$row['member_id']}" ), FALSE, (string) \IPS\Http\UserAgent::parse( $val ), FALSE );
					}
					catch ( \OutOfRangeException $e ) { }
				}
				return (string) \IPS\Http\UserAgent::parse( $val );
			},
			'issue_user_agent' => function( $val )
			{
				return  \IPS\Theme::i()->getTemplate( 'api', 'core', 'admin' )->clientDetails( $val );

			}
		);
		$table->rowButtons = function( $row ) use ( $client ) {
			$return = [];
			if( $row['status'] == 'active')
			{
				$return['revoke'] = array(
					'icon' => 'times-circle',
					'title' => 'oauth_app_revoke',
					'link' => \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=revokeToken&client_id={$row['client_id']}&member_id={$row['member_id']}&token={$row['access_token']}" )->setQueryString( 'r', $client ? 'c' : 'm' )->csrf(),
					'data' => array( 'confirm' => '', 'confirmMessage' => \IPS\Member::loggedIn()->language()->addToStack( 'oauth_app_revoke_title' ) )
				);
			}
			return $return;
		};
		$revokeAllLink = $client ? \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=revokeAllTokens&client_id={$client->client_id}" )->csrf() : \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=revokeAllTokens&member_id={$member->member_id}" )->csrf();
		$table->rootButtons = array(
			'revoke'	=> array(
				'icon'		=> 'times-circle',
				'title'		=> 'oauth_revoke_all_tokens',
				'link'		=> $revokeAllLink,
				'data'		=> array( 'confirm' => '' )
			)
		);
		
		\IPS\Output::i()->output = $table;
		if ( $client )
		{
			\IPS\Output::i()->title = $client->_title;
			if ( $client->type !== 'mobile' )
			{
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=api&tab=oauth" ), 'oauth_clients' );
			}
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$client->client_id}" ), $client->_title );
			\IPS\Output::i()->breadcrumb[] = array( NULL, 'oauth_view_authorizations' );
		}
		else
		{
			\IPS\Output::i()->title = $member->name;
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$member->member_id}" ), $member->name );
			
			if ( $count > 1 )
			{
				\IPS\Output::i()->breadcrumb[] = array( NULL, 'oauth_member_authorizations' );
			}
			else
			{
				foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_oauth_clients', array( \IPS\Db::i()->findInSet( 'oauth_grant_types', array( 'authorization_code', 'implicit', 'password' ) ) ) ), 'IPS\Api\OAuthClient' ) as $_client )
				{
					\IPS\Output::i()->breadcrumb[] = array( NULL, $_client->_title );
					break;
				}
			}
		}
	}
	
	
	/**
	 * Revoke Authorizations
	 *
	 * @return	void
	 */
	protected function revokeToken()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'oauth_tokens' );
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'client_id=? AND access_token=?', \IPS\Request::i()->client_id, \IPS\Request::i()->token ) );
		\IPS\Session::i()->log( 'acplogs__oauth_revoke_token', array( 'core_oauth_client_' . \IPS\Request::i()->client_id => TRUE ) );
		
		if ( \IPS\Request::i()->r === 'c' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens" )->setQueryString( 'client_id', \IPS\Request::i()->client_id ) );
		}
		elseif ( \IPS\Request::i()->r === 'p' )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view" )->setQueryString( 'id', \IPS\Request::i()->member_id ) );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens" )->setQueryString( 'member_id', \IPS\Request::i()->member_id ) );
		}
	}
	
	/**
	 * Revoke ALL Authorizations
	 *
	 * @return	void
	 */
	protected function revokeAllTokens()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'oauth_tokens' );
		\IPS\Session::i()->csrfCheck();
		
		if ( \IPS\Request::i()->member_id )
		{
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'member_id=?', \IPS\Request::i()->member_id ) );
			\IPS\Session::i()->log( 'acplogs__oauth_revoke_member', array( \IPS\Member::load( \IPS\Request::i()->member_id )->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens" )->setQueryString( 'member_id', \IPS\Request::i()->member_id ) );
		}
		else
		{					
			\IPS\Db::i()->update( 'core_oauth_server_access_tokens', array( 'status' => 'revoked' ), array( 'client_id=?', \IPS\Request::i()->client_id ) );
			\IPS\Session::i()->log( 'acplogs__oauth_revoke_client', array( 'core_oauth_client_' . \IPS\Request::i()->client_id => TRUE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=tokens" )->setQueryString( 'client_id', \IPS\Request::i()->client_id ) );
		}
	}

	protected function form()
	{
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'system/api.css', 'core', 'admin' ) );
		return parent::form();
	}
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		if ( $new->_clientSecret )
		{
			\IPS\Output::i()->title = $new->_title;
			\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( "app=core&module=applications&controller=api&tab=oauth" ), 'oauth_clients' );
			\IPS\Output::i()->breadcrumb[] = array( NULL, $new->_title );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('api')->oauthSecret( $new, $new->_clientSecret, NULL );
			\IPS\Output::i()->sidebar['actions'] = $new->getButtons( \IPS\Http\Url::internal( "app=core&module=applications&controller=oauth&do=view&client_id={$new->client_id}" ) );
			unset( \IPS\Output::i()->sidebar['actions']['view'] );
		}
		else
		{
			return parent::_afterSave( $old, $new, $lastUsedTab );
		}
	}
}
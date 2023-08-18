<?php
/**
 * @brief		Open ID Connect Handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		9 Jun 2020
 */

namespace IPS\Login\Handler\OAuth2;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Open ID Connect
 */
abstract class _OpenID extends \IPS\Login\Handler\OAuth2
{	
	/**
	 * @brief	ID Token
	 */
	protected $_idToken = array();
	
	/**
	 * Process an Access Token
	 *
	 * @param	\IPS\Login	$login			The login object
	 * @param	array		$accessToken	Access Token
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	protected function _processAccessToken( \IPS\Login $login, $accessToken )
	{
		/* Set up ID token */
		if( isset( $accessToken['id_token'] ) )
		{
			$this->_idToken[ $accessToken['access_token'] ] = $accessToken['id_token'];
		}
		
		try
		{
			$member = parent::_processAccessToken( $login, $accessToken );
		
			/* Store the id token */
			if( $this->_idToken[ $accessToken['access_token'] ] )
			{			
				\IPS\Db::i()->update( 'core_login_links', array( 'token_id_token' => $this->_idToken[ $accessToken['access_token'] ] ), array( 'token_login_method=? and token_member=?', $this->id, $member->member_id ) );
			}

			return $member;
		}
		catch ( \Exception $exception )
		{
			if ( $exception->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
			{
				\IPS\Db::i()->update( 'core_login_links', array( 'token_id_token' => $this->_idToken[ $accessToken['access_token'] ] ), array( 'token_login_method=? and token_member=?', $this->id, $exception->member->member_id ) );
			}
			
			throw $exception;
		}
	}
	
	/**
	 * Retrieve the ID Token
	 *
	 * @param	string		$accessToken	 Access Token
	 * @return	string		
	 */
	protected function _getIdToken( $accessToken )
	{
		if( !isset( $this->_idToken[ $accessToken ] ) or !$this->_idToken[ $accessToken ] )
		{
			$this->_idToken[ $accessToken ] = \IPS\Db::i()->select( 'token_id_token', 'core_login_links', array( 'token_login_method=? and token_access_token=?', $this->id, $accessToken ) )->first();
		}
		
		return $this->_idToken[ $accessToken ];
	}
}
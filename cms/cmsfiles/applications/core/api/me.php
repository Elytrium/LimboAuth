<?php
/**
 * @brief		Me API
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - 2016 Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Community Suite
 * @since		3 Dec 2015
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\api;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Me API
 */
class _me extends \IPS\Api\Controller
{
	/**
	 * GET /core/me
	 * Get basic information about the authorized user
	 *
	 * @apimemberonly
	 * @return	\IPS\Member
	 */
	public function GETindex()
	{
		$output = $this->member->apiOutput( $this->member );
		
		if ( $this->canAccess( 'core', 'me', 'GETitem' ) )
		{
			$output['email'] 		= $this->member->email;
		}
		
		$output['lastVisit'] 	= $this->member->last_visit ? \IPS\DateTime::ts( $this->member->last_visit )->rfc3339() : NULL;
		$output['lastPost'] 	= $this->member->member_last_post ? \IPS\DateTime::ts( $this->member->member_last_post )->rfc3339() : NULL;
		
		return new \IPS\Api\Response( 200, $output );
	}
	
	/**
	 * GET /core/me/email
	 * Get authorized user's email address
	 *
	 * @apimemberonly
	 * @param		string		$path			Requested path
	 * @return	array
	 * @apiresponse	string	email	Email address
	 */
	public function GETitem( $path )
	{
		return new \IPS\Api\Response( 200, array( 'email' => $this->member->email ) );
	}
}
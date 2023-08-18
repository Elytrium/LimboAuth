<?php
/**
 * @brief		Poll Question
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		4 Dec 2015
 */

namespace IPS\Poll;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Poll Question
 */
class _Question
{
	/**
	 * @brief	Data
	 */
	protected $data;
	
	/**
	 * Constructor
	 *
	 * @param	array	$data	Data
	 * @return	void
	 */
	public function __construct( $data )
	{
		$this->data = $data;
	}
	
	/**
	 * Get output for API
	 *
	 * @param	\IPS\Member|NULL	$authorizedMember	The member making the API request or NULL for API Key / client_credentials
	 * @return	array
	 * @apiresponse	string	question	The question
	 * @apiresponse	object	options		Each of the options and how many votes they have had
	 */
	public function apiOutput( \IPS\Member $authorizedMember = NULL )
	{
		return array(
			'question'	=> $this->data['question'],
			'options'	=> array_combine( $this->data['choice'], $this->data['votes'] )
		);
	}
}
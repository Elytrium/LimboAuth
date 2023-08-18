<?php
/**
 * @brief		OAuth Exception Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Apr 2017
 */

namespace IPS\Login\Handler\OAuth2;

/**
 * OAuth2 Exception
 */
class _Exception extends \Exception
{
	/**
	 * @brief	Description of the error
	 */
	public $description;
	
	/**
	 * Constructor
	 *
	 * @param	string		$message		Exception Message
	 * @param	string|NULL	$description	Error Description or NULL.
	 * @return	void
	 */
	public function __construct( $message, $description = null )
	{
		parent::__construct( $message );
		$this->description = $description;
	}
}
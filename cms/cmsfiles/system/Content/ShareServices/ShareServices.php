<?php
/**
 * @brief		Share Services Abstract Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Aug 2018
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Share Services Abstract Class
 */
abstract class _ShareServices
{
	/**
	 * Get services
	 *
	 * @return	array
	 */
	public static function services()
	{
		return array(
			'Facebook'	=>	'IPS\Content\ShareServices\Facebook',
			'Twitter'	=>	'IPS\Content\ShareServices\Twitter',
			'Linkedin'	=>	'IPS\Content\ShareServices\Linkedin',
			'Pinterest'	=>	'IPS\Content\ShareServices\Pinterest',
			'Reddit'	=>	'IPS\Content\ShareServices\Reddit',
			'Email'	=>	'IPS\Content\ShareServices\Email',
		);
	}

	/**
	 * @brief	Cached services
	 */
	static $services = NULL;

	/**
	 * Helper method to get the class based on the key
	 *
	 * @param	string	$key	Service to load
	 * @return	mixed
	 * @throws	\InvalidArgumentException
	 */
	public static function getClassByKey( $key )
	{
		if ( static::$services == NULL )
		{
			static::$services = static::services();
		}

		if ( !isset( static::$services[ ucwords($key) ] ) )
		{
			throw new \InvalidArgumentException;
		}
		return static::$services[ ucwords($key) ];
	}
	
	/**
	 * Determine whether the logged in user has the ability to autoshare
	 *
	 * @return	boolean
	 */
	public static function canAutoshare()
	{
		return FALSE;
	}

	/**
	 * Add any additional form elements to the configuration form. These must be setting keys that the service configuration form can save as a setting.
	 *
	 * @param	\IPS\Helpers\Form				$form		Configuration form for this service
	 * @param	\IPS\core\ShareLinks\Service	$service	The service
	 * @return	void
	 */
	public static function modifyForm( \IPS\Helpers\Form &$form, $service )
	{
		// Do nothing by default
	}
	
	/**
	 * @brief	URL to the content item
	 */
	protected $url = NULL;
	
	/**
	 * @brief	Title of the content item
	 */
	protected $title = NULL;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Http\Url	$url	URL to the content [optional - if omitted, some services will figure out on their own]
	 * @param	string			$title	Default text for the content, usually the title [optional - if omitted, some services will figure out on their own]
	 * @return	void
	 */
	public function __construct( \IPS\Http\Url $url=NULL, $title=NULL )
	{
		$this->url		= $url;
		$this->title	= $title;
	}

	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	abstract public function __toString();
}
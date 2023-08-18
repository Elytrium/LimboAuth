<?php
/**
 * @brief		Promote Abstract Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jun 2013
 */

namespace IPS\Content\Promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Promote Handler
 */
abstract class _PromoteAbstract extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Caches
	 * @note	Defined cache keys will be cleared automatically as needed
	 */
	protected $caches = array( 'promoters' );
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_social_promote_sharers';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'sharer_';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'key';
	
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'key';

	/**
	 * @brief	[Node] Automatically set position for new nodes
	 */
	public static $automaticPositionDetermination = FALSE;
	
	/**
	 * @brief Default settings
	 */
	public $defaultSettings = array();
	
	/**
	 * @brief The last response sent back
	 */
	public $response = NULL;
	
	/**
	 * @brief \IPS\core\Promote object
	 */
	public $promote = NULL;
		
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
	{
		/* Initiate an object */
		$classname = '\\IPS\Content\\Promote\\' . mb_ucfirst( $data['sharer_key'] );
		
		if ( !class_exists( $classname ) )
		{
			throw new \RuntimeException;
		}
		 
		$obj = new $classname;
		$obj->_new = FALSE;
		
		/* Import data */
		$databasePrefixLength = \strlen( static::$databasePrefix );
		foreach ( $data as $k => $v )
		{
			if( static::$databasePrefix )
			{
				$k = \substr( $k, $databasePrefixLength );
			}
			
			$obj->$k = $v;
		}
		$obj->changed = array();
			
		/* Return */
		return $obj;
	}
	
	/**
	 * @brief	Member
	 */
	protected $member;
	
	/**
	 * Constructor
	 *
	 * \IPS\Member	$member	The member
	 * @returns $this for daisy chaining
	 */
	public function setMember( \IPS\Member $member )
	{
		$this->member = $member;
		
		return $this;
	}
	
	/**
	 * Returns the lowercase key
	 *
	 * @returns string
	 */
	public function get__key()
	{
		return mb_strtolower( $this->key );
	}
	
	/**
	 * Get settings
	 *
	 * @return	array
	 */
	public function get_settings()
	{
		$settings = json_decode( $this->_data['settings'], TRUE );
		return ( $settings ? array_merge( $this->defaultSettings, $settings ) : $this->defaultSettings );
	}
	
	/**
	 * Can a member promote using this service?
	 *
	 * @return	bool
	 */
	public function canPromote()
	{	
		return $this->enabled and \IPS\core\Promote::canPromote( $this->member );
	}
	
	/**
	 * Get image
	 *
	 * @return string
	 */
	abstract public function getPhoto();
	
	/**
	 * Get name
	 *
	 * @param	string|NULL	$serviceId		Specific page/group ID
	 * @return string
	 */
	abstract public function getName( $serviceId=NULL );
	
	/**
	 * Return the published URL
	 *
	 * @param	array	$data	Data returned from a successful POST
	 * @return	\IPS\Http\Url
	 * @throws InvalidArgumentException
	 */
	abstract function getUrl( $data );
	
	/**
	 * Allow for any extra processing
	 *
	 * @param	array	$values	Values from the form isn't it though
	 * @return	mixed
	 */
	public function processPromoteForm( $values )
	{
		return NULL;
	}
	
	/**
	 * Bulk save settings
	 *
	 * @param	array	$settings	Array of (k => v) settings
	 * @return void
	 */
	public function saveSettings( $settings )
	{
		$this->settings = json_encode( array_merge( $this->settings, $settings ) );
		$this->save();
	}
}
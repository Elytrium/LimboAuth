<?php
/**
 * @brief		Front Navigation Extension: Custom Item
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		21 Jan 2015
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Custom Item
 */
class _YourActivityStreamsItem extends \IPS\core\FrontNavigation\FrontNavigationAbstract
{
	/**
	 * @brief	The ID number
	 */
	public	$id;
	
	/**
	 * @brief	The stream ID
	 */
	protected	$streamId;
	
	/**
	 * Constructor
	 *
	 * @param	array	$configuration	The configuration
	 * @param	int		$id				The ID number
	 * @param	string	$permissions	The permissions (* or comma-delimited list of groups)
	 * @return	void
	 */
	public function __construct( $configuration, $id, $permissions )
	{
		parent::__construct( $configuration, $id, $permissions );
		
		if ( \count( $configuration ) and isset( $configuration['menu_stream_id'] ) )
		{
			$this->streamId = $configuration['menu_stream_id'];
		}
		else
		{
			$this->streamId = $id;
		}
	}
	
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('activity_stream_single');
	}
	
	/**
	 * Can access?
	 *
	 * @return	bool
	 */
	public function canAccessContent()
	{
		if ( ! \IPS\Member::loggedIn()->member_id and $this->streamId and $this->streamId <= 5 )
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Allow multiple instances?
	 *
	 * @return	bool
	 */
	public static function allowMultiple()
	{
		return TRUE;
	}
	
	/**
	 * Get configuration fields
	 *
	 * @param	array	$existingConfiguration	The existing configuration, if editing an existing item
	 * @param	int		$id						The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function configuration( $existingConfiguration, $id = NULL )
	{
		$globalStreams = array();
		foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_streams', '`member` IS NULL' ), 'IPS\core\Stream' ) as $stream )
		{
			$globalStreams[ $stream->id ] = $stream->_title;
		}
				
		return array(
			new \IPS\Helpers\Form\Select( 'menu_stream_id', isset( $existingConfiguration['menu_stream_id'] ) ? $existingConfiguration['menu_stream_id'] : NULL, NULL, array( 'options' => $globalStreams ), NULL, NULL, NULL, 'menu_stream_id' ),
			new \IPS\Helpers\Form\Radio( 'menu_title_type', isset( $existingConfiguration['menu_title_type'] ) ? $existingConfiguration['menu_title_type'] : 0, NULL, array( 'options' => array( 0 => 'menu_title_type_stream', 1 => 'menu_title_type_custom' ), 'toggles' => array( 1 => array( 'menu_stream_title' ) ) ), NULL, NULL, NULL, 'menu_title_type' ),
			new \IPS\Helpers\Form\Translatable( 'menu_stream_title', NULL, NULL, array( 'app' => 'core', 'key' => $id ? "menu_stream_title_{$id}" : NULL ), NULL, NULL, NULL, 'menu_stream_title' ),
		);
	}
	
	/**
	 * Parse configuration fields
	 *
	 * @param	array	$configuration	The values received from the form
	 * @param	int		$id				The ID number of the existing item, if editing
	 * @return	array
	 */
	public static function parseConfiguration( $configuration, $id )
	{
		if ( $configuration['menu_title_type'] )
		{
			\IPS\Lang::saveCustom( 'core', "menu_stream_title_{$id}", $configuration['menu_stream_title'] );
		}
		else
		{
			\IPS\Lang::deleteCustom( 'core', "menu_stream_title_{$id}" );
		}
		
		unset( $configuration['menu_stream_title'] );
		
		return $configuration;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		if ( ! empty( $this->configuration['title'] ) )
		{
			return $this->configuration['title'];
		}
		else if ( isset( $this->configuration['menu_title_type'] ) and $this->configuration['menu_title_type'] )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( "menu_stream_title_{$this->id}" );
		}
		
		return \IPS\Member::loggedIn()->language()->addToStack( "stream_title_{$this->streamId}" );
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		switch ( $this->streamId )
		{
			case 1:
				$furlKey = 'discover_unread';
				break;
			case 2:
				$furlKey = 'discover_istarted';
				break;
			case 3:
				$furlKey = 'discover_followed';
				break;
			case 4:
				$furlKey = 'discover_following';
				break;
			case 5:
				$furlKey = 'discover_posted';
				break;
			default:
				$furlKey = 'discover_stream';
				break;
		}
		
		return \IPS\Http\Url::internal( "app=core&module=discover&controller=streams&id={$this->streamId}", 'front', $furlKey );
	}

	/**
	 * Get Attributes
	 *
	 * @return	string
	 */
	public function attributes()
	{
		return "data-streamid='{$this->id}'";
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return \IPS\Dispatcher::i()->application->directory === 'core' and \IPS\Dispatcher::i()->module->key === 'discover' and \IPS\Request::i()->id == $this->streamId;
	}
	
	/**
	 * Children
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function children( $noStore=FALSE )
	{
		return NULL;
	}
}
<?php
/**
 * @brief		Front Navigation Extension: Your Activity Streams
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Jun 2015
 */

namespace IPS\core\extensions\core\FrontNavigation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Front Navigation Extension: Your Activity Streams
 */
class _YourActivityStreams extends \IPS\core\FrontNavigation\FrontNavigationAbstract
{
	/**
	 * Get Type Title which will display in the AdminCP Menu Manager
	 *
	 * @return	string
	 */
	public static function typeTitle()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('your_activity_streams_acp');
	}
		
	/**
	 * Can the currently logged in user access the content this item links to?
	 *
	 * @return	bool
	 */
	public function canAccessContent()
	{
		return \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'discover' ) ) and \count( $this->children() );
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack('your_activity_streams');
	}
	
	/**
	 * Get Link
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return NULL;
	}
	
	/**
	 * Is Active?
	 *
	 * @return	bool
	 */
	public function active()
	{
		return ( \IPS\Dispatcher::i()->application->directory === 'core' and \IPS\Dispatcher::i()->module->key === 'discover' and ( isset( \IPS\Request::i()->id ) or ( isset( \IPS\Request::i()->do ) and \IPS\Request::i()->do == 'create' ) ) );
	}
	
	/**
	 * Cached Children
	 *
	 * @return	array
	 */
	protected static $children = array();
	
	/**
	 * Items
	 *
	 * @param	\IPS\Member|null	$member	Member or NULL for currently logged in member
	 * @return	array
	 */
	public static function items( \IPS\Member $member = NULL )
	{	
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !isset( static::$children[ $member->member_id ] ) )
		{
			static::$children[ $member->member_id ] = array();
			
			if ( !isset( \IPS\Data\Store::i()->globalStreamIds ) )
			{
				$globalStreamIds = array();
				foreach ( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_streams', '`member` IS NULL', 'position ASC' ), 'IPS\core\Stream' ) as $stream )
				{
					$globalStreamIds[ $stream->id ] = ( $stream->ownership == 'all' and $stream->read == 'all' and $stream->follow == 'all' and $stream->date_type != 'last_visit' );
				}
				
				\IPS\Data\Store::i()->globalStreamIds = $globalStreamIds;
			}
					
			$globalStreamIdsToShow = array_keys( !$member->member_id ? array_filter( \IPS\Data\Store::i()->globalStreamIds ) : \IPS\Data\Store::i()->globalStreamIds );
			
			if ( \count( $globalStreamIdsToShow ) )
			{
				if ( $member->member_id )
				{
					static::$children[ $member->member_id ][] = new MenuHeader('default_streams');
				}
				
				foreach ( $globalStreamIdsToShow as $id )
				{
					static::$children[ $member->member_id ][] = new YourActivityStreamsItem( array(), $id, '*' );
				}
			}
			
			if ( $member->member_id )
			{
				if ( $member->member_streams and $streams = json_decode( $member->member_streams, TRUE ) and \count( $streams['streams'] ) )
				{
					static::$children[ $member->member_id ][] = new MenuHeader('custom_streams');
					foreach ( $streams['streams'] as $id => $title )
					{
						static::$children[ $member->member_id ][] = new YourActivityStreamsItem( array( 'title' => $title ), $id, '*' );
					}
				}
				
				static::$children[ $member->member_id ][] = new MenuSeparator;
				static::$children[ $member->member_id ][] = new MenuButton( 'create_new_stream', \IPS\Http\Url::internal( "app=core&module=discover&controller=streams&do=create", 'front', 'discover_all' ) );
			}
		}
		
		return static::$children[ $member->member_id ];
	}
	
	/**
	 * Children
	 *
	 * @param	bool	$noStore	If true, will skip datastore and get from DB (used for ACP preview)
	 * @return	array
	 */
	public function children( $noStore=FALSE )
	{	
		return static::items();		
	}
}
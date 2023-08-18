<?php
/**
 * @brief		Views Trait
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Views Trait
 */
trait ViewUpdates
{
	/**
	 * Update View Count
	 *
	 * @return	void
	 */
	public function updateViews()
	{
		$idColumn = static::$databaseColumnId;
		$class = \get_called_class();
		
		$countUpdated = false;
		if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
		{
			try
			{
				\IPS\Redis::i()->zIncrBy( 'topic_views', 1, $class .'__' . $this->$idColumn );
				$countUpdated = true;
			}
			catch( \Exception $e ) {}
		}
		
		if ( ! $countUpdated )
		{
			\IPS\Db::i()->insert( 'core_view_updates', array(
					'classname'	=> $class,
					'id'		=> $this->$idColumn
			) );
		}
	}
}
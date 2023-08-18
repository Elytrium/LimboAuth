<?php
/**
 * @brief		Storage Class for sessions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		12 September 2017
 */

namespace IPS\Session;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Session Handler
 */
abstract class _Store
{
	/**
	 * @brief	Just return a count
	 */
	const ONLINE_COUNT_ONLY = 1;
	
	/**
	 * @brief	Return members
	 */
	const ONLINE_MEMBERS = 2;
	
	/**
	 * @brief	Return guests
	 */
	const ONLINE_GUESTS = 4;
	
	/**
	 * @brief	Instance
	 */
	protected static $instance = NULL;
	
	/**
	 * Returns the engine object
	 *
	 * @return	class
	 */
	public static function i()
	{ 
		if ( static::$instance === NULL )
		{
			if ( \IPS\REDIS_ENABLED and \IPS\CACHE_METHOD == 'Redis' and ( \IPS\CACHE_CONFIG or \IPS\REDIS_CONFIG ) )
			{ 
				try
				{
					/* Try and use Redis */
					$connection = \IPS\Redis::i()->connection('read');
					
					if( !$connection )
					{
						throw new \RuntimeException;
					}
					
					/* No exceptions means it worked */
					static::$instance = new \IPS\Session\Store\Redis;
				}
				catch( \Exception $e )
				{
					/* Something went wrong, so fall back */
					static::$instance = new \IPS\Session\Store\Database;
				}
			}
			else
			{
				static::$instance = new \IPS\Session\Store\Database;
			}	
		}

		return static::$instance;
	}
	
	/**
	 * Load the session from the storage engine 
	 *
	 * @param	string	$sessionId	Session ID
	 * @return	array
	 */
	abstract public function loadSession( $sessionId );
	
	/**
	 * Update the session storage engine
	 *
	 * @param	array	$data	Session data to store
	 * @return void
	 */
	abstract public function updateSession( $data );

	/**
	 * Delete from the session engine
	 *
	 * @param	string	$sessionId	Session ID
	 * @return	void
	 */
	abstract public function deleteSession( $sessionId ); 
	
	/**
	 * Delete from the session engine
	 *
	 * @param	int			$memberId	You can probably figure this out right?
	 * @param	string|NULL	$userAgent	User Agent [optional]
	 * @param	array|NULL	$keepSessionIds	Array of session ids to keep [optional]
	 * @return	void
	 */
	abstract public function deleteByMember( int $memberId, string $userAgent=NULL, array $keepSessionIds=NULL );
	
	/**
	 * Delete from the session engine
	 *
	 * @param	int		$memberId	You can probably figure this out right?
	 * @return	array|FALSE
	 */
	abstract public function getLatestMemberSession( $memberId );
	
	/**
	 * Fetch all online users (but not spiders)
	 *
	 * @param	int			$flags				Bitwise flags	
	 * @param	string		$sort				Sort direction
	 * @param	array|NULL	$limit				Limit [ offset, limit ]
	 * @param	int			$memberGroup		Limit by a specific member group ID
	 * @param	boolean		$showAnonymous		Show anonymously logged in peoples?	
	 * @return array
	 */
	abstract public function getOnlineUsers( $flags=0, $sort='desc', $limit=NULL, $memberGroup=NULL, $showAnonymous=FALSE );
	
	/**
	 * Fetch all members active at a specific location
	 *
	 * @param	string	$app		Application directory (core, forums, etc)
	 * @param	string	$module		Module
	 * @param	string	$controller Controller
	 * @param	int		$id			Current item ID (empty if none)
	 * @param	string	$url		Current viewing URL
	 * @return array
	 */
	abstract public function getOnlineMembersByLocation( $app, $module, $controller, $id, $url );
	
	/**
	 * Clear sessions - abstracted so it can be called externally without initiating a session
	 *
	 * @param	int		$timeout	Sessions older than the number of seconds provided will be deleted
	 * @return void
	 */
	public static function clearSessions( $timeout )
	{
		/* Session engines can overload this */
	}
}
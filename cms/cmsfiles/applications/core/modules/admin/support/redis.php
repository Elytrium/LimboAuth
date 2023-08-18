<?php
/**
 * @brief		Redis Info
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		19 Oct 2018
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Redis info
 */
class _redis extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Not accessible for CIC */
		if( \IPS\CIC )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C394/3', 403, '' );
		}

		\IPS\Dispatcher::i()->checkAcpPermission( 'redis_data' );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=support&controller=support' ), \IPS\Member::loggedIn()->language()->addToStack('support') );
		parent::execute();
	}

	/**
	 * Info screen
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$redis = NULL;
		$info  = NULL;
		$datasource  = array();
		
		if ( \IPS\CACHE_METHOD == 'Redis' or \IPS\STORE_METHOD == 'Redis' )
		{
			$info = \IPS\Redis::i()->info();
			
			$data = array( 'type' => 'redis_datastore', 'count' => NULL, 'enabled' => false );
			
			if ( \IPS\STORE_METHOD == 'Redis' )
			{
				$data['enabled'] = true;
				
				/* Lets ensure we have in the cache */
				$data['count'] = \count( \IPS\Redis::i()->debugGetKeys( \IPS\Redis::i()->get( 'redisKey_store' ) . '_str_*', TRUE ) );
			}
			
			$datasource[] = $data;
			
			$data = array( 'type' => 'redis_cache', 'count' => NULL, 'enabled' => false );
			
			if ( \IPS\CACHE_METHOD == 'Redis' )
			{
				$data['enabled'] = true;
				
				/* Lets ensure we have something */
				$data['count'] = \count( \IPS\Redis::i()->debugGetKeys( \IPS\Redis::i()->get( 'redisKey' ) . '_*', TRUE ) );
			}
			
			$datasource[] = $data;
			
			/* And now sessions */
			if ( \IPS\CACHE_METHOD == 'Redis' and \IPS\REDIS_ENABLED )
			{
				$datasource[] = array( 'type' => 'redis_sessions', 'count' => \count( \IPS\Redis::i()->debugGetKeys( 'session_id_*', TRUE ) ), 'enabled' => true );
				$datasource[] = array( 'type' => 'redis_topic_views', 'count' => \IPS\Redis::i()->zCard('topic_views'), 'enabled' => true );
				$datasource[] = array( 'type' => 'redis_advert_impressions', 'count' => \IPS\Redis::i()->zCard('advert_impressions'), 'enabled' => true );
			}
			else
			{
				$datasource[] = array( 'type' => 'redis_sessions'   , 'count' => NULL, 'enabled' => false );
				$datasource[] = array( 'type' => 'redis_topic_views', 'count' => NULL, 'enabled' => false );
				$datasource[] = array( 'type' => 'redis_advert_impressions', 'count' => NULL, 'enabled' => false );
			}
		}
		
		/* Not using redis then are we? */
		if ( $info === NULL )
		{
			\IPS\Output::i()->error( 'redis_not_enabled', '2C394/2', 403, '' );
		}
		
		$table = new \IPS\Helpers\Table\Custom( $datasource, \IPS\Http\Url::internal( 'app=core&module=support&controller=redis' ) );
		$table->langPrefix = 'redis_table_';
		
		/* Custom parsers */
		$table->parsers = array(
            'type'    => function( $val, $row )
            {
                return \IPS\Member::loggedIn()->language()->addToStack( $val );
            },
            'count'    => function( $val, $row )
            {
                return \intval( $val );
            },
			'enabled' => function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'support' )->redisEnabledBadge( $val );
			}
		);
		
		\IPS\Output::i()->sidebar['actions']['settings'] = array(
			'icon'	=> 'cog',
			'link'	=> \IPS\Http\Url::internal( '&app=core&module=settings&controller=advanced&tab=datastore' ),
			'title'	=> 'redis_settings',
		);	
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('redis_info');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->redis( $info, $table );
	}
}
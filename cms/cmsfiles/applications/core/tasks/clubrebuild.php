<?php
/**
 * @brief		clubrebuild Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		02 Mar 2017
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * clubrebuild Task
 */
class _clubrebuild extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		if ( !\IPS\Settings::i()->clubs )
		{
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 0 ), array( '`key`=?', 'clubrebuild' ) );
			return NULL;
		}
		
		$this->runUntilTimeout( function()
		{
			$select = \IPS\Db::i()->select( '*', 'core_clubs', array( 'rebuilt is null or rebuilt<?', time() - 1200 ), 'rebuilt ASC', 10 );

			if ( !$select->count() )
			{
				return FALSE;
			}
			
			foreach ( new \IPS\Patterns\ActiveRecordIterator( $select, 'IPS\Member\Club' ) as $club )
			{
				$club->content = 0;
				$club->last_activity = 0;
				
				foreach ( $club->nodes() as $node )
				{
					try
					{
						$nodeClass = $node['node_class'];
						$node = $nodeClass::load( $node['node_id'] );
						
						if ( $lastCommentTime = $node->getLastCommentTime( new \IPS\Member ) and $lastCommentTime->getTimestamp() > $club->last_activity )
						{
							$club->last_activity = $lastCommentTime->getTimestamp();
						}
						
						$club->content += (int) $node->getContentItemCount();
					}
					catch ( \Exception $e ) { }
				}
				
				$club->rebuilt = time();
				$club->save();
			}
			
			return TRUE;
		} );
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}
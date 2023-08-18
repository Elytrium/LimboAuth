<?php
/**
 * @brief		Background Task: Repair File URLs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		28 May 2014
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Repair File URLs
 */
class _RepairFileUrls
{
	
	/**
	 * @brief Number of files to fix per cycle
	 */
	public $batch = \IPS\REBUILD_QUICK;
	
	/**
	 * Run Background Task
	 *
	 * @param	mixed						$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int							$offset	Offset
	 * @return	int							New offset
	 * @throws	\IPS\Task\Queue\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$exploded	= explode( '_', $data['storageExtension'] );		
		$classname	= "IPS\\{$exploded[2]}\\extensions\\core\\FileStorage\\{$exploded[3]}";
		$offset		= $offset ?: 0;
		
        if ( !class_exists( $classname ) or !\IPS\Application::appIsEnabled( $exploded[2] ) or !method_exists( $classname, 'fixUrls' ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$extension = new $classname;

		for ( $i = 0; $i < $this->batch; $i++ )
		{
			try
			{
				$return = $extension->fixUrls( $offset );
				
				$offset++;
				
				/* Did we return a new offset? */
				if ( \is_numeric( $return ) )
				{
					$offset = $return;
				}
			} 
			catch ( \UnderflowException $e )
			{
				throw new \IPS\Task\Queue\OutOfRangeException;
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'repair_files_bgtask' );
				continue;
			}
		}

		if( !$offset )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return $offset;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaining task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('updating_storage_urls', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $data['storageExtension'] ) ) ) ), 'complete' => $data['count'] ? round( ( 100 / $data['count'] * $offset ), 2 ) : 100 );
	}	
}
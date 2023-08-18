<?php
/**
 * @brief		Background Task: Rebuild database categories
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		13 Jun 2014
 */

namespace IPS\cms\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild database categories
 */
class _RebuildCategories
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_NORMAL;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname  = $data['class'];
		$databaseId = mb_substr( $classname, 18 );
		
		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'rebuildCategories' );

		try
		{
			$data['count'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'cms_database_categories' )->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}
		
		return $data;
	}

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
		$classname  = $data['class'];
		$databaseId = mb_substr( $classname, 18 );
		
		$class  = '\IPS\cms\Categories' . $databaseId;
		$parsed	= 0;
		
		foreach ( \IPS\Db::i()->select( '*', 'cms_database_categories', array( 'category_database_id=?', $databaseId ), 'category_id asc', array( $offset, $this->rebuild ) ) as $row )
		{
			try
			{
				$cat = $class::constructFromData( $row );
				$cat->setLastComment();
				$cat->setLastReview();
				$cat->save();
				
				$parsed++;
			}
			catch( \Exception $e ){}
		}

		if( $parsed !== $this->rebuild )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		return ( $offset + $this->rebuild );
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
		$classname  = $data['class'];
		$databaseId = mb_substr( $classname, 18 );
		
		$title = ( \IPS\Application::appIsEnabled('cms') ) ? \IPS\cms\Databases::load( $databaseId )->_title : 'Database #' . $databaseId;
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack( 'rebuilding_cms_database_categories', FALSE, array( 'sprintf' => array( $title ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}	
}
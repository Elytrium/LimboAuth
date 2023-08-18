<?php
/**
 * @brief		Background Task: Rebuild database records
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
 * Background Task: Rebuild database records
 */
class _RebuildRecords
{
	/**
	 * @brief Number of content items to rebuild per cycle
	 */
	public $rebuild	= \IPS\REBUILD_SLOW;

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname  = $data['class'];
		$databaseId = mb_substr( $classname, 15 );
		
		\IPS\Log::debug( "Getting preQueueData for " . $classname, 'rebuildRecords' );

		try
		{
			$data['count'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'cms_custom_database_' . $databaseId )->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}

		$data['completed'] = 0;
		
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
	public function run( &$data, $offset )
	{
		$classname	= $data['class'];
		$databaseId	= mb_substr( $classname, 15 );
		$last		= NULL;

		/* Make sure there's even content to parse */
		if( !class_exists( $classname ) or !isset( $classname::$databaseColumnMap['content'] ) )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}
		
		$fixImage = isset( $data['fixImage'] ) ? (boolean) $data['fixImage'] : TRUE;
		$fixHtml  = isset( $data['fixHtml'] ) ? (boolean) $data['fixHtml'] : FALSE;
		$fixFurls = isset( $data['fixFurls']) ? (boolean) $data['fixFurls'] : FALSE;
		$class  = '\IPS\cms\Records' . $databaseId;
		
		if ( \IPS\Db::i()->checkForTable( 'cms_custom_database_' . $databaseId ) )
		{
			foreach ( \IPS\Db::i()->select( '*', 'cms_custom_database_' . $databaseId, array( 'primary_id_field > ?', $offset ), 'primary_id_field asc', array( 0, $this->rebuild ) ) as $row )
			{
				$record = $class::constructFromData( $row );
				$record->resetLastComment();
				$save = FALSE;
				
				if ( $fixImage )
				{
					if ( $record->record_image and file_exists( \IPS\ROOT_PATH . '/uploads/' . $record->record_image ) )
					{
						try
						{
							$record->record_image = (string) \IPS\File::create( 'cms_Records', $record->record_image, file_get_contents( \IPS\ROOT_PATH . '/uploads/' . $record->record_image ) );
						}
						catch ( \Exception $e )
						{
							$record->record_image = NULL;
						}
						$save = TRUE;
					}
				}
				
				if ( ! $record->record_publish_date )
				{
					$record->record_publish_date = $record->record_saved;
					$save = TRUE;
				}
				
				if ( $fixFurls and preg_match( '#%([\d\w]{2})#', $record->record_static_furl ) )
				{
					$record->record_static_furl = urldecode( $record->record_static_furl );
					$save = TRUE;
				}
				
				if ( $save )
				{
					$record->save();
				}
				
				if ( $fixHtml )
				{
					$fields = iterator_to_array( \IPS\Db::i()->select( '*', 'cms_database_fields', array( 'field_database_id=? AND field_type=? AND field_html=1', $databaseId, 'Editor' ) ) );
					
					if ( \count( $fields ) )
					{
						foreach( $fields as $field )
						{
							$column = 'field_' . $field['field_id'];
							
							if ( $record->member_id and $record->$column )
							{
								try
								{
									$author = \IPS\Member::load( $record->member_id );
									
									/* In 3.x this would have been shown as HTML */
									if ( $author->group['g_dohtml'] )
									{
										/* This code is copied from IPB3 to ensure it is compatible with data saved */
										$record->$column = str_replace( "&#39;" , "'", $record->$column );
										$record->$column = str_replace( "&#33;" , "!", $record->$column );
										$record->$column = str_replace( "&#036;", "$", $record->$column );
										$record->$column = str_replace( "&#124;", "|", $record->$column );
										$record->$column = str_replace( "&amp;" , "&", $record->$column );
										$record->$column = str_replace( "&gt;"	 , ">", $record->$column );
										$record->$column = str_replace( "&lt;"	 , "<", $record->$column );
										$record->$column = str_replace( "&#60;" , "<", $record->$column );
										$record->$column = str_replace( "&#62;" , ">", $record->$column );
										$record->$column = str_replace( "&quot;", '"', $record->$column );
										$record->$column = str_replace( '&quot;', '"', $record->$column );
										$record->$column = str_replace( '&lt;', '<', $record->$column );
										$record->$column = str_replace( '&gt;', '>', $record->$column );
										
										$record->save();
									}
								}
								catch( \OutOfRangeException $e ) { }
							}		
						}
					}
				}

				$data['completed']++;
				$last = $record->primary_id_field;
			}
		}
		
		if( $last === NULL )
		{
			throw new \IPS\Task\Queue\OutOfRangeException;
		}

		return $last;
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
		$databaseId = mb_substr( $classname, 15 );
		
		$title = ( \IPS\Application::appIsEnabled('cms') ) ? \IPS\cms\Databases::load( $databaseId )->_title : 'Database #' . $databaseId;
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('rebuilding_cms_database_records', FALSE, array( 'sprintf' => array( $title ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $data['completed'], 2 ) ) : 100 );
	}	
}
<?php
/**
 * @brief		Background Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blogs
 * @since		12 Apr 2016
 */

namespace IPS\blog\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task
 */
class _RebuildEntryImages
{
	/**
	 * @brief   Number of entry images to rebuild per cycle
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
		$data['count'] = (int) \IPS\Db::i()->select( 'count(*)', 'blog_entries', "entry_image<>''" )->first();

		if( $data['count'] == 0 )
		{
			return NULL;
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
		$select = \IPS\Db::i()->select( '*', 'blog_entries', array( "entry_image<>? AND entry_id > ?", '', $offset ), 'entry_id ASC', array( 0, $this->rebuild ) );
		$last	= NULL;

		foreach ( $select as $entry )
		{
			try
			{
				$filePath = \IPS\ROOT_PATH . '/uploads/' . $entry['entry_image'];

				if ( ! file_exists( $filePath ) )
				{
					continue;
				}
				
				/* Have we processed this before? It's a bit crude but it is accurate */
				if ( mb_substr( trim( $entry['entry_content'] ), 0, 37 ) === '<a href="<fileStore.core_Attachment>/' and mb_stristr( $entry['entry_content'], '/' . $entry['entry_image'] . '.' ) and mb_stristr( $entry['entry_content'], 'data-fileid="' ) )
				{
					continue;
				}
				
				$file = \IPS\File::create( 'core_Attachment', $entry['entry_image'], NULL, NULL, FALSE, $filePath );
				$attachment = $file->makeAttachment('', \IPS\Member::load( $entry['entry_author_id'] ) );
				$fileName = htmlspecialchars( $attachment['attach_file'], ENT_DISALLOWED, 'UTF-8', TRUE );
				
				$image = <<<IMAGE
<a href="<fileStore.core_Attachment>/{$file}" class="ipsAttachLink ipsAttachLink_image" style="float:left;"><img data-fileid="{$attachment['attach_id']}" src="<fileStore.core_Attachment>/{$file}" class="ipsImage ipsImage_thumbnailed" style="margin:10px;" alt="{$fileName}"></a>
IMAGE;
				
				\IPS\Db::i()->update( 'blog_entries', array( 'entry_content' => $image . $entry['entry_content'] ), array( 'entry_id=?', $entry['entry_id'] ) );

				$map	= array(
					'attachment_id'		=> $attachment['attach_id'],
					'location_key'		=> 'blog_Entries',
					'id1'				=> $entry['entry_id'],
					'id2'				=> NULL,
					'id3'				=> NULL,
					'temp'				=> NULL,
				);

				\IPS\Db::i()->replace( 'core_attachments_map', $map );
			}
			catch ( \Exception $e ) {}

			$last = $entry['entry_id'];
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
		return array(
			'text'      => \IPS\Member::loggedIn()->language()->addToStack( 'rebuilding_blog_entry_images', FALSE ),
			'complete'  => ( isset( $data['count'] ) AND $data['count'] ) 
				? ( round( 100 / $data['count'] * $offset, 2 ) ) 
				: 100
		);
	}
}
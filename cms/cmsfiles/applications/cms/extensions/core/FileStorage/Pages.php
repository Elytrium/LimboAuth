<?php
/**
 * @brief		File Storage Extension: Pages
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 October 2014
 */

namespace IPS\cms\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: CMS Pages
 */
class _Pages
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return 1; # Number of steps needed to clear/move files
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\Underflowexception				When file record doesn't exist. Indicating there are no more files to move
	 * @return	void
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		/* Just remove page object data so it will rebuild on the next iteration */
		\IPS\cms\Pages\Page::deleteCachedIncludes( NULL, $oldConfiguration );
		
		throw new \UnderflowException;
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		/* Just remove page object data so it will rebuild on the next iteration */
		\IPS\cms\Pages\Page::deleteCachedIncludes();
		
		throw new \UnderflowException;
	}


	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		$bits = explode( '/', (string) $file );
		$name = array_pop( $bits );

		try
		{
			foreach( \IPS\Db::i()->select( '*', 'cms_templates', array( "template_file_object LIKE '%" . \IPS\Db::i()->escape_string( $name ) . "%'") ) as $template )
			{
				$fileObject = \IPS\File::get( 'core_Theme', $template['template_file_object'] );

				if( $fileObject->url == (string) $file )
				{
					return TRUE;
				}
			}
			
			return FALSE;
		}
		catch( \IPS\Db\Exception $e )
		{
			return FALSE;
		}
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\cms\Pages\Page::deleteCachedIncludes();
	}
}
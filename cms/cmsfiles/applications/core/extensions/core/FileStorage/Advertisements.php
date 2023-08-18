<?php
/**
 * @brief		File Storage Extension: Advertisements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Sep 2013
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Advertisements
 */
class _Advertisements
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_advertisements', array( "ad_images!=?", '[]' ) )->first();
	}
	
	/**
	 * Move stored files
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @param	int			$storageConfiguration	New storage configuration ID
	 * @param	int|NULL	$oldConfiguration		Old storage configuration ID
	 * @throws	\UnderflowException					When file record doesn't exist. Indicating there are no more files to move
	 * @return	void|int							An offset integer to use on the next cycle, or nothing
	 */
	public function move( $offset, $storageConfiguration, $oldConfiguration=NULL )
	{
		$advertisement = \IPS\Db::i()->select( '*', 'core_advertisements', array( "ad_images!=?", '[]' ), 'ad_id', array( $offset, 1 ) )->first();

		$advertisement['_images']	= json_decode( $advertisement['ad_images'], TRUE );

		if( \count( $advertisement['_images'] ) )
		{
			$files	= array();
			
			try
			{
				$files['large'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Advertisements', $advertisement['_images']['large'] )->move( $storageConfiguration );
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
			
			if( isset( $advertisement['_images']['small'] ) )
			{
				try
				{
					$files['small'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Advertisements', $advertisement['_images']['small'] )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}

			if( isset( $advertisement['_images']['medium'] ) )
			{
				try
				{
					$files['medium'] = (string) \IPS\File::get( $oldConfiguration ?: 'core_Advertisements', $advertisement['_images']['medium'] )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}
		
		if ( \count( $files ) )
		{
			\IPS\Db::i()->update( 'core_advertisements', array( 'ad_images' => json_encode( $files ) ), array( 'ad_id=?', $advertisement['ad_id'] ) );
		}
	}
	
	/**
	 * Fix all URLs
	 *
	 * @param	int			$offset					This will be sent starting with 0, increasing to get all files stored by this extension
	 * @return void
	 */
	public function fixUrls( $offset )
	{
		$advertisement = \IPS\Db::i()->select( '*', 'core_advertisements', array( "ad_images!=?", '[]' ), 'ad_id', array( $offset, 1 ) )->first();

		$advertisement['_images']	= json_decode( $advertisement['ad_images'], TRUE );
	
		try
		{
			$fixed = array();
			foreach( array( 'large', 'small', 'medium' ) as $location )
			{
				if ( $new = \IPS\File::repairUrl( $advertisement['_images'][ $location ] ) )
				{
					$fixed[ $location ] = $new;
				}
			}
			
			if ( \count( $fixed ) )
			{
				\IPS\Db::i()->update( 'core_advertisements', array( 'ad_images' => json_encode( $fixed ) ), array( 'ad_id=?', $advertisement['ad_id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged and the \IPS\Db::i()->update not run as the exception is thrown */
		}
	}

	/**
	 * Check if a file is valid
	 *
	 * @param	string	$file		The file path to check
	 * @return	bool
	 */
	public function isValidFile( $file )
	{
		foreach( \IPS\Db::i()->select( '*', 'core_advertisements', array( "ad_images!=?", '[]' ), 'ad_id' ) as $advertisement )
		{
			$advertisement['_images']	= json_decode( $advertisement['ad_images'], TRUE );

			if( \count( $advertisement['_images'] ) )
			{
				if( $advertisement['_images']['large'] == (string) $file )
				{
					return TRUE;
				}

				if( isset( $advertisement['_images']['small'] ) AND $advertisement['_images']['small'] == (string) $file )
				{
					return TRUE;
				}

				if( isset( $advertisement['_images']['medium'] ) AND $advertisement['_images']['medium'] == (string) $file )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Delete all stored files
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( \IPS\Db::i()->select( '*', 'core_advertisements', "ad_images!='[]'" ) as $advertisement )
		{
			$advertisement['_images']	= json_decode( $advertisement['ad_images'], TRUE );

			if( \count( $advertisement['_images'] ) )
			{
				if( $advertisement['_images']['large'] )
				{
					try
					{
						\IPS\File::get( 'core_Advertisements', $advertisement['_images']['large'] )->delete();
					}
					catch( \Exception $e ){}
				}

				if( isset( $advertisement['_images']['small'] ) )
				{
					try
					{
						\IPS\File::get( 'core_Advertisements', $advertisement['_images']['small'] )->delete();
					}
					catch( \Exception $e ){}
				}

				if( isset( $advertisement['_images']['medium'] ) )
				{
					try
					{
						\IPS\File::get( 'core_Advertisements', $advertisement['_images']['medium'] )->delete();
					}
					catch( \Exception $e ){}
				}
			}
		}
	}
}
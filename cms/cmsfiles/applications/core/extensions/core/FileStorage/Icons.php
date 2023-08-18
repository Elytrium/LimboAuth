<?php
/**
 * @brief		File Storage Extension: Icons
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		01 Aug 2018
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: Icons
 */
class _Icons
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		$count	= \IPS\Settings::i()->icons_favicon ? 1 : 0;

		if( \IPS\Settings::i()->icons_sharer_logo )
		{
			$count	+= \count( json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) );
		}

		if( \IPS\Settings::i()->icons_homescreen )
		{
			$count	+= \count( json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) );
		}

		if( \IPS\Settings::i()->icons_apple_startup )
		{
			$count	+= \count( json_decode( \IPS\Settings::i()->icons_apple_startup, TRUE ) );
		}

		if( \IPS\Settings::i()->icons_mask_icon )
		{
			$count	+= 1;
		}

		return $count;
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
		if( \IPS\Settings::i()->icons_favicon )
		{
			try
			{
				\IPS\File::get( $oldConfiguration ?: 'core_Icons', \IPS\Settings::i()->icons_favicon )->move( $storageConfiguration );
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}

		if( \IPS\Settings::i()->icons_sharer_logo )
		{
			$logos	= json_decode( \IPS\Settings::i()->icons_sharer_logo, true );

			foreach( $logos as $logo )
			{
				try
				{
					\IPS\File::get( $oldConfiguration ?: 'core_Icons', $logo )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}

		if( \IPS\Settings::i()->icons_homescreen )
		{
			$homeScreen = json_decode( \IPS\Settings::i()->icons_homescreen, TRUE );

			foreach( $homeScreen as $key => $logo )
			{
				try
				{
					\IPS\File::get( $oldConfiguration ?: 'core_Icons', ( $key == 'original' ) ? $logo : $logo['url'] )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}

		if( \IPS\Settings::i()->icons_apple_startup )
		{
			$apple = json_decode( \IPS\Settings::i()->icons_apple_startup, TRUE );

			foreach( $apple as $key => $logo )
			{
				try
				{
					\IPS\File::get( $oldConfiguration ?: 'core_Icons', ( $key == 'original' ) ? $logo : $logo['url'] )->move( $storageConfiguration );
				}
				catch( \Exception $e )
				{
					/* Any issues are logged */
				}
			}
		}

		if( \IPS\Settings::i()->icons_mask_icon )
		{
			try
			{
				\IPS\File::get( $oldConfiguration ?: 'core_Icons', \IPS\Settings::i()->icons_mask_icon )->move( $storageConfiguration );
			}
			catch( \Exception $e )
			{
				/* Any issues are logged */
			}
		}

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
		$updated = array();

		if( \IPS\Settings::i()->icons_favicon )
		{
			$updated['icons_favicon'] = \IPS\File::repairUrl( \IPS\Settings::i()->icons_favicon );
		}

		if( \IPS\Settings::i()->icons_sharer_logo )
		{
			$newLogos	= array();

			foreach( json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) as $logo )
			{
				$newLogos[] = \IPS\File::repairUrl( $logo );
			}

			$updated['icons_sharer_logo']	= json_encode( $newLogos );
		}

		if( \IPS\Settings::i()->icons_homescreen )
		{
			$newLogos	= array();

			foreach( json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) as $key => $logo )
			{
				if( $key == 'original' )
				{
					$newLogos[ $key ] = \IPS\File::repairUrl( $logo );
				}
				else
				{
					$newLogos[ $key ] = $logo;
					$newLogos[ $key ]['url'] = \IPS\File::repairUrl( $logo['url'] );
				}
			}

			$updated['icons_homescreen']	= json_encode( $newLogos );
		}

		if( \IPS\Settings::i()->icons_apple_startup )
		{
			$newLogos	= array();

			foreach( json_decode( \IPS\Settings::i()->icons_apple_startup, TRUE ) as $key => $logo )
			{
				if( $key == 'original' )
				{
					$newLogos[ $key ] = \IPS\File::repairUrl( $logo );
				}
				else
				{
					$newLogos[ $key ] = $logo;
					$newLogos[ $key ]['url'] = \IPS\File::repairUrl( $logo['url'] );
				}
			}

			$updated['icons_apple_startup']	= json_encode( $newLogos );
		}

		if( \IPS\Settings::i()->icons_mask_icon )
		{
			$updated['icons_mask_icon'] = \IPS\File::repairUrl( \IPS\Settings::i()->icons_mask_icon );
		}

		if( \count( $updated ) )
		{
			\IPS\Settings::i()->changeValues( $updated );
		}
		
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
		if( \IPS\Settings::i()->icons_favicon AND $file == \IPS\Settings::i()->icons_favicon )
		{
			return TRUE;
		}

		if( \IPS\Settings::i()->icons_sharer_logo )
		{
			$logos	= json_decode( \IPS\Settings::i()->icons_sharer_logo, true );

			if( \in_array( $file, $logos ) )
			{
				return TRUE;
			}
		}

		if( \IPS\Settings::i()->icons_homescreen )
		{
			foreach( json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) as $key => $logo )
			{
				if( ( $key == 'original' AND $file == $logo ) OR ( $key != 'original' AND $file == $logo['url'] ) )
				{
					return TRUE;
				}
			}
		}

		if( \IPS\Settings::i()->icons_apple_startup )
		{
			foreach( json_decode( \IPS\Settings::i()->icons_apple_startup, TRUE ) as $key => $logo )
			{
				if( ( $key == 'original' AND $file == $logo ) OR ( $key != 'original' AND $file == $logo['url'] ) )
				{
					return TRUE;
				}
			}
		}

		if( \IPS\Settings::i()->icons_mask_icon AND $file == \IPS\Settings::i()->icons_mask_icon )
		{
			return TRUE;
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
		if( \IPS\Settings::i()->icons_favicon )
		{
			try
			{
				\IPS\File::get( 'core_Icons', \IPS\Settings::i()->icons_favicon )->delete();
			}
			catch( \OutOfRangeException $e ){}
		}

		if( \IPS\Settings::i()->icons_sharer_logo )
		{
			foreach( json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) as $logo )
			{
				try
				{
					\IPS\File::get( 'core_Icons', $logo )->delete();
				}
				catch( \OutOfRangeException $e ){}
			}
		}

		if( \IPS\Settings::i()->icons_homescreen )
		{
			foreach( json_decode( \IPS\Settings::i()->icons_homescreen, TRUE ) as $key => $logo )
			{
				try
				{
					\IPS\File::get( 'core_Icons', ( $key == 'original' ) ? $logo : $logo['url'] )->delete();
				}
				catch( \OutOfRangeException $e ){}
			}
		}

		if( \IPS\Settings::i()->icons_apple_startup )
		{
			foreach( json_decode( \IPS\Settings::i()->icons_apple_startup, TRUE ) as $key => $logo )
			{
				try
				{
					\IPS\File::get( 'core_Icons', ( $key == 'original' ) ? $logo : $logo['url'] )->delete();
				}
				catch( \OutOfRangeException $e ){}
			}
		}

		if( \IPS\Settings::i()->icons_mask_icon )
		{
			try
			{
				\IPS\File::get( 'core_Icons', \IPS\Settings::i()->icons_mask_icon )->delete();
			}
			catch( \OutOfRangeException $e ){}
		}

		\IPS\Settings::i()->changeValues( array( 'icons_favicon' => NULL, 'icons_sharer_logo' => NULL, 'icons_homescreen' => NULL, 'icons_apple_startup' => NULL, 'icons_mask_icon' => NULL ) );
	}
}
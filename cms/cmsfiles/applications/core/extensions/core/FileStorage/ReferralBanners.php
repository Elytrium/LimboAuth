<?php
/**
 * @brief		File Storage Extension: ReferralBanners
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Aug 2019
 */

namespace IPS\core\extensions\core\FileStorage;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File Storage Extension: ReferralBanners
 */
class _ReferralBanners
{
	/**
	 * Count stored files
	 *
	 * @return	int
	 */
	public function count()
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'core_referral_banners', 'rb_upload=1' )->first();
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
		$record = \IPS\Db::i()->select( '*', 'core_referral_banners', 'rb_upload=1', 'rb_id', array( $offset, 1 ) )->first();

		try
		{
			$file = \IPS\File::get( $oldConfiguration ?: 'core_ReferralBanners', $record['rb_url'] )->move( $storageConfiguration );

			if ( (string) $file != $record['rb_url'] )
			{
				\IPS\Db::i()->update( 'core_referral_banners', array( 'rb_url' => (string) $file ), array( 'rb_id=?', $record['rb_id'] ) );
			}
		}
		catch( \Exception $e )
		{
			/* Any issues are logged */
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
		$record = \IPS\Db::i()->select( '*', 'core_referral_banners', 'rb_upload=1', 'rb_id', array( $offset, 1 ) )->first();

		if ( $new = \IPS\File::repairUrl( $record['rb_url'] ) )
		{
			\IPS\Db::i()->update( 'core_referral_banners', array( 'rb_url' => $new ), array( 'rb_id=?', $record['rb_id'] ) );
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
		try
		{
			\IPS\Db::i()->select( '*', 'core_referral_banners', array( 'rb_url=? and rb_upload=1', (string) $file ) )->first();
			return TRUE;
		}
		catch ( \UnderflowException $e )
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
		foreach( \IPS\Db::i()->select( '*', 'core_referral_banners', 'rb_upload=1' ) as $banner )
		{
			try
			{
				\IPS\File::get( 'core_ReferralBanners', $banner['rb_url'] )->delete();
			}
			catch( \Exception $e ){}
		}
	}
}
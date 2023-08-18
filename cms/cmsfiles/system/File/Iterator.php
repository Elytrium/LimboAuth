<?php
/**
 * @brief		File IteratorIterator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Oct 2013
 */

namespace IPS\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * File IteratorIterator
 */
class _Iterator extends \IteratorIterator implements \Countable
{
	/**
	 * @brief	Stroage Extension
	 */
	protected $storageExtension;
	
	/**
	 * @brief	URL Field
	 */
	protected $urlField;
	
	/**
	 * @brief	URLs Only
	 */
	protected $fileUrlsOnly;
	
	/**
	 * @brief	Used to restore 'real' names when filenames cleaned (eg adh1029_file.php back to fi^le.php)
	 */
	protected $replaceNameField;

	/**
	 * @brief	Used to pre-cache the filesize value if available
	 */
	protected $fileSizeField;

	
	/**
	 * Constructor
	 *
	 * @param	Traversable $iterator			The iterator
	 * @param	string		$storageExtension	The storage extension
	 * @param	string|NULL	$urlField			If passed a string, will look for an element with that key in the data returned from the iterator
	 * @param	bool		$fileUrlsOnly		Only return the file URL instead of the file object
	 * @param	string|NULL	$replaceNameField	If passed a string, it will replace the originalFilename with the data in the array. Used to restore 'real' names when filenames cleaned (eg adh1029_file.php back to fi^le.php)
	 * @param 	string		$fileSizeField		Field to use to pre-cache the filesize
	 * @return	void
	 */
	public function __construct( \Traversable $iterator, $storageExtension, $urlField=NULL, $fileUrlsOnly=FALSE, $replaceNameField=NULL, $fileSizeField=NULL )
	{
		$this->storageExtension = $storageExtension;
		$this->urlField = $urlField;
		$this->fileUrlsOnly = $fileUrlsOnly;
		$this->replaceNameField = $replaceNameField;
		$this->fileSizeField = $fileSizeField;
		return parent::__construct( $iterator );
	}
	
	/**
	 * Get current
	 *
	 * @return	\IPS\File
	 */
	public function current()
	{
		try
		{
			$data = $this->data();
			$urlField = NULL;
			
			if ( $this->urlField )
			{
				if ( !\is_string( $this->urlField ) and \is_callable( $this->urlField ) )
				{
					$urlFieldCallback = $this->urlField;
					$urlField = $urlFieldCallback( $data );
				}
				else
				{
					$urlField = $this->urlField;
				}
			}

			$fileSize = ( $this->fileSizeField AND isset( $data[ $this->fileSizeField ] ) ) ? $data[ $this->fileSizeField ] : ( $this->fileSizeField === FALSE ? FALSE : NULL );
			$obj = \IPS\File::get( $this->storageExtension, $urlField ? $data[ $urlField ] : $data, $fileSize );
			
			if ( $this->replaceNameField and ! empty( $data[ $this->replaceNameField ] ) )
			{
				$obj->originalFilename = $data[ $this->replaceNameField ];
			}
			
			return ( $this->fileUrlsOnly ) ? (string) $obj->url : $obj;
		}
		catch ( \Exception $e )
		{
			$this->next();
			return $this->current();
		}
	}
	
	/**
	 * Get data
	 *
	 * @return	mixed
	 */
	public function data()
	{
		return parent::current();
	}
	
	/**
	 * Get count
	 *
	 * @return	int
	 */
	public function count()
	{
		return $this->getInnerIterator()->count();
	}
}
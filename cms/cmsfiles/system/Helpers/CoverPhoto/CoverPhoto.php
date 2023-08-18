<?php
/**
 * @brief		Cover Photo Helper
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */

namespace IPS\Helpers;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Cover Photo Helper
 */
class _CoverPhoto
{
	/**
	 * File
	 */
	public $file;
	
	/**
	 * Offset
	 */
	public $offset = 0;
	
	/**
	 * Editable
	 */
	public $editable = FALSE;

	/**
	 * Maximum file size
	 */
	public $maxSize = NULL;
	
	/**
	 * Overlay
	 */
	public $overlay;
	
	/**
	 * Object
	 */
	public $object;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\File|NULL	$file		The file
	 * @param	int				$offset		The offset
	 * @param	bool			$editable	User can edit?
	 */
	public function __construct( \IPS\File $file = NULL, $offset = 0, $editable = FALSE )
	{
		$this->file = $file;
		$this->offset = $offset;
	}
	
	/**
	 * Render
	 *
	 * @return	string
	 */
	public function __toString()
	{
		if( !\IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_core.js', 'core' ) );
		}

		return \IPS\Theme::i()->getTemplate( 'global', 'core' )->coverPhoto( $this->object->url(), $this );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $this->file )
		{
			$this->file->delete();
		}
	}
}
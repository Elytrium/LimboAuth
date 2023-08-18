<?php
/**
 * @brief		Pending Version Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		8 Apr 2020
 */

namespace IPS\downloads\modules\front\downloads;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Pending Version Controller
 */
class _pending extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]    Class
	 */
	protected static $contentModel = \IPS\downloads\File\PendingVersion::class;

	/**
	 * @brief	Storage for loaded file
	 */
	protected $file = NULL;

	/**
	 * @brief	Storage for loaded version
	 */
	protected $version = NULL;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->file = \IPS\downloads\File::load( \IPS\Request::i()->file_id );
			$this->version = \IPS\downloads\File\PendingVersion::load( \IPS\Request::i()->id );

			if( $this->file->id !== $this->version->file()->id )
			{
				throw new \OutOfRangeException;
			}

			if ( !$this->version->canUnhide() AND !$this->version->canDelete())
			{
				\IPS\Output::i()->error( 'node_error', '2D417/1', 404, '' );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			/* The version does not exist, but the file does. Redirect there instead. */
			if( isset( $this->file ) )
			{
				\IPS\Output::i()->redirect( $this->file->url() );
			}

			\IPS\Output::i()->error( 'node_error', '2D417/2', 404, '' );
		}

		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_view.js', 'downloads', 'front' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_pending.js', 'downloads', 'front' ) );

		parent::execute();
	}

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Display */
		\IPS\Output::i()->title = $this->file->name;

		$container = $this->file->container();
		foreach ( $container->parents() as $parent )
		{
			\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
		}
		\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );

		\IPS\Output::i()->breadcrumb[] = array( $this->file->url(), $this->file->name );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('pending_version') );

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'view' )->pendingView( $this->file, $this->version );
	}

	/**
	 * Download a file
	 *
	 * @return	void
	 */
	public function download()
	{
		try
		{
			$record = \IPS\Db::i()->select( '*', 'downloads_files_records', array( 'record_id=? AND record_file_id=?', \IPS\Request::i()->fileId, $this->file->id ) )->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2D417/3', 404, '' );
		}

		/* Download */
		if ( $record['record_type'] === 'link' )
		{
			\IPS\Output::i()->redirect( $record['record_location'] );
		}
		else
		{
			$file = \IPS\File::get( 'downloads_Files', $record['record_location'] );
			$file->originalFilename = $record['record_realname'] ?: $file->originalFilename;
		}

		/* If it's an AWS file just redirect to it */
		try
		{
			if ( $signedUrl = $file->generateTemporaryDownloadUrl() )
			{
				\IPS\Output::i()->redirect( $signedUrl );
			}
		}
		catch( \UnexpectedValueException $e )
		{
			\IPS\Log::log( $e, 'downloads' );
			\IPS\Output::i()->error( 'generic_error', '3D417/5', 500, '' );
		}

		/* Send headers and print file */
		\IPS\Output::i()->sendStatusCodeHeader( 200 );
		\IPS\Output::i()->sendHeader( "Content-type: " . \IPS\File::getMimeType( $file->originalFilename ) . ";charset=UTF-8" );
		\IPS\Output::i()->sendHeader( "Content-Security-Policy: default-src 'none'; sandbox" );
		\IPS\Output::i()->sendHeader( "X-Content-Security-Policy:  default-src 'none'; sandbox" );
		\IPS\Output::i()->sendHeader( 'Content-Disposition: ' . \IPS\Output::getContentDisposition( 'attachment', $file->originalFilename ) );
		\IPS\Output::i()->sendHeader( "Content-Length: " . $file->filesize() );

		$file->printFile();
		exit;
	}

	/**
	 * Moderate
	 *
	 * @return	void
	 */
	protected function moderate()
	{
		if( $this->file->hidden() === 1 AND \IPS\Request::i()->action == 'unhide' )
		{
			\IPS\Output::i()->error( 'file_version_pending_cannot_approve', '2D417/4', 403, '' );
		}

		return parent::moderate();
	}

	/**
	 * Method used to allow pending version approval for authors
	 *
	 * @return void
	 */
	protected function delete()
	{
		if( !$this->version->canDelete())
		{
			\IPS\Output::i()->error( 'file_version_pending_cannot_delete', '2D417/4', 403, '' ); //TODO error code
		}
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		$this->version->delete();
		\IPS\Session::i()->modLog( 'modlog__action_deletedpending', array( (string) $this->file->url() => FALSE, $this->file->name => FALSE ), $this->file );
		\IPS\Output::i()->redirect( $this->file->url() , 'deleted');
	}
}
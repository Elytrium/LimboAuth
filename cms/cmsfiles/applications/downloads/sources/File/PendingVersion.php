<?php
/**
 * @brief		Pending Version Model
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Downloads
 * @since		6 Apr 2020
 */

namespace IPS\downloads\File;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PendingVersion Model
 */
class _PendingVersion extends \IPS\Content\Item implements \IPS\Content\Hideable
{
	/**
	 * @brief	Application
	 */
	public static $application = 'downloads';

	/**
	 * @brief	Module
	 */
	public static $module = 'downloads';

	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'downloads_files_pending';

	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'pending_';

	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';

	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array('pending_file_id');

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'author'				=> 'member_id',
		'title'					=> 'name',
		'date'					=> 'date',
		'approved'				=> 'approved',
	);

	/**
	 * @brief	Title
	 */
	public static $title = 'downloads_file_pending';

	/**
	 * @brief	Icon
	 */
	public static $icon = 'download';

	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'downloads-file-pending';

	/**
	 * @brief   Used by the dataLayer
	 */
	public static $contentType = 'file';

	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return  boolean
	 */
	public function canUnhide( $member=NULL )
	{
		return \IPS\downloads\File::modPermission( 'unhide', $member, $this->file()->containerWrapper() );
	}

	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		return FALSE;
	}

	/**
	 * Can delete pending version?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{

		$member = $member ?: \IPS\Member::loggedIn();
		if( !$member->member_id )
		{
			return FALSE;
		}

		if( !$this->author()->member_id == $member->member_id AND !$this->file()->canDeletePendingVersion( $member ) )
		{
			return FALSE;
		}

		return TRUE;
	}
	
	/**
	 * Returns the content
	 *
	 * @return	string
	 */
	public function content()
	{
		return $this->form_values['file_changelog'];
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Pending URL
	 *
	 * @param	null|string		$action 	'do' action
	 * @return 	\IPS\Http\Url
	 */
	public function url( $action=NULL ): \IPS\Http\Url
	{
		$url = \IPS\Http\Url::internal( "app=downloads&module=downloads&controller=pending&file_id={$this->file()->id}&id={$this->id}", 'front', 'downloads_file_pending', $this->file()->name_furl );

		if( $action )
		{
			$url = $url->setQueryString( 'do', $action );
		}

		return $url;
	}

	/**
	 * @brief	store decoded form values
	 */
	protected $_formValues = NULL;

	/**
	 * Get form values decoded
	 *
	 * @return	array
	 */
	public function get_form_values(): array
	{
		if( $this->_formValues !== NULL )
		{
			return $this->_formValues;
		}

		return json_decode( $this->_data['form_values'], TRUE );
	}

	/**
	 * Getter for file name
	 *
	 * @return 	string
	 */
	public function get_name(): string
	{
		return $this->file()->mapped('title');
	}

	/**
	 * Get decoded record deletions
	 *
	 * @return 	array
	 */
	public function get_record_deletions(): array
	{
		return json_decode( $this->_data['record_deletions'], TRUE );
	}

	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	public function setDefaultValues()
	{
		$this->approved = 0;
	}

	/**
	 * Setter for download save version
	 *
	 * @return	void
	 */
	public function saveVersion()
	{
		$this->save_version = 1;
	}

	/**
	 * Set form values
	 *
	 * @param	array 	$values
	 */
	public function set_form_values( array $values )
	{
		foreach( $values as $k => $v )
		{
			if( $v instanceof \IPS\File )
			{
				$values[ $k ] = (string) $v;
			}
			elseif( \is_array( $v ) )
			{
				foreach( $v as $_key => $_value )
				{
					if( $_value instanceof \IPS\File )
					{
						$values[ $k ][ $_key ] = (string) $_value;
					}
				}
			}
		}

		$this->_formValues = $values;
		$this->_data['form_values'] = json_encode( $values );
	}

	/**
	 * Set record deletion IDs
	 *
	 * @param 	array 	$ids
	 */
	public function set_record_deletions( array $ids )
	{
		$this->_data['record_deletions'] = json_encode( $ids );
	}

	/**
	 * Setter for download updated date
	 *
	 * @param	$value		New date
	 */
	public function set_updated( $value )
	{
		$this->date = $value;
	}

	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @param	bool				$immediately	Delete immediately
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL, $immediately = FALSE )
	{
		/* We always want to immediately delete these records instead of leaving them soft deleted */
		if( $action === 'delete' )
		{
			$file = $this->file();
			$this->delete();

			/* Moderator log */
			\IPS\Session::i()->modLog( 'modlog__action_newversion_reject', array( (string) $file->url() => FALSE, $file->name => FALSE ), $file );

			if( \IPS\Dispatcher::hasInstance() AND !\IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->redirect( $file->url() );
			}
			elseif( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}

			return;
		}
		elseif( $action == 'approve' OR $action == 'unhide' )
		{
			/* Moderator log */
			\IPS\Session::i()->modLog( 'modlog__action_newversion_approved', array( (string) $this->file()->url() => FALSE, $this->file()->name => FALSE ), $this->file() );
			\IPS\Api\Webhook::fire( 'downloads_new_version_approved', $this );
		}

		return parent::modAction( $action, $member, $reason, $immediately );
	}

	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		$ids = [];
		foreach( \IPS\Db::i()->select( '*', 'downloads_files_records', [ 'record_file_id=? AND record_hidden=1', $this->file_id ], NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER ) as $record )
		{
			switch( $record['record_type'] )
			{
				case 'upload':
				case 'ssupload':
						$this->file()->deleteRecords( $record['record_id'], $record['record_location'], ( $record['record_type'] == 'ssupload' ) ? 'downloads_Screenshots' : 'downloads_Files' );

						try
						{
							if( $record['record_type'] == 'ssupload' AND !empty( $record['record_thumb'] )
								AND !\IPS\Db::i()->select( 'COUNT(record_id)', 'downloads_files_records', [ 'record_id<>? AND record_thumb=?', $record['record_id'], $record['record_thumb'] ] )->first() )
							{
									\IPS\File::get( 'downloads_Screenshots', $record['record_thumb'] )->delete();
							}
						}
						catch( \Exception $e ){}
					break;
				case 'link':
				case 'sslink':
						$ids[] = $record['record_id'];
					break;
			}
		}

		if( \count( $ids ) )
		{
			$this->file()->deleteRecords( $ids );
		}

		parent::delete();
	}

	/**
	 * Get related file object
	 *
	 * @return \IPS\downloads\File
	 */
	public function file()
	{
		return \IPS\downloads\File::load( $this->file_id );
	}

	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unhide( $member )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		if( $this->save_version )
		{
			$this->file()->saveVersion();
		}

		/* Remove hidden flag */
		\IPS\Db::i()->update( 'downloads_files_records', array( 'record_hidden' => 0 ), array( 'record_file_id=?', $this->file_id ) );

		$deletions = $this->record_deletions;
		$file = $this->file();
		array_walk( $deletions['records'], function( $arr, $key ) use ( $file ) {
			$file->deleteRecords( $key, $arr['url'], $arr['handler'] );
		});
		$file->deleteRecords( $deletions['links'] );

		foreach( $deletions['thumbs'] as $url )
		{
			try
			{
				\IPS\File::get( 'downloads_Screenshots', $url )->delete();
			}
			catch( \Exception $e ){}
		}

		$file->size = \floatval( \IPS\Db::i()->select( 'SUM(record_size)', 'downloads_files_records', array( 'record_file_id=? AND record_type=? AND record_backup=0', $file->id, 'upload' ), NULL, NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first() );

		/* Work out the new primary screenshot */
		try
		{
			$file->primary_screenshot = \IPS\Db::i()->select( 'record_id', 'downloads_files_records', array( 'record_file_id=? AND ( record_type=? OR record_type=? ) AND record_backup=0 AND record_hidden=0', $file->id, 'ssupload', 'sslink' ), 'record_default DESC, record_id ASC', NULL, NULL, NULL, \IPS\Db::SELECT_FROM_WRITE_SERVER )->first();
		}
		catch ( \UnderflowException $e ) { }

		/* The container may not have versions enabled */
		if( !empty( $this->form_values['file_version'] ) )
		{
			$file->version = $this->form_values['file_version'];
		}

		$file->changelog = $this->content();
		$file->updated = time();
		$file->approver = $member->member_id;
		$file->approvedon = time();
		$file->published = time();
		$file->save();

		/* Saved form values for after new version processing */
		$formValues = $this->form_values;

		/* Delete pending record */
		$this->delete();

		/* Send notifications */
		if ( $file->open )
		{
			$file->sendApprovedNotification();
		}

		$file->processAfterNewVersion( $formValues );
	}

	/**
	 * Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();

		\IPS\File::claimAttachments( "downloads-{$this->file_id}-changelog", $this->file_id, $this->id, 'changelogpending' );
	}
}
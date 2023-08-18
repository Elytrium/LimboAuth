<?php
/**
 * @brief		Table helper for attachments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		19 Jun 2018
 */

namespace IPS\core\Attachments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Table Builder for attachments
 */
class _Table extends \IPS\Helpers\Table\Db
{
	/**
	 * Constructor
	 *
	 * @param	string	$table						Database table
	 * @param	\IPS\Http\Url	$baseUrl			Base URL
	 * @param	array|null		$where				WHERE clause
	 * @param	array|null		$forceIndex			Index to force
	 * @return	void
	 */
	public function __construct( $table, \IPS\Http\Url $baseUrl, $where=NULL, $forceIndex=NULL )
	{
		/* Do any multi-mod */
		if ( isset( \IPS\Request::i()->modaction ) )
		{
			$this->multiMod();
		}

		return parent::__construct( $table, $baseUrl, $where, $forceIndex );
	}

	/**
	 * @brief	Return table filters
	 */
	public $showFilters	= TRUE;

	/**
	 * Saved Actions (for multi-moderation)
	 */
	public $savedActions = array();

	/**
	 * Return the filters that are available for selecting table rows
	 *
	 * @return	array
	 */
	public function getFilters()
	{
		return array();
	}
	
	/**
	 * Does the user have permission to use the multi-mod checkboxes?
	 *
	 * @param	string|null		$action		Specific action to check (hide/unhide, etc.) or NULL for a generic check
	 * @return	bool
	 */
	public function canModerate( $action=NULL )
	{
		return (bool) \IPS\Member::loggedIn()->group['gbw_delete_attachments'];
	}
	
	/**
	 * What multimod actions are available
	 *
	 * @return	array
	 */
	public function multimodActions()
	{
		return array( 'delete' );
	}
	
	/**
	 * Multimod
	 *
	 * @return	void
	 */
	protected function multimod()
	{
		if( !\is_array( \IPS\Request::i()->moderate ) )
		{
			return;
		}

		\IPS\Session::i()->csrfCheck();

		foreach (\IPS\Request::i()->moderate as $id => $status )
		{
			try
			{
				$attachment = \IPS\Db::i()->select( '*', 'core_attachments', array( 'attach_id=?', $id ) )->first();

				/* Check it belongs to us */
				if ( $attachment['attach_member_id'] !== \IPS\Member::loggedIn()->member_id )
				{
					\IPS\Output::i()->error( 'no_module_permission', '2C388/1', 403, '' );
				}

				/* And we can delete it */
				if ( !\IPS\Member::loggedIn()->group['gbw_delete_attachments'] )
				{
					\IPS\core\extensions\core\EditorMedia\Attachment::getLocations( $attachment['attach_id'] );
					if ( \count( \IPS\core\extensions\core\EditorMedia\Attachment::$locations[ $attachment['attach_id'] ] ) )
					{
						\IPS\Output::i()->error( 'no_module_permission', '2C388/2', 403, '' );
					}
				}

				/* Delete */
				try
				{
					\IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->delete();
					if ( $attachment['attach_thumb_location'] )
					{
						\IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] )->delete();
					}
				}
				catch ( \Exception $e ) { }
				\IPS\Db::i()->delete( 'core_attachments', array( 'attach_id=?', $attachment['attach_id'] ) );
				\IPS\Db::i()->delete( 'core_attachments_map', array( 'attachment_id=?', $attachment['attach_id'] ) );
			}
			catch ( \UnderflowException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C388/3', 404, '' );
			}
		}

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=system&controller=attachments', 'front', 'attachments' ), 'deleted' );
	}
}
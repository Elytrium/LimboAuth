<?php
/**
 * @brief		My Attachments Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Jul 2013
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * My Attachments Controller
 */
class _attachments extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Logged in and can upload only */
		if ( !\IPS\Member::loggedIn()->member_id or \IPS\Member::loggedIn()->group['g_attach_max'] == 0 )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C229/1', 403, '' );
		}
		
		/* Build Table */
		$table = new \IPS\core\Attachments\Table( 'core_attachments', \IPS\Http\Url::internal( 'app=core&module=system&controller=attachments', 'front', 'attachments' ), array( array( 'attach_member_id=?', \IPS\Member::loggedIn()->member_id ) ) );
		$table->include = array( 'attach_id', 'attach_location', 'attach_date', 'attach_file', 'attach_filesize', 'attach_is_image', 'attach_content', 'attach_hits', 'attach_security_key' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate('myAttachments'), 'rows' );
		$table->joins = array();
		
		/* Sort */
		$table->sortOptions = array( 'attach_date' => 'attach_date', 'attach_file' => 'attach_file', 'attach_filesize' => 'attach_filesize' );
		$table->sortBy = $table->sortBy ?: 'attach_date';
		if ( $table->sortBy === 'attach_file' )
		{
			$table->sortDirection = 'asc';
		}
		$table->filters = array( 'images' => array( 'attach_is_image=1' ), 'files' => array( 'attach_is_image=0' ) );

		/* Get the associated content */
		$self = $this;
		$table->parsers = array( 'attach_content' => function( $val, $row ) use ( $self )
		{
			return \IPS\core\extensions\core\EditorMedia\Attachment::getLocations( $row['attach_id'] );
		} );
			
		/* Display */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('my_attachments');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('my_attachments') );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('myAttachments')->template( (string) $table, \IPS\Db::i()->select( 'SUM(attach_filesize)', 'core_attachments', array( 'attach_member_id=?', \IPS\Member::loggedIn()->member_id ) )->first(), \IPS\Db::i()->select( 'COUNT(*)', 'core_attachments', array( 'attach_member_id=?', \IPS\Member::loggedIn()->member_id ) )->first() );
	}

	/**
	 * Rotate imate
	 *
	 * @return void
	 */
	protected function rotate()
	{
		\IPS\Session::i()->csrfCheck();

		/* Get attachment to make sure it's valid */
		try
		{
			$attachment = \IPS\Db::i()->select( '*', 'core_attachments', [ 'attach_id=? and attach_is_image=?', \IPS\Request::i()->id, 1 ] )
				->join( 'core_attachments_map', 'core_attachments.attach_id=core_attachments_map.attachment_id' )
				->first();
		}
		catch( \UnderflowException $e )
		{
			\IPS\Output::i()->json( array( 'error' => 'node_error' ) );
		}

		/* Check Permission */
		$exploded = explode( '_', $attachment['location_key'] );
		try
		{
			$extensions = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'EditorLocations' );
			if ( isset( $extensions[ $exploded[1] ] ) )
			{
				$attachmentItem = $extensions[ $exploded[1] ]->attachmentLookup( $attachment[ 'id1' ], $attachment[ 'id2' ], $attachment[ 'id3' ] );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->json( array( 'error' => 'no_permission' ) );
		}

		/* If we have permission to edit the post, we're going to save the rotation angle */
		if( $attachmentItem->author()->member_id == \IPS\Member::loggedIn()->member_id OR $attachmentItem->canEdit() )
		{
			$baseRotation = $attachment['attach_img_rotate'];
			$temp = false;
		}
		else
		{
			$baseRotation = \IPS\Request::i()->current ?? 0;
			$temp = true;
		}

		$angle = \IPS\Request::i()->direction == 'right' ? 90 : -90;
		$rotation = $baseRotation + $angle;

		/* Make sure we don't rotate to some stupid number */
		if( $rotation >= 360 )
		{
			$rotation -= 360;
		}
		elseif( $rotation <= -360 )
		{
			$rotation += 360;
		}

		/* Store the angle for the future */
		if( !$temp )
		{
			\IPS\Db::i()->update( 'core_attachments', [ 'attach_img_rotate' => $rotation ], [ 'attach_id=?', $attachment[ 'attach_id' ] ] );
		}

		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( [
				'rotate' => $rotation,
				'message'	=> \IPS\Member::loggedIn()->language()->addToStack('gallery_image_rotated'),
				'fileId'    => \IPS\Request::i()->id,
				'saved' 	=> $temp ? 0 : 1
			] );
		}
		else
		{
			\IPS\Output::i()->redirect( $attachmentItem->url() );
		}
	}
}
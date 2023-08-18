<?php
/**
 * @brief		Editor Extension: Record Form
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		20 Feb 2014
 */

namespace IPS\cms\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Record Content
 */
class _Records
{
	/**
	 * @brief	Flag to indicate we don't want to be listed as a selectable area when configuring buttons
	 */
	public static $buttonLocation	= FALSE;

	/**
	 * Can we use HTML in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canUseHtml( $member )
	{
		return NULL;
	}
	
	/**
	 * Can we use attachments in this editor?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @param	\IPS\Helpers\Form\Editor $editor The editor instance
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canAttach( $member, $editor )
	{
		if( !$editor->options['allowAttachments'] )
		{
			return FALSE;
		}

		return NULL;
	}
	
	/**
	 * Can whatever is posted in this editor be moderated?
	 * If this returns TRUE, we must ensure the content is ran through word, link and image filters
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool
	 */
	public function canBeModerated( $member, $field )
	{
		/* Creating/editing a record */
		if ( preg_match( '/^RecordField_(?:new|\d+)_\d+/', $field->options['autoSaveKey'] ) )
		{
			return TRUE;
		}
		/* Creating/editing a comment or review */
		elseif ( preg_match( '/^(?:editComment|reply|review)\-cms\/records\d+\-\d+/', $field->options['autoSaveKey'] ) )
		{
			return TRUE;
		}
		/* Unknown */
		else
		{
			if ( \IPS\IN_DEV )
			{
				throw new \RuntimeException( 'Unknown canBeModerated: ' . $field->options['autoSaveKey'] );
			}
			return FALSE;
		}
	}

	/**
	 * Permission check for attachments
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	int|null	$id1		Primary ID
	 * @param	int|null	$id2		Secondary ID
	 * @param	string|null	$id3		Arbitrary data
	 * @param	array		$attachment	The attachment data
	 * @param	bool		$viewOnly	If true, just check if the user can see the attachment rather than download it
	 * @return	bool
	 */
	public function attachmentPermissionCheck( $member, $id1, $id2, $id3, $attachment, $viewOnly=FALSE )
	{
		if ( ! $id3 )
		{
			throw new \OutOfRangeException;
		}

		$className	= $this->_getClassName( $id3 );
		$id			= ( mb_strpos( $className, 'Review' ) !== FALSE OR mb_strpos( $className, 'Comment' ) !== FALSE ) ? $id2 : $id1;
		
		try
		{
			return $className::load( $id )->canView( $member );
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
	}

	/**
	 * Figure out the correct class name to return
	 *
	 * @param	string|int	$id3	The id3 value stored
	 * @return	string
	 */
	protected function _getClassName( $id3 )
	{
		/* Review? */
		if( mb_strpos( $id3, '-review' ) )
		{
			$bits = explode( '-', $id3 );
			$className = '\IPS\cms\Records\Review' . $bits[0];
		}
		/* Comment? */
		elseif( mb_strpos( $id3, '-comment' ) )
		{
			$bits = explode( '-', $id3 );
			$className = '\IPS\cms\Records\Comment' . $bits[0];
		}
		/* Record */
		else
		{
			$className = '\IPS\cms\Records' . $id3;
		}

		return $className;
	}
	
	/**
	 * Attachment lookup
	 *
	 * @param	int|null	$id1	Primary ID
	 * @param	int|null	$id2	Secondary ID
	 * @param	string|null	$id3	Arbitrary data
	 * @return	\IPS\Http\Url|\IPS\Content|\IPS\Node\Model
	 * @throws	\LogicException
	 */
	public function attachmentLookup( $id1, $id2, $id3 )
	{
		try
		{
			if ( $id3 )
			{
				$className	= $this->_getClassName( $id3 );
				$id			= ( mb_strpos( $className, 'Review' ) !== FALSE OR mb_strpos( $className, 'Comment' ) !== FALSE ) ? $id2 : $id1;

				$return = $className::load( $id );
				$return->url(); // Need to check that won't throw an exception later, which might happen if the database no longer has a page
				return $return;
			}
			else
			{
				return FALSE;
			}
		}
		catch ( \Exception $e )
		{
			return FALSE;
		}
	}
}
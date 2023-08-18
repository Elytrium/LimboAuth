<?php
/**
 * @brief		Editor Extension: Image comments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Gallery
 * @since		13 Mar 2014
 */

namespace IPS\gallery\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Image comments
 */
class _Gallery extends \IPS\gallery\extensions\core\EditorLocations\Images
{
	/**
	 * Can we use attachments in this editor?
	 *
	 * @param	\IPS\Member					$member	The member
	 * @param	\IPS\Helpers\Form\Editor	$field	The editor field
	 * @return	bool|null	NULL will cause the default value (based on the member's permissions) to be used, and is recommended in most cases. A boolean value will override that.
	 */
	public function canAttach( $member, $field )
	{
		return NULL;
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
        if( $id3 and $id3 == 'album' )
        {
            try
            {
                $album = \IPS\gallery\Album::load( $id1 );
                return $album->canView( $member );
            }
            catch ( \OutOfRangeException $e )
            {
                return FALSE;
            }
        }

        return parent::attachmentPermissionCheck( $member, $id1, $id2, $id3, $attachment, $viewOnly );
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
        if( $id3 and $id3 == 'album' )
        {
            return \IPS\gallery\Album\Comment::load( $id2 );
        }

        return parent::attachmentLookup( $id1, $id2, $id3 );
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
		/* Image comments/reviews */
		if ( preg_match( '/^(?:editComment|reply|review)\-gallery\/gallery\-\d+/', $field->options['autoSaveKey'] ) )
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
}
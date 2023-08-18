<?php
/**
 * @brief		Editor Extension: Purchase Custom Fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		23 Feb 2015
 */

namespace IPS\nexus\extensions\core\EditorLocations;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Editor Extension: Support
 */
class _Purchases
{
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
		try
		{
			/* If it's in the cart, we have to allow it to be public
				so the member can see what they're doing. This is only a
				temporary state */
			if ( $id3 == 'cart' )
			{
				return TRUE;
			}
			
			/* If it's in an invoice, we can check that */
			if ( mb_substr( $id3, 0, 7 ) == 'invoice' )
			{
				return \IPS\nexus\Invoice::load( \intval( mb_substr( $id3, 7 ) ) )->canView( $member );
			}
			
			/* If it's in an purchase, we can link to that */
			if ( $id3 == 'purchase' )
			{
				return \IPS\nexus\Purchase::load( $id1 )->canView( $member );
			}		
			
			/* Still here? Return false */
			return FALSE;
		}
		catch ( \OutOfRangeException $e )
		{
			return FALSE;
		}
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
		/* If it's in the cart, wer obviously can't link to that */
		if ( $id3 == 'cart' )
		{
			throw new \LogicException;
		}
		
		/* If it's in an invoice, we can link to that */
		if ( mb_substr( $id3, 0, 8 ) == 'invoice-' )
		{
			$invoice = \IPS\nexus\Invoice::load( \intval( mb_substr( $id3, 8 ) ) );
			return \IPS\Dispatcher::i()->controllerLocation == 'admin' ? $invoice->acpUrl() : $invoice->url();
		}
		
		/* If it's in an purchase, we can link to that */
		if ( $id3 == 'purchase' )
		{
			return \IPS\nexus\Purchase::load( $id1 );
		}		
		
		/* Still here? No idea */
		throw new \LogicException;
	}
}
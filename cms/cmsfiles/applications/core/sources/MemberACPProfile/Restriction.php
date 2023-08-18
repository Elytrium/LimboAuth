<?php
/**
 * @brief		Member Restrictions Extension Base
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2017
 */

namespace IPS\core\MemberACPProfile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member Restrictions Extension Base
 */
class _Restriction
{
	/**
	 * @brief	Member
	 */
	protected $member;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
	}
	
	/**
	 * Is this extension available?
	 *
	 * @return	bool
	 */
	public function enabled()
	{
		return TRUE;
	}
	
	/**
	 * Modify Edit Restrictions form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( \IPS\Helpers\Form $form )
	{

	}
	
	/**
	 * Save Form
	 *
	 * @param	array	$values	Values from form
	 * @return	void
	 */
	public function save( $values )
	{
		
	}
	
	/**
	 * What restrictions are active on the account?
	 *
	 * @return	array
	 */
	public function activeRestrictions()
	{
		return array();
	}
}
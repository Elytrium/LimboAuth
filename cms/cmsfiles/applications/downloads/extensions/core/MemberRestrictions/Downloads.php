<?php
/**
 * @brief		Member Restrictions: Downloads
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2017
 */

namespace IPS\downloads\extensions\core\MemberRestrictions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member Restrictions: Downloads
 */
class _Downloads extends \IPS\core\MemberACPProfile\Restriction
{
	/**
	 * Modify Edit Restrictions form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( \IPS\Helpers\Form $form )
	{
		$form->add( new \IPS\Helpers\Form\YesNo( 'idm_block_submissions', !$this->member->idm_block_submissions ) );
	}
	
	/**
	 * Save Form
	 *
	 * @param	array	$values	Values from form
	 * @return	array
	 */
	public function save( $values )
	{
		$return = array();
		
		if ( $this->member->idm_block_submissions == $values['idm_block_submissions'] )
		{
			$return['idm_block_submissions'] = array( 'old' => $this->member->members_bitoptions['idm_block_submissions'], 'new' => !$values['idm_block_submissions'] );
			$this->member->idm_block_submissions = !$values['idm_block_submissions'];	
		}
		
		return $return;
	}
	
	/**
	 * What restrictions are active on the account?
	 *
	 * @return	array
	 */
	public function activeRestrictions()
	{
		$return = array();
		
		if ( $this->member->idm_block_submissions )
		{
			$return[] = 'restriction_no_downloads';
		}
		
		return $return;
	}

	/**
	 * Get details of a change to show on history
	 *
	 * @param	array	$changes	Changes as set in save()
	 * @param   array   $row        Row of data from member history table.
	 * @return	array
	 */
	public static function changesForHistory( array $changes, array $row ): array
	{
		if ( isset( $changes['idm_block_submissions'] ) )
		{
			return array( \IPS\Member::loggedIn()->language()->addToStack( 'history_restrictions_downloads_' . \intval( $changes['idm_block_submissions']['new'] ) ) );
		}
		return array();
	}
}
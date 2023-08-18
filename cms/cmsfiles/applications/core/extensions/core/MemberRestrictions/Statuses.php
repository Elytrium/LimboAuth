<?php
/**
 * @brief		Member Restrictions: Statuses
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Nov 2017
 */

namespace IPS\core\extensions\core\MemberRestrictions;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Member Restrictions: Statuses
 */
class _Statuses extends \IPS\core\MemberACPProfile\Restriction
{
	/**
	 * Is this extension available?
	 *
	 * @return	bool
	 */
	public function enabled()
	{
		return $this->member->canAccessModule( \IPS\Application\Module::get( 'core', 'members' ) );
	}

	/**
	 * Modify Edit Restrictions form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( \IPS\Helpers\Form $form )
	{
		/* There are two columns for "can post status updates". The first is a user-level option (the user can turn status updates on and
			off in their account), and the second is an admin-level option (block user from posting status updates). Having two options is
			confusing so we merge both options into one select list */
		$value = ( !$this->member->members_bitoptions['bw_no_status_update'] AND $this->member->pp_setting_count_comments ) ? 0 :
			( !$this->member->members_bitoptions['bw_no_status_update'] ? 1 : 2 );

		$form->add( new \IPS\Helpers\Form\Radio( 'bw_no_status_update', $value, FALSE, array( 'options' => array(
			0 => 'members_disable_pm_on',
			1 => 'members_disable_pm_member_disable',
			2 => 'members_disable_pm_admin_disable',
		) ) ) );
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
		
		$old = ( !$this->member->members_bitoptions['bw_no_status_update'] AND $this->member->pp_setting_count_comments ) ? 0 :
			( !$this->member->members_bitoptions['bw_no_status_update'] ? 1 : 2 );
		if ( $old != $values['bw_no_status_update'] )
		{
			$return['bw_no_status_update'] = array( 'old' => $old, 'new' => $values['bw_no_status_update'] );
			$this->member->members_bitoptions['bw_no_status_update'] = ( $values['bw_no_status_update'] == 2 ) ? 1 : 0;
			$this->member->pp_setting_count_comments = ( $values['bw_no_status_update'] == 0 ) ? 1 : 0;
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
		
		if ( $this->member->members_bitoptions['bw_no_status_update'] )
		{
			$return[] = 'restriction_no_statuses';
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
		if ( isset( $changes['bw_no_status_update'] ) )
		{
			return array( \IPS\Member::loggedIn()->language()->addToStack( 'history_restrictions_status_updates_' . $changes['bw_no_status_update']['new'] ) );
		}
		return array();
	}
}
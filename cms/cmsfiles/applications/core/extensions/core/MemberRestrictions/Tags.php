<?php
/**
 * @brief		Member Restrictions: Tags
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
 * @brief	Member Restrictions: Tags
 */
class _Tags extends \IPS\core\MemberACPProfile\Restriction
{
	/**
	 * Is this extension available?
	 *
	 * @return	bool
	 */
	public function enabled()
	{
		return \IPS\Settings::i()->tags_enabled and ( !$this->member->group['gbw_disable_tagging'] or !$this->member->group['gbw_disable_prefixes'] );
	}

	/**
	 * Modify Edit Restrictions form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( \IPS\Helpers\Form $form )
	{
		if ( !$this->member->group['gbw_disable_tagging'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_tagging', !$this->member->members_bitoptions['bw_disable_tagging'] ) );
		}
		if ( !$this->member->group['gbw_disable_prefixes'] )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'bw_disable_prefixes', !$this->member->members_bitoptions['bw_disable_prefixes'] ) );
		}
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
		
		if ( !$this->member->group['gbw_disable_tagging'] AND array_key_exists( 'bw_disable_tagging', $values ) )
		{
			if ( $this->member->members_bitoptions['bw_disable_tagging'] == $values['bw_disable_tagging'] )
			{
				$return['bw_disable_tagging'] = array( 'old' => $this->member->members_bitoptions['bw_disable_tagging'], 'new' => !$values['bw_disable_tagging'] );
				$this->member->members_bitoptions['bw_disable_tagging'] = !$values['bw_disable_tagging'];
			}
		}
		if ( !$this->member->group['gbw_disable_prefixes'] AND array_key_exists( 'bw_disable_prefixes', $values ) )
		{
			if ( $this->member->members_bitoptions['bw_disable_prefixes'] == $values['bw_disable_prefixes'] )
			{
				$return['bw_disable_prefixes'] = array( 'old' => $this->member->members_bitoptions['bw_disable_prefixes'], 'new' => !$values['bw_disable_prefixes'] );
				$this->member->members_bitoptions['bw_disable_prefixes'] = !$values['bw_disable_prefixes'];
			}
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
		
		if ( !$this->member->group['gbw_disable_tagging'] and $this->member->members_bitoptions['bw_disable_tagging'] )
		{
			$return[] = 'restriction_no_tagging';
		}
		if ( !$this->member->group['gbw_disable_prefixes'] and $this->member->members_bitoptions['bw_disable_prefixes'] )
		{
			$return[] = 'restriction_no_prefixes';
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
		$return = array();
		
		foreach ( array( 'bw_disable_tagging', 'bw_disable_prefixes' ) as $k )
		{
			if ( isset( $changes[ $k ] ) )
			{
				$return[] = \IPS\Member::loggedIn()->language()->addToStack( 'history_restrictions_' . $k . '_' . \intval( $changes[ $k ]['new'] ) );
			}
		}
		
		return $return;
	}
}
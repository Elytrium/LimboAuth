<?php
/**
 * @brief		Member Restrictions: Content
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
 * @brief	Member Restrictions: Content
 */
class _Content extends \IPS\core\MemberACPProfile\Restriction
{
	/**
	 * Modify Edit Restrictions form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( \IPS\Helpers\Form $form )
	{
		$form->add( new \IPS\Helpers\Form\Date( 'restrict_post', $this->member->restrict_post, FALSE, array( 'time' => TRUE, 'unlimited' => -1, 'unlimitedLang' => 'indefinitely' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('until') ) );
		$form->add( new \IPS\Helpers\Form\Date( 'mod_posts', $this->member->mod_posts, FALSE, array( 'time' => TRUE, 'unlimited' => -1, 'unlimitedLang' => 'indefinitely' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('until') ) );
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
		
		$modPosts = \is_object( $values['mod_posts'] ) ? $values['mod_posts']->getTimestamp() : ( $values['mod_posts'] ?: 0 );
		if ( $modPosts != $this->member->mod_posts )
		{
			$return['mod_posts'] = array( 'old' => $this->member->mod_posts, 'new' => $modPosts );
			$this->member->mod_posts = $modPosts;
		}
		
		$restrictPost = \is_object( $values['restrict_post'] ) ? $values['restrict_post']->getTimestamp() : ( $values['restrict_post'] ?: 0 );
		if ( $restrictPost != $this->member->restrict_post )
		{
			$return['restrict_post'] = array( 'old' => $this->member->restrict_post, 'new' => $restrictPost );
			$this->member->restrict_post = $restrictPost;
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
		
		if ( $this->member->restrict_post )
		{
			$return[] = 'moderation_nopost';
		}
		elseif ( $this->member->mod_posts )
		{
			$return[] = 'moderation_modq';
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

		foreach ( array( 'modq' => 'mod_posts', 'nopost' => 'restrict_post', 'banned' => 'temp_ban' ) as $k => $v )
		{
			if ( isset( $changes[ $v ] ) )
			{
				if ( $changes[ $v ]['new'] )
				{
					$c = \IPS\Member::loggedIn()->language()->addToStack( 'moderation_' . $k );
					if ( $changes[ $v ]['new'] != -1 )
					{
						$interval = \IPS\DateTime::formatInterval( ( \is_numeric( $changes[ $v ]['new'] ) ) ? \IPS\DateTime::ts( $row['log_date'] )->diff( \IPS\DateTime::ts( $changes[ $v ]['new'] ) ) : new \DateInterval( $changes[ $v ]['new'] ), 2 );
						$c = \IPS\Member::loggedIn()->language()->addToStack( 'history_received_warning_penalty_time', FALSE, array( 'sprintf' => array( $c, $interval ) ) );
					}
				}
				else
				{
					$c = \IPS\Member::loggedIn()->language()->addToStack( 'history_warning_revoke_' . $v );
				}
				$return[] = $c;
			}
		}
		
		return $return;
	}
}
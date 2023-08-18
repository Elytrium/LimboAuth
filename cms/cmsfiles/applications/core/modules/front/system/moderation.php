<?php
/**
 * @brief		Moderation
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Moderation
 */
class _moderation extends \IPS\Dispatcher\Controller
{
	/**
	 * Flag Member As Spammer
	 *
	 * @return	void
	 */
	protected function flagAsSpammer()
	{
		\IPS\Session::i()->csrfCheck();
		
		$member = \IPS\Member::load( \IPS\Request::i()->id );

		$redirectTarget = ( \IPS\Request::i()->referrer ) ? \IPS\Http\Url::createFromString( urldecode( \IPS\Request::i()->referrer ), TRUE ) : $member->url();

		if ( $member->member_id and $member->member_id != \IPS\Member::loggedIn()->member_id and \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') and !$member->modPermission() and !$member->isAdmin() )
		{
			if ( \IPS\Request::i()->s )
			{
				$member->flagAsSpammer();
				\IPS\Session::i()->modLog( 'modlog__spammer_flagged', array( $member->name => FALSE ) );

				$actions = explode( ',', \IPS\Settings::i()->spm_option );

				/* Redirect to the users profile if we're deleting the content to avoid that the moderator sees a 404 error message (unless soft delete is enabled) */
				if ( \in_array( 'delete', $actions ) AND !( \IPS\Member::loggedIn()->modPermission('can_manage_deleted_content') AND \IPS\Settings::i()->dellog_retention_period ) )
				{
					$redirectTarget = $member->url();
				}
			}
			else
			{
				$member->unflagAsSpammer();
				\IPS\Session::i()->modLog( 'modlog__spammer_unflagged', array( $member->name => FALSE ) );
			}
		}

		\IPS\Output::i()->redirect( $redirectTarget, ( \IPS\Request::i()->s ) ? 'account_flagged' : 'account_unflagged');
	}
}

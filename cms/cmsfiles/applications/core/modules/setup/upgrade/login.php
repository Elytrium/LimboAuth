<?php
/**
 * @brief		Upgrader: Login
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Login
 */
class _login extends \IPS\Dispatcher\Controller
{
	/**
	 * Show login form and/or process login form
	 *
	 * @todo	[Upgrade] Will also need to account for things in the input (e.g. password) that would be replaced, like & to &amp;
	 * @return	void
	 */
	public function manage()
	{
		/* Clear previous session data */
		if( !isset( \IPS\Request::i()->sessionCheck ) AND \count( $_SESSION ) )
		{
			foreach( $_SESSION as $k => $v )
			{
				unset( $_SESSION[ $k ] );
			}
		}

		/* Store a session variable and then check it on the next page load to make sure PHP sessions are working */
		if( !isset( \IPS\Request::i()->sessionCheck ) )
		{
			$_SESSION['sessionCheck'] = TRUE;
			\IPS\Output::i()->redirect( \IPS\Request::i()->url()->setQueryString( 'sessionCheck', 1 ), NULL, 307 ); // 307 instructs the browser to resubmit the form as a POST request maintaining all the values from before
		}
		else
		{
			if( !isset( $_SESSION['sessionCheck'] ) OR !$_SESSION['sessionCheck'] )
			{
				\IPS\Output::i()->error( 'session_check_fail', '5C289/1', 500, '' );
			}
		}

		/* Are we automatically logging in? */
		if ( isset( \IPS\Request::i()->autologin ) and isset( \IPS\Request::i()->cookie['IPSSessionAdmin'] ) )
		{
			try
			{
				$session = \IPS\Db::i()->select( '*', 'core_sys_cp_sessions', array( 'session_id=?', \IPS\Request::i()->cookie['IPSSessionAdmin'] ) )->first();
				$member = $session['session_member_id'] ? \IPS\Member::load( $session['session_member_id'] ) : new \IPS\Member;
				if ( $member->member_id and $this->_memberHasUpgradePermission( $member ) and ( !\IPS\Settings::i()->match_ipaddress or ( $session['session_ip_address'] === \IPS\Request::i()->ipAddress() ) ) )
				{
					$_SESSION['uniqueKey'] = \IPS\Login::generateRandomString();
					\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=systemcheck" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
				}
			}
			catch( \UnderflowException $e ) {}
		}
		if ( \IPS\BYPASS_UPGRADER_LOGIN )
		{
			$_SESSION['uniqueKey']	= \IPS\Login::generateRandomString();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=systemcheck" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
		}
		
		/* Before going any further, make sure there is an actual upgrade to be done. This both provides a nicer experience (so
			people don't log in ust to be told there's nothing to upgrade) and prevents having a permenantly available login
			screen which doesn't use locking (which could be use to bruteforce an account) */
		$canUpgrade = FALSE;
		foreach( \IPS\Application::applications() as $app => $data )
		{
			$path = \IPS\Application::getRootPath( $app ) . '/applications/' . $app;
			
			if ( $app != 'chat' and is_dir( $path . '/data' ) )
			{
				$currentVersion		= \IPS\Application::load( $app )->long_version;
				$availableVersion	= \IPS\Application::getAvailableVersion( $app );
	
				if ( empty( $errors ) AND $availableVersion > $currentVersion )
				{
					$canUpgrade = TRUE;
				}
			}
		}

		/* We need to allow logins if the previous upgrade wasn't finished */
		if( \IPS\Db::i()->checkForTable( 'upgrade_temp' ) )
		{
			$canUpgrade = TRUE;
		}

		if ( !$canUpgrade )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'forms' )->noapps();

			/* We do this just to be 100% certain the flag didn't get "stuck" and needs to be reset. */
			\IPS\core\Setup\Upgrade::setUpgradingFlag( FALSE );
			return;
		}
		
		/* Nope, show a form */
		$error = NULL;
		if ( isset( \IPS\Request::i()->auth ) and isset( \IPS\Request::i()->password ) )
		{
			$table = 'core_members';
			if ( \IPS\Db::i()->checkForTable( 'members' ) AND !\IPS\Db::i()->checkForTable( 'core_members' ) )
			{
				$table = 'members';
			}
			
			$memberRows = \IPS\Db::i()->select( '*', $table, array( 'email=? OR email=? OR name=? OR name=?', \IPS\Request::i()->auth, \IPS\Request::legacyEscape( \IPS\Request::i()->auth ), \IPS\Request::i()->auth, \IPS\Request::legacyEscape( \IPS\Request::i()->auth ) ) );
			if ( \count( $memberRows ) )
			{
				foreach( $memberRows as $memberRow )
				{
					$member = \IPS\Member::constructFromData( $memberRow );
					
					if ( password_verify( \IPS\Request::i()->password, $member->members_pass_hash ) or $member->verifyLegacyPassword( \IPS\Request::i()->protect('password') ) )
					{
						if ( $this->_memberHasUpgradePermission( $member ) )
						{
							$_SESSION['uniqueKey']	= \IPS\Login::generateRandomString();
							\IPS\IPS::resyncIPSCloud('Beginning upgrade');
							\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=systemcheck" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
						}
						else
						{
							$error = 'login_upgrader_no_permission';
						}
					}
					else
					{
						$error = 'login_err_bad_password';
					}
				}
			}
			else
			{
				$error = 'login_err_no_account';
			}
		}

		if ( $error )
		{
			$error = \IPS\Member::loggedIn()->language()->addToStack( $error, FALSE, array( 'pluralize' => array( 3 ) ) );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('login');
		\IPS\Output::i()->output 	.= \IPS\Theme::i()->getTemplate( 'forms' )->login( $error );
	}
	
	/**
	 * Can member log into upgrader?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	protected function _memberHasUpgradePermission( \IPS\Member $member )
	{
		/* 4.x */
		if ( \IPS\Db::i()->checkForTable( 'core_admin_permission_rows' ) )
		{
			/* This permission was added in 4.1.6, so if we have it, use it */
			if ( \IPS\Application::load('core')->long_version > 101021 )
			{
				return $member->hasAcpRestriction( 'core', 'overview', 'upgrade_manage' );
			}
			/* Otherwise, let them in if they're an admin */
			else
			{
				return $member->isAdmin();
			}
		}
		/* 3.x */
		else
		{
			/* Does our primary group have permission? */
			try
			{
				$admin = (bool) \IPS\Db::i()->select( 'g_access_cp', 'groups', array( 'g_id=?', $member->member_group_id ) )->first();
			}
			catch( \UnderflowException $e )
			{
				throw new \OutOfRangeException( 'upgrade_group_not_exist' );
			}

			if( $admin )
			{
				return TRUE;
			}

			/* Check secondary groups as well */
			if( $member->mgroup_others )
			{
				/* In some versions we stored as ",1,2," with trailing/preceeding commas, so account for that */
				foreach( explode( ',', trim( $member->mgroup_others, ',' ) ) as $group )
				{
					try
					{
						$admin = (bool) \IPS\Db::i()->select( 'g_access_cp', 'groups', array( 'g_id=?', $group ) )->first();

						if( $admin )
						{
							return TRUE;
						}
					}
					/* It is possible the user has an old group that no longer exists defined as a secondary group */
					catch( \UnderflowException $e ){}
				}
			}

			/* Still here? No permission */
			return FALSE;
		}
	}		
}
<?php
/**
 * @brief		Recount and Reset Tools
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Oct 2013
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Recount and Reset Tools
 */
class _reset extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_recount_content' );
		return parent::execute();
	}

	/**
	 * Queue the content recount task
	 *
	 * @return	void
	 */
	public function posts()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Task::queue( 'core', 'RecountMemberContent', array(), 4 );
		\IPS\Session::i()->log( 'acplog__recount_member_content' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members' ), \IPS\Member::loggedIn()->language()->addToStack( 'member_recount_content_process' ) );
	}

	/**
	 * Queue the reputation recount task
	 *
	 * @return	void
	 */
	public function rep()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Task::queue( 'core', 'RecountMemberReputation', array(), 4 );
		\IPS\Session::i()->log( 'acplog__recount_member_rep' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members' ), \IPS\Member::loggedIn()->language()->addToStack( 'member_recount_rep_process' ) );
	}
}
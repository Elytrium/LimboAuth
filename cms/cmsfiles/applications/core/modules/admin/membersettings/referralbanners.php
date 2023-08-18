<?php
/**
 * @brief		referralbanners
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Core
 * @since		7 Aug 2019
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * referralbanners
 */
class _referralbanners extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\ReferralBanner';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;

	/**
	 * Title can contain HTML?
	 */
	public $_titleHtml = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'referrals_manage', 'core', 'membersettings' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( 'referral_banners_blurb', TRUE, TRUE );
		return parent::manage();
	}
	
	/**
	 * Form
	 *
	 * @return	void
	 */
	public function form()
	{
		parent::form();
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('referral_banners');
	}
}
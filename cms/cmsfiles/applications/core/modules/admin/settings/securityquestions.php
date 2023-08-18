<?php
/**
 * @brief		Security Questions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		26 Aug 2016
 */

namespace IPS\core\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Security Questions
 */
class _securityquestions extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\MFA\SecurityQuestions\Question';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;

	/**
	 * Form
	 *
	 * @return	void
	 */
	public function form()
	{
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal('app=core&module=settings&controller=mfa&tab=handlers'), \IPS\Member::loggedIn()->language()->addToStack('menu__core_settings_mfa') );
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal('app=core&module=settings&controller=mfa&tab=questions&do=settings&key=questions'), \IPS\Member::loggedIn()->language()->addToStack("mfa_questions_title") );
		parent::form();
	}
		
	/**
	 * Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		return $this->manage();
	}
	
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array() );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=settings&controller=mfa&tab=handlers&do=settings&key=questions&tab=questions'), 'saved' );
		}
	}
}
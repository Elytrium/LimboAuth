<?php
/**
 * @brief		reportedContent
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Dec 2017
 */

namespace IPS\core\modules\admin\moderation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * reportedContent
 */
class _reportedContent extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\core\Reports\Rules';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		/* Add a button for settings */
		\IPS\Output::i()->sidebar['actions'] = array(
			'settings'	=> array(
				'title'		=> 'reportedContent_settings',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=reportedContent&do=settings' ),
				'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('reportedContent_settings') )
			),
			'types'	=> array(
				'title'		=> 'reportedContent_types',
				'icon'		=> 'cog',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=moderation&controller=reportedContentTypes' )
			)
		);

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('forms')->blurb( 'automaticmoderation_blurb' );
		
		if ( ! \IPS\Settings::i()->automoderation_enabled )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate('forms')->blurb( 'automaticmoderation_disabled_blurb' );
		}
		
		\IPS\Dispatcher::i()->checkAcpPermission( 'reportedContent_manage' );
		parent::execute();
	}
	
	/**
	 * Profile Settings
	 *
	 * @return	void
	 */
	protected function settings()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'reportedContent_manage' );

		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\YesNo( 'automoderation_enabled', \IPS\Settings::i()->automoderation_enabled, FALSE, array(), NULL, NULL, NULL, 'automoderation_enabled' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'automoderation_report_again_mins', \IPS\Settings::i()->automoderation_report_again_mins, FALSE, array(), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('automoderation_report_again_mins_suffix'), 'automoderation_report_again_mins' ) );
		
		if ( $values = $form->values() )
		{
			$form->saveAsSettings();
			
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => (int) $values['automoderation_enabled'] ), array( '`key`=?', 'automaticmoderation' ) );
			
			\IPS\Session::i()->log( 'acplog__automoderation_settings' );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=reportedContent' ), 'saved' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('reportedContent_settings');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate('global')->block( 'reportedContent_settings', $form, FALSE );
	}
}
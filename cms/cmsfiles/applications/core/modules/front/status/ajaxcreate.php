<?php
/**
 * @brief        Status Updates Feed
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * @since        15 Aug 2014
 * @version        SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\front\status;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY')) {
	header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
	exit;
}

/**
 * Status Updates
 */
class _ajaxcreate extends \IPS\Dispatcher\Controller
{
	/**
	 * Status Update Create Form
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( !\IPS\Settings::i()->profile_comments )
		{
			\IPS\Output::i()->error('node_error', '2C231/2', 403, '');
		}

		if ( \IPS\Member::loggedIn()->member_id and !\IPS\Member::loggedIn()->pp_setting_count_comments )
		{
			\IPS\Output::i()->redirect( \IPS\Member::loggedIn()->url()->setQueryString( 'tab', 'statuses' ) );
		}

		if ( !\IPS\core\Statuses\Status::canCreateFromCreateMenu() )
		{
			\IPS\Output::i()->error('no_module_permission', '2C231/1', 403, '');
		}

		$form = new \IPS\Helpers\Form( 'new_status', 'status_new' );
		foreach ( \IPS\core\Statuses\Status::formElements( NULL, NULL, TRUE ) AS $k => $element )
		{
			$form->add( $element );
		}
		
		if ( $values = $form->values() )
		{
			$values['status_content'] = $values['status_content_ajax'];
			$status = \IPS\core\Statuses\Status::createFromForm( $values );
			if( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array(
					'redirect'	=> (string) \IPS\Member::loggedIn()->url(),
					'message'	=> '',
					'content'	=> \IPS\Theme::i()->getTemplate( 'widgets' )->recentStatusUpdatesStatus( $status )
				) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Member::loggedIn()->url(), NULL, 303 );
			}
		}
		
		$form = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'statusPopupTemplate' ) );
		
		if ( \IPS\core\Statuses\Status::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			$form = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( NULL, TRUE, FALSE ) . $form;
		}
		\IPS\Output::i()->output = $form;
	}
}
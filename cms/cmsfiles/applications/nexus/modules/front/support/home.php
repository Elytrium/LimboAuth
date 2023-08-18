<?php
/**
 * @brief		Support Index
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		08 Apr 2014
 */

namespace IPS\nexus\modules\front\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Index
 */
class _home extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X265/1', 403, '' );
		}
		
		parent::execute();
	}
	
	/**
	 * Index
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$table = new \IPS\Helpers\Table\Content( 'IPS\nexus\Support\Request', \IPS\Http\Url::internal( 'app=nexus&module=support&controller=home', 'front', 'support' ) );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'support' ), 'tableRows' );
		$table->classes[] = 'ipsDataList_large';
		$table->showFilters = FALSE;
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('support');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('support')->requestList( (string) $table );
	}
	
	/**
	 * Create
	 *
	 * @return	void
	 */
	protected function create()
	{		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('new_request');
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'new_request' ) );

		$accounts = \IPS\nexus\Customer::loggedIn()->parentContacts( array( 'support=1' ) );
		if ( \count( $accounts ) and !isset( \IPS\Request::i()->account ) )
		{
			$options = array();
			$options[ \IPS\nexus\Customer::loggedIn()->member_id ] = \IPS\nexus\Customer::loggedIn()->language()->addToStack( 'my_account', FALSE, array( 'sprintf' => array( \IPS\nexus\Customer::loggedIn()->cm_name ) ) );
			foreach ( $accounts as $contact )
			{
				$options[ $contact->main_id->member_id ] = $contact->main_id->cm_name;
			}
			
			$form = new \IPS\Helpers\Form( 'account', 'continue' );
			$form->class = 'ipsForm_vertical';
			$form->add( new \IPS\Helpers\Form\Radio( 'support_account', \IPS\nexus\Customer::loggedIn()->member_id, TRUE, array( 'options' => $options, 'parse' => 'normal' ) ) );
			if ( $values = $form->values() )
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=support&controller=home&do=create&account={$values['support_account']}", 'front', 'support_create' ) );
			}
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('support')->create( \IPS\nexus\Support\Request::create() );
		}
	}
}
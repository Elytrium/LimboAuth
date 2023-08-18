<?php
/**
 * @brief		Support Departments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		08 Apr 2014
 */

namespace IPS\nexus\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Departments
 */
class _departments extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Support\Department';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'departments_manage' );
		parent::execute();
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		try
		{
			$node = \IPS\nexus\Support\Department::load( \IPS\Request::i()->id );

			if( \IPS\Settings::i()->contact_type == 'contact_nexus_department' AND \IPS\Settings::i()->contact_nexus_department == $node->id )
			{
				\IPS\Output::i()->error( 'nexus_nodeletedept_contactus', '1X344/1', 403, '' );
			}

			if ( $node->canDelete() and \IPS\Db::i()->select( 'COUNT(*)', 'nexus_support_requests', array( 'r_department=?', $node->id ) ) )
			{
				$form = new \IPS\Helpers\Form( 'delete', 'delete' );
				$form->add( new \IPS\Helpers\Form\Node( 'move_existing_requests_to', NULL, TRUE, array( 'class' => 'IPS\nexus\Support\Department', 'permissionCheck' => function( $_node ) use ( $node )
				{
					return $node->id != $_node->id;
				} ) ) );
				if ( $values = $form->values() )
				{
					\IPS\Db::i()->update( 'nexus_support_requests', array( 'r_department' => $values['move_existing_requests_to']->id ), array( 'r_department=?', $node->id ) );
					\IPS\Request::i()->form_submitted = TRUE;
					\IPS\Request::i()->wasConfirmed = TRUE;
					return parent::delete();
				}

				\IPS\Output::i()->output = $form;
				return;
			}
		}
		catch ( \OutOfRangeException $e ){}

		return parent::delete();
	}
}
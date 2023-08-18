<?php
/**
 * @brief		Profile Completion
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		04 Jan 2018
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile Completion
 */
class _profilecompletion extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = '\IPS\Member\ProfileStep';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;
	
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$return = parent::_getRootButtons();
		
		if ( isset( \IPS\Output::i()->sidebar['actions']['add'] ) )
		{
			$class = new \IPS\Member\ProfileStep;
			if ( ! $class->canAdd() )
			{
				unset( \IPS\Output::i()->sidebar['actions']['add'] );
			}
		}
		
		return $return;
	}
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'profilefields_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		$class = new \IPS\Member\ProfileStep;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'members' )->profileCompleteBlurb( $class->canAdd() );
		parent::manage();
	}

	/**
	 * Enable quick registration
	 *
	 * @return	void
	 */
	public function enableQuickRegister()
	{
		\IPS\Session::i()->csrfCheck();
		
		\IPS\Settings::i()->changeValues( array( 'quick_register' => 1 ) );
		
		\IPS\Session::i()->log( 'acplog__quick_register_enabled' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=membersettings&controller=profiles&tab=profilecompletion' ), 'profile_complete_quick_register_off_enabled' );
	}
	
	/**
	 * Add Step Form
	 *
	 * @return	void
	 */
	public function form()
	{
		$form = new \IPS\Helpers\Form;
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$step = \IPS\Member\ProfileStep::load( \IPS\Request::i()->id );
			}
			catch( \OutOFRangeException $e )
			{
				\IPS\Output::i()->error( 'node_error', '2C360/1', 404, '' );
			}
		}
		else
		{
			$step = new \IPS\Member\ProfileStep;
		}
		
		$step->form( $form );
		
		if ( $values = $form->values() )
		{
			$values = $step->formatFormValues( $values );
			
			$step->extension = \IPS\Member\ProfileStep::findExtensionFromAction( $values['step_completion_act'] );
			$step->required = ( isset( $values['step_required'] ) ) ? $values['step_required'] : FALSE;		
			$step->completion_act = $values['step_completion_act'];
			$step->subcompletion_act = isset( $values['step_subcompletion_act'] ) ? $values['step_subcompletion_act'] : NULL;
			$step->save();
			
			$step->postSaveForm( $values );
			
			if ( method_exists( $step->extension, 'postAcpSave' ) )
			{
				$step->extension->postAcpSave( $step, $values );
			}
			
			\IPS\Member::updateAllMembers( array( "members_bitoptions2 = members_bitoptions2 & ~" . \IPS\Member::$bitOptions['members_bitoptions']['members_bitoptions2']['profile_completed'] ) );
			
			\IPS\Session::i()->log( 'acplog__profile_step_added', array( "profile_step_title_{$step->id}" => TRUE ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=membersettings&controller=profiles&tab=profilecompletion" ), 'saved' );
		}

		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'profile_completion' );
		\IPS\Output::i()->output	= $form;
	}
}
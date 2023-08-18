<?php
/**
 * @brief		Custom Fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		08 Sep 2014
 */

namespace IPS\nexus\modules\front\clients;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Custom Fields
 */
class _info extends \IPS\Dispatcher\Controller
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
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X242/1', 403, '' );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=info', 'front', 'clientsinfo' ), \IPS\Member::loggedIn()->language()->addToStack('client_info') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('client_info');
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		
		if ( $output = \IPS\MFA\MFAHandler::accessToArea( 'nexus', 'BillingInfo', \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=info', 'front', 'clientsinfo' ) ) )
		{
			$form = new \IPS\Helpers\Form;
			foreach( $this->_buildInfoForm( TRUE ) AS $formElement )
			{
				$form->add( $formElement );
			}
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->info( $form ) . $output;
			return;
		}
		
		parent::execute();
	}
	
	/**
	 * Edit Info
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;
		foreach( $this->_buildInfoForm() AS $formElement )
		{
			$form->add( $formElement );
		}
		
		if ( $values = $form->values() )
		{
			$changes = array();
			foreach( array( 'cm_first_name', 'cm_last_name' ) AS $nameField )
			{
				if ( isset( $values[ $nameField ] ) )
				{
					if ( $values[ $nameField ] != \IPS\nexus\Customer::loggedIn()->$nameField )
					{
						/* We only need to log this once, so do it if it isn't set */
						if ( !isset( $changes['name'] ) )
						{
							$changes['name'] = \IPS\nexus\Customer::loggedIn()->cm_name;
						}
						
						\IPS\nexus\Customer::loggedIn()->$nameField = $values[ $nameField ];
					}
				}
			}
			
			foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
			{
				$column = $field->column;
				$helper = $field->buildHelper();
				if ( $helper instanceof \IPS\Helpers\Form\Upload )
				{
					$valueToSave = (string) $values["nexus_ccfield_{$field->id}"];
				}
				else
				{
					$valueToSave = $helper::stringValue( $values["nexus_ccfield_{$field->id}"] );
				}
				if ( \IPS\nexus\Customer::loggedIn()->$column != $valueToSave )
				{
					$changes['other'][] = array( 'name' => 'nexus_ccfield_' . $field->id, 'value' => $field->displayValue( $valueToSave ), 'old' => $field->displayValue( \IPS\nexus\Customer::loggedIn()->$column ) );
					\IPS\nexus\Customer::loggedIn()->$column = $valueToSave;
				}
				
				if ( $field->type === 'Editor' )
				{
					$field->claimAttachments( \IPS\nexus\Customer::loggedIn()->member_id );
				}
			}
			if ( !empty( $changes ) )
			{
				\IPS\nexus\Customer::loggedIn()->log( 'info', $changes );
			}
			\IPS\nexus\Customer::loggedIn()->save();
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=info', 'front', 'clientsinfo' ) );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('clients')->info( $form );
	}
	
	/**
	 * Build Information Form
	 *
	 * @param	bool	$protected	If TRUE, current values will be blanked out
	 * @return	array
	 */
	protected function _buildInfoForm( $protected = FALSE )
	{
		$formElements = array();
		$formElements['cm_first_name']	= new \IPS\Helpers\Form\Text( 'cm_first_name', $protected ? NULL : \IPS\nexus\Customer::loggedIn()->cm_first_name, TRUE );
		$formElements['cm_last_name']	= new \IPS\Helpers\Form\Text( 'cm_last_name', $protected ? NULL : \IPS\nexus\Customer::loggedIn()->cm_last_name, TRUE );
		foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
		{
			$column = $field->column;
			if ( $field->type === 'Editor' )
			{
				$field::$editorOptions = array_merge( $field::$editorOptions, array( 'attachIds' => array( \IPS\nexus\Customer::loggedIn()->member_id ) ) );
			}
			$formElements[ $column ] = $field->buildHelper( $protected ? NULL : \IPS\nexus\Customer::loggedIn()->$column );
		}
		
		return $formElements;
	}
}
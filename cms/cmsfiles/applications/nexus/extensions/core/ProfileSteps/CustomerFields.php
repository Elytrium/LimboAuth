<?php
/**
 * @brief		Profile Completion Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 May 2017
 */

namespace IPS\nexus\extensions\core\ProfileSteps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile fields extension
 */
class _CustomerFields
{
	/**
	 * Available parent actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function actions()
	{
		$return = array();
		
		if ( \IPS\Db::i()->select( 'COUNT(*)', 'nexus_customer_fields' )->first() )
		{
			$return['customer_fields'] = 'complete_profile_app__nexus_CustomerFields';
		}
		
		return $return;
	}
	
	/**
	 * Available sub actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function subActions()
	{
		$return = array();
		
		foreach( \IPS\Db::i()->select( '*', 'nexus_customer_fields' ) as $field )
		{
			$field = \IPS\nexus\Customer\CustomField::constructFromData( $field );
			$return['customer_fields'][ 'nexus_ccfield_' . $field->_id ] = 'nexus_ccfield_' . $field->_id;
		}
		
		return $return;
	}
	
	/**
	 * Can the actions have multiple choices?
	 *
	 * @param	string		$action		Action key (basic_profile, etc)
	 * @return	boolean
	 */
	public static function actionMultipleChoice( $action )
	{
		return TRUE;
	}

	/**
	 * Can be set as required?
	 *
	 * @return	array
	 * @note	This is intended for items which have their own independent settings and dedicated enable pages, such as MFA and Social Login integration
	 */
	public static function canBeRequired()
	{
		return array( 'customer_fields' );
	}
	
	/**
	 * Has a specific step been completed?
	 *
	 * @param	\IPS\Member\ProfileStep	$step	The step to check
	 * @param	\IPS\Member|NULL		$member	The member to check, or NULL for currently logged in
	 * @return	bool
	 */
	public function completed( \IPS\Member\ProfileStep $step, \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		if ( !$member->member_id )
		{
			return FALSE;
		}
		try
		{
			$profileFields = iterator_to_array( \IPS\Db::i()->select( '*', 'nexus_customers', array( 'member_id=?', $member->member_id ) ) );
		}
		catch( \Exception $e )
		{
			return FALSE;
		}
		
		if ( ! \count( $profileFields ) )
		{
			return FALSE;
		}
		
		$done = 0;
		foreach( $step->subcompletion_act as $item )
		{
			$fieldId = \substr( $item, 14 );
			
			foreach( $profileFields AS $key => $value )
			{
				if ( $key == 'field_' . $fieldId )
				{
					if ( (bool) $value )
					{
						$done++;
					}
				}
			}
		}
		
		return ( $done === \count( $step->subcompletion_act ) );
	}
	
	/**
	 * Action URL
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member, or NULL for currently logged in
	 * @return	\IPS\Http\Url
	 */
	public function url( $action, \IPS\Member $member = NULL )
	{
		return \IPS\Http\Url::internal( "app=core&module=members&controller=profile&do=edit&id={$member->member_id}", 'front', 'edit_profile', $member->members_seo_name );
	}
	
	/**
	 * Post ACP Save
	 *
	 * @param	\IPS\Member\ProfileStep		$step	The step
	 * @param	array						$values	Form Values
	 * @return	void
	 */
	public function postAcpSave( \IPS\Member\ProfileStep $step, array $values )
	{
		$subActions = static::subActions()['customer_fields'];
		
		/* If we are going to add a profile field to a step, or even require it, we need to make sure the actual field is updated */
		foreach( $subActions AS $key )
		{
			if ( \in_array( $key, $values['step_subcompletion_act'] ) )
			{
				$fieldId = \substr( $key, 14 );
				$update = array();
				$update['f_reg_show'] = 1;
				if ( $step->required )
				{
					$update['f_reg_require'] = 1;
				}
				
				\IPS\Db::i()->update( 'nexus_customer_fields', $update, array( "f_id=?", $fieldId ) );
			}
		}
	}
	
	/**
	 * Format Form Values
	 *
	 * @param	array				$values	The form values
	 * @param	\IPS\Member			$member	The member
	 * @param	\IPS\Helpers\Form	$form	The form object
	 * @return	void
	 */
	public static function formatFormValues( $values, &$member, &$form )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$profileFields = array();
		foreach ( \IPS\nexus\Customer\CustomField::roots() as $field )
		{
			if ( isset( $values[ "nexus_ccfield_{$field->_id}"] ) )
			{
				if( $field->required and ( $values[ "nexus_ccfield_{$field->_id}" ] === NULL or !isset( $values[ "nexus_ccfield_{$field->_id}" ] ) ) )
				{
					\IPS\Output::i()->error( 'reg_required_fields', '1C223/5', 403, '' );
				}
				
				$helper = $field->buildHelper();
				$profileFields[ "field_{$field->_id}" ] = $helper::stringValue( $values[ "nexus_ccfield_{$field->_id}" ] );
				
				if ( $helper instanceof \IPS\Helpers\Form\Editor )
				{
					$field->claimAttachments( $member->member_id );
				}
			}
		}
		
		if ( \count( $profileFields ) )
		{
			try
			{
				\IPS\Db::i()->select( 'member_id', 'nexus_customers', array( "member_id=?", $member->member_id ) )->first();
				\IPS\Db::i()->update( 'nexus_customers', $profileFields, array( "member_id=?", $member->member_id ) );
			}
			catch( \Exception $e )
			{
				\IPS\Db::i()->insert( 'nexus_customers', array_merge( array( 'member_id' => $member->member_id ), array_merge( array( 'member_id' => $member->member_id ), $profileFields ) ) );
			}
		}
	}
	
	/**
	 * Wizard Steps
	 *
	 * @param	\IPS\Member|NULL	$member	The member completing the wizard, or NULL for currently logged in member
	 * @return	array
	 */
	public static function wizard( \IPS\Member $member = NULL )
	{
		$include = array();
		$member = $member ?: \IPS\Member::loggedIn();
		$wizards = array();
		
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			if ( $step->completion_act === 'customer_fields' AND ! $step->completed( $member ) )
			{
				$wizards[ $step->key ] = function( $data ) use ( $member, $include, $step ) {
					$form = new \IPS\Helpers\Form( 'customer_profile_fields_' . $step->id, 'profile_complete_next' );
					
					foreach( $step->subcompletion_act as $item )
					{
						$id		= \substr( $item, 14 );
						$field	= \IPS\nexus\Customer\CustomField::load( $id );
						$form->add( $field->buildHelper() );
					}
					
					if ( $values = $form->values() )
					{
						static::formatFormValues( $values, $member, $form );
						$member->save();
						
						return $values;
					}
					
					return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'profileCompleteTemplate' ), $step );
				};
			}
		}
		
		if ( \count( $wizards ) )
		{
			return $wizards;
		}
	}
	
	/**
	 * Resyncs when something external happens
	 *
	 * @param	\IPS\Member\ProfileStep		$step The step
	 * @return void
	 */
	public function resync( \IPS\Member\ProfileStep $step )
	{
		$subActions = array();
		
		foreach( $step->subcompletion_act as $item )
		{
			$fieldId = \substr( $item, 12 );
			try
			{
				\IPS\nexus\Customer\CustomField::load( $fieldId );
				$subActions[] = $item;
			}
			catch( \OutOfRangeException $e )
			{
				/* No longer exists.. */
			}
		}
		
		if ( \count( $subActions ) and \count( $subActions ) != $step->subcompletion_act )
		{
			$step->subcompletion_act = $subActions;
			$step->save();
		}
		else if ( ! \count( $subActions ) )
		{
			/* No fields left, so delete this */
			$step->delete();
		}
	 }
}
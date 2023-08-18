<?php
/**
 * @brief		Profile Completion Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		16 Nov 2016
 */

namespace IPS\core\extensions\core\ProfileSteps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile fields extension
 */
class _ProfileFields
{
	/**
	 * Available parent actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function actions()
	{
		$return = array();
		
		if ( \IPS\core\ProfileFields\Field::fieldData() )
		{
			$return['profile_fields'] = 'complete_profile_app__core_ProfileFields';
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
		
		foreach( \IPS\core\ProfileFields\Field::fieldData() AS $fieldData )
		{
			foreach( $fieldData AS $id => $field )
			{
				$field = \IPS\core\ProfileFields\Field::constructFromData( $field );
				if ( !$field->admin_only AND $field->member_edit )
				{
					$return['profile_fields'][ 'core_pfield_' . $field->_id ] = 'core_pfield_' . $field->_id;
				}
			}
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
		return array( 'profile_fields' );
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
		
		if ( ! $member->group['g_edit_profile'] )
		{
			/* Member has no permission to edit profile */
			return TRUE;
		}
		
		/* Does the member have any profile fields? */
		if ( ! \count( $member->profileFields( \IPS\core\ProfileFields\Field::PROFILE_COMPLETION ) ) ) 
		{
			return FALSE;
		}
		
		$done = 0;
		foreach( $step->subcompletion_act as $item )
		{
			$fieldId = \substr( $item, 12 );
			foreach( $member->profileFields( \IPS\core\ProfileFields\Field::PROFILE_COMPLETION, TRUE ) AS $group => $field )
			{
				foreach( $field AS $key => $value )
				{
					if ( $key == 'core_pfield_' . $fieldId )
					{
						if ( (bool) $value or $value === "0" )
						{
							$done++;
						}
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
		$subActions = static::subActions()['profile_fields'];
		
		/* If we are going to add a profile field to a step, or even require it, we need to make sure the actual field is updated */
		foreach( $subActions AS $key )
		{
			if ( \in_array( $key, $values['step_subcompletion_act'] ) )
			{
				$fieldId = \substr( $key, 12 );
				$update = array();
				$update['pf_show_on_reg'] = 1;
				$update['pf_not_null'] = $step->required;
				
				\IPS\Db::i()->update( 'core_pfields_data', $update, array( "pf_id=?", $fieldId ) );
			}
		}
		
		unset( \IPS\Data\Store::i()->profileFields );
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
		foreach ( \IPS\core\ProfileFields\Field::roots() as $field )
		{
			if ( isset( $values[ "core_pfield_{$field->_id}"] ) )
			{
				if( $field->required and ( $values[ "core_pfield_{$field->_id}" ] === NULL or !isset( $values[ "core_pfield_{$field->_id}" ] ) ) )
				{
					\IPS\Output::i()->error( 'reg_required_fields', '1C223/5', 403, '' );
				}
				
				$helper = $field->buildHelper();
				$profileFields[ "field_{$field->_id}" ] = $helper::stringValue( $values[ "core_pfield_{$field->_id}" ] );
				
				if ( $helper instanceof \IPS\Helpers\Form\Editor )
				{
					$field->claimAttachments( $member->member_id );
				}
			}
		}
		
		if ( \count( $profileFields ) )
		{
			/* Use insert into ... on duplicate key update here to cover both cases where the row exists or does not exist */
			\IPS\Db::i()->insert( 'core_pfields_content', array_merge( array( 'member_id' => $member->member_id ), $profileFields ), true );

			/* Track and sync the changed custom fields */
			$member->changedCustomFields = $profileFields;
			$member->save();
		}
	}
	
	/**
	 * Wizard Steps
	 *
	 * @param	\IPS\Member|NULL	$member	Member or NULL for currently logged in member
	 * @return	array
	 */
	public static function wizard( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		$wizards = array();
		
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			try
			{
				$values = \IPS\Db::i()->select( '*', 'core_pfields_content', array( 'member_id = ?', $member->member_id ) )->first();

				foreach( $values as $k => $v )
				{
					if( $k == 'member_id' )
					{
						continue;
					}

					$profileFields[ 'core_p' . $k ] = $v;
				}
			}
			catch( \UnderflowException $e )
			{
				$profileFields = array();
			}

			if ( $step->completion_act === 'profile_fields' AND ! $step->completed( $member ) )
			{
				$wizards[ $step->key ] = function( $data ) use ( $member, $step, $profileFields ) {
					$form = new \IPS\Helpers\Form( 'profile_profile_fields_' . $step->id, 'profile_complete_next' );
					
					foreach( $step->subcompletion_act as $item )
					{
						$id		= \substr( $item, 12 );
						$field	= \IPS\core\ProfileFields\Field::loadWithMember( $id, NULL, NULL, $member );

						$value = isset( $profileFields['core_pfield_' . $id] ) ? $profileFields['core_pfield_' . $id] : NULL;

						if ( \is_array( $value ) and $field->multiple )
						{
							$value = implode( ',', array_keys( explode( '<br>', $value ) ) );
						}
						
						$form->add( $field->buildHelper( $value ) );
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
	 * Post Delete
	 * 
	 * @param	\IPS\Member\ProfileStep		$step	The step
	 * @return	void
	 */
	public function onDelete( \IPS\Member\ProfileStep $step )
	{
		$subActions = static::subActions()['profile_fields'];

		$notChangeableFields = array(
			'CheckboxSet',
			'Radio'
		);

		/* If we are going to add a profile field to a step, or even require it, we need to make sure the actual field is updated */
		foreach( $subActions AS $key )
		{
			if ( \in_array( $key, $step->subcompletion_act ) )
			{
				$fieldId = \substr( $key, 12 );
				$update = array();
				
				try
				{
					$field 	= \IPS\core\ProfileFields\Field::load( $fieldId );
	
					if ( \in_array( $field->type, $notChangeableFields ) )
					{
						/* reset the pf_not_null field to 0 if this field can't be set via the fields form */
						if ( $step->required )
						{
							$update['pf_not_null'] = 0;
							\IPS\Db::i()->update( 'core_pfields_data', $update, array( "pf_id=?", $fieldId ) );
						}
					}
				}
				catch( \OutOfRangeException $e ) { }
			}
		}

		unset( \IPS\Data\Store::i()->profileFields );
	}
	
	/**
	 * Resyncs when something external happens
	 *
	 * @param	\IPS\Member\ProfileStep		$step	The step
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
				\IPS\core\ProfileFields\Field::load( $fieldId );
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
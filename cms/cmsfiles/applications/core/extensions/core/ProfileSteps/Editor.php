<?php
/**
 * @brief		Profile Completion Extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		06 Jun 2018
 */

namespace IPS\core\extensions\core\ProfileSteps;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Profile Completion Extension
 */
class _Editor
{
	/**
	 * Available Actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function actions()
	{
		return array( 'custom' => 'complete_profile_custom_editor' );
	}

	/**
	 * Available sub actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function subActions()
	{
		return array( 'custom' => array( 'complete_profile_custom_editor' ) );
	}

	/**
	 * Can the actions have multiple choices?
	 *
	 * @param	string		$action		Action key (basic_profile, etc)
	 * @return	boolean|null
	 */
	public static function actionMultipleChoice( $action )
	{
		return NULL;
	}
	
	/**
	 * Has a specific step been completed?
	 *
	 * @param	\IPS\Member\ProfileStep	$step   The step to check
	 * @param	\IPS\Member|NULL		$member The member to check, or NULL for currently logged in
	 * @return	bool
	 */
	public function completed( \IPS\Member\ProfileStep $step, \IPS\Member $member = NULL )
	{
		$memberId = $member ? $member->member_id : \IPS\Member::loggedIn()->member_id;

		return (bool) \IPS\Db::i()->select( 'count(*)', 'core_profile_completion', array( 'member_id=? and step_id=?', $memberId, $step->id ) )->first();
	}
	
	/**
	 * Can be set as required?
	 *
	 * @return	array
	 * @note	This is intended for items which have their own independent settings and dedicated enable pages, such as MFA and Social Login integration
	 */
	public static function canBeRequired()
	{
		return array();
	}

	/**
	 * Post ACP Save
	 *
	 * @param	\IPS\Member\ProfileStep		$step   The step
	 * @param	array						$values Form Values
	 * @return	void
	 */
	public function postAcpSave( \IPS\Member\ProfileStep $step, array $values )
	{
		\IPS\Lang::saveCustom( 'core', "profile_step_subaction_custom_" . $step->id, $values['step_subcompletion_act'] );
	}
	
	/**
	 * Format Form Values
	 *
	 * @param	array                   $values The values from the form
	 * @param	\IPS\Member             $member The member
	 * @param	\IPS\Helpers\Form       $form   The form
	 * @return	void
	 */
	public static function formatFormValues( $values, &$member, &$form )
	{
		
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
			if ( $step->completion_act === 'custom' AND !$step->completed( $member ) )
			{
				$wizards[ $step->key ] = function( $data ) use ( $member, $step ) {
					$form = new \IPS\Helpers\Form( 'profile_profile_fields_' . $step->id, 'profile_complete_next' );
					
					$form->addHtml( $member->language()->addToStack( 'profile_step_subaction_custom_' . $step->id ) );
					
					/* Because there are no form elements, $values is an empty array - that's ok and means the form was submitted, while FALSE means it wasn't */
					if ( ( $values = $form->values() ) !== FALSE )
					{
						/* Store the flag that the step is done */
						\IPS\Db::i()->insert( 'core_profile_completion', array( 'member_id' => $member->member_id, 'step_id' => $step->id, 'completed' => time() ) );

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
	 * @param	\IPS\Member\ProfileStep		The step
	 * @return	void
	 */
	public function onDelete( \IPS\Member\ProfileStep $step )
    {
    	\IPS\Lang::deleteCustom( 'core', 'profile_step_subaction_custom' );
    }
}
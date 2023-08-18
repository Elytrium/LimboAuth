<?php
/**
 * @brief		Profile Completiong Extension
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
 * Core ProfileSteps Extension
 */
class _Core
{
	/* !Extension Methods */
	
	/**
	 * Available Actions to complete steps
	 *
	 * @return	array	array( 'key' => 'lang_string' )
	 */
	public static function actions()
	{
		$return = array( 'basic_profile' => 'complete_profile_basic_profile' );
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->showInUcp() )
			{
				$return['social_login'] = 'complete_profile_social_login';
				break;
			}
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
		/* Basic stuff */
		$return['basic_profile'] = array(
			'birthday'		=> 'complete_profile_birthday',
		);
		
		/* Signatures */
		if ( \IPS\Settings::i()->signatures_enabled )
		{
			$return['basic_profile']['signature'] = 'complete_profile_signature';
		}
		
		/* Social Integration */
		foreach ( \IPS\Login::methods() as $method )
		{
			if ( $method->showInUcp() )
			{
				$return['social_login'][ $method->id ] = $method->_title;
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
		switch( $action )
		{
			case 'basic_profile':
				return TRUE;
			break;
			case 'social_login':
				return FALSE;
			break;
		}
		
		return FALSE;
	}
	
	/**
	 * Can be set as required?
	 *
	 * @return	array
	 * @note	This is intended for items which have their own independent settings and dedicated enable pages, such as Social Login integration
	 */
	public static function canBeRequired()
	{
		return array( 'basic_profile' );
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
		if( array_key_exists( 'signature', $values ) )
		{
			$sigLimits = explode( ":", $member->group['g_signature_limits'] );
			
			/* Check Limits */
			$signature = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$signature->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $values['signature'] ) );
			
			$errors = array();
				
			/* Links */
			if ( \is_numeric( $sigLimits[4] ) and ( $signature->getElementsByTagName('a')->length + $signature->getElementsByTagName('iframe')->length ) > $sigLimits[4] )
			{
				$errors[] = $member->language()->addToStack('sig_num_links_exceeded');
			}

			/* Number of Images */
			if ( \is_numeric( $sigLimits[1] ) and $signature->getElementsByTagName('img')->length > 0 )
			{
				$imageCount = 0;
				foreach ( $signature->getElementsByTagName('img') as $img )
				{
					if( !$img->hasAttribute("data-emoticon") )
					{
						$imageCount++;
					}
				}
				if( $imageCount > $sigLimits[1] )
				{
					$errors[] = $member->language()->addToStack('sig_num_images_exceeded');
				}
			}
			
			/* Size of images */
			if ( ( \is_numeric( $sigLimits[2] ) and $sigLimits[2] ) or ( \is_numeric( $sigLimits[3] ) and $sigLimits[3] ) )
			{
				foreach ( $signature->getElementsByTagName('img') as $image )
				{
					$attachId			= $image->getAttribute('data-fileid');
					$imageProperties	= NULL;

					if( $attachId )
					{
						try
						{
							$attachment = \IPS\Db::i()->select( 'attach_location, attach_thumb_location', 'core_attachments', array( 'attach_id=?', $attachId ) )->first();
							$imageProperties = \IPS\File::get( 'core_Attachment', $attachment['attach_thumb_location'] ?: $attachment['attach_location'] )->getImageDimensions();
							$src = (string) \IPS\File::get( 'core_Attachment', $attachment['attach_location'] )->url;
						}
						catch( \UnderflowException $e ){}
					}
					
					if( \is_array( $imageProperties ) AND \count( $imageProperties ) )
					{
						if( $imageProperties[0] > $sigLimits[2] OR $imageProperties[1] > $sigLimits[3] )
						{
							$errors[] = $member->language()->addToStack( 'sig_imagetoobig', FALSE, array( 'sprintf' => array( $src, $sigLimits[2], $sigLimits[3] ) ) );
						}
					}
				}
			}
			
			/* Lines */
			$preBreaks = 0;
			
			/* Make sure we are not trying to bypass the limit by using <pre> tags, which will not have <p> or <br> tags in its content */
			foreach( $signature->getElementsByTagName('pre') AS $pre )
			{
				$content = nl2br( trim( $pre->nodeValue ) );
				$preBreaks += \count( explode( "<br />", $content ) );
			}
			
			if ( \is_numeric( $sigLimits[5] ) and ( $signature->getElementsByTagName('p')->length + $signature->getElementsByTagName('br')->length + $preBreaks ) > $sigLimits[5] )
			{
				$errors[] = $member->language()->addToStack('sig_num_lines_exceeded');
			}
			
			if( !empty( $errors ) )
			{
				$form->error = $member->language()->addToStack('sig_restrictions_exceeded');
				$form->elements['']['signature']->error = $member->language()->formatList( $errors );
			}
			else
			{
				$member->signature = $values['signature'];
			}
		}
		
		if ( array_key_exists( 'bday', $values ) )
		{
			$member->bday_month	= $values['bday']['month'];
			$member->bday_day	= $values['bday']['day'];
			$member->bday_year	= $values['bday']['year'];
		}
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
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		static::$_member = $member ?: \IPS\Member::loggedIn();
		static::$_step = $step;
		
		foreach( $step->subcompletion_act as $item )
		{
			if ( $step->completion_act === 'social_login' )
			{
				try
				{
					return \IPS\Login\Handler::load( $item )->canProcess( static::$_member );
				}
				catch ( \OutOfRangeException $e )
				{
					return TRUE;
				}
			}
			else
			{
				if ( ! static::$_member->group['g_edit_profile'] )
				{
					/* Member has no permission to edit profile */
					return TRUE;
				}

				$method = 'completed' . str_replace( ' ', '', ucwords( str_replace( '_', ' ', $item ) ) );

				if ( method_exists( $this, $method ) )
				{
					return static::$method();
				}
				else
				{
					\IPS\Log::debug( "missing_profile_step_method", 'profile_completion' );
					
					continue;
				}
			}
		}

		return TRUE;
	}
	
	/**
	 * Wizard Steps
	 *
	 * @param	\IPS\Member|NULL	$member	Member or NULL for currently logged in member
	 * @return	array
	 */
	public static function wizard( \IPS\Member $member = NULL )
	{
		static::$_member = $member ?: \IPS\Member::loggedIn();
		
		$return = array();

		$return = array_merge( $return, static::wizardBasicProfile() );
		$return = array_merge( $return, static::wizardSocial() );

		return $return;
	}
	
	/* !Completed Utility Methods */
	
	/**
	 * @brief	Member
	 */
	protected static $_member = NULL;
	
	/**
	 * @brief	Step
	 */
	protected static $_step = NULL;
	
	/**
	 * Added their birthday?
	 *
	 * @return	bool
	 */
	protected static function completedBirthday()
	{
		return (bool) static::$_member->birthday;
	}
	
	/**
	 * Added a signature?
	 *
	 * @return	bool
	 */
	protected static function completedSignature()
	{
		if ( ! static::$_member->canEditSignature() )
		{
			/* Mark complete as signatures off or no permission to edit profile */
			return TRUE;
		}

		return (bool) ( static::$_member->signature );
	}
	
	/* !Wizard Utility Methods */
	
	/**
	 * Wizard: Basic Profile
	 *
	 * @return	array
	 */
	protected static function wizardBasicProfile()
	{
		$member = static::$_member;
		$wizards = array();
		
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			$include = array();
			if ( $step->completion_act === 'basic_profile' )
			{
				foreach( $step->subcompletion_act as $item )
				{
					switch( $item )
					{
						case 'birthday':
							if ( !static::completedBirthday( static::$_member ) )
							{
								$include['birthday'] = $step;
							}
						break;
						
						case 'signature':
							if ( !static::completedSignature( static::$_member ) AND static::$_member->canEditSignature() )
							{
								$include['signature'] = $step;
							}
						break;
					}
				}
				
				if ( \count( $include ) )
				{
					$wizards[ $step->key ] = function( $data ) use ( $member, $include, $step ) {
						$form = new \IPS\Helpers\Form( 'profile_generic_' . $step->id, 'profile_complete_next' );

						if ( isset( $include['birthday'] ) )
						{
							static::birthdayForm( $form, $include['birthday'], $member );
						}
						
						if ( isset( $include['signature'] ) )
						{
							static::signatureForm( $form, $include['signature'], $member );
						}

						/* The forms are built immediately after posting which means it resubmits with empty values which confuses some form elements */
						if ( $values = $form->values() )
						{
							static::formatFormValues( $values, $member, $form );

							if( $form->error )
							{
								return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'profileCompleteTemplate' ), $step );
							}

							$member->save();
							return $values;
						}
	
						return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'profileCompleteTemplate' ), $step );
					};
				}

			}
		}

		return $wizards;
	}

	/**
	 * Wizard: Social
	 *
	 * @return	array
	 */
	protected static function wizardSocial()
	{
		$return = array();
		$member = static::$_member;
		foreach( \IPS\Member\ProfileStep::loadAll() AS $step )
		{
			if ( $step->completion_act === 'social_login' )
			{
				foreach( $step->subcompletion_act as $item )
				{
					if ( !$step->completed( $member ) )
					{
						try
						{
							$method = \IPS\Login\Handler::load( $item );
							
							$return[ $step->key ] = function( $data ) use ( $member, $step, $method )
							{
								$login = new \IPS\Login( \IPS\Http\Url::internal( 'app=core&module=system&controller=settings&do=completion', 'front', 'settings' ), \IPS\Login::LOGIN_UCP );
								$login->reauthenticateAs = $member;
								$error = NULL;
								try
								{
									if ( $success = $login->authenticate( $method ) )
									{					
										if ( $success->member->member_id === $member->member_id )
										{
											$method->completeLink( $member, NULL );
											return array();
										}
										else
										{
											$error = \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_already_associated', FALSE, array( 'sprintf' => array( $method->_title ) ) );
										}
									}
								}
								catch ( \IPS\Login\Exception $e )
								{
									if ( $e->getCode() === \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT )
									{
										if ( $e->member->member_id === $member->member_id )
										{
											$method->completeLink( $member, NULL );
											return array();
										}
										else
										{
											$error = \IPS\Member::loggedIn()->language()->addToStack( 'profilesync_email_exists', FALSE, array( 'sprintf' => array( $method->_title ) ) );
										}
									}
									else
									{
										$error = $e->getMessage();
									}
								}
								
								return \IPS\Theme::i()->getTemplate( 'system' )->profileCompleteSocial( $step, \IPS\Theme::i()->getTemplate( 'system' )->settingsProfileSyncLogin( $method, $login, $error ), \IPS\Request::i()->url() );
							};
						}
						catch ( \OutOfRangeException $e ) { } 						
					}
				}
			}
		}
		
		return $return;
	}
	
	/* !Misc Utility Methods */

	/**
	 * Birthday Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */
	protected static function birthdayForm( &$form, $step, $member )
	{
		$form->add( new \IPS\Helpers\Form\Custom( 'bday', NULL, $step->required, array( 'getHtml' => function( $element ) use ( $member, $step )
		{
			return strtr( $member->language()->preferredDateFormat(), array(
				'DD'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_day( $element->name, $element->value, $element->error ),
				'MM'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_month( $element->name, $element->value, $element->error ),
				'YY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error, $step->required ),
				'YYYY'	=> \IPS\Theme::i()->getTemplate( 'members', 'core', 'global' )->bdayForm_year( $element->name, $element->value, $element->error, $step->required ),
			) );
		} ),
		/* Validation */
		function( $val ) use ( $step )
		{
			if ( $step->required and ( ! $val['day'] or ! $val['month'] or ! $val['year'] ) )
			{
				throw new \InvalidArgumentException('form_required');
			}
		} ) );
		
		if ( \IPS\Settings::i()->profile_birthday_type == 'private' )
		{
			$form->addMessage( 'profile_birthday_display_private', 'ipsMessage ipsMessage_info' );
		}
	}
	
	/**
	 * Signature Form
	 *
	 * @param	\IPS\Helpers\Form		$form	The form
	 * @param	\IPS\Member\ProfileStep	$step	The step
	 * @param	\IPS\Member				$member	The member
	 * @return	void
	 */
	protected static function signatureForm( &$form, $step, $member )
	{
		$form->add( new \IPS\Helpers\Form\Editor( 'signature', $member->signature, $step->required, array( 'app' => 'core', 'key' => 'Signatures', 'autoSaveKey' => "frontsig-" .$member->member_id, 'attachIds' => array( $member->member_id ) ) ) );
	}
}
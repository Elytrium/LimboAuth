<?php
/**
 * @brief		ACP Member Profile: Warnings Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Nov 2017
 */

namespace IPS\core\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Warnings Block
 */
class _Warnings extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get output
	 *
	 * @return	string
	 */
	public function output()
	{
		$restrictions = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberRestrictions', TRUE, 'core', 'Content', FALSE ) as $class )
		{
			try
			{
				$ext = new $class( $this->member );
				if ( $ext->enabled() )
				{
					foreach ( $ext->activeRestrictions() as $v )
					{
						$restrictions[] = $v;
					}
				}
			}
			catch ( \Exception $e ) { }
		}
		
		$flagActions = array();
		$spamOption = explode( ',', \IPS\Settings::i()->spm_option );
		if ( \in_array( 'delete', $spamOption ) )
		{
			$flagActions[] = \IPS\Member::loggedIn()->language()->addToStack('spam_flag_confirm_delete');
		}
		elseif ( \in_array( 'unapprove', $spamOption ) )
		{
			$flagActions[] = \IPS\Member::loggedIn()->language()->addToStack('spam_flag_confirm_unapprove');
		}
		if ( \in_array( 'ban', $spamOption ) )
		{
			$flagActions[] = \IPS\Member::loggedIn()->language()->addToStack('spam_flag_confirm_ban');
		}
		elseif ( \in_array( 'disable', $spamOption ) )
		{
			$flagActions[] = \IPS\Member::loggedIn()->language()->addToStack('spam_flag_confirm_disable');
		}
		if ( \IPS\Settings::i()->spam_service_enabled and \IPS\Settings::i()->spam_service_send_to_ips )
		{
			$flagActions[] = \IPS\Member::loggedIn()->language()->addToStack('spam_flag_confirm_report');
		}
		if ( \count( $flagActions ) )
		{
			$flagMessage = sprintf( \IPS\Member::loggedIn()->language()->get('spam_flag_confirm'), \IPS\Member::loggedIn()->language()->formatList( $flagActions ) );
		}
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->warnings( $this->member, $restrictions, $flagMessage );
	}
	
	/**
	 * Edit Window
	 *
	 * @return	string
	 */
	public function edit()
	{
		/* Get extensions */
		$extensions = array();
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberRestrictions', TRUE, 'core', 'Content', FALSE ) as $class )
		{
			try
			{
				$ext = new $class( $this->member );
				if ( $ext->enabled() )
				{
					$exploded = explode( '\\', $class );
					$extensions[ $exploded[1] . '_' . $exploded[5] ] = $ext;
				}
			}
			catch ( \Exception $e ) { }
		}
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		if ( \IPS\Settings::i()->warn_on )
		{
			$form->addHeader( 'warnings' );
			$form->add( new \IPS\Helpers\Form\Number( 'member_warnings', $this->member->warn_level, FALSE ) );	
		}
		foreach ( $extensions as $key => $ext )
		{
			$form->addHeader( 'member_restrictions__' . $key );
			$ext->form( $form );
		}
		
		/* Handle Submissions */
		if ( $values = $form->values() )
		{
			$changes = array();
			
			if ( \IPS\Settings::i()->warn_on )
			{
				if ( $this->member->warn_level != $values['member_warnings'] )
				{
					$changes['member_warnings'] = array( 'old' => $this->member->warn_level, 'new' => $values['member_warnings'] );
					$this->member->warn_level = $values['member_warnings'];
				}
			}
			foreach ( $extensions as $key => $ext )
			{
				foreach ( $ext->save( $values ) as $k => $v )
				{
					$changes[ $k ] = $v;
				}
			}
			
			if ( \count( $changes ) )
			{
				$this->member->logHistory( 'core', 'warning', array( 'restrictions' => $changes ) );
			} 
			
			$this->member->save();
			\IPS\Session::i()->log( 'acplog__members_edited_restrictions', array( $this->member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=view&id={$this->member->member_id}" ), 'saved' );
		}
				
		/* Display */
		return $form;
	}
}
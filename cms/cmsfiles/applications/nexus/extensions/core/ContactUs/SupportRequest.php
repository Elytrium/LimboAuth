<?php
/**
 * @brief		Contact Us extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		29 Sep 2016
 */

namespace IPS\nexus\extensions\core\ContactUs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Contact Us extension
 */
class _SupportRequest
{
	/**
	 * Process Form
	 *
	 * @param	\IPS\Helpers\Form		$form	    The form
	 * @param	array                   $formFields Additional Configuration Formfields
	 * @param	array                   $options    Type Radio Form Options
	 * @param	array                   $toggles    Type Radio Form Toggles
	 * @param	array                   $disabled   Type Radio Form Disabled Options
	 * @return	void
	 */
	public function process( &$form, &$formFields, &$options, &$toggles, &$disabled )
	{
		if ( \count( \IPS\nexus\Support\Department::roots() ) )
		{
			$options['contact_nexus_department'] = 'contact_nexus_department';
		}
		else
		{
			$options['contact_nexus_no_departments'] = 'contact_nexus_no_departments';
			$disabled['contact_nexus_no_departments'] = 'contact_nexus_no_departments';
		}
		$toggles['contact_nexus_department'] = array( 'contact_nexus' );

		if ( \count( \IPS\nexus\Support\Department::roots() ) )
		{
			$formFields[]	= new \IPS\Helpers\Form\Node( 'contact_nexus_department', \IPS\Settings::i()->contact_nexus_department , FALSE, array( 'class' => 'IPS\nexus\Support\Department' ), function( $value ){
				if( !$value AND \IPS\Request::i()->contact_type == 'contact_nexus_department' )
				{
					throw new \DomainException( 'form_required' );
				}
			}, NULL, NULL, 'contact_nexus' );
		}
	}

	/**
	 * Handle the Form
	 *
	 * @param	array                   $values     Values from form
	 * @return	bool
	 */
	public function handleForm( $values )
	{
		if ( \IPS\Settings::i()->contact_type == 'contact_nexus_department' )
		{
			$fromEmail = ( \IPS\Member::loggedIn()->member_id ) ? \IPS\Member::loggedIn()->email : $values['email_address'];
			$content = $values['contact_text'];

			$department = \IPS\nexus\Support\Department::load( \IPS\Settings::i()->contact_nexus_department );
			$member = \IPS\Member::loggedIn();

			$request = new \IPS\nexus\Support\Request;
			$request->status = \IPS\nexus\Support\Status::load( TRUE, 'status_default_member' );
			$request->severity = \IPS\nexus\Support\Severity::load( TRUE, 'sev_default' );
			$request->last_reply = time();
			$request->last_reply_by = (int) $member->member_id;
			$request->last_new_reply = time();
			$request->started = time();
			$request->replies = 1;
			$request->title = ( $values['contact_name'] ) ? sprintf( \IPS\Member::loggedIn()->language()->get( 'contact_nexus_title_with_name' ), $values['contact_name'] ) :  \IPS\Member::loggedIn()->language()->get( 'contact_nexus_title' );

			if ( \IPS\Member::loggedIn()->member_id)
			{
				$request->member = \IPS\Member::loggedIn()->member_id;
			}
			else
			{
				$request->email = $fromEmail;
			}
			$request->department = $department;
			$request->save();

			$reply = new \IPS\nexus\Support\Reply;
			$reply->request = $request->id;
			$reply->member = (int) \IPS\Member::loggedIn()->member_id;
			$reply->post = $content;
			$reply->type = \IPS\Member::loggedIn()->member_id ? $reply::REPLY_MEMBER  : $reply::REPLY_EMAIL;
			$reply->date = time();
			$reply->email = $fromEmail;
			$reply->ip_address = \IPS\Request::i()->ipAddress();
			$reply->save();
			$request->processAfterCreate( $reply, $values );
			$request->sendNotifications();

			return TRUE;
		}
		return FALSE;

	}

	/**
	 * Allows extensions to do something before the form is shown... e.g. add your own custom fields, or redirect the page
	 *
	 * @param	\IPS\Helpers\Form		$form	    The form
	 * @return	void
	 */
	public function runBeforeFormOutput( &$form )
	{

	}


}
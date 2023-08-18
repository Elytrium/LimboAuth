<?php
/**
 * @brief		Bulk mail handler
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Jun 2013
 */

namespace IPS\core\modules\admin\bulkmail;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Bulk mail management
 */
class _bulkmail extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'bulkmail_manage' );
		
		/* Make sure we have a community outgoing email */
		if ( !\IPS\Settings::i()->email_out )
		{
			\IPS\Output::i()->error( 'no_outgoing_address', '1C125/9', 403, '' );
		}
		
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Create the table */
		$table					= new \IPS\Helpers\Table\Db( 'core_bulk_mail', \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail' ) );
		$table->langPrefix		= 'bulkmail_';
		$table->mainColumn		= 'mail_subject';
		$table->include			= array( 'mail_subject', 'mail_start', 'mail_sentto', 'mail_taken' );
		$table->quickSearch		= 'mail_subject';

		/* Default sort options */
		$table->sortBy			= $table->sortBy ?: 'mail_start';
		$table->sortDirection	= $table->sortDirection ?: 'desc';

		/* Custom parsers */
		$table->parsers			= array(
			'mail_start'	=> function( $val, $row )
			{
				if( !$val )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('bulkmail_notstarted');
				}

				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'mail_sentto'	=> function( $val, $row )
			{
				return \IPS\Member::loggedIn()->language()->addToStack( 'bulkmail_sentto_members', FALSE, array( 'pluralize' => array( (int) $val ) ) );
			},
			'mail_taken'	=> function( $val, $row )
			{
				if( !$row['mail_updated'] )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('bulkmail_notstarted');
				}

				$started	= \IPS\DateTime::ts( $row['mail_start'] );
				$updated	= \IPS\DateTime::ts( $row['mail_updated'] );

				return \IPS\DateTime::formatInterval( $updated->diff( $started ) );
			}
		);

		/* Specify the buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'bulkmail', 'bulkmail_add' ) )
		{
			\IPS\Output::i()->sidebar['actions'] = array(
				'add'	=> array(
					'primary'	=> TRUE,
					'icon'		=> 'plus',
					'title'		=> 'bulkmail_add',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=form' ),
				)
			);
		}

		$table->rowButtons = function( $row )
		{		
			$return = array();

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'bulkmail', 'bulkmail_edit' ) )
			{
				$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=form&id=' ) . $row['mail_id'],
				);

				if( $row['mail_active'] )
				{
					$return['cancel'] = array(
						'icon'		=> 'minus-circle',
						'title'		=> 'cancel',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=cancel&id=' . $row['mail_id'] )->csrf(),
					);
				}
				else
				{
					$return['resend'] = array(
						'icon'		=> 'refresh',
						'title'		=> 'resend',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=resend&id=' ) . $row['mail_id'],
					);
				}
			}

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'bulkmail', 'bulkmail_delete' ) )
			{
				$return['delete'] = array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=delete&id=' ) . $row['mail_id'],
					'data'		=> array( 'delete' => '' ),
				);
			}

			return $return;
		};

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('manage_bulk_mail');
		\IPS\Output::i()->output	= (string) $table;
	}

	/**
	 * Delete a bulk mail
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();
		
		/* Retrieve the bulk mail details for the log */
		try
		{
			$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/7', 404, '' );
		}
		
		/* Unclaim attachments */
		\IPS\File::unclaimAttachments( 'core_Admin', $mail->id, NULL, 'bulkmail' );
		
		/* Delete the bulk mail */
		$mail->delete();
		
		/* Log and redirect */
		\IPS\Session::i()->log( 'acplog__bulkmail_deleted', array( $mail->subject => FALSE ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail' ), 'deleted' );
	}

	/**
	 * Cancel a bulk mail
	 *
	 * @return	void
	 */
	public function cancel()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Retrieve the bulk mail details for the log */
		try
		{
			$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/8', 404, '' );
		}

		/* Cancel the bulk mail */
		$mail->updated	= time();
		$mail->active	= 0;
		$mail->save();
		
		/* Remove any queue tasks */
		foreach( \IPS\Db::i()->select( '*', 'core_queue', array( "`key`=?", 'Bulkmail' ) ) AS $task )
		{
			$data = json_decode( $task['data'], TRUE );
			if ( isset( $data['mail_id'] ) AND $data['mail_id'] == $mail->id )
			{
				\IPS\Db::i()->delete( 'core_queue', array( "id=?", $task['id'] ) );
			}
		}

		/* Log and redirect */
		\IPS\Session::i()->log( 'acplog__bulkmail_cancelled', array( $mail->subject => FALSE ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail' ), 'cancelled' );
	}

	/**
	 * Resend a bulk mail
	 *
	 * @return	void
	 * @see		_bulkmail::send()
	 * @note	This method simply redirects to the preview() method
	 */
	public function resend()
	{
		return $this->preview();
	}

	/**
	 * Begin sending a bulk mail.  This method will display a preview form and allow the administrator to confirm before initiating the bulk mail send process.
	 *
	 * @return	void
	 * @note	The resend() method redirects here.  Additionally, upon successfully saving a bulk mail the user is redirected here.
	 */
	public function preview()
	{
		/* Retrieve the bulk mail details */
		try
		{
			$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/1', 404, '' );
		}

		/* Get the members */
		$results	= $mail->getQuery( array( 0, 1000 ) );
		
		/* Get a count of the members */
		$total 		= $mail->getQuery( \IPS\core\BulkMail\Bulkmailer::GET_COUNT_ONLY )->first();

		/* Do we have anyone to send to? */
		if( !$total )
		{
			/* Disable the bulk mail - nothing to send */
			if( $mail->active )
			{
				$mail->active	= 0;
				$mail->save();
			}

			\IPS\Output::i()->error( 'no_members_to_send_to', '1C125/3', 400, '' );
		}

		/* Output */
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('bm_send_preview');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->message( 'bulkmail_send_info', 'information' );
		\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'members' )->bulkMailPreview( $mail, $results, $total );
	}

	/**
	 * Show a preview of the email that will be sent inside an iframe.  This allows us to use the email template properly.
	 *
	 * @return	void
	 */
	public function iframePreview()
	{
		/* Retrieve the bulk mail details */
		try
		{
			$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/2', 404, '' );
		}
		
		/* For the preview we need to display the tags replaced with this member's data */
		$content = $mail->content;
		foreach ( $mail->returnTagValues( 0, \IPS\Member::loggedIn() ) as $k => $v )
		{
			$content = str_replace( array( $k, urlencode( $k ) ), $v, $content );
		}
		
		/* Display */
		$email	= \IPS\Email::buildFromContent( $mail->subject, \IPS\Email::staticParseTextForEmail( $content, \IPS\Member::loggedIn()->language() ), NULL, \IPS\Email::TYPE_BULK )->setUnsubscribe( 'core', 'unsubscribeBulk' );

		\IPS\Output::i()->sendOutput( $email->compileContent( 'html', \IPS\Member::loggedIn(), NULL, FALSE ) );
	}
	
	/**
	 * Format a bulk mail by replacing out the tags with the proper values
	 *
	 * @param	\IPS\Member 	$member	Member data
	 * @return	string
	 */
	public function formatBody( $member )
	{
		if( empty($this->_data['content']) )
		{
			return '';
		}

		/* Default tags */
		$tags	= $this->returnTagValues( 0, $member );

		/* Work on a copy rather than the original template */
		$body	= $this->_data['content'];

		/* Loop over the tags and swap out as appropriate */
		foreach( $tags as $key => $value )
		{
			$body	= str_replace( $key, $value, $body );
		}

		return $body;
	}

	/**
	 * Actually send the bulk mail. We end up here once the preview has been confirmed the admin continues.
	 *
	 * @return	void
	 */
	public function send()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Retrieve the bulk mail details */
		try
		{
			$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/5', 404, '' );
		}

		/* Make this bulk mail active */
		$mail->active	= 1;
		$mail->start	= time();
		$mail->sentto	= 0;
		$mail->save();

		\IPS\Session::i()->log( 'acplogs__bulkmail_sent', array( $mail->subject => FALSE ) );
		
		/* And redirect */
		\IPS\Task::queue( 'core', 'Bulkmail', array( 'mail_id' => $mail->id ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail' ), 'bm_initiated' );
	}

	/**
	 * Add or edit a bulk mail
	 *
	 * @return	void
	 */
	public function form()
	{
		/* Are we editing? */
		$mail	= array( '_options' => array() );

		if( (int) \IPS\Request::i()->id )
		{
			/* Retrieve the bulk mail details */
			try
			{
				$mail	= \IPS\core\BulkMail\Bulkmailer::load( (int) \IPS\Request::i()->id );
			}
			catch( \OutOfRangeException $e )
			{
				\IPS\Output::i()->error( 'couldnt_find_bulkmail', '2C125/6', 404, '' );
			}
		}
		else
		{
			$mail	= new \IPS\core\BulkMail\Bulkmailer;
		}

		/* Get tags */
		$tags	= \IPS\core\BulkMail\Bulkmailer::getTags();
		$autoSaveKey = 'bulkmail' . ( $mail->id ? '-' . $mail->id : '' );
		
		/* Start the form */
		$form = new \IPS\Helpers\Form;
		$form->class = '';
		$form->addTab( 'bulkmail__main', NULL, NULL, 'ipsForm_vertical' );
		$form->add( new \IPS\Helpers\Form\Text( 'mail_subject', $mail->subject, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Editor( 'mail_body', $mail->content ?: '', TRUE, array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => $autoSaveKey, 'tags' => $tags, 'attachIds' => $mail->id ? array( $mail->id, NULL, 'bulkmail' ) : NULL ) ) );

		/* Add the filters tab and the "generic filters" header */
		$form->addTab( 'bulkmail__filters', NULL, NULL, 'ipsForm_horizontal' );
		$form->addHeader( 'generic_bm_filters' );

		$lastApp	= 'core';

		/* Now grab bulk mail extensions */
		foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' ) as $key => $extension )
		{
			if( method_exists( $extension, 'getSettingField' ) AND $extension->availableIn( 'bulkmail' ) )
			{
				/* See if we need a new form header - one per app */
				$_key		= explode( '_', $key );

				if( $_key[0] != $lastApp )
				{
					$lastApp	= $_key[0];
					$form->addHeader( $lastApp . '_bm_filters' );
				}

				/* Grab our fields and add to the form */
				$fields		= $extension->getSettingField( !empty( $mail->_options[ $key ] ) ? $mail->_options[ $key ] : array() );

				foreach( $fields as $field )
				{
					$form->add( $field );
				}
			}
		}

		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$mail->subject	= $values['mail_subject'];
			$mail->content	= $values['mail_body'];
			$mail->updated	= 0;
			$mail->start	= 0;
			$mail->sentto	= 0;
			$mail->active	= 0;

			$_options	= array();

			/* Loop over bulk mail extensions to format the options */
			foreach ( \IPS\Application::allExtensions( 'core', 'MemberFilter', TRUE, 'core' ) as $key => $extension )
			{
				if( method_exists( $extension, 'save' ) AND $extension->availableIn( 'bulkmail' ) )
				{
					/* Grab our fields and add to the form */
					$_value		= $extension->save( $values );

					if( $_value )
					{
						$_options[ $key ]	= $_value;
					}
				}
			}

			$mail->_options	= $_options;

			if ( !empty( $mail->id ) )
			{
				$mail->save();

				\IPS\Session::i()->log( 'acplogs__bulkmail_edited', array( $mail->subject => FALSE ) );
			}
			else
			{
				$mail->save();

				\IPS\Session::i()->log( 'acplogs__bulkmail_added', array( $mail->subject => FALSE ) );
			}
			
			/* Claim attachments */
			\IPS\File::claimAttachments( $autoSaveKey, $mail->id, NULL, 'bulkmail' );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=bulkmail&controller=bulkmail&do=preview&id=' . $mail->id ), 'saved' );
		}

		/* Output */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('mail_configuration');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'global' )->message( 'unsubscribed_users_mail', 'information' );
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'mail_configuration', $form );
	}
}
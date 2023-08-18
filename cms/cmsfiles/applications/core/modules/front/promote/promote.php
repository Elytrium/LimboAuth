<?php
/**
 * @brief		Promote Items
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		1 Feb 2017
 */

namespace IPS\core\modules\front\promote;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Promote things. (Very technical description there)
 */
class _promote extends \IPS\Dispatcher\Controller
{
	/**
	 * Edit internal data
	 *
	 * @return void
	 */
	protected function edit()
	{
		try
		{
			$promote = \IPS\core\Promote::load( \IPS\Request::i()->promote_id );
			$id = $promote->class_id;
			$class = $promote->class;
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/5', 404, '' );
		}
		
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_manage' ) and $promote->added_by != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission', '2C366/2', 403, '' );
		}
			
		try
		{
			$object = $class::load( $id );
		}
		catch ( \Exception $ex )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/6', 404, '' );
		}
		
		/* Do we have viewing perms? */
		if ( ! \IPS\core\Promote::objectCanView( $object ) )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/7', 404, '' );
		}
		
		$title = NULL;
		$settings = $promote->form_data;
		
		if ( ! empty( $settings['internal']['title'] ) )
		{
			$title = $settings['internal']['title'];
		}
		
		$form = new \IPS\Helpers\Form( 'form', 'save' );
		$form->class = 'ipsForm_vertical cPromoteDialog';
		$form->attributes = array( 'data-controller' => 'core.front.system.promote' );
		$form->add( new \IPS\Helpers\Form\Text( 'promote_social_title_internal', $title, FALSE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'promote_internal_text', $promote->getText('internal'), FALSE, array( 'rows' => 10 ) ) );
		
		/* Existing media */
		try
		{
			if ( $images = $object->contentImages( 20 ) )
			{
				$form->addHtml( \IPS\Theme::i()->getTemplate( 'promote' )->promoteDialogImages( $images, $promote ) );
			}
		}
		catch( \BadMethodCallException $e ) { }
		
		/* Upload box */
		$existingMedia = array();
		if ( $promote )
		{
			foreach( $promote->media as $file )
			{
				try
				{
					$existingMedia[] = \IPS\File::get( 'core_Promote', $file );
				}
				catch ( \OutOfRangeException $e ) { }
			}
		}
		
		$uploader = new \IPS\Helpers\Form\Upload( 'promote_media', $existingMedia, FALSE, array( 
			'multiple' => TRUE, 
			'storageExtension' => 'core_Promote', 
			'maxFiles' => 10, 
			'image' => TRUE, 
			'maxFileSize' => 3,
		) );

		$form->add( $uploader );
			
		/* Saving? */
		if ( $values = $form->values() )
		{
			/* Content images */
			$saveImages = array();
			if ( $images )
			{
				foreach( $images as $image )
				{
					foreach( $image as $extension => $path )
					{
						if ( \IPS\Request::i()->attach_files and \in_array( $path, array_keys( \IPS\Request::i()->attach_files ) ) )
						{
							$saveImages[] = array( $extension => $path );
						}
					}
				}
			}
			
			$media = array();
			if ( \is_array( $values['promote_media'] ) )
			{
				foreach( $values['promote_media'] as $key => $file )
				{
					$media[] = (string) $file;
				}
			}
			
			$settings['internal']['title'] = $values['promote_social_title_internal'];
			
			$promote->images = $saveImages;
			$promote->media = $media;
			$promote->form_data = $settings;
			$promote->setText( 'internal', $values['promote_internal_text'] );
			$promote->save();
			
			\IPS\Db::i()->update( 'core_tasks', array( 'enabled' => 1 ), array( '`key`=?', 'promote' ) );
			
			/* Redirect back to the list */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promote&controller=promote&do=view', 'front', 'promote_manage' ), 'saved' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_internal_edit');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'promote' )->edit( $form );
	}
	
	/**
	 * View sent history
	 *
	 * @return void
	 */
	protected function history()
	{
		/* Guests can't promote things */
		if( ! \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C356/1', 403, '' );
		}
		
		if ( ! \IPS\core\Promote::canPromote() )
		{
			\IPS\Output::i()->error( 'promote_no_permission', '2C356/2', 403, '' );
		}
		
		try
		{
			$promote = \IPS\core\Promote::load( \IPS\Request::i()->promote_id );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/5', 404, '' );
		}
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_view_history');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'promote' )->history( $promote->history() );
	}
	
	/**
	 * List promoted items for moderators
	 *
	 * @return void
	 */
	protected function view()
	{
		/* Guests can't promote things */
		if( ! \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C356/1', 403, '' );
		}
		
		if ( ! \IPS\core\Promote::canPromote() )
		{
			\IPS\Output::i()->error( 'promote_no_permission', '2C356/2', 403, '' );
		}
		
		/* Create the table */
		$table = new \IPS\core\Promote\Table( \IPS\Http\Url::internal( 'app=core&module=promote&controller=promote&do=view', 'front', 'promote_manage' ) );
		
		if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_manage' ) )
		{
			$table->setMember( \IPS\Member::loggedIn() );
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_promote.js', 'core' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote.css' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote_responsive.css' ) );
		}
		
		\IPS\Output::i()->breadcrumb['module'] = array( \IPS\Http\Url::internal( "app=core&module=promote&controller=promote&do=view", 'front', 'promote_manage' ), \IPS\Member::loggedIn()->language()->addToStack('promote_manage_link') );

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_manage_link');
		\IPS\Output::i()->output = $table;
	}
	
	/**
	 * Delete a promote item
	 *
	 * @return void
	 */
	protected function delete()
	{
		\IPS\Session::i()->csrfCheck();
		
		if ( ! \IPS\core\Promote::canPromote() )
		{
			\IPS\Output::i()->error( 'no_promote_permission', '2C356/B', 403, '' );
		}
		
		try
		{
			$item = \IPS\core\Promote::load( \IPS\Request::i()->promote_id );
			
			if ( ! \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'promotion', 'promote_manage' ) and $item->added_by != \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->error( 'no_module_permission', '2C366/1', 403, '' );
			}
			
			$item->delete();
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promote&controller=promote&do=view', 'front', 'promote_manage' ), 'promote_item_deleted' );
		}
		catch( \OutOfRangeException $ex )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/3', 404, '' );
		}
	}
	
	/**
	 * Promote dialog
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$promote = NULL;
		$existing = NULL;
		
		/* Guests can't promote things */
		if( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C356/4', 403, '' );
		}
		
		/* Can we promote anything at all? */
		if ( ! \IPS\core\Promote::canPromote() )
		{
			\IPS\Output::i()->error( 'no_promote_permission', '2S356/A', 403, '' );
		}
		
		/* Editing? */
		if ( isset( \IPS\Request::i()->promote_id ) )
		{
			try
			{
				$promote = \IPS\core\Promote::load( \IPS\Request::i()->promote_id );
				$id = $promote->class_id;
				$class = $promote->class;
				
				if ( isset( \IPS\Request::i()->repromote ) )
				{
					$promote->skipCloneDuplication = TRUE;
					$existing = clone $promote;
					
					/* We're repromoting, so reset the scheduled data so we don't pre-fill an old date */
					$promote->schedule_auto = 0;
					$promote->scheduled = NULL;
				}
			}
			catch( \OutOfRangeException $ex )
			{
				\IPS\Output::i()->error( 'page_not_found', '2C356/5', 404, '' );
			}
		}
		else
		{
			/* Nope... */
			$class = \IPS\Request::i()->class;
			$id = \intval( \IPS\Request::i()->id );
			
			/* Do we have an existing promotion waiting to be sent? */
			$existing = \IPS\core\Promote::loadByClassAndId( $class, $id );
		}
		
		try
		{
			if( !$class OR !class_exists( $class ) OR !is_subclass_of( $class, 'IPS\Content' ) )
			{
				throw new \InvalidArgumentException;
			}

			$object = $class::load( (int) $id );
		}
		catch ( \Exception $ex )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/6', 404, '' );
		}

		/* Check that the object can be promoted */
		if( !$object->canPromoteToSocialMedia() )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C405/1', 403, 'promote_cant_promote_admin' );
		}
		
		/* Do we have viewing perms? */
		if ( ! \IPS\core\Promote::objectCanView( $object ) )
		{
			\IPS\Output::i()->error( 'page_not_found', '2C356/7', 404, '' );
		}
		
		/* Links */
		$normalLink = $object->url();
		$shortLink = NULL;
		$images = NULL;
		
		try
		{
			$shortLink = \IPS\core\Promote::shortenUrl( $normalLink );
		}
		catch ( \Exception $e ) { }
			
		/* Do we have access to any share services? */
		if ( $services = \IPS\core\Promote::promoteServices() )
		{
			$form = new \IPS\Helpers\Form( 'form', 'promote_submit' );
			$form->class = 'ipsForm_horizontal cPromoteDialog';
			$form->attributes = array( 'data-controller' => 'core.front.system.promote' );

			$form->addTab('promote_links');
			$form->addHtml( \IPS\Theme::i()->getTemplate( 'promote' )->promoteDialogLinks( $normalLink, $shortLink ) );

			$form->addTab('promote_content');
			foreach( $services as $service )
			{
				$text = \IPS\core\Promote::objectTitle( $object );
				$content = \IPS\core\Promote::objectContent( $object );
				
				if ( $promote )
				{
					$service->promote = $promote;
					$promoteText = $promote->text;
					$text = isset( $promoteText[ mb_strtolower( $service->key ) ] ) ? $promoteText[ mb_strtolower( $service->key ) ] : '';
					
					foreach( $service->form( $text ) as $element )
					{
						if ( ! ( $element instanceof \IPS\Helpers\Form\FormAbstract ) )
						{
							$form->addHtml( $element );
						}
						else
						{
							$form->add( $element );
						}
					}
				}
				else
				{
					foreach( $service->form( $text, ( $shortLink ?: $normalLink ), $content ) as $element )
					{
						if ( ! ( $element instanceof \IPS\Helpers\Form\FormAbstract ) )
						{
							$form->addHtml( $element );
						}
						else
						{
							$form->add( $element );
						}
					}
				}
			}
			
			$form->addTab( 'promote_meta' );

			/* Existing media */
			try
			{
				if ( $images = $object->contentImages( 20 ) )
				{
					$form->addHtml( \IPS\Theme::i()->getTemplate( 'promote' )->promoteDialogImages( $images, $promote ) );
				}
			}
			catch( \BadMethodCallException $e ) { }
			
			/* Upload box */
			$existingMedia = array();
			if ( $promote )
			{
				foreach( $promote->media as $file )
				{
					try
					{
						$existingMedia[] = \IPS\File::get( 'core_Promote', $file );
					}
					catch ( \OutOfRangeException $e ) { }
				}
			}
			
			$uploader = new \IPS\Helpers\Form\Upload( 'promote_media', $existingMedia, FALSE, array( 
				'multiple' => TRUE, 
				'storageExtension' => 'core_Promote', 
				'maxFiles' => 10, 
				'image' => TRUE, 
				'maxFileSize' => 3,
				'template' => "promote.imageUpload", 
			) );

			$uploader->template = array( \IPS\Theme::i()->getTemplate( 'promote', 'core', 'front' ), 'promoteAttachments' );
			$form->add( $uploader );			
			
			/* Scheduling */
			$form->addTab('promote_schedule');
			$schedule = 'now';
			
			if ( $promote )
			{
				if ( ! isset( \IPS\Request::i()->repromote ) )
				{
					if ( $promote->schedule_auto )
					{
						$schedule = 'auto';
					}
					else
					{
						$schedule = 'custom';
					}
				}
			}
			
			\IPS\Member::loggedIn()->language()->words['promote_custom_schedule_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('promote_custom_schedule__desc'), \IPS\Member::loggedIn()->language()->get( 'timezone__' . \IPS\Settings::i()->promote_tz ) );
			
			/* Sort out some language strings */
			if ( $promote and $promote->schedule_auto )
			{
				$currentSlot = \IPS\DateTime::ts( $promote->scheduled )->setTimezone( new \DateTimeZone( \IPS\Settings::i()->promote_tz ) );
				
				/* Keep the current slot */
				\IPS\Member::loggedIn()->language()->words['promote_auto_schedule_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('promote_auto_schedule__desc'), $currentSlot->html() . ' ' . $currentSlot->localeTime( FALSE ), \IPS\Settings::i()->promote_tz );
			}
			else
			{
				$nextAuto = \IPS\core\Promote::getNextAutoSchedule();
				if ( $nextAuto )
				{
					\IPS\Member::loggedIn()->language()->words['promote_auto_schedule_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('promote_auto_schedule__desc'), $nextAuto->html() . ' ' . $nextAuto->localeTime( FALSE ), \IPS\Settings::i()->promote_tz );
				}
			}
			
			$dateOptions = array(
				'now' => 'promote_send_now',
				'auto' => 'promote_auto_schedule',
				'custom' => 'promote_custom_schedule'
			);
			
			if ( ! \IPS\Settings::i()->promote_scheduled )
			{
				unset( $dateOptions['auto'] );
			}
			
			$form->add( new \IPS\Helpers\Form\Radio( 'promote_schedule', $schedule, FALSE, array(
				'options' => $dateOptions
			) ) );
			
			$form->add( new \IPS\Helpers\Form\Date( 'promote_custom_date', ( ( $promote and ! $promote->schedule_auto and $promote->scheduled ) ? \IPS\DateTime::ts( $promote->scheduled )->setTimezone( new \DateTimeZone( \IPS\Settings::i()->promote_tz ) ) : NULL ), FALSE, array( 'time' => true, 'timezone' => new \DateTimeZone( \IPS\Settings::i()->promote_tz ) ), NULL, NULL, NULL, 'promote_custom_date' ) );
		}
		else
		{
			\IPS\Output::i()->error( 'promote_no_permission', '2C266/1', 403, '' ); //error code needed
		}
		
		/* Saving? */
		if ( $values = $form->values() )
		{
			$text = array();
			$shareTo = array();
			$processedForms = array();
			
			foreach( $services as $service )
			{
				$key = mb_strtolower( $service->key );
				if ( isset( $values[ 'promote_social_content_' . $key ] ) and $values[ 'promote_social_content_' . $key ] )
				{
					$text[ $key ] = $values[ 'promote_social_content_' . $key ];
					$shareTo[] = $key;
				}
				
				$processedForms[ $key ] = $service->processPromoteForm( $values );
			}
			
			$media = array();
			if ( \is_array( $values['promote_media'] ) )
			{
				foreach( $values['promote_media'] as $key => $file )
				{
					$media[] = (string) $file;
				}
			}
			
			$scheduled = time();
			$scheduleAuto = 0;
			
			if ( $values['promote_schedule'] == 'custom' AND $values['promote_custom_date'] )
			{
				$scheduled = $values['promote_custom_date']->getTimestamp();
			}
			else if ( $values['promote_schedule'] == 'auto' )
			{
				$scheduleAuto = 1;
				
				if ( $promote and $promote->schedule_auto )
				{
					$scheduled = $promote->scheduled;
				}
				else
				{
					$scheduled = \IPS\core\Promote::getNextAutoSchedule()->getTimestamp();
				}
			}
			
			/* Content images */
			$saveImages = array();
			if ( $images )
			{
				foreach( $images as $image )
				{
					foreach( $image as $extension => $path )
					{
						if ( \IPS\Request::i()->attach_files and \in_array( $path, array_keys( \IPS\Request::i()->attach_files ) ) )
						{
							$saveImages[] = array( $extension => $path );
						}
					}
				}
			}

			$isNew = FALSE;
			if ( ! $promote )
			{
				$isNew = TRUE;
				$promote = new \IPS\core\Promote;
				$promote->class = $class;
				$promote->class_id = $id;
				$promote->added_by = \IPS\Member::loggedIn()->member_id;
			}
			
			$promote->internal = ( \in_array( 'internal', $shareTo ) ? 1 : 0 );
			$promote->text = $text;
			$promote->short_link = $shortLink ?: $normalLink;
			$promote->images = $saveImages;
			$promote->media = $media;
			$promote->added = time();
			$promote->scheduled = $scheduled;
			$promote->schedule_auto = $scheduleAuto;
			$promote->share_to = $shareTo;
			$promote->failed = 0; # Reset the failed flag if we're re-promoting
			$promote->sent = 0;
			$promote->form_data = $processedForms;

			if ( is_subclass_of( $class, 'IPS\Content' ) )
			{
				$promote->author_id = $object->author()->member_id ?: 0;
			}

			$promote->save();

			if ( $isNew )
			{
				/* Points */
				$promote->object()->author()->achievementAction( 'core', 'ContentPromotion', [
					'content' => $promote->object(),
					'promotype' => 'promote'
				] );
			}

			if ( isset( \IPS\Request::i()->promote_id ) )
			{
				/* Redirect back to the list */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=promote&controller=promote&do=view', 'front', 'promote_manage' ), 'saved' );
			}
			else
			{
				/* Show a we're done page */
				$output = \IPS\Theme::i()->getTemplate( 'promote' )->promoteScheduled( $promote );
			}
		}
		else
		{
			$output = \IPS\Theme::i()->getTemplate( 'promote' )->promoteDialog( \IPS\Member::loggedIn()->language()->addToStack('promote_social_title'), $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'promote', 'core' ), 'promoteDialogTemplate' ), $existing, $object ) );
		}
	
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_promote.js', 'core' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote.css' ) );

		if ( \IPS\Theme::i()->settings['responsive'] )
  		{
			\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'styles/promote_responsive.css' ) );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('promote_social_button');
		\IPS\Output::i()->output = $output;
	}
}
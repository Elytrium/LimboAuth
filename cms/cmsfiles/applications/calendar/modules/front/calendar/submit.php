<?php
/**
 * @brief		Submit Event Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Calendar
 * @since		8 Jan 2014
 */

namespace IPS\calendar\modules\front\calendar;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Submit Event Controller
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * Choose Calendar
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form( 'select_calendar', 'continue' );
		$form->class = 'ipsForm_vertical ipsForm_noLabels';
		$form->add( new \IPS\Helpers\Form\Node( 'calendar', NULL, TRUE, array(
			'url'					=> \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ),
			'class'					=> 'IPS\calendar\Calendar',
			'permissionCheck'		=> 'add',
			'forceOwner'			=> \IPS\Member::loggedIn(),
			'clubs'					=> \IPS\Settings::i()->club_nodes_in_apps
		) ) );

		/* Are we creating an event for a specific day? If yes, pass the values to the form */
		if( \IPS\Request::i()->y AND \IPS\Request::i()->m AND \IPS\Request::i()->d )
		{
			$form->hiddenValues['y'] = \IPS\Request::i()->y;
			$form->hiddenValues['m'] = \IPS\Request::i()->m;
			$form->hiddenValues['d'] = \IPS\Request::i()->d;
		}

		/* Are we coming from a venue? */
		if( \IPS\Settings::i()->calendar_venues_enabled and \IPS\Request::i()->venue )
		{
			$form->hiddenValues['venue'] = \IPS\Request::i()->venue;
		}

		if ( $values = $form->values() )
		{
			$url = \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit&do=submit', 'front', 'calendar_submit' )->setQueryString( 'id', $values['calendar']->_id );

			if( isset( $values['y'], $values['m'], $values['d'] ) )
			{
				$url = $url->setQueryString( 'd', $values['d'] )->setQueryString( 'm', $values['m'] )->setQueryString( 'y', $values['y'] );
			}

			if( \IPS\Settings::i()->calendar_venues_enabled and isset( $values['venue'] ) )
			{
				$url = $url->setQueryString( 'venue', $values['venue'] );
			}

			\IPS\Output::i()->redirect( $url );
		}

		/* Display */
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'submit_event' );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'add_cal_event_header' ) );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->calendarSelector( $form );
	}

	/**
	 * Submit Event
	 *
	 * @return	void
	 */
	protected function submit()
	{
		$calendar = NULL;
		$club =  NULL;
	
		try
		{
			$calendar = \IPS\calendar\Calendar::loadAndCheckPerms( \IPS\Request::i()->id );
			
			if ( $club = $calendar->club() )
			{
				\IPS\core\FrontNavigation::$clubTabActive = TRUE;
				\IPS\Output::i()->breadcrumb = array();
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
				\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
				\IPS\Output::i()->breadcrumb[] = array( $calendar->url(), $calendar->_title );
				
				if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
				{
					\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $calendar, 'sidebar' );
				}
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ) );
		}

		$form = \IPS\calendar\Event::create( $calendar );

		$extraOutput = '';
		$guestPostBeforeRegister = ( !\IPS\Member::loggedIn()->member_id ) ? ( $calendar and !$calendar->can( 'add', \IPS\Member::loggedIn(), FALSE ) ) : NULL;
		$modQueued = \IPS\calendar\Event::moderateNewItems( \IPS\Member::loggedIn(), $calendar, $guestPostBeforeRegister );
		if ( $guestPostBeforeRegister or $modQueued )
		{
			$extraOutput .= \IPS\Theme::i()->getTemplate( 'forms', 'core' )->postingInformation( $guestPostBeforeRegister, $modQueued, TRUE );
		}			

		/* Display */
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'submit' )->submitPage( $extraOutput . $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'calendar' ), 'submitForm' ) ), \IPS\Member::loggedIn()->language()->addToStack('add_cal_event_header'), $calendar );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'submit_event' );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack( 'add_cal_event_header' ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'calendar', 'front' ) );
	}

	/**
	 * Copy Event
	 *
	 * @return	void
	 */
	protected function copy()
	{
		try
		{
			$existing = \IPS\calendar\Event::loadAndCheckPerms( \IPS\Request::i()->event_id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ) );
		}

		if( !$existing->canCopyEvent())
		{
			\IPS\Output::i()->error( 'no_module_permission', '2L179/9', 403, '' );
		}

		/* Are we the author of the existing event? */
		if( $existing->author()->member_id !== \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=calendar&module=calendar&controller=submit', 'front', 'calendar_submit' ) );
		}
		
		$form = new \IPS\Helpers\Form( 'form', \IPS\Member::loggedIn()->language()->checkKeyExists( \IPS\Calendar\Event::$formLangPrefix . '_save' ) ? \IPS\Calendar\Event::$formLangPrefix . '_save' : 'save' );
		$form->class = 'ipsForm_vertical';
		$formElements = \IPS\Calendar\Event::formElements( $existing, $existing->container(), TRUE );
		foreach ( $formElements as $key => $object )
		{
			if ( \is_object( $object ) )
			{
				$form->add( $object );
			}
			else
			{
				$form->addMessage( $object, NULL, FALSE, $key );
			}
		}

		if ( $values = $form->values() )
		{
			/* Set the container */
			if ( !isset( $values[ 'event_container' ] ) )
			{
				$values[ 'event_container' ] = $existing->container();
			}

			/* Disable read/write separation */
			\IPS\Db::i()->readWriteSeparation = FALSE;

			try
			{
				$obj = \IPS\calendar\Event::createFromForm( $values );

				/* Set cover photo offset from original if we're using the same photo */
				if( $existing->cover_photo and $existing->cover_photo == $obj->cover_photo )
				{
					try
					{
						$obj->cover_photo = \IPS\File::get( 'calendar_Events', $existing->cover_photo )->duplicate();
						$obj->cover_offset = $existing->cover_offset;
						$obj->save();
					}
					catch ( \IPS\File\Exception $e ){}
				}

				if ( !\IPS\Member::loggedIn()->member_id and $obj->hidden() )
				{
					\IPS\Output::i()->redirect( $obj->container()->url(), 'mod_queue_message' );
				}
				else if ( $obj->hidden() == 1 )
				{
					\IPS\Output::i()->redirect( $obj->url(), 'mod_queue_message' );
				}
				else
				{
					\IPS\Output::i()->redirect( $obj->url() );
				}
			}
			catch ( \DomainException $e )
			{
				$form->error = $e->getMessage();
			}
		}

		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submitPage( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'calendar' ), 'submitForm' ) ), \IPS\Member::loggedIn()->language()->addToStack('copy_cal_event_header', TRUE, array( 'sprintf' => $existing->title ) ) );

		if ( \IPS\calendar\Event::moderateNewItems( \IPS\Member::loggedIn() ) )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->modQueueMessage( \IPS\Member::loggedIn()->warnings( 5, NULL, 'mq' ), \IPS\Member::loggedIn()->mod_posts ) . \IPS\Output::i()->output;
		}

		/* Display */
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack('copy_cal_event_header', TRUE, array( 'sprintf' => $existing->title ) );
		\IPS\Output::i()->sidebar['enabled'] = FALSE;
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('copy_cal_event_header', TRUE, array( 'sprintf' => $existing->title ) ) );
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_submit.js', 'calendar', 'front' ) );
	}
}
<?php
/**
 * @brief		Ignore Preferences
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 Aug 2013
 */

namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Ignore Preferences
 */
class _ignore extends \IPS\Dispatcher\Controller
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
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C146/1', 403, '' );
		}

		\IPS\Output::i()->jsFiles	= array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js('front_ignore.js', 'core' ) );

		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$url = \IPS\Http\Url::internal( "app=core&module=system&controller=ignore", 'front', 'ignore' );
		
		/* Build form */
		$form = \IPS\core\Ignore::form();
		if ( $values = $form->values() )
		{
			\IPS\Session::i()->csrfCheck();

			try
			{
				\IPS\core\Ignore::createFromForm( $values );
			}
			catch( \InvalidArgumentException $e )
			{
				if ( \IPS\Request::i()->isAjax() )
				{
					\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( 'cannot_ignore_self' ) ), 403 );
				}
				else
				{
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ), '1C146/2', 403, '' );
				}
			}
				
			if ( \IPS\Request::i()->isAjax() )
			{
				$data = array();
				foreach( \IPS\core\Ignore::types() AS $type )
				{
					if ( $values["ignore_{$type}"] )
					{
						$data["ignore_{$type}"] = 1;
					}
				}
				
				\IPS\Output::i()->json( array( 'name' => $values['member']->name, 'member_id' => $values['member']->member_id, 'data' => $data ) );
			}
			else
			{
				\IPS\Output::i()->redirect( $url );
			}
		}
		
		/* Build table */
		$table = new \IPS\Helpers\Table\Db( 'core_ignored_users', $url, array( array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) ) );
		$table->title = 'ignored_users_current';
		$table->langPrefix = 'ignore_';
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' ), 'ignoreTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'system' ), 'ignoreTableRows' );
		$filters = array();
		foreach ( \IPS\core\Ignore::types() as $type )
		{
			$filters[ $type ] = "ignore_{$type}=1";
		}
		$table->filters = $filters;
				
		/* Display */
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('ignored_users') );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('ignored_users');
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->ignore( $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'system' ), 'ignoreForm' ) ), (string) $table, ( isset( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : 0 ) );
	}
	
	/**
	 * Add new ignored user
	 *
	 * @return	void
	 */
	protected function add()
	{
		\IPS\Session::i()->csrfCheck();

		try
		{
			/* We have to html_entity_decode because the javascript code sends the encoded name here */
			$member = \IPS\Member::load( html_entity_decode( \IPS\Request::i()->name, ENT_QUOTES, 'UTF-8' ), 'name' );
			
			/* If \IPS\Member::load() cannot find a member, it just creates a new guest object, never throwing the exception */
			if ( !$member->member_id )
			{
				throw new \OutOfRangeException( 'cannot_ignore_no_user' );
			}
			
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_self' );
			}

			if ( !$member->canBeIgnored() )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_that_member' );
			}
			
			$ignore = NULL;
			try
			{
				$ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
			}
			catch ( \OutOfRangeException $e ) {}
			
			$data = array();
			foreach ( \IPS\core\Ignore::types() as $t )
			{
				$data[ $t ] = $ignore ? $ignore->$t : FALSE;
			}

			\IPS\Output::i()->json( $data );			
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->json( array( 'error' => \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ) ), 403 );
		}
	}

	/**
	 * Edit
	 *
	 * @return	void
	 */
	protected function edit()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			
			/* If \IPS\Member::load() cannot find a member, it just creates a new guest object, never throwing the exception */
			if ( !$member->member_id )
			{
				throw new \OutOfRangeException( 'cannot_ignore_no_user' );
			}
			
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_self' );
			}
			
			if ( !$member->canBeIgnored() )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_that_member' );
			}
			
			$ignore = NULL;
			try
			{
				$ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
			}
			catch ( \OutOfRangeException $e ) {}
			
			$form = new \IPS\Helpers\Form( NULL, 'ignore_edit' );
			$form->class = 'ipsForm_vertical';
			
			foreach ( \IPS\core\Ignore::types() as $type )
			{
				$form->add( new \IPS\Helpers\Form\Checkbox( "ignore_{$type}", $ignore ? $ignore->$type : FALSE ) );
			}

			/* Save values */
			if( $values = $form->values() )
			{
				foreach ( \IPS\core\Ignore::types() as $type )
				{
					$ignore->$type = $values["ignore_{$type}"];
				}

				$ignore->save();

				if( !\IPS\Request::i()->isAjax() )
				{
					$this->manage();
				}
				else
				{
					\IPS\Output::i()->json( 'OK' );
				}
			}
			
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('ignored_users') );
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('add_ignored_user');
			\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'system' ), 'ignoreEditForm' ) );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->json( \IPS\Member::loggedIn()->language()->addToStack( $e->getMessage() ), 403 );
		}
	}

	/**
	 * Remove (AJAX)
	 *
	 * @return	void
	 */
	protected function remove()
	{
		\IPS\Session::i()->csrfCheck();

		/* Make sure the user confirmed the deletion */
		\IPS\Request::i()->confirmedDelete();

		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			
			/* If \IPS\Member::load() cannot find a member, it just creates a new guest object, never throwing the exception */
			if ( !$member->member_id )
			{
				throw new \OutOfRangeException;
			}
			
			$ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
			$ignore->delete();
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'results' => 'ok', 'name' => $member->name ) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=ignore", 'front', 'ignore' ), \IPS\Member::loggedIn()->language()->addToStack( 'ignore_removed', FALSE, array( 'sprintf' => array( $member->name ) ) ) );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C146/5', 404, '' );
		}
	}
	
	/**
	 * Ignore Specific Content Type (AJAX)
	 *
	 * @return	void
	 */
	protected function ignoreType()
	{
		\IPS\Session::i()->csrfCheck();
		
		try
		{
			if ( !\in_array( \IPS\Request::i()->type, \IPS\core\Ignore::types() ) )
			{
				throw new \OutOfRangeException( 'invalid_type' );
			}
			
			$member	= \IPS\Member::load( \IPS\Request::i()->member_id );
			
			if ( !$member->member_id )
			{
				throw new \OutOfRangeException( 'cannot_ignore_no_user' );
			}
			
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_self' );
			}
			
			if ( !$member->canBeIgnored() )
			{
				throw new \InvalidArgumentException( 'cannot_ignore_that_member' );
			}
			
			$type = \IPS\Request::i()->type;
			$value = isset( \IPS\Request::i()->off ) ? FALSE : TRUE;
			
			try
			{
				$ignore = \IPS\core\Ignore::load( $member->member_id, 'ignore_ignore_id', array( 'ignore_owner_id=?', \IPS\Member::loggedIn()->member_id ) );
				$ignore->$type = $value;
				$ignore->save();
			}
			catch( \OutOfRangeException $e )
			{
				$ignore = new \IPS\core\Ignore;
				$ignore->$type = $value;
				$ignore->owner_id	= \IPS\Member::loggedIn()->member_id;
				$ignore->ignore_id	= $member->member_id;
				$ignore->save();
			}
						
			\IPS\Member::loggedIn()->members_bitoptions['has_no_ignored_users'] = FALSE;
			\IPS\Member::loggedIn()->save();
						
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'result' => 'ok' ) );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=ignore", 'front', 'ignore' ), 'ignore_adjusted' );
			}
		}

		catch( \Exception $e )
		{
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( array( 'error' => $e->getMessage() ), 403 );
			}
			else
			{
				\IPS\Output::i()->error( $e->getMessage(), '1C146/4', 403, '' );
			}
		}
	}
}
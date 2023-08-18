<?php
/**
 * @brief		Submit
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		10 Mar 2014
 */

namespace IPS\blog\modules\front\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Submit
 */
class _submit extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief This blog
	 */
	protected $blog = NULL;
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( isset( \IPS\Request::i()->id ) )
		{
			try
			{
				$this->blog = \IPS\blog\Blog::load( \IPS\Request::i()->id );
			}
			catch( \OutOfRangeException $e ) {}
			\IPS\blog\Entry::canCreate( \IPS\Member::loggedIn(), $this->blog, TRUE );
		}
		
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( 'submit_entry' );
		
		/* Load Blog */
		try
		{
			/* Can we add to this Blog? We load the blog in execute, so emulate an OutOfRangeException here if we don't actually have one. */
			if ( $this->blog === NULL )
			{
				throw new \OutOfRangeException;
			}
			
			\IPS\blog\Entry::canCreate( \IPS\Member::loggedIn(), $this->blog, TRUE );
			
			$form = \IPS\blog\Entry::create( $this->blog );
			$formTemplate = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'submit', 'blog' ), 'submitFormTemplate' ) );
			
			if ( $club = $this->blog->club() )
			{
				\IPS\core\FrontNavigation::$clubTabActive = TRUE;
				\IPS\Output::i()->breadcrumb = array();
				\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=clubs&controller=directory', 'front', 'clubs_list' ), \IPS\Member::loggedIn()->language()->addToStack('module__core_clubs') );
				\IPS\Output::i()->breadcrumb[] = array( $club->url(), $club->name );
				
				if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
				{
					\IPS\Output::i()->sidebar['contextual'] = \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $club, $this->blog, 'sidebar' );
				}
			}
            \IPS\Output::i()->breadcrumb[] = array( $this->blog->url(), $this->blog->_title );

			if( !$this->blog->social_group )
			{
				\IPS\Session::i()->setLocation( $this->blog->url(), array(), 'loc_blog_adding_entry', array( $this->blog->_title => FALSE ) );
			}

			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'submit' )->submit( $formTemplate, $this->blog );
		}
		catch ( \OutOfRangeException $e )
		{
			$form = new \IPS\Helpers\Form( 'select_blog', 'continue' );
			$form->class = 'ipsForm_vertical ipsForm_noLabels';
			$form->add( new \IPS\Helpers\Form\Node( 'blog_select', NULL, TRUE, array(
					'url'					=> \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=submit', 'front', 'blog_submit' ),
					'class'					=> 'IPS\blog\Blog',
					'permissionCheck'		=> 'add',
					'forceOwner'			=> \IPS\Member::loggedIn(),
					'clubs'					=> \IPS\Settings::i()->club_nodes_in_apps
			) ) );
			
			if ( $values = $form->values() )
			{
				$url = \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=submit', 'front', 'blog_submit' )->setQueryString( 'id', $values['blog_select']->_id );
				\IPS\Output::i()->redirect( $url );
			}
			
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'submit' )->blogSelector( $form );
		}
	}
}
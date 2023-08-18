<?php
/**
 * @brief		page
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		02 Aug 2019
 */

namespace IPS\core\modules\front\clubs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * page
 */
class _page extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Page
	 */
	protected $page;

	/**
	 * @brief These properties are used to specify datalayer context properties.
	 *
	 */
	public static $dataLayerContext = array(
		'community_area' =>  [ 'value' => 'clubs', 'odkUpdate' => 'true']
	);
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		try
		{
			$this->page = \IPS\Member\Club\Page::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2S410/4', 404, '' );
		}
		
		\IPS\Output::i()->sidebar['contextual'] = '';

		/* Club info in sidebar */
		if ( \IPS\Settings::i()->clubs_header == 'sidebar' )
		{
			\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->header( $this->page->club, NULL, 'sidebar', $this->page );
		}

		if( ( \IPS\GeoLocation::enabled() and \IPS\Settings::i()->clubs_locations AND $location = $this->page->club->location() ) )
		{
			\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubLocationBox( $this->page->club, $location );
		}
		
		if( $this->page->club->type != \IPS\Member\Club::TYPE_PUBLIC AND $this->page->club->canViewMembers() )
		{
			\IPS\Output::i()->sidebar['contextual'] .= \IPS\Theme::i()->getTemplate( 'clubs', 'core' )->clubMemberBox( $this->page->club );
		}
		
		\IPS\Output::i()->breadcrumb[] = array( $this->page->club->url(), $this->page->club->name );
		\IPS\Output::i()->breadcrumb[] = array( NULL, $this->page->title );

		if( !$this->page->meta_index )
		{
			\IPS\Output::i()->metaTags['robots'] = 'noindex';
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
		if ( !$this->page->club->rulesAcknowledged() )
		{
			\IPS\Http\Url::internal( $this->page->club->url()->setQueryString( 'do', 'rules' ) );
		}
		
		\IPS\Output::i()->title = $this->page->title;
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'clubs' )->viewPage( $this->page );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		if( !$this->page->canEdit() )
		{
			\IPS\Output::i()->error( 'node_noperm_edit', '2S410/1', 403, '' );
		}

		/* Init form */
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		\IPS\Member\Club\Page::form( $form, $this->page->club, $this->page );
		
		/* Form Submission */
		if ( $values = $form->values() )
		{
			$this->page->formatFormValues( $values );
			$this->page->save();
			
			\IPS\File::claimAttachments( "club-page-{$this->page->id}", $this->page->id );
			
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				\IPS\Output::i()->redirect( $this->page->url() );
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( "edit" );
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		\IPS\Session::i()->csrfCheck();

		if( !$this->page->canDelete() )
		{
			\IPS\Output::i()->error( 'node_noperm_delete', '2S410/2', 403, '' );
		}
		
		$this->page->delete();
		
		\IPS\Output::i()->redirect( $this->page->club->url(), 'deleted' );
	}
}
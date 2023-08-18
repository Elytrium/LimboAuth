<?php
/**
 * @brief		Support Request View
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		09 Apr 2014
 */

namespace IPS\nexus\modules\front\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Support Request View
 */
class _view extends \IPS\Content\Controller
{
	/**
	 * [Content\Controller]	Class
	 */
	protected static $contentModel = 'IPS\nexus\Support\Request';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2X264/1', 403, '' );
		}
		
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_support.js', 'nexus', 'front' ) );
		parent::execute();
	}
	
	/**
	 * @brief	The current support request (instance of \IPS\nexus\Support\Request)
	 */
	protected $request;

	/**
	 * View Item
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->request = parent::manage();
		if ( !$this->request )
		{
			\IPS\Output::i()->error( 'node_error', '2X204/1', 404, '' );
		}

        $allowedStatuses = \IPS\nexus\Support\Status::publicSetStatuses( $this->request );
		if ( isset( \IPS\Request::i()->setStatus ) and !empty( $allowedStatuses ) and \in_array( \IPS\Request::i()->setStatus, array_keys( $allowedStatuses ) ) )
		{
			\IPS\Session::i()->csrfCheck();
			$newStatus = \IPS\nexus\Support\Status::load( \IPS\Request::i()->setStatus );
			$this->request->log( 'status', $this->request->status, $newStatus );
			$this->request->status = $newStatus;
			$this->request->save();
			\IPS\Output::i()->redirect( $this->request->url() );
		}
				
		if ( !isset( \IPS\Request::i()->root ) and $purchase = $this->request->purchase and $purchase->childrenCount( NULL ) )
		{
			\IPS\Request::i()->root = $purchase->id;
			\IPS\Request::i()->noshowroot = TRUE;
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'support' )->request( $this->request );
	}
	
	/**
	 * Rate Staff Response
	 *
	 * @return	void
	 */
	protected function rate()
	{
		try
		{
			$reply = \IPS\nexus\Support\Reply::loadAndCheckPerms( \IPS\Request::i()->response );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X209/1', 404, '' );
		}
		
		/* If we've rated, do not allow us to rate again */
		try
		{
			\IPS\Db::i()->select( '*', 'nexus_support_ratings', array( 'rating_reply=?', $reply->id ) )->first();

			\IPS\Output::i()->error( 'already_rated', '2X204/2', 403, '' );
		}
		catch ( \UnderflowException $e ) {}
		
		$form = new \IPS\Helpers\Form( 'feedback', 'send_feedback' );
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Rating( 'support_rating_rating', \IPS\Request::i()->rating, TRUE ) );
		$form->add( new \IPS\Helpers\Form\TextArea( 'support_rating_feedback', NULL, FALSE ) );
		if ( $values = $form->values() )
		{
			$save = array(
				'rating_reply'	=> $reply->id,
				'rating_rating'	=> $values['support_rating_rating'],
				'rating_from'	=> \IPS\Member::loggedIn()->member_id,
				'rating_staff'	=> $reply->member,
				'rating_note'	=> $values['support_rating_feedback'],
				'rating_date'	=> time()
			);

			\IPS\Db::i()->insert( 'nexus_support_ratings', $save );

			if ( \IPS\Request::i()->isAjax() )
			{
				$reply->ratingData = $save;
				\IPS\Output::i()->json( \IPS\Theme::i()->getTemplate('support')->ratingValue( $reply ) );
			}
			else
			{			
				\IPS\Output::i()->redirect( $reply->url(), 'thanks_for_your_feedback' );
			}
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
	
	/**
	 * Change Severity
	 *
	 * @return	void
	 */
	protected function severity()
	{
		try
		{
			$request = \IPS\nexus\Support\Request::loadAndCheckPerms( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X209/1', 404, '' );
		}
		
		if ( !\IPS\Settings::i()->nexus_severities or \IPS\Member::loggedIn()->cm_no_sev )
		{
			\IPS\Output::i()->error( 'node_error', '2X209/3', 404, '' );
		}
		
		$options = array();
		foreach ( \IPS\nexus\Support\Severity::roots( NULL, NULL, array( array( 'sev_public=1' ), array( "sev_departments='*' OR " . \IPS\Db::i()->findInSet( 'sev_departments', array( $request->department->id ) ) ) ) ) as $severity )
		{
			$options[ $severity->id ] = $severity->_title;
		}
						
		$form = new \IPS\Helpers\Form;
		$form->class = 'ipsForm_vertical';
		$form->add( new \IPS\Helpers\Form\Radio( 'support_severity', $request->severity->id, FALSE, array(
			'options' => $options
		) ) );
		if ( $values = $form->values() )
		{
			$request->severity = \IPS\nexus\Support\Severity::load( $values['support_severity'] );
			$request->save();
			\IPS\Output::i()->redirect( $request->url() );
		}
		
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'forms', 'core' ), 'popupTemplate' ) );
	}
}
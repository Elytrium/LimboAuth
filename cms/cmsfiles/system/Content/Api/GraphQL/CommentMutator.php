<?php
/**
 * @brief		Base mutator class for comments
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Jun 2018
 */

namespace IPS\Content\Api\GraphQL;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Base mutator class for comments
 */
abstract class _CommentMutator extends ContentMutator
{

	/** 
	 * Report comment
	 * 
	 */
	protected function _revokeCommentReport( array $args, \IPS\Content\Comment $comment )
	{
		try
		{
			$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'id=? AND report_by=? AND date_reported > ?', $args['reportID'], \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) )->first();
		}
		catch( \UnderflowException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_REPORT', '1F295/1', 403 );
		}
		
		try
		{
			$index = \IPS\core\Reports\Report::load( $report['rid'] );
		}
		catch( \OutofRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_REPORT', '1F295/1', 403 );
		}
		
		\IPS\Db::i()->delete( 'core_rc_reports', array( 'id=?', $args['reportID'] ) );
		
		/* Recalculate, we may have dropped below the threshold needed to hide a thing */
		$index->runAutomaticModeration();
		
		$comment->alreadyReported = NULL;
		$comment->reportData = array();

		return $comment;
	}

	/** 
	 * Report comment
	 * 
	 */
	protected function _reportComment( array $args, \IPS\Content\Comment $comment )
	{
		$class = $this->class;
		$canReport = $comment->canReport();

		if ( $canReport !== TRUE AND !( $canReport == 'report_err_already_reported' AND \IPS\Settings::i()->automoderation_enabled ) )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'CANNOT_REPORT', '1F295/1', 403 );
		}

		$itemIdColumn = $class::$databaseColumnId;
		$idColumn = $comment::$databaseColumnId;

		if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->automoderation_enabled )
		{
			/* Has this member already reported this in the past 24 hours */
			try {
				$index = \IPS\core\Reports\Report::loadByClassAndId( \get_class( $comment ), $comment->$idColumn );
				$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? and report_by=? and date_reported > ?', $index->id, \IPS\Member::loggedIn()->member_id, time() - ( \IPS\Settings::i()->automoderation_report_again_mins * 60 ) ) );

				throw new \IPS\Api\GraphQL\SafeException( 'ALREADY_REPORTED', '1F295/1', 403 );
			}
			catch( \Exception $e ) { 
				if( $e instanceof \IPS\Api\GraphQL\SafeException ){
					throw $e;
				}
			}

			if( !\in_array( $args['reason'], array_keys( \IPS\core\Reports\Types::roots() ) ) && $args['reason'] !== 0 )
			{
				throw new \IPS\Api\GraphQL\SafeException( 'INVALID_REASON', '1F295/1', 403 );
			}
		}

		if( !\IPS\Settings::i()->automoderation_enabled )
		{
			$args['reason'] = 0;
		}

		$args['additionalInfo'] = "<p>" . $args['additionalInfo'] . "</p>";

		try 
		{
			$comment->report( $args['additionalInfo'], $args['reason'] );
		}
		catch( \Exception $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'REPORT_FAILED', '1F295/1', 403 );
		} 
		

		return $comment;
	}

	/**
	 * Comment reactions
	 *
	 * @param 	int 					$reactionID 	ID of reaction to add
	 * @param	\IPS\Content\Comment	$comment		The comment to add a reaction on
	 * @return	void
	 */
	protected function _reactComment( $reactionID, \IPS\Content\Comment $comment )
	{
		try 
		{
			$reaction = \IPS\Content\Reaction::load( $reactionID );
		}
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'INVALID_REACTION', '1F295/1', 403 );
		}

		$comment->react( $reaction );
	}

	/**
	 * Remove comment reaction
	 *
	 * @param	\IPS\Content\Comment	$comment		The comment to remove the reaction on
	 * @return	void
	 */
	protected function _unreactComment( \IPS\Content\Comment $comment )
	{
		try {
			$comment->removeReaction();
		} 
		catch ( \OutOfRangeException $e )
		{
			throw new \IPS\Api\GraphQL\SafeException( 'NO_REACTIONS', '1F295/1', 403 );
		}
	}

	/**
	 * Create
	 *
	 * @param 	array 					$commentData 	Array of data used to generate comment
	 * @param	\IPS\Content\Item		$item			Content Item
	 * @param	string					$postKey		Post key
	 * @return	\IPS\Content\Comment
	 */
	protected function _createComment( array $commentData, \IPS\Content\Item $item, string $postKey = NULL, $replyTo = NULL )
	{
		/* Work out the date */
		$date = \IPS\DateTime::create();
		$hidden = false;
		$quoteHtml = '';

		if( $replyTo !== NULL && $replyTo instanceof \IPS\Content\Comment ){
			try {
				$idField = $replyTo::$databaseColumnId;
				$app = $replyTo::$application;
				$replyToItem = $replyTo->item();
				$contentType = $replyToItem::$module;
				$itemClassSafe = str_replace( '\\', '_', mb_substr( $replyTo::$itemClass, 4 ) );
				$citationLang = \IPS\Member::loggedIn()->language()->get('_date_just_now_c') . ', ' . $replyTo->mapped('author_name');

				$quoteHtml = <<<HTML
					<blockquote class="ipsQuote" data-ipsquote="" data-ipsquote-contentapp="{$app}" data-ipsquote-contentclass="{$itemClassSafe}" data-ipsquote-contentcommentid="{$replyTo->mapped('id')}" data-ipsquote-contentid="{$replyTo->item()->mapped('id')}" data-ipsquote-contenttype="{$contentType}" data-ipsquote-timestamp="{$replyTo->mapped('date')}" data-ipsquote-userid="{$replyTo->author()->member_id}" data-ipsquote-username="{$replyTo->mapped('author_name')}">
						<div class="ipsQuote_citation">
							{$citationLang}
						</div>
						<div class='ipsQuote_contents'>{$replyTo->mapped('content')}</div>
					</blockquote>
HTML;
			} catch ( \Exception $err ) {
				// If something goes wrong here, it isn't a big deal - just continue without the quote
			}
		}
		
		/* Add attachments */
		$attachmentIdsToClaim = array();
		if ( $postKey )
		{
			try
			{
				$this->_addAttachmentsToContent( $postKey, $commentData['content'] );
			}
			catch ( \DomainException $e )
			{
				throw new \IPS\Api\GraphQL\SafeException( 'ATTACHMENTS_TOO_LARGE', '2S400/2', 403 );
			}
		}
		
		/* Parse */
		$content = $quoteHtml . \IPS\Text\Parser::parseStatic( $commentData['content'], TRUE, md5( $postKey . ':' ), \IPS\Member::loggedIn(), $item::$application . '_' . mb_ucfirst( $item::$module ) );
		
		/* Create post */
		$class = $this->class;
		/*if ( \in_array( 'IPS\Content\Review', class_parents( $class ) ) )
		{
			$comment = $class::create( $item, $content, FALSE, \intval( \IPS\Request::i()->rating ), $author->member_id ? NULL : $author->real_name, $author, $date, ( !$this->member and \IPS\Request::i()->ip_address ) ? \IPS\Request::i()->ip_address : \IPS\Request::i()->ipAddress(), $hidden );
		}
		else
		{*/
			$comment = $class::create( $item, $content, FALSE, \IPS\Member::loggedIn()->member_id ? NULL : \IPS\Member::loggedIn()->real_name, NULL, \IPS\Member::loggedIn(), $date, \IPS\Request::i()->ipAddress() );
		/*}*/
		$itemIdColumn = $item::$databaseColumnId;
		$commentIdColumn = $comment::$databaseColumnId;
		\IPS\File::claimAttachments( "{$postKey}:", $item->$itemIdColumn, $comment->$commentIdColumn );
		
		/* Index */
		if ( $item instanceof \IPS\Content\Searchable )
		{
			if ( $item::$firstCommentRequired and !$comment->isFirst() )
			{
				if ( \in_array( 'IPS\Content\Searchable', class_implements( $class ) ) )
				{					
					\IPS\Content\Search\Index::i()->index( $item->firstComment() );
				}
			}
			else
			{
				\IPS\Content\Search\Index::i()->index( $item );
			}
		}
		if ( $comment instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $comment );
		}
		
		/* Hide */
		if ( isset( $commentData['hidden'] ) and \IPS\Member::loggedIn()->member_id and $comment->canHide() )
		{
			$comment->hide( \IPS\Member::loggedIn() );
		}

		/* Mark it as read */
		if( $item instanceof \IPS\Content\ReadMarkers )
		{
			$item->markRead();
		}
		
		/* Return */
		return $comment;
	}

}
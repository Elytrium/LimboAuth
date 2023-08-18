<?php
/**
 * @brief		Content Discovery Stream Subscription
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		30 Jul 2021
 */

namespace IPS\core\Stream;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Discovery Stream Subscription
 */
class _Subscription extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'core_stream_subscriptions';


	/*
	 * Get the stream object
	 *
	 * return \IPS\core\Stream
	 */
	public function get_stream() : \IPS\core\Stream
	{
		return \IPS\core\Stream::load( $this->stream_id) ;
	}

	/**
	 * Send Digest
	 *
	 * @return    void
	 */
	public function send( array $data, $stream, \IPS\Member $recipient, $subscriptionRow, $showMoreLink = FALSE )
	{
		$email = \IPS\Email::buildFromTemplate( 'core', 'activity_stream_subscription', array( $stream, $data, $recipient, $subscriptionRow, $showMoreLink ), \IPS\Email::TYPE_LIST );
		$email->setUnsubscribe( 'core', 'unsubscribeStream', array( $subscriptionRow['id'], $stream->_title, md5( $recipient->email . ';' . $recipient->ip_address . ';' . $recipient->joined->getTimestamp() ) ) );

		$email->send($recipient);
	}

	/**
	 * Process a batch of digests
	 *
	 * @param string $frequency One of either "daily" or "weekly" to denote the kind of digest to send
	 * @param int $numberToSend The number of digests to send for this batch
	 * @return    bool
	 */
	public static function sendBatch( $frequency = 'daily', $numberToSend = 25 )
	{
		$subscriptions = iterator_to_array(
			\IPS\Db::i()->select( 'core_stream_subscriptions.*, core_members.last_visit', 'core_stream_subscriptions', array('frequency = ? AND sent < ? and last_visit > ?', $frequency, ( $frequency == 'daily' ) ? time() - 86400 : time() - 604800, time() - \IPS\Settings::i()->activity_stream_subscriptions_inactive_limit * 86400), 'sent ASC', array(0, $numberToSend), NULL, NULL, \IPS\Db::SELECT_DISTINCT | \IPS\Db::SELECT_FROM_WRITE_SERVER )
			->join( 'core_members', 'core_stream_subscriptions.member_id=core_members.member_id') );

		if ( !\count( $subscriptions ) )
		{
			/* Nothing to send */
			return FALSE;
		}

		$ids = [];
		foreach ( $subscriptions as $row )
		{
			$member = \IPS\Member::load( $row['member_id'] );
			if ( !$member->email or $member->isBanned() )
			{
				/* Update sent time, so the batch doesn't get stuck in a loop */
				\IPS\Db::i()->update( 'core_stream_subscriptions', array( 'sent' => time() ), array( 'id=?', $row['id'] ) );
				continue;
			}

			/* Build it */
			$mail = new static;
			$data = $mail->getContentForStream( $row );
			$ids[] = $row['id'];
			if( $items = \count( $data ) )
			{
				$showMore = FALSE;
				if( $items > 10 )
				{
					$data = \array_slice($data, 0, 10);
					$showMore = TRUE;
				}

				$mail->send( $data, \IPS\core\Stream::load( $row['stream_id'] ), \IPS\Member::load( $row['member_id'] ), $row, $showMore );
			}
		}

		if( \count( $ids ) )
		{
			\IPS\Db::i()->update( 'core_stream_subscriptions', array( 'sent' => time() ), \IPS\Db::i()->in( 'id', $ids ) );
		}

		return TRUE;
	}

	/**
	 * @param \IPS\core\Stream $stream
	 * @param \IPS\Member|null $member
	 * @return Subscription|null
	 */
	public static function loadByStreamAndMember( \IPS\core\Stream $stream, \IPS\Member $member = NULL ) : ?\IPS\core\Stream\Subscription
	{
		$member = $member ?: \IPS\Member::loggedIn();
		try
		{
			return static::constructFromData( \IPS\Db::i()->select('*', static::$databaseTable, ['stream_id=? AND member_id=?', $stream->id, $member->member_id ] )->first() );
		}
		catch( \UnderflowException $e )
		{
			return NULL;
		}
	}

	/**
	 * Fetch all the content for the stream
	 *
	 * @param array $subscriptionRow
	 * @return array
	 */
	public function getContentForStream( array $subscriptionRow ) : array
	{
		$items = [];
		$stream = \IPS\core\Stream::load( $subscriptionRow['stream_id'] );

		$query = $stream->query( \IPS\Member::load( $subscriptionRow['member_id']) );

		
		/* Override the timeframe and set it to the last sent time */
		$query->filterByCreateDate( \IPS\DateTime::ts( $subscriptionRow['sent'] ) );
		/* We want only 10 items for the email, so we'll grab 11 to see if we need to show the "more link" */
		$query->setLimit(11);

		/* Get the results */
		$results = $query->search( NULL, $stream->tags ? explode( ',', $stream->tags ) : NULL, ( $stream->include_comments ? \IPS\Content\Search\Query::TAGS_MATCH_ITEMS_ONLY + \IPS\Content\Search\Query::TERM_OR_TAGS : \IPS\Content\Search\Query::TERM_OR_TAGS ) );

		/* Load data we need like the authors, etc */
		$results->init();

		foreach ( $results as $result )
		{
			$data = $result->asArray();
			$itemClass = $data['indexData']['index_class'];
			$object = $itemClass::load( $data['indexData']['index_object_id']);

			if( \in_array( 'IPS\Content\Comment', class_parents( $itemClass ) ) )
			{
				$itemClass = $itemClass::$itemClass;
			}

			$containerUrl = NULL;
			$containerTitle = NULL;
			if ( isset( $itemClass::$containerNodeClass ) )
			{
				$containerClass	= $itemClass::$containerNodeClass;
				$containerTitle	= $containerClass::titleFromIndexData( $data['indexData'], $data['itemData'], $data['containerData'] );
				$containerUrl	= $containerClass::urlFromIndexData( $data['indexData'], $data['itemData'], $data['containerData'] );
			}

			$items[] = array_merge($data, [
				'title' => $object instanceof \IPS\Content\Comment ? $object->item()->searchIndexTitle() : $object->searchIndexTitle(),
				'url' => $object->url(),
				'content' => $object->content(),
				'object' => $object,
				'date' => \IPS\DateTime::ts( $object->mapped('date') ),
				'itemClass' => $itemClass,
				'containerUrl' => $containerUrl,
				'containerTitle' => $containerTitle
			]);
		}

		return $items;
	}
	/**
	 * Has the member any subscribed streams
	 *
	 * @param \IPS\Member|null $member
	 * @return bool
	 */
	public static function hasSubscribedStreams( \IPS\Member $member = NULL ) : bool
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return (bool) \IPS\Db::i()->select( 'COUNT(*)', 'core_stream_subscriptions', array( 'member_id=?', $member->member_id ) )->first();
	}

	/**
	 * Has the member any subscribed streams
	 *
	 * @param \IPS\Member|null $member
	 * @return \Iterator
	 */
	public static function getSubscribedStreams( \IPS\Member $member = NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		return new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_stream_subscriptions', array( 'member_id=?', $member->member_id ) ), \get_called_class() );
	}

}

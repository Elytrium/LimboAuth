<?php
/**
 * @brief		postBeforeRegisterFollowup Task
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Sep 2018
 */

namespace IPS\core\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * postBeforeRegisterFollowup Task
 */
class _postBeforeRegisterFollowup extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * If ran successfully, should return anything worth logging. Only log something
	 * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which will be most cases).
	 * If an error occurs which means the task could not finish running, throw an \IPS\Task\Exception - do not log an error as a normal log.
	 * Tasks should execute within the time of a normal HTTP request.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{	
		$this->runUntilTimeout( function()
		{
			/* Get the email */
			try
			{
				$userData = \IPS\Db::i()->select( array( 'email', 'language', 'secret' ), 'core_post_before_registering', array( "`member` IS NULL and followup IS NULL and timestamp<" . ( time() - ( 86400 * 2 ) ) ), 'timestamp ASC', 1 )->first();
			}
			catch ( \UnderflowException $e )
			{
				/* Disable the task if there is nothing at all left to process */
				if( \IPS\Db::i()->select( 'COUNT(*)', 'core_post_before_registering', array( "`member` IS NULL") )->first() === 0 )
				{
					$this->enabled = FALSE;
					$this->save();
				}

				return FALSE;
			}
			
			/* Get the associated content */
			$content = array();
			foreach ( \IPS\Db::i()->select( array( 'class', 'id' ), 'core_post_before_registering', array( 'email=?', $userData['email'] ) ) as $contentRow )
			{
				$class = $contentRow['class'];
				try
				{
					/* Remove any data if the content class doesn't exist any more */
					if ( !class_exists( $class ) )
					{
						throw new \OutOfRangeException;
					}

					$content[] = $class::load( $contentRow['id'] );
				}
				catch ( \OutOfRangeException $e )
				{
					\IPS\Db::i()->delete( 'core_post_before_registering', array( 'class=? AND id=?', $contentRow['class'], $contentRow['id'] ) );
				}
			}
			if ( !$content )
			{
				return TRUE;
			}
			
			/* Build the email */
			try
			{
				$language = \IPS\Lang::load( $userData['language'] );
			}
			catch ( \Exception $e )
			{
				$language = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
			}			
			$email = \IPS\Email::buildFromTemplate( 'core', 'postBeforeRegisterFollowup', array( $content, $userData['secret'] ), \IPS\Email::TYPE_TRANSACTIONAL );
			$email->language = $language;
			$email->setUnsubscribe( 'core', 'unsubscribeNotNeeded' );
			$email->send( $userData['email'] );
			
			/* Update the row */
			\IPS\Db::i()->update( 'core_post_before_registering', array( 'followup' => time() ), array( 'email=?', $userData['email'] ) );
			
			/* Return */
			return TRUE;
		} );
	}
	
	/**
	 * Cleanup
	 *
	 * If your task takes longer than 15 minutes to run, this method
	 * will be called before execute(). Use it to clean up anything which
	 * may not have been done
	 *
	 * @return	void
	 */
	public function cleanup()
	{
		
	}
}
<?php

/**
 * @brief        LiveTopic Trait
 * @author        <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license        https://www.invisioncommunity.com/legal/standards/
 * @package        Invision Community
 * @since        17 Nov 2022
 */

namespace IPS\forums\Topic;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * LiveTopic Trait
 *
 * This carries specific functionality for LiveTopic and forum topic interactions
 * @todo consider moving into cloud but would be tricky given you cannot hook into traits and methods will need to be exposed to normal forum logic.
 */
trait LiveTopic
{
	/**
	 * Return the live topic, or null if one doesn't exist / customer not on cloud
	 *
	 * @return \IPS\cloud\LiveTopic|null
	 */
	public function getLiveTopic(): ?\IPS\cloud\LiveTopic
	{
		if ( \IPS\Application::appIsEnabled( 'cloud' ) )
		{
			try
			{
				return \IPS\cloud\LiveTopic::load( $this->tid, 'topic_topic_id' );
			}
			catch ( \OutOfRangeException )
			{
				return NULL;
			}
		}

		return NULL;
	}


}
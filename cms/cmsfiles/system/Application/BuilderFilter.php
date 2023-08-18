<?php
/**
 * @brief		Application builder custom filter iterator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Aug 2013
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Custom filter iterator for application building
 */
class _BuilderFilter extends \RecursiveFilterIterator
{
	/**
	 * Accept the member
	 *
	 * @return bool
	 */
	public function accept()
	{
		return !( $this->isDir() && \in_array( $this->getFilename(), $this->getDirectoriesToIgnore() ) );
	}

	/**
	 * Returns the skipped directories
	 *
	 * @return array
	 */
	protected function getDirectoriesToIgnore()
	{
		return array(
			'.git',
			'.svn',
			'dev'
		);
	}
}
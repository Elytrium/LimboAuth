<?php
/**
 * @brief		Editor Stored Replies aka Stock Actions aka whatever else I rename it during this coding session
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Code
 * @since		03 September 2021
 */

namespace IPS\core\modules\admin\editor;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Stored Replies
 */
class _storedreplies extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\StoredReplies';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'stored_replies_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Have we not dragged the button to the editor bar? */
		$toolbars = json_decode( \IPS\Settings::i()->ckeditor_toolbars, true );
		$found = 0;
		foreach ( array('desktop', 'tablet', 'phone') as $device )
		{
			foreach ( $toolbars[$device] as $row )
			{
				if ( \is_array( $row ) and isset( $row['items'] ) )
				{
					if ( \in_array( 'ipsstockreplies', $row['items'] ) )
					{
						$found++;
					}
				}
			}
		}

		/* Do we have any stored replies yet? */
		if ( \IPS\Db::i()->select('count(*)', 'core_editor_stored_replies' )->first() )
		{
			if ( $found > 0 and $found < 3 )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'editor_stored_replies_not_all_editors_have_button', 'info' );
			}
			else
			{
				if ( $found == 0 )
				{
					\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'editor_stored_replies_no_editor_has_button', 'warning' );
				}
			}
		}
		else
		{
			if ( $found )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'editor_stored_replies_none_created_button', 'info' );
			}
		}

		parent::manage();
	}

	/**
	 * Search
	 *
	 * @return	void
	 */
	protected function search()
	{
		$rows = array();

		/* Get results */
		$nodeClass = $this->nodeClass;
		$results = [];

		/* Convert to HTML */
		foreach ( $nodeClass::search( 'reply_title', \IPS\Request::i()->input, 'reply_title' ) as $result )
		{
			$id = ( $result instanceof $this->nodeClass ? '' : 's.' ) . $result->_id;
			$rows[ $id ] = $this->_getRow( $result, FALSE, TRUE );
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'trees', 'core' )->rows( $rows, '' );
	}
}
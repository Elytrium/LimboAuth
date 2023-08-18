<?php
/**
 * @brief		Achievement badges
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Feb 2021
 */

namespace IPS\core\modules\admin\achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement badges
 */
class _badges extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\Achievements\Badge';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'badges_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if( $data = \IPS\core\Achievements\Rule::getRebuildProgress() )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'achievements' )->rebuildProgress( $data, TRUE );
		}

		\IPS\Output::i()->sidebar['actions']['export'] = array(
			'primary' => false,
			'icon' => 'cloud-download',
			'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=badges&do=exportForm'),
			'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_export') ),
			'title' => 'acp_achievements_export',
		);

		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'achievements', 'badges_manage' ) )
		{
			\IPS\Output::i()->sidebar['actions']['import'] = array(
				'primary' => false,
				'icon' => 'cloud-upload',
				'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=badges&do=importForm'),
				'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import') ),
				'title' => 'acp_achievements_import',
			);
		}


		return parent::manage();
	}

	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_member_badges', [ 'badge=?', $node->id ] )->first();
		
		$url = \IPS\Http\Url::internal("app=core&module=achievements&controller=badges&do=view&id={$node->id}");
		
		return \IPS\Theme::i()->getTemplate('achievements')->memberCount( $url, $count );
	}
	
	/**
	 * View who has earned a badge
	 *
	 * @return	void
	 */
	public function view()
	{
		$badge = \IPS\core\Achievements\Badge::load( \IPS\Request::i()->id );
		
		$table = new \IPS\Helpers\Table\Db( 'core_member_badges', \IPS\Http\Url::internal("app=core&module=achievements&controller=badges&do=view&id={$badge->id}"), [ 'badge=?', $badge->id ] );
		$table->joins[] = [
			'select'	=> 'action,identifier',
			'from'		=> 'core_achievements_log',
			'where'		=> 'core_achievements_log.id=core_member_badges.action_log',
			'type'		=> 'LEFT'
		];
		$table->langPrefix = 'badge_log_';
		$table->include = [ 'member', 'action_log', 'rule', 'datetime' ];
		$table->sortBy = $table->sortBy ?: 'datetime';

		$table->advancedSearch = array(
			'datetime'	=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			);

		$table->parsers = array(
			'member'	=> function ( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( \IPS\Member::load( $val ) );
			},
			'action_log'	=> function( $val, $row ) {
				try
				{
					if ( $row['action'] )
					{
						$exploded = explode( '_', $row['action'] );
						$extension = \IPS\Application::load( $exploded[0] )->extensions( 'core', 'AchievementAction' )[ $exploded[1] ];
						return $extension->logRow( $row['identifier'], explode( ',', $row['actor'] ) );
					}
					else
					{
						throw new \OutOfRangeException;
					}
				}
				catch( \OutOfRangeException $e )
				{
					return \IPS\Member::loggedIn()->language()->addToStack('unknown');
				}
			},
			'rule'	=> function( $val, $row ) {
				$rule = NULL;
				if ( $val )
				{
					try
					{
						$rule = \IPS\core\Achievements\Rule::load( $val );
					}
					catch ( \OutOfRangeException $e ) { }
				}
				
				if ( $rule )
				{
					return \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->basicUrl( \IPS\Http\Url::internal("app=core&module=achievements&controller=rules&do=form&id={$rule->id}"), FALSE, $rule->extension()->ruleDescription( $rule ), TRUE, FALSE, FALSE, TRUE );
				}
				else
				{
					return \IPS\Member::loggedIn()->language()->addToStack('unknown');
				}
			},
			'datetime'	=> function( $val )
			{
				return \IPS\DateTime::ts( $val );
			}
		);
		
		\IPS\Output::i()->title		= $badge->_title;
		\IPS\Output::i()->output	= $table;
	}

	/**
	 * Import dialog
	 *
	 * @return void
	 */
	public function exportForm()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'badges_manage' );
		$assignedBadges = \IPS\core\Achievements\Badge::getAssignedBadgeIds();

		$where = NULL;
		if ( \count( $assignedBadges ) )
		{
			$where = [ \IPS\Db::i()->in( '`id`', $assignedBadges, TRUE ) ];
		}

		$exportableBadges = \IPS\Db::i()->select( 'COUNT(*)', 'core_badges', $where )->first();

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'achievements' )->badgeExport( $exportableBadges );
	}

	/**
	 * Export ranks with images as an XML file (XML is better at potentially large values from raw image data)
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function export()
	{
		$xml = \IPS\Xml\SimpleXML::create('badges');

		$langs = [];
		foreach( \IPS\Db::i()->select('word_key, word_default', 'core_sys_lang_words', [ "lang_id=? AND word_key LIKE 'core_badges_%'", \IPS\Member::loggedIn()->language()->id ] ) as $row )
		{
			$langs[ \mb_substr( $row['word_key'], 12 ) ] = $row['word_default'];
		}

		$assignedBadges = \IPS\core\Achievements\Badge::getAssignedBadgeIds();

		$where = NULL;
		if ( \count( $assignedBadges ) )
		{
			$where = [ \IPS\Db::i()->in( '`id`', $assignedBadges, TRUE ) ];
		}

		/* Ranks */
		foreach ( \IPS\Db::i()->select( '*', 'core_badges', $where ) as $badge )
		{
			try
			{
				$icon = \IPS\File::get( 'core_Badges', $badge['image'] );

				$forXml = [
					'manually_awarded' => $badge['manually_awarded'],
					'title' => $langs[ $badge['id'] ],
					'icon_name' => $icon->originalFilename,
					'icon_data' => base64_encode( $icon->contents() )
				];

				$xml->addChild( 'badge', $forXml );
			}
			catch( \Exception $e ) { }
		}
		
		\IPS\Session::i()->log( 'acplogs__exported_badges' );

		\IPS\Output::i()->sendOutput( $xml->asXML(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', "Achievement_Badges.xml" ) ) );
	}

	/**
	 * Import from upload
	 *
	 * @return	void
	 */
	public function import()
	{
		\IPS\Session::i()->csrfCheck();

		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3C130/1', 500, '' );
		}

		try
		{
			\IPS\core\Achievements\Badge::importXml( \IPS\Request::i()->file, ( ! empty( \IPS\Request::i()->wipe ) ) );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2C422/1', 403, '' );
		}
		
		\IPS\Session::i()->log( 'acplogs__imported_badges' );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=badges' ), 'completed' );
	}

	/**
	 * Import dialog
	 *
	 * @return void
	 */
	public function importForm()
	{
		$form = new \IPS\Helpers\Form( 'form', 'acp_achievements_import' );

		$form->add( new \IPS\Helpers\Form\YesNo( 'acp_achievements_import_option_badge_wipe', 0, FALSE, [], NULL, NULL, NULL, 'acp_achievements_import_option_rule_wipe' ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'acp_achievements_import_xml', NULL, FALSE, [ 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ], NULL, NULL, NULL, 'acp_achievements_import_xml' ) );

		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['acp_achievements_import_xml'], $tempFile );

			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=badges&do=import' )->setQueryString( array('wipe' => $values['acp_achievements_import_option_badge_wipe'], 'file' => $tempFile, 'key' => md5_file( $tempFile )) )->csrf() );
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import'), $form, FALSE );
	}
}
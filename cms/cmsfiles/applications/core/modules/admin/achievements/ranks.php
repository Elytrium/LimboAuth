<?php
/**
 * @brief		Achievement ranks
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		10 Apr 2013
 */

namespace IPS\core\modules\admin\achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement ranks
 */
class _ranks extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * @brief	Cached counts
	 */
	public static $counts = [];
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\Achievements\Rank';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'ranks_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		static::$counts = [];
		$previousCount = NULL;
		
		$allRanks = array_values( \IPS\core\Achievements\Rank::getStore() );
		$totalRanks = \count( $allRanks );
		for ( $i = 0; $i < $totalRanks; $i++ )
		{
			$where = [];
			$where[] = [ 'achievements_points>=' . \intval( $allRanks[ $i ]->points ) ];
			if ( isset( $allRanks[ $i + 1 ] ) )
			{
				$where[] = [ 'achievements_points<' . \intval( $allRanks[ $i + 1 ]->points ) ];
			}
			static::$counts[ $allRanks[ $i ]->id ] = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $where )->first();
		}

		if( $data = \IPS\core\Achievements\Rule::getRebuildProgress() )
		{
			\IPS\Output::i()->output .= \IPS\Theme::i()->getTemplate( 'achievements' )->rebuildProgress( $data, TRUE );
		}

		\IPS\Output::i()->sidebar['actions']['export'] = array(
			'primary' => false,
			'icon' => 'cloud-download',
			'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=ranks&do=export'),
			'data'	=> [],
			'title' => 'acp_achievements_export',
		);

		\IPS\Output::i()->sidebar['actions']['import'] = array(
			'primary' => false,
			'icon' => 'cloud-upload',
			'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=ranks&do=importForm'),
			'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import') ),
			'title' => 'acp_achievements_import',
		);

		return parent::manage();
	}

	/**
	 * Import dialog
	 *
	 * @return void
	 */
	public function importForm()
	{
		$form = new \IPS\Helpers\Form( 'form', 'acp_achievements_import' );

		$form->add( new \IPS\Helpers\Form\Radio( 'acp_achievements_import_option', 'replace', FALSE, [ 'options' =>
		[
			'wipe' => 'acp_achievements_import_option_wipe',
			'replace' => 'acp_achievements_import_option_replace'
		],
			'toggles' => [ 'wipe' => [ 'acp_achievements_import_option_wipe_sure' ] ]
		] ) );

		$form->add( new \IPS\Helpers\Form\YesNo( 'acp_achievements_import_option_wipe_sure', 0, FALSE, [], NULL, NULL, NULL, 'acp_achievements_import_option_wipe_sure' ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'acp_achievements_import_xml', NULL, FALSE, [ 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ], NULL, NULL, NULL, 'acp_achievements_import_xml' ) );

		if ( $values = $form->values() )
		{
			if ( $values['acp_achievements_import_option'] == 'wipe' and empty( $values['acp_achievements_import_option_wipe_sure'] ) )
			{
				$form->error = \IPS\Member::loggedIn()->language()->addToStack( 'acp_achievements_import_error' );
			}
			else
			{
				/* Move it to a temporary location */
				$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
				move_uploaded_file( $values['acp_achievements_import_xml'], $tempFile );

				/* Initate a redirector */
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=ranks&do=import' )->setQueryString( array('option' => $values['acp_achievements_import_option'], 'file' => $tempFile, 'key' => md5_file( $tempFile )) )->csrf() );
			}
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import'), $form, FALSE );
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
			\IPS\core\Achievements\Rank::importXml( \IPS\Request::i()->file, \IPS\Request::i()->option );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2C423/1', 403, '' );
		}
		
		\IPS\Session::i()->log( 'acplogs__imported_ranks' );
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=ranks' ), 'completed' );
	}

	/**
	 * Export ranks with images as an XML file (XML is better at potentially large values from raw image data)
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function export()
	{
		$xml = \IPS\Xml\SimpleXML::create('ranks');
		$langs = [];

		foreach( \IPS\Db::i()->select('word_key, word_default', 'core_sys_lang_words', [ "lang_id=? AND word_key LIKE 'core_member_rank_%'", \IPS\Member::loggedIn()->language()->id ] ) as $row )
		{
			$langs[ \mb_substr( $row['word_key'], 17 ) ] = $row['word_default'];
		}

		/* Ranks */
		foreach ( \IPS\Db::i()->select( '*', 'core_member_ranks') as $row )
		{
			$forXml = [
				'title'  => $langs[$row['id']] ?? NULL,
				'points' => $row['points']
			];

			if ( $row['icon'] )
			{
				try
				{
					$icon = \IPS\File::get( 'core_Ranks', $row['icon'] );
					$forXml['icon_name'] = $icon->originalFilename;
					$forXml['icon_data'] = base64_encode( $icon->contents() );
				}
				catch( \Exception $e ) { }
			}

			$xml->addChild( 'rank', $forXml );
		}
		
		\IPS\Session::i()->log( 'acplogs__exported_ranks' );

		\IPS\Output::i()->sendOutput( $xml->asXML(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', "Achievement_Ranks.xml" ) ) );
	}

	/**
	 * Fetch any additional HTML for this row
	 *
	 * @param	object	$node	Node returned from $nodeClass::load()
	 * @return	NULL|string
	 */
	public function _getRowHtml( $node )
	{
		$url = \IPS\Http\Url::internal("app=core&module=members&controller=members&advanced_search_submitted=1&members_achievements_points={$node->id}")->csrf();
		
		return \IPS\Theme::i()->getTemplate('achievements')->memberCount( $url, static::$counts[ $node->id ] );
	}
}
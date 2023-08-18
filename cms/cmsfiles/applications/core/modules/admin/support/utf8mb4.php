<?php
/**
 * @brief		utf8mb4 Converter
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 April 2016
 */

namespace IPS\core\modules\admin\support;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * utf8mb4 Converter
 */
class _utf8mb4 extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Bootstrap
	 *
	 * @return	void
	 */
	protected function manage()
	{	
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('utf8mb4_converter');

		/* Check it isn't utf8mb4 already */
		if ( \IPS\Settings::i()->getFromConfGlobal('sql_utf8mb4') === TRUE)
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global', 'core')->message( 'utf8mb4_converter_finished', 'success' );
			return;
		}
		
		/* Requires MySQL 5.5.3 */
		if ( !\IPS\CIC AND version_compare( \IPS\Db::i()->server_info, '5.5.3', '<' ) )
		{
			\IPS\Output::i()->error( 'utf8mb4_converter_requires_553', '1C325/1', 403, '' );
		}
		
		/* Display Wizard */
		$supportController = new support;
		\IPS\Output::i()->output = (string) new \IPS\Helpers\Wizard( array(
			'utf8mb4_converter_intro'	=> array( $this, '_introduction' ),
			'utf8mb4_converter_convert'	=> array( $this, '_convert' ),
			'utf8mb4_converter_finish'	=> array( $this, '_finish' ),
		), \IPS\Http\Url::internal( 'app=core&module=support&controller=utf8mb4' )->csrf() );
	}
	
	/**
	 * Introduction
	 *
	 * @param	mixed	$data	Wizard Data
	 * @return	mixed
	 */
	public function _introduction( $data )
	{
		$form = new \IPS\Helpers\Form( 'utf8mb4_converter_intro', 'continue' );
		$form->hiddenValues['continue'] = 1;
		$form->addMessage( \IPS\CIC ? 'utf8mb4_converter_cic_explain' : 'utf8mb4_converter_explain' );
		
		if ( $form->values() )
		{
			return array();
		}
		
		return (string) $form;
	}
	
	/**
	 * Convert
	 *
	 * @param	mixed	$wizardData	Wizard Data
	 * @return	mixed
	 */
	public function _convert( $wizardData )
	{
		\IPS\Session::i()->csrfCheck();

		if ( isset( \IPS\Request::i()->finished ) )
		{
			return $wizardData;
		}
		
		$baseUrl = \IPS\Http\Url::internal( 'app=core&module=support&controller=utf8mb4' )->csrf();
		
		return new \IPS\Helpers\MultipleRedirect( $baseUrl,
			function( $mrData )
			{
				try
				{
					/* If this is the first run, do the database itself... */
					if ( !\is_array( $mrData ) )
					{
						$databaseName = \IPS\Settings::i()->sql_database;
						\IPS\Db::i()->query("ALTER DATABASE `{$databaseName}` CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;");
											
						return array( array( 'done' => array() ), \IPS\Member::loggedIn()->language()->addToStack('utf8mb4_converter_converting'), 0 );
					}
					
					/* Set properties */
					\IPS\Db::i()->charset = 'utf8mb4';
					\IPS\Db::i()->collation = 'utf8mb4_unicode_ci';
					\IPS\Db::i()->binaryCollation = 'utf8mb4_bin';
					
					/* Do each table */
					$select = \IPS\Settings::i()->sql_tbl_prefix ? ( new \IPS\Db\Select( "SHOW TABLES LIKE '" . \IPS\Db::i()->escape_string( \IPS\Settings::i()->sql_tbl_prefix ) . "%'", array(), \IPS\Db::i() ) ) : ( new \IPS\Db\Select( "SHOW TABLES", array(), \IPS\Db::i() ) );
					$totalCount = \count( $select );
					$i = 0;
					foreach ( $select as $table )
					{						
						$i++;
						$table = mb_substr( $table, mb_strlen( \IPS\Settings::i()->sql_tbl_prefix ) );
												
						/* If we've already done it, skip to next */
						if ( \in_array( $table, $mrData['done'] ) )
						{
							continue;
						}
						
						/* Check it belongs to us */
						$appName = mb_substr( $table, 0, mb_strpos( $table, '_' ) );
						try
						{
							$app = \IPS\Application::load( $appName );
							$schema	= json_decode( file_get_contents( \IPS\ROOT_PATH . "/applications/{$app->directory}/data/schema.json" ), TRUE );
							if ( !array_key_exists( $table, $schema ) )
							{
								/* Make a special exception for CMS database tables */
								if( $app->directory !== 'cms' OR mb_strpos( $table, 'cms_custom_database_' ) !== 0 )
								{
									continue;
								}
							}
						}
						catch ( \Exception $e )
						{
							continue;
						}

						/* If this is the search index table, clear it first. Some clients have timeouts on this step with the search index
							table because it is large and it has a primary key and a unique index, so the table work is too intensive. */
						if( $table == 'core_search_index' )
						{
							\IPS\Db::i()->delete( 'core_search_index' );
						}
						
						/* Get table definition */
						$tableDefinition = \IPS\Db::i()->getTableDefinition( $table, FALSE, TRUE );
						
						/* Drop any potentially problematic indexes */
						$indexesToRecreate = array();
						$maxLen = \mb_strtolower( $tableDefinition['engine'] ) === 'innodb' ? 767 : 1000;
						foreach ( $tableDefinition['indexes'] as $indexName => $indexData )
						{
							/* If this is a fulltext index, we don't need to drop it and recreate it */
							if( mb_strtolower( $indexData['type'] ) == 'fulltext' )
							{
								continue;
							}

							/* If all columns in an index are already utf8mb4 then we don't need to worry about recreating the index */
							$needToRecreate = FALSE;

							$length = 0;
							$hasText = false;
							foreach( $indexData['columns'] as $column )
							{
								if ( \in_array( mb_strtolower( $tableDefinition['columns'][ $column ]['type'] ), array( 'mediumtext', 'text' ) ) )
								{
									$hasText = true;
								}
								if ( isset( $tableDefinition['columns'][ $column ]['length'] ) )
								{
									$length += $tableDefinition['columns'][ $column ]['length'];
								}

								if( isset( $tableDefinition['columns'][ $column ]['collation'] ) AND $tableDefinition['columns'][ $column ]['collation'] !== 'utf8mb4_unicode_ci' )
								{
									$needToRecreate = TRUE;
								}
							}
														
							if ( $needToRecreate AND ( ( $length * 4 > $maxLen ) or $hasText ) )
							{
								$indexesToRecreate[ $indexName ] = $indexData;
								\IPS\Db::i()->dropIndex( $table, $indexName );
							}
						}
												
						/* Do the table */
						$repair	= false;

						if( $tableDefinition['collation'] !== 'utf8mb4_unicode_ci' )
						{
							\IPS\Db::i()->query("ALTER TABLE `" . \IPS\Settings::i()->sql_tbl_prefix . "{$table}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
							\IPS\Db::i()->query("ALTER TABLE `" . \IPS\Settings::i()->sql_tbl_prefix . "{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
							$repair	= true;
						}

						/* Aggregate all the changes into one query for efficiency */
						$aggregatedChanges = array();

						/* Do each column */
						foreach ( $tableDefinition['columns'] as $columnName => $columnData )
						{
							if( \in_array( \strtoupper( $columnData['type'] ), array( 'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET' ) ) )
							{
								if( $tableDefinition['columns'][ $columnName ]['collation'] !== 'utf8mb4_unicode_ci' )
								{
									$aggregatedChanges[] = "CHANGE COLUMN `" . \IPS\Db::i()->escape_string( $columnName ) . "` " . \IPS\Db::i()->compileColumnDefinition( $columnData );
									$repair = true;
								}
							}
						}
						
						/* Recreate any indexes */
						foreach ( $indexesToRecreate as $indexName => $indexData )
						{
							$aggregatedChanges[] = \IPS\Db::i()->buildIndex( $table, $indexData );
							$repair	= true;
						}

						/* Now if we have any changes to make....make them */
						if( \count( $aggregatedChanges ) )
						{
							\IPS\Db::i()->query( "ALTER TABLE " . \IPS\Db::i()->prefix . \IPS\Db::i()->escape_string( $table ) . " " . implode( ', ', $aggregatedChanges ) );
						}
												
						/* Repair and optimize */
						if( $repair === true )
						{
							\IPS\Db::i()->query("REPAIR TABLE `" . \IPS\Settings::i()->sql_tbl_prefix . "{$table}`");
							//\IPS\Db::i()->query("OPTIMIZE TABLE `" . \IPS\Settings::i()->sql_tbl_prefix . "{$table}`");
						}

						/* If this is the search index table, initiate rebuild now. */
						if( $table == 'core_search_index' and \IPS\Settings::i()->search_method == 'mysql' )
						{
							\IPS\Content\Search\Index::i()->rebuild();
						}
						
						/* Continue */
						$mrData['done'][] = $table;
						return array( $mrData, \IPS\Member::loggedIn()->language()->addToStack('utf8mb4_converter_converting'), floor( 100 / $totalCount * $i ) );
					}
					
					/* If we get to this point, we're finished */
					return NULL;
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'utf8mb4' );
					\IPS\Output::i()->error( \IPS\Member::loggedIn()->language()->addToStack( 'utf8mb4_converter_error', FALSE, array( 'sprintf' => array( $e->getMessage() ) ) ), '4C171/4', 403, '' );
				}	
				
			},
			function() use ( $baseUrl )
			{
				\IPS\Output::i()->redirect( $baseUrl->setQueryString( 'finished', 1 ) );
			}
		);
	}
	
	/**
	 * Finish
	 *
	 * @param	mixed	$data	Wizard Data
	 * @return	mixed
	 */
	public function _finish( $data )
	{
		\IPS\Session::i()->log( 'acplog__ran_utf8mb4_converter' );

		return \IPS\Theme::i()->getTemplate('support')->finishUtf8Mb4Conversion();
	}
}
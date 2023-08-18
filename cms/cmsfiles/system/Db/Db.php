<?php
/**
 * @brief		Database Class
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Feb 2013
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

// Make sure PHP 8.1 uses MySQL errors as we expect
\mysqli_report( MYSQLI_REPORT_OFF );

/**
 * @brief	Database Class
 * @note	All functionality MUST be supported by MySQL 5.1.3 and higher. All references to the MySQL manual are therefore the 5.1 version.
 */
class _Db extends \mysqli
{
	/**
	 * SELECT flags
	 */
	const SELECT_DISTINCT = 1;
	const SELECT_MULTIDIMENSIONAL_JOINS = 4;
	const SELECT_FROM_WRITE_SERVER = 8;

	/**
	 * INSERT/UPDATE flags
	 */
	const LOW_PRIORITY = 1;
	const IGNORE = 2;
	
	/**
	 * @brief	Datatypes
	 */
	public static $dataTypes = array(
		'database_column_type_numeric'	=> array(
			'TINYINT'	=> 'TINYINT [±127 ⊻ 255] [1B]',
			'SMALLINT'	=> 'SMALLINT [±3.3e4 ⊻ 6.6e4] [2B]',
			'MEDIUMINT'	=> 'MEDIUMINT [±8.4e6 ⊻ 1.7e7] [3B]',
			'INT'		=> 'INT [±2.1e9 ⊻ 4.3e9] [4B]',
			'BIGINT'	=> 'BIGINT [±9.2e18 ⊻ 1.8e19] [8B]',
			'DECIMAL'	=> 'DECIMAL',
			'FLOAT'		=> 'FLOAT',
			'BIT'		=> 'BIT',
			
		),
		'database_column_type_datetime'	=> array(
			'DATE'		=> 'DATE',
			'DATETIME'	=> 'DATETIME',
			'TIMESTAMP'	=> 'TIMESTAMP',
			'TIME'		=> 'TIME',
			'YEAR'		=> 'YEAR',
		),
		'database_column_type_string'	=> array(
			'CHAR'		=> 'CHAR [M≤6.6e4] [(M*w)B]',
			'VARCHAR'	=> 'VARCHAR [M≤6.6e4] [(L+(1∨2))B]',
			'TINYTEXT'	=> 'TINYTEXT [256B] [(L+1)B]',
			'TEXT'		=> 'TEXT [64kB] [(L+2)B]',
			'MEDIUMTEXT'=> 'MEDIUMTEXT [16MB] [(L+3)B]',
			'LONGTEXT'	=> 'LONGTEXT [4GB] [(L+4)B]',
			'BINARY'	=> 'BINARY [M≤6.6e4] [(M)B]',
			'VARBINARY'	=> 'VARBINARY [M≤6.6e4] [(L+(1∨2))B]',
			'TINYBLOB'	=> 'TINYBLOB [256B] [(L+1)B]',
			'BLOB'		=> 'BLOB [64kB] [(L+2)B]',
			'MEDIUMBLOB'=> 'MEDIUMBLOB [16MB] [(L+3)B]',
			'BIGBLOB'	=> 'BIGBLOB [4GB] [(L+4)B]',
			'ENUM'		=> 'ENUM [6.6e4] [(1∨2)B]',
			'SET'		=> 'SET [64] [(1∨2∨3∨4∨8)B]',
		)
	);

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	Our identifier
	 */
	public $identifier;

	/**
	 * @brief	Stored connection details so we can connect on-demand
	 */
	protected $connectionDetails = array();

	/**
	 * @brief	Track if we've connected
	 */
	protected $connections = array( 'read' => FALSE, 'write' => FALSE );

	/**
	 * Constructor
	 *
	 * @note	Overridden so we can delay connecting to the server until we actually need to
	 * @param	string		$host			Host
	 * @param	string		$username		Username
	 * @param	string		$password		Password
	 * @param	string		$database		Database name
	 * @param	int			$port			Port
	 * @param	string		$socket			Socket
	 * @param	bool		$utf8mb4		Use UTF8MB4?
	 * @param	string		$prefix			Table prefix to use
	 * @param	NULL|array	$readDatabase	If using read/write, the connection details for the read database
	 * @return	void
	 */
	public function __construct( $host = NULL, $username = NULL, $password = NULL, $database = "", $port = NULL, $socket = NULL, $utf8mb4 = true, $prefix = '', $readDatabase = NULL )
	{
		$this->connectionDetails = array(
			'host'		=> $host ?? ini_get("mysqli.default_host"),
			'username'	=> $username ?? ini_get("mysqli.default_user"),
			'password'	=> $password ?? ini_get("mysqli.default_pw"),
			'database'	=> $database,
			'port'		=> $port ?? ini_get("mysqli.default_port"),
			'socket'	=> $socket ?? ini_get("mysqli.default_socket"),
			'utf8mb4'	=> $utf8mb4,
			'readDatabase'	=> $readDatabase
		);

		/* Set the prefix */
		$this->prefix = $prefix;

		/* Now initialize the object so we can connect later */
		parent::init();
	}

	/**
	 * Get instance
	 *
	 * @param	mixed	$identifier			Identifier
	 * @param	array	$connectionSettings	Connection settings (use when initiating a new connection)
	 * @return	\IPS\Db
	 */
	public static function i( $identifier=NULL, $connectionSettings=array() )
	{
		/* Did we pass a null value? */
		$identifier	= ( $identifier === NULL ) ? '__MAIN' : $identifier;
	
		/* Don't have an instance? */
		if( !isset( static::$multitons[ $identifier ] ) )
		{
			/* Load the default settings if necessary */
			if( $identifier === '__MAIN' )
			{
				require( \IPS\SITE_FILES_PATH . '/conf_global.php' );
				if ( \IPS\CIC2 )
				{
					$INFO['sql_pass']		= $_SERVER['IPS_CLOUD2_DBPASS'];
					$INFO['sql_read_pass']	= $_SERVER['IPS_CLOUD2_DBPASS'];
				}
				$connectionSettings = isset( $INFO ) ? $INFO : array();
			}

			$readDatabase = NULL;

			/* Read/Write Separation? */
			if ( isset( $connectionSettings['sql_read_host'] ) and \IPS\READ_WRITE_SEPARATION )
			{
				$readDatabase = array(
					'host'		=> $connectionSettings['sql_read_host'],
					'username'	=> $connectionSettings['sql_read_user'],
					'password'	=> $connectionSettings['sql_read_pass'],
					'database'	=> $connectionSettings['sql_read_database'],
					'port'		=> ( isset( $connectionSettings['sql_read_port'] ) and $connectionSettings['sql_read_port']) ? $connectionSettings['sql_read_port'] : NULL,
					'socket'	=> ( isset( $connectionSettings['sql_read_socket'] ) and $connectionSettings['sql_read_socket'] ) ? $connectionSettings['sql_read_socket'] : NULL,
				);
			}

			static::$multitons[ $identifier ] = new static(
				$connectionSettings['sql_host'],
				$connectionSettings['sql_user'],
				$connectionSettings['sql_pass'],
				$connectionSettings['sql_database'],
				( isset( $connectionSettings['sql_port'] ) and $connectionSettings['sql_port']) ? $connectionSettings['sql_port'] : NULL,
				( isset( $connectionSettings['sql_socket'] ) and $connectionSettings['sql_socket'] ) ? $connectionSettings['sql_socket'] : NULL,
				isset( $connectionSettings['sql_utf8mb4'] ) and $connectionSettings['sql_utf8mb4'],
				$connectionSettings['sql_tbl_prefix'] ?? '',
				$readDatabase
			);

			static::$multitons[ $identifier ]->identifier = $identifier;
		}
		
		/* Return */
		return static::$multitons[ $identifier ];
	}
	
	/**
	 * Apparently, get_charset can be unavailable
	 *
	 * @param	bool	$read	Read only connection?
	 * @return	string
	 */
	public function getCharset( $read=FALSE )
	{
		if ( method_exists( $this, 'get_charset' ) )
		{
			return ( $read AND $this->connectionDetails['readDatabase'] ) ? $this->reader->get_charset()->charset : static::get_charset()->charset;
		}
		else
		{
			return ( $read AND $this->connectionDetails['readDatabase'] ) ? $this->reader->character_set_name() : static::character_set_name();
		}
	}
	
	/**
	 * Establish database connection
	 *
	 * @param	bool	$read	Connect to read database (if specified)?
	 * @return	mysqli
	 */
	protected function _establishConnection( $read = FALSE )
	{
		/* Which details to use? */
		$sqlCredentials = $this->connectionDetails;
		$logDatabase	= 'database';

		if( $read AND $this->connectionDetails['readDatabase'] )
		{
			$sqlCredentials = $this->connectionDetails['readDatabase'];
			$logDatabase	= 'read database';

			$this->reader	= new \mysqli(
				$sqlCredentials['host'],
				$sqlCredentials['username'],
				$sqlCredentials['password'],
				$sqlCredentials['database'],
				$sqlCredentials['port'],
				$sqlCredentials['socket']
			);

			$error	= $this->reader->connect_error;
			$errno	= $this->reader->connect_errno;
		}
		else
		{
			$logDatabase	= 'write database';

			/* Connect */
			parent::real_connect(
				$sqlCredentials['host'],
				$sqlCredentials['username'],
				$sqlCredentials['password'],
				$sqlCredentials['database'],
				$sqlCredentials['port'],
				$sqlCredentials['socket']
			);

			$error	= mysqli_connect_error();
			$errno	= $this->connect_errno;
		}

		/* Store a log entry so we can track */
		$this->log( "Connected to the " . $logDatabase, ( $read and $this->connectionDetails['readDatabase'] ) ? 'read' : 'write' );

		/* If the connection failed, throw an exception */
		if( $error )
		{
			throw new \IPS\Db\Exception( $error, $errno );
		}

		/* Enable strict mode for IN_DEV */
		if ( \IPS\IN_DEV )
		{
			if( $read AND $this->connectionDetails['readDatabase'] )
			{
				$this->reader->query( "SET sql_mode='STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY,ANSI_QUOTES'" );
			}
			else
			{
				parent::query( "SET sql_mode='STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY,ANSI_QUOTES'" );
			}
		}
		
		/* Charset */
		if( $read AND $this->connectionDetails['readDatabase'] )
		{
			if ( $this->connectionDetails['utf8mb4'] )
			{
				if ( $this->reader->set_charset( 'utf8mb4' ) === FALSE )
				{
					/* If setting utf8mb4 fails, then gracefully fallback to normal utf8 */
					$this->reader->set_charset( 'utf8' );
				}
			}
			else
			{
				$this->reader->set_charset( 'utf8' );
			}
		}
		else
		{
			if ( $this->connectionDetails['utf8mb4'] )
			{
				if ( $this->set_charset( 'utf8mb4' ) === FALSE )
				{
					/* If setting utf8mb4 fails, then gracefully fallback to normal utf8 */
					$this->set_charset( 'utf8' );
				}
			}
			else
			{
				$this->set_charset( 'utf8' );
			}
		}

		/* Set charset / collation properties */
		if ( $this->getCharset( $read ) === 'utf8mb4' )
		{
			$this->charset = 'utf8mb4';
			$this->collation = 'utf8mb4_unicode_ci';
			$this->binaryCollation = 'utf8mb4_bin';
		}
		else
		{
			$this->charset = 'utf8';
			$this->collation = 'utf8_unicode_ci';
			$this->binaryCollation = 'utf8_bin';
		}
		
		/* Return */
		return $this;
	}

	/**
	 * Check if we are connected, and connect if not
	 *
	 * @param	bool	$read	Is this a read query (i.e. connect to reader)?
	 * @return	void
	 */
	public function checkConnection( $read = FALSE )
	{
		/* If we aren't using read/write separation, we only have one connection */
		if( !$this->connectionDetails['readDatabase'] )
		{
			$read = FALSE;
		}

		/* Have we already connected? */
		if( $this->connections[ $read ? 'read' : 'write' ] === TRUE )
		{
			return;
		}
				
		/* Connect */
		$this->_establishConnection( $read );

		/* And then flag that the connection was successful */
		$this->connections[ $read ? 'read' : 'write' ] = TRUE;
	}
	
	/**
	 * @brief	Charset
	 */
	public $charset = 'utf8';
	
	/**
	 * @brief	Collation
	 */
	public $collation = 'utf8_unicode_ci';
	
	/**
	 * @brief	Binary Collation
	 */
	public $binaryCollation = 'utf8_bin';
	
	/**
	 * @brief	Table Prefix
	 */
	public $prefix = '';
	
	/**
	 * @brief	Query log
	 */
	public $log = array();

	/**
	 * @brief	Return the query instead of executing it
	 * @note	Only designed to work with methods that call query() vs prepared statements
	 */
	public $returnQuery	= FALSE;
		
	/**
	 * @brief	MySQLi object for reading, if using read/write separation
	 */
	protected $reader = NULL;
	
	/**
	 * @brief	Read/Write Separation Enabled
	 * @todo	This is hacky. Do it properly later
	 */
	public $readWriteSeparation = TRUE;
	
	/**
	 * Run a query
	 *
	 * @param	string	$query	The query
	 * @param	bool	$log	Should be logged?
	 * @param	bool	$read	If TRUE and read/write separation is in use, will use the "read" connection 
	 * @return	mixed
	 * @see		<a href="http://uk1.php.net/manual/en/mysqli.query.php">mysqli::query</a>
	 * @throws	\IPS\Db\Exception
	 */
	public function query( $query, $log = TRUE, $read=FALSE )
	{
		/* Should we return the query instead of executing it? */
		if( $this->returnQuery === TRUE )
		{
			$this->returnQuery	= FALSE;
			return $query;
		}

		/* Make sure we're connected */
		$this->checkConnection( $read );

		/* Log */
		if ( \IPS\QUERY_LOG and $log )
		{
			$this->log( $query, ( $read and $this->readWriteSeparation ) ? 'read' : 'write' );
		}
		
		/* Run */
		if ( $read and $this->reader and $this->readWriteSeparation )
		{
			$return = $this->reader->query( $query );
			if ( $return === FALSE )
			{
				throw new \IPS\Db\Exception( $this->reader->error, $this->reader->errno );
			}
		}
		else
		{
			$return = parent::query( $query );
			if ( $return === FALSE )
			{
				throw new \IPS\Db\Exception( $this->error, $this->errno );
			}
		}
		
		/* Return */
		return $return;
	}

	/**
	 * Force a query to run regardless of $this->returnQuery
	 *
	 * @param	string	$query	The query
	 * @param	bool	$log	Should be logged?
	 * @param	bool	$read	If TRUE and read/write separation is in use, will use the "read" connection 
	 * @return	mixed
	 * @see		<a href="http://uk1.php.net/manual/en/mysqli.query.php">mysqli::query</a>
	 * @throws	\IPS\Db\Exception
	 */
	public function forceQuery( $query, $log = TRUE, $read=FALSE )
	{
		$return = $this->returnQuery;
		$this->returnQuery	= false;

		$result	= $this->query( $query, $log, $read );

		$this->returnQuery	= $return;

		return $result;
	}

	/**
	 * Run Prepared SQL Statement
	 *
	 * @param	string	$query	SQL Statement
	 * @param	array	$_binds	Variables to bind
	 * @param	bool	$read	If TRUE and read/write separation is in use, will use the "read" connection 
	 * @return	\mysqli_stmt
	 */
	public function preparedQuery( $query, array $_binds, $read=FALSE )
	{
		/* Make sure we're connected */
		$this->checkConnection( ( $read AND $this->readWriteSeparation ) );

		/* Init Bind object */
		$bind = new Db\Bind();
		
		/* Sort out subqueries */
		$binds = array();
		$i = 0;
		for ( $j = 0; $j < \strlen( $query ); $j++ )
		{
			if ( $query[ $j ] == '?' )
			{
				if ( array_key_exists( $i, $_binds ) )
				{
					if ( $_binds[ $i ] instanceof \IPS\Db\Select )
					{
						$query = \substr( $query, 0, $j ) . $_binds[ $i ]->query . \substr( $query, $j + 1);
						$j += \strlen( $_binds[ $i ]->query );

						foreach ( $_binds[ $i ]->binds as $_bind )
						{
							$binds[] = $_bind;
						}
					}
					else
					{
						$binds[] = $_binds[ $i ];
					}
					
					$i++;
				}
			}
		}
		
		/* Store the original query before the bind checks are done as NULL replaces ? which throws out the order in the query log */
		$queryForLog = $query;
		
		/* Loop values to bind */
		$i = 0;
		$longThreshold = 1048576;
		$sendAsLong = array();
		foreach ( $binds as $bindVal )
		{
			if( ( \is_object( $bindVal ) OR \is_string( $bindVal ) ) AND \strlen( (string) $bindVal ) > $longThreshold )
			{
				$sendAsLong[ $i ] = (string) $bindVal;
			}

			$i++;
			switch ( \gettype( $bindVal ) )
			{
				case 'boolean':
				case 'integer':
					$bind->add( 'i', $bindVal );
					break;
					
				case 'double':
					$bind->add( 'd', $bindVal );
					break;
												
				case 'string':
					if( \strlen( $bindVal ) > $longThreshold )
					{
						$bind->add( 'b', NULL );
					}
					else
					{
						$bind->add( 's', $bindVal );
					}
					break;
					
				case 'object':
					if( method_exists( $bindVal, '__toString' ) )
					{
						if( \strlen( $bindVal ) > $longThreshold )
						{
							$bind->add( 'b', NULL );
						}
						else
						{
							$bind->add( 's', (string) $bindVal );
						}
						break;
					}
					// Deliberately no break
					
				case 'NULL':
				case 'array':
				case 'resource':
				case 'unknown type':
				default:
					/* For NULL values, you can't bind, so we adjust the query to actually pass a NULL value */
					$pos = 0;
					for ( $j=0; $j<$i; $j++ )
					{
						$pos = mb_strpos( $query, '?', $pos ) + 1;
					}
					$query = mb_substr( $query, 0, $pos - 1 ) . 'NULL' . mb_substr( $query, $pos );
					$i--;
					break;
			}
		}
				
		/* Log */
		if ( \IPS\QUERY_LOG )
		{
			/* Log */
			$this->log( static::_replaceBinds( $queryForLog, $binds ), ( $read and $this->readWriteSeparation ) ? 'read' : 'write' );	
		}

		/* Return full query */
		if( $this->returnQuery === TRUE )
		{
			$this->returnQuery = FALSE;
			return static::_replaceBinds( $queryForLog, $binds );
		}
		
		/* Add a backtrace to the query so we know where it came from if it causes issues */
		$comment = '??';
		$line = '?';
		foreach( debug_backtrace( FALSE ) as $b )
		{
			if ( isset( $b['line'] ) )
			{
				$line = $b['line'];
			}
			
			if( isset( $b['class'] ) and !\in_array( $b['class'], array( 'IPS\_Db', 'IPS\Db\_Select', 'IPS\Patterns\_ActiveRecord', 'IPS\Patterns\_ActiveRecordIterator', 'IteratorIterator' ) ) )
			{
				$comment = "{$b['class']}::{$b['function']}:{$line}";
				break;
			}
		}
		$_query = $query;
		$query = "/*" . \IPS\Settings::i()->sql_database . "::" . \IPS\Settings::i()->sql_user . "::{$comment}*/ {$query}";

		/* Prepare */
		if ( $read and $this->reader and $this->readWriteSeparation )
		{
			$stmt = $this->reader->prepare( $query );
			if( $stmt === FALSE )
			{
				throw new \IPS\Db\Exception( $this->reader->error, $this->reader->errno, NULL, $queryForLog, $binds );
			}
		}
		else
		{
			$stmt = parent::prepare( $query );

			if( $stmt === FALSE )
			{
				throw new \IPS\Db\Exception( $this->error, $this->errno, NULL, $queryForLog, $binds );
			}
		}

		/* Bind values */
		if( $bind->haveBinds() === TRUE )
		{
			$stmt->bind_param( ...$bind->get() );

			if( \count( $sendAsLong ) )
			{
				foreach( $sendAsLong as $index => $data )
				{
					$chunks = str_split( $data, $longThreshold - 1 );

					foreach( $chunks as $chunk )
					{
						$stmt->send_long_data( $index, $chunk );
					}
				}
			}
		}
		
		/* Execute */
		$stmt->execute();
		
		/* Handle errors */
		$count = 1;
		while ( $stmt->error )
		{
			/* If we hit a deadlock, try again upto 3 times total */
			if ( $stmt->errno === 1213 and $count <= 3 )
			{
				usleep(250);
				$stmt->execute();
				$count++;
			}
						
			/* Throw error */
			else
			{
				throw new \IPS\Db\Exception( $stmt->error, $stmt->errno, NULL, $queryForLog, $binds );
			}
		}
				
		/* Store result */
		$stmt->store_result();
						
		/* Return a Statement object */
		return $stmt;
	}

	/**
	 * Log
	 *
	 * @param	string	$logQuery	Query to log
	 * @param	string	$server		Will be "read" or "write" to indicate which server was (or would be) used in read/write separation
	 * @return	void
	 */
	protected function log( $logQuery, $server=NULL )
	{		
		$this->log[] = array(
			'query'		=> $logQuery,
			'server'	=> $server,
			'backtrace'	=> var_export( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), TRUE ),
			'extra'		=> NULL,
		);
	}
		
	/**
	 * Build SELECT statement
	 *
	 * @param	array|string		$columns	The columns (as an array) to select or an expression
	 * @param	array|string		$table		The table to select from. Either (string) table_name or (array) ( name, alias ) or \IPS\Db\Select object
	 * @param	array|string|NULL	$where		WHERE clause - see \IPS\Db::compileWhereClause() for details
	 * @param	string|NULL			$order		ORDER BY clause
	 * @param	array|int			$limit		Rows to fetch or array( offset, limit )
	 * @param	string|NULL|array	$group		Column(s) to GROUP BY
	 * @param	array|string|NULL	$having		HAVING clause (same format as WHERE clause)
	 * @param	int					$flags		Bitwise flags
	 *	@li	\IPS\Db::SELECT_DISTINCT				Will use SELECT DISTINCT
	 *	@li	\IPS\Db::SELECT_MULTIDIMENSIONAL_JOINS	Will return the result as a multidimensional array, with each joined table separately
	 *	@li	\IPS\Db::SELECT_FROM_WRITE_SERVER		Will send the query to the write server (if read/write separation is enabled)
	 * @return	\IPS\Db\Select
	 *
	 */
	public function select( $columns, $table, $where=NULL, $order=NULL, $limit=NULL, $group=NULL, $having=NULL, $flags=0 )
	{
		$binds = array();
		$query = 'SELECT ';
		
		/* Flags */
		if ( $flags & static::SELECT_DISTINCT )
		{
			$query .= 'DISTINCT ';
		}

		/* Columns */
		if ( \is_string( $columns ) )
		{
			$query .= $columns;
		}
		else
		{
			$query .= implode( ', ', array_map( function( $col )
			{
				return ( mb_strpos( $col, '`' ) === FALSE ) ? ( '`' . $col . '`' ) : $col;
			}, $columns ) );
		}
		
		/* Tables */
		if ( $table instanceof \IPS\Db\Select )
		{
			$tableQuery = $table->query;
			$binds = $table->binds;
			preg_match( '/FROM `(.+?)`( AS `(.+?)`)?/', $tableQuery, $matches );
			$query .= isset( $matches[3] ) ? " FROM ( {$tableQuery} ) AS `{$matches[3]}`" : ( " FROM ( {$tableQuery} ) AS `" . md5(mt_rand()) . '`' );			
		}
		elseif ( \is_array( $table ) )
		{
			if ( \is_array( $table[0] ) and \count( $table[0] ) )
			{
				$tables = array();
				foreach( $table as $item )
				{
					$tables[] = " `{$this->prefix}{$item[0]}` AS `{$item[1]}`";
				}
				
				$query .= " FROM " . implode( ', ', $tables );
			}
			else
			{
				$tableName = ( $table[0] instanceof \IPS\Db\Select ) ? '(' . $table[0] . ')' : '`' . $this->prefix . $table[0] . '`';
				$query .= " FROM {$tableName} AS `{$table[1]}`";
			}
		}
		else
		{
			$query .= $this->prefix ? " FROM `{$this->prefix}{$table}` AS `{$table}`" : " FROM `{$table}`";
		}
		
		/* WHERE */
		if ( $where )
		{
			$where = $this->compileWhereClause( $where );
			$query .= ' WHERE ' . $where['clause'];
			$binds = array_merge( $binds, $where['binds'] );
		}
		
		/* Group? */
		if( $group )
		{
			if ( \is_array( $group ) )
			{
				$query .= " GROUP BY " . implode( ',', array_map( function( $val )
				{
					if( mb_strpos( $val, '.' ) !== FALSE )
					{
						$pieces = explode( '.', $val );

						foreach( $pieces as $k => $piece )
						{
							$pieces[ $k ] = '`' . $piece . '`';
						}

						return implode( '.', $pieces );
					}

					return "`{$val}`";
				}, $group ) );
			}
			else
			{
				if( mb_strpos( $group, '.' ) !== FALSE )
				{
					$pieces = explode( '.', $group );

					foreach( $pieces as $k => $piece )
					{
						$pieces[ $k ] = '`' . $piece . '`';
					}

					$group = implode( '.', $pieces );
				}
				else
				{
					$group = "`{$group}`";
				}

				$query .= " GROUP BY {$group}";
			}
		}
				
		/* Having? */
		if( $having )
		{
			$having = $this->compileWhereClause( $having );
			$query .= ' HAVING ' . $having['clause'];
			$binds = array_merge( $binds, $having['binds'] );
		}
		
		/* Order? */
		if( $order )
		{
			$query .= ' ORDER BY ' . $order;
		}
		
		/* Limit */
		if( $limit )
		{
			$query .= $this->compileLimitClause( $limit );
		}
		
		/* Return */
		return new \IPS\Db\Select( $query, $binds, $this, $flags & static::SELECT_MULTIDIMENSIONAL_JOINS, $flags & static::SELECT_FROM_WRITE_SERVER );
	}
	
	/**
	 * Build UNION statement
	 *
	 * @param	array				$selects		Array of \IPS\Db\Select objects
	 * @param	string|NULL			$order			ORDER BY clause
	 * @param	array|int			$limit			Rows to fetch or array( offset, limit )
	 * @param	string|null			$group			Group by clause
	 * @param	bool				$unionAll		TRUE to perform a UNION ALL, FALSE (default) to perform a regular UNION
	 * @param	int					$flags			Bitwise flags
	 * @param	array|string|NULL	$where			WHERE clause (see example)
	 * @param	string				$querySelect	Custom select for the outer query
	 * @return	\IPS\Db|Select
	 */
	public function union( $selects, $order, $limit, $group=NULL, $unionAll=FALSE, $flags=0, $where=NULL, $querySelect='*' )
	{
		/* Combine selects */
		$query = array();
		$binds = array();
		foreach ( $selects as $s )
		{
			$query[] = '( ' . $s->query . ' )';
			$binds = array_merge( $binds, $s->binds );
		}

		$union	= $unionAll ? "UNION ALL" : "UNION";
		
		$query = "SELECT " . $querySelect . " FROM( " . implode( ' ' . $union . ' ', $query ) . ") derivedTable ";
		
		/* WHERE */
		if ( $where )
		{
			$where = $this->compileWhereClause( $where );
			$query .= ' WHERE ' . $where['clause'];
			$binds = array_merge( $binds, $where['binds'] );
		}

		/* Group */
		if( $group )
		{
			$query.= " GROUP BY " . $group;
		}

		/* Order? */
		if( $order )
		{
			$query .= ' ORDER BY ' . $order;
		}
		
		/* Limit */
		if( $limit )
		{
			$query .= $this->compileLimitClause( $limit );
		}
		
		/* Return */
		$return =  new \IPS\Db\Select( $query, $binds, $this );
		$return->isUnion = TRUE;
		return $return;
	}
	
	/**
	 * Run INSERT statement and return insert ID
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/insert.html'>INSERT Syntax</a>
	 * @param	string					$table			Table name
	 * @param	array|\IPS\Db\Select	$set			Values to insert or array of values to set for multiple rows (NB, if providing multiple rows, they MUST all contain the same columns) or a statement to do INSERT INTO SELECT FROM
	 * @param	bool					$odkUpdate		Append an ON DUPLICATE KEY UPDATE clause to the query.  Similar to the replace() method but updates if a record is found, instead of delete and reinsert.
	 * @param	bool					$ignoreErrors	Ignore errors?
	 * @see		replace
	 * @return	int
	 * @throws	\IPS\Db\Exception
	 */
	public function insert( $table, $set, $odkUpdate=FALSE, $ignoreErrors=FALSE )
	{
		/* Build */
		$query = $this->_buildInsertQuery( ( $ignoreErrors ? 'INSERT IGNORE' : 'INSERT' ), $table, $set );
		
		/* Add "ON DUPLICATE KEY UPDATE" */
		if( $odkUpdate )
		{
			$query[0]	.= " ON DUPLICATE KEY UPDATE " . implode( ', ', array_map( function( $val ){ return "{$val}=VALUES({$val})"; }, $query[2] ) );
		}
		
		/* Run */
		$return = $this->returnQuery;

		$stmt = $this->preparedQuery( $query[0], $query[1] );

		if( $return === TRUE )
		{
			return $stmt;
		}
		
		$insertId = $stmt->insert_id;
		
		$stmt->close();

		return $insertId;
	}
	
	/**
	 * Run REPLACE statament and return number of affected rows OR inserted ID
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/replace.html'>REPLACE Syntax</a>
	 * @param	string	$table			Table name
	 * @param	array	$set			Values to insert
	 * @param	bool	$getInsertId	If TRUE, returns the insert ID rather than the number of affected rows
	 * @return	int
	 * @throws	\IPS\Db\Exception
	 */
	public function replace( $table, $set, $getInsertId=false )
	{
		/* Build */
		$query = $this->_buildInsertQuery( 'REPLACE', $table, $set );

		$return = $this->returnQuery;

		$stmt = $this->preparedQuery( $query[0], $query[1] );

		if( $return === TRUE )
		{
			return $stmt;
		}

		$return = $getInsertId ? $stmt->insert_id : $stmt->affected_rows;
		
		$stmt->close();
		
		return $return;
	}

	/**
	 * Escapes special characters in a string for use in an SQL statement, taking into account the current charset of the connection
	 *
	 * @see		https://php.net/manual/en/mysqli.real-escape-string.php
	 * @param	string	$escapestr	The string to be escaped.
	 * @return	string	An escaped string.
	 */
	public function real_escape_string( $escapestr )
	{
		/* Make sure we're connected */
		$this->checkConnection( TRUE );

		return $this->connectionDetails['readDatabase'] ? $this->reader->real_escape_string( $escapestr ) : parent::real_escape_string( $escapestr );
	}

	/**
	 * Escapes special characters in a string for use in an SQL statement, taking into account the current charset of the connection
	 *
	 * @see		https://php.net/manual/en/mysqli.real-escape-string.php
	 * @param	string	$escapestr	The string to be escaped.
	 * @return	string	An escaped string.
	 */
	public function escape_string( $escapestr )
	{
		/* Make sure we're connected */
		$this->checkConnection( TRUE );

		return $this->connectionDetails['readDatabase'] ? $this->reader->escape_string( $escapestr ) : parent::escape_string( $escapestr );
	}

	/**
	 * Build the replace or insert into query
	 *
	 * @param	string					$type	INSERT|REPLACE
	 * @param	string					$table	Table name
	 * @param	array|\IPS\Db\Select	$set	Values to insert or array of values to set for multiple rows (NB, if providing multiple rows, they MUST all contain the same columns) or a statement to do INSERT INTO SELECT FROM
	 * @return	array	0 => query, 1 => binds, 2 => columns
	 */
	protected function _buildInsertQuery( $type, $table, $set )
	{
		$columns	= NULL;

		/* Is a statement? */
		if ( $set instanceof \IPS\Db\Select )
		{
			$query = "{$type} INTO `{$this->prefix}{$table}` " . $set->query;
			$binds = $set->binds;
		}
		elseif ( \is_array( $set ) and \count( $set ) == 2 and isset( $set[1] ) and $set[1] instanceof \IPS\Db\Select )
		{
			$query = "{$type} INTO `{$this->prefix}{$table}` (" . $set[0] . ") " . $set[1]->query;
			$binds = $set[1]->binds;
		}
		else
		{
			/* Is this just one row? */
			foreach ( $set as $k => $v )
			{
				if ( !\is_array( $v ) )
				{
					$set = array( $set );
				}
				break;
			}
			
			/* Compile */
			$columns = NULL;
			$values = array();
			$binds = array();
			if ( \count( $set ) )
			{
				foreach ( $set as $row )
				{
					if ( $columns === NULL )
					{
						 $columns = array_map( function( $val ){ return "`{$val}`"; }, array_keys( $row ) );
					}
					
					$binds = array_merge( $binds, array_values( $row ) );
					$values[] = '( ' . implode( ', ', array_fill( 0, \count( $columns ), '?' ) ) . ' )';
				}
			}
			else
			{
				$columns = array();
				$values = array( '()' );
			}
			
			/* Construct query */
			$query = "{$type} INTO `{$this->prefix}{$table}` ( " . implode( ', ', $columns ) . ' ) VALUES ' . implode( ', ', $values );
		}

		return array( 0 => $query, 1 => $binds, 2 => $columns );
	}
	
	/**
	 * Run UPDATE statement and return number of affected rows
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/update.html'>UPDATE Syntax</a>
	 * @param	string|array	$table		Table Name, or array( Table Name => Identifier )
	 * @param	string|array	$set		Values to set (keys should be the table columns) or pre-formatted SET clause or \IPS\Db\Select object
	 * @param	mixed			$where		WHERE clause (see \IPS\Db::compileWhereClause for details)
	 * @param	array			$joins		Tables to join
	 * @param	int|array|null	$limit		LIMIT clause (see \IPS\Db::select for details)
	 * @param	int				$flags		Bitwise flags
	 *	@li	\IPS\Db::LOW_PRIORITY			Will use LOW_PRIORITY
	 *	@li	\IPS\Db::IGNORE					Will use IGNORE
	 * @return	int
	 * @throws	\IPS\Db\Exception
	 */
	public function update( $table, $set, $where='', $joins=array(), $limit=NULL, $flags=0 )
	{
		$binds = array();
		
		/* Work out table */
		$table = \is_array( $table ) ? "`{$this->prefix}{$table[0]}` `{$this->prefix}{$table[1]}`" : "`{$this->prefix}{$table}` `{$table}`";

		/* Work out joins */
		$_joins	= array();
		
		foreach ( $joins as $join )
		{
			$type = ( isset( $join['type'] ) and \in_array( mb_strtoupper( $join['type'] ), array( 'LEFT', 'INNER', 'RIGHT' ) ) ) ? mb_strtoupper( $join['type'] ) : 'LEFT';
			$_table = \is_array( $join['from'] ) ? "`{$this->prefix}{$join['from'][0]}` {$this->prefix}{$join['from'][1]}" : "`{$this->prefix}{$join['from']}` {$join['from']}";

			$on = $this->compileWhereClause( $join['where'] );
			$binds = array_merge( $binds, $on['binds'] );

			$_joins[] = "{$type} JOIN {$_table} ON {$on['clause']}";
		}
		$joins = empty( $_joins ) ? '' : ( ' ' . implode( "\n", $_joins ) );
		
		/* Work out SET clause */
		if ( \is_array( $set ) )
		{
			$_set = array();
			foreach ( $set as $k => $v )
			{
				$_set[] = "`{$k}`=" . ( \is_object( $v ) ? '(?)' : '?' );
				$binds[] = $v;
			}
			$set = implode( ',', $_set );
		}
				
		/* Compile where clause */
		if ( $where !== '' )
		{
			$_where = $this->compileWhereClause( $where );
			$where = 'WHERE ' . $_where['clause'];
			$binds = array_merge( $binds, $_where['binds'] );
		}
		
		/* Build query */
		$query = 'UPDATE ';
		if ( $flags & static::LOW_PRIORITY )
		{
			$query .= 'LOW_PRIORITY ';
		}
		if ( $flags & static::IGNORE )
		{
			$query .= 'IGNORE ';
		}
		$query .= "{$table} {$joins} SET {$set} {$where} ";
		
		/* Limit */
		if( $limit !== NULL )
		{
			$query .= $this->compileLimitClause( $limit );
		}
				
		/* Run it */
		$return = $this->returnQuery;

		$stmt = $this->preparedQuery( $query, $binds );

		if( $return === TRUE )
		{
			return $stmt;
		}

		$return = $stmt->affected_rows;
		
		$stmt->close();
		
		return $return;
	}
	
	/**
	 * Run DELETE statement and return number of affected rows
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/delete.html'>DELETE Syntax</a>
	 * @param	string|array							$table				Table Name or array of table names
	 * @param	string|array|\IPS\Db\Select|null		$where				WHERE clause (see \IPS\Db::compileWhereClause for details)
	 * @param	string|null							$order				ORDER BY clause
	 * @param	int|array|null						$limit				LIMIT clause (see \IPS\Db::select for details)
	 * @param	string|array|null						$statementColumn		If \IPS\Db\Select is passed, this is either the name of the column that results are being loaded from (and we will use a WHERE clause like WHERE {statementColumn} IN ({select-query})) or an array to map the outer table column to the inner table column (and we will JOIN the inner table and use an ON clause like ON {statementColumn[0]} IN ({statementColumn[1]}))
	 * @param	string								$deleteWhat			What to delete (used when executing a multitable delete)
	 * @param	bool									$statementReverse	If \IPS\Db\Select is passed, TRUE will use NOT IN() rather than IN().
	 * @return	\IPS\Db\Select
	 * @throws	\IPS\Db\Exception
	 */
	public function delete( $table, $where=NULL, $order=NULL, $limit=NULL, $statementColumn=NULL, $deleteWhat='', $statementReverse=FALSE )
	{
		/* Clear any size cache if it exists */
		if( \is_array( $table ) )
		{
			foreach( $table as $_table )
			{
				if( isset( $this->cachedTableData[ $_table ] ) )
				{
					unset( $this->cachedTableData[ $_table ] );
				}
			}
		}
		else
		{
			if( isset( $this->cachedTableData[ $table ] ) )
			{
				unset( $this->cachedTableData[ $table ] );
			}
		}

		/* TRUNCATE is faster, so use that if appropriate */
		if ( $where === NULL and $limit === NULL and \is_string( $table ) )
		{
			$return = $this->returnQuery;

			$stmt = $this->preparedQuery( "TRUNCATE `{$this->prefix}{$table}`", array() );

			if( $return === TRUE )
			{
				return $stmt;
			}
			
			$return = $stmt->affected_rows;
			
			$stmt->close();

			return $return;
		}
		
		/* Start building the query */
		$query = "DELETE ";

		if( $deleteWhat )
		{
			$query .= $deleteWhat . ' ';
		}

		$query .= "FROM ";

		if( \is_string( $table ) )
		{
			$query .= "`{$this->prefix}{$table}`";
		}
		else
		{
			$tables = array();

			foreach( $table as $alias => $_table )
			{
				$alias = \is_string( $alias ) ? $alias : $_table;

				$tables[] = "`{$this->prefix}{$_table}` AS `{$alias}`";
			}

			$query .= implode( ', ', $tables );
		}

		/* Is a statement? */
		if ( $where instanceof \IPS\Db\Select )
		{
			if( \is_string( $statementColumn ) )
			{
				$query .= ' WHERE ' . $statementColumn . ' ' . ( $statementReverse ? 'NOT ' : '' ) . 'IN(' . $where->query . ')';
			}
			else
			{
				$query .= ' JOIN (' . $where->query . ') d ON ' . $statementColumn[0] . ' ' . ( $statementReverse ? 'NOT ' : '' ) . 'IN(d.' . $statementColumn[1] . ')';
			}

			$binds = $where->binds;
		}

		/* Add where clause */
		else
		{
			$binds = array();
			if ( $where !== NULL )
			{
				$_where = $this->compileWhereClause( $where );
				$query .= ' WHERE ' . $_where['clause'];
				$binds = $_where['binds'];
			}
		}
		
		/* Order? */
		if( $order !== NULL )
		{
			$query .= ' ORDER BY ' . $order;
		}
		
		/* Limit */
		if( $limit !== NULL )
		{
			$query .= $this->compileLimitClause( $limit );
		}

		/* Run it */
		$return = $this->returnQuery;

		$stmt = $this->preparedQuery( $query, $binds );

		if( $return === TRUE )
		{
			return $stmt;
		}

		$return = $stmt->affected_rows;
		
		$stmt->close();
		
		return $return;
	}
			
	/**
	 * Compile WHERE clause
	 *
	 * @code
	 	// Single clause
	 	"foo IS NOT NULL"
	 	// Single clause with bound values (always bind values to ensure they are properly escaped)
	 	array( 'foo=?', 'fooValue' )
	 	array( 'foo=? OR bar=?', 'fooValue', 'barValue' )
	 	// Multiple clauses (will be joined with AND) with bound values
	 	array( array( 'foo=?, 'fooValue' ), array( 'bar=?', 'barValue' ) )
	 * @endcode
	 * @param	string|array	$data	See examples
	 * @return	array	Array containing the WHERE clause and the values to be bound - array( 'clause' => '1=1', 'binds' => array() )
	 */
	public function compileWhereClause( $data )
	{
		$return = array( 'clause' => '1=1', 'binds' => array() );
		
		if( \is_string( $data ) )
		{
			$return['clause'] = $data;
		}
		elseif ( \is_array( $data ) and ! empty( $data ) )
		{
			if ( \is_string( $data[0] ) )
			{
				$data = array( $data );
			}
		
			$clauses = array();
			foreach ( $data as $bit )
			{
				if( !\is_array( $bit ) )
				{
					$clauses[] = $bit;
				}
				else
				{
					$clause = array_shift( $bit );
					
					$binds = $bit;
					$i = 0;
					foreach ( $binds as $k => $v )
					{
						$i++;
						if ( $v === NULL )
						{
							$pos = 0;
							for ( $j=0; $j<$i; $j++ )
							{
								$pos = mb_strpos( $clause, '?', $pos ) + 1;
							}

							if( mb_substr( $clause, $pos - 3, 3 ) == '!=?' )
							{
								$clause = mb_substr( $clause, 0, $pos - 3 ) . ' IS NOT NULL' . mb_substr( $clause, $pos );
							}
							else
							{
								$clause = mb_substr( $clause, 0, $pos - 2 ) . ' IS NULL' . mb_substr( $clause, $pos );
							}

							$i--;
							unset( $binds[$k] );
						}

					}
							
					$clauses[] = $clause;
					$return['binds'] = array_merge( $return['binds'], $binds );
				}
			}
			
			$return['clause'] = implode( ' AND ', $clauses );
		}
		
		return $return;
	}
	
	/**
	 * Compile LIMIT clause
	 *
	 * @param	int|array	$data	Rows to fetch or array( offset, limit )
	 * @return	string
	 */
	public function compileLimitClause( $data )
	{
		$limit = NULL;
		if( \is_array( $data ) )
		{
			$offset = \intval( $data[0] );
			$limit  = \intval( $data[1] );
		}
		else
		{
			$offset = \intval( $data );
		}

		if( $limit !== NULL )
		{
			return " LIMIT {$offset},{$limit}";
		}
		else
		{
			return " LIMIT {$offset}";
		}
	}
	
	/**
	 * Compile column definition
	 *
	 * @code
	 	\IPS\Db::i()->compileColumnDefinition( array(
	 		'name'			=> 'column_name',		// Column name
	 		'type'			=> 'VARCHAR',			// Data type (do not specify length, etc. here)
	 		'length'		=> 255,					// Length. May be required or optional depending on data type.
	 		'decimals'		=> 2,					// Decimals. May be required or optional depending on data type.
	 		'values'		=> array( 0, 1 ),		// Acceptable values. Required for ENUM and SET data types.
	 		'allow_null'	=> FALSE,				// (Optional) Specifies whether or not NULL vavlues are allowed. Defaults to TRUE.
	 		'default'		=> 'Default Value',		// (Optional) Default value
	 		'comment'		=> 'Column Comment',	// (Optional) Column comment
	 		'unsigned'		=> TRUE,				// (Optional) Will specify UNSIGNED for numeric types. Defaults to FALSE.
	 		'auto_increment'=> TRUE,				// (Optional) Will specify auto_increment. Defaults to FALSE.
	 		'primary'		=> TRUE,				// (Optional) Will specify PRIMARY KEY. Defaults to FALSE.
	 		'unqiue'		=> TRUE,				// (Optional) Will specify UNIQUE. Defaults to FALSE.
	 		'key'			=> TRUE,				// (Optional) Will specify KEY. Defaults to FALSE.
	 	) );
	 * @endcode
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/create-table.html'>MySQL CREATE TABLE syntax</a>
	 * @param	array	$data	Column Data (see \IPS\Db::createTable for details)
	 * @return	string
	 */
	public function compileColumnDefinition( $data )
	{
		/* Specify name and type */
		$definition = "`{$data['name']}` " . mb_strtoupper( $data['type'] ) . ' ';
		
		/* Some types specify length */
		if(
			\in_array( mb_strtoupper( $data['type'] ), array( 'VARCHAR', 'VARBINARY' ) )
			or
			(
				isset( $data['length'] ) and $data['length']
				and
				\in_array( mb_strtoupper( $data['type'] ), array( 'BIT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'CHAR', 'BINARY' ) )
			)
		) {
			$definition .= "({$data['length']}";
			
			/* And some of those specify decimals (which may or may not be optional) */
			if( \in_array( mb_strtoupper( $data['type'] ), array( 'DECIMAL', 'NUMERIC' ) ) and isset( $data['decimals'] ) )
			{
				$definition .= ',' . $data['decimals'];
			}
			
			$definition .= ') ';
		}
		
		/* Numeric types can be UNSIGNED */
		if( \in_array( mb_strtoupper( $data['type'] ), array( 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'NUMERIC' ) ) )
		{
			if( isset( $data['unsigned'] ) and $data['unsigned'] === TRUE )
			{
				$definition .= 'UNSIGNED ';
			}
		}
		
		/* ENUM and SETs have values */
		if( \in_array( mb_strtoupper( $data['type'] ), array( 'ENUM', 'SET' ) ) )
		{
			$values = array();
			foreach ( $data['values'] as $v )
			{
				$values[] = "'{$this->escape_string( $v )}'";
			}
			
			$definition .= '(' . implode( ',', $values ) . ') ';
		}
		
		/* Text types specify a character set and collation */
		if( \in_array( mb_strtoupper( $data['type'] ), array( 'CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'ENUM', 'SET' ) ) )
		{
			$definition .= "CHARACTER SET {$this->charset} COLLATE {$this->collation} ";
		}
		
		/* NULL? */
		if( isset( $data['allow_null'] ) and $data['allow_null'] === FALSE )
		{
			$definition .= 'NOT NULL ';
		}
		else
		{
			$definition .= 'NULL ';
		}
		
		/* auto_increment? */
		if( isset( $data['auto_increment'] ) and $data['auto_increment'] === TRUE )
		{
			$definition .= 'AUTO_INCREMENT ';
		}
		else
		{
			/* Default value */
			if( isset( $data['default'] ) and !\in_array( mb_strtoupper( $data['type'] ), array( 'TINYTEXT', 'TEXT', 'MEDIUMTEXT', 'LONGTEXT', 'BLOB', 'MEDIUMBLOB', 'BIGBLOB', 'LONGBLOB' ) ) )
			{
				if( $data['type'] == 'BIT' )
				{
					$definition .= "DEFAULT {$data['default']} ";
				}
				else
				{
					$defaultValue = \in_array( mb_strtoupper( $data['type'] ), array( 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE', 'FLOAT', 'DECIMAL', 'NUMERIC' ) ) ? \floatval( $data['default'] ) : ( ! \in_array( $data['default'], array( 'CURRENT_TIMESTAMP', 'BIT' ) ) ? '\'' . $this->escape_string( $data['default'] ) . '\'' : $data['default'] );
					$definition .= "DEFAULT {$defaultValue} ";
				}
			}
		}
		
		/* Index? */
		if( isset( $data['primary'] ) )
		{
			$definition .= 'PRIMARY KEY ';
		}
		elseif( isset( $data['unique'] ) )
		{
			$definition .= 'UNIQUE ';
		}
		if( isset( $data['key'] ) )
		{
			$definition .= 'KEY ';
		}
		
		/* Comment */
		if( isset( $data['comment'] ) and ! empty( $data['comment'] ) )
		{
			$definition .= "COMMENT '{$this->escape_string( $data['comment'] )}'";
		}
									
		/* Return */
		return $definition;
	}
	
	/**
	 * Compile index definition
	 *
	 * @code
	 	\IPS\Db::i()->compileIndexDefinition( array(
	 		'type'		=> 'key',				// "primary", "unique", "fulltext" or "key"
	 		'name'		=> 'index_name',		// Index name. Not required if type is "primary"
	 		'length'	=> 200,					// Index length (used when taking part of a text field, for example)
	 		'columns'	=> array( 'column' )	// Columns to be in the index
	 	) );
	 * @endcode
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/create-index.html'>MySQL CREATE INDEX syntax</a>
	 * @see		createTable
	 * @param	array	$data	Index Data (see \IPS\Db::createTable for details)
	 * @return	string
	 */
	public function compileIndexDefinition( $data )
	{
		$definition = '';
		
		/* Specify type */
		switch ( \strtolower( $data['type'] ) )
		{
			case 'primary':
				$definition .= 'PRIMARY KEY ';
				break;
				
			case 'unique':
				$definition .= "UNIQUE KEY `{$data['name']}` ";
				break;
				
			case 'fulltext':
				$definition .= "FULLTEXT KEY `{$data['name']}` ";
				break;
				
			default:
				$definition .= "KEY `{$data['name']}` ";
				break;
		}
		
		/* Specify columns */
		$definition .= '(' . implode( ',', array_map( function ( $val, $len )
		{
			return ( ! empty( $len ) ) ? "`{$val}`({$len})" : "`{$val}`";
		}, $data['columns'], ( ( isset( $data['length'] ) AND \is_array( $data['length'] ) ) ? $data['length'] : array_fill( 0, \count( $data['columns'] ), null ) ) ) ) . ')';
		
		/* Return */
		return $definition;
	}
	
	/**
	 * Does table exist?
	 *
	 * @param	string	$name	Table Name
	 * @return	bool
	 */
	public function checkForTable( $name )
	{
		return ( $this->forceQuery( "SHOW TABLES LIKE '". $this->escape_string( "{$this->prefix}{$name}" ) . "'" )->num_rows > 0 );
	}

	/**
	 * Does column exist?
	 *
	 * @param	string	$name	Table Name
	 * @param	string	$column	Column Name
	 * @return	bool
	 */
	public function checkForColumn( $name, $column )
	{
		return ( $this->forceQuery( "SHOW COLUMNS FROM `". $this->escape_string( "{$this->prefix}{$name}" ) . "` LIKE '". $this->escape_string( $column ) . "'" )->num_rows > 0 );
	}

	/**
	 * Does index exist?
	 *
	 * @param	string	$name	Table Name
	 * @param	string	$index	Index Name
	 * @return	bool
	 */
	public function checkForIndex( $name, $index )
	{
		return ( $this->forceQuery( "SHOW INDEXES FROM `". $this->escape_string( "{$this->prefix}{$name}" ) . "` WHERE Key_name LIKE '". $this->escape_string( $index ) . "'" )->num_rows > 0 );
	}
	
	/**
	 * Create Table
	 *
	 * @code
	 	\IPS\Db::createTable( array(
	 		'name'			=> 'table_name',	// Table name
	 		'columns'		=> array( ... ),	// Column data - see \IPS\Db::compileColumnDefinition for details
	 		'indexes'		=> array( ... ),	// (Optional) Index data - see \IPS\Db::compileIndexDefinition for details
	 		'comment'		=> '...',			// (Optional) Table comment
	 		'engine'		=> 'MEMORY',		// (Optional) Engine to use - will default to not specifying one, unless a FULLTEXT index is specified, in which case MyISAM is forced
	 		'temporary'		=> TRUE,			// (Optional) Will sepcify CREATE TEMPORARY TABLE - defaults to FALSE
	 		'if_not_exists'	=> TRUE,			// (Optional) Will sepcify CREATE TABLE name IF NOT EXISTS - defaults to FALSE
	 	) );
	 * @endcode
	 * @param	array	$data	Table Definition (see code sample for details)
	 * @throws	\IPS\Db\Exception
	 * @return	void|string
	 */
	public function createTable( $data )
	{
		/* Make sure we're connected */
		$this->checkConnection( TRUE );

		return $this->query( $this->_createTableQuery( $data ) );
	}
	
	/**
	 * Create copy of table structure
	 *
	 * @param	string	$table			The table name
	 * @param	string	$newTableName	Name of table to create
	 * @throws	\IPS\Db\Exception
	 * @return	void|string
	 */
	public function duplicateTableStructure( $table, $newTableName )
	{
		/* Make sure we're connected */
		$this->checkConnection( TRUE );

		return $this->query( "CREATE TABLE `{$this->prefix}{$newTableName}` LIKE `{$this->prefix}{$table}`" );
	}
	
	/**
	 * Create Table Query
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/create-table.html'>MySQL CREATE TABLE syntax</a>
	 * @see		compileColumnDefinition
	 * @see		compileIndexDefinition
	 * @param	array	$data	Table Definition (see code sample for details)
	 * @return	string
	 */
	public function _createTableQuery( $data )
	{
		$data = $this->updateDefinitionIndexLengths( $data );
		$mysqlVersion = \IPS\Db::i()->server_info;
		
		/* Start with a basic CREATE TABLE */
		$query = 'CREATE ';
		if( isset( $data['temporary'] ) and $data['temporary'] === TRUE )
		{
			$query.= 'TEMPORARY ';
		}
		$query .= 'TABLE ';
		if( isset( $data['if_not_exists'] ) and $data['if_not_exists'] === TRUE )
		{
			$query.= 'IF NOT EXISTS ';
		}
				
		/* Add in our create definition */
		$query .= "`{$this->prefix}{$data['name']}` (\n\t";
		$createDefinitons = array();
		foreach ( $data['columns'] as $field )
		{
			$createDefinitons[] = $this->compileColumnDefinition( $field );
		}
		if( isset( $data['indexes'] ) )
		{
			foreach ( $data['indexes'] as $index )
			{
				if( $index['type'] === 'fulltext' )
				{
					/* If this is a fulltext index, set engine to myisam but only if engine is something besides innodb or myisam OR the mysql version is less than 5.6 - in
						this case we assume the default engine is most likely either myisam or innodb */
					if( !$this->_innoDbSupportsFulltextIndexes() OR ( isset( $data['engine'] ) AND !\in_array( mb_strtoupper( $data['engine'] ), array( 'INNODB', 'MYISAM' ) ) ) )
					{
						$data['engine'] = 'MYISAM';
					}
				}

				$createDefinitons[] = $this->compileIndexDefinition( $index );
			}
		}
		$query .= implode( ",\n\t", $createDefinitons );
		$query .= "\n)\n";
		
		/* Specifying a particular engine? */
		if( isset( $data['engine'] ) and $data['engine'] )
		{
			$query .= "ENGINE {$data['engine']} ";
		}
		
		/* Specify UTF8 */
		$query .= "CHARACTER SET {$this->charset} COLLATE {$this->collation} ";
		
		/* Add comment */
		if( isset( $data['comment'] ) )
		{
			$query .= "COMMENT '{$this->escape_string( $data['comment'] )}'";
		}
		
		/* Return */
		return $query;
	}
	
	/**
	 * Rename table
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/rename-table.html'>Rename Table</a>
	 * @param	string	$oldName	The current table name
	 * @param	string	$newName	The new name
	 * @return	void
	 * @see		<a href='http://stackoverflow.com/questions/12856783/best-practice-with-mysql-innodb-to-rename-huge-table-when-table-with-same-name-a'>Renaming huge InnoDB tables</a>
	 * @see		<a href='http://www.percona.com/blog/2011/02/03/performance-problem-with-innodb-and-drop-table/'>Performance problem dropping huge InnoDB tables</a>
	 * @note	A race condition can occur sometimes with InnoDB + innodb_file_per_table so we can't drop then rename...see above links
	 */
	public function renameTable( $oldName, $newName )
	{
		/* Find out if the table we are renaming *to* already exists */
		$cleanUp	= FALSE;
		$query		= "`{$this->prefix}{$this->escape_string( $oldName )}` TO `{$this->prefix}{$this->escape_string( $newName )}`";

		if( $this->checkForTable( $newName ) )
		{
			$query	= "`{$this->prefix}{$this->escape_string( $newName )}` TO `{$this->prefix}{$this->escape_string( $newName )}_DROP`, " . $query;
			$cleanUp = TRUE;
		}

		$result = $this->query( "RENAME TABLE " . $query );

		if( $cleanUp )
		{
			$this->dropTable( $newName . '_DROP', TRUE );
		}

		return $result;
	}
	
	/**
	 * Alter Table
	 * Can only update the comment and engine
	 * @note This will not examine key lengths and adjust.
	 *
	 * @param	string			$table		Table name
	 * @param	string|null		$comment	Table comment. NULL to not change
	 * @param	string|null		$engine		Engine to use. NULL to not change
	 * @return	void
	 */
	public function alterTable( $table, $comment=NULL, $engine=NULL )
	{
		if ( $comment === NULL and $engine === NULL )
		{
			return;
		}
		
		$query = "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` ";
		if ( $comment !== NULL )
		{
			$query .= "COMMENT='{$this->escape_string( $comment )}' ";
		}
		if ( $engine !== NULL )
		{
			$query .= "ENGINE={$engine}";
		}
				
		return $this->query( $query );
	}

	/**
	 * Find out the default storage engine
	 *
	 * @return	string
	 */
	public function defaultEngine()
	{
		$result = $this->forceQuery( "SHOW ENGINES" );

		while( $engine = $result->fetch_assoc() )
		{
			if( mb_strtoupper( $engine['Support'] ) == 'DEFAULT' )
			{
				return $engine['Engine'];
			}
		}
		
		return $this->_innoDbSupportsFulltextIndexes() ? 'InnoDB' : 'MyISAM';
	}
	
	/**
	 * Is InnoDB supported for fulltext indexes?
	 *
	 * @return	bool
	 */
	protected function _innoDbSupportsFulltextIndexes()
	{
		/* MariaDB supports fulltext for InnoDB on versions higher than 10.0.5 */
		if ( preg_match( '/^(\d*\.\d*(\.\d*)-)?(\d*\.\d*(\.\d*))-MariaDB/', $this->server_info, $matches ) )
		{
			$mariaVersion = $matches[3];
			return version_compare( $mariaVersion, '10.0.5', '>=' );
		}
		
		/* Normal MySQL supports fulltext for InnoDB on versions higher than 5.6 */
		else
		{
			return $this->server_version >= 50600;
		}
	}
	
	/**
	 * Drop table
	 *
	 * @see		<a href='http://dev.mysql.com/doc/refman/5.1/en/drop-table.html'>DROP TABLE Syntax</a>
	 * @param	string|array	$table		Table Name(s)
	 * @param	bool			$ifExists	Adds an "IF EXISTS" clause to the query
	 * @param	bool			$temporary	Table is temporary?
	 * @return	mixed
	 */
	public function dropTable( $table, $ifExists=FALSE, $temporary=FALSE )
	{
		$prefix = $this->prefix;
		
		return $this->query(
			  'DROP '
			. ( $temporary ? 'TEMPORARY ' : '' )
			. 'TABLE '
			. ( $ifExists ? 'IF EXISTS ' :'' )
			. implode( ', ', array_map(
				function( $val ) use ( $prefix )
				{
					return '`' . $prefix . $val . '`';
				},
				( \is_array( $table ) ? $table : array( $table ) )
			) )
		);
	}

	/**
	 * Get database tables
	 *
	 * @param	string|NULL		$prefix		Optional table prefix to filter by
	 * @return	array
	 */
	public function getTables( $prefix=NULL )
	{
		$query	= $this->query( "SHOW TABLES" );
		$tables	= array();

		while ( $row = $query->fetch_assoc() )
		{
			$name = array_pop($row);

			if ( $prefix === NULL OR mb_substr( $name, 0, \strlen( $prefix ) ) === $prefix )
			{
				$tables[] = $name;
			}
		}

		return $tables;
	}
	
	/**
	 * Get the table definition for an existing table
	 *
	 * @see		createTable
	 * @param	string	$table	Table Name
	 * @param	boolean	$columnsOnly	Fetch columns only
	 * @param	boolean	$getCollation	Get column collations
	 * @return	array	Table definition - see IPS\Db::createTable for details
	 * @throws	\OutOfRangeException
	 * @throws	\IPS\Db\Exception
	 */
	public function getTableDefinition( $table, $columnsOnly=FALSE, $getCollation=FALSE )
	{
		/* Set name */
		$definition = array(
			'name'		=> $table,
		);
	
		/* Fetch columns */
		if( !$this->checkForTable( $table ) )
		{
			throw new \OutOfRangeException;
		}
		$query = $this->forceQuery( "SHOW FULL COLUMNS FROM `{$this->prefix}" . $this->escape_string( $table ) . '`' );
		if ( $query->num_rows === 0 )
		{
			throw new \OutOfRangeException;
		}
		while ( $row = $query->fetch_assoc() )
		{
			/* Set basic information */
			$columnDefinition = array(
				'name' => $row['Field'],
				'type'		=> '',
				'length'	=> 0,
				'decimals'	=> NULL,
				'values'	=> array()
			);
			
			if ( $getCollation and isset( $row['Collation'] ) )
			{
				$columnDefinition['collation'] = $row['Collation'];
			}
			
			/* Parse the type */
			if( mb_strpos( $row['Type'], '(' ) !== FALSE )
			{
				/* First, we need to protect the enum options as they may have spaces before splitting */
				preg_match( '/(.+?)\((.+?)\)/', $row['Type'], $matches );
				$options = $matches[2];
				$type = preg_replace( '/(.+?)\((.+?)\)/', "$1(___TEMP___)", $row['Type'] );
				$typeInfo = explode( ' ', $type );
				$typeInfo[0] = str_replace( "___TEMP___", $options, $typeInfo[0] );

				/* Now we match out the options */
				preg_match( '/(.+?)\((.+?)\)/', $typeInfo[0], $matches );
				$columnDefinition['type'] = mb_strtoupper( $matches[1] );
				
				if( $columnDefinition['type'] === 'ENUM' or $columnDefinition['type'] === 'SET' )
				{
					preg_match_all( "/'(.*?)'/", $matches[2], $enum );
					$columnDefinition['values'] = $enum[1];
				}
				else
				{						
					$lengthInfo = explode( ',', $matches[2] );
					$columnDefinition['length'] = \intval( $lengthInfo[0] );
					if( isset( $lengthInfo[1] ) )
					{
						$columnDefinition['decimals'] = \intval( $lengthInfo[1] );
					}
				}
			}
			else
			{
				$typeInfo = explode( ' ', $row['Type'] );

				$columnDefinition['type'] = mb_strtoupper( $typeInfo[0] );
				$columnDefinition['length'] = 0;
			}
			
			/* unsigned? */
			$columnDefinition['unsigned'] = \in_array( 'unsigned', $typeInfo );

			/* Allow NULL? */
			$columnDefinition['allow_null'] = ( $row['Null'] === 'YES' );
						
			/* Default value */
			$columnDefinition['default'] = $row['Default'];
			
			/* auto_increment */
			$columnDefinition['auto_increment'] = mb_strpos( $row['Extra'], 'auto_increment' ) !== FALSE;
			
			/* Comment */
			$columnDefinition['comment'] = $row['Comment'] ?: '';
			
			/* Add it in the defintion */
			ksort( $columnDefinition );
			$definition['columns'][ $columnDefinition['name'] ] = $columnDefinition;
		}
		
		if( !$columnsOnly )
		{
			/* Fetch indexes */
			$indexes = array();
			$query = $this->forceQuery( "SHOW INDEXES FROM `{$this->prefix}{$table}`" );
			while ( $row = $query->fetch_assoc() )
			{
				$length = ( isset( $row['Sub_part'] ) AND ! empty( $row['Sub_part'] ) ) ? \intval( $row['Sub_part'] ) : null;
				
				if( isset( $indexes[ $row['Key_name'] ] ) )
				{
					$indexes[ $row['Key_name'] ]['length'][] = $length;
					$indexes[ $row['Key_name'] ]['columns'][] = $row['Column_name'];
				}
				else
				{
					$type = 'key';
					if( $row['Key_name'] === 'PRIMARY' )
					{
						$type = 'primary';
					}
					elseif( $row['Index_type'] === 'FULLTEXT' )
					{
						$type = 'fulltext';
					}
					elseif( !$row['Non_unique'] )
					{
						$type = 'unique';
					}
					
					$indexes[ $row['Key_name'] ] = array(
						'type'		=> $type,
						'name'		=> $row['Key_name'],
						'length'	=> array( $length ),
						'columns'	=> array( $row['Column_name'] )
						);
				}
			}
			$definition['indexes'] = $indexes;
			
			/* Finally, get the table comment and engine */
			$row = $this->forceQuery( "SHOW TABLE STATUS LIKE '{$this->prefix}" . $this->escape_string( $table ) . "'" )->fetch_assoc();

			if( $row['Comment'] )
			{
				$definition['comment']	= $row['Comment'];
			}

			if( $row['Collation'] )
			{
				$definition['collation'] = $row['Collation'];
			}
	
			if( $row['Engine'] )
			{
				$definition['engine']	= $row['Engine'];
			}
				
		}
		
		/* Return */
		return $definition;
	}
	
	/**
	 * Add column to table in database
	 *
	 * @see		compileColumnDefinition
	 * @param	string	$table			Table name
	 * @param	array	$definition		Column Definition (see \IPS\Db::compileColumnDefinition for details)
	 * @return	void
	 */
	public function addColumn( $table, $definition )
	{
		return $this->query( "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` ADD COLUMN {$this->compileColumnDefinition( $definition )}" );
	}
	
	/**
	 * Modify an existing column
	 *
	 * @see		compileColumnDefinition
	 * @param	string	$table			Table name
	 * @param	string	$column			Column name
	 * @param	array	$definition		New column definition (see \IPS\Db::compileColumnDefinition for details)
	 * @return	void
	 */
	public function changeColumn( $table, $column, $definition )
	{
		return $this->query( "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` CHANGE COLUMN `{$this->escape_string( $column )}` {$this->compileColumnDefinition( $definition )}" );
	}
	
	/**
	 * Drop a column
	 *
	 * @param	string			$table			Table name
	 * @param	string|array	$column			Column name
	 * @return	void
	 */
	public function dropColumn( $table, $column )
	{
		if( \is_array( $column ) )
		{
			$drops	= array();

			foreach( $column as $_column )
			{
				$drops[]	= "DROP COLUMN `{$this->escape_string( $_column )}`";
			}

			$statement	= implode( ", ", $drops );
		}
		else
		{
			$statement = "DROP COLUMN `{$this->escape_string( $column )}`";
		}

		return $this->query( "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` {$statement};" );
	}
	
	/**
	 * Add index to table in database
	 *
	 * @see		compileIndexDefinition
	 * @param	string	$table				Table name
	 * @param	array	$definition			Index Definition (see \IPS\Db::compileIndexDefinition for details)
	 * @param	bool	$discardDuplicates	If adding a unique index, should duplicates be discarded? (If FALSE and there are any, an exception will be thrown)
	 * @return	void
	 */
	public function addIndex( $table, $definition, $discardDuplicates=TRUE )
	{
		/* If it's a unique index, make sure there won't be any duplicates */
		if ( $discardDuplicates and \in_array( $definition['type'], array( 'primary', 'unique' ) ) AND $this->returnQuery === FALSE )
		{
			$this->duplicateTableStructure( $table, "{$table}_temp" );
			$this->addIndex( "{$table}_temp", $definition, FALSE );
			$this->insert( "{$table}_temp", \IPS\Db::i()->select( '*', $table ), FALSE, TRUE );
			$this->dropTable( $table );
			$this->renameTable( "{$table}_temp", $table );
		}
		/* Otherwise just do it normally */
		else
		{
			return $this->query( "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` {$this->buildIndex( $table, $definition )}" );
		}
	}
	
	/**
	 * Modify an existing index
	 *
	 * @see		compileIndexDefinition
	 * @param	string	$table			Table name
	 * @param	string	$index			Index name
	 * @param	array	$definition		New index definition (see \IPS\Db::compileIndexDefinition for details)
	 * @return	void
	 */
	public function changeIndex( $table, $index, $definition )
	{
		$returnQuery = $this->returnQuery;
		$return = NULL;

		if( $this->checkForIndex( $table, $index ) )
		{
			$query = $this->dropIndex( $table, $index );
		
			if( $returnQuery === TRUE )
			{
				$return = $query;
			}
		}
		
		if ( $returnQuery )
		{
			$this->returnQuery = TRUE;
		}
		
		$query = $this->addIndex( $table, $definition );
		
		if( $returnQuery === TRUE )
		{
			$this->returnQuery = FALSE;
			$return .= $query;
	
			return $return;
		}

		return $query;
	}

	/**
	 * Build an index query for add/change
	 *
	 * @see		compileIndexDefinition
	 * @param	string	$table			Table name
	 * @param	array	$definition		New index definition (see \IPS\Db::compileIndexDefinition for details)
	 * @param	array|NULL	$data		Table definition, or null to pull from database
	 * @return	void
	 */
	public function buildIndex( $table, $definition, $data=NULL )
	{
		$indexName	= $definition['name'];
		
		if ( $data === NULL )
		{
			$data	= $this->getTableDefinition( $table, FALSE, TRUE );
		}
		
		$engine		= mb_strtolower( $data['engine'] );
		
		/* Add the index to the table definition */
		$data['indexes'][ $indexName ] = $definition;
		
		/* Reduce sub_part if required */
		$data	= $this->updateDefinitionIndexLengths( $data );
		$return = '';

		/* Do we need to adjust the engine because it's a fulltext index? */
		if( $engine !== mb_strtolower( $data['engine'] ) )
		{
			$return = "ENGINE={$data['engine']}, ";
		}

		/* Extract the key we want to add */
		$definition = $data['indexes'][ $indexName ];

		return $return . "ADD {$this->compileIndexDefinition( $definition )}";
	}
	
	/**
	 * Drop an index
	 *
	 * @param	string			$table			Table name
	 * @param	string|array	$index			Column name
	 * @return	mixed
	 */
	public function dropIndex( $table, $index )
	{
		$index = ( \is_array( $index ) ) ? $index : array( $index );

		$indexes	= array();

		if( \IPS\Db::i()->returnQuery )
		{
			foreach( $index as $key => $col )
			{
				if ( !$this->checkForIndex( $table, $col ) )
				{
					unset( $index[$key] );
				}
			}
		}

		foreach( $index as $col )
		{
			$indexes[]	= ( $col == 'PRIMARY KEY' ) ? "DROP " . $col : "DROP INDEX `" . $this->escape_string( $col ) . "`";
		}

		$_index	= implode( ', ', $indexes );


		try
		{
			$return = '';
			
			if ( $_index )
			{
				$return = $this->query( "ALTER TABLE `{$this->prefix}{$this->escape_string( $table )}` {$_index};" );
			}
			else
			{
				/* Even if we do not run a query here, we need to reset this */
				\IPS\Db::i()->returnQuery = FALSE;
			}
			
			return $return;
		}
		catch( \IPS\Db\Exception $e )
		{
			/* No need to stop here if index doesn't exist */
			if ( $e->getCode() !== 1091 )
			{
				throw $e;
			}

			return 0;
		}
	}

	/**
	 * FIND_IN_SET
	 * Generates a WHERE clause to determine if any value from a column containing a comma-delimined list matches any value from an array
	 * 
	 * @param	string	$column		Column name (which contains a comma-delimited list)
	 * @param	array	$values		Acceptable values
	 * @param	bool	$reverse	If true, will match cases where NO values from $column match any from $values
	 * @return 	string	Where clause
	 * @link	in
	 	More efficient equivilant for columns that do not contain comma-delimited lists
	 * @endlink
	 */
	public function findInSet( $column, $values, $reverse=FALSE )
	{
		$where = array();

		foreach( $values as $i )
		{
			if ( $i != NULL and \is_numeric( $i ) )
			{
				$where[] = ( $reverse ? 'NOT ' : '' ) . "FIND_IN_SET(" . $i . "," . $column . ")";
			}
			else if ( $i != NULL and \is_string( $i ) )
			{
				$where[] = ( $reverse ? 'NOT ' : '' ) . "FIND_IN_SET('" . $this->real_escape_string( $i ) . "'," . $column . ")";
			}
		}

		$statement = $reverse ? 'AND' : 'OR';
		
		if ( ! empty( $where ) )
		{
			return '( ' . implode( " {$statement} ", $where ) . ' )';
		}
		else
		{
			return $reverse ? '1=1' : '1=0';
		}
	}

	/**
	 * IN
	 * Generates a WHERE clause to determine if the value of a column matches any value from an array
	 *
	 * @param	string	$column			Column name
	 * @param	array	$values			Acceptable values
	 * @param	bool	$reverse		If true, will match cases where $column does NOT match $values
	 * @return 	string	Where clause
	 * @link	findInSet
	 	For columns that contain comma-delimited lists
	 * @endlink
	 */
	public function in( $column, $values, $reverse=FALSE )
	{
		$in	= array();

		if( !\is_array( $values ) )
		{
			$values = array( $values );
		}
		
		foreach( $values as $i )
		{
			/* We must use the !== comparison so that 0 is not treated the same as NULL */
			if ( $i !== NULL and \is_numeric( $i ) and ( \is_int( $i ) or \is_float( $i ) ) )
			{
				$in[] = $i;
			}
			else if ( $i != NULL and \is_string( $i ) )
			{
				$in[] = "'" . $this->real_escape_string( $i ) . "'";
			}
			else if( $i instanceof \IPS\Db\Select )
			{
				$in[] = (string) $i;
			}
		}
	
		$return = array();

		if ( ! empty( $in ) )
		{
			$return[] = $column . ( $reverse ? ' NOT' : '' ) . ' IN(' . implode( ',', $in ) . ')';
		}
		
		if ( \count( $return ) )
		{
			return '( ' . implode( ' OR ', $return ) . ' )';
		}
		else
		{
			return $reverse ? '1=1' : '1=0';
		}
	}

	/**
	 * Generates a WHERE clause to perform a LIKE search
	 *
	 * @param	string|array	$column				The column(s) we are searching (multiple columns are searched as an OR)
	 * @param	string			$string				The string we are searching for
	 * @param	bool			$escape				Whether or not to escape wildcards in the search string
	 * @param	bool			$trailingWildcard	Add a wildcard to the end of the string
	 * @param	bool			$leadingWildcard	Add a wildcard to the beginning of the string (note that database indexes cannot be used in this case)
	 * @param	bool			$reverse			Perform a NOT LIKE query instead of a LIKE query
	 * @return	array
	 */
	public function like( $column, $string, $escape=TRUE, $trailingWildcard=TRUE, $leadingWildcard=FALSE, $reverse=FALSE )
	{
		if( $escape === TRUE )
		{
			$string = str_replace( array( '%', '_' ), array( '\%', '\_' ), $string );
		}

		if( !\is_array( $column ) )
		{
			$column = array( $column );
		}

		$_not			= $reverse ? 'NOT ' : '';
		$searchClause	= array();

		if( $trailingWildcard === TRUE AND $leadingWildcard === TRUE )
		{
			foreach( $column as $_column )
			{
				$searchClause[] = "{$_column} {$_not}LIKE CONCAT( '%', ?, '%' )";
			}
		}
		elseif( $trailingWildcard === TRUE )
		{
			foreach( $column as $_column )
			{
				$searchClause[] = "{$_column} {$_not}LIKE CONCAT( ?, '%' )";
			}
		}
		elseif( $leadingWildcard === TRUE )
		{
			foreach( $column as $_column )
			{
				$searchClause[] = "{$_column} {$_not}LIKE CONCAT( '%', ? )";
			}
		}
		else
		{
			foreach( $column as $_column )
			{
				$searchClause[] = "{$_column} {$_not}LIKE ?";
			}
		}

		return array_merge( array( implode( ' OR ', $searchClause ) ), array_fill( 1, \count( $searchClause ), $string ) );
	}
	
	/**
	 * Bitwise WHERE clause
	 *
	 * @param	array	$definition		Bitwise keys as defined by the class
	 * @param	string	$key			The key to check for
	 * @param	bool	$value			Value to check for
	 * @param	string	$prefix			Column prefix (optional)
	 * @return	string
	 * @throws	\InvalidArgumentException
	 */
	public function bitwiseWhere( $definition, $key, $value=TRUE, $prefix=NULL )
	{
		$operator = $value ? '& ' : '& ~';
		foreach ( $definition as $column => $keys )
		{
			if ( isset( $keys[ $key ] ) )
			{
				$column = $prefix ? $prefix . $column : $column;
				return "(`{$column}` {$operator}{$keys[ $key ]} ) != 0";
			}
		}
		
		throw new \InvalidArgumentException;
	}

	/**
	 * Strip index lengths in the schema definitions - useful for a better comparison of the definitions
	 * since different engines and charsets require different storage.  Also, strip engine and collation.
	 *
	 * @param	array|string	$data		Table definition (array) or table name (string)
	 * @return	array
	 */
	public function normalizeDefinition( $data )
	{
		$definition  = ( \is_array( $data ) ) ? $data : $this->getTableDefinition( $data, FALSE, TRUE );

		if ( isset( $definition['indexes'] ) )
		{
			foreach( $definition['indexes'] as $key => &$index )
			{
				/* Make sure the keys are in the correct order otherwise normal variances trigger differences just because 'columns' can come before 'length', etc */
				ksort( $index );
				
				if( isset( $index['length'] ) )
				{
					foreach( $index['length'] as $_key => $length )
					{
						$definition['indexes'][ $key ]['length'][ $_key ] = null;
					}
				}
			}
		}

		$decimalTypes	= array( 'DECIMAL' );
		$lengthTypes	= array( 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'DECIMAL', 'FLOAT', 'BIT' );

		foreach ( $definition['columns'] as $k => $c )
		{
			if( !\in_array( $c['type'], $decimalTypes ) )
			{
				if( array_key_exists( 'decimals', $c ) )
				{
					unset( $definition['columns'][ $k ]['decimals'] );
				}
			}
			else
			{
				if( !array_key_exists( 'decimals', $c ) )
				{
					$definition['columns'][ $k ]['decimals'] = null;
				}
				else
				{
					$definition['columns'][ $k ]['decimals'] = (int) $definition['columns'][ $k ]['decimals'];
				}
			}

			if( !\in_array( $c['type'], $lengthTypes ) )
			{
				if( array_key_exists( 'length', $c ) )
				{
					unset( $definition['columns'][ $k ]['length'] );
				}
			}
			else
			{
				if( !array_key_exists( 'length', $c ) )
				{
					$definition['columns'][ $k ]['length'] = null;
				}
				else
				{
					$definition['columns'][ $k ]['length'] = (int) $definition['columns'][ $k ]['length'];
				}
			}

			if ( !isset( $c['values'] ) )
			{
				$definition['columns'][ $k ]['values'] = array();
			}
			
			if ( $c['type'] === 'BIT' )
			{
				if( \is_null( $c['default'] ) )
				{
					$definition['columns'][ $k ]['default'] = NULL;
				}
				elseif( mb_strpos( $c['default'], 'b' ) === 0 )
				{
					$definition['columns'][ $k ]['default'] = $c['default'];
				}
				else
				{
					$definition['columns'][ $k ]['default'] = "b'{$c['default']}'";
				}
			}
			
			ksort( $definition['columns'][ $k ] );
		}

		if( isset( $definition['collation'] ) )
		{
			unset( $definition['collation'] );
		}

		if( isset( $definition['engine'] ) )
		{
			unset( $definition['engine'] );
		}
		
		/* Prevent conflicts when schema says DEFAULT '0' but it is DEFAULT 0 and an INT type column as this is always set as a 0 anyway */
		foreach( $definition['columns'] as $name => $data )
		{
			if ( \in_array( mb_strtoupper( $data['type'] ), array_keys( static::$dataTypes['database_column_type_numeric'] ) ) and ( ! \in_array( mb_strtoupper( $data['type'] ), array( 'DECIMAL', 'FLOAT', 'BIT' ) ) ) )
			{
				if( \is_numeric( $data['default'] ) )
				{
					$definition['columns'][ $name ]['default'] = \intval( $data['default'] );
				}

				/* Length is no longer supported */
				if( isset( $data['length'] ) )
				{
					unset( $definition['columns'][ $name ]['length'] );
				}
			}

			/* These are legacy things we no longer support as MySQL 8 has deprecated the functionality */
			if( isset( $data['zerofill'] ) )
			{
				unset( $definition['columns'][ $name ]['zerofill'] );
			}

			if( isset( $data['binary'] ) )
			{
				unset( $definition['columns'][ $name ]['binary'] );
			}

			if ( \in_array( mb_strtoupper( $data['type'] ), array_keys( static::$dataTypes['database_column_type_numeric'] ) ) and ( ! \in_array( mb_strtoupper( $data['type'] ), array( 'DECIMAL', 'FLOAT', 'BIT' ) ) ) and \is_numeric( $data['default'] ) )
			{
				$definition['columns'][ $name ]['default'] = \intval( $data['default'] );
			}
		}
		
		return $definition;
	}

	/**
	 * Attempt to fix issues with keys longer than maximum allowed by DB engine
	 * which is 1000 bytes for MyISAM and 767 for InnoDB taking into consideration the
	 * multiplier (4 bytes per character for utf8mb4 and 3 bytes per character for UTF8)
	 *
	 * @param	array|string	$data		Table definition (array) or table name (string)
	 * @return	array
	 */
	public function updateDefinitionIndexLengths( $data )
	{
		$definition  = ( \is_array( $data ) ) ? $data : $this->getTableDefinition( $data, FALSE, TRUE );
		$length      = 0;
		/* We use 4 for utf8mb4 to prevent issues if the client attempts to switch or forgets to mark conf_global.php that utf8mb4 is used */
		$multiplier	 = 4;
		$needsFixing = array();
		$maxLen      = 1000;
		
		if ( ( ! isset( $definition['engine'] ) OR mb_strtolower( $definition['engine'] ) == 'innodb' ) and isset( $definition['indexes'] ) )
		{
			/* Only set/change the engine if it is not already innodb - don't change innodb to myisam */
			if( ( ! isset( $definition['engine'] ) OR mb_strtolower( $definition['engine'] ) != 'innodb' ) )
			{
				$definition['engine'] = $this->defaultEngine();
			}
			
			/* Any FULLTEXT fields? */
			foreach( $definition['indexes'] as $key => $data )
			{
				if ( $data['type'] === 'fulltext' )
				{
					/* If this is a fulltext index, set engine to myisam but only if engine is something besides innodb or myisam OR the mysql version is less than 5.6 - in
						this case we assume the default engine is most likely either myisam or innodb */
					if( !$this->_innoDbSupportsFulltextIndexes() OR ( isset( $definition['engine'] ) AND !\in_array( mb_strtoupper( $definition['engine'] ), array( 'INNODB', 'MYISAM' ) ) ) )
					{
						$definition['engine'] = 'myisam';
					}
				}
			}
		}
		
		if ( \mb_strtolower( $definition['engine'] ) === 'innodb' )
		{
			$maxLen = 767;
		}
		
		if ( isset( $definition['indexes'] ) )
		{
			foreach( $definition['indexes'] as $key => $index )
			{
				$thisLength = null;
				$hasText	= false;
				
				foreach( $index['columns'] as $i => $column )
				{
					if( isset( $index['length'][ $i ] ) )
					{
						$thisLength = $index['length'][ $i ];
					}
					elseif( (int) $definition['columns'][ $column ]['length'] or empty( $definition['columns'][ $column ]['length'] ) )
					{
						$thisLength = (int) $definition['columns'][ $column ]['length'];
					}
					else
					{
						$thisLength = 250;
					}
					
					$isText = \in_array( mb_strtolower( $definition['columns'][ $column ]['type'] ), array( 'mediumtext', 'text' ) );
					
					if ( $hasText === false and $isText === true )
					{
						$hasText = true;
					}
					
					if ( isset( $definition['columns'][ $column ] ) and ( ( ! empty( $thisLength ) or $isText ) ) )
					{
						$length += $thisLength;
					}
				}
			
				if ( ( $length * $multiplier > $maxLen ) or $hasText )
				{
					foreach( $index['columns'] as $i => $column )
					{
						if( isset( $index['length'][ $i ] ) )
						{
							$thisLength = $index['length'][ $i ];
						}
						elseif( (int) $definition['columns'][ $column ]['length'] or empty( $definition['columns'][ $column ]['length'] ) )
						{
							$thisLength = (int) $definition['columns'][ $column ]['length'];
						}
						else
						{
							$thisLength = 250;
						}

						/* If this is a datetime column, the length will top out at 8 bytes max, so just use 8 as our limitation...indexing datetime columns is fairly rare for us anyways */
						if ( \in_array( mb_strtoupper( $definition['columns'][ $column ]['type'] ), array_keys( static::$dataTypes['database_column_type_datetime'] ) ) )
						{
							$thisLength = 8;
						}

						if ( isset( $definition['columns'][ $column ] ) and ( ( ! empty( $thisLength ) or \in_array( mb_strtolower( $definition['columns'][ $column ]['type'] ), array( 'mediumtext', 'text' ) ) ) ) )
						{
							/* Column name, column length, column type  */
							$needsFixing[ $key ][ $i ] = array( $column, $thisLength, $definition['columns'][ $column ]['type'] );
						}
					}
				}
						
				$length = 0;
			}
		}
		
		if ( \count( $needsFixing ) )
		{
			foreach( $needsFixing as $key => $i )
			{
				$totalLength	= 0;
				$maxChars		= $maxLen / $multiplier;

				foreach( $i as $vals )
				{
					$totalLength += $vals[1];
				}

				if ( $totalLength > $maxChars )
				{
					/* Check each column can be reduced by the amount we need reducing */
					$debt = 0;
 
					$reduceEachBy = ( ( 100 / $totalLength ) * $maxChars) / 100;
 
					/* Apply debt if we have any. We do not reduce non-strings. */
					foreach( $i as $x => $vals )
					{
						if ( !\in_array( mb_strtoupper( $vals[2] ), array_keys( static::$dataTypes['database_column_type_string'] ) ) )
						{
							$debt += $vals[1];
						}
					}

					/* Recalculate value to multiply index sub lengths with (subtracting debt) */
					if ( $debt < $totalLength )
					{
						$reduceEachBy = ( ( 100 / ($totalLength - $debt) ) * ( $maxChars - $debt ) ) / 100;
					}

					foreach( $i as $x => $vals )
					{
						/* No length? */
						if ( empty( $vals[1] ) )
						{
							$vals[1] = 250;
						}

						if ( !\in_array( mb_strtoupper( $vals[2] ), array_keys( static::$dataTypes['database_column_type_string'] ) ) )
						{
							/* Preserve col len where possible but if the column length is greater than subpart allowed, NULL the length
							   otherwise MySQL will complain as you cannot use subpart on non-string column. */
							$vals[1] = NULL;
							$i[ $x ] = $vals;
							
							continue;
						}

						$vals[1] = floor( $vals[1] * $reduceEachBy );	
						$i[ $x ] = $vals;
					}
				}

				foreach( $i as $x => $vals )
				{
					if ( !isset( $definition['columns'][ $definition['indexes'][ $key ]['columns'][ $x ] ]['length'] ) OR ( $definition['columns'][ $definition['indexes'][ $key ]['columns'][ $x ] ]['length'] != $vals[1] AND \in_array( mb_strtoupper( $vals[2] ), array_keys( static::$dataTypes['database_column_type_string'] ) ) ) )
					{
						$definition['indexes'][ $key ]['length'][ $x ] = \intval( $vals[1] );
					}
					else
					{
						$definition['indexes'][ $key ]['length'][ $x ] = NULL;
					}
				}
			}
		}

		return $definition;
	}
	
	/**
	 * Create database
	 *
	 * @param	string	$name	Database Name
	 * @return	bool
	 */
	public function createDatabase( $name )
	{
		return ( $this->query( "CREATE DATABASE ". $this->escape_string( "{$name}" ) ) );
	}

	/**
	 * @brief	Cached table data
	 */
	public $cachedTableData	= array();

	/**
	 * Is it recommended to run a query manually?
	 *
	 * @param	string	$tableName	Database table to work with
	 * @return	bool
	 * @note	Constants \IPSUPGRADE_MANUAL_THRESHOLD and \IPS\UPGRADE_LARGE_TABLE_SIZE can be defined in constants.php
	 */
	public function recommendManualQuery( $tableName )
	{
		/* Does the table even exist? */
		if( !$this->checkForTable( $tableName ) )
		{
			return FALSE;
		}

		/* Have we gathered the table data yet? */
		if( !isset( $this->cachedTableData[ $tableName ] ) )
		{
			$this->cachedTableData[ $tableName ] = array( 'rows' => 0, 'size' => 0 );

			$this->cachedTableData[ $tableName ]['rows'] = $this->select( 'count(*)', $tableName )->first();

			$result = $this->forceQuery( "SHOW TABLE STATUS WHERE name LIKE '" . $this->prefix . $tableName . "'" );

			while( $data = $result->fetch_assoc() )
			{
				$this->cachedTableData[ $tableName ]['size'] = $data['Data_length'];
			}
		}

		/* Now determine if we're over our limits and return appropriately */
		if( $this->cachedTableData[ $tableName ]['rows'] > \IPS\UPGRADE_MANUAL_THRESHOLD )
		{
			return TRUE;
		}

		if( $this->cachedTableData[ $tableName ]['size'] > \IPS\UPGRADE_LARGE_TABLE_SIZE )
		{
			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Strip comments from a .sql file
	 *
	 * @param	string	$contents	Contents from SQL file
	 * @return	string
	 */
	public static function stripComments( $contents )
	{
		$contents = preg_replace( '/\/\*.+?\*\//', '', $contents );
		$contents = preg_replace( '/#.*/', '', $contents );
		$contents = preg_replace( '/--.*/', '', $contents );
		$contents = trim( $contents );

		return $contents;
	}

	/**
	 * Replace binds in a prepared query to get the "full" query
	 *
	 * @param	string	$query	Query
	 * @param	array 	$binds	Any binds in the query
	 * @return	string
	 */
	public static function _replaceBinds( $query, $binds )
	{
		/* Replace ?s with the actual values */
		if( \is_array( $binds ) AND \count( $binds ) )
		{
			foreach ( $binds as $b )
			{
				$b = ( $b instanceof \IPS\Db\Select ) ? (string) $b : $b;
				$query = preg_replace( '/\?/', var_export( $b, TRUE ), $query, 1 );
			}
		}

		return $query;
	}
}

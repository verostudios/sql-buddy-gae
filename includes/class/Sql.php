<?php
/*

SQL Buddy - Web based MySQL administration
http://www.sqlbuddy.com/

sql.php
- sql class

MIT license

2008 Calvin Lough <http://calv.in>

*/

class SQL {
	
	var $adapter = "";
	var $method = "";
	var $version = "";
	var $conn = "";
	var $options = "";
	var $errorMessage = "";
	var $db = "";
	var $ignoreTables = array('information_schema', 'mysql', 'performance_schema');
	
	function SQL($connString, $user = "", $pass = "") {
		list($this->adapter, $options) = explode(":", $connString, 2);
		
		if ($this->adapter != "sqlite") {
			$this->adapter = "mysql";
		}
		
		$optionsList = explode(";", $options);
		
		foreach ($optionsList as $option) {
			list($a, $b) = explode("=", $option);
			$opt[$a] = $b;
		}
		
		$this->options = $opt;
		$database = (array_key_exists("database", $opt)) ? $opt['database'] : "";
		
		if ($this->adapter == "sqlite" && substr(sqlite_libversion(), 0, 1) == "3" && class_exists("PDO") && in_array("sqlite", PDO::getAvailableDrivers())) {
			$this->method = "pdo";
			
			try
			{
				$this->conn = new PDO("sqlite:" . $database, null, null, array(PDO::ATTR_PERSISTENT => true));
			}
			catch (PDOException $error) {
				$this->conn = false;
            	$this->errorMessage = $error->getMessage();
          	}
		} else if ($this->adapter == "sqlite" && substr(sqlite_libversion(), 0, 1) == "2" && class_exists("PDO") && in_array("sqlite2", PDO::getAvailableDrivers())) {
			$this->method = "pdo";
			
			try
			{
				$this->conn = new PDO("sqlite2:" . $database, null, null, array(PDO::ATTR_PERSISTENT => true));
			}
			catch (PDOException $error) {
				$this->conn = false;
            	$this->errorMessage = $error->getMessage();
          	}
		} else if ($this->adapter == "sqlite") {
			$this->method = "sqlite";
			$this->conn = sqlite_open($database, 0666, $sqliteError);
		} else {
			$this->method = "pdo";
			$host = (array_key_exists("host", $opt)) ? $opt['host'] : "";
			$this->conn = new PDO($connString, $user, $pass);
		}
		
		if ($this->conn && $this->method == "pdo") {
			$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		}
		
		if ($this->conn && $this->adapter == "mysql") {
			$this->query("SET NAMES 'utf8'");
		}
		
		if ($this->conn && $database) {
			$this->db = $database;
		}
	}
	
	function isConnected() {
		return ($this->conn !== false);
	}
	
	function disconnect() {
		if ($this->conn) {
			if ($this->method == "pdo") {
				$this->conn = null;
			} else if ($this->method == "mysql") {
				mysql_close($this->conn);
				$this->conn = null;
			} else if ($this->method == "sqlite") {
				sqlite_close($this->conn);
				$this->conn = null;
			}
		}
	}
	
	function getAdapter() {
		return $this->adapter;
	}
	
	function getMethod() {
		return $this->method;
	}
	
	function getOptionValue($optKey) {
		if (array_key_exists($optKey, $this->options)) {
			return $this->options[$optKey];
		} else {
			return false;
		}
	}
	
	function selectDB($db) {
		if ($this->conn) {
			if ($this->method == "mysql") {
				$this->db = $db;

				return $this->conn->query("USE $db;"); // (mysql_select_db($db));
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	function query($queryText) {

		if ($this->conn) {
			if ($this->method == "pdo") {
				try {
					$queryResult = $this->conn->prepare($queryText);
				
					if ($queryResult !== FALSE)
						$queryResult->execute();
					
				} catch ( PDOException $e ) {
					$this->errorMessage = $e->getMessage();
				}

				return $queryResult;
			} else if ($this->method == "mysql") {
				$queryResult = @mysql_query($queryText, $this->conn);

				if (!$queryResult) {
					$this->errorMessage = mysql_error();
				}

				return $queryResult;
			} else if ($this->method == "sqlite") {
				$queryResult = sqlite_query($this->conn, $queryText);

				if (!$queryResult) {
					$this->errorMessage = sqlite_error_string(sqlite_last_error($this->conn));
				}

				return $queryResult;
			}
		} else {
			$this->errorMessage = 'no database connection detected.';
			return false;
		}
	}
	
	// Be careful using this function - when used with pdo, the pointer is moved
	// to the end of the result set and the query needs to be rerun. Unless you 
	// actually need a count of the rows, use the isResultSet() function instead
	function rowCount($resultSet) {
		if (!$resultSet)
			return false;
		
		if ($this->conn) {
			if ($this->method == "pdo") {
				return $resultSet->rowCount();
			} else if ($this->method == "mysql") {
				return @mysql_num_rows($resultSet);
			} else if ($this->method == "sqlite") {
				return @sqlite_num_rows($resultSet);
			}
		}
	}
	
	function isResultSet($resultSet) {
		if ($this->conn) {
			if ($this->method == "pdo") {
				return ($resultSet == true);
			} else {
				return ($this->rowCount($resultSet) > 0);
			}
		}
	}

	function fetchArray($resultSet) {
		if (!$resultSet)
			return false;
		
		if ($this->conn) {
			if ($this->method == "pdo") {
				return $resultSet->fetch(PDO::FETCH_NUM);
			} else if ($this->method == "mysql") {
				return mysql_fetch_row($resultSet);
			} else if ($this->method == "sqlite") {
				return sqlite_fetch_array($resultSet, SQLITE_NUM);
			}
		}
	}

	function fetchAssoc($resultSet) {
		if (!$resultSet)
			return false;
		
		if ($this->conn) {
			if ($this->method == "pdo") {
				return $resultSet->fetch(PDO::FETCH_ASSOC);
			} else if ($this->method == "mysql") {
				return mysql_fetch_assoc($resultSet);
			} else if ($this->method == "sqlite") {
				return sqlite_fetch_array($resultSet, SQLITE_ASSOC);
			}
		}
	}

	function affectedRows($resultSet) {
		if (!$resultSet)
			return false;
		
		if ($this->conn) {
			if ($this->method == "pdo") {
				return $resultSet->rowCount();
			} else if ($this->method == "mysql") {
				return @mysql_affected_rows($resultSet);
			} else if ($this->method == "sqlite") {
				return sqlite_changes($resultSet);
			}
		}
	}
	
	function result($resultSet, $targetRow, $targetColumn = "") {
		if (!$resultSet)
			return false;
		
		if ($this->conn) {
			if ($this->method == "pdo") {
				if ($targetColumn) {
					$resultRow = $resultSet->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $targetRow);
					return $resultRow[$targetColumn];
				} else {
					$resultRow = $resultSet->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_ABS, $targetRow);
					return $resultRow[0];
				}
			} else if ($this->method == "mysql") {
				return mysql_result($resultSet, $targetRow, $targetColumn);
			} else if ($this->method == "sqlite") {
				return sqlite_column($resultSet, $targetColumn);
			}
		}
	}
	
	function listDatabases() {
		if ($this->conn) {
			if ($this->adapter == "mysql") {
				return $this->conn->query("SHOW DATABASES;");
			} else if ($this->adapter == "sqlite") {
				return $this->db;
			}
		}
	}
	
	function listTables() {
		if ($this->conn) {
			if ($this->adapter == "mysql") {
				return $this->conn->query("SHOW TABLES;");
			} else if ($this->adapter == "sqlite") {
				return $this->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
			}
		}
	}
	
	function hasCharsetSupport()
	{
		if ($this->conn) {
			if ($this->adapter == "mysql" && version_compare($this->getVersion(), "4.1", ">")) {
				return true;
			} else  {
				return false;
			}
		}
	}
	
	function listCharset() {
		if ($this->conn) {
			if ($this->adapter == "mysql") {	
				return $this->query("SHOW CHARACTER SET");
			} else if ($this->adapter == "sqlite") {
				return "";
			}
		}
	}
	
	function listCollation() {
		if ($this->conn) {
			if ($this->adapter == "mysql") {
				return $this->query("SHOW COLLATION");
			} else if ($this->adapter == "sqlite") {
				return "";
			}
		}
	}
	
	function insertId() {
		if ($this->conn) {
			if ($this->method == "pdo") {
				return $this->conn->lastInsertId();
			} else if ($this->method == "mysql") {
				return @mysql_insert_id($this->conn);
			} else if ($this->method == "sqlite") {
				return sqlite_last_insert_rowid($this-conn);
			}
		}
	}

	function escapeString($toEscape) {
		if ($this->conn) {
			if ($this->method == "pdo") {
				$toEscape = $this->conn->quote($toEscape);
				$toEscape = substr($toEscape, 1, -1);
				return $toEscape;
			} else if ($this->adapter == "mysql") {
				return mysql_real_escape_string($toEscape);
			} else if ($this->adapter == "sqlite") {
				return sqlite_escape_string($toEscape);
			}
		}
	}
	
	function getVersion() {
		if ($this->conn) {
			// cache
			if ($this->version) {
				return $this->version;
			}
			
			switch( $this->adapter ) {
				case 'mysql':
					// $verSql = mysql_get_server_info();
					$query = "SELECT VERSION() as mysql_version;";
					$result = $this->query( $query );
					$row = $result->fetch();

					$version = explode("-", $row['mysql_version']);
					$this->version = $version[0];
					return $this->version;
					break;
				case 'sqlite':
					$this->version = sqlite_libversion();
					return $this->version;
					break;
			}
		}

	}
	
	// returns the number of rows in a table
	function tableRowCount($db, $table) {
		if ($this->conn) {
			if ($this->adapter == "mysql") {
				$countSql = $this->query("SELECT COUNT(*) AS `RowCount` FROM $db.$table;");
				$countRow = $countSql->fetch(PDO::FETCH_ASSOC);
				$count = (int)$countRow["RowCount"];
				return $count;
			} else if ($this->adapter == "sqlite") {
				$countSql = $this->query("SELECT COUNT(*) AS 'RowCount' FROM '" . $table . "'");
				$count = (int)($this->result($countSql, 0, "RowCount"));
				return $count;
			}
		}
	}
	
	// gets column info for a table
	function describeTable($db, $table) {
		if ($this->conn) {
			if ($this->adapter == "mysql") {
				return $this->query("DESCRIBE $db." . $table . ";");
			} else if ($this->adapter == "sqlite") {
				$columnSql = $this->query("SELECT sql FROM sqlite_master where tbl_name = '" . $table . "'");
				$columnInfo = $this->result($columnSql, 0, "sql");
				$columnStart = strpos($columnInfo, '(');
				$columns = substr($columnInfo, $columnStart+1, -1);
				$columns = split(',[^0-9]', $columns);
				
				$columnList = array();
				
				foreach ($columns as $column) {
					$column = trim($column);
					$columnSplit = explode(" ", $column, 2);
					$columnName = $columnSplit[0];
					$columnType = (sizeof($columnSplit) > 1) ? $columnSplit[1] : "";
					$columnList[] = array($columnName, $columnType);
				}
				
				return $columnList;
			}
		}
	}
	
	/*
		Return names, row counts etc for every database, table and view in a JSON string
	*/
	function getMetadata() {
		$output = '';
		if ($this->conn) {
			if ($this->adapter == "mysql" && version_compare($this->getVersion(), "5.0.0", ">=")) {
				// $this->selectDB("information_schema");

				$schemaQuery = $this->conn->query("SELECT `SCHEMA_NAME` FROM information_schema.SCHEMATA ORDER BY `SCHEMA_NAME`;");
				if ( $schemaQuery->rowCount() > 0 )
				{
					while ( $schema = $schemaQuery->fetch(PDO::FETCH_ASSOC) )
					{
						if ( !in_array($schema['SCHEMA_NAME'], $this->ignoreTables) )
						{
							$output .= '{"name": "' . $schema['SCHEMA_NAME'] . '"';
							// other interesting columns: TABLE_TYPE, ENGINE, TABLE_COLUMN and many more
							$tableQuery = $this->conn->query("SELECT `TABLE_NAME`, `TABLE_ROWS` FROM information_schema.TABLES WHERE `TABLE_SCHEMA`='" . $schema['SCHEMA_NAME'] . "' ORDER BY `TABLE_NAME`;");
							if ( $tableQuery->rowCount() > 0 )
							{
								$output .= ',"items": [';
								while ( $table = $tableQuery->fetch(PDO::FETCH_ASSOC) )
								{
									if ($schema['SCHEMA_NAME'] == "information_schema") {
										$countSql = $this->conn->query("SELECT COUNT(*) AS `RowCount` FROM information_schema." . $table['TABLE_NAME'] . ";");
										$countRow = $countSql->fetch(PDO::FETCH_ASSOC);
										$rowCount = (int)($countRow['RowCount']);
									} else {
										$rowCount = (int)($table['TABLE_ROWS']);
									}
									
									$output.= '{"name":"' . $table['TABLE_NAME'] . '","rowcount":' . $rowCount . '},';
								}

								if (substr($output, -1) == ",")
									$output = substr($output, 0, -1);
								
								$output .= ']';
							}
							$output .= '},';
						}
					}
					$output = substr($output, 0, -1);
				} else {
					$output = '{"name": "SCHEMA_NAME","items": [{"name":"rowcount","rowcount":0}]}';
				}
			} else if ($this->adapter == "mysql") {
				$schemaSql = $this->listDatabases();
				
				if ($this->conn->rowCount($schemaSql)) {
					while ($schema = $this->conn->fetch($schemaSql)) {
						$output .= '{"name": "' . $schema[0] . '"';
						
						$this->selectDB($schema[0]);
						$tableSql = $this->listTables();
						
						if ($this->conn->rowCount($tableSql)) {
							$output .= ',"items": [';
							while ($table = $this->conn->fetch($tableSql)) {
								$countSql = $this->conn->query("SELECT COUNT(*) AS `RowCount` FROM `" . $table[0] . "`");
								$countRow = $countSql->fetch();
								$rowCount = (int)($countRow['RowCount']);
								$output .= '{"name":"' . $table[0] . '","rowcount":' . $rowCount . '},';
							}
							
							if (substr($output, -1) == ",")
								$output = substr($output, 0, -1);
							
							$output .= ']';
						}
						$output .= '},';
					}
					$output = substr($output, 0, -1);
				}
			} else if ($this->adapter == "sqlite") {
				$output .= '{"name": "' . $this->db . '"';
				
				$tableSql = $this->listTables();
				
				if ($tableSql) {
					$output .= ',"items": [';
					while ($tableRow = $this->fetchArray($tableSql)) {
						$countSql = $this->query("SELECT COUNT(*) AS 'RowCount' FROM '" . $tableRow[0] . "'");
						$rowCount = (int)($this->result($countSql, 0, "RowCount"));
						$output .= '{"name":"' . $tableRow[0] . '","rowcount":' . $rowCount . '},';
					}
					
					if (substr($output, -1) == ",")
						$output = substr($output, 0, -1);
					
					$output .= ']';
				}
				$output .= '}';
			}
		}
		return $output;
	}

	function error() {
		return $this->errorMessage;
	}

}
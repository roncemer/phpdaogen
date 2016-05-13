<?php
// DDL.class.php
// Copyright (c) 2011-2016 Ronald B. Cemer
// All rights reserved.
// This software is released under the BSD license.
// Please see the accompanying LICENSE.txt for details.

if (!class_exists('PreparedStatement', false)) include dirname(__FILE__).'/PreparedStatement.class.php';
if (!class_exists('Spyc', false)) include dirname(__FILE__).'/spyc/spyc.php';

// The DDL class represents an entire Data Definition Language (DDL) schema.
class DDL {
	// The list of currently supported dialects.
	// As we complete new subclasses of Connection for different RDBMS engines, we add their SQL dialects here.
	public static $SUPPORTED_DIALECTS = array('mysql', 'pgsql');

	// Array of DDLTable, DDLIndex, DDLInsert instances.
	public $topLevelEntities;

	public function DDL($topLevelEntities = array()) {
		$this->topLevelEntities = $topLevelEntities;
	}

	public function getAllTableNames() {
		$allTableNames = array();
		foreach ($this->topLevelEntities as $tle) {
			if (($tle instanceof DDLTable) &&
				(!in_array($tle->tableName, $allTableNames))) {
				$allTableNames[] = $tle->tableName;
			}
		}
		return $allTableNames;
	}

	// Given another DDL instance, find table names which exist in both this DDL instance and
	// the other DDL instance.
	// Returns an array of common table names (strings).
	public function getCommonTableNames($otherDDL) {
		$commonTableNames = array();
		foreach ($this->topLevelEntities as $tle) {
			if ($tle instanceof DDLTable) {
				foreach ($otherDDL->topLevelEntities as $otle) {
					if (($otle instanceof DDLTable) && ($otle->tableName == $tle->tableName)) {
						if (!in_array($tle->tableName, $commonTableNames)) {
							$commonTableNames[] = $tle->tableName;
						}
						break;
					}
				}
			}
		}
		return $commonTableNames;
	}

	// Returns the index, or false if not found.
	public function getTableIdxInTopLevelEntities($tableName) {
		for ($i = 0, $n = count($this->topLevelEntities); $i < $n; $i++) {
			if ($this->topLevelEntities[$i] instanceof DDLTable) {
				if ($this->topLevelEntities[$i]->tableName == $tableName) {
					unset($tle);
					return $i;
				}
			}
		}
		unset($tle);
		return false;
	}


	// Given a table name, find and return the DDLTable instance, an array of DDLIndex instances,
	// and an array of DDLForeignKey instances for that table.
	// Returns an object with the following attributes:
	//     tbl: The DDLTable instance, or false if the table was not found in this DDL instance.
	//     idxs: An associative array of index names to DDLIndex instances for the specified table.
	//     fks: An associative array of foreign key names to DDLForeignKey instances for the
	//     specified table.
	public function getTableIndexesAndForeignKeys($tableName) {
		$tbl = false;
		$idxs = array();
		$fks = array();
		foreach ($this->topLevelEntities as $tle) {
			if (($tbl === false) &&
				($tle instanceof DDLTable) &&
				($tle->tableName == $tableName)) {
				$tbl = $tle;
				continue;
			}
			if (($tle instanceof DDLIndex) && ($tle->tableName == $tableName)) {
				$idxs[$tle->indexName] = $tle;
			}
			if (($tle instanceof DDLForeignKey) && ($tle->localTableName == $tableName)) {
				$fks[$tle->foreignKeyName] = $tle;
				continue;
			}
		}
		$result = new stdClass();
		$result->tbl = $tbl;
		$result->idxs = $idxs;
		$result->fks = $fks;
		return $result;
	}
} // DDL

// The DDLTable class represents a complete table definition including columns and optional primary
// key, but NOT including any additional indexes on the table (which are represented by individual
// DDLIndex instances).
class DDLTable {
	// The group name (string).  Optional.  Defaults to null.
	public $group = null;
	// The table name (string).
	public $tableName;
	// Array of DDLTableColumn instances.
	public $columns;
	// A single DDLPrimaryKey instance, or false if none.
	public $primaryKey;

	public function DDLTable($tableName, $columns = array(), $primaryKey = false) {
		$this->tableName = $tableName;
		$this->columns = $columns;
		$this->primaryKey = $primaryKey;
	}

	public function getColumnIdx($name) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->name == $name) {
				return $i;
			}
		}
		return -1;
	}
} // DDLTable

class DDLForeignKey {
	// The foreign key name (string).
	public $foreignKeyName;
	// The local (referring) table name (string).
	public $localTableName;
	// The foreign (referenced) table name (string).
	public $foreignTableName;
	// Array of DDLForeignKeyColumn instances.
	public $columns;

	public function DDLForeignKey($foreignKeyName, $localTableName, $foreignTableName, $columns = array()) {
		$this->foreignKeyName= $foreignKeyName;
		$this->localTableName = $localTableName;
		$this->foreignTableName = $foreignTableName;
		$this->columns = $columns;
	}

	// Returns the index in $columns for the column whose local name equals $localColumnName,
	// or -1 if not found.
	public function getLocalColumnIdx($localColumnName) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->localName == $localColumnName) {
				return $i;
			}
		}
		return -1;
	}

	// Returns the index in $columns for the column whose foreign name equals $foreignColumnName,
	// or -1 if not found.
	public function getForeignColumnIdx($foreignColumnName) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->foreignName == $foreignColumnName) {
				return $i;
			}
		}
		return -1;
	}
} // DDLForeignKey

// The DDLIndex class represents a single index (but not a primary key) on a table.
class DDLIndex {
	// The index name (string).
	public $indexName;
	// The table name (string).
	public $tableName;
	// Whether this is a unique index (boolean).
	public $unique;
	// Array of DDLKeyColumn instances.
	public $columns;
	// Whether this is a fulltext index (boolean).
	public $fulltext;

	public function DDLIndex($indexName, $tableName, $unique, $columns = array(), $fulltext = false) {
		$this->indexName = $indexName;
		$this->tableName = $tableName;
		$this->unique = $unique;
		$this->columns = $columns;
		$this->fulltext = $fulltext;
	}

	// Returns the index in $columns for the column whose name equals $columnName,
	// or -1 if not found.
	public function getColumnIdx($columnName) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->name == $columnName) {
				return $i;
			}
		}
		return -1;
	}
} // DDLIndex

// The DDLInsert class represents a single row to be inserted into a table.
class DDLInsert {
	// The table name (string).
	public $tableName;
	// Array of DDLInsertColumn instances.
	public $columns;
	// Optional array of column names referencing columns in this insert which
	// uniquely identify this insert, so that it can be omitted or changed into
	// an update if a row already exists in the table with these column values.
	public $keyColumnNames;
	// true to update the row if it already exists, or false to simply omit the insert.
	// Requires that keyColumnNames be specified.
	public $updateIfExists;

	public function DDLInsert($tableName, $columns = array(), $keyColumnNames = array(), $updateIfExists = false) {
		$this->tableName = $tableName;
		$this->columns = $columns;
		$this->keyColumnNames = $keyColumnNames;
		$this->updateIfExists = $updateIfExists;
	}

	// Returns the index in $columns for the column whose name equals $columnName,
	// or -1 if not found.
	public function getColumnIdx($columnName) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->name == $columnName) {
				return $i;
			}
		}
		return -1;
	}
} // DDLInsert

// The DDLTableColumn class represents a single column within a DDLTable instance.
// DDLTableColumn instances are members of the $columns array member variable of the
// DDLTable class.
class DDLTableColumn {
	// The column name (string).
	public $name;
	// The column type (string) -- see ddl.dtd for allowed types.
	public $type;
	// The column size (int), or 0 if not applicable.
	public $size;
	// The column scale (int), or 0 if not applicable.
	public $scale;
	// Whether to allow null values (boolean).
	public $allowNull;
	// Default value (string), or null if none.
	public $defaultValue;
	// System variable name for default value (string), or null if none.
	public $sysVarDefault;
	// Whether to auto-increment this column (boolean).
	public $autoIncrement;
	// Whether to use current connection's time zone; only applies to datetime type (boolean).
	public $useTimeZone;

	public function DDLTableColumn(
		$name,
		$type,
		$size,
		$scale,
		$allowNull,
		$defaultValue = null,
		$sysVarDefault = null,
		$autoIncrement = false,
		$useTimeZone = false) {

		$this->name = $name;
		$this->type = $type;
		$this->size = $size;
		$this->scale = $scale;
		$this->allowNull = $allowNull;
		$this->defaultValue = $defaultValue;
		$this->sysVarDefault = $sysVarDefault;
		$this->autoIncrement = $autoIncrement;
		$this->useTimeZone = $useTimeZone;
	}

	public function isEqualForView($otherColumn) {
		$save_defaultValue = $this->defaultValue;
		$save_sysVarDefault = $this->sysVarDefault;
		$save_autoIncrement = $this->autoIncrement;
		$save_useTimeZone = $this->useTimeZone;

		$this->defaultValue = $otherColumn->defaultValue;
		$this->sysVarDefault = $otherColumn->sysVarDefault;
		$this->autoIncrement = $otherColumn->autoIncrement;
		$this->useTimeZone = $otherColumn->useTimeZone;

		$result = ($this == $otherColumn);

		$this->defaultValue = $save_defaultValue;
		$this->sysVarDefault = $save_sysVarDefault;
		$this->autoIncrement = $save_autoIncrement;
		$this->useTimeZone = $save_useTimeZone;

		return $result;
	}
} // DDLTableColumn

// The DDLPrimaryKey class represents the primary key within a DDLTable instance.
// The $primaryKey member variable of the DDLTable class can be either a DDLPrimaryKey instance,
// or false if there is no primary key on the table.
class DDLPrimaryKey {
	// Array of DDLKeyColumn instances.
	public $columns;

	public function DDLPrimaryKey($columns = array()) {
		$this->columns = $columns;
	}

	// Returns the index in $columns for the column whose name equals $columnName,
	// or -1 if not found.
	public function getColumnIdx($columnName) {
		for ($i = 0, $n = count($this->columns); $i < $n; $i++) {
			if ($this->columns[$i]->name == $columnName) {
				return $i;
			}
		}
		return -1;
	}
} // DDLPrimaryKey

// The DDLForeignKeyColumn represents a single column within a DDLForeignKey instance.
// DDLForeignKeyColumn instances are members of the $columns array member variable of the
// DDLForeignKey class.
class DDLForeignKeyColumn {
	// The name of the referring column in the local table.
	public $localName;
	// The name of the referenced column in the foreign table.
	public $foreignName;

	public function DDLForeignKeyColumn($localName, $foreignName) {
		$this->localName = $localName;
		$this->foreignName = $foreignName;
	}
} // DDLForeignKeyColumn

// The DDLKeyColumn represents a single column within a DDLIndex or a DDLPrimaryKey instance.
// DDLKeyColumn instances are members of the $columns array member variable of the
// DDLIndex or DDLPrimaryKey class.
class DDLKeyColumn {
	// The name of the column.
	public $name;

	public function DDLKeyColumn($name) {
		$this->name = $name;
	}
} // DDLKeyColumn

// The DDLInsertColumn represents a single column and its value within a DDLInsert instance.
// DDLInsertColumn instances are members of the $columns array member variable of the
// DDLInsert class.
class DDLInsertColumn {
	// The name of the column.
	public $name;
	// The value (string), or false if none.
	public $value;
	// The filename from which to read the value for inserting, or false if none.
	public $filename;
	// The system variable name for the value (string), or false if none.
	public $sysVarValue;
	// Whether to quote value; only applies when value is set and sysVarValue is false (boolean).
	public $quoted;

	public function DDLInsertColumn($name, $value, $filename = false, $sysVarValue = false, $quoted = false) {
		$this->name = $name;
		$this->value = $value;
		$this->filename = $filename;
		$this->sysVarValue = $sysVarValue;
		$this->quoted = $quoted;
	}

	public function getValueOrFileValue($basepath = '') {
		if ($this->filename !== false) {
			$fn = $this->filename;
			if (($basepath != '') && (($fn == '') || ($fn[0] != '/'))) $fn = $basepath.'/'.$fn;
			if (!file_exists($fn)) {
				fprintf(STDERR, "WARNING: Cannot find insert filename: %s\n", $fn);
				return '';
			}
			return file_get_contents($fn);
		} else if ($this->value !== false) {
			return $this->value;
		}
	}
} // DDLInsertColumn






// The ConnectionDDLLoader class loads DDL from a database, and returns a DDL instance which
// represents the DDL of the qualifying tables.
class ConnectionDDLLoader {
	// Load a DDL instance from a Connection, using the given database dialect.
	// Note: This function uses the class of $connection to determine the SQL dialect.
	// Currently only MySQLConnection or MySQLiConnection (mysql dialect) is supported.
	// All other connection types will throw an exception.
	// Parameters:
	// $d: The open database Connection instance.
	// $generateInserts: true to generate DDLInsert instances to populate the tables with the data
	// which is currently in the tables; false to exclude DDLInsert instances.
	// Optional.  Defaults to false.
	// $allowedTableNames: An array of table names which are allowed to be processed, or an empty
	// array to allow all tables.  Optional.  Defaults to an empty array.
	// $dbname: An optional database name.  If omitted or empty, defaults to the current database.
	// Returns: A DDL instance.
	public function loadDDL($db, $generateInserts = false, $allowedTableNames = array(), $dbname = '') {

		$dialect = $db->getDialect();
		if (!in_array($dialect, DDL::$SUPPORTED_DIALECTS)) {
			throw new Exception(sprintf("Unsupported SQL dialect: %s in Connection subclass: %s\n", $dialect, get_class($db)));
		}
		$ddl = new DDL();

		switch ($dialect) {
		case 'mysql':
			$dbnameenc = ($dbname != '') ? $db->encode($dbname) : 'database()';
			$dbnamepfx = ($dbname != '') ? $dbname.'.' : '';

			// Pre-fetch all foreign keys for this database, because this query is slow.
			$__allMySQLFKRows = $db->fetchAllObjects(
				$db->executeQuery(new PreparedStatement(<<<EOF
select
  b.constraint_name as foreignKeyName,
  b.table_name as localTableName,
  case when b.referenced_table_schema is not null and b.referenced_table_schema <> $dbnameenc then
	  concat(b.referenced_table_schema, '.', b.referenced_table_name)
	else b.referenced_table_name end as foreignTableName,
  b.column_name as col_localName,
  b.referenced_column_name as col_foreignName
from information_schema.table_constraints a
inner join information_schema.key_column_usage b
  on a.constraint_name = b.constraint_name
    and b.table_schema = $dbnameenc
where a.table_schema = $dbnameenc
  and b.table_schema = $dbnameenc
  and a.constraint_type = 'FOREIGN KEY'
order by b.table_name, b.constraint_name, ordinal_position
EOF
					, 0, 0, true
				)),
				true
			);
			$mysqlFKs = array();
			$__fk = false;
			foreach ($__allMySQLFKRows as $__fkrow) {
				if (($__fk === false) ||
					($__fkrow->foreignKeyName != $__fk->foreignKeyName) ||
					($__fkrow->localTableName != $__fk->localTableName) ||
					($__fkrow->foreignTableName != $__fk->foreignTableName)) {
					if ($__fk !== false) $mysqlFKs[] = $__fk;
					$__fk = new DDLForeignKey(
						$__fkrow->foreignKeyName,
						$__fkrow->localTableName,
						$__fkrow->foreignTableName
					);
				}
				$__fk->columns[] = new DDLForeignKeyColumn(
					$__fkrow->col_localName,
					$__fkrow->col_foreignName
				);
			}
			if ($__fk !== false) $mysqlFKs[] = $__fk;
			unset($__fk);
			unset($__allMySQLFKRows);
			unset($__fkrow);
			break;
		case 'pgsql':
			$dbnameenc = ($dbname != '') ? $db->encode($dbname) : 'current_schema()';
			$dbnamepfx = ($dbname != '') ? $dbname.'.' : '';
			break;
		} // if ($dialect == 'mysql')

		// Get table names.
		$tableNames = array();
		switch ($dialect) {
		case 'mysql':
			$ps = new PreparedStatement('show tables'.(($dbname != '') ? ' in '.$dbname : ''), 0, 0, true);
			$tableRows = $db->fetchAllObjects($db->executeQuery($ps), true);
			if (!empty($tableRows)) {
				$colname = '';
				foreach (array_keys((array)($tableRows[0])) as $cn) {
					if (strncasecmp($cn, 'tables_in_', 10) == 0) {
						$colname = $cn;
						break;
					}
				}
				if ($colname == '') {
					throw new Exception("Cannot decipher result set of MySQL \"show tables\" command.");
				}
				foreach ($tableRows as $tableRow) {
					$tableNames[] = $tableRow->$colname;
				}
				unset($tableRow);
				unset($colname);
			}
			unset($tableRows);
			break;
		case 'pgsql':
			$ps = new PreparedStatement("select table_name from information_schema.tables where table_type = 'BASE TABLE' and table_catalog = current_database() and table_schema = $dbnameenc order by table_name", 0, 0, true);
			$tableRows = $db->fetchAllObjects($db->executeQuery($ps), true);
			foreach ($tableRows as $tableRow) {
				$tableNames[] = $tableRow->table_name;
			}
			unset($tableRow);
			unset($tableRows);
			break;
		}	// switch ($dialect)
		if (!empty($allowedTableNames)) {
			$ntns = array();
			foreach ($allowedTableNames as $tn) {
				if (in_array($tn, $tableNames)) {
					$ntns[] = $tn;
				}
			}
			$tableNames = $ntns;
			unset($ntns);
			unset($tn);
		}
		$tableNames = array_unique($tableNames);

		// Process all tables which qualify.
		foreach ($tableNames as $tableName) {
			$tableNameEnc = $db->encode($tableName);

			$table = new DDLTable($tableName);
			$tableIndexes = array();
			switch ($dialect) {
			case 'mysql':
				$ps = new PreparedStatement('desc '.$dbnamepfx.$tableName);
				try {
					$rs = $db->executeQuery($ps);
				} catch (Exception $ex) {
					// If we catch an exception, it may be because we tried to describe a view
					// which has a column which doesn't exist in the target table.
					$ps = new PreparedStatement('show create view '.$dbnamepfx.$tableName);
					try {
						$db->fetchObject($db->executeQuery($ps), true);
					} catch (Exception $ex2) {
						// Not a view.  Re-throw the original exception.
						throw $ex;
					}
					// Don't try to load this table.  It's a broken vew.  We need to re-create it, so don't load it.
					break;
				}
				while (($tc = $db->fetchObject($rs)) !== false) {
					$name = $tc->Field;
					$size = 0;
					$scale = 0;
					$allowNull = (strcasecmp($tc->Null, 'yes') == 0) ? true : false;
					$defaultValue = null;
					$sysVarDefault = null;
					$autoIncrement = false;
					$useTimeZone = false;

					$tp = strtolower($tc->Type);
					if (strncmp($tp, 'int', 3) == 0) {
						$type = 'integer';
					} else if ((strncmp($tp, 'tinyint', 7) == 0) ||
								(strncmp($tp, 'smallint', 8) == 0)) {
						$type = 'smallint';
					} else if ((strncmp($tp, 'mediumint', 9) == 0) ||
								(strncmp($tp, 'bigint', 6) == 0)) {
						$type = 'bigint';
					} else if ((strncmp($tp, 'dec', 3) == 0) ||
								(strncmp($tp, 'numeric', 7) == 0) ||
								(strncmp($tp, 'float', 5) == 0) ||
								(strncmp($tp, 'double', 6) == 0) ||
								(strncmp($tp, 'real', 4) == 0)) {
						$type = 'decimal';
						$size = $this->__mysql_parseSize($tp);
						$scale = $this->__mysql_parseScale($tp);
					} else if (strncmp($tp, 'char', 4) == 0) {
						$type = 'char';
						$size = $this->__mysql_parseSize($tp);
					} else if (strncmp($tp, 'varchar', 7) == 0) {
						$type = 'varchar';
						$size = $this->__mysql_parseSize($tp);
					} else if (strncmp($tp, 'binary', 6) == 0) {
						$type = 'binary';
						$size = $this->__mysql_parseSize($tp);
					} else if (strncmp($tp, 'varbinary', 9) == 0) {
						$type = 'varbinary';
						$size = $this->__mysql_parseSize($tp);
					} else if ((strncmp($tp, 'text', 4) == 0) ||
								(strncasecmp($tp, 'tinytext', 8) == 0) ||
								(strncasecmp($tp, 'mediumtext', 10) == 0) ||
								(strncasecmp($tp, 'longtext', 8) == 0)) {
						$type = 'text';
					} else if ((strncmp($tp, 'blob', 4) == 0) ||
								(strncmp($tp, 'tinyblob', 8) == 0) ||
								(strncmp($tp, 'mediumblob', 10) == 0) ||
								(strncmp($tp, 'longblob', 8) == 0)) {
						$type = 'blob';
					} else if ($tp == 'date') {
						$type = 'date';
					} else if ($tp == 'time') {
						$type = 'time';
					} else if ($tp == 'datetime') {
						$type = 'datetime';
					} else if ($tp == 'timestamp') {
						$type = 'datetime';
						$useTimeZone = true;
					} else {
						// Default all unrecognized types to text.
						$type = 'text';
					}

					if (stripos($tc->Extra, 'auto_increment') !== false) {
						$autoIncrement = true;
					} else if ($tc->Default === null) {
						if (!$allowNull) {
							switch ($type) {
							case 'integer':
							case 'smallint':
							case 'bigint':
								$defaultValue = 0;
								break;
							case 'decimal':
								$defaultValue = 0.0;
								break;
							default:
								$defaultValue = '';
								break;
							}
						}
					} else if ($tc->Default == 'CURRENT_TIMESTAMP') {
						$sysVarDefault = 'CURRENT_TIMESTAMP';
					} else {
						$defaultValue = $tc->Default;
						// For date, datetime and time defaults which are all zeros,
						// take the default down to an empty string.
						if ((($type == 'date') && ($defaultValue == '0000-00-00')) ||
							(($type == 'date') && ($defaultValue == '0001-01-01')) ||
							(($type == 'datetime') && ($defaultValue == '0000-00-00 00:00:00')) ||
							(($type == 'datetime') && ($defaultValue == '0001-01-01 00:00:00')) ||
							(($type == 'time') && ($defaultValue == '00:00:00'))) {
							$defaultValue = '';
						}
					}

					$table->columns[] = new DDLTableColumn(
						$name,
						$type,
						$size,
						$scale,
						$allowNull,
						$defaultValue,
						$sysVarDefault,
						$autoIncrement,
						$useTimeZone
					);
				}	// while (($tc = $db->fetchObject($rs)) !== false)
				$db->freeResult($rs);
				unset($tc);

				$ps = new PreparedStatement('show indexes from '.$dbnamepfx.$tableName);
				$irs = $db->fetchAllObjects($db->executeQuery($ps), true);
				$idxs = array();
				foreach ($irs as $ir) {
					// For mysql, omit indexes which have the same name as a foreign key.
					$isFKIndex = false;
					foreach ($mysqlFKs as $fk) {
						if (($fk->localTableName == $tableName) &&
							($fk->foreignKeyName == $ir->Key_name)) {
							$isFKIndex = true;
							break;
						}
					}
					if ($isFKIndex) {
						// This index corresponds to a foreign key.  Skip it.
						continue;
					}
					$idxname = $ir->Key_name;
					$colname = $ir->Column_name;
					if (($ir->Sub_part !== null) && ($ir->Sub_part != '')) {
						$colname .= '('.$ir->Sub_part.')';
					}
					if (strcasecmp($idxname, 'primary') == 0) {
						$found = false;
						for ($i = 0, $n = count($idxs); $i < $n; $i++) {
							if ($idxs[$i] instanceof DDLPrimaryKey) {
								$idxs[$i]->columns[] = new DDLKeyColumn($colname);
								$found = true;
								break;
							}
						}
						if (!$found) {
							$idxs[] = new DDLPrimaryKey(array(new DDLKeyColumn($colname)));
						}
					} else {
						$fulltext = (strcasecmp($ir->Index_type, 'fulltext') == 0);

						$found = false;
						for ($i = 0, $n = count($idxs); $i < $n; $i++) {
							if (($idxs[$i] instanceof DDLIndex) &&
								($idxs[$i]->indexName == $idxname)) {
								$idxs[$i]->columns[] = new DDLKeyColumn($colname);
								$found = true;
								break;
							}
						}
						if (!$found) {
							$idxs[] = new DDLIndex(
								$idxname,
								$tableName,
								(((int)$ir->Non_unique) == 0) ? true : false,
								array(new DDLKeyColumn($colname)),
								$fulltext
							);
						}
					}
				}
				unset($irs);
				unset($ir);
				foreach ($idxs as $idx) {
					if ($idx instanceof DDLPrimaryKey) {
						$table->primaryKey = $idx;
					} else if ($idx instanceof DDLIndex) {
						$tableIndexes[] = $idx;
					}
				}
				unset($idx);
				unset($idxs);
				break;

			case 'pgsql':
				$ps = new PreparedStatement("select * from information_schema.columns where table_catalog = $dbnameenc and table_schema = $dbnameenc and table_name = $tableNameEnc order by ordinal_position");
				$rs = $db->executeQuery($ps);
				while (($tc = $db->fetchObject($rs)) !== false) {
					$name = $tc->column_name;
					$size = 0;
					$scale = 0;
					$allowNull = (strcasecmp($tc->is_nullable, 'yes') == 0) ? true : false;
					$defaultValue = null;
					$sysVarDefault = null;
					$autoIncrement = false;
					$useTimeZone = false;

					$tp = strtolower($tc->data_type);
					if (($tp == 'char') || ($tp == 'character')) {
						$type = 'char';
						$size = (int)$tc->character_maximum_length;
					} else if (($tp == 'character varying') || ($tp == 'varchar')) {
						$type = 'varchar';
						$size = (int)$tc->character_maximum_length;
					} else if ($tp == 'text') {
						$type = 'text';
					} else if ($tp == 'bigint') {
						$type = 'bigint';
					} else if (($tp == 'int') || ($tp == 'integer')) {
						$type = 'integer';
					} else if ($tp == 'smallint') {
						$type = 'smallint';
					} else if ($tp == 'serial') {
						$type = 'integer';
						$autoIncrement = true;
					} else if ($tp == 'bigserial') {
						$type = 'bigint';
						$autoIncrement = true;
					} else if (($tp == 'numeric') || ($tp == 'decimal')) {
						$type = 'decimal';
						$size = (int)$tc->numeric_precision;
						$scale = (int)$tc->numeric_scale;
					} else if ($tp == 'date') {
						$type = 'date';
					} else if ($tp == 'time') {
						$type = 'time';
					} else if ($tp == 'timestamp') {
						$type = 'datetime';
					} else if ($tp == 'timestamp with time zone') {
						$type = 'datetime';
						$useTimeZone = true;
					} else if ($tp == 'bytea') {
						$type = 'blob';
					} else {
						throw new Exception("Unsupported PostgreSQL type ($tp) in table $tableName");
					}

					$dv = $tc->column_default;
					if ($dv === null) {
						if (!$allowNull) {
							switch ($type) {
							case 'integer':
							case 'smallint':
							case 'bigint':
								$defaultValue = 0;
								break;
							case 'decimal':
								$defaultValue = 0.0;
								break;
							default:
								$defaultValue = '';
								break;
							}
						}
					} else {	// if ($dv === null)
						// Some column defaults are suffixed with '::' followed by the data type.
						// Strip those suffixes off.
						$testsuffix = '::'.$tc->data_type;
						if ((($suffixpos = strrpos($dv, $testsuffix)) !== false) &&
							(($suffixpos+strlen($testsuffix)) == strlen($dv))) {
							$dv = substr($dv, 0, $suffixpos);
						}
						if (stripos($dv, 'nextval') === 0) {
							$autoIncrement = true;
						} else if (($dv == 'now()') || (strcasecmp($dv, 'CURRENT_TIMESTAMP') == 0)) {
							$sysVarDefault = 'CURRENT_TIMESTAMP';
						} else {
							if ((strlen($dv) >= 2) && ($dv[0] == '\'') && ($dv[strlen($dv)-1] == '\'')) {
								// Strip leading/trailing quotes; replace all instances of '' with '.
								$dv = str_replace("''", "'", substr($dv, 1, strlen($dv)-2));
							}
							$defaultValue = $dv;
							// For date, datetime and time defaults which are all zeros,
							// take the default down to an empty string.
							if ((($type == 'date') && ($defaultValue == '0000-00-00')) ||
								(($type == 'date') && ($defaultValue == '0001-01-01')) ||
								(($type == 'datetime') && ($defaultValue == '0000-00-00 00:00:00')) ||
								(($type == 'datetime') && ($defaultValue == '0001-01-01 00:00:00')) ||
								(($type == 'time') && ($defaultValue == '00:00:00'))) {
								$defaultValue = '';
							}
						}
					}	// if ($dv === null) ... else

					$table->columns[] = new DDLTableColumn(
						$name,
						$type,
						$size,
						$scale,
						$allowNull,
						$defaultValue,
						$sysVarDefault,
						$autoIncrement,
						$useTimeZone
					);
				}	// while (($tc = $db->fetchObject($rs)) !== false)
				$db->freeResult($rs);
				unset($tc);

				$ps = new PreparedStatement("select pg_class.relname, pg_index.indrelid, pg_index.indkey, pg_index.indisunique, pg_index.indisprimary from pg_class, pg_index where pg_class.oid = pg_index.indexrelid and pg_class.oid in (select indexrelid from pg_index, pg_class where pg_class.relname = $tableNameEnc and pg_class.oid = pg_index.indrelid and pg_index.indrelid in (select c.oid from pg_class c inner join pg_namespace n on n.oid = c.relnamespace where n.nspname = $dbnameenc and c.relname = $tableNameEnc))", 0, 0, true);
				$irs = $db->fetchAllObjects($db->executeQuery($ps), true);
				foreach ($irs as $ir) {
					$idxname = $ir->relname;
					if ($ir->indisprimary == 't') {
						$idx = new DDLPrimaryKey();
					} else {
						$idx = new DDLIndex(
							$idxname,
							$tableName,
							($ir->indisunique == 't') ? true : false,
							false		// no support for fulltext indexes under pgsql yet
						);
					}

					foreach (explode(' ', $ir->indkey) as $indkey) {
						$ps = new PreparedStatement("select a.attname from pg_index c left join pg_class t on c.indrelid = t.oid left join pg_attribute a on a.attrelid = t.oid and a.attnum = any(indkey) where c.indrelid = {$ir->indrelid} and t.relname = $tableNameEnc and a.attnum = $indkey", 0, 0, true);
						$crs = $db->fetchAllObjects($db->executeQuery($ps), true);
						foreach ($crs as $cr) {
							$idx->columns[] = new DDLKeyColumn($cr->attname);
						}
					}

					if ($ir->indisprimary == 't') {
						$table->primaryKey = $idx;
					} else {
						$tableIndexes[] = $idx;
					}
				}
				break;
			}	// switch ($dialect)
			$ddl->topLevelEntities[] = $table;
			if (!empty($tableIndexes)) {
				$ddl->topLevelEntities = array_merge($ddl->topLevelEntities, $tableIndexes);
			}

			// Get foreign keys for this table.
			switch ($dialect) {
			case 'mysql':
				foreach ($mysqlFKs as $fk) {
					if ($fk->localTableName == $tableName) {
						$ddl->topLevelEntities[] = $fk;
					}
				}
				break;
			case 'pgsql':
				$ps = new PreparedStatement(<<<EOF
select constraint_name
from information_schema.table_constraints
where table_schema = $dbnameenc table_name = '$tableName' and constraint_type = 'FOREIGN KEY'
EOF
					, 0, 0, true
				);
				$fkrs = $db->fetchAllObjects($db->executeQuery($ps), true);
				foreach ($fkrs as $fkr) {
					$fkname = $fkr->constraint_name;
					$ps = new PreparedStatement(<<<EOF
select
 tc.constraint_name,
 tc.constraint_type,
 tc.table_name,
 kcu.column_name,
 case when ccu.table_catalog = current_database() and ccu.table_schema is not null and ccu.table_schema <> '' and ccu.table_schema <> $dbnameenc then
   ccu.table_schema||'.'||ccu.table_name else ccu.table_name end as references_table,
 ccu.column_name as references_field
from information_schema.table_constraints tc
left join information_schema.key_column_usage kcu
 on tc.constraint_catalog = kcu.constraint_catalog
 and tc.constraint_schema = kcu.constraint_schema
 and tc.constraint_name = kcu.constraint_name
left join information_schema.referential_constraints rc
 on tc.constraint_catalog = rc.constraint_catalog
 and tc.constraint_schema = rc.constraint_schema
 and tc.constraint_name = rc.constraint_name
left join information_schema.constraint_column_usage ccu
 on rc.constraint_catalog = ccu.constraint_catalog
 and rc.constraint_schema = ccu.constraint_schema
 and rc.constraint_name = ccu.constraint_name
where tc.table_name = '$tableName' and tc.constraint_name = '$fkname'
EOF
					);
					$fkcs = $db->fetchAllObjects($db->executeQuery($ps), true);
					$fk = false;
					foreach ($fkcs as $fkc) {
						if ($fk === false) {
							$fk = new DDLForeignKey(
								$fkname,
								$tableName,
								$fkc->references_table
							);
						}
						$fk->columns[] = new DDLForeignKeyColumn($fkc->column_name, $fkc->references_field);
					}
					if ($fk !== false) $ddl->topLevelEntities[] = $fk;
				}
				break;
			}	// switch ($dialect)

			if ($generateInserts) {
				$sql = 'select * from '.$dbnamepfx.$tableName;
				if ($table->primaryKey !== false) {
					$sql .= ' order by ';
					$sep = '';
					foreach ($table->primaryKey->columns as $col) {
						$sql .= $sep.$col->name;
						if ($sep == '') $sep = ', ';
					}
				}
				$ps = new PreparedStatement($sql);
				$rs = $db->executeQuery($ps);
				while (($row = $db->fetchObject($rs)) !== false) {
					$insert = new DDLInsert($tableName);
					foreach ($row as $key=>$val) {
						$quoted = true;
						$colidx = $table->getColumnIdx($key);
						if ($colidx >= 0) {
							switch ($table->columns[$colidx]->type) {
							case 'integer':
							case 'smallint':
							case 'bigint':
							case 'decimal':
								$quoted = false;
								break;
							default:
								$quoted = true;
								break;
							}
						}
						$inscol = new DDLInsertColumn(
							$key,		// name
							$val,		// value
							false,		// value
							false,		// sysVarValue
							$quoted		// quoted
						);
						$insert->columns[] = $inscol;
					}
					unset($key);
					unset($val);

					$ddl->topLevelEntities[] = $insert;
				}
				$db->freeResult($rs);
			}

			unset($table);	// release reference to array element
		}	// foreach ($tableNames as $tableName)

		return $ddl;
	} // loadDDL()

	// Determine whether a database exists.
	// Returns true if the specified database (schema in pgsql dialect) exists; false if not.
	public static function doesDatabaseExist($db, $dbName) {
		switch ($db->getDialect()) {
		case 'mysql':
			$ps = new PreparedStatement('select SCHEMA_NAME from information_schema.SCHEMATA where SCHEMA_NAME = ?', 0, 1);
			$ps->setString($dbName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		case 'pgsql':
			$ps = new PreparedStatement('select schema_name from information_schema.schemata where catalog_name = current_database() and schema_name = ?', 0, 1);
			$ps->setString($dbName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		}
	} // doesDatabaseExist()

	// Determine whether a table exists.
	// Returns true if the specified table exists; false if not.
	public static function doesTableExist($db, $tableName, $dbName = null) {
		switch ($db->getDialect()) {
		case 'mysql':
			if (($dbName !== null) && ($dbName != '')) {
				$ps = new PreparedStatement('select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_NAME = ?', 0, 1);
				$ps->setString($dbName);
			} else {
				$ps = new PreparedStatement('select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = database() and TABLE_NAME = ?', 0, 1);
			}
			$ps->setString($tableName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		case 'pgsql':
			if (($dbName !== null) && ($dbName != '')) {
				$ps = new PreparedStatement("select table_name from information_schema.tables where table_type = 'BASE TABLE' and table_schema not in ('pg_catalog', 'information_schema') and table_catalog = current_database() and table_schema = ? and table_name = ?", 0, 1);
				$ps->setString($dbName);
			} else {
				$ps = new PreparedStatement("select table_name from information_schema.tables where table_type = 'BASE TABLE' and table_schema not in ('pg_catalog', 'information_schema') and table_catalog = current_database() and table_schema = current_schema() and table_name = ?", 0, 1);
			}
			$ps->setString($tableName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		}
	} // doesTableExist()

	// Given a table name and optional database name, returns true if the table is a view, or false if not.
	public static function isView($db, $tableName, $dbName = '') {
		switch ($db->getDialect()) {
		case 'mysql':
			$dbNamePlaceholder = ($dbName != '') ? '?' : 'database()';
			$ps = new PreparedStatement("select TABLE_NAME from information_schema.VIEWS where TABLE_SCHEMA = $dbNamePlaceholder and TABLE_NAME = ?");
			if ($dbName != '') $ps->setString($dbName);
			$ps->setString($tableName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		case 'pgsql':
			$dbNamePlaceholder = ($dbName != '') ? '?' : 'current_schema()';
			$ps = new PreparedStatement("select viewname from pg_views where schemaname = $dbNamePlaceholder and viewname = ?");
			if ($dbName != '') $ps->setString($dbName);
			$ps->setString($tableName);
			return ($db->fetchObject($db->executeQuery($ps), true) !== false);
		}
	} // isView()

	protected function __mysql_parseSize($type) {
		if (($idx1 = strpos($type, '(')) === false) return 0;
		$idx1++;
		if (($idx2 = strpos($type, ')', $idx1)) === false) return 0;
		$pieces = explode(',', substr($type, $idx1, $idx2-$idx1));
		if (!empty($pieces)) return (int)trim($pieces[0]);
		return 0;
	} // __mysql_parseSize()

	protected function __mysql_parseScale($type) {
		if (($idx1 = strpos($type, '(')) === false) return 0;
		$idx1++;
		if (($idx2 = strpos($type, ')', $idx1)) === false) return 0;
		$pieces = explode(',', substr($type, $idx1, $idx2-$idx1));
		if (isset($pieces[1])) return (int)trim($pieces[1]);
		return 0;
	} // __mysql_parseScale()
} // ConnectionDDLLoader






// The YAMLDDLParser class parses a YAML document, and returns a DDL
// instance which represents the DDL described in the YAML document.
class YAMLDDLParser {
	// Parse and return a DDL instance from the text of a YAML document.
	// Parameters:
	// $allowedTableNames: An array of table names which are allowed to be processed, or an empty
	// array to allow all tables.  Optional.  Defaults to an empty array.
	// $group: An optional group name (the base name of the YAML file without any path name
	//   or file type extension).  Optional. Defaults to null.
	public function parseFromYAML($yaml, $allowedTableNames = array(), $group = null) {
		$ddl = new DDL();

		$cfg = @Spyc::YAMLLoadString($yaml);
		if (!is_array($cfg)) $cfg = array();

		if (isset($cfg['tables']) && is_array($cfg['tables'])) {
			foreach ($cfg['tables'] as $tableName=>$tblAttrs) {
				$tblSection = "tables => $tableName";
				$this->__allowOnlyAttrs($tblSection, $tblAttrs, array('columns', 'primaryKey', 'indexes', 'foreignKeys', 'inserts'));
				if ((!empty($allowedTableNames)) && (!in_array($tableName, $allowedTableNames))) {
					continue;
				}
				$autoIncColName = '';
				unset($ddlTable);
				$ddlTable = new DDLTable($tableName);
				if (isset($tblAttrs['columns']) && is_array($tblAttrs['columns'])) {
					$colSection = "tables => $tableName => columns";
					foreach ($tblAttrs['columns'] as $columnName=>$colAttrs) {
						$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'size', 'scale', 'null', 'default', 'defaultIsBase64Encoded', 'sysVarDefault', 'autoIncrement', 'useTimeZone'));
						$this->__requireAttrs($colSection, $colAttrs, array('type'));
						switch ($colAttrs['type']) {
						case 'integer':
						case 'smallint':
						case 'bigint':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'null', 'default', 'defaultIsBase64Encoded', 'autoIncrement'));
							break;
						case 'decimal':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'size', 'scale', 'null', 'default', 'defaultIsBase64Encoded'));
							$this->__requireAttrs($colSection, $colAttrs, array('size', 'scale'));
							break;
						case 'char':
						case 'varchar':
						case 'binary':
						case 'varbinary':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'size', 'null', 'default', 'defaultIsBase64Encoded'));
							$this->__requireAttrs($colSection, $colAttrs, array('size'));
							break;
						case 'text':
						case 'blob':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'null', 'default', 'defaultIsBase64Encoded'));
							break;
						case 'date':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'null', 'default', 'defaultIsBase64Encoded', 'sysVarDefault'));
							break;
						case 'time':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'null', 'default', 'defaultIsBase64Encoded', 'sysVarDefault'));
							break;
						case 'datetime':
							$this->__allowOnlyAttrs($colSection, $colAttrs, array('type', 'null', 'default', 'defaultIsBase64Encoded', 'sysVarDefault', 'useTimeZone'));
							break;
						default:
							throw new Exception(sprintf("Invalid column type: %s", $colAttrs['type']));
							break;
						}
						if (isset($colAttrs['autoIncrement'])) {
							if (isset($colAttrs['sysVarDefault'])) {
								throw new Exception(
									"Cannot have both autoIncrement and sysVarDefault".
										" attributes on a column."
								);
							}
							if (isset($colAttrs['default'])) {
								throw new Exception(
									"Cannot have both autoIncrement and default".
										" attributes on a column."
								);
							}
							if ($autoIncColName != '') {
								throw new Exception(sprintf(
									"Cannot have more than one autoIncrement column per table".
										" in table name \"%s\"",
									$tableName
								));
							}
							$autoIncColName = $columnName;
						} else if (isset($colAttrs['sysVarDefault'])) {
							if (isset($colAttrs['default'])) {
								throw new Exception(
									"Cannot have both sysVarDefault and default".
										" attributes on a column."
								);
							}
						}

						$type = $colAttrs['type'];
						$allowNull = (isset($colAttrs['null']) && (!$colAttrs['null'])) ? false : true;
						$defaultValue = isset($colAttrs['default']) ? $colAttrs['default'] : null;
						if ($defaultValue !== null) {
							// Base64-decode the default value if needed.
							if (isset($colAttrs['defaultIsBase64Encoded']) &&
								$colAttrs['defaultIsBase64Encoded']) {
								$defaultValue = base64_decode($defaultValue);
							}
							// For date, datetime and time defaults which are all zeros,
							// take the default down to an empty string.
							if ((($type == 'date') && ($defaultValue == '0000-00-00')) ||
								(($type == 'date') && ($defaultValue == '0001-01-01')) ||
								(($type == 'datetime') && ($defaultValue == '0000-00-00 00:00:00')) ||
								(($type == 'datetime') && ($defaultValue == '0001-01-01 00:00:00')) ||
								(($type == 'time') && ($defaultValue == '00:00:00'))) {
								$defaultValue = '';
							}
						} else {
							if (!$allowNull) {
								switch ($type) {
								case 'integer':
								case 'smallint':
								case 'bigint':
									$defaultValue = 0;
									break;
								case 'decimal':
									$defaultValue = 0.0;
									break;
								default:
									$defaultValue = '';
									break;
								}
							}
						}

						// Only save tables which contain one or more columns.
						// Save a table when we encounter the first column definition for
						// that table.
						// Tables with no columns can be used for things like inserts within
						// schema files other than those in which the tables are defined.
						if (empty($ddlTable->columns)) {
							$ddl->topLevelEntities[] = &$ddlTable;
						}

						$ddlTable->columns[] = new DDLTableColumn(
							$columnName,
							$type,
							isset($colAttrs['size']) ? (int)$colAttrs['size'] : 0,
							isset($colAttrs['scale']) ? (int)$colAttrs['scale'] : 0,
							$allowNull,
							$defaultValue,
							isset($colAttrs['sysVarDefault']) ? $colAttrs['sysVarDefault'] : null,
							(isset($colAttrs['autoIncrement']) && ($colAttrs['autoIncrement'])) ?
								true : false,
							(isset($colAttrs['useTimeZone']) && ($colAttrs['useTimeZone'])) ?
								true : false
						);
					}	// foreach ($tblAttrs['columns'] as $columnName=>$colAttrs)
				}	// if (isset($tblAttrs['columns']) && is_array($tblAttrs['columns']))

				if (isset($tblAttrs['primaryKey']) && is_array($tblAttrs['primaryKey'])) {
					$pkSection = "tables => $tableName => primaryKey";
					$this->__allowOnlyAttrs($colSection, $tblAttrs['primaryKey'], array('columns'));
					$ddlTable->primaryKey = new DDLPrimaryKey();
					if (isset($tblAttrs['primaryKey']['columns']) &&
						is_array($tblAttrs['primaryKey']['columns'])) {
						$pkColsSection = "tables => $tableName => primaryKey => columns";
						foreach ($tblAttrs['primaryKey']['columns'] as $columnName) {
							$ddlTable->primaryKey->columns[] = new DDLKeyColumn($columnName);
						}
					}
				}

				if (isset($tblAttrs['indexes']) && is_array($tblAttrs['indexes'])) {
					foreach ($tblAttrs['indexes'] as $indexName=>$indexAttrs) {
						// Make sure index name begins with table name and underscore.
						if (strpos($indexName, $tableName.'_') !== 0) {
							$indexName = $tableName.'_'.$indexName;
						}

						$indexSection = "tables => $tableName => indexes => $indexName";
						$this->__allowOnlyAttrs($indexSection, $indexAttrs, array('unique', 'fulltext', 'columns'));
						unset($ddlIndex);
						$ddlIndex = new DDLIndex(
							$indexName,
							$tableName,
							(isset($indexAttrs['unique']) && ($indexAttrs['unique'])) ? true : false,
							array(),
							(isset($indexAttrs['fulltext']) && ($indexAttrs['fulltext'])) ? true : false
						);
						$ddl->topLevelEntities[] = &$ddlIndex;
						if (isset($indexAttrs['columns']) && is_array($indexAttrs['columns'])) {
							foreach ($indexAttrs['columns'] as $columnName) {
								$ddlIndex->columns[] = new DDLKeyColumn($columnName);
							}
						}
					}
				}

				if (isset($tblAttrs['foreignKeys']) && is_array($tblAttrs['foreignKeys'])) {
					foreach ($tblAttrs['foreignKeys'] as $fkName=>$fkAttrs) {
						$fkSection = "tables => $tableName => foreignKeys => $fkName";
						$this->__allowOnlyAttrs($fkSection, $fkAttrs, array('foreignTable', 'columns'));
						$this->__requireAttrs($fkSection, $fkAttrs, array('foreignTable'));
/// TODO: Verify that the foreign key name hasn't been already created.

						$referencedTableName = $fkAttrs['foreignTable'];

						$ddlForeignKey = new DDLForeignKey(
							$fkName,
							$tableName,
							$referencedTableName
						);
						if (isset($fkAttrs['columns']) && is_array($fkAttrs['columns'])) {
							foreach ($fkAttrs['columns'] as $colSec=>$columnAttrs) {
								$columnSection = "tables => $tableName => foreignKeys => $fkName => columns => $colSec";
								$this->__allowOnlyAttrs($columnSection, $columnAttrs, array('local', 'foreign'));
								$this->__requireAttrs($columnSection, $columnAttrs, array('local', 'foreign'));
								$ddlForeignKey->columns[] = new DDLForeignKeyColumn($columnAttrs['local'], $columnAttrs['foreign']);
							}
						}
						$ddl->topLevelEntities[] = $ddlForeignKey;
					}
				}

				if (isset($tblAttrs['inserts']) && is_array($tblAttrs['inserts'])) {
					foreach ($tblAttrs['inserts'] as $insertName=>$insertAttrs) {
						unset($ddlInsert);
						$keyColumnNames =
							(isset($insertAttrs['keyColumnNames']) &&
							is_array($insertAttrs['keyColumnNames'])) ?
							$insertAttrs['keyColumnNames'] : array();
						$ddlInsert = new DDLInsert(
							$tableName,
							array(),
							$keyColumnNames,
							((count($keyColumnNames) > 0) &&
								isset($insertAttrs['updateIfExists']) &&
								($insertAttrs['updateIfExists'])) ?
									true : false
						);
						$ddl->topLevelEntities[] = &$ddlInsert;
						foreach ($insertAttrs as $columnName=>$columnAttrs) {
							if (($columnName == 'keyColumnNames') ||
								($columnName == 'updateIfExists')) {
								continue;
							}

							$columnSection = "tables => $tableName => inserts => $insertName => $columnName";
							$this->__allowOnlyAttrs($columnSection, $columnAttrs, array('valueIsBase64Encoded', 'value', 'sysVarValue', 'filename', 'quoted'));
							$this->__requireOnlyOneAttr($columnSection, $columnAttrs, array('value', 'sysVarValue', 'filename'));
							if (isset($columnAttrs['sysVarValue'])) {
								if (isset($columnAttrs['quoted'])) {
									throw new Exception(sprintf(
										"Cannot have both sysVarValue and quoted in \"%s\" section.",
										$columnSection
									));
								}
								$ddlInsert->columns[] = new DDLInsertColumn(
									$columnName,					// name
									false,							// value
									false,							// filename
									$columnAttrs['sysVarValue'],	// sysVarValue
									false							// quoted
								);
							} else if (isset($columnAttrs['filename'])) {
								$ddlInsert->columns[] = new DDLInsertColumn(
									$columnName,					// name
									false,							// value
									$columnAttrs['filename'],		// filename
									false,							// sysVarValue
									true							// quoted
								);
							} else {
								$val = isset($columnAttrs['value']) ? $columnAttrs['value'] : null;
								if (($val !== null) &&
									isset($columnAttrs['valueIsBase64Encoded']) &&
									$columnAttrs['valueIsBase64Encoded']) {
									// Base64-decode the value if needed.
									$val = base64_decode($val);
								}
								$ddlInsert->columns[] = new DDLInsertColumn(
									$columnName,					// name
									$val,							// value
									false,							// filename
									false,							// sysVarValue
									(isset($columnAttrs['quoted']) && ($columnAttrs['quoted'])) ? true : false
																	// quoted
								);
							}
						}
					}
				}

				if ($autoIncColName != '') {
					if (($ddlTable->primaryKey === false) || 
						((count($ddlTable->primaryKey->columns) != 1) ||
						($ddlTable->primaryKey->columns[0]->name !=
							$autoIncColName))) {
						throw new Exception(sprintf(
							"Use of autoIncrement requires the primary key to be comprised".
								" of only the autoIncrement column in table \"%s\".",
							$ddlTable->tableName
						));
					}
				}
			}	// foreach ($cfg['tables'] as $tableName=>$tblAttrs)
		}	// if (isset($cfg['tables']) && is_array($cfg['tables']))

		for ($i = 0, $n = count($ddl->topLevelEntities); $i < $n; $i++) {
			if ($ddl->topLevelEntities[$i] instanceof DDLTable) {
				$ddl->topLevelEntities[$i]->group = $group;
			}
		}

		return $ddl;
	}

	protected function __allowOnlyAttrs($sectionName, $attrs, $attrNames) {
		foreach (array_keys($attrs) as $attrName) {
			if (!in_array($attrName, $attrNames)) {
				throw new Exception(sprintf(
					"Unexpected attribute \"%s\" in \"%s\" section.",
					$attrName,
					$sectionName
				));
			}
		}
	}

	protected function __requireAttrs($sectionName, $attrs, $attrNames) {
		foreach ($attrNames as $attrName) {
			if (!isset($attrs[$attrName])) {
				throw new Exception
					(sprintf("Missing attribute \"%s\" in \"%s\" section.", $attrName, $sectionName));
			}
		}
	}

	protected function __requireOnlyOneAttr($sectionName, $attrs, $attrNames) {
		$foundAttrs = array();
		foreach ($attrNames as $attrName) {
			if (array_key_exists($attrName, $attrs)) {
				if (in_array($attrName, $foundAttrs)) {
					throw new Exception(sprintf("Duplicate [%s] attribute in \"%s\" section.", $attrName, $sectionName));
				} else {
					$foundAttrs[] = $attrName;
				}
			}
		}
		if (empty($foundAttrs)) {
			throw new Exception(sprintf(
				"Must specify one of [%s] attributes in \"%s\" section.",
				implode(', ', $attrNames),
				$sectionName
			));
		} else if (count($foundAttrs) > 1) {
			throw new Exception(sprintf(
				"Must specify ONLY ONE of [%s] attributes in \"%s\" section; found: %s.",
				implode(', ', $attrNames),
				$sectionName,
				implode(',', $foundAttrs)
			));
		}
	}

	// Load a YAML DDL file, aggegatin it into an aggregate DDL instance.
	// $ddlFile: The filename of the YAML DDL file to be loaded.
	// $aggregateDDL: A DDL instance to receive the aggregated DDL.
	// $allowedTableNames: An optional array of string table names which are allowed in the
	//   final DDL.  Optional.  Defaults to an empty array.
	public static function loadAndAggregateDDLFile($ddlFile, &$aggregateDDL) {
		if (!file_exists($ddlFile)) {
			fprintf(STDERR, "Cannot find DDL file: %s\n", $ddlFile);
			return 11;
		}

		$result = 12;
		$errorMsg = '';
		try {
			if ((strtolower(substr($ddlFile, -9)) == '.ddl.yaml') ||
				(strtolower(substr($ddlFile, -8)) == '.ddl.yml')) {
				$group = str_replace(array('.ddl.yaml', '.ddl.yml'), '', basename($ddlFile));
				$parser = new YAMLDDLParser();
				$ddl = $parser->parseFromYAML(file_get_contents($ddlFile), array(), $group);
				$aggregateDDL->topLevelEntities = array_merge($aggregateDDL->topLevelEntities, $ddl->topLevelEntities);
				$result = 0;
			} else {
				throw new Exception("Unrecognized file extension (must be .ddl.yaml or .ddl.yml)");
			}
		} catch (Exception $ex) {
			$errorMsg = $ex->getMessage();
			$result = 11;
		}
		if ($result != 0) {
			fprintf(STDERR, "\n\nError processing DDL file: %s\n", $ddlFile);
			if ($errorMsg != '') {
				fprintf(STDERR, "Error message: %s\n", $errorMsg);
			}
		}
		return $result;
	} // loadAndAggregateDDLFile()

	// Load all YAML DDL files found under the specified directory, aggregating them
	// into a single DDL instance.
	// $dir: The directory under which the YAML DDL files exist.
	// $aggregateDDL: The DDL instance to contain the aggregated DDL.
	// $allowedTableNames: An optional array of string table names which are allowed in the
	//   final DDL.  Optional.  Defaults to an empty array.
	// $dbmap: An optional DDLTableToDatabaseMap instance for group- and table-to-database mapping,
	//   or null if none.  If provided, this is used to automatically add the database prefix
	//   to any foreign table names in databases other than the current database, which are
	//   referenced by foreign keys in the curent database.
	//   Optional.  Defaults to null.
	public static function loadAllDDLFiles($dir, &$aggregateDDL, &$allowedTableNames = array(), $dbmap = null) {
		$result = 0;
		if (($dp = @opendir($dir)) !== false) {
			while (($fn = readdir($dp)) !== false) {
				if (($fn == '.') || ($fn == '..')) continue;
				$fn = $dir.'/'.$fn;
				if (is_dir($fn)) {
					$res = self::loadAllDDLFiles($fn, $aggregateDDL, $allowedTableNames);
					if ($result == 0) $result = $res;
				} else if ((substr($fn, -9) == '.ddl.yaml') || (substr($fn, -8) == '.ddl.yml')) {
					$res = self::loadAndAggregateDDLFile($fn, $aggregateDDL);
					if ($result == 0) $result = $res;
				}
			}
			@closedir($dp);
		}

		if ($result != 0) {
			$aggregateDDL->topLevelEntities = array();
			return $result;
		}

		if ($dbmap !== null) {
			// For foreign keys which reference tables in other databases,
			// prepend the foreign table name with the foreign database name
			// and a dot.
			for ($i = 0, $n = count($aggregateDDL->topLevelEntities); $i < $n; $i++) {
				if ($aggregateDDL->topLevelEntities[$i] instanceof DDLForeignKey) {
					$fk = $aggregateDDL->topLevelEntities[$i];
					$tmp = $aggregateDDL->getTableIndexesAndForeignKeys($fk->foreignTableName);
					$dbname = $dbmap->getDatabase($tmp->tbl->group, $fk->foreignTableName);
					if (($dbname !== null) && ($dbname != '')) {
						$fk->foreignTableName = $dbname.'.'.$fk->foreignTableName;
					}
				}
			}
		}

		if (!empty($allowedTableNames)) {
			// Delete any entries which are for tables not listed in $allowedTableNames.
			$anyDeleted = false;
			for ($i = 0, $n = count($aggregateDDL->topLevelEntities); $i < $n; $i++) {
				if (isset($aggregateDDL->topLevelEntities[$i]->tableName) &&
					(!in_array($aggregateDDL->topLevelEntities[$i]->tableName, $allowedTableNames))) {
					unset($aggregateDDL->topLevelEntities[$i]);
					$anyDeleted = true;
					continue;
				}
				if (($aggregateDDL->topLevelEntities[$i] instanceof DDLForeignKey) &&
					(!in_array($aggregateDDL->topLevelEntities[$i]->localTableName, $allowedTableNames))) {
					unset($aggregateDDL->topLevelEntities[$i]);
					$anyDeleted = true;
					continue;
				}
			}
			if ($anyDeleted) $aggregateDDL->topLevelEntities = array_slice($aggregateDDL->topLevelEntities, 0);
		}

		return 0;
	} // loadAllDDLFiles()
} // YAMLDDLParser






// The SQLDDLSerializer class outputs SQL for a specified SQL dialect, given a valid
// DDL instance.
class SQLDDLSerializer {

	// Serialize a DDL object tree, returning SQL for the requested SQL dialect.
	// Parameters:
	// $ddl: A valid DDL instance.
	// $dialect: The SQL dialect to use.  Must be one of the dialects listed in
	// DDL::$SUPPORTED_DIALECTS.  Defaults to 'mysql'.
	// $dbmap: An optional DDLTableToDatabaseMap instance for group- and table-to-database mapping,
	//   or null if none.  Optional.  Defaults to null.
	// $localDBName: An optional local database (or schema) name.  If specified, this will be prefixed,
	// along with a dot, to all local table and view names.
	// $basepath: The base directory for insert filenames with relative paths.
	// Returns an array of strings, where each string is a single SQL statement.
	public function serialize($ddl, $dialect = 'mysql', $dbmap = null, $localDBName = '', $basepath = '') {
		if (!in_array($dialect, DDL::$SUPPORTED_DIALECTS)) {
			throw new Exception(sprintf(
				"Requested SQL dialect \"%s\" is not in the list of supported dialects (%s).",
				$dialect,
				implode(', ',  DDL::$SUPPORTED_DIALECTS)
			));
		}

		$ldbpfx = ($localDBName != '') ? $localDBName.'.' : '';

		$tablesCreated = array();

		$sqlStatements = array();
		for ($pass = 1; $pass <= 3; $pass++) {
			foreach ($ddl->topLevelEntities as &$tle) {
				if ($tle instanceof DDLTable) {
					// Tables can only be created on the first pass.
					if ($pass != 1) continue;

					$dbname = ($dbmap !== null) ? $dbmap->getDatabase($tle->group, $tle->tableName) : null;
					if (($dbname !== null) && ($dbname != '')) {
						$sqlStatements[] = sprintf("drop table if exists %s", $ldbpfx.$tle->tableName);
						$sqlStatements[] = sprintf("create or replace view %s as select * from %s.%s", $ldbpfx.$tle->tableName, $dbname, $tle->tableName);
						continue;
					}

					// Find auto-increment column; create sequence if needed (depends on dialect).
					$autoIncColName = '';
					foreach ($tle->columns as &$col) {
						if ($col->autoIncrement) {
							if ($autoIncColName != '') {
								throw new Exception(sprintf(
									"Cannot have more than one auto-increment column per table".
										" in table name \"%s\"",
									$tle->tableName
								));
							}
							$autoIncColName = $col->name;
						}
					}
					unset($col);	// release reference to last element
					if ($autoIncColName != '') {
						switch ($dialect) {
						case 'pgsql':
							$sqlStatements[] = sprintf("drop sequence if exists %s_autoInc_seq", $ldbpfx.$tle->tableName);
							$sqlStatements[] = sprintf("create sequence %s_autoInc_seq", $ldbpfx.$tle->tableName);
							continue;
						}
					}

					$tablesCreated[] = $ldbpfx.$tle->tableName;

					$sqlStatements[] = sprintf('drop view if exists %s', $ldbpfx.$tle->tableName);
					$sql = sprintf("create table %s (", $ldbpfx.$tle->tableName);
					$sep = '';
					foreach ($tle->columns as &$col) {
						$sql .= $sep;
						if ($sep == '') $sep = ', ';
						$sql .= ' '.$this->serializeTableColumn($col, $tle->tableName, $dialect, true, true, true, true, true, $localDBName);
					}
					unset($col);	// release reference to last element
					if ($tle->primaryKey !== false) {
						$sql .= $sep.' primary key (';
						if ($sep == '') $sep = ', ';
						$sep2 = '';
						foreach ($tle->primaryKey->columns as &$col) {
							$sql .= $sep2.$col->name;
							if ($sep2 == '') $sep2 = ', ';
						}
						unset($col);	// release reference to last element
						$sql .= ")";
					}

					$tmp = $ddl->getTableIndexesAndForeignKeys($tle->tableName);
					foreach ($tmp->idxs as $itle) {
						// Currently, we only support fulltext indexes on mysql, so don't
						// try to create them on any other RDBMS.
						if ((!$itle->fulltext) || ($dialect == 'mysql')) {
							$sql .= sprintf(
								"%s%s index %s (",
								$sep,
								$itle->fulltext ? ' fulltext' : ($itle->unique ? ' unique' : ''),
								$itle->indexName
							);
							if ($sep == '') $sep = ', ';
							$sep2 = '';
							foreach ($itle->columns as &$col) {
								$sql .= $sep2.$col->name;
								if ($sep2 == '') $sep2 = ', ';
							}
							unset($col);	// release reference to last element
							$sql .= ")";
							unset($sep2);
						}
					}

					$sql .= ')';
					if ($dialect == 'mysql') {
						$sql .= " engine=InnoDB character set utf8 collate utf8_general_ci";
					}
					$sqlStatements[] = $sql;
					unset($sql);
				}	// if ($tle instanceof DDLTable)
				else if ($tle instanceof DDLIndex) {
					// Indexes can only be created on the first pass.
					if ($pass != 1) continue;

					// Don't create indexes on views to tables in other databases.
					$tmp = $ddl->getTableIndexesAndForeignKeys($tle->tableName);
					$tbl = $tmp->tbl;
					$dbname = (($dbmap !== null) && ($tbl !== false)) ? $dbmap->getDatabase($tbl->group, $tbl->tableName) : null;
					if (($dbname !== null) && ($dbname != '')) {
						continue;
					}

					// Don't create indexes on tables which we just created; we would have already
					// created the index when we created the table.
					if (in_array($ldbpfx.$tle->tableName, $tablesCreated)) {
						continue;
					}

					// Currently, we only support fulltext indexes on mysql, so don't
					// try to create them on any other RDBMS.
					if ((!$tle->fulltext) || ($dialect == 'mysql')) {
						$sql = sprintf(
							"create%s index %s on %s (",
							$tle->fulltext ? ' fulltext' : ($tle->unique ? ' unique' : ''),
							$tle->indexName,
							$ldbpfx.$tle->tableName
						);
						$sep = '';
						foreach ($tle->columns as &$col) {
							$sql .= $sep.$col->name;
							if ($sep == '') $sep = ', ';
						}
						unset($col);	// release reference to last element
						$sql .= ")";
						$sqlStatements[] = $sql;
						unset($sl);
					}
				}	// else if ($tle instanceof DDLIndex)
				else if ($tle instanceof DDLForeignKey) {
					// Foreign keys can only be created on the second pass, after all tables exist.
					if ($pass != 2) continue;

					// Don't create foreign keys in views to tables in other databases.
					$tmp = $ddl->getTableIndexesAndForeignKeys($tle->localTableName);
					$tbl = $tmp->tbl;
					$dbname = (($dbmap !== null) && ($tbl !== false)) ? $dbmap->getDatabase($tbl->group, $tbl->tableName) : null;
					if (($dbname !== null) && ($dbname != '')) {
						continue;
					}

					$localCols = array();
					$foreignCols = array();
					foreach ($tle->columns as $fkcol) {
						$localCols[] = $fkcol->localName;
						$foreignCols[] = $fkcol->foreignName;
					}
					$sqlStatements[] = sprintf(
						"alter table %s add constraint %s foreign key (%s) references %s (%s)",
						$ldbpfx.$tle->localTableName,
						$tle->foreignKeyName,
						implode(', ', $localCols),
						$ldbpfx.$tle->foreignTableName,
						implode(', ', $foreignCols)
					);
				}	// else if ($tle instanceof DDLForeignKey)
				else if ($tle instanceof DDLInsert) {
					// Inserts can only be created on the third pass, after all tables exist
					// and foreign keys have been created.
					if ($pass != 3) continue;

					$tmp = $ddl->getTableIndexesAndForeignKeys($tle->tableName);
					$tbl = $tmp->tbl;
					$dbname = (($dbmap !== null) && ($tbl !== false)) ? $dbmap->getDatabase($tbl->group, $tbl->tableName) : null;
					if (($dbname !== null) && ($dbname != '')) {
						continue;
					}

					$sqlStatements = array_merge($sqlStatements, $this->serializeInsert($tle, $dialect, null, $localDBName, $basepath));
				}	// else if ($tle instanceof DDLInsert)
			}	// foreach ($ddl->topLevelEntities as &$tle)
			unset($tle);	// release reference to last element
		}	// for ($pass = 1; $pass <= 3; $pass++)
		return $sqlStatements;
	}

	public function serializeTableColumn(
		$col,
		$tableName,
		$dialect = 'mysql',
		$includeColumnName = true,
		$includeColumnType = true,
		$includeColumnNull = true,
		$includeColumnDefault = true,
		$includeColumnAutoIncrement = true,
		$localDBName = '') {

		$ldbpfx = ($localDBName != '') ? $localDBName.'.' : '';

		$sql = '';
		if ($includeColumnName) {
			$sql .= $col->name.' ';
		}

		if ($includeColumnType) {
			switch ($col->type) {
			case 'integer':
			case 'smallint':
			case 'bigint':
				$sql .= $col->type;
				break;
			case 'decimal':
				$sql .= sprintf('decimal(%d, %d)', $col->size, $col->scale);
				break;
			case 'char':
			case 'varchar':
			case 'binary':
			case 'varbinary':
				switch ($dialect) {
				case 'mysql':
					$sql .= sprintf('%s(%d)', $col->type, $col->size);
					break;
				case 'pgsql':
					if (($col->type == 'binary') || ($col->type == 'varbinary') || ($col->type == 'blob')) {
						$sql .= 'bytea';
					} else {
						$sql .= sprintf('%s(%d)', $col->type, $col->size);
					}
					break;
				}
				break;
			case 'text':
				switch ($dialect) {
				case 'mysql':
					$sql .= 'longtext';
					break;
				case 'pgsql':
					$sql .= 'text';
					break;
				}
				break;
			case 'blob':
				switch ($dialect) {
				case 'mysql':
					$sql .= 'longblob';
					break;
				case 'pgsql':
					$sql .= 'bytea';
					break;
				}
				break;
			case 'date':
			case 'time':
				$sql .= $col->type;
				break;
			case 'datetime':
				switch ($dialect) {
				case 'mysql':
					if ($col->useTimeZone) {
						$sql .= 'timestamp';
					} else {
						$sql .= 'datetime';
					}
					break;
				case 'pgsql':
					if ($col->useTimeZone) {
						$sql .= 'timestamp with time zone';
					} else {
						$sql .= 'timestamp without time zone';
					}
					break;
				}
				break;
			default:
				throw new Exception("Invalid column type: %s", $col->type);
				break;
			}	// switch ($col->type])
		}	// if ($includeColumnType)

		if ($includeColumnNull) {
			if (!$col->allowNull) {
				$sql .= ' not null';
			}
		}

		if ($col->autoIncrement) {
			if ($includeColumnAutoIncrement) {
				switch ($dialect) {
				case 'mysql':
					$sql .= ' auto_increment';
					break;
				case 'pgsql':
					$sql .= " default nextval('".$ldpfx.$tableName."_autoInc_seq')";
					break;
				}
			}
		} else if ($col->sysVarDefault !== null) {
			if ($includeColumnDefault) {
				$sql .= " default ".$this->__convertSysVar($col->sysVarDefault, $dialect);
			}
		} else if ($col->defaultValue !== null) {
			if ($includeColumnDefault) {
				switch ($col->type) {
				case 'integer':
				case 'smallint':
				case 'bigint':
					$sql .= sprintf(" default %s", (string)((int)$col->defaultValue));
					break;
				case 'decimal':
					$sql .= sprintf(" default %s", (string)((double)$col->defaultValue));
					break;
				case 'date':
					if ($col->defaultValue == '') {
						// For date types which default to empty, change the default to 0000-00-00 or 0001-01-01, depending on which one the database will accept.
						switch ($dialect) {
						case 'mysql':
							$sql .= " default '0000-00-00'";
							break;
						case 'pgsql':
							$sql .= " default '0001-01-01'";
							break;
						}
					} else {
						$sql .= sprintf(
							" default '%s'",
							$this->__escape($col->defaultValue, $dialect)
						);
					}
					break;
				case 'time':
					if ($col->defaultValue == '') {
						// For time types which default to empty, change the default to 00:00:00.
						switch ($dialect) {
						case 'mysql':
						case 'pgsql':
							$sql .= " default '00:00:00'";
							break;
						}
					} else {
						$sql .= sprintf(
							" default '%s'",
							$this->__escape($col->defaultValue, $dialect)
						);
					}
					break;
				case 'datetime':
					if ($col->defaultValue == '') {
						// For datetime types which default to empty, change the default to 0000-00-00 00:00:00 or 0001-01-01 00:00:00, depending on which one the database will accept.
						switch ($dialect) {
						case 'mysql':
							$sql .= " default '0000-00-00 00:00:00'";
							break;
						case 'pgsql':
							$sql .= " default '0001-01-01 00:00:00'";
							break;
						}
					} else {
						$sql .= sprintf(
							" default '%s'",
							$this->__escape($col->defaultValue, $dialect)
						);
					}
					break;
				default:
					$vlen = strlen($col->defaultValue);
					$valIsBinary = false;
					for ($vi = 0; $vi < $vlen; $vi++) {
						$vcc = ord($col->defaultValue[$vi]);
						if (($vcc < 32) || ($vcc > 126)) {
							$valIsBinary = true;
							break;
						}
					}
					if ($valIsBinary) {
						$arrData = unpack("H*hex", $col->value);
						$sql .= ' default 0x'.$arrData['hex'];
						unset($arrData);
					} else {
						$sql .= sprintf(
							" default '%s'",
							$this->__escape($col->defaultValue, $dialect)
						);
					}
					break;
				}
			}	// if ($includeColumnDefault)
		} else {	// if ($col->defaultValue !== null)
			if ($includeColumnDefault) {
				$sql .= ' default NULL';
			}	// if ($includeColumnDefault)
		}	// if ($col->defaultValue !== null) ... else
		return $sql;
	}

	// If $db is provided, it will be used to suppress insert statements which are not needed,
	// and may also be used to transform insert statements into update statements for rows which
	// exist but have non-key columns whose values don't match the original insert values (for
	// inserts with key columns and updateIfExists set to true).
	public function serializeInsert($insert, $dialect, $db = null, $localDBName = null, $basepath = '') {

		$ldbpfx = ($localDBName != '') ? $localDBName.'.' : '';

		$sqlStatements = array();

		if (empty($insert->keyColumnNames)) {
			$whereClause = '';
		} else { // if (empty($insert->keyColumnNames))
			// Build "where" clause to be used for select and update statements.
			$whereClause = ' where ';
			$sep = '';
			foreach ($insert->keyColumnNames as $kcn) {
				$kcidx = $insert->getColumnIdx($kcn);
				if ($kcidx < 0) continue;
				$col = $insert->columns[$kcidx];
				if (($col->sysVarValue !== false) ||
					(($col->filename === false) && ($col->value === false))) {
					continue;
				}
				$value = $col->getValueOrFileValue($basepath);
				if ($value === null) {
					$whereClause .= $sep.$kcn.' is NULL';
				} else {
					$whereClause .= $sep.$kcn.' = ';
					if (($col->quoted) || ($col->filename !== false)) {
						$vlen = strlen($value);
						$valIsBinary = false;
						for ($vi = 0; $vi < $vlen; $vi++) {
							$vcc = ord($value[$vi]);
							if (($vcc < 32) || ($vcc > 126)) {
								$valIsBinary = true;
								break;
							}
						}
						if ($valIsBinary) {
							$arrData = unpack("H*hex", $value);
							$whereClause .= '0x'.$arrData['hex'];
							unset($arrData);
						} else {
							$whereClause .= sprintf("'%s'", $this->__escape($value, $dialect));
						}
					} else {
						$whereClause .= $value;
					}
				}
				if ($sep == '') $sep = ' and ';
			} // foreach ($insert->keyColumnNames as $kcn)
		} // if (empty($insert->keyColumnNames)) ... else


		if ($db) {

			// We have a database connection.
			// Only generate inserts/updates as needed, based on what is already in the table.

			// Build "where" clause if we have key column names.
			if (empty($insert->keyColumnNames)) {
				$needInsert = true;
			} else { // if (empty($insert->keyColumnNames))
				$needInsert = false;

				// If the row does not exist, we need to insert it.
				// If updateIfExists is set and the row exists and one or more non-key columns don't match, update the row.
				// In all other cases, we don't need to do anything.
				$sql = 'select ';
				if ($insert->updateIfExists) {
					$sep = '';
					foreach ($insert->columns as $col) {
						$sql .= $sep.$col->name;
						if ($sep == '') $sep = ', ';
					}
				} else {
					$sql .= $insert->keyColumnNames[0];
				}
				$sql .= ' from '.$ldbpfx.$insert->tableName.$whereClause;
				if (!($row = $db->fetchArray($db->executeQuery(new PreparedStatement($sql, 0, 1, true))))) {
					$needInsert = true;
				} else { // if (!($row = $db->fetchArray(...)))
					if ($insert->updateIfExists) {
						$colNamesToUpdate = array();
						foreach ($insert->columns as $col) {
							if ($col->sysVarValue === false) {
								$value = $col->getValueOrFileValue($basepath);
								if (($row[$col->name] != $value) ||
									(($row[$col->name] === null) != ($value === null))) {
									$colNamesToUpdate[] = $col->name;
								}
							}
						}
						if (!empty($colNamesToUpdate)) {
							// The row exists, but some of the non-key columns don't match.  Update them.
							$sql = sprintf("update %s set ", $ldbpfx.$insert->tableName);
							$sep = '';
							foreach ($insert->columns as $col) {
								if (!in_array($col->name, $colNamesToUpdate)) continue;
								$sql .= $sep.$col->name.' = ';
								$value = $col->getValueOrFileValue($basepath);
								if (($col->quoted) || ($col->filename !== false)) {
									$vlen = strlen($value);
									$valIsBinary = false;
									for ($vi = 0; $vi < $vlen; $vi++) {
										$vcc = ord($value[$vi]);
										if (($vcc < 32) || ($vcc > 126)) {
											$valIsBinary = true;
											break;
										}
									}
									if ($valIsBinary) {
										$arrData = unpack("H*hex", $value);
										$sql .= '0x'.$arrData['hex'];
										unset($arrData);
									} else {
										$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
									}
								} else {
									$sql .= $value;
								}
								if ($sep == '') $sep = ', ';
							} // foreach ($insert->columns as $col)
							$sql .= $whereClause;
							$sqlStatements[] = $sql;
						} // if (!empty($colNamesToUpdate))
					} // if ($insert->updateIfExists)
				} // if (!($row = $db->fetchArray(...))) ... else
			} // if (empty($insert->keyColumnNames)) ... else

			if ($needInsert) {
				$sql = sprintf("insert into %s (", $ldbpfx.$insert->tableName);
				$sep = '';
				foreach ($insert->columns as $col) {
					$sql .= $sep.$col->name;
					if ($sep == '') $sep = ', ';
				}
				$sql .= ') values (';
				$sep = '';
				foreach ($insert->columns as $col) {
					$sql .= $sep;
					if ($col->sysVarValue !== false) {
						$sql .= $this->__convertSysVar($col->sysVarValue, $dialect);
					} else {
						$value = $col->getValueOrFileValue($basepath);
						if ($value === null) {
							$sql .= 'NULL';
						} else {
							if (($col->quoted) || ($col->filename !== false)) {
								$vlen = strlen($value);
								$valIsBinary = false;
								for ($vi = 0; $vi < $vlen; $vi++) {
									$vcc = ord($value[$vi]);
									if (($vcc < 32) || ($vcc > 126)) {
										$valIsBinary = true;
										break;
									}
								}
								if ($valIsBinary) {
									$arrData = unpack("H*hex", $value);
									$sql .= '0x'.$arrData['hex'];
									unset($arrData);
								} else {
									$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
								}
							} else {
								$sql .= $value;
							}
						}
					}
					if ($sep == '') $sep = ', ';
				}
				$sql .= ")";
				$sqlStatements[] = $sql;
			} // if ($needInsert)

		} else { // if ($db)

			// We don't have a database connection.
			// Generate generate generic inserts/updates to ensure that all inserted rows are correct,
			// regardless of what is in the table.

			$sql = sprintf("insert into %s (", $ldbpfx.$insert->tableName);
			$sep = '';
			foreach ($insert->columns as $col) {
				$sql .= $sep.$col->name;
				if ($sep == '') $sep = ', ';
			}
			if (!empty($insert->keyColumnNames)) {
				$sql .= ') select ';
			} else {
				$sql .= ') values (';
			}
			$sep = '';
			foreach ($insert->columns as $col) {
				$sql .= $sep;
				if ($col->sysVarValue !== false) {
					$sql .= $this->__convertSysVar($col->sysVarValue, $dialect);
				} else {
					$value = $col->getValueOrFileValue($basepath);
					if ($value === null) {
						$sql .= 'NULL';
					} else {
						if (($col->quoted) || ($col->filename !== false)) {
							$vlen = strlen($value);
							$valIsBinary = false;
							for ($vi = 0; $vi < $vlen; $vi++) {
								$vcc = ord($value[$vi]);
								if (($vcc < 32) || ($vcc > 126)) {
									$valIsBinary = true;
									break;
								}
							}
							if ($valIsBinary) {
								$arrData = unpack("H*hex", $value);
								$sql .= '0x'.$arrData['hex'];
							} else {
								$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
							}
						} else {
							$sql .= $value;
						}
					}
				}
				if ($sep == '') $sep = ', ';
			}
			if (!empty($insert->keyColumnNames)) {
				if ($dialect != 'pgsql') {
					$sql .= ' from dual';
				}
				$sql .=
					' where not exists(select '.
					implode(', ', $insert->keyColumnNames).
					' from '.$ldbpfx.$insert->tableName.
					' where ';
				$sep = '';
				foreach ($insert->keyColumnNames as $kcn) {
					$kcidx = $insert->getColumnIdx($kcn);
					if ($kcidx < 0) continue;
					$col = $insert->columns[$kcidx];
					if (($col->sysVarValue !== false) ||
						(($col->filename === false) && ($col->value === false))) {
						continue;
					}
					$value = $col->getValueOrFileValue($basepath);
					if ($value === null) {
						$sql .= $sep.$kcn.' is NULL';
					} else {
						$sql .= $sep.$kcn.' = ';
						if (($col->quoted) || ($col->filename !== false)) {
							$vlen = strlen($value);
							$valIsBinary = false;
							for ($vi = 0; $vi < $vlen; $vi++) {
								$vcc = ord($value[$vi]);
								if (($vcc < 32) || ($vcc > 126)) {
									$valIsBinary = true;
									break;
								}
							}
							if ($valIsBinary) {
								$arrData = unpack("H*hex", $value);
								$sql .= '0x'.$arrData['hex'];
								unset($arrData);
							} else {
								$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
							}
						} else {
							$sql .= $value;
						}
					}
					if ($sep == '') $sep = ' and ';
				}
				$sql .= ")";
			} else {
				$sql .= ")";
			}
			$sqlStatements[] = $sql;

			if ((!empty($insert->keyColumnNames)) && ($insert->updateIfExists)) {
				$sql = sprintf("update %s set ", $ldbpfx.$insert->tableName);
				$sep = '';
				foreach ($insert->columns as $col) {
					$sql .= $sep.$col->name.' = ';
					if ($col->sysVarValue !== false) {
						$sql .= $this->__convertSysVar($col->sysVarValue, $dialect);
					} else {
						$value = $col->getValueOrFileValue($basepath);
						if ($value === null) {
							$sql .= 'NULL';
						} else {
							if (($col->quoted) || ($col->filename !== false)) {
								$vlen = strlen($value);
								$valIsBinary = false;
								for ($vi = 0; $vi < $vlen; $vi++) {
									$vcc = ord($value[$vi]);
									if (($vcc < 32) || ($vcc > 126)) {
											$valIsBinary = true;
										break;
									}
								}
								if ($valIsBinary) {
									$arrData = unpack("H*hex", $value);
									$sql .= '0x'.$arrData['hex'];
									unset($arrData);
								} else {
									$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
								}
							} else {
								$sql .= $value;
							}
						}
					}
					if ($sep == '') $sep = ', ';
				}
				$sql .= ' where ';
				$sep = '';
				foreach ($insert->keyColumnNames as $kcn) {
					$kcidx = $insert->getColumnIdx($kcn);
					if ($kcidx < 0) continue;
					$col = $insert->columns[$kcidx];
					if (($col->sysVarValue !== false) ||
						(($col->filename === false) && ($col->value === false))) {
						continue;
					}
					$value = $col->getValueOrFileValue($basepath);
					$sql .= $sep.$kcn.' = ';
					if ($value === null) {
						$sql .= 'NULL';
					} else if (($col->quoted) || ($col->filename !== false)) {
						$vlen = strlen($value);
						$valIsBinary = false;
						for ($vi = 0; $vi < $vlen; $vi++) {
							$vcc = ord($value[$vi]);
							if (($vcc < 32) || ($vcc > 126)) {
								$valIsBinary = true;
								break;
							}
						}
						if ($valIsBinary) {
							$arrData = unpack("H*hex", $value);
							$sql .= '0x'.$arrData['hex'];
							unset($arrData);
						} else {
							$sql .= sprintf("'%s'", $this->__escape($value, $dialect));
						}
					} else {
						$sql .= $value;
					}
					if ($sep == '') $sep = ' and ';
				}
				$sqlStatements[] = $sql;
			}

		} // if ($db) ... else

		return $sqlStatements;
	} // serializeInsert()

	protected function __convertSysVar($sysVar, $dialect) {
		switch (strtolower($sysVar)) {
		case 'current_date':
			switch ($dialect) {
			case 'mysql':
			case 'pgsql':
			default:
				return 'CURRENT_DATE';
				break;
			}
			break;
		case 'current_time':
			switch ($dialect) {
			case 'mysql':
			case 'pgsql':
			default:
				return 'CURRENT_TIME';
				break;
			}
			break;
		case 'current_timestamp':
			switch ($dialect) {
			case 'mysql':
			case 'pgsql':
			default:
				return 'CURRENT_TIMESTAMP';
				break;
			}
			break;
		default:
			throw new Exception(sprintf("Invalid system variable reference \"%s\".", $sysVar));
			break;
		}
	}

	protected function __escape($s, $dialect) {
		switch ($dialect) {
		case 'mysql':
			$s2 = '';
			$len = strlen($s);
			for ($i = 0; $i < $len; $i++) {
				switch ($s[$i]) {
				case "\x00":
				case "\n":
				case "\r":
				case "\\":
				case "'":
				case "\"":
				case "\x1a":
					$s2 .= "\\";
					// no break here.
				default:
					$s2 .= $s[$i];
				}
			}
			$s = $s2;
			unset($s2);
			break;
		case 'pgsql':
			$s = str_replace("'", "''", $s);
			break;
		}
		return $s;
	}
} // SQLDDLSerializer






class YAMLDDLSerializer {
	// Serialize a DDL object tree, returning YAML.
	// Parameters:
	// $ddl: A valid DDL instance.
	// Returns: The YAML document, as a string.
	public function serialize($ddl) {
		$yaml = "tables:\n";
		$anythingOutput = false;
		foreach ($ddl->topLevelEntities as &$tle) {
			if (!($tle instanceof DDLTable)) continue;
			if ($anythingOutput) $yaml .= "\n\n";
			$anythingOutput = true;
			$yaml .= "  {$tle->tableName}:\n";
			if (!empty($tle->columns)) {
				$yaml .= "    columns:\n";
				foreach ($tle->columns as &$col) {
					$yaml .= "      {$col->name}: ".'{'." type: {$col->type}";
					if ( ($col->useTimeZone) && ($col->type == 'datetime') ) {
						$yaml .= ', useTimeZone: Yes';
					}
					if (($col->type == 'decimal') ||
						($col->type == 'char') ||
						($col->type == 'varchar') ||
						($col->type == 'binary') ||
						($col->type == 'varbinary')) {
						$yaml .= sprintf(', size: %d', $col->size);
					}
					if ($col->type == 'decimal') {
						$yaml .= sprintf(', scale: %d', $col->scale);
					}
					if (!$col->allowNull) {
						$yaml .= ', null: No';
					}
					if (($col->autoIncrement) &&
						(($col->type == 'integer') ||
					 	($col->type == 'smallint') ||
					 	($col->type == 'bigint'))) {
						$yaml .= ', autoIncrement: Yes';
					} else if ($col->sysVarDefault !== null) {
						$yaml .= sprintf(
							', sysVarDefault: %s',
							$col->sysVarDefault
						);
					} else if ($col->defaultValue !== null) {
						if (($col->type == 'binary') || ($col->type == 'varbinary') || ($col->type == 'blob')) {
							$yaml .= sprintf(
								', default: "%s", defaultIsBas64Encoded: Yes',
								base64_encode($col->defaultValue)
							);
						} else {
/// TODO: Figure out how to quote and properly escape this to avoid breaking the YAML format.
							$yaml .= sprintf(
								', default: "%s"',
								$col->defaultValue
							);
						}
					} else {
						$yaml .= ', default: NULL';
					}
					$yaml .= " }\n";
				}
				unset($col);	// release reference to last element
			}
			if ($tle->primaryKey !== false) {
				$yaml .= "    primaryKey:\n";
				if (empty($tle->primaryKey->columns)) {
					$yaml .= "      columns: ~\n";
				} else {
					$yaml .= "      columns: [";
					$sep = '';
					foreach ($tle->primaryKey->columns as &$col) {
						$yaml .= sprintf("%s %s", $sep, $col->name);
						if ($sep == '') $sep = ',';
					}
					unset($col);	// release reference to last element
					$yaml .= " ]\n";
				}
			}

			// Output indexes for this table.
			$anyIndexesOutput = false;
			foreach ($ddl->topLevelEntities as &$itle) {
				if (!($itle instanceof DDLIndex)) continue;
				if ($itle->tableName != $tle->tableName) continue;
				if (!$anyIndexesOutput) {
					$anyIndexesOutput = true;
					$yaml .= "    indexes:\n";
				}
				$yaml .= sprintf("      %s:\n", $itle->indexName);
				if ($itle->fulltext) {
					$yaml .= "        fulltext: Yes\n";
				} else if ($itle->unique) {
					$yaml .= "        unique: Yes\n";
				}
				if (empty($itle->columns)) {
					$yaml .= "        columns: ~\n";
				} else {
					$yaml .= "        columns: [";
					$sep = '';
					foreach ($itle->columns as &$col) {
						$yaml .= sprintf("%s %s", $sep, $col->name);
						if ($sep == '') $sep = ',';
					}
					unset($col);	// release reference to last element
					$yaml .= " ]\n";
				}
			}
			unset($itle);	// release reference to last element
			if (!$anyIndexesOutput) $yaml .= "    indexes: ~\n";


			// Output foreign keys for this table.
			$anyForeignKeysOutput = false;
			foreach ($ddl->topLevelEntities as &$fktle) {
				if (!($fktle instanceof DDLForeignKey)) continue;
				if ($fktle->localTableName != $tle->tableName) continue;
				if (!$anyForeignKeysOutput) {
					$anyForeignKeysOutput = true;
					$yaml .= "    foreignKeys:\n";
				}
				$yaml .= sprintf(
					"      %s:\n        foreignTable: %s\n",
					$fktle->foreignKeyName,
					$fktle->foreignTableName
				);
				if (empty($fktle->columns)) {
					$yaml .= "        columns: ~\n";
				} else {
					$yaml .= "        columns:\n";
					foreach ($fktle->columns as &$col) {
						$yaml .= sprintf(
							"          %s: { local: %s, foreign: %s }\n",
							$col->localName,
							$col->localName,
							$col->foreignName
						);
					}
					unset($col);	// release reference to last element
				}
			}
			unset($fktle);	// release reference to last element
			if (!$anyForeignKeysOutput) $yaml .= "    foreignKeys: ~\n";


			// Output inserts for this table.
			$anyInsertsOutput = false;
			foreach ($ddl->topLevelEntities as &$itle) {
				if (!($itle instanceof DDLInsert)) continue;
				if ($itle->tableName != $tle->tableName) continue;
				if (!$anyInsertsOutput) {
					$anyInsertsOutput = true;
					$yaml .= "    inserts:\n";
				}

				$yaml .= "      -\n";
				if (!empty($itle->keyColumnNames)) {
					$yaml .= sprintf(
						"        keyColumnNames: [ %s ]\n",
						implode(', ', $tle->keyColumnNames)
					);
					$yaml .= sprintf(
						"        updateIfExists: %s\n",
						$tle->updateIfExists ? 'true' : 'false'
					);
				}
				foreach ($itle->columns as &$col) {
					$yaml .= sprintf("        %s: {", $col->name);
					if ($col->sysVarValue !== false) {
						$yaml .= sprintf(" sysVarValue: %s", $col->sysVarValue);
					} else if ($col->filename !== false) {
						$yaml .= sprintf(" filename: %s", $col->filename);
					} else {
						$vlen = strlen($col->value);
						$valIsBinary = false;
						for ($vi = 0; $vi < $vlen; $vi++) {
							$vcc = ord($col->value[$vi]);
							if (($vcc < 32) || ($vcc > 126)) {
								$valIsBinary = true;
								break;
							}
						}
						if ($valIsBinary) {
							$yaml .= sprintf(
								' value: "%s", valueIsBase64Encoded: Yes',
								base64_encode($col->value)
							);
						} else {
/// TODO: Figure out how to quote and properly escape this to avoid breaking the YAML format.
							$yaml .= sprintf(' value: "%s"', $col->value);
						}
						if ($col->quoted) $yaml .= ', quoted: Yes';
					}
					$yaml .= " }\n";
				}
				unset($col);	// release reference to last element
			}
			unset($itle);	// release reference to last element
		}
		unset($tle);	// release reference to last element
		return $yaml;
	}
} // YAMLDDLSerializer






// The SQLDDLUpdater class outputs DDL update SQL for a specified SQL dialect,
// given a pair of valid DDL instances.  The outputted SQL will update the
// tables and indexes (DDL) from one DDL to another.
class SQLDDLUpdater {

	// Given two DDL object trees (one old and one new), generate SQL in the selected
	// database dialect to update the schema from the old to the new DDL structure.
	// Parameters:
	// $oldDDL: A valid DDL instance which represents the current (old) state of
	// the database schema. Typically this would be retrieved from a database
	// server which needs to be updated to reflect a new schema.
	// $newDDL: A valid DDL instance which represents the desired final (new) state
	// of the database schema.
	// $allowDropTable: true to allow dropping of tables which are in $oldDDL but
	// not in $newDDL; false to not drop them.  Defaults to false.
	// $allowDropColumn: true to allow dropping of columns which are in $oldDDL
	// but not in $newDDL; false to not drop them.  Defaults to false.
	// $allowDropIndex: true to allow dropping of indexes and foreign keys which are in $oldDDL
	// but not in $newDDL; false to not drop them.  Defaults to false.
	// $dialect: The SQL dialect to use.  Must be one of the dialects listed in
	// DDL::$SUPPORTED_DIALECTS.  Defaults to 'mysql'.
	// $dbmap: An optional DDLTableToDatabaseMap instance for group- and table-to-database mapping,
	//   or null if none.  Optional.  Defaults to null.
	// $localDBName: An optional local database (or schema) name.  If specified, this will be prefixed,
	// along with a dot, to all local table and view names.
	// Returns an array of strings, where each string is a single SQL statement.
	// Returns the SQL statements required to transform the schema from $oldDDL
	// to match $newDDL, as an array of strings, where each string is a single SQL statement.
	// $basepath: The base directory for insert filenames with relative paths.
	// Returns an array of strings, where each string is a single SQL statement.
	public function generateSQLUpdates(
		$oldDDL,
		$newDDL,
		$allowDropTable = false,
		$allowDropColumn = false,
		$allowDropIndex = false,
		$dialect = 'mysql',
		$dbmap = null,
		$localDBName = null,
		$basepath = '') {

		if (!in_array($dialect, DDL::$SUPPORTED_DIALECTS)) {
			throw new Exception(sprintf(
				"Requested SQL dialect \"%s\" is not in the list of supported dialects (%s).",
				$dialect,
				implode(', ',  DDL::$SUPPORTED_DIALECTS)
			));
		}

		$ldbpfx = ($localDBName != '') ? $localDBName.'.' : '';

		$serializer = new SQLDDLSerializer();

		$sqlStatements = array();

		// Determine which table names are common among all tables.
		$commonTableNames = $oldDDL->getCommonTableNames($newDDL);
		$oldTableNames = $oldDDL->getAllTableNames();
		$newTableNames = $newDDL->getAllTableNames();

		$idxsDroppedByTableName = array();
		$fksDroppedByTableName = array();

		$droppedIndexOnTableNames = array();

		if ($allowDropIndex) {
			// For tables which exist in both old and new schemas, drop all indexes and
			// foreign keys which exist in $oldDDL but don't exist in $newDDL,
			// or which exist in both but are different between the two.
			foreach ($commonTableNames as $tableName) {
				$tmp = $oldDDL->getTableIndexesAndForeignKeys($tableName);
				$oidxs = $tmp->idxs;
				$ofks = $tmp->fks;
				unset($tmp);

				$tmp = $newDDL->getTableIndexesAndForeignKeys($tableName);
				$nidxs = $tmp->idxs;
				$nfks = $tmp->fks;
				unset($tmp);

				// Indexes
				foreach (array_keys($oidxs) as $indexName) {
					if ((!isset($nidxs[$indexName])) ||
						($oidxs[$indexName] != $nidxs[$indexName])) {
						switch ($dialect) {
						case 'mysql':
							// MySQL also creates an index with the same name as the foreign key.
							// This means we only want to drop the index if it's not part of
							// a foreign key in the new schema, or if it's part of a foreign key
							// which does not match between the old and new schemas.
							if ((!isset($nfks[$indexName])) ||
								(isset($ofks[$indexName]) && ($ofks[$indexName] != $nfks[$indexName]))) {
								$itn = $indexName.' on '.$ldbpfx.$tableName;
								if (!in_array($itn, $droppedIndexOnTableNames)) {
									$sqlStatements[] = "drop index $indexName on {$ldbpfx}{$tableName}";
									$droppedIndexOnTableNames[] = $itn;
								}
								unset($itn);
							}
							break;
						case 'pgsql':
							$itn = $indexName.' on '.$tableName;
							if (!in_array($itn, $droppedIndexOnTableNames)) {
								$sqlStatements[] = "drop index {$indexName}";
								$droppedIndexOnTableNames[] = $itn;
							}
							unset($itn);
							break;
						}
						if (!isset($idxsDroppedByTableName[$tableName])) {
							$idxsDroppedByTableName[$tableName] = array();
						}
						if (!in_array($indexName, $idxsDroppedByTableName[$tableName])) {
							$idxsDroppedByTableName[$tableName][] = $indexName;
						}
					}
				}

				// Foreign keys
				foreach ($ofks as $foreignKeyName=>$ofk) {
					if ((!isset($nfks[$foreignKeyName])) ||
						($ofks[$foreignKeyName] != $nfks[$foreignKeyName])) {
						switch ($dialect) {
						case 'mysql':
							$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop foreign key $foreignKeyName";
							$itn = $foreignKeyName.' on '.$tableName;
							if (!in_array($itn, $droppedIndexOnTableNames)) {
								$sqlStatements[] = "drop index $foreignKeyName on {$ldbpfx}{$tableName}";
								$droppedIndexOnTableNames[] = $itn;
							}
							unset($itn);
							break;
						case 'pgsql':
							$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop constraint $foreignKeyName";
							break;
						}
						if (!isset($fksDroppedByTableName[$tableName])) {
							$fksDroppedByTableName[$tableName] = array();
						}
						if (!in_array($foreignKeyName, $fksDroppedByTableName[$tableName])) {
							$fksDroppedByTableName[$tableName][] = $foreignKeyName;
						}
					}
				}
			}
		}	// if ($allowDropIndex)

		if ($allowDropTable) {
			// Drop all tables which exist in $oldDDL but don't exist in $newDDL.
			foreach ($oldTableNames as $tn) {
				if (!in_array($tn, $newTableNames)) {
					$sqlStatements[] = 'drop table if exists '.$ldbpfx.$tn;
					$sqlStatements[] = 'drop view if exists '.$ldbpfx.$tn;
					if ($dialect == 'pgsql') {
						$sqlStatements[] = sprintf("drop sequence if exists %s_autoInc_seq", $ldbpfx.$tn);
					}
				}
			}
		}	// if ($allowDropTable)

		// Create all tables and their indexes, which exist in $newDDL but don't exist in $oldDDL.
		// Perform all inserts for newly created tables.
		foreach ($newDDL->topLevelEntities as $ntle) {
			if (($ntle instanceof DDLTable) &&
				(!in_array($ntle->tableName, $commonTableNames))) {
				$tles = array($ntle);
				for ($i = 0, $ni = count($newDDL->topLevelEntities); $i < $ni; $i++) {
					$tle = $newDDL->topLevelEntities[$i];
					if ((($tle instanceof DDLIndex) || ($tle instanceof DDLInsert)) &&
						($tle->tableName == $ntle->tableName)) {
						$tles[] = $tle;
						continue;
					}
				}
				$tddl = new DDL($tles);
				$sqlStatements = array_merge($sqlStatements, $serializer->serialize($tddl, $dialect, $dbmap, $localDBName, $basepath));
			}
		}	// foreach ($newDDL->topLevelEntities as $ntle)

		// Process each common table name.
		foreach ($commonTableNames as $tableName) {
			// Find the old table definition, and all indexes and foreign keys for it.
			$tmp = $oldDDL->getTableIndexesAndForeignKeys($tableName);
			$otbl = $tmp->tbl;
			$oidxs = $tmp->idxs;
			$ofks = $tmp->fks;
			unset($tmp);

			// Find the new table definition, and all indexes and foreign keys for it.
			$tmp = $newDDL->getTableIndexesAndForeignKeys($tableName);
			$ntbl = $tmp->tbl;
			$nidxs = $tmp->idxs;
			$nfks = $tmp->fks;
			unset($tmp);

			$dbname = (($dbmap !== null) && ($ntbl !== false)) ? $dbmap->getDatabase($ntbl->group, $ntbl->tableName) : null;

			$column_sqlStatements = array();

			if ($allowDropColumn) {
				// Drop columns which exist in the old table but not the new.
				$ncols = array();
				foreach ($ntbl->columns as $ncol) $ncols[$ncol->name] = $ncol;
				for ($oci = 0, $onc = count($otbl->columns); $oci < $onc; $oci++) {
					$ocol = $otbl->columns[$oci];
					if (!isset($ncols[$ocol->name])) {
						switch ($dialect) {
						case 'mysql':
							$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop column ".$ocol->name;
							break;
						case 'pgsql':
							$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop column ".$ocol->name." cascade";
							break;
						}
					}
				}
			}

			// Add columns which exist in the new table but not the old.
			// Update columns which exist in both tables.
			$ocols = array();
			foreach ($otbl->columns as $ocol) $ocols[$ocol->name] = $ocol;
			for ($nci = 0, $nnc = count($ntbl->columns); $nci < $nnc; $nci++) {
				$ncol = $ntbl->columns[$nci];
				if (!isset($ocols[$ncol->name])) {
					// Column exists in new but not old.  Add the column to the table.
					switch ($dialect) {
					case 'mysql':
					case 'pgsql':
						$column_sqlStatements[] =
							"alter table {$ldbpfx}{$tableName} add column ".
							$serializer->serializeTableColumn($ncol, $tableName, $dialect, true, true, true, true, true, $localDBName).
							(($nci > 0) ? (' after '.$ntbl->columns[$nci-1]->name) : ' first');
						break;
					}
				} else if ($ncol != $ocols[$ncol->name]) {
					// If the table is a view, ignore differences in the auto_increment and default values.
					if (($dbname !== null) && ($dbname != '') && ($ncol->isEqualForView($ocols[$ncol->name]))) {
						continue;
					}

					// Column exists in both new and old, but is different.  Alter the column.
					$ocol = $ocols[$ncol->name];
					switch ($dialect) {
					case 'mysql':
						$column_sqlStatements[] =
							"alter table {$ldbpfx}{$tableName} change column {$ncol->name} ".
							$serializer->serializeTableColumn($ncol, $tableName, $dialect, true, true, true, true, true, $localDBName).
							(($nci > 0) ? (' after '.$ntbl->columns[$nci-1]->name) : ' first');
						break;
					case 'pgsql':
						if (($ocol->type != $ncol->type) ||
							(($ncol->type == 'datetime') &&
							 ($ocol->useTimeZone != $ncol->useTimeZone)) ||
							($ocol->size != $ncol->size) ||
							($ocol->scale != $ncol->scale)) {

							// The only binary type PostgreSQL supports is bytea, and it does
							// not have a size parameter.  Therefore, binary, varbinary and
							// blob types all map to bytea in PostgreSQL.  If both the new and
							// old columns are any of these types, then we don't need to alter
							// the column type.
							if ((($ocol->type == 'binary') || ($ocol->type == 'varbinary') || ($ocol->type == 'blob')) &&
								(($ncol->type == 'binary') || ($ncol->type == 'varbinary') || ($ncol->type == 'blob'))) {
								// Nothing to do here.
							} else {
								$column_sqlStatements[] =
									"alter table {$ldbpfx}{$tableName} alter column {$ncol->name} type ".
									$serializer->serializeTableColumn(
										$ncol,
										$tableName,
										$dialect,
										false,
										true,
										false,
										false,
										false,
										$localDBName
									);
							}
						}
						if ($ocol->allowNull != $ncol->allowNull) {
							if ($ncol->allowNull) {
								$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} alter column {$ncol->name} drop not null";
							} else {
								$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} alter column {$ncol->name} set not null";
							}
						}
						if (($ocol->defaultValue != $ncol->defaultValue) ||
							($ocol->sysVarDefault != $ncol->sysVarDefault) ||
							($ocol->autoIncrement != $ncol->autoIncrement)) {
							$s = $serializer->serializeTableColumn(
								$ncol,
								$tableName,
								$dialect,
								false,
								false,
								false,
								true,
								true,
								$localDBName
							);
							if ($s != '') {
								$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} alter column {$ncol->name} set ".ltrim($s, ' ');		// $s includes the "default" keyword.
							} else {
								$column_sqlStatements[] = "alter table {$ldbpfx}{$tableName} alter column {$ncol->name} drop default";
							}
						}
						break;
					}
				}
			}	// for ($nci = 0, $nnc = count($ntbl->columns); $nci < $nnc; $nci++)

			$dbname = (($dbmap !== null) && ($ntbl !== false)) ? $dbmap->getDatabase($ntbl->group, $ntbl->tableName) : null;
			if (($dbname !== null) && ($dbname != '')) {
				// This is a mapped table, which is represented as a view.
				if (!empty($column_sqlStatements)) {
					// One or more columns likely changed in the target table.  Update the view, then we're done with this table.
					$sqlStatements[] = sprintf("drop table if exists %s", $ldbpfx.$ntbl->tableName);
					$sqlStatements[] = sprintf("create or replace view %s as select * from %s.%s", $ldbpfx.$ntbl->tableName, $dbname, $ntbl->tableName);
				}
				continue;
			}

			// This is a normal table (not mapped to another database).  Do the rest of the processing for it.
			$sqlStatements = array_merge($sqlStatements, $column_sqlStatements);

			// Add, drop or update the primary key.
			if (($otbl->primaryKey === false) && ($ntbl->primaryKey !== false)) {
				// Add the primary key.
				$cns = array();
				foreach ($ntbl->primaryKey->columns as $cl) $cns[] = $cl->name;
				switch ($dialect) {
				case 'mysql':
				case 'pgsql':
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} add primary key (".implode(', ', $cns).")";
					break;
				}
			} else if (($otbl->primaryKey !== false) && ($ntbl->primaryKey === false)) {
				// Drop the primary key.
				switch ($dialect) {
				case 'mysql':
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop primary key";
					break;
				case 'pgsql':
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop constraint ${tableName}_pkey";
					break;
				}
			} else if (($otbl->primaryKey !== false) &&
						($ntbl->primaryKey !== false) &&
						($otbl->primaryKey != $ntbl->primaryKey)) {
				// Drop and re-add the primary key.
				$cns = array();
				foreach ($ntbl->primaryKey->columns as $cl) $cns[] = $cl->name;
				switch ($dialect) {
				case 'mysql':
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop primary key";
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} add primary key (".implode(', ', $cns).")";
					break;
				case 'pgsql':
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop constraint ${tableName}_pkey";
					$sqlStatements[] = "alter table {$ldbpfx}{$tableName} add primary key (".implode(', ', $cns).")";
					break;
				}
			}

			// Create any indexes which are missing or different between the old and new.
			// Drop and re-create any indexes which don't match.
			foreach (array_keys($nidxs) as $indexName) {
				if ((!isset($oidxs[$indexName])) || ($oidxs[$indexName] != $nidxs[$indexName])) {
					if (isset($oidxs[$indexName])) {
						// Drop the index (will re-create it below).
						$itn = $indexName.' on '.$ldbpfx.$tableName;
						if (!in_array($itn, $droppedIndexOnTableNames)) {
							switch ($dialect) {
							case 'mysql':
								$sqlStatements[] = "drop index $indexName on {$ldbpfx}{$tableName}";
								break;
							case 'pgsql':
								$sqlStatements[] = "drop index $indexName";
								break;
							}
							$droppedIndexOnTableNames[] = $itn;
						}
						unset($itn);
					}
					// (Re-)create the index.
					$tddl = new DDL(array($nidxs[$indexName]));
					$sqlStatements = array_merge($sqlStatements, $serializer->serialize($tddl, $dialect, $dbmap, $localDBName, $basepath));
				}
			}
		}	// foreach ($commonTableNames as $tableName)

		// For all tables which exist in $newDDL but don't exist in $oldDDL,
		// create their foriegn keys.
		foreach ($newDDL->topLevelEntities as $ntle) {
			if (($ntle instanceof DDLTable) &&
				(!in_array($ntle->tableName, $commonTableNames))) {

				$dbname = ($dbmap !== null) ? $dbmap->getDatabase($ntle->group, $ntle->tableName) : null;
				if (($dbname !== null) && ($dbname != '')) {
					// This is a mapped table, which is represented as a view.  Don't do anything for this table here.
					continue;
				}

				$tles = array();
				for ($i = 0, $ni = count($newDDL->topLevelEntities); $i < $ni; $i++) {
					$tle = $newDDL->topLevelEntities[$i];
					if (($tle instanceof DDLForeignKey) &&
						($tle->localTableName == $ntle->tableName)) {
						$tles[] = $tle;
						continue;
					}
				}
				if (!empty($tles)) {
					$tddl = new DDL($tles);
					$sqlStatements = array_merge($sqlStatements, $serializer->serialize($tddl, $dialect, $dbmap, $localDBName, $basepath));
				}
			}
		}

		// Create any foreign keys which are missing or different between the old and new.
		// Drop and re-create any foreign keys which don't match.
		foreach ($commonTableNames as $tableName) {
			// Find the old table definition, and all foreign keys for it.
			$tmp = $oldDDL->getTableIndexesAndForeignKeys($tableName);
			$ofks = $tmp->fks;
			unset($tmp);

			// Find the new table definition, and all indexes and foreign keys for it.
			$tmp = $newDDL->getTableIndexesAndForeignKeys($tableName);
			$nfks = $tmp->fks;
			$ntbl = $tmp->tbl;
			unset($tmp);

			$dbname = (($dbmap !== null) && ($ntbl !== false)) ? $dbmap->getDatabase($ntbl->group, $ntbl->tableName) : null;
			if (($dbname !== null) && ($dbname != '')) {
				// This is a mapped table, which is represented as a view.  Don't do anything for this table here.
				continue;
			}

			foreach ($nfks as $foreignKeyName=>$nfk) {
				if ((!isset($ofks[$foreignKeyName])) ||
					($ofks[$foreignKeyName] != $nfks[$foreignKeyName])) {
					if (isset($ofks[$foreignKeyName])) {
						// Drop the foreign key (will re-create it below).
						if (!in_array($foreignKeyName, $fksDroppedByTableName[$tableName])) {
							switch ($dialect) {
							case 'mysql':
								$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop foreign key $foreignKeyName";
								$itn = $foreignKeyName.' on '.$ldbpfx.$tableName;
								if (!in_array($itn, $droppedIndexOnTableNames)) {
									$sqlStatements[] = "drop index $foreignKeyName on {$ldbpfx}{$tableName}";
									$droppedIndexOnTableNames[] = $itn;
								}
								break;
							case 'pgsql':
								$sqlStatements[] = "alter table {$ldbpfx}{$tableName} drop constraint $foreignKeyName";
								break;
							}
						}
					}

					$tmp = $newDDL->getTableIndexesAndForeignKeys($nfk->foreignTableName);
					$tbl = $tmp->tbl;
					if ($tbl !== false) {
						$dbname = (($dbmap !== null) && ($tbl !== false)) ? $dbmap->getDatabase($tbl->group, $tbl->tableName) : null;
						if (($dbname === null) || ($dbname == '')) {
							// (Re-)create the foreign key.
							$tddl = new DDL(array($nfks[$foreignKeyName]));
							$sqlStatements = array_merge($sqlStatements, $serializer->serialize($tddl, $dialect, null, $localDBName, $basepath));
						}
					}
				}
			}
		}

		// Combine consecutive "alter table" statements for the same table name into a single "alter table" statement,
		// limiting its length to 65000 characters.
		$new_sqlStatements = array();
		$combinedAlterStatement = null;
		$combinedAlterPrefix = null;
		$alterTableRegex = '/^alter table [^\s]+ /';
		foreach ($sqlStatements as $statement) {
			// If the previous statement was an "alter table" statement, and the current statement is
			// an "alter table" statement for the same table, and combining them won't exceed the maximum
			// statement length we'd like to adhere to, append the new statement to the combined "alter table"
			// statement.
			// If we couldn't append (not an "alter table" statement, not the same table, or would exceed the
			// maximum statement length), flush the existing combined "alter table" statement, and resume
			// processing with the new statement.
			if ($combinedAlterStatement !== null) {
				if (preg_match($alterTableRegex, $statement, $matches) > 0) {
					if ($matches[0] == $combinedAlterPrefix) {
						$toAdd = ', '.substr($statement, strlen($combinedAlterPrefix));
						if ((strlen($combinedAlterStatement)+strlen($toAdd)) <= 650000) {
							$combinedAlterStatement .= $toAdd;
							continue;
						}
					}
				}
				$new_sqlStatements[] = $combinedAlterStatement;
				$combinedAlterStatement = null;
				$combinedAlterPrefixLen = 0;
			}
			// If we didn't append the current statment to a combined "alter table" statement, but it is
			// an "alter table" statement, start a new combined "alter table" statement.
			if (preg_match($alterTableRegex, $statement, $matches) > 0) {
				$combinedAlterStatement = $statement;
				$combinedAlterPrefix = $matches[0];
				continue;
			}
			// This is not an "alter table" statement at all.  Append it to the results.
			$new_sqlStatements[] = $statement;
		}

		// Flush the last statment, if it was a (possibly combined) "alter table" statement.
		if ($combinedAlterStatement !== null) {
			$new_sqlStatements[] = $combinedAlterStatement;
		}

		return $new_sqlStatements;
	}

	private static function __indexNameSortComparator($a, $b) {
		return strcmp($a->indexName, $b->indexName);
	}
} // SQLDDLUpdater






class DDLTableToDatabaseMap {
	// An associative array of table-name-to-database mappings.
	protected $tableMaps;
	// An associative array of group-name-to-database mappings.
	protected $groupMaps;
	// Array of all target database names.
	public $allTargetDatabases;

	public function DDLTableToDatabaseMap($configString = null) {
		$this->clear();
		if (($configString !== null) && ($configString != '')) {
			$this->parseFromConfigString($configString);
		}
	} // DDLTableToDatabaseMap()

	public function clear() {
		$this->tableMaps = array();
		$this->groupMaps = array();
		$this->allTargetDatabases = array();
	} // clear()

	// Parse a table-to-database map.
	// $configString: A string, from the tableToDatabaseMap entry in database.ini,
	// which maps tables, or groups of tables, to databases.
	//   A comma-separated list of mapping rules.  Example mapping rules:
	//       group:groupname:database
	//       tablename:database
	//   Where:
	//       group is the literal word "group" (no quotes) which indicates that we are
	//           mapping all tables in .ddl.yaml file to a specific database.
	//           the entire group of tables (all tables contained in the identically
	//           named .ddl.yaml file) to the target database
	//       groupname is the name of a .ddl.yaml file such that all tables
	//           whose schema are defined in this DDL file will be mapped to the specified
	//           database.  For example, group:security:erp_common will map all tables whose
	//           schema are define in security.ddl.yaml to the "erp_common" database.
	//       database is the target database name for the mapping.  Omit to undo a previous
	//           mapping.  This allows un-mapping tables which would otherwise be mapped as
	//           part of a group.  For example, group:security:erp_common,appuserrole:
	//           will map all tables in security.ddl.yaml except appuserrole, to the erp_common
	//           database.
	// Throws an Exception if something goes wrong.
	public function parseFromConfigString($configString) {
		foreach (explode(',', $configString) as $s) {
			$pieces = explode(':', $s);
			for ($i = 0, $n = count($pieces); $i < $n; $i++) $pieces[$i] = trim($pieces[$i]);
			$npieces = count($pieces);
			switch (count($pieces)) {
			case 2:
				if ($pieces[0] == '') {
					throw new Exception(sprintf('Missing table name in table mapping: %s', $s));
				}
				$this->tableMaps[$pieces[0]] = $pieces[1];
				if (($pieces[1] != '') && (!in_array($pieces[1], $this->allTargetDatabases))) {
					$this->allTargetDatabases[] = $pieces[1];
				}
				break;
			case 3:
				if ($pieces[0] != 'group') {
					throw new Exception(sprintf('Missing "group" keyword at beginning of group mapping: %s', $s));
				}
				if ($pieces[1] == '') {
					throw new Exception(sprintf('Missing group name in group mapping: %s', $s));
				}
				$this->groupMaps[$pieces[1]] = $pieces[2];
				if (($pieces[2] != '') && (!in_array($pieces[2], $this->allTargetDatabases))) {
					$this->allTargetDatabases[] = $pieces[2];
				}
				break;
			default:
				throw new Exception(sprintf('Mal-formed mapping: %s', $s));
			}
		}
	} // parseFromConfigString()

	// Given a group name and a table name, return the mapped database for the table.
	// Returns null if there is no mapping, or if the table is mapped to the current database.
	public function getDatabase($groupName, $tableName) {
		// A table mapped to an empty database name can un-map that table from any
		// group mapping which is assigned to that table's group.
		// A table mapped to a non-empty database name can re-map that table from any
		// group mapping which is assigned to that table's group.
		if (is_string($tableName) && ($tableName !== null) && (array_key_exists($tableName, $this->tableMaps))) {
			if (($result = $this->tableMaps[$tableName]) != '') return $result;
		} else if (is_string($groupName) && ($groupName !== null) && (array_key_exists($groupName, $this->groupMaps))) {
			if (($result = $this->groupMaps[$groupName]) != '') return $result;
		}
		return null;
	}
} // DDLTableToDatabaseMap






class DAOClassGenerator {
	public static $GENERATED_HEADER =
		"// Generated automatically by phpdaogen.\n// Do NOT edit this file.\n// Any changes made to this file will be overwritten the next time it is generated.\n\n";
	public static $STUB_DATA_HEADER =
		"// This file can be edited (within reason) to extend the functionality\n// of the generated (abstract) data class.\n\n";
	public static $STUB_DAO_HEADER =
		"// This file can be edited (within reason) to extend the functionality\n// of the generated (abstract) DAO class.\n\n";


	// Returns PHP code for the data class.
	// Returns false if the table was not found in the DDL.
	public function generateDataClass($ddl, $tableName, $isAbstract = false) {
		if (($tableIdx = $ddl->getTableIdxInTopLevelEntities($tableName)) === false) {
			return false;
		}
		$table = &$ddl->topLevelEntities[$tableIdx];

		$concreteTableClassName = ucfirst($tableName);
		$tableClassName = $concreteTableClassName.($isAbstract ? 'Abstract' : '');

		// Open the class.
		$code = "<?php\n";
		$code .= DAOClassGenerator::$GENERATED_HEADER;
		$code .= ($isAbstract ? 'abstract ' : '')."class $tableClassName {\n";

		// Emit member variables.
		foreach ($table->columns as $column) {
			$code .= "\tpublic \${$column->name};\n";
		}
		$code .= "\n";

		// createDefault static factory function
		$code .= "\tpublic static function createDefault() {\n";
		$code .= "\t\t\$v = new $concreteTableClassName();\n";
		$code .= "\t\t\$v->defaultAllFields();\n";
		$code .= "\t\treturn \$v;\n";
		$code .= "\t}\n\n";

		// defaultAllFields function
		$code .= "\tpublic function defaultAllFields() {\n";
		foreach ($table->columns as $column) {
			$code .= "\t\t".'$this->'.$column->name.' = '.
				$this->getPHPEncodedDefaultValue($column).";\n";
		}
		$code .= "\t\treturn \$this;\n";
		$code .= "\t}\n\n";

		// loadFromArray function
		$code .= "\tpublic function loadFromArray(\$arr) {\n";
		foreach ($table->columns as $column) {
			$phpDataType = $this->getPHPDataType($column);
			$code .= "\t\t".'$this->'.$column->name.' = isset($arr[\''.$column->name.'\']) ? ('.
				$phpDataType.')$arr[\''.$column->name.'\'] : '.
				$this->getPHPEncodedDefaultValue($column).';'."\n";
		}
		$code .= "\t\treturn \$this;\n";
		$code .= "\t}\n";

		// Close the class.
		$code .= "}\n";

		unset($table);

		return $code;
	}

	public function generateStubDataClass($ddl, $tableName) {
		if (($tableIdx = $ddl->getTableIdxInTopLevelEntities($tableName)) === false) {
			return false;
		}
		$table = &$ddl->topLevelEntities[$tableIdx];

		$tableClassName = ucfirst($tableName);

		// Open the class.
		$code = "<?php\n";
		$code .= DAOClassGenerator::$STUB_DATA_HEADER;
		$code .= "include dirname(__FILE__).'/abstract/{$tableClassName}Abstract.class.php';\n";
		$code .= "class $tableClassName extends {$tableClassName}Abstract {\n";
		$code .= "}\n";
		return $code;
	}

	// Returns PHP code for the DAO class.
	// Returns false if the table was not found in the DDL.
	public function generateDAOClass($ddl, $tableName, $isAbstract = false) {
		if (($tableIdx = $ddl->getTableIdxInTopLevelEntities($tableName)) === false) {
			return false;
		}
		$table = &$ddl->topLevelEntities[$tableIdx];

		$concreteTableClassName = ucfirst($tableName);
		$tableClassName = $concreteTableClassName.($isAbstract ? 'Abstract' : '');
		$daoClassName = $concreteTableClassName.($isAbstract ? 'DAOAbstract' : 'DAO');

		// Open the class.
		$code = "<?php\n";
		$code .= DAOClassGenerator::$GENERATED_HEADER;

		// Include data class.
		if ($isAbstract) {
			$code .= "if (!class_exists('$concreteTableClassName', false)) include dirname(dirname(__FILE__)).'/$concreteTableClassName.class.php';\n\n";
		} else {
			$code .= "if (!class_exists('$concreteTableClassName', false)) include dirname(__FILE__).'/$concreteTableClassName.class.php';\n\n";
		}

		$code .= ($isAbstract ? 'abstract ' : '')."class $daoClassName {\n";
		$code .= "\tpublic static \$ALLOWED_QUERY_OPERATORS = array('=', '<>', '<', '<=', '>', '>=', 'beginsWith', 'contains', 'endsWith');\n";
		$code .= "\tpublic static \$ALLOWED_NUMERIC_QUERY_OPERATORS = array('=', '<>', '<', '<=', '>', '>=');\n";
		$code .= "\tpublic static \$ALLOWED_STRING_QUERY_OPERATORS = array('=', '<>', '<', '<=', '>', '>=', 'beginsWith', 'contains', 'endsWith');\n";
		$code .= "\tpublic static \$ALLOWED_BINARY_QUERY_OPERATORS = array('=', '<>');\n";

		// Emit member variables.
		$code .= "\tprotected \$connection;\n";
		$code .= "\tprotected \$cache = null;\n\n";

		$code .= "\tpublic function __construct(".'$connection, $cache = null'.") {\n";
		$code .= "\t\t".'$this->connection = $connection;'."\n";
		$code .= "\t\t".'$this->cache = $cache;'."\n";
		$code .= "\t}\n\n";

		$code .= "\tpublic function getCache() {\n";
		$code .= "\t\t".'return $this->cache;'."\n";
		$code .= "\t}\n\n";

		$code .= "\tpublic function setCache(".'$cache'.") {\n";
		$code .= "\t\t".'$this->cache = $cache;'."\n";
		$code .= "\t}\n\n";

		// insert function
		$code .= "\tpublic function insert(\$$tableName) {\n";
		$code .= "\t\t".'$ps = new PreparedStatement("insert into '.$tableName.' (';
		$sep = '';
		foreach ($table->columns as $column) {
			if (!$column->autoIncrement) {
				$code .= $sep.$column->name;
				if ($sep == '') $sep = ', ';
			}
		}
		$code .= ') values (';
		$sep = '';
		foreach ($table->columns as $column) {
			if (!$column->autoIncrement) {
				$code .= $sep.'?';
				if ($sep == '') $sep = ', ';
			}
		}
		$code .= ")\");\n";
		foreach ($table->columns as $column) {
			if (!$column->autoIncrement) {
				$code .= "\t\t".'$ps->set'.ucwords($this->getPSDataType($column)).'($'.$tableName.'->'.$column->name.");\n";
			}
		}
		$code .= "\t\t".'$result = $this->connection->executeUpdate($ps);'."\n";
		foreach ($table->columns as $column) {
			if ($column->autoIncrement) {
				$code .= "\t\t".'$'.$tableName.'->'.$column->name.' = $this->connection->getLastInsertId();'."\n";
			}
		}
		$code .= "\t\treturn \$result;\n";
		$code .= "\t}\n\n";

		if ($table->primaryKey !== false) {
			// update function
			$code .= "\tpublic function update(\$$tableName) {\n";

			$anyColumnsNotInPrimaryKey = false;
			foreach ($table->columns as $column) {
				if (($table->primaryKey === false) ||
					($table->primaryKey->getColumnIdx($column->name) < 0)) {
					$anyColumnsNotInPrimaryKey = true;
					break;
				}
			}

			if ($anyColumnsNotInPrimaryKey) {
				$code .= "\t\t".'$ps = new PreparedStatement("update '.$tableName.' set ';
				$sep = '';
				foreach ($table->columns as $column) {
					if (($table->primaryKey === false) ||
						($table->primaryKey->getColumnIdx($column->name) < 0)) {
						$code .= $sep.$column->name.' = ?';
						if ($sep == '') $sep = ', ';
					}
				}
				if ($table->primaryKey !== false) {
					$whereAnd = ' where ';
					foreach ($table->primaryKey->columns as $pkcol) {
						$column = $table->columns[$table->getColumnIdx($pkcol->name)];
						$code .= $whereAnd.$column->name.' = ?';
						$whereAnd = ' and ';
					}
				}
				$code .= "\");\n";
				foreach ($table->columns as $column) {
					if (($table->primaryKey === false) ||
						($table->primaryKey->getColumnIdx($column->name) < 0)) {
						$code .= "\t\t".'$ps->set'.ucwords($this->getPSDataType($column)).'($'.$tableName.'->'.$column->name.");\n";
					}
				}
				if ($table->primaryKey !== false) {
					foreach ($table->primaryKey->columns as $pkcol) {
						$column = $table->columns[$table->getColumnIdx($pkcol->name)];
						$code .= "\t\t".'$ps->set'.ucwords($this->getPSDataType($column)).'($'.$tableName.'->'.$column->name.");\n";
					}
				}
				$code .= "\t\t".'return $this->connection->executeUpdate($ps);'."\n";
			} else {	// if ($anyColumnsNotInPrimaryKey)
				$code .= "\t\treturn true;\n";
			}	// if ($anyColumnsNotInPrimaryKey) ... else

			$code .= "\t}\n\n";
		} // if ($table->primaryKey !== false)

		if ($table->primaryKey !== false) {
			// delete function
			$code .= "\tpublic function delete(";
			$sep = '';
			if ($table->primaryKey !== false) {
				foreach ($table->primaryKey->columns as $pkcol) {
					$code .= $sep.'$'.$pkcol->name;
					if ($sep == '') $sep = ', ';
				}
			}
			$code .= ") {\n";
			if ($table->primaryKey !== false) {
				$code .= "\t\t".'$ps = new PreparedStatement("delete from '.$tableName;
				$whereAnd = ' where ';
				foreach ($table->primaryKey->columns as $pkcol) {
					$code .= $whereAnd.$pkcol->name.' = ?';
					$whereAnd = ' and ';
				}
				$code .= "\");\n";
				foreach ($table->primaryKey->columns as $pkcol) {
					$column = $table->columns[$table->getColumnIdx($pkcol->name)];
					$code .= "\t\t".'$ps->set'.ucwords($this->getPSDataType($column)).'($'.$column->name.");\n";
				}
				$code .= "\t\t".'return $this->connection->executeUpdate($ps);'."\n";
			} else {
				$code .= "\t\treturn true;\n";
			}
			$code .= "\t}\n\n";
		} // if ($table->primaryKey !== false)

		if ($table->primaryKey !== false) {
			// load function
			$code .= "\tpublic function load(";
			$sep = '';
			if ($table->primaryKey !== false) {
				foreach ($table->primaryKey->columns as $pkcol) {
					$code .= $sep.'$'.$pkcol->name;
					if ($sep == '') $sep = ', ';
				}
			}
			$code .= ") {\n";
			if ($table->primaryKey !== false) {
				$code .= "\t\t".'$ps = new PreparedStatement("select * from '.$tableName;
				$whereAnd = ' where ';
				foreach ($table->primaryKey->columns as $pkcol) {
					$code .= $whereAnd.$pkcol->name.' = ?';
					$whereAnd = ' and ';
				}
				$code .= "\", 0, 1);\n";
				foreach ($table->primaryKey->columns as $pkcol) {
					$column = $table->columns[$table->getColumnIdx($pkcol->name)];
					$code .= "\t\t".'$ps->set'.ucwords($this->getPSDataType($column)).'($'.$pkcol->name.");\n";
				}
			}
			$code .= "\t\t".'$rows = $this->findWithPreparedStatement($ps);'."\n";
			$code .= "\t\t".'if (count($rows) > 0) return $rows[0];'."\n";
			$code .= "\t\treturn false;\n";
			$code .= "\t}\n\n";
		} // if ($table->primaryKey !== false)


		// finder functions for each column
		for ($finderi = 0; $finderi < count($table->columns); $finderi++) {
			$finderColumn = $table->columns[$finderi];
			$phpDataType = $this->getPHPDataType($finderColumn);
			$psDataType = $this->getPSDataType($finderColumn);

			// findBy<name>PS function.
			$code .= "\tpublic function findBy".ucwords($finderColumn->name).
				'PS($'.$finderColumn->name.", \$queryOperator = '=', \$orderBy = null, \$offset = 0, \$limit = 0) {\n";
			if ($psDataType == 'binary') {
				$oplist = 'self::$ALLOWED_BINARY_QUERY_OPERATORS';
				$sqlqoname = '$queryOperator';
			} else if ($phpDataType == 'string') {
				$oplist = 'self::$ALLOWED_STRING_QUERY_OPERATORS';
				$sqlqoname = '$sqlQueryOperator';
			} else {
				$oplist = 'self::$ALLOWED_NUMERIC_QUERY_OPERATORS';
				$sqlqoname = '$queryOperator';
			}
			$code .= "\t\tif (!in_array(\$queryOperator, $oplist)) \$queryOperator = $oplist"."[0];\n";
			if ($psDataType == 'string') {
				$code .=
					"\t\tif ((\$queryOperator == 'beginsWith') || (\$queryOperator == 'endsWith') || (\$queryOperator == 'contains')) {\n".
					"\t\t\t\$sqlQueryOperator = \$this->connection->likeOperator;\n".
					"\t\t\t\$needLower = (!\$this->connection->hasCaseInsensitiveLike);\n".
					"\t\t} else {\n".
					"\t\t\t\$sqlQueryOperator = \$queryOperator;\n".
					"\t\t\t\$needLower = false;\n".
					"\t\t}\n";
			}

			$code .= "\t\t\$ps = new PreparedStatement(\"select * from $tableName where ";
			if ($psDataType == 'string') {
				$code .=
					'".($needLower ? \'lower('.$finderColumn->name.')\' : \''.$finderColumn->name.'\')'.
					".' '.$sqlqoname.' '.".
					"(\$needLower ? 'lower(?)' : '?').";
			} else {
				$code .= $finderColumn->name.' '.$sqlqoname.' ?".';
			}
			$code .=
				"(((\$orderBy!==null)&&(\$orderBy!='')) ? (' order by '.\$orderBy) : ''), ".
				"\$offset, \$limit);\n";
			if ($psDataType == 'string') {
				$code .= "\t\tif (\$queryOperator == 'beginsWith') {\n";
				$code .= "\t\t\t".'$ps->set'.ucwords($psDataType).'($'.$finderColumn->name.".'%');\n";
				$code .= "\t\t} else if (\$queryOperator == 'endsWith') {\n";
				$code .= "\t\t\t".'$ps->set'.ucwords($psDataType).'(\'%\'.$'.$finderColumn->name.");\n";
				$code .= "\t\t} else if (\$queryOperator == 'contains') {\n";
				$code .= "\t\t\t".'$ps->set'.ucwords($psDataType).'(\'%\'.$'.$finderColumn->name.".'%');\n";
				$code .= "\t\t} else {\n";
				$code .= "\t\t\t".'$ps->set'.ucwords($psDataType).'($'.$finderColumn->name.");\n";
				$code .= "\t\t}\n";
			} else {
				$code .= "\t\t".'$ps->set'.ucwords($psDataType).'($'.$finderColumn->name.");\n";
			}
			$code .= "\t\treturn \$ps;\n";
			$code .= "\t}\n\n";

			// findBy<name> function.
			$code .= "\tpublic function findBy".ucwords($finderColumn->name).
				'($'.$finderColumn->name.", \$queryOperator = '=', \$orderBy = null, \$offset = 0, \$limit = 0) {\n";
			$code .= "\t\treturn \$this->findWithPreparedStatement(\$this->findBy".
				ucwords($finderColumn->name).'PS($'.$finderColumn->name.
				", \$queryOperator, \$orderBy, \$offset, \$limit));\n";
			$code .= "\t}\n\n";
		}

		// findAllPS function.
		$code .= "\tpublic function findAllPS".
			"(\$orderBy = null, \$offset = 0, \$limit = 0) {\n";
		$code .= "\t\t".'$ps = new PreparedStatement("select * from '.
			$tableName.
			"\".(((\$orderBy!==null)&&(\$orderBy!='')) ? (' order by '.\$orderBy) : ''), ".
			"\$offset, \$limit);\n";
		$code .= "\t\treturn \$ps;\n";
		$code .= "\t}\n\n";

		// findAll function.
		$code .= "\tpublic function findAll".
			"(\$orderBy = null, \$offset = 0, \$limit = 0) {\n";
			$code .= "\t\treturn \$this->findWithPreparedStatement(\$this->findAllPS".
				"(\$orderBy, \$offset, \$limit));\n";
			$code .= "\t}\n\n";

		$code .= "\tpublic function findWithPreparedStatement(\$ps) {\n";
		$code .= "\t\t\$cacheKey = null;\n";
		$code .= "\t\tif (\$this->cache !== null) {\n";
		$code .= "\t\t\t\$cacheKey = serialize(\$ps);\n";
		$code .= "\t\t\tif ((\$rows = \$this->cache->get(\$cacheKey)) !== false) {\n";
		$code .= "\t\t\t\treturn \$rows;\n";
		$code .= "\t\t\t}\n";
		$code .= "\t\t}\n";
		$code .= "\t\t\$rows = array();\n";
		$code .= "\t\t\$rs = \$this->connection->executeQuery(\$ps);\n";
		$code .= "\t\twhile (\$arr = \$this->connection->fetchArray(\$rs)) {\n";
		$code .= "\t\t\t\$row = new $concreteTableClassName();\n";
		$code .= "\t\t\t\$row->loadFromArray(\$arr);\n";
		$code .= "\t\t\t\$rows[] = \$row;\n";
		$code .= "\t\t}\n";
		$code .= "\t\t\$this->connection->freeResult(\$rs);\n";
		$code .= "\t\tif (\$this->cache !== null) {\n";
		$code .= "\t\t\t\$this->cache->set(\$cacheKey, \$rows);\n";
		$code .= "\t\t}\n";
		$code .= "\t\treturn \$rows;\n";
		$code .= "\t}\n";

		// Close the class.
		$code .= "}\n";

		unset($table);

		return $code;
	}

	public function generateStubDAOClass($ddl, $tableName, $isAbstract = false) {
		if (($tableIdx = $ddl->getTableIdxInTopLevelEntities($tableName)) === false) {
			return false;
		}
		$table = &$ddl->topLevelEntities[$tableIdx];

		$tableClassName = ucfirst($tableName);
		$daoClassName = $tableClassName.'DAO';

		// Open the class.
		$code = "<?php\n";
		$code .= DAOClassGenerator::$STUB_DAO_HEADER;
		$code .= "include dirname(__FILE__).'/abstract/{$daoClassName}Abstract.class.php';\n";
		$code .= "class $daoClassName extends {$daoClassName}Abstract {\n";
		$code .= "}\n";
		return $code;
	}

	// $column must be a DDLTableColumn instance.
	public function getPHPEncodedDefaultValue(&$column) {
		switch ($column->sysVarDefault) {
		case 'CURRENT_TIMESTAMP':
			return "date('Y-m-d H:i:s')";
			break;
		}
		if ( ($column->allowNull) && ($column->defaultValue === null) ) {
			return 'null';
		}
		switch ($column->type) {
		case 'binary':
		case 'varbinary':
		case 'blob':
			if ($column->defaultValue == '') return '\'\'';
			return sprintf("base64_decode('%s')", base64_encode($column->defaultValue));
		case 'integer':
		case 'smallint':
		case 'bigint':
			$val = $column->defaultValue;
			if ($val == '') $val = '0';
			return $val;
		case 'decimal':
			$val = $column->defaultValue;
			return number_format((double)trim($column->defaultValue), $column->scale, '.', '');
		default:
			$val = $column->defaultValue;
			if ($val == '') {
				switch ($column->type) {
				case 'date': $val = '0001-01-01'; break;
				case 'datetime': $val = '0001-01-01 00:00:00'; break;
				case 'time': $val = '00:00:00'; break;
				}
			}
			return '"'.$this->phpencode($val).'"';
		}
	}

	// $column must be a DDLTableColumn instance.
	public function getPHPDataType(&$column) {
		$dataType = '';
		switch ($column->type) {
		case 'integer':
		case 'smallint':
		case 'bigint':
			return 'int';
		case 'decimal':
			return 'double';
		default:
			return 'string';
		}
	}

	// $column must be a DDLTableColumn instance.
	public function getPSDataType(&$column) {
		if (($column->type == 'binary') ||
			($column->type == 'varbinary') ||
			($column->type == 'blob')) {
			return 'binary';
		}
		return $this->getPHPDataType($column);
	}

	// This must ONLY be used with strings which will be enclosed in double-quotes ("...").
	public function phpencode($s) {
		$ss = '';
		for ($i = 0; $i < strlen($s); $i++) {
			$c = $s[$i];
			$co = ord($c);
			if (($co >= 0x20) && ($co <= 0x7e)) {
				$ss .= $c;
				continue;
			}
			switch ($c) {
			case "\n": $ss .= "\\n"; break;
			case "\r": $ss .= "\\r"; break;
			case "\t": $ss .= "\\t"; break;
			case "\v": $ss .= "\\v"; break;
			case "\f": $ss .= "\\f"; break;
			case "\\": $ss .= "\\\\"; break;
			case "\$": $ss .= "\\\$"; break;
			case "\"": $ss .= "\\\""; break;
			default:
				$hex = dechex($co);
				while (strlen($hex) < 2) $hex = '0'.$hex;
				$ss .= "\\x".$hex;
			}
		}
		return $ss;
	}
} // DAOClassGenerator

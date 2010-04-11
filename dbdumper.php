<?php

/**********************************************************
 *
 *	Database dumper 
 *
 *	author:     Michal Svec, pan.svec@gmail.com
 *	website:	http://misa.ufb.cz
 *	published:  3.4.2010
 *
 *
 *	this work is licensed under a 
 *	Creative Commons Attribution 3.0 License.
 *	http://creativecommons.org/licenses/by/3.0/
 *
 *	This means you may use it for any purpose,
 *	and make any changes you like.
 *	All I ask is that you include a link back.
 *
 *	Are you using this tool? Send me an email
 *	to pan.svec@gmail.com
 *
 **********************************************************/


error_reporting(E_ALL ^ E_NOTICE);

define('COUNT', 3000);		// rows selected in one select
define('DEBUG', 3);	
define('MAXFILESIZE', 8000000);	// maximum size of export file


function pr($data) {
	echo "<pre>";
	print_r($data);
	echo "</pre>";
}


// debug function - just echoing strings to page
function debug($string, $lvl = 0) {
	if($lvl > DEBUG)
		echo $string."<br />";
}


class awesomeDumper3000 {

	var
		$filename,	// output file name
		$from,		// offset
		$table,		// actual table
		$ignoredTables = array(),	// don't export these tables
		$droptable,	// drop table command
		$interval,	// refresh interval
		$encoding,	//connection encoding
		$maxFileSize,	// maximum size of *.sql file
		$rowCount;		// number of rows in one SQL select per iteration


	function __construct() {
		$this->table = mysql_escape_string($_GET['table']);
		$this->from = (int) (!empty($_GET['from']) ? $_GET['from'] : 0);
		$this->filename = (!empty($_GET['filename']) ? $_GET['filename'] : "dump.sql");
		$this->droptable = ($_GET['droptable']==1 ? 1 : 0);
		$this->encoding = (!empty($_GET['encoding']) ? $_GET['encoding'] : "utf8");
		$this->interval = (!empty($_GET['interval']) ? (int) $_GET['interval'] : 1);
		$this->ignoredTables = (empty($_GET['ignored']) ? array(): $_GET['ignored']);
		$this->rowCount = (empty($_GET['count']) ? COUNT : $_GET['count']);
		$this->maxFileSize = (empty($_GET['maxfilesize']) ? MAXFILESIZE : $_GET['maxfilesize']);

		debug("<b>table:<b> ".$this->table." from ".$this->from, 0);
	}

	/**
	 *	gets header of table
	 *	param:	string	table name
	 */
	function getCreateTable($table) {
		debug("function: getCreateTable(".$table.")",2);

		if(empty($table))
			return "";

		if($this->droptable == 1)
			$out = "DROP TABLE $table;";

		$sql = 'SHOW CREATE TABLE '.$table;

		$export = mysql_query($sql);
		$table = mysql_fetch_array($export);

		return "\n\n\n".$out."\n".$table[1].";\n\n";
	}
	
	
	/**
	 *	gets column list for selected table
	 *
	 *	param:	string	table name
	 */	
	function getColumns($tablename) {
		debug("<b>function<b>: getColumns(".$tablename.")",2);

		$sql = 'SHOW COLUMNS FROM '.$tablename;

		$query = mysql_query($sql);
		
		$columns = array();
		while($data = mysql_fetch_array($query)) {
			$columns[] = $data['Field'];
		}

		return $columns;
	}
	
	
	/**
	 *	Simple output function
	 *
	 *	param:	string	data to append
	 */
	function outputFileAppend($data) {
		debug("function: outputFileAppend()",2);

		// if actual file is larger than MAXFILESIZE, jump to next file
		if((file_exists($this->filename) ? filesize($this->filename) : 0) + strlen($data) > $this->maxFileSize) {
			preg_match("/([^\.]*)\.?([0-9]*)\.([^\.]+)/i", $this->filename, $matches);
			
			if($matches[2] != "")
				++$matches[2];
			else 
				$matches[2] = 1;
			
			$this->filename = $matches[1].".".$matches[2].".".$matches[3];
		} 
		
		debug("output file: ".$this->filename,2);
		
		file_put_contents($this->filename, $data, FILE_APPEND);
	}
	
	
	function exportTable($table, $from) {
		debug("function: exportTable(".$table.", ".$from.")",2);
			
		if(empty($table))
			return "";
		
		$sql = 'SELECT COUNT(*) FROM '.$table;
		$query = mysql_query($sql);
		$rows = mysql_fetch_row($query);
		
		debug("<b>total rows:</b> ".$rows[0]);

		$sql = 'SELECT * FROM '.$table.' LIMIT '.$from.', '.$this->rowCount;
		$query = mysql_query($sql);

		$out = "";
		$row_count = 0;
		while($data = mysql_fetch_array($query)) {
			$i = 0;
			$row = array();
			foreach($data as $key=>$val) {
				if($i%2 != 0)
					$row[$key] = "'".mysql_escape_string($val)."'";

				$i++;
			}
			$out .= 'INSERT INTO '.$table.' ('.join(", ", array_keys($row)).') VALUES ('.join(", ", array_values($row)).');'."\n";
			$row_count++;
		}

		debug("<b>exporting</b>: ".$row_count." rows from <b>$from</b>",1);

		$this->outputFileAppend($out);
		
		debug("function exportTable returns: ".(($from+$count > $rows[0]) ? "false" : "true"),2);
		
		if($from+$count > $rows[0])
			return false;
		else
			return true;
	}
	
	function getTableList() {
		debug("function: getTableList()",2);

		$sql = 'SHOW TABLES';
		$query = mysql_query($sql);
		
		while($data = mysql_fetch_array($query)) {
			$tables[] = $data[0];
 		}
 
 		return $tables;
	}
	
	
	function dumpDatabase() {
		debug("function: dumpDatabase()",2);
		$tables = $this->getTableList();

		if(empty($this->table)) {
		
			$i = 0;
			while(in_array($tables[$i], $this->ignoredTables)) {
				$i++;
			}
			if($i > count($tables))
				return false;

			$this->table = $tables[$i];
			$this->outputFileAppend($this->getCreateTable($this->table));
		}
		
		$end = $this->exportTable($this->table, $this->from);
		
		
		if($end)
			debug("exporttable: returned true",2);
		else
			debug("exporttable: returned false",2);

		if(!$end) {
			// ok, we're heading to next table
			foreach($tables as $k=>$v) {
				if($v == $this->table) {
					$i=1;
					while(in_array($tables[$k+$i], $this->ignoredTables) && ($k+$i <= count($tables))) {
						$i++;
					}
					if($k+$i < count($tables)) {
						$this->table = $tables[$k+$i];
						
						$this->outputFileAppend($this->getCreateTable($this->table));
						
						$this->from = 0;
						debug("<b>next table:</b> ". $this->table,1);
						return true;
					}
					else {
						debug("<b>same table</b>",1);

						$this->from = ($this->from+COUNT);
						return false;
					}
				}
			}
			
		}
		else {
			$this->from = ($this->from+$this->rowCount);
			return true;
		}
	}
	
	
	function setIgnoreTable($list) {
		$this->ignoredTables = $list;
	}
	
	function run() {
		debug("function: run()",2);

		$dbinfo = parse_ini_file(dirname(__FILE__).'/dbdumper.ini');
		
		$db = mysql_connect($dbinfo['dbhost'],$dbinfo['dbuser'],$dbinfo['dbpass']);
		if (!$db) {
			echo '<span class="err">Could not connect: '.mysql_error().'</span>';
			return;
		}
		if(!mysql_select_db($dbinfo['dbname']))  {
			echo '<span class="err">Could not select database: '.mysql_error().'</span>';
			return;
		}
		else {

			// exporting stop
			if(isset($_GET['stop'])) {
				echo '<a class="control" href="?run">Start</a><br />';	
			}
			// pause in export - we will start on same table and row
			else if(isset($_GET['pause'])) {
				echo '<a class="control" href="?table='.$this->table.'&from='.($this->from).'&filename='.$this->filename.'&run">Resume</a><br />';
			
			}
			// export is running
			else if(isset($_GET['run'])) {
				@mysql_query("SET CHARACTER SET utf8",$db);
				@mysql_query("SET NAMES UTF8",$db);
				@mysql_query("SET character_set_results=utf8",$db);
				@mysql_query("SET character_set_connection=utf8",$db);
				@mysql_query("SET character_set_client=utf8",$db); 	
			
				if($this->dumpDatabase()) {
					echo '<a class="control" href="?stop">Stop</a><br />';
					echo '<a class="control" href="?pause">Pause</a><br />';
					
					$ITString = array();	// ignored tables url string
					foreach($this->ignoredTables as $it) {
						$ITString[] = "ignored[]=".$it;
					}

					echo '<meta HTTP-EQUIV="REFRESH" content="1; url=?table='.$this->table.'&filename='.$this->filename.'&from='.($this->from).'&droptable='.$this->droptable.'&encoding='.$this->encoding.'&interval='.$this->interval.'&'.join("&", $ITString).'&run">';
				}
				// the end
			else {
					echo '<h2>End of backup!</h2><a class="control" href="?run">Restart</a><br />';
					echo '<a class="control" href="'.$_SERVER['SCRIPT_NAME'].'">New backup</a>';
				}
			}
			// export start
			else {
?>
				<form action="" method="get">
					<input type="checkbox" name="droptable" value="1" /> Add DROP TABLE command?<br />
					Refresh interval: <input type="text" name="interval" value="2" size="2" /> seconds<br />
					Database encoding: <input type="text" name="encoding" value="utf8" size="8" /><br />
					<span class="hint">used for SET CHARACTER SET, NAMES, character_set_results,character_set_connection and character_set_client </span><br />
					Rows in one select: <input type="text" name="count" value="<?php echo COUNT ?>" size="8" />
					<br /><span class="hint">used in LIMIT command </span>
					<br />
					Maximum size of exported file: <input type="text" name="maxfilesize" value="<?php echo MAXFILESIZE ?>" size="8" />bytes<br />
					<span class="hint">if this limit is exceed, the script will create new file with his sequence number</span><br /><br />
					Ignore these tables:<br />
<?php
					foreach($this->getTableList() as $table)
						echo '&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" name="ignored[]" value="'.$table.'">'.$table."<br />\n";
?>
					
					<input type="hidden" name="run" value="1" /><br /><br />
					<button class="control" type="submit">Run</button><br />
				</form>
<?php
			}
		}
	}
}
?>

<html>
<head>
	<title>Database dumper</title>
	
	<style>
		.control { font-size: 20px; font-weight: bold; color: #f00000; text-decoration: none; }
		.hint { font-size: 10px; }
	</style>
	
</head>

<body style="background: #444; color: #eee;">
<div id="wrap" style="margin:auto; width: 600px; border: 1px solid #eee; background: #fff; margin-top: 30px;">

	<div id="header" style="width: 100%; height: 50px;text-align: center; border-bottom: 1px solid #444; ">
		<h1 style="color: #f00000">Database dumper 1.0</h1>	
	</div>
	
	<div id="main" style=" padding: 20px; color: #111;">
<?php
		$dumper = new awesomeDumper3000	();
		$dumper->run($dbinfo);
?>		
	</div>
	
</div>
</body>
</html>


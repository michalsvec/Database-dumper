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


define('TABLES', 5);
define('ROWS', 10000);

function generateRandomWord($min=4,$max=7) {
	
	$word = "";

	$lim = rand($min,$max);
	for ($i = 1; $i <= $lim; $i++) {
		$word .= chr(ord('a') + rand(0,25));
	}

	return $word;
}


// database connection
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



for($i=0; $i<TABLES; $i++) {
	
	$table = generateRandomWord();

	mysql_query('CREATE TABLE  `'.$table.'` (`col1` INT( 10 ) NOT NULL ,`col2` VARCHAR( 255 ) NOT NULL);');

	for($j=0; $j<ROWS; $j++) {
		mysql_query("INSERT INTO  `$table` (`col1` ,`col2`) VALUES ('$j',  '".generateRandomWord(100,200)."');");
	}
}

echo "done";
?>


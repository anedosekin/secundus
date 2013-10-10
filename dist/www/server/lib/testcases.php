<?php
/*
	howto
	add task
	$comlist[]='
	{
		"taskname":" ... ",
		"commands":[ {  ...  }]
	}
	if need test result add "result":[[ ... ],[ ... ] ...]
	if blob add	"blobs":{"filename":"@full path", ... }
 */
// SIMPLE COMMS

$comlist[]='
{
	"taskname":"simple inserts + select",
	"result":[[["txt1000","1000"]],[["new text for 1001","1001"]],[["txt1000","1000"],["new text for 1001","1001"]]],
	"commands":[
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"txt1000"},{"DATA":"1000"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"txt1001"},{"DATA":"1001"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"},{"intinf":"intdata"}],
			"WHERE":["intdata=?"],"LINK":[{"DATA":"1000"}]},
		{"TYPE":"UPDATE","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"}],"WHERE":["intdata=?"],
			"LINK":[{"DATA":"new text for 1001"},{"DATA":"1001"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"},{"intinf":"intdata"}],
			"WHERE":["intdata=?"],"LINK":[{"DATA":"1001"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"},{"intinf":"intdata"}],
			"WHERE":["intdata BETWEEN ? AND ?"],"LINK":[{"DATA":"1000"},{"DATA":"1010"}]},
		{"TYPE":"DELETE","FROM":"rmn_exp","WHERE":["intdata BETWEEN ? AND ?"],
			"LINK":[{"DATA":"1000"},{"DATA":"1010"}]}
		
	]		
}';
/*
// BLOBS
$comlist[]='
{
	"taskname":"insert & select BLOB (text blob)",
	"blobs":{"text1.txt":"@/home/rmn/html/text1.txt","text2.txt":"@/home/rmn/html/text2.txt"},
	"result":[[["blob text1","test text\nwith enter\n"]],[["new blolb data","this is text, just text.\nand it too.\n"]]],
	"commands":[
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"intdata":"?"},{"txtdata":"?"},{"blobdata":"?"}],
			"LINK":[{"DATA":"1005"},{"DATA":"blob text1"},{"DATA":"text1.txt","ISFILE":true}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"},{"blobinf":"blobdata"}],
			"WHERE":["intdata=?"],"LINK":[{"DATA":"1005"}]},
		{"TYPE":"UPDATE","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"blobdata":"?"}],"WHERE":["intdata=?"],
 			"LINK":[{"DATA":"new blolb data"},{"DATA":"text2.txt","ISFILE":true},{"DATA":"1005"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"},{"blobinf":"blobdata"}],
			"WHERE":["intdata=?"],"LINK":[{"DATA":"1005"}]}
	]
}
';
*/
// TEST SID
$comlist[]='
{
	"taskname":"SID test",
	"commands":[
		{"TYPE":"GENSID","LINK":[{"DATA":"pfff"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
  			"LINK":[{"DATA":"this is sid:","ADDSID":true},{"DATA":"1006"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"}],"WHERE":["intdata=?"],
			"LINK":[{"DATA":"1006"}]},
		{"TYPE":"UPDATE","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"}],"WHERE":["intdata=?"],
 			"LINK":[{"DATA":"","ADDSID":true},{"DATA":"1006"}]},
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[{"txtinf":"txtdata"}],"WHERE":["intdata=?"],
			"LINK":[{"DATA":"1006"}]}
	]
}';

// RECURSIVE SELECT
// table rmn_insel
// CREATE TABLE rmn_insel (id bigint,  intdata bigint,  txtinfo text,  justtxt text);
$comlist[]='
{
	"taskname":"Recursive select. Need 2 db tables",
	"result":[[[[["3","inf3","text3"],["6","inf6","text6"],["9","inf9","text9"]],"main dt 1010","1010"],[[["8","inf8","text8"],["10","inf10","text10"]],"main dt 1013","1013"],[[["3","inf3","text3"],["6","inf6","text6"],["9","inf9","text9"]],"main dt 1016","1010"]]],
	"commands":[
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"1"},{"DATA":"1"},{"DATA":"inf1"},{"DATA":"text1"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"2"},{"DATA":"2"},{"DATA":"inf2"},{"DATA":"text2"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"3"},{"DATA":"1010"},{"DATA":"inf3"},{"DATA":"text3"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"4"},{"DATA":"4"},{"DATA":"inf4"},{"DATA":"text4"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"5"},{"DATA":"5"},{"DATA":"inf5"},{"DATA":"text5"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"6"},{"DATA":"1010"},{"DATA":"inf6"},{"DATA":"text6"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"7"},{"DATA":"7"},{"DATA":"inf7"},{"DATA":"text7"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"8"},{"DATA":"1013"},{"DATA":"inf8"},{"DATA":"text8"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"9"},{"DATA":"1010"},{"DATA":"inf9"},{"DATA":"text9"}]},
		{"TYPE":"INSERT","FROM":"rmn_insel","FIELDS":[{"id":"?"},{"intdata":"?"},{"txtinfo":"?"},{"justtxt":"?"}],
  			"LINK":[{"DATA":"10"},{"DATA":"1013"},{"DATA":"inf10"},{"DATA":"text10"}]},		
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1010"},{"DATA":"1010"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1011"},{"DATA":"1011"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1012"},{"DATA":"1012"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1013"},{"DATA":"1013"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1014"},{"DATA":"1014"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1015"},{"DATA":"1015"}]},
		{"TYPE":"INSERT","FROM":"rmn_exp","FIELDS":[{"txtdata":"?"},{"intdata":"?"}],
			"LINK":[{"DATA":"main dt 1016"},{"DATA":"1010"}]},		
		{"TYPE":"SELECT","FROM":"rmn_exp","FIELDS":[
			{"TYPE":"SELECT","FROM":"rmn_insel","FIELDS":[{"idd":"id"},{"info":"txtinfo"},{"just":"justtxt"}],
				"WHERE":["intdata=?"],"LINK":[{"DATA":"intdata","INSEL":true}]},
		 	{"txtinf":"txtdata"},{"intinf":"intdata"}],"WHERE":["intdata=? OR intdata=?"],"LINK":[{"DATA":"1010"},{"DATA":"1013"}]}
	]
}';

// clear all
$comlist[]='
{
	"taskname":"Clear rows from 1000 to 1020 rmn_exp, rmn_insel",
	"commands":[
		{"TYPE":"DELETE","FROM":"rmn_exp","WHERE":["intdata BETWEEN ? AND ?"],
			"LINK":[{"DATA":"1000"},{"DATA":"1020"}]},
		{"TYPE":"DELETE","FROM":"rmn_insel","WHERE":["intdata BETWEEN ? AND ?"],
			"LINK":[{"DATA":"1000"},{"DATA":"1020"}]}

	]
}';



?>
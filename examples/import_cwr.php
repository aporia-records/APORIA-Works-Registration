<?
/*
	import_cwr.php
	Copyright © 2016, 2017 Gord Dimitrieff <gord@aporia-records.com>

	This file is an example of how CWR registration data can be imported using the AporiaWorksRegistration library.
	It has been written for a Filemaker database using the SmartPill PHP plugin, but can obviously be adapted to work
	with any other database system.

*/

require_once("fm_functionlib.php"); // FM database functions
include("AporiaWorksRegistration.php"); // EWR/CMRRA data class

$table = "Import_Works";
$msg_table = "Import_EWR_Messages";

$submitter_code	= fm_evaluate("\$submitter_code");
$submitter_ipi	= fm_evaluate("\$submitter_ipi");

$filespec = fm_select_file ("Select CWR file to load");


if(!$filespec) exit; //quit if user cancels the import operation

$ack = new WorksRegistration($submitter_code, $submitter_ipi);
$ack->CWR_Filename = basename($filespec);

if($handle = fopen($filespec, "r"))
{
	$ack->ReadCWR($handle);
	fclose($handle);
}

ksort($ack->Works);

$ack->CurrentWork = 0; // Set to 0 so that the first call to NextWork() will advance to position 1.

while($ack->NextWork())
{
	$work = $ack->getWorkDetails();
	if(!$work) printf("No work returned!\n");

	$columns = "";
	$values = "";

	// Define fields to import
	$import_fields = array(
			'Title'							=>	$work['Work_Title'],
			'Collective_Registration_Date'	=>	$work['Registration_Date'],
			'Collective_Work_ID'			=>	$work['Collective_Work_ID'],
			'Duration'						=>	$work['Duration'],
			'ISWC'							=>	$work['ISWC'],
			'Work_ID'						=>	$work['Work_ID'],
			'Transaction_Status'			=>	$work['Transaction_Status']
		);
	
	$cols = array_keys($import_fields);

	for($i = 0; $i<count($cols); $i++)
	{
		$columns .= sprintf("%s", $cols[$i]);
		if($i<count($cols)-1) $columns .= ", ";

		// Convert character set for Mac OS / Filemaker
		$values .= sprintf("'%s'", str_replace("'", "''", iconv('ISO-8859-1', 'macintosh//translit', $import_fields[$cols[$i]]))); // 'ASCII//TRANSLIT'
		if($i<count($cols)-1) $values .= ", ";
	}
	
	$sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, $columns, $values);
	printf("%s\n", $sql);

	if(fm_sql_execute($sql) > 0) printf("%s\nsql:\n%s\n\n", fm_get_last_error(), $sql);
	
	if(isset($work['Messages']) && count($work['Messages'] > 0)) // Error messages attached to this transaction
	{
		foreach($work['Messages'] as $message)
		{
			$sql = sprintf("INSERT INTO %s (Work_ID, Message_Record_Type, Message_Level, Validation_Number, Message_Type, Message_Text) VALUES ('%d', '%s', '%s', '%s', '%s', '%s')", 
						$msg_table,
						$work['Work_ID'],
						$message['Original_Record_Type'],
						$message['Message_Level'],
						$message['Validation_Number'],
						$message['Message_Type'],
						$message['Message_Text']
						);
			printf("\nsql:\n%s\n", $sql);
			if(fm_sql_execute($sql) > 0) printf("%s\nsql:\n%s\n\n", fm_get_last_error(), $sql);
		}
	}

	if(isset($work['ACK'])) // This entry has an acknowledgement - log it in the CWR audit table
	{
			$sql = sprintf("INSERT INTO CWR_Audit (Work_ID, Transaction_Status, Society_Work_ID, Date_Registration_Accepted, Society_Code, CWR_Filename) VALUES ('%s', '%s', '%s', '%s', '%d', '%s')", $work['Work_ID'], $work['Transaction_Status'], $work['Collective_Work_ID'], $work['Registration_Date'], intval($ack->Header['Sender_IPI']), $ack->CWR_Filename);
			printf("%s\n", $sql);
			if(fm_sql_execute($sql) > 0) printf("%s\nsql:\n%s\n\n", fm_get_last_error(), $sql);

			if(!empty($work['ISWC']))
			{
				$sql = sprintf("UPDATE Songs SET ISWC = '%s' WHERE Work_ID = '%d'", $work['ISWC'], $work['Work_ID']);
				printf("%s\n", $sql);
				if(fm_sql_execute($sql) > 0) printf("%s\nsql:\n%s\n\n", fm_get_last_error(), $sql);				
			}
	}
} //end of while()

printf("\nReady.\n");
?>
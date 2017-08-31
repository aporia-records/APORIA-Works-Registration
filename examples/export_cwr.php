<?php
/*
		Last modification: 12 July 2017
*/

require_once("fm_functionlib.php"); // FM database functions
include("WorksRegistration-v2.php"); // EWR/CMRRA data class

$debug = false;

$submitter_code	= fm_evaluate("\$submitter_code");
$submitter_ipi	= fm_evaluate("\$submitter_ipi");
$File_Type		= fm_evaluate("\$File_Type");

/* $delivery set to true by Filemaker */

if(!isset($delivery))	$delivery = false;
if($delivery) 
{
	printf("Delivery set.\n");
	$delivery_list	= fm_evaluate("\$delivery_list");
	$delivery_list	= explode( ',', $delivery_list );	
}

/* need to add:
$this->Contact_Name
$this->Contact_ID,
*/

//if($found_set)
//	$where = sprintf("Work_ID IN (%s)", fm_evaluate("\$found_set"));
	$where = sprintf("Work_ID IN (%s)", fm_evaluate("FoundSetToSQL ( Songs::Work_ID )"));

/* Load relevant tables from Filemaker */
$songs = fm_getasarray(array(
	'select'	=> 'Work_ID, Title, ISWC, ISRC, Copyright_Date, Copyright_Number, Duration, Alt_Title, Alt_Title_2, Alt_Title_3, Lang, Artist_id, Performer_2, Performer_3, Recorded_Flag, Musical_Work_Distribution_Category, Version_Type, CWR_Work_Type, Text_Music_Relationship, Composite_Type, Music_Arrangement, Lyric_Adaptation, Composite_Component_Count, Grand_Rights_Ind',
	'from'		=> 'Songs',
	'where'		=> $where), $debug);

$shares = fm_getasarray(array(
	'select'	=> 'Work_ID, IPI, Role, MR_Collection_Share, MR_Ownership_Share, PR_Collection_Share, PR_Ownership_Share, Shares_Change, Link, coPublisher, Territory_Set',
	'from'		=> 'Shares',
	'order_by'	=> 'Work_ID, Role'));

$shareholders = fm_getasarray(array(
	'select'	=>	'IPI, First_Name, Last_Name, Controlled, PRO, US_Rep, MRO',
	'from'		=> 'Shareholders',
	'order_by'	=> 'IPI ASC'));
	
$a = fm_getasarray(array(
	'select'	=> 'z_id, Artist_Name',
	'from'		=> 'Artists',
	'order_by'	=> 'z_id ASC'));

$tis = fm_getasarray(array(
	'select'	=> 'Territory_Set_id, TIS_Number, Indicator_Code',
	'from'		=>	'Territory_Sets_TIS'));

foreach($tis as $ter)
{
	$Territory[$ter['Territory_Set_id']][] = array('Indicator' => $ter['Indicator_Code'], 'TIS_Number' => $ter['TIS_Number']);
}

unset($tis);

/* Load society cross-references */
$XRF = array();
$xrfs = fm_getasarray(array(
	'select'	=>	'Work_ID, Society_Code, Society_Work_ID',
	'from'		=>	'CWR_Audit',
	'where'		=>	'Society_Work_ID IS NOT NULL',
	'order_by'	=>	'Work_ID ASC'));

foreach($xrfs as $xrf)
{
//	$work_id =& $xrf['Work_ID'];
	$XRF[$xrf['Work_ID']][] = array('Organisation_Code' => $xrf['Society_Code'], 'Identifier' => $xrf['Society_Work_ID'], 'Identifier_Type' => 'W', 'Validity' => 'Y');
}
unset($xrfs);



foreach($a as $art)		// Create artist[] array with z_id as key, for easy mapping to songs['Artist_id']
	if(!empty($art['Artist_Name'])) $artist[$art['z_id']] = $art['Artist_Name'];

unset($a);

$isrc_set = array();
$tracks = array();
$tracks_releases = array();

foreach($songs as $s) $isrc_set[]=sprintf("'%s'", $s['ISRC']);

if(count($isrc_set) > 0) 
{
	$isrc_set = implode(", ", $isrc_set);

	$tracks = fm_getasarray(array(
//		'select'	=> 'id, Title, ISRC, Duration',
		'select'	=> 'id, Title, Version, Artist_Name, Label_Name, ISRC, Duration',
		'from'		=> 'Tracks',
		'where'		=> sprintf("ISRC IN (%s)", $isrc_set)
	));

	$tracks_releases = fm_getasarray(array(
		'select'	=> 'ISRC, UPC',
		'from'		=> 'Tracks_Releases',
		'where'		=> sprintf("ISRC IN (%s)", $isrc_set)
	));
}

$upc_set = array();
foreach($tracks_releases as $tr) $upc_set[]=sprintf("'%s'", $tr['UPC']);

$releases = array();

if(count($upc_set) > 0)
{
	$upc_set = implode(", ", $upc_set);

	$releases = fm_getasarray(array(
//		'select'	=> 'id, Title, Release_Date, BIEM_Media_Type, Best_UPC, Best_Catalogue_Number, Label',
		'select'	=> 'id, Title, Version, Release_Date, BIEM_Media_Type, Best_UPC, Best_Catalogue_Number, Label',
		'from'		=> 'Releases',
		'order_by'	=> 'Release_Date ASC',
		'where'		=> sprintf("Best_UPC IN (%s)", $upc_set)
	));
}


/*
Releases:
1 - get release details from Tracks_Releases, Releases and Labels tables - limit to 1 release, oldest release date available - preference to digital releases
2 - sort into an array that can be looked up by isrc value

*/

$songcount = count($songs);
$sharecount = 0;

printf("Creating registration file for %d works.\n", $songcount);

$cwr = new WorksRegistration($submitter_code, $submitter_ipi);

/* Build tracks and releases tables */
foreach ($tracks as $t) if(!empty($t['ISRC'])) $cwr->addTrack($t);
unset($tracks);

foreach($tracks_releases as $t) if(!empty($t['ISRC'])) $cwr->addTrack($t);
unset($tracks_releases);

foreach($releases as $r)
{
	$r['UPC'] = $r['Best_UPC'];
	$r['Cat_No'] = $r['Best_Catalogue_Number'];
	$cwr->addRelease($r);
}
unset($releases);

/* Build song and share heirarchy */
foreach($songs as $song)
{
	if(empty($song['Artist_id']))
	{
		printf("ERROR: No artist defined in the work '%s' (Work ID: %d).\n", $song['Title'], $song['Work_ID']);
		exit;
	}
	$song['Performer_1'] = $artist[$song['Artist_id']];
	unset($song['Artist_id']);

	$cwr->NewWork($song);
	foreach($shares as $share)
		if($share['Work_ID'] == $song['Work_ID'])
		{
			$cwr->NewShare($share);

			foreach($Territory[$share['Territory_Set']] as $territory)
				$cwr->addTerritory($territory['TIS_Number'], $territory['Indicator']);
		}

	if(isset($XRF[$song['Work_ID']]))
		foreach($XRF[$song['Work_ID']] as $xrf)
			$cwr->addXRef($xrf);
}

foreach($shareholders as $shareholder)
	if(!$cwr->addShareholder($shareholder)) printf("%s\n", $cwr->LastMsg());

$filepath = fm_evaluate("Settings::EWR_Filepath");

switch($File_Type)
{
	case 'CWR':
	{
//		$cwr->CWR_Version = 2.2; // Test v2.2

		/* Determine appropriate file name */
		$cwr->receiver_society = fm_evaluate("Settings::CWR_Society");
		$x=0;
		while(file_exists($filepath.$cwr->cwr_filename()))
		{
			printf("%s exists.\n", $filepath.$cwr->cwr_filename());
			$cwr->file_version++; //Add new versions if file exists
			$x++;
		}
		if($x) printf("\n%d previous CWR file versions found.\n\n", $x);

		/* Write EWR file */
		$CWR_Works = $cwr->WriteCWR();

		if($CWR_Works) // Only proceed if works were actually entered into the CWR file.
		{
			$filepath = $filepath.$cwr->cwr_filename();
			$handle = fopen($filepath, "w");
			printf("\n%s: %d bytes written.\n", $filepath, fwrite($handle, $cwr->CWR_File_Contents));
			fclose($handle);

			foreach($CWR_Works as $work_id)
			{
				$sql = sprintf("INSERT INTO CWR_Audit (Work_ID, Date_Registration_Sent, Society_Code, CWR_Filename) VALUES ('%s', '%s', '%03d', '%s')", $work_id, date('Y-m-d'), $cwr->receiver_society, $cwr->cwr_filename());
				if(fm_sql_execute($sql)>0) printf("%s\nsql:\n%s\n\n", fm_get_last_error(), $sql);
			}
			printf("%d works included in this CWR file.\n\n", count($CWR_Works));

			$filename = $cwr->cwr_filename();
			$delivery_format = "CWR";

		}
		else printf("\n*** CWR file not written - zero works to include. ***\n");
		
		break;
	}
	case 'EXCEL':
	{
		/* Write CMRRA .csv file */
		printf("Writing Excel Format Catalogue File:\n%s\n\n", $filepath.$cwr->EXCEL_Filename);
		$handle = fopen($filepath.$cwr->EXCEL_Filename, "w");
		$cwr->WriteCatalogue($handle);
		fclose($handle);

		$filename = $cwr->EXCEL_Filename;
		$delivery_format = "CSV";

		break;
	}
	default: printf("No file type specified!\n");
}

printf("Done.\n");

/*
if(count($cwr->Msgs))
{
	printf("\n***** Error messages: *****\n");
	foreach($cwr->Msgs as $msg) printf("%s\n", $msg);
}
*/
?>
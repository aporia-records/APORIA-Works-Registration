<?php

date_default_timezone_set("America/Toronto");

include("AporiaWorksRegistration.php");

$submitter_code = "APO";
$submitter_ipi = 538783703;

$cwr_to_load = "CW175594707_APO.V21";

$cwr = new WorksRegistration($submitter_code, $submitter_ipi);

printf("%s\n", $cwr->Welcome_String);

$cwr->CWR_File_Contents = file_get_contents($cwr_to_load);
$cwr->ReadCWR();

printf("File metadata:\n");
printf("Sender: %s (%d)\n", $cwr->Header['Sender_Name'], $cwr->Header['Sender_IPI']);
printf("Creation Date: %s\nCreation Time: %s\n", $cwr->Header['Creation_Date'], $cwr->Header['Creation_Time']);
printf("Transmission Date: %s\n", $cwr->Header['Transmission_Date']);
printf("Character Set: %s\n", $cwr->Header['Character_Set']);
printf("Transaction Groups: %d\n", count($cwr->Header['Group']));

$cwr->CurrentWork = 0;
while($cwr->NextWork())
{
	$work = $cwr->getWorkDetails();
	if(!$work) printf("No work returned!\n");

	printf("\nWork:\n%s", $work['Title']);
	printf(" - This work is %02.2f%% controlled by the submitter.\n", $cwr->percentageControlled());
	$cwr->CurrentShare = 0;
	while($cwr->NextShare()) 
	{
		$shareholder = $cwr->getShareDetails();
		$ipi =& $shareholder['IPI'];
		
		$shareholder['Name'] = $cwr->Shareholders[$ipi]['Name']." ".$cwr->Shareholders[$ipi]['First_Name'];
		printf("\nShareholder:\n%s (%d) - %0.2f%% / %0.2f%%\n", $shareholder['Name'], $shareholder['IPI'], $shareholder['PR_Ownership_Share'], $shareholder['MR_Ownership_Share']);

		$tis_entries = $cwr->getTISentries();

		if($collection_shares = $cwr->getCollectionValues())
		{
			printf("Collection rights in %d territories - derived from %s TIS statement(s):\n", count($collection_shares), count($tis_entries));
			foreach($tis_entries as $tis) printf("TIS %04d (%s): %s\n", $tis['TIS-N'], $tis['Indicator'], $tis['Name']);
			printf("%s\n\n", implode(", ", array_keys($collection_shares)));			
		}
		else printf("No collection rights defined.\n");
	}
}

printf("\n\n*****\n\nCreating new CWR File...\n");
$cwr->WriteCWR();

file_put_contents("TEST.CWR", $cwr->CWR_File_Contents);
printf("%d works, file size %d bytes.\n", count($cwr->CWR_Work_IDs), strlen($cwr->CWR_File_Contents));

printf("\nMessages: ");
foreach($cwr->Msgs as $message) printf("\n%s", $message);
if(count($cwr->Msgs) == 0) printf("None.\n");
echo "\n";
?>

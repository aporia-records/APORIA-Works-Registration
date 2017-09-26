<?php
/*************************** APORIA WorksRegistration Class ***************************

	APORIA Works Registration
	Copyright © 2016, 2017 Gord Dimitrieff <gord@aporia-records.com>

	This file is part of APORIA Works Registration, a PHP library for reading, 
	writing and manipulating CISAC Common Works Registration (CWR) files.

	APORIA Works Registration is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	APORIA Works Registration is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with APORIA Works Registration.  If not, see <http://www.gnu.org/licenses/>.

*/
/*

v1.43

updates:
validateWork(): now checks the Text Music Relationship for a valid entry, replaces invalid entries with spaces
cwr_lib.php: error messages now added to the messages array, rather than displayed via printf()

bug fixes:
cwr_lib.php: leading zeros were removed from IPI Name numbers after the last update -- now fixed
writeCWR(): now fails if Submitter Name is missing in the shareholders array

*/

require("cwr-lib.php");

class WorksRegistration {

	// Name of this class
	const Name		= "APORIA Works Registration";

	// Version/revision of this class
	const Version	= 1.42;

	// bitmasks for registration groups/transaction types
	const CWR_NWR	= 1;
	const CWR_REV	= 2;
	const CWR_ISW	= 4;
	const CWR_EXC	= 8;
	const CWR_ACK	= 16;
	const CWR_SOC	= 32; // CWR Light

	public $Welcome_String;
	
	public $Rightsholders = array();
	public $CurrentRightsholder = 0;
	public $Contributors = array();

	public $Works = array();
	public $Links = array();
	public $Header = array();
	public $Trailer = array();
	public $Shareholders = array();
	public $Performers = array();
	public $Msgs = array();

	private $track		= array();
	private $release	= array();

	public $errors		= 0;
	public $warnings	= 0;
	
	public $CurrentWork = 0;
	public $CurrentShare = 0;
	public $CurrentXRef = 0;
	
	public $ACK = false; // Set to 'true' if an ACK from a society has been loaded

	public $submitter_ipi = false; // Set to submiter's IPI number
	public $submitter_code;

	public $receiver_society = 0;
	public $file_version = 1;

	public $Contact_Name = '';	// The name of a business contact person at the organization that originated this transaction.
	public $Contact_ID = '';	// An identifier associated with the contact person at the organization that originated this transaction.
	
	public $CWR_Filename;
	public $CMRRA_Filename;
	public $EXCEL_Filename;

	public	$character_set = '';
	public	$transmission_date;
	private $creation_date;
	private $creation_time;

	private $TISdata = array();
	public	$TIS_mem = 0; 		// TIS_mem will contain the amount of memory allocated to store TISdata.

	public $CWR_File_Contents;	// Will contain the contents of the CWR file
	public $CWR_Work_IDs;		// Array will contain the list of Work_IDs included in this CWR file

/* CWR v2.2 fields */
	public	$CWR_Version;
	public	$CWR_Revision	= 0;
	public	$Software_Package;
	public	$Software_Package_Version;

/* Callback functions */
	public $callback_find_unknown_writer = null; // should return an ID if the unknown writer is already in the database
	public $callback_check_ipi = null; // check if the IPI is valid/exists in the IPI database

/**
 TIS Rewrite Rules:
	array(Target_Society => array(ISO_1, ISO_2, etc.))
**/
	public $TISrewrite = false;
	public $TISrewrite_rules = array(
		'88' => array('CA')
	);

	function __construct($submitter_code, $submitter_ipi)
	{
		$this->submitter_code		= $submitter_code;
		$this->submitter_ipi		= $submitter_ipi;
		$this->creation_date 		= date("Ymd");
		$this->transmission_date	= $this->creation_date;
		$this->creation_time		= date('Hms');
		$this->CWR_Filename 		= $this->cwr_filename(); // substr to shorten date to YYMMDD format as per filename specifications
		$this->EXCEL_Filename		= sprintf("%s-CATALOG-%s.csv", $this->submitter_code, substr($this->creation_date, 2));
		$this->CWR_Version			= (float) CWR_Version; /* Constant defined in cwr-lib.php */
		$this->file_version 		= 1; //file sequence starts at number 1.

		$this->Software_Package			=	substr(self::Name, 0, 30);
		$this->Software_Package_Version =	substr(sprintf("%1.1f/PHP %s", self::Version, phpversion()), 0, 30);

		$this->CWR_Work_IDs			= array();

		$tare = memory_get_usage();
		require("TIS-data.php");	// Populate $this->TISdata
		$load = memory_get_usage();
		
		$this->TIS_mem = ($load-$tare);

		$this->Welcome_String = sprintf("\n%s [%s]\n\n", self::Name, $this->Software_Package_Version);
	}

	function cwr_filename()
	{
		return(sprintf("CW%s%04d%s_%03d.V%02d", substr($this->creation_date, 2, 2), $this->file_version, $this->submitter_code, $this->receiver_society, str_replace(".","", $this->CWR_Version))); //, 
	}

	function LastMsg() // return the contents of the last message
	{
		$last = count($this->Msgs) -1;
		return($this->Msgs[$last]);
	}
	
/* New Work functions */
	function NewWork($data = array())
	{
		if(!isset($this->Works)) $this->CurrentWork = 0;
		else $this->CurrentWork = count($this->Works);

		if(empty($data['ISWC'])) $data['ISWC'] = ''; // initialize an empty ISWC field, so it will always exist

		$this->CurrentWork++;
		$this->setWorkDetails($data);
		$this->Works[$this->CurrentWork]['PER'] = array();

		$this->CurrentShare = 0; //reset share index for new song
		$this->CurrentXRef = 0; //reset XRef index for new song
		
		return(true);
	}
	
	function NextWork()
	{
		if(! isset($this->Works)) return(false);
		if($this->CurrentWork < count($this->Works))
		{	
			$this->CurrentWork++;
			return(true);
		}
		else return(false);
	}
	
	function PrevWork()
	{
		if($this->CurrentWork > 1)
		{	
			$this->CurrentWork--;
			return(true);
		}
		else return(false);
	}
	
	function LastWork()
	{
		if($this->CurrentWork > 0)
		{
			$this->CurrentWork = count($this->Works);
			return(true);
		}
		else return(false);
	}

	function getWorkDetails()
	{
		if($this->CurrentWork > 0 && isset($this->Works[$this->CurrentWork]))
			return($this->Works[$this->CurrentWork]);
		else return(false);
	}
	
	function setWorkDetails($data)
	{
		if(is_array($data))
		{
			if(isset($data['ISRC']))
			{
				$this->Works[$this->CurrentWork]['ISRC'][] = str_replace(array('ISRC', '-', ' '), '', strtoupper($data['ISRC'])); // Strip formatting dashes and/or spaces from ISRC codes
				unset($data['ISRC']);
			}

			foreach($data as $key => $value) {
				if(!is_array($value)) $this->Works[$this->CurrentWork][$key] = trim($value);
			}
			if(!array_key_exists('Duration', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['Duration'] = '';
			if(!array_key_exists('PR_Ownership_Share', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['PR_Ownership_Share'] = 0;
			if(!array_key_exists('MR_Ownership_Share', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['MR_Ownership_Share'] = 0;
			if(!array_key_exists('SR_Ownership_Share', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['SR_Ownership_Share'] = 0;

			return(true);
		}
		else return(false);
	}

	function removeWorkDetail($element)
	{
		if(isset($this->Works[$this->CurrentWork][$element]))
		{
			$this->Works[$this->CurrentWork][$element] = null;
			unset($this->Works[$this->CurrentWork][$element]);
			return(true);
		}
		return(false);
	}

	function setAttributes($data) // Used to add COM, EWT and VER record attributes to a Work
	{
		if(!empty($data['Title']))
		{
			$this->Works[$this->CurrentWork][$data['Record_Type']] = $data;
			return(true);
		}
		else switch($data['Record_Type'])
		{
			case 'COM':
			{
				$this->Msgs[] = "COM: No Title was entered - record dropped.";
				break;
			}
			case 'EWT': 
			{
				$this->Msgs[] = "EWT: Entire Work Title was missing - record dropped.";
				break;
			}
			case 'VER':
			{
				$this->Msgs[] = "VER: Original Work Title was not entered.";
				break;
			}
		} 
		return(false);
	}

	function addToList($data)
	{
		$list = $data['Record_Type'];
		unset($data['Record_Type']);

		$this->Works[$this->CurrentWork][$list][] = $data;
	}

	function addARI($data)
	{
		if(is_array($data))
		{
			if(!empty($data['Society_Code']))
			{
				if(in_array($data['Type_of_Right'], array('MEC', 'PER', 'SYN', 'ALL')))
				{
					if(!empty($data['Note']) && !empty($data['Work_Num']))
					{
						if(!empty($data['Note']) && !empty($data['Subject_Code']))
						{
							$this->Works[$this->CurrentWork]['ARI'][] = $data;
							return(true);
						}
						else $this->Msgs[] = "ARI: Subject must be entered if Note is not blank, and must match an entry in the Subject table.";
					}
					else $this->Msgsp[] = "ARI: Neither Work # or Note was entered.";
				}
				else $this->Msgs[] = "ARI: Type of right must be entered and must be a valid right or 'ALL' for all.";
			}
			else $this->Msgs[] = "ARI: Society # must be entered and must match an entry in the Society Code table or '000'.";
		}
		return(false);
	}

/* Work splits - Share functions */
	function NewShare($data = array())
	{
		$this->CurrentShare++;
		if(empty($data['Link'])) $data['Link'] = 0;
		return($this->setShareDetails($data));
	}

	function getShareDetails()
	{
		$shareKey = $this->CurrentShare;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
		if($firstKey == 0) $shareKey--;

		if(($this->CurrentWork > 0) && ($this->CurrentShare > 0))
			return($this->Works[$this->CurrentWork]['Share'][$shareKey]);			

		return(false);
	}

	function setShareDetails($data)
	{
		$shareKey = $this->CurrentShare;
		if(isset($this->Works[$this->CurrentWork]['Share']))
		{
			$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
			if($firstKey == 0) $shareKey--;			
		}

		if(is_array($data))
		{
			foreach($data as $key => $value)
				$this->Works[$this->CurrentWork]['Share'][$shareKey][$key] = trim($value);

			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['PR_Ownership_Share'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['PR_Ownership_Share'] = 0;
			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['MR_Ownership_Share'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['MR_Ownership_Share'] = 0;
			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['SR_Ownership_Share'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['SR_Ownership_Share'] = 0;

			return(true);
		}
		else return(false);
	}

	function removeShareDetail($element)
	{
		if(isset($this->Works[$this->CurrentWork]['Share']))
		{
			$this->Works[$this->CurrentWork]['Share'][$element] = null;
			unset($this->Works[$this->CurrentWork]['Share'][$element]);

			return(true);
		}
		return(false);
	}

	function getTISentries() // Return array of TIS entries along with territory names and codes
	{
		$shareKey = $this->CurrentShare;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
		if($firstKey == 0) $shareKey--;

		if(($this->CurrentWork > 0) && ($this->CurrentShare > 0))
		{
			$selection = array();
			if(!empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS']))
			{
				foreach($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'] as $TIS_Numeric_Code => $territory)
				{
					$details = array();
					$details = $this->getTISdetails($TIS_Numeric_Code, $this->TISdata);
					$selection[] = array(
						'Indicator'				=> $territory['Indicator'],
						'TIS-N'					=> $TIS_Numeric_Code,
						'TIS-A'					=> $details['TIS-A'],
						'TIS-A-Ext'				=> $details['TIS-A-Ext'],
						'Name'					=> $details['Name'],
						'PR_Collection_Share'	=> $territory['PR_Collection_Share'],
						'MR_Collection_Share'	=> $territory['MR_Collection_Share'],
						'SR_Collection_Share'	=> $territory['SR_Collection_Share']
					);
				}
			return($selection);
			}
		}
	}

	function getCollectionValues() // Returns an array of collection values, where the array index is the ISO country code
	{
		$shareKey = $this->CurrentShare;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
		if($firstKey == 0) $shareKey--;

		if(($this->CurrentWork > 0) && ($this->CurrentShare > 0))
		{
			$selection = array();
			if(!empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS']))
			{
				foreach($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'] as $TIS_Numeric_Code => $territory)
				{
					$territories_to_pick = array();
					$this->selectTIS($this->TISdata, $TIS_Numeric_Code, $territories_to_pick);

					foreach($territories_to_pick as $ter)
					{
						if($territory['Indicator'] == 'I') // Include in the selection
						{
							$selection[$ter['ISO']] = $territory;
							$selection[$ter['ISO']]['ISO'] = $ter['ISO'];
							$selection[$ter['ISO']]['TIS-N'] = $ter['TIS-N'];
						}
						if($territory['Indicator'] == 'E')
						{
							$selection[$ter['ISO']] = null;
							unset($selection[$ter['ISO']]); // Exclude from the selection
						}
						
					}
				}
				return($selection);
			}
		}
		return(false);
	}

	function addTerritory($TIS, $data)
	{
		$shareKey = $this->CurrentShare;
		if(isset($this->Works[$this->CurrentWork]['Share']))
		{
			$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
			if($firstKey == 0) $shareKey--;			
		}

		if(is_array($data))
		{
			foreach($data as $key => $value)
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS][$key] = trim($value);

			if(!array_key_exists('PR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['PR_Collection_Share'] = 0;

			if(!array_key_exists('MR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['MR_Collection_Share'] = 0;

			if(!array_key_exists('SR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['SR_Collection_Share'] = 0;

			if(!array_key_exists('Shares_Change', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Shares_Change'] = '';

			if($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Indicator'] == 'I' &&
				($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['PR_Collection_Share']
				+	$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['MR_Collection_Share']
				+	$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['SR_Collection_Share'] == 0)) 
			{
				$ipi =& $this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['IPI'];
				$this->Msgs[] = sprintf("addTerritory: PR Collection Share, MR Collection Share, and SR Collection Share cannot all be zero for IPI %09d.", $ipi);
				return(false);
			}

			return(true);
		}
		else {
			$this->Msgs[] = sprintf("addTerritory() requires an array as argument! - Got '%s'", $data);
			return(false);
		}
	}

	function sortShares()
	{
		foreach($this->Works[$this->CurrentWork]['Share'] as $shareKey => $share)
		{
			$ipi = intval($share['IPI']);
			if($this->Shareholders[$ipi]['Controlled']=='Y') $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl']=1;
			else $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl']=0;

			$sortByControl[$shareKey] = $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl'];
			$sortByChain[$shareKey] = $this->Works[$this->CurrentWork]['Share'][$shareKey]['Link'];
		}
		array_multisort($sortByControl, SORT_DESC, $sortByChain, SORT_ASC, $this->Works[$this->CurrentWork]['Share']);

		// Reset the array index so that it begins at 1 rather than 0:
		$this->Works[$this->CurrentWork]['Share'] = array_combine(range(1, count($this->Works[$this->CurrentWork]['Share'])), array_values($this->Works[$this->CurrentWork]['Share']));
	}

	function NextShare()
	{
		if(! isset($this->Works[$this->CurrentWork]['Share'])) return(false);
		if($this->CurrentShare < count($this->Works[$this->CurrentWork]['Share']))
		{	
			$this->CurrentShare++;
			return(true);
		}
		else return(false);
	}

/* Validation functions */
	function validateWork()
	{
		$work =& $this->Works[$this->CurrentWork];  // set up $work is a reference to the current work

		if(empty($work['Recorded_Flag'])) $work['Recorded_Flag'] = 'U';
		if(empty($work['ISRC'])) unset($work['ISRC']); // Strip empty ISRC values

		if(empty($work['Version_Type'])) $work['Version_Type'] = 'ORI'; // Default to type 'Original Work'
		if(empty($work['Musical_Work_Distribution_Category'])) $work['Musical_Work_Distribution_Category'] = 'POP'; // Default to distribution category 'Popular'

		if(!empty($work['PER'])) $work['PER'] = array_unique($work['PER']);

		if(!empty($work['ALT']))
		{
			if(!in_array($work['ALT']['Title_Type'], array('AT', 'TE', 'FT', 'IT', 'OT', 'TT', 'PT', 'RT', 'ET', 'OL', 'AL')))
			{
				$this->Msgs[] = "ALT: A language Code Must be entered if the Title Type is equal to 'OL' or 'AL'.";
				return(false);
			}
		}

		if(!in_array($work['Text_Music_Relationship'], array('MUS', 'MTX', 'TXT', 'MTN', '')))
		{
			$this->Msgs[] = "Text Music Relationship entered was not found in the Text Music Relationship table - replaced with spaces";
			$work['Text_Music_Relationship'] = '';
		}

		// Match Works to Tracks
		foreach($this->track as $isrc => $trackDetails)
		{
			$track =& $this->track[$isrc];

			if(!empty($track['Work_ID']))
			{
				if($work['Work_ID'] == $track['Work_ID'])
				{
					// Add ISRC to Work details
					$work['ISRC'][] = str_replace(array('ISRC', '-', ' '), '', strtoupper($track['ISRC']));
					$work['ISRC'] = array_unique($work['ISRC']);

					// Add ISWC to Track details
					if(empty($work['ISWC'])) $track['ISWC'] = $work['ISWC'];
				}

			}
		}

		if(isset($work['ISRC'])) foreach($work['ISRC'] as $isrc_key => $isrc) if(empty($isrc)) unset($work['ISRC'][$isrc_key]);

		if(isset($work['ORN']))
		{
			if(!isset($work['ORN']['Intended_Purpose'])) $work['ORN']['Intended_Purpose']='';
			switch($work['ORN']['Intended_Purpose'])
			{
				case 'LIB':
				{
					if(empty($work['ORN']['CD_Identifier'])) 
						$this->Msgs[] = "Intended Purpose equal to 'LIB' (Library Work) entered and CD Identifier missing.";
					return(false);
				}
				case 'COM':
				case 'FIL':
				case 'GEN':
				case 'MUL':
				case 'RAD':
				case 'TEL':
				case 'THR':
				case 'VID':break;
				default:
				{
					$this->Msgs[] = "Intended Purpose was not entered or was not found in the Intended Purpose Table.";
					return(false);
				}
			}
		}

		if($work['CWR_Work_Type'] == 'FM') /* Film and television music must have an associated Work Origin record */
		{
			if(empty($work['ORN']['Production_Title']))
			{
				$this->Msgs[] = "CWR Work Type was set to 'FM' but there was no ORN record with a Production Title.";
				return(false);
			}
		}

		/* Check if this is a new work registration, or a revised work registration */
		$this->CurrentXRef = 0;
		$xrefCheck = array();

		if(!isset($work['Transaction_Type'])) $work['Transaction_Type'] = self::CWR_NWR;  // Default to NWR registrations

		while($this->NextXRef())
		{
			$xref = $this->getXRefDetails();
			if(isset($xrefCheck[$xref['Organisation_Code']]))
			{
				$this->removeXRef();
			}
			else
			{
				$xrefCheck[$xref['Organisation_Code']] = $xref['Identifier'];
				
				if($xref['Organisation_Code'] == $this->receiver_society) 
					$work['Transaction_Type']		= self::CWR_REV;

				/* Check for MusicMark societies because there is no MusicMark reference number */
				if($this->receiver_society == 707)
				{
					if($xref['Organisation_Code'] == 10
						|| $xref['Organisation_Code'] == 21 
						|| $xref['Organisation_Code'] == 101) $work['Transaction_Type']		= self::CWR_REV;
				}
			}
		}

		if(isset($work['ACK'])) $work['Transaction_Type'] += self::CWR_ACK;

		return(true);
	}

	function validateShares()
	{
		$PR_Ownership_Share = 0;
		$MR_Ownership_Share = 0;
		$SR_Ownership_Share = 0;
		$territory_totals = array();

		$publishers = 0;
		$subpublishers = 0;
		$writers = 0;
		$controlled_writers = 0;
		$arrangers = 0;

		$this->CurrentShare = 0;

		while($this->NextShare()) 
		{
			$shareholder = $this->getShareDetails();

			$ipi = intval($shareholder['IPI']);
			
		    if($ipi < 100000000 && $this->Shareholders[$ipi]['Controlled']=='Y')
			{
				$this->Msgs[] = sprintf("Validation failed: invalid IPI number for controlled writer %s (%09d).", $this->Shareholders[$ipi]['Name']." ".$this->Shareholders[$ipi]['First_Name'], $shareholder['IPI']);
				return(false);
			}
			else if(is_callable($this->callback_check_ipi))
			{
				if(! $this->callback_check_ipi($ipi))
				{
					$this->Msgs[] = sprintf("Validation failed: IPI number for controlled writer %s (%09d) not found in database.", $this->Shareholders[$ipi]['Name']." ".$this->Shareholders[$ipi]['First_Name'], $shareholder['IPI']);
					return(false);
				}
			}

			// Sum ownership share values
			$PR_Ownership_Share += $shareholder['PR_Ownership_Share'];
			$MR_Ownership_Share += $shareholder['MR_Ownership_Share'];
			$SR_Ownership_Share += $shareholder['SR_Ownership_Share'];

			// Sum collection share values with the totals for each territory:
			$selection = $this->getCollectionValues(); // get collection values for each applicable territory	
			if(!empty($selection)) 
			{
				foreach($selection as $ter => $territory)
				{
					if(empty($territory_totals[$ter]))
					{
						$territory_totals[$ter]=array();

						$territory_totals[$ter]['PR_Collection_Share'] = 0;
						$territory_totals[$ter]['MR_Collection_Share'] = 0;
						$territory_totals[$ter]['SR_Collection_Share'] = 0;
					}
					$territory_totals[$ter]['PR_Collection_Share'] += $territory['PR_Collection_Share'];
					$territory_totals[$ter]['MR_Collection_Share'] += $territory['MR_Collection_Share'];
					$territory_totals[$ter]['SR_Collection_Share'] += $territory['SR_Collection_Share'];

					$collection_total = $territory['PR_Collection_Share'] + $territory['MR_Collection_Share'] + $territory['SR_Collection_Share'];

					if($collection_total == 0)
					{
						$this->Msgs[] = sprintf("Validation failed: PR Collection Share, MR Collection Share, and SR Collection Share are all zero for %s (%09d).", $this->Shareholders[$ipi]['Name']." ".$this->Shareholders[$ipi]['First_Name'], $shareholder['IPI']);
						return(false);
					}
				}
				
			}
			switch($shareholder['Role'])
			{
				/* Publishers: */
				case 'AQ': //	Acquirer	A publisher that acquires some or all of the ownership from an Original Publisher, but yet the Original Publisher still controls the work.
				case 'AM': //	Administrator	An interested party that collects royalty payments on behalf of a publisher that it represents.
				case 'ES': //	Substituted Publisher	A publisher acting on behalf of publisher or sub-publisher.
				case 'E': //publishers
				{
					// Only include active publishers
					if($shareholder['PR_Ownership_Share']+$shareholder['MR_Ownership_Share'] > 0) $publishers++;
					break;
				}
				case 'SE':
				{
					$subpublishers++;
					break;
				}

				/* Writers: */
				case 'A': // Author, Writer, Author of Lyrics	The creator or one of the creators of a text of a musical work.
				case 'C': // Composer, Writer	The creator or one of the creators of the musical elements of a musical work.
				case 'CA': // Composer/Author	The creator or one of the creators of text and musical elements within a musical work.
				{
					$writers++;
					if($this->Shareholders[$ipi]['Controlled']=='Y') $controlled_writers++;
					break;
				}

				/* Arrangers, etc. */
				case 'AR': // Arranger:		A modifier of musical elements of a musical work.
				case 'SA': // Sub Author:	The author of text which substitutes or modifies an existing text of musical work.
				case 'AD': // Adaptor:		The author or one of the authors of an adapted text of a musical work.
				case 'SR': // Sub Arranger:	A creator of arrangements authorized by the Sub-Publisher.
				case 'TR': // Translator:	A modifier of a text in a different language.
				{
					$arrangers++;
					break;
				}
				default:
				{
					// No Role Defined!
					$this->Msgs[] = "Missing or invalid shareholder role!";
					return(false);
				}
			}
		}

		if($writers < 1)
		{
			$this->Msgs[] = "There must be at least one writer (Writer Designation Code = 'CA', 'A', 'C') in a work.";
			return(false);
		}

		if($controlled_writers < 1)
		{
			$this->Msgs[] = "There must be at least one writer controlled by collecting submitter in a work.";
			return(false);
		}

		//If Version Type is equal to “ORI”, there cannot be an SWR or OWR record that contains a Writer Designation Code equal to “AR” (Arranger), “AD”: (Adapter), “SR” (Sub-Arranger), “SA” (Sub-Author), or “TR” (Translator). (TR)
		if($this->Works[$this->CurrentWork]['Version_Type'] == "ORI" && $arrangers > 0)
		{
			$this->Msgs[] = "If Version Type is equal to 'ORI', there cannot be an SWR or OWR record that contains a Writer Designation Code equal to 'AR' (Arranger), 'AD': (Adapter), 'SR' (Sub-Arranger), 'SA' (Sub-Author), or 'TR' (Translator).";
			return(false);
		}

		/* Check ownership totals */
		if($PR_Ownership_Share != 0 && ($PR_Ownership_Share < 99.94 || $PR_Ownership_Share > 100.06))
		{
			$this->Msgs[] = "PR Ownership shares do not total zero or 100%.";
			return(false);
		}

		if($MR_Ownership_Share != 0 && ($MR_Ownership_Share < 100 || $MR_Ownership_Share > 100.06))
		{
			$this->Msgs[] = "MR Ownership shares do not total zero or 100%.";
			return(false);
		}

		if($SR_Ownership_Share != 0 && ($SR_Ownership_Share < 100 || $SR_Ownership_Share > 100.06))
		{
			$this->Msgs[] = "SR Ownership shares do not total zero or 100%.";
			return(false);
		}

		/* Check collection totals */
		foreach($territory_totals as $ter => $territory_total)
		{
				if($territory_total['PR_Collection_Share'] > 100.06)
				{
					$this->Msgs[] = sprintf("PR Collection shares exceed 100%% (+/- 0.6%%) in territory '%s'.", $ter);
					return(false);
				}
				if($territory_total['MR_Collection_Share'] > 100.06)
				
				{
					$this->Msgs[] = sprintf("MR Collection shares exceed 100%% (+/- 0.6%%) in territory '%s'.", $ter);
					return(false);
				}
				if($territory_total['SR_Collection_Share'] > 100.06)
				{
					$this->Msgs[] = sprintf("SR Collection shares exceed 100%% (+/- 0.6%%) in territory '%s'.", $ter);
					return(false);
				}
		}
		return(true);
	}

	function percentageControlled()
	{
		$shares = array();
		$this->CurrentShare = 0;
		$percentageControlled = 0;

		while($this->NextShare()) $shares[] = $this->getShareDetails();

		foreach($shares as $shareholder)
		{
			$ipi = $shareholder['IPI'];
			if($this->Shareholders[$ipi]['Controlled'] == 'Y') $percentageControlled += $shareholder['PR_Ownership_Share'];
		}

		return($percentageControlled);
	}

/* CWR 2.2 XRF functions */
	function addXRef($data = array())
	{
		$this->CurrentXRef++;
		return($this->setXRef($data));
	}

	function removeXRef()
	{
		$xrefKey = $this->CurrentXRef;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['XRefs']));
		if($firstKey == 0) $xrefKey--;

		if(($this->CurrentWork > 0) && ($this->CurrentXRef > 0))
		{
			$this->Works[$this->CurrentWork]['XRefs'][$xrefKey] = null;
			unset($this->Works[$this->CurrentWork]['XRefs'][$xrefKey]);			
			$this->Works[$this->CurrentWork]['XRefs'] = array_values($this->Works[$this->CurrentWork]['XRefs']);
		}

		return(true);
	}

	function setXRef($data)
	{
		$xrefKey = $this->CurrentXRef;
		if(isset($this->Works[$this->CurrentWork]['XRefs']))
		{
			$firstKey = min(array_keys($this->Works[$this->CurrentWork]['XRefs']));
			if($firstKey == 0) $xrefKey--;			
		}

		if(is_array($data))
		{
			foreach($data as $key => $value)
				$this->Works[$this->CurrentWork]['XRefs'][$xrefKey][$key] = trim($value);
			return(true);
		}
		else return(false);
	}

	function getXRefDetails()
	{
		$xrefKey = $this->CurrentXRef;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['XRefs']));
		if($firstKey == 0) $xrefKey--;

		if(($this->CurrentWork > 0) && ($this->CurrentXRef > 0))
			return($this->Works[$this->CurrentWork]['XRefs'][$xrefKey]);

		return(false);
	}

	function NextXRef()
	{
		if(! isset($this->Works[$this->CurrentWork]['XRefs'])) return(false);
		if($this->CurrentXRef < count($this->Works[$this->CurrentWork]['XRefs']))
		{	
			$this->CurrentXRef++;
			return(true);
		}
		else return(false);
	}

/* End of XRF functions */

	function getNextLink()
	{
		$this->CurrentLink++;
		if($this->CurrentLink < count($this->Works[$this->CurrentWork]['Link']))
			return($this->Works[$this->CurrentWork]['Link'][$this->CurrentLink]);
		else return(false);
	}

	function addShareholder($data)
	{
		if(!is_array($data) && !isset($data['IPI']))
		{
			$this->Msgs[] = "addShareholder() - requires data in array form.  IPI is mandatory.";
			return(false);
		}
		$ipi = intval($data['IPI']);

		if(!array_key_exists ($ipi, $this->Shareholders)) // Skip this entry if it already exists
		{
			if((count($data) > 5))
			{
				if($ipi < 100000000) $this->Msgs[] = sprintf("Warning: unidentified party %s (Temp IPI '%d' will be replaced with spaces)", $data['Name']." ".$data['First_Name'], $ipi);

				$this->Shareholders[$ipi]['Name'] 		= $data['Name'];
				$this->Shareholders[$ipi]['First_Name']	= $data['First_Name'];
				$this->Shareholders[$ipi]['Controlled'] = $data['Controlled'];
				$this->Shareholders[$ipi]['US_Rep'] 	= $data['US_Rep'];

				if(!empty($data['PRO'])) $this->Shareholders[$ipi]['PRO'] = $data['PRO'];
				else $this->Shareholders[$ipi]['PRO'] = 99; // Defaul to No Society if none is declared

				if(!empty($data['MRO'])) $this->Shareholders[$ipi]['MRO'] = $data['MRO'];
				else $this->Shareholders[$ipi]['MRO'] = 99; // Defaul to No Society if none is declared
				
				if(!empty($data['SRO'])) $this->Shareholders[$ipi]['SRO'] = $data['SRO'];
				else $this->Shareholders[$ipi]['SRO'] = 99; // Defaul to No Society if none is declared

				return(true);
			}
			else $this->Msgs[] = sprintf("Insufficient data in shareholders table! (ipi: %d)", $ipi);
			return(false);	
		}
		else return(true);
	}

	function tempIPI($lastname, $firstname, $pro)
	{
		$ipi = false;
		$temp_ipi = array();

		if(is_callable($this->callback_find_unknown_writer)) $ipi = $this->callback_find_unknown_writer($lastname, $firstname, $pro);

		if($ipi == false)
		{
			foreach($this->Shareholders as $writer)
			{
				if($writer['IPI'] < 100000000 ) $temp_ipi[] = $writer['IPI'];
				if($writer['Name'].'/'.$writer['First_Name'].'/'.$writer['PRO'] == $lastname.'/'.$firstname.'/'.$pro) $ipi = $writer['IPI'];
			}
			$ipi = max($temp_ipi) + 1;
		}
		return($ipi);
	}

	function addPerformer($lastname, $firstname = '', $ipi = 0, $ipibase = 0)
	{
		$found = false;
		$i = 0;

		while($i < count($this->Performers) && !$found)
		{
			$performer =& $this->Performers[$i];
			if($ipi > 0 && $performer['IPI'] == $ipi) $found = $i;
			else if($performer['Last_Name'].$performer['First_Name'] == $lastname.$firstname) $found = $i;
			$i++;
		}

		if($found == false)
		{
			$this->Performers[] = array(
				'Last_Name'	=> $lastname,
				'First_Name'=> $firstname,
				'IPI'		=> $ipi,
				'IPI_base'	=> $ipibase);

			end($this->Performers);
			$found = key($this->Performers);
		}

		$this->Works[$this->CurrentWork]['PER'][] = $found;

		return($found);
	}

	function addMessage($data)
	{
		$this->Works[$this->CurrentWork]['Messages'][] = $data;
		return;
	}

	/* TIS Territory functions: */
	function getTISdetails($TIS_Numeric_Code, $tree)
	{ 
	    $result = false;
	    if (is_array($tree))
		{
	        foreach ($tree as $nodekey => $node) 
			{
	            if ($node['TIS-N'] == $TIS_Numeric_Code) 
				{
	                return($node);
	            } 
				else if (!empty($node['children'])) 
				{
	                if($result = $this->getTISdetails($TIS_Numeric_Code, $node['children'])) return($result);
				}
	        }
			return($result);
		}
	}

	function selectTIS($tree, $TIS_Numeric_Code, &$result = array()) 
	{ 
	    $result = array();
	    if (is_array($tree)) {
	        foreach ($tree as $node) {
	            if ($node['TIS-N'] == $TIS_Numeric_Code) {
	                if(strlen($node['TIS-A']) == 2) $result[] = array('ISO' => $node['TIS-A'], 'TIS-N' => $node['TIS-N']); // Include 2 character ISO codes and TIS number
					if (!empty($node['children'])) $this->getTISelements($node['children'], $result);
	                return true;
	            } else if (!empty($node['children'])) {
	                if ($this->selectTIS($node['children'], $TIS_Numeric_Code, $result))
					{
		                return true;
	                }
	            }
	        }
	    } else {
	        if ($tree == $TIS_Numeric_Code) {
	            return true;
	        }
	    }
	    return false;
	}

	function getTISelements($tree, &$result) 
	{
	    if (is_array($tree)) foreach($tree as $node)
		{
	        if(strlen($node['TIS-A']) == 2) $result[] = array('ISO' => $node['TIS-A'], 'TIS-N' => $node['TIS-N']); // Include 2 character ISO codes and TIS number // = $node['TIS-A']; // Include 2 character ISO codes
	        if(!empty($node['children'])) {
	            array_merge($result,$this->getTISelements($node['children'], $result));
	        }
	    }
		return($result);
	}

/* Recordings functions */

	function addTrack($data)
	{
		if(!is_array($data) && !isset($data['ISRC']))
		{
			$this->Msgs[] = "addTrack() - requires data in array form.  ISRC is mandatory.";
			return(false);
		}

		if(empty($data['UPC']) && isset($data['EAN'])) $data['UPC'] =& $data['EAN'];

		$isrc = str_replace(array('ISRC', ' ', '-'), '', $data['ISRC']);


		if(!empty($data['id']))
		{
			$data['Track_ID']	= $data['id'];
			unset($data['id']);
		}

		if(empty($data['Label']) && !empty($data['Rights_Holder_Name'])) $data['Label'] = $data['Rights_Holder_Name'];

		if(!empty($data['UPC']) > 0)
		{
			$upc = $data['UPC'];
			unset($data['UPC']);

			$this->track[$isrc]['Releases'][]['UPC'] = $upc;
			$this->release[$upc]['Tracks'][] = array('Track_ID' => $this->track[$isrc]['Track_ID'], 'ISRC' => $this->track[$isrc]['ISRC'], 'Work_ID' => $this->track[$isrc]['Work_ID'], 'ISWC' => $this->track[$isrc]['ISWC']);
		}

		foreach($data as $key => $value)
			if(!is_array($value)) $this->track[$isrc][$key] = strtoupper(trim($value));

		if(empty($this->track[$isrc]['Releases'])) $this->track[$isrc]['Releases'] = array();

		return(true);
	}

	function addRelease($data)
	{
		if(is_array($data))
		{
			if(empty($data['UPC']))
			{
				if(!empty($data['EAN'])) $data['UPC'] =& $data['EAN'];
				else {
					$this->Msgs[] = "addRelease() - requires data in array form.  UPC is mandatory.";
					return(false);
				}
			}

			$upc = $data['UPC'];
			if(!empty($data['ISRC'])) $data['ISRC'] = str_replace(array('ISRC', ' ', '-'), ' ', strtoupper($data['ISRC']));

			foreach($data as $key => $value)
				if(!is_array($value)) $this->release[$upc][$key] = strtoupper(trim($value));

			return(true);
		}
		return(false);
	}

	function addRightsholder($data)
	{
		$found = false;
		$i = 0;

		while($i < count($this->Rightsholders))
		{
			$rightsholder =& $this->Rightsholders[$i];
			if($rightsholder['Name'] == $data['Name']) $found = $i;
			$i++;
		}

		if($found == false) // Add new entry to Rightsholder array
		{
			$this->Rightsholders[] = $data;
			end($this->Rightsholders);
			$found = key($this->Rightsholders);
			$this->Rightsholders[$found]['id'] = $found;
		}
		return($found);
	}

	function addContributor($data)
	{
		$found = false;
		$i = 0;

		while($i < count($this->Contributors))
		{
			$contributor =& $this->Contributors[$i];
			if($contributor['Name'] == $data['Name']) $found = $i;
			$i++;
		}

		if($found == false) // Add new entry to Rightsholder array
		{
			$this->Contributors[]['Name'] = $data['Name'];
			end($this->Contributors);
			$found = key($this->Contributors);
			$this->Contributors[$found]['Contributor_ID'] = $found;
		}

		if(!empty($data['ISRC']) && !empty($data['Role'])) 
			$this->track[$data['ISRC']]['Contributors'][] = array('Contributor_ID' => $found, 'Role' => $data['Role'], 'Category' => $data['Category']);

		return($found);
	}

/* End of Recordings functions */


/******************************************************
/* Generic Works Registration Formats
/******************************************************/

	function WriteCatalogue($handle)
	{
		$csv = array();
		$x = 0; // CSV row counter

		$headerRow = array(
			'SONG TITLE' => '',
			'AKA 1' => '',
			'COMPOSER 1 FIRST NAME' => '',
			'COMPOSER 1 SURNAME' => '',
			'COMPOSER 1 CONTROLLED' => '',
			'COMPOSER 1 CAPACITY' => '',
			'COMPOSER 1 CAE NO' => '',
			'COMPOSER 1 AFFILIATION' => '',
			'COMPOSER 1 LINKED PUBLISHER' => '',
			'COMPOSER 2 FIRST NAME' => '',
			'COMPOSER 2 SURNAME' => '',
			'COMPOSER 2 CONTROLLED' => '',
			'COMPOSER 2 CAPACITY' => '',
			'COMPOSER 2 CAE NO' => '',
			'COMPOSER 2 AFFILIATION' => '',
			'COMPOSER 2 LINKED PUBLISHER' => '',
			'COMPOSER 3 FIRST NAME' => '',
			'COMPOSER 3 SURNAME' => '',
			'COMPOSER 3 CONTROLLED' => '',
			'COMPOSER 3 CAPACITY' => '',
			'COMPOSER 3 CAE NO' => '',
			'COMPOSER 3 AFFILIATION' => '',
			'COMPOSER 3 LINKED PUBLISHER' => '',
			'COMPOSER 4 FIRST NAME' => '',
			'COMPOSER 4 SURNAME' => '',
			'COMPOSER 4 CONTROLLED' => '',
			'COMPOSER 4 CAPACITY' => '',
			'COMPOSER 4 CAE NO' => '',
			'COMPOSER 4 AFFILIATION' => '',
			'COMPOSER 4 LINKED PUBLISHER' => '',
			'COMPOSER 5 FIRST NAME' => '',
			'COMPOSER 5 SURNAME' => '',
			'COMPOSER 5 CONTROLLED' => '',
			'COMPOSER 5 CAPACITY' => '',
			'COMPOSER 5 CAE NO' => '',
			'COMPOSER 5 AFFILIATION' => '',
			'COMPOSER 5 LINKED PUBLISHER' => '',
			'COMPOSER 1 SHARE' => '',
			'COMPOSER 2 SHARE' => '',
			'COMPOSER 3 SHARE' => '',
			'COMPOSER 4 SHARE' => '',
			'COMPOSER 5 SHARE' => '',
			'PUBLISHER 1 NAME' => '',
			'PUBLISHER 1 CONTROLLED' => '',
			'PUBLISHER 1 CAPACITY' => '',
			'PUBLISHER 1 CAE NO' => '',
			'PUBLISHER 1 LINKED PUBLISHER' => '',
			'PUBLISHER 1 AFFILIATION' => '',
			'PUBLISHER 2 NAME' => '',
			'PUBLISHER 2 CONTROLLED' => '',
			'PUBLISHER 2 CAPACITY' => '',
			'PUBLISHER 2 CAE NO' => '',
			'PUBLISHER 2 LINKED PUBLISHER' => '',
			'PUBLISHER 2 AFFILIATION' => '',
			'PUBLISHER 3 NAME' => '',
			'PUBLISHER 3 CONTROLLED' => '',
			'PUBLISHER 3 CAPACITY' => '',
			'PUBLISHER 3 CAE NO' => '',
			'PUBLISHER 3 LINKED PUBLISHER' => '',
			'PUBLISHER 3 AFFILIATION' => '',
			'PUBLISHER 4 NAME' => '',
			'PUBLISHER 4 CONTROLLED' => '',
			'PUBLISHER 4 CAPACITY' => '',
			'PUBLISHER 4 CAE NO' => '',
			'PUBLISHER 4 LINKED PUBLISHER' => '',
			'PUBLISHER 4 AFFILIATION' => '',
			'PUBLISHER 5 NAME' => '',
			'PUBLISHER 5 CONTROLLED' => '',
			'PUBLISHER 5 CAPACITY' => '',
			'PUBLISHER 5 CAE NO' => '',
			'PUBLISHER 5 LINKED PUBLISHER' => '',
			'PUBLISHER 5 AFFILIATION' => '',
			'PUBLISHER 1 MO SHARE' => '',
			'PUBLISHER 1 PO SHARE' => '',
			'PUBLISHER 2 MO SHARE' => '',
			'PUBLISHER 2 PO SHARE' => '',
			'PUBLISHER 3 MO SHARE' => '',
			'PUBLISHER 3 PO SHARE' => '',
			'PUBLISHER 4 MO SHARE' => '',
			'PUBLISHER 4 PO SHARE' => '',
			'PUBLISHER 5 MO SHARE' => '',
			'PUBLISHER 5 PO SHARE' => '',
			'PUBLISHER 1 MC SHARE' => '',
			'PUBLISHER 1 PC SHARE' => '',
			'PUBLISHER 2 MC SHARE' => '',
			'PUBLISHER 2 PC SHARE' => '',
			'PUBLISHER 3 MC SHARE' => '',
			'PUBLISHER 3 PC SHARE' => '',
			'PUBLISHER 4 MC SHARE' => '',
			'PUBLISHER 4 PC SHARE' => '',
			'PUBLISHER 5 MC SHARE' => '',
			'PUBLISHER 5 PC SHARE' => '',
			'SONG NOTES' => '',
			'ARTIST' => '',
			'DURATION' => '',
			'ISWC' => '',
			'ISRC' => '',
			'TITLE LANGUAGE' => '' );

		if(!isset($this->Works)) return(false);

		$this->CurrentWork = 0;
		while($this->NextWork())
		{
			/* Initialize Row */
			$csv[$x]=$headerRow;

			$Song = $this->getWorkDetails();
			$csv[$x]['SONG TITLE']			= $Song['Title'];
			$csv[$x]['AKA 1']				= $Song['Alt_Title'];
			$csv[$x]['ARTIST']				= $Song['Performer_1'];
			$csv[$x]['DURATION']			= $Song['Duration'];
			$csv[$x]['ISWC']	 			= $Song['ISWC'];
			$csv[$x]['ISRC']	 			= $Song['ISRC'];
			$csv[$x]['TITLE LANGUAGE']		= $Song['Lang'];
			$csv[$x]['SONG NOTES']			= '';
			
			$pub = 0; // publisher counter
			$wtr = 0; // writer counter

			$Link_Row = array();

			$this->CurrentShare = 0;
			while($this->NextShare())
			{
				$Shareholder = $this->getShareDetails();
				$ipi = $Shareholder['IPI'];

				switch($Shareholder['Role'])
				{
					case 'A':
					case 'C':
					case 'CA':
					{
						$composer = sprintf("COMPOSER %1d", $wtr+1);
					
						$csv[$x][$composer.' FIRST NAME']			= $this->Shareholders[$ipi]['First_Name'];
						$csv[$x][$composer.' SURNAME']				= $this->Shareholders[$ipi]['Name'];
						$csv[$x][$composer.' CONTROLLED']			= $this->Shareholders[$ipi]['Controlled'];
						$csv[$x][$composer.' CAPACITY']				= $Shareholder['Role'];
						$csv[$x][$composer.' CAE NO']				= $ipi;
						$csv[$x][$composer.' AFFILIATION']			= $this->Shareholders[$ipi]['PRO'];
						$csv[$x][$composer.' LINKED PUBLISHER']		= $Shareholder['Link'];

						$csv[$x][$composer.' SHARE'] 				= sprintf("%03.2f", $Shareholder['PR_Ownership_Share']);

						$wtr++;
						break;
					}

					case 'E':
					case 'SE':
					{
						$publisher = sprintf("PUBLISHER %1d", $pub+1);

						$csv[$x][$publisher.' NAME']				= $this->Shareholders[$ipi]['Name'];
						$csv[$x][$publisher.' CONTROLLED']			= $this->Shareholders[$ipi]['Controlled'];
						$csv[$x][$publisher.' CAPACITY']			= $Shareholder['Role'];
						$csv[$x][$publisher.' CAE NO']				= $ipi;
						
						$csv[$x][$publisher.' LINKED PUBLISHER']	= $Shareholder['Link'];
						$csv[$x][$publisher.' AFFILIATION']			= $this->Shareholders[$ipi]['PRO'];

						$csv[$x][$publisher.' MO SHARE']			= sprintf("%03.2f", $Shareholder['MR_Ownership_Share']);
						$csv[$x][$publisher.' PO SHARE']			= sprintf("%03.2f", $Shareholder['PR_Ownership_Share']);
						$csv[$x][$publisher.' MC SHARE']			= sprintf("%03.2f", $Shareholder['MR_Collection_Share']);
						$csv[$x][$publisher.' PC SHARE']			= sprintf("%03.2f", $Shareholder['PR_Collection_Share']);

						$pub++;
						break;
					}
				}
			}

			$x ++; //advance to next csv row
		}

		// Print header row
		fputcsv($handle, array_keys($headerRow));

		// Print array rows to CSV
		foreach($csv as $row) fputcsv($handle, $row);		

		return($x+1); // Return number of lines written in CSV file
	}

/******************************************************
/* CISAC Common Works Registration 
/******************************************************/
	function WriteCWR()
	{	
		/*
			Usage Notes:
			WriteCWR() creates a CWR text file in $this->CWR_File_Contents
			$this->CWR_Work_IDs is an array holding the Work_ID numbers for each work in the CWR
		*/

		/* Serial Numbers */
		$rc = 0; // Record Number Counter
		$tx = 0; // Transaction (Song) Number Counter
		$sq = 0; // Record Sequence Number Counter
		$group_id = 0; // Group ID Number
		$group_rc = 0; // Group record count
		$group_tx = 0; // Group transaction count

		$errors		= 0;
		$warnings	= 0;

		$this->CurrentShare = 0;
		$this->CurrentWork = 0;

		if(empty($this->submitter_code) || empty($this->submitter_ipi))
		{
			$this->Msgs[] = "CANNOT GENERATE CWR!\nSubmitter code or IPI not supplied.";
			return(false);
		}
		if(!array_key_exists($this->submitter_ipi, $this->Shareholders))
		{
			$this->Msgs[] = "CANNOT GENERATE CWR!\nSubmitter Name not supplied - use addShareholder() to include it.";
			return(false);
		}

		if(empty($this->receiver_society))
			$this->Msgs[] = "WARNING: No receiver society specified!";

		/* Generate Transmission Header */
		$rc++;
		$rec = array(
				'Record_Type'		=> 'HDR',
				'Sender_Type' 		=> 'PB', 
				'Sender_ID'			=> $this->submitter_ipi, 
				'Sender_Name'		=> $this->Shareholders[$this->submitter_ipi]['Name'], 
				'Creation_Date'		=> $this->creation_date,
				'Creation_Time'		=> $this->creation_time,
				'Transmission_Date'	=> $this->transmission_date,
				'Character_Set'		=> $this->character_set);

		if($this->CWR_Version > 2.1)
		{
			$rec['CWR_Version'] 				= $this->CWR_Version;
			$rec['CWR_Revision'] 				= $this->CWR_Revision;
			$rec['Software_Package'] 			= $this->Software_Package;
			$rec['Software_Package_Version'] 	= $this->Software_Package_Version;
		}
		$cwr = encode_cwr($this->Msgs, $rec, $group_tx, $sq);

		/**
			AGREEMENT GROUP LOGIC GOES HERE:

				AGR
					TER ...
					IPA ...

		**/

		/**
			BEGINNING OF NWR/REV/ISC/EXC TRANSACTION GROUP 
		**/

		$RegistrationTypes	= 0;
		$this->CurrentWork = 0;

		// Scan all works to determine which types of transaction groups we will need to generate
		while($this->NextWork())
		{
			$this->validateWork();
			$work = $this->getWorkDetails();

			if($work['Transaction_Type'] & self::CWR_NWR)	$RegistrationTypes = $RegistrationTypes | self::CWR_NWR;
			if($work['Transaction_Type'] & self::CWR_REV)	$RegistrationTypes = $RegistrationTypes | self::CWR_REV;
			if($work['Transaction_Type'] & self::CWR_ISW)	$RegistrationTypes = $RegistrationTypes | self::CWR_ISW;
			if($work['Transaction_Type'] & self::CWR_EXC)	$RegistrationTypes = $RegistrationTypes | self::CWR_EXC;
			if($work['Transaction_Type'] & self::CWR_ACK)	$RegistrationTypes = $RegistrationTypes | self::CWR_ACK;
		}
		
		// Loop for each registration transaction group type (i.e. loop for each bit set in RegistrationTypes)
		$loops = 0;
		if($RegistrationTypes & self::CWR_NWR) $loops++;
		if($RegistrationTypes & self::CWR_REV) $loops++;
		if($RegistrationTypes & self::CWR_ISW) $loops++;
		if($RegistrationTypes & self::CWR_EXC) $loops++;

		for($registrationGroup = 0; $registrationGroup < $loops; $registrationGroup++)
		{
			$group_tx = 0; // Reset group transaction count
			$group_rc = 0; // Reset group record count

			$transaction_type	= "";
			
			if($RegistrationTypes & self::CWR_NWR)
			{
				$transaction_type	= "NWR";
				$RegistrationTypes	-= self::CWR_NWR;
			}
			else if($RegistrationTypes & self::CWR_REV)
			{
				$transaction_type	= "REV";
				$RegistrationTypes	-= self::CWR_REV;
			}
			else if($RegistrationTypes & self::CWR_ISW)
			{
				$transaction_type	= "ISW";
				$RegistrationTypes	-= self::CWR_ISW;
			}
			else if($RegistrationTypes & self::CWR_EXC)
			{
				$transaction_type	= "EXC";
				$RegistrationTypes	-= self::CWR_EXC;
			}

			if($RegistrationTypes & self::CWR_ACK) $group_transaction_type	= "ACK";
			else $group_transaction_type = $transaction_type;

			/* Group Header */
			$rc++;
			$group_id++;
			$group_rc++;

			$rec = array(
					'Record_Type'		=> 'GRH',
					'Transaction_Type'	=> $group_transaction_type,
					'CWR_Version'		=> $this->CWR_Version,
					'Group_ID'			=> $group_id
			);
			$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

			$this->CurrentWork = 0; // Set to 0 so that the first call to NextWork() will advance to position 1.
			while($this->NextWork())
			{
				$work = $this->getWorkDetails();

				/* Create a CWR entry only if this work passes validation */
				if($this->validateWork() && $this->validateShares())
				{
					/* start of new transaction */
					$sq = 0; // Reset the sequence number

					// Handle ACK transactions
					if(($work['Transaction_Type'] & self::CWR_ACK) && $group_transaction_type == "ACK")
					{
						$work['Transaction_Type'] -= self::CWR_ACK;

						$rec = array(
							'Record_Type'							=> "ACK",
							'Creation_Date'							=> $work['ACK']['Creation_Date'],
							'Creation_Time'							=> $work['ACK']['Creation_Time'],
							'Original_Group_ID'						=> $work['ACK']['Original_Group_ID'],
							'Original_Transaction_Sequence_Num'		=> $work['ACK']['Original_Transaction_Sequence_Num'],
							'Original_Transaction_Type'				=> $work['ACK']['Original_Transaction_Type'],
							'Creation_Title'						=> $work['ACK']['Creation_Title'],
							'Submitter_Creation_Num'				=> $work['ACK']['Submitter_Creation_Num'],
							'Recipient_Creation_Num'				=> $work['ACK']['Recipient_Creation_Num'],
							'Processing_Date'						=> $work['ACK']['Processing_Date'],
							'Transaction_Status'					=> $work['ACK']['Transaction_Status']
						);
						$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

						$sq++;
						$rc++;
						$group_rc++;
					}

					if(	($work['Transaction_Type'] == self::CWR_NWR && $transaction_type == "NWR") || 
					($work['Transaction_Type'] == self::CWR_REV && $transaction_type == "REV") || 
					($work['Transaction_Type'] == self::CWR_ISW && $transaction_type == "ISW") || 
					($work['Transaction_Type'] == self::CWR_EXC && $transaction_type == "EXC")	)
					{
						// Increase record count/group record counts
						$rc++;
						$group_rc++;

						$this->CWR_Work_IDs[] = $work['Work_ID']; // add this work to the list of works included in the CWR - i.e. the ones that pass the above validation

						/* Generate NWR Record (Transaction Header) */
						$rec = array(
							'Record_Type'							=> $transaction_type,
							'Work_Title'							=> $work['Title'],
							'Language_Code'							=> $work['Lang'],
							'Submitter_Work_ID'						=> $work['Work_ID'],
							'ISWC'									=> $work['ISWC'],
							'Copyright_Date'						=> $work['Copyright_Date'],
							'Copyright_Number'						=> $work['Copyright_Number'],
							'Musical_Work_Distribution_Category'	=> $work['Musical_Work_Distribution_Category'],
							'Duration'								=> $work['Duration'],
							'Recorded_Indicator'					=> $work['Recorded_Flag'],
							'Text_Music_Relationship'				=> $work['Text_Music_Relationship'],
							'Composite_Type'						=> $work['Composite_Type'],
							'Version_Type'							=> $work['Version_Type'],
							'Music_Arrangement'						=> $work['Music_Arrangement'],
							'Lyric_Adaptation'						=> $work['Lyric_Adaptation'],
							'Contact_Name'							=> $this->Contact_Name,
							'Contact_ID'							=> $this->Contact_ID,
							'CWR_Work_Type'							=> $work['CWR_Work_Type'],
							'Grand_Rights_Ind'						=> $work['Grand_Rights_Ind'],
							'Composite_Component_Count'				=> $work['Composite_Component_Count'],
							'Priority_Flag'							=> ''
						);
						$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

						/* Build Share detail records here */
						$sh = 0;

						$publisher = array();
						$original_publisher = array();
						$writer = array();

						$this->sortShares();
						$this->CurrentShare = 0;

						$loop = 0;

						if($this->TISrewrite && isset($this->TISrewrite_rules[$this->receiver_society]))
							$this->Msgs[] = sprintf("NOTICE: Re-writing collection shares for society #%d to be valid only in territories: %s", $this->receiver_society, implode(", ", $this->TISrewrite_rules[$this->receiver_society]));

						while($this->NextShare())
						{
							/**
							 Rewrite TIS territories as individual territory codes according to the rules in $this->TISrewrite_rules
							**/
							if($this->TISrewrite && isset($this->TISrewrite_rules[$this->receiver_society]))
							{
								$target_territories =& $this->TISrewrite_rules[$this->receiver_society];
								$territories_to_rewrite = $this->getCollectionValues();

								// Remove all the old TIS entries
								if(isset($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'])) 
									foreach($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'] as $key => $data) 
										unset($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'][$key]);

								$this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'] = null;

								// Create new TIS entries based on the rules in TISrewrite_rules[]
								if($territories_to_rewrite) 
									foreach($territories_to_rewrite as $ISO => $tis_data)
										if(in_array($ISO, $target_territories)) 
										{
											if($tis_data)
											$this->addTerritory($tis_data['TIS-N'], $tis_data);
										}
							}
							/**
							 End of TIS-rewriting
							**/
							$shareholder = $this->getShareDetails();

							switch($shareholder['Role'])
							{
								/* Publishers: */
								case 'AQ': //	Acquirer	A publisher that acquires some or all of the ownership from an Original Publisher, but yet the Original Publisher still controls the work.
								case 'AM': //	Administrator	An interested party that collects royalty payments on behalf of a publisher that it represents.
								case 'ES': //	Substituted Publisher	A publisher acting on behalf of publisher or sub-publisher.
								case 'E': //publishers
								{
									// Only include active publishers with either ownership or collection rights
									if(($shareholder['PR_Ownership_Share']+$shareholder['MR_Ownership_Share'] > 0) || (!empty($shareholder['TIS']) && count($shareholder['TIS']) > 0))
										$publisher[] = $this->getShareDetails();

									break;
								}
								case 'SE':
								{
									// New in v1.41: Only include sub-publishers that have defined collection rights (possible if the TIS array has been re-written)
									if(!empty($shareholder['TIS']) && count($shareholder['TIS']) > 0)
										$publisher[] = $this->getShareDetails();
									else 
									{
										$this->Msgs[] = sprintf("NOTICE: Sub-Publisher '%s' has no collection rights in the relevant territorie(s) - removed from CWR.\n", $this->Shareholders[$shareholder['IPI']]['Name']);
									}
									break;
								}

								/* Writers: */
								case 'A': // Author, Writer, Author of Lyrics	The creator or one of the creators of a text of a musical work.
								case 'C': // Composer, Writer	The creator or one of the creators of the musical elements of a musical work.
								case 'CA': // Composer/Author	The creator or one of the creators of text and musical elements within a musical work.
								case 'AR': // Arranger:		A modifier of musical elements of a musical work.
								case 'SA': // Sub Author:	The author of text which substitutes or modifies an existing text of musical work.
								case 'AD': // Adaptor:		The author or one of the authors of an adapted text of a musical work.
								case 'SR': // Sub Arranger:	A creator of arrangements authorized by the Sub-Publisher.
								case 'TR': // Translator:	A modifier of a text in a different language.
								{
									$writer[] = $this->getShareDetails();
									break;
								}
								/* Income Participants: */
								case 'PA': //	Income Participant	A person or corporation that receives royalty payments for a work but is not a copyright owner.
								{
									if(empty($shareholder['First_Name']))  // This is a publisher if there is no first name
											if(count($shareholder['TIS']) > 0) $publisher[] = $this->getShareDetails();
									else
										$writer[] = $this->getShareDetails();	
								}
							}
						}

						/* Generate SPU/OPU and SPT/OPT records */
						foreach($publisher as $shareholder)
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;
							$ipi = $shareholder['IPI'];

							if(empty($shareholder['Link'])) $this->Msgs("No chain of title declared! (work: %s)\n", $work['Title']);
							$chain = intval($shareholder['Link']);

							if($shareholder['Role'] == 'E')
							{
//								$original_publisher[$chain] = $ipi;					
								$original_publisher[$chain][0] = $ipi;
								$pub_sequence[$ipi] = $chain;
							}

							if(array_key_exists('coPublisher', $shareholder))
								$original_publisher[$shareholder['coPublisher']][] = $ipi;

							$rec = array(
								'Publisher_Sequence_Number'		=> $chain,
								'Interested_Party_Number'		=> $ipi,
								'Publisher_Name'				=> $this->Shareholders[$ipi]['Name'],
								'Publisher_CAE_IPI_Name_Number'	=> $ipi,
								'Publisher_Type'				=> $shareholder['Role'],
//								'Submitter_Agreement_Number'	=> $shareholder['Agreement_Number'],
								'PR_Society'					=> $this->Shareholders[$ipi]['PRO'],
								'PR_Ownership_Share'			=> $shareholder['PR_Ownership_Share'],
								'MR_Society'					=> $this->Shareholders[$ipi]['MRO'],
								'MR_Ownership_Share'			=> $shareholder['MR_Ownership_Share'],
								'SR_Society'					=> $this->Shareholders[$ipi]['SRO'],
								'SR_Ownership_Share'			=> $shareholder['SR_Ownership_Share'],
								'USA_License_Ind'				=> substr($this->Shareholders[$ipi]['US_Rep'], 0, 1)
							);

							if($this->Shareholders[$ipi]['Controlled'] == 'Y')
									$rec['Record_Type'] = 'SPU';
							else 	$rec['Record_Type'] = 'OPU';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

							$territory_sq = 0;

							/* v2.2: Add support for OPT records here: */
							if($rec['Record_Type'] == 'SPU') $spt_opt = "SPT";
							else $spt_opt = "OPT";

							if($rec['Record_Type'] == 'SPU' || $this->CWR_Version > 2.1) /* Generate SPT record for controlled publishers, or OPT record if CWR version is 2.2+ */
							{
								// Create a record for each collection territory defined under this share
								if(isset($shareholder['TIS'])) foreach($shareholder['TIS'] as $TIS_Numeric_Code => $territory)
								{
									$sq++;
									$rc++;
									$group_rc++;
									$sh++;
									$territory_sq++;

									$rec = array(
										'Record_Type'					=> $spt_opt,
										'Interested_Party_Number'		=> $ipi,
										'PR_Collection_Share'			=> $territory['PR_Collection_Share'],
										'MR_Collection_Share'			=> $territory['MR_Collection_Share'],
										'SR_Collection_Share'			=> $territory['SR_Collection_Share'],
										'TIS_Numeric_Code'				=> $TIS_Numeric_Code,
										'Inclusion_Exclusion_Indicator' => $territory['Indicator'],
										'Shares_Change'					=> $territory['Shares_Change'],
										'Sequence_Number'				=> $territory_sq
									);
									$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
								}
							}
						}

						/* Generate SWR/OWR and SWT/OWT records */
						foreach($writer as $shareholder)
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;
							$ipi = intval($shareholder['IPI']);
//							$chain = $shareholder['Link'];

							$rec = array(
								'Interested_Party_Number'		=> $ipi,
								'Writer_Last_Name'				=> $this->Shareholders[$ipi]['Name'],
								'Writer_First_Name'				=> $this->Shareholders[$ipi]['First_Name'],
								'Writer_Designation_Code'		=> $shareholder['Role'],
								'Writer_CAE_IPI_Name_Number'	=> $ipi,
								'PR_Society'					=> $this->Shareholders[$ipi]['PRO'],
								'PR_Ownership_Share'			=> $shareholder['PR_Ownership_Share'],
								'MR_Society'					=> $this->Shareholders[$ipi]['MRO'],
								'MR_Ownership_Share'			=> $shareholder['MR_Ownership_Share'],
								'SR_Society'					=> $this->Shareholders[$ipi]['SRO'],
								'SR_Ownership_Share'			=> $shareholder['SR_Ownership_Share'],
								'USA_License_Ind'				=> substr($this->Shareholders[$ipi]['US_Rep'], 0, 1)
							);

							if($this->Shareholders[$ipi]['Controlled'] == 'Y')
									$rec['Record_Type'] = 'SWR';
							else 	$rec['Record_Type'] = 'OWR';

							if($ipi < 100000000  && $this->Shareholders[$ipi]['Controlled']=='N') // Remove SOCAN temp IPIs from OWR records
							{
							    $rec['Interested_Party_Number']        = '';
							    $rec['Writer_CAE_IPI_Name_Number']    = '';
							}
							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

							$territory_sq = 0;
							if($rec['Record_Type'] == 'SWR' || ($this->CWR_Version > 2.1)) /* Generate SWT record for controlled writers or OWT for CWR 2.2 */
							{
								// Create a record for each collection territory defined under this share
								if(!empty($shareholder['TIS'])) foreach($shareholder['TIS'] as $TIS_Numeric_Code => $territory)
								{								$sq++;
									$rc++;
									$group_rc++;
									$sh++;
									$territory_sq++;

									if($rec['Record_Type'] == 'SWR') $swt_owt = "SWT";
									else $swt_owt = "OWT";

									$rec = array(
										'Record_Type'					=> $swt_owt,
										'Interested_Party_Number'		=> $ipi,
										'PR_Collection_Share'			=> $territory['PR_Collection_Share'],
										'MR_Collection_Share'			=> $territory['MR_Collection_Share'],
										'SR_Collection_Share'			=> $territory['SR_Collection_Share'],
										'TIS_Numeric_Code'				=> $TIS_Numeric_Code,
										'Inclusion_Exclusion_Indicator' => $territory['Indicator'],
										'Shares_Change'					=> $territory['Shares_Change'],
										'Sequence_Number'				=> $territory_sq
									);
									$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
								}
							}

							/* Generate PWR records */
							if($this->Shareholders[$ipi]['Controlled'] == 'Y' || ($this->CWR_Version > 2.1 && array_key_exists($shareholder['Link'], $original_publisher)))  // Only genereate PWR for controlled writers, or if CWR is version 2.2+
							{
								foreach($original_publisher[$shareholder['Link']] as $pub_ipi)
								{
									$sq++;
									$rc++;
									$group_rc++;
									$sh++;

									$rec = array(
										'Record_Type'				=> 'PWR',
										'Publisher_IP_Number'		=> $pub_ipi,
										'Publisher_Name'			=> $this->Shareholders[$pub_ipi]['Name'],
										'Writer_IP_Number'			=> $ipi
									);

									/* include publisher sequence number if CWR version is 2.2+ */
									if($this->CWR_Version > 2.1) $rec['Publisher_Sequence_Number'] = $pub_sequence[$pub_ipi];

									$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
								}
							}
						}

						/* ALT - Alternative Titles */
						if(!empty($work['ALT'])) foreach($work['ALT'] as $altTitle)
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = array(
								'Record_Type'		=> 'ALT',
								'Alternate_Title'	=> $altTitle['Alternate_Title'],
								'Title_Type'		=> $altTitle['Title_Type'],
								'Language_Code'		=> $altTitle['Language_Code'] );

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* EWT - Entire Work Title for Excerpts */
						if(!empty($work['EWT']))
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = $work['EWT'];
							$rec['Record_Type'] = 'EWT';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* Original Work Title for Versions */
						if(!empty($work['VER']))
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = $work['VER'];
							$rec['Record_Type'] = 'VER';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* Performing Artist */
						if(!empty($work['PER']))
						{
							foreach($work['PER'] as $performer_id)
							{
								$sq++;
								$rc++;
								$group_rc++;
								$sh++;

								$rec = array(
									'Record_Type'		=> 'PER',
									'Performing_Artist_Last_Name' 			=> $this->Performers[$performer_id]['Last_Name'],
									'Performing_Artist_First_Name' 			=> $this->Performers[$performer_id]['First_Name'],
									'Performing_Artist_CAE_IPI_Name_Number' => $this->Performers[$performer_id]['IPI'],
									'Performing_Artist_IPI_Base_Number' 	=> $this->Performers[$performer_id]['IPI_base']);

								$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
							}
						}

						/* REC - Recording Detail - only create a record if we have an associated ISRC */
						if(is_array($work['ISRC']))
						{
							$recordings = array();
							if(count($work['ISRC']) > 0) foreach($work['ISRC'] as $isrc)
							{
								if(is_valid_isrc($isrc))
								{

									$rec = array(
										'Record_Type'				=> 'REC',
										'Recording_Format'			=> 'A',
										'First_Release_Duration'	=> $work['Duration'],
										'ISRC'						=> $isrc
									);

									if(isset($this->track[$isrc])) // Include track/release details if we have them
									{
										foreach($this->track[$isrc]['Releases'] as $release) // Releases on file
										{
											$upc = $release['UPC'];

											if(verifycheckdigit($upc)) $rec['EAN'] = $upc;
											else $this->Msgs[] = sprintf("Warning: EAN/UPC %013s is invalid -- replacing with spaces.", $upc);

											if(isset($this->release[$upc]['Release_Date']))	$rec['First_Release_Date']				= $this->release[$upc]['Release_Date'];
											if(isset($this->release[$upc]['Title']))		$rec['First_Album_Title']				= $this->release[$upc]['Title'];
											if(isset($this->release[$upc]['Label_Name']))	$rec['First_Album_Label']				= $this->release[$upc]['Label_Name'];
											if(isset($this->release[$upc]['Cat_No']))		$rec['First_Release_Catalog_Number']	= $this->release[$upc]['Cat_No'];
											if(isset($this->release[$upc]['Media_Type']))	$rec['Media_Type']						= $this->release[$upc]['Media_Type'];

											if($this->CWR_Version > 2.1)
											{
												$rec['CWR_Version'] = $this->CWR_Version;

												/* CWR 2.2 adds track-level details: */
												// ISRC validity will be checked by encode_cwr()
												if(isset($this->track[$isrc]['Title']))			$rec['Recording_Title']					= $this->track[$isrc]['Title'];
												if(isset($this->track[$isrc]['Version']))		$rec['Version_Title']					= $this->track[$isrc]['Version'];
												if(isset($this->track[$isrc]['Artist_Name']))	$rec['Display_Artist']					= $this->track[$isrc]['Artist_Name'];
												if(isset($this->track[$isrc]['Label_Name']))	$rec['Record_Label']					= $this->track[$isrc]['Label_Name'];
												if(isset($this->track[$isrc]['Track_ID']))		$rec['Submitter_Recording_Identifier']	= $this->track[$isrc]['Track_ID'];
											}
											$recordings[] = $rec;
										}
									}
									else $recordings[] = $rec;								
								}
								else $this->Msgs[] = sprintf("ISRC '%s' attached to work '%s' is invalid -- skipping 'REC' entry.", $isrc, $work['Title']);
							} // end of foreach($work['ISRC'] as $isrc)

							foreach($recordings as $recording)
							{
								$sq++;
								$rc++;
								$group_rc++;
								$sh++;
								$cwr .= encode_cwr($this->Msgs, $recording, $group_tx, $sq);
							}
						}

						/* ORN */
						if(isset($work['ORN']))
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = $work['ORN'];
							$rec['Record_Type'] = 'ORN';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* INS */
						if(!empty($work['INS']))
						{
							foreach($work['INS'] as $ins)
							{
								$sq++;
								$rc++;
								$group_rc++;
								$sh++;

								$rec = $ins;
								$rec['Record_Type']	= 'INS';

								$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
							}
						}
						
						/* IND */
						if(!empty($work['IND']))
						{
							foreach($work['IND'] as $ind)
							{
								$sq++;
								$rc++;
								$group_rc++;
								$sh++;

								$rec = $ind;
								$rec['Record_Type']	= 'IND';

								$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
							}
						}

						/* COM - Component */
						if(!empty($work['COM']))
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = $work['COM'];
							$rec['Record_Type'] = 'COM';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* ARI - Additional Related Information */
						if(!empty($work['ARI'])) foreach($work['ARI'] as $ari)
						{
							$sq++;
							$rc++;
							$group_rc++;
							$sh++;

							$rec = $ari;
							$rec['Record_Type'] = 'ARI';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
						}

						/* XRF - CWR 2.2: Work ID cross reference */
						if($this->CWR_Version > 2.1)
						{
							$this->CurrentXRef = 0;
							while($this->NextXRef())
							{
								$sq++;
								$rc++;
								$group_rc++;

								$xref = $this->getXRefDetails();

								$rec = array(
									'Record_Type'		=> 'XRF',
									'Organisation_Code'	=>	$xref['Organisation_Code'],
									'Identifier'		=>	$xref['Identifier'],
									'Identifier_Type'	=>	$xref['Identifier_Type'],
									'Validity'			=>	$xref['Validity']
								);
								$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
							}
						}

						/* Increase transaction count after transaction has been created */
						$tx++;
						$group_tx++;
					} // end of nwr/rev if()

				} // end of validation if()
				else $this->Msgs[] = sprintf("SKIPPING WORK - Title: %s", $work['Title'], $this->LastMsg());
			} // end of NextWork() while

			/* $tx and $group_tx now contain the count of transactions (since they started at zero) */

			/* Generate Group Trailer */
			$rc++;
			$group_rc++;

			$rec = array(
				'Record_Type'	=> 'GRT',
				'Group_ID'		=> $group_id,
				'Tx_Count'		=> $group_tx,
				'Rc_Count'		=> $group_rc);
			$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
			
		}


		/**
				END OF NWR/REV/ISC/EXC TRANSACTION GROUP 
		**/

		/* Generate Transmission Trailer */
		$rc++;
		$rec = array(
			'Record_Type'	=> 'TRL',
			'Group_ID'		=> $group_id,
			'Tx_Count'		=> $tx,
			'Rc_Count'		=> $rc);
		$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

		$this->CWR_Work_IDs = array_unique($this->CWR_Work_IDs);

		if(count($this->CWR_Work_IDs) > 0) $this->CWR_File_Contents = $cwr;

		return($this->CWR_Work_IDs); // return the list of Work_IDs included in this CWR file (for audit trail purposes).
	}

	function ReadCWR()
	{
		$rc 	= 0; // Record counter
		$txc 	= 0; // Transaction counter
		$line 	= 0; // Line counter

		$this->CurrentShare = 0;
		$this->CurrentWork = 0;

		$Messages = array();

		if (!empty($this->CWR_File_Contents)) 
		{
			$buffer = strtok($this->CWR_File_Contents, "\r\n");
			do {

				$line++; // increase line counter
				$record = decode_cwr($buffer);

				switch($record['Record_Type'])
				{
					case 'HDR':
					{
						$rc ++; //increase record sequence counter				
						$this->Header['Sender_IPI']		= $record['Sender_ID'];
						$this->Header['Sender_Type']	= $record['Sender_Type'];
						$this->Header['Sender_Name']	= $record['Sender_Name'];
						$this->Header['Creation_Date']	= $record['Creation_Date'];
						$this->Header['Creation_Time']	= $record['Creation_Time'];
						$this->Header['Transmission_Date']	= $record['Transmission_Date'];
						$this->Header['Character_Set']	= $record['Character_Set'];
						break;
					}
					case 'GRH':
					{
						$rc ++; //increase record sequence counter
						$this->Header['Group'][$record['Group_ID']]['Transaction_Type']	= $record['Transaction_Type'];
						$this->Header['Group'][$record['Group_ID']]['CWR_Version']		= $record['CWR_Version'];

						/* Batch Request is an optional value */
						if(isset($record['Batch_Request'])) $this->Header['Group'][$record['Group_ID']]['Batch_Request']	= $record['Batch_Request'];
						
						break;
					}
					case 'GRT':
					{
						$this->Header['Group'][$record['Group_ID']]['Tx_Count']	= $record['Tx_Count'];
						$this->Header['Group'][$record['Group_ID']]['Rc_Count']	= $record['Rc_Count'];
						$this->Header['Group'][$record['Group_ID']]['Currency_Indicator']	= $record['Currency_Indicator'];
						$this->Header['Group'][$record['Group_ID']]['Total_monetary_value']	= $record['Total_monetary_value'];
					}
					case 'TRL':
					{
						$rc ++; //increase record sequence counter				
						$this->Trailer['Tx_Count']		= $record['Tx_Count'];
						$this->Trailer['Rc_Count']		= $record['Rc_Count'];
						break;
					}

					case 'NWR': /* New Work Registration */
					case 'REV': /* Revised Registration */
					case 'ISW': /* Notification of ISWC */
					case 'EXC': /* Existing Work in Conflict */
					{
						if($record['Record_Type'] == 'NWR') $record['Transaction_Type'] = self::CWR_NWR;
						if($record['Record_Type'] == 'REV') $record['Transaction_Type'] = self::CWR_REV;
						if($record['Record_Type'] == 'ISW') $record['Transaction_Type'] = self::CWR_ISW;
						if($record['Record_Type'] == 'EXC') $record['Transaction_Type'] = self::CWR_EXC;

						$rc ++; //increase record sequence counter

						$tx = $record['Tx_Count'] * 1; // Transaction Sequence Number
						$sq = $record['Rc_Count'] * 1; // Record Sequence Number

						$record['Work_ID']		= intval($record['Submitter_Work_ID']);
						$record['Title']		=& $record['Work_Title'];
						$record['Work_Type']	= $record['CWR_Work_Type'];
						$record['Lang']			= $record['Language_Code'];

						$test = $this->getWorkDetails();
						if($test['Work_ID'] == $record['Work_ID'])
						{							
							$record['Transaction_Type'] = $record['Transaction_Type'] | $test['Transaction_Type'];
							$this->setWorkDetails($record);
						} else {
							$this->NewWork($record);
							$this->CWR_Work_IDs[] = $record['Work_ID'];
						}

						break;
					}

					case 'ACK':
					{
						$txc++; //increase transaction counter
						$rc ++; //increase record sequence counter

						$tx = $record['Tx_Count'] * 1; // Transaction Sequence Number
						$sq = $record['Rc_Count'] * 1; // Record Sequence Number

						$record['Transaction_Type'] = self::CWR_ACK;
	
						$this->ACK = true;

						$record['ACK']					= array('Creation_Date'					=> $record['Creation_Date'],
															'Creation_Time'						=> $record['Creation_Time'],
															'Original_Group_ID'					=> $record['Original_Group_ID'],
															'Original_Transaction_Sequence_Num'	=> $record['Original_Transaction_Sequence_Num'],
															'Original_Transaction_Type'			=> $record['Original_Transaction_Type'],
															'Creation_Title'					=> $record['Creation_Title'],
															'Submitter_Creation_Num'			=> $record['Submitter_Creation_Num'],
															'Recipient_Creation_Num'			=> $record['Recipient_Creation_Num'],
															'Processing_Date'					=> $record['Processing_Date'],
															'Transaction_Status'				=> $record['Transaction_Status']);
						$record['Work_ID']				= intval($record['Submitter_Creation_Num']);
						$record['Title']				= $record['Creation_Title'];
						$record['Collective_Work_ID']	= trim($record['Recipient_Creation_Num']);
						$record['Registration_Date']	= $record['Processing_Date'];

						$test = $this->getWorkDetails();
						if($test['Work_ID'] == $record['Work_ID'])
						{
							$record['Transaction_Type'] = $record['Transaction_Type'] | $test['Transaction_Type'];
							$this->setWorkDetails($record);
						} else {
							$this->NewWork($record);
							$this->CWR_Work_IDs[] = $record['Work_ID'];
						}

						break;
					}

					case 'MSG':
					{
//						print_r($record);
						$record['Transaction_Number'] = intval($record['Tx_Count']);
						$this->addMessage($record);

						$rc ++; //increase record sequence counter
						$tx = intval($record['Original_Record_Sequence_Num']);

						break;
					}

					// Publisher ownership shares
					case 'SPU':
					case 'OPU':
					{
						if($record['Record_Type'] == "SPU") $record['Controlled'] = 'Y';
						else $record['Controlled'] = 'N';

						$this->addShareholder(array(
							'IPI'						=> $record['Interested_Party_Number'],
							'Name'					=> $record['Publisher_Name'],
							'First_Name'				=> '', // First_Name is left blank for publishers
							'PRO'						=> $record['PR_Society'],
							'MRO'						=> $record['MR_Society'],
							'SRO'						=> $record['SR_Society'],
							'Controlled'				=> $record['Controlled'],
							'US_Rep'					=> $record['USA_License_Ind']));

						$this->NewShare(array(
							'IPI'						=> $record['Interested_Party_Number'],
							'Link'						=> intval($record['Publisher_Sequence_Number']),
							'Role'						=> $record['Publisher_Type'],
							'PR_Ownership_Share'		=> $record['PR_Ownership_Share'],
							'MR_Ownership_Share'		=> $record['MR_Ownership_Share'],
							'SR_Ownership_Share'		=> $record['SR_Ownership_Share']));
						break;
					}

					// Publisher collection territories/shares
					case 'SPT':
					case 'OPT':
					{
						$share = $this->getShareDetails(); // Get the current share

						$this->addTerritory($record['TIS_Numeric_Code'], array(
							'Indicator'				=> $record['Inclusion_Exclusion_Indicator'],
							'PR_Collection_Share'	=> $record['PR_Collection_Share'],
							'MR_Collection_Share'	=> $record['MR_Collection_Share'],
							'SR_Collection_Share'	=> $record['SR_Collection_Share'],
							'Shares_Change'			=> $record['Shares_Change']
						));
						break;
					}

					// Writer ownership shares
					case 'SWR':
					case 'OWR':
					{

						if($record['Record_Type'] == "SWR") $record['Controlled'] = 'Y';
						else $record['Controlled'] = 'N';

						$ipi = intval($record['Interested_Party_Number']);
						if($ipi == 0) $ipi = $this->tempIPI($record['Writer_Last_Name'], $record['Writer_First_Name'], $record['PR_Society']); // Assign temp IPI in place of unknown IPI

						$this->addShareholder(array(
							'IPI'						=> $ipi,
							'First_Name'				=> $record['Writer_First_Name'],
							'Name'						=> $record['Writer_Last_Name'],
							'PRO'						=> $record['PR_Society'],
							'MRO'						=> $record['MR_Society'],
							'SRO'						=> $record['SR_Society'],
							'Controlled'				=> $record['Controlled'],
							'US_Rep'					=> $record['USA_License_Ind']));

						$this->NewShare(array(
							'IPI'						=> $ipi, // $record['Interested_Party_Number'],
							'Role'						=> $record['Writer_Designation_Code'],
							'PR_Ownership_Share'		=> $record['PR_Ownership_Share'],
							'MR_Ownership_Share'		=> $record['MR_Ownership_Share'],
							'SR_Ownership_Share'		=> $record['SR_Ownership_Share']));
						break;
					}

					// Writer territory of control / collection shares
					case 'SWT':
					case 'OWT':
					{
						$share = $this->getShareDetails(); // Get the current share

						$this->addTerritory($record['TIS_Numeric_Code'], array(
							'Indicator'				=> $record['Inclusion_Exclusion_Indicator'],
							'PR_Collection_Share'	=> $record['PR_Collection_Share'],
							'MR_Collection_Share'	=> $record['MR_Collection_Share'],
							'SR_Collection_Share'	=> $record['SR_Collection_Share'],
							'Shares_Change'			=> $record['Shares_Change']
						));
						break;
					}

					case 'EWT':
					{
						$this->setEntireWorkTitle($record);
						break;
					}

					// Publisher for writer link
					case 'PWR':
					{
						// CWR 2.1: Locate publisher in the shares table and lookup the chain-of-title link
						if(empty($record['Publisher_Sequence_Number']))
						{
							$this->CurrentShare = 0;
							while($this->NextShare())
							{
								$share = $this->getShareDetails();
								if($share['IPI'] == $record['Publisher_IP_Number']) $link = intval($share['Link']);
							}
						}
						else $link = intval($record['Publisher_Sequence_Number']); // CWR v2.2+ does not require a lookup

						// Locate writer in the shares table and set the chain-of-title Link
						$this->CurrentShare = 0;
						while($this->NextShare())
						{
							$share = $this->getShareDetails();
							if($share['IPI'] == $record['Writer_IP_Number']) $this->setShareDetails(array('Link' => $link));
						}
						break;
					}

					case 'ALT':
					case 'ORN':
					case 'IND':
					case 'INS':
					{
						$this->addToList($record);
						break;
					}

					case 'COM': /* Component */
					case 'EWT': /* Entire Work Title for Excerpts */
					case 'VER': /* Original Work Title for Versions */
					{
						$this->setAttributes($record);
						break;
					}

					case 'PER':
					{
						$this->addPerformer(
							$record['Performing_Artist_Last_Name'], 
							$record['Performing_Artist_First_Name'], 
							$record['Performing_Artist_CAE_IPI_Name_Number'], 
							$record['Performing_Artist_IPI_Base_Number'] );

						break;
					}

					case 'REC':
					{
						$this->Works[$this->CurrentWork]['ISRC'][] = $record['ISRC'];

						$this->addRelease(array(
							'Release_Date'	=> $record['First_Release_Date'],
							'Title'			=> $record['First_Album_Title'],
							'Release_Date'	=> $record['First_Release_Date'],
							'Label'			=> $record['First_Album_Label'],	// Releases's label
							'Cat_No'		=> $record['First_Release_Catalog_Number'],
							'Media_Type'	=> $record['Media_Type'],
							'UPC'			=> intval($record['EAN'])));

						$this->addTrack(array(
							'Track_ID'				=> $record['Submitter_Recording_Identifier'],
							'Work_ID'				=> $this->Works[$this->CurrentWork]['Work_ID'],
							'ISWC'					=> $this->Works[$this->CurrentWork]['ISWC'],
							'Duration'				=> $record['First_Release_Duration'],
							'ISRC'					=> $record['ISRC'],
//							'UPC'					=> intval($record['EAN']),
							'ISRC_Validity'			=> $record['ISRC_Validity'],
							'Recording_Format'		=> $record['Recording_Format'],
							'Recording_Technique'	=> $record['Recording_Technique'],
							'Title'					=> $record['Recording_Title'],
							'Version'				=> $record['Version_Title'],
							'Label'					=> $record['Record_Label'],		// Track's label
							'Artist_Name'			=> $record['Display_Artist'],
							'Performer_ID'			=> $this->addPerformer($record['Display_Artist']) ));

						break;
					}

					case 'ARI':
					{
						$this->addARI($data);
						break;
					}

					case 'XRF':
					{
						$this->addXRef($record);
						break;
					}

					default:
					{
						$this->Msgs[] = sprintf("Skipping record type '%s' (line %d)\n", $record['Record_Type'], $line);
						if(!empty($record['Record_Type'])) $this->Msgs[] = sprintf("Skipping Record Type '%s' -- Expected record number %d (line %d)\n", $record['Record_Type'], $rc, $line);
					}
				}

			} 	while (($buffer = strtok("\r\n")) !== false && empty($this->Trailer['Tx_Count']) );
			ksort($this->Works);
		}
		else 
		{
			$this->Msgs[] = "No CWR file loaded!";
			$this->errors++;
			return(false);
		}
		$this->CWR_Work_IDs = array_unique($this->CWR_Work_IDs);
		return(true);
	}
}
?>
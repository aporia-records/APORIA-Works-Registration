<?php
/*************************** APORIA WorksRegistration Class ***************************

	APORIA Works Registration
	Copyright © 2016, 2017, 2018 Gord Dimitrieff <gord@aporia-records.com>

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

Full changlog here:
https://github.com/aporia-records/APORIA-Works-Registration/wiki/Change-Log

v1.48
bug fixes:
- CWR_Revision: now defaults to revision 1 if CWR version is set to 2.2
- addShareholder(): now correctly returns true if a shareholder was successfully added
- Publisher roles now correctly sorted: The first SPU record within a chain must be for an Original Publisher or Income Participant (Publisher Type = “E” or “PA”).
- empty/null values for Society Code fields are now outputted correctly
- cwr-lib.php: ISRC_Validity flag is now set after checking the ISRC supplied for a 'REC' record type
- setXRef(): will now skip any record with an Organisation Code of '000' or '099'
- addShareholder(): Now strips invalid Society Codes and replaces with the No Society value
- addPerformer(); Now converts names to upper-case

New functions:
addAltTitle(title, type, language) - will add an ALT list entry
getAltTitles() - returns the ALT list array
removeShare() - removes the current share and reindexes the shares array within the work

*/

require("cwr-lib.php");

class WorksRegistration {

	// Name of this class
	const Name		= "APORIA Works Registration";

	// Version/revision of this class
	const Version	= 1.48;

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

	public	$character_set = '';
	public	$transmission_date;
	private $creation_date;
	private $creation_time;

	private $CISAC_Society_Codes = array(
		  1,   2,   3,   4,   5,   6,   7,   8,   9,  10,  11,  12,  14,  15,  16,  17,  18,  19,  20,  21,
		 22,  23,  24,  25,  26,  28,  29,  30,  31,  32,  33,  34,  35,  36,  37,  38,  39,  40,  41,  43, 
		 44,  45,  47,  48,  49,  50,  51,  52,  54,  55,  56,  57,  58,  59,  60,  61,  62,  63,  64,  65, 
		 66,  67,  68,  69,  70,  71,  72,  73,  74,  75,  76,  77,  78,  79,  80,  82,  84,  85,  86,  87, 
		 88,  89,  90,  91,  93,  94,  95,  96,  98, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 
		112, 115, 116, 117, 118, 119, 120, 121, 122, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134, 
		135, 136, 137, 138, 139, 140, 141, 142, 143, 144, 145, 146, 147, 148, 149, 151, 152, 153, 154, 155, 
		156, 157, 158, 159, 160, 161, 162, 163, 164, 166, 168, 169, 170, 171, 172, 173, 174, 175, 176, 177, 
		178, 179, 181, 182, 183, 184, 186, 187, 189, 190, 191, 192, 193, 194, 195, 196, 197, 198, 199, 200, 
		201, 202, 203, 204, 206, 207, 208, 209, 210, 212, 213, 214, 215, 216, 217, 218, 219, 220, 221, 222, 
		223, 224, 225, 226, 227, 228, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 239, 240, 241, 242, 
		243, 244, 245, 246, 247, 248, 249, 250, 251, 252, 253, 254, 256, 257, 258, 259, 260, 261, 262, 263, 
		264, 265, 266, 267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277, 278, 279, 280, 281, 282, 283, 
		284, 285, 286, 287, 288, 289, 290, 291, 292, 293, 294, 295, 296, 297, 298, 299, 300, 301, 302, 303, 
		304, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317, 318, 319, 320, 321, 322, 635, 658, 
		672, 707, 758, 775, 776, 777, 778, 779, 888
	);

	private $TIS_Numeric_Codes = array(
		4, 8, 12, 20, 24, 28, 31, 32, 36, 40, 44, 48, 50, 51, 52, 56, 64, 68, 70, 72, 76, 84, 90, 96, 100, 104, 108, 112, 
		116, 120, 124, 132, 140, 144, 148, 152, 156, 158, 170, 174, 178, 180, 188, 191, 192, 196, 200, 203, 204, 208, 212, 
		214, 218, 222, 226, 230, 231, 232, 233, 242, 246, 250, 258, 262, 266, 268, 270, 276, 278, 280, 288, 296, 300, 308, 
		320, 324, 328, 332, 336, 340, 344, 348, 352, 356, 360, 364, 368, 372, 376, 380, 384, 388, 392, 398, 400, 404, 408, 
		410, 414, 417, 418, 422, 426, 428, 430, 434, 438, 440, 442, 446, 450, 454, 458, 462, 466, 470, 478, 480, 484, 492, 
		496, 498, 499, 504, 508, 512, 516, 520, 524, 528, 540, 548, 554, 558, 562, 566, 578, 583, 584, 585, 586, 591, 598, 
		600, 604, 608, 616, 620, 624, 626, 630, 634, 642, 643, 646, 659, 662, 670, 674, 678, 682, 686, 688, 690, 694, 702, 
		703, 704, 705, 706, 710, 716, 720, 724, 728, 729, 732, 736, 740, 748, 752, 756, 760, 762, 764, 768, 776, 780, 784, 
		788, 792, 795, 798, 800, 804, 807, 810, 818, 826, 834, 840, 854, 858, 860, 862, 882, 886, 887, 890, 891, 894, 2100, 
		2101, 2102, 2103, 2104, 2105, 2106, 2107, 2108, 2109, 2110, 2111, 2112, 2113, 2114, 2115, 2116, 2117, 2118, 2119, 
		2120, 2121, 2122, 2123, 2124, 2125, 2126, 2127, 2128, 2129, 2130, 2131, 2132, 2133, 2134, 2136);

	private $TISdata = array();
	public	$TIS_mem = 0; 		// TIS_mem will contain the amount of memory allocated to store TISdata.

	public $CWR_File_Contents;	// Will contain the contents of the CWR file
	public $CWR_Work_IDs;		// Array will contain the list of Work_IDs included in this CWR file

/* CWR v2.2 fields */
	public	$CWR_Version;
	public	$CWR_Revision;
	public	$Software_Package;
	public	$Software_Package_Version;

/* DDEX fields will be held in the DDEX array: */
	public $DDEX = array();

/* Callback functions */
	public $callback_find_unknown_writer = null; // should return an ID if the unknown writer is already in the database
	public $callback_lookup_ipi = null; // check if the IPI is valid/exists in the IPI database

/* OTHER RUNTIME SETTINGS */
	public $Transliterate = false;	// If true, characters will be transliterated to their valid CIS Character Set equivalents
    public $No_Society_Code = '';	// Default value to use when no society is delcared for a given shareholder.  This can be changed to '099' if you find it necessary

/**
 TIS Rewrite Rules:
	array(Target_Society => array(ISO_1, ISO_2, etc.))
**/
	public $TISrewrite = false;
	public $TISrewrite_rules = array(
//		'88' => array('CA')
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
		$this->CWR_Version			= (float) CWR_Version;	/* Constant defined in cwr-lib.php */

		$this->file_version 		= 1;	//file sequence starts at number 1.

		$this->Software_Package			=	substr(self::Name, 0, 30);
		$this->Software_Package_Version =	substr(sprintf("%1.2f/PHP %s", self::Version, phpversion()), 0, 30);

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
		if($last<0) return(false);

		return($this->Msgs[$last]);
	}
	
/* New Work functions */
	function NewWork($data = array())
	{
		if(!isset($this->Works)) $this->CurrentWork = 0;
		else $this->CurrentWork = count($this->Works);

		if(empty($data['ISWC'])) $data['ISWC'] = ''; // initialize an empty ISWC field, so it will always exist

		if(!empty($data['ISWC']) && !is_valid_iswc($data['ISWC']))
		{
			$this->Msgs[] = sprintf("NewWork(): Invalid ISWC %s - replaced with spaces. (%d)", $data['ISWC'], is_valid_iswc($data['ISWC']));
			$data['ISWC'] = '';
		}

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

			if(isset($data['Title'])) 
			{
				$data['Title'] = filterchars(strtoupper($data['Title']));
				if($this->Transliterate) $data['Title'] = transliterate($data['Title'], $data['Language_Code']);
			}

			foreach($data as $key => $value) {
				if(!is_array($value)) $this->Works[$this->CurrentWork][$key] = trim($value);
			}
			if(!array_key_exists('Duration', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['Duration'] = '';
			if(!array_key_exists('Priority_Flag', $this->Works[$this->CurrentWork])) $this->Works[$this->CurrentWork]['Priority_Flag'] = '';

			if(!array_key_exists('Contact_Name', $this->Works[$this->CurrentWork]))	$this->Works[$this->CurrentWork]['Contact_Name'] =& $this->Contact_Name;
			if(!array_key_exists('Contact_ID', $this->Works[$this->CurrentWork]))	$this->Works[$this->CurrentWork]['Contact_ID'] =& $this->Contact_ID;

			if(!array_key_exists('ISRC', $this->Works[$this->CurrentWork]))	$this->Works[$this->CurrentWork]['ISRC'] = array();

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

	function getISRCs()
	{
		if($this->CurrentWork > 0 && isset($this->Works[$this->CurrentWork]['ISRC']))
			return($this->Works[$this->CurrentWork]['ISRC']);
		else return(array());
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

		if($list == 'ALT')
		{
			$National_Title = false;

			// Strip invalid characters Alternative Title field
			if($data['Title_Type'] == 'OL' || $data['Title_Type'] == 'AL') $National_Title = true;
			$data['Alternate_Title'] = filterchars(strtoupper($data['Alternate_Title']));
			if($this->Transliterate) $data['Alternate_Title'] = transliterate($data['Alternate_Title'], $data['Language_Code']);
		}
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

	function removeShare() // remove the current share and reindex the array
	{
		$shareKey = $this->CurrentShare;
		$firstKey = min(array_keys($this->Works[$this->CurrentWork]['Share']));
		if($firstKey == 0) $shareKey--;
		
		unset($this->Works[$this->CurrentWork]['Share'][$shareKey]);
		$this->Works[$this->CurrentWork]['Share'] = array_merge($this->Works[$this->CurrentWork]['Share']);
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

			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['Special_Agreements_Indicator'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['Special_Agreements_Indicator'] = '';
			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['Reversionary_Indicator'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['Reversionary_Indicator'] = '';

			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['First_Recording_Refusal_Ind'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['First_Recording_Refusal_Ind'] = 'N';
			if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['Work_For_Hire_Indicator'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['Work_For_Hire_Indicator'] = 'N';

			if(!isset($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'])) $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'] = array();

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

		if(!in_array($TIS, $this->TIS_Numeric_Codes))
		{
			$this->Msgs[] = "TIS Numeric Code was not found in the TIS database.";
			return(false);
		}

		if(is_array($data))
		{
			// Indicator and Include_Exclude_Indicator have the same meanings.... for backwards compatility with older versions
			if(array_key_exists('Include_Exclude_Indicator', $data)) $data['Indicator'] =& $data['Include_Exclude_Indicator'];

			// Collection values should be zero if this territory is being excluded - added in v1.44
			if($data['Indicator'] == 'E')
			{
				$data['PR_Collection_Share'] = 0;
				$data['MR_Collection_Share'] = 0;
				$data['SR_Collection_Share'] = 0;
			}

			// Insert values into this share's TIS array
			foreach($data as $key => $value)
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS][$key] = trim($value);

			if(!array_key_exists('PR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['PR_Collection_Share'] = 0;

			if(!array_key_exists('MR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['MR_Collection_Share'] = 0;

			if(!array_key_exists('SR_Collection_Share', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['SR_Collection_Share'] = 0;

			if(!array_key_exists('Shares_Change', $this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]))
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Shares_Change'] = 'N';
			else if(empty($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Shares_Change'])) 
				$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Shares_Change'] = 'N';

			if($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['Indicator'] == 'I' &&
				($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['PR_Collection_Share']
				+	$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['MR_Collection_Share']
				+	$this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]['SR_Collection_Share'] == 0)) 
			{
				$ip_number =& $this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['IP_Number'];
				$this->Msgs[] = sprintf("addTerritory: PR Collection Share, MR Collection Share, and SR Collection Share cannot all be zero for Interested Party %d -- removing entry.", $ip_number);
				unset($this->Works[$this->CurrentWork]['Share'][$shareKey]['TIS'][$TIS]); // Remove empty territory from the collection shares
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
			$ip_number = intval($share['IP_Number']);
			if($this->Shareholders[$ip_number]['Controlled']=='Y') $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl']=1;
			else $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl']=0;

			switch($share['Role'])
			{
				case 'A': // Author, Writer, Author of Lyrics	The creator or one of the creators of a text of a musical work.
				case 'C': // Composer, Writer	The creator or one of the creators of the musical elements of a musical work.
				case 'CA': // Composer/Author	The creator or one of the creators of text and musical elements within a musical work.
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=1;
					break;
				}
				case 'TR': // Translator:	A modifier of a text in a different language.
				case 'AR': // Arranger:		A modifier of musical elements of a musical work.
				case 'SA': // Sub Author:	The author of text which substitutes or modifies an existing text of musical work.
				case 'AD': // Adaptor:		The author or one of the authors of an adapted text of a musical work.
				case 'SR': // Sub Arranger:	A creator of arrangements authorized by the Sub-Publisher.
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=2;
					break;
				}
				case 'E': //publishers
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=3;
					break;
				}
				case 'PA': //publishers
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=4;
					break;
				}
				case 'ES': //	Substituted Publisher	A publisher acting on behalf of publisher or sub-publisher.
				case 'AM': //	Administrator	An interested party that collects royalty payments on behalf of a publisher that it represents.
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=5;
					break;
				}
				case 'SE': // sub-publisher
				case 'AQ': //	Acquirer	A publisher that acquires some or all of the ownership from an Original Publisher, but yet the Original Publisher still controls the work.
				{
					$this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole']=6;
					break;
				}
			}

			$sortByRole[$shareKey]		= $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByRole'];
			$sortByControl[$shareKey]	= $this->Works[$this->CurrentWork]['Share'][$shareKey]['sortByControl'];
			$sortByChain[$shareKey]		= $this->Works[$this->CurrentWork]['Share'][$shareKey]['Link'];
		}
		array_multisort($sortByControl, SORT_DESC, $sortByChain, SORT_ASC, $sortByRole, SORT_ASC, $this->Works[$this->CurrentWork]['Share']);
//		array_multisort($sortByControl, SORT_DESC, $sortByChain, SORT_ASC, $this->Works[$this->CurrentWork]['Share']);

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

		// Check 'Grand Rights' indicator - default to 'N' if not specified.
		if(empty($work['Grand_Rights_Ind'])) $work['Grand_Rights_Ind'] = 'N';
		if($work['Grand_Rights_Ind'] != 'Y') $work['Grand_Rights_Ind'] = 'N';

		// Check Recorded Indicator - default to Unknown
		if(empty($work['Recorded_Indicator'])) $work['Recorded_Indicator'] = 'U';

		// Strip empty ISRC values
		if(empty($work['ISRC'])) unset($work['ISRC']);

		// Check work version type - default to type 'Original Work'
		if(empty($work['Version_Type'])) $work['Version_Type'] = 'ORI';

		// Check distribution category - default to 'Popular'
		if(empty($work['Musical_Work_Distribution_Category'])) $work['Musical_Work_Distribution_Category'] = 'POP';

		if(!empty($work['PER'])) $work['PER'] = array_unique($work['PER']);

		if(!empty($work['ALT']))
		{
			foreach($work['ALT'] as $altTitle)
			{
				if(!in_array($altTitle['Title_Type'], array('AT', 'TE', 'FT', 'IT', 'OT', 'TT', 'PT', 'RT', 'ET', 'OL', 'AL')))
				{
					if(empty($altTitle['Language_Code']) && ($altTtiel['Title_Type'] == 'OL' || $altTtiel['Title_Type'] == 'AL'))
					{
						$this->Msgs[] = "ALT: A language Code Must be entered if the Title Type is equal to 'OL' or 'AL'.";
						return(false);
					}
				}
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

		if(empty($work['Transaction_Type'])) $work['Transaction_Type'] = self::CWR_NWR;  // Default to NWR registrations

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
		$controlled_swt_records = 0;

		$this->CurrentShare = 0;

		while($this->NextShare())
		{
			$shareholder = $this->getShareDetails();

			$ip_number = intval($shareholder['IP_Number']);
			$ipi_name_number = $this->Shareholders[$ip_number]['IPI_Name_Number'];

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
						$this->Msgs[] = sprintf("Validation failed: PR Collection Share, MR Collection Share, and SR Collection Share are all zero for %s (%09d).", $this->Shareholders[$ip_number]['Name']." ".$this->Shareholders[$ip_number]['First_Name'], $shareholder['IPI_Name_Number']);
						return(false);
					}
				}
				
			}

			// Check if collection shares are within range for publisher role types
			switch($shareholder['Role'])
			{
				/* Publishers: */
				case 'AQ': //	Acquirer	A publisher that acquires some or all of the ownership from an Original Publisher, but yet the Original Publisher still controls the work.
				case 'AM': //	Administrator	An interested party that collects royalty payments on behalf of a publisher that it represents.
				case 'ES': //	Substituted Publisher	A publisher acting on behalf of publisher or sub-publisher.
				case 'E':  //	publishers
				case 'SE': // 	Sub-publishers
				{
					if(!empty($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS']))
						foreach($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'] as $ter => $territory)
					{
						if($territory['Indicator'] == 'I' && $territory['PR_Collection_Share'] + $territory['MR_Collection_Share'] + $territory['SR_Collection_Share'] == 0)
						{
							$this->Msgs[] = sprintf("SPT001: The Inclusion/Exclusion Indicator was entered as 'I' but PR Collection Share, MR Collection Share, and SR Collection Share were all zero (TIS %03d)", $ter);
							return(false);
						}

						if($territory['PR_Collection_Share'] > 50)
						{
							$this->Msgs[] = sprintf("SPT006: PR Collection Share was not in the range 0-50%% (TIS %03d)", $ter);
							return(false);
						}
						if($territory['MR_Collection_Share'] > 100.06)
						{
							$this->Msgs[] = sprintf("SPT007: MR Collection Share was not in the range 0-100%% (TIS %03d)", $ter);
							return(false);
						}
						if($territory['SR_Collection_Share'] > 100.06)
						{
							$this->Msgs[] = sprintf("SPT008: SR Collection Share was not in the range 0-100%% (TIS %03d)", $ter);
							return(false);
						}
						if($territory['Shares_Change'] != 'Y' && $territory['Shares_Change'] != 'N')
						{
							$this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS'][$ter]['Shares_Change'] = 'N';
							$this->Msgs[] = "Shares change when Subpublished entered was not one of 'N' or 'Y' - replaced with 'N'.";
						}
					}
					break;
				}
			}

			// Count shareholder role types
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
					if($this->Shareholders[$ip_number]['Controlled']=='Y')
					{
						$controlled_writers++;
						if(count($this->Works[$this->CurrentWork]['Share'][$this->CurrentShare]['TIS']) > 0) $controlled_swt_records++;
					}

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

			// Check Special Agreements Indicator:
			switch($shareholder['Role'])
			{
				/* Publishers: */
				case 'AQ': //	Acquirer	A publisher that acquires some or all of the ownership from an Original Publisher, but yet the Original Publisher still controls the work.
				case 'AM': //	Administrator	An interested party that collects royalty payments on behalf of a publisher that it represents.
				case 'ES': //	Substituted Publisher	A publisher acting on behalf of publisher or sub-publisher.
				case 'E': //publishers
				case 'SE': // Sub-publishers
				{
					if(!empty($shareholder['Special_Agreements_Indicator']))
					{
						if(in_array($shareholder['Special_Agreements_Indicator'], array('R', 'L', 'B', 'Y', 'N', 'U')))
						{
							// If Record Type is “OPU”, Special Agreements Indicator can only be “L” or blank. (FR - default to space)
							if($this->Shareholder[$ip_number]['Controlled'] == 'N' && $shareholder['Special_Agreements_Indicator'] != 'L')
							{
								$this->Msgs = sprintf("Warning: Non-controlled shareholder %d - Special Agreements Indicator can only be “L” or blank. (FR - default to space) ", $ip_number);
								$this->setShareDetails(array('Special_Agreements_Indicator' => ''));
							}
						}
						else
						{
							// If entered, Special Agreement Indicator must match an entry in the Special Agreement Indicator table. (FR - default to spaces)
							$this->Msgs = sprintf("Warning: Shareholder %d - Special Agreements Indicator must match an entry in the Special Agreement Indicator table. (FR - default to spaces)", $ip_number);
							$this->setShareDetails(array('Special_Agreements_Indicator' => ''));
						}
					}
					break;
				}
			}

		}

		if($writers < 1)
		{
			$this->Msgs[] = "There must be at least one writer (Writer Designation Code = 'CA', 'A', 'C') in a work.";
			return(false);
		}

		if($controlled_swt_records < 1)
		{
			$this->Msgs[] = "At least one writer controlled by collecting submitter was missing a collection territory (SWT).";
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
			$ip_number = $shareholder['IP_Number'];
			if($this->Shareholders[$ip_number]['Controlled'] == 'Y') $percentageControlled += $shareholder['PR_Ownership_Share'];
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
			if(!empty($data['Organisation_Code']) && intval($data['Organisation_Code']) != 99) // Do not use “000”or “099”.
			{
				foreach($data as $key => $value)
					$this->Works[$this->CurrentWork]['XRefs'][$xrefKey][$key] = trim($value);
				return(true);
			}
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
		if(!is_array($data) && !isset($data['IP_Number']))
		{
			$this->Msgs[] = "addShareholder() - requires data in array form.  IP_Number is mandatory.";
			return(false);
		}

		$ip_number = intval($data['IP_Number']);
		$performer_number = 0;

		if(!array_key_exists ($ip_number, $this->Shareholders)) // Skip this entry if it already exists
		{
			if((count($data) > 5))
			{
				$invalid_chars = array(chr(34), '*', ',', ':', ';', '<', '=', '>', '[', chr(92), ']', '^', '_', '{', '}', '~', '£', '€');
				
				// Strip invalid characters from Name fields
				$this->Shareholders[$ip_number]['Name'] 		= str_replace($invalid_chars, "", filterchars(strtoupper($data['Name'])));
				$this->Shareholders[$ip_number]['First_Name']	= str_replace($invalid_chars, "", filterchars(strtoupper($data['First_Name'])));

				$this->Shareholders[$ip_number]['Controlled'] 	= $data['Controlled'];
				$this->Shareholders[$ip_number]['US_Rep'] 		= $data['US_Rep'];

				if(!empty($data['IPI_Name_Number']))
				{
					$ipi_name_number = $data['IPI_Name_Number'];
					
					$performer_number = $this->findPerformerByIPIName($ipi_name_number);

					$this->Shareholders[$ip_number]['IPI_Name_Number'] = $ipi_name_number;
				    if(!is_valid_ipi_name($ipi_name_number) && $this->Shareholders[$ip_number]['Controlled']=='Y')
					{
						$this->Msgs[] = sprintf("Validation failed: invalid IPI number for controlled writer %s (%011d).", $this->Shareholders[$ip_number]['Name']." ".$this->Shareholders[$ip_number]['First_Name'], $ipi_name_number);
						$this->Shareholders[$ip_number]['IPI_Name_Number_Valid'] = 'N';
					}
					else if(is_callable($this->callback_lookup_ipi))
					{
						if(! $this->callback_lookup_ipi($ipi_name_number))
						{
							$this->Msgs[] = sprintf("Validation failed: IPI number for controlled writer %s (%09d) not found in database.", $this->Shareholders[$ip_number]['Name']." ".$this->Shareholders[$ip_number]['First_Name'], $ipi_name_number);
							$this->Shareholders[$ip_number]['IPI_Name_Number_Valid'] = 'N';
						}
					}
				}
				else $this->Shareholders[$ip_number]['IPI_Name_Number'] = ''; // No IPI Name Number - default to spaces

				if(!empty($data['IPI_Base_Number']) && is_valid_ipi_base($data['IPI_Base_Number'])) $this->Shareholders[$ip_number]['IPI_Base_Number'] = $data['IPI_Base_Number'];
				else $this->Shareholders[$ip_number]['IPI_Base_Number'] = ''; // No IPI Base Number - default to spaces

				if($performer_number > 0) $this->Performers[$performer_number]['IPI_Base_Number'] = $this->Shareholders[$ip_number]['IPI_Base_Number'];

                if(intval($data['PRO']) > 0) $this->Shareholders[$ip_number]['PRO'] = $data['PRO'];
                else $this->Shareholders[$ip_number]['PRO'] = $this->No_Society_Code; // Default to No Society if none is declared

                if(intval($data['MRO']) > 0) $this->Shareholders[$ip_number]['MRO'] = $data['MRO'];
                else $this->Shareholders[$ip_number]['MRO'] = $this->No_Society_Code; // Default to No Society if none is declared

                if(intval($data['SRO']) > 0) $this->Shareholders[$ip_number]['SRO'] = $data['SRO'];
                else $this->Shareholders[$ip_number]['SRO'] = $this->No_Society_Code; // Default to No Society if none is declared

				// Replace 99 'no society' codes with the No_Society_Code
                if(intval($this->No_Society_Code) != 99)
                {
                    if(intval($this->Shareholders[$ip_number]['PRO']) == 99) $this->Shareholders[$ip_number]['PRO'] = $this->No_Society_Code;
                    if(intval($this->Shareholders[$ip_number]['MRO']) == 99) $this->Shareholders[$ip_number]['MRO'] = $this->No_Society_Code;
                    if(intval($this->Shareholders[$ip_number]['SRO']) == 99) $this->Shareholders[$ip_number]['SRO'] = $this->No_Society_Code;
                }

				// Strip invalid Society Codes
				if(!empty($this->Shareholders[$ip_number]['PRO']) && !in_array(intval($this->Shareholders[$ip_number]['PRO']), $this->CISAC_Society_Codes)) 
				{
					$this->Msgs[] = sprintf("Invalid PRO Code %03d - removed from Interested Party %d (%s).", $this->Shareholders[$ip_number]['PRO'], $data['IP_Number'], $this->Shareholders[$ip_number]['Name']);
					$this->Shareholders[$ip_number]['PRO'] = $this->No_Society_Code;
				}
				if(!empty($this->Shareholders[$ip_number]['MRO']) && !in_array(intval($this->Shareholders[$ip_number]['MRO']), $this->CISAC_Society_Codes))
				{
					$this->Msgs[] = sprintf("Invalid MRO Code %03d - removed from Interested Party %d (%s).", $this->Shareholders[$ip_number]['MRO'], $data['IP_Number'], $this->Shareholders[$ip_number]['Name']);
					$this->Shareholders[$ip_number]['MRO'] = $this->No_Society_Code;
					print_r($this->CISAC_Society_Codes);
				}
				if(!empty($this->Shareholders[$ip_number]['SRO']) && !in_array(intval($this->Shareholders[$ip_number]['SRO']), $this->CISAC_Society_Codes))
				{
					$this->Msgs[] = sprintf("Invalid SRO Code %03d - removed from Interested Party %d (%s).", $this->Shareholders[$ip_number]['SRO'], $data['IP_Number'], $this->Shareholders[$ip_number]['Name']);
					$this->Shareholders[$ip_number]['SRO'] = $this->No_Society_Code;
				}

				if(!empty($data['Personal_Number'])) $this->Shareholders[$ip_number]['Personal_Number'] = $data['Personal_Number'];
				else $this->Shareholders[$ip_number]['Personal_Number'] = ''; // Initialize Personal Number if none specified

				if(!empty($data['Unknown_Indicator'])) $this->Shareholders[$ip_number]['Unknown_Indicator'] = $data['Unknown_Indicator'];
				else $this->Shareholders[$ip_number]['Unknown_Indicator'] = ''; // Initialize Unknown Indictor if none specified

				// Non-controlled shareholder checks:
				if($this->Shareholders[$ip_number]['Controlled'] == 'N')
				{
					if(empty($this->Shareholders[$ip_number]['Name']))
					{
						$this->Shareholders[$ip_number]['Unknown_Indicator'] = 'Y';
						$this->Msgs[] = sprintf("Warning: Shareholder %d - Last Name empty; Unknown Indicator set to 'Y'.", $ip_number);
					} 

					if($this->Shareholders[$ip_number]['Unknown_Indicator'] == 'Y')
					{
							if(!empty($this->Shareholders[$ip_number]['Name']))
							{
								$this->Shareholders[$ip_number]['Name'] = '';
								$this->Msgs[] = sprintf("Warning: Shareholder %d - Unknown Indicator set to 'Y' -- Last Name set to spaces.", $ip_number);
							}
					} else { 
						// If Record Type is equal to OWR, and Writer Unknown Indicator is entered, it must be equal to Y or N (FR - default to N) 
						$this->Shareholders[$ip_number]['Unknown_Indicator'] = 'N';
					}
				} 

				// Controlled shareholder checks:
				if($this->Shareholders[$ip_number]['Controlled'] == 'Y') 
				{ 
					if(empty($this->Shareholders[$ip_number]['Name']))
					{
						$this->Msgs[] = sprintf("Shareholder %d - Cannot add a Controlled Shareholder without a Last Name.", $ip_number);
						return(false);
					}
					if(empty($this->Shareholders[$ip_number]['Unknown_Indicator']))
					{
						$this->Shareholders[$ip_number]['Unknown_Indicator'] = '';
//						$this->Msgs[] = sprintf("Warning: Controlled Shareholder %d - Unknown Indicator not set.", $ip_number);
					}
				}
			}
			else
			{
				$this->Msgs[] = sprintf("Insufficient data in shareholders table! (Interested Party: %d)", $ip_number);
				return(false);
			}
		}
		else return(false);
		return(true);
	}

	function findPerformerByIPIName($ipi_name_number)
	{
		if(empty($ipi_name_number)) return(false);

		foreach($this->Performers as $performer_number => $performer)
			if($performer['IPI_Name_Number'] == $ipi_name_number) return($performer_number);

		return(false);
	}

	function findShareholderByIPIName($ipi_name_number)
	{
		foreach($this->Shareholders as $ip_number => $shareholder)
			if($shareholder['IPI_Name_Number'] == $ipi_name_number) return($ip_number);

		return(false);
	}

	function tempIPnumber($lastname, $firstname, $pro)
	{
		$ip_number = false;
		$temp_ip_number = array();

		if(is_callable($this->callback_find_unknown_writer)) $ip_number = $this->callback_find_unknown_writer($lastname, $firstname, $pro);

		if($ip_number == false)
		{
			foreach($this->Shareholders as $writer)
			{
				if($writer['IP_Number'] < 100000000 ) $temp_ip_number[] = $writer['IP_Number'];
				if($writer['Name'].'/'.$writer['First_Name'].'/'.$writer['PRO'] == $lastname.'/'.$firstname.'/'.$pro) $ip_number = $writer['IP_Number'];
			}
			$ip_number = max($temp_ip_number) + 1;
		}
		return($ip_number);
	}

	function getPerfomers()
	{
		return($this->Works[$this->CurrentWork]['PER']);
	}

	function addAltTitle($title, $type, $language_code = '')
	{
		$this->addToList(array(
			'Record_Type'		=> 'ALT', 
			'Alternate_Title'	=> $title,
			'Title_Type'		=> $type,
			'Language_Code'		=> $language_code));
	}

	function getAltTitles()
	{
		return($this->Works[$this->CurrentWork]['ALT']);
	}

	function addPerformer($lastname, $firstname = '', $ipi_name_number = 0, $ipi_base_number = '')
	{
		$found = false;
		$i = 1;

		$lastname = strtoupper($lastname);
		$firstname = strtoupper($firstname);

		while($i <= count($this->Performers) && !$found)
		{
			if(!isset($this->Performers[$i])) break;
			$performer =& $this->Performers[$i];
			if($ipi_name_number > 0 && $performer['IPI_Name_Number'] == $ipi_name_number) $found = $i;
			else if($performer['Last_Name'].$performer['First_Name'] == $lastname.$firstname) $found = $i;
			$i++;
		}

		if(!is_valid_ipi_base($ipi_base_number)) $ipi_base_number = ''; // Replace invalid IPI Base Numbers with blanks

		if($ipi_name_number > 0)
		{
			$ip_number = $this->findShareholderByIPIName($ipi_name_number);
			if($ip_number) $ipi_base_number = $this->Shareholders[$ip_number]['IPI_Base_Number'];
		}

		if($found == false)
		{
			$this->Performers[$i] = array(
				'Last_Name'			=> $lastname,
				'First_Name'		=> $firstname,
				'IPI_Name_Number'	=> $ipi_name_number,
				'IPI_Base_Number'	=> $ipi_base_number);

			$found = $i;

//			end($this->Performers);
//			$found = key($this->Performers);
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

		if(!isset($data['ISWC'])) $data['ISWC'] = '';

		if(!empty($data['ISWC']) && !is_valid_iswc($data['ISWC']))
		{
			$this->Msgs[] = sprintf("addTrack(): Invalid ISWC %s - replaced with spaces.", $data['ISWC']);
			$data['ISWC'] = '';
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

			$tmp = array();

			if(!empty($this->track[$isrc]['Track_ID']))	$tmp['Track_ID']	= $this->track[$isrc]['Track_ID'];
			else if(!empty($data['Track_ID'])) 			$tmp['Track_ID']	= $data['Track_ID'];

			if(!empty($this->track[$isrc]['ISRC']))		$tmp['ISRC']		= $this->track[$isrc]['ISRC'];
			else if(!empty($data['ISRC']))				$tmp['ISRC']		= $data['ISRC'];

			if(!empty($this->track[$isrc]['Work_ID']))	$tmp['Work_ID']		= $this->track[$isrc]['Work_ID'];
			else if(!empty($data['Work_ID']))			$tmp['Work_ID']		= $data['Work_ID'];

			if(!empty($this->track[$isrc]['ISWC']))		$tmp['ISWC']		= $this->track[$isrc]['ISWC'];
			else if(!empty($data['ISWC']))				$tmp['ISWC']		= $data['ISWC'];

			if(count($tmp) > 0) $this->release[$upc]['Tracks'][] = $tmp;
			unset($tmp);

//			$this->release[$upc]['Tracks'][] = array('Track_ID' => $this->track[$isrc]['Track_ID'], 'ISRC' => $this->track[$isrc]['ISRC'], 'Work_ID' => $this->track[$isrc]['Work_ID'], 'ISWC' => $this->track[$isrc]['ISWC']);
		}

		foreach($data as $key => $value)
			if(!is_array($value)) $this->track[$isrc][$key] = strtoupper(trim($value));

		// ISRC validity check
		if(is_valid_isrc($this->track[$isrc]['ISRC'])) $this->track[$isrc]['ISRC_Validity'] = 'Y';
		else $this->track[$isrc]['ISRC_Validity'] = 'N';

		// ISRC-ISWC link check: invalid if there is no corresponding ISWC
		if(empty($this->track[$isrc]['ISWC'])) $this->track[$isrc]['ISRC_Validity'] = 'U';

		// Recording format defaults to Audio
		if(empty($this->track[$isrc]['Recording_Format'])) $this->track[$isrc]['Recording_Format'] = 'A';
		if($this->track[$isrc]['Recording_Format'] != 'V') $this->track[$isrc]['Recording_Format'] = 'A';

		// Recording technique - defaults to Unknown
		if(empty($this->track[$isrc]['Recording_Technique'])) $this->track[$isrc]['Recording_Technique'] = 'U';
		if($this->track[$isrc]['Recording_Technique'] != 'A' && $this->track[$isrc]['Recording_Technique'] != 'D') $this->track[$isrc]['Recording_Technique'] = 'U';

		// Initialize the releases list if it does not already exist
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

		if(!$submitter_ip = $this->findShareholderByIPIName($this->submitter_ipi))
		{
			$this->Msgs[] = "CANNOT GENERATE CWR!\nSubmitter Name not supplied - use addShareholder() to include it.";
			return(false);
		}

		if(empty($this->receiver_society))
			$this->Msgs[] = "WARNING: No receiver society specified!";

		if($this->CWR_Version == 2.2 && $this->CWR_Revision == 0)
			$this->CWR_Revision		= 1;	// Set to CWR 2.2 revision 1 if not otherwise defined

		/* Generate Transmission Header */
		$rc++;
		$rec = array(
				'Record_Type'		=> 'HDR',
				'Sender_Type' 		=> 'PB', 
				'Sender_ID'			=> $this->submitter_ipi, 
				'Sender_Name'		=> $this->Shareholders[$submitter_ip]['Name'], 
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
				$work = array_filter($this->getWorkDetails(), 'is_not_array');		// Get the first-level values of the current Work array [is_not_array() defined in cwr-lib.php]

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
						/**
							Generate NWR Record (Transaction Header)
						**/
						$rec = $work;
						$rec['Work_Title'] =& $rec['Title'];			// Remap 'Title' to 'Work_Title'
						$rec['Submitter_Work_ID'] =& $rec['Work_ID'];	// Remap 'Work_ID' to 'Submitter_Work_ID'
						$rec['Record_Type'] = $transaction_type;		// Set the transaction type
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
//										$this->Msgs[] = sprintf("NOTICE: Sub-Publisher '%s' has no collection rights in the relevant territorie(s) - removed from CWR.", $this->Shareholders[$shareholder['IPI_Name_Number']]['Name']);
										$this->Msgs[] = sprintf("NOTICE: Sub-Publisher '%s' has no collection rights in the relevant territorie(s) - removed from CWR.", $shareholder['IPI_Name_Number']);
										print_r($this->Shareholders[$shareholder['IPI_Name_Number']]);
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
							$ip_number = $shareholder['IP_Number'];

							if(empty($shareholder['Link'])) $this->Msgs("No chain of title declared! (work: %s)", $work['Title']);
							$chain = intval($shareholder['Link']);

							if($shareholder['Role'] == 'E')
							{
								$original_publisher[$chain][0] = $ip_number;
								$pub_sequence[$ip_number] = $chain;
							}

							if(array_key_exists('coPublisher', $shareholder))
								$original_publisher[$shareholder['coPublisher']][] = $ip_number;

							$rec = array(
								'Publisher_Sequence_Number'		=> $chain,
								'Interested_Party_Number'		=> $ip_number,
								'Publisher_Name'				=> $this->Shareholders[$ip_number]['Name'],
								'Publisher_Unknown_Indicator'	=> $this->Shareholders[$ip_number]['Unknown_Indicator'],
								'Publisher_CAE_IPI_Name_Number'	=> $this->Shareholders[$ip_number]['IPI_Name_Number'],
								'Publisher_IPI_Base_Number'		=> $this->Shareholders[$ip_number]['IPI_Base_Number'],
								'Publisher_Type'				=> $shareholder['Role'],

								'PR_Society'					=> $this->Shareholders[$ip_number]['PRO'],
								'PR_Ownership_Share'			=> $shareholder['PR_Ownership_Share'],
								'MR_Society'					=> $this->Shareholders[$ip_number]['MRO'],
								'MR_Ownership_Share'			=> $shareholder['MR_Ownership_Share'],
								'SR_Society'					=> $this->Shareholders[$ip_number]['SRO'],
								'SR_Ownership_Share'			=> $shareholder['SR_Ownership_Share'],

								'Special_Agreements_Indicator'	=> $shareholder['Special_Agreements_Indicator'],
								'First_Recording_Refusal_Ind'	=> $shareholder['First_Recording_Refusal_Ind'],

//								'Submitter_Agreement_Number'	=> $shareholder['Agreement_Number'],
//								'International_Standard_Agreement_Code' 
//								'Society-assigned_Agreement_Number'
//								'Agreement_Type'									

								'USA_License_Ind'				=> substr($this->Shareholders[$ip_number]['US_Rep'], 0, 1)
							);
							
							if($this->Shareholders[$ip_number]['Controlled'] == 'Y')
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
										'Interested_Party_Number'		=> $ip_number,
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
							$ip_number = $shareholder['IP_Number'];

							$rec = array(
								'Interested_Party_Number'		=> $ip_number,
								'Writer_Last_Name'				=> $this->Shareholders[$ip_number]['Name'],
								'Writer_First_Name'				=> $this->Shareholders[$ip_number]['First_Name'],
								'Writer_Unknown_Indicator'		=> $this->Shareholders[$ip_number]['Unknown_Indicator'],
								'Writer_Designation_Code'		=> $shareholder['Role'],

								'Writer_CAE_IPI_Name_Number'	=> $this->Shareholders[$ip_number]['IPI_Name_Number'],
								'Writer_IPI_Base_Number'		=> $this->Shareholders[$ip_number]['IPI_Base_Number'],
								'Personal_Number'				=> $this->Shareholders[$ip_number]['Personal_Number'],

								'PR_Society'					=> $this->Shareholders[$ip_number]['PRO'],
								'PR_Ownership_Share'			=> $shareholder['PR_Ownership_Share'],
								'MR_Society'					=> $this->Shareholders[$ip_number]['MRO'],
								'MR_Ownership_Share'			=> $shareholder['MR_Ownership_Share'],
								'SR_Society'					=> $this->Shareholders[$ip_number]['SRO'],
								'SR_Ownership_Share'			=> $shareholder['SR_Ownership_Share'],
								'USA_License_Ind'				=> substr($this->Shareholders[$ip_number]['US_Rep'], 0, 1),

								'Reversionary_Indicator'		=> $shareholder['Reversionary_Indicator'],
								'First_Recording_Refusal_Ind'	=> $shareholder['First_Recording_Refusal_Ind'],
								'Work_For_Hire_Indicator'		=> $shareholder['Work_For_Hire_Indicator'],
								
							);

							if($this->Shareholders[$ip_number]['Controlled'] == 'Y')
									$rec['Record_Type'] = 'SWR';
							else 	$rec['Record_Type'] = 'OWR';

							$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);

							$territory_sq = 0;
							if($rec['Record_Type'] == 'SWR' || ($this->CWR_Version > 2.1)) /* Generate SWT record for controlled writers or OWT for CWR 2.2 */
							{
								// Create a record for each collection territory defined under this share
								if(!empty($shareholder['TIS'])) foreach($shareholder['TIS'] as $TIS_Numeric_Code => $territory)
								{
									$sq++;
									$rc++;
									$group_rc++;
									$sh++;
									$territory_sq++;

									if($rec['Record_Type'] == 'SWR') $swt_owt = 'SWT';
									else $swt_owt = "OWT";

									$rec = array(
										'Record_Type'					=> $swt_owt,
										'Interested_Party_Number'		=> $ip_number,
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
							if($this->Shareholders[$ip_number]['Controlled'] == 'Y' || ($this->CWR_Version > 2.1 && array_key_exists($shareholder['Link'], $original_publisher)))  // Only genereate PWR for controlled writers, or if CWR is version 2.2+
							{
								foreach($original_publisher[$shareholder['Link']] as $pub_ip_number)
								{
									$sq++;
									$rc++;
									$group_rc++;
									$sh++;

									$rec = array(
										'Record_Type'				=> 'PWR',
										'Publisher_IP_Number'		=> $pub_ip_number,
										'Publisher_Name'			=> $this->Shareholders[$pub_ip_number]['Name'],
										'Writer_IP_Number'			=> $ip_number
									);

									/* include publisher sequence number if CWR version is 2.2+ */
									if($this->CWR_Version > 2.1) $rec['Publisher_Sequence_Number'] = $pub_sequence[$pub_ip_number];

									$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
								}
							}
						}

						/* ALT - Alternative Titles */
						$work['ALT'] = $this->getAltTitles();
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
						$work['PER'] = $this->getPerfomers();
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
									'Performing_Artist_CAE_IPI_Name_Number' => $this->Performers[$performer_id]['IPI_Name_Number'],
									'Performing_Artist_IPI_Base_Number' 	=> $this->Performers[$performer_id]['IPI_Base_Number']);

								$cwr .= encode_cwr($this->Msgs, $rec, $group_tx, $sq);
							}
						}

						/* REC - Recording Detail - only create a record if we have an associated ISRC */
						$work['ISRC'] = $this->getISRCs();
						if(is_array($work['ISRC']))
						{
							$recordings = array();
							if(count($work['ISRC']) > 0) foreach($work['ISRC'] as $isrc)
							{
								if(is_valid_isrc($isrc))
								{

									$rec = array(
										'Record_Type'				=> 'REC',
										'Recording_Format'			=> $this->track[$isrc]['Recording_Format'],
										'Recording_Technique'		=> $this->track[$isrc]['Recording_Technique'],
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
												$rec['ISRC_Validity'] = 'Y'; // ISRC validity has already been checked by is_valid_isrc()
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
	// end of WriteCWR()

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
						$record['Language_Code']			= $record['Language_Code'];

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
							'IP_Number'						=> $record['Interested_Party_Number'],
							'IPI_Name_Number'				=> $record['Publisher_CAE_IPI_Name_Number'],
							'IPI_Base_Number'				=> $record['Publisher_IPI_Base_Number'],
							'First_Name'					=> '', // First_Name is left blank for publishers
							'Name'							=> $record['Publisher_Name'],
							'PRO'							=> $record['PR_Society'],
							'MRO'							=> $record['MR_Society'],
							'SRO'							=> $record['SR_Society'],
							'Controlled'					=> $record['Controlled'],
							'US_Rep'						=> $record['USA_License_Ind']));

						$this->NewShare(array(
							'IP_Number'						=> $record['Interested_Party_Number'],
							'Link'							=> intval($record['Publisher_Sequence_Number']),
							'Role'							=> $record['Publisher_Type'],
							'PR_Ownership_Share'			=> $record['PR_Ownership_Share'],
							'MR_Ownership_Share'			=> $record['MR_Ownership_Share'],
							'SR_Ownership_Share'			=> $record['SR_Ownership_Share'],
							'Special_Agreements_Indicator'	=> $record['Special_Agreements_Indicator']));
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

						$ip_number = intval($record['Interested_Party_Number']);
						if($ip_number == 0) $ip_number = $this->tempIPnumber($record['Writer_Last_Name'], $record['Writer_First_Name'], $record['PR_Society']); // Assign temp IPI in place of unknown IPI

						$this->addShareholder(array(
							'IP_Number'					=> $ip_number,
							'IPI_Name_Number'			=> $record['Writer_CAE_IPI_Name_Number'],
							'IPI_Base_Number'			=> $record['Writer_IPI_Base_Number'],
							'First_Name'				=> $record['Writer_First_Name'],
							'Name'						=> $record['Writer_Last_Name'],
							'PRO'						=> $record['PR_Society'],
							'MRO'						=> $record['MR_Society'],
							'SRO'						=> $record['SR_Society'],
							'Controlled'				=> $record['Controlled'],
							'US_Rep'					=> $record['USA_License_Ind']));

						$this->NewShare(array(
							'IP_Number'					=> $ip_number,

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
								if($share['IP_Number'] == $record['Publisher_IP_Number']) $link = intval($share['Link']);
							}
						}
						else $link = intval($record['Publisher_Sequence_Number']); // CWR v2.2+ does not require a lookup

						// Locate writer in the shares table and set the chain-of-title Link
						$this->CurrentShare = 0;
						while($this->NextShare())
						{
							$share = $this->getShareDetails();
							if($share['IP_Number'] == $record['Writer_IP_Number']) $this->setShareDetails(array('Link' => $link));
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
						$this->Msgs[] = sprintf("Skipping record type '%s' (line %d)", $record['Record_Type'], $line);
						if(!empty($record['Record_Type'])) $this->Msgs[] = sprintf("Skipping Record Type '%s' -- Expected record number %d (line %d)", $record['Record_Type'], $rc, $line);
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
	// end of ReadCWR()

/******************************************************
/* DDEX Registration Formats
/******************************************************/



}
?>
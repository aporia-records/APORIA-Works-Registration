<?php
/*************************** APORIA WorksRegistration Class ***************************

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

/****************************** CWR FUNCTIONS **************************************/

/* Constants */
define("EDI_Version", "01.10");
define("CWR_Version", "02.10"); // CWR2.1

/* Required by the calculateModulo101CheckSum() function for validating IPI numbers */
define("MODULUS_101", 101);
define("SPECIAL_CASE", 0);
define("DEFAULT_CHECK_SUM", 0);


/*	UPC and EAN-13 check digit validator
	Apr 24, 2009
	This code is licensed for use under the LGPLv3
	(C) 2009 Marty Anstey
	https://marty.anstey.ca/
	This function has been optimized for speed fairly well. I tested this function against over one million EAN-13 codes from www.upcdatabase.com and all check digits were validated correctly. Only a few hand-typed UPC codes were tested however, so if you experience any problems please let me know (visit the contact page on my website) and please pass along the offending UPC code.
	If you end up using this function in your application, a note in the credits would be appreciated ;)
	No error checking is performed; expects a 12 or 13 digit code only. Returns TRUE if the check digit in the UPC or EAN-13 code is correct, FALSE otherwise.
*/
function verifycheckdigit($val)
{
	return ((((ceil(($a=((strlen($val)==12)?((($val[0]+$val[2]+$val[4]+$val[6]+$val[8]+$val[10])*3)+($val[1]+$val[3]+$val[5]+$val[7]+$val[9])):(($val[0]+$val[2]+$val[4]+$val[6]+$val[8]+$val[10])+(($val[1]+$val[3]+$val[5]+$val[7]+$val[9]+$val[11])*3))))/10))*10-$a)==substr($val,-1,1))?TRUE:FALSE);
}

/**
 * Luhn algorithm validity check, sourced from the wikipedia article (https://en.wikipedia.org/wiki/Luhn_algorithm)
 *
 */
function checkLuhn($number)
{
	$sum = 0;
	$numDigits = strlen($number);
	$parity = $numDigits % 2;
	for ($i = 0; $i < $numDigits; $i++)
	{
		$digit = substr($number, $i, 1);
		if ($parity == ($i % 2))
		{
			$digit <<= 1;
			if (9 < $digit) $digit = $digit - 9;
		}
		$sum += intval($digit);
	}
	return (0 == ($sum % 10));
}

/**
 * Adapted from Java code provided by Peter Klauser @ SUISA
 *
 * @param nineDigitNumber
 *			string of digits, in the most cases it's the id
 */
function calculateModulo101CheckSum($nineDigitNumber)
{
	$sum = 0;
	$subStringCounter = 0;

	for ($i = 10; $i > 1; $i--)
	{
		$digit = substr($nineDigitNumber, $subStringCounter, 1);
		$newDigit = $digit * $i;
		$sum += $newDigit;
		$subStringCounter++;
	}

	$checkSum = $sum % MODULUS_101;
	if ($checkSum != 0)
	{
		if ($checkSum == SPECIAL_CASE) return(DEFAULT_CHECK_SUM);
		$checkSum = MODULUS_101 - $checkSum;
	}
	return(sprintf("%02d", $checkSum));
}

function is_valid_ipi_name($ipi)
{
	if(strlen($ipi) != 11 || !preg_match("/[0-9]{11}/", $ipi)) return(false);
	if($ipi != substr($ipi, 0, 9).calculateModulo101CheckSum($ipi)) {printf("%s\n", substr($ipi, 0, 9).calculateModulo101CheckSum($ipi)); return(false);}

	return(true);
}

function is_valid_ipi_base($ipibase)
{
	if(strlen($ipibase != 13) || !preg_match("/I-[0-9]{9}-[0-9]/", $ipibase)) return(false);

	return(true);
}

function is_valid_iswc($iswc)
{
	if(!preg_match("/T[0-9]{10}/", $iswc)) return(false);
	if(!checkLuhn($iswc)) return(false);

	return(true);
}

function is_valid_isrc($isrc)
{
	$country_codes = array(

		// ISO 3166-1 alpha 2 Codes:
		'AF', // 'Afghanistan',
		'AX', // 'Aland Islands',
		'AL', // 'Albania',
		'DZ', // 'Algeria',
		'AS', // 'American Samoa',
		'AD', // 'Andorra',
		'AO', // 'Angola',
		'AI', // 'Anguilla',
		'AQ', // 'Antarctica',
		'AG', // 'Antigua And Barbuda',
		'AR', // 'Argentina',
		'AM', // 'Armenia',
		'AW', // 'Aruba',
		'AU', // 'Australia',
		'AT', // 'Austria',
		'AZ', // 'Azerbaijan',
		'BS', // 'Bahamas',
		'BH', // 'Bahrain',
		'BD', // 'Bangladesh',
		'BB', // 'Barbados',
		'BY', // 'Belarus',
		'BE', // 'Belgium',
		'BZ', // 'Belize',
		'BJ', // 'Benin',
		'BM', // 'Bermuda',
		'BT', // 'Bhutan',
		'BO', // 'Bolivia',
		'BA', // 'Bosnia And Herzegovina',
		'BW', // 'Botswana',
		'BV', // 'Bouvet Island',
		'BR', // 'Brazil',
		'IO', // 'British Indian Ocean Territory',
		'BN', // 'Brunei Darussalam',
		'BG', // 'Bulgaria',
		'BF', // 'Burkina Faso',
		'BI', // 'Burundi',
		'KH', // 'Cambodia',
		'CM', // 'Cameroon',
		'CA', // 'Canada',
		'CV', // 'Cape Verde',
		'KY', // 'Cayman Islands',
		'CF', // 'Central African Republic',
		'TD', // 'Chad',
		'CL', // 'Chile',
		'CN', // 'China',
		'CX', // 'Christmas Island',
		'CC', // 'Cocos (Keeling) Islands',
		'CO', // 'Colombia',
		'KM', // 'Comoros',
		'CG', // 'Congo',
		'CD', // 'Congo, Democratic Republic',
		'CK', // 'Cook Islands',
		'CR', // 'Costa Rica',
		'CI', // 'Cote D\'Ivoire',
		'HR', // 'Croatia',
		'CU', // 'Cuba',
		'CY', // 'Cyprus',
		'CZ', // 'Czech Republic',
		'DK', // 'Denmark',
		'DJ', // 'Djibouti',
		'DM', // 'Dominica',
		'DO', // 'Dominican Republic',
		'EC', // 'Ecuador',
		'EG', // 'Egypt',
		'SV', // 'El Salvador',
		'GQ', // 'Equatorial Guinea',
		'ER', // 'Eritrea',
		'EE', // 'Estonia',
		'ET', // 'Ethiopia',
		'FK', // 'Falkland Islands (Malvinas)',
		'FO', // 'Faroe Islands',
		'FJ', // 'Fiji',
		'FI', // 'Finland',
		'FR', // 'France',
		'GF', // 'French Guiana',
		'PF', // 'French Polynesia',
		'TF', // 'French Southern Territories',
		'GA', // 'Gabon',
		'GM', // 'Gambia',
		'GE', // 'Georgia',
		'DE', // 'Germany',
		'GH', // 'Ghana',
		'GI', // 'Gibraltar',
		'GR', // 'Greece',
		'GL', // 'Greenland',
		'GD', // 'Grenada',
		'GP', // 'Guadeloupe',
		'GU', // 'Guam',
		'GT', // 'Guatemala',
		'GG', // 'Guernsey',
		'GN', // 'Guinea',
		'GW', // 'Guinea-Bissau',
		'GY', // 'Guyana',
		'HT', // 'Haiti',
		'HM', // 'Heard Island & Mcdonald Islands',
		'VA', // 'Holy See (Vatican City State)',
		'HN', // 'Honduras',
		'HK', // 'Hong Kong',
		'HU', // 'Hungary',
		'IS', // 'Iceland',
		'IN', // 'India',
		'ID', // 'Indonesia',
		'IR', // 'Iran, Islamic Republic Of',
		'IQ', // 'Iraq',
		'IE', // 'Ireland',
		'IM', // 'Isle Of Man',
		'IL', // 'Israel',
		'IT', // 'Italy',
		'JM', // 'Jamaica',
		'JP', // 'Japan',
		'JE', // 'Jersey',
		'JO', // 'Jordan',
		'KZ', // 'Kazakhstan',
		'KE', // 'Kenya',
		'KI', // 'Kiribati',
		'KR', // 'Korea',
		'KW', // 'Kuwait',
		'KG', // 'Kyrgyzstan',
		'LA', // 'Lao People\'s Democratic Republic',
		'LV', // 'Latvia',
		'LB', // 'Lebanon',
		'LS', // 'Lesotho',
		'LR', // 'Liberia',
		'LY', // 'Libyan Arab Jamahiriya',
		'LI', // 'Liechtenstein',
		'LT', // 'Lithuania',
		'LU', // 'Luxembourg',
		'MO', // 'Macao',
		'MK', // 'Macedonia',
		'MG', // 'Madagascar',
		'MW', // 'Malawi',
		'MY', // 'Malaysia',
		'MV', // 'Maldives',
		'ML', // 'Mali',
		'MT', // 'Malta',
		'MH', // 'Marshall Islands',
		'MQ', // 'Martinique',
		'MR', // 'Mauritania',
		'MU', // 'Mauritius',
		'YT', // 'Mayotte',
		'MX', // 'Mexico',
		'FM', // 'Micronesia, Federated States Of',
		'MD', // 'Moldova',
		'MC', // 'Monaco',
		'MN', // 'Mongolia',
		'ME', // 'Montenegro',
		'MS', // 'Montserrat',
		'MA', // 'Morocco',
		'MZ', // 'Mozambique',
		'MM', // 'Myanmar',
		'NA', // 'Namibia',
		'NR', // 'Nauru',
		'NP', // 'Nepal',
		'NL', // 'Netherlands',
		'AN', // 'Netherlands Antilles',
		'NC', // 'New Caledonia',
		'NZ', // 'New Zealand',
		'NI', // 'Nicaragua',
		'NE', // 'Niger',
		'NG', // 'Nigeria',
		'NU', // 'Niue',
		'NF', // 'Norfolk Island',
		'MP', // 'Northern Mariana Islands',
		'NO', // 'Norway',
		'OM', // 'Oman',
		'PK', // 'Pakistan',
		'PW', // 'Palau',
		'PS', // 'Palestinian Territory, Occupied',
		'PA', // 'Panama',
		'PG', // 'Papua New Guinea',
		'PY', // 'Paraguay',
		'PE', // 'Peru',
		'PH', // 'Philippines',
		'PN', // 'Pitcairn',
		'PL', // 'Poland',
		'PT', // 'Portugal',
		'PR', // 'Puerto Rico',
		'QA', // 'Qatar',
		'RE', // 'Reunion',
		'RO', // 'Romania',
		'RU', // 'Russian Federation',
		'RW', // 'Rwanda',
		'BL', // 'Saint Barthelemy',
		'SH', // 'Saint Helena',
		'KN', // 'Saint Kitts And Nevis',
		'LC', // 'Saint Lucia',
		'MF', // 'Saint Martin',
		'PM', // 'Saint Pierre And Miquelon',
		'VC', // 'Saint Vincent And Grenadines',
		'WS', // 'Samoa',
		'SM', // 'San Marino',
		'ST', // 'Sao Tome And Principe',
		'SA', // 'Saudi Arabia',
		'SN', // 'Senegal',
		'RS', // 'Serbia',
		'SC', // 'Seychelles',
		'SL', // 'Sierra Leone',
		'SG', // 'Singapore',
		'SK', // 'Slovakia',
		'SI', // 'Slovenia',
		'SB', // 'Solomon Islands',
		'SO', // 'Somalia',
		'ZA', // 'South Africa',
		'GS', // 'South Georgia And Sandwich Isl.',
		'ES', // 'Spain',
		'LK', // 'Sri Lanka',
		'SD', // 'Sudan',
		'SR', // 'Suriname',
		'SJ', // 'Svalbard And Jan Mayen',
		'SZ', // 'Swaziland',
		'SE', // 'Sweden',
		'CH', // 'Switzerland',
		'SY', // 'Syrian Arab Republic',
		'TW', // 'Taiwan',
		'TJ', // 'Tajikistan',
		'TZ', // 'Tanzania',
		'TH', // 'Thailand',
		'TL', // 'Timor-Leste',
		'TG', // 'Togo',
		'TK', // 'Tokelau',
		'TO', // 'Tonga',
		'TT', // 'Trinidad And Tobago',
		'TN', // 'Tunisia',
		'TR', // 'Turkey',
		'TM', // 'Turkmenistan',
		'TC', // 'Turks And Caicos Islands',
		'TV', // 'Tuvalu',
		'UG', // 'Uganda',
		'UA', // 'Ukraine',
		'AE', // 'United Arab Emirates',
		'GB', // 'United Kingdom',
		'US', // 'United States',
		'UM', // 'United States Outlying Islands',
		'UY', // 'Uruguay',
		'UZ', // 'Uzbekistan',
		'VU', // 'Vanuatu',
		'VE', // 'Venezuela',
		'VN', // 'Viet Nam',
		'VG', // 'Virgin Islands, British',
		'VI', // 'Virgin Islands, U.S.',
		'WF', // 'Wallis And Futuna',
		'EH', // 'Western Sahara',
		'YE', // 'Yemen',
		'ZM', // 'Zambia',
		'ZW', // 'Zimbabwe',

		// Other valid ISRC country codes:
		'TC', // TuneCore (Digital Services) -- Allocation due to historical irregularity
		'CP', // International ISRC Agency (reserved for future use)
		'DG', // International ISRC Agency (ranges allocated as required)
		'ZZ', // International ISRC Agency individual allocations where reqd
		'CS', // Allocated to producers in former Serbia & Montenegro (prior to 2006)
		'YU' // Allocated to producers in former Yugoslavia (prior to 2003)
	);

	if(strlen($isrc) != 12) return(false);
    if(!preg_match("/[A-Z]{2}[A-Z0-9]{3}[0-9]{7}/", $isrc)) return(false);
	if(!in_array(substr($isrc, 0, 2), $country_codes)) return(false);

    return true;
}

function decode_date($str)
{
	if(trim($str) == false) return(false);
	else return(substr($str, 0, 4)."-".substr($str, 4, 2)."-".substr($str, 6, 2));
}

function encode_date($str)
{
	return( substr($str,0,4).substr($str,5,2).substr($str,8,2) );	
}

function decode_time($str)
{
	if(trim($str) == false) return(false);
	else return(substr($str, 0, 2).":".substr($str, 2, 2).":".substr($str, 4, 2));
}

function encode_time($t) //remove colon from time string
{
	return( substr($t,0,2).substr($t,3,2).substr($t,6,2) );	
}

function check_record(&$data, $mandatory = array(), $optional = array())
{
	$error = false;

	if(!isset($data['Record_Type'])) return("Error: No record type provided!");

	foreach($data as $key => $value)
	{
		$data[$key] = trim($value); // Remove whitespace

		if(!empty($value))
		{
			/* Validate Share encoding */
			if(stristr($key, "Share")) // This field contains a share value
			{
				$value *= 100; /* shift decimal place to the right */

/*				If the value came greater than 100% then assume it already has an implied decimal.
				A tolerance of plus or minus 00006 (.06%) is allowed on the total sum of shares, but not individual shares. */
				if($value > 10000) $value = abs($value/100);

				$data[$key] = $value;
			}

			/* Validate Date encoding */
			if(stristr($key, "Date")) // This field contains a Date value
				switch(strlen($data[$key]))
				{
					case 8: break; // already properly encoded
					case 10: $data[$key] = encode_date($value); break;// encode date
					default: $error = sprintf("%s is not in a valid format!", $key);
				}

			/* Validate Time encoding */
			if(stristr($key, "Time") || stristr($key, "Duration")) // This field contains a Time value
				switch(strlen($data[$key]))
				{
					case 6: break; // already properly encoded
					case 8: $data[$key] = encode_time($value); break;// encode time
					default: $error = sprintf("%s is not in a valid format!", $key);
				}
		}
	}

	foreach($mandatory as $m => $len)
	{
		if(!isset($data[$m])) $error = sprintf("%s is mandatory in record type %s!", $m, $data['Record_Type']);
		if(!is_numeric($data[$m])) $data[$m] = substr($data[$m], 0, $len);
		else if( $data[$m] > pow(10, $len)-1 ) $error = sprintf("%s exceeds maximum allowable value(%d)!", $m, pow(10, $len)-1);
	}

	foreach($optional as $o => $len)
	{
		if(!isset($data[$o])) $data[$o]='';
		if(!is_numeric($data[$o])) $data[$o] = substr($data[$o], 0, $len);
		else if( $data[$o] > pow(10, $len)-1 ) $error = sprintf("%s exceeds maximum allowable value (%d)!", $o, pow(10, $len)-1);
	}

	return($error);
}

function record_prefix($Record_Type, $Transaction_Sq, $Record_Sq)
{
	return(sprintf("%3s%08d%08d", $Record_Type, $Transaction_Sq, $Record_Sq));
}

function encode_cwr(&$msgs, $rec, $Transaction_Sq = false, $Record_Sq = false)
{
	if(!is_array($rec) || !isset($rec['Record_Type'])) return(false);

	$CWR_Version = CWR_Version;
	if(isset($rec['CWR_Version']))	$CWR_Version = $rec['CWR_Version'];
	$error = false;

	foreach($rec as $key => $value)
	{
		if(stristr($key, "IPI_Name")) // check IPI Name number values
		{
			$rec[$key] = sprintf("%011d", $rec[$key]); // Add leading zeros if missing
			if(!is_valid_ipi_name($rec[$key])) $rec[$key] = ' '; // replace invalid/unkown IPI Name Numbers and/or temp IDs with spaces
		}

		if(stristr($key, "IPI_Base")) // Check IPI Base Number values
		{
			if(!is_valid_ipi_base($rec[$key])) $rec[$key] = ''; // replace invalid/unkown IPI Base Numbers and temp IDs with spaces
		}
	}

	switch($rec['Record_Type'])
	{
		case 'HDR':
		{
			$error = check_record(
						$rec, 
						array('Sender_Type' => 2, 'Sender_ID' => 9, 'Sender_Name' => 45, 'Creation_Date' => 8, 'Creation_Time' => 6, 'Transmission_Date' => 8), 
						array('Character_Set' => 15));

			$data = sprintf("%3s%2s%09d%-45s%5s%8s%6s%8s%-15s", 
						$rec['Record_Type'],
						$rec['Sender_Type'], 
						$rec['Sender_ID'], 
						$rec['Sender_Name'], 
						EDI_Version, 
						$rec['Creation_Date'],
						$rec['Creation_Time'],
						$rec['Transmission_Date'],
						$rec['Character_Set']
					);
			if($CWR_Version > 2.1)
			{
				$CWR_Version = $rec['CWR_Version'];

				$data .= sprintf("%1.1f%03d%-30s%-30s",
							$rec['CWR_Version'],
							$rec['CWR_Revision'],
							$rec['Software_Package'],
							$rec['Software_Package_Version']
						);
			}
			break;
		}
		
		case 'GRH': // Group Header
		{
			$error = check_record($rec, array('Transaction_Type' => 3, 'Group_ID' => 4), array('Batch_Request' => 10, 'Submission_Distribution_type' => 2));
			
			$data = sprintf("%3s%3s%05d%05.2F%010d%2s",
						$rec['Record_Type'],
						$rec['Transaction_Type'],
						$rec['Group_ID'],
						$CWR_Version,
						$rec['Batch_Request'],
						$rec['Submission_Distribution_type']
					);
			break;
		}
		
		case 'GRT': // Group Trailer
		{
			$error = check_record($rec, array('Group_ID' => 5, 'Tx_Count' => 8, 'Rc_Count' => 8));
			
			$data = sprintf("%3s%05d%08d%08d%3s%10s",
						$rec['Record_Type'],
						$rec['Group_ID'],
						$rec['Tx_Count'],
						$rec['Rc_Count'],
						' ',	// Currency Indicator -- Not used by CWR
						' '		// Total monetary value -- Not used by CWR
					);
			break;
		}
		
		case 'TRL': // Trailer
		{
			$error = check_record($rec, array('Group_ID' => 5, 'Tx_Count' => 8, 'Rc_Count' => 8));

			$data = sprintf("%3s%05d%08d%08d",
						$rec['Record_Type'],
						$rec['Group_ID'],
						$rec['Tx_Count'],
						$rec['Rc_Count']
					);
			break;
		}

		case 'NWR': /* New Work Registration */
		case 'REV': /* Revised Registration */
		{
			$error = check_record($rec,
				/* mandatory fields */
				array(	'Work_Title' => 60,
						'Submitter_Work_ID' => 14,
						'Musical_Work_Distribution_Category' => 3, 
						'Duration' => 6,
						'Recorded_Indicator' => 1, 
						'Version_Type' => 3),
				/* optional fields - to be initialized if not present */
				array(	'Language_Code' => 2, 
						'ISWC' => 11, 
						'Copyright_Date' => 8, 
						'Copyright_Number' => 12,
						'Text_Music_Relationship' => 3,
						'Composite_Type' => 3,
						'Excerpt_Type' => 3,
						'Music_Arrangement' => 3,
						'Lyric_Adaptation' => 3,
						'Contact_Name' => 30,
						'Contact_ID' => 10,
						'CWR_Work_Type' => 2,
						'Grand_Rights_Ind' => 1,
						'Composite_Component_Count' => 3,
						'Date_of_publication_of_printed_edition' => 8,
						'Exceptional_Clause' => 1,
						'Opus_Number' => 25,
						'Catalogue_Number' => 25,
						'Priority_Flag' => 1));
			
			$data = sprintf("%19s%-60s%-2s%-14s%11s%8s%-12s%3s%6s%1s%3s%3s%3s%3s%3s%3s%30s%10s%2s%1s%03d%8s%1s%-25s%-25s%1s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Work_Title'],
						$rec['Language_Code'],
						$rec['Submitter_Work_ID'],
						$rec['ISWC'],
						$rec['Copyright_Date'],
						$rec['Copyright_Number'],
						$rec['Musical_Work_Distribution_Category'],
						$rec['Duration'],
						$rec['Recorded_Indicator'],
						$rec['Text_Music_Relationship'],
						$rec['Composite_Type'],
						$rec['Version_Type'],
						$rec['Excerpt_Type'],
						$rec['Music_Arrangement'],
						$rec['Lyric_Adaptation'],
						$rec['Contact_Name'],
						$rec['Contact_ID'],
						$rec['CWR_Work_Type'],
						$rec['Grand_Rights_Ind'],
						$rec['Composite_Component_Count'],
						$rec['Date_of_publication_of_printed_edition'],
						$rec['Exceptional_Clause'],
						$rec['Opus_Number'],
						$rec['Catalogue_Number'],
						$rec['Priority_Flag']);
			break;
		}
		
		case 'SPU': /* Publisher controlled by submitter */
		case 'OPU': /* Other publisher */
		{
			$error = check_record(
						$rec,
						array('Publisher_Sequence_Number' => 2),
						array(
							'Interested_Party_Number' => 9,
							'Publisher_Name' => 45,
							'Publisher_Unknown_Indicator' => 1,
							'Publisher_Type' => 2,
							'Tax_ID_Number' => 9,
							'Publisher_CAE_IPI_Name_Number' => 11,
							'Submitter_Agreement_Number' => 14,
							'PR_Society' => 3,
							'PR_Ownership_Share' => 5,
							'MR_Society' => 3,
							'MR_Ownership_Share' => 5,
							'SR_Society' => 3,
							'SR_Ownership_Share' => 5,
							'Special_Agreements_Indicator' => 1,
							'First_Recording_Refusal_Ind' => 1,
							'Publisher_IPI_Base_Number' => 13,
							'ISAC' => 14, //International Standard Agreement Code
							'Society_Assigned_Agreement_Number' => 14,
							'Agreement_Type' => 2,
							'USA_License_Ind' => 1 ));
			
			$data = sprintf("%19s%02d%09d%-45s%1s%-2s%09d%-11s%14s%03d%05d%03d%05d%03d%05d%1s%1s%1s%-13s%14s%14s%2s%1s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Publisher_Sequence_Number'],
						$rec['Interested_Party_Number'],
						$rec['Publisher_Name'],
						$rec['Publisher_Unknown_Indicator'],
						$rec['Publisher_Type'],
						$rec['Tax_ID_Number'],
						$rec['Publisher_CAE_IPI_Name_Number'],
						$rec['Submitter_Agreement_Number'],
						$rec['PR_Society'],
						$rec['PR_Ownership_Share'],
						$rec['MR_Society'],
						$rec['MR_Ownership_Share'],
						$rec['SR_Society'],
						$rec['SR_Ownership_Share'],
						$rec['Special_Agreements_Indicator'],
						$rec['First_Recording_Refusal_Ind'],
						' ', // Filler
						$rec['Publisher_IPI_Base_Number'],
						$rec['ISAC'],
						$rec['Society_Assigned_Agreement_Number'],
						$rec['Agreement_Type'],
						$rec['USA_License_Ind']
					);
			break;
		}

		case 'SPT': /* Publisher territory of control */
		case 'OPT': /* Publisher Non-Controlled Collection */
		{
			$error = check_record(
						$rec,
						array(
							'Interested_Party_Number' => 9,
							'Inclusion_Exclusion_Indicator' => 1,
							'TIS_Numeric_Code' => 4,
							'Sequence_Number' => 3),
						array(
							'PR_Collection_Share' => 5,
							'MR_Collection_Share' => 5,
							'SR_Collection_Share' => 5,
							'Shares_Change' => 1));

			$data = sprintf("%19s%09d%6s%05d%05d%05d%1s%04d%1s%03d",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Interested_Party_Number'],
						' ', // Constant (set to spaces)
						$rec['PR_Collection_Share'],
						$rec['MR_Collection_Share'],
						$rec['SR_Collection_Share'],
						$rec['Inclusion_Exclusion_Indicator'],
						$rec['TIS_Numeric_Code'],
						$rec['Shares_Change'],
						$rec['Sequence_Number']);
			break;
		}

		case 'SWR': /* Writer controlled by submitter */
		{
			$rec['Writer_Unknown_Indicator'] = " "; // Must be blank in SWR records

			$error = check_record($rec, 
						array(	'Interested_Party_Number' => 9,
								'Writer_Last_Name' => 45,
								'Writer_Designation_Code' => 2),
						array(	'Writer_First_Name' => 30,
								'Tax_ID_Number' => 9,
								'Writer_CAE_IPI_Name_Number' => 9,
								'PR_Society' => 3,
								'PR_Ownership_Share' => 5,
								'MR_Society' => 3,
								'MR_Ownership_Share' => 5,
								'SR_Society' => 3,
								'SR_Ownership_Share' => 5,
								'Reversionary_Indicator' => 1,
								'First_Recording_Refusal_Ind' => 1,
								'Work_For_Hire_Indicator' => 1,
								'Writer_IPI_Base_Number' => 13,
								'Personal_Number' => 12,
								'USA_License_Ind' => 1));
		}
		case 'OWR': /* Other writer */
		{
			if(empty($rec['Writer_Last_Name'])) $rec['Writer_Unknown_Indicator'] = "Y";
			$error = check_record($rec, 
						array(),
						array(	'Writer_Unknown_Indicator' => 1,
								'Interested_Party_Number' => 9,
								'Writer_Last_Name' => 45,
								'Writer_Designation_Code' => 2,
								'Writer_First_Name' => 30,
								'Tax_ID_Number' => 9,
								'Writer_CAE_IPI_Name_Number' => 9,
								'PR_Society' => 3,
								'PR_Ownership_Share' => 5,
								'MR_Society' => 3,
								'MR_Ownership_Share' => 5,
								'SR_Society' => 3,
								'SR_Ownership_Share' => 5,
								'Reversionary_Indicator' => 1,
								'First_Recording_Refusal_Ind' => 1,
								'Work_For_Hire_Indicator' => 1,
								'Writer_IPI_Base_Number' => 13,
								'Personal_Number' => 12,
								'USA_License_Ind' => 1));

//			$data = sprintf("%19s%09d%-45s%-30s%1s%-2s%09s%011d%03d%05d%03d%05d%03d%05d%1s%1s%1s%1s%-13s%012d%1s",
			$data = sprintf("%19s%9s%-45s%-30s%1s%-2s%09s%-11s%03d%05d%03d%05d%03d%05d%1s%1s%1s%1s%-13s%012d%1s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Interested_Party_Number'], // submitter's number -- not the same as IPI
						$rec['Writer_Last_Name'],
						$rec['Writer_First_Name'],
						$rec['Writer_Unknown_Indicator'],
						$rec['Writer_Designation_Code'],
						$rec['Tax_ID_Number'],
						$rec['Writer_CAE_IPI_Name_Number'],
						$rec['PR_Society'],
						$rec['PR_Ownership_Share'],
						$rec['MR_Society'],
						$rec['MR_Ownership_Share'],
						$rec['SR_Society'],
						$rec['SR_Ownership_Share'],
						$rec['Reversionary_Indicator'],
						$rec['First_Recording_Refusal_Ind'],
						$rec['Work_For_Hire_Indicator'],
						' ', //Filler
						$rec['Writer_IPI_Base_Number'],
						$rec['Personal_Number'],
						$rec['USA_License_Ind']);
			break;
		}

		case 'SWT': /* Writer territory of control */
		case 'OWT': /* CWR v2.2: Other Writer Collection */
		{
			$error = check_record(
						$rec,
						array(
							'Interested_Party_Number' => 9,
							'Inclusion_Exclusion_Indicator' => 1,
							'TIS_Numeric_Code' => 4,
							'Sequence_Number' => 3),
						array(
							'PR_Collection_Share' => 5,
							'MR_Collection_Share' => 5,
							'SR_Collection_Share' => 5,
							'Shares_Change' => 1));

			$data = sprintf("%19s%09d%05d%05d%05d%1s%04d%1s%03d",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Interested_Party_Number'],
						$rec['PR_Collection_Share'],
						$rec['MR_Collection_Share'],
						$rec['SR_Collection_Share'],
						$rec['Inclusion_Exclusion_Indicator'],
						$rec['TIS_Numeric_Code'],
						$rec['Shares_Change'],
						$rec['Sequence_Number']);
			break;
		}

		case 'PWR': /* Publisher for writer */
		{
			$error = check_record(
						$rec,
						array('Publisher_IP_Number' => 9, 'Publisher_Name' => 45, 'Writer_IP_Number' => 9),
						array('Submitter_Agreement_Number' => 14, 'Society_Assigned_Agreement_Number' => 14));
			$data = sprintf("%19s%09d%-45s%-14s%-14s%09d",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Publisher_IP_Number'],
						$rec['Publisher_Name'],
						$rec['Submitter_Agreement_Number'],
						$rec['Society_Assigned_Agreement_Number'],
						$rec['Writer_IP_Number']);

			// Add a publisher sequence number if it has been supplied
			if(isset($rec['Publisher_Sequence_Number']))	$data .= sprintf("%02d", $rec['Publisher_Sequence_Number']);

			break;
		}

		case 'ALT': /* Alternate Title */
		{
			$error = check_record($rec, array('Alternate_Title' => 60, 'Title_Type' => 2), array('Language_Code' => 2));
			
			$data = sprintf("%19s%-60s%-2s%-2s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Alternate_Title'],
						$rec['Title_Type'],
						$rec['Language_Code']);			
			break;
		}

		case 'PER': /* Performing artist */
		{
			$error = check_record(
						$rec, 
						array(	'Performing_Artist_Last_Name' => 45),
						array(	'Performing_Artist_First_Name' => 30,
								'Performing_Artist_CAE_IPI_Name_Number' => 11,
								'Performing_Artist_IPI_Base_Number' => 13));

			$data = sprintf("%19s%-45s%-30s%-11s%-13s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Performing_Artist_Last_Name'],
						$rec['Performing_Artist_First_Name'],
						$rec['Performing_Artist_CAE_IPI_Name_Number'],
						$rec['Performing_Artist_IPI_Base_Number']);
			break;
		}

		case 'COM': /* Component */
		case 'EWT': /* Entire Work Title for Excerpts */
		case 'VER': /* Original Work Title for Versions */
		{
			$error = check_record(
						$rec,
						array('Title' => 60),
						array('ISWC' => 11,
						'Language_Code' => 2,
						'Writer_1_Last_Name' => 45,
						'Writer_1_First_Name' => 30,
						'Source' => 60,
						'Writer_1_IPI_Name_Num' => 11,
						'Writer_1_IPI_Base_Number' => 13,
						'Writer_2_Last_Name' => 45,
						'Writer_2_First_Name' => 30,
						'Writer_2_IPI_Name_Num' => 11,
						'Writer_2_IPI_Base_Number' => 13,
						'Submitter_Work_ID' => 14));

			$data = sprintf("%-60s%-11s%2s%-45s-30s-60s%-11s%-13s%-45s%-30s%-11s%-13s%-14s",
						$rec['Original_Work_Title'],
						$rec['ISWC_of_Original_Work'],
						$rec['Language_Code'],
						$rec['Writer_1_Last_Name'],
						$rec['Writer_1_First_Name'],
						$rec['Source'],
						// Version 2.0 fields:
						$rec['Writer_1_IPI_Name_Num'],
						$rec['Writer_1_IPI_Base_Number'],
						$rec['Writer_2_Last_Name'],
						$rec['Writer_2_First_Name'],
						$rec['Writer_2_IPI_Name_Num'],
						$rec['Writer_2_IPI_Base_Number'],
						$rec['Submitter_Work_ID']);
			break;
		}

		case 'REC': /* Recording Detail */
		{
			$error = check_record($rec, array(),
						array('First_Release_Date' => 8,
							'First_Release_Duration' => 6,
							'First_Album_Title' => 60,
							'First_Album_Label' => 60,
							'First_Release_Catalog_Number' => 18,
							'EAN' => 13,
							'ISRC' => 12,
							'Recording_Format' => 1,
							'Recording_Technique' => 1,
							'Media_Type' => 3));

			if(is_valid_isrc($rec['ISRC'])) $rec['ISRC_Validity'] = 'Y';
			else $rec['ISRC_Validity'] = 'N';

			if(strlen($rec['EAN']) == 12) $rec['EAN'] = '0'.$rec['EAN']; // Convert 12-digit UPC to 13-digit EAN
			if(!verifycheckdigit($rec['EAN'])) $rec['EAN'] = ''; // Replace invalid EANs with spaces

			$data = sprintf("%19s%8s%-60s%6s%5s%-60s%-60s%-18s%-13s%-12s%1s%1s%-3s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['First_Release_Date'],
						' ', // Constant
						$rec['First_Release_Duration'],
						' ', // Constant
						$rec['First_Album_Title'],
						$rec['First_Album_Label'],
						$rec['First_Release_Catalog_Number'],
						$rec['EAN'],
						$rec['ISRC'],
						$rec['Recording_Format'],		/* 'A' or 'V': Audio or video */
						$rec['Recording_Technique'],	/* 'A', 'D' or 'U' : Analogue, Digital or Unknown */
						$rec['Media_Type']);

			/* CWR v2.2+ fields: */
			if($CWR_Version > 2.1)
			{
				$data .= sprintf("%-60s%-60s%-60s%-60s%-20s%-14s",
						$rec['Recording_Title'],
						$rec['Version_Title'],
						$rec['Display_Artist'],
						$rec['Record_Label'],
						$rec['ISRC_Validity'],
						$rec['Submitter_Recording_Identifier']);
			}

			break;
		}

		case 'ORN': /* Work Origin */
		{
			$error = check_record($rec, 
						array('Intended_Purpose' => 3),
						array(
							'Production_Title' => 60,
							'CD_Identifier' => 15,
							'Cut_Number' => 4,
							'Library' => 60,
							'Production_Num' => 12,
							'Episode_Title' => 60,
							'Episode_Num' => 20,
							'Year_of_Production' => 4,
							'AVI_Society_Code' => 3,
							'Audio_Visual_Number' => 15,
							'VISAN_ISAN' => 12,
							'VISAN_Episode' => 4,
							'VISAN_Check_Digit_1' => 1,
							'VISAN_Version' => 8,
							'VISAN_Check_Digit_2' => 1,
							'EIDR' => 21,
							'EIDR_Root_Number' => 20,
							'EIDR_Check_Digit' => 1
						));

			$data = sprintf("%3s%-60s%-15s%04d%-60s%1s%25s%-12s%-60s%-20s%4d%3s%-15s",
				$rec['Intended_Purpose'],
				$rec['Production_Title'],
				$rec['CD_Identifier'],
				$rec['Cut_Number'],

				/* v2.1 fields: */
				$rec['Library'],
				$rec['BLTVR'],
				' ', // Filler
				$rec['Production_Num'],
				$rec['Episode_Title'],
				$rec['Episode_Num'],
				$rec['Year_of_Production'],
				$rec['AVI_Society_Code'],
				$rec['Audio_Visual_Number']);
			
				/* v2.2 fields */
			if($CWR_Version > 2.1)
				$data .= sprintf("%-12s%-4s%1s%-8s%1s%-21s%-20s%1s",
					$rec['VISAN_ISAN'],
					$rec['VISAN_Episode'],
					$rec['VISAN_Check_Digit_1'],
					$rec['VISAN_Version'],
					$rec['VISAN_Check_Digit_2'],
					$rec['EIDR'],
					$rec['EIDR_Root_Number'],
					$rec['EIDR_Check_Digit']);
			break;
		}

		case 'ARI': /* Additional Related Information */
		{
			$error = check_record($rec, 
						array('Society_Code' => 3, 'Type_of_Right' =>3),
						array('Work_Num' => 14, 'Subject_Code' => 2, 'Note' => 160));

			$data = sprintf("%19s%03d%-14s%3s%2s%-160s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Society_Code'],
						$rec['Work_Num'],
						$rec['Type_of_Right'],
						$rec['Subject_Code'],
						$rec['Note']);
			break;
		}

		case 'XRF': /* Work ID Cross Reference */
		{
			$error = check_record($rec, 
						array('Organisation_Code' => 3, 'Identifier' => 14, 'Identifier_Type' => 1, 'Validity' =>1),
						array());

			$data = sprintf("%19s%03d%-14s%1s%1s",
						record_prefix($rec['Record_Type'], $Transaction_Sq, $Record_Sq),
						$rec['Organisation_Code'],	/* Number assigned to the Organisation (e.g. Society, publisher, DSP etc...) which generated the Work ID is. These values reside in the Organisation Code Table (including the Sender ID Code Table, the CWR Submitter ID Codes Table, the Society Code Table, ISWC and ISRC). Note: Do not use “000”or “099”. */
						$rec['Identifier'],			/* An identifier that relates to this work Transaction.  */
						$rec['Identifier_Type'],	/* The type of identifier (“W” for Work, “R” for Recording, “P” for Product, “V“ for Video) */
						$rec['Validity']);			/* Indicates whether the Identifier is valid or not:“Y” is valid, “U” the link is invalid, “N” the identifier is invalid */
			break;
		}

		default:
		{
			$msgs[] = sprintf("(Txn %d, Seq %d): Unsupported record type '%s'.\n", $Transaction_Sq, $Record_Sq, $rec['Record_Type']);
			$data = "";
		}
	}
	
	if($error) $msgs[] = sprintf("%s (Txn %d, Seq %d): %s", $rec['Record_Type'], $Transaction_Sq, $Record_Sq, $error);
	if(!empty($data)) $data .= "\r\n"; /* Added CR \r at the request of CMRRA */

	return($data);
}


function decode_cwr($rec)
{
	$data = array();

	$rec = str_pad($rec, 365); // Pad the records to handle societies that trim their records (yes, I'm looking at you BMI).

	switch(substr($rec, 0, 3))
	{
		case 'HDR':
		{
			$data = unpack(
						"A3Record_Type".
						"/A2Sender_Type".
						"/A9Sender_ID".
						"/A45Sender_Name".
						"/A5EDI_Version".
						"/A8Creation_Date".
						"/A6Creation_Time".
						"/A8Transmission_Date".

						/* CWR 2.1 field */
						"/A15Character_Set".			

						/* CWR 2.2 fields: */
						"/A3CWR_Version".
						"/A3CWR_Revision".
						"/A30Software_Package".
						"/A30Software_Package_Version",
					 $rec);
			break;
		}
		
		case 'GRH': // Group Header
		{			
			$data = unpack(
						"A3Record_Type".
						"/A3Transaction_Type".
						"/A5Group_ID".
						"/A5CWR_Version".
						"/A10Batch_Request".
						"/A2Submission_Distribution_Type", // Should be blank - not used for CWR
					$rec);
			$data['CWR_Version'] = floatval($data['CWR_Version']);
//			$data['Batch_Request'] = substr($rec, 16, 10);

			break;
		}
		
		case 'GRT': // Group Trailer
		{
			$data = unpack(
						"A3Record_Type".
						"/A5Group_ID".
						"/A8Tx_Count".
						"/A8Rc_Count".

						"/A3Currency_Indicator". // Version 1.10 fields – Not used for CWR 
						"/A10Total_monetary_value", // Version 1.10 fields – Not used for CWR 
					$rec);					
			break;
		}
		
		case 'TRL': // Trailer
		{
			$data = unpack("A3Record_Type/A5Group_ID/A8Tx_Count/A8Rc_Count", $rec);
			break;
		}

		case 'NWR': /* New Work Registration */
		case 'REV': /* Revised Registration */
		case 'ISW': /* Notification of ISWC */
		case 'EXC': /* Existing Work in Conflict */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A60Work_Title".
						"/A2Language_Code".
						"/A14Submitter_Work_ID".
						"/A11ISWC".
						"/A8Copyright_Date".
						"/A12Copyright_Number".
						"/A3Musical_Work_Distribution_Category".
						"/A6Duration".
						"/A1Recorded_Indicator".
						"/A3Text_Music_Relationship".
						"/A3Composite_Type".
						"/A3Version_Type".
						"/A3Excerpt_Type".
						"/A3Music_Arrangement".
						"/A3Lyric_Adaptation".
						"/A30Contact_Name".
						"/A10Contact_ID".
						"/A2CWR_Work_Type".
						"/A1Grand_Rights_Ind".
						"/A3Composite_Component_Count".

						/* GEMA specific fields: */
						"/A8Date_of_publication_of_printed_edition".	
						"/A1Exceptional_Clause".

						/* Version 2.0 fields: */
						"/A25Opus_Number".
						"/A25Catalogue_Number".

						/* Version 2.1 field: */
						"/A1Priority_Flag",
					$rec);
			break;
		}
		
		case 'SPU': /* Publisher controlled by submitter */
		case 'OPU': /* Other publisher */
		{

			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A2Publisher_Sequence_Number".
						"/A9Interested_Party_Number".
						"/A45Publisher_Name".
						"/A1Publisher_Unknown_Indicator".
						"/A2Publisher_Type".
						"/A9Tax_ID_Number".
						"/A11Publisher_CAE_IPI_Name_Number".
						"/A14Submitter_Agreement_Number".
						"/A3PR_Society".
						"/A5PR_Ownership_Share".
						"/A3MR_Society".
						"/A5MR_Ownership_Share".
						"/A3SR_Society".
						"/A5SR_Ownership_Share".
						"/A1Special_Agreements_Indicator".
						"/A1First_Recording_Refusal_Ind".
						"/A1Filler".
						"/A13Publisher_IPI_Base_Number".
						"/A14International_Standard_Agreement_Code".
						"/A14Society_Assigned_Agreement_Number".
						"/A2Agreement_Type".
						"/A1USA_License_Ind",
					$rec);
			break;
		}

		case 'SPT': /* Publisher territory of control */
		case 'OPT': /* Publisher Non-Controlled Collection */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A9Interested_Party_Number".
						"/A6Constant".
						"/A5PR_Collection_Share".
						"/A5MR_Collection_Share".
						"/A5SR_Collection_Share".

						// CWR 2.0 fields:
						"/A1Inclusion_Exclusion_Indicator".
						"/A4TIS_Numeric_Code".
						"/A1Shares_Change".

						// CWR 2.1 field:
						"/A3Sequence_Number",
					$rec);
			break;
		}

		case 'SWR': /* Writer controlled by submitter */
		case 'OWR': /* Other writer */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A9Interested_Party_Number".
						"/A45Writer_Last_Name".
						"/A30Writer_First_Name".
						"/A1Writer_Unknown_Indicator".
						"/A2Writer_Designation_Code".
						"/A9Tax_ID_Number".
						"/A11Writer_CAE_IPI_Name_Number".
						"/A3PR_Society".
						"/A5PR_Ownership_Share".
						"/A3MR_Society".
						"/A5MR_Ownership_Share".
						"/A3SR_Society".
						"/A5SR_Ownership_Share".
						"/A1Reversionary_Indicator".
						"/A1First_Recording_Refusal_Ind".
						"/A1Work_For_Hire_Indicator".
						"/A1Filler".

						// CWR 2.0 fields:
						"/A13Writer_IPI_Base_Number".
						"/A12Personal_Number".

						// CWR 2.1 field:
						"/A1USA_License_Ind",
					$rec);
			break;
		}

		case 'SWT': /* Writer territory of control */
		case 'OWT': /* Other Writer Collection */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A9Interested_Party_Number".
						"/A5PR_Collection_Share".
						"/A5MR_Collection_Share".
						"/A5SR_Collection_Share".
						"/A1Inclusion_Exclusion_Indicator".
						"/A4TIS_Numeric_Code".
						"/A1Shares_Change".
						"/A3Sequence_Number",
					$rec);
			break;
		}

		case 'PWR': /* Publisher for writer */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A9Publisher_IP_Number".
						"/A45Publisher_Name".
						"/A14Submitter_Agreement_Number".
						"/A14Society_Assigned_Agreement_Number".

						// Version 2.1 Fields:
						"/A9Writer_IP_Number".
						
						// Version 2.2 Fields:
						"/A2Publisher_Sequence_Number",
					$rec);
			break;
		}

		case 'ALT': /* Alternate Title */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A60Alternate_Title".
						"/A2Title_Type".
						"/A2Language_Code",
					$rec);
			break;
		}

		case 'COM': /* Component */
		case 'EWT': /* Entire Work Title for Excerpts */
		case 'VER': /* Original Work Title for Versions */
		{
			$rec = str_pad($rec, 365); // Pad the records to handle societies that trim their records (e.g. BMI).
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A60Title".
						"/A11ISWC".
						"/A2Language_Code".
						"/A45Writer_1_Last_Name".
						"/A30Writer_1_First_Name".
						"/A60Source".
						"/A11Writer_1_IPI_Name_Num".
						"/A13Writer_1_IPI_Base_Number".
						"/A45Writer_2_Last_Name".
						"/A30Writer_2_First_Name".
						"/A11Writer_2_IPI_Name_Num".
						"/A13Writer_2_IPI_Base_Number".
						"/A14Submitter_Work_ID",
					$rec);
			break;
		}

		case 'PER': /* Performing artist */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A45Performing_Artist_Last_Name".
						"/A30Performing_Artist_First_Name".
						"/A11Performing_Artist_CAE_IPI_Name_Number".
						"/A13Performing_Artist_IPI_Base_Number",
					$rec);
			break;
		}

		case 'REC': /* Recording Detail */
		{
			$rec = str_pad($rec, 541); // Pad the records to handle societies that trim their records (e.g. BMI).
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A8First_Release_Date".
						"/A60Constant".
						"/A6First_Release_Duration".
						"/A5Constant".
						"/A60First_Album_Title".
						"/A60First_Album_Label".
						"/A18First_Release_Catalog_Number".
						"/A13EAN".
						"/A12ISRC".
						"/A1Recording_Format".
						"/A1Recording_Technique".

						// CWR 2.1 field:
						"/A3Media_Type".

						// CWR 2.2 fields:
						"/A60Recording_Title".
						"/A60Version_Title".
						"/A60Display_Artist".
						"/A60Record_Label".
						"/A20ISRC_Validity". 					// If an ISRC is supplied, Indicates that the validity of the ISRC:“Y” is valid, “U” the link is invalid, “N” the ISRC is invalid
						"/A14Submitter_Recording_Identifier",
					$rec);
			break;
		}

		case 'ACK': /* Acknowledgement */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A8Creation_Date".
						"/A6Creation_Time".
						"/A5Original_Group_ID".
						"/A8Original_Transaction_Sequence_Num".
						"/A3Original_Transaction_Type".
						"/A60Creation_Title".
						"/A20Submitter_Creation_Num".
						"/A20Recipient_Creation_Num".
						"/A8Processing_Date".
						"/A2Transaction_Status",
					$rec);
			break;
		}

		case 'AGR': /* Agreement supporting Work Registration */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A14Submitter_Agreement_Number".
						"/A14ISAC".
						"/A2Agreement_Type".
						"/A8Agreement_Start_Date".
						"/A8Agreement_End_Date".
						"/A8Retention_End_Date".
						"/A1Prior_Royalty_Status".
						"/A8Prior_Royalty_Start_Date".
						"/A1Post_term_Collection_Status".
						"/A8Post_term_Collection_End_Date".
						"/A8Date_of_Signature_of_Agreement".
						"/A5Number_of_Works".
						"/A1Sales_Manufacture_Clause".
						"/A1Shares_Change".
						"/A1Advance_Given".
						"/Society_assigned_Agreement_Num",
					$rec);
			break;
		}

		case 'MSG': /* Message */
		{
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A1Message_Type".
						"/A8Original_Record_Sequence_Num".
						"/A3Original_Record_Type".
						"/A1Message_Level".
						"/A3Validation_Number".
						"/A150Message_Text",
					$rec);
			break;
		}

		case 'ARI': /* Additional Related Information */
		{
			$rec = str_pad($rec, 202); // Pad the records to handle societies that trim their records (e.g. BMI).
			$data = unpack(
						"A3Record_Type/A8Tx_Count/A8Rc_Count".
						"/A3Society_Code".
						"/A14Work_Num".
						"/A3Type_of_Right".
						"/A2Subject_Code".
						"/A160Note",
					$rec);
			break;
		}

		case 'XRF': /* Work ID Cross Reference */
		{
			$rec = str_pad($rec, 39); // Pad the records to handle societies that trim their records (e.g. BMI).
			$data = unpack(
				"A3Record_Type/A8Tx_Count/A8Rc_Count".
				"/A3Organisation_Code".
				"/A14Identifier".
				"/A1Identifier_Type".	// The type of identifier (“W” for Work, “R” for Recording, “P” for Product, “V“ for Video)
				"/A1Validity",			// Indicates whether the Identifier is valid or not:“Y” is valid, “U” the link is invalid, “N” the identifier is invalid
			$rec);
			break;
		}
	}

	/* Clean up CWR data: */
	if(!is_array($data)) $msgs[] = sprintf("ERROR:  CWR entry not decoded for type '%s', strlen = %d\nRecord = %s\nDecode String = %s\n\n", substr($rec, 0, 3), strlen($rec), $rec, $data);
	else
	foreach($data as $key => $value)
	{
		$data[$key] = trim($value); // Remove whitespace

		if(stristr($key, "Share")) // This field contains a share value
			$data[$key] = intval($value) / 100; /* shift decimal place to the left */

		if(stristr($key, "Date")) // This field contains a Date value
			$data[$key] = decode_date($value);

		if(stristr($key, "Time") || stristr($key, "Duration")) // This field contains a Time value
			$data[$key] = decode_time($value);
	}

//	if(!isset($data['Tx_Count'])) print_r($data); // for debugging undecoded records

	if(isset($data['Tx_Count'])) $data['Tx_Count'] = intval($data['Tx_Count']);
	if(isset($data['Rc_Count'])) $data['Rc_Count'] = intval($data['Rc_Count']);
	
	return($data);
}
?>
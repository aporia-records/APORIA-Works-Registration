# APORIA Works Registration
Copyright © 2016, 2017 Gord Dimitrieff <gord@aporia-records.com>

**Current Version: 1.46**

APORIA Works Registration is a PHP library for reading, writing and manipulating CISAC Common Works Registration (CWR) v2.1R7 and v2.2 files. Aporia has been using this library in a production environment as a method of sending registrations to MusicMark and CMRRA directly from a Filemaker database since late 2016.

You can also find the most recent version posted here:  
http://aporia-records.com/awr/

The APORIA Works Registration library is distributed under the terms of the GNU General Public License.  You can redistribute it and/or modify it under the terms of the GNU GPL as published by the Free Software Foundation.  Visit http://www.gnu.org/licenses/ for more information.

**Please contact me at the above email address if you would like to license this work on terms that differ from the GPL.**

## Limitations/Caveats:
* Agreement (AGR, TER, IPA) record types are not currently implemented.
* Non-Roman character record types (NPA, NPN, NWN, NAT, NPR, NET, NCT, NVT, NOW) are not currently implemented.
* CWR Light (i.e. the SOC transaction type) is not currently implemented.
* The library has been designed to handle CWR 2.2, but I have not been able to test this, since none of the societies I belong to have implemented CWR 2.2 yet.
* As Aporia does not represent any works in the 'serious' distribution category, record types COM, INS, and IND have not been thoroughly tested.

## CWR Technical Background
Before you attempt to use this library, you will need to have a solid understanding of the CWR file format.  It is a flat text file, where the first 3 characters of each line specify what type of data is contained in that line.  Before making use of this library, you should read both the Functional Specifications and the CWR User Manual:

Functional Specifications for CWR 2.2:  
http://members.cisac.org/CisacPortal/consulterDocument.do?id=29541

CWR User Manual:  
http://members.cisac.org/CisacPortal/consulterDocument.do?id=22272

## Library Documentation
[See the wiki for documentation.](https://github.com/aporia-records/APORIA-Works-Registration/wiki)

## Design objectives/considerations:
#### CWR Validity:
A major goal of this library is to only generate valid CWR files.  Not all possible errors are currently detected, but the most common ones are.

#### NWR vs REV transactions:
A design goal is to automatically determine whether or not a transaction is a new registration or a revised registration.  This is accomplished by pre-loading all existing society registration IDs, and categorizing a transaction as a REV if it has already been submitted to the receiving society.  Consequently, tracking and supplying these IDs is mandatory.  This has the added benefit of also allowing the support of CWR 2.2's XRF record type.

#### Master Recording Metadata:
The library currently supports full matching of ISWCs to ISRCs, and will eventually be able to facilitate master-side metadata registration files (e.g. for PPL or SoundExchange).

#### Why PHP? 
I chose PHP because at one point in time I was limited to the SmartPill PHP plugin for Filemaker.  It is not the most memory efficient language, but the automatic array/dictionary hashing does make this type of project relatively easy.  The biggest use of memory is storing the TIS territory data as a hierarchal data tree.  If this becomes a major problem in the future, it could be converted to a nested set and loaded from a database, which would probably save memory.

In the meantime, if you find you have memory issues, upgrading to PHP 7+ might help, as it is approximately 30% more memory efficient. 

Having said all this, I have not encountered any major memory problems yet, and I have been preparing CWR files with hundreds of transactions.

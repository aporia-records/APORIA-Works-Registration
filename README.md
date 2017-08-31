# APORIA Works Registration
Copyright © 2016, 2017 Gord Dimitrieff <gord@aporia-records.com>

APORIA Works Registration is a PHP library for reading, writing and manipulating CISAC Common Works Registration (CWR) v2.1R7 and v2.2 files. Aporia has been using this library in a production environment as a method of sending registrations to MusicMark and CMRRA directly from a Filemaker database since late 2016.

The APORIA Works Registration library is distributed under the terms of the GNU General Public License.  You can redistribute it and/or modify it under the terms of the GNU GPL as published by the Free Software Foundation.  Visit http://www.gnu.org/licenses/ for more information.

**Please contact me at the above email address if you would like to license this work on terms that differ from the GPL.**

## Limitations/Caveats:
* Agreement (AGR, TER, IPA) record types are not currently implemented.
* Non-Roman character record types (NPA, NPN, NWN, NAT, NPR, NET, NCT, NVT, NOW) are not currently implemented.
* CWR Light (i.e. the SOC transaction type) is not currently implemented.
* The library has been designed to handle CWR 2.2, but I have not been able to test this, since none of the societies I belong to have implemented CWR 2.2 yet.
* As Aporia does not represent any works in the 'serious' distribution category, record types COM, INS, and IND have not been thoroughly tested.

OpenSRS Domain Verification Tool
================================

The purpose in building this tool is to provide resellers with a quick tool that can be provided
to their support staff for the new Domain Verification Procedure without requiring full access 
to the OpenSRS Reseller Web Interface (RWI).

This tool is based off the official OpenSRS PHP Toolkit found here:

https://github.com/OpenSRS/osrs-toolkit-php


To-Do
-----

- Split things off into individual files
- Improve CSS (it's bad right now, I know)
- Look into AJAX-ifying things

Requirements
------------

This tool requires the following:

- PHP 5 (5.3+ recommended)
- OpenSSL
- PEAR: http://pear.php.net/
- getmypid() enabled
- cURL: required for OMA

This tool passes all data in JSON, so your PHP install must have json_encode
and json_decode.  These functions are standard in php 5.3+.  If an earlier 
version of PHP 5 is being used, the php-json libraries at 
http://pecl.php.net/package/json will be required. 


Getting Started
---------------
1. Install this tool to the desired location
2. In opensrs directory, rename/copy the openSRS_config.php.template to openSRS_config.php
3. Open openSRS_config.php in a text editor and fill in your reseller details

That's it!

Optional
---------
- Rename verify.php (no dependencies based on the name of this file)


Setting up openSRS_config.php
------------------------------

__OSRS_HOST__
> * LIVE: rr-n1-tor.opensrs.net
> * TEST: horizon.opensrs.net  

__CRYPT_TYPE__
> OpenSRS default encryption type 
> * ssl (default)
> * sslv3
> * tls
> 
> Please note that OpenSSL ver 1.0.1e does not work with "ssl" encryption type

__OSRS_USERNAME__
> OpenSRS Reseller Username

__OSRS_KEY__
> OpenSRS Private Key
> To generate a key, login into the RWI by going to the following address,
>   * LIVE:  https://rr-n1-tor.opensrs.net/resellers/
>   * TEST:  https://horizon.opensrs.net/resellers/
>
> In RWI, Profile Management > Generate New Private Key

__OSRS_DEBUG__
> WHen set to 1, the Toolkit will spit out the raw XML request/response.
     

Generating Private Key
------------------------

To generate a key, login into the RWI by going to the following address:

    LIVE:  https://rr-n1-tor.opensrs.net/resellers/
    TEST:  https://horizon.opensrs.net/resellers/

NOTE:  The TEST system is rather different than the LIVE system.  Therefore,
during testing, the results that you receive are VERY different than you would
get on the LIVE system.  Both Personal Names and Domain searching will yield
different results.  

Once authenticated, scroll down to the bottom of the page where the heading
shows "Profile Management".  Here, there is a link called "Generate New
Private Key".  If you are sure you wish to continue, click on the 'OK' button
on the warning window.  This window ensure that you wish to generate a new
key.  On refresh, you will see a key similiar to this structure:

a2e308f4df69c969de9ada09c39bb43d0e4d0a88ce83fd2c0a193328d16e8bf5080ee7e0ef388fd8aa0dfb42d33dcd02d3de321c9af1f06b

This is essentially the authentication key that you'll be using to working
with the API.  It's essential to keep this key to yourself!


Whitelisting IP address
-----------------------

Once you've generated your key (as above), you'll also need to whitelist your
IP address.  If this is not done, an error will be received and continuing
with the API will not possible.  To whitelist your IP address, login to either
LIVE or TEST environment in the RWI (like in section 2.1.0).  Scroll down to
the bottom of the screen under the "Profile Management" section, there is a
link called "Add IPs for Script/API Access".  Once you've clicked on that, you
can "Add New Rule" by typing in the subnet for where your application will be
hosted.  A total of 5 addresses can be added by default. 

IMPORTANT NOTE:  Adding a new IP address can take up to 15 minutes to
propagate into our system.   
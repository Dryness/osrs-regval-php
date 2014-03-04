<?php
session_start();

$timeout = 300;  //seconds

if (isset($_SESSION['timeout'])) {
	$inactive = time() - (int)$_SESSION['timeout'];
	if ($inactive > $timeout) {
	session_unset();
	}
}
$_SESSION['timeout'] = time();

// Initiating variables
$tf = "json";  // Language we expect the results to be in
$formFormat = "json";
$domainEmpty = true;  // Is the domain search field empty?
$domainError = "<p class=\"info\">Please enter a domain name.</p>";  // Error to display when the domain field is empty
$status = ""; // These are the response messages
$json = "";  // This is the server response in json format (poor choice of name, I know)
$update = false; // Hiding form fields if we've retrieved domain owner information

// required functions
require_once dirname(__FILE__) . "/opensrs/spyc.php";
require_once dirname(__FILE__) . "/opensrs/openSRS_loader.php";

// Need a recurisve array search tool
function array_key_exists_r($needle, $haystack)
{
    $result = array_key_exists($needle, $haystack);
    if ($result) return $result;
    foreach ($haystack as $v) {
        if (is_array($v)) {
            $result = array_key_exists_r($needle, $v);
        }
        if ($result) return $result;
    }
    return $result;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	if (empty($_POST['domain'])) {
		$domainEmpty = true;
		$update = false;
	}
}

function is_valid_domain_name($domain_name)
{
    if (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ) {
    	return true;
    }
    else {
    	return false;
    }
}

if (isset($_POST['domain'])) {
	if (is_valid_domain_name($_POST['domain']) == false) {
		$domainEmpty = true;
		$update = false;
	}
}

//	 Checking Verification status
if (isset($_POST["lookupVerification"])) {

	$domain = trim($_POST['domain']);
	$_SESSION['domain'] = $domain;

	if (empty($_SESSION['domain'])) {
		$domainEmpty = true;
	}

	else {
		$domainEmpty = false;
// 		put the data in a formatted array
		$callstring = "";
		$callArray = array (

// 			the "func => " business actually ends up going through the loader (included above)
// 			and calls the respective PHP file from /opensrs/ 
			"func" => "lookupGetVerificationStatus",
			"data" => array (
				"domain" => $_SESSION["domain"]
			)
		);

		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);

//	 	This takes the raw json response and decodes it to a PHP array
		$json = json_decode($osrsHandler->resultFormatted, true);

//		Response handler 
		if (array_key_exists('registrant_verification_status', $json)) {
			if ($json['registrant_verification_status'] == 'pending') {
				$status = "<strong>" . $domain . ":</strong><p class=\"warning\">The verification process has been initiated, and the validation email will be sent.<br/>This domain will be suspended in: <span style=\"color:red; font-weight:bold;\">" . $json['days_to_suspend'] . " days</span> on <span style=\"color:red; font-weight:bold;\">" . date("M jS Y",strtotime($json['verification_deadline'])) . "</span></p>";
			}
			elseif ($json['registrant_verification_status'] == 'verifying') {
				$status = "<strong>" . $domain . "</strong><p class=\"warning\">The verification process has been initiated and is waiting for registrant response.<br/>This domain will be suspended in: <span style=\"color:red; font-weight:bold;\">" . $json['days_to_suspend'] . " days</span> on <span style=\"color:red; font-weight:bold;\">" . date("M jS Y",strtotime($json['verification_deadline'])) . "</span></p>";
			}
			elseif ($json['registrant_verification_status'] == 'unverified') {
				$status = "<strong>" . $domain . "</strong><p class=\"info\">The verification process has not been initiated. No further action is required at this time.</p>";
			}
			elseif ($json['registrant_verification_status'] == 'verified') {
				$status = "<strong>" . $domain . "</strong><p class\"info\">The registrant has already been verified.  No further action is required at this time.</p>";
			}
			elseif ($json['registrant_verification_status'] == 'suspended') {
				$status = "<strong>" . $domain . "</strong><p class=\"warning\">The registrant has failed verification and the domain has been suspended as of <span style=\"color:red\">" . $json['verification_deadline'] . "</span></p>";
			}
			elseif ($json['registrant_verification_status'] == 'admin_reviewing') {
				$status =  "<strong>" . $domain . "</strong><p class=\"info\">The registrant data has been submitted and is being validated manually by the Tucows Compliance team.</p>";
			}
		}
		else { 
			if ($json['response_text'] == "Authentication Error."){
				$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p>";
			}
			else {
				$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p><pre>" . $json["response_text"] . "</pre>";
			}
		}
	}
}

//	Send verification email
if (isset($_POST["sendVerification"])) {
	$domain = trim($_POST['domain']);
	$_SESSION['domain'] = $domain;

// 	Ensure domain field is populated first
	if (empty($domain)) {
		$domainEmpty = true;
	}
	else {
		$domainEmpty = false;
		$callstring = "";
		$callArray = array (
			"func" => "provSendVerificationMail",
			"data" => array (
				"domain" => $domain,
			)
		);
	
		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);
		$json = json_decode($osrsHandler->resultFormatted, true);

//		Response handler
		if ($json['response_code'] == '200'){
			$status = "<strong>" . $domain . "</strong><p class=\"success\">The verification email has been successfully re-sent.</p>";
		}
		elseif ($json['response_code'] == '415') {
			if ($json['response_text'] == "Authentication Error."){
				$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p>";
			}
			else {
				$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p><pre>" . $json["response_text"] . "</pre>";
			}
		}
		elseif ($json['response_code'] == '400') {
			$status = "<strong>". $domain . "</strong><p class=\"info\">The verification process has not been initiated for this domain. No action is required at this time.</p>";
		}
		else {
// 		Covering all our bases, just in case a code was missed
			$status = "<p class=\"error\">Verification email not sent, please contact support.</p>";
		}
	}
}
if (isSet($_POST["provUpdateContacts"])) {
// 	Sanitize input, validate email address format
	$first_name = strip_tags(trim($_POST['first_name']));
	$last_name = strip_tags(trim($_POST['last_name']));
	$sanitizedEmail = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
	$domain = $_SESSION['domain'];

	if (filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL)) {
		$email = filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL);
		$domainEmpty = false;

// 		Get contact array again to ensure only the first/last/email can be updated
		$callstring = "";
		$callArray = array (
			"func" => "lookupGetDomain",
			"data" => array (
				"domain" => $domain,
				"type" => 'owner'				
			)
		);
		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);
		$contactInfo = json_decode($osrsHandler->resultFormatted, true);

		$org_name = $contactInfo['attributes']['contact_set']['owner']['org_name'];
		$address1 = $contactInfo['attributes']['contact_set']['owner']['address1'];
		$address2 = $contactInfo['attributes']['contact_set']['owner']['address2'];
		$address3 = $contactInfo['attributes']['contact_set']['owner']['address3'];
		$city = $contactInfo['attributes']['contact_set']['owner']['city'];
		$state = $contactInfo['attributes']['contact_set']['owner']['state'];
		$postal_code = $contactInfo['attributes']['contact_set']['owner']['postal_code'];
		$country = $contactInfo['attributes']['contact_set']['owner']['country'];
		$phone = $contactInfo['attributes']['contact_set']['owner']['phone'];
		$fax = $contactInfo['attributes']['contact_set']['owner']['fax'];

//		Getting the domain's lock status
		$callstring = "";
		$callArray = array (
			"func" => "lookupGetDomain",
			"data" => array (
				"domain" => $domain,
				"type" => 'status',
				"domain_name" => $domain
			)
		);

		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);
		$json = json_decode($osrsHandler->resultFormatted, true);

// 		If the domain is unlocked, we can update immediately
// 		lock_state 0 -> unlocked, 1 -> locked
		if($json['attributes']['lock_state'] == '0') {
			$callstring = "";
			$callArray = array (
				"func" => "provUpdateContacts",
				"personal" => array (
					"first_name" => $first_name,
					"last_name" => $last_name,
					"org_name" => $org_name,
					"address1" => $address1,
					"address2" => $address2,
					"address3" => $address3,
					"city" => $city,
					"state" => $state,
					"postal_code" => $postal_code,
					"country" => $country,
					"phone" => $phone,
					"fax" => $fax,
					"email" => $email
				),
				"data" => array (
					"domain" => $domain,
					"types" => 'owner'
				)
			);
		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);

		$status = "<strong>" . $domain . "</strong><p class=\"success\">Domain contact updated successfully</span>.<br/>The verification email has been sent to: <strong>" . $email . "</p>";
		}
		
		else {
// 		Domain is locked, so we unlock it first
			$callstring = "";
			$callArray = array (
				"func" => "provModify",
				"data" => array (
					"affect_domains" => '0',
					"domain" => $domain,
					"domain_name" => $domain,
					"lock_state" => '0',
					"data" => "status"
				),
			);
			$callstring = json_encode($callArray);
			$osrsHandler = processOpenSRS ($formFormat, $callstring);

// 			Apply modifications
			$callstring = "";
			$callArray = array (
				"func" => "provUpdateContacts",
				"personal" => array (
					"first_name" => $first_name,
					"last_name" => $last_name,
					"org_name" => $org_name,
					"address1" => $address1,
					"address2" => $address2,
					"address3" => $address3,
					"city" => $city,
					"state" => $state,
					"postal_code" => $postal_code,
					"country" => $country,
					"phone" => $phone,
					"fax" => $fax,
					"email" => $email
				),
				"data" => array (
				"domain" => $domain,
				"types" => 'owner'
				)
			);
			$callstring = json_encode($callArray);
			$osrsHandler = processOpenSRS ($formFormat, $callstring);

// 			Re-lock domain
			$callstring = "";
			$callArray = array (
				"func" => "provModify",
				"data" => array (
					"affect_domains" => '0',
					"domain" => $domain,
					"domain_name" => $domain,
					"lock_state" => '1',  // 0 = unlocked, 1 = locked
					"data" => "status"
				),
			);
			$callstring = json_encode($callArray);
			$osrsHandler = processOpenSRS ($formFormat, $callstring);

			$status = "<strong>" . $domain . "</strong><p class=\"success\">Owner contact updated successfully</span>.<br/>The verification email has been sent to <strong>" . $email . "</strong></p>";
		}
	}

	else {
		$first_name = strip_tags(trim($_POST['first_name']));
		$last_name = strip_tags(trim($_POST['last_name']));
		$sanitizedEmail = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
		$update = true;
		$domainEmpty = false;

		$callstring = "";
		$callArray = array (
			"func" => "lookupGetDomain",
			"data" => array (
				"domain" => $domain,
				"type" => 'owner',
			)
		);

		$status = "<strong>" . $domain . "</strong><p class=\"error\">The owner contact could not be updated.<br/>Please verify the First Name, Last Name and Email address and try again.</p>";

		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);
		$json = json_decode($osrsHandler->resultFormatted, true);

		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
	<head>
	<title>Domain Verification Tool</title>
	<meta name="generator" http-equiv="generator" content="OpenSRS" />
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Language" content="en"/>
	<link rel="stylesheet" href="style.css" type="text/css" media="all" />
	</head>
	<body>
<div id="container2">
<p class="warning">This page displays the current registrant contact name and email address.  By changing this information, the verification process will begin and the registrant <strong>must</strong> confirm their email address is correct.</p>
	<?php echo($status); ?>
		<form action="<?php echo ($_SERVER['PHP_SELF']) ?>" method="post" autocomplete="off">
			<div id="label" class="label">
<p style="margin-top: 8px; margin-left: 18px;">Domain:</p>
<p style="margin-top: 25px; margin-left: 18px;">First name:</p>
<p style="margin-top: 30px; margin-left: 18px;">Last name:</p>
<p style="margin-top: 35px; margin-left: 18px;">Email:</p>
</div>
<div id="entry">
<input type="text" size="30" maxlength="64" name="domain" value="<?php echo $domain; ?>" autocomplete="off" readonly="readonly" style="margin-right: 10px; background: #E5E5E5;"> <br/>
<input type="text" size="30" maxlength="64" name="first_name" value="<?php echo $first_name;?>" autocomplete="off" style="margin-right: 10px;"><br/>
<input type="text" size="30" maxlength="64" name="last_name" value="<?php echo $last_name; ?>" autocomplete="off" style="margin-right: 10px;"><br/>
<input type="text" size="30" maxlength="100" name="email" value="<?php echo $sanitizedEmail; ?>" autocomplete="off" style="margin-right: 10px;"><br/>
</div>
			<div id="buttons" class="buttons">
				<input type="submit" name="provUpdateContacts" value="Update">
				<input type="submit" name="" value="Go Back">
			</div>
		</form>
	</div>
	</body>
</html>
		<?php
	}
}

if (isset($_POST["getDomainOwner"])) {
	$domain = trim($_POST['domain']);
	
	if (empty($domain)) {
		$domainEmpty = true;
	}
	else {
		$_SESSION['domain'] = $domain;
		$domainEmpty = false;

		$callstring = "";
		$callArray = array (
			"func" => "lookupGetDomain",
			"data" => array (
				"domain" => $domain,
				"type" => 'owner',
			)
		);

		$callstring = json_encode($callArray);
		$osrsHandler = processOpenSRS ($formFormat, $callstring);
		$json = json_decode($osrsHandler->resultFormatted, true);

		if ($json['response_text'] == "Authentication Error.") {
			$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p>";
			$update = false;
		}
		elseif ($json["response_code"] == "415") {
			$status = "<strong>" . $domain . "</strong><p class=\"error\">Sorry, this domain does not exist in your reseller account.</p><pre>" . $json['response_text'] . "</pre>";
			$update = false;
		}
		else {
			$update = true;
			?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
<head>
	<title>Domain Verification Tool</title>
	<meta name="generator" http-equiv="generator" content="OpenSRS" />
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Language" content="en"/>
	<link rel="stylesheet" href="style.css" type="text/css" media="all" />
	</head>
	<body>
	<div id="container2">
<p class="warning">This page displays the current registrant contact name and email address.  By changing this information, the verification process will begin and the registrant <strong>must</strong> confirm their email address is correct.</p>
<?php echo($status); ?>
		<form action="<?php echo ($_SERVER['PHP_SELF']) ?>" method="post" autocomplete="off">
			<div id="label" class="label">
<p style="margin-top: 11px; margin-left: 20px;">Domain:</p>
<p style="margin-top: 25px; margin-left: 20px;">First name:</p>
<p style="margin-top: 30px; margin-left: 20px;">Last name:</p>
<p style="margin-top: 30px; margin-left: 20px;">Email:</p>
</div>
<div id="entry">
<input type="text" size="30" maxlength="64" name="domain" value="<?php echo $domain; ?>" readonly="readonly" autocomplete="off" style="background: #E5E5E5; margin-right: 17px;"><br/>
<input type="text" size="30" maxlength="64" name="first_name" value="<?php echo($json['attributes']['contact_set']['owner']['first_name']);?>" autocomplete="off" style="margin-right: 17px;"><br/>
<input type="text" size="30" maxlength="64" name="last_name" value="<?php echo($json['attributes']['contact_set']['owner']['last_name']); ?>" autocomplete="off" style="margin-right: 17px;"><br/>
<input type="text" size="30" maxlength="100" name="email" value="<?php echo($json['attributes']['contact_set']['owner']['email']); ?>" autocomplete="off" style="margin-right: 17px;"><br/>
</div>
			<div class="buttons">
				<input type="submit" name="provUpdateContacts" value="Update">
				<input type="submit" name="" value="Go Back">
			</div>
		</form>
	</div>
	</body>
</html>
		<?php
		}
	}
}

if ($update != true) {
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" >
<head>
	<title>Domain Verification Tool</title>
	<meta name="generator" http-equiv="generator" content="OpenSRS" />
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<meta http-equiv="Content-Language" content="en"/>
	<link rel="stylesheet" href="style.css" type="text/css" media="all" />
</head>
<body>
	<div id="container">
		<?php echo $status; ?>
		<?php if ($domainEmpty == true) { echo($domainError); } ?>
		<form action="<?php echo ($_SERVER['PHP_SELF']) ?>" method="post">
		<div class="label" >
<p style="margin-top: 11px; margin-left: 20px; margin-top: 0px; font-weight: bold;">Please enter a domain:<br/><span style="font-size: 10px;">Format: testdomain.com</span></p>
		</div> 
		<div id="entry">
		<input type="text" size="30" maxlength="80" name="domain" value="<?php if (empty($_SESSION['domain'])) {echo(""); $domainEmpty = true;} else{ echo($_SESSION['domain']); $domainEmpty = false;} ?>" />
		</div>
		<div class="buttons">
				<input value="Get Verification Status" type="submit" name="lookupVerification" />
				<input value="Resend Verification Email" type="submit" name="sendVerification" />
				<input value="View and Modify Domain Contacts" type="submit" name="getDomainOwner" />
		</div>
	</div>
</body>
</html>
	<?php
}
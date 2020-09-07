<?php
/**
 * WHMCS SDK Name.am Registrar Module
 *
 * Registrar Modules allow you to create modules that allow for domain
 * registration, management, transfers, and other functionality within
 * WHMCS.
 *
 * This file demonstrates how a registrar module for WHMCS should
 * be structured and exercises supported functionality.
 *
 * Registrar Modules are stored in a unique directory within the
 * modules/registrars/ directory that matches the module's unique name.
 * This name should be all lowercase, containing only letters and numbers,
 * and always start with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For
 * example this file, the filename is "registrarmodule.php" and therefore all
 * function begin "nameam_".
 *
 * If your module or third party API does not support a given function, you
 * should not define the function within your module. WHMCS recommends that
 * all registrar modules implement Register, Transfer, Renew, GetNameservers,
 * SaveNameservers, GetContactDetails & SaveContactDetails.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/domain-registrars/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domain\TopLevel\ImportItem;
// use WHMCS\Results\ResultsList;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\NameamApi\ApiClient;

require_once __DIR__ . '/lib/ApiClient.php';

// Require any libraries needed for the module to function.
// require_once __DIR__ . '/path/to/library/loader.php';
//
// Also, perform any initialization required by the service's library.

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function nameam_MetaData()
{
    return array(
        'DisplayName' => 'Name.am Registrar Module for WHMCS',
        'APIVersion' => '1.1',
    );
}

/**
 * Define registrar configuration options.
 *
 * The values you return here define what configuration options
 * we store for the module. These values are made available to
 * each module function.
 *
 * You can store an unlimited number of configuration settings.
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * @return array
 */
function nameam_getConfigArray()
{
    return array(
        // Friendly display name for the module
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Name.am Registrar Module for WHMCS',
        ),
        // a text field type allows for single line text input
        'Email' => array(
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'Your name.am account email',
        ),
        // a password field type allows for masked text input
        'Password' => array(
            'Type' => 'password',
            'Size' => '255',
            'Default' => '',
            'Description' => 'Your name.am account password',
        ),
        // the yesno field type displays a single checkbox option
        'Test Mode' => array(
            'Type' => 'yesno',
            'Description' => 'Tick to enable',
        ),
        // the dropdown field type renders a select menu of options
        'Account Mode' => array(
            'Type' => 'dropdown',
            'Options' => array(
                'reseller' => 'Reseller',
                'own' => 'My own use',
            ),
            'Description' => 'Choose one',
        ),
        // the radio field type displays a series of radio button options
        'Email Preference' => array(
            'Type' => 'radio',
            'Options' => 'Notify all,Notify Renew,Notify Payments, Notify Orders',
            'Description' => 'Choose your preference',
        ),
        // the textarea field type allows for multi-line text input
        'Additional Information' => array(
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '60',
            'Description' => 'Write a Notes for you :)',
        ),
    );
}

/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_RegisterDomain($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    /**
     * Nameservers.
     *
     * If purchased with web hosting, values will be taken from the
     * assigned web hosting server. Otherwise uses the values specified
     * during the order process.
     */
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    

    /**
     * Admin contact information.
     *
     * Defaults to the same as the client information. Can be configured
     * to use the web hosts details if the `Use Clients Details` option
     * is disabled in Setup > General Settings > Domains.
     */
    $adminFirstName = $params["adminfirstname"];
    $adminLastName = $params["adminlastname"];
    $adminCompanyName = $params["admincompanyname"];
    $adminEmail = $params["adminemail"];
    $adminAddress1 = $params["adminaddress1"];
    $adminAddress2 = $params["adminaddress2"];
    $adminCity = $params["admincity"];
    $adminState = $params["adminstate"]; // eg. TX
    $adminStateFull = $params["adminfullstate"]; // eg. Texas
    $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
    $adminCountry = $params["admincountry"]; // eg. GB
    $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
    $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. The premium order should only be processed
     * if the cost price now matches the previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'years' => $registrationPeriod,
        'nameservers' => array(
            'ns1' => $nameserver1,
            'ns2' => $nameserver2,
            'ns3' => $nameserver3,
            'ns4' => $nameserver4,
            'ns5' => $nameserver5,
        ),
        'contacts' => array(
            'registrant' => array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'companyname' => $companyName,
                'email' => $email,
                'address1' => $address1,
                'address2' => $address2,
                'city' => $city,
                'state' => $state,
                'zipcode' => $postcode,
                'country' => $countryCode,
                'phonenumber' => $phoneNumberFormatted,
            ),
            'tech' => array(
                'firstname' => $adminFirstName,
                'lastname' => $adminLastName,
                'companyname' => $adminCompanyName,
                'email' => $adminEmail,
                'address1' => $adminAddress1,
                'address2' => $adminAddress2,
                'city' => $adminCity,
                'state' => $adminState,
                'zipcode' => $adminPostcode,
                'country' => $adminCountry,
                'phonenumber' => $adminPhoneNumberFormatted,
            ),
        ),
        'dnsmanagement' => $enableDnsManagement,
        'emailforwarding' => $enableEmailForwarding,
        'idprotection' => $enableIdProtection,
    );

    if ($premiumDomainsEnabled && $premiumDomainsCost) {
        $postfields['accepted_premium_cost'] = $premiumDomainsCost;
    }


    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = json_encode([
        [
            "name" => $tld, 
            "type" => "domain_registration", 
            "domain" => $sld.'.'.$tld, 
            "plan" => [
                "_id" => $registrationPeriod."_year_register" 
            ], 
            "registrantContacts" => [
                "organization" => $companyName, 
                "firstName" => $firstName, 
                "lastName" => $lastName, 
                "fullName" => $firstName." ".$lastName, 
                "address1" => $address1, 
                "country" => $countryCode, 
                "email" => $email, 
                "phone" => str_replace('.', '', $phoneNumberFormatted), 
                "state" => $adminState, 
                "city" => $adminCity, 
                "zip" => $adminPostcode 
            ], 
            "administrativeContacts" => [
                "organization" => $companyName, 
                "firstName" => $firstName, 
                "lastName" => $lastName, 
                "fullName" => $firstName." ".$lastName, 
                "address1" => $address1, 
                "country" => $countryCode, 
                "email" => $email, 
                "phone" => str_replace('.', '', $phoneNumberFormatted), 
                "state" => $adminState, 
                "city" => $adminCity, 
                "zip" => $adminPostcode 
            ], 
            "technicalContacts" => [
                "organization" => $companyName, 
                "firstName" => $firstName, 
                "lastName" => $lastName, 
                "fullName" => $firstName." ".$lastName, 
                "address1" => $address1, 
                "country" => $countryCode, 
                "email" => $email, 
                "phone" => str_replace('.', '', $phoneNumberFormatted), 
                "state" => $adminState, 
                "city" => $adminCity, 
                "zip" => $adminPostcode 
            ], 
            "billingContacts" => [
                "organization" => $companyName, 
                "firstName" => $firstName, 
                "lastName" => $lastName, 
                "fullName" => $firstName." ".$lastName, 
                "address1" => $address1, 
                "country" => $countryCode, 
                "email" => $email, 
                "phone" => str_replace('.', '', $phoneNumberFormatted), 
                "state" => $adminState, 
                "city" => $adminCity, 
                "zip" => $adminPostcode 
            ], 
            "nameServers" => [
               ["hostname" => $nameserver1], 
               ["hostname" => $nameserver2],
               ["hostname" => $nameserver3],
               ["hostname" => $nameserver4],
               ["hostname" => $nameserver5]
            ] 
        ] 
    ]); 

    try {
        $api = new ApiClient();
        $response = $api->call('/client/carts/purchase', $postfields);
        // $api->call('/client/carts/purchase', $postfields);
        if ($response['status'] == 'failed') {
            throw new Exception($response['method']);
        } else {
            return array(
                'success' => true,
            );
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Initiate domain transfer.
 *
 * Attempt to create a domain transfer request for a given domain.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain transfer order
 * * When a pending domain transfer order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
// function nameam_TransferDomain($params)
// {
//     // user defined configuration values
//     $apiEmail = $params['Email'];
//     $apiPassword = $params['Password'];
//     $testMode = $params['Test Mode'];
//     $accountMode = $params['Account Mode'];
//     $emailPreference = $params['Email Preference'];
//     $additionalInfo = $params['Additional Information'];

//     // registration parameters
//     $sld = $params['sld'];
//     $tld = $params['tld'];
//     $registrationPeriod = $params['regperiod'];
//     $eppCode = $params['eppcode'];

//     /**
//      * Nameservers.
//      *
//      * If purchased with web hosting, values will be taken from the
//      * assigned web hosting server. Otherwise uses the values specified
//      * during the order process.
//      */
//     $nameserver1 = $params['ns1'];
//     $nameserver2 = $params['ns2'];
//     $nameserver3 = $params['ns3'];
//     $nameserver4 = $params['ns4'];
//     $nameserver5 = $params['ns5'];

//     // registrant information
//     $firstName = $params["firstname"];
//     $lastName = $params["lastname"];
//     $fullName = $params["fullname"]; // First name and last name combined
//     $companyName = $params["companyname"];
//     $email = $params["email"];
//     $address1 = $params["address1"];
//     $address2 = $params["address2"];
//     $city = $params["city"];
//     $state = $params["state"]; // eg. TX
//     $stateFullName = $params["fullstate"]; // eg. Texas
//     $postcode = $params["postcode"]; // Postcode/Zip code
//     $countryCode = $params["countrycode"]; // eg. GB
//     $countryName = $params["countryname"]; // eg. United Kingdom
//     $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
//     $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
//     $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

//     /**
//      * Admin contact information.
//      *
//      * Defaults to the same as the client information. Can be configured
//      * to use the web hosts details if the `Use Clients Details` option
//      * is disabled in Setup > General Settings > Domains.
//      */
//     $adminFirstName = $params["adminfirstname"];
//     $adminLastName = $params["adminlastname"];
//     $adminCompanyName = $params["admincompanyname"];
//     $adminEmail = $params["adminemail"];
//     $adminAddress1 = $params["adminaddress1"];
//     $adminAddress2 = $params["adminaddress2"];
//     $adminCity = $params["admincity"];
//     $adminState = $params["adminstate"]; // eg. TX
//     $adminStateFull = $params["adminfullstate"]; // eg. Texas
//     $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
//     $adminCountry = $params["admincountry"]; // eg. GB
//     $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
//     $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

//     // domain addon purchase status
//     $enableDnsManagement = (bool) $params['dnsmanagement'];
//     $enableEmailForwarding = (bool) $params['emailforwarding'];
//     $enableIdProtection = (bool) $params['idprotection'];

//     /**
//      * Premium domain parameters.
//      *
//      * Premium domains enabled informs you if the admin user has enabled
//      * the selling of premium domain names. If this domain is a premium name,
//      * `premiumCost` will contain the cost price retrieved at the time of
//      * the order being placed. The premium order should only be processed
//      * if the cost price now matches that previously fetched amount.
//      */
//     $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
//     $premiumDomainsCost = $params['premiumCost'];

//     // Build post data
//     $postfields = array(
//         'email' => $apiEmail,
//         'password' => $apiPassword,
//         'testmode' => $testMode,
//         'domain' => $sld . '.' . $tld,
//         'eppcode' => $eppCode,
//         'nameservers' => array(
//             'ns1' => $nameserver1,
//             'ns2' => $nameserver2,
//             'ns3' => $nameserver3,
//             'ns4' => $nameserver4,
//             'ns5' => $nameserver5,
//         ),
//         'years' => $registrationPeriod,
//         'contacts' => array(
//             'registrant' => array(
//                 'firstname' => $firstName,
//                 'lastname' => $lastName,
//                 'companyname' => $companyName,
//                 'email' => $email,
//                 'address1' => $address1,
//                 'address2' => $address2,
//                 'city' => $city,
//                 'state' => $state,
//                 'zipcode' => $postcode,
//                 'country' => $countryCode,
//                 'phonenumber' => $phoneNumberFormatted,
//             ),
//             'tech' => array(
//                 'firstname' => $adminFirstName,
//                 'lastname' => $adminLastName,
//                 'companyname' => $adminCompanyName,
//                 'email' => $adminEmail,
//                 'address1' => $adminAddress1,
//                 'address2' => $adminAddress2,
//                 'city' => $adminCity,
//                 'state' => $adminState,
//                 'zipcode' => $adminPostcode,
//                 'country' => $adminCountry,
//                 'phonenumber' => $adminPhoneNumberFormatted,
//             ),
//         ),
//         'dnsmanagement' => $enableDnsManagement,
//         'emailforwarding' => $enableEmailForwarding,
//         'idprotection' => $enableIdProtection,
//     );



//     $postfields = [];
//     $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
//     $postfields['post_data'] = json_encode([$params['domainname']]); 

//     print_r('15');
//     print_r($params);
//     exit;

//     try {
//         $api = new ApiClient();
//         $api->call('/client/domains/wizard/transfer', $postfields);

//         return array(
//             'success' => true,
//         );

//     } catch (\Exception $e) {
//         return array(
//             'error' => $e->getMessage(),
//         );
//     }
// }

/**
 * Renew a domain.
 *
 * Attempt to renew/extend a domain for a given number of years.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain renewal order
 * * When a pending domain renewal order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_RenewDomain($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. A premium renewal should only be processed
     * if the cost price now matches that previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    // Build post data.
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'years' => $registrationPeriod,
        'dnsmanagement' => $enableDnsManagement,
        'emailforwarding' => $enableEmailForwarding,
        'idprotection' => $enableIdProtection,
    );


    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = json_encode([
        [
            "name" => $tld, 
            "type" => "domain_renew", 
            "domain" => $sld.'.'.$tld, 
            "plan" => [
                "_id" => $registrationPeriod."_year_register" 
            ]
        ] 
    ]);


    try {
        $api = new ApiClient();
        $api->call('/client/carts/purchase', $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_GetNameservers($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = ''; 

    try {
        $api = new ApiClient();
        $result_data = $api->call('/client/domains', $postfields, 'GET');
        $domain = [];
        foreach ($api->getFromResponse('docs') as $key => $value) {
            if ($value['domain'] == $params['domainname']) {
                $domain = $value;
            }
        }

        return array(
            // 'success' => true,
            'ns1' => (($domain['nameServers'][0]['hostname']) ? $domain['nameServers'][0]['hostname'] : ''),
            'ns2' => (($domain['nameServers'][1]['hostname']) ? $domain['nameServers'][1]['hostname'] : ''),
            'ns3' => (($domain['nameServers'][2]['hostname']) ? $domain['nameServers'][2]['hostname'] : ''),
            'ns4' => (($domain['nameServers'][3]['hostname']) ? $domain['nameServers'][3]['hostname'] : ''),
            'ns5' => (($domain['nameServers'][4]['hostname']) ? $domain['nameServers'][4]['hostname'] : ''),
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_SaveNameservers($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // submitted nameserver values
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver1' => $nameserver1,
        'nameserver2' => $nameserver2,
        'nameserver3' => $nameserver3,
        'nameserver4' => $nameserver4,
        'nameserver5' => $nameserver5,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $nameServers = [];
    $pattern = '/^(http[s]?\:\/\/)?(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/';
    if ($params['ns1'] && preg_match($pattern, $params['ns1'])) {
        $nameServers[] = ["hostname" => $params['ns1']];
    }
    if ($params['ns2'] && preg_match($pattern, $params['ns2'])) {
        $nameServers[] = ["hostname" => $params['ns2']];
    }
    if ($params['ns3'] && preg_match($pattern, $params['ns3'])) {
        $nameServers[] = ["hostname" => $params['ns3']];
    }
    if ($params['ns4'] && preg_match($pattern, $params['ns4'])) {
        $nameServers[] = ["hostname" => $params['ns4']];
    }
    if ($params['ns5'] && preg_match($pattern, $params['ns5'])) {
        $nameServers[] = ["hostname" => $params['ns5']];
    }
    $postfields['post_data'] = json_encode([
        "nameServers" => $nameServers
    ]); 

    // print_r('1');
    // print_r($params);
    // exit;

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$params['domainname'], $postfields, 'PUT');

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get the current WHOIS Contact Information.
 *
 * Should return a multi-level array of the contacts and name/address
 * fields that be modified.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_GetContactDetails($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = '';

    try {
        $api = new ApiClient();
        $api->call('/client/domains', $postfields, 'GET');

        $domain = [];
        foreach ($api->getFromResponse('docs') as $key => $value) {
            if ($value['domain'] == $params['domainname']) {
                $domain = $value;
            }
        }

        return array(
            'Registrant' => array(
                'First Name' => $domain['registrantContacts']['firstName'],
                'Last Name' => $domain['registrantContacts']['lastName'],
                'Company Name' => $domain['registrantContacts']['organization'],
                'Email Address' => $domain['registrantContacts']['email'],
                'Address 1' => $domain['registrantContacts']['address1'],
                'Address 2' => '',
                'City' => $domain['registrantContacts']['city'],
                'State' => $domain['registrantContacts']['state'],
                'Postcode' => $domain['registrantContacts']['zip'],
                'Country' => $domain['registrantContacts']['country'],
                'Phone Number' => $domain['registrantContacts']['phone'],
                'Fax Number' => '',
            ),
            'Technical' => array(
                'First Name' => $domain['technicalContacts']['firstName'],
                'Last Name' => $domain['technicalContacts']['lastName'],
                'Company Name' => $domain['technicalContacts']['organization'],
                'Email Address' => $domain['technicalContacts']['email'],
                'Address 1' => $domain['technicalContacts']['address1'],
                'Address 2' => '',
                'City' => $domain['technicalContacts']['city'],
                'State' => $domain['technicalContacts']['state'],
                'Postcode' => $domain['technicalContacts']['zip'],
                'Country' => $domain['technicalContacts']['country'],
                'Phone Number' => $domain['technicalContacts']['phone'],
                'Fax Number' => '',
            ),
            'Billing' => array(
                'First Name' => $domain['billingContacts']['firstName'],
                'Last Name' => $domain['billingContacts']['lastName'],
                'Company Name' => $domain['billingContacts']['organization'],
                'Email Address' => $domain['billingContacts']['email'],
                'Address 1' => $domain['billingContacts']['address1'],
                'Address 2' => '',
                'City' => $domain['billingContacts']['city'],
                'State' => $domain['billingContacts']['state'],
                'Postcode' => $domain['billingContacts']['zip'],
                'Country' => $domain['billingContacts']['country'],
                'Phone Number' => $domain['billingContacts']['phone'],
                'Fax Number' => '',
            ),
            'Admin' => array(
                'First Name' => $domain['administrativeContacts']['firstName'],
                'Last Name' => $domain['administrativeContacts']['lastName'],
                'Company Name' => $domain['administrativeContacts']['organization'],
                'Email Address' => $domain['administrativeContacts']['email'],
                'Address 1' => $domain['administrativeContacts']['address1'],
                'Address 2' => '',
                'City' => $domain['administrativeContacts']['city'],
                'State' => $domain['administrativeContacts']['state'],
                'Postcode' => $domain['administrativeContacts']['zip'],
                'Country' => $domain['administrativeContacts']['country'],
                'Phone Number' => $domain['administrativeContacts']['phone'],
                'Fax Number' => '',
            ),
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Update the WHOIS Contact Information for a given domain.
 *
 * Called when a change of WHOIS Information is requested within WHMCS.
 * Receives an array matching the format provided via the `GetContactDetails`
 * method with the values from the users input.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_SaveContactDetails($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // whois information
    $contactDetails = $params['contactdetails'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'contacts' => array(
            'registrant' => array(
                'firstname' => $contactDetails['Registrant']['First Name'],
                'lastname' => $contactDetails['Registrant']['Last Name'],
                'company' => $contactDetails['Registrant']['Company Name'],
                'email' => $contactDetails['Registrant']['Email Address'],
                // etc...
            ),
            'tech' => array(
                'firstname' => $contactDetails['Technical']['First Name'],
                'lastname' => $contactDetails['Technical']['Last Name'],
                'company' => $contactDetails['Technical']['Company Name'],
                'email' => $contactDetails['Technical']['Email Address'],
                // etc...
            ),
            'billing' => array(
                'firstname' => $contactDetails['Billing']['First Name'],
                'lastname' => $contactDetails['Billing']['Last Name'],
                'company' => $contactDetails['Billing']['Company Name'],
                'email' => $contactDetails['Billing']['Email Address'],
                // etc...
            ),
            'admin' => array(
                'firstname' => $contactDetails['Admin']['First Name'],
                'lastname' => $contactDetails['Admin']['Last Name'],
                'company' => $contactDetails['Admin']['Company Name'],
                'email' => $contactDetails['Admin']['Email Address'],
                // etc...
            ),
        ),
    );


    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = json_encode([
        "administrativeContacts" => [
            "organization" => $contactDetails['Admin']['Company Name'],
            "firstName" => $contactDetails['Admin']['First Name'],
            "lastName" => $contactDetails['Admin']['Last Name'],
            "fullName" => $contactDetails['Admin']['First Name']." ".$contactDetails['Admin']['Last Name'],
            "address1" => $contactDetails['Admin']['Address 1'],
            "country" => $contactDetails['Admin']['Country'],
            "email" => $contactDetails['Admin']['Email Address'],
            "phone" => str_replace('.', '', $contactDetails['Admin']['Phone Number']),
            "state" => $contactDetails['Admin']['State'],
            "city" => $contactDetails['Admin']['City'],
            "zip" => $contactDetails['Admin']['Postcode'],
        ], 
        "registrantContacts" => [
            "organization" => $contactDetails['Registrant']['Company Name'],
            "firstName" => $contactDetails['Registrant']['First Name'],
            "lastName" => $contactDetails['Registrant']['Last Name'],
            "fullName" => $contactDetails['Registrant']['First Name']." ".$contactDetails['Registrant']['Last Name'],
            "address1" => $contactDetails['Registrant']['Address 1'],
            "country" => $contactDetails['Registrant']['Country'],
            "email" => $contactDetails['Registrant']['Email Address'],
            "phone" => str_replace('.', '', $contactDetails['Registrant']['Phone Number']),
            "state" => $contactDetails['Registrant']['State'],
            "city" => $contactDetails['Registrant']['City'],
            "zip" => $contactDetails['Registrant']['Postcode'],
        ], 
        "technicalContacts" => [
            "organization" => $contactDetails['Technical']['Company Name'],
            "firstName" => $contactDetails['Technical']['First Name'],
            "lastName" => $contactDetails['Technical']['Last Name'],
            "fullName" => $contactDetails['Technical']['First Name']." ".$contactDetails['Technical']['Last Name'],
            "address1" => $contactDetails['Technical']['Address 1'],
            "country" => $contactDetails['Technical']['Country'],
            "email" => $contactDetails['Technical']['Email Address'],
            "phone" => str_replace('.', '', $contactDetails['Technical']['Phone Number']),
            "state" => $contactDetails['Technical']['State'],
            "city" => $contactDetails['Technical']['City'],
            "zip" => $contactDetails['Technical']['Postcode'],
        ], 
        "billingContacts" => [
            "organization" => $contactDetails['Billing']['Company Name'],
            "firstName" => $contactDetails['Billing']['First Name'],
            "lastName" => $contactDetails['Billing']['Last Name'],
            "fullName" => $contactDetails['Billing']['First Name']." ".$contactDetails['Billing']['Last Name'],
            "address1" => $contactDetails['Billing']['Address 1'],
            "country" => $contactDetails['Billing']['Country'],
            "email" => $contactDetails['Billing']['Email Address'],
            "phone" => str_replace('.', '', $contactDetails['Billing']['Phone Number']),
            "state" => $contactDetails['Billing']['State'],
            "city" => $contactDetails['Billing']['City'],
            "zip" => $contactDetails['Billing']['Postcode'],
        ] 
    ]);

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$sld.'.'.$tld.'', $postfields, 'PUT');

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain availability check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function nameam_CheckAvailability($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // availability check parameters
    $searchTerm = $params['searchTerm'];
    $punyCodeSearchTerm = $params['punyCodeSearchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $isIdnDomain = (bool) $params['isIdnDomain'];
    $premiumEnabled = (bool) $params['premiumEnabled'];

    // domain parameters
    $sld = $params['sld'];
    $tld = (!empty($params['tlds'][0])) ? substr($params['tlds'][0], 1) : '';
    // $tld = $params['tld'];

    // Build post data
    $post_data = [];

    $test_domen = '';
    if ($testMode) {
        $test_domen = 'test-domain-refuse-';
    }

    foreach ($tldsToInclude as $tldKey => $tldValue) {
        $post_data[] = [
            "tld" => substr($tldValue, 1),
            "domain" => $searchTerm . $tldValue,
        ];
    }
    $post_data = json_encode($post_data);

    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'searchTerm' => $searchTerm,
        'tldsToSearch' => $tldsToInclude,
        'includePremiumDomains' => $premiumEnabled,
        'authentication' => json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']),
        'post_data' => $post_data,
    );

    try {
        $api = new ApiClient();
        $result_data = $api->call('/client/domains/check', $postfields);

        $results = new ResultsList();
        // print_r($results);
        // exit;
        foreach ($result_data as $domain) {

            // Instantiate a new domain search result object
            // print_r($domain['sld'], $domain['tld']);
            // exit;
            // $searchResult = new SearchResult(str_replace($domain['tld'], '', $domain['domain']), '.'.$domain['tld']);
            $searchResult = new SearchResult('', $domain['domain']);

            // print_r($domain);
            // exit;

            // Determine the appropriate status to return
            if ($domain['available'] == true OR $domain['available'] == '1') {
                $status = SearchResult::STATUS_NOT_REGISTERED;
            } elseif ($domain['available'] == false OR $domain['available'] == '') {
                $status = SearchResult::STATUS_REGISTERED;
            // } elseif ($domain['statis'] == 'reserved') {
            //     $status = SearchResult::STATUS_RESERVED;
            } else {
                $status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
            }
            $searchResult->setStatus($status);

            // Return premium information if applicable
            if ($domain['premium']) {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing(
                    array(
                        'register' => $domain['price'],
                        'renew' => $domain['priceRenew'],
                        'CurrencyCode' => 'AMD',
                    )
                );
            }

            // Append to the search results list
            $results->append($searchResult);
        }

        return $results;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Domain Suggestion Settings.
 *
 * Defines the settings relating to domain suggestions (optional).
 * It follows the same convention as `getConfigArray`.
 *
 * @see https://developers.whmcs.com/domain-registrars/check-availability/
 *
 * @return array of Configuration Options
 */
function nameam_DomainSuggestionOptions() {
    return array(
        'includeCCTlds' => array(
            'FriendlyName' => 'Include Country Level TLDs',
            'Type' => 'yesno',
            'Description' => 'Tick to enable',
        ),
    );
}

/**
 * Get Domain Suggestions.
 *
 * Provide domain suggestions based on the domain lookup term provided.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain suggestions check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function nameam_GetDomainSuggestions($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // availability check parameters
    $searchTerm = $params['searchTerm'];
    $punyCodeSearchTerm = $params['punyCodeSearchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $isIdnDomain = (bool) $params['isIdnDomain'];
    $premiumEnabled = (bool) $params['premiumEnabled'];
    $suggestionSettings = $params['suggestionSettings'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'searchTerm' => $searchTerm,
        'tldsToSearch' => $tldsToInclude,
        'includePremiumDomains' => $premiumEnabled,
        'includeCCTlds' => $suggestionSettings['includeCCTlds'],
    );

    print_r('2');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('GetSuggestions', $postfields);

        $results = new ResultsList();
        foreach ($api->getFromResponse('domains') as $domain) {

            // Instantiate a new domain search result object
            $searchResult = new SearchResult($domain['sld'], $domain['tld']);

            // All domain suggestions should be available to register
            $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);

            // Used to weight results by relevance
            $searchResult->setScore($domain['score']);

            // Return premium information if applicable
            if ($domain['isPremiumName']) {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing(
                    array(
                        'register' => $domain['premiumRegistrationPrice'],
                        'renew' => $domain['premiumRenewPrice'],
                        'CurrencyCode' => 'USD',
                    )
                );
            }

            // Append to the search results list
            $results->append($searchResult);
        }

        return $results;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get registrar lock status.
 *
 * Also known as Domain Lock or Transfer Lock status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return string|array Lock status or error message
 */
function nameam_GetRegistrarLock($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = '';

    try {
        $api = new ApiClient();
        $result_data = $api->call('/client/domains', $postfields, 'GET');
        $domain['transferLock'] = false;
        foreach ($api->getFromResponse('docs') as $key => $value) {
            if ($value['domain'] == $params['domainname']) {
                $domain = $value;
            }
        }

        if ($domain['transferLock']) {
            return 'locked';
        } else {
            return 'unlocked';
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Set registrar lock status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_SaveRegistrarLock($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // lock status
    $lockStatus = $params['lockenabled'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'registrarlock' => ($lockStatus == 'locked') ? 1 : 0,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $transferLock = (($params['lockenabled'] == 'locked') ? true : false);
    $postfields['post_data'] = json_encode([
        "transferLock" => $transferLock
    ]);

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$params['domainname'], $postfields, 'PUT');

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get DNS Records for DNS Host Record Management.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array DNS Host Records
 */
function nameam_GetDNS($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $transferLock = (($params['lockenabled'] == 'locked') ? true : false);
    $postfields['post_data'] = [];

    try {
        $api = new ApiClient();
        $api->call('/client/domains', $postfields, 'GET');
        foreach ($api->getFromResponse('docs') as $key => $value) {
            if ($value['domain'] == $params['domainname']) {
                $domain = $value;
            }
        }

        $hostRecords = array();
        foreach ($domain['records'] as $record) {
            $hostRecords[] = array(
                "hostname" => $record['name'], // eg. www
                "type" => $record['type'], // eg. A
                "address" => $record['content'], // eg. 10.0.0.1
                "priority" => (($record['type'] == 'MX') ? $record['priority'] : 'N/A'), // eg. 10 (N/A for non-MX records)
            );
        }
        return $hostRecords;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Update DNS Host Records.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_SaveDNS($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // dns record parameters
    $dnsrecords = $params['dnsrecords'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'records' => $dnsrecords,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $dnsrecords = [];
    foreach ($params['dnsrecords'] as $key => $value) {
        $dnsrecords[] = [
                "type" => $value['type'],
                "ttl" => 1,
                // "prefix" => "xx",
                "name" => $value['hostname'],
                "content" => $value['address'],
                "action" => "CREATE"
            ];
    }
    $postfields['post_data'] = json_encode([
        "records" => $dnsrecords
    ]);

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$params['domainname'], $postfields, 'PUT');

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Enable/Disable ID Protection.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_IDProtectToggle($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // id protection parameter
    $protectEnable = (bool) $params['protectenable'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $whoIsPrivacyStatus = (($params['idprotection'] == '1') ? false : true);
    $postfields['post_data'] = json_encode([
        "whoIsPrivacyStatus" => $whoIsPrivacyStatus
    ]);

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$params['domainname'], $postfields, 'PUT');

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Request EEP Code.
 *
 * Supports both displaying the EPP Code directly to a user or indicating
 * that the EPP Code will be emailed to the registrant.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 *
 */
function nameam_GetEPPCode($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = '';

    try {
        $api = new ApiClient();
        $api->call('/client/domains/'.$sld.'.'.$tld.'/transfer', $postfields, 'GET');

        if ($api->getFromResponse('transferCode')) {
            // If EPP Code is returned, return it for display to the end user
            return array(
                'eppcode' => $api->getFromResponse('transferCode'),
            );
        } else {
            // If EPP Code is not returned, it was sent by email, return success
            return array(
                'success' => 'success',
            );
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Release a Domain.
 *
 * Used to initiate a transfer out such as an IPSTAG change for .UK
 * domain names.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/*function nameam_ReleaseDomain($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // transfer tag
    $transferTag = $params['transfertag'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'newtag' => $transferTag,
    );

    print_r('9');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('ReleaseDomain', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}*/

/**
 * Delete Domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/*function nameam_RequestDelete($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    print_r('10');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('DeleteDomain', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}*/

/**
 * Register a Nameserver.
 *
 * Adds a child nameserver for the given domain name.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/*function nameam_RegisterNameserver($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];
    $ipAddress = $params['ipaddress'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
        'ip' => $ipAddress,
    );

    print_r('11');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('RegisterNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}*/

/**
 * Modify a Nameserver.
 *
 * Modifies the IP of a child nameserver.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_ModifyNameserver($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];
    $currentIpAddress = $params['currentipaddress'];
    $newIpAddress = $params['newipaddress'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
        'currentip' => $currentIpAddress,
        'newip' => $newIpAddress,
    );

    print_r('12');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('ModifyNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Delete a Nameserver.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_DeleteNameserver($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
    );

    print_r('13');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('DeleteNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Sync Domain Status & Expiration Date.
 *
 * Domain syncing is intended to ensure domain status and expiry date
 * changes made directly at the domain registrar are synced to WHMCS.
 * It is called periodically for a domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_Sync($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = [];

    try {
        $api = new ApiClient();
        $api->call('/client/domains', $postfields, 'GET');
        foreach ($api->getFromResponse('docs') as $key => $value) {
            if ($value['domain'] == $params['domainname']) {
                $domain = $value;
            }
        }

        $expired = false;
        $date = new DateTime($domain['expiration']);
        $now = new DateTime();
        if($date < $now) {
            $expired = true;
        }

        return array(
            'expirydate' => $domain('expiration'), // Format: YYYY-MM-DD
            'active' => (bool) (($domain['status'] == 'active') ? true : false), // Return true if the domain is active
            'expired' => (bool) $expired, // Return true if the domain has expired
            'transferredAway' => (bool) false, // Return true if the domain is transferred out
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

function nameam_GetTldPricing($params)
{
    // Perform API call to retrieve extension information
    // A connection error should return a simple array with error key and message
    // return ['error' => 'This error occurred',];

    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];

    $postfields = [];
    $postfields['authentication'] = json_encode(["email"=>$apiEmail,"password"=>$apiPassword,"token"=>'']);
    $postfields['post_data'] = [];

    $api = new ApiClient();
    $result_data = $api->call('/client/products', $postfields, 'GET');
    if (empty($result_data)) {
        return ['error' => 'This error occurred'];        
    }
    // $extensionData = [];
    // foreach ($result_data as $key => $value) {
    //     $extensionData[$value]
    // }

    $results = new ResultsList;
    $extensionData = $result_data;
    // print_r($extensionData);
    // exit;
    foreach ($extensionData as $extension) {
        $reactivate['minPeriod'] = 0;
        $reactivate['maxPeriod'] = 0;
        $reactivate['price'] = 0;
        $register['minPeriod'] = 0;
        $register['maxPeriod'] = 0;
        $register['price'] = 0;
        $renew['minPeriod'] = 0;
        $renew['maxPeriod'] = 0;
        $renew['price'] = 0;
        $transfer['minPeriod'] = 0;
        $transfer['maxPeriod'] = 0;
        $transfer['price'] = 0;
        $currencyCode = 'AMD';
        foreach ($extension['plans'] as $key => $value) {
            if ($value['behavior'] == "reactivate") {
                if ($reactivate['minPeriod'] == 0) {
                    $reactivate['minPeriod'] = $value['duration'];
                }
                $reactivate['maxPeriod'] = $value['duration'];
                $reactivate['price'] = ($value['currentPrice'] > 0) ? $value['currentPrice'] : '0';
            } else if ($value['behavior'] == "register") {
                if ($register['minPeriod'] == 0) {
                    $register['minPeriod'] = $value['duration'];
                }
                $register['maxPeriod'] = $value['duration'];
                $register['price'] = ($value['currentPrice'] > 0) ? $value['currentPrice'] : '0';
            } else if ($value['behavior'] == "renew") {
                if ($renew['minPeriod'] == 0) {
                    $renew['minPeriod'] = $value['duration'];
                }
                $renew['maxPeriod'] = $value['duration'];
                $renew['price'] = ($value['currentPrice'] > 0) ? $value['currentPrice'] : '0';
            } else if ($value['behavior'] == "transfer") {
                if ($transfer['minPeriod'] == 0) {
                    $transfer['minPeriod'] = $value['duration'];
                }
                $transfer['maxPeriod'] = $value['duration'];
                $transfer['price'] = ($value['currentPrice'] > 0) ? $value['currentPrice'] : '0';
            }
            // $currencyCode = $value['currency'];
        }
        // if ($reactivate['minPeriod'] == 0) {
        //     continue;
        // }
        $item = (new ImportItem)
            ->setExtension(".".$extension['name'])
            ->setMinYears($register['minPeriod'])
            ->setMaxYears($register['maxPeriod'])
            ->setRegisterPrice($register['price'])
            ->setRenewPrice($renew['price'])
            ->setTransferPrice($transfer['price'])
            ->setRedemptionFeeDays(null)
            ->setRedemptionFeePrice(null)
            ->setCurrency($currencyCode)
            ->setEppRequired(true);

        $results[] = $item;
    }
    return $results;
}

/**
 * Incoming Domain Transfer Sync.
 *
 * Check status of incoming domain transfers and notify end-user upon
 * completion. This function is called daily for incoming domains.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function nameam_TransferSync($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'email' => $apiEmail,
        'password' => $apiPassword,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    print_r('14');
    print_r($params);
    exit;

    try {
        $api = new ApiClient();
        $api->call('CheckDomainTransfer', $postfields);

        if ($api->getFromResponse('transfercomplete')) {
            return array(
                'completed' => true,
                'expirydate' => $api->getFromResponse('expirydate'), // Format: YYYY-MM-DD
            );
        } elseif ($api->getFromResponse('transferfailed')) {
            return array(
                'failed' => true,
                'reason' => $api->getFromResponse('failurereason'), // Reason for the transfer failure if available
            );
        } else {
            // No status change, return empty array
            return array();
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Client Area Custom Button Array.
 *
 * Allows you to define additional actions your module supports.
 * In this example, we register a Push Domain action which triggers
 * the `nameam_push` function when invoked.
 *
 * @return array
 */
/*function nameam_ClientAreaCustomButtonArray()
{
    return array(
        'Push Domain' => 'push',
    );
}*/

/**
 * Client Area Allowed Functions.
 *
 * Only the functions defined within this function or the Client Area
 * Custom Button Array can be invoked by client level users.
 *
 * @return array
 */
/*function nameam_ClientAreaAllowedFunctions()
{
    return array(
        'Push Domain' => 'push',
    );
}*/

/**
 * Example Custom Module Function: Push
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/*function nameam_push($params)
{
    // user defined configuration values
    $apiEmail = $params['Email'];
    $apiPassword = $params['Password'];
    $testMode = $params['Test Mode'];
    $accountMode = $params['Account Mode'];
    $emailPreference = $params['Email Preference'];
    $additionalInfo = $params['Additional Information'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Perform custom action here...

    return 'Not implemented';
}*/

/**
 * Client Area Output.
 *
 * This function renders output to the domain details interface within
 * the client area. The return should be the HTML to be output.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return string HTML Output
 */
/*function nameam_ClientArea($params)
{
    $output = '
        <div class="alert alert-info">
            Your custom HTML output goes here...
        </div>
    ';

    return $output;
}*/

#!/usr/bin/env php
<?php

// Load necessary functions
require_once __DIR__ . '/functions.php';

outputStdout("=============================================");
outputStdout(sprintf("Running dynamic DNS client for netcup %s", VERSION));
outputStdout("This script is not affiliated with netcup.");
outputStdout("=============================================\n");

if (! _is_curl_installed()) {
    outputStderr("cURL PHP extension is not installed. Please install the cURL PHP extension, otherwise the script will not work. Exiting.");
    exit(1);
}

if (!defined('USE_IPV4')) {
    outputWarning("USE_IPV4 not defined in config.php. Assuming that IPv4 should be used to support deprecated legacy configs. Please add USE_IPV4 to your config.php, as in config.dist.php");
    define('USE_IPV4', true);
}

if (!defined('USE_IPV6')) {
    define('USE_IPV6', false);
}

if (USE_IPV4 === false && USE_IPV6 === false) {
    outputStderr("IPv4 as well as IPv6 is disabled in config.php. Please activate either IPv4 or IPv6 in config.php. I do not know what I am supposed to do. Exiting.");
    exit(1);
}

if (!defined('IPV4_ADDRESS_URL')) {
    define('IPV4_ADDRESS_URL', 'https://get-ipv4.steck.cc');
}

if (!defined('IPV4_ADDRESS_URL_FALLBACK')) {
    define('IPV4_ADDRESS_URL_FALLBACK', 'https://ipv4.seeip.org');
}

if (!defined('IPV6_ADDRESS_URL')) {
    define('IPV6_ADDRESS_URL', 'https://get-ipv6.steck.cc');
}

if (!defined('IPV6_ADDRESS_URL_FALLBACK')) {
    define('IPV6_ADDRESS_URL_FALLBACK', 'https://v6.ident.me');
}

if (!defined('RETRY_SLEEP')) {
    define('RETRY_SLEEP', 30);
}

if (!defined('JITTER_MAX')) {
    define('JITTER_MAX', 30);
}

if (!defined('CHANGE_TTL')) {
    // Match the value shipped in config.dist.php so users who delete the
    // line get the recommended behaviour rather than the opposite.
    define('CHANGE_TTL', true);
}

if (!defined('CACHE_FILE')) {
    define('CACHE_FILE', __DIR__ . '/cache.json');
}

if (!defined('CLOUDDNS_DYNDNS_APIURL')) {
    define('CLOUDDNS_DYNDNS_APIURL', 'https://customercontrolpanel.de/wsDynDns.php');
}

// Resolve the domain lists early: config errors should surface before any network
// calls, and we need to know whether a CCP DNS API session is required at all.
$domains = getDomains();
$cloudDnsDomains = getCloudDnsDomains();

if (count($domains) === 0 && count($cloudDnsDomains) === 0) {
    outputStderr("Your configuration does not contain any domains to update (DOMAINLIST and DOMAINLIST_CLOUDDNS_DYNDNS are both empty). Exiting.");
    exit(1);
}

if (count($cloudDnsDomains) > 0 && (!defined('CLOUDDNS_DYNDNS_APIKEY') || CLOUDDNS_DYNDNS_APIKEY === '')) {
    outputStderr("You have configured DOMAINLIST_CLOUDDNS_DYNDNS, but CLOUDDNS_DYNDNS_APIKEY is missing or empty. Please create an API key in the netcup CCP (master data > API, section \"API-Keys\" - NOT a Legacy API key) and add it to config.php. Exiting.");
    exit(1);
}

if (count($domains) > 0) {
    $missingCcpConstants = array();
    foreach (array('CUSTOMERNR', 'APIKEY', 'APIPASSWORD') as $ccpConstant) {
        if (!defined($ccpConstant)) {
            $missingCcpConstants[] = $ccpConstant;
        }
    }
    if (count($missingCcpConstants) > 0) {
        outputStderr(sprintf("You have configured domains for the CCP DNS API (DOMAINLIST), but the following required config option(s) are missing: %s. Please add them to config.php, or move your domains to DOMAINLIST_CLOUDDNS_DYNDNS if they are managed through CloudDNS. Exiting.", implode(', ', $missingCcpConstants)));
        exit(1);
    }
}

$domainsInBothLists = array_intersect(array_keys($domains), array_keys($cloudDnsDomains));
if (count($domainsInBothLists) > 0) {
    outputWarning(sprintf("The following domain(s) are configured in DOMAINLIST as well as DOMAINLIST_CLOUDDNS_DYNDNS: %s. A domain should only be listed in one of the two - the CCP DNS API cannot manage CloudDNS-managed domains and vice versa.", implode(', ', $domainsInBothLists)));
}

if (USE_IPV4 === true) {
    // Get current IPv4 address
    if (!$publicIPv4 = getCurrentPublicIPv4()) {
        outputStderr("Main API and fallback API didn't return a valid IPv4 address (Try 3 / 3). Exiting.");
        exit(1);
    }
}

if (USE_IPV6 === true) {
    //Get current IPv6 address
    if (!$publicIPv6 = getCurrentPublicIPv6()) {
        outputStderr("Main API and fallback API didn't return a valid IPv6 address (Try 3 / 3). Do you have IPv6 connectivity? If not, please disable USE_IPV6 in config.php. Exiting.");
        exit(1);
    }
}

// Compute a fingerprint of the config values that affect what the script does.
// If any of these change (e.g. new subdomain added), the cache is automatically
// invalidated so the script runs a full update.
$configFingerprint = md5(json_encode(array(
    'domainlist' => defined('DOMAINLIST') ? DOMAINLIST : (defined('DOMAIN') && defined('HOST') ? DOMAIN . ':' . HOST : ''),
    'domainlist_clouddns' => defined('DOMAINLIST_CLOUDDNS_DYNDNS') ? DOMAINLIST_CLOUDDNS_DYNDNS : '',
    'use_ipv4' => USE_IPV4,
    'use_ipv6' => USE_IPV6,
    'change_ttl' => CHANGE_TTL,
)));

// Check if IP has changed since last run (cache)
if (!isset($forceUpdate) || $forceUpdate !== true) {
    if (file_exists(CACHE_FILE)) {
        $cacheContents = file_get_contents(CACHE_FILE);
        $cache = ($cacheContents !== false) ? json_decode($cacheContents, true) : null;
        if ($cache !== null) {
            $cacheMatch = true;

            if (!isset($cache['config_hash']) || $cache['config_hash'] !== $configFingerprint) {
                $cacheMatch = false;
            }
            if (USE_IPV4 === true && (!isset($cache['ipv4']) || $cache['ipv4'] !== $publicIPv4)) {
                $cacheMatch = false;
            }
            if (USE_IPV6 === true && (!isset($cache['ipv6']) || $cache['ipv6'] !== $publicIPv6)) {
                $cacheMatch = false;
            }

            if ($cacheMatch) {
                outputStdout("IP address hasn't changed since last run (cached). Skipping update. Use --force to update anyway.");
                exit(0);
            }
        }
    }
} else {
    outputStdout("Force mode enabled. Bypassing IP cache.");
}

// Apply jitter to spread API load across time
if (JITTER_MAX > 0) {
    $jitterSeconds = rand(1, JITTER_MAX);
    outputStdout(sprintf("Waiting %d second%s (jitter) to spread API load...", $jitterSeconds, $jitterSeconds === 1 ? '' : 's'));
    sleep($jitterSeconds);
} else {
    outputWarning("Jitter is disabled. To reduce load on the DNS API, please consider enabling it (JITTER_MAX in config).");
}

// Domains managed through the CCP DNS API. When none are configured (pure-CloudDNS
// setup), no CCP session is opened and the CCP credentials are not needed at all.
if (count($domains) > 0) {
    // Login
    if ($apisessionid = login(CUSTOMERNR, APIKEY, APIPASSWORD)) {
        outputStdout("Logged in successfully!");
    } else {
        exit(1);
    }

    foreach ($domains as $domain => $subdomains) {
        outputStdout(sprintf('Beginning work on domain "%s"', $domain));

        // Let's get infos about the DNS zone
        if ($infoDnsZone = infoDnsZone($domain, CUSTOMERNR, APIKEY, $apisessionid)) {
            outputStdout("Successfully received Domain info.");
        } else {
            exit(1);
        }
        //TTL Warning
        if (CHANGE_TTL !== true && $infoDnsZone['responsedata']['ttl'] > 300) {
            outputStdout("TTL is higher than 300 seconds - this is not optimal for dynamic DNS, since DNS updates will take a long time. Ideally, change TTL to lower value. You may set CHANGE_TTL to True in config.php, in which case TTL will be set to 300 seconds automatically.");
        }

        //If user wants it, then we lower TTL, in case it doesn't have correct value
        if (CHANGE_TTL === true && $infoDnsZone['responsedata']['ttl'] !== "300") {
            $infoDnsZone['responsedata']['ttl'] = 300;

            if (updateDnsZone($domain, CUSTOMERNR, APIKEY, $apisessionid, $infoDnsZone['responsedata'])) {
                outputStdout("Lowered TTL to 300 seconds successfully.");
            } else {
                outputStderr("Failed to set TTL... Continuing.");
            }
        }

        //Let's get the DNS record data.
        if ($infoDnsRecords = infoDnsRecords($domain, CUSTOMERNR, APIKEY, $apisessionid)) {
            outputStdout("Successfully received DNS record data.");
        } else {
            exit(1);
        }

        foreach ($subdomains as $subdomain) {
            outputStdout(sprintf('Updating DNS records for subdomain "%s" of domain "%s".', $subdomain, $domain));

            if (USE_IPV4 === true) {
                updateDnsRecordsForIP($infoDnsRecords, $subdomain, $domain, $apisessionid, 'A', $publicIPv4);
            }

            if (USE_IPV6 === true) {
                updateDnsRecordsForIP($infoDnsRecords, $subdomain, $domain, $apisessionid, 'AAAA', $publicIPv6);
            }
        }
    }

    //Logout
    if (logout(CUSTOMERNR, APIKEY, $apisessionid)) {
        outputStdout("Logged out successfully!");
    } else {
        exit(1);
    }
}

// CloudDNS-managed domains use netcup's separate DynDNS webservice. One request per
// fqdn carries the A and/or AAAA update. The API sets the TTL of created/updated
// records to 300 seconds itself; CHANGE_TTL does not apply here.
foreach ($cloudDnsDomains as $domain => $subdomains) {
    outputStdout(sprintf('Beginning work on CloudDNS domain "%s" (via the CloudDNS DynDNS API).', $domain));

    foreach ($subdomains as $subdomain) {
        $fqdn = buildCloudDnsFqdn($subdomain, $domain);
        $updateSucceeded = updateCloudDnsDynDns(
            $fqdn,
            USE_IPV4 === true ? $publicIPv4 : null,
            USE_IPV6 === true ? $publicIPv6 : null
        );
        if (!$updateSucceeded) {
            exit(1);
        }
    }
}

// Write IP cache for next run
$cacheData = array();
$cacheData['config_hash'] = $configFingerprint;
if (USE_IPV4 === true) {
    $cacheData['ipv4'] = $publicIPv4;
}
if (USE_IPV6 === true) {
    $cacheData['ipv6'] = $publicIPv6;
}
if (file_put_contents(CACHE_FILE, json_encode($cacheData)) === false) {
    outputWarning(sprintf('Could not write cache file "%s". Caching will not work until this is resolved.', CACHE_FILE));
}

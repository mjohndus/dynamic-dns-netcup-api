<?php

// Enter your netcup customer number here.
// [Only needed for domains using the classic CCP DNS API (DOMAINLIST). If ALL your domains
// are managed through CloudDNS (DOMAINLIST_CLOUDDNS_DYNDNS below), you can remove this.]
define('CUSTOMERNR', '12345');


// Enter your API password and API key here - you can generate them in your CCP at
// https://www.customercontrolpanel.de under master data > API, in the "Legacy-API-Keys"
// section (netcup now calls these "Legacy" because they only work with the classic DNS API).
// [Only needed for domains using the classic CCP DNS API (DOMAINLIST). If ALL your domains
// are managed through CloudDNS (DOMAINLIST_CLOUDDNS_DYNDNS below), you can remove these.]
define('APIPASSWORD', 'abcdefghijklmnopqrstuvwxyz');
define('APIKEY', 'abcdefghijklmnopqrstuvwxyz');


// Define domains and subdomains which should be used for dynamic DNS in the following format:
// domain.tld: host1, host2, host3; domain2.tld: host1, host4, *, @
// Start with the domain (without subdomain), add ':' after the domain, then add as many subdomains as you want, separated by ','.
// To add another domain, finish with ';'.
// Whitespace (spaces and newlines) are ignored. If you have a very complicated configuration, you may want to use multiple lines. Feel free to do so!
// If one of the subdomains does not exist, the script will create them for you.
// Subdomain configuration: Use '@' for the domain without subdomain. Use '*' for wildcard: All subdomains (except ones already defined in DNS).
define('DOMAINLIST', 'myfirstdomain.com: server, dddns; myseconddomain.com: @, *, some-subdomain');


// The old format for configuring domain + host is still supported, but deprecated. I recommend to switch to the above config,
// as it allows you to define multiple domains + subdomains.

// Enter Domain which should be used for dynamic DNS.
// define('DOMAIN', 'mydomain.com');
// Enter subdomain to be used for dynamic DNS, alternatively '@' for domain root or '*' for wildcard. If the record doesn't exist, the script will create it.
// define('HOST', 'server');


// netcup is migrating domains to its new "CloudDNS" system. CloudDNS-managed domains can
// NOT be updated through the classic CCP DNS API anymore - the API then wrongly reports
// that the zone contains no DNS records (statuscode 5029). See
// https://github.com/stecklars/dynamic-dns-netcup-api/issues/42 for more information.
// List CloudDNS-managed domains here instead of in DOMAINLIST; they will be updated through
// netcup's CloudDNS DynDNS webservice. Same format as DOMAINLIST. Use '@' for the domain
// root and '*' for wildcard.
// Notes:
// - The DynDNS API automatically sets the TTL of records it creates or updates to
//   300 seconds (ideal for dynamic DNS), so no manual TTL setup is needed. The
//   CHANGE_TTL option does not apply to these domains.
// - A domain should be listed EITHER here OR in DOMAINLIST, never in both.
// [Optional; if ALL your domains are CloudDNS-managed, you can remove DOMAINLIST, CUSTOMERNR,
// APIKEY and APIPASSWORD entirely.]
// define('DOMAINLIST_CLOUDDNS_DYNDNS', 'myclouddomain.com: @, home');

// API key for the CloudDNS DynDNS webservice. This is a regular (new-style) netcup API key,
// created in your CCP under master data > API in the "API-Keys" section. It is NOT the
// Legacy API key used for APIKEY above - netcup lists the two types separately.
// [Required if DOMAINLIST_CLOUDDNS_DYNDNS is set.]
// define('CLOUDDNS_DYNDNS_APIKEY', 'your-api-key');

// URL of the netcup CloudDNS DynDNS webservice.
// [Optional; will be set to default value 'https://customercontrolpanel.de/wsDynDns.php' if missing.]
// define('CLOUDDNS_DYNDNS_APIURL', 'https://customercontrolpanel.de/wsDynDns.php');


// Enter an URL to use to determine the public IPv4 address.
// [Optional; will be set to default value 'https://get-ipv4.steck.cc' if missing.]
define('IPV4_ADDRESS_URL', 'https://get-ipv4.steck.cc');

// Enter an URL to use as fallback to determine the public IPv4 address.
// [Optional; will be set to default value 'https://ipv4.seeip.org' if missing.]
define('IPV4_ADDRESS_URL_FALLBACK', 'https://ipv4.seeip.org');

// Enter an URL to use to determine the public IPv6 address.
// [Optional; will be set to default value 'https://get-ipv6.steck.cc' if missing.]
define('IPV6_ADDRESS_URL', 'https://get-ipv6.steck.cc');

// Enter an URL to use as fallback to determine the public IPv6 address.
// [Optional; will be set to default value 'https://v6.ident.me' if missing.]
define('IPV6_ADDRESS_URL_FALLBACK', 'https://v6.ident.me');


// If set to true, the script will check for your public IPv4 address and add it as an A-Record / change an existing A-Record for the host.
// You may want to deactivate this, for example, when using a carrier grade NAT (CGNAT).
// Most likely though, you should keep this active, unless you know otherwise.
define('USE_IPV4', true);

// If set to true, the script will check for your public IPv6 address too and add it as an AAAA-Record / change an existing AAAA-Record for the host.
// Activate this only if you have IPv6 connectivity, or you *WILL* get errors.
define('USE_IPV6', false);


// If set to true, this will change TTL to 300 seconds on every run if necessary.
define('CHANGE_TTL', true);


// Seconds to wait between retries on network errors or invalid responses.
// [Optional; will be set to default value 30 if missing.]
// define('RETRY_SLEEP', 30);


// Maximum random delay in seconds (1 to JITTER_MAX) applied before API calls.
// This spreads load on the DNS API when many users run the script via cron at the same time.
// Set to 0 to disable (not recommended).
// [Optional; will be set to default value 30 if missing.]
define('JITTER_MAX', 30);


// Path to the IP cache file. The script caches the current IP address after
// a successful update. On subsequent runs, if the IP hasn't changed, the
// script skips the DNS API entirely. Use --force to bypass the cache.
// [Optional; will be set to default value '__DIR__/cache.json' if missing.]
// define('CACHE_FILE', __DIR__ . '/cache.json');


// Use netcup DNS REST-API.
define('APIURL', 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON');

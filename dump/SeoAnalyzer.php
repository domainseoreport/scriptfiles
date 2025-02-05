<?php
namespace App\Libraries;

use CodeIgniter\Cache\CacheInterface;
use App\Models\ApiKeyModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use DOMDocument;
use DOMXPath;
use CodeIgniter\Log\Logger;

class SeoAnalyzer
{
    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var Client
     */
    private Client $httpClient;

    /**
     * @var ApiKeyModel
     */
    private ApiKeyModel $apiKeyModel;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var string
     */
    private string $googleApiKey = '';

    /**
     * @var string
     */
    private string $safeBrowsingGoogleApiKey = '';

    /**
     * @var int
     */
    private int $maxRetries;

    /**
     * @var int
     */
    private int $retryDelay;

    /**
     * Store pre-fetched HTML so we can avoid multiple fetch attempts.
     *
     * @var array
     */
    private array $htmlCache = [];

    /**
     * Store pre-fetched headers.
     *
     * @var array
     */
    private array $cachedHeaders = [];

    /**
     * To keep track of IP addresses tested in the IP Canonicalization check.
     */
    private array $checkedIPs = [];

    /**
     * Constructor
     *
     * Initializes cache, HTTP client, logger, and retrieves the Google API keys.
     * Configures a retry middleware stack for Guzzle.
     *
     * @throws \Exception If Google API Key for PageSpeed is not set.
     */
    public function __construct()
    {
        // Initialize cache, API key model and logger from CodeIgniter services.
        $this->cache = \Config\Services::cache();
        $this->apiKeyModel = new ApiKeyModel();
        $this->logger = \Config\Services::logger();

        // Retrieve Google API keys from environment variables or database.
        $this->googleApiKey = getenv('GOOGLE_API_KEY') ?: $this->getActiveApiKey('pagespeed');
        $this->safeBrowsingGoogleApiKey = getenv('GOOGLE_SAFE_BROWSING_API_KEY') ?: $this->getActiveApiKey('safe_browsing');

        if (empty($this->googleApiKey)) {
            throw new \Exception("Google API Key is not set. Please set 'GOOGLE_API_KEY' or add an active 'pagespeed' API key in the 'api_keys' table.");
        }

        // Set retry parameters.
        $this->maxRetries = 3;
        $this->retryDelay = 2; // seconds

        // Set up Guzzle client with retry middleware.
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            function (
                $retries,
                RequestInterface $request,
                ResponseInterface $response = null,
                \Exception $exception = null
            ) {
                if ($retries >= $this->maxRetries) {
                    return false;
                }
                if ($exception instanceof ConnectException) {
                    $this->logger->warning("Connection exception encountered (retry {$retries}/{$this->maxRetries}).");
                    return true;
                }
                if ($response && $response->getStatusCode() >= 500) {
                    $this->logger->warning("5xx server error (retry {$retries}/{$this->maxRetries}).");
                    return true;
                }
                return false;
            },
            function ($retries) {
                return $this->retryDelay * 1000 * pow(2, $retries - 1); // Exponential backoff.
            }
        ));

        $this->httpClient = new Client([
            'timeout' => 60,
            'connect_timeout' => 20,
            'handler' => $handlerStack,
            'headers' => [
                'User-Agent' => 'SEOAnalyzerBot/1.0',
                'Accept-Encoding' => 'gzip, deflate',
            ],
            'verify' => true, // Enable SSL verification.
        ]);
    }

    /**
     * Analyze the given URL for SEO metrics.
     *
     * @param string $url The URL to analyze.
     * @param array $options Optional parameters (skip_protocol_checks, force_refresh).
     * @return array The analysis report data.
     */
    public function analyze(string $url, array $options = []): array
    {
        $reportData = [];
        $forceRefresh = $options['force_refresh'] ?? false;
        $skipProtocolChecks = $options['skip_protocol_checks'] ?? false;

        if (!$skipProtocolChecks) {
            $accessibleUrl = $this->isDomainAccessible(parse_url($url, PHP_URL_HOST));
            if (!$accessibleUrl) {
                return ['error' => 'Unable to determine accessible URL.'];
            }
            $url = $accessibleUrl;
        } else {
            log_message('debug', "Skipping protocol checks for URL: {$url}");
        }

        if (!$this->isValidUrl($url)) {
            $this->logger->error("Invalid or unreachable URL provided: {$url}");
            return ['error' => 'Invalid or unreachable URL provided. Please ensure the URL is correct and accessible.'];
        }

        $cacheKey = 'seo_analyze_' . md5($url);
        if (!$forceRefresh) {
            if ($cached = $this->cache->get($cacheKey)) {
                $this->logger->info("Returning cached SEO analysis for URL: {$url}");
                return $cached;
            }
        }

        $this->logger->info("Starting SEO analysis for URL: {$url}");
        [$html, $headers] = $this->fetchHtml($url);
        if (empty($html)) {
            $this->logger->error("Unable to fetch HTML content for URL: {$url}");
            return ['error' => 'Unable to fetch HTML content for the provided URL.'];
        }
        if (!$this->isWorkingDomain($html)) {
            $this->logger->error("URL appears to be parked or expired: {$url}");
            return ['error' => 'URL appears to be parked, expired, or not a working site.'];
        }

        $reportData = ['url' => $url, 'analysis_steps' => []];

        try {
            // Basic Meta Tags Extraction.
            $this->logger->info("Step: Basic Meta Tags Extraction");
            $basicMeta = $this->extractBasicMetaTags($html);
            $reportData = array_merge($reportData, $basicMeta);
            $reportData['analysis_steps']['Basic Meta Tags'] = 'Done';

            // Headings Analysis.
            $this->logger->info("Step: Headings Analysis");
            $headings = $this->checkHeadings($html);
            $reportData['headings'] = $headings;
            $reportData['analysis_steps']['Headings'] = 'Done';

            // Alt Attributes.
            $this->logger->info("Step: Images Alt Attributes");
            $dom = $this->createDomDocument($html);
            $altAttributes = $this->checkAltAttributes($dom);
            $reportData['alt_attributes'] = $altAttributes;
            $reportData['analysis_steps']['Images Alt'] = 'Done';

            // Link Analysis.
            $this->logger->info("Step: Link Analysis (Broken/Redirects)");
            $domForLinks = $this->createDomDocument($html);
            $linkAnalysis = $this->analyzeLinks($domForLinks, $url);
            $reportData['link_analysis'] = $linkAnalysis;
            $reportData['analysis_steps']['Links'] = 'Done';

            // Keyword Analysis.
            $this->logger->info("Step: Keyword Analysis");
            $domKeywords = $this->createDomDocument($html);
            $keywordAnalysis = $this->analyzeHeadingsAndKeywords($domKeywords);
            $reportData['keyword_analysis'] = $keywordAnalysis;
            $reportData['analysis_steps']['Keywords'] = 'Done';

            // Keyword Cloud.
            $this->logger->info("Step: Keyword Cloud");
            $domCloud = $this->createDomDocument($html);
            $keywordCloud = $this->generateKeywordCloud($domCloud);
            $reportData['keyword_cloud'] = $keywordCloud;
            $reportData['analysis_steps']['Keyword Cloud'] = 'Done';

            // Schema Data.
            $this->logger->info("Step: Schema Data Extraction");
            $domSchema = $this->createDomDocument($html);
            $schemaInfo = $this->checkSchema($domSchema);
            $reportData['schema_data'] = $schemaInfo;
            $reportData['analysis_steps']['Schema'] = 'Done';

            // Mobile Friendliness.
            $this->logger->info("Step: Mobile Friendliness Check");
            $domMobile = $this->createDomDocument($html);
            $mobileFriendly = $this->checkMobileFriendly($domMobile);
            $reportData['mobile_friendly'] = $mobileFriendly;
            $reportData['analysis_steps']['Mobile'] = 'Done';

            // OpenGraph.
            $this->logger->info("Step: OpenGraph Tags");
            $domOg = $this->createDomDocument($html);
            $openGraph = $this->checkOpenGraph($domOg);
            $reportData['open_graph'] = $openGraph;
            $reportData['analysis_steps']['OpenGraph'] = 'Done';

            // Missing OG Tags.
            $this->logger->info("Step: Missing OG Tags");
            $domMissingOg = $this->createDomDocument($html);
            $missingOg = $this->checkMissingOpenGraphTags($domMissingOg);
            $reportData['missing_open_graph_tags'] = $missingOg;
            $reportData['analysis_steps']['Missing OG'] = 'Done';

            // Safe Browsing.
            $this->logger->info("Step: Safe Browsing");
            $safeBrowsing = $this->checkSafeBrowsing($url);
            $reportData['safe_browsing'] = $safeBrowsing;
            $reportData['analysis_steps']['Safe Browsing'] = 'Done';

            // PageSpeed Insights.
            $this->logger->info("Step: PageSpeed Insights");
            // Cache Google PageSpeed data for 24 hours (86400 seconds) as performance metrics rarely change.
            $pageSpeed = $this->checkPageSpeed($url);
            $reportData['pagespeed'] = $pageSpeed;
            $reportData['analysis_steps']['PageSpeed'] = 'Done';

            // Text/HTML Ratio.
            $this->logger->info("Step: Text/HTML Ratio");
            $textHtmlStats = $this->calculateTextHtmlRatioExtended($url);
            $reportData['text_html_stats'] = $textHtmlStats;
            $reportData['analysis_steps']['Text/HTML Ratio'] = 'Done';

            // Google Analytics.
            $this->logger->info("Step: Google Analytics Check");
            $analytics = $this->checkAnalytics($html);
            $reportData['analytics'] = $analytics;
            $reportData['analysis_steps']['Analytics'] = 'Done';

            // Technology Detection.
            $this->logger->info("Step: Technology Detection");
            $technologies = $this->detectFromHtml($html, $headers, $url);
            $reportData['technologies'] = $technologies;
            $reportData['analysis_steps']['Technologies'] = 'Done';

            // WWW Resolution.
            $this->logger->info("Step: WWW Resolution");
            $wwwResolve = $this->checkWWWResolve($url);
            $reportData['www_resolve'] = $wwwResolve;
            $reportData['analysis_steps']['WWW Resolve'] = 'Done';

            // IP Canonicalization.
            $this->logger->info("Step: IP Canonical Check");
            try {
                $ipCanonical = $this->checkIPCanonicalization($url);
                $reportData['ip_canonical'] = [
                    'status'        => $ipCanonical,
                    'message'       => $ipCanonical 
                                        ? 'All IP addresses correctly redirect to domain name'
                                        : 'Potential issue: Direct IP access detected (no redirect to domain)',
                    'recommendation'=> 'Ensure all IP addresses redirect to your canonical domain',
                    'checked_ips'   => $this->getCheckedIPs()
                ];
                $this->logger->debug("Checked IPs: " . print_r($this->getCheckedIPs(), true));
                $reportData['analysis_steps']['IP Canonical'] = $ipCanonical ? 'Passed' : 'Failed';
            } catch (\InvalidArgumentException $e) {
                $this->logger->error("IP Canonicalization Input Error: " . $e->getMessage());
                $reportData['ip_canonical'] = [
                    'status'        => 'error',
                    'message'       => "Invalid configuration: " . $e->getMessage(),
                    'resolution'    => 'Verify the input URL format'
                ];
                $reportData['analysis_steps']['IP Canonical'] = 'Error (Invalid input)';
            } catch (\RuntimeException $e) {
                $this->logger->error("IP Canonicalization DNS Error: " . $e->getMessage());
                $reportData['ip_canonical'] = [
                    'status'        => 'error',
                    'message'       => "DNS lookup failed: " . $e->getMessage(),
                    'resolution'    => 'Check domain DNS configuration'
                ];
                $reportData['analysis_steps']['IP Canonical'] = 'Error (DNS issue)';
            } catch (\Exception $e) {
                $this->logger->critical("IP Canonicalization Check Failed: " . $e->getMessage());
                $reportData['ip_canonical'] = [
                    'status'        => 'error',
                    'message'       => "Check failed: " . $e->getMessage(),
                    'resolution'    => 'Verify server configuration and network connectivity'
                ];
                $reportData['analysis_steps']['IP Canonical'] = 'Error (Check failed)';
            }

            // DOCTYPE.
            $this->logger->info("Step: DOCTYPE Check");
            $domDocType = $this->createDomDocument($html);
            $docTypeResult = $this->checkDocType($domDocType);
            $reportData['doctype'] = $docTypeResult;
            $reportData['analysis_steps']['DocType'] = $docTypeResult['doctype_correct'] ? 'Passed' : 'Failed';

            // Charset.
            $this->logger->info("Step: Charset Check");
            $domCharset = $this->createDomDocument($html);
            $charsetResult = $this->checkCharset($domCharset);
            $reportData['charset'] = $charsetResult;
            $reportData['analysis_steps']['Charset'] = $charsetResult['charset_correct'] ? 'Passed' : 'Failed';

            // DNS Info.
            $this->logger->info("Step: DNS Records");
            $parsedUrl = parse_url($url);
            $domain = $parsedUrl['host'] ?? '';
            $dnsInfo = $this->checkDNSRecords($domain);
            $reportData['dns_info'] = $dnsInfo;
            $reportData['analysis_steps']['DNS'] = 'Done';

            // IP Info.
            $this->logger->info("Step: IP Info");
            $ipInfo = $this->checkIP($domain);
            $reportData['ip_info'] = $ipInfo;
            $reportData['analysis_steps']['IP'] = 'Done';

            // SSL.
            $this->logger->info("Step: SSL Check");
            $sslInfo = $this->checkSSL($domain);
            $reportData['ssl_info'] = $sslInfo;
            $reportData['analysis_steps']['SSL'] = 'Done';

            // robots.txt.
            $this->logger->info("Step: robots.txt");
            $robotsTxt = $this->checkRobotsTxt($domain);
            $reportData['robots_txt'] = $robotsTxt;
            $reportData['analysis_steps']['robots.txt'] = 'Done';

            // sitemap.xml.
            $this->logger->info("Step: sitemap.xml");
            $xmlSitemap = $this->checkXmlSitemap($domain);
            $reportData['xml_sitemap'] = $xmlSitemap;
            $reportData['analysis_steps']['Sitemap'] = 'Done';

            // Meta Robots.
            $this->logger->info("Step: Meta Robots");
            $domMeta = $this->createDomDocument($html);
            $metaRobots = $this->checkMetaRobots($domMeta);
            $reportData['meta_robots'] = $metaRobots;
            $reportData['analysis_steps']['Meta Robots'] = 'Done';

            // GZIP.
            $this->logger->info("Step: GZIP Check");
            $gzipStatus = $this->checkGzip($url);
            $reportData['gzip_enabled'] = $gzipStatus;
            $reportData['analysis_steps']['GZIP'] = 'Done';

            // W3C Validation.
            $this->logger->info("Step: W3C Validation");
            $domW3C = $this->createDomDocument($html);
            $w3cValidation = $this->checkW3CValidator($domW3C);
            $reportData['w3c_validation'] = $w3cValidation;
            $reportData['analysis_steps']['W3C'] = 'Done';

            // Additional SEO Checks.
            $this->logger->info("Step: Additional SEO Checks");
            $reportData['url_rewrite'] = $this->checkUrlRewrite($url);
            $reportData['underscores'] = $this->checkUnderscoresInUrl($url);
            $reportData['embedded_objects'] = $this->checkEmbeddedObjects($html);
            $reportData['iframes'] = $this->checkIframes($html);
            $reportData['favicon'] = $this->checkFavicon($html, $url);
            $reportData['custom_404'] = $this->checkCustom404($url);
            $reportData['language'] = $this->checkLanguage($html);
            $reportData['email_privacy'] = $this->checkEmailPrivacy($html);
            $reportData['mobile_compatibility'] = $this->checkMobileCompatibility($html);
            $reportData['social_urls'] = $this->checkSocialUrls($html);
            $reportData['canonical_tag'] = $this->checkCanonicalTag($html);
            $reportData['hreflang_tags'] = $this->checkHreflangTags($html);
            $reportData['security_headers'] = $this->checkSecurityHeaders($url);

            $reportData['keyword_consistency'] = $this->checkKeywordConsistency($html);
            $reportData['structured_data_validation'] = $this->validateStructuredData($this->createDomDocument($html));
            $reportData['accessibility'] = $this->checkAccessibility($html);
            $reportData['readability'] = $this->analyzeContentReadability($html);

            $reportData['dom_size'] = $this->checkDomSize($html);
            $reportData['page_cache'] = $this->checkPageCache($url);
            $reportData['cdn_usage'] = $this->checkCdnUsage($html);
            $reportData['ttfb'] = $this->checkTTFB($url);
            $reportData['server_signature'] = $this->checkServerSignature($url);
            $reportData['directory_browsing'] = $this->checkDirectoryBrowsing($url);
            $reportData['unsafe_cross_origin'] = $this->checkUnsafeCrossOriginLinks($html);
            $reportData['noindex_tag'] = $this->checkNoindexTag($html);
            $reportData['nofollow_tag'] = $this->checkNofollowTag($html);
            $reportData['disallow_directives'] = $this->checkDisallowDirective(parse_url($url, PHP_URL_HOST));
            $reportData['meta_refresh'] = $this->checkMetaRefresh($html);
            $reportData['spf_records'] = $this->checkSpfRecords(parse_url($url, PHP_URL_HOST));
            $reportData['ads_txt'] = $this->checkAdsTxtValidation(parse_url($url, PHP_URL_HOST));


            // When processing robots.txt:
            $robotsContent = @file_get_contents($this->ensureHttpProtocol($domain) . '/robots.txt');
            if ($robotsContent !== false) {
                $parsedRobots = $this->parseRobotsTxt($robotsContent);
                $robotsAnalysis = $this->analyzeRobotsTxt($parsedRobots, $url);
                $reportData['robots_analysis'] = $robotsAnalysis;
            }

            // For Twitter Cards:
            $domForTwitter = $this->createDomDocument($html);
            $twitterData = $this->checkTwitterCards($domForTwitter);
            $missingTwitter = $this->checkMissingTwitterTags($domForTwitter);
            $reportData['twitter_cards'] = $twitterData;
            $reportData['missing_twitter_tags'] = $missingTwitter;

            // For domain extraction and RDAP lookup:
            $extractedDomain = $this->extractDomain($url);
            $rdapData = $this->fetchDomainRdap($url);
            $reportData['extracted_domain'] = $extractedDomain;
            $reportData['rdap_data'] = $rdapData; 

            $reportData['analysis_steps']['Additional SEO Checks'] = 'Done';

        } catch (\Exception $e) {
            $this->logger->error("Unexpected error during SEO analysis: {$e->getMessage()}");
            $reportData['error'] = "An unexpected error occurred: " . $e->getMessage();
        }

        // Store in CI cache for 24 hours (86400 seconds)
        $this->cache->save($cacheKey, $reportData, 86400);
        $this->logger->debug("Checked IPs: " . print_r($reportData, true));
        $this->logger->info("SEO analysis completed for URL: {$url}");
        return $reportData;
    }

    /*--------------------------------------------------------------------------------
     |                          FALLBACK HTML FETCH LOGIC                             |
     --------------------------------------------------------------------------------*/

    /**
     * Fetches HTML content (and response headers) using multiple fallback methods: Guzzle -> file_get_contents -> cURL.
     * Caches results in memory to avoid re-fetching.
     *
     * @param string $url The URL to fetch HTML from.
     * @return array Array containing HTML content and response headers.
     */
    private function fetchHtml(string $url): array
    {
        if (isset($this->htmlCache[$url])) {
            return [$this->htmlCache[$url], $this->cachedHeaders[$url]];
        }

        [$html, $headers] = $this->fetchHtmlWithGuzzle($url);
        if (!empty($html)) {
            $this->cacheFetchResult($url, $html, $headers);
            return [$html, $headers];
        }

        [$html, $headers] = $this->fetchHtmlWithFileGetContents($url);
        if (!empty($html)) {
            $this->cacheFetchResult($url, $html, $headers);
            return [$html, $headers];
        }

        [$html, $headers] = $this->fetchHtmlWithCurl($url);
        if (!empty($html)) {
            $this->cacheFetchResult($url, $html, $headers);
            return [$html, $headers];
        }

        return ['', []];
    }

    /**
     * Cache the fetched HTML and headers.
     *
     * @param string $url The URL fetched.
     * @param string $html The HTML content.
     * @param array $headers The response headers.
     */
    private function cacheFetchResult(string $url, string $html, array $headers): void
    {
        $this->htmlCache[$url]     = $html;
        $this->cachedHeaders[$url] = $headers;
    }

    /**
     * Fetch HTML using Guzzle.
     *
     * @param string $url The URL to fetch.
     * @return array Array containing HTML content and response headers.
     */
    private function fetchHtmlWithGuzzle(string $url): array
    {
        try {
            $resp    = $this->httpClient->get($url);
            $body    = (string) $resp->getBody();
            $headers = $resp->getHeaders();
            return [$body, $headers];
        } catch (\Exception $e) {
            $this->logger->error("Guzzle fetch failed for {$url}: " . $e->getMessage());
            return ['', []];
        }
    }

    /**
     * Fetch HTML using file_get_contents.
     *
     * @param string $url The URL to fetch.
     * @return array Array containing HTML content and response headers.
     */
    private function fetchHtmlWithFileGetContents(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'header'  => ["User-Agent: SEOAnalyzerBot/1.0\r\n"],
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ]
        ]);

        try {
            $content = @file_get_contents($url, false, $ctx);
            if ($content === false) {
                return ['', []];
            }
            $rawHeaders = $http_response_header ?? [];
            $headers    = ['raw_headers' => $rawHeaders];
            return [$content, $headers];
        } catch (\Exception $e) {
            $this->logger->error("file_get_contents fallback failed for {$url}: " . $e->getMessage());
            return ['', []];
        }
    }

    /**
     * Fetch HTML using cURL.
     *
     * @param string $url The URL to fetch.
     * @return array Array containing HTML content and response headers.
     */
    private function fetchHtmlWithCurl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_USERAGENT      => 'SEOAnalyzerBot/1.0',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER         => true,
        ]);

        $response   = curl_exec($ch);
        $error      = curl_error($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error("cURL error for {$url}: {$error}");
            return ['', []];
        }
        if ($httpCode >= 400) {
            $this->logger->warning("cURL received HTTP status code {$httpCode} for {$url}");
            return ['', []];
        }

        $headerBlock = substr($response, 0, $headerSize);
        $body        = substr($response, $headerSize);

        $headersArr = [];
        $lines = explode("\r\n", $headerBlock);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(': ', $line, 2);
                $headersArr[$k][] = $v;
            }
        }
        return [$body, $headersArr];
    }

    /*--------------------------------------------------------------------------------
     |                              UTILITIES / HELPERS                              |
     --------------------------------------------------------------------------------*/

    /**
     * Creates a DOMDocument from an HTML string with enhanced error handling.
     *
     * @param string $html The HTML content.
     * @return DOMDocument The parsed DOMDocument object.
     * @throws InvalidArgumentException If the input HTML is empty or invalid.
     */
    private function createDomDocument(string $html): DOMDocument
    {
        if (empty($html)) {
            throw new \InvalidArgumentException('The provided HTML string must not be empty.');
        }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        try {
            $withXmlDeclaration = '<?xml encoding="UTF-8">' . $html;
            $dom->loadHTML($withXmlDeclaration, LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            return $dom;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to parse the HTML string.', 0, $e);
        }
    }

    /**
     * Validate URL format and domain accessibility.
     *
     * @param string $url The URL to validate.
     * @return bool True if valid and reachable, False otherwise.
     */
    private function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $accessibleUrl = $this->isDomainAccessible(parse_url($url, PHP_URL_HOST));
        return (bool)$accessibleUrl;
    }

    /**
     * Check domain accessibility with protocol/subdomain variations.
     *
     * @param string $domain The domain name.
     * @return string|null The accessible URL or null if not found.
     */
    public function isDomainAccessible(string $domain): ?string
    {
        $protocols = ['https://', 'http://'];
        $timeout = 10;
        $maxRedirects = 5;
        $userAgent = 'Mozilla/5.0 (compatible; SEO-Checker/1.0)';
        $domainParts = explode('.', $domain);
        $checkWWW = (count($domainParts) <= 2);
        $checkedUrls = [];
        $effectiveUrls = [];
        foreach ($protocols as $protocol) {
            $variations = [$protocol . $domain];
            if ($checkWWW) {
                $variations[] = $protocol . 'www.' . $domain;
                $variations[] = $protocol . $domain;
            }
            foreach ($variations as $url) {
                if (in_array($url, $checkedUrls)) continue;
                $checkedUrls[] = $url;
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => $maxRedirects,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => $userAgent,
                    CURLOPT_NOBODY => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $curlError = curl_error($ch);
                $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
                curl_close($ch);
                log_message('debug', "Checked: {$url} => {$effectiveUrl} [{$httpCode}]");
                if (!$curlError && $httpCode >= 200 && $httpCode < 400) {
                    $effectiveUrl = rtrim($effectiveUrl, '/');
                    $effectiveUrls[$effectiveUrl] = [
                        'https' => parse_url($effectiveUrl, PHP_URL_SCHEME) === 'https',
                        'www' => strpos(parse_url($effectiveUrl, PHP_URL_HOST), 'www.') === 0,
                        'redirects' => $redirectCount
                    ];
                }
            }
        }
        uasort($effectiveUrls, function($a, $b) {
            if ($a['https'] !== $b['https']) return $b['https'] <=> $a['https'];
            if ($a['www'] !== $b['www']) return $a['www'] <=> $b['www'];
            return $a['redirects'] <=> $b['redirects'];
        });
        if (!empty($effectiveUrls)) {
            $bestUrl = array_key_first($effectiveUrls);
            log_message('debug', "Selected best URL: {$bestUrl}");
            return $bestUrl;
        }
        log_message('debug', "No accessible URL found for domain: {$domain}");
        return null;
    }

    /**
     * Check if domain is working (not parked).
     *
     * @param string $html The HTML content.
     * @return bool True if working, False otherwise.
     */
    private function isWorkingDomain(string $html): bool
    {
        if (strlen($html) < 300) {
            return false;
        }
        $parkingPhrases = [
            'domain is parked', 'buy this domain', 'this domain is for sale',
            'godaddy placeholder', 'sedoparking.com', 'coming soon', 'domain default page',
            'under construction', 'page not found', 'snapnames.com', 'namecheap parking',
            'domain has expired', 'renew your domain'
        ];
        $lower = strtolower($html);
        foreach ($parkingPhrases as $p) {
            if (strpos($lower, $p) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retrieve an active API key from the database.
     *
     * @param string $serviceName The service name (e.g., 'pagespeed').
     * @return string|null The API key or null if not found.
     */
    private function getActiveApiKey(string $serviceName): ?string
    {
        $key = $this->apiKeyModel
            ->where('service_name', $serviceName)
            ->where('status', 'active')
            ->orderBy('usage_count', 'ASC')
            ->first();

        if ($key) {
            $this->incrementKeyUsage($key['id']);
            return $key['api_key'];
        }
        return null;
    }

    /**
     * Increment the usage count for an API key.
     *
     * @param int $keyId The API key ID.
     */
    private function incrementKeyUsage(int $keyId): void
    {
        $this->apiKeyModel->where('id', $keyId)->increment('usage_count');
    }

    /**
     * Extract Basic Meta Tags.
     *
     * @param string $html The HTML content.
     * @return array Associative array of meta tags.
     */
    private function extractBasicMetaTags(string $html): array
    {
        $title = $this->getTagContent($html, 'title');
        $metaDescription = $this->getMetaTag($html, 'description');
        $metaKeywords = $this->getMetaTag($html, 'keywords');

        return [
            'title'              => $title ?: 'N/A',
            'title_length'       => mb_strlen($title ?? ''),
            'meta_description'   => $metaDescription ?: 'N/A',
            'description_length' => mb_strlen($metaDescription ?? ''),
            'meta_keywords'      => $metaKeywords ?: 'N/A',
        ];
    }

    /**
     * Get content of a specified tag.
     */
    private function getTagContent(string $html, string $tagName): string
    {
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//{$tagName}");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return '';
    }

    /**
     * Get meta tag content.
     */
    private function getMetaTag(string $html, string $name): string
    {
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);
        $meta = $xpath->query("//meta[@name='{$name}']");
        if ($meta->length > 0) {
            return trim($meta->item(0)->getAttribute('content'));
        }
        return '';
    }

    /**
     * Check Headings h1-h6.
     */
    private function checkHeadings(string $html): array
    {
        $result = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);
        for ($i = 1; $i <= 6; $i++) {
            $nodes = $xpath->query("//h{$i}");
            foreach ($nodes as $nd) {
                $text = trim($nd->textContent);
                if ($text !== '') {
                    $result["h{$i}"][] = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }
        return $result;
    }

    /**
     * Check Alt Attributes.
     */
    private function checkAltAttributes(DOMDocument $dom): array
    {
        $results = [
            'total_images' => 0,
            'images_missing_alt' => [],
            'images_with_empty_alt' => [],
            'images_with_short_alt' => [],
            'images_with_long_alt' => [],
            'images_with_redundant_alt' => [],
            'suggestions' => []
        ];
        $xpath = new DOMXPath($dom);
        $imgTags = $xpath->query("//img");
        $results['total_images'] = $imgTags->length;
        foreach ($imgTags as $img) {
            $src = $img->getAttribute('src') ?: 'N/A';
            $alt = $img->getAttribute('alt');
            $title = $img->getAttribute('title') ?: 'N/A';
            $width = $img->getAttribute('width') ?: 'N/A';
            $height = $img->getAttribute('height') ?: 'N/A';
            $class = $img->getAttribute('class') ?: 'N/A';
            $parentTag = $img->parentNode->nodeName;
            $parentTxt = trim($img->parentNode->textContent);
            $position = $this->getNodePosition($img);

            if (!$img->hasAttribute('alt')) {
                $results['images_missing_alt'][] = compact('src','title','width','height','class','parentTag','parentTxt','position');
            } elseif (trim($alt) === '') {
                $results['images_with_empty_alt'][] = compact('src','title','width','height','class','parentTag','parentTxt','position');
            } else {
                $altLength = mb_strlen($alt);
                $normalizedAlt = strtolower($alt);
                $redundantAlt = in_array($normalizedAlt, ['image','photo','picture','logo']);
                if ($altLength < 5) {
                    $results['images_with_short_alt'][] = [
                        'src' => $src,
                        'alt' => $alt,
                        'length' => $altLength,
                        'title' => $title,
                        'width' => $width,
                        'height'=> $height,
                        'class' => $class,
                        'parent_tag' => $parentTag,
                        'parentTxt' => $parentTxt,
                        'position' => $position
                    ];
                }
                if ($altLength > 100) {
                    $results['images_with_long_alt'][] = [
                        'src' => $src,
                        'alt' => $alt,
                        'length' => $altLength,
                        'title' => $title,
                        'width' => $width,
                        'height'=> $height,
                        'class' => $class,
                        'parent_tag' => $parentTag,
                        'parentTxt' => $parentTxt,
                        'position' => $position
                    ];
                }
                if ($redundantAlt) {
                    $results['images_with_redundant_alt'][] = [
                        'src' => $src,
                        'alt' => $alt,
                        'title' => $title,
                        'width' => $width,
                        'height'=> $height,
                        'class' => $class,
                        'parent_tag' => $parentTag,
                        'parentTxt' => $parentTxt,
                        'position' => $position
                    ];
                }
            }
        }
        $totalMissing = count($results['images_missing_alt']);
        $totalEmpty = count($results['images_with_empty_alt']);
        $totalShort = count($results['images_with_short_alt']);
        $totalLong = count($results['images_with_long_alt']);
        $totalRedund = count($results['images_with_redundant_alt']);
        if ($totalMissing > 0) {
            $results['suggestions'][] = "There are {$totalMissing} images missing alt attributes.";
        }
        if ($totalEmpty > 0) {
            $results['suggestions'][] = "There are {$totalEmpty} images with empty alt attributes.";
        }
        if ($totalShort > 0) {
            $results['suggestions'][] = "There are {$totalShort} images with very short alt text (<5 chars).";
        }
        if ($totalLong > 0) {
            $results['suggestions'][] = "There are {$totalLong} images with very long alt text (>100 chars).";
        }
        if ($totalRedund > 0) {
            $results['suggestions'][] = "There are {$totalRedund} images with redundant alt text (e.g., 'image','logo').";
        }
        if ($totalMissing === 0 && $totalEmpty === 0 && $totalShort === 0 && $totalLong === 0 && $totalRedund === 0) {
            $results['suggestions'][] = "Great job! All images have appropriate alt attributes.";
        }
        return $results;
    }

    /**
     * Analyze Links.
     */
    private function analyzeLinks(DOMDocument $dom, string $baseUrl): array
    {
        $results = [
            'total_links' => 0,
            'total_internal_links' => 0,
            'total_external_links' => 0,
            'unique_links' => 0,
            'total_nofollow_links' => 0,
            'total_dofollow_links' => 0,
            'percentage_nofollow_links' => 0,
            'percentage_dofollow_links' => 0,
            'total_target_blank_links' => 0,
            'links_by_position' => [
                'header' => 0, 'nav' => 0, 'main' => 0,
                'footer' => 0, 'aside' => 0, 'section' => 0, 'body' => 0
            ],
            'total_image_links' => 0,
            'total_text_links' => 0,
            'external_domains' => [],
            'unique_external_domains_count' => 0,
            'total_https_links' => 0,
            'total_http_links' => 0,
            'total_tracking_links' => 0,
            'total_non_tracking_links' => 0,
            'average_anchor_text_length' => 0,
            'link_diversity_score' => 0,
        ];
        $xpath = new DOMXPath($dom);
        $anchorTags = $xpath->query("//a[@href]");
        $linksData = [];
        $extDomains = [];
        $totalAnchorLength = 0;
        foreach ($anchorTags as $aTag) {
            $href = trim($aTag->getAttribute('href'));
            if (!$href) continue;
            if (preg_match('/^(mailto|tel|javascript|#):/i', $href)) continue;
            if (!preg_match('#^https?://#i', $href)) {
                $href = $this->resolveUrl($href, $baseUrl);
            }
            $href = $this->normalizeUrl($href);
            $isInternal = $this->isInternalLink($href, $baseUrl);
            $relAttr = strtolower($aTag->getAttribute('rel'));
            $isNofollow = (strpos($relAttr, 'nofollow') !== false);
            $targetAttr = strtolower($aTag->getAttribute('target'));
            $hasTargetBlk = ($targetAttr === '_blank');
            $posCategory = $this->categorizeLinkPosition($aTag);
            $isImageLink = false;
            foreach ($aTag->childNodes as $child) {
                if ($child->nodeName === 'img') {
                    $isImageLink = true;
                    break;
                }
            }
            $anchorText = trim($aTag->textContent);
            $totalAnchorLength += strlen($anchorText);
            $pUrl = parse_url($href);
            if (!$isInternal && isset($pUrl['host'])) {
                $extDomains[] = strtolower($pUrl['host']);
            }
            $linksData[] = [
                'href' => $href,
                'is_internal' => $isInternal,
                'text' => $anchorText,
                'is_nofollow' => $isNofollow,
                'has_target_blank' => $hasTargetBlk,
                'position_category' => $posCategory,
                'is_image_link' => $isImageLink,
                'is_https' => (isset($pUrl['scheme']) && strtolower($pUrl['scheme']) === 'https'),
                'has_tracking_params' => $this->hasTrackingParameters($pUrl),
            ];
        }
        $results['total_links'] = count($linksData);
        $uniqueHref = array_unique(array_column($linksData, 'href'));
        $results['unique_links'] = count($uniqueHref);
        foreach ($linksData as $ld) {
            if ($ld['is_internal']) {
                $results['total_internal_links']++;
            } else {
                $results['total_external_links']++;
            }
            if ($ld['is_nofollow']) {
                $results['total_nofollow_links']++;
            } else {
                $results['total_dofollow_links']++;
            }
            if ($ld['has_target_blank']) {
                $results['total_target_blank_links']++;
            }
            if ($ld['is_image_link']) {
                $results['total_image_links']++;
            } else {
                $results['total_text_links']++;
            }
            if ($ld['is_https']) {
                $results['total_https_links']++;
            } else {
                $results['total_http_links']++;
            }
            if ($ld['has_tracking_params']) {
                $results['total_tracking_links']++;
            } else {
                $results['total_non_tracking_links']++;
            }
            if (isset($results['links_by_position'][$ld['position_category']])) {
                $results['links_by_position'][$ld['position_category']]++;
            } else {
                $results['links_by_position'][$ld['position_category']] = 1;
            }
        }
        $uniqExtDom = array_unique($extDomains);
        $results['external_domains'] = array_values($uniqExtDom);
        $results['unique_external_domains_count'] = count($uniqExtDom);
        $results['average_anchor_text_length'] = ($results['total_links'] > 0)
            ? ($totalAnchorLength / $results['total_links'])
            : 0;
        $results['link_diversity_score'] = ($results['total_external_links'] > 0)
            ? ($results['unique_external_domains_count'] / $results['total_external_links'])
            : 0;
        if ($results['total_links'] > 0) {
            $results['percentage_nofollow_links'] = round(($results['total_nofollow_links'] / $results['total_links']) * 100, 2);
            $results['percentage_dofollow_links'] = round(($results['total_dofollow_links'] / $results['total_links']) * 100, 2);
        }
        return $results;
    }

    /**
     * Analyze Headings & Keywords.
     */
    private function analyzeHeadingsAndKeywords(DOMDocument $dom): array
    {
        $html = $dom->saveHTML();
        $headings = $this->checkHeadings($html);
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is','',$html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is','',$html);
        $html = preg_replace('/<!--(.*?)-->/', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES|ENT_HTML5,'UTF-8');
        $textContent = strip_tags($html);
        $textContent = preg_replace('/&[A-Za-z0-9#]+;/', ' ', $textContent);
        $textContent = strtolower($textContent);
        $textContent = preg_replace('/[^a-z\s]/', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);
        $words = explode(' ', $textContent);
        $words = array_filter($words);
        $totalWords = count($words);
        $stopWords = $this->getStopWords();
        $filteredWords = array_filter($words, function($w) use($stopWords){
            return !in_array($w, $stopWords) && strlen($w) > 2;
        });
        $wordFrequency = array_count_values($filteredWords);
        arsort($wordFrequency);
        $keywords = array_slice(array_keys($wordFrequency), 0, 10);
        $keywordDetails = [];
        foreach ($keywords as $k) {
            $count = $wordFrequency[$k] ?? 0;
            $density = $totalWords > 0 ? ($count / $totalWords) * 100 : 0;
            $keywordDetails[$k] = [
                'count' => $count,
                'density' => round($density, 2),
            ];
        }
        $keywordsInHeadings = [];
        $allH = array_merge(...array_values($headings));
        foreach ($keywords as $k) {
            foreach ($allH as $hText) {
                if (stripos($hText, $k) !== false) {
                    $keywordsInHeadings[] = $k;
                    break;
                }
            }
        }
        $suggestions = [];
        if (empty($headings['h1'])) {
            $suggestions[] = "No H1 found. Consider adding one H1.";
        } elseif (count($headings['h1']) > 1) {
            $suggestions[] = "Multiple H1 found. Usually recommended to have only one.";
        }
        foreach (['h2','h3','h4','h5','h6'] as $lvl) {
            if (empty($headings[$lvl])) {
                $suggestions[] = "No <{$lvl}> tags found. Consider using them for structure.";
            }
        }
        if (empty($keywordsInHeadings)) {
            $suggestions[] = "None of the top keywords appear in headings. Integrate them if relevant.";
        }
        $overused = [];
        $underused = [];
        foreach ($keywordDetails as $k => $val) {
            if ($val['count'] > 5 && $val['density'] > 2) {
                $overused[] = $k;
            }
            if ($val['count'] < 2) {
                $underused[] = $k;
            }
        }
        if (!empty($overused)) {
            $suggestions[] = "Possible overuse of keywords: " . implode(', ', $overused);
        }
        if (!empty($underused)) {
            $suggestions[] = "Possible underuse of keywords: " . implode(', ', $underused);
        }
        return [
            'headings' => $headings,
            'keyword_details' => $keywordDetails,
            'keywords_in_headings' => $keywordsInHeadings,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Generate Keyword Cloud.
     */
    private function generateKeywordCloud(DOMDocument $dom): array
    {
        $html = $dom->saveHTML();
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is','',$html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is','',$html);
        $html = preg_replace('/<!--(.*?)-->/', '', $html);
        $html = html_entity_decode($html, ENT_QUOTES|ENT_HTML5,'UTF-8');
        $textContent = strip_tags($html);
        $textContent = preg_replace('/&[A-Za-z0-9#]+;/', ' ', $textContent);
        $textContent = strtolower($textContent);
        $textContent = preg_replace('/[^a-z\s]/', ' ', $textContent);
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);
        $words = explode(' ', $textContent);
        $words = array_filter($words);
        $stopWords = $this->getStopWords();
        $filtered = array_filter($words, function($w) use ($stopWords){
            return !in_array($w, $stopWords) && strlen($w) > 2;
        });
        $filtered = array_values($filtered);
        $unigrams = $filtered;
        $bigrams = [];
        for ($i=0; $i<count($filtered)-1; $i++){
            $bigrams[] = $filtered[$i].' '.$filtered[$i+1];
        }
        $trigrams = [];
        for($i=0; $i<count($filtered)-2; $i++){
            $trigrams[] = $filtered[$i].' '.$filtered[$i+1].' '.$filtered[$i+2];
        }
        $uniCloud = $this->buildFrequencyData($unigrams);
        $biCloud  = $this->buildFrequencyData($bigrams);
        $triCloud = $this->buildFrequencyData($trigrams);
        $suggestions = [];
        $overusedSingles = $this->detectOveruse($uniCloud);
        $overusedBigrams = $this->detectOveruse($biCloud);
        $overusedTrigrams = $this->detectOveruse($triCloud);
        $allOverused = array_unique(array_merge($overusedSingles, $overusedBigrams, $overusedTrigrams));
        if (!empty($allOverused)) {
            $suggestions[] = "Possible overuse of phrases: " . implode(', ', $allOverused);
        }
        return [
            'unigrams' => $uniCloud,
            'bigrams' => $biCloud,
            'trigrams' => $triCloud,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Build frequency data for keyword cloud.
     */
    private function buildFrequencyData(array $tokens, int $minCount = 2): array
    {
        $counts = array_count_values($tokens);
        $counts = array_filter($counts, fn($cnt) => $cnt >= $minCount);
        arsort($counts);
        $total = array_sum($counts);
        $result = [];
        foreach ($counts as $phrase => $count) {
            $density = $total > 0 ? ($count / $total) * 100 : 0;
            $result[] = [
                'phrase' => $phrase,
                'count' => $count,
                'density' => round($density, 2),
            ];
        }
        return $result;
    }

    /**
     * Detect overuse in keyword cloud.
     */
    private function detectOveruse(array $data): array
    {
        $overused = [];
        foreach($data as $d){
            if($d['count'] > 5 || $d['density'] > 5){
                $overused[] = $d['phrase'];
            }
        }
        return $overused;
    }

    /**
     * Check Schema Data.
     */
    private function checkSchema(DOMDocument $dom): array
    {
        $schemas = ['json_ld' => [], 'microdata' => []];
        $xpath = new DOMXPath($dom);
        $jsonLdScripts = $xpath->query('//script[@type="application/ld+json"]');
        foreach($jsonLdScripts as $js){
            $json = trim($js->nodeValue);
            $decoded = json_decode($json, true);
            if(json_last_error() !== JSON_ERROR_NONE){
                $this->logger->warning("JSON-LD decoding error: " . json_last_error_msg());
                continue;
            }
            if(isset($decoded['@graph']) && is_array($decoded['@graph'])){
                foreach($decoded['@graph'] as $s){
                    $types = $s['@type'] ?? 'undefined';
                    if(is_array($types)){
                        foreach($types as $type){
                            if(!is_string($type)){
                                $type = 'undefined';
                                $this->logger->warning("Non-string @type encountered in @graph, defaulting to 'undefined'.");
                            }
                            $schemas['json_ld'][$type][] = $s;
                        }
                    } else {
                        $type = is_string($types) ? $types : 'undefined';
                        if(!is_string($types)){
                            $this->logger->warning("Non-string @type encountered in @graph, defaulting to 'undefined'.");
                        }
                        $schemas['json_ld'][$type][] = $decoded;
                    }
                }
            } else {
                $types = $decoded['@type'] ?? 'undefined';
                if(is_array($types)){
                    foreach($types as $type){
                        if(!is_string($type)){
                            $type = 'undefined';
                            $this->logger->warning("Non-string @type encountered, defaulting to 'undefined'.");
                        }
                        $schemas['json_ld'][$type][] = $decoded;
                    }
                } else {
                    $type = is_string($types) ? $types : 'undefined';
                    if(!is_string($types)){
                        $this->logger->warning("Non-string @type encountered, defaulting to 'undefined'.");
                    }
                    $schemas['json_ld'][$type][] = $decoded;
                }
            }
        }
        $microItems = $xpath->query('//*[@itemscope]');
        foreach($microItems as $item){
            $typeUrl = $item->getAttribute('itemtype');
            $type = ($typeUrl) ? basename(parse_url($typeUrl, PHP_URL_PATH)) : 'undefined';
            $schemas['microdata'][$type][] = $item->nodeName;
        }
        if(empty($schemas['json_ld']) && empty($schemas['microdata'])){
            $this->logger->info("No schema data found.");
            return ['schema_data' => 'No schema data found.'];
        }
        $this->logger->info("Schema data extracted successfully.");
        return ['schema_data' => $schemas];
    }

    /**
     * Check Mobile Friendly.
     */
    private function checkMobileFriendly(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $viewport = $xpath->query('//meta[@name="viewport"]');
        if($viewport->length > 0){
            return [
                'mobile_friendly' => true,
                'mobile_friendly_details' => 'Viewport meta tag found.'
            ];
        }
        return [
            'mobile_friendly' => false,
            'mobile_friendly_details' => 'No viewport meta tag found.'
        ];
    }

    /**
     * Check OpenGraph.
     */
    private function checkOpenGraph(DOMDocument $dom): array
    {
        $ogData = [];
        $xpath = new DOMXPath($dom);
        $ogTags = $xpath->query('//meta[starts-with(translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"og:")]');
        foreach($ogTags as $m){
            $property = $m->getAttribute('property');
            $content  = $m->getAttribute('content');
            if($property && $content){
                $key = strtolower(str_replace('og:','',$property));
                $ogData[$key] = $content;
            }
        }
        return ['open_graph_data' => $ogData];
    }

    /**
     * Check Missing OpenGraph Tags.
     */
    private function checkMissingOpenGraphTags(DOMDocument $dom): array
    {
        $exists = $this->checkOpenGraph($dom);
        $tags = $exists['open_graph_data'] ?? [];
        $required = [
            'title' => ['description' => 'Page title (og:title)', 'suggestion' => 'Keep it concise.'],
            'description' => ['description' => 'Page description (og:description)', 'suggestion' => 'A short compelling description.'],
            'image' => ['description' => 'Preview image (og:image)', 'suggestion' => 'Use at least 1200x630.'],
            'url' => ['description' => 'Canonical URL (og:url)', 'suggestion' => 'Primary page URL.'],
            'site_name' => ['description' => 'Website Name (og:site_name)', 'suggestion' => 'Your brand name.'],
            'type' => ['description' => 'Content type (og:type)', 'suggestion' => 'e.g., website, article.'],
            'locale' => ['description' => 'Content locale (og:locale)', 'suggestion' => 'e.g., en_US.'],
        ];
        $missing = [];
        foreach($required as $tag => $details){
            if(!isset($tags[$tag]) || !$tags[$tag]){
                $missing["og:{$tag}"] = $details;
            }
        }
        return $missing;
    }

    /**
     * Check Safe Browsing.
     */
    private function checkSafeBrowsing(string $url): string
    {
        if(empty($this->safeBrowsingGoogleApiKey)){
            return 'API Key not set.';
        }
        $api = "https://safebrowsing.googleapis.com/v4/threatMatches:find?key={$this->safeBrowsingGoogleApiKey}";
        $payload = [
            'client' => [
                'clientId' => 'SEOAnalyzerTool',
                'clientVersion' => '1.0'
            ],
            'threatInfo' => [
                'threatTypes' => ["MALWARE", "SOCIAL_ENGINEERING"],
                'platformTypes' => ["ANY_PLATFORM"],
                'threatEntryTypes' => ["URL"],
                'threatEntries' => [['url' => $url]]
            ]
        ];
        try {
            $resp = $this->httpClient->post($api, ['json' => $payload]);
            $data = json_decode($resp->getBody(), true);
            if(empty($data['matches'])){
                return "Safe";
            }
            return "Unsafe";
        } catch(\Exception $e){
            return "Error checking Safe Browsing: ".$e->getMessage();
        }
    }

    /**
     * Analyze PageSpeed with caching.
     *
     * Caches PageSpeed API results for 24 hours (86400 seconds) since they rarely change.
     *
     * @param string $url
     * @return array
     */
    private function checkPageSpeed(string $url): array
    {
        $cacheKey = 'pagespeed_' . md5($url);
        $cacheTTL = 86400; // 24 hours cache
        if ($cached = $this->cache->get($cacheKey)) {
            $this->logger->info("Returning cached PageSpeed data for: $url");
            return $cached;
        }
        if (empty($this->googleApiKey)) {
            return $this->handlePageSpeedError('PageSpeed API key not configured');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->handlePageSpeedError('Invalid URL format');
        }
        $strategies = ['mobile', 'desktop'];
        $results = [];
        $client = $this->httpClient;
        $promises = [];
        try {
            foreach ($strategies as $strategy) {
                $apiUrl = $this->buildPageSpeedApiUrl($url, $strategy);
                $promises[$strategy] = $client->getAsync($apiUrl);
            }
            $responses = Utils::unwrap($promises);
            foreach ($strategies as $strategy) {
                $response = $responses[$strategy];
                $data = json_decode($response->getBody(), true);
                $results[$strategy] = $this->processPageSpeedData($data);
            }
            $results['timestamp'] = date('c');
            $results['url'] = $url;
            $results['cache_hit'] = false;
            $this->cache->save($cacheKey, $results, $cacheTTL);
        } catch (\Exception $e) {
            $this->logger->error("PageSpeed analysis failed: " . $e->getMessage());
            return $this->handlePageSpeedError($e->getMessage(), $e);
        }
        return $results;
    }

    /**
     * Build PageSpeed API URL.
     */
    private function buildPageSpeedApiUrl(string $url, string $strategy): string
    {
        $params = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => ['PERFORMANCE', 'ACCESSIBILITY', 'SEO'],
            'key' => $this->googleApiKey
        ];
        $this->logger->debug("Google API Path : " . print_r("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?" . http_build_query($params), true));
        return 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query($params);
    }

    /**
     * Process PageSpeed API data.
     */
    private function processPageSpeedData(array $data): array
    {
        $result = [
            'score' => null,
            'metrics' => [],
            'diagnostics' => [],
            'screenshot' => null,
            'category_scores' => [],
            'audits' => []
        ];
        try {
            $result['score'] = round(($data['lighthouseResult']['categories']['performance']['score'] ?? 0) * 100, 1);
            foreach (['accessibility', 'seo'] as $category) {
                $result['category_scores'][$category] = round(($data['lighthouseResult']['categories'][$category]['score'] ?? 0) * 100, 1);
            }
            $metrics = [
                'first-contentful-paint' => 'FCP',
                'largest-contentful-paint' => 'LCP',
                'interactive' => 'TTI',
                'cumulative-layout-shift' => 'CLS',
                'total-blocking-time' => 'TBT'
            ];
            foreach ($metrics as $metric => $label) {
                $auditData = $data['lighthouseResult']['audits'][$metric] ?? [];
                $numericValue = $auditData['numericValue'] ?? 0;
                $result['metrics'][$label] = [
                    'value' => $numericValue,
                    'unit' => 'ms',
                    'displayValue' => $this->formatMetric($numericValue, $metric),
                    'score' => $auditData['score'] ?? null
                ];
            }
            $result['screenshot'] = $data['lighthouseResult']['audits']['final-screenshot']['details']['data'] ?? null;
            $audits = $data['lighthouseResult']['audits'] ?? [];
            foreach ($audits as $audit) {
                if (!isset($audit['score']) || !isset($audit['details'])) {
                    continue;
                }
                if ($audit['score'] < 0.9 && isset($audit['details']['type']) && $audit['details']['type'] === 'opportunity') {
                    $impact = $this->calculateImpact($audit);
                    $result['diagnostics'][] = [
                        'title' => $audit['title'] ?? 'Unknown Audit',
                        'description' => $audit['description'] ?? '',
                        'impact' => $impact,
                        'recommendation' => $audit['details']['items'][0]['recommendation'] ?? null
                    ];
                }
            }
            $result['score_class'] = $this->classifyScore($result['score']);
            $result['version'] = $data['lighthouseResult']['lighthouseVersion'] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error("PageSpeed data processing failed: " . $e->getMessage());
        }
        return $result;
    }

    /**
     * Classify performance score.
     */
    private function classifyScore(float $score): array
    {
        return match(true) {
            $score >= 90 => ['label' => 'Excellent', 'color' => 'green'],
            $score >= 50 => ['label' => 'Needs Improvement', 'color' => 'orange'],
            default => ['label' => 'Poor', 'color' => 'red']
        };
    }

    /**
     * Format metrics for human readability.
     */
    private function formatMetric(float $value, string $metric): string
    {
        return match($metric) {
            'cumulative-layout-shift' => number_format($value, 2),
            'largest-contentful-paint' => number_format($value / 1000, 2) . 's',
            default => number_format($value) . 'ms'
        };
    }

    /**
     * Handle PageSpeed errors.
     */
    private function handlePageSpeedError(string $message, \Throwable $e = null): array
    {
        $this->logger->error("PageSpeed Error: $message" . ($e ? " - " . $e->getMessage() : ''));
        return [
            'error' => true,
            'message' => 'Performance analysis unavailable',
            'user_message' => 'We couldn\'t retrieve performance data at this time. Please try again later or check your URL.',
            'technical_details' => $this->config->showErrors ? $message : null,
            'timestamp' => date('c'),
            'retry_suggestion' => true,
            'documentation_link' => '/help/performance-troubleshooting'
        ];
    }

    /**
     * Calculate the impact of an audit opportunity.
     */
    private function calculateImpact(array $audit): string
    {
        $details = $audit['details'] ?? [];
        $items = $details['items'] ?? [];
        $totalWastedMs = 0;
        $totalWastedBytes = 0;
        foreach ($items as $item) {
            $totalWastedMs += $item['wastedMs'] ?? 0;
            $totalWastedBytes += $item['wastedBytes'] ?? 0;
        }
        if ($totalWastedMs > 0) {
            return sprintf('Potential savings of %dms', $totalWastedMs);
        }
        if ($totalWastedBytes > 0) {
            return sprintf('Reduce by %s', $this->formatBytes($totalWastedBytes));
        }
        if (isset($audit['numericValue'])) {
            $unit = $audit['numericUnit'] ?? 'units';
            return sprintf('%s %s improvement possible', round($audit['numericValue'], 2), $unit);
        }
        $score = $audit['score'] ?? 1;
        $impact = (1 - $score) * 100;
        return match(true) {
            $impact >= 40 => 'High impact',
            $impact >= 20 => 'Moderate impact',
            default => 'Low impact'
        };
    }

    /**
     * Format bytes into a human-readable format.
     */
    private function formatBytes(float $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $formatted = round($bytes / (1024 ** $i), 2);
        return "$formatted {$units[$i]}";
    }

    /**
     * Extended Text/HTML Ratio.
     */
    private function calculateTextHtmlRatioExtended(string $url): array
    {
        $start = microtime(true);
        [$html, ] = $this->fetchHtml($url);
        $loadTime = microtime(true) - $start;
        if (!$html) {
            return ['text_html_ratio' => "Error: couldn't fetch HTML."];
        }
        $htmlSize = strlen($html);
        $plain = strip_tags($html);
        $textSize = strlen($plain);
        $ratio = ($htmlSize > 0) ? ($textSize / $htmlSize) * 100 : 0;
        $wordCount = str_word_count($plain);
        $readTime = round($wordCount / 200);
        $cat = 'Text-heavy';
        if($ratio < 10){ $cat = 'HTML-heavy'; }
        elseif($ratio <= 50){ $cat = 'Balanced'; }
        preg_match_all('/<([a-z][a-z0-9]*)\b[^>]*>/i', $html, $m);
        $tagCount = count($m[1]);
        preg_match_all('/<a\s+(?:[^>]*?\s+)?href=[\'"]([^\'"]+)[\'"]/i', $html, $mm);
        $linkCount = count($mm[1]);
        preg_match_all('/<img\b[^>]*>/i', $html, $im);
        $imageCount = count($im[0]);
        preg_match_all('/<script\b[^>]*>/i', $html, $scr);
        $scriptCount = count($scr[0]);
        preg_match_all('/<style\b[^>]*>/i', $html, $sty);
        $styleCount = count($sty[0]);
        $httpCode = $this->getHttpResponseCode($url);
        return [
            'text_html_ratio' => [
                'html_size_bytes' => $htmlSize,
                'text_size_bytes' => $textSize,
                'ratio_percent' => round($ratio, 2),
                'ratio_category' => $cat,
                'word_count' => $wordCount,
                'estimated_reading_time' => $readTime,
                'load_time_seconds' => round($loadTime, 2),
                'total_html_tags' => $tagCount,
                'total_links' => $linkCount,
                'total_images' => $imageCount,
                'total_scripts' => $scriptCount,
                'total_styles' => $styleCount,
                'http_response_code' => $httpCode,
            ]
        ];
    }

    /**
     * Get HTTP response code.
     */
    private function getHttpResponseCode(string $url): int
    {
        try {
            $resp = $this->httpClient->head($url);
            return $resp->getStatusCode();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check Google Analytics presence.
     */
    private function checkAnalytics(string $html): string
    {
        $patterns = [
            '/google-analytics\.com\/analytics\.js/',
            '/gtag\(\'config\',\s*\'[A-Z0-9-]+\'\)/',
            '/ga\([\'"]create[\'"],\s*[\'"]UA-\d+-\d+[\'"]/',
            '/www\.googletagmanager\.com\/gtag\/js/',
            '/_gaq\.push/'
        ];
        foreach($patterns as $p){
            if(preg_match($p, $html)){
                return "Google Analytics Found";
            }
        }
        return "Google Analytics Not Found";
    }

    /**
     * Technology Detection from HTML, headers, meta tags, and linked files.
     */
    private function detectFromHtml(string $html, array $headers, string $url): array
    {
        $technologies = [];
        $techPatterns = [
            'WordPress' => '/wp-content|wp-admin|wordpress/i',
            'Joomla' => '/joomla/i',
            'Drupal' => '/drupal/i',
            'Magento' => '/mage\.php|Magento/i',
            'Shopify' => '/shopify\.com/i',
            'Nginx' => '/nginx/i',
            'Apache' => '/apache/i',
            'IIS' => '/iis/i',
            'PHP' => '/PHP/i',
            'Node.js' => '/node\.js/i',
            'Express.js' => '/express\.js/i',
        ];
        foreach($techPatterns as $tech => $pattern){
            if(preg_match($pattern, $html)){
                $technologies[] = $tech;
            }
        }
        foreach($headers as $k => $vals){
            if(!is_array($vals)){ $vals = [$vals]; }
            foreach($vals as $v){
                if(!is_string($v)) continue;
                foreach($techPatterns as $tech => $pat){
                    if(preg_match($pat, $v)){
                        $technologies[] = $tech;
                    }
                }
                if(stripos($k, 'Server') !== false){
                    if(stripos($v, 'nginx') !== false){
                        $technologies[] = 'Nginx';
                    }
                    if(stripos($v, 'apache') !== false){
                        $technologies[] = 'Apache';
                    }
                    if(stripos($v, 'iis') !== false){
                        $technologies[] = 'IIS';
                    }
                }
                if(stripos($k, 'X-Powered-By') !== false){
                    if(stripos($v, 'php') !== false){
                        $technologies[] = 'PHP';
                    }
                    if(stripos($v, 'node.js') !== false){
                        $technologies[] = 'Node.js';
                    }
                    if(stripos($v, 'express') !== false){
                        $technologies[] = 'Express.js';
                    }
                }
            }
        }
        $dom = $this->createDomDocument($html);
        $xpath = new DOMXPath($dom);
        $metas = $xpath->query('//meta');
        foreach($metas as $m){
            $content = strtolower($m->getAttribute('content'));
            if(strpos($content, 'wordpress') !== false)  $technologies[] = 'WordPress';
            if(strpos($content, 'joomla') !== false)     $technologies[] = 'Joomla';
            if(strpos($content, 'drupal') !== false)     $technologies[] = 'Drupal';
            if(strpos($content, 'magento') !== false)    $technologies[] = 'Magento';
            if(strpos($content, 'shopify') !== false)    $technologies[] = 'Shopify';
        }
        $linked = $this->getLinkedFiles($dom);
        foreach($linked as $lf){
            if(is_string($lf)){
                foreach($techPatterns as $tech => $pat){
                    if(preg_match($pat, $lf)){
                        $technologies[] = $tech;
                    }
                }
            }
        }
        $extraTech = $this->checkSpecificFiles($url);
        if(is_array($extraTech) && !empty($extraTech['Other'])){
            $technologies = array_merge($technologies, $extraTech['Other']);
        }
        $technologies = array_unique($technologies);
        sort($technologies);
        return $technologies;
    }

    /**
     * Get linked CSS/JS files.
     */
    private function getLinkedFiles(DOMDocument $dom): array
    {
        $linkedFiles = [];
        $links = $dom->getElementsByTagName('link');
        foreach($links as $l){
            $rel = strtolower($l->getAttribute('rel'));
            if(in_array($rel, ['stylesheet','preload'])){
                $href = $l->getAttribute('href');
                if($href){
                    $linkedFiles[] = $href;
                }
            }
        }
        $scripts = $dom->getElementsByTagName('script');
        foreach($scripts as $s){
            $src = $s->getAttribute('src');
            if($src){
                $linkedFiles[] = $src;
            }
        }
        return $linkedFiles;
    }

    /**
     * Check specific files for technology detection.
     */
    private function checkSpecificFiles(string $url): array
    {
        $tech = ['Other' => []];
        $robotsUrl = rtrim($this->ensureHttpProtocol($url), '/') . '/robots.txt';
        $content = @file_get_contents($robotsUrl);
        if($content !== false){
            if(strpos($content, 'Disallow: /wp-admin/') !== false){
                $tech['Other'][] = 'WordPress';
            }
        }
        return $tech;
    }

    /**
     * Ensure URL has an http/https protocol.
     */
    private function ensureHttpProtocol(string $url): string
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            $httpsUrl = "https://" . ltrim($url, '/');
            if ($this->isUrlAccessible($httpsUrl)) {
                return $httpsUrl;
            }
            return "http://" . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * Check if URL is accessible.
     */
    private function isUrlAccessible(string $url): bool
    {
        try {
            $this->httpClient->head($url, ['timeout' => 5]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check WWW resolution.
     */
    private function checkWWWResolve(string $url): array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if(!$host){
            return ['www_resolution' => false, 'details' => 'Invalid URL'];
        }
        $nonWwwHost = preg_replace('/^www\./i', '', $host);
        $wwwHost = 'www.' . $nonWwwHost;
        $scheme = $parsed['scheme'] ?? 'https';
        $originalUrl = "{$scheme}://{$nonWwwHost}";
        $wwwUrl = "{$scheme}://{$wwwHost}";
        $result = ['www_resolution' => true, 'details' => []];
        try {
            $resp = $this->httpClient->head($wwwUrl, [
                'allow_redirects' => ['track_redirects' => true],
                'timeout' => 10,
                'verify' => true
            ]);
            $redirHist = $resp->getHeader('X-Guzzle-Redirect-History') ?? [];
            $finalUrl = end($redirHist) ?: $wwwUrl;
            $result['details']['www_to_non_www'] = [
                'status' => (stripos($finalUrl, $originalUrl) !== false),
                'redirected_to' => $finalUrl
            ];
        } catch(\Exception $e){
            $result['details']['www_to_non_www'] = ['status' => false, 'error' => $e->getMessage()];
            $result['www_resolution'] = false;
        }
        return $result;
    }

    /**
     * Check IP Canonicalization.
     */
    private function checkIPCanonicalization(string $url, int $timeout = 10): bool
    {
        $this->checkedIPs = [];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL provided.");
        }
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            throw new \InvalidArgumentException("No host found in the URL.");
        }
        $domainHost = $parsed['host'];
        $dnsRecords = dns_get_record($domainHost, DNS_A | DNS_AAAA);
        if (empty($dnsRecords)) {
            throw new \RuntimeException("Failed to retrieve DNS records for the domain.");
        }
        $ips = [];
        foreach ($dnsRecords as $record) {
            if (isset($record['ip'])) $ips[] = $record['ip'];
            if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
        }
        if (empty($ips)) {
            return false;
        }
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        foreach ($ips as $ip) {
            $this->checkedIPs[] = $ip;
            $isIPv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            $formattedIp = $isIPv6 ? "[$ip]" : $ip;
            foreach (['http', 'https'] as $scheme) {
                $ipUrl = "$scheme://$formattedIp$port$path$query$fragment";
                try {
                    $effectiveUri = null;
                    $response = $this->httpClient->get($ipUrl, [
                        'timeout' => $timeout,
                        'allow_redirects' => [
                            'max' => 5,
                            'strict' => true,
                            'track_redirects' => true
                        ],
                        'verify' => true,
                        'headers' => ['Host' => $domainHost],
                        'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$effectiveUri) {
                            $effectiveUri = $stats->getEffectiveUri();
                        }
                    ]);
                    $finalUrl = (string) $effectiveUri;
                    $finalHost = parse_url($finalUrl, PHP_URL_HOST);
                    if (strcasecmp($finalHost, $domainHost) === 0) {
                        return true;
                    }
                } catch (\Exception $e) {
                    error_log("Request to $ipUrl failed: " . $e->getMessage());
                    continue;
                }
            }
        }
        return false;
    }

    private function getCheckedIPs(): array
    {
        return $this->checkedIPs;
    }

    /**
     * Check DNS Records.
     */
    private function checkDNSRecords(string $domain): array
    {
        $types = ['A', 'NS', 'CNAME', 'MX', 'TXT', 'AAAA', 'SRV', 'PTR', 'SOA', 'CAA'];
        $info = [];
        foreach($types as $t){
            $const = 'DNS_' . $t;
            if(defined($const)){
                $records = @dns_get_record($domain, constant($const));
                if(!empty($records)){
                    $info[$t] = $records;
                }
            }
        }
        return $info;
    }

  /**
 * Check IP Information + Geolocation
 *
 * 1) Resolve domain to IPv4 (via gethostbyname).
 * 2) Optionally fetch IPv6 addresses via dns_get_record.
 * 3) Call ip-api.com (free tier) to get geolocation data for the IPv4 address.
 */
private function checkIP(string $domain): array
{
    // Start with empty structure
    $ipInfo = [
        'IPv4' => null,
        'IPv6' => [],
        'geo'  => [
            // We'll fill these from ip-api.com
            'ip'      => null,
            'country' => null,
            'region'  => null,
            'city'    => null,
            'zip'     => null,
            'isp'     => null,
            'org'     => null,
            'as'      => null,
            'error'   => null,
        ],
    ];

    // 1) Resolve domain to IPv4
    $ipv4 = gethostbyname($domain);
    // If resolution succeeded -> gethostbyname returns the IP
    // If it failed -> it might return the domain again or "0.0.0.0"
    if ($ipv4 !== $domain && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipInfo['IPv4'] = $ipv4;
    }

    // 2) Fetch IPv6 (AAAA) records
    $AAAA = @dns_get_record($domain, DNS_AAAA);
    if (!empty($AAAA)) {
        $ipv6Arr = [];
        foreach ($AAAA as $rec) {
            if (!empty($rec['ipv6'])) {
                $ipv6Arr[] = $rec['ipv6'];
            }
        }
        if (!empty($ipv6Arr)) {
            $ipInfo['IPv6'] = $ipv6Arr;
        }
    }

    // 3) If we got a valid IPv4, try to get geolocation from ip-api.com
    if (!empty($ipInfo['IPv4'])) {
        $ip = $ipInfo['IPv4'];
        // ip-api.com free endpoint, no HTTPS
        $endpoint = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,zip,isp,org,as,query";

        try {
            $response = $this->httpClient->get($endpoint, [
                'timeout' => 10,
            ]);
            $data = json_decode($response->getBody(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning("checkIP: JSON parse error from ip-api.com for IP '{$ip}'.");
                $ipInfo['geo']['error'] = 'Invalid JSON response from ip-api.com.';
            } elseif (empty($data) || !isset($data['status']) || $data['status'] !== 'success') {
                // Possibly 'fail' status, or we got an error message
                $msg = $data['message'] ?? 'Unknown error';
                $this->logger->warning("checkIP: ip-api.com returned error: {$msg}");
                $ipInfo['geo']['error'] = "ip-api.com error: {$msg}";
            } else {
                // Fill the geo details
                $ipInfo['geo']['ip']      = $data['query']      ?? $ip;
                $ipInfo['geo']['country'] = $data['country']    ?? '';
                $ipInfo['geo']['region']  = $data['regionName'] ?? '';
                $ipInfo['geo']['city']    = $data['city']       ?? '';
                $ipInfo['geo']['zip']     = $data['zip']        ?? '';
                $ipInfo['geo']['isp']     = $data['isp']        ?? '';
                $ipInfo['geo']['org']     = $data['org']        ?? '';
                $ipInfo['geo']['as']      = $data['as']         ?? '';
            }
        } catch (\Exception $e) {
            $this->logger->error("checkIP: Exception contacting ip-api.com: {$e->getMessage()}");
            $ipInfo['geo']['error'] = 'Exception contacting ip-api.com: ' . $e->getMessage();
        }
    } else {
        $this->logger->warning("checkIP: Unable to resolve domain '{$domain}' to a valid IPv4 address. No geo lookup.");
        $ipInfo['geo']['error'] = 'Could not resolve a valid IPv4 address for domain.';
    }

    return $ipInfo;
}


    /**
     * Check SSL.
     */
    public function checkSSL(string $domain): array
    {
        $hasSSL = false;
        try {
            $this->httpClient->get("https://{$domain}", ['verify' => true]);
            $hasSSL = true;
        } catch(\Exception $e){
            $hasSSL = false;
        }
        $certInfo = $hasSSL ? $this->getSSLInfo($domain) : null;
        return [
            'has_ssl' => $hasSSL,
            'ssl_info' => $certInfo
        ];
    }

    /**
     * Get SSL Certificate Information.
     */
    private function getSSLInfo(string $domain, int $port = 443): array
    {
        $sslInfo = [];
        $ctx = stream_context_create(['ssl'=>[
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);
        $client = @stream_socket_client("ssl://{$domain}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if($client){
            $params = stream_context_get_params($client);
            if(isset($params['options']['ssl']['peer_certificate'])){
                $cert = $params['options']['ssl']['peer_certificate'];
                $parsed = openssl_x509_parse($cert);
                if($parsed){
                    $sslInfo = $parsed;
                }
            }
            fclose($client);
        } else {
            $sslInfo['error'] = "Unable to retrieve SSL info: {$errstr} ({$errno})";
        }
        return $sslInfo;
    }

    /**
     * Check robots.txt.
     */
    private function checkRobotsTxt(string $domain): array
    {
        $robotsUrl = rtrim($this->ensureHttpProtocol($domain), '/') . '/robots.txt';
        try {
            $content = @file_get_contents($robotsUrl);
            return ['robots_txt_found' => !empty($content), 'robots_txt_content' => $content];
        } catch (\Exception $e) {
            return ['robots_txt_found' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check sitemap.xml.
     */
    private function checkXmlSitemap(string $domain): array
    {
        $sitemapUrl = rtrim($this->ensureHttpProtocol($domain), '/') . '/sitemap.xml';
        try {
            $this->httpClient->head($sitemapUrl);
            return ['xml_sitemap_found' => true, 'xml_sitemap_path' => $sitemapUrl];
        } catch(\Exception $e){
            return ['xml_sitemap_found' => false, 'xml_sitemap_path' => $sitemapUrl];
        }
    }

    /**
     * Check Meta Robots.
     */
    private function checkMetaRobots(DOMDocument $dom): string
    {
        $xpath = new DOMXPath($dom);
        $meta = $xpath->query('//meta[@name="robots"]');
        if($meta->length > 0){
            $content = $meta->item(0)->getAttribute('content');
            return $content ?: 'Found meta robots but no content.';
        }
        return 'No meta robots tag found.';
    }

    /**
     * Check GZIP compression.
     */
    private function checkGzip(string $url): bool
    {
        try {
            $response = $this->httpClient->get($url, [
                'headers' => [
                    'Accept-Encoding' => 'gzip, deflate, br',
                ],
                'timeout' => 5,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return false;
            }
            $contentEncoding = $response->getHeaderLine('Content-Encoding');
            $encodings = array_map('strtolower', array_map('trim', explode(',', $contentEncoding)));
            $allowedEncodings = ['gzip', 'deflate', 'br'];
            return !empty(array_intersect($encodings, $allowedEncodings));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * W3C Validation (stub).
     */
    private function checkW3CValidator(DOMDocument $dom): array
    {
        return [
            'w3c_valid' => true,
            'details' => 'No W3C errors found (stubbed).'
        ];
    }

    /**
     * Check Document Type.
     */
    private function checkDocType(DOMDocument $dom): array
    {
        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                $doctype = $child->name;
                if (strtoupper($doctype) === 'HTML') {
                    $this->logger->info("Document type is HTML5.");
                    return [
                        'doctype_correct' => true,
                        'details' => 'Document type is HTML5.'
                    ];
                } else {
                    $this->logger->warning("Document type is {$doctype}, expected HTML5.");
                    return [
                        'doctype_correct' => false,
                        'details' => "Document type is {$doctype}, expected HTML5."
                    ];
                }
            }
        }
        $this->logger->warning("No DOCTYPE found in the document.");
        return [
            'doctype_correct' => false,
            'details' => 'No DOCTYPE found in the document.'
        ];
    }

    /**
     * Check Charset.
     */
    private function checkCharset(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $metaCharset = $xpath->query('//meta[@charset]');
        $metaContentType = $xpath->query('//meta[@http-equiv="Content-Type"]');
        if ($metaCharset->length > 0) {
            $charset = strtoupper($metaCharset->item(0)->getAttribute('charset'));
            if($charset === 'UTF-8'){
                $this->logger->info("Charset is UTF-8.");
                return [
                    'charset_correct' => true,
                    'details' => 'Charset is UTF-8.'
                ];
            } else {
                $this->logger->warning("Charset is {$charset}, expected UTF-8.");
                return [
                    'charset_correct' => false,
                    'details' => "Charset is {$charset}, expected UTF-8."
                ];
            }
        }
        if ($metaContentType->length > 0) {
            $content = strtolower($metaContentType->item(0)->getAttribute('content'));
            if(strpos($content, 'charset=utf-8') !== false){
                $this->logger->info("Meta Content-Type declares UTF-8.");
                return [
                    'charset_correct' => true,
                    'details' => 'Meta Content-Type declares UTF-8.'
                ];
            } else {
                $this->logger->warning("Meta Content-Type does not declare UTF-8.");
                return [
                    'charset_correct' => false,
                    'details' => 'Meta Content-Type does not declare UTF-8.'
                ];
            }
        }
        $this->logger->warning("No charset declaration found in the document.");
        return [
            'charset_correct' => false,
            'details' => 'No charset declaration found.'
        ];
    }

    /**
     * Categorize link position.
     */
    private function categorizeLinkPosition(\DOMNode $aTag): string
    {
        $parent = $aTag->parentNode;
        while($parent && $parent->nodeName !== 'body'){
            if(in_array($parent->nodeName, ['header','nav','footer','aside','main','section'])){
                return $parent->nodeName;
            }
            $parent = $parent->parentNode;
        }
        return 'body';
    }

    /**
     * Check tracking parameters in URL.
     */
    private function hasTrackingParameters(array $parsedUrl): bool
    {
        if(isset($parsedUrl['query'])){
            parse_str($parsedUrl['query'], $q);
            $trackKeys = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'];
            foreach($trackKeys as $tk){
                if(isset($q[$tk])) return true;
            }
        }
        return false;
    }

    /**
     * Normalize URL.
     */
    private function normalizeUrl(string $url): string
    {
        $p = parse_url($url);
        if(!$p){
            return $url;
        }
        $scheme = isset($p['scheme']) ? strtolower($p['scheme']) . '://' : '';
        $host   = isset($p['host']) ? strtolower($p['host']) : '';
        $port   = isset($p['port']) ? (':' . $p['port']) : '';
        $path   = isset($p['path']) ? rtrim($p['path'], '/') : '';
        $query  = isset($p['query']) ? ('?' . $p['query']) : '';
        return "{$scheme}{$host}{$port}{$path}{$query}";
    }

    /**
     * Check if link is internal.
     */
    private function isInternalLink(string $linkUrl, string $baseUrl): bool
    {
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $linkHost = parse_url($linkUrl, PHP_URL_HOST);
        return (strtolower($baseHost) === strtolower($linkHost));
    }

    /**
     * Get node position in DOM.
     */
    private function getNodePosition(\DOMNode $node): string
    {
        $doc = $node->ownerDocument;
        $xp = new DOMXPath($doc);
        $all = $xp->query('//*');
        foreach($all as $i => $nd){
            if($nd->isSameNode($node)){
                return "Element #" . ($i + 1);
            }
        }
        return 'N/A';
    }

    /**
     * Resolve relative URL against a base URL.
     */
    private function resolveUrl(string $relativeUrl, string $baseUrl): string
    {
        if (parse_url($relativeUrl, PHP_URL_SCHEME) != '') {
            return $relativeUrl;
        }
        if (substr($relativeUrl, 0, 2) === '//') {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            return $scheme . ':' . $relativeUrl;
        }
        $base = parse_url($baseUrl);
        if (!isset($base['scheme']) || !isset($base['host'])) {
            return $relativeUrl;
        }
        if ($relativeUrl[0] === '/') {
            $path = $relativeUrl;
        } else {
            $path = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';
            $path .= $relativeUrl;
        }
        $segments = explode('/', $path);
        $resolved = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $segment;
            }
        }
        $normalizedPath = '/' . implode('/', $resolved);
        $absoluteUrl = $base['scheme'] . '://' . $base['host'];
        if (isset($base['port'])) {
            $absoluteUrl .= ':' . $base['port'];
        }
        $absoluteUrl .= $normalizedPath;
        if (isset($base['query']) && !empty($base['query'])) {
            $absoluteUrl .= '?' . $base['query'];
        }
        if (isset($base['fragment']) && !empty($base['fragment'])) {
            $absoluteUrl .= '#' . $base['fragment'];
        }
        return $absoluteUrl;
    }

    /**
     * Get array of stop words.
     */
    private function getStopWords(): array
    {
        return [
            "a","about","above","after","again","against","all","am","an","and","any","are","aren't","as","at",
            "be","because","been","before","being","below","between","both","but","by","can","can't","cannot",
            "could","couldn't","did","didn't","do","does","doesn't","doing","don't","down","during","each","few",
            "for","from","further","had","hadn't","has","hasn't","have","haven't","having","he","he'd","he'll",
            "he's","her","here","here's","hers","herself","him","himself","his","how","how's","i","i'd","i'll",
            "i'm","i've","if","in","into","is","isn't","it","it's","its","itself","let's","me","more","most",
            "mustn't","my","myself","no","nor","not","of","off","on","once","only","or","other","ought","our",
            "ours","ourselves","out","over","own","same","shan't","she","she'd","she'll","she's","should",
            "shouldn't","so","some","such","than","that","that's","the","their","theirs","them","themselves",
            "then","there","there's","these","they","they'd","they'll","they're","they've","this","those","through",
            "to","too","under","until","up","very","was","wasn't","we","we'd","we'll","we're","we've","were",
            "weren't","what","what's","when","when's","where","where's","which","while","who","who's","whom",
            "why","why's","with","won't","would","wouldn't","you","you'd","you'll","you're","you've","your",
            "yours","yourself","yourselves"
        ];
    }

    /**
     * Check if URL is rewritten (clean URL).
     */
    private function checkUrlRewrite(string $url): array
    {
        $this->logger->info("Checking URL rewrite for: {$url}");
        try {
            $parsed = parse_url($url);
            $hasQuery = isset($parsed['query']) && !empty($parsed['query']);
            if ($hasQuery) {
                $this->logger->warning("URL contains query parameters: {$url}");
                return [
                    'url_rewrite' => false,
                    'details' => 'The URL contains query parameters. Consider implementing URL rewriting for a clean, descriptive URL.'
                ];
            }
            $this->logger->info("URL appears clean: {$url}");
            return [
                'url_rewrite' => true,
                'details' => 'The URL appears clean and SEO-friendly. No query parameters detected.'
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking URL rewrite for {$url}: " . $e->getMessage());
            return [
                'url_rewrite' => false,
                'details' => "Error checking the URL format. Please review manually. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for underscores in the URL path.
     */
    private function checkUnderscoresInUrl(string $url): array
    {
        $this->logger->info("Checking for underscores in URL: {$url}");
        try {
            $parsed = parse_url($url);
            $path = $parsed['path'] ?? '';
            if (strpos($path, '_') !== false) {
                $this->logger->warning("Underscores found in URL path: {$url}");
                return [
                    'underscores_in_url' => true,
                    'details' => 'The URL contains underscores. It is recommended to use hyphens (-) for word separation.'
                ];
            }
            $this->logger->info("No underscores found in URL: {$url}");
            return [
                'underscores_in_url' => false,
                'details' => 'No underscores were found in the URL path. Your URL structure is good.'
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking underscores in URL {$url}: " . $e->getMessage());
            return [
                'underscores_in_url' => false,
                'details' => "Error checking URL structure. Please review manually. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for embedded objects.
     */
    private function checkEmbeddedObjects(string $html): array
    {
        $this->logger->info("Checking for embedded objects in HTML.");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new \DOMXPath($dom);
            $embeds = $xpath->query('//embed | //object | //applet');
            $count = $embeds->length;
            if ($count > 0) {
                $this->logger->warning("Found {$count} embedded object(s) in HTML.");
                return [
                    'embedded_objects_count' => $count,
                    'details' => "Your page contains {$count} embedded object(s). Consider replacing or removing them if not necessary."
                ];
            } else {
                $this->logger->info("No embedded objects found.");
                return [
                    'embedded_objects_count' => 0,
                    'details' => "No embedded objects found. This is ideal for SEO."
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error checking embedded objects: " . $e->getMessage());
            return [
                'embedded_objects_count' => 0,
                'details' => "Error checking embedded objects. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for iframes.
     */
    private function checkIframes(string $html): array
    {
        $this->logger->info("Checking for iframes in HTML.");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new \DOMXPath($dom);
            $iframes = $xpath->query('//iframe');
            $count = $iframes->length;
            if ($count > 0) {
                $this->logger->warning("Found {$count} iframe(s) in HTML.");
                return [
                    'iframe_count' => $count,
                    'details' => "There are {$count} iframe(s) on your page. Ensure their content is accessible to search engines."
                ];
            } else {
                $this->logger->info("No iframes found.");
                return [
                    'iframe_count' => 0,
                    'details' => "No iframes detected. This is beneficial for SEO."
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error checking iframes: " . $e->getMessage());
            return [
                'iframe_count' => 0,
                'details' => "Error checking iframes. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for a favicon.
     */
    private function checkFavicon(string $html, string $domain): array
    {
        $this->logger->info("Checking for favicon in HTML and default location for domain: {$domain}");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new \DOMXPath($dom);
            $linkIcons = $xpath->query('//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]');
            if ($linkIcons->length > 0) {
                $favicons = [];
                foreach ($linkIcons as $link) {
                    $href = $link->getAttribute('href');
                    $resolvedUrl = $this->resolveUrl($href, $domain);
                    $favicons[] = $resolvedUrl;
                }
                $this->logger->info("Favicon(s) found in HTML.");
                return [
                    'favicon_found' => true,
                    'favicons' => $favicons,
                    'details' => "Favicon detected. Verify that the correct icon is used for branding."
                ];
            } else {
                $faviconUrl = rtrim($this->ensureHttpProtocol($domain), '/') . '/favicon.ico';
                try {
                    $response = $this->httpClient->head($faviconUrl, ['timeout' => 5]);
                    if ($response->getStatusCode() == 200) {
                        $this->logger->info("Favicon found at default location: {$faviconUrl}");
                        return [
                            'favicon_found' => true,
                            'favicons' => [$faviconUrl],
                            'details' => "Favicon found at /favicon.ico. This helps with brand recognition."
                        ];
                    }
                } catch (\Exception $ex) {
                    $this->logger->warning("No favicon at default location: {$faviconUrl} - " . $ex->getMessage());
                }
            }
            $this->logger->warning("No favicon found for domain: {$domain}");
            return [
                'favicon_found' => false,
                'details' => "No favicon detected. Consider adding one to improve branding."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking favicon for {$domain}: " . $e->getMessage());
            return [
                'favicon_found' => false,
                'details' => "Error checking for favicon. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for a custom 404 page.
     */
    private function checkCustom404(string $domain): array
    {
        $this->logger->info("Checking for a custom 404 page on domain: {$domain}");
        $nonExistentUrl = rtrim($this->ensureHttpProtocol($domain), '/') . '/non-existent-page-' . time();
        try {
            $response = $this->httpClient->get($nonExistentUrl, [
                'http_errors' => false,
                'timeout' => 10
            ]);
            $status = $response->getStatusCode();
            $body = (string)$response->getBody();
            if ($status == 404) {
                $defaultMessages = ['Not Found', '404'];
                $isCustom = true;
                foreach ($defaultMessages as $msg) {
                    if (stripos($body, $msg) !== false) {
                        $isCustom = false;
                        break;
                    }
                }
                if ($isCustom) {
                    $this->logger->info("Custom 404 page detected with status: {$status}");
                    return [
                        'custom_404' => true,
                        'status_code' => $status,
                        'details' => "Custom 404 page detected. This improves user experience."
                    ];
                } else {
                    $this->logger->warning("Default 404 page detected with status: {$status}");
                    return [
                        'custom_404' => false,
                        'status_code' => $status,
                        'details' => "Default 404 page detected. Consider creating a custom 404 page."
                    ];
                }
            } else {
                $this->logger->warning("Non-404 status ({$status}) received when testing for a 404 page.");
                return [
                    'custom_404' => false,
                    'status_code' => $status,
                    'details' => "Test did not return a 404 status. Verify your error handling."
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error checking custom 404 page on {$domain}: " . $e->getMessage());
            return [
                'custom_404' => false,
                'status_code' => 0,
                'details' => "Error checking 404 page. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for language declaration.
     */
    private function checkLanguage(string $html): array
    {
        $this->logger->info("Checking for language declaration in HTML.");
        try {
            $dom = $this->createDomDocument($html);
            $htmlTag = $dom->getElementsByTagName('html')->item(0);
            if ($htmlTag && $htmlTag->hasAttribute('lang')) {
                $lang = $htmlTag->getAttribute('lang');
                $this->logger->info("Language declared as: {$lang}");
                return [
                    'language_declared' => true,
                    'language' => $lang,
                    'details' => "Document declares language as '{$lang}'."
                ];
            }
            $this->logger->warning("No language attribute found in <html> tag.");
            return [
                'language_declared' => false,
                'details' => "No language attribute found. Consider adding one (e.g., <html lang='en'>)."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking language in HTML: " . $e->getMessage());
            return [
                'language_declared' => false,
                'details' => "Error checking language. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check visible email addresses for privacy.
     */
    private function checkEmailPrivacy(string $html): array
    {
        $this->logger->info("Checking for visible email addresses.");
        try {
            preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $html, $matches);
            $emails = array_unique($matches[0]);
            if (!empty($emails)) {
                $this->logger->warning("Email addresses found: " . implode(', ', $emails));
                return [
                    'emails_found' => $emails,
                    'details' => "Found email(s): " . implode(', ', $emails) . ". Consider obfuscating or using a contact form."
                ];
            }
            $this->logger->info("No visible email addresses detected.");
            return [
                'emails_found' => [],
                'details' => "No visible email addresses found. Good for privacy."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking email privacy: " . $e->getMessage());
            return [
                'emails_found' => [],
                'details' => "Error checking email addresses. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check mobile compatibility.
     */
    private function checkMobileCompatibility(string $html): array
    {
        $this->logger->info("Checking mobile compatibility.");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new \DOMXPath($dom);
            $viewportMeta = $xpath->query('//meta[@name="viewport"]');
            $hasViewport = ($viewportMeta->length > 0);
            $styles = $xpath->query('//style');
            $responsiveCSS = false;
            foreach ($styles as $style) {
                if (stripos($style->nodeValue, '@media') !== false) {
                    $responsiveCSS = true;
                    break;
                }
            }
            $this->logger->info("Mobile compatibility: viewport=" . ($hasViewport ? 'present' : 'absent') . ", responsive CSS=" . ($responsiveCSS ? 'detected' : 'not detected'));
            if (!$hasViewport && !$responsiveCSS) {
                $suggestion = "No viewport meta tag and no responsive CSS detected. This may hurt mobile experience.";
            } elseif (!$hasViewport) {
                $suggestion = "Responsive CSS is present but no viewport meta tag found. Add a viewport tag.";
            } elseif (!$responsiveCSS) {
                $suggestion = "Viewport meta tag is present but no responsive CSS detected. Consider adding responsive CSS.";
            } else {
                $suggestion = "Mobile compatibility is well-implemented.";
            }
            return [
                'mobile_compatibility' => ($hasViewport && $responsiveCSS),
                'details' => $suggestion
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking mobile compatibility: " . $e->getMessage());
            return [
                'mobile_compatibility' => false,
                'details' => "Error checking mobile compatibility. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Extract social profile URLs.
     */
    private function checkSocialUrls(string $html): array
    {
        $this->logger->info("Extracting social URLs from HTML.");
        try {
            // List of popular social platforms (keywords).
            $platformKeywords = [
                'facebook',
                'twitter',
                'instagram',
                'linkedin',
                'youtube',
                'pinterest',
                'tiktok',
                'snapchat',
                'reddit',
                'tripadvisor',
                'yelp',
                'foursquare',
                'quora',
                'medium',
                'tumblr'
            ];
            $socialLinks = [];
            $dom = $this->createDomDocument($html);
            $xpath = new \DOMXPath($dom);
            $anchorTags = $xpath->query('//a[@href]');
            foreach ($anchorTags as $a) {
                $href = trim($a->getAttribute('href'));
                if (empty($href)) continue;
                // Normalize URL (lowercase, remove trailing slash).
                $normalizedHref = rtrim(strtolower($href), '/');
                $parsedUrl = parse_url($normalizedHref);
                if (!isset($parsedUrl['host'])) continue;
                $host = strtolower($parsedUrl['host']);
                $path = $parsedUrl['path'] ?? '';
                if (trim($path, '/') === '') {
                    $this->logger->debug("Skipping generic link: {$normalizedHref}");
                    continue;
                }
                foreach ($platformKeywords as $keyword) {
                    if (stripos($host, $keyword) !== false) {
                        if (!isset($socialLinks[$keyword])) {
                            $socialLinks[$keyword] = [];
                        }
                        if (!in_array($normalizedHref, $socialLinks[$keyword])) {
                            $socialLinks[$keyword][] = $normalizedHref;
                        }
                        break;
                    }
                }
            }
            // Ensure uniqueness.
            foreach ($socialLinks as $platform => $urls) {
                $socialLinks[$platform] = array_values(array_unique($urls));
            }
            if (!empty($socialLinks)) {
                $detectedPlatforms = array_keys($socialLinks);
                $this->logger->info("Social URLs found for platforms: " . implode(', ', $detectedPlatforms));
                $details = "Social profile links detected for: " . implode(', ', $detectedPlatforms) . ". Verify these links.";
                return [
                    'social_urls' => $socialLinks,
                    'details' => $details
                ];
            }
            $this->logger->info("No social URLs detected.");
            return [
                'social_urls' => [],
                'details' => "No social profile URLs detected. Consider adding links to your official profiles."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error extracting social URLs: " . $e->getMessage());
            return [
                'social_urls' => [],
                'details' => "Error extracting social URLs. Error: " . $e->getMessage()
            ];
        }
    }


 /**
     * Check if the page has a canonical tag.
     *
     * @param string $html The HTML content.
     * @return array Status and friendly suggestion regarding the canonical tag.
     */
    private function checkCanonicalTag(string $html): array
    {
        $this->logger->info("Checking for canonical tag in HTML.");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new DOMXPath($dom);
            $canonical = $xpath->query("//link[@rel='canonical']");
            if ($canonical->length > 0) {
                $url = trim($canonical->item(0)->getAttribute('href'));
                $this->logger->info("Canonical tag found: " . $url);
                return [
                    'canonical_tag_found' => true,
                    'canonical_url'       => $url,
                    'details'             => "A canonical tag was found, which helps prevent duplicate content issues. Ensure this URL is the preferred version of the page."
                ];
            }
            $this->logger->warning("No canonical tag found.");
            return [
                'canonical_tag_found' => false,
                'details'             => "No canonical tag was found. It is recommended to add one to indicate the preferred version of the page and avoid duplicate content."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking canonical tag: " . $e->getMessage());
            return [
                'canonical_tag_found' => false,
                'details'             => "An error occurred while checking for the canonical tag. Error: " . $e->getMessage()
            ];
        }
    }


/**
     * Check for hreflang tags to support multilingual or regional targeting.
     *
     * @param string $html The HTML content.
     * @return array Associative array containing the detected hreflang tags and suggestions.
     */
    private function checkHreflangTags(string $html): array
    {
        $this->logger->info("Checking for hreflang tags in HTML.");
        try {
            $dom = $this->createDomDocument($html);
            $xpath = new DOMXPath($dom);
            $hreflangNodes = $xpath->query("//link[@rel='alternate'][@hreflang]");
            $hreflangs = [];
            if ($hreflangNodes->length > 0) {
                foreach ($hreflangNodes as $node) {
                    $lang = trim($node->getAttribute('hreflang'));
                    $url  = trim($node->getAttribute('href'));
                    if (!empty($lang) && !empty($url)) {
                        $hreflangs[$lang][] = $url;
                    }
                }
                // Remove duplicate URLs per language.
                foreach ($hreflangs as $lang => $urls) {
                    $hreflangs[$lang] = array_unique($urls);
                }
                $this->logger->info("Hreflang tags detected: " . implode(', ', array_keys($hreflangs)));
                return [
                    'hreflang_tags_found' => true,
                    'hreflang_tags'       => $hreflangs,
                    'details'             => "Hreflang tags were detected. Ensure they accurately represent the language and regional targeting of your pages."
                ];
            }
            $this->logger->warning("No hreflang tags found.");
            return [
                'hreflang_tags_found' => false,
                'details'             => "No hreflang tags were detected. If your site targets multiple languages or regions, consider implementing hreflang tags to help search engines serve the correct version."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking hreflang tags: " . $e->getMessage());
            return [
                'hreflang_tags_found' => false,
                'details'             => "An error occurred while checking for hreflang tags. Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check for security-related HTTP headers.
     *
     * @param string $url The URL to check.
     * @return array Associative array with detected security headers and suggestions.
     */
    private function checkSecurityHeaders(string $url): array
    {
        $this->logger->info("Checking security headers for URL: {$url}");
        try {
            $response = $this->httpClient->head($url, ['timeout' => 10]);
            $expectedHeaders = [
                'Content-Security-Policy',
                'X-Frame-Options',
                'X-XSS-Protection',
                'Strict-Transport-Security',
                'X-Content-Type-Options'
            ];
            $securityHeaders = [];
            foreach ($expectedHeaders as $header) {
                $value = $response->getHeaderLine($header);
                if (!empty($value)) {
                    $securityHeaders[$header] = $value;
                }
            }
            if (empty($securityHeaders)) {
                $this->logger->warning("No security headers found.");
                return [
                    'security_headers_found' => false,
                    'security_headers'       => [],
                    'details'                => "No standard security headers were found. It is recommended to implement headers such as Content-Security-Policy, X-Frame-Options, X-XSS-Protection, Strict-Transport-Security, and X-Content-Type-Options to enhance security."
                ];
            }
            $this->logger->info("Security headers found: " . implode(', ', array_keys($securityHeaders)));
            return [
                'security_headers_found' => true,
                'security_headers'       => $securityHeaders,
                'details'                => "Security headers were detected. Review them to ensure they are configured correctly to protect your site."
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error checking security headers: " . $e->getMessage());
            return [
                'security_headers_found' => false,
                'security_headers'       => [],
                'details'                => "An error occurred while checking for security headers. Error: " . $e->getMessage()
            ];
        }
    }


/**
 * ------------------------------------------------------------------------
 * Additional Tools for SEO Analysis
 * ------------------------------------------------------------------------
 */

 private function checkKeywordConsistency(string $html): array 
{
    // Remove <script> and <style> elements using DOMDocument.
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'style'] as $tag) {
        $elements = $dom->getElementsByTagName($tag);
        for ($i = $elements->length - 1; $i >= 0; $i--) {
            $element = $elements->item($i);
            $element->parentNode->removeChild($element);
        }
    }
    
    // Extract body text
    $body = $dom->getElementsByTagName('body')->item(0);
    $bodyText = $body ? $body->textContent : strip_tags($html);
    
    // Clean the body text
    $cleanContent = strtolower($bodyText);
    $cleanContent = preg_replace('/[^a-z\s]/', ' ', $cleanContent);
    $wordsContent = array_filter(explode(' ', $cleanContent), function($word) {
        return strlen($word) > 2;
    });
    
    // Remove stopwords using your getStopWords() method.
    $stopwords = $this->getStopWords();
    $wordsContent = array_filter($wordsContent, function($word) use ($stopwords) {
        return !in_array($word, $stopwords);
    });
    
    // Extract title and meta description
    $title = $this->getTagContent($html, 'title');
    $metaDesc = $this->getMetaTag($html, 'description');
    
    // Extract headings text
    $headings = $this->checkHeadings($html);
    $allHeadingsText = implode(' ', array_merge(...array_values($headings)));
    
    // Process keywords from title, meta, and headings
    $processText = function($text) use ($stopwords) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z\s]/', ' ', $text);
        $words = array_filter(explode(' ', $text), function($word) use ($stopwords) {
            return strlen($word) > 2 && !in_array($word, $stopwords);
        });
        return array_unique($words);
    };
    
    $titleWords = $processText($title);
    $metaWords = $processText($metaDesc);
    $headingWords = $processText($allHeadingsText);
    
    // Compute overall frequency in the body
    $bodyWordFrequency = array_count_values($wordsContent);
    arsort($bodyWordFrequency);
    $bodyTopKeywords = array_slice(array_keys($bodyWordFrequency), 0, 10);
    
    // Count in how many fields each body keyword appears
    $fields = [$titleWords, $metaWords, $headingWords];
    $keywordAppearance = [];
    foreach ($bodyTopKeywords as $word) {
        $count = 0;
        foreach ($fields as $field) {
            if (in_array($word, $field)) {
                $count++;
            }
        }
        $keywordAppearance[$word] = $count;
    }
    
    // Consider a keyword "common" if it appears in at least 2 out of 3 fields
    $commonKeywords = [];
    foreach ($keywordAppearance as $word => $count) {
        if ($count >= 2) {
            $commonKeywords[] = $word;
        }
    }
    
    // Determine missing keywords per field
    $missingInTitle = array_diff($bodyTopKeywords, $titleWords);
    $missingInMeta  = array_diff($bodyTopKeywords, $metaWords);
    $missingInHeadings = array_diff($bodyTopKeywords, $headingWords);
    
    return [
        'common_keywords'     => array_values($commonKeywords),
        'body_top_keywords'   => array_values($bodyTopKeywords),
        'missing_in_title'    => array_values($missingInTitle),
        'missing_in_meta'     => array_values($missingInMeta),
        'missing_in_headings' => array_values($missingInHeadings),
        'suggestions'         => "Ensure that your target keywords appear in the title, meta description, and headings. Consider adding any missing keywords."
    ];
}
 

/**
 * 8B. Structured Data Validator
 *
 * Validates basic structured data (JSON‑LD and microdata) by checking for a few common required fields.
 * This is a simple validator—you may expand it for other schema types.
 */
private function validateStructuredData(DOMDocument $dom): array
{
    $schemaData = $this->checkSchema($dom);
    $results = [];
    if (is_string($schemaData['schema_data'])) {
        // No structured data found.
        return [
            'structured_data_valid' => false,
            'details' => 'No structured data was found on the page.'
        ];
    }

    // For each JSON‑LD type, we do a simple check.
    foreach ($schemaData['schema_data']['json_ld'] as $type => $items) {
        // For example, for "Article" you might expect "headline" and "datePublished"
        if (strtolower($type) === 'article') {
            foreach ($items as $item) {
                if (!isset($item['headline']) || !isset($item['datePublished'])) {
                    $results['Article'][] = 'Missing headline or datePublished';
                } else {
                    $results['Article'][] = 'OK';
                }
            }
        } else {
            // For other types, just mark as detected.
            $results[$type] = "Detected " . count($items) . " items.";
        }
    }

    // For microdata, simply list the types found.
    if (!empty($schemaData['schema_data']['microdata'])) {
        $results['microdata'] = "Detected types: " . implode(', ', array_keys($schemaData['schema_data']['microdata']));
    }

    return [
        'structured_data_valid' => true,
        'details' => $results
    ];
}

/**
 * 8C. Accessibility Checker
 *
 * Performs a basic scan for accessibility issues:
 * - Checks for the presence of common landmark elements (header, nav, main, footer)
 * - Checks that form inputs have associated labels
 * - (Other checks can be added as needed)
 */
private function checkAccessibility(string $html): array
{
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);

    // Check for landmark elements
    $landmarks = ['header', 'nav', 'main', 'footer'];
    $landmarkResults = [];
    foreach ($landmarks as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        $landmarkResults[$tag] = ($nodes->length > 0) ? 'Present' : 'Missing';
    }

    // Check for form controls with labels
    $forms = $dom->getElementsByTagName('form');
    $formIssues = [];
    foreach ($forms as $form) {
        $inputs = $form->getElementsByTagName('input');
        foreach ($inputs as $input) {
            // Ignore input types that typically do not need labels
            $type = strtolower($input->getAttribute('type'));
            if (in_array($type, ['hidden', 'submit', 'button'])) {
                continue;
            }
            // Check if there is an associated <label> element
            $id = $input->getAttribute('id');
            $hasLabel = false;
            if ($id) {
                $labelNodes = $xpath->query("//label[@for='{$id}']");
                if ($labelNodes->length > 0) {
                    $hasLabel = true;
                }
            }
            // Alternatively, check if input is wrapped inside a label element.
            $parentName = $input->parentNode->nodeName;
            if ($parentName === 'label') {
                $hasLabel = true;
            }
            if (!$hasLabel) {
                $formIssues[] = "Input with type '{$type}' is missing a label.";
            }
        }
    }

    $suggestions = [];
    if (in_array('Missing', $landmarkResults)) {
        $suggestions[] = "Some landmark elements are missing (e.g. header, nav, main, footer). Ensure these are used for better accessibility.";
    }
    if (!empty($formIssues)) {
        $suggestions[] = "Form issues: " . implode(' ', $formIssues);
    }
    if (empty($suggestions)) {
        $suggestions[] = "Basic accessibility elements are in place.";
    }
    return [
        'landmark_elements' => $landmarkResults,
        'form_issues' => $formIssues,
        'suggestions' => implode(' ', $suggestions)
    ];
}

/**
 * 8D. Content Readability Analyzer
 *
 * Calculates a Flesch–Kincaid Reading Ease score based on sentence, word, and syllable counts.
 */
private function analyzeContentReadability(string $html): array
{
    // Remove scripts, styles, and HTML tags
    $clean = preg_replace(['/<script\b[^>]*>(.*?)<\/script>/is', '/<style\b[^>]*>(.*?)<\/style>/is'], '', $html);
    $text = strip_tags($clean);
    $text = trim(preg_replace('/\s+/', ' ', $text));

    // Split into sentences (very basic splitting on .!?)
    $sentences = preg_split('/[\.!\?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $sentenceCount = count($sentences);

    // Split into words
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $wordCount = count($words);

    // Count syllables using a basic heuristic
    $syllableCount = 0;
    foreach ($words as $word) {
        $syllableCount += $this->countSyllables($word);
    }

    // Calculate Flesch Reading Ease Score
    if ($sentenceCount == 0 || $wordCount == 0) {
        return ['readability' => 'Not enough text to analyze.'];
    }
    $wordsPerSentence = $wordCount / $sentenceCount;
    $syllablesPerWord = $syllableCount / $wordCount;
    $readingEase = 206.835 - (1.015 * $wordsPerSentence) - (84.6 * $syllablesPerWord);
    $readingEase = round($readingEase, 2);

    $suggestions = [];
    if ($readingEase < 60) {
        $suggestions[] = "The content is difficult to read. Consider simplifying the language.";
    } else {
        $suggestions[] = "The content is easily readable.";
    }

    return [
        'sentence_count' => $sentenceCount,
        'word_count' => $wordCount,
        'syllable_count' => $syllableCount,
        'flesch_reading_ease' => $readingEase,
        'suggestions' => implode(' ', $suggestions)
    ];
}

/**
 * Helper function to count syllables in a word.
 * This is a very basic heuristic and may not be 100% accurate.
 */
private function countSyllables(string $word): int
{
    $word = strtolower($word);
    // Remove non-alphabetic characters
    $word = preg_replace('/[^a-z]/', '', $word);
    if (empty($word)) {
        return 0;
    }
    // Count vowel groups as syllables
    preg_match_all('/[aeiouy]+/', $word, $matches);
    $syllableCount = count($matches[0]);
    // Special adjustments
    if (substr($word, -1) == 'e') {
        $syllableCount--;
    }
    if ($syllableCount <= 0) {
        $syllableCount = 1;
    }
    return $syllableCount;
}



/*      End of Class***/

/**
 * -------------------------------
 * Additional SEO Tests Functions
 * -------------------------------
 */

/**
 * DOM Size Test
 * Checks the total number of nodes in the DOM against a recommended threshold.
 */
private function checkDomSize(string $html): array {
    $dom = $this->createDomDocument($html);
    $nodes = $dom->getElementsByTagName('*');
    $nodeCount = $nodes->length;
    $threshold = 1500;
    return [
        'node_count' => $nodeCount,
        'threshold'  => $threshold,
        'passed'     => ($nodeCount <= $threshold),
        'details'    => ($nodeCount <= $threshold)
                          ? "DOM size ($nodeCount nodes) is within the recommended limit."
                          : "DOM size ($nodeCount nodes) exceeds the recommended limit of $threshold nodes. Consider simplifying your page structure."
    ];
}

/**
 * Page Cache Test
 * Checks whether caching headers are present in the HTML response.
 */
private function checkPageCache(string $url): array {
    try {
        $response = $this->httpClient->head($url, ['timeout' => 10]);
        $cacheControl = $response->getHeaderLine('Cache-Control');
        $passed = (stripos($cacheControl, 'max-age') !== false || stripos($cacheControl, 'public') !== false);
        return [
            'cache_control' => $cacheControl,
            'passed'        => $passed,
            'details'       => $passed
                               ? "Page caching is enabled (Cache-Control: $cacheControl)."
                               : "Caching headers are missing. Consider enabling server‑side caching."
        ];
    } catch (\Exception $e) {
        return [
            'error'   => true,
            'details' => "Error checking page cache: " . $e->getMessage()
        ];
    }
}

/**
 * CDN Usage Test
 * Scans resource URLs (from <link>, <script>, and <img> tags) for known CDN domains.
 */
private function checkCdnUsage(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    // Get resource URLs from link, script, and image elements.
    $nodes = $xpath->query("//link[@href] | //script[@src] | //img[@src]");
    $resourceUrls = [];
    foreach ($nodes as $node) {
        $attr = $node->hasAttribute('href') ? 'href' : 'src';
        $url = $node->getAttribute($attr);
        if (!empty($url)) {
            $resourceUrls[] = $url;
        }
    }
    // Define a sample list of common CDN domain parts.
    $cdnDomains = ['cloudflare.com', 'akamaihd.net', 'cdn.jsdelivr.net', 'stackpath.bootstrapcdn.com', 'maxcdn.bootstrapcdn.com'];
    $cdnUsed = false;
    $cdnResources = [];
    foreach ($resourceUrls as $url) {
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            foreach ($cdnDomains as $cdn) {
                if (stripos($parsed['host'], $cdn) !== false) {
                    $cdnUsed = true;
                    $cdnResources[] = $url;
                    break;
                }
            }
        }
    }
    return [
        'cdn_used'      => $cdnUsed,
        'cdn_resources' => array_values(array_unique($cdnResources)),
        'details'       => $cdnUsed
                           ? "CDN usage detected for some static resources."
                           : "No CDN usage detected. Using a CDN for images, JS, and CSS can improve performance."
    ];
}

/**
 * Check Resource Caching
 * Generic function to check caching headers on a given resource URL.
 */
private function checkResourceCaching(string $url): array {
    try {
        $response = $this->httpClient->head($url, ['timeout' => 10]);
        $cacheControl = $response->getHeaderLine('Cache-Control');
        $passed = (stripos($cacheControl, 'max-age') !== false || stripos($cacheControl, 'public') !== false);
        return [
            'resource_url'  => $url,
            'cache_control' => $cacheControl,
            'passed'        => $passed,
            'details'       => $passed
                               ? "Caching headers are properly set for this resource."
                               : "Caching headers are missing for this resource."
        ];
    } catch (\Exception $e) {
        return [
            'error'   => true,
            'details' => "Error checking caching for resource: " . $e->getMessage()
        ];
    }
}

/**
 * JavaScript Minification Test
 * Checks if a JavaScript file appears minified by examining its average line length.
 */
private function checkJsMinification(string $url): array {
    try {
        $response = $this->httpClient->get($url, ['timeout' => 10]);
        $content = (string)$response->getBody();
        $lines = explode("\n", $content);
        $avgLineLength = array_sum(array_map('strlen', $lines)) / count($lines);
        // Heuristic: an average line length above 200 characters likely indicates minification.
        $isMinified = ($avgLineLength > 200);
        return [
            'average_line_length' => round($avgLineLength, 2),
            'is_minified'         => $isMinified,
            'details'             => $isMinified
                                     ? "JavaScript file appears to be minified."
                                     : "JavaScript file does not appear to be minified."
        ];
    } catch (\Exception $e) {
        return [
            'error'   => true,
            'details' => "Error checking JS minification: " . $e->getMessage()
        ];
    }
}

/**
 * CSS Minification Test
 * Checks if a CSS file appears minified using a similar heuristic as JS.
 */
private function checkCssMinification(string $url): array {
    try {
        $response = $this->httpClient->get($url, ['timeout' => 10]);
        $content = (string)$response->getBody();
        $lines = explode("\n", $content);
        $avgLineLength = array_sum(array_map('strlen', $lines)) / count($lines);
        $isMinified = ($avgLineLength > 200);
        return [
            'average_line_length' => round($avgLineLength, 2),
            'is_minified'         => $isMinified,
            'details'             => $isMinified
                                     ? "CSS file appears to be minified."
                                     : "CSS file does not appear to be minified."
        ];
    } catch (\Exception $e) {
        return [
            'error'   => true,
            'details' => "Error checking CSS minification: " . $e->getMessage()
        ];
    }
}

/**
 * Render Blocking Resources Test
 * Checks for CSS and synchronous JavaScript in the <head> section.
 */
private function checkRenderBlockingResources(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $cssFiles = $xpath->query("//head//link[@rel='stylesheet']");
    $jsFiles = $xpath->query("//head//script[not(@async) and not(@defer)]");
    $blockingCount = $cssFiles->length + $jsFiles->length;
    return [
        'blocking_resources_count' => $blockingCount,
        'details' => ($blockingCount == 0)
                     ? "No render blocking resources detected in the head section."
                     : "Found $blockingCount render blocking resources in the head section. Consider deferring or async loading JS and inlining critical CSS."
    ];
}

/**
 * Nested Tables Test
 * Checks if there are any nested tables in the HTML.
 */
private function checkNestedTables(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $tables = $xpath->query("//table");
    $nestedCount = 0;
    foreach ($tables as $table) {
        $innerTables = $table->getElementsByTagName('table');
        if ($innerTables->length > 0) {
            $nestedCount++;
        }
    }
    return [
        'nested_tables_count' => $nestedCount,
        'details' => ($nestedCount == 0)
                     ? "No nested tables found."
                     : "Found $nestedCount tables with nested tables. Nested tables can slow down page rendering."
    ];
}

/**
 * Frameset Test
 * Checks if the HTML uses a <frameset> tag.
 */
private function checkFrameset(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $frameset = $xpath->query("//frameset");
    return [
        'frameset_found' => ($frameset->length > 0),
        'details' => ($frameset->length > 0)
                     ? "Frameset detected. Frames are generally not recommended for SEO."
                     : "No frameset detected."
    ];
}

/**
 * Time To First Byte (TTFB) Test
 * Measures the time it takes for the first byte to be received.
 */
private function checkTTFB(string $url): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $start = microtime(true);
    curl_exec($ch);
    $ttfb = microtime(true) - $start;
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'ttfb_seconds' => round($ttfb, 2),
        'http_code'    => $httpCode,
        'details'      => "Time to first byte is " . round($ttfb, 2) . " seconds."
    ];
}

/**
 * Server Signature Test
 * Retrieves the Server header from the HTTP response.
 */
private function checkServerSignature(string $url): array {
    try {
        $response = $this->httpClient->head($url, ['timeout' => 10]);
        $server = $response->getHeaderLine('Server');
        return [
            'server_signature' => $server,
            'details' => $server
                         ? "Server signature found: $server"
                         : "No server signature found."
        ];
    } catch (\Exception $e) {
        return [
            'error' => true,
            'details' => "Error checking server signature: " . $e->getMessage()
        ];
    }
}

/**
 * Directory Browsing Test
 * Checks if directory listing is enabled by looking for typical markers.
 */
private function checkDirectoryBrowsing(string $url): array {
    try {
        $response = $this->httpClient->get($url, ['http_errors' => false, 'timeout' => 10]);
        $body = (string)$response->getBody();
        $browsingEnabled = stripos($body, 'Index of') !== false;
        return [
            'directory_browsing_enabled' => $browsingEnabled,
            'details' => $browsingEnabled
                         ? "Directory browsing appears to be enabled. This may expose sensitive files."
                         : "Directory browsing is disabled."
        ];
    } catch (\Exception $e) {
        return [
            'error' => true,
            'details' => "Error checking directory browsing: " . $e->getMessage()
        ];
    }
}

/**
 * Unsafe Cross‑Origin Links Test
 * Checks for links with target="_blank" missing rel="noopener" or "noreferrer".
 */
private function checkUnsafeCrossOriginLinks(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $anchors = $xpath->query("//a[@target='_blank']");
    $unsafeLinks = [];
    foreach ($anchors as $a) {
        $rel = $a->getAttribute('rel');
        if (stripos($rel, 'noopener') === false && stripos($rel, 'noreferrer') === false) {
            $unsafeLinks[] = $a->getAttribute('href');
        }
    }
    return [
        'unsafe_cross_origin_links_count' => count($unsafeLinks),
        'unsafe_links' => $unsafeLinks,
        'details' => count($unsafeLinks)
                     ? "Found " . count($unsafeLinks) . " links with target _blank missing rel='noopener' or 'noreferrer'."
                     : "All target _blank links have proper security attributes."
    ];
}

/**
 * Noindex Tag Test
 * Checks if the page contains a meta robots tag with "noindex".
 */
private function checkNoindexTag(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $metaRobots = $xpath->query("//meta[@name='robots']");
    $noindex = false;
    if ($metaRobots->length > 0) {
        $content = strtolower($metaRobots->item(0)->getAttribute('content'));
        if (strpos($content, 'noindex') !== false) {
            $noindex = true;
        }
    }
    return [
        'noindex_tag' => $noindex,
        'details' => $noindex
                     ? "The page contains a noindex tag."
                     : "The page does not contain a noindex tag."
    ];
}

/**
 * Nofollow Tag Test
 * Checks if the page contains a meta robots tag with "nofollow".
 */
private function checkNofollowTag(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $metaRobots = $xpath->query("//meta[@name='robots']");
    $nofollow = false;
    if ($metaRobots->length > 0) {
        $content = strtolower($metaRobots->item(0)->getAttribute('content'));
        if (strpos($content, 'nofollow') !== false) {
            $nofollow = true;
        }
    }
    return [
        'nofollow_tag' => $nofollow,
        'details' => $nofollow
                     ? "The page contains a nofollow tag."
                     : "The page does not contain a nofollow tag."
    ];
}

/**
 * Disallow Directive Test
 * Checks robots.txt for any Disallow directives.
 */
private function checkDisallowDirective(string $domain): array {
    $robots = $this->checkRobotsTxt($domain);
    if (isset($robots['robots_txt_content'])) {
        preg_match_all('/Disallow:\s*(\S+)/i', $robots['robots_txt_content'], $matches);
        $disallow = array_unique($matches[1]);
        return [
            'disallow_directives' => $disallow,
            'details' => !empty($disallow)
                         ? "Found disallow directives: " . implode(', ', $disallow)
                         : "No disallow directives found in robots.txt."
        ];
    } else {
        return [
            'disallow_directives' => [],
            'details' => "Robots.txt not found or could not be read."
        ];
    }
}

/**
 * Meta Refresh Test
 * Checks for a meta refresh tag.
 */
private function checkMetaRefresh(string $html): array {
    $dom = $this->createDomDocument($html);
    $xpath = new DOMXPath($dom);
    $metaRefresh = $xpath->query("//meta[@http-equiv='refresh']");
    return [
        'meta_refresh_found' => ($metaRefresh->length > 0),
        'details' => ($metaRefresh->length > 0)
                     ? "Meta refresh tag detected. This may negatively impact user experience and SEO."
                     : "No meta refresh tag detected."
    ];
}

/**
 * SPF Records Test
 * Checks if the domain has an SPF record.
 */
private function checkSpfRecords(string $domain): array {
    $records = dns_get_record($domain, DNS_TXT);
    $spfFound = false;
    foreach ($records as $record) {
        if (isset($record['txt']) && stripos($record['txt'], 'v=spf1') !== false) {
            $spfFound = true;
            break;
        }
    }
    return [
        'spf_record_found' => $spfFound,
        'details' => $spfFound
                     ? "SPF record found for the domain."
                     : "No SPF record found. It is recommended to add an SPF record to improve email deliverability and security."
    ];
}

 
/**
 * Ads.txt Validation Test
 * Checks if ads.txt exists on the domain and performs a simple validation.
 */
private function checkAdsTxtValidation(string $domain): array {
    $adsUrl = rtrim($this->ensureHttpProtocol($domain), '/') . '/ads.txt';
    try {
        $response = $this->httpClient->get($adsUrl, ['timeout' => 10]);
        $content = (string)$response->getBody();
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $validLines = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip empty lines or commented lines.
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $validLines++;
            }
        }
        $valid = ($validLines > 0);
        if ($valid) {
            $message = "ads.txt file exists and appears to be valid.";
        } else {
            $message = "ads.txt file exists but its format appears to be incorrect. Please verify that the file follows the proper ads.txt format.";
        }
        return [
            'ads_txt_found' => true,
            'valid_ads_txt' => $valid,
            'lines_count'   => count($lines),
            'details'       => $message,
        ];
    } catch (\Exception $e) {
        // Instead of returning technical error details, we return a user-friendly message.
        return [
            'ads_txt_found' => false,
            'details'       => "No ads.txt file was found on your domain. If you plan to serve ads, consider adding an ads.txt file to your website's root directory. For more information, please refer to the IAB guidelines: https://iabtechlab.com/ads-txt/"
        ];
    }
}



private function parseRobotsTxt(string $content): array
{
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $parsed = [];
    $currentUserAgent = null;
    foreach ($lines as $line) {
        // Remove comments and trim whitespace.
        $line = trim(preg_replace('/#.*$/', '', $line));
        if (empty($line)) {
            continue;
        }
        if (strpos($line, ':') !== false) {
            list($directive, $value) = explode(':', $line, 2);
            $directive = strtolower(trim($directive));
            $value = trim($value);
            switch ($directive) {
                case 'user-agent':
                    $currentUserAgent = strtolower($value);
                    if (!isset($parsed['user_agents'][$currentUserAgent])) {
                        $parsed['user_agents'][$currentUserAgent] = [
                            'allow'       => [],
                            'disallow'    => [],
                            'crawl-delay' => null,
                            'sitemap'     => [],
                        ];
                    }
                    break;
                case 'allow':
                    if ($currentUserAgent) {
                        $parsed['user_agents'][$currentUserAgent]['allow'][] = $value;
                    }
                    break;
                case 'disallow':
                    if ($currentUserAgent) {
                        $parsed['user_agents'][$currentUserAgent]['disallow'][] = $value;
                    }
                    break;
                case 'crawl-delay':
                    if ($currentUserAgent) {
                        $parsed['user_agents'][$currentUserAgent]['crawl-delay'] = $value;
                    }
                    break;
                case 'sitemap':
                    $parsed['sitemaps'][] = $value;
                    break;
                default:
                    // Handle other directives if necessary.
                    break;
            }
        }
    }
    return $parsed;
}

private function analyzeRobotsTxt(array $parsedRobots, string $baseUrl): array
{
    $details = [];
    $recommendations = [];
    
    if (isset($parsedRobots['user_agents'])) {
        foreach ($parsedRobots['user_agents'] as $agent => $rules) {
            if ($agent === '*') { // Global rules
                $details['Global Rules'] = [];
                if (!empty($rules['disallow'])) {
                    $details['Global Rules']['Disallowed Paths'] = $rules['disallow'];
                    foreach ($rules['disallow'] as $path) {
                        if ($path === '/' || $path === '') {
                            $recommendations[] = "The root path '/' is disallowed. This blocks all crawling—revise it if needed.";
                        }
                    }
                }
                if (!empty($rules['allow'])) {
                    $details['Global Rules']['Allowed Paths'] = $rules['allow'];
                }
                if (!empty($rules['crawl-delay'])) {
                    $details['Global Rules']['Crawl Delay'] = $rules['crawl-delay'];
                    if ($rules['crawl-delay'] > 10) {
                        $recommendations[] = "A crawl delay of {$rules['crawl-delay']} seconds might slow indexing. Consider lowering it.";
                    }
                }
                if (isset($parsedRobots['sitemaps'])) {
                    $details['Global Rules']['Sitemaps'] = $parsedRobots['sitemaps'];
                } else {
                    $recommendations[] = "No sitemap declared in robots.txt. Adding one can help search engines index your site.";
                }
            }
        }
    }
    if (empty($recommendations)) {
        $recommendations[] = "Your robots.txt appears to be properly configured.";
    }
    return [
        'details' => $details,
        'recommendations' => $recommendations,
    ];
}


private function checkTwitterCards(DOMDocument $dom): array
{
    $twitterData = [];
    $xpath = new DOMXPath($dom);
    // Look for meta tags with name starting with "twitter:"
    $metaTags = $xpath->query('//meta[starts-with(translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"twitter:")]');
    foreach ($metaTags as $meta) {
        $name = strtolower($meta->getAttribute('name'));
        $content = trim($meta->getAttribute('content'));
        if ($name && $content) {
            // Remove the "twitter:" prefix.
            $key = str_replace('twitter:', '', $name);
            $twitterData[$key] = $content;
        }
    }
    return $twitterData;
}



private function checkMissingTwitterTags(DOMDocument $dom): array
{
    $existing = $this->checkTwitterCards($dom);
    $required = [
        'card'    => 'Specify the card type (e.g., summary or summary_large_image).',
        'site'    => 'Specify the Twitter username of your site (e.g., @yourhandle).',
        'creator' => 'Specify the Twitter username of the content creator.',
    ];
    $missing = [];
    foreach ($required as $tag => $desc) {
        if (empty($existing[$tag])) {
            $missing["twitter:{$tag}"] = [
                'description' => $desc,
                'suggestion'  => "Add a twitter:{$tag} meta tag with the appropriate value."
            ];
        }
    }
    return $missing;
}


private function extractDomain(string $urlOrDomain): string
{
    $urlOrDomain = trim($urlOrDomain);
    $parsed = parse_url($urlOrDomain);
    $host = $parsed['host'] ?? $urlOrDomain;
    // Remove "www." if present.
    return preg_replace('/^www\./i', '', $host);
}


private function fetchDomainRdap(string $urlOrDomain): array
{
    $domain = $this->extractDomain($urlOrDomain);
    if (empty($domain)) {
        $this->logger->warning("No valid domain found in input: {$urlOrDomain}");
        return [];
    }
    $rdapUrl = "https://rdap.org/domain/{$domain}";
    try {
        $response = file_get_contents($rdapUrl);
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning("Invalid JSON returned by RDAP for {$domain}");
            return [];
        }
        return $data;
    } catch (\Exception $e) {
        $this->logger->error("Error fetching RDAP data for {$domain}: " . $e->getMessage());
        return [];
    }
}

 


}

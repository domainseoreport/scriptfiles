<?php
namespace App\Libraries;

use CodeIgniter\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Responsible for:
 *  - Normalizing a raw domain name
 *  - Checking DNS
 *  - Checking accessibility (HTTP/HTTPS)
 *  - Inserting/updating `domains` table
 */
class DomainManager
{
    protected $db;
    protected $logger;

    public function __construct(ConnectionInterface $db, LoggerInterface $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    /**
     * Given a raw domain like "www.Example.com",
     *  1) Normalize it => "example.com"
     *  2) Check DNS + accessibility
     *  3) Insert/update `domains` table
     *  4) Return [ 'domain_id' => ..., 'accessible_url' => ... ] or null if invalid
     */
    public function validateAndStoreDomain(string $rawDomain): ?array
    {
        // 1) Normalize
        $normalized = $this->normalizeDomain($rawDomain);
        if (!$normalized) {
            $this->logger->warning("DomainManager: Invalid domain format: {$rawDomain}");
            return null;
        }

        // 2) Check DNS
        if (!$this->hasValidDNS($normalized)) {
            $this->logger->warning("DomainManager: DNS check failed for {$normalized}");
            return null;
        }

        // 3) Check accessibility => choose https://example.com if it works, etc.
        $accessibleUrl = $this->isDomainAccessible($normalized);
        if (!$accessibleUrl) {
            $this->logger->warning("DomainManager: No accessible URL for {$normalized}");
            return null;
        }

        // ------------------------------------------------------------------
        // PATCH for BulkProcessController + SeoAnalyzer:
        // We update the `bulk_domains.domain` field to store a *full* URL,
        // so that next time the BulkProcessController loads `$row['domain']`,
        // it already has "https://" or "http://".
        // ------------------------------------------------------------------
        try {
            $affected = $this->db->table('bulk_domains')
                ->where('domain', $rawDomain)
                ->update(['domain' => $accessibleUrl]);

            if ($affected) {
                $this->logger->info("Updated bulk_domains => domain='{$accessibleUrl}' (was '{$rawDomain}')");
            }
        } catch (\Exception $e) {
            // Not fatal if we can't update, but log it
            $this->logger->warning("DomainManager: Could not update bulk_domains: " . $e->getMessage());
        }

        // 4) Insert or update in `domains` table
        $existing = $this->db->table('domains')
            ->where('canonical_domain', $normalized)
            ->get()
            ->getRowArray();

        if (!$existing) {
            // Create new row
            $slug = $this->slugify($normalized);
            $this->db->table('domains')->insert([
                'subdomain_slug'   => $slug,
                // Save either the user input or the final accessibleUrl in 'domain'
                'domain'           => $rawDomain,
                'canonical_domain' => $normalized,
                'accessible_url'   => $accessibleUrl,
            ]);
            $domainId = $this->db->insertID();

            $this->logger->info("Inserted new domain #{$domainId} => {$normalized}");
        } else {
            // Domain already in DB
            $domainId = $existing['id'];
            // Optionally update `accessible_url` if empty or changed
            if (empty($existing['accessible_url'])) {
                $this->db->table('domains')
                         ->where('id', $domainId)
                         ->update(['accessible_url' => $accessibleUrl]);
            }
            $this->logger->info("Domain #{$domainId} already exists => {$normalized}");
        }

        // Return the domain_id + the final best URL
        return [
            'domain_id'      => $domainId,
            'accessible_url' => $accessibleUrl,
        ];
    }

    // ------------------------------------------
    //  Helpers from your FrontController, etc.
    // ------------------------------------------

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        // Must match e.g. "example.com" or "sub.example.com"
        if (!preg_match('/^(?!\-)([a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', $domain)) {
            return null;
        }
        return $domain;
    }

    private function hasValidDNS(string $domain): bool
    {
        return checkdnsrr($domain, 'A')
            || checkdnsrr($domain, 'AAAA')
            || checkdnsrr($domain, 'CNAME')
            || checkdnsrr($domain, 'MX');
    }

    /**
     * Attempt to figure out the best working URL:
     *  1) Check "https://example.com", "http://example.com"
     *  2) Also "https://www.example.com", etc. if domain only has 1 or 2 labels
     */
    private function isDomainAccessible($domain)
    {
        $protocols = ['https://', 'http://'];
        $timeout = 10;
        $maxRedirects = 5;
        $userAgent = 'Mozilla/5.0 (compatible; SEO-Checker/1.0)';

        // Determine if we should check www/non-www
        $domainParts = explode('.', $domain);
        $checkWWW = (count($domainParts) <= 2); // Only check 'www.' for short domains

        $checkedUrls = [];
        $effectiveUrls = [];

        // 1) Build protocol + domain combos
        foreach ($protocols as $protocol) {
            // first check domain as is
            $variations = [$protocol . $domain];

            if ($checkWWW) {
                $variations[] = $protocol . 'www.' . $domain;
                $variations[] = $protocol . $domain;
            }

            // 2) cURL test each variation
            foreach ($variations as $url) {
                if (in_array($url, $checkedUrls)) {
                    continue;
                }
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
                    CURLOPT_NOBODY => false, // Full GET request
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $curlError = curl_error($ch);
                $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
                curl_close($ch);

                log_message('debug', "Checked: {$url} => {$effectiveUrl} [{$httpCode}]");

                // 2xx or 3xx => consider success
                if (!$curlError && $httpCode >= 200 && $httpCode < 400) {
                    $effectiveUrl = rtrim($effectiveUrl, '/');
                    $scheme = parse_url($effectiveUrl, PHP_URL_SCHEME);
                    $host   = parse_url($effectiveUrl, PHP_URL_HOST);
                    // We'll keep track of 'https' priority, 'www' usage, # of redirects, etc.
                    $effectiveUrls[$effectiveUrl] = [
                        'https'    => ($scheme === 'https'),
                        'www'      => (strpos($host, 'www.') === 0),
                        'redirects'=> $redirectCount
                    ];
                }
            }
        }

        // 3) Sort by priority: HTTPS > non-WWW > fewer redirects
        uasort($effectiveUrls, function($a, $b) {
            // first prefer 'https' => true
            if ($a['https'] !== $b['https']) {
                return $b['https'] <=> $a['https'];
            }
            // next prefer 'non-www' => a['www'] < b['www']
            if ($a['www'] !== $b['www']) {
                return $a['www'] <=> $b['www'];
            }
            // then fewer redirects
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

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}

<?php
/**
 * ServerInfoHelper.php
 *
 * Provides helper functions for retrieving server-related information.
 */

class ServerInfoHelper {

    /**
     * Retrieves IP information (location, ISP, etc.) for a given IP address.
     *
     * In a production environment you might query an external API (like ip-api.com,
     * ipinfo.io, or similar). For demonstration purposes, this returns dummy data.
     *
     * @param string $ip The IP address.
     * @return array An associative array with keys such as 'country', 'region', 'city', 'isp'.
     */
    private function getIpInfo(string $ip): array
{
    // You can use any external IP info API (ip-api, ipinfo, etc.) or your own logic.
    // This is just a fallback dummy.
    // If you do call an external API, be sure to handle timeouts or errors gracefully.
    return [
        'country' => 'Unknown Country',
        'region'  => 'Unknown Region',
        'city'    => 'Unknown City',
        'isp'     => 'Unknown ISP'
    ];
}

    /**
     * Performs a WHOIS lookup for a given domain.
     *
     * In a production environment you might use shell_exec() with the whois command
     * or a dedicated PHP WHOIS library. For demonstration purposes, this returns dummy data.
     *
     * @param string $domain The domain name.
     * @return string The WHOIS information as a string.
     */
    private function whoislookup(string $domain): string {
        $rdapUrl = "https://rdap.org/domain/{$domain}";
        try {
            $response = file_get_contents($rdapUrl);
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Optionally, format the RDAP data as a string.
                return print_r($data, true);
            }
        } catch (\Exception $e) {
            return "Error fetching WHOIS: " . $e->getMessage();
        }
        return "No WHOIS data available.";
    }

 /**
     * Recursively renders a table (or nested tables) for an array of key => value pairs.
     *
     * @param array  $data     The array of certificate data.
     * @param string $caption  Optional heading or caption for the table.
     * @return string HTML output.
     */
    public static function renderSslData(array $data, string $caption = ''): string {
        // Start building the table.
        $html = '<table class="table table-bordered table-sm mb-3">';
        if ($caption !== '') {
            $html .= '<caption><strong>' . htmlspecialchars($caption) . '</strong></caption>';
        }
        $html .= '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';

        // Loop through each key => value in the array.
        foreach ($data as $key => $value) {
            // Convert $key to a user-friendly label.
            $label = ucwords(str_replace('_', ' ', (string)$key));

            // If the value is an array, render a nested table.
            if (is_array($value)) {
                // Recursively call the static method using self::
                $nestedTable = self::renderSslData($value, $label);
                $html .= '<tr><td colspan="2">' . $nestedTable . '</td></tr>';
            } else {
                // Otherwise, just display key => value in a row.
                $html .= '<tr><td>' . htmlspecialchars($label) . '</td><td>' . htmlspecialchars((string)$value) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';
        return $html;
    }
}
?>

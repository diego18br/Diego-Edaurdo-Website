<?php
/**
 * PerformanceService - Integration with Google PageSpeed Insights API
 *
 * Fetches performance scores and Core Web Vitals metrics.
 * No API key required for basic usage.
 */

class PerformanceService {
    private $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Get performance metrics for a URL
     *
     * @param string $url The website URL to analyze
     * @param string $strategy 'mobile' or 'desktop'
     * @return array Performance metrics
     */
    public function getPerformanceMetrics($url, $strategy = 'mobile') {
        if (empty($url)) {
            return $this->getDefaultResponse('No URL provided');
        }

        // Build API URL with query parameters
        $queryParams = http_build_query([
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance'
        ]);

        $ch = curl_init($this->apiUrl . '?' . $queryParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // PageSpeed can be slow
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return $this->getDefaultResponse('Failed to fetch performance data');
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['lighthouseResult'])) {
            return $this->getDefaultResponse('Invalid response from PageSpeed API');
        }

        return $this->parseResponse($data, $strategy);
    }

    /**
     * Parse PageSpeed response into our standard format
     *
     * @param array $data Raw API response
     * @param string $strategy The strategy used (mobile/desktop)
     * @return array Formatted performance data
     */
    private function parseResponse($data, $strategy) {
        $lighthouse = $data['lighthouseResult'];
        $audits = $lighthouse['audits'] ?? [];

        // Overall performance score (0-100)
        $score = round(($lighthouse['categories']['performance']['score'] ?? 0) * 100);

        // Core Web Vitals
        $metrics = [
            'fcp' => $this->extractMetric($audits, 'first-contentful-paint'),
            'lcp' => $this->extractMetric($audits, 'largest-contentful-paint'),
            'cls' => $this->extractMetric($audits, 'cumulative-layout-shift', true),
            'tbt' => $this->extractMetric($audits, 'total-blocking-time'),
            'si' => $this->extractMetric($audits, 'speed-index')
        ];

        return [
            'score' => $score,
            'metrics' => $metrics,
            'strategy' => $strategy,
            'available' => true
        ];
    }

    /**
     * Extract a specific metric from audit data
     *
     * @param array $audits Lighthouse audits array
     * @param string $key Audit key name
     * @param bool $isDecimal Whether the value should be kept as decimal
     * @return float|int Metric value
     */
    private function extractMetric($audits, $key, $isDecimal = false) {
        if (!isset($audits[$key]['numericValue'])) {
            return 0;
        }

        $value = $audits[$key]['numericValue'];

        // Convert milliseconds to seconds for time-based metrics
        if (!$isDecimal && $value > 100) {
            return round($value / 1000, 2); // Convert to seconds
        }

        return round($value, 3);
    }

    /**
     * Get default response when data is unavailable
     *
     * @param string $message Error message
     * @return array Default response structure
     */
    private function getDefaultResponse($message) {
        return [
            'score' => 0,
            'metrics' => [
                'fcp' => 0,
                'lcp' => 0,
                'cls' => 0,
                'tbt' => 0,
                'si' => 0
            ],
            'strategy' => 'mobile',
            'available' => false,
            'message' => $message
        ];
    }
}

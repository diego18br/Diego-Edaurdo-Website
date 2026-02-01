<?php
/**
 * UptimeService - Integration with UptimeRobot API
 *
 * Fetches uptime status, response times, SSL info, and incident logs
 * for monitored websites. Enhanced for Solo plan features.
 */

class UptimeService {
    private $apiKey;
    private $apiUrl = 'https://api.uptimerobot.com/v2/getMonitors';

    public function __construct() {
        $this->apiKey = UPTIMEROBOT_API_KEY;
    }

    /**
     * Get monitor status from UptimeRobot with full details
     *
     * @param string $monitorId The UptimeRobot monitor ID
     * @return array|null Parsed monitor data or null on error
     */
    public function getMonitorStatus($monitorId) {
        if (empty($monitorId)) {
            return $this->getDefaultResponse('No monitor configured');
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'api_key' => $this->apiKey,
            'monitors' => $monitorId,
            'custom_uptime_ratios' => '1-7-30-90',
            'response_times' => 1,
            'response_times_limit' => 24,      // Last 24 data points for graph
            'logs' => 1,
            'logs_limit' => 10,                // Last 10 incidents
            'ssl' => 1                          // SSL certificate info
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            return $this->getDefaultResponse('Failed to fetch uptime data');
        }

        $data = json_decode($response, true);

        if (!$data || $data['stat'] !== 'ok' || empty($data['monitors'])) {
            return $this->getDefaultResponse('Invalid response from UptimeRobot');
        }

        return $this->parseResponse($data['monitors'][0]);
    }

    /**
     * Parse UptimeRobot response into our standard format
     *
     * @param array $monitor Raw monitor data from API
     * @return array Formatted monitor data
     */
    private function parseResponse($monitor) {
        // Status codes: 0=paused, 1=not checked yet, 2=up, 8=seems down, 9=down
        $statusMap = [
            0 => 'paused',
            1 => 'pending',
            2 => 'up',
            8 => 'down',
            9 => 'down'
        ];

        $status = $statusMap[$monitor['status']] ?? 'unknown';

        // Parse uptime ratios (format: "99.95-99.87-99.90-99.85")
        $uptimeRatios = explode('-', $monitor['custom_uptime_ratio'] ?? '0-0-0-0');

        // Calculate average response time from recent checks
        $avgResponseTime = 0;
        $responseTimeHistory = [];
        if (!empty($monitor['response_times'])) {
            $times = array_column($monitor['response_times'], 'value');
            $avgResponseTime = count($times) > 0 ? round(array_sum($times) / count($times)) : 0;

            // Format response time history for graph
            foreach ($monitor['response_times'] as $rt) {
                $responseTimeHistory[] = [
                    'time' => date('H:i', $rt['datetime']),
                    'value' => (int)$rt['value']
                ];
            }
            // Reverse to show oldest first
            $responseTimeHistory = array_reverse($responseTimeHistory);
        }

        // Parse SSL certificate info
        $ssl = null;
        if (!empty($monitor['ssl'])) {
            $sslData = $monitor['ssl'];
            $expiryDate = !empty($sslData['expires']) ? strtotime($sslData['expires']) : null;
            $daysUntilExpiry = $expiryDate ? ceil(($expiryDate - time()) / 86400) : null;

            $ssl = [
                'issuer' => $sslData['brand'] ?? 'Unknown',
                'expires' => $sslData['expires'] ?? null,
                'days_until_expiry' => $daysUntilExpiry,
                'status' => $this->getSslStatus($daysUntilExpiry)
            ];
        }

        // Parse incident logs
        $incidents = [];
        if (!empty($monitor['logs'])) {
            foreach ($monitor['logs'] as $log) {
                // Type: 1=down, 2=up, 98=started, 99=paused
                if ($log['type'] == 1 || $log['type'] == 2) {
                    $incidents[] = [
                        'type' => $log['type'] == 1 ? 'down' : 'up',
                        'datetime' => date('c', $log['datetime']),
                        'datetime_formatted' => date('M j, Y g:i A', $log['datetime']),
                        'duration' => isset($log['duration']) ? $this->formatDuration($log['duration']) : null,
                        'reason' => $log['reason']['detail'] ?? null
                    ];
                }
            }
        }

        return [
            'status' => $status,
            'uptime_1d' => floatval($uptimeRatios[0] ?? 0),
            'uptime_7d' => floatval($uptimeRatios[1] ?? 0),
            'uptime_30d' => floatval($uptimeRatios[2] ?? 0),
            'uptime_90d' => floatval($uptimeRatios[3] ?? 0),
            'response_time_avg' => $avgResponseTime,
            'response_time_history' => $responseTimeHistory,
            'ssl' => $ssl,
            'incidents' => $incidents,
            'last_check' => date('c'),
            'available' => true
        ];
    }

    /**
     * Get SSL status based on days until expiry
     */
    private function getSslStatus($days) {
        if ($days === null) return 'unknown';
        if ($days <= 0) return 'expired';
        if ($days <= 7) return 'critical';
        if ($days <= 30) return 'warning';
        return 'valid';
    }

    /**
     * Format duration in seconds to human readable
     */
    private function formatDuration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . 'm';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . 'h';
        } else {
            return round($seconds / 86400, 1) . 'd';
        }
    }

    /**
     * Get default response when data is unavailable
     *
     * @param string $message Error message
     * @return array Default response structure
     */
    private function getDefaultResponse($message) {
        return [
            'status' => 'unknown',
            'uptime_1d' => 0,
            'uptime_7d' => 0,
            'uptime_30d' => 0,
            'uptime_90d' => 0,
            'response_time_avg' => 0,
            'response_time_history' => [],
            'ssl' => null,
            'incidents' => [],
            'last_check' => null,
            'available' => false,
            'message' => $message
        ];
    }
}

<?php
/**
 * MetricsCache - Database cache management for website metrics
 *
 * Handles storing, retrieving, and validating cached metric data
 * to reduce external API calls.
 */

class MetricsCache {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get cached metrics for a website
     *
     * @param int $websiteId Website ID
     * @param string $metricType 'uptime' or 'performance'
     * @return array|null Cached data or null if not found/expired
     */
    public function get($websiteId, $metricType) {
        $stmt = $this->pdo->prepare("
            SELECT data, fetched_at, expires_at
            FROM website_metrics_cache
            WHERE website_id = ? AND metric_type = ?
        ");
        $stmt->execute([$websiteId, $metricType]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        $data = json_decode($result['data'], true);
        $data['cached_at'] = $result['fetched_at'];
        $data['is_stale'] = strtotime($result['expires_at']) < time();

        return $data;
    }

    /**
     * Store metrics in cache
     *
     * @param int $websiteId Website ID
     * @param string $metricType 'uptime' or 'performance'
     * @param array $data Metrics data to cache
     * @return bool Success status
     */
    public function set($websiteId, $metricType, $data) {
        // Determine cache duration based on metric type
        $duration = $metricType === 'uptime'
            ? METRICS_CACHE_DURATION_UPTIME
            : METRICS_CACHE_DURATION_PERFORMANCE;

        $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        $jsonData = json_encode($data);

        $stmt = $this->pdo->prepare("
            INSERT INTO website_metrics_cache (website_id, metric_type, data, fetched_at, expires_at)
            VALUES (?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                data = VALUES(data),
                fetched_at = NOW(),
                expires_at = VALUES(expires_at)
        ");

        return $stmt->execute([$websiteId, $metricType, $jsonData, $expiresAt]);
    }

    /**
     * Check if cached data is still valid (not expired)
     *
     * @param int $websiteId Website ID
     * @param string $metricType 'uptime' or 'performance'
     * @return bool True if cache is valid
     */
    public function isValid($websiteId, $metricType) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM website_metrics_cache
            WHERE website_id = ? AND metric_type = ? AND expires_at > NOW()
        ");
        $stmt->execute([$websiteId, $metricType]);
        return $stmt->fetch() !== false;
    }

    /**
     * Delete cached metrics for a website
     *
     * @param int $websiteId Website ID
     * @param string|null $metricType Specific type or null for all
     * @return bool Success status
     */
    public function delete($websiteId, $metricType = null) {
        if ($metricType) {
            $stmt = $this->pdo->prepare("
                DELETE FROM website_metrics_cache
                WHERE website_id = ? AND metric_type = ?
            ");
            return $stmt->execute([$websiteId, $metricType]);
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM website_metrics_cache WHERE website_id = ?
        ");
        return $stmt->execute([$websiteId]);
    }

    /**
     * Check if refresh is allowed (rate limiting)
     *
     * @param int $websiteId Website ID
     * @param int $clientId Client requesting refresh
     * @return array ['allowed' => bool, 'next_available' => timestamp|null]
     */
    public function canRefresh($websiteId, $clientId) {
        // Count refreshes in the last hour
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count, MAX(created_at) as last_refresh
            FROM metric_refresh_log
            WHERE website_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$websiteId]);
        $result = $stmt->fetch();

        $count = (int)$result['count'];
        $allowed = $count < REFRESH_RATE_LIMIT;

        $nextAvailable = null;
        if (!$allowed && $result['last_refresh']) {
            // Calculate when the oldest refresh in the window expires
            $stmt = $this->pdo->prepare("
                SELECT created_at FROM metric_refresh_log
                WHERE website_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $stmt->execute([$websiteId]);
            $oldest = $stmt->fetch();
            if ($oldest) {
                $nextAvailable = date('c', strtotime($oldest['created_at']) + 3600);
            }
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, REFRESH_RATE_LIMIT - $count),
            'next_available' => $nextAvailable
        ];
    }

    /**
     * Log a refresh action
     *
     * @param int $websiteId Website ID
     * @param int $clientId Client who triggered refresh
     * @return bool Success status
     */
    public function logRefresh($websiteId, $clientId) {
        $stmt = $this->pdo->prepare("
            INSERT INTO metric_refresh_log (website_id, triggered_by, created_at)
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$websiteId, $clientId]);
    }
}

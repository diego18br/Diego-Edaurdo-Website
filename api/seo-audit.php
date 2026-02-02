<?php
/**
 * SEO Audit Endpoint
 * GET /api/seo-audit.php?website_id=X - Get latest audit
 * POST /api/seo-audit.php - Run new audit
 *
 * Returns SEO audit results for a client's website
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services/SeoAuditor.php';

// Require authentication
requireAuth();

$pdo = getDbConnection();
$clientId = $_SESSION['client_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get latest audit
    $websiteId = intval($_GET['website_id'] ?? 0);

    if (!$websiteId) {
        errorResponse('Website ID required');
    }

    // Verify website belongs to client
    $stmt = $pdo->prepare("SELECT id, name, url FROM client_websites WHERE id = ? AND client_id = ? AND is_active = 1");
    $stmt->execute([$websiteId, $clientId]);
    $website = $stmt->fetch();

    if (!$website) {
        errorResponse('Website not found or access denied', 404);
    }

    // Get latest audit
    $stmt = $pdo->prepare("
        SELECT *
        FROM seo_audits
        WHERE website_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$websiteId]);
    $audit = $stmt->fetch();

    if (!$audit) {
        successResponse([
            'website' => $website,
            'audit' => null,
            'message' => 'No audit available. Run an audit to see results.'
        ]);
    }

    // Parse JSON fields
    $audit['audit_data'] = json_decode($audit['audit_data'], true);
    $audit['issues'] = json_decode($audit['issues'], true);
    $audit['recommendations'] = json_decode($audit['recommendations'], true);

    successResponse([
        'website' => $website,
        'audit' => $audit
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Run new audit
    $input = json_decode(file_get_contents('php://input'), true);
    $websiteId = intval($input['website_id'] ?? 0);

    if (!$websiteId) {
        errorResponse('Website ID required');
    }

    // Verify website belongs to client
    $stmt = $pdo->prepare("SELECT id, name, url FROM client_websites WHERE id = ? AND client_id = ? AND is_active = 1");
    $stmt->execute([$websiteId, $clientId]);
    $website = $stmt->fetch();

    if (!$website) {
        errorResponse('Website not found or access denied', 404);
    }

    // Check rate limit (max 1 audit per hour per website)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recent_audits
        FROM seo_audits
        WHERE website_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$websiteId]);
    $rateCheck = $stmt->fetch();

    if ($rateCheck['recent_audits'] > 0) {
        errorResponse('An audit was recently run. Please wait before running another.', 429);
    }

    // Run the audit
    $auditor = new SeoAuditor($website['url']);
    $result = $auditor->audit();

    if (!$result['success']) {
        errorResponse($result['error'] ?? 'Audit failed');
    }

    // Store the audit
    $stmt = $pdo->prepare("
        INSERT INTO seo_audits
        (website_id, overall_score, meta_score, content_score, technical_score, performance_score, audit_data, issues, recommendations)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $websiteId,
        $result['overall_score'],
        $result['scores']['meta'],
        $result['scores']['content'],
        $result['scores']['technical'],
        $result['scores']['performance'],
        json_encode($result),
        json_encode($result['issues']),
        json_encode($result['recommendations'])
    ]);

    $auditId = $pdo->lastInsertId();

    successResponse([
        'website' => $website,
        'audit' => [
            'id' => $auditId,
            'overall_score' => $result['overall_score'],
            'meta_score' => $result['scores']['meta'],
            'content_score' => $result['scores']['content'],
            'technical_score' => $result['scores']['technical'],
            'performance_score' => $result['scores']['performance'],
            'audit_data' => $result,
            'issues' => $result['issues'],
            'recommendations' => $result['recommendations'],
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 'Audit completed successfully');

} else {
    errorResponse('Method not allowed', 405);
}

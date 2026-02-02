<?php
/**
 * SEO Auditor Service
 * Performs comprehensive SEO audits on websites
 */

class SeoAuditor {
    private $url;
    private $html;
    private $dom;
    private $issues = [];
    private $recommendations = [];
    private $scores = [
        'meta' => 0,
        'content' => 0,
        'technical' => 0,
        'performance' => 0
    ];

    public function __construct($url) {
        $this->url = $url;
    }

    /**
     * Run full SEO audit
     */
    public function audit() {
        // Fetch the page
        if (!$this->fetchPage()) {
            return [
                'success' => false,
                'error' => 'Could not fetch the website'
            ];
        }

        // Run all checks
        $metaData = $this->checkMeta();
        $contentData = $this->checkContent();
        $technicalData = $this->checkTechnical();
        $securityData = $this->checkSecurity();

        // Calculate overall score
        $overallScore = round(
            ($this->scores['meta'] * 0.25) +
            ($this->scores['content'] * 0.25) +
            ($this->scores['technical'] * 0.30) +
            ($this->scores['performance'] * 0.20)
        );

        return [
            'success' => true,
            'url' => $this->url,
            'overall_score' => $overallScore,
            'scores' => $this->scores,
            'meta' => $metaData,
            'content' => $contentData,
            'technical' => $technicalData,
            'security' => $securityData,
            'issues' => $this->issues,
            'recommendations' => $this->recommendations
        ];
    }

    /**
     * Fetch the page HTML
     */
    private function fetchPage() {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DiegoEduardoSEOBot/1.0)'
        ]);

        $this->html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($this->html)) {
            return false;
        }

        // Parse HTML
        $this->dom = new DOMDocument();
        @$this->dom->loadHTML($this->html, LIBXML_NOERROR);

        return true;
    }

    /**
     * Check meta tags
     */
    private function checkMeta() {
        $xpath = new DOMXPath($this->dom);
        $score = 100;
        $data = [];

        // Title tag
        $titleNodes = $xpath->query('//title');
        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : null;
        $data['title'] = $title;
        $data['title_length'] = $title ? strlen($title) : 0;

        if (!$title) {
            $score -= 25;
            $this->issues[] = ['type' => 'critical', 'message' => 'Missing title tag'];
            $this->recommendations[] = 'Add a unique, descriptive title tag (50-60 characters)';
        } elseif (strlen($title) < 30) {
            $score -= 10;
            $this->issues[] = ['type' => 'warning', 'message' => 'Title tag is too short (' . strlen($title) . ' chars)'];
            $this->recommendations[] = 'Expand your title to 50-60 characters for better SEO';
        } elseif (strlen($title) > 60) {
            $score -= 5;
            $this->issues[] = ['type' => 'info', 'message' => 'Title tag may be truncated in search results (' . strlen($title) . ' chars)'];
        }

        // Meta description
        $descNodes = $xpath->query('//meta[@name="description"]/@content');
        $description = $descNodes->length > 0 ? trim($descNodes->item(0)->textContent) : null;
        $data['description'] = $description;
        $data['description_length'] = $description ? strlen($description) : 0;

        if (!$description) {
            $score -= 20;
            $this->issues[] = ['type' => 'critical', 'message' => 'Missing meta description'];
            $this->recommendations[] = 'Add a compelling meta description (150-160 characters)';
        } elseif (strlen($description) < 70) {
            $score -= 10;
            $this->issues[] = ['type' => 'warning', 'message' => 'Meta description is too short (' . strlen($description) . ' chars)'];
        } elseif (strlen($description) > 160) {
            $score -= 5;
            $this->issues[] = ['type' => 'info', 'message' => 'Meta description may be truncated (' . strlen($description) . ' chars)'];
        }

        // Open Graph tags
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
        $ogImage = $xpath->query('//meta[@property="og:image"]/@content');

        $data['og_tags'] = [
            'title' => $ogTitle->length > 0,
            'description' => $ogDesc->length > 0,
            'image' => $ogImage->length > 0
        ];

        if ($ogTitle->length === 0 || $ogDesc->length === 0) {
            $score -= 10;
            $this->issues[] = ['type' => 'warning', 'message' => 'Missing Open Graph tags for social sharing'];
            $this->recommendations[] = 'Add og:title, og:description, and og:image meta tags';
        }

        // Canonical URL
        $canonical = $xpath->query('//link[@rel="canonical"]/@href');
        $data['canonical'] = $canonical->length > 0 ? $canonical->item(0)->textContent : null;

        if (!$data['canonical']) {
            $score -= 5;
            $this->issues[] = ['type' => 'info', 'message' => 'Missing canonical URL tag'];
        }

        // Viewport
        $viewport = $xpath->query('//meta[@name="viewport"]/@content');
        $data['viewport'] = $viewport->length > 0;

        if (!$data['viewport']) {
            $score -= 15;
            $this->issues[] = ['type' => 'critical', 'message' => 'Missing viewport meta tag (affects mobile usability)'];
            $this->recommendations[] = 'Add <meta name="viewport" content="width=device-width, initial-scale=1">';
        }

        $this->scores['meta'] = max(0, $score);
        return $data;
    }

    /**
     * Check content structure
     */
    private function checkContent() {
        $xpath = new DOMXPath($this->dom);
        $score = 100;
        $data = [];

        // H1 tags
        $h1Tags = $xpath->query('//h1');
        $data['h1_count'] = $h1Tags->length;
        $data['h1_text'] = [];
        foreach ($h1Tags as $h1) {
            $data['h1_text'][] = trim($h1->textContent);
        }

        if ($h1Tags->length === 0) {
            $score -= 25;
            $this->issues[] = ['type' => 'critical', 'message' => 'Missing H1 heading'];
            $this->recommendations[] = 'Add exactly one H1 tag that describes the page content';
        } elseif ($h1Tags->length > 1) {
            $score -= 10;
            $this->issues[] = ['type' => 'warning', 'message' => 'Multiple H1 tags found (' . $h1Tags->length . ')'];
            $this->recommendations[] = 'Use only one H1 tag per page';
        }

        // Heading structure
        $h2Tags = $xpath->query('//h2');
        $h3Tags = $xpath->query('//h3');
        $data['h2_count'] = $h2Tags->length;
        $data['h3_count'] = $h3Tags->length;

        if ($h2Tags->length === 0) {
            $score -= 10;
            $this->issues[] = ['type' => 'warning', 'message' => 'No H2 headings found'];
            $this->recommendations[] = 'Use H2 headings to structure your content';
        }

        // Images
        $images = $xpath->query('//img');
        $imagesWithoutAlt = $xpath->query('//img[not(@alt) or @alt=""]');
        $data['images_total'] = $images->length;
        $data['images_without_alt'] = $imagesWithoutAlt->length;

        if ($imagesWithoutAlt->length > 0) {
            $score -= min(20, $imagesWithoutAlt->length * 5);
            $this->issues[] = ['type' => 'warning', 'message' => $imagesWithoutAlt->length . ' images missing alt text'];
            $this->recommendations[] = 'Add descriptive alt text to all images';
        }

        // Links
        $links = $xpath->query('//a[@href]');
        $internalLinks = 0;
        $externalLinks = 0;
        $noTextLinks = 0;
        $parsedUrl = parse_url($this->url);
        $domain = $parsedUrl['host'] ?? '';

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);

            if (empty($text) && !$link->getElementsByTagName('img')->length) {
                $noTextLinks++;
            }

            if (strpos($href, 'http') === 0) {
                $linkDomain = parse_url($href, PHP_URL_HOST);
                if ($linkDomain && strpos($linkDomain, $domain) !== false) {
                    $internalLinks++;
                } else {
                    $externalLinks++;
                }
            } else {
                $internalLinks++;
            }
        }

        $data['links'] = [
            'total' => $links->length,
            'internal' => $internalLinks,
            'external' => $externalLinks,
            'no_text' => $noTextLinks
        ];

        if ($noTextLinks > 0) {
            $score -= min(10, $noTextLinks * 2);
            $this->issues[] = ['type' => 'info', 'message' => $noTextLinks . ' links have no anchor text'];
        }

        // Word count (rough estimate)
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            $text = preg_replace('/\s+/', ' ', $body->item(0)->textContent);
            $wordCount = str_word_count($text);
            $data['word_count'] = $wordCount;

            if ($wordCount < 300) {
                $score -= 15;
                $this->issues[] = ['type' => 'warning', 'message' => 'Low content word count (' . $wordCount . ' words)'];
                $this->recommendations[] = 'Consider adding more content (aim for 300+ words)';
            }
        }

        $this->scores['content'] = max(0, $score);
        return $data;
    }

    /**
     * Check technical SEO
     */
    private function checkTechnical() {
        $xpath = new DOMXPath($this->dom);
        $score = 100;
        $data = [];

        // Check HTTPS
        $data['https'] = strpos($this->url, 'https://') === 0;
        if (!$data['https']) {
            $score -= 25;
            $this->issues[] = ['type' => 'critical', 'message' => 'Site not using HTTPS'];
            $this->recommendations[] = 'Install an SSL certificate and redirect HTTP to HTTPS';
        }

        // Check for robots meta
        $robotsMeta = $xpath->query('//meta[@name="robots"]/@content');
        $data['robots_meta'] = $robotsMeta->length > 0 ? $robotsMeta->item(0)->textContent : null;

        if ($data['robots_meta'] && (strpos($data['robots_meta'], 'noindex') !== false)) {
            $score -= 20;
            $this->issues[] = ['type' => 'critical', 'message' => 'Page is set to noindex'];
        }

        // Check language
        $htmlLang = $xpath->query('//html/@lang');
        $data['language'] = $htmlLang->length > 0 ? $htmlLang->item(0)->textContent : null;

        if (!$data['language']) {
            $score -= 5;
            $this->issues[] = ['type' => 'info', 'message' => 'Missing language attribute on HTML tag'];
            $this->recommendations[] = 'Add lang="en" to the <html> tag';
        }

        // Check for favicon
        $favicon = $xpath->query('//link[contains(@rel, "icon")]');
        $data['favicon'] = $favicon->length > 0;

        if (!$data['favicon']) {
            $score -= 5;
            $this->issues[] = ['type' => 'info', 'message' => 'No favicon detected'];
        }

        // Check for structured data
        $jsonLd = $xpath->query('//script[@type="application/ld+json"]');
        $data['structured_data'] = $jsonLd->length > 0;

        if (!$data['structured_data']) {
            $score -= 10;
            $this->issues[] = ['type' => 'info', 'message' => 'No structured data (JSON-LD) found'];
            $this->recommendations[] = 'Add structured data to enhance search appearance';
        }

        // Mobile optimization check
        $data['mobile_optimized'] = true; // Already checked viewport in meta

        $this->scores['technical'] = max(0, $score);
        return $data;
    }

    /**
     * Check security headers
     */
    private function checkSecurity() {
        $score = 100;
        $data = [];

        // Fetch headers
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // Parse headers
        $headerLines = explode("\r\n", $response);
        $headers = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        // Check security headers
        $data['x_frame_options'] = isset($headers['x-frame-options']);
        $data['x_content_type_options'] = isset($headers['x-content-type-options']);
        $data['strict_transport_security'] = isset($headers['strict-transport-security']);
        $data['content_security_policy'] = isset($headers['content-security-policy']);

        $missingHeaders = [];
        if (!$data['x_frame_options']) $missingHeaders[] = 'X-Frame-Options';
        if (!$data['x_content_type_options']) $missingHeaders[] = 'X-Content-Type-Options';
        if (!$data['strict_transport_security']) $missingHeaders[] = 'Strict-Transport-Security';

        if (count($missingHeaders) > 0) {
            $score -= count($missingHeaders) * 10;
            $this->issues[] = ['type' => 'info', 'message' => 'Missing security headers: ' . implode(', ', $missingHeaders)];
        }

        $this->scores['performance'] = max(0, $score);
        return $data;
    }
}

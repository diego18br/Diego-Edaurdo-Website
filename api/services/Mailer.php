<?php
/**
 * Simple SMTP Mailer Service
 * Sends emails via SMTP without external dependencies
 */

class Mailer {
    private $socket;
    private $host;
    private $port;
    private $secure;
    private $username;
    private $password;
    private $debug = false;
    private $lastError = '';

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->secure = SMTP_SECURE;
        $this->username = SMTP_USERNAME;
        $this->password = SMTP_PASSWORD;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function log($message) {
        if ($this->debug) {
            error_log("[SMTP] " . $message);
        }
    }

    private function connect() {
        $host = ($this->secure === 'ssl') ? 'ssl://' . $this->host : $this->host;

        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, 30);

        if (!$this->socket) {
            $this->lastError = "Connection failed: $errstr ($errno)";
            $this->log($this->lastError);
            return false;
        }

        $response = $this->getResponse();
        if (substr($response, 0, 3) !== '220') {
            $this->lastError = "Server did not respond with 220: $response";
            return false;
        }

        return true;
    }

    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        $this->log("RECV: " . trim($response));
        return $response;
    }

    private function sendCommand($command, $expectedCode = null) {
        $this->log("SEND: " . trim($command));
        fputs($this->socket, $command . "\r\n");

        $response = $this->getResponse();

        if ($expectedCode !== null) {
            $code = substr($response, 0, 3);
            if ($code !== $expectedCode) {
                $this->lastError = "Expected $expectedCode, got: $response";
                return false;
            }
        }

        return $response;
    }

    private function authenticate() {
        // Send EHLO
        if (!$this->sendCommand("EHLO " . gethostname(), '250')) {
            return false;
        }

        // Start TLS if using port 587
        if ($this->secure === 'tls') {
            if (!$this->sendCommand("STARTTLS", '220')) {
                return false;
            }

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = "Failed to enable TLS";
                return false;
            }

            if (!$this->sendCommand("EHLO " . gethostname(), '250')) {
                return false;
            }
        }

        // Authenticate
        if (!$this->sendCommand("AUTH LOGIN", '334')) {
            return false;
        }

        if (!$this->sendCommand(base64_encode($this->username), '334')) {
            return false;
        }

        if (!$this->sendCommand(base64_encode($this->password), '235')) {
            return false;
        }

        return true;
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string $textBody Plain text content (optional)
     * @param string $fromEmail From email address
     * @param string $fromName From name
     * @param string $replyTo Reply-to email (optional)
     * @param string $replyToName Reply-to name (optional)
     * @return bool Success or failure
     */
    public function send($to, $subject, $htmlBody, $textBody = '', $fromEmail = null, $fromName = null, $replyTo = null, $replyToName = null) {
        $fromEmail = $fromEmail ?? SMTP_USERNAME;
        $fromName = $fromName ?? FROM_NAME;

        // Connect to SMTP server
        if (!$this->connect()) {
            return false;
        }

        // Authenticate
        if (!$this->authenticate()) {
            fclose($this->socket);
            return false;
        }

        // Set sender
        if (!$this->sendCommand("MAIL FROM:<$fromEmail>", '250')) {
            fclose($this->socket);
            return false;
        }

        // Set recipient
        if (!$this->sendCommand("RCPT TO:<$to>", '250')) {
            fclose($this->socket);
            return false;
        }

        // Start data
        if (!$this->sendCommand("DATA", '354')) {
            fclose($this->socket);
            return false;
        }

        // Build message
        $boundary = md5(time() . rand());

        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";

        if ($replyTo) {
            $replyToName = $replyToName ?? $replyTo;
            $headers .= "Reply-To: $replyToName <$replyTo>\r\n";
        }

        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . time() . "." . md5($to . $subject) . "@" . $this->host . ">\r\n";
        $headers .= "\r\n";

        // Build body
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n\r\n";

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= "--$boundary--\r\n";

        // Send message (escape dots at start of lines)
        $message = $headers . $body;
        $message = str_replace("\n.", "\n..", $message);

        fputs($this->socket, $message);

        // End data
        if (!$this->sendCommand(".", '250')) {
            fclose($this->socket);
            return false;
        }

        // Quit
        $this->sendCommand("QUIT");
        fclose($this->socket);

        return true;
    }
}

/**
 * Helper function to send email using the Mailer class
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '', $replyTo = null, $replyToName = null) {
    $mailer = new Mailer();

    $result = $mailer->send(
        $to,
        $subject,
        $htmlBody,
        $textBody,
        SMTP_USERNAME,
        FROM_NAME,
        $replyTo,
        $replyToName
    );

    if (!$result) {
        error_log("Email send failed: " . $mailer->getLastError());
    }

    return $result;
}

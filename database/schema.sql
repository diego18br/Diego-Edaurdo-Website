-- Diego Eduardo Payment Portal Database Schema
-- Run this in your MySQL database via cPanel's phpMyAdmin

-- Create clients table
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_stripe_customer_id (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Create a sessions table for server-side session storage
-- (PHP uses file-based sessions by default, but DB sessions are more scalable)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    client_id INT DEFAULT NULL,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Website Dashboard Tables
-- ============================================

-- Websites assigned to clients (admin-managed)
CREATE TABLE IF NOT EXISTS client_websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    name VARCHAR(255) NOT NULL,
    uptime_monitor_id VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached metrics to reduce external API calls
CREATE TABLE IF NOT EXISTS website_metrics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    website_id INT NOT NULL,
    metric_type ENUM('uptime', 'performance') NOT NULL,
    data JSON NOT NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (website_id) REFERENCES client_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_website_metric (website_id, metric_type),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting log for metric refreshes
CREATE TABLE IF NOT EXISTS metric_refresh_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    website_id INT NOT NULL,
    triggered_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES client_websites(id) ON DELETE CASCADE,
    FOREIGN KEY (triggered_by) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_website_created (website_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

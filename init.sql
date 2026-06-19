CREATE DATABASE IF NOT EXISTS gisp_db;
USE gisp_db;

CREATE TABLE IF NOT EXISTS measures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    measure_id VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(500) NOT NULL,
    purpose TEXT,
    requirements TEXT,
    required_docs TEXT,
    procedure_steps TEXT,
    npa_section_exists BOOLEAN DEFAULT FALSE,
    url VARCHAR(500),
    scraped_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    measure_id VARCHAR(50) NOT NULL,
    document_name VARCHAR(500),
    document_type VARCHAR(100),
    file_path VARCHAR(500),
    file_hash VARCHAR(64),
    download_error TEXT,
    downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (measure_id) REFERENCES measures(measure_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS document_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    content_text LONGTEXT,
    extracted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS comparison_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    measure_id VARCHAR(50) NOT NULL,
    comparison_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    has_discrepancies BOOLEAN DEFAULT FALSE,
    discrepancies_json JSON,
    summary TEXT,
    FOREIGN KEY (measure_id) REFERENCES measures(measure_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS measure_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    measure_id VARCHAR(50) NOT NULL,
    error_type VARCHAR(100),
    error_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (measure_id) REFERENCES measures(measure_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type ENUM('errors', 'recommendations') NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    content JSON,
    file_path VARCHAR(500)
);
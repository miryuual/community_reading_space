CREATE TABLE IF NOT EXISTS crs_collection (
 id_collection INT AUTO_INCREMENT PRIMARY KEY,
 biblio_id INT NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL,
 author VARCHAR(255) NULL,
 publication_year VARCHAR(30) NULL,
 collection_type VARCHAR(120) NULL,
 cover_url VARCHAR(255) NULL,
 description TEXT NULL,
 synced_at DATETIME NULL,
 INDEX idx_crs_collection_title(title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crs_category (
 id_category INT AUTO_INCREMENT PRIMARY KEY,
 category_name VARCHAR(100) NOT NULL UNIQUE,
 description TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crs_topic (
 id_topic INT AUTO_INCREMENT PRIMARY KEY,
 id_collection INT NOT NULL,
 id_category INT NOT NULL,
 member_id VARCHAR(40) NULL,
 user_id INT NULL,
 title VARCHAR(255) NOT NULL,
 content TEXT NOT NULL,
 status ENUM('Open','Answered','Closed') NOT NULL DEFAULT 'Open',
 is_pinned TINYINT(1) NOT NULL DEFAULT 0,
 is_hidden TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NULL,
 CONSTRAINT fk_crs_topic_collection FOREIGN KEY (id_collection) REFERENCES crs_collection(id_collection),
 CONSTRAINT fk_crs_topic_category FOREIGN KEY (id_category) REFERENCES crs_category(id_category),
 INDEX idx_crs_topic_status(status), INDEX idx_crs_topic_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crs_reply (
 id_reply INT AUTO_INCREMENT PRIMARY KEY,
 id_topic INT NOT NULL,
 member_id VARCHAR(40) NULL,
 user_id INT NULL,
 parent_reply_id INT NULL,
 content TEXT NOT NULL,
 is_solution TINYINT(1) NOT NULL DEFAULT 0,
 is_hidden TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_crs_reply_topic FOREIGN KEY (id_topic) REFERENCES crs_topic(id_topic) ON DELETE CASCADE,
 CONSTRAINT fk_crs_reply_parent FOREIGN KEY (parent_reply_id) REFERENCES crs_reply(id_reply) ON DELETE SET NULL,
 INDEX idx_crs_reply_topic(id_topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crs_vote (
 id_vote INT AUTO_INCREMENT PRIMARY KEY,
 id_topic INT NULL,
 id_reply INT NULL,
 member_id VARCHAR(40) NULL,
 user_id INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_crs_vote_topic FOREIGN KEY (id_topic) REFERENCES crs_topic(id_topic) ON DELETE CASCADE,
 CONSTRAINT fk_crs_vote_reply FOREIGN KEY (id_reply) REFERENCES crs_reply(id_reply) ON DELETE CASCADE,
 INDEX idx_crs_vote_topic(id_topic), INDEX idx_crs_vote_reply(id_reply)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crs_report (
 id_report INT AUTO_INCREMENT PRIMARY KEY,
 id_topic INT NULL,
 id_reply INT NULL,
 member_id VARCHAR(40) NULL,
 user_id INT NULL,
 reason TEXT NOT NULL,
 status ENUM('Pending','Reviewed','Rejected') NOT NULL DEFAULT 'Pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_crs_report_topic FOREIGN KEY (id_topic) REFERENCES crs_topic(id_topic) ON DELETE CASCADE,
 CONSTRAINT fk_crs_report_reply FOREIGN KEY (id_reply) REFERENCES crs_reply(id_reply) ON DELETE CASCADE,
 INDEX idx_crs_report_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO crs_category(category_name,description) VALUES
('Pertanyaan','Pertanyaan mengenai isi atau pemahaman koleksi'),
('Ulasan','Ulasan dan pengalaman membaca'),
('Interpretasi','Penafsiran gagasan atau isi bacaan'),
('Rekomendasi','Rekomendasi koleksi untuk pembaca lain');

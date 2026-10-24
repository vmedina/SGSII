CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    subject VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    CONSTRAINT chk_users_active CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    room_number VARCHAR(30) NOT NULL,
    location VARCHAR(120) NOT NULL,
    equipment_count INT UNSIGNED NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rooms_name (name),
    UNIQUE KEY uq_rooms_number (room_number),
    CONSTRAINT chk_rooms_active CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    asset_code VARCHAR(50) NOT NULL,
    category VARCHAR(60) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('operational', 'repair', 'out_of_service') NOT NULL DEFAULT 'operational',
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplies_name (name),
    UNIQUE KEY uq_supplies_asset_code (asset_code),
    CONSTRAINT chk_supplies_active CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NULL,
    supply_id INT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    reservation_date DATE NOT NULL,
    start_time CHAR(5) NOT NULL,
    end_time CHAR(5) NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reservations_room_time (room_id, reservation_date, start_time, end_time),
    KEY idx_reservations_supply_time (supply_id, reservation_date, start_time, end_time),
    KEY idx_reservations_user (user_id),
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_reservations_room FOREIGN KEY (room_id) REFERENCES rooms (id),
    CONSTRAINT fk_reservations_supply FOREIGN KEY (supply_id) REFERENCES supplies (id),
    CONSTRAINT chk_reservations_resource CHECK (
        (room_id IS NOT NULL AND supply_id IS NULL AND quantity = 1)
        OR (room_id IS NULL AND supply_id IS NOT NULL AND quantity > 0)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supply_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    loaned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at DATETIME NULL,
    notes VARCHAR(300) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_loans_supply_open (supply_id, returned_at),
    KEY idx_loans_user (user_id),
    CONSTRAINT fk_loans_supply FOREIGN KEY (supply_id) REFERENCES supplies (id),
    CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT chk_loans_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS incidents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NULL,
    supply_id INT UNSIGNED NULL,
    description VARCHAR(1000) NOT NULL,
    urgency ENUM('low', 'medium', 'high') NOT NULL,
    status ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    admin_notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_incidents_room_status (room_id, status),
    KEY idx_incidents_supply_status (supply_id, status),
    KEY idx_incidents_reporter (reporter_id),
    CONSTRAINT fk_incidents_reporter FOREIGN KEY (reporter_id) REFERENCES users (id),
    CONSTRAINT fk_incidents_room FOREIGN KEY (room_id) REFERENCES rooms (id),
    CONSTRAINT fk_incidents_supply FOREIGN KEY (supply_id) REFERENCES supplies (id),
    CONSTRAINT chk_incidents_resource CHECK (
        (room_id IS NOT NULL AND supply_id IS NULL)
        OR (room_id IS NULL AND supply_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

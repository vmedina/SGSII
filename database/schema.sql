PRAGMA foreign_keys = ON;

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    subject TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'user')),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    room_number TEXT NOT NULL UNIQUE,
    location TEXT NOT NULL,
    equipment_count INTEGER NOT NULL DEFAULT 0 CHECK (equipment_count >= 0),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE supplies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    asset_code TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity >= 0),
    status TEXT NOT NULL DEFAULT 'operational' CHECK (status IN ('operational', 'repair', 'out_of_service')),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    room_id INTEGER REFERENCES rooms(id),
    supply_id INTEGER REFERENCES supplies(id),
    quantity INTEGER NOT NULL DEFAULT 1 CHECK (quantity > 0),
    reservation_date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'cancelled')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK ((room_id IS NOT NULL AND supply_id IS NULL AND quantity = 1) OR (room_id IS NULL AND supply_id IS NOT NULL))
);
CREATE INDEX idx_reservations_room_time ON reservations(room_id, reservation_date, start_time, end_time);
CREATE INDEX idx_reservations_supply_time ON reservations(supply_id, reservation_date, start_time, end_time);

CREATE TABLE loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supply_id INTEGER NOT NULL REFERENCES supplies(id),
    user_id INTEGER NOT NULL REFERENCES users(id),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    loaned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    returned_at TEXT,
    notes TEXT NOT NULL DEFAULT ''
);

CREATE TABLE incidents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reporter_id INTEGER NOT NULL REFERENCES users(id),
    room_id INTEGER REFERENCES rooms(id),
    supply_id INTEGER REFERENCES supplies(id),
    description TEXT NOT NULL,
    urgency TEXT NOT NULL CHECK (urgency IN ('low', 'medium', 'high')),
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved')),
    admin_notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TEXT,
    CHECK ((room_id IS NOT NULL AND supply_id IS NULL) OR (room_id IS NULL AND supply_id IS NOT NULL))
);

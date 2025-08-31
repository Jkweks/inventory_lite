CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE TABLE IF NOT EXISTS inventory_items (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(64) UNIQUE NOT NULL,
    parent_sku VARCHAR(64),
    name TEXT NOT NULL,
    unit VARCHAR(16) DEFAULT 'ea',
    category TEXT,
    item_type TEXT,
    item_use TEXT,
    finish TEXT,
    description TEXT,
    image_url TEXT,
    cost_usd NUMERIC(12,2) DEFAULT 0,
    sage_id VARCHAR(64),
    qty_on_hand NUMERIC(12,3) NOT NULL DEFAULT 0,
    qty_committed NUMERIC(12,3) NOT NULL DEFAULT 0,
    min_qty NUMERIC(12,3) DEFAULT 0,
    archived BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT now(),
    updated_at TIMESTAMP DEFAULT now()
);
CREATE TABLE IF NOT EXISTS jobs (id SERIAL PRIMARY KEY, job_number VARCHAR(64) UNIQUE NOT NULL, name TEXT, status VARCHAR(16) NOT NULL DEFAULT 'bid' CHECK (status IN ('bid','active','complete','cancelled')), archived BOOLEAN NOT NULL DEFAULT FALSE, date_released DATE, date_completed DATE, notes TEXT, created_at TIMESTAMP DEFAULT now(), updated_at TIMESTAMP DEFAULT now());
CREATE TABLE IF NOT EXISTS job_materials (id SERIAL PRIMARY KEY, job_id INTEGER NOT NULL REFERENCES jobs(id) ON DELETE CASCADE, item_id INTEGER NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE, qty_committed NUMERIC(12,3) NOT NULL, qty_used NUMERIC(12,3) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT now(), updated_at TIMESTAMP DEFAULT now(), UNIQUE(job_id, item_id));
CREATE TABLE IF NOT EXISTS cycle_counts (id SERIAL PRIMARY KEY, item_id INTEGER NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE, counted_qty NUMERIC(12,3) NOT NULL, count_date DATE NOT NULL DEFAULT CURRENT_DATE, note TEXT, created_at TIMESTAMP DEFAULT now());
CREATE TABLE IF NOT EXISTS inventory_txns (id SERIAL PRIMARY KEY, item_id INTEGER NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE, txn_type VARCHAR(32) NOT NULL CHECK (txn_type IN ('cycle_count','job_release','job_complete','adjustment','return')), qty_delta NUMERIC(12,3) NOT NULL, ref_table VARCHAR(64), ref_id INTEGER, note TEXT, created_at TIMESTAMP DEFAULT now());
CREATE TABLE IF NOT EXISTS item_locations (
    id SERIAL PRIMARY KEY,
    item_id INTEGER NOT NULL REFERENCES inventory_items(id) ON DELETE CASCADE,
    location VARCHAR(16) NOT NULL,
    qty_on_hand NUMERIC(12,3) NOT NULL DEFAULT 0,
    UNIQUE(item_id, location)
);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE OR REPLACE FUNCTION set_updated_at() RETURNS TRIGGER AS $$ BEGIN NEW.updated_at = now(); RETURN NEW; END; $$ LANGUAGE plpgsql;
DO $$ BEGIN
IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'set_updated_at_inventory_items') THEN CREATE TRIGGER set_updated_at_inventory_items BEFORE UPDATE ON inventory_items FOR EACH ROW EXECUTE PROCEDURE set_updated_at(); END IF;
IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'set_updated_at_jobs') THEN CREATE TRIGGER set_updated_at_jobs BEFORE UPDATE ON jobs FOR EACH ROW EXECUTE PROCEDURE set_updated_at(); END IF;
IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'set_updated_at_job_materials') THEN CREATE TRIGGER set_updated_at_job_materials BEFORE UPDATE ON job_materials FOR EACH ROW EXECUTE PROCEDURE set_updated_at(); END IF;
END $$;

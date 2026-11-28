-- Orders M5 (decisions/0177 §1): one QuickBooks invoice per destination under one order.
-- One row per order and destination (dest_key: 'order', 'a<order_addresses.id>', 'ours'; OrderDestinations::destKey).
-- Safe to run twice (CREATE TABLE IF NOT EXISTS). No UPDATE or DELETE. Nothing writes it until the wiring release:
-- until then an order with more than one address still says to send each address as its own order (0177 status).
-- The lane renames the file to the day it ships, and checks the id and charset types against `orders`.
CREATE TABLE IF NOT EXISTS order_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  dest_key VARCHAR(24) NOT NULL,
  position INT NOT NULL DEFAULT 0,
  subtotal_cents INT NOT NULL DEFAULT 0,
  tax_cents INT NOT NULL DEFAULT 0,
  total_cents INT NOT NULL DEFAULT 0,
  deposit_cents INT NOT NULL DEFAULT 0,
  invoice_id VARCHAR(40) NULL,
  invoice_no VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY order_dest (order_id, dest_key),
  KEY order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

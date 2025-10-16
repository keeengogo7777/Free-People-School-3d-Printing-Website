<?php
require __DIR__ . '/db.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS production_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    bottles_collected INT NOT NULL,
    revenue DECIMAL(10, 2) NOT NULL,
    parts_printed INT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');

echo "Таблиця production_logs успішно створена або вже існує.";

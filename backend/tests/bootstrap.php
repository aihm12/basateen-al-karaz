<?php

require __DIR__.'/../vendor/autoload.php';

// Never allow RefreshDatabase to target a non-test database.
if (($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE')) !== 'basateen_al_karaz_test') {
    throw new RuntimeException('Tests require the isolated basateen_al_karaz_test database.');
}

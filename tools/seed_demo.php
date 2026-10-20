<?php
// Uso (linha de comando): php tools/seed_demo.php   — carrega dados de demonstração no banco do config.php
if (PHP_SAPI !== 'cli') exit('Somente linha de comando.');
require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/demo.php';
carregar_demo();
echo "Demo carregada.\n";

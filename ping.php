<?php
// Diagnóstico temporário (apagar depois). Não exibe senhas.
header('Content-Type: text/plain; charset=utf-8');
echo "PHP OK: " . PHP_VERSION . "\n";
foreach (['pdo_mysql', 'mbstring', 'fileinfo'] as $e) echo "ext $e: " . (extension_loaded($e) ? 'sim' : 'NAO') . "\n";
echo "config.php: " . (is_file(__DIR__ . '/config.php') ? 'existe' : 'NAO existe') . "\n";
echo "pasta: " . __DIR__ . "\n";
if (is_file(__DIR__ . '/config.php')) {
    $c = require __DIR__ . '/config.php';
    foreach (['db_host', 'db_nome', 'db_user', 'db_pass', 'url_base'] as $k) echo "$k: " . (empty($c[$k]) ? 'VAZIO' : ($k === 'db_pass' ? '(preenchida)' : $c[$k])) . "\n";
    try { new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_nome'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass']); echo "Banco: CONECTOU\n"; }
    catch (Throwable $e) { echo "Banco: FALHOU (codigo " . $e->getCode() . ")\n"; }
}

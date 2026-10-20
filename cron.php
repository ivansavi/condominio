<?php
// Rotina diária (Cron Job da Hostinger): envia os lembretes de pagamento pendentes.
declare(strict_types=1);
require __DIR__ . '/src/app.php';
header('Content-Type: text/plain; charset=utf-8');
if (PHP_SAPI !== 'cli' && (conf('cron_token') === '' || !hash_equals(conf('cron_token'), (string)($_GET['token'] ?? '')))) { http_response_code(403); exit('Acesso negado.'); }
if (conf('lembretes_ativos') !== '1') exit("Lembretes desativados.\n");
foreach (processar_lembretes() as $tipo => $r) echo "$tipo: {$r['cobrancas']} cobranca(s), {$r['sem_destinatario']} sem destinatario\n";

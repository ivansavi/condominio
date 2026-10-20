<?php
declare(strict_types=1);

define('RAIZ', dirname(__DIR__));
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

$GLOBALS['cfg'] = is_file(RAIZ . '/config.php') ? require RAIZ . '/config.php' : null;

function cfg(string $k, $padrao = null) { return $GLOBALS['cfg'][$k] ?? $padrao; }

if (!empty($GLOBALS['cfg']['debug'])) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); }

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('condo_sess');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ---------- Banco de dados ---------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!$GLOBALS['cfg']) { http_response_code(500); exit('Arquivo config.php não encontrado. Copie config.sample.php para config.php.'); }
    try {
        $pdo = new PDO(
            'mysql:host=' . str_replace(':', ';port=', cfg('db_host')) . ';dbname=' . cfg('db_nome') . ';charset=utf8mb4',
            cfg('db_user'), cfg('db_pass'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {
        http_response_code(500);
        exit(cfg('debug') ? e($e->getMessage()) : 'Não foi possível conectar ao banco de dados.');
    }
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement { $st = db()->prepare($sql); $st->execute($params); return $st; }
function linha(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function linhas(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function valor(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function conf(string $chave, string $padrao = ''): string {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (linhas('SELECT chave, valor FROM configuracoes') as $r) $cache[$r['chave']] = (string)$r['valor']; }
    return $cache[$chave] ?? $padrao;
}
function conf_set(string $chave, string $valor): void {
    q('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$chave, $valor]);
}

/* ---------- Helpers de saída/entrada ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = 'inicio', array $params = []): string { return 'index.php?' . http_build_query(['p' => $p] + $params); }
function redirect(string $p, array $params = []): never { header('Location: ' . url($p, $params)); exit; }
function post(string $k, string $padrao = ''): string { return trim((string)($_POST[$k] ?? $padrao)); }
function get(string $k, string $padrao = ''): string { return trim((string)($_GET[$k] ?? $padrao)); }
function eh_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function flash(string $msg, string $tipo = 'ok'): void { $_SESSION['flash'][] = [$tipo, $msg]; }

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_exigir(): void {
    if (!eh_post()) return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function dinheiro($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function ler_dinheiro(string $s): float {
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    if (str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    return round((float)$s, 2);
}
function data_br(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
function mes_ano(string $d): string { $t = strtotime($d); return ucfirst(MESES[(int)date('n', $t)]) . '/' . date('Y', $t); }
function competencia_valida(string $ym): ?string { return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym) ? $ym . '-01' : null; }
function nome_unidade(array $u): string { return trim(($u['bloco'] !== '' ? 'Bloco ' . $u['bloco'] . ' · ' : '') . 'Apto ' . $u['numero']); }
function paragrafos(string $t): string { return nl2br(e($t)); }

function senha_provisoria(): string {
    $al = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = ''; for ($i = 0; $i < 8; $i++) $s .= $al[random_int(0, strlen($al) - 1)];
    return $s;
}

/* ---------- Autenticação ---------- */
function usuario(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $u = linha('SELECT u.*, un.bloco, un.numero FROM usuarios u LEFT JOIN unidades un ON un.id = u.unidade_id WHERE u.id = ? AND u.ativo = 1', [$_SESSION['uid']]);
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}
function eh_gestao(): bool { $u = usuario(); return $u && ($u['perfil'] === 'gestao' || ($u['perfil'] === 'sindico' && $u['acesso_gestao'])); }
function eh_admin(): bool { $u = usuario(); return $u && in_array($u['perfil'], ['sindico', 'gestao'], true); }
function perfil_label(array $u): string {
    return match ($u['perfil']) { 'gestao' => 'Gestão', 'sindico' => 'Síndico/Administrador' . ($u['acesso_gestao'] ? ' + Gestão' : ''), default => 'Condômino' };
}

function logar(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);
}

function ip_chave(string $email): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($email)); }
function login_bloqueado(string $email): bool {
    q('DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
    return (int)valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > (NOW() - INTERVAL 15 MINUTE)', [ip_chave($email)]) >= 5;
}
function login_falhou(string $email): void { q('INSERT INTO tentativas_login (chave) VALUES (?)', [ip_chave($email)]); }

/* ---------- Financeiro ---------- */
function cobranca_situacao(array $c): string {
    if ($c['status'] === 'aberto' && $c['vencimento'] < date('Y-m-d')) return 'vencido';
    return $c['status'];
}
/** Valor atualizado com multa e juros (padrão: 2% + 1% a.m. pro rata) para cobranças vencidas. */
function cobranca_atualizada(array $c): array {
    $base = (float)$c['valor']; $multa = 0.0; $juros = 0.0; $dias = 0;
    if (cobranca_situacao($c) === 'vencido') {
        $dias = (int)((strtotime(date('Y-m-d')) - strtotime($c['vencimento'])) / 86400);
        $multa = round($base * (float)conf('multa_pct', '2') / 100, 2);
        $juros = round($base * (float)conf('juros_mensal_pct', '1') / 100 / 30 * $dias, 2);
    }
    return ['base' => $base, 'multa' => $multa, 'juros' => $juros, 'dias' => $dias, 'total' => round($base + $multa + $juros, 2)];
}
function selo(array $c): string {
    $s = cobranca_situacao($c);
    $rot = ['aberto' => 'Em aberto', 'vencido' => 'Vencido', 'pago' => 'Pago', 'cancelado' => 'Cancelado'][$s];
    return '<span class="selo selo-' . $s . '">' . $rot . '</span>';
}

/** Resumo de um mês: receitas (pagamentos recebidos + lançamentos), despesas e saldos. */
function resumo_mes(string $comp): array {
    $ini = $comp; $fim = date('Y-m-d', strtotime($comp . ' +1 month'));
    $pagas = linhas("SELECT c.*, un.bloco, un.numero FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id
                     WHERE c.status = 'pago' AND c.data_pagamento >= ? AND c.data_pagamento < ? ORDER BY c.data_pagamento", [$ini, $fim]);
    $rec_cond = array_sum(array_map(fn($c) => (float)($c['valor_pago'] ?? $c['valor']), $pagas));
    $lanc = linhas('SELECT * FROM lancamentos WHERE competencia = ? ORDER BY data, id', [$comp]);
    $outras_rec = 0.0; $desp = 0.0; $por_cat = [];
    foreach ($lanc as $l) {
        if ($l['tipo'] === 'receita') $outras_rec += (float)$l['valor'];
        else { $desp += (float)$l['valor']; $por_cat[$l['categoria']] = ($por_cat[$l['categoria']] ?? 0) + (float)$l['valor']; }
    }
    arsort($por_cat);
    // saldo anterior = saldo inicial + resultado de todos os meses anteriores
    $ant_cond = (float)valor("SELECT COALESCE(SUM(COALESCE(valor_pago, valor)),0) FROM cobrancas WHERE status='pago' AND data_pagamento < ?", [$ini]);
    $ant_lanc = (float)valor("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE -valor END),0) FROM lancamentos WHERE competencia < ?", [$ini]);
    $saldo_ant = (float)conf('saldo_inicial', '0') + $ant_cond + $ant_lanc;
    $receitas = $rec_cond + $outras_rec;
    return [
        'comp' => $comp, 'pagas' => $pagas, 'lancamentos' => $lanc, 'por_categoria' => $por_cat,
        'rec_condominio' => $rec_cond, 'rec_outras' => $outras_rec, 'receitas' => $receitas, 'despesas' => $desp,
        'saldo_anterior' => $saldo_ant, 'saldo_final' => $saldo_ant + $receitas - $desp,
    ];
}

/* ---------- Arquivos ---------- */
function salvar_upload(string $campo): ?int {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio do arquivo.');
    if ($f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Arquivo muito grande (máximo 5 MB).');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Envie apenas PDF, JPG ou PNG.');
    $nome = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], RAIZ . '/storage/uploads/' . $nome)) throw new RuntimeException('Não foi possível salvar o arquivo.');
    q('INSERT INTO arquivos (nome_original, caminho, mime, tamanho) VALUES (?,?,?,?)', [mb_substr(basename($f['name']), 0, 200), $nome, $mime, $f['size']]);
    return (int)db()->lastInsertId();
}

/* ---------- E-mail ---------- */
function enviar_email(string $para, string $assunto, string $texto): bool {
    $de = cfg('email_remetente', 'nao-responder@localhost');
    $nome = conf('condominio_nome', 'Condomínio');
    $h = "From: =?UTF-8?B?" . base64_encode($nome) . "?= <$de>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $texto, $h);
}

/* ---------- Layout ---------- */
function avisos_nao_lidos(): int {
    $u = usuario(); if (!$u) return 0;
    return (int)valor('SELECT COUNT(*) FROM comunicados c WHERE c.ativo = 1 AND NOT EXISTS (SELECT 1 FROM comunicado_leituras l WHERE l.comunicado_id = c.id AND l.usuario_id = ?)', [$u['id']]);
}

function icone(string $n): string {
    $p = [
        'inicio' => 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'boleto' => 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5',
        'contas' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
        'avisos' => 'M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8M10 20a2 2 0 0 0 4 0',
        'perfil' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21a8 8 0 0 1 16 0',
        'moradores' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M2 21a7 7 0 0 1 14 0M17 3.5a4 4 0 0 1 0 7M22 21a7 7 0 0 0-4-6',
        'mais' => 'M5 12h.01M12 12h.01M19 12h.01',
    ];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$n] ?? '') . '"/></svg>';
}

function menu_principal(): array {
    if (eh_gestao()) return [['inicio', 'Início', 'inicio'], ['cobrancas', 'Cobranças', 'boleto'], ['contas_gestao', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['mais', 'Mais', 'mais']];
    if (eh_admin()) return [['inicio', 'Início', 'inicio'], ['usuarios', 'Moradores', 'moradores'], ['prestacoes', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['mais', 'Mais', 'mais']];
    return [['inicio', 'Início', 'inicio'], ['boletos', 'Boletos', 'boleto'], ['prestacoes', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['perfil', 'Perfil', 'perfil']];
}

function pagina_inicio(string $titulo, array $o = []): void {
    $u = usuario(); $atual = $GLOBALS['pagina_atual'] ?? '';
    $nome = conf('condominio_nome', 'Condomínio');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">',
        '<meta name="theme-color" content="#0f766e"><link rel="manifest" href="assets/manifest.webmanifest">',
        '<link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="apple-touch-icon" href="assets/icon.svg">',
        '<title>', e($titulo), ' · ', e($nome), '</title><link rel="stylesheet" href="assets/app.css?v=1"></head><body>';
    if ($u) {
        echo '<header class="topo"><div class="topo-in">';
        if (!empty($o['voltar'])) echo '<a class="voltar" href="', e($o['voltar']), '" aria-label="Voltar">‹</a>';
        echo '<div class="topo-tit"><strong>', e($titulo), '</strong><small>', e($nome), '</small></div>';
        echo '<a class="topo-user" href="', e(url('perfil')), '" aria-label="Meu perfil">', e(mb_strtoupper(mb_substr($u['nome'], 0, 1))), '</a>';
        echo '</div></header>';
    }
    echo '<main class="conteudo', $u ? '' : ' sem-nav', '">';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) echo '<div class="alerta alerta-', e($t), '" role="status">', e($m), '</div>';
    unset($_SESSION['flash']);
}

function pagina_fim(): void {
    $u = usuario(); $atual = $GLOBALS['pagina_atual'] ?? '';
    echo '</main>';
    if ($u) {
        $nao_lidos = avisos_nao_lidos();
        echo '<nav class="nav" aria-label="Menu principal">';
        foreach (menu_principal() as [$p, $rot, $ic]) {
            $ativo = $p === $atual ? ' ativo' : '';
            echo '<a class="nav-item', $ativo, '" href="', e(url($p)), '">', icone($ic);
            if ($p === 'avisos' && $nao_lidos) echo '<span class="badge">', $nao_lidos, '</span>';
            echo '<span>', e($rot), '</span></a>';
        }
        echo '</nav>';
    }
    echo '<script src="assets/app.js?v=1"></script></body></html>';
}

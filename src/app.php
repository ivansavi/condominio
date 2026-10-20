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
        migrar($pdo);
    } catch (Throwable $e) {
        http_response_code(500);
        exit(cfg('debug') ? e($e->getMessage()) : 'Não foi possível conectar ao banco de dados.');
    }
    return $pdo;
}
/** Aplica automaticamente os arquivos de database/migrations/*.sql ainda não executados (ordem alfabética). */
function migrar(PDO $pdo): void {
    $arqs = glob(RAIZ . '/database/migrations/*.sql') ?: [];
    sort($arqs);
    $feitos = static function () use ($pdo): array {
        try { return $pdo->query('SELECT arquivo FROM schema_versao')->fetchAll(PDO::FETCH_COLUMN); }
        catch (PDOException $e) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_versao (arquivo VARCHAR(100) NOT NULL PRIMARY KEY, aplicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            return [];
        }
    };
    $pend = array_filter($arqs, fn($f) => !in_array(basename($f), $feitos(), true));
    if (!$pend) return;
    $pdo->query("SELECT GET_LOCK('condo_migrar', 30)")->fetchColumn();
    try {
        $ja = $feitos();
        foreach ($pend as $f) {
            if (in_array(basename($f), $ja, true)) continue; // outra requisição já aplicou
            foreach (preg_split('/;\s*\n/', (string)file_get_contents($f)) as $cmd) {
                $cmd = trim(preg_replace('/^--.*$/m', '', $cmd));
                if ($cmd !== '') $pdo->exec($cmd);
            }
            $pdo->prepare('INSERT INTO schema_versao (arquivo) VALUES (?)')->execute([basename($f)]);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('condo_migrar')")->fetchColumn();
    }
}

function q(string $sql, array $params = []): PDOStatement { $st = db()->prepare($sql); $st->execute($params); return $st; }
function linha(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function linhas(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function valor(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

/** Chaves de configuração que pertencem ao condomínio atual (coluna correspondente em `condominios`). */
const CONF_CONDOMINIO = ['condominio_nome' => 'nome', 'condominio_cnpj' => 'cnpj', 'condominio_endereco' => 'endereco', 'condominio_telefone' => 'telefone',
    'condominio_email' => 'email', 'pix_chave' => 'pix_chave', 'dia_vencimento' => 'dia_vencimento', 'multa_pct' => 'multa_pct',
    'juros_mensal_pct' => 'juros_mensal_pct', 'saldo_inicial' => 'saldo_inicial'];

function conf(string $chave, string $padrao = ''): string {
    if (isset(CONF_CONDOMINIO[$chave])) {
        $c = condominio_atual();
        $v = $c[CONF_CONDOMINIO[$chave]] ?? null;
        return $v === null || $v === '' ? $padrao : (string)$v;
    }
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try { foreach (linhas('SELECT chave, valor FROM configuracoes') as $r) $cache[$r['chave']] = (string)$r['valor']; } catch (PDOException $e) { /* banco ainda não instalado */ }
    }
    return $cache[$chave] ?? $padrao;
}
function conf_set(string $chave, string $valor): void {
    if (isset(CONF_CONDOMINIO[$chave])) {
        q('UPDATE condominios SET ' . CONF_CONDOMINIO[$chave] . ' = ? WHERE id = ?', [$valor === '' ? null : $valor, cid()]);
        unset($GLOBALS['cond_cache']);
        return;
    }
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
        $u = linha('SELECT * FROM usuarios WHERE id = ? AND ativo = 1', [$_SESSION['uid']]);
        if (!$u) unset($_SESSION['uid']);
    }
    return $u;
}

/* ---------- Condomínios, níveis e permissões ---------- */
const PERMISSOES = [
    'moradores' => 'Moradores, unidades, proprietários e locatários',
    'comunicados' => 'Comunicados e avisos',
    'financeiro_ver' => 'Ver inadimplência e boletos das unidades',
    'cobrancas' => 'Gerar cobranças e dar baixa em boletos',
    'contas' => 'Prestação de contas (lançamentos e publicação)',
    'emails' => 'E-mails e lembretes',
    'condominio' => 'Dados do condomínio, logo e endereço',
    'niveis' => 'Níveis de acesso e equipe (quem acessa o quê)',
];

/** Condomínios que o usuário logado pode abrir: onde tem nível de acesso ou vínculo ativo com uma unidade. */
function condominios_acessiveis(): array {
    $u = usuario();
    if (!$u) return [];
    static $cache = [];
    if (isset($cache[$u['id']])) return $cache[$u['id']];
    if ($u['super_admin']) return $cache[$u['id']] = linhas('SELECT * FROM condominios WHERE ativo = 1 ORDER BY nome');
    return $cache[$u['id']] = linhas('SELECT c.* FROM condominios c WHERE c.ativo = 1 AND (
            EXISTS (SELECT 1 FROM acessos a WHERE a.condominio_id = c.id AND a.usuario_id = ?)
         OR EXISTS (SELECT 1 FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id
                    WHERE un.condominio_id = c.id AND v.usuario_id = ? AND (v.data_fim IS NULL OR v.data_fim >= CURDATE()))) ORDER BY c.nome', [$u['id'], $u['id']]);
}

/** Força um condomínio específico (usado por rotinas sem login, como o envio de lembretes). */
function usar_condominio(int $id): void { $GLOBALS['cond_forcado'] = $id; unset($GLOBALS['cond_cache']); }

function condominio_atual(): ?array {
    $id = $GLOBALS['cond_forcado'] ?? null;
    if (!$id) {
        $u = usuario();
        if ($u) {
            $lista = condominios_acessiveis();
            $ids = array_map(fn($c) => (int)$c['id'], $lista);
            foreach ([(int)($_SESSION['cond'] ?? 0), (int)$u['ultimo_condominio_id']] as $cand) if ($cand && in_array($cand, $ids, true)) { $id = $cand; break; }
            if (!$id && $ids) $id = min($ids); // sem escolha anterior: o primeiro condomínio cadastrado
            if ($id) $_SESSION['cond'] = $id;
        } else {
            try { if ((int)valor('SELECT COUNT(*) FROM condominios') === 1) $id = (int)valor('SELECT id FROM condominios'); } catch (PDOException $e) { return null; }
        }
    }
    if (!$id) return null;
    if (($GLOBALS['cond_cache']['id'] ?? 0) !== $id) {
        try { $GLOBALS['cond_cache'] = linha('SELECT * FROM condominios WHERE id = ?', [$id]) ?? []; } catch (PDOException $e) { return null; }
    }
    return $GLOBALS['cond_cache'] ?: null;
}
function cid(): int { return (int)(condominio_atual()['id'] ?? 0); }

/** Nível de acesso do usuário no condomínio atual (null = apenas condômino). */
function nivel_atual(): ?array {
    $u = usuario(); if (!$u) return null;
    static $cache = [];
    $k = $u['id'] . ':' . cid();
    if (!array_key_exists($k, $cache)) $cache[$k] = linha('SELECT n.* FROM acessos a JOIN niveis_acesso n ON n.id = a.nivel_id WHERE a.usuario_id = ? AND a.condominio_id = ?', [$u['id'], cid()]);
    return $cache[$k];
}
function permissoes_atuais(): array {
    $u = usuario(); if (!$u) return [];
    if ($u['super_admin']) return array_keys(PERMISSOES);
    $n = nivel_atual();
    return $n ? array_values(array_filter(explode(',', $n['permissoes']))) : [];
}
function pode(string $perm): bool { return in_array($perm, permissoes_atuais(), true); }
function eh_admin(): bool { $u = usuario(); return $u && ($u['super_admin'] || nivel_atual()); }
function eh_gestao(): bool { return pode('cobrancas'); }
function rotulo_acesso(): string {
    $u = usuario();
    if ($u && $u['super_admin']) return 'Gestor principal (todos os condomínios)';
    return nivel_atual()['nome'] ?? 'Condômino';
}

/** Unidades do usuário no condomínio atual (vínculo ainda ativo). */
function unidades_do_usuario(?int $uid = null): array {
    $uid ??= (int)(usuario()['id'] ?? 0);
    return linhas('SELECT un.*, v.tipo, v.data_inicio, v.data_fim FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id
                   WHERE v.usuario_id = ? AND un.condominio_id = ? AND (v.data_fim IS NULL OR v.data_fim >= CURDATE()) ORDER BY un.bloco, CAST(un.numero AS UNSIGNED), un.numero', [$uid, cid()]);
}
function ids_unidades_do_usuario(): array { return array_map(fn($u) => (int)$u['id'], unidades_do_usuario()); }
function unidade_do_condominio(int $id): ?array { return linha('SELECT * FROM unidades WHERE id = ? AND condominio_id = ?', [$id, cid()]); }

/** Cria os níveis de acesso padrão de um condomínio novo. */
function semear_niveis(int $cond): int {
    $padrao = [
        ['Gestor principal', 'Acesso total ao condomínio', implode(',', array_keys(PERMISSOES)), 1],
        ['Gestão', 'Boletos, prestação de contas, moradores e e-mails', 'moradores,comunicados,financeiro_ver,cobrancas,contas,emails', 0],
        ['Síndico / Administrador', 'Moradores, comunicados e acompanhamento financeiro', 'moradores,comunicados,financeiro_ver', 0],
    ];
    foreach ($padrao as [$nome, $desc, $perms, $sis]) q('INSERT IGNORE INTO niveis_acesso (condominio_id, nome, descricao, permissoes, sistema) VALUES (?,?,?,?,?)', [$cond, $nome, $desc, $perms, $sis]);
    return (int)valor("SELECT id FROM niveis_acesso WHERE condominio_id = ? AND nome = 'Gestor principal'", [$cond]);
}

/** Foto de perfil (ou a inicial do nome). */
function avatar(array $u, int $px = 40): string {
    $estilo = ' style="--s:' . $px . 'px"';
    if (!empty($u['foto_arquivo_id'])) return '<img class="av" src="' . e(url('arquivo', ['id' => $u['foto_arquivo_id']])) . '" alt=""' . $estilo . '>';
    return '<span class="av"' . $estilo . '>' . e(mb_strtoupper(mb_substr($u['nome'], 0, 1))) . '</span>';
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
                     WHERE un.condominio_id = ? AND c.status = 'pago' AND c.data_pagamento >= ? AND c.data_pagamento < ? ORDER BY c.data_pagamento", [cid(), $ini, $fim]);
    $rec_cond = array_sum(array_map(fn($c) => (float)($c['valor_pago'] ?? $c['valor']), $pagas));
    $lanc = linhas('SELECT * FROM lancamentos WHERE condominio_id = ? AND competencia = ? ORDER BY data, id', [cid(), $comp]);
    $outras_rec = 0.0; $desp = 0.0; $por_cat = [];
    foreach ($lanc as $l) {
        if ($l['tipo'] === 'receita') $outras_rec += (float)$l['valor'];
        else { $desp += (float)$l['valor']; $por_cat[$l['categoria']] = ($por_cat[$l['categoria']] ?? 0) + (float)$l['valor']; }
    }
    arsort($por_cat);
    // saldo anterior = saldo inicial + resultado de todos os meses anteriores
    $ant_cond = (float)valor("SELECT COALESCE(SUM(COALESCE(c.valor_pago, c.valor)),0) FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id WHERE un.condominio_id = ? AND c.status='pago' AND c.data_pagamento < ?", [cid(), $ini]);
    $ant_lanc = (float)valor("SELECT COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE -valor END),0) FROM lancamentos WHERE condominio_id = ? AND competencia < ?", [cid(), $ini]);
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

/** Re-codifica uma imagem enviada (JPG/PNG/WebP) com GD: remove metadados, limita o tamanho e devolve o id em `arquivos`. */
function salvar_imagem(string $campo, int $max_px, bool $png): ?int {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio da imagem.');
    if ($f['size'] > 8 * 1024 * 1024) throw new RuntimeException('Imagem muito grande (máximo 8 MB).');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP.');
    $img = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$img) throw new RuntimeException('Não foi possível ler a imagem.');
    $w = imagesx($img); $h = imagesy($img);
    if (max($w, $h) > $max_px) { $img = imagescale($img, $w >= $h ? $max_px : (int)round($w * $max_px / $h), $w >= $h ? (int)round($h * $max_px / $w) : $max_px); }
    $nome = bin2hex(random_bytes(16)) . ($png ? '.png' : '.jpg');
    $destino = RAIZ . '/storage/uploads/' . $nome;
    if ($png) { imagealphablending($img, false); imagesavealpha($img, true); imagepng($img, $destino, 6); }
    else {
        $fundo = imagecreatetruecolor(imagesx($img), imagesy($img)); imagefill($fundo, 0, 0, imagecolorallocate($fundo, 255, 255, 255));
        imagecopy($fundo, $img, 0, 0, 0, 0, imagesx($img), imagesy($img)); imagejpeg($fundo, $destino, 88);
    }
    q('INSERT INTO arquivos (nome_original, caminho, mime, tamanho) VALUES (?,?,?,?)', [$png ? 'logo.png' : 'foto.jpg', $nome, $png ? 'image/png' : 'image/jpeg', filesize($destino)]);
    return (int)db()->lastInsertId();
}
function apagar_arquivo(?int $id): void {
    if (!$id) return;
    $f = linha('SELECT caminho FROM arquivos WHERE id = ?', [$id]);
    if ($f) { @unlink(RAIZ . '/storage/uploads/' . basename($f['caminho'])); q('DELETE FROM arquivos WHERE id = ?', [$id]); }
}

require __DIR__ . '/historico.php';
require __DIR__ . '/email.php';
require __DIR__ . '/lembretes.php';

/* ---------- Layout ---------- */
function avisos_nao_lidos(): int {
    $u = usuario(); if (!$u) return 0;
    return (int)valor('SELECT COUNT(*) FROM comunicados c WHERE c.ativo = 1 AND c.condominio_id = ? AND NOT EXISTS (SELECT 1 FROM comunicado_leituras l WHERE l.comunicado_id = c.id AND l.usuario_id = ?)', [cid(), $u['id']]);
}

const ICONES = [
    'inicio' => 'M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    'boleto' => 'M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2zM9 8h6M9 12h6',
    'contas' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'avisos' => 'M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8M10 20a2 2 0 0 0 4 0',
    'perfil' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21a8 8 0 0 1 16 0',
    'moradores' => 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M2 21a7 7 0 0 1 14 0M17 3.5a4 4 0 0 1 0 7M22 21a7 7 0 0 0-4-6',
    'mais' => 'M4 7h16M4 12h16M4 17h16',
    'plus' => 'M12 5v14M5 12h14',
    'megafone' => 'M3 11v2a1 1 0 0 0 1 1h3l8 4V6L7 10H4a1 1 0 0 0-1 1zM19 9a4 4 0 0 1 0 6',
    'alerta' => 'M12 3l10 18H2zM12 10v5M12 18h.01',
    'predio' => 'M4 21V4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v17M15 9h4a1 1 0 0 1 1 1v11M3 21h18M8 8h3M8 12h3M8 16h3',
    'config' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
    'sair' => 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9',
    'cadeado' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
    'chave' => 'M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.8 7.8 5.5 5.5 0 0 1 7.8-7.8zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4',
    'arquivo' => 'M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8zM14 3v5h5M9 13h6M9 17h6',
    'clipe' => 'M21 11l-9 9a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8',
    'impressora' => 'M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z',
    'editar' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
    'olho' => 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6',
    'carteira' => 'M3 7a2 2 0 0 1 2-2h14v4M3 7v11a2 2 0 0 0 2 2h16V9H5a2 2 0 0 1-2-2zM17 14h.01',
    'relogio' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18M12 7v5l3 2',
    'check' => 'M20 6L9 17l-5-5',
    'desfazer' => 'M3 7v6h6M3 13a9 9 0 1 0 3-7',
    'email' => 'M3 5h18v14H3zM3 7l9 7 9-7',
    'lua' => 'M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z',
    'sol' => 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4',
    'camera' => 'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
    'imagem' => 'M3 3h18v18H3zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3M21 15l-5-5L5 21',
    'escudo' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
];

/** Ícone de linha (SVG inline). $tam em pixels. */
function icone(string $n, int $tam = 24): string {
    return '<svg class="i" viewBox="0 0 24 24" width="' . $tam . '" height="' . $tam . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . (ICONES[$n] ?? '') . '"/></svg>';
}

/** Troca os emojis usados nas telas por ícones de linha (visual uniforme em qualquer aparelho). */
function emojis_para_icones(string $html): string {
    static $mapa = ['👋' => '', '🎉' => '', '✅' => 'check', '➕' => 'plus', '📣' => 'megafone', '📢' => 'megafone', '🧾' => 'boleto', '📊' => 'contas', '📄' => 'arquivo', '🖨' => 'impressora',
        '✏' => 'editar', '👁' => 'olho', '💰' => 'carteira', '🔑' => 'chave', '📎' => 'clipe', '🏢' => 'predio', '👥' => 'moradores', '⚙' => 'config', '🙂' => 'perfil',
        '🚪' => 'sair', '🔒' => 'cadeado', '⚠' => 'alerta', '↩' => 'desfazer', '🕘' => 'relogio'];
    return preg_replace_callback('/(' . implode('|', array_map('preg_quote', array_keys($mapa))) . ')\x{FE0F}?\s?/u',
        fn($m) => $mapa[$m[1]] === '' ? '' : icone($mapa[$m[1]], 18) . ' ', $html);
}

function menu_principal(): array {
    if (pode('cobrancas')) return [['inicio', 'Início', 'inicio'], ['cobrancas', 'Cobranças', 'boleto'], ['contas_gestao', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['mais', 'Mais', 'mais']];
    if (eh_admin()) return [['inicio', 'Início', 'inicio'], ['usuarios', 'Moradores', 'moradores'], ['prestacoes', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['mais', 'Mais', 'mais']];
    return [['inicio', 'Início', 'inicio'], ['boletos', 'Boletos', 'boleto'], ['prestacoes', 'Contas', 'contas'], ['avisos', 'Avisos', 'avisos'], ['perfil', 'Perfil', 'perfil']];
}

function pagina_inicio(string $titulo, array $o = []): void {
    ob_start();
    $u = usuario(); $atual = $GLOBALS['pagina_atual'] ?? '';
    $nome = conf('condominio_nome', 'Condomínio');
    $tema = ['claro' => 'light', 'escuro' => 'dark'][$u['tema'] ?? 'auto'] ?? '';
    $cond = condominio_atual(); $varios = $u && count(condominios_acessiveis()) > 1;
    echo '<!doctype html><html lang="pt-BR"', $tema ? ' data-theme="' . $tema . '"' : '', '><head><meta charset="utf-8">',
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">',
        '<meta name="theme-color" content="#0f766e"><meta name="csrf" content="', e(csrf_token()), '"><link rel="manifest" href="assets/manifest.webmanifest">',
        '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">',
        '<link rel="icon" href="assets/icon.svg" type="image/svg+xml"><link rel="apple-touch-icon" href="assets/icon.svg">';
    if (!$u) echo "<script>try{var t=localStorage.getItem('tema');if(t==='claro')document.documentElement.dataset.theme='light';else if(t==='escuro')document.documentElement.dataset.theme='dark'}catch(e){}</script>";
    echo '<title>', e($titulo), ' · ', e($nome), '</title><link rel="stylesheet" href="assets/app.css?v=3"></head><body>';
    if ($u) {
        echo '<header class="topo"><div class="topo-in">';
        if (!empty($o['voltar'])) echo '<a class="voltar" href="', e($o['voltar']), '" aria-label="Voltar">‹</a>';
        elseif (!empty($cond['logo_arquivo_id'])) echo '<img class="logo-topo" src="', e(url('arquivo', ['id' => $cond['logo_arquivo_id']])), '" alt="">';
        echo '<div class="topo-tit"><strong>', e($titulo), '</strong>';
        if ($varios) echo '<a class="topo-cond" href="', e(url('meus_condominios')), '">', e($nome), ' ▾</a>';
        else echo '<small>', e($nome), '</small>';
        echo '</div><button type="button" class="topo-btn" data-tema-alternar aria-label="Alternar tema claro e escuro">', icone('lua', 20), icone('sol', 20), '</button>';
        echo '<a class="topo-user" href="', e(url('perfil')), '" aria-label="Meu perfil">', avatar($u, 40), '</a>';
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
        echo '<nav class="nav" aria-label="Menu principal"><div class="nav-in">';
        foreach (menu_principal() as [$p, $rot, $ic]) {
            $ativo = $p === $atual ? ' ativo' : '';
            echo '<a class="nav-item', $ativo, '" href="', e(url($p)), '">', icone($ic);
            if ($p === 'avisos' && $nao_lidos) echo '<span class="badge">', $nao_lidos, '</span>';
            echo '<span>', e($rot), '</span></a>';
        }
        echo '</div></nav>';
    }
    echo '<script src="assets/app.js?v=3"></script>';
    foreach ($GLOBALS['scripts_extra'] ?? [] as $js) echo '<script src="assets/', e($js), '?v=3"></script>';
    echo '</body></html>';
    echo emojis_para_icones((string)ob_get_clean());
}

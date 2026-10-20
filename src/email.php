<?php
declare(strict_types=1);

/** Configuração SMTP salva em Configurações > E-mails. Vazio = usa o mail() do PHP. */
function smtp_config(): ?array {
    if (conf('smtp_host') === '') return null;
    return [
        'host' => conf('smtp_host'), 'porta' => (int)conf('smtp_porta', '587'), 'seguranca' => conf('smtp_seguranca', 'tls'),
        'usuario' => conf('smtp_usuario'), 'senha' => conf('smtp_senha'),
        'de_email' => conf('smtp_de_email') ?: conf('smtp_usuario'), 'de_nome' => conf('smtp_de_nome') ?: conf('condominio_nome', 'Condomínio'),
    ];
}

function limpar_cabecalho(string $s): string { return trim(preg_replace('/[\r\n]+/', ' ', $s)); }

/** Envia uma mensagem por SMTP (SSL na 465, STARTTLS na 587 ou sem criptografia). Lança RuntimeException em caso de erro. */
function smtp_enviar(array $c, string $para, string $assunto, string $mensagem_mime_headers, string $corpo): void {
    $remoto = ($c['seguranca'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['porta'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remoto, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("Não foi possível conectar em {$c['host']}:{$c['porta']} ($errstr)");
    stream_set_timeout($fp, 20);
    $ler = function () use ($fp): string {
        $r = '';
        while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $r;
    };
    $esp = function (string $cmd, array $ok) use ($fp, $ler): string {
        if ($cmd !== '') fwrite($fp, $cmd . "\r\n");
        $r = $ler();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('Servidor SMTP: ' . trim($r));
        return $r;
    };
    try {
        $esp('', [220]);
        $nome = parse_url((string)cfg('url_base'), PHP_URL_HOST) ?: 'localhost';
        $esp("EHLO $nome", [250]);
        if ($c['seguranca'] === 'tls') {
            $esp('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Falha ao iniciar a conexão segura (STARTTLS).');
            $esp("EHLO $nome", [250]);
        }
        if ($c['usuario'] !== '') {
            $esp('AUTH LOGIN', [334]); $esp(base64_encode($c['usuario']), [334]); $esp(base64_encode($c['senha']), [235]);
        }
        $esp('MAIL FROM:<' . limpar_cabecalho($c['de_email']) . '>', [250]);
        $esp('RCPT TO:<' . limpar_cabecalho($para) . '>', [250, 251]);
        $esp('DATA', [354]);
        $dados = $mensagem_mime_headers . "\r\n\r\n" . $corpo;
        $dados = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $dados));
        fwrite($fp, str_replace("\n", "\r\n", $dados) . "\r\n.\r\n");
        $r = $ler();
        if ((int)substr($r, 0, 3) !== 250) throw new RuntimeException('Servidor SMTP: ' . trim($r));
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/** Modelo de e-mail (HTML) com a identidade do condomínio. $botao = [rótulo, link]. */
function email_html(string $titulo, string $texto, ?array $botao = null): string {
    $cond = e(conf('condominio_nome', 'Condomínio'));
    $btn = $botao ? '<p style="margin:24px 0 0"><a href="' . e($botao[1]) . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;padding:14px 24px;border-radius:12px">' . e($botao[0]) . '</a></p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#f1f5f7;font-family:Arial,Helvetica,sans-serif;color:#17202a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden" cellpadding="0" cellspacing="0">'
        . '<tr><td style="background:#0f766e;color:#fff;padding:20px 28px;font-size:18px;font-weight:700">' . $cond . '</td></tr>'
        . '<tr><td style="padding:28px"><h1 style="margin:0 0 14px;font-size:20px">' . e($titulo) . '</h1>'
        . '<div style="font-size:16px;line-height:1.55">' . nl2br(e($texto)) . '</div>' . $btn . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#f8fafb;color:#64707d;font-size:12px">Mensagem automática de ' . $cond . '. Para não receber e-mails, desmarque a opção em Meu perfil no aplicativo.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Envia um e-mail (SMTP configurado ou mail() do PHP) e registra o resultado.
 * $op: 'titulo' (cabeçalho do e-mail), 'botao' => [rótulo, link], 'tipo' (para o registro).
 */
function enviar_email(string $para, string $assunto, string $texto, array $op = []): bool {
    $smtp = smtp_config();
    $de_email = $smtp['de_email'] ?? cfg('email_remetente', 'nao-responder@localhost');
    $de_nome = $smtp['de_nome'] ?? conf('condominio_nome', 'Condomínio');
    $html = email_html($op['titulo'] ?? $assunto, $texto, $op['botao'] ?? null);
    $texto_puro = $texto . (!empty($op['botao']) ? "\n\n" . $op['botao'][0] . ': ' . $op['botao'][1] : '');
    $limite = 'b' . bin2hex(random_bytes(8));
    $enc = fn(string $s): string => '=?UTF-8?B?' . base64_encode(limpar_cabecalho($s)) . '?=';
    $headers = 'Date: ' . date('r') . "\r\nFrom: " . $enc($de_nome) . ' <' . limpar_cabecalho($de_email) . ">\r\nTo: <" . limpar_cabecalho($para) . ">\r\nSubject: " . $enc($assunto)
        . "\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$limite\"";
    $corpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto_puro))
        . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$limite--";
    $erro = null;
    try {
        if ($smtp) smtp_enviar($smtp, $para, $assunto, $headers, $corpo);
        elseif (!@mail($para, $enc($assunto), $corpo, substr($headers, strpos($headers, "\r\nFrom:") + 2), '-f' . limpar_cabecalho($de_email))) throw new RuntimeException('O servidor não aceitou o envio (mail). Configure o SMTP.');
    } catch (Throwable $e) { $erro = $e->getMessage(); }
    try {
        q('INSERT INTO email_log (destinatario, assunto, tipo, status, erro) VALUES (?,?,?,?,?)', [$para, mb_substr($assunto, 0, 200), $op['tipo'] ?? 'geral', $erro ? 'falha' : 'enviado', $erro ? mb_substr($erro, 0, 255) : null]);
    } catch (Throwable $e) { /* log indisponível */ }
    $GLOBALS['ultimo_erro_email'] = $erro;
    return $erro === null;
}

function link_app(string $p = 'inicio', array $params = []): string { return rtrim((string)cfg('url_base'), '/') . '/' . url($p, $params); }

/** E-mails dos condôminos ativos que aceitam receber mensagens (opcionalmente de uma unidade). */
function emails_condominos(?int $unidade_id = null): array {
    $sql = "SELECT nome, email FROM usuarios WHERE ativo = 1 AND receber_emails = 1 AND perfil = 'condomino' AND email <> ''";
    $par = [];
    if ($unidade_id) { $sql .= ' AND unidade_id = ?'; $par[] = $unidade_id; }
    return linhas($sql, $par);
}

/** Envia a mesma mensagem a todos os condôminos. Retorna [enviados, falhas]. */
function enviar_para_condominos(string $assunto, string $texto, array $op = []): array {
    @set_time_limit(300);
    $ok = 0; $falha = 0;
    foreach (emails_condominos() as $d) enviar_email($d['email'], $assunto, $texto, $op) ? $ok++ : $falha++;
    return [$ok, $falha];
}

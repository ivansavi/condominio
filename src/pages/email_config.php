<?php
$me = usuario();
if (conf('cron_token') === '') conf_set('cron_token', bin2hex(random_bytes(16)));
if (eh_post()) {
    $acao = post('acao');
    if ($acao === 'salvar') {
        foreach (['smtp_host', 'smtp_usuario', 'smtp_de_email', 'smtp_de_nome'] as $k) conf_set($k, post($k));
        conf_set('smtp_porta', (string)(int)post('smtp_porta', '587'));
        conf_set('smtp_seguranca', in_array(post('smtp_seguranca'), ['tls', 'ssl', 'nenhuma'], true) ? post('smtp_seguranca') : 'tls');
        if (post('smtp_senha') !== '') conf_set('smtp_senha', (string)$_POST['smtp_senha']); // vazio = mantém a atual
        conf_set('lembretes_ativos', post('lembretes_ativos') ? '1' : '0');
        conf_set('lembrete_dias_antes', (string)max(1, min(30, (int)post('lembrete_dias_antes', '3'))));
        flash('Configurações de e-mail salvas.');
    } elseif ($acao === 'teste') {
        $ok = enviar_email($me['email'], 'Teste de e-mail', "Se você recebeu esta mensagem, o envio de e-mails está funcionando.", ['tipo' => 'teste', 'botao' => ['Abrir o aplicativo', link_app('inicio')]]);
        flash($ok ? 'E-mail de teste enviado para ' . $me['email'] . '. Confira a caixa de entrada e o spam.' : 'Falhou: ' . ($GLOBALS['ultimo_erro_email'] ?? 'erro desconhecido'), $ok ? 'ok' : 'erro');
    } elseif ($acao === 'lembretes') {
        $r = processar_lembretes();
        $tot = array_sum(array_column($r, 'cobrancas'));
        flash($tot ? "Lembretes enviados para $tot cobrança(s)." : 'Nenhum lembrete pendente no momento.');
    }
    redirect('email_config');
}
$log = linhas('SELECT * FROM email_log ORDER BY id DESC LIMIT 15');
$cron_url = rtrim((string)cfg('url_base'), '/') . '/cron.php?token=' . conf('cron_token');
pagina_inicio('E-mails e lembretes', ['voltar' => url('mais')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="salvar">
  <h3>Servidor de e-mail (SMTP)</h3>
  <p class="muted pequeno">Na Hostinger: crie um e-mail em Emails → Contas de e-mail e use servidor <code>smtp.hostinger.com</code>, porta <code>465</code> com SSL (ou <code>587</code> com TLS). Deixe o servidor vazio para usar o envio padrão da hospedagem.</p>
  <label>Servidor SMTP<input name="smtp_host" value="<?= e(conf('smtp_host')) ?>" placeholder="smtp.hostinger.com"></label>
  <div class="duas">
    <label>Porta<input name="smtp_porta" inputmode="numeric" value="<?= e(conf('smtp_porta', '465')) ?>"></label>
    <label>Segurança<select name="smtp_seguranca">
      <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'TLS / STARTTLS (587)', 'nenhuma' => 'Nenhuma'] as $k => $r): ?><option value="<?= $k ?>" <?= conf('smtp_seguranca', 'ssl') === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label>
  </div>
  <label>Usuário (e-mail completo)<input name="smtp_usuario" value="<?= e(conf('smtp_usuario')) ?>" autocomplete="off" placeholder="contato@seudominio.com.br"></label>
  <label>Senha<input type="password" name="smtp_senha" autocomplete="new-password" placeholder="<?= conf('smtp_senha') !== '' ? '•••••••• (deixe vazio para manter)' : '' ?>"></label>
  <label>E-mail do remetente<input type="email" name="smtp_de_email" value="<?= e(conf('smtp_de_email')) ?>" placeholder="igual ao usuário"></label>
  <label>Nome do remetente<input name="smtp_de_nome" value="<?= e(conf('smtp_de_nome', conf('condominio_nome'))) ?>"></label>
  <h3>Lembretes automáticos de pagamento</h3>
  <label class="check"><input type="checkbox" name="lembretes_ativos" value="1" <?= conf('lembretes_ativos') === '1' ? 'checked' : '' ?>> Enviar lembretes aos condôminos</label>
  <label>Avisar quantos dias antes do vencimento<input type="number" min="1" max="30" name="lembrete_dias_antes" value="<?= e(conf('lembrete_dias_antes', '3')) ?>"></label>
  <p class="muted pequeno">Cada cobrança recebe: aviso antes do vencimento, aviso no dia seguinte ao vencimento e novo aviso após 15 dias de atraso. Nunca repete o mesmo aviso.</p>
  <button class="btn btn-grande">Salvar</button>
</form>

<form method="post"><?= csrf_campo() ?><input type="hidden" name="acao" value="teste"><button class="btn btn-sec">Enviar e-mail de teste para <?= e($me['email']) ?></button></form>
<form method="post" data-confirmar="Enviar agora os lembretes pendentes?"><?= csrf_campo() ?><input type="hidden" name="acao" value="lembretes"><button class="btn btn-sec">Enviar lembretes pendentes agora</button></form>

<div class="card">
  <h3>Envio automático todos os dias</h3>
  <p class="muted pequeno">No hPanel, abra Avançado → Cron Jobs, escolha "uma vez por dia" e use um destes comandos:</p>
  <code class="bloco-cod" id="cron-url">wget -q -O /dev/null "<?= e($cron_url) ?>"</code>
  <button class="btn btn-sec" type="button" data-copiar="#cron-url">Copiar comando</button>
</div>

<h3 class="sec">Últimos e-mails</h3>
<?php foreach ($log as $l): ?>
  <div class="card item"><div><strong><?= e($l['assunto']) ?></strong><span class="muted pequeno d-bloco"><?= e($l['destinatario']) ?> · <?= e(date('d/m H:i', strtotime($l['criado_em']))) ?><?= $l['erro'] ? ' · ' . e($l['erro']) : '' ?></span></div>
    <span class="selo <?= $l['status'] === 'enviado' ? 'selo-pago' : 'selo-vencido' ?>"><?= $l['status'] === 'enviado' ? 'Enviado' : 'Falhou' ?></span></div>
<?php endforeach;
if (!$log) echo '<div class="card vazio">Nenhum e-mail enviado ainda.</div>';
pagina_fim();

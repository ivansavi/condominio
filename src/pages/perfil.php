<?php
$u = usuario();
if (eh_post()) {
    $nome = post('nome'); $email = strtolower(post('email'));
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Informe seu nome e um e-mail válido.', 'erro');
    elseif (valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $u['id']])) flash('Este e-mail já está em uso por outro usuário.', 'erro');
    else {
        $tema = in_array(post('tema'), ['auto', 'claro', 'escuro'], true) ? post('tema') : 'auto';
        q('UPDATE usuarios SET nome=?, email=?, telefone=?, whatsapp=?, contato_emergencia=?, receber_emails=?, tema=? WHERE id=?',
            [$nome, $email, post('telefone') ?: null, post('whatsapp') ?: null, post('contato_emergencia') ?: null, post('receber_emails') ? 1 : 0, $tema, $u['id']]);
        atualizar_dados_vinculos((int)$u['id']);
        flash('Dados atualizados!');
        redirect('perfil');
    }
}
$GLOBALS['scripts_extra'][] = 'foto.js';
$meus = condominios_acessiveis();
pagina_inicio('Meu perfil');
?>
<div class="card foto-card">
  <div class="foto-grande"><?= avatar($u, 96) ?></div>
  <div><strong><?= e($u['nome']) ?></strong><br><span class="muted pequeno"><?= e(rotulo_acesso()) ?></span></div>
  <div class="botoes foto-botoes">
    <button type="button" class="btn btn-sec" data-foto-escolher><?= icone('imagem', 20) ?> Escolher foto</button>
    <button type="button" class="btn btn-sec" data-foto-camera><?= icone('camera', 20) ?> Tirar foto</button>
    <?php if ($u['foto_arquivo_id']): ?><button type="button" class="btn btn-perigo" data-foto-remover>Remover foto</button><?php endif; ?>
  </div>
  <input type="file" id="foto-arquivo" accept="image/*" hidden>
  <input type="file" id="foto-captura" accept="image/*" capture="user" hidden>
</div>

<div class="modal" id="foto-modal" hidden role="dialog" aria-modal="true" aria-label="Enquadrar foto">
  <div class="modal-in">
    <h3 id="foto-titulo">Enquadre sua foto</h3>
    <div class="foto-palco"><video id="foto-video" playsinline muted hidden></video><canvas id="foto-canvas" width="300" height="300"></canvas></div>
    <div id="foto-controles">
      <label class="zoom">Zoom<input type="range" id="foto-zoom" min="1" max="4" step="0.01" value="1"></label>
      <div class="duas"><button type="button" class="btn btn-sec" data-foto-girar>Girar 90°</button><button type="button" class="btn btn-sec" data-foto-centralizar>Centralizar</button></div>
    </div>
    <p class="muted pequeno" id="foto-dica">Arraste para posicionar e use o zoom para enquadrar o rosto no círculo.</p>
    <div class="botoes">
      <button type="button" class="btn btn-grande" id="foto-ok">Usar esta foto</button>
      <button type="button" class="btn btn-sec" id="foto-virar" hidden>Virar câmera</button>
      <button type="button" class="btn btn-sec" id="foto-cancelar">Cancelar</button>
    </div>
  </div>
</div>

<form method="post" class="form card"><?= csrf_campo() ?>
  <h3>Meus dados</h3>
  <label>Nome completo<input name="nome" value="<?= e($u['nome']) ?>" required></label>
  <label>E-mail (também é seu login)<input type="email" name="email" value="<?= e($u['email']) ?>" inputmode="email" required></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($u['telefone']) ?>" inputmode="tel" placeholder="(11) 3333-4444"></label>
  <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($u['whatsapp']) ?>" inputmode="tel" placeholder="(11) 99999-8888"></label>
  <label>Contato de emergência<input name="contato_emergencia" value="<?= e($u['contato_emergencia']) ?>" placeholder="Nome e telefone"></label>
  <label>Aparência
    <select name="tema"><?php foreach (['auto' => 'Automático (segue o aparelho)', 'claro' => 'Versão clara', 'escuro' => 'Versão escura'] as $k => $r): ?><option value="<?= $k ?>" <?= $u['tema'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label>
  <label class="check"><input type="checkbox" name="receber_emails" value="1" <?= $u['receber_emails'] ? 'checked' : '' ?>> Receber avisos, lembretes e boletos por e-mail</label>
  <button class="btn btn-grande">Salvar</button>
</form>

<?php if (count($meus) > 0): ?>
  <h3 class="sec">Meus condomínios <?php if (count($meus) > 1): ?><a href="<?= e(url('meus_condominios')) ?>">trocar</a><?php endif; ?></h3>
  <div class="card">
  <?php foreach ($meus as $c): ?>
    <div class="linha-r"><span><?= e($c['nome']) ?></span><strong class="pequeno muted"><?= (int)$c['id'] === cid() ? 'aberto agora' : '' ?></strong></div>
  <?php endforeach; ?></div>
<?php endif; ?>
<a class="card item" href="<?= e(url('senha')) ?>"><span class="item-ic"><span class="chip"><?= icone('cadeado', 20) ?></span>Alterar minha senha</span><span class="seta">›</span></a>
<a class="card item" href="<?= e(url('sair')) ?>"><span class="item-ic"><span class="chip"><?= icone('sair', 20) ?></span>Sair</span><span class="seta">›</span></a>
<?php pagina_fim();

<?php
$u = usuario();
pagina_inicio('Mais opções');
$itens = [];
if (eh_gestao()) {
    $itens[] = ['cobrancas', '🧾', 'Cobranças e boletos'];
    $itens[] = ['cobranca_gerar', '➕', 'Gerar boletos do mês'];
    $itens[] = ['contas_gestao', '📊', 'Prestação de contas'];
}
$itens[] = ['inadimplencia', '⚠️', 'Inadimplência'];
$itens[] = ['usuarios', '👥', 'Moradores e acessos'];
$itens[] = ['unidades', '🏢', 'Unidades'];
$itens[] = ['aviso_form', '📣', 'Novo comunicado'];
if (eh_gestao()) $itens[] = ['config', '⚙️', 'Dados do condomínio'];
$itens[] = ['perfil', '🙂', 'Meu perfil'];
?>
<div class="lista-menu">
<?php foreach ($itens as [$p, $ic, $rot]): ?>
  <a class="card item" href="<?= e(url($p)) ?>"><span><?= $ic ?> <?= e($rot) ?></span><span class="seta">›</span></a>
<?php endforeach; ?>
  <a class="card item" href="<?= e(url('sair')) ?>"><span>🚪 Sair</span><span class="seta">›</span></a>
</div>
<?php pagina_fim();

<?php
$u = usuario();
pagina_inicio('Mais opções');
$g = [];
if (pode('cobrancas')) $g['Financeiro'] = [['cobrancas', 'boleto', 'Cobranças e boletos'], ['cobranca_gerar', 'plus', 'Gerar boletos do mês']];
if (pode('contas')) $g['Financeiro'][] = ['contas_gestao', 'contas', 'Prestação de contas'];
elseif (eh_admin()) $g['Financeiro'][] = ['prestacoes', 'contas', 'Prestação de contas'];
if (pode('financeiro_ver')) $g['Financeiro'][] = ['inadimplencia', 'alerta', 'Inadimplência'];
if (pode('moradores')) $g['Cadastros'] = [['usuarios', 'moradores', 'Moradores e acessos'], ['unidades', 'predio', 'Unidades, proprietários e locatários']];
if (pode('comunicados')) $g['Comunicação'][] = ['aviso_form', 'megafone', 'Novo comunicado'];
if (pode('emails')) $g['Comunicação'][] = ['email_config', 'email', 'E-mails e lembretes'];
if (pode('condominio')) $g['Condomínio'][] = ['config', 'config', 'Dados do condomínio e logo'];
if (pode('niveis')) $g['Condomínio'][] = ['niveis', 'escudo', 'Níveis de acesso'];
if ($u['super_admin']) $g['Condomínio'][] = ['condominios', 'predio', 'Cadastrar novo condomínio'];
if (unidades_do_usuario()) $g['Minha conta'][] = ['boletos', 'carteira', 'Meus boletos'];
if (count(condominios_acessiveis()) > 1) $g['Minha conta'][] = ['meus_condominios', 'predio', 'Trocar de condomínio'];
$g['Minha conta'][] = ['perfil', 'perfil', 'Meu perfil'];
$g['Minha conta'][] = ['sair', 'sair', 'Sair'];
foreach ($g as $titulo => $itens): ?>
  <h3 class="sec"><?= e($titulo) ?></h3>
  <div class="lista-menu">
  <?php foreach ($itens as [$p, $ic, $rot]): ?>
    <a class="card item" href="<?= e(url($p)) ?>"><span class="item-ic"><span class="chip"><?= icone($ic, 20) ?></span><?= e($rot) ?></span><span class="seta">›</span></a>
  <?php endforeach; ?>
  </div>
<?php endforeach;
pagina_fim();

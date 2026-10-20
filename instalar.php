<?php
// Instalador: cria as tabelas e o primeiro usuário da Gestão. APAGUE este arquivo depois de usar.
declare(strict_types=1);
require __DIR__ . '/src/app.php';

$pronto = false;
$ja_tem = false;
try { $ja_tem = (int)valor('SELECT COUNT(*) FROM usuarios') > 0; } catch (Throwable $e) { $ja_tem = false; }

if ($ja_tem) { http_response_code(403); exit('Sistema já instalado. Por segurança, apague o arquivo instalar.php do servidor.'); }

if (eh_post()) {
    csrf_exigir();
    $nome = post('nome'); $email = strtolower(post('email')); $s = (string)($_POST['senha'] ?? '');
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($s) < 8 || post('condominio') === '') {
        flash('Preencha todos os campos (senha com no mínimo 8 caracteres).', 'erro');
    } else {
        foreach (preg_split('/;\s*\n/', file_get_contents(__DIR__ . '/database/schema.sql')) as $cmd) {
            $cmd = trim(preg_replace('/^--.*$/m', '', $cmd));
            if ($cmd !== '') db()->exec($cmd);
        }
        q("INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?,?,?,'gestao')", [$nome, $email, password_hash($s, PASSWORD_DEFAULT)]);
        conf_set('condominio_nome', post('condominio'));
        conf_set('multa_pct', '2'); conf_set('juros_mensal_pct', '1'); conf_set('dia_vencimento', '10');
        if (post('demo')) { require __DIR__ . '/src/demo.php'; carregar_demo(); }
        $pronto = true;
    }
}
pagina_inicio('Instalação');
?>
<div class="login">
<?php if ($pronto): ?>
  <h1>Instalado! 🎉</h1>
  <div class="alerta alerta-ok">Tabelas criadas e usuário da Gestão cadastrado.</div>
  <div class="alerta alerta-aviso"><strong>Importante:</strong> apague agora o arquivo <code>instalar.php</code> do servidor (File Manager da Hostinger).</div>
  <a class="btn btn-grande" href="index.php">Ir para o login</a>
<?php else: ?>
  <h1>Instalação</h1>
  <p class="muted">Crie o primeiro acesso da Gestão. Depois você cadastra síndico e moradores dentro do sistema.</p>
  <form method="post" class="form card"><?= csrf_campo() ?>
    <label>Nome do condomínio<input name="condominio" required></label>
    <label>Seu nome<input name="nome" required></label>
    <label>Seu e-mail (login)<input type="email" name="email" required></label>
    <label>Senha (mínimo 8 caracteres)<input type="password" name="senha" minlength="8" autocomplete="new-password" required></label>
    <label class="check"><input type="checkbox" name="demo" value="1"> Carregar dados de demonstração (use só em ambiente de teste)</label>
    <button class="btn btn-grande">Instalar</button>
  </form>
<?php endif; ?>
</div>
<?php pagina_fim();

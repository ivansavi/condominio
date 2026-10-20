<?php
// Copie este arquivo para config.php e preencha com os dados do MySQL da Hostinger
// (hPanel > Bancos de Dados > Gerenciamento).
return [
    'db_host' => 'localhost',
    'db_nome' => 'u000000000_condominio',
    'db_user' => 'u000000000_admin',
    'db_pass' => 'SUA_SENHA_AQUI',

    // URL pública do sistema, sem barra no final (usada nos links de e-mail)
    'url_base' => 'https://seudominio.com.br',

    // Remetente dos e-mails de recuperação de senha (crie um e-mail no hPanel)
    'email_remetente' => 'nao-responder@seudominio.com.br',

    'debug' => false,
];

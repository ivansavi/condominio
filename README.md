# App de Gestão de Condomínio

PHP 8 + MySQL, sem dependências, feito para hospedagem compartilhada da Hostinger. Interface pensada para celular (menu inferior, botões grandes, instalável na tela inicial).

## Perfis
| Perfil | O que faz |
|---|---|
| **Condômino** | Vê e copia boletos (linha digitável / Pix / PDF), histórico financeiro, prestação de contas, avisos; edita contatos e senha |
| **Síndico/Administrador** | Cadastra moradores e unidades (gera o login), publica comunicados, vê inadimplência e prestações |
| **Gestão** | Tudo do síndico + gera cobranças/boletos, dá baixa, lança despesas/receitas, publica a prestação de contas, configurações |

Um síndico pode receber também o acesso à Gestão (caixa "Síndico também acessa o portal da gestão"), compartilhando o mesmo portal.
Não existe auto-cadastro: todo login é criado por síndico ou gestão, com senha provisória (envio por WhatsApp em 1 toque; a troca é exigida no 1º acesso).

## Instalação na Hostinger
1. **hPanel → Bancos de Dados → MySQL**: crie o banco e o usuário; anote nome, usuário e senha.
2. Envie os arquivos deste projeto para `public_html` (Gerenciador de Arquivos, FTP ou Git do hPanel). PHP 8.1+ (hPanel → Avançado → Configuração do PHP).
3. Copie `config.sample.php` para `config.php` e preencha banco, `url_base` e `email_remetente` (no ambiente de teste o deploy automático gera esse arquivo a partir dos secrets do GitHub).
4. Acesse `https://seudominio.com.br/instalar.php`, informe o condomínio e crie o usuário da Gestão.
5. **Apague `instalar.php`** do servidor.
6. Em *Mais → Dados do condomínio* informe CNPJ, chave Pix, multa/juros e saldo inicial; depois cadastre as unidades (há cadastro em lote, ex.: `101-110, 201-210`) e os moradores.

Ative o SSL grátis no hPanel; o `.htaccess` força HTTPS e bloqueia `config.php`, `src/`, `storage/` e `database/`.

O banco é criado e atualizado **sozinho**: a cada requisição o app aplica os arquivos novos de `database/migrations/*.sql` (controle na tabela `schema_versao`). Para alterar o banco no futuro basta adicionar `00N_descricao.sql` e fazer o deploy.

## Fluxo mensal da Gestão
1. *Cobranças → Gerar boletos do mês* (uma cobrança por unidade, usando a taxa de cada uma).
2. Em cada cobrança, anexe o PDF do boleto emitido no banco e/ou a linha digitável / Pix copia e cola (ou configure uma chave Pix única).
3. Ao receber, *dar baixa* na cobrança. A receita entra sozinha na prestação de contas.
4. *Contas*: lance despesas, anexe balancete/notas, escreva uma mensagem e **Publique**. Só então os condôminos veem.

## Observações
- **Boleto bancário registrado**: o app controla cobranças, status, multa/juros e exibe os dados de pagamento, mas o boleto com código de barras é emitido no seu banco/gateway e anexado aqui. Integração direta com API bancária (emissão e baixa automática) pode ser a próxima etapa.
- Recuperação de senha por e-mail usa `mail()` do PHP (funciona na Hostinger com e-mail do domínio). Se não chegar, síndico/gestão gera nova senha provisória no cadastro do morador.
- Uploads (PDF/JPG/PNG até 5 MB) ficam em `storage/uploads`, fora de acesso direto, e só são servidos a quem tem permissão.
- Segurança: senhas com `password_hash`, CSRF em todos os formulários, consultas preparadas, bloqueio após 5 tentativas de login em 15 min.

## Ambiente de teste (staging)
Use um **subdomínio** da Hostinger (ex.: `teste.seudominio.com.br`) com um **banco MySQL separado** do de produção.
1. Crie o subdomínio e um banco novo no hPanel; faça o passo a passo de instalação acima nele.
2. Na tela do `instalar.php`, marque **"Carregar dados de demonstração"**. Logins (senha `demo1234`): `gestao@demo.com`, `sindico@demo.com`, `condomino@demo.com`.
3. **Atualização automática**: o workflow `.github/workflows/deploy-teste.yml` envia por FTP o código a cada push na branch. Cadastre no GitHub (Settings → Secrets → Actions): `FTP_SERVER`, `FTP_USER`, `FTP_PASSWORD`, `FTP_DIR` (pasta do subdomínio), `DB_NAME`, `DB_USER`, `DB_PASS`, `URL_BASE` (ex.: `https://teste.seudominio.com.br`) e opcionalmente `DB_HOST` (padrão `localhost`) e `EMAIL_REMETENTE`. O workflow gera o `config.php`; os uploads não são sobrescritos.

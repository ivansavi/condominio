-- Vários condomínios, níveis de acesso, foto e tema do usuário.
-- As colunas antigas de usuarios (perfil, acesso_gestao, unidade_id, tipo_morador, data_compra, data_venda) deixam de ser usadas.

CREATE TABLE IF NOT EXISTS condominios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  cnpj VARCHAR(20) NULL,
  endereco VARCHAR(255) NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  pix_chave VARCHAR(150) NULL,
  dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
  multa_pct DECIMAL(5,2) NOT NULL DEFAULT 2,
  juros_mensal_pct DECIMAL(5,2) NOT NULL DEFAULT 1,
  saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0,
  logo_arquivo_id INT UNSIGNED NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cond_logo FOREIGN KEY (logo_arquivo_id) REFERENCES arquivos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO condominios (id, nome, cnpj, endereco, pix_chave, dia_vencimento, multa_pct, juros_mensal_pct, saldo_inicial)
SELECT 1,
  COALESCE((SELECT valor FROM configuracoes WHERE chave = 'condominio_nome'), 'Meu Condomínio'),
  (SELECT valor FROM configuracoes WHERE chave = 'condominio_cnpj'),
  (SELECT valor FROM configuracoes WHERE chave = 'condominio_endereco'),
  (SELECT valor FROM configuracoes WHERE chave = 'pix_chave'),
  CAST(COALESCE((SELECT valor FROM configuracoes WHERE chave = 'dia_vencimento'), '10') AS UNSIGNED),
  CAST(COALESCE((SELECT valor FROM configuracoes WHERE chave = 'multa_pct'), '2') AS DECIMAL(5,2)),
  CAST(COALESCE((SELECT valor FROM configuracoes WHERE chave = 'juros_mensal_pct'), '1') AS DECIMAL(5,2)),
  CAST(COALESCE((SELECT valor FROM configuracoes WHERE chave = 'saldo_inicial'), '0') AS DECIMAL(12,2))
WHERE NOT EXISTS (SELECT 1 FROM condominios);

ALTER TABLE unidades ADD COLUMN condominio_id INT UNSIGNED NOT NULL DEFAULT 1 FIRST;
ALTER TABLE unidades DROP INDEX uq_unidade;
ALTER TABLE unidades ADD UNIQUE KEY uq_unidade (condominio_id, bloco, numero);
ALTER TABLE unidades ADD CONSTRAINT fk_unid_cond FOREIGN KEY (condominio_id) REFERENCES condominios(id);

ALTER TABLE lancamentos ADD COLUMN condominio_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE comunicados ADD COLUMN condominio_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE prestacao_anexos ADD COLUMN condominio_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE prestacoes ADD COLUMN condominio_id INT UNSIGNED NOT NULL DEFAULT 1 FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (condominio_id, competencia);

CREATE TABLE IF NOT EXISTS niveis_acesso (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  condominio_id INT UNSIGNED NOT NULL,
  nome VARCHAR(80) NOT NULL,
  descricao VARCHAR(200) NULL,
  permissoes VARCHAR(400) NOT NULL DEFAULT '',
  sistema TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_nivel (condominio_id, nome),
  CONSTRAINT fk_nivel_cond FOREIGN KEY (condominio_id) REFERENCES condominios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acessos (
  usuario_id INT UNSIGNED NOT NULL,
  condominio_id INT UNSIGNED NOT NULL,
  nivel_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, condominio_id),
  CONSTRAINT fk_acesso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_acesso_cond FOREIGN KEY (condominio_id) REFERENCES condominios(id) ON DELETE CASCADE,
  CONSTRAINT fk_acesso_nivel FOREIGN KEY (nivel_id) REFERENCES niveis_acesso(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE usuarios
  ADD COLUMN super_admin TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN foto_arquivo_id INT UNSIGNED NULL,
  ADD COLUMN ultimo_condominio_id INT UNSIGNED NULL,
  ADD COLUMN tema ENUM('auto','claro','escuro') NOT NULL DEFAULT 'auto';

INSERT INTO niveis_acesso (condominio_id, nome, descricao, permissoes, sistema) VALUES
  (1, 'Gestor principal', 'Acesso total ao condomínio', 'moradores,comunicados,financeiro_ver,cobrancas,contas,emails,condominio,niveis', 1),
  (1, 'Gestão', 'Boletos, prestação de contas, moradores e e-mails', 'moradores,comunicados,financeiro_ver,cobrancas,contas,emails', 0),
  (1, 'Síndico / Administrador', 'Moradores, comunicados e acompanhamento financeiro', 'moradores,comunicados,financeiro_ver', 0);

INSERT INTO acessos (usuario_id, condominio_id, nivel_id)
SELECT u.id, 1, (SELECT n.id FROM niveis_acesso n WHERE n.condominio_id = 1 AND n.nome =
  CASE WHEN u.perfil = 'gestao' THEN 'Gestor principal' WHEN u.acesso_gestao = 1 THEN 'Gestão' ELSE 'Síndico / Administrador' END)
FROM usuarios u WHERE u.perfil IN ('gestao', 'sindico');

UPDATE usuarios SET super_admin = 1 WHERE perfil = 'gestao';

-- Morador/inquilino/proprietário passa a existir só em unidade_vinculos (um cadastro pode ter várias unidades, em vários condomínios)
INSERT INTO unidade_vinculos (unidade_id, tipo, nome, cpf, telefone, email, data_inicio, data_fim, usuario_id)
SELECT u.unidade_id, IF(u.tipo_morador = 'inquilino', 'locatario', 'proprietario'), u.nome, u.cpf, COALESCE(u.telefone, u.whatsapp), u.email, u.data_compra, u.data_venda, u.id
FROM usuarios u
WHERE u.unidade_id IS NOT NULL AND u.perfil = 'condomino'
  AND NOT EXISTS (SELECT 1 FROM unidade_vinculos v WHERE v.usuario_id = u.id AND v.unidade_id = u.unidade_id);

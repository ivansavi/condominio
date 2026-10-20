-- Esquema MySQL/MariaDB (Hostinger). Cada comando termina com ";" no fim da linha.
CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(60) NOT NULL PRIMARY KEY,
  valor TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS unidades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bloco VARCHAR(20) NOT NULL DEFAULT '',
  numero VARCHAR(20) NOT NULL,
  valor_condominio DECIMAL(10,2) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_unidade (bloco, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  cpf VARCHAR(14) NULL,
  telefone VARCHAR(25) NULL,
  whatsapp VARCHAR(25) NULL,
  contato_emergencia VARCHAR(160) NULL,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('condomino','sindico','gestao') NOT NULL DEFAULT 'condomino',
  acesso_gestao TINYINT(1) NOT NULL DEFAULT 0,
  unidade_id INT UNSIGNED NULL,
  tipo_morador ENUM('proprietario','inquilino') NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  trocar_senha TINYINT(1) NOT NULL DEFAULT 0,
  ultimo_login DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reset_senha (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_token (token_hash),
  CONSTRAINT fk_reset_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tentativas_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave CHAR(64) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chave (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS arquivos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome_original VARCHAR(200) NOT NULL,
  caminho VARCHAR(100) NOT NULL,
  mime VARCHAR(80) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cobrancas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidade_id INT UNSIGNED NOT NULL,
  competencia DATE NOT NULL,
  descricao VARCHAR(160) NOT NULL DEFAULT 'Taxa de condomínio',
  valor DECIMAL(10,2) NOT NULL,
  vencimento DATE NOT NULL,
  status ENUM('aberto','pago','cancelado') NOT NULL DEFAULT 'aberto',
  data_pagamento DATE NULL,
  valor_pago DECIMAL(10,2) NULL,
  forma_pagamento VARCHAR(40) NULL,
  linha_digitavel VARCHAR(80) NULL,
  pix_copia_cola TEXT NULL,
  arquivo_id INT UNSIGNED NULL,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cob_unidade (unidade_id, vencimento),
  KEY idx_cob_status (status, vencimento),
  CONSTRAINT fk_cob_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_cob_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lancamentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  competencia DATE NOT NULL,
  tipo ENUM('receita','despesa') NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  descricao VARCHAR(200) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  data DATE NOT NULL,
  KEY idx_lanc_comp (competencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestacoes (
  competencia DATE NOT NULL PRIMARY KEY,
  publicada TINYINT(1) NOT NULL DEFAULT 0,
  publicada_em DATETIME NULL,
  observacao TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestacao_anexos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  competencia DATE NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  arquivo_id INT UNSIGNED NOT NULL,
  KEY idx_anexo_comp (competencia),
  CONSTRAINT fk_anexo_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comunicados (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(160) NOT NULL,
  corpo TEXT NOT NULL,
  importante TINYINT(1) NOT NULL DEFAULT 0,
  autor_id INT UNSIGNED NULL,
  publicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_com_data (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comunicado_leituras (
  comunicado_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comunicado_id, usuario_id),
  CONSTRAINT fk_leit_com FOREIGN KEY (comunicado_id) REFERENCES comunicados(id) ON DELETE CASCADE,
  CONSTRAINT fk_leit_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datas de compra/venda e preferência de e-mail no cadastro de moradores
ALTER TABLE usuarios
  ADD COLUMN data_compra DATE NULL AFTER tipo_morador,
  ADD COLUMN data_venda DATE NULL AFTER data_compra,
  ADD COLUMN receber_emails TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo;

-- Histórico de alterações (o que mudou, como era e como ficou)
CREATE TABLE IF NOT EXISTS historico_alteracoes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entidade VARCHAR(20) NOT NULL,
  entidade_id INT UNSIGNED NOT NULL,
  lote CHAR(16) NOT NULL,
  campo VARCHAR(80) NOT NULL,
  valor_antigo TEXT NULL,
  valor_novo TEXT NULL,
  autor_id INT UNSIGNED NULL,
  autor_nome VARCHAR(120) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist (entidade, entidade_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proprietários (histórico) e locatários de cada unidade
CREATE TABLE IF NOT EXISTS unidade_vinculos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidade_id INT UNSIGNED NOT NULL,
  tipo ENUM('proprietario','locatario') NOT NULL,
  nome VARCHAR(120) NOT NULL,
  cpf VARCHAR(14) NULL,
  telefone VARCHAR(25) NULL,
  email VARCHAR(150) NULL,
  data_inicio DATE NULL,
  data_fim DATE NULL,
  usuario_id INT UNSIGNED NULL,
  observacao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vinc (unidade_id, tipo),
  CONSTRAINT fk_vinc_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id) ON DELETE CASCADE,
  CONSTRAINT fk_vinc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registro dos e-mails enviados
CREATE TABLE IF NOT EXISTS email_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  destinatario VARCHAR(150) NOT NULL,
  assunto VARCHAR(200) NOT NULL,
  tipo VARCHAR(30) NOT NULL DEFAULT 'geral',
  status ENUM('enviado','falha') NOT NULL,
  erro VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Controle para não repetir lembretes de pagamento
CREATE TABLE IF NOT EXISTS lembretes_enviados (
  cobranca_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cobranca_id, tipo),
  CONSTRAINT fk_lemb_cob FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

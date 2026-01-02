CREATE DATABASE IF NOT EXISTS listafacil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE listafacil;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'usuario') DEFAULT 'usuario',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campanhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    tipo ENUM('dinheiro', 'itens', 'misto') NOT NULL,
    meta_valor DECIMAL(10,2) NULL,
    meta_itens INT NULL,
    status ENUM('ativa', 'concluida', 'cancelada') DEFAULT 'ativa',
    data_inicio DATE NOT NULL,
    data_fim DATE,
    token VARCHAR(50) UNIQUE NOT NULL,
    criado_por INT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) UNIQUE,
    email VARCHAR(100),
    pin_hash VARCHAR(255) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campanha_id INT NOT NULL,
    doador_id INT NOT NULL,
    tipo ENUM('dinheiro', 'item') NOT NULL,
    forma_pagamento ENUM('pix','dinheiro') NOT NULL DEFAULT 'pix',
    valor DECIMAL(10,2) NULL,
    item_descricao VARCHAR(200) NULL,
    quantidade INT DEFAULT 1,
    status ENUM('prometido', 'aguardando_confirmacao', 'pago', 'entregue', 'cancelado') DEFAULT 'prometido',
    comprovante VARCHAR(255) NULL,
    comprovante_nome_original VARCHAR(255) NULL,
    comprovante_mime VARCHAR(100) NULL,
    comprovante_enviado_em TIMESTAMP NULL,
    token_publico VARCHAR(64) UNIQUE NULL,
    observacao TEXT,
    data_promessa TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_confirmacao TIMESTAMP NULL,
    FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (doador_id) REFERENCES doadores(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doador_sessoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doador_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_em TIMESTAMP NULL,
    FOREIGN KEY (doador_id) REFERENCES doadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grupo_membros (
    grupo_id INT NOT NULL,
    doador_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (grupo_id, doador_id),
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (doador_id) REFERENCES doadores(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campanha_grupos (
    campanha_id INT NOT NULL,
    grupo_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (campanha_id, grupo_id),
    FOREIGN KEY (campanha_id) REFERENCES campanhas(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO usuarios (nome, email, senha, tipo)
VALUES ('Administrador', 'admin@igreja.com', '$2y$10$NXqHOzOBrwnQM7rmk.qHveuRsPBeDYjZt5gKyhzsp9eAUMDmwn5yK', 'admin')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    tipo = VALUES(tipo);

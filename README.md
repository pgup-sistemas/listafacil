# Listafacil - Sistema de Gestão de Doações

Sistema web em PHP + MySQL para gerenciar campanhas de doações (dinheiro e itens), com área pública para doadores e área administrativa para controle.

## Requisitos

- XAMPP (Apache + MySQL) com PHP 7.4+ (recomendado PHP 8.x)
- Extensões PHP: PDO, PDO_MySQL

## Instalação (XAMPP)

1. Copie esta pasta para:

`c:\xampp\htdocs\listafacil`

2. Inicie no XAMPP:

- Apache
- MySQL

3. Crie o banco/tabelas:

- Abra o phpMyAdmin: `http://localhost/phpmyadmin`
- Importe o arquivo: `c:\xampp\htdocs\listafacil\database.sql`

4. Configuração do banco:

Edite `config.php` se necessário:

- `DB_USER` (padrão `root`)
- `DB_PASS` (padrão vazio no XAMPP)

5. Acesse:

- Público: `http://localhost/listafacil/index.php`
- Admin: `http://localhost/listafacil/login.php`

## Acesso inicial (admin)

- Email: `admin@igreja.com`
- Senha: `admin123`

## Estrutura

- `index.php`: lista campanhas ativas
- `campanha.php`: página pública da campanha + doação
- `api.php`: endpoints AJAX (criar doação, atualizar status, etc.)
- `login.php` / `logout.php`: autenticação
- `dashboard.php`: painel
- `nova_campanha.php`: criação
- `gerenciar_campanhas.php`: lista/gerência
- `detalhes_campanha.php`: doações da campanha
- `relatorios.php`: filtros + exportação CSV
- `uploads/`: reservado para anexos futuros

## Migração (se você já tinha o banco criado)

Se você importou um `database.sql` antigo antes dessas evoluções, execute no phpMyAdmin (aba SQL):

```sql
USE listafacil;

ALTER TABLE doadores
  ADD COLUMN pin_hash VARCHAR(255) NULL,
  ADD UNIQUE KEY uniq_doadores_telefone (telefone);

ALTER TABLE doacoes
  MODIFY COLUMN status ENUM('prometido','aguardando_confirmacao','pago','entregue','cancelado') DEFAULT 'prometido',
  ADD COLUMN forma_pagamento ENUM('pix','dinheiro') NOT NULL DEFAULT 'pix' AFTER tipo,
  ADD COLUMN comprovante_nome_original VARCHAR(255) NULL AFTER comprovante,
  ADD COLUMN comprovante_mime VARCHAR(100) NULL AFTER comprovante_nome_original,
  ADD COLUMN comprovante_enviado_em TIMESTAMP NULL AFTER comprovante_mime,
  ADD COLUMN token_publico VARCHAR(64) UNIQUE NULL AFTER comprovante_enviado_em;

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
```

Depois disso, você pode:

- Cadastrar doadores em `admin_doadores.php`
- Criar grupos e adicionar membros em `admin_grupos.php`
- Criar campanhas e selecionar grupos em `nova_campanha.php`

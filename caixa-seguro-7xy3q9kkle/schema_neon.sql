-- Schema PostgreSQL para Neon
-- Sistema de Restaurante - Caixa Seguro
-- Gerado em: 2026-04-03

-- Timezone padrão
SET timezone TO 'America/Sao_Paulo';

-- ============================================
-- TABELAS PRINCIPAIS
-- ============================================

CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS produtos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE RESTRICT,
    preco DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estoque_atual INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0,
    imagem VARCHAR(255),
    ativo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) NOT NULL DEFAULT 'usuario',
    ativo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS garcons (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(20),
    ativo SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS comandas (
    id SERIAL PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    taxa_gorjeta DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    garcom_id INT REFERENCES garcons(id) ON DELETE SET NULL,
    usuario_fechamento_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
    data_venda TIMESTAMP,
    data_finalizacao TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS itens_comanda (
    id SERIAL PRIMARY KEY,
    comanda_id INT NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    produto_id INT NOT NULL REFERENCES produtos(id) ON DELETE RESTRICT,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS itens_livres (
    id SERIAL PRIMARY KEY,
    comanda_id INT NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    descricao VARCHAR(200) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id SERIAL PRIMARY KEY,
    produto_id INT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    tipo VARCHAR(20) NOT NULL, -- 'entrada' ou 'saida'
    quantidade INT NOT NULL,
    observacao TEXT,
    data_movimentacao TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS comprovantes_venda (
    id SERIAL PRIMARY KEY,
    comanda_id INT NOT NULL REFERENCES comandas(id) ON DELETE CASCADE,
    conteudo TEXT NOT NULL,
    tipo VARCHAR(30) NOT NULL DEFAULT 'cliente',
    impresso SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS configuracoes (
    id SERIAL PRIMARY KEY,
    taxa_gorjeta DECIMAL(5,2) DEFAULT 0.00,
    tipo_taxa VARCHAR(20) DEFAULT 'nenhuma',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS perdas_estoque (
    id SERIAL PRIMARY KEY,
    produto_id INT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    quantidade_perdida INT NOT NULL,
    valor_perda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estoque_esperado INT NOT NULL DEFAULT 0,
    estoque_real INT NOT NULL DEFAULT 0,
    motivo VARCHAR(255) DEFAULT 'Diferenca de inventario',
    data_identificacao TIMESTAMP DEFAULT NOW(),
    visualizada SMALLINT DEFAULT 0,
    data_visualizacao TIMESTAMP,
    observacoes TEXT
);

-- ============================================
-- INDICES
-- ============================================

CREATE INDEX IF NOT EXISTS idx_produtos_categoria ON produtos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_produtos_ativo ON produtos(ativo);
CREATE INDEX IF NOT EXISTS idx_itens_comanda_comanda ON itens_comanda(comanda_id);
CREATE INDEX IF NOT EXISTS idx_itens_comanda_produto ON itens_comanda(produto_id);
CREATE INDEX IF NOT EXISTS idx_comandas_status ON comandas(status);
CREATE INDEX IF NOT EXISTS idx_comandas_data ON comandas(data_venda);
CREATE INDEX IF NOT EXISTS idx_movimentacoes_produto ON movimentacoes_estoque(produto_id);
CREATE INDEX IF NOT EXISTS idx_comprovantes_comanda ON comprovantes_venda(comanda_id);

-- ============================================
-- DADOS INICIAIS
-- ============================================

-- Configuracao padrao de gorjeta
INSERT INTO configuracoes (taxa_gorjeta, tipo_taxa) VALUES (0, 'nenhuma')
ON CONFLICT DO NOTHING;

-- Taxa de comissao padrao
INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('commission_rate', '0.03', 'Taxa de comissao dos garcons')
ON CONFLICT (chave) DO NOTHING;

-- Configuracoes white-label (defaults)
INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('nome_estabelecimento', 'Meu Estabelecimento', 'Nome exibido no cardapio e comprovantes') ON CONFLICT (chave) DO NOTHING;
INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('nome_sistema', 'GestaoInteli', 'Nome do sistema exibido no header e login') ON CONFLICT (chave) DO NOTHING;
INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('garcons_ativo', '1', 'Modulo de garcons ativo (1) ou desativado (0)') ON CONFLICT (chave) DO NOTHING;

-- Constraints de seguranca para perdas
ALTER TABLE perdas_estoque ADD CONSTRAINT chk_perdas_positivas CHECK (quantidade_perdida >= 0 AND valor_perda >= 0);

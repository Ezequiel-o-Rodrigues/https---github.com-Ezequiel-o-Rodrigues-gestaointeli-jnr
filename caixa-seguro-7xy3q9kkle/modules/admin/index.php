<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';

// Handlers: salvar/atualizar usuário, alternar ativo, deletar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'save_user') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $nome = $_POST['nome'] ?? '';
            $email = $_POST['email'] ?? '';
            $perfil = $_POST['perfil'] ?? 'usuario';
            $ativo = isset($_POST['ativo']) ? true : false;
            $senha = $_POST['senha'] ?? '';

            if ($id) {
                // update
                if (!empty($senha)) {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, $hash, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $perfil, $ativo, $id]);
                }
                $_SESSION['sucesso'] = 'Usuário atualizado com sucesso.';
            } else {
                // insert
                if (empty($senha)) throw new Exception('Senha é obrigatória para novo usuário.');
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo, created_at) VALUES (?, ?, ?, ?, ?::boolean, NOW())");
                $stmt->execute([$nome, $email, $hash, $perfil, $ativo]);
                $_SESSION['sucesso'] = 'Usuário criado com sucesso.';
            }
            header('Location: index.php'); exit;
        }

        if ($action === 'toggle_user') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('UPDATE usuarios SET ativo = NOT ativo WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Status do usuário alterado.';
            header('Location: index.php'); exit;
        }

        if ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Usuário removido.';
            header('Location: index.php'); exit;
        }

        if ($action === 'toggle_garcons_module') {
            $atual = getConfig('garcons_ativo', '1');
            $novo = $atual === '1' ? '0' : '1';
            $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('garcons_ativo', ?, 'Modulo de garcons ativo') ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()");
            $stmt->execute([$novo]);
            $_SESSION['sucesso'] = $novo === '1' ? 'Modulo de garcons ativado.' : 'Modulo de garcons desativado. Comandas serao atribuidas ao caixa.';
            header('Location: index.php'); exit;
        }

        if ($action === 'save_estabelecimento') {
            $campos = ['nome_estabelecimento', 'nome_sistema', 'telefone', 'email_contato', 'endereco', 'link_ifood', 'link_whatsapp', 'horario_delivery', 'instagram', 'facebook'];
            foreach ($campos as $campo) {
                $valor = trim($_POST[$campo] ?? '');
                $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES (?, ?, ?) ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()");
                $stmt->execute([$campo, $valor, $campo]);
            }
            $_SESSION['sucesso'] = 'Dados do estabelecimento atualizados.';
            header('Location: index.php'); exit;
        }

        if ($action === 'save_commission') {
    $rate_percent = floatval($_POST['commission_rate'] ?? 3);
    if ($rate_percent < 0 || $rate_percent > 100) {
        throw new Exception('Taxa de comissão deve estar entre 0% e 100%.');
    }
    
    $rate = $rate_percent / 100; // Converter de porcentagem para decimal
    
    // Usar a NOVA tabela configuracoes_sistema
    $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('commission_rate', ?, 'Taxa de comissão dos garçons') ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()");
    $stmt->execute([$rate]);
    $_SESSION['sucesso'] = 'Taxa de comissão atualizada para ' . number_format($rate_percent, 1) . '%.';
    header('Location: index.php'); exit;
}

        // Garcons management moved to /modules/garcons/
    } catch (Exception $e) {
        $_SESSION['erro'] = 'Erro: ' . $e->getMessage();
        header('Location: index.php'); exit;
    }
}

// Buscar usuários
$stmt = $db->prepare('SELECT id, nome, email, perfil, ativo, created_at FROM usuarios ORDER BY id DESC');
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos com categoria
$stmt = $db->prepare('SELECT p.*, c.nome as categoria_nome FROM produtos p LEFT JOIN categorias c ON p.categoria_id = c.id ORDER BY c.nome, p.nome');
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar configuração de comissão - NOVO CÓDIGO
try {
    $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'commission_rate'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config && isset($config['valor'])) {
        $current_commission = floatval($config['valor']);
    } else {
        // Se não existir, criar com valor padrão
        $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('commission_rate', '0.03', 'Taxa de comissão dos garçons')");
        $stmt->execute();
        $current_commission = 0.03;
    }
} catch (Exception $e) {
    // Fallback para valor padrão em caso de erro
    $current_commission = 0.03;
    error_log("Erro ao buscar taxa de comissão: " . $e->getMessage());
}

require_once '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Administracao</h1>
    </div>

    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['sucesso'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['erro'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <!-- Card: Dados do Estabelecimento -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-building"></i> Dados do Estabelecimento</h5>
            <p class="text-muted small">Essas informacoes aparecem no cardapio publico e nos comprovantes.</p>
            <form method="POST">
                <input type="hidden" name="action" value="save_estabelecimento">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome do Estabelecimento</label>
                        <input type="text" class="form-control" name="nome_estabelecimento" value="<?= htmlspecialchars(getConfig('nome_estabelecimento', '')) ?>" placeholder="Ex: Restaurante do Ze">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nome do Sistema</label>
                        <input type="text" class="form-control" name="nome_sistema" value="<?= htmlspecialchars(getConfig('nome_sistema', 'GestaoInteli')) ?>" placeholder="GestaoInteli">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telefone</label>
                        <input type="text" class="form-control" name="telefone" value="<?= htmlspecialchars(getConfig('telefone', '')) ?>" placeholder="(11) 99999-9999">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email de Contato</label>
                        <input type="email" class="form-control" name="email_contato" value="<?= htmlspecialchars(getConfig('email_contato', '')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Endereco</label>
                        <input type="text" class="form-control" name="endereco" value="<?= htmlspecialchars(getConfig('endereco', '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link iFood</label>
                        <input type="url" class="form-control" name="link_ifood" value="<?= htmlspecialchars(getConfig('link_ifood', '')) ?>" placeholder="https://www.ifood.com.br/...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Link WhatsApp</label>
                        <input type="url" class="form-control" name="link_whatsapp" value="<?= htmlspecialchars(getConfig('link_whatsapp', '')) ?>" placeholder="https://wa.me/5511...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Horario Delivery</label>
                        <input type="text" class="form-control" name="horario_delivery" value="<?= htmlspecialchars(getConfig('horario_delivery', '')) ?>" placeholder="18h as 23h">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Instagram</label>
                        <input type="url" class="form-control" name="instagram" value="<?= htmlspecialchars(getConfig('instagram', '')) ?>" placeholder="https://instagram.com/...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Facebook</label>
                        <input type="url" class="form-control" name="facebook" value="<?= htmlspecialchars(getConfig('facebook', '')) ?>" placeholder="https://facebook.com/...">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Salvar Dados</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Card: Configurações -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-sliders"></i> Configuracoes do Sistema</h5>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="save_commission">
                <div class="col-md-4">
                    <label for="commission_rate" class="form-label">Taxa de Comissão dos Garçons (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                               value="<?= $current_commission * 100 ?>" min="0" max="100" step="0.1" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">Taxa atual: <?= number_format($current_commission * 100, 1) ?>%</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Salvar Configuração</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Card: Gerenciar Categorias -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Gerenciar Categorias</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoriaModal" onclick="novaCategoria()">Nova Categoria</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-categorias">
                        <?php
                        $stmt = $db->prepare('SELECT id, nome FROM categorias ORDER BY nome');
                        $stmt->execute();
                        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach($categorias as $cat):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($cat['nome']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal" onclick="editarCategoria(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome']) ?>')">Editar</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletarCategoria(<?= $cat['id'] ?>)">Remover</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Card: Gerenciar Produtos (Cardápio + Caixa) -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Gerenciar Produtos</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="filtro-produto" class="form-control form-control-sm" placeholder="Buscar produto..." style="width: 200px;" oninput="filtrarProdutos()">
                    <select id="filtro-categoria" class="form-select form-select-sm" style="width: 160px;" onchange="filtrarProdutos()">
                        <option value="">Todas categorias</option>
                        <?php foreach($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary btn-sm text-nowrap" onclick="novoProduto()">Novo Produto</button>
                </div>
            </div>
            <p class="text-muted small mb-2">Alterações aqui refletem automaticamente no cardápio público e no caixa.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-produtos">
                        <?php foreach($produtos as $prod): ?>
                        <tr class="produto-row <?= $prod['ativo'] ? '' : 'table-secondary' ?>"
                            data-nome="<?= htmlspecialchars(strtolower($prod['nome'])) ?>"
                            data-categoria="<?= $prod['categoria_id'] ?>">
                            <td>
                                <?php if ($prod['imagem']): ?>
                                <img src="<?= PathConfig::url('public/images/products/' . $prod['imagem']) ?>"
                                     alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                                <?php else: ?>
                                <span class="text-muted" style="font-size:0.8rem;">Sem img</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($prod['nome']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($prod['categoria_nome'] ?? 'Sem categoria') ?></span></td>
                            <td>R$ <?= number_format($prod['preco'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($prod['estoque_atual'] <= $prod['estoque_minimo'] && $prod['estoque_atual'] > 0): ?>
                                <span class="text-warning fw-bold"><?= $prod['estoque_atual'] ?></span>
                                <?php elseif ($prod['estoque_atual'] == 0): ?>
                                <span class="text-danger fw-bold">0</span>
                                <?php else: ?>
                                <?= $prod['estoque_atual'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $prod['ativo'] ? 'success' : 'secondary' ?>">
                                    <?= $prod['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick='editarProduto(<?= json_encode($prod) ?>)'>Editar</button>
                                    <button class="btn btn-outline-warning" onclick="toggleProduto(<?= $prod['id'] ?>, <?= $prod['ativo'] ?>)">
                                        <?= $prod['ativo'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="deletarProduto(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['nome']) ?>')">Excluir</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mt-2">Total: <?= count($produtos) ?> produtos</p>
        </div>
    </div>
</div>

<!-- Modal Produto -->
<div class="modal fade" id="produtoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="produtoModalTitle">Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="produtoForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="prod_id">

                    <div class="mb-3">
                        <label for="prod_nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="prod_nome" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="prod_categoria" class="form-label">Categoria *</label>
                            <select class="form-select" id="prod_categoria" required>
                                <option value="">Selecione...</option>
                                <?php foreach($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="prod_preco" class="form-label">Preço (R$) *</label>
                            <input type="number" class="form-control" id="prod_preco" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="prod_estoque_minimo" class="form-label">Estoque mínimo</label>
                            <input type="number" class="form-control" id="prod_estoque_minimo" min="0" value="0">
                        </div>
                        <div class="col-6" id="estoque_inicial_group">
                            <label for="prod_estoque_inicial" class="form-label">Estoque inicial</label>
                            <input type="number" class="form-control" id="prod_estoque_inicial" min="0" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="prod_imagem" class="form-label">Imagem</label>
                        <input type="file" class="form-control" id="prod_imagem" accept="image/*">
                        <div id="prod_imagem_preview" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Produto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Categoria -->
<div class="modal fade" id="categoriaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoriaModalTitle">Nova Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="cat_nome" class="form-label">Nome *</label>
                    <input type="text" class="form-control" id="cat_nome" required>
                    <input type="hidden" id="cat_id">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarCategoria()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS (bundle com Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= PathConfig::modules('admin/') ?>admin.js"></script>


<?php require_once '../../includes/footer.php'; ?>
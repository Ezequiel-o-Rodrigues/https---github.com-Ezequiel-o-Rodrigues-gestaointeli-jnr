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
            $ativo = isset($_POST['ativo']) ? 1 : 0;
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
                $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, perfil, ativo, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
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

        if ($action === 'save_commission') {
    $rate_percent = floatval($_POST['commission_rate'] ?? 3);
    if ($rate_percent < 0 || $rate_percent > 100) {
        throw new Exception('Taxa de comissão deve estar entre 0% e 100%.');
    }
    
    $rate = $rate_percent / 100; // Converter de porcentagem para decimal
    
    // Usar a NOVA tabela configuracoes_sistema
    $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('commission_rate', ?, 'Taxa de comissão dos garçons') ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()");
    $stmt->execute([$rate, $rate]);
    $_SESSION['sucesso'] = 'Taxa de comissão atualizada para ' . number_format($rate_percent, 1) . '%.';
    header('Location: index.php'); exit;
}

        if ($action === 'save_garcom') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $nome = trim($_POST['nome'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome)) throw new Exception('Nome é obrigatório.');

            if ($id) {
                $stmt = $db->prepare("UPDATE garcons SET nome = ?, codigo = ?, ativo = ? WHERE id = ?");
                $stmt->execute([$nome, $codigo, $ativo, $id]);
                $_SESSION['sucesso'] = 'Garçom atualizado com sucesso.';
            } else {
                $stmt = $db->prepare("INSERT INTO garcons (nome, codigo, ativo, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$nome, $codigo, $ativo]);
                $_SESSION['sucesso'] = 'Garçom criado com sucesso.';
            }
            header('Location: index.php'); exit;
        }

        if ($action === 'toggle_garcom') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('UPDATE garcons SET ativo = NOT ativo WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Status do garçom alterado.';
            header('Location: index.php'); exit;
        }

        if ($action === 'delete_garcom') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM garcons WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Garçom removido.';
            header('Location: index.php'); exit;
        }
    } catch (Exception $e) {
        $_SESSION['erro'] = 'Erro: ' . $e->getMessage();
        header('Location: index.php'); exit;
    }
}

// Buscar usuários
$stmt = $db->prepare('SELECT id, nome, email, perfil, ativo, created_at FROM usuarios ORDER BY id DESC');
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar garçons
$stmt = $db->prepare('SELECT id, nome, codigo, ativo, created_at FROM garcons ORDER BY nome');
$stmt->execute();
$garcons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar configuração de comissão - NOVO CÓDIGO
try {
    $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = "commission_rate"');
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
        <h1 class="h3">⚙️ Administração</h1>
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

    <!-- Card: Configurações -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">⚙️ Configurações do Sistema</h5>
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

    <!-- Card: Desempenho dos Garçons -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">📈 Desempenho dos Garçons</h5>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriodoRapido('hoje')">Hoje</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriodoRapido('semana')">Esta Semana</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriodoRapido('mes')">Este Mês</button>
                    </div>
                    <input type="date" id="data-inicio-garcons" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 150px;">
                    <input type="date" id="data-fim-garcons" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width: 150px;">
                    <button class="btn btn-primary btn-sm" onclick="atualizarDesempenhoGarcons()">Atualizar</button>
                </div>
            </div>
            <div id="garcons-performance">
                <div class="text-center py-4" id="garcons-loading">Carregando dados...</div>
            </div>
        </div>
    </div>

    <!-- Card: Gerenciar Garçons -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">👨‍🍳 Gerenciar Garçons</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#garcomModal" onclick="novoGarcom()">Novo Garçom</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Código</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($garcons as $garcom): ?>
                        <tr>
                            <td><?= htmlspecialchars($garcom['nome']) ?></td>
                            <td><?= htmlspecialchars($garcom['codigo'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $garcom['ativo'] ? 'success' : 'secondary' ?>">
                                    <?= $garcom['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($garcom['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editarGarcom(<?= $garcom['id'] ?>, '<?= htmlspecialchars($garcom['nome']) ?>', '<?= htmlspecialchars($garcom['codigo'] ?? '') ?>', <?= $garcom['ativo'] ?>)" data-bs-toggle="modal" data-bs-target="#garcomModal">Editar</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Alternar status?')">
                                    <input type="hidden" name="action" value="toggle_garcom">
                                    <input type="hidden" name="id" value="<?= $garcom['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Toggle</button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remover garçom?')">
                                    <input type="hidden" name="action" value="delete_garcom">
                                    <input type="hidden" name="id" value="<?= $garcom['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Card: Gerenciar Categorias -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">🏷️ Gerenciar Categorias</h5>
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

<!-- Modal Garçom -->
<div class="modal fade" id="garcomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="garcomModalTitle">Novo Garçom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="garcomForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_garcom">
                    <input type="hidden" name="id" id="garcom_id">
                    
                    <div class="mb-3">
                        <label for="garcom_nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="garcom_nome" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="garcom_codigo" class="form-label">Código</label>
                        <input type="text" class="form-control" id="garcom_codigo" name="codigo">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="garcom_ativo" name="ativo" checked>
                        <label class="form-check-label" for="garcom_ativo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS (bundle com Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= PathConfig::modules('admin/') ?>admin.js"></script>

<!-- Fetch and render performance dos garçons -->
<script>
function formatCurrency(v) {
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function formatPeriod(dataInicio, dataFim) {
    if (dataInicio === dataFim) {
        return new Date(dataInicio + 'T00:00:00').toLocaleDateString('pt-BR');
    }
    return new Date(dataInicio + 'T00:00:00').toLocaleDateString('pt-BR') + ' a ' + new Date(dataFim + 'T00:00:00').toLocaleDateString('pt-BR');
}

function carregarDesempenhoGarcons() {
    const container = document.getElementById('garcons-performance');
    const loading = document.getElementById('garcons-loading');
    const dataInicio = document.getElementById('data-inicio-garcons').value;
    const dataFim = document.getElementById('data-fim-garcons').value;
    const apiUrl = '<?= PathConfig::api("garcons.php") ?>';
    
    const url = `${apiUrl}?data_inicio=${dataInicio}&data_fim=${dataFim}`;
    
    if (!loading) {
        container.innerHTML = '<div class="text-center py-4">Carregando dados...</div>';
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = '<div class="alert alert-danger">Erro: ' + data.error + '</div>';
                return;
            }

            const avg = data.average || 0;
            const periodo = formatPeriod(data.data_inicio, data.data_fim);
            const commissionRate = data.commission_rate_percent || 3;
            let html = '';
            html += '<p class="small text-muted">Período: <strong>' + periodo + '</strong> • Comandas com garçons: <strong>' + data.total_comandas + '</strong> • Garçons ativos: <strong>' + data.active_garcons + '</strong> • Média: <strong>' + avg + '</strong> • Comissão: <strong>' + commissionRate + '%</strong></p>';
            html += '<div class="table-responsive"><table class="table table-sm align-middle">';
            html += '<thead><tr><th>Garçom</th><th>Comandas</th><th>Performance</th><th>Vendas</th><th>Comissão</th><th>Classificação</th></tr></thead><tbody>';

            data.garcons.forEach(g => {
                const pct = g.percent_of_average !== null ? g.percent_of_average : 0;
                const barValue = Math.min(pct, 200);
                const badgeClass = g.badge ? 'badge bg-' + g.badge : 'badge bg-secondary';
                html += '<tr>';
                html += '<td>' + (g.codigo ? ('<small class="text-muted">' + g.codigo + '</small> ') : '') + g.nome + '</td>';
                html += '<td>' + g.comandas + '</td>';
                html += '<td style="min-width:220px">';
                html += '<div class="d-flex align-items-center">';
                html += '<div class="progress flex-grow-1 me-2" style="height:12px"><div class="progress-bar" role="progressbar" style="width:' + barValue + '%" aria-valuenow="' + barValue + '" aria-valuemin="0" aria-valuemax="200"></div></div>';
                html += '<small class="text-muted">' + (g.percent_of_average !== null ? g.percent_of_average + '%':'-') + '</small>';
                html += '</div></td>';
                html += '<td>' + formatCurrency(g.vendas_total) + '</td>';
                html += '<td>' + formatCurrency(g.comissao) + '</td>';
                html += '<td><span class="' + badgeClass + '">' + g.classification + '</span></td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados</div>';
            console.error(err);
        });
}

function atualizarDesempenhoGarcons() {
    carregarDesempenhoGarcons();
}

function setPeriodoRapido(periodo) {
    const dataInicioInput = document.getElementById('data-inicio-garcons');
    const dataFimInput = document.getElementById('data-fim-garcons');
    const hoje = new Date();
    
    switch(periodo) {
        case 'hoje':
            const hojeStr = hoje.toISOString().split('T')[0];
            dataInicioInput.value = hojeStr;
            dataFimInput.value = hojeStr;
            break;
            
        case 'semana':
            const inicioSemana = new Date(hoje);
            inicioSemana.setDate(hoje.getDate() - hoje.getDay() + 1); // Segunda-feira
            dataInicioInput.value = inicioSemana.toISOString().split('T')[0];
            dataFimInput.value = hoje.toISOString().split('T')[0];
            break;
            
        case 'mes':
            const inicioMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            dataInicioInput.value = inicioMes.toISOString().split('T')[0];
            dataFimInput.value = hoje.toISOString().split('T')[0];
            break;
    }
    
    carregarDesempenhoGarcons();
}

document.addEventListener('DOMContentLoaded', function () {
    carregarDesempenhoGarcons();
});

function novoGarcom() {
    document.getElementById('garcomModalTitle').textContent = 'Novo Garçom';
    document.getElementById('garcomForm').reset();
    document.getElementById('garcom_id').value = '';
    document.getElementById('garcom_ativo').checked = true;
}

function editarGarcom(id, nome, codigo, ativo) {
    document.getElementById('garcomModalTitle').textContent = 'Editar Garçom';
    document.getElementById('garcom_id').value = id;
    document.getElementById('garcom_nome').value = nome;
    document.getElementById('garcom_codigo').value = codigo;
    document.getElementById('garcom_ativo').checked = ativo == 1;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
<?php
require_once '../../config/paths.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$garcons_ativo = getConfig('garcons_ativo', '1') === '1';

// Handlers POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'toggle_module') {
            $novo = $garcons_ativo ? '0' : '1';
            $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('garcons_ativo', ?, 'Modulo de garcons ativo') ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()");
            $stmt->execute([$novo]);
            $_SESSION['sucesso'] = $novo === '1' ? 'Modulo de garcons ativado.' : 'Modulo desativado. Comandas atribuidas ao caixa.';
            header('Location: index.php'); exit;
        }

        if ($action === 'save_garcom') {
            $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
            $nome = trim($_POST['nome'] ?? '');
            $codigo = trim($_POST['codigo'] ?? '');
            $ativo = isset($_POST['ativo']) ? true : false;
            if (empty($nome)) throw new Exception('Nome e obrigatorio.');

            if ($id) {
                $stmt = $db->prepare("UPDATE garcons SET nome = ?, codigo = ?, ativo = ? WHERE id = ?");
                $stmt->execute([$nome, $codigo, $ativo, $id]);
                $_SESSION['sucesso'] = 'Garcom atualizado.';
            } else {
                $stmt = $db->prepare("INSERT INTO garcons (nome, codigo, ativo, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$nome, $codigo, $ativo]);
                $_SESSION['sucesso'] = 'Garcom cadastrado.';
            }
            header('Location: index.php'); exit;
        }

        if ($action === 'toggle_garcom') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('UPDATE garcons SET ativo = NOT ativo WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Status alterado.';
            header('Location: index.php'); exit;
        }

        if ($action === 'delete_garcom') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM garcons WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sucesso'] = 'Garcom removido.';
            header('Location: index.php'); exit;
        }

        if ($action === 'save_commission') {
            $rate_percent = floatval($_POST['commission_rate'] ?? 3);
            if ($rate_percent < 0 || $rate_percent > 100) throw new Exception('Taxa entre 0% e 100%.');
            $rate = $rate_percent / 100;
            $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('commission_rate', ?, 'Taxa de comissao') ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = NOW()");
            $stmt->execute([$rate]);
            $_SESSION['sucesso'] = 'Comissao atualizada para ' . number_format($rate_percent, 1) . '%.';
            header('Location: index.php'); exit;
        }
    } catch (Exception $e) {
        $_SESSION['erro'] = $e->getMessage();
        header('Location: index.php'); exit;
    }
}

// Buscar dados
$stmt = $db->prepare('SELECT * FROM garcons ORDER BY nome');
$stmt->execute();
$garcons = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_commission = 0.03;
try {
    $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'commission_rate'");
    $stmt->execute();
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) $current_commission = floatval($c['valor']);
} catch (Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-badge"></i> Garcons</h1>
            <p class="text-muted mb-0" style="font-size:0.85rem;">Gerencie sua equipe de atendimento e acompanhe o desempenho.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="POST">
                <input type="hidden" name="action" value="toggle_module">
                <button type="submit" class="btn <?= $garcons_ativo ? 'btn-success' : 'btn-outline-secondary' ?> btn-sm">
                    <i class="bi bi-<?= $garcons_ativo ? 'check-circle' : 'x-circle' ?>"></i>
                    <?= $garcons_ativo ? 'Modulo Ativo' : 'Modulo Desativado' ?>
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i><?= $_SESSION['sucesso'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['sucesso']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-1"></i><?= $_SESSION['erro'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['erro']); ?>
    <?php endif; ?>

    <?php if (!$garcons_ativo): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-person-slash" style="font-size:3rem;color:var(--text-muted);"></i>
            <h5 class="mt-3">Modulo de Garcons Desativado</h5>
            <p class="text-muted">Todas as comandas serao atribuidas ao operador do caixa.<br>Ative o modulo para gerenciar garcons e acompanhar desempenho.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-4">
        <!-- COLUNA ESQUERDA: Cadastro + Lista -->
        <div class="col-lg-7">
            <!-- Card: Lista de Garcons -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0"><i class="bi bi-people"></i> Equipe</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#garcomModal" onclick="novoGarcom()">
                            <i class="bi bi-plus-lg"></i> Novo Garcom
                        </button>
                    </div>
                    <?php if (empty($garcons)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-person-plus" style="font-size:2rem;"></i>
                        <p class="mt-2">Nenhum garcom cadastrado ainda.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Nome</th><th>Codigo</th><th>Status</th><th>Acoes</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($garcons as $g): ?>
                                <tr class="<?= $g['ativo'] ? '' : 'table-secondary' ?>">
                                    <td class="fw-medium"><?= htmlspecialchars($g['nome']) ?></td>
                                    <td><code><?= htmlspecialchars($g['codigo'] ?? '-') ?></code></td>
                                    <td>
                                        <span class="badge bg-<?= $g['ativo'] ? 'success' : 'secondary' ?>">
                                            <?= $g['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editarGarcom(<?= $g['id'] ?>, '<?= htmlspecialchars(addslashes($g['nome'])) ?>', '<?= htmlspecialchars(addslashes($g['codigo'] ?? '')) ?>', <?= $g['ativo'] ? 1 : 0 ?>)" data-bs-toggle="modal" data-bs-target="#garcomModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Alternar status?')">
                                                <input type="hidden" name="action" value="toggle_garcom">
                                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                                <button type="submit" class="btn btn-outline-warning"><i class="bi bi-toggle-on"></i></button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remover este garcom?')">
                                                <input type="hidden" name="action" value="delete_garcom">
                                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mb-0"><?= count($garcons) ?> garcons cadastrados</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card: Comissao -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-percent"></i> Taxa de Comissao</h5>
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="save_commission">
                        <div class="col-auto">
                            <label class="form-label">Percentual sobre vendas</label>
                            <div class="input-group" style="width:180px;">
                                <input type="number" class="form-control" name="commission_rate" value="<?= $current_commission * 100 ?>" min="0" max="100" step="0.1" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Atual: <?= number_format($current_commission * 100, 1) ?>%</div>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: Desempenho -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0"><i class="bi bi-graph-up-arrow"></i> Desempenho</h5>
                    </div>
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" onclick="setPeriodo('hoje')">Hoje</button>
                            <button class="btn btn-outline-secondary" onclick="setPeriodo('semana')">Semana</button>
                            <button class="btn btn-outline-secondary" onclick="setPeriodo('mes')">Mes</button>
                        </div>
                        <input type="date" id="perf-inicio" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width:130px;">
                        <input type="date" id="perf-fim" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width:130px;">
                        <button class="btn btn-primary btn-sm" onclick="carregarDesempenho()"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                    <div id="desempenho-container">
                        <div class="text-center py-4 text-muted">Carregando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Modal Garcom -->
<div class="modal fade" id="garcomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="garcomModalTitle">Novo Garcom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_garcom">
                    <input type="hidden" name="id" id="garcom_id">
                    <div class="mb-3">
                        <label class="form-label">Nome *</label>
                        <input type="text" class="form-control" name="nome" id="garcom_nome" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Codigo</label>
                        <input type="text" class="form-control" name="codigo" id="garcom_codigo" placeholder="Ex: G01">
                        <div class="form-text">Codigo curto para identificacao rapida no caixa.</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ativo" id="garcom_ativo" checked>
                        <label class="form-check-label" for="garcom_ativo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function novoGarcom() {
    document.getElementById('garcomModalTitle').textContent = 'Novo Garcom';
    document.getElementById('garcom_id').value = '';
    document.getElementById('garcom_nome').value = '';
    document.getElementById('garcom_codigo').value = '';
    document.getElementById('garcom_ativo').checked = true;
}

function editarGarcom(id, nome, codigo, ativo) {
    document.getElementById('garcomModalTitle').textContent = 'Editar Garcom';
    document.getElementById('garcom_id').value = id;
    document.getElementById('garcom_nome').value = nome;
    document.getElementById('garcom_codigo').value = codigo;
    document.getElementById('garcom_ativo').checked = ativo == 1;
}

function formatCurrency(v) {
    return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function setPeriodo(tipo) {
    const hoje = new Date();
    const fim = hoje.toISOString().split('T')[0];
    let inicio = fim;
    if (tipo === 'semana') {
        const seg = new Date(hoje);
        seg.setDate(hoje.getDate() - hoje.getDay() + 1);
        inicio = seg.toISOString().split('T')[0];
    } else if (tipo === 'mes') {
        inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1).toISOString().split('T')[0];
    }
    document.getElementById('perf-inicio').value = inicio;
    document.getElementById('perf-fim').value = fim;
    carregarDesempenho();
}

function carregarDesempenho() {
    const inicio = document.getElementById('perf-inicio').value;
    const fim = document.getElementById('perf-fim').value;
    const container = document.getElementById('desempenho-container');
    container.innerHTML = '<div class="text-center py-4 text-muted">Carregando...</div>';

    fetch(`<?= PathConfig::api('garcons.php') ?>?data_inicio=${inicio}&data_fim=${fim}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.garcons || data.garcons.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">Sem dados no periodo.</div>';
                return;
            }

            let html = '<div class="small text-muted mb-2">Comandas: <strong>' + data.total_comandas + '</strong> &middot; Comissao: <strong>' + data.commission_rate_percent + '%</strong></div>';

            data.garcons.forEach(g => {
                const pct = g.percent_of_average ?? 0;
                const bar = Math.min(pct, 200);
                const badgeClass = g.badge || 'secondary';
                html += `
                <div class="d-flex align-items-center py-2 border-bottom" style="gap:10px;">
                    <div style="min-width:90px;">
                        <div class="fw-bold" style="font-size:0.85rem;">${g.nome}</div>
                        <div class="text-muted" style="font-size:0.7rem;">${g.codigo || ''} &middot; ${g.comandas} cmd</div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-${badgeClass}" style="width:${bar/2}%"></div>
                        </div>
                    </div>
                    <div class="text-end" style="min-width:80px;font-size:0.8rem;">
                        <div class="fw-bold">${formatCurrency(g.vendas_total)}</div>
                        <div class="text-muted" style="font-size:0.7rem;">${formatCurrency(g.comissao)}</div>
                    </div>
                    <span class="badge bg-${badgeClass}" style="font-size:0.65rem;">${g.classification}</span>
                </div>`;
            });

            container.innerHTML = html;
        })
        .catch(err => {
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar</div>';
            console.error(err);
        });
}

document.addEventListener('DOMContentLoaded', carregarDesempenho);
</script>

<?php require_once '../../includes/footer.php'; ?>

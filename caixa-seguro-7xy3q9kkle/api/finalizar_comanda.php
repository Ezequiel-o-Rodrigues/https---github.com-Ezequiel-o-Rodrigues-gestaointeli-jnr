<?php
// API: finalizar_comanda.php
// Retorna JSON limpo; usa Database class
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Buffer para evitar qualquer saída acidental
if (!ob_get_level()) ob_start();

$data = json_decode(file_get_contents('php://input'), true);

try {
    $comanda_id = $data['comanda_id'] ?? null;

    if (!$comanda_id) {
        throw new Exception('Comanda ID não informado');
    }

    // Conectar ao banco
    $database = new Database();
    $db = $database->getConnection();

    // === PRE-TRANSACTION: verificar tabelas e configs ANTES de abrir transação ===
    $has_itens_livres = false;
    $check = $db->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'itens_livres' AND table_schema = 'public'");
    if ($check->fetch()) $has_itens_livres = true;

    $taxa_config = 0;
    $tipo_taxa = 'nenhuma';
    try {
        $stmt = $db->prepare("SELECT taxa_gorjeta, tipo_taxa FROM configuracoes ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $conf = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($conf) {
            $taxa_config = floatval($conf['taxa_gorjeta'] ?? 0);
            $tipo_taxa = $conf['tipo_taxa'] ?? 'nenhuma';
        }
    } catch (Exception $e) {
        error_log("configuracoes table not found, using defaults");
    }

    // === TRANSACTION START ===
    $db->beginTransaction();

    // Calcular total da comanda
    if ($has_itens_livres) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as total FROM (SELECT subtotal FROM itens_comanda WHERE comanda_id = ? UNION ALL SELECT subtotal FROM itens_livres WHERE comanda_id = ?) t");
        $stmt->execute([$comanda_id, $comanda_id]);
    } else {
        $stmt = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as total FROM itens_comanda WHERE comanda_id = ?");
        $stmt->execute([$comanda_id]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_comanda = floatval($row['total'] ?? 0);

    if ($tipo_taxa === 'percentual' && $taxa_config > 0) {
        $taxa_gorjeta = ($total_comanda * $taxa_config) / 100;
    } elseif ($tipo_taxa === 'fixa' && $taxa_config > 0) {
        $taxa_gorjeta = $taxa_config;
    } else {
        $taxa_gorjeta = 0;
    }

    // Atualizar comanda
    $stmt = $db->prepare("UPDATE comandas SET status = 'fechada', valor_total = ?, taxa_gorjeta = ?, data_venda = NOW() WHERE id = ?");
    $stmt->execute([$total_comanda, $taxa_gorjeta, $comanda_id]);

    // Baixar estoque: para cada item em itens_comanda, inserir movimentacao tipo 'saida' e decrementar estoque
    $stmt = $db->prepare("SELECT ic.produto_id, ic.quantidade FROM itens_comanda ic WHERE ic.comanda_id = ?");
    $stmt->execute([$comanda_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mov_stmt = $db->prepare("INSERT INTO movimentacoes_estoque (produto_id, tipo, quantidade, observacao, data_movimentacao, created_at) VALUES (?, 'saida', ?, ?, NOW(), NOW())");
    $update_prod = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - ?, updated_at = NOW() WHERE id = ?");

    foreach ($itens as $item) {
        $pid = (int)$item['produto_id'];
        $qtd = (int)$item['quantidade'];
        // registrar movimentação
        $mov_stmt->execute([$pid, $qtd, 'Venda - comanda ' . $comanda_id]);
        // decrementar estoque (não bloqueamos negativo aqui)
        $update_prod->execute([$qtd, $pid]);
    }

    // Gerar comprovante automaticamente
    $query_comprovante = "
        SELECT 
            c.id as comanda_id,
            c.valor_total,
            c.taxa_gorjeta,
            c.data_venda,
            g.nome as garcom_nome,
            g.codigo as garcom_codigo,
            STRING_AGG(CONCAT(p.nome, '|', ic.quantidade, '|', ic.preco_unitario, '|', ic.subtotal), ';') as itens
        FROM comandas c
        LEFT JOIN garcons g ON c.garcom_id = g.id
        LEFT JOIN itens_comanda ic ON c.id = ic.comanda_id
        LEFT JOIN produtos p ON ic.produto_id = p.id
        WHERE c.id = ?
        GROUP BY c.id, c.valor_total, c.taxa_gorjeta, c.data_venda, g.nome, g.codigo
    ";
    
    $stmt_comp = $db->prepare($query_comprovante);
    $stmt_comp->execute([$comanda_id]);
    $comanda_data = $stmt_comp->fetch(PDO::FETCH_ASSOC);
    
    $comprovante_id = null;
    if ($comanda_data) {
        // Gerar conteúdo do comprovante
        $conteudo = gerarConteudoComprovante($comanda_data);
        
        // Salvar comprovante
        $query_insert = "INSERT INTO comprovantes_venda (comanda_id, conteudo, tipo) VALUES (?, ?, 'cliente') RETURNING id";
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->execute([$comanda_id, $conteudo]);
        $row_comp = $stmt_insert->fetch(PDO::FETCH_ASSOC);
        $comprovante_id = $row_comp['id'] ?? null;
    }

    $db->commit();

    // Limpar buffer e enviar JSON
    while (ob_get_level()) ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Comanda finalizada e estoque baixado com sucesso',
        'valor_total' => $total_comanda,
        'taxa_gorjeta' => $taxa_gorjeta,
        'comprovante_id' => $comprovante_id
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function gerarConteudoComprovante($comanda) {
    $itens = explode(';', $comanda['itens']);
    $linhas = [];
    
    // Comandos ESC/POS
    $reset = "\x1B\x40"; // Reset printer
    $center = "\x1B\x61\x01"; // Center align
    $left = "\x1B\x61\x00"; // Left align
    $bold_on = "\x1B\x45\x01"; // Bold on
    $bold_off = "\x1B\x45\x00"; // Bold off
    $cut = "\x1B\x69"; // Partial cut
    
    // Cabeçalho
    $linhas[] = $reset . $center . $bold_on . strtoupper(getNomeEstabelecimento()) . $bold_off . $left;
    $linhas[] = str_repeat("-", 48);
    $linhas[] = "Comanda: #" . $comanda['comanda_id'];
    $linhas[] = "Data: " . date('d/m/Y H:i', strtotime($comanda['data_venda']));
    $linhas[] = "Garçom: " . ($comanda['garcom_nome'] ? $comanda['garcom_nome'] . " (" . $comanda['garcom_codigo'] . ")" : "Não informado");
    $linhas[] = str_repeat("-", 48);
    $linhas[] = "QTD  DESCRICAO";
    $linhas[] = "     VALOR UNIT.   SUBTOTAL";
    $linhas[] = str_repeat("-", 48);
    
    // Itens
    foreach ($itens as $item) {
        if (empty($item)) continue;
        
        list($nome, $quantidade, $preco_unitario, $subtotal) = explode('|', $item);
        
        // Formatar nome do produto
        $nome_linhas = str_split($nome, 25);
        
        $linhas[] = str_pad($quantidade, 4) . " " . $nome_linhas[0];
        $linhas[] = "     R$ " . str_pad(number_format($preco_unitario, 2, ',', '.'), 8) . 
                   "   R$ " . number_format($subtotal, 2, ',', '.');
        
        // Linhas adicionais do nome
        for ($i = 1; $i < count($nome_linhas); $i++) {
            $linhas[] = "     " . $nome_linhas[$i];
        }
        
        $linhas[] = ""; // Linha em branco
    }
    
    // Rodapé
    $linhas[] = str_repeat("-", 48);
    $linhas[] = "SUBTOTAL: R$ " . number_format($comanda['valor_total'] - $comanda['taxa_gorjeta'], 2, ',', '.');
    $linhas[] = "GORJETA:  R$ " . number_format($comanda['taxa_gorjeta'], 2, ',', '.');
    $linhas[] = str_repeat("=", 48);
    $linhas[] = $bold_on . "TOTAL:    R$ " . number_format($comanda['valor_total'], 2, ',', '.') . $bold_off;
    $linhas[] = str_repeat("=", 48);
    $linhas[] = "";
    $linhas[] = $center . "OBRIGADO PELA PREFERÊNCIA!";
    $linhas[] = $center . "VOLTE SEMPRE!";
    $linhas[] = "";
    $linhas[] = $center . date('d/m/Y H:i:s');
    $linhas[] = "\n\n\n\n\n"; // Avançar papel
    $linhas[] = $cut; // Cortar papel
    
    return implode("\n", $linhas);
}

?>
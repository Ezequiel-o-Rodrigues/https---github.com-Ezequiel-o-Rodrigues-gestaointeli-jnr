<?php
ini_set('display_errors', 0);
require_once __DIR__ . '/../config/paths.php';
require_once PathConfig::config('database.php');
header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);
    
    $comanda_id = $data['comanda_id'] ?? null;
    $produto_id = $data['produto_id'] ?? null;
    $quantidade = $data['quantidade'] ?? 1;
    $item_id = $data['item_id'] ?? null;
    $nova_quantidade = $data['nova_quantidade'] ?? null;

    // Se for alteração de quantidade de item existente
    if ($item_id && $nova_quantidade !== null) {
        // Buscar produto do item para recalcular subtotal
        $query_item = "SELECT ic.produto_id, p.preco, p.nome FROM itens_comanda ic JOIN produtos p ON ic.produto_id = p.id WHERE ic.id = ?";
        $stmt_item = $db->prepare($query_item);
        $stmt_item->execute([$item_id]);
        $item_info = $stmt_item->fetch(PDO::FETCH_ASSOC);
        
        if (!$item_info) {
            throw new Exception('Item não encontrado');
        }
        
        // Atualizar quantidade e subtotal
        $novo_subtotal = $item_info['preco'] * $nova_quantidade;
        $query_update = "UPDATE itens_comanda SET quantidade = ?, subtotal = ? WHERE id = ?";
        $stmt_update = $db->prepare($query_update);
        $stmt_update->execute([$nova_quantidade, $novo_subtotal, $item_id]);
        
        // Atualizar total da comanda
        $query_total = "SELECT SUM(subtotal) as total FROM itens_comanda WHERE comanda_id = ?";
        $stmt_total = $db->prepare($query_total);
        $stmt_total->execute([$comanda_id]);
        $total = $stmt_total->fetch(PDO::FETCH_ASSOC);
        
        $query_update_comanda = "UPDATE comandas SET valor_total = ? WHERE id = ?";
        $stmt_update_comanda = $db->prepare($query_update_comanda);
        $stmt_update_comanda->execute([$total['total'] ?? 0, $comanda_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantidade alterada com sucesso'
        ]);
        return;
    }

    if (!$comanda_id || !$produto_id) {
        throw new Exception('Dados incompletos');
    }

    // 1. Verificar se produto existe e tem estoque disponível (APENAS VERIFICAÇÃO)
    $query_produto = "SELECT estoque_atual, nome, preco FROM produtos WHERE id = ? AND ativo = true";
    $stmt_produto = $db->prepare($query_produto);
    $stmt_produto->execute([$produto_id]);
    $produto = $stmt_produto->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new Exception('Produto não encontrado ou inativo');
    }

    // 2. Verificar estoque disponível (APENAS VALIDAÇÃO)
    if ($produto['estoque_atual'] < $quantidade) {
        throw new Exception('Estoque insuficiente para o produto: ' . $produto['nome']);
    }

    // 3. Verificar se item já existe na comanda
    $query_existe = "SELECT id, quantidade FROM itens_comanda   WHERE comanda_id = ? AND produto_id = ?";
    $stmt_existe = $db->prepare($query_existe);
    $stmt_existe->execute([$comanda_id, $produto_id]);
    $item_existente = $stmt_existe->fetch(PDO::FETCH_ASSOC);

    if ($item_existente) {
        // Atualizar quantidade do item existente
        $nova_quantidade = $item_existente['quantidade'] + $quantidade;
        $novo_subtotal = $produto['preco'] * $nova_quantidade;
        $query_update = "UPDATE itens_comanda SET quantidade = ?, subtotal = ? WHERE id = ?";
        $stmt_update = $db->prepare($query_update);
        $stmt_update->execute([$nova_quantidade, $novo_subtotal, $item_existente['id']]);
    } else {
        // Inserir novo item
        $subtotal = $produto['preco'] * $quantidade;
        $query_insert = "INSERT INTO itens_comanda (comanda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->execute([$comanda_id, $produto_id, $quantidade, $produto['preco'], $subtotal]);
    }

    // 4. Atualizar total da comanda (SEM BAIXAR ESTOQUE)
    $query_total = "SELECT SUM(subtotal) as total FROM itens_comanda WHERE comanda_id = ?";
    $stmt_total = $db->prepare($query_total);
    $stmt_total->execute([$comanda_id]);
    $total = $stmt_total->fetch(PDO::FETCH_ASSOC);

    $query_update_comanda = "UPDATE comandas SET valor_total = ? WHERE id = ?";
    $stmt_update_comanda = $db->prepare($query_update_comanda);
    $stmt_update_comanda->execute([$total['total'] ?? 0, $comanda_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Item adicionado à comanda (estoque será baixado apenas na finalização)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        throw new Exception('ID da categoria é obrigatório');
    }

    // Verificar se existem produtos vinculados
    $stmt = $db->prepare("SELECT COUNT(*) FROM produtos WHERE categoria_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Não é possível excluir uma categoria que possui produtos vinculados.');
    }

    $stmt = $db->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Categoria removida com sucesso'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

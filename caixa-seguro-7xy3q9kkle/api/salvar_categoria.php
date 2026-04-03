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
    
    if (!$input || empty($input['nome'])) {
        throw new Exception('Nome da categoria é obrigatório');
    }

    $id = $input['id'] ?? null;
    $nome = trim($input['nome']);

    if ($id) {
        $stmt = $db->prepare("UPDATE categorias SET nome = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nome, $id]);
        $message = 'Categoria atualizada com sucesso';
    } else {
        $stmt = $db->prepare("INSERT INTO categorias (nome, created_at) VALUES (?, NOW()) RETURNING id");
        $stmt->execute([$nome]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $row['id'];
        $message = 'Categoria criada com sucesso';
    }

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

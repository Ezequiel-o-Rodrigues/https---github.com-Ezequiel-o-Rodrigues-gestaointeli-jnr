<?php
// criar_comanda.php - Módulo de Caixa

$base_path = '/';
require_once __DIR__ . '/../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $garcomCodigo = $input['garcom'] ?? null;
    
    if (!$garcomCodigo) {
        echo json_encode(['success' => false, 'message' => 'Código do garçom não informado']);
        exit;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Buscar ID do garçom pelo código
        $queryGarcom = "SELECT id, nome, codigo FROM garcons WHERE codigo = :codigo AND ativo = true";
        $stmtGarcom = $db->prepare($queryGarcom);
        $stmtGarcom->bindParam(':codigo', $garcomCodigo);
        $stmtGarcom->execute();
        $garcom = $stmtGarcom->fetch(PDO::FETCH_ASSOC);
        
        if (!$garcom) {
            echo json_encode(['success' => false, 'message' => 'Garçom não encontrado ou inativo!']);
            exit;
        }
        
        // Criar nova comanda
        $query = "INSERT INTO comandas (garcom_id, status, valor_total, created_at, updated_at)
                  VALUES (:garcom_id, 'aberta', 0.00, NOW(), NOW()) RETURNING id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':garcom_id', $garcom['id']);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $comandaId = $row['id'];
        
        echo json_encode([
            'success' => true, 
            'comanda_id' => $comandaId,
            'garcom' => $garcom['codigo'],
            'garcom_nome' => $garcom['nome'],
            'message' => 'Comanda #' . $comandaId . ' criada para ' . $garcom['nome'] . ' (' . $garcom['codigo'] . ')'
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao criar comanda: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno do sistema: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>
<?php
// ✅ CORRIGIDO
require_once __DIR__ . '/../config/paths.php';
require_once PathConfig::config('database.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO comandas (status, valor_total, taxa_gorjeta) VALUES ('aberta', 0, 0) RETURNING id";
    $stmt = $db->prepare($query);

    if ($stmt->execute()) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $comanda_id = $row['id'];
        
        echo json_encode([
            'success' => true,
            'comanda_id' => (int)$comanda_id,
            'message' => 'Comanda criada com sucesso'
        ]);
    } else {
        throw new Exception('Falha ao executar query INSERT');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar comanda: ' . $e->getMessage()
    ]);
}
?>
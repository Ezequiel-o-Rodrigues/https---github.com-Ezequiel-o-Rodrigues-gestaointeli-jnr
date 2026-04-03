<?php
/**
 * API: Marcar Perda Dinâmica como Visualizada
 * Arquivo: api/marcar_perda_visualizada_dinamica.php
 * 
 * Responsabilidades:
 * - Marcar perda calculada dinamicamente como visualizada
 * - Salvar na tabela perdas_estoque se não existir
 * - Permitir que o usuário marque as perdas do período como visualizadas
 * 
 * @author Sistema de Gestão
 * @date 2025-12-15
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Receber dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $produto_id = $input['produto_id'] ?? null;
    $data_identificacao = $input['data_identificacao'] ?? date('Y-m-d H:i:s');
    $quantidade_perdida = $input['quantidade_perdida'] ?? 0;
    $valor_perda = $input['valor_perda'] ?? 0;
    
    // Validação
    if (!$produto_id || !$quantidade_perdida || !$valor_perda) {
        throw new Exception('Dados da perda são obrigatórios');
    }

    if ($quantidade_perdida < 0 || $valor_perda < 0) {
        throw new Exception('Valores de perda não podem ser negativos');
    }
    
    // Verificar se já existe registro para este produto nesta data
    $check_stmt = $db->prepare("
        SELECT id FROM perdas_estoque 
        WHERE produto_id = ? AND DATE(data_identificacao) = DATE(?)
    ");
    $check_stmt->execute([$produto_id, $data_identificacao]);
    $perda_existente = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($perda_existente) {
        // Atualizar como visualizada
        $update_stmt = $db->prepare("
            UPDATE perdas_estoque 
            SET visualizada = 1, 
                data_visualizacao = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$perda_existente['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Perda atualizada como visualizada',
            'perda_id' => $perda_existente['id']
        ]);
    } else {
        // Criar novo registro como visualizado
        $insert_stmt = $db->prepare("
            INSERT INTO perdas_estoque (
                produto_id, 
                quantidade_perdida, 
                valor_perda,
                data_identificacao,
                visualizada,
                data_visualizacao,
                motivo
            ) VALUES (?, ?, ?, ?, 1, NOW(), 'Registrado do período') RETURNING id
        ");

        $insert_stmt->execute([
            $produto_id,
            $quantidade_perdida,
            $valor_perda,
            $data_identificacao
        ]);
        $row = $insert_stmt->fetch(PDO::FETCH_ASSOC);
        $perda_id = $row['id'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Perda registrada e marcada como visualizada',
            'perda_id' => $perda_id
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

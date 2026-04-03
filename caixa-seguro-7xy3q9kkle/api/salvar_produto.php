<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Ler dados do POST (multipart/form-data agora)
    $input = $_POST;
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }

    // Validações básicas
    if (empty($input['nome'])) {
        throw new Exception('Nome do produto é obrigatório');
    }
    if (empty($input['categoria_id'])) {
        throw new Exception('Categoria é obrigatória');
    }
    if (!isset($input['preco']) || $input['preco'] < 0) {
        throw new Exception('Preço inválido');
    }
    if (!isset($input['estoque_minimo']) || $input['estoque_minimo'] < 0) {
        throw new Exception('Estoque mínimo inválido');
    }

    $estoque_inicial = $input['estoque_inicial'] ?? 0;
    $imagem_nome = null;

    // Processar upload de imagem
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['imagem']['tmp_name'];
        $file_name = $_FILES['imagem']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed_exts)) {
            throw new Exception('Extensão de arquivo não permitida');
        }

        $new_file_name = uniqid('prod_') . '.' . $file_ext;
        $upload_dir = __DIR__ . '/../public/images/products/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
            $imagem_nome = $new_file_name;
        } else {
            throw new Exception('Erro ao mover arquivo de imagem');
        }
    }

    if (isset($input['id']) && !empty($input['id'])) {
        // ATUALIZAR produto existente
        $query = "UPDATE produtos SET 
                  nome = :nome, 
                  categoria_id = :categoria_id, 
                  preco = :preco, 
                  estoque_minimo = :estoque_minimo,";
        
        if ($imagem_nome) {
            $query .= " imagem = :imagem,";
        }
        
        $query .= " updated_at = NOW() WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':nome', $input['nome']);
        $stmt->bindParam(':categoria_id', $input['categoria_id']);
        $stmt->bindParam(':preco', $input['preco']);
        $stmt->bindParam(':estoque_minimo', $input['estoque_minimo']);
        $stmt->bindParam(':id', $input['id']);
        if ($imagem_nome) {
            $stmt->bindParam(':imagem', $imagem_nome);
        }

        $success = $stmt->execute();

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Produto atualizado com sucesso',
                'id' => $input['id']
            ]);
        } else {
            throw new Exception('Erro ao atualizar produto');
        }

    } else {
        // NOVO produto
        $query = "INSERT INTO produtos
                  (nome, categoria_id, preco, estoque_minimo, estoque_atual, imagem, ativo, created_at)
                  VALUES
                  (:nome, :categoria_id, :preco, :estoque_minimo, :estoque_atual, :imagem, true, NOW())
                  RETURNING id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':nome', $input['nome']);
        $stmt->bindParam(':categoria_id', $input['categoria_id']);
        $stmt->bindParam(':preco', $input['preco']);
        $stmt->bindParam(':estoque_minimo', $input['estoque_minimo']);
        $stmt->bindParam(':estoque_atual', $estoque_inicial);
        $stmt->bindParam(':imagem', $imagem_nome);

        $success = $stmt->execute();

        if ($success) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $produto_id = $row['id'];
            
            // Se tem estoque inicial, registrar movimentação
            if ($estoque_inicial > 0) {
                $mov_query = "INSERT INTO movimentacoes_estoque 
                             (produto_id, tipo, quantidade, observacao, created_at) 
                             VALUES 
                             (:produto_id, 'entrada', :quantidade, 'Estoque inicial', NOW())";
                
                $mov_stmt = $db->prepare($mov_query);
                $mov_stmt->bindParam(':produto_id', $produto_id);
                $mov_stmt->bindParam(':quantidade', $estoque_inicial);
                $mov_stmt->execute();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Produto criado com sucesso',
                'id' => $produto_id
            ]);
        } else {
            throw new Exception('Erro ao criar produto');
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
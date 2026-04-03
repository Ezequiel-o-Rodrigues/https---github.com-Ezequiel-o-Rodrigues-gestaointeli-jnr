<?php
require_once __DIR__ . '/config/auth.php';

// DEBUG
error_log("=== INICIANDO LOGIN ===");

// Se já estiver logado, redireciona para página principal
if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    error_log("Tentando login com: " . $login);
    
    if (!empty($login) && !empty($senha)) {
        require_once __DIR__ . '/config/database.php';
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Aceitar email como login
            $stmt = $db->prepare("SELECT id, nome, email, senha, perfil, ativo FROM usuarios WHERE email = ? AND ativo = true");
            $stmt->execute([$login]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                error_log("Usuário encontrado: " . $usuario['nome']);
                error_log("Hash no BD: " . $usuario['senha']);
                error_log("Senha fornecida: " . $senha);
                
                // Verificar senha
                if (password_verify($senha, $usuario['senha'])) {
                    error_log("✅ SENHA VÁLIDA!");
                    
                    // Login bem-sucedido
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_perfil'] = $usuario['perfil'];
                    $_SESSION['usuario_logado'] = true;
                    $_SESSION['last_activity'] = time();
                    
                    error_log("SESSION após login: " . print_r($_SESSION, true));
                    error_log("✅ Login bem-sucedido! Redirecionando...");
                    
                    header('Location: index.php');
                    exit;
                } else {
                    error_log("❌ SENHA INVÁLIDA!");
                    $error = 'Senha incorreta';
                }
            } else {
                error_log("❌ USUÁRIO NÃO ENCONTRADO OU INATIVO");
                $error = 'Usuário não encontrado ou inativo';
            }
        } catch (Exception $e) {
            error_log("❌ ERRO NO BANCO: " . $e->getMessage());
            $error = 'Erro ao processar login: ' . $e->getMessage();
        }
    } else {
        $error = 'Preencha todos os campos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GestaoInteli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #334155 0%, #4f46e5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #334155 0%, #4f46e5 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-header h2 { font-weight: 700; letter-spacing: -0.02em; }
        .login-header p { opacity: 0.85; font-size: 0.9rem; }
        .login-body {
            padding: 2rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79,70,229,0.4);
        }
        .form-control:focus {
            border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(79,70,229,0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <h2><i class="bi bi-shop"></i> GestaoInteli</h2>
                        <p class="mb-0">Sistema de Gestao Comercial</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> 
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['expired'])): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-clock"></i> 
                                Sessão expirada. Faça login novamente.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['logout'])): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 
                                Logout realizado com sucesso.
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Usuário ou Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="email" name="email" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="junior ou admin@sistema.com">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" required 
                                           placeholder="Sua senha">
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-login">
                                    <i class="bi bi-box-arrow-in-right"></i> Entrar
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <strong>para recuperar a senha contacte o desenvolvedor</strong><br>
                                <br>
                                ezequielrod2020@gmail.com
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Entrando...';
        });
    </script>
</body>
</html>
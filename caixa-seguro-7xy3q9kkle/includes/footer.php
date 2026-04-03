<?php
$usuarioLogado = isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true;
?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= getNomeSistema() ?> &mdash; Todos os direitos reservados</p>
        </div>
    </footer>

    <!-- Scripts por página -->
    <script>
        const currentPath = window.location.pathname;
        if (currentPath.includes('/relatorios/')) {
            if (typeof Relatorios === 'undefined') {
                const script = document.createElement('script');
                script.src = './relatorios.js?v=<?= time() ?>';
                script.onerror = function() {
                    console.error('Erro ao carregar relatorios.js');
                };
                document.head.appendChild(script);
            }
        }
    </script>
</body>
</html>

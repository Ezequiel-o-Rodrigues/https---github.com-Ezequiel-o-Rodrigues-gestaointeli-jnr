// modules/caixa/caixa.js
// Proteção contra dupla inicialização/declaração quando o script é incluído mais de uma vez
if (window.CaixaSystemAlreadyLoaded) {
    console.warn('CaixaSystem já carregado. Ignorando segunda inclusão.');
} else {
    window.CaixaSystemAlreadyLoaded = true;

    class CaixaSystem {
    constructor() {
        this.comandaAtual = null;
        this.itensComanda = [];
        this.produtos = [];
        this.carregando = false;
        
        this.init();
    }
    
    async init() {
        await this.carregarProdutos();
        this.configurarEventos();

        // Se o servidor informou uma comanda atual (via window.appConfig), sincronizar
        try {
            if (window.appConfig && window.appConfig.comandaAtualId) {
                const cid = window.appConfig.comandaAtualId;
                if (cid) {
                    this.comandaAtual = { id: cid };
                    await this.carregarItensComanda();
                    this.atualizarUIComanda();
                }
            }
        } catch (e) {
            console.warn('Não foi possível sincronizar comanda inicial:', e);
        }

        this.mostrarToast('Sistema de caixa carregado', 'success');
    }
    
    configurarEventos() {
        // Nota: botões finalizarComanda e cancelarComanda têm onclick inline no template
        // que delega para as funções globais, então não registramos listeners aqui
        // para evitar duplicação de chamadas
        
        // Nova comanda (sem onclick inline, então usamos addEventListener)
        const btnNova = document.getElementById('btn-nova-comanda');
        if (btnNova) {
            btnNova.addEventListener('click', () => this.novaComanda());
        }
        
        // Busca de produtos (aceitar nomes de id alternativos do template)
        const buscaInput = document.getElementById('busca-produto') || document.getElementById('search-produto');
        if (buscaInput) {
            buscaInput.addEventListener('input', (e) => {
                this.filtrarProdutos(e.target.value);
            });
        }
        
        // Filtro por categoria
        document.querySelectorAll('.categoria-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.filtrarPorCategoria(e.target.dataset.categoria);
            });
        });
        
        // Foco automático na busca
        if (buscaInput) {
            buscaInput.focus();
        }

        // NOTA: Os produtos têm onclick inline no template (adicionarProduto)
        // que delega para this.adicionarItem(), então NÃO registramos listeners aqui
        // para evitar duplicação de chamadas de clique
    }
    
    async carregarProdutos() {
        try {
            this.mostrarLoadingProdutos(true);
            
            // AJUSTE: tentar endpoint API padrão (se houver) — se não existir, o código atual do servidor renderiza produtos em PHP
            const response = await fetch(PathConfig.api('produtos_categoria.php'));
            const data = await response.json();
            
            if (data.success && data.produtos) {
                this.produtos = data.produtos;
            } else if (Array.isArray(data)) {
                // alguns endpoints podem retornar diretamente um array
                this.produtos = data;
            } else if (data.produtos) {
                this.produtos = data.produtos;
                this.renderizarProdutos(this.produtos);
            } else {
                throw new Error(data.message || 'Erro ao carregar produtos');
            }
        } catch (error) {
            console.error('Erro ao carregar produtos:', error);
            this.mostrarToast('Erro ao carregar produtos', 'error');
        } finally {
            this.mostrarLoadingProdutos(false);
        }
    }
    
    renderizarProdutos(produtos) {
        const container = document.getElementById('lista-produtos');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (produtos.length === 0) {
            container.innerHTML = '<div class="produto-vazio">Nenhum produto encontrado</div>';
            return;
        }
        
        produtos.forEach(produto => {
            const produtoEl = this.criarElementoProduto(produto);
            container.appendChild(produtoEl);
        });
    }
    
    criarElementoProduto(produto) {
        const div = document.createElement('div');
        div.className = 'produto-card';
        div.innerHTML = `
            <div class="produto-info">
                <h4 class="produto-nome">${this.escapeHtml(produto.nome)}</h4>
                <p class="produto-preco">R$ ${parseFloat(produto.preco).toFixed(2)}</p>
                <div class="produto-estoque ${produto.estoque_atual <= produto.estoque_minimo ? 'estoque-baixo' : ''}">
                    Estoque: ${produto.estoque_atual}
                    ${produto.estoque_atual <= produto.estoque_minimo ? '⚠️' : ''}
                </div>
            </div>
            <button class="btn-add-item" data-produto-id="${produto.id}">
                Adicionar
            </button>
        `;
        
        div.querySelector('.btn-add-item').addEventListener('click', () => {
            this.adicionarItem(produto.id, 1);
        });
        
        return div;
    }
    
    async novaComanda() {
        if (this.carregando) return;
        
        try {
            this.carregando = true;
            
            // Usar endpoint PHP do projeto (sem garçom)
            const response = await fetch(PathConfig.api('nova_comanda.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (data.success) {
                // API pode retornar comanda (objeto) ou apenas comanda_id
                if (data.comanda) {
                    this.comandaAtual = data.comanda;
                } else if (data.comanda_id) {
                    this.comandaAtual = { id: data.comanda_id };
                } else {
                    // fallback: tentar mensagem/estrutura diversa
                    this.comandaAtual = { id: null };
                }

                this.itensComanda = [];
                this.atualizarUIComanda();
                this.mostrarToast('Nova comanda criada (sem garçom)', 'success');
            } else {
                throw new Error(data.message || 'Erro ao criar comanda');
            }
        } catch (error) {
            console.error('Erro ao criar comanda:', error);
            this.mostrarToast('Erro ao criar comanda', 'error');
        } finally {
            this.carregando = false;
        }
    }
    
    async criarComandaComGarcom(garcomCodigo) {
        if (this.carregando) return;
        
        try {
            this.carregando = true;
            
            // Usar endpoint específico para criar comanda com garçom
            const response = await fetch('criar_comanda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ garcom: garcomCodigo })
            });

            const data = await response.json();

            if (data.success) {
                this.comandaAtual = { id: data.comanda_id };
                this.itensComanda = [];
                this.atualizarUIComanda();
                this.mostrarToast(data.message || 'Comanda criada com garçom', 'success');
                return true;
            } else {
                throw new Error(data.message || 'Erro ao criar comanda com garçom');
            }
        } catch (error) {
            console.error('Erro ao criar comanda com garçom:', error);
            this.mostrarToast('Erro ao criar comanda: ' + error.message, 'error');
            return false;
        } finally {
            this.carregando = false;
        }
    }
    
    async adicionarItem(produtoId, quantidade = 1) {
        if (!this.comandaAtual) {
            // Criar comanda automaticamente SEM CONFIRMAÇÃO
            await this.novaComanda();

            if (!this.comandaAtual || !this.comandaAtual.id) {
                this.mostrarToast('Não foi possível criar a comanda', 'error');
                return;
            }
        }
        
        if (this.carregando) return;
        
        try {
            this.carregando = true;
            
            // Usar endpoint PHP instalado no projeto
            const response = await fetch(PathConfig.api('adicionar_item.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comanda_id: this.comandaAtual.id,
                    produto_id: produtoId,
                    quantidade: quantidade
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.carregarItensComanda();
                this.mostrarToast('Item adicionado à comanda', 'success');
            } else {
                this.mostrarToast(data.message || 'Erro ao adicionar item', 'error');
            }
        } catch (error) {
            console.error('Erro ao adicionar item:', error);
            this.mostrarToast('Erro ao adicionar item', 'error');
        } finally {
            this.carregando = false;
        }
    }
    
    async carregarItensComanda() {
        if (!this.comandaAtual) return;
        
        try {
            // Usar endpoint PHP do projeto
            const response = await fetch(PathConfig.api(`itens_comanda.php?comanda_id=${this.comandaAtual.id}`));
            const data = await response.json();

            // DEBUG: log para diagnóstico
            console.log('Resposta itens_comanda.php:', data);

            // aceitar formatos diferentes retornados pela API
            if (data.success && data.itens) {
                this.itensComanda = data.itens;
            } else if (data.itens && Array.isArray(data.itens)) {
                this.itensComanda = data.itens;
            } else if (Array.isArray(data)) {
                this.itensComanda = data;
            } else if (data.itens_comanda) {
                this.itensComanda = data.itens_comanda;
            } else {
                // Se nada der match, tentar assumir que é um array vazio ou erro
                this.itensComanda = [];
                console.warn('Formato inesperado na resposta de itens:', data);
            }

            console.log('Itens carregados:', this.itensComanda);

            this.atualizarListaItens();
            this.atualizarTotal();
            this.atualizarBotaoFinalizar();
        } catch (error) {
            console.error('Erro ao carregar itens:', error);
            this.itensComanda = [];
            this.atualizarListaItens();
        }
    }
    
    atualizarListaItens() {
        // Usar container horizontal (novo layout) ou fallback para lista-itens (se existir)
        const containerHorizontal = document.getElementById('itens-comanda-horizontal') || document.getElementById('itens-comanda');
        const containerLista = document.getElementById('lista-itens');
        
        // Decidir qual container usar
        const container = containerHorizontal || containerLista;
        const totalElement = document.getElementById('total-comanda');
        
        if (!container) {
            console.warn('Nenhum container de itens encontrado (itens-comanda-horizontal, itens-comanda, ou lista-itens)');
            return;
        }
        
        console.log('Atualizando lista itens. Container:', container.id, 'Itens:', this.itensComanda.length);
        
        if (this.itensComanda.length === 0) {
            container.innerHTML = '<div class="empty-comanda">Nenhum item adicionado</div>';
            if (totalElement) totalElement.textContent = '0.00';
            return;
        }
        
        container.innerHTML = '';
        this.itensComanda.forEach((item, index) => {
            try {
                let itemEl;
                // Renderizar em formato horizontal se for container horizontal
                if (container === containerHorizontal) {
                    itemEl = this.criarElementoItemHorizontal(item);
                } else {
                    itemEl = this.criarElementoItem(item);
                }
                if (itemEl) {
                    container.appendChild(itemEl);
                }
            } catch (e) {
                console.error('Erro ao renderizar item:', index, item, e);
            }
        });
        
        console.log('Lista de itens atualizada com sucesso');
    }
    
    criarElementoItem(item) {
        const div = document.createElement('div');
        div.className = 'item-comanda';
        // Aceitar tanto 'nome_produto' quanto 'nome'
        const nomeProduto = item.nome_produto || item.nome || 'Produto';
        div.innerHTML = `
            <div class="item-info">
                <span class="item-nome">${this.escapeHtml(nomeProduto)}</span>
                <span class="item-quantidade">${item.quantidade}x</span>
                <span class="item-subtotal">R$ ${parseFloat(item.subtotal).toFixed(2)}</span>
            </div>
            <button class="btn-remover-item" data-item-id="${item.id}">
                ✕
            </button>
        `;
        
        div.querySelector('.btn-remover-item').addEventListener('click', () => {
            this.removerItem(item.id);
        });
        
        return div;
    }

    criarElementoItemHorizontal(item) {
        const div = document.createElement('div');
        div.className = 'item-comanda-horizontal';
        // Aceitar tanto 'nome_produto' quanto 'nome'
        const nomeProduto = item.nome_produto || item.nome || 'Produto';
        const preco = item.subtotal ? parseFloat(item.subtotal).toFixed(2) : '0.00';
        
        div.innerHTML = `
            <span class="item-nome">${this.escapeHtml(nomeProduto)}</span>
            <span class="item-quantidade" data-item-id="${item.id}" title="Clique para alterar quantidade">${item.quantidade}</span>
            <span class="item-preco">R$ ${preco}</span>
            <button class="btn-remover" data-item-id="${item.id}" type="button">✕</button>
        `;
        
        const btnRemover = div.querySelector('.btn-remover');
        if (btnRemover) {
            btnRemover.addEventListener('click', (e) => {
                e.stopPropagation();
                console.log('Botão remover clicado. Item ID:', item.id);
                this.removerItem(item.id);
            });
        }
        
        const quantidadeSpan = div.querySelector('.item-quantidade');
        if (quantidadeSpan) {
            quantidadeSpan.addEventListener('click', (e) => {
                e.stopPropagation();
                this.alterarQuantidadeItem(item.id, item.quantidade, nomeProduto);
            });
        }
        
        return div;
    }
    
    async alterarQuantidadeItem(itemId, quantidadeAtual, nomeProduto) {
        const novaQuantidade = prompt(`Quantidade de "${nomeProduto}":`, quantidadeAtual);
        if (novaQuantidade === null) return; // Cancelou
        
        const qtd = parseInt(novaQuantidade);
        if (isNaN(qtd) || qtd <= 0) {
            alert('Quantidade inválida');
            return;
        }
        
        if (qtd === parseInt(quantidadeAtual)) return; // Sem alteração
        
        if (this.carregando) {
            this.mostrarToast('Operação em andamento...', 'info');
            return;
        }
        
        try {
            this.carregando = true;
            
            const response = await fetch(PathConfig.api('adicionar_item.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    comanda_id: this.comandaAtual.id,
                    item_id: itemId,
                    nova_quantidade: qtd
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                await this.carregarItensComanda();
                this.mostrarToast('Quantidade alterada', 'success');
            } else {
                this.mostrarToast(data.message || 'Erro ao alterar quantidade', 'error');
            }
        } catch (error) {
            console.error('Erro ao alterar quantidade:', error);
            this.mostrarToast('Erro ao alterar quantidade', 'error');
        } finally {
            this.carregando = false;
        }
    }
    
    async removerItem(itemId) {
        console.log('removerItem chamado. itemId:', itemId, 'comandaAtual:', this.comandaAtual, 'carregando:', this.carregando);
        
        if (!this.comandaAtual) {
            this.mostrarToast('Nenhuma comanda ativa', 'warning');
            return;
        }
        
        if (this.carregando) {
            this.mostrarToast('Operação em andamento...', 'info');
            return;
        }
        
        try {
            this.carregando = true;
            
            console.log('Enviando requisição para remover item:', itemId, 'de comanda:', this.comandaAtual.id);
            
            // Usar endpoint PHP do projeto (API espera comanda_id E item_id)
            const response = await fetch(PathConfig.api('remover_item.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comanda_id: this.comandaAtual.id,
                    item_id: itemId
                })
            });
            
            const data = await response.json();
            
            console.log('Resposta remover_item:', data);
            
            if (data.success) {
                await this.carregarItensComanda();
                this.mostrarToast('Item removido com sucesso', 'success');
            } else {
                this.mostrarToast(data.message || 'Erro ao remover item', 'error');
            }
        } catch (error) {
            console.error('Erro ao remover item:', error);
            this.mostrarToast('Erro ao remover item: ' + error.message, 'error');
        } finally {
            this.carregando = false;
        }
    }
    
    async finalizarComanda() {
        console.log('finalizarComanda chamado. Estado:', {
            comandaAtual: this.comandaAtual,
            itensCount: this.itensComanda.length,
            carregando: this.carregando
        });

        if (!this.comandaAtual || this.itensComanda.length === 0) {
            this.mostrarToast('Comanda vazia ou não existe comanda aberta', 'warning');
            return;
        }
        
        if (this.carregando) {
            this.mostrarToast('Operação em andamento, aguarde...', 'info');
            return;
        }
        
        // Primeiro validar estoque
        console.log('Validando estoque...');
        const estoqueOk = await this.validarEstoqueFinalizacao();
        if (!estoqueOk) {
            this.mostrarToast('Estoque insuficiente para finalizar venda', 'error');
            return;
        }
        
        
        try {
            this.carregando = true;
            
            console.log('Enviando requisição para finalizar comanda:', this.comandaAtual.id);
            
            // Usar endpoint PHP do projeto
            const response = await fetch(PathConfig.api('finalizar_comanda.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comanda_id: this.comandaAtual.id
                })
            });
            
            const data = await response.json();
            
            console.log('Resposta finalizar_comanda:', data);
            
            if (data.success) {
                this.mostrarToast('Comanda finalizada com sucesso! Total: R$ ' + data.valor_total.toFixed(2), 'success');
                
                // Mostrar modal de comprovante
                const modal = document.getElementById('modalComprovante');
                const totalElement = document.getElementById('totalVenda');
                
                if (modal) {
                    // Atualizar valor total no modal
                    if (totalElement) {
                        totalElement.innerHTML = `Total: <strong>R$ ${data.valor_total.toFixed(2).replace('.', ',')}</strong>`;
                    }
                    
                    // Armazenar comprovante_id para os botões
                    modal.dataset.comprovanteId = data.comprovante_id;
                    
                    // Mostrar modal
                    modal.style.display = 'flex';
                }
                
                // Limpar comanda
                this.limparComanda();
            } else {
                this.mostrarToast(data.message || 'Erro ao finalizar comanda', 'error');
                console.error('Erro na resposta:', data.message);
            }
        } catch (error) {
            console.error('Erro ao finalizar comanda:', error);
            this.mostrarToast('Erro ao finalizar comanda: ' + error.message, 'error');
        } finally {
            this.carregando = false;
        }
    }
    
    async validarEstoqueFinalizacao() {
        if (!this.comandaAtual) {
            console.warn('validarEstoqueFinalizacao: comanda não existe');
            return false;
        }
        
        try {
            console.log('Validando estoque para comanda:', this.comandaAtual.id);
            
            // Usar endpoint PHP do projeto
            const response = await fetch(PathConfig.api('verificar_estoque.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comanda_id: this.comandaAtual.id
                })
            });
            
            const data = await response.json();
            console.log('Resposta verificar_estoque:', data);
            
            // aceitar diferentes formatos
            if (typeof data.estoque_suficiente !== 'undefined') {
                const resultado = data.estoque_suficiente;
                console.log('Estoque suficiente:', resultado);
                return resultado;
            }
            if (typeof data.success !== 'undefined' && data.success === false) {
                console.log('API retornou erro');
                return false;
            }
            console.log('Estoque válido (sem bloqueios explícitos)');
            return true;
        } catch (error) {
            console.error('Erro ao validar estoque:', error);
            return false;
        }
    }

    cancelarComanda() {
        if (!this.comandaAtual) {
            this.mostrarToast('Nenhuma comanda ativa', 'warning');
            return;
        }
        
        if (confirm('Cancelar comanda atual? Os itens serão perdidos.')) {
            this.limparComanda();
            this.mostrarToast('Comanda cancelada', 'info');
        }
    }
    
    limparComanda() {
        this.comandaAtual = null;
        this.itensComanda = [];
        this.atualizarUIComanda();
    }
    
    atualizarUIComanda() {
        const numeroElement = document.getElementById('comanda-numero') || document.getElementById('numero-comanda');
        const totalElement = document.getElementById('total-comanda');
        
        if (!numeroElement) {
            console.warn('Elemento numero-comanda não encontrado');
            return;
        }
        
        console.log('Atualizando UI comanda. Estado:', this.comandaAtual);
        
        if (this.comandaAtual) {
            numeroElement.textContent = '#' + this.comandaAtual.id;
        } else {
            numeroElement.textContent = '--';
            if (totalElement) totalElement.textContent = '0.00';
            this.atualizarListaItens();
        }
        
        this.atualizarBotaoFinalizar();
    }
    
    atualizarTotal() {
        const totalElement = document.getElementById('total-comanda');
        if (!totalElement) return;
        
        const total = this.itensComanda.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
        totalElement.textContent = total.toFixed(2);
    }
    
    atualizarBotaoFinalizar() {
        const btnFinalizar = document.getElementById('btn-finalizar');
        if (btnFinalizar) {
            btnFinalizar.disabled = !this.comandaAtual || this.itensComanda.length === 0;
        }
    }
    
    filtrarProdutos(termo) {
        const produtosFiltrados = this.produtos.filter(produto =>
            produto.nome.toLowerCase().includes(termo.toLowerCase())
        );
        this.renderizarProdutos(produtosFiltrados);
    }
    
    filtrarPorCategoria(categoria) {
        // Ativar botão da categoria
        document.querySelectorAll('.categoria-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.categoria === categoria);
        });
        
        if (categoria === 'todas') {
            this.renderizarProdutos(this.produtos);
        } else {
            const produtosFiltrados = this.produtos.filter(produto =>
                produto.categoria_nome === categoria
            );
            this.renderizarProdutos(produtosFiltrados);
        }
    }
    
    mostrarLoadingProdutos(mostrar) {
        const loading = document.getElementById('loading-produtos');
        const lista = document.getElementById('lista-produtos');
        
        if (!loading || !lista) return;
        
        if (mostrar) {
            loading.style.display = 'block';
            lista.style.display = 'none';
        } else {
            loading.style.display = 'none';
            lista.style.display = 'grid';
        }
    }
    
    mostrarToast(mensagem, tipo = 'info') {
        // Criar container se não existir
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 3000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: center;
            `;
            document.body.appendChild(toastContainer);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${tipo}`;
        toast.textContent = mensagem;
        
        // Estilos do toast
        const cores = {
            success: '#27ae60',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };
        
        toast.style.cssText = `
            background: ${cores[tipo] || cores.info};
            color: white;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-size: 14px;
            max-width: 300px;
            word-wrap: break-word;
            animation: slideIn 0.3s ease;
        `;
        
        toastContainer.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 3000);
        
        // Adicionar animações CSS se não existirem
        if (!document.getElementById('toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    

    mostrarModalComprovante(comprovanteId, valorTotal) {
        console.log('mostrarModalComprovante chamado:', comprovanteId, valorTotal);
        
        const modal = document.getElementById('modalComprovante');
        console.log('Modal encontrado:', modal);
        
        const totalElement = document.getElementById('totalVenda');
        console.log('Total element encontrado:', totalElement);
        
        if (!modal) {
            console.error('Modal de comprovante não encontrado no DOM');
            return;
        }
        
        // Atualizar valor total
        if (totalElement) {
            totalElement.innerHTML = `Total: <strong>R$ ${valorTotal.toFixed(2).replace('.', ',')}</strong>`;
        }
        
        console.log('Mostrando modal...');
        // Mostrar modal
        modal.style.display = 'flex';
        
        // Armazenar comprovante_id para uso nos botões
        modal.dataset.comprovanteId = comprovanteId;
        
        console.log('Modal deve estar visível agora');
    }
    
    fecharModalComprovante() {
        const modal = document.getElementById('modalComprovante');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    // No caixa.js, atualize o método imprimirComprovante:

async imprimirComprovante(comprovanteId) {
    try {
        console.log('Solicitando comprovante:', comprovanteId);
        
        const response = await fetch('/api/imprimir_simples.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({comprovante_id: comprovanteId})
        });
        
        const data = await response.json();
        
        if (data.success) {
            // DELEGAR COMPLETAMENTE PARA O SERVIÇO DE IMPRESSÃO
            if (window.impressaoService) {
                const resultado = await window.impressaoService.imprimirComprovante(data.conteudo);
                
                if (resultado.success) {
                    if (resultado.usadoUSB) {
                        this.mostrarToast('✅ Comprovante impresso!', 'success');
                    } else if (resultado.fallback) {
                        this.mostrarToast('📄 Comprovante aberto para impressão', 'success');
                    } else if (resultado.download) {
                        this.mostrarToast('📥 Comprovante baixado - imprima manualmente', 'info');
                    }
                } else {
                    this.mostrarToast('❌ ' + resultado.message, 'error');
                }
            } else {
                // Fallback direto se o serviço não carregou
                this.mostrarComprovanteParaImpressao(data.conteudo);
            }
        } else {
            this.mostrarToast('Erro: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erro geral de impressão:', error);
        this.mostrarToast('Erro no sistema de impressão', 'error');
    } finally {
        // Fechar modal independente do resultado
        this.fecharModalComprovante();
    }
}
    mostrarComprovanteParaImpressao(conteudo) {
        // Converter comandos ESC/POS para texto legível
        const textoLimpo = conteudo
            .replace(/\x1B[\x40\x61\x01\x00\x45\x69]/g, '') // Remover comandos ESC/POS
            .replace(/\x01/g, '')
            .replace(/\x00/g, '')
            .replace(/\n{3,}/g, '\n\n'); // Reduzir múltiplas quebras de linha
        
        const janela = window.open('', '_blank', 'width=400,height=600');
        janela.document.write(`
            <html>
                <head>
                    <title>Comprovante</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; margin: 20px; }
                        pre { white-space: pre-wrap; }
                    </style>
                </head>
                <body>
                    <pre>${textoLimpo}</pre>
                    <script>
                        window.onload = function() {
                            if (confirm('Imprimir comprovante?')) {
                                window.print();
                            }
                        };
                    </script>
                </body>
            </html>
        `);
        janela.document.close();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inicializar sistema quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    // evitar duplicação de instância
    if (!window.caixaSystem) {
        window.caixaSystem = new CaixaSystem();
    }
});

}
class EstoqueManager {
    constructor() {
        this.debug = true;
        this.baseUrl = '';
        this.apiUrl = '../../api';
        this.modalInitialized = false;
        
        // Log para debug
        console.log('[EstoqueManager] Inicializado com apiUrl:', this.apiUrl);
    }

    init() {
        this.log('🚀 EstoqueManager inicializado');
        this.initializeModals();
        this.setupEventListeners();
        this.loadInitialData();
    }

    log(message, data = null) {
        if (this.debug) {
            console.log(`[Estoque] ${message}`, data || '');
        }
    }

    error(message, error = null) {
        console.error(`[Estoque] ❌ ${message}`, error || '');
    }

    initializeModals() {
        this.log('Inicializando modais');
        this.modalInitialized = true;
    }

    setupEventListeners() {
        this.log('Configurando event listeners');
        
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'entryForm') {
                e.preventDefault();
                this.registerEntry();
            }
            if (e.target.id === 'productForm') {
                e.preventDefault();
                this.saveProduct();
            }
        });

        const imageInput = document.getElementById('productImage');
        if (imageInput) {
            imageInput.addEventListener('change', (e) => this.previewImage(e));
        }
    }

    loadInitialData() {
        this.log('Carregando dados iniciais');
    }

    showEntryModal(productId = null) {
        this.log('Abrindo modal de entrada', { productId });
        
        try {
            const form = document.getElementById('entryForm');
            if (form) form.reset();
            
            if (productId) {
                this.loadProductForEntry(productId);
            } else {
                const productIdField = document.getElementById('entryProductId');
                const productNameField = document.getElementById('entryProductName');
                if (productIdField) productIdField.value = '';
                if (productNameField) productNameField.textContent = '';
            }
            
            const modalElement = document.getElementById('entryModal');
            if (!modalElement) {
                throw new Error('Modal de entrada não encontrado');
            }
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
        } catch (error) {
            this.error('Erro ao abrir modal de entrada', error);
            this.showAlert('Erro ao abrir formulário de entrada', 'error');
        }
    }

    showProductModal(productId = null) {
        this.log('Abrindo modal de produto', { productId });
        
        try {
            const title = document.getElementById('productModalLabel');
            const form = document.getElementById('productForm');
            
            if (!title) {
                throw new Error('Elemento productModalLabel não encontrado');
            }
            if (!form) {
                throw new Error('Elemento productForm não encontrado');
            }
            
            if (productId) {
                title.textContent = 'Editar Produto';
                this.loadProductData(productId);
            } else {
                title.textContent = 'Novo Produto';
                form.reset();
                const productIdField = document.getElementById('productId');
                if (productIdField) {
                    productIdField.value = '';
                }
            }
            
            const modalElement = document.getElementById('productModal');
            if (!modalElement) {
                throw new Error('Modal de produto não encontrado');
            }
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            
        } catch (error) {
            this.error('Erro ao abrir modal de produto', error);
            this.showAlert('Erro ao abrir formulário de produto', 'error');
        }
    }

    async loadProductData(productId) {
        this.log('Carregando dados do produto', { productId });
        
        try {
            const url = `${this.apiUrl}/produto_info.php?id=${productId}`;
            console.log('[EstoqueManager] URL para carregar produto:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const product = await response.json();
            this.log('Dados do produto carregados', product);

            this.setFormValue('productId', product.id);
            this.setFormValue('productName', product.nome);
            this.setFormValue('productCategory', product.categoria_id);
            this.setFormValue('productPrice', product.preco);
            this.setFormValue('productMinStock', product.estoque_minimo);
            this.setFormValue('productInitialStock', product.estoque_atual || 0);

            // Carregar preview se houver imagem
            if (product.imagem) {
                this.showImagePreview(product.imagem);
            } else {
                this.hideImagePreview();
            }

        } catch (error) {
            this.error('Erro ao carregar produto', error);
            this.showAlert('Erro ao carregar dados do produto', 'error');
        }
    }

    async loadProductForEntry(productId) {
        this.log('Carregando produto para entrada', { productId });
        
        try {
            const url = `${this.apiUrl}/produto_info_simple.php?id=${productId}`;
            this.log('URL da requisição:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} - ${response.statusText}`);
            }
            
            const result = await response.json();
            this.log('Resposta da API:', result);

            // Usar os dados retornados, mesmo se success for false
            this.setFormValue('entryProductId', result.id || productId);
            this.setTextContent('entryProductName', result.nome || `Produto ID: ${productId}`);
            
            if (!result.success) {
                this.log('Produto não encontrado, mas modal será aberto:', result.message);
            }

        } catch (error) {
            this.error('Erro ao carregar produto para entrada', error);
            
            // Fallback: mesmo com erro, permitir que o modal abra com ID do produto
            this.setFormValue('entryProductId', productId);
            this.setTextContent('entryProductName', `Produto ID: ${productId}`);
            
            // Não mostrar alerta de erro, apenas log
            this.log('Usando fallback para produto:', productId);
        }
    }

    setFormValue(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.value = value || '';
            this.log(`Campo ${elementId} preenchido com: ${value}`);
        } else {
            this.error(`Elemento não encontrado: ${elementId}`);
        }
    }

    setTextContent(elementId, text) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text || '';
            this.log(`Texto ${elementId} definido como: ${text}`);
        } else {
            this.error(`Elemento não encontrado: ${elementId}`);
        }
    }

    async saveProduct() {
        this.log('Salvando produto...');
        
        try {
            const form = document.getElementById('productForm');
            const formData = new FormData();
            
            formData.append('id', document.getElementById('productId')?.value || '');
            formData.append('nome', document.getElementById('productName')?.value || '');
            formData.append('categoria_id', document.getElementById('productCategory')?.value || '');
            formData.append('preco', document.getElementById('productPrice')?.value || '');
            formData.append('estoque_minimo', document.getElementById('productMinStock')?.value || '');
            formData.append('estoque_inicial', document.getElementById('productInitialStock')?.value || '0');
            
            const imageInput = document.getElementById('productImage');
            if (imageInput && imageInput.files[0]) {
                formData.append('imagem', imageInput.files[0]);
            }

            this.log('Enviando FormData...');

            if (!document.getElementById('productName')?.value || !document.getElementById('productCategory')?.value) {
                throw new Error('Preencha todos os campos obrigatórios');
            }

            const button = document.querySelector('#productModal .btn-primary');
            if (!button) {
                throw new Error('Botão de salvar não encontrado');
            }

            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            button.disabled = true;

            const url = `${this.apiUrl}/salvar_produto.php`;
            console.log('[EstoqueManager] URL para salvar produto:', url);

            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            this.log('Resposta da API', { status: response.status, url: response.url });

            const result = await response.json();
            this.log('Resultado do salvamento', result);

            if (result.success) {
                this.showAlert('Produto salvo com sucesso!', 'success');
                this.hideModal('productModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(result.message || 'Erro ao salvar produto');
            }

        } catch (error) {
            this.error('Erro ao salvar produto', error);
            this.showAlert('Erro: ' + error.message, 'error');
        } finally {
            const button = document.querySelector('#productModal .btn-primary');
            if (button) {
                button.innerHTML = 'Salvar Produto';
                button.disabled = false;
            }
        }
    }

    async registerEntry() {
        this.log('Registrando entrada...');
        
        try {
            const produtoIdElement = document.getElementById('entryProductId');
            const quantidadeElement = document.getElementById('entryQuantity');
            const fornecedorElement = document.getElementById('entrySupplier');
            const observacaoElement = document.getElementById('entryNotes');
            
            const formData = {
                produto_id: produtoIdElement?.value || '',
                quantidade: quantidadeElement?.value || '',
                fornecedor_id: fornecedorElement?.value || null,
                observacao: observacaoElement?.value || ''
            };

            this.log('Dados da entrada', formData);

            // Validações mais específicas
            if (!formData.produto_id || formData.produto_id === '') {
                throw new Error('Selecione um produto');
            }
            
            if (!formData.quantidade || formData.quantidade === '' || isNaN(formData.quantidade)) {
                throw new Error('Digite uma quantidade válida');
            }
            
            const quantidade = parseInt(formData.quantidade);
            if (quantidade <= 0) {
                throw new Error('A quantidade deve ser maior que zero');
            }
            
            // Atualizar formData com quantidade convertida
            formData.quantidade = quantidade;

            const button = document.querySelector('#entryModal .btn-primary');
            if (!button) {
                throw new Error('Botão de registrar não encontrado');
            }

            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
            button.disabled = true;

            const response = await fetch(`${this.apiUrl}/registrar_entrada.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            this.log('Resposta da API', { status: response.status });

            const result = await response.json();
            this.log('Resultado do registro', result);

            if (result.success) {
                this.showAlert('Entrada registrada com sucesso!', 'success');
                this.hideModal('entryModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                throw new Error(result.message || 'Erro ao registrar entrada');
            }

        } catch (error) {
            this.error('Erro ao registrar entrada', error);
            this.showAlert('Erro: ' + error.message, 'error');
        } finally {
            const button = document.querySelector('#entryModal .btn-primary');
            if (button) {
                button.innerHTML = 'Registrar Entrada';
                button.disabled = false;
            }
        }
    }

    async toggleProduct(productId, newStatus) {
        const action = newStatus ? 'ativar' : 'desativar';
        this.log(`${action} produto`, { productId, newStatus });
        
        if (!confirm(`Deseja ${action} este produto?`)) {
            this.log('Ação cancelada pelo usuário');
            return;
        }

        try {
            const response = await fetch(`${this.apiUrl}/toggle_produto.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    produto_id: productId,
                    ativo: newStatus
                })
            });

            this.log('Resposta da API', { status: response.status });

            const result = await response.json();
            this.log('Resultado do toggle', result);

            if (result.success) {
                this.showAlert(`Produto ${action}do com sucesso!`, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(result.message || `Erro ao ${action} produto`);
            }

        } catch (error) {
            this.error(`Erro ao ${action} produto`, error);
            this.showAlert('Erro: ' + error.message, 'error');
        }
    }

    filterProducts() {
        const categorySelects = document.querySelectorAll('[onchange="estoqueManager.filterProducts()"]');
        const searchInput = document.querySelector('[onkeyup="estoqueManager.filterProducts()"]');
        
        const category = categorySelects[0]?.value || 'all';
        const status = categorySelects[1]?.value || 'all';
        const search = (searchInput?.value || '').toLowerCase();

        this.log('Filtrando produtos', { category, status, search });

        document.querySelectorAll('.product-row').forEach(row => {
            const rowCategory = row.getAttribute('data-category');
            const rowStatus = row.getAttribute('data-status');
            const rowName = row.getAttribute('data-name');

            const categoryMatch = category === 'all' || rowCategory === category;
            const statusMatch = status === 'all' || rowStatus === status;
            const searchMatch = rowName.includes(search);

            if (categoryMatch && statusMatch && searchMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    editProduct(productId) {
        this.log('Editando produto', { productId });
        this.showProductModal(productId);
    }

    hideModal(modalId) {
        const modalElement = document.getElementById(modalId);
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }

    showAlert(message, type = 'info') {
        this.log(`Exibindo alerta: ${message}`, { type });
        
        if (type === 'error') {
            alert('❌ ' + message);
        } else if (type === 'success') {
            alert('✅ ' + message);
        } else {
            alert('ℹ️ ' + message);
        }
    }

    showInventoryModal(productId = null) {
        if (productId) {
            const produtoSelect = document.getElementById('produto_id');
            if (produtoSelect) {
                produtoSelect.value = productId;
                this.triggerEvent(produtoSelect, 'change');
            }
        }
        
        const modalElement = document.getElementById('inventarioModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }

    triggerEvent(element, eventName) {
        const event = new Event(eventName, { bubbles: true });
        element.dispatchEvent(event);
    }

    previewImage(event) {
        const input = event.target;
        const preview = document.getElementById('imagePreview');
        const img = preview.querySelector('img');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            this.hideImagePreview();
        }
    }

    showImagePreview(filename) {
        const preview = document.getElementById('imagePreview');
        if (preview) {
            const img = preview.querySelector('img');
            // Supõe que as imagens estão em /public/images/products/ no sistema do cardapio
            // Mas para o estoque, precisamos do caminho correto do servidor
            // O cardapio usa public/images/menu/ ou algo assim. 
            // Vamos usar o BASE_URL para resolver isso.
            const basePath = window.appConfig ? window.appConfig.basePath : '/';
            // Caminho configurado no Render e Hostinger: public_html/public/images/
            img.src = `../../public/images/products/${filename}`;
            preview.style.display = 'block';
        }
    }

    hideImagePreview() {
        const preview = document.getElementById('imagePreview');
        if (preview) {
            preview.style.display = 'none';
            const img = preview.querySelector('img');
            img.src = '';
        }
    }
}

function abrirModalInventarioProduto(produtoId) {
    if (window.estoqueManager) {
        window.estoqueManager.showInventoryModal(produtoId);
    }
}

const estoqueManager = new EstoqueManager();
// modules/admin/admin.js

function openUserModal(user) {
    const modalEl = document.getElementById('userModal');
    const userId = document.getElementById('userId');
    const userNome = document.getElementById('userNome');
    const userEmail = document.getElementById('userEmail');
    const userSenha = document.getElementById('userSenha');
    const userPerfil = document.getElementById('userPerfil');
    const userAtivo = document.getElementById('userAtivo');

    if (!user) {
        // novo
        userId.value = '';
        userNome.value = '';
        userEmail.value = '';
        userSenha.value = '';
        userPerfil.value = 'usuario';
        userAtivo.checked = true;
    } else {
        userId.value = user.id || '';
        userNome.value = user.nome || '';
        userEmail.value = user.email || '';
        userSenha.value = '';
        userPerfil.value = user.perfil || 'usuario';
        userAtivo.checked = !!user.ativo;
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

document.addEventListener('DOMContentLoaded', function () {
    const btnNovo = document.getElementById('btn-novo-usuario');
    if (btnNovo) btnNovo.addEventListener('click', () => openUserModal(null));
});

function novaCategoria() {
    document.getElementById('categoriaModalTitle').textContent = 'Nova Categoria';
    document.getElementById('cat_nome').value = '';
    document.getElementById('cat_id').value = '';
}

function editarCategoria(id, nome) {
    document.getElementById('categoriaModalTitle').textContent = 'Editar Categoria';
    document.getElementById('cat_nome').value = nome;
    document.getElementById('cat_id').value = id;
}

async function salvarCategoria() {
    const nome = document.getElementById('cat_nome').value;
    const id = document.getElementById('cat_id').value;

    if (!nome) {
        alert('Nome é obrigatório');
        return;
    }

    try {
        const response = await fetch(PathConfig.api('salvar_categoria.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, nome })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
        alert('Erro ao salvar categoria');
    }
}

async function deletarCategoria(id) {
    if (!confirm('Deseja realmente excluir esta categoria?')) return;

    try {
        const response = await fetch(PathConfig.api('deletar_categoria.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
        alert('Erro ao excluir categoria');
    }
}

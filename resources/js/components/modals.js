const openModal = (id) => {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('open');
};

const closeModal = (id) => {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('open');
};

export const initModals = () => {
    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => openModal(button.dataset.modalOpen));
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.modalClose || button.closest('[data-modal]')?.id;
            if (targetId) closeModal(targetId);
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal.id);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-modal].open').forEach((modal) => {
            modal.classList.remove('open');
        });
    });
};

document.addEventListener('DOMContentLoaded', initModals);

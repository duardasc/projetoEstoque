document.addEventListener('DOMContentLoaded', function () {
    const openBtn = document.getElementById('open-btn');
    const closeBtn = document.getElementById('close-btn');
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');

    openBtn.addEventListener('click', function () {
        sidebar.classList.add('open');
        content.style.marginLeft = '250px'; // Ajusta o conteúdo quando a barra lateral está aberta
    });

    closeBtn.addEventListener('click', function () {
        sidebar.classList.remove('open');
        content.style.marginLeft = '0'; // Ajusta o conteúdo quando a barra lateral está fechada
    });
});

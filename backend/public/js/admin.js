// Sidebar toggle on small screens
(function () {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var burger = document.getElementById('sidebarToggle');
    if (!sidebar || !burger) return;
    var toggle = function (open) {
        sidebar.classList.toggle('open', open);
        if (backdrop) backdrop.classList.toggle('show', open);
        document.body.style.overflow = open ? 'hidden' : '';
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    burger.addEventListener('click', function () { toggle(!sidebar.classList.contains('open')); });
    if (backdrop) backdrop.addEventListener('click', function () { toggle(false); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) toggle(false);
    });
})();

// Theme toggle, the choice is kept in localStorage
(function () {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var icon = btn.querySelector('i');
    var paint = function () {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        icon.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
    };
    paint();
    btn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('admin-theme', next); } catch (e) {}
        paint();
        if (window.redrawChart) window.redrawChart();
    });
})();

// Image upload area with live preview
(function () {
    var zone = document.getElementById('dropzone');
    if (!zone) return;
    var input = zone.querySelector('input[type=file]');
    var preview = document.getElementById('imagePreview');
    var show = function (file) {
        if (!file || !/^image\//.test(file.type)) return;
        var reader = new FileReader();
        reader.onload = function (e) { preview.src = e.target.result; preview.classList.remove('d-none'); };
        reader.readAsDataURL(file);
    };
    input.addEventListener('change', function () { show(input.files[0]); });
    ['dragenter', 'dragover'].forEach(function (n) {
        zone.addEventListener(n, function (e) { e.preventDefault(); zone.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (n) {
        zone.addEventListener(n, function () { zone.classList.remove('drag'); });
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(input.files[0]); }
    });
})();

// Success messages close on their own
document.querySelectorAll('.toast-wrap .alert').forEach(function (el) {
    setTimeout(function () {
        if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(el).close();
    }, 5000);
});

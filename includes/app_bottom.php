        </div>
    </div>
</div>
<script>
(function () {
    const toggle = document.getElementById('menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!toggle || !sidebar || !backdrop) return;

    function isMobile() {
        return window.innerWidth <= 960;
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('open');
        backdrop.classList.remove('open');
    }

    toggle.addEventListener('click', () => {
        if (isMobile()) {
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('open');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    });
    backdrop.addEventListener('click', closeMobileSidebar);
})();
</script>
</body>
</html>

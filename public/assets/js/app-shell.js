(function () {
    document.querySelectorAll('.mobile-menu-button').forEach(function (button) {
        var target = document.getElementById(button.getAttribute('aria-controls'));
        if (!target) return;
        button.addEventListener('click', function () {
            var open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!open));
            target.classList.toggle('is-open', !open);
        });
    });
})();

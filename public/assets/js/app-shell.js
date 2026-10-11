(function () {
    document.documentElement.classList.add('has-js');

    document.querySelectorAll('.mobile-menu-button').forEach(function (button) {
        var target = document.getElementById(button.getAttribute('aria-controls'));
        if (!target) return;
        var label = button.querySelector('.sr-only');

        button.addEventListener('click', function () {
            var open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!open));
            target.classList.toggle('is-open', !open);
            if (label) label.textContent = open ? 'Open primary navigation' : 'Close primary navigation';
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || button.getAttribute('aria-expanded') !== 'true') return;
            button.setAttribute('aria-expanded', 'false');
            target.classList.remove('is-open');
            if (label) label.textContent = 'Open primary navigation';
            button.focus();
        });
    });
})();

document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-toggle-sidebar]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('is-sidebar-open');
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            var message = el.getAttribute('data-confirm') || '계속 진행하시겠습니까?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-open-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-open-modal'));
            if (target) {
                target.classList.add('is-open');
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('.modal');
            if (modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('[data-dismiss-popup]').forEach(function (button) {
        button.addEventListener('click', function () {
            var popupId = button.getAttribute('data-dismiss-popup');
            if (popupId) {
                try {
                    localStorage.setItem('dismiss_' + popupId, '1');
                } catch (e) {}
            }
            var modal = document.getElementById(popupId);
            if (modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('.notice-popup').forEach(function (modal) {
        try {
            if (localStorage.getItem('dismiss_' + modal.id) === '1') {
                return;
            }
        } catch (e) {}
        modal.classList.add('is-open');
    });

    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            var cls = master.getAttribute('data-check-all');
            document.querySelectorAll('.' + cls).forEach(function (item) {
                item.checked = master.checked;
            });
        });
    });
});

document.querySelectorAll('[data-accordion-button]').forEach(function (button) {
    button.addEventListener('click', function () {
        var item = button.closest('[data-accordion-item]');
        if (!item) return;
        var willOpen = !item.classList.contains('is-open');
        if (item.parentElement) {
            item.parentElement.querySelectorAll('[data-accordion-item]').forEach(function (sibling) {
                sibling.classList.remove('is-open');
                var siblingButton = sibling.querySelector('[data-accordion-button]');
                if (siblingButton) siblingButton.setAttribute('aria-expanded', 'false');
            });
        }
        if (willOpen) {
            item.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
        } else {
            button.setAttribute('aria-expanded', 'false');
        }
    });
});

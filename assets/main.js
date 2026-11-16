// Employee Management & Payroll System - Phase 1
// Small vanilla JavaScript helpers. The application works without JavaScript.

(function () {
    'use strict';

    // Toggle password visibility on inputs paired with a .toggle-password button.
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }

            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        });
    });

    // Confirm destructive form submissions.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm');
            if (message && !confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Running clock on the time clock kiosk (HH:MM:SS AM/PM, computer local time).
    var clockEl = document.getElementById('clock');
    if (clockEl) {
        function updateClock() {
            var now = new Date();
            clockEl.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
        updateClock();
        setInterval(updateClock, 1000);
    }
})();
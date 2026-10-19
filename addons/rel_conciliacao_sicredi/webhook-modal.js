(function () {
    'use strict';
    var dialog = document.getElementById('sicredi-webhook-dialog');
    var content = document.getElementById('sicredi-webhook-content');
    var heading = document.getElementById('sicredi-webhook-heading');
    var root = document.documentElement;
    var previousOverflow;
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-webhook-template]');
        if (!trigger) return;
        var template = document.getElementById(trigger.dataset.webhookTemplate);
        if (!template || dialog.open) return;
        content.replaceChildren(template.content.cloneNode(true));
        heading.textContent = 'Webhook Sicredi - titulo ' + trigger.dataset.titulo;
        previousOverflow = root.style.overflowY;
        dialog.showModal();
        dialog.scrollTop = 0;
        root.style.overflowY = 'hidden';
    });
    document.getElementById('sicredi-webhook-close').addEventListener('click', function () {
        dialog.close();
    });
    dialog.addEventListener('close', function () {
        root.style.overflowY = previousOverflow;
        content.replaceChildren();
    });
    dialog.addEventListener('click', function (event) {
        if (event.target !== dialog) return;
        var bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right ||
            event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
})();

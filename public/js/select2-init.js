/*
 * Select2 global: semua <select> di aplikasi otomatis menjadi dropdown yang bisa diketik/dicari.
 *
 * - Diterapkan juga pada select yang muncul belakangan (baris dinamis Alpine, konten navigasi AJAX)
 *   lewat MutationObserver.
 * - Opsi pertama dengan value "" dipakai sebagai placeholder; select tidak wajib bisa dikosongkan (×).
 * - Perubahan dari Select2 dikirim ulang sebagai event native "change" agar x-model Alpine ikut terupdate.
 * - Kecualikan select tertentu dengan atribut data-no-select2.
 */
(function ($) {
    'use strict';

    if (!$.fn.select2) return;

    var SELECTOR = 'select:not([data-no-select2]):not(.select2-hidden-accessible)';

    function init(el) {
        var $el = $(el);
        if ($el.hasClass('select2-hidden-accessible') || $el.closest('template').length) return;

        var $empty = $el.find('option[value=""]').first();
        var $modal = $el.closest('[role="dialog"], .modal, [data-select2-parent]');

        $el.select2({
            width: '100%',
            language: 'id',
            minimumResultsForSearch: 0,
            placeholder: $empty.length ? ($.trim($empty.text()) || 'Pilih...') : undefined,
            allowClear: $empty.length > 0 && !el.required && !el.multiple,
            dropdownParent: $modal.length ? $modal.first() : $(document.body),
        });

        // Sinkronkan tampilan dengan nilai yang di-set Alpine (x-model) saat inisialisasi
        setTimeout(function () { $el.trigger('change.select2'); }, 0);
    }

    function scan(root) {
        $(root).find(SELECTOR).addBack(SELECTOR).each(function () { init(this); });
    }

    // Select2 memicu 'change' versi jQuery saja; teruskan sebagai event native untuk Alpine x-model.
    $(document).on('select2:select select2:unselect select2:clear', 'select', function () {
        this.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Fokus langsung ke kotak pencarian saat dropdown dibuka
    $(document).on('select2:open', function () {
        setTimeout(function () {
            var search = document.querySelector('.select2-container--open .select2-search__field');
            if (search) search.focus();
        }, 0);
    });

    $(function () {
        // Tunggu Alpine selesai inisialisasi agar nilai awal x-model sudah terpasang
        var start = function () {
            scan(document.body);
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) setTimeout(function () { scan(node); }, 0);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        };
        if (window.Alpine && window.Alpine.version) start();
        else document.addEventListener('alpine:initialized', start, { once: true });
    });
})(jQuery);

/*
 * Modal form global.
 *
 * Pakai pada link/tombol dengan atribut data-modal:
 *   <a href="/masters/departments/create" data-modal>        isi modal diambil dari halaman tujuan (AJAX)
 *   <button type="button" data-modal="#password-modal">      isi modal dari <template id="password-modal"> di halaman
 *
 * Halaman tujuan menandai elemen yang dipakai sebagai isi modal dengan [data-modal-content]
 * (biasanya <form data-ajax-form>). Atribut opsional pada elemen tersebut:
 *   data-modal-title="Tambah Departemen"   judul modal
 *   data-modal-size="sm|md|lg|xl"          lebar modal (default md)
 * Di dalam isi modal: .modal-body = area yang bisa di-scroll, .modal-footer = tombol aksi,
 * [data-modal-close] = tombol tutup (pada halaman penuh tetap berfungsi sebagai link biasa).
 *
 * Halaman create/edit tetap bisa dibuka langsung (tanpa modal) sebagai fallback.
 * Setelah form [data-ajax-form] di dalam modal sukses disimpan, ajax-form.js menutup modal
 * lalu memuat ulang halaman aktif.
 */
(function ($) {
    'use strict';

    var SIZES = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' };
    var $modal = $();
    var request = null;
    var lastFocus = null;

    function build() {
        if ($modal.length) return $modal;
        $modal = $(
            '<div id="app-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-space-md" role="dialog" aria-modal="true" aria-labelledby="app-modal-title">' +
            '  <div class="app-modal-panel bg-surface-card rounded-lg shadow-xl w-full flex flex-col max-h-[calc(100vh-2rem)]">' +
            '    <div class="card-header shrink-0">' +
            '      <h3 id="app-modal-title" class="card-title"></h3>' +
            '      <button type="button" class="p-space-xs -mr-space-xs text-on-surface-variant hover:text-on-surface hover:bg-surface-subtle rounded" data-modal-close aria-label="Tutup">' +
            '        <span class="material-symbols-outlined !text-[20px]">close</span>' +
            '      </button>' +
            '    </div>' +
            '    <div class="app-modal-content flex flex-col min-h-0 flex-1"></div>' +
            '  </div>' +
            '</div>'
        ).appendTo(document.body);
        return $modal;
    }

    function loading() {
        return '<div class="flex items-center justify-center gap-space-sm py-space-xl text-body-sm text-on-surface-variant">' +
            '<span class="material-symbols-outlined animate-spin">progress_activity</span> Memuat formulir...</div>';
    }

    function show(title, size) {
        build();
        lastFocus = document.activeElement;
        $modal.find('#app-modal-title').text(title || '');
        setSize(size);
        $modal.find('.app-modal-content').html(loading());
        $modal.removeClass('hidden').addClass('flex');
        $('html').addClass('overflow-hidden');
    }

    function setSize(size) {
        var $panel = $modal.find('.app-modal-panel');
        $.each(SIZES, function (k, cls) { $panel.removeClass(cls); });
        $panel.addClass(SIZES[size] || SIZES.md);
    }

    /** Pasang elemen isi modal. jQuery .html()/.append() ikut menjalankan <script> inline di dalamnya. */
    function fill($content) {
        var title = $content.attr('data-modal-title');
        if (title) $modal.find('#app-modal-title').text(title);
        setSize($content.attr('data-modal-size'));
        $content.addClass('flex flex-col min-h-0 flex-1');
        $modal.find('.app-modal-content').empty().append($content);
        setTimeout(function () {
            var $first = $content.find(':input:visible:enabled').not('[type=hidden], button, [data-modal-close]').first();
            if ($first.length) $first.trigger('focus');
        }, 50);
    }

    function close() {
        if (!$modal.length || $modal.hasClass('hidden')) return;
        if (request) { request.abort(); request = null; }
        if ($.fn.select2) $modal.find('.select2-hidden-accessible').select2('close');
        $modal.addClass('hidden').removeClass('flex');
        $modal.find('.app-modal-content').empty();
        $('html').removeClass('overflow-hidden');
        if (lastFocus && document.body.contains(lastFocus)) lastFocus.focus();
    }

    function openRemote(url, title, size) {
        show(title, size);
        if (request) request.abort();
        request = $.ajax({ url: url, method: 'GET', dataType: 'html', cache: false, headers: { 'X-AJAX-NAV': '1' } })
            .done(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var content = doc.querySelector('[data-modal-content]');
                if (!content) { close(); location.href = url; return; } // mis. sesi habis → halaman login
                var token = doc.querySelector('meta[name="csrf-token"]');
                if (token) $('meta[name="csrf-token"]').attr('content', token.content);
                fill($(content.outerHTML));
            })
            .fail(function (xhr, status) {
                if (status === 'abort') return;
                close();
                if (xhr.status === 401 || xhr.status === 419) { location.href = url; return; }
                var msg = xhr.status === 403 ? 'Anda tidak memiliki hak akses untuk aksi ini.' : 'Formulir gagal dimuat (' + xhr.status + '). Silakan coba lagi.';
                window.AppAlert ? AppAlert.error(msg) : alert(msg);
            })
            .always(function () { request = null; });
    }

    function openTemplate(selector, title, size) {
        var tpl = document.querySelector(selector);
        if (!tpl) return;
        show(title, size);
        var $wrap = $('<div></div>').append(tpl.content ? document.importNode(tpl.content, true) : $(tpl).html());
        var $content = $wrap.find('[data-modal-content]').first();
        fill($content.length ? $content : $wrap);
    }

    window.AppModal = {
        open: function (urlOrSelector, opts) {
            opts = opts || {};
            if (urlOrSelector.charAt(0) === '#') openTemplate(urlOrSelector, opts.title, opts.size);
            else openRemote(urlOrSelector, opts.title, opts.size);
        },
        close: close,
        isOpen: function () { return $modal.length > 0 && !$modal.hasClass('hidden'); },
        contains: function (el) { return $modal.length > 0 && $.contains($modal[0], el); },
    };

    // Dipasang sebelum ajax-nav.js agar klik tidak diteruskan ke navigasi AJAX biasa
    $(document).on('click', '[data-modal]', function (e) {
        if (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return; // buka di tab baru → halaman penuh
        e.preventDefault();
        e.stopImmediatePropagation();
        var $el = $(this);
        var target = $el.attr('data-modal') || $el.attr('href');
        var label = $.trim($el.clone().find('.material-symbols-outlined').remove().end().text());
        AppModal.open(target, { title: $el.attr('data-modal-title') || label, size: $el.attr('data-modal-size') });
    });

    $(document).on('click', '#app-modal [data-modal-close]', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        close();
    });

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape' || !AppModal.isOpen()) return;
        // Esc milik dropdown Select2 / dialog SweetAlert, bukan untuk menutup modal
        if ($(e.target).closest('.select2-container').length || $('.select2-container--open').length || $('.swal2-container').length) return;
        close();
    });

    window.addEventListener('popstate', close);
})(jQuery);

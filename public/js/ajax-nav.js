/*
 * Navigasi AJAX (jQuery) untuk seluruh halaman aplikasi.
 *
 * Link internal & submit form ditangani via AJAX: server tetap merender halaman Blade utuh,
 * lalu hanya bagian yang berubah yang ditukar (#app-content dan menu sidebar) tanpa memuat
 * ulang CSS/JS/font. Riwayat browser (back/forward) tetap berfungsi lewat History API.
 *
 * Kecualikan elemen dengan atribut data-no-ajax (pada link, form, atau pembungkusnya).
 * Halaman dapat mendaftarkan pembersihan (mis. clearInterval) lewat AppNav.onLeave(fn).
 *
 * Mode region: link/form GET di dalam elemen [data-ajax-region] (ber-id) yang menuju halaman
 * yang sama (path sama, query berbeda — tab status, filter, pagination) hanya memperbarui
 * elemen tersebut, tanpa scroll ke atas. Form dengan [data-auto-submit] dikirim otomatis saat
 * select/tanggal berubah dan saat mengetik di kolom pencarian (debounce).
 */
(function ($) {
    'use strict';

    var SKIP_PATH = /\/(attachments|logout)(\/|$)|\/export(\?|$)/;
    var leaveHandlers = [];
    var current = null;
    var $progress = $(); // diisi saat DOM siap (script dimuat di <head>)
    $(function () { $progress = $('#nav-progress'); });
    var progressTimer = null;

    window.AppNav = {
        onLeave: function (fn) { leaveHandlers.push(fn); },
        visit: function (url) { visit(url, { method: 'GET' }); },
        reload: function () { visit(location.href, { method: 'GET', replace: true, keepScroll: true }); },
    };

    function sameOrigin(url) {
        var a = document.createElement('a');
        a.href = url;
        return a.origin === location.origin;
    }

    function shouldSkip(el, url) {
        return !url || !sameOrigin(url) || SKIP_PATH.test(new URL(url, location.href).pathname + new URL(url, location.href).search)
            || $(el).closest('[data-no-ajax]').length > 0;
    }

    // ---------------------------------------------------------------- progress bar
    function startProgress() {
        clearInterval(progressTimer);
        var width = 10;
        $progress.css({ width: width + '%', opacity: 1 });
        progressTimer = setInterval(function () {
            width = Math.min(90, width + (90 - width) * 0.1);
            $progress.css('width', width + '%');
        }, 200);
    }

    function doneProgress() {
        clearInterval(progressTimer);
        $progress.css('width', '100%');
        setTimeout(function () { $progress.css({ opacity: 0 }); }, 200);
        setTimeout(function () { $progress.css({ width: 0 }); }, 500);
    }

    // ---------------------------------------------------------------- request & swap
    function visit(url, opts) {
        opts = opts || {};
        if (current) current.abort();

        var xhrRef;
        var ajax = {
            url: url,
            method: opts.method || 'GET',
            dataType: 'html',
            cache: false,
            headers: { 'X-AJAX-NAV': '1' },
            xhr: function () { xhrRef = new window.XMLHttpRequest(); return xhrRef; },
        };
        if (opts.data !== undefined) {
            ajax.data = opts.data;
            ajax.processData = false;
            ajax.contentType = false;
        }

        startProgress();
        current = $.ajax(ajax)
            .done(function (html, status, jq) {
                var finalUrl = (xhrRef && xhrRef.responseURL) || url;
                var type = jq.getResponseHeader('Content-Type') || '';
                if (type.indexOf('text/html') === -1) { location.href = finalUrl; return; }
                var swapped = opts.region ? (swapRegion(html, opts.region) || swap(html)) : swap(html);
                if (!swapped) { location.href = finalUrl; return; } // mis. halaman login / error penuh

                var method = (opts.method || 'GET').toUpperCase();
                if (opts.replace || opts.pop) {
                    history.replaceState({ ajaxNav: true }, '', finalUrl);
                } else if (method !== 'GET' && finalUrl === location.href) {
                    history.replaceState({ ajaxNav: true }, '', finalUrl);
                } else {
                    history.pushState({ ajaxNav: true }, '', finalUrl);
                }
                if (!opts.keepScroll && !opts.region) window.scrollTo(0, 0);
            })
            .fail(function (jq, status) {
                if (status === 'abort') return;
                // Tampilkan halaman error dari server (mis. 403/404/500) bila berbentuk layout aplikasi,
                // selain itu (419 sesi habis, dsb.) lakukan navigasi penuh.
                if (jq.responseText && jq.status !== 419 && swap(jq.responseText)) {
                    if (!opts.pop) history.pushState({ ajaxNav: true }, '', url);
                    window.scrollTo(0, 0);
                    return;
                }
                location.href = url;
            })
            .always(function () {
                current = null;
                doneProgress();
                if (opts.region) $('#' + opts.region).removeClass('opacity-60 pointer-events-none');
            });
        if (opts.region) $('#' + opts.region).addClass('opacity-60 pointer-events-none transition-opacity');
    }

    /** Tukar hanya isi satu region; fokus & posisi kursor input dipertahankan. */
    function swapRegion(html, id) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById(id);
        var $region = $('#' + id);
        if (!fresh || !$region.length) return false;

        var active = document.activeElement;
        var focusName = active && $region[0].contains(active) ? active.getAttribute('name') : null;
        var caret = focusName && typeof active.selectionStart === 'number' ? active.selectionStart : null;

        document.title = doc.title;
        $region.html(fresh.innerHTML);

        if (focusName) {
            var el = $region.find('[name="' + focusName + '"]')[0];
            if (el) {
                el.focus();
                if (caret !== null) { try { el.setSelectionRange(caret, caret); } catch (e) {} }
            }
        }
        return true;
    }

    /** Region tujuan bila elemen berada di [data-ajax-region] dan URL-nya halaman yang sama. */
    function regionFor(el, url) {
        var region = $(el).closest('[data-ajax-region]').attr('id');
        return region && new URL(url, location.href).pathname === location.pathname ? region : null;
    }

    /** Tukar isi halaman dengan dokumen baru. false bila dokumen bukan layout aplikasi. */
    function swap(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var content = doc.getElementById('app-content');
        if (!content) return false;

        // Tutup dropdown Select2 yang masih terbuka
        if ($.fn.select2) $('.select2-hidden-accessible').select2('close');

        // Jalankan pembersihan halaman lama
        leaveHandlers.splice(0).forEach(function (fn) { try { fn(); } catch (e) { console.error(e); } });

        document.title = doc.title;
        var token = doc.querySelector('meta[name="csrf-token"]');
        if (token) {
            $('meta[name="csrf-token"]').attr('content', token.content);
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token.content } });
        }

        var nav = doc.querySelector('#app-sidebar nav');
        if (nav) $('#app-sidebar nav').replaceWith(nav.outerHTML);

        // .html() milik jQuery ikut mengeksekusi <script> inline (fungsi Alpine x-data halaman)
        $('#app-content').html(content.innerHTML);

        // Tutup drawer sidebar mobile setelah pindah halaman
        if (window.Alpine) {
            try { window.Alpine.$data(document.body).sidebar = false; } catch (e) {}
        }
        return true;
    }

    // ---------------------------------------------------------------- event binding
    $(document).on('click', 'a[href]', function (e) {
        var a = this;
        var href = a.getAttribute('href');
        if (e.isDefaultPrevented() || e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (shouldSkip(a, a.href)) return;
        // Link ke anchor di halaman yang sama
        if (a.pathname === location.pathname && a.search === location.search && a.hash) return;

        e.preventDefault();
        visit(a.href, { method: 'GET', region: regionFor(a, a.href) });
    });

    $(document).on('submit', 'form', function (e) {
        if (e.isDefaultPrevented()) return; // dibatalkan onsubmit="return confirm()" / validasi Alpine
        var form = this;
        var action = form.getAttribute('action') || location.href;
        var url = new URL(action, location.href).href;
        if ((form.target && form.target !== '_self') || shouldSkip(form, url)) return;

        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        var submitter = e.originalEvent && e.originalEvent.submitter;
        e.preventDefault();

        if (method === 'GET') {
            var params = $(form).serializeArray();
            if (submitter && submitter.name) params.push({ name: submitter.name, value: submitter.value });
            var target = new URL(url);
            target.search = $.param(params.filter(function (p) { return p.value !== ''; }));
            visit(target.href, { method: 'GET', region: regionFor(form, target.href) });
            return;
        }

        var data = new FormData(form);
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);
        $(form).find('[type=submit]').prop('disabled', true);
        visit(url, { method: 'POST', data: data });
    });

    // Form filter otomatis: kirim saat select/tanggal berubah, dan saat mengetik (debounce 400 ms)
    var typingTimer = null;
    function autoSubmit(form) {
        if (form.requestSubmit) form.requestSubmit(); else $(form).trigger('submit');
    }
    $(document).on('change', 'form[data-auto-submit] select, form[data-auto-submit] input[type=date]', function (e) {
        // Select2 memicu 'change' jQuery + event native (untuk Alpine); proses yang native saja
        if (!e.originalEvent && $(this).hasClass('select2-hidden-accessible')) return;
        autoSubmit(this.form);
    });
    $(document).on('input', 'form[data-auto-submit] input[type=search]', function () {
        var form = this.form;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function () { autoSubmit(form); }, 400);
    });

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.ajaxNav) visit(location.href, { method: 'GET', pop: true, keepScroll: true });
    });

    // Tandai halaman awal agar tombol back kembali lewat AJAX
    history.replaceState({ ajaxNav: true }, '', location.href);
})(jQuery);

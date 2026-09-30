/*
 * Form CRUD via AJAX + notifikasi SweetAlert2.
 *
 * Pakai pada <form data-ajax-form>. Form dikirim sebagai AJAX (Accept: application/json):
 *   - Sukses  → server membalas {status, message, redirect, notice?}; SweetAlert ditampilkan lalu
 *               halaman berpindah ke `redirect` lewat navigasi AJAX (tanpa reload penuh).
 *   - 422     → error validasi ditampilkan di bawah tiap field + ringkasan di SweetAlert;
 *               pelanggaran aturan bisnis ditampilkan sebagai pesan error.
 *   - 403/409/419/500 → pesan yang sesuai.
 * Atribut opsional:
 *   data-confirm="Judul"            konfirmasi SweetAlert sebelum kirim (mis. hapus)
 *   data-confirm-text="Keterangan"  teks tambahan dialog konfirmasi
 *   data-confirm-button="Ya, hapus" label tombol konfirmasi
 * Form di dalam modal (ajax-modal.js): setelah sukses modal ditutup dan halaman aktif dimuat ulang
 * (redirect dari server diabaikan).
 */
(function ($) {
    'use strict';

    var COLORS = { confirm: '#131b2e', cancel: '#94a3b8', danger: '#DC2626' };

    var AppAlert = window.AppAlert = {
        fire: function (opts) {
            if (!window.Swal) { alert((opts.title || '') + (opts.text ? '\n' + opts.text : '')); return Promise.resolve({ isConfirmed: true }); }
            return Swal.fire($.extend({
                confirmButtonColor: COLORS.confirm,
                cancelButtonColor: COLORS.cancel,
                confirmButtonText: 'OK',
                cancelButtonText: 'Batal',
                reverseButtons: true,
            }, opts));
        },
        success: function (message, title) {
            return AppAlert.fire({ icon: 'success', title: title || 'Berhasil', text: message, timer: 1800, timerProgressBar: true, showConfirmButton: false });
        },
        error: function (message, title, html) {
            return AppAlert.fire({ icon: 'error', title: title || 'Gagal', text: html ? undefined : message, html: html });
        },
        notice: function (status, message) {
            return AppAlert.fire({ icon: status === 'warning' ? 'warning' : 'info', title: status === 'warning' ? 'Perhatian' : 'Informasi', text: message });
        },
        confirm: function (title, text, button, danger) {
            return AppAlert.fire({
                icon: 'warning', title: title, text: text, showCancelButton: true,
                confirmButtonText: button || 'Ya, lanjutkan',
                confirmButtonColor: danger ? COLORS.danger : COLORS.confirm,
                focusCancel: true,
            });
        },
    };

    // ---------------------------------------------------------------- error per field
    function clearErrors($form) {
        $form.find('.js-ajax-error').remove();
        $form.find('.js-ajax-invalid').removeClass('js-ajax-invalid !border-error ring-1 ring-error');
    }

    /** "items.0.name" → "items[0][name]" */
    function toInputName(key) {
        var parts = key.split('.');
        return parts[0] + parts.slice(1).map(function (p) { return '[' + p + ']'; }).join('');
    }

    function findField($form, key) {
        var name = toInputName(key);
        var base = key.split('.')[0];
        var $field = $form.find('[name="' + name + '"]');
        if (!$field.length) $field = $form.find('[name="' + name + '[]"]');
        if (!$field.length) $field = $form.find('[name="' + base + '[]"], [name="' + base + '"]');
        return $field.first();
    }

    function showErrors($form, errors) {
        var placedGroups = {};
        $.each(errors, function (key, messages) {
            var $field = findField($form, key);
            var message = $('<p class="form-error js-ajax-error"></p>').text(messages[0]);
            if (!$field.length) {
                $form.prepend(message.addClass('mb-space-sm'));
                return;
            }
            // Checkbox/array: satu pesan per grup
            var group = key.split('.')[0];
            if ($field.is(':checkbox, :radio') || /\[\]$/.test($field.attr('name') || '')) {
                if (placedGroups[group]) return;
                placedGroups[group] = true;
            }
            var $select2 = $field.next('.select2');
            var $target = $select2.length ? $select2 : $field;
            if (!$field.is(':checkbox, :radio')) {
                ($select2.length ? $select2.find('.select2-selection') : $field).addClass('js-ajax-invalid !border-error ring-1 ring-error');
            }
            var $wrap = $field.is(':checkbox, :radio') ? $field.closest('.card-body, fieldset, .grid').first() : $target;
            $wrap.length ? $wrap.after(message) : $target.after(message);
        });

        var $first = $form.find('.js-ajax-error').first();
        if (!$first.length) return;
        var $scroller = $form.closest('#app-modal').find('.modal-body').first();
        if ($scroller.length) {
            $scroller.animate({ scrollTop: Math.max(0, $scroller.scrollTop() + $first.offset().top - $scroller.offset().top - 80) }, 250);
        } else {
            $('html, body').animate({ scrollTop: Math.max(0, $first.offset().top - 140) }, 250);
        }
    }

    function errorSummary(errors) {
        var items = [];
        $.each(errors, function (key, messages) { items.push(messages[0]); });
        var unique = items.filter(function (m, i) { return items.indexOf(m) === i; }).slice(0, 5);
        var $ul = $('<ul style="text-align:left;margin:0 auto;max-width:360px;list-style:disc;padding-left:1.25rem"></ul>');
        unique.forEach(function (m) { $ul.append($('<li></li>').text(m)); });
        return $ul.prop('outerHTML');
    }

    // ---------------------------------------------------------------- submit
    function setBusy($form, $submitter, busy) {
        var $buttons = $form.find('[type=submit]');
        $buttons.prop('disabled', busy);
        if (!$submitter || !$submitter.length) return;
        if (busy) {
            $submitter.data('label', $submitter.html());
            $submitter.html('<span class="material-symbols-outlined !text-[18px] animate-spin">progress_activity</span> Memproses...');
        } else if ($submitter.data('label')) {
            $submitter.html($submitter.data('label'));
        }
    }

    function send(form, submitter) {
        var $form = $(form);
        var $submitter = submitter ? $(submitter) : $form.find('[type=submit]').first();
        var data = new FormData(form);
        if (submitter && submitter.name) data.append(submitter.name, submitter.value);

        clearErrors($form);
        setBusy($form, $submitter, true);

        $.ajax({
            url: form.getAttribute('action') || location.href,
            method: 'POST', // _method (PUT/DELETE) dikirim di FormData
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { Accept: 'application/json' },
        })
            .done(function (res) {
                setBusy($form, $submitter, false);
                if (res && res.status && res.status !== 'success') {
                    AppAlert.notice(res.status, res.message);
                    return;
                }
                // Form di dalam modal: tutup modal lalu segarkan halaman aktif (tetap di halaman yang sama)
                var inModal = window.AppModal && AppModal.contains(form);
                if (inModal) AppModal.close();
                AppAlert.success(res && res.message ? res.message : 'Data berhasil disimpan.').then(function () {
                    var next = function () {
                        if (inModal) {
                            if (window.AppNav) AppNav.reload(); else location.reload();
                        } else if (res && res.redirect) {
                            window.AppNav ? AppNav.visit(res.redirect) : (location.href = res.redirect);
                        } else if (window.AppNav) {
                            AppNav.reload();
                        }
                    };
                    if (res && res.notice) AppAlert.notice(res.notice.status, res.notice.message).then(next);
                    else next();
                });
            })
            .fail(function (xhr) {
                setBusy($form, $submitter, false);
                var res = xhr.responseJSON || {};
                if (xhr.status === 422 && res.errors) {
                    showErrors($form, res.errors);
                    AppAlert.error(null, 'Periksa kembali isian', errorSummary(res.errors));
                } else if (xhr.status === 419) {
                    AppAlert.error('Sesi Anda telah berakhir. Halaman akan dimuat ulang.', 'Sesi habis').then(function () { location.reload(); });
                } else if (xhr.status === 401) {
                    location.href = '/login';
                } else if (xhr.status === 403) {
                    AppAlert.error(res.message && res.message !== 'This action is unauthorized.' ? res.message : 'Anda tidak memiliki hak akses untuk aksi ini.', 'Akses ditolak');
                } else if (res.message) {
                    AppAlert.error(res.message);
                } else {
                    AppAlert.error('Terjadi kesalahan pada server (' + xhr.status + '). Silakan coba lagi.');
                }
            });
    }

    $(document).on('submit', 'form[data-ajax-form]', function (e) {
        if (e.isDefaultPrevented()) return; // validasi Alpine/confirm lain membatalkan
        e.preventDefault();
        e.stopImmediatePropagation(); // jangan diproses navigasi AJAX biasa

        var form = this;
        var submitter = e.originalEvent && e.originalEvent.submitter;
        var title = form.getAttribute('data-confirm');
        if (!title) { send(form, submitter); return; }

        AppAlert.confirm(title, form.getAttribute('data-confirm-text') || '', form.getAttribute('data-confirm-button'), /hapus|batal|tolak|eksekusi|tutup/i.test(title))
            .then(function (r) { if (r.isConfirmed) send(form, submitter); });
    });

    // Hapus pesan error field begitu user memperbaiki isian
    $(document).on('input change', 'form[data-ajax-form] .js-ajax-invalid, form[data-ajax-form] :input', function () {
        var $f = $(this);
        $f.removeClass('js-ajax-invalid !border-error ring-1 ring-error');
        $f.nextAll('.js-ajax-error').first().remove();
        $f.next('.select2').find('.select2-selection').removeClass('js-ajax-invalid !border-error ring-1 ring-error');
        $f.next('.select2').nextAll('.js-ajax-error').first().remove();
    });
})(jQuery);

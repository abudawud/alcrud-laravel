<div>
    <x-adminlte-modal style="z-index: 1062;" id="modalCRU" theme="primary" title="Loading...">
        <input type="hidden" id="modalCRU-local-callback" value=""/>
        <div>
            <div class="content-loading">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                        aria-valuenow="90" aria-valuemin="0" aria-valuemax="100" style="width: 90%"></div>
                </div>
            </div>
            <div class="content-main">
                <div style="display: none" class="content-error rounded bg-danger py-1 px-2 mb-2">
                </div>
                <div class="content-html"></div>
            </div>
        </div>
        <x-slot name="footerSlot">
        </x-slot>
    </x-adminlte-modal>

    <x-adminlte-modal style="z-index: 1062;" id="modalD" theme="danger" title="Hapus Data">
        <div id="modal-body">
            <div style="display: none" class="error-hint rounded bg-danger py-1 px-2 mb-2">
            </div>
            <form action="" method="post">
                {!! Form::token() !!}
                {!! Form::hidden('_method', 'DELETE') !!}
            </form>
            <span class="modal-message">Apakah anda yakin ingin menghapus data ini ?</span>
        </div>
        <x-slot name="footerSlot">
            <x-adminlte-button type="submit" theme="danger" label="Ya" />
        </x-slot>
    </x-adminlte-modal>
</div>

@push('js')
    <script>
        $(document).ready(function() {
            $('body').on('click', '.modal-remote', function(e) {
                e.preventDefault();
                let data = $(this).data();
                const dataCallback = data.callback;
                if (!!dataCallback && typeof window[dataCallback] === 'function') {
                    data['extra'] = window[dataCallback](data);
                }
                doXHR($(this).attr('href'), $(this).data());
            })

            function resetSize() {
                $('#modalCRU > div.modal-dialog').removeClass('modal-xl modal-lg');
            }

            function doXHR(url, data = []) {
                $.ajax({
                    url: url,
                    data: data,
                    dataType: 'json',
                    beforeSend: function() {
                        resetSize();
                        $('#modalCRU-local-callback').val("");
                        $('#modalCRU').find('.modal-title').html("Loading...");
                        $('#modalCRU').find('.content-loading').show();
                        $('#modalCRU').find('.content-html,.content-error,.modal-footer').empty();
                        $('#modalCRU').find('.modal-footer,.content-error').hide();
                        $('#modalCRU').find('.content-main').hide();
                        $('#modalCRU').modal('show');
                    },
                    success: function(data) {
                        if (!!data.size) {
                            $('#modalCRU > div.modal-dialog').addClass(`modal-${data.size}`);
                        }
                        $('#modalCRU-local-callback').val(data.local_callback);
                        $('#modalCRU').find('.modal-title').html(data.title);
                        $('#modalCRU').find('.content-loading').slideUp(200, function() {
                            $('#modalCRU').find('.content-html').html(data.content).slideDown();
                            if (data.footer != undefined) {
                                $('#modalCRU').find('.modal-footer').html(data.footer).show();
                            }
                            $('#modalCRU').find('.content-main').slideDown();
                        });
                    },
                    error: function(xhr) {
                        if (xhr.status == 401) {
                            window.location.reload();
                        } else if (xhr.status == 422) {
                            const json = xhr.responseJSON;
                            $('#modalCRU').find('.modal-title').html(`Error ${xhr.status}`);
                            $('#modalCRU div.content-error').append(`<b>Data tidak valid:</b>`);
                            $('#modalCRU div.content-error').append('<ul></ul>');
                            Object.values(json.errors)
                                .forEach(function(error) {
                                    error.forEach(function(item) {
                                        $('#modalCRU div.content-error > ul')
                                            .append(`<li>${item}</li>`)
                                    })
                                })
                        } else {
                            $('#modalCRU').find('.modal-title').html(`Error ${xhr.status}`);
                            $('#modalCRU').find('.content-error').html(
                                `<b>${xhr.statusText} (${xhr.status})</b><p>${xhr.responseText}</p>`
                            );
                        }
                        $('#modalCRU').find('.content-loading').slideUp(200, function() {
                            $('#modalCRU div.content-error').show();
                            $('#modalCRU').find(".content-main").slideDown();
                        });
                    }
                });
            }

            $('#btn-reset').on('click', function() {
                const datatableId = $(this).data('datatable-id') ?? '#datatable';
                $(`${datatableId} thead tr.filter th input`).val('').change();
                $(datatableId).DataTable().search('').draw();
            });

            $('#modalCRU').on('click', 'button[type=submit]', function() {
                $('#modalCRU').find('form').submit();
            })

            $('body').on('click', '.btn.btn-delete,.modal-remote.btn-delete', function(e) {
                e.preventDefault();
                const data = $(this).data();
                const title = data.title ?? 'Hapus Data';
                const desc = data.desc ?? 'Apakah anda yakin ingin menghapus data ini ?';

                $('#modalD').find('h4.modal-title').text(title);
                $('#modalD').find('span.modal-message').text(desc);
                $('#modalD').find('form').attr('action', $(this).attr('href'));
                $('#modalD').find('.error-hint').hide().empty();
                $('#modalD').modal('show');
            });


            $('#modalD').on('click', 'button[type=submit]', function(e) {
                e.preventDefault();
                const form = $('#modalD').find('form');
                const data = form.serialize();
                $.ajax({
                    url: form[0].action,
                    method: form[0].method,
                    data: data,
                    success: function(data) {
                        if (data.notification) {
                            toastr[data.notification.type](data.notification.message, data.notification.title);
                        }
                        if (!!data.status_code) {
                            if (data.status_code == 302){
                                window.location.href = data.location;
                            }
                            return;
                        }
                        if (!!data.callback_function) {
                            if (typeof window[data.callback_function] === 'function') {
                                window[data.callback_function](data.callback_data);
                            } else {
                              console.error('Function does not exist.');
                            }
                        }
                        const datatableId = data.datatableId ?? '#datatable';
                        if (!$(datatableId).DataTable) {
                            const redirect = $('div.need-delete').find('.btn-delete').data(
                                'redirect');
                            $('div.card-body,div.card-footer').slideUp();
                            if (!redirect) { // if no redirect url
                                alert("Berhasil menghapus record");
                            } else {
                                window.location = redirect;
                            }
                        } else {
                            $(datatableId).DataTable().draw(false);
                        }
                        $('#modalD').modal('hide');
                    },
                    beforeSend: function() {
                        $('#modalD').find('button[type=submit]').attr('disabled', true);
                        $('#modalD').find('.error-hint').hide().empty();
                    },
                    complete: function() {
                        $('#modalD').find('button[type=submit]').attr('disabled', false);
                    },
                    error: function(data) {
                        if (data.status == 401) {
                            window.location.reload();
                        } else {
                            $('#modalD').find('.error-hint').append(
                                `<b>${data.statusText} (${data.status})</b><br/><p>${data.responseText}`
                                );
                            $('#modalD').find('.error-hint').slideDown();
                        }
                    }
                });
            });

            function submitLocal() {
                const localCallback = $('#modalCRU-local-callback').val();
                const form = $('#modalCRU').find('form');
                var data = $(form).serializeArray();
                if (window[localCallback](data)) {
                    $('#modalCRU').modal('hide');
                }
            }

            function submitServer() {
                const form = $('#modalCRU').find('form');
                const data = form.serialize();

                $.ajax({
                    url: form[0].action,
                    method: form[0].method,
                    data: data,
                    dataType: 'json',
                    success: function(data) {
                        if (data.notification) {
                            toastr[data.notification.type](data.notification.message, data.notification.title);
                        }
                        const datatableId = data.datatableId ?? '#datatable';
                        if ($(datatableId).length) {
                            $(datatableId).DataTable().draw(false);
                        }
                        if (!!data.callback_function) {
                            if (typeof window[data.callback_function] === 'function') {
                                window[data.callback_function](data.callback_data);
                            } else {
                              console.error('Function does not exist.');
                            }
                        }
                        if (!!data.status_code) {
                            if (data.status_code == 302){
                                if (data.location_type == 'modal') {
                                    doXHR(data.location, data.location_data || []);
                                } else {
                                    window.location.href = data.location;
                                }
                            }
                            return;
                        }
                        $('#modalCRU').modal('hide');
                    },
                    beforeSend: function() {
                        $('#modalCRU').find('.content-main').hide();
                        $('#modalCRU').find('.content-loading').show();
                        $('#modalCRU').find('button[type=submit]').attr('disabled', true);
                    },
                    error: function(data) {
                        $('#modalCRU div.content-error').empty();
                        if (data.status == 401) {
                            window.location.reload();
                        } else if (data.status == 422) {
                            const json = data.responseJSON;
                            $('#modalCRU div.content-error').append(`<b>Data tidak valid:</b>`);
                            $('#modalCRU div.content-error').append('<ul></ul>');
                            Object.values(json.errors)
                                .forEach(function(error) {
                                    error.forEach(function(item) {
                                        $('#modalCRU div.content-error > ul')
                                            .append(`<li>${item}</li>`)
                                    })
                                })
                        } else {
                            $('#modalCRU div.content-error').append(
                                `<b>(${data.status}) ${data.statusText}</b><br/><p>${data.responseJSON?.message ?? data.responseText ?? "Please check your connection!"}`
                                );
                        }
                        $('#modalCRU').find('.content-loading').slideUp(200, function() {
                            $('#modalCRU').find(".content-main").slideDown();
                        });
                        $('#modalCRU').find('button[type=submit]').attr('disabled', false);
                        $('#modalCRU').stop().animate({
                            scrollTop: 0
                        }, 500, 'swing', function() {
                            $('#modalCRU div.content-error').slideDown();
                        });
                    }
                });
            }

            $('#modalCRU').on('submit', 'form', function(e) {
                e.preventDefault();
                const localCallback = $('#modalCRU-local-callback').val();
                if (!!localCallback && typeof window[localCallback] === 'function') {
                    submitLocal();
                } else {
                    submitServer();
                }
            });
        })
    </script>
@endpush

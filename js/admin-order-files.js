/**
 * Admin Order Files
 * Drag & Drop, AJAX uploads, PDF generation, File Management
 */
(function($){
    'use strict';

    $(function(){
        
        // --- UPLOAD HANDLERS ---
        $('body').on('click', '.select-file-btn', function(e) {
            e.preventDefault(); e.stopPropagation();
            openMediaFrame($(this));
        });

        // Click en texto o caja trigger input file
        $('body').on('click', '.muyunicos-dropzone', function(e) {
            if ($(e.target).closest('.select-file-btn').length > 0) return;
            $(this).find('.muyunicos-file-input').trigger('click');
        });
        $('body').on('click', '.muyunicos-file-input', function(e){ e.stopPropagation(); });

        // Input Change
        $('body').on('change', '.muyunicos-file-input', function() {
            if (this.files.length > 0) uploadFile(this.files[0], $(this).closest('.muyunicos-dropzone'));
        });

        // Drag & Drop
        $('body').on('dragover', '.muyunicos-dropzone', function(e) { e.preventDefault(); $(this).addClass('dragover'); });
        $('body').on('dragleave', '.muyunicos-dropzone', function(e) { e.preventDefault(); $(this).removeClass('dragover'); });
        $('body').on('drop', '.muyunicos-dropzone', function(e) { 
            e.preventDefault(); $(this).removeClass('dragover');
            if (e.originalEvent.dataTransfer.files.length > 0) uploadFile(e.originalEvent.dataTransfer.files[0], $(this));
        });

        function openMediaFrame(btn) {
            if ( typeof wp === 'undefined' || !wp.media ) return;
            var frame = wp.media({ title: 'Seleccionar Archivo', multiple: false, button: { text: 'Añadir' } });
            var dropzone = btn.closest('.muyunicos-dropzone');
            var itemId = dropzone.data('item-id');
            // Nonce is global now via wp_localize_script
            var nonce = muOrderFilesData.nonce;

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $.post(muOrderFilesData.ajaxurl, { 
                    action: 'mu_add_media_file', 
                    item_id: itemId, 
                    file_url: attachment.url, 
                    security: nonce 
                }, function(res) {
                    handleResponse(res, itemId);
                });
            });
            frame.open();
        }

        function uploadFile(file, dropzone) {
            var itemId = dropzone.data('item-id');
            var nonce = muOrderFilesData.nonce;
            var fd = new FormData();
            fd.append('file', file); 
            fd.append('action', 'mu_upload_file'); 
            fd.append('item_id', itemId); 
            fd.append('security', nonce);

            var feedback = $('.feedback-up-' + itemId);
            feedback.text('Subiendo...').show();

            $.ajax({
                url: muOrderFilesData.ajaxurl, type: 'POST', data: fd, contentType: false, processData: false,
                success: function(res) { handleResponse(res, itemId); },
                complete: function() { setTimeout(function(){ feedback.fadeOut(); }, 2000); }
            });
        }

        function handleResponse(res, itemId) {
            var btn = $('#pdf-controls-wrapper-' + itemId).find('.administar-btn');
            var feedback = $('.feedback-up-' + itemId);
            
            if (res.success) {
                feedback.text('✔ Ok').css('color','green');
                btn.data('archivos', res.data.all_files).attr('data-archivos', JSON.stringify(res.data.all_files));
                btn.find('.file-count').text(res.data.all_files.length);
                btn.prop('disabled', false);
            } else {
                feedback.text('Error').css('color','red');
                alert(res.data.message || 'Error desconocido');
            }
        }

        // --- PDF GENERATION STUB ---
        $('body').on('click', '.generar-pdf-ajax-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var itemId = btn.data('item-id');
            var nonce = muOrderFilesData.nonce;
            
            // Selección de imagen para el PDF
            var frame = wp.media({ title: 'Imagen para PDF', library: {type: 'image'}, multiple: false });
            frame.on('select', function() {
               var att = frame.state().get('selection').first().toJSON();
               var feedback = $('.feedback-gen-' + itemId);
               feedback.text('Generando...').show();
               $.post(muOrderFilesData.ajaxurl, {
                   action: 'mu_generate_pdf', item_id: itemId, image_id: att.id, security: nonce
               }, function(res){
                   if(res.success) {
                       feedback.text('✔ Listo');
                       handleResponse(res, itemId); // Reutilizamos lógica de actualización
                   } else {
                       feedback.text('Error');
                       alert(res.data.message);
                   }
               });
            });
            frame.open();
        });

        // --- MODAL & DELETE ---
        var modal = $('#mu-file-manager-modal');
        var list = modal.find('.mu-file-list');
        var currentItem = null;

        $('body').on('click', '.administar-btn', function() {
            currentItem = $(this).data('item-id');
            var files = $(this).data('archivos');
            if (typeof files === 'string') files = JSON.parse(files);
            renderModal(files);
            modal.fadeIn(200);
        });

        $('.mu-close').click(function(){ modal.fadeOut(200); });
        $(window).click(function(e){ if($(e.target).is(modal)) modal.fadeOut(200); });

        function renderModal(files) {
            list.empty();
            if(!files || files.length === 0) { modal.find('.mu-empty').show(); return; }
            modal.find('.mu-empty').hide();
            
            $.each(files, function(i, url) {
                var name = url.split('/').pop();
                list.append(`
                    <li>
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:250px;">${name}</span>
                        <div style="flex-shrink:0;">
                            <a href="${url}" target="_blank" class="button button-small">Ver</a>
                            <button class="button button-small delete-file-btn" style="color:#a00;border-color:#a00;" data-url="${url}">×</button>
                        </div>
                    </li>
                `);
            });
        }

        $('body').on('click', '.delete-file-btn', function() {
            if(!confirm('¿Quitar archivo?')) return;
            var btn = $(this);
            var url = btn.data('url');
            var nonce = muOrderFilesData.nonce;
            
            btn.text('...').prop('disabled',true);
            $.post(muOrderFilesData.ajaxurl, {
                action: 'mu_delete_file', item_id: currentItem, file_url: url, security: nonce
            }, function(res){
                if(res.success) {
                    handleResponse(res, currentItem);
                    renderModal(res.data.all_files); // Refrescar modal
                    if(res.data.all_files.length === 0) modal.find('.mu-empty').show();
                } else {
                    alert('Error al borrar');
                    btn.text('×').prop('disabled',false);
                }
            });
        });

    });
})(jQuery);

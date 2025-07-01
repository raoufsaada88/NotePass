jQuery(document).ready(function($) {
    // Initialize jQuery UI tabs
    $('#fh-tabs').tabs();

    // Add new dynamic block row
    $('.fh-add-block').on('click', function() {
        var section = $(this).data('section');
        var container = $('.fh-blocks-container[data-section="' + section + '"]');

        // Create new block row HTML
        var index = container.find('.fh-block-row').length;

        var newBlock = $('<div class="fh-block-row" data-index="' + index + '">' +
            '<input type="text" name="' + section + '_label[]" placeholder="Label" style="width:30%; margin-right:10px;">' +
            '<input type="text" name="' + section + '_value[]" placeholder="Value" style="width:30%; margin-right:10px;">' +
            '<textarea class="fh-block-note" name="' + section + '_note[]" rows="4" style="width:35%;"></textarea>' +
            '<button type="button" class="button fh-remove-block" style="margin-left:10px;">Remove</button>' +
            '</div>');

        container.append(newBlock);
    });

    // Remove block row
    $(document).on('click', '.fh-remove-block', function() {
        var row = $(this).closest('.fh-block-row');
        row.fadeOut(200, function() {
            $(this).remove();
        });
    });

    // Export to HTML (simple)
    $('#export-html').on('click', function(e) {
        e.preventDefault();
        var content = $('#fh-notes-form').html();
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>Exported Notes</title></head><body>' + content + '</body></html>');
        win.document.close();
        win.focus();
    });

    // Export to PDF using jsPDF
    $('#export-pdf').on('click', function(e) {
        e.preventDefault();

        const { jsPDF } = window.jspdf;
        var doc = new jsPDF();

        // Simple text export (improvable)
        var text = '';
        $('#fh-notes-form').find('input[type="text"], textarea').each(function() {
            text += $(this).val() + '\n';
        });

        doc.text(text || 'Project Handover Notes', 10, 10);
        doc.save('notepass-handover-notes.pdf');
    });
});

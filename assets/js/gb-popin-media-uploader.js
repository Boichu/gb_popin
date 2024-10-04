jQuery(document).ready(function($) {
    

    $('.upload-button').on('click', function(e) {
        console.log('click upload');
        var mediaUploader;
        var thos = this;
        e.preventDefault();
        // If the media uploader instance exists, reopen it.
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        // Create a new media uploader instance.
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Select Image',
            button: {
                text: 'Select Image'
            },
            multiple: false
        });

        // When an image is selected, run a callback.
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $(thos).parent().find('.upload-selected').val(attachment.url);
            $(thos).parent().find('.champ_hidden').val(attachment.id);
            $(thos).parent().find('.upload-image').attr('src', attachment.url).show();
            mediaUploader.close();
        });

        // Open the uploader dialog.
        mediaUploader.open();
    });
});
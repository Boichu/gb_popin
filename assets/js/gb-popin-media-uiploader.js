jQuery(document).ready(function($) {
    var mediaUploader;

    $('#upload-button').on('click', function(e) {
        e.preventDefault();
        // If the media uploader instance exists, reopen it.
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        // Create a new media uploader instance.
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Select Portrait Image',
            button: {
                text: 'Select Image'
            },
            multiple: false
        });

        // When an image is selected, run a callback.
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#image-url').val(attachment.url);
        });

        // Open the uploader dialog.
        mediaUploader.open();
    });
});
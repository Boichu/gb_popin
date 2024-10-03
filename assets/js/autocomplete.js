jQuery(document).ready(function($) {
    $('#projet').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: myAutocomplete.ajaxurl,
                dataType: "json",
                data: {
                    action: 'search_projet', // L'action dans WordPress
                    term: request.term // Le terme recherché
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 2, // Nombre minimum de caractères pour déclencher l'autocomplétion
        select: function(event, ui) {
            $('#projet').val(ui.item.label); // Mettre à jour la valeur du champ avec le projet sélectionné
            return false;
        }
    });
});
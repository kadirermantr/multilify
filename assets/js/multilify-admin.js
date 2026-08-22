/**
 * Multilify admin JavaScript.
 */

(function($) {
    'use strict';

    var l10n = window.multilifyAdmin || {};

    $(document).ready(function() {

        // Form validation for language code
        $('#lang_code').on('input', function() {
            var val = $(this).val();
            // Convert to lowercase and remove invalid characters
            val = val.toLowerCase().replace(/[^a-z]/g, '');
            $(this).val(val);
        });

        // Confirm delete
        $('.button-link-delete').on('click', function(e) {
            if (!window.confirm(l10n.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });

        // Toggle inline edit row for an existing language
        $('.multilify-edit-toggle').on('click', function() {
            var code = $(this).data('code');
            var $row = $('#multilify-edit-' + code);
            var isOpen = $row.is(':visible');

            $row.toggle();
            $(this).attr('aria-expanded', !isOpen);

            // Move focus into the form so keyboard users land where they expect.
            if (!isOpen) {
                $row.find('input[name="lang_name"]').trigger('focus');
            }
        });

        // Cancel inline edit
        $('.multilify-edit-cancel').on('click', function() {
            var code = $(this).data('code');

            $('#multilify-edit-' + code).hide();
            $('.multilify-edit-toggle[data-code="' + code + '"]')
                .attr('aria-expanded', 'false')
                .trigger('focus');
        });

        // Auto-generate slug from title
        $('[id^="multilang_title_"]').on('blur', function() {
            var langCode = $(this).attr('id').replace('multilang_title_', '');
            var slugField = $('#multilang_slug_' + langCode);

            // Only auto-generate if slug is empty
            if (slugField.val() === '') {
                slugField.val(generateSlug($(this).val()));
            }
        });

        // Generate slug helper function
        function generateSlug(text) {
            text = (text === null || text === undefined) ? '' : String(text);

            var map = {
                'ğ': 'g', 'Ğ': 'g',
                'ü': 'u', 'Ü': 'u',
                'ş': 's', 'Ş': 's',
                'ı': 'i', 'İ': 'i',
                'ö': 'o', 'Ö': 'o',
                'ç': 'c', 'Ç': 'c',
                'ä': 'a', 'Ä': 'a',
                'ß': 'ss'
            };

            // Transliterate before lowercasing so Turkish İ/I map correctly.
            return text
                .trim()
                .replace(/[ğĞüÜşŞıİöÖçÇäÄß]/g, function(ch) {
                    return map[ch];
                })
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }


    });

})(jQuery);

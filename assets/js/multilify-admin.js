/**
 * Multilang Admin JavaScript
 */

(function($) {
    'use strict';

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
            if (!confirm('Are you sure you want to delete this language? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-generate slug from title
        $('[id^="multilang_title_"]').on('blur', function() {
            var langCode = $(this).attr('id').replace('multilang_title_', '');
            var slugField = $('#multilang_slug_' + langCode);

            // Only auto-generate if slug is empty
            if (slugField.val() === '') {
                var title = $(this).val();
                var slug = generateSlug(title);
                slugField.val(slug);
            }
        });

        // Generate slug helper function
        function generateSlug(text) {
            return text
                .toLowerCase()
                .trim()
                // Turkish characters
                .replace(/ğ/g, 'g')
                .replace(/ü/g, 'u')
                .replace(/ş/g, 's')
                .replace(/ı/g, 'i')
                .replace(/ö/g, 'o')
                .replace(/ç/g, 'c')
                // German characters
                .replace(/ä/g, 'a')
                .replace(/ö/g, 'o')
                .replace(/ü/g, 'u')
                .replace(/ß/g, 'ss')
                // Other special characters
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }

        // Smooth scroll to error
        if ($('.error').length) {
            $('html, body').animate({
                scrollTop: $('.error').first().offset().top - 100
            }, 500);
        }

        // Add tooltips
        if (typeof jQuery.fn.tooltip !== 'undefined') {
            $('[data-tooltip]').tooltip();
        }

        // Language switcher cookie handler
        $('.wp-multilang-switcher .lang-link').on('click', function() {
            var lang = $(this).data('lang');
            // Set cookie for language preference
            document.cookie = 'wp_multilang_preference=' + lang + '; path=/; max-age=2592000'; // 30 days
        });

    });

})(jQuery);

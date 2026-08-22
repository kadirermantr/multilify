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
            var row = document.getElementById('multilify-edit-' + code);

            if (!row) {
                return;
            }

            var willOpen = row.hidden;

            row.hidden = !willOpen;
            $(this).attr('aria-expanded', willOpen ? 'true' : 'false');

            // Move focus into the form so keyboard users land where they expect.
            if (willOpen) {
                $(row).find('input[name="lang_name"]').trigger('focus');
            }
        });

        // Cancel inline edit
        $('.multilify-edit-cancel').on('click', function() {
            var code = $(this).data('code');
            var row = document.getElementById('multilify-edit-' + code);

            if (row) {
                row.hidden = true;
            }

            $('.multilify-edit-toggle[data-code="' + code + '"]')
                .attr('aria-expanded', 'false')
                .trigger('focus');
        });

        // Copy a snippet to the clipboard, with a text selection fallback for
        // browsers that refuse the async clipboard API.
        $('[data-multilify-copy]').on('click', function() {
            var $button = $(this);
            var $label = $button.find('.multilify-copy__label');
            var text = $button.attr('data-multilify-copy');
            var original = $button.data('originalLabel');

            if (!original) {
                original = $label.text();
                $button.data('originalLabel', original);
            }

            function report(message, ok) {
                $label.text(message);
                $button.toggleClass('is-copied', ok);

                window.clearTimeout($button.data('resetTimer'));
                $button.data('resetTimer', window.setTimeout(function() {
                    $label.text(original);
                    $button.removeClass('is-copied');
                }, 2000));
            }

            // execCommand is synchronous and needs no permission prompt, so it
            // reports a result even where the async API sits pending on one.
            if (copyWithExecCommand(text)) {
                report(l10n.copied || 'Copied', true);
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    report(l10n.copied || 'Copied', true);
                }).catch(function() {
                    selectSnippet($button);
                    report(l10n.copyFailed || 'Press Ctrl+C to copy', false);
                });
                return;
            }

            selectSnippet($button);
            report(l10n.copyFailed || 'Press Ctrl+C to copy', false);
        });

        // Flag picker: the grid and the free text field stay in step, so whichever
        // one the user reaches for, the form submits a single value.
        $('[data-multilify-flagpicker]').each(function() {
            var $picker = $(this);
            var $input = $picker.find('[data-multilify-flaginput]');
            var $options = $picker.find('.multilify-flagpicker__option');

            function markSelected(value) {
                $options.each(function() {
                    var isMatch = ($(this).data('flag') === value);

                    $(this)
                        .toggleClass('is-selected', isMatch)
                        .attr('aria-checked', isMatch ? 'true' : 'false')
                        .attr('tabindex', isMatch ? '0' : '-1');
                });

                // With nothing chosen the first option stays keyboard reachable.
                if (!$options.filter('.is-selected').length) {
                    $options.first().attr('tabindex', '0');
                }
            }

            $options.on('click', function() {
                var value = $(this).data('flag');

                // Clicking the selected flag again clears it.
                if ($(this).hasClass('is-selected')) {
                    $input.val('');
                    markSelected('');
                    return;
                }

                $input.val(value);
                markSelected(value);
            });

            // Arrow keys move through the grid, as a radio group should.
            $options.on('keydown', function(e) {
                var keys = ['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'];

                if (keys.indexOf(e.key) === -1) {
                    return;
                }

                e.preventDefault();

                var index = $options.index(this);
                var step = (e.key === 'ArrowRight' || e.key === 'ArrowDown') ? 1 : -1;
                var next = (index + step + $options.length) % $options.length;

                $options.eq(next).trigger('focus').trigger('click');
            });

            $input.on('input', function() {
                markSelected($(this).val());
            });

            markSelected($input.val());
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

        // Copy through a throwaway textarea; returns false when the browser
        // refuses, so the caller can fall back.
        function copyWithExecCommand(text) {
            var field = document.createElement('textarea');

            field.value = text;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.top = '-1000px';
            field.style.opacity = '0';

            document.body.appendChild(field);

            var selection = document.getSelection();
            var previous = selection.rangeCount ? selection.getRangeAt(0) : null;
            var copied = false;

            field.select();

            try {
                copied = document.execCommand('copy');
            } catch (e) {
                copied = false;
            }

            document.body.removeChild(field);

            // Restore whatever the user had selected before the click.
            if (previous) {
                selection.removeAllRanges();
                selection.addRange(previous);
            }

            return copied;
        }

        // Select the snippet so the keyboard shortcut can finish the job.
        function selectSnippet($button) {
            var node = $button.siblings('pre').get(0);

            if (!node || !window.getSelection) {
                return;
            }

            var range = document.createRange();

            range.selectNodeContents(node);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
        }

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

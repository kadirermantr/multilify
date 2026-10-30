/**
 * Multilify admin JavaScript.
 */

(function($) {
    'use strict';

    var l10n = window.multilifyAdmin || {};

    // The block editor moves the meta boxes into its own panel once it has
    // rendered, and moving an iframe reloads it. WordPress waits for the page
    // to finish loading before it builds an editor inside a meta box, but the
    // move can still come later, and then the translation editors showed an
    // empty page while their content sat in a document no longer on screen.
    // An editor that has lost its document is rebuilt from the content it
    // still holds. One in Code mode is left alone: it shows the textarea.
    function reviveEditor(frame) {
        var id = frame.id.replace(/_ifr$/, '');
        var editor = window.tinymce && window.tinymce.get(id);
        var settings = window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit[id];

        if (!editor || !settings || !editor.initialized || editor.isHidden() || editor.getDoc() === frame.contentDocument) {
            return;
        }

        editor.save();
        editor.remove();
        window.tinymce.init(settings);
    }

    // TinyMCE names every editor frame "Rich Text Area", so a screen reader
    // cannot tell the languages apart. The name the meta box passes as
    // aria_label goes on the frame instead, whenever one appears or reloads.
    function nameEditorFrame(frame) {
        var settings = window.tinyMCEPreInit && window.tinyMCEPreInit.mceInit[frame.id.replace(/_ifr$/, '')];

        if (settings && settings.aria_label) {
            frame.setAttribute('title', settings.aria_label);
        }
    }

    function reviveEditors() {
        $('iframe[id^="multilang_content_"]').each(function() {
            reviveEditor(this);
            nameEditorFrame(this);
        });
    }

    // A moved iframe loads again. Load does not bubble, so it is caught on
    // the way down instead.
    document.addEventListener('load', function(e) {
        if (e.target && 'IFRAME' === e.target.tagName && /^multilang_content_.+_ifr$/.test(e.target.id)) {
            reviveEditor(e.target);
            nameEditorFrame(e.target);
        }
    }, true);

    $(document).on('tinymce-editor-init', function(event, editor) {
        if (/^multilang_content_/.test(editor.id) && editor.iframeElement) {
            nameEditorFrame(editor.iframeElement);
        }
    });

    // And once the page has loaded, for a move that came before this ran.
    if ('complete' === document.readyState) {
        window.setTimeout(reviveEditors, 0);
    } else {
        $(window).on('load', function() {
            window.setTimeout(reviveEditors, 0);
        });
    }

    $(document).ready(function() {

        // Form validation for language code
        $('#lang_code').on('input', function() {
            var val = $(this).val();
            // Convert to lowercase and remove invalid characters
            val = val.toLowerCase().replace(/[^a-z]/g, '');
            $(this).val(val);
        });

        // Confirm a delete in the page rather than through window.confirm, which
        // renders as a browser chrome alert and cannot be styled.
        var $dialog = $('#multilify-confirm');
        var $page = $('#wpwrap');
        var pendingForm = null;
        var lastFocus = null;

        // The page behind an open dialog is made inert, which needs the dialog
        // outside it: it is moved under body once.
        $dialog.appendTo(document.body);

        function closeDialog() {
            $dialog.attr('hidden', true);
            $page.removeAttr('inert');
            pendingForm = null;

            if (lastFocus) {
                $(lastFocus).trigger('focus');
                lastFocus = null;
            }
        }

        function openDialog(form, languageName) {
            pendingForm = form;
            lastFocus = document.activeElement;

            // A function, so a $ in the language name is never read as a pattern.
            $dialog.find('.multilify-dialog__title').text(
                (l10n.confirmDeleteTitle || 'Delete %s?').replace('%s', function() {
                    return languageName;
                })
            );

            $page.attr('inert', '');
            $dialog.removeAttr('hidden');
            $dialog.find('[data-multilify-dialog-cancel]').trigger('focus');
        }

        $('form[data-multilify-confirm]').on('submit', function(e) {
            // The dialog re-submits this same form once confirmed.
            if (pendingForm === this) {
                return;
            }

            // attr, not data: data() would turn a language called "0" or "null" into a non-string.
            e.preventDefault();
            openDialog(this, $(this).attr('data-language') || '');
        });

        $dialog.on('click', '[data-multilify-dialog-cancel]', closeDialog);

        $dialog.on('click', '[data-multilify-dialog-confirm]', function() {
            var form = pendingForm;

            if (form) {
                $dialog.attr('hidden', true);
                $page.removeAttr('inert');
                lastFocus = null;
                form.submit();
            }
        });

        // Clicking the backdrop dismisses, the same as cancelling.
        $dialog.on('click', function(e) {
            if (e.target === this) {
                closeDialog();
            }
        });

        $(document).on('keydown', function(e) {
            if (!$dialog.attr('hidden') && e.key === 'Escape') {
                closeDialog();
            }
        });

        // aria-modal only means anything if focus cannot walk out of the panel
        // into the page behind it.
        $dialog.on('keydown', function(e) {
            if (e.key !== 'Tab') {
                return;
            }

            var focusable = $dialog
                .find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
                .filter(':visible');

            if (!focusable.length) {
                return;
            }

            var first = focusable.get(0);
            var last = focusable.get(focusable.length - 1);

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
                return;
            }

            if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        // Edit a language in its dialog. Closing it any way but Save drops
        // what was typed: the next opening shows the saved name and flag.
        $('.multilify-edit-toggle').on('click', function() {
            var dialog = document.getElementById('multilify-edit-' + $(this).data('code'));

            if (dialog && 'function' === typeof dialog.showModal) {
                dialog.showModal();
                $(dialog).find('input[name="lang_name"]').trigger('focus');
            }
        });

        $('[data-multilify-edit]').each(function() {
            var dialog = this;
            var $fields = $(dialog).find('input[name="lang_name"], input[name="lang_flag"]');
            var $save = $(dialog).find('[data-multilify-edit-save]');

            // Save stays off until the name or the flag differs from what is saved.
            // The flag grid writes the field without an input event, so clicks count too.
            function updateSave() {
                var dirty = $fields.toArray().some(function(field) {
                    return field.value.trim() !== field.defaultValue.trim();
                });

                $save.prop('disabled', !dirty);
            }

            $(dialog).on('input change', 'input', updateSave);
            $(dialog).on('click', '.multilify-flagpicker__option', function() {
                window.setTimeout(updateSave, 0);
            });

            $(dialog).on('click', '[data-multilify-edit-close]', function() {
                dialog.close();
            });

            $(dialog).on('click', function(e) {
                if (e.target === dialog) {
                    dialog.close();
                }
            });

            dialog.addEventListener('close', function() {
                $(dialog).find('form').get(0).reset();
                $(dialog).find('[data-multilify-flaginput]').trigger('input');
                updateSave();
            });
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

            // A live region inside a button is not reliably announced, so the
            // result is also written to a status region beside it.
            var $status = $button.siblings('[data-multilify-copy-status]');

            function report(message, ok) {
                $label.text(message);
                $status.text(message);
                $button.toggleClass('is-copied', ok);

                window.clearTimeout($button.data('resetTimer'));
                $button.data('resetTimer', window.setTimeout(function() {
                    $label.text(original);
                    $status.text('');
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

            $options.on('click', function(e) {
                var value = $(this).data('flag');

                // Clicking the selected flag again clears it. Only a pointer
                // click: Space on a checked radio must leave it checked.
                if ($(this).hasClass('is-selected') && e.originalEvent && e.originalEvent.detail > 0) {
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

                // In a right-to-left admin the grid starts on the right, so
                // the horizontal arrows swap direction.
                if (document.documentElement.dir === 'rtl' && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
                    step = -step;
                }
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
                slugField.val(generateSlug($(this).val(), langCode));
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
            var active = document.activeElement;
            var copied = false;

            field.select();

            try {
                copied = document.execCommand('copy');
            } catch (e) {
                copied = false;
            }

            document.body.removeChild(field);

            // Selecting the field took focus with it, and removing the field
            // dropped focus to the page, so the next Tab started from the top.
            if (active && typeof active.focus === 'function') {
                active.focus();
            }

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
        function generateSlug(text, langCode) {
            text = (text === null || text === undefined) ? '' : String(text);

            // German writes an umlaut out as a digraph, as the guide's /de/ueber-uns/ does.
            if (langCode === 'de') {
                var umlauts = { 'ä': 'ae', 'Ä': 'ae', 'ö': 'oe', 'Ö': 'oe', 'ü': 'ue', 'Ü': 'ue' };

                text = text.replace(/[äÄöÖüÜ]/g, function(ch) {
                    return umlauts[ch];
                });
            }

            // Letters that do not decompose into a base letter plus an accent.
            var map = {
                'ı': 'i', 'İ': 'i',
                'ß': 'ss',
                'æ': 'ae', 'Æ': 'ae',
                'œ': 'oe', 'Œ': 'oe',
                'ø': 'o', 'Ø': 'o',
                'ł': 'l', 'Ł': 'l',
                'đ': 'd', 'Đ': 'd'
            };

            // Transliterate before lowercasing so Turkish İ/I map correctly.
            // Every other accented letter is decomposed and its mark dropped,
            // so ñ, é and ç come out as n, e and c the way sanitize_title() does.
            return text
                .trim()
                .replace(/[ıİßæÆœŒøØłŁđĐ]/g, function(ch) {
                    return map[ch];
                })
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}\s_-]/gu, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }


    });


    // ---------------------------------------------------------------
    // Languages screen and translations box.
    $(function() {

        // Translations box: one tab per language, arrow keys move between them.
        $('[data-multilify-tabs]').each(function() {
            var $box = $(this);
            var $tabs = $box.find('[role="tab"]');

            function select($tab, focus) {
                $tabs.attr('aria-selected', 'false').attr('tabindex', '-1');
                $tab.attr('aria-selected', 'true').attr('tabindex', '0');
                $box.find('[role="tabpanel"]').removeClass('is-active');
                $('#' + $tab.attr('aria-controls')).addClass('is-active');

                if (focus) {
                    $tab.trigger('focus');
                }
            }

            $tabs.on('click', function() {
                select($(this), false);
            });

            $tabs.on('keydown', function(e) {
                var index = $tabs.index(this);
                var next = null;

                if ('ArrowRight' === e.key || 'ArrowDown' === e.key) {
                    next = (index + 1) % $tabs.length;
                } else if ('ArrowLeft' === e.key || 'ArrowUp' === e.key) {
                    next = (index - 1 + $tabs.length) % $tabs.length;
                } else if ('Home' === e.key) {
                    next = 0;
                } else if ('End' === e.key) {
                    next = $tabs.length - 1;
                }

                if (null !== next) {
                    e.preventDefault();
                    select($tabs.eq(next), true);
                }
            });
        });

        // Add a language: a dialog that fills its fields from the catalog.
        var add = document.querySelector('[data-multilify-add]');

        if (add && 'function' === typeof add.showModal) {
            var $add = $(add);
            var $code = $add.find('#lang_code');
            var $addressCode = $add.find('[data-multilify-add-code]');

            // The address in the code hint follows what is typed; an ellipsis
            // stands in until there is a code.
            function updatePreview() {
                var value = $code.val();

                $addressCode.text(value ? value : '\u2026');
            }

            $('[data-multilify-add-open]').on('click', function() {
                add.showModal();
                $add.find('[data-multilify-add-filter]').trigger('focus');
            });

            $add.on('click', '[data-multilify-add-close]', function() {
                add.close();
            });

            // A click on the backdrop closes it, as WordPress's own modals do.
            $add.on('click', function(e) {
                if (e.target === add) {
                    add.close();
                }
            });

            var $list = $add.find('[data-multilify-add-list]');
            var $filter = $add.find('[data-multilify-add-filter]');

            $add.on('click', '.mfy-option, .mfy-tile[data-code]', function() {
                var $pick = $(this);

                $add.find('.is-picked').removeClass('is-picked');
                $pick.addClass('is-picked');
                $code.val($pick.attr('data-code'));
                $add.find('#lang_name').val($pick.attr('data-name'));
                $add.find('#lang_flag').val($pick.attr('data-flag'));
                updatePreview();

                // A pick from the results closes them and leaves its name in the search.
                if ($pick.hasClass('mfy-option')) {
                    $filter.val($pick.attr('data-name'));
                    $list.prop('hidden', true);
                }
            });

            // Matching ignores case and accents, so "viet" finds Tiếng Việt.
            function fold(text) {
                return String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
            }

            // The results open only while something is typed, and only with matches.
            $filter.on('input', function() {
                var query = fold($(this).val()).trim();
                var shown = 0;

                $add.find('.mfy-option').each(function() {
                    var $option = $(this);
                    var haystack = fold($option.attr('data-name') + ' ' + $option.attr('data-english') + ' ' + $option.attr('data-code'));
                    var match = '' !== query && -1 !== haystack.indexOf(query);

                    $option.prop('hidden', !match);
                    shown += match ? 1 : 0;
                });

                $list.prop('hidden', 0 === shown);
            });

            $code.on('input', updatePreview);

            // Closed without adding (Escape, the close button, Cancel or the
            // backdrop): the next opening starts from an empty form.
            add.addEventListener('close', function() {
                $add.find('form').get(0).reset();
                $add.find('.is-picked').removeClass('is-picked');
                $add.find('.mfy-option').prop('hidden', false);
                $list.prop('hidden', true);
                updatePreview();
            });
        }

        // Row menus: placed under their button, and closed before the delete
        // confirmation opens over them.
        $('.mfy-menu').each(function() {
            var menu = this;

            menu.addEventListener('toggle', function(e) {
                if ('open' !== e.newState) {
                    return;
                }

                var button = document.querySelector('[popovertarget="' + menu.id + '"]');
                var rect = button.getBoundingClientRect();

                menu.style.top = (rect.bottom + 4) + 'px';
                menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
            });

            $(menu).find('form').on('submit', function() {
                menu.hidePopover();
            });
        });
    });
})(jQuery);

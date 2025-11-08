<?php
/**
 * Translation meta box view
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="multilify-meta-box">
    <p class="description">
        Enter content for this language. If left empty, the default language content will be displayed.
    </p>

    <div class="multilify-field">
        <label for="multilang_title_<?php echo esc_attr( $lang_code ); ?>">
            <strong>Title</strong>
        </label>
        <input type="text"
               id="multilang_title_<?php echo esc_attr( $lang_code ); ?>"
               name="multilang_title_<?php echo esc_attr( $lang_code ); ?>"
               value="<?php echo esc_attr( $title ); ?>"
               class="widefat"
               placeholder="Enter title in this language...">
    </div>

    <div class="multilify-field">
        <label for="multilang_slug_<?php echo esc_attr( $lang_code ); ?>">
            <strong>Slug (URL)</strong>
        </label>
        <input type="text"
               id="multilang_slug_<?php echo esc_attr( $lang_code ); ?>"
               name="multilang_slug_<?php echo esc_attr( $lang_code ); ?>"
               value="<?php echo esc_attr( $slug ); ?>"
               class="widefat"
               placeholder="example-page, beispiel-seite, ornek-sayfa...">
        <p class="description">
            Custom slug for the URL. If left empty, the main content slug will be used.
        </p>
    </div>

    <div class="multilify-field">
        <label for="multilang_content_<?php echo esc_attr( $lang_code ); ?>">
            <strong>Content</strong>
        </label>
        <?php
        $multilify_editor_id = 'multilang_content_' . $lang_code;
        $multilify_settings = array(
            'textarea_name' => 'multilang_content_' . $lang_code,
            'textarea_rows' => 10,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => array(
                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
            ),
        );
        wp_editor( $content, $multilify_editor_id, $multilify_settings );
        ?>
    </div>
</div>

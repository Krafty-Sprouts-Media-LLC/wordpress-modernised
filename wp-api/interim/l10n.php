<?php

/**
 * Stand-in for WordPress's early translation loader until the translation
 * step. Listed in docs/stand-ins.md. (`__()` itself is Laravel's for now: see
 * docs/stand-ins.md, "Known clash".)
 */
if (! function_exists('wp_load_translations_early')) {
    /**
     * Loads translations early, before WordPress has fully started.
     *
     * Stand-in: there are no WordPress translations to load yet, so it does nothing.
     *
     * @since WM-0.2.0
     * @since WP-3.4.0
     */
    function wp_load_translations_early()
    {
        // Nothing to load until the translation step.
    }
}

if (! function_exists('get_locale')) {
    /**
     * Retrieves the current locale.
     *
     * Stand-in: always English (US), passed through WordPress's `locale`
     * filter, until the translation step reads WPLANG and the site's setting.
     *
     * @since WM-0.5.0
     * @since WP-1.5.0
     *
     * @return string The locale of the blog or from the {@see 'locale'} hook.
     */
    function get_locale()
    {
        /** This filter is documented in wp-includes/l10n.php */
        return apply_filters('locale', 'en_US');
    }
}

/*
 * Stand-ins for WordPress's translation functions until the translation step:
 * each returns the English text, passed through the same gettext filters
 * WordPress applies, so plugins that filter text still see it.
 */
if (! function_exists('_x')) {
    /**
     * Retrieves translated string with gettext context.
     *
     * Stand-in: English only (docs/stand-ins.md).
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $text  Text to translate.
     * @param  string  $context  Context information for the translators.
     * @param  string  $domain  Optional. Text domain. Default 'default'.
     * @return string Translated context string without pipe.
     */
    function _x($text, $context, $domain = 'default')
    {
        return apply_filters('gettext_with_context', $text, $text, $context, $domain);
    }
}

if (! function_exists('_n')) {
    /**
     * Translates and retrieves the singular or plural form based on the supplied number.
     *
     * Stand-in: English only (docs/stand-ins.md).
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $single  The text to be used if the number is singular.
     * @param  string  $plural  The text to be used if the number is plural.
     * @param  int  $number  The number to compare against to use either the singular or plural form.
     * @param  string  $domain  Optional. Text domain. Default 'default'.
     * @return string The translated singular or plural form.
     */
    function _n($single, $plural, $number, $domain = 'default')
    {
        $translation = $number == 1 ? $single : $plural;

        return apply_filters('ngettext', $translation, $single, $plural, $number, $domain);
    }
}

if (! function_exists('_nx')) {
    /**
     * Translates and retrieves the singular or plural form based on the supplied number, with gettext context.
     *
     * Stand-in: English only (docs/stand-ins.md).
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $single  The text to be used if the number is singular.
     * @param  string  $plural  The text to be used if the number is plural.
     * @param  int  $number  The number to compare against to use either the singular or plural form.
     * @param  string  $context  Context information for the translators.
     * @param  string  $domain  Optional. Text domain. Default 'default'.
     * @return string The translated singular or plural form.
     */
    function _nx($single, $plural, $number, $context, $domain = 'default')
    {
        $translation = $number == 1 ? $single : $plural;

        return apply_filters('ngettext_with_context', $translation, $single, $plural, $number, $context, $domain);
    }
}

if (! function_exists('_n_noop')) {
    /**
     * Registers plural strings in POT file, but does not translate them.
     *
     * @since WM-0.5.0
     * @since WP-2.5.0
     *
     * @param  string  $singular  Singular form to be localized.
     * @param  string  $plural  Plural form to be localized.
     * @param  string  $domain  Optional. Text domain. Default null.
     * @return array Array of translation information for the strings.
     */
    function _n_noop($singular, $plural, $domain = null)
    {
        return [
            0 => $singular,
            1 => $plural,
            'singular' => $singular,
            'plural' => $plural,
            'context' => null,
            'domain' => $domain,
        ];
    }
}

if (! function_exists('_nx_noop')) {
    /**
     * Registers plural strings with gettext context in POT file, but does not translate them.
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $singular  Singular form to be localized.
     * @param  string  $plural  Plural form to be localized.
     * @param  string  $context  Context information for the translators.
     * @param  string  $domain  Optional. Text domain. Default null.
     * @return array Array of translation information for the strings.
     */
    function _nx_noop($singular, $plural, $context, $domain = null)
    {
        return [
            0 => $singular,
            1 => $plural,
            2 => $context,
            'singular' => $singular,
            'plural' => $plural,
            'context' => $context,
            'domain' => $domain,
        ];
    }
}

if (! function_exists('esc_html__')) {
    /**
     * Retrieves the translation of $text and escapes it for safe use in HTML output.
     *
     * Stand-in: English only (docs/stand-ins.md).
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $text  Text to translate.
     * @param  string  $domain  Optional. Text domain. Default 'default'.
     * @return string Translated text.
     */
    function esc_html__($text, $domain = 'default')
    {
        return esc_html(apply_filters('gettext', $text, $text, $domain));
    }
}

if (! function_exists('esc_attr__')) {
    /**
     * Retrieves the translation of $text and escapes it for safe use in an attribute.
     *
     * Stand-in: English only (docs/stand-ins.md).
     *
     * @since WM-0.5.0
     * @since WP-2.8.0
     *
     * @param  string  $text  Text to translate.
     * @param  string  $domain  Optional. Text domain. Default 'default'.
     * @return string Translated text on success, original text on failure.
     */
    function esc_attr__($text, $domain = 'default')
    {
        return esc_attr(apply_filters('gettext', $text, $text, $domain));
    }
}

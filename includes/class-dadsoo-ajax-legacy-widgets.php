<?php
/**
 * Backwards-compatible aliases for widget names used before this plugin's
 * naming was cleaned up (first `elinweb`/`elin` → `dadsoo`, then the
 * `agax` typo → `ajax`).
 *
 * Elementor stores `widgetType` inside the page data, so renaming a widget
 * would blank out every section built with an older version. These
 * subclasses keep every old identifier alive while staying out of the
 * widget panel.
 *
 * @package Dadsoo_Ajax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dadsoo_Agax_Legacy_Form_Widget extends Dadsoo_Ajax_Elementor_Form_Widget
{
    public function get_name()
    {
        return 'dadsoo-agax-comment-form';
    }

    public function show_in_panel()
    {
        return false;
    }
}

class Dadsoo_Agax_Legacy_Comments_Widget extends Dadsoo_Ajax_Elementor_Comments_Widget
{
    public function get_name()
    {
        return 'dadsoo-agax-comments';
    }

    public function show_in_panel()
    {
        return false;
    }
}

class Dadsoo_Elinweb_Legacy_Form_Widget extends Dadsoo_Ajax_Elementor_Form_Widget
{
    public function get_name()
    {
        return 'elinweb-agax-comment-form';
    }

    public function show_in_panel()
    {
        return false;
    }
}

class Dadsoo_Elinweb_Legacy_Comments_Widget extends Dadsoo_Ajax_Elementor_Comments_Widget
{
    public function get_name()
    {
        return 'elinweb-agax-comments';
    }

    public function show_in_panel()
    {
        return false;
    }
}

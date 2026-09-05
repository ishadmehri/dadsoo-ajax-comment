<?php
/**
 * Backwards-compatible aliases for the widget names used before 3.0.0.
 *
 * Elementor stores `widgetType` inside the page data, so renaming a widget would
 * blank out every section built with the old plugin. These subclasses keep the
 * old identifiers alive while staying out of the widget panel.
 *
 * @package Dadsoo_Agax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dadsoo_Agax_Legacy_Form_Widget extends Dadsoo_Agax_Elementor_Form_Widget
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

class Dadsoo_Agax_Legacy_Comments_Widget extends Dadsoo_Agax_Elementor_Comments_Widget
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

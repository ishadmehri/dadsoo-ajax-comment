<?php
/**
 * Elementor widget for the Dadsoo Ajax comment form.
 *
 * @package Dadsoo_Ajax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dadsoo_Ajax_Elementor_Form_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'dadsoo-ajax-comment-form';
    }

    public function get_title()
    {
        return __('Dadsoo Ajax Comment Form', 'dadsoo-ajax-comment');
    }

    public function get_icon()
    {
        return 'eicon-form-horizontal';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('comment', 'form', 'dadsoo', 'ajax');
    }

    public function get_style_depends()
    {
        return array('dadsoo-ajax-comment');
    }

    public function get_script_depends()
    {
        return array('dadsoo-ajax-comment');
    }

    protected function render()
    {
        echo dadsoo_ajax_comment()->render_comment_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

<?php
/**
 * Elementor widget for the Dadsoo Agax comment form.
 *
 * @package Dadsoo_Agax_Comment
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dadsoo_Agax_Elementor_Form_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'dadsoo-agax-comment-form';
    }

    public function get_title()
    {
        return __('Dadsoo Agax Comment Form', 'dadsoo-agax-comment');
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
        return array('comment', 'form', 'dadsoo', 'agax');
    }

    public function get_style_depends()
    {
        return array('dadsoo-agax-comment');
    }

    public function get_script_depends()
    {
        return array('dadsoo-agax-comment');
    }

    protected function render()
    {
        echo dadsoo_agax_comment()->render_comment_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

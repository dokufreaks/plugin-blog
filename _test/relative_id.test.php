<?php

/**
 * Class plugin_include_relative_id_test
 *
 * @group plugin_blog
 * @group plugins
 */
class plugin_blog_relative_id_test extends DokuWikiTest {
    public function setup() {
        $this->pluginsEnabled[] = 'include';
        $this->pluginsEnabled[] = 'blog';
        parent::setup();
    }

    public function test_simple_name_is_absolute() {

    }
}

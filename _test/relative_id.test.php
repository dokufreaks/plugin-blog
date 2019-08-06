<?php

/**
 * Class plugin_include_relative_id_test
 *
 * @group plugin_blog
 * @group plugins
 */
class plugin_blog_relative_id_test extends DokuWikiTest {
    private $blog_content = 'waeWa2oh';

    public function setup() {
        $this->pluginsEnabled[] = 'include';
        $this->pluginsEnabled[] = 'blog';
        parent::setup();
        saveWikiText('blog_content:test', $this->blog_content, 'Create test page with random string for blog test');
    }

    public function test_simple_name_is_absolute() {
        saveWikiText('blog:start', '{{blog>blog_content}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains($this->blog_content, $html);
    }

    public function test_relative_blog() {
        saveWikiText('blog:start', '{{blog>..:blog_content}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains($this->blog_content, $html);
    }

    public function test_absolute_blog() {
        saveWikiText('blog:start', '{{blog>:blog_content}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains($this->blog_content, $html);
    }

    public function test_empty_blog() {
        global $conf;
        saveWikiText('blog:start', '{{blog>}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertNotContains($this->blog_content, $html);

        $conf['plugin']['blog']['namespace'] = 'blog_content';
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains($this->blog_content, $html);

    }
}

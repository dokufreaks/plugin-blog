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
        saveWikiText('blog:start', '{{blog>wiki}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains('Formatting Syntax', $html);
    }

    public function test_relative_blog() {
        saveWikiText('blog:start', '{{blog>..:wiki}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains('Formatting Syntax', $html);
    }

    public function test_absolute_blog() {
        saveWikiText('blog:start', '{{blog>:wiki}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains('Formatting Syntax', $html);
    }

    public function test_empty_blog() {
        global $conf;
        saveWikiText('blog:start', '{{blog>}}', 'Created blog for wiki ns');
        $html = p_wiki_xhtml('blog:start');
        $this->assertNotContains('Formatting Syntax', $html);

        $conf['plugin']['blog']['namespace'] = 'wiki';
        $html = p_wiki_xhtml('blog:start');
        $this->assertContains('Formatting Syntax', $html);

    }
}

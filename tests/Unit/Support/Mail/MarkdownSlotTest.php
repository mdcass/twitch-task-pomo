<?php

namespace Tests\Unit\Support\Mail;

use App\Support\Mail\MarkdownSlot;
use Tests\TestCase;

class MarkdownSlotTest extends TestCase
{
    public function test_it_leaves_unindented_text_unchanged(): void
    {
        $content = "Hello\nWorld";

        $this->assertSame($content, MarkdownSlot::toText($content));
    }

    public function test_it_dedents_formatter_indented_markdown_before_html_parsing(): void
    {
        $html = MarkdownSlot::toHtml("    # Welcome\n\n    Please confirm your email.")->toHtml();

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Welcome', $html);
        $this->assertStringContainsString('<p>Please confirm your email.</p>', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    public function test_it_preserves_relative_indentation_when_dedenting_text(): void
    {
        $content = "    Parent\n        Child";

        $this->assertSame("Parent\n    Child", MarkdownSlot::toText($content));
    }

    public function test_it_ignores_rendered_html_when_calculating_common_indentation(): void
    {
        $content = <<<'TEXT'
        Intro line

            <table class="action">
                <tr>
                    <td>Button</td>
                </tr>
            </table>

        Outro line
        TEXT;

        $html = MarkdownSlot::toHtml($content)->toHtml();

        $this->assertStringContainsString('<p>Intro line</p>', $html);
        $this->assertStringContainsString('<table class="action">', $html);
        $this->assertStringContainsString('<p>Outro line</p>', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    public function test_it_normalizes_each_markdown_block_independently(): void
    {
        $content = <<<'TEXT'
        # Heading

            Paragraph line
        TEXT;

        $html = MarkdownSlot::toHtml($content)->toHtml();

        $this->assertStringContainsString('<h1>Heading</h1>', $html);
        $this->assertStringContainsString('<p>Paragraph line</p>', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }
}

<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_bold_italic_and_emojis_are_rendered(): void
    {
        $this->assertSame(
            'Dieu est <strong>bon</strong> et <em>fidèle</em> 🙏',
            (string) RichText::toHtml('Dieu est **bon** et *fidèle* 🙏')
        );
        $this->assertSame('<strong><em>Alléluia</em></strong>', (string) RichText::toHtml('***Alléluia***'));
        $this->assertSame('<strong>gras <em>et</em> fin</strong>', (string) RichText::toHtml('**gras *et* fin**'));
        $this->assertSame('super<em>bien</em>', (string) RichText::toHtml('super*bien*'));
    }

    public function test_html_is_escaped_and_escaped_stars_stay_literal(): void
    {
        $this->assertSame('&lt;script&gt;x&lt;/script&gt; <strong>ok</strong>', (string) RichText::toHtml('<script>x</script> **ok**'));
        $this->assertSame('prix *promo* 2*3', (string) RichText::toHtml('prix \*promo\* 2\*3'));
    }

    public function test_markers_without_text_or_across_lines_are_left_untouched(): void
    {
        $this->assertSame('** a ** et * b *', (string) RichText::toHtml('** a ** et * b *'));
        $this->assertSame("**début\nfin**", (string) RichText::toHtml("**début\nfin**"));
    }

    public function test_plain_removes_markers(): void
    {
        $this->assertSame("gras et italique\n🙏 2*3", RichText::plain("**gras** et *italique*\n🙏 2\*3"));
        $this->assertSame('', RichText::plain(null));
    }
}

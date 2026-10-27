<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_bold_italic_and_emojis_are_rendered(): void
    {
        $this->assertSame(
            '<p>Dieu est <strong>bon</strong> et <em>fidèle</em> 🙏</p>',
            (string) RichText::toHtml('Dieu est **bon** et *fidèle* 🙏')
        );
        $this->assertSame('<p><strong><em>Alléluia</em></strong></p>', (string) RichText::toHtml('***Alléluia***'));
        $this->assertSame('<p><strong>gras <em>et</em> fin</strong></p>', (string) RichText::toHtml('**gras *et* fin**'));
        $this->assertSame('<p>super<em>bien</em></p>', (string) RichText::toHtml('super*bien*'));
        // Syntaxe de l'application : __italique__, ~~barré~~.
        $this->assertSame('<p><strong><em>Gloire</em></strong> et <s>peur</s></p>', (string) RichText::toHtml('**__Gloire__** et ~~peur~~'));
        $this->assertSame('<p>mon_fichier_nom</p>', (string) RichText::toHtml('mon_fichier_nom'));
    }

    public function test_html_is_escaped_and_escaped_stars_stay_literal(): void
    {
        $this->assertSame('<p>&lt;script&gt;x&lt;/script&gt; <strong>ok</strong></p>', (string) RichText::toHtml('<script>x</script> **ok**'));
        $this->assertSame('<p>prix *promo* 2*3</p>', (string) RichText::toHtml('prix \*promo\* 2\*3'));
    }

    public function test_markers_without_text_or_across_lines_are_left_untouched(): void
    {
        $this->assertSame('<p>** a ** et * b *</p>', (string) RichText::toHtml('** a ** et * b *'));
        $this->assertSame('<p>**début<br>fin**</p>', (string) RichText::toHtml("**début\nfin**"));
    }

    public function test_paragraphs_headings_quotes_and_lists(): void
    {
        $this->assertSame(
            '<h3>Ma guérison</h3><p>Ligne 1<br>Ligne 2</p><p>Nouveau paragraphe</p>'
            . '<blockquote>Car rien n&#039;est impossible<br>à Dieu</blockquote>'
            . '<ul><li>prière</li><li><strong>foi</strong></li></ul><ol><li>un</li><li>deux</li></ol>',
            (string) RichText::toHtml("# Ma guérison\nLigne 1\nLigne 2\n\nNouveau paragraphe\n> Car rien n'est impossible\n> à Dieu\n- prière\n- **foi**\n1. un\n2. deux")
        );
        // Sans espace après « # » ou « - » : texte ordinaire.
        $this->assertSame('<p>#Gloire -5 °C</p>', (string) RichText::toHtml('#Gloire -5 °C'));
    }

    public function test_links_are_safe(): void
    {
        $this->assertSame(
            '<p>Voir <a href="https://exemple.org/a_b__c?x=1&amp;y=2" target="_blank" rel="nofollow noopener noreferrer ugc"><strong>ce site</strong></a></p>',
            (string) RichText::toHtml('Voir [**ce site**](https://exemple.org/a_b__c?x=1&y=2)')
        );
        // Adresse nue : lien ; « javascript: » : jamais un lien.
        $this->assertSame(
            '<p>Lire <a href="https://bible.com/jn3" target="_blank" rel="nofollow noopener noreferrer ugc">https://bible.com/jn3</a>.</p>',
            (string) RichText::toHtml('Lire https://bible.com/jn3.')
        );
        $this->assertSame('<p>[clic](javascript:alert(1))</p>', (string) RichText::toHtml('[clic](javascript:alert(1))'));
    }

    public function test_plain_removes_markers(): void
    {
        $this->assertSame("gras et italique\n🙏 2*3", RichText::plain("**gras** et *italique*\n🙏 2\*3"));
        $this->assertSame("Titre\ncitation\nliste\n1. un\nce site", RichText::plain("# Titre\n> citation\n- liste\n1. un\n[ce site](https://exemple.org)"));
        $this->assertSame('', RichText::plain(null));
    }
}

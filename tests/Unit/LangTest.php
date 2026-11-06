<?php

namespace Javaabu\Workflows\Tests\Unit;

use Javaabu\Workflows\Definitions\Condition;
use Javaabu\Workflows\Support\Strings;
use Javaabu\Workflows\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LangTest extends TestCase
{
    /** Strings in docblock examples, not shown by the package */
    protected const array EXAMPLES = ['Department head', 'Create an invoice'];

    /**
     * @return array<int, string>
     */
    protected function usedStrings(): array
    {
        $root = dirname(__DIR__, 2);
        $strings = [];

        foreach (['src', 'resources/views'] as $folder) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$folder}")) as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                preg_match_all("/(?:__|trans_choice)\\('((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $string) {
                    $strings[] = stripslashes($string);
                }
            }
        }

        // The condition operators are translated from Condition::OPERATORS
        foreach (Condition::OPERATORS as $label) {
            $strings[] = $label;
        }

        return array_values(array_diff(array_unique($strings), self::EXAMPLES));
    }

    /**
     * @return array<string, string>
     */
    protected function lang(string $locale): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2) . "/lang/{$locale}.json"), true);
    }

    #[Test]
    public function it_has_every_string_in_english_and_dhivehi(): void
    {
        $en = $this->lang('en');
        $dv = $this->lang('dv');

        foreach ($this->usedStrings() as $string) {
            $this->assertArrayHasKey($string, $en, "lang/en.json is missing \"{$string}\"");
            $this->assertArrayHasKey($string, $dv, "lang/dv.json is missing \"{$string}\"");
        }

        $this->assertEquals(array_keys($en), array_keys($dv));
    }

    #[Test]
    public function it_keeps_the_placeholders_in_dhivehi(): void
    {
        foreach ($this->lang('dv') as $key => $translation) {
            preg_match_all('/:[a-z_]+/', $key, $expected);
            preg_match_all('/:[a-z_]+/', $translation, $actual);

            sort($expected[0]);
            sort($actual[0]);

            $this->assertEquals(array_unique($expected[0]), array_unique($actual[0]), "Placeholders differ in \"{$key}\"");
            $this->assertMatchesRegularExpression('/\p{Thaana}/u', $translation, "\"{$key}\" isn't in Thaana");
        }
    }

    #[Test]
    public function it_translates_the_pages(): void
    {
        $this->app->setLocale('dv');

        $this->assertSame('ވޯކްފްލޯތައް', __('Workflows'));
        $this->assertSame('ވޯކްފްލޯތައް', Strings::workflows()['title']);
        $this->assertSame('ފިޔަވަޅެއް އިތުރުކުރޭ', Strings::builder()['add_step']);
        $this->assertSame('ނިންމުން', Strings::stepType('decision'));
    }

    #[Test]
    public function the_styles_use_logical_properties(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/resources/css/workflows.css');

        $this->assertDoesNotMatchRegularExpression('/(margin|padding|border)-(left|right)|(^|[\s;{])(left|right)\s*:|text-align:\s*(left|right)|float:\s*(left|right)/m', $css);

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/resources')) as $file) {
            if (preg_match('/\.(php|tsx)$/', $file->getFilename())) {
                $this->assertDoesNotMatchRegularExpression('/\b(ml|mr|pl|pr|left|right)-\d|\btext-(left|right)\b/', file_get_contents($file->getPathname()), "{$file->getFilename()} uses a physical direction utility");
            }
        }
    }
}

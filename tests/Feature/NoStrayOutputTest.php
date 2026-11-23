<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Um byte escrito antes do `<?php` (linha em branco, BOM) ou depois de um `?>`
 * é enviado como corpo da resposta antes de o Laravel emitir os cabeçalhos.
 * O PHP então fecha os cabeçalhos com o padrão (text/html) e o Laravel perde
 * X-Inertia, Content-Type, Cache-Control e Set-Cookie, o que quebra o Inertia
 * ("plain JSON response was received") e a sessão.
 */
class NoStrayOutputTest extends TestCase
{
    public function test_no_php_file_emits_output_outside_php_tags(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $path) {
            $contents = file_get_contents($path);
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            if (! str_starts_with($contents, '<?php')) {
                $offenders[] = "$relative: não começa com <?php (linha em branco ou BOM antes da tag)";
            }
            if (str_contains($contents, '?>')) {
                $offenders[] = "$relative: contém tag de fechamento ?>";
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    /** @return iterable<string> */
    private function phpFiles(): iterable
    {
        foreach (['app', 'bootstrap', 'config', 'routes', 'database'] as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                $name = $file->getFilename();
                if ($file->isFile()
                    && str_ends_with($name, '.php')
                    && ! str_ends_with($name, '.blade.php')
                    && ! str_contains($file->getPath(), DIRECTORY_SEPARATOR.'cache')) {
                    yield $file->getPathname();
                }
            }
        }

        yield base_path('public/index.php');
    }
}

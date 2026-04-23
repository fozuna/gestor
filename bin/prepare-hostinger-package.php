<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$outputRoot = $root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'hostinger-upload';
$privateRoot = $outputRoot . DIRECTORY_SEPARATOR . 'private';
$publicHtmlRoot = $outputRoot . DIRECTORY_SEPARATOR . 'public_html';

$requiredPaths = [
    $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' => 'Execute `composer install --no-dev --optimize-autoloader` localmente antes de gerar o pacote.',
    $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.css' => 'Execute `npm run build` localmente antes de gerar o pacote.',
    $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.js' => 'Execute `npm run build` localmente antes de gerar o pacote.',
];

foreach ($requiredPaths as $path => $message) {
    if (!file_exists($path)) {
        fwrite(STDERR, '[prepare-hostinger-package] Requisito ausente: ' . $path . PHP_EOL . $message . PHP_EOL);
        exit(1);
    }
}

deleteTree($outputRoot);
mkdirSafe($privateRoot);
mkdirSafe($publicHtmlRoot);

$privateItems = [
    'app',
    'bin',
    'bootstrap',
    'config',
    'install',
    'resources',
    'routes',
    'scripts',
    'storage',
    'vendor',
    'public',
    '.env.example',
    'composer.json',
    'composer.lock',
];

foreach ($privateItems as $item) {
    copyPath($root . DIRECTORY_SEPARATOR . $item, $privateRoot . DIRECTORY_SEPARATOR . $item);
}

copyPath($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets', $publicHtmlRoot . DIRECTORY_SEPARATOR . 'assets');
copyPath($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '.htaccess', $publicHtmlRoot . DIRECTORY_SEPARATOR . '.htaccess');

$publicIndex = <<<'PHP'
<?php
declare(strict_types=1);

require __DIR__ . '/../private/public/index.php';
PHP;

file_put_contents($publicHtmlRoot . DIRECTORY_SEPARATOR . 'index.php', $publicIndex . PHP_EOL);

$readme = <<<'TXT'
Pacote pronto para Hostinger (hospedagem compartilhada)

1. Envie a pasta "public_html" para o diretório public_html do subdomínio.
2. Envie a pasta "private" como irmã de public_html no mesmo nível.
3. Copie .env.example para private/.env e ajuste as variáveis reais.
   - Alternativa: edite private/config/config.php se preferir não depender de .env
4. Defina permissões:
   - pastas: 755
   - arquivos: 644
5. Garanta escrita em private/storage.

Sem npm/composer no servidor:
- vendor já está incluído em private/vendor
- assets buildados já estão incluídos em public_html/assets
TXT;

file_put_contents($outputRoot . DIRECTORY_SEPARATOR . 'README-HOSTINGER.txt', $readme . PHP_EOL);

echo '[prepare-hostinger-package] Pacote gerado em: ' . $outputRoot . PHP_EOL;
echo '[prepare-hostinger-package] Upload público: ' . $publicHtmlRoot . PHP_EOL;
echo '[prepare-hostinger-package] Upload privado: ' . $privateRoot . PHP_EOL;

function mkdirSafe(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Não foi possível criar diretório: ' . $path);
    }
}

function copyPath(string $source, string $destination): void
{
    if (is_dir($source)) {
        mkdirSafe($destination);
        $items = scandir($source);
        if ($items === false) {
            throw new RuntimeException('Não foi possível listar diretório: ' . $source);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            copyPath($source . DIRECTORY_SEPARATOR . $item, $destination . DIRECTORY_SEPARATOR . $item);
        }
        return;
    }

    if (!is_file($source)) {
        return;
    }

    $dir = dirname($destination);
    mkdirSafe($dir);
    if (!copy($source, $destination)) {
        throw new RuntimeException('Não foi possível copiar arquivo: ' . $source);
    }
}

function deleteTree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        deleteTree($path . DIRECTORY_SEPARATOR . $item);
    }

    @rmdir($path);
}

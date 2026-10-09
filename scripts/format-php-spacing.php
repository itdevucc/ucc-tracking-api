<?php

// Formatea únicamente espacios dentro de funciones. No ejecuta Laravel ni usa BD.
$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$files = [$root.'/bootstrap/app.php'];

foreach (['app', 'routes'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

function significantTokens(string $source): array
{
    return array_values(array_filter(token_get_all($source, TOKEN_PARSE), fn ($token) => ! is_array($token) || $token[0] !== T_WHITESPACE));
}

function formatFunctionSpacing(string $source): string
{
    $tokens = token_get_all($source, TOKEN_PARSE);
    $depth = 0;
    $functions = [];
    $pendingFunction = false;
    $previous = null;
    $result = '';

    foreach ($tokens as $index => $token) {
        $text = is_array($token) ? $token[1] : $token;

        if (is_array($token) && $token[0] === T_FUNCTION) {
            $pendingFunction = true;
        }

        if ($text === '{') {
            $depth++;

            if ($pendingFunction) {
                $functions[] = $depth;
                $pendingFunction = false;
            }
        } elseif (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $depth++;
        } elseif ($text === '}') {
            if ($functions !== [] && end($functions) === $depth) {
                array_pop($functions);
            }

            $depth--;
        } elseif ($text === ';') {
            // Firmas de interfaces y métodos abstractos no tienen cuerpo.
            $pendingFunction = false;
        }

        if (is_array($token) && $token[0] === T_WHITESPACE) {
            $next = $tokens[$index + 1] ?? null;

            if ($functions !== [] && str_contains($text, "\n")
                && (in_array($previous, ['{', ';', '}'], true) || $next === '}')) {
                preg_match('/[\t ]*$/', $text, $indent);
                $newline = str_contains($source, "\r\n") ? "\r\n" : "\n";
                $text = $newline.$newline.($indent[0] ?? '');
            }
        } else {
            $previous = $text;
        }

        $result .= $text;
    }

    return $result;
}

$changed = 0;

foreach ($files as $path) {
    $original = file_get_contents($path);
    $formatted = formatFunctionSpacing($original);

    // Comparar tokens sin espacios: jamás escribir si cambió código o comentarios.
    $before = array_map(fn ($token) => is_array($token) ? [$token[0], $token[1]] : $token, significantTokens($original));
    $after = array_map(fn ($token) => is_array($token) ? [$token[0], $token[1]] : $token, significantTokens($formatted));

    if ($before !== $after) {
        throw new RuntimeException('El formato alteró tokens: '.$path);
    }

    if ($formatted !== $original) {
        $changed++;

        if ($write) {
            if (file_put_contents($path, $formatted) === false) {
                throw new RuntimeException('No se pudo escribir: '.$path);
            }
        }
    }
}

echo count($files).' archivos revisados; '.$changed.($write ? ' formateados' : ' pendientes de formato').PHP_EOL;
exit(! $write && $changed > 0 ? 1 : 0);

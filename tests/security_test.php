<?php
declare(strict_types=1);

require __DIR__ . '/../src/Security.php';

assertSame('42', e(42), 'Los enteros deben convertirse a texto.');
assertSame('texto', e('texto'), 'Los textos deben conservarse.');
assertSame('', e(null), 'Null debe producir una cadena vacía.');
assertSame(
    '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
    e('<script>alert("x")</script>'),
    'El HTML debe codificarse por completo.'
);

echo "PASS: helper de escape seguro\n";

function assertSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "FAIL: {$message} Esperado: {$expected}; obtenido: {$actual}"
        );
    }
}

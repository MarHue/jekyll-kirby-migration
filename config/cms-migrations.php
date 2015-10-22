<?php
use MarHue\Migrations\JekyllKirby\Formats;

return [
    'formats' => [
        'input' => ['Jekyll' => Formats\Input\Jekyll::class],
        'output' => ['Kirby' => Formats\Output\Kirby::class]
    ]
];
<?php

use App\Models\CodeSequence;

test('next increments a fresh counter starting at 1', function () {
    expect(CodeSequence::next('laptop', ''))->toBe(1)
        ->and(CodeSequence::next('laptop', ''))->toBe(2)
        ->and(CodeSequence::next('laptop', ''))->toBe(3);
});

test('each type keeps its own independent counter', function () {
    CodeSequence::next('laptop', '');
    CodeSequence::next('laptop', '');

    expect(CodeSequence::next('sale', ''))->toBe(1);
});

test('each period key keeps its own independent counter', function () {
    CodeSequence::next('sale', '2609');
    CodeSequence::next('sale', '2609');

    expect(CodeSequence::next('sale', '2610'))->toBe(1)
        ->and(CodeSequence::next('sale', '2609'))->toBe(3);
});

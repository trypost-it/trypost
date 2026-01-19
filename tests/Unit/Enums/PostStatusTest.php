<?php

use App\Enums\Post\Status;

test('post status has correct values', function () {
    expect(Status::Draft->value)->toBe('draft');
    expect(Status::Scheduled->value)->toBe('scheduled');
    expect(Status::Publishing->value)->toBe('publishing');
    expect(Status::Published->value)->toBe('published');
    expect(Status::PartiallyPublished->value)->toBe('partially_published');
    expect(Status::Failed->value)->toBe('failed');
});

test('post status has labels', function () {
    expect(Status::Draft->label())->toBe('Rascunho');
    expect(Status::Scheduled->label())->toBe('Agendado');
    expect(Status::Publishing->label())->toBe('Publicando');
    expect(Status::Published->label())->toBe('Publicado');
    expect(Status::PartiallyPublished->label())->toBe('Parcialmente Publicado');
    expect(Status::Failed->label())->toBe('Falhou');
});

test('post status has colors', function () {
    expect(Status::Draft->color())->toBe('gray');
    expect(Status::Scheduled->color())->toBe('blue');
    expect(Status::Publishing->color())->toBe('yellow');
    expect(Status::Published->color())->toBe('green');
    expect(Status::PartiallyPublished->color())->toBe('orange');
    expect(Status::Failed->color())->toBe('red');
});
